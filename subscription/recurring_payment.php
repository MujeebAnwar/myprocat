<?php
    
    require_once ('config.php');
    require_once DOCUMENT_ROOT.'/paymentSdk/shared.php';
    require_once DOCUMENT_ROOT.'/setup/start.php';
    require_once (DOCUMENT_ROOT.'/Service/EmailService.php');
    require_once DOCUMENT_ROOT.'/subscription/helper.php'; 
    

    $subscriptions = array('id','subscribed_at','guid','commitment','prepaid','name', 'hours', 'rate', 'address_details', 'plan_id','id_owner','email','first_name','mid_name','last_name','card_expiry_date');
    $DB->sql(
        'SELECT casepad_subscribed_plan.id,DATE_FORMAT(subscribed_at, "%Y-%m-%d"),guid,commitment,prepaid,name,hours,rate,address_details,casepad_subscribed_plan.plan_id,id_owner, email, first_name, mid_name, last_name, card_expiry_date
        FROM casepad_subscribed_plan 
        LEFT JOIN accounts ON casepad_subscribed_plan.id_owner = accounts.id_user
        LEFT JOIN subscription_plans ON casepad_subscribed_plan.plan_id = subscription_plans.id
        WHERE casepad_subscribed_plan.is_active = 1
        AND next_payment_date IS NOT NULL 
        AND date(next_payment_date) = ?
        AND commitment = ? 
        AND prepaid = ?',
        array('sss', date('Y-m-d'), 'annual', 0),
        $subscriptions
    );


    $merchant_id = $sharedCredentials["MID"];
    $merchant_key = $sharedCredentials["MKEY"];
    $transaction_type = $transactionTypes["NonUI"]["Sale"];
    $vaultOperation = "RETRIEVE";
    foreach($subscriptions as $subscription){

        if($subscription['card_expiry_date'] < date('Y-m-d')){
          generateFailedInvoice($subscription);
           continue;
        }
        $address_details = json_decode($subscription['address_details'],true);

        $firstName = $subscription['first_name'];
        $midName = $subscription['mid_name'];
        $lastName = $subscription['last_name'];
        $email = $subscription['email'];
        $planName = $subscription['name'];
        $planId = $subscription['plan_id'];
        $hours = $subscription['hours'];
        $rate = $subscription['rate'];
        $guid = $subscription['guid'];
        $email = $subscription['email'];
        $first_name = $subscription['first_name'];
        $mid_name = $subscription['mid_name'];
        $last_name = $subscription['last_name'];

         // configuring the transaction
        $amount = $subscription['rate'] * $subscription['hours'];
        $order_number = "Invoice for " . $subscription['hours'] . " hours of " . $subscription['name'];
        $transaction_id = uniqid('order_', true);
        $address_line1 = $address_details['AddressLine1'];
        $address_line2 = $address_details['AddressLine2'];
        $city = $address_details['City'];
        $state = $address_details['State'];
        $zip_code = $address_details['ZipCode'];
        $country = $address_details['Country'];
        $telephone = isset($address_details['Telephone']) ? count($address_details['Telephone']) > 0 ? $address_details['Telephone'] : '' : '';

        // you (or your client's) merchant credentials.
        // grab a test account from us for development!


        // some arbitrary values for this demo
   


  
      $vaultToken = decryptData($subscription['guid']);
      
      // and then piecing together our XML request
      $xmlRequest = "<?xml version=\"1.0\" encoding=\"utf-16\"?>
      <Request_v1 xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\">
          <Application>
              <ApplicationID>DEMO</ApplicationID>
              <LanguageID>EN</LanguageID>
          </Application>
          <Payments>
              <PaymentType>
              <Merchant>
                  <MerchantID>$merchant_id</MerchantID>
                  <MerchantKey>$merchant_key</MerchantKey>
              </Merchant>
              <TransactionBase>
                  <TransactionID>$transaction_id</TransactionID>
                  <TransactionType>$transaction_type</TransactionType>
                  <Reference1>$order_number</Reference1>
                  <Amount>$amount</Amount>
              </TransactionBase>
              <VaultStorage>
                  <GUID>$vaultToken</GUID>
                  <Service>$vaultOperation</Service>
              </VaultStorage>
              <Customer>
                  <Name>
                      <FirstName>$firstName</FirstName>
                      <MI>$midName</MI>
                      <LastName>$lastName</LastName>
                  </Name>
                  <Address>
                      <AddressLine1>$address_line1</AddressLine1>
                      <AddressLine2></AddressLine2>
                      <City>$city</City>
                      <State>$state</State>
                      <ZipCode>$zip_code</ZipCode>
                      <Country>$country</Country>
                      <EmailAddress>$email</EmailAddress>
                      <Telephone>$telephone</Telephone>
                      <Fax></Fax>
                  </Address>
              </Customer>
              </PaymentType>
          </Payments>
      </Request_v1>";

      // since no user interaction is required, this request can be done
      // directly from the server. and since it's a server-side request,
      // there's no need to tokenize the xml.
      $url = "https://www.sageexchange.com/sevd/frmPayment.aspx";
      $body = "request=" . urlencode($xmlRequest);
      $response = makePostRequest($body, $url);
      
      // work with it as a simplexml object...
      $xmlResponse = simplexml_load_string($response);
      
      // // // or as json...
      // $jsonResponse = json_encode($xmlResponse);
      

      // echo $jsonResponse;die;
      // ... or as an array.
      $paymentResponse = json_decode($jsonResponse, true);
      

      
      if(!$xmlResponse){
        continue;
      }
    
      if(isset($paymentResponse['PaymentResponses']))
      {
          if(isset($paymentResponse['PaymentResponses']['PaymentResponseType']['Response']['ResponseIndicator']) 
              && $paymentResponse['PaymentResponses']['PaymentResponseType']['Response']['ResponseIndicator'] == 'A')
          {

              try{
                  
                  $transaction_id = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['TransactionID'];
                  $transaction_date = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['TransactionDate'];
                  $paymentMethod = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['PaymentTypeID'];
                  $transaction_amount = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['Amount'];
                  $last4 = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['Last4'];
                
                  $vanReference = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['VANReference'];
                
                  $last4 = str_repeat('', strlen($last4)-4) . substr($last4, -4);
                
                  // Get the last invoice number
      
                  $user_id = $subscription['id_owner'];
                  $invoiceNumber = array('invoice_number');
                  $invoice_number = $DB->sql(
                      'SELECT MAX(invoice_number) as invoice_number FROM casepad_payment_invoices',
                      array(),
                      $invoiceNumber
                  );
      
                  $invoice_number = $invoiceNumber[0]['invoice_number'] + 1;
                  $invoice_date = date("Y-m-d H:i:s", strtotime($transaction_date));
            
                  // Update the current subscription

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
                  $DB->sql(
                        'INSERT INTO casepad_payment_invoices (`id_owner`,`subscribed_plan_id`,`invoice_number`,`transaction_id`,`van_reference`,`last_four_digits`,`invoice_date`,`payment_method`, `total_amount`,`rate`,`hours`,`payment_response`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                        array('ssssssssssis', $user_id, $subscription['id'],$invoice_number, $transaction_id, $vanReference, $last4, $invoice_date,$paymentMethod, $transaction_amount, $rate, $hours, $jsonResponse)
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
      
                  if($subscription['commitment'] == 'annual' && $subscription['prepaid'] == 0){
                        $start = new DateTime($subscription['subscribed_at']);
                        $nextMonthDate = (new DateTime())->modify('+1 month');
                        $diff = $start->diff($nextMonthDate);
                        if($diff->y == 1){
                          $nextMonthDate = NULL;
                        }else{
                          $nextMonthDate = $nextMonthDate->format('Y-m-d H:i:s');
                        }
                
                        $DB->sql(
                            'UPDATE casepad_subscribed_plan SET next_payment_date = ? WHERE id = ?',
                            array('ss', $nextMonthDate, $subscription['id'])
                        );
                  }
      

                $customerEmail = $email;
      
                $customerName = $first_name . ' ' . $mid_name . ' ' . $last_name;
      
                $invoiceData = [
                  'invoice_number' => $invoice_number,
                  'invoice_date' => date("F j, Y g:i A", strtotime($invoice_date)),
                  'rate' => $rate,
                  'hours' => $hours,
                  'payment_method' => $paymentMethod,
                  'total_amount' => $transaction_amount,
                  'address_details' => $address_details,
                  'customer_name' => $customerName,
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

              }catch(Exception $e){
                  generateFailedInvoice($subscription);
                  // header('Location: /payment_failed.php');
                  exit;
              }
            
          }else{
            generateFailedInvoice($subscription);
          }
      }else{
        generateFailedInvoice($subscription);
      }
    }

    function generateFailedInvoice($subscription){
      global $DB;
      $transaction_id = uniqid('order_', true);
      $invoiceNumber = array('invoice_number');
      $invoice_number = $DB->sql(
        'SELECT MAX(invoice_number) as invoice_number FROM casepad_payment_invoices',
        array(),
        $invoiceNumber
      );
      $invoice_number = $invoiceNumber[0]['invoice_number'] + 1;
      $invoice_date = date("Y-m-d H:i:s");
      $paymentMethod = 'Recurring Payment';
      $vanReference = '';
      $last4 = NULL;
      $rate = $subscription['rate'];
      $hours = $subscription['hours'];
      $total_amount = $subscription['rate'] * $subscription['hours'];
      $discount = 0;
      $payment_response = 'Failed to process payment';
      $DB->sql(
        'INSERT INTO casepad_payment_invoices (`id_owner`,`subscribed_plan_id`,`invoice_number`,`transaction_id`,`van_reference`,`last_four_digits`,`invoice_date`,`payment_method`,`discount`, `total_amount`,`rate`,`hours`,`payment_response`,`status`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        array('sssssssssssisi', $subscription['id_owner'], $subscription['id'],$invoice_number, $transaction_id, $vanReference, $last4, $invoice_date,$paymentMethod, $discount, $total_amount, $rate, $hours, $payment_response,0)
      );

      $start = new DateTime($subscription['subscribed_at']);
      $nextMonthDate = (new DateTime())->modify('+1 month');
      $diff = $start->diff($nextMonthDate);
      if($diff->y == 1){
        $nextMonthDate = NULL;
      }else{
        $nextMonthDate = $nextMonthDate->format('Y-m-d H:i:s');
      }

      $DB->sql(
        'UPDATE casepad_subscribed_plan SET next_payment_date = ?, is_active = 0 WHERE id = ?',
        array('ss', $nextMonthDate, $subscription['id'])
      );

    }
?>