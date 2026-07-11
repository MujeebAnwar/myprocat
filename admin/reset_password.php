<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/login.php');
require_once (DOCUMENT_ROOT.'/setup/force_authorized.php');
require_once (DOCUMENT_ROOT.'/setup/force_admin.php');
require_once (DOCUMENT_ROOT.'/lib/adminactions.php');
require_once (DOCUMENT_ROOT.'/template/form.php');
require_once (DOCUMENT_ROOT.'/template/roomlist.php');

if($Session->valid && $UserAccount->logged_in && $UserAccount->user_details['is_admin'])
{
	$set_title = "Myprocat.com: Reset Password";
	$set_body = new section();
	if(is_array($_POST) && array_key_exists('action',$_POST) && $_POST['action'] === 'reset_password')
	{
		if($DB->sql(
			'INSERT INTO passwords (`hash_password`,`id_user`,`time_stamp`) '.
			'VALUES (?,?,NOW())',
			array('ss',md5($_POST['Temporary_Password']),$_POST['Set_Password_For'])
			))
		{
			$set_body->push(new paragraph('Temporary password set',array('class' => 'importantmessage')));
		} else {
			$set_body->push(new paragraph('Error setting temporary password',array('class' => 'message')));
		}
	} else {
		$form = new form(NULL,array('method' => 'POST'));
		$form->push(new field('Set Password For',array('class' => 'resetpassword','arrange' => 'vertical')));
		$form->push(new field('Temporary Password',array('type'=> 'password', 'class' => 'resetpassword','arrange' => 'vertical')));
		$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'action','value'=>'reset_password')));
		$form->push(new submit('Submit'));
		$set_body->push($form);
		$set_body->push(new paragraph());
		$sec = new content_block(NULL,'div');
		$set_body->push($sec);
	}
} else {
	$set_title = "Myprocat.com: Error";
	$set_body = new paragraph("You do not have permission to use this page.");
}
require_once(DOCUMENT_ROOT.'/template/mainframe.php');
?>
