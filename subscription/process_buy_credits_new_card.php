<?php
// Start a UI payment (new card) for buying additional hours, and create a new Vault GUID.
require_once('config.php');
require_once DOCUMENT_ROOT . '/setup/start.php';
require_once DOCUMENT_ROOT . '/paymentSdk/shared.php';

if (!isset($UserAccount) || !$UserAccount->logged_in) {
    header('Location: /login.php');
    exit;
}

$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0.00;
$planName = isset($_POST['planName']) ? $_POST['planName'] : '';
$plan_id = isset($_POST['plan_id']) ? $_POST['plan_id'] : '';
$description = isset($_POST['description']) ? $_POST['description'] : '';
$billing = isset($_POST['billing']) ? $_POST['billing'] : 'monthly';
$hours = isset($_POST['hours']) ? intval($_POST['hours']) : 0;
$rate = isset($_POST['rate']) ? floatval($_POST['rate']) : 0.00;
$subscription_id = isset($_POST['subscription_id']) ? $_POST['subscription_id'] : '';

if ($amount <= 0 || $hours < 1 || empty($subscription_id)) {
    header('Location: /subscription/buy_credits.php');
    exit;
}

// Verify subscription belongs to user and is active
$sub = array('id', 'id_owner', 'is_active');
$DB->sql(
    'SELECT id, id_owner, is_active FROM casepad_subscribed_plan WHERE id = ?',
    array('s', $subscription_id),
    $sub
);
if (!isset($sub[0]) || !is_array($sub[0]) || $sub[0]['id'] === 'id' || $sub[0]['id_owner'] != $UserAccount->user_details['id_user'] || (int)$sub[0]['is_active'] !== 1) {
    header('Location: /subscription/buy_credits.php');
    exit;
}

// Merchant credentials
$merchant_id = $sharedCredentials["MID"];
$merchant_key = $sharedCredentials["MKEY"];

// Configure the transaction (UI Sale + Vault CREATE)
$transaction_type = $transactionTypes["UI"]["Sale"];
$transaction_id = uniqid('order_', true);
$vault_id = uniqid();
$vaultOperation = "CREATE";

$firstName = $UserAccount->user_details['first_name'];
$midName = $UserAccount->user_details['mid_name'];
$lastName = $UserAccount->user_details['last_name'];
$email = $UserAccount->user_details['email'];

$order_number = $planName . " - " . $hours . " additional hours (" . ucfirst($billing) . ")";

// Store context so the response handler knows this is a buy_credits flow
$data = json_encode([
    'flow' => 'buy_credits_new_card',
    'transaction_id' => $transaction_id,
    'subscription_id' => $subscription_id,
    'plan_name' => $planName,
    'plan_id' => $plan_id,
    'description' => $description,
    'hours' => $hours,
    'rate' => $rate,
    'amount' => $amount,
    'user_id' => $UserAccount->user_details['id_user'],
    'billing' => $billing,
]);

$DB->sql(
    'INSERT INTO casepad_subscription_session (`transaction_key`,`data`) VALUES (?,?)',
    array('ss', $transaction_id, $data)
);

// Build XML request
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
            <VaultID>$vault_id</VaultID>
            <VaultStorage>
                <Service>$vaultOperation</Service>
            </VaultStorage>
            <TransactionBase>
                <TransactionID>$transaction_id</TransactionID>
                <TransactionType>$transaction_type</TransactionType>
                <Reference1>$order_number</Reference1>
                <Amount>" . number_format($amount, 2, '.', '') . "</Amount>
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
$basePath = dirname($_SERVER['PHP_SELF']);
$redirectUrl = $protocol . '://' . $host . $basePath . '/buy_credits_payment_response.php';
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
        <p><strong>Amount: $<?php echo number_format($amount, 2); ?></strong></p>
    </div>

    <form id="paymentForm" method="POST" action="https://www.sageexchange.com/sevd/frmPayment.aspx">
        <input type="hidden" name="request" value="<?php echo htmlspecialchars($tokenizedRequest, ENT_QUOTES); ?>" />
        <input type="hidden" name="redirect_url" value="<?php echo $redirectUrl; ?>" />
        <input type="hidden" name="consumer_initiated" value="true" />
    </form>
    <script>
        document.getElementById('paymentForm').submit();
    </script>
</body>
</html>


