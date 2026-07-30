<?php
$encryption = include DOCUMENT_ROOT . '/config/encryption.php';

function encryptData($data)
{
    global $encryption;
    $cipher = $encryption['encryption_method'];
    $secretKey = $encryption['secret_key'];
    $key = hash('sha256', $secretKey, true);

    $ivLength = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivLength);

    $encrypted = openssl_encrypt($data, $cipher, $key, OPENSSL_RAW_DATA, $iv);

    // Combine IV + encrypted data
    return base64_encode($iv . $encrypted);
}

function decryptData($encryptedData)
{
    global $encryption;
    $cipher = $encryption['encryption_method'];
    $secretKey = $encryption['secret_key'];
    $key = hash('sha256', $secretKey, true);

    $data = base64_decode($encryptedData);
    $ivLength = openssl_cipher_iv_length($cipher);

    $iv = substr($data, 0, $ivLength);
    $encrypted = substr($data, $ivLength);

    return openssl_decrypt($encrypted, $cipher, $key, OPENSSL_RAW_DATA, $iv);
}

function send_invoice_email($customerEmail, $invoiceNumber, $emailBody)
{
    if (empty($customerEmail)) {
        return false;
    }

    $mailConfig = include DOCUMENT_ROOT . '/config/mail.php';
    $invoiceCcEmail = $mailConfig['invoice_cc_email'] ?? 'cs@procat.com';

    require_once DOCUMENT_ROOT . '/Service/EmailService.php';
    $mail = new EmailService();

    return $mail->send(
        $customerEmail,
        'MyProCat Invoice - Order #' . $invoiceNumber,
        $emailBody,
        true,
        $invoiceCcEmail
    );
}

function format_order_billing_label($billing, $annualPaymentType = null)
{
    if ($billing === 'monthly') {
        return 'Monthly';
    }
    if ($billing === 'annual' && $annualPaymentType === 'monthly') {
        return 'Annual Monthly';
    }
    if ($billing === 'annual') {
        return 'Annual';
    }

    return ucfirst((string)$billing);
}

function fit_order_number_parts($leadingPart, $trailingPart, $maxLength = 50)
{
    if (strlen($leadingPart) + strlen($trailingPart) <= $maxLength) {
        return $leadingPart . $trailingPart;
    }

    $maxLeadingLength = $maxLength - strlen($trailingPart);
    if ($maxLeadingLength < 1) {
        return substr($trailingPart, 0, $maxLength);
    }

    return rtrim(substr($leadingPart, 0, $maxLeadingLength)) . $trailingPart;
}

function build_subscription_order_number($planName, $hours, $billingLabel, $requireUnique = false, $maxLength = 50)
{
    $planName = trim((string)$planName);
    $hours = (int)$hours;
    $uniquePart = $requireUnique ? ' #' . substr(uniqid(), -6) : '';
    $trailingPart = sprintf(' - %dhrs (%s)%s', $hours, $billingLabel, $uniquePart);

    return fit_order_number_parts($planName, $trailingPart, $maxLength);
}

function build_addon_order_number($planName, $hours, $billing, $annualPaymentType = null, $requireUnique = false, $maxLength = 50)
{
    $planName = trim((string)$planName);
    $hours = (int)$hours;
    $billingLabel = format_order_billing_label($billing, $annualPaymentType);
    $uniquePart = $requireUnique ? ' #' . substr(uniqid(), -6) : '';
    $trailingPart = sprintf(' - %dhrs Add-on (%s)%s', $hours, $billingLabel, $uniquePart);

    return fit_order_number_parts($planName, $trailingPart, $maxLength);
}

function build_invoice_order_number($invoiceNumber, $planName, $hours, $maxLength = 50)
{
    $planName = trim((string)$planName);
    $hours = (int)$hours;
    $trailingPart = sprintf(' %dhrs', $hours);
    $leadingPart = 'Inv #' . $invoiceNumber . ' - ';

    return fit_order_number_parts($leadingPart, $planName . $trailingPart, $maxLength);
}

function build_recurring_order_number($planName, $hours, $maxLength = 50)
{
    return build_subscription_order_number($planName, $hours, 'Recurring', false, $maxLength);
}

function build_license_order_number($licenseTitle, $hours, $billingLabel = null, $requireUnique = false, $maxLength = 50)
{
    $licenseTitle = trim((string)$licenseTitle);
    $hours = (int)$hours;

    if ($billingLabel !== null && $billingLabel !== '') {
        return build_subscription_order_number($licenseTitle, $hours, $billingLabel, $requireUnique, $maxLength);
    }

    $uniquePart = $requireUnique ? ' #' . substr(uniqid(), -6) : '';
    $trailingPart = sprintf(' - %dhrs%s', $hours, $uniquePart);

    return fit_order_number_parts($licenseTitle, $trailingPart, $maxLength);
}

function build_order_number($description, $uniqueSuffix = null, $maxLength = 50)
{
    if ($uniqueSuffix !== null && $uniqueSuffix !== '') {
        $uniqueSuffix = ' #' . substr((string)$uniqueSuffix, -6);
    }

    return fit_order_number_parts((string)$description, (string)$uniqueSuffix, $maxLength);
}

/**
 * Advance next_payment_date for annual monthly-installment subscriptions.
 * Clears the date once a full year from subscribed_at has elapsed.
 */
function advance_annual_installment_next_payment_date($DB, $subscription)
{
    if (!isset($subscription['commitment']) || $subscription['commitment'] !== 'annual') {
        return;
    }
    if (!isset($subscription['prepaid']) || intval($subscription['prepaid']) !== 0) {
        return;
    }

    $start = new DateTime($subscription['subscribed_at']);
    $nextMonthDate = (new DateTime())->modify('+1 month');
    $diff = $start->diff($nextMonthDate);
    if ($diff->y >= 1) {
        $nextMonthDate = null;
    } else {
        $nextMonthDate = $nextMonthDate->format('Y-m-d H:i:s');
    }

    $DB->sql(
        'UPDATE casepad_subscribed_plan SET next_payment_date = ? WHERE id = ?',
        array('ss', $nextMonthDate, $subscription['id'])
    );
}
