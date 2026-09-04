<?php
/**
 * Customer Failed Order Email — Thaaniyam Hub
 * Override: yourtheme/woocommerce/emails/customer-failed-order.php
 * @version 10.4.0
 */
defined( 'ABSPATH' ) || exit;

$order_number    = $order->get_order_number();
$order_date      = date_i18n( 'M d, Y', strtotime( $order->get_date_created() ) );
$order_total_val = $order->get_total();
$first_name      = $order->get_billing_first_name();
$payment_method  = $order->get_payment_method_title();
$view_order_url  = $order->get_checkout_payment_url();

$logo_url    = 'https://yme.384.myftpupload.com/wp-content/uploads/2026/01/thaaniyam-logo.png';
$badge_label = 'Payment Failed';
$headline    = 'Your payment has failed';
$subheadline = 'Hi ' . $first_name . ', we were unable to process your payment for order #' . $order_number . '.';
include __DIR__ . '/email-head.php';
include __DIR__ . '/email-section-header.php';
?>
        <tr>
          <td style="background-color:#ffffff;border-left:1px solid #E0EBD8;border-right:1px solid #E0EBD8;padding:0;border-bottom:1px solid #E8F0E4;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
              <tr>
                <td width="50%" align="center" style="padding:18px 10px;border-right:1px solid #E8F0E4;vertical-align:top;"><div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#8BA888;font-family:Arial,Helvetica,sans-serif;margin-bottom:5px;">Order No.</div><div style="font-size:16px;font-weight:700;color:#1E4D2B;font-family:Arial,Helvetica,sans-serif;">#<?php echo esc_html($order_number);?></div></td>
                <td width="50%" align="center" style="padding:18px 10px;vertical-align:top;"><div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#8BA888;font-family:Arial,Helvetica,sans-serif;margin-bottom:5px;">Total</div><div style="font-size:16px;font-weight:700;color:#C0392B;font-family:Arial,Helvetica,sans-serif;"><?php echo wc_price($order_total_val);?></div></td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td class="email-body-td" style="background-color:#ffffff;border-left:1px solid #E0EBD8;border-right:1px solid #E0EBD8;padding:28px 32px;">
            <div style="background-color:#FDECEA;border-left:4px solid #C0392B;border-radius:6px;padding:16px 20px;margin-bottom:24px;">
              <p style="font-size:14px;color:#7B3030;font-family:Arial,Helvetica,sans-serif;line-height:1.6;margin:0;">Your payment of <strong><?php echo wc_price($order_total_val); ?></strong> via <strong><?php echo esc_html($payment_method); ?></strong> could not be processed. Please try again using the button below.</p>
            </div>
            <table cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;"><tr><td align="center">
              <table cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin:0 auto;"><tr><td align="center" bgcolor="#C0392B" style="background-color:#C0392B;border-radius:6px;padding:14px 36px;"><a href="<?php echo esc_url($view_order_url);?>" style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#ffffff;text-decoration:none;">Pay Now &rarr;</a></td></tr></table>
            </td></tr></table>
          </td>
        </tr>
<?php include __DIR__ . '/email-foot.php'; ?>
