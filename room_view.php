<?php
require_once ('config.php');
require_once DOCUMENT_ROOT.'/setup/start.php';
require_once DOCUMENT_ROOT.'/template/Master.php';
require_once DOCUMENT_ROOT.'/template/room.php';
$params = array();
$params['user'] = &$UserAccount;
$params['DB'] = &$DB;
$set_body = NULL;
$set_background = NULL;
if(is_array($_GET) && array_key_exists('id',$_GET) && is_numeric($_GET['id']))
{
	$set_body = new room($_GET['id'],$params);	
	if(!is_null($set_body->background))
	{
		#$set_background = $set_body->background;
	}
	$set_title = "Myprocat.com: ".$set_body->title;
} else {
	$set_body = new paragraph("You've reached this page the wrong way", array('class' => 'importantmessage'));
}
require_once (DOCUMENT_ROOT.'/template/mainframe.php');

?>
</body>