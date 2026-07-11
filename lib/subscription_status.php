<?php
require_once(DOCUMENT_ROOT.'/lib/account.php');
class subscription_status
{
	private $_for;
	public function __construct(useraccount &$ownerAccount)
	{
		$this->_for = $ownerAccount;
	}
	public function query()
	{
		$results = ['used','total'];
		$count = $this->_for->dbH()->sql(<<<SQL
SELECT 
(SELECT count(*) FROM casepad_sessions 
	LEFT JOIN casepad_transcript 
	ON casepad_sessions.id_transcript = casepad_transcript.id
    WHERE casepad_transcript.id_owner = ? AND casepad_transcript.subscription_status > 0 AND casepad_sessions.permissions LIKE '%E%') AS used,
(SELECT count(*) as total FROM casepad_subscriptions 
    WHERE casepad_subscriptions.id_user = ? AND casepad_subscriptions.expiration > NOW()) AS total
SQL
		,['ss',$this->_for->user_details['id_user'],$this->_for->user_details['id_user']]
		,$results
		);
		if($count !== false && $count == 1)
		{
			return $results[0];
		}
		return false;
}
	public function avail()
	{
		$rv = $this->query();
		if($rv !== false)
		{
			return $rv['total'] - $rv['used'];
		}
		return false;
		
	}
}
?>