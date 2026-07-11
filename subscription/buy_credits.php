<?php
// Include required files first
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once ('plans_config.php');

// Check if user is logged in
if (!isset($UserAccount) || !$UserAccount->logged_in) {
    header('Location: /login.php');
    exit;
}

$userId = $UserAccount->user_details['id_user'];

// Get user's current active subscription
$subscriptionData = array('id','guid', 'plan_id', 'rate', 'hours', 'commitment', 'prepaid', 'plan_name', 'plan_key', 'last_four_digits','card_expiry_date');
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
    array('s', $userId),
    $subscriptionData
);

// Redirect if no active subscription
if (!isset($subscriptionData[0]) || !is_array($subscriptionData[0]) || !isset($subscriptionData[0]['id']) || $subscriptionData[0]['id'] === 'id') {
    header('Location: /subscription/payment.php?error=no_subscription');
    exit;
}

$currentSubscription = $subscriptionData[0];

$planName = strtoupper($currentSubscription['plan_name']);
$planKey = $currentSubscription['plan_key'];
$rate = (float)$currentSubscription['rate'];
$billing = $currentSubscription['commitment'];
$subscriptionId = $currentSubscription['id'];
$vaultToken = $currentSubscription['guid'];
$lastFourDigits = isset($currentSubscription['last_four_digits']) ? trim((string)$currentSubscription['last_four_digits']) : '';
$cardExpiryDate = isset($currentSubscription['card_expiry_date']) ? trim((string)$currentSubscription['card_expiry_date']) : '';
$isSavedCardExpired = false;
if ($cardExpiryDate !== '' && strtolower($cardExpiryDate) !== 'card_expiry_date') {
    $expTs = strtotime($cardExpiryDate);
    if ($expTs !== false) {
        $isSavedCardExpired = $expTs < strtotime(date('Y-m-d'));
    }
}

// Set page title
$set_title = "Buy More Hours - " . $planName . " Plan - DepoDash Resource Center";

// Create the main content body
$set_body = new content_block(NULL, 'div', array('style' => 'width: 100%; height: 100%;'));

// Banner section
$banner = new content_block(NULL, 'div', array('class' => 'banner'));
$banner->push(new content_block('Buy More Hours', 'h1', array('style' => 'text-align: center; margin:5px 0px;')));
$set_body->push($banner);

// Buy container
$buyContainer = new content_block(NULL, 'div', array('class' => 'buy-container'));

// Plan header showing current subscription info
$planHeader = new content_block(NULL, 'div', array('class' => 'plan-header'));
$planHeader->push(new content_block($planName . ' Plan', 'h1', array('class' => 'plan-name')));
$planHeader->push(new paragraph('Add more hours to your current subscription at your locked-in rate', array('class' => 'plan-description')));
$buyContainer->push($planHeader);

// Current subscription info box
$subscriptionInfo = new content_block(NULL, 'div', array('style' => 'background: #e8f5e9; border: 1px solid #c8e6c9; border-radius: 8px; padding: 16px; margin-bottom: 20px;'));
$subscriptionInfo->push(new content_block('✓ Your Subscribed Rate', 'div', array('style' => 'font-weight: 600; color: #2e7d32; margin-bottom: 8px;')));
$infoGrid = new content_block(NULL, 'div', array('style' => 'display: flex; gap: 24px; flex-wrap: wrap;'));
$infoGrid->push(new content_block('<strong>Rate:</strong> $' . number_format($rate, 2) . '/hour', 'span', array('style' => 'color: #333;')));
$infoGrid->push(new content_block('<strong>Billing:</strong> ' . ucfirst($billing), 'span', array('style' => 'color: #333;')));
$subscriptionInfo->push($infoGrid);
$buyContainer->push($subscriptionInfo);

// Separator line
$buyContainer->push(new content_block(NULL, 'div', array('class' => 'separator-line')));

// Hours section
$hoursSection = new content_block(NULL, 'div', array('class' => 'hours-section'));
$hoursSection->push(new paragraph('How many additional hours do you need?', array('class' => 'hours-question')));

// Hours input wrapper
$hoursInputWrapper = new content_block(NULL, 'div', array('class' => 'hours-input-wrapper'));

// Hours input field
$hoursInput = new content_block(NULL, 'input', array(
    'type' => 'number',
    'class' => 'hours-input',
    'placeholder' => 'Enter hours',
    'min' => '1',
    'step' => '1',
    'value' => '5',
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
$pricingInfo->push(new content_block('$' . number_format($rate, 2) . ' / hour', 'span', array('class' => 'rate-display', 'id' => 'rateDisplay')));
$pricingInfo->push(new content_block('Total $0.00', 'span', array('class' => 'total-display', 'id' => 'totalDisplay')));
$hoursSection->push($pricingInfo);

$buyContainer->push($hoursSection);

// Order summary container
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
$lineItem1Left->push(new content_block($planName . ' Plan (Additional Hours)', 'span', array('class' => 'order-item-name')));
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
$lineItem2Right->push(new content_block('$<span id="orderRate">' . number_format($rate, 2) . '</span> each', 'span', array('class' => 'order-item-unit-price')));
$lineItem2->push($lineItem2Right);
$orderDetails->push($lineItem2);

// Line item 3: Total due
$lineItem3 = new content_block(NULL, 'div', array('class' => 'order-line-item total-due-item'));
$lineItem3Left = new content_block(NULL, 'div', array('class' => 'order-line-left'));
$lineItem3Left->push(new content_block('Total due', 'span', array('class' => 'order-item-label total-due-label')));
$lineItem3->push($lineItem3Left);
$lineItem3Right = new content_block(NULL, 'div', array('class' => 'order-line-right'));
$lineItem3Right->push(new content_block('$<span id="orderTotalDue">0.00</span>', 'span', array('class' => 'order-total-amount')));
$lineItem3->push($lineItem3Right);
$orderDetails->push($lineItem3);

$orderSummaryBox->push($orderDetails);
$orderSummaryContainer->push($orderSummaryBox);

// Separator line
$orderSummaryContainer->push(new content_block(NULL, 'div', array('class' => 'separator-line')));

// Payment form (hidden, will be submitted via JavaScript)
$paymentForm = new content_block(NULL, 'form', array(
    'id' => 'paymentForm',
    'method' => 'POST',
    'action' => '/subscription/process_vault_payment.php',
    'style' => 'display: none;'
));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'amount', 'id' => 'paymentAmount', 'value' => '0.00')));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'vaultToken', 'value' => $vaultToken)));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'plan_id', 'value' => $currentSubscription['plan_id'])));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'planName', 'value' => $planName)));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'description', 'value' => 'Additional hours for ' . $planName . ' Plan')));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'billing', 'value' => $billing)));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'hours', 'id' => 'paymentHours', 'value' => '0')));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'rate', 'id' => 'paymentRate', 'value' => number_format($rate, 2))));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'subscription_id', 'value' => $subscriptionId)));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'is_additional_credits', 'value' => '1')));
$paymentForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'annualPaymentType', 'id' => 'paymentAnnualType', 'value' => 'one_time')));
$orderSummaryContainer->push($paymentForm);

// New card payment form (hidden, will be submitted via JavaScript)
$newCardForm = new content_block(NULL, 'form', array(
    'id' => 'newCardForm',
    'method' => 'POST',
    'action' => '/subscription/process_buy_credits_new_card.php',
    'style' => 'display: none;'
));
$newCardForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'amount', 'id' => 'newCardAmount', 'value' => '0.00')));
$newCardForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'plan_id', 'value' => $currentSubscription['plan_id'])));
$newCardForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'planName', 'value' => $planName)));
$newCardForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'description', 'value' => 'Additional hours for ' . $planName . ' Plan')));
$newCardForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'billing', 'value' => $billing)));
$newCardForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'hours', 'id' => 'newCardHours', 'value' => '0')));
$newCardForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'rate', 'id' => 'newCardRate', 'value' => number_format($rate, 2))));
$newCardForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'subscription_id', 'value' => $subscriptionId)));
$newCardForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'is_additional_credits', 'value' => '1')));
$orderSummaryContainer->push($newCardForm);

// Confirm & Pay Button
$confirmPayButton = new content_block(NULL, 'button', array('type' => 'button', 'class' => 'next-button', 'id' => 'confirmPayButton', 'onclick' => 'showPaymentChoiceModal();'));
$confirmPayButton->push(new content_block('Pay Now', 'span'));
$orderSummaryContainer->push($confirmPayButton);

// Payment Choice Modal
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

// Terms and Privacy Section
$termsPrivacySection = new content_block(NULL, 'div', array('class' => 'terms-privacy-section'));
$termsPrivacySection->push(new paragraph('By paying, you agree to Link\'s <a href="#" class="terms-link">Terms</a> and <a href="#" class="terms-link">Privacy</a>.', array('class' => 'terms-privacy-text')));
$orderSummaryContainer->push($termsPrivacySection);

// Back to subscription link
$backLink = new content_block(NULL, 'div', array('style' => 'text-align: center; margin-top: 20px;'));
$backLink->push(new anchor('← Back to My Subscription', array('href' => '/subscription/my_subscription.php', 'style' => 'color: #27475f; text-decoration: none; font-weight: 500;')));
$orderSummaryContainer->push($backLink);

$buyContainer->push($orderSummaryContainer);
$set_body->push($buyContainer);

// Add JavaScript for calculation
$script = new content_block("
    var rate = " . $rate . ";
    var planName = '" . addslashes($planName) . "';
    var billing = '" . addslashes($billing) . "';
    var lastFourDigits = '" . addslashes($lastFourDigits) . "';
    var isSavedCardExpired = " . ($isSavedCardExpired ? 'true' : 'false') . ";
    
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
        if (currentValue > 1) {
            input.value = currentValue - 1;
            calculateTotal();
        }
    }
    
    function calculateTotal() {
        var hours = parseInt(document.getElementById('hoursInput').value || 0);
        
        if (hours < 1) {
            hours = 0;
        }
        
        var total = hours * rate;
        
        // Update displays
        document.getElementById('totalDisplay').textContent = 'Total \$' + formatCurrency(total);
        document.getElementById('orderTotalAmount').textContent = '\$' + formatCurrency(total);
        document.getElementById('orderItemPrice').textContent = '\$' + formatCurrency(total);
        document.getElementById('orderHoursQty').textContent = hours;
        document.getElementById('orderTotalDue').textContent = formatCurrency(total);
    }
    
    function proceedToPayment() {
        var hours = parseInt(document.getElementById('hoursInput').value || 0);

        if (hours < 1) {
            alert('Please enter at least 1 hour.');
            return false;
        }

        var total = hours * rate;

        // Update hidden form fields
        document.getElementById('paymentAmount').value = total.toFixed(2);
        document.getElementById('paymentHours').value = hours;
        document.getElementById('newCardAmount').value = total.toFixed(2);
        document.getElementById('newCardHours').value = hours;

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
        if (hours < 1) {
            alert('Please enter at least 1 hour.');
            return false;
        }

        // If saved card is expired, skip the modal and go straight to new card (Paya) transaction page.
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
        var hours = parseInt(document.getElementById('hoursInput').value || 0);
        if (hours < 1) {
            alert('Please enter at least 1 hour.');
            return false;
        }

        var total = hours * rate;

        // Update hidden form fields for new card flow
        document.getElementById('newCardAmount').value = total.toFixed(2);
        document.getElementById('newCardHours').value = hours;

        // Disable button to prevent double submission
        var submitButton = document.getElementById('confirmPayButton');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<span>Processing...</span>';
        }

        // Submit the new card form
        document.getElementById('newCardForm').submit();
    }
    
    // Initialize display
    document.addEventListener('DOMContentLoaded', function() {
        calculateTotal();
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

