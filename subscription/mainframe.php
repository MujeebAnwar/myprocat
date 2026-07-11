<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/start.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once (DOCUMENT_ROOT.'/lib/messages.php');
require_once (DOCUMENT_ROOT.'/templateV2/navbar.php');
require_once (DOCUMENT_ROOT.'/subscription/left-sidebar.php');

// Create the page
$page = new page(NULL, array('lang' => 'en'));

// Create header
$header = new header();
if(!isset($set_title))
{
	$set_title = "Subscription Options - DepoDash Resource Center";
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
	isset($sidebar_title) ? $sidebar_title : 'DepoDash Resource Center',
	isset($sidebar_phone) ? $sidebar_phone : '123-456-7891',
	isset($sidebar_email) ? $sidebar_email : 'support@depodash.com',
	isset($UserAccount) ? $UserAccount : NULL,
	isset($DB) ? $DB : NULL
);
$mainContainer->push($leftSidebar);

// Main content area
$mainContent = new content_block(NULL, 'div', array('class' => 'main-content'));

// Add main body content
if(!isset($set_body))
{
	$contentBody = new section(NULL);
	$contentBody->push(new paragraph("Welcome to Subscription page!",array('class' => 'importantmessage')));
} else {
	$mainContent->push($set_body);
}

$mainContainer->push($mainContent);

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

