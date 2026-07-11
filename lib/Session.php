<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/lib/database.php');
require_once (DOCUMENT_ROOT.'/lib/account.php');
class Session
{
	// --------- Session Data  ---------

	// Static data (options)
	private $cookieName = 'MYPROCATSESS';
	private $cookie_bytes = 96; // Cookies will be 2*(96 bytes in base 64)+1 length so 128+128+1 bytes long (257 bytes)
	public $cookie_timeout = 10800;// 60*60*3; // Cookie timeout for non-persistent cookies (3 hours)
	private $persistent_cookie_timeout = 15552000;//60*60*24*90; // Cookie timeout for "permanant" cookies (90 days)
 
	// Private session data
	private $session_key = "";
	private $series_key = "";
	private $persistent = false;

	// Public Session Data

	public $user = null; 		// Related User object (if any)
	public $valid = false;		// Is this session valid
	public $error = "";	// Last error


	// ---------  Database Access  --------------
	private $DB = NULL;
	// Use the global default database unless we set it to something different;
	private $_Default_DB_Name = "";
	

	/// Construction
	public function __construct(&$Already_Open_DB = NULL,$is_persistent = false)
	{
		if(is_null($Already_Open_DB) )
		{
			$this->DB = new databaseI();
		} else {
			$this->DB = &$Already_Open_DB;
		}
		$this->user = new useraccount($this->DB);
		if($is_persistent)
		{
			$persistent = true;
		}
	}

	/// Private methods
	private function generate_session()
	{
		$this->session_key = base64_encode(openssl_random_pseudo_bytes($this->cookie_bytes));
		$this->series_key = base64_encode(openssl_random_pseudo_bytes($this->cookie_bytes));
	}
	private function expire_sessions()
	{
		// No parameters or return values

		// Delete any related session data
		$expiration = $this->cookie_timeout;
		if($this->persistent)
		{
			$expiration = $this->persistent_cookie_timeout;
		}
		$this->DB->sql(
			'DELETE s, sd
				FROM sessions AS s 
				LEFT JOIN session_data AS sd 
				USING (id_session)
				WHERE ADDTIME(s.time_stamp,SEC_TO_TIME(?)) < NOW()',
			array('i',$expiration)
			);
	}
	private function delete_user_sessions($this_user = null)
	{
		if(!is_null($this_user))
		{
			$this->user = &$this_user;
		}
		if(!is_null($this->user))
		{
			if(is_array($this->user->user_details) && array_key_exists('id_user', $this->user->user_details) && !is_null($this->user->user_details['id_user']))
			{
				$this->DB->sql(
					'DELETE s, sd
						FROM sessions AS s 
						LEFT JOIN session_data AS sd 
						USING (id_session)
						WHERE s.id_user = ?',
					array('s',$this->user->user_details['id_user'])
					);
			}
		}
	}
	private function new_session()
	{
		$this->generate_session();
		$idu = "";
		if(!is_null($this->user))
		{
			$idu = $this->user->user_details['id_user'];	
		}
		$this->DB->sql( 
			'INSERT INTO sessions (id_user,session_key,series_key,persistent,time_stamp) 
				VALUES (?,?,?,?,NOW())', 
			array('sssi',$idu,$this->session_key,$this->series_key,$this->persistent)
			);
		$this->update_expiry();
		$this->valid = true;
		return true;	
	}
	


	private function update_expiry($fromAPI = false)
	{
		if($fromAPI || array_key_exists('resource',$_GET))
		{
			$this->DB->sql(
					'UPDATE sessions SET time_stamp = NOW() WHERE session_key = ?',
					array('s',$this->session_key));
		} else {
			$this->series_key = base64_encode(openssl_random_pseudo_bytes($this->cookie_bytes));
			$this->DB->sql(
						'UPDATE sessions SET time_stamp = NOW(),series_key = ? WHERE session_key = ?',
						array('ss',$this->series_key,$this->session_key));
		}
	}

	private function cookie_auth($fromAPI = false)
	{
		$this->Log_Out();
		if(is_array($_COOKIE) && array_key_exists($this->cookieName,$_COOKIE))
		{
			// previously existing cookie is here, 
			// check to see if it's still around
			list($this->session_key, $this->series_key,$discard) =
    		explode(":", $_COOKIE[$this->cookieName].":", 3);
			$results = array ('id_user','series_key','persistent');
			$this->DB->sql(
				'SELECT id_user,series_key,persistent FROM sessions WHERE session_key = ?',
				array('s',$this->session_key),
				$results);
			if(count($results)>0 && ($fromAPI || $this->series_key === $results[0]['series_key']))
			{
				// We are already logged in
				// Acquire User info and update session expiration
				if (is_null($this->user))
				{
					$this->user = new useraccount($this->DB);
				}
				$this->valid = true;
				$this->persistent = $results[0]['persistent'];
				$this->user->resume_session($this,$results[0]['id_user']);
				$this->update_expiry($fromAPI);	
			} else {
				if(count($results) > 0)
				{
					$this->error = "Your session has been de-authorized because it was illicitly transferred";
					$this->DB->sql(
					'DELETE FROM sessions WHERE session_key = ?',
					array('s',$this->session_key));
				} else {
					$this->error = "Your session has expired";
				}
				// Redundant, for clarity
				$this->valid = false;
			}
		} else {
			$this->error = "You are not logged in";
			// Redundant, for clarity
			$this->valid = false;
		}
		return $this->valid;
	}

	/// Public Methods
	public function &DB()
	{
		return $this->DB;
	}
	public function make_cookie()
	{
		$cookieData = $this->session_key.":".$this->series_key;
		$expiration = $this->cookie_timeout;
		if($this->persistent)
		{
			$expiration = $this->persistent_cookie_timeout;
		}
		setcookie($this->cookieName,$cookieData,time()+$expiration,'/');
	}
	public function Log_Out()
	{
		if($this->valid && !array_key_exists('resource',$_GET))
		{
			if($this->DB->sql( 'DELETE FROM sessions WHERE session_key=?', 
				array('s',$this->session_key))<1)
			{
				$this->error .= "Error logging out:".$this->DB->error;
			}
			if(!is_null($this->user))
			{
				$this->delete_user_sessions();
				$this->user->Log_Out();
			}
			
		} 
		$this->user = NULL;
		$this->valid = false;
		$this->session_data = array();
	}
	public function API_Log_In()
	{
		if(array_key_exists('apikey',$_POST))
		{
			$authOb = json_decode($_POST['apikey']);
			if($this->fromOB($authOb))
			{
				$this->update_expiry(true);
				$this->valid = true;
			}
		} else if($this->cookie_auth(true))
		{
			$this->update_expiry(true);
			$this->valid = true;
		} 
	}
	public function asJSON()
	{
		return make_json([$this->session_key, $this->series_key]);
	}
	public function fromOB($authKey)
	{
		$_COOKIE[$this->cookieName] = $authKey[0].":".$authKey[1];
		return $this->cookie_auth(true);
	}
	public function Log_In($email=null,$password=null)
	{
		
		if($this->valid && is_null($email) && !is_null($this->user) && $this->user->logged_in)
		{
			return true;
		}
		$this->expire_sessions();
		if(is_null($email))
		{
			if($this->cookie_auth())
			{
				$this->update_expiry();
				$this->valid = true;
			}
		} else {
			if($this->user->authenticate($email,$password))
			{
				$this->valid = true;
				$this->new_session();
			} else {
				$this->valid = false;
			}	
		}
		if($this->valid)
		{
			$this->make_cookie();
		}
		if(is_null($this->user))
		{
			$this->error = "Unable to load accounts";
			return false;
		}
		// Pass log in errors from the user module
		$this->error = $this->user->error;
		return $this->user->logged_in;
	}
	public function change_password($oldpassword,$newpassword,$newpasswordagain)
	{
		if(is_null($this->user))
		{
			return false;
		}
		if(!$this->user->change_password($oldpassword,$newpassword,$newpasswordagain))
		{
			$this->error = $this->user->error;
			if($this->error === "You entered the old password incorrectly")
			{
				$this->Log_Out();
			}
			return false;
		}
		return true;
	}

}