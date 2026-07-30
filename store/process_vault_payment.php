<?php
require_once __DIR__ . '/../config.php';
require_once DOCUMENT_ROOT . '/setup/start.php';
require_once DOCUMENT_ROOT . '/paymentSdk/shared.php';
require_once DOCUMENT_ROOT . '/store/helper.php';
require_once DOCUMENT_ROOT . '/store/lib/payment_helpers.php';
require_once __DIR__ . '/payment_complete.php';

if (is_null($UserAccount) || !is_a($UserAccount, 'useraccount') || !$UserAccount->logged_in) {
	header('Location: /logout.php');
	exit;
}



$checkout = myprocat_validate_checkout_post($DB, $UserAccount);
$user_id = $checkout['user_id'];

$purchaseRows = array('id', 'guid', 'vault_id', 'address_details', 'card_expiry_date', 'last_four_digits');
$DB->sql(
	'SELECT id, guid, vault_id, address_details, card_expiry_date, last_four_digits
	 FROM myprocat_purchases
	 WHERE id_owner = ? AND is_active = 1
	 ORDER BY id DESC
	 LIMIT 1',
	array('s', $user_id),
	$purchaseRows
);

if (empty($purchaseRows) || !isset($purchaseRows[0]['guid']) || $purchaseRows[0]['guid'] === 'guid' || trim((string)$purchaseRows[0]['guid']) === '') {
	header('Location: /store/payment_failed.php');
	exit;
}

$purchase = $purchaseRows[0];

$cardExpiry = trim((string)$purchase['card_expiry_date']);
if ($cardExpiry !== '' && $cardExpiry !== 'card_expiry_date') {
	$expTs = strtotime($cardExpiry);
	if ($expTs !== false && $expTs < strtotime(date('Y-m-d'))) {
		header('Location: /store/payment_failed.php');
		exit;
	}
}

$decryptedVaultToken = decryptData($purchase['guid']);
$address_details_json = (string)$purchase['address_details'];
$address_details = json_decode($address_details_json, true);
if (!is_array($address_details)) {
	$address_details = array();
}

$firstName = (string)$UserAccount->user_details['first_name'];
$midName = isset($UserAccount->user_details['mid_name']) ? (string)$UserAccount->user_details['mid_name'] : '';
$lastName = (string)$UserAccount->user_details['last_name'];
$email = (string)$UserAccount->user_details['email'];

$address_line1 = isset($address_details['AddressLine1']) ? (string)$address_details['AddressLine1'] : '';
$address_line2 = isset($address_details['AddressLine2']) ? (string)$address_details['AddressLine2'] : '';
$city = isset($address_details['City']) ? (string)$address_details['City'] : '';
$state = isset($address_details['State']) ? (string)$address_details['State'] : '';
$zip_code = isset($address_details['ZipCode']) ? (string)$address_details['ZipCode'] : '';
$country = isset($address_details['Country']) ? (string)$address_details['Country'] : '';
$telephone = isset($address_details['Telephone']) && !is_array($address_details['Telephone']) ? (string)$address_details['Telephone'] : '';
if (!empty($address_details['EmailAddress'])) {
	$email = (string)$address_details['EmailAddress'];
}

$order_number = build_license_order_number($checkout['license_title'], $checkout['hours']);
$transaction_id = uniqid('myprocat_vault_', true);

$xmlRequest = contract_build_vault_sale_xml(array(
	'merchant_id'      => $sharedCredentials['MID'],
	'merchant_key'     => $sharedCredentials['MKEY'],
	'transaction_type' => $transactionTypes['NonUI']['Sale'],
	'transaction_id'   => $transaction_id,
	'order_number'     => $order_number,
	'amount'           => $checkout['amount'],
	'vault_guid'       => $decryptedVaultToken,
	'vault_operation'  => 'RETRIEVE',
	'first_name'       => $firstName,
	'mid_name'         => $midName,
	'last_name'        => $lastName,
	'address_line1'    => $address_line1,
	'address_line2'    => $address_line2,
	'city'             => $city,
	'state'            => $state,
	'zip_code'         => $zip_code,
	'country'          => $country,
	'email'            => $email,
	'telephone'        => $telephone,
));

$decoded = contract_decode_payment_xml(contract_post_vault_sale_request($xmlRequest));
if ($decoded['paymentResponse'] === null || !contract_payment_is_approved($decoded['paymentResponse'])) {
	header('Location: /store/payment_failed.php');
	exit;
}

try {
	
	$paymentResponse = $decoded['paymentResponse'];
	$jsonResponse = $decoded['jsonResponse'];

	$txResp = contract_payment_transaction_block($paymentResponse);

	$transaction_date = isset($txResp['TransactionDate']) ? $txResp['TransactionDate'] : date('Y-m-d H:i:s');
	$transaction_amount = isset($txResp['Amount']) ? $txResp['Amount'] : $checkout['amount'];
	$vanReference = isset($txResp['VANReference']) ? $txResp['VANReference'] : '';
	$last4 = isset($txResp['Last4']) ? substr($txResp['Last4'], -4) : (string)$purchase['last_four_digits'];
	$paymentMethod = myprocat_payment_method_label(isset($txResp['PaymentTypeID']) ? $txResp['PaymentTypeID'] : '');

	$card_expiry_sql = ($cardExpiry !== '' && $cardExpiry !== 'card_expiry_date')
		? date('Y-m-d', strtotime($cardExpiry))
		: date('Y-m-d');

	$result = myprocat_finalize_successful_payment($DB, array(
		'user_id'               => $user_id,
		'license_id'            => $checkout['license_id'],
		'license_type'          => $checkout['license_type'],
		'license_title'         => $checkout['license_title'],
		'hours'                 => $checkout['hours'],
		'rate'                  => $checkout['rate'],
		'transaction_id'        => $transaction_id,
		'transaction_amount'    => $transaction_amount,
		'transaction_date'      => $transaction_date,
		'van_reference'         => $vanReference,
		'last4'                 => $last4,
		'payment_method'        => $paymentMethod,
		'json_response'         => $jsonResponse,
		'guid'                  => $purchase['guid'],
		'vault_id'              => $purchase['vault_id'],
		'address_details'       => $address_details_json,
		'card_expiry_sql'       => $card_expiry_sql,
		'auto_purchase_enabled' => $checkout['auto_purchase_enabled'],
		'balance_threshold'     => $checkout['balance_threshold'],
		'min_account_balance'   => $checkout['min_account_balance'],
		'delete_session_key'    => null,
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
