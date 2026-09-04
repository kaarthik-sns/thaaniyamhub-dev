<?php
/**
 * Admin Payment Gateway Enabled Email — Thaaniyam Hub
 * Override: yourtheme/woocommerce/emails/admin-payment-gateway-enabled.php
 * @version 10.4.0
 */
defined( 'ABSPATH' ) || exit;

$logo_url    = 'https://yme.384.myftpupload.com/wp-content/uploads/2026/01/thaaniyam-logo.png';
$badge_label = 'Gateway Enabled';
$headline    = isset( $gateway ) ? 'Payment Gateway Enabled: ' . esc_html( $gateway->get_title() ) : 'Payment Gateway Enabled';
$subheadline = '';
include __DIR__ . '/email-head.php';
include __DIR__ . '/email-section-header.php';
?>
        <tr>
          <td class="email-body-td" style="background-color:#ffffff;border-left:1px solid #E0EBD8;border-right:1px solid #E0EBD8;padding:28px 32px;">
            <?php if ( isset( $gateway ) ) : ?>
            <div style="background-color:#F4FAF5;border-left:4px solid #2D6E3E;border-radius:6px;padding:16px 20px;">
              <p style="font-size:14px;color:#1E3A24;font-family:Arial,Helvetica,sans-serif;line-height:1.7;margin:0;">The <strong><?php echo esc_html( $gateway->get_title() ); ?></strong> payment gateway has been enabled on your WooCommerce store. This email is to inform you of this change.</p>
            </div>
            <?php endif; ?>
          </td>
        </tr>
<?php include __DIR__ . '/email-foot.php'; ?>
