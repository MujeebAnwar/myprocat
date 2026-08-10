<?php
$query = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
	? '?'.$_SERVER['QUERY_STRING']
	: '';

if($_SERVER['REQUEST_METHOD'] === 'POST')
{
	require_once __DIR__.'/signup/forgotpassword.php';
	exit;
}

header('Location: /signup/forgotpassword.php'.$query);
exit;
?>
