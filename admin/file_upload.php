<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/login.php');
if(!($Session->valid && $UserAccount->logged_in))
{
	GoToPage('/logout.php');
	//var_dump($Session);
}

require_once (DOCUMENT_ROOT.'/lib/adminactions.php');
require_once (DOCUMENT_ROOT.'/lib/file_details.php');
if(is_array($_POST) && is_array($_FILES)
	&& array_key_exists('UploadFile',$_FILES)
	&& array_key_exists('title',$_POST)
	&& array_key_exists('description',$_POST)
	&& array_key_exists('id_room',$_POST)
	&& is_numeric($_POST['id_room'])
	)
{
	$fp = new new_file_details($DB,$UserAccount);
	$fp->fetch_results();
	if($fp->can_upload($_POST['id_room']) || $UserAccount->user_details['is_admin'])
	{

		if(!file_exists(DOCUMENT_ROOT.'/data/'.$_POST['id_room']))
		{
			mkdir(DOCUMENT_ROOT.'/data/'.$_POST['id_room'],0755);
		}
		$target_file = DOCUMENT_ROOT.'/data/'.$_POST['id_room'].'/'.$fp->id_file;

		if (move_uploaded_file($_FILES["UploadFile"]["tmp_name"], $target_file)) 
		{
			// Upload success,

			// Move things in the list down a space
			$success = $DB->sql('UPDATE `filelist` SET `order` = `order`+1 WHERE `id_room` = ?',array('i',$_POST['id_room']));
			// Insert the new item into the database
			
			
			$filename = $_FILES['UploadFile']['name'];

			if($DB->sql(
				'INSERT INTO `filelist` (`id_filelist`, `id_file`, `id_room`, `title`, `filename`, `description`, `time_stamp`, `order`) '.
				'VALUES (NULL, ?, ?, ?, ?, ?, NOW(), 0)',
				array('iisss',
					$fp->id_file,$_POST['id_room'],$_POST['title'],$filename,$_POST['description']
					)
				))
				{
					// Success
				}


		} 
	}

}
require_once(DOCUMENT_ROOT."/setup/reflect.php");
?>