<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/login.php');
require_once (DOCUMENT_ROOT.'/setup/force_authorized.php');
require_once (DOCUMENT_ROOT.'/setup/force_admin.php');
require_once (DOCUMENT_ROOT.'/lib/file_details.php');
require_once (DOCUMENT_ROOT.'/lib/Util.php');
require_once (DOCUMENT_ROOT.'/lib/messages.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once (DOCUMENT_ROOT.'/template/form.php');

class publish_update_form extends content_block
{
	public function __construct($id_file)
	{
		global $DB;
		global $UserAccount;
		$fp = new file_details($DB,$UserAccount,$id_file);
		$fp->fetch_results();
		$link = 'myprocat.com/actions/download.php?id='.$id_file;
		$content = new row(NULL);
		$content->push(new paragraph('Publish new version of \''.$fp->get_filename().'\'.'));
		$form = new form(NULL,array('method' => 'POST'));
		$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'action','value'=>'publish')));
		$form->push(new input(NULL,array("type"=>"hidden",'name'=> 'id_file','value'=>$id_file)));
		$form->push(new field('Product Name',array('class'=> 'filepublish', 'arrange'=> 'vertical','placeholder' => "Winner|WinnerVR")));
		$form->push(new field('Version',array('class'=> 'filepublish','arrange'=> 'vertical','placeholder' => "Full version number e.x. 2015 15.1.14")));
		$form->push(new field('Download Link',array('class'=> 'filepublish','arrange'=> 'vertical','value' => $link)));
		$form->push(new field('Release Notes Link',array('class'=> 'filepublish','arrange'=> 'vertical')));
		$form->push(new field('Purchase Link',array('class'=> 'filepublish','arrange'=> 'vertical')));	
		$form->push(new field('Daily invite limit',array('class'=> 'filepublish','arrange'=> 'vertical','value' => '0')));
		$form->push(new submit('Publish'));	
		$content->push($form);
		parent::__construct($content);

	}

}

if($Session->valid && $UserAccount->logged_in && $UserAccount->user_details['is_admin'])
{
	$set_title = "Myprocat.com: Publish File";
	$set_body = new section();
	$error = "";
	$complete = false;
	if(is_array($_POST) && array_key_exists('action',$_POST) && $_POST['action'] === 'publish')
	{
		$results = array('product','version');
		$checkproduct = $DB->sql(
			"SELECT valid_products.product_name,upgrade_records.version
			FROM valid_products 
			LEFT JOIN upgrade_records ON valid_products.product_name=upgrade_records.product_name
			WHERE valid_products.product_name =?
			ORDER BY upgrade_records.id_release DESC LIMIT 1",
			array('s',$_POST['Product_Name']),
			$results
			);
		if($_POST['Product_Name'] == "")
		{
			$error = "Missing product name. ";
		} else if (count($results) < 1) {
			$error = "Invalid product name";
		} else if ($_POST['Version'] == "") {
			$error = "Missing version number";
		} else if (!is_null($results[0]['version']) && version_compare2($_POST['Version'],$results[0]['version']) < 0) {
			$error = "Version number must be greater than ".$results[0]['version'];
		} else if ($_POST['Download_Link'] == "") {
			$error = "Missing download link";
		} else {
		$DB->sql(
			'INSERT INTO upgrade_records '.
			'(`product_name`, `version`, `download_url`, `info_url`,`purchase_url`,`daily_invites`,`release_date`) '.
			'VALUES '.
			'(?,?,?,?,?,?,NOW())',
			array('sssssi',
				$_POST['Product_Name'],
				$_POST['Version'],
				$_POST['Download_Link'],
				$_POST['Release_Notes_Link'],
				$_POST['Purchase_Link'],
				$_POST['Daily_invite_limit']
				)
			);
		$complete = true;
		$set_body->push(DelayShowMessage($DB,"Publish File Successful",1));
		}
	} 
	if($error !== "")
	{
		$set_body->push(new paragraph($error,array("class" => "errormessage")));
	}
	if(!$complete)
	{
		$set_body->push(new publish_update_form($_POST['id_file']));
	}
} else {
	$set_title = "Myprocat.com: Error";
	$set_body = new paragraph("You do not have permission to use this page.");
}
require DOCUMENT_ROOT.'/template/mainframe.php';
?>