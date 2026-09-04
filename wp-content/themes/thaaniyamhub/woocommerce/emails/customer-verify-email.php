<?php
/**
 * Customer Verify Email - Thaaniyam Hub
 * Override: yourtheme/woocommerce/emails/customer-verify-email.php
 * @version 11.0.0
 */
defined( 'ABSPATH' ) || exit;

$logo_url    = 'https://yme.384.myftpupload.com/wp-content/uploads/2026/01/thaaniyam-logo.png';
$badge_label = 'Verification';
$headline    = ! empty( $email_heading ) ? $email_heading : 'Confirm your email address';
$subheadline = 'Verify your email to secure your account and link your orders.';
include __DIR__ . '/email-head.php';
include __DIR__ . '/email-section-header.php';
?>
        <tr>
          <td class="email-body-td" style="background-color:#ffffff;border-left:1px solid #E0EBD8;border-right:1px solid #E0EBD8;padding:28px 32px;">
            <p style="font-size:14px;color:#3A5A3A;font-family:Arial,Helvetica,sans-serif;line-height:1.7;margin:0 0 16px;">Hi <strong><?php echo esc_html($user_display_name); ?></strong>,</p>
            <p style="font-size:14px;color:#3A5A3A;font-family:Arial,Helvetica,sans-serif;line-height:1.7;margin:0 0 16px;">
              Once you've confirmed that <strong><?php echo esc_html( $user_email ); ?></strong> is your email address, we'll link any past orders to your account.
            </p>
            
            <table cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;"><tr><td align="center" style="padding:8px 0 24px;">
              <table cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin:0 auto;"><tr><td align="center" bgcolor="#1E4D2B" style="background-color:#1E4D2B;border-radius:6px;padding:14px 36px;"><a href="<?php echo esc_url( $verify_url ); ?>" style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#ffffff;text-decoration:none;">Confirm email address</a></td></tr></table>
            </td></tr></table>
            
            <p style="font-size:13px;color:#8BA888;font-family:Arial,Helvetica,sans-serif;line-height:1.6;margin:0 0 16px;">
              If you didn't request this email, there's nothing to worry about, and you can safely ignore it.
            </p>
            
            <?php if ( ! empty( $additional_content ) ) : ?>
                <div style="margin-top: 20px; border-top: 1px solid #F0F5EC; padding-top: 20px; font-size: 13px; color: #3A5A3A; font-family: Arial,Helvetica,sans-serif; line-height: 1.6;">
                    <?php echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) ); ?>
                </div>
            <?php endif; ?>
          </td>
        </tr>
<?php include __DIR__ . '/email-foot.php'; ?>
