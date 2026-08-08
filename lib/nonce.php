<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/lib/database.php');

interface nonce_cache
{
	public function read(&$expires, &$nonce, $realm);
	public function write($expires, $nonce, $realm);
	public function delete($realm);
}

class fake_nonce_cache implements nonce_cache
{
	public function read(&$expires, &$payload, $realm)
	{
		$content = file_get_contents('c:/nonces/' . $realm . '.txt');
		$result = preg_match(
			'/(\d+):(.*)/',
			$content,
			$matches
		);
		if ($result != 1 || count($matches) != 3)
		{
			throw new RuntimeException('LIB error.\nfailed to read nonce from file');
		}
		$expires = $matches[1];
		$payload = base64_decode($matches[2]);
	}
	public function write($expires, $payload, $realm)
	{
		file_put_contents(
			'c:/nonces/' . $realm . '.txt',
			$expires . ':' . base64_encode($payload)
		);
	}
	public function delete($realm)
	{
		unlink('c:/nonces/' . $realm . '.txt');
	}
}

class db_nonce_cache implements nonce_cache
{
	private $db = null;
	private $id_user = null;
	public function __construct($db, $id_user)
	{
		if (is_null($db)
			|| is_null($id_user))
		{
			throw new RuntimeException('LIB error.\n'.__METHOD__.': argument null');
		}
		$this->db = $db;
		$this->id_user = $id_user;
	}
	public function read(&$expires, &$payload, $realm)
	{
		if (is_null($realm))
		{
			throw new RuntimeException('LIB error.\n'.__METHOD__.': argument null');
		}
		$results = array('expires', 'nonce');
		if (!$this->db->sql(
<<<SQL
SELECT expires, nonce
FROM nonces
WHERE realm = ? AND id_user = ? AND expires > NOW()
SQL
		, array('si', $realm, $this->id_user)
		, $results))
		{
			throw new RuntimeException('LIB error.\nfailed to read nonce from database: '.$this->db->error);
		}
		$expires = new DateTime($results[0]['expires']);
		$payload = base64_decode($results[0]['nonce']);
	}
	public function write($expires, $payload, $realm)
	{
		if (is_null($expires)
			|| is_null($payload)
			|| is_null($realm))
		{
			throw new RuntimeException('LIB error.\n'.__METHOD__.': argument null');
		}
		if (!$this->db->sql(
<<<SQL
INSERT INTO nonces (expires, nonce, realm, id_user)
VALUES (?, ?, ?, ?)
ON DUPLICATE KEY UPDATE
nonces.expires = ?,
nonces.nonce = ?
SQL
		, array('ssssss', $expires->format('Y-m-d H:i:s'), base64_encode($payload), $realm, $this->id_user, $expires->format('Y-m-d H:i:s'), base64_encode($payload))))
		{
			throw new RuntimeException('LIB error.\nfailed to write nonce to database: '.$this->db->error);
		}
	}
	public function delete($realm)
	{
		if (is_null($realm))
		{
			throw new RuntimeException('LIB error.\n'.__METHOD__.': argument null');
		}
		if (!$this->db->sql(
<<<SQL
DELETE FROM nonces
WHERE realm = ? AND id_user = ?
SQL
			, array('ss', $realm, $this->id_user)))
		{
			throw new RuntimeException('LIB error.\nfailed to delete nonce from database: '.$this->db->error);
		}
	}
}

class nonce
{
	private $expires = null;
	private $payload = null;
	public function __construct($expires, $payload)
	{
		$this->expires = $expires;
		$this->payload = $payload;
	}
	public function is_expired()
	{
		return time() > $this->expires->getTimestamp();
	}
	public function verify($payload)
	{
		if (is_null($payload))
		{
			throw new RuntimeException('LIB error.\n'.__METHOD__.': argument null');
		}
		if ($this->is_expired())
		{
			throw new RuntimeException('LIB error.\nnonce is expired');
		}
		if ($this->payload !== $payload)
		{
			throw new RuntimeException('LIB error.\nnonce is invalid');
		}
	}
	public function expires()
	{
		return $this->expires;
	}
	public function payload()
	{
		return $this->payload;
	}
}

class noncelib
{
	private $ncache = null;
	private static function realm($action, $id)
	{
		return $action.'/'.strval($id);
	}
	public function __construct($ncache)
	{
		if (is_null($ncache))
		{
			throw new RuntimeException('LIB error.\n'.__METHOD__.': argument null');
		}
		$this->ncache = $ncache;
	}
	public function create($length, $expires, $action, $id)
	{
		if (is_null($length)
			|| $length == 0
			|| is_null($action)
			|| is_null($id))
		{
			throw new RuntimeException('LIB error.\n'.__METHOD__.': argument null');
		}
		$anonce = new nonce(
			$expires,
			openssl_random_pseudo_bytes($length)
		);
		$this->ncache->write($anonce->expires(), $anonce->payload(), self::realm($action, $id));
		return $anonce;
	}
	public function verify_and_delete($other_payload, $action, $id)
	{
		$this->verify($other_payload, $action, $id);
		$this->delete($action, $id);
	}
	public function verify($other_payload, $action, $id)
	{
		if (is_null($other_payload)
			|| is_null($action)
			|| is_null($id))
		{
			throw new RuntimeException('LIB error.\n'.__METHOD__.': argument null');
		}
		$expires = null;
		$payload = null;
		$this->ncache->read($expires, $payload, self::realm($action, $id));
		$anonce = new nonce($expires, $payload);
		$anonce->verify($other_payload);
	}
	public function delete($action, $id)
	{
		if (is_null($action)
			|| is_null($id))
		{
			throw new RuntimeException('LIB error.\n'.__METHOD__.': argument null');
		}
		$this->ncache->delete(self::realm($action, $id));
	}
}
?>
