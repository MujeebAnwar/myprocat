<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/start.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once (DOCUMENT_ROOT.'/lib/password_recovery.php');
require_once (DOCUMENT_ROOT.'/template/form.php');
require_once (DOCUMENT_ROOT.'/lib/messages.php');
require_once (DOCUMENT_ROOT.'/lib/Util.php');
require_once (DOCUMENT_ROOT.'/lib/password_requirements_authenticator.php');
require_once (DOCUMENT_ROOT.'/template/hovertooltip.php');

if($Session->valid && isset($UserAccount) && $UserAccount->logged_in)
{
	// You can't recover your password if you're logged in.
	GoToPage("/index.php");
}
$errorcode = '';
function form_validate()
{
	$pra = new password_requirements_authenticator();
	$pra_errmsg;
	global $errorcode;
	if(!array_key_exists('Recovery_Token',$_POST) || strlen($_POST['Recovery_Token']) < 1 ||
		!array_key_exists('New_Password',$_POST) || strlen($_POST['New_Password']) < 1 ||
		!array_key_exists('Confirm_New_Password',$_POST) || strlen($_POST['Confirm_New_Password']) < 1 )
	{
		return false; // default error message, form is incomplete
	}
	if($_POST['New_Password'] !== $_POST['Confirm_New_Password'])
	{
		$errorcode = "New Password fields must match";
		return false;
	}
	if(!$pra->IsOk($_POST['New_Password'],$pra_errmsg))
	{
		$errorcode = $pra_errmsg;
		return true;
	}
	return true;
}
$set_body = new section(NULL,array('style'=>'max-width:710px'));
if(array_key_exists('Email',$_POST)) {
	$_POST['email'] = $_POST['Email'];
} else {
	// handle GET request from link in password recovery email message
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
if(!is_array($_POST) || !array_key_exists('email',$_POST) || is_null($_POST['email']))
{
	$set_body->push(new paragraph("Please enter your e-mail address:",array('class' => 'message')));
	$form = new form(NULL,array('method'=>'POST'));
	$form->push(new field("Email",array('class' => 'pwdrecovery','arrange'=> 'vertical',"type"=>"text",'name'=> 'email')));
	$form->push(new submit('Submit',array('class'=>'pwdrecovery_submit')));
	$set_body->push($form);
} else {
	$pwdrec = new recovery_session($_POST['email']);
	if($pwdrec->get_status() === RECOVERY_STATUS_INIT)
	{
		/// New should generate, this shouldn't happen
		$set_body->push(new paragraph('Password recovery failed.',array('class' => 'message')));
	}
	if($pwdrec->get_status() === RECOVERY_STATUS_GENERATED)
	{
		if(form_validate())
		{
			if($pwdrec->validate($_POST['email'],$_POST['Recovery_Token']))
			{
				if($pwdrec->new_password($_POST['New_Password']))
				{
					setcookie("NavNext",'/index.php',time()+200,'/');
					$set_body->push(DelayShowMessage($DB,"Your password has been changed, please log in.",1));
				} else {
					$set_body->push(new paragraph("Unable to change your password.",array('class' => 'importantmessage')));
				}
				// Error code will only appear if status != used, so on failure to set password
				$errorcode = "Unable to set your password."; 
			} else {
				$errorcode = "Invalid data submitted, please review your submitted data and try again.";
				unset($_POST['Recovery_Token']);
			}
		}
		if($pwdrec->get_status() !== RECOVERY_STATUS_USED && $pwdrec->get_status() !== RECOVERY_STATUS_ERROR)
		{
			$set_body->push(new paragraph('Reset your password',array('class' => 'importantmessage')));
			if(!array_key_exists('Recovery_Token', $_POST)
				|| is_null($_POST['Recovery_Token'])
				|| strlen($_POST['Recovery_Token']) == 0) {
				$set_body->push(new paragraph(
					'We have sent you an e-mail '.
					'with a recovery token you will need in order to complete this form. Please check to '.
					'make sure you\'ve received that e-mail, but do not shut this window unless you\'ve already '.
					'completed the password reset.',array('class'=>'errormessage')));
			}
			if($errorcode != "")
			{
				$set_body->push(new paragraph($errorcode,array('class' => 'errormessage')));
			} else {
				$set_body->push(new paragraph("Please fill out this form completely and accurately to reset your password:",array('class' => 'verbosemessage')));
			}
			$sec = new section();
			$form = new form(NULL,array('method'=>'POST'));

			// ***Email***
			// must include an autocomplete=username field otherwise Edge will store the new password using the "Customer ID" value as the username
			$params = array('name'=>'email','value'=>$_POST['email'],'arrange'=>'vertical','autocomplete'=>'username');
			if(!is_null($_POST['email'])
				&& strlen($_POST['email']) > 0) {
				// hide the email-related elements if we already have the value
				$params = array_merge(array('type'=>'hidden','style'=>'display:none'), $params);
				$form->push(new field("Email",$params));
			}
			else {
				$form->push(new field("Email",$params));
			}
			
			// ***Recovery Token***
			$params = array('onfocus'=>'focusHider()','class'=>'pwdrecovery','arrange'=>'vertical','autocomplete'=>'one-time-code');
			if(array_key_exists('Recovery_Token', $_POST)
				&& !is_null($_POST['Recovery_Token'])
				&& strlen($_POST['Recovery_Token']) > 0) {
				// hide the recovery token-related elements if we already have the value
				$params = array_merge(array('type'=>'hidden','style'=>'display:none'), $params);
				$form->push(new field('Recovery Token',$params));
			} else {
				$s = new hovertooltip("Please check your e-mail for the recovery token.",array('halign'=>'left'));
				$form->push($s);
				$params = array_merge(array('type'=>'text'), $params);
				$form->push(new field('Recovery Token',$params));
			}

			// ***New Password***
			$s = new hovertooltip(
				"New passwords must be at least 8 characters, ".
				"have a mix of upper and lower-cased letters, ".
				"must contain at least one non-letter symbol, ".
				"and cannot contain an ampersand.",array('halign'=>'left'));
			$form->push($s);
			$newpasswordfield = new field('New Password',array('onfocus' => 'focusHider()','type' => 'password','class' => 'pwdrecovery','arrange'=> 'vertical','validate'=> 'true','autocomplete'=>'new-password'));
			$form->push($newpasswordfield);

			// ***Confirm New Password***
			$form->push(new field('Confirm New Password',array('onfocus' => 'focusHider()','type' => 'password','class' => 'pwdrecovery','arrange'=> 'vertical','validate'=> 'true','compare' => $newpasswordfield,'autocomplete'=>'new-password')));
			$form->push(new submit('Submit',array('class'=>'pwdrecovery_submit')));
			$sec->push($form);
			$set_body->push($sec);
		}
	}
	if($pwdrec->get_status() === RECOVERY_STATUS_ERROR)
	{
		if($pwdrec->last_error !== "")
		{
			$set_body->push(new paragraph(
				$pwdrec->last_error,array('class' => 'errormessage')));
		}
		$set_body->push(new paragraph(
			"Unable to reset your password at this time.",array('class' => 'errormessage')));
	}
}
$page_script = 
'
function focusHider()
{
	document.getElementById("ShowMe").style.display = \'none\';
}
'
;
require_once (DOCUMENT_ROOT.'/template/mainframe.php');
?>