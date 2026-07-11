<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once (DOCUMENT_ROOT.'/template/form.php');
class login_embed extends content_block
{
	public $_redirect;
	public $_postFormData;
	public function __construct($hidden_values = [], $redirect_after_success = false)
	{
		global $Session;
		global $UserAccount;
		$this->_redirect = $redirect_after_success;
		if($this->_redirect === 'self')
		{
			$this->_redirect = $_SERVER['REQUEST_URI'];
		}
		$this->_postFormData = $hidden_values;
		$content = new content_block(null,'div',['class'=>'login_form_container']);
		if(!$Session->valid)
		{
			if(is_array($_POST) && array_key_exists('Email',$_POST))
			{
	
				if($_POST['Email'] == "")
				{
					$content = new paragraph("You must type in your e-mail address to log in.",array('class' => 'errormessage'));
				} else {
					if(strlen($Session->error))
					{
						$content->push(new paragraph("Error: ".$Session->error,array('class' => 'errormessage','style' => 'font-size: 13pt')));
						$form = new form(NULL,array('method' => 'POST','id'=>'forgotpassword','action' => '/forgotpassword.php'));
						$form->push(new input(NULL,array('type'=>'hidden','name'=>'email','value'=>$_POST['Email'])));
						$form->push(new submit('Forgot your password? click here.',array('class' => 'forgotpasswordlink')));
						$content->push($form);
					} 
				}
			}	
			$form = new form(NULL,['id'=>'loginForm','method'=>'POST']);
			$br = new content_block(NULL,'br');
			$form->push(new paragraph("LOG IN:",['class'=> 'white-text']));
			// use unique id value to distinguish email fields on combined login/register page
			$form->push(new field("Email", ['arrange'=> 'vertical','type'=>'email','autocomplete'=>'username','id'=>'email-embed']));
			$form->push($br);
			// use unique id value to distinguish password fields on combined login/register page
			$form->push(new field('Password',['type'=>'password','autocomplete' => 'current-password','validate'=>'true','arrange'=> 'vertical','id'=>'password-embed']));
			$form->push($br);
			$form->push(new submit("Login",['class' => 'login','style'=>'float: right;']));
			foreach($this->_postFormData AS $k => $v)
			{
				$form->push(new input(NULL,['type' => 'hidden', 'name' => $k, 'value' => $v]));
			}
			$content->push($form);
		} else {
			$content = [new paragraph("Hello ".$UserAccount->user_details['first_name'].",",array('class' => 'importantmessage'))];
			if($this->_redirect) {
				array_push($content,new paragraph("You are now logged in.",['class' => 'message']));
				
				array_push($content,new content_block(DelayGoToPagePostScript($this->_redirect,$this->_postFormData),'script'));
			} else {
				array_push($content,new paragraph("You are now logged in.",['class' => 'message']));
			}
		}
		
		parent::__construct($content,'raw');
	}
}