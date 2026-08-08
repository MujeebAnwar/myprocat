<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/start.php');
require_once (DOCUMENT_ROOT.'/setup/force_authorized.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');

	if($Session->Log_In())
	{
		GoToPage("/resources.php");
	}

require_once (DOCUMENT_ROOT.'/template/mainframe.php');
?>