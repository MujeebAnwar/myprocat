<?php
require_once ('config.php');
require_once DOCUMENT_ROOT.'/setup/start.php';
require_once DOCUMENT_ROOT.'/template/Master.php';
require_once DOCUMENT_ROOT.'/template/form.php';


if(is_array($_POST) && array_key_exists('Email',$_POST))
{
	if($_POST['Email'] == "")
	{
		$set_body = new paragraph("You must type in your e-mail address to log in.",array('class' => 'errormessage'));
	} else {
		if(strlen($Session->error))
		{
			$set_body = new paragraph("Error: ".$Session->error,array('class' => 'errormessage'));
			$form = new form(NULL,array('method' => 'POST','id'=>'forgotpassword','action' => '/signup/forgotpassword.php'));
			$form->push(new input(NULL,array('type'=>'hidden','name'=>'email','value'=>$_POST['Email'])));
			$form->push(new submit('Forgot your password? click here.',array('class' => 'forgotpasswordlink')));
			$set_body->push($form);
		}
	}

} else {
	if($Session->valid)
	{
		$Session->Log_Out();
	}
	header('Location: /signup/login.php');
	exit;
}

require_once (DOCUMENT_ROOT.'/template/mainframe.php');
?>