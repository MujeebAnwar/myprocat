<?php
/**
 * user_info helpers — key/value profile attributes (company_name, phone, …).
 * Columns: id_user, info, value
 */

function admin_users_ensure_user_info_table($DB)
{
	static $ready = false;
	if($ready)
	{
		return true;
	}

	$cols = array('Field', 'Type');
	$hasTable = @$DB->sql('SHOW COLUMNS FROM `user_info`', array(''), $cols);
	$hasInfo = false;
	$hasInfoKey = false;
	if($hasTable !== false && !empty($cols))
	{
		foreach($cols as $col)
		{
			if(!is_array($col) || !isset($col['Field']) || $col['Field'] === 'Field')
			{
				continue;
			}
			if($col['Field'] === 'info')
			{
				$hasInfo = true;
			}
			if($col['Field'] === 'info_key')
			{
				$hasInfoKey = true;
			}
		}
	}

	if($hasInfo)
	{
		$ready = true;
		return true;
	}

	// Rename info_key/info_value → info/value if present.
	if($hasInfoKey)
	{
		@$DB->sql('ALTER TABLE `user_info` CHANGE `info_key` `info` VARCHAR(64) NOT NULL', array(''));
		@$DB->sql('ALTER TABLE `user_info` CHANGE `info_value` `value` TEXT NULL', array(''));
		$ready = true;
		return true;
	}

	$ok = @$DB->sql(
		'CREATE TABLE IF NOT EXISTS `user_info` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`id_user` VARCHAR(64) NOT NULL,
			`info` VARCHAR(64) NOT NULL,
			`value` TEXT DEFAULT NULL,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uq_user_info_user_key` (`id_user`, `info`),
			KEY `idx_user_info_user` (`id_user`),
			KEY `idx_user_info_key` (`info`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
		array('')
	);
	$ready = ($ok !== false);
	return $ready;
}

/**
 * Load all info => value pairs for a user.
 *
 * @return array<string, string>
 */
function admin_users_load_user_info($DB, $id_user)
{
	if(!admin_users_ensure_user_info_table($DB))
	{
		return array();
	}
	$rows = array('info', 'value');
	$ok = @$DB->sql(
		'SELECT `info`, `value` FROM user_info WHERE id_user = ?',
		array('s', $id_user),
		$rows
	);
	if($ok === false || empty($rows))
	{
		return array();
	}

	$out = array();
	foreach($rows as $row)
	{
		if(!is_array($row) || !isset($row['info']) || $row['info'] === 'info')
		{
			continue;
		}
		$key = trim((string)$row['info']);
		if($key === '')
		{
			continue;
		}
		$out[$key] = isset($row['value']) && $row['value'] !== null
			? (string)$row['value']
			: '';
	}
	return $out;
}

/**
 * Upsert one key/value for a user. Empty value deletes the key.
 */
function admin_users_set_user_info_value($DB, $id_user, $info, $value)
{
	if(!admin_users_ensure_user_info_table($DB))
	{
		return false;
	}
	$info = trim((string)$info);
	if($info === '')
	{
		return false;
	}
	$value = $value === null ? '' : (string)$value;

	if($value === '')
	{
		return false !== $DB->sql(
			'DELETE FROM user_info WHERE id_user = ? AND `info` = ?',
			array('ss', $id_user, $info)
		);
	}

	return false !== $DB->sql(
		'INSERT INTO user_info (`id_user`, `info`, `value`)
		 VALUES (?, ?, ?)
		 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
		array('sss', $id_user, $info, $value)
	);
}

/**
 * Upsert many key/value pairs.
 *
 * @param array<string, string> $pairs
 */
function admin_users_save_user_info($DB, $id_user, array $pairs)
{
	$any = false;
	foreach($pairs as $key => $value)
	{
		if(admin_users_set_user_info_value($DB, $id_user, $key, $value))
		{
			$any = true;
		}
	}
	return $any;
}
?>
