<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/template/appVersion.php');
require_once (DOCUMENT_ROOT.'/lib/Util.php');
$module_link_arr = [];
$script_link_arr = [];
$headerInjection = [];
$coreStyle = [];
$defaultStyle = [];
$masterModules = [];
$staticModules = [];
$masterCSSIncludes = [];
$masterImportCache = [];
$stack_depth = 0;
$init_modules = [];
// Get file modification time used for caching with register_css & register_module

function getImportLine($JSModuleName,$ModuleFile)
{
	global $masterImportCache;
	if(!array_key_exists($ModuleFile,$masterImportCache))
	{
		$masterImportCache[$ModuleFile] = getMtime(DOCUMENT_ROOT.$ModuleFile);
	}
	return "import { ".$JSModuleName." } from '".$ModuleFile."?mtime=".$masterImportCache[$ModuleFile]."';";
}
// Use this for javascript modules which are a dependency (Where the block is reused, or a parent element)
// use $element->jscript() for element *specific* javascript block (will be added to the *page specific* css)
function register_module($name,$module,$modTime = null)
{
	global $masterModules;
	if(is_null($modTime))
	{
		$modTime = filemtime($_SERVER["SCRIPT_FILENAME"]);
	}
	$masterModules[$name] = ['dat' => $module,'mtime' => $modTime];
}
// use NULL for default module location "" for "no root path"
function register_static_module($module,$location = "")
{
	global $staticModules;
	$staticModules[$module] = $location;
}
// Use this for base includes that will have a css dependency (Where the block is reused, or a parent element)
// use $element->css() for element *specific* CSS (will be added to the *page specific* css)
function register_css($name,$css,$modTime = null)
{
	global $masterCSSIncludes;
	if(is_null($modTime))
	{
		$modTime = filemtime($_SERVER["SCRIPT_FILENAME"]);
	}
	$masterCSSIncludes[$name] = ['dat' => $css,'mtime' => $modTime];
}
?>