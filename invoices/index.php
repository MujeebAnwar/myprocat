<?php
require_once ('config.php');
require_once DOCUMENT_ROOT.'/setup/start.php';

if(is_null($UserAccount) || !is_a($UserAccount,'useraccount') || !$UserAccount->logged_in)
{
	header('Location: /logout.php');
	exit;
}

// Include the invoice page which uses templateV2 mainframe
require_once('invoice.php');
?>
