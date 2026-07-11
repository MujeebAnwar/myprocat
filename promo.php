<?php
require_once ('config.php');
require_once DOCUMENT_ROOT.'/setup/start.php';
require_once DOCUMENT_ROOT.'/template/Master.php';
require_once DOCUMENT_ROOT.'/template/form.php';
require_once DOCUMENT_ROOT.'/lib/messages.php';
require_once DOCUMENT_ROOT.'/template/banner.php';
require_once DOCUMENT_ROOT.'/lib/mailer.php';
require_once DOCUMENT_ROOT.'/lib/Util.php';
$error = "";
$showform = true;
function validate_postcontent()
{
	global $error,$DB,$showform;
	if(!is_array($_POST))
	{
		return false;
	}
	if(!array_key_exists('id_promo',$_POST) || strlen($_POST['id_promo']) < 1)
	{
		return false;
	}
	if(!array_key_exists('First_Name',$_POST) || strlen($_POST['First_Name']) < 1)
	{
		$error = "You must enter a first name";
		return false;
	}
	if(!array_key_exists('Last_Name',$_POST) || strlen($_POST['Last_Name']) < 1)
	{
		$error = "You must enter a last name";
		return false;
	}
	if(!array_key_exists('Email',$_POST) || strlen($_POST['Email']) < 1)
	{
		$error = "You must enter an e-mail address";
		return false;
	}
	if(!phpMailer::ValidateAddress($_POST['Email']))
	{
		$error = "You must enter a valid e-mail address";
		return false;
	}
	$results = array('id_promo');
	$DB->sql("SELECT id_promo FROM promo_registrations WHERE email LIKE ? and id_promo=?",
		array('si',strtolower($_POST['Email']),$_POST['id_promo']),
		$results
		);
	if(count($results) > 0)
	{
		$error = "You have already registered for this promotion!";
		$showform = false;
		return false;
	}
	return true;

}
$page = new page();
$header = new header();
$set_title = "Procat Deals!";
$header->push(new content_block($set_title,'title'));
$header->push(new stylesheet('/css/PromoCSS.css'));
$header->push(new content_block(NULL,'link',array('rel' => 'shortcut icon','href' => '/favicon.ico')));
$page->push($header);
$body = new body();
$results = array('promo_name','promo_description','is_expired'); // Promo fields
	$DB->sql("SELECT promo_name,promo_description,expiry<NOW() FROM promotions WHERE id_promo=?",
		array('i',$_GET['offer']),
		$results
		);
$contentSection = new row(NULL,array('class' => 'content_section'));
$contentBody = new section(NULL,array('class' => 'promo_body_section'));
if(count($results) == 1)  // The URL contained a valid promo code
{
	$offer = new section(NULL,array('class'=> 'promo_offer'));
	$offer->push(new paragraph($results[0]['promo_name'],array('class'=> 'offer_name')));
	$offer->push(new paragraph($results[0]['promo_description'],array('class' => 'offer_description')));
	if($results[0]['is_expired'])
	{
		$contentBody = new paragraph("Sorry, This promotion has expired.",array('class' => 'message'));
	} else {
		$contentSection->push($offer);
		if(validate_postcontent())
		{
			$m = new Mailer();

			$CouponChars = range('A','N');
			array_splice($CouponChars,-1,0,range('P','Z'));
			array_splice($CouponChars,-1,0,range('0','9'));
			
			$CouponCode = "";
			for($i=0;$i<4;$i++)
			{
				$CouponCode .= $CouponChars[rand(0,count($CouponChars))];
			}
			//$CouponCode = implode("-", str_split($CouponCode, 4));
			if($DB->sql('INSERT INTO promo_registrations '.
				'(`first_name`, `last_name`, `email`, `id_promo`, `registration_code`) '.
				'VALUES (?, ?, ?, ?, ?)',
				array('sssis',$_POST['First_Name'],$_POST['Last_Name'],$_POST['Email'],$_POST['id_promo'],$CouponCode)))
			{
				$contentBody->push(new paragraph(
						"We have sent your personlized coupon code to your e-mail address, please check your e-mail!",
						array('class' => 'message')));
				$contentBody->push(new content_block(DelayGoToPageScript('http://www.procat.com',7),'script'));
				$m->Send($_POST['Email'],"Procat Coupon: ".$results[0]['promo_name'],
					"Dear ".$_POST['First_Name'].", \r\n\r\n".
					"Thank you for your interest in our products.\r\nHere is your coupon code for the '".
					$results[0]['promo_name']."' promotion:\r\n\r\n".
					$CouponCode."\r\n\r\n".
					"Please present this coupon code to your ProCAT sales representative to redeem this offer.\r\n\r\n".
					"ProCAT Sales\r\n".
					"(800) 966-1221\r\n\r\n".
					"Do *NOT* reply to this e-mail, this e-mail address is unmonitored, if you have questions, ".
					"or did not sign up for this promotion yourself, please e-mail us at support@procat.com.\r\n".
					"Prices are subject to change without notice.  You may not combine multiple coupons towards a single purchase.  ".
					"Coupons expire within 30 days of its issue date but no longer than the term of the stated promotion."
					);
			} else {
				$contentBody->push(new paragraph(
						"There was an error processing your registration.",
						array('class' => 'message')));
				$contentBody->push(new paragraph(
						$DB->error,
						array('class' => 'message')));
			}

					
		} else {
			// Display promo info form:
			
			if(count($results) == 1)  // The URL contained a valid promo code
			{		
				if($results[0]['is_expired'])
				{
					$contentBody = new paragraph("Sorry, This promotion has expired.",array('class' => 'message'));
				} else {
					// Display form to enter promo information
					
					$form = new form(NULL,array('method'=>'POST'));
					$form->push(new input(NULL,array('type'=>'hidden','name'=>'id_promo','value'=>$_GET['offer'])));
					$form->push(new field("First Name",array('class'=>'promo_field','arrange'=>'vertical')));
					$form->push(new field("Last Name",array('class'=>'promo_field','arrange'=>'vertical')));
					$form->push(new field("Email",array('class'=>'promo_field','arrange'=>'vertical')));
					$form->push(new submit("Submit",array('class'=>'promo_field','arrange'=>'vertical')));
					if($error !== "")
					{
						$contentBody->push(new paragraph($error,array('class'=>'errormessage')));
					}
					if($showform)
					{
						$contentBody->push(new paragraph(
							"Please fill out the following form to get your personalized coupon code:",
							array('class' => 'message')));
						$contentBody->push($form);
					}
				}
			} 
		}
	}
} else {
	$contentBody = new paragraph("The URL you entered is invalid.",array('class' => 'message'));
}
/////
$contentSection->push($contentBody);
$body->push($contentSection);
$page->push($body);
$page->render();
?>