<?php
require_once('config.php');
require_once(DOCUMENT_ROOT.'/Service/EmailService.php');
require_once(DOCUMENT_ROOT.'/lib/database.php');
require_once(DOCUMENT_ROOT.'/lib/rate_limiter.php');
define('RECOVERY_STATUS_ERROR', -1);
define('RECOVERY_STATUS_INIT', 0);
define('RECOVERY_STATUS_GENERATED', 1);
define('RECOVERY_STATUS_VALIDATED', 2);
define('RECOVERY_STATUS_USED', 3);
class recovery_session
{
	private $cookie_bytes = 96; // Cookies will be 2*(96 bytes in base 64)+1 length so 128+128+1 bytes long (257 bytes)
	private $token_bytes = 16; // Recovery token is 16 bytes (32 chars in hex) long (128 bits)
	private $cookieName = 'MPROCATPWDRECOVER';
	private $DB = NULL;
	private $timeout = 600; // 10*60 = 10 minutes
	private $session_data = array();
	private $session_columns = array('id_user', 'first_name', 'last_name', 'email','session_key', 'recoverytoken','Stage','tries');
	public  $last_error = "";
	public  $notification_warning = "";

	public function __construct($email_address)
	{
		$this->DB = new databaseI();
		$this->DB->sql(
			'DELETE FROM password_recovery '.
			'WHERE ADDTIME(time_stamp,SEC_TO_TIME(?)) < NOW()',
			array('i',$this->timeout)
		);

		$email_address = trim((string)$email_address);
		$sessionLoaded = $this->load_session();

		// Only reuse an existing recovery cookie when it belongs to this email.
		if($sessionLoaded
			&& (!isset($this->session_data['email'])
				|| strtolower($this->session_data['email']) !== strtolower($email_address)))
		{
			$sessionLoaded = false;
			$this->session_data = array();
			$this->last_error = '';
		}

		if(!$sessionLoaded)
		{
			$this->last_error = '';
			$results = $this->session_columns;
			$queryOk = $this->DB->sql(
				'SELECT joined.id_user,joined.first_name,joined.last_name,joined.email,0,0,0,COALESCE(SUM(password_recovery.tries),0) '.
				'FROM (SELECT id_user,first_name,last_name,email FROM accounts UNION SELECT id_user,first_name,last_name,email FROM casepad_accounts) AS joined '.
				'LEFT JOIN password_recovery ON joined.id_user=password_recovery.id_user '.
				'WHERE email=? '.
				'GROUP BY joined.id_user,joined.first_name,joined.last_name,joined.email',
				array('s',$email_address),
				$results
			);
			if($queryOk === false)
			{
				$this->last_error = 'Unable to reset your password at this time.';
				$this->session_data['Stage'] = RECOVERY_STATUS_ERROR;
				$this->session_data['tries'] = 0;
			}
			else if(count($results) === 1 && !is_null($results[0]['id_user']) && $results[0]['id_user'] !== '')
			{
				$this->session_data = $results[0];
				$this->session_data['tries'] = (int)$this->session_data['tries'];
				$this->session_data['Stage'] = (int)$this->session_data['Stage'];
				$this->generate_session();
			} else {
				$this->last_error = "We have no such e-mail address on file.";
				$this->session_data['Stage'] = RECOVERY_STATUS_ERROR;
				$this->session_data['tries'] = 0;
				setcookie($this->cookieName, '', time() - 3600, '/');
			}
		}
		if(isset($this->session_data['tries']) && (int)$this->session_data['tries'] > 5)
		{
			$this->last_error = "Your account has been locked.";
			$this->session_data['Stage'] = RECOVERY_STATUS_ERROR;
		}
	}
	private function load_session()
	{
		if(array_key_exists($this->cookieName,$_COOKIE))
		{
			$results = $this->session_columns;
			$this->DB->sql(
				'SELECT joined.id_user,joined.first_name,joined.last_name,joined.email,password_recovery.id_session,'.
				'password_recovery.id_recoverytoken,password_recovery.Stage,password_recovery.tries '.
				'FROM password_recovery '.
				'LEFT JOIN (SELECT id_user,first_name,last_name,email FROM accounts UNION SELECT id_user,first_name,last_name,email FROM casepad_accounts) AS joined ON password_recovery.id_user=joined.id_user '.
				'WHERE password_recovery.id_session=?',
				array('s',$_COOKIE[$this->cookieName]),
				$results
			);
			if(count($results) === 1)
			{
				$this->session_data = $results[0];
				$this->session_data['Stage'] = (int)$this->session_data['Stage'];
				$this->session_data['tries'] = (int)$this->session_data['tries'];
				return true;
			} else {
				$this->last_error = "Your recovery token has expired.";
				$this->session_data['Stage'] = RECOVERY_STATUS_ERROR;
				setcookie($this->cookieName, '', time() - 3600, '/');
			}
		}
		return false;
	}
	private function save_session()
	{
		// Upsert: PHP 8+ throws on duplicate-key INSERT instead of returning false,
		// so INSERT-then-UPDATE is unsafe. Use ON DUPLICATE KEY UPDATE.
		return false !== $this->DB->sql(
			'INSERT INTO password_recovery '.
			'(`id_session`,`id_user`,`id_recoverytoken`,`time_stamp`,`Stage`,`tries`) '.
			'VALUES (?,?,?,NOW(),?,?) '.
			'ON DUPLICATE KEY UPDATE '.
			'`id_user`=VALUES(`id_user`), '.
			'`id_recoverytoken`=VALUES(`id_recoverytoken`), '.
			'`Stage`=VALUES(`Stage`), '.
			'`tries`=VALUES(`tries`), '.
			'`time_stamp`=NOW()',
			array('sssii',
				$this->session_data['session_key'],
				$this->session_data['id_user'],
				$this->session_data['recoverytoken'],
				$this->session_data['Stage'],
				$this->session_data['tries'])
		);
	}
	private function delete_session()
	{
		if(!is_null($this->session_data['session_key']))
		{
			if($this->DB->sql(
				'DELETE FROM password_recovery WHERE `id_session`=?',
				array('s',$this->session_data['session_key'])
			))
			{
				return true;
			}
		}
		return false;
	}
	private function usernamematch($dbfield,$userinfo)
	{
		$database_field = strtolower($dbfield);
		$usersupplied_info = strtolower($userinfo);
		if(strlen($usersupplied_info)<1)
		{
			return false;
		}
		$pos = strpos($database_field,$usersupplied_info);
		if($pos === false)
		{
			return false;
		}
		if($pos !== 0)
		{
			if($database_field[$pos-1] !== " ")
			{
				return false;
			}
		}
		if($pos+strlen($usersupplied_info) < strlen($database_field))
		{
			if($database_field[$pos+strlen($usersupplied_info)] !== " ")
			{
				return false;
			}
		}
		return true;
	}
	public function validate($email,$recoverytoken)
	{
		if($this->session_data['tries'] > 5)
		{
			$this->last_error = "Your account has been locked.";
			$this->session_data['Stage'] = RECOVERY_STATUS_ERROR;
			return false;
		}
		if(!is_null($email) && strtolower($email) === strtolower($this->session_data['email']) &&
			!is_null($recoverytoken) && $recoverytoken === $this->session_data['recoverytoken'] &&
			$this->session_data['Stage'] === RECOVERY_STATUS_GENERATED)
		{
			$this->session_data['Stage'] = RECOVERY_STATUS_VALIDATED;
			$this->save_session();
			return true;
		} else {
			$this->session_data['tries']++;
			$this->save_session();
		}
		return false;
	}
	public function get_data($field)
	{
		if(array_key_exists($field,$this->session_data))
		{
			return $this->session_data[$field];
		}
		return NULL;
	}
	public function get_status()
	{
		if(!is_array($this->session_data) || !array_key_exists('Stage',$this->session_data))
		{
			return RECOVERY_STATUS_INIT;
		}
		return $this->session_data['Stage'];
	}
	public function new_password($new_password)
	{
		$password_changed = false;
		if(($this->session_data['Stage'] === RECOVERY_STATUS_VALIDATED )
			&& !is_null($new_password)
			&& strlen($new_password) >= 8
			&& array_key_exists('id_user',$this->session_data) 
			&& !is_null($this->session_data['id_user'])
			)
		{
			$hashword = password_hash($new_password,PASSWORD_DEFAULT);
			if($this->DB->sql(
				'INSERT INTO passwords (`id_user`,`hash_password`,`time_stamp`)
				VALUES (?,?,NOW())',
				array('ss',$this->session_data['id_user'],$hashword)))
			{
				$password_changed = true;
			}
			$this->session_data['Stage'] = RECOVERY_STATUS_USED;
			$this->delete_session();
		}
		return $password_changed;
	}
	private function generate_session()
	{
		$this->session_data['session_key'] = base64_encode(openssl_random_pseudo_bytes($this->cookie_bytes));
		// prefer only alphanumeric characters for recovery token to make it easier to copy/paste
		// (most email clients will highlight a span of alphanumeric text when double-clicked or tapped, but the highlight extent will stop at non-alphanumeric chars)
		// hex should be "good enough"
		$this->session_data['recoverytoken'] = bin2hex(openssl_random_pseudo_bytes($this->token_bytes));
		$this->session_data['Stage'] = RECOVERY_STATUS_GENERATED;
		$this->session_data['tries'] = (int)$this->session_data['tries'];
		if ($this->save_session())
		{
			setcookie($this->cookieName,$this->session_data['session_key'],time()+$this->timeout,'/');
			$this->send_notification();
		} else {
			$this->last_error = 'Unable to reset your password at this time.';
			if(!empty($this->DB->error))
			{
				$this->last_error = $this->DB->error;
			}
			$this->session_data['Stage'] = RECOVERY_STATUS_ERROR;
		}
	}
	private function send_notification()
	{
		try {
			if(rate_limiter('sendmail', 10, 86400) === false)
			{
				// Session already saved; allow form continue without sending another email.
				$this->notification_warning = 'Too many reset emails were requested recently. If you already received an email, use that token below.';
				return;
			}
		} catch (Exception $e) {
			// Do not block password reset if rate limiting fails.
			$this->notification_warning = '';
		}

		$resetUrl = 'https://'.$_SERVER['SERVER_NAME'].'/signup/forgotpassword.php'
			.'?email='.urlencode($this->session_data['email'])
			.'&Recovery_Token='.urlencode($this->session_data['recoverytoken']);
		$safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
		$safeToken = htmlspecialchars($this->session_data['recoverytoken'], ENT_QUOTES, 'UTF-8');
		$supportEmail = 'support@'.$_SERVER['SERVER_NAME'];
		$safeSupport = htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8');

		$emailBody = @file_get_contents(DOCUMENT_ROOT.'/signup/emails/forget-password.php');
		if($emailBody === false || $emailBody === '')
		{
			$emailBody = '<p>Reset your password: <a href="'.$safeUrl.'">'.$safeUrl.'</a></p><p>Recovery token: '.$safeToken.'</p>';
		}
		else
		{
			$emailBody = str_replace('{$safeUrl}', $safeUrl, $emailBody);
			$emailBody = str_replace('{$safeToken}', $safeToken, $emailBody);
			$emailBody = str_replace('{$safeSupport}', $safeSupport, $emailBody);
		}

		$m = new EmailService();
		$m->send($this->session_data['email'], 'Password Recovery', $emailBody, false);
	}
}
?>