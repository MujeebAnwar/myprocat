<?php

function makeError(&$responseOb,$error,$extra = "")
{
	$errorCodes = [
	'Unknown' => 0,
	'No action Requested' => 1,
	'Unable to create transcript: ' => 2,
	'Cannot invite that user again' => 3,
	'No such user to invite' => 4,
	'You cannot invite a producer for this transcript' => 5,
	'Unable to write change to transcript' => 7,
	'Unable to make changes to the current transcript' => 8,
	'You are logged in, but not connected to a transcript.' => 9,
	'Your session has expired' => 10,
	'Invalid Parameters, missing command code' => 11,
	'Internal server error: ' => 12,
	'Current user does not have permission to create tokens for specified transcript' => 13,
	"Token string specified when creating new token" => 14,
	'No token parameters specified' => 15,
	'Missing required token string parameter' => 16,
	"Failed to create token" => 17,
	'You are not logged in' => 18,
	'You do not have permission to view this transcript' => 19,
	'You are not connected to a transcript' => 20
	];
	$responseOb['er'] = $error.$extra;
	$responseOb['en'] = $errorCodes[$error];
}
function clearError(&$responseOb)
{
	unset($responseOb['er']);
	unset($responseOb['en']);
}