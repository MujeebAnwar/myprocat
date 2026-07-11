<?php
require_once 'config.php';
require_once DOCUMENT_ROOT.'/lib/database.php';
require_once DOCUMENT_ROOT.'/template/Master.php';
require_once DOCUMENT_ROOT.'/template/form.php';
class messagehandler
{
	private $message;
	private $id;
	private $DB;
	public function __construct($DB, $newmessage = NULL)
	{
		$this->DB = $DB;
		$this->message = $newmessage;
	}

	public function get_message($id_message)
	{
		if(is_numeric($id_message))
		{
			$results = array('id_message','messagetext');
			$this->DB->sql(
				'SELECT id_message,message FROM messages WHERE id_message = ?',
				array('i',$id_message),
				$results
				);
			if(count($results) > 0)
			{
				return new paragraph($results[0]['messagetext'],array('class'=>'message'));
			}
		}
		return NULL;
	}
	public function post_message($message = NULL)
	{
		if(!is_null($message))
		{
			$this->message = $message;
		}
		if(is_null($this->message))
		{
			return NULL;
		}

		// This bears some explanation:
		// This syntax will insert a new message into the message table *if it does not already exist*
		// However if it *does* exist, it will instead return the id of the thing that is already there.
		$this->DB->sql(
			'INSERT INTO messages SET `message`=? ON DUPLICATE KEY UPDATE `id_message`=LAST_INSERT_ID(`id_message`)',
			array('s',$message)
			);
		return $this->DB->iid();
	}
}
function DelayShowMessage($DB,$message,$seconds=5)
{
	$msghandler = new messagehandler($DB);
	$msgid = $msghandler->post_message($message);
	$form = new form(NULL,array('id' => 'message_form', 'method' => 'POST', 'action' => '/showmessage.php'));
	$form->push(new input(NULL,array('type' => 'hidden', 'name' => 'msgid','value' => $msgid)));
	return array(
		$form,
		new content_block("setTimeout( function(){document.forms[\"message_form\"].submit()}, ". $seconds*1000 ." );",'script')
			);
}
?>