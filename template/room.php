<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/start.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once (DOCUMENT_ROOT.'/template/adminbuttons.php');
class room_permissions
{
	private $permissions;
	public function __construct($permission_list)
	{
		$this->permissions = $permission_list;
	}
	public function has_permission($id_user = NULL, $permission_type = 'can_read')
	{
		if(!is_array($this->permissions))
		{
			exit("Fatal application error, invalid room permission array");
		}
		foreach($this->permissions AS $perm)
		{
			if($perm['id_user'] == $id_user)
			{
				return $perm[$permission_type];
			}
		}
		return false;
	}
	public function can_read($id_user = NULL)
	{
		return $this->has_permission($id_user,'can_read');
	}
	public function can_upload($id_user = NULL)
	{
		return $this->has_permission($id_user,'can_upload');
	}
	public function can_remove($id_user = NULL)
	{
		return $this->has_permission($id_user,'can_remove');
	}
}
class filelink extends content_block
{
	private $filename = "";
	private $description = "";
	private $timestamp = "";
	private $fileID = "";
	private $fileRef = NULL;
	private $useraccount = NULL;
	private $is_admin = false;
	private $icon = "";
	public $fileinfosection;
	public $datetimesection;
	public function __construct($file_ID,$parameters)
	{
		if(!is_array($parameters))
		{
			exit("Invalid constructor for filelink object");
		}
		if(!array_key_exists('filename',$parameters)) exit("Invalid filelink constructor, missing filename");
		if(!array_key_exists('description',$parameters)) exit("Invalid filelink constructor, missing description");
		if(!is_numeric($file_ID)) exit("Invalid filelink constructor, invalid fileID");
		if(!array_key_exists('filename',$parameters)) exit("Invalid filelink constructor, missing filename");
		if(is_array($parameters) && array_key_exists('user',$parameters) && is_a($parameters['user'],'useraccount'))
		{
			$useraccount = &$parameters['user'];
		}
		if(isset($useraccount) && !is_null($useraccount) && $useraccount->logged_in && 
			is_array($useraccount->user_details) && $useraccount->user_details['is_admin'])
		{
			$this->is_admin = true;
		}
		$this->filename = $parameters['filename'];
		$this->description = $parameters['description'];
		$this->timestamp = $parameters['time_stamp'];
		$this->fileID = $file_ID;
		if (array_key_exists('fileRef',$parameters))
		{
			$this->fileRef = $parameters['fileRef'];
		} else {
			$this->fileRef = "/actions/download.php?id=".$this->fileID;
		}
		if(array_key_exists('icon',$parameters)){
			$this->icon = $parameters['icon'];
		} else {
			$this->set_default_icon($this->filename);
		}
		$content = new row(NULL,array("class" => "filelink_container"));
		$downloadbutton =new anchor(
							new image("/img/DownloadButtonRed.png",array('width' => 150,'style' => 'margin-top:4px')),array("style"=>'float:left',"href" => $this->fileRef)
							);
		$fileinfo = new section(NULL,array("class" => "filelink_description"));
		//$fileinfo->push($downloadbutton);
		$fileinfo->push(
						new paragraph(
							new anchor(
								array(
									new image("/ico/".$this->icon,array('class' => 'filelink_icon')),
									$this->filename
									),array("href" => $this->fileRef, "class"=>"filelink_anchor")
								),
								array("class" => "filelink_filename")
							)
						);
		$fileinfo->push(new paragraph($this->description, array("class" => "filelink_description")));
		$this->datetimesection = new section(
					new paragraph(
						$this->timestamp,
						array("class" => "filelink_date")
						),
					array("class" => "filelink_date")
					);

		//$content->push($downloadbutton);
		$content->push($fileinfo);
		$content->push($this->datetimesection);
		parent::__construct($content,'raw',$parameters);
	}
	public function set_default_icon($filename = NULL)
	{
		if(is_null($filename) || strrchr($filename,".") === false)
		{
			$this->icon = "gears.png";
			return;
		}
		if(!(stripos($filename,"Winner_Setup")===false))
		{
			$this->icon = "winnericon.png";
			return;
		}
		switch(strrchr($filename,"."))
		{
		case ".exe":
			$this->icon = "program.png";
			break;
		case ".pdf":
			$this->icon = "pdficon_large.png";
			break;
		case ".zip":
			$this->icon = "zipicon.png";
			break;
		case ".txt":
			$this->icon = "texticon.png";
			break;
		default:
			$this->icon = "gears.png";
		}
	}
}
class license_list extends content_block
{
	private $roomOb;
	private $DB;
	private $useraccount;
	private $filelist;

	public function __construct($roomOb,$DB,$user)
	{
		$this->roomOb = $roomOb;
		$this->DB = $DB;
		$this->useraccount = $user;
		$content = new section();
		$filelist = array('id_file','filename','time_stamp');
		$this->DB->sql(
			'SELECT id,computer_name,time_stamp '.
			'FROM hardware_2021 '.
			'WHERE id_user=? '.
			'ORDER BY computer_name',
			array('s',$this->useraccount->user_details['id_user']),
			$filelist
		);
		//$content->push(new paragraph());
		foreach($filelist AS $filedata)
		{
			$filedata['user'] = &$this->useraccount;
			$filedata['filename'] = self::sanitize_filename($filedata['filename']); // dont need an extension, for display purposes only, default gear icon okay
			$filedata['description'] = 'If the computer named "'.$filedata['filename'].'" is typically kept offline, you may download a file to import into the Winner License Service (requires Winner 24.6.5 or later).';
			$output_filename = sprintf(
				'Winner_License_%s_%s_%s.txt',
				date('Y-m-d'),
				self::sanitize_filename($filedata['filename']),
				self::sanitize_filename($this->roomOb->title)
			);
			$filedata['fileRef'] = "/actions/download_license.php?id=".$filedata['id_file']."&rt=".urlencode($this->roomOb->title)."&fn=".urlencode($output_filename);
			$newrow = new filelink($filedata['id_file'],$filedata);
			$content->push($newrow);
		}
		parent::__construct($content,'raw');
	}
	private static function sanitize_filename($filename)
	{
		return preg_replace('/[^A-Za-z0-9_]+/', '-', $filename);
	}
}
class filelist extends content_block
{
	// Local references to external objects
	private $useraccount;
	private $DB;

	// Internal data
	private $id_room; // Room identifier
	private $is_public;
	private $filelist;
	private $title;
	private $description;

	public function load_list()
	{
		if(is_null($this->useraccount) && $this->is_public)
		{
			$this->filelist = array('id_file','filename','description','time_stamp','can_read','can_upload','can_remove','is_admin');
			$this->DB->sql(
			'SELECT filelist.id_file,filelist.filename,filelist.description,DATE_FORMAT(filelist.time_stamp,"%M, %e %Y, %h:%i %p"),'.
			'1,0,0,0 '.
			'FROM filelist '. 
			'LEFT JOIN rooms '.
			'ON filelist.id_room=rooms.id_room '. 
			'WHERE filelist.id_room=? AND rooms.is_public=1 '.
			'ORDER BY filelist.order',
			array('i',$this->id_room),
			$this->filelist);
		
		} else {
			if(!is_null($this->useraccount) && is_a($this->useraccount,'useraccount') && $this->useraccount->user_details['is_admin'])
			{
				$this->filelist = array('id_file','filename','description','time_stamp','can_read','can_upload','can_remove','is_admin');
				
				$this->DB->sql(
				'SELECT filelist.id_file,filelist.filename,filelist.description,DATE_FORMAT(filelist.time_stamp,"%M, %e %Y, %h:%i %p"),'.
				'1,1,1,1 '.
				'FROM filelist '. 
				'WHERE filelist.id_room=? '.
				'ORDER BY filelist.order',
				array('i',$this->id_room),
				$this->filelist);
			} else {
				$this->filelist = array('id_file','filename','description','time_stamp','can_read','can_upload','can_remove','is_admin');
				
				$this->DB->sql(
				'SELECT filelist.id_file,filelist.filename,filelist.description,DATE_FORMAT(filelist.time_stamp,"%M, %e %Y, %h:%i %p"),'.
				'room_permissions.can_read,room_permissions.can_upload,room_permissions.can_remove,0 '.
				'FROM filelist '. 
				'LEFT JOIN room_permissions '.
				'ON filelist.id_room=room_permissions.id_room '. 
				'WHERE filelist.id_room=? AND (room_permissions.id_user=? AND room_permissions.expires>NOW())'.
				'ORDER BY filelist.order',
				array('is',$this->id_room,$this->useraccount->user_details['id_user']),
				$this->filelist);
			}
		}
		return count($this->filelist);
	}
	public function __construct($roomOb,$DB,$user)
	{
		if(!is_a($DB,'DatabaseI'))
		{
			exit("Invalid constructor for filelist, no database interface");
		}
		if(is_null($roomOb) || !is_a($roomOb,'room'))
		{
			exit("Invalid constructor for filelist, invalid room object");
		}
		$this->id_room = $roomOb->id_room;
		$this->is_public = $roomOb->is_public;
		$this->DB = &$DB;
		$this->useraccount = &$user;
		$this->load_list();
		$content = new section(NULL,array("class" => "filelist_list"));
		$roomtitle = $roomOb->title;
		$params = array("class" => "filelist_roomtitle");
		if($roomOb->description === "")
		{
			$params['style'] = 'margin-bottom: 25px;';
		}
		if(!is_null($roomOb->vis_title) && $roomOb->vis_title !== "")
		{
			$roomtitle = $roomOb->vis_title;
		}
		$row = new row(
			new paragraph($roomtitle,$params),
			array("class" => "filelist_roomtitle"));
		if($roomOb->description !== "")
		{
			$row->push(new row(new paragraph(
				$roomOb->description,array('class' => 'filelist_roomdescription'))
			,array('class' => 'filelist_roomdescription')));
		}
		$content->push($row);
		$above = NULL;
		$cur = NULL;
		$prevfile = NULL;
		if($roomOb->i_can_upload() || (!is_null($this->useraccount) && $this->useraccount->user_details['is_admin']))
		{
			$content->push(new file_uploadlink($roomOb->id_room));
		}
		foreach($this->filelist AS $filedata)
		{
			$filedata['user'] = &$this->useraccount;
			$newrow = new filelink($filedata['id_file'],$filedata);
			$content->push($newrow);
			if($roomOb->i_can_remove())
			{
				if(!is_null($prevfile))
				{
					$adminpanel = new file_admin_panel($this->id_room,$cur,$above,$filedata['id_file']);
					$prevfile->datetimesection->push($adminpanel);
				}
				$above = $cur['id_file'];
				$cur = $filedata;
				$prevfile = $newrow;
			}
		}
		if($roomOb->i_can_remove() && !is_null($prevfile))
		{
			$adminpanel = new file_admin_panel($this->id_room,$cur,$above,NULL);
			$prevfile->datetimesection->push($adminpanel);
		}
		parent::__construct($content,'raw');
	}
}

class room extends content_block
{
	public $id_room = NULL;
	private $user_object = NULL;
	private $DB = NULL;
	public $permissions = NULL;
	public $title = NULL;
	public $vis_title = NULL;
	public $description = NULL;
	public $background = NULL;
	public $foreground = NULL;
	public $is_public = false;
	public $keyless_premium = false;
	public function load_permissions()
	{
		$permission_list = array('id_user','can_read','can_upload','can_remove','id_room');
		$this->DB->sql(
			'SELECT accounts.id_user,room_permissions.can_read,room_permissions.can_upload,room_permissions.can_remove,room_permissions.id_room '.
			'FROM accounts LEFT JOIN room_permissions ON accounts.id_user=room_permissions.id_user '.
			'WHERE room_permissions.id_user=? AND room_permissions.expires>NOW() AND room_permissions.id_room=?',
			array('si',$this->user_object->user_details['id_user'],$this->id_room),
			$permission_list);
		$this->permissions = new room_permissions($permission_list);
	}
	public function load_room($id_room = NULL)
	{
		if(!is_null($id_room))
		{
			$this->id_room = $id_room;
			$this->content = NULL;
			$this->title = NULL;
			$this->description = NULL;
			$this->background = NULL;
		}
		$results = array ('title','vis_title','description','background','foreground','is_public','keyless_premium');
		$this->DB->sql(
			'SELECT room_title,vis_room_title,room_description,background,foreground,is_public,keyless_premium '.
			'FROM rooms '.
			'LEFT JOIN rooms_keyless_premium ON rooms.id_room = rooms_keyless_premium.id_room '.
			'WHERE rooms.id_room=? '.
			'ORDER BY rooms.order',
			array('i',$this->id_room),
			$results
			);
		if(count($results) === 1)
		{
			$this->title = $results[0]['title'];
			$this->vis_title = $results[0]['vis_title'];
			$this->description = $results[0]['description'];
			$this->background = $results[0]['background'];
			$this->foreground = $results[0]['foreground'];
			$this->is_public = $results[0]['is_public'];
			$this->keyless_premium = $results[0]['keyless_premium'];
		}
	}
	public function populate_room($room_data = array())
	{
		$this->id_room = $room_data['id_room'];
		$this->title = $room_data['title'];
		$this->description = $room_data['description'];
		$this->background = $room_data['background'];
		$this->foreground = $room_data['foreground'];
	}
	public function __construct($content_id_room = NULL,$parameters = array())
	{
		if(!(is_array($parameters) && array_key_exists('DB',$parameters) && is_a($parameters['DB'],'databaseI')))
		{
			exit("Invalid constructor for room content block, missing database interface object");
		}
		$this->user_object = &$parameters['user'];
		$this->DB = &$parameters['DB'];	
		unset ($parameters['user']);
		unset ($parameters['DB']);
		//var_dump($this->user_object);	
		if(!is_null($content_id_room))
		{
			$this->load_room($content_id_room);
			if(!is_null($this->user_object))
			{
				$this->load_permissions();
			}
		}
	}
	public function i_can_read()
	{
		if(!is_null($this->user_object) && is_a($this->user_object,'useraccount') && $this->user_object->user_details['is_admin'])
		{
			return true;
		}
		if($this->is_public)
		{
			return true;
		}
		if(is_null($this->permissions))
		{
			return false;
		}
		return $this->permissions->can_read($this->user_object->user_details['id_user']);
	}
	public function i_can_upload()
	{
		if(!is_null($this->user_object) && is_a($this->user_object,'useraccount') && $this->user_object->user_details['is_admin'])
		{
			return true;
		}
		if(is_null($this->permissions))
		{
			return false;
		}
		return $this->permissions->can_upload($this->user_object->user_details['id_user']);
	}
	public function i_can_remove()
	{
		if(!is_null($this->user_object) && is_a($this->user_object,'useraccount') && $this->user_object->user_details['is_admin'])
		{
			return true;
		}
		if(is_null($this->permissions))
		{
			return false;
		}
		return $this->permissions->can_remove($this->user_object->user_details['id_user']);
	}
	public function render()
	{
		$cb = new row(NULL,array('style' => 'width:850px'));
		if($this->i_can_read())
		{
			$cb->push(new filelist($this,$this->DB,$this->user_object));
			if ($this->keyless_premium)
			{
				$cb->push(new license_list($this,$this->DB,$this->user_object));
			}
		} else {
			if(is_null($this->user_object) || !$this->user_object->logged_in)
			{
				$cb->push(
				new section(new paragraph("You must be logged in to view this page",array('class' => 'importantmessage')),
					array('class' => 'whitebox')
					)
				);
			} else {
				$sec  = new section(new paragraph("You do not have permission to view this room.",array('class' => 'importantmessage')),
					array('class' => 'whitebox'));
				$cb->push($sec);
				if($this->user_object->user_details['is_admin'] || $this->user_object->user_details['is_manager'])
				{
					$cb->push(new filelist($this,$this->DB,$this->user_object));
				}
			}
		}
		$cb->render();
	}	
}
?>