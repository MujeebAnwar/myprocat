<?php
require_once __DIR__ . '/../config.php';
require_once DOCUMENT_ROOT . '/setup/start.php';
require_once DOCUMENT_ROOT . '/paymentSdk/shared.php';
require_once DOCUMENT_ROOT . '/store/helper.php';
require_once __DIR__ . '/payment_complete.php';

if (is_null($UserAccount) || !is_a($UserAccount, 'useraccount') || !$UserAccount->logged_in) {
	header('Location: /logout.php');
	exit;
}



$checkout = myprocat_validate_checkout_post($DB, $UserAccount);
$amount = $checkout['amount'];
$licenseType = $checkout['license_type'];
$licenseId = $checkout['license_id'];
$licenseTitle = $checkout['license_title'];
$hours = $checkout['hours'];
$dbRate = $checkout['rate'];
$autoPurchaseEnabled = !empty($checkout['auto_purchase_enabled']);
$balanceThreshold = $checkout['balance_threshold'];
$minAccountBalance = $checkout['min_account_balance'];

$merchant_id = $sharedCredentials['MID'];
$merchant_key = $sharedCredentials['MKEY'];
$transaction_type = $transactionTypes['UI']['Sale'];

$order_number = build_license_order_number($licenseTitle, $hours);
$transaction_id = uniqid('myprocat_', true);
$vault_id = uniqid('vault_', true);
$vaultOperation = 'CREATE';
$firstName = $UserAccount->user_details['first_name'];
$midName = $UserAccount->user_details['mid_name'];
$lastName = $UserAccount->user_details['last_name'];
$email = $UserAccount->user_details['email'];

$data = json_encode(array(
	'payment_source' => 'myprocat',
	'transaction_id' => $transaction_id,
	'vault_id' => $vault_id,
	'license_type' => $licenseType,
	'license_id' => $licenseId,
	'license_title' => $licenseTitle,
	'hours' => $hours,
	'rate' => $dbRate,
	'user_id' => $UserAccount->user_details['id_user'],
	'auto_purchase_enabled' => $autoPurchaseEnabled ? 1 : 0,
	'balance_threshold' => $autoPurchaseEnabled ? $balanceThreshold : null,
	'min_account_balance' => $autoPurchaseEnabled ? $minAccountBalance : null,
));

$DB->sql(
	'INSERT INTO casepad_subscription_session (`transaction_key`,`data`) VALUES (?,?)',
	array('ss', $transaction_id, $data)
);

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
$redirectUrl = $protocol . '://' . $host . $basePath . '/payment_response.php';
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
