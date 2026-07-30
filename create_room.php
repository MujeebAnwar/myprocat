<?php
require_once ('config.php');
require_once DOCUMENT_ROOT.'/setup/start.php';
require_once DOCUMENT_ROOT.'/templateV2/new_room.php';


$breadcrumb_items = array('Home');
$breadcrumb_items[] = array('text' => 'Add Room', 'url' => 'create_room.php');

if(is_null($UserAccount) || !is_a($UserAccount,'useraccount') || !$UserAccount->logged_in)
{
	$set_body = array(
		new row(
			new paragraph('Please log in to see our private resource rooms',array('class'=>'importantmessage'))
			,array('class' => 'whitebox')),
		new new_room($UserAccount,$DB)
		);
} else {
	$set_body = new new_room($UserAccount,$DB);
}

// New Room

if($Session->valid && $UserAccount->logged_in && $UserAccount->user_details['is_admin'])
{
	if(isset($_POST) )
	{
		if(isset($_POST['save_room']) && array_key_exists('room_name',$_POST) && array_key_exists('room_description',$_POST))
		{

			if($_POST['room_name'] == '') {
				$error = 'Room name is required';
			}
			if($_POST['room_description'] == '') {
				$error = 'Room description is required';
			}
			if($error) {
				$set_body = new new_room($UserAccount,$DB,$error);
			}else{
				$maxOrder = array('order');
				$room_lookup = array();
				$DB->sql(
					'SELECT Max(`order`) as "order" FROM rooms',
					array(),
					$maxOrder
				);
				// $max_order = $DB->last_results[0]['MAX(order)'];
				// var_dump($maxOrder[0]['order']);die;
	
				$maxOrder = $maxOrder[0]['order'] + 1;
				$DB->sql(
					'INSERT INTO rooms (`room_title`,`room_description`,`vis_room_title`,`background`,`foreground`,`order`) VALUES (?,?,?,?,?,?)',
					array('sssssi', $_POST['room_name'], $_POST['room_description'],'','','',$maxOrder)
				);
				header('Location: resources.php');
			}
			
		}
	}
}
$set_title = "Myprocat.com: Resource Rooms";
require_once (DOCUMENT_ROOT.'/templateV2/mainframe/mainframe.php');
?>
