<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/login.php');
require_once (DOCUMENT_ROOT.'/setup/force_authorized.php');
require_once (DOCUMENT_ROOT.'/lib/adminactions.php');
require_once (DOCUMENT_ROOT.'/lib/file_details.php');
require_once (DOCUMENT_ROOT.'/template/form.php');
require_once (DOCUMENT_ROOT.'/lib/Util.php');
if(is_array($_POST) 
	&& array_key_exists('id_file',$_POST)
	&& is_numeric($_POST['id_file'])
	)
{
	$fp = new file_details($DB,$UserAccount,$_POST['id_file']);
	$content = new paragraph("Edit File:",array('class' => 'importantmessage'));
	if($fp->can_upload())
	{
		$filename = $fp->get_filename();
		$description = $fp->get_description();
		$fa = new action_file_edit($DB,$fp);
		$form = new form(NULL,array('method' => 'POST'));
		$form->push(new input(NULL,
			array('name'=> 'id_file', 'type'=>'hidden','value' => $fp->id_file)));
		if(array_key_exists('id_room',$_POST) 
					&& $_POST['id_room'] !== "" 
					)
				{
					$form->push(new input(NULL,
						array('name'=> 'id_room', 'type'=>'hidden','value' => $_POST['id_room'])));
				}
		$form->push(new field("filename",
			array('type' => 'text','class' => 'edit_filename','arrange' => 'vertical','value' => $filename)));
		$form->push(new textfield("description",
			array('class' => 'edit_file_description','arrange' => 'vertical','value' => $description)));
		$form->push(new submit("Submit Changes",array('class' => 'edit_file')));
		if($fa->check_requirements() === FORM_STATUS_OK)
		{
			if($fa->submit() === FORM_STATUS_OK)
			{
				if(array_key_exists('id_room',$_POST) 
					&& $_POST['id_room'] !== "" 
					)
				{
					$content->push(new paragraph("Success!",array('class' => 'importantmessage')));
					$content->push(new paragraph("You will now be returned to the room.",array("class" => 'message')));
					$content->push(new delayjump("'/room_view.php?id=".$_POST['id_room']."'",2));
				} else {
					$content->push($form);
				}
			} else {
				$content->push(new paragraph($fa->error,array('class'=>'errormessage')));
				$content->push($form);
			}
		} else {
			$content->push($form);
		}
	} else {
		$content->push(new paragraph("You don't have permission to modify this file", array('class' => 'errormessage')));
	}	

} else {
	GoToPage('/error.html');
}
$set_body = $content;
require DOCUMENT_ROOT.'/template/mainframe.php';

?>