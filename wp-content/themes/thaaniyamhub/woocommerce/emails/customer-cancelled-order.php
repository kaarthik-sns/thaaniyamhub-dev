<?php
/**
 * Customer Cancelled Order Email — Thaaniyam Hub
 * Override: yourtheme/woocommerce/emails/customer-cancelled-order.php
 * @version 9.8.0
 */
defined( 'ABSPATH' ) || exit;

$order_number    = $order->get_order_number();
$order_date      = date_i18n( 'M d, Y', strtotime( $order->get_date_created() ) );
$subtotal        = $order->get_subtotal();
$discount_total  = $order->get_discount_total();
$has_discount    = $discount_total > 0;
$shipping_total  = $order->get_shipping_total();
$order_tax       = $order->get_total_tax();
$order_total_val = $order->get_total();
$billing_address = $order->get_formatted_billing_address();
$shipping_address = $order->get_formatted_shipping_address();
$first_name      = $order->get_billing_first_name();
$payment_method  = $order->get_payment_method_title();
$view_order_url  = wc_get_account_endpoint_url( 'orders' );
$coupon_codes    = array();
foreach ( $order->get_items( 'coupon' ) as $coupon_item ) {
    $coupon_codes[] = strtoupper( $coupon_item->get_code() );
}

$logo_url    = 'https://yme.384.myftpupload.com/wp-content/uploads/2026/01/thaaniyam-logo.png';
$badge_label = 'Order Cancelled';
$headline    = 'Your order has been cancelled';
$subheadline = 'Hi ' . $first_name . ', your order #' . $order_number . ' has been cancelled.';
include __DIR__ . '/email-head.php';
include __DIR__ . '/email-section-header.php';
?>
        <tr>
          <td style="background-color:#ffffff;border-left:1px solid #E0EBD8;border-right:1px solid #E0EBD8;padding:0;border-bottom:1px solid #E8F0E4;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
              <tr>
                <td width="33%" align="center" style="padding:18px 10px;border-right:1px solid #E8F0E4;vertical-align:top;"><div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#8BA888;font-family:Arial,Helvetica,sans-serif;margin-bottom:5px;">Order No.</div><div style="font-size:16px;font-weight:700;color:#1E4D2B;font-family:Arial,Helvetica,sans-serif;">#<?php echo esc_html($order_number);?></div></td>
                <td width="34%" align="center" style="padding:18px 10px;border-right:1px solid #E8F0E4;vertical-align:top;"><div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#8BA888;font-family:Arial,Helvetica,sans-serif;margin-bottom:5px;">Date</div><div style="font-size:14px;font-weight:700;color:#1E4D2B;font-family:Arial,Helvetica,sans-serif;"><?php echo esc_html($order_date);?></div></td>
                <td width="33%" align="center" style="padding:18px 10px;vertical-align:top;"><div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#8BA888;font-family:Arial,Helvetica,sans-serif;margin-bottom:5px;">Total</div><div style="font-size:16px;font-weight:700;color:#C0392B;font-family:Arial,Helvetica,sans-serif;"><?php echo wc_price($order_total_val);?></div></td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td class="email-body-td" style="background-color:#ffffff;border-left:1px solid #E0EBD8;border-right:1px solid #E0EBD8;padding:28px 32px;">
            <p style="font-size:11px;letter-spacing:2.5px;text-transform:uppercase;color:#8BA888;font-family:Arial,Helvetica,sans-serif;margin:0 0 18px 0;text-align:center;">Cancelled Items</p>
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
                  <td valign="top" style="padding:14px 8px 14px 0;border-bottom:1px solid #F4F7F0;width:64px;"><?php if($img_src):?><img src="<?php echo esc_url($img_src);?>" width="52" height="52" alt="" style="display:block;width:52px;height:52px;border-radius:8px;border:0;" /><?php else:?><div style="width:52px;height:52px;border-radius:8px;background-color:#F4E4E4;text-align:center;line-height:52px;font-size:20px;">&#128683;</div><?php endif;?></td>
                  <td valign="middle" style="padding:14px 8px;border-bottom:1px solid #F4F7F0;"><div style="font-size:14px;color:#7B3030;font-family:Arial,Helvetica,sans-serif;font-weight:600;text-decoration:line-through;"><?php echo esc_html($item->get_name());?></div><?php if($sku):?><div style="font-size:11px;color:#8BA888;font-family:Arial,Helvetica,sans-serif;margin-top:3px;">SKU: <?php echo esc_html($sku);?></div><?php endif;?></td>
                  <td align="center" valign="middle" style="padding:14px 4px;border-bottom:1px solid #F4F7F0;font-size:13px;color:#5A7A5A;font-family:Arial,Helvetica,sans-serif;"><?php echo intval($qty);?></td>
                  <td align="right" valign="middle" style="padding:14px 0;border-bottom:1px solid #F4F7F0;font-size:14px;color:#C0392B;font-family:Arial,Helvetica,sans-serif;font-weight:700;white-space:nowrap;"><?php echo wc_price($total);?></td>
                </tr>
                <?php endforeach;?>
              </tbody>
            </table>
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin-top:14px;">
              <tr><td style="padding:12px 0 0;border-top:2px solid #E8F0E4;color:#1E3A24;font-size:15px;font-family:Arial,Helvetica,sans-serif;font-weight:700;">Order Total</td><td align="right" style="padding:12px 0 0;border-top:2px solid #E8F0E4;color:#C0392B;font-size:20px;font-family:Arial,Helvetica,sans-serif;font-weight:700;"><?php echo wc_price($order_total_val);?></td></tr>
            </table>
          </td>
        </tr>
        <tr>
          <td class="email-section-td" style="background-color:#ffffff;border-left:1px solid #E0EBD8;border-right:1px solid #E0EBD8;border-top:1px solid #F0F5EC;padding:24px 32px;">
            <div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#8BA888;font-family:Arial,Helvetica,sans-serif;margin-bottom:6px;">Payment Method</div>
            <div style="font-size:13px;color:#3A5A3A;font-family:Arial,Helvetica,sans-serif;margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid #F0F5EC;"><?php echo esc_html($payment_method);?></div>
            <div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#8BA888;font-family:Arial,Helvetica,sans-serif;margin-bottom:8px;">Order Status</div>
            <table cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;"><tr><td style="background-color:#FDECEA;border:1px solid #F5C6CB;border-radius:20px;padding:5px 14px;" bgcolor="#FDECEA"><span style="font-size:12px;font-family:Arial,Helvetica,sans-serif;font-weight:700;color:#C0392B;">Cancelled</span></td></tr></table>
          </td>
        </tr>
        <tr><td class="email-section-td" style="background-color:#ffffff;border-left:1px solid #E0EBD8;border-right:1px solid #E0EBD8;border-top:1px solid #F0F5EC;padding:24px 32px;"><div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#8BA888;font-family:Arial,Helvetica,sans-serif;margin-bottom:8px;">Billing Address</div><div style="font-size:13px;color:#3A5A3A;line-height:1.75;font-family:Arial,Helvetica,sans-serif;"><?php echo wp_kses_post($billing_address?:'&#8212;');?></div></td></tr>
        <?php if($shipping_address&&$shipping_address!==$billing_address):?><tr><td class="email-section-td" style="background-color:#ffffff;border-left:1px solid #E0EBD8;border-right:1px solid #E0EBD8;border-top:1px solid #F0F5EC;padding:24px 32px;"><div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#8BA888;font-family:Arial,Helvetica,sans-serif;margin-bottom:8px;">Shipping Address</div><div style="font-size:13px;color:#3A5A3A;line-height:1.75;font-family:Arial,Helvetica,sans-serif;"><?php echo wp_kses_post($shipping_address);?></div></td></tr><?php endif;?>
<?php include __DIR__ . '/email-foot.php'; ?>
