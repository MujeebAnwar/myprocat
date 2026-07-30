<?php
require_once ('config.php');
require_once DOCUMENT_ROOT.'/setup/start.php';
require_once DOCUMENT_ROOT.'/templateV2/roomlist.php';


$breadcrumb_items = array('Home');

if(is_null($UserAccount) || !is_a($UserAccount,'useraccount') || !$UserAccount->logged_in)
{
	// $set_body = array(
	// 	new row(
	// 		new paragraph('Please log in to see our private resource rooms',array('class'=>'importantmessage'))
	// 		,array('class' => 'whitebox')),
	// 	new roomlist($UserAccount,$DB)
	// 	);

	header('Location: /signup/login.php');
	exit;
} else {
	$set_body = new roomlist($UserAccount,$DB);
}



// Delete room functionality

if(isset($_POST['action']) && isset($_POST['id_room']) && $_POST['action'] === 'delete_room') {
	// Check if user is logged in and is admin

	if(is_null($UserAccount) || !is_a($UserAccount,'useraccount') || !$UserAccount->logged_in || !$UserAccount->user_details['is_admin'])
	{
		header('Location: resources.php');
		exit;
	}

	// Get room ID from URL
	$id_room = isset($_POST['id_room']) ? intval($_POST['id_room']) : 0;
	if($id_room <= 0) {
		header('Location: resources.php');
		exit;
	}


	// Get files in the room
	$files = array('count');
	$file_count = 0;
	$DB->sql(
		'SELECT COUNT(id_file) as "count" FROM filelist WHERE id_room = ?',
		array('i', $id_room),
		$files
	);

	$file_count = isset($files[0]['count']) ? $files[0]['count'] : 0;

	if($file_count > 0) {
		// $set_body = 
		$error = "You cannot delete a room with files in it.";
		$set_body->unshift(new paragraph($error,array("class" => "errormessage")));
	} else {
		// Delete the room
		$DB->sql(
			'DELETE FROM rooms WHERE id_room = ?',
			array('i', $id_room)
		);
	}

}

$set_title = "Myprocat.com: Resource Rooms";
require_once (DOCUMENT_ROOT.'/templateV2/mainframe/mainframe.php');
?>
