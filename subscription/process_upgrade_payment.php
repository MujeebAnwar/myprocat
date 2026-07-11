<?php
    
    require_once ('config.php');
    require_once DOCUMENT_ROOT.'/paymentSdk/shared.php';
    require_once DOCUMENT_ROOT.'/setup/start.php';
    require_once (DOCUMENT_ROOT.'/Service/EmailService.php');
    require_once DOCUMENT_ROOT.'/subscription/helper.php'; 
    

    $planName = $_REQUEST['planName'];
    $subscribed_plan_id = $_REQUEST['subscribed_plan_id'];

    $currentSubscription = array('id','address_details','plan_id','guid','hours');
    $DB->sql(
        'SELECT id,address_details,plan_id,guid,hours FROM casepad_subscribed_plan WHERE id = ?',
        array('s', $subscribed_plan_id),
        $currentSubscription
    );
    $currentSubscription = $currentSubscription[0];




    if(!$currentSubscription){
        header('Location: /subscription/payment.php?error=no_subscription');
        exit;
    }

    $vaultToken = decryptData($currentSubscription['guid']);


    $firstName = $UserAccount->user_details['first_name'];
    $midName = $UserAccount->user_details['mid_name'];
    $lastName = $UserAccount->user_details['last_name'];
    $email = $UserAccount->user_details['email'];
  
    $newPlanId = $_REQUEST['plan_id'];
    $hours = intval($_REQUEST['hours']);

    $rate = $_REQUEST['rate'];
    $commitment = $_REQUEST['billing'];
    $prepaid = $_REQUEST['annualPaymentType'] == 'one_time' ? 1 : 0;

    $address_details = json_decode($currentSubscription['address_details'],true);


    // you (or your client's) merchant credentials.
    // grab a test account from us for development!
    $merchant_id = $sharedCredentials["MID"];
    $merchant_key = $sharedCredentials["MKEY"];

    // configuring the transaction
    $amount = $_REQUEST['amount'];

    $transaction_type = $transactionTypes["NonUI"]["Sale"];
    $vaultOperation = "RETRIEVE";

    $order_number = ucfirst($planName) . " - " . $hours . " hours (" . ucfirst($commitment) . ")";

    // some arbitrary values for this demo
    $transaction_id = uniqid('order_', true);


    $address_line1 = $address_details['AddressLine1'];
    $address_line2 = $address_details['AddressLine2'];
    $city = $address_details['City'];
    $state = $address_details['State'];
    $zip_code = $address_details['ZipCode'];
    $country = $address_details['Country'];
    $email = $address_details['EmailAddress'];
    $telephone = $address_details['Telephone'];

    
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
    
    // // work with it as a simplexml object...
    $xmlResponse = simplexml_load_string($response);
    
    // // or as json...
    $jsonResponse = json_encode($xmlResponse);
    

    // $jsonResponse = '{"PaymentResponses":{"PaymentResponseType":{"Response":{"ResponseIndicator":"A","ResponseCode":"610610","ResponseMessage":"APPROVED 610610                 "},"TransactionResponse":{"AuthCode":"610610","AVSResult":{},"CVVResult":"P","VANReference":"BMDVFNTUVG","TransactionID":"order_6936da362273b2.85767597","Last4":"XXXXXXXXXXXX1111","PaymentDescription":"411111XXXXXX1111","Amount":"55","PaymentTypeID":"4","Reference1":"CLASSIC - 5 hours (Monthly)","TransactionDate":"12\/8\/2025 9:01:27 AM","EntryMode":"K","TaxAmount":"0","ShippingAmount":"0","TransactionPaymentType":"CREDITCARD","VatAmount":"0","HarmonizedTaxAmount":"0","ProvincialTaxAmount":"0","QuebecTaxAmount":"0"},"Customer":{"Name":{"FirstName":"Web","MI":{},"LastName":"Dev"},"Address":{"AddressLine1":"Beri Wala Chowk","AddressLine2":"Array","City":"Sialkot ","State":"Punjab","ZipCode":"53204","Country":"PK","EmailAddress":"webdev@fake.1.com","Telephone":"03216137253","Fax":{}},"Company":{"Address":{}}},"ShippingRecipient":{"Name":{},"Address":{},"Company":{"Address":{}}}}},"VaultAccountResponse":{"Response":{}}}';
    // echo $jsonResponse;die;
    // ... or as an array.
    $paymentResponse = json_decode($jsonResponse, true);
    

    if(!$xmlResponse){
        header('Location: /subscription/upgrade.php');
        exit;
     }
   
     

     if(isset($paymentResponse['PaymentResponses'])){
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
                $discount = 0;
                // Get the last invoice number
    
                $user_id = $UserAccount->user_details['id_user'];
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

                $next_payment_date = NULL;
                if(!$prepaid ){
                    $next_payment_date = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s', strtotime('+1 month'))));
                }
                if($commitment == 'annual' && $prepaid){
                  $hours = $hours * 12;
                  $discount = ($transaction_amount * 1) / (100 - 1);
                }
                $subscriped_at = date('Y-m-d H:i:s');
                $DB->sql(
                    'UPDATE casepad_subscribed_plan SET plan_id = ?, rate = ?, hours = ?, commitment = ?, prepaid = ?, next_payment_date = ?, subscribed_at = ? WHERE id = ?',
                    array('isssissi', $newPlanId, $rate, $hours, $commitment, $prepaid, $next_payment_date,$subscriped_at, $subscribed_plan_id)
                );

                $DB->sql(
                      'INSERT INTO casepad_payment_invoices (`id_owner`,`subscribed_plan_id`,`invoice_number`,`transaction_id`,`van_reference`,`last_four_digits`,`invoice_date`,`payment_method`,`discount`, `total_amount`,`rate`,`hours`,`payment_response`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                      array('sssssssssssis', $user_id, $subscribed_plan_id,$invoice_number, $transaction_id, $vanReference, $last4, $invoice_date,$paymentMethod, $discount,$transaction_amount, $rate, $hours, $jsonResponse)
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
    
            
    
              $customerEmail = $UserAccount->user_details['email'];
    
              $customerName = $UserAccount->user_details['first_name'] . ' ' . $UserAccount->user_details['mid_name'] . ' ' . $UserAccount->user_details['last_name'];
    
              $address_details = json_decode($currentSubscription['address_details'],true);
              $invoiceData = [
                'invoice_number' => $invoice_number,
                'invoice_date' => date("F j, Y g:i A", strtotime($invoice_date)),
                'rate' => $rate,
                'hours' => $hours,
                'payment_method' => $paymentMethod,
                'total_amount' => $transaction_amount,
                'address_details' => $address_details,
                'customer_name' => $customerName,
                'discount' => $discount,
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
              $thankYouUrl = '/thank_you.php?order_id=' . urlencode($invoice_number) 
                           . '&plan=' . urlencode($currentSubscription['plan_id']) 
                           . '&amount=' . urlencode($transaction_amount);
              header('Location: ' . $thankYouUrl);
              exit;
            }catch(Exception $e){
                header('Location: /subscription/payment_failed.php');
                exit;
            }
           
        }

        header('Location: /subscription/payment_failed.php');
        die;
    }
   

?>