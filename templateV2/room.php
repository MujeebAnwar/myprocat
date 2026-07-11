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

class room_video_grid extends content_block
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
	private $room_obj = NULL;
	
	public function __construct($content_id_room = NULL, $parameters = array())
	{
		if(!(is_array($parameters) && array_key_exists('DB',$parameters) && is_a($parameters['DB'],'databaseI')))
		{
			exit("Invalid constructor for room_video_grid content block, missing database interface object");
		}
		$this->user_object = &$parameters['user'];
		$this->DB = &$parameters['DB'];	
		
		if(!is_null($content_id_room))
		{
			// Load room using existing room class
			$this->room_obj = new room($content_id_room, $parameters);
			$this->id_room = $this->room_obj->id_room;
			$this->title = $this->room_obj->title;
			$this->vis_title = $this->room_obj->vis_title;
			$this->description = $this->room_obj->description;
			$this->background = $this->room_obj->background;
			$this->foreground = $this->room_obj->foreground;
			$this->is_public = $this->room_obj->is_public;
			$this->permissions = $this->room_obj->permissions;
		}
		
		parent::__construct(NULL, 'raw', $parameters);
	}
	
	public function i_can_read()
	{
		return !is_null($this->room_obj) ? $this->room_obj->i_can_read() : false;
	}
	
	public function i_can_upload()
	{
		return !is_null($this->room_obj) ? $this->room_obj->i_can_upload() : false;
	}
	
	public function i_can_remove()
	{
		return !is_null($this->room_obj) ? $this->room_obj->i_can_remove() : false;
	}
	
	public function render()
	{
		$content = array();
		
		// Create video section container
		$videoSection = new content_block(NULL, 'div', array('class' => 'inner-content resource-detail-view'));
		
		// Section heading
		$headingText = !is_null($this->vis_title) && $this->vis_title !== "" ? strtoupper($this->vis_title) : strtoupper($this->title);
		$videoSection->push(new content_block($headingText, 'h2', array('style' => 'text-align: center;', 'class' => 'section-heading')));
		
		// Video grid for files
		$videoGrid = new content_block(NULL, 'div', array('class' => 'video-grid detail-grid'));
		
		// Add button container if user can upload
		if($this->i_can_upload() || (!is_null($this->user_object) && $this->user_object->user_details['is_admin']))
		{
			$buttonContainer = new content_block(NULL, 'div', array('class' => 'flex-start'));
			$buttonContainer->push(new input(null, array('type' => 'button','class' => 'primary_button', 'onclick' => 'showForm()','value' => 'Add File')));
			$videoGrid->push($buttonContainer);
		}
		
		
		// Load files from the room
		if($this->i_can_read() || (!is_null($this->user_object) && $this->user_object->user_details['is_admin']))
		{
			$filelist = array('id_file','title','filename','description','time_stamp','can_read','can_upload','can_remove');
			
			// Query files based on permissions
			if(!is_null($this->user_object) && $this->user_object->user_details['is_admin'])
			{
				$this->DB->sql(
					'SELECT filelist.id_file,filelist.title,filelist.filename,filelist.description,DATE_FORMAT(filelist.time_stamp,"%M, %e %Y, %h:%i %p"),1,1,1 '.
					'FROM filelist '.
					'WHERE filelist.id_room=? '.
					'ORDER BY filelist.order',
					array('i', $this->id_room),
					$filelist
				);
			} else if($this->is_public && is_null($this->user_object)) {
				$this->DB->sql(
					'SELECT filelist.id_file,filelist.title,filelist.filename,filelist.description,DATE_FORMAT(filelist.time_stamp,"%M, %e %Y, %h:%i %p"),1,0,0 '.
					'FROM filelist '.
					'LEFT JOIN rooms ON filelist.id_room=rooms.id_room '.
					'WHERE filelist.id_room=? AND rooms.is_public=1 '.
					'ORDER BY filelist.order',
					array('i', $this->id_room),
					$filelist
				);
			} else if(!is_null($this->user_object)) {
				$this->DB->sql(
					'SELECT filelist.id_file,filelist.title,filelist.filename,filelist.description,DATE_FORMAT(filelist.time_stamp,"%M, %e %Y, %h:%i %p"),'.
					'room_permissions.can_read,room_permissions.can_upload,room_permissions.can_remove '.
					'FROM filelist '.
					'LEFT JOIN room_permissions ON filelist.id_room=room_permissions.id_room '.
					'WHERE filelist.id_room=? AND (room_permissions.id_user=? AND room_permissions.expires>NOW()) '.
					'ORDER BY filelist.order',
					array('is', $this->id_room, $this->user_object->user_details['id_user']),
					$filelist
				);
			}
			
			// Display files as video blocks
			foreach($filelist as $file)
			{
				// Add draggable attributes for admin users
				$blockAttrs = array(
					'class' => 'video-block', 
					'data-video' => 'file-'.$file['id_file'],
					'data-file-id' => $file['id_file']
				);
				
				if(!is_null($this->user_object) && $this->user_object->user_details['is_admin']) {
					$blockAttrs['draggable'] = 'true';
					$blockAttrs['class'] = 'video-block draggable-file';
				}
				
				$videoBlock = new content_block(NULL, 'div', $blockAttrs);
				
				// File title - clickable to download
				$titleHeading = new content_block(NULL, 'h3');
				$titleLink = new content_block($file['title'], 'a', array(
					'href' => '/actions/download.php?id='.$file['id_file'], 
					'style' => 'color: inherit; text-decoration: none;',
					'title' => htmlspecialchars($file['description'], ENT_QUOTES, 'UTF-8')   // DepoDash's Editor - Winner Lite
					
				));
				$titleHeading->push($titleLink);
				$videoBlock->push($titleHeading);
				
				// File description - limited to 2 lines
				if (!empty($file['description'])) {
					$descriptionSpan = new content_block(htmlspecialchars($file['description'], ENT_QUOTES, 'UTF-8'), 'span', array(
						'class' => 'file-description-text',
						'title' => htmlspecialchars($file['description'], ENT_QUOTES, 'UTF-8')
					));
					$videoBlock->push($descriptionSpan);
				}
				// Hover icons for admin/users with permissions
				if(($file['can_upload'] || $file['can_remove']) || (!is_null($this->user_object) && $this->user_object->user_details['is_admin']))
				{
					$hoverIcons = new content_block(NULL, 'div', array('class' => 'hover-icons'));
					
			// Delete icon (if can remove)
			if($file['can_remove'] || (!is_null($this->user_object) && $this->user_object->user_details['is_admin']))
			{
				$trashIcon = new content_block(NULL, 'div', array('class' => 'icon-btn trash-icon'));
				
			// Create form for delete action
			$deleteForm = new content_block(NULL, 'form', array(
				'method' => 'POST',
				'action' => '/admin/file_delete.php',
				'style' => 'display: inline; margin: 0;',
			));
			
			// Hidden input for file id
			$deleteForm->push(new content_block(NULL, 'input', array(
				'type' => 'hidden',
				'name' => 'id_file',
				'value' => $file['id_file']
			)));
			
			// Hidden input for room id
			$deleteForm->push(new content_block(NULL, 'input', array(
				'type' => 'hidden',
				'name' => 'id_room',
				'value' => $this->id_room
			)));
				
			// Submit button with SVG
			$deleteButton = new content_block(NULL, 'button', array(
				'type' => 'submit',
				'class' => 'icon-submit-btn',
				'title' => 'Delete',
				'onclick' => "return confirm('Delete this file?')"
			));
			$deleteButton->push(new content_block('<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>', 'raw'));
			
			$deleteForm->push($deleteButton);
			$trashIcon->push($deleteForm);
			$hoverIcons->push($trashIcon);
			}
				
				// Download icon
				$downloadIcon = new content_block(NULL, 'div', array('class' => 'icon-btn download-icon'));
				$downloadLink = new content_block(NULL, 'a', array('href' => '/actions/download.php?id='.$file['id_file'], 'title' => 'Download', 'class' => 'anchor_button'));
				$downloadLink->push(new content_block('<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>', 'raw'));
				$downloadIcon->push($downloadLink);
				$hoverIcons->push($downloadIcon);
				
				// Edit icon (if can upload/admin)
				if($file['can_upload'] || (!is_null($this->user_object) && $this->user_object->user_details['is_admin']))
				{
					$editIcon = new content_block(NULL, 'div', array('class' => 'icon-btn pencil-icon'));
					$editLink = new content_block(NULL, 'a', array('href' => '/file_edit.php?id='.$file['id_file'], 'title' => 'Edit', 'class' => 'anchor_button'));
					$editLink->push(new content_block('<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>', 'raw'));
					$editIcon->push($editLink);
					$hoverIcons->push($editIcon);
				}

			$publishIcon = new content_block(NULL, 'div', array('class' => 'icon-btn eye-icon'));
			
			// Create form for publish action
			$publishForm = new content_block(NULL, 'form', array(
				'method' => 'POST',
				'action' => 'file_publish.php',
				'style' => 'display: inline; margin: 0;'
			));
			
			// Hidden input for file id
			$publishForm->push(new content_block(NULL, 'input', array(
				'type' => 'hidden',
				'name' => 'id_file',
				'value' => $file['id_file']
			)));

			$publishForm->push(new content_block(NULL, 'input', array(
				'type' => 'hidden',
				'name' => 'id_room',
				'value' => $this->id_room
			)));
			
			// Submit button with SVG
			$publishButton = new content_block(NULL, 'button', array(
				'type' => 'submit',
				'class' => 'icon-submit-btn',
				'title' => 'Publish'
			));
			$publishButton->push(new content_block('<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                                </svg>', 'raw'));
			
			$publishForm->push($publishButton);
			$publishIcon->push($publishForm);
			$hoverIcons->push($publishIcon);
					
					$videoBlock->push($hoverIcons);
				}
				
				$videoGrid->push($videoBlock);
			}
		} else {
			// No permission to view
			if(is_null($this->user_object) || !$this->user_object->logged_in)
			{
				$videoGrid->push(
					new section(new paragraph("You must be logged in to view this room",array('class' => 'importantmessage')),
						array('class' => 'whitebox')
					)
				);
			} else {
				$videoGrid->push(
					new section(new paragraph("You do not have permission to view this room.",array('class' => 'importantmessage')),
						array('class' => 'whitebox'))
				);
			}
		}
		
		$videoSection->push($videoGrid);
		
		// Form Content (for uploading new files)
		if($this->i_can_upload() || (!is_null($this->user_object) && $this->user_object->user_details['is_admin']))
		{
			$formContent = new content_block(NULL, 'div', array('class' => 'form-content', 'id' => 'formContent', 'style' => 'display: none; margin:auto; margin-top: 40px; !important'));
			
			// Create upload form
			$uploadForm = new content_block(NULL, 'form', array('method' => 'post', 'action' => '/admin/file_upload.php', 'enctype' => 'multipart/form-data'));
			$uploadForm->push(new content_block(NULL, 'input', array('type' => 'hidden', 'name' => 'id_room', 'value' => $this->id_room)));
			
			$uploadForm->push(new content_block('Add File', 'h3', array('class' => 'form-heading')));
			
			$formGrid = new content_block(NULL, 'div', array('class' => 'form-grid'));
			
			// File Title box
			$formBox1 = new content_block(NULL, 'div', array('class' => 'form-box'));
			$formBox1->push(new content_block('File Title', 'h4'));
			$roomField = new content_block(NULL, 'div', array('class' => 'room-field'));
			$roomField->push(new content_block(NULL, 'input', array('type' => 'text', 'name' => 'title', 'placeholder' => 'Enter file name', 'class' => 'room-input', 'required' => 'required')));
			$formBox1->push($roomField);
			$formGrid->push($formBox1);
			
			// File Description box
			$formBox2 = new content_block(NULL, 'div', array('class' => 'form-box'));
			$formBox2->push(new content_block('File Description', 'h4'));
			$descField = new content_block(NULL, 'div', array('class' => 'description-field'));
			$descField->push(new content_block(null, 'textarea', array('name' => 'description', 'placeholder' => 'Enter description', 'class' => 'description-input', 'rows' => '4')));
			$formBox2->push($descField);
			$formGrid->push($formBox2);
			
			// Upload File box
			$formBox3 = new content_block(NULL, 'div', array('class' => 'form-box'));
			$formBox3->push(new content_block('UPLOAD FILE', 'h4'));
			$uploadField = new content_block(NULL, 'div', array('class' => 'upload-field'));
			$uploadField->push(new content_block(NULL, 'input', array('type' => 'file', 'name' => 'UploadFile', 'id' => 'fileUpload', 'class' => 'file-input', 'accept' => '*/*', 'required' => 'required')));
			$fileLabel = new content_block(NULL, 'label', array('for' => 'fileUpload', 'class' => 'file-label'));
			$fileLabel->push(new content_block('📁', 'span', array('class' => 'file-icon')));
			$fileLabel->push(new content_block('Choose file or drag and drop', 'span', array('class' => 'file-text')));
			$uploadField->push($fileLabel);
			$formBox3->push($uploadField);
			$formGrid->push($formBox3);
			
			$uploadForm->push($formGrid);
			
			// Submit button
			$submitBtn = new content_block(NULL, 'div', array('style' => 'text-align: center; margin-top: 20px;'));
			$submitBtn->push(new input(null, array('style' => 'margin-right: 10px;', 'type' => 'button', 'class' => 'secondary_button', 'onclick' => 'hideForm()','value' => 'Cancel')));
			$submitBtn->push(new submit('Upload File', array('type' => 'submit', 'class' => 'primary_button')));
			$uploadForm->push($submitBtn);
			
			$formContent->push($uploadForm);
			$videoSection->push($formContent);
			
			// Add file upload drag-and-drop and file name display JavaScript
			$fileUploadScript = new content_block("
			document.addEventListener('DOMContentLoaded', function() {
				const fileInput = document.getElementById('fileUpload');
				const fileLabel = document.querySelector('.file-label');
				const fileText = document.querySelector('.file-text');
				const uploadField = document.querySelector('.upload-field');
				
				if (!fileInput || !fileLabel || !uploadField) return;
				
				// Store original text
				const originalText = fileText.textContent;
				
				// Handle file selection via input change
				fileInput.addEventListener('change', function(e) {
					if (this.files && this.files.length > 0) {
						const fileName = this.files[0].name;
						fileText.textContent = fileName;
						fileLabel.style.borderColor = '#4CAF50';
					} else {
						fileText.textContent = originalText;
						fileLabel.style.borderColor = '';
					}
				});
				
				// Prevent default drag behaviors
				['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
					uploadField.addEventListener(eventName, preventDefaults, false);
					document.body.addEventListener(eventName, preventDefaults, false);
				});
				
				function preventDefaults(e) {
					e.preventDefault();
					e.stopPropagation();
				}
				
				// Highlight drop area when dragging over it
				['dragenter', 'dragover'].forEach(eventName => {
					uploadField.addEventListener(eventName, highlight, false);
				});
				
				['dragleave', 'drop'].forEach(eventName => {
					uploadField.addEventListener(eventName, unhighlight, false);
				});
				
				function highlight(e) {
					fileLabel.style.borderColor = '#4CAF50';
					fileLabel.style.backgroundColor = 'rgba(76, 175, 80, 0.1)';
				}
				
				function unhighlight(e) {
					fileLabel.style.borderColor = '';
					fileLabel.style.backgroundColor = '';
				}
				
				// Handle dropped files
				uploadField.addEventListener('drop', handleDrop, false);
				
				function handleDrop(e) {
					const dt = e.dataTransfer;
					const files = dt.files;
					
					if (files.length > 0) {
						// Update the file input
						fileInput.files = files;
						
						// Display the file name
						const fileName = files[0].name;
						fileText.textContent = fileName;
						fileLabel.style.borderColor = '#4CAF50';
						
						// Trigger change event
						const event = new Event('change', { bubbles: true });
						fileInput.dispatchEvent(event);
					}
				}
			});
			", 'script', array('type' => 'text/javascript'));
			
			$videoSection->push($fileUploadScript);
		}
		
		// Add drag-and-drop JavaScript for admin users
		if(!is_null($this->user_object) && $this->user_object->user_details['is_admin']) {
			$sortScript = new content_block("
			document.addEventListener('DOMContentLoaded', function() {
				const grid = document.querySelector('.video-grid');
				if (!grid) return;
				
				let draggedElement = null;
				
				// Get all draggable file blocks
				function getDraggableFiles() {
					return Array.from(grid.querySelectorAll('.draggable-file'));
				}
				
				// Update order in the DOM and save to database
				function updateFileOrder(saveToDb = false) {
					const blocks = getDraggableFiles();
					const roomId = '".$this->id_room."';
					
					blocks.forEach((block, index) => {
						const newOrder = index + 1;
						block.dataset.order = newOrder;
					});
					
					// Prepare order data
					const orderData = blocks.map(b => ({
						id: b.dataset.fileId,
						order: b.dataset.order
					}));
					
					console.log('File order updated:', orderData);
					
					// Save to database via AJAX only if requested
					if(saveToDb) {
						saveFileOrderToDatabase(roomId, orderData);
					}
				}
				
				// Save order to database
				function saveFileOrderToDatabase(roomId, orderData) {
					const formData = new FormData();
					formData.append('id_room', roomId);
					formData.append('files', JSON.stringify(orderData));
					
					fetch('/admin/update_file_order.php', {
						method: 'POST',
						body: formData,
						credentials: 'same-origin'
					})
					.then(response => response.json())
					.then(data => {
						if(data.success) {
							console.log('File order saved successfully:', data);
						} else {
							console.error('Error saving file order:', data.message);
						}
					})
					.catch(error => {
						console.error('Network error:', error);
					});
				}
				
				// Drag event handlers
				function handleDragStart(e) {
					draggedElement = this;
					this.style.opacity = '0.5';
					e.dataTransfer.effectAllowed = 'move';
					e.dataTransfer.setData('text/html', this.innerHTML);
				}
				
				function handleDragOver(e) {
					if (e.preventDefault) {
						e.preventDefault();
					}
					e.dataTransfer.dropEffect = 'move';
					return false;
				}
				
				function handleDragEnter(e) {
					if (this.classList.contains('draggable-file')) {
						this.classList.add('drag-over');
					}
				}
				
				function handleDragLeave(e) {
					if (this.classList.contains('draggable-file')) {
						this.classList.remove('drag-over');
					}
				}
				
				function handleDrop(e) {
					if (e.stopPropagation) {
						e.stopPropagation();
					}
					
					if (draggedElement !== this && this.classList.contains('draggable-file')) {
						// Get all draggable files
						const files = getDraggableFiles();
						const draggedIndex = files.indexOf(draggedElement);
						const targetIndex = files.indexOf(this);
						
						// Reorder the elements
						if (draggedIndex < targetIndex) {
							this.parentNode.insertBefore(draggedElement, this.nextSibling);
						} else {
							this.parentNode.insertBefore(draggedElement, this);
						}
						
						// Update order and save to database
						updateFileOrder(true);
					}
					
					this.classList.remove('drag-over');
					return false;
				}
				
				function handleDragEnd(e) {
					this.style.opacity = '1';
					
					// Remove drag-over class from all elements
					getDraggableFiles().forEach(file => {
						file.classList.remove('drag-over');
					});
				}
				
				// Attach drag event listeners to all draggable files
				function initFileDragAndDrop() {
					const draggableFiles = getDraggableFiles();
					
					draggableFiles.forEach(file => {
						file.addEventListener('dragstart', handleDragStart, false);
						file.addEventListener('dragenter', handleDragEnter, false);
						file.addEventListener('dragover', handleDragOver, false);
						file.addEventListener('dragleave', handleDragLeave, false);
						file.addEventListener('drop', handleDrop, false);
						file.addEventListener('dragend', handleDragEnd, false);
					});
				}
				
				// Initialize
				initFileDragAndDrop();
				updateFileOrder();
			});
			", 'script', array('type' => 'text/javascript'));
			
			$videoSection->push($sortScript);
		}
		
		$content[] = $videoSection;
		
		// Render all content
		foreach($content as $block)
		{
			$block->render();
		}
	}
}
?>