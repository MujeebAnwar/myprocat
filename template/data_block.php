<?php
class data_block
{
	private $sql;
	private $params = array();
	private $paramtypes = "";
	private $column_names = array();

	// You can re-inspect cached results
	public $results = array();

	// Setters
	public function set_sql($sql = "")
	{
		$this->sql = $sql;
	}
	public function set_columns($column_names = array())
	{
		$this->column_names = $column_names;	
	}
	public function set_paramtypes($paramtypes = "")
	{
		$this->paramtypes = $paramtypes;
	}
	public function set_params($params = array(),$column_order = NULL)
	{
		$this->params = $params;
		$paramtypes = array();
		if(array_key_exists('param_types',$params))
		{
			$this->paramtypes = $params['paramtypes'];
			unset($this->params['paramtypes']);
		}
		$this->set_paramtypes($paramtypes);
		if(!is_null($column_order) && is_array($column_order))
		{
			$this->set_columns($column_order);
		} else {
			if(array_key_exists('result_columns',$params) && is_array($params['result_columns']))
			{
				$this->set_columns($params['result_columns']);
				unset($this->params['result_columns']);
			}
		}
	}

	// Constructor
	public function __construct($sql = "",$params = array(),$column_order = NULL)
	{
		if(is_array($sql))
		{
			$this->set_sql($sql['sql']);
			$this->set_params($sql['params'],$sql['result_columns']);
		} else {
			$this->set_sql($sql);
			$this->set_params($params,$column_order);
		}
		
	}
	
	// Public Methods
	public function fetch()
	{
		$params = array($paramtypes);
		array_push($params,$this->params);
		$this->results = $this->column_names;
		$DB->sql($sql,$params,$this->results);
		return $this->results;
	}
	public function fetch_as_json()
	{
		return json_encode($this->fetch());
	}

}
?>