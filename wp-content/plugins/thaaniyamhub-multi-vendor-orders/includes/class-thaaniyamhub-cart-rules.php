<?php
/**
 * Thaaniyam Hub Marketplace — Cart & Order Rules (MOV, MOQ & Serviceability)
 *
 * Implements Minimum Cart Value (MOV) on the total cart, Minimum Order Quantity (MOQ)
 * at the SKU level, checkout pincode serviceability checks, and free shipping progress nudges.
 *
 * @package thaaniyamhub-multi-vendor-orders
 */

defined('ABSPATH') || exit;

class ThaaniyamHub_Cart_Rules {

    /**
     * Initialize all WooCommerce hooks for MOV, MOQ, and validations.
     */
    public static function init() {
        // --- Minimum Cart Value (MOV) Enforcement ---
        add_action( 'woocommerce_before_cart', [ __CLASS__, 'display_mov_nudge_message' ] );
        add_action( 'woocommerce_before_checkout_form', [ __CLASS__, 'display_mov_nudge_message' ] );
        add_action( 'woocommerce_checkout_process', [ __CLASS__, 'enforce_mov_at_checkout' ] );

        // --- Minimum Order Quantity (MOQ) Settings ---
        // Admin Product edit page (backend)
        add_action( 'woocommerce_product_options_inventory_product_data', [ __CLASS__, 'add_moq_product_field' ] );
        add_action( 'woocommerce_admin_process_product_object', [ __CLASS__, 'save_moq_product_field' ] );

        // WCFM Frontend Product Manager (for vendors)
        add_filter( 'wcfm_product_fields_stock', [ __CLASS__, 'add_wcfm_moq_field' ], 10, 3 );
        add_action( 'after_wcfm_products_manage_meta_save', [ __CLASS__, 'save_wcfm_moq_field' ], 10, 2 );

        // --- Minimum Order Quantity (MOQ) Enforcements & Displays ---
        add_action( 'woocommerce_before_add_to_cart_form', [ __CLASS__, 'display_moq_on_product_page' ] );
        add_filter( 'woocommerce_quantity_input_min', [ __CLASS__, 'override_min_quantity_selector' ], 10, 2 );
        add_filter( 'woocommerce_quantity_input_value', [ __CLASS__, 'override_default_quantity_value' ], 10, 2 );
        add_filter( 'woocommerce_available_variation', [ __CLASS__, 'override_variation_min_qty' ], 10, 3 );

        // Cart validation
        add_filter( 'woocommerce_add_to_cart_validation', [ __CLASS__, 'validate_add_to_cart_moq' ], 10, 3 );
        add_action( 'woocommerce_check_cart_items', [ __CLASS__, 'validate_cart_moq' ] );
        add_action( 'woocommerce_check_cart_items', [ __CLASS__, 'validate_cart_vendor_pickups' ] );

        // --- Free Shipping Progress Bar Notice ---
        add_action( 'woocommerce_before_cart', [ __CLASS__, 'display_free_shipping_nudge' ], 5 );
        add_action( 'woocommerce_before_checkout_form', [ __CLASS__, 'display_free_shipping_nudge' ], 5 );

        // --- Product Page Javascript Injection ---
        add_action( 'wp_footer', [ __CLASS__, 'inject_product_validation_inline' ], 9999 );
        add_action( 'admin_footer', [ __CLASS__, 'inject_product_validation_inline' ], 9999 );

        // --- Pincode Serviceability Checkout Validation & AJAX ---
        add_action( 'woocommerce_checkout_update_order_review', [ __CLASS__, 'check_checkout_state_allowed_ajax' ] );
        add_action( 'woocommerce_after_checkout_validation', [ __CLASS__, 'validate_checkout_serviceability' ], 10, 2 );
        add_action( 'wp_footer', [ __CLASS__, 'inject_checkout_serviceability_scripts' ] );
        
        add_action( 'wp_ajax_thaaniyamhub_check_pincode_serviceability', [ __CLASS__, 'ajax_check_pincode_serviceability' ] );
        add_action( 'wp_ajax_nopriv_thaaniyamhub_check_pincode_serviceability', [ __CLASS__, 'ajax_check_pincode_serviceability' ] );
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    public static function get_product_moq( $product ) {
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return 1;
        }
        $moq = (int) $product->get_meta( '_thaaniyamhub_moq' );
        if ( $moq <= 0 && $product->is_type( 'variation' ) ) {
            $parent = wc_get_product( $product->get_parent_id() );
            if ( $parent ) {
                $moq = (int) $parent->get_meta( '_thaaniyamhub_moq' );
            }
        }
        return $moq > 0 ? $moq : 1;
    }

    // =========================================================================
    // 1. MINIMUM CART VALUE (MOV) LOGIC
    // =========================================================================

    public static function display_mov_nudge_message() {
        if ( ! WC()->cart ) {
            return;
        }

        $mov = (float) get_option( 'thaaniyamhub_minimum_cart_value', 0 );
        if ( $mov <= 0 ) {
            return;
        }

        $cart_total = (float) WC()->cart->get_cart_contents_total();

        if ( $cart_total < $mov ) {
            $remaining = $mov - $cart_total;
            $pct = min( 100, max( 0, round( ( $cart_total / $mov ) * 100 ) ) );
            
            echo '<div class="thaaniyamhub-mov-nudge" style="width: 100%; flex: 0 0 100%; box-sizing: border-box; background: hsl(35, 45%, 97%); border: 1px solid hsl(35, 35%, 88%); padding: 18px; border-radius: 12px; margin-bottom: 24px; font-family: \'Outfit\', \'Inter\', sans-serif;">';
            echo '  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">';
            echo '    <span style="font-weight: 600; color: hsl(35, 55%, 24%); font-size: 14px;">🛒 ' . sprintf( __( 'Add items worth %s more to place your order (Minimum cart value is %s).', 'thaaniyamhub-multi-vendor-orders' ), '<strong>' . wc_price( $remaining ) . '</strong>', '<strong>' . wc_price( $mov ) . '</strong>' ) . '</span>';
            echo '    <span style="font-weight: 700; color: hsl(35, 75%, 42%); font-size: 13px;">' . $pct . '%</span>';
            echo '  </div>';
            echo '  <div style="background: hsl(35, 20%, 90%); border-radius: 999px; height: 10px; width: 100%; overflow: hidden;">';
            echo '    <div style="background: linear-gradient(90deg, hsl(35, 75%, 62%), hsl(35, 80%, 48%)); width: ' . $pct . '%; height: 100%; border-radius: 999px; transition: width 0.4s ease;"></div>';
            echo '  </div>';
            echo '</div>';
        }
    }

    public static function enforce_mov_at_checkout() {
        $mov = (float) get_option( 'thaaniyamhub_minimum_cart_value', 0 );
        if ( $mov > 0 && WC()->cart ) {
            $cart_total = (float) WC()->cart->get_cart_contents_total();
            if ( $cart_total < $mov ) {
                $remaining = $mov - $cart_total;
                wc_add_notice(
                    sprintf(
                        __( 'Your cart total is %s. Please add items worth %s more to place your order (Minimum cart value is %s).', 'thaaniyamhub-multi-vendor-orders' ),
                        wc_price( $cart_total ),
                        wc_price( $remaining ),
                        wc_price( $mov )
                    ),
                    'error'
                );
            }
        }
    }

    // =========================================================================
    // 2. MINIMUM ORDER QUANTITY (MOQ) SETTINGS
    // =========================================================================

    public static function add_moq_product_field() {
        woocommerce_wp_text_input( [
            'id'          => '_thaaniyamhub_moq',
            'label'       => __( 'Minimum Order Qty (MOQ)', 'thaaniyamhub-multi-vendor-orders' ),
            'desc_tip'    => 'true',
            'description' => __( 'The minimum quantity required to buy this product.', 'thaaniyamhub-multi-vendor-orders' ),
            'type'        => 'number',
            'custom_attributes' => [
                'min'  => '1',
                'step' => '1'
            ]
        ] );
    }

    public static function save_moq_product_field( $product ) {
        $moq = isset( $_POST['_thaaniyamhub_moq'] ) ? sanitize_text_field( $_POST['_thaaniyamhub_moq'] ) : '';
        $product->update_meta_data( '_thaaniyamhub_moq', $moq );
    }

    public static function add_wcfm_moq_field( $inventory_fields, $product_id, $product_type ) {
        $inventory_fields['_thaaniyamhub_moq'] = [
            'label'       => __( 'Minimum Order Qty (MOQ)', 'thaaniyamhub-multi-vendor-orders' ),
            'type'        => 'number',
            'class'       => 'wcfm-text wcfm_ele simple variable',
            'label_class' => 'wcfm_title wcfm_ele simple variable',
            'value'       => get_post_meta( $product_id, '_thaaniyamhub_moq', true ),
            'desc'        => __( 'The minimum quantity required to buy this product.', 'thaaniyamhub-multi-vendor-orders' ),
            'desc_class'  => 'wcfm_page_options_desc',
            'custom_attributes' => [
                'min'  => '1',
                'step' => '1'
            ]
        ];
        return $inventory_fields;
    }

    public static function save_wcfm_moq_field( $product_id, $wcfm_products_manage_form_data ) {
        if ( isset( $wcfm_products_manage_form_data['_thaaniyamhub_moq'] ) ) {
            $moq_value = sanitize_text_field( $wcfm_products_manage_form_data['_thaaniyamhub_moq'] );
            update_post_meta( $product_id, '_thaaniyamhub_moq', $moq_value );
        }
    }

    // =========================================================================
    // 3. MINIMUM ORDER QUANTITY (MOQ) ENFORCEMENT & DISPLAY
    // =========================================================================

    public static function display_moq_on_product_page() {
        global $product;
        if ( ! $product ) {
            return;
        }

        $moq = self::get_product_moq( $product );
        if ( $moq > 1 ) {
            echo '<div class="thaaniyamhub-moq-label" style="margin: 10px 0; font-weight: bold; color: #7c3aed; font-size: 14px;">';
            printf( __( 'Minimum order: %d units', 'thaaniyamhub-multi-vendor-orders' ), $moq );
            echo '</div>';
        }
    }

    public static function override_min_quantity_selector( $min, $product ) {
        if ( $product ) {
            $moq = self::get_product_moq( $product );
            if ( $moq > 1 ) {
                return $moq;
            }
        }
        return $min;
    }

    public static function override_default_quantity_value( $val, $product ) {
        if ( $product ) {
            $moq = self::get_product_moq( $product );
            if ( $moq > 1 && is_single() ) {
                return $moq;
            }
        }
        return $val;
    }

    public static function override_variation_min_qty( $data, $product, $variation ) {
        $moq = self::get_product_moq( $variation );
        if ( $moq > 1 ) {
            $data['min_qty'] = $moq;
            if ( isset( $data['input_value'] ) && $data['input_value'] < $moq ) {
                $data['input_value'] = $moq;
            }
        }
        return $data;
    }

    public static function validate_add_to_cart_moq( $passed, $product_id, $quantity ) {
        $product = wc_get_product( $product_id );
        if ( $product ) {
            $moq = self::get_product_moq( $product );
            if ( $moq > 1 && $quantity < $moq ) {
                wc_add_notice(
                    sprintf(
                        __( 'You cannot add "%s" to cart. The minimum order quantity is %d units.', 'thaaniyamhub-multi-vendor-orders' ),
                        $product->get_name(),
                        $moq
                    ),
                    'error'
                );
                return false;
            }
        }
        return $passed;
    }

    public static function validate_cart_moq() {
        if ( ! WC()->cart ) {
            return;
        }

        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            $product = $cart_item['data'];
            $moq = self::get_product_moq( $product );
            if ( $moq > 1 && $cart_item['quantity'] < $moq ) {
                WC()->cart->set_quantity( $cart_item_key, $moq );
                wc_add_notice(
                    sprintf(
                        __( 'Adjusted "%s" quantity to %d because it is the minimum order quantity.', 'thaaniyamhub-multi-vendor-orders' ),
                        $product->get_name(),
                        $moq
                    ),
                    'notice'
                );
            }
        }
    }

    public static function validate_cart_vendor_pickups() {
        if ( ! WC()->cart || WC()->cart->is_empty() ) {
            return;
        }

        $invalid_vendors = [];
        foreach ( WC()->cart->get_cart() as $item ) {
            $pid = $item['product_id'];
            $vid = function_exists( 'wcfm_get_vendor_id_by_post' ) ? (int) wcfm_get_vendor_id_by_post( $pid ) : (int) get_post_field( 'post_author', $pid );
            if ( $vid > 0 && ! isset( $invalid_vendors[ $vid ] ) ) {
                $assigned_pickup = '';
                if ( class_exists( 'ThaaniyamHub_Dispatch' ) && method_exists( 'ThaaniyamHub_Dispatch', 'resolve_pickup_nickname' ) ) {
                    $assigned_pickup = ThaaniyamHub_Dispatch::resolve_pickup_nickname( $vid );
                } else {
                    $assigned_pickup = get_user_meta( $vid, '_shiprocket_pickup_id', true );
                    if ( ! $assigned_pickup ) {
                        $pickup_map = get_option( 'thaaniyamhub_vendor_pickup_map', [] );
                        $assigned_pickup = $pickup_map[ $vid ] ?? '';
                    }
                }
                $v_postcode = '';
                if ( $assigned_pickup && class_exists( 'ThaaniyamHub_Dashboard' ) && method_exists( 'ThaaniyamHub_Dashboard', 'resolve_pickup_pincode' ) ) {
                    $v_postcode = ThaaniyamHub_Dashboard::resolve_pickup_pincode( $assigned_pickup, $vid );
                }
                if ( empty( $assigned_pickup ) || empty( $v_postcode ) ) {
                    $vendor_name = function_exists( 'wcfm_get_vendor_store_name_by_vendor' ) ? wcfm_get_vendor_store_name_by_vendor( $vid ) : ( get_user_meta( $vid, 'store_name', true ) ?: ( get_userdata( $vid )->display_name ?? ('#' . $vid) ) );
                    $invalid_vendors[ $vid ] = $vendor_name;
                }
            }
        }

        if ( ! empty( $invalid_vendors ) ) {
            foreach ( $invalid_vendors as $vid => $vendor_name ) {
                wc_add_notice(
                    sprintf(
                        __( 'Order cannot proceed: Products from "%s" cannot be shipped because the vendor does not have a verified pickup location assigned. Please remove these items to proceed.', 'thaaniyamhub-multi-vendor-orders' ),
                        $vendor_name
                    ),
                    'error'
                );
            }
        }
    }

    // =========================================================================
    // 4. FREE SHIPPING PROGRESS BAR NOTICE
    // =========================================================================

    public static function display_free_shipping_nudge() {
        if ( ! WC()->cart ) {
            return;
        }

        // Resolve customer postcode and determine their zone dynamically
        $customer_postcode = '';
        if ( WC()->customer ) {
            $customer_postcode = WC()->customer->get_shipping_postcode();
            if ( empty( $customer_postcode ) ) {
                $customer_postcode = WC()->customer->get_billing_postcode();
            }
        }

        $czone = '';
        if ( ! empty( $customer_postcode ) && class_exists( 'ThaaniyamHub_Shipping_Helper' ) ) {
            $czone = ThaaniyamHub_Shipping_Helper::get_shipping_zone_from_pincode( $customer_postcode );
        }

        $free_threshold = (float) get_option( 'thaaniyamhub_shipping_free_threshold', 899 );
        if ( $czone === 'D' ) {
            $free_threshold = (float) get_option( 'thaaniyamhub_shipping_free_threshold_zone_d', 1199 );
        }

        if ( $free_threshold <= 0 ) {
            return;
        }

        $subtotal = (float) WC()->cart->get_subtotal();

        if ( $subtotal < $free_threshold ) {
            $remaining = $free_threshold - $subtotal;
            $pct = min( 100, max( 0, round( ( $subtotal / $free_threshold ) * 100 ) ) );
            
            echo '<div class="thaaniyamhub-free-shipping-nudge" style="width: 100%; flex: 0 0 100%; box-sizing: border-box; background: hsl(260, 30%, 97%); border: 1px solid hsl(260, 20%, 90%); padding: 18px; border-radius: 12px; margin-bottom: 24px; font-family: \'Outfit\', \'Inter\', sans-serif;">';
            echo '  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">';
            echo '    <span style="font-weight: 600; color: hsl(260, 30%, 25%); font-size: 14px;">🚚 ' . sprintf( __( 'Add %s more for free standard shipping!', 'thaaniyamhub-multi-vendor-orders' ), '<strong>' . wc_price( $remaining ) . '</strong>' ) . '</span>';
            echo '    <span style="font-weight: 700; color: hsl(260, 50%, 50%); font-size: 13px;">' . $pct . '%</span>';
            echo '  </div>';
            echo '  <div style="background: hsl(260, 10%, 90%); border-radius: 999px; height: 10px; width: 100%; overflow: hidden;">';
            echo '    <div style="background: linear-gradient(90deg, hsl(260, 60%, 65%), hsl(260, 60%, 50%)); width: ' . $pct . '%; height: 100%; border-radius: 999px; transition: width 0.4s ease;"></div>';
            echo '  </div>';
            echo '</div>';
        } else {
            echo '<div class="thaaniyamhub-free-shipping-nudge" style="width: 100%; flex: 0 0 100%; box-sizing: border-box; background: hsl(140, 30%, 96%); border: 1px solid hsl(140, 20%, 88%); padding: 16px; border-radius: 12px; margin-bottom: 24px; font-family: \'Outfit\', \'Inter\', sans-serif; display: flex; align-items: center; gap: 8px; color: hsl(140, 40%, 20%); font-weight: 600; font-size: 14px;">';
            echo '🎉 ' . __( 'Congratulations! You qualify for Free Standard Shipping.', 'thaaniyamhub-multi-vendor-orders' );
            echo '</div>';
        }
    }

    // =========================================================================

    public static function inject_product_validation_inline() {
        $js_path = wp_normalize_path( WP_CONTENT_DIR . '/themes/twentytwentyone-child/assets/js/product-validation.js' );
        if ( file_exists( $js_path ) ) {
            ?>
            <script type="text/javascript">
            <?php readfile( $js_path ); ?>
            </script>
            <?php
        }
    }

    // =========================================================================
    // 6. PINCODE SERVICEABILITY VALIDATIONS & AJAX
    // =========================================================================

    public static function check_delivery_serviceability( $customer_pincode ) {
        thaaniyamhub_log( "Serviceability: Checking serviceability for customer pincode: '{$customer_pincode}'" );
        if ( ! WC()->cart ) {
            thaaniyamhub_log( "Serviceability: WC Cart is empty/unavailable. Serviceability check bypassed." );
            return true;
        }

        $vendor_ids = [];
        foreach ( WC()->cart->get_cart() as $item ) {
            $pid = $item['product_id'];
            $vid = function_exists( 'wcfm_get_vendor_id_by_post' ) ? (int) wcfm_get_vendor_id_by_post( $pid ) : 0;
            if ( ! $vid ) {
                $vid = (int) get_post_field( 'post_author', $pid );
            }
            if ( $vid > 0 ) {
                $vendor_ids[ $vid ][] = $item['data']->get_name();
            }
        }

        if ( empty( $vendor_ids ) ) {
            thaaniyamhub_log( "Serviceability: No vendor products found in cart. Serviceability check bypassed." );
            return true;
        }

        thaaniyamhub_log( "Serviceability: Vendors found in cart: " . print_r( array_keys($vendor_ids), true ) );

        $api = new ThaaniyamHub_Shiprocket_API();

        foreach ( $vendor_ids as $vid => $product_names ) {
            $vendor_postcode = '';
            $assigned_pickup = '';
            if ( class_exists( 'ThaaniyamHub_Dispatch' ) && method_exists( 'ThaaniyamHub_Dispatch', 'resolve_pickup_nickname' ) ) {
                $assigned_pickup = ThaaniyamHub_Dispatch::resolve_pickup_nickname( $vid );
            } else {
                $assigned_pickup = get_user_meta( $vid, '_shiprocket_pickup_id', true );
                if ( ! $assigned_pickup ) {
                    $pickup_map = get_option( 'thaaniyamhub_vendor_pickup_map', [] );
                    $assigned_pickup = $pickup_map[ $vid ] ?? '';
                }
            }
            if ( $assigned_pickup ) {
                if ( class_exists( 'ThaaniyamHub_Dashboard' ) && method_exists( 'ThaaniyamHub_Dashboard', 'resolve_pickup_pincode' ) ) {
                    $vendor_postcode = ThaaniyamHub_Dashboard::resolve_pickup_pincode( $assigned_pickup, $vid );
                }
            }
            if ( empty( $assigned_pickup ) || empty( $vendor_postcode ) ) {
                $vendor_name = function_exists( 'wcfm_get_vendor_store_name_by_vendor' ) ? wcfm_get_vendor_store_name_by_vendor( $vid ) : ( get_user_meta( $vid, 'store_name', true ) ?: ( get_userdata( $vid )->display_name ?? ('#' . $vid) ) );
                thaaniyamhub_log( "Serviceability: Vendor #{$vid} ({$vendor_name}) does not have an assigned and verified Shiprocket pickup location. Blocking checkout.", 'error' );
                return new WP_Error( 'unassigned_pickup', sprintf(
                    __( 'One or more items in your cart cannot be shipped because the vendor (%s) does not have a verified pickup location configured. Please contact support.', 'thaaniyamhub-multi-vendor-orders' ),
                    $vendor_name
                ) );
            }

            $params = [
                'pickup_postcode'   => (int) $vendor_postcode,
                'delivery_postcode' => (int) $customer_pincode,
                'weight'            => 0.5,
                'cod'               => 0,
            ];
            thaaniyamhub_log( "Serviceability: Calling Shiprocket check_serviceability API with parameters: " . print_r( $params, true ) );
            $svc = $api->check_serviceability( $params );

            if ( is_wp_error( $svc ) ) {
                $err_msg = $svc->get_error_message();
                thaaniyamhub_log( "Serviceability: Shiprocket API error for vendor #{$vid}: {$err_msg}", 'warning' );

                // Distinguish API/config failures from genuine unserviceability.
                // Auth errors, decryption failures, and HTTP errors are server-side
                // configuration issues — do NOT block the customer from checking out.
                $bypass_keywords = [
                    'decrypt', 'password', 'token', 'auth', 'credential',
                    'unauthori', 'forbidden', 'http', 'curl', 'timed out',
                ];
                $is_config_error = false;
                $err_lower = strtolower( $err_msg );
                foreach ( $bypass_keywords as $kw ) {
                    if ( strpos( $err_lower, $kw ) !== false ) {
                        $is_config_error = true;
                        break;
                    }
                }

                if ( $is_config_error ) {
                    thaaniyamhub_log( "Serviceability: Bypassing serviceability check for vendor #{$vid} due to API configuration error (not a genuine unserviceability).", 'warning' );
                    continue; // Skip this vendor — treat as serviceable
                }

                // For non-config errors, block checkout with a user-facing message.
                return new WP_Error( 'unserviceable', sprintf(
                    __( 'We are coming soon to your area! Currently, we do not provide delivery service to pincode %s.', 'thaaniyamhub-multi-vendor-orders' ),
                    $customer_pincode
                ) );
            }


            $couriers = $svc['data']['available_courier_companies'] ?? [];
            thaaniyamhub_log( "Serviceability: Shiprocket API returned " . count($couriers) . " available courier(s) for vendor #{$vid}." );
            if ( empty( $couriers ) ) {
                thaaniyamhub_log( "Serviceability: No courier options returned. Delivery is unserviceable.", 'warning' );
                return new WP_Error( 'unserviceable', sprintf(
                    __( 'We are coming soon to your area! Currently, we do not provide delivery service to pincode %s.', 'thaaniyamhub-multi-vendor-orders' ),
                    $customer_pincode
                ) );
            }
        }

        thaaniyamhub_log( "Serviceability: All vendor packages are serviceable." );
        return true;
    }

    public static function validate_checkout_serviceability( $data, $errors ) {
        // First verify every vendor in cart has an assigned & verified Shiprocket pickup location
        if ( WC()->cart ) {
            foreach ( WC()->cart->get_cart() as $item ) {
                $pid = $item['product_id'];
                $vid = function_exists( 'wcfm_get_vendor_id_by_post' ) ? (int) wcfm_get_vendor_id_by_post( $pid ) : (int) get_post_field( 'post_author', $pid );
                if ( $vid > 0 ) {
                    $assigned_pickup = '';
                    if ( class_exists( 'ThaaniyamHub_Dispatch' ) && method_exists( 'ThaaniyamHub_Dispatch', 'resolve_pickup_nickname' ) ) {
                        $assigned_pickup = ThaaniyamHub_Dispatch::resolve_pickup_nickname( $vid );
                    } else {
                        $assigned_pickup = get_user_meta( $vid, '_shiprocket_pickup_id', true );
                        if ( ! $assigned_pickup ) {
                            $pickup_map = get_option( 'thaaniyamhub_vendor_pickup_map', [] );
                            $assigned_pickup = $pickup_map[ $vid ] ?? '';
                        }
                    }
                    $v_postcode = '';
                    if ( $assigned_pickup && class_exists( 'ThaaniyamHub_Dashboard' ) && method_exists( 'ThaaniyamHub_Dashboard', 'resolve_pickup_pincode' ) ) {
                        $v_postcode = ThaaniyamHub_Dashboard::resolve_pickup_pincode( $assigned_pickup, $vid );
                    }
                    if ( empty( $assigned_pickup ) || empty( $v_postcode ) ) {
                        $vendor_name = function_exists( 'wcfm_get_vendor_store_name_by_vendor' ) ? wcfm_get_vendor_store_name_by_vendor( $vid ) : ( get_user_meta( $vid, 'store_name', true ) ?: ( get_userdata( $vid )->display_name ?? ('#' . $vid) ) );
                        $errors->add( 'shipping', sprintf(
                            __( 'One or more items in your cart cannot be shipped because the vendor (%s) does not have a verified pickup location configured.', 'thaaniyamhub-multi-vendor-orders' ),
                            $vendor_name
                        ) );
                        return;
                    }
                }
            }
        }

        // Validate allowed states next
        $customer_state = $data['shipping_state'] ?: $data['billing_state'];
        $c_state_code = '';
        if ( class_exists( 'ThaaniyamHub_Shipping_Helper' ) ) {
            $c_state_code = ThaaniyamHub_Shipping_Helper::normalize_state_code( $customer_state );
        } else {
            $c_state_code = strtoupper( substr( trim( $customer_state ), 0, 2 ) );
        }

        $allowed_states = [ 'TN', 'PY', 'KL', 'KA', 'AP' ];
        if ( ! empty( $c_state_code ) && ! in_array( $c_state_code, $allowed_states, true ) ) {
            $errors->add( 'shipping', __( 'We are coming soon to your area! Currently, we do not provide delivery service to your state.', 'thaaniyamhub-multi-vendor-orders' ) );
            return;
        }

        $customer_pincode = $data['shipping_postcode'] ?: $data['billing_postcode'];
        if ( empty( $customer_pincode ) ) {
            return;
        }

        $res = self::check_delivery_serviceability( $customer_pincode );
        if ( is_wp_error( $res ) ) {
            $errors->add( 'shipping', $res->get_error_message() );
        }
    }

    /**
     * Checks if the selected state at checkout is in the allowed states list during AJAX update.
     * Shows an error notice dynamically if the state is outside the allowed list.
     */
    public static function check_checkout_state_allowed_ajax( $post_data ) {
        $data = [];
        if ( ! empty( $post_data ) ) {
            wp_parse_str( $post_data, $data );
        }

        $customer_state = '';
        if ( isset( $data['ship_to_different_address'] ) && $data['ship_to_different_address'] ) {
            $customer_state = $data['shipping_state'] ?? '';
        } else {
            $customer_state = $data['billing_state'] ?? '';
        }

        if ( empty( $customer_state ) ) {
            if ( isset( WC()->customer ) ) {
                $customer_state = WC()->customer->get_shipping_state() ?: WC()->customer->get_billing_state();
            }
        }

        if ( ! empty( $customer_state ) ) {
            if ( class_exists( 'ThaaniyamHub_Shipping_Helper' ) ) {
                $c_state_code = ThaaniyamHub_Shipping_Helper::normalize_state_code( $customer_state );
            } else {
                $c_state_code = strtoupper( substr( trim( $customer_state ), 0, 2 ) );
            }

            $allowed_states = [ 'TN', 'PY', 'KL', 'KA', 'AP' ];
            if ( ! in_array( $c_state_code, $allowed_states, true ) ) {
                wc_add_notice( __( 'We are coming soon to your area! Currently, we do not provide delivery service to your state.', 'thaaniyamhub-multi-vendor-orders' ), 'error' );
            }
        }
    }

    public static function ajax_check_pincode_serviceability() {
        check_ajax_referer( 'thaaniyamhub_svc_nonce', '_nonce' );
        $pincode = sanitize_text_field( $_POST['pincode'] ?? '' );
        if ( ! $pincode ) {
            wp_send_json_error( __( 'Pincode is required.', 'thaaniyamhub-multi-vendor-orders' ) );
        }

        $res = self::check_delivery_serviceability( $pincode );
        if ( is_wp_error( $res ) ) {
            wp_send_json_error( $res->get_error_message() );
        }

        wp_send_json_success();
    }

    public static function inject_checkout_serviceability_scripts() {
        if ( ! is_checkout() || is_order_received_page() ) {
            return;
        }

        $nonce = wp_create_nonce('thaaniyamhub_svc_nonce');
        ?>
        <script>
            jQuery(function($) {
                "use strict";

                var checkTimeout;
                
                function checkPincodeServiceability(pincode, $fieldWrapper) {
                    $fieldWrapper.find(".thaaniyamhub-svc-status").remove();
                    $fieldWrapper.append('<span class="thaaniyamhub-svc-status" style="font-size:11px;color:#888;margin-top:4px;display:block;">Checking delivery serviceability…</span>');

                    $.post(typeof wc_checkout_params !== "undefined" ? wc_checkout_params.ajax_url : "/wp-admin/admin-ajax.php", {
                        action: "thaaniyamhub_check_pincode_serviceability",
                        pincode: pincode,
                        _nonce: <?php echo wp_json_encode($nonce); ?>
                    }, function(res) {
                        $fieldWrapper.find(".thaaniyamhub-svc-status").remove();
                        if (res.success) {
                            $fieldWrapper.append('<span class="thaaniyamhub-svc-status" style="font-size:11px;color:#15803d;font-weight:600;margin-top:4px;display:block;">✓ Delivery is serviceable</span>');
                        } else {
                            $fieldWrapper.append('<span class="thaaniyamhub-svc-status" style="font-size:11px;color:#b91c1c;font-weight:600;margin-top:4px;display:block;">⚠️ ' + res.data + '</span>');
                        }
                    });
                }

                $(document).on("change keyup", "#billing_postcode, #shipping_postcode", function() {
                    var $input = $(this);
                    var val = $input.val().replace(/\s/g, "");
                    if (val.length === 6) {
                        clearTimeout(checkTimeout);
                        checkTimeout = setTimeout(function() {
                            checkPincodeServiceability(val, $input.closest(".form-row"));
                        }, 500);
                    }
                });
            });
        </script>
        <?php
    }
}

