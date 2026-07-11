<?php
require_once ('config.php');
require_once DOCUMENT_ROOT.'/setup/start.php';
require_once DOCUMENT_ROOT.'/template/Master.php';
require_once DOCUMENT_ROOT.'/template/roomlist.php';
require_once DOCUMENT_ROOT.'/setup/force_authorized.php';

if(is_null($UserAccount) || !is_a($UserAccount,'useraccount') || !$UserAccount->logged_in)
{
	$set_body = array(
		new row(
			new paragraph('Please log in to see our private resource rooms',array('class'=>'importantmessage'))
			,array('class' => 'whitebox')),
		new roomlist($UserAccount,$DB)
		);
} else {
	$set_body = new roomlist($UserAccount,$DB);
}
$set_title = "Myprocat.com: Resource Rooms";
require_once (DOCUMENT_ROOT.'/template/mainframe.php');

?>