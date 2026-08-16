<?php
require_once (realpath(__DIR__).'/config.php');
require_once DOCUMENT_ROOT.'/setup/start.php';
require_once DOCUMENT_ROOT.'/template/Master.php';

$redirect = '/resources.php';
if(array_key_exists('NavNext',$_GET))
{
	$parsed = parse_url($_GET['NavNext']);
	if(!array_key_exists('host',$parsed))
	{
		$_COOKIE['NavNext'] = $parsed['path'].(array_key_exists('query',$parsed) ? '?'.$parsed['query'] : '');
		setcookie('NavNext', $_COOKIE['NavNext'], time() + 86400, '/');
	}
}
if(array_key_exists('NavNext',$_COOKIE)
	&& $_COOKIE['NavNext'] != ''
	&& $_COOKIE['NavNext'] != '/logout.php'
	&& strpos($_COOKIE['NavNext'], '/signup/login') === false
)
{
	$parsed = parse_url($_COOKIE['NavNext']);
	if(!array_key_exists('host',$parsed))
	{
		$redirect = $_COOKIE['NavNext'];
	}
}

$login_error = '';
$email_value = '';
$agree_checked = false;

// Session Log_In already ran in setup/login.php when Email+Password were POSTed.
// Require agreement before allowing a successful login to stick.
if(is_array($_POST) && array_key_exists('Email', $_POST))
{
	$email_value = htmlspecialchars($_POST['Email'], ENT_QUOTES, 'UTF-8');
	$agree_checked = array_key_exists('agree_terms', $_POST);

	if(!$agree_checked)
	{
		if(isset($Session) && $Session->valid)
		{
			$Session->Log_Out();
			$UserAccount = &$Session->user;
		}
		$login_error = 'You must agree to the Privacy Policy and Single User License to sign in.';
	}
	else if($_POST['Email'] === '')
	{
		$login_error = 'You must type in your e-mail address to log in.';
	}
	else if(isset($Session) && strlen($Session->error))
	{
		$login_error = $Session->error;
	}
	else if(is_null($UserAccount) || !is_a($UserAccount, 'useraccount') || !$UserAccount->logged_in)
	{
		$login_error = 'Invalid email or password.';
	}
}

if($login_error === '' && !is_null($UserAccount) && is_a($UserAccount, 'useraccount') && $UserAccount->logged_in)
{
	header('Location: '.$redirect);
	exit;
}

$agree_attr = $agree_checked ? ' checked' : '';

$error_html = $login_error !== ''
	? '<p id="loginError" class="signup-error" style="color:red;">'.htmlspecialchars($login_error, ENT_QUOTES, 'UTF-8').'</p>'
	: '<p id="loginError" class="signup-error"></p>';

$form_html = <<<HTML
<link rel="stylesheet" href="/signup/css/signup.css">
<div class="signup-form-column">
<form id="loginForm" class="signup-form" method="POST" action="/signup/login.php">

    <div class="signup-field">
        <input type="email" name="Email" id="login_email" class="input-field" placeholder="Email *" required autocomplete="username" value="{$email_value}">
        <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
    </div>

    <div class="signup-field">
        <input type="password" name="Password" id="login_password" class="input-field" placeholder="Password *" required autocomplete="current-password">
        <button type="button" class="password-toggle" onclick="togglePassword('login_password', this)" aria-label="Toggle password visibility">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
    </div>

    <div class="login-forgot-link">
        <a href="/signup/forgotpassword.php">Forgot password?</a>
    </div>

    <label class="signup-terms" for="agree_terms">
        <input type="checkbox" name="agree_terms" id="agree_terms" value="1" required{$agree_attr}>
        <span>I agree to the <a href="https://procat.com/privacy-policy/" target="_blank" rel="noopener noreferrer">Privacy Policy</a> and <a href="https://procat.com/privacy-policy/" target="_blank" rel="noopener noreferrer">Single User License</a> agreement.</span>
    </label>

    <button type="submit" id="loginBtn" class="signup-submit-btn">Sign In</button>

    {$error_html}
</form>
</div>
HTML;

$scripts = <<<'JAVASCRIPT'
function togglePassword(fieldId, btn) {
    var input = document.getElementById(fieldId);
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
    } else {
        input.type = 'password';
        btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    }
}

function updateButtonState() {
    var btn = document.getElementById('loginBtn');
    var email = document.getElementById('login_email').value.trim();
    var password = document.getElementById('login_password').value;
    var agreed = document.getElementById('agree_terms').checked;
    if (email && password && agreed) {
        btn.classList.add('active');
    } else {
        btn.classList.remove('active');
    }
}

document.querySelectorAll('#loginForm input').forEach(function(input) {
    input.addEventListener('input', updateButtonState);
    input.addEventListener('change', updateButtonState);
});
updateButtonState();
JAVASCRIPT;

$set_body = new content_block(NULL, 'div', array('class' => 'login-page'));
$set_body->push(new content_block($form_html, 'raw'));
$set_body->push(new content_block($scripts, 'script', array('type' => 'text/javascript')));


$breadcrumb_items = array();

$sidebar_title = '';
$sidebar_logo = '/signup/img/login_logo.jpg';
$sidebar_logo_text = '';
require_once DOCUMENT_ROOT.'/templateV2/mainframe/mainframe.php';
?>
