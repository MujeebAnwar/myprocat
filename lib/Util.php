<?php 
function json_fail($object)
{
	header("HTTP/1.1 400 Bad Request");
	header('Content-Type: application/json; charset=utf-8');
	echo(json_encode($object,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT));
	exit;
}

function fail($message = NULL)
{
	header("HTTP/1.1 400 Bad Request");
	if(is_null($message))
	{
		echo("Bad Request");
	} else {
		echo($message);
	}
	exit;
}
function json_succeed($object)
{
	header("HTTP/1.1 200 Success");
	header('Content-Type: application/json; charset=utf-8');
	echo(json_encode($object,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT));
	exit;
}
function succeed($message = NULL)
{
	header("HTTP/1.1 200 Success");
	if(is_null($message))
	{
		echo("Success.");
	} else {
		echo($message);
	}
	exit;
}
function sanitize_numeric($requestOb,$fieldName)
{
	if(!is_array($requestOb))
	{
		fail("Invalid Request Object.");
	}
	$status = "{$fieldName} not specified";
	if(!array_key_exists($fieldName,$requestOb))
	{
		fail($status);
	}
	$matches = array();
	preg_match("/^(\d+)/",$requestOb[$fieldName],$matches);

	$status = "Invalid {$fieldName}";
	if(!is_array($matches) || !array_key_exists(0,$matches))
	{
		fail($status);
	}
	return $matches[0];
}
function get_ob_numeric($ob,$key)
{
	$status = "{$key} not specified";
	if(!array_key_exists($key,$ob))
	{
		return false;
	}
	$matches = array();
	preg_match("/^(\d+)/",$ob[$key],$matches);

	$status = "Invalid {$key}";
	if(!is_array($matches) || !array_key_exists(0,$matches))
	{
		return false;
	}
	return $matches[0];
}

function get_ob_string($ob,$key)
{
	$status = "{$key} not specified";
	if(!array_key_exists($key,$ob))
	{
		return false;
	}
	return "".$ob[$key];
}
function GoToPage($url)
{
	if(headers_sent())
	{
		?>
		<script>document.location.replace("<?php echo $url ?>");</script>
		<?php

	} else {
		header('HTTP/1.1 303 See Other');
		header('Location: '.$url);
	}
	exit(0);
}
function DelayGoToPage($url,$seconds=3)
{
	?>
		<script>
		setTimeout( function(){document.location.replace("<?php echo $url ?>")}, <?php echo $seconds*1000 ?> );
		</script>
	<?php	
	exit(0);
}
function DelayGoToPageScript($url,$seconds=3)
{
	$ret = "setTimeout( function(){document.location.replace('";
	$ret .= $url;
	$ret .= "')},";
	$ret .= $seconds*1000;
	$ret .= ");";
	return $ret;
}
function FullDump($this_object)
{
	ini_set('xdebug.var_display_max_depth', -1);
	ini_set('xdebug.var_display_max_children', -1);
	ini_set('xdebug.var_display_max_data', -1);
	var_dump($this_object); 
}
function pickcolor($string)
	{
		$color = 0;
		foreach(str_split($string) AS $letter)
		{
			$color += ord($letter)*383;
		}
		return $color % 360;
	}
function version_compare2($a, $b) 
{ 
    $a = preg_split("/[ \.,]+/",$a); //Split version into pieces and remove trailing .0 
    $b = preg_split("/[ \.,]+/",$b); //Split version into pieces and remove trailing .0 
    foreach ($a as $depth => $aVal) 
    { //Iterate over each piece of A 
        if (isset($b[$depth])) 
        { //If B matches A to this depth, compare the values 
            if ($aVal > $b[$depth]) return 1; //Return A > B 
            else if ($aVal < $b[$depth]) return -1; //Return B > A 
            //An equal result is inconclusive at this point 
        } 
        else 
        { //If B does not match A to this depth, then A comes after B in sort order 
            return 1; //so return A > B 
        } 
    } 
    //At this point, we know that to the depth that A and B extend to, they are equivalent. 
    //Either the loop ended because A is shorter than B, or both are equal. 
    return (count($a) < count($b)) ? -1 : 0; 
} 
function array_populate(&$array,$key,$postkey = NULL)
{
	if(is_null($postkey))
	{
		$postkey = $key;
	}
	if(is_array($_POST))
	{
		if(array_key_exists($postkey,$_POST))
		{
			$array[$key] = $_POST[$postkey];
		}
	}
}
?>