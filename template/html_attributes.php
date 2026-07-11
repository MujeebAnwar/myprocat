<?php
class html_attributes
{
	private $attributes = array();

	public function set_attributes($attributes = array())
	{
		$this->attributes = $attributes;
	}
	public function __construct($attributes = array())
	{
		$this->set_attributes($attributes);
	}
	public function render_attributes($limit_to = NULL)
	{
		foreach($this->attributes AS $key => $value)
		{
			if(is_null($limit_to))
			{
				print " $key='$value'";
			} else if(strstr($key,$limit_to) === 0)
			{
				$subkey = substr($key,strlen($limit_to));
				print " $key='$value'";
			}
		}
	}
}
?>