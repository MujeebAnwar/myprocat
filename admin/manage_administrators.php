<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/login.php');
require_once (DOCUMENT_ROOT.'/setup/force_authorized.php');
require_once (DOCUMENT_ROOT.'/setup/force_admin.php');
require_once (DOCUMENT_ROOT.'/lib/adminactions.php');
require_once (DOCUMENT_ROOT.'/template/form.php');
require_once (DOCUMENT_ROOT.'/template/roomlist.php');

if($Session->valid && $UserAccount->logged_in && $UserAccount->user_details['is_admin'])
{
	if(is_array($_POST) && array_key_exists('action',$_POST))
	{
		if($_POST['action'] === 'add_administrator')
		{

			$DB->sql(
				'INSERT INTO administrators (id_user,is_admin) VALUES (?,1)',
				array('s',$_POST['Add'])
				);

		} else if($_POST['action'] === 'delete_administrator'){
			$DB->sql(
				'DELETE FROM administrators WHERE id_user=?',
				array('s',$_POST['id_user'])
				);

		}
	}
	$set_title = "Myprocat.com: Manage Administrators";
	$set_body = new section();
	$set_body->push(new paragraph('Administrators',array('class' => 'importantmessage')));
	$form = new form(NULL,array('method' => 'POST'));
	$form->push(new field('Add',array('class' => 'addadmin')));
	$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'action','value'=>'add_administrator')));
	$form->push(new submit('Submit'));
	$set_body->push($form);
	$results = array('id_admin','first','last');
	$DB->sql(
		'SELECT administrators.id_user,accounts.first_name,accounts.last_name FROM administrators '.
		'LEFT JOIN accounts ON administrators.id_user=accounts.id_user',
		array(),
		$results
		);
	$set_body->push(new paragraph());
	$sec = new content_block(NULL,'div');
	foreach($results AS $r)
	{
		$line = new row(NULL);
		$left = new section();
		$form = new form(NULL,array('method' => 'POST'));
		$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'action','value'=>'delete_administrator')));
		$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'id_user','value'=>$r['id_admin'])));
		$form->push(new image_submitbutton('/img/deletebutton.png',40,30));

		$p = new paragraph( $r['id_admin'].": ".$r['first'].' '.$r['last'],array("class"=>"permission_item"));
		$right = new section(NULL,array('style' => 'width:500px'));
		$line->push($left);
		$line->push($right);
		$left->push($form);
		$right->push($p);
		$sec->push($line);

	}
	$set_body->push($sec);
} else {
	$set_title = "Myprocat.com: Error";
	$set_body = new paragraph("You do not have permission to use this page.");
}
require_once(DOCUMENT_ROOT.'/template/mainframe.php');
?>
