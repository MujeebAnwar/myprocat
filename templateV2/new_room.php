<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/start.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once (DOCUMENT_ROOT.'/template/form.php');

class new_room extends content_block
{
    public function __construct($UserAccount,$DB)
    {
        // Create the main container
        $container = new content_block(NULL, 'div', array('class' => 'room-form-section'));
        
        // Create form content wrapper
        $formContentWrapper = new content_block(NULL, 'div', array(
            'class' => 'form-content',
            'id' => 'formContent',
            'style' => 'margin: auto !important;'
        ));
        
        // Add heading
        $heading = new content_block('Add Room', 'h3', array('class' => 'form-heading'));
        $formContentWrapper->push($heading);
        
        // Create form grid
        $formGrid = new content_block(NULL, 'div', array('class' => 'form-grid'));
        
        // Create form box
        $formBox = new content_block(NULL, 'div', array('class' => 'form-box'));
        
        // Room field section
        $roomSection = new content_block(NULL, 'div', array());
        $roomHeading = new content_block('ROOM', 'h4', array());
        $roomSection->push($roomHeading);
        
        $roomFieldDiv = new content_block(NULL, 'div', array('class' => 'room-field'));
        $roomInput = new content_block(NULL, 'input', array(
            'required' => 'required',
            'type' => 'text',
            'placeholder' => 'Enter room name',
            'class' => 'room-input',
            'name' => 'room_name'
        ));
        $roomFieldDiv->push($roomInput);
        $roomSection->push($roomFieldDiv);
        $formBox->push($roomSection);
        
        // Description field section
        $descSection = new content_block(NULL, 'div', array('style' => 'margin-top: 20px;'));
        $descHeading = new content_block('DESCRIPTION', 'h4', array());
        $descSection->push($descHeading);
        
        $descFieldDiv = new content_block(NULL, 'div', array('class' => 'description-field'));
        $descTextarea = new content_block('', 'textarea', array(
            'required' => 'required',
            'placeholder' => 'Enter description',
            'class' => 'description-input',
            'rows' => '4',
            'name' => 'room_description'
        ));
        $descFieldDiv->push($descTextarea);
        $descSection->push($descFieldDiv);
        $formBox->push($descSection);
        
        // Submit button section
        $buttonSection = new content_block(NULL, 'div', array(
            'class' => 'flex-end',
            'style' => 'margin-top: 20px;'
        ));
        $submitButton = new submit('Add Room',array('class' => 'primary_button','name' => 'save_room'));
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
?>