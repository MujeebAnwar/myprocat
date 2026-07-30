<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');

class right_sidebar extends content_block
{
	private $user;
	private $DB;
	
	public function __construct($user = NULL, $DB = NULL, $parameters = array())
	{
		$this->user = $user;
		$this->DB = $DB;
		// Create right sidebar container
		$sidebar = new content_block(NULL, 'div', array('class' => 'right-sidebar'));
		
		// Training section
		$trainingSection = new content_block(NULL, 'div', array('class' => 'training-section'));
		
		// University section
		$universityDiv = new content_block(NULL, 'div');
		$universityDiv->push(new paragraph('Click on DepoDash University to access our full training library for every DepoDash topic.'));
		
		// University logo/image
		$universityDiv->push(new image(
			'/img/depo-dash-univ-NO-FLAME.png',
			array('style' => 'padding: 20px;width: 100%;height: 100%;object-fit: contain;')
		));
		$trainingSection->push($universityDiv);
		
		// Live training section
		$liveTraining = new content_block(NULL, 'div', array('class' => 'live-training'));
		$liveTraining->push(new paragraph('Prefer live training? Click below to sign up for a live training session.'));
		
		$liveDiv = new content_block(NULL, 'div');
		$iconSpan = new content_block(NULL, 'span', array('class' => 'training-people'));
		$iconSpan->push(new content_block(
			'<svg style="color:#9a9a9a;" xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 18h-.75a3 3 0 0 1-3-3v-3a3 3 0 0 1 3-3h.75a.75.75 0 0 1 .75.75v7.5a.75.75 0 0 1-.75.75m15.75 0h-.75a.75.75 0 0 1-.75-.75v-7.5A.75.75 0 0 1 19.5 9h.75a3 3 0 0 1 3 3v3a3 3 0 0 1-3 3M3.75 9a8.25 8.25 0 1 1 16.5 0M15 21.75h2.25a3 3 0 0 0 3-3V18"/><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 23.25H12a1.5 1.5 0 1 1 0-3h1.5a1.5 1.5 0 1 1 0 3M9 8.25a3 3 0 0 1 5.753-1.192c.218.505.294 1.06.218 1.605A3 3 0 0 1 13 11.079a1.5 1.5 0 0 0-1 1.415v.256"/><path d="M12 16.5a.375.375 0 0 1 0-.75m0 .75a.375.375 0 0 0 0-.75"/></g></svg>',
			'raw'
		));
		$liveDiv->push($iconSpan);
		$liveTraining->push($liveDiv);
		
		$trainingSection->push($liveTraining);
		$sidebar->push($trainingSection);
		
		parent::__construct($sidebar, 'raw', $parameters);
	}
}
?>

