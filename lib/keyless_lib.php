<?php
require_once 'config.php';
require_once DOCUMENT_ROOT.'/lib/account.php';
define('cmdGET',         "get");
define('cmdSET',         "set");

define('errEMPTY_FLD',   100);
define('errBAD_EMAIL',   101);
define('errBAD_PASS',    102);
define('errPASSWORD_DB', 103);
define('errEXPIRES_DB',  104);
define('errNOTACTIVE',   105);
class keyless_session
{
	public $user_object = NULL;
	public $roomname = 'Winner%';
	public $permissions = NULL;
	public $errCode = NULL;
	public $DB = NULL;
	public function __construct($username, $password, $roomaccess = NULL)
	{
		if(!is_null($roomaccess))
		{
			$this->roomname = $roomaccess.'%';
		}
		$this->DB = new databaseI();
		$this->user_object = new useraccount($this->DB);
		if(!(is_null($username) || is_null($password) || $username === "" || $password === ""))
		{
			if($this->user_object->authenticate($username,$password))
			{
				$this->load_permissions();
			} else {
				$this->errCode = errBAD_PASS;
			}
		}

	}
	public function load_permissions()
	{
		if($this->user_object->logged_in)
		{
			$permission_list = 
			array(
				'id_user',
				'expires',
				'expires_day',
				'can_read',
				'can_upload',
				'can_remove',
				'id_hardware',
				'id_hardware_record',
				'room_title'
				);
			$this->DB->sql(
				'SELECT '.
					'room_permissions.id_user,'.
					'DATE_FORMAT(room_permissions.expires + INTERVAL 3 DAY,\'%e %M %Y\'), '.
					'DATE_FORMAT(room_permissions.expires + INTERVAL 3 DAY,\'%e\'),'.
					'room_permissions.can_read,'.
					'room_permissions.can_upload,'.
					'room_permissions.can_remove,'.
					'hardware.id_hardware,'.
					'hardware.id_hardware_record,'.
					'rooms.room_title '.
				'FROM accounts '.
					'LEFT JOIN room_permissions ON accounts.id_user = room_permissions.id_user '.
					'LEFT JOIN rooms ON rooms.id_room = room_permissions.id_room '.
					'LEFT JOIN hardware ON accounts.id_user = hardware.id_user '.
				'WHERE room_permissions.id_user=? '.
					'AND (room_permissions.expires + INTERVAL 3 DAY)>NOW() '.
					'AND rooms.room_title LIKE ? AND rooms.keyless_access = 1',
				array('ss',$this->user_object->user_details['id_user'],$this->roomname),
				$permission_list);
			$this->permissions = $permission_list[0];
			$this->errCode = errNOTACTIVE;
		} else {
			$this->permissions = NULL;
			$this->errCode = errBAD_PASS;
		}
	}
	public function Set_Hardware_id($hardwareID)
	{
		$rows_effected = 0;
		if(is_null($hardwareID) || $hardwareID == 0)
		{
			$this->errCode = errEMPTY_FLD;
			return 0;
		}
		if(!is_null($this->permissions) && !is_null($hardwareID))
		{
			if($this->permissions['id_hardware_record'] > 0)
			{
				if($this->permissions['id_hardware'] == $hardwareID)
				{
					$this->errCode = NULL;
					return 1;
				} else {
				$rows_effected = $this->DB->sql(
					'UPDATE hardware SET id_hardware = ? WHERE id_hardware_record = ?',
					array('ii',$hardwareID,$this->permissions['id_hardware_record'])
					);
				}
			} else {
				$rows_effected = $this->DB->sql(
					'INSERT INTO hardware (id_user ,id_hardware)'.
					'VALUES (?,?)',
					array('si',$this->permissions['id_user'],$hardwareID)
					);
			}
		}
		return ($rows_effected)?1:0;

	}
	public function Get_Hardware_id()
	{
		if(!is_null($this->permissions))
		{
			if(is_null($this->permissions['id_hardware']))
			{
				return 0;
			}
			return $this->permissions['id_hardware'];
		}
		$this->errCode = errEXPIRES_DB;
		return 0;
	}
	public function generate_response($action,$hardwareID)
	{
		if(is_null($this->permissions)) 
		{
			if(!is_null($this->errCode))
			{
				return $this->errCode; 
			} else {
				return errPASSWORD_DB;
			}
		}
		if(is_null($action)){return errEMPTY_FLD; }
		if($action == cmdSET && is_null($hardwareID)){return errEMPTY_FLD; }
		$response = date( "j F Y" )."|";
		$response .= $this->permissions['expires'].'|';
		$serverID = 200 - date("d") * 3 + $this->permissions['expires_day'] * 3;
		$response .= md5( $serverID ).'|';
		switch($action)
		{
			case cmdGET:
			$response .= $this->Get_Hardware_id();
			break;
			case cmdSET:
			$response .= $this->Set_Hardware_id($hardwareID);
			break;
			default:
			$response .= '0|'; // No action or invalid action results in error code
		}
		return $response;
	}
}
?>