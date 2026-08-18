<?php
require_once('config.php');
require_once(DOCUMENT_ROOT . '/setup/start.php');
require_once(DOCUMENT_ROOT . '/template/Master.php');

if (!isset($UserAccount) || !$UserAccount->logged_in) {
	header('Location: /signup/login.php');
	exit;
}

$invoice_id = isset($_REQUEST['invoice_number']) ? (int)$_REQUEST['invoice_number'] : 0;
if ($invoice_id <= 0) {
	header('Location: /invoices/');
	exit;
}

$invoiceRow = array(
	'id',
	'invoice_number',
	'invoice_date',
	'rate',
	'hours',
	'discount',
	'total_amount',
	'payment_method',
	'transaction_id',
	'myprocat_purchase_id',
	'subscription_address',
	'payment_response',
	'customer_name',
);

$ownerCondition = '';
$queryParams = array('s', (string)$invoice_id);
if (empty($UserAccount->user_details['is_admin'])) {
	$ownerCondition = ' AND casepad_payment_invoices.id_owner = ?';
	$queryParams = array('ss', (string)$invoice_id, $UserAccount->user_details['id_user']);
}

$sqlOk = $DB->sql(
	'SELECT
		casepad_payment_invoices.id,
		casepad_payment_invoices.invoice_number,
		casepad_payment_invoices.invoice_date,
		casepad_payment_invoices.rate,
		casepad_payment_invoices.hours,
		casepad_payment_invoices.discount,
		casepad_payment_invoices.total_amount,
		casepad_payment_invoices.payment_method,
		casepad_payment_invoices.transaction_id,
		casepad_payment_invoices.myprocat_purchase_id,
		casepad_subscribed_plan.address_details AS subscription_address,
		casepad_payment_invoices.payment_response,
		IFNULL(
				CONCAT(accounts.first_name, " ", accounts.last_name),
				CONCAT(casepad_accounts.first_name, " ", casepad_accounts.last_name)
			) AS customer_name
	FROM casepad_payment_invoices
	LEFT JOIN casepad_subscribed_plan
		ON casepad_subscribed_plan.id = NULLIF(casepad_payment_invoices.subscribed_plan_id, "")
	LEFT JOIN accounts
		ON accounts.id_user = casepad_payment_invoices.id_owner
	LEFT JOIN casepad_accounts
		ON casepad_accounts.id_user = casepad_payment_invoices.id_owner
	WHERE casepad_payment_invoices.id = ?' . $ownerCondition . '
	LIMIT 1',
	$queryParams,
	$invoiceRow
);

if ($sqlOk === false || !isset($invoiceRow[0]) || !is_array($invoiceRow[0]) || $invoiceRow[0]['id'] === 'id') {
	if (!empty($_REQUEST['modal'])) {
		header('Content-Type: text/html; charset=utf-8');
		http_response_code(404);
		echo '<div style="padding:24px;text-align:center;color:#dc3545;">Invoice not found.</div>';
		exit;
	}
	header('Location: /invoices/');
	exit;
}

$invoice = $invoiceRow[0];

$renewSupportNotes = '';
$renewExpiresAt = '';
$renewPreviousExpiresAt = '';
$transactionId = isset($invoice['transaction_id']) ? trim((string)$invoice['transaction_id']) : '';
if ($transactionId !== '') {
	$renewRows = array('notes', 'expires_at', 'rooms_granted_json');
	$renewOk = @$DB->sql(
		'SELECT notes, expires_at, rooms_granted_json
		 FROM renew_support_orders
		 WHERE transaction_id = ?
		 LIMIT 1',
		array('s', $transactionId),
		$renewRows
	);
	if ($renewOk !== false) {
		foreach ($renewRows as $row) {
			if (!is_array($row) || !isset($row['notes']) || $row['notes'] === 'notes') {
				continue;
			}
			if ($row['notes'] !== '') {
				$renewSupportNotes = $row['notes'];
			}
			if (!empty($row['expires_at']) && $row['expires_at'] !== 'expires_at') {
				$renewExpiresAt = $row['expires_at'];
			}
			if (!empty($row['rooms_granted_json']) && $row['rooms_granted_json'] !== 'rooms_granted_json') {
				$roomsGranted = json_decode($row['rooms_granted_json'], true);
				if (is_array($roomsGranted)) {
					foreach ($roomsGranted as $roomGrant) {
						if (!empty($roomGrant['previous_expires'])) {
							$renewPreviousExpiresAt = $roomGrant['previous_expires'];
							break;
						}
					}
				}
			}
			break;
		}
	}
}

$licenseTitle = '';
$myprocatAddress = '';
$purchaseId = isset($invoice['myprocat_purchase_id']) ? (int)$invoice['myprocat_purchase_id'] : 0;
if ($purchaseId > 0) {
	$purchaseRows = array('license_title', 'address_details');
	$purchaseOk = $DB->sql(
		'SELECT license_title, address_details
		 FROM myprocat_purchases
		 WHERE id = ?
		 LIMIT 1',
		array('s', (string)$purchaseId),
		$purchaseRows
	);
	if ($purchaseOk !== false) {
		foreach ($purchaseRows as $row) {
			if (!is_array($row) || !isset($row['license_title']) || $row['license_title'] === 'license_title') {
				continue;
			}
			$licenseTitle = $row['license_title'];
			$myprocatAddress = isset($row['address_details']) ? $row['address_details'] : '';
			break;
		}
	}
}

$minutesSource = '';
$creditRows = array('source');
$creditOk = $DB->sql(
	'SELECT source
	 FROM casepad_minutes_credits
	 WHERE invoice_id = ?
	 LIMIT 1',
	array('s', (string)$invoice['id']),
	$creditRows
);
if ($creditOk !== false) {
	foreach ($creditRows as $row) {
		if (is_array($row) && isset($row['source']) && $row['source'] !== 'source' && $row['source'] !== '') {
			$minutesSource = $row['source'];
			break;
		}
	}
}

$addressDetails = array();
$addressJson = '';
if (!empty($invoice['subscription_address'])) {
	$addressJson = $invoice['subscription_address'];
} else if (!empty($myprocatAddress)) {
	$addressJson = $myprocatAddress;
} else if (!empty($invoice['payment_response'])) {
	$paymentResponse = json_decode($invoice['payment_response'], true);
	if (
		is_array($paymentResponse)
		&& isset($paymentResponse['PaymentResponses']['PaymentResponseType']['Customer']['Address'])
		&& is_array($paymentResponse['PaymentResponses']['PaymentResponseType']['Customer']['Address'])
	) {
		$addressDetails = $paymentResponse['PaymentResponses']['PaymentResponseType']['Customer']['Address'];
	}
}

if ($addressJson !== '' && empty($addressDetails)) {
	$decodedAddress = json_decode($addressJson, true);
	if (is_array($decodedAddress)) {
		$addressDetails = $decodedAddress;
	}
}

$serviceDescription = 'Transcription (hours)';
if ($renewSupportNotes !== '') {
	$serviceDescription = $renewSupportNotes;
	// Older renew orders may only have expires_at; append a period line when missing.
	if (strpos($serviceDescription, 'Service Period:') === false && $renewExpiresAt !== '') {
		$periodStart = $renewPreviousExpiresAt !== '' ? $renewPreviousExpiresAt : $invoice['invoice_date'];
		$startTs = strtotime($periodStart);
		$endTs = strtotime($renewExpiresAt);
		if ($startTs !== false && $endTs !== false) {
			$serviceDescription .= "\nService Period: " . date('M j, Y', $startTs) . ' - ' . date('M j, Y', $endTs);
		}
	}
} else if ($licenseTitle !== '') {
	$serviceDescription = $licenseTitle;
} else if ($minutesSource !== '') {
	$serviceDescription = $minutesSource;
}

$invoiceData = array(
	'invoice_number' => $invoice['invoice_number'],
	'invoice_date' => date('F j, Y g:i A', strtotime($invoice['invoice_date'])),
	'rate' => $invoice['rate'],
	'hours' => $invoice['hours'],
	'payment_method' => $invoice['payment_method'],
	'address_details' => $addressDetails,
	'customer_name' => $invoice['customer_name'],
	'discount' => $invoice['discount'],
	'total_amount' => $invoice['total_amount'],
	'service_description' => $serviceDescription,
);

$invoiceEmbedView = true;
ob_start();
include DOCUMENT_ROOT . '/store/invoice-email.php';
$invoiceHtml = ob_get_clean();

if (!empty($_REQUEST['modal'])) {
	header('Content-Type: text/html; charset=utf-8');
	echo $invoiceHtml;
	exit;
}

$set_title = 'Invoice #' . $invoice['invoice_number'] . ' - MyProCAT';
$sidebar_title = 'Invoices';
$page_banner = new content_block(NULL, 'div', array('class' => 'banner'));
$page_banner->push(new content_block('Invoice #' . $invoice['invoice_number'], 'h1'));

$set_body = new content_block(NULL, 'div', array('style' => 'width: 100%;'));

$actions = new content_block(NULL, 'div', array(
	'style' => 'display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin: 20px 0;',
));
$actions->push(new anchor('Back to Invoices', array(
	'class' => 'secondary_button',
	'href' => '/invoices/',
	'style' => 'margin-bottom:0; font-size:14px; padding:10px 16px;',
)));
$set_body->push($actions);

$invoiceWrapper = new content_block(NULL, 'div', array('style' => 'max-width: 680px; margin: 0 auto 40px;'));
$invoiceWrapper->push(new content_block($invoiceHtml, 'div'));
$set_body->push($invoiceWrapper);

$breadcrumb_items = array(
	array('text' => 'Home', 'url' => '/resources.php'),
	array('text' => 'Invoices', 'url' => '/invoices/'),
	array('text' => 'Invoice #' . $invoice['invoice_number'], 'url' => '#'),
);

require_once DOCUMENT_ROOT . '/templateV2/mainframe/mainframe.php';
