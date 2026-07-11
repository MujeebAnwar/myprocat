<?php
// Does all the initial setup for a new user and session variables,
// Doesn't log you in from username/password, only from cookie code
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/lib/Util.php');
require_once (DOCUMENT_ROOT.'/lib/Session.php');
require_once (DOCUMENT_ROOT.'/lib/account.php');
require_once (DOCUMENT_ROOT.'/lib/database.php');

$DB = new databaseI();
$Session = new Session($DB);
$UserAccount = &$Session->user;
if(is_array($_POST) && array_key_exists('Email',$_POST) && array_key_exists('Password',$_POST))
{
	$Session->Log_In($_POST['Email'],$_POST['Password']);
} else {
	$Session->Log_In();
}
?>