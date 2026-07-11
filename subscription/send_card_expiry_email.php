<?php
// Include required files first
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once ('plans_config.php');
require_once (DOCUMENT_ROOT.'/Service/EmailService.php');


$subscriptions = array('id', 'card_expiry_date','email','first_name','mid_name','last_name','last_four_digits');
$DB->sql(
    'SELECT id, card_expiry_date, email, first_name, mid_name, last_name, last_four_digits
    FROM casepad_subscribed_plan
    LEFT JOIN accounts ON casepad_subscribed_plan.id_owner = accounts.id_user
    WHERE card_expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND card_expiry_date >= CURDATE()',
    array(),
    $subscriptions
);
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
// Remove subscription directory from path and add paymentSdk
$basePath = dirname($_SERVER['PHP_SELF']);
$redirectUrl = $protocol . '://' . $host . $basePath . '/update_card.php';



$emailService = new EmailService();
foreach($subscriptions as $subscription){
    $body = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:600px; margin:0 auto; background:#ffffff;">
      <tr>
        <td style="padding:28px 24px 10px 24px; text-align:center; border-bottom:1px solid #e9ecef;">
          <!-- Brand header with logo -->
          <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 auto;">
            <tr>
              <td style="text-align:center;">
                <!-- Inline SVG logo to avoid external requests in email clients -->
                <img style="height: 50px;" src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSOHTAekTL0-95EVIkVT_P3GW3_PDElWqr83A&s" alt="">
                <div style="margin-top:10px; color:#666; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:18px;">
                  Calabasas, CA 91302<br>
                  (818) 222-6600<br>
                  <a href="mailto:support@depodash.com" style="color:#1a365d; text-decoration:none;">support@depodash.com</a>
                </div>
              </td>
            </tr>
            <tr>
              <td style="padding:22px 24px 0 24px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                  <tr>
                    <td style="vertical-align:top;">
                      <div style="font-family:Arial, Helvetica, sans-serif; color:#111; font-size:20px; font-weight:700; line-height:1.3;">Hello ' . $subscription['first_name'] . ' ' . $subscription['mid_name'] . ' ' . $subscription['last_name'] . '</div>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td style="padding:22px 24px 0 24px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                  <tr>
                    <td style="vertical-align:top;">
                      <div style="font-family:Arial, Helvetica, sans-serif; color:#111; font-size:20px; font-weight:700; line-height:1.3;">Your card ending in ************' . $subscription['last_four_digits'] . ' will expire on ' . $subscription['card_expiry_date'] . '. Please update your payment method to avoid any interruption to your subscription</div>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td style="padding:22px 24px 0 24px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                  <tr>
                    <td style="vertical-align:top;">
                      <div><a href="'.$redirectUrl.'" style="background-color: #ff6600; color: #fff;
    border: none; padding: 10px 20px; border-radius: 5px; text-decoration: none;">Update Card</a></div>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
                <td style="padding:20px 24px 10px 24px;">
                <div style="font-family:Arial, Helvetica, sans-serif; font-size:14px; color:#111; line-height:22px;">
                    If you have any questions or need assistance, our support team is here to help.
                    Simply reply to this email or contact us at <a href="mailto:support@depodash.com" style="color:#ff6600; text-decoration:none;">support@depodash.com</a>.
                </div>
                <div style="height:8px; line-height:8px; font-size:8px;">&nbsp;</div>
                <div style="font-family:Arial, Helvetica, sans-serif; font-size:14px; color:#111; line-height:22px;">
                    We appreciate your trust in DepoDash and look forward to supporting your transcription and reporting needs.
                </div>
                <div style="height:16px; line-height:16px; font-size:16px;">&nbsp;</div>
                <div style="font-family:Arial, Helvetica, sans-serif; font-size:14px; color:#111; line-height:22px;">
                    Best regards,<br>
                    The DepoDash Team
                </div>
                <div style="height:24px; line-height:24px; font-size:24px;">&nbsp;</div>
                </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>';
    $emailService->send($subscription['email'], 'Card Expiry Notification', $body,false);
}
?>
