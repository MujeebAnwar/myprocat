<?php
require_once ('./config.php');
require_once (DOCUMENT_ROOT.'/setup/start.php');
require_once (DOCUMENT_ROOT.'/lib/password_recovery.php');
require_once (DOCUMENT_ROOT.'/lib/texter.php');

try
{
$json_response = array();
$json_response['Status'] = "UNTESTED";
$json_response['Error'] = "";
if(!array_key_exists('textNumber',$_POST))
{
	$json_response['Error'] = "No number supplied";
	$json_response['Status'] = "FAILED";
} else if( strlen($_POST['textNumber']) < 4 || strlen($_POST['textNumber']) > 4){
	$json_response['Error'] = "Wrong number of digits supplied";
	$json_response['Status'] = "FAILED";
} else if( preg_match('/[^0-9]/', $_POST['textNumber'])){
	$json_response['Error'] = "Number can contain only digits";
	$json_response['Status'] = "FAILED";
} else {
	$pwdrec = new recovery_session("fake e-mail address that doesn't actually exist in the database");
	if($pwdrec->get_status() !== RECOVERY_STATUS_ERROR)
	{
		// We have a valid password recovery session
		$id = $pwdrec->get_data('id_user');
		$numbersearch = "%".$_POST['textNumber'];
		$results = array('phone_number');
		$DB->sql("SELECT phone_number FROM phone_records WHERE id_user=? AND phone_number LIKE ? AND phone_type='cell'",
			array('ss',$id,$numbersearch),
			$results
		);
		$json_response['DB'] = $numbersearch;
		if(count($results) < 1)
		{
			$json_response['Error'] = "We can't locate that cell number in our records.";
			$json_response['Status'] = "FAILED";
		} else if(count($results) > 1) {
			$json_response['Error'] = "That matches more than one cell number in your record, we don't know which one to text you at, please call us.";
			$json_response['Status'] = "FAILED";
		} else {
			$t = new texter();
			try
			{
				if($t->send($results[0]['phone_number'],'MyProCAT: '.$pwdrec->get_data('pin_code').' is your verification code'))
				{
					$json_response['Error'] = "Message Sent";
					$json_response['Status'] = "SUCCESS";
				} else {
					$json_response['Error'] = "Our text message service isn't responding right now. Error Code 201";
					$json_response['Status'] = "FAILED";
				}
			} catch (Exception $e)
			{
				$json_response['Error'] = "Our text message service appears to be offline. Error Code 200";
				$json_response['Status'] = "FAILED";
			}
		}
	} else {
		$json_response['Error'] = "Your password recovery session has timed out.";
		$json_response['Status'] = "FAILED";
	}
}
print json_encode($json_response);
} catch (Exception $e)
{
	print json_encode(array('Error' => 'Server error sending text message', 'Status' => 'FAILED'));
}
?>
