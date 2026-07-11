<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/login.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once (DOCUMENT_ROOT.'/lib/messages.php');

$message = NULL;
if(is_array($_POST) && array_key_exists('msgid', $_POST) && is_numeric($_POST['msgid']))
{
	$msgob = new messagehandler($DB);
	$message = $msgob->get_message($_POST['msgid']);
}
if(!is_null($message))
{
	$set_body = $message;
} else {
	GoToPage('/');
}



require_once (DOCUMENT_ROOT.'/template/mainframe.php');
?>

