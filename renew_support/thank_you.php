<?php
require_once __DIR__ . '/../config.php';
require_once DOCUMENT_ROOT . '/setup/start.php';
require_once DOCUMENT_ROOT . '/template/Master.php';

$set_title = 'Thank You - Renew Support';
$sidebar_title = 'Renew Support';

$page_banner = new content_block(NULL, 'div', array('class' => 'banner'));
$page_banner->push(new content_block('Thank You', 'h1'));

$set_body = new content_block(NULL, 'div', array('class' => 'inner-content'));

$thankYouCard = new content_block(NULL, 'div', array(
	'class' => 'form-box',
	'style' => 'max-width: 600px; margin: 60px auto; text-align: center; padding: 40px 30px;',
));

$iconWrapper = new content_block(NULL, 'div', array('style' => 'margin-bottom: 24px;'));
$iconWrapper->push(new content_block('✓', 'div', array(
	'style' => 'width: 80px; height: 80px; background: #28a745; color: white; font-size: 40px; font-weight: bold; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;',
)));
$thankYouCard->push($iconWrapper);

$thankYouCard->push(new content_block('Renewal Successful!', 'h1', array(
	'style' => 'color: #27475f; font-size: 28px; margin-bottom: 16px;',
)));
$thankYouCard->push(new paragraph(
	'Your payment has been processed and your license renewal has been applied.',
	array('style' => 'color: #333; font-size: 16px; margin-bottom: 8px;')
));
$thankYouCard->push(new paragraph(
	'A confirmation email with your invoice has been sent to your email address.',
	array('style' => 'color: #666; font-size: 14px; margin-bottom: 24px;')
));

if (isset($_GET['order_id'])) {
	$orderDetails = new content_block(NULL, 'div', array(
		'style' => 'background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 24px; text-align: left;',
	));
	$orderDetails->push(new content_block('Invoice Details', 'h3', array(
		'style' => 'color: #27475f; font-size: 16px; margin-bottom: 12px; border-bottom: 1px solid #e9ecef; padding-bottom: 8px;',
	)));

	$row = new content_block(NULL, 'div', array('style' => 'display: flex; justify-content: space-between; margin-bottom: 8px;'));
	$row->push(new content_block('Invoice Number:', 'span', array('style' => 'color: #666;')));
	$row->push(new content_block('#' . htmlspecialchars($_GET['order_id'], ENT_QUOTES), 'span', array('style' => 'color: #333; font-weight: 600;')));
	$orderDetails->push($row);

	if (isset($_GET['plan'])) {
		$planRow = new content_block(NULL, 'div', array('style' => 'display: flex; justify-content: space-between; margin-bottom: 8px;'));
		$planRow->push(new content_block('Package:', 'span', array('style' => 'color: #666;')));
		$planRow->push(new content_block(htmlspecialchars($_GET['plan'], ENT_QUOTES), 'span', array('style' => 'color: #333; font-weight: 600;')));
		$orderDetails->push($planRow);
	}

	if (isset($_GET['tier'])) {
		$tierRow = new content_block(NULL, 'div', array('style' => 'display: flex; justify-content: space-between; margin-bottom: 8px;'));
		$tierRow->push(new content_block('Tier:', 'span', array('style' => 'color: #666;')));
		$tierRow->push(new content_block(htmlspecialchars(ucfirst($_GET['tier']), ENT_QUOTES), 'span', array('style' => 'color: #333; font-weight: 600;')));
		$orderDetails->push($tierRow);
	}

	if (isset($_GET['amount'])) {
		$amountRow = new content_block(NULL, 'div', array('style' => 'display: flex; justify-content: space-between;'));
		$amountRow->push(new content_block('Amount Paid:', 'span', array('style' => 'color: #666;')));
		$amountRow->push(new content_block('$' . htmlspecialchars($_GET['amount'], ENT_QUOTES), 'span', array('style' => 'color: #28a745; font-weight: 600;')));
		$orderDetails->push($amountRow);
	}

	$thankYouCard->push($orderDetails);
}

$buttonWrapper = new content_block(NULL, 'div', array(
	'style' => 'display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;',
));
$buttonWrapper->push(new anchor('Back to Renew Support', array(
	'class' => 'primary_button text-white',
	'href' => '/renew_support/',
	'style' => 'padding: 12px 24px; font-size: 14px;',
)));
$buttonWrapper->push(new anchor('View Invoices', array(
	'class' => 'secondary_button',
	'href' => '/invoices/',
	'style' => 'padding: 12px 24px; font-size: 14px;',
)));
$thankYouCard->push($buttonWrapper);

$set_body->push($thankYouCard);

$breadcrumb_items = array(
	array('text' => 'Home', 'url' => '/resources.php'),
	array('text' => 'Renew Support', 'url' => '/renew_support/'),
	array('text' => 'Thank You', 'url' => '#'),
);

$sidebar_title = '';
$sidebar_logo = '/renew_support/img/support.png';
$sidebar_logo_text = '';
require_once DOCUMENT_ROOT . '/templateV2/mainframe/mainframe.php';
?>
