<?php
require_once (realpath(__DIR__).'/config.php');
require_once DOCUMENT_ROOT.'/setup/start.php';
require_once DOCUMENT_ROOT.'/template/Master.php';
require_once DOCUMENT_ROOT.'/lib/password_recovery.php';
require_once DOCUMENT_ROOT.'/lib/password_requirements_authenticator.php';
require_once DOCUMENT_ROOT.'/lib/Util.php';

if($Session->valid && isset($UserAccount) && $UserAccount->logged_in)
{
	GoToPage('/resources.php');
}

$errorcode = '';
$status_message = '';
$status_type = ''; // error | success | info
$form_mode = 'request'; // request | reset | none
$email_value = '';
$token_value = '';
$show_token_field = true;
$show_email_field = true;

function forgot_password_form_validate(&$errorcode)
{
	$pra = new password_requirements_authenticator();
	$pra_errmsg = '';
	if(!array_key_exists('Recovery_Token',$_POST) || strlen($_POST['Recovery_Token']) < 1 ||
		!array_key_exists('New_Password',$_POST) || strlen($_POST['New_Password']) < 1 ||
		!array_key_exists('Confirm_New_Password',$_POST) || strlen($_POST['Confirm_New_Password']) < 1 )
	{
		return false;
	}
	if($_POST['New_Password'] !== $_POST['Confirm_New_Password'])
	{
		$errorcode = 'New Password fields must match';
		return false;
	}
	if(!$pra->IsOk($_POST['New_Password'],$pra_errmsg))
	{
		$errorcode = $pra_errmsg;
		return false;
	}
	return true;
}

if(array_key_exists('Email',$_POST)) {
	$_POST['email'] = $_POST['Email'];
} else {
	if(array_key_exists('email',$_GET)) {
		$_POST['Email'] = $_GET['email'];
	}
	else if(array_key_exists('Email',$_GET)) {
		$_POST['Email'] = $_GET['Email'];
	}
	if(array_key_exists('Email',$_POST)) {
		$_POST['email'] = $_POST['Email'];
	}
	if(array_key_exists('Recovery_Token',$_GET)) {
		$_POST['Recovery_Token'] = $_GET['Recovery_Token'];
	}
}

if(!is_array($_POST) || !array_key_exists('email',$_POST) || is_null($_POST['email']) || $_POST['email'] === '')
{
	$form_mode = 'request';
	$status_message = 'Please enter your e-mail address to reset your password.';
	$status_type = 'info';
}
else
{
	$email_value = $_POST['email'];
	$pwdrec = new recovery_session($_POST['email']);

	if($pwdrec->get_status() === RECOVERY_STATUS_INIT)
	{
		$form_mode = 'none';
		$status_message = 'Password recovery failed.';
		$status_type = 'error';
	}
	else if($pwdrec->get_status() === RECOVERY_STATUS_GENERATED)
	{
		$form_mode = 'reset';
		if(forgot_password_form_validate($errorcode))
		{
			if($pwdrec->validate($_POST['email'],$_POST['Recovery_Token']))
			{
				if($pwdrec->new_password($_POST['New_Password']))
				{
					setcookie('NavNext', '/signup/login.php', time() + 200, '/');
					$form_mode = 'none';
					$status_message = 'Your password has been changed. Redirecting to Sign In...';
					$status_type = 'success';
				}
				else
				{
					$errorcode = 'Unable to set your password.';
					$status_message = 'Unable to change your password.';
					$status_type = 'error';
				}
			}
			else
			{
				$errorcode = 'Invalid data submitted, please review your submitted data and try again.';
				unset($_POST['Recovery_Token']);
			}
		}

		if($pwdrec->get_status() !== RECOVERY_STATUS_USED && $pwdrec->get_status() !== RECOVERY_STATUS_ERROR)
		{
			$form_mode = 'reset';
			if(array_key_exists('Recovery_Token', $_POST)
				&& !is_null($_POST['Recovery_Token'])
				&& strlen($_POST['Recovery_Token']) > 0)
			{
				$token_value = $_POST['Recovery_Token'];
				$show_token_field = false;
			}
			if(!is_null($_POST['email']) && strlen($_POST['email']) > 0)
			{
				$show_email_field = false;
			}
			if($errorcode !== '')
			{
				$status_message = $errorcode;
				$status_type = 'error';
			}
			else if(!empty($pwdrec->notification_warning))
			{
				$status_message = $pwdrec->notification_warning;
				$status_type = 'info';
			}
			else if($show_token_field)
			{
				$status_message = 'We have sent you an e-mail with a recovery token. Enter it below to reset your password.';
				$status_type = 'info';
			}
			else
			{
				$status_message = 'Please choose a new password.';
				$status_type = 'info';
			}
		}
		else if($pwdrec->get_status() === RECOVERY_STATUS_USED)
		{
			$form_mode = 'none';
		}
	}

	if($pwdrec->get_status() === RECOVERY_STATUS_ERROR)
	{
		$form_mode = 'request';
		if($pwdrec->last_error !== '')
		{
			$status_message = $pwdrec->last_error;
		}
		else
		{
			$status_message = 'Unable to reset your password at this time.';
		}
		$status_type = 'error';
	}
}

$email_attr = htmlspecialchars($email_value, ENT_QUOTES, 'UTF-8');
$token_attr = htmlspecialchars($token_value, ENT_QUOTES, 'UTF-8');

$status_color = 'red';
if($status_type === 'success')
{
	$status_color = 'green';
}
else if($status_type === 'info')
{
	$status_color = '#1a365d';
}
$status_html = $status_message !== ''
	? '<p class="signup-error" style="color:'.$status_color.';">'.htmlspecialchars($status_message, ENT_QUOTES, 'UTF-8').'</p>'
	: '<p class="signup-error"></p>';

$form_fields = '';
if($form_mode === 'request')
{
	$form_fields = <<<HTML
	<div class="forgot-password-hint">Your Winner License Manager password must match your MyProCAT.com password. After resetting your MyProCAT.com password, update the password in Winner License Manager to match.</div>
    <div class="signup-field">
        <input type="email" name="Email" id="forgot_email" class="input-field" placeholder="Email *" required autocomplete="username" value="{$email_attr}">
        <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
    </div>
    <button type="submit" id="forgotBtn" class="signup-submit-btn">Submit</button>
HTML;
}
else if($form_mode === 'reset')
{
	$email_field = $show_email_field
		? <<<HTML
    <div class="signup-field">
        <input type="email" name="Email" id="forgot_email" class="input-field" placeholder="Email *" required autocomplete="username" value="{$email_attr}">
        <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
    </div>
HTML
		: '<input type="hidden" name="Email" id="forgot_email" value="'.$email_attr.'" autocomplete="username">';

	$token_field = $show_token_field
		? <<<HTML
    <div class="signup-field">
        <input type="text" name="Recovery_Token" id="forgot_token" class="input-field" placeholder="Recovery Token *" required autocomplete="one-time-code" value="{$token_attr}">
    </div>
HTML
		: '<input type="hidden" name="Recovery_Token" id="forgot_token" value="'.$token_attr.'" autocomplete="one-time-code">';

	$form_fields = <<<HTML
{$email_field}
{$token_field}
    <p class="signup-hint">Password must be at least 8 characters, include upper and lower case letters, at least one non-letter symbol, and cannot contain an ampersand.</p>
    <div class="signup-field">
        <input type="password" name="New_Password" id="forgot_new_password" class="input-field" placeholder="New Password *" required autocomplete="new-password">
        <button type="button" class="password-toggle" onclick="togglePassword('forgot_new_password', this)" aria-label="Toggle password visibility">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
    </div>
    <div class="signup-field">
        <input type="password" name="Confirm_New_Password" id="forgot_confirm_password" class="input-field" placeholder="Confirm New Password *" required autocomplete="new-password">
        <button type="button" class="password-toggle" onclick="togglePassword('forgot_confirm_password', this)" aria-label="Toggle password visibility">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
    </div>
    <button type="submit" id="forgotBtn" class="signup-submit-btn">Reset Password</button>
HTML;
}

$back_link = '<div class="login-forgot-link" style="text-align:center;margin-top:16px;"><a href="/signup/login.php">Back to Sign In</a></div>';

$form_html = <<<HTML
<link rel="stylesheet" href="/signup/css/signup.css">
<div class="signup-form-column">
<form id="forgotForm" class="signup-form" method="POST" action="/signup/forgotpassword.php">
{$form_fields}
{$status_html}
</form>
{$back_link}
</div>
HTML;

$redirect_script = '';
if($status_type === 'success')
{
	$redirect_script = "setTimeout(function(){ document.location.replace('/signup/login.php'); }, 1500);";
}

$scripts = <<<JAVASCRIPT
function togglePassword(fieldId, btn) {
    var input = document.getElementById(fieldId);
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
    } else {
        input.type = 'password';
        btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    }
}

function updateForgotButtonState() {
    var btn = document.getElementById('forgotBtn');
    if (!btn) return;
    var form = document.getElementById('forgotForm');
    var inputs = form.querySelectorAll('input[required]');
    var ready = true;
    inputs.forEach(function(input) {
        if (!String(input.value || '').trim()) {
            ready = false;
        }
    });
    if (ready) {
        btn.classList.add('active');
    } else {
        btn.classList.remove('active');
    }
}

document.querySelectorAll('#forgotForm input').forEach(function(input) {
    input.addEventListener('input', updateForgotButtonState);
});
updateForgotButtonState();
{$redirect_script}
JAVASCRIPT;

$set_body = new content_block(NULL, 'div', array('class' => 'login-page'));
$set_body->push(new content_block($form_html, 'raw'));
$set_body->push(new content_block($scripts, 'script', array('type' => 'text/javascript')));

$set_title = 'Forgot Password - MyProCAT';
$sidebar_title = 'Forgot Password';
$breadcrumb_items = array();

require_once DOCUMENT_ROOT.'/templateV2/mainframe/mainframe.php';
?>
