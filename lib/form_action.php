<?php
define('FORM_STATUS_OK',"FORM_STATUS_OK");
define('FORM_STATUS_INCOMPLETE',"FORM_STATUS_INCOMPLETE");
define('FORM_STATUS_ERROR',"FORM_STATUS_ERROR");
define('FORM_STATUS_UNCHECKED',"FORM_STATUS_UNCHECKED");
define('FORM_STATUS_SUBMIT_SUCCESS',"FORM_STATUS_SUBMIT_SUCCESS");
class form_requirement
{
	public $status = FORM_STATUS_UNCHECKED;
	public $error = "";

	protected $fieldname = NULL;
	protected $field_text = NULL;
	protected $param = NULL;
	public function __construct($fieldname,$requirement_parameter)
	{
		$this->field_text = $fieldname;
		$this->param = $requirement_parameter;
		$this->fieldname = preg_replace('/ /','_',$fieldname);
		
	}
	 // Post field exists and is non-blank (base requirement)
	public function op()
	{
		
		if(is_array($_POST) && array_key_exists($this->fieldname,$_POST) && strlen($_POST[$this->fieldname]) > 0)
		{
			$this->status = FORM_STATUS_OK;
		} else {
			$this->status = FORM_STATUS_INCOMPLETE;
			$this->error = "Missing form field ".$this->field_text;
		}

		return $this->status;
	}
}

class field_exists extends form_requirement
{
	public function __construct($name)
	{
		parent::__construct($name,$name);
	}
}
class field_min_length extends form_requirement
{
	public function __construct($name,$length)
	{
		if(!is_int($length))
		{
			$this->error = "Invalid length requirement for field_length";
			return FORM_STATUS_ERROR;
		}
		parent::__construct($name,$length);
	}
	public function op()
	{
		if(parent::op() === FORM_STATUS_OK) // Field exists and is non-blank
		{
			if(strlen($_POST[$this->fieldname]) >= $this->param)
			{
				$this->status = FORM_STATUS_OK;
			} else {
				$this->status = FORM_STATUS_ERROR;
				$this->error = $this->field_text." is too short. (minimum length ".$this->param.")";
			}
		}
		
		return $this->status;
	}
}
class field_contains extends form_requirement
{
	public function __construct($name,$string)
	{
		parent::__construct($name,$string);
	}
	public function op()
	{
		if(parent::op() === FORM_STATUS_OK) // Field exists and is non-blank
		{
			if(strpos($_POST[$this->fieldname],$this->param) !== false)
			{
				$this->status = FORM_STATUS_OK;
			} else {
				$this->status = FORM_STATUS_ERROR;
				$this->error = $this->field_text." must contain ".$this->param;
			}
		}
		return $this->status;
	}
}
class field_not_contains extends form_requirement
{
	public function __construct($name,$string)
	{
		parent::__construct($name,$string);
	}
	public function op()
	{
		if(parent::op() === FORM_STATUS_OK) // Field exists and is non-blank
		{
			if(strpos($_POST[$this->fieldname],$this->param) === false)
			{
				$this->status = FORM_STATUS_OK;
			} else {
				$this->status = FORM_STATUS_ERROR;
				$this->error = $this->field_text." must not contain ".$this->param;
			}
		}
		return $this->status;
	}
}
class field_is_int extends form_requirement
{
	public function __construct($name)
	{
		parent::__construct($name,$name);
	}
	public function op()
	{
		if(parent::op() === FORM_STATUS_OK) // Field exists and is non-blank
		{
			if(is_numeric($_POST[$this->fieldname]))
			{
				$this->status = FORM_STATUS_OK;
			} else {
				$this->status = FORM_STATUS_ERROR;
				$this->error = $this->field_text." must not contain ".$this->param;
			}
		}
		return $this->status;
	}
}
class field_is_gte extends form_requirement
{
	public function __construct($name,$number)
	{
		if(!is_numeric($number))
		{
			$this->error = "Invalid requirement for field_is_gte";
			return FORM_STATUS_ERROR;
		}
		parent::__construct($name,$number);
	}
	public function op()
	{
		if(parent::op() === FORM_STATUS_OK) // Field exists and is non-blank
		{
			if($_POST[$this->fieldname] >=$this->param)
			{
				$this->status = FORM_STATUS_OK;
			} else {
				$this->status = FORM_STATUS_ERROR;
				$this->error = $this->field_text." must be at least ".$this->param;
			}
		}
		return $this->status;
	}
}
class field_is_lte extends form_requirement
{
	public function __construct($name,$number)
	{
		if(!is_numeric($number))
		{
			$this->error = "Invalid requirement for field_is_lte";
			return FORM_STATUS_ERROR;
		}
		parent::__construct($name,$number);
	}
	public function op()
	{
		if(parent::op() === FORM_STATUS_OK) // Field exists and is non-blank
		{
			if($_POST[$this->fieldname] <=$this->param)
			{
				$this->status = FORM_STATUS_OK;
			} else {
				$this->status = FORM_STATUS_ERROR;
				$this->error = $this->field_text." must be no more than ".$this->param;
			}
		}
		return $this->status;
	}
}
class field_matches_otherfield extends form_requirement
{
	protected $otherfieldname = NULL;
	protected $otherfieldtext = NULL;
	public function __construct($fieldname,$otherfieldname)
	{
		$this->otherfieldtext = $otherfieldname;
		$this->otherfieldname = preg_replace('/ /','_',$otherfieldname);
		parent::__construct($fieldname,$otherfieldname);
	}
	public function op()
	{
		if(parent::op() === FORM_STATUS_OK) // Field exists and is non-blank
		{
			if(!(is_array($_POST) && array_key_exists($this->otherfieldname,$_POST) && strlen($_POST[$this->otherfieldname]) > 0))
			{
				$this->error = "You cannot have a blank ".$this->otherfieldname;
				$this->status = FORM_STATUS_ERROR;
				return FORM_STATUS_ERROR;
			}
			if($_POST[$this->fieldname] === $_POST[$this->otherfieldname])
			{
				$this->status = FORM_STATUS_OK;
			} else {
				$this->status = FORM_STATUS_ERROR;
				$this->error = $this->field_text." must match ".$this->otherfieldtext;
			}
		}
		return $this->status;
	}	
}
class field_matches_string extends form_requirement
{
	protected $textstring = NULL;
	public function __construct($fieldname,$textstring)
	{
		$this->textstring = $textstring;
		parent::__construct($fieldname,$textstring);
	}
	public function op()
	{
		if(parent::op() === FORM_STATUS_OK) // Field exists and is non-blank
		{
			
			if($_POST[$this->fieldname] === $this->textstring)
			{
				$this->status = FORM_STATUS_OK;
			} else {
				$this->status = FORM_STATUS_ERROR;
				$this->error = $this->field_text." must match the string '".$this->textstring."'";
			}
		}
		return $this->status;
	}	
}
class field_is_email extends form_requirement
{
	public function __construct($name)
	{
		parent::__construct($name,$name);
	}
	public function op()
	{
		if(parent::op() === FORM_STATUS_OK) // Field exists and is non-blank
		{
			$break = strpos($_POST[$this->fieldname],"@");
			if($break !== false)
			{
				if($break < 1 || $break >= strlen($_POST[$this->fieldname])-2)
				{
					$this->status = FORM_STATUS_ERROR;
					$this->error = $this->field_text." must contain an e-mail address.";
				} else {
					$left = substr(0,$break-1);
					$right = substr($break);
					if(strcspn($left,'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890#-_~!$&\'()*+,;=:.') < strlen($left))
					{ 
						$this->status = FORM_STATUS_ERROR;
						$this->error = $this->field_text." must contain an e-mail address.";
						return $this->status;
					}
					$founddot = strpos($right,".");
					if(strcspn($right,'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890#-_~!$&\'()*+,;=:.') < strlen($left) ||
						$founddot === false ||
						$founddot <= 1 ||
						$founddot >= strlen($right)-2
						)
					{ 
						$this->status = FORM_STATUS_ERROR;
						$this->error = $this->field_text." must contain an e-mail address.";
						return $this->status;
					}
					
					$this->status = FORM_STATUS_OK;
				}
			} else {
				$this->status = FORM_STATUS_ERROR;
				$this->error = $this->field_text." must contain an e-mail address.";
			}
		}
		return $this->status;
	}
}


class form_action
{
	private $submit_action = NULL;
	private $requirements = NULL;
	private $DB;
	public $status = FORM_STATUS_UNCHECKED;
	public $error = "";
	public $bparams = array();
	public function check_requirements()
	{
		foreach ($this->requirements AS $req)
		{
			if(!is_subclass_of($req,'form_requirement'))
			{
				$this->error = "Invalid form requirement";
				$this->status = FORM_STATUS_ERROR;
				return FORM_STATUS_ERROR;
			}
			$this->status = $req->op();
			if($this->status !== FORM_STATUS_OK)
			{
				$this->error = $req->error;
				return $this->status;
			}

			
		}
		// All requirements have passed
		$this->status = FORM_STATUS_OK;
		return $this->status;
	}
	public function __construct($DB, $action = NULL, $bind_parameters=array(), $requirements = array())
	{
		$this->DB = $DB;
		$this->submit_action = $action;
		$this->bparams = $bind_parameters;
		$this->requirements = $requirements;
	}
	public function submit()
	{
		if($this->status === FORM_STATUS_UNCHECKED)
		{
			$this->check_requirements();
		}
		if($this->status !== FORM_STATUS_OK)
		{
			return $this->status;
		}
		if(is_callable($this->submit_action))
		{
			$this->submit_action->__invoke();
		} else {
			if($this->DB->sql($this->submit_action,$this->bparams))
			{
				$this->status =  FORM_STATUS_OK;
			} else {
				$this->status = FORM_STATUS_ERROR;
				$this->error = "DB ERROR:".$this->DB->error;
			}
		}
		return $this->status;
	}
	public function set_action($action)
	{
		$this->submit_action = $action;
	}	
}


?>