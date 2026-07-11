<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once (DOCUMENT_ROOT.'/template/form.php');

class file_move_up_button extends content_block
{
	private $id_room;
	private $file1;
	private $file2;
	public function __construct($id_room,$file1,$file2)
	{
		$this->id_room = $id_room;
		$this->file1 = $file1;
		$this->file2 = $file2;
		$content = new content_block(NULL,'div',array('class' => 'admin_up_button'));
		$ff = new form(NULL,array('method'=>'POST','action'=>'/admin/file_move.php'));
		$ff->push(new input(NULL,array('type' => 'hidden','name'=> 'id_file_1','value'=> $file1)));
		$ff->push(new input(NULL,array('type' => 'hidden','name'=> 'id_file_2','value'=> $file2)));
		$ff->push(new input(NULL,array('type' => 'hidden','name'=> 'id_room','value'=> $id_room)));
		$ff->push(new image_submitbutton('/img/upbutton.png',104,30));
		$content->push($ff);
		parent::__construct($content,'raw',array());
	}
}
class file_move_down_button extends content_block
{
	private $id_room;
	private $file1;
	private $file2;
	public function __construct($id_room,$file1,$file2)
	{
		$this->id_room = $id_room;
		$this->file1 = $file1;
		$this->file2 = $file2;
		$content = new content_block(NULL,'div',array('class' => 'admin_up_button'));
		$ff = new form(NULL,array('method'=>'POST','action'=>'/admin/file_move.php'));
		$ff->push(new input(NULL,array('type' => 'hidden','name'=> 'id_file_1','value'=> $file1)));
		$ff->push(new input(NULL,array('type' => 'hidden','name'=> 'id_file_2','value'=> $file2)));
		$ff->push(new input(NULL,array('type' => 'hidden','name'=> 'id_room','value'=> $id_room)));
		$ff->push(new image_submitbutton('/img/downbutton.png',104,30));
		$content->push($ff);
		parent::__construct($content,'raw',array());
	}
}
class file_edit_button extends content_block
{
	private $id_room;
	private $id_file;
	public function __construct($id_room,$id_file)
	{
		$this->id_room = $id_room;
		$this->id_file = $id_file;
		$content = new content_block(NULL,'div',array('class' => 'admin_edit_button'));
		$ff = new form(NULL,array('method'=>'POST','action'=>'/admin/file_edit_details.php'));
		$ff->push(new input(NULL,array('type' => 'hidden','name'=> 'id_file','value'=> $id_file)));
		$ff->push(new input(NULL,array('type' => 'hidden','name'=> 'id_room','value'=> $id_room)));
		$ff->push(new image_submitbutton('/img/editbutton.png',40,30));
		$content->push($ff);
		parent::__construct($content,'raw',array());
	}
}
class file_delete_button extends content_block
{
	private $id_room;
	private $id_file;
	public function __construct($id_room,$id_file)
	{
		$this->id_room = $id_room;
		$this->id_file = $id_file;
		$content = new content_block(NULL,'div',array('class' => 'admin_delete_button'));
		$ff = new form(NULL,array('method'=>'POST','action'=>'/admin/file_delete.php'));
		$ff->push(new input(NULL,array('type' => 'hidden','name'=> 'id_file','value'=> $id_file)));
		$ff->push(new input(NULL,array('type' => 'hidden','name'=> 'id_room','value'=> $id_room)));
		$ff->push(new image_submitbutton('/img/deletebutton.png',40,30));
		$content->push($ff);
		parent::__construct($content,'raw',array());
	}
}
class file_publish_button extends content_block
{
	private $id_room;
	private $id_file;
	public function __construct($id_room,$id_file)
	{
		$this->id_room = $id_room;
		$this->id_file = $id_file;
		$content = new content_block(NULL,'div',array('class' => 'admin_publish_button'));
		$ff = new form(NULL,array('method'=>'POST','action'=>'/admin/file_publish.php'));
		$ff->push(new input(NULL,array('type' => 'hidden','name'=> 'id_file','value'=> $id_file)));
		$ff->push(new input(NULL,array('type' => 'hidden','name'=> 'id_room','value'=> $id_room)));
		$ff->push(new image_submitbutton('/img/publishbutton.png',40,30));
		$content->push($ff);
		parent::__construct($content,'raw',array());
	}
}
class file_uploadlink extends content_block
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
	public function __construct($id_room)
	{
		

		$form = new form(NULL,array('method' => 'POST','action' => '/admin/file_upload.php','enctype'=>"multipart/form-data"));
		$content = new row(NULL,array("class" => "filelink_container"));
		$content->push(new input(NULL,array('type' => 'hidden', 'name' => 'id_room','value' => $id_room)));
		$uploadbutton = new section(
						new anchor(
							new image_submitbutton("/img/upload.png",100,100),array("href" => 'file_upload.php')
							),array("class" => "filelink_downloadbutton")
					);
		$fileinfo = new section(NULL,array("class" => "fileupload_description"));
		$fileinfo->push(
						new paragraph(
								new input(NULL,array('name' => 'UploadFile', "class" => "fileupload_filename","value" => "Select a file","type" => "file"))
							,array("class" => "fileupload_filename")
							)
						);
		$fileinfo->push(
			new paragraph(
				new content_block(NULL,'textarea',array('name' => 'description','class' => 'fileupload_description','placeholder' => 'File Description goes here')), 
				array("class" => "fileupload_description")
			)
		);
		$this->datetimesection = new section(
					new paragraph(
						"Select a file, <br />type a description, <br />then hit the upload button",
						array("class" => "filelink_date")
						),
					array("class" => "filelink_date")
					);

		$content->push($uploadbutton);
		$content->push($fileinfo);
		$content->push($this->datetimesection);
		$form->push($content);
		parent::__construct($form,'raw',NULL);
	}

	
}
class file_admin_panel extends content_block
{
	private $id_room;
	private $id_file;
	public function __construct($id_room,$curfile_ob,$id_prevfile,$id_nextfile)
	{
		$this->id_file = $id_file = $curfile_ob['id_file'];
		$content = new section(NULL,array("class" => 'adminbox',"style" => 'font-size: 0;height:90px;width:124px;margin:0;margin-left:30px;padding:0'));
		$mgrrow = new content_block(NULL,'div',array("style" => 'height:30px;width:124px;margin:0;padding:0;clear: left;overflow: none;text-align: center;'));
		if(!is_null($id_prevfile) && is_numeric($id_prevfile))
		{
			$mgrrow->push(new file_move_up_button($id_room,$id_file,$id_prevfile));
		}
		$content->push($mgrrow);
		$mgrrow = new content_block(NULL,'div',array("style" => 'font-size: 0;height:30px;width:124px;margin:0;padding:0;clear: left;overflow: none;text-align: center;'));
		$mgrrow->push(new file_edit_button($id_room,$id_file));
		if($curfile_ob['can_remove'])
		{
			$mgrrow->push(new file_delete_button($id_room,$id_file));
		}
		if($curfile_ob['is_admin'])
		{
			$mgrrow->push(new file_publish_button($id_room,$id_file));
		}
		$content->push($mgrrow);
		$mgrrow = new content_block(NULL,'div',array("style" => 'font-size: 0;height:30px;width:124px;margin:0;padding:0;clear: left;overflow: none;text-align: center;'));
		if(!is_null($id_nextfile) && is_numeric($id_nextfile))
		{
			$mgrrow->push(new file_move_down_button($id_room,$id_file,$id_nextfile));
		}
		$content->push($mgrrow);
		parent::__construct($content,'raw');
	}
}
?>