<?php
// Include required files first
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once ('plans_config.php');

// Set page title
$set_title = "My Subscription - DepoDash Resource Center";

// Get user's current subscription
$currentSubscription = null;
$remainingMinutes = 0;
$remainingStorage = 0;
$planDetails = null;

if (isset($UserAccount) && $UserAccount->logged_in) {
    $userId = $UserAccount->user_details['id_user'];
    
    // Get the latest active subscription
    $subscriptionData = array('id', 'plan_id', 'rate', 'hours', 'commitment', 'prepaid', 'plan_name', 'plan_key', 'is_active','created_at','subscribed_at','card_expiry_date','last_four_digits');
    $DB->sql(
        'SELECT casepad_subscribed_plan.id,
                casepad_subscribed_plan.plan_id,
                casepad_subscribed_plan.rate,
                casepad_subscribed_plan.hours,
                casepad_subscribed_plan.commitment,
                casepad_subscribed_plan.prepaid,
                subscription_plans.name as plan_name,
                subscription_plans.plan_id as plan_key,
                casepad_subscribed_plan.is_active,
                casepad_subscribed_plan.created_at,
                casepad_subscribed_plan.subscribed_at,
                casepad_subscribed_plan.card_expiry_date,
                casepad_subscribed_plan.last_four_digits
         FROM casepad_subscribed_plan 
         LEFT JOIN subscription_plans ON subscription_plans.id = casepad_subscribed_plan.plan_id
         WHERE casepad_subscribed_plan.id_owner = ? AND casepad_subscribed_plan.is_active = 1
         ORDER BY casepad_subscribed_plan.id DESC LIMIT 1',
        array('s', $userId),
        $subscriptionData
    );

    if (isset($subscriptionData[0]) && is_array($subscriptionData[0]) && isset($subscriptionData[0]['id']) && $subscriptionData[0]['id'] !== 'id') {
        $currentSubscription = $subscriptionData[0];
        
        // Get plan details from config
        if (isset($plans_config['plans'][$currentSubscription['plan_key']])) {
            $planDetails = $plans_config['plans'][$currentSubscription['plan_key']];
        }
    }
    
    // Get remaining minutes
    $minutesData = array('total_minutes');
    $DB->sql(
        'SELECT SUM(minutes) as total_minutes FROM casepad_minutes_credits WHERE id_owner = ?',
        array('s', $userId),
        $minutesData
    );
    $remainingMinutes = isset($minutesData[0]['total_minutes']) ? (int)$minutesData[0]['total_minutes'] : 0;
    
    // Get remaining storage
    $storageData = array('total_storage');
    $DB->sql(
        'SELECT SUM(storage) as total_storage FROM casepad_storage_credits WHERE id_owner = ?',
        array('s', $userId),
        $storageData
    );
    $remainingStorage = isset($storageData[0]['total_storage']) ? (int)$storageData[0]['total_storage'] : 0;
}

// Create the main content body
$set_body = new content_block(NULL, 'div', array('style' => 'width: 100%;height: 100%;'));

// Banner section
$banner = new content_block(NULL, 'div', array('class' => 'banner'));
$banner->push(new content_block('My Subscription', 'h1', array('style' => 'text-align: center; margin:5px 0px;')));
$set_body->push($banner);

// Main container
$mainContainer = new content_block(NULL, 'div', array('style' => 'max-width: 900px; margin: 30px auto; padding: 0 20px;'));

if ($currentSubscription) {
    // Current Plan Card
    $planCard = new content_block(NULL, 'div', array('class' => 'form-box', 'style' => 'margin-bottom: 24px;'));
    
    // Plan Header
    $planHeader = new content_block(NULL, 'div', array('style' => 'display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;'));
    
    $planTitleSection = new content_block(NULL, 'div');
    $planTitleSection->push(new content_block('Current Plan', 'h3', array('style' => 'margin: 0 0 4px 0; font-size: 14px; color: #666; font-weight: 500;')));
    $planTitleSection->push(new content_block(strtoupper($currentSubscription['plan_name'] ?? 'CLASSIC'), 'h2', array('style' => 'margin: 0; font-size: 28px; color: #27475f;')));
    $planHeader->push($planTitleSection);
    
    // Status Badge
    $isActive = $currentSubscription['is_active'] == 1;
    $statusColor = $isActive ? '#28a745' : '#dc3545';
    $statusText = $isActive ? 'Active' : 'Expired';
    $statusBadge = new content_block($statusText, 'span', array('style' => 'background: ' . $statusColor . '; color: white; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 500;'));
    $planHeader->push($statusBadge);
    
    $planCard->push($planHeader);
    
    // Plan Details Grid
    $detailsGrid = new content_block(NULL, 'div', array('style' => 'display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 24px;'));
    
    // Rate
    $rateBox = new content_block(NULL, 'div', array('style' => 'background: #f8f9fa; padding: 16px; border-radius: 8px;'));
    $rateBox->push(new content_block('Rate per Hour', 'div', array('style' => 'font-size: 12px; color: #666; margin-bottom: 4px;')));
    $rateBox->push(new content_block('$' . number_format($currentSubscription['rate'], 2), 'div', array('style' => 'font-size: 24px; font-weight: 600; color: #27475f;')));
    $detailsGrid->push($rateBox);
    
    // Hours Purchased
    $hoursBox = new content_block(NULL, 'div', array('style' => 'background: #f8f9fa; padding: 16px; border-radius: 8px;'));
    $hoursBox->push(new content_block('Hours Purchased', 'div', array('style' => 'font-size: 12px; color: #666; margin-bottom: 4px;')));
    $hoursBox->push(new content_block($currentSubscription['hours'] . ' hrs', 'div', array('style' => 'font-size: 24px; font-weight: 600; color: #27475f;')));
    $detailsGrid->push($hoursBox);
    
    // Billing Type
    $billingBox = new content_block(NULL, 'div', array('style' => 'background: #f8f9fa; padding: 16px; border-radius: 8px;'));
    $billingBox->push(new content_block('Billing Type', 'div', array('style' => 'font-size: 12px; color: #666; margin-bottom: 4px;')));
    $billingText = ucfirst($currentSubscription['commitment']);
    if ($currentSubscription['prepaid']) {
        $billingText .= ' (Prepaid)';
    }
    $billingBox->push(new content_block($billingText, 'div', array('style' => 'font-size: 24px; font-weight: 600; color: #27475f;')));
    $detailsGrid->push($billingBox);
    
    // Subscription Date
    $subscriptionDateBox = new content_block(NULL, 'div', array('style' => 'background: #f8f9fa; padding: 16px; border-radius: 8px;'));
    $subscriptionDateBox->push(new content_block('Subscription Date', 'div', array('style' => 'font-size: 12px; color: #666; margin-bottom: 4px;')));
    $subscriptionDateBox->push(new content_block(date('M d, Y', strtotime($currentSubscription['subscribed_at'])), 'div', array('style' => 'font-size: 24px; font-weight: 600; color: #27475f;')));
    $detailsGrid->push($subscriptionDateBox);
    
    // Card Expiry Date
    $cardExpiryDateBox = new content_block(NULL, 'div', array('style' => 'background: #f8f9fa; padding: 16px; border-radius: 8px;'));
    $cardExpiryDateBox->push(new content_block('Card Expiry Date', 'div', array('style' => 'font-size: 12px; color: #666; margin-bottom: 4px;')));
    $cardExpiryDateBox->push(new content_block(date('M d, Y', strtotime($currentSubscription['card_expiry_date'])), 'div', array('style' => 'font-size: 24px; font-weight: 600; color: #27475f;')));
    $detailsGrid->push($cardExpiryDateBox);
    
    // Last 4 Digits
    $lastFourDigitsBox = new content_block(NULL, 'div', array('style' => 'background: #f8f9fa; padding: 16px; border-radius: 8px;'));
    $lastFourDigitsBox->push(new content_block('Last 4 Digits', 'div', array('style' => 'font-size: 12px; color: #666; margin-bottom: 4px;')));
    $lastFourDigitsBox->push(new content_block($currentSubscription['last_four_digits'], 'div', array('style' => 'font-size: 24px; font-weight: 600; color: #27475f;')));
    $detailsGrid->push($lastFourDigitsBox);
    
    $planCard->push($detailsGrid);
    
    // Action Buttons
    $actionButtons = new content_block(NULL, 'div', array('style' => 'display: flex; gap: 12px; flex-wrap: wrap;'));
    $actionButtons->push(new anchor('Upgrade Plan', array('href' => '/subscription/upgrade.php', 'class' => 'primary_button text-white', 'style' => 'padding: 12px 24px;')));
    $actionButtons->push(new anchor('Update Card', array('href' => '/subscription/update_card.php', 'class' => 'secondary_button', 'style' => 'padding: 12px 24px;')));
    $actionButtons->push(new anchor('Buy More Hours', array('href' => '/subscription/buy_credits.php', 'class' => 'secondary_button', 'style' => 'padding: 12px 24px;')));
    $planCard->push($actionButtons);
    
    $mainContainer->push($planCard);
    
    // Credits Section
    $creditsCard = new content_block(NULL, 'div', array('class' => 'form-box', 'style' => 'margin-bottom: 24px;'));
    $creditsCard->push(new content_block('Available Credits', 'h3', array('style' => 'margin: 0 0 20px 0; font-size: 18px; color: #27475f;')));
    
    $creditsGrid = new content_block(NULL, 'div', array('style' => 'display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;'));
    
    
} else {
    // No Subscription Card
    $noSubCard = new content_block(NULL, 'div', array('class' => 'form-box', 'style' => 'text-align: center; padding: 60px 40px;'));
    
    $noSubCard->push(new content_block('📦', 'div', array('style' => 'font-size: 64px; margin-bottom: 20px;')));
    $noSubCard->push(new content_block('No Active Subscription', 'h2', array('style' => 'margin: 0 0 12px 0; color: #27475f;')));
    $noSubCard->push(new paragraph('You don\'t have an active subscription yet. Choose a plan to get started with DepoDash transcription services.', array('style' => 'color: #666; margin-bottom: 24px; max-width: 400px; margin-left: auto; margin-right: auto;')));
    
    $noSubCard->push(new anchor('View Subscription Plans', array('href' => '/subscription/payment.php', 'class' => 'primary_button text-white', 'style' => 'padding: 14px 32px; font-size: 16px;')));
    
    $mainContainer->push($noSubCard);
}

$set_body->push($mainContainer);

// Add hover styles
$hoverStyles = new content_block('
    .form-box a:hover {
        background: #e9ecef !important;
        transform: translateY(-2px);
    }
', 'style');
$set_body->push($hoverStyles);

// Include mainframe to render the page
require_once('mainframe.php');
?>

