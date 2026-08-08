<?php
require_once __DIR__ . '/../config.php';
require_once DOCUMENT_ROOT . '/paymentSdk/shared.php';
require_once DOCUMENT_ROOT . '/setup/start.php';
require_once DOCUMENT_ROOT . '/store/helper.php';
require_once __DIR__ . '/payment_complete.php';

function renew_support_redirect_failed()
{
	header('Location: /renew_support/payment_failed.php');
	exit;
}

try {
	$response = openEnvelope($_REQUEST['response']);
	$xmlResponse = simplexml_load_string($response);
} catch (Exception $e) {
	error_log($e);
	$xmlResponse = null;
}

if (!$xmlResponse) {
	renew_support_redirect_failed();
}

$jsonResponse = json_encode($xmlResponse);
$paymentResponse = json_decode($jsonResponse, true);

if (!isset($paymentResponse['PaymentResponses'])) {
	renew_support_redirect_failed();
}

$responseIndicator = isset($paymentResponse['PaymentResponses']['PaymentResponseType']['Response']['ResponseIndicator'])
	? $paymentResponse['PaymentResponses']['PaymentResponseType']['Response']['ResponseIndicator']
	: null;

if ($responseIndicator !== 'A') {
	renew_support_redirect_failed();
}

$transaction_id = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['TransactionID'];
$transactionData = array('transaction_key', 'data');
$DB->sql(
	'SELECT transaction_key, data FROM casepad_subscription_session WHERE transaction_key = ?',
	array('s', $transaction_id),
	$transactionData
);

if (!isset($transactionData[0]['data']) || $transactionData[0]['data'] === 'data') {
	renew_support_redirect_failed();
}

try {
	$transaction_data = json_decode($transactionData[0]['data'], true);
	if (!is_array($transaction_data) || (isset($transaction_data['payment_source']) ? $transaction_data['payment_source'] : '') !== 'renew_support') {
		renew_support_redirect_failed();
	}

	$tx = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse'];
	$transaction_date = isset($tx['TransactionDate']) ? $tx['TransactionDate'] : date('Y-m-d H:i:s');
	$transaction_amount = isset($tx['Amount']) ? $tx['Amount'] : $transaction_data['total_amount'];
	$paymentMethodId = isset($tx['PaymentTypeID']) ? $tx['PaymentTypeID'] : '';
	$vanReference = isset($tx['VANReference']) ? $tx['VANReference'] : '';

	$vaultBlock = isset($paymentResponse['PaymentResponses']['PaymentResponseType']['VaultResponse'])
		? $paymentResponse['PaymentResponses']['PaymentResponseType']['VaultResponse']
		: array();
	$last4 = isset($vaultBlock['Last4']) ? $vaultBlock['Last4'] : '';
	$last4 = substr((string)$last4, -4);

	$address_details = null;
	if (isset($paymentResponse['PaymentResponses']['PaymentResponseType']['Customer']['Address'])) {
		$address_details = json_encode($paymentResponse['PaymentResponses']['PaymentResponseType']['Customer']['Address']);
	}

	$result = renew_support_finalize_successful_payment($DB, array(
		'user_id' => $transaction_data['user_id'],
		'sku_id' => (int)$transaction_data['sku_id'],
		'sku_key' => $transaction_data['sku_key'],
		'tier' => $transaction_data['tier'],
		'display_name' => $transaction_data['display_name'],
		'base_plan_code' => $transaction_data['base_plan_code'],
		'addon_plan_code' => isset($transaction_data['addon_plan_code']) ? $transaction_data['addon_plan_code'] : null,
		'base_amount' => isset($transaction_data['base_amount']) ? (float)$transaction_data['base_amount'] : (float)$transaction_amount,
		'addon_amount' => isset($transaction_data['addon_amount']) ? (float)$transaction_data['addon_amount'] : 0.0,
		'total_amount' => (float)$transaction_amount,
		'max_renewals' => isset($transaction_data['max_renewals']) ? $transaction_data['max_renewals'] : null,
		'transaction_id' => $transaction_id,
		'transaction_date' => $transaction_date,
		'van_reference' => $vanReference,
		'last4' => $last4,
		'payment_method' => renew_support_payment_method_label($paymentMethodId),
		'json_response' => $jsonResponse,
		'address_details' => $address_details,
		'delete_session_key' => $transaction_id,
	));

	$thankYouUrl = '/renew_support/thank_you.php'
		. '?order_id=' . urlencode($result['invoice_number'])
		. '&plan=' . urlencode($result['display_name'])
		. '&tier=' . urlencode($result['tier'])
		. '&amount=' . urlencode(number_format((float)$result['total_amount'], 2, '.', ''));

	header('Location: ' . $thankYouUrl);
	exit;
} catch (Exception $e) {
	error_log($e);
	renew_support_redirect_failed();
}
?>
