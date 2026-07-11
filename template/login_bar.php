<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once (DOCUMENT_ROOT.'/template/form.php');
class login_bar extends content_block
{
	public function __construct($ua = NULL)
	{
		$content = new row(NULL,array('class'=>'login_bar'));
		$un = NULL;
		$form = NULL;
		$classleft = "";
		$classright = "";
		if(!is_null($ua) && is_a($ua,'useraccount') && $ua->logged_in)
		{
			$un = 'Welcome '.$ua->user_details['first_name'].', you are logged in'.(($ua->user_details['is_admin'])?' as an administrator':'').'.';
			$form = new form(NULL,array('class' => 'login_form','method' => 'POST','action' => '/logout.php'));
			$form->push(new submit("Log Out",array('class' => 'login')));
			$classleft = 'logoutbar_left';
			$classright = 'logoutbar_right';
		
		} else {
			$un = 'You are not logged in.';
			$form = new form(NULL,array('method' => 'POST'));
			$form->push(new field("Email",array('class' => 'login','type'=>'email','autocomplete' => 'username')));
			$form->push(new field("Password",array('class' => 'login','type' => 'password','autocomplete' => 'current-password')));
			$form->push(new submit("Login",array('class' => 'login')));
			$classleft = 'loginbar_left';
			$classright = 'loginbar_right';
		}
		$content->push(new section(new paragraph($un,array('class' => 'welcome')),array('class' => $classleft)));
		$content->push(new section($form,array('class' => $classright)));

		parent::__construct($content,'raw');
	}
}
?>