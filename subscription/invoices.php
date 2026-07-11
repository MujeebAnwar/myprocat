<?php
require_once ('config.php');
require_once DOCUMENT_ROOT.'/setup/start.php';
$breadcrumb_items = array('Home');

if(is_null($UserAccount) || !is_a($UserAccount,'useraccount') || !$UserAccount->logged_in)
{
	header('Location: login.php');
	exit;
} else {
	// Include the invoice page which uses the component-based structure
	require_once('invoice.php');
}
?>
