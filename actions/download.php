<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/login.php');
$download_error = "You must be logged in to download that resource, please log in to continue.";
if($Session->valid && $UserAccount->logged_in)
{
	$fp = NULL;
	require_once (DOCUMENT_ROOT.'/lib/file_details.php');
	if(is_array($_GET) 
		&& array_key_exists('id',$_GET)
		&& is_numeric($_GET['id'])
		)
	{
		$id_file = $_GET['id'];
		$fp = new file_details($DB,$UserAccount,$id_file);
		$fp->fetch_results();
		if($fp->can_read() || $UserAccount->user_details['is_admin'])
		{
			$file = DOCUMENT_ROOT.'/data/'.$fp->id_room.'/'.$fp->id_file;

			if (file_exists($file)) 
			{
			    header('Content-Description: File Transfer');
			    header('Content-Type: application/octet-stream');
			    header('Content-Disposition: attachment; filename='.$fp->get_filename());
			    header('Expires: 0');
			    header('Cache-Control: must-revalidate');
			    header('Pragma: public');
			    header('Content-Length: ' . filesize($file));
			    readfile($file);
			    exit;
			} else {
				
				$download_error = "This file appears to be missing from the server, please contact tech support.";
				error_log("Missing File: ".$fp->id_room);
			}
		} else  {
			$download_error = "You do not have download permission for this resource, please contact tech support.";
			error_log("Invalid Permissions for download: ".$UserAccount->user_details['id_user']);
		}
	} else {
		$download_error = "No file specified, bad link?";
		error_log("No File specified for download from:".$UserAccount->user_details['id_user']);
	}

} else {
	$download_error = "Your session has expired (or been idle for too long), you will need to log in again";
   error_log("Attempt to download file for expired session: ".$Session->valid." && ".$UserAccount->logged_in);
}

require_once (DOCUMENT_ROOT.'/template/Master.php');
$set_body = new paragraph($download_error,array('class' => 'errormessage'));
require_once (DOCUMENT_ROOT.'/template/mainframe.php');
//GoToPage("/logout.html");
?>