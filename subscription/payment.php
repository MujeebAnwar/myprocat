<?php
require_once ('config.php');
require_once DOCUMENT_ROOT.'/setup/start.php';
$breadcrumb_items = array('Home');

if(is_null($UserAccount) || !is_a($UserAccount,'useraccount') || !$UserAccount->logged_in)
{
        // $set_body = array(
        // 	new row(
        // 		new paragraph('Please log in to see our private resource rooms',array('class'=>'importantmessage'))
        // 		,array('class' => 'whitebox')),
        // 	new roomlist($UserAccount,$DB)
        // 	);

	header('Location: login.php');
	exit;
} else {
        $isUserHaveSubscription = false;
        $subscriptionData = array('count');
        $DB->sql(
            'SELECT COUNT(*) as count FROM casepad_subscribed_plan WHERE id_owner = ? AND is_active = 1',
            array('s', $UserAccount->user_details['id_user']),
            $subscriptionData
        );
        if(isset($subscriptionData[0]) && is_array($subscriptionData[0]) && isset($subscriptionData[0]['count']) && $subscriptionData[0]['count'] > 0) {
            $isUserHaveSubscription = true;
        }
        if($isUserHaveSubscription) {
            header('Location: my_subscription.php');
            exit;
        }
	require_once ('subscription.php');
}
// Include the subscription page which uses the component-based structure
?>
