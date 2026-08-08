<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once DOCUMENT_ROOT . '/lib/account.php';
class navbar extends content_block
{
	private $user;
	private $DB;
	
	public function __construct($user = NULL, $DB = NULL, $parameters = array())
	{
		$this->user = $user;
		$this->DB = $DB;
		
		// Create header container
		$header = new content_block(NULL, 'div', array('class' => 'header'));
		
		// Logo section
		$logo = new content_block(NULL, 'div', array('class' => 'logo'));
		$logoImg = new image('/templateV2/mainframe/img/logo.png', array('style' => 'height: 50px;'));
		$logo->push($logoImg);
		$header->push($logo);
		
		// Navigation section
		$nav = new content_block(NULL, 'nav', array('class' => 'top-nav'));
		
		// Mobile menu toggle (shown via CSS on small screens)
		$menuBtn = new content_block(
			array(
				new content_block(NULL, 'span', array('class' => 'hamburger-line')),
				new content_block(NULL, 'span', array('class' => 'hamburger-line')),
				new content_block(NULL, 'span', array('class' => 'hamburger-line'))
			),
			'button',
			array(
				'class' => 'mobile-menu-toggle',
				'type' => 'button',
				'id' => 'mobileMenuToggle',
				'aria-label' => 'Open menu',
				'aria-controls' => 'mainNav',
				'aria-expanded' => 'false',
				'onclick' => 'toggleMobileMenu(event)'
			)
		);
		$nav->push($menuBtn);
		
		$navList = new content_block(NULL, 'ul', array('class' => 'navigation', 'id' => 'mainNav'));
		
		// Navigation items - dynamic links
		$isLoggedIn = $this->user && !is_null($this->user) && is_a($this->user, 'useraccount') && $this->user->logged_in;

		if ($isLoggedIn) {
			$winnerLicense = $this->user->myprocat_license();

			if ($winnerLicense === true || $winnerLicense !== 'Lite') {
				$navList->push(new content_block(
					new anchor('Store', array('href' => '/store/buy.php')),
					'li'
				));
			}

			$navList->push(new content_block(
				new anchor('Renew Support', array('href' => '/renew_support/')),
				'li'
			));

			$navList->push(new content_block(
				new anchor('Support', array('href' => '/contact.php')),
				'li'
			));

			$navList->push(new content_block(
				new anchor('Lexi', array('href' => 'https://procat.com/etc/lexi','target' => '_blank')),
				'li'
			));

			$navList->push(new content_block(
				new anchor('Invoices', array('href' => '/invoices/')),
				'li'
			));

			$navList->push(new content_block(
				new anchor('RESOURCES', array('href' => '/resources.php')),
				'li'
			));
		}

		// User info - dynamic based on login state
		$userLi = new content_block(NULL, 'li', array('class' => 'user-ctn', 'onclick' => 'toggleUserDropdown()'));
		$userLi->push(new image('https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRFi74mB1mAjXESb3Sb3ikYHfshZ4D4ZkMyWQ&s'));

		if ($isLoggedIn)
		{
			$userName = isset($this->user->user_details['full_name']) ? $this->user->user_details['full_name'] : 'User';
			$userLi->push(new content_block($userName, 'span', array('class' => 'user-info')));

			// Create dropdown menu
			$dropdown = new content_block(NULL, 'div', array('class' => 'user-dropdown', 'id' => 'userDropdown'));
			$logoutLink = new anchor('Logout', array('href' => '/logout.php', 'class' => 'dropdown-item'));
			$dropdown->push($logoutLink);
			$userLi->push($dropdown);
		}
		
		$navList->push($userLi);
		
		$nav->push($navList);
		$header->push($nav);
		
		// Add JavaScript for dropdown toggle
		$script = new content_block("
			function toggleMobileMenu(e) {
				if (e) {
					e.stopPropagation();
					e.preventDefault();
				}
				var nav = document.getElementById('mainNav');
				var btn = document.getElementById('mobileMenuToggle');
				if (!nav || !btn) return;
				
				nav.classList.toggle('open');
				btn.setAttribute('aria-expanded', nav.classList.contains('open') ? 'true' : 'false');
			}

			function toggleUserDropdown() {
				var dropdown = document.getElementById('userDropdown');
				if (dropdown) {
					dropdown.classList.toggle('show');
				}
			}
			
			// Close dropdown when clicking outside
			window.addEventListener('click', function(event) {
				// Close mobile menu when clicking outside
				var nav = document.getElementById('mainNav');
				var btn = document.getElementById('mobileMenuToggle');
				if (nav && btn && !event.target.closest('#mobileMenuToggle') && !event.target.closest('#mainNav')) {
					nav.classList.remove('open');
					btn.setAttribute('aria-expanded', 'false');
				}

				if (!event.target.closest('.user-ctn')) {
					var dropdown = document.getElementById('userDropdown');
					if (dropdown) {
						dropdown.classList.remove('show');
					}
				}
			});

			// Ensure menu closes when resizing back to desktop
			window.addEventListener('resize', function() {
				var nav = document.getElementById('mainNav');
				var btn = document.getElementById('mobileMenuToggle');
				if (!nav || !btn) return;
				if (window.innerWidth > 1400) {
					nav.classList.remove('open');
					btn.setAttribute('aria-expanded', 'false');
				}
			});
		", 'script', array('type' => 'text/javascript'));
		$header->push($script);
		
		parent::__construct($header, 'raw', $parameters);
	}
}
?>

