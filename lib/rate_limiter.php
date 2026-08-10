<?php
require_once (DOCUMENT_ROOT.'/config.php');
require_once DOCUMENT_ROOT.'/lib/database.php';

/**
 * Returns true when the request is allowed, false when the limit is exceeded.
 */
function rate_limiter($requester, $limit, $seconds_period)
{
	if ($requester == null) {
		throw new Exception("rate_limit: requester param cannot be null");
	}
	if ($limit == null) {
		throw new Exception("rate_limit: limit param cannot be null");
	}
	if ($seconds_period == null) {
		throw new Exception("rate_limit: period param cannot be null");
	}
	global $DB;
	if (is_null($DB)) {
		$DB = new databaseI();
	}
	// get the ip address of the client, handling proxy headers if present
	$ip = $_SERVER['REMOTE_ADDR'];
	if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	}
	// ensure the IP address is a valid ipv4 or ipv6 address
	if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
		throw new Exception('Error: Invalid IP address');
	}
	// purge old limit history (for all clients)
	if (false === $DB->sql(
<<<SQL
DELETE FROM `rate_limits` WHERE DATE_ADD(`first_request_time`, INTERVAL ? SECOND) < NOW()
SQL
		, ['i', $seconds_period]
		))
	{
		throw new Exception($DB->error);
	}
	// increment the request count
	if (false === $DB->sql(
<<<SQL
INSERT INTO `rate_limits` (`requester`,`ip_address`,`request_count`,`first_request_time`) VALUES (?,?,1,NOW())
ON DUPLICATE KEY UPDATE `request_count`=`request_count`+1
SQL
		, ['ss', $requester, $ip]
		))
	{
		throw new Exception($DB->error);
	}
	// check if the limit has been exceeded
	$data = ['limit_exceeded'];
	if (false === $DB->sql(
<<<SQL
SELECT 1 FROM `rate_limits`
WHERE `requester` = ? AND `ip_address` = ? AND `request_count` > ? AND DATE_ADD(`first_request_time`, INTERVAL ? SECOND) >= NOW()
SQL
		, ['ssii', $requester, $ip, $limit, $seconds_period]
		, $data
		))
	{
		throw new Exception($DB->error);
	}
	if (count($data) === 1
		&& $data[0]['limit_exceeded'])
	{
		return false;
	}
	return true;
}
?>
