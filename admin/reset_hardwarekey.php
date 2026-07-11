<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/login.php');
require_once (DOCUMENT_ROOT.'/setup/force_authorized.php');
require_once (DOCUMENT_ROOT.'/setup/force_admin.php');
require_once (DOCUMENT_ROOT.'/lib/adminactions.php');
require_once (DOCUMENT_ROOT.'/lib/keyless_lib.php');
require_once (DOCUMENT_ROOT.'/template/form.php');
require_once (DOCUMENT_ROOT.'/template/roomlist.php');

if($Session->valid && $UserAccount->logged_in && $UserAccount->user_details['is_admin'])
{
	$set_title = "Myprocat.com: Reset Hardware Key";
	$set_body = new section();
	$set_body->push(new paragraph('Reset user hardware ID',array('class' => 'importantmessage')));
	if(is_array($_POST) && array_key_exists('action',$_POST) && $_POST['action'] === 'reset_hardware')
	{
		if($DB->sql(
			'DELETE FROM hardware WHERE id_user=?',
			array('s',$_POST['Reset_Key_For'])
			))
		{
			$set_body->push(new paragraph('Hardware Key reset',array('class' => 'message')));
		} else {
			$set_body->push(new paragraph('Error resetting key (No key or bad ID?)',array('class' => 'message')));
		}
	}
	$form = new form(NULL,array('method' => 'POST'));
	$form->push(new field('Reset Key For',array('class' => 'resetpassword','arrange' => 'vertical')));
	$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'action','value'=>'reset_hardware')));
	$form->push(new submit('Submit'));
	$set_body->push($form);
	$set_body->push(new paragraph());
	$sec = new content_block(NULL,'div');
	$set_body->push($sec);
} else {
	$set_title = "Myprocat.com: Error";
	$set_body = new paragraph("You do not have permission to use this page.");
}
require_once(DOCUMENT_ROOT.'/template/mainframe.php');
?>
