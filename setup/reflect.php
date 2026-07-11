<?php
/*
"Reflecting" URLs just take you back to where you were after performing some action.

This presumes you have been somewhere not-reflecting on the site already.
*/

if(is_array($_COOKIE) 
	&& array_key_exists('NavNext', $_COOKIE) 
	&& !strstr($_COOKIE['NavNext'],'logout.php')
	&& !strstr($_COOKIE['NavNext'],$_SERVER['SCRIPT_NAME'])
	)
{
	GoToPage($_COOKIE['NavNext']);
} else {
	// On failure (Perhaps the cookie expired?) redirect to home page, reflecting URLs shouldn't generate any content
	GoToPage("/");
}

?>