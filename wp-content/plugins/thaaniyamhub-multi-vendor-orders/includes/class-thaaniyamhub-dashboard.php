<?php
/**
 * Thaaniyam Hub Marketplace — Consolidated Dashboard Controls
 *
 * 1. WooCommerce Orders List UI
 *    - Row actions for Shiprocket fulfillment operations (Push, AWB, Label, Invoice, Cancel)
 *    - Bulk actions for Shiprocket logistics operations
 *
 * 2. Order Detail Meta Box
 *    - Shiprocket fulfillment panel on vendor order detail pages in WP-admin and WCFM
 *    - AWB generation, Label printing, Invoice printing, Order cancellation (AJAX)
 *
 * 3. Vendor Data & Access Isolation
 *    - Products list query filter (vendors only see their own products)
 *    - Orders list query filter (vendors only see vendor orders assigned to them)
 *
 * @package thaaniyamhub-multi-vendor-orders
 */

defined('ABSPATH') || exit;

class ThaaniyamHub_Dashboard
{

    /**
     * Boot hooks and filters.
     */
    public static function init()
    {
        // ---- Orders List: Enqueue Assets ----
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

        // ---- Row Actions in Orders Table ----
        add_filter('woocommerce_admin_order_actions', [__CLASS__, 'add_row_actions'], 20, 2);

        // ---- Bulk Actions ----
        // Legacy (post-based orders)
        add_filter('bulk_actions-edit-shop_order', [__CLASS__, 'register_bulk_actions']);
        add_filter('handle_bulk_actions-edit-shop_order', [__CLASS__, 'handle_bulk_actions'], 10, 3);
        // HPOS (wc-orders screen)
        add_filter('bulk_actions-woocommerce_page_wc-orders', [__CLASS__, 'register_bulk_actions']);
        add_filter('handle_bulk_actions-woocommerce_page_wc-orders', [__CLASS__, 'handle_bulk_actions'], 10, 3);

        // Admin Notices for Bulk Actions
        add_action('admin_notices', [__CLASS__, 'display_bulk_notices']);

        // ---- Vendor Product Isolation ----
        add_action('pre_get_posts', [__CLASS__, 'vendor_product_isolation']);

        // ---- Vendor Order Isolation & Access Control ----
        add_action('pre_get_posts', [__CLASS__, 'vendor_order_isolation_legacy']);
        add_filter('woocommerce_order_list_table_prepare_items_query_args', [__CLASS__, 'vendor_order_isolation_hpos']);
        add_action('admin_init', [__CLASS__, 'restrict_order_edit_access']);

        // ---- WCFM Frontend: Customer View Permission ----
        add_filter('wcfm_is_component_for_vendor', [__CLASS__, 'allow_customer_view_access_for_vendor'], 10, 4);

        // ---- WooCommerce Admin: Meta Boxes ----
        add_action('add_meta_boxes', [__CLASS__, 'add_commission_meta_boxes']);
        add_action('add_meta_boxes', [__CLASS__, 'register_meta_box']);

        // ---- AJAX Handlers ----
        add_action('wp_ajax_thaaniyamhub_sf_push_order', [__CLASS__, 'ajax_push_order']);
        add_action('wp_ajax_thaaniyamhub_sf_generate_awb', [__CLASS__, 'ajax_generate_awb']);
        add_action('wp_ajax_thaaniyamhub_sf_print_label', [__CLASS__, 'ajax_print_label']);
        add_action('wp_ajax_thaaniyamhub_sf_print_invoice', [__CLASS__, 'ajax_print_invoice']);
        add_action('wp_ajax_thaaniyamhub_sf_cancel_shipment', [__CLASS__, 'ajax_cancel_shipment']);
        add_action('wp_ajax_thaaniyamhub_sf_add_pickup_location', [__CLASS__, 'ajax_add_pickup_location']);
        add_action('wp_ajax_thaaniyamhub_sf_set_default_pickup', [__CLASS__, 'ajax_set_default_pickup']);
        add_action('wp_ajax_thaaniyamhub_sf_get_couriers', [__CLASS__, 'ajax_get_couriers']);
        add_action('wp_ajax_thaaniyamhub_sf_get_serviceability_pre_push', [__CLASS__, 'ajax_get_serviceability_pre_push']);
        add_action('wp_ajax_thaaniyamhub_sf_reassign_courier', [__CLASS__, 'ajax_reassign_courier']);
        add_action('wp_ajax_thaaniyamhub_sf_schedule_pickup', [__CLASS__, 'ajax_schedule_pickup']);
        add_action('wp_ajax_thaaniyamhub_sf_download_manifest', [__CLASS__, 'ajax_download_manifest']);
        add_action('wp_ajax_thaaniyamhub_sf_initiate_return', [__CLASS__, 'ajax_initiate_return']);

        // ---- User Profile Edit Fields ----
        add_action('show_user_profile', [__CLASS__, 'render_vendor_profile_fields']);
        add_action('edit_user_profile', [__CLASS__, 'render_vendor_profile_fields']);
        add_action('personal_options_update', [__CLASS__, 'save_vendor_profile_fields']);
        add_action('edit_user_profile_update', [__CLASS__, 'save_vendor_profile_fields']);

        // ---- Admin Order Action Menu Hooks ----
        add_filter('woocommerce_order_actions', ['ThaaniyamHub_Dispatch', 'register_retry_action']);
        add_action('woocommerce_order_action_thaaniyamhub_retry_shiprocket_push', ['ThaaniyamHub_Dispatch', 'handle_retry_action']);

        // ---- WCFM Frontend (Vendor Dashboard) ----
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_frontend_assets']);
        add_action('end_wcfm_orders_details', [__CLASS__, 'render_wcfm_panel']);
        add_action('wcfm_vendor_settings_update', [__CLASS__, 'save_wcfm_settings'], 10, 2);

        // ---- WCFM Marketplace: Cashfree Withdrawal & Vendor Payment Setup ----
        add_filter('wcfm_marketplace_withdrwal_payment_methods', [__CLASS__, 'register_cashfree_withdrawal_method']);
        add_filter('wcfm_marketplace_active_withdrwal_payment_methods', [__CLASS__, 'filter_active_withdrawal_methods']);
        add_filter('wcfm_marketplace_settings_fields_billing', [__CLASS__, 'add_cashfree_vendor_billing_fields'], 50, 2);
        add_action('wcfm_marketplace_settings_after_billing', [__CLASS__, 'render_cashfree_vendor_billing_script']);
        add_action('wcfmmp_admin_wcfm_vendor_commission_payment_settings_after', [__CLASS__, 'render_cashfree_vendor_billing_script']);
    }

    // =========================================================================
    // 0. CAPABILITY & PERMISSION HELPERS
    // =========================================================================

    public static function current_user_can_manage_order(int $order_id): bool
    {
        if (current_user_can('manage_woocommerce') || current_user_can('administrator')) {
            return true;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }

        $vendor_id = ThaaniyamHub_Dispatch::get_order_vendor_id($order);
        if ($vendor_id && $vendor_id === get_current_user_id()) {
            return true;
        }

        return false;
    }

    // =========================================================================
    // 0.1 REFUND STATUS HELPER
    // =========================================================================

    /**
     * Get active/recent refund status information for an order or sub-order.
     *
     * @param WC_Order|int $order_or_id Order object or order ID.
     * @return array|null Refund info array or null if no refund.
     */
    public static function get_order_refund_info($order_or_id): ?array
    {
        global $wpdb;
        static $cache = [];

        $order = ($order_or_id instanceof WC_Order) ? $order_or_id : wc_get_order($order_or_id);
        if (!$order) {
            return null;
        }

        $order_id = $order->get_id();
        if (isset($cache[$order_id])) {
            return $cache[$order_id];
        }

        $table = $wpdb->prefix . 'wcfm_marketplace_refund_request';
        $table_exists = ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table);

        $rows = [];
        if ($table_exists) {
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE order_id = %d ORDER BY ID DESC", $order_id));
        }

        $result = null;

        if (!empty($rows)) {
            $statuses = array_map(function ($r) {
                return strtolower($r->refund_status);
            }, $rows);

            $total_amt = 0;
            $reasons = [];
            foreach ($rows as $r) {
                $total_amt += (float) $r->refunded_amount;
                if (!empty($r->refund_reason) && !in_array($r->refund_reason, $reasons, true)) {
                    $reasons[] = $r->refund_reason;
                }
            }

            if (in_array('requested', $statuses, true) || in_array('pending', $statuses, true)) {
                $result = [
                    'status'    => 'requested',
                    'label'     => __('Refund Requested', 'thaaniyamhub-multi-vendor-orders'),
                    'amount'    => $total_amt,
                    'reasons'   => $reasons,
                    'badge_cls' => 'thaaniyamhub-badge-refund-requested',
                ];
            } elseif (in_array('completed', $statuses, true) || in_array('approved', $statuses, true)) {
                $result = [
                    'status'    => 'completed',
                    'label'     => __('Refund Completed', 'thaaniyamhub-multi-vendor-orders'),
                    'amount'    => $total_amt,
                    'reasons'   => $reasons,
                    'badge_cls' => 'thaaniyamhub-badge-refund-completed',
                ];
            } elseif (in_array('cancelled', $statuses, true) || in_array('rejected', $statuses, true)) {
                $result = [
                    'status'    => 'cancelled',
                    'label'     => __('Refund Cancelled', 'thaaniyamhub-multi-vendor-orders'),
                    'amount'    => $total_amt,
                    'reasons'   => $reasons,
                    'badge_cls' => 'thaaniyamhub-badge-refund-cancelled',
                ];
            }
        }

        // Fallback: Check order meta or WooCommerce refund total
        if (!$result) {
            if ($order->get_meta('_wcfm_refund_request') === 'yes' || $order->get_meta('_refund_requested') === 'yes') {
                $result = [
                    'status'    => 'requested',
                    'label'     => __('Refund Requested', 'thaaniyamhub-multi-vendor-orders'),
                    'amount'    => 0,
                    'reasons'   => [],
                    'badge_cls' => 'thaaniyamhub-badge-refund-requested',
                ];
            } elseif ((float) $order->get_total_refunded() > 0 || $order->get_status() === 'refunded') {
                $result = [
                    'status'    => 'completed',
                    'label'     => __('Refund Completed', 'thaaniyamhub-multi-vendor-orders'),
                    'amount'    => (float) $order->get_total_refunded(),
                    'reasons'   => [],
                    'badge_cls' => 'thaaniyamhub-badge-refund-completed',
                ];
            }
        }

        $cache[$order_id] = $result;
        return $result;
    }

    // =========================================================================
    // 2. ORDER LIST ROW ACTIONS
    // =========================================================================

    public static function add_row_actions(array $actions, WC_Order $order): array
    {
        if (!ThaaniyamHub_Dispatch::is_order_eligible_for_fulfillment($order)) {
            return $actions;
        }

        $order_id = $order->get_id();
        if (!self::current_user_can_manage_order($order_id)) {
            return $actions;
        }

        $record = ThaaniyamHub_Shiprocket_API::get_fulfillment($order_id);

        // 1. Push order (if not yet pushed)
        if (!$record || empty($record->shiprocket_order_id)) {
            if ('processing' === $order->get_status()) {
                $actions['ag_sf_push'] = [
                    'url' => '#',
                    'name' => __('Push to Shiprocket', 'thaaniyamhub-multi-vendor-orders'),
                    'action' => 'thaaniyamhub-sf-push thaaniyamhub-sf-row-action',
                    'class' => 'thaaniyamhub-sf-action-push',
                    'id' => $order_id,
                ];
            }
        } else {
            // 2. Generate AWB (if pushed, but no AWB)
            if (empty($record->awb_code) && 'cancelled' !== $record->fulfillment_status) {
                $actions['ag_sf_awb'] = [
                    'url' => '#',
                    'name' => __('Generate AWB', 'thaaniyamhub-multi-vendor-orders'),
                    'action' => 'thaaniyamhub-sf-awb thaaniyamhub-sf-row-action',
                    'class' => 'thaaniyamhub-sf-action-awb',
                    'id' => $order_id,
                ];
            }

            // 3. Print Shipping Label (if AWB exists)
            if (!empty($record->awb_code)) {
                $actions['ag_sf_label'] = [
                    'url' => '#',
                    'name' => __('Print Label', 'thaaniyamhub-multi-vendor-orders'),
                    'action' => 'thaaniyamhub-sf-label thaaniyamhub-sf-row-action',
                    'class' => 'thaaniyamhub-sf-action-label',
                    'id' => $order_id,
                ];
            }

            // 4. Download Manifest (if AWB exists)
            if (!empty($record->awb_code)) {
                $actions['ag_sf_manifest'] = [
                    'url' => !empty($record->manifest_url) ? esc_url($record->manifest_url) : '#',
                    'name' => __('Download Manifest', 'thaaniyamhub-multi-vendor-orders'),
                    'action' => 'thaaniyamhub-sf-manifest thaaniyamhub-sf-row-action',
                    'class' => 'thaaniyamhub-sf-action-manifest',
                    'id' => $order_id,
                ];
            }

            // 5. Print Invoice
            $actions['ag_sf_invoice'] = [
                'url' => '#',
                'name' => __('Print Commercial Invoice', 'thaaniyamhub-multi-vendor-orders'),
                'action' => 'thaaniyamhub-sf-invoice thaaniyamhub-sf-row-action',
                'class' => 'thaaniyamhub-sf-action-invoice',
                'id' => $order_id,
            ];

            // 6. Cancel Shipment
            if ('cancelled' !== $record->fulfillment_status) {
                $actions['ag_sf_cancel'] = [
                    'url' => '#',
                    'name' => __('Cancel Shiprocket Shipment', 'thaaniyamhub-multi-vendor-orders'),
                    'action' => 'thaaniyamhub-sf-cancel thaaniyamhub-sf-row-action',
                    'class' => 'thaaniyamhub-sf-action-cancel',
                    'id' => $order_id,
                ];
            }
        }

        return $actions;
    }

    // =========================================================================
    // 3. BULK ACTIONS
    // =========================================================================

    public static function register_bulk_actions(array $bulk_actions): array
    {
        $bulk_actions['thaaniyamhub_sf_bulk_push'] = __('🚚 Shiprocket: Push to Shiprocket', 'thaaniyamhub-multi-vendor-orders');
        $bulk_actions['thaaniyamhub_sf_bulk_awb'] = __('🚚 Shiprocket: Generate AWBs', 'thaaniyamhub-multi-vendor-orders');
        $bulk_actions['thaaniyamhub_sf_bulk_schedule'] = __('🚚 Shiprocket: Schedule Pickups', 'thaaniyamhub-multi-vendor-orders');
        $bulk_actions['thaaniyamhub_sf_bulk_label'] = __('🚚 Shiprocket: Print Labels', 'thaaniyamhub-multi-vendor-orders');
        $bulk_actions['thaaniyamhub_sf_bulk_manifest'] = __('🚚 Shiprocket: Download Manifests', 'thaaniyamhub-multi-vendor-orders');
        $bulk_actions['thaaniyamhub_sf_bulk_invoice'] = __('🚚 Shiprocket: Print Invoices', 'thaaniyamhub-multi-vendor-orders');
        $bulk_actions['thaaniyamhub_sf_bulk_cancel'] = __('🚚 Shiprocket: Cancel Shipments', 'thaaniyamhub-multi-vendor-orders');
        return $bulk_actions;
    }

    public static function handle_bulk_actions(string $redirect_to, string $action, array $ids): string
    {
        if (empty($ids)) {
            return $redirect_to;
        }

        $sub_order_ids = array_filter($ids, function ($id) {
            $order = wc_get_order($id);
            return $order && ThaaniyamHub_Dispatch::is_order_eligible_for_fulfillment($order) && self::current_user_can_manage_order((int) $id);
        });

        if (empty($sub_order_ids)) {
            return add_query_arg('thaaniyamhub_sf_err_no_suborders', 1, $redirect_to);
        }

        $api = new ThaaniyamHub_Shiprocket_API();
        $current_user_id = get_current_user_id();

        switch ($action) {
            case 'thaaniyamhub_sf_bulk_push':
                thaaniyamhub_log("Bulk Action: Push to Shiprocket initiated for sub-orders: [" . implode(', ', $sub_order_ids) . "] by user #" . $current_user_id);
                $success = 0;
                $fail = 0;
                foreach ($sub_order_ids as $id) {
                    $record = ThaaniyamHub_Shiprocket_API::get_fulfillment($id);
                    if (!$record || empty($record->shiprocket_order_id)) {
                        ThaaniyamHub_Dispatch::push_to_shiprocket($id);
                        $updated = ThaaniyamHub_Shiprocket_API::get_fulfillment($id);
                        if ($updated && !empty($updated->shiprocket_order_id)) {
                            $success++;
                        } else {
                            $fail++;
                        }
                    }
                }
                thaaniyamhub_log("Bulk Action: Push to Shiprocket completed. Succeeded: {$success}, Failed: {$fail}");
                return add_query_arg(['thaaniyamhub_sf_bulk_pushed' => $success, 'thaaniyamhub_sf_bulk_push_failed' => $fail], $redirect_to);

            case 'thaaniyamhub_sf_bulk_awb':
                thaaniyamhub_log("Bulk Action: Generate AWBs initiated for sub-orders: [" . implode(', ', $sub_order_ids) . "] by user #" . $current_user_id);
                $success = 0;
                $fail = 0;
                foreach ($sub_order_ids as $id) {
                    $record = ThaaniyamHub_Shiprocket_API::get_fulfillment($id);
                    if ($record && !empty($record->shiprocket_shipment_id) && empty($record->awb_code) && 'cancelled' !== $record->fulfillment_status) {
                        $sub_order = wc_get_order($id);
                        $courier_id = $sub_order ? (int) $sub_order->get_meta('_shiprocket_selected_courier_id') : 0;
                        $result = $api->assign_awb([
                            'shipment_id' => [(int) $record->shiprocket_shipment_id],
                            'courier_id' => $courier_id ?: null,
                        ], $id);

                        if (!is_wp_error($result)) {
                            $awb = $result['response']['data']['awb_code'] ?? ($result['awb_code'] ?? '');
                            $courier = $result['response']['data']['courier_name'] ?? ($result['courier_name'] ?? '');
                            $shipping_cost = 0.0;
                            if (isset($result['response']['data']['net_total'])) {
                                $shipping_cost = (float) $result['response']['data']['net_total'];
                            } elseif (isset($result['response']['data']['freight_charge'])) {
                                $shipping_cost = (float) $result['response']['data']['freight_charge'];
                            }

                            if ($awb) {
                                $tomorrow = date('Y-m-d', strtotime('+1 day'));
                                $pickup_res = $api->request_pickup([
                                    'shipment_id' => [(int) $record->shiprocket_shipment_id],
                                    'pickup_date' => [$tomorrow],
                                ], $id);
                                $p_date = '';
                                $p_tok = '';
                                if (!is_wp_error($pickup_res)) {
                                    $p_date = $pickup_res['response']['pickup_scheduled_date'] ?? ($pickup_res['pickup_scheduled_date'] ?? $tomorrow);
                                    $p_tok = $pickup_res['response']['pickup_token_number'] ?? ($pickup_res['pickup_token_number'] ?? '');
                                }

                                $m_res = $api->generate_manifest(['shipment_id' => [(int) $record->shiprocket_shipment_id]], $id);
                                $m_url = (!is_wp_error($m_res) && !empty($m_res['manifest_url'])) ? $m_res['manifest_url'] : '';
                                if (!$m_url && !empty($record->shiprocket_order_id)) {
                                    $p_res = $api->print_manifest(['order_ids' => [(int) $record->shiprocket_order_id]], $id);
                                    if (!is_wp_error($p_res) && !empty($p_res['manifest_url'])) {
                                        $m_url = $p_res['manifest_url'];
                                    }
                                }

                                $f_status = $p_date ? 'pickup_scheduled' : 'assigned';
                                $up_data = [
                                    'awb_code'              => $awb,
                                    'courier_name'          => $courier,
                                    'pickup_scheduled_date' => $p_date,
                                    'pickup_token_number'   => $p_tok,
                                ];
                                if ($m_url) {
                                    $up_data['manifest_url'] = $m_url;
                                }
                                ThaaniyamHub_Shiprocket_API::update_status($id, $f_status, $up_data);
                                $order = wc_get_order($id);
                                if ($order) {
                                    $order->update_meta_data('_shiprocket_actual_shipping_cost', $shipping_cost);
                                    if ($p_date) {
                                        $order->update_meta_data('_shiprocket_pickup_scheduled_date', $p_date);
                                    }
                                    if ($p_tok) {
                                        $order->update_meta_data('_shiprocket_pickup_token_number', $p_tok);
                                    }
                                    if ($m_url) {
                                        $order->update_meta_data('_shiprocket_manifest_url', $m_url);
                                    }
                                    $note = sprintf(__('📦 AWB generated via bulk action: %s (%s) - Shipping Cost: %s', 'thaaniyamhub-multi-vendor-orders'), $awb, $courier, wc_price($shipping_cost));
                                    if ($p_date) {
                                        $note .= ' ' . sprintf(__('📅 Pickup scheduled: %s%s', 'thaaniyamhub-multi-vendor-orders'), $p_date, $p_tok ? " (Token: {$p_tok})" : '');
                                    }
                                    if ($m_url) {
                                        $note .= ' ' . sprintf(__('📄 Manifest: <a href="%s" target="_blank">Download Manifest</a>', 'thaaniyamhub-multi-vendor-orders'), esc_url($m_url));
                                    }
                                    $order->add_order_note($note);
                                    $order->save();
                                    if (class_exists('ThaaniyamHub_Ledger')) {
                                        ThaaniyamHub_Ledger::update_shiprocket_cost($id, (float) $shipping_cost, $courier, $awb);
                                    }
                                }
                                thaaniyamhub_log("Bulk Action: Generate AWB & Pickup succeeded for Order #{$id}. AWB: {$awb}, Courier: {$courier}, Pickup: {$p_date}");
                                $success++;
                                continue;
                            }
                        }
                        thaaniyamhub_log("Bulk Action: Generate AWB failed/skipped for Order #{$id}." . (is_wp_error($result) ? " Error: " . $result->get_error_message() : ""), 'error');
                        $fail++;
                    }
                }
                thaaniyamhub_log("Bulk Action: Generate AWBs completed. Succeeded: {$success}, Failed: {$fail}");
                return add_query_arg(['thaaniyamhub_sf_bulk_awb_ok' => $success, 'thaaniyamhub_sf_bulk_awb_fail' => $fail], $redirect_to);

            case 'thaaniyamhub_sf_bulk_label':
                thaaniyamhub_log("Bulk Action: Print Labels initiated for sub-orders: [" . implode(', ', $sub_order_ids) . "] by user #" . $current_user_id);
                $shipment_ids = [];
                foreach ($sub_order_ids as $id) {
                    $record = ThaaniyamHub_Shiprocket_API::get_fulfillment($id);
                    if ($record && !empty($record->shiprocket_shipment_id) && !empty($record->awb_code)) {
                        $shipment_ids[] = (int) $record->shiprocket_shipment_id;
                    }
                }
                if (!empty($shipment_ids)) {
                    thaaniyamhub_log("Bulk Action: Requesting label generation from Shiprocket for Shipment IDs: [" . implode(', ', $shipment_ids) . "]");
                    $result = $api->generate_label(['shipment_id' => $shipment_ids], $sub_order_ids[0]);
                    if (!is_wp_error($result) && !empty($result['label_url'])) {
                        foreach ($sub_order_ids as $id) {
                            ThaaniyamHub_Shiprocket_API::update_status($id, 'manifested', ['shipping_label_url' => $result['label_url']]);
                        }
                        thaaniyamhub_log("Bulk Action: Print Labels succeeded. Label URL: {$result['label_url']}");
                        wp_redirect($result['label_url']);
                        exit;
                    } else {
                        thaaniyamhub_log("Bulk Action: Print Labels failed." . (is_wp_error($result) ? " Error: " . $result->get_error_message() : ""), 'error');
                    }
                } else {
                    thaaniyamhub_log("Bulk Action: Print Labels aborted - no valid shipment IDs with AWBs found in selection.", 'warning');
                }
                return add_query_arg('thaaniyamhub_sf_bulk_label_fail', 1, $redirect_to);

            case 'thaaniyamhub_sf_bulk_invoice':
                thaaniyamhub_log("Bulk Action: Print Invoices initiated for sub-orders: [" . implode(', ', $sub_order_ids) . "] by user #" . $current_user_id);
                $order_ids = [];
                foreach ($sub_order_ids as $id) {
                    $record = ThaaniyamHub_Shiprocket_API::get_fulfillment($id);
                    if ($record && !empty($record->shiprocket_order_id)) {
                        $order_ids[] = (int) $record->shiprocket_order_id;
                    }
                }
                if (!empty($order_ids)) {
                    thaaniyamhub_log("Bulk Action: Requesting invoice generation from Shiprocket for Shiprocket Order IDs: [" . implode(', ', $order_ids) . "]");
                    $result = $api->generate_invoice(['ids' => $order_ids], $sub_order_ids[0]);
                    if (!is_wp_error($result) && !empty($result['invoice_url'])) {
                        foreach ($sub_order_ids as $id) {
                            ThaaniyamHub_Shiprocket_API::update_status($id, 'manifested', ['commercial_invoice_url' => $result['invoice_url']]);
                        }
                        thaaniyamhub_log("Bulk Action: Print Invoices succeeded. Invoice URL: {$result['invoice_url']}");
                        wp_redirect($result['invoice_url']);
                        exit;
                    } else {
                        thaaniyamhub_log("Bulk Action: Print Invoices failed." . (is_wp_error($result) ? " Error: " . $result->get_error_message() : ""), 'error');
                    }
                } else {
                    thaaniyamhub_log("Bulk Action: Print Invoices aborted - no valid Shiprocket order IDs found in selection.", 'warning');
                }
                return add_query_arg('thaaniyamhub_sf_bulk_invoice_fail', 1, $redirect_to);

            case 'thaaniyamhub_sf_bulk_manifest':
                thaaniyamhub_log("Bulk Action: Download Manifests initiated for sub-orders: [" . implode(', ', $sub_order_ids) . "] by user #" . $current_user_id);
                $shipment_ids = [];
                $order_ids = [];
                foreach ($sub_order_ids as $id) {
                    $record = ThaaniyamHub_Shiprocket_API::get_fulfillment($id);
                    if ($record && !empty($record->shiprocket_shipment_id) && !empty($record->awb_code)) {
                        $shipment_ids[] = (int) $record->shiprocket_shipment_id;
                        if (!empty($record->shiprocket_order_id)) {
                            $order_ids[] = (int) $record->shiprocket_order_id;
                        }
                    }
                }
                if (!empty($shipment_ids)) {
                    thaaniyamhub_log("Bulk Action: Requesting manifest generation from Shiprocket for Shipment IDs: [" . implode(', ', $shipment_ids) . "]");
                    $result = $api->generate_manifest(['shipment_id' => $shipment_ids], $sub_order_ids[0]);
                    $manifest_url = (!is_wp_error($result) && !empty($result['manifest_url'])) ? $result['manifest_url'] : '';

                    if (!$manifest_url && !empty($order_ids)) {
                        $print_result = $api->print_manifest(['order_ids' => $order_ids], $sub_order_ids[0]);
                        if (!is_wp_error($print_result) && !empty($print_result['manifest_url'])) {
                            $manifest_url = $print_result['manifest_url'];
                        }
                    }

                    if ($manifest_url) {
                        foreach ($sub_order_ids as $id) {
                            ThaaniyamHub_Shiprocket_API::update_status($id, 'manifested', ['manifest_url' => $manifest_url]);
                            $order = wc_get_order($id);
                            if ($order) {
                                $order->update_meta_data('_shiprocket_manifest_url', $manifest_url);
                                $order->add_order_note(sprintf(__('📋 Shiprocket manifest generated via bulk action: <a href="%s" target="_blank">%s</a>', 'thaaniyamhub-multi-vendor-orders'), esc_url($manifest_url), esc_url($manifest_url)));
                                $order->save();
                            }
                        }
                        thaaniyamhub_log("Bulk Action: Download Manifests succeeded. Manifest URL: {$manifest_url}");
                        wp_redirect($manifest_url);
                        exit;
                    } else {
                        thaaniyamhub_log("Bulk Action: Download Manifests failed." . (is_wp_error($result) ? " Error: " . $result->get_error_message() : ""), 'error');
                    }
                } else {
                    thaaniyamhub_log("Bulk Action: Download Manifests aborted - no valid shipments with AWBs found in selection.", 'warning');
                }
                return add_query_arg('thaaniyamhub_sf_bulk_manifest_fail', 1, $redirect_to);

            case 'thaaniyamhub_sf_bulk_cancel':
                thaaniyamhub_log("Bulk Action: Cancel Shipments initiated for sub-orders: [" . implode(', ', $sub_order_ids) . "] by user #" . $current_user_id);
                $success = 0;
                $fail = 0;
                foreach ($sub_order_ids as $id) {
                    $record = ThaaniyamHub_Shiprocket_API::get_fulfillment($id);
                    if ($record && !empty($record->shiprocket_order_id) && 'cancelled' !== $record->fulfillment_status) {
                        $result = $api->cancel_order([$record->shiprocket_order_id], $id);
                        if (!is_wp_error($result)) {
                            ThaaniyamHub_Shiprocket_API::update_status($id, 'cancelled');
                            $order = wc_get_order($id);
                            if ($order) {
                                $order->add_order_note(__('❌ Shiprocket shipment cancelled via bulk action.', 'thaaniyamhub-multi-vendor-orders'));
                                $order->set_status('cancelled');
                                $order->save();
                            }
                            thaaniyamhub_log("Bulk Action: Cancel Shipment succeeded for Order #{$id} (Shiprocket Order: #{$record->shiprocket_order_id})");
                            $success++;
                        } else {
                            thaaniyamhub_log("Bulk Action: Cancel Shipment failed for Order #{$id} (Shiprocket Order: #{$record->shiprocket_order_id}). Error: " . $result->get_error_message(), 'error');
                            $fail++;
                        }
                    }
                }
                thaaniyamhub_log("Bulk Action: Cancel Shipments completed. Succeeded: {$success}, Failed: {$fail}");
                return add_query_arg(['thaaniyamhub_sf_bulk_cancelled' => $success, 'thaaniyamhub_sf_bulk_cancel_failed' => $fail], $redirect_to);

            case 'thaaniyamhub_sf_bulk_schedule':
                thaaniyamhub_log("Bulk Action: Schedule Pickups initiated for sub-orders: [" . implode(', ', $sub_order_ids) . "] by user #" . $current_user_id);
                $success = 0;
                $fail = 0;
                foreach ($sub_order_ids as $id) {
                    $record = ThaaniyamHub_Shiprocket_API::get_fulfillment($id);
                    if ($record && !empty($record->shiprocket_shipment_id) && !empty($record->awb_code) && in_array($record->fulfillment_status, ['assigned', 'manifested'], true)) {
                        $result = $api->request_pickup(['shipment_id' => [(int) $record->shiprocket_shipment_id]], $id);
                        if (!is_wp_error($result)) {
                            ThaaniyamHub_Shiprocket_API::update_status($id, 'pickup_scheduled');
                            $order = wc_get_order($id);
                            if ($order) {
                                $order->add_order_note(sprintf(__('📅 Shiprocket pickup scheduled via bulk action for Shipment: %s.', 'thaaniyamhub-multi-vendor-orders'), $record->shiprocket_shipment_id));
                                $order->save();
                            }
                            thaaniyamhub_log("Bulk Action: Schedule Pickup succeeded for Order #{$id} (Shipment: #{$record->shiprocket_shipment_id})");
                            $success++;
                        } else {
                            thaaniyamhub_log("Bulk Action: Schedule Pickup failed for Order #{$id} (Shipment: #{$record->shiprocket_shipment_id}). Error: " . $result->get_error_message(), 'error');
                            $fail++;
                        }
                    }
                }
                thaaniyamhub_log("Bulk Action: Schedule Pickups completed. Succeeded: {$success}, Failed: {$fail}");
                return add_query_arg(['thaaniyamhub_sf_bulk_scheduled' => $success, 'thaaniyamhub_sf_bulk_schedule_failed' => $fail], $redirect_to);

            default:
                return $redirect_to;
        }
    }

    public static function display_bulk_notices()
    {
        if (isset($_GET['thaaniyamhub_sf_err_no_suborders'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Error: Shiprocket actions can only be executed on vendor sub-orders.', 'thaaniyamhub-multi-vendor-orders') . '</p></div>';
        }

        if (isset($_GET['thaaniyamhub_sf_bulk_pushed'])) {
            $ok = (int) $_GET['thaaniyamhub_sf_bulk_pushed'];
            $no = (int) $_GET['thaaniyamhub_sf_bulk_push_failed'];
            echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(esc_html__('Shiprocket Bulk Push: %d order(s) pushed successfully. %d failed.', 'thaaniyamhub-multi-vendor-orders'), $ok, $no) . '</p></div>';
        }

        if (isset($_GET['thaaniyamhub_sf_bulk_awb_ok'])) {
            $ok = (int) $_GET['thaaniyamhub_sf_bulk_awb_ok'];
            $no = (int) $_GET['thaaniyamhub_sf_bulk_awb_fail'];
            echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(esc_html__('Shiprocket Bulk AWB: %d shipment(s) assigned AWB successfully. %d failed.', 'thaaniyamhub-multi-vendor-orders'), $ok, $no) . '</p></div>';
        }

        if (isset($_GET['thaaniyamhub_sf_bulk_cancelled'])) {
            $ok = (int) $_GET['thaaniyamhub_sf_bulk_cancelled'];
            $no = (int) $_GET['thaaniyamhub_sf_bulk_cancel_failed'];
            echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(esc_html__('Shiprocket Bulk Cancel: %d shipment(s) cancelled. %d failed.', 'thaaniyamhub-multi-vendor-orders'), $ok, $no) . '</p></div>';
        }

        if (isset($_GET['thaaniyamhub_sf_bulk_scheduled'])) {
            $ok = (int) $_GET['thaaniyamhub_sf_bulk_scheduled'];
            $no = (int) $_GET['thaaniyamhub_sf_bulk_schedule_failed'];
            echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(esc_html__('Shiprocket Bulk Schedule: %d pickup(s) scheduled. %d failed.', 'thaaniyamhub-multi-vendor-orders'), $ok, $no) . '</p></div>';
        }

        if (isset($_GET['thaaniyamhub_sf_bulk_label_fail'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Shiprocket Bulk Labels: Failed to generate shipping labels. Verify AWBs are assigned first.', 'thaaniyamhub-multi-vendor-orders') . '</p></div>';
        }

        if (isset($_GET['thaaniyamhub_sf_bulk_invoice_fail'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Shiprocket Bulk Invoices: Failed to generate commercial invoices.', 'thaaniyamhub-multi-vendor-orders') . '</p></div>';
        }

        if (isset($_GET['thaaniyamhub_sf_bulk_manifest_fail'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Shiprocket Bulk Manifest: Failed to generate manifests. Please ensure AWBs are assigned and pickups are scheduled.', 'thaaniyamhub-multi-vendor-orders') . '</p></div>';
        }
    }

    // =========================================================================
    // 4. ADMIN ORDER DETAIL META BOXES
    // =========================================================================

    public static function add_commission_meta_boxes()
    {
        $screens = ['shop_order', 'woocommerce_page_wc-orders'];
        foreach ($screens as $screen) {
            add_meta_box(
                'thaaniyamhub_commission_details_metabox',
                __('Thaaniyam Hub Commission Details', 'thaaniyamhub-multi-vendor-orders'),
                [__CLASS__, 'render_commission_meta_box'],
                $screen,
                'normal',
                'high'
            );
        }
    }

    public static function register_meta_box()
    {
        $screens = ['shop_order', 'woocommerce_page_wc-orders'];
        foreach ($screens as $screen) {
            add_meta_box(
                'thaaniyamhub-sf-fulfillment-metabox',
                '🚚 ' . __('Shiprocket Fulfillment Controls', 'thaaniyamhub-multi-vendor-orders'),
                [__CLASS__, 'render_meta_box'],
                $screen,
                'side',
                'high'
            );
        }
    }

    public static function render_commission_meta_box($post_or_order)
    {
        $order = ($post_or_order instanceof WC_Order) ? $post_or_order : wc_get_order($post_or_order->ID);
        if (!$order) {
            return;
        }

        $order_id = $order->get_id();
        global $wpdb;
        $table = $wpdb->prefix . 'thaaniyamhub_vendor_ledger';

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE sub_order_id = %d OR parent_order_id = %d LIMIT 1", $order_id, $order_id));
        if ($row) {
            self::render_single_vendor_order_commission_view($row);
        } else {
            echo '<p>' . esc_html__('No commission details found in ledger for this order.', 'thaaniyamhub-multi-vendor-orders') . '</p>';
        }
    }

    /**
     * Render a standard WooCommerce question mark help tip.
     *
     * @param string $tip_text
     * @return string
     */
    private static function help_tip(string $tip_text): string
    {
        if (function_exists('wc_help_tip')) {
            return wc_help_tip($tip_text, true);
        }
        return sprintf(
            '<span class="woocommerce-help-tip" data-tip="%s" aria-label="%s" style="cursor:help;margin-left:5px;"></span>',
            esc_attr($tip_text),
            esc_attr($tip_text)
        );
    }

    private static function render_suborder_commission_view($row, $parent_id)
    {
        self::render_order_commission_detail_view($row, $parent_id);
    }

    private static function render_single_vendor_order_commission_view($row)
    {
        self::render_order_commission_detail_view($row, 0);
    }

    private static function render_order_commission_detail_view($row, $parent_id = 0)
    {
        $vendor_name = thaaniyamhub_get_vendor_name_by_vendor_id((int) $row->vendor_id);
        $parent_url  = $parent_id > 0 ? admin_url('admin.php?page=wc-orders&action=edit&id=' . $parent_id) : '';
        if ($parent_id > 0 && !get_option('woocommerce_enable_order_tracking')) {
            $parent_url = get_edit_post_link($parent_id) ?: $parent_url;
        }

        $refunded_amount = (float) ($row->refunded_amount ?? 0);
        $total_inflow    = (float) ($row->total_incoming ?: $row->gross_sales + $row->shipping_charge);
        $net_inflow      = max(0.0, round($total_inflow - $refunded_amount, 2));
        $has_refund      = ($refunded_amount > 0);

        // Fetch refund records on sub-order for detailed transparent audit
        $refund_notes = [];
        $sub_order_obj = wc_get_order((int) $row->sub_order_id);
        if ($sub_order_obj) {
            $refund_objs = $sub_order_obj->get_refunds();
            if (!empty($refund_objs)) {
                foreach ($refund_objs as $ref) {
                    $r_amt = abs((float) $ref->get_amount());
                    $r_reason = trim((string) $ref->get_reason());
                    $r_date = $ref->get_date_created() ? $ref->get_date_created()->date_i18n('d M Y, H:i') : '';
                    $refund_notes[] = sprintf(
                        'Refund #%d: %s (%s)%s',
                        $ref->get_id(),
                        wp_strip_all_tags(wc_price($r_amt)),
                        $r_date ?: 'Recent',
                        $r_reason ? ' — Reason: "' . esc_html($r_reason) . '"' : ''
                    );
                }
            }
        }

        $comm_deducted = (float) $row->commission_deducted;
        $catalog_price = (float) ($row->item_subtotal ?: $row->gross_sales);
        if ($comm_deducted <= 0) {
            $comm_pct_str = '0%';
        } else {
            $rate = (float) $row->commission_rate;
            if ($rate <= 0 && $catalog_price > 0) {
                $rate = round(($comm_deducted / $catalog_price) * 100, 2);
            }
            $comm_pct_str = ($rate > 0) ? $rate . '%' : '0%';
        }

        $is_profit = (float) $row->net_profit >= 0;
        $profit_color = $is_profit ? '#059669' : '#dc2626';

        $payout_status_label = strtoupper($row->payout_status ?: 'PENDING');
        $payout_bg = ('disbursed' === strtolower($row->payout_status)) ? '#d1fae5' : ('refunded' === strtolower($row->payout_status) ? '#fee2e2' : '#fef3c7');
        $payout_fg = ('disbursed' === strtolower($row->payout_status)) ? '#065f46' : ('refunded' === strtolower($row->payout_status) ? '#991b1b' : '#92400e');

        $has_awb = !empty($row->shiprocket_awb);
        $shipping_cost = (float) $row->shiprocket_shipping_cost;

        $gateway_fees = round((float) $row->gateway_fee + (float) $row->gateway_tax, 2);
        $payout_fee = round((float) $row->other_service_cost, 2);
        $total_processing_fee = round($gateway_fees + $payout_fee, 2);

        $discount_val = (float) $row->discount_total;
        if ($has_refund) {
            $reason_text = sprintf(__('Order has a processed customer refund of %s. Commission, vendor net payout, and net profit are calculated on retained sales.', 'thaaniyamhub-multi-vendor-orders'), wp_strip_all_tags(wc_price($refunded_amount)));
        } elseif ($discount_val > 0 && $comm_deducted <= 0) {
            $reason_text = sprintf(__('Platform absorbed %s coupon discount & payment processing fees under 0%% commission tier.', 'thaaniyamhub-multi-vendor-orders'), wp_strip_all_tags(wc_price($discount_val)));
        } elseif ($comm_deducted <= 0) {
            $reason_text = __('0% commission tier: Platform absorbs banking gateway charges & payout transfer fees.', 'thaaniyamhub-multi-vendor-orders');
        } elseif ($discount_val > 0) {
            $reason_text = sprintf(__('Platform absorbed %s coupon discount for this promotional order.', 'thaaniyamhub-multi-vendor-orders'), wp_strip_all_tags(wc_price($discount_val)));
        } else {
            $reason_text = __('Standard platform commission and payment transaction fees applied.', 'thaaniyamhub-multi-vendor-orders');
        }

        $shipping_label_short = $has_awb ? __('Shiprocket Freight', 'thaaniyamhub-multi-vendor-orders') : __('Logistics (Estimated)', 'thaaniyamhub-multi-vendor-orders');

        echo '<div class="thaaniyamhub-commission-view" style="padding: 4px 0;">';
        echo '<style>
            .thaaniyamhub-commission-view table.wp-list-table th { vertical-align: middle; font-weight: 600; color: #1e293b; padding: 10px 14px; }
            .thaaniyamhub-commission-view table.wp-list-table td { vertical-align: middle; padding: 10px 14px; }
            .thaaniyamhub-commission-view .woocommerce-help-tip { vertical-align: middle; margin-left: 6px; cursor: help; }
        </style>';

        if ($has_refund) {
            echo '<div style="margin-bottom: 12px; padding: 12px 16px; background: #fef2f2; border: 1px solid #fecaca; border-left: 4px solid #dc2626; border-radius: 8px;">';
            echo '<div style="display: flex; align-items: center; gap: 8px; font-weight: 700; color: #991b1b; font-size: 13px;">';
            echo '<span>⚠️</span><span>' . sprintf(esc_html__('Customer Refund Processed: %s', 'thaaniyamhub-multi-vendor-orders'), wp_strip_all_tags(wc_price($refunded_amount))) . '</span>';
            echo '</div>';
            if (!empty($refund_notes)) {
                echo '<div style="margin-top: 6px; font-size: 12px; color: #7f1d1d;">';
                foreach ($refund_notes as $r_note) {
                    echo '<div>• ' . esc_html($r_note) . '</div>';
                }
                echo '</div>';
            }
            echo '<div style="margin-top: 6px; font-size: 11.5px; color: #b91c1c;">';
            echo esc_html__('Note: Platform commission, vendor net payout, and net profit calculations have been adjusted to reflect this refund.', 'thaaniyamhub-multi-vendor-orders');
            echo '</div>';
            echo '</div>';
        }

        echo '<table class="wp-list-table widefat fixed striped" style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">';
        echo '<tbody>';

        if ($parent_id > 0) {
            printf(
                '<tr><th style="width: 270px;"><strong>%s</strong>%s</th><td><a href="%s" style="font-weight:700;color:#4f46e5;">#%d</a></td></tr>',
                esc_html__('Parent Order', 'thaaniyamhub-multi-vendor-orders'),
                self::help_tip(__('Original checkout order containing all customer items across vendors.', 'thaaniyamhub-multi-vendor-orders')),
                esc_url($parent_url),
                esc_html($parent_id)
            );
        }

        printf(
            '<tr><th style="width: 270px;"><strong>%s</strong>%s</th><td><strong>%s</strong> <span style="color:#64748b;">(ID: %d)</span></td></tr>',
            esc_html__('Vendor', 'thaaniyamhub-multi-vendor-orders'),
            self::help_tip(__('The registered marketplace vendor / seller fulfilling this order.', 'thaaniyamhub-multi-vendor-orders')),
            esc_html($vendor_name),
            esc_html($row->vendor_id)
        );

        printf(
            '<tr><th><strong>%s</strong>%s</th><td>%s</td></tr>',
            esc_html__('Product Catalog Price', 'thaaniyamhub-multi-vendor-orders'),
            self::help_tip(__('Original product catalog subtotal before any promotional discounts or coupon codes are applied.', 'thaaniyamhub-multi-vendor-orders')),
            wp_kses_post(wc_price($catalog_price))
        );

        if ($discount_val > 0) {
            printf(
                '<tr><th><strong>%s</strong>%s</th><td style="color:#dc2626;font-weight:600;">-%s <small style="color:#b91c1c;margin-left:6px;font-weight:600;">(%s: %s)</small></td></tr>',
                esc_html__('Admin Discount / Coupon', 'thaaniyamhub-multi-vendor-orders'),
                self::help_tip(__('Promotional discount or coupon funded by Thaaniyam Hub. The vendor receives their full agreed share based on catalog price; this discount is absorbed by the platform.', 'thaaniyamhub-multi-vendor-orders')),
                wp_kses_post(wc_price($discount_val)),
                esc_html__('Code', 'thaaniyamhub-multi-vendor-orders'),
                esc_html($row->coupon_codes ?: 'Discount')
            );
        }

        printf(
            '<tr><th><strong>%s</strong>%s</th><td><strong>%s</strong>%s</td></tr>',
            esc_html__('Customer Order Total (Gross Inflow)', 'thaaniyamhub-multi-vendor-orders'),
            self::help_tip(__('Total gross cash inflow received from customer at checkout (post-discount product price + shipping charges + taxes).', 'thaaniyamhub-multi-vendor-orders')),
            wp_kses_post(wc_price($total_inflow)),
            ((float)$row->shipping_charge > 0 ? sprintf(' <small style="color:#64748b;">(incl. %s shipping)</small>', wp_strip_all_tags(wc_price($row->shipping_charge))) : '')
        );

        if ($has_refund) {
            printf(
                '<tr><th><strong>%s</strong>%s</th><td style="color:#dc2626;font-weight:700;">-%s <small style="background:#fee2e2;color:#991b1b;padding:2px 8px;border-radius:4px;margin-left:6px;font-weight:600;">Refund Deducted</small></td></tr>',
                esc_html__('Customer Refund', 'thaaniyamhub-multi-vendor-orders'),
                self::help_tip(__('Amount refunded back to customer via WooCommerce / Cashfree.', 'thaaniyamhub-multi-vendor-orders')),
                wp_kses_post(wc_price($refunded_amount))
            );

            printf(
                '<tr><th><strong>%s</strong>%s</th><td><strong style="color:#059669;font-size:13.5px;">%s</strong> <small style="color:#065f46;font-weight:600;">(Retained)</small></td></tr>',
                esc_html__('Net Customer Inflow', 'thaaniyamhub-multi-vendor-orders'),
                self::help_tip(__('Net customer funds retained after refund deduction.', 'thaaniyamhub-multi-vendor-orders')),
                wp_kses_post(wc_price($net_inflow))
            );
        }

        printf(
            '<tr><th><strong>%s</strong>%s</th><td><span style="color:#4f46e5;font-weight:700;">%s (%s)</span>%s</td></tr>',
            esc_html__('Platform Gross Commission', 'thaaniyamhub-multi-vendor-orders'),
            self::help_tip(__('Gross platform commission fee earned by Thaaniyam Hub from vendor catalog sales based on the agreed tier (calculated on net retained catalog price).', 'thaaniyamhub-multi-vendor-orders')),
            wp_kses_post(wc_price($comm_deducted)),
            esc_html($comm_pct_str),
            ($comm_deducted <= 0 ? ' <span style="background:#f1f5f9;color:#475569;font-size:10px;font-weight:600;padding:2px 6px;border-radius:4px;margin-left:6px;">0% Commission Tier (Vendor receives 100%)</span>' : '')
        );

        printf(
            '<tr><th><strong>%s</strong>%s</th><td>%s</td></tr>',
            esc_html__('Tax on Commission (18% GST)', 'thaaniyamhub-multi-vendor-orders'),
            self::help_tip(__('18% GST applicable on the platform commission earned by Thaaniyam Hub.', 'thaaniyamhub-multi-vendor-orders')),
            wp_kses_post(wc_price($row->commission_tax))
        );

        printf(
            '<tr><th><strong>%s</strong>%s</th><td><strong style="font-size:13px;color:#1e40af;">%s</strong>%s</td></tr>',
            esc_html__('Vendor Net Payout', 'thaaniyamhub-multi-vendor-orders'),
            self::help_tip(__('The net disbursement amount payable to the vendor: Retained Product Catalog Price minus Platform Commission (plus Shipping if vendor delivers).', 'thaaniyamhub-multi-vendor-orders')),
            wp_kses_post(wc_price($row->vendor_net_payout)),
            ($has_refund && (float)$row->vendor_net_payout <= 0 ? ' <small style="background:#fee2e2;color:#991b1b;padding:2px 6px;border-radius:4px;margin-left:6px;font-weight:600;">Fully Refunded</small>' : '')
        );

        if ($shipping_cost > 0) {
            if ($has_awb) {
                printf(
                    '<tr><th><strong>%s</strong>%s</th><td>%s <small style="background:#ffedd5;color:#9a3412;padding:2px 8px;border-radius:4px;margin-left:6px;font-weight:600;">📦 AWB: %s%s</small></td></tr>',
                    esc_html__('Shiprocket Actual Logistics Cost', 'thaaniyamhub-multi-vendor-orders'),
                    self::help_tip(__('Actual courier freight cost billed by Shiprocket for dispatching this shipment.', 'thaaniyamhub-multi-vendor-orders')),
                    wp_kses_post(wc_price($shipping_cost)),
                    esc_html($row->shiprocket_awb),
                    (!empty($row->shiprocket_courier_name) ? ' (' . esc_html($row->shiprocket_courier_name) . ')' : '')
                );
            } else {
                printf(
                    '<tr><th><strong>%s</strong>%s</th><td>%s <small style="background:#f1f5f9;color:#64748b;padding:2px 8px;border-radius:4px;margin-left:6px;font-weight:600;border:1px dashed #cbd5e1;">⏱️ Estimated (Pending Shiprocket dispatch)</small></td></tr>',
                    esc_html__('Estimated Logistics Cost', 'thaaniyamhub-multi-vendor-orders'),
                    self::help_tip(__('Estimated shipping cost based on customer checkout shipping charge. This order has not yet been pushed to Shiprocket. Once dispatched and an AWB is generated, this row will automatically update with the exact courier freight cost billed by Shiprocket.', 'thaaniyamhub-multi-vendor-orders')),
                    wp_kses_post(wc_price($shipping_cost))
                );
            }
        }

        if ($total_processing_fee > 0) {
            printf(
                '<tr><th><strong>%s</strong>%s</th><td>%s <small style="color:#64748b;margin-left:6px;">(Cashfree PG: %s + Payout Transfer: %s)</small></td></tr>',
                esc_html__('Payment Gateway & Transfer Fees', 'thaaniyamhub-multi-vendor-orders'),
                self::help_tip(__('Cashfree Payment Gateway charges (~2% + 18% GST on customer checkout) and bank payout transfer fee (₹2.50 + 18% GST).', 'thaaniyamhub-multi-vendor-orders')),
                wp_kses_post(wc_price($total_processing_fee)),
                wp_strip_all_tags(wc_price($gateway_fees)),
                wp_strip_all_tags(wc_price($payout_fee))
            );
        }

        // Net Admin Profit with interactive / transparent breakdown
        $breakdown_html = sprintf(
            '<div style="font-size:11.5px;line-height:1.6;color:%s;margin-top:8px;padding:10px 14px;background:%s;border:1px solid %s;border-radius:8px;box-shadow:0 1px 2px rgba(0,0,0,0.03);">' .
                '<div style="font-weight:700;margin-bottom:6px;display:flex;align-items:center;gap:6px;">' .
                    '<span>📊</span>' .
                    '<span>%s</span>' .
                '</div>' .
                '<table style="width:100%%;max-width:440px;font-size:11.5px;border-collapse:collapse;color:%s;">' .
                    '<tr><td style="padding:2px 0;">%s:</td><td style="text-align:right;font-weight:700;color:#059669;">+%s</td></tr>' .
                    ($has_refund ? sprintf('<tr><td style="padding:2px 0;">%s:</td><td style="text-align:right;font-weight:700;color:#dc2626;">-%s</td></tr><tr><td style="padding:2px 0;font-weight:700;">%s:</td><td style="text-align:right;font-weight:700;color:#059669;">=%s</td></tr>', esc_html__('Customer Refund', 'thaaniyamhub-multi-vendor-orders'), wp_strip_all_tags(wc_price($refunded_amount)), esc_html__('Net Inflow', 'thaaniyamhub-multi-vendor-orders'), wp_strip_all_tags(wc_price($net_inflow))) : '') .
                    '<tr><td style="padding:2px 0;">%s:</td><td style="text-align:right;font-weight:600;color:#dc2626;">-%s</td></tr>' .
                    ($shipping_cost > 0 ? sprintf('<tr><td style="padding:2px 0;">%s:</td><td style="text-align:right;font-weight:600;color:#dc2626;">-%s</td></tr>', esc_html($shipping_label_short), wp_strip_all_tags(wc_price($shipping_cost))) : '') .
                    ((float)$row->commission_tax > 0 ? sprintf('<tr><td style="padding:2px 0;">%s:</td><td style="text-align:right;font-weight:600;color:#dc2626;">-%s</td></tr>', esc_html__('Tax on Commission (GST)', 'thaaniyamhub-multi-vendor-orders'), wp_strip_all_tags(wc_price($row->commission_tax))) : '') .
                    ($total_processing_fee > 0 ? sprintf('<tr><td style="padding:2px 0;">%s:</td><td style="text-align:right;font-weight:600;color:#dc2626;">-%s</td></tr>', esc_html__('Cashfree Gateway & Payout Fees', 'thaaniyamhub-multi-vendor-orders'), wp_strip_all_tags(wc_price($total_processing_fee))) : '') .
                    '<tr style="border-top:1px dashed %s;"><td style="padding:5px 0 2px 0;font-weight:700;">%s:</td><td style="padding:5px 0 2px 0;text-align:right;font-weight:800;font-size:12.5px;color:%s;">%s (%s%%)</td></tr>' .
                '</table>' .
                '<div style="margin-top:6px;font-size:11px;color:%s;border-top:1px solid %s;padding-top:5px;">' .
                    'ℹ️ %s' .
                '</div>' .
            '</div>',
            $is_profit ? '#065f46' : '#991b1b',
            $is_profit ? '#ecfdf5' : '#fef2f2',
            $is_profit ? '#a7f3d0' : '#fecaca',
            esc_html__('Profit & Loss Calculation Breakdown', 'thaaniyamhub-multi-vendor-orders'),
            $is_profit ? '#064e3b' : '#7f1d1d',
            esc_html__('Customer Gross Inflow', 'thaaniyamhub-multi-vendor-orders'),
            wp_strip_all_tags(wc_price($total_inflow)),
            esc_html__('Vendor Net Payout', 'thaaniyamhub-multi-vendor-orders'),
            wp_strip_all_tags(wc_price($row->vendor_net_payout)),
            $is_profit ? '#6ee7b7' : '#f87171',
            esc_html__('Net Admin Profit', 'thaaniyamhub-multi-vendor-orders'),
            $profit_color,
            wp_strip_all_tags(wc_price($row->net_profit)),
            esc_html($row->profit_margin),
            $is_profit ? '#065f46' : '#991b1b',
            $is_profit ? '#d1fae5' : '#fee2e2',
            esc_html($reason_text)
        );

        printf(
            '<tr><th><strong>%s</strong>%s</th><td><span style="color:%s;font-weight:800;font-size:14px;">%s <small style="font-size:11px;">(%s%% margin)</small></span>%s</td></tr>',
            esc_html__('Net Admin Profit (P&L)', 'thaaniyamhub-multi-vendor-orders'),
            self::help_tip(__('Net platform profit or loss retained by Thaaniyam Hub after deducting customer refund, vendor payout, logistics, commission taxes, payment gateway fees, and payout transfer charges.', 'thaaniyamhub-multi-vendor-orders')),
            $profit_color,
            wp_kses_post(wc_price($row->net_profit)),
            esc_html($row->profit_margin),
            $breakdown_html
        );

        printf(
            '<tr><th><strong>%s</strong>%s</th><td><span style="background:%s;color:%s;padding:3px 8px;border-radius:4px;font-weight:700;font-size:11px;">%s</span></td></tr>',
            esc_html__('Payout Status', 'thaaniyamhub-multi-vendor-orders'),
            self::help_tip(__('Disbursement status of the vendor payout: PENDING (awaiting batch transfer), DISBURSED (transferred to vendor bank account via Cashfree), or REFUNDED (full refund applied).', 'thaaniyamhub-multi-vendor-orders')),
            $payout_bg,
            $payout_fg,
            esc_html($payout_status_label)
        );

        printf(
            '<tr><th><strong>%s</strong>%s</th><td><span style="color:#64748b;font-size:12px;">%s</span></td></tr>',
            esc_html__('Recorded At', 'thaaniyamhub-multi-vendor-orders'),
            self::help_tip(__('Timestamp when this order transaction entry was recorded in the database ledger.', 'thaaniyamhub-multi-vendor-orders')),
            esc_html(date_i18n('d M Y, H:i:s', strtotime($row->created_at)))
        );

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    private static function render_parent_order_commission_view($rows, $order_id)
    {
        echo '<div class="thaaniyamhub-commission-view" style="padding: 4px 0;">';
        echo '<style>
            .thaaniyamhub-commission-view table.wp-list-table th { vertical-align: middle; font-weight: 600; color: #1e293b; padding: 10px 14px; }
            .thaaniyamhub-commission-view table.wp-list-table td { vertical-align: middle; padding: 10px 14px; }
            .thaaniyamhub-commission-view .woocommerce-help-tip { vertical-align: middle; margin-left: 6px; cursor: help; }
        </style>';
        echo '<table class="wp-list-table widefat fixed striped" style="margin-top: 6px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">';
        echo '<thead>';
        echo '<tr>';
        printf('<th>%s%s</th>', esc_html__('Sub Order', 'thaaniyamhub-multi-vendor-orders'), self::help_tip(__('Individual vendor sub-order ID', 'thaaniyamhub-multi-vendor-orders')));
        printf('<th>%s%s</th>', esc_html__('Vendor', 'thaaniyamhub-multi-vendor-orders'), self::help_tip(__('Marketplace vendor / store fulfilling these items', 'thaaniyamhub-multi-vendor-orders')));
        printf('<th>%s%s</th>', esc_html__('Gross Sales', 'thaaniyamhub-multi-vendor-orders'), self::help_tip(__('Post-discount product amount paid by customer for this vendor', 'thaaniyamhub-multi-vendor-orders')));
        printf('<th>%s%s</th>', esc_html__('Refunded', 'thaaniyamhub-multi-vendor-orders'), self::help_tip(__('Amount refunded back to customer', 'thaaniyamhub-multi-vendor-orders')));
        printf('<th>%s%s</th>', esc_html__('Commission (Admin Fee)', 'thaaniyamhub-multi-vendor-orders'), self::help_tip(__('Platform commission earned by Thaaniyam Hub', 'thaaniyamhub-multi-vendor-orders')));
        printf('<th>%s%s</th>', esc_html__('Shipping Charge', 'thaaniyamhub-multi-vendor-orders'), self::help_tip(__('Customer shipping fee allocated to this vendor sub-order', 'thaaniyamhub-multi-vendor-orders')));
        printf('<th>%s%s</th>', esc_html__('Vendor Net Payout', 'thaaniyamhub-multi-vendor-orders'), self::help_tip(__('Net disbursement payable to vendor', 'thaaniyamhub-multi-vendor-orders')));
        printf('<th>%s%s</th>', esc_html__('Payout Status', 'thaaniyamhub-multi-vendor-orders'), self::help_tip(__('Disbursement status (PENDING / DISBURSED / REFUNDED)', 'thaaniyamhub-multi-vendor-orders')));
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        $total_gross    = 0.0;
        $total_refund   = 0.0;
        $total_comm     = 0.0;
        $total_shipping = 0.0;
        $total_payout   = 0.0;

        foreach ($rows as $row) {
            $vendor_name = thaaniyamhub_get_vendor_name_by_vendor_id((int) $row->vendor_id);
            $sub_url = admin_url('admin.php?page=wc-orders&action=edit&id=' . $row->sub_order_id);
            if (!get_option('woocommerce_enable_order_tracking')) {
                $sub_url = get_edit_post_link($row->sub_order_id) ?: $sub_url;
            }

            $r_refund = (float) ($row->refunded_amount ?? 0);

            $total_gross    += (float) $row->gross_sales;
            $total_refund   += $r_refund;
            $total_comm     += (float) $row->commission_deducted;
            $total_shipping += (float) $row->shipping_charge;
            $total_payout   += (float) $row->vendor_net_payout;

            $payout_status_label = strtoupper($row->payout_status ?: 'PENDING');
            $payout_bg = ('disbursed' === strtolower($row->payout_status)) ? '#d1fae5' : ('refunded' === strtolower($row->payout_status) ? '#fee2e2' : '#fef3c7');
            $payout_fg = ('disbursed' === strtolower($row->payout_status)) ? '#065f46' : ('refunded' === strtolower($row->payout_status) ? '#991b1b' : '#92400e');

            $comm_deducted = (float) $row->commission_deducted;
            $catalog_price = (float) ($row->item_subtotal ?: $row->gross_sales);
            if ($comm_deducted <= 0) {
                $comm_pct_str = '0%';
            } else {
                $rate = (float) $row->commission_rate;
                if ($rate <= 0 && $catalog_price > 0) {
                    $rate = round(($comm_deducted / $catalog_price) * 100, 2);
                }
                $comm_pct_str = ($rate > 0) ? $rate . '%' : '0%';
            }

            echo '<tr>';
            printf('<td><a href="%s" style="font-weight:700;color:#4f46e5;">#%d</a></td>', esc_url($sub_url), esc_html($row->sub_order_id));
            printf('<td><strong>%s</strong> <span style="color:#64748b;">(ID: %d)</span></td>', esc_html($vendor_name), esc_html($row->vendor_id));
            printf('<td>%s</td>', wp_kses_post(wc_price($row->gross_sales)));
            if ($r_refund > 0) {
                printf('<td style="color:#dc2626;font-weight:600;">-%s</td>', wp_strip_all_tags(wc_price($r_refund)));
            } else {
                echo '<td style="color:#94a3b8;">₹0.00</td>';
            }
            printf('<td><span style="color:#4f46e5;font-weight:600;">%s (%s)</span></td>', wp_kses_post(wc_price($comm_deducted)), esc_html($comm_pct_str));
            printf('<td>%s</td>', wp_kses_post(wc_price($row->shipping_charge)));
            printf('<td><strong style="color:#1e40af;">%s</strong></td>', wp_kses_post(wc_price($row->vendor_net_payout)));
            printf('<td><span style="background:%s;color:%s;padding:2px 6px;border-radius:4px;font-weight:700;font-size:10px;">%s</span></td>', $payout_bg, $payout_fg, esc_html($payout_status_label));
            echo '</tr>';
        }

        echo '<tr style="background-color: #f8fafc; font-weight: bold; border-top: 2px solid #cbd5e1;">';
        printf('<td colspan="2" style="text-align: right;">%s</td>', esc_html__('Total Summary:', 'thaaniyamhub-multi-vendor-orders'));
        printf('<td>%s</td>', wp_kses_post(wc_price($total_gross)));
        if ($total_refund > 0) {
            printf('<td style="color:#dc2626;">-%s</td>', wp_strip_all_tags(wc_price($total_refund)));
        } else {
            echo '<td style="color:#94a3b8;">₹0.00</td>';
        }
        printf('<td><span style="color:#4f46e5;">%s</span></td>', wp_kses_post(wc_price($total_comm)));
        printf('<td>%s</td>', wp_kses_post(wc_price($total_shipping)));
        printf('<td><span style="color:#1e40af;">%s</span></td>', wp_kses_post(wc_price($total_payout)));
        echo '<td></td>';
        echo '</tr>';
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    public static function render_meta_box($post_or_order)
    {
        $order = $post_or_order instanceof WP_Post ? wc_get_order($post_or_order->ID) : $post_or_order;
        if (!$order) {
            return;
        }

        if (!ThaaniyamHub_Dispatch::is_order_eligible_for_fulfillment($order)) {
            echo '<p style="color:#888;font-size:12px;">' . esc_html__('Shiprocket fulfillment is tracked on vendor sub-orders and single-vendor orders only.', 'thaaniyamhub-multi-vendor-orders') . '</p>';
            return;
        }

        $order_id = $order->get_id();
        if (!self::current_user_can_manage_order($order_id)) {
            echo '<p style="color:#888;font-size:12px;">' . esc_html__('You do not have permission to manage this order.', 'thaaniyamhub-multi-vendor-orders') . '</p>';
            return;
        }

        $record = ThaaniyamHub_Shiprocket_API::get_fulfillment($order_id);

        echo '<div id="thaaniyamhub-sf-fulfillment-metabox" data-order-id="' . esc_attr($order_id) . '">';

        if (!$record || !$record->shiprocket_order_id) {
            if ('processing' !== $order->get_status()) {
                echo '<p style="color:#b91c1c;font-weight:600;margin:0;">' . esc_html__('Order must be in processing status to push to Shiprocket.', 'thaaniyamhub-multi-vendor-orders') . '</p>';
                echo '</div>';
                return;
            }

            $vendor_id = ThaaniyamHub_Dispatch::get_order_vendor_id($order);
            $default_pickup = get_user_meta($vendor_id, '_shiprocket_pickup_id', true);
            if (!$default_pickup) {
                $default_pickup = ThaaniyamHub_Dispatch::resolve_pickup_nickname($vendor_id);
            }

            $api = new ThaaniyamHub_Shiprocket_API();
            $pickup_res = $api->get_pickup_addresses();
            $pickup_addresses = [];
            if (!is_wp_error($pickup_res) && !empty($pickup_res['data']['shipping_address'])) {
                foreach ($pickup_res['data']['shipping_address'] as $addr) {
                    if (!empty($addr['pickup_location']) && !empty($addr['status'])) {
                        $pickup_addresses[] = $addr;
                    }
                }
            }

            $sync_failed = $order->get_meta('_shiprocket_sync_failed');
            $fail_reason = $order->get_meta('_shiprocket_sync_fail_reason');

            echo '<div class="thaaniyamhub-not-dispatched">';
            if ($sync_failed) {
                echo '<strong>⚠️ ' . esc_html__('Dispatch failed:', 'thaaniyamhub-multi-vendor-orders') . '</strong><br>';
                echo esc_html($fail_reason) . '<br><br>';
            } else {
                echo esc_html__('Order has not been pushed to Shiprocket yet.', 'thaaniyamhub-multi-vendor-orders') . '<br><br>';
            }

            // Pickup dropdown
            echo '<div class="thaaniyamhub-pickup-selector-wrapper" style="margin-bottom:12px; border-bottom:1px solid #eee; padding-bottom:10px;">';
            echo '<label for="thaaniyamhub_sf_pickup_location" style="display:block;margin-bottom:5px;font-weight:600;">' . esc_html__('Pickup Location:', 'thaaniyamhub-multi-vendor-orders') . '</label>';
            echo '<select id="thaaniyamhub_sf_pickup_location" name="thaaniyamhub_sf_pickup_location" style="width:100%; margin-bottom:5px;" data-addresses="' . esc_attr(wp_json_encode($pickup_addresses)) . '">';
            if (!empty($pickup_addresses)) {
                foreach ($pickup_addresses as $addr) {
                    $loc = trim($addr['pickup_location']);
                    $selected = selected($loc, $default_pickup, false);
                    echo '<option value="' . esc_attr($loc) . '" ' . $selected . '>' . esc_html($loc) . '</option>';
                }
            } else {
                echo '<option value="' . esc_attr($default_pickup) . '" selected>' . esc_html($default_pickup ?: __('No configured location', 'thaaniyamhub-multi-vendor-orders')) . '</option>';
            }
            echo '</select>';

            // Pickup details box
            echo '<div class="thaaniyamhub-pickup-details-box" style="margin-top: 8px; font-size: 11px; color: #555; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; padding: 8px; line-height: 1.4; display: none;">';
            echo '<strong>' . esc_html__('Contact:', 'thaaniyamhub-multi-vendor-orders') . '</strong> <span class="pickup-contact-name"></span> (<span class="pickup-contact-phone"></span>)<br>';
            echo '<strong>' . esc_html__('Address:', 'thaaniyamhub-multi-vendor-orders') . '</strong> <span class="pickup-address-details"></span>';
            echo '<div class="pickup-lat-long-warning" style="display: none; color: #b91c1c; margin-top: 6px; font-weight: 600; padding: 6px; background: #fee2e2; border: 1px solid #fca5a5; border-radius: 3px; line-height: 1.3;">';
            echo '⚠️ ' . esc_html__('Coordinates not verified in Shiprocket. Pickup may fall back to default panel address until verified.', 'thaaniyamhub-multi-vendor-orders');
            echo '</div>';
            echo '</div>';

            echo '<div style="display:flex; justify-content:space-between; margin-top:2px;">';
            echo '<a href="#" id="thaaniyamhub-sf-make-default-pickup" style="font-size:10px;text-decoration:none;" data-vendor-id="' . esc_attr($vendor_id) . '">⭐ ' . esc_html__('Make default', 'thaaniyamhub-multi-vendor-orders') . '</a>';
            echo '<a href="#" id="thaaniyamhub-sf-toggle-new-pickup" style="font-size:10px;text-decoration:none;">➕ ' . esc_html__('Add new address', 'thaaniyamhub-multi-vendor-orders') . '</a>';
            echo '</div>';

            // New address form
            echo '<div id="thaaniyamhub-sf-new-pickup-form" style="display:none;background:#f3f4f6;border:1px solid #d1d5db;border-radius:4px;padding:8px;margin-top:8px;">';
            echo '<h4 style="margin:0 0 6px 0;font-size:11px;color:#374151;">' . esc_html__('New Pickup Address', 'thaaniyamhub-multi-vendor-orders') . '</h4>';
            echo '<input type="text" id="thaaniyamhub_np_nickname" placeholder="Nickname (e.g. Warehouse B) *" style="width:100%;margin-bottom:4px;font-size:10px;padding:3px;">';
            echo '<input type="text" id="thaaniyamhub_np_name" placeholder="Contact Name *" style="width:100%;margin-bottom:4px;font-size:10px;padding:3px;">';
            echo '<input type="text" id="thaaniyamhub_np_phone" placeholder="Phone *" style="width:100%;margin-bottom:4px;font-size:10px;padding:3px;">';
            echo '<input type="email" id="thaaniyamhub_np_email" placeholder="Email *" style="width:100%;margin-bottom:4px;font-size:10px;padding:3px;">';
            echo '<input type="text" id="thaaniyamhub_np_address" placeholder="Address Line 1 *" style="width:100%;margin-bottom:4px;font-size:10px;padding:3px;">';
            echo '<input type="text" id="thaaniyamhub_np_address2" placeholder="Address Line 2 (Optional)" style="width:100%;margin-bottom:4px;font-size:10px;padding:3px;">';
            echo '<input type="text" id="thaaniyamhub_np_city" placeholder="City *" style="width:100%;margin-bottom:4px;font-size:10px;padding:3px;">';
            echo '<input type="text" id="thaaniyamhub_np_state" placeholder="State *" style="width:100%;margin-bottom:4px;font-size:10px;padding:3px;">';
            echo '<input type="text" id="thaaniyamhub_np_pincode" placeholder="Pincode *" style="width:100%;margin-bottom:6px;font-size:10px;padding:3px;">';
            echo '<button type="button" id="thaaniyamhub-sf-btn-add-pickup" class="button button-secondary button-small" style="font-size:10px;width:100%;">' . esc_html__('Register Address', 'thaaniyamhub-multi-vendor-orders') . '</button>';
            echo '</div>';
            echo '</div>';

            // Metrics inputs - do not autofill, only use manually saved overrides
            $weight = $order->get_meta('_shiprocket_weight_override');
            $length = $order->get_meta('_shiprocket_length_override');
            $breadth = $order->get_meta('_shiprocket_width_override');
            $height = $order->get_meta('_shiprocket_height_override');

            $dimension_presets = class_exists('ThaaniyamHub_Dimension_Manager') ? ThaaniyamHub_Dimension_Manager::get_vendor_presets((int) $vendor_id) : [];

            echo '<div class="thaaniyamhub-dims-wrapper" style="margin-bottom:12px;border-top:1px solid #eee;padding-top:10px;">';
            echo '<label style="display:block;margin-bottom:5px;font-weight:600;">' . esc_html__('Package Details:', 'thaaniyamhub-multi-vendor-orders') . '</label>';

            if (!empty($dimension_presets)) {
                echo '<div style="margin-bottom:8px;">';
                echo '<label for="thaaniyamhub_sf_package_preset" style="font-size:10px;color:#555;display:block;margin-bottom:3px;">' . esc_html__('Select Saved Dimension (Optional):', 'thaaniyamhub-multi-vendor-orders') . '</label>';
                echo '<select id="thaaniyamhub_sf_package_preset" style="width:100%;font-size:11px;padding:3px 5px;margin-bottom:4px;">';
                echo '<option value="">' . esc_html__('-- Choose Preset or Enter Custom Below --', 'thaaniyamhub-multi-vendor-orders') . '</option>';
                foreach ($dimension_presets as $dp) {
                    $dp_label = sprintf('%s (%s kg | %s × %s × %s cm)', $dp['name'], $dp['weight'], $dp['length'], $dp['width'], $dp['height']);
                    echo '<option value="' . esc_attr($dp['id']) . '" data-weight="' . esc_attr($dp['weight']) . '" data-length="' . esc_attr($dp['length']) . '" data-width="' . esc_attr($dp['width']) . '" data-height="' . esc_attr($dp['height']) . '">' . esc_html($dp_label) . '</option>';
                }
                echo '</select>';
                echo '</div>';
            }

            echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:8px;">';
            echo '<div><label style="font-size:10px;display:block;">Weight (kg) <span style="color:#b91c1c;">*</span></label><input type="number" id="thaaniyamhub_sf_weight" step="0.001" min="0.01" value="' . esc_attr($weight) . '" style="width:100%;"></div>';
            echo '<div><label style="font-size:10px;display:block;">Length (cm) <span style="color:#b91c1c;">*</span></label><input type="number" id="thaaniyamhub_sf_length" step="0.1" min="1" value="' . esc_attr($length) . '" style="width:100%;background-color:#f3f4f6;cursor:not-allowed;" readonly></div>';
            echo '<div><label style="font-size:10px;display:block;">Width (cm) <span style="color:#b91c1c;">*</span></label><input type="number" id="thaaniyamhub_sf_width" step="0.1" min="1" value="' . esc_attr($breadth) . '" style="width:100%;background-color:#f3f4f6;cursor:not-allowed;" readonly></div>';
            echo '<div><label style="font-size:10px;display:block;">Height (cm) <span style="color:#b91c1c;">*</span></label><input type="number" id="thaaniyamhub_sf_height" step="0.1" min="1" value="' . esc_attr($height) . '" style="width:100%;background-color:#f3f4f6;cursor:not-allowed;" readonly></div>';
            echo '</div>';
            echo '<button type="button" id="thaaniyamhub-sf-btn-fetch-couriers" class="button button-secondary button-small" style="width:100%;margin-bottom:6px;" data-order-id="' . esc_attr($order_id) . '">' . esc_html__('Fetch Available Couriers', 'thaaniyamhub-multi-vendor-orders') . '</button>';
            echo '<div id="thaaniyamhub-sf-pre-push-couriers" style="display:none;margin-bottom:8px;border:1px solid #ddd;padding:8px;background:#f9f9f9;border-radius:4px;max-height:150px;overflow-y:auto;"></div>';
            echo '</div>';

            echo '<button type="button" id="thaaniyamhub-sf-btn-push" class="button button-primary" style="width:100%;" data-order-id="' . esc_attr($order_id) . '">';
            esc_html_e('Push to Shiprocket', 'thaaniyamhub-multi-vendor-orders');
            echo '</button>';

            echo '</div>';
        } else {
            // Already dispatched. Show logs and buttons.
            $status_class = 'thaaniyamhub-status-' . sanitize_html_class($record->fulfillment_status);
            ?>
            <div class="thaaniyamhub-fulfillment-grid">
                <div>
                    <div class="thaaniyamhub-field-label">
                        <?php esc_html_e('Shiprocket Order ID', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </div>
                    <div class="thaaniyamhub-field-value"><?php echo esc_html($record->shiprocket_order_id); ?></div>
                </div>
                <div>
                    <div class="thaaniyamhub-field-label"><?php esc_html_e('Shipment ID', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </div>
                    <div class="thaaniyamhub-field-value"><?php echo esc_html($record->shiprocket_shipment_id); ?></div>
                </div>
                <div>
                    <div class="thaaniyamhub-field-label"><?php esc_html_e('AWB Code', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </div>
                    <div class="thaaniyamhub-field-value"><?php echo $record->awb_code ? esc_html($record->awb_code) : '—'; ?>
                    </div>
                </div>
                <div>
                    <div class="thaaniyamhub-field-label"><?php esc_html_e('Courier', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </div>
                    <div class="thaaniyamhub-field-value">
                        <?php echo $record->courier_name ? esc_html($record->courier_name) : '—'; ?>
                    </div>
                </div>
                <div>
                    <div class="thaaniyamhub-field-label">
                        <?php esc_html_e('Pickup Location', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </div>
                    <div class="thaaniyamhub-field-value"><?php echo esc_html($record->pickup_location_nickname); ?></div>
                </div>
                <div>
                    <div class="thaaniyamhub-field-label"><?php esc_html_e('Status', 'thaaniyamhub-multi-vendor-orders'); ?></div>
                    <div class="thaaniyamhub-field-value">
                        <span class="thaaniyamhub-status-badge <?php echo esc_attr($status_class); ?>">
                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $record->fulfillment_status))); ?>
                        </span>
                    </div>
                </div>
                <div>
                    <div class="thaaniyamhub-field-label"><?php esc_html_e('Weight', 'thaaniyamhub-multi-vendor-orders'); ?></div>
                    <div class="thaaniyamhub-field-value">
                        <?php 
                        $disp_weight = $order->get_meta('_shiprocket_weight_override');
                        echo $disp_weight ? esc_html($disp_weight) . ' kg' : '—'; 
                        ?>
                    </div>
                </div>
                <div>
                    <div class="thaaniyamhub-field-label"><?php esc_html_e('Dimensions', 'thaaniyamhub-multi-vendor-orders'); ?></div>
                    <div class="thaaniyamhub-field-value">
                        <?php 
                        $l = $order->get_meta('_shiprocket_length_override');
                        $w = $order->get_meta('_shiprocket_width_override');
                        $h = $order->get_meta('_shiprocket_height_override');
                        echo ($l && $w && $h) ? esc_html(sprintf('%s x %s x %s cm', $l, $w, $h)) : '—';
                        ?>
                    </div>
                </div>
                <?php if ($record->awb_code): ?>
                <div>
                    <div class="thaaniyamhub-field-label"><?php esc_html_e('Actual Shipping Cost', 'thaaniyamhub-multi-vendor-orders'); ?></div>
                    <div class="thaaniyamhub-field-value">
                        <?php 
                        $actual_cost = self::get_actual_shipping_cost($order, $record);
                        echo $actual_cost ? wp_kses_post(wc_price($actual_cost)) : '—'; 
                        ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php 
                $pickup_date = !empty($record->pickup_scheduled_date) ? $record->pickup_scheduled_date : $order->get_meta('_shiprocket_pickup_scheduled_date');
                $pickup_token = !empty($record->pickup_token_number) ? $record->pickup_token_number : $order->get_meta('_shiprocket_pickup_token_number');
                if ($pickup_date): ?>
                <div>
                    <div class="thaaniyamhub-field-label"><?php esc_html_e('Pickup Scheduled', 'thaaniyamhub-multi-vendor-orders'); ?></div>
                    <div class="thaaniyamhub-field-value">
                        <span style="color:#166534;font-weight:600;">📅 <?php echo esc_html($pickup_date); ?></span>
                        <?php if ($pickup_token): ?>
                            <br><small style="color:#666;font-size:11px;font-weight:normal;"><?php echo sprintf(esc_html__('Token: %s', 'thaaniyamhub-multi-vendor-orders'), esc_html($pickup_token)); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php 
                $manifest_url_disp = !empty($record->manifest_url) ? $record->manifest_url : $order->get_meta('_shiprocket_manifest_url');
                if ($manifest_url_disp): ?>
                <div>
                    <div class="thaaniyamhub-field-label"><?php esc_html_e('Manifest Document', 'thaaniyamhub-multi-vendor-orders'); ?></div>
                    <div class="thaaniyamhub-field-value">
                        <a href="<?php echo esc_url($manifest_url_disp); ?>" target="_blank" style="display:inline-flex;align-items:center;gap:4px;color:#0369a1;text-decoration:none;font-size:12px;font-weight:600;">
                            <span class="dashicons dashicons-pdf"></span> <?php esc_html_e('View Manifest PDF', 'thaaniyamhub-multi-vendor-orders'); ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!$record->awb_code):
                $vendor_id = ThaaniyamHub_Dispatch::get_order_vendor_id($order);
                $default_pickup = get_user_meta($vendor_id, '_shiprocket_pickup_id', true);
                if (!$default_pickup) {
                    $default_pickup = $record->pickup_location_nickname ?: ThaaniyamHub_Dispatch::resolve_pickup_nickname($vendor_id);
                }

                $api = new ThaaniyamHub_Shiprocket_API();
                $pickup_res = $api->get_pickup_addresses();
                $pickup_addresses = [];
                if (!is_wp_error($pickup_res) && !empty($pickup_res['data']['shipping_address'])) {
                    foreach ($pickup_res['data']['shipping_address'] as $addr) {
                        if (!empty($addr['pickup_location']) && !empty($addr['status'])) {
                            $pickup_addresses[] = $addr;
                        }
                    }
                }

                // Metrics inputs - do not autofill, only use manually saved overrides
                $weight = $order->get_meta('_shiprocket_weight_override');
                $length = $order->get_meta('_shiprocket_length_override');
                $breadth = $order->get_meta('_shiprocket_width_override');
                $height = $order->get_meta('_shiprocket_height_override');
                ?>
                <div class="thaaniyamhub-package-options-wrapper"
                    style="margin-top:15px; border-top:1px solid #eee; padding-top:10px; margin-bottom:15px;">
                    <?php if ('cancelled' === $record->fulfillment_status): ?>
                        <strong style="display:block;margin-bottom:8px;font-size:12px;color:#b91c1c;">&#x21BB;
                            <?php esc_html_e('Resend / Re-push Order:', 'thaaniyamhub-multi-vendor-orders'); ?></strong>
                    <?php else: ?>
                        <strong style="display:block;margin-bottom:8px;font-size:12px;color:#1a1a1a;">&#x1F69A;
                            <?php esc_html_e('Package & Shipment Details:', 'thaaniyamhub-multi-vendor-orders'); ?></strong>
                    <?php endif; ?>

                    <div class="thaaniyamhub-pickup-selector-wrapper"
                        style="margin-bottom:12px; border-bottom:1px solid #eee; padding-bottom:10px;">
                        <label for="thaaniyamhub_sf_pickup_location"
                            style="display:block;margin-bottom:5px;font-weight:600;"><?php esc_html_e('Pickup Location:', 'thaaniyamhub-multi-vendor-orders'); ?></label>
                        <select id="thaaniyamhub_sf_pickup_location" name="thaaniyamhub_sf_pickup_location"
                            style="width:100%; margin-bottom:5px;"
                            data-addresses="<?php echo esc_attr(wp_json_encode($pickup_addresses)); ?>">
                            <?php if (!empty($pickup_addresses)): ?>
                                <?php foreach ($pickup_addresses as $addr):
                                    $loc = trim($addr['pickup_location']);
                                    $selected = selected($loc, $default_pickup, false);
                                    echo '<option value="' . esc_attr($loc) . '" ' . $selected . '>' . esc_html($loc) . '</option>';
                                endforeach; ?>
                            <?php else: ?>
                                <option value="<?php echo esc_attr($default_pickup); ?>" selected>
                                    <?php echo esc_html($default_pickup); ?>
                                </option>
                            <?php endif; ?>
                        </select>

                        <div class="thaaniyamhub-pickup-details-box"
                            style="margin-top: 8px; font-size: 11px; color: #555; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; padding: 8px; line-height: 1.4; display: none;">
                            <strong><?php esc_html_e('Contact:', 'thaaniyamhub-multi-vendor-orders'); ?></strong> <span
                                class="pickup-contact-name"></span> (<span class="pickup-contact-phone"></span>)<br>
                            <strong><?php esc_html_e('Address:', 'thaaniyamhub-multi-vendor-orders'); ?></strong> <span
                                class="pickup-address-details"></span>
                            <div class="pickup-lat-long-warning"
                                style="display: none; color: #b91c1c; margin-top: 6px; font-weight: 600; padding: 6px; background: #fee2e2; border: 1px solid #fca5a5; border-radius: 3px; line-height: 1.3;">
                                ⚠️ <?php esc_html_e('Coordinates not verified in Shiprocket.', 'thaaniyamhub-multi-vendor-orders'); ?>
                            </div>
                        </div>

                        <div style="display:flex; justify-content:space-between; margin-top:2px;">
                            <a href="#" id="thaaniyamhub-sf-make-default-pickup" style="font-size:10px;text-decoration:none;"
                                data-vendor-id="<?php echo esc_attr($vendor_id); ?>">⭐
                                <?php esc_html_e('Make default', 'thaaniyamhub-multi-vendor-orders'); ?></a>
                            <a href="#" id="thaaniyamhub-sf-toggle-new-pickup" style="font-size:10px;text-decoration:none;">➕
                                <?php esc_html_e('Add new address', 'thaaniyamhub-multi-vendor-orders'); ?></a>
                        </div>

                        <div id="thaaniyamhub-sf-new-pickup-form"
                            style="display:none;background:#f3f4f6;border:1px solid #d1d5db;border-radius:4px;padding:8px;margin-top:8px;">
                            <h4 style="margin:0 0 6px 0;font-size:11px;color:#374151;">
                                <?php esc_html_e('New Pickup Address', 'thaaniyamhub-multi-vendor-orders'); ?>
                            </h4>
                            <input type="text" id="thaaniyamhub_np_nickname" placeholder="Nickname *"
                                style="width:100%;margin-bottom:4px;font-size:10px;padding:3px;">
                            <input type="text" id="thaaniyamhub_np_name" placeholder="Contact Name *"
                                style="width:100%;margin-bottom:4px;font-size:10px;padding:3px;">
                            <input type="text" id="thaaniyamhub_np_phone" placeholder="Phone *"
                                style="width:100%;margin-bottom:4px;font-size:10px;padding:3px;">
                            <input type="email" id="thaaniyamhub_np_email" placeholder="Email *"
                                style="width:100%;margin-bottom:4px;font-size:10px;padding:3px;">
                            <input type="text" id="thaaniyamhub_np_address" placeholder="Address Line 1 *"
                                style="width:100%;margin-bottom:4px;font-size:10px;padding:3px;">
                            <input type="text" id="thaaniyamhub_np_address2" placeholder="Address Line 2"
                                style="width:100%;margin-bottom:4px;font-size:10px;padding:3px;">
                            <input type="text" id="thaaniyamhub_np_city" placeholder="City *"
                                style="width:100%;margin-bottom:4px;font-size:10px;padding:3px;">
                            <input type="text" id="thaaniyamhub_np_state" placeholder="State *"
                                style="width:100%;margin-bottom:4px;font-size:10px;padding:3px;">
                            <input type="text" id="thaaniyamhub_np_pincode" placeholder="Pincode *"
                                style="width:100%;margin-bottom:6px;font-size:10px;padding:3px;">
                            <button type="button" id="thaaniyamhub-sf-btn-add-pickup" class="button button-secondary button-small"
                                style="font-size:10px;width:100%;"><?php esc_html_e('Register Address', 'thaaniyamhub-multi-vendor-orders'); ?></button>
                        </div>
                    </div>

                    <?php
                    $dimension_presets = class_exists('ThaaniyamHub_Dimension_Manager') ? ThaaniyamHub_Dimension_Manager::get_vendor_presets((int) $vendor_id) : [];
                    ?>
                    <div class="thaaniyamhub-dims-wrapper" style="margin-bottom:12px;border-bottom:1px solid #eee;padding-bottom:10px;">
                        <label
                            style="display:block;margin-bottom:5px;font-weight:600;"><?php esc_html_e('Package Details:', 'thaaniyamhub-multi-vendor-orders'); ?></label>
                        <?php if (!empty($dimension_presets)): ?>
                            <div style="margin-bottom:8px;">
                                <label for="thaaniyamhub_sf_package_preset" style="font-size:10px;color:#555;display:block;margin-bottom:3px;"><?php esc_html_e('Select Saved Dimension (Optional):', 'thaaniyamhub-multi-vendor-orders'); ?></label>
                                <select id="thaaniyamhub_sf_package_preset" style="width:100%;font-size:11px;padding:3px 5px;margin-bottom:4px;">
                                    <option value=""><?php esc_html_e('-- Choose Preset or Enter Custom Below --', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                                    <?php foreach ($dimension_presets as $dp): 
                                        $dp_label = sprintf('%s (%s kg | %s × %s × %s cm)', $dp['name'], $dp['weight'], $dp['length'], $dp['width'], $dp['height']);
                                    ?>
                                        <option value="<?php echo esc_attr($dp['id']); ?>" data-weight="<?php echo esc_attr($dp['weight']); ?>" data-length="<?php echo esc_attr($dp['length']); ?>" data-width="<?php echo esc_attr($dp['width']); ?>" data-height="<?php echo esc_attr($dp['height']); ?>"><?php echo esc_html($dp_label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:8px;">
                            <div><label style="font-size:10px;display:block;">Weight (kg) <span
                                        style="color:#b91c1c;">*</span></label><input type="number" id="thaaniyamhub_sf_weight"
                                    step="0.001" min="0.01" value="<?php echo esc_attr($weight); ?>" style="width:100%;"></div>
                            <div><label style="font-size:10px;display:block;">Length (cm) <span
                                        style="color:#b91c1c;">*</span></label><input type="number" id="thaaniyamhub_sf_length"
                                    step="0.1" min="1" value="<?php echo esc_attr($length); ?>" style="width:100%;background-color:#f3f4f6;cursor:not-allowed;" readonly></div>
                            <div><label style="font-size:10px;display:block;">Width (cm) <span
                                        style="color:#b91c1c;">*</span></label><input type="number" id="thaaniyamhub_sf_width"
                                    step="0.1" min="1" value="<?php echo esc_attr($breadth); ?>" style="width:100%;background-color:#f3f4f6;cursor:not-allowed;" readonly></div>
                            <div><label style="font-size:10px;display:block;">Height (cm) <span
                                        style="color:#b91c1c;">*</span></label><input type="number" id="thaaniyamhub_sf_height"
                                    step="0.1" min="1" value="<?php echo esc_attr($height); ?>" style="width:100%;background-color:#f3f4f6;cursor:not-allowed;" readonly></div>
                        </div>
                        <button type="button" id="thaaniyamhub-sf-btn-fetch-couriers" class="button button-secondary button-small"
                            style="width:100%;margin-bottom:6px;"
                            data-order-id="<?php echo esc_attr($order_id); ?>"><?php esc_html_e('Fetch Available Couriers', 'thaaniyamhub-multi-vendor-orders'); ?></button>
                        <div id="thaaniyamhub-sf-pre-push-couriers"
                            style="display:none;margin-bottom:8px;border:1px solid #ddd;padding:8px;background:#f9f9f9;border-radius:4px;max-height:150px;overflow-y:auto;">
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="thaaniyamhub-actions">
                <?php if (!$record->awb_code && 'cancelled' !== $record->fulfillment_status): ?>
                    <button type="button" id="thaaniyamhub-sf-btn-generate-awb" class="button button-primary" style="width:100%;"
                        data-order-id="<?php echo esc_attr($order_id); ?>">
                        <span class="dashicons dashicons-location"></span>
                        <?php esc_html_e('Generate AWB', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </button>
                <?php endif; ?>

                <?php 
                $pickup_date = !empty($record->pickup_scheduled_date) ? $record->pickup_scheduled_date : $order->get_meta('_shiprocket_pickup_scheduled_date');
                $pickup_token = !empty($record->pickup_token_number) ? $record->pickup_token_number : $order->get_meta('_shiprocket_pickup_token_number');
                ?>

                <?php if ($record->awb_code && $pickup_date): ?>
                    <div style="width:100%; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:10px 12px; margin-bottom:10px; display:flex; align-items:center; gap:10px;">
                        <span style="font-size:22px; line-height:1;">📅</span>
                        <div style="flex:1;">
                            <div style="font-size:10px; font-weight:700; color:#166534; text-transform:uppercase; letter-spacing:0.5px;">
                                <?php esc_html_e('Scheduled Pickup Date', 'thaaniyamhub-multi-vendor-orders'); ?>
                            </div>
                            <div style="font-size:13px; font-weight:700; color:#14532d; margin-top:1px;">
                                <?php echo esc_html($pickup_date); ?>
                            </div>
                            <?php if ($pickup_token): ?>
                                <div style="font-size:11px; color:#15803d; font-weight:600; margin-top:2px;">
                                    <?php echo sprintf(esc_html__('Token: %s', 'thaaniyamhub-multi-vendor-orders'), esc_html($pickup_token)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($record->awb_code && in_array($record->fulfillment_status, ['assigned', 'manifested', 'pickup_scheduled'], true)): ?>
                    <div class="thaaniyamhub-reassign-wrapper"
                        style="width:100%; border-top:1px solid #eee; padding-top:10px; margin-top:10px; display:none;"
                        id="thaaniyamhub-sf-reassign-section">
                        <label style="display:block;margin-bottom:5px;font-weight:600;">
                            <?php esc_html_e('Select New Courier:', 'thaaniyamhub-multi-vendor-orders'); ?>
                        </label>
                        <div id="thaaniyamhub-sf-courier-reassign-loader" data-order-id="<?php echo esc_attr($order_id); ?>">
                            <span class="thaaniyamhub-sf-courier-loading">
                                <span class="dashicons dashicons-update thaaniyamhub-sf-spin"></span>
                                <?php esc_html_e('Loading couriers…', 'thaaniyamhub-multi-vendor-orders'); ?>
                            </span>
                        </div>
                        <button type="button" id="thaaniyamhub-sf-btn-reassign-submit" class="button button-primary"
                            style="margin-top:6px; width:100%;" data-order-id="<?php echo esc_attr($order_id); ?>">
                            <?php esc_html_e('Assign Selected Courier', 'thaaniyamhub-multi-vendor-orders'); ?>
                        </button>
                    </div>
                    <button type="button" id="thaaniyamhub-sf-btn-reassign-courier" class="button"
                        data-order-id="<?php echo esc_attr($order_id); ?>">
                        <span class="dashicons dashicons-randomize"></span>
                        <?php esc_html_e('Change Courier Partner', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </button>
                <?php endif; ?>

                <?php
                $label_url = !empty($record->shipping_label_url) ? $record->shipping_label_url : ($order ? $order->get_meta('_shiprocket_label_url') : '');
                $manifest_url = !empty($record->manifest_url) ? $record->manifest_url : ($order ? $order->get_meta('_shiprocket_manifest_url') : '');
                $invoice_url = !empty($record->commercial_invoice_url) ? $record->commercial_invoice_url : ($order ? $order->get_meta('_shiprocket_invoice_url') : '');
                ?>

                <!-- 1. Shipping Label Button: View if generated, Print if not yet generated -->
                <?php if ($label_url): ?>
                    <a href="<?php echo esc_url($label_url); ?>" target="_blank" class="button">
                        <span class="dashicons dashicons-pdf"></span>
                        <?php esc_html_e('View Shipping Label', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </a>
                <?php elseif ($record->awb_code): ?>
                    <button type="button" id="thaaniyamhub-sf-btn-print-label" class="button"
                        data-order-id="<?php echo esc_attr($order_id); ?>">
                        <span class="dashicons dashicons-printer"></span>
                        <?php esc_html_e('Print Label', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </button>
                <?php endif; ?>

                <!-- 2. Manifest Button: View if generated, Download if not yet generated -->
                <?php if ($manifest_url): ?>
                    <a href="<?php echo esc_url($manifest_url); ?>" target="_blank" class="button" style="color:#0369a1;border-color:#0369a1;">
                        <span class="dashicons dashicons-pdf"></span>
                        <?php esc_html_e('View Manifest', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </a>
                <?php elseif ($record->awb_code): ?>
                    <button type="button" id="thaaniyamhub-sf-btn-download-manifest" class="button"
                        data-order-id="<?php echo esc_attr($order_id); ?>">
                        <span class="dashicons dashicons-clipboard"></span>
                        <?php esc_html_e('Download Manifest', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </button>
                <?php endif; ?>

                <!-- 3. Invoice Button: View if generated, Print if not yet generated -->
                <?php if ($invoice_url): ?>
                    <a href="<?php echo esc_url($invoice_url); ?>" target="_blank" class="button">
                        <span class="dashicons dashicons-media-document"></span>
                        <?php esc_html_e('View Invoice', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </a>
                <?php elseif (!empty($record->shiprocket_order_id)): ?>
                    <button type="button" id="thaaniyamhub-sf-btn-print-invoice" class="button"
                        data-order-id="<?php echo esc_attr($order_id); ?>">
                        <span class="dashicons dashicons-media-document"></span>
                        <?php esc_html_e('Print Invoice', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </button>
                <?php endif; ?>

                <?php if (!in_array($record->fulfillment_status, ['cancelled', 'delivered', 'return_initiated', 'return_picked_up', 'return_ofd', 'returned', 'return_cancelled', 'rto'], true)): ?>
                    <button type="button" id="thaaniyamhub-sf-btn-cancel-shipment" class="button"
                        style="color:#b91c1c;border-color:#b91c1c;" data-order-id="<?php echo esc_attr($order_id); ?>">
                        <span class="dashicons dashicons-no-alt"></span>
                        <?php esc_html_e('Cancel Shipment', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </button>
                <?php endif; ?>

                <?php if ('delivered' === $record->fulfillment_status): ?>
                    <button type="button" id="thaaniyamhub-sf-btn-return-order" class="button button-primary" style="width:100%; background:#b91c1c; border-color:#b91c1c;"
                        data-order-id="<?php echo esc_attr($order_id); ?>">
                        <span class="dashicons dashicons-undo"></span>
                        <?php esc_html_e('Return Order', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </button>
                <?php endif; ?>

                <?php if ('cancelled' === $record->fulfillment_status): ?>
                    <button type="button" id="thaaniyamhub-sf-btn-push" class="button button-primary" style="width:100%;"
                        data-order-id="<?php echo esc_attr($order_id); ?>">
                        <?php esc_html_e('Resend to Shiprocket', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </button>
                <?php endif; ?>
            </div>
        <?php
        }

        echo '</div>'; // #thaaniyamhub-sf-fulfillment-metabox
    }

    // =========================================================================
    // 5. ENQUEUE ASSETS (CSS + JS)
    // =========================================================================

    public static function enqueue_assets(string $hook)
    {
        $is_order_list = in_array($hook, ['edit.php', 'woocommerce_page_wc-orders'], true);
        $is_order_detail = in_array($hook, ['post.php', 'post-new.php', 'woocommerce_page_wc-orders'], true);

        if (!$is_order_list && !$is_order_detail) {
            return;
        }

        // Inline CSS
        $css = '
        /* Sub-orders rows in listing */
        tr.thaaniyamhub-suborder-row td { background: #f9fafb !important; border-left: 3px solid #7c3aed; }
        tr.thaaniyamhub-suborder-row td:first-child { padding-left: 24px !important; }
        tr.thaaniyamhub-suborder-row.thaaniyamhub-suborder-hidden { display: none !important; }
        tr.thaaniyamhub-suborder-row.ag-visible { display: table-row !important; }
        .thaaniyamhub-suborder-label a { color: #7c3aed; font-weight: 500; text-decoration: none; }
        .thaaniyamhub-toggle-suborders {
            display: inline-flex !important; align-items: center !important; justify-content: center !important;
            background: #7c3aed !important; border-color: #6d28d9 !important; color: #fff !important;
            font-size: 12px !important; padding: 4px 10px !important; border-radius: 4px !important;
            cursor: pointer; line-height: 1 !important; height: auto !important; text-decoration: none !important;
        }
        .thaaniyamhub-toggle-suborders:hover, .thaaniyamhub-toggle-suborders:focus {
            background: #6d28d9 !important; color: #fff !important; border-color: #5b21b6 !important;
        }
        .thaaniyamhub-toggle-suborders .dashicons {
            font-size: 14px !important; width: 14px !important; height: 14px !important;
            line-height: 14px !important; vertical-align: middle !important; margin-right: 4px !important;
            transition: transform 0.2s;
        }
        .thaaniyamhub-toggle-suborders.open .dashicons { transform: rotate(180deg) !important; }

        /* Fulfillment Controls styling */
        #thaaniyamhub-sf-fulfillment-metabox .thaaniyamhub-fulfillment-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; }
        #thaaniyamhub-sf-fulfillment-metabox .thaaniyamhub-field-label { font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
        #thaaniyamhub-sf-fulfillment-metabox .thaaniyamhub-field-value { font-weight: 600; font-size: 14px; color: #1a1a1a; }
        #thaaniyamhub-sf-fulfillment-metabox .thaaniyamhub-status-badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        #thaaniyamhub-sf-fulfillment-metabox .thaaniyamhub-status-dispatched { background: #f3f4f6; color: #374151; }
        #thaaniyamhub-sf-fulfillment-metabox .thaaniyamhub-status-manifested { background: #e0f2fe; color: #0369a1; }
        #thaaniyamhub-sf-fulfillment-metabox .thaaniyamhub-status-assigned   { background: #fef3c7; color: #b45309; }
        #thaaniyamhub-sf-fulfillment-metabox .thaaniyamhub-status-pickup_scheduled { background: #f0fdf4; color: #166534; }
        #thaaniyamhub-sf-fulfillment-metabox .thaaniyamhub-status-picked_up  { background: #ede9fe; color: #6d28d9; }
        #thaaniyamhub-sf-fulfillment-metabox .thaaniyamhub-status-delivered  { background: #dcfce7; color: #15803d; }
        #thaaniyamhub-sf-fulfillment-metabox .thaaniyamhub-status-cancelled  { background: #fee2e2; color: #b91c1c; }
        #thaaniyamhub-sf-fulfillment-metabox .thaaniyamhub-status-rto        { background: #ffedd5; color: #c2410c; }
        #thaaniyamhub-sf-fulfillment-metabox .thaaniyamhub-status-return_initiated { background: #fee2e2; color: #b91c1c; }
        #thaaniyamhub-sf-fulfillment-metabox .thaaniyamhub-status-return_picked_up { background: #ede9fe; color: #6d28d9; }
        #thaaniyamhub-sf-fulfillment-metabox .thaaniyamhub-status-return_ofd       { background: #fef3c7; color: #b45309; }
        #thaaniyamhub-sf-fulfillment-metabox .thaaniyamhub-status-returned         { background: #dcfce7; color: #15803d; }
        #thaaniyamhub-sf-fulfillment-metabox .thaaniyamhub-status-return_cancelled { background: #fee2e2; color: #b91c1c; }
        #thaaniyamhub-sf-fulfillment-metabox .thaaniyamhub-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
        #thaaniyamhub-sf-fulfillment-metabox .thaaniyamhub-actions .button {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            line-height: 1 !important;
            height: 30px !important;
            padding: 0 10px !important;
            box-sizing: border-box !important;
        }
        #thaaniyamhub-sf-fulfillment-metabox .thaaniyamhub-actions .button .dashicons {
            margin: 0 !important;
            padding: 0 !important;
            line-height: 1 !important;
            font-size: 18px !important;
            width: 18px !important;
            height: 18px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            align-self: center !important;
        }
        #thaaniyamhub-sf-fulfillment-metabox .thaaniyamhub-not-dispatched { background: #fff7ed; border: 1px solid #fed7aa; border-radius: 6px; padding: 12px; color: #9a3412; font-size: 13px; }

        /* Row Actions icons */
        .thaaniyamhub-sf-row-action { display: inline-block; margin-right: 5px; }
        .thaaniyamhub-sf-action-push::after { content: "\f546"; font-family: dashicons; }
        .thaaniyamhub-sf-action-awb::after { content: "\f312"; font-family: dashicons; }
        .thaaniyamhub-sf-action-schedule::after { content: "\f508"; font-family: dashicons; }
        .thaaniyamhub-sf-action-label::after { content: "\f115"; font-family: dashicons; }
        .thaaniyamhub-sf-action-manifest::after { content: "\f481"; font-family: dashicons; }
        .thaaniyamhub-sf-action-invoice::after { content: "\f497"; font-family: dashicons; }
        .thaaniyamhub-sf-action-cancel::after { content: "\f158"; font-family: dashicons; color: #b91c1c; }
        .ag-sf-loading-row { opacity: 0.5; pointer-events: none; }

        /* Courier loader */
        .thaaniyamhub-sf-courier-loading { color: #888; font-size: 12px; }
        @keyframes thaaniyamhub-sf-spin-anim { to { transform: rotate(360deg); } }
        .thaaniyamhub-sf-spin { display: inline-block; animation: thaaniyamhub-sf-spin-anim .7s linear infinite; }
        #thaaniyamhub-sf-courier-loader select, #thaaniyamhub-sf-courier-reassign-loader select { width: 100%; margin-top: 4px; }

        /* Refund Status Badges */
        .thaaniyamhub-refund-badge-wrap { margin-top: 5px; display: block; }
        .thaaniyamhub-refund-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.3;
            white-space: nowrap;
            letter-spacing: 0.2px;
        }
        .thaaniyamhub-badge-refund-requested {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        .thaaniyamhub-badge-refund-completed {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .thaaniyamhub-badge-refund-cancelled {
            background: #f3f4f6;
            color: #4b5563;
            border: 1px solid #e5e7eb;
        }
        tr.thaaniyamhub-refund-requested td {
            background-color: #fffef7 !important;
        }
        .thaaniyamhub-status-refund-badge {
            display: block;
            margin-top: 5px;
        }
        ';

        wp_register_style('ag-sf-dashboard-styles', false);
        wp_enqueue_style('ag-sf-dashboard-styles');
        wp_add_inline_style('ag-sf-dashboard-styles', $css);

        // Inline JS
        $js = '
        jQuery(function($) {
            "use strict";

            // Inject custom confirm modal CSS
            var modalCss = `
                .thaaniyam-confirm-backdrop {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.6);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 99999999;
                    opacity: 0;
                    transition: opacity 0.2s ease-in-out;
                    pointer-events: none;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                }
                .thaaniyam-confirm-backdrop.show {
                    opacity: 1;
                    pointer-events: auto;
                }
                .thaaniyam-confirm-card {
                    background: #fff;
                    border-radius: 12px;
                    padding: 30px 25px;
                    width: 90%;
                    max-width: 460px;
                    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
                    transform: scale(0.85);
                    transition: transform 0.2s ease-in-out;
                }
                .thaaniyam-confirm-backdrop.show .thaaniyam-confirm-card {
                    transform: scale(1);
                }
                .thaaniyam-confirm-icon {
                    width: 60px;
                    height: 60px;
                    border: 3px solid #ff6600;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 20px auto;
                    font-size: 32px;
                    color: #ff6600;
                }
                .thaaniyam-confirm-title {
                    font-size: 20px;
                    font-weight: bold;
                    margin: 0 0 15px 0;
                    color: #333;
                    text-align: center;
                }
                .thaaniyam-confirm-text {
                    font-size: 14px;
                    color: #555;
                    line-height: 1.6;
                    margin: 0 0 25px 0;
                    background: #f9fafb;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    padding: 15px;
                    text-align: left;
                }
                .thaaniyam-confirm-actions {
                    display: flex;
                    justify-content: flex-end;
                    gap: 12px;
                }
                .thaaniyam-confirm-btn-confirm {
                    background: #ff6600;
                    color: #fff !important;
                    border: none;
                    border-radius: 6px;
                    padding: 10px 22px;
                    font-size: 14px;
                    font-weight: bold;
                    cursor: pointer;
                    transition: background 0.15s ease-in-out;
                }
                .thaaniyam-confirm-btn-confirm:hover {
                    background: #e05500;
                }
                .thaaniyam-confirm-btn-cancel {
                    background: #e5e7eb;
                    color: #4b5563 !important;
                    border: none;
                    border-radius: 6px;
                    padding: 10px 22px;
                    font-size: 14px;
                    font-weight: bold;
                    cursor: pointer;
                    transition: background 0.15s ease-in-out;
                }
                .thaaniyam-confirm-btn-cancel:hover {
                    background: #d1d5db;
                }
            `;
            if ($("style#thaaniyam-confirm-css").length === 0) {
                $("<style id=\'thaaniyam-confirm-css\'>").text(modalCss).appendTo("head");
            }

            function showThaaniyamConfirm(title, addressHtml, onConfirmCallback) {
                $(".thaaniyam-confirm-backdrop").remove();
                var $backdrop = $(
                    "<div class=\'thaaniyam-confirm-backdrop\'>" +
                        "<div class=\'thaaniyam-confirm-card\'>" +
                            "<div class=\'thaaniyam-confirm-icon\'>🚚</div>" +
                            "<h2 class=\'thaaniyam-confirm-title\'></h2>" +
                            "<div class=\'thaaniyam-confirm-text\'></div>" +
                            "<div class=\'thaaniyam-confirm-actions\'>" +
                                "<button class=\'thaaniyam-confirm-btn-cancel\'>Cancel</button>" +
                                "<button class=\'thaaniyam-confirm-btn-confirm\'>Confirm & Push</button>" +
                            "</div>" +
                        "</div>" +
                    "</div>"
                );
                $backdrop.find(".thaaniyam-confirm-title").text(title);
                $backdrop.find(".thaaniyam-confirm-text").html(addressHtml);
                $("body").append($backdrop);

                setTimeout(function() {
                    $backdrop.addClass("show");
                }, 10);

                $backdrop.find(".thaaniyam-confirm-btn-confirm").on("click", function(e) {
                    e.preventDefault();
                    $backdrop.removeClass("show");
                    setTimeout(function() {
                        $backdrop.remove();
                        if (typeof onConfirmCallback === "function") {
                            onConfirmCallback();
                        }
                    }, 200);
                });

                $backdrop.find(".thaaniyam-confirm-btn-cancel").on("click", function(e) {
                    e.preventDefault();
                    $backdrop.removeClass("show");
                    setTimeout(function() {
                        $backdrop.remove();
                    }, 200);
                });
            }

            function showThaaniyamError(title, errorMsg) {
                $(".thaaniyam-confirm-backdrop").remove();
                var $backdrop = $(
                    "<div class=\'thaaniyam-confirm-backdrop\'>" +
                        "<div class=\'thaaniyam-confirm-card\' style=\'text-align: center;\'>" +
                            "<div class=\'thaaniyam-confirm-icon\' style=\'border-color: #b91c1c; color: #b91c1c; display: flex; align-items: center; justify-content: center;\'>⚠️</div>" +
                            "<h2 class=\'thaaniyam-confirm-title\' style=\'color: #b91c1c;\'></h2>" +
                            "<div class=\'thaaniyam-confirm-text\' style=\'background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; text-align: center; font-weight: 500;\'></div>" +
                            "<div class=\'thaaniyam-confirm-actions\' style=\'justify-content: center; margin-top: 15px;\'>" +
                                "<button class=\'thaaniyam-confirm-btn-confirm\' style=\'background: #b91c1c;\'>OK</button>" +
                            "</div>" +
                        "</div>" +
                    "</div>"
                );
                $backdrop.find(".thaaniyam-confirm-title").text(title);
                $backdrop.find(".thaaniyam-confirm-text").text(errorMsg);
                $("body").append($backdrop);

                setTimeout(function() {
                    $backdrop.addClass("show");
                }, 10);

                $backdrop.find(".thaaniyam-confirm-btn-confirm").on("click", function(e) {
                    e.preventDefault();
                    $backdrop.removeClass("show");
                    setTimeout(function() {
                        $backdrop.remove();
                    }, 200);
                });
            }

            function getErrorTitle(action) {
                if (action.indexOf("push_order") > -1) return "Dispatch Error";
                if (action.indexOf("schedule_pickup") > -1) return "Pickup Error";
                if (action.indexOf("generate_awb") > -1) return "AWB Error";
                if (action.indexOf("print_label") > -1) return "Label Error";
                if (action.indexOf("print_invoice") > -1) return "Invoice Error";
                if (action.indexOf("cancel_shipment") > -1) return "Cancel Error";
                return "Error";
            }

            // ---- Toggle Sub-orders in list ----
            function initThaaniyamSubOrders() {
                $("tr.thaaniyamhub-suborder-row, .thaaniyamhub-suborder-label").each(function () {
                    var $row = $(this).closest("tr");
                    var parentId = $row.attr("data-parent-id");

                    if (!parentId) {
                        var classList = $row.attr("class") ? $row.attr("class").split(/\s+/) : [];
                        $.each(classList, function (index, item) {
                            if (item.indexOf("parent-") === 0) {
                                parentId = item.substring(7);
                            }
                        });

                        if (!parentId) {
                            var href = $row.find(".thaaniyamhub-suborder-label a").attr("href");
                            var match = href && (href.match(/id=(\d+)/) || href.match(/post=(\d+)/));
                            if (match) {
                                parentId = match[1];
                            }
                        }

                        if (parentId) {
                            $row.attr("data-parent-id", parentId);
                            $row.addClass("thaaniyamhub-suborder-row");
                        }
                    }

                    if (parentId) {
                        var $parentRow = $("#order-" + parentId + ", #post-" + parentId + ", tr[id$=\"-" + parentId + "\"]");
                        var hasParentOnPage = $parentRow.length > 0 && $parentRow.find(".thaaniyamhub-toggle-suborders").length > 0;

                        if (hasParentOnPage) {
                            if (!$row.hasClass("ag-visible")) {
                                $row.addClass("thaaniyamhub-suborder-hidden");
                            }
                        } else {
                            $row.removeClass("thaaniyamhub-suborder-hidden").addClass("ag-visible");
                        }
                    }
                });
            }

            initThaaniyamSubOrders();

            function initRefundBadges() {
                $("tr.thaaniyamhub-has-refund, tr:has(.thaaniyamhub-refund-badge-data)").each(function() {
                    var $row = $(this);
                    var $badgeData = $row.find(".thaaniyamhub-refund-badge-data");
                    if ($badgeData.length && !$row.find(".column-order_status .thaaniyamhub-status-refund-badge").length) {
                        var status = $badgeData.data("refund-status");
                        var label = $badgeData.data("refund-label");
                        var badgeCls = $badgeData.data("refund-badge-cls");
                        var tip = $badgeData.data("refund-tip");
                        var $statusCol = $row.find(".column-order_status");
                        if ($statusCol.length) {
                            $statusCol.append("<div class=\"thaaniyamhub-status-refund-badge\"><span class=\"thaaniyamhub-refund-badge " + badgeCls + "\" title=\"" + (tip || "") + "\">" + label + "</span></div>");
                        }
                    }
                });
            }

            initRefundBadges();

            $(document).on("click", ".thaaniyamhub-toggle-suborders", function(e) {
                e.preventDefault();
                var $btn      = $(this);
                var parentId  = $btn.data("parent-id");
                var $rows     = $("tr.thaaniyamhub-suborder-row[data-parent-id=\"" + parentId + "\"], tr.thaaniyamhub-suborder-row.parent-" + parentId);
                var isOpen    = $btn.hasClass("open");

                if (isOpen) {
                    $rows.addClass("thaaniyamhub-suborder-hidden").removeClass("ag-visible");
                    $btn.removeClass("open");
                } else {
                    $rows.removeClass("thaaniyamhub-suborder-hidden").addClass("ag-visible");
                    $btn.addClass("open");
                }
            });

            // ---- Package Dimension Preset Selection ----
            $(document).on("change", "#thaaniyamhub_sf_package_preset, #ag_sf_fe_package_preset", function() {
                var $opt = $(this).find(":selected");
                var w = $opt.data("weight");
                var l = $opt.data("length");
                var wd = $opt.data("width");
                var h = $opt.data("height");

                if (w !== undefined && w !== "" && w !== null) {
                    $("#thaaniyamhub_sf_weight, #ag_sf_fe_weight").val(w).trigger("input");
                    $("#thaaniyamhub_sf_length, #ag_sf_fe_length").val(l).trigger("input");
                    $("#thaaniyamhub_sf_width, #ag_sf_fe_width").val(wd).trigger("input");
                    $("#thaaniyamhub_sf_height, #ag_sf_fe_height").val(h).trigger("input");
                }
            });

            // If user modifies input values manually, reset preset dropdown to custom
            $(document).on("input", "#thaaniyamhub_sf_weight, #thaaniyamhub_sf_length, #thaaniyamhub_sf_width, #thaaniyamhub_sf_height", function() {
                var $preset = $("#thaaniyamhub_sf_package_preset");
                var $sel = $preset.find(":selected");
                if ($sel.val()) {
                    var w = parseFloat($("#thaaniyamhub_sf_weight").val()) || 0;
                    var l = parseFloat($("#thaaniyamhub_sf_length").val()) || 0;
                    var wd = parseFloat($("#thaaniyamhub_sf_width").val()) || 0;
                    var h = parseFloat($("#thaaniyamhub_sf_height").val()) || 0;

                    var pw = parseFloat($sel.data("weight")) || 0;
                    var pl = parseFloat($sel.data("length")) || 0;
                    var pwd = parseFloat($sel.data("width")) || 0;
                    var ph = parseFloat($sel.data("height")) || 0;

                    if (w !== pw || l !== pl || wd !== pwd || h !== ph) {
                        $preset.val("");
                    }
                }
            });

            $(document).on("input", "#ag_sf_fe_weight, #ag_sf_fe_length, #ag_sf_fe_width, #ag_sf_fe_height", function() {
                var $preset = $("#ag_sf_fe_package_preset");
                var $sel = $preset.find(":selected");
                if ($sel.val()) {
                    var w = parseFloat($("#ag_sf_fe_weight").val()) || 0;
                    var l = parseFloat($("#ag_sf_fe_length").val()) || 0;
                    var wd = parseFloat($("#ag_sf_fe_width").val()) || 0;
                    var h = parseFloat($("#ag_sf_fe_height").val()) || 0;

                    var pw = parseFloat($sel.data("weight")) || 0;
                    var pl = parseFloat($sel.data("length")) || 0;
                    var pwd = parseFloat($sel.data("width")) || 0;
                    var ph = parseFloat($sel.data("height")) || 0;

                    if (w !== pw || l !== pl || wd !== pwd || h !== ph) {
                        $preset.val("");
                    }
                }
            });

            // ---- Order detail actions ----
            $(document).on("click", "#thaaniyamhub-sf-btn-push", function() {
                var $btn = $(this);
                var origText = $btn.text() || "Push to Shiprocket";
                var orderId = $btn.data("order-id");
                var pickupLocation = $("#thaaniyamhub_sf_pickup_location").val() || "";
                var weight = $("#thaaniyamhub_sf_weight").val() || "";
                var length = $("#thaaniyamhub_sf_length").val() || "";
                var width = $("#thaaniyamhub_sf_width").val() || "";
                var height = $("#thaaniyamhub_sf_height").val() || "";
                var courierId = $("input[name=\'thaaniyamhub_sf_pre_push_courier_id\']:checked").val() || "";

                // Get address details for confirmation
                var $select = $("#thaaniyamhub_sf_pickup_location");
                var raw = $select.attr("data-addresses") || "[]";
                var addresses = [];
                try { addresses = JSON.parse(raw); } catch(e) { addresses = []; }
                var addr = addresses.find(function(item) {
                    return (item.pickup_location || "").trim().toLowerCase() === pickupLocation.trim().toLowerCase();
                });

                var addressHtml = "";
                if (addr) {
                    var address_str = (addr.address || addr.address_line_1 || "") + " " + (addr.address_2 || addr.address_line_2 || "");
                    var location_str = addr.city + ", " + addr.state + " - " + (addr.pin_code || addr.pincode || addr.pin || "");
                    addressHtml += "<strong>Nickname:</strong> " + $("<div>").text(addr.pickup_location || "").html() + "<br>";
                    addressHtml += "<strong>Contact Name:</strong> " + $("<div>").text(addr.name || "").html() + "<br>";
                    addressHtml += "<strong>Phone:</strong> " + $("<div>").text(addr.phone || "").html() + "<br>";
                    addressHtml += "<strong>Address:</strong> " + $("<div>").text(address_str.trim() + ", " + location_str.trim()).html();
                } else {
                    addressHtml += "<strong>Nickname:</strong> " + $("<div>").text(pickupLocation).html() + "<br>";
                    addressHtml += "<span style=\'color: #b91c1c; font-weight: 600;\'>⚠️ Address not found</span>";
                }

                // Client-side validation: weight and dimensions are required
                if (!weight || parseFloat(weight) <= 0 || !length || parseFloat(length) <= 0 || !width || parseFloat(width) <= 0 || !height || parseFloat(height) <= 0) {
                    showThaaniyamError("Missing Package Details", "Weight must be greater than 0 kg and all dimensions (Length, Width, Height) are required before pushing to Shiprocket. Please fill in all fields marked with *.");
                    return;
                }

                showThaaniyamConfirm("Confirm Pickup Location", addressHtml, function() {
                    ag_sf_ajax($btn, "thaaniyamhub_sf_push_order", {
                        order_id: orderId,
                        pickup_location: pickupLocation,
                        weight: weight,
                        length: length,
                        width: width,
                        height: height,
                        courier_id: courierId
                    }, function(res) {
                        if (res.success) {
                            location.reload();
                        } else {
                            alert("Dispatch Error: " + res.data);
                            $btn.prop("disabled", false).text(origText);
                        }
                    });
                });
            });

            $(document).on("click", "#thaaniyamhub-sf-btn-fetch-couriers", function() {
                var $btn = $(this);
                var orderId = $btn.data("order-id");
                var pickupLocation = $("#thaaniyamhub_sf_pickup_location").val() || "";
                var weight = $("#thaaniyamhub_sf_weight").val() || "";
                var length = $("#thaaniyamhub_sf_length").val() || "";
                var breadth = $("#thaaniyamhub_sf_width").val() || "";
                var height = $("#thaaniyamhub_sf_height").val() || "";
                var $container = $("#thaaniyamhub-sf-pre-push-couriers");

                if (!pickupLocation) {
                    alert("Please select a pickup location first.");
                    return;
                }

                if (!weight || parseFloat(weight) <= 0 || !length || parseFloat(length) <= 0 || !breadth || parseFloat(breadth) <= 0 || !height || parseFloat(height) <= 0) {
                    showThaaniyamError("Missing Package Details", "Weight must be greater than 0 kg and all dimensions (Length, Width, Height) are required to fetch available couriers. Please fill in all fields.");
                    return;
                }

                $btn.prop("disabled", true).text("Fetching Couriers...");
                $container.hide().empty();

                var data = {
                    action: "thaaniyamhub_sf_get_serviceability_pre_push",
                    order_id: orderId,
                    pickup_location: pickupLocation,
                    weight: weight,
                    length: length,
                    breadth: breadth,
                    height: height,
                    _nonce: agSFDashboard.nonce
                };

                $.post(ajaxurl, data, function(res) {
                    $btn.prop("disabled", false).text("Fetch Available Couriers");
                    if (res.success && res.data.couriers && res.data.couriers.length > 0) {
                        var html = "<div style=\'font-weight:600;margin-bottom:5px;font-size:11px;\'>Select Courier:</div>";
                        $.each(res.data.couriers, function(i, c) {
                            var cid = c.courier_company_id || c.id || "";
                            var name = c.courier_name || c.name || "";
                            var rate = c.rate || c.freight_charge || "";
                            var etd = c.etd || c.expected_date || "";
                            var lbl = name;
                            if (rate) lbl += " — \u20b9" + rate;
                            if (etd) lbl += " (ETD: " + etd + ")";
                            
                            html += "<label style=\'display:block;font-size:11px;margin-bottom:4px;font-weight:normal;cursor:pointer;\'>";
                            html += "<input type=\'radio\' name=\'thaaniyamhub_sf_pre_push_courier_id\' value=\'" + cid + "\' style=\'margin-right:5px;\'>";
                            html += lbl;
                            html += "</label>";
                        });
                        $container.html(html).slideDown(200);
                    } else {
                        var errMsg = res.data || "No serviceability for the selected parameters.";
                        $container.html("<div style=\'color:#b91c1c;font-size:11px;\'>" + errMsg + "</div>").slideDown(200);
                    }
                }).fail(function() {
                    $btn.prop("disabled", false).text("Fetch Available Couriers");
                    $container.html("<div style=\'color:#b91c1c;font-size:11px;\'>Request failed.</div>").slideDown(200);
                });
            });

            $(document).on("click", "#thaaniyamhub-sf-btn-schedule-pickup", function() {
                var $btn = $(this);
                var orderId = $btn.data("order-id");
                ag_sf_ajax($btn, "thaaniyamhub_sf_schedule_pickup", { order_id: orderId }, function(res) {
                    if (res.success) {
                        location.reload();
                    } else {
                        alert("Pickup Error: " + res.data);
                        $btn.prop("disabled", false).text("Schedule Pickup");
                    }
                });
            });

            $(document).on("click", "#thaaniyamhub-sf-btn-reassign-courier", function() {
                var $btn = $(this);
                var orderId = $btn.data("order-id");
                var $section = $("#thaaniyamhub-sf-reassign-section");
                
                if ($section.is(":visible")) {
                    $section.slideUp(200);
                    return;
                }

                $section.slideDown(200);
                var $loader = $("#thaaniyamhub-sf-courier-reassign-loader");
                $loader.html("<span class=\"thaaniyamhub-sf-courier-loading\"><span class=\"dashicons dashicons-update thaaniyamhub-sf-spin\"></span> Loading couriers\u2026</span>");

                $.post(ajaxurl, {
                    action:   "thaaniyamhub_sf_get_couriers",
                    order_id: orderId,
                    _nonce:   agSFDashboard.nonce
                }, function(res) {
                    if (res.success && res.data.couriers.length > 0) {
                        var $sel = $("<select id=\"ag_sf_reassign_courier_id\" style=\"width:100%;\"></select>");
                        $.each(res.data.couriers, function(i, c) {
                            var cid   = c.courier_company_id || c.id || "";
                            var name  = c.courier_name || c.name || "";
                            var rate  = c.rate || c.freight_charge || "";
                            var etd   = c.etd || c.expected_date || "";
                            var lbl   = name;
                            if (rate) lbl += " \u2014 \u20b9" + rate;
                            if (etd)  lbl += " (ETD: " + etd + ")";
                            var $opt  = $("<option>").val(cid).text(lbl);
                            if (parseInt(cid) === parseInt(res.data.selected_id)) { $opt.prop("selected", true); }
                            $sel.append($opt);
                        });
                        $loader.html($sel);
                    } else {
                        var errMsg = (res && res.data) ? res.data : "No couriers found.";
                        $loader.html("<span style=\"color:#888;font-size:12px;\">\u26a0\ufe0f " + errMsg + "</span>");
                    }
                }).fail(function() {
                    $loader.html("<span style=\"color:#b91c1c;font-size:12px;\">\u274c Failed to load couriers.</span>");
                });
            });

            $(document).on("click", "#thaaniyamhub-sf-btn-reassign-submit", function() {
                var $btn = $(this);
                var orderId = $btn.data("order-id");
                var courierId = $("#ag_sf_reassign_courier_id").val() || "";

                if (!courierId) {
                    alert("Please select a courier partner first.");
                    return;
                }

                ag_sf_ajax($btn, "thaaniyamhub_sf_reassign_courier", { order_id: orderId, courier_id: courierId }, function(res) {
                    if (res.success) {
                        alert(res.data.message || "Courier partner reassigned successfully!");
                        location.reload();
                    } else {
                        alert("Reassignment Error: " + res.data);
                        $btn.prop("disabled", false).text("Assign Selected Courier");
                    }
                });
            });

            $(document).on("click", "#thaaniyamhub-sf-btn-generate-awb", function() {
                var $btn = $(this);
                var orderId = $btn.data("order-id");
                var pickupLocation = $("#thaaniyamhub_sf_pickup_location").val() || "";
                var weight = $("#thaaniyamhub_sf_weight").val() || "";
                var length = $("#thaaniyamhub_sf_length").val() || "";
                var width = $("#thaaniyamhub_sf_width").val() || "";
                var height = $("#thaaniyamhub_sf_height").val() || "";
                var courierId = $("input[name=\'thaaniyamhub_sf_pre_push_courier_id\']:checked").val() || "";

                ag_sf_ajax($btn, "thaaniyamhub_sf_generate_awb", {
                    order_id: orderId,
                    pickup_location: pickupLocation,
                    weight: weight,
                    length: length,
                    width: width,
                    height: height,
                    courier_id: courierId
                }, function(res) {
                    if (res.success) {
                        location.reload();
                    } else {
                        alert("AWB Error: " + res.data);
                        $btn.prop("disabled", false).text("Generate AWB");
                    }
                });
            });

            $(document).on("click", "#thaaniyamhub-sf-btn-print-label", function() {
                var $btn = $(this);
                var orderId = $btn.data("order-id");
                ag_sf_ajax($btn, "thaaniyamhub_sf_print_label", { order_id: orderId }, function(res) {
                    if (res.success && res.data.label_url) {
                        window.open(res.data.label_url, "_blank");
                        location.reload();
                    } else {
                        alert("Label Error: " + res.data);
                        $btn.prop("disabled", false).text("Print Label");
                    }
                });
            });

            $(document).on("click", "#thaaniyamhub-sf-btn-download-manifest", function() {
                var $btn = $(this);
                var orderId = $btn.data("order-id");
                var origHtml = $btn.html();
                $btn.prop("disabled", true).html("<span class=\"dashicons dashicons-update thaaniyamhub-sf-spin\"></span> Generating Manifest\u2026");

                $.post(ajaxurl, {
                    action: "thaaniyamhub_sf_download_manifest",
                    order_id: orderId,
                    _nonce: agSFDashboard.nonce
                }, function(res) {
                    $btn.prop("disabled", false).html(origHtml);
                    if (res.success && res.data.manifest_url) {
                        window.open(res.data.manifest_url, "_blank");
                        location.reload();
                    } else {
                        alert("Manifest Error: " + (res.data || "Could not generate manifest."));
                    }
                }).fail(function() {
                    $btn.prop("disabled", false).html(origHtml);
                    alert("Request failed. Please try again.");
                });
            });

            $(document).on("click", "#thaaniyamhub-sf-btn-print-invoice", function() {
                var $btn = $(this);
                var orderId = $btn.data("order-id");
                ag_sf_ajax($btn, "thaaniyamhub_sf_print_invoice", { order_id: orderId }, function(res) {
                    if (res.success && res.data.invoice_url) {
                        window.open(res.data.invoice_url, "_blank");
                        location.reload();
                    } else {
                        alert("Invoice Error: " + res.data);
                        $btn.prop("disabled", false).text("Print Invoice");
                    }
                });
            });

            $(document).on("click", "#thaaniyamhub-sf-btn-cancel-shipment", function() {
                if (!confirm("Cancel this Shiprocket shipment? This cannot be undone.")) return;
                var $btn = $(this);
                var orderId = $btn.data("order-id");
                ag_sf_ajax($btn, "thaaniyamhub_sf_cancel_shipment", { order_id: orderId }, function(res) {
                    if (res.success) {
                        location.reload();
                    } else {
                        alert("Cancel Error: " + res.data);
                        $btn.prop("disabled", false).text("Cancel Shipment");
                    }
                });
            });

            $(document).on("click", "#thaaniyamhub-sf-btn-return-order", function() {
                if (!confirm("Initiate return for this order in Shiprocket? The package will be picked up from the customer and shipped back to the vendor.")) return;
                var $btn = $(this);
                var orderId = $btn.data("order-id");
                ag_sf_ajax($btn, "thaaniyamhub_sf_initiate_return", { order_id: orderId }, function(res) {
                    if (res.success) {
                        alert("Return order initiated successfully!");
                        location.reload();
                    } else {
                        alert("Return Error: " + res.data);
                        $btn.prop("disabled", false).text("Return Order");
                    }
                });
            });

            $(document).on("click", "#thaaniyamhub-sf-toggle-new-pickup", function(e) {
                e.preventDefault();
                $("#thaaniyamhub-sf-new-pickup-form").slideToggle(200);
            });

            $(document).on("click", "#thaaniyamhub-sf-make-default-pickup", function(e) {
                e.preventDefault();
                var $link = $(this);
                var orderId = $("#thaaniyamhub-sf-fulfillment-metabox").data("order-id");
                var pickupLocation = $("#thaaniyamhub_sf_pickup_location").val();
                
                if (!pickupLocation) {
                    alert("Please select a pickup location first.");
                    return;
                }

                $link.text("Saving…");
                var data = {
                    action: "thaaniyamhub_sf_set_default_pickup",
                    order_id: orderId,
                    pickup_location: pickupLocation,
                    _nonce: agSFDashboard.nonce
                };

                $.post(ajaxurl, data, function(res) {
                    if (res.success) {
                        alert(res.data);
                    } else {
                        alert("Error: " + res.data);
                    }
                    $link.text("⭐ Make default");
                }).fail(function() {
                    alert("Request failed.");
                    $link.text("⭐ Make default");
                });
            });

            $(document).on("click", "#thaaniyamhub-sf-btn-add-pickup", function() {
                var $btn = $(this);
                var orderId = $("#thaaniyamhub-sf-fulfillment-metabox").data("order-id");
                
                var params = {
                    action: "thaaniyamhub_sf_add_pickup_location",
                    order_id: orderId,
                    pickup_location: $("#thaaniyamhub_np_nickname").val(),
                    name: $("#thaaniyamhub_np_name").val(),
                    phone: $("#thaaniyamhub_np_phone").val(),
                    email: $("#thaaniyamhub_np_email").val(),
                    address: $("#thaaniyamhub_np_address").val(),
                    address_2: $("#thaaniyamhub_np_address2").val(),
                    city: $("#thaaniyamhub_np_city").val(),
                    state: $("#thaaniyamhub_np_state").val(),
                    pin_code: $("#thaaniyamhub_np_pincode").val(),
                    _nonce: agSFDashboard.nonce
                };

                if (!params.pickup_location || !params.name || !params.phone || !params.email || !params.address || !params.city || !params.state || !params.pin_code) {
                    alert("All fields except Address Line 2 are required.");
                    return;
                }

                $btn.prop("disabled", true).text("Registering…");

                $.post(ajaxurl, params, function(res) {
                    $btn.prop("disabled", false).text("Register Address");
                    if (res.success) {
                        var nickname = res.data.pickup_location;
                        var $select = $("#thaaniyamhub_sf_pickup_location");
                        if ($select.find("option[value=\'" + nickname + "\']").length === 0) {
                            $select.append($("<option>", {
                                value: nickname,
                                text: nickname
                            }));
                        }
                        $select.val(nickname);

                        var raw = $select.attr("data-addresses") || "[]";
                        var addresses = [];
                        try { addresses = JSON.parse(raw); } catch(e) { addresses = []; }
                        if (res.data.address_data) {
                            addresses.push(res.data.address_data);
                            $select.attr("data-addresses", JSON.stringify(addresses));
                        }

                        $select.trigger("change");
                        alert(res.data.message);
                        $("#thaaniyamhub-sf-new-pickup-form").slideUp(200);
                        $("#thaaniyamhub-sf-new-pickup-form input").val("");
                    } else {
                        alert("API Error: " + res.data);
                    }
                }).fail(function() {
                    $btn.prop("disabled", false).text("Register Address");
                    alert("Request failed.");
                });
            });

            $(document).on("click", ".thaaniyamhub-sf-row-action a", function(e) {
                e.preventDefault();
                var $a = $(this);
                var $parent = $a.parent();
                var actionClass = $parent.attr("class");
                var orderId = $parent.data("id") || $a.closest("tr").find(".column-order_title a, .column-order_number a").text().replace("#", "").trim();
                
                if (!orderId) {
                    var href = $a.closest("tr").find(".column-order_title a").attr("href") || "";
                    var match = href.match(/post=(\d+)/) || href.match(/id=(\d+)/);
                    if (match) {
                        orderId = match[1];
                    }
                }
                
                if (!orderId) return;

                var action = "";
                if (actionClass.indexOf("thaaniyamhub-sf-action-push") !== -1) {
                    action = "thaaniyamhub_sf_push_order";
                } else if (actionClass.indexOf("thaaniyamhub-sf-action-awb") !== -1) {
                    action = "thaaniyamhub_sf_generate_awb";
                } else if (actionClass.indexOf("thaaniyamhub-sf-action-schedule") !== -1) {
                    action = "thaaniyamhub_sf_schedule_pickup";
                } else if (actionClass.indexOf("thaaniyamhub-sf-action-label") !== -1) {
                    action = "thaaniyamhub_sf_print_label";
                } else if (actionClass.indexOf("thaaniyamhub-sf-action-manifest") !== -1) {
                    action = "thaaniyamhub_sf_download_manifest";
                } else if (actionClass.indexOf("thaaniyamhub-sf-action-invoice") !== -1) {
                    action = "thaaniyamhub_sf_print_invoice";
                } else if (actionClass.indexOf("thaaniyamhub-sf-action-cancel") !== -1) {
                    if (!confirm("Cancel this Shiprocket shipment?")) return;
                    action = "thaaniyamhub_sf_cancel_shipment";
                }

                if (!action) return;

                var $row = $a.closest("tr");
                $row.addClass("ag-sf-loading-row");

                var data = {
                    action: action,
                    order_id: orderId,
                    _nonce: agSFDashboard.nonce
                };

                $.post(ajaxurl, data, function(res) {
                    $row.removeClass("ag-sf-loading-row");
                    if (res.success) {
                        if (action === "thaaniyamhub_sf_print_label" && res.data.label_url) {
                            window.open(res.data.label_url, "_blank");
                        } else if (action === "thaaniyamhub_sf_print_invoice" && res.data.invoice_url) {
                            window.open(res.data.invoice_url, "_blank");
                        } else if (action === "thaaniyamhub_sf_download_manifest" && res.data.manifest_url) {
                            window.open(res.data.manifest_url, "_blank");
                            location.reload();
                        } else {
                            location.reload();
                        }
                    } else {
                        alert("Error: " + res.data);
                    }
                }).fail(function() {
                    $row.removeClass("ag-sf-loading-row");
                    alert("Request failed.");
                });
            });

            function ag_sf_ajax($btn, action, data, cb) {
                var origText = $btn.text() || "Submit";
                $btn.prop("disabled", true).text("Please wait…");
                data.action = action;
                data._nonce = agSFDashboard.nonce;
                $.post(ajaxurl, data, function(res) {
                    if (res && res.success) {
                        cb(res);
                    } else {
                        var errMsg = (res && res.data) ? res.data : "Unknown error occurred.";
                        showThaaniyamError(getErrorTitle(action), errMsg);
                        $btn.prop("disabled", false).text(origText);
                    }
                }).fail(function(jqXHR) {
                    var errorMsg = "Request failed. Check your connection.";
                    if (jqXHR.responseJSON && jqXHR.responseJSON.data) {
                        errorMsg = jqXHR.responseJSON.data;
                    } else if (jqXHR.responseText) {
                        try {
                            var res = JSON.parse(jqXHR.responseText);
                            if (res && res.data) {
                                errorMsg = res.data;
                            }
                        } catch(e) {}
                    }
                    showThaaniyamError(getErrorTitle(action), errorMsg);
                    $btn.prop("disabled", false).text(origText);
                });
            }
            
            $(".thaaniyamhub-sf-row-action").each(function() {
                var $parent = $(this);
                $parent.closest("tr").find(".column-order_title a").parent().append($parent);
            });

            function agSfLoadCouriers(orderId) {
                var $loader = $("#thaaniyamhub-sf-courier-loader");
                if ($loader.length === 0) return;

                $loader.html("<span class=\"thaaniyamhub-sf-courier-loading\"><span class=\"dashicons dashicons-update thaaniyamhub-sf-spin\"></span> Loading couriers\u2026</span>");

                $.post(ajaxurl, {
                    action:   "thaaniyamhub_sf_get_couriers",
                    order_id: orderId,
                    _nonce:   agSFDashboard.nonce
                }, function(res) {
                    if (res.success && res.data.couriers.length > 0) {
                        var $sel = $("<select id=\"ag_sf_courier_id\" style=\"width:100%;\"></select>");
                        $.each(res.data.couriers, function(i, c) {
                            var cid   = c.courier_company_id || c.id || "";
                            var name  = c.courier_name || c.name || "";
                            var rate  = c.rate || c.freight_charge || "";
                            var etd   = c.etd || c.expected_date || "";
                            var lbl   = name;
                            if (rate) lbl += " \u2014 \u20b9" + rate;
                            if (etd)  lbl += " (ETD: " + etd + ")";
                            var $opt  = $("<option>").val(cid).text(lbl);
                            if (parseInt(cid) === parseInt(res.data.selected_id)) { $opt.prop("selected", true); }
                            $sel.append($opt);
                        });
                        $loader.html($sel);
                    } else {
                        var errMsg = (res && res.data) ? res.data : "No couriers found.";
                        $loader.html("<span style=\"color:#888;font-size:12px;\">\u26a0\ufe0f " + errMsg + "</span>");
                    }
                }).fail(function() {
                    $loader.html("<span style=\"color:#b91c1c;font-size:12px;\">\u274c Failed to load couriers.</span>");
                });
            }

            var $courierLoader = $("#thaaniyamhub-sf-courier-loader");
            if ($courierLoader.length > 0) {
                agSfLoadCouriers($courierLoader.data("order-id"));
            }

            $(document).on("click", "#thaaniyamhub-sf-courier-retry", function(e) {
                e.preventDefault();
                var $loader = $("#thaaniyamhub-sf-courier-loader");
                if ($loader.length > 0) { agSfLoadCouriers($loader.data("order-id")); }
            });

            function updatePickupDetails($select) {
                var $wrapper = $select.closest(".thaaniyamhub-pickup-selector-wrapper");
                var $detailsBox = $wrapper.find(".thaaniyamhub-pickup-details-box");
                var val = $select.val();
                var raw = $select.attr("data-addresses") || "[]";
                var addresses = [];
                try { addresses = JSON.parse(raw); } catch(e) { addresses = []; }
                
                if (!val || !addresses || addresses.length === 0) {
                    $detailsBox.hide();
                    return;
                }
                
                var addr = addresses.find(function(item) {
                    return (item.pickup_location || "").trim().toLowerCase() === val.trim().toLowerCase();
                });
                
                if (addr) {
                    var address_str = (addr.address || addr.address_line_1 || "") + " " + (addr.address_2 || addr.address_line_2 || "");
                    var location_str = addr.city + ", " + addr.state + " - " + (addr.pin_code || addr.pincode || addr.pin || "");
                    $detailsBox.find(".pickup-contact-name").text(addr.name || "");
                    $detailsBox.find(".pickup-contact-phone").text(addr.phone || "");
                    $detailsBox.find(".pickup-address-details").text(address_str.trim() + ", " + location_str.trim());
                    
                    var $warning = $detailsBox.find(".pickup-lat-long-warning");
                    if (addr.lat_long_status === 0 || addr.lat_long_status === "0" || !addr.lat || !addr.long) {
                        $warning.show();
                    } else {
                        $warning.hide();
                    }
                    
                    $detailsBox.slideDown(150);
                } else {
                    $detailsBox.hide();
                }
            }

            $(document).on("change", "#thaaniyamhub_sf_pickup_location, #ag_sf_fe_pickup_location", function() {
                updatePickupDetails($(this));
            });

            setTimeout(function() {
                $("#thaaniyamhub_sf_pickup_location, #ag_sf_fe_pickup_location").each(function() {
                    updatePickupDetails($(this));
                });
            }, 100);
        });
        ';

        wp_register_style('ag-dashboard-styles', false);
        wp_enqueue_style('ag-dashboard-styles');
        wp_add_inline_style('ag-dashboard-styles', $css);

        wp_add_inline_script('woocommerce_admin', $js);
        wp_localize_script('woocommerce_admin', 'agSFDashboard', [
            'nonce' => wp_create_nonce('thaaniyamhub_sf_nonce'),
        ]);
    }

    // =========================================================================
    // 6. WCFM FRONTEND — ASSET ENQUEUE
    // =========================================================================

    public static function enqueue_frontend_assets()
    {
        global $wp;
        $order_id = 0;
        if (isset($wp->query_vars['wcfm-orders-details'])) {
            $order_id = (int) $wp->query_vars['wcfm-orders-details'];
        }
        if (!$order_id) {
            $order_id = (int) get_query_var('wcfm-orders-details', 0);
        }

        $is_settings = isset($wp->query_vars['wcfm-settings']) || get_query_var('wcfm-settings', false);

        if (!$order_id && !$is_settings) {
            return;
        }

        $css = '
        /* AG Shiprocket — WCFM Frontend Panel */
        #ag-sf-wcfm-panel { margin-top:16px; }
        #ag-sf-wcfm-panel h4 { margin:0 0 12px 0; font-size:14px; font-weight:700; display:flex; align-items:center; gap:6px; }
        #ag-sf-wcfm-panel .thaaniyamhub-fulfillment-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:14px; }
        #ag-sf-wcfm-panel .thaaniyamhub-field-label { font-size:10px; color:#888; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:2px; }
        #ag-sf-wcfm-panel .thaaniyamhub-field-value { font-weight:600; font-size:13px; color:#1a1a1a; }
        #ag-sf-wcfm-panel .thaaniyamhub-status-badge { display:inline-block; padding:2px 8px; border-radius:9999px; font-size:11px; font-weight:600; text-transform:uppercase; }
        #ag-sf-wcfm-panel .thaaniyamhub-status-dispatched { background: #f3f4f6; color: #374151; }
        #ag-sf-wcfm-panel .thaaniyamhub-status-manifested { background:#e0f2fe; color:#0369a1; }
        #ag-sf-wcfm-panel .thaaniyamhub-status-assigned   { background:#fef3c7; color:#b45309; }
        #ag-sf-wcfm-panel .thaaniyamhub-status-pickup_scheduled { background: #f0fdf4; color: #166534; }
        #ag-sf-wcfm-panel .thaaniyamhub-status-picked_up  { background:#ede9fe; color:#6d28d9; }
        #ag-sf-wcfm-panel .thaaniyamhub-status-delivered  { background:#dcfce7; color:#15803d; }
        #ag-sf-wcfm-panel .thaaniyamhub-status-cancelled  { background:#fee2e2; color:#b91c1c; }
        #ag-sf-wcfm-panel .thaaniyamhub-status-rto        { background:#ffedd5; color:#c2410c; }
        #ag-sf-wcfm-panel .thaaniyamhub-status-return_initiated { background:#fee2e2; color:#b91c1c; }
        #ag-sf-wcfm-panel .thaaniyamhub-status-return_picked_up { background:#ede9fe; color:#6d28d9; }
        #ag-sf-wcfm-panel .thaaniyamhub-status-return_ofd       { background:#fef3c7; color:#b45309; }
        #ag-sf-wcfm-panel .thaaniyamhub-status-returned         { background:#dcfce7; color:#15803d; }
        #ag-sf-wcfm-panel .thaaniyamhub-status-return_cancelled { background:#fee2e2; color:#b91c1c; }
        #ag-sf-wcfm-panel .thaaniyamhub-not-dispatched { background:#fff7ed; border:1px solid #fed7aa; border-radius:6px; padding:12px; color:#9a3412; font-size:13px; margin-bottom:12px; }
        #ag-sf-wcfm-panel .thaaniyamhub-actions { display:flex; gap:8px; flex-wrap:wrap; margin-top:12px; }
        #ag-sf-wcfm-panel select { width:100%; padding:6px; margin-bottom:8px; border:1px solid #ccc; border-radius:4px; }
        #ag-sf-wcfm-panel label { display:block; font-weight:600; margin-bottom:4px; font-size:12px; }
        #thaaniyamhub-sf-new-pickup-form-fe { background:#f3f4f6; border:1px solid #d1d5db; border-radius:4px; padding:10px; margin-top:8px; display:none; }
        #thaaniyamhub-sf-new-pickup-form-fe input { width:100%; margin-bottom:4px; font-size:11px; padding:4px; border:1px solid #ccc; border-radius:3px; box-sizing:border-box; }
        #thaaniyamhub-sf-new-pickup-form-fe h5 { margin:0 0 8px 0; font-size:11px; color:#374151; }
        .ag-sf-fe-link { font-size:11px; text-decoration:none; margin-right:8px; cursor:pointer; }
        .wcfm-top-element-container { position: relative !important; }
        #add_new_order_dashboard.add_new_wcfm_ele_dashboard {
            position: absolute !important; top: 15px !important; right: 15px !important; bottom: auto !important; z-index: 999;
        }
        ';

        wp_register_style('ag-sf-frontend-styles', false);
        wp_enqueue_style('ag-sf-frontend-styles');
        wp_add_inline_style('ag-sf-frontend-styles', $css);

        $ajax_url = esc_js(admin_url('admin-ajax.php'));
        $js = '
        jQuery(function($) {
            "use strict";

            // Inject custom confirm modal CSS
            var modalCss = `
                .thaaniyam-confirm-backdrop {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.6);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 99999999;
                    opacity: 0;
                    transition: opacity 0.2s ease-in-out;
                    pointer-events: none;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                }
                .thaaniyam-confirm-backdrop.show {
                    opacity: 1;
                    pointer-events: auto;
                }
                .thaaniyam-confirm-card {
                    background: #fff;
                    border-radius: 12px;
                    padding: 30px 25px;
                    width: 90%;
                    max-width: 460px;
                    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
                    transform: scale(0.85);
                    transition: transform 0.2s ease-in-out;
                }
                .thaaniyam-confirm-backdrop.show .thaaniyam-confirm-card {
                    transform: scale(1);
                }
                .thaaniyam-confirm-icon {
                    width: 60px;
                    height: 60px;
                    border: 3px solid #ff6600;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 20px auto;
                    font-size: 32px;
                    color: #ff6600;
                }
                .thaaniyam-confirm-title {
                    font-size: 20px;
                    font-weight: bold;
                    margin: 0 0 15px 0;
                    color: #333;
                    text-align: center;
                }
                .thaaniyam-confirm-text {
                    font-size: 14px;
                    color: #555;
                    line-height: 1.6;
                    margin: 0 0 25px 0;
                    background: #f9fafb;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    padding: 15px;
                    text-align: left;
                }
                .thaaniyam-confirm-actions {
                    display: flex;
                    justify-content: flex-end;
                    gap: 12px;
                }
                .thaaniyam-confirm-btn-confirm {
                    background: #ff6600;
                    color: #fff !important;
                    border: none;
                    border-radius: 6px;
                    padding: 10px 22px;
                    font-size: 14px;
                    font-weight: bold;
                    cursor: pointer;
                    transition: background 0.15s ease-in-out;
                }
                .thaaniyam-confirm-btn-confirm:hover {
                    background: #e05500;
                }
                .thaaniyam-confirm-btn-cancel {
                    background: #e5e7eb;
                    color: #4b5563 !important;
                    border: none;
                    border-radius: 6px;
                    padding: 10px 22px;
                    font-size: 14px;
                    font-weight: bold;
                    cursor: pointer;
                    transition: background 0.15s ease-in-out;
                }
                .thaaniyam-confirm-btn-cancel:hover {
                    background: #d1d5db;
                }
            `;
            if ($("style#thaaniyam-confirm-css").length === 0) {
                $("<style id=\'thaaniyam-confirm-css\'>").text(modalCss).appendTo("head");
            }

            function showThaaniyamConfirm(title, addressHtml, onConfirmCallback) {
                $(".thaaniyam-confirm-backdrop").remove();
                var $backdrop = $(
                    "<div class=\'thaaniyam-confirm-backdrop\'>" +
                        "<div class=\'thaaniyam-confirm-card\'>" +
                            "<div class=\'thaaniyam-confirm-icon\'>🚚</div>" +
                            "<h2 class=\'thaaniyam-confirm-title\'></h2>" +
                            "<div class=\'thaaniyam-confirm-text\'></div>" +
                            "<div class=\'thaaniyam-confirm-actions\'>" +
                                "<button class=\'thaaniyam-confirm-btn-cancel\'>Cancel</button>" +
                                "<button class=\'thaaniyam-confirm-btn-confirm\'>Confirm & Push</button>" +
                            "</div>" +
                        "</div>" +
                    "</div>"
                );
                $backdrop.find(".thaaniyam-confirm-title").text(title);
                $backdrop.find(".thaaniyam-confirm-text").html(addressHtml);
                $("body").append($backdrop);

                setTimeout(function() {
                    $backdrop.addClass("show");
                }, 10);

                $backdrop.find(".thaaniyam-confirm-btn-confirm").on("click", function(e) {
                    e.preventDefault();
                    $backdrop.removeClass("show");
                    setTimeout(function() {
                        $backdrop.remove();
                        if (typeof onConfirmCallback === "function") {
                            onConfirmCallback();
                        }
                    }, 200);
                });

                $backdrop.find(".thaaniyam-confirm-btn-cancel").on("click", function(e) {
                    e.preventDefault();
                    $backdrop.removeClass("show");
                    setTimeout(function() {
                        $backdrop.remove();
                    }, 200);
                });
            }

            function showThaaniyamError(title, errorMsg) {
                $(".thaaniyam-confirm-backdrop").remove();
                var $backdrop = $(
                    "<div class=\'thaaniyam-confirm-backdrop\'>" +
                        "<div class=\'thaaniyam-confirm-card\' style=\'text-align: center;\'>" +
                            "<div class=\'thaaniyam-confirm-icon\' style=\'border-color: #b91c1c; color: #b91c1c; display: flex; align-items: center; justify-content: center;\'>⚠️</div>" +
                            "<h2 class=\'thaaniyam-confirm-title\' style=\'color: #b91c1c;\'></h2>" +
                            "<div class=\'thaaniyam-confirm-text\' style=\'background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; text-align: center; font-weight: 500;\'></div>" +
                            "<div class=\'thaaniyam-confirm-actions\' style=\'justify-content: center; margin-top: 15px;\'>" +
                                "<button class=\'thaaniyam-confirm-btn-confirm\' style=\'background: #b91c1c;\'>OK</button>" +
                            "</div>" +
                        "</div>" +
                    "</div>"
                );
                $backdrop.find(".thaaniyam-confirm-title").text(title);
                $backdrop.find(".thaaniyam-confirm-text").text(errorMsg);
                $("body").append($backdrop);

                setTimeout(function() {
                    $backdrop.addClass("show");
                }, 10);

                $backdrop.find(".thaaniyam-confirm-btn-confirm").on("click", function(e) {
                    e.preventDefault();
                    $backdrop.removeClass("show");
                    setTimeout(function() {
                        $backdrop.remove();
                    }, 200);
                });
            }

            function getErrorTitle(action) {
                if (action.indexOf("push_order") > -1) return "Dispatch Error";
                if (action.indexOf("schedule_pickup") > -1) return "Pickup Error";
                if (action.indexOf("generate_awb") > -1) return "AWB Error";
                if (action.indexOf("print_label") > -1) return "Label Error";
                if (action.indexOf("print_invoice") > -1) return "Invoice Error";
                if (action.indexOf("cancel_shipment") > -1) return "Cancel Error";
                return "Error";
            }

            var ajaxUrl = (typeof wcfm_params !== "undefined" && wcfm_params.ajax_url) ? wcfm_params.ajax_url : "' . $ajax_url . '";
            var nonce   = (typeof agSFFrontend !== "undefined") ? agSFFrontend.nonce : "";

            function agSfFeAjax($btn, action, data, cb) {
                var origText = $btn.text();
                $btn.prop("disabled", true).text("Please wait\u2026");
                data.action = action;
                data._nonce = nonce;
                $.post(ajaxUrl, data, function(res) {
                    if (res && res.success) {
                        cb(res);
                    } else {
                        var errMsg = (res && res.data) ? res.data : "Unknown error occurred.";
                        showThaaniyamError(getErrorTitle(action), errMsg);
                        $btn.prop("disabled", false).text(origText);
                    }
                }).fail(function(jqXHR) {
                    var errorMsg = "Request failed. Check your connection.";
                    if (jqXHR.responseJSON && jqXHR.responseJSON.data) {
                        errorMsg = jqXHR.responseJSON.data;
                    } else if (jqXHR.responseText) {
                        try {
                            var res = JSON.parse(jqXHR.responseText);
                            if (res && res.data) {
                                errorMsg = res.data;
                            }
                        } catch(e) {}
                    }
                    showThaaniyamError(getErrorTitle(action), errorMsg);
                    $btn.prop("disabled", false).text(origText);
                });
            }

            // ---- Package Dimension Preset Selection (Frontend) ----
            $(document).on("change", "#ag_sf_fe_package_preset", function() {
                var $opt = $(this).find(":selected");
                var w = $opt.data("weight");
                var l = $opt.data("length");
                var wd = $opt.data("width");
                var h = $opt.data("height");

                if (w !== undefined && w !== "" && w !== null) {
                    $("#ag_sf_fe_weight").val(w).trigger("input");
                    $("#ag_sf_fe_length").val(l).trigger("input");
                    $("#ag_sf_fe_width").val(wd).trigger("input");
                    $("#ag_sf_fe_height").val(h).trigger("input");
                }
            });

            // If user modifies input values manually, reset preset dropdown to custom
            $(document).on("input", "#ag_sf_fe_weight, #ag_sf_fe_length, #ag_sf_fe_width, #ag_sf_fe_height", function() {
                var $preset = $("#ag_sf_fe_package_preset");
                var $sel = $preset.find(":selected");
                if ($sel.val()) {
                    var w = parseFloat($("#ag_sf_fe_weight").val()) || 0;
                    var l = parseFloat($("#ag_sf_fe_length").val()) || 0;
                    var wd = parseFloat($("#ag_sf_fe_width").val()) || 0;
                    var h = parseFloat($("#ag_sf_fe_height").val()) || 0;

                    var pw = parseFloat($sel.data("weight")) || 0;
                    var pl = parseFloat($sel.data("length")) || 0;
                    var pwd = parseFloat($sel.data("width")) || 0;
                    var ph = parseFloat($sel.data("height")) || 0;

                    if (w !== pw || l !== pl || wd !== pwd || h !== ph) {
                        $preset.val("");
                    }
                }
            });

            $(document).on("click", "#ag-sf-fe-btn-push", function() {
                var $btn   = $(this);
                var origText = $btn.text() || "Push to Shiprocket";
                var oid    = $btn.data("order-id");
                var pickup = $("#ag_sf_fe_pickup_location").val() || "";
                var weight = $("#ag_sf_fe_weight").val() || "";
                var length = $("#ag_sf_fe_length").val() || "";
                var width  = $("#ag_sf_fe_width").val() || "";
                var height = $("#ag_sf_fe_height").val() || "";
                var courierId = $("input[name=\'ag_sf_fe_pre_push_courier_id\']:checked").val() || "";

                // Client-side validation: weight and dimensions are required
                if (!weight || parseFloat(weight) <= 0 || !length || parseFloat(length) <= 0 || !width || parseFloat(width) <= 0 || !height || parseFloat(height) <= 0) {
                    showThaaniyamError("Missing Package Details", "Weight and dimensions (Length, Width, Height) are required before pushing to Shiprocket. Please fill in all fields marked with *.");
                    return;
                }

                // Get address details for confirmation
                var $select = $("#ag_sf_fe_pickup_location");
                var raw = $select.attr("data-addresses") || "[]";
                var addresses = [];
                try { addresses = JSON.parse(raw); } catch(e) { addresses = []; }
                var addr = addresses.find(function(item) {
                    return (item.pickup_location || "").trim().toLowerCase() === pickup.trim().toLowerCase();
                });

                var addressHtml = "";
                if (addr) {
                    var address_str = (addr.address || addr.address_line_1 || "") + " " + (addr.address_2 || addr.address_line_2 || "");
                    var location_str = addr.city + ", " + addr.state + " - " + (addr.pin_code || addr.pincode || addr.pin || "");
                    addressHtml += "<strong>Nickname:</strong> " + $("<div>").text(addr.pickup_location || "").html() + "<br>";
                    addressHtml += "<strong>Contact Name:</strong> " + $("<div>").text(addr.name || "").html() + "<br>";
                    addressHtml += "<strong>Phone:</strong> " + $("<div>").text(addr.phone || "").html() + "<br>";
                    addressHtml += "<strong>Address:</strong> " + $("<div>").text(address_str.trim() + ", " + location_str.trim()).html();
                } else {
                    addressHtml += "<strong>Nickname:</strong> " + $("<div>").text(pickup).html() + "<br>";
                    addressHtml += "<span style=\'color: #b91c1c; font-weight: 600;\'>⚠️ Address not found</span>";
                }

                showThaaniyamConfirm("Confirm Pickup Location", addressHtml, function() {
                    agSfFeAjax($btn, "thaaniyamhub_sf_push_order", {
                        order_id: oid, pickup_location: pickup,
                        weight: weight, length: length, width: width, height: height,
                        courier_id: courierId
                    }, function(res) {
                        res.success ? location.reload() : alert("Dispatch Error: " + res.data) || $btn.prop("disabled", false).text(origText);
                    });
                });
            });

            $(document).on("click", "#ag-sf-fe-btn-fetch-couriers", function() {
                var $btn = $(this);
                var oid  = $btn.data("order-id");
                var pickup = $("#ag_sf_fe_pickup_location").val() || "";
                var weight = $("#ag_sf_fe_weight").val() || "";
                var length = $("#ag_sf_fe_length").val() || "";
                var breadth = $("#ag_sf_fe_width").val() || "";
                var height = $("#ag_sf_fe_height").val() || "";
                var $container = $("#ag-sf-fe-pre-push-couriers");

                if (!pickup) {
                    alert("Please select a pickup location first.");
                    return;
                }

                if (!weight || parseFloat(weight) <= 0 || !length || parseFloat(length) <= 0 || !breadth || parseFloat(breadth) <= 0 || !height || parseFloat(height) <= 0) {
                    showThaaniyamError("Missing Package Details", "Weight and dimensions (Length, Width, Height) are required to fetch available couriers. Please fill in all fields.");
                    return;
                }

                $btn.prop("disabled", true).text("Fetching Couriers...");
                $container.hide().empty();

                var data = {
                    action: "thaaniyamhub_sf_get_serviceability_pre_push",
                    order_id: oid, pickup_location: pickup,
                    weight: weight, length: length, breadth: breadth, height: height,
                    _nonce: nonce
                };

                $.post(ajaxUrl, data, function(res) {
                    $btn.prop("disabled", false).text("Fetch Available Couriers");
                    if (res.success && res.data.couriers && res.data.couriers.length > 0) {
                        var html = "<div style=\'font-weight:600;margin-bottom:5px;font-size:11px;\'>Select Courier:</div>";
                        $.each(res.data.couriers, function(i, c) {
                            var cid = c.courier_company_id || c.id || "";
                            var name = c.courier_name || c.name || "";
                            var rate = c.rate || c.freight_charge || "";
                            var etd = c.etd || c.expected_date || "";
                            var lbl = name;
                            if (rate) lbl += " — \u20b9" + rate;
                            if (etd) lbl += " (ETD: " + etd + ")";
                            
                            html += "<label style=\'display:block;font-size:11px;margin-bottom:4px;font-weight:normal;cursor:pointer;\'>";
                            html += "<input type=\'radio\' name=\'ag_sf_fe_pre_push_courier_id\' value=\'" + cid + "\' style=\'margin-right:5px;\'>";
                            html += lbl;
                            html += "</label>";
                        });
                        $container.html(html).slideDown(200);
                    } else {
                        var errMsg = res.data || "No serviceability for the selected parameters.";
                        $container.html("<div style=\'color:#b91c1c;font-size:11px;\'>" + errMsg + "</div>").slideDown(200);
                    }
                }).fail(function() {
                    $btn.prop("disabled", false).text("Fetch Available Couriers");
                    $container.html("<div style=\'color:#b91c1c;font-size:11px;\'>Request failed.</div>").slideDown(200);
                });
            });

            $(document).on("click", "#ag-sf-fe-btn-schedule", function() {
                var $btn = $(this);
                var oid  = $btn.data("order-id");
                agSfFeAjax($btn, "thaaniyamhub_sf_schedule_pickup", { order_id: oid }, function(res) {
                    res.success ? location.reload() : alert("Pickup Error: " + res.data) || $btn.prop("disabled", false).text("Schedule Pickup");
                });
            });

            $(document).on("click", "#ag-sf-fe-btn-manifest", function() {
                var $btn = $(this);
                var oid  = $btn.data("order-id");
                var origText = $btn.text();
                $btn.prop("disabled", true).text("Generating Manifest\u2026");

                $.post(ajaxUrl, {
                    action: "thaaniyamhub_sf_download_manifest",
                    order_id: oid,
                    _nonce: nonce
                }, function(res) {
                    $btn.prop("disabled", false).text(origText);
                    if (res.success && res.data.manifest_url) {
                        window.open(res.data.manifest_url, "_blank");
                        location.reload();
                    } else {
                        alert("Manifest Error: " + (res.data || "Could not generate manifest."));
                    }
                }).fail(function() {
                    $btn.prop("disabled", false).text(origText);
                    alert("Request failed. Please try again.");
                });
            });

            $(document).on("click", "#ag-sf-fe-btn-reassign", function() {
                var $btn = $(this);
                var oid  = $btn.data("order-id");
                var $section = $("#ag-sf-fe-reassign-section");

                if ($section.is(":visible")) {
                    $section.slideUp(200);
                    return;
                }

                $section.slideDown(200);
                var $loader = $("#ag-sf-fe-courier-reassign-loader");
                $loader.html("<span style=\"color:#888;font-size:12px;\">&#x231b; Loading couriers\u2026</span>");

                $.post(ajaxUrl, {
                    action:   "thaaniyamhub_sf_get_couriers",
                    order_id: oid, _nonce: nonce
                }, function(res) {
                    if (res.success && res.data.couriers.length > 0) {
                        var $sel = $("<select id=\"ag_sf_fe_reassign_courier_id\" style=\"width:100%;margin-top:4px;\"></select>");
                        $.each(res.data.couriers, function(i, c) {
                            var cid  = c.courier_company_id || c.id || "";
                            var name = c.courier_name || c.name || "";
                            var rate = c.rate || c.freight_charge || "";
                            var etd  = c.etd || c.expected_date || "";
                            var lbl  = name;
                            if (rate) lbl += " \u2014 \u20b9" + rate;
                            if (etd)  lbl += " (ETD: " + etd + ")";
                            var $opt = $("<option>").val(cid).text(lbl);
                            if (parseInt(cid) === parseInt(res.data.selected_id)) { $opt.prop("selected", true); }
                            $sel.append($opt);
                        });
                        $loader.html($sel);
                    } else {
                        var errMsg = (res && res.data) ? res.data : "No couriers found.";
                        $loader.html("<span style=\"color:#888;font-size:12px;\">\u26a0\ufe0f " + errMsg + "</span>");
                    }
                }).fail(function() {
                    $loader.html("<span style=\"color:#b91c1c;font-size:11px;\">\u274c Failed to load couriers.</span>");
                });
            });

            $(document).on("click", "#ag-sf-fe-btn-reassign-submit", function() {
                var $btn = $(this);
                var oid  = $btn.data("order-id");
                var cid  = $("#ag_sf_fe_reassign_courier_id").val() || "";

                if (!cid) {
                    alert("Please select a courier partner first.");
                    return;
                }

                agSfFeAjax($btn, "thaaniyamhub_sf_reassign_courier", { order_id: oid, courier_id: cid }, function(res) {
                    if (res.success) {
                        alert(res.data.message || "Courier partner reassigned successfully!");
                        location.reload();
                    } else {
                        alert("Reassignment Error: " + res.data);
                        $btn.prop("disabled", false).text("Assign Selected Courier");
                    }
                });
            });

            $(document).on("click", "#ag-sf-fe-btn-awb", function() {
                var $btn   = $(this);
                var oid    = $btn.data("order-id");
                var pickup = $("#ag_sf_fe_pickup_location").val() || "";
                var weight = $("#ag_sf_fe_weight").val() || "";
                var length = $("#ag_sf_fe_length").val() || "";
                var width  = $("#ag_sf_fe_width").val() || "";
                var height = $("#ag_sf_fe_height").val() || "";
                var cid    = $("input[name=\'ag_sf_fe_pre_push_courier_id\']:checked").val() || "";

                agSfFeAjax($btn, "thaaniyamhub_sf_generate_awb", {
                    order_id: oid, pickup_location: pickup,
                    weight: weight, length: length, width: width, height: height,
                    courier_id: cid
                }, function(res) {
                    res.success ? location.reload() : alert("AWB Error: " + res.data) || $btn.prop("disabled", false).text("Generate AWB");
                });
            });

            $(document).on("click", "#ag-sf-fe-btn-label", function() {
                var $btn = $(this);
                var oid  = $btn.data("order-id");
                agSfFeAjax($btn, "thaaniyamhub_sf_print_label", { order_id: oid }, function(res) {
                    if (res.success && res.data.label_url) { window.open(res.data.label_url, "_blank"); location.reload(); }
                    else { alert("Label Error: " + res.data); $btn.prop("disabled", false).text("Print Label"); }
                });
            });

            $(document).on("click", "#ag-sf-fe-btn-invoice", function() {
                var $btn = $(this);
                var oid  = $btn.data("order-id");
                agSfFeAjax($btn, "thaaniyamhub_sf_print_invoice", { order_id: oid }, function(res) {
                    if (res.success && res.data.invoice_url) { window.open(res.data.invoice_url, "_blank"); location.reload(); }
                    else { alert("Invoice Error: " + res.data); $btn.prop("disabled", false).text("Print Invoice"); }
                });
            });

            $(document).on("click", "#ag-sf-fe-btn-cancel", function() {
                if (!confirm("Cancel this Shiprocket shipment? This cannot be undone.")) return;
                var $btn = $(this);
                var oid  = $btn.data("order-id");
                agSfFeAjax($btn, "thaaniyamhub_sf_cancel_shipment", { order_id: oid }, function(res) {
                    res.success ? location.reload() : alert("Cancel Error: " + res.data) || $btn.prop("disabled", false).text("Cancel Shipment");
                });
            });

            $(document).on("click", "#ag-sf-fe-btn-return", function() {
                if (!confirm("Initiate return for this order in Shiprocket? The package will be picked up from the customer and shipped back to the vendor.")) return;
                var $btn = $(this);
                var oid  = $btn.data("order-id");
                agSfFeAjax($btn, "thaaniyamhub_sf_initiate_return", { order_id: oid }, function(res) {
                    if (res.success) {
                        alert("Return order initiated successfully!");
                        location.reload();
                    } else {
                        alert("Return Error: " + res.data);
                        $btn.prop("disabled", false).text("Return Order");
                    }
                });
            });

            $(document).on("click", "#ag-sf-fe-make-default", function(e) {
                e.preventDefault();
                var oid    = $("#ag-sf-wcfm-panel").data("order-id");
                var pickup = $("#ag_sf_fe_pickup_location").val();
                if (!pickup) { alert("Please select a pickup location first."); return; }
                var $lnk = $(this).text("Saving\u2026");
                $.post(ajaxUrl, { action: "thaaniyamhub_sf_set_default_pickup", order_id: oid, pickup_location: pickup, _nonce: nonce }, function(res) {
                    alert(res.success ? res.data : "Error: " + res.data);
                    $lnk.text("\u2b50 Make default");
                });
            });

            $(document).on("click", "#ag-sf-fe-toggle-new", function(e) {
                e.preventDefault();
                $("#thaaniyamhub-sf-new-pickup-form-fe").slideToggle(200);
            });

            $(document).on("click", "#ag-sf-fe-btn-add-pickup", function() {
                var $btn   = $(this);
                var oid    = $("#ag-sf-wcfm-panel").data("order-id");
                var params = {
                    action: "thaaniyamhub_sf_add_pickup_location", order_id: oid, _nonce: nonce,
                    pickup_location: $("#ag_fe_np_nickname").val(),
                    name:    $("#ag_fe_np_name").val(),    phone:   $("#ag_fe_np_phone").val(),
                    email:   $("#ag_fe_np_email").val(),   address: $("#ag_fe_np_address").val(),
                    address_2: $("#ag_fe_np_address2").val(), city:  $("#ag_fe_np_city").val(),
                    state:   $("#ag_fe_np_state").val(),   pin_code: $("#ag_fe_np_pincode").val()
                };
                if (!params.pickup_location || !params.name || !params.phone || !params.email || !params.address || !params.city || !params.state || !params.pin_code) {
                    alert("All fields except Address Line 2 are required."); return;
                }
                $btn.prop("disabled", true).text("Registering\u2026");
                $.post(ajaxUrl, params, function(res) {
                    $btn.prop("disabled", false).text("Register Address");
                    if (res.success) {
                        var nn   = res.data.pickup_location;
                        var $sel = $("#ag_sf_fe_pickup_location");
                        if ($sel.find("option[value=\'" + nn + "\']").length === 0) { $sel.append($("<option>", { value: nn, text: nn })); }
                        $sel.val(nn);

                        var raw = $sel.attr("data-addresses") || "[]";
                        var addresses = [];
                        try { addresses = JSON.parse(raw); } catch(e) { addresses = []; }
                        if (res.data.address_data) {
                            addresses.push(res.data.address_data);
                            $sel.attr("data-addresses", JSON.stringify(addresses));
                        }

                        $sel.trigger("change");
                        alert(res.data.message);
                        $("#thaaniyamhub-sf-new-pickup-form-fe").slideUp(200);
                        $("#thaaniyamhub-sf-new-pickup-form-fe input").val("");
                    } else { alert("API Error: " + res.data); }
                }).fail(function() { $btn.prop("disabled", false).text("Register Address"); alert("Request failed."); });
            });

            function agSfFELoadCouriers(orderId) {
                var $loader = $("#ag-sf-fe-courier-loader");
                if ($loader.length === 0) return;

                $loader.html("<span style=\"color:#888;font-size:12px;\">\u231b Loading couriers\u2026</span>");

                $.post(ajaxUrl, {
                    action:   "thaaniyamhub_sf_get_couriers",
                    order_id: orderId, _nonce: nonce
                }, function(res) {
                    if (res.success && res.data.couriers.length > 0) {
                        var $sel = $("<select id=\"ag_sf_fe_courier_id\" style=\"width:100%;margin-top:4px;\"></select>");
                        $.each(res.data.couriers, function(i, c) {
                            var cid  = c.courier_company_id || c.id || "";
                            var name = c.courier_name || c.name || "";
                            var rate = c.rate || c.freight_charge || "";
                            var etd  = c.etd || c.expected_date || "";
                            var lbl  = name;
                            if (rate) lbl += " \u2014 \u20b9" + rate;
                            if (etd)  lbl += " (ETD: " + etd + ")";
                            var $opt = $("<option>").val(cid).text(lbl);
                            if (parseInt(cid) === parseInt(res.data.selected_id)) { $opt.prop("selected", true); }
                            $sel.append($opt);
                        });
                        $loader.html($sel);
                    } else {
                        var errMsg = (res && res.data) ? res.data : "No couriers found.";
                        $loader.html("<span style=\"color:#888;font-size:12px;\">\u26a0\ufe0f " + errMsg + "</span>");
                    }
                }).fail(function() {
                    $loader.html("<span style=\"color:#b91c1c;font-size:11px;\">\u274c Failed to load couriers.</span>");
                });
            }

            var $feCourierLoader = $("#ag-sf-fe-courier-loader");
            if ($feCourierLoader.length > 0) {
                agSfFELoadCouriers($feCourierLoader.data("order-id"));
            }

            $(document).on("click", "#ag-sf-fe-courier-retry", function(e) {
                e.preventDefault();
                var $loader = $("#ag-sf-fe-courier-loader");
                if ($loader.length > 0) { agSfFELoadCouriers($loader.data("order-id")); }
            });

            $(document).on("click", "#ag-sf-settings-toggle-new", function(e) {
                e.preventDefault();
                $("#thaaniyamhub-sf-new-pickup-form-settings").slideToggle(200);
            });

            $(document).on("click", "#ag-sf-settings-btn-add-pickup", function() {
                var $btn   = $(this);
                var params = {
                    action: "thaaniyamhub_pm_vendor_add_location", _nonce: nonce,
                    pickup_location: $("#ag_settings_np_nickname").val(),
                    name:      $("#ag_settings_np_name").val(),
                    phone:     $("#ag_settings_np_phone").val(),
                    email:     $("#ag_settings_np_email").val(),
                    address:   $("#ag_settings_np_address").val(),
                    address_2: $("#ag_settings_np_address2").val(),
                    city:      $("#ag_settings_np_city").val(),
                    state:     $("#ag_settings_np_state").val(),
                    pin_code:  $("#ag_settings_np_pincode").val()
                };
                if (!params.pickup_location || !params.name || !params.phone || !params.email || !params.address || !params.city || !params.state || !params.pin_code) {
                    alert("All fields except Address Line 2 are required."); return;
                }
                $btn.prop("disabled", true).text("Registering\u2026");
                $.post(ajaxUrl, params, function(res) {
                    $btn.prop("disabled", false).text("Register Address");
                    if (res.success) {
                        var nn   = res.data.pickup_location;
                        var $sel = $("#wcfmmp_shiprocket_pickup_id");
                        if ($sel.find("option[value=\'" + nn + "\']").length === 0) { $sel.append($("<option>", { value: nn, text: nn })); }
                        $sel.val(nn);
                        alert(res.data.message);
                        $("#thaaniyamhub-sf-new-pickup-form-settings").slideUp(200);
                        $("#thaaniyamhub-sf-new-pickup-form-settings input").val("");
                    } else { alert("API Error: " + res.data); }
                }).fail(function() { $btn.prop("disabled", false).text("Register Address"); alert("Request failed."); });
            });

            function updatePickupDetails($select) {
                var $wrapper = $select.closest(".thaaniyamhub-pickup-selector-wrapper");
                var $detailsBox = $wrapper.find(".thaaniyamhub-pickup-details-box");
                var val = $select.val();
                var raw = $select.attr("data-addresses") || "[]";
                var addresses = [];
                try { addresses = JSON.parse(raw); } catch(e) { addresses = []; }
                
                if (!val || !addresses || addresses.length === 0) {
                    $detailsBox.hide();
                    return;
                }
                
                var addr = addresses.find(function(item) {
                    return (item.pickup_location || "").trim().toLowerCase() === val.trim().toLowerCase();
                });
                
                if (addr) {
                    var address_str = (addr.address || addr.address_line_1 || "") + " " + (addr.address_2 || addr.address_line_2 || "");
                    var location_str = addr.city + ", " + addr.state + " - " + (addr.pin_code || addr.pincode || addr.pin || "");
                    $detailsBox.find(".pickup-contact-name").text(addr.name || "");
                    $detailsBox.find(".pickup-contact-phone").text(addr.phone || "");
                    $detailsBox.find(".pickup-address-details").text(address_str.trim() + ", " + location_str.trim());
                    
                    var $warning = $detailsBox.find(".pickup-lat-long-warning");
                    if (addr.lat_long_status === 0 || addr.lat_long_status === "0" || !addr.lat || !addr.long) {
                        $warning.show();
                    } else {
                        $warning.hide();
                    }
                    $detailsBox.slideDown(150);
                } else {
                    $detailsBox.hide();
                }
            }

            $(document).on("change", "#thaaniyamhub_sf_pickup_location, #ag_sf_fe_pickup_location", function() {
                updatePickupDetails($(this));
            });

            setTimeout(function() {
                $("#thaaniyamhub_sf_pickup_location, #ag_sf_fe_pickup_location").each(function() {
                    updatePickupDetails($(this));
                });
            }, 100);
        });
        ';

        wp_add_inline_script('jquery', $js);
        wp_localize_script('jquery', 'agSFFrontend', [
            'nonce' => wp_create_nonce('thaaniyamhub_sf_nonce'),
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }

    // =========================================================================
    // 7. WCFM FRONTEND — SHIPROCKET PANEL
    // =========================================================================

    public static function render_wcfm_panel($order_id)
    {
        $order_id = (int) $order_id;
        if (!$order_id) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $vendor_id = 0;
        if (function_exists('wcfm_is_vendor') && wcfm_is_vendor()) {
            $vendor_id = get_current_user_id();
        } elseif (isset($_POST['vendor_id']) && !empty($_POST['vendor_id'])) {
            $vendor_id = absint($_POST['vendor_id']);
        }

        if (!$vendor_id && is_user_logged_in()) {
            $user = wp_get_current_user();
            if (in_array('wcfm_vendor', (array) $user->roles, true) || in_array('vendor', (array) $user->roles, true) || in_array('seller', (array) $user->roles, true)) {
                $vendor_id = $user->ID;
            }
        }

        if (!$vendor_id) {
            $vendor_id = (int) $order->get_meta('_order_vendor_id');
        }
        if (!$vendor_id && class_exists('ThaaniyamHub_Order_Splitter')) {
            $vendor_id = ThaaniyamHub_Order_Splitter::get_vendor_id($order);
        }

        if (!ThaaniyamHub_Dispatch::is_order_eligible_for_fulfillment($order)) {
            return;
        }

        $record = ThaaniyamHub_Shiprocket_API::get_fulfillment($order_id);
        $status_class = $record ? 'thaaniyamhub-status-' . sanitize_html_class($record->fulfillment_status) : '';

        echo '<div class="wcfm-container" id="ag-sf-wcfm-panel" data-order-id="' . esc_attr($order_id) . '">';
        echo '<div class="wcfm-content">';

        // ── Panel styles ─────────────────────────────────────────────────────
        echo '<style>
        #ag-sf-wcfm-panel { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .ag-sf-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 4px rgba(0,0,0,.06);
            margin-bottom: 16px;
        }
        .ag-sf-card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 18px;
            background: linear-gradient(135deg, #1a1f36 0%, #2d3561 100%);
            color: #fff;
        }
        .ag-sf-card-header .ag-sf-icon {
            width: 32px; height: 32px;
            background: rgba(255,255,255,.15);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px;
        }
        .ag-sf-card-header h4 {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: .3px;
            color: #fff !important;
        }
        .ag-sf-card-header .ag-sf-sub {
            font-size: 11px;
            color: rgba(255,255,255,.65);
            margin-top: 1px;
        }
        .ag-sf-card-body { padding: 18px; }
        .ag-sf-status-banner {
            display: flex; align-items: flex-start; gap: 10px;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 13px;
        }
        .ag-sf-status-banner.pending {
            background: #fffbeb;
            border: 1px solid #fcd34d;
            color: #92400e;
        }
        .ag-sf-status-banner.failed {
            background: #fef2f2;
            border: 1px solid #fca5a5;
            color: #7f1d1d;
        }
        .ag-sf-status-banner .ag-sf-status-icon { font-size: 16px; margin-top: 1px; flex-shrink: 0; }
        .ag-sf-status-banner strong { display: block; font-weight: 700; margin-bottom: 2px; }
        .ag-sf-section-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .7px;
            color: #6b7280;
            margin-bottom: 10px;
        }
        .ag-sf-pickup-card {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 12.5px;
            line-height: 1.6;
            margin-bottom: 18px;
        }
        .ag-sf-pickup-card .store-name {
            font-weight: 700;
            font-size: 13.5px;
            color: #111827;
            margin-bottom: 2px;
        }
        .ag-sf-pickup-card .contact { color: #374151; }
        .ag-sf-pickup-card .address { color: #6b7280; }
        .ag-sf-pickup-not-found {
            display: inline-flex; align-items: center; gap: 6px;
            background: #fef2f2; border: 1px solid #fca5a5;
            border-radius: 6px; padding: 8px 12px;
            font-size: 12px; color: #991b1b; font-weight: 600;
            margin-bottom: 16px;
        }
        .ag-sf-dims-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 14px;
        }
        .ag-sf-field { display: flex; flex-direction: column; }
        .ag-sf-field label {
            font-size: 11px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 5px;
            letter-spacing: .2px;
        }
        .ag-sf-field label .req { color: #ef4444; margin-left: 2px; }
        .ag-sf-field input[type="number"] {
            width: 100%;
            padding: 8px 10px;
            border: 1.5px solid #d1d5db;
            border-radius: 7px;
            font-size: 13px;
            color: #111827;
            transition: border-color .2s, box-shadow .2s;
            box-sizing: border-box;
            background: #fff;
        }
        .ag-sf-field input[type="number"][readonly] {
            background-color: #f3f4f6;
            cursor: not-allowed;
        }
        .ag-sf-field input[type="number"]:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,.12);
        }
        .ag-sf-btn-fetch {
            width: 100%;
            padding: 10px 18px;
            background: #fff;
            border: 1.5px solid #6366f1;
            border-radius: 8px;
            color: #4f46e5;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .3px;
            cursor: pointer;
            transition: background .2s, color .2s;
            margin-bottom: 10px;
            display: flex; align-items: center; justify-content: center; gap: 6px;
        }
        .ag-sf-btn-fetch:hover { background: #eef2ff; }
        .ag-sf-couriers-list {
            display: none;
            margin-bottom: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #f9fafb;
            max-height: 160px;
            overflow-y: auto;
            padding: 10px;
        }
        .ag-sf-divider { border: 0; border-top: 1px solid #e5e7eb; margin: 16px 0; }
        .ag-sf-btn-push {
            width: 100%;
            padding: 12px 18px;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            border: 0;
            border-radius: 8px;
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: .4px;
            cursor: pointer;
            transition: opacity .2s, transform .1s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            box-shadow: 0 2px 8px rgba(99,102,241,.35);
            text-transform: uppercase;
        }
        .ag-sf-btn-push:hover { opacity: .9; transform: translateY(-1px); }
        .ag-sf-btn-push:active { transform: translateY(0); }
        </style>';

        echo '<div class="ag-sf-card">';
        echo '<div class="ag-sf-card-header">';
        echo '<div class="ag-sf-icon">🚚</div>';
        echo '<div>';
        echo '<h4>' . esc_html__('Shiprocket Fulfillment', 'thaaniyamhub-multi-vendor-orders') . '</h4>';
        echo '<div class="ag-sf-sub">' . esc_html__('Push this order to Shiprocket for shipping', 'thaaniyamhub-multi-vendor-orders') . '</div>';
        echo '</div>';
        echo '</div>'; // .ag-sf-card-header
        echo '<div class="ag-sf-card-body">';

        if (!$record || !$record->shiprocket_order_id) {
            if ('processing' !== $order->get_status()) {
                echo '<div class="ag-sf-status-banner failed">';
                echo '<div class="ag-sf-status-icon">🔒</div>';
                echo '<div><strong>' . esc_html__('Cannot Push', 'thaaniyamhub-multi-vendor-orders') . '</strong>' . esc_html__('Order must be in Processing status before it can be pushed to Shiprocket.', 'thaaniyamhub-multi-vendor-orders') . '</div>';
                echo '</div>';
                echo '</div></div></div>';
                return;
            }

            $default_pickup = get_user_meta($vendor_id, '_shiprocket_pickup_id', true);
            if (!$default_pickup) {
                $default_pickup = ThaaniyamHub_Dispatch::resolve_pickup_nickname($vendor_id);
            }

            $api = new ThaaniyamHub_Shiprocket_API();
            $pickup_res = $api->get_pickup_addresses();
            $pickup_addresses = [];
            if (!is_wp_error($pickup_res) && !empty($pickup_res['data']['shipping_address'])) {
                foreach ($pickup_res['data']['shipping_address'] as $addr) {
                    if (!empty($addr['pickup_location']) && !empty($addr['status'])) {
                        $pickup_addresses[] = $addr;
                    }
                }
            }

            $sync_failed = $order->get_meta('_shiprocket_sync_failed');
            $fail_reason = $order->get_meta('_shiprocket_sync_fail_reason');

            // Status banner
            if ($sync_failed) {
                echo '<div class="ag-sf-status-banner failed">';
                echo '<div class="ag-sf-status-icon">⚠️</div>';
                echo '<div><strong>' . esc_html__('Previous Dispatch Failed', 'thaaniyamhub-multi-vendor-orders') . '</strong>' . esc_html($fail_reason) . '</div>';
                echo '</div>';
            } else {
                echo '<div class="ag-sf-status-banner pending">';
                echo '<div class="ag-sf-status-icon">⏳</div>';
                echo '<div><strong>' . esc_html__('Awaiting Dispatch', 'thaaniyamhub-multi-vendor-orders') . '</strong>' . esc_html__('This order has not been pushed to Shiprocket yet. Click Push to Shiprocket Button.', 'thaaniyamhub-multi-vendor-orders') . '</div>';
                echo '</div>';
            }

            // ── Pickup Location ──────────────────────────────────────────────
            $assigned_addr_details = null;
            if (!empty($pickup_addresses)) {
                foreach ($pickup_addresses as $addr) {
                    if (isset($addr['pickup_location'])) {
                        $loc_clean = strtolower(preg_replace('/[^a-z0-9]/', '', trim($addr['pickup_location'])));
                        $def_clean = strtolower(preg_replace('/[^a-z0-9]/', '', trim($default_pickup)));
                        if ($loc_clean === $def_clean) {
                            $assigned_addr_details = $addr;
                            break;
                        }
                    }
                }
            }

            echo '<div class="ag-sf-section-label">📍 ' . esc_html__('Pickup Location', 'thaaniyamhub-multi-vendor-orders') . '</div>';

            if ($assigned_addr_details) {
                $addr_line = trim(($assigned_addr_details['address'] ?? $assigned_addr_details['address_line_1'] ?? '') . ' ' . ($assigned_addr_details['address_2'] ?? $assigned_addr_details['address_line_2'] ?? ''));
                $city_state_pin = trim(($assigned_addr_details['city'] ?? '') . ', ' . ($assigned_addr_details['state'] ?? '') . ' – ' . ($assigned_addr_details['pin_code'] ?? $assigned_addr_details['pincode'] ?? $assigned_addr_details['pin'] ?? ''));
                $contact_name = $assigned_addr_details['name'] ?? '';
                $contact_phone = $assigned_addr_details['phone'] ?? '';

                echo '<div class="ag-sf-pickup-card">';
                echo '<div class="store-name">' . esc_html($default_pickup) . '</div>';
                if ($contact_name || $contact_phone) {
                    echo '<div class="contact">' . esc_html($contact_name) . ($contact_phone ? ' · ' . esc_html($contact_phone) : '') . '</div>';
                }
                echo '<div class="address">' . esc_html($addr_line ? $addr_line . ', ' . $city_state_pin : $city_state_pin) . '</div>';
                echo '</div>';
            } else {
                echo '<div class="ag-sf-pickup-not-found">⚠️ ' . esc_html($default_pickup ?: __('No pickup location configured', 'thaaniyamhub-multi-vendor-orders')) . ' — not found in Shiprocket</div>';
            }

            echo '<input type="hidden" id="ag_sf_fe_pickup_location" name="pickup_location" value="' . esc_attr($default_pickup) . '" data-addresses="' . esc_attr(wp_json_encode($pickup_addresses)) . '">';

            echo '<hr class="ag-sf-divider">';

            // ── Package Dimensions ───────────────────────────────────────────
            $weight = $order->get_meta('_shiprocket_weight_override');
            $length = $order->get_meta('_shiprocket_length_override');
            $breadth = $order->get_meta('_shiprocket_width_override');
            $height = $order->get_meta('_shiprocket_height_override');

            $dimension_presets = class_exists('ThaaniyamHub_Dimension_Manager') ? ThaaniyamHub_Dimension_Manager::get_vendor_presets((int) $vendor_id) : [];

            echo '<div class="ag-sf-section-label">📦 ' . esc_html__('Package Details', 'thaaniyamhub-multi-vendor-orders') . '</div>';

            if (!empty($dimension_presets)) {
                echo '<div style="margin-bottom:8px;">';
                echo '<label for="ag_sf_fe_package_preset" style="font-size:11px;font-weight:600;color:#555;display:block;margin-bottom:3px;">' . esc_html__('Select Saved Dimension (Optional):', 'thaaniyamhub-multi-vendor-orders') . '</label>';
                echo '<select id="ag_sf_fe_package_preset" style="width:100%;font-size:12px;padding:4px 6px;margin-bottom:4px;border-radius:4px;border:1px solid #cbd5e1;">';
                echo '<option value="">' . esc_html__('-- Choose Preset or Enter Custom Below --', 'thaaniyamhub-multi-vendor-orders') . '</option>';
                foreach ($dimension_presets as $dp) {
                    $dp_label = sprintf('%s (%s kg | %s × %s × %s cm)', $dp['name'], $dp['weight'], $dp['length'], $dp['width'], $dp['height']);
                    echo '<option value="' . esc_attr($dp['id']) . '" data-weight="' . esc_attr($dp['weight']) . '" data-length="' . esc_attr($dp['length']) . '" data-width="' . esc_attr($dp['width']) . '" data-height="' . esc_attr($dp['height']) . '">' . esc_html($dp_label) . '</option>';
                }
                echo '</select>';
                echo '</div>';
            }

            echo '<div class="ag-sf-dims-grid">';
            echo '<div class="ag-sf-field"><label>Weight (kg)<span class="req">*</span></label><input type="number" id="ag_sf_fe_weight" step="0.001" min="0.01" placeholder="e.g. 0.500" value="' . esc_attr($weight) . '"></div>';
            echo '<div class="ag-sf-field"><label>Length (cm)<span class="req">*</span></label><input type="number" id="ag_sf_fe_length" step="0.1" min="1" placeholder="e.g. 20" value="' . esc_attr($length) . '" style="background-color:#f3f4f6;cursor:not-allowed;" readonly></div>';
            echo '<div class="ag-sf-field"><label>Width (cm)<span class="req">*</span></label><input type="number" id="ag_sf_fe_width" step="0.1" min="1" placeholder="e.g. 15" value="' . esc_attr($breadth) . '" style="background-color:#f3f4f6;cursor:not-allowed;" readonly></div>';
            echo '<div class="ag-sf-field"><label>Height (cm)<span class="req">*</span></label><input type="number" id="ag_sf_fe_height" step="0.1" min="1" placeholder="e.g. 10" value="' . esc_attr($height) . '" style="background-color:#f3f4f6;cursor:not-allowed;" readonly></div>';
            echo '</div>';

            echo '<button type="button" id="ag-sf-fe-btn-fetch-couriers" class="ag-sf-btn-fetch" data-order-id="' . esc_attr($order_id) . '">🔍 ' . esc_html__('Fetch Available Couriers', 'thaaniyamhub-multi-vendor-orders') . '</button>';
            echo '<div id="ag-sf-fe-pre-push-couriers" class="ag-sf-couriers-list"></div>';

            echo '<hr class="ag-sf-divider">';

            echo '<button type="button" id="ag-sf-fe-btn-push" class="ag-sf-btn-push" data-order-id="' . esc_attr($order_id) . '">🚀 ' . esc_html__('Push to Shiprocket', 'thaaniyamhub-multi-vendor-orders') . '</button>';

        } else {
            // Dispatched view
            ?>
            <div class="thaaniyamhub-fulfillment-grid">
                <div>
                    <div class="thaaniyamhub-field-label">
                        <?php esc_html_e('Shiprocket Order ID', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </div>
                    <div class="thaaniyamhub-field-value"><?php echo esc_html($record->shiprocket_order_id); ?></div>
                </div>
                <div>
                    <div class="thaaniyamhub-field-label"><?php esc_html_e('Shipment ID', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </div>
                    <div class="thaaniyamhub-field-value"><?php echo esc_html($record->shiprocket_shipment_id); ?></div>
                </div>
                <div>
                    <div class="thaaniyamhub-field-label"><?php esc_html_e('AWB Code', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </div>
                    <div class="thaaniyamhub-field-value"><?php echo $record->awb_code ? esc_html($record->awb_code) : '—'; ?>
                    </div>
                </div>
                <div>
                    <div class="thaaniyamhub-field-label"><?php esc_html_e('Courier', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </div>
                    <div class="thaaniyamhub-field-value">
                        <?php echo $record->courier_name ? esc_html($record->courier_name) : '—'; ?>
                    </div>
                </div>
                <div>
                    <div class="thaaniyamhub-field-label">
                        <?php esc_html_e('Pickup Location', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </div>
                    <div class="thaaniyamhub-field-value"><?php echo esc_html($record->pickup_location_nickname); ?></div>
                </div>
                <div>
                    <div class="thaaniyamhub-field-label"><?php esc_html_e('Status', 'thaaniyamhub-multi-vendor-orders'); ?></div>
                    <div class="thaaniyamhub-field-value">
                        <span class="thaaniyamhub-status-badge <?php echo esc_attr($status_class); ?>">
                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $record->fulfillment_status))); ?>
                        </span>
                    </div>
                </div>
                <div>
                    <div class="thaaniyamhub-field-label"><?php esc_html_e('Weight', 'thaaniyamhub-multi-vendor-orders'); ?></div>
                    <div class="thaaniyamhub-field-value">
                        <?php 
                        $disp_weight = $order->get_meta('_shiprocket_weight_override');
                        echo $disp_weight ? esc_html($disp_weight) . ' kg' : '—'; 
                        ?>
                    </div>
                </div>
                <div>
                    <div class="thaaniyamhub-field-label"><?php esc_html_e('Dimensions', 'thaaniyamhub-multi-vendor-orders'); ?></div>
                    <div class="thaaniyamhub-field-value">
                        <?php 
                        $l = $order->get_meta('_shiprocket_length_override');
                        $w = $order->get_meta('_shiprocket_width_override');
                        $h = $order->get_meta('_shiprocket_height_override');
                        echo ($l && $w && $h) ? esc_html(sprintf('%s x %s x %s cm', $l, $w, $h)) : '—';
                        ?>
                    </div>
                </div>
                <?php if ($record->awb_code): ?>
                <div>
                    <div class="thaaniyamhub-field-label"><?php esc_html_e('Actual Shipping Cost', 'thaaniyamhub-multi-vendor-orders'); ?></div>
                    <div class="thaaniyamhub-field-value">
                        <?php 
                        $actual_cost = self::get_actual_shipping_cost($order, $record);
                        echo $actual_cost ? wp_kses_post(wc_price($actual_cost)) : '—'; 
                        ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php 
                $fe_pickup_date = !empty($record->pickup_scheduled_date) ? $record->pickup_scheduled_date : $order->get_meta('_shiprocket_pickup_scheduled_date');
                $fe_pickup_token = !empty($record->pickup_token_number) ? $record->pickup_token_number : $order->get_meta('_shiprocket_pickup_token_number');
                if ($fe_pickup_date): ?>
                <div>
                    <div class="thaaniyamhub-field-label"><?php esc_html_e('Pickup Scheduled', 'thaaniyamhub-multi-vendor-orders'); ?></div>
                    <div class="thaaniyamhub-field-value">
                        <span style="color:#166534;font-weight:600;">📅 <?php echo esc_html($fe_pickup_date); ?></span>
                        <?php if ($fe_pickup_token): ?>
                            <br><small style="color:#666;font-size:11px;font-weight:normal;"><?php echo sprintf(esc_html__('Token: %s', 'thaaniyamhub-multi-vendor-orders'), esc_html($fe_pickup_token)); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php 
                $fe_manifest_url_disp = !empty($record->manifest_url) ? $record->manifest_url : $order->get_meta('_shiprocket_manifest_url');
                if ($fe_manifest_url_disp): ?>
                <div>
                    <div class="thaaniyamhub-field-label"><?php esc_html_e('Manifest Document', 'thaaniyamhub-multi-vendor-orders'); ?></div>
                    <div class="thaaniyamhub-field-value">
                        <a href="<?php echo esc_url($fe_manifest_url_disp); ?>" target="_blank" style="display:inline-flex;align-items:center;gap:4px;color:#0369a1;text-decoration:none;font-size:12px;font-weight:600;">
                            📄 <?php esc_html_e('View Manifest PDF', 'thaaniyamhub-multi-vendor-orders'); ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!$record->awb_code):
                $default_pickup = get_user_meta($vendor_id, '_shiprocket_pickup_id', true);
                if (!$default_pickup) {
                    $default_pickup = $record->pickup_location_nickname ?: ThaaniyamHub_Dispatch::resolve_pickup_nickname($vendor_id);
                }

                $api = new ThaaniyamHub_Shiprocket_API();
                $pickup_res = $api->get_pickup_addresses();
                $pickup_addresses = [];
                if (!is_wp_error($pickup_res) && !empty($pickup_res['data']['shipping_address'])) {
                    foreach ($pickup_res['data']['shipping_address'] as $addr) {
                        if (!empty($addr['pickup_location']) && !empty($addr['status'])) {
                            $pickup_addresses[] = $addr;
                        }
                    }
                }

                // Metrics inputs - do not autofill, only use manually saved overrides
                $weight = $order->get_meta('_shiprocket_weight_override');
                $length = $order->get_meta('_shiprocket_length_override');
                $breadth = $order->get_meta('_shiprocket_width_override');
                $height = $order->get_meta('_shiprocket_height_override');
                ?>
                <div class="thaaniyamhub-package-options-wrapper"
                    style="margin-top:15px; border-top:1px solid #eee; padding-top:10px; margin-bottom:15px;">
                    <?php if ('cancelled' === $record->fulfillment_status): ?>
                        <strong style="display:block;margin-bottom:8px;font-size:12px;color:#b91c1c;">&#x21BB;
                            <?php esc_html_e('Resend / Re-push Order:', 'thaaniyamhub-multi-vendor-orders'); ?></strong>
                    <?php else: ?>
                        <strong style="display:block;margin-bottom:8px;font-size:12px;color:#1a1a1a;">&#x1F69A;
                            <?php esc_html_e('Package & Shipment Details:', 'thaaniyamhub-multi-vendor-orders'); ?></strong>
                    <?php endif; ?>

                    <div class="thaaniyamhub-pickup-selector-wrapper"
                        style="margin-bottom:12px; border-bottom:1px solid #eee; padding-bottom:10px;">
                        <label for="ag_sf_fe_pickup_location"
                            style="display:block;margin-bottom:4px;font-weight:600;"><?php esc_html_e('Pickup Location:', 'thaaniyamhub-multi-vendor-orders'); ?></label>
                        <?php
                        $assigned_addr_details = null;
                        if (!empty($pickup_addresses)) {
                            foreach ($pickup_addresses as $addr) {
                                if (isset($addr['pickup_location'])) {
                                    $loc_clean = strtolower(preg_replace('/[^a-z0-9]/', '', trim($addr['pickup_location'])));
                                    $def_clean = strtolower(preg_replace('/[^a-z0-9]/', '', trim($default_pickup)));
                                    if ($loc_clean === $def_clean) {
                                        $assigned_addr_details = $addr;
                                        break;
                                    }
                                }
                            }
                        }
                        if ($assigned_addr_details) {
                            $addr_line = trim(($assigned_addr_details['address'] ?? $assigned_addr_details['address_line_1'] ?? '') . ' ' . ($assigned_addr_details['address_2'] ?? $assigned_addr_details['address_line_2'] ?? ''));
                            $city_state_pin = trim(($assigned_addr_details['city'] ?? '') . ', ' . ($assigned_addr_details['state'] ?? '') . ' - ' . ($assigned_addr_details['pin_code'] ?? $assigned_addr_details['pincode'] ?? $assigned_addr_details['pin'] ?? ''));
                            $contact_name = $assigned_addr_details['name'] ?? '';
                            $contact_phone = $assigned_addr_details['phone'] ?? '';

                            echo '<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:4px;padding:10px;font-size:12px;line-height:1.5;color:#333;">';
                            echo '<strong>' . esc_html($default_pickup) . '</strong><br>';
                            if ($contact_name || $contact_phone) {
                                echo esc_html($contact_name) . ' (' . esc_html($contact_phone) . ')<br>';
                            }
                            echo esc_html($addr_line) . ', ' . esc_html($city_state_pin);
                            echo '</div>';
                        } else {
                            echo '<span class="thaaniyamhub-pickup-location-assigned" style="font-weight:bold;display:inline-block;">' . esc_html($default_pickup) . '</span>';
                            echo '<br><span style="color:#b91c1c;font-size:11px;font-weight:600;">⚠️ Address not found in Shiprocket account</span>';
                        }
                        ?>
                        <input type="hidden" id="ag_sf_fe_pickup_location" name="pickup_location"
                            value="<?php echo esc_attr($default_pickup); ?>"
                            data-addresses="<?php echo esc_attr(wp_json_encode($pickup_addresses)); ?>">
                    </div>

                    <?php
                    $dimension_presets = class_exists('ThaaniyamHub_Dimension_Manager') ? ThaaniyamHub_Dimension_Manager::get_vendor_presets((int) $vendor_id) : [];
                    ?>
                    <div class="thaaniyamhub-dims-wrapper" style="margin-bottom:12px;border-bottom:1px solid #eee;padding-bottom:10px;">
                        <label
                            style="display:block;margin-bottom:5px;font-weight:600;"><?php esc_html_e('Package Details:', 'thaaniyamhub-multi-vendor-orders'); ?></label>
                        <?php if (!empty($dimension_presets)): ?>
                            <div style="margin-bottom:8px;">
                                <label for="ag_sf_fe_package_preset" style="font-size:11px;font-weight:600;color:#555;display:block;margin-bottom:3px;"><?php esc_html_e('Select Saved Dimension (Optional):', 'thaaniyamhub-multi-vendor-orders'); ?></label>
                                <select id="ag_sf_fe_package_preset" style="width:100%;font-size:12px;padding:4px 6px;margin-bottom:4px;border-radius:4px;border:1px solid #cbd5e1;">
                                    <option value=""><?php esc_html_e('-- Choose Preset or Enter Custom Below --', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                                    <?php foreach ($dimension_presets as $dp): 
                                        $dp_label = sprintf('%s (%s kg | %s × %s × %s cm)', $dp['name'], $dp['weight'], $dp['length'], $dp['width'], $dp['height']);
                                    ?>
                                        <option value="<?php echo esc_attr($dp['id']); ?>" data-weight="<?php echo esc_attr($dp['weight']); ?>" data-length="<?php echo esc_attr($dp['length']); ?>" data-width="<?php echo esc_attr($dp['width']); ?>" data-height="<?php echo esc_attr($dp['height']); ?>"><?php echo esc_html($dp_label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:8px;">
                            <div><label style="font-size:10px;display:block;">Weight (kg) <span
                                        style="color:#b91c1c;">*</span></label><input type="number" id="ag_sf_fe_weight" step="0.001"
                                    min="0.01" value="<?php echo esc_attr($weight); ?>" style="width:100%;"></div>
                            <div><label style="font-size:10px;display:block;">Length (cm) <span
                                        style="color:#b91c1c;">*</span></label><input type="number" id="ag_sf_fe_length" step="0.1"
                                    min="1" value="<?php echo esc_attr($length); ?>" style="width:100%;background-color:#f3f4f6;cursor:not-allowed;" readonly></div>
                            <div><label style="font-size:10px;display:block;">Width (cm) <span
                                        style="color:#b91c1c;">*</span></label><input type="number" id="ag_sf_fe_width" step="0.1"
                                    min="1" value="<?php echo esc_attr($breadth); ?>" style="width:100%;background-color:#f3f4f6;cursor:not-allowed;" readonly></div>
                            <div><label style="font-size:10px;display:block;">Height (cm) <span
                                        style="color:#b91c1c;">*</span></label><input type="number" id="ag_sf_fe_height" step="0.1"
                                    min="1" value="<?php echo esc_attr($height); ?>" style="width:100%;background-color:#f3f4f6;cursor:not-allowed;" readonly></div>
                        </div>
                        <button type="button" id="ag-sf-fe-btn-fetch-couriers" class="wcfm_submit_button"
                            style="width:100%;margin-bottom:6px;"
                            data-order-id="<?php echo esc_attr($order_id); ?>"><?php esc_html_e('Fetch Available Couriers', 'thaaniyamhub-multi-vendor-orders'); ?></button>
                        <div id="ag-sf-fe-pre-push-couriers"
                            style="display:none;margin-bottom:8px;border:1px solid #ddd;padding:8px;background:#f9f9f9;border-radius:4px;max-height:150px;overflow-y:auto;">
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="thaaniyamhub-actions">
                <?php if (!$record->awb_code && 'cancelled' !== $record->fulfillment_status): ?>
                    <button type="button" id="ag-sf-fe-btn-awb" class="wcfm_submit_button"
                        data-order-id="<?php echo esc_attr($order_id); ?>">
                        <?php esc_html_e('Generate AWB', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </button>
                <?php endif; ?>

                <?php 
                $fe_pickup_date = !empty($record->pickup_scheduled_date) ? $record->pickup_scheduled_date : $order->get_meta('_shiprocket_pickup_scheduled_date');
                $fe_pickup_token = !empty($record->pickup_token_number) ? $record->pickup_token_number : $order->get_meta('_shiprocket_pickup_token_number');
                ?>

                <?php if ($record->awb_code && $fe_pickup_date): ?>
                    <div style="width:100%; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:10px 12px; margin-bottom:10px; display:flex; align-items:center; gap:10px;">
                        <span style="font-size:22px; line-height:1;">📅</span>
                        <div style="flex:1;">
                            <div style="font-size:10px; font-weight:700; color:#166534; text-transform:uppercase; letter-spacing:0.5px;">
                                <?php esc_html_e('Scheduled Pickup Date', 'thaaniyamhub-multi-vendor-orders'); ?>
                            </div>
                            <div style="font-size:14px; font-weight:700; color:#14532d; margin-top:1px;">
                                <?php echo esc_html($fe_pickup_date); ?>
                            </div>
                            <?php if ($fe_pickup_token): ?>
                                <div style="font-size:11px; color:#15803d; font-weight:600; margin-top:2px;">
                                    <?php echo sprintf(esc_html__('Token: %s', 'thaaniyamhub-multi-vendor-orders'), esc_html($fe_pickup_token)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($record->awb_code && in_array($record->fulfillment_status, ['assigned', 'manifested', 'pickup_scheduled'], true)): ?>
                    <div class="thaaniyamhub-reassign-wrapper"
                        style="width:100%; border-top:1px solid #eee; padding-top:10px; margin-top:10px; display:none;"
                        id="ag-sf-fe-reassign-section">
                        <label style="display:block;margin-bottom:4px;font-weight:600;">
                            <?php esc_html_e('Select New Courier:', 'thaaniyamhub-multi-vendor-orders'); ?>
                        </label>
                        <div id="ag-sf-fe-courier-reassign-loader" data-order-id="<?php echo esc_attr($order_id); ?>">
                            <span style="color:#888;font-size:12px;">&#x231b; Loading couriers…</span>
                        </div>
                        <button type="button" id="ag-sf-fe-btn-reassign-submit" class="wcfm_submit_button" style="margin-top:6px;"
                            data-order-id="<?php echo esc_attr($order_id); ?>">
                            <?php esc_html_e('Assign Selected Courier', 'thaaniyamhub-multi-vendor-orders'); ?>
                        </button>
                    </div>
                    <button type="button" id="ag-sf-fe-btn-reassign" class="wcfm_submit_button"
                        data-order-id="<?php echo esc_attr($order_id); ?>">
                        <?php esc_html_e('Change Courier Partner', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </button>
                <?php endif; ?>

                <?php
                $fe_label_url = !empty($record->shipping_label_url) ? $record->shipping_label_url : ($order ? $order->get_meta('_shiprocket_label_url') : '');
                $fe_manifest_url = !empty($record->manifest_url) ? $record->manifest_url : ($order ? $order->get_meta('_shiprocket_manifest_url') : '');
                $fe_invoice_url = !empty($record->commercial_invoice_url) ? $record->commercial_invoice_url : ($order ? $order->get_meta('_shiprocket_invoice_url') : '');
                ?>

                <!-- 1. Shipping Label Button: View if generated, Print if not yet generated -->
                <?php if ($fe_label_url): ?>
                    <a href="<?php echo esc_url($fe_label_url); ?>" target="_blank" class="wcfm_submit_button"
                        style="text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
                        📄 <?php esc_html_e('View Shipping Label', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </a>
                <?php elseif ($record->awb_code): ?>
                    <button type="button" id="ag-sf-fe-btn-label" class="wcfm_submit_button"
                        data-order-id="<?php echo esc_attr($order_id); ?>">
                        <?php esc_html_e('Print Label', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </button>
                <?php endif; ?>

                <!-- 2. Manifest Button: View if generated, Download if not yet generated -->
                <?php if ($fe_manifest_url): ?>
                    <a href="<?php echo esc_url($fe_manifest_url); ?>" target="_blank" class="wcfm_submit_button"
                        style="text-decoration:none; display:inline-flex; align-items:center; gap:4px; color:#0369a1; border-color:#0369a1;">
                        📄 <?php esc_html_e('View Manifest', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </a>
                <?php elseif ($record->awb_code): ?>
                    <button type="button" id="ag-sf-fe-btn-manifest" class="wcfm_submit_button"
                        data-order-id="<?php echo esc_attr($order_id); ?>">
                        <?php esc_html_e('Download Manifest', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </button>
                <?php endif; ?>

                <!-- 3. Invoice Button: View if generated, Print if not yet generated -->
                <?php if ($fe_invoice_url): ?>
                    <a href="<?php echo esc_url($fe_invoice_url); ?>" target="_blank" class="wcfm_submit_button"
                        style="text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
                        📄 <?php esc_html_e('View Invoice', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </a>
                <?php elseif (!empty($record->shiprocket_order_id)): ?>
                    <button type="button" id="ag-sf-fe-btn-invoice" class="wcfm_submit_button"
                        data-order-id="<?php echo esc_attr($order_id); ?>">
                        <?php esc_html_e('Print Invoice', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </button>
                <?php endif; ?>

                <?php if (!in_array($record->fulfillment_status, ['cancelled', 'delivered', 'return_initiated', 'return_picked_up', 'return_ofd', 'returned', 'return_cancelled', 'rto'], true)): ?>
                    <button type="button" id="ag-sf-fe-btn-cancel" class="wcfm_submit_button"
                        style="background:#b91c1c;border-color:#b91c1c;" data-order-id="<?php echo esc_attr($order_id); ?>">
                        <?php esc_html_e('Cancel Shipment', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </button>
                <?php endif; ?>

                <?php if ('delivered' === $record->fulfillment_status): ?>
                    <button type="button" id="ag-sf-fe-btn-return" class="wcfm_submit_button" style="background:#b91c1c; border-color:#b91c1c; width:100%;"
                        data-order-id="<?php echo esc_attr($order_id); ?>">
                        🔄 <?php esc_html_e('Return Order', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </button>
                <?php endif; ?>

                <?php if ('cancelled' === $record->fulfillment_status): ?>
                    <button type="button" id="ag-sf-fe-btn-push" class="wcfm_submit_button"
                        data-order-id="<?php echo esc_attr($order_id); ?>">
                        <?php esc_html_e('Resend to Shiprocket', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </button>
                <?php endif; ?>
            </div>
        <?php
        }
        echo '</div>'; // .ag-sf-card-body
        echo '</div>'; // .ag-sf-card
        echo '</div>'; // .wcfm-content
        echo '</div>'; // .wcfm-container
    }

    // =========================================================================
    // AJAX CALLBACKS
    // =========================================================================

    public static function ajax_push_order()
    {
        check_ajax_referer('thaaniyamhub_sf_nonce', '_nonce');

        $order_id = (int) ($_POST['order_id'] ?? 0);
        if (!self::current_user_can_manage_order($order_id)) {
            wp_send_json_error(__('Insufficient permissions.', 'thaaniyamhub-multi-vendor-orders'), 403);
        }

        thaaniyamhub_log("AJAX Action (Push Order): Initiated for Order #{$order_id} by user #" . get_current_user_id());

        $wc_order = wc_get_order($order_id);
        if (!$wc_order) {
            thaaniyamhub_log("AJAX Action (Push Order): Failed - Order #{$order_id} not found.", 'error');
            wp_send_json_error(__('Order not found.', 'thaaniyamhub-multi-vendor-orders'));
        }

        $pickup_location = sanitize_text_field($_POST['pickup_location'] ?? '');
        if ($pickup_location) {
            update_post_meta($order_id, '_shiprocket_pickup_override', $pickup_location);
        }

        $weight = (float) ($_POST['weight'] ?? 0);
        if ($weight <= 0) {
            wp_send_json_error(__('Weight must be greater than 0 kg for Shiprocket fulfillment.', 'thaaniyamhub-multi-vendor-orders'));
        }
        $wc_order->update_meta_data('_shiprocket_weight_override', $weight);
        $length = (float) ($_POST['length'] ?? 0);
        if ($length > 0) {
            $wc_order->update_meta_data('_shiprocket_length_override', $length);
        }
        $width = (float) ($_POST['width'] ?? 0);
        if ($width > 0) {
            $wc_order->update_meta_data('_shiprocket_width_override', $width);
        }
        $height = (float) ($_POST['height'] ?? 0);
        if ($height > 0) {
            $wc_order->update_meta_data('_shiprocket_height_override', $height);
        }

        $courier_id = (int) ($_POST['courier_id'] ?? 0);
        if ($courier_id > 0) {
            $wc_order->update_meta_data('_shiprocket_selected_courier_id', $courier_id);
        }
        $wc_order->save();

        thaaniyamhub_log("AJAX Action (Push Order): Saved overrides for Order #{$order_id} (Pickup: '{$pickup_location}', Weight: {$weight}, Length: {$length}, Width: {$width}, Height: {$height}, Courier ID: {$courier_id})");

        ThaaniyamHub_Dispatch::push_to_shiprocket($order_id, $pickup_location);

        $record = ThaaniyamHub_Shiprocket_API::get_fulfillment($order_id);

        if ($record && !empty($record->shiprocket_order_id)) {
            thaaniyamhub_log("AJAX Action (Push Order): Succeeded for Order #{$order_id}");
            wp_send_json_success();
        } else {
            $reason = $wc_order->get_meta('_shiprocket_sync_fail_reason') ?: __('Unknown dispatch error.', 'thaaniyamhub-multi-vendor-orders');
            if ('missing_package_metrics' === $reason) {
                $reason = __('Weight and dimensions are required.', 'thaaniyamhub-multi-vendor-orders');
            }
            thaaniyamhub_log("AJAX Action (Push Order): Failed for Order #{$order_id}. Reason: {$reason}", 'error');
            wp_send_json_error($reason);
        }
    }

    public static function ajax_initiate_return()
    {
        check_ajax_referer('thaaniyamhub_sf_nonce', '_nonce');

        $order_id = (int) ($_POST['order_id'] ?? 0);
        if (!self::current_user_can_manage_order($order_id)) {
            wp_send_json_error(__('Insufficient permissions.', 'thaaniyamhub-multi-vendor-orders'), 403);
        }

        thaaniyamhub_log("AJAX Action (Initiate Return): Initiated for Order #{$order_id} by user #" . get_current_user_id());

        $wc_order = wc_get_order($order_id);
        if (!$wc_order) {
            thaaniyamhub_log("AJAX Action (Initiate Return): Failed - Order #{$order_id} not found.", 'error');
            wp_send_json_error(__('Order not found.', 'thaaniyamhub-multi-vendor-orders'));
        }

        $record = ThaaniyamHub_Shiprocket_API::get_fulfillment($order_id);
        if (!$record || 'delivered' !== $record->fulfillment_status) {
            thaaniyamhub_log("AJAX Action (Initiate Return): Failed - Order #{$order_id} is not in delivered status. Current status: " . ($record ? $record->fulfillment_status : 'none'), 'error');
            wp_send_json_error(__('Order must be delivered before initiating a return.', 'thaaniyamhub-multi-vendor-orders'));
        }

        // Get pickup/merchant warehouse nickname
        $vendor_id = ThaaniyamHub_Dispatch::get_order_vendor_id($wc_order);
        $pickup_location = get_user_meta($vendor_id, '_shiprocket_pickup_id', true);
        if (!$pickup_location) {
            $pickup_location = ThaaniyamHub_Dispatch::resolve_pickup_nickname($vendor_id);
        }

        if (!$pickup_location) {
            wp_send_json_error(__('Vendor does not have a registered pickup nickname.', 'thaaniyamhub-multi-vendor-orders'));
        }

        // Fetch registered pickup addresses in Shiprocket to get vendor's warehouse full address details
        $api = new ThaaniyamHub_Shiprocket_API();
        $pickup_res = $api->get_pickup_addresses();
        $vendor_addr = null;

        if (!is_wp_error($pickup_res) && !empty($pickup_res['data']['shipping_address'])) {
            foreach ($pickup_res['data']['shipping_address'] as $addr) {
                if (isset($addr['pickup_location']) && strtolower(trim($addr['pickup_location'])) === strtolower(trim($pickup_location))) {
                    $vendor_addr = $addr;
                    break;
                }
            }
        }

        if (!$vendor_addr) {
            wp_send_json_error(sprintf(__('Vendor pickup nickname "%s" not found in Shiprocket account registered addresses.', 'thaaniyamhub-multi-vendor-orders'), $pickup_location));
        }

        // Customer Details (to be used as return pickup source)
        $customer_first_name = $wc_order->get_shipping_first_name() ?: $wc_order->get_billing_first_name();
        $customer_last_name = $wc_order->get_shipping_last_name() ?: $wc_order->get_billing_last_name();
        $customer_address_1 = $wc_order->get_shipping_address_1() ?: $wc_order->get_billing_address_1();
        $customer_address_2 = $wc_order->get_shipping_address_2() ?: $wc_order->get_billing_address_2();
        $customer_city = $wc_order->get_shipping_city() ?: $wc_order->get_billing_city();
        $customer_state = $wc_order->get_shipping_state() ?: $wc_order->get_billing_state();
        $customer_pincode = $wc_order->get_shipping_postcode() ?: $wc_order->get_billing_postcode();
        $customer_phone = $wc_order->get_shipping_phone() ?: $wc_order->get_billing_phone();
        $customer_email = $wc_order->get_billing_email();

        // Vendor details (destination)
        $vendor_name = $vendor_addr['name'] ?? $pickup_location;
        $vendor_address_1 = $vendor_addr['address'] ?? $vendor_addr['address_line_1'] ?? '';
        $vendor_address_2 = $vendor_addr['address_2'] ?? $vendor_addr['address_line_2'] ?? '';
        $vendor_city = $vendor_addr['city'] ?? '';
        $vendor_state = $vendor_addr['state'] ?? '';
        $vendor_pincode = $vendor_addr['pin_code'] ?? $vendor_addr['pincode'] ?? $vendor_addr['pin'] ?? '';
        $vendor_phone = $vendor_addr['phone'] ?? '';

        // Order items
        $order_items = [];
        $declared_val = 0.0;
        foreach ($wc_order->get_items() as $item) {
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
            wp_send_json_error(__('No items to return.', 'thaaniyamhub-multi-vendor-orders'));
        }

        // Metrics
        $weight = $wc_order->get_meta('_shiprocket_weight_override');
        $length = $wc_order->get_meta('_shiprocket_length_override');
        $breadth = $wc_order->get_meta('_shiprocket_width_override');
        $height = $wc_order->get_meta('_shiprocket_height_override');

        if (empty($weight) || empty($length) || empty($breadth) || empty($height)) {
            // Recalculate metrics as fallback
            $metrics = ThaaniyamHub_Dispatch::calculate_package_metrics($wc_order);
            $weight = $weight ?: ($metrics['weight'] ?? 0.5);
            $length = $length ?: ($metrics['length'] ?? 10.0);
            $breadth = $breadth ?: ($metrics['width'] ?? 10.0);
            $height = $height ?: ($metrics['height'] ?? 10.0);
        }

        // Generate unique return order ID using logs to count attempts
        global $wpdb;
        $attempt_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}thaaniyamhub_shiprocket_api_logs 
          WHERE sub_order_id = %d AND endpoint_requested LIKE '%%orders/create/return%%'",
            $order_id
        ));
        $unique_return_order_id = $order_id . '-RET' . ($attempt_count > 0 ? '-' . $attempt_count : '');

        $payload = [
            'order_id' => $unique_return_order_id,
            'order_date' => current_time('Y-m-d'),
            'channel_id' => get_option('thaaniyamhub_shiprocket_channel_id', '10832781'),
            'pickup_customer_name' => $customer_first_name,
            'pickup_last_name' => $customer_last_name,
            'pickup_address' => $customer_address_1,
            'pickup_address_2' => $customer_address_2,
            'pickup_city' => $customer_city,
            'pickup_state' => $customer_state,
            'pickup_country' => 'India',
            'pickup_pincode' => (int) $customer_pincode,
            'pickup_phone' => $customer_phone,
            'pickup_email' => $customer_email,
            'shipping_customer_name' => $vendor_name,
            'shipping_address' => $vendor_address_1,
            'shipping_address_2' => $vendor_address_2,
            'shipping_city' => $vendor_city,
            'shipping_state' => $vendor_state,
            'shipping_country' => 'India',
            'shipping_pincode' => (int) $vendor_pincode,
            'shipping_phone' => $vendor_phone,
            'order_items' => $order_items,
            'payment_method' => 'Prepaid',
            'sub_total' => round($declared_val, 2),
            'weight' => (float) $weight,
            'length' => (float) $length,
            'breadth' => (float) $breadth,
            'height' => (float) $height,
        ];

        thaaniyamhub_log("AJAX Action (Initiate Return): Creating return order in Shiprocket with payload: " . wp_json_encode($payload));
        $result = $api->create_return_order($payload, $order_id);

        if (is_wp_error($result)) {
            $error_msg = $result->get_error_message();
            thaaniyamhub_log("AJAX Action (Initiate Return): Shiprocket API failed for Order #{$order_id}: {$error_msg}", 'error');
            wp_send_json_error($error_msg);
        }

        thaaniyamhub_log("AJAX Action (Initiate Return): API success. Response: " . print_r($result, true));

        $sr_return_order_id = (string) ($result['order_id'] ?? '');
        $sr_return_shipment_id = (string) ($result['shipment_id'] ?? '');

        if (!$sr_return_order_id) {
            thaaniyamhub_log("AJAX Action (Initiate Return): Shiprocket returned no order_id in return response!", 'error');
            wp_send_json_error(__('Shiprocket returned no order_id in return response.', 'thaaniyamhub-multi-vendor-orders'));
        }

        // Backup original outbound shipment details in order metadata
        $wc_order->update_meta_data('_shiprocket_original_order_id', $record->shiprocket_order_id);
        $wc_order->update_meta_data('_shiprocket_original_shipment_id', $record->shiprocket_shipment_id);
        $wc_order->update_meta_data('_shiprocket_original_awb_code', $record->awb_code);
        $wc_order->update_meta_data('_shiprocket_original_courier_name', $record->courier_name);

        // Store return details in order metadata
        $wc_order->update_meta_data('_shiprocket_return_order_id', $sr_return_order_id);
        $wc_order->update_meta_data('_shiprocket_return_shipment_id', $sr_return_shipment_id);
        $wc_order->save();

        // Update custom database table with the return order and shipment details, clearing old outbound delivery tracking details
        $wpdb->update(
            $wpdb->prefix . 'thaaniyamhub_shiprocket_fulfillment',
            [
                'shiprocket_order_id' => $sr_return_order_id,
                'shiprocket_shipment_id' => $sr_return_shipment_id,
                'awb_code' => null,
                'courier_name' => null,
                'shipping_label_url' => null,
                'commercial_invoice_url' => null,
                'fulfillment_status' => 'return_initiated',
            ],
            ['sub_order_id' => $order_id]
        );

        $wc_order->add_order_note(
            sprintf(
                __('🔄 Shiprocket return order initiated. Return Order ID: %s | Shipment ID: %s. Customer pickup address: %s.', 'thaaniyamhub-multi-vendor-orders'),
                $sr_return_order_id,
                $sr_return_shipment_id,
                $customer_address_1 . ', ' . $customer_city . ' - ' . $customer_pincode
            )
        );

        wp_send_json_success();
    }

    public static function get_actual_shipping_cost($order, $record)
    {
        if (!$order || !$record || !$record->awb_code) {
            return 0.0;
        }

        $cost = $order->get_meta('_shiprocket_actual_shipping_cost');
        if ('' !== $cost && false !== $cost) {
            return (float) $cost;
        }

        $selected_courier_id = (int) $order->get_meta('_shiprocket_selected_courier_id');
        $api = new ThaaniyamHub_Shiprocket_API();
        $svc = $api->check_serviceability(['order_id' => (int) $record->shiprocket_order_id]);
        $shipping_cost = 0.0;
        if (!is_wp_error($svc) && !empty($svc['data']['available_courier_companies'])) {
            foreach ($svc['data']['available_courier_companies'] as $c) {
                $c_id = (int) ($c['courier_company_id'] ?? 0);
                $c_name = $c['courier_name'] ?? '';
                if (($selected_courier_id && $c_id === $selected_courier_id) || 
                    (!$selected_courier_id && $record->courier_name && strcasecmp($c_name, $record->courier_name) === 0)) {
                    $shipping_cost = isset($c['rate']) ? (float) $c['rate'] : (isset($c['freight_charge']) ? (float) $c['freight_charge'] : 0.0);
                    break;
                }
            }
            if (!$shipping_cost && count($svc['data']['available_courier_companies']) === 1) {
                $c = reset($svc['data']['available_courier_companies']);
                $shipping_cost = isset($c['rate']) ? (float) $c['rate'] : (isset($c['freight_charge']) ? (float) $c['freight_charge'] : 0.0);
            }
        }

        if ($shipping_cost > 0) {
            $order->update_meta_data('_shiprocket_actual_shipping_cost', $shipping_cost);
            $order->save();
        }

        return $shipping_cost;
    }

    public static function ajax_generate_awb()
    {
        check_ajax_referer('thaaniyamhub_sf_nonce', '_nonce');

        $order_id = (int) ($_POST['order_id'] ?? 0);
        if (!self::current_user_can_manage_order($order_id)) {
            wp_send_json_error(__('Insufficient permissions.', 'thaaniyamhub-multi-vendor-orders'), 403);
        }

        thaaniyamhub_log("AJAX Action (Generate AWB): Initiated for Order #{$order_id} by user #" . get_current_user_id());

        $record = ThaaniyamHub_Shiprocket_API::get_fulfillment($order_id);
        if (!$record || !$record->shiprocket_shipment_id) {
            thaaniyamhub_log("AJAX Action (Generate AWB): Failed for Order #{$order_id} - Shipment not found in local fulfillment table.", 'error');
            wp_send_json_error(__('Shipment not found. Dispatch the order first.', 'thaaniyamhub-multi-vendor-orders'));
        }

        $wc_order = wc_get_order($order_id);
        $pickup_location = sanitize_text_field($_POST['pickup_location'] ?? '');
        $weight = (float) ($_POST['weight'] ?? 0);
        $length = (float) ($_POST['length'] ?? 0);
        $width = (float) ($_POST['width'] ?? 0);
        $height = (float) ($_POST['height'] ?? 0);

        $has_changes = false;
        if ($pickup_location && $pickup_location !== $record->pickup_location_nickname) {
            $has_changes = true;
        }
        $old_weight = $wc_order ? (float) $wc_order->get_meta('_shiprocket_weight_override') : 0;
        $old_length = $wc_order ? (float) $wc_order->get_meta('_shiprocket_length_override') : 0;
        $old_width = $wc_order ? (float) $wc_order->get_meta('_shiprocket_width_override') : 0;
        $old_height = $wc_order ? (float) $wc_order->get_meta('_shiprocket_height_override') : 0;

        if ($weight > 0 && abs($weight - $old_weight) > 0.0001) {
            $has_changes = true;
        }
        if ($length > 0 && abs($length - $old_length) > 0.0001) {
            $has_changes = true;
        }
        if ($width > 0 && abs($width - $old_width) > 0.0001) {
            $has_changes = true;
        }
        if ($height > 0 && abs($height - $old_height) > 0.0001) {
            $has_changes = true;
        }

        $api = new ThaaniyamHub_Shiprocket_API();

        if ($has_changes && $wc_order) {
            if ($pickup_location) {
                update_post_meta($order_id, '_shiprocket_pickup_override', $pickup_location);
            }
            if ($weight > 0) {
                $wc_order->update_meta_data('_shiprocket_weight_override', $weight);
            }
            if ($length > 0) {
                $wc_order->update_meta_data('_shiprocket_length_override', $length);
            }
            if ($width > 0) {
                $wc_order->update_meta_data('_shiprocket_width_override', $width);
            }
            if ($height > 0) {
                $wc_order->update_meta_data('_shiprocket_height_override', $height);
            }
            $wc_order->save();

            thaaniyamhub_log("AJAX Action (Generate AWB): Saved metrics overrides/pickup location update for Order #{$order_id} (Pickup: '{$pickup_location}', Weight: {$weight}, Length: {$length}, Width: {$width}, Height: {$height})");

            if ($pickup_location && $pickup_location !== $record->pickup_location_nickname) {
                $patch_res = $api->update_order_pickup_location([(int) $record->shiprocket_order_id], $pickup_location, $order_id);
                if (is_wp_error($patch_res)) {
                    thaaniyamhub_log("AJAX Action (Generate AWB): Failed to update pickup location nickname '{$pickup_location}' for Order #{$order_id}: " . $patch_res->get_error_message(), 'error');
                    wp_send_json_error(__('Failed to update pickup location in Shiprocket: ', 'thaaniyamhub-multi-vendor-orders') . $patch_res->get_error_message());
                }
            }

            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'thaaniyamhub_shiprocket_fulfillment',
                ['pickup_location_nickname' => $pickup_location ?: $record->pickup_location_nickname],
                ['sub_order_id' => $order_id]
            );

            $record = ThaaniyamHub_Shiprocket_API::get_fulfillment($order_id);
        }

        $courier_id = (int) ($_POST['courier_id'] ?? 0);
        if ($courier_id > 0 && $wc_order) {
            $wc_order->update_meta_data('_shiprocket_selected_courier_id', $courier_id);
            $wc_order->save();
        } else {
            $courier_id = $wc_order ? (int) $wc_order->get_meta('_shiprocket_selected_courier_id') : 0;
        }

        thaaniyamhub_log("AJAX Action (Generate AWB): Requesting AWB assignment from Shiprocket for shipment #{$record->shiprocket_shipment_id} (Order #{$order_id}) with Courier ID {$courier_id}");

        $result = $api->assign_awb([
            'shipment_id' => [(int) $record->shiprocket_shipment_id],
            'courier_id' => $courier_id ?: null,
        ], $order_id);

        if (is_wp_error($result)) {
            thaaniyamhub_log("AJAX Action (Generate AWB): Shiprocket assign AWB API failed for Order #{$order_id}: " . $result->get_error_message(), 'error');
            wp_send_json_error($result->get_error_message());
        }

        $awb = $result['response']['data']['awb_code'] ?? ($result['awb_code'] ?? '');
        $sr_courier_id = $result['response']['data']['courier_company_id'] ?? 0;

        $courier_name = '';
        $shipping_cost = 0.0;
        if (isset($result['response']['data']['net_total'])) {
            $shipping_cost = (float) $result['response']['data']['net_total'];
        } elseif (isset($result['response']['data']['freight_charge'])) {
            $shipping_cost = (float) $result['response']['data']['freight_charge'];
        }

        if ($sr_courier_id && $record->shiprocket_order_id) {
            $svc = $api->check_serviceability(['order_id' => (int) $record->shiprocket_order_id]);
            if (!is_wp_error($svc)) {
                foreach ($svc['data']['available_courier_companies'] ?? [] as $c) {
                    if ((int) ($c['courier_company_id'] ?? 0) === (int) $sr_courier_id) {
                        $courier_name = $c['courier_name'] ?? '';
                        if (!$shipping_cost) {
                            $shipping_cost = isset($c['rate']) ? (float) $c['rate'] : (isset($c['freight_charge']) ? (float) $c['freight_charge'] : 0.0);
                        }
                        break;
                    }
                }
            }
        }

        if (!$awb) {
            thaaniyamhub_log("AJAX Action (Generate AWB): Shiprocket assign AWB API succeeded but returned empty AWB for Order #{$order_id}", 'warning');
            wp_send_json_error(__('AWB not returned by Shiprocket.', 'thaaniyamhub-multi-vendor-orders'));
        }

        thaaniyamhub_log("AJAX Action (Generate AWB): Successfully assigned AWB {$awb} via courier '{$courier_name}' (#{$sr_courier_id}) for Order #{$order_id} - Shipping Cost: {$shipping_cost}");

        // Automatically schedule pickup right after AWB assignment
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $pickup_payload = [
            'shipment_id' => [(int) $record->shiprocket_shipment_id],
            'pickup_date' => [$tomorrow],
        ];
        $pickup_res = $api->request_pickup($pickup_payload, $order_id);
        $pickup_scheduled_date = '';
        $pickup_token = '';
        $pickup_msg = '';
        if (!is_wp_error($pickup_res)) {
            $pickup_scheduled_date = $pickup_res['response']['pickup_scheduled_date'] ?? ($pickup_res['pickup_scheduled_date'] ?? $tomorrow);
            $pickup_token = $pickup_res['response']['pickup_token_number'] ?? ($pickup_res['pickup_token_number'] ?? '');
            $pickup_msg = $pickup_res['response']['data'] ?? ($pickup_res['data'] ?? '');
            thaaniyamhub_log("AJAX Action (Generate AWB): Automatically scheduled pickup for Order #{$order_id}: Date/Time {$pickup_scheduled_date}, Token {$pickup_token}");
        } else {
            thaaniyamhub_log("AJAX Action (Generate AWB): Auto pickup request failed for Order #{$order_id}: " . $pickup_res->get_error_message(), 'warning');
        }

        // Automatically call 'Generate Manifest' API after successful pickup response as per Shiprocket docs
        $manifest_url = '';
        $manifest_res = $api->generate_manifest(['shipment_id' => [(int) $record->shiprocket_shipment_id]], $order_id);
        if (!is_wp_error($manifest_res) && !empty($manifest_res['manifest_url'])) {
            $manifest_url = $manifest_res['manifest_url'];
            thaaniyamhub_log("AJAX Action (Generate AWB): Auto-generated manifest for Order #{$order_id}: {$manifest_url}");
        } elseif (!empty($record->shiprocket_order_id)) {
            $print_res = $api->print_manifest(['order_ids' => [(int) $record->shiprocket_order_id]], $order_id);
            if (!is_wp_error($print_res) && !empty($print_res['manifest_url'])) {
                $manifest_url = $print_res['manifest_url'];
                thaaniyamhub_log("AJAX Action (Generate AWB): Auto-fetched manifest via print_manifest for Order #{$order_id}: {$manifest_url}");
            }
        }

        $fulfillment_status = $pickup_scheduled_date ? 'pickup_scheduled' : 'assigned';
        $update_data = [
            'awb_code'              => $awb,
            'courier_name'          => $courier_name,
            'pickup_scheduled_date' => $pickup_scheduled_date,
            'pickup_token_number'   => $pickup_token,
        ];
        if ($manifest_url) {
            $update_data['manifest_url'] = $manifest_url;
        }

        ThaaniyamHub_Shiprocket_API::update_status($order_id, $fulfillment_status, $update_data);

        if ($wc_order) {
            $wc_order->update_meta_data('_shiprocket_actual_shipping_cost', $shipping_cost);
            if ($pickup_scheduled_date) {
                $wc_order->update_meta_data('_shiprocket_pickup_scheduled_date', $pickup_scheduled_date);
            }
            if ($pickup_token) {
                $wc_order->update_meta_data('_shiprocket_pickup_token_number', $pickup_token);
            }
            if ($manifest_url) {
                $wc_order->update_meta_data('_shiprocket_manifest_url', $manifest_url);
            }
            $note = sprintf(__('📦 AWB generated: %s via %s - Shipping Cost: %s', 'thaaniyamhub-multi-vendor-orders'), $awb, $courier_name ?: "#{$sr_courier_id}", wc_price($shipping_cost));
            if ($pickup_scheduled_date) {
                $note .= ' ' . sprintf(__('📅 Pickup scheduled: %s%s.%s', 'thaaniyamhub-multi-vendor-orders'), $pickup_scheduled_date, $pickup_token ? " (Token: {$pickup_token})" : '', $pickup_msg ? " Info: {$pickup_msg}" : '');
            }
            if ($manifest_url) {
                $note .= ' ' . sprintf(__('📄 Manifest PDF: <a href="%s" target="_blank">Download Manifest</a>', 'thaaniyamhub-multi-vendor-orders'), esc_url($manifest_url));
            }
            $wc_order->add_order_note($note);
            $wc_order->save();
            if (class_exists('ThaaniyamHub_Ledger')) {
                ThaaniyamHub_Ledger::update_shiprocket_cost($order_id, (float) $shipping_cost, $courier_name, $awb);
            }
        }

        wp_send_json_success([
            'awb'                   => $awb,
            'courier'               => $courier_name,
            'pickup_scheduled_date' => $pickup_scheduled_date,
            'pickup_token'          => $pickup_token,
            'manifest_url'          => $manifest_url,
        ]);
    }

    public static function ajax_print_label()
    {
        check_ajax_referer('thaaniyamhub_sf_nonce', '_nonce');

        $order_id = (int) ($_POST['order_id'] ?? 0);
        if (!self::current_user_can_manage_order($order_id)) {
            wp_send_json_error(__('Insufficient permissions.', 'thaaniyamhub-multi-vendor-orders'), 403);
        }

        thaaniyamhub_log("AJAX Action (Print Label): Initiated for Order #{$order_id} by user #" . get_current_user_id());

        $record = ThaaniyamHub_Shiprocket_API::get_fulfillment($order_id);
        if (!$record || !$record->shiprocket_shipment_id) {
            thaaniyamhub_log("AJAX Action (Print Label): Failed for Order #{$order_id} - Shipment not found in local fulfillment table.", 'error');
            wp_send_json_error(__('Shipment not found.', 'thaaniyamhub-multi-vendor-orders'));
        }

        if ($record->shipping_label_url) {
            thaaniyamhub_log("AJAX Action (Print Label): Returning cached Label URL for Order #{$order_id}: " . $record->shipping_label_url);
            wp_send_json_success(['label_url' => $record->shipping_label_url]);
        }

        thaaniyamhub_log("AJAX Action (Print Label): Requesting label generation from Shiprocket for shipment #{$record->shiprocket_shipment_id} (Order #{$order_id})");

        $api = new ThaaniyamHub_Shiprocket_API();
        $result = $api->generate_label(['shipment_id' => [(int) $record->shiprocket_shipment_id]], $order_id);

        if (is_wp_error($result)) {
            thaaniyamhub_log("AJAX Action (Print Label): Shiprocket generate label API failed for Order #{$order_id}: " . $result->get_error_message(), 'error');
            wp_send_json_error($result->get_error_message());
        }

        $label_url = $result['label_url'] ?? '';
        if (!$label_url) {
            thaaniyamhub_log("AJAX Action (Print Label): Shiprocket generate label API succeeded but returned empty label URL for Order #{$order_id}", 'warning');
            wp_send_json_error(__('Label URL not returned by Shiprocket.', 'thaaniyamhub-multi-vendor-orders'));
        }

        thaaniyamhub_log("AJAX Action (Print Label): Succeeded for Order #{$order_id}. Label URL: {$label_url}");

        ThaaniyamHub_Shiprocket_API::update_status($order_id, 'manifested', ['shipping_label_url' => $label_url]);
        wp_send_json_success(['label_url' => $label_url]);
    }

    public static function ajax_print_invoice()
    {
        check_ajax_referer('thaaniyamhub_sf_nonce', '_nonce');

        $order_id = (int) ($_POST['order_id'] ?? 0);
        if (!self::current_user_can_manage_order($order_id)) {
            wp_send_json_error(__('Insufficient permissions.', 'thaaniyamhub-multi-vendor-orders'), 403);
        }

        thaaniyamhub_log("AJAX Action (Print Invoice): Initiated for Order #{$order_id} by user #" . get_current_user_id());

        $record = ThaaniyamHub_Shiprocket_API::get_fulfillment($order_id);
        if (!$record || !$record->shiprocket_order_id) {
            thaaniyamhub_log("AJAX Action (Print Invoice): Failed for Order #{$order_id} - Shipment not found in local fulfillment table.", 'error');
            wp_send_json_error(__('Shipment not found.', 'thaaniyamhub-multi-vendor-orders'));
        }

        if ($record->commercial_invoice_url) {
            thaaniyamhub_log("AJAX Action (Print Invoice): Returning cached Invoice URL for Order #{$order_id}: " . $record->commercial_invoice_url);
            wp_send_json_success(['invoice_url' => $record->commercial_invoice_url]);
        }

        thaaniyamhub_log("AJAX Action (Print Invoice): Requesting commercial invoice generation from Shiprocket for Order #{$order_id} (Shiprocket Order: #{$record->shiprocket_order_id})");

        $api = new ThaaniyamHub_Shiprocket_API();
        $result = $api->generate_invoice(['ids' => [(int) $record->shiprocket_order_id]], $order_id);

        if (is_wp_error($result)) {
            thaaniyamhub_log("AJAX Action (Print Invoice): Shiprocket generate invoice API failed for Order #{$order_id}: " . $result->get_error_message(), 'error');
            wp_send_json_error($result->get_error_message());
        }

        $invoice_url = $result['invoice_url'] ?? '';
        if (!$invoice_url) {
            thaaniyamhub_log("AJAX Action (Print Invoice): Shiprocket generate invoice API succeeded but returned empty invoice URL for Order #{$order_id}", 'warning');
            wp_send_json_error(__('Invoice URL not returned by Shiprocket.', 'thaaniyamhub-multi-vendor-orders'));
        }

        thaaniyamhub_log("AJAX Action (Print Invoice): Succeeded for Order #{$order_id}. Invoice URL: {$invoice_url}");

        ThaaniyamHub_Shiprocket_API::update_status($order_id, 'manifested', ['commercial_invoice_url' => $invoice_url]);
        wp_send_json_success(['invoice_url' => $invoice_url]);
    }

    public static function ajax_cancel_shipment()
    {
        check_ajax_referer('thaaniyamhub_sf_nonce', '_nonce');

        $order_id = (int) ($_POST['order_id'] ?? 0);
        if (!self::current_user_can_manage_order($order_id)) {
            wp_send_json_error(__('Insufficient permissions.', 'thaaniyamhub-multi-vendor-orders'), 403);
        }

        thaaniyamhub_log("AJAX Action (Cancel Shipment): Initiated for Order #{$order_id} by user #" . get_current_user_id());

        $record = ThaaniyamHub_Shiprocket_API::get_fulfillment($order_id);
        if (!$record || !$record->shiprocket_order_id) {
            thaaniyamhub_log("AJAX Action (Cancel Shipment): Failed - Fulfillment record not found for Order #{$order_id}.", 'error');
            wp_send_json_error(__('Fulfillment record not found.', 'thaaniyamhub-multi-vendor-orders'));
        }

        thaaniyamhub_log("AJAX Action (Cancel Shipment): Requesting shipment cancellation from Shiprocket for Order #{$order_id} (Shiprocket Order: #{$record->shiprocket_order_id})");

        $api = new ThaaniyamHub_Shiprocket_API();
        $result = $api->cancel_order([$record->shiprocket_order_id], $order_id);

        if (is_wp_error($result)) {
            thaaniyamhub_log("AJAX Action (Cancel Shipment): Shiprocket cancel API failed for Order #{$order_id}: " . $result->get_error_message(), 'error');
            wp_send_json_error($result->get_error_message());
        }

        thaaniyamhub_log("AJAX Action (Cancel Shipment): Succeeded for Order #{$order_id}. Shipment cancelled.");

        ThaaniyamHub_Shiprocket_API::update_status($order_id, 'cancelled');

        $order = wc_get_order($order_id);
        if ($order) {
            $order->add_order_note(__('❌ Shiprocket shipment cancelled by admin.', 'thaaniyamhub-multi-vendor-orders'));
            $order->set_status('cancelled');
            $order->save();
        }

        wp_send_json_success();
    }

    public static function ajax_set_default_pickup()
    {
        check_ajax_referer('thaaniyamhub_sf_nonce', '_nonce');

        $order_id = (int) ($_POST['order_id'] ?? 0);
        if (!self::current_user_can_manage_order($order_id)) {
            wp_send_json_error(__('Insufficient permissions.', 'thaaniyamhub-multi-vendor-orders'), 403);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(__('Order not found.', 'thaaniyamhub-multi-vendor-orders'));
        }

        $vendor_id = ThaaniyamHub_Dispatch::get_order_vendor_id($order);
        if (!$vendor_id) {
            wp_send_json_error(__('Vendor not found for this order.', 'thaaniyamhub-multi-vendor-orders'));
        }

        $pickup_location = sanitize_text_field($_POST['pickup_location'] ?? '');
        if (!$pickup_location) {
            wp_send_json_error(__('Pickup location is empty.', 'thaaniyamhub-multi-vendor-orders'));
        }

        if (class_exists('ThaaniyamHub_Pickup_Manager') && method_exists('ThaaniyamHub_Pickup_Manager', 'set_vendor_pickup_location')) {
            ThaaniyamHub_Pickup_Manager::set_vendor_pickup_location($vendor_id, $pickup_location, 'ORDER_SET_DEFAULT');
        } else {
            update_user_meta($vendor_id, '_shiprocket_pickup_id', $pickup_location);
        }

        wp_send_json_success(__('Default pickup location updated.', 'thaaniyamhub-multi-vendor-orders'));
    }

    public static function ajax_add_pickup_location()
    {
        check_ajax_referer('thaaniyamhub_sf_nonce', '_nonce');

        $order_id = (int) ($_POST['order_id'] ?? 0);
        if (!self::current_user_can_manage_order($order_id)) {
            wp_send_json_error(__('Insufficient permissions.', 'thaaniyamhub-multi-vendor-orders'), 403);
        }

        $params = [
            'pickup_location' => sanitize_text_field($_POST['pickup_location'] ?? ''),
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'address' => sanitize_text_field($_POST['address'] ?? ''),
            'address_2' => sanitize_text_field($_POST['address_2'] ?? ''),
            'city' => sanitize_text_field($_POST['city'] ?? ''),
            'state' => sanitize_text_field($_POST['state'] ?? ''),
            'country' => 'India',
            'pin_code' => sanitize_text_field($_POST['pin_code'] ?? ''),
        ];

        foreach (['pickup_location', 'name', 'email', 'phone', 'address', 'city', 'state', 'pin_code'] as $field) {
            if (empty($params[$field])) {
                wp_send_json_error(sprintf(__('Field "%s" is required.', 'thaaniyamhub-multi-vendor-orders'), $field));
            }
        }

        $api = new ThaaniyamHub_Shiprocket_API();
        $result = $api->add_pickup_address($params);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success([
            'pickup_location' => $params['pickup_location'],
            'address_data' => $params,
            'message' => __('Address registered successfully!', 'thaaniyamhub-multi-vendor-orders')
        ]);
    }

    public static function resolve_pickup_details(string $pickup_nickname, int $vendor_id = 0): array
    {
        $details = [
            'pincode' => '',
            'state' => '',
            'city' => '',
            'address' => '',
        ];

        if (empty($pickup_nickname)) {
            return $details;
        }

        $locations = [];
        if (class_exists('ThaaniyamHub_Pickup_Manager')) {
            $locs = ThaaniyamHub_Pickup_Manager::get_cached_locations();
            if (is_array($locs) && !is_wp_error($locs)) {
                $locations = $locs;
            }
        }

        if (empty($locations)) {
            $api = new ThaaniyamHub_Shiprocket_API();
            $pickup_res = $api->get_pickup_addresses();
            if (!is_wp_error($pickup_res) && !empty($pickup_res['data']['shipping_address'])) {
                $locations = $pickup_res['data']['shipping_address'];
            }
        }

        foreach ($locations as $loc) {
            $loc_name = trim($loc['pickup_location'] ?? '');
            if (strcasecmp($loc_name, trim($pickup_nickname)) === 0) {
                $details['pincode'] = trim((string) ($loc['pin_code'] ?? $loc['pincode'] ?? $loc['zip'] ?? $loc['postal_code'] ?? ''));
                $details['state'] = trim((string) ($loc['state'] ?? ''));
                $details['city'] = trim((string) ($loc['city'] ?? ''));
                $details['address'] = trim((string) ($loc['address'] ?? ''));
                break;
            }
        }

        return $details;
    }

    public static function resolve_pickup_pincode(string $pickup_nickname, int $vendor_id): string
    {
        $details = self::resolve_pickup_details($pickup_nickname, $vendor_id);
        return $details['pincode'];
    }

    public static function ajax_get_serviceability_pre_push()
    {
        check_ajax_referer('thaaniyamhub_sf_nonce', '_nonce');

        $order_id = (int) ($_POST['order_id'] ?? 0);
        if (!self::current_user_can_manage_order($order_id)) {
            wp_send_json_error(__('Insufficient permissions.', 'thaaniyamhub-multi-vendor-orders'), 403);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(__('Order not found.', 'thaaniyamhub-multi-vendor-orders'));
        }

        $pickup_nickname = sanitize_text_field($_POST['pickup_location'] ?? '');
        $weight = max(0.1, (float) ($_POST['weight'] ?? 0.1));
        $length = max(1.0, (float) ($_POST['length'] ?? 1.0));
        $breadth = max(1.0, (float) ($_POST['breadth'] ?? 1.0));
        $height = max(1.0, (float) ($_POST['height'] ?? 1.0));

        $vendor_id = ThaaniyamHub_Dispatch::get_order_vendor_id($order);
        $pickup_postcode = self::resolve_pickup_pincode($pickup_nickname, $vendor_id);
        if (!$pickup_postcode) {
            wp_send_json_error(__('Could not resolve pincode for selected pickup location.', 'thaaniyamhub-multi-vendor-orders'));
        }

        $delivery_postcode = $order->get_shipping_postcode() ?: $order->get_billing_postcode();
        if (!$delivery_postcode) {
            wp_send_json_error(__('Order is missing shipping/billing postcode.', 'thaaniyamhub-multi-vendor-orders'));
        }

        $is_cod = strtolower($order->get_payment_method()) === 'cod' ? 1 : 0;

        $params = [
            'pickup_postcode' => (int) $pickup_postcode,
            'delivery_postcode' => (int) $delivery_postcode,
            'weight' => $weight,
            'cod' => $is_cod,
            'length' => (int) $length,
            'breadth' => (int) $breadth,
            'height' => (int) $height,
            'declared_value' => (int) $order->get_total(),
        ];

        $api = new ThaaniyamHub_Shiprocket_API();
        $response = $api->check_serviceability($params);

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        $couriers = $response['data']['available_courier_companies'] ?? [];
        if (!is_array($couriers) || empty($couriers)) {
            wp_send_json_error(__('No serviceability for the selected parameters.', 'thaaniyamhub-multi-vendor-orders'));
        }

        $couriers = self::maybe_filter_couriers_for_vendor($couriers);

        $selected_courier = (int) $order->get_meta('_shiprocket_selected_courier_id');

        wp_send_json_success([
            'couriers' => array_values($couriers),
            'selected_id' => $selected_courier,
        ]);
    }

    public static function ajax_reassign_courier()
    {
        check_ajax_referer('thaaniyamhub_sf_nonce', '_nonce');

        $order_id = (int) ($_POST['order_id'] ?? 0);
        if (!self::current_user_can_manage_order($order_id)) {
            wp_send_json_error(__('Insufficient permissions.', 'thaaniyamhub-multi-vendor-orders'), 403);
        }

        thaaniyamhub_log("AJAX Action (Reassign Courier): Initiated for Order #{$order_id} by user #" . get_current_user_id());

        $record = ThaaniyamHub_Shiprocket_API::get_fulfillment($order_id);
        if (!$record || !$record->shiprocket_shipment_id) {
            thaaniyamhub_log("AJAX Action (Reassign Courier): Failed - Fulfillment record not found for Order #{$order_id}.", 'error');
            wp_send_json_error(__('Fulfillment record not found.', 'thaaniyamhub-multi-vendor-orders'));
        }

        $courier_id = (int) ($_POST['courier_id'] ?? 0);
        if (!$courier_id) {
            thaaniyamhub_log("AJAX Action (Reassign Courier): Failed - no Courier ID selected for Order #{$order_id}.", 'error');
            wp_send_json_error(__('Please select a courier partner.', 'thaaniyamhub-multi-vendor-orders'));
        }

        thaaniyamhub_log("AJAX Action (Reassign Courier): Requesting AWB reassignment from Shiprocket for shipment #{$record->shiprocket_shipment_id} (Order #{$order_id}) to Courier ID {$courier_id}");

        $api = new ThaaniyamHub_Shiprocket_API();
        $result = $api->assign_awb([
            'shipment_id' => [(int) $record->shiprocket_shipment_id],
            'courier_id' => $courier_id,
        ], $order_id);

        if (is_wp_error($result)) {
            thaaniyamhub_log("AJAX Action (Reassign Courier): Shiprocket assign AWB API failed for Order #{$order_id}: " . $result->get_error_message(), 'error');
            wp_send_json_error($result->get_error_message());
        }

        $awb = $result['response']['data']['awb_code'] ?? ($result['awb_code'] ?? '');
        $sr_courier_id = $result['response']['data']['courier_company_id'] ?? 0;

        $courier_name = '';
        $shipping_cost = 0.0;
        if (isset($result['response']['data']['net_total'])) {
            $shipping_cost = (float) $result['response']['data']['net_total'];
        } elseif (isset($result['response']['data']['freight_charge'])) {
            $shipping_cost = (float) $result['response']['data']['freight_charge'];
        }

        if ($sr_courier_id && $record->shiprocket_order_id) {
            $svc = $api->check_serviceability(['order_id' => (int) $record->shiprocket_order_id]);
            if (!is_wp_error($svc)) {
                foreach ($svc['data']['available_courier_companies'] ?? [] as $c) {
                    if ((int) ($c['courier_company_id'] ?? 0) === (int) $sr_courier_id) {
                        $courier_name = $c['courier_name'] ?? '';
                        if (!$shipping_cost) {
                            $shipping_cost = isset($c['rate']) ? (float) $c['rate'] : (isset($c['freight_charge']) ? (float) $c['freight_charge'] : 0.0);
                        }
                        break;
                    }
                }
            }
        }

        if (!$awb) {
            thaaniyamhub_log("AJAX Action (Reassign Courier): Shiprocket assign AWB API succeeded but returned empty AWB for Order #{$order_id}", 'warning');
            wp_send_json_error(__('AWB reassignment failed — no AWB returned.', 'thaaniyamhub-multi-vendor-orders'));
        }

        thaaniyamhub_log("AJAX Action (Reassign Courier): Successfully reassigned AWB {$awb} via courier '{$courier_name}' (#{$sr_courier_id}) for Order #{$order_id} - Shipping Cost: {$shipping_cost}");

        // Automatically schedule pickup and manifest for reassigned courier
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $pickup_payload = [
            'shipment_id' => [(int) $record->shiprocket_shipment_id],
            'pickup_date' => [$tomorrow],
        ];
        $pickup_res = $api->request_pickup($pickup_payload, $order_id);
        $pickup_scheduled_date = '';
        $pickup_token = '';
        if (!is_wp_error($pickup_res)) {
            $pickup_scheduled_date = $pickup_res['response']['pickup_scheduled_date'] ?? ($pickup_res['pickup_scheduled_date'] ?? $tomorrow);
            $pickup_token = $pickup_res['response']['pickup_token_number'] ?? ($pickup_res['pickup_token_number'] ?? '');
            thaaniyamhub_log("AJAX Action (Reassign Courier): Automatically scheduled pickup for Order #{$order_id}: Date/Time {$pickup_scheduled_date}, Token {$pickup_token}");
        }

        $manifest_url = '';
        $manifest_res = $api->generate_manifest(['shipment_id' => [(int) $record->shiprocket_shipment_id]], $order_id);
        if (!is_wp_error($manifest_res) && !empty($manifest_res['manifest_url'])) {
            $manifest_url = $manifest_res['manifest_url'];
        }

        $update_data = [
            'awb_code'              => $awb,
            'courier_name'          => $courier_name,
            'pickup_scheduled_date' => $pickup_scheduled_date,
            'pickup_token_number'   => $pickup_token,
        ];
        if ($manifest_url) {
            $update_data['manifest_url'] = $manifest_url;
        }

        ThaaniyamHub_Shiprocket_API::update_status($order_id, $pickup_scheduled_date ? 'pickup_scheduled' : 'assigned', $update_data);

        $wc_order = wc_get_order($order_id);
        if ($wc_order) {
            $wc_order->update_meta_data('_shiprocket_selected_courier_id', $courier_id);
            $wc_order->update_meta_data('_shiprocket_actual_shipping_cost', $shipping_cost);
            if ($pickup_scheduled_date) {
                $wc_order->update_meta_data('_shiprocket_pickup_scheduled_date', $pickup_scheduled_date);
            }
            if ($pickup_token) {
                $wc_order->update_meta_data('_shiprocket_pickup_token_number', $pickup_token);
            }
            if ($manifest_url) {
                $wc_order->update_meta_data('_shiprocket_manifest_url', $manifest_url);
            }
            $wc_order->add_order_note(sprintf(__('🔄 AWB Reassigned: %s via %s - Shipping Cost: %s', 'thaaniyamhub-multi-vendor-orders'), $awb, $courier_name ?: "#{$sr_courier_id}", wc_price($shipping_cost)));
            $wc_order->save();
            if (class_exists('ThaaniyamHub_Ledger')) {
                ThaaniyamHub_Ledger::update_shiprocket_cost($order_id, (float) $shipping_cost, $courier_name, $awb);
            }
        }

        wp_send_json_success(['message' => __('Courier partner changed successfully!', 'thaaniyamhub-multi-vendor-orders')]);
    }

    public static function ajax_schedule_pickup()
    {
        check_ajax_referer('thaaniyamhub_sf_nonce', '_nonce');

        $order_id = (int) ($_POST['order_id'] ?? 0);
        if (!self::current_user_can_manage_order($order_id)) {
            wp_send_json_error(__('Insufficient permissions.', 'thaaniyamhub-multi-vendor-orders'), 403);
        }

        thaaniyamhub_log("AJAX Action (Schedule Pickup): Initiated for Order #{$order_id} by user #" . get_current_user_id());

        $record = ThaaniyamHub_Shiprocket_API::get_fulfillment($order_id);
        if (!$record || !$record->shiprocket_shipment_id) {
            thaaniyamhub_log("AJAX Action (Schedule Pickup): Failed - Shipment record not found for Order #{$order_id}.", 'error');
            wp_send_json_error(__('Shipment not found. Dispatch the order first.', 'thaaniyamhub-multi-vendor-orders'));
        }

        if (empty($record->awb_code)) {
            thaaniyamhub_log("AJAX Action (Schedule Pickup): Failed - AWB not generated yet for Order #{$order_id}.", 'error');
            wp_send_json_error(__('AWB not generated yet. Please generate AWB first.', 'thaaniyamhub-multi-vendor-orders'));
        }

        thaaniyamhub_log("AJAX Action (Schedule Pickup): Requesting pickup schedule from Shiprocket for shipment #{$record->shiprocket_shipment_id} (Order #{$order_id})");

        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $pickup_date = sanitize_text_field($_POST['pickup_date'] ?? $tomorrow);
        if (!$pickup_date) {
            $pickup_date = $tomorrow;
        }

        $api = new ThaaniyamHub_Shiprocket_API();
        $result = $api->request_pickup([
            'shipment_id' => [(int) $record->shiprocket_shipment_id],
            'pickup_date' => [$pickup_date],
        ], $order_id);

        if (is_wp_error($result)) {
            thaaniyamhub_log("AJAX Action (Schedule Pickup): Shiprocket pickup schedule API failed for Order #{$order_id}: " . $result->get_error_message(), 'error');
            wp_send_json_error($result->get_error_message());
        }

        // Shiprocket returns pickup_scheduled_date with date and estimated pickup time
        $pickup_scheduled_date = $result['response']['pickup_scheduled_date'] ?? ($result['pickup_scheduled_date'] ?? $pickup_date);
        $pickup_token = $result['response']['pickup_token_number'] ?? ($result['pickup_token_number'] ?? '');
        $pickup_msg = $result['response']['data'] ?? ($result['data'] ?? '');

        thaaniyamhub_log("AJAX Action (Schedule Pickup): Succeeded for Order #{$order_id}. Scheduled Date/Time: {$pickup_scheduled_date}, Token: {$pickup_token}");

        // As per Shiprocket docs: Call 'Generate Manifest' API after the successful response of pickup API
        $manifest_url = '';
        $manifest_result = $api->generate_manifest(['shipment_id' => [(int) $record->shiprocket_shipment_id]], $order_id);
        if (!is_wp_error($manifest_result) && !empty($manifest_result['manifest_url'])) {
            $manifest_url = $manifest_result['manifest_url'];
            thaaniyamhub_log("AJAX Action (Schedule Pickup): Auto-generated manifest for Order #{$order_id}: {$manifest_url}");
        } elseif (!empty($record->shiprocket_order_id)) {
            $print_result = $api->print_manifest(['order_ids' => [(int) $record->shiprocket_order_id]], $order_id);
            if (!is_wp_error($print_result) && !empty($print_result['manifest_url'])) {
                $manifest_url = $print_result['manifest_url'];
                thaaniyamhub_log("AJAX Action (Schedule Pickup): Auto-fetched manifest via print_manifest for Order #{$order_id}: {$manifest_url}");
            }
        }

        $status = 'pickup_scheduled';
        $update_data = [
            'pickup_scheduled_date' => $pickup_scheduled_date,
            'pickup_token_number'   => $pickup_token,
        ];
        if ($manifest_url) {
            $update_data['manifest_url'] = $manifest_url;
        }

        ThaaniyamHub_Shiprocket_API::update_status($order_id, $status, $update_data);

        $order = wc_get_order($order_id);
        if ($order) {
            $order->update_meta_data('_shiprocket_pickup_scheduled_date', $pickup_scheduled_date);
            if ($pickup_token) {
                $order->update_meta_data('_shiprocket_pickup_token_number', $pickup_token);
            }
            if ($manifest_url) {
                $order->update_meta_data('_shiprocket_manifest_url', $manifest_url);
            }
            $note = sprintf(
                __('📅 Shiprocket pickup scheduled: %s%s.%s', 'thaaniyamhub-multi-vendor-orders'),
                $pickup_scheduled_date,
                $pickup_token ? " (Token: {$pickup_token})" : '',
                $pickup_msg ? " Info: {$pickup_msg}" : ''
            );
            if ($manifest_url) {
                $note .= ' ' . sprintf(__('📄 Manifest PDF: <a href="%s" target="_blank">Download Manifest</a>', 'thaaniyamhub-multi-vendor-orders'), esc_url($manifest_url));
            }
            $order->add_order_note($note);
            $order->save();
        }

        wp_send_json_success([
            'message' => __('Pickup scheduled successfully!', 'thaaniyamhub-multi-vendor-orders'),
            'pickup_scheduled_date' => $pickup_scheduled_date,
            'pickup_token' => $pickup_token,
            'manifest_url' => $manifest_url
        ]);
    }

    public static function ajax_download_manifest()
    {
        check_ajax_referer('thaaniyamhub_sf_nonce', '_nonce');

        $order_id = (int) ($_POST['order_id'] ?? 0);
        if (!self::current_user_can_manage_order($order_id)) {
            wp_send_json_error(__('Insufficient permissions.', 'thaaniyamhub-multi-vendor-orders'), 403);
        }

        thaaniyamhub_log("AJAX Action (Download Manifest): Initiated for Order #{$order_id} by user #" . get_current_user_id());

        $record = ThaaniyamHub_Shiprocket_API::get_fulfillment($order_id);
        if (!$record || !$record->shiprocket_shipment_id) {
            thaaniyamhub_log("AJAX Action (Download Manifest): Failed - Shipment record not found for Order #{$order_id}.", 'error');
            wp_send_json_error(__('Shipment record not found.', 'thaaniyamhub-multi-vendor-orders'));
        }

        if (empty($record->awb_code)) {
            thaaniyamhub_log("AJAX Action (Download Manifest): Failed - AWB not generated yet for Order #{$order_id}.", 'error');
            wp_send_json_error(__('AWB not generated yet. Please generate AWB first.', 'thaaniyamhub-multi-vendor-orders'));
        }

        // Return cached manifest URL if already available
        if (!empty($record->manifest_url)) {
            thaaniyamhub_log("AJAX Action (Download Manifest): Returning cached manifest URL for Order #{$order_id}: {$record->manifest_url}");
            wp_send_json_success(['manifest_url' => $record->manifest_url]);
        }

        $order = wc_get_order($order_id);
        $cached_url = $order ? $order->get_meta('_shiprocket_manifest_url') : '';
        if ($cached_url) {
            ThaaniyamHub_Shiprocket_API::update_status($order_id, $record->fulfillment_status, ['manifest_url' => $cached_url]);
            wp_send_json_success(['manifest_url' => $cached_url]);
        }

        thaaniyamhub_log("AJAX Action (Download Manifest): Requesting manifest generation from Shiprocket for shipment #{$record->shiprocket_shipment_id} (Order #{$order_id})");

        $api = new ThaaniyamHub_Shiprocket_API();
        
        // 1. First attempt generate_manifest
        $result = $api->generate_manifest(['shipment_id' => [(int) $record->shiprocket_shipment_id]], $order_id);
        $manifest_url = '';

        if (!is_wp_error($result) && !empty($result['manifest_url'])) {
            $manifest_url = $result['manifest_url'];
        }

        // 2. If generate_manifest did not return URL (e.g. already manifested), try print_manifest
        if (!$manifest_url && !empty($record->shiprocket_order_id)) {
            thaaniyamhub_log("AJAX Action (Download Manifest): Calling print_manifest for Order #{$order_id} (Shiprocket Order #{$record->shiprocket_order_id})");
            $print_result = $api->print_manifest(['order_ids' => [(int) $record->shiprocket_order_id]], $order_id);
            if (!is_wp_error($print_result) && !empty($print_result['manifest_url'])) {
                $manifest_url = $print_result['manifest_url'];
            }
        }

        if (!$manifest_url) {
            $err_msg = is_wp_error($result) ? $result->get_error_message() : __('Manifest could not be generated. Please ensure pickup is scheduled first.', 'thaaniyamhub-multi-vendor-orders');
            thaaniyamhub_log("AJAX Action (Download Manifest): Failed for Order #{$order_id}. Error: {$err_msg}", 'error');
            wp_send_json_error($err_msg);
        }

        thaaniyamhub_log("AJAX Action (Download Manifest): Succeeded for Order #{$order_id}. Manifest URL: {$manifest_url}");

        $new_status = in_array($record->fulfillment_status, ['assigned', 'dispatched'], true) ? 'manifested' : $record->fulfillment_status;
        ThaaniyamHub_Shiprocket_API::update_status($order_id, $new_status, ['manifest_url' => $manifest_url]);

        if ($order) {
            $order->update_meta_data('_shiprocket_manifest_url', $manifest_url);
            $order->add_order_note(sprintf(__('📋 Shiprocket Manifest generated: <a href="%s" target="_blank">%s</a>', 'thaaniyamhub-multi-vendor-orders'), esc_url($manifest_url), esc_url($manifest_url)));
            $order->save();
        }

        wp_send_json_success(['manifest_url' => $manifest_url]);
    }

    public static function ajax_get_couriers()
    {
        check_ajax_referer('thaaniyamhub_sf_nonce', '_nonce');

        $order_id = (int) ($_POST['order_id'] ?? 0);
        if (!self::current_user_can_manage_order($order_id)) {
            wp_send_json_error(__('Insufficient permissions.', 'thaaniyamhub-multi-vendor-orders'), 403);
        }

        $record = ThaaniyamHub_Shiprocket_API::get_fulfillment($order_id);
        if (!$record || !$record->shiprocket_order_id) {
            wp_send_json_error(__('Shipment not found — dispatch the order first.', 'thaaniyamhub-multi-vendor-orders'));
        }

        $api = new ThaaniyamHub_Shiprocket_API();
        $response = $api->check_serviceability(['order_id' => (int) $record->shiprocket_order_id]);

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        $couriers = $response['data']['available_courier_companies'] ?? [];
        if (!is_array($couriers) || empty($couriers)) {
            wp_send_json_error(
                __('No courier partners available for this shipment. Please wait a moment and try again.', 'thaaniyamhub-multi-vendor-orders')
            );
        }

        $couriers = self::maybe_filter_couriers_for_vendor($couriers);

        $wc_order = wc_get_order($order_id);
        $selected_courier = $wc_order ? (int) $wc_order->get_meta('_shiprocket_selected_courier_id') : 0;

        wp_send_json_success([
            'couriers' => array_values($couriers),
            'selected_id' => $selected_courier,
        ]);
    }

    /**
     * Check if the current user is a vendor.
     */
    private static function is_current_user_vendor(): bool
    {
        $user = wp_get_current_user();
        if (!$user || 0 === $user->ID) {
            return false;
        }

        $roles = (array) $user->roles;

        // 1. Check explicit vendor roles first
        $vendor_roles = ['wcfm_vendor', 'vendor', 'seller'];
        foreach ($vendor_roles as $role) {
            if (in_array($role, $roles, true)) {
                thaaniyamhub_log("is_current_user_vendor check: user_id=" . $user->ID . ", roles=" . implode(',', $roles) . " -> YES (vendor role matched)");
                return true;
            }
        }

        // 2. Check WCFM global helper
        if (function_exists('wcfm_is_vendor') && wcfm_is_vendor()) {
            thaaniyamhub_log("is_current_user_vendor check: user_id=" . $user->ID . ", roles=" . implode(',', $roles) . " -> YES (wcfm_is_vendor() is true)");
            return true;
        }

        // 3. Admin / Store manager checks
        $is_admin_or_mgr = current_user_can('manage_woocommerce') || current_user_can('administrator');
        thaaniyamhub_log("is_current_user_vendor check: user_id=" . $user->ID . ", roles=" . implode(',', $roles) . ", is_admin_or_mgr=" . ($is_admin_or_mgr ? 'yes' : 'no'));
        if ($is_admin_or_mgr) {
            return false;
        }

        return false;
    }

    /**
     * Filter couriers to only include the ones with the lowest price if the current user is a vendor.
     */
    private static function maybe_filter_couriers_for_vendor(array $couriers): array
    {
        $is_vendor = self::is_current_user_vendor();
        thaaniyamhub_log("maybe_filter_couriers_for_vendor check: is_vendor=" . ($is_vendor ? 'yes' : 'no') . ", total_couriers=" . count($couriers));

        if (!$is_vendor) {
            return $couriers;
        }

        $min_rate = null;
        foreach ($couriers as $c) {
            $rate = isset($c['rate']) ? (float) $c['rate'] : (isset($c['freight_charge']) ? (float) $c['freight_charge'] : null);
            if (null !== $rate) {
                if (null === $min_rate || $rate < $min_rate) {
                    $min_rate = $rate;
                }
            }
        }

        thaaniyamhub_log("maybe_filter_couriers_for_vendor: min_rate=" . ($min_rate !== null ? $min_rate : 'null'));

        if (null !== $min_rate) {
            $filtered = [];
            foreach ($couriers as $c) {
                $rate = isset($c['rate']) ? (float) $c['rate'] : (isset($c['freight_charge']) ? (float) $c['freight_charge'] : null);
                if (null !== $rate && abs($rate - $min_rate) < 0.01) {
                    $filtered[] = $c;
                }
            }
            thaaniyamhub_log("maybe_filter_couriers_for_vendor: filtered count=" . count($filtered));
            if (!empty($filtered)) {
                return $filtered;
            }
        }

        return $couriers;
    }


    // =========================================================================
    // USER PROFILE EDIT FIELDS (BACKEND)
    // =========================================================================

    public static function render_vendor_profile_fields($user)
    {
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }

        $vendor_id = $user->ID;
        $default_pickup = get_user_meta($vendor_id, '_shiprocket_pickup_id', true);

        $api = new ThaaniyamHub_Shiprocket_API();
        $pickup_res = $api->get_pickup_addresses();
        $locations = [];
        if (!is_wp_error($pickup_res) && !empty($pickup_res['data']['shipping_address'])) {
            foreach ($pickup_res['data']['shipping_address'] as $addr) {
                if (!empty($addr['pickup_location']) && !empty($addr['status'])) {
                    $locations[] = trim($addr['pickup_location']);
                }
            }
        }
        ?>
        <h3><?php esc_html_e('Shiprocket Store Fulfillment Settings', 'thaaniyamhub-multi-vendor-orders'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label
                        for="_shiprocket_pickup_id"><?php esc_html_e('Default Pickup Location', 'thaaniyamhub-multi-vendor-orders'); ?></label>
                </th>
                <td>
                    <select name="_shiprocket_pickup_id" id="_shiprocket_pickup_id">
                        <option value="">
                            <?php esc_html_e('— Select Default Location —', 'thaaniyamhub-multi-vendor-orders'); ?>
                        </option>
                        <?php if (!empty($locations)): ?>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?php echo esc_attr($loc); ?>" <?php selected($loc, $default_pickup); ?>>
                                    <?php echo esc_html($loc); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php if ($default_pickup): ?>
                                <option value="<?php echo esc_attr($default_pickup); ?>" selected>
                                    <?php echo esc_html($default_pickup); ?>
                                </option>
                            <?php endif; ?>
                        <?php endif; ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e('Select the default pickup location for this vendor\'s store when dispatching shipments.', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    public static function save_vendor_profile_fields($user_id)
    {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }
        if (isset($_POST['_shiprocket_pickup_id'])) {
            $user_id = (int) $user_id;
            $pickup_location = sanitize_text_field($_POST['_shiprocket_pickup_id']);
            if (class_exists('ThaaniyamHub_Pickup_Manager') && method_exists('ThaaniyamHub_Pickup_Manager', 'set_vendor_pickup_location')) {
                ThaaniyamHub_Pickup_Manager::set_vendor_pickup_location($user_id, $pickup_location, 'WP_PROFILE_SAVE');
            } else {
                update_user_meta($user_id, '_shiprocket_pickup_id', $pickup_location);
            }
        }
    }

    // =========================================================================
    // VENDOR ISOLATION VIEWS
    // =========================================================================

    public static function vendor_product_isolation(WP_Query $query)
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        global $pagenow;
        if ('edit.php' !== $pagenow || 'product' !== $query->get('post_type')) {
            return;
        }

        if (current_user_can('manage_woocommerce') || current_user_can('administrator')) {
            return;
        }

        if (current_user_can('market_vendor') || (function_exists('wcfm_is_vendor') && wcfm_is_vendor())) {
            $query->set('author', get_current_user_id());
        }
    }

    public static function vendor_order_isolation_legacy(WP_Query $query)
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        global $pagenow;
        if ('edit.php' !== $pagenow || 'shop_order' !== $query->get('post_type')) {
            return;
        }

        if (current_user_can('manage_woocommerce') || current_user_can('administrator')) {
            return;
        }

        $vendor_id = get_current_user_id();
        $meta_query = $query->get('meta_query') ?: [];
        $meta_query[] = [
            'key' => '_order_vendor_id',
            'value' => $vendor_id,
            'compare' => '=',
        ];
        $query->set('meta_query', $meta_query);
    }

    public static function vendor_order_isolation_hpos(array $query_args): array
    {
        if (current_user_can('manage_woocommerce') || current_user_can('administrator')) {
            return $query_args;
        }

        $vendor_id = get_current_user_id();
        $meta_query = isset($query_args['meta_query']) ? $query_args['meta_query'] : [];
        $meta_query[] = [
            'key' => '_order_vendor_id',
            'value' => $vendor_id,
            'compare' => '=',
        ];
        $query_args['meta_query'] = $meta_query;

        return $query_args;
    }

    public static function restrict_order_edit_access()
    {
        if (!is_admin()) {
            return;
        }

        if (current_user_can('manage_woocommerce') || current_user_can('administrator')) {
            return;
        }

        global $pagenow;
        $order_id = 0;

        if ('post.php' === $pagenow && isset($_GET['post'])) {
            $post = get_post((int) $_GET['post']);
            if ($post && 'shop_order' === $post->post_type) {
                $order_id = $post->ID;
            }
        } elseif ('admin.php' === $pagenow && isset($_GET['page']) && 'wc-orders' === $_GET['page'] && isset($_GET['id'])) {
            $order_id = (int) $_GET['id'];
        }

        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $vendor_id = (int) $order->get_meta('_order_vendor_id');
                if ($vendor_id !== get_current_user_id()) {
                    wp_die(__('You do not have permission to access this order.', 'thaaniyamhub-multi-vendor-orders'));
                }
            }
        }
    }

    public static function save_wcfm_settings($vendor_id, $wcfm_settings_form)
    {
        $vendor_id = (int) $vendor_id;
        if (!$vendor_id) {
            return;
        }

        // Only update if shiprocket_pickup_id was EXPLICITLY submitted in the HTTP POST request payload,
        // and NOT merely inherited from WCFM's background merge of existing wcfmmp_profile_settings!
        $raw_submitted_fields = [];
        if (isset($_POST['wcfm_settings_form'])) {
            if (is_string($_POST['wcfm_settings_form'])) {
                parse_str($_POST['wcfm_settings_form'], $raw_submitted_fields);
            } elseif (is_array($_POST['wcfm_settings_form'])) {
                $raw_submitted_fields = $_POST['wcfm_settings_form'];
            }
        }

        if (isset($raw_submitted_fields['shiprocket_pickup_id'])) {
            $pickup_location = sanitize_text_field($raw_submitted_fields['shiprocket_pickup_id']);
            if (class_exists('ThaaniyamHub_Pickup_Manager') && method_exists('ThaaniyamHub_Pickup_Manager', 'set_vendor_pickup_location')) {
                ThaaniyamHub_Pickup_Manager::set_vendor_pickup_location($vendor_id, $pickup_location, 'WCFM_SETTINGS_SAVE');
            } else {
                update_user_meta($vendor_id, '_shiprocket_pickup_id', $pickup_location);
            }
        }
    }

    /**
     * Ensure vendors can view details for customers who have ordered their products.
     *
     * @param bool   $is_component_for_vendor Current permission result.
     * @param int    $component_id            Customer User ID.
     * @param string $component               Component name ('customer').
     * @param int    $current_vendor          Vendor User ID.
     * @return bool
     */
    public static function allow_customer_view_access_for_vendor($is_component_for_vendor, $component_id, $component, $current_vendor)
    {
        if ($component !== 'customer') {
            return $is_component_for_vendor;
        }
        if ($is_component_for_vendor) {
            return true;
        }
        if (!$component_id || !$current_vendor) {
            return $is_component_for_vendor;
        }

        global $wpdb;

        // 1. Check WCFM Marketplace orders table for existing commission record
        $table_name = $wpdb->prefix . 'wcfm_marketplace_orders';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            $has_wcfm_order = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$table_name} WHERE vendor_id = %d AND customer_id = %d LIMIT 1",
                $current_vendor,
                $component_id
            ));
            if ($has_wcfm_order) {
                return true;
            }
        }

        // 2. Check WooCommerce order postmeta (_customer_user and _thaaniyamhub_vendor_id)
        if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil') && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
            $has_wc_order = $wpdb->get_var($wpdb->prepare(
                "SELECT o.id FROM {$wpdb->prefix}wc_orders o
                 INNER JOIN {$wpdb->prefix}wc_orders_meta om ON (o.id = om.order_id AND om.meta_key = '_thaaniyamhub_vendor_id' AND om.meta_value = %d)
                 WHERE o.customer_id = %d
                 LIMIT 1",
                $current_vendor,
                $component_id
            ));
        } else {
            $has_wc_order = $wpdb->get_var($wpdb->prepare(
                "SELECT p.ID FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm1 ON (p.ID = pm1.post_id AND pm1.meta_key = '_customer_user' AND pm1.meta_value = %d)
                 INNER JOIN {$wpdb->postmeta} pm2 ON (p.ID = pm2.post_id AND pm2.meta_key = '_thaaniyamhub_vendor_id' AND pm2.meta_value = %d)
                 WHERE p.post_type IN ('shop_order', 'shop_order_placehold')
                 LIMIT 1",
                $component_id,
                $current_vendor
            ));
        }
        if ($has_wc_order) {
            return true;
        }

        // 3. Check line items of parent/sub-orders placed by customer for vendor's products
        $vendor_products = get_posts([
            'post_type' => 'product',
            'post_status' => 'any',
            'author' => $current_vendor,
            'fields' => 'ids',
            'posts_per_page' => -1,
        ]);

        if (!empty($vendor_products)) {
            $product_ids_imploded = implode(',', array_map('intval', $vendor_products));
            if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil') && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
                $has_item = $wpdb->get_var($wpdb->prepare(
                    "SELECT o.id FROM {$wpdb->prefix}wc_orders o
                     INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON (o.id = oi.order_id AND oi.order_item_type = 'line_item')
                     INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON (oi.order_item_id = oim.order_item_id AND oim.meta_key IN ('_product_id', '_variation_id') AND oim.meta_value IN ({$product_ids_imploded}))
                     WHERE o.customer_id = %d
                     LIMIT 1",
                    $component_id
                ));
            } else {
                $has_item = $wpdb->get_var($wpdb->prepare(
                    "SELECT o.ID FROM {$wpdb->posts} o
                     INNER JOIN {$wpdb->postmeta} pm ON (o.ID = pm.post_id AND pm.meta_key = '_customer_user' AND pm.meta_value = %d)
                     INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON (o.ID = oi.order_id AND oi.order_item_type = 'line_item')
                     INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON (oi.order_item_id = oim.order_item_id AND oim.meta_key IN ('_product_id', '_variation_id') AND oim.meta_value IN ({$product_ids_imploded}))
                     WHERE o.post_type IN ('shop_order', 'shop_order_placehold')
                     LIMIT 1",
                    $component_id
                ));
            }
            if ($has_item) {
                return true;
            }
        }

        return $is_component_for_vendor;
    }

    // =========================================================================
    // CASHFREE VENDOR WITHDRAWAL & BILLING SETUP HELPERS
    // =========================================================================

    /**
     * Register Cashfree as a selectable withdrawal method in WCFM.
     *
     * @param array $methods
     * @return array
     */
    public static function register_cashfree_withdrawal_method( $methods ) {
        if ( class_exists( 'WCFMmp_Gateway_Cashfree' ) ) {
            $methods['cashfree'] = __( 'Cashfree Payouts', 'thaaniyamhub-multi-vendor-orders' );
        }
        return $methods;
    }

    /**
     * Ensure Cashfree is available in active withdrawal methods list.
     *
     * @param array $methods
     * @return array
     */
    public static function filter_active_withdrawal_methods( $methods ) {
        if ( ! class_exists( 'WCFMmp_Gateway_Cashfree' ) ) {
            return $methods;
        }

        $api = class_exists( 'ThaaniyamHub_Cashfree_Payout_API' ) ? ThaaniyamHub_Cashfree_Payout_API::get_instance() : null;
        if ( $api && $api->is_configured() ) {
            if ( ! isset( $methods['cashfree'] ) ) {
                $methods['cashfree'] = __( 'Cashfree Payouts (Bank Transfer / UPI)', 'thaaniyamhub-multi-vendor-orders' );
            }
        }
        return $methods;
    }

    /**
     * Add Cashfree Payout (Bank Account / UPI) form fields to WCFM Vendor Billing Settings.
     *
     * @param array $fields
     * @param int   $vendor_id
     * @return array
     */
    public static function add_cashfree_vendor_billing_fields( $fields, $vendor_id ) {
        $vendor_data = get_user_meta( $vendor_id, 'wcfmmp_profile_settings', true );
        if ( ! is_array( $vendor_data ) ) {
            $vendor_data = [];
        }

        $cashfree = $vendor_data['payment']['cashfree'] ?? [];
        $bank     = $vendor_data['payment']['bank'] ?? [];

        $ac_name     = $cashfree['ac_name'] ?? ( $bank['ac_name'] ?? '' );
        $ac_number   = $cashfree['ac_number'] ?? ( $bank['ac_number'] ?? '' );
        $bank_name   = $cashfree['bank_name'] ?? ( $bank['bank_name'] ?? '' );
        $ifsc        = $cashfree['ifsc'] ?? ( $bank['ifsc'] ?? '' );
        $upi_id      = $cashfree['upi_id'] ?? ( $vendor_data['payment']['upi']['vpa'] ?? '' );
        $payout_type = $cashfree['payout_type'] ?? ( ! empty( $upi_id ) && empty( $ac_number ) ? 'upi' : 'bank' );

        $fields['cashfree_payout_heading'] = [
            'label'       => __( 'Cashfree Disbursals', 'thaaniyamhub-multi-vendor-orders' ),
            'type'        => 'html',
            'class'       => 'paymode_field paymode_cashfree',
            'label_class' => 'paymode_field paymode_cashfree',
            'value'       => '<div style="margin-top: 15px; margin-bottom: 10px; border-top: 1px solid #e2e8f0; padding-top: 10px;"><strong style="font-size: 14px; color: #0284c7;">' . esc_html__( 'Cashfree Direct Payout Account Setup', 'thaaniyamhub-multi-vendor-orders' ) . '</strong><p class="description">' . esc_html__( 'Configure your verified Bank Account or UPI ID to receive instant commission disbursements.', 'thaaniyamhub-multi-vendor-orders' ) . '</p></div>',
        ];

        $fields['cashfree_payout_type'] = [
            'label'       => __( 'Payout Destination', 'thaaniyamhub-multi-vendor-orders' ),
            'name'        => 'payment[cashfree][payout_type]',
            'type'        => 'select',
            'options'     => [
                'bank' => __( 'Bank Account (IMPS / NEFT)', 'thaaniyamhub-multi-vendor-orders' ),
                'upi'  => __( 'UPI ID / VPA', 'thaaniyamhub-multi-vendor-orders' ),
            ],
            'class'       => 'wcfm-select wcfm_ele paymode_field paymode_cashfree',
            'label_class' => 'wcfm_title wcfm_ele paymode_field paymode_cashfree',
            'value'       => $payout_type,
        ];

        $fields['cashfree_ac_name'] = [
            'label'       => __( 'Account Holder Name', 'thaaniyamhub-multi-vendor-orders' ),
            'placeholder' => __( 'As registered in bank records', 'thaaniyamhub-multi-vendor-orders' ),
            'name'        => 'payment[cashfree][ac_name]',
            'type'        => 'text',
            'class'       => 'wcfm-text wcfm_ele paymode_field paymode_cashfree cf_bank_field_row',
            'label_class' => 'wcfm_title wcfm_ele paymode_field paymode_cashfree cf_bank_field_row',
            'value'       => $ac_name,
        ];

        $fields['cashfree_ac_number'] = [
            'label'       => __( 'Bank Account Number', 'thaaniyamhub-multi-vendor-orders' ),
            'placeholder' => __( 'Enter bank account number', 'thaaniyamhub-multi-vendor-orders' ),
            'name'        => 'payment[cashfree][ac_number]',
            'type'        => 'text',
            'class'       => 'wcfm-text wcfm_ele paymode_field paymode_cashfree cf_bank_field_row',
            'label_class' => 'wcfm_title wcfm_ele paymode_field paymode_cashfree cf_bank_field_row',
            'value'       => $ac_number,
        ];

        $fields['cashfree_ifsc'] = [
            'label'       => __( 'Bank IFSC Code', 'thaaniyamhub-multi-vendor-orders' ),
            'placeholder' => __( 'e.g. HDFC0001234', 'thaaniyamhub-multi-vendor-orders' ),
            'name'        => 'payment[cashfree][ifsc]',
            'type'        => 'text',
            'class'       => 'wcfm-text wcfm_ele paymode_field paymode_cashfree cf_bank_field_row',
            'label_class' => 'wcfm_title wcfm_ele paymode_field paymode_cashfree cf_bank_field_row',
            'value'       => $ifsc,
        ];

        $fields['cashfree_bank_name'] = [
            'label'       => __( 'Bank & Branch Name', 'thaaniyamhub-multi-vendor-orders' ),
            'placeholder' => __( 'e.g. HDFC Bank, Main Branch', 'thaaniyamhub-multi-vendor-orders' ),
            'name'        => 'payment[cashfree][bank_name]',
            'type'        => 'text',
            'class'       => 'wcfm-text wcfm_ele paymode_field paymode_cashfree cf_bank_field_row',
            'label_class' => 'wcfm_title wcfm_ele paymode_field paymode_cashfree cf_bank_field_row',
            'value'       => $bank_name,
        ];

        $fields['cashfree_upi_id'] = [
            'label'       => __( 'UPI ID / VPA', 'thaaniyamhub-multi-vendor-orders' ),
            'placeholder' => __( 'e.g. merchant@okhdfcbank or 9876543210@paytm', 'thaaniyamhub-multi-vendor-orders' ),
            'name'        => 'payment[cashfree][upi_id]',
            'type'        => 'text',
            'class'       => 'wcfm-text wcfm_ele paymode_field paymode_cashfree cf_upi_field_row',
            'label_class' => 'wcfm_title wcfm_ele paymode_field paymode_cashfree cf_upi_field_row',
            'value'       => $upi_id,
        ];

        return $fields;
    }

    /**
     * Render script for dynamic toggle between Bank Account and UPI fields in WCFM settings.
     */
    public static function render_cashfree_vendor_billing_script() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            function toggleCashfreePayoutFields() {
                var paymentMethod = $('select[name="payment[method]"]').val();
                if (paymentMethod === 'cashfree') {
                    $('.paymode_cashfree').show();
                    var payoutType = $('select[name="payment[cashfree][payout_type]"]').val();
                    if (payoutType === 'upi') {
                        $('.cf_bank_field_row').hide();
                        $('.cf_upi_field_row').show();
                    } else {
                        $('.cf_bank_field_row').show();
                        $('.cf_upi_field_row').hide();
                    }
                } else {
                    $('.paymode_cashfree').hide();
                }
            }

            $(document).on('change', 'select[name="payment[method]"], select[name="payment[cashfree][payout_type]"]', function() {
                toggleCashfreePayoutFields();
            });

            // Initial trigger
            setTimeout(toggleCashfreePayoutFields, 300);
        });
        </script>
        <?php
    }
}

