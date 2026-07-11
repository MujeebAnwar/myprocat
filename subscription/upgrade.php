<?php
// Include required files first
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once ('plans_config.php');

// Set page title
$set_title = "Upgrade Plan - DepoDash Resource Center";

// Get user's current subscription
$currentPlanKey = null;
$currentSubscription = null;

if (isset($UserAccount) && $UserAccount->logged_in) {
    $userId = $UserAccount->user_details['id_user'];
    
    // Get the latest active subscription
    $subscriptionData = array('id', 'plan_id', 'rate', 'hours', 'commitment','prepaid', 'plan_name', 'plan_key','last_four_digits');
    $DB->sql(
        'SELECT casepad_subscribed_plan.id,
                casepad_subscribed_plan.plan_id,
                casepad_subscribed_plan.rate,
                casepad_subscribed_plan.hours,
                casepad_subscribed_plan.commitment,
                casepad_subscribed_plan.prepaid,
                subscription_plans.name as plan_name,
                subscription_plans.plan_id as plan_key,
                casepad_subscribed_plan.last_four_digits
         FROM casepad_subscribed_plan 
         LEFT JOIN subscription_plans ON subscription_plans.id = casepad_subscribed_plan.plan_id
         WHERE casepad_subscribed_plan.id_owner = ? 
         ORDER BY casepad_subscribed_plan.id DESC LIMIT 1',
        array('s', $userId),
        $subscriptionData
    );
    
    if (isset($subscriptionData[0]) && is_array($subscriptionData[0]) && isset($subscriptionData[0]['id']) && $subscriptionData[0]['id'] !== 'id') {
        $currentSubscription = $subscriptionData[0];
        $currentPlanKey = $currentSubscription['plan_key'];
    }
}

// Define plan hierarchy for upgrades
$planHierarchy = array('classic' => 1, 'professional' => 2, 'enterprise' => 3);
$currentPlanLevel = isset($planHierarchy[$currentPlanKey]) ? $planHierarchy[$currentPlanKey] : 0;

// Get current billing type from subscription
$currentBillingType = null;
if ($currentSubscription && isset($currentSubscription['commitment'])) {
    $currentBillingType = $currentSubscription['commitment']; // 'monthly' or 'annual'
}

// Billing rules:
// - If current subscription is annual: do NOT allow switching to monthly (prevent commitment downgrade)
// - If current subscription is monthly (or no subscription): allow choosing monthly vs annual
$billingLocked = ($currentSubscription && $currentBillingType === 'annual');

// Create the main content body
$set_body = new content_block(NULL, 'div', array('style' => 'width: 100%;height: 100%;'));

// Banner section
$banner = new content_block(NULL, 'div', array('class' => 'banner'));
$banner->push(new content_block('Upgrade Your Plan', 'h1', array('style' => 'text-align: center; margin:5px 0px;')));
$set_body->push($banner);

// Section heading
if ($currentSubscription) {
    $billingLabel = $currentBillingType ? ' (' . ucfirst($currentBillingType) . ')' : '';
    $currentPlanText = 'Current Plan: <strong>' . strtoupper($currentSubscription['plan_name']) . $billingLabel . '</strong>';
    $sectionHeading = new content_block($currentPlanText, 'h2', array('class' => 'section-heading', 'style' => 'text-align:center; margin-top:20px; font-size: 16px; color: #666;'));
    $set_body->push($sectionHeading);
}

$descText = 'Upgrade to unlock more features, better rates, and additional capabilities.';
$desc = new paragraph($descText, array('style' => 'text-align:center; max-width: 600px; margin: 10px auto 20px auto; color: #666;'));
$set_body->push($desc);

// Billing toggle (only for users without an existing billing commitment)
if (!$billingLocked) {
    $billingToggle = new content_block(NULL, 'div', array('class' => 'billing-toggle', 'style' => 'margin-bottom:30px;'));
    $toggleContainer = new content_block(NULL, 'div', array('class' => 'container'));
    $tabs = new content_block(NULL, 'div', array('class' => 'tabs'));
    
    // Set default checked based on current subscription billing type
    $monthlyChecked = ($currentBillingType !== 'annual') ? array('checked' => 'checked') : array();
    $annualChecked = ($currentBillingType === 'annual') ? array('checked' => 'checked') : array();
    
    $tabs->push(new content_block(NULL, 'input', array_merge($monthlyChecked, array('name' => 'tabs', 'id' => 'radio-1', 'type' => 'radio', 'onchange' => 'updateBillingType("monthly")'))));
    $tabs->push(new content_block('Monthly', 'label', array('for' => 'radio-1', 'class' => 'tab')));
    $tabs->push(new content_block(NULL, 'input', array_merge($annualChecked, array('name' => 'tabs', 'id' => 'radio-2', 'type' => 'radio', 'onchange' => 'updateBillingType("annual")'))));
    
    $annualLabel = new content_block('Annual', 'label', array('for' => 'radio-2', 'class' => 'tab'));
    $tabs->push($annualLabel);
    
    $tabs->push(new content_block(NULL, 'span', array('class' => 'glider')));
    $toggleContainer->push($tabs);
    $billingToggle->push($toggleContainer);
    $set_body->push($billingToggle);
}

// Plans comparison container
$plansContainer = new content_block(NULL, 'div', array('style' => 'max-width: 1000px; margin: 0 auto; padding: 0 20px;'));

// Plans Grid
$plansGrid = new content_block(NULL, 'div', array('class' => 'pricing-cards', 'style' => 'display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; margin-bottom: 40px;'));

foreach ($plans_config['plans'] as $planKey => $plan) {
    $planLevel = isset($planHierarchy[$planKey]) ? $planHierarchy[$planKey] : 0;
    $isCurrentPlan = ($planKey === $currentPlanKey);
    $isUpgrade = ($planLevel > $currentPlanLevel);
    $isDowngrade = ($planLevel < $currentPlanLevel && $currentPlanLevel > 0);

    // If user already has a plan, don't show any downgrade options/cards
    if ($isDowngrade) {
        continue;
    }
    
    // Card styling - will be updated by JavaScript based on billing type match
    $cardStyle = 'background: white; border-radius: 16px; padding: 32px 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); position: relative; transition: all 0.3s ease;';
    if ($plan['is_accent']) {
        $cardStyle .= ' border: 2px solid #ff6600;';
    } else {
        $cardStyle .= ' border: 1px solid #e0e0e0;';
    }
    
    $planCard = new content_block(NULL, 'div', array('class' => 'upgrade-plan-card', 'id' => 'upgrade-card-' . $planKey, 'data-plan' => $planKey, 'data-is-current' => $isCurrentPlan ? '1' : '0', 'data-is-accent' => $plan['is_accent'] ? '1' : '0', 'style' => $cardStyle));
    
    // Badge container - will be updated by JavaScript
    $badgeContainer = new content_block(NULL, 'div', array('id' => 'upgrade-badge-' . $planKey, 'class' => 'plan-badge-container'));
    if ($plan['is_accent'] && $isUpgrade && !$isCurrentPlan) {
        $recommendedBadge = new content_block('Recommended', 'div', array('class' => 'plan-badge recommended-badge', 'style' => 'position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: #ff6600; color: white; padding: 4px 16px; border-radius: 20px; font-size: 12px; font-weight: 600;'));
        $badgeContainer->push($recommendedBadge);
    }
    $planCard->push($badgeContainer);
    
    // Plan Name
    $planCard->push(new content_block($plan['name'], 'h3', array('style' => 'margin: 10px 0 8px 0; font-size: 22px; color: #27475f; text-align: center;')));
    
    // Ideal For
    $planCard->push(new paragraph($plan['ideal_for'], array('style' => 'text-align: center; color: #666; font-size: 13px; margin-bottom: 16px; min-height: 40px;')));
    
    // Pricing
    if ($plan['has_price']) {
        $priceDisplay = new content_block(NULL, 'div', array('style' => 'text-align: center; margin-bottom: 20px;'));
        $priceWrapper = new content_block(NULL, 'div', array('style' => 'display: flex; align-items: center; gap: 8px; justify-content: center; flex-wrap: wrap;'));
        $priceWrapper->push(new content_block($plan['pricing']['monthly'], 'span', array('id' => 'upgrade-price-' . $planKey, 'style' => 'font-size: 24px; font-weight: 700; color: #27475f;')));
        $savingsBadge = new content_block($plan['pricing']['savings_badge'], 'span', array('id' => 'upgrade-savings-' . $planKey, 'class' => 'savings-badge', 'style' => 'display: none; background: #28a745; color: white; padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; white-space: nowrap;'));
        $priceWrapper->push($savingsBadge);
        $priceDisplay->push($priceWrapper);
        $priceDisplay->push(new content_block('per hour', 'div', array('style' => 'font-size: 13px; color: #999;')));
        $planCard->push($priceDisplay);
    } else {
        $priceDisplay = new content_block(NULL, 'div', array('style' => 'text-align: center; margin-bottom: 20px;'));
        $priceDisplay->push(new content_block('Custom Pricing', 'div', array('style' => 'font-size: 20px; font-weight: 600; color: #27475f;')));
        $priceDisplay->push(new content_block('Contact sales', 'div', array('style' => 'font-size: 13px; color: #999;')));
        $planCard->push($priceDisplay);
    }
    
    // Features
    $featuresList = new content_block(NULL, 'ul', array('style' => 'list-style: none; padding: 0; margin: 0 0 24px 0;'));
    foreach ($plan['card_features'] as $feature) {
        $featureItem = new content_block('✓ ' . $feature, 'li', array('style' => 'padding: 8px 0; font-size: 13px; color: #555; border-bottom: 1px solid #f0f0f0;'));
        $featuresList->push($featureItem);
    }
    $planCard->push($featuresList);
    
    // Action Button - container with ID for JavaScript updates
    $buttonContainer = new content_block(NULL, 'div', array('id' => 'upgrade-btn-container-' . $planKey, 'style' => 'text-align: center;'));
    
    // Button will be dynamically updated by JavaScript based on billing type
    // Default: show upgrade/get started button
    if ($plan['has_price']) {
        $defaultBilling = $currentBillingType ? $currentBillingType : 'monthly';
        $buttonContainer->push(new anchor('Get Started', array('href' => '/subscription/buy.php?plan=' . $plan['plan_id'] . '&billing=' . $defaultBilling, 'class' => 'primary_button text-white upgrade-btn-' . $planKey, 'style' => 'display: inline-block; padding: 12px 24px; width: 100%; text-align: center; box-sizing: border-box;')));
    } else {
        $buttonContainer->push(new anchor('Request Quote', array('href' => 'https://depodash.com/customequote','target' => '_blank', 'class' => 'secondary_button', 'style' => 'display: inline-block; padding: 12px 24px; width: 100%; text-align: center; box-sizing: border-box;')));
    }
    
    $planCard->push($buttonContainer);
    $plansGrid->push($planCard);
}

$plansContainer->push($plansGrid);

// Benefits of Upgrading Section
$benefitsSection = new content_block(NULL, 'div', array('class' => 'form-box', 'style' => 'margin-bottom: 30px;'));
$benefitsSection->push(new content_block('Benefits of Upgrading', 'h3', array('style' => 'margin: 0 0 20px 0; font-size: 20px; color: #27475f; text-align: center;')));

$benefitsGrid = new content_block(NULL, 'div', array('style' => 'display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;'));

$benefits = array(
    array('icon' => '💰', 'title' => 'Better Rates', 'desc' => 'Higher plans offer lower per-hour rates'),
    array('icon' => '⚡', 'title' => 'More Features', 'desc' => 'Access advanced tools and capabilities'),
    array('icon' => '👥', 'title' => 'More Users', 'desc' => 'Add team members to your account'),
    array('icon' => '🎯', 'title' => 'Priority Support', 'desc' => 'Get faster response times')
);

foreach ($benefits as $benefit) {
    $benefitBox = new content_block(NULL, 'div', array('style' => 'text-align: center; padding: 20px;'));
    $benefitBox->push(new content_block($benefit['icon'], 'div', array('style' => 'font-size: 36px; margin-bottom: 12px;')));
    $benefitBox->push(new content_block($benefit['title'], 'div', array('style' => 'font-weight: 600; color: #27475f; margin-bottom: 6px;')));
    $benefitBox->push(new content_block($benefit['desc'], 'div', array('style' => 'font-size: 13px; color: #666;')));
    $benefitsGrid->push($benefitBox);
}

$benefitsSection->push($benefitsGrid);
$plansContainer->push($benefitsSection);

// Back to Subscription Link
$backLink = new content_block(NULL, 'div', array('style' => 'text-align: center; margin-bottom: 30px;'));
$backLink->push(new anchor('← Back to My Subscription', array('href' => '/subscription/my_subscription.php', 'style' => 'color: #27475f; text-decoration: none; font-weight: 500;')));
$plansContainer->push($backLink);

$set_body->push($plansContainer);

// Add hover styles for cards
$cardStyles = new content_block('
    .upgrade-plan-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.12);
    }
    
    @media (max-width: 768px) {
        .pricing-cards {
            grid-template-columns: 1fr !important;
        }
    }
    
    #upgrade-price-classic, #upgrade-price-professional, #upgrade-price-enterprise {
        transition: all 0.3s ease;
    }
    
    .price-updating {
        animation: priceFlash 0.3s ease;
    }
    
    .savings-badge {
        transition: all 0.3s ease;
        animation: badgeBounce 0.5s ease;
    }
    
    @keyframes priceFlash {
        0% { opacity: 1; }
        50% { opacity: 0.5; transform: scale(0.98); }
        100% { opacity: 1; transform: scale(1); }
    }
    
    @keyframes badgeBounce {
        0% { 
            opacity: 0; 
            transform: scale(0.5);
        }
        60% {
            transform: scale(1.1);
        }
        100% { 
            opacity: 1; 
            transform: scale(1);
        }
    }
', 'style');
$set_body->push($cardStyles);

// Add JavaScript for billing type toggle - dynamically generated from config
$pricingDataJs = "var upgradePricingData = {\n";
foreach ($plans_config['plans'] as $planKey => $plan) {
    $planLevel = isset($planHierarchy[$planKey]) ? $planHierarchy[$planKey] : 0;
    $isUpgrade = ($planLevel > $currentPlanLevel);
    $isDowngrade = ($planLevel < $currentPlanLevel && $currentPlanLevel > 0);

    // Keep JS data in sync with UI: exclude downgrade plans entirely
    if ($isDowngrade) {
        continue;
    }
    
    $pricingDataJs .= "    '" . $planKey . "': {\n";
    $pricingDataJs .= "        hasPrice: " . ($plan['has_price'] ? 'true' : 'false') . ",\n";
    if ($plan['has_price']) {
        $pricingDataJs .= "        monthly: '" . addslashes($plan['pricing']['monthly']) . "',\n";
        $pricingDataJs .= "        annual: '" . addslashes($plan['pricing']['annual']) . "',\n";
        $pricingDataJs .= "        savingsPercent: " . $plan['pricing']['savings_percent'] . ",\n";
        $pricingDataJs .= "        savingsBadge: '" . addslashes($plan['pricing']['savings_badge']) . "',\n";
    }
    $pricingDataJs .= "        planId: '" . $plan['plan_id'] . "',\n";
    $pricingDataJs .= "        planName: '" . addslashes($plan['name']) . "',\n";
    $pricingDataJs .= "        isAccent: " . ($plan['is_accent'] ? 'true' : 'false') . ",\n";
    $pricingDataJs .= "        isUpgrade: " . ($isUpgrade ? 'true' : 'false') . ",\n";
    $pricingDataJs .= "        isDowngrade: " . ($isDowngrade ? 'true' : 'false') . "\n";
    $pricingDataJs .= "    },\n";
}
$pricingDataJs .= "};\n\n";

// Add current subscription info
$pricingDataJs .= "var currentSubscriptionPlan = " . ($currentPlanKey ? "'" . $currentPlanKey . "'" : "null") . ";\n";
$pricingDataJs .= "var currentSubscriptionBilling = " . ($currentBillingType ? "'" . $currentBillingType . "'" : "null") . ";\n\n";

$defaultBillingJs = $currentBillingType ? $currentBillingType : 'monthly';

$billingScript = new content_block($pricingDataJs . "
var selectedBillingType = '" . $defaultBillingJs . "';

function updateBillingType(billingType) {
    // If user is currently annual, lock to annual (no switching down to monthly)
    if (currentSubscriptionBilling === 'annual') {
        billingType = 'annual';
    }
    
    selectedBillingType = billingType;
    
    // Update each plan card
    for (var planKey in upgradePricingData) {
        if (upgradePricingData.hasOwnProperty(planKey)) {
            var plan = upgradePricingData[planKey];
            var isCurrentPlanAndBilling = (currentSubscriptionBilling !== null && planKey === currentSubscriptionPlan && billingType === currentSubscriptionBilling);
            var isCurrentPlanDifferentBilling = (currentSubscriptionBilling !== null && planKey === currentSubscriptionPlan && billingType !== currentSubscriptionBilling);
            
            // Update price display
            if (plan.hasPrice) {
                var priceEl = document.getElementById('upgrade-price-' + planKey);
                if (priceEl) {
                    priceEl.classList.add('price-updating');
                    priceEl.textContent = plan[billingType];
                    setTimeout(function(el) {
                        return function() {
                            el.classList.remove('price-updating');
                        };
                    }(priceEl), 300);
                }
                
                // Update savings badge
                // var savingsBadge = document.getElementById('upgrade-savings-' + planKey);
                // if (savingsBadge) {
                //     if (billingType === 'annual') {
                //         savingsBadge.textContent = plan.savingsBadge;
                //         savingsBadge.style.display = 'inline-block';
                //     } else {
                //         savingsBadge.style.display = 'none';
                //     }
                // }
            }
            
            // Update card border
            var card = document.getElementById('upgrade-card-' + planKey);
            if (card) {
                if (isCurrentPlanAndBilling) {
                    card.style.border = '2px solid #28a745';
                } else if (plan.isAccent) {
                    card.style.border = '2px solid #ff6600';
                } else {
                    card.style.border = '1px solid #e0e0e0';
                }
            }
            
            // Update badge
            var badgeContainer = document.getElementById('upgrade-badge-' + planKey);
            if (badgeContainer) {
                if (isCurrentPlanAndBilling) {
                    badgeContainer.innerHTML = '<div class=\"plan-badge current-badge\" style=\"position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: #28a745; color: white; padding: 4px 16px; border-radius: 20px; font-size: 12px; font-weight: 600;\">Current Plan</div>';
                } else if (plan.isAccent && plan.isUpgrade) {
                    badgeContainer.innerHTML = '<div class=\"plan-badge recommended-badge\" style=\"position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: #ff6600; color: white; padding: 4px 16px; border-radius: 20px; font-size: 12px; font-weight: 600;\">Recommended</div>';
                } else {
                    badgeContainer.innerHTML = '';
                }
            }
            
            // Update button
            var btnContainer = document.getElementById('upgrade-btn-container-' + planKey);
            if (btnContainer) {
                if (isCurrentPlanAndBilling) {
                    // Exact match - show 'Your Current Plan'
                    btnContainer.innerHTML = '<span style=\"display: inline-block; padding: 12px 24px; background: #e9ecef; color: #666; border-radius: 8px; font-weight: 500;\">Your Current Plan</span>';
                } else if (isCurrentPlanDifferentBilling && plan.hasPrice) {
                    // Same plan, different billing - show 'Switch to Annual/Monthly'
                    var switchText = billingType === 'annual' ? 'Switch to Annual' : 'Switch to Monthly';
                    btnContainer.innerHTML = '<a href=\"/subscription/buy.php?plan=' + plan.planId + '&billing=' + billingType + '\" class=\"primary_button upgrade-btn-' + planKey + '\" style=\"display: inline-block; padding: 12px 24px; width: 100%; text-align: center; box-sizing: border-box;\">' + switchText + '</a>';
                } else if (plan.isDowngrade) {
                    // Downgrade - show contact support
                    btnContainer.innerHTML = '<span style=\"display: inline-block; padding: 12px 24px; background: #f8f9fa; color: #999; border-radius: 8px; font-size: 13px;\">Contact Support to Downgrade</span>';
                } else if (plan.hasPrice) {
                    // Upgrade or new plan
                    var btnText = currentSubscriptionPlan ? 'Upgrade to ' + plan.planName : 'Get Started';
                    btnContainer.innerHTML = '<a href=\"/subscription/buy.php?plan=' + plan.planId + '&billing=' + billingType + '\" class=\"primary_button text-white upgrade-btn-' + planKey + '\" style=\"display: inline-block; padding: 12px 24px; width: 100%; text-align: center; box-sizing: border-box;\">' + btnText + '</a>';
                } else {
                    // Enterprise - Request Quote
                    btnContainer.innerHTML = '<a href=\"https://depodash.com/customequote\" target=\"_blank\" class=\"primary_button text-white\" style=\"display: inline-block; padding: 12px 24px; width: 100%; text-align: center; box-sizing: border-box;\">Request Quote</a>';
                }
            }
        }
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateBillingType('" . $defaultBillingJs . "');
});
", 'script', array('type' => 'text/javascript'));
$set_body->push($billingScript);

// Include mainframe to render the page
require_once('mainframe.php');
?>

