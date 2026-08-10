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
$message = '';
$form_error = '';
$form_success = '';

if($_SERVER['REQUEST_METHOD'] === 'POST')
{
	$first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
	$last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
	$email = isset($_POST['email']) ? trim($_POST['email']) : '';
	$message = isset($_POST['message']) ? trim($_POST['message']) : '';

	if($first_name === '' || $last_name === '' || $email === '' || $message === '')
	{
		$form_error = 'Please fill in all fields.';
	}
	else if(!filter_var($email, FILTER_VALIDATE_EMAIL))
	{
		$form_error = 'Please enter a valid email address.';
	}
	else
	{
		$safeFirst = htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8');
		$safeLast = htmlspecialchars($last_name, ENT_QUOTES, 'UTF-8');
		$safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
		$safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
		$userId = isset($UserAccount->user_details['id_user']) ? htmlspecialchars($UserAccount->user_details['id_user'], ENT_QUOTES, 'UTF-8') : '';

		$emailBody = <<<HTML
<div style="font-family: Arial, sans-serif; font-size: 14px; color: #333;">
	<h2 style="color: #27475f;">Contact Us Message</h2>
	<p><strong>First Name:</strong> {$safeFirst}</p>
	<p><strong>Last Name:</strong> {$safeLast}</p>
	<p><strong>Email:</strong> {$safeEmail}</p>
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
$message_value = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

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

    <div class="signup-field">
        <input type="email" name="email" id="contact_email" class="input-field" placeholder="Email *" required autocomplete="email" value="{$email_value}">
        <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
    </div>

    <div class="signup-field">
        <textarea name="message" id="contact_message" class="input-field input-textarea" placeholder="Message *" required rows="3">{$message_value}</textarea>
    </div>

    <button type="submit" id="contactBtn" class="signup-submit-btn">Send Message</button>

    {$status_html}
</form>
</div>
HTML;

$scripts = <<<'JAVASCRIPT'
function updateContactButtonState() {
    var btn = document.getElementById('contactBtn');
    var firstName = document.getElementById('contact_first_name').value.trim();
    var lastName = document.getElementById('contact_last_name').value.trim();
    var email = document.getElementById('contact_email').value.trim();
    var message = document.getElementById('contact_message').value.trim();
    if (firstName && lastName && email && message) {
        btn.classList.add('active');
    } else {
        btn.classList.remove('active');
    }
}

document.querySelectorAll('#contactForm input, #contactForm textarea').forEach(function(input) {
    input.addEventListener('input', updateContactButtonState);
});
updateContactButtonState();
JAVASCRIPT;

$set_body = new content_block(NULL, 'div', array('class' => 'login-page'));
$set_body->push(new content_block($form_html, 'raw'));
$set_body->push(new content_block($scripts, 'script', array('type' => 'text/javascript')));

$set_title = 'Contact Us - MyProCAT';
$sidebar_title = 'Contact Us';
$breadcrumb_items = array(
	array('text' => 'Home', 'url' => '/resources.php'),
	array('text' => 'Contact Us', 'url' => '/contact.php'),
);

$sidebar_title = 'MyProCAT';
$sidebar_logo = '/templateV2/mainframe/img/contact.png';
$sidebar_logo_text = 'MyProCAT Contact Us';
require_once DOCUMENT_ROOT.'/templateV2/mainframe/mainframe.php';
?>
