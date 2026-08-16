<?php
require_once __DIR__ . '/../config.php';
require_once DOCUMENT_ROOT . '/setup/start.php';
require_once DOCUMENT_ROOT . '/template/Master.php';

$set_title = 'Thank You - MyProCAT Subscription';

$page_banner = new content_block(NULL, 'div', array('class' => 'banner'));
$page_banner->push(new content_block('Thank You', 'h1'));

$set_body = new content_block(NULL, 'div', array('class' => 'inner-content'));

$thankYouCard = new content_block(NULL, 'div', array('class' => 'form-box', 'style' => 'max-width: 600px; margin: 60px auto; text-align: center; padding: 40px 30px;'));

$iconWrapper = new content_block(NULL, 'div', array('style' => 'margin-bottom: 24px;'));
$iconWrapper->push(new content_block('✓', 'div', array('style' => 'width: 80px; height: 80px; background: #28a745; color: white; font-size: 40px; font-weight: bold; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);')));
$thankYouCard->push($iconWrapper);

$thankYouCard->push(new content_block('Thank You for Your Purchase!', 'h1', array('style' => 'color: #27475f; font-size: 28px; margin-bottom: 16px;')));
$thankYouCard->push(new paragraph('Your payment has been processed successfully.', array('style' => 'color: #333; font-size: 16px; margin-bottom: 8px;')));
$thankYouCard->push(new paragraph('A confirmation email with your invoice has been sent to your email address.', array('style' => 'color: #666; font-size: 14px; margin-bottom: 24px;')));

if (isset($_GET['order_id'])) {
	$orderDetails = new content_block(NULL, 'div', array('style' => 'background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 24px; text-align: left;'));
	$orderDetails->push(new content_block('Invoice Details', 'h3', array('style' => 'color: #27475f; font-size: 16px; margin-bottom: 12px; border-bottom: 1px solid #e9ecef; padding-bottom: 8px;')));

	$orderInfo = new content_block(NULL, 'div', array('style' => 'display: flex; justify-content: space-between; margin-bottom: 8px;'));
	$orderInfo->push(new content_block('Invoice Number:', 'span', array('style' => 'color: #666;')));
	$orderInfo->push(new content_block('#' . htmlspecialchars($_GET['order_id'], ENT_QUOTES), 'span', array('style' => 'color: #333; font-weight: 600;')));
	$orderDetails->push($orderInfo);

	if (isset($_GET['license'])) {
		$planInfo = new content_block(NULL, 'div', array('style' => 'display: flex; justify-content: space-between; margin-bottom: 8px;'));
		$planInfo->push(new content_block('License:', 'span', array('style' => 'color: #666;')));
		$planInfo->push(new content_block(htmlspecialchars($_GET['license'], ENT_QUOTES), 'span', array('style' => 'color: #333; font-weight: 600;')));
		$orderDetails->push($planInfo);
	}

	if (isset($_GET['amount'])) {
		$amountInfo = new content_block(NULL, 'div', array('style' => 'display: flex; justify-content: space-between;'));
		$amountInfo->push(new content_block('Amount Paid:', 'span', array('style' => 'color: #666;')));
		$amountInfo->push(new content_block('$' . htmlspecialchars($_GET['amount'], ENT_QUOTES), 'span', array('style' => 'color: #28a745; font-weight: 600;')));
		$orderDetails->push($amountInfo);
	}

	$thankYouCard->push($orderDetails);
}

$nextSteps = new content_block(NULL, 'div', array('style' => 'text-align: left; margin-bottom: 24px;'));
$nextSteps->push(new content_block('What\'s Next?', 'h3', array('style' => 'color: #27475f; font-size: 16px; margin-bottom: 12px;')));
$stepsList = new content_block(NULL, 'ul', array('style' => 'color: #666; font-size: 14px; line-height: 1.8; padding-left: 20px; margin: 0;'));
$stepsList->push(new content_block('Check your email for the invoice and confirmation details.', 'li'));
$stepsList->push(new content_block('Your MyProCAT license is now active and ready to use.', 'li'));
$stepsList->push(new content_block('Start using ProCAT for your transcription needs.', 'li'));
$nextSteps->push($stepsList);
$thankYouCard->push($nextSteps);

$buttonWrapper = new content_block(NULL, 'div', array('style' => 'display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;'));
$buttonWrapper->push(new anchor('Back to Store', array('class' => 'primary_button text-white', 'href' => '/store/buy.php', 'style' => 'padding: 12px 24px; font-size: 14px;')));
$thankYouCard->push($buttonWrapper);

$set_body->push($thankYouCard);

$breadcrumb_items = array(
	array('text' => 'Home', 'url' => '/resources.php'),
	array('text' => 'Store', 'url' => '/store/'),
	array('text' => 'Thank You', 'url' => '#'),
);

$sidebar_title = '';
$sidebar_logo = '/store/img/buy.png';
$sidebar_logo_text = '';
require_once DOCUMENT_ROOT . '/templateV2/mainframe/mainframe.php';
?>
