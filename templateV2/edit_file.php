<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/start.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once (DOCUMENT_ROOT.'/template/form.php');

class edit_file extends content_block
{
    public function __construct($UserAccount, $DB, $fileData = array())
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
        $heading = new content_block('Edit File', 'h3', array('class' => 'form-heading'));
        $formContentWrapper->push($heading);
        
        // Create form grid
        $formGrid = new content_block(NULL, 'div', array('class' => 'form-grid'));
        
        // Create form box
        $formBox = new content_block(NULL, 'div', array('class' => 'form-box'));
        
        // File name field section
        $fileSection = new content_block(NULL, 'div', array());
        $fileHeading = new content_block('FILE NAME', 'h4', array());
        $fileSection->push($fileHeading);
        
        $fileFieldDiv = new content_block(NULL, 'div', array('class' => 'room-field'));
        $fileInput = new content_block(NULL, 'input', array(
            'required' => 'required',
            'type' => 'text',
            'placeholder' => 'Enter file name',
            'class' => 'room-input',
            'name' => 'title',
            'value' => isset($fileData['title']) ? htmlspecialchars($fileData['title']) : ''
        ));
        $fileFieldDiv->push($fileInput);
        $fileSection->push($fileFieldDiv);
        $formBox->push($fileSection);
        
        // Description field section
        $descSection = new content_block(NULL, 'div', array('style' => 'margin-top: 20px;'));
        $descHeading = new content_block('DESCRIPTION', 'h4', array());
        $descSection->push($descHeading);
        
        $descFieldDiv = new content_block(NULL, 'div', array('class' => 'description-field'));
        $descContent = isset($fileData['description']) ? htmlspecialchars($fileData['description']) : '';
        $descTextarea = new content_block($descContent, 'textarea', array(
            'required' => 'required',
            'placeholder' => 'Enter description',
            'class' => 'description-input',
            'rows' => '4',
            'name' => 'description'
        ));
        $descFieldDiv->push($descTextarea);
        $descSection->push($descFieldDiv);
        $formBox->push($descSection);
        
        // Hidden field for file ID
        if(isset($fileData['id_file'])) {
            $hiddenIdField = new content_block(NULL, 'input', array(
                'type' => 'hidden',
                'name' => 'id_file',
                'value' => $fileData['id_file']
            ));
            $formBox->push($hiddenIdField);
        }
        
        // Hidden field for room ID (to redirect back after update)
        if(isset($fileData['id_room'])) {
            $hiddenRoomField = new content_block(NULL, 'input', array(
                'type' => 'hidden',
                'name' => 'id_room',
                'value' => $fileData['id_room']
            ));
            $formBox->push($hiddenRoomField);
        }
        
        // Submit button section
        $buttonSection = new content_block(NULL, 'div', array(
            'class' => 'flex-end',
            'style' => 'margin-top: 20px;'
        ));
        $submitButton = new submit('Update File',array('class' => 'primary_button','name' => 'update_file'));
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

