<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/start.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once (DOCUMENT_ROOT.'/template/room.php');
require_once (DOCUMENT_ROOT.'/template/roomicon.php');
class roomlist extends content_block
{
	protected $DB = NULL;
	protected $userOb = NULL;
	public $roomlist = array();

	protected function fetch_list()
	{
		$this->roomlist = array ('id_room','title','vis_title','description','background','foreground','is_public');
		if(is_null($this->userOb) || !$this->userOb->logged_in)
		{
			$this->DB->sql(
				'SELECT rooms.id_room,rooms.room_title,rooms.vis_room_title,rooms.room_description,rooms.background,rooms.foreground,rooms.is_public '.
				'FROM rooms '.
				'WHERE rooms.is_public=1 '.
				'ORDER BY rooms.order',
				array(),
				$this->roomlist
				);

		} else {
			if($this->userOb->user_details['is_admin'])
			{
				$this->DB->sql(
				'SELECT rooms.id_room,rooms.room_title,rooms.vis_room_title,rooms.room_description,rooms.background,rooms.foreground,rooms.is_public '.
				'FROM rooms '.
				'ORDER BY rooms.order',
				array(''),
				$this->roomlist
				);
			} else {
			$this->DB->sql(
				'SELECT rooms.id_room,rooms.room_title,rooms.vis_room_title,rooms.room_description,rooms.background,rooms.foreground,rooms.is_public '.
				'FROM rooms '.
				'LEFT JOIN room_permissions '.
				'ON room_permissions.id_room = rooms.id_room AND room_permissions.id_user=? '.
				'WHERE rooms.is_public=1 OR (room_permissions.can_read=1 AND room_permissions.id_user=? AND room_permissions.expires>NOW()) '.
				'ORDER BY rooms.order',
				array('ss',$this->userOb->user_details['id_user'],$this->userOb->user_details['id_user']),
				$this->roomlist
				);
		}
			
		}
	}
	public function __construct($userOb = NULL,$DB = NULL)
	{
		if(is_null($DB) || !is_a($DB,'databaseI'))
		{
			exit("Invalid constructor for roomlist, missing database interface");
		}
		$this->userOb = &$userOb;
		$this->DB = &$DB;
		$this->fetch_list();
		$content = new section(NULL,array('class' => 'room_list'));
		foreach($this->roomlist AS $roomdata)
		{
			$icon = new roomicon($roomdata);
			$content->push($icon);
		}
		parent::__construct($content,'raw');
	}
}
?>