<?php
require_once ('config.php');
require_once DOCUMENT_ROOT.'/setup/start.php';
require_once DOCUMENT_ROOT.'/template/Master.php';
require_once DOCUMENT_ROOT.'/Service/EmailService.php';

if(is_null($UserAccount) || !is_a($UserAccount, 'useraccount') || !$UserAccount->logged_in)
{
	header('Location: /signup/login.php');
	exit;
}

$first_name = isset($UserAccount->user_details['first_name']) ? $UserAccount->user_details['first_name'] : '';
$last_name = isset($UserAccount->user_details['last_name']) ? $UserAccount->user_details['last_name'] : '';
$email = isset($UserAccount->user_details['email']) ? $UserAccount->user_details['email'] : '';
$callback_number = '';
$product = '';
$message = '';
$form_error = '';
$form_success = '';

$products = array();
$id_user = isset($UserAccount->user_details['id_user']) ? $UserAccount->user_details['id_user'] : '';

if($id_user !== '' && isset($DB) && !is_null($DB))
{
	// Prefer cell, then business, then home from phone_records.
	$phoneRows = array('phone_number', 'phone_type');
	$DB->sql(
		'SELECT phone_number, phone_type
		 FROM phone_records
		 WHERE id_user = ?
		 ORDER BY FIELD(phone_type, \'cell\', \'business\', \'home\'), phone_type ASC
		 LIMIT 1',
		array('s', $id_user),
		$phoneRows
	);
	if(!empty($phoneRows[0]['phone_number']) && $phoneRows[0]['phone_number'] !== 'phone_number')
	{
		$callback_number = $phoneRows[0]['phone_number'];
	}

	$roomRows = array('id_room', 'room_title', 'vis_room_title');
	$DB->sql(
		'SELECT id_room, room_title, vis_room_title
		 FROM rooms
		 ORDER BY `order` ASC, room_title ASC',
		array(''),
		$roomRows
	);
	foreach($roomRows as $row)
	{
		if(!is_array($row) || !isset($row['id_room']) || $row['id_room'] === 'id_room')
		{
			continue;
		}
		$label = !empty($row['vis_room_title']) ? $row['vis_room_title'] : $row['room_title'];
		$products[] = array(
			'id_room' => $row['id_room'],
			'room_title' => $row['room_title'],
			'label' => $label,
		);
	}
}

if($_SERVER['REQUEST_METHOD'] === 'POST')
{
	$first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
	$last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
	$email = isset($_POST['email']) ? trim($_POST['email']) : '';
	$callback_number = isset($_POST['callback_number']) ? trim($_POST['callback_number']) : '';
	$product = isset($_POST['product']) ? trim($_POST['product']) : '';
	$message = isset($_POST['message']) ? trim($_POST['message']) : '';

	$validProduct = false;
	$productLabel = $product;
	foreach($products as $item)
	{
		if((string)$item['id_room'] === (string)$product)
		{
			$validProduct = true;
			$productLabel = $item['label'];
			break;
		}
	}

	if($first_name === '' || $last_name === '' || $email === '' || $callback_number === '' || $product === '' || $message === '')
	{
		$form_error = 'Please fill in all fields.';
	}
	else if(!filter_var($email, FILTER_VALIDATE_EMAIL))
	{
		$form_error = 'Please enter a valid email address.';
	}
	else if(!$validProduct)
	{
		$form_error = 'Please select a valid product.';
	}
	else
	{
		$safeFirst = htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8');
		$safeLast = htmlspecialchars($last_name, ENT_QUOTES, 'UTF-8');
		$safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
		$safeCallback = htmlspecialchars($callback_number, ENT_QUOTES, 'UTF-8');
		$safeProduct = htmlspecialchars($productLabel, ENT_QUOTES, 'UTF-8');
		$safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
		$userId = htmlspecialchars($id_user, ENT_QUOTES, 'UTF-8');

		$emailBody = <<<HTML
<div style="font-family: Arial, sans-serif; font-size: 14px; color: #333;">
	<h2 style="color: #27475f;">Contact Us Message</h2>
	<p><strong>First Name:</strong> {$safeFirst}</p>
	<p><strong>Last Name:</strong> {$safeLast}</p>
	<p><strong>Email:</strong> {$safeEmail}</p>
	<p><strong>Call Back Number:</strong> {$safeCallback}</p>
	<p><strong>Product:</strong> {$safeProduct}</p>
	<p><strong>User ID:</strong> {$userId}</p>
	<p><strong>Message:</strong></p>
	<p>{$safeMessage}</p>
</div>
HTML;

		$mail = new EmailService();
		$result = $mail->send(
			'support@procat.com',
			'MyProCAT Contact Us - '.$first_name.' '.$last_name,
			$emailBody,
			false
		);

		if($result === true)
		{
			$form_success = 'Thank you! Your message has been sent.';
			$message = '';
			$product = '';
		}
		else
		{
			$form_error = 'Unable to send your message right now. Please try again later.';
		}
	}
}

$first_name_value = htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8');
$last_name_value = htmlspecialchars($last_name, ENT_QUOTES, 'UTF-8');
$email_value = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$callback_number_value = htmlspecialchars($callback_number, ENT_QUOTES, 'UTF-8');
$message_value = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

$product_options = '<option value=""></option>';
foreach($products as $item)
{
	$optValue = htmlspecialchars((string)$item['id_room'], ENT_QUOTES, 'UTF-8');
	$optLabel = htmlspecialchars($item['room_title'], ENT_QUOTES, 'UTF-8');
	$selected = ((string)$product === (string)$item['id_room']) ? ' selected' : '';
	$product_options .= '<option value="'.$optValue.'"'.$selected.'>'.$optLabel.'</option>';
}

$status_html = '';
if($form_error !== '')
{
	$status_html = '<p class="signup-error" style="color:red;">'.htmlspecialchars($form_error, ENT_QUOTES, 'UTF-8').'</p>';
}
else if($form_success !== '')
{
	$status_html = '<p class="signup-error" style="color:green;">'.htmlspecialchars($form_success, ENT_QUOTES, 'UTF-8').'</p>';
}
else
{
	$status_html = '<p class="signup-error"></p>';
}

$form_html = <<<HTML
<link rel="stylesheet" href="/signup/css/signup.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<div class="signup-form-column contact-form-column">
<form id="contactForm" class="signup-form" method="POST" action="/contact.php">

    <div class="signup-field-row">
        <div class="signup-field">
            <input type="text" name="first_name" id="contact_first_name" class="input-field" placeholder="First Name *" required autocomplete="given-name" value="{$first_name_value}">
        </div>
        <div class="signup-field">
            <input type="text" name="last_name" id="contact_last_name" class="input-field" placeholder="Last Name *" required autocomplete="family-name" value="{$last_name_value}">
        </div>
    </div>

    <div class="signup-field-row">
        <div class="signup-field">
            <input type="email" name="email" id="contact_email" class="input-field" placeholder="Email *" required autocomplete="email" value="{$email_value}">
            <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
        </div>
        <div class="signup-field">
            <input type="tel" name="callback_number" id="contact_callback_number" class="input-field" placeholder="Call Back Number *" required autocomplete="tel" value="{$callback_number_value}">
            <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
        </div>
    </div>

    <div class="signup-field">
        <select name="product" id="contact_product" class="input-field" required data-placeholder="Product *">
            {$product_options}
        </select>
    </div>

    <div class="signup-field">
        <textarea name="message" id="contact_message" class="input-field input-textarea" placeholder="Message *" required rows="3">{$message_value}</textarea>
    </div>

    <button type="submit" id="contactBtn" class="signup-submit-btn">Send Message</button>

    {$status_html}
</form>
</div>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
HTML;

$scripts = <<<'JAVASCRIPT'
function updateContactButtonState() {
    var btn = document.getElementById('contactBtn');
    var firstName = document.getElementById('contact_first_name').value.trim();
    var lastName = document.getElementById('contact_last_name').value.trim();
    var email = document.getElementById('contact_email').value.trim();
    var callback = document.getElementById('contact_callback_number').value.trim();
    var product = document.getElementById('contact_product').value;
    var message = document.getElementById('contact_message').value.trim();
    if (firstName && lastName && email && callback && product && message) {
        btn.classList.add('active');
    } else {
        btn.classList.remove('active');
    }
}

document.querySelectorAll('#contactForm input, #contactForm textarea').forEach(function(input) {
    input.addEventListener('input', updateContactButtonState);
    input.addEventListener('change', updateContactButtonState);
});

if (window.jQuery) {
    jQuery(function($) {
        $('#contact_product').select2({
            width: '100%',
            placeholder: 'Product *',
            allowClear: true,
            minimumResultsForSearch: 0
        });
        $('#contact_product').on('change select2:select select2:clear', updateContactButtonState);
    });
}

updateContactButtonState();
JAVASCRIPT;

$set_body = new content_block(NULL, 'div', array('class' => 'login-page'));
$set_body->push(new content_block($form_html, 'raw'));
$set_body->push(new content_block($scripts, 'script', array('type' => 'text/javascript')));

$set_title = 'Contact Us - MyProCAT';
$page_banner = new content_block(NULL, 'div', array('class' => 'banner'));
$page_banner->push(new content_block('Request Support', 'h1'));

$sidebar_title = 'Request Support';
$breadcrumb_items = array(
	array('text' => 'Home', 'url' => '/resources.php'),
	array('text' => 'Request Support', 'url' => '/contact.php'),
);

$sidebar_title = '';
$sidebar_logo = '/templateV2/mainframe/img/contact.png';
$sidebar_logo_text = '';
require_once DOCUMENT_ROOT.'/templateV2/mainframe/mainframe.php';
?>
