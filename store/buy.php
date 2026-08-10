<?php
require_once __DIR__ . '/../config.php';
require_once DOCUMENT_ROOT . '/setup/start.php';
require_once DOCUMENT_ROOT . '/template/Master.php';
require_once DOCUMENT_ROOT . '/template/form.php';
require_once DOCUMENT_ROOT . '/lib/account.php';

if (is_null($UserAccount) || !is_a($UserAccount, 'useraccount') || !$UserAccount->logged_in) {
	header('Location: /logout.php');
	exit;
}

$winnerLicense = $UserAccount->myprocat_license();
if ($winnerLicense === false || $winnerLicense === 'Lite') {
	header('Location: /logout.php');
	exit;
}

$expectedLicenseType = $UserAccount->myprocat_subscription() ? 'subscription' : 'perpetual';

$licenseType = isset($_GET['type']) ? trim($_GET['type']) : '';
if (!in_array($licenseType, array('perpetual', 'subscription'), true) || $licenseType !== $expectedLicenseType) {
	header('Location: /store/buy.php?type=' . urlencode($expectedLicenseType));
	exit;
}

$licenseRows = array('id', 'per_hour_amount', 'minimum_hours', 'type');
$DB->sql(
	'SELECT id, per_hour_amount, minimum_hours, type FROM myprocat_subscription_license_table WHERE type = ? LIMIT 1',
	array('s', $licenseType),
	$licenseRows
);

if (!isset($licenseRows[0]['id']) || $licenseRows[0]['id'] === 'id') {
	header('Location: /store/?error=license_unavailable');
	exit;
}

$license = $licenseRows[0];
$rate = (float)$license['per_hour_amount'];
$minHours = (int)$license['minimum_hours'];
if ($minHours < 1) {
	$minHours = 1;
}

$autoPurchaseEnabled = false;
$existingThreshold = '';
$existingMinBalance = '';
$hasSavedCard = false;
$lastFourDigits = '';
$isSavedCardExpired = false;
$existingPurchaseRows = array(
	'auto_purchase_enabled',
	'balance_threshold',
	'min_account_balance',
	'guid',
	'last_four_digits',
	'card_expiry_date',
);
$DB->sql(
	'SELECT auto_purchase_enabled, balance_threshold, min_account_balance, guid, last_four_digits, card_expiry_date
	 FROM myprocat_purchases
	 WHERE id_owner = ? AND is_active = 1
	 ORDER BY id DESC
	 LIMIT 1',
	array('s', $UserAccount->user_details['id_user']),
	$existingPurchaseRows
);
if (!empty($existingPurchaseRows) && isset($existingPurchaseRows[0]['auto_purchase_enabled']) && $existingPurchaseRows[0]['auto_purchase_enabled'] !== 'auto_purchase_enabled') {
	$autoPurchaseEnabled = !empty($existingPurchaseRows[0]['auto_purchase_enabled']);
	if (isset($existingPurchaseRows[0]['balance_threshold']) && $existingPurchaseRows[0]['balance_threshold'] !== '' && $existingPurchaseRows[0]['balance_threshold'] !== 'balance_threshold') {
		$existingThreshold = rtrim(rtrim(number_format((float)$existingPurchaseRows[0]['balance_threshold'], 2, '.', ''), '0'), '.');
	}
	if (isset($existingPurchaseRows[0]['min_account_balance']) && $existingPurchaseRows[0]['min_account_balance'] !== '' && $existingPurchaseRows[0]['min_account_balance'] !== 'min_account_balance') {
		$existingMinBalance = rtrim(rtrim(number_format((float)$existingPurchaseRows[0]['min_account_balance'], 2, '.', ''), '0'), '.');
	}

	$storedGuid = isset($existingPurchaseRows[0]['guid']) ? trim((string)$existingPurchaseRows[0]['guid']) : '';
	if ($storedGuid !== '' && $storedGuid !== 'guid') {
		$hasSavedCard = true;
		$lastFourDigits = isset($existingPurchaseRows[0]['last_four_digits']) ? trim((string)$existingPurchaseRows[0]['last_four_digits']) : '';
		$cardExpiryDate = isset($existingPurchaseRows[0]['card_expiry_date']) ? trim((string)$existingPurchaseRows[0]['card_expiry_date']) : '';
		if ($cardExpiryDate !== '' && $cardExpiryDate !== 'card_expiry_date') {
			$expTs = strtotime($cardExpiryDate);
			if ($expTs !== false && $expTs < strtotime(date('Y-m-d'))) {
				$isSavedCardExpired = true;
			}
		}
	}
}

if (!$hasSavedCard) {
	$fallbackPurchaseRows = array(
		'auto_purchase_enabled',
		'balance_threshold',
		'min_account_balance',
	);
	$DB->sql(
		'SELECT auto_purchase_enabled, balance_threshold, min_account_balance
		 FROM myprocat_purchases
		 WHERE id_owner = ?
		 ORDER BY is_active DESC, id DESC
		 LIMIT 1',
		array('s', $UserAccount->user_details['id_user']),
		$fallbackPurchaseRows
	);
	if (!empty($fallbackPurchaseRows) && isset($fallbackPurchaseRows[0]['auto_purchase_enabled']) && $fallbackPurchaseRows[0]['auto_purchase_enabled'] !== 'auto_purchase_enabled') {
		$autoPurchaseEnabled = !empty($fallbackPurchaseRows[0]['auto_purchase_enabled']);
		if (isset($fallbackPurchaseRows[0]['balance_threshold']) && $fallbackPurchaseRows[0]['balance_threshold'] !== '' && $fallbackPurchaseRows[0]['balance_threshold'] !== 'balance_threshold') {
			$existingThreshold = rtrim(rtrim(number_format((float)$fallbackPurchaseRows[0]['balance_threshold'], 2, '.', ''), '0'), '.');
		}
		if (isset($fallbackPurchaseRows[0]['min_account_balance']) && $fallbackPurchaseRows[0]['min_account_balance'] !== '' && $fallbackPurchaseRows[0]['min_account_balance'] !== 'min_account_balance') {
			$existingMinBalance = rtrim(rtrim(number_format((float)$fallbackPurchaseRows[0]['min_account_balance'], 2, '.', ''), '0'), '.');
		}
	}
}

$licenseTitles = array(
	'perpetual' => 'Perpetual License',
	'subscription' => 'Subscription License',
);
$licenseTitle = $licenseTitles[$licenseType];
$billingDescriptor = ($licenseType === 'subscription') ? 'Subscription License' : 'One-Time License Purchase';

$set_title = 'Buy Platform Time - ' . $licenseTitle . ' - ProCAT Resource Center';
$sidebar_title = 'Buy Platform Time';

$page_banner = new content_block(NULL, 'div', array('class' => 'banner'));
$page_banner->push(new content_block('Buy Platform Time', 'h1'));

$set_body = new content_block(NULL, 'div', array('style' => 'width: 100%;'));

$buyContainer = new content_block(NULL, 'div', array('class' => 'buy-container', 'style' => 'max-width: 1100px; margin: 0 auto; padding: 0 20px 40px;'));

$planHeader = new content_block(NULL, 'div', array('class' => 'plan-header', 'style' => 'text-align: center; margin-bottom: 24px;'));
$planHeader->push(new content_block($licenseTitle, 'h1', array('class' => 'plan-name', 'style' => 'font-size: 28px; color: #27475f; margin: 0 0 8px 0;')));
$planHeader->push(new content_block($billingDescriptor, 'h2', array('class' => 'billing-description', 'style' => 'font-size: 16px; color: #666; font-weight: 500; margin: 0;')));
$buyContainer->push($planHeader);

$buyContainer->push(new content_block(NULL, 'div', array('class' => 'separator-line')));

$hoursSection = new content_block(NULL, 'div', array('class' => 'hours-section'));
$hoursSection->push(new paragraph('Enter the number of hours needed:', array('class' => 'hours-question')));

$rateHintBox = new content_block(NULL, 'div', array(
	'class' => 'rate-hint-box',
	'style' => 'background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 16px; margin-bottom: 20px;',
));
$rateHintBox->push(new content_block(
	'Minimum ' . $minHours . ' hours required',
	'p',
	array('style' => 'margin: 0 0 8px 0; font-size: 12px; color: #999; font-style: italic;')
));
$rateHintBox->push(new content_block(
	'Rate: <strong>$' . number_format($rate, 2) . '</strong> per hour',
	'p',
	array('style' => 'margin: 0; font-size: 13px; color: #666;')
));
$hoursSection->push($rateHintBox);

$hoursInputWrapper = new content_block(NULL, 'div', array('class' => 'hours-input-wrapper'));
$hoursInput = new content_block(NULL, 'input', array(
	'type' => 'number',
	'class' => 'hours-input',
	'placeholder' => 'Enter or use spinner to increase hours',
	'min' => (string)$minHours,
	'step' => '1',
	'value' => (string)$minHours,
	'id' => 'hoursInput',
	'onkeydown' => 'return hoursInputKeydown(event)',
	'onchange' => 'enforceMinHours(this); calculateTotal()',
	'oninput' => 'sanitizeHoursInput(this); calculateTotal()',
));
$hoursInputWrapper->push($hoursInput);

$spinner = new content_block(NULL, 'div', array('class' => 'hours-spinner'));
$spinner->push(new content_block('▲', 'button', array('type' => 'button', 'class' => 'spinner-btn', 'onclick' => 'incrementHours()')));
$spinner->push(new content_block('▼', 'button', array('type' => 'button', 'class' => 'spinner-btn', 'onclick' => 'decrementHours()')));
$hoursInputWrapper->push($spinner);
$hoursSection->push($hoursInputWrapper);

$pricingInfo = new content_block(NULL, 'div', array('class' => 'pricing-info'));
$pricingInfo->push(new content_block('$' . number_format($rate, 2) . ' / hour', 'span', array('class' => 'rate-display', 'id' => 'rateDisplay')));
$pricingInfo->push(new content_block('Total $0.00', 'span', array('class' => 'total-display', 'id' => 'totalDisplay')));
$hoursSection->push($pricingInfo);

$buyContainer->push($hoursSection);

$orderSummaryContainer = new content_block(NULL, 'div', array('class' => 'order-summary-container'));

$orderSummaryBox = new content_block(NULL, 'div', array('class' => 'order-summary-box'));

$orderTotalHeader = new content_block(NULL, 'div', array('class' => 'order-total-header'));
$orderTotalHeader->push(new content_block('$0.00', 'span', array('class' => 'order-total-amount', 'id' => 'orderTotalAmount')));
$orderSummaryBox->push($orderTotalHeader);

$orderDetails = new content_block(NULL, 'div', array('class' => 'order-details'));

$lineItem1 = new content_block(NULL, 'div', array('class' => 'order-line-item'));
$lineItem1Left = new content_block(NULL, 'div', array('class' => 'order-line-left'));
$lineItem1Left->push(new content_block($licenseTitle, 'span', array('class' => 'order-item-name')));
$lineItem1->push($lineItem1Left);
$lineItem1Right = new content_block(NULL, 'div', array('class' => 'order-line-right'));
$lineItem1Right->push(new content_block('$0.00', 'span', array('class' => 'order-item-price', 'id' => 'orderItemPrice')));
$lineItem1->push($lineItem1Right);
$orderDetails->push($lineItem1);

$lineItem2 = new content_block(NULL, 'div', array('class' => 'order-line-item'));
$lineItem2Left = new content_block(NULL, 'div', array('class' => 'order-line-left'));
$lineItem2Left->push(new content_block('Hours', 'span', array('class' => 'order-item-label')));
$lineItem2Left->push(new content_block('Qty <span id="orderHoursQty">' . $minHours . '</span>', 'span', array('class' => 'order-item-quantity')));
$lineItem2->push($lineItem2Left);
$lineItem2Right = new content_block(NULL, 'div', array('class' => 'order-line-right'));
$lineItem2Right->push(new content_block('$<span id="orderRate">' . number_format($rate, 2) . '</span> each', 'span', array('class' => 'order-item-unit-price')));
$lineItem2->push($lineItem2Right);
$orderDetails->push($lineItem2);

$lineItem3 = new content_block(NULL, 'div', array('class' => 'order-line-item'));
$lineItem3Left = new content_block(NULL, 'div', array('class' => 'order-line-left'));
$lineItem3Left->push(new content_block('Subtotal', 'span', array('class' => 'order-item-label')));
$lineItem3->push($lineItem3Left);
$lineItem3Right = new content_block(NULL, 'div', array('class' => 'order-line-right'));
$lineItem3Right->push(new content_block('$<span id="orderSubtotal">0.00</span>', 'span', array('class' => 'order-item-price')));
$lineItem3->push($lineItem3Right);
$orderDetails->push($lineItem3);

$lineItem4 = new content_block(NULL, 'div', array('class' => 'order-line-item total-due-item'));
$lineItem4Left = new content_block(NULL, 'div', array('class' => 'order-line-left'));
$lineItem4Left->push(new content_block('Total to be charged', 'span', array('class' => 'order-item-label total-due-label')));
$lineItem4->push($lineItem4Left);
$lineItem4Right = new content_block(NULL, 'div', array('class' => 'order-line-right'));
$lineItem4Right->push(new content_block('$<span id="orderTotalDue">0.00</span>', 'span', array('class' => 'order-total-amount')));
$lineItem4->push($lineItem4Right);
$orderDetails->push($lineItem4);

$orderSummaryBox->push($orderDetails);
$orderSummaryContainer->push($orderSummaryBox);
$orderSummaryContainer->push(new content_block(NULL, 'div', array('class' => 'separator-line')));

$autoPurchaseSection = new content_block(NULL, 'div', array(
	'class' => 'hours-section',
	'style' => 'margin: 16px 0;',
));
$autoPurchaseToggle = new content_block(NULL, 'div', array(
	'style' => 'display: flex; align-items: flex-start; gap: 10px; margin-bottom: 16px;',
));
$autoPurchaseCheckboxAttrs = array(
	'type' => 'checkbox',
	'id' => 'autoPurchaseEnabled',
	'onchange' => 'toggleAutoPurchaseFields()',
	'style' => 'margin-top: 4px; width: 18px; height: 18px; cursor: pointer;',
);
if ($autoPurchaseEnabled) {
	$autoPurchaseCheckboxAttrs['checked'] = 'checked';
}
$autoPurchaseToggle->push(new content_block(NULL, 'input', $autoPurchaseCheckboxAttrs));
$autoPurchaseToggle->push(new content_block(
	'Enable automatic purchase when my account balance is low',
	'label',
	array(
		'for' => 'autoPurchaseEnabled',
		'style' => 'font-size: 14px; color: #27475f; font-weight: 600; cursor: pointer; line-height: 1.4;',
	)
));
$autoPurchaseSection->push($autoPurchaseToggle);

$autoPurchaseFieldsStyle = $autoPurchaseEnabled ? '' : 'display: none;';
$autoPurchaseFields = new content_block(NULL, 'div', array(
	'id' => 'autoPurchaseFields',
	'style' => 'background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 16px; ' . $autoPurchaseFieldsStyle,
));
$autoPurchaseFields->push(new content_block(
	'When your balance drops below the threshold, we will automatically purchase enough hours to restore your minimum account balance.',
	'p',
	array('style' => 'margin: 0 0 16px 0; font-size: 13px; color: #666;')
));

$thresholdRow = new content_block(NULL, 'div', array('style' => 'margin-bottom: 14px;'));
$thresholdRow->push(new content_block('Balance threshold (hours)', 'label', array(
	'for' => 'balanceThresholdInput',
	'style' => 'display: block; font-size: 12px; color: #666; margin-bottom: 6px; font-weight: 600;',
)));
$thresholdRow->push(new content_block(NULL, 'input', array(
	'type' => 'number',
	'class' => 'hours-input',
	'id' => 'balanceThresholdInput',
	'min' => '0',
	'step' => '1',
	'value' => $existingThreshold !== '' ? $existingThreshold : '0',
	'placeholder' => 'Hours',
	'style' => 'max-width: 220px; margin: 0;',
	'oninput' => 'syncAutoPurchaseFields()',
)));
$autoPurchaseFields->push($thresholdRow);

$minBalanceRow = new content_block(NULL, 'div', array('style' => 'margin-bottom: 0;'));
$minBalanceRow->push(new content_block('Minimum account balance (hours)', 'label', array(
	'for' => 'minAccountBalanceInput',
	'style' => 'display: block; font-size: 12px; color: #666; margin-bottom: 6px; font-weight: 600;',
)));
$minBalanceRow->push(new content_block(NULL, 'input', array(
	'type' => 'number',
	'class' => 'hours-input',
	'id' => 'minAccountBalanceInput',
	'min' => '0',
	'step' => '1',
	'value' => $existingMinBalance !== '' ? $existingMinBalance : '0',
	'placeholder' => 'Hours',
	'style' => 'max-width: 220px; margin: 0;',
	'oninput' => 'syncAutoPurchaseFields()',
)));
$autoPurchaseFields->push($minBalanceRow);
$autoPurchaseSection->push($autoPurchaseFields);
$orderSummaryContainer->push($autoPurchaseSection);

$paymentForm = new content_block(NULL, 'form', array(
	'id' => 'vaultPaymentForm',
	'method' => 'POST',
	'action' => '/store/process_vault_payment.php',
	'style' => 'display: none;',
));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'amount', 'id' => 'paymentAmount', 'value' => '0.00')));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'license_type', 'value' => $licenseType)));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'license_id', 'value' => $license['id'])));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'license_title', 'value' => $licenseTitle)));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'hours', 'id' => 'paymentHours', 'value' => (string)$minHours)));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'rate', 'id' => 'paymentRate', 'value' => number_format($rate, 2, '.', ''))));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'auto_purchase_enabled', 'id' => 'paymentAutoPurchaseEnabled', 'value' => $autoPurchaseEnabled ? '1' : '0')));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'balance_threshold', 'id' => 'paymentBalanceThreshold', 'value' => $existingThreshold !== '' ? $existingThreshold : '0')));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'min_account_balance', 'id' => 'paymentMinAccountBalance', 'value' => $existingMinBalance !== '' ? $existingMinBalance : '0')));
$orderSummaryContainer->push($paymentForm);

$newCardForm = new content_block(NULL, 'form', array(
	'id' => 'newCardForm',
	'method' => 'POST',
	'action' => '/store/process_payment.php',
	'style' => 'display: none;',
));
$newCardForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'amount', 'id' => 'newCardAmount', 'value' => '0.00')));
$newCardForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'license_type', 'value' => $licenseType)));
$newCardForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'license_id', 'value' => $license['id'])));
$newCardForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'license_title', 'value' => $licenseTitle)));
$newCardForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'hours', 'id' => 'newCardHours', 'value' => (string)$minHours)));
$newCardForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'rate', 'id' => 'newCardRate', 'value' => number_format($rate, 2, '.', ''))));
$newCardForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'auto_purchase_enabled', 'id' => 'newCardAutoPurchaseEnabled', 'value' => $autoPurchaseEnabled ? '1' : '0')));
$newCardForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'balance_threshold', 'id' => 'newCardBalanceThreshold', 'value' => $existingThreshold !== '' ? $existingThreshold : '0')));
$newCardForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'min_account_balance', 'id' => 'newCardMinAccountBalance', 'value' => $existingMinBalance !== '' ? $existingMinBalance : '0')));
$orderSummaryContainer->push($newCardForm);

$confirmPayOnclick = $hasSavedCard ? 'showPaymentChoiceModal();' : 'useNewCardAndPay();';
$confirmPayButton = new content_block(NULL, 'button', array(
	'type' => 'button',
	'class' => 'next-button',
	'id' => 'confirmPayButton',
	'onclick' => $confirmPayOnclick,
));
$confirmPayButton->push(new content_block('Pay Now', 'span'));
$orderSummaryContainer->push($confirmPayButton);

$termsPrivacySection = new content_block(NULL, 'div', array('class' => 'terms-privacy-section'));
$termsPrivacySection->push(new paragraph('By paying, you agree to ProCAT\'s <a href="https://ProCAT.com/terms-of-service/" class="terms-link">Terms</a> and <a href="https://ProCAT.com/privacy-policy/" class="terms-link">Privacy</a>.', array('class' => 'terms-privacy-text')));
$orderSummaryContainer->push($termsPrivacySection);

$backLink = new content_block(NULL, 'div', array('style' => 'text-align: center; margin-top: 20px;'));
$backLink->push(new anchor('← Change License Type', array(
	'href' => '/subscription/myprocat/',
	'style' => 'color: #27475f; text-decoration: none; font-weight: 500;',
)));
$orderSummaryContainer->push($backLink);

$buyContainer->push($orderSummaryContainer);
$set_body->push($buyContainer);

if ($hasSavedCard) {
	$modalOverlay = new content_block(NULL, 'div', array(
		'id' => 'paymentChoiceModal',
		'style' => 'display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:9999; align-items:center; justify-content:center; padding:20px;',
	));
	$modalBox = new content_block(NULL, 'div', array(
		'style' => 'width:100%; max-width:520px; background:#ffffff; border-radius:10px; border:1px solid #e0e0e0; box-shadow:0 10px 30px rgba(0,0,0,0.2); overflow:hidden; font-family:"Montserrat", sans-serif;',
	));
	$modalHeader = new content_block(NULL, 'div', array('style' => 'background:#1a365d; color:#fff; padding:16px 18px; display:flex; align-items:center; justify-content:space-between;'));
	$modalHeader->push(new content_block('Choose Payment Method', 'div', array('style' => 'font-size:16px; font-weight:600;')));
	$modalHeader->push(new content_block('✕', 'button', array('type' => 'button', 'onclick' => 'hidePaymentChoiceModal();', 'style' => 'background:transparent; border:none; color:#fff; font-size:18px; cursor:pointer; line-height:1;')));
	$modalBox->push($modalHeader);
	$modalBody = new content_block(NULL, 'div', array('style' => 'padding:18px;'));
	$modalMsg = 'Do you want to use your existing card';
	if ($lastFourDigits !== '' && strtolower($lastFourDigits) !== 'last_four_digits') {
		$modalMsg .= ' ending in <strong>' . htmlspecialchars($lastFourDigits, ENT_QUOTES) . '</strong>';
	}
	$modalMsg .= ' or use a new card?';
	$modalBody->push(new content_block($modalMsg, 'div', array('style' => 'color:#333; font-size:14px; margin-bottom:14px; line-height:1.5;')));
	$modalButtons = new content_block(NULL, 'div', array('style' => 'display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end;'));
	$modalButtons->push(new content_block('Use Existing Card', 'button', array('type' => 'button', 'class' => 'primary_button', 'style' => 'margin:0;', 'onclick' => 'useExistingCardAndPay();')));
	$modalButtons->push(new content_block('Use New Card', 'button', array('type' => 'button', 'class' => 'secondary_button', 'style' => 'margin:0;', 'onclick' => 'useNewCardAndPay();')));
	$modalButtons->push(new content_block('Cancel', 'button', array('type' => 'button', 'class' => 'secondary_button', 'style' => 'margin:0;', 'onclick' => 'hidePaymentChoiceModal();')));
	$modalBody->push($modalButtons);
	$modalBox->push($modalBody);
	$modalOverlay->push($modalBox);
	$set_body->push($modalOverlay);
}

$script = new content_block("
	var rate = " . json_encode($rate) . ";
	var minHours = " . json_encode($minHours) . ";
	var hasSavedCard = " . ($hasSavedCard ? 'true' : 'false') . ";
	var isSavedCardExpired = " . ($isSavedCardExpired ? 'true' : 'false') . ";

	function formatCurrency(amount) {
		return amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}

	function hoursInputKeydown(e) {
		var allowedKeys = ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Home', 'End', 'Enter'];
		if (!e || allowedKeys.indexOf(e.key) !== -1) return true;
		if (e.ctrlKey || e.metaKey || e.altKey) return true;
		if (e.key === '-' || e.key === '+' || e.key === 'e' || e.key === 'E' || e.key === '.') {
			e.preventDefault();
			return false;
		}
		return true;
	}

	function sanitizeHoursInput(inputEl) {
		if (!inputEl) return;
		var raw = (inputEl.value || '').trim();
		if (raw === '') return;
		var hours = parseInt(raw, 10);
		if (isNaN(hours)) return;
		if (hours < 0) {
			inputEl.value = String(minHours);
		}
	}

	function enforceMinHours(inputEl) {
		if (!inputEl) return;
		var raw = (inputEl.value || '').trim();
		if (raw === '') return;
		var hours = parseInt(raw, 10);
		if (isNaN(hours)) return;
		if (hours < minHours) {
			inputEl.value = String(minHours);
		}
	}

	function incrementHours() {
		var input = document.getElementById('hoursInput');
		var currentValue = parseInt(input.value || 0, 10);
		if (isNaN(currentValue) || currentValue < minHours) currentValue = minHours;
		input.value = currentValue + 1;
		calculateTotal();
	}

	function decrementHours() {
		var input = document.getElementById('hoursInput');
		var currentValue = parseInt(input.value || 0, 10);
		if (isNaN(currentValue) || currentValue < minHours) currentValue = minHours;
		if (currentValue > minHours) {
			input.value = currentValue - 1;
			calculateTotal();
		}
	}

	function calculateTotal() {
		var input = document.getElementById('hoursInput');
		var hours = parseInt(input.value || 0, 10);

		if (isNaN(hours) || hours < minHours) {
			document.getElementById('rateDisplay').textContent = 'Minimum ' + minHours + ' hours required';
			document.getElementById('totalDisplay').textContent = 'Total \$0.00';
			document.getElementById('orderTotalAmount').textContent = '\$0.00';
			document.getElementById('orderItemPrice').textContent = '\$0.00';
			document.getElementById('orderHoursQty').textContent = '0';
			document.getElementById('orderSubtotal').textContent = '0.00';
			document.getElementById('orderTotalDue').textContent = '0.00';
			return;
		}

		var total = hours * rate;

		document.getElementById('rateDisplay').textContent = '\$' + formatCurrency(rate) + ' / hour';
		document.getElementById('totalDisplay').textContent = 'Total \$' + formatCurrency(total);
		document.getElementById('orderTotalAmount').textContent = '\$' + formatCurrency(total);
		document.getElementById('orderItemPrice').textContent = '\$' + formatCurrency(total);
		document.getElementById('orderHoursQty').textContent = hours;
		document.getElementById('orderSubtotal').textContent = formatCurrency(total);
		document.getElementById('orderTotalDue').textContent = formatCurrency(total);

		document.getElementById('paymentAmount').value = total.toFixed(2);
		document.getElementById('paymentHours').value = hours;
		document.getElementById('paymentRate').value = rate.toFixed(2);
		document.getElementById('newCardAmount').value = total.toFixed(2);
		document.getElementById('newCardHours').value = hours;
		document.getElementById('newCardRate').value = rate.toFixed(2);
	}

	function syncAutoPurchaseFields() {
		var enabled = document.getElementById('autoPurchaseEnabled').checked;
		var threshold = document.getElementById('balanceThresholdInput').value;
		var minBalance = document.getElementById('minAccountBalanceInput').value;
		var enabledValue = enabled ? '1' : '0';

		document.getElementById('paymentAutoPurchaseEnabled').value = enabledValue;
		document.getElementById('paymentBalanceThreshold').value = threshold;
		document.getElementById('paymentMinAccountBalance').value = minBalance;
		document.getElementById('newCardAutoPurchaseEnabled').value = enabledValue;
		document.getElementById('newCardBalanceThreshold').value = threshold;
		document.getElementById('newCardMinAccountBalance').value = minBalance;
	}

	function toggleAutoPurchaseFields() {
		var enabled = document.getElementById('autoPurchaseEnabled').checked;
		var fields = document.getElementById('autoPurchaseFields');
		if (fields) {
			fields.style.display = enabled ? 'block' : 'none';
		}
		syncAutoPurchaseFields();
	}

	function validateAutoPurchaseSettings() {
		var enabled = document.getElementById('autoPurchaseEnabled').checked;
		if (!enabled) {
			return true;
		}

		var threshold = parseFloat(document.getElementById('balanceThresholdInput').value);
		var minBalance = parseFloat(document.getElementById('minAccountBalanceInput').value);

		if (isNaN(threshold) || threshold < 0) {
			alert('Please enter a valid balance threshold in hours.');
			return false;
		}
		if (isNaN(minBalance) || minBalance < 0) {
			alert('Please enter a valid minimum account balance in hours.');
			return false;
		}
		if (minBalance < threshold) {
			alert('Minimum account balance must be at or above the balance threshold.');
			return false;
		}

		return true;
	}

	function proceedToPayment() {
		if (!preparePaymentSubmission()) {
			return false;
		}
		document.getElementById('vaultPaymentForm').submit();
	}

	function useNewCardAndPay() {
		if (!preparePaymentSubmission()) {
			return false;
		}
		document.getElementById('newCardForm').submit();
	}

	function preparePaymentSubmission() {
		var hours = parseInt(document.getElementById('hoursInput').value || 0, 10);
		if (isNaN(hours) || hours < minHours) {
			alert('Please enter at least ' + minHours + ' hours.');
			return false;
		}

		if (!validateAutoPurchaseSettings()) {
			return false;
		}

		calculateTotal();
		syncAutoPurchaseFields();

		var submitButton = document.getElementById('confirmPayButton');
		if (submitButton) {
			submitButton.disabled = true;
			submitButton.innerHTML = '<span>Processing...</span>';
		}

		return true;
	}

	function showPaymentChoiceModal() {
		var hours = parseInt(document.getElementById('hoursInput').value || 0, 10);
		if (isNaN(hours) || hours < minHours) {
			alert('Please enter at least ' + minHours + ' hours.');
			return false;
		}

		if (!validateAutoPurchaseSettings()) {
			return false;
		}

		if (isSavedCardExpired) {
			useNewCardAndPay();
			return false;
		}

		var modal = document.getElementById('paymentChoiceModal');
		if (modal) {
			modal.style.display = 'flex';
		}
	}

	function hidePaymentChoiceModal() {
		var modal = document.getElementById('paymentChoiceModal');
		if (modal) {
			modal.style.display = 'none';
		}
	}

	function useExistingCardAndPay() {
		hidePaymentChoiceModal();
		proceedToPayment();
	}

	document.addEventListener('DOMContentLoaded', function() {
		calculateTotal();
		syncAutoPurchaseFields();
	});
", 'script', array('type' => 'text/javascript'));
$set_body->push($script);

$breadcrumb_items = array(
	array('text' => 'Home', 'url' => '/resources.php'),
	array('text' => 'Store', 'url' => '/store/'),
	array('text' => 'Buy Platform Time', 'url' => '/store/buy.php?type=' . urlencode($licenseType)),
);

$sidebar_title = 'MyProCAT';
$sidebar_logo = '/store/img/buy.png';
$sidebar_logo_text = 'MyProCAT Buy Platform Time';
require_once DOCUMENT_ROOT . '/templateV2/mainframe/mainframe.php';
?>
