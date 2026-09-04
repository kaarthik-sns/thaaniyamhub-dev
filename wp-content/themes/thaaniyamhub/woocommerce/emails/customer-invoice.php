<?php
/**
 * Customer Invoice / Payment Request Email — Thaaniyam Hub
 * Override: yourtheme/woocommerce/emails/customer-invoice.php
 * @version 10.4.0
 */
defined( 'ABSPATH' ) || exit;

$order_number    = $order->get_order_number();
$order_date      = date_i18n( 'M d, Y', strtotime( $order->get_date_created() ) );
$subtotal        = $order->get_subtotal();
$discount_total  = $order->get_discount_total();
$has_discount    = $discount_total > 0;
$shipping_total  = $order->get_shipping_total();
$order_tax       = $order->get_total_tax();
$refunded_total  = $order->get_total_refunded();
$has_refund      = $refunded_total > 0;
$order_total_val = $order->get_total();
$net_total       = $has_refund ? ( $order_total_val - $refunded_total ) : $order_total_val;
$billing_address = $order->get_formatted_billing_address();
$shipping_address = $order->get_formatted_shipping_address();
$first_name      = $order->get_billing_first_name();
$payment_method  = $order->get_payment_method_title();
$coupon_codes    = array();
foreach ( $order->get_items( 'coupon' ) as $coupon_item ) { $coupon_codes[] = strtoupper( $coupon_item->get_code() ); }

if ( $order->needs_payment() ) {
    $cta_url   = $order->get_checkout_payment_url();
    $cta_label = 'Pay for this order &rarr;';
    $badge     = 'Payment Required';
    $headtext  = 'Your invoice is ready';
    $subtext   = 'Hi ' . $first_name . ', please complete payment for order #' . $order_number . '.';
} else {
    $cta_url   = wc_get_account_endpoint_url( 'orders' );
    $cta_label = 'View My Order &rarr;';
    $badge     = 'Invoice';
    $headtext  = 'Order Invoice #' . $order_number;
    $subtext   = 'Hi ' . $first_name . ', here is your invoice for order #' . $order_number . '.';
}

$logo_url    = 'https://yme.384.myftpupload.com/wp-content/uploads/2026/01/thaaniyam-logo.png';
$badge_label = $badge;
$headline    = $headtext;
$subheadline = $subtext;
include __DIR__ . '/email-head.php';
include __DIR__ . '/email-section-header.php';
?>
        <tr>
          <td style="background-color:#ffffff;border-left:1px solid #E0EBD8;border-right:1px solid #E0EBD8;padding:0;border-bottom:1px solid #E8F0E4;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
              <tr>
                <td width="33%" align="center" style="padding:18px 10px;border-right:1px solid #E8F0E4;vertical-align:top;"><div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#8BA888;font-family:Arial,Helvetica,sans-serif;margin-bottom:5px;">Order No.</div><div style="font-size:16px;font-weight:700;color:#1E4D2B;font-family:Arial,Helvetica,sans-serif;">#<?php echo esc_html($order_number);?></div></td>
                <td width="34%" align="center" style="padding:18px 10px;border-right:1px solid #E8F0E4;vertical-align:top;"><div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#8BA888;font-family:Arial,Helvetica,sans-serif;margin-bottom:5px;">Date</div><div style="font-size:14px;font-weight:700;color:#1E4D2B;font-family:Arial,Helvetica,sans-serif;"><?php echo esc_html($order_date);?></div></td>
                <td width="33%" align="center" style="padding:18px 10px;vertical-align:top;"><div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#8BA888;font-family:Arial,Helvetica,sans-serif;margin-bottom:5px;">Total Due</div><div style="font-size:16px;font-weight:700;color:#2D6E3E;font-family:Arial,Helvetica,sans-serif;"><?php echo wc_price($net_total);?></div></td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td class="email-body-td" style="background-color:#ffffff;border-left:1px solid #E0EBD8;border-right:1px solid #E0EBD8;padding:28px 32px;">
            <p style="font-size:11px;letter-spacing:2.5px;text-transform:uppercase;color:#8BA888;font-family:Arial,Helvetica,sans-serif;margin:0 0 18px 0;text-align:center;">Invoice Items</p>
            <table class="order-items-table" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
              <thead><tr>
                <th width="64" style="padding:0 0 10px;border-bottom:2px solid #E8F0E4;"></th>
                <th align="left" style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#8BA888;font-family:Arial,Helvetica,sans-serif;padding:0 8px 10px;border-bottom:2px solid #E8F0E4;font-weight:600;">Product</th>
                <th width="40" align="center" style="font-size:11px;color:#8BA888;font-family:Arial,Helvetica,sans-serif;padding:0 4px 10px;border-bottom:2px solid #E8F0E4;font-weight:600;">Qty</th>
                <th width="80" align="right" style="font-size:11px;color:#8BA888;font-family:Arial,Helvetica,sans-serif;padding:0 0 10px;border-bottom:2px solid #E8F0E4;font-weight:600;">Total</th>
              </tr></thead>
              <tbody>
                <?php foreach($order->get_items() as $item_id=>$item):
                  $_product=$item->get_product();$qty=$item->get_quantity();$total=$item->get_total();
                  $sku=$_product?$_product->get_sku():'';$img_src='';
                  if($_product){$img=wp_get_attachment_image_src(get_post_thumbnail_id($_product->get_id()),'thumbnail');if($img){$img_src=$img[0];}}?>
                <tr>
                  <td valign="top" style="padding:14px 8px 14px 0;border-bottom:1px solid #F4F7F0;width:64px;"><?php if($img_src):?><img src="<?php echo esc_url($img_src);?>" width="52" height="52" alt="" style="display:block;width:52px;height:52px;border-radius:8px;border:0;" /><?php else:?><div style="width:52px;height:52px;border-radius:8px;background-color:#E8F0E4;text-align:center;line-height:52px;font-size:20px;">&#127807;</div><?php endif;?></td>
                  <td valign="middle" style="padding:14px 8px;border-bottom:1px solid #F4F7F0;"><div style="font-size:14px;color:#1E3A24;font-family:Arial,Helvetica,sans-serif;font-weight:600;"><?php echo esc_html($item->get_name());?></div><?php if($sku):?><div style="font-size:11px;color:#8BA888;font-family:Arial,Helvetica,sans-serif;margin-top:3px;">SKU: <?php echo esc_html($sku);?></div><?php endif;?></td>
                  <td align="center" valign="middle" style="padding:14px 4px;border-bottom:1px solid #F4F7F0;font-size:13px;color:#5A7A5A;font-family:Arial,Helvetica,sans-serif;"><?php echo intval($qty);?></td>
                  <td align="right" valign="middle" style="padding:14px 0;border-bottom:1px solid #F4F7F0;font-size:14px;color:#2D6E3E;font-family:Arial,Helvetica,sans-serif;font-weight:700;white-space:nowrap;"><?php echo wc_price($total);?></td>
                </tr>
                <?php endforeach;?>
              </tbody>
            </table>
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin-top:14px;">
              <tr><td style="padding:6px 0;color:#8BA888;font-size:13px;font-family:Arial,Helvetica,sans-serif;">Subtotal</td><td align="right" style="padding:6px 0;color:#3A5A3A;font-size:13px;"><?php echo wc_price($subtotal);?></td></tr>
              <?php if($has_discount):?><tr><td style="padding:6px 0;color:#856404;font-size:13px;font-family:Arial,Helvetica,sans-serif;">Discount<?php foreach($coupon_codes as $code):?> <span style="background-color:#FFF3CD;border:1px solid #FFEAA7;color:#856404;border-radius:4px;padding:1px 6px;font-size:10px;font-weight:700;"><?php echo esc_html($code);?></span><?php endforeach;?></td><td align="right" style="padding:6px 0;color:#C0392B;font-size:13px;font-weight:700;">-<?php echo wc_price($discount_total);?></td></tr><?php endif;?>
              <tr><td style="padding:6px 0;color:#8BA888;font-size:13px;font-family:Arial,Helvetica,sans-serif;">Shipping</td><?php if($shipping_total>0):?><td align="right" style="padding:6px 0;color:#3A5A3A;font-size:13px;"><?php echo wc_price($shipping_total);?></td><?php else:?><td align="right" style="padding:6px 0;color:#6FCF97;font-size:13px;font-weight:700;">Free</td><?php endif;?></tr>
              <?php if($order_tax>0):?><tr><td style="padding:6px 0;color:#8BA888;font-size:13px;">Tax</td><td align="right" style="padding:6px 0;color:#3A5A3A;font-size:13px;"><?php echo wc_price($order_tax);?></td></tr><?php endif;?>
              <tr><td style="padding:12px 0 0;border-top:2px solid #E8F0E4;color:#1E3A24;font-size:15px;font-family:Arial,Helvetica,sans-serif;font-weight:700;">Total Due</td><td align="right" style="padding:12px 0 0;border-top:2px solid #E8F0E4;color:#1E4D2B;font-size:20px;font-family:Arial,Helvetica,sans-serif;font-weight:700;"><?php echo wc_price($net_total);?></td></tr>
            </table>
          </td>
        </tr>
        <tr>
          <td align="center" style="background-color:#ffffff;border-left:1px solid #E0EBD8;border-right:1px solid #E0EBD8;border-top:1px solid #F0F5EC;padding:24px 32px;">
            <table cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin:0 auto;"><tr><td align="center" bgcolor="#1E4D2B" style="background-color:#1E4D2B;border-radius:6px;padding:14px 36px;"><a href="<?php echo esc_url($cta_url);?>" style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#ffffff;text-decoration:none;"><?php echo $cta_label;?></a></td></tr></table>
          </td>
        </tr>
        <tr><td class="email-section-td" style="background-color:#ffffff;border-left:1px solid #E0EBD8;border-right:1px solid #E0EBD8;border-top:1px solid #F0F5EC;padding:24px 32px;"><div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#8BA888;font-family:Arial,Helvetica,sans-serif;margin-bottom:6px;">Payment Method</div><div style="font-size:13px;color:#3A5A3A;font-family:Arial,Helvetica,sans-serif;"><?php echo esc_html($payment_method);?></div></td></tr>
        <tr><td class="email-section-td" style="background-color:#ffffff;border-left:1px solid #E0EBD8;border-right:1px solid #E0EBD8;border-top:1px solid #F0F5EC;padding:24px 32px;"><div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#8BA888;font-family:Arial,Helvetica,sans-serif;margin-bottom:8px;">Billing Address</div><div style="font-size:13px;color:#3A5A3A;line-height:1.75;font-family:Arial,Helvetica,sans-serif;"><?php echo wp_kses_post($billing_address?:'&#8212;');?></div></td></tr>
        <?php if($shipping_address&&$shipping_address!==$billing_address):?><tr><td class="email-section-td" style="background-color:#ffffff;border-left:1px solid #E0EBD8;border-right:1px solid #E0EBD8;border-top:1px solid #F0F5EC;padding:24px 32px;"><div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#8BA888;font-family:Arial,Helvetica,sans-serif;margin-bottom:8px;">Shipping Address</div><div style="font-size:13px;color:#3A5A3A;line-height:1.75;font-family:Arial,Helvetica,sans-serif;"><?php echo wp_kses_post($shipping_address);?></div></td></tr><?php endif;?>
<?php include __DIR__ . '/email-foot.php'; ?>
