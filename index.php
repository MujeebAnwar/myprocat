<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/start.php');
require_once (DOCUMENT_ROOT.'/setup/force_authorized.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');

	$set_body = 
	array(
	new paragraph("Hello ".$UserAccount->user_details['first_name'].",",array('class' => 'importantmessage')),
	new paragraph("Welcome to myprocat.com, please use the menu at the top of the screen to navigate to the resources you need to access.",array('class' => 'message'))
		);

require_once (DOCUMENT_ROOT.'/template/mainframe.php');
?>