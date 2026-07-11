<?php
require_once('config.php');
require_once(DOCUMENT_ROOT . '/setup/start.php');
require_once(DOCUMENT_ROOT . '/template/Master.php');
require_once(DOCUMENT_ROOT . '/paymentSdk/shared.php');
require_once(DOCUMENT_ROOT . '/subscription/helper.php');
require_once(DOCUMENT_ROOT . '/Service/EmailService.php');
if (!isset($UserAccount) || !$UserAccount->logged_in) {
    header('Location: /login.php');
    exit;
}

// In the invoices UI this param currently holds the invoice *id* (not the invoice_number).
$invoice_id = isset($_REQUEST['invoice_number']) ? (int)$_REQUEST['invoice_number'] : 0;
$method = isset($_REQUEST['method']) ? (string)$_REQUEST['method'] : '';

if ($invoice_id <= 0) {
    header('Location: /subscription/invoice.php');
    exit;
}

// Load invoice (must belong to current user)
$invoiceRow = array(
    'id',
    'invoice_number',
    'id_owner',
    'subscribed_plan_id',
    'total_amount',
    'rate',
    'hours',
    'status'
);
$DB->sql(
    'SELECT id, invoice_number, id_owner, subscribed_plan_id, total_amount, rate, hours, status
     FROM casepad_payment_invoices
     WHERE id = ? AND id_owner = ?
     LIMIT 1',
    array('ss', $invoice_id, $UserAccount->user_details['id_user']),
    $invoiceRow
);
if (!isset($invoiceRow[0])) {
    header('Location: /subscription/invoices.php');
    exit;
}
$invoice = $invoiceRow[0];

if ((int)$invoice['status'] === 1) {
    header('Location: /subscription/invoices.php');
    exit;
}

// Load subscription info (guid + address + plan)
$subRow = array('id', 'plan_id', 'guid', 'vault_id', 'address_details', 'commitment', 'prepaid', 'subscribed_at', 'last_four_digits', 'card_expiry_date');
$DB->sql(
    'SELECT id, plan_id, guid, vault_id, address_details, commitment, prepaid, subscribed_at, last_four_digits, card_expiry_date
     FROM casepad_subscribed_plan
     WHERE id = ? AND id_owner = ?
     LIMIT 1',
    array('ss', $invoice['subscribed_plan_id'], $UserAccount->user_details['id_user']),
    $subRow
);
if (!isset($subRow[0])) {
    header('Location: /subscription/invoices.php');
    exit;
}
$sub = $subRow[0];

$planNameRow = array('name');
$DB->sql(
    'SELECT name FROM subscription_plans WHERE id = ? LIMIT 1',
    array('s', $sub['plan_id']),
    $planNameRow
);
$planName = isset($planNameRow[0]['name']) ? $planNameRow[0]['name'] : 'Subscription';

// If no method provided (e.g. no JS), show a simple chooser page.
if ($method !== 'existing' && $method !== 'new') {
    $set_title = "Pay Invoice - DepoDash";
    $set_body = new content_block(NULL, 'div', array('class' => 'inner-content'));
    $card = new content_block(NULL, 'div', array('class' => 'form-box', 'style' => 'max-width: 600px; margin: 60px auto; text-align: center; padding: 40px 30px;'));
    $card->push(new content_block('Pay Invoice', 'h1', array('style' => 'color: #27475f; font-size: 28px; margin-bottom: 10px;')));
    $card->push(new paragraph('Choose how you want to pay this invoice.', array('style' => 'color: #666; font-size: 14px; margin-bottom: 18px;')));

    $details = new content_block(NULL, 'div', array('style' => 'background: #f8f9fa; border-radius: 8px; padding: 18px; margin-bottom: 22px; text-align: left;'));
    $details->push(new content_block('Invoice Details', 'h3', array('style' => 'color: #27475f; font-size: 16px; margin-bottom: 12px; border-bottom: 1px solid #e9ecef; padding-bottom: 8px;')));
    $row1 = new content_block(NULL, 'div', array('style' => 'display:flex; justify-content: space-between; margin-bottom: 8px;'));
    $row1->push(new content_block('Invoice Number:', 'span', array('style' => 'color:#666;')));
    $row1->push(new content_block('#' . htmlspecialchars($invoice['invoice_number']), 'span', array('style' => 'color:#333; font-weight:600;')));
    $details->push($row1);
    $row2 = new content_block(NULL, 'div', array('style' => 'display:flex; justify-content: space-between; margin-bottom: 8px;'));
    $row2->push(new content_block('Amount Due:', 'span', array('style' => 'color:#666;')));
    $row2->push(new content_block('$' . htmlspecialchars($invoice['total_amount']), 'span', array('style' => 'color:#dc3545; font-weight:600;')));
    $details->push($row2);
    $card->push($details);

    $buttons = new content_block(NULL, 'div', array('style' => 'display:flex; gap: 12px; justify-content:center; flex-wrap:wrap;'));
    $hasSavedCard = !empty($sub['guid']) && !empty($sub['vault_id']);
    if ($hasSavedCard) {
        $buttons->push(new anchor('Use existing card', array('class' => 'primary_button text-white', 'href' => '/subscription/pay_invoice.php?invoice_number=' . urlencode($invoice_id) . '&method=existing', 'style' => 'margin-bottom:0; font-size:14px; padding:10px 16px;')));
    } else {
        $buttons->push(new content_block('Use existing card', 'span', array('class' => 'secondary_button', 'style' => 'margin-bottom:0; font-size:14px; padding:10px 16px; opacity:0.5; cursor:not-allowed;')));
    }
    $buttons->push(new anchor('Use new card', array('class' => 'secondary_button', 'href' => '/subscription/pay_invoice.php?invoice_number=' . urlencode($invoice_id) . '&method=new', 'style' => 'margin-bottom:0; font-size:14px; padding:10px 16px;')));
    $buttons->push(new anchor('Back to invoices', array('class' => 'secondary_button', 'href' => '/subscription/invoice.php', 'style' => 'margin-bottom:0; font-size:14px; padding:10px 16px;')));

    // Show saved card (masked)
    if (!empty($sub['last_four_digits'])) {
        $expText = '';
        if (!empty($sub['card_expiry_date']) && strlen($sub['card_expiry_date']) >= 10) {
            $mm = substr($sub['card_expiry_date'], 5, 2);
            $yy = substr($sub['card_expiry_date'], 2, 2);
            $expText = " (exp {$mm}/{$yy})";
        }
        $card->push(new paragraph('Saved card ending in <strong>' . htmlspecialchars($sub['last_four_digits']) . '</strong>' . $expText, array('style' => 'margin-top:16px; color:#666; font-size:14px;')));
    }

    $card->push($buttons);
    $set_body->push($card);
    require_once('mainframe.php');
    exit;
}

// Utility: grant credits once per invoice id.
function grantInvoiceCreditsOnce($invoiceId, $userId, $planName, $hours, $timeStamp)
{
    global $DB;
    $creditCheck = array('count');
    $DB->sql(
        'SELECT COUNT(*) as count FROM casepad_minutes_credits WHERE invoice_id = ?',
        array('s', $invoiceId),
        $creditCheck
    );
    $alreadyGranted = isset($creditCheck[0]['count']) ? (int)$creditCheck[0]['count'] : 0;
    if ($alreadyGranted > 0) {
        return;
    }

    $DB->sql(
        'INSERT INTO casepad_minutes_credits (`id_owner`,`minutes`,`time_stamp`,`source`,`invoice_id`) VALUES (?,?,?,?,?)',
        array('ssssi', $userId, ((int)$hours) * 60, $timeStamp, $planName, $invoiceId)
    );

    $storage = 10;
    $DB->sql(
        'INSERT INTO casepad_storage_credits (`id_owner`,`storage`,`time_stamp`,`source`,`invoice_id`) VALUES (?,?,?,?,?)',
        array('sissi', $userId, $storage, $timeStamp, $planName, $invoiceId)
    );
}

// Utility: reactivate subscription if it was disabled by a failed recurring charge.
function reactivateSubscriptionIfNeeded($subscription)
{
    global $DB, $UserAccount;
    $next_payment_date = NULL;
    if ($subscription['commitment'] === 'annual' && (int)$subscription['prepaid'] === 0) {
        $start = new DateTime($subscription['subscribed_at']);
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
        array('sss', $next_payment_date, $subscription['id'], $UserAccount->user_details['id_user'])
    );
}

// METHOD: EXISTING CARD (Vault GUID, non-UI)
if ($method === 'existing') {
    if (empty($sub['guid'])) {
        header('Location: /subscription/payment_failed.php');
        exit;
    }


    $merchant_id = $sharedCredentials["MID"];
    $merchant_key = $sharedCredentials["MKEY"];
    $transaction_type = $transactionTypes["NonUI"]["Sale"];
    $vaultOperation = "RETRIEVE";

    $firstName = $UserAccount->user_details['first_name'];
    $midName = $UserAccount->user_details['mid_name'];
    $lastName = $UserAccount->user_details['last_name'];

    $address_details = array();
    if (!empty($sub['address_details'])) {
        $address_details = json_decode($sub['address_details'], true);
    }

    $amount = $invoice['total_amount'];
    $order_number = "Invoice #{$invoice['invoice_number']} - {$planName} ({$invoice['hours']} hours)";
    $transaction_id = uniqid('order_', true);

    $vaultToken = decryptData($sub['guid']);

    $address_line1 = $address_details['AddressLine1'] ?? '';
    $address_line2 = $address_details['AddressLine2'] ?? '';
    $city = $address_details['City'] ?? '';
    $state = $address_details['State'] ?? '';
    $zip_code = $address_details['ZipCode'] ?? '';
    $country = $address_details['Country'] ?? '';
    $email = $address_details['EmailAddress'] ?? $UserAccount->user_details['email'];
    $telephone = $address_details['Telephone'] ?? '';

    $xmlRequest = "<?xml version=\"1.0\" encoding=\"utf-16\"?>
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
                <Amount>$amount</Amount>
            </TransactionBase>
            <VaultStorage>
                <GUID>$vaultToken</GUID>
                <Service>$vaultOperation</Service>
            </VaultStorage>
            <Customer>
                <Name>
                    <FirstName>$firstName</FirstName>
                    <MI>$midName</MI>
                    <LastName>$lastName</LastName>
                </Name>
                <Address>
                    <AddressLine1>$address_line1</AddressLine1>
                    <AddressLine2>$address_line2</AddressLine2>
                    <City>$city</City>
                    <State>$state</State>
                    <ZipCode>$zip_code</ZipCode>
                    <Country>$country</Country>
                    <EmailAddress>$email</EmailAddress>
                    <Telephone></Telephone>
                    <Fax></Fax>
                </Address>
            </Customer>
            </PaymentType>
        </Payments>
    </Request_v1>";

    $url = "https://www.sageexchange.com/sevd/frmPayment.aspx";
    $body = "request=" . urlencode($xmlRequest);
    $response = makePostRequest($body, $url);

    $xmlResponse = simplexml_load_string($response);
    if (!$xmlResponse) {
        header('Location: /subscription/payment_failed.php');
        exit;
    }

    $jsonResponse = json_encode($xmlResponse);
    $paymentResponse = json_decode($jsonResponse, true);

    $indicator = $paymentResponse['PaymentResponses']['PaymentResponseType']['Response']['ResponseIndicator'] ?? '';
    if ($indicator !== 'A') {
        header('Location: /subscription/payment_failed.php');
        exit;
    }

    try {
        $transaction_id = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['TransactionID'] ?? '';
        $transaction_date = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['TransactionDate'] ?? date('c');
        $transaction_amount = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['Amount'] ?? $amount;
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


        grantInvoiceCreditsOnce($invoice['id'], $UserAccount->user_details['id_user'], $planName, $invoice['hours'], $now);
        reactivateSubscriptionIfNeeded($sub);

        $invoiceData = [
            'invoice_number' => $invoice['invoice_number'],
            'invoice_date' => date("F j, Y g:i A", strtotime($invoice['invoice_date'])),
            'rate' => $invoice['rate'],
            'hours' => $invoice['hours'],
            'payment_method' => $paymentMethod,
            'total_amount' => $invoice['total_amount'],
            'address_details' => $address_details,
            'customer_name' => $UserAccount->user_details['first_name'] . ' ' . $UserAccount->user_details['mid_name'] . ' ' . $UserAccount->user_details['last_name'],
        ];
        $customerEmail = $UserAccount->user_details['email'];
        ob_start();
        include(DOCUMENT_ROOT . '/subscription/invoice-email.php');
        $emailBody = ob_get_clean();
    
        if (!empty($customerEmail)) {
            $mail = new EmailService();
            $mail->send($customerEmail, "DepoDash Invoice - Order #" . $invoice_number, $emailBody);
        }
        
        $thankYouUrl = '/thank_you.php?order_id=' . urlencode($invoice['invoice_number'])
            . '&plan=' . urlencode($sub['plan_id'])
            . '&amount=' . urlencode($transaction_amount);
        header('Location: ' . $thankYouUrl);
        exit;
    } catch (Exception $e) {
        header('Location: /subscription/payment_failed.php');
        exit;
    }
}

// METHOD: NEW CARD (Sage UI payment)
if ($method === 'new') {
    $merchant_id = $sharedCredentials["MID"];
    $merchant_key = $sharedCredentials["MKEY"];
    $transaction_type = $transactionTypes["UI"]["Sale"];

    $amount = $invoice['total_amount'];
    $order_number = "Invoice #{$invoice['invoice_number']} - {$planName} ({$invoice['hours']} hours)";
    $transaction_id = uniqid('order_', true);

    // Store context so the response handler can finalize the invoice.
    $data = json_encode([
        'flow' => 'pay_invoice',
        'invoice_id' => (int)$invoice['id'],
        'user_id' => (int)$UserAccount->user_details['id_user'],
        'subscribed_plan_id' => (int)$invoice['subscribed_plan_id'],
        'plan_name' => $planName,
        'plan_id' => (int)$sub['plan_id'],
        'hours' => (int)$invoice['hours'],
        'amount' => $amount,
    ]);
    $DB->sql(
        'INSERT INTO casepad_subscription_session (`transaction_key`,`data`) VALUES (?,?)',
        array('ss', $transaction_id, $data)
    );

    $email = $UserAccount->user_details['email'];

    $firstName = $UserAccount->user_details['first_name'];
    $midName = $UserAccount->user_details['mid_name'];
    $lastName = $UserAccount->user_details['last_name'];
    $xmlRequest = "<?xml version=\"1.0\" encoding=\"utf-16\"?>
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
                <Amount>$amount</Amount>
            </TransactionBase>
            <Customer>
                <Name> 
                    <FirstName>$firstName</FirstName> 
                    <MI>$midName</MI> 
                    <LastName>$lastName</LastName> 
                </Name> 
                <Address>
                    <AddressLine1></AddressLine1>
                    <AddressLine2></AddressLine2>
                    <City></City>
                    <State></State>
                    <ZipCode></ZipCode>
                    <Country></Country>
                    <EmailAddress>$email</EmailAddress>
                    <Telephone></Telephone>
                    <Fax></Fax>
                </Address>
            </Customer>
            <Level3>
                <Level2></Level2>
            </Level3>
        </PaymentType>
    </Payments>
    <UI>
        <Display>
            <Header>true</Header>
            <SupportLink>false</SupportLink>
            <CheckPayment>false</CheckPayment>
            <CardPayment>true</CardPayment>
            <SELogo>true</SELogo>
        </Display>
        <Theme>
            <MainFontColor>#333333</MainFontColor>
            <MainBackColor>#f8f9fa</MainBackColor>
            <HeaderBackColor>#1a365d</HeaderBackColor>
            <TotalsBoxBackColor>#fff5f0</TotalsBoxBackColor>
            <DividerBackColor>#ff6600</DividerBackColor>
        </Theme>
        <SinglePayment>
            <TransactionBase>
                <Reference1>
                    <Enabled>true</Enabled>
                    <Visible>true</Visible>
                </Reference1>
                <SubtotalAmount>
                    <Enabled>false</Enabled>
                    <Visible>true</Visible>
                </SubtotalAmount>
                <TaxAmount>
                    <Enabled>false</Enabled>
                    <Visible>true</Visible>
                </TaxAmount>
                <ShippingAmount>
                    <Enabled>false</Enabled>
                    <Visible>false</Visible>
                </ShippingAmount>
            </TransactionBase>
            <Customer>
                <Name>
                    <FirstName>
                        <Enabled>false</Enabled>
                        <Visible>true</Visible>
                    </FirstName>
                    <LastName>
                        <Enabled>false</Enabled>
                        <Visible>false</Visible>
                    </LastName>
                </Name>
                <Address>
                    <AddressLine1>
                        <Enabled>true</Enabled>
                        <Visible>true</Visible>
                    </AddressLine1>
                    <City>
                        <Enabled>true</Enabled>
                        <Visible>true</Visible>
                    </City>
                    <State>
                        <Enabled>true</Enabled>
                        <Visible>true</Visible>
                    </State>
                    <ZipCode>
                        <Enabled>false</Enabled>
                        <Visible>true</Visible>
                    </ZipCode>
                    <Country>
                        <Enabled>false</Enabled>
                        <Visible>true</Visible>
                    </Country>
                    <EmailAddress>
                        <Enabled>false</Enabled>
                        <Visible>true</Visible>
                    </EmailAddress>
                    <Telephone>
                        <Enabled>true</Enabled>
                        <Visible>true</Visible>
                    </Telephone>
                </Address>
            </Customer>
        </SinglePayment>
    </UI>
<UIType>
    <SinglePayment></SinglePayment>
</UIType>
</Request_v1>";

    $tokenizedRequest = getEnvelope($xmlRequest);

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $redirectUrl = $protocol . '://' . $host . '/pay_invoice_payment_response.php';
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Processing Payment...</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
                background: #f5f5f5;
            }
            .payment-container {
                background: white;
                padding: 40px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                text-align: center;
            }
            .spinner {
                border: 4px solid #f3f3f3;
                border-top: 4px solid #3498db;
                border-radius: 50%;
                width: 40px;
                height: 40px;
                animation: spin 1s linear infinite;
                margin: 20px auto;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
    </head>
    <body>
        <div class="payment-container">
            <h2>Redirecting to Payment Gateway...</h2>
            <div class="spinner"></div>
            <p>Please wait while we process your payment.</p>
            <p><strong>Amount: $<?php echo number_format((float)$amount, 2); ?></strong></p>
        </div>

        <form id="paymentForm" method="POST" action="https://www.sageexchange.com/sevd/frmPayment.aspx">
            <input type="hidden" name="request" value="<?php echo htmlspecialchars($tokenizedRequest, ENT_QUOTES); ?>" />
            <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars($redirectUrl, ENT_QUOTES); ?>" />
            <input type="hidden" name="consumer_initiated" value="true" />
        </form>
        <script>
            document.getElementById('paymentForm').submit();
        </script>
    </body>
    </html>
    <?php
    exit;
}

header('Location: /subscription/invoice.php');
exit;
?>


