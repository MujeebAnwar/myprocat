<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/login.php');
require_once (DOCUMENT_ROOT.'/setup/force_admin.php');
require_once (DOCUMENT_ROOT.'/lib/adminactions.php');
require_once (DOCUMENT_ROOT.'/lib/file_details.php');
if(is_array($_POST) 
	&& array_key_exists('id_room_1',$_POST)
	&& array_key_exists('id_room_2',$_POST)
	&& is_numeric($_POST['id_room_1'])
	&& is_numeric($_POST['id_room_2'])
	)
{

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
		array('iiii',$_POST['id_room_1'],$_POST['id_room_2'],$_POST['id_room_1'],$_POST['id_room_2'])
		);	
}

require_once (DOCUMENT_ROOT.'/setup/reflect.php');
?>
