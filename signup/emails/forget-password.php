<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Password Recovery</title>
</head>
<body style="margin:0; padding:0; background-color:#f5f7fa;">
	<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f5f7fa;">
		<tr>
			<td style="padding:24px 12px;">
				<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:600px; margin:0 auto; background:#ffffff; border-radius:8px;">
					<tr>
						<td style="padding:28px 24px 16px 24px; text-align:center; border-bottom:1px solid #e9ecef;">
							<div style="font-family:Arial, Helvetica, sans-serif; color:#27475f; font-size:22px; font-weight:700;">MyProCAT Password Recovery</div>
						</td>
					</tr>
					<tr>
						<td style="padding:28px 24px; font-family:Arial, Helvetica, sans-serif; color:#333; font-size:14px; line-height:22px;">
							<p style="margin:0 0 16px 0;">A password reset was requested for your account.</p>
							<p style="margin:0 0 16px 0;">To reset your password, click the button below:</p>
							<p style="margin:0 0 24px 0; text-align:center;">
								<a href="{$safeUrl}" style="display:inline-block; background:#ff6600; color:#ffffff; text-decoration:none; font-weight:600; padding:12px 24px; border-radius:6px;">Reset Password</a>
							</p>
							<p style="margin:0 0 8px 0;">Or copy and paste this link into your browser:</p>
							<p style="margin:0 0 20px 0; word-break:break-all;"><a href="{$safeUrl}" style="color:#1a365d;">{$safeUrl}</a></p>
							<p style="margin:0 0 8px 0;">You can also use this recovery token in the password recovery form:</p>
							<p style="margin:0 0 24px 0; font-size:16px; font-weight:700; letter-spacing:0.5px; color:#27475f;">{$safeToken}</p>
							<p style="margin:0 0 8px 0;">If you did not request a password reset, please contact tech support.</p>
							<p style="margin:0;">Do not reply to this e-mail. Contact support at <a href="mailto:{$safeSupport}" style="color:#1a365d; text-decoration:none;">{$safeSupport}</a>.</p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>