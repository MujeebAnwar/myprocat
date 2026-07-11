<?php
// Must be logged in to change your password
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/start.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once (DOCUMENT_ROOT.'/template/form.php');
require_once (DOCUMENT_ROOT.'/template/hovertooltip.php');
require_once (DOCUMENT_ROOT.'/lib/password_requirements_authenticator.php');
$content = new section();
$set_delay_redirect = false;

if($Session->valid && isset($UserAccount) && $UserAccount->logged_in)
{
	$pra = new password_requirements_authenticator();
	$pra_errmsg;
	$content->push(new paragraph("Welcome, ".$UserAccount->user_details['first_name'],array('class' => 'importantmessage')));
	if($UserAccount->password_expired) 
	{
	 $content->push(new paragraph("Your password has expired, you must change your password.",array('class' => 'errormessage')));
	}
	if(is_array($_POST) && array_key_exists('New_password',$_POST) && strlen($_POST['New_password']) > 0)
	{

		if(!(array_key_exists('Old_Password',$_POST) && strlen($_POST['Old_Password']) > 0 ))
		{
			$content->push(new paragraph("You must enter your old password.",array('class' => 'errormessage')));

		} else if(!(array_key_exists('New_password',$_POST) && strlen($_POST['New_password']) > 0 ))
		{
			$content->push(new paragraph("You must enter a new password.",array('class' => 'errormessage')));
		} else if(!(array_key_exists('New_password_again',$_POST) && strlen($_POST['New_password_again']) > 0 )) {
			$content->push(new paragraph("You must enter your new password twice for verification.",array('class' => 'errormessage')));
		} else if(!($_POST['New_password_again'] === $_POST['New_password'])) {
			$content->push(new paragraph("New password fields must match.",array('class' => 'errormessage')));
		} else if (!$pra->IsOk($_POST['New_password'],$pra_errmsg))
		{
			$content->push(new paragraph($pra_errmsg,array('class' => 'errormessage')));
		}
		 else if($Session->change_password($_POST['Old_Password'],$_POST['New_password'],$_POST['New_password_again']))
		{
			$content->push(new paragraph("Your password has been changed.",array('class' => 'importantmessage')));
			$content->push(new paragraph("Don't forget to change your myprocat.com in Winner as well!.",array('class' => 'message')));
			$set_delay_redirect = true;
		} else {
			$content->push(new paragraph($Session->error,array('class' => 'errormessage')));
			if(is_null($Session) || !$Session->valid || is_null($Session->user) || !$Session->user->logged_in)
			{
				
				$set_delay_redirect = true;
			}
		}
	}
	if($set_delay_redirect)
	{
		$content->push(new paragraph("You will now be directed back to the log in page.",array('class' => 'importantmessage')));
	} else {

		$content->push(new paragraph("Please Change your password:",array('class' => 'message')));
		$form = new form(NULL,array('method' => 'POST'));
		$form->push(new field("Old Password",array('type' => 'password','class' => 'changepassword_field','arrange' => 'vertical')));
		$s = new hovertooltip(
				"New passwords must be at least 8 characters, ".
				"have a mix of upper and lower-cased letters, ".
				"must contain at least one non-letter symbol, ".
				"and cannot contain an ampersand.",array());
		$form->push($s);
		$newpassword = new field("New password",array('type' => 'password','class' => 'changepassword_field','arrange' => 'vertical','validate'=>true));
		$form->push($newpassword);
		$form->push(new field("New password again",array('type' => 'password','class' => 'changepassword_field','arrange' => 'vertical','validate'=>true,'compare'=> $newpassword)));
		$form->push(new submit("Change Password",array('class' => 'changepassword_submit')));
		$content->push($form);

	}
} else {
	$content->push(new paragraph("You must be logged in to change your password.",array('class' => 'importantmessage')));
}
$set_body = $content;
$set_title = "Myprocat.com: Change your password";
require_once (DOCUMENT_ROOT.'/template/mainframe.php');
if($set_delay_redirect)
{
	DelayGoToPage('/index.php');
}
