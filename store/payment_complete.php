<?php
/**
 * Shared MyProCat post-payment handler (new card callback or saved-card vault charge).
 */

require_once DOCUMENT_ROOT . '/Service/EmailService.php';
require_once DOCUMENT_ROOT . '/store/helper.php';
require_once DOCUMENT_ROOT . '/lib/account.php';

function myprocat_finalize_successful_payment($DB, array $payload)
{
	$user_id = $payload['user_id'];
	$license_id = (int)$payload['license_id'];
	$licenseType = $payload['license_type'];
	$licenseTitle = $payload['license_title'];
	$hours = (int)$payload['hours'];
	$rate = (float)$payload['rate'];
	$transaction_id = $payload['transaction_id'];
	$transaction_amount = $payload['transaction_amount'];
	$transaction_date = $payload['transaction_date'];
	$vanReference = $payload['van_reference'];
	$last4 = $payload['last4'];
	$paymentMethod = $payload['payment_method'];
	$jsonResponse = $payload['json_response'];
	$guid = $payload['guid'];
	$vault_id = $payload['vault_id'];
	$address_details = $payload['address_details'];
	$card_expiry_sql = $payload['card_expiry_sql'];
	$auto_purchase_enabled = !empty($payload['auto_purchase_enabled']) ? 1 : 0;
	$balance_threshold = $auto_purchase_enabled ? $payload['balance_threshold'] : null;
	$min_account_balance = $auto_purchase_enabled ? $payload['min_account_balance'] : null;
	$delete_session_key = isset($payload['delete_session_key']) ? $payload['delete_session_key'] : null;
	$preserve_purchase_record = !empty($payload['preserve_purchase_record']);

	$prepaid = ($licenseType === 'perpetual') ? 1 : 0;
	$discount = 0;

	$invoiceNumber = array('invoice_number');
	$DB->sql(
		'SELECT MAX(invoice_number) as invoice_number FROM casepad_payment_invoices',
		array(),
		$invoiceNumber
	);

	if (is_null($invoiceNumber[0]['invoice_number']) || $invoiceNumber[0]['invoice_number'] === 'invoice_number') {
		$invoice_number = 10800;
	} else {
		$invoice_number = (int)$invoiceNumber[0]['invoice_number'] + 1;
	}

	$invoice_date = date('Y-m-d H:i:s', strtotime($transaction_date));
	$next_payment_date = null;
	if ($licenseType === 'subscription') {
		$next_payment_date = date('Y-m-d H:i:s', strtotime('+1 month'));
	}

	$created_at = date('Y-m-d H:i:s');
	$updated_at = $created_at;
	$is_active = 1;

	$myprocat_purchase_id = 0;

	if ($preserve_purchase_record) {
		$myprocat_purchase_id = isset($payload['myprocat_purchase_id']) ? (int)$payload['myprocat_purchase_id'] : 0;
	} else {
		$existingPurchase = array('id');
		$DB->sql(
			'SELECT id FROM myprocat_purchases WHERE id_owner = ? AND is_active = 1 ORDER BY id DESC LIMIT 1',
			array('s', $user_id),
			$existingPurchase
		);

		$hasExistingPurchase = !empty($existingPurchase)
			&& isset($existingPurchase[0]['id'])
			&& $existingPurchase[0]['id'] !== 'id';

		if ($hasExistingPurchase) {
			$myprocat_purchase_id = (int)$existingPurchase[0]['id'];
			if (false === $DB->sql(
				'UPDATE myprocat_purchases SET
					license_id = ?,
					license_type = ?,
					license_title = ?,
					rate = ?,
					hours = ?,
					last_four_digits = ?,
					card_expiry_date = ?,
					vault_id = ?,
					guid = ?,
					address_details = ?,
					prepaid = ?,
					is_active = ?,
					next_payment_date = ?,
					auto_purchase_enabled = ?,
					balance_threshold = ?,
					min_account_balance = ?,
					updated_at = ?
				 WHERE id = ? AND id_owner = ?',
				array(
					'isssisssssiisiddsis',
					$license_id,
					$licenseType,
					$licenseTitle,
					$rate,
					$hours,
					$last4,
					$card_expiry_sql,
					$vault_id,
					$guid,
					$address_details,
					$prepaid,
					$is_active,
					$next_payment_date,
					$auto_purchase_enabled,
					$balance_threshold,
					$min_account_balance,
					$updated_at,
					$myprocat_purchase_id,
					$user_id,
				)
			)) {
				error_log($DB->error);
				throw new Exception('Unable to update MyProCat purchase record.');
			}
		} else {
			if (false === $DB->sql(
				'INSERT INTO myprocat_purchases (`id_owner`,`license_id`,`license_type`,`license_title`,`rate`,`hours`,`last_four_digits`,`card_expiry_date`,`vault_id`,`guid`,`address_details`,`prepaid`,`is_active`,`next_payment_date`,`auto_purchase_enabled`,`balance_threshold`,`min_account_balance`,`created_at`,`updated_at`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
				array(
					'sisssisssssiisiddss',
					$user_id,
					$license_id,
					$licenseType,
					$licenseTitle,
					$rate,
					$hours,
					$last4,
					$card_expiry_sql,
					$vault_id,
					$guid,
					$address_details,
					$prepaid,
					$is_active,
					$next_payment_date,
					$auto_purchase_enabled,
					$balance_threshold,
					$min_account_balance,
					$created_at,
					$updated_at,
				)
			)) {
				error_log($DB->error);
				throw new Exception('Unable to save MyProCat purchase record.');
			}

			$myprocat_purchase_id = (int)$DB->iid();
		}
	}

	if ($myprocat_purchase_id <= 0) {
		throw new Exception('Unable to resolve MyProCat purchase record.');
	}

	return myprocat_record_payment_invoice_and_credits($DB, array(
		'user_id'              => $user_id,
		'myprocat_purchase_id' => $myprocat_purchase_id,
		'invoice_number'       => $invoice_number,
		'transaction_id'       => $transaction_id,
		'van_reference'        => $vanReference,
		'last4'                => $last4,
		'invoice_date'         => $invoice_date,
		'payment_method'       => $paymentMethod,
		'discount'             => $discount,
		'total_amount'         => $transaction_amount,
		'rate'                 => $rate,
		'hours'                => $hours,
		'json_response'        => $jsonResponse,
		'license_title'        => $licenseTitle,
		'address_details'      => $address_details,
		'delete_session_key'   => $delete_session_key,
	));
}

/**
 * Inserts invoice + credits and sends the invoice email.
 */
function myprocat_record_payment_invoice_and_credits($DB, array $payload)
{
	global $UserAccount;
	$user_id = $payload['user_id'];
	$myprocat_purchase_id = (int)$payload['myprocat_purchase_id'];
	$hours = (int)$payload['hours'];
	$licenseTitle = $payload['license_title'];
	$discount = isset($payload['discount']) ? (int)$payload['discount'] : 0;

	$DB->sql(
		'INSERT INTO casepad_payment_invoices (`id_owner`,`subscribed_plan_id`,`myprocat_purchase_id`,`invoice_number`,`transaction_id`,`van_reference`,`last_four_digits`,`invoice_date`,`payment_method`,`discount`,`total_amount`,`rate`,`hours`,`payment_response`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
		array(
			'ssisssssssssis',
			$user_id,
			'',
			$myprocat_purchase_id,
			$payload['invoice_number'],
			$payload['transaction_id'],
			$payload['van_reference'],
			$payload['last4'],
			$payload['invoice_date'],
			$payload['payment_method'],
			$discount,
			$payload['total_amount'],
			$payload['rate'],
			$hours,
			$payload['json_response'],
		)
	);

	$invoice_id = $DB->iid();

	$DB->sql(
		'INSERT INTO casepad_minutes_credits (`id_owner`,`minutes`,`time_stamp`,`source`,`invoice_id`) VALUES (?,?,?,?,?)',
		array('ssssi', $user_id, $hours * 60, $payload['invoice_date'], $licenseTitle, $invoice_id)
	);

	$DB->sql(
		'INSERT INTO casepad_storage_credits (`id_owner`,`storage`,`time_stamp`,`source`,`invoice_id`) VALUES (?,?,?,?,?)',
		array('sissi', $user_id, 10, $payload['invoice_date'], $licenseTitle, $invoice_id)
	);

	if (!empty($payload['delete_session_key'])) {
		$DB->sql(
			'DELETE FROM casepad_subscription_session WHERE transaction_key = ?',
			array('s', $payload['delete_session_key'])
		);
	}


	$userDetails = $UserAccount->user_details;
	$customerEmail = $userDetails['email'];
	$customerName = trim($userDetails['first_name'] . ' ' . $userDetails['mid_name'] . ' ' . $userDetails['last_name']);

	$invoiceData = array(
		'service_description' => $payload['license_title'],
		'invoice_number' => $payload['invoice_number'],
		'invoice_date' => date('F j, Y g:i A', strtotime($payload['invoice_date'])),
		'rate' => $payload['rate'],
		'hours' => $hours,
		'payment_method' => $payload['payment_method'],
		'address_details' => json_decode($payload['address_details'], true),
		'customer_name' => $customerName,
		'discount' => $discount,
		'total_amount' => $payload['total_amount'],
	);

	ob_start();
	include DOCUMENT_ROOT . '/store/invoice-email.php';
	$emailBody = ob_get_clean();

	if (!empty($customerEmail)) {
		send_invoice_email($customerEmail, $payload['invoice_number'], $emailBody);
	}

	return array(
		'invoice_number' => $payload['invoice_number'],
		'license_title' => $licenseTitle,
		'total_amount' => $payload['total_amount'],
	);
}

function myprocat_payment_method_label($paymentMethodId)
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

function myprocat_validate_checkout_post($DB, $UserAccount)
{
	$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0.00;
	$licenseType = isset($_POST['license_type']) ? trim($_POST['license_type']) : '';
	$licenseId = isset($_POST['license_id']) ? intval($_POST['license_id']) : 0;
	$licenseTitle = isset($_POST['license_title']) ? trim($_POST['license_title']) : '';
	$hours = isset($_POST['hours']) ? intval($_POST['hours']) : 0;
	$rate = isset($_POST['rate']) ? floatval($_POST['rate']) : 0.00;
	$autoPurchaseEnabled = isset($_POST['auto_purchase_enabled']) && (string)$_POST['auto_purchase_enabled'] === '1';
	$balanceThreshold = null;
	$minAccountBalance = null;

	if ($autoPurchaseEnabled) {
		$balanceThreshold = isset($_POST['balance_threshold']) ? floatval($_POST['balance_threshold']) : -1;
		$minAccountBalance = isset($_POST['min_account_balance']) ? floatval($_POST['min_account_balance']) : -1;

		if ($balanceThreshold < 0 || $minAccountBalance < 0) {
			die('Invalid auto-purchase settings. Please go back and try again.');
		}
		if ($minAccountBalance < $balanceThreshold) {
			die('Minimum account balance must be at or above the balance threshold.');
		}
	}

	if ($amount <= 0 || $hours <= 0 || $rate <= 0) {
		die('Invalid payment amount. Please go back and enter valid hours.');
	}

	if (!in_array($licenseType, array('perpetual', 'subscription'), true)) {
		die('Invalid license type.');
	}

	$licenseRows = array('id', 'per_hour_amount', 'minimum_hours', 'type');
	$DB->sql(
		'SELECT id, per_hour_amount, minimum_hours, type FROM myprocat_subscription_license_table WHERE id = ? AND type = ? LIMIT 1',
		array('is', $licenseId, $licenseType),
		$licenseRows
	);

	if (!isset($licenseRows[0]['id']) || $licenseRows[0]['id'] === 'id') {
		die('Invalid license configuration.');
	}

	$dbRate = (float)$licenseRows[0]['per_hour_amount'];
	$dbMinHours = (int)$licenseRows[0]['minimum_hours'];
	$expectedAmount = round($hours * $dbRate, 2);

	if ($hours < $dbMinHours || abs($amount - $expectedAmount) > 0.01 || abs($rate - $dbRate) > 0.01) {
		die('Payment details do not match the selected license. Please go back and try again.');
	}

	return array(
		'amount' => $amount,
		'license_type' => $licenseType,
		'license_id' => $licenseId,
		'license_title' => $licenseTitle,
		'hours' => $hours,
		'rate' => $dbRate,
		'user_id' => $UserAccount->user_details['id_user'],
		'auto_purchase_enabled' => $autoPurchaseEnabled ? 1 : 0,
		'balance_threshold' => $autoPurchaseEnabled ? $balanceThreshold : null,
		'min_account_balance' => $autoPurchaseEnabled ? $minAccountBalance : null,
	);
}

?>
