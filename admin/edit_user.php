<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/login.php');
require_once (DOCUMENT_ROOT.'/setup/force_authorized.php');
require_once (DOCUMENT_ROOT.'/setup/force_admin.php');
require_once (DOCUMENT_ROOT.'/lib/adminactions.php');
require_once (DOCUMENT_ROOT.'/lib/keyless_lib.php');
require_once (DOCUMENT_ROOT.'/template/form.php');
require_once (DOCUMENT_ROOT.'/template/roomlist.php');
require_once (DOCUMENT_ROOT.'/lib/nonce.php');
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
	$set_body->push(new paragraph('Edit user information',array('class' => 'importantmessage')));
	if(is_array($_POST) && array_key_exists('action',$_POST))
	{
		switch($_POST['action'])
		{
			case 'create_user':
			if(!$DB->sql(
						'INSERT INTO accounts '.
						'(`id_user`)'.
						'VALUES (?)',
						array('s',$id_selected)
					))
			{
				$set_body->push(new paragraph('User already exists',array('class' => 'errormessage')));
			} 
			break;
			case 'search':
			if(array_key_exists('Search_For',$_POST))
			{
				$results = 
				array('id_user','email','first_name','last_name','account_expires',
					'id_perm','id_room','room_title','can_read','can_upload','can_remove','room_expires','hardware_key','password_last_changed');
				//if(strstr($_POST['Search_For'],'@') !== false)
				{
					$searchstring = '%'.$_POST['Search_For'].'%';
					$DB->sql(
						'SELECT '.
						'accounts.id_user,accounts.email,accounts.first_name,'.
						'accounts.last_name,accounts.expires,'.
						'room_permissions.room_permissions_id,room_permissions.id_room,rooms.room_title,'.
						'room_permissions.can_read,room_permissions.can_upload,room_permissions.can_remove,'.
						'room_permissions.expires,hardware.id_hardware,'.
						'pw.time_stamp AS password_last_changed '.
						'FROM accounts '.
						'LEFT JOIN room_permissions ON accounts.id_user = room_permissions.id_user '.
						'LEFT JOIN rooms ON rooms.id_room = room_permissions.id_room '.
						'LEFT JOIN hardware ON accounts.id_user = hardware.id_user '.
						'LEFT JOIN (SELECT MAX(time_stamp) AS time_stamp, id_user FROM passwords GROUP BY id_user) AS pw ON accounts.id_user = pw.id_user '.
						'WHERE accounts.email LIKE ? OR accounts.id_user LIKE ? OR accounts.first_name LIKE ? OR accounts.last_name LIKE ?',
						array('ssss',$searchstring,$searchstring,$searchstring,$searchstring),
						$results
						);
				} 
				$duplicate = array();

				foreach($results AS $res)
				{
					$duplicate[$res['id_user']] = 1;
				}
				if(count($duplicate) === 1 )
				{
					$id_selected = $results[0]['id_user'];
					$user_info = array_intersect_key($results[0], array_flip(array('email','first_name','last_name','account_expires','password_last_changed')));
					$permissions = $results;
					
				} else {
					$search_results = $results;
				}

			}
			break;
			case 'update_user':
			if(!is_null($id_selected) &&
				array_key_exists('email',$_POST) && !is_null($_POST['email']) && strstr($_POST['email'],'@') !== false &&
				array_key_exists('first_name',$_POST) && !is_null($_POST['first_name']) && strlen($_POST['first_name']) > 0 &&
				array_key_exists('last_name',$_POST) && !is_null($_POST['last_name']) && strlen($_POST['last_name']) > 0 &&
				array_key_exists('account_expires',$_POST) && !is_null($_POST['account_expires']) && strlen($_POST['account_expires']) > 0
				)
			{
				$r = $DB->sql(
					'UPDATE accounts SET '.
					'email=?,first_name=?,last_name=?,expires=? '.
					'WHERE id_user=?',
					array('sssss',$_POST['email'],$_POST['first_name'],$_POST['last_name'],$_POST['account_expires'],$id_selected)
				);
				if(!$r)
				{
					$set_body->push(new paragraph('No user data changed',array('class' => 'message')));
				}
			} else {
				$set_body->push(new paragraph('Invalid input',array('class' => 'message')));
			}
			break;
			case 'Delete':
			$r = $DB->sql(
					'DELETE FROM room_permissions '.
					'WHERE room_permissions_id=?',
					array('i',$_POST['id_perm'])
				);
				if(!$r)
				{
					$set_body->push(new paragraph('Room Permissions not changed',array('class' => 'message')));
				} else {
					$set_body->push(new paragraph('Room Access deleted for room \''.$_POST['room_title'].'\'.',array('class' => 'message')));
				}
			break;
			case 'Update':
			if(!is_null($_POST['id_perm']))
			{
				$can_read = 0;
				$can_upload = 0;
				$can_remove = 0;
				if(array_key_exists('Read',$_POST))
				{
					$can_read = 1;
				}
				if(array_key_exists('Upload',$_POST))
				{
					$can_upload = 1;
				}
				if(array_key_exists('Delete',$_POST))
				{
					$can_remove = 1;
				}
				
				$r = $DB->sql(
					'UPDATE room_permissions SET '.
					'room_permissions.expires=?,can_read=?,can_upload=?,can_remove=? '.
					'WHERE room_permissions_id=?',
					array('siiii',$_POST['Expiration'],$can_read,$can_upload,$can_remove ,$_POST['id_perm'])
				);
				if(!$r)
				{
					$set_body->push(new paragraph('Room Permissions not changed',array('class' => 'message')));
				} else {
					$set_body->push(new paragraph('Room Access changed for room \''.$_POST['room_title'].'\'.',array('class' => 'message')));
				}
			} else {
				$set_body->push(new paragraph('Invalid input',array('class' => 'message')));
			}
			break;
			case 'add_room':
			if(array_key_exists('room_selected',$_POST) && !is_null($_POST['room_selected']))
			{
				$can_read = 0;
				$can_upload = 0;
				$can_remove = 0;
				if(array_key_exists('Read',$_POST))
				{
					$can_read = 1;
				}
				if(array_key_exists('Upload',$_POST))
				{
					$can_upload = 1;
				}
				if(array_key_exists('Delete',$_POST))
				{
					$can_remove = 1;
				}
				
				$r = $DB->sql(
					'INSERT INTO room_permissions '.
					'(id_room,id_user,expires,can_read,can_upload,can_remove)'.
					'VALUES (?,?,?,?,?,?)'
					,
					array('issiii',$_POST['room_selected'],$id_selected,$_POST['Expiration'],$can_read,$can_upload,$can_remove)
				);
				if(!$r)
				{
					$set_body->push(new paragraph('Room Permissions not changed',array('class' => 'message')));
				} else {
					$set_body->push(new paragraph('Room Access added.',array('class' => 'message')));
				}
			} else {
				$set_body->push(new paragraph('Invalid input',array('class' => 'message')));
			}
			break;
			case 'reset_hardware':
			if(!is_null($id_selected))
			{
				if($DB->sql(
				'DELETE FROM hardware WHERE id_user=?',
				array('s',$id_selected)
						))
					{
						$set_body->push(new paragraph('Hardware Key reset',array('class' => 'message')));
					} else {
						$set_body->push(new paragraph('Error resetting key (No existing key or bad ID?)',array('class' => 'message')));
					}
			}
			break;
			case 'set_key_destroyed':
				if (!is_null($id_selected)
					&& array_key_exists('destroyed',$_POST)
					&& !is_null($_POST['destroyed']))
				{
					if($DB->sql(
<<<SQL
INSERT INTO key_destroy_log
	(destroyed, id_user)
VALUES
	(?, ?)
ON DUPLICATE KEY UPDATE
	destroyed = ?,
	id_user = ?
SQL
					, array(
						'isis',
						 $_POST['destroyed'],
						 $id_selected,
						 $_POST['destroyed'],
						 $id_selected)))
					{
						$set_body->push(new paragraph('Log Key as '.($_POST['destroyed'] ? 'D' : 'Und').'estroyed succeeded',array('class' => 'message')));
					} else {
						$set_body->push(new paragraph('Log Key as Destroyed failed (No existing key or bad ID?)',array('class' => 'message')));
					}
				}
			break;
			case 'enable_key_undestroy':
				if (!is_null($id_selected))
				{
					$results = array('id');
					if (!$DB->sql(
<<<SQL
SELECT id
FROM key_destroy_log
WHERE id_user = ?
SQL
					, array('s',$id_selected)
					, $results))
					{
						break;
					}
					try
					{
						$expires = new DateTime();
						$expires->setTimestamp(time() + (60 * 60)); // 1h
						$nlib = new noncelib(new db_nonce_cache($DB, $id_selected));
						$anonce = $nlib->create(32, $expires, 'set_key_undestroyed', $results[0]['id']);
						$set_body->push(new paragraph('Enable Key Undestroy succeeded', array('class' => 'message')));
						$set_body->push(new paragraph('ID: '.$results[0]['id'], array('class' => 'message')));
						$set_body->push(new paragraph('Nonce: '.base64_encode($anonce->payload()), array('class' => 'message')));
					}
					catch (RuntimeException $e)
					{
						$set_body->push(new paragraph('Enable Key Undestroy failed (No existing key or bad ID?)',array('class' => 'message')));
					}
				}
			break;
			case 'release_license':
				if (!is_null($id_selected))
				{
					if (false === $DB->sql(
<<<SQL
DELETE FROM paired_licenses
WHERE id_room_permission
IN
(
	SELECT room_permissions_id
	FROM room_permissions
	WHERE id_user = ?
)
SQL
					, array('s',$id_selected)))
					{
						$set_body->push(new paragraph('Release license failed',array('class' => 'message')));
						break;
					}
					$set_body->push(new paragraph('Release license succeeded, or already released', array('class' => 'message')));
				}
			break;
			case 'query_acquired_licenses':
				if (!is_null($id_selected))
				{
					$results = 
						array(
							'computer_name',
							'room_title',
							'expires',
							'time_stamp'
							);
					if (false === $DB->sql(
<<<SQL
SELECT
	hardware_2021.computer_name,
	rooms.room_title,
	paired_licenses.expires,
	paired_licenses.time_stamp
FROM paired_licenses 
LEFT JOIN room_permissions ON paired_licenses.id_room_permission = room_permissions.room_permissions_id 
LEFT JOIN rooms ON room_permissions.id_room = rooms.id_room
LEFT JOIN hardware_2021 ON paired_licenses.id_hardware = hardware_2021.id
WHERE hardware_2021.id_user = ?
AND paired_licenses.expires > NOW()
SQL
						, array('s',$id_selected)
						, $results))
					{
						$set_body->push(new paragraph('No licenses acquired.',array('class' => 'message')));
						break;
					}
					//$set_body->push(new paragraph('Acquired licenses:', array('class' => 'message')));
					$output = '<table border=\"1\"><tr><th>Computer Name</th><th>Room Title</th><th>Expires</th><th>Acquired</th><th>Duration</th></tr>';
					foreach($results as $item)
					{
						$duration = date_diff(date_create($item['expires']), date_create($item['time_stamp']));
						$output = $output.'<tr><td>'.$item['computer_name'].'</td><td>'.$item['room_title'].'</td><td>'.$item['expires'].'</td><td>'.$item['time_stamp'].'</td><td>'.$duration->format('%a').' days</td></tr>';
					}
					$output = $output.'</table>';
					$set_body->push(new paragraph('Acquired licenses: ', array('class' => 'message')));
					$set_body->push(new paragraph($output, array('class' => 'message')));
				}
			break;
			default:
		}

	} 
	if(!is_null($id_selected) && is_null($permissions))
	{
		$results = 
			array('id_user','email','first_name','last_name','account_expires',
				'id_perm','id_room','room_title','can_read','can_upload','can_remove','room_expires','hardware_key','password_last_changed');
			//if(strstr($_POST['Search_For'],'@') !== false)
			{
				$DB->sql(
					'SELECT '.
					'accounts.id_user,accounts.email,accounts.first_name,'.
					'accounts.last_name,accounts.expires,'.
					'room_permissions.room_permissions_id,room_permissions.id_room,rooms.room_title,'.
					'room_permissions.can_read,room_permissions.can_upload,room_permissions.can_remove,'.
					'room_permissions.expires,hardware.id_hardware,'.
					'pw.time_stamp AS password_last_changed '.
					'FROM accounts '.
					'LEFT JOIN room_permissions ON accounts.id_user = room_permissions.id_user '.
					'LEFT JOIN rooms ON rooms.id_room = room_permissions.id_room '.
					'LEFT JOIN hardware ON accounts.id_user = hardware.id_user '.
					'LEFT JOIN (SELECT MAX(time_stamp) AS time_stamp, id_user FROM passwords GROUP BY id_user) AS pw ON accounts.id_user = pw.id_user '.
					'WHERE accounts.id_user=?',
					array('s',$id_selected),
					$results
					);
				$user_info = array_intersect_key($results[0], array_flip(array('email','first_name','last_name','account_expires','password_last_changed')));
				$permissions = $results;

			} 
			$_POST['Search_For'] = $id_selected;
	}

	$form = new form(NULL,array('method' => 'POST'));
	$params = array('class' => 'edit_user');
	if(is_array($_POST) && array_key_exists('Search_For',$_POST))
	{
		$params['value'] = $_POST['Search_For'];
	}
	$form->push(new field('Search For',$params));
	$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'action','value'=>'search')));
	$form->push(new submit('Search'));
	$set_body->push($form);
	if(is_null($id_selected))
	{
		if(is_null($search_results))
		{
			$set_body->push(new paragraph("Or create a new user id:",array('class'=>'message')));
			$form = new form(NULL,array('method' => 'POST'));
			$form->push(new field('id_selected',$params));
			$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'action','value'=>'create_user')));
			$form->push(new submit('Add'));
			$set_body->push($form);
		} else {
			$duplicate = array();

			foreach($search_results AS $res)
			{
				if(!array_key_exists($res['id_user'],$duplicate))
				{
					$form = new form(NULL,array('method' => 'POST','id'=>'select_'.$res['id_user']));
					$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'id_selected','value'=>$res['id_user'])));
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
		$set_body->push(new paragraph('User Info:',array('class'=> 'message')));
		
		$roomdata = array('id_room','room_title');
		$DB->sql(
						'SELECT '.
						'rooms.id_room,rooms.room_title '.
						'FROM rooms ',
						array(''),
						$roomdata
						);
		$roomlist = array();
		foreach($roomdata AS $dat)
		{
			$roomlist[$dat['id_room']] = $dat['room_title'];
		}
		$form = new form(NULL,array('method' => 'POST'));		
		$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'action','value'=>'update_user')));
		$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'id_selected','value'=>$id_selected)));
		foreach($user_info AS $fieldname => $value)
		{
			$fmt = array('class' => 'edit_user','arrange'=> 'vertical','value' => $value);
			if ($fieldname == "password_last_changed")
				$fmt['readonly'] = 'true';
			$form->push(new field($fieldname, $fmt));
		}
		$form->push(new submit('Update User Info',array('class' => 'edit_user')));
		$set_body->push($form);
		$set_body->push(new paragraph('Room Access:',array('class'=> 'message')));
		foreach($permissions AS $perm)
		{
			$row = new row(NULL,array("class" => "permissions_row"));
			$form = new form(NULL,array('method' => 'POST','id'=>'select_'.$id_selected));
			$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'id_selected','value'=>$id_selected)));
			$form->push(new input(NULL,array('type' => 'hidden','name'=> 'id_perm','value' => $perm['id_perm'])));
			$form->push(new input(NULL,array('type' => 'hidden','name'=> 'room_title','value' => $perm['room_title'])));	
			$o = new optionmenu($perm['id_room'],$roomlist,array('class' => 'room_select','name'=> 'room_selected','readonly' => 'true'));
			$form->push($o);
			$form->push(new field( 'Expiration',array('class' => 'room_expiration','value' => $perm['room_expires'])));
			$atr = array('class'=> 'room_expiration','type' => 'checkbox');
			if($perm['can_read'])
			{
				$atr['checked'] = "checked";
			} else {
				unset($atr['checked']);
			}
			$form->push(new field('Read',$atr));
			if($perm['can_upload'])
			{
				$atr['checked'] = "checked";
			} else {
				unset($atr['checked']);
			}
			$form->push(new field('Upload',$atr));
			if($perm['can_remove'])
			{
				$atr['checked'] = "checked";
			} else {
				unset($atr['checked']);
			}
			$form->push(new field('Remove',$atr));
			$form->push(new submit('Update',array('class' => 'room_expiration','name'=> 'action')));
			$form->push(new submit('Delete',array('class' => 'room_expiration','name'=> 'action')));
			
			$row->push($form);
			$set_body->push($row);
		}
		$row = new row(NULL,array('style' => 'padding-top:2px'));
		$form = new form(NULL,array('method' => 'POST','id'=>'select_'.$id_selected));
		$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'action','value'=>'add_room')));
		$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'id_selected','value'=>$id_selected)));
		$o = new optionmenu(NULL,$roomlist,array('class' => 'room_select','name'=> 'room_selected'));
		$form->push($o);
		$form->push(new field( 'Expiration',array('class' => 'room_expiration','value' => "")));
		$form->push(new submit('Add New',array('class' => 'room_expiration')));
		$atr = array('class'=> 'room_expiration','type' => 'checkbox');
		$form->push(new field('Read',$atr));
		$form->push(new field('Upload',$atr));
		$form->push(new field('Remove',$atr));
		$row->push($form);
		$set_body->push($row);

		$set_body->push(new paragraph('Reset Password:',array('class'=> 'message')));
		$form = new form(NULL,array('method' => 'POST','action' => '/admin/reset_password.php'));
		$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'Set_Password_For','value'=>$id_selected)));
		$form->push(new field('Temporary Password',array('type'=> 'password', 'class' => 'resetpassword')));
		$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'action','value'=>'reset_password')));
		$form->push(new submit('Submit'));
		$set_body->push($form);

		// Winner ..2020
		$form = new form(NULL,array('method' => 'POST','id'=>'select_'.$id_selected));
		$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'action','value'=>'reset_hardware')));
		$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'id_selected','value'=>$id_selected)));
		$set_body->push(new paragraph('Winner 2020 and prior:',array('class'=> 'message')));
		$form->push(new submit('Reset Hardware Key',array('class' => 'edit_user',)));
		$set_body->push($form);

		// Winner 2021..
		$form = new form(NULL, array('method' => 'POST', 'id' => 'select_'.$id_selected));
		$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'action','value'=>'set_key_destroyed')));
		$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'id_selected','value'=>$id_selected)));
		$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'destroyed','value'=>1)));
		$set_body->push(new paragraph('Winner 2021 and later:',array('class'=> 'message')));
		$form->push(new submit('Log Key as Destroyed',array('class' => 'edit_user',)));
		$set_body->push($form);

		$form = new form(NULL, array('method' => 'POST', 'id' => 'select_'.$id_selected));
		$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'action','value'=>'set_key_destroyed')));
		$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'id_selected','value'=>$id_selected)));
		$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'destroyed','value'=>0)));
		$form->push(new submit('Log Key as Undestroyed',array('class' => 'edit_user',)));
		$set_body->push($form);

		$form = new form(NULL, array('method' => 'POST', 'id' => 'select_'.$id_selected));
		$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'action','value'=>'enable_key_undestroy')));
		$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'id_selected','value'=>$id_selected)));
		$form->push(new submit('Enable Key Undestroy',array('class' => 'edit_user',)));
		$set_body->push($form);

		$form = new form(NULL, array('method' => 'POST', 'id' => 'select_'.$id_selected));
		$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'action','value'=>'release_license')));
		$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'id_selected','value'=>$id_selected)));
		$form->push(new submit('Release License',array('class' => 'edit_user',)));
		$set_body->push($form);

		$form = new form(NULL, array('method' => 'POST', 'id' => 'select_'.$id_selected));
		$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'action','value'=>'query_acquired_licenses')));
		$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'id_selected','value'=>$id_selected)));
		$form->push(new submit('Show Acquired Licenses',array('class' => 'edit_user',)));
		$set_body->push($form);
	}
}
require_once(DOCUMENT_ROOT.'/template/mainframe.php');

?>