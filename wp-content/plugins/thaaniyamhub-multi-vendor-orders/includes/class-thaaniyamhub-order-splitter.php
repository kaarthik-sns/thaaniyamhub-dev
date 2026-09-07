<?php
/**
 * Thaaniyam Hub Marketplace — Vendor Order Splitter
 *
 * When a multi-vendor checkout is completed, splits the checkout into standard,
 * independent WooCommerce orders (one per vendor). Each order is a standard WC_Order
 * that natively integrates with WCFM, Shiprocket, and the Financial Ledger.
 *
 * Coupons and discounts are equally split across all vendors based on total vendor count.
 *
 * Hook: woocommerce_checkout_order_processed @ priority 50
 *
 * @package thaaniyamhub-multi-vendor-orders
 */

defined('ABSPATH') || exit;

class ThaaniyamHub_Order_Splitter
{
    /**
     * In-memory recursion prevention map.
     *
     * @var array<int, bool>
     */
    private static $is_splitting = [];

    /**
     * Register hooks.
     */
    public static function init()
    {
        // Classic checkout hook (priority 50 runs after checkout order creation)
        add_action('woocommerce_checkout_order_processed', [__CLASS__, 'maybe_split'], 50, 3);

        // Blocks / Store API checkout hook
        add_action('woocommerce_store_api_checkout_order_processed', [__CLASS__, 'maybe_split_from_order'], 50, 1);

        // Early status transition hooks (priority 5 runs BEFORE priority 10 transactional emails are triggered)
        add_action('woocommerce_order_status_pending_to_processing', [__CLASS__, 'on_status_transition_early'], 5, 2);
        add_action('woocommerce_order_status_pending_to_completed', [__CLASS__, 'on_status_transition_early'], 5, 2);
        add_action('woocommerce_order_status_pending_to_on-hold', [__CLASS__, 'on_status_transition_early'], 5, 2);
        add_action('woocommerce_order_status_failed_to_processing', [__CLASS__, 'on_status_transition_early'], 5, 2);
        add_action('woocommerce_order_status_cancelled_to_processing', [__CLASS__, 'on_status_transition_early'], 5, 2);

        // Synchronize payment completion from primary checkout order to split secondary orders
        add_action('woocommerce_payment_complete', [__CLASS__, 'on_payment_complete'], 20, 1);

        // Synchronize status changes from primary checkout order to split secondary orders
        add_action('woocommerce_order_status_changed', [__CLASS__, 'on_order_status_changed'], 20, 4);

        // Prevent resuming previously split or contaminated draft orders
        add_action('woocommerce_before_checkout_process', [__CLASS__, 'prevent_resuming_split_orders']);
    }

    /**
     * Early status transition hook — ensures prepaid order is split BEFORE WooCommerce generates and sends emails.
     *
     * @param int            $order_id Order ID.
     * @param WC_Order|false $order    Order object.
     */
    public static function on_status_transition_early($order_id, $order = null)
    {
        if ('yes' !== get_option('thaaniyamhub_enable_suborder_split', 'yes')) {
            return;
        }
        if (!$order || !is_a($order, 'WC_Order')) {
            $order = wc_get_order($order_id);
        }
        if (!$order || !is_a($order, 'WC_Order')) {
            return;
        }
        if ($order->get_meta('_thaaniyamhub_order_split_done')) {
            return;
        }
        self::split($order);
    }

    // -------------------------------------------------------------------------
    // Checkout Entry Points
    // -------------------------------------------------------------------------

    /**
     * Entry point from classic checkout.
     *
     * @param int      $order_id    Order ID.
     * @param array    $posted_data Posted checkout data.
     * @param WC_Order $order       Order object.
     */
    public static function maybe_split($order_id, $posted_data = null, $order = null)
    {
        if ('yes' !== get_option('thaaniyamhub_enable_suborder_split', 'yes')) {
            return;
        }
        if (!$order) {
            $order = wc_get_order($order_id);
        }
        if (!$order || !is_a($order, 'WC_Order')) {
            return;
        }
        if ($order->get_meta('_thaaniyamhub_order_split_done')) {
            return;
        }

        // Cache session vendor rates into order meta during checkout for background webhook fallback
        self::save_session_vendor_shipping_rates($order);

        // Orders are prepaid: defer splitting until payment succeeds (e.g. payment_complete or processing status).
        // Splitting here strips other vendors' items from the primary order, shrinking the total before
        // the payment gateway (Cashfree/Razorpay) reads it, which causes undercharging.
        if (doing_action('woocommerce_checkout_order_processed') || doing_action('woocommerce_store_api_checkout_order_processed')) {
            if ($order->needs_payment()) {
                thaaniyamhub_log(sprintf('Order splitter: Deferring split for Order #%d until payment completion (needs_payment is true).', $order->get_id()));
                return;
            }
        }

        self::split($order);
    }

    /**
     * Entry point from Blocks / Store API checkout.
     *
     * @param WC_Order $order Order object.
     */
    public static function maybe_split_from_order($order)
    {
        if ('yes' !== get_option('thaaniyamhub_enable_suborder_split', 'yes')) {
            return;
        }
        if (!$order || !is_a($order, 'WC_Order')) {
            return;
        }
        if ($order->get_meta('_thaaniyamhub_order_split_done')) {
            return;
        }

        self::save_session_vendor_shipping_rates($order);

        if (doing_action('woocommerce_store_api_checkout_order_processed')) {
            if ($order->needs_payment()) {
                thaaniyamhub_log(sprintf('Order splitter: Deferring split for Order #%d until payment completion (needs_payment is true).', $order->get_id()));
                return;
            }
        }

        self::split($order);
    }

    /**
     * Save vendor shipping rates from session into order metadata for resilient retrieval later.
     *
     * @param WC_Order $order
     */
    private static function save_session_vendor_shipping_rates(WC_Order $order)
    {
        if (!isset(WC()->session)) {
            return;
        }

        $chosen_methods = WC()->session->get('chosen_shipping_methods');
        $chosen_method  = !empty($chosen_methods) ? $chosen_methods[0] : '';
        $is_low_cost    = (false !== strpos($chosen_method, 'low_cost_shipping'));

        $session_key    = $is_low_cost ? 'thaaniyamhub_low_cost_shipping_vendor_rates' : 'thaaniyamhub_standard_shipping_vendor_rates';
        $session_rates  = WC()->session->get($session_key);
        if (!is_array($session_rates)) {
            $session_rates = WC()->session->get('thaaniyamhub_vendor_shipping_rates');
        }

        if (is_array($session_rates) && !empty($session_rates)) {
            $order->update_meta_data('_thaaniyamhub_vendor_shipping_rates', $session_rates);
            $order->save();
        }
    }

    /**
     * Ensure WooCommerce does not resume an order awaiting payment if it was already processed/split.
     */
    public static function prevent_resuming_split_orders()
    {
        if (!isset(WC()->session)) {
            return;
        }

        $order_id = absint(WC()->session->get('order_awaiting_payment'));
        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order && $order->get_meta('_thaaniyamhub_order_split_done')) {
                thaaniyamhub_log(sprintf('Splitter: Clearing order_awaiting_payment session because Order #%d was previously split.', $order_id));
                WC()->session->set('order_awaiting_payment', null);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Core Splitter Logic
    // -------------------------------------------------------------------------

    /**
     * Split multi-vendor checkout into independent standard WooCommerce orders per vendor.
     *
     * @param WC_Order $order The initial checkout order.
     */
    public static function split(WC_Order $order)
    {
        global $wpdb, $WCFMmp;

        $primary_order_id = $order->get_id();

        if (isset(self::$is_splitting[$primary_order_id])) {
            return;
        }
        if ($order->get_meta('_thaaniyamhub_order_split_done')) {
            return;
        }
        self::$is_splitting[$primary_order_id] = true;

        if (class_exists('ThaaniyamHub_Order_Debug_Logger')) {
            ThaaniyamHub_Order_Debug_Logger::log('SPLITTER_START', "Starting Order Splitter for Order #{$primary_order_id}", [
                'Order ID'      => '#' . $primary_order_id,
                'Status'        => $order->get_status(),
                'Total'         => '₹' . $order->get_total(),
                'Items Count'   => $order->get_item_count(),
            ]);
        }
        thaaniyamhub_log("--- START VENDOR ORDER SPLITTING FOR ORDER #{$primary_order_id} ---");

        $vendor_groups = self::group_items_by_vendor($order);
        $vendor_count  = count($vendor_groups);

        $log_groups = [];
        foreach ($vendor_groups as $vid => $v_items) {
            $it_names = [];
            foreach ($v_items as $it) {
                $it_names[] = $it->get_name() . ' (x' . $it->get_quantity() . ', total: ₹' . $it->get_total() . ')';
            }
            $log_groups['Vendor #' . $vid] = implode('; ', $it_names);
        }

        if (class_exists('ThaaniyamHub_Order_Debug_Logger')) {
            ThaaniyamHub_Order_Debug_Logger::log('SPLITTER_GROUPING', "Order #{$primary_order_id} grouped into {$vendor_count} vendor group(s)", [
                'Order ID'      => '#' . $primary_order_id,
                'Vendor Count'  => $vendor_count,
                'Vendor Groups' => $log_groups,
                'Decision'      => ($vendor_count <= 1) ? 'SINGLE_VENDOR_NO_SPLIT' : 'MULTI_VENDOR_SPLIT_REQUIRED',
            ]);
        }
        thaaniyamhub_log(sprintf('Order splitter: Order #%d grouped into %d vendor group(s).', $primary_order_id, $vendor_count));

        if ($vendor_count <= 1) {
            // Single vendor or admin products only — no split needed
            $vendor_id = 0;
            if (!empty($vendor_groups)) {
                $vendor_id = (int) key($vendor_groups);
            }
            if ($vendor_id > 0) {
                self::assign_vendor_pickup_location($order, $vendor_id);
                $order->update_meta_data('_order_vendor_id', $vendor_id);

                // Ensure shipping item has vendor_id for WCFM
                foreach ($order->get_items('shipping') as $s_item) {
                    $s_item->update_meta_data('vendor_id', $vendor_id);
                    $s_item->save();
                }
            }

            // Always mark split done so status transition hooks do not re-run split
            $order->update_meta_data('_thaaniyamhub_order_split_done', '1');
            $order->save();

            if ($vendor_id > 0) {
                if (class_exists('ThaaniyamHub_Ledger')) {
                    ThaaniyamHub_Ledger::record_vendor_order($order, $vendor_id);
                }
                if (class_exists('ThaaniyamHub_Order_History')) {
                    ThaaniyamHub_Order_History::log_order($order);
                }
            }

            if (class_exists('ThaaniyamHub_Order_Debug_Logger')) {
                ThaaniyamHub_Order_Debug_Logger::log('SPLITTER_SINGLE_VENDOR_DONE', "Order #{$primary_order_id} preserved as single-vendor order (Vendor #{$vendor_id})", [
                    'Order ID'   => '#' . $primary_order_id,
                    'Vendor ID'  => $vendor_id,
                    'Split Done' => '1',
                ]);
            }

            unset(self::$is_splitting[$primary_order_id]);
            thaaniyamhub_log("--- END ORDER SPLITTING FOR SINGLE VENDOR ORDER #{$primary_order_id} ---");
            return;
        }

        // Multi-Vendor Checkout: Split into separate independent standard orders per vendor
        $vendor_ids = array_map('intval', array_keys($vendor_groups));
        $primary_vendor_id = $vendor_ids[0];
        $other_vendor_ids   = array_slice($vendor_ids, 1);

        $created_order_ids   = [$primary_order_id];
        $secondary_order_ids = [];

        // 1. Create independent standard WC orders for each secondary vendor
        foreach ($other_vendor_ids as $vendor_id) {
            $items = $vendor_groups[$vendor_id];
            $new_order = self::create_vendor_order($order, $vendor_id, $items, $vendor_count);

            if ($new_order) {
                $new_order_id = $new_order->get_id();
                $created_order_ids[]   = $new_order_id;
                $secondary_order_ids[] = $new_order_id;

                if (class_exists('ThaaniyamHub_Order_Debug_Logger')) {
                    ThaaniyamHub_Order_Debug_Logger::log('SPLITTER_SECONDARY_ORDER', "Independent order #{$new_order_id} created for secondary Vendor #{$vendor_id}", [
                        'Primary Order ID'   => '#' . $primary_order_id,
                        'Secondary Order ID' => '#' . $new_order_id,
                        'Vendor ID'          => $vendor_id,
                        'Secondary Total'    => '₹' . $new_order->get_total(),
                        'Secondary Items'    => $new_order->get_item_count(),
                    ]);
                }

                // Process WCFM commission for this new vendor order
                if (isset($WCFMmp->wcfmmp_commission) && method_exists($WCFMmp->wcfmmp_commission, 'wcfmmp_checkout_order_processed')) {
                    $WCFMmp->wcfmmp_commission->wcfmmp_checkout_order_processed($new_order_id, [], $new_order);
                }

                // Record in Financial Ledger
                if (class_exists('ThaaniyamHub_Ledger')) {
                    ThaaniyamHub_Ledger::record_vendor_order($new_order, $vendor_id);
                }

                // Record in Order History
                if (class_exists('ThaaniyamHub_Order_History')) {
                    ThaaniyamHub_Order_History::log_order($new_order);
                }

                thaaniyamhub_log(sprintf('Splitter: Independent standard order #%d created for Vendor #%d.', $new_order_id, $vendor_id));
            } else {
                if (class_exists('ThaaniyamHub_Order_Debug_Logger')) {
                    ThaaniyamHub_Order_Debug_Logger::log('SPLITTER_SECONDARY_ORDER_FAILED', "Failed to create order for secondary Vendor #{$vendor_id} from primary Order #{$primary_order_id}", [
                        'Primary Order ID' => '#' . $primary_order_id,
                        'Vendor ID'        => $vendor_id,
                    ]);
                }
                thaaniyamhub_log(sprintf('Splitter: Failed to create order for Vendor #%d!', $vendor_id), 'error');
            }
        }

        // 2. Update the primary order to contain only the primary vendor's items, shipping, & equal discount
        self::update_primary_order($order, $primary_vendor_id, $vendor_groups[$primary_vendor_id], $vendor_count);

        if (!empty($secondary_order_ids)) {
            $order->update_meta_data('_thaaniyamhub_secondary_order_ids', $secondary_order_ids);
            $order->save();
        }

        // Re-process WCFM commission for primary order so its rows in wcfm_marketplace_orders
        // accurately reflect ONLY primary vendor's items, prorated shipping, and commission
        if (isset($WCFMmp->wcfmmp_commission) && method_exists($WCFMmp->wcfmmp_commission, 'wcfmmp_commission_order_reset')) {
            $WCFMmp->wcfmmp_commission->wcfmmp_commission_order_reset($primary_order_id);
            // Clear in-memory flag on $order as well
            $order->delete_meta_data('_wcfmmp_order_processed');
            foreach ($order->get_items('line_item') as $p_item) {
                $p_item->delete_meta_data('_wcfmmp_order_item_processed');
            }
            if (method_exists($WCFMmp->wcfmmp_commission, 'wcfmmp_checkout_order_processed')) {
                $fresh_primary = wc_get_order($primary_order_id);
                $WCFMmp->wcfmmp_commission->wcfmmp_checkout_order_processed($primary_order_id, [], $fresh_primary ?: $order);
            }
        } else {
            // Fallback cleanup if reset method not present
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}wcfm_marketplace_orders WHERE order_id = %d AND vendor_id != %d",
                $primary_order_id,
                $primary_vendor_id
            ));
        }

        // Record primary order in Financial Ledger
        if (class_exists('ThaaniyamHub_Ledger')) {
            ThaaniyamHub_Ledger::record_vendor_order($order, $primary_vendor_id);
        }

        // Record primary order in Order History
        if (class_exists('ThaaniyamHub_Order_History')) {
            ThaaniyamHub_Order_History::log_order($order);
        }

        unset(self::$is_splitting[$primary_order_id]);
        thaaniyamhub_log("Order splitting completed. Independent orders created: " . implode(', ', $created_order_ids));
        thaaniyamhub_log("--- END VENDOR ORDER SPLITTING FOR ORDER #{$primary_order_id} ---");
    }

    // -------------------------------------------------------------------------
    // Order Creation Helpers
    // -------------------------------------------------------------------------

    /**
     * Create a standard independent WooCommerce order for a specific vendor.
     *
     * @param WC_Order $primary_order The original checkout order.
     * @param int      $vendor_id     Vendor user ID.
     * @param array    $items         Line items for this vendor.
     * @param int      $vendor_count  Total number of vendors in the checkout.
     * @return WC_Order|false
     */
    private static function create_vendor_order(WC_Order $primary_order, int $vendor_id, array $items, int $vendor_count)
    {
        try {
            $new_order = wc_create_order([
                'customer_id' => $primary_order->get_customer_id(),
                'created_via' => 'thaaniyamhub_marketplace',
            ]);

            if (is_wp_error($new_order) || !$new_order) {
                thaaniyamhub_log('Splitter: wc_create_order error — ' . ($new_order ? $new_order->get_error_message() : 'Unknown error'));
                return false;
            }

            // Copy addresses
            $new_order->set_address($primary_order->get_address('billing'), 'billing');
            $new_order->set_address($primary_order->get_address('shipping'), 'shipping');

            // Copy payment details
            $new_order->set_payment_method($primary_order->get_payment_method());
            $new_order->set_payment_method_title($primary_order->get_payment_method_title());
            $new_order->set_transaction_id($primary_order->get_transaction_id());

            // Copy customer note
            $new_order->set_customer_note($primary_order->get_customer_note());

            // Add line items
            foreach ($items as $item) {
                $product = $item->get_product();
                if (!$product) {
                    continue;
                }
                $new_item = new WC_Order_Item_Product();
                $new_item->set_product($product);
                $new_item->set_quantity($item->get_quantity());
                $new_item->set_subtotal($item->get_subtotal());
                $new_item->set_total($item->get_total());
                $new_item->set_subtotal_tax($item->get_subtotal_tax());
                $new_item->set_total_tax($item->get_total_tax());

                foreach ($item->get_meta_data() as $meta) {
                    if (strpos($meta->key, '_wcfm') === 0) {
                        continue;
                    }
                    $new_item->update_meta_data($meta->key, $meta->value);
                }
                $new_item->update_meta_data('_vendor_id', $vendor_id);
                $new_order->add_item($new_item);
            }

            // Add shipping cost for this vendor
            $shipping_total = self::calculate_prorated_shipping($primary_order, $vendor_id, $items);
            $sub_package_qty = 0;
            foreach ($items as $item) {
                $sub_package_qty += $item->get_quantity();
            }

            $parent_shipping_item = null;
            foreach ($primary_order->get_items('shipping') as $ship_item) {
                $parent_shipping_item = $ship_item;
                break;
            }

            if ($parent_shipping_item) {
                $shipping_item = new WC_Order_Item_Shipping();
                $shipping_item->set_method_title($parent_shipping_item->get_method_title());
                $shipping_item->set_method_id($parent_shipping_item->get_method_id());
                $shipping_item->set_instance_id($parent_shipping_item->get_instance_id());
                $shipping_item->set_total($shipping_total);
                $shipping_item->set_taxes($parent_shipping_item->get_taxes());

                foreach ($parent_shipping_item->get_meta_data() as $meta) {
                    if ('vendor_id' !== $meta->key && 'package_qty' !== $meta->key) {
                        $shipping_item->update_meta_data($meta->key, $meta->value);
                    }
                }
                $shipping_item->update_meta_data('vendor_id', $vendor_id);
                $shipping_item->update_meta_data('package_qty', $sub_package_qty);
                $new_order->add_item($shipping_item);
            } elseif ($shipping_total > 0) {
                $shipping_item = new WC_Order_Item_Shipping();
                $shipping_item->set_method_title(__('Shipping', 'thaaniyamhub-multi-vendor-orders'));
                $shipping_item->set_total($shipping_total);
                $shipping_item->update_meta_data('vendor_id', $vendor_id);
                $shipping_item->update_meta_data('package_qty', $sub_package_qty);
                $new_order->add_item($shipping_item);
            }

            // Equal Coupon & Discount Split based on total vendor count
            $parent_coupons = $primary_order->get_items('coupon');
            if (!empty($parent_coupons) && $vendor_count > 0) {
                foreach ($parent_coupons as $coupon_item) {
                    $total_disc     = (float) $coupon_item->get_discount();
                    $total_disc_tax = (float) $coupon_item->get_discount_tax();

                    $equal_disc     = round($total_disc / $vendor_count, 2);
                    $equal_disc_tax = round($total_disc_tax / $vendor_count, 2);

                    $new_coupon = new WC_Order_Item_Coupon();
                    $new_coupon->set_code($coupon_item->get_code());
                    $new_coupon->set_discount($equal_disc);
                    $new_coupon->set_discount_tax($equal_disc_tax);
                    $new_order->add_item($new_coupon);
                }
            }

            // Set pickup location
            self::assign_vendor_pickup_location($new_order, $vendor_id);

            // Stamp standard metadata
            $new_order->update_meta_data('_order_vendor_id', $vendor_id);
            $new_order->update_meta_data('_thaaniyamhub_primary_order_id', $primary_order->get_id());
            $new_order->update_meta_data('_thaaniyamhub_order_split_done', '1');

            if ($primary_order->get_transaction_id()) {
                $new_order->set_transaction_id($primary_order->get_transaction_id());
            }

            // Set order status to pending initially so WooCommerce status transition hooks fire when paid
            $new_order->set_status('pending');
            $new_order->calculate_totals();
            $new_order->save();

            return $new_order;
        } catch (\Exception $e) {
            thaaniyamhub_log('Splitter: Exception creating vendor order — ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Update the primary order so it retains only the primary vendor's items.
     *
     * @param WC_Order $order
     * @param int      $primary_vendor_id
     * @param array    $primary_items
     * @param int      $vendor_count
     */
    private static function update_primary_order(WC_Order $order, int $primary_vendor_id, array $primary_items, int $vendor_count)
    {
        // 1. Remove line items that belong to other vendors
        foreach ($order->get_items('line_item') as $item_id => $item) {
            $p_id = $item->get_product_id();
            $v_id = function_exists('wcfm_get_vendor_id_by_post') ? (int) wcfm_get_vendor_id_by_post($p_id) : (int) get_post_field('post_author', $p_id);
            if ($v_id !== $primary_vendor_id) {
                $order->remove_item($item_id);
            }
        }

        // 2. Adjust shipping total on primary order
        $shipping_total = self::calculate_prorated_shipping($order, $primary_vendor_id, $primary_items);
        $primary_pkg_qty = 0;
        foreach ($primary_items as $p_item) {
            $primary_pkg_qty += $p_item->get_quantity();
        }

        foreach ($order->get_items('shipping') as $s_item) {
            $s_item->set_total($shipping_total);
            $s_item->update_meta_data('vendor_id', $primary_vendor_id);
            $s_item->update_meta_data('package_qty', $primary_pkg_qty);
            $s_item->save();
        }

        // 3. Equal Coupon & Discount Split: Primary order gets remainder of equal division
        $parent_coupons = $order->get_items('coupon');
        if (!empty($parent_coupons) && $vendor_count > 0) {
            foreach ($parent_coupons as $coupon_item) {
                $total_disc     = (float) $coupon_item->get_discount();
                $total_disc_tax = (float) $coupon_item->get_discount_tax();

                $equal_disc     = round($total_disc / $vendor_count, 2);
                $equal_disc_tax = round($total_disc_tax / $vendor_count, 2);

                $primary_disc     = max(0.0, round($total_disc - ($equal_disc * ($vendor_count - 1)), 2));
                $primary_disc_tax = max(0.0, round($total_disc_tax - ($equal_disc_tax * ($vendor_count - 1)), 2));

                $coupon_item->set_discount($primary_disc);
                $coupon_item->set_discount_tax($primary_disc_tax);
                $coupon_item->save();
            }
        }

        // 4. Set pickup location & metadata
        self::assign_vendor_pickup_location($order, $primary_vendor_id);
        $order->update_meta_data('_order_vendor_id', $primary_vendor_id);
        $order->update_meta_data('_thaaniyamhub_order_split_done', '1');

        $order->calculate_totals();
        $order->save();
    }

    // -------------------------------------------------------------------------
    // Shipping & Pickup Helpers
    // -------------------------------------------------------------------------

    /**
     * Calculate a vendor's shipping cost (uses session vendor rates with fallback).
     *
     * @param WC_Order $order
     * @param int      $vendor_id
     * @param array    $vendor_items
     * @return float
     */
    public static function calculate_prorated_shipping(WC_Order $order, int $vendor_id, array $vendor_items): float
    {
        // 0. Retrieve rate from order meta (saved during checkout)
        $saved_rates = $order->get_meta('_thaaniyamhub_vendor_shipping_rates');
        if (is_array($saved_rates) && isset($saved_rates[$vendor_id])) {
            $actual_rate = floatval($saved_rates[$vendor_id]);
            thaaniyamhub_log(sprintf('Splitter: Retrieved saved order shipping rate for Vendor #%d: ₹%s (Order #%d)', $vendor_id, $actual_rate, $order->get_id()));
            return $actual_rate;
        }

        // 1. Retrieve actual rate calculated for this vendor from WooCommerce session
        if (isset(WC()->session)) {
            $is_low_cost = false;
            $chosen_methods = WC()->session->get('chosen_shipping_methods');
            $chosen_method  = !empty($chosen_methods) ? $chosen_methods[0] : '';
            if (false !== strpos($chosen_method, 'low_cost_shipping')) {
                $is_low_cost = true;
            } else {
                foreach ($order->get_shipping_methods() as $shipping_item) {
                    if (stripos($shipping_item->get_name(), 'Low Cost') !== false) {
                        $is_low_cost = true;
                        break;
                    }
                }
            }

            $session_key = $is_low_cost ? 'thaaniyamhub_low_cost_shipping_vendor_rates' : 'thaaniyamhub_standard_shipping_vendor_rates';
            $session_rates = WC()->session->get($session_key);
            if (!is_array($session_rates)) {
                $session_rates = WC()->session->get('thaaniyamhub_vendor_shipping_rates');
            }

            if (is_array($session_rates) && isset($session_rates[$vendor_id])) {
                $actual_rate = floatval($session_rates[$vendor_id]);
                thaaniyamhub_log(sprintf('Splitter: Retrieved session shipping rate (%s) for Vendor #%d: ₹%s (Order #%d)', $session_key, $vendor_id, $actual_rate, $order->get_id()));
                return $actual_rate;
            }
        }

        // 2. Fallback: prorate order shipping total by item quantity ratio
        $total_shipping = (float) $order->get_shipping_total();
        if ($total_shipping <= 0.0) {
            return 0.0;
        }

        $total_items = 0;
        foreach ($order->get_items() as $item) {
            $total_items += $item->get_quantity();
        }

        $vendor_item_count = 0;
        foreach ($vendor_items as $item) {
            $vendor_item_count += $item->get_quantity();
        }

        if ($total_items <= 0) {
            return 0.0;
        }

        $ratio = $vendor_item_count / $total_items;
        return round($total_shipping * $ratio, 2);
    }

    /**
     * Assign Shiprocket pickup location and postcode for a vendor.
     *
     * @param WC_Order $order
     * @param int      $vendor_id
     */
    public static function assign_vendor_pickup_location(WC_Order $order, int $vendor_id)
    {
        if ($vendor_id <= 0) {
            return;
        }
        $pickup_location = '';
        if (class_exists('ThaaniyamHub_Dispatch') && method_exists('ThaaniyamHub_Dispatch', 'resolve_pickup_nickname')) {
            $pickup_location = ThaaniyamHub_Dispatch::resolve_pickup_nickname($vendor_id);
        }
        if (!empty($pickup_location)) {
            $order->update_meta_data('pickup_location', $pickup_location);
            $order->update_meta_data('_pickup_location', $pickup_location);

            if (class_exists('ThaaniyamHub_Dashboard') && method_exists('ThaaniyamHub_Dashboard', 'resolve_pickup_pincode')) {
                $pickup_postcode = ThaaniyamHub_Dashboard::resolve_pickup_pincode($pickup_location, $vendor_id);
                if ($pickup_postcode) {
                    $order->update_meta_data('pickup_postcode', $pickup_postcode);
                    $order->update_meta_data('_shiprocket_pickup_postcode', $pickup_postcode);
                }
            }
        }
    }

    /**
     * Group order line items by vendor ID.
     *
     * @param WC_Order $order
     * @return array  [ vendor_id => [ WC_Order_Item_Product, ... ], ... ]
     */
    public static function group_items_by_vendor(WC_Order $order): array
    {
        $groups = [];
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $vendor_id = 0;
            if (function_exists('wcfm_get_vendor_id_by_post')) {
                $vendor_id = (int) wcfm_get_vendor_id_by_post($product_id);
            }
            if (!$vendor_id) {
                $vendor_id = (int) get_post_field('post_author', $product_id);
            }
            if (!$vendor_id) {
                continue;
            }
            $groups[$vendor_id][] = $item;
        }
        return $groups;
    }

    // -------------------------------------------------------------------------
    // Public Query Helpers
    // -------------------------------------------------------------------------

    /**
     * Backward-compatible stub: returns empty array as all orders are independent.
     *
     * @param int $order_id
     * @return array
     */
    public static function get_sub_order_ids($order_id): array
    {
        return [];
    }

    /**
     * Backward-compatible stub: returns false as all orders are standard standalone orders.
     *
     * @param mixed $order
     * @return bool
     */
    public static function is_thaaniyamhub_suborder($order): bool
    {
        return false;
    }

    /**
     * Get the vendor ID for an order.
     *
     * @param mixed $order
     * @return int
     */
    public static function get_vendor_id($order): int
    {
        if (!is_a($order, 'WC_Order')) {
            $order = wc_get_order($order);
        }
        if (!$order) {
            return 0;
        }
        $v_id = (int) $order->get_meta('_order_vendor_id');
        if ($v_id > 0) {
            return $v_id;
        }
        foreach ($order->get_items() as $item) {
            $p_id = $item->get_product_id();
            $v = function_exists('wcfm_get_vendor_id_by_post') ? (int) wcfm_get_vendor_id_by_post($p_id) : (int) get_post_field('post_author', $p_id);
            if ($v > 0) {
                return $v;
            }
        }
        return 0;
    }

    /**
     * Find shipping item for a vendor on an order.
     *
     * @param mixed $order
     * @param int   $vendor_id
     * @return WC_Order_Item_Shipping|null
     */
    public static function find_vendor_shipping_item($order, $vendor_id)
    {
        if (!is_a($order, 'WC_Order')) {
            $order = wc_get_order($order);
        }
        if (!$order) {
            return null;
        }
        foreach ($order->get_items('shipping') as $item) {
            if ((int) $item->get_meta('vendor_id') === (int) $vendor_id) {
                return $item;
            }
        }
        $items = $order->get_items('shipping');
        return !empty($items) ? reset($items) : null;
    }

    // -------------------------------------------------------------------------
    // Split Order Payment & Status Synchronization
    // -------------------------------------------------------------------------

    /**
     * Recursion prevention flag for status synchronization.
     *
     * @var bool
     */
    private static $is_syncing_status = false;

    /**
     * When payment completes on a primary checkout order, synchronize payment to split secondary orders.
     *
     * @param int $order_id Primary order ID.
     */
    public static function on_payment_complete($order_id)
    {
        // Skip if we're already syncing status to secondary orders (prevents recursive re-entry)
        if (self::$is_syncing_status) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Idempotency: use a transient lock to prevent duplicate webhook processing
        // Cashfree can send multiple identical 'notify' webhooks for the same order
        $lock_key = 'thaaniyamhub_payment_complete_' . $order_id;
        if (get_transient($lock_key)) {
            thaaniyamhub_log(sprintf('Order splitter: Skipping duplicate payment_complete for Order #%d (lock active).', $order_id));
            return;
        }
        set_transient($lock_key, 1, 60); // 60-second lock window

        // If order hasn't been split yet, split now upon successful payment
        if (!$order->get_meta('_thaaniyamhub_order_split_done')) {
            self::split($order);
        }

        $secondary_ids = $order->get_meta('_thaaniyamhub_secondary_order_ids');
        if (empty($secondary_ids) || !is_array($secondary_ids)) {
            return;
        }

        $tx_id          = $order->get_transaction_id();
        $payment_method = $order->get_payment_method();
        $payment_title  = $order->get_payment_method_title();

        self::$is_syncing_status = true;

        foreach ($secondary_ids as $sec_id) {
            $sec_order = wc_get_order($sec_id);
            if (!$sec_order) {
                continue;
            }

            if ($payment_method && !$sec_order->get_payment_method()) {
                $sec_order->set_payment_method($payment_method);
            }
            if ($payment_title && !$sec_order->get_payment_method_title()) {
                $sec_order->set_payment_method_title($payment_title);
            }
            if ($tx_id && !$sec_order->get_transaction_id()) {
                $sec_order->set_transaction_id($tx_id);
            }

            if ($sec_order->get_status() !== 'processing' && $sec_order->get_status() !== 'completed') {
                $sec_order->payment_complete($tx_id);
                thaaniyamhub_log(sprintf('Order splitter: Synced payment_complete for split order #%d from primary order #%d.', $sec_id, $order_id));
            }
        }

        self::$is_syncing_status = false;
    }

    /**
     * Synchronize order status changes from primary checkout order to split secondary orders.
     *
     * @param int      $order_id   Order ID.
     * @param string   $old_status Previous status.
     * @param string   $new_status New status.
     * @param WC_Order $order      Order object.
     */
    public static function on_order_status_changed($order_id, $old_status, $new_status, $order = null)
    {
        if (self::$is_syncing_status) {
            return;
        }

        $clean_old_status = ltrim($old_status, 'wc-');
        $clean_new_status = ltrim($new_status, 'wc-');

        // Do not sync terminal per-vendor statuses like 'refunded' or 'cancelled' across split orders.
        // Each vendor order manages its status based on its own ID independently.
        if (in_array($clean_new_status, array('refunded', 'cancelled'), true)) {
            return;
        }

        // Only synchronize initial checkout payment transitions (e.g. pending -> processing or on-hold).
        // Post-checkout fulfillment statuses (e.g. processing -> completed) are per-vendor and independent.
        $initial_unpaid_statuses = array('pending', 'failed', 'cancelled', 'checkout-draft');
        if (!in_array($clean_old_status, $initial_unpaid_statuses, true)) {
            return;
        }

        if (!$order || !is_a($order, 'WC_Order')) {
            $order = wc_get_order($order_id);
        }
        if (!$order) {
            return;
        }

        // If order hasn't been split yet and transitions to a paid or processing status, split now
        $target_statuses = array('processing', 'completed', 'on-hold');
        $clean_status    = ltrim($new_status, 'wc-');
        if (!$order->get_meta('_thaaniyamhub_order_split_done') && in_array($clean_status, $target_statuses, true)) {
            self::split($order);
        }

        $secondary_ids = $order->get_meta('_thaaniyamhub_secondary_order_ids');
        if (empty($secondary_ids) || !is_array($secondary_ids)) {
            return;
        }

        self::$is_syncing_status = true;

        $tx_id          = $order->get_transaction_id();
        $payment_method = $order->get_payment_method();
        $payment_title  = $order->get_payment_method_title();

        foreach ($secondary_ids as $sec_id) {
            $sec_order = wc_get_order($sec_id);
            if (!$sec_order) {
                continue;
            }

            if ($payment_method && !$sec_order->get_payment_method()) {
                $sec_order->set_payment_method($payment_method);
            }
            if ($payment_title && !$sec_order->get_payment_method_title()) {
                $sec_order->set_payment_method_title($payment_title);
            }
            if ($tx_id && !$sec_order->get_transaction_id()) {
                $sec_order->set_transaction_id($tx_id);
            }

            $clean_new_status = ltrim($new_status, 'wc-');
            if ($sec_order->get_status() !== $clean_new_status) {
                if ('processing' === $clean_new_status && $tx_id) {
                    $sec_order->payment_complete($tx_id);
                } else {
                    $sec_order->update_status($new_status, sprintf(__('Synced status from checkout order #%d.', 'thaaniyamhub-multi-vendor-orders'), $order_id));
                }
                thaaniyamhub_log(sprintf('Order splitter: Synced status (%s -> %s) for split order #%d from primary order #%d.', $old_status, $new_status, $sec_id, $order_id));
            } else {
                $sec_order->save();
            }
        }

        self::$is_syncing_status = false;
    }
}

