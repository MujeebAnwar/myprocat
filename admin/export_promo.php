<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/login.php');
require_once (DOCUMENT_ROOT.'/setup/force_authorized.php');
require_once (DOCUMENT_ROOT.'/setup/force_admin.php');
$error = "";
if(is_array($_GET) && array_key_exists('id_promo',$_GET))
{
	$promo_registrations = array(
	'first_name',
	'last_name',
	'email',
	'registration_code',
	'time',
	);
	if(!$DB->sql('SELECT first_name,last_name,email,registration_code,time '.
		'FROM promo_registrations '.
		'WHERE id_promo = ?',
	array('i',$_GET['id_promo']),
	$promo_registrations
	))
	{
		$promo_registrations = NULL;
	}
	if(is_null($promo_registrations))
	{
		$error = "No registrations found for that offer";
	} else {
		header('Content-Description: File Transfer');
	    header('Content-Type: application/octet-stream');
	    header('Content-Disposition: attachment; filename=export.csv');
	    header('Expires: 0');
	    header('Cache-Control: must-revalidate');
	    header('Pragma: public');
	    print '"'.implode('","',array_keys($promo_registrations[0]))."\"\r\n";
	    foreach($promo_registrations AS $reg)
	    {
	    	print '"'.implode('","',$reg)."\"\r\n";
	    }
	    exit;
	}
} else {
	$error = "You've reached this page the wrong way.";
}
require_once (DOCUMENT_ROOT.'/template/Master.php');
$set_body = new paragraph($error,array('class' => 'errormessage'));
require_once (DOCUMENT_ROOT.'/template/mainframe.php');
?>