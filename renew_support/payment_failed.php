<?php
require_once __DIR__ . '/../config.php';
require_once DOCUMENT_ROOT . '/setup/start.php';
require_once DOCUMENT_ROOT . '/template/Master.php';

$set_title = 'Payment Failed - Renew Support';
$sidebar_title = 'Renew Support';

$page_banner = new content_block(NULL, 'div', array('class' => 'banner'));
$page_banner->push(new content_block('Payment Failed', 'h1'));

$set_body = new content_block(NULL, 'div', array('class' => 'inner-content'));

$card = new content_block(NULL, 'div', array(
	'class' => 'form-box',
	'style' => 'max-width: 600px; margin: 60px auto; text-align: center; padding: 40px 30px;',
));

$card->push(new content_block('Payment could not be completed', 'h1', array(
	'style' => 'color: #27475f; font-size: 26px; margin-bottom: 16px;',
)));
$card->push(new paragraph(
	'Your card was not charged, or the payment gateway returned an error. Please try again or contact support.',
	array('style' => 'color: #666; font-size: 14px; margin-bottom: 24px;')
));

$buttons = new content_block(NULL, 'div', array(
	'style' => 'display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;',
));
$buttons->push(new anchor('Try Again', array(
	'class' => 'primary_button text-white',
	'href' => '/renew_support/',
	'style' => 'padding: 12px 24px; font-size: 14px;',
)));
$buttons->push(new anchor('Contact Support', array(
	'class' => 'secondary_button',
	'href' => '/contact.php',
	'style' => 'padding: 12px 24px; font-size: 14px;',
)));
$card->push($buttons);

$set_body->push($card);

$breadcrumb_items = array(
	array('text' => 'Home', 'url' => '/resources.php'),
	array('text' => 'Renew Support', 'url' => '/renew_support/'),
	array('text' => 'Payment Failed', 'url' => '#'),
);

$sidebar_title = 'MyProCAT';
$sidebar_logo = '/renew_support/img/support.png';
$sidebar_logo_text = 'MyProCAT Support Center';
require_once DOCUMENT_ROOT . '/templateV2/mainframe/mainframe.php';
?>
