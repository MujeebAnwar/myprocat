<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once (DOCUMENT_ROOT.'/template/roomlist.php');
class menu_item extends content_block
{
	public function __construct($content=NULL,$params = array())
	{
		if(!array_key_exists('class',$params))
		{
			$params['class'] = 'menu_h';
		}
		$cnt = $content;
		if($params['href'])
		{
			$cnt = new anchor($content,array('href' => $params['href'],'class' => 'menu_h'));
			unset($params['href']);
		}
		parent::__construct($cnt,'li',$params);
	}
}
class submenu extends content_block
{
	public function __construct($content=NULL,$params = array())
	{
		if(!array_key_exists('class',$params))
		{
			$params['class'] = 'menu_h';
		}
		parent::__construct($content,'ul',$params);
	}
}
class room_submenu extends content_block
{
	public function __construct($content=NULL,$params = array())
	{
		$rl = new roomlist($params['userOb'],$params['DB']);
		$sbm = new submenu();
		foreach($rl->roomlist AS $thisroom)
		{
			#$sbm->push(new menu_item($thisroom['title'],array('href' => "/room_view.php?id=".$thisroom['id_room'])));
		}
		$content = new menu_item(array("Resources"),array('href' => "/roomlist_view.php"));
		$content->push($sbm);
		parent::__construct($content,'raw');
	}
}
class my_menu extends content_block
{
	public function __construct($content=NULL,$params = array())
	{
		$content = new menu_item(array("My Account"),array('href' => "/account_view.php"));
		if(array_key_exists('userOb',$params))
		{
			$ua = $params['userOb'];
			if(is_a($ua,'useraccount') && $ua->logged_in)
			{
				$sbm = new submenu();
				/*if($ua->user_details['owns_room'])
				{
					$sbm->push(new menu_item("My Room",array('href' => "/myroom.php")));
				}*/
				
				$sbm->push(new menu_item("Log Out",array('href' => "/logout.php")));
				$sbm->push(new menu_item("Change my password",array('href' => "/changepassword.php")));
				if($ua->user_details['is_admin'] || $ua->user_details['is_manager'])
				{
					if($ua->user_details['is_admin'] || $ua->user_details['is_manager'])
					{
						$sbm->push(new menu_item("--- Admin ---",array('href' => "")));
						$sbm->push(new menu_item("Edit Rooms",array('href' => "/admin/EditRoomlist.php")));
					}
					
					if($ua->user_details['is_admin'])
					{
						$sbm->push(new menu_item('Edit User Info',array('href' => '/admin/users/')));
						$sbm->push(new menu_item('Reset a password',array('href' => '/admin/reset_password.php')));
						$sbm->push(new menu_item('Reset hardware ID',array('href' => '/admin/reset_hardwarekey.php')));
						$sbm->push(new menu_item("Upload CSV",array('href' => "/admin/UploadCSV.php")));
						$sbm->push(new menu_item('Manage Admins',array('href' => '/admin/manage_administrators.php')));
						if($ua->user_details['email'] === 'admin@admin.com' || $ua->user_details['email'] === 'ksmith@procat.com')
						{
							$sbm->push(new menu_item('Manage Promotions',array('href' => '/admin/promotions.php')));
						}
						$sbm->push(new menu_item('Import User from Myprocat',array('href' => '/admin/import_user.php')));
						$sbm->push(new menu_item("--- Speech Rec ---",array('href' => "")));
						$sbm->push(new menu_item('Edit Speech Account',array('href' => '/Speech/accountedit.php')));
						$sbm->push(new menu_item('Speech Usage',array('href' => '/Speech/usageoverview.php')));
					}
					$content->push($sbm);
				
				}
				$content->push($sbm);
			}
		}
		parent::__construct($content,'raw');
	}
}

class menu extends content_block
{
	public function __construct($content=NULL,$params = array())
	{
		$params['class'] = 'menu_h_box';
		$ua = NULL;
		$db = NULL;
		if(array_key_exists('userOb',$params))
		{
			$ua = $params['userOb'];
			unset($params['userOb']);
		}
		if(array_key_exists('DB',$params))
		{
			$db = $params['DB'];
			unset($params['DB']);
		}
		
		parent::__construct(NULL,'section',$params);

		//$p = new paragraph("MENU",array('class' => 'menu_header'));
		//$this->push($p);

		$menubox = new content_block(NULL,'ul',array('class' => 'menu_h'));
		//$room_menu = new room_submenu(NULL,array('userOb' => $ua,'DB' => $db));
		//$menubox->push($room_menu);
		
		$this->push($menubox);
		$absfile = $content;
		if(!is_null($absfile))
		{
			if(!file_exists($absfile))
			{
				$absfile = DOCUMENT_ROOT.$content;
			}
			if(!file_exists($absfile))
			{
				foreach(explode(PATH_SEPERATOR,get_include_path()) AS $path)
				{
					$absfile = $path.$content;
					if(file_exists($absfile))
					{
						break;
					}
				}
			}
			if(file_exists($absfile))
			{
				$r = fopen ($absfile,"r");
				$stack = array(
					array(NULL,$menubox)
					);
				$depth = 0;
				$sbm = $menubox;
				while(($data = fgetcsv($r)) !== false)
				{
					// Since the top of the stack is the menu box, we want to create our items at data+1
					
					$stack[$data[0]+1][0] = new menu_item(array($data[1]),array('href' => $data[2]));
					// Then push them into the item in $stack[data]
					if(!array_key_exists('1',$stack[$data[0]]))
					{
						$stack[$data[0]][1] = new submenu();
						$stack[$data[0]][0]->push($stack[$data[0]][1]);
					}

					$stack[$data[0]][1]->push($stack[$data[0]+1][0]);
				}
			}
			
		}
		if(is_a($ua,'useraccount') && $ua->logged_in)
		{
			$menubox->push(new my_menu(NULL,array('userOb' => $ua)));
		}
		

	}
	
}
?>