<?php
// Start a UI payment (new card) for buying additional hours, and create a new Vault GUID.
require_once('config.php');
require_once DOCUMENT_ROOT . '/setup/start.php';
require_once DOCUMENT_ROOT . '/paymentSdk/shared.php';
require_once DOCUMENT_ROOT . '/subscription/helper.php';

if (!isset($UserAccount) || !$UserAccount->logged_in) {
    header('Location: /login.php');
    exit;
}

// Verify subscription belongs to user and is active
$sub = array('id', 'id_owner', 'is_active','guid','vault_id');
$DB->sql(
    'SELECT id, id_owner, is_active,guid,vault_id FROM casepad_subscribed_plan WHERE id_owner = ?',
    array('s', $UserAccount->user_details['id_user']),
    $sub
);
if (!isset($sub[0]) || (int)$sub[0]['is_active'] !== 1) {
    header('Location: /subscription/payment.php?error=no_subscription');
    exit;
}

$currentSubscription = $sub[0];
$guid = decryptData($currentSubscription['guid']);
$vault_id = decryptData($currentSubscription['vault_id']);

$merchant_id = $sharedCredentials["MID"];
$merchant_key = $sharedCredentials["MKEY"];
// Store context so the response handler knows this is a update_card flow
$transaction_id = $currentSubscription['guid'];
$data = json_encode([
    'flow' => 'update_card',
    'subscribed_plan_id' => $currentSubscription['id'],
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
    <VaultOperation> 
        <Merchant> 
        <MerchantID>$merchant_id</MerchantID> 
        <MerchantKey>$merchant_key</MerchantKey> 
        </Merchant> 
        <VaultStorage> 
            <Service>UPDATE</Service> 
            <GUID>$guid</GUID> 
        </VaultStorage> 
        <VaultID>$vault_id</VaultID> 
    </VaultOperation> 
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
$redirectUrl = $protocol . '://' . $host . $basePath . '/update_card_payment_response.php';
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


