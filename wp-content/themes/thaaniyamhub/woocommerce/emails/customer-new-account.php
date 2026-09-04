<?php
/**
 * Customer New Account Email — Thaaniyam Hub
 * Override: yourtheme/woocommerce/emails/customer-new-account.php
 * @version 10.4.0
 */
defined( 'ABSPATH' ) || exit;

$first_name  = $user_display_name ?? $user_login;
$my_account  = get_permalink( get_option('woocommerce_myaccount_page_id') );

$logo_url    = 'https://yme.384.myftpupload.com/wp-content/uploads/2026/01/thaaniyam-logo.png';
$badge_label = 'Welcome';
$headline    = 'Welcome to Thaaniyam Hub!';
$subheadline = 'Your account has been created. Start exploring our natural millet products.';
include __DIR__ . '/email-head.php';
include __DIR__ . '/email-section-header.php';
?>
        <tr>
          <td class="email-body-td" style="background-color:#ffffff;border-left:1px solid #E0EBD8;border-right:1px solid #E0EBD8;padding:28px 32px;">
            <p style="font-size:14px;color:#3A5A3A;font-family:Arial,Helvetica,sans-serif;line-height:1.7;margin:0 0 16px;">Hi <strong><?php echo esc_html($first_name); ?></strong>,</p>
            <p style="font-size:14px;color:#3A5A3A;font-family:Arial,Helvetica,sans-serif;line-height:1.7;margin:0 0 16px;">Your new account has been created at Thaaniyam Hub with the username <strong><?php echo esc_html( $user_login ); ?></strong>.</p>
            <?php if ( $password_generated ) : ?>
            <div style="background-color:#F4FAF5;border-left:4px solid #2D6E3E;border-radius:6px;padding:16px 20px;margin-bottom:20px;">
              <p style="font-size:13px;color:#1E3A24;font-family:Arial,Helvetica,sans-serif;line-height:1.6;margin:0;">Your temporary password: <strong><?php echo esc_html( $user_pass ); ?></strong><br/><span style="color:#8BA888;font-size:12px;">Please change your password after logging in.</span></p>
            </div>
            <?php endif; ?>
            <table cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;"><tr><td align="center" style="padding:8px 0;">
              <table cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin:0 auto;"><tr><td align="center" bgcolor="#1E4D2B" style="background-color:#1E4D2B;border-radius:6px;padding:14px 36px;"><a href="<?php echo esc_url($my_account); ?>" style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#ffffff;text-decoration:none;">Go to My Account &rarr;</a></td></tr></table>
            </td></tr></table>
          </td>
        </tr>
<?php include __DIR__ . '/email-foot.php'; ?>
