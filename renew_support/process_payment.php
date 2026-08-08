<?php
require_once __DIR__ . '/../config.php';
require_once DOCUMENT_ROOT . '/setup/start.php';
require_once DOCUMENT_ROOT . '/paymentSdk/shared.php';
require_once DOCUMENT_ROOT . '/store/helper.php';
require_once __DIR__ . '/helper.php';

if (is_null($UserAccount) || !is_a($UserAccount, 'useraccount') || !$UserAccount->logged_in) {
	header('Location: /signup/login.php');
	exit;
}

$id_user = $UserAccount->user_details['id_user'];
$sku_key = isset($_POST['sku_key']) ? trim($_POST['sku_key']) : '';
$tier = isset($_POST['tier']) ? trim($_POST['tier']) : '';

$user_products = renew_support_get_user_products($DB, $id_user);
$owned_keys = renew_support_owned_product_keys($user_products);
$eligible_skus = renew_support_get_eligible_skus($DB, $owned_keys, $id_user);
$sku = renew_support_find_eligible_sku($eligible_skus, $sku_key);

if ($sku === null || !isset($sku['tiers'][$tier])) {
	header('Location: /renew_support/?error=invalid_plan');
	exit;
}

$amount = (float)$sku['tiers'][$tier];
if ($amount <= 0) {
	header('Location: /renew_support/?error=invalid_amount');
	exit;
}

$addon_amount = 0.0;
if (!empty($sku['addon_plan_code'])) {
	$addonPlanCols = array('standard_price');
	$DB->sql(
		'SELECT standard_price FROM ' . RENEW_SUPPORT_TABLE_PLANS . ' WHERE plan_code = ? LIMIT 1',
		array('s', $sku['addon_plan_code']),
		$addonPlanCols
	);
	foreach ($addonPlanCols as $row) {
		if (is_array($row) && isset($row['standard_price']) && $row['standard_price'] !== 'standard_price') {
			$addon_amount = (float)$row['standard_price'];
			break;
		}
	}
}
$base_amount = max(0, $amount - $addon_amount);

$display_name = $sku['display_name'];
$tier_label = ucfirst($tier);
$order_number = build_order_number('Renew: ' . $display_name . ' - ' . $tier_label, substr(uniqid(), -6));

$merchant_id = $sharedCredentials['MID'];
$merchant_key = $sharedCredentials['MKEY'];
$transaction_type = $transactionTypes['UI']['Sale'];

$transaction_id = uniqid('renew_', true);
$vault_id = uniqid('vault_', true);
$vaultOperation = 'CREATE';

$firstName = isset($UserAccount->user_details['first_name']) ? $UserAccount->user_details['first_name'] : '';
$midName = isset($UserAccount->user_details['mid_name']) ? $UserAccount->user_details['mid_name'] : '';
$lastName = isset($UserAccount->user_details['last_name']) ? $UserAccount->user_details['last_name'] : '';
$email = isset($UserAccount->user_details['email']) ? $UserAccount->user_details['email'] : '';

$max_renewals = isset($sku['max_renewals']) ? $sku['max_renewals'] : null;

$sessionData = json_encode(array(
	'payment_source' => 'renew_support',
	'transaction_id' => $transaction_id,
	'vault_id' => $vault_id,
	'user_id' => $id_user,
	'sku_id' => (int)$sku['id'],
	'sku_key' => $sku_key,
	'tier' => $tier,
	'display_name' => $display_name,
	'base_plan_code' => $sku['base_plan_code'],
	'addon_plan_code' => !empty($sku['addon_plan_code']) ? $sku['addon_plan_code'] : null,
	'base_amount' => $base_amount,
	'addon_amount' => $addon_amount,
	'total_amount' => $amount,
	'max_renewals' => $max_renewals,
));

$DB->sql(
	'INSERT INTO casepad_subscription_session (`transaction_key`,`data`) VALUES (?,?)',
	array('ss', $transaction_id, $sessionData)
);

renew_support_save_selection($sku_key, $tier, $amount, $display_name);

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
                <Reference1>" . htmlspecialchars($order_number, ENT_QUOTES) . "</Reference1>
                <Amount>" . number_format($amount, 2, '.', '') . "</Amount>
            </TransactionBase>
            <Customer>
                <Name>
                    <FirstName>" . htmlspecialchars($firstName, ENT_QUOTES) . "</FirstName>
                    <MI>" . htmlspecialchars($midName, ENT_QUOTES) . "</MI>
                    <LastName>" . htmlspecialchars($lastName, ENT_QUOTES) . "</LastName>
                </Name>
                <Address>
                    <AddressLine1></AddressLine1>
                    <AddressLine2></AddressLine2>
                    <City></City>
                    <State></State>
                    <ZipCode></ZipCode>
                    <Country></Country>
                    <EmailAddress>" . htmlspecialchars($email, ENT_QUOTES) . "</EmailAddress>
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
$basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/');
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
            border-top: 4px solid #ff6600;
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
        <p>Please wait while we process your renewal payment.</p>
        <p><strong><?php echo htmlspecialchars($display_name . ' — ' . $tier_label, ENT_QUOTES, 'UTF-8'); ?></strong></p>
        <p><strong>Amount: $<?php echo number_format($amount, 2); ?></strong></p>
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
