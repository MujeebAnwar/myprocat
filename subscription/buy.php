<?php
// Include required files first
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once ('plans_config.php');

// Get plan details from URL parameters
$plan = isset($_GET['plan']) ? $_GET['plan'] : 'classic';
$billing = isset($_GET['billing']) ? $_GET['billing'] : 'monthly';


// Validate plan exists in config
if (!isset($plans_config['plans'][$plan])) {
    $plan = 'classic';
}


// Current user subscription
$currentSubscription = array('id', 'guid', 'plan_id', 'rate', 'hours', 'commitment', 'prepaid', 'plan_name', 'plan_key', 'last_four_digits', 'card_expiry_date');
$DB->sql(
    'SELECT casepad_subscribed_plan.id,
            casepad_subscribed_plan.guid,
            casepad_subscribed_plan.plan_id,
            casepad_subscribed_plan.rate,
            casepad_subscribed_plan.hours,
            casepad_subscribed_plan.commitment,
            casepad_subscribed_plan.prepaid,
            subscription_plans.name as plan_name,
            subscription_plans.plan_id as plan_key,
            casepad_subscribed_plan.last_four_digits,
            casepad_subscribed_plan.card_expiry_date
     FROM casepad_subscribed_plan 
     LEFT JOIN subscription_plans ON subscription_plans.id = casepad_subscribed_plan.plan_id
     WHERE casepad_subscribed_plan.id_owner = ? AND casepad_subscribed_plan.is_active = 1
     ORDER BY casepad_subscribed_plan.id DESC LIMIT 1',
    array('s', $UserAccount->user_details['id_user']),
    $currentSubscription
);

// If user is upgrading and their saved card is expired, skip the "existing vs new" modal and force new card payment.
$subscriptionCardExpiryDate = isset($currentSubscription[0]['card_expiry_date']) ? trim((string)$currentSubscription[0]['card_expiry_date']) : '';
$isSavedCardExpired = false;
if ($subscriptionCardExpiryDate !== '' && strtolower($subscriptionCardExpiryDate) !== 'card_expiry_date') {
    $expTs = strtotime($subscriptionCardExpiryDate);
    if ($expTs !== false) {
        $isSavedCardExpired = $expTs < strtotime(date('Y-m-d'));
    }
}

// Get plan data from config
$planData = $plans_config['plans'][$plan];
$planName = $planData['name'];
$planId = $planData['id'];
$description = trim(preg_replace('/\s+/', ' ', $planData['ideal_for'])); // Clean up newlines and extra spaces

// Check if plan has pricing tiers
$hasPricingTiers = isset($planData['pricing']['tiers']) && isset($planData['pricing']['minimum_hours']);

// Set page title
$set_title = "Buy Hours - " . $planName . " Plan - DepoDash Resource Center";

// Create the main content body
$set_body = new content_block(NULL, 'div', array('style' => 'width: 100%; height: 100%;'));

// Banner section
$banner = new content_block(NULL, 'div', array('class' => 'banner'));
$banner->push(new content_block('Buy Hours', 'h1', array('style' => 'text-align: center; margin:5px 0px;')));
$set_body->push($banner);

// Buy container
$buyContainer = new content_block(NULL, 'div', array('class' => 'buy-container'));

// Plan header
$planHeader = new content_block(NULL, 'div', array('class' => 'plan-header'));
$planHeader->push(new content_block($planName, 'h1', array('class' => 'plan-name')));
$planHeader->push(new paragraph($description . ' - ' . ucfirst($billing), array('class' => 'plan-description')));
$buyContainer->push($planHeader);

// Separator line
$buyContainer->push(new content_block(NULL, 'div', array('class' => 'separator-line')));

// Hours section
$hoursSection = new content_block(NULL, 'div', array('class' => 'hours-section'));
$hoursSection->push(new paragraph('How many hours do you need?', array('class' => 'hours-question')));

// Rate distribution hint
$rateHintBox = new content_block(NULL, 'div', array('class' => 'rate-hint-box', 'style' => 'background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 16px; margin-bottom: 20px;'));
$rateHintTitle = new content_block('Pricing Tiers', 'h4', array('style' => 'margin: 0 0 12px 0; font-size: 14px; font-weight: 600; color: #333;'));
$rateHintBox->push($rateHintTitle);

// Create rate distribution list based on plan and billing type - dynamically generated from config
$rateList = new content_block(NULL, 'ul', array('class' => 'rate-distribution-list', 'id' => 'rateDistributionList', 'style' => 'margin: 0; padding-left: 20px; font-size: 13px; color: #666;'));

// Generate pricing tiers from config
if ($hasPricingTiers) {
    $minHours = $planData['pricing']['minimum_hours'];
    $rateHintBox->push(new content_block('Minimum ' . $minHours . ' hours required', 'p', array('style' => 'margin: 0 0 8px 0; font-size: 12px; color: #999; font-style: italic;')));
    
    $tiers = $planData['pricing']['tiers'][$billing];
    foreach ($tiers as $index => $tier) {
        $maxDisplay = ($tier['max'] >= 999999) ? '+' : '-' . $tier['max'];
        $tierText = $tier['min'] . $maxDisplay . ' hours: <strong>$' . number_format($tier['rate'], 2) . '</strong> per hour';
        $marginStyle = ($index === count($tiers) - 1) ? 'margin-bottom: 0;' : 'margin-bottom: 8px;';
        $rateList->push(new content_block($tierText, 'li', array('style' => $marginStyle)));
    }
} else {
    // For plans without pricing tiers (e.g., Enterprise)
    $rateHintBox->push(new content_block('Contact sales for pricing', 'p', array('style' => 'margin: 0 0 8px 0; font-size: 12px; color: #999; font-style: italic;')));
}

$rateHintBox->push($rateList);

// Add savings note
if ($billing === 'annual') {
    // $savingsNote = new content_block('💡 Annual billing saves you up to 25%!', 'p', array('style' => 'margin: 12px 0 0 0; padding-top: 12px; border-top: 1px solid #e0e0e0; font-size: 12px; color: #28a745; font-weight: 500;'));
    // $rateHintBox->push($savingsNote);
}

$hoursSection->push($rateHintBox);

// Annual payment option toggle (only for annual billing) - sleek design
if ($billing === 'annual') {
    // Separator line above toggle
    $hoursSection->push(new content_block(NULL, 'div', array('style' => 'height: 1px; background: #e0e0e0; margin: 24px 0 16px 0;')));
    
    // Sleek toggle wrapper - no card styling
    $annualPaymentToggle = new content_block(NULL, 'div', array('class' => 'annual-payment-toggle', 'style' => 'margin-bottom: 20px;'));
    
    // Toggle switch row
    $toggleWrapper = new content_block(NULL, 'div', array('style' => 'display: flex; align-items: center; justify-content: space-between; gap: 16px;'));
    
    // Left side: Label and description
    $toggleLabel = new content_block(NULL, 'div', array('style' => 'flex: 1;'));
    $toggleLabel->push(new content_block('Pay in Full (one-time)', 'div', array('style' => 'font-weight: 500; color: #333; font-size: 14px; margin-bottom: 4px;')));
    $toggleLabel->push(new content_block('✓ ' . $plans_config['annual_payment_options']['one_time']['discount_label'], 'div', array('id' => 'paymentDiscountLabel', 'style' => 'font-size: 12px; color: #28a745;')));
    $toggleWrapper->push($toggleLabel);
    
    // Right side: Toggle switch
    $toggleSwitch = new content_block(NULL, 'label', array('class' => 'toggle-switch', 'style' => 'position: relative; display: inline-block; width: 50px; height: 26px;'));
    $toggleSwitch->push(new content_block(NULL, 'input', array('type' => 'checkbox', 'id' => 'annualPaymentToggle', 'checked' => 'checked', 'onchange' => 'updateAnnualPaymentType()', 'style' => 'opacity: 0; width: 0; height: 0;')));
    $toggleSwitch->push(new content_block(NULL, 'span', array('class' => 'toggle-slider')));
    $toggleWrapper->push($toggleSwitch);
    
    $annualPaymentToggle->push($toggleWrapper);
    
    // Subtle description below toggle
    $toggleDescription = new content_block('When off: Amount will be divided into 12 monthly payments', 'div', array('id' => 'toggleDescription', 'style' => 'font-size: 11px; color: #999; margin-top: 8px; padding-left: 0;'));
    $annualPaymentToggle->push($toggleDescription);
    
    $hoursSection->push($annualPaymentToggle);
    
    // Separator line below toggle
    $hoursSection->push(new content_block(NULL, 'div', array('style' => 'height: 1px; background: #e0e0e0; margin: 16px 0 24px 0;')));
}

// Hours input wrapper
$hoursInputWrapper = new content_block(NULL, 'div', array('class' => 'hours-input-wrapper'));

// Hours input field
$hoursInput = new content_block(NULL, 'input', array(
    'type' => 'number',
    'class' => 'hours-input',
    'placeholder' => 'Enter or use spinner to increase hours',
    'min' => '0',
    'step' => '1',
    'value' => '0',
    'id' => 'hoursInput',
    'onchange' => 'calculateTotal()',
    'oninput' => 'calculateTotal()'
));
$hoursInputWrapper->push($hoursInput);

// Spinner buttons
$spinner = new content_block(NULL, 'div', array('class' => 'hours-spinner'));
$spinner->push(new content_block('▲', 'button', array('type' => 'button', 'class' => 'spinner-btn', 'onclick' => 'incrementHours()')));
$spinner->push(new content_block('▼', 'button', array('type' => 'button', 'class' => 'spinner-btn', 'onclick' => 'decrementHours()')));
$hoursInputWrapper->push($spinner);

$hoursSection->push($hoursInputWrapper);

// Pricing info
$pricingInfo = new content_block(NULL, 'div', array('class' => 'pricing-info'));
$pricingInfo->push(new content_block('Rate varies by hours', 'span', array('class' => 'rate-display', 'id' => 'rateDisplay','style' => 'display: none;')));
$pricingInfo->push(new content_block('Total $0.00', 'span', array('class' => 'total-display', 'id' => 'totalDisplay','style' => 'display: none;')));
$hoursSection->push($pricingInfo);

$buyContainer->push($hoursSection);

// Order summary container - integrated from order-summary.php
$orderSummaryContainer = new content_block(NULL, 'div', array('class' => 'order-summary-container'));

// Order summary box
$orderSummaryBox = new content_block(NULL, 'div', array('class' => 'order-summary-box'));

// Order total header
$orderTotalHeader = new content_block(NULL, 'div', array('class' => 'order-total-header'));
$orderTotalHeader->push(new content_block('$0.00', 'span', array('class' => 'order-total-amount', 'id' => 'orderTotalAmount')));
$orderSummaryBox->push($orderTotalHeader);

// Order details
$orderDetails = new content_block(NULL, 'div', array('class' => 'order-details'));

// Line item 1: Plan Name
$lineItem1 = new content_block(NULL, 'div', array('class' => 'order-line-item'));
$lineItem1Left = new content_block(NULL, 'div', array('class' => 'order-line-left'));
$lineItem1Left->push(new content_block($planName . ' Plan (<span id="orderBillingType">' . ucfirst($billing) . '</span>)', 'span', array('class' => 'order-item-name')));
$lineItem1->push($lineItem1Left);
$lineItem1Right = new content_block(NULL, 'div', array('class' => 'order-line-right'));
$lineItem1Right->push(new content_block('$0.00', 'span', array('class' => 'order-item-price', 'id' => 'orderItemPrice')));
$lineItem1->push($lineItem1Right);
$orderDetails->push($lineItem1);

// Line item 2: Hours
$lineItem2 = new content_block(NULL, 'div', array('class' => 'order-line-item'));
$lineItem2Left = new content_block(NULL, 'div', array('class' => 'order-line-left'));
$lineItem2Left->push(new content_block('Hours', 'span', array('class' => 'order-item-label')));
$lineItem2Left->push(new content_block('Qty <span id="orderHoursQty">0</span>', 'span', array('class' => 'order-item-quantity')));
$lineItem2->push($lineItem2Left);
$lineItem2Right = new content_block(NULL, 'div', array('class' => 'order-line-right'));
$lineItem2Right->push(new content_block('$<span id="orderRate">0.00</span> each', 'span', array('class' => 'order-item-unit-price')));
$lineItem2->push($lineItem2Right);
$orderDetails->push($lineItem2);

// Line item 3: Subtotal
$lineItem3 = new content_block(NULL, 'div', array('class' => 'order-line-item'));
$lineItem3Left = new content_block(NULL, 'div', array('class' => 'order-line-left'));
$lineItem3Left->push(new content_block('Subtotal', 'span', array('class' => 'order-item-label')));
$lineItem3->push($lineItem3Left);
$lineItem3Right = new content_block(NULL, 'div', array('class' => 'order-line-right'));
$lineItem3Right->push(new content_block('$<span id="orderSubtotal">0.00</span>', 'span', array('class' => 'order-item-price')));
$lineItem3->push($lineItem3Right);
$orderDetails->push($lineItem3);

// Line item 4: Discount (for annual one-time payment)
$lineItem4 = new content_block(NULL, 'div', array('class' => 'order-line-item', 'id' => 'discountLineItem', 'style' => 'display: none;'));
$lineItem4Left = new content_block(NULL, 'div', array('class' => 'order-line-left'));
$lineItem4Left->push(new content_block('Discount (<span id="discountPercent">1</span>% one-time payment)', 'span', array('class' => 'order-item-label', 'style' => 'color: #28a745;')));
$lineItem4->push($lineItem4Left);
$lineItem4Right = new content_block(NULL, 'div', array('class' => 'order-line-right'));
$lineItem4Right->push(new content_block('-$<span id="orderDiscount">0.00</span>', 'span', array('class' => 'order-item-price', 'style' => 'color: #28a745;')));
$lineItem4->push($lineItem4Right);
$orderDetails->push($lineItem4);

// Line item 5: Monthly Payment Info (for annual monthly installments)
$lineItem5 = new content_block(NULL, 'div', array('class' => 'order-line-item', 'id' => 'monthlyPaymentLineItem', 'style' => 'display: none;'));
$lineItem5Left = new content_block(NULL, 'div', array('class' => 'order-line-left'));
$lineItem5Left->push(new content_block('Monthly Payment (x12)', 'span', array('class' => 'order-item-label', 'style' => 'color: #1a365d; font-weight: 500;')));
$lineItem5->push($lineItem5Left);
$lineItem5Right = new content_block(NULL, 'div', array('class' => 'order-line-right'));
$lineItem5Right->push(new content_block('$<span id="orderMonthlyPayment">0.00</span>/mo', 'span', array('class' => 'order-item-price', 'style' => 'color: #1a365d; font-weight: 600;')));
$lineItem5->push($lineItem5Right);
$orderDetails->push($lineItem5);

// Line item 6: Total due
$lineItem6 = new content_block(NULL, 'div', array('class' => 'order-line-item total-due-item'));
$lineItem6Left = new content_block(NULL, 'div', array('class' => 'order-line-left'));
$lineItem6Left->push(new content_block('<span id="totalDueLabel">Total due</span>', 'span', array('class' => 'order-item-label total-due-label')));
$lineItem6->push($lineItem6Left);
$lineItem6Right = new content_block(NULL, 'div', array('class' => 'order-line-right'));
$lineItem6Right->push(new content_block('$<span id="orderTotalDue">0.00</span>', 'span', array('class' => 'order-total-amount')));
$lineItem6->push($lineItem6Right);
$orderDetails->push($lineItem6);

$orderSummaryBox->push($orderDetails);
$orderSummaryContainer->push($orderSummaryBox);

// Separator line
$orderSummaryContainer->push(new content_block(NULL, 'div', array('class' => 'separator-line')));

// Payment form (hidden, will be submitted via JavaScript)
$hasActiveSubscription = isset($currentSubscription[0]['id']) && $currentSubscription[0]['id'] && $currentSubscription[0]['id'] !== 'id';
$lastFourDigits = isset($currentSubscription[0]['last_four_digits']) ? trim((string)$currentSubscription[0]['last_four_digits']) : '';
$action = $hasActiveSubscription ? '/subscription/process_upgrade_payment.php' : '/subscription/process_payment.php';
$paymentForm = new content_block(NULL, 'form', array(
    'id' => 'paymentForm',
    'method' => 'POST',
    'action' => $action,
    'style' => 'display: none;'
));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'subscribed_plan_id', 'value' => isset($currentSubscription[0]['id']) ? $currentSubscription[0]['id'] : '')));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'amount', 'id' => 'paymentAmount', 'value' => '0.00')));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'plan', 'value' => $plan)));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'plan_id', 'value' => $planId)));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'planName', 'value' => $planName)));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'description', 'value' => $description)));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'billing', 'value' => $billing)));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'hours', 'id' => 'paymentHours', 'value' => '0')));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'rate', 'id' => 'paymentRate', 'value' => '0.00')));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'annualPaymentType', 'id' => 'paymentAnnualType', 'value' => 'one_time')));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'monthlyPaymentAmount', 'id' => 'paymentMonthlyAmount', 'value' => '0.00')));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'totalAnnualAmount', 'id' => 'paymentTotalAnnual', 'value' => '0.00')));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'installments', 'id' => 'paymentInstallments', 'value' => '1')));
$orderSummaryContainer->push($paymentForm);

// New card payment form (hidden) - only for users with an active subscription
if ($hasActiveSubscription) {
    $newCardForm = new content_block(NULL, 'form', array(
        'id' => 'newCardForm',
        'method' => 'POST',
        'action' => '/subscription/process_upgrade_payment_new_card.php',
        'style' => 'display: none;'
    ));
    $newCardForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'subscribed_plan_id', 'value' => isset($currentSubscription[0]['id']) ? $currentSubscription[0]['id'] : '')));
    $newCardForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'amount', 'id' => 'newCardAmount', 'value' => '0.00')));
    $newCardForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'plan', 'value' => $plan)));
    $newCardForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'plan_id', 'value' => $planId)));
    $newCardForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'planName', 'value' => $planName)));
    $newCardForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'description', 'value' => $description)));
    $newCardForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'billing', 'value' => $billing)));
    $newCardForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'hours', 'id' => 'newCardHours', 'value' => '0')));
    $newCardForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'rate', 'id' => 'newCardRate', 'value' => '0.00')));
    $newCardForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'annualPaymentType', 'id' => 'newCardAnnualType', 'value' => 'one_time')));
    $newCardForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'monthlyPaymentAmount', 'id' => 'newCardMonthlyAmount', 'value' => '0.00')));
    $newCardForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'totalAnnualAmount', 'id' => 'newCardTotalAnnual', 'value' => '0.00')));
    $newCardForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'installments', 'id' => 'newCardInstallments', 'value' => '1')));
    $orderSummaryContainer->push($newCardForm);

    // Payment Choice Modal (same UI style as buy_credits.php)
    $modalOverlay = new content_block(NULL, 'div', array(
        'id' => 'paymentChoiceModal',
        'style' => 'display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:9999; align-items:center; justify-content:center; padding:20px;'
    ));
    $modalBox = new content_block(NULL, 'div', array(
        'style' => 'width:100%; max-width:520px; background:#ffffff; border-radius:10px; border:1px solid #e0e0e0; box-shadow:0 10px 30px rgba(0,0,0,0.2); overflow:hidden; font-family:"Montserrat", sans-serif;'
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

// Confirm & Pay Button
$confirmPayButton = new content_block(NULL, 'button', array('type' => 'button', 'class' => 'next-button', 'id' => 'confirmPayButton', 'onclick' => ($hasActiveSubscription ? 'showPaymentChoiceModal();' : 'proceedToPayment();')));
$confirmPayButton->push(new content_block('Pay Now', 'span'));
$orderSummaryContainer->push($confirmPayButton);

// Terms and Privacy Section
$termsPrivacySection = new content_block(NULL, 'div', array('class' => 'terms-privacy-section'));
$termsPrivacySection->push(new paragraph('By paying, you agree to Link\'s <a href="#" class="terms-link">Terms</a> and <a href="#" class="terms-link">Privacy</a>.', array('class' => 'terms-privacy-text')));
$termsLinks = new content_block(NULL, 'div', array('class' => 'terms-links'));
$termsLinks->push(new anchor('Terms', array('href' => '#', 'class' => 'terms-link')));
$termsLinks->push(new anchor('Privacy', array('href' => '#', 'class' => 'terms-link')));
$termsPrivacySection->push($termsLinks);
$orderSummaryContainer->push($termsPrivacySection);

$buyContainer->push($orderSummaryContainer);
$set_body->push($buyContainer);

// Add CSS for smooth transitions and enhanced styling
$rateHintStyles = new content_block("
    .rate-distribution-list li {
        transition: all 0.3s ease;
        margin-left: -8px;
    }
    .rate-distribution-list li strong {
        color: inherit;
    }
    
    /* Toggle Switch Styles - Modern & Sleek */
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 26px;
        flex-shrink: 0;
    }
    
    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    
    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #cbd5e0;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border-radius: 26px;
    }
    
    .toggle-slider:before {
        position: absolute;
        content: '';
        height: 20px;
        width: 20px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border-radius: 50%;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .toggle-switch input:checked + .toggle-slider {
        background-color: #28a745;
    }
    
    .toggle-switch input:checked + .toggle-slider:before {
        transform: translateX(24px);
    }
    
    .toggle-switch:hover .toggle-slider {
        box-shadow: 0 0 0 4px rgba(40, 167, 69, 0.1);
    }
    
    .toggle-switch input:focus + .toggle-slider {
        box-shadow: 0 0 0 4px rgba(40, 167, 69, 0.2);
    }
    
    /* Annual Payment Toggle Container */
    .annual-payment-toggle {
        animation: slideIn 0.3s ease-out;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
", 'style');
$set_body->push($rateHintStyles);

// Add JavaScript for tiered pricing calculation - dynamically generated from config
$pricingTiersJs = '';
$minHoursJs = 0;

if ($hasPricingTiers) {
    $minHoursJs = $planData['pricing']['minimum_hours'];
    $pricingTiersJs = "var planPricing = {\n";
    foreach ($planData['pricing']['tiers'] as $billingTypeKey => $tiers) {
        $pricingTiersJs .= "    " . $billingTypeKey . ": [\n";
        foreach ($tiers as $tier) {
            $maxValue = ($tier['max'] >= 999999) ? 'Infinity' : $tier['max'];
            $pricingTiersJs .= "        { min: " . $tier['min'] . ", max: " . $maxValue . ", rate: " . $tier['rate'] . " },\n";
        }
        $pricingTiersJs = rtrim($pricingTiersJs, ",\n") . "\n";
        $pricingTiersJs .= "    ],\n";
    }
    $pricingTiersJs = rtrim($pricingTiersJs, ",\n") . "\n";
    $pricingTiersJs .= "};\n";
    $pricingTiersJs .= "var minHours = " . $minHoursJs . ";\n";
} else {
    $pricingTiersJs = "var planPricing = null;\n";
    $pricingTiersJs .= "var minHours = 0;\n";
}

// Add annual payment config to JavaScript
$annualPaymentConfigJs = "var annualPaymentConfig = " . json_encode($plans_config['annual_payment_options']) . ";\n";

$script = new content_block($pricingTiersJs . $annualPaymentConfigJs . "
    var planType = '" . addslashes($plan) . "';
    var billingType = '" . addslashes($billing) . "';
    var planName = '" . addslashes($planName) . "';
    var planDescription = '" . addslashes($description) . "';
    var currentAnnualPaymentType = 'one_time';
    var hasActiveSubscription = " . ($hasActiveSubscription ? 'true' : 'false') . ";
    var isSavedCardExpired = " . ($isSavedCardExpired ? 'true' : 'false') . ";
    
    function getRate(hours) {
        if (!planPricing || !planPricing[billingType]) {
            return 0;
        }
        
        var pricing = planPricing[billingType];
        
        if (hours < minHours) {
            return 0; // Not enough hours for this plan
        }
        
        for (var i = 0; i < pricing.length; i++) {
            if (hours >= pricing[i].min && hours <= pricing[i].max) {
                return pricing[i].rate;
            }
        }
        
        return 0;
    }

    function formatCurrency(amount) {
        return amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    
    function incrementHours() {
        var input = document.getElementById('hoursInput');
        input.value = parseInt(input.value || 0) + 1;
        calculateTotal();
    }
    
    function decrementHours() {
        var input = document.getElementById('hoursInput');
        var currentValue = parseInt(input.value || 0);
        if (currentValue > 0) {
            input.value = currentValue - 1;
            calculateTotal();
        }
    }
    
    function updateAnnualPaymentType() {
        var toggle = document.getElementById('annualPaymentToggle');
        if (toggle) {
            currentAnnualPaymentType = toggle.checked ? 'one_time' : 'monthly';
            
            // Update label text with smooth transition
            var discountLabel = document.getElementById('paymentDiscountLabel');
            if (discountLabel) {
                // Fade out
                discountLabel.style.transition = 'opacity 0.2s ease, color 0.2s ease';
                discountLabel.style.opacity = '0';
                
                setTimeout(function() {
                    if (toggle.checked) {
                        discountLabel.textContent = '✓ ' + annualPaymentConfig.one_time.discount_label;
                        discountLabel.style.color = '#28a745';
                    } else {
                        discountLabel.textContent = annualPaymentConfig.monthly.discount_label;
                        discountLabel.style.color = '#666';
                    }
                    // Fade in
                    discountLabel.style.opacity = '1';
                }, 200);
            }
            
            calculateTotal();
        }
    }
    
    function calculateTotal() {
        var hours = parseInt(document.getElementById('hoursInput').value || 0);
        var rate = getRate(hours);
        var multiplier = (billingType === 'annual') ? 12 : 1;
        var baseTotal = hours * rate * multiplier;
        
        // Calculate values based on payment type
        var discount = 0;
        var total = baseTotal;
        var monthlyPayment = 0;
        var amountToPay = baseTotal;
        
        if (billingType === 'annual') {
            if (currentAnnualPaymentType === 'one_time') {
                // One-time payment with discount
                var discountPercent = annualPaymentConfig.one_time.discount_percent;
                discount = baseTotal * (discountPercent / 100);
                total = baseTotal - discount;
                amountToPay = total;
                monthlyPayment = 0;
            } else {
                // Monthly installments - divide by 12
                monthlyPayment = baseTotal / 12;
                amountToPay = monthlyPayment; // First month's payment
                total = baseTotal;
            }
        }
        
        var rateLabel = (billingType === 'annual') ? ' / hour (annual billing)' : ' / hour';
        var totalLabel = (billingType === 'annual') ? 'Annual Total $' : 'Total $';

        if (hours < minHours) {
            document.getElementById('rateDisplay').textContent = 'Minimum ' + minHours + ' hours required';
            document.getElementById('totalDisplay').textContent = totalLabel + formatCurrency(0) + (billingType === 'annual' ? ' (12 months)' : '');
            // Update order summary with zeros
            updateOrderSummary(0, 0, 0, 0, 0, 0);
        } else {
            document.getElementById('rateDisplay').textContent = '$' + rate.toFixed(2) + rateLabel;
            if (billingType === 'annual' && currentAnnualPaymentType === 'monthly') {
                document.getElementById('totalDisplay').textContent = totalLabel + formatCurrency(baseTotal) + ' ($' + formatCurrency(monthlyPayment) + '/mo x 12)';
            } else {
                document.getElementById('totalDisplay').textContent = totalLabel + formatCurrency(amountToPay) + (billingType === 'annual' ? ' (12 months)' : '');
            }
            // Update order summary with calculated values
            updateOrderSummary(hours, rate, amountToPay, discount, baseTotal, monthlyPayment);
        }

        // Highlight active tier
        highlightActiveTier(hours);
    }
    
    function updateOrderSummary(hours, rate, amountToPay, discount, baseTotal, monthlyPayment) {
        // Update order summary header - show the amount being charged now
        var orderTotalAmount = document.getElementById('orderTotalAmount');
        if (orderTotalAmount) {
            orderTotalAmount.textContent = '$' + formatCurrency(amountToPay);
        }
        
        // Update billing type with payment info
        var orderBillingType = document.getElementById('orderBillingType');
        if (orderBillingType) {
            var billingText = billingType.charAt(0).toUpperCase() + billingType.slice(1);
            if (billingType === 'annual') {
                if (currentAnnualPaymentType === 'one_time') {
                    billingText += ' - One-time payment';
                } else {
                    billingText += ' - 12 monthly installments';
                }
            }
            orderBillingType.textContent = billingText;
        }
        
        // Update plan item price
        var orderItemPrice = document.getElementById('orderItemPrice');
        if (orderItemPrice) {
            orderItemPrice.textContent = '$' + formatCurrency(baseTotal);
        }
        
        // Update hours quantity
        var orderHoursQty = document.getElementById('orderHoursQty');
        if (orderHoursQty) {
            orderHoursQty.textContent = hours;
        }
        
        // Update rate
        var orderRate = document.getElementById('orderRate');
        if (orderRate) {
            orderRate.textContent = formatCurrency(rate);
        }
        
        // Update subtotal
        var orderSubtotal = document.getElementById('orderSubtotal');
        if (orderSubtotal) {
            orderSubtotal.textContent = formatCurrency(baseTotal);
        }
        
        // Show/hide discount line (for one-time payment)
        var discountLineItem = document.getElementById('discountLineItem');
        var orderDiscount = document.getElementById('orderDiscount');
        var discountPercentEl = document.getElementById('discountPercent');
        
        if (discount > 0 && billingType === 'annual' && currentAnnualPaymentType === 'one_time') {
            if (discountLineItem) discountLineItem.style.display = 'flex';
            if (orderDiscount) orderDiscount.textContent = formatCurrency(discount);
            if (discountPercentEl) discountPercentEl.textContent = annualPaymentConfig.one_time.discount_percent;
        } else {
            if (discountLineItem) discountLineItem.style.display = 'none';
        }
        
        // Show/hide monthly payment line (for monthly installments)
        var monthlyPaymentLineItem = document.getElementById('monthlyPaymentLineItem');
        var orderMonthlyPayment = document.getElementById('orderMonthlyPayment');
        var totalDueLabel = document.getElementById('totalDueLabel');
        
        if (billingType === 'annual' && currentAnnualPaymentType === 'monthly' && monthlyPayment > 0) {
            if (monthlyPaymentLineItem) monthlyPaymentLineItem.style.display = 'flex';
            if (orderMonthlyPayment) orderMonthlyPayment.textContent = formatCurrency(monthlyPayment);
            if (totalDueLabel) totalDueLabel.textContent = 'Due today (1st month)';
        } else {
            if (monthlyPaymentLineItem) monthlyPaymentLineItem.style.display = 'none';
            if (totalDueLabel) totalDueLabel.textContent = 'Total due';
        }
        
        // Update total due - shows amount being charged now
        var orderTotalDue = document.getElementById('orderTotalDue');
        if (orderTotalDue) {
            orderTotalDue.textContent = formatCurrency(amountToPay);
        }
    }
    
    function highlightActiveTier(hours) {
        var listItems = document.querySelectorAll('.rate-distribution-list li');
        
        listItems.forEach(function(item) {
            item.style.color = '#666';
            item.style.fontWeight = 'normal';
            item.style.backgroundColor = 'transparent';
            item.style.padding = '0';
            item.style.borderRadius = '0';
        });
        
        if (!planPricing || !planPricing[billingType]) {
            return;
        }
        
        var pricing = planPricing[billingType];
        for (var i = 0; i < pricing.length; i++) {
            if (hours >= pricing[i].min && hours <= pricing[i].max) {
                if (listItems[i]) {
                    listItems[i].style.color = '#007bff';
                    listItems[i].style.fontWeight = '600';
                    listItems[i].style.backgroundColor = '#e7f3ff';
                    listItems[i].style.padding = '4px 8px';
                    listItems[i].style.borderRadius = '4px';
                }
                break;
            }
        }
    }
    
    function proceedToPayment() {
        var hours = parseInt(document.getElementById('hoursInput').value || 0);

        if (hours < minHours) {
            alert('Please enter at least ' + minHours + ' hours for the ' + planName + ' plan.');
            return false;
        }

        var rate = getRate(hours);
        if (rate === 0) {
            alert('Invalid hours entered. Please check the pricing tiers.');
            return false;
        }

        var multiplier = (billingType === 'annual') ? 12 : 1;
        var baseTotal = hours * rate * multiplier;
        
        // Calculate payment amount based on payment type
        var amountToPay = baseTotal;
        var monthlyPaymentAmount = 0;
        var installments = 1;
        
        if (billingType === 'annual') {
            if (currentAnnualPaymentType === 'one_time') {
                // One-time payment with discount
                var discountPercent = annualPaymentConfig.one_time.discount_percent;
                var discount = baseTotal * (discountPercent / 100);
                amountToPay = baseTotal - discount;
                monthlyPaymentAmount = 0;
                installments = 1;
            } else {
                // Monthly installments - charge 1/12 of total
                monthlyPaymentAmount = baseTotal / 12;
                amountToPay = monthlyPaymentAmount; // Charge first month now
                installments = 12;
            }
        }

        // Update hidden form fields
        document.getElementById('paymentAmount').value = amountToPay.toFixed(2);
        document.getElementById('paymentHours').value = hours;
        document.getElementById('paymentRate').value = rate.toFixed(2);
        document.getElementById('paymentMonthlyAmount').value = monthlyPaymentAmount.toFixed(2);
        document.getElementById('paymentTotalAnnual').value = baseTotal.toFixed(2);
        document.getElementById('paymentInstallments').value = installments;
        
        // Update annual payment type if annual billing
        if (billingType === 'annual') {
            document.getElementById('paymentAnnualType').value = currentAnnualPaymentType;
        }

        // Disable button to prevent double submission
        var submitButton = document.getElementById('confirmPayButton');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<span>Processing...</span>';
        }

        // Submit the payment form
        document.getElementById('paymentForm').submit();
    }

    function showPaymentChoiceModal() {
        var hours = parseInt(document.getElementById('hoursInput').value || 0);
        if (hours < minHours) {
            alert('Please enter at least ' + minHours + ' hours for the ' + planName + ' plan.');
            return false;
        }

        // If the saved card is expired, skip the modal and go straight to new-card payment (process_upgrade_payment_new_card.php).
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

    function useNewCardAndPay() {
        if (!hasActiveSubscription) {
            proceedToPayment();
            return;
        }

        var hours = parseInt(document.getElementById('hoursInput').value || 0);
        if (hours < minHours) {
            alert('Please enter at least ' + minHours + ' hours for the ' + planName + ' plan.');
            return false;
        }

        var rate = getRate(hours);
        if (rate === 0) {
            alert('Invalid hours entered. Please check the pricing tiers.');
            return false;
        }

        var multiplier = (billingType === 'annual') ? 12 : 1;
        var baseTotal = hours * rate * multiplier;

        var amountToPay = baseTotal;
        var monthlyPaymentAmount = 0;
        var installments = 1;

        if (billingType === 'annual') {
            if (currentAnnualPaymentType === 'one_time') {
                var discountPercent = annualPaymentConfig.one_time.discount_percent;
                var discount = baseTotal * (discountPercent / 100);
                amountToPay = baseTotal - discount;
                monthlyPaymentAmount = 0;
                installments = 1;
            } else {
                monthlyPaymentAmount = baseTotal / 12;
                amountToPay = monthlyPaymentAmount;
                installments = 12;
            }
        }

        // Update hidden form fields for new-card UI payment flow
        document.getElementById('newCardAmount').value = amountToPay.toFixed(2);
        document.getElementById('newCardHours').value = hours;
        document.getElementById('newCardRate').value = rate.toFixed(2);
        document.getElementById('newCardMonthlyAmount').value = monthlyPaymentAmount.toFixed(2);
        document.getElementById('newCardTotalAnnual').value = baseTotal.toFixed(2);
        document.getElementById('newCardInstallments').value = installments;
        if (billingType === 'annual') {
            document.getElementById('newCardAnnualType').value = currentAnnualPaymentType;
        }

        hidePaymentChoiceModal();

        // Disable button to prevent double submission
        var submitButton = document.getElementById('confirmPayButton');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<span>Processing...</span>';
        }

        document.getElementById('newCardForm').submit();
    }
    
    // Initialize display
    calculateTotal();
    
    // Payment method toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
        const paymentOptions = document.querySelectorAll('input[name=\"payment-method\"]');
        const detailCards = document.querySelectorAll('.payment-details-card');

        function showCard(target) {
            detailCards.forEach(function(card) {
                card.classList.toggle('active', card.dataset.details === target);
            });
        }

        paymentOptions.forEach(function(option) {
            option.addEventListener('change', function(event) {
                showCard(event.target.dataset.target);
            });
        });

        const checkedOption = document.querySelector('input[name=\"payment-method\"]:checked');
        if (checkedOption) {
            showCard(checkedOption.dataset.target);
        }

        // Close modal on backdrop click
        var modal = document.getElementById('paymentChoiceModal');
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    hidePaymentChoiceModal();
                }
            });
        }
    });
", 'script', array('type' => 'text/javascript'));
$set_body->push($script);

// Include mainframe to render the page
require_once('mainframe.php');
?>
