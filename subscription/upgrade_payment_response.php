<?php
require_once('config.php');
require_once (DOCUMENT_ROOT.'/paymentSdk/shared.php');
require_once(DOCUMENT_ROOT . '/setup/start.php');
require_once(DOCUMENT_ROOT . '/Service/EmailService.php');
require_once(DOCUMENT_ROOT . '/subscription/helper.php');

$response = openEnvelope($_REQUEST['response']);
$xmlResponse = simplexml_load_string($response);

if (!$xmlResponse) {
    header('Location: /subscription/upgrade.php');
    exit;
}

$jsonResponse = json_encode($xmlResponse);
$paymentResponse = json_decode($jsonResponse, true);

if (!isset($paymentResponse['PaymentResponses'])) {
    header('Location: /subscription/payment_failed.php');
    exit;
}

if (
    !isset($paymentResponse['PaymentResponses']['PaymentResponseType']['Response']['ResponseIndicator']) ||
    $paymentResponse['PaymentResponses']['PaymentResponseType']['Response']['ResponseIndicator'] !== 'A'
) {
    header('Location: /subscription/payment_failed.php');
    exit;
}

$transaction_id = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['TransactionID'];
$transactionData = ['transaction_key', 'data'];
$DB->sql(
    'SELECT transaction_key,data FROM casepad_subscription_session WHERE transaction_key = ?',
    array('s', $transaction_id),
    $transactionData
);

if (!isset($transactionData[0]['data'])) {
    header('Location: /subscription/payment_failed.php');
    exit;
}

try {
    $transaction_data = json_decode($transactionData[0]['data'], true);
    if (!is_array($transaction_data) || !isset($transaction_data['flow']) || $transaction_data['flow'] !== 'upgrade_new_card') {
        header('Location: /subscription/payment_failed.php');
        exit;
    }

    $subscribed_plan_id = $transaction_data['subscribed_plan_id'];
    $planName = $transaction_data['plan_name'];
    $plan_id = $transaction_data['plan_id'];
    $hours = (int)$transaction_data['hours'];
    $rate = (float)$transaction_data['rate'];
    $billing = $transaction_data['billing'];
    $annualPaymentType = isset($transaction_data['annualPaymentType']) ? $transaction_data['annualPaymentType'] : 'one_time';
    $prepaid = ($annualPaymentType === 'one_time') ? 1 : 0;
    $vault_id = isset($transaction_data['vault_id']) ? $transaction_data['vault_id'] : '';
    $user_id = $transaction_data['user_id'];

    // Ensure user owns subscription
    $sub = ['id', 'id_owner', 'is_active', 'address_details'];
    $DB->sql(
        'SELECT id,id_owner,is_active,address_details FROM casepad_subscribed_plan WHERE id = ?',
        array('s', $subscribed_plan_id),
        $sub
    );

    if (!isset($sub[0]) || $sub[0]['id_owner'] != $user_id || (int)$sub[0]['is_active'] !== 1) {
        header('Location: /subscription/payment_failed.php');
        exit;
    }

    $transaction_date = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['TransactionDate'];
    $transaction_amount = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['Amount'];
    $vanReference = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['VANReference'];
    $paymentTypeId = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['PaymentTypeID'] ?? '';

    $guidRaw = $paymentResponse['PaymentResponses']['PaymentResponseType']['VaultResponse']['GUID'] ?? '';
    $expiration_date = $paymentResponse['PaymentResponses']['PaymentResponseType']['VaultResponse']['ExpirationDate'] ?? '';
    $last4 = $paymentResponse['PaymentResponses']['PaymentResponseType']['VaultResponse']['Last4'] ?? '';
    $discount = 0;
    // Mask to 4 digits (keeps existing code style in repo)
    $last4 = str_repeat('', strlen($last4) - 4) . substr($last4, -4);

    // Convert expiration_date (MMYY) -> YYYY-MM-01
    $card_expiry_date = null;
    if (!empty($expiration_date) && strlen($expiration_date) >= 4) {
        $month = substr($expiration_date, 0, 2);
        $currentYear = date('Y');
        $currentYear = substr($currentYear, 0, 2);
        $year = $currentYear . substr($expiration_date, 2, 2);
        $card_expiry_date = date('Y-m-d', strtotime("01-$month-$year"));
    }

    // Capture address details returned by gateway (fallback to current address_details if missing)
    $address_details = null;
    if (isset($paymentResponse['PaymentResponses']['PaymentResponseType']['Customer']['Address'])) {
        $address_details = json_encode($paymentResponse['PaymentResponses']['PaymentResponseType']['Customer']['Address']);
    } else if (isset($sub[0]['address_details'])) {
        $address_details = $sub[0]['address_details'];
    }

    // Map payment method
    $paymentMethod = 'Other';
    switch ((string)$paymentTypeId) {
        case '3': $paymentMethod = 'American Express'; break;
        case '4': $paymentMethod = 'Visa'; break;
        case '5': $paymentMethod = 'MasterCard'; break;
        case '6': $paymentMethod = 'Discover'; break;
        case '7': $paymentMethod = 'JCB'; break;
        case 'D': $paymentMethod = 'Debit Card'; break;
        case 'O': $paymentMethod = 'Other'; break;
        case 'C': $paymentMethod = 'ACH'; break;
        default: $paymentMethod = 'Other'; break;
    }

    $next_payment_date = null;
    if (!$prepaid) {
        $next_payment_date = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s', strtotime('+1 month'))));
    }

    if($billing == 'annual' && $annualPaymentType){
        $hours = $hours * 12;
        $discount = ($transaction_amount * 1) / (100 - 1);
    }
    $subscriped_at = date('Y-m-d H:i:s');

    $guid = !empty($guidRaw) ? encryptData($guidRaw) : null;
    $vault_id_enc = !empty($vault_id) ? encryptData($vault_id) : null;

    // Update subscription (plan + card)
    $DB->sql(
        'UPDATE casepad_subscribed_plan SET plan_id = ?, rate = ?, hours = ?, commitment = ?, prepaid = ?, next_payment_date = ?, subscribed_at = ?, guid = ?, vault_id = ?, last_four_digits = ?, card_expiry_date = ?, address_details = ?, updated_at = ? WHERE id = ?',
        array(
            'isssisssssssss',
            $plan_id,
            $rate,
            $hours,
            $billing,
            $prepaid,
            $next_payment_date,
            $subscriped_at,
            $guid,
            $vault_id_enc,
            $last4,
            $card_expiry_date,
            $address_details,
            date('Y-m-d H:i:s'),
            $subscribed_plan_id
        )
    );

    // Get the last invoice number
    $invoiceNumber = array('invoice_number');
    $DB->sql(
        'SELECT MAX(invoice_number) as invoice_number FROM casepad_payment_invoices',
        array(),
        $invoiceNumber
    );

    if (!isset($invoiceNumber[0]['invoice_number']) || is_null($invoiceNumber[0]['invoice_number'])) {
        $invoice_number = 10800;
    } else {
        $invoice_number = $invoiceNumber[0]['invoice_number'] + 1;
    }

    $invoice_date = date("Y-m-d H:i:s", strtotime($transaction_date));

    $DB->sql(
        'INSERT INTO casepad_payment_invoices (`id_owner`,`subscribed_plan_id`,`invoice_number`,`transaction_id`,`van_reference`,`last_four_digits`,`invoice_date`,`payment_method`,`discount`, `total_amount`,`rate`,`hours`,`payment_response`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
        array('sssssssssssis', $user_id, $subscribed_plan_id, $invoice_number, $transaction_id, $vanReference, $last4, $invoice_date, $paymentMethod, $discount,$transaction_amount, $rate, $hours, $jsonResponse)
    );

    $invoice_id = $DB->iid();

    $DB->sql(
        'INSERT INTO casepad_minutes_credits (`id_owner`,`minutes`,`time_stamp`,`source`,`invoice_id`) VALUES (?,?,?,?,?)',
        array('ssssi', $user_id, $hours * 60, $invoice_date, $planName, $invoice_id)
    );

    $storage = 10;
    $DB->sql(
        'INSERT INTO casepad_storage_credits (`id_owner`,`storage`,`time_stamp`,`source`,`invoice_id`) VALUES (?,?,?,?,?)',
        array('sissi', $user_id, $storage, $invoice_date, $planName, $invoice_id)
    );

    $DB->sql(
        'DELETE FROM casepad_subscription_session WHERE transaction_key = ?',
        array('s', $transaction_id)
    );

    // Email invoice
    $user_details = array('first_name', 'mid_name', 'last_name', 'email');
    $DB->sql(
        'SELECT first_name,mid_name,last_name,email FROM accounts WHERE id_user = ?',
        array('s', $user_id),
        $user_details
    );

    $customerEmail = $user_details[0]['email'];
    $customerName = $user_details[0]['first_name'] . ' ' . $user_details[0]['mid_name'] . ' ' . $user_details[0]['last_name'];

    $invoiceData = [
        'invoice_number' => $invoice_number,
        'invoice_date' => date("F j, Y g:i A", strtotime($invoice_date)),
        'rate' => $rate,
        'hours' => $hours,
        'payment_method' => $paymentMethod,
        'total_amount' => $transaction_amount,
        'address_details' => $address_details ? json_decode($address_details, true) : [],
        'customer_name' => $customerName,
        'discount' => $discount,
    ];

    ob_start();
    include(DOCUMENT_ROOT . '/subscription/invoice-email.php');
    $emailBody = ob_get_clean();

    if (!empty($customerEmail)) {
        $mail = new EmailService();
        $mail->send($customerEmail, "DepoDash Invoice - Order #" . $invoice_number, $emailBody);
    }

    $thankYouUrl = '/subscription/thank_you.php?order_id=' . urlencode($invoice_number)
        . '&plan=' . urlencode($plan_id)
        . '&amount=' . urlencode($transaction_amount);
    header('Location: ' . $thankYouUrl);
    exit;
} catch (Exception $e) {
    header('Location: /subscription/payment_failed.php');
    exit;
}


