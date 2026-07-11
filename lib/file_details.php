<?php

class file_details
{
	protected $DB;
	protected $useraccount;
	protected $results = NULL;
	public $id_file = NULL;
	public $id_room = NULL;
	public function fetch_results()
	{
		if(is_null($this->results) && !is_null($this->id_file))
		{
			$results = array('id_file','filename','description','time_stamp','can_read','can_upload','can_remove','is_public','id_room','expires');
			
			$this->DB->sql(
			'SELECT filelist.id_file,filelist.filename,filelist.description,DATE_FORMAT(filelist.time_stamp,"%M, %e %Y, %h:%i %p"),'.
			'room_permissions.can_read,room_permissions.can_upload,room_permissions.can_remove,rooms.is_public,filelist.id_room,'.
			'DATE_FORMAT(room_permissions.expires,\'%m-%d-%y\') '.
			'FROM filelist  '.
			'LEFT JOIN room_permissions '.
			'ON filelist.id_room=room_permissions.id_room AND room_permissions.id_user=? AND room_permissions.expires>NOW() '.
			'LEFT JOIN rooms '.
			'ON filelist.id_room=rooms.id_room '.
			'WHERE filelist.id_file=? ',
			array('si',$this->useraccount->user_details['id_user'],$this->id_file),
			$results);
			foreach($results AS $data)
			{
				// If a single file ID is in more than one room (shouldn't happen)
				// We put the last room ID found in as the "currently selected" room to get
				// permissions from.
				$this->results[$data['id_room']] = $data;
				$this->id_room = $data['id_room'];
			}
		}
		return($this->results);
	}
	public function __construct($DB,$user,$id_file=NULL)
	{
		$this->DB = &$DB;
		$this->useraccount = &$user;
		if(!(is_a($this->DB,'databaseI') && is_a($this->useraccount,'useraccount')))
		{
			exit("Missing required construction parameters in filepermissions");
		}

		$this->id_file = $id_file;
	}

	public function set_file($id_file)
	{
		$this->results = NULL;
		$this->id_file = $id_file;
	}
	public function get_filename()
	{
		if($this->checkable('filename'))
		{
			return $this->results[$this->id_room]['filename'];
		} 
		return "";
	}
	public function get_description()
	{
		if($this->checkable('description'))
		{
			return $this->results[$this->id_room]['description'];
		} 
		return "";
	}
	private function checkable($attribute)
	{
		$this->fetch_results();
		if(is_null($this->results))
		{
			return false;
		}
		if(!is_array($this->results))
		{
			return false;
		}
		if(count($this->results) < 1)
		{
			return false;
		}
		if(!array_key_exists($this->id_room,$this->results))
		{
			return false;
		}
		if(!array_key_exists($attribute,$this->results[$this->id_room]))
		{
			return false;
		}
		if(is_null($this->results[$this->id_room][$attribute]))
		{
			return false;
		}
		return true;
	}
	public function can_read($id_room = NULL)
	{	
		if(!is_null($this->useraccount) && is_a($this->useraccount,'useraccount') && $this->useraccount->user_details['is_admin'])
		{
			return true;
		}
		if(!is_null($id_room))
		{
			$this->id_room = $id_room;
		}
		if($this->checkable('is_public') && $this->results[$this->id_room]['is_public'])
		{
			return true;
		} else if($this->checkable('can_read') && $this->results[$this->id_room]['can_read'])
		{
			return true;
		}
		return false;
	}
	public function can_upload($id_room = NULL)
	{
		if(!is_null($this->useraccount) && is_a($this->useraccount,'useraccount') && $this->useraccount->user_details['is_admin'])
		{
			return true;
		}
		if(!is_null($id_room))
		{
			$this->id_room = $id_room;
		}

		if($this->checkable('can_upload') && $this->results[$this->id_room]['can_upload'])
		{
			return true;
		}
		return false;
	}
	public function can_remove($id_room = NULL)
	{
		if(!is_null($this->useraccount) && is_a($this->useraccount,'useraccount') && $this->useraccount->user_details['is_admin'])
		{
			return true;
		}
		if(!is_null($id_room))
		{
			$this->id_room = $id_room;
		}
		if($this->checkable('can_remove') && $this->results[$this->id_room]['can_remove'])
		{
			return true;
		}
		return false;
	}
}
class new_file_details extends file_details
{
	public function fetch_results()
	{
		
		if(is_null($this->results))
		{
			$results = array('id_file','filename','description','time_stamp','can_read','can_upload','can_remove','is_public','id_room','expires');
			
			$this->DB->sql(
			'SELECT (SELECT MAX(filelist.id_file)+1 FROM filelist) AS newfileID,\'newfile\',\'newfile description\',NOW(),room_permissions.can_read,room_permissions.can_upload,room_permissions.can_remove,rooms.is_public,rooms.id_room,
DATE_FORMAT(room_permissions.expires,\'%m-%d-%y\') 
FROM rooms 
LEFT JOIN room_permissions 
ON room_permissions.id_room=rooms.id_room AND 
room_permissions.id_user=? AND room_permissions.expires>NOW()',
			array('s',$this->useraccount->user_details['id_user']),
			$results);
			foreach($results AS $data)
			{
				$this->results[$data['id_room']] = $data;
				$this->id_file= is_null($data['id_file']) ? 1 : $data['id_file'];
			}

			
		}
		return($this->results);
	}
	public function __construct($DB,$user)
	{
		parent::__construct($DB,$user);
		$this->fetch_results();
	}
}

?>