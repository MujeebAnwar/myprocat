<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/login.php');
require_once (DOCUMENT_ROOT.'/setup/force_authorized.php');
require_once (DOCUMENT_ROOT.'/setup/force_admin.php');
require_once (DOCUMENT_ROOT.'/lib/adminactions.php');
require_once (DOCUMENT_ROOT.'/template/form.php');
require_once (DOCUMENT_ROOT.'/template/roomlist.php');
require_once (DOCUMENT_ROOT.'/template/roomicon.php');

class room_move_up_button extends content_block
{
	private $file1;
	private $file2;
	public function __construct($file1,$file2)
	{
		$this->file1 = $file1;
		$this->file2 = $file2;
		$content = new content_block(NULL,'div',array('class' => 'admin_up_button'));
		$ff = new form(NULL,array('method'=>'POST','action'=>'#room_'.$file1));
		$ff->push(new input(NULL,array('type' => 'hidden', 'name' => 'action', 'value' => 'move_up')));
		$ff->push(new input(NULL,array('type' => 'hidden','name'=> 'id_room','value'=> $file1)));
		$ff->push(new input(NULL,array('type' => 'hidden','name'=> 'id_room_2','value'=> $file2)));
		$ff->push(new image_submitbutton('/img/room_uparrow.png',20,30));
		$content->push($ff);
		parent::__construct($content,'raw',array());
	}
}
class room_move_down_button extends content_block
{
	private $file1;
	private $file2;
	public function __construct($file1,$file2)
	{
		$this->file1 = $file1;
		$this->file2 = $file2;
		$content = new content_block(NULL,'div',array('class' => 'admin_up_button'));
		$ff = new form(NULL,array('method'=>'POST','action'=>'#room_'.$file1));
		$ff->push(new input(NULL,array('type' => 'hidden', 'name' => 'action', 'value' => 'move_down')));
		$ff->push(new input(NULL,array('type' => 'hidden','name'=> 'id_room','value'=> $file1)));
		$ff->push(new input(NULL,array('type' => 'hidden','name'=> 'id_room_2','value'=> $file2)));
		$ff->push(new image_submitbutton('/img/room_downarrow.png',20,30));
		$content->push($ff);
		parent::__construct($content,'raw',array());
	}
}
class newroom_form extends content_block
{
	private $file1;
	private $file2;
	public function __construct()
	{
		$content = new content_block(NULL,'div',array('class' => 'admin_up_button'));
		$ff = new form(NULL,array('method'=>'POST','action'=>'#room_top'));
		$ff->push(new input(NULL,array('type' => 'hidden', 'name' => 'action', 'value' => 'create_new')));
		$ff->push(new input(NULL,array('type' => 'hidden','name'=> 'id_room','value'=> 'new')));
		$ff->push(new image_submitbutton('/img/new_room.png',250,75));
		$content->push($ff);
		$content->push(new anchor(NULL,array('name' => 'room_top')));
		parent::__construct($content,'raw',array());
	}
}
class deleteroom_form extends content_block
{
	private $file1;
	private $file2;
	public function __construct($id_room)
	{
		$content = new content_block(NULL,'div',array('class' => 'deleteroom_button'));
		$ff = new form(NULL,array('method'=>'POST','action'=>'#room_top'));
		$ff->push(new input(NULL,array('type' => 'hidden', 'name' => 'action', 'value' => 'delete_room')));
		$ff->push(new input(NULL,array('type' => 'hidden','name'=> 'id_room','value'=> $id_room)));
		$ff->push(new image_submitbutton('/img/deletebutton.png',40,30));
		$content->push($ff);
		$content->push(new anchor(NULL,array('name' => 'room_top')));
		parent::__construct($content,'raw',array());
	}
}
class edit_room_form extends content_block
{
	private $room_data;
	public function __construct($room_data,$prev_id,$next_id)
	{
		$this->room_data = $room_data;
		$rowparams = array("class" => "roomedit_container");
		$rowparams['style'] = 'clear: left;overflow:none';
		$content = new content_block(NULL,'div',$rowparams);
		$content->push(new anchor(NULL,array('name' => 'room_'.$room_data['id_room'])));
		$form = new form(NULL,array('method' => 'POST','action' => '#room_'.$room_data['id_room']));
		$sec1 = new section(NULL,array("class" => "roomedit_left"));
		$sec1->push(new input(NULL,array('type' => 'hidden', 'name' => 'id_room', 'value' => $room_data['id_room'])));
		$sec1->push(new input(NULL,array('type' => 'hidden', 'name' => 'action', 'value' => 'edit')));
		$sec1->push(new field('Room Title',array('class'=> 'roomedit', 'arrange'=> 'vertical','value' => $room_data['title'])));
		$sec1->push(new field('Alt Title',array('class'=> 'roomedit', 'arrange'=> 'vertical','value' => $room_data['vis_title'])));
		$sec1->push(new textfield("Room Description",
			array('class' => 'edit_room_description','arrange' => 'vertical','value' => $room_data['description']))
		);
		$atr = array('class'=> 'roomedit','type' => 'checkbox','arrange'=> 'vertical');
		if($room_data['is_public'])
		{
			$atr['checked'] = "checked";
		}

		$sec1->push(new field('Public Room',$atr));
		$sec1->push(new submit('Submit Changes',$atr));
		$form->push($sec1);
		$sec2 = new section(NULL,array("class" => "roomedit_center"));
		$sec2->push(new field('Background',array('class'=> 'roomedit','arrange'=> 'vertical','value' => $room_data['background'])));
		$sec2->push(new field('Foreground',array('class'=> 'roomedit','arrange'=> 'vertical','value' => $room_data['foreground'])));
		
		$roomref = new roomicon($room_data);
		$roomref->add_style('margin-left:100px;margin-top:20px');
		$sec2->push($roomref);
		$form->push($sec2);
		$sec3 = new section(NULL,array("class" => "roomedit_right"));
		if(!is_null($prev_id))
		{
			$sec3->push( new room_move_up_button($room_data['id_room'],$prev_id));
		}
		if(!is_null($next_id))
		{
			$sec3->push( new room_move_down_button($room_data['id_room'],$next_id));
		}
		$content->push($form);
		$content->push($sec3);
		$content->push(new deleteroom_form($room_data['id_room']));
		
		parent::__construct($content);

	}

}

class roomlist_edit extends roomlist
{
	public function __construct($userOb = NULL,$DB = NULL)
	{
		if(is_null($DB) || !is_a($DB,'databaseI'))
		{
			exit("Invalid constructor for roomlist, missing database interface");
		}
		if(is_null($userOb) || !is_a($userOb,'useraccount'))
		{
			exit("Invalid constructor for roomlist, missing database interface");
		}
		if($userOb->user_details['is_admin'])
		{
			$this->userOb = &$userOb;
			$this->DB = &$DB;
			$this->fetch_list();
			$content = new section(NULL,array('class' => 'room_list'));

			$content->push(new newroom_form());
			$above = NULL;
			$current = NULL;
			$prevData = NULL;
			foreach($this->roomlist AS $roomdata)
			{
				
				if(!is_null($prevData))
				{
					$entry = new edit_room_form($prevData,$above,$roomdata['id_room']);
					$content->push($entry);
					$above = $prevData['id_room'];
				}
				$prevData = $roomdata;
			}
			if(!is_null($prevData))
			{
				$entry = new edit_room_form($prevData,$above,NULL);
				$content->push($entry);
			}
		} else 
		{
			$content = new paragraph("You do not have permission to access this page.");
		}
		content_block::__construct($content);
	}
}
if($Session->valid && $UserAccount->logged_in && $UserAccount->user_details['is_admin'])
{
	$set_title = "Myprocat.com: Edit Rooms";
	if(is_array($_POST) )
	{
		if(array_key_exists('action',$_POST))
		{
			if($_POST['action'] === 'edit')
			{
				$checked = 0;
				if(array_key_exists('Public_Room',$_POST))
				{
					$checked = 1;
				}
				$DB->sql(
					'UPDATE rooms '.
					'SET `room_title`=?,`vis_room_title`=?, `room_description`=?,`background`=?,`foreground`=?,`is_public`=? '.
					'WHERE `id_room`=?',
					array('sssssii',$_POST['Room_Title'],$_POST['Alt_Title'],$_POST['Room_Description'],$_POST['Background'],$_POST['Foreground'],$checked,$_POST['id_room'])
					);
			} else if(($_POST['action'] === 'move_up' || $_POST['action'] === 'move_down') && ( array_key_exists('id_room_2',$_POST)&& array_key_exists('id_room',$_POST))){
					$DB->sql(
						"UPDATE rooms
							SET `order` = 
				            (
								SELECT * FROM 
				                (
				                    SELECT SUM(`order`) FROM `rooms` WHERE `id_room` IN (?, ?)
				                )  AS `_T`
				            ) - `order`
						WHERE `id_room` IN (?, ?)",
						array('iiii',$_POST['id_room'],$_POST['id_room_2'],$_POST['id_room'],$_POST['id_room_2'])
						);	

			} else if($_POST['action'] ==='create_new'){
				$DB->sql(
						"UPDATE rooms SET `order` = `order`+1",
						array('')
						);
				$DB->sql(
					'INSERT INTO rooms (`room_title`) VALUES (\'New Room\')',
					array('')
					);

			}else if($_POST['action'] ==='delete_room' && array_key_exists('id_room',$_POST)){
				$DB->sql(
					'DELETE FROM rooms WHERE `id_room`=?',
					array('i',$_POST['id_room'])
					);
			}
		}
	}
	$set_body = new roomlist_edit($UserAccount,$DB);

} else {
	$set_title = "Myprocat.com: Error";
	$set_body = new paragraph("You do not have permission to use this page.");
}
require DOCUMENT_ROOT.'/template/mainframe.php';
?>