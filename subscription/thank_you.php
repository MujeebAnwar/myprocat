<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once (DOCUMENT_ROOT.'/setup/start.php');

// Set page title
$set_title = "Thank You - DepoDash Resource Center";

// Create the main content body
$set_body = new content_block(NULL, 'div', array('class' => 'inner-content'));

// Thank you card container
$thankYouCard = new content_block(NULL, 'div', array('class' => 'form-box', 'style' => 'max-width: 600px; margin: 60px auto; text-align: center; padding: 40px 30px;'));

// Success icon
$iconWrapper = new content_block(NULL, 'div', array('style' => 'margin-bottom: 24px;'));
$successIcon = new content_block('✓', 'div', array('style' => 'width: 80px; height: 80px; background: #28a745; color: white; font-size: 40px; font-weight: bold; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);'));
$iconWrapper->push($successIcon);
$thankYouCard->push($iconWrapper);

// Thank you heading
$thankYouCard->push(new content_block('Thank You for Your Purchase!', 'h1', array('style' => 'color: #27475f; font-size: 28px; margin-bottom: 16px;')));

// Confirmation message
$thankYouCard->push(new paragraph('Your payment has been processed successfully.', array('style' => 'color: #333; font-size: 16px; margin-bottom: 8px;')));
$thankYouCard->push(new paragraph('A confirmation email with your invoice has been sent to your email address.', array('style' => 'color: #666; font-size: 14px; margin-bottom: 24px;')));

// Order details section (if available)
if (isset($_GET['order_id'])) {
    $orderDetails = new content_block(NULL, 'div', array('style' => 'background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 24px; text-align: left;'));
    $orderDetails->push(new content_block('Invoice Details', 'h3', array('style' => 'color: #27475f; font-size: 16px; margin-bottom: 12px; border-bottom: 1px solid #e9ecef; padding-bottom: 8px;')));
    
    $orderInfo = new content_block(NULL, 'div', array('style' => 'display: flex; justify-content: space-between; margin-bottom: 8px;'));
    $orderInfo->push(new content_block('Invoice Number:', 'span', array('style' => 'color: #666;')));
    $orderInfo->push(new content_block('#' .htmlspecialchars($_GET['order_id']), 'span', array('style' => 'color: #333; font-weight: 600;')));
    $orderDetails->push($orderInfo);
    
    if (isset($_GET['plan'])) {
        $planId = $_GET['plan'];
        $planData = ['name'];
        $DB->sql(
            'SELECT `name` FROM subscription_plans WHERE id = ?',
            array('s', $planId),
            $planData
        );
        $planName = $planData[0]['name'];
        $planInfo = new content_block(NULL, 'div', array('style' => 'display: flex; justify-content: space-between; margin-bottom: 8px;'));
        $planInfo->push(new content_block('Plan:', 'span', array('style' => 'color: #666;')));
        $planInfo->push(new content_block(htmlspecialchars($planName), 'span', array('style' => 'color: #333; font-weight: 600;')));
        $orderDetails->push($planInfo);
    }
    
    if (isset($_GET['amount'])) {
        $amountInfo = new content_block(NULL, 'div', array('style' => 'display: flex; justify-content: space-between;'));
        $amountInfo->push(new content_block('Amount Paid:', 'span', array('style' => 'color: #666;')));
        $amountInfo->push(new content_block('$' . htmlspecialchars($_GET['amount']), 'span', array('style' => 'color: #28a745; font-weight: 600;')));
        $orderDetails->push($amountInfo);
    }
    
    $thankYouCard->push($orderDetails);
}

// What's next section
$nextSteps = new content_block(NULL, 'div', array('style' => 'text-align: left; margin-bottom: 24px;'));
$nextSteps->push(new content_block('What\'s Next?', 'h3', array('style' => 'color: #27475f; font-size: 16px; margin-bottom: 12px;')));

$stepsList = new content_block(NULL, 'ul', array('style' => 'color: #666; font-size: 14px; line-height: 1.8; padding-left: 20px; margin: 0;'));
$stepsList->push(new content_block('Check your email for the invoice and confirmation details.', 'li'));
$stepsList->push(new content_block('Your subscription is now active and ready to use.', 'li'));
$stepsList->push(new content_block('Start using DepoDash for your transcription needs.', 'li'));
$nextSteps->push($stepsList);
$thankYouCard->push($nextSteps);

// Action buttons
$buttonWrapper = new content_block(NULL, 'div', array('style' => 'display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;'));
$buttonWrapper->push(new anchor('Go to Dashboard', array('class' => 'solid_button text-white', 'href' => '/index.php', 'style' => 'padding: 12px 24px; font-size: 14px;')));
$buttonWrapper->push(new anchor('View Invoices', array('class' => 'outline_button', 'href' => '/subscription/invoices.php', 'style' => 'padding: 12px 24px; font-size: 14px;')));
$thankYouCard->push($buttonWrapper);

// Support info
$supportInfo = new content_block(NULL, 'div', array('style' => 'margin-top: 30px; padding-top: 20px; border-top: 1px solid #e9ecef;'));
$supportInfo->push(new paragraph('Need help? Contact our support team', array('style' => 'color: #666; font-size: 13px; margin-bottom: 6px;')));
$supportInfo->push(new anchor('support@depodash.com', array('href' => 'mailto:support@depodash.com', 'style' => 'color: #ff6600; text-decoration: none; font-size: 14px;')));
$thankYouCard->push($supportInfo);

$set_body->push($thankYouCard);

// Add custom styles for the checkmark animation
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

@keyframes checkmarkPop {
    0% {
        transform: scale(0);
    }
    50% {
        transform: scale(1.2);
    }
    100% {
        transform: scale(1);
    }
}

.form-box > div:first-child > div {
    animation: checkmarkPop 0.6s ease-out 0.2s both;
}
', 'style');
$set_body->push($customStyles);

// Include mainframe to render the page
require_once('mainframe.php');
?>

