<?php
require_once ('config.php');
require_once DOCUMENT_ROOT.'/template/Master.php';
require_once DOCUMENT_ROOT.'/template/form.php';
$coreStyle['controls.css'] = "";
define ('SPECIAL_NULL_BUTTON','__special_null_button_placeholder');
class renderButton extends content_block
{
	private $interiorHtml = [];
	private $buttonStyle = null;
	private $buttonValue = null;
	private $buttonAdditionalStyleValues = [];
	private $act = null;
	private $toolTip = null;
	private $attribute_renderer;
	private $type = 'j';
	private $buttonCallback = null;
	public function __construct($content = null)
	{
		$this->interiorHtml = array(
		'arrowButton' => ['style'=>'arrowButton','display'=>"&#x21EA;"],
		);
		$this->attribute_renderer = new html_attributes();
		parent::__construct($content,'raw');
	}
	public function registerButton($buttonName,$style,$display)
	{
		$this->interiorHtml[$buttonName] = ['style'=>$style,'display'=>$display];
	}
	public function format_button($style,$javascript_callback,$buttonValue=null,$additionalParams=null)
	{
		$this->buttonAdditionalStyleValues = [];
		$this->buttonCallback = $javascript_callback;
		$this->buttonStyle = $style;
		$this->buttonValue = $buttonValue;
		if(is_array($additionalParams))
		{
			if(array_key_exists('post',$additionalParams))
			{
				$this->type = 'p';
				if(!is_null($additionalParams['post']))
				{
					$this->act = $additionalParams['post'];
				}
				unset($additionalParams['post']);
			}
			if(array_key_exists('tooltip',$additionalParams))
			{
				$this->toolTip = $additionalParams['tooltip'];
				unset($additionalParams['tooltip']);
			}
			if(array_key_exists('formValue',$additionalParams))
			{
				$this->buttonValue = $additionalParams['formValue'];
				unset($additionalParams['formValue']);
			}
			if(count($additionalParams) > 0)
			{
				$this->buttonAdditionalStyleValues = array_merge($this->buttonAdditionalStyleValues,$additionalParams);
			}
		}
	}
	public function render_html($newValue = null)
	{
		if(!is_null($newValue))
		{
			$this->buttonValue = $newValue;
		} else {
			if(!is_null($this->content))
			{
				$this->buttonValue = $this->content;
			}
		}
		if($this->buttonValue !== SPECIAL_NULL_BUTTON)
		{
		print "<div ";
		
		$this->buttonAdditionalStyleValues['class'] = "button ".$this->interiorHtml[$this->buttonStyle]['style'];
		$this->buttonAdditionalStyleValues['button_value'] = $this->buttonValue;
		$this->buttonAdditionalStyleValues['button_display'] = $this->interiorHtml[$this->buttonStyle]['display'];
		$this->buttonAdditionalStyleValues['onclick'] ='('.$this->buttonCallback.')(this,this.getAttribute("button_value"))';
		$this->attribute_renderer->set_attributes($this->buttonAdditionalStyleValues);
		$this->attribute_renderer->render_attributes();
		print ">";
		if(!is_null($this->type == 'p'))
		{
			print "<form method='POST' ";
			if(!is_null($this->act))
			{
				print "action = '".$this->act."' ";
			}
			print ">";
			print "<input type=hidden name='".$this->buttonStyle."' value='".$this->buttonValue."' />";
			print "</form>";
		} else {
			print $this->buttonValue;
		}
		if(!is_null($this->toolTip))
		{
			print "<div class='tooltip'><p>".$this->toolTip."</div>";
		}
		print "</div>";
		} else{
			print "<div>&nbsp;</div>";
		}
	}
}
class jscriptButton extends renderButton
{
public function __construct($style,$javascript_callback = 'function(el,value){;}',$additionalParams=null,$buttonValue=null)
	{
		parent::__construct();
		$this->format_button($style,$javascript_callback,$buttonValue,$additionalParams);
	}
}
class postButton extends renderButton
{
	public function __construct($style,$buttonValue=null,$additionalParams=null)
	{
		parent::__construct();
		if(is_null($additionalParams))
		{
			$additionalParams = [];
		}
		if(!array_key_exists('post',$additionalParams))
		{
			$additionalParams['post'] = null;
		}	
		$this->format_button($style,'function(b,v){b.firstElementChild.submit();}',$buttonValue,$additionalParams);

	}
	
	
}
?>