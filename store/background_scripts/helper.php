<?php
/**
 * Shared helpers for MyProCat background scripts.
 */

if (!defined('MYPROCAT_BG_HELPER_LOADED')) {
	define('MYPROCAT_BG_HELPER_LOADED', true);

	/**
	 * Active purchases with auto-purchase enabled and a saved vault card.
	 */
	function myprocat_bg_fetch_auto_purchase_candidates($DB)
	{
		$rows = array(
			'id',
			'id_owner',
			'license_id',
			'license_type',
			'license_title',
			'rate',
			'guid',
			'vault_id',
			'address_details',
			'card_expiry_date',
			'last_four_digits',
			'balance_threshold',
			'min_account_balance',
			'email',
			'first_name',
			'mid_name',
			'last_name',
		);

		$DB->sql(
			'SELECT mp.id, mp.id_owner, mp.license_id, mp.license_type, mp.license_title, mp.rate,
				mp.guid, mp.vault_id, mp.address_details, mp.card_expiry_date, mp.last_four_digits,
				mp.balance_threshold, mp.min_account_balance,
				a.email, a.first_name, a.mid_name, a.last_name
			 FROM myprocat_purchases mp
			 LEFT JOIN casepad_accounts a ON mp.id_owner = a.id_user
			 WHERE mp.is_active = 1
			   AND mp.auto_purchase_enabled = 1
			   AND mp.guid IS NOT NULL
			   AND mp.guid != ""
			 ORDER BY mp.id ASC',
			array(),
			$rows
		);

		return $rows;
	}

	/**
	 * Returns the user's current balance in minutes (billing_data::balances), or null when unavailable.
	 */
	function myprocat_bg_user_balance_minutes($DB, $user_id)
	{
		require_once DOCUMENT_ROOT . '/CasePadSE/lib/billing_data.php';

		$billing = new billing_data($DB);
		$results = $billing->balances($user_id, 'owner');

		if ($results === false || empty($results) || !isset($results[0]['balance'])) {
			return null;
		}

		if ($results[0]['balance'] === 'balance') {
			return null;
		}

		return (float)$results[0]['balance'];
	}

	/**
	 * Converts stored auto-purchase settings (hours) to minutes for balance comparison.
	 */
	function myprocat_bg_hours_to_minutes($hours)
	{
		return (float)$hours * 60;
	}

	/**
	 * Hours to purchase when balance drops below the threshold.
	 */
	function myprocat_bg_calculate_auto_purchase_hours($balance_threshold, $min_account_balance)
	{
		$threshold = (float)$balance_threshold;
		$minimum = (float)$min_account_balance;

		if ($minimum < $threshold) {
			return 0;
		}

		return (int)round($minimum - $threshold);
	}

	/**
	 * Returns true when the saved card expiry date is in the past.
	 */
	function myprocat_bg_card_is_expired($card_expiry_date)
	{
		$cardExpiry = trim((string)$card_expiry_date);
		if ($cardExpiry === '' || $cardExpiry === 'card_expiry_date') {
			return false;
		}

		$expTs = strtotime($cardExpiry);
		if ($expTs === false) {
			return false;
		}

		return $expTs < strtotime(date('Y-m-d'));
	}

	/**
	 * Minimum purchasable hours for the license tied to a purchase row.
	 */
	function myprocat_bg_license_minimum_hours($DB, array $purchase)
	{
		$licenseRows = array('minimum_hours');
		$DB->sql(
			'SELECT minimum_hours FROM myprocat_subscription_license_table WHERE id = ? LIMIT 1',
			array('i', (int)$purchase['license_id']),
			$licenseRows
		);

		if (empty($licenseRows) || !isset($licenseRows[0]['minimum_hours']) || $licenseRows[0]['minimum_hours'] === 'minimum_hours') {
			return 1;
		}

		$minHours = (int)$licenseRows[0]['minimum_hours'];
		return $minHours > 0 ? $minHours : 1;
	}

	/**
	 * Customer + address fields for a Sage vault sale.
	 */
	function myprocat_bg_customer_fields_from_purchase(array $purchase)
	{
		$address_details = json_decode(isset($purchase['address_details']) ? $purchase['address_details'] : '', true);
		if (!is_array($address_details)) {
			$address_details = array();
		}

		$email = isset($purchase['email']) ? (string)$purchase['email'] : '';
		if (!empty($address_details['EmailAddress'])) {
			$email = (string)$address_details['EmailAddress'];
		}

		return array(
			'first_name'    => isset($purchase['first_name']) ? (string)$purchase['first_name'] : '',
			'mid_name'      => isset($purchase['mid_name']) ? (string)$purchase['mid_name'] : '',
			'last_name'     => isset($purchase['last_name']) ? (string)$purchase['last_name'] : '',
			'address_line1' => isset($address_details['AddressLine1']) ? (string)$address_details['AddressLine1'] : '',
			'address_line2' => isset($address_details['AddressLine2']) ? (string)$address_details['AddressLine2'] : '',
			'city'          => isset($address_details['City']) ? (string)$address_details['City'] : '',
			'state'         => isset($address_details['State']) ? (string)$address_details['State'] : '',
			'zip_code'      => isset($address_details['ZipCode']) ? (string)$address_details['ZipCode'] : '',
			'country'       => isset($address_details['Country']) ? (string)$address_details['Country'] : '',
			'email'         => $email,
			'telephone'     => isset($address_details['Telephone']) && !is_array($address_details['Telephone'])
				? (string)$address_details['Telephone']
				: '',
		);
	}

	/**
	 * Charge the saved vault card and return decoded payment data, or null on failure.
	 */
	function myprocat_bg_charge_saved_card($DB, array $purchase, $hours, $amount, array $sharedCredentials, array $transactionTypes)
	{
		require_once DOCUMENT_ROOT . '/subscription/helper.php';
		require_once DOCUMENT_ROOT . '/contract/lib/payment_helpers.php';

		$guid = trim((string)$purchase['guid']);
		if ($guid === '' || $guid === 'guid') {
			return null;
		}

		$customer = myprocat_bg_customer_fields_from_purchase($purchase);
		$transaction_id = uniqid('myprocat_auto_', true);
		$order_number = build_license_order_number($purchase['license_title'], $hours, 'Auto', true);
		$xmlRequest = contract_build_vault_sale_xml(array(
			'merchant_id'      => $sharedCredentials['MID'],
			'merchant_key'     => $sharedCredentials['MKEY'],
			'transaction_type' => $transactionTypes['NonUI']['Sale'],
			'transaction_id'   => $transaction_id,
			'order_number'     => $order_number,
			'amount'           => $amount,
			'vault_guid'       => decryptData($guid),
			'vault_operation'  => 'RETRIEVE',
			'first_name'       => $customer['first_name'],
			'mid_name'         => $customer['mid_name'],
			'last_name'        => $customer['last_name'],
			'address_line1'    => $customer['address_line1'],
			'address_line2'    => $customer['address_line2'],
			'city'             => $customer['city'],
			'state'            => $customer['state'],
			'zip_code'         => $customer['zip_code'],
			'country'          => $customer['country'],
			'email'            => $customer['email'],
			'telephone'        => $customer['telephone'],
		));

		$decoded = contract_decode_payment_xml(contract_post_vault_sale_request($xmlRequest));
		
		if ($decoded['paymentResponse'] === null || !contract_payment_is_approved($decoded['paymentResponse'])) {
			return null;
		}

		$txResp = contract_payment_transaction_block($decoded['paymentResponse']);
		$last4 = isset($txResp['Last4']) ? substr($txResp['Last4'], -4) : (string)$purchase['last_four_digits'];

		return array(
			'transaction_id'     => isset($txResp['TransactionID']) ? $txResp['TransactionID'] : $transaction_id,
			'transaction_date'   => isset($txResp['TransactionDate']) ? $txResp['TransactionDate'] : date('Y-m-d H:i:s'),
			'transaction_amount' => isset($txResp['Amount']) ? $txResp['Amount'] : $amount,
			'van_reference'      => isset($txResp['VANReference']) ? $txResp['VANReference'] : '',
			'last4'              => $last4,
			'payment_method'     => myprocat_payment_method_label(isset($txResp['PaymentTypeID']) ? $txResp['PaymentTypeID'] : ''),
			'json_response'      => $decoded['jsonResponse'],
		);
	}
}
?>
