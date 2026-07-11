<?php
// Include required files first
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once ('plans_config.php');

// Set page title
$set_title = "Subscription Options - DepoDash Resource Center";

// Create the main content body
$set_body = new content_block(NULL, 'div', array('class' => 'inner-content'));

// Banner section
$banner = new content_block(NULL, 'div', array('class' => 'banner'));
$banner->push(new content_block('Subscription Options', 'h1', array('style' => 'text-align: center; margin:5px 0px;')));
$set_body->push($banner);

// Section heading
$sectionHeading = new content_block('Flexible Plans for Modern Legal Proceedings', 'h2', array('class' => 'section-heading', 'style' => 'text-align:center;margin-top:30px;'));
$set_body->push($sectionHeading);

// Description paragraphs
$desc1 = new paragraph(
'From independent reporters to enterprise agencies and court systems, DepoDash offers secure, scalable transcription for every legal environment — in person, remote, or hybrid.',
array('style' => 'text-align:center; max-width: 48rem; margin: 0 auto 6px auto;')
);
$set_body->push($desc1);

$desc2 = new paragraph(
'<strong>All plans include</strong>: secure audio capture, voice‑to‑text real‑time transcription, speaker diarization, post‑production and automatic formatting.',
array('style' => 'text-align:center; max-width: 48rem; margin: 0 auto 24px auto;')
);
$set_body->push($desc2);

// Billing toggle with script to update links
$billingToggle = new content_block(NULL, 'div', array('class' => 'billing-toggle', 'style' => 'margin-bottom:20px;'));
$toggleContainer = new content_block(NULL, 'div', array('class' => 'container'));
$tabs = new content_block(NULL, 'div', array('class' => 'tabs'));

$tabs->push(new content_block(NULL, 'input', array('checked' => 'checked', 'name' => 'tabs', 'id' => 'radio-1', 'type' => 'radio', 'onchange' => 'updateBillingType("monthly")')));
$tabs->push(new content_block('Monthly', 'label', array('for' => 'radio-1', 'class' => 'tab')));
$tabs->push(new content_block(NULL, 'input', array('name' => 'tabs', 'id' => 'radio-2', 'type' => 'radio', 'onchange' => 'updateBillingType("annual")')));

$annualLabel = new content_block('Annual', 'label', array('for' => 'radio-2', 'class' => 'tab'));
// $annualLabel->push(new content_block('SAVE UP TO 7%', 'span', array('class' => 'notification', 'id' => 'savingsNotification')));
$tabs->push($annualLabel);

$tabs->push(new content_block(NULL, 'span', array('class' => 'glider')));
$toggleContainer->push($tabs);
$billingToggle->push($toggleContainer);
$set_body->push($billingToggle);

// Pricing table container
$formBox = new content_block(NULL, 'div', array('class' => 'form-box', 'style' => 'max-width: 100dvw; overflow-x: auto;'));
$pricingScroll = new content_block(NULL, 'div', array('class' => 'pricing-scroll'));
$table = new content_block(NULL, 'table', array('class' => 'pricing-table', 'style' => 'border-collapse:collapse;'));

// Table header - dynamically generated from config
$thead = new content_block(NULL, 'thead');
$headerRow = new content_block(NULL, 'tr');
$headerRow->push(new content_block('', 'th'));
foreach ($plans_config['plans'] as $planKey => $plan) {
$headerAttrs = array();
if ($plan['is_accent']) {
    $headerAttrs['class'] = 'col-accent';
}
$headerRow->push(new content_block($plan['name'], 'th', $headerAttrs));
}
$thead->push($headerRow);
$table->push($thead);

// Table body - dynamically generated from config
$tbody = new content_block(NULL, 'tbody');

// Generate rows dynamically from feature_rows config
foreach ($plans_config['feature_rows'] as $featureRow) {
$row = new content_block(NULL, 'tr');
$row->push(new content_block($featureRow['label'], 'td', array('class' => 'row-title')));

foreach ($plans_config['plans'] as $planKey => $plan) {
    $cellAttrs = array();
    if ($plan['is_accent']) {
        $cellAttrs['class'] = 'col-accent';
    }
    
    // Handle special case for price row
    if ($featureRow['key'] === 'price') {
        if ($plan['has_price']) {
            $priceCellClass = 'price';
            if (isset($cellAttrs['class'])) {
                $priceCellClass .= ' ' . $cellAttrs['class'];
            }
            $priceCell = new content_block(NULL, 'td', array('class' => $priceCellClass));
            $priceWrapper = new content_block(NULL, 'div', array('style' => 'display: flex; align-items: center; gap: 8px; justify-content: center;'));
            $priceWrapper->push(new content_block($plan['pricing']['monthly'], 'span', array('id' => $plan['price_id'])));
            $savingsBadge = new content_block($plan['pricing']['savings_badge'], 'span', array('id' => $plan['savings_badge_id'], 'class' => 'savings-badge', 'style' => 'display: none; background: #28a745; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; white-space: nowrap;'));
            // $priceWrapper->push($savingsBadge);
            $priceCell->push($priceWrapper);
            $row->push($priceCell);
        } else {
            $cellAttrs['class'] = (isset($cellAttrs['class']) ? $cellAttrs['class'] . ' ' : '') . 'muted';
            $row->push(new content_block($plan['pricing']['monthly'], 'td', $cellAttrs));
        }
    } else {
        // Get feature value from plan
        $featureKey = $featureRow['key'];
        $featureValue = isset($plan['features'][$featureKey]) ? $plan['features'][$featureKey] : '';
        
        // Handle different feature types
        if ($featureRow['type'] === 'check') {
            if ($featureValue === 'check') {
                $cellAttrs['class'] = (isset($cellAttrs['class']) ? $cellAttrs['class'] . ' ' : '') . 'check';
                $row->push(new content_block('✔', 'td', $cellAttrs));
            } else {
                $cellAttrs['class'] = (isset($cellAttrs['class']) ? $cellAttrs['class'] . ' ' : '') . 'dash';
                $row->push(new content_block('—', 'td', $cellAttrs));
            }
        } else {
            // Text type - check if it's a special field
            if ($featureKey === 'ideal_for') {
                $featureValue = $plan['ideal_for'];
            } elseif ($featureKey === 'users') {
                $featureValue = $plan['users'];
            } elseif ($featureKey === 'minimum_usage') {
                $featureValue = $plan['minimum_usage'];
            }
            
            // Add muted class for certain values
            if (in_array($featureValue, array('Available (costs apply)', 'Contact sales for pricing')) || 
                strpos($featureValue, 'Manual') !== false || 
                strpos($featureValue, 'Programmable') !== false) {
                $cellAttrs['class'] = (isset($cellAttrs['class']) ? $cellAttrs['class'] . ' ' : '') . 'muted';
            }
            
            $row->push(new content_block($featureValue, 'td', $cellAttrs));
        }
    }
}

$tbody->push($row);
}

// CTA row - dynamically generated from config
$rowCTA = new content_block(NULL, 'tr', array('class' => 'cta-row'));
$rowCTA->push(new content_block('', 'td'));
foreach ($plans_config['plans'] as $planKey => $plan) {
$tdAttrs = array();
if ($plan['is_accent']) {
    $tdAttrs['class'] = 'col-accent';
}
$td = new content_block(NULL, 'td', $tdAttrs);

// Build button href
$target = '';
if ($plan['has_price']) {
    $href = '/subscription/purchase.php?plan=' . $plan['id'] . '&billing=monthly';
    $buttonClass = $plan['button_type'] . '_button text-white ' . $plan['button_class'];
} else {
    $href = isset($plan['button_href']) ? $plan['button_href'] : '#';
    $buttonClass = $plan['button_type'] . '_button';
    $target = '_blank';
}

$td->push(new anchor($plan['button_text'], array('class' => $buttonClass, 'href' => $href, 'target' => $target)));
$rowCTA->push($td);
}
$tbody->push($rowCTA);

$table->push($tbody);
$pricingScroll->push($table);
$formBox->push($pricingScroll);
$set_body->push($formBox);

// Mobile Cards View - dynamically generated from config
$pricingCards = new content_block(NULL, 'div', array('class' => 'pricing-cards'));

foreach ($plans_config['plans'] as $planKey => $plan) {
$card = new content_block(NULL, 'div', array('class' => 'pricing-card' . ($plan['is_accent'] ? ' accent' : '')));

// Plan title
$card->push(new content_block($plan['name'], 'h3', array('class' => 'plan-title')));

// Plan subtitle
$card->push(new paragraph($plan['ideal_for'], array('class' => 'plan-sub')));

// Plan meta (users • minimum usage)
$card->push(new paragraph($plan['users'] . ' • ' . $plan['minimum_usage'], array('class' => 'plan-meta')));

// Price section
if ($plan['has_price']) {
    $priceContainer = new content_block(NULL, 'div', array('style' => 'display: flex; align-items: center; gap: 8px; justify-content: center; flex-wrap: wrap;'));
    $cardPrice = new content_block($plan['pricing']['monthly'] . ' / hr', 'span', array('id' => $plan['card_price_id'], 'class' => 'plan-price price', 'style' => 'margin: 0;'));
    $priceContainer->push($cardPrice);
    $cardSavingsBadge = new content_block($plan['pricing']['savings_badge'], 'span', array('id' => $plan['card_savings_badge_id'], 'class' => 'savings-badge', 'style' => 'display: none; background: #28a745; color: white; padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; white-space: nowrap;'));
    $priceContainer->push($cardSavingsBadge);
    $card->push($priceContainer);
} else {
    $card->push(new paragraph($plan['pricing']['monthly'], array('class' => 'plan-price muted')));
}

// Features list
$features = new content_block(NULL, 'ul', array('class' => 'plan-features'));
foreach ($plan['card_features'] as $feature) {
    $features->push(new content_block($feature, 'li'));
}
$card->push($features);

// CTA button
if ($plan['has_price']) {
    $href = '/subscription/purchase.php?plan=' . $plan['id'] . '&billing=monthly';
    $buttonClass = $plan['button_type'] . '_button text-white ' . $plan['button_class'];
} else {
    $href = isset($plan['button_href']) ? $plan['button_href'] : '#';
    $buttonClass = $plan['button_type'] . '_button';
}
$card->push(new anchor($plan['button_text'], array('class' => $buttonClass, 'href' => $href)));

$pricingCards->push($card);
}

$set_body->push($pricingCards);

// Cold storage info
$coldStorageBox = new content_block(NULL, 'div', array('class' => 'form-box', 'style' => 'max-width: 60rem; margin: 24px auto 0 auto;'));
$coldStorageBox->push(new content_block('COLD STORAGE (Optional Add‑On)', 'h3', array('style' => 'margin-bottom:10px;')));
$coldStorageBox->push(new paragraph('Need to retain files beyond standard retention? DepoDash offers secure, encrypted cold storage for long‑term archiving — ideal for compliance, recordkeeping, or court‑mandated retention.'));
$coldStorageBox->push(new paragraph('Contact sales for details and pricing.', array('style' => 'margin-top:10px;')));
$coldStorageBox->push(new paragraph('*See File Retention Policy and Retrieval Policy', array('style' => 'margin-top:10px; font-size:12px; color:#666;')));
$set_body->push($coldStorageBox);

// Add CSS for price transition animations - dynamically generated from config
$priceIds = array();
foreach ($plans_config['plans'] as $planKey => $plan) {
if ($plan['has_price']) {
    $priceIds[] = '#' . $plan['price_id'];
    $priceIds[] = '#' . $plan['card_price_id'];
}
}
$priceIdsCss = implode(', ', $priceIds);

$priceAnimationStyle = new content_block("
" . $priceIdsCss . " {
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
", 'style');
$set_body->push($priceAnimationStyle);

// Add JavaScript for billing type toggle - dynamically generated from config
$pricingDataJs = "var pricingData = {\n";
$maxSavingsPercent = 0;
foreach ($plans_config['plans'] as $planKey => $plan) {
if ($plan['has_price']) {
    $pricingDataJs .= "    " . $plan['id'] . ": {\n";
    $pricingDataJs .= "        monthly: '" . addslashes($plan['pricing']['monthly']) . "',\n";
    $pricingDataJs .= "        annual: '" . addslashes($plan['pricing']['annual']) . "',\n";
    $pricingDataJs .= "        savingsPercent: " . $plan['pricing']['savings_percent'] . ",\n";
    $pricingDataJs .= "        savingsBadge: '" . addslashes($plan['pricing']['savings_badge']) . "',\n";
    $pricingDataJs .= "        priceId: '" . $plan['price_id'] . "',\n";
    $pricingDataJs .= "        savingsBadgeId: '" . $plan['savings_badge_id'] . "',\n";
    $pricingDataJs .= "        cardPriceId: '" . $plan['card_price_id'] . "',\n";
    $pricingDataJs .= "        cardSavingsBadgeId: '" . $plan['card_savings_badge_id'] . "',\n";
    $pricingDataJs .= "        buttonClass: '" . $plan['button_class'] . "',\n";
    $pricingDataJs .= "        planId: '" . $plan['plan_id'] . "',\n";
    $pricingDataJs .= "        planName: '" . addslashes($plan['name']) . "',\n";
    $cleanDescription = trim(preg_replace('/\s+/', ' ', $plan['ideal_for'])); // Clean up newlines
    $pricingDataJs .= "        description: '" . addslashes($cleanDescription) . "'\n";
    $pricingDataJs .= "    },\n";
    
    if ($plan['pricing']['savings_percent'] > $maxSavingsPercent) {
        $maxSavingsPercent = $plan['pricing']['savings_percent'];
    }
}
}
$pricingDataJs .= "};\n\n";
$pricingDataJs .= "var maxSavingsPercent = " . $maxSavingsPercent . ";\n\n";

$billingScript = new content_block($pricingDataJs . "
function updateBillingType(billingType) {
    // Update savings notification
    updateSavingsNotification(billingType);
    
    // Update pricing displays
    updatePricing(billingType);
    
    // Update all buy buttons dynamically
    for (var planKey in pricingData) {
        if (pricingData.hasOwnProperty(planKey)) {
            var plan = pricingData[planKey];
            var buttons = document.querySelectorAll('.' + plan.buttonClass);
            buttons.forEach(function(btn) {
                var url = '/subscription/purchase.php?plan=' + plan.planId + '&billing=' + billingType;
                btn.href = url;
            });
        }
    }
}

function updateSavingsNotification(billingType) {
    var notification = document.getElementById('savingsNotification');
    if (notification) {
        notification.textContent = 'SAVE UP TO ' + maxSavingsPercent + '%';
    }
}

function updatePricing(billingType) {
    // Update all pricing elements dynamically
    for (var planKey in pricingData) {
        if (pricingData.hasOwnProperty(planKey)) {
            var plan = pricingData[planKey];
            
            // Update table price
            var tablePriceEl = document.getElementById(plan.priceId);
            if (tablePriceEl) {
                tablePriceEl.classList.add('price-updating');
                tablePriceEl.textContent = plan[billingType];
                setTimeout(function() {
                    tablePriceEl.classList.remove('price-updating');
                }, 300);
            }
            
            // Update card price
            var cardPriceEl = document.getElementById(plan.cardPriceId);
            if (cardPriceEl) {
                cardPriceEl.classList.add('price-updating');
                cardPriceEl.textContent = plan[billingType] + ' / hr';
                setTimeout(function() {
                    cardPriceEl.classList.remove('price-updating');
                }, 300);
            }
            
            // Update savings badges
            var badgeUpdates = [
                { id: plan.savingsBadgeId, text: plan.savingsBadge },
                { id: plan.cardSavingsBadgeId, text: plan.savingsBadge }
            ];
            
            badgeUpdates.forEach(function(item) {
                var badge = document.getElementById(item.id);
                if (badge) {
                    if (billingType === 'annual') {
                        badge.textContent = item.text;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            });
        }
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Set initial billing type to monthly
    updateBillingType('monthly');
});
", 'script', array('type' => 'text/javascript'));
$set_body->push($billingScript);

// Include mainframe to render the page
require_once('mainframe.php');
?>

