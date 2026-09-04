<?php
/**
 * Customer Reset Password Email — Thaaniyam Hub
 * Override: yourtheme/woocommerce/emails/customer-reset-password.php
 * @version 10.4.0
 */
defined( 'ABSPATH' ) || exit;

$logo_url    = 'https://yme.384.myftpupload.com/wp-content/uploads/2026/01/thaaniyam-logo.png';
$badge_label = 'Password Reset';
$headline    = 'Reset your password';
$subheadline = 'Click the button below to reset your Thaaniyam Hub password.';
include __DIR__ . '/email-head.php';
include __DIR__ . '/email-section-header.php';
?>
        <tr>
          <td class="email-body-td" style="background-color:#ffffff;border-left:1px solid #E0EBD8;border-right:1px solid #E0EBD8;padding:28px 32px;">
            <p style="font-size:14px;color:#3A5A3A;font-family:Arial,Helvetica,sans-serif;line-height:1.7;margin:0 0 20px;">
              Someone requested a password reset for your account (<strong><?php echo esc_html( $user_login ); ?></strong>). If this was you, click the button below. If not, you can ignore this email.
            </p>
            <table cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;"><tr><td align="center" style="padding:8px 0 24px;">
              <table cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin:0 auto;"><tr><td align="center" bgcolor="#1E4D2B" style="background-color:#1E4D2B;border-radius:6px;padding:14px 36px;"><a href="<?php echo esc_url( $reset_link ); ?>" style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#ffffff;text-decoration:none;">Reset My Password</a></td></tr></table>
            </td></tr></table>
            <p style="font-size:12px;color:#8BA888;font-family:Arial,Helvetica,sans-serif;line-height:1.6;margin:0;text-align:center;">This link will expire in 24 hours. If you did not request a password reset, please ignore this email.</p>
          </td>
        </tr>
<?php include __DIR__ . '/email-foot.php'; ?>
