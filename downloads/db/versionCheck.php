<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT."/lib/database.php");
require_once (DOCUMENT_ROOT.'/lib/Util.php');
$DB = new databaseI();
if($_POST["productType"])
{
	$product = $_POST["productType"];
} else {
	$product = "Invalid Product type";
}
$results = array('ReleaseId','Version','DownloadURL','InfoURL','PurchaseURL','ReleaseDate',
	'DaysSince','DailyInvites','InvitesSent');
$DB->sql(
	'SELECT id_release,version,download_url,info_url,purchase_url,release_date,'.
	'DATEDIFF(NOW(), release_date) AS days,daily_invites,invites_sent '.
	'FROM upgrade_records '.
	'WHERE `product_name`=? ORDER BY id_release DESC LIMIT 1',
	array('s',$product),
	$results
	);
$row = $results[0];
if(version_compare2($_POST['currentVersion'],$row['Version'])>=0)
{
	echo "Current matches or exceeds published version ($_POST[currentVersion],$row[Version])";
	exit;
}
if($row['DailyInvites'] > 0)
{
	$cap = $row['DailyInvites']*$row['DaysSince'];
	if(!is_null($row['InvitesSent']) && $row['InvitesSent'] >= $cap)
	{
		echo "Invite Limit hit (".$row['InvitesSent'].")";
		exit;
	}
}
if(!$row)
{
	echo "Invalid product '$product'.";
} else {
	echo "[".trim(strtoupper($product),"' \"")."]\n";
	echo "Version = ".$row['Version']."\n";
	echo "Download = ".$row['DownloadURL']."\n";
	echo "Info = ".$row['InfoURL']."\n";
	echo "Purchase = ".$row['PurchaseURL']."\n";
	echo "ReleaseDate = ".$row['ReleaseDate']."\n";

	$DB->sql(
		"UPDATE upgrade_records SET invites_sent = ? WHERE `id_release` = ?",
		array('ii',is_null($row['InvitesSent'])?1:$row['InvitesSent']+1,$row['ReleaseId'])
		);
}

?>