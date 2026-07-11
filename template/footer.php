<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
class footer extends content_block
{
	public function __construct($content = NULL,$params = array())
	{
		if(!array_key_exists('class',$params))
		{
			$params['class'] = 'main_footer';
		}
		if(is_null($content))
		{
			$content = array(
				new paragraph(
					array(
					"<sup>&copy;</sup>Copyright ".strftime("%Y").", All Rights Reserved." 
					),
					array('class' => 'copyright_notice'))
				);
				if(array_key_exists('restricted',$params) && $params['restricted'] != false)
				{
					array_push($content,
					new paragraph(
					array(
						"The information contained on this page is subject to a confidentiality agreement with ProCAT and it is intended for the exclusive use of licensed ProCAT clients.  ",
						"Redistribution of this material to any other party is expressly prohibited."
					),
					array('class' => 'copyright_notice')));
				}
				array_push($content,
				new paragraph(
					"ProCAT and CaptiVision are registered trademarks of Advanced Translations Technology.  Winner, Winner VR, Winner XP, AudioTrack, AudioTrax, AudioProof, AutoIndex, AutoEdit, eScript, vScript, AudioScript, WebCaption, LEXnet, Flash and Stylus are trademarks of Advanced Translations Technology."
					,array('class' => 'copyright_notice')
					)
				);
		}

		$sec = new section(NULL,$params);
		$sec->push($content);
		parent::__construct(NULL,'raw',$params);
		$this->push($sec);
	}
}
?>