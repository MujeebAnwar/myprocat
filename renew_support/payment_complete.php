<?php
/**
 * Renew Support post-payment handler.
 * Saves casepad_payment_invoices, renew_support_orders, and extends room_permissions.
 */

require_once DOCUMENT_ROOT . '/store/helper.php';
require_once DOCUMENT_ROOT . '/Service/EmailService.php';
require_once __DIR__ . '/helper.php';

function renew_support_payment_method_label($paymentMethodId)
{
	switch ($paymentMethodId) {
		case '3': return 'American Express';
		case '4': return 'Visa';
		case '5': return 'MasterCard';
		case '6': return 'Discover';
		case '7': return 'JCB';
		case 'D': return 'Debit Card';
		case 'O': return 'Other';
		case 'C': return 'ACH';
		default: return 'Other';
	}
}

function renew_support_next_invoice_number($DB)
{
	$invoiceNumber = array('invoice_number');
	$DB->sql(
		'SELECT MAX(invoice_number) as invoice_number FROM casepad_payment_invoices',
		array(),
		$invoiceNumber
	);

	if (
		empty($invoiceNumber)
		|| !isset($invoiceNumber[0]['invoice_number'])
		|| is_null($invoiceNumber[0]['invoice_number'])
		|| $invoiceNumber[0]['invoice_number'] === 'invoice_number'
	) {
		return 10800;
	}

	return (int)$invoiceNumber[0]['invoice_number'] + 1;
}

/**
 * Room titles granted by a SKU (via renew_support_sku_rooms → products).
 *
 * @return array<int, array{product_key:string,room_title:string,grant_can_read:int,grant_can_upload:int,grant_can_remove:int}>
 */
function renew_support_get_sku_grant_rooms($DB, $sku_id)
{
	$columns = array(
		'product_key',
		'room_title',
		'grant_can_read',
		'grant_can_upload',
		'grant_can_remove',
	);
	$ok = $DB->sql(
		'SELECT
			p.product_key,
			p.room_title,
			sr.grant_can_read,
			sr.grant_can_upload,
			sr.grant_can_remove
		 FROM ' . RENEW_SUPPORT_TABLE_SKU_ROOMS . ' sr
		 INNER JOIN ' . RENEW_SUPPORT_TABLE_PRODUCTS . ' p ON p.id = sr.product_id
		 WHERE sr.sku_id = ?
		 ORDER BY sr.sort_order ASC',
		array('i', (int)$sku_id),
		$columns
	);

	$rooms = array();
	if ($ok === false || empty($columns)) {
		return $rooms;
	}
	foreach ($columns as $row) {
		if (!is_array($row) || !isset($row['room_title']) || $row['room_title'] === 'room_title') {
			continue;
		}
		$rooms[] = $row;
	}
	return $rooms;
}

/**
 * Extend each SKU room permission from its existing expires date.
 * - Student SKU only (`student`): +3 years
 * - All other SKUs (including student_xp / student_vr): +365 days
 *
 * @param string $sku_key
 * @return array{expires_at:string,rooms:array}
 */
function renew_support_apply_room_grants($DB, $id_user, array $grant_rooms, $sku_key = '')
{
	$isStudent = ($sku_key === 'student');
	$interval = $isStudent ? '+3 years' : '+365 days';

	$expiresAt = date('Y-m-d H:i:s', strtotime($interval));
	$granted = array();

	foreach ($grant_rooms as $room) {
		$roomTitle = $room['room_title'];
		$canRead = isset($room['grant_can_read']) ? (int)$room['grant_can_read'] : 1;
		$canUpload = isset($room['grant_can_upload']) ? (int)$room['grant_can_upload'] : 0;
		$canRemove = isset($room['grant_can_remove']) ? (int)$room['grant_can_remove'] : 0;

		$idRoomRows = array('id_room', 'expires');
		$DB->sql(
			'SELECT r.id_room, rp.expires
			 FROM rooms r
			 LEFT JOIN room_permissions rp
				ON rp.id_room = r.id_room AND rp.id_user = ?
			 WHERE r.room_title = ?
			 LIMIT 1',
			array('ss', $id_user, $roomTitle),
			$idRoomRows
		);

		$idRoom = null;
		$currentExpires = null;
		foreach ($idRoomRows as $row) {
			if (!is_array($row) || !isset($row['id_room']) || $row['id_room'] === 'id_room') {
				continue;
			}
			$idRoom = (int)$row['id_room'];
			$currentExpires = !empty($row['expires']) ? $row['expires'] : null;
			break;
		}

		if (!$idRoom) {
			continue;
		}

		// Add interval onto the existing expire date (fallback to now if none).
		$baseTs = time();
		if ($currentExpires) {
			$currentTs = strtotime($currentExpires);
			if ($currentTs !== false) {
				$baseTs = $currentTs;
			}
		}
		$newExpires = date('Y-m-d H:i:s', strtotime($interval, $baseTs));
		if (strtotime($newExpires) > strtotime($expiresAt)) {
			$expiresAt = $newExpires;
		}

		$existing = array('room_permissions_id');
		$DB->sql(
			'SELECT room_permissions_id FROM room_permissions WHERE id_user = ? AND id_room = ? LIMIT 1',
			array('si', $id_user, $idRoom),
			$existing
		);

		$hasExisting = false;
		foreach ($existing as $row) {
			if (is_array($row) && isset($row['room_permissions_id']) && $row['room_permissions_id'] !== 'room_permissions_id') {
				$hasExisting = true;
				break;
			}
		}

		if ($hasExisting) {
			$DB->sql(
				'UPDATE room_permissions
				 SET can_read = ?, can_upload = ?, can_remove = ?, expires = ?
				 WHERE id_user = ? AND id_room = ?',
				array('iiissi', $canRead, $canUpload, $canRemove, $newExpires, $id_user, $idRoom)
			);
		} else {
			$DB->sql(
				'INSERT INTO room_permissions (id_user, id_room, can_read, can_upload, can_remove, expires)
				 VALUES (?,?,?,?,?,?)',
				array('siiiis', $id_user, $idRoom, $canRead, $canUpload, $canRemove, $newExpires)
			);
		}

		$granted[] = array(
			'product_key' => $room['product_key'],
			'room_title' => $roomTitle,
			'expires' => $newExpires,
		);
	}

	return array(
		'expires_at' => $expiresAt,
		'rooms' => $granted,
	);
}

function renew_support_track_student_renewal($DB, $id_user, $sku_key, $order_id, $max_renewals)
{
	if ($max_renewals === null) {
		return;
	}

	$productKey = 'student';
	if ($sku_key === 'student_xp') {
		$productKey = 'student_xp';
	} else if ($sku_key === 'student_vr') {
		$productKey = 'student_vr';
	}

	$state = renew_support_get_student_renewal_state($DB, $id_user, $productKey);
	$newCount = (int)$state['renewal_count'] + 1;
	$terminated = ($newCount >= (int)$max_renewals) ? 1 : 0;
	$now = date('Y-m-d H:i:s');

	$DB->sql(
		'INSERT INTO ' . RENEW_SUPPORT_TABLE_STUDENT_RENEWALS . '
			(id_user, product_key, renewal_count, last_order_id, last_renewed_at, terminated)
		 VALUES (?,?,?,?,?,?)
		 ON DUPLICATE KEY UPDATE
			renewal_count = VALUES(renewal_count),
			last_order_id = VALUES(last_order_id),
			last_renewed_at = VALUES(last_renewed_at),
			terminated = VALUES(terminated)',
		array('ssissi', $id_user, $productKey, $newCount, (int)$order_id, $now, $terminated)
	);
}

/**
 * Record invoice in casepad_payment_invoices (no hour/storage credits).
 */
function renew_support_record_invoice($DB, array $payload)
{
	$discount = isset($payload['discount']) ? (int)$payload['discount'] : 0;
	// Store unit price as rate with hours=1 so invoice total = rate * hours in UIs that multiply.
	$rate = (float)$payload['total_amount'];
	$hours = 1;

	$ok = $DB->sql(
		'INSERT INTO casepad_payment_invoices
			(`id_owner`,`subscribed_plan_id`,`myprocat_purchase_id`,`invoice_number`,`transaction_id`,`van_reference`,`last_four_digits`,`invoice_date`,`payment_method`,`discount`,`total_amount`,`rate`,`hours`,`payment_response`)
		 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
		array(
			'ssisssssssssis',
			$payload['user_id'],
			'',
			0,
			$payload['invoice_number'],
			$payload['transaction_id'],
			$payload['van_reference'],
			$payload['last4'],
			$payload['invoice_date'],
			$payload['payment_method'],
			$discount,
			$payload['total_amount'],
			$rate,
			$hours,
			$payload['json_response'],
		)
	);

	if ($ok === false) {
		error_log(isset($DB->error) ? $DB->error : 'renew_support invoice insert failed');
		throw new Exception('Unable to save payment invoice.');
	}

	return (int)$DB->iid();
}

function renew_support_send_invoice_email(array $payload)
{
	global $UserAccount;

	if (!$UserAccount || empty($UserAccount->user_details['email'])) {
		return;
	}

	$userDetails = $UserAccount->user_details;
	$customerEmail = $userDetails['email'];
	$customerName = trim(
		(isset($userDetails['first_name']) ? $userDetails['first_name'] : '') . ' ' .
		(isset($userDetails['mid_name']) ? $userDetails['mid_name'] : '') . ' ' .
		(isset($userDetails['last_name']) ? $userDetails['last_name'] : '')
	);

	$invoiceData = array(
		'invoice_number' => $payload['invoice_number'],
		'invoice_date' => date('F j, Y g:i A', strtotime($payload['invoice_date'])),
		'rate' => $payload['total_amount'],
		'hours' => 1,
		'payment_method' => $payload['payment_method'],
		'address_details' => isset($payload['address_details'])
			? json_decode($payload['address_details'], true)
			: null,
		'customer_name' => $customerName,
		'discount' => 0,
		'total_amount' => $payload['total_amount'],
		'service_description' => isset($payload['service_description'])
			? $payload['service_description']
			: 'Renew Support',
	);

	ob_start();
	include DOCUMENT_ROOT . '/store/invoice-email.php';
	$emailBody = ob_get_clean();

	send_invoice_email($customerEmail, $payload['invoice_number'], $emailBody);
}

/**
 * Full successful renew payment finalize.
 *
 * @return array{invoice_number:mixed,display_name:string,total_amount:float,tier:string,order_id:int}
 */
function renew_support_finalize_successful_payment($DB, array $payload)
{
	$user_id = $payload['user_id'];
	$sku_id = (int)$payload['sku_id'];
	$sku_key = $payload['sku_key'];
	$tier = $payload['tier'];
	$display_name = $payload['display_name'];
	$base_plan_code = $payload['base_plan_code'];
	$addon_plan_code = isset($payload['addon_plan_code']) ? $payload['addon_plan_code'] : null;
	$total_amount = (float)$payload['total_amount'];
	$base_amount = isset($payload['base_amount']) ? (float)$payload['base_amount'] : $total_amount;
	$addon_amount = isset($payload['addon_amount']) ? (float)$payload['addon_amount'] : 0.0;
	$max_renewals = isset($payload['max_renewals']) ? $payload['max_renewals'] : null;

	$invoice_number = renew_support_next_invoice_number($DB);
	$invoice_date = date('Y-m-d H:i:s', strtotime($payload['transaction_date']));

	$grantRooms = renew_support_get_sku_grant_rooms($DB, $sku_id);
	$grantResult = renew_support_apply_room_grants($DB, $user_id, $grantRooms, $sku_key);
	$expiresAt = $grantResult['expires_at'];
	$roomsJson = json_encode($grantResult['rooms']);

	$serviceDescription = $display_name . ' (' . ucfirst($tier) . ')';

	$invoicePayload = array(
		'user_id' => $user_id,
		'invoice_number' => $invoice_number,
		'transaction_id' => $payload['transaction_id'],
		'van_reference' => $payload['van_reference'],
		'last4' => $payload['last4'],
		'invoice_date' => $invoice_date,
		'payment_method' => $payload['payment_method'],
		'total_amount' => $total_amount,
		'json_response' => $payload['json_response'],
		'address_details' => isset($payload['address_details']) ? $payload['address_details'] : null,
		'service_description' => $serviceDescription,
	);

	renew_support_record_invoice($DB, $invoicePayload);

	$orderOk = $DB->sql(
		'INSERT INTO ' . RENEW_SUPPORT_TABLE_ORDERS . '
			(id_user, sku_id, sku_key, base_plan_code, addon_plan_code, tier,
			 base_amount, addon_amount, total_amount, status, expires_at, rooms_granted_json,
			 invoice_number, transaction_id, payment_method, payment_response, applied_at, notes)
		 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
		array(
			'sissssdddsssssssss',
			$user_id,
			$sku_id,
			$sku_key,
			$base_plan_code,
			$addon_plan_code,
			$tier,
			$base_amount,
			$addon_amount,
			$total_amount,
			'applied',
			$expiresAt,
			$roomsJson,
			(string)$invoice_number,
			$payload['transaction_id'],
			$payload['payment_method'],
			$payload['json_response'],
			date('Y-m-d H:i:s'),
			$display_name . ' (' . ucfirst($tier) . ')',
		)
	);

	if ($orderOk === false) {
		error_log(isset($DB->error) ? $DB->error : 'renew_support order insert failed');
		throw new Exception('Unable to save renew support order.');
	}

	$order_id = (int)$DB->iid();
	renew_support_track_student_renewal($DB, $user_id, $sku_key, $order_id, $max_renewals);

	if (!empty($payload['delete_session_key'])) {
		$DB->sql(
			'DELETE FROM casepad_subscription_session WHERE transaction_key = ?',
			array('s', $payload['delete_session_key'])
		);
	}

	try {
		renew_support_send_invoice_email($invoicePayload);
	} catch (Exception $e) {
		error_log('Renew support invoice email failed: ' . $e->getMessage());
	}

	if (session_status() === PHP_SESSION_NONE) {
		session_start();
	}
	unset($_SESSION['renew_support']);

	return array(
		'invoice_number' => $invoice_number,
		'display_name' => $display_name,
		'total_amount' => $total_amount,
		'tier' => $tier,
		'order_id' => $order_id,
	);
}
