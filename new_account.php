<?php
require_once ('config.php');
require_once DOCUMENT_ROOT.'/setup/start.php';
require_once DOCUMENT_ROOT.'/setup/force_authorized.php';
require_once DOCUMENT_ROOT.'/template/Master.php';
require_once DOCUMENT_ROOT.'/template/room.php';
require_once DOCUMENT_ROOT.'/lib/file_details.php';
require_once DOCUMENT_ROOT.'/CasePadSE/lib/account_verifier.php';

$set_body = new section();
$new_accounts_enabled = (($_SERVER['SERVER_NAME'] == 'casepad.cloud' )||  ($_SERVER['SERVER_NAME'] == 'dev.casepad.cloud'));
$av = new account_verifier($Session, !$new_accounts_enabled);
if($new_accounts_enabled)
{
	$code_issued = $av->has_reg_code();
	$reg_code_submit = $_GET['code'];
	if(!$reg_code_submit)
		$reg_code_submit = $_POST['Code'];
	if($reg_code_submit)
	{
		if($code_issued)
		{
			$set_body->push(new paragraph("Checking your code."));
			if($av->verify_regcode($reg_code_submit))
			{
				$UserAccount->user_details['email_verified'] = 1;
			} else {
				$set_body->push(new paragraph("Code not found."));
			
			}
		}
	}
	if($UserAccount->user_details['email_verified'])
	{
		$set_body->push(new paragraph("This account has a verified e-mail address."));
	} else {
		if(!$code_issued)
		{
			$set_body->push(new paragraph("Your code has expired, or was never sent."));
			if($av->create_reg_code())
			{
				$av->send_registration_email();
				$set_body->push(new paragraph("We have tried to send you a new code, check your e-mail."));
			}
		}
		$set_body->push(new paragraph("Enter your registration code:"));
		$form = new form(NULL,array('method' => 'POST'));
		$form->push(new field("Code",array('type' => 'text','class' => 'changepassword_field','arrange' => 'vertical')));
		$form->push(new submit("Submit registration code",array('class' => 'changepassword_submit')));
		$set_body->push($form);
	}

} else {
	$set_body->push(new paragraph("This server does not allow verified user-created accounts."));
	$set_body->push(new paragraph("Unverified accounts will have notes and settings retained, but cannot schedule rooms."));
}

require_once (DOCUMENT_ROOT.'/template/mainframe.php');
?>