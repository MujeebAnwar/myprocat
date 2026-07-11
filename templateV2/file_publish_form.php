<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/login.php');
require_once (DOCUMENT_ROOT.'/setup/force_authorized.php');
require_once (DOCUMENT_ROOT.'/setup/force_admin.php');
require_once (DOCUMENT_ROOT.'/lib/file_details.php');
require_once (DOCUMENT_ROOT.'/lib/Util.php');
require_once (DOCUMENT_ROOT.'/lib/messages.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once (DOCUMENT_ROOT.'/template/form.php');

class file_publish_form extends content_block
{
	public function __construct($id_file)
	{
		global $DB;
		global $UserAccount;
		$fp = new file_details($DB,$UserAccount,$id_file);
		$fp->fetch_results();

         // Create the main container
         $container = new content_block(NULL, 'div', array('class' => 'room-form-section'));
        
         // Create form content wrapper
         $formContentWrapper = new content_block(NULL, 'div', array(
             'class' => 'form-content',
             'id' => 'formContent',
             'style' => 'margin: auto !important;'
         ));
         
         // Add heading
         $heading = new content_block('Publish new version of \''.$fp->get_filename().'\'.', 'h3', array('class' => 'form-heading'));
         $formContentWrapper->push($heading);
         
         // Create form grid
         $formGrid = new content_block(NULL, 'div', array('class' => 'form-grid'));
         
        // Create form box
        $formBox = new content_block(NULL, 'div', array('class' => 'form-box'));
        
        // Product Name field section
        $productSection = new content_block(NULL, 'div', array());
        $productHeading = new content_block('PRODUCT NAME', 'h4', array());
        $productSection->push($productHeading);
        
        $productFieldDiv = new content_block(NULL, 'div', array('class' => 'room-field'));
        $productInput = new content_block(NULL, 'input', array(
            'required' => 'required',
            'type' => 'text',
            'placeholder' => 'Winner|WinnerVR',
            'class' => 'room-input',
            'name' => 'Product Name'
        ));
        $productFieldDiv->push($productInput);
        $productSection->push($productFieldDiv);
        $formBox->push($productSection);
        
        // Version field section
        $versionSection = new content_block(NULL, 'div', array('style' => 'margin-top: 20px;'));
        $versionHeading = new content_block('VERSION', 'h4', array());
        $versionSection->push($versionHeading);
        
        $versionFieldDiv = new content_block(NULL, 'div', array('class' => 'room-field'));
        $versionInput = new content_block(NULL, 'input', array(
            'required' => 'required',
            'type' => 'text',
            'placeholder' => 'Full version number e.x. 2015 15.1.14',
            'class' => 'room-input',
            'name' => 'Version'
        ));
        $versionFieldDiv->push($versionInput);
        $versionSection->push($versionFieldDiv);
        $formBox->push($versionSection);
        
        // Download Link field section
        $link = 'myprocat.com/actions/download.php?id='.$id_file;
        $downloadSection = new content_block(NULL, 'div', array('style' => 'margin-top: 20px;'));
        $downloadHeading = new content_block('DOWNLOAD LINK', 'h4', array());
        $downloadSection->push($downloadHeading);
        
        $downloadFieldDiv = new content_block(NULL, 'div', array('class' => 'room-field'));
        $downloadInput = new content_block(NULL, 'input', array(
            'required' => 'required',
            'type' => 'text',
            'placeholder' => 'Enter download link',
            'class' => 'room-input',
            'name' => 'Download Link',
            'value' => $link
        ));
        $downloadFieldDiv->push($downloadInput);
        $downloadSection->push($downloadFieldDiv);
        $formBox->push($downloadSection);
        
        // Release Notes Link field section
        $releaseSection = new content_block(NULL, 'div', array('style' => 'margin-top: 20px;'));
        $releaseHeading = new content_block('RELEASE NOTES LINK', 'h4', array());
        $releaseSection->push($releaseHeading);
        
        $releaseFieldDiv = new content_block(NULL, 'div', array('class' => 'room-field'));
        $releaseInput = new content_block(NULL, 'input', array(
            'type' => 'text',
            'placeholder' => 'Enter release notes link',
            'class' => 'room-input',
            'name' => 'Release Notes Link'
        ));
        $releaseFieldDiv->push($releaseInput);
        $releaseSection->push($releaseFieldDiv);
        $formBox->push($releaseSection);
        
        // Purchase Link field section
        $purchaseSection = new content_block(NULL, 'div', array('style' => 'margin-top: 20px;'));
        $purchaseHeading = new content_block('PURCHASE LINK', 'h4', array());
        $purchaseSection->push($purchaseHeading);
        
        $purchaseFieldDiv = new content_block(NULL, 'div', array('class' => 'room-field'));
        $purchaseInput = new content_block(NULL, 'input', array(
            'type' => 'text',
            'placeholder' => 'Enter purchase link',
            'class' => 'room-input',
            'name' => 'Purchase Link'
        ));
        $purchaseFieldDiv->push($purchaseInput);
        $purchaseSection->push($purchaseFieldDiv);
        $formBox->push($purchaseSection);
        
        // Daily invite limit field section
        $limitSection = new content_block(NULL, 'div', array('style' => 'margin-top: 20px;'));
        $limitHeading = new content_block('DAILY INVITE LIMIT', 'h4', array());
        $limitSection->push($limitHeading);
        
        $limitFieldDiv = new content_block(NULL, 'div', array('class' => 'room-field'));
        $limitInput = new content_block(NULL, 'input', array(
            'type' => 'text',
            'placeholder' => 'Enter daily invite limit',
            'class' => 'room-input',
            'name' => 'Daily invite limit',
            'value' => '0'
        ));
        $limitFieldDiv->push($limitInput);
        $limitSection->push($limitFieldDiv);
        $formBox->push($limitSection);
        
        // Hidden fields
        $hiddenAction = new content_block(NULL, 'input', array(
            'type' => 'hidden',
            'name' => 'action',
            'value' => 'publish'
        ));
        $formBox->push($hiddenAction);
        
        $hiddenIdFile = new content_block(NULL, 'input', array(
            'type' => 'hidden',
            'name' => 'id_file',
            'value' => $id_file
        ));
        $formBox->push($hiddenIdFile);

        $hiddenIdRoom = new content_block(NULL, 'input', array(
            'type' => 'hidden',
            'name' => 'id_room',
            'value' => array_keys($fp->fetch_results())[0]
        ));
        $formBox->push($hiddenIdFile);
        
        // Submit button section
        $buttonSection = new content_block(NULL, 'div', array(
            'class' => 'flex-end',
            'style' => 'margin-top: 20px;'
        ));
        $submitButton = new submit('Publish',array('class' => 'primary_button','name' => 'publish'));
        $buttonSection->push($submitButton);
        $formBox->push($buttonSection);
         
         // Assemble the structure
         $formGrid->push($formBox);
         $formContentWrapper->push($formGrid);
         
         // Create form and add wrapper
         $theForm = new form($formContentWrapper, array(
             'action' => '',
             'method' => 'POST'
         ));
         
         $container->push($theForm);
        
        // Set as content for this block
        
        parent::__construct($container, 'raw', array());

	}
}