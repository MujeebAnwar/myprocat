<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
class banner extends content_block
{
	public function __construct($content = NULL,$params = array())
	{
		if(!array_key_exists('class',$params))
		{
			$params['class'] = 'main_banner';
		} 
		parent::__construct(NULL,'div',$params);
		$p = new paragraph(
				new anchor(
					new image($content),
					array('href'=>'/')
					)
				,array('class'=> 'main_banner')
			);
		$this->push($p);
	}
}