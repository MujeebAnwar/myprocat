<?php
// Does all the initial setup for a new user and session variables,
// Also logs you in, if you were trying to log in.
// On a successful login with username and password, it directs you to whichever page you were going to.
// In case you were deflected by login-related diversions. 
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/lib/Util.php');
require_once (DOCUMENT_ROOT.'/setup/login.php');

if($Session->valid && isset($UserAccount) && $UserAccount->logged_in &&
	is_array($_POST) && array_key_exists('Email',$_POST) && array_key_exists('Password',$_POST)
	)
{
	if(array_key_exists('NavNext',$_COOKIE) 
		&& $_COOKIE['NavNext'] !== "" 
		&& !strstr($_COOKIE['NavNext'],'logout.php')
		&& !strstr($_COOKIE['NavNext'],$_SERVER['SCRIPT_NAME'])
		)
	{
		GoToPage($_COOKIE['NavNext']);
	}
}
setcookie("NavNext",$_SERVER['REQUEST_URI'],time()+$Session->cookie_timeout,'/');
if(!is_null($UserAccount) && $UserAccount->password_expired && strpos($_SERVER['SCRIPT_NAME'],"changepassword")===false)
{
	GoToPage('/changepassword.php');
}
?>