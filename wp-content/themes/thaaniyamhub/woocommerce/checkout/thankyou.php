<?php
/**
 * Thankyou page template for Thaaniyam Hub.
 *
 * Supports single-vendor and multi-vendor split orders transparently,
 * showing all vendor packages, combined total paid, and direct order tracking.
 *
 * @package ThaaniyamHub
 * @version 1.2.0
 *
 * @var WC_Order|false $order
 */

defined('ABSPATH') || exit;

// Remove the duplicate default WooCommerce single-order details table from thankyou hook
remove_action('woocommerce_thankyou', 'woocommerce_order_details_table', 10);

if (!function_exists('thaaniyamhub_format_thankyou_price')) {
    function thaaniyamhub_format_thankyou_price($amount) {
        return '<span class="th-price-tag">₹' . number_format((float) $amount, 2, '.', '') . '</span>';
    }
}

if (!function_exists('thaaniyamhub_get_display_payment_method')) {
    function thaaniyamhub_get_display_payment_method(WC_Order $order) {
        $actual = '';
        if (function_exists('thaaniyamhub_fetch_actual_payment_method')) {
            $actual = thaaniyamhub_fetch_actual_payment_method($order->get_id());
        }
        if (empty($actual)) {
            $actual = $order->get_meta('_actual_payment_method_title');
        }
        
        $gateway_id    = strtolower($order->get_payment_method());
        $gateway_title = $order->get_payment_method_title();

        if (!empty($actual)) {
            if ($gateway_id === 'cashfree' && stripos($actual, 'cashfree') === false) {
                return 'Cashfree (' . $actual . ')';
            }
            if ($gateway_id === 'razorpay' && stripos($actual, 'razorpay') === false) {
                return 'Razorpay (' . $actual . ')';
            }
            return $actual;
        }

        return !empty($gateway_title) ? $gateway_title : 'Online Payment';
    }
}

?>
<style>
    .thaaniyamhub-thankyou-wrapper {
        max-width: 920px;
        margin: 0 auto;
        padding: 10px 0 50px;
        font-family: inherit;
        color: #1e293b;
    }
    .thaaniyamhub-thankyou-wrapper * {
        box-sizing: border-box;
    }
    .th-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 4px 18px rgba(0, 0, 0, 0.04);
        margin-bottom: 24px;
        overflow: hidden;
    }
    .th-header-card {
        padding: 32px 28px 26px;
        text-align: left;
        position: relative;
        border-top: 4px solid #0b4d45;
    }
    .th-header-top {
        display: flex;
        align-items: center;
        gap: 18px;
        margin-bottom: 24px;
    }
    .th-check-icon {
        width: 52px;
        height: 52px;
        background: #ecfdf5;
        border: 2px solid #10b981;
        color: #047857;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 26px;
        font-weight: 700;
        flex-shrink: 0;
    }
    .th-header-title {
        font-size: 24px;
        font-weight: 700;
        color: #0f172a !important;
        margin: 0 0 4px !important;
        line-height: 1.2;
    }
    .th-header-subtitle {
        font-size: 15px;
        color: #64748b !important;
        margin: 0 !important;
    }
    .th-overview-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 14px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 16px 20px;
        border-radius: 10px;
    }
    .th-overview-item span {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b !important;
        font-weight: 600;
        display: block;
        margin-bottom: 4px;
    }
    .th-overview-item strong {
        font-size: 15px;
        color: #0f172a !important;
        font-weight: 700;
        display: block;
    }
    .th-price-tag {
        white-space: nowrap !important;
        display: inline-block !important;
        font-variant-numeric: tabular-nums;
        font-family: inherit;
    }
    .th-highlight-total {
        color: #0b4d45 !important;
        font-size: 18px;
        white-space: nowrap !important;
        display: inline-block !important;
    }
    .th-notice-box {
        background: #f0fdf4;
        border: 1px solid #86efac;
        border-radius: 12px;
        padding: 16px 20px;
        margin-bottom: 24px;
        display: flex;
        align-items: flex-start;
        gap: 14px;
    }
    .th-notice-box .th-notice-icon {
        font-size: 24px;
        line-height: 1;
        flex-shrink: 0;
    }
    .th-notice-title {
        color: #166534 !important;
        font-size: 15px;
        font-weight: 700;
        margin-bottom: 2px;
        display: block;
    }
    .th-notice-desc {
        color: #15803d !important;
        font-size: 13.5px;
        line-height: 1.45;
        margin: 0;
    }
    .th-pkg-header {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 14px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    .th-pkg-store-title {
        margin: 0 !important;
        font-size: 16px;
        color: #0f172a !important;
        font-weight: 700;
    }
    .th-pkg-badge-order {
        background: #ffffff;
        color: #334155;
        font-size: 13px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 6px;
        border: 1px solid #cbd5e1;
    }
    .th-pkg-badge-status {
        background: #dcfce7;
        color: #15803d;
        font-size: 13px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 6px;
    }
    .th-items-table {
        width: 100%;
        border-collapse: collapse;
    }
    .th-items-table th {
        color: #64748b;
        font-size: 12px;
        text-transform: uppercase;
        font-weight: 600;
        padding: 10px 0;
        border-bottom: 1px solid #e2e8f0;
    }
    .th-items-table td {
        padding: 12px 0;
        border-bottom: 1px solid #f1f5f9;
        font-size: 14px;
        color: #1e293b;
    }
    .th-pkg-totals {
        background: #f8fafc;
        border-radius: 10px;
        padding: 14px 18px;
        margin-top: 14px;
        font-size: 13.5px;
        color: #475569;
    }
    .th-pkg-totals-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 6px;
    }
    .th-pkg-totals-row.th-pkg-final {
        padding-top: 8px;
        margin-top: 8px;
        border-top: 1px dashed #cbd5e1;
        font-size: 15px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 0;
    }
    .th-grand-summary {
        background: #ffffff;
        border: 2px solid #0b4d45;
        border-radius: 14px;
        padding: 22px 24px;
        margin-bottom: 24px;
        box-shadow: 0 4px 16px rgba(11, 77, 69, 0.06);
    }
    .th-btn-primary {
        background: #0b4d45 !important;
        color: #ffffff !important;
        padding: 12px 26px !important;
        border-radius: 8px !important;
        text-decoration: none !important;
        font-weight: 600 !important;
        font-size: 14.5px !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px !important;
        border: none !important;
        transition: all 0.2s ease;
    }
    .th-btn-primary:hover {
        background: #083731 !important;
        color: #ffffff !important;
        transform: translateY(-1px);
    }
    .th-btn-secondary {
        background: #ffffff !important;
        color: #334155 !important;
        padding: 12px 26px !important;
        border-radius: 8px !important;
        text-decoration: none !important;
        font-weight: 600 !important;
        font-size: 14.5px !important;
        border: 1px solid #cbd5e1 !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px !important;
        transition: all 0.2s ease;
    }
    .th-btn-secondary:hover {
        background: #f8fafc !important;
        color: #0f172a !important;
    }
</style>

<div class="woocommerce-order thaaniyamhub-thankyou-wrapper">

<?php
if ($order) :

    do_action('woocommerce_before_thankyou', $order->get_id());

    if ($order->has_status('failed')) :
?>
        <div class="th-card" style="background: #fef2f2; border-color: #fecaca; padding: 32px 24px; text-align: center;">
            <div style="font-size: 42px; margin-bottom: 12px;">⚠️</div>
            <h3 style="color: #991b1b !important; margin: 0 0 8px;"><?php esc_html_e('Payment Failed or Incomplete', 'woocommerce'); ?></h3>
            <p style="color: #7f1d1d !important; margin: 0 0 20px; font-size: 15px;"><?php esc_html_e('Unfortunately your order cannot be processed as the payment was not completed or was declined. Please try again.', 'woocommerce'); ?></p>
            <div style="display: flex; justify-content: center; gap: 12px;">
                <a href="<?php echo esc_url($order->get_checkout_payment_url()); ?>" class="button" style="background: #dc2626; color: #fff; padding: 10px 24px; border-radius: 6px; text-decoration: none;"><?php esc_html_e('Retry Payment', 'woocommerce'); ?></a>
                <?php if (is_user_logged_in()) : ?>
                    <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" class="button" style="background: #4b5563; color: #fff; padding: 10px 24px; border-radius: 6px; text-decoration: none;"><?php esc_html_e('My Account', 'woocommerce'); ?></a>
                <?php endif; ?>
            </div>
        </div>
<?php
    else :

        // 1. Resolve all orders in this checkout (primary + secondary split orders)
        $all_orders = [$order];
        $primary_order = $order;

        $split_parent_id = (int) $order->get_meta('_thaaniyamhub_primary_order_id');
        if ($split_parent_id > 0 && $split_parent_id !== $order->get_id()) {
            $resolved_parent = wc_get_order($split_parent_id);
            if ($resolved_parent) {
                $primary_order = $resolved_parent;
                $all_orders = [$resolved_parent];
            }
        }

        $sec_ids = $primary_order->get_meta('_thaaniyamhub_secondary_order_ids');
        if (!empty($sec_ids) && is_array($sec_ids)) {
            foreach ($sec_ids as $sid) {
                if ((int) $sid !== $primary_order->get_id()) {
                    $sec_o = wc_get_order((int) $sid);
                    if ($sec_o) {
                        $all_orders[] = $sec_o;
                    }
                }
            }
        }

        $is_multi_vendor = count($all_orders) > 1;

        // Calculate combined totals
        $combined_total        = 0.0;
        $combined_subtotal     = 0.0;
        $combined_shipping     = 0.0;
        $combined_discount     = 0.0;
        $order_numbers_display = [];

        foreach ($all_orders as $o) {
            $combined_total    += (float) $o->get_total();
            $combined_subtotal += (float) $o->get_subtotal();
            $combined_shipping += (float) $o->get_shipping_total();
            $combined_discount += (float) $o->get_discount_total();
            $order_numbers_display[] = '#' . $o->get_order_number();
        }

        $display_payment_method = thaaniyamhub_get_display_payment_method($primary_order);
?>

        <!-- 1. Header Card with Order Overview -->
        <div class="th-card th-header-card">
            <div class="th-header-top">
                <div class="th-check-icon">✓</div>
                <div>
                    <h2 class="th-header-title"><?php esc_html_e('Thank you for your order!', 'woocommerce'); ?></h2>
                    <p class="th-header-subtitle"><?php esc_html_e('Your payment was successful and your order has been received.', 'woocommerce'); ?></p>
                </div>
            </div>

            <!-- Overview Grid -->
            <div class="th-overview-grid">
                <div class="th-overview-item">
                    <span><?php echo $is_multi_vendor ? esc_html__('Order Numbers:', 'woocommerce') : esc_html__('Order Number:', 'woocommerce'); ?></span>
                    <strong><?php echo esc_html(implode(', ', $order_numbers_display)); ?></strong>
                </div>
                <div class="th-overview-item">
                    <span><?php esc_html_e('Date:', 'woocommerce'); ?></span>
                    <strong><?php echo esc_html(wc_format_datetime($primary_order->get_date_created())); ?></strong>
                </div>
                <div class="th-overview-item">
                    <span><?php esc_html_e('Total Paid:', 'woocommerce'); ?></span>
                    <strong class="th-highlight-total"><?php echo thaaniyamhub_format_thankyou_price($combined_total); ?></strong>
                </div>
                <div class="th-overview-item">
                    <span><?php esc_html_e('Payment Method:', 'woocommerce'); ?></span>
                    <strong><?php echo esc_html($display_payment_method); ?></strong>
                </div>
            </div>
        </div>

        <?php if ($is_multi_vendor) : ?>
            <!-- 2. Multi-Vendor Notice Box -->
            <div class="th-notice-box">
                <span class="th-notice-icon">📦</span>
                <div>
                    <strong class="th-notice-title">
                        <?php printf(esc_html__('Your order contains items from %d sellers (%d separate packages)', 'woocommerce'), count($all_orders), count($all_orders)); ?>
                    </strong>
                    <p class="th-notice-desc">
                        <?php esc_html_e('Each vendor will pack and dispatch their items separately directly to your address. You can review each shipment below.', 'woocommerce'); ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- 3. Package Cards List -->
        <div style="display: flex; flex-direction: column; gap: 20px; margin-bottom: 24px;">
            <?php
            $pkg_index = 1;
            foreach ($all_orders as $v_order) :
                $v_id = (int) $v_order->get_meta('_order_vendor_id');
                $store_name = '';
                if ($v_id > 0 && function_exists('wcfm_get_vendor_store_name')) {
                    $store_name = wcfm_get_vendor_store_name($v_id);
                }
                if (empty($store_name)) {
                    $store_name = get_bloginfo('name');
                }

                $status_label = wc_get_order_status_name($v_order->get_status());
            ?>
                <div class="th-card" style="margin-bottom: 0;">
                    <!-- Package Header -->
                    <div class="th-pkg-header">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 20px;">🏪</span>
                            <div>
                                <span style="font-size: 11.5px; text-transform: uppercase; color: #64748b; font-weight: 600; display: block;">
                                    <?php echo $is_multi_vendor ? sprintf(esc_html__('Package %d of %d', 'woocommerce'), $pkg_index, count($all_orders)) : esc_html__('Seller', 'woocommerce'); ?>
                                </span>
                                <h4 class="th-pkg-store-title"><?php echo esc_html($store_name); ?></h4>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="th-pkg-badge-order">
                                <?php echo esc_html__('Order #', 'woocommerce') . $v_order->get_order_number(); ?>
                            </span>
                            <span class="th-pkg-badge-status">
                                <?php echo esc_html($status_label); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div style="padding: 16px 22px;">
                        <table class="th-items-table">
                            <thead>
                                <tr>
                                    <th style="text-align: left;"><?php esc_html_e('Product', 'woocommerce'); ?></th>
                                    <th style="text-align: center; width: 80px;"><?php esc_html_e('Qty', 'woocommerce'); ?></th>
                                    <th style="text-align: right; width: 110px;"><?php esc_html_e('Total', 'woocommerce'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                foreach ($v_order->get_items('line_item') as $item_id => $item) :
                                    $product = $item->get_product();
                                    $item_subtotal = (float) $v_order->get_line_subtotal($item, true);
                                ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                <?php if ($product && $product->get_image_id()) : ?>
                                                    <div style="width: 42px; height: 42px; border-radius: 6px; overflow: hidden; flex-shrink: 0; background: #f1f5f9; border: 1px solid #e2e8f0;">
                                                        <?php echo $product->get_image([42, 42], ['style' => 'width: 100%; height: 100%; object-fit: cover;']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <div style="font-weight: 600; color: #0f172a;"><?php echo esc_html($item->get_name()); ?></div>
                                                    <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                                                        <?php wc_display_item_meta($item); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="text-align: center; color: #334155; font-weight: 600;">
                                            × <?php echo esc_html($item->get_quantity()); ?>
                                        </td>
                                        <td style="text-align: right; font-weight: 600; color: #0f172a;">
                                            <?php echo thaaniyamhub_format_thankyou_price($item_subtotal); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- Package Totals Summary -->
                        <div class="th-pkg-totals">
                            <div class="th-pkg-totals-row">
                                <span><?php esc_html_e('Items Subtotal:', 'woocommerce'); ?></span>
                                <strong style="color: #0f172a;"><?php echo thaaniyamhub_format_thankyou_price($v_order->get_subtotal()); ?></strong>
                            </div>
                            <?php if ((float) $v_order->get_shipping_total() > 0) : ?>
                                <div class="th-pkg-totals-row">
                                    <span><?php esc_html_e('Shipping:', 'woocommerce'); ?></span>
                                    <strong style="color: #0f172a;"><?php echo thaaniyamhub_format_thankyou_price($v_order->get_shipping_total()); ?></strong>
                                </div>
                            <?php endif; ?>
                            <?php if ((float) $v_order->get_discount_total() > 0) : ?>
                                <div class="th-pkg-totals-row" style="color: #15803d;">
                                    <span><?php esc_html_e('Discount:', 'woocommerce'); ?></span>
                                    <strong>-<?php echo thaaniyamhub_format_thankyou_price($v_order->get_discount_total()); ?></strong>
                                </div>
                            <?php endif; ?>
                            <div class="th-pkg-totals-row th-pkg-final">
                                <span><?php echo $is_multi_vendor ? sprintf(esc_html__('Package %d Total:', 'woocommerce'), $pkg_index) : esc_html__('Order Total:', 'woocommerce'); ?></span>
                                <span style="color: #0b4d45;"><?php echo thaaniyamhub_format_thankyou_price($v_order->get_total()); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php
                $pkg_index++;
            endforeach;
            ?>
        </div>

        <!-- 4. Overall Grand Total Summary (for Multi-Vendor) -->
        <?php if ($is_multi_vendor) : ?>
            <div class="th-grand-summary">
                <h4 style="margin: 0 0 16px; font-size: 16px; color: #0b4d45 !important; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                    <span>🧾</span> <?php esc_html_e('Overall Checkout Financial Summary', 'woocommerce'); ?>
                </h4>
                <div style="display: flex; flex-direction: column; gap: 8px; font-size: 14px; color: #475569;">
                    <div style="display: flex; justify-content: space-between;">
                        <span><?php esc_html_e('Combined Items Subtotal:', 'woocommerce'); ?></span>
                        <strong style="color: #0f172a;"><?php echo thaaniyamhub_format_thankyou_price($combined_subtotal); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span><?php esc_html_e('Combined Shipping Total:', 'woocommerce'); ?></span>
                        <strong style="color: #0f172a;"><?php echo thaaniyamhub_format_thankyou_price($combined_shipping); ?></strong>
                    </div>
                    <?php if ($combined_discount > 0) : ?>
                        <div style="display: flex; justify-content: space-between; color: #15803d;">
                            <span><?php esc_html_e('Total Discounts Applied:', 'woocommerce'); ?></span>
                            <strong>-<?php echo thaaniyamhub_format_thankyou_price($combined_discount); ?></strong>
                        </div>
                    <?php endif; ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 12px; margin-top: 6px; border-top: 2px solid #e2e8f0; font-size: 16px; color: #0b4d45; font-weight: 700;">
                        <span><?php esc_html_e('Total Amount Charged & Paid:', 'woocommerce'); ?></span>
                        <span style="font-size: 20px; font-weight: 800; color: #0b4d45;"><?php echo thaaniyamhub_format_thankyou_price($combined_total); ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- 5. Shipping & Billing Addresses -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 28px;">
            <div class="th-card" style="padding: 20px 22px; margin-bottom: 0;">
                <h4 style="margin: 0 0 12px; font-size: 15px; color: #0f172a !important; font-weight: 700;"><?php esc_html_e('Shipping Address', 'woocommerce'); ?></h4>
                <address style="font-style: normal; color: #475569; line-height: 1.6; font-size: 13.5px;">
                    <?php echo $primary_order->get_formatted_shipping_address() ?: esc_html__('N/A', 'woocommerce'); ?>
                    <?php if ($primary_order->get_shipping_phone()) : ?>
                        <div style="margin-top: 6px; color: #334155;">📞 <?php echo esc_html($primary_order->get_shipping_phone()); ?></div>
                    <?php endif; ?>
                </address>
            </div>

            <div class="th-card" style="padding: 20px 22px; margin-bottom: 0;">
                <h4 style="margin: 0 0 12px; font-size: 15px; color: #0f172a !important; font-weight: 700;"><?php esc_html_e('Billing Address', 'woocommerce'); ?></h4>
                <address style="font-style: normal; color: #475569; line-height: 1.6; font-size: 13.5px;">
                    <?php echo $primary_order->get_formatted_billing_address() ?: esc_html__('N/A', 'woocommerce'); ?>
                    <?php if ($primary_order->get_billing_phone()) : ?>
                        <div style="margin-top: 6px; color: #334155;">📞 <?php echo esc_html($primary_order->get_billing_phone()); ?></div>
                    <?php endif; ?>
                    <?php if ($primary_order->get_billing_email()) : ?>
                        <div style="margin-top: 4px; color: #334155;">✉️ <?php echo esc_html($primary_order->get_billing_email()); ?></div>
                    <?php endif; ?>
                </address>
            </div>
        </div>

        <!-- 6. Action Links -->
        <div style="display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; margin-top: 10px;">
            <?php if (is_user_logged_in()) : ?>
                <a href="<?php echo esc_url(wc_get_endpoint_url('orders', '', wc_get_page_permalink('myaccount'))); ?>" class="th-btn-primary">
                    <span>📋</span> <?php esc_html_e('View All Orders in My Account', 'woocommerce'); ?>
                </a>
            <?php endif; ?>
            <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" class="th-btn-secondary">
                <span>🛍️</span> <?php esc_html_e('Continue Shopping', 'woocommerce'); ?>
            </a>
        </div>

<?php
        // Fire standard thank-you hooks for third-party tracking/analytics
        do_action('woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id());
        do_action('woocommerce_thankyou', $order->get_id());

    endif;

else :
?>
    <p class="woocommerce-notice woocommerce-notice--success woocommerce-thankyou-order-received">
        <?php echo apply_filters('woocommerce_thankyou_order_received_text', esc_html__('Thank you. Your order has been received.', 'woocommerce'), null); ?>
    </p>
<?php
endif;
?>

</div>
