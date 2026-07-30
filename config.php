<?php
if (!function_exists('NoDirectAccess')) {
	function NoDirectAccess()
	{
		header('HTTP/1.0 404 Not Found');

		//header('Location: '.'/error.html');
		exit("<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML 2.0//EN\">
<html><head>
<title>404 Not Found</title>
</head><body>
<h1>Not Found</h1>
<p>The requested URL was not found on this server.</p>
<hr>
</body></html>");
	}
}
// Exclude any URL that has ".." in it
if(!(strpos($_SERVER['REQUEST_URI'],'..') === false))
{
	NoDirectAccess();
}

if (!defined('DOCUMENT_ROOT')) {
	define('DOCUMENT_ROOT', dirname(__FILE__));
}
?>