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

if(is_array($_POST)
	&& array_key_exists('id_room',$_POST)
	&& is_numeric($_POST['id_room'])
	&& array_key_exists('id_file',$_POST)
	&& is_numeric($_POST['id_file'])
	)
{
	$fp = new file_details($DB,$UserAccount,$_POST['id_file']);
	$fp->fetch_results();
	if($fp->can_remove() || $UserAccount->user_details['is_admin'])
	{
		if($DB->sql('DELETE FROM `filelist` WHERE id_file = ? AND id_room = ?',
			array('ii',$_POST['id_file'],$_POST['id_room'])
			))
		{
			unlink(DOCUMENT_ROOT.'/data/'.$_POST['id_room'].'/'.$_POST['id_file']);
			require_once(DOCUMENT_ROOT."/setup/reflect.php");
		}

	}
}
GoToPage('/logout.php');
?>
