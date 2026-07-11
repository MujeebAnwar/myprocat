<?php
// Must be logged in to change your password
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/start.php');
require_once (DOCUMENT_ROOT.'/lib/form_action.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once (DOCUMENT_ROOT.'/template/form.php');
$content = new section();
$set_delay_redirect = false;
if($Session->valid && isset($UserAccount) && $UserAccount->logged_in)
{
	$content->push(new paragraph("Welcome, ".$UserAccount->user_details['first_name'],array('class' => 'importantmessage')));
	if($UserAccount->password_expired) 
	{
	 $content->push(new paragraph("Your password has expired, you must change your password.",array('class' => 'errormessage')));
	}
	  
	$fa_reqs = array();
	array_push($fa_reqs,new field_exists('Old Password'));
	array_push($fa_reqs,new field_matches_otherfield('New password','New password again'));
	array_push($fa_reqs,new field_min_length('New password',8));
	$fa = new form_action(NULL,NULL,NULL,$fa_reqs);
	$form = new form(NULL,array('method' => 'POST','form_action' => $fa));
	$form->push(new field("Old Password",array('type' => 'password','class' => 'changepassword_field','arrange' => 'vertical')));
	$form->push(new field("New password",array('type' => 'password','class' => 'changepassword_field','arrange' => 'vertical')));
	$form->push(new field("New password again",array('type' => 'password','class' => 'changepassword_field','arrange' => 'vertical')));
	$form->push(new submit("Change Password",array('class' => 'changepassword_submit')));

	if($fa->check_requirements() === FORM_STATUS_OK)
	{
		$fa->set_action(
			function() use ($Session,$fa) 
			{
				if(!$Session->change_password($_POST['Old_Password'],$_POST['New_password'],$_POST['New_password_again']))
				{
					$fa->error = $Session->error;
					$fa->status = FORM_STATUS_ERROR;
				} else {
					$fa->status = FORM_STATUS_SUBMIT_SUCCESS;
				}
			}
		);
		$fa->submit();
	}
	
	if(!($fa->status === FORM_STATUS_OK || $fa->status === FORM_STATUS_SUBMIT_SUCCESS || $fa->status === FORM_STATUS_ERROR))
	{
		$content->push(new paragraph("Please Change your password:",array('class' => 'message')));
	}
}
if($Session->valid && isset($UserAccount) && $UserAccount->logged_in)
{
	$content->push($form);
	$set_body = $content;

} else {
	$set_body = new paragraph("You must be logged in to change your password.",array('class' => 'importantmessage'));
}

require_once (DOCUMENT_ROOT.'/template/mainframe.php');
if($set_delay_redirect)
{
	DelayGoToPage('/index.php');
}
