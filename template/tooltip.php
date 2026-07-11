<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
class tooltip extends content_block
{
	public function __construct($content = NULL,$params = array())
	{
		parent::__construct(NULL,'div',array("class" => 'hovertooltip'));
		$p = new content_block(
				new paragraph($content,$params),
					'div',
				array('class'=>'hovertooltippopup')
			);
		$this->push($p);
	}
}
?>
