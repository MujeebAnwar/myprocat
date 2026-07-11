<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/login.php');
require_once (DOCUMENT_ROOT.'/setup/force_authorized.php');
require_once (DOCUMENT_ROOT.'/setup/force_admin.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once (DOCUMENT_ROOT.'/template/form.php');
function fieldsplit($line,$fieldnames)
{
	$splitdata = explode("+",$line);
	$ret = array();
	foreach($fieldnames AS $index => $key)
	{
		if(count($splitdata) > $index)
		{
			$ret[$key] = trim($splitdata[$index]);
		}
	}
	return $ret;

}
function insertPhoneRecord($id_user,$phonenumber,$phoneType)
{
	global $DB;
	$pNumber = preg_replace("/[^0-9]/","",$phonenumber);
	if(strlen($pNumber) > 0)
	{
		if(false === $DB->sql(
			'INSERT IGNORE INTO tmp_phone_records '.
			'(id_user,phone_number,phone_type) '.
			'VALUES (?,?,?)',
				array('sss',
				$id_user,
				$pNumber,
				$phoneType)
				)
			)
		{
			$set_body->push(new paragraph("insertPhoneRecord: ".$id_user." ".$phonenumber." ".$phoneType." DB error: ".$DB->error,array("class" => "filelink_filename")));
		}
	}
}
function permsplit($data)
{
	$splitdata = explode(",",$data);
	$p = array();
	for($i=0; $i<count($splitdata) ; $i=$i+2)
	{
		$room = $splitdata[$i];
		$perm = $splitdata[$i+1];
		$p[$room] = array();
		list($p[$room]['can_read'],$p[$room]['can_upload'],$p[$room]['can_remove'],$p[$room]['expires']) =
			explode(':',$perm);
	}
	return $p;

}
if(is_array($_POST) && is_array($_FILES)
	&& array_key_exists('UploadFile',$_FILES)
	)
{
	$set_body = new row(NULL,array('style' => 'border:1px solid black;max-height:500;overflow:auto;margin:10px')); 
	$set_body->push(new paragraph(gmdate(DATE_ISO8601)." file upload complete",array("class" => "filelink_filename")));

	$accountsUpdated = 0;
	$DB->sql("DROP TABLE IF EXISTS tmp_accounts");
	$DB->sql("DROP TABLE IF EXISTS tmp_room_permissions");
	$DB->sql("DROP TABLE IF EXISTS tmp_phone_records");
	$DB->sql("CREATE TABLE tmp_accounts LIKE accounts");
	$DB->sql("CREATE TABLE tmp_room_permissions LIKE room_permissions");
	$DB->sql("CREATE TABLE tmp_phone_records LIKE phone_records");

	try
	{
		$DB->sql("CREATE UNIQUE INDEX unique_user_room ON room_permissions (id_user, id_room)");
	}
	catch (PDOException $ex)
	{
		if ($ex->errorInfo[2] != 1061) // 1061: duplicate key name
		{
			throw $ex;
		}
	}
	try
	{
		$DB->sql("CREATE UNIQUE INDEX unique_user_phonetype ON phone_records (id_user, phone_type)");
	}
	catch (PDOException $ex)
	{	
		if ($ex->errorInfo[2] != 1061) // 1061: duplicate key name
		{
			throw $ex;
		}
	}
	try
	{
		$DB->sql("CREATE INDEX unique_expires ON tmp_room_permissions (expires)");
	}
	catch (PDOException $ex)
	{	
		if ($ex->errorInfo[2] != 1061) // 1061: duplicate key name
		{
			throw $ex;
		}
	}

	// during my testing on dev, locking the temp tables makes no noticeable performance improvement
	//$DB->sql("LOCK TABLES tmp_accounts WRITE, tmp_room_permissions WRITE, tmp_phone_records WRITE");

	$tracking = array();
	$set_body->push(new paragraph(gmdate(DATE_ISO8601)." temporary tables created",array("class" => "filelink_filename")));
	foreach (array(DOCUMENT_ROOT.'/Documentation/fixedadminaccounts.txt',$_FILES["UploadFile"]["tmp_name"]) AS $filename)
	{
		$fh = fopen($filename, "r") or die("Unable to open file!");
		$roomlist = array('id_room','room_name');
		$room_lookup = array();
		if (false === $DB->sql(
			'SELECT id_room,room_title FROM rooms',
			array(),
			$roomlist
		))
		{
			$set_body->push(new paragraph("DB error: ".$DB->error,array("class" => "filelink_filename")));
		}
		foreach($roomlist AS $roominfo)
		{
			$room_lookup[$roominfo['room_name']] = $roominfo['id_room'];
		}
		$line = fgets($fh);
		if(strstr($line,"+") === false)
		{
			$headers = preg_split("/\s+/",trim($line));
			$line = fgets($fh);
		} else {
			$headers = array('Email','Status','ID','First','Middle','Last','Expiration','businessphone','homephone','cellphone','Software');
		}
		while(strlen($line))
		{
			$newrecord = fieldsplit($line,$headers);
			if(isset($tracking))
			{
				if(array_key_exists($newrecord['ID'],$tracking))
				{
					$set_body->push(new paragraph("Duplicate record found for user: ".$newrecord['ID'],array("class" => "filelink_filename")));
				}
				$tracking[$newrecord['ID']] = $newrecord;
			}
			$result = $DB->sql(
				'INSERT IGNORE INTO tmp_accounts '.
				'(email,id_user,first_name,mid_name,last_name,expires) '.
				'VALUES (?,?,?,?,?,STR_TO_DATE(?,\'%m/%d/%Y\') + INTERVAL 86399 SECOND)',
				array('ssssss',
					$newrecord['Email'],
					$newrecord['ID'],
					$newrecord['First'],
					$newrecord['Middle'],
					$newrecord['Last'],
					$newrecord['Expiration']
				)
			);
			if ($result === false) {
				$set_body->push(new paragraph("DB error: ".$DB->error,array("class" => "filelink_filename")));
			} else if ($result) {
				$accountsUpdated++;
			}
			if(array_key_exists('businessphone',$newrecord) && $newrecord['businessphone'])
			{
				insertPhoneRecord($newrecord['ID'],$newrecord['businessphone'],'business');
			}
			if(array_key_exists('homephone',$newrecord) &&$newrecord['homephone'])
			{
				insertPhoneRecord($newrecord['ID'],$newrecord['homephone'],'home');
			}
			if(array_key_exists('cellphone',$newrecord) &&$newrecord['cellphone'])
			{
				insertPhoneRecord($newrecord['ID'],$newrecord['cellphone'],'cell');
			}
			if(array_key_exists('Software',$newrecord))
			{
				$roomrecord = permsplit($newrecord['Software']);
				$trackInsertedRoom = array(); // for this user only
				foreach($roomrecord AS $roomname => $roomPerm)
				{
					$id_room = NULL;
					if(array_key_exists($roomname,$room_lookup))
					{
						$id_room = $room_lookup[$roomname];
					}
					if(is_null($id_room))
					{
						if(!array_key_exists('Invalid_room:'.$roomname,$tracking))
						{
							$set_body->push(new paragraph("Invalid room: ".$roomname,array("class" => "filelink_filename")));
							$tracking['Invalid_room:'.$roomname] = "sent";
						}
					//} else {
					// during my testing on dev, preventing attempts to insert dup room perms improves performance by 100%
					} else if (!array_key_exists($id_room,$trackInsertedRoom)) {
						$params = array('siiiis',$newrecord['ID'],$id_room,$roomPerm['can_read'],$roomPerm['can_upload'],$roomPerm['can_remove'],$roomPerm['expires']);
						if (false === $DB->sql(
							'INSERT IGNORE INTO tmp_room_permissions '.
							'(id_user,id_room,can_read,can_upload,can_remove,expires) '.
							'VALUES (?,?,?,?,?,STR_TO_DATE(?,\'%m/%d/%Y\') + INTERVAL 86399 SECOND)',
							$params
						))
						{
							$set_body->push(new paragraph("DB error: ".$DB->error,array("class" => "filelink_filename")));
						}
						else
						{
							$trackInsertedRoom[$id_room] = "1";
						}
						
						$params = array('ss',$roomPerm['expires'],$newrecord['ID']);
						if (false === $DB->sql(
							'UPDATE tmp_accounts '.
							'SET expires=GREATEST(expires,STR_TO_DATE(?,\'%m/%d/%Y\') + INTERVAL 86399 SECOND) '.
							'WHERE id_user=?',
							$params
						))
						{
							$set_body->push(new paragraph("DB error: ".$DB->error,array("class" => "filelink_filename")));
						}
					}
				}
			}
			$line = fgets($fh);
		}
		fclose($fh);
		$set_body->push(new paragraph(gmdate(DATE_ISO8601)." finished loading ".$filename,array("class" => "filelink_filename")));
	}

	//$DB->sql("UNLOCK TABLES");

	$set_body->push(new paragraph(gmdate(DATE_ISO8601)." temporary tables populated",array("class" => "filelink_filename")));

	if (false === $DB->sql(<<<SQL
INSERT INTO accounts (id_team,id_user,email,legacyHashword,title,first_name,mid_name,last_name,address,city,state,zip,country,is_active,owns_room,is_manager,is_admin,expires)
SELECT
	tmp_accounts.id_team, 
	tmp_accounts.id_user, 
	tmp_accounts.email, 
	tmp_accounts.legacyHashword, 
	tmp_accounts.title, 
	tmp_accounts.first_name, 
	tmp_accounts.mid_name, 
	tmp_accounts.last_name, 
	tmp_accounts.address, 
	tmp_accounts.city, 
	tmp_accounts.state, 
	tmp_accounts.zip, 
	tmp_accounts.country, 
	tmp_accounts.is_active, 
	tmp_accounts.owns_room, 
	tmp_accounts.is_manager, 
	tmp_accounts.is_admin, 
	tmp_accounts.expires 
FROM tmp_accounts 
-- some records from nightly domino export are incomplete
-- exclude records without an expiration date
WHERE tmp_accounts.expires != 0
ON DUPLICATE KEY UPDATE 
	accounts.id_team = tmp_accounts.id_team, 
	accounts.id_user = tmp_accounts.id_user, 
	accounts.email = tmp_accounts.email, 
	accounts.legacyHashword = tmp_accounts.legacyHashword, 
	accounts.title = tmp_accounts.title, 
	accounts.first_name = tmp_accounts.first_name, 
	accounts.mid_name = tmp_accounts.mid_name, 
	accounts.last_name = tmp_accounts.last_name, 
	accounts.address = tmp_accounts.address, 
	accounts.city = tmp_accounts.city, 
	accounts.state = tmp_accounts.state, 
	accounts.zip = tmp_accounts.zip, 
	accounts.country = tmp_accounts.country, 
	accounts.is_active = tmp_accounts.is_active, 
	accounts.owns_room = tmp_accounts.owns_room, 
	accounts.is_manager = tmp_accounts.is_manager, 
	accounts.is_admin = tmp_accounts.is_admin, 
	accounts.expires = tmp_accounts.expires
SQL
	))
	{
		$set_body->push(new paragraph("DB error: ".$DB->error,array("class" => "filelink_filename")));
	}

	$set_body->push(new paragraph(gmdate(DATE_ISO8601)." account table updated",array("class" => "filelink_filename")));
	
	// requires unique index (id_user + id_room)
	if (false === $DB->sql(<<<SQL
INSERT INTO room_permissions (id_user,id_room,can_read,can_upload,can_remove,expires)
SELECT 
	tmp_room_permissions.id_user, 
	tmp_room_permissions.id_room, 
	tmp_room_permissions.can_read, 
	tmp_room_permissions.can_upload, 
	tmp_room_permissions.can_remove, 
	tmp_room_permissions.expires 
FROM tmp_room_permissions 
-- some records from nightly domino export are incomplete
-- exclude records without an expiration date
WHERE tmp_room_permissions.expires != 0
ON DUPLICATE KEY UPDATE
	room_permissions.id_user = tmp_room_permissions.id_user, 
	room_permissions.id_room = tmp_room_permissions.id_room, 
	room_permissions.can_read = tmp_room_permissions.can_read, 
	room_permissions.can_upload = tmp_room_permissions.can_upload, 
	room_permissions.can_remove = tmp_room_permissions.can_remove, 
	room_permissions.expires = tmp_room_permissions.expires 
SQL
	))
	{
		$set_body->push(new paragraph("DB error: ".$DB->error,array("class" => "filelink_filename")));
	}

	$set_body->push(new paragraph(gmdate(DATE_ISO8601)." room_permissions table updated",array("class" => "filelink_filename")));

	// requires unique index (id_user + phone_type)
	if (false === $DB->sql(<<<SQL
INSERT INTO phone_records (id_user,phone_number,phone_type)
SELECT
	tmp_phone_records.id_user, 
	tmp_phone_records.phone_number, 
	tmp_phone_records.phone_type 
FROM tmp_phone_records 
ON DUPLICATE KEY UPDATE
	phone_records.id_user = tmp_phone_records.id_user, 
	phone_records.phone_number = tmp_phone_records.phone_number, 
	phone_records.phone_type = tmp_phone_records.phone_type 
SQL
	))
	{
		$set_body->push(new paragraph("DB error: ".$DB->error,array("class" => "filelink_filename")));
	}

	$set_body->push(new paragraph(gmdate(DATE_ISO8601)." phone_records table updated",array("class" => "filelink_filename")));
	$set_body->push(new paragraph(gmdate(DATE_ISO8601)." ".$accountsUpdated." records updated",array("class" => "filelink_filename")));
	
} else {
	$form = new form(NULL,array('method' => 'POST','enctype'=>"multipart/form-data"));
	$content = new row(NULL,array("class" => "filelink_container", 'style' => 'margin:10px'));
	$uploadbutton = new section(
		new anchor(
			new image_submitbutton("/img/upload.png",100,100),array("href" => 'file_upload.php')
			),array('style' => 'margin-top:10px')
		);
	$fileinfo = new section(NULL,array("class" => "filelink_description"));
	$fileinfo->push(
		new paragraph(
			new input(NULL,array('name' => 'UploadFile', "class" => "fileupload_filename","value" => "Select the lotus export file","type" => "file"))
			,array("class" => "fileupload_filename")
			)
		);
	$fileinfo->push(
		new paragraph("Select the Lotus notes user file above, then click the upload button to the right. (v2021)"
			,array("class" => "filelink_date")
			)
		);
	$content->push($fileinfo);
	$content->push($uploadbutton);
	$form->push($content);
	$set_body = $form;
}
require_once(DOCUMENT_ROOT.'/template/mainframe.php');
?>