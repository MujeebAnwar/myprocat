<?php
require_once(DOCUMENT_ROOT.'/lib/databaseIdentities.php');
class databaseI
{
	private $last_sql = "";
	private $last_params = array();
	private $last_results = array();

	public $_DB = null;
	public $error;

	public function __construct($identityName = null)
	{

		if ($this->_DB != null)
		{
			$this->_DB->close(); 
			$this->_DB = null;
		}
		$identities = new databaseIdentities();
		if(is_null($identityName))
		{
			$identityName = $identities->getDefaultIdentity();
		}
		$this->_DB = $identities->getDB($identityName);
		if ($this->_DB->connect_errno) 
		{
			echo "Failed to connect to MySQL: " . $this->_DB->connect_error;
			exit;
		}
	}
	public function begin_transaction(){
		$this->_DB->begin_transaction();
	}
	public function commit() {
		$this->_DB->commit();
	}
	public function rollback() {
		$this->_DB->rollback();
	}
	// Default is empty parameters, and no result parameters
	// The first bindparam is a string of 's' and 'i' where
	// it corresponds to copying the paramters as 'string' or 'integer'
	// the rest of the names in the bindparams are the
	public function sql($sql,$bindparams=array(),&$resultparams = NULL,$callback = NULL)
	{
		$copyresults = $resultparams;
		$resultparams = array();
		$this->last_params = $bindparams;
		$this->last_results = &$resultparams;
		$this->last_sql = $sql;
		if($statement = $this->_DB->prepare($sql))
		{
			$bp = array();
			if(is_array($bindparams) && count($bindparams)>1)
			{
				foreach ($bindparams as $key => &$value)
				{
					$bp[] = &$value;
				}
				unset($value);

				call_user_func_array(array($statement, "bind_param"), $bp);
			}
			try
			{
				if(!$statement->execute())
				{
					$this->error = "SQL error: ".$this->_DB->error."<br>In SQL: ".$sql;
					$statement->close();
					return false;
				}
			}
			catch (mysqli_sql_exception $e)
			{
				// Preserve legacy false-return behavior under PHP 8+ mysqli exceptions.
				$this->error = "SQL error: ".$e->getMessage()."<br>In SQL: ".$sql;
				$statement->close();
				return false;
			}
			if($copyresults != NULL)
			{
				// Map column names => bound variables (kept for result row keys).
				// PHP 8+ treats string keys in call_user_func_array as named args,
				// so bind_result must receive a numerically indexed reference list.
				$rp = array();
				foreach ($copyresults as $key => &$value)
				{
					$colName = is_int($key) ? $value : $key;
					$rp[$colName] = &$value;
				}
				unset($value);

				$bindResults = array();
				foreach ($rp as &$bound)
				{
					$bindResults[] = &$bound;
				}
				unset($bound);

				call_user_func_array(array($statement, "bind_result"), $bindResults);
				$i = 0;
				while($statement->fetch())
				{
					if(is_null($callback))
					{
						$resultparams[$i] = array();
						foreach($rp as $k=>$v)
						{
							$resultparams[$i][$k] = $v;
						}
						$i++;
					} else {
						$callback($rp);
					}
				}
				$statement->close();
				return $i;
			}
			$affected = $statement->affected_rows;
			$statement->close();
			return $affected;

		} else {
			$this->error = "SQL error: ".$this->_DB->error."<br>In SQL: ".$sql;
			return false;
		}
		
	}
	public function iid()
	{
		return $this->_DB->insert_id;
	}
}
?>