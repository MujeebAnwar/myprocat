<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/login.php');
require_once (DOCUMENT_ROOT.'/setup/force_authorized.php');
require_once (DOCUMENT_ROOT.'/setup/force_admin.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once (DOCUMENT_ROOT.'/template/form.php');
require_once (DOCUMENT_ROOT.'/lib/Util.php');
class promotions_entry extends content_block
{

}

if($Session->valid && $UserAccount->logged_in && $UserAccount->user_details['is_admin'])
{
	$error = "";
	$set_body = new section();
	$set_body->push(new paragraph("Edit Promotions:",array('class' => 'importantmessage')));
	$form_mode = "Add";
	$populate = array(
		'id_promo' => NULL,
		'promo_name' => NULL,
		'promo_description' => NULL,
		'expiry' => NULL
		);
	$now = array('ctime');
	$DB->sql("SELECT NOW()",array(),$now);
	$nowstring = $now[0]['ctime'];
	array_populate($populate,'id_promo');
	array_populate($populate,'promo_name','Promotion_Name');
	array_populate($populate,'promo_description','Description');
	array_populate($populate,'expiry','Expiration');
	if(is_array($_POST) && array_key_exists("action",$_POST))
	{
		switch($_POST['action'])
		{
			case 'clear':
			{
				$populate = array(
				'id_promo' => NULL,
				'promo_name' => NULL,
				'promo_description' => NULL,
				'expiry' => NULL
				);
				$form_mode = 'Add';
				break;
			}
			case 'new':
			{
				$fields = array_keys($populate);

				$sqlfields = '('.implode(',',$fields).')';
				$sqlparams = array("isss");
				array_splice($sqlparams,1,0,array_values($populate));
				if($DB->sql(
					'INSERT INTO promotions '.$sqlfields.
					'VALUES (?,?,?,?)',
					$sqlparams
					))
				{
					$populate['id_promo'] = $DB->iid();
					$form_mode = "Edit";
				} else {
					$error = "Failed to add new record.";
				}
				break;
			}
			case 'modify':
			{
				if($DB->sql(
					'UPDATE promotions SET '.
					'promo_name = ?, '.
					'promo_description = ?, '.
					'expiry = ? '.
					'WHERE id_promo = ?',
					array('sssi',$populate['promo_name'],
						$populate['promo_description'],
						$populate['expiry'],
						$populate['id_promo']
						)
					))
				{
					$form_mode = "Edit";
				} else {
					$form_mode = "Edit";
					$error = "Failed to update record.";
				}
				break;
			}
			case 'select':
			{
				$results = array(
				'id_promo',
				'promo_name',
				'promo_description',
				'expiry'
				);
				if($DB->sql("SELECT id_promo,promo_name,promo_description,expiry FROM promotions WHERE id_promo=?",
					array('i',$populate['id_promo']),
					$results
					))
				{
					$populate = $results[0];
					$form_mode = "Edit";
				} else {
					$populate = array(
						'id_promo' => NULL,
						'promo_name' => NULL,
						'promo_description' => NULL,
						'expiry' => NULL
						);
					$error = "Unable to locate record.";
				}
				break;
			}

		}
	}
	$promotions_list = array(
		'id_promo',
		'promo_name',
		'promo_description',
		'expiry',
		'is_expired'
		);
	if(!$DB->sql("SELECT id_promo,promo_name,promo_description,expiry,expiry<NOW() AS is_expired FROM promotions ORDER BY is_expired ASC",
		array(),
		$promotions_list
		))
	{
		$promotions_list = NULL;
	}

	$promo_registrations = NULL;
	if(!is_null($populate['id_promo']))
	{
		$promo_registrations = array(
		'id_registration',
		'first_name',
		'last_name',
		'email',
		'registration_code',
		'time',
		'is_redeemed'
		);
		if(!$DB->sql('SELECT id_registration,first_name,last_name,email,registration_code,time,is_redeemed '.
			'FROM promo_registrations '.
			'WHERE id_promo = ?',
		array('i',$populate['id_promo']),
		$promo_registrations
		))
		{
			$promo_registrations = NULL;
		}
	}
	if($error !== "")
	{
		$set_body->push(new paragraph($error,array('class' => 'errormessage')));
	}
	$form = new form(NULL,array('method'=>'POST'));
	if($form_mode === "Edit")
	{
		$form->push(new input(NULL,array('type'=>'hidden','name'=>'id_promo','value'=>$populate['id_promo'])));
		$form->push(new input(NULL,array('type'=>'hidden','name'=>'action','value'=>'modify')));
	} else {
		$form->push(new input(NULL,array('type'=>'hidden','name'=>'action','value'=>'new')));
	}
	$form->push(new field('Promotion Name',array('class'=>'edit_user','arrange'=>'vertical','value'=>$populate['promo_name'])));
	$form->push(new textfield('Description',array('class'=>'edit_promo_desc','arrange'=>'vertical','value'=>$populate['promo_description'])));
	$form->push(new field('Expiration',array('class'=>'edit_user','arrange'=>'vertical','value'=>$populate['expiry'])));
	$form->push(new row(new paragraph('(Server time: '.$nowstring.')',array('class'=>'datereminder')),array('class'=>'datereminder')));
	$form->push(new submit($form_mode,array('class'=>'edit_user','arrange'=>'vertical')));
	$set_body->push($form);
	if($form_mode === "Edit")
	{
		$form = new form(NULL,array('method'=>'POST'));
		$form->push(new input(NULL,array('type'=>'hidden','name'=>'action','value'=>'clear')));
		$form->push(new submit('Clear',array('class'=>'edit_user','arrange'=>'vertical')));
		$set_body->push($form);
	}
	if(is_array($promotions_list))
	{
		$set_body->push(new paragraph("Active Promotions:",array('class'=>'message')));
		$expired_header = true;
		foreach($promotions_list as $promo)
		{
			
			if($expired_header && $promo['is_expired'])
			{
				$set_body->push(new paragraph("Expired Promotions:",array('class'=>'message')));
				$expired_header = false;
			}
			$form = new form(NULL,array('method' => 'POST','id'=>'select_'.$promo['id_promo']));
			$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'id_promo','value'=>$promo['id_promo'])));
			$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'action','value'=>'select')));
			$params = array();
			$params['class'] = 'registration_item';
			$params['onclick'] = 'document.forms["'.'select_'.$promo['id_promo'].'"].submit()';
			if($form_mode === "Edit" && $populate['id_promo'] === $promo['id_promo'])
			{
				$params['style'] = 'background-color:orange';
			}
			$fp = new paragraph(
					$promo['promo_name'].": ".$promo['expiry'].":  ",
					$params);
			$params = array('href'=>'/admin/export_promo.php?id_promo='.$promo['id_promo']);
			$params['onclick'] = "event.stopPropagation();";
			$fp->push(new anchor('Export',$params));
			$fp->push(":  ");
			$params = array('target'=>'_blank','href'=>'/promo.php?offer='.$promo['id_promo']);
			$params['onclick'] = "event.stopPropagation();";
			$fp->push(new anchor('URL',$params));
			$form->push($fp);
			$set_body->push($form);
			if($form_mode === "Edit" && $populate['id_promo'] === $promo['id_promo'] && !is_null($promo_registrations))
			{
				$reglist = new row(NULL,array('class' => 'registration_list'));
				$reglist->push(new paragraph("Registrations:",array('class' => 'registration_entry')));
				foreach($promo_registrations AS $reg)
				{
					$reglist->push(
						new paragraph(
							$reg['first_name'].' '.$reg['last_name'].', '.$reg['email'].": ".$reg['registration_code'],
							array('class'=>'registration_entry'))
						);
				}
				$set_body->push($reglist);

			}
			
		}
	}
}
require_once(DOCUMENT_ROOT.'/template/mainframe.php');

?>
