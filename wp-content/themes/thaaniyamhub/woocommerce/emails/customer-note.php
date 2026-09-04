<?php
/**
 * Customer Note Email — Thaaniyam Hub
 * Override: yourtheme/woocommerce/emails/customer-note.php
 * @version 10.4.0
 */
defined( 'ABSPATH' ) || exit;

$order_number  = $order->get_order_number();
$first_name    = $order->get_billing_first_name();
$view_order_url = wc_get_account_endpoint_url( 'orders' );

$logo_url    = 'https://yme.384.myftpupload.com/wp-content/uploads/2026/01/thaaniyam-logo.png';
$badge_label = 'Note Added';
$headline    = 'A note has been added to your order';
$subheadline = 'Hi ' . $first_name . ', here is a note for your order #' . $order_number . '.';
include __DIR__ . '/email-head.php';
include __DIR__ . '/email-section-header.php';
?>
        <tr>
          <td class="email-body-td" style="background-color:#ffffff;border-left:1px solid #E0EBD8;border-right:1px solid #E0EBD8;padding:28px 32px;">
            <div style="background-color:#F4FAF5;border-left:4px solid #2D6E3E;border-radius:6px;padding:20px 24px;">
              <p style="font-size:14px;color:#1E3A24;font-family:Arial,Helvetica,sans-serif;line-height:1.7;margin:0;"><?php echo wp_kses_post( $customer_note ); ?></p>
            </div>
            <p style="font-size:13px;color:#8BA888;font-family:Arial,Helvetica,sans-serif;margin:16px 0 0;text-align:center;">Order #<?php echo esc_html($order_number);?></p>
          </td>
        </tr>
        <tr>
          <td align="center" style="background-color:#ffffff;border-left:1px solid #E0EBD8;border-right:1px solid #E0EBD8;border-top:1px solid #F0F5EC;padding:24px 32px;">
            <table cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin:0 auto;"><tr><td align="center" bgcolor="#1E4D2B" style="background-color:#1E4D2B;border-radius:6px;padding:14px 36px;"><a href="<?php echo esc_url($view_order_url);?>" style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#ffffff;text-decoration:none;">View My Order &rarr;</a></td></tr></table>
          </td>
        </tr>
<?php include __DIR__ . '/email-foot.php'; ?>
