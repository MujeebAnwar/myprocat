<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
class hovertooltip extends content_block
{
	public function __construct($text = NULL,$parameters = array())
	{
		$p = new paragraph($text,array('class'=>'hovertooltippopup'));
		$contents = new content_block($p,'div',array('class' => 'hovertooltippopup'));
		if(!array_key_exists('class',$parameters))
		{
			$parameters = array_merge($parameters,array('class' => 'hovertooltip'));
		}
		parent::__construct($contents,'div',$parameters);
	}

};
?>