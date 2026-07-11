<?php
require_once ('config.php');
require_once DOCUMENT_ROOT.'/lib/database.php';
class useraccount
{
	public $logged_in = false;
	public $password_expired = true;	
	// Populated with field names listed below
	public $user_details = array();
	public $error = "";

	// Private fields
	private $userDataFields = 
	array(
		'legacyHashword',
		'id_user',
		'owns_room',
		'is_manager',
		'is_admin',
		'first_name',
		'mid_name',
		'last_name',
		'is_active',
		'title',
		'email');
	private $sqlDataFields = "";
	private $password_history = array();
	const password_duration_change_days = 365; // Days since password changed before requiring user to change it
	const password_duration_locked_days = 365 * 2; // Days since password changed before user's account will no longer be accessible
	private $DB = NULL;
	// Use the global default database unless we set it to something different;
	private $_Default_DB_Name = "";

	private function by_email($email = NULL)
	{
		$results = $this->userDataFields;
		$this->DB->sql(
			'SELECT
accounts.legacyHashword,accounts.id_user,accounts.owns_room,managers.is_manager,administrators.is_admin,
accounts.first_name,accounts.mid_name,accounts.last_name,accounts.is_active,accounts.title,accounts.email
FROM accounts 
LEFT JOIN administrators ON accounts.id_user=administrators.id_user 
LEFT JOIN managers ON accounts.id_user=managers.id_user 
WHERE accounts.email = ? AND (accounts.expires + INTERVAL 3 DAY) > NOW()',
			array('s',$email),
			$results);
		if(count($results)>0)
		{
			return $results;
		}
		return NULL;
	}
	private function by_id_user($id_user = NULL)
	{
		$results = $this->userDataFields;
		$this->DB->sql(
			'SELECT
accounts.legacyHashword,accounts.id_user,accounts.owns_room,managers.is_manager,administrators.is_admin,
accounts.first_name,accounts.mid_name,accounts.last_name,accounts.is_active,accounts.title,accounts.email
FROM accounts 
LEFT JOIN administrators ON accounts.id_user=administrators.id_user 
LEFT JOIN managers ON accounts.id_user=managers.id_user 
WHERE accounts.id_user = ? AND (accounts.expires + INTERVAL 3 DAY) > NOW()',
			array('s',$id_user),
			$results);
		if(count($results)>0)
		{
			return $results;
		}
		return NULL;
	}
	private function get_passwords($id_user = NULL)
	{
		$this->password_history = array();
		if(!is_null($id_user))
		{
			$pwdResults = array ('hash_password','is_expired');
			$this->DB->sql(
				'SELECT hash_password,DATE_ADD(time_stamp,INTERVAL '.self::password_duration_change_days.' DAY)<NOW()
				FROM passwords
				WHERE id_user = ? AND DATE_ADD(time_stamp,INTERVAL '.self::password_duration_locked_days.' DAY)>NOW()
				ORDER BY id_password DESC',
				array('s',$this->user_details['id_user']),
				$pwdResults);
			$this->password_history = $pwdResults;
		}
	}
	private function populate_object($by = NULL,$data = NULL)
	{
		$results = NULL;
		$this->user_details = array();
		if($by == 'email')
		{
			$results = $this->by_email($data);
		} else if ($by = 'id_user')
		{
			$results = $this->by_id_user($data);
		}
		if(is_null($results))
		{
			if($this->DB->_DB->error != "")
			{
				$this->error .= $this->DB->_DB->error;
			} else {
				$this->error = "No such user";
			}
			$this->get_passwords();
			$this->user_details = array();
			return false;
		} else {
			$this->user_details = $results[0];
			$this->get_passwords($results[0]['id_user']);
			return true;
		}
	}
	// Call the constructor with an already open instance of the database to share DB connection between objects
	public function __construct($Already_Open_DB = NULL)
	{
		if(is_null($Already_Open_DB) )
		{
			$this->DB = new databaseI($this->_Default_DB_Name);
		} else {
			$this->DB = $Already_Open_DB;
		}
		$this->sqlDataFields = '`'.join('`,`',$this->userDataFields).'`';
	}
	public function Log_Out()
	{
		$this->logged_in = false;
		$this->password_expired = false;
		$this->user_details = array();
	}
	public function authenticate($email,$password)
	{
		$this->Log_Out();
		
		if($this->populate_object('email',$email))
		{
			if(count($this->password_history)>0)
			{ // We have at least one new password, use that
				if(strncmp('$',$this->password_history[0]['hash_password'],1)===0)
				{
					if(password_verify($password,$this->password_history[0]['hash_password']))
					{
						$this->password_expired = $this->password_history[0]['is_expired'];
						$this->logged_in = true;
						$this->DB->sql('INSERT INTO accountLog (`id_user`,`action`) VALUES (?,?)',
							array('ss',$this->user_details['id_user'],'login')
							);
					} else {
						$this->error = "We were unable to authenticate your email and password, submitted email = \"".$email."\""; 
					}
				} else {
					if(md5($password) === $this->password_history[0]['hash_password'])
					{
						$this->password_expired = true;
						$this->logged_in = true;
						$this->DB->sql('INSERT INTO accountLog (`id_user`,`action`) VALUES (?,?)',
							array('ss',$this->user_details['id_user'],'loginTemp')
							);
					} else {
						$this->error = "We were unable to authenticate your email and password, submitted email = \"".$email."\""; 
					}
				}
			} else { // Have to use legacy md5 password.
				if(strlen($this->user_details['legacyHashword']) > 0 && 
					md5($password) === $this->user_details['legacyHashword'])
				{
					$this->logged_in = true;
					$this->password_expired = true;
					$this->DB->sql('INSERT INTO accountLog (`id_user`,`action`) VALUES (?,?)',
						array('ss',$this->user_details['id_user'],'loginOldLegacy')
						);
				} else {
					$this->error = "We were unable to authenticate your email and password, submitted email = \"".$email."\""; 
				}
			}

		} else {
			$this->error = "We were unable to authenticate your email and password, submitted email = \"".$email."\""; 
			$this->logged_in = false;
		}
		return $this->logged_in;
	}
	public function resume_session($sessionOBject = NULL, $id_user = null)
	{
		$this->Log_Out();
		if(is_null($sessionOBject) || is_null($id_user))
		{
			return false;
		}
		if(!$sessionOBject->valid)
		{
			return false;
		}

		if($this->populate_object('id_user',$id_user))
		{
				$this->logged_in = true;
				if(strlen($this->user_details['legacyHashword'])<=0)
				{
					$this->password_expired = $this->password_history[0]['is_expired'];
				}
		}
		return $this->logged_in;

	}
	public function change_password($oldpassword,$newpassword,$newpasswordagain)
	{
		if(!$this->logged_in)
		{
			$this->error = "You've reached this page the wrong way";
			return false;
		}
		if(!$newpassword === $newpasswordagain)
		{
			$this->error = "New password fields do not match";
			return false;
		}
		if($oldpassword === $newpassword)
		{
			$this->error = "You must change your password to something new";
			return false;
		}
		// Must re-authenticate to change passwords
		if($this->authenticate($this->user_details['email'],$oldpassword))
		{
			if(count($this->password_history) > 0)
			{
				foreach($this->password_history AS $entry)
				{
					if(password_verify($newpassword,$entry['hash_password']))
					{
						$this->error = "You can not re-use a password from the previous 2 years.";
						return false;
					}
				}
			}
			$hashword = password_hash($newpassword,PASSWORD_DEFAULT);
			$this->DB->sql(
				'INSERT INTO passwords (`id_user`,`hash_password`,`time_stamp`)
				VALUES (?,?,NOW())',
				array('ss',$this->user_details['id_user'],$hashword));
			if(strlen($this->user_details['legacyHashword'])>0)
			{
				$this->DB->sql(
				"UPDATE `accounts` SET `legacyHashword` = '' WHERE `id_user` = ?",
				array('s',$this->user_details['id_user']));
			}
			return true;
		} else {
			$this->error = "You entered the old password incorrectly";
			return false;
		}
		return false;
	}
}
?>