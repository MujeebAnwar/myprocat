<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/start.php');
if(!($Session->valid && $UserAccount->logged_in))
{
	GoToPage('/logout.php');
	//var_dump($Session);
}

// Otherwise load content normally
?>