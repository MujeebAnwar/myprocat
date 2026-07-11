<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/lib/Util.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
class roomicon extends content_block
{
	
	public function __construct($room_data)
	{
		if(!is_null($room_data['background']) && $room_data['background'] !== "")
		{
			$params['class'] = 'room_icon';
			$color = pickcolor($room_data['title']);
			$params['style'] = 
			'background-color:HSL('.pickcolor($room_data['title']).',30%,75%);'.
			//'background:radial-gradient(HSL('.$color.',70%,30%),HSL('.$color.',70%,30%),HSL('.$color.',70%,10%));'.
			'background-image:url('.$room_data['background'].')';
		} else {
			$params['class'] = 'room_color_icon';
			$params['style'] = 'background-color:HSL('.pickcolor($room_data['title']).',70%,30%)';
		}
		$sec = new section(NULL,$params);
		$content = new anchor($sec, array('href' => '/room_view.php?id='.$room_data['id_room'],'class' => 'room_icon'));
		$title = $room_data['title'];
		if(!is_null($room_data['vis_title']) && $room_data['vis_title'] !== "")
		{
			$title = $room_data['vis_title'];
		}
		if(!is_null($room_data['foreground']) && $room_data['foreground'] !== "")
		{
			$p = new image($room_data['foreground'],array('class'=>'room_icon',
				'title' => $title ,'alt'=>$room_data['title']));
		} else {
			$parts = explode(': ',$title,2);
			$params = array('class' => 'room_icon');
			if(count($parts) > 1)
			{
				$params['style'] = 'margin-top:15%';
			}
			$p = new paragraph($parts[0] ,$params);
			if(count($parts) > 1)
			{
				$p->push(new paragraph($parts[1],array('class' => 'room_icon_subtitle')));
			}
		}
		$sec->push($p);
		parent::__construct($content,'raw');
	}
	public function add_style($style)
	{
		$this->content->content->add_style($style);
	}

}
?>