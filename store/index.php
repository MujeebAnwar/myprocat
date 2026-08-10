<?php
require_once ('config.php');
require_once DOCUMENT_ROOT . '/setup/start.php';
require_once DOCUMENT_ROOT . '/template/Master.php';
require_once DOCUMENT_ROOT . '/template/form.php';
require_once DOCUMENT_ROOT . '/lib/account.php';
if (is_null($UserAccount) || !is_a($UserAccount, 'useraccount') || !$UserAccount->logged_in) {
	header('Location: /logout.php');
	exit;
}

$winnerLicense = $UserAccount->myprocat_license();
if ($winnerLicense === false || $winnerLicense === 'Lite') {
	header('Location: /logout.php');
	exit;
}

$set_title = 'My ProCat Subscription - ProCAT Resource Center';
$sidebar_title = 'My ProCat Subscription';
$form_message = '';
$form_message_class = 'myaccount-form-notice';
$contactSalesUrl = 'https://www.depodash.com/contact';

$page_banner = new content_block(NULL, 'div', array('class' => 'banner'));
$page_banner->push(new content_block('Store', 'h1'));

$winnerLicense = $UserAccount->myprocat_license();
if ($winnerLicense !== false && $winnerLicense !== 'Lite') {
	$selectedLicenseType = $UserAccount->myprocat_subscription() ? 'subscription' : 'perpetual';
} else {
	$selectedLicenseType = 'None';
}

$licensesByType = array(
	'perpetual' => null,
	'subscription' => null,
);
$licenseRows = array('id', 'per_hour_amount', 'minimum_hours', 'type');
$DB->sql(
	'SELECT id, per_hour_amount, minimum_hours, type FROM myprocat_subscription_license_table ORDER BY id',
	array(),
	$licenseRows
);

foreach ($licenseRows as $row) {
	if (!isset($row['type']) || $row['type'] === 'type') {
		continue;
	}
	if (array_key_exists($row['type'], $licensesByType)) {
		$licensesByType[$row['type']] = $row;
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$licenseType = isset($_POST['license_type']) ? trim($_POST['license_type']) : '';

	if (!in_array($licenseType, array('perpetual', 'subscription'), true)) {
		$form_message = 'Please select a valid license type.';
		$form_message_class = 'myaccount-form-alert';
	} else if (empty($licensesByType[$licenseType])) {
		$form_message = 'The selected license type is not currently available.';
		$form_message_class = 'myaccount-form-alert';
	} else {
		header('Location: /store/buy.php?type=' . urlencode($licenseType));
		exit;
	}
}

$licenseChoices = array(
	'perpetual' => array(
		'title' => 'Perpetual License',
		'description' => 'One-time license with platform access based on your purchased hours.',
		'features' => array(
			'One-time license purchase',
			'Platform access based on purchased hours',
			'No recurring subscription required',
		),
		'is_accent' => false,
	),
	'subscription' => array(
		'title' => 'Subscription License',
		'description' => 'Recurring subscription license with ongoing platform access.',
		'features' => array(
			'Recurring subscription billing',
			'Ongoing platform access',
			'Flexible renewal options',
		),
		'is_accent' => true,
	),
);

$set_body = new content_block(NULL, 'div', array('class' => 'myprocat-subscription', 'style' => 'width: 100%;'));

$buyContainer = new content_block(NULL, 'div', array('class' => 'buy-container', 'style' => 'max-width: 1100px; margin: 0 auto; padding: 0 20px 40px;'));

$planHeader = new content_block(NULL, 'div', array('class' => 'plan-header', 'style' => 'text-align: center; margin-bottom: 24px;'));
$planHeader->push(new content_block('Select License Type', 'h1', array('class' => 'plan-name', 'style' => 'font-size: 28px; color: #27475f; margin: 0 0 8px 0;')));
$planHeader->push(new paragraph(
	'Choose the license type that best fits your MyProCAT account, or contact sales for a custom solution.',
	array('class' => 'plan-description', 'style' => 'color: #666; max-width: 640px; margin: 0 auto;')
));
$buyContainer->push($planHeader);

if ($form_message !== '') {
	$buyContainer->push(new content_block($form_message, 'p', array(
		'class' => $form_message_class,
		'style' => 'text-align: center; margin-bottom: 20px;',
	)));
}

$buyContainer->push(new content_block(NULL, 'div', array('class' => 'separator-line', 'style' => 'height: 1px; background: #e0e0e0; margin: 0 0 32px 0;')));

$cardsGrid = new content_block(NULL, 'div', array(
	'class' => 'pricing-cards myprocat-license-cards',
	'style' => 'display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; margin-bottom: 40px;',
));

foreach ($licenseChoices as $licenseKey => $choice) {
	$licenseData = $licensesByType[$licenseKey];
	$isSelected = ($selectedLicenseType === $licenseKey);
	$isDisabled = empty($licenseData);

	$cardStyle = 'background: white; border-radius: 16px; padding: 32px 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); position: relative; transition: all 0.3s ease; display: flex; flex-direction: column;';
	if ($choice['is_accent']) {
		$cardStyle .= ' border: 2px solid #ff6600;';
	} else {
		$cardStyle .= ' border: 1px solid #e0e0e0;';
	}
	if ($isSelected) {
		$cardStyle .= ' box-shadow: 0 8px 30px rgba(255, 102, 0, 0.18);';
	}

	$card = new content_block(NULL, 'div', array(
		'class' => 'upgrade-plan-card myprocat-license-card' . ($isSelected ? ' myprocat-license-card--selected' : ''),
		'style' => $cardStyle,
	));

	if ($isSelected) {
		$selectedBadge = new content_block('Current Selection', 'div', array(
			'style' => 'position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: #27475f; color: white; padding: 4px 16px; border-radius: 20px; font-size: 12px; font-weight: 600; white-space: nowrap;',
		));
		$card->push($selectedBadge);
	} else if ($choice['is_accent'] && !$isDisabled) {
		$recommendedBadge = new content_block('Recommended', 'div', array(
			'style' => 'position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: #ff6600; color: white; padding: 4px 16px; border-radius: 20px; font-size: 12px; font-weight: 600; white-space: nowrap;',
		));
		$card->push($recommendedBadge);
	}

	$card->push(new content_block($choice['title'], 'h3', array('style' => 'margin: 10px 0 8px 0; font-size: 22px; color: #27475f; text-align: center;')));
	$card->push(new paragraph($choice['description'], array('style' => 'text-align: center; color: #666; font-size: 13px; margin-bottom: 16px; min-height: 40px;')));

	$priceDisplay = new content_block(NULL, 'div', array('style' => 'text-align: center; margin-bottom: 20px;'));
	if (!empty($licenseData)) {
		$priceWrapper = new content_block(NULL, 'div', array('style' => 'display: flex; align-items: center; gap: 8px; justify-content: center; flex-wrap: wrap;'));
		$priceWrapper->push(new content_block('$' . number_format((float)$licenseData['per_hour_amount'], 2), 'span', array('style' => 'font-size: 24px; font-weight: 700; color: #27475f;')));
		$priceDisplay->push($priceWrapper);
		$priceDisplay->push(new content_block('per hour', 'div', array('style' => 'font-size: 13px; color: #999;')));
		$priceDisplay->push(new content_block('Minimum ' . (int)$licenseData['minimum_hours'] . ' hours', 'div', array('style' => 'font-size: 12px; color: #999; margin-top: 6px;')));
	} else {
		$priceDisplay->push(new content_block('Unavailable', 'div', array('style' => 'font-size: 20px; font-weight: 600; color: #999;')));
		$priceDisplay->push(new content_block('Contact support for details', 'div', array('style' => 'font-size: 13px; color: #999;')));
	}
	$card->push($priceDisplay);

	$featuresList = new content_block(NULL, 'ul', array('style' => 'list-style: none; padding: 0; margin: 0 0 24px 0; flex: 1;'));
	foreach ($choice['features'] as $feature) {
		$featuresList->push(new content_block('✓ ' . $feature, 'li', array('style' => 'padding: 8px 0; font-size: 13px; color: #555; border-bottom: 1px solid #f0f0f0;')));
	}
	$card->push($featuresList);

	$buttonContainer = new content_block(NULL, 'div', array('style' => 'text-align: center; margin-top: auto;'));
	if (!$isDisabled) {
		$selectForm = new form(NULL, array('method' => 'post', 'action' => '', 'style' => 'margin: 0;'));
		$buttonClass = 'primary_button text-white';
		$buttonLabel = $isSelected ? 'Continue' : 'Select Plan';
		$buttonAttrs = array(
			'type' => 'submit',
			'name' => 'license_type',
			'value' => $licenseKey,
			'class' => $buttonClass,
			'style' => 'display: inline-block; padding: 12px 24px; width: 100%; text-align: center; box-sizing: border-box; border: none; cursor: pointer; font-family: inherit; font-size: 16px; font-weight: 600; border-radius: 5px;',
		);
		if ($isSelected) {
			$buttonAttrs['style'] .= ' opacity: 0.85;';
		}
		$selectForm->push(new content_block($buttonLabel, 'button', $buttonAttrs));
		$buttonContainer->push($selectForm);
	} else {
		$buttonContainer->push(new content_block('Unavailable', 'button', array(
			'type' => 'button',
			'disabled' => 'disabled',
			'class' => 'secondary_button',
			'style' => 'display: inline-block; padding: 12px 24px; width: 100%; text-align: center; box-sizing: border-box; opacity: 0.6; cursor: not-allowed;',
		)));
	}
	$card->push($buttonContainer);
	$cardsGrid->push($card);
}

// Contact Sales card (replaces None)
$contactCardStyle = 'background: white; border-radius: 16px; padding: 32px 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); position: relative; transition: all 0.3s ease; border: 1px solid #e0e0e0; display: flex; flex-direction: column;';
$contactCard = new content_block(NULL, 'div', array(
	'class' => 'upgrade-plan-card myprocat-license-card myprocat-license-card--contact',
	'style' => $contactCardStyle,
));

$contactCard->push(new content_block('Contact Sales', 'h3', array('style' => 'margin: 10px 0 8px 0; font-size: 22px; color: #27475f; text-align: center;')));
$contactCard->push(new paragraph(
	'Need a custom license arrangement or have questions about MyProCAT pricing?',
	array('style' => 'text-align: center; color: #666; font-size: 13px; margin-bottom: 16px; min-height: 40px;')
));

$contactPriceDisplay = new content_block(NULL, 'div', array('style' => 'text-align: center; margin-bottom: 20px;'));
$contactPriceDisplay->push(new content_block('Custom Pricing', 'div', array('style' => 'font-size: 20px; font-weight: 600; color: #27475f;')));
$contactPriceDisplay->push(new content_block('Contact sales for details', 'div', array('style' => 'font-size: 13px; color: #999;')));
$contactCard->push($contactPriceDisplay);

$contactFeatures = new content_block(NULL, 'ul', array('style' => 'list-style: none; padding: 0; margin: 0 0 24px 0; flex: 1;'));
foreach (array(
	'Custom license options',
	'Volume and enterprise pricing',
	'Dedicated sales support',
) as $feature) {
	$contactFeatures->push(new content_block('✓ ' . $feature, 'li', array('style' => 'padding: 8px 0; font-size: 13px; color: #555; border-bottom: 1px solid #f0f0f0;')));
}
$contactCard->push($contactFeatures);

$contactButtonContainer = new content_block(NULL, 'div', array('style' => 'text-align: center; margin-top: auto;'));
$contactButtonContainer->push(new anchor('Contact Sales', array(
	'href' => $contactSalesUrl,
	'target' => '_blank',
	'class' => 'secondary_button',
	'style' => 'display: inline-block; padding: 12px 24px; width: 100%; text-align: center; box-sizing: border-box;',
)));
$contactCard->push($contactButtonContainer);
$cardsGrid->push($contactCard);

$buyContainer->push($cardsGrid);
$set_body->push($buyContainer);

$licenseStyles = new content_block('
.myprocat-license-card:hover {
	transform: translateY(-5px);
	box-shadow: 0 8px 30px rgba(0,0,0,0.12);
}

.myprocat-license-card--selected {
	border-color: #27475f !important;
}

@media (max-width: 768px) {
	.myprocat-license-cards {
		grid-template-columns: 1fr !important;
	}
}
', 'style');
$set_body->push($licenseStyles);

$breadcrumb_items = array(
	array('text' => 'Home', 'url' => '/resources.php'),
	array('text' => 'Store', 'url' => '/store/'),
);

$sidebar_title = 'MyProCAT';
$sidebar_logo = '/store/img/buy.png';
$sidebar_logo_text = 'MyProCAT Buy Platform Time';
require_once DOCUMENT_ROOT . '/templateV2/mainframe/mainframe.php';
?>
