<?php
class databaseIdentity
{
	private $_host;
	private $_user;
	private $_pass;
	private $_database;
	private $_port;
	public function __construct($user = null,$pass = null,$database = null,$host = null, $port = null)
	{
		$this->_database = 'myprocat_new';
		$this->_host = 'localhost';
		$this->_port = 3306;
		$this->_user = $user;
		$this->_pass = $pass;
		if(!is_null($database))
		{
			$this->_database = $database;
		}
		if(!is_null($host))
		{
			$this->_host = $host;
		}
		if(!is_null($port))
		{
			$this->_port = $port;
		}
	}
	public function getIdentity()
	{
		return [
		'database'=>$this->_database,
		'host'=>$this->_host,
		'port'=>$this->_port,
		'user'=>$this->_user,
		'pass'=>$this->_pass];
	}
}
class databaseIdentities
{
	private $_accounts = [];
	public function __construct()
	{
		include(DOCUMENT_ROOT.'/lib/defaultDbAccount.php');
		$accounts = scandir(DOCUMENT_ROOT.'/lib/dbAccounts',SCANDIR_SORT_ASCENDING);
		foreach($accounts AS $file)
		{
			if(strpos($file,'.php') == (strlen($file) - strlen('.php')))
			{
			
				include (DOCUMENT_ROOT.'/lib/dbAccounts/'.$file);
			}
		}
		
	}
	public function getDB($dbIdName = null)
	{
		if(is_null($dbIdName))
		{
			$dbIdName = $this->getDefaultIdentity();
		}

		$thisId = $this->_accounts[$dbIdName]->getIdentity();
		return new
			mysqli($thisId['host'],
				$thisId['user'],
				$thisId['pass'],
				$thisId['database'],
				$thisId['port']
				);
	}
	public function getDefaultIdentity()
	{
		return $this->_default;
	}
}
?>