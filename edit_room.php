<?php
require_once ('config.php');
require_once DOCUMENT_ROOT.'/setup/start.php';
require_once DOCUMENT_ROOT.'/templateV2/edit_room.php';

$breadcrumb_items = array('Home');

// Check if user is logged in and is admin
if(is_null($UserAccount) || !is_a($UserAccount,'useraccount') || !$UserAccount->logged_in || !$UserAccount->user_details['is_admin'])
{
	header('Location: resources.php');
	exit;
}

// Get room ID from URL
$id_room = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($id_room <= 0) {
	header('Location: resources.php');
	exit;
}

// Fetch existing room data
$roomData = array('id_room', 'room_title', 'room_description', 'vis_room_title', 'background', 'foreground', 'order', 'is_public');
$DB->sql(
	'SELECT id_room, room_title, room_description, vis_room_title, background, foreground, `order`, is_public FROM rooms WHERE id_room = ?',
	array('i', $id_room),
	$roomData
);

if(empty($roomData) || count($roomData) == 0) {
	header('Location: resources.php');
	exit;
}

$currentRoom = $roomData[0];

// Handle form submission
if($Session->valid && $UserAccount->logged_in && $UserAccount->user_details['is_admin'])
{
	if(isset($_POST['update_room']) && array_key_exists('room_name', $_POST) && array_key_exists('room_description', $_POST) && array_key_exists('id_room', $_POST))
	{
		$update_id = intval($_POST['id_room']);
		
		$is_public = isset($_POST['is_public']) ? 1 : 0;
		$DB->sql(
			'UPDATE rooms SET room_title = ?, room_description = ?, is_public = ? WHERE id_room = ?',
			array('ssii', $_POST['room_name'], $_POST['room_description'], $is_public, $update_id)
		);
		
		header('Location: resources.php');
		exit;
	}
}

// Display the edit form with existing data
$set_body = new edit_room($UserAccount, $DB, $currentRoom);
$breadcrumb_items[] = ['text' => $currentRoom['room_title'], 'url' => 'resource_detail.php?id=' . $currentRoom['id_room']];
$set_title = "Myprocat.com: Edit Room";
require_once (DOCUMENT_ROOT.'/templateV2/mainframe/mainframe.php');
?>
