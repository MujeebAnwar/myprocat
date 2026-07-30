<?php
/**
 * MyProCat Auto-Purchase Script
 *
 * When a user's balance (minutes from billing_data) falls below balance_threshold
 * (stored in hours), automatically charge their saved card and add hours.
 *
 * Purchase size: min_account_balance - balance_threshold (both in hours)
 * Example: threshold 20 hours, minimum 100 hours -> buy 80 hours.
 *
 * Usage (cron, e.g. hourly):
 *   php /path/to/subscription/myprocat/background_scripts/auto_purchase.php
 */

require_once (realpath(__DIR__ . '/..') . '/../config.php');
require_once DOCUMENT_ROOT . '/paymentSdk/shared.php';
require_once DOCUMENT_ROOT . '/lib/database.php';
require_once DOCUMENT_ROOT . '/subscription/helper.php';
require_once __DIR__ . '/../payment_complete.php';
require_once __DIR__ . '/helper.php';

$DB = new databaseI();


$candidates = myprocat_bg_fetch_auto_purchase_candidates($DB);

$checked_count = 0;
$skipped_count = 0;
$purchased_count = 0;
$failed_count = 0;

foreach ($candidates as $purchase) {
	if (!isset($purchase['id']) || $purchase['id'] === 'id') {
		continue;
	}

	$purchase_id = (int)$purchase['id'];
	$user_id = (string)$purchase['id_owner'];
	$threshold = (float)$purchase['balance_threshold'];
	$min_balance = (float)$purchase['min_account_balance'];
	$rate = (float)$purchase['rate'];

	if ($threshold < 0 || $min_balance < $threshold || $rate <= 0) {
		echo "Invalid auto-purchase settings: purchase #{$purchase_id} ({$user_id}) - skipping\n";
		$skipped_count++;
		continue;
	}

	$checked_count++;

	if (myprocat_bg_card_is_expired($purchase['card_expiry_date'])) {
		echo "Expired card: purchase #{$purchase_id} ({$user_id}) - skipping\n";
		$skipped_count++;
		continue;
	}

	$balance_minutes = myprocat_bg_user_balance_minutes($DB, $user_id);
	if ($balance_minutes === null) {
		echo "Balance unavailable: purchase #{$purchase_id} ({$user_id}) - skipping\n";
		$skipped_count++;
		continue;
	}

	$threshold_minutes = myprocat_bg_hours_to_minutes($threshold);

	if ($balance_minutes >= $threshold_minutes) {
		echo "Balance OK: purchase #{$purchase_id} ({$user_id}) balance={$balance_minutes}min threshold={$threshold}hr ({$threshold_minutes}min)\n";
		continue;
	}

	$new_hours = myprocat_bg_calculate_auto_purchase_hours($threshold, $min_balance);
	$license_min_hours = myprocat_bg_license_minimum_hours($DB, $purchase);
	if ($new_hours < $license_min_hours) {
		$new_hours = $license_min_hours;
	}

	if ($new_hours <= 0) {
		echo "Invalid purchase hours: purchase #{$purchase_id} ({$user_id}) - skipping\n";
		$skipped_count++;
		continue;
	}

	$amount = round($new_hours * $rate, 2);
	if ($amount <= 0) {
		echo "Invalid purchase amount: purchase #{$purchase_id} ({$user_id}) - skipping\n";
		$skipped_count++;
		continue;
	}

	$payment = myprocat_bg_charge_saved_card($DB, $purchase, $new_hours, $amount, $sharedCredentials, $transactionTypes);
	if ($payment === null) {
		echo "Payment declined or gateway error: purchase #{$purchase_id} ({$user_id}) balance={$balance_minutes}min threshold={$threshold}hr\n";
		$failed_count++;
		continue;
	}

	try {
		$result = myprocat_finalize_successful_payment($DB, array(
			'user_id'                  => $user_id,
			'license_id'               => (int)$purchase['license_id'],
			'license_type'             => $purchase['license_type'],
			'license_title'            => $purchase['license_title'],
			'hours'                    => $new_hours,
			'rate'                     => $rate,
			'transaction_id'           => $payment['transaction_id'],
			'transaction_amount'       => $payment['transaction_amount'],
			'transaction_date'         => $payment['transaction_date'],
			'van_reference'            => $payment['van_reference'],
			'last4'                    => $payment['last4'],
			'payment_method'           => $payment['payment_method'],
			'json_response'            => $payment['json_response'],
			'guid'                     => $purchase['guid'],
			'vault_id'                 => $purchase['vault_id'],
			'address_details'          => (string)$purchase['address_details'],
			'card_expiry_sql'          => trim((string)$purchase['card_expiry_date']) !== '' && $purchase['card_expiry_date'] !== 'card_expiry_date'
				? date('Y-m-d', strtotime($purchase['card_expiry_date']))
				: date('Y-m-d'),
			'preserve_purchase_record' => true,
			'myprocat_purchase_id'     => $purchase_id,
			'delete_session_key'       => null,
		));

		$purchased_count++;
		echo "Auto-purchased {$new_hours} hours for {$user_id}: invoice #{$result['invoice_number']} (balance was {$balance_minutes}min, threshold {$threshold}hr)\n";
	} catch (Exception $e) {
		error_log($e);
		echo "Finalize failed: purchase #{$purchase_id} ({$user_id}) - {$e->getMessage()}\n";
		$failed_count++;
	}
}

echo "Done. checked={$checked_count} purchased={$purchased_count} skipped={$skipped_count} failed={$failed_count}\n";
