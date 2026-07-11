<?php
require_once ('config.php');
require_once DOCUMENT_ROOT.'/setup/start.php';
require_once DOCUMENT_ROOT.'/setup/force_authorized.php';
require_once DOCUMENT_ROOT.'/template/Master.php';
require_once DOCUMENT_ROOT.'/template/room.php';
require_once DOCUMENT_ROOT.'/lib/file_details.php';
$params = array();
$params['user'] = &$UserAccount;
$params['DB'] = &$DB;
$set_body = new section();
$set_body->push(
	new paragraph("You are logged in as ".$UserAccount->user_details['first_name']." ".$UserAccount->user_details['last_name'],array("class"=>"message"))
	);
$fp = new new_file_details($DB,$UserAccount);
$results = $fp->fetch_results();
if(count($results) > 0)
{
$set_body->push(
	new paragraph("You have been granted access to the following rooms:",array("class"=>"message"))
	);
	foreach($results AS $perm)
	{
		if($perm['can_read'] || $perm['is_public'])
		{
			$rm = new room($perm['id_room'],array('DB' => $DB));
			$permstring = new paragraph($rm->title,
				array("class"=>"permission_item", 'onclick' => 'document.location.replace("/room_view.php?id='.$perm['id_room'].'");')
				);
			$a = new anchor($permstring,array('class' => "permission_item", "href" => "/room_view.php?id=".$perm['id_room']));
			if(!is_null($perm['expires']))
			{
				$permstring->push('(expires:'.$perm['expires'].')');
			}
			if($perm['can_upload']){
				$permstring->push(": Upload files");
			}
			if($perm['can_remove']){
				$permstring->push(", Delete files");
			}
			$set_body->push($a);
		}
		
	}
}
$set_title = "Myprocat.com: Account details";
require_once (DOCUMENT_ROOT.'/template/mainframe.php');
?>