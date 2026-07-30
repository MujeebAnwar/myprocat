<?php
require_once __DIR__ . '/../config.php';
require_once DOCUMENT_ROOT . '/paymentSdk/shared.php';
require_once DOCUMENT_ROOT . '/setup/start.php';
require_once DOCUMENT_ROOT . '/store/helper.php';
require_once __DIR__ . '/payment_complete.php';

try {
	$response    = openEnvelope($_REQUEST['response']);
	$xmlResponse = simplexml_load_string($response);
} catch (Exception $e) {
	error_log($e);
	$xmlResponse = null;
}


if (!$xmlResponse) {
	header('Location: /store/payment_failed.php');
	exit;
}

$jsonResponse    = json_encode($xmlResponse);
$paymentResponse = json_decode($jsonResponse, true);

if (!isset($paymentResponse['PaymentResponses'])) {
	header('Location: /store/payment_failed.php');
	exit;
}

$responseIndicator = $paymentResponse['PaymentResponses']['PaymentResponseType']['Response']['ResponseIndicator'] ?? null;
if ($responseIndicator !== 'A') {
	header('Location: /store/payment_failed.php');
	exit;
}

$transaction_id = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['TransactionID'];
$transactionData = array('transaction_key', 'data');
$DB->sql(
	'SELECT transaction_key, data FROM casepad_subscription_session WHERE transaction_key = ?',
	array('s', $transaction_id),
	$transactionData
);

if (!isset($transactionData[0]['data']) || $transactionData[0]['data'] === 'data') {
	header('Location: /store/payment_failed.php');
	exit;
}

try {
	$transaction_data = json_decode($transactionData[0]['data'], true);
	if (!is_array($transaction_data) || ($transaction_data['payment_source'] ?? '') !== 'myprocat') {
		header('Location: /store/payment_failed.php');
		exit;
	}

	$transaction_date = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['TransactionDate'];
	$transaction_amount = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['Amount'];
	$paymentMethodId = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['PaymentTypeID'];
	$guid = encryptData($paymentResponse['PaymentResponses']['PaymentResponseType']['VaultResponse']['GUID']);
	$expiration_date = $paymentResponse['PaymentResponses']['PaymentResponseType']['VaultResponse']['ExpirationDate'];
	$last4 = $paymentResponse['PaymentResponses']['PaymentResponseType']['VaultResponse']['Last4'];
	$licenseTitle = $transaction_data['license_title'];
	$licenseType = $transaction_data['license_type'];
	$hours = (int)$transaction_data['hours'];
	$user_id = $transaction_data['user_id'];
	$rate = (float)$transaction_data['rate'];
	$vault_id = encryptData($transaction_data['vault_id']);
	$vanReference = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['VANReference'];

	$month = substr($expiration_date, 0, 2);
	$year = substr($expiration_date, 2, 2);
	$currentYear = substr(date('Y'), 0, 2);
	$year = $currentYear . $year;
	$card_expiry_date = '01-' . $month . '-' . $year;
	$last4 = str_repeat('', strlen($last4) - 4) . substr($last4, -4);
	$card_expiry_sql = date('Y-m-d', strtotime($card_expiry_date));
	$address_details = json_encode($paymentResponse['PaymentResponses']['PaymentResponseType']['Customer']['Address']);

	$result = myprocat_finalize_successful_payment($DB, array(
		'user_id'               => $user_id,
		'license_id'            => (int)$transaction_data['license_id'],
		'license_type'          => $licenseType,
		'license_title'         => $licenseTitle,
		'hours'                 => $hours,
		'rate'                  => $rate,
		'transaction_id'        => $transaction_id,
		'transaction_amount'    => $transaction_amount,
		'transaction_date'      => $transaction_date,
		'van_reference'         => $vanReference,
		'last4'                 => $last4,
		'payment_method'        => myprocat_payment_method_label($paymentMethodId),
		'json_response'         => $jsonResponse,
		'guid'                  => $guid,
		'vault_id'              => $vault_id,
		'address_details'       => $address_details,
		'card_expiry_sql'       => $card_expiry_sql,
		'auto_purchase_enabled' => !empty($transaction_data['auto_purchase_enabled']) ? 1 : 0,
		'balance_threshold'     => isset($transaction_data['balance_threshold']) ? (float)$transaction_data['balance_threshold'] : null,
		'min_account_balance'   => isset($transaction_data['min_account_balance']) ? (float)$transaction_data['min_account_balance'] : null,
		'delete_session_key'    => $transaction_id,
	));

	$thankYouUrl = '/store/thank_you.php?order_id=' . urlencode($result['invoice_number'])
		. '&license=' . urlencode($result['license_title'])
		. '&amount=' . urlencode($result['total_amount']);
	header('Location: ' . $thankYouUrl);
	exit;
} catch (Exception $e) {
	error_log($e);
	header('Location: /store/payment_failed.php');
	exit;
}
?>
