<?php
require_once("config.php");
require_once DOCUMENT_ROOT.'/lib/Util.php';
require_once DOCUMENT_ROOT.'/lib/database.php';
require_once DOCUMENT_ROOT.'/lib/Session.php';
$DB = new databaseI();
$Session = new Session($DB);
$UserAccount = &$Session->user;
if(is_array($_POST) && array_key_exists('Email',$_POST) && array_key_exists('Password',$_POST))
{
	$Session->Log_In($_POST['Email'],$_POST['Password']);
} else {
	$Session->API_Log_In();
}
if(array_key_exists('getapikey',$_POST))
{
	if(!is_null($UserAccount) && $Session->valid && $UserAccount->logged_in)
	{
		print make_json(['apikey'=> $Session->asJSON()]);
	} else {
		print make_json(['er'=>'expired']);
	}
}
?>