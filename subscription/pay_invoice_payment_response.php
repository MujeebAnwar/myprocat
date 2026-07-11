<?php
require_once('config.php');
require_once(DOCUMENT_ROOT . '/setup/start.php');
require_once(DOCUMENT_ROOT . '/paymentSdk/shared.php');
require_once(DOCUMENT_ROOT . '/subscription/helper.php');
require_once(DOCUMENT_ROOT . '/Service/EmailService.php');
if (!isset($UserAccount) || !$UserAccount->logged_in) {
    header('Location: /login.php');
    exit;
}

if (!isset($_REQUEST['response'])) {
    header('Location: /subscription/payment_failed.php');
    exit;
}

$response = openEnvelope($_REQUEST['response']);
$xmlResponse = simplexml_load_string($response);
if (!$xmlResponse) {
    header('Location: /subscription/payment_failed.php');
    exit;
}

$jsonResponse = json_encode($xmlResponse);
$paymentResponse = json_decode($jsonResponse, true);

if (!isset($paymentResponse['PaymentResponses'])) {
    header('Location: /subscription/payment_failed.php');
    exit;
}

$indicator = $paymentResponse['PaymentResponses']['PaymentResponseType']['Response']['ResponseIndicator'] ?? '';
if ($indicator !== 'A') {
    header('Location: /subscription/payment_failed.php');
    exit;
}

$transaction_id = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['TransactionID'] ?? '';
if (empty($transaction_id)) {
    header('Location: /subscription/payment_failed.php');
    exit;
}

// Look up session context
$transactionData = ['transaction_key', 'data'];
$DB->sql(
    'SELECT transaction_key, data FROM casepad_subscription_session WHERE transaction_key = ?',
    array('s', $transaction_id),
    $transactionData
);

if (!isset($transactionData[0]['data'])) {
    header('Location: /subscription/payment_failed.php');
    exit;
}

$ctx = json_decode($transactionData[0]['data'], true);
if (!is_array($ctx) || ($ctx['flow'] ?? '') !== 'pay_invoice') {
    header('Location: /subscription/payment_failed.php');
    exit;
}

$invoice_id = isset($ctx['invoice_id']) ? (int)$ctx['invoice_id'] : 0;
if ($invoice_id <= 0) {
    header('Location: /subscription/payment_failed.php');
    exit;
}

// Load invoice and verify ownership
$invoiceRow = array('id', 'invoice_number', 'id_owner', 'subscribed_plan_id', 'total_amount','rate', 'hours', 'status');
$DB->sql(
    'SELECT id, invoice_number, id_owner, subscribed_plan_id, total_amount, rate, hours, status
     FROM casepad_payment_invoices
     WHERE id = ? AND id_owner = ?
     LIMIT 1',
    array('ss', $invoice_id, $UserAccount->user_details['id_user']),
    $invoiceRow
);
if (!isset($invoiceRow[0])) {
    header('Location: /subscription/payment_failed.php');
    exit;
}
$invoice = $invoiceRow[0];

// Payment details
$transaction_date = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['TransactionDate'] ?? date('c');
$transaction_amount = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['Amount'] ?? $invoice['total_amount'];
$paymentTypeId = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['PaymentTypeID'] ?? '';
$last4 = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['Last4'] ?? '';
$vanReference = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['VANReference'] ?? '';

switch ($paymentTypeId) {
    case '3': $paymentMethod = 'American Express'; break;
    case '4': $paymentMethod = 'Visa'; break;
    case '5': $paymentMethod = 'MasterCard'; break;
    case '6': $paymentMethod = 'Discover'; break;
    case '7': $paymentMethod = 'JCB'; break;
    case 'D': $paymentMethod = 'Debit Card'; break;
    case 'C': $paymentMethod = 'ACH'; break;
    default:  $paymentMethod = 'Other'; break;
}
$last4 = !empty($last4) ? substr($last4, -4) : NULL;
$now = date("Y-m-d H:i:s", strtotime($transaction_date));

// Determine plan name for credits
$planName = $ctx['plan_name'] ?? 'Subscription';

try {
    // Mark invoice as paid
    $DB->sql(
        'UPDATE casepad_payment_invoices
         SET status = 1,
             transaction_id = ?,
             van_reference = ?,
             last_four_digits = ?,
             payment_method = ?,
             payment_response = ?
         WHERE id = ?',
        array('sssssi', $transaction_id, $vanReference, $last4, $paymentMethod, $jsonResponse, $invoice['id'])
    );

    // Grant credits if not already granted
    $creditCheck = array('count');
    $DB->sql(
        'SELECT COUNT(*) as count FROM casepad_minutes_credits WHERE invoice_id = ?',
        array('s', $invoice['id']),
        $creditCheck
    );
    $alreadyGranted = isset($creditCheck[0]['count']) ? (int)$creditCheck[0]['count'] : 0;
    if ($alreadyGranted === 0) {
        $DB->sql(
            'INSERT INTO casepad_minutes_credits (`id_owner`,`minutes`,`time_stamp`,`source`,`invoice_id`) VALUES (?,?,?,?,?)',
            array('ssssi', $UserAccount->user_details['id_user'], ((int)$invoice['hours']) * 60, $now, $planName, $invoice['id'])
        );

        $storage = 10;
        $DB->sql(
            'INSERT INTO casepad_storage_credits (`id_owner`,`storage`,`time_stamp`,`source`,`invoice_id`) VALUES (?,?,?,?,?)',
            array('sissi', $UserAccount->user_details['id_user'], $storage, $now, $planName, $invoice['id'])
        );
    }

    // Reactivate subscription (common case for failed recurring invoice)
    $subRow = array('id', 'commitment', 'prepaid', 'subscribed_at', 'plan_id');
    $DB->sql(
        'SELECT id, commitment, prepaid, subscribed_at, plan_id
         FROM casepad_subscribed_plan
         WHERE id = ? AND id_owner = ?
         LIMIT 1',
        array('ss', $invoice['subscribed_plan_id'], $UserAccount->user_details['id_user']),
        $subRow
    );
    if (isset($subRow[0])) {
        $sub = $subRow[0];
        $next_payment_date = NULL;
        if ($sub['commitment'] === 'annual' && (int)$sub['prepaid'] === 0) {
            $start = new DateTime($sub['subscribed_at']);
            $nextMonthDate = (new DateTime())->modify('+1 month');
            $diff = $start->diff($nextMonthDate);
            if ($diff->y == 1) {
                $next_payment_date = NULL;
            } else {
                $next_payment_date = $nextMonthDate->format('Y-m-d H:i:s');
            }
        }
        $DB->sql(
            'UPDATE casepad_subscribed_plan SET is_active = 1, next_payment_date = ? WHERE id = ? AND id_owner = ?',
            array('sss', $next_payment_date, $sub['id'], $UserAccount->user_details['id_user'])
        );
    }

    // Clean up session
    $DB->sql(
        'DELETE FROM casepad_subscription_session WHERE transaction_key = ?',
        array('s', $transaction_id)
    );

    $customerEmail = $UserAccount->user_details['email'];
    $customerName = $UserAccount->user_details['first_name'] . ' ' . $UserAccount->user_details['mid_name'] . ' ' . $UserAccount->user_details['last_name'];
    $invoiceData = [
        'invoice_number' => $invoice['invoice_number'],
        'invoice_date' => date("F j, Y g:i A", strtotime($invoice['invoice_date'])),
        'rate' => $invoice['rate'],
        'hours' => $invoice['hours'],
        'payment_method' => $paymentMethod,
        'total_amount' => $invoice['total_amount'],
        'address_details' => $address_details,
        'customer_name' => $customerName,
    ];
    ob_start();
    include(DOCUMENT_ROOT . '/subscription/invoice-email.php');
    $emailBody = ob_get_clean();
    if (!empty($customerEmail)) {
        $mail = new EmailService();
        $mail->send($customerEmail, "DepoDash Invoice - Order #" . $invoice['invoice_number'], $emailBody);
    }
    $plan_id = isset($ctx['plan_id']) ? (int)$ctx['plan_id'] : 0;
    $thankYouUrl = '/subscription/thank_you.php?order_id=' . urlencode($invoice['invoice_number'])
        . '&plan=' . urlencode((string)$plan_id)
        . '&amount=' . urlencode((string)$transaction_amount);
    header('Location: ' . $thankYouUrl);
    exit;
} catch (Exception $e) {
    header('Location: /subscription/payment_failed.php');
    exit;
}


