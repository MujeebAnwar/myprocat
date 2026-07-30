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
	
	public function __construct($title = 'ProCAT Resource Center', $phone = '+01 818 222 5010', $email = 'support@procat.com', $user = NULL, $DB = NULL, $parameters = array())
	{
		$this->title = $title;
		$this->phone = $phone;
		$this->email = $email;
		$this->user = $user;
		$this->DB = $DB;
		
		// Create left sidebar container
		$sidebar = new content_block(NULL, 'div', array('class' => 'left-sidebar'));
		
		// Page title
		$pageTitle = new content_block($this->title, 'h1', array('class' => 'page-title'));
		$sidebar->push($pageTitle);
		

		// Training section
		$trainingSection = new content_block(NULL, 'div', array('class' => 'training-section'));
		
		// University section
		$universityDiv = new content_block(NULL, 'div');
		$universityDiv->push(new paragraph('Click on ProCAT University to access our full training library for every ProCAT topic.',['class' => 'text-white']));
		
		// University logo/image

		$universityLink = new content_block(NULL, 'a', array('href' => 'https://www.youtube.com/@MyProCAT', 'target' => '_blank', 'class' => 'anchor_button'));
		$universityLink->push(new image(
			'/templateV2/mainframe/img/universtiy_logo.png',
			array('style' => 'padding: 20px;width: 100%;height: 100%;object-fit: contain;')
		));
		$universityDiv->push($universityLink);
		$trainingSection->push($universityDiv);
		
		// Live training section
		// $liveTraining = new content_block(NULL, 'div', array('class' => 'live-training'));
		// $liveTraining->push(new paragraph('Prefer live training? Click below to sign up for a live training session.',['class' => 'text-white']));
		
		// $liveDiv = new content_block(NULL, 'div',[]);
		// $iconSpan = new content_block(NULL, 'span', array('class' => 'training-people'));
		// $iconSpan->push(new image(
		// 	'/img/round_classroom_icon.png',
		// 	array('style' => 'padding: 20px;width: 70%;object-fit: contain;')
		// ));
		// $liveDiv->push($iconSpan);
		// $liveTraining->push($liveDiv);
		// $liveLink = new content_block(NULL, 'a', array('href' => 'https://depodash.com/calendarofevents', 'target' => '_blank', 'class' => 'anchor_button'));
		// $liveLink->push(new content_block(NULL, 'div',['style' => 'padding-top: 20px']));
		// $liveLink->push($liveTraining);
		// $trainingSection->push($liveLink);
		$sidebar->push($trainingSection);
		
		// Contact info section
		$contactInfo = new content_block(NULL, 'div', array('class' => 'contact-info'));
		$contactInfo->push(new content_block($this->phone, 'p', array('class' => 'phone')));
		$contactInfo->push(new content_block($this->email, 'p', array('class' => 'email')));
		$sidebar->push($contactInfo);
		
		parent::__construct($sidebar, 'raw', $parameters);
	}
}
?>

