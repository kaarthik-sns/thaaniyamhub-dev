<?php
/**
 * Thaaniyam Hub Marketplace — Order History Logger
 *
 * Logs detailed metadata for each product line item upon WooCommerce checkout
 * into the custom `thaaniyamhub_order_history` table.
 *
 * Hooks:
 *   - woocommerce_checkout_order_processed @ priority 60
 *   - woocommerce_store_api_checkout_order_processed @ priority 60
 *
 * @package thaaniyamhub-multi-vendor-orders
 */

defined('ABSPATH') || exit;

class ThaaniyamHub_Order_History {

    /**
     * Register checkout/order creation hooks.
     */
    public static function init() {
        // Hook priority 60 runs after the sub-orders are split by the splitter (priority 50)
        add_action( 'woocommerce_checkout_order_processed', [ __CLASS__, 'log_order_history_classic' ], 60, 3 );
        add_action( 'woocommerce_store_api_checkout_order_processed', [ __CLASS__, 'log_order_history_blocks' ], 60, 1 );
    }

    /**
     * Entry point from classic checkout.
     */
    public static function log_order_history_classic( $order_id, $posted_data = null, $order = null ) {
        if ( ! $order ) {
            $order = wc_get_order( $order_id );
        }
        if ( $order ) {
            // Defer if order splitting is pending for prepaid order
            if ( $order->needs_payment() && ! $order->get_meta( '_thaaniyamhub_order_split_done' ) ) {
                return;
            }
            self::log_order( $order );
        }
    }

    /**
     * Entry point from Blocks / Store API checkout.
     */
    public static function log_order_history_blocks( $order ) {
        if ( $order && is_a( $order, 'WC_Order' ) ) {
            // Defer if order splitting is pending for prepaid order
            if ( $order->needs_payment() && ! $order->get_meta( '_thaaniyamhub_order_split_done' ) ) {
                return;
            }
            self::log_order( $order );
        }
    }

    /**
     * Core logging logic. Logs product line items for an independent order.
     *
     * @param WC_Order $order The WooCommerce order.
     */
    public static function log_order( WC_Order $order ) {
        global $wpdb;
        $order_id = $order->get_id();

        // Idempotency check: prevent double logging.
        if ( $order->get_meta( '_thaaniyamhub_history_logged' ) ) {
            return;
        }

        thaaniyamhub_log( "ThaaniyamHub_Order_History: Processing order history logging for order #{$order_id}" );

        // Load Shiprocket shipping configuration options
        $default_weight = floatval( thaaniyamhub_get_shipping_setting( 'default_weight', 0.1 ) );
        $additional_weight = floatval( thaaniyamhub_get_shipping_setting( 'additional_weight', 0.0 ) );

        $history_table = $wpdb->prefix . 'thaaniyamhub_order_history';

        $sub_additional_weight = $order->get_meta( '_additional_weight' );
        if ( $sub_additional_weight === '' ) {
            $sub_additional_weight = $order->get_meta( 'additional_weight' );
        }
        if ( $sub_additional_weight === '' ) {
            $sub_additional_weight = $additional_weight;
        } else {
            $sub_additional_weight = floatval( $sub_additional_weight );
        }

        $customer_id = $order->get_customer_id();
        $payment_method = $order->get_payment_method();
        $delivery_location = self::get_delivery_location_string( $order );
        $courier_info = self::resolve_courier_details( $order, $order );
        $order_total = (float) $order->get_total();

        $items = $order->get_items();
        $total_items_qty = 0;
        foreach ( $items as $item ) {
            $total_items_qty += $item->get_quantity();
        }

        $shipping_total = (float) $order->get_shipping_total();

        foreach ( $items as $item ) {
            $product = $item->get_product();
            if ( ! $product ) {
                continue;
            }

            $product_id = $item->get_product_id();
            $product_price = round( (float) $item->get_subtotal() / max( 1, $item->get_quantity() ), 2 );

            // Resolve vendor ID
            $vendor_id = 0;
            if ( method_exists( $item, 'get_meta' ) && $item->get_meta( '_vendor_id' ) ) {
                $vendor_id = (int) $item->get_meta( '_vendor_id' );
            }
            if ( ! $vendor_id && function_exists( 'wcfm_get_vendor_id_by_post' ) ) {
                $vendor_id = (int) wcfm_get_vendor_id_by_post( $product_id );
            }
            if ( ! $vendor_id ) {
                $vendor_id = (int) get_post_field( 'post_author', $product_id );
            }

            $pickup_location = self::resolve_pickup_location( $vendor_id );

            // Product metrics
            $unit_weight = (float) $product->get_weight();
            if ( $unit_weight <= 0 ) {
                $weight_unit = strtolower( get_option( 'woocommerce_weight_unit', 'kg' ) );
                if ( in_array( $weight_unit, [ 'g', 'grams' ], true ) ) {
                    $unit_weight = $default_weight * 1000;
                } elseif ( 'lbs' === $weight_unit ) {
                    $unit_weight = $default_weight / 0.453592;
                } else {
                    $unit_weight = $default_weight;
                }
            }
            $weight = self::convert_weight_to_kg( $unit_weight * $item->get_quantity() );

            // Dimensions
            $length = self::convert_dimension_to_cm( (float) $product->get_length() );
            $width = self::convert_dimension_to_cm( (float) $product->get_width() );
            $height = self::convert_dimension_to_cm( (float) $product->get_height() );

            // Financial metrics
            $order_cost = (float) $item->get_total();
            $tax = (float) $item->get_total_tax();
            $discount = (float) $item->get_subtotal() - $order_cost;
            $shipping_cost = $total_items_qty > 0 ? round( ( $shipping_total / $total_items_qty ) * $item->get_quantity(), 2 ) : 0.0;
            $pickup_cost = 0.00;

            // Platform commission
            $variation_id = $item->get_variation_id();
            $quantity     = $item->get_quantity();
            $item_subtotal = (float) $item->get_subtotal();
            [ $commission_cost, $commission_percentage ] = self::resolve_item_commission( $order_id, $product_id, $vendor_id, $order_cost, $item_subtotal, $quantity, $variation_id );

            $wpdb->insert(
                $history_table,
                [
                    'order_id'              => $order_id,
                    'sub_order_id'          => $order_id,
                    'customer_id'           => $customer_id,
                    'vendor_id'             => $vendor_id,
                    'product_id'            => $product_id,
                    'product_price'         => $product_price,
                    'weight'                => $weight,
                    'additional_weight'     => $sub_additional_weight,
                    'length'                => $length,
                    'width'                 => $width,
                    'height'                => $height,
                    'pickup_location'       => $pickup_location,
                    'delivery_location'     => $delivery_location,
                    'courier_name'          => $courier_info['name'],
                    'courier_id'            => $courier_info['id'],
                    'order_cost'            => $order_cost,
                    'pickup_cost'           => $pickup_cost,
                    'shipping_cost'         => $shipping_cost,
                    'tax'                   => $tax,
                    'discount'              => $discount,
                    'commission_cost'       => $commission_cost,
                    'commission_percentage' => $commission_percentage,
                    'order_total'           => $order_total,
                    'payment_method'        => $payment_method,
                ],
                [
                    '%d', '%d', '%d', '%d', '%d',
                    '%f', '%f', '%f', '%f', '%f', '%f',
                    '%s', '%s', '%s', '%s',
                    '%f', '%f', '%f', '%f', '%f',
                    '%f', '%f', '%f',
                    '%s'
                ]
            );
        }

        // Set the meta key to prevent duplicate logging
        $order->update_meta_data( '_thaaniyamhub_history_logged', '1' );
        $order->save();

        thaaniyamhub_log( "ThaaniyamHub_Order_History: Finished order history logging for order #{$order_id}" );
    }

    /**
     * Resolve the pickup location nickname for a vendor.
     */
    public static function resolve_pickup_location( int $vendor_id ) {
        // Priority 1: Profile user meta key.
        $nickname = trim( (string) get_user_meta( $vendor_id, '_shiprocket_pickup_id', true ) );
        if ( $nickname ) {
            return $nickname;
        }

        // Priority 2: Admin-configured pickup map.
        $pickup_map = get_option( 'thaaniyamhub_vendor_pickup_map', [] );
        if ( ! empty( $pickup_map[ $vendor_id ] ) ) {
            return trim( (string) $pickup_map[ $vendor_id ] );
        }

        // Priority 3: WCFM store name.
        if ( function_exists( 'wcfmmp_get_store' ) ) {
            $store = wcfmmp_get_store( $vendor_id );
            if ( $store ) {
                $info = $store->get_shop_info();
                if ( ! empty( $info['store_name'] ) ) {
                    return trim( $info['store_name'] );
                }
            }
        }

        return '';
    }

    /**
     * Formats billing/shipping details into a single delivery location string.
     */
    public static function get_delivery_location_string( WC_Order $order ) {
        $address_parts = array_filter( [
            $order->get_shipping_address_1() ?: $order->get_billing_address_1(),
            $order->get_shipping_address_2() ?: $order->get_billing_address_2(),
            $order->get_shipping_city() ?: $order->get_billing_city(),
            $order->get_shipping_postcode() ?: $order->get_billing_postcode(),
            $order->get_shipping_state() ?: $order->get_billing_state(),
            $order->get_shipping_country() ?: $order->get_billing_country()
        ] );
        return implode( ', ', $address_parts );
    }

    /**
     * Resolves the selected courier partner name and ID from order/shipping meta.
     */
    public static function resolve_courier_details( WC_Order $order, WC_Order $parent_order ) {
        // Try directly from the order/suborder meta
        $courier_id = $order->get_meta( '_shiprocket_selected_courier_id' );
        $courier_name = $order->get_meta( '_shiprocket_selected_courier_name' );

        // If empty, try parent order meta
        if ( ! $courier_id ) {
            $courier_id = $parent_order->get_meta( '_shiprocket_selected_courier_id' );
        }
        if ( ! $courier_name ) {
            $courier_name = $parent_order->get_meta( '_shiprocket_selected_courier_name' );
        }

        // Try from shipping items metadata
        if ( ! $courier_id || ! $courier_name ) {
            foreach ( $order->get_items( 'shipping' ) as $shipping_item ) {
                $c_id = $shipping_item->get_meta( '_courier_company_id' );
                $c_name = $shipping_item->get_meta( '_courier_name' );
                if ( $c_id && ! $courier_id ) {
                    $courier_id = $c_id;
                }
                if ( $c_name && ! $courier_name ) {
                    $courier_name = $c_name;
                }

                // Check serialized rate info
                $rate_data = $shipping_item->get_meta( 'ph_shiprocket_shipping_rates' );
                if ( is_array( $rate_data ) ) {
                    if ( empty( $courier_id ) && isset( $rate_data['courier_company_id'] ) ) {
                        $courier_id = $rate_data['courier_company_id'];
                    }
                    if ( empty( $courier_name ) && isset( $rate_data['serviceId'] ) ) {
                        $courier_name = $rate_data['serviceId'];
                    }
                }
            }
        }

        return [
            'id'   => $courier_id ? (string) $courier_id : '',
            'name' => $courier_name ? (string) $courier_name : '',
        ];
    }

    /**
     * Resolves the platform/admin commission cost and percentage of a product.
     */
    public static function resolve_item_commission( int $parent_id, int $product_id, int $vendor_id, float $item_total, float $item_subtotal, int $quantity = 1, int $variation_id = 0 ) {
        global $wpdb, $WCFMmp;

        // Try to query WCFM orders table first (populated at priority 30)
        // WCFM stores the vendor's earnings in commission_amount.
        $wcfm_row = $wpdb->get_row( $wpdb->prepare(
            "SELECT commission_amount, item_total
             FROM {$wpdb->prefix}wcfm_marketplace_orders
             WHERE order_id = %d AND product_id = %d AND vendor_id = %d LIMIT 1",
            $parent_id,
            $product_id,
            $vendor_id
        ) );

        if ( $wcfm_row && isset( $wcfm_row->commission_amount ) ) {
            $vendor_earnings = (float) $wcfm_row->commission_amount;
            $item_total_val = (float) $wcfm_row->item_total;

            // Platform commission cost = Item Total - Vendor Earnings
            $commission_cost = round( max( 0.00, $item_total_val - $vendor_earnings ), 2 );
            $commission_percentage = ( $item_total_val > 0 ) ? round( ( $commission_cost / $item_total_val ) * 100, 2 ) : 0.00;

            return [ $commission_cost, $commission_percentage ];
        }

        // Determine base price depending on whether vendor deducts discount
        $base_price = $item_total;
        if ( $WCFMmp && isset( $WCFMmp->wcfmmp_vendor ) && method_exists( $WCFMmp->wcfmmp_vendor, 'is_vendor_deduct_discount' ) ) {
            $deduct_discount = $WCFMmp->wcfmmp_vendor->is_vendor_deduct_discount( $vendor_id, $parent_id );
            $base_price = $deduct_discount ? $item_total : $item_subtotal;
        }

        // If WCFM row is not in database yet, resolve it dynamically using WCFM functions/rules
        if ( $WCFMmp && isset( $WCFMmp->wcfmmp_commission ) && isset( $WCFMmp->wcfmmp_product ) ) {
            $rule = $WCFMmp->wcfmmp_product->wcfmmp_get_product_commission_rule( $product_id, $variation_id, $vendor_id, $base_price, $quantity, $parent_id );
            if ( $rule ) {
                $vendor_earnings = (float) $WCFMmp->wcfmmp_commission->wcfmmp_get_order_item_commission( $parent_id, $vendor_id, $product_id, $variation_id, $base_price, $quantity, $rule );
                $commission_cost = round( max( 0.00, $base_price - $vendor_earnings ), 2 );
                $commission_percentage = ( $base_price > 0 ) ? round( ( $commission_cost / $base_price ) * 100, 2 ) : 0.00;
                return [ $commission_cost, $commission_percentage ];
            }
        }

        // Fallback static calculation using WCFM option/meta tables
        $fallback = ThaaniyamHub_Ledger::calculate_wcfm_commission_statically( $product_id, $variation_id, $vendor_id, $base_price, $quantity );
        return [ $fallback['admin_fee'], $fallback['effective_percentage'] ];
    }

    /**
     * Standardizes weights to kg.
     */
    public static function convert_weight_to_kg( $weight ) {
        $weight_unit = strtolower( get_option( 'woocommerce_weight_unit', 'kg' ) );
        if ( in_array( $weight_unit, [ 'g', 'grams' ], true ) ) {
            $weight /= 1000;
        } elseif ( 'lbs' === $weight_unit ) {
            $weight *= 0.453592;
        }
        return $weight;
    }

    /**
     * Standardizes dimensions to cm.
     */
    public static function convert_dimension_to_cm( $dim ) {
        $dim_unit = strtolower( get_option( 'woocommerce_dimension_unit', 'cm' ) );
        if ( 'in' === $dim_unit ) {
            $dim *= 2.54;
        } elseif ( 'm' === $dim_unit ) {
            $dim *= 100;
        } elseif ( 'mm' === $dim_unit ) {
            $dim /= 10;
        }
        return $dim;
    }
}
