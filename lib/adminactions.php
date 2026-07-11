<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/lib/form_action.php');
require_once (DOCUMENT_ROOT.'/template/room.php');
require_once (DOCUMENT_ROOT.'/lib/Util.php');
class admin_requirement extends form_requirement
{
	public function __construct($userOb)
	{
		if(!is_a($userOb,'useraccount'))
		{
			$this->error = "Invalid user object";
			return FORM_STATUS_ERROR;
		}
		parent::__construct(NULL,$userOb);
	}
	public function op()
	{
		$this->status = FORM_STATUS_ERROR;
		if($this->param->user_details['is_admin'])
		{
			$this->status = FORM_STATUS_OK;
		}
		return $this->status;

	}
}
class mgr_requirement extends form_requirement
{
	public function __construct($userOb)
	{
		if(!is_a($userOb,'useraccount'))
		{
			$this->error = "Invalid user object";
			return FORM_STATUS_ERROR;
		}
		parent::__construct(NULL,$userOb);
	}
	public function op()
	{
		$this->status = FORM_STATUS_ERROR;
		if($this->param->user_details['is_admin'] || $this->param->user_details['is_manager'] )
		{
			$this->status = FORM_STATUS_OK;
		}
		return $this->status;

	}
}
class adminform extends form_action
{
	public function __construct($DB, $sql = NULL, $bind_parameters=array(), $requirements = array(), $userOb = NULL)
	{
		if(!is_a($userOb,'useraccount'))
		{
			$this->error = "Invalid user object";
			return FORM_STATUS_ERROR;
		}
		array_push($requirements, new admin_requirement($userOb));
		parent::__construct($DB,$sql,$bind_parameters,$requirements);
	}
}
class mgrform extends form_action
{
	public function __construct($DB, $sql = NULL, $bind_parameters=array(), $requirements = array(), $userOb = NULL)
	{
		if(!is_a($userOb,'useraccount'))
		{
			$this->error = "Invalid user object";
			return FORM_STATUS_ERROR;
		}
		array_push($requirements, new mgr_requirement($userOb));
		parent::__construct($DB,$sql,$bind_parameters,$requirements);
	}

}

class file_admin_requirement extends form_requirement
{
	public function __construct($roomOb)
	{
		if(!is_a($roomOb,'room'))
		{
			$this->error = "Invalid user object";
			return FORM_STATUS_ERROR;
		}
		parent::__construct(NULL,$roomOb);
	}
	public function op()
	{
		$this->status = FORM_STATUS_ERROR;
		if($this->param->i_can_remove())
		{
			$this->status = FORM_STATUS_OK;
		}
		return $this->status;
	}
}
class ob_is_room extends form_requirement
{
	public function __construct($roomOb)
	{
		if(!is_a($roomOb,'room'))
		{
			$this->error = "Invalid room object";
			return FORM_STATUS_ERROR;
		}
		parent::__construct(NULL,$roomOb);
	}
	public function op()
	{
		$this->status = FORM_STATUS_ERROR;
		if(is_a($this->param,'room'))
		{
			$this->status = FORM_STATUS_OK;
		} else {
			$this->error = "Invalid room object";
		}
		return $this->status;
	}
}
class form_room_file_swap extends form_action
{

	public function __construct($DB,$file_details,$id_file_1,$id_file_2)
	{
		if(!is_a($file_details,'file_details'))
		{
			$this->error = "Invalid file detail object";
			return FORM_STATUS_ERROR;
		}
		if(!$file_details->id_file == $id_file_1)
		{
			$this->error = "Invalid file detail object";
			return FORM_STATUS_ERROR;
		}
		if(!$file_details->can_remove())
		{
			$this->error = "You don't have permissions to do that";
			return FORM_STATUS_ERROR;
   		}
		$action = 
		"UPDATE filelist
			SET `order` = 
            (
				SELECT * FROM 
                (
                    SELECT SUM(`order`) FROM `filelist` WHERE `id_file` IN (?, ?)
                )  AS `_T`
            ) - `order`
		WHERE `id_file` IN (?, ?)";
		
		parent::__construct($DB,$action,array('iiii',$id_file_1,$id_file_2,$id_file_1,$id_file_2),array(new field_exists('id_file_1')));
		
	}

}
class action_file_edit extends form_action
{
	private $file_details;
	public function __construct($DB,$file_details)
	{
		$this->file_details = &$file_details;
		$action = 
		'UPDATE `myprocat_new`.`filelist` '.
		'SET '.
		'`filename` = ?, '.
		'`description` = ?, '.
		'`time_stamp` = NOW() '.
		'WHERE `filelist`.`id_file` = ?';
		$id_file_exists = new field_exists('id_file');
		if($id_file_exists->op() == FORM_STATUS_INCOMPLETE)
		{
			GoToPage('/error.html');
		}
		$filename_exists = new field_exists('filename');
		if($filename_exists->op() == FORM_STATUS_INCOMPLETE)
		{
			$_POST['filename'] = "";
		}
		$description_exists = new field_exists('description');
		if($description_exists->op() == FORM_STATUS_INCOMPLETE)
		{
			$_POST['description'] = "";
		}
		parent::__construct($DB,$action,
			array('ssi',$_POST['filename'],$_POST['description'],$_POST['id_file']),
			array(
				$id_file_exists,
				$filename_exists,
				new field_exists('description'),
				new field_is_int('id_file'),
				new field_matches_string('id_file',$file_details->id_file)
				)
			);

	}
	public function submit()
	{
		if(is_a($this->file_details,'file_details'))
		{
			if($this->file_details->can_remove())
			{
				return parent::submit();
			}
		}
		$this->error = "Invalid permissions";
		return FORM_STATUS_ERROR;

	}
}

?>