<?php if (empty($invoiceEmbedView)): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyProCat Invoice Email</title>
    <!-- Inline styles only for email client compatibility -->
</head>
<body style="margin:0; padding:0; background-color:#f5f7fa;">
<?php endif; ?>
  <center style="width:100%; background:#f5f7fa;">
    <!--[if mso]>
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600"><tr><td>
    <![endif]-->
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:600px; margin:0 auto; background:#ffffff;">
      <tr>
        <td style="padding:28px 24px 10px 24px; text-align:center; border-bottom:1px solid #e9ecef;">
          <!-- Brand header with logo -->
          <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 auto;">
            <tr>
              <td style="text-align:center;">
                <!-- Inline SVG logo to avoid external requests in email clients -->
                <img style="height: 50px;" src="<?php $siteUrl = 'https://' . $_SERVER['HTTP_HOST']; echo $siteUrl . '/templateV2/mainframe/img/logo.png'; ?>" alt="">
              </td>
            </tr>
          </table>
        </td>
      </tr>

      <!-- Invoice meta -->
      <tr>
        <td style="padding:22px 24px 0 24px;">
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr>
              <td style="vertical-align:top;">
                <div style="font-family:Arial, Helvetica, sans-serif; color:#111; font-size:20px; font-weight:700; line-height:1.3;">Order Number: <?php echo htmlspecialchars($invoiceData['invoice_number'] ?? 'N/A'); ?></div>
                <div style="height:6px; line-height:6px; font-size:6px;">&nbsp;</div>
                <div style="font-family:Arial, Helvetica, sans-serif; color:#444; font-size:14px;">Order Date: <?php echo htmlspecialchars($invoiceData['invoice_date'] ?? 'N/A'); ?></div>
              </td>
            </tr>
          </table>
        </td>
      </tr>

      <!-- Bill To section -->
      <tr>
        <td style="padding:22px 24px 6px 24px;">
          <div style="font-family:Arial, Helvetica, sans-serif; color:#111; font-size:18px; font-weight:700; margin-bottom:6px;">BILL TO:</div>
          <div style="font-family:Arial, Helvetica, sans-serif; color:#333; font-size:14px; line-height:22px;">
            <?php
            $addressDetails = is_array($invoiceData['address_details'] ?? null) ? $invoiceData['address_details'] : array();
            $locationParts = array();
            if (!empty($addressDetails['City'])) {
                $locationParts[] = $addressDetails['City'];
            }
            if (!empty($addressDetails['State'])) {
                $locationParts[] = $addressDetails['State'];
            }
            if (!empty($addressDetails['ZipCode'])) {
                $locationParts[] = $addressDetails['ZipCode'];
            }
            ?>
            <?php echo htmlspecialchars($invoiceData['customer_name'] ?? 'N/A'); ?><br>
            <?php echo htmlspecialchars($addressDetails['AddressLine1'] ?? ''); ?><br>
            <?php if (!empty($locationParts)): ?>
            <?php echo htmlspecialchars(implode(', ', $locationParts)); ?><br>
            <?php endif; ?>
            <?php echo htmlspecialchars($addressDetails['Country'] ?? ''); ?><br>
          </div>
        </td>
      </tr>

      <!-- Line items table -->
      <tr>
        <td style="padding:18px 0 0 0;">
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
            <tr>
              <td style="padding:0 24px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                  <tr>
                    <td colspan="3" style="background:#27475f; color:#ffffff; font-family:Arial, Helvetica, sans-serif; font-size:18px; font-weight:700; padding:12px 16px; border-top-left-radius:6px;">Service DESCRIPTION</td>
                    <td style="width:90px; background:#27475f; color:#ffffff; font-family:Arial, Helvetica, sans-serif; font-size:16px; font-weight:700; text-align:center; padding:12px 8px;">Hours</td>
                    <td style="width:90px; background:#27475f; color:#ffffff; font-family:Arial, Helvetica, sans-serif; font-size:16px; font-weight:700; text-align:center; padding:12px 8px; border-top-right-radius:6px;">Rate</td>
                  </tr>
                  <!-- Item row -->
                  <tr>
                    <td colspan="3" style="font-family:Arial, Helvetica, sans-serif; font-size:14px; color:#1a365d; padding:14px 16px; border-left:1px solid #e9ecef; border-right:1px solid #e9ecef;"><?php
                      $serviceDescription = !empty($invoiceData['service_description'])
                        ? $invoiceData['service_description']
                        : 'Transcription (hours)';
                      // Normalize fancy dashes / mojibake so email clients don't show "â€“".
                      $serviceDescription = str_replace(
                        array("\xE2\x80\x93", "\xE2\x80\x94", 'â€“', 'â€”', '–', '—'),
                        ' - ',
                        $serviceDescription
                      );
                      echo nl2br(htmlspecialchars($serviceDescription, ENT_QUOTES, 'UTF-8'));
                    ?></td>
                    <td style="font-family:Arial, Helvetica, sans-serif; font-size:14px; color:#1a365d; text-align:center; padding:14px 8px; border-right:1px solid #e9ecef;"><?php echo htmlspecialchars($invoiceData['hours'] ?? '0'); ?></td>
                    <td style="font-family:Arial, Helvetica, sans-serif; font-size:14px; color:#1a365d; text-align:center; padding:14px 8px; border-right:1px solid #e9ecef;"><?php echo htmlspecialchars($invoiceData['rate'] ?? '0.00'); ?></td>
                  </tr>
                  <!-- Empty spacer rows for additional items -->
                  <tr>
                    <td colspan="5" style="height:44px; border-left:1px solid #e9ecef; border-right:1px solid #e9ecef; border-top:1px solid #f2f4f7;">&nbsp;</td>
                  </tr>
                  <tr>
                    <td colspan="5" style="height:44px; border-left:1px solid #e9ecef; border-right:1px solid #e9ecef; border-top:1px solid #f2f4f7;">&nbsp;</td>
                  </tr>
                  <tr>
                    <td colspan="5" style="height:44px; border-left:1px solid #e9ecef; border-right:1px solid #e9ecef; border-top:1px solid #f2f4f7; border-bottom:1px solid #e9ecef;">&nbsp;</td>
                  </tr>
                
                  <!-- Discount row -->
                  <?php if(isset($invoiceData['discount']) && $invoiceData['discount'] > 0): ?>
                   <!-- Subtotal row -->
                    <tr>
                      <td colspan="3" style="padding:0 16px 0 16px; background:#27475f; color:#ffffff; font-family:Arial, Helvetica, sans-serif; font-size:16px; font-weight:700; height:40px; line-height:40px;">SUBTOTAL :</td>
                      <td colspan="2" style="padding:0 16px; background:#27475f; color:#ffffff; font-family:Arial, Helvetica, sans-serif; font-size:18px; font-weight:700; text-align:right; height:40px; line-height:40px;">$<?php echo htmlspecialchars(number_format($invoiceData['total_amount'] + $invoiceData['discount'] ?? 0, 2)); ?></td>
                    </tr>
                    <tr>
                      <td colspan="3" style="padding:0 16px 0 16px; background:#27475f; color:#ffffff; font-family:Arial, Helvetica, sans-serif; font-size:16px; font-weight:700; height:40px; line-height:40px;">DISCOUNT :</td>
                      <td colspan="2" style="padding:0 16px; background:#27475f; color:#ffffff; font-family:Arial, Helvetica, sans-serif; font-size:18px; font-weight:700; text-align:right; height:40px; line-height:40px;"> -  $<?php echo htmlspecialchars(number_format($invoiceData['discount'] ?? 0, 2)); ?></td>
                    </tr>
                  <?php endif; ?>
                  <!-- Total row -->
                  <tr>
                    <td colspan="3" style="padding:0 16px 0 16px; background:#27475f; color:#ffffff; font-family:Arial, Helvetica, sans-serif; font-size:16px; font-weight:700; height:40px; line-height:40px; border-bottom-left-radius:6px;">TOTAL :</td>
                    <td colspan="2" style="padding:0 16px; background:#27475f; color:#ffffff; font-family:Arial, Helvetica, sans-serif; font-size:18px; font-weight:700; text-align:right; height:40px; line-height:40px; border-bottom-right-radius:6px;">$<?php echo htmlspecialchars(number_format($invoiceData['total_amount'] ?? 0, 2)); ?></td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>
        </td>
      </tr>

      <!-- Footer copy -->
      <tr>
        <td style="padding:20px 24px 10px 24px;">
          <div style="font-family:Arial, Helvetica, sans-serif; font-size:14px; color:#111; line-height:22px;">
            If you have any questions or need assistance, our support team is here to help.
            Simply reply to this email or contact us at <a href="mailto:cs@myprocat.com" style="color:#ff6600; text-decoration:none;">cs@myprocat.com</a>.
          </div>
          <div style="height:8px; line-height:8px; font-size:8px;">&nbsp;</div>
          <div style="font-family:Arial, Helvetica, sans-serif; font-size:14px; color:#111; line-height:22px;">
            We appreciate your trust in MyProCat and look forward to supporting your transcription and reporting needs.
          </div>
          <div style="height:16px; line-height:16px; font-size:16px;">&nbsp;</div>
          <div style="font-family:Arial, Helvetica, sans-serif; font-size:14px; color:#111; line-height:22px;">
            Best regards,<br>
            The MyProCat Team
          </div>
          <div style="height:24px; line-height:24px; font-size:24px;">&nbsp;</div>
        </td>
      </tr>
    </table>
    <!--[if mso]></td></tr></table><![endif]-->
  </center>
<?php if (empty($invoiceEmbedView)): ?>
</body>
</html>
<?php endif; ?>