<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');

class left_sidebar extends content_block
{
	private $title;
	private $phone;
	private $email;
	private $user;
	private $DB;
	
	public function __construct($title = 'DepoDash Resource Center', $phone = '123-456-7891', $email = 'support@depodash.com', $user = NULL, $DB = NULL, $parameters = array())
	{
		$this->title = $title;
		$this->phone = $phone;
		$this->email = $email;
		$this->user = $user;
		$this->DB = $DB;
		
		// Create left sidebar container
		$sidebar = new content_block(NULL, 'div', array('class' => 'left-sidebar'));

		// logo
		$logo = new image('/img/store.png', array('style' => 'width: 100%; height: 20%; object-fit: contain;'));
		$sidebar->push($logo);
		// Page title
		// $pageTitle = new content_block($this->title, 'h1', array('class' => 'page-title'));
		// $sidebar->push($pageTitle);
		
		// Contact info section
		$contactInfo = new content_block(NULL, 'div', array('class' => 'contact-info'));
		$contactInfo->push(new content_block($this->phone, 'p', array('class' => 'phone')));
		$contactInfo->push(new content_block($this->email, 'p', array('class' => 'email')));
		$sidebar->push($contactInfo);
		
		parent::__construct($sidebar, 'raw', $parameters);
	}
}
?>

