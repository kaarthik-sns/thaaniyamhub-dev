<?php
/**
 * Thaaniyam Hub Marketplace — Failsafe Shiprocket Dispatch Engine
 *
 * Pushes confirmed vendor sub-orders directly to Shiprocket's API when
 * order status transitions to 'processing'. On failure, flags the sub-order
 * for retry and logs the full error payload.
 *
 * Hook: woocommerce_order_status_processing @ priority 25
 *
 * Only acts on sub-orders created by ThaaniyamHub_Order_Splitter (meta _is_thaaniyamhub_suborder = 1).
 * Parent orders are silently skipped.
 *
 * Retry: wp_thaaniyamhub_sf_retry_failed_dispatches (hourly WP-Cron job).
 *
 * @package thaaniyamhub-shiprocket-fulfillment
 */

defined('ABSPATH') || exit;

if (!class_exists('ThaaniyamHub_Dispatch')) {
    class ThaaniyamHub_Dispatch
    {

        public static function init()
        {
            // Hourly retry cron for failed dispatches.
            // add_action('wp_thaaniyamhub_sf_retry_failed_dispatches', [__CLASS__, 'retry_failed_orders']);
            // if (!wp_next_scheduled('wp_thaaniyamhub_sf_retry_failed_dispatches')) {
            //     wp_schedule_event(time(), 'hourly', 'wp_thaaniyamhub_sf_retry_failed_dispatches');
            // }
            // Auto-cancel Shiprocket order when order is cancelled.
            add_action('woocommerce_order_status_cancelled', [__CLASS__, 'auto_cancel_shiprocket_shipment']);
            // Prevent cancellation if not allowed by Shiprocket
            add_action('woocommerce_before_order_status_change', [__CLASS__, 'before_order_status_change_validation'], 10, 4);
            // Auto-push sub-order to Shiprocket on processing status transition.
            // add_action( 'woocommerce_order_status_processing', [ __CLASS__, 'push_to_shiprocket' ], 25 );
        }

        /**
         * Prevent order status transition to cancelled if shipment cannot be cancelled.
         */
        public static function before_order_status_change_validation($order_id, $from, $to, $order = null)
        {
            if ('cancelled' !== $to) {
                return;
            }

            if (!$order) {
                $order = wc_get_order($order_id);
            }
            if (!$order) {
                return;
            }

            // Only check sub-orders or single-vendor orders.
            if (!self::is_order_eligible_for_fulfillment($order)) {
                return;
            }

            thaaniyamhub_log("ThaaniyamHub_Dispatch: before_order_status_change_validation triggered for order #{$order_id} changing from '{$from}' to '{$to}'.");

            $record = ThaaniyamHub_Shiprocket_API::get_fulfillment($order_id);
            if (!$record || empty($record->shiprocket_order_id) || 'cancelled' === $record->fulfillment_status) {
                thaaniyamhub_log("ThaaniyamHub_Dispatch: before_order_status_change_validation - no active Shiprocket order for order #{$order_id} (or already cancelled). Status transition allowed.");
                return;
            }

            $uncancellable_statuses = ['picked_up', 'out_for_delivery', 'delivered', 'rto', 'return_initiated', 'return_picked_up', 'return_ofd', 'returned'];
            if (in_array($record->fulfillment_status, $uncancellable_statuses, true)) {
                thaaniyamhub_log("ThaaniyamHub_Dispatch: before_order_status_change_validation - cancellation blocked. Shiprocket status '{$record->fulfillment_status}' is uncancellable for order #{$order_id}.", 'warning');
                throw new Exception(__('Order cannot be cancelled as the Shiprocket shipment has already been picked up or is out for delivery.', 'thaaniyamhub-shiprocket-fulfillment'));
            }

            if (!empty($record->awb_code) && 'out_for_pickup' === $record->fulfillment_status) {
                thaaniyamhub_log("ThaaniyamHub_Dispatch: before_order_status_change_validation - cancellation blocked. AWB is generated and status is out_for_pickup for order #{$order_id}.", 'warning');
                throw new Exception(__('Order cannot be cancelled as the AWB has been generated and the Shiprocket shipment is out for pickup.', 'thaaniyamhub-shiprocket-fulfillment'));
            }

            // Let's call the API to cancel it and see if it allows it.
            thaaniyamhub_log("ThaaniyamHub_Dispatch: before_order_status_change_validation - requesting Shiprocket cancellation for order #{$order_id} (Shiprocket Order: #{$record->shiprocket_order_id}).");
            $api = new ThaaniyamHub_Shiprocket_API();
            $result = $api->cancel_order([$record->shiprocket_order_id], $order_id);

            if (is_wp_error($result)) {
                $error_msg = $result->get_error_message();
                thaaniyamhub_log("ThaaniyamHub_Dispatch: before_order_status_change_validation - Shiprocket cancel order API returned error for order #{$order_id}: {$error_msg}", 'error');
                throw new Exception(sprintf(__('Shiprocket Cancel Error: %s. Order cannot be cancelled.', 'thaaniyamhub-shiprocket-fulfillment'), $error_msg));
            }

            // Success - update local status to cancelled so auto_cancel_shiprocket_shipment doesn't call it again.
            thaaniyamhub_log("ThaaniyamHub_Dispatch: before_order_status_change_validation - Shiprocket cancellation succeeded for order #{$order_id}. Updating local status to cancelled.");
            ThaaniyamHub_Shiprocket_API::update_status($order_id, 'cancelled');
        }

        /**
         * Automatically cancel the Shiprocket order when the WooCommerce order status is set to cancelled.
         *
         * @param int $order_id WooCommerce order ID.
         */
        public static function auto_cancel_shiprocket_shipment(int $order_id)
        {
            $record = ThaaniyamHub_Shiprocket_API::get_fulfillment($order_id);
            if (!$record || empty($record->shiprocket_order_id) || 'cancelled' === $record->fulfillment_status) {
                return;
            }

            thaaniyamhub_log("ThaaniyamHub_Dispatch: Auto-cancelling Shiprocket order #{$record->shiprocket_order_id} for sub-order #{$order_id} because order status changed to cancelled.");

            $api = new ThaaniyamHub_Shiprocket_API();
            $result = $api->cancel_order([$record->shiprocket_order_id], $order_id);

            if (is_wp_error($result)) {
                $error_msg = $result->get_error_message();
                thaaniyamhub_log("ThaaniyamHub_Dispatch: Auto-cancel Shiprocket API error for sub-order #{$order_id} — {$error_msg}", 'error');
                $order = wc_get_order($order_id);
                if ($order) {
                    $order->add_order_note(sprintf(__('⚠️ Shiprocket auto-cancellation failed: %s. Please cancel manually.', 'thaaniyamhub-shiprocket-fulfillment'), $error_msg));
                }
                return;
            }

            ThaaniyamHub_Shiprocket_API::update_status($order_id, 'cancelled');
            thaaniyamhub_log("ThaaniyamHub_Dispatch: Auto-cancel Shiprocket order #{$record->shiprocket_order_id} succeeded for sub-order #{$order_id}. Updated local status to cancelled.");
            $order = wc_get_order($order_id);
            if ($order) {
                $order->add_order_note(__('❌ Shiprocket shipment automatically cancelled because order was cancelled in WooCommerce/WCFM.', 'thaaniyamhub-shiprocket-fulfillment'));
            }
        }

        /**
         * Push a sub-order to Shiprocket when it reaches 'processing' status.
         *
         * @param int    $order_id        WooCommerce order ID.
         * @param string $pickup_override Optional pickup location override.
         */
        public static function push_to_shiprocket(int $order_id, string $pickup_override = '')
        {
            thaaniyamhub_log("--- START SHIPROCKET DISPATCH FOR ORDER #{$order_id} ---");
            $order = wc_get_order($order_id);
            if (!$order) {
                thaaniyamhub_log("Shiprocket Dispatch: Order/Sub-order #{$order_id} not found in database.", 'error');
                thaaniyamhub_log("--- END SHIPROCKET DISPATCH FOR ORDER #{$order_id} ---");
                return;
            }

            // Only dispatch sub-orders or single-vendor orders.
            if (!self::is_order_eligible_for_fulfillment($order)) {
                thaaniyamhub_log("Shiprocket Dispatch: Order #{$order_id} is not eligible for Shiprocket fulfillment (likely a multi-vendor parent/master order that was split). Skipping.");
                thaaniyamhub_log("--- END SHIPROCKET DISPATCH FOR ORDER #{$order_id} ---");
                return;
            }

            // Skip if already dispatched successfully (and not cancelled).
            $existing = ThaaniyamHub_Shiprocket_API::get_fulfillment($order_id);
            if ($existing && !empty($existing->shiprocket_order_id) && 'cancelled' !== $existing->fulfillment_status) {
                thaaniyamhub_log("Shiprocket Dispatch: Order/Sub-order #{$order_id} already dispatched (Shiprocket Order ID: {$existing->shiprocket_order_id}, Status: {$existing->fulfillment_status}) — skipping.");
                thaaniyamhub_log("--- END SHIPROCKET DISPATCH FOR ORDER #{$order_id} ---");
                return;
            }

            thaaniyamhub_log("Shiprocket Dispatch: Preparing data to dispatch order/sub-order #{$order_id} to Shiprocket.");

            $vendor_id = self::get_order_vendor_id($order);
            thaaniyamhub_log("Shiprocket Dispatch: Order vendor ID resolved as #{$vendor_id}.");

            // Resolve pickup location nickname.
            $pickup_nickname = $pickup_override ?: trim((string) $order->get_meta('_shiprocket_pickup_override'));
            if (!$pickup_nickname) {
                $pickup_nickname = self::resolve_pickup_nickname($vendor_id);
            }
            thaaniyamhub_log("Shiprocket Dispatch: Resolved pickup location nickname: '{$pickup_nickname}'");

            if (!$pickup_nickname) {
                $note = 'AG Dispatch: Cannot push to Shiprocket — pickup location nickname not configured for vendor #' . $vendor_id . '. Set the _shiprocket_pickup_id user meta or configure in settings.';
                $order->add_order_note($note);
                thaaniyamhub_log("Shiprocket Dispatch: Failed — {$note}", 'error');
                self::flag_failed($order, 'NO_PICKUP_LOCATION');
                thaaniyamhub_log("--- END SHIPROCKET DISPATCH FOR ORDER #{$order_id} ---");
                return;
            }

            // Build the Shiprocket order payload.
            $payload = self::build_payload($order, $pickup_nickname);

            if (is_wp_error($payload)) {
                $error_msg = $payload->get_error_message();
                thaaniyamhub_log("Shiprocket Dispatch: Aborted — {$error_msg}", 'warning');
                self::flag_failed($order, $payload->get_error_message());
                $order->add_order_note(
                    sprintf(
                        __('⚠️ Shiprocket dispatch paused: %s Please fill package details and push manually.', 'thaaniyamhub-shiprocket-fulfillment'),
                        $error_msg
                    )
                );
                thaaniyamhub_log("--- END SHIPROCKET DISPATCH FOR ORDER #{$order_id} ---");
                return;
            }

            if (empty($payload)) {
                thaaniyamhub_log("Shiprocket Dispatch: Failed to build payload for Order #{$order_id}!", 'error');
                self::flag_failed($order, 'PAYLOAD_BUILD_FAILED');
                thaaniyamhub_log("--- END SHIPROCKET DISPATCH FOR ORDER #{$order_id} ---");
                return;
            }

            // Call the API.
            thaaniyamhub_log("Shiprocket Dispatch: Sending payload to Shiprocket API...");
            $api = new ThaaniyamHub_Shiprocket_API();
            $result = $api->create_order($payload, $order_id);

            if (is_wp_error($result)) {
                $error_msg = $result->get_error_message();
                thaaniyamhub_log("Shiprocket Dispatch: Shiprocket API returned error for sub-order #{$order_id} — {$error_msg}", 'error');
                self::flag_failed($order, $error_msg);
                $order->add_order_note(
                    sprintf(
                        __('Shiprocket dispatch FAILED: %s. Sub-order will be retried automatically.', 'thaaniyamhub-shiprocket-fulfillment'),
                        $error_msg
                    )
                );
                thaaniyamhub_log("--- END SHIPROCKET DISPATCH FOR ORDER #{$order_id} ---");
                return;
            }

            thaaniyamhub_log("Shiprocket Dispatch: API Response: " . print_r($result, true));

            // Success — extract IDs and save fulfillment record.
            $sr_order_id = (string) ($result['order_id'] ?? '');
            $sr_shipment_id = (string) ($result['shipment_id'] ?? '');

            if (!$sr_order_id) {
                thaaniyamhub_log("Shiprocket Dispatch: Shiprocket returned no order_id for sub-order #{$order_id}!", 'error');
                self::flag_failed($order, 'NO_ORDER_ID_IN_RESPONSE');
                thaaniyamhub_log("--- END SHIPROCKET DISPATCH FOR ORDER #{$order_id} ---");
                return;
            }

            thaaniyamhub_log("Shiprocket Dispatch: Saving local fulfillment record (Order ID: {$sr_order_id}, Shipment ID: {$sr_shipment_id}).");
            ThaaniyamHub_Shiprocket_API::save_fulfillment(
                $order_id,
                $vendor_id,
                $sr_order_id,
                $sr_shipment_id,
                $pickup_nickname
            );

            // Force override pickup location to bypass Shiprocket channel setting default address overrides
            if (!empty($sr_order_id) && !empty($pickup_nickname)) {
                thaaniyamhub_log("Shiprocket Dispatch: Force-patching pickup location to '{$pickup_nickname}'...");
                $patch_res = $api->update_order_pickup_location([(int) $sr_order_id], $pickup_nickname, $order_id);
                if (is_wp_error($patch_res)) {
                    thaaniyamhub_log("Shiprocket Dispatch: Warning — Failed to force-patch pickup location for Shiprocket Order #{$sr_order_id}: " . $patch_res->get_error_message(), 'warning');
                } else {
                    thaaniyamhub_log("Shiprocket Dispatch: Successfully force-patched pickup location to {$pickup_nickname} for Shiprocket Order #{$sr_order_id}.");
                }
            }

            // Store on order meta for quick access.
            $order->update_meta_data('_shiprocket_order_id', $sr_order_id);
            $order->update_meta_data('_shiprocket_shipment_id', $sr_shipment_id);
            $order->update_meta_data('pickup_location', $pickup_nickname);
            $order->update_meta_data('_pickup_location', $pickup_nickname);
            $order->delete_meta_data('_shiprocket_sync_failed');
            $order->save();

            $order->add_order_note(
                sprintf(
                    __('✅ Shiprocket order created. Order ID: %s | Shipment ID: %s | Pickup: %s', 'thaaniyamhub-shiprocket-fulfillment'),
                    $sr_order_id,
                    $sr_shipment_id,
                    $pickup_nickname
                )
            );

            thaaniyamhub_log(
                "Shiprocket Dispatch: Sub-order #{$order_id} successfully dispatched. SR Order: {$sr_order_id} | Shipment: {$sr_shipment_id}"
            );
            thaaniyamhub_log("--- END SHIPROCKET DISPATCH FOR ORDER #{$order_id} ---");
        }

        private static function build_payload(WC_Order $order, string $pickup_nickname)
        {
            $order_id = $order->get_id();
            $date_obj = $order->get_date_created();
            $order_date = $date_obj ? $date_obj->date('Y-m-d H:i') : current_time('Y-m-d H:i');

            // Detect payment mode.
            $is_cod = strtolower($order->get_payment_method()) === 'cod';
            $payment_str = $is_cod ? 'COD' : 'Prepaid';

            // Build order items array.
            $order_items = [];
            $declared_val = 0.0;
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if (!$product) {
                    continue;
                }
                $order_items[] = [
                    'name' => $item->get_name(),
                    'sku' => $product->get_sku() ?: ('AG-' . $item->get_product_id()),
                    'units' => $item->get_quantity(),
                    'selling_price' => round($item->get_total() / max(1, $item->get_quantity()), 2),
                    'discount' => round($item->get_subtotal() - $item->get_total(), 2),
                ];
                $declared_val += (float) $item->get_total();
            }

            if (empty($order_items)) {
                thaaniyamhub_log("ThaaniyamHub_Dispatch::build_payload: No order items for sub-order #{$order_id}.");
                return null;
            }

            // Do not autofill from product metrics or fallbacks, only use manual overrides.
            $weight = $order->get_meta('_shiprocket_weight_override');
            $length = $order->get_meta('_shiprocket_length_override');
            $breadth = $order->get_meta('_shiprocket_width_override');
            $height = $order->get_meta('_shiprocket_height_override');

            if (
                $weight === '' || floatval($weight) <= 0 ||
                $length === '' || floatval($length) <= 0 ||
                $breadth === '' || floatval($breadth) <= 0 ||
                $height === '' || floatval($height) <= 0
            ) {
                return new WP_Error(
                    'missing_package_metrics',
                    __('Weight and dimensions are required.', 'thaaniyamhub-shiprocket-fulfillment')
                );
            }

            $channel_id = get_option('thaaniyamhub_shiprocket_channel_id', '10832781');

            // Check if there are previous attempts to push this order to generate a unique ID.
            global $wpdb;
            $attempt_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}thaaniyamhub_shiprocket_api_logs 
              WHERE sub_order_id = %d AND endpoint_requested LIKE '%%orders/create/adhoc%%'",
                $order_id
            ));
            $unique_order_id = $attempt_count > 0 ? $order_id . '-R' . $attempt_count : (string) $order_id;

            $payload = [
                'order_id' => $unique_order_id,
                'order_date' => $order_date,
                'pickup_location' => $pickup_nickname,
                'channel_id' => $channel_id,
                'comment' => $order->get_customer_note(),
                'billing_customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'billing_last_name' => $order->get_billing_last_name(),
                'billing_address' => $order->get_billing_address_1(),
                'billing_address_2' => $order->get_billing_address_2(),
                'billing_isd_code' => '+91',
                'billing_phone' => $order->get_billing_phone(),
                'billing_customer_email' => $order->get_billing_email(),
                'billing_city' => $order->get_billing_city(),
                'billing_pincode' => $order->get_billing_postcode(),
                'billing_state' => $order->get_billing_state(),
                'billing_country' => $order->get_billing_country() ?: 'IN',
                'shipping_is_billing' => false,
                'shipping_customer_name' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
                'shipping_last_name' => $order->get_shipping_last_name(),
                'shipping_address' => $order->get_shipping_address_1() ?: $order->get_billing_address_1(),
                'shipping_address_2' => $order->get_shipping_address_2() ?: $order->get_billing_address_2(),
                'shipping_city' => $order->get_shipping_city() ?: $order->get_billing_city(),
                'shipping_pincode' => $order->get_shipping_postcode() ?: $order->get_billing_postcode(),
                'shipping_country' => $order->get_shipping_country() ?: $order->get_billing_country() ?: 'IN',
                'shipping_state' => $order->get_shipping_state() ?: $order->get_billing_state(),
                'shipping_email' => $order->get_billing_email(),
                'shipping_isd_code' => '+91',
                'shipping_phone' => $order->get_shipping_phone() ?: $order->get_billing_phone(),
                'order_items' => $order_items,
                'payment_method' => $payment_str,
                'shipping_charges' => round((float) $order->get_shipping_total(), 2),
                'giftwrap_charges' => 0,
                'transaction_charges' => 0,
                'total_discount' => 0,
                'sub_total' => round($declared_val, 2),
                'length' => floatval($length),
                'breadth' => floatval($breadth),
                'height' => floatval($height),
                'weight' => floatval($weight),
            ];

            // COD amount.
            if ($is_cod) {
                $payload['cod_amount'] = round((float) $order->get_total(), 2);
            }

            return $payload;
        }

        public static function calculate_package_metrics(WC_Order $order): array
        {
            $default_weight = floatval(thaaniyamhub_get_shipping_setting('default_weight', 0.1));
            $default_length = floatval(thaaniyamhub_get_shipping_setting('default_length', 10.0));
            $default_width = floatval(thaaniyamhub_get_shipping_setting('default_width', 10.0));
            $default_height = floatval(thaaniyamhub_get_shipping_setting('default_height', 10.0));
            $vol_divisor = intval(thaaniyamhub_get_shipping_setting('volumetric_divisor', 5000));
            if ($vol_divisor <= 0) {
                $vol_divisor = 5000;
            }

            // Retrieve the additional weight from order meta, vendor settings, or global settings
            $add_weight = $order->get_meta('_additional_weight');
            if ($add_weight === '') {
                $add_weight = $order->get_meta('additional_weight');
            }
            if ($add_weight === '') {
                $vendor_id = self::get_order_vendor_id($order);
                if ($vendor_id > 0) {
                    $add_weight = get_user_meta($vendor_id, '_shiprocket_additional_weight', true);
                }
            }
            if ($add_weight === '') {
                $add_weight = floatval(thaaniyamhub_get_shipping_setting('additional_weight', 0.0));
            } else {
                $add_weight = floatval($add_weight);
            }

            $w = 0.0;
            $l = 0.0;
            $b = 0.0;
            $h = 0.0;
            $weight_unit = strtolower(get_option('woocommerce_weight_unit', 'kg'));

            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if (!$product) {
                    continue;
                }
                $qty = max(1, (int) $item->get_quantity());
                $item_weight = floatval($product->get_weight());
                if ($item_weight <= 0) {
                    // Convert default_weight (in kg) to store's unit
                    if (in_array($weight_unit, ['g', 'grams'], true)) {
                        $item_weight = $default_weight * 1000;
                    } elseif ('lbs' === $weight_unit) {
                        $item_weight = $default_weight / 0.453592;
                    } else {
                        $item_weight = $default_weight;
                    }
                }
                $w += $item_weight * $qty;
                $l = max($l, (float) $product->get_length());
                $b = max($b, (float) $product->get_width());
                $h += (float) $product->get_height();
            }

            // Unit conversion to kg / cm.
            if (in_array($weight_unit, ['g', 'grams'], true)) {
                $w /= 1000;
            } elseif ('lbs' === $weight_unit) {
                $w *= 0.453592;
            }

            $dim_unit = strtolower(get_option('woocommerce_dimension_unit', 'cm'));
            if ('in' === $dim_unit) {
                $l *= 2.54;
                $b *= 2.54;
                $h *= 2.54;
            } elseif ('m' === $dim_unit) {
                $l *= 100;
                $b *= 100;
                $h *= 100;
            } elseif ('mm' === $dim_unit) {
                $l /= 10;
                $b /= 10;
                $h /= 10;
            }

            // Check if the order has saved total package weight metadata
            $saved_total_weight = $order->get_meta('_total_package_weight');
            if ($saved_total_weight === '') {
                $saved_total_weight = $order->get_meta('total_package_weight');
            }

            if ($saved_total_weight !== '' && floatval($saved_total_weight) > 0) {
                $w = floatval($saved_total_weight);
            } else {
                $w = $w > 0 ? $w + $add_weight : $default_weight;
            }

            $l = $l > 0 ? $l : $default_length;
            $b = $b > 0 ? $b : $default_width;
            $h = $h > 0 ? $h : $default_height;

            $vol_weight = ($l * $b * $h) / max(1, $vol_divisor);
            $w = max($w, $vol_weight);

            return [round($w, 3), round($l, 2), round($b, 2), round($h, 2)];
        }

        public static function resolve_pickup_nickname(int $vendor_id): string
        {
            // Priority 1: Profile user meta key set by Admin in Pickup Manager.
            $nickname = trim((string) get_user_meta($vendor_id, '_shiprocket_pickup_id', true));
            if ($nickname) {
                return $nickname;
            }

            // Priority 2: Admin-configured option map
            $pickup_map = get_option('thaaniyamhub_vendor_pickup_map', []);
            if (!empty($pickup_map[$vendor_id])) {
                return trim($pickup_map[$vendor_id]);
            }

            return '';
        }

        private static function flag_failed(WC_Order $order, string $reason)
        {
            $order->update_meta_data('_shiprocket_sync_failed', '1');
            $order->update_meta_data('_shiprocket_sync_fail_reason', $reason);
            $order->save();
        }

        public static function retry_failed_orders()
        {
            $failed_orders = wc_get_orders([
                'meta_key' => '_shiprocket_sync_failed',
                'meta_value' => '1',
                'limit' => 20,
                'status' => ['processing'],
            ]);

            if (empty($failed_orders)) {
                return;
            }

            thaaniyamhub_log('ThaaniyamHub_Dispatch: Cron retry — ' . count($failed_orders) . ' failed sub-order(s) found.');

            foreach ($failed_orders as $order) {
                self::push_to_shiprocket($order->get_id());
            }
        }

        /**
         * Get the vendor ID associated with an order.
         * Works for both split sub-orders and single-vendor orders.
         */
        public static function get_order_vendor_id($order): int
        {
            if (!is_a($order, 'WC_Order')) {
                $order = wc_get_order((int) $order);
            }
            if (!$order) {
                return 0;
            }

            // If a vendor is logged in, use the logged-in vendor's ID
            if (function_exists('wcfm_is_vendor') && wcfm_is_vendor()) {
                $vendor_id = get_current_user_id();
                if ($vendor_id) {
                    return $vendor_id;
                }
            }

            // 1. Sub-order meta.
            $vendor_id = (int) $order->get_meta('_order_vendor_id');
            if ($vendor_id) {
                return $vendor_id;
            }

            // 2. Query WCFM marketplace orders table.
            global $wpdb;
            $vendor_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT vendor_id FROM {$wpdb->prefix}wcfm_marketplace_orders WHERE order_id = %d LIMIT 1",
                $order->get_id()
            ));
            if ($vendor_id) {
                return $vendor_id;
            }

            // 3. Fallback: scan items.
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                if (function_exists('wcfm_get_vendor_id_by_post')) {
                    $vendor_id = (int) wcfm_get_vendor_id_by_post($product_id);
                }
                if (!$vendor_id) {
                    $vendor_id = (int) get_post_field('post_author', $product_id);
                }
                if ($vendor_id) {
                    return $vendor_id;
                }
            }

            return 0;
        }

        /**
         * Check if an order is eligible for Shiprocket fulfillment.
         * All standard vendor orders are eligible.
         */
        public static function is_order_eligible_for_fulfillment($order): bool
        {
            if (!is_a($order, 'WC_Order')) {
                $order = wc_get_order((int) $order);
            }
            if (!$order) {
                return false;
            }

            return true;
        }

        /**
         * Add "Retry Shiprocket Push" to WooCommerce order actions menu.
         */
        public static function register_retry_action(array $actions): array
        {
            $actions['thaaniyamhub_retry_shiprocket_push'] = __('Retry Shiprocket Push', 'thaaniyamhub-shiprocket-fulfillment');
            return $actions;
        }

        /**
         * Handle the "Retry Shiprocket Push" order action.
         */
        public static function handle_retry_action($order)
        {
            if (is_numeric($order)) {
                $order = wc_get_order($order);
            }
            if ($order) {
                self::push_to_shiprocket($order->get_id());
            }
        }
    }
}
