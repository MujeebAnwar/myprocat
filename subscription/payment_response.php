<?php 
    require_once ('config.php');
    require_once (DOCUMENT_ROOT.'/paymentSdk/shared.php');
    require_once (DOCUMENT_ROOT.'/setup/start.php');
    require_once (DOCUMENT_ROOT.'/Service/EmailService.php');
    require_once DOCUMENT_ROOT.'/subscription/helper.php';

   
    $response = openEnvelope($_REQUEST['response']);
    // work with it as a simplexml object...
    $xmlResponse = simplexml_load_string($response);
    

    if(!$xmlResponse){
       header('Location: /subscription/payment.php');
       exit;
    }
    // // or as json...
    $jsonResponse = json_encode($xmlResponse);
    

    $paymentResponse = json_decode($jsonResponse, true);

    if(isset($paymentResponse['PaymentResponses'])){
        if(isset($paymentResponse['PaymentResponses']['PaymentResponseType']['Response']['ResponseIndicator']) 
            && $paymentResponse['PaymentResponses']['PaymentResponseType']['Response']['ResponseIndicator'] == 'A')
          {

            $transaction_id = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['TransactionID'];
            $transactionData = ['transaction_key','data'];
             $DB->sql(
                'SELECT transaction_key,data FROM casepad_subscription_session WHERE transaction_key = ?',
                array('s', $transaction_id),
                $transactionData
            );

            if(isset($transactionData[0]['data'])){

                try{
                  $transaction_data = json_decode($transactionData[0]['data'], true);
                  $transaction_id = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['TransactionID'];
                  $transaction_date = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['TransactionDate'];
                  $transaction_amount = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['Amount'];
                  $paymentMethod = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['PaymentTypeID'];
                  $guid = encryptData($paymentResponse['PaymentResponses']['PaymentResponseType']['VaultResponse']['GUID']);
                  $expiration_date = $paymentResponse['PaymentResponses']['PaymentResponseType']['VaultResponse']['ExpirationDate'];
                  $last4 = $paymentResponse['PaymentResponses']['PaymentResponseType']['VaultResponse']['Last4'];
                  $planName = $transaction_data['plan_name'];
                  $plan_id = $transaction_data['plan_id'];
                  $hours = $transaction_data['hours'];
                  $user_id = $transaction_data['user_id'];
                  $hours = $transaction_data['hours'];
                  $rate = $transaction_data['rate'];
                  $billing = $transaction_data['billing'];
                  $vault_id = encryptData($transaction_data['vault_id']);
                  $annualPaymentType = $transaction_data['annualPaymentType'] == 'one_time' ? 1 : 0;
                  $discount = 0;
  
                  $month = substr($expiration_date, 0, 2); // 12
                  $year  = substr($expiration_date, 2, 2); // 26
  
                  // Convert 2-digit year to 4-digit year (assumes 2000+)
                  $currentYear = date('Y');
                  $currentYear = substr($currentYear, 0, 2);
                  $year = $currentYear . $year;
  
                  // Create date as 1st day of that month
                  $card_expiry_date = "01-$month-$year";
                  $last4 = str_repeat('', strlen($last4)-4) . substr($last4, -4);
                  $vanReference = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['VANReference'];
                
                  // Get the last invoice number
  

                  
                  $invoiceNumber = array('invoice_number');
                  $invoice_number = $DB->sql(
                      'SELECT MAX(invoice_number) as invoice_number FROM casepad_payment_invoices',
                      array(),
                      $invoiceNumber
                  );
  

                  if(is_null($invoiceNumber[0]['invoice_number'])){
                    $invoice_number = 10800;
                  }else{
                    $invoice_number = $invoiceNumber[0]['invoice_number'] + 1;
                  }
                  $invoice_date = date("Y-m-d H:i:s", strtotime($transaction_date));
  
                  $address_details =  json_encode($paymentResponse['PaymentResponses']['PaymentResponseType']['Customer']['Address']);
                  
                  $next_payment_date = NULL;
                  if(!$annualPaymentType ){
                    $next_payment_date = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s', strtotime('+1 month'))));
                  }

                  if($billing == 'annual' && $annualPaymentType){
                    $hours = $hours * 12;
                    $discount = ($transaction_amount * 1) / (100 - 1);
                  }


                  switch($paymentMethod){
                    case '3':
                      $paymentMethod = 'American Express';
                      break;
                    case '4':
                      $paymentMethod = 'Visa';
                      break;
                    case '5':
                      $paymentMethod = 'MasterCard';
                      break;
                    case '6':
                      $paymentMethod = 'Discover';
                      break;
                    case '7':
                      $paymentMethod = 'JCB';
                      break;
                    case 'D':
                      $paymentMethod = 'Debit Card';
                      break;
                    case 'O':
                      $paymentMethod = 'Other';
                      break;
                    case 'C':
                      $paymentMethod = 'ACH';
                      break;
                    default:
                      $paymentMethod = 'Other';
                      break;
                  }

                  // In active All existing plans
                  $DB->sql('UPDATE casepad_subscribed_plan SET is_active = 0 WHERE id_owner = ?', array('s', $user_id));
                   $DB->sql(
                          'INSERT INTO casepad_subscribed_plan (`id_owner`,`plan_id`,`rate`,`hours`,`last_four_digits`,`card_expiry_date`,`vault_id`,`guid`,`commitment`,`prepaid`,`address_details`,`created_at`,`updated_at`,`subscribed_at`,`next_payment_date`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                          array('sssssssssisssss', $user_id, $plan_id, $rate, $hours,$last4, date('Y-m-d', strtotime($card_expiry_date)), $vault_id, $guid, $billing, $annualPaymentType, $address_details, date('Y-m-d H:i:s'), date('Y-m-d H:i:s'),date('Y-m-d H:i:s'),$next_payment_date)
                    );
  
                  $subscribed_plan_id = $DB->iid();

                  $DB->sql(
                    'INSERT INTO casepad_payment_invoices (`id_owner`,`subscribed_plan_id`,`invoice_number`,`transaction_id`,`van_reference`,`last_four_digits`,`invoice_date`,`payment_method`, `discount`,`total_amount`,`rate`,`hours`,`payment_response`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    array('sssssssssssis', $user_id, $subscribed_plan_id,$invoice_number, $transaction_id, $vanReference, $last4, $invoice_date,$paymentMethod, $discount, $transaction_amount, $rate, $hours, $jsonResponse)
                  );
  
                  $invoice_id = $DB->iid();
                  $DB->sql(
                    'INSERT INTO casepad_minutes_credits (`id_owner`,`minutes`,`time_stamp`,`source`,`invoice_id`) VALUES (?,?,?,?,?)',
                    array('ssssi', $user_id, $hours * 60, $invoice_date, $planName, $invoice_id)
                  );
  
                  $storage = 10;
                  $DB->sql(
                  'INSERT INTO casepad_storage_credits (`id_owner`,`storage`,`time_stamp`,`source`,`invoice_id`) VALUES (?,?,?,?,?)',
                  array('sissi', $user_id, $storage, $invoice_date, $planName, $invoice_id)
                );
  
                $DB->sql(
                  'DELETE FROM casepad_subscription_session WHERE transaction_key = ?',
                  array('s', $transaction_id)
                );
  
                $user_details = array('first_name','mid_name','last_name','email');
                $DB->sql(
                  'SELECT first_name,mid_name,last_name,email FROM accounts WHERE id_user = ?',
                  array('s', $user_id),
                  $user_details
                );

                $customerEmail = $user_details[0]['email'];
                $customerName = $user_details[0]['first_name'] . ' ' . $user_details[0]['mid_name'] . ' ' . $user_details[0]['last_name'];
  
                $invoiceData = [
                  'invoice_number' => $invoice_number,
                  'invoice_date' => date("F j, Y g:i A", strtotime($invoice_date)),
                  'rate' => $rate,
                  'hours' => $hours,
                  'payment_method' => $paymentMethod,
                  'address_details' => json_decode($address_details, true),
                  'customer_name' => $customerName,
                  'discount' => $discount,
                  'total_amount' => $transaction_amount,
                ];

                // Load the invoice email template
                ob_start();
                include(DOCUMENT_ROOT . '/subscription/invoice-email.php');
                $emailBody = ob_get_clean();
  
                // Send invoice email to customer
                if (!empty($customerEmail)) {
                    $mail = new EmailService();
                    $mail->send($customerEmail, "DepoDash Invoice - Order #" . $invoice_number, $emailBody);
                }

               
  
                // Redirect to thank you page
                $thankYouUrl = '/subscription/thank_you.php?order_id=' . urlencode($invoice_number) 
                             . '&plan=' . urlencode($plan_id) 
                             . '&amount=' . urlencode($transaction_amount);
                header('Location: ' . $thankYouUrl);
                exit;
                }catch(Exception $e){
                  header('Location: /subscription/payment_failed.php');
                  exit;
                }
              
            } else {
                header('Location: /subscription/payment_failed.php');
                exit;
            }
        } else {
            header('Location: /subscription/payment_failed.php');
            exit;
        }
    } else {
        header('Location: /subscription/payment_failed.php');
        exit;
    }



    // use the VANReference to void or refund the transaction  
    // $reference = $arrayResponse["PaymentResponses"]["PaymentResponseType"]["TransactionResponse"]["VANReference"];

?>