<?php
require_once ('config.php');
require_once DOCUMENT_ROOT.'/setup/start.php';
require_once DOCUMENT_ROOT.'/templateV2/file_publish_form.php';


$breadcrumb_items = array('Home');

// Check if user is logged in and is admin
if(is_null($UserAccount) || !is_a($UserAccount,'useraccount') || !$UserAccount->logged_in || !$UserAccount->user_details['is_admin'])
{
	header('Location: resources.php');
	exit;
}

// Get room ID from URL
$id_file = isset($_POST['id_file']) ? intval($_POST['id_file']) : 0;
if($id_file <= 0) {
	header('Location: resources.php');
	exit;
}

// Handle form submission
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

		$fp = new file_details($DB,$UserAccount,$id_file);
		$fp->fetch_results();
		header('Location: resource_detail.php?id=' . array_keys($fp->fetch_results())[0]);
		}
	} 
	if($error !== "")
	{
		$set_body->push(new paragraph($error,array("class" => "errormessage")));
	}
	if(!$complete)
	{

		$fp = new file_details($DB,$UserAccount,$id_file);
		$fp->fetch_results();
		$set_body->push(new file_publish_form($_POST['id_file']));
		$breadcrumb_items[] = ['text' => $fp->get_filename(), 'url' => 'resource_detail.php?id=' . array_keys($fp->fetch_results())[0]];
	}
} else {
	$set_title = "Myprocat.com: Error";
	$set_body = new paragraph("You do not have permission to use this page.");
}

require_once (DOCUMENT_ROOT.'/templateV2/mainframe.php');
?>