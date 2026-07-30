<?php
/**
 * Shared payment-recording helpers used by the contract payment flows:
 *   - contract/pay/payment_response.php                 (new contract, redirect)
 *   - contract/pay/child_payment_response.php           (installment, redirect)
 *   - contract/pay/process_vault_contract_payment.php   (new contract, vault sale)
 *   - contract/upcomming/payment_response.php           (renewal, redirect)
 *   - contract/upcomming/process_vault_contract_payment.php (renewal, vault sale)
 *
 * Requires: lib/database.php (databaseI), subscription/helper.php (encryptData/decryptData)
 */

if (!defined('CONTRACT_PAYMENT_HELPER_LOADED')) {
	define('CONTRACT_PAYMENT_HELPER_LOADED', true);

	/**
	 * Sage / payment processor PaymentTypeID -> display label.
	 */
	function contract_payment_method_label($paymentTypeId)
	{
		switch ((string)$paymentTypeId) {
			case '3': return 'American Express';
			case '4': return 'Visa';
			case '5': return 'MasterCard';
			case '6': return 'Discover';
			case '7': return 'JCB';
			case 'D': return 'Debit Card';
			case 'C': return 'ACH';
			case 'O': return 'Other';
			default:  return 'Other';
		}
	}

	/**
	 * Extract vault details from a decoded payment response.
	 * Always returns an array with: guid, vault_id, last4, card_expiry_date.
	 * GUID/vault_id are encrypted; card_expiry_date defaults to today when absent.
	 */
	function contract_parse_vault_response(array $paymentResponse)
	{
		$out = array(
			'guid'             => '',
			'vault_id'         => '',
			'last4'            => '',
			'card_expiry_date' => date('Y-m-d'),
		);

		if (!isset($paymentResponse['PaymentResponses']['PaymentResponseType']['VaultResponse'])) {
			return $out;
		}

		$vault = $paymentResponse['PaymentResponses']['PaymentResponseType']['VaultResponse'];

		if (!empty($vault['GUID'])) {
			$encrypted = encryptData($vault['GUID']);
			$out['guid']     = $encrypted;
			$out['vault_id'] = $encrypted;
		}
		if (!empty($vault['Last4'])) {
			$out['last4'] = substr($vault['Last4'], -4);
		}
		if (!empty($vault['ExpirationDate'])) {
			$exp     = $vault['ExpirationDate'];
			$month   = substr($exp, 0, 2);
			$year2   = substr($exp, 2, 2);
			$century = substr(date('Y'), 0, 2);
			$out['card_expiry_date'] = $century . $year2 . '-' . $month . '-01';
		}

		return $out;
	}

	/**
	 * Returns the next invoice number, starting at 10800 when the table is empty.
	 */
	function contract_next_invoice_number($DB)
	{
		$rows = array('invoice_number');
		$DB->sql(
			'SELECT MAX(invoice_number) as invoice_number FROM casepad_payment_invoices',
			array(),
			$rows
		);
		if (empty($rows) || is_null($rows[0]['invoice_number'])) {
			return 10800;
		}
		return intval($rows[0]['invoice_number']) + 1;
	}

	/**
	 * Looks up the enterprise subscription_plans row.
	 * Throws when the plan record is missing.
	 *
	 * @return array{id:string|int, plan_id:string, name:string}
	 */
	function contract_fetch_enterprise_plan($DB)
	{
		$plan = array('id', 'plan_id', 'name');
		$DB->sql(
			"SELECT id, plan_id, name FROM subscription_plans WHERE plan_id = 'enterprise' LIMIT 1",
			array(),
			$plan
		);
		if (empty($plan)) {
			throw new Exception('Enterprise plan not found');
		}
		return $plan[0];
	}

	/**
	 * Marks a client_contract as paid and stamps transaction info.
	 */
	function contract_mark_contract_paid($DB, $contract_id, $transaction_id, $van_reference)
	{
		$cid = intval($contract_id);
		$tid = (string)$transaction_id;
		$van = (string)$van_reference;

		$DB->sql(
			"UPDATE client_contract SET payment_status = 'paid', transaction_id = ?, van_reference = ?, paid_at = NOW() WHERE id = ?",
			array('ssi', $tid, $van, $cid)
		);
	}

	/**
	 * Records auto-renew preference for a contract when the flag is on.
	 * No-op when $auto_renew !== 1. Returns the new row id, or null when skipped.
	 */
	function contract_record_auto_renew($DB, $contract_id, $user_id, $auto_renew)
	{
		$ar = intval($auto_renew);
		if ($ar !== 1) {
			return null;
		}
		$cid = intval($contract_id);
		$uid = (string)$user_id;

		$DB->sql(
			'INSERT INTO client_contract_auto_renew (`contract_id`,`id_user`,`auto_renew`) VALUES (?,?,?)',
			array('isi', $cid, $uid, $ar)
		);
		return intval($DB->iid());
	}

	/**
	 * True when the contract row is a root agreement (not a renewal child).
	 */
	function contract_is_root_contract(array $contract)
	{
		return !isset($contract['parent_id'])
			|| is_null($contract['parent_id'])
			|| intval($contract['parent_id']) === 0;
	}

	/**
	 * Deactivates all active casepad_subscribed_plan rows for a user.
	 */
	function contract_deactivate_user_subscribed_plans($DB, $user_id)
	{
		$DB->sql(
			'UPDATE casepad_subscribed_plan SET is_active = 0, updated_at = ? WHERE id_owner = ? AND is_active = 1',
			array('ss', date('Y-m-d H:i:s'), $user_id)
		);
	}

	/**
	 * Deactivates the user's subscription when a paid contract has reached its term limit.
	 */
	function contract_deactivate_subscription_if_term_completed($DB, $contract_id, $user_id)
	{
		if (!function_exists('contract_bg_has_reached_term_limit')) {
			require_once DOCUMENT_ROOT . '/contract/background_scripts/helper.php';
		}

		$contract_id = intval($contract_id);
		if ($contract_id <= 0) {
			return;
		}

		$rows = array('billing_period');
		$DB->sql(
			'SELECT billing_period FROM client_contract WHERE id = ? LIMIT 1',
			array('i', $contract_id),
			$rows
		);
		if (empty($rows) || contract_is_prepaid_contract($rows[0])) {
			return;
		}

		if (contract_bg_has_reached_term_limit($DB, $contract_id)) {
			contract_deactivate_user_subscribed_plans($DB, $user_id);
		}
	}

	/**
	 * True when the contract is a one-time prepaid purchase (billing_period = 0).
	 */
	function contract_is_prepaid_contract(array $contract)
	{
		return intval($contract['billing_period']) === 0;
	}

	/**
	 * Root recurring contract payments start a fresh subscription row (old plan deactivated).
	 * Renewal payments reuse the active plan and refresh vault details when provided.
	 * Prepaid contracts are one-time purchases and do not create or modify subscriptions.
	 *
	 * @param array $contract parent_id, billing_period, hourly_rate, total_hours
	 * @param array $vault    guid, vault_id, last4, card_expiry_date (optional for vault reuse)
	 * @param string $address_details JSON address blob
	 * @return int subscribed_plan_id, or 0 for prepaid (no subscription)
	 */
	function contract_resolve_subscribed_plan_for_payment($DB, $user_id, array $contract, array $vault, $address_details)
	{
		if (contract_is_prepaid_contract($contract)) {
			return 0;
		}

		$plan           = contract_fetch_enterprise_plan($DB);
		$billing_period = intval($contract['billing_period']);
		$billing        = contract_billing_period_label($billing_period);
		$prepaid        = 0;
		$existing_id    = contract_active_subscribed_plan_id($DB, $user_id);
		$is_root        = contract_is_root_contract($contract);

		if ($is_root && $existing_id > 0) {
			if (empty($vault['guid']) || empty($vault['vault_id'])) {
				$existing_rows = array('guid', 'vault_id', 'last_four_digits', 'card_expiry_date', 'address_details');
				$DB->sql(
					'SELECT guid, vault_id, last_four_digits, card_expiry_date, address_details FROM casepad_subscribed_plan WHERE id = ? LIMIT 1',
					array('i', $existing_id),
					$existing_rows
				);
				if (!empty($existing_rows) && isset($existing_rows[0]['guid']) && $existing_rows[0]['guid'] !== 'guid') {
					$existing = $existing_rows[0];
					if (empty($vault['guid'])) {
						$vault['guid'] = $existing['guid'];
					}
					if (empty($vault['vault_id'])) {
						$vault['vault_id'] = $existing['vault_id'];
					}
					if (empty($vault['last4'])) {
						$vault['last4'] = $existing['last_four_digits'];
					}
					if (empty($vault['card_expiry_date'])) {
						$vault['card_expiry_date'] = $existing['card_expiry_date'];
					}
					if ($address_details === '' || $address_details === 'null') {
						$address_details = $existing['address_details'];
					}
				}
			}
			contract_deactivate_user_subscribed_plans($DB, $user_id);
			$existing_id = 0;
		}

		if ($existing_id <= 0) {
			return contract_create_subscribed_plan($DB, array(
				'user_id'           => $user_id,
				'plan_id'           => $plan['id'],
				'rate'              => $contract['hourly_rate'],
				'hours'             => $contract['total_hours'],
				'last4'             => isset($vault['last4']) ? $vault['last4'] : '',
				'card_expiry_date'  => !empty($vault['card_expiry_date']) ? $vault['card_expiry_date'] : date('Y-m-d'),
				'vault_id'          => isset($vault['vault_id']) ? $vault['vault_id'] : '',
				'guid'              => isset($vault['guid']) ? $vault['guid'] : '',
				'commitment'        => $billing,
				'prepaid'           => $prepaid,
				'address_details'   => $address_details,
				'next_payment_date' => null,
			));
		}

		if (!empty($vault['guid'])) {
			contract_update_subscribed_plan_vault(
				$DB,
				$existing_id,
				$vault['guid'],
				!empty($vault['card_expiry_date']) ? $vault['card_expiry_date'] : date('Y-m-d'),
				isset($vault['vault_id']) ? $vault['vault_id'] : ''
			);
		}

		return $existing_id;
	}

	/**
	 * Returns the most recent active casepad_subscribed_plan id for the user, or 0 if none.
	 */
	function contract_active_subscribed_plan_id($DB, $user_id)
	{
		$rows = array('id');
		$DB->sql(
			'SELECT id FROM casepad_subscribed_plan WHERE id_owner = ? AND is_active = 1 ORDER BY id DESC LIMIT 1',
			array('s', $user_id),
			$rows
		);
		if (empty($rows) || !isset($rows[0]['id'])) {
			return 0;
		}
		return intval($rows[0]['id']);
	}

	/**
	 * Inserts a new casepad_subscribed_plan row and returns its id.
	 *
	 * Required keys in $args:
	 *   user_id, plan_id, rate, hours, last4, card_expiry_date,
	 *   vault_id, guid, commitment, prepaid, address_details
	 * Optional:
	 *   next_payment_date (default null)
	 */
	function contract_create_subscribed_plan($DB, array $args)
	{
		$now              = date('Y-m-d H:i:s');
		$nextPaymentDate  = isset($args['next_payment_date']) ? $args['next_payment_date'] : null;

		$DB->sql(
			'INSERT INTO casepad_subscribed_plan (`id_owner`,`plan_id`,`rate`,`hours`,`last_four_digits`,`card_expiry_date`,`vault_id`,`guid`,`commitment`,`prepaid`,`address_details`,`created_at`,`updated_at`,`subscribed_at`,`next_payment_date`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
			array(
				'sssssssssisssss',
				$args['user_id'],
				$args['plan_id'],
				$args['rate'],
				$args['hours'],
				$args['last4'],
				$args['card_expiry_date'],
				$args['vault_id'],
				$args['guid'],
				$args['commitment'],
				intval($args['prepaid']),
				$args['address_details'],
				$now,
				$now,
				$now,
				$nextPaymentDate
			)
		);

		return intval($DB->iid());
	}

	/**
	 * Updates the vault token / expiry / vault_id on an existing subscribed plan.
	 * Used by renewal flows that capture a fresh vault response.
	 */
	function contract_update_subscribed_plan_vault($DB, $subscribed_plan_id, $guid, $card_expiry_date, $vault_id)
	{
		$sid = intval($subscribed_plan_id);
		if ($sid <= 0) {
			return;
		}

		$DB->sql(
			'UPDATE casepad_subscribed_plan SET guid = ?, card_expiry_date = ?, vault_id = ? WHERE id = ?',
			array('sssi', $guid, $card_expiry_date, $vault_id, $sid)
		);
	}

	/**
	 * Inserts the payment invoice plus the matching minutes_credits and storage_credits rows
	 * in one place. Returns the new invoice id.
	 *
	 * Required keys in $args:
	 *   user_id, subscribed_plan_id, invoice_number, transaction_id, van_reference,
	 *   last4, invoice_date, payment_method, total_amount, rate, hours,
	 *   payment_response (json string), plan_name, storage_fee
	 * Optional:
	 *   discount (default 0)
	 */
	function contract_record_invoice_with_credits($DB, array $args)
	{
		$discount = isset($args['discount']) ? intval($args['discount']) : 0;
		$storage  = intval($args['storage_fee']);
		$minutes  = intval(round(floatval($args['hours']) * 60));
		$subscribed_plan_id = isset($args['subscribed_plan_id']) ? (string)$args['subscribed_plan_id'] : '';
		if (intval($subscribed_plan_id) <= 0) {
			$subscribed_plan_id = '';
		}

		$DB->sql(
			'INSERT INTO casepad_payment_invoices (`id_owner`,`subscribed_plan_id`,`invoice_number`,`transaction_id`,`van_reference`,`last_four_digits`,`invoice_date`,`payment_method`,`discount`,`total_amount`,`rate`,`hours`,`payment_response`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
			array(
				'sssssssssssis',
				$args['user_id'],
				$subscribed_plan_id,
				$args['invoice_number'],
				$args['transaction_id'],
				$args['van_reference'],
				$args['last4'],
				$args['invoice_date'],
				$args['payment_method'],
				$discount,
				$args['total_amount'],
				$args['rate'],
				$args['hours'],
				$args['payment_response']
			)
		);

		$invoice_id = intval($DB->iid());

		$DB->sql(
			'INSERT INTO casepad_minutes_credits (`id_owner`,`minutes`,`time_stamp`,`source`,`invoice_id`) VALUES (?,?,?,?,?)',
			array('sissi', $args['user_id'], $minutes, $args['invoice_date'], $args['plan_name'], $invoice_id)
		);

		$DB->sql(
			'INSERT INTO casepad_storage_credits (`id_owner`,`storage`,`time_stamp`,`source`,`invoice_id`) VALUES (?,?,?,?,?)',
			array('sissi', $args['user_id'], $storage, $args['invoice_date'], $args['plan_name'], $invoice_id)
		);

		return $invoice_id;
	}

	/**
	 * Shared post-payment handler for contract/pay root flows (new-card callback or vault charge).
	 * Prepaid contracts are recorded as one-time purchases without creating a subscription.
	 */
	function contract_finalize_root_contract_payment($DB, array $payload)
	{
		$contract_id = intval($payload['contract_id']);
		$user_id     = $payload['user_id'];
		$contract    = $payload['contract'];
		$is_prepaid  = contract_is_prepaid_contract($contract);
		$auto_renew  = isset($payload['auto_renew']) ? intval($payload['auto_renew']) : 0;

		contract_mark_contract_paid(
			$DB,
			$contract_id,
			$payload['transaction_id'],
			$payload['van_reference']
		);

		if (!$is_prepaid) {
			contract_record_auto_renew($DB, $contract_id, $user_id, $auto_renew);
		}

		$plan           = contract_fetch_enterprise_plan($DB);
		$plan_name      = $plan['name'];
		$invoice_number = isset($payload['invoice_number'])
			? $payload['invoice_number']
			: contract_next_invoice_number($DB);

		$subscribed_plan_id = 0;
		if (!$is_prepaid) {
			$vault           = isset($payload['vault']) ? $payload['vault'] : array();
			$address_details = isset($payload['address_details']) ? $payload['address_details'] : '';
			$subscribed_plan_id = contract_resolve_subscribed_plan_for_payment(
				$DB,
				$user_id,
				$contract,
				$vault,
				$address_details
			);
		}

		contract_record_invoice_with_credits($DB, array(
			'user_id'            => $user_id,
			'subscribed_plan_id' => $subscribed_plan_id,
			'invoice_number'     => $invoice_number,
			'transaction_id'     => $payload['transaction_id'],
			'van_reference'      => $payload['van_reference'],
			'last4'              => $payload['last4'],
			'invoice_date'       => $payload['invoice_date'],
			'payment_method'     => $payload['payment_method'],
			'total_amount'       => $payload['total_amount'],
			'rate'               => $payload['rate'],
			'hours'              => $payload['hours'],
			'payment_response'   => $payload['payment_response'],
			'plan_name'          => $plan_name,
			'storage_fee'        => $payload['storage_fee'],
		));

		if (!$is_prepaid) {
			contract_deactivate_subscription_if_term_completed($DB, $contract_id, $user_id);
		}

		if (!empty($payload['delete_session_key'])) {
			contract_delete_subscription_session($DB, $payload['delete_session_key']);
		}
	}

	/**
	 * Shared post-payment handler for contract/pay child flows (recurring installment).
	 * Prepaid child invoices are one-time purchases and do not require a subscription.
	 */
	function contract_finalize_child_contract_payment($DB, array $payload)
	{
		$contract_id = intval($payload['contract_id']);
		$user_id     = $payload['user_id'];
		$contract    = $payload['contract'];
		$is_prepaid  = contract_is_prepaid_contract($contract);
		$auto_renew  = isset($payload['auto_renew']) ? intval($payload['auto_renew']) : 0;

		contract_mark_contract_paid(
			$DB,
			$contract_id,
			$payload['transaction_id'],
			$payload['van_reference']
		);

		if (!$is_prepaid) {
			contract_record_auto_renew($DB, $contract_id, $user_id, $auto_renew);
		}

		$plan           = contract_fetch_enterprise_plan($DB);
		$plan_name      = $plan['name'];
		$invoice_number = isset($payload['invoice_number'])
			? $payload['invoice_number']
			: contract_next_invoice_number($DB);

		$subscribed_plan_id = 0;
		if (!$is_prepaid) {
			$subscribed_plan_id = contract_active_subscribed_plan_id($DB, $user_id);
			if ($subscribed_plan_id <= 0) {
				throw new Exception('No active subscription found for user ' . $user_id);
			}
		}

		contract_record_invoice_with_credits($DB, array(
			'user_id'            => $user_id,
			'subscribed_plan_id' => $subscribed_plan_id,
			'invoice_number'     => $invoice_number,
			'transaction_id'     => $payload['transaction_id'],
			'van_reference'      => $payload['van_reference'],
			'last4'              => $payload['last4'],
			'invoice_date'       => $payload['invoice_date'],
			'payment_method'     => $payload['payment_method'],
			'total_amount'       => $payload['total_amount'],
			'rate'               => $payload['rate'],
			'hours'              => $payload['hours'],
			'payment_response'   => $payload['payment_response'],
			'plan_name'          => $plan_name,
			'storage_fee'        => $payload['storage_fee'],
		));

		if (!$is_prepaid) {
			$vault = isset($payload['vault']) ? $payload['vault'] : array();
			if (!empty($vault['guid'])) {
				contract_update_subscribed_plan_vault(
					$DB,
					$subscribed_plan_id,
					$vault['guid'],
					!empty($vault['card_expiry_date']) ? $vault['card_expiry_date'] : date('Y-m-d'),
					isset($vault['vault_id']) ? $vault['vault_id'] : ''
				);
			}
		}

		if (!$is_prepaid) {
			contract_deactivate_subscription_if_term_completed($DB, $contract_id, $user_id);
		}

		if (!empty($payload['delete_session_key'])) {
			contract_delete_subscription_session($DB, $payload['delete_session_key']);
		}
	}

	/**
	 * Removes the pending Sage transaction session row.
	 */
	function contract_delete_subscription_session($DB, $transaction_key)
	{
		$key = (string)$transaction_key;
		$DB->sql(
			'DELETE FROM casepad_subscription_session WHERE transaction_key = ?',
			array('s', $key)
		);
	}

	/**
	 * Returns true when the response indicator marks the transaction approved.
	 */
	function contract_payment_is_approved(array $paymentResponse)
	{
		$payment_approved = isset($paymentResponse['PaymentResponses']['PaymentResponseType']['Response']['ResponseIndicator'])
			&& $paymentResponse['PaymentResponses']['PaymentResponseType']['Response']['ResponseIndicator'] === 'A';
		if(!$payment_approved)
		{
			error_log(json_encode($paymentResponse));
		}
		return $payment_approved;
	}

	/**
	 * Pulls the TransactionResponse sub-array (or empty when absent).
	 */
	function contract_payment_transaction_block(array $paymentResponse)
	{
		if (!isset($paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse'])) {
			return array();
		}
		return $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse'];
	}

	/**
	 * Maps a billing_period int to its label. Defaults to 'monthly'.
	 */
	function contract_billing_period_label($billing_period)
	{
		$map = array(1 => 'monthly', 3 => 'quarterly', 12 => 'annual');
		$bp  = intval($billing_period);
		return isset($map[$bp]) ? $map[$bp] : 'monthly';
	}

	/**
	 * Decode the JSON address_details column on casepad_subscribed_plan into the
	 * shape used by the Sage XML builder. Falls back to $fallback_email.
	 */
	function contract_address_fields_from_subscription(array $subscription, $fallback_email = '')
	{
		$raw  = isset($subscription['address_details']) ? $subscription['address_details'] : '';
		$addr = json_decode($raw, true);
		if (!is_array($addr)) {
			$addr = array();
		}

		return array(
			'raw'           => $addr,
			'address_line1' => isset($addr['AddressLine1']) ? $addr['AddressLine1'] : '',
			'address_line2' => isset($addr['AddressLine2']) ? $addr['AddressLine2'] : '',
			'city'          => isset($addr['City'])         ? $addr['City']         : '',
			'state'         => isset($addr['State'])        ? $addr['State']        : '',
			'zip_code'      => isset($addr['ZipCode'])      ? $addr['ZipCode']      : '',
			'country'       => isset($addr['Country'])      ? $addr['Country']      : '',
			'email'         => !empty($addr['EmailAddress']) ? $addr['EmailAddress'] : $fallback_email,
			'telephone'     => !empty($addr['Telephone'])   ? $addr['Telephone']    : '',
		);
	}

	/**
	 * Build the Sage VEPS sale XML request (RETRIEVE vault flow) for a contract payment.
	 *
	 * Required keys in $args:
	 *   merchant_id, merchant_key, transaction_type, transaction_id, order_number,
	 *   amount, vault_guid, vault_operation,
	 *   first_name, mid_name, last_name,
	 *   address_line1, address_line2, city, state, zip_code, country, email, telephone
	 */
	function contract_build_vault_sale_xml(array $args)
	{
		$amount_fmt = number_format(floatval($args['amount']), 2, '.', '');

		$merchant_id      = $args['merchant_id'];
		$merchant_key     = $args['merchant_key'];
		$transaction_type = $args['transaction_type'];
		$transaction_id   = $args['transaction_id'];
		$order_number     = $args['order_number'];
		$vault_guid       = $args['vault_guid'];
		$vault_operation  = $args['vault_operation'];
		$first_name       = $args['first_name'];
		$mid_name         = $args['mid_name'];
		$last_name        = $args['last_name'];
		$address_line1    = $args['address_line1'];
		$address_line2    = isset($args['address_line2']) ? (!is_array($args['address_line2']) ? $args['address_line2'] : '') : '';
		$city             = $args['city'];
		$state            = $args['state'];
		$zip_code         = $args['zip_code'];
		$country          = $args['country'];
		$email            = $args['email'];
		$telephone        = isset($args['telephone']) ? (!is_array($args['telephone']) ? $args['telephone'] : '') : '';

		return "<?xml version=\"1.0\" encoding=\"utf-16\"?>
<Request_v1 xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\">
    <Application>
        <ApplicationID>DEMO</ApplicationID>
        <LanguageID>EN</LanguageID>
    </Application>
    <Payments>
        <PaymentType>
            <Merchant>
                <MerchantID>$merchant_id</MerchantID>
                <MerchantKey>$merchant_key</MerchantKey>
            </Merchant>
            <TransactionBase>
                <TransactionID>$transaction_id</TransactionID>
                <TransactionType>$transaction_type</TransactionType>
                <Reference1>$order_number</Reference1>
                <Amount>$amount_fmt</Amount>
            </TransactionBase>
            <VaultStorage>
                <GUID>$vault_guid</GUID>
                <Service>$vault_operation</Service>
            </VaultStorage>
            <Customer>
                <Name>
                    <FirstName>$first_name</FirstName>
                    <MI>$mid_name</MI>
                    <LastName>$last_name</LastName>
                </Name>
                <Address>
                    <AddressLine1>$address_line1</AddressLine1>
                    <AddressLine2>$address_line2</AddressLine2>
                    <City>$city</City>
                    <State>$state</State>
                    <ZipCode>$zip_code</ZipCode>
                    <Country>$country</Country>
                    <EmailAddress>$email</EmailAddress>
                    <Telephone>$telephone</Telephone>
                    <Fax></Fax>
                </Address>
            </Customer>
        </PaymentType>
    </Payments>
</Request_v1>";
	}

	/**
	 * Posts a vault sale XML request to Sage and returns the raw response body.
	 */
	function contract_post_vault_sale_request($xmlRequest)
	{
		$url  = 'https://www.sageexchange.com/sevd/frmPayment.aspx';
		$body = 'request=' . urlencode($xmlRequest);
		return makePostRequest($body, $url);
	}

	/**
	 * Parse a raw Sage XML response body into a decoded array, or null on failure.
	 * Returns array with keys: paymentResponse (array|null), jsonResponse (string|null).
	 */
	function contract_decode_payment_xml($responseBody)
	{
		try {
			$xmlResponse = simplexml_load_string($responseBody);
		} catch (Exception $e) {
			error_log($e);
			$xmlResponse = null;
		}
		if (!$xmlResponse) {
			return array('paymentResponse' => null, 'jsonResponse' => null);
		}
		$jsonResponse = json_encode($xmlResponse);
		
		return array(
			'paymentResponse' => json_decode($jsonResponse, true),
			'jsonResponse'    => $jsonResponse,
		);
	}
}
?>
