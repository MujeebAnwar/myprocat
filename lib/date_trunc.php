<?php
require_once 'config.php';

function date_trunc($date_part, $date)
{
	switch ($date_part)
	{
		case 'year':
			$date->setDate($date->format('Y'), 1, 1);
		break;
		case 'month':
			$date->setDate($date->format('Y'), $date->format('m'), 1);
		break;
		case 'day':
			$date->setTime(0, 0, 0);
		break;
		case 'hour':
			$date->setTime($date->format('H'), 0, 0);
		break;
		case 'minute':
			$date->setTime($date->format('H'), $date->format('i'), 0);
		break;
		default:
			throw new Exception('date_trunc: date part not implemented');
	}
	return $date;
}
/*
	date_advance
	this function will work as expected with time parts, but not necessary with date parts.
	you should use date_trunc before calling date_advance with date parts.
	ex: given a date of 2020-01-31, adding 1 month, the result is 2020-03-02.
*/
function date_advance($date_part, $date)
{
	switch ($date_part)
	{
		case 'year':
			$date->add(new DateInterval('P1Y'));
		break;
		case 'month':
			$date->add(new DateInterval('P1M'));
		break;
		case 'day':
			$date->add(new DateInterval('P1D'));
		break;
		case 'hour':
			$date->add(new DateInterval('PT1H'));
		break;
		case 'minute':
			$date->add(new DateInterval('PT1M'));
		break;
		case 'second':
			$date->add(new DateInterval('PT1S'));
		break;
		default:
			throw new Exception('date_trunc: date part not implemented');
	}
	return $date;
}
function date_retreat($date_part, $date)
{
	switch ($date_part)
	{
		case 'year':
			$date->sub(new DateInterval('P1Y'));
		break;
		case 'month':
			$date->sub(new DateInterval('P1M'));
		break;
		case 'day':
			$date->sub(new DateInterval('P1D'));
		break;
		case 'hour':
			$date->sub(new DateInterval('PT1H'));
		break;
		case 'minute':
			$date->sub(new DateInterval('PT1M'));
		break;
		case 'second':
			$date->sub(new DateInterval('PT1S'));
		break;
		default:
			throw new Exception('date_trunc: date part not implemented');
	}
	return $date;
}
?>