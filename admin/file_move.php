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
	&& array_key_exists('id_file_1',$_POST)
	&& array_key_exists('id_file_2',$_POST)
	&& array_key_exists('id_room',$_POST)
	&& is_numeric($_POST['id_file_1'])
	&& is_numeric($_POST['id_file_2'])
	&& is_numeric($_POST['id_room'])
	)
{
	$fp = new file_details($DB,$UserAccount,$_POST['id_file_1']);
	if($fp->can_upload())
	{
		$fa = new form_room_file_swap($DB,$fp,$_POST['id_file_1'],$_POST['id_file_2']);
		if($fa->submit() == FORM_STATUS_OK)
		{
			// Submit Succesful~ go back home.
			//require_once (DOCUMENT_ROOT.'/setup/reflect.php');
		}
	}	
}

require_once (DOCUMENT_ROOT.'/setup/reflect.php');
?>
