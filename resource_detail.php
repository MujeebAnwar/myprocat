<?php
require_once ('config.php');
require_once DOCUMENT_ROOT.'/setup/start.php';
require_once DOCUMENT_ROOT.'/templateV2/room.php';

// Set up parameters
$params = array();
$params['user'] = &$UserAccount;
$params['DB'] = &$DB;

// Set breadcrumb and page script
$breadcrumb_items = array('Home');
$page_script = "
function showForm() {
    document.getElementById('formContent').style.display = 'block';
    document.getElementById('formContent').scrollIntoView({ behavior: 'smooth' });
}
function hideForm() {
    document.getElementById('formContent').style.display = 'none';
    document.getElementById('formContent').scrollIntoView({ behavior: 'smooth' });
}
";

// Load room with video grid display
if(is_array($_GET) && array_key_exists('id',$_GET) && is_numeric($_GET['id']))
{
	$set_body = new room_video_grid($_GET['id'], $params);	
	
	// Update breadcrumb and title with room name
	$room_title = !is_null($set_body->vis_title) && $set_body->vis_title !== "" ? $set_body->vis_title : $set_body->title;
	$breadcrumb_items[] = $room_title;
	$set_title = "ProCAT Resource Center - " . $room_title;
} else {
	$set_body = new paragraph("Please provide a room ID to view files.", array('class' => 'importantmessage'));
	$breadcrumb_items[] = "Error";
	$set_title = "ProCAT Resource Center - Error";
}

// Render the page
require_once (DOCUMENT_ROOT.'/templateV2/mainframe/mainframe.php');
?>
