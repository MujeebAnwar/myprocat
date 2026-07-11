<?php
include '../config.php';
$folder_noaccess = preg_replace('/\\\\/','/',dirname(__FILE__));
$p = strrpos($folder_noaccess,'/');
$folder_noaccess = substr($folder_noaccess,$p);
// Exclude any file attempting to access a file directly in this folder
if(!(strstr($_SERVER['SCRIPT_NAME'],$folder_noaccess) === false))
{
	NoDirectAccess();
}
echo "$_SERVER[SCRIPT_NAME], $folder_noaccess";
?>