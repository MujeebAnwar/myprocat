<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/template/html_attributes.php');
require_once (DOCUMENT_ROOT.'/template/table_renderer.php');
require_once (DOCUMENT_ROOT.'/template/data_block.php');

class content_block
{
	protected $content;
	// "raw" types of content just mean the contents are themselves things that need rendered
	// So collections of things would be raw, also direct html, or text intended to be rendered
	// directly through to the page is raw.
	// This being the default lets you create a collection object which contains, but is also identified as
	// a different subobject (So you can create a new content block type which contains a row as it's only content
	// but when rendered, only the row itself is rendered
	private $type = 'raw'; 
	private $atr = NULL;
	private $paramters = array();
	private $indent = 0;

	public function __construct($content = NULL,$type = NULL,$parameters = array())
	{
		$this->atr = new html_attributes();
		//$this->tbl = new table_renderer();
		//$this->dat = new data_block();
		
		$this->type = $type;
		$this->content = $content;
		$this->parameters = $parameters;
		if(is_array($this->content) && array_key_exists('parameters',$this->content))
		{
			$this->parameters = $this->content['parameters'];
			unset($this->content['paramenters']);
		}
		if(is_array($this->content) && array_key_exists('type',$this->content))
		{
			$this->type = $this->content['type'];
			unset($this->content['type']);
		}

		
	}
	private function add_indent($prevIndent = 0)
	{
		$this->indent = $prevIndent+1;
	}
	private function print_indent()
	{
		//print "\n";
		for($i=0;$i<$this->indent;$i++)
		{
			//print "  ";
		}
	}
	private function render_subelements($elements = NULL,$type = NULL)
	{
		//var_dump($elements);
		$is_collection = (is_array($elements) && !array_key_exists('contents',$elements));
		if($is_collection)
		{
			foreach($elements AS $key => $subelement)
			{
				if($key === 'contents' || $key === 'type' || $key === 'parameters')
				{
					continue;
				}
				$cb = new content_block($subelement);
				$cb->add_indent($this->indent);
				$cb->render();
			}
		} else if(is_a($elements,'content_block'))
		{
			$elements->add_indent($this->indent);
			$elements->render();
		} else {
			$type = 'raw';
			$params = $this->parameters;
			$content = $elements;
			if(is_array($elements) && array_key_exists('contents',$elements))
			{
				$content = $elements['content'];
				$type = $elements['type'];
				$params = $elements['parameters'];
			}
			$cb = new content_block($content,$type,$params);
			$cb->add_indent($this->indent);
			$cb->render();
			unset($cb);
		}

	}
	public function add_style($styleattributes)
	{
		if(is_array($this->parameters) && array_key_exists('style',$this->parameters))
		{
			$this->parameters['style'] .= ';'.$styleattributes;
		} else {
			$this->parameters['style'] = $styleattributes;
		}

	}
	public function render()
	{
		if(is_object($this->content) || is_array($this->content))
		{
			$this->print_indent();
		}
		switch($this->type)
		{
		case 'row':
			print "<div";
			$this->add_style('clear: left;overflow: auto');
			
			$this->atr->set_attributes($this->parameters);
			$this->atr->render_attributes();
			print ">";
			$this->render_subelements($this->content,'raw');
			$this->print_indent();
			print "</div>";
			break;
		case 'section':
			print "<div";
			$this->add_style('position:relative;float:left');
			$this->atr->set_attributes($this->parameters);
			$this->atr->render_attributes();
			print ">";
			$this->render_subelements($this->content,'raw');
			$this->print_indent();
			print "</div>";
			break;
		case 'inline_block':
			print "<div";
			if(is_array($this->parameters) && array_key_exists('style',$this->parameters))
			{
				$this->parameters['style'] .= ';display:inline-block';
			} else {
				$this->parameters['style'] = 'display:inline-block';
			}
			$this->atr->set_attributes($this->parameters);
			$this->atr->render_attributes();
			print ">";
			$this->render_subelements($this->content,'raw');
			$this->print_indent();
			print "</div>";
			break;

		case 'include':
		case 'require':
			require($this->content);
			break;
		case 'p':
		case 'paragraph':
			print "<p";
			$this->atr->set_attributes($this->parameters);
			$this->atr->render_attributes();
			print ">";
			$this->render_subelements($this->content,'raw');
			break;
		case 'h1':
			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
			case 'heading':
				print "<".$this->type;
				$this->atr->set_attributes($this->parameters);
				$this->atr->render_attributes();
				print ">";
				$this->render_subelements($this->content,'raw');
				print "</".$this->type.">";
				break;
			case 'button':
				print "<button";
				$this->atr->set_attributes($this->parameters);
				$this->atr->render_attributes();
				print ">";
				$this->render_subelements($this->content,'raw');
				print "</button>";
				break;	
		case 'a':
		case 'anchor':
			print "<a";
			$this->atr->set_attributes($this->parameters);
			$this->atr->render_attributes();
			print ">";
			$this->render_subelements($this->content,'raw');
			print "</a>";
			break;
		case 'html':
		case 'page':
			print "<html";
			$this->atr->set_attributes($this->parameters);
			$this->atr->render_attributes();
			print ">";
			$this->render_subelements($this->content,'raw');
			print "</html>";
			break;
		case 'img':
		case 'image':
		case 'link':
		case 'input':
		case 'meta':
			print "<".$this->type;
			$this->atr->set_attributes($this->parameters);
			$this->atr->render_attributes();
			if(is_null($this->content))
			{
				print " />";
			} else {
				print ">";
				$this->render_subelements($this->content,'raw');
				print "</".$this->type.">";
			}
			break;
		case 'body':
		case 'html':
		case 'head':
		case 'title':
		case 'li':
		case 'ul':
		case 'nav':
		case 'form':
		case 'textarea':
		case 'div':
		case 'script':
		case 'audio':
		case 'source':
		case 'select':
		case 'option':
		case 'span':
		case 'table':
		case 'th':
		case 'tr':
		case 'td':
		case 'style':
		case 'label':
			print "<".$this->type;
			$this->atr->set_attributes($this->parameters);
			$this->atr->render_attributes();
			print ">";
			$this->render_subelements($this->content,'raw');
			print "</".$this->type.">";
		break;
		case 'text':
		case 'raw':
		default:
			if(is_array($this->content))
			{
				$this->render_subelements($this->content,'raw');
			} else if(is_object($this->content) && (is_a($this->content,'content_block') || is_subclass_of($this->content,'content_block')))
			{
				$this->content->add_indent($this->indent);
				$this->content->render();
			} else {
				print $this->content;
			}
			break;
		}
	
	}
	public function push($newcontent)
	{
		if(is_null($this->content))
		{
			$this->content = $newcontent;
		} else if(is_array($this->content))
		{
			array_push($this->content,$newcontent);
		} else {
			$content = $this->content;
			$this->content = array($content,$newcontent);
		}
	}
	public function unshift($newcontent)
	{
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
	}
	public function as_html()
	{
		ob_start();
		$this->render();
		$rendered = ob_get_contents();
		ob_end_clean();
		return $rendered;
	}
	public function innerHTML()
	{
		ob_start();
		$this->render_subelements($this->content,'raw');
		$rendered = ob_get_contents();
		ob_end_clean();
		return $rendered;	
	}

}


class row extends content_block
{
	public function __construct($content=NULL,$parameters = array())
	{
		parent::__construct($content,'row',$parameters);
	}
}
class section extends content_block
{
	public function __construct($content=NULL,$parameters = array())
	{
		parent::__construct($content,'section',$parameters);
	}
}
class inline_block extends content_block
{
	public function __construct($content=NULL,$parameters = array())
	{
		parent::__construct($content,'inline_block',$parameters);
	}
}
class label extends content_block
{
	public function __construct($content=NULL,$parameters = array())
	{
		parent::__construct($content,'label',$parameters);
	}
}
class paragraph extends content_block
{
	public function __construct($content=NULL,$parameters = array())
	{
		parent::__construct($content,'paragraph',$parameters);
	}
}
class image extends content_block
{
	public function __construct($content=NULL,$parameters = array())
	{
		if(is_array($content))
		{
			exit("Invalid image construction type");
		}
		$parameters['src'] = $content;
		parent::__construct(NULL,'image',$parameters);
	}
	public function push($content)
	{
		exit("Fatal Error: Illegal to push content into an image");
	}
}
class page extends content_block
{

	public function __construct($content = NULL,$parameters = array())
	{
		parent::__construct($content,'page',$parameters);
	}

}
class anchor extends content_block
{
	public function __construct($content = NULL,$parameters = array())
	{
		parent::__construct($content,'anchor',$parameters);
	}
}
class header extends content_block
{
	public function __construct($content = NULL,$parameters = array())
	{
		parent::__construct($content,'head',$parameters);
	}
}
class body extends content_block
{
	public function __construct($content = NULL,$parameters = array())
	{
		parent::__construct($content,'body',$parameters);
	}
}
class title extends content_block
{
	public function __construct($content = NULL,$parameters = array())
	{
		parent::__construct($content,'title',$parameters);
	}
}

class stylesheet extends content_block
{
	public function __construct($content = NULL,$parameters = array())
	{
		$parameters['rel'] = "stylesheet";
		$parameters['type'] = "text/css";
		$parameters['href'] = $content;
		parent::__construct(NULL,'link',$parameters);
	}
}
class script extends content_block
{
	public function __construct($javascript)
	{

		$content = new content_block($javascript,'script',array('type'=>'text/javascript'));
		parent::__construct($content,'raw');
	}
}
class externalscript extends content_block
{
	public function __construct($url)
	{
		$mtime = 0;
		try 
		{
			$mtime = @filemtime(CA_CORE.'/'.$url);
		} catch(Exception $e)
		{
			$mtime = rand();
		}
		$content = new content_block("",'script',array('type'=>'text/javascript','src'=>CA_URL_CORE.'/'.$url."?".$mtime));
		parent::__construct($content,'raw');
	}
}
class externalappscript extends content_block
{
	public function __construct($url)
	{		
		$mtime = 0;
		try 
		{
			$mtime = @filemtime(CA_APP.'/'.$url);
		} catch(Exception $e)
		{
			$mtime = rand();
		}
		$content = new content_block("",'script',array('type'=>'text/javascript','src'=>CA_URL_APP.'/'.$url."?".$mtime));
		parent::__construct($content,'raw');
	}
}
class delayjump extends content_block
{
	public function __construct($url,$seconds=5)
	{
		$content = new script("setTimeout( function(){document.location.replace(".CA_URL_CORE.'/'.$url.")}, '.($seconds*1000).' );");
		parent::__construct($content,'raw');
	}
}
?>