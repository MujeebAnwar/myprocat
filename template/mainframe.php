<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once (DOCUMENT_ROOT.'/template/banner.php');
require_once (DOCUMENT_ROOT.'/template/footer.php');
require_once (DOCUMENT_ROOT.'/template/menu.php');
require_once (DOCUMENT_ROOT.'/template/login_bar.php');
require_once (DOCUMENT_ROOT.'/lib/messages.php');
$page = new page();
$header = new header();
if(!isset($set_title))
{
	$set_title = "Welcome to MyProcat.com!";
}
$header->push(new content_block($set_title,'title'));
$header->push(new stylesheet('/css/MasterTemplate.css'));
$header->push(new content_block(NULL,'link',array('rel' => 'shortcut icon','href' => '/favicon.ico')));
if(isset($page_script))
{
	$header->push(new content_block($page_script,'script',array("type" => 'text/javascript')));
}
$page->push($header);
$body = new body();
// Build page background if any
if(isset($set_background))
{
	$background = new content_block(NULL,'div',array('style' => 'background-image: url('.$set_background.')','class' => 'background'));
	$body->push($background);
} 
// Build header
// Build contents
$topsection = new section();
$menu = new menu("/template/mainmenu.def",array('userOb' => $UserAccount,'DB' => $DB));
$topsection->push(new login_bar($UserAccount));
$topsection->push(new banner('/img/MyProCATBannerPage2.png'));
$topsection->push($menu);
$body->push($topsection);
$contentSection = new content_block(NULL,'div',array('class' => 'content_section','style' => 'clear:both'));
//$contentSection->push($menu);
$contentBody = new section(NULL,array('style' => 'max-width: 800px;'));
if(!isset($set_body))
{
	$contentBody->push(new paragraph("Welcome to MyProcat.com!",array('class' => 'importantmessage')));
	if(!is_null($UserAccount) && $UserAccount->logged_in)
	{
		$contentBody->push(new paragraph("You are now logged in.",array('class' => 'message')));
		$contentBody->push(new paragraph("We will now direct you to the list of resource rooms.",array('class' => 'message')));
		$contentBody->push(new content_block(DelayGoToPageScript('/resources.php'),'script'));
		//DelayGoToPage('/roomlist_view.php');
	}
} else {
	$contentBody->push($set_body);
}
$contentSection->push($contentBody);
$body->push($contentSection);
if(isset($set_background))
{
	$footerparams = array('class' => 'footer_yellow');
	
} else {
	$footerparams = array();
}
if(!is_null($UserAccount) && $UserAccount->logged_in)
{
	$footerparams['restricted'] = true;
}
$footer = new footer(NULL,$footerparams);
$body->push(new content_block(NULL,'div',array('class' => 'clearfix','style' => 'clear:both')));
$body->push($footer);
$page->push($body);

if(!is_null($Session) && $Session->valid)
{
	// +3 so we avoid a race condition where you're not quite logged out in the database yet.
	// We don't want the session expiration message to renew the session if you haven't logged out yet.
	// (In case the javascript clock and server clock drift apart by 1 second or two over the 
	// session duration (defaults to 3 hours))
	$body->push(DelayShowMessage($DB,"Your session has expired, and you have been logged out.",$Session->cookie_timeout+3));
}
$page->render();

//ini_set('xdebug.var_display_max_depth', 10);
//var_dump($page);
?>