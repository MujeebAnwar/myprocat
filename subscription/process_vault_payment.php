<?php
    
    require_once ('config.php');
    require_once DOCUMENT_ROOT.'/paymentSdk/shared.php';
    require_once DOCUMENT_ROOT.'/setup/start.php';
    require_once (DOCUMENT_ROOT.'/Service/EmailService.php');
    require_once DOCUMENT_ROOT.'/subscription/helper.php'; 
    

    
    $vaultToken = decryptData($_REQUEST['vaultToken']);
    $planName = $_REQUEST['planName'];
    $hours = $_REQUEST['hours'];
    $billing = $_REQUEST['billing'];


    $firstName = $UserAccount->user_details['first_name'];
    $midName = $UserAccount->user_details['mid_name'];
    $lastName = $UserAccount->user_details['last_name'];
    $email = $UserAccount->user_details['email'];
  
    $hours = $_REQUEST['hours'];
    $rate = $_REQUEST['rate'];

    $subscription_id = $_REQUEST['subscription_id'];
    $currentSubscription = array('id','address_details','plan_id');
    $DB->sql(
        'SELECT id,address_details,plan_id FROM casepad_subscribed_plan WHERE id = ?',
        array('s', $subscription_id),
        $currentSubscription
    );
    $currentSubscription = $currentSubscription[0];

    
    $address_details = json_decode($currentSubscription['address_details'],true);


    // you (or your client's) merchant credentials.
    // grab a test account from us for development!
    $merchant_id = $sharedCredentials["MID"];
    $merchant_key = $sharedCredentials["MKEY"];

    // configuring the transaction
    $amount = $_REQUEST['amount'];

    $transaction_type = $transactionTypes["NonUI"]["Sale"];
    $vaultOperation = "RETRIEVE";

    $order_number = ucfirst($planName) . " - " . $hours . " hours (" . ucfirst($billing) . ")";

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
                    <AddressLine2>$address_line2</AddressLine2>
                    <City>$city</City>
                    <State>$state</State>
                    <ZipCode>$zip_code</ZipCode>
                    <Country>$country</Country>
                    <EmailAddress>$email</EmailAddress>
                    <Telephone></Telephone>
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
    
    // or as json...
    $jsonResponse = json_encode($xmlResponse);
    

    // ... or as an array.
    $paymentResponse = json_decode($jsonResponse, true);
    

    if(!$xmlResponse){
        header('Location: /subscription/buy_credits.php');
        exit;
     }
   
     
     if(isset($paymentResponse['PaymentResponses'])){
        if(isset($paymentResponse['PaymentResponses']['PaymentResponseType']['Response']['ResponseIndicator']) 
            && $paymentResponse['PaymentResponses']['PaymentResponseType']['Response']['ResponseIndicator'] == 'A')
          {

            try{
                $transaction_id = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['TransactionID'];
                $transactionData = ['transaction_key','data'];
                 $DB->sql(
                    'SELECT transaction_key,data FROM casepad_subscription_session WHERE transaction_key = ?',
                    array('s', $transaction_id),
                    $transactionData
                );
    
                
                $transaction_id = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['TransactionID'];
                $transaction_date = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['TransactionDate'];
                $transaction_amount = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['Amount'];
                $last4 = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['Last4'];
               
                $vanReference = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['VANReference'];
              
                $last4 = str_repeat('', strlen($last4)-4) . substr($last4, -4);
              
                // Get the last invoice number
                $paymentMethod = $paymentResponse['PaymentResponses']['PaymentResponseType']['TransactionResponse']['PaymentTypeID'];
                switch ($paymentMethod) {
                  case '3': $paymentMethod = 'American Express'; break;
                  case '4': $paymentMethod = 'Visa'; break;
                  case '5': $paymentMethod = 'MasterCard'; break;
                  case '6': $paymentMethod = 'Discover'; break;
                  case '7': $paymentMethod = 'JCB'; break;
                  case 'D': $paymentMethod = 'Debit Card'; break;
                  case 'C': $paymentMethod = 'ACH'; break;
                  default:  $paymentMethod = 'Other'; break;
                }
    
                $user_id = $UserAccount->user_details['id_user'];
                $invoiceNumber = array('invoice_number');
                $invoice_number = $DB->sql(
                    'SELECT MAX(invoice_number) as invoice_number FROM casepad_payment_invoices WHERE id_owner = ?',
                    array('s', $user_id),
                    $invoiceNumber
                );
    
                $invoice_number = $invoiceNumber[0]['invoice_number'] + 1;
                $invoice_date = date("Y-m-d H:i:s", strtotime($transaction_date));
           
                $DB->sql(
                      'INSERT INTO casepad_payment_invoices (`id_owner`,`subscribed_plan_id`,`invoice_number`,`transaction_id`,`van_reference`,`last_four_digits`,`invoice_date`,`payment_method`, `total_amount`,`rate`,`hours`,`payment_response`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                      array('ssssssssssis', $user_id, $subscription_id,$invoice_number, $transaction_id, $vanReference, $last4, $invoice_date,$paymentMethod, $transaction_amount, $rate, $hours, $jsonResponse)
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