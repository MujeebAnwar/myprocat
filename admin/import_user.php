<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/login.php');
require_once (DOCUMENT_ROOT.'/setup/force_authorized.php');
require_once (DOCUMENT_ROOT.'/setup/force_admin.php');
require_once (DOCUMENT_ROOT.'/lib/adminactions.php');
require_once (DOCUMENT_ROOT.'/lib/keyless_lib.php');
require_once (DOCUMENT_ROOT.'/template/form.php');
require_once (DOCUMENT_ROOT.'/template/roomlist.php');
if($Session->valid && $UserAccount->logged_in && $UserAccount->user_details['is_admin'])
{
	$set_body = new section();
	$userlist = array();
	$id_selected = NULL;
	$user_info = array();
	$search_results = NULL;
	$permissions = NULL;
	if(is_array($_POST) && array_key_exists('id_selected',$_POST))
	{
		$id_selected = $_POST['id_selected'];
	}
	$set_body->push(new paragraph('Import user from myprocat.com',array('class' => 'importantmessage')));
	if(is_array($_POST) && array_key_exists('action',$_POST))
	{
		switch($_POST['action'])
		{
			case 'import_user':
			if(!is_null($id_selected))
			{
				$xDB = new databaseI('myprocat-remote-ssh-tunnel');
				$acctInfo = array( 
				'id_team','id_user','email','legacyhashword','title',
				'first_name','mid_name','last_name',
				'address','city','state','zip','country',
				'is_active','owns_room','is_manager','is_admin','expires');
				
				if(!$xDB->sql(<<<SQL
SELECT 
`accounts`.`id_team`,`accounts`.`id_user`,`accounts`.`email`,`accounts`.`legacyhashword`,`accounts`.`title`,
`accounts`.`first_name`,`accounts`.`mid_name`,`accounts`.`last_name`,
`accounts`.`address`,`accounts`.`city`,`accounts`.`state`,`accounts`.`zip`,`accounts`.`country`,
`accounts`.`is_active`,`accounts`.`owns_room`,`accounts`.`is_manager`,`accounts`.`is_admin`,`accounts`.`expires`
FROM accounts 
WHERE accounts.id_user = ?
SQL
					,array('s',$id_selected),
					$acctInfo
					)){
						$set_body->push(new paragraph('Unable to get myprocat.com account info for user',['class'=>'errormessage']));
					} else {
						$passwordInfo = ['hash_password','id_user','time_stamp'];
						if(!$xDB->sql(<<<SQL
SELECT `hash_password`,`id_user`,`time_stamp`
FROM passwords
WHERE id_user=?
SQL
						,array('s',$id_selected),
						$passwordInfo))
						{
							$set_body->push(new paragraph('Unable to get myprocat.com account info for user',['class'=>'errormessage']));
						} else {
							if(count($acctInfo) != 1)
							{
								$set_body->push(new paragraph('Invalid myprocat.com account',['class'=>'errormessage']));
							} else{
								$acct = $acctInfo[0];
								$rv = $DB->sql(<<<SQL
INSERT INTO accounts
(`id_team`,`id_user`,`email`,`legacyhashword`,`title`,
`first_name`,`mid_name`,`last_name`,
`address`,`city`,`state`,`zip`,`country`,
`is_active`,`owns_room`,`is_manager`,`is_admin`,`expires`)
VALUES (
?,?,?,?,?,
?,?,?,
?,?,?,?,?,
?,?,?,?,?
) ON DUPLICATE KEY UPDATE
	expires=?,
	email=?
SQL
									,[
									'issss'.'sss'.'sssss'.'iiiis'.'ss',
									$acct['id_team'],
									$acct['id_user'],
									$acct['email'],
									$acct['legacyhashword'],
									$acct['title'],

									$acct['first_name'],
									$acct['mid_name'],
									$acct['last_name'],

									$acct['address'],
									$acct['city'],
									$acct['state'],
									$acct['zip'],
									$acct['country'],

									$acct['is_active'],
									$acct['owns_room'],
									$acct['is_manager'],
									$acct['is_admin'],
									$acct['expires'],
									$acct['expires'],
									$acct['email']
									]
								);
								if($rv === false && strstr($DB->error,"Duplicate entry") === false)
								{
									$set_body->push(new paragraph("Import Error: ".$DB->error,['class'=>'errormessage']));
							    } else { 
							
								foreach($passwordInfo AS $pwd)
								{
									$DB->sql(<<<SQL
INSERT INTO passwords
(`hash_password`,`id_user`,`time_stamp`)
VALUES (?,?,?)
SQL
									,['sss',$pwd['hash_password'],$pwd['id_user'],$pwd['time_stamp']]						
									);
								}
								$set_body->push(new paragraph("Account imported."));
								}
							}
						}
					}
			}
			break;
			case 'selectid':
			if(array_key_exists('Search_For',$_POST))
			{
				$xDB = new databaseI('myprocat-remote-ssh-tunnel');
				$results = 
				array('id_user','email','first_name','last_name','account_expires');
				{
					$searchstring = $_POST['Search_For'];
					$xDB->sql(<<<SQL
SELECT 
accounts.id_user,accounts.email,accounts.first_name,
accounts.last_name,accounts.expires
FROM accounts 
WHERE accounts.id_user = ? 
SQL
						,array('s',$searchstring),
						$results
						);
				} 
				$duplicate = array();
				$set_body->push("<P>Selected: '".$searchstring."'");
				foreach($results AS $res)
				{
					$duplicate[$res['id_user']] = 1;
				}
				if(count($duplicate) === 1 )
				{
					$id_selected = $results[0]['id_user'];
					$user_info = array_intersect_key($results[0], array_flip(array('email','first_name','last_name','account_expires')));
					$permissions = $results;
					
				} else {
					$search_results = $results;
				}
			}
			break;
			case 'search':
			if(array_key_exists('Search_For',$_POST))
			{
				$xDB = new databaseI('myprocat-remote-ssh-tunnel');
				$results = 
				array('id_user','email','first_name','last_name','account_expires');
				//if(strstr($_POST['Search_For'],'@') !== false)
				{
					$searchstring = '%'.$_POST['Search_For'].'%';
					$xDB->sql(<<<SQL
SELECT 
accounts.id_user,accounts.email,accounts.first_name,
accounts.last_name,accounts.expires
FROM accounts 
WHERE accounts.email LIKE ? OR accounts.id_user LIKE ? OR accounts.first_name LIKE ? OR accounts.last_name LIKE ?
SQL
						,array('ssss',$searchstring,$searchstring,$searchstring,$searchstring),
						$results
						);
				} 
				$duplicate = array();
				$set_body->push("<P>Searched: '".$searchstring."', Got ".count($results)." matches.");
				foreach($results AS $res)
				{
					$duplicate[$res['id_user']] = 1;
				}
				if(count($duplicate) === 1 )
				{
					$id_selected = $results[0]['id_user'];
					$user_info = array_intersect_key($results[0], array_flip(array('email','first_name','last_name','account_expires')));
					$permissions = $results;
					
				} else {
					$search_results = $results;
				}

			}
			break;
			
			default:
			
		}

	} 

	$form = new form(NULL,array('method' => 'POST'));
	$params = array('class' => 'edit_user');
	if(is_array($_POST) && array_key_exists('Search_For',$_POST))
	{
		$params['value'] = $_POST['Search_For'];
	}
	if(!is_null($id_selected))
	{
		$params['value'] = $id_selected;
	}
	$form->push(new field('Search For',$params));
	$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'action','value'=>'search')));
	$form->push(new submit('Search'));
	$set_body->push($form);
	if(is_null($id_selected))
	{
		if(is_null($search_results))
		{
		} else {
			$duplicate = array();

			foreach($search_results AS $res)
			{
				if(!array_key_exists($res['id_user'],$duplicate))
				{
					$form = new form(NULL,array('method' => 'POST','id'=>'select_'.$res['id_user']));
					$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'action','value'=>'selectid')));
					$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'Search_For','value'=>$res['id_user'])));
					$params = array();
					$params['class'] = 'permission_item';
					$params['onclick'] = 'document.forms["'.'select_'.$res['id_user'].'"].submit()';
					$form->push(new paragraph($res['id_user'].": ".$res['first_name']." ".$res['last_name']. ", ".$res['email'],$params));
					$set_body->push($form);
				}
				$duplicate[$res['id_user']] = 1;
			}
		}
	} else {
		$set_body->push(new paragraph('Myprocat Account Info:',array('class'=> 'message')));
		
		
		$form = new form(NULL,array('method' => 'POST'));		
		$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'action','value'=>'import_user')));
		$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'id_selected','value'=>$id_selected)));
		foreach($user_info AS $fieldname => $value)
		{
			$form->push(new paragraph( [$fieldname,": ",$value],['class'=>'message']));
		}
		$form->push(new paragraph(new submit('Import User Account',array('class' => 'edit_user'))));
		$set_body->push($form);

	}
	

}
require_once(DOCUMENT_ROOT.'/template/mainframe.php');

?>