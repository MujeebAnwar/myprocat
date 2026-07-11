<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once (DOCUMENT_ROOT.'/lib/password_requirements_authenticator.php');
class input extends content_block
{
	public function __construct($text = NULL,$parameters = array())
	{

		parent::__construct($text,'input',$parameters);
	}
	
}
class submit extends content_block
{
	public function __construct($text = NULL,$parameters = array())
	{
		$parameters['type'] = 'submit';
		if(!is_null($text))
		{
			$parameters['value'] = $text;
		}
		parent::__construct(NULL,'input',$parameters);

	}

}
class image_submitbutton extends content_block
{
	public function __construct($imgsrc,$width,$height)
	{
		$content = new section(NULL,array('style' => 'background:url('.$imgsrc.');border:none;width:'.$width.'px;height:'.$height.'px'));
		$content->push(new content_block(NULL,'input',array('type' => 'submit','style' =>'border:none;background:transparent;color:transparent;width:'.$width.'px;height:'.$height.'px')));
		parent::__construct($content,'raw',array());
	}
}
class textarea extends content_block
{
	public function __construct($text = NULL,$parameters = array())
	{
		parent::__construct($content,'textarea',$parameters);
	}
}
class optionmenu extends content_block
{
	public function __construct($selected = NULL,$options = array(),$parameters = array())
	{
		$content = new content_block(NULL,'select',$parameters);
		$readonly = false;
		if(array_key_exists('readonly', $parameters))
		{
			$readonly = true;
			unset($parameters['readonly']);

		}
		if(is_null($selected))
		{
			$o = new content_block("-- Select --",'option',array('style' => 'display:none','value' => '', 'disabled' => 'disabled', 'selected' => 'selected'));
			$content->push($o);
		}
		foreach($options AS $key => $value)
		{
			$params = array('value' => $key);
			if(!is_null($selected) && $selected === $key)
			{
				$params['selected'] = 'selected';
			} else if($readonly) {
				$params['disabled'] = 'disabled';
				$params['style'] = 'display:none';
 			}
			$o = new content_block($value,'option',$params);
			$content->push($o);
			
		}
		parent::__construct($content,'optionmenu',$parameters);
	}
}
class field extends content_block
{
	private $arrange = 'horizontal';
	public function __construct($text = NULL,$parameters = array())
	{
		$pra = new password_requirements_authenticator();

		$ff = 'form_field';
		$fl = 'field_label';
		$fi = 'field_input';
		$inputIdentifier = preg_replace('/ /','_',$text);
		$validate = false;
		$compare = NULL;
		if(array_key_exists('validate',$parameters))
		{
			$validate = true;
			unset($parameters['validate']);
		}
		if(array_key_exists('compare',$parameters))
		{
			$compare = $parameters['compare'];
			unset($parameters['compare']);
		}
		if(array_key_exists('class',$parameters))
		{
			$ff = $parameters['class']."_form_field";
			$fl = $parameters['class']."_field_label";
			$fi = $parameters['class']."_field_input";
		}
		if(array_key_exists('arrange',$parameters))
		{
			$this->arrange = $parameters['arrange'];
			unset($parameters['arrange']);
		}
		$this->parameters = $parameters;
		if (!array_key_exists('id', $parameters))
		{
			$parameters['id'] = $inputIdentifier;
		}
		if($this->arrange === 'vertical')
		{

			$content = new row(NULL,array('class'=>$ff));
		} else {
			$content = new section(NULL,array('class'=>$ff));
		}
		$labstyle = array('class'=>$fl);
		if(array_key_exists('style',$parameters))
		{
			$labstyle = array_merge($labstyle,array("style"=>$parameters["style"]));
		}
		$labstyle = array_merge($labstyle,array('for'=>$parameters['id']));
		$flab = new section(new label($text.":", $labstyle), array('class'=>$fl));
		$parameters['class'] = $fi;
		if(!array_key_exists('type',$parameters))
		{
			$parameters['type'] = 'text';
		}
		$parameters['name'] = $inputIdentifier;
		if(is_array($_POST) && array_key_exists($inputIdentifier,$_POST) && $parameters['type'] !== "password" && strlen($_POST[$inputIdentifier])>0)
		{
			if(!array_key_exists('value',$parameters))
			{
				$parameters['value'] = $_POST[$inputIdentifier];
			}
		}
		$inptFormField = new input(NULL, $parameters);
		$finpt = new section($inptFormField,array('class'=>$fi));
		$content->push($flab);
		$content->push($finpt);
		if($validate)
		{
			if($parameters['type'] === 'password')
			{
				$content->push(new paragraph("",
						array('class'=>$fl,
						'id' => $inputIdentifier."_validatorDisplay",
						'style '=> 'position: absolute;left:100%;text-align:left;width:400px;color:red;margin-left:22px'
						)
					)
				);
				if(is_null($compare))
				{
					$inptFormField->parameters['onkeyup'] = "password_validator(this,".$inputIdentifier."_validatorDisplay)";
					
					
					$content->push(new content_block(
						$pra->javascript_validator(),
						'script',
						array('type'=>'text/javascript')
						)
					);
				} else {
					$inptFormField->parameters['onkeyup'] = "compare_validator(this,".$compare->parameters['name'].",".$inputIdentifier."_validatorDisplay)";
					
					$content->push(new content_block(
						$pra->javascript_compare_validator(),
						'script',
						array('type'=>'text/javascript')
						)
					);

				}
			}
		}
		parent::__construct($content,'raw',$parameters);
	}
}
class textfield extends content_block
{
	public function __construct($text = NULL,$parameters = array())
	{
		$ff = 'form_field';
		$fl = 'field_label';
		$fi = 'field_input';
		
		if(array_key_exists('class',$parameters))
		{
			$ff = $parameters['class']."_form_field";
			$fl = $parameters['class']."_field_label";
			$fi = $parameters['class']."_field_input";
		}

		$inputIdentifier = preg_replace('/ /','_',$text);
		$this->parameters = $parameters;
		$parameters['class'] = $fi;
		$blob = "";
		if(array_key_exists('value',$parameters))
		{
			$blob = $parameters['value'];
			unset($parameters['value']);
		} else {
			if(is_array($_POST) && array_key_exists($inputIdentifier,$_POST) && strlen($_POST[$inputIdentifier])>0)
			{
				$blob = $_POST[$inputIdentifier];
			}
		}
		$parameters['name'] = $inputIdentifier;
		
		if($parameters['arrange'] === 'vertical')
		{
			$this->content = new row(NULL,array('class'=>$ff));
			$flab = new row(new paragraph($text.":",array('class'=>$fl)),array('class'=>$fl));
			$finpt = new row(new content_block($blob,'textarea',$parameters),array('class'=>$fi));
		
		} else {
			$this->content = new section(NULL,array('class'=>$ff));
			$flab = new section(new paragraph($text.":",array('class'=>$fl)),array('class'=>$fl));
			$finpt = new section(new content_block($blob,'textarea',$parameters),array('class'=>$fi));
		}
		
		$this->content->push($flab);
		$this->content->push($finpt);
	}	
}
class form extends content_block
{
	private $form_action = NULL;
	public function __construct($content = NULL,$parameters = array())
	{
		if(array_key_exists('form_action',$parameters))
		{
			$this->form_action = $parameters['form_action'];
			unset($parameters['form_action']);
		}
		parent::__construct($content,'form',$parameters);
	}
	public function render()
	{
		if(!is_null($this->form_action))
		{
			if($this->form_action->status !== FORM_STATUS_UNCHECKED)
			{
				if($this->form_action->status === FORM_STATUS_ERROR)
				{
					$newcontent = new paragraph($this->form_action->error,array('class' => 'form_error'));
					if(is_null($this->content))
					{
						$this->content = $newcontent;
					} else if(is_array($this->content))
					{
						array_unshift($this->content,$newcontent);
					} else {
						$content = $this->content;
						$this->content = array($newcontent,$content);
					}
				} else if($this->form_action->status === FORM_STATUS_SUBMIT_SUCCESS) {
					$this->content = new paragraph("Success!",array('class' => 'form_success'));
				}
			}
		}
		parent::render();
	}
}
?>