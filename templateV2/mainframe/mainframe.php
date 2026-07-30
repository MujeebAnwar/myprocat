<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/start.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once (DOCUMENT_ROOT.'/lib/messages.php');
require_once (DOCUMENT_ROOT.'/templateV2/mainframe/navbar.php');
require_once (DOCUMENT_ROOT.'/templateV2/mainframe/left-sidebar.php');
require_once (DOCUMENT_ROOT.'/templateV2/mainframe/right-sidebar.php');

// Create the page
$page = new page(NULL, array('lang' => 'en'));

// Create header
$header = new header();
if(!isset($set_title))
{
	$set_title = "DepoDash Resource Center - Home";
}
$header->push(new content_block(NULL, 'meta', array('charset' => 'UTF-8')));
$header->push(new content_block(NULL, 'meta', array('name' => 'viewport', 'content' => 'width=device-width, initial-scale=1.0')));
$header->push(new title($set_title));
$header->push(new stylesheet('/css/depodash.css'));
$header->push(new content_block(NULL,'link',array('rel' => 'shortcut icon','href' => '/favicon.ico')));

if(isset($page_script))
{
	$header->push(new content_block($page_script, 'script', array('type' => 'text/javascript')));
}

$page->push($header);

// Create body
$body = new body();

// Add navbar - pass UserAccount and DB for dynamic functionality
$navbar = new navbar(
	isset($UserAccount) ? $UserAccount : NULL,
	isset($DB) ? $DB : NULL
);
$body->push($navbar);

// Main container
$mainContainer = new content_block(NULL, 'div', array('class' => 'main-container'));

// Left sidebar - pass dynamic data
$leftSidebar = new left_sidebar(
	isset($sidebar_title) ? $sidebar_title : 'ProCAT Resource Center',
	isset($sidebar_phone) ? $sidebar_phone : '+01 818 222 5010',
	isset($sidebar_email) ? $sidebar_email : 'support@procat.com',
	isset($UserAccount) ? $UserAccount : NULL,
	isset($DB) ? $DB : NULL
);
$mainContainer->push($leftSidebar);

// Main content area
$mainContent = new content_block(NULL, 'div', array('class' => 'main-content'));

// Optional page banner (renders above breadcrumbs)
if(isset($page_banner) && !is_null($page_banner))
{
	$mainContent->push($page_banner);
}

// Breadcrumb navigation
// $breadcrumb_items should be an array of arrays with 'text' and 'url' keys
// Example: array(array('text' => 'Home', 'url' => '/'), array('text' => 'Page', 'url' => '/page.php'))
if(!isset($breadcrumb_items))
{
	$breadcrumb_items = array(array('text' => 'Home', 'url' => 'resources.php'));
}
$breadcrumb = new content_block(NULL, 'nav', array('class' => 'breadcrumb', 'id' => 'breadcrumbNav'));

foreach($breadcrumb_items as $index => $item)
{

	// Support both old format (string) and new format (array with text and url)
	$text = is_array($item) ? $item['text'] : $item;
	$url = is_array($item) && isset($item['url']) ? $item['url'] : '#';
	
	// If using old string format and text is 'Home', use resources.php as default URL
	if(!is_array($item) && strtolower($text) === 'home')
	{
		$url = 'resources.php';
	}
	
	if($index === count($breadcrumb_items) - 1)
	{
		// Last item is active (not clickable)
		$link = new content_block($text, 'a', array('href' => $url, 'class' => 'breadcrumb-item active'));
		$breadcrumb->push($link);

	} else {
		// Non-active items are clickable links
		$link = new content_block($text, 'a', array('href' => $url, 'class' => 'breadcrumb-item breadcrumb-link'));
		$breadcrumb->push($link);
		$breadcrumb->push(new content_block('>', 'span', array('class' => 'breadcrumb-separator')));
	}
}
$mainContent->push($breadcrumb);

// Add main body content
if(!isset($set_body))
{
	$contentBody = new section(NULL);
	$contentBody->push(new paragraph("Welcome to MyProcat.com!",array('class' => 'importantmessage')));
	if(!is_null($UserAccount) && $UserAccount->logged_in)
	{
		$contentBody->push(new paragraph("You are now logged in.",array('class' => 'message')));
		$contentBody->push(new paragraph("We will now direct you to the list of resource rooms.",array('class' => 'message')));
		$contentBody->push(new content_block(DelayGoToPageScript('/roomlist_view.php'),'script'));
	}
} else {
	$mainContent->push($set_body);
}

$mainContainer->push($mainContent);

// Right sidebar - pass UserAccount and DB for dynamic content
// $rightSidebar = new right_sidebar(
// 	isset($UserAccount) ? $UserAccount : NULL,
// 	isset($DB) ? $DB : NULL
// );
// $mainContainer->push($rightSidebar);

$body->push($mainContainer);
$page->push($body);

// Handle session timeout messages (like old template)
if(!is_null($Session) && $Session->valid)
{
	// +3 so we avoid a race condition where you're not quite logged out in the database yet.
	// We don't want the session expiration message to renew the session if you haven't logged out yet.
	// (In case the javascript clock and server clock drift apart by 1 second or two over the 
	// session duration (defaults to 3 hours))
	$delayMessage = DelayShowMessage($DB,"Your session has expired, and you have been logged out.",$Session->cookie_timeout+3);
	foreach($delayMessage as $element) {
		$body->push($element);
	}
}

// Render the page
$page->render();
?>

