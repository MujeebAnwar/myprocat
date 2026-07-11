<?php
require_once ('config.php');
require_once DOCUMENT_ROOT.'/setup/start.php';
require_once DOCUMENT_ROOT.'/templateV2/edit_file.php';

$breadcrumb_items = array('Home');

// Check if user is logged in
if(is_null($UserAccount) || !is_a($UserAccount,'useraccount') || !$UserAccount->logged_in)
{
	header('Location: index.php');
	exit;
}

// Get file ID from URL
$id_file = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($id_file <= 0) {
	header('Location: resources.php');
	exit;
}

// Fetch existing file data
$fileData = array('id_file', 'title', 'filename', 'description', 'id_room');
$DB->sql(
	'SELECT id_file, title, filename, description, id_room FROM filelist WHERE id_file = ?',
	array('i', $id_file),
	$fileData
);

if(empty($fileData) || count($fileData) == 0) {
	header('Location: resources.php');
	exit;
}

$currentFile = $fileData[0];

// Check permissions using file_details
require_once DOCUMENT_ROOT.'/lib/file_details.php';
$fp = new file_details($DB, $UserAccount, $id_file);
if(!$fp->can_upload()) {
	header('Location: resources.php');
	exit;
}

// Handle form submission
if($Session->valid && $UserAccount->logged_in)
{
	if(isset($_POST['update_file']) && array_key_exists('title', $_POST) && array_key_exists('description', $_POST) && array_key_exists('id_file', $_POST))
	{
		$update_id = intval($_POST['id_file']);
		$id_room = isset($_POST['id_room']) ? intval($_POST['id_room']) : 0;
		
		$DB->sql(
			'UPDATE filelist SET title = ?, description = ?, time_stamp = NOW() WHERE id_file = ?',
			array('ssi', $_POST['title'], $_POST['description'], $update_id)
		);
		
		// Redirect back to the room if id_room is provided
		if($id_room > 0) {
			header('Location: resource_detail.php?id=' . $id_room);
		} else {
			header('Location: resources.php');
		}
		exit;
	}
}

// Display the edit form with existing data
$set_body = new edit_file($UserAccount, $DB, $currentFile);

$breadcrumb_items[] = ['text' => $currentFile['filename'], 'url' => 'resource_detail.php?id=' . $currentFile['id_room']];
$set_title = "Myprocat.com: Edit File";
require_once (DOCUMENT_ROOT.'/templateV2/mainframe.php');
?>
