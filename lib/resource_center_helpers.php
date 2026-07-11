<?php
require_once 'config.php';
require_once (DOCUMENT_ROOT.'/api/auth.php');
require_once (DOCUMENT_ROOT.'/lib/account.php');

class resource_center_helpers
{
	private $DB = null;
	public function __construct($DB = null)
	{
		if(is_a($DB,'databaseI'))
		{
			$this->DB = $DB;
		} else {
			$this->DB = new databaseI();
		} 
	}
	public function get_keyless_room_with_greatest_expiration($id_user)
	{
		$results = array(
			'room_expires',
			'room_name'
		);
		if (!$this->DB->sql(
<<<SQL
SELECT
	`room_permissions`.`expires` + INTERVAL 3 DAY AS 'room_expires',
	`rooms`.`room_title` AS 'room_name'
FROM `rooms` 
LEFT JOIN `room_permissions` ON `room_permissions`.`id_room` = `rooms`.`id_room`
WHERE `room_permissions`.`id_user` = ?
AND `room_permissions`.`can_read` = 1
AND `rooms`.`keyless_access` = 1
AND (`room_permissions`.`expires` + INTERVAL 3 DAY) > NOW()
ORDER BY `room_permissions`.`expires` DESC
LIMIT 1;
SQL
			, array('s',$id_user)
			, $results
		))
		{
			// error or no rows in result set
			return false;
		}
		return $results[0];
	}
	public function set_room_expiration_months_from_now($id_user, $room_title, $months) // convenience
	{
		if (is_null($months)
			|| !is_numeric($months))
		{
			throw new RuntimeException('LIB error.\n'.__METHOD__.': argument null');
		}
		$expires = new DateTime('NOW', new DateTimeZone('UTC'));
		$expires->add(new DateInterval('P'.$months.'M'));
		return $this->set_room_expiration_if_less(
			$id_user,
			$room_title,
			$expires->format('Y-m-d H:i:s')
		);
	}
	// set_room_expiration_if_less can only be used to increase the expires value
	// returns false if an error occurred, otherwise true
	public function set_room_expiration_if_less($id_user, $room_title, $expires)
	{
		if (is_null($id_user)
			|| is_null($room_title)
			|| is_null($expires))
		{
			throw new RuntimeException('LIB error.\n'.__METHOD__.': argument null');
		}
		// using (SELECT * from room_permissions) sub-query to fix MySQL error:
		// "You can't specify target table 'room_permissions' for update in FROM clause"
		return false !== $this->DB->sql(
<<<SQL
UPDATE `room_permissions` SET `expires` = ?
WHERE `room_permissions_id` IN
(
	SELECT `permissions`.`room_permissions_id`
	FROM (SELECT * from `room_permissions`) as `permissions`
	LEFT JOIN `rooms` ON `rooms`.`id_room` = `permissions`.`id_room`
	WHERE `permissions`.`id_user` = ?
	AND `rooms`.`room_title` = ?
	AND `permissions`.`expires` < ?
)
SQL
		, array('ssss', $expires, $id_user, $room_title, $expires));
	}
	// returns false if an error occurred, otherwise true
	public function set_room_expiration($id_user, $room_title, $expires)
	{
		if (is_null($id_user)
			|| is_null($room_title)
			|| is_null($expires))
		{
			throw new RuntimeException('LIB error.\n'.__METHOD__.': argument null');
		}
		// using (SELECT * from room_permissions) sub-query to fix MySQL error:
		// "You can't specify target table 'room_permissions' for update in FROM clause"
		return false !== $this->DB->sql(
<<<SQL
UPDATE `room_permissions` SET `expires` = ?
WHERE `room_permissions_id` IN
(
	SELECT `permissions`.`room_permissions_id`
	FROM (SELECT * from `room_permissions`) as `permissions`
	LEFT JOIN `rooms` ON `rooms`.`id_room` = `permissions`.`id_room`
	WHERE `permissions`.`id_user` = ?
	AND `rooms`.`room_title` = ?
)
SQL
		, array('sss', $expires, $id_user, $room_title));
	}
	// returns the single result if found, otherwise false
	public function get_room_expiration($id_user, $room_title)
	{
		if (is_null($id_user)
			|| is_null($room_title))
		{
			throw new RuntimeException('LIB error.\n'.__METHOD__.': argument null');
		}
		$results = array(
			'room_expires',
			'room_name'
		);
		if (!$this->DB->sql(
<<<SQL
SELECT
	`room_permissions`.`expires` + INTERVAL 3 DAY AS 'room_expires',
	`rooms`.`room_title` AS 'room_name'
FROM `rooms` 
LEFT JOIN `room_permissions` ON `room_permissions`.`id_room` = `rooms`.`id_room`
WHERE `room_permissions`.`id_user` = ?
AND `room_permissions`.`can_read` = 1
AND `rooms`.`room_title` = ?
SQL
			, array('ss', $id_user, $room_title)
			, $results
		))
		{
			// error or no rows in result set
			return false;
		}
		return $results[0];
	}
}
