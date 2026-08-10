<?php
require_once __DIR__ . '/../config.php';
require_once DOCUMENT_ROOT . '/setup/start.php';
require_once DOCUMENT_ROOT . '/template/Master.php';

$set_title = 'Payment Failed - MyProCAT Subscription';

$page_banner = new content_block(NULL, 'div', array('class' => 'banner'));
$page_banner->push(new content_block('Payment Failed', 'h1'));

$set_body = new content_block(NULL, 'div', array('class' => 'inner-content'));

$failedCard = new content_block(NULL, 'div', array('class' => 'form-box', 'style' => 'max-width: 500px; margin: 80px auto; text-align: center; padding: 50px 40px;'));

$iconWrapper = new content_block(NULL, 'div', array('style' => 'margin-bottom: 24px;'));
$iconWrapper->push(new content_block('✕', 'div', array('style' => 'width: 80px; height: 80px; background: #dc3545; color: white; font-size: 40px; font-weight: bold; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto; box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);')));
$failedCard->push($iconWrapper);

$failedCard->push(new content_block('Payment Failed', 'h1', array('style' => 'color: #dc3545; font-size: 28px; margin-bottom: 16px;')));
$failedCard->push(new paragraph('Your payment could not be processed. Please try again or contact support.', array('style' => 'color: #666; font-size: 16px; margin-bottom: 32px;')));

$buttonWrapper = new content_block(NULL, 'div', array('style' => 'display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;'));
$buttonWrapper->push(new anchor('Try Again', array('class' => 'primary_button text-white', 'href' => '/store/buy.php', 'style' => 'padding: 12px 24px; font-size: 14px;')));
$failedCard->push($buttonWrapper);

$set_body->push($failedCard);

$breadcrumb_items = array(
	array('text' => 'Home', 'url' => '/resources.php'),
	array('text' => 'Store', 'url' => '/store/'),
	array('text' => 'Payment Failed', 'url' => '#'),
);

$sidebar_title = 'MyProCAT';
$sidebar_logo = '/store/img/buy.png';
$sidebar_logo_text = 'MyProCAT Buy Platform Time';
require_once DOCUMENT_ROOT . '/templateV2/mainframe/mainframe.php';
?>
