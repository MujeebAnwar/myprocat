<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once (DOCUMENT_ROOT.'/setup/start.php');

// Set page title
$set_title = "Payment Failed - DepoDash Resource Center";

// Create the main content body
$set_body = new content_block(NULL, 'div', array('class' => 'inner-content'));

// Failed payment card container
$failedCard = new content_block(NULL, 'div', array('class' => 'form-box', 'style' => 'max-width: 500px; margin: 80px auto; text-align: center; padding: 50px 40px;'));

// Error icon
$iconWrapper = new content_block(NULL, 'div', array('style' => 'margin-bottom: 24px;'));
$errorIcon = new content_block('✕', 'div', array('style' => 'width: 80px; height: 80px; background: #dc3545; color: white; font-size: 40px; font-weight: bold; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto; box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);'));
$iconWrapper->push($errorIcon);
$failedCard->push($iconWrapper);

// Failed heading
$failedCard->push(new content_block('Payment Failed', 'h1', array('style' => 'color: #dc3545; font-size: 28px; margin-bottom: 16px;')));

// Simple static message
$failedCard->push(new paragraph('Your payment could not be processed. Please try again or contact support.', array('style' => 'color: #666; font-size: 16px; margin-bottom: 32px;')));

// Action buttons
$buttonWrapper = new content_block(NULL, 'div', array('style' => 'display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;'));
// $buttonWrapper->push(new anchor('Try Again', array('class' => 'solid_button text-white', 'href' => '/subscription/subscription.php', 'style' => 'padding: 12px 24px; font-size: 14px;')));
$buttonWrapper->push(new anchor('Try Again', array('class' => 'outline_button', 'href' => '/subscription/payment.php', 'style' => 'padding: 12px 24px; font-size: 14px;')));
$failedCard->push($buttonWrapper);

$set_body->push($failedCard);

// Add custom styles
$customStyles = new content_block('
.form-box {
    animation: fadeInUp 0.5s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes errorPop {
    0% { transform: scale(0); }
    50% { transform: scale(1.2); }
    100% { transform: scale(1); }
}

.form-box > div:first-child > div {
    animation: errorPop 0.5s ease-out 0.2s both;
}
', 'style');
$set_body->push($customStyles);

// Include mainframe to render the page
require_once('mainframe.php');
?>
