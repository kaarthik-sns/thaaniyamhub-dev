<?php
/**
 * Thaaniyam Hub Marketplace — Financial Ledger & Accounting Engine
 *
 * Records and computes granular per-order and per-sub-order financial data into
 * `wp_thaaniyamhub_vendor_ledger`:
 *   - Pre-discount Item Subtotal, Coupon Codes & Discount Amounts
 *   - Post-discount Gross Sales (Order Price), Customer Shipping, Taxes, Total Incoming
 *   - Platform Commission, 18% GST Tax on Commission
 *   - Vendor Net Payout & Payout Status
 *   - Actual Shiprocket Logistics Freight Costs, Courier Name, AWB Tracking
 *   - Cashfree Payment Gateway Fees + 18% GST, Payout Transfer Fees + 18% GST
 *   - Total Outflows, Net Platform Profit (₹), and Profit Margin (%)
 *
 * @package thaaniyamhub-multi-vendor-orders
 */

defined('ABSPATH') || exit;

class ThaaniyamHub_Ledger
{

    /**
     * Boot hooks for order status transitions and AJAX export handlers.
     */
    public static function init()
    {
        add_action('woocommerce_order_status_changed', [__CLASS__, 'on_order_status_changed'], 20, 3);
        add_action('woocommerce_order_refunded', [__CLASS__, 'on_order_refunded'], 20, 2);
        add_action('woocommerce_refund_deleted', [__CLASS__, 'on_refund_deleted'], 20, 2);
        add_action('wcfm_refund_request_status_update', [__CLASS__, 'on_wcfm_refund_updated'], 20, 3);
        add_action('wcfm_marketplace_refund_request_approved', [__CLASS__, 'on_wcfm_refund_updated'], 20, 3);
        add_action('woocommerce_trash_order', [__CLASS__, 'on_order_trashed'], 20, 1);
        add_action('trashed_post', [__CLASS__, 'on_order_trashed'], 20, 1);
        add_action('woocommerce_untrash_order', [__CLASS__, 'on_order_untrashed'], 20, 1);
        add_action('untrashed_post', [__CLASS__, 'on_order_untrashed'], 20, 1);
        add_action('woocommerce_delete_order', [__CLASS__, 'on_order_deleted'], 20, 1);
        add_action('deleted_post', [__CLASS__, 'on_order_deleted'], 20, 1);
        add_action('before_delete_post', [__CLASS__, 'on_order_deleted'], 20, 1);
        add_action('wp_ajax_thaaniyamhub_export_ledger_csv', [__CLASS__, 'ajax_export_csv']);
    }

    // =========================================================================
    // 1. PUBLIC RECORDING & RE-CALCULATION API
    // =========================================================================

    /**
     * Backward-compatible wrapper for record_vendor_order.
     *
     * @param WC_Order      $order        The vendor order.
     * @param int           $vendor_id    Vendor user ID.
     * @param WC_Order|null $unused       Optional backward-compatibility parameter.
     * @return bool
     */
    public static function record_from_suborder(WC_Order $order, int $vendor_id, ?WC_Order $unused = null)
    {
        return self::record_vendor_order($order, $vendor_id);
    }

    /**
     * Record or update an order row in the financial ledger.
     *
     * @param WC_Order      $order        The independent standard WooCommerce order.
     * @param int           $vendor_id    Vendor user ID.
     * @param WC_Order|null $unused       Optional backward-compatibility parameter.
     * @return bool
     */
    public static function record_vendor_order(WC_Order $order, int $vendor_id, ?WC_Order $unused = null)
    {
        global $wpdb, $WCFMmp;

        $order_id = $order->get_id();

        // ---------------------------------------------------------------------
        // A. Product Price (Catalog Subtotal), Admin Discounts & Customer Order Price
        // ---------------------------------------------------------------------
        // 1. Resolve line item subtotals and totals directly from this order
        $line_subtotal = 0.0;
        $line_total    = 0.0;

        foreach ( $order->get_items() as $item ) {
            $line_subtotal += (float) $item->get_subtotal();
            $line_total    += (float) $item->get_total();
        }

        // 2. Line item level discount
        $line_discount = max( 0.0, round( $line_subtotal - $line_total, 2 ) );

        // 3. Direct order discounts and coupon line items
        $order_discount = (float) $order->get_discount_total();
        $coupon_disc    = 0.0;
        foreach ( $order->get_items( 'coupon' ) as $c_item ) {
            $coupon_disc += (float) $c_item->get_discount();
        }

        // 4. WCFM marketplace orders discount column
        $wcfm_discount = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(discount_amount) FROM {$wpdb->prefix}wcfm_marketplace_orders WHERE order_id = %d AND vendor_id = %d",
            $order_id,
            $vendor_id
        ) );

        // 5. Postmeta coupon/cart discount fallbacks
        $meta_discount = (float) $order->get_meta( '_order_total_discount' );
        if ( $meta_discount <= 0 ) {
            $meta_discount = (float) $order->get_meta( '_cart_discount' );
        }

        // Determine total Admin Discount applied to this vendor's order
        $discount_total = max(
            $line_discount,
            $order_discount,
            $coupon_disc,
            $wcfm_discount,
            $meta_discount
        );
        $discount_total = round( $discount_total, 2 );

        // Extract applied coupon codes
        $coupon_codes_arr = $order->get_coupon_codes();
        if ( empty( $coupon_codes_arr ) ) {
            foreach ( (array) $order->get_items( 'coupon' ) as $ci ) {
                if ( is_object( $ci ) && method_exists( $ci, 'get_code' ) && $ci->get_code() ) {
                    $coupon_codes_arr[] = $ci->get_code();
                }
            }
        }
        $coupon_codes_str = ! empty( $coupon_codes_arr ) ? implode( ', ', array_unique( array_filter( $coupon_codes_arr ) ) ) : '';

        // Resolve item_subtotal (Product Catalog Price before discount) & customer_items_paid (Post-discount product total)
        $item_subtotal       = 0.0;
        $customer_items_paid = 0.0;

        if ( $line_subtotal > 0 ) {
            if ( $line_subtotal > $line_total ) {
                // Line items properly store pre-discount subtotal and post-discount total
                $item_subtotal       = $line_subtotal;
                $customer_items_paid = $line_total;
            } elseif ( $discount_total > 0 ) {
                // Line subtotal and total are equal, but an order discount exists
                $customer_items_paid = $line_total;
                $item_subtotal       = round( $customer_items_paid + $discount_total, 2 );
            } else {
                $item_subtotal       = $line_subtotal;
                $customer_items_paid = $line_total;
            }
        } else {
            // Check WCFM marketplace orders table
            $wcfm_item_sum = (float) $wpdb->get_var( $wpdb->prepare(
                "SELECT SUM(item_total) FROM {$wpdb->prefix}wcfm_marketplace_orders WHERE order_id = %d AND vendor_id = %d",
                $order_id,
                $vendor_id
            ) );
            if ( $wcfm_item_sum > 0 ) {
                if ( $discount_total > 0 ) {
                    $customer_items_paid = $wcfm_item_sum;
                    $item_subtotal       = round( $wcfm_item_sum + $discount_total, 2 );
                } else {
                    $item_subtotal       = $wcfm_item_sum;
                    $customer_items_paid = $wcfm_item_sum;
                }
            } else {
                $derived_price = max( 0.0, (float) $order->get_total() - (float) $order->get_shipping_total() - (float) $order->get_total_tax() );
                if ( $discount_total > 0 ) {
                    $customer_items_paid = $derived_price;
                    $item_subtotal       = round( $derived_price + $discount_total, 2 );
                } else {
                    $item_subtotal       = $derived_price;
                    $customer_items_paid = $derived_price;
                }
            }
        }

        $item_subtotal       = round( $item_subtotal, 2 );
        $customer_items_paid = round( $customer_items_paid, 2 );
        $gross_sales         = $customer_items_paid; // Post-discount product total paid by customer

        // ---------------------------------------------------------------------
        // B. Shipping, Taxes & Total Incoming Inflow
        // ---------------------------------------------------------------------
        $shipping_charge = round( (float) $order->get_shipping_total(), 2 );
        if ( $shipping_charge <= 0 ) {
            $wcfm_shipping = $wpdb->get_var( $wpdb->prepare(
                "SELECT SUM(shipping) FROM {$wpdb->prefix}wcfm_marketplace_orders WHERE order_id = %d AND vendor_id = %d",
                $order_id,
                $vendor_id
            ) );
            if ( $wcfm_shipping && (float) $wcfm_shipping > 0 ) {
                $shipping_charge = round( (float) $wcfm_shipping, 2 );
            }
        }
        $tax_amount     = round( (float) $order->get_total_tax(), 2 );
        $total_incoming = round( $gross_sales + $shipping_charge + $tax_amount, 2 );

        // ---------------------------------------------------------------------
        // B.2. Customer Refunds
        // ---------------------------------------------------------------------
        $refunded_amount = 0.0;
        
        // 1. Check WooCommerce native refunds
        $wc_refund = abs( (float) $order->get_total_refunded() );
        if ( $wc_refund > 0 ) {
            $refunded_amount += $wc_refund;
        }

        // 2. Check WCFM marketplace refund requests / orders table
        $wcfm_refund = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(refunded_amount) FROM {$wpdb->prefix}wcfm_marketplace_refund_request 
             WHERE order_id = %d AND vendor_id = %d AND refund_status IN ('completed', 'approved')",
            $order_id,
            $vendor_id
        ) );
        if ( $wcfm_refund > 0 ) {
            $refunded_amount = max( $refunded_amount, $wcfm_refund );
        }

        $wcfm_orders_refund = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(refunded_amount) FROM {$wpdb->prefix}wcfm_marketplace_orders 
             WHERE order_id = %d AND vendor_id = %d",
            $order_id,
            $vendor_id
        ) );
        if ( $wcfm_orders_refund > 0 ) {
            $refunded_amount = max( $refunded_amount, $wcfm_orders_refund );
        }

        $refunded_amount = round( min( $total_incoming, $refunded_amount ), 2 );
        $net_incoming    = max( 0.0, round( $total_incoming - $refunded_amount, 2 ) );

        // ---------------------------------------------------------------------
        // C. Platform Commission & 18% GST Tax on Commission
        // ---------------------------------------------------------------------
        $commission_deducted_raw = self::get_wcfm_commission( $vendor_id, $order_id, $order, false );
        
        $commission_rate = 0.0;
        if ( $item_subtotal > 0 ) {
            $commission_rate = round( ( $commission_deducted_raw / $item_subtotal ) * 100, 2 );
        }

        // Net catalog price after refund deduction
        $refund_item_portion = min( $item_subtotal, $refunded_amount );
        $net_catalog_price   = max( 0.0, round( $item_subtotal - $refund_item_portion, 2 ) );

        $is_full_refund = ( 'refunded' === strtolower( (string) $order->get_status() ) ) || 
                          ( $refunded_amount >= $total_incoming && $total_incoming > 0 );

        if ( $is_full_refund || $net_catalog_price <= 0 ) {
            $commission_deducted = 0.00;
        } elseif ( $refunded_amount > 0 ) {
            if ( $commission_rate > 0 ) {
                $commission_deducted = round( $net_catalog_price * ( $commission_rate / 100 ), 2 );
            } else {
                $commission_deducted = max( 0.0, round( $commission_deducted_raw - round( $refund_item_portion * ( $commission_rate / 100 ), 2 ), 2 ) );
            }
        } else {
            $commission_deducted = $commission_deducted_raw;
        }

        // 18% GST on Platform Commission
        $commission_tax_rate = (float) get_option( 'thaaniyamhub_commission_tax_rate', 18.00 );
        $commission_tax      = round( $commission_deducted * ( $commission_tax_rate / 100 ), 2 );

        // ---------------------------------------------------------------------
        // D. Vendor Net Payout
        // ---------------------------------------------------------------------
        $vendor_get_shipping = false;
        if ( isset( $WCFMmp ) && $WCFMmp && isset( $WCFMmp->wcfmmp_vendor ) && method_exists( $WCFMmp->wcfmmp_vendor, 'is_vendor_get_shipping' ) ) {
            $vendor_get_shipping = $WCFMmp->wcfmmp_vendor->is_vendor_get_shipping( $vendor_id );
        } else {
            $global_options      = get_option( 'wcfm_commission_options', [] );
            $vendor_get_shipping = isset( $global_options['get_shipping'] ) && 'yes' === $global_options['get_shipping'];
        }

        $shipping_model = get_option( 'thaaniyamhub_financial_shipping_model', 'admin_retains' );

        if ( $is_full_refund ) {
            $vendor_net_payout = 0.00;
        } else {
            $vendor_product_share = max( 0.0, round( $net_catalog_price - $commission_deducted, 2 ) );

            if ( 'vendor_delivers' === $shipping_model || $vendor_get_shipping ) {
                $vendor_net_payout = round( $vendor_product_share + $shipping_charge, 2 );
            } else {
                $vendor_net_payout = $vendor_product_share;
            }
            $vendor_net_payout = max( 0.0, $vendor_net_payout );
        }

        // ---------------------------------------------------------------------
        // E. Shiprocket Actual Shipping Cost & AWB Tracking
        // ---------------------------------------------------------------------
        $shiprocket_shipping_cost = 0.0;
        $shiprocket_courier_name  = '';
        $shiprocket_awb           = '';

        // Check order meta
        $meta_sr_cost = $order->get_meta( '_shiprocket_actual_shipping_cost' );
        if ( '' !== $meta_sr_cost && null !== $meta_sr_cost ) {
            $shiprocket_shipping_cost = (float) $meta_sr_cost;
        }

        // Check local fulfillment table
        $fulfillment = $wpdb->get_row( $wpdb->prepare(
            "SELECT awb_code, courier_name FROM {$wpdb->prefix}thaaniyamhub_shiprocket_fulfillment WHERE sub_order_id = %d LIMIT 1",
            $order_id
        ) );
        if ( $fulfillment ) {
            $shiprocket_awb          = $fulfillment->awb_code ?: '';
            $shiprocket_courier_name = $fulfillment->courier_name ?: '';
        }

        // Fallback to order meta for courier / AWB
        if ( empty( $shiprocket_awb ) ) {
            $shiprocket_awb = (string) $order->get_meta( '_shiprocket_awb' );
        }
        if ( empty( $shiprocket_courier_name ) ) {
            $shiprocket_courier_name = (string) $order->get_meta( '_shiprocket_selected_courier_name' );
        }

        if ( $shiprocket_shipping_cost <= 0 && 'admin_retains' === $shipping_model ) {
            $shiprocket_shipping_cost = $shipping_charge; // baseline estimate
        }

        // ---------------------------------------------------------------------
        // F. Payment Gateway & Other Service Costs (Cashfree)
        // ---------------------------------------------------------------------
        $payment_method = $order->get_payment_method();
        $is_cod         = ( 'cod' === strtolower( (string) $payment_method ) );

        $gateway_fee        = 0.0;
        $gateway_tax        = 0.0;
        $other_service_cost = 0.0;

        if ( ! $is_cod && $total_incoming > 0 ) {
            $pg_fee_pct  = (float) get_option( 'thaaniyamhub_cashfree_pg_fee_percent', 2.00 );
            $pg_fixed    = (float) get_option( 'thaaniyamhub_cashfree_pg_fixed_fee', 0.00 );
            $pg_gst_pct  = (float) get_option( 'thaaniyamhub_cashfree_pg_gst_percent', 18.00 );

            $gateway_fee = round( ( $total_incoming * ( $pg_fee_pct / 100 ) ) + $pg_fixed, 2 );
            $gateway_tax = round( $gateway_fee * ( $pg_gst_pct / 100 ), 2 );
        }

        // Cashfree Payout Transfer Fee
        if ( $vendor_net_payout > 0 ) {
            $payout_fee_base    = (float) get_option( 'thaaniyamhub_cashfree_payout_fee', 2.50 );
            $payout_gst_pct     = (float) get_option( 'thaaniyamhub_cashfree_payout_gst_percent', 18.00 );
            $other_service_cost = round( $payout_fee_base + ( $payout_fee_base * ( $payout_gst_pct / 100 ) ), 2 );
        } else {
            $other_service_cost = 0.00;
        }

        // ---------------------------------------------------------------------
        // G. Total Outgoing, Net Platform Profit & Margins
        // ---------------------------------------------------------------------
        $total_outgoing = round(
            $vendor_net_payout +
            $shiprocket_shipping_cost +
            $commission_tax +
            $gateway_fee +
            $gateway_tax +
            $other_service_cost,
            2
        );

        $net_profit = round( $net_incoming - $total_outgoing, 2 );
        $profit_margin = ( $net_incoming > 0 ) ? round( ( $net_profit / $net_incoming ) * 100, 2 ) : 0.00;

        // ---------------------------------------------------------------------
        // H. Customer Details & Order Status
        // ---------------------------------------------------------------------
        $customer_name = trim( (string) $order->get_formatted_billing_full_name() );
        if ( empty( $customer_name ) ) {
            $customer_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
        }

        $city          = $order->get_shipping_city() ?: $order->get_billing_city();
        $postcode      = $order->get_shipping_postcode() ?: $order->get_billing_postcode();
        $customer_city = trim( $city . ( $postcode ? ' - ' . $postcode : '' ) );

        $order_status = $order->get_status();

        // Check existing payout status
        $existing_payout_status = $wpdb->get_var( $wpdb->prepare(
            "SELECT payout_status FROM {$wpdb->prefix}thaaniyamhub_vendor_ledger WHERE sub_order_id = %d AND vendor_id = %d LIMIT 1",
            $order_id,
            $vendor_id
        ) );
        $payout_status = $existing_payout_status ?: 'pending';
        if ( $is_full_refund && ( empty( $existing_payout_status ) || 'pending' === $existing_payout_status ) ) {
            $payout_status = 'refunded';
        }

        $order_date = $order->get_date_created();
        $created_at = $order_date ? $order_date->date( 'Y-m-d H:i:s' ) : current_time( 'mysql' );

        // ---------------------------------------------------------------------
        // I. Upsert into Ledger Table
        // ---------------------------------------------------------------------
        $table = $wpdb->prefix . 'thaaniyamhub_vendor_ledger';
        $data = [
            'parent_order_id'          => $order_id,
            'sub_order_id'             => $order_id,
            'vendor_id'                => $vendor_id,
            'item_subtotal'            => $item_subtotal,
            'discount_total'           => $discount_total,
            'coupon_codes'             => $coupon_codes_str,
            'gross_sales'              => $gross_sales,
            'shipping_charge'          => $shipping_charge,
            'tax_amount'               => $tax_amount,
            'refunded_amount'          => $refunded_amount,
            'total_incoming'           => $total_incoming,
            'commission_rate'          => $commission_rate,
            'commission_deducted'      => $commission_deducted,
            'commission_tax'           => $commission_tax,
            'vendor_net_payout'        => $vendor_net_payout,
            'shiprocket_shipping_cost' => $shiprocket_shipping_cost,
            'shiprocket_courier_name'  => $shiprocket_courier_name,
            'shiprocket_awb'           => $shiprocket_awb,
            'gateway_fee'              => $gateway_fee,
            'gateway_tax'              => $gateway_tax,
            'other_service_cost'       => $other_service_cost,
            'total_outgoing'           => $total_outgoing,
            'net_profit'               => $net_profit,
            'profit_margin'            => $profit_margin,
            'payment_method'           => (string) $payment_method,
            'payout_status'            => $payout_status,
            'order_status'             => $order_status,
            'customer_name'            => $customer_name,
            'customer_city'            => $customer_city,
            'created_at'               => $created_at,
        ];

        $formats = [
            '%d', '%d', '%d',
            '%f', '%f', '%s',
            '%f', '%f', '%f', '%f', '%f',
            '%f', '%f', '%f', '%f',
            '%f', '%s', '%s',
            '%f', '%f', '%f', '%f',
            '%f', '%f',
            '%s', '%s', '%s', '%s', '%s',
            '%s',
        ];

        $result = $wpdb->replace( $table, $data, $formats );

        if ( false !== $result ) {
            thaaniyamhub_log(
                sprintf(
                    'ThaaniyamHub_Ledger: Upserted Order #%d | Vendor #%d | Inflow: ₹%s (Refund: ₹%s, Net: ₹%s) | Commission: ₹%s (+GST ₹%s) | Payout: ₹%s | SR Freight: ₹%s | PG Cost: ₹%s | Outflow: ₹%s | Net Profit: ₹%s (%s%%)',
                    $order_id,
                    $vendor_id,
                    $total_incoming,
                    $refunded_amount,
                    $net_incoming,
                    $commission_deducted,
                    $commission_tax,
                    $vendor_net_payout,
                    $shiprocket_shipping_cost,
                    $gateway_fee + $gateway_tax,
                    $total_outgoing,
                    $net_profit,
                    $profit_margin
                )
            );
            return true;
        } else {
            thaaniyamhub_log( 'ThaaniyamHub_Ledger: DB replace failed — ' . $wpdb->last_error, 'error' );
            return false;
        }
    }

    /**
     * Dynamically update Shiprocket logistics cost and recompute financial totals.
     *
     * @param int    $order_id      Sub-order ID or parent order ID.
     * @param float  $shipping_cost Actual courier cost from Shiprocket.
     * @param string $courier_name  Name of the assigned courier.
     * @param string $awb           Assigned AWB tracking number.
     * @return bool
     */
    public static function update_shiprocket_cost( int $order_id, float $shipping_cost, string $courier_name = '', string $awb = '' ): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'thaaniyamhub_vendor_ledger';

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE sub_order_id = %d OR parent_order_id = %d",
            $order_id,
            $order_id
        ) );

        if ( empty( $rows ) ) {
            return false;
        }

        foreach ( $rows as $row ) {
            $updated_cost = max( 0.0, round( $shipping_cost, 2 ) );
            $c_name       = $courier_name ?: $row->shiprocket_courier_name;
            $awb_code     = $awb ?: $row->shiprocket_awb;

            $total_outgoing = round(
                (float) $row->vendor_net_payout +
                $updated_cost +
                (float) $row->commission_tax +
                (float) $row->gateway_fee +
                (float) $row->gateway_tax +
                (float) $row->other_service_cost,
                2
            );

            $total_incoming = (float) $row->total_incoming;
            $net_profit     = round( $total_incoming - $total_outgoing, 2 );
            $profit_margin  = ( $total_incoming > 0 ) ? round( ( $net_profit / $total_incoming ) * 100, 2 ) : 0.00;

            $wpdb->update(
                $table,
                [
                    'shiprocket_shipping_cost' => $updated_cost,
                    'shiprocket_courier_name'  => $c_name,
                    'shiprocket_awb'           => $awb_code,
                    'total_outgoing'           => $total_outgoing,
                    'net_profit'               => $net_profit,
                    'profit_margin'            => $profit_margin,
                ],
                [ 'id' => $row->id ],
                [ '%f', '%s', '%s', '%f', '%f', '%f' ],
                [ '%d' ]
            );

            thaaniyamhub_log(
                sprintf(
                    'ThaaniyamHub_Ledger: Updated Shiprocket actual cost for Order #%d (Ledger ID %d) -> Cost: ₹%s, Courier: %s, AWB: %s | New Profit: ₹%s (%s%%)',
                    $order_id,
                    $row->id,
                    $updated_cost,
                    $c_name,
                    $awb_code,
                    $net_profit,
                    $profit_margin
                )
            );
        }

        return true;
    }

    /**
     * Hook listener: Synchronize WooCommerce order status changes to the ledger.
     *
     * @param int      $order_id
     * @param string   $old_status
     * @param string   $new_status
     */
    public static function on_order_status_changed( $order_id, $old_status, $new_status )
    {
        global $wpdb;
        $table = $wpdb->prefix . 'thaaniyamhub_vendor_ledger';

        if ( in_array( $new_status, [ 'trash', 'wc-trash' ], true ) ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$table} SET order_status = 'trash' WHERE sub_order_id = %d OR parent_order_id = %d",
                $order_id,
                $order_id
            ) );
        } elseif ( 'refunded' === $new_status || 'wc-refunded' === $new_status ) {
            self::recalculate_order_ledger( (int) $order_id );
        } else {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$table} SET order_status = %s WHERE sub_order_id = %d OR parent_order_id = %d",
                $new_status,
                $order_id,
                $order_id
            ) );
        }
    }

    /**
     * Hook listener: Automatically recalculate ledger when a refund is created.
     *
     * @param int $order_id
     * @param int $refund_id
     */
    public static function on_order_refunded( $order_id, $refund_id )
    {
        self::recalculate_order_ledger( (int) $order_id );
    }

    /**
     * Hook listener: Automatically recalculate ledger when a refund is deleted.
     *
     * @param int $refund_id
     * @param int $order_id
     */
    public static function on_refund_deleted( $refund_id, $order_id )
    {
        self::recalculate_order_ledger( (int) $order_id );
    }

    /**
     * Hook listener: Recalculate ledger on WCFM refund status change.
     */
    public static function on_wcfm_refund_updated( $refund_id, $order_id, $status = '' )
    {
        self::recalculate_order_ledger( (int) $order_id );
    }

    /**
     * Core helper: Recalculate all ledger entries for a parent order or sub-order.
     *
     * @param int $order_id
     */
    public static function recalculate_order_ledger( int $order_id )
    {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $vendor_id = (int) $order->get_meta( '_order_vendor_id' );
        if ( ! $vendor_id ) {
            foreach ( $order->get_items() as $item ) {
                if ( function_exists( 'wcfm_get_vendor_id_by_post' ) ) {
                    $vendor_id = (int) wcfm_get_vendor_id_by_post( $item->get_product_id() );
                }
                if ( ! $vendor_id ) {
                    $vendor_id = (int) get_post_field( 'post_author', $item->get_product_id() );
                }
                if ( $vendor_id > 0 ) {
                    break;
                }
            }
        }

        if ( $vendor_id > 0 ) {
            self::record_from_suborder( $order, $vendor_id );
        }
    }

    /**
     * Hook listener: Mark order as trashed in ledger.
     */
    public static function on_order_trashed( $order_id )
    {
        global $wpdb;
        $table = $wpdb->prefix . 'thaaniyamhub_vendor_ledger';
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$table} SET order_status = 'trash' WHERE sub_order_id = %d OR parent_order_id = %d",
            (int) $order_id,
            (int) $order_id
        ) );
    }

    /**
     * Hook listener: Restore order status when untrashed.
     */
    public static function on_order_untrashed( $order_id )
    {
        $order = wc_get_order( (int) $order_id );
        if ( $order ) {
            $status = $order->get_status();
            global $wpdb;
            $table = $wpdb->prefix . 'thaaniyamhub_vendor_ledger';
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$table} SET order_status = %s WHERE sub_order_id = %d OR parent_order_id = %d",
                $status,
                (int) $order_id,
                (int) $order_id
            ) );
        }
    }

    /**
     * Hook listener: Remove ledger entry when order is permanently deleted.
     */
    public static function on_order_deleted( $order_id )
    {
        global $wpdb;
        $table = $wpdb->prefix . 'thaaniyamhub_vendor_ledger';
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$table} WHERE sub_order_id = %d OR parent_order_id = %d",
            (int) $order_id,
            (int) $order_id
        ) );
    }

    /**
     * Mark a ledger row as disbursed (called from payout flow).
     *
     * @param int $sub_order_id
     * @return bool
     */
    public static function mark_disbursed( int $sub_order_id ): bool
    {
        global $wpdb;
        $rows = $wpdb->update(
            $wpdb->prefix . 'thaaniyamhub_vendor_ledger',
            [ 'payout_status' => 'disbursed' ],
            [ 'sub_order_id' => $sub_order_id ],
            [ '%s' ],
            [ '%d' ]
        );
        return false !== $rows;
    }

    // =========================================================================
    // 2. QUERY & REPORTING API
    // =========================================================================

    /**
     * High performance multi-filter query for ledger records.
     *
     * @param array $args
     * @return array [ 'rows' => stdClass[], 'total_count' => int, 'total_pages' => int ]
     */
    public static function query_ledger( array $args = [] ): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'thaaniyamhub_vendor_ledger';

        $defaults = [
            'vendor_id'     => 0,
            'search'        => '',
            'from'          => '',
            'to'            => '',
            'order_status'  => '',
            'payout_status' => '',
            'profitability' => 'all',
            'orderby'       => 'sub_order_id',
            'order'         => 'DESC',
            'limit'         => 50,
            'paged'         => 1,
        ];
        $params = wp_parse_args( $args, $defaults );

        $where_clauses = [ '1=1' ];

        // 1. Vendor Filter
        if ( ! empty( $params['vendor_id'] ) ) {
            $where_clauses[] = $wpdb->prepare( 'vendor_id = %d', (int) $params['vendor_id'] );
        }

        // 2. Search Term (Order ID, Sub-order ID, Customer, Coupon, AWB)
        if ( ! empty( $params['search'] ) ) {
            $s = '%' . $wpdb->esc_like( trim( $params['search'] ) ) . '%';
            $where_clauses[] = $wpdb->prepare(
                '(parent_order_id LIKE %s OR sub_order_id LIKE %s OR customer_name LIKE %s OR customer_city LIKE %s OR coupon_codes LIKE %s OR shiprocket_awb LIKE %s OR shiprocket_courier_name LIKE %s)',
                $s, $s, $s, $s, $s, $s, $s
            );
        }

        // 3. Date Range
        if ( ! empty( $params['from'] ) ) {
            $where_clauses[] = $wpdb->prepare( 'created_at >= %s', $params['from'] . ' 00:00:00' );
        }
        if ( ! empty( $params['to'] ) ) {
            $where_clauses[] = $wpdb->prepare( 'created_at <= %s', $params['to'] . ' 23:59:59' );
        }

        // 4. Order Status — Exclude trash / deleted orders
        if ( ! empty( $params['order_status'] ) && 'all' !== $params['order_status'] ) {
            $where_clauses[] = $wpdb->prepare( 'order_status = %s', $params['order_status'] );
        } else {
            $where_clauses[] = "order_status NOT IN ('trash', 'wc-trash', 'auto-draft', 'draft')";
        }

        // Exclude trashed / deleted posts from wp_posts
        $where_clauses[] = "parent_order_id NOT IN (SELECT ID FROM {$wpdb->posts} WHERE post_status IN ('trash', 'auto-draft'))";

        // 5. Payout Status
        if ( ! empty( $params['payout_status'] ) && 'all' !== $params['payout_status'] ) {
            $where_clauses[] = $wpdb->prepare( 'payout_status = %s', $params['payout_status'] );
        }

        // 6. Profitability
        if ( 'profitable' === $params['profitability'] ) {
            $where_clauses[] = 'net_profit >= 0';
        } elseif ( 'loss' === $params['profitability'] ) {
            $where_clauses[] = 'net_profit < 0';
        }

        $where_sql = implode( ' AND ', $where_clauses );

        // Total Count
        $total_count = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table} WHERE {$where_sql}" );

        // Sorting & Pagination — Default by Order ID DESC
        $allowed_order_by = [
            'id',
            'created_at',
            'date',
            'order_date',
            'parent_order_id',
            'order_id',
            'sub_order_id',
            'item_subtotal',
            'discount_total',
            'gross_sales',
            'total_incoming',
            'commission_deducted',
            'commission_tax',
            'vendor_net_payout',
            'shiprocket_shipping_cost',
            'net_profit',
            'profit_margin',
        ];
        $raw_orderby = $params['orderby'] ?? 'sub_order_id';
        $orderby     = in_array( $raw_orderby, $allowed_order_by, true ) ? $raw_orderby : 'sub_order_id';
        $order       = ( 'ASC' === strtoupper( (string) ( $params['order'] ?? 'DESC' ) ) ) ? 'ASC' : 'DESC';

        if ( in_array( $orderby, [ 'sub_order_id', 'order_id', 'parent_order_id' ], true ) ) {
            $order_sql = "sub_order_id {$order}, parent_order_id {$order}, id {$order}";
        } elseif ( in_array( $orderby, [ 'created_at', 'date', 'order_date' ], true ) ) {
            $order_sql = "created_at {$order}, sub_order_id {$order}, parent_order_id {$order}, id {$order}";
        } elseif ( 'id' === $orderby ) {
            $order_sql = "sub_order_id {$order}, id {$order}";
        } else {
            $order_sql = "{$orderby} {$order}, sub_order_id DESC, id DESC";
        }

        $limit  = max( 1, (int) $params['limit'] );
        $paged  = max( 1, (int) $params['paged'] );
        $offset = ( $paged - 1 ) * $limit;

        $rows = $wpdb->get_results(
            "SELECT * FROM {$table}
             WHERE {$where_sql}
             ORDER BY {$order_sql}
             LIMIT {$limit} OFFSET {$offset}"
        );

        $total_pages = ( $limit > 0 ) ? (int) ceil( $total_count / $limit ) : 1;

        return [
            'rows'        => is_array( $rows ) ? $rows : [],
            'total_count' => $total_count,
            'total_pages' => $total_pages,
            'paged'       => $paged,
        ];
    }

    /**
     * Compute real-time aggregate KPI totals matching the active filter criteria.
     *
     * @param array $args
     * @return array
     */
    public static function get_financial_summary( array $args = [] ): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'thaaniyamhub_vendor_ledger';

        $defaults = [
            'vendor_id'     => 0,
            'search'        => '',
            'from'          => '',
            'to'            => '',
            'order_status'  => '',
            'payout_status' => '',
            'profitability' => 'all',
        ];
        $params = wp_parse_args( $args, $defaults );

        $where_clauses = [ '1=1' ];

        if ( ! empty( $params['vendor_id'] ) ) {
            $where_clauses[] = $wpdb->prepare( 'vendor_id = %d', (int) $params['vendor_id'] );
        }
        if ( ! empty( $params['search'] ) ) {
            $s = '%' . $wpdb->esc_like( trim( $params['search'] ) ) . '%';
            $where_clauses[] = $wpdb->prepare(
                '(parent_order_id LIKE %s OR sub_order_id LIKE %s OR customer_name LIKE %s OR customer_city LIKE %s OR coupon_codes LIKE %s OR shiprocket_awb LIKE %s OR shiprocket_courier_name LIKE %s)',
                $s, $s, $s, $s, $s, $s, $s
            );
        }
        if ( ! empty( $params['from'] ) ) {
            $where_clauses[] = $wpdb->prepare( 'created_at >= %s', $params['from'] . ' 00:00:00' );
        }
        if ( ! empty( $params['to'] ) ) {
            $where_clauses[] = $wpdb->prepare( 'created_at <= %s', $params['to'] . ' 23:59:59' );
        }
        if ( ! empty( $params['order_status'] ) && 'all' !== $params['order_status'] ) {
            $where_clauses[] = $wpdb->prepare( 'order_status = %s', $params['order_status'] );
        } else {
            $where_clauses[] = "order_status NOT IN ('trash', 'wc-trash', 'auto-draft', 'draft')";
        }

        // Exclude trashed / deleted posts from wp_posts
        $where_clauses[] = "parent_order_id NOT IN (SELECT ID FROM {$wpdb->posts} WHERE post_status IN ('trash', 'auto-draft'))";
        if ( ! empty( $params['payout_status'] ) && 'all' !== $params['payout_status'] ) {
            $where_clauses[] = $wpdb->prepare( 'payout_status = %s', $params['payout_status'] );
        }
        if ( 'profitable' === $params['profitability'] ) {
            $where_clauses[] = 'net_profit >= 0';
        } elseif ( 'loss' === $params['profitability'] ) {
            $where_clauses[] = 'net_profit < 0';
        }

        $where_sql = implode( ' AND ', $where_clauses );

        $summary = $wpdb->get_row(
            "SELECT
                COUNT(id)                             AS order_count,
                COALESCE(SUM(item_subtotal), 0)       AS total_subtotal,
                COALESCE(SUM(discount_total), 0)      AS total_discounts,
                COALESCE(SUM(gross_sales), 0)         AS total_gross_sales,
                COALESCE(SUM(shipping_charge), 0)     AS total_customer_shipping,
                COALESCE(SUM(tax_amount), 0)          AS total_taxes,
                COALESCE(SUM(refunded_amount), 0)     AS total_refunds,
                COALESCE(SUM(total_incoming), 0)      AS total_incoming,
                COALESCE(SUM(commission_deducted), 0) AS total_commission,
                COALESCE(SUM(commission_tax), 0)      AS total_commission_tax,
                COALESCE(SUM(vendor_net_payout), 0)   AS total_vendor_payout,
                COALESCE(SUM(shiprocket_shipping_cost), 0) AS total_shiprocket_cost,
                COALESCE(SUM(gateway_fee), 0)         AS total_gateway_fee,
                COALESCE(SUM(gateway_tax), 0)         AS total_gateway_tax,
                COALESCE(SUM(other_service_cost), 0)  AS total_other_costs,
                COALESCE(SUM(total_outgoing), 0)      AS total_outgoing,
                COALESCE(SUM(net_profit), 0)          AS total_net_profit
             FROM {$table}
             WHERE {$where_sql}"
        );

        $total_inflow   = (float) ( $summary->total_incoming ?? 0 );
        $total_refunds  = (float) ( $summary->total_refunds ?? 0 );
        $net_inflow     = max( 0.0, round( $total_inflow - $total_refunds, 2 ) );
        $total_profit   = (float) ( $summary->total_net_profit ?? 0 );
        $overall_margin = ( $net_inflow > 0 ) ? round( ( $total_profit / $net_inflow ) * 100, 2 ) : 0.00;

        return [
            'order_count'             => (int) ( $summary->order_count ?? 0 ),
            'total_subtotal'          => (float) ( $summary->total_subtotal ?? 0 ),
            'total_discounts'         => (float) ( $summary->total_discounts ?? 0 ),
            'total_gross_sales'       => (float) ( $summary->total_gross_sales ?? 0 ),
            'total_customer_shipping' => (float) ( $summary->total_customer_shipping ?? 0 ),
            'total_taxes'             => (float) ( $summary->total_taxes ?? 0 ),
            'total_refunds'           => $total_refunds,
            'total_incoming'          => $total_inflow,
            'total_net_incoming'      => $net_inflow,
            'total_commission'        => (float) ( $summary->total_commission ?? 0 ),
            'total_commission_tax'    => (float) ( $summary->total_commission_tax ?? 0 ),
            'total_vendor_payout'     => (float) ( $summary->total_vendor_payout ?? 0 ),
            'total_shiprocket_cost'   => (float) ( $summary->total_shiprocket_cost ?? 0 ),
            'total_gateway_fee'       => (float) ( $summary->total_gateway_fee ?? 0 ),
            'total_gateway_tax'       => (float) ( $summary->total_gateway_tax ?? 0 ),
            'total_other_costs'       => (float) ( $summary->total_other_costs ?? 0 ),
            'total_outgoing'          => (float) ( $summary->total_outgoing ?? 0 ),
            'total_net_profit'        => $total_profit,
            'overall_margin'          => $overall_margin,
        ];
    }

    /**
     * Backward-compatible helper for legacy single-vendor report calls.
     */
    public static function get_vendor_ledger( int $vendor_id, string $from = '', string $to = '', int $limit = 0 ): array
    {
        $res = self::query_ledger( [
            'vendor_id' => $vendor_id,
            'from'      => $from,
            'to'        => $to,
            'limit'     => $limit > 0 ? $limit : 500,
        ] );
        return $res['rows'];
    }

    /**
     * Backward-compatible helper for legacy vendor summary calls.
     */
    public static function get_vendor_summary( int $vendor_id, string $cycle_from = '' ): array
    {
        $year_from = date( 'Y' ) . '-01-01';
        $ytd = (object) self::get_financial_summary( [ 'vendor_id' => $vendor_id, 'from' => $year_from ] );
        $cycle = $cycle_from ? (object) self::get_financial_summary( [ 'vendor_id' => $vendor_id, 'from' => $cycle_from ] ) : null;

        return [
            'ytd'   => $ytd,
            'cycle' => $cycle,
        ];
    }

    // =========================================================================
    // 3. COMMISSION RESOLUTION HELPERS
    // =========================================================================

    /**
     * Resolves WCFM commission settings (global, vendor, product, variation).
     *
     * @param int   $product_id
     * @param int   $variation_id
     * @param int   $vendor_id
     * @param float $item_price
     * @param int   $quantity
     * @return array [float $admin_fee, float $vendor_earnings, float $effective_percentage]
     */
    public static function calculate_wcfm_commission_statically( int $product_id, int $variation_id, int $vendor_id, float $item_price, int $quantity = 1 ): array
    {
        $global_options = get_option( 'wcfm_commission_options', [] );
        $commission_for = isset( $global_options['commission_for'] ) ? $global_options['commission_for'] : 'vendor';

        $commission_data = [];
        $resolved        = false;

        // 1. Variation level
        if ( $variation_id ) {
            $data = get_post_meta( $variation_id, '_wcfmmp_commission', true );
            if ( is_array( $data ) && isset( $data['commission_mode'] ) && 'global' !== $data['commission_mode'] ) {
                $commission_data = $data;
                $resolved        = true;
            }
        }

        // 2. Product level
        if ( ! $resolved && $product_id ) {
            $data = get_post_meta( $product_id, '_wcfmmp_commission', true );
            if ( is_array( $data ) && isset( $data['commission_mode'] ) && 'global' !== $data['commission_mode'] ) {
                $commission_data = $data;
                $resolved        = true;
            }
        }

        // 3. Vendor level
        if ( ! $resolved && $vendor_id ) {
            $vendor_data = get_user_meta( $vendor_id, 'wcfmmp_profile_settings', true );
            if ( is_array( $vendor_data ) && isset( $vendor_data['commission'] ) ) {
                $data = $vendor_data['commission'];
                if ( isset( $data['commission_mode'] ) && 'global' !== $data['commission_mode'] && 'percentage' !== $data['commission_mode'] ) {
                    $commission_data = $data;
                    $resolved        = true;
                }
            }
        }

        // 4. Fallback to global options
        if ( ! $resolved ) {
            $commission_data = $global_options;
        }

        $mode = isset( $commission_data['commission_mode'] ) ? $commission_data['commission_mode'] : 'percent';
        if ( 'percentage' === $mode ) {
            $mode = 'percent';
        }
        $percent = isset( $commission_data['commission_percent'] ) ? (float) $commission_data['commission_percent'] : 90.0;
        $fixed   = isset( $commission_data['commission_fixed'] ) ? (float) $commission_data['commission_fixed'] : 0.0;

        $base_commission = 0.0;
        switch ( $mode ) {
            case 'percent':
                $base_commission = $item_price * ( $percent / 100 );
                break;
            case 'fixed':
                $base_commission = $fixed * $quantity;
                break;
            case 'percent_fixed':
                $base_commission = ( $item_price * ( $percent / 100 ) ) + ( $fixed * $quantity );
                break;
            default:
                if ( $percent > 0 ) {
                    $base_commission = $item_price * ( $percent / 100 );
                }
                break;
        }

        $base_commission = max( 0.0, min( $item_price, $base_commission ) );

        if ( 'admin' === $commission_for ) {
            $admin_fee       = $base_commission;
            $vendor_earnings = max( 0.0, $item_price - $admin_fee );
        } else {
            $vendor_earnings = $base_commission;
            $admin_fee       = max( 0.0, $item_price - $vendor_earnings );
        }

        $effective_percentage = ( $item_price > 0 ) ? round( ( $admin_fee / $item_price ) * 100, 2 ) : 0.0;

        return [
            'admin_fee'            => round( $admin_fee, 2 ),
            'vendor_earnings'      => round( $vendor_earnings, 2 ),
            'effective_percentage' => $effective_percentage,
        ];
    }

    /**
     * Resolve the platform commission deducted for a vendor on a given order.
     *
     * @param int      $vendor_id
     * @param int      $order_id
     * @param WC_Order $order
     * @param bool     $deduct_discount
     * @return float
     */
    private static function get_wcfm_commission( int $vendor_id, int $order_id, WC_Order $order, bool $deduct_discount = false ): float
    {
        global $wpdb, $WCFMmp;

        // Priority 1: WCFM marketplace orders table
        $wcfm_row = $wpdb->get_row( $wpdb->prepare(
            "SELECT SUM(commission_amount) AS vendor_earnings, SUM(item_total) AS item_total_sum
             FROM {$wpdb->prefix}wcfm_marketplace_orders
             WHERE vendor_id = %d AND order_id = %d",
            $vendor_id,
            $order_id
        ) );

        if ( $wcfm_row && null !== $wcfm_row->vendor_earnings ) {
            $vendor_earnings = (float) $wcfm_row->vendor_earnings;
            $item_total_sum  = (float) $wcfm_row->item_total_sum;

            $vendor_get_shipping = false;
            if ( isset( $WCFMmp ) && $WCFMmp && isset( $WCFMmp->wcfmmp_vendor ) && method_exists( $WCFMmp->wcfmmp_vendor, 'is_vendor_get_shipping' ) ) {
                $vendor_get_shipping = $WCFMmp->wcfmmp_vendor->is_vendor_get_shipping( $vendor_id );
            } else {
                $global_options      = get_option( 'wcfm_commission_options', [] );
                $vendor_get_shipping = isset( $global_options['get_shipping'] ) && 'yes' === $global_options['get_shipping'];
            }

            if ( $vendor_get_shipping ) {
                $shipping_charge = round( (float) $order->get_shipping_total(), 2 );
                $vendor_earnings = max( 0.0, $vendor_earnings - $shipping_charge );
            }

            return round( max( 0.00, $item_total_sum - $vendor_earnings ), 2 );
        }

        // Priority 2: WCFM dynamic commission calculator API
        $total_commission = 0.0;
        foreach ( $order->get_items() as $item ) {
            $product_id   = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $quantity     = $item->get_quantity();
            $base_price   = $deduct_discount ? (float) $item->get_total() : (float) $item->get_subtotal();

            if ( isset( $WCFMmp ) && $WCFMmp && isset( $WCFMmp->wcfmmp_commission ) && isset( $WCFMmp->wcfmmp_product ) ) {
                $rule            = $WCFMmp->wcfmmp_product->wcfmmp_get_product_commission_rule( $product_id, $variation_id, $vendor_id, $base_price, $quantity, $order_id );
                $vendor_earnings = (float) $WCFMmp->wcfmmp_commission->wcfmmp_get_order_item_commission( $order_id, $vendor_id, $product_id, $variation_id, $base_price, $quantity, $rule );
                $total_commission += max( 0.00, $base_price - $vendor_earnings );
            } else {
                $fallback = self::calculate_wcfm_commission_statically( $product_id, $variation_id, $vendor_id, $base_price, $quantity );
                $total_commission += $fallback['admin_fee'];
            }
        }

        return round( $total_commission, 2 );
    }

    // =========================================================================
    // 4. CSV EXPORT AJAX HANDLER
    // =========================================================================

    /**
     * AJAX: Export filtered ledger dataset as a clean CSV spreadsheet.
     */
    public static function ajax_export_csv()
    {
        if ( isset( $_REQUEST['_nonce'] ) ) {
            check_ajax_referer( 'thaaniyamhub_export_ledger_csv', '_nonce' );
        } else {
            check_admin_referer( 'thaaniyamhub_export_ledger_csv' );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Insufficient permissions', 403 );
        }

        $filters = [
            'vendor_id'     => (int) ( $_GET['thaaniyamhub_vendor_id'] ?? 0 ),
            'search'        => sanitize_text_field( $_GET['thaaniyamhub_search'] ?? '' ),
            'from'          => sanitize_text_field( $_GET['thaaniyamhub_from'] ?? '' ),
            'to'            => sanitize_text_field( $_GET['thaaniyamhub_to'] ?? '' ),
            'order_status'  => sanitize_text_field( $_GET['thaaniyamhub_order_status'] ?? '' ),
            'payout_status' => sanitize_text_field( $_GET['thaaniyamhub_payout_status'] ?? '' ),
            'profitability' => sanitize_text_field( $_GET['thaaniyamhub_profitability'] ?? 'all' ),
            'orderby'       => sanitize_text_field( $_GET['thaaniyamhub_orderby'] ?? $_GET['orderby'] ?? 'sub_order_id' ),
            'order'         => sanitize_text_field( $_GET['thaaniyamhub_order'] ?? $_GET['order'] ?? 'DESC' ),
            'limit'         => 10000, // Export all matching
            'paged'         => 1,
        ];

        $res = self::query_ledger( $filters );
        $rows = $res['rows'];

        $filename = 'ThaaniyamHub_Financial_Ledger_' . date( 'Y-m-d_His' ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );
        // UTF-8 BOM for Excel
        fprintf( $output, chr(0xEF).chr(0xBB).chr(0xBF) );

        // Header Row
        fputcsv( $output, [
            'Ledger ID',
            'Order Date',
            'Parent Order #',
            'Sub Order #',
            'Vendor ID',
            'Vendor Name',
            'Customer Name',
            'Destination Location',
            'Payment Method',
            'Order Status',
            'Item Subtotal (₹)',
            'Coupon Codes',
            'Discount Total (₹)',
            'Gross Sales / Order Price (₹)',
            'Customer Shipping Fee (₹)',
            'Product Tax (₹)',
            'Total Incoming Revenue (₹)',
            'Customer Refund (₹)',
            'Net Customer Inflow (₹)',
            'Platform Commission (₹)',
            'Commission Rate (%)',
            '18% GST on Commission (₹)',
            'Vendor Net Payout (₹)',
            'Payout Status',
            'Shiprocket Shipping Cost (₹)',
            'Courier Partner',
            'AWB Tracking #',
            'Cashfree PG Base Fee (₹)',
            'GST on PG Fee (₹)',
            'Payout Transfer Fee (₹)',
            'Total Outgoing Costs (₹)',
            'Net Platform Profit (₹)',
            'Profit Margin (%)'
        ] );

        foreach ( $rows as $r ) {
            $vendor_name = 'Store #' . $r->vendor_id;
            if ( function_exists( 'wcfm_get_vendor_store_name' ) ) {
                $v_name = wcfm_get_vendor_store_name( $r->vendor_id );
                if ( $v_name ) $vendor_name = $v_name;
            } elseif ( $u = get_userdata( $r->vendor_id ) ) {
                $vendor_name = $u->display_name;
            }

            $r_refund = (float) ( $r->refunded_amount ?? 0 );
            $r_inflow = (float) $r->total_incoming;
            $r_net_inflow = max( 0.0, $r_inflow - $r_refund );

            fputcsv( $output, [
                $r->id,
                date( 'Y-m-d H:i:s', strtotime( $r->created_at ) ),
                $r->parent_order_id,
                $r->sub_order_id,
                $r->vendor_id,
                $vendor_name,
                $r->customer_name,
                $r->customer_city,
                strtoupper( $r->payment_method ),
                ucfirst( $r->order_status ),
                number_format( (float) $r->item_subtotal, 2, '.', '' ),
                $r->coupon_codes ?: 'None',
                number_format( (float) $r->discount_total, 2, '.', '' ),
                number_format( (float) $r->gross_sales, 2, '.', '' ),
                number_format( (float) $r->shipping_charge, 2, '.', '' ),
                number_format( (float) $r->tax_amount, 2, '.', '' ),
                number_format( $r_inflow, 2, '.', '' ),
                number_format( $r_refund, 2, '.', '' ),
                number_format( $r_net_inflow, 2, '.', '' ),
                number_format( (float) $r->commission_deducted, 2, '.', '' ),
                number_format( (float) $r->commission_rate, 2, '.', '' ) . '%',
                number_format( (float) $r->commission_tax, 2, '.', '' ),
                number_format( (float) $r->vendor_net_payout, 2, '.', '' ),
                ucfirst( $r->payout_status ),
                number_format( (float) $r->shiprocket_shipping_cost, 2, '.', '' ),
                $r->shiprocket_courier_name,
                $r->shiprocket_awb,
                number_format( (float) $r->gateway_fee, 2, '.', '' ),
                number_format( (float) $r->gateway_tax, 2, '.', '' ),
                number_format( (float) $r->other_service_cost, 2, '.', '' ),
                number_format( (float) $r->total_outgoing, 2, '.', '' ),
                number_format( (float) $r->net_profit, 2, '.', '' ),
                number_format( (float) $r->profit_margin, 2, '.', '' ) . '%'
            ] );
        }

        fclose( $output );
        exit;
    }
}
