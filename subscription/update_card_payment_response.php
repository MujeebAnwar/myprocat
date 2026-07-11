<?php
   require_once ('config.php');
   require_once (DOCUMENT_ROOT.'/setup/start.php');
   require_once (DOCUMENT_ROOT.'/Service/EmailService.php');
   require_once DOCUMENT_ROOT.'/subscription/helper.php';
   require_once (DOCUMENT_ROOT.'/paymentSdk/shared.php');

  
   $response = openEnvelope($_REQUEST['response']);
   // work with it as a simplexml object...
   $xmlResponse = simplexml_load_string($response);
   
   if(!$xmlResponse){
      header('Location: /subscription/my_subscription.php');
      exit;
   }
   // // or as json...
   $jsonResponse = json_encode($xmlResponse);

   $cardResponse = json_decode($jsonResponse, true);

   if(isset($cardResponse['VaultResponse'])){

      if(isset($cardResponse['VaultResponse']['Response']['ResponseIndicator']) 
         && $cardResponse['VaultResponse']['Response']['ResponseIndicator'] == 'A')
      {
        try{
            $guid = $cardResponse['VaultResponse']['GUID'];
            $expiration_date = $cardResponse['VaultResponse']['ExpirationDate'];
            $last4 = $cardResponse['VaultResponse']['Last4'];
            $last4 = str_repeat('', strlen($last4)-4) . substr($last4, -4);
            $month = substr($expiration_date, 0, 2); // 12
            $year  = substr($expiration_date, 2, 2); // 26
            // Convert 2-digit year to 4-digit year (assumes 2000+)
            $currentYear = date('Y');
            $currentYear = substr($currentYear, 0, 2);
            $year = $currentYear . $year;
            // Create date as 1st day of that month
            $card_expiry_date = "01-$month-$year";
            $last4 = str_repeat('', strlen($last4)-4) . substr($last4, -4);
            $user_id = $UserAccount->user_details['id_user'];
            $DB->sql(
               'UPDATE casepad_subscribed_plan SET card_expiry_date = ?, last_four_digits = ? WHERE id_owner = ? and is_active = ?',
               array('sssi', date('Y-m-d', strtotime($card_expiry_date)), $last4, $user_id, 1),
            );
           //  Show Message using message handler
           header('Location: /subscription/card_updated_message.php?success=true&title=Card Updated Successfully&message=Your card has been updated successfully&buttonText=Back to Subscription&buttonHref=/subscription/my_subscription.php');
        }catch(Exception $e){
            header('Location: /subscription/card_updated_message.php?success=false&title=Card Update Failed&message=Your card could not be updated. Please try again or contact support.&buttonText=Back to Subscription&buttonHref=/subscription/my_subscription.php');
        }
      }
   }else{
    header('Location: /subscription/card_updated_message.php?title=Card Update Failed&message=Your card could not be updated. Please try again or contact support.&buttonText=Back to Subscription&buttonHref=/subscription/my_subscription.php');
   }