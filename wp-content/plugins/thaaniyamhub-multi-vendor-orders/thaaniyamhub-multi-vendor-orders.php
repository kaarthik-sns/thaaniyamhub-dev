<?php
/**
 * Plugin Name: Thaaniyam Hub - Multi-Vendor Orders
 * Description: Thaaniyam Hub Multi-Vendor Marketplace — Hybrid DB engine, independent vendor orders,
 *              Shiprocket logistics dispatch, financial ledger, AWB/label controls, and PDF reporting.
 * Author: Searchnscore Solution PVT LTD
 * Version: 1.0.0
 * Text Domain: thaaniyamhub-multi-vendor-orders
 */

defined('ABSPATH') || exit;

// =========================================================================
// VERSION COMPATIBILITY AND DEPENDENCY CONSTANTS
// =========================================================================
define( 'THAANIYAMHUB_WOO_MAX_TESTED', '11.0.1' );
define( 'THAANIYAMHUB_SHIPROCKET_MAX_TESTED', '2.0.9' );
define( 'THAANIYAMHUB_WCFM_MAX_TESTED', '6.8.1' );
define( 'THAANIYAMHUB_WCFMU_MAX_TESTED', '6.7.8' );
define( 'THAANIYAMHUB_WCFMMP_MAX_TESTED', '3.8.3' );
define( 'THAANIYAMHUB_WCFM_MEMBERSHIP_MAX_TESTED', '2.12.0' );
define( 'THAANIYAMHUB_WCFM_PRODUCT_HUB_MAX_TESTED', '1.0.11' );
define( 'THAANIYAMHUB_RAZORPAY_MAX_TESTED', '4.8.7' );

// Helper to check if WooCommerce is active
if ( ! function_exists( 'thaaniyamhub_is_woocommerce_active' ) ) {
    function thaaniyamhub_is_woocommerce_active() {
        if ( class_exists( 'WooCommerce' ) ) {
            return true;
        }
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active( 'woocommerce/woocommerce.php' ) || is_plugin_active_for_network( 'woocommerce/woocommerce.php' );
    }
}

// Abort loading the plugin if WooCommerce is not active
if ( ! thaaniyamhub_is_woocommerce_active() ) {
    add_action( 'admin_notices', function() {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e( 'Thaaniyam Hub - Multi-Vendor Orders requires WooCommerce to be installed and active.', 'thaaniyamhub-multi-vendor-orders' ); ?></p>
        </div>
        <?php
    } );
    return;
}

// Version compatibility check in wp-admin
if ( ! function_exists( 'thaaniyamhub_check_plugin_compatibility' ) ) {
    function thaaniyamhub_check_plugin_compatibility() {
        if ( ! is_admin() ) {
            return;
        }

        $warnings = [];

        $plugins_to_check = [
            'woocommerce' => [
                'name'       => 'WooCommerce',
                'file'       => 'woocommerce/woocommerce.php',
                'max_tested' => THAANIYAMHUB_WOO_MAX_TESTED,
            ],
            'shiprocket' => [
                'name'       => 'Shiprocket',
                'file'       => 'shiprocket/class-shiprocket-woocommerce-shipping.php',
                'max_tested' => THAANIYAMHUB_SHIPROCKET_MAX_TESTED,
            ],
            'wc-frontend-manager' => [
                'name'       => 'WC Frontend Manager (WCFM)',
                'file'       => 'wc-frontend-manager/wc_frontend_manager.php',
                'max_tested' => THAANIYAMHUB_WCFM_MAX_TESTED,
            ],
            'wc-frontend-manager-ultimate' => [
                'name'       => 'WCFM Ultimate',
                'file'       => 'wc-frontend-manager-ultimate/wc_frontend_manager_ultimate.php',
                'max_tested' => THAANIYAMHUB_WCFMU_MAX_TESTED,
            ],
            'wc-multivendor-marketplace' => [
                'name'       => 'WCFM Multivendor Marketplace',
                'file'       => 'wc-multivendor-marketplace/wc-multivendor-marketplace.php',
                'max_tested' => THAANIYAMHUB_WCFMMP_MAX_TESTED,
            ],
            'wc-multivendor-membership' => [
                'name'       => 'WCFM Multivendor Membership',
                'file'       => 'wc-multivendor-membership/wc-multivendor-membership.php',
                'max_tested' => THAANIYAMHUB_WCFM_MEMBERSHIP_MAX_TESTED,
            ],
            'wc-frontend-manager-product-hub' => [
                'name'       => 'WCFM Product Hub',
                'file'       => 'wc-frontend-manager-product-hub/wc_frontend_manager_product_hub.php',
                'max_tested' => THAANIYAMHUB_WCFM_PRODUCT_HUB_MAX_TESTED,
            ],
            'woo-razorpay' => [
                'name'       => 'Razorpay WooCommerce',
                'file'       => 'woo-razorpay/woo-razorpay.php',
                'max_tested' => THAANIYAMHUB_RAZORPAY_MAX_TESTED,
            ]
        ];

        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        foreach ( $plugins_to_check as $slug => $info ) {
            $file_path = $info['file'];
            $is_active = is_plugin_active( $file_path ) || is_plugin_active_for_network( $file_path );
            
            if ( $is_active ) {
                $full_path = WP_PLUGIN_DIR . '/' . $file_path;
                if ( file_exists( $full_path ) ) {
                    $data = get_plugin_data( $full_path );
                    $active_version = $data['Version'] ?? '';
                    if ( ! empty( $active_version ) && version_compare( $active_version, $info['max_tested'], '>' ) ) {
                        $warnings[] = sprintf(
                            '<strong>%1$s</strong> (Active: %2$s, Max Tested: %3$s)',
                            esc_html( $info['name'] ),
                            esc_html( $active_version ),
                            esc_html( $info['max_tested'] )
                        );
                    }
                }
            }
        }

        if ( ! empty( $warnings ) ) {
            add_action( 'admin_notices', function() use ( $warnings ) {
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p>
                        <strong><?php esc_html_e( 'Thaaniyam Hub - Compatibility Notice:', 'thaaniyamhub-multi-vendor-orders' ); ?></strong><br>
                        <?php esc_html_e( 'The following active plugins have versions newer than the maximum tested limits of the custom Multi-Vendor Orders plugin. Please verify key checkout/shipping features on a staging environment:', 'thaaniyamhub-multi-vendor-orders' ); ?>
                        <ul style="list-style-type: disc; padding-left: 20px; margin-top: 5px;">
                            <?php foreach ( $warnings as $warning ) : ?>
                                <li><?php echo wp_kses_post( $warning ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </p>
                </div>
                <?php
            } );
        }
    }
}
add_action( 'admin_init', 'thaaniyamhub_check_plugin_compatibility' );

// =========================================================================
// STANDALONE LOGGING HELPER (WOOCOMMERCE LOGGER INTEGRATION)
// =========================================================================
if (!function_exists('thaaniyamhub_get_latest_wc_log_file')) {
    function thaaniyamhub_get_latest_wc_log_file($source = 'thaaniyamhub-orders-shiprocket') {
        $upload_dir = wp_upload_dir();
        $wc_log_dir = $upload_dir['basedir'] . '/wc-logs';
        if (!is_dir($wc_log_dir)) {
            return false;
        }
        $files = glob($wc_log_dir . '/' . $source . '-*.log');
        if (empty($files)) {
            return false;
        }
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        return $files[0];
    }
}

if (!function_exists('thaaniyamhub_log')) {
    function thaaniyamhub_log($message, $level = 'info', $source = 'thaaniyamhub-orders-shiprocket')
    {
        if ( ! empty( $GLOBALS['thaaniyamhub_in_shipping_calculation'] ) ) {
            return;
        }

        $message_str = is_array($message) || is_object($message) ? print_r($message, true) : $message;

        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $context = array('source' => $source);
            $logger->log($level, $message_str, $context);
        }
    }
}

// =========================================================================
// SHIPPING SETTINGS LOOKUP HELPER
// =========================================================================
if (!function_exists('thaaniyamhub_get_shipping_setting')) {
    function thaaniyamhub_get_shipping_setting($key, $default = '')
    {
        $option_name = 'thaaniyamhub_' . $key;
        $val = get_option($option_name);
        if (false !== $val) {
            return $val;
        }

        // Fallback to old WooCommerce shipping method settings
        $settings = get_option('woocommerce_shiprocket_woocommerce_shipping_settings', array());
        if (isset($settings[$key])) {
            // Migrate to the new option name for future calls
            update_option($option_name, $settings[$key]);
            return $settings[$key];
        }

        return $default;
    }
}

// =========================================================================
// THAANIYAM HUB MODULE INCLUDES
// Load all new include files — logging helper must be defined first.
// =========================================================================
$thaaniyamhub_includes = plugin_dir_path(__FILE__) . 'includes/';

require_once $thaaniyamhub_includes . 'class-thaaniyamhub-db-install.php';
require_once $thaaniyamhub_includes . 'class-thaaniyamhub-shiprocket-api.php';
require_once $thaaniyamhub_includes . 'class-thaaniyamhub-dispatch.php';
require_once $thaaniyamhub_includes . 'class-thaaniyamhub-tracker.php';
require_once $thaaniyamhub_includes . 'class-thaaniyamhub-pickup-logger.php';
require_once $thaaniyamhub_includes . 'class-thaaniyamhub-pickup-manager.php';
require_once $thaaniyamhub_includes . 'class-thaaniyamhub-dimension-manager.php';

require_once $thaaniyamhub_includes . 'class-thaaniyamhub-order-splitter.php';
require_once $thaaniyamhub_includes . 'class-thaaniyamhub-ledger.php';
require_once $thaaniyamhub_includes . 'class-thaaniyamhub-dashboard.php';
require_once $thaaniyamhub_includes . 'class-thaaniyamhub-pdf-report.php';
require_once $thaaniyamhub_includes . 'class-thaaniyamhub-order-history.php';
require_once $thaaniyamhub_includes . 'class-thaaniyamhub-cart-rules.php';
require_once $thaaniyamhub_includes . 'class-thaaniyamhub-order-id-handler.php';
require_once $thaaniyamhub_includes . 'class-thaaniyamhub-order-notes.php';

// Cashfree Vendor Payouts & Refunds Module
require_once $thaaniyamhub_includes . 'class-thaaniyamhub-cashfree-payout-api.php';
require_once $thaaniyamhub_includes . 'class-wcfmmp-gateway-cashfree-payout.php';
require_once $thaaniyamhub_includes . 'class-thaaniyamhub-cashfree-webhook.php';
require_once $thaaniyamhub_includes . 'class-thaaniyamhub-payout-scheduler.php';
require_once $thaaniyamhub_includes . 'class-thaaniyamhub-cashfree-refund-handler.php';
require_once $thaaniyamhub_includes . 'class-thaaniyamhub-settings.php';

// Initialize Cashfree split refund handler
ThaaniyamHub_Cashfree_Refund_Handler::init();

// =========================================================================
// ACTIVATION & DEACTIVATION HOOKS
// =========================================================================
register_activation_hook(__FILE__, ['ThaaniyamHub_DB_Install', 'run']);
register_deactivation_hook(__FILE__, ['ThaaniyamHub_Payout_Scheduler', 'clear_schedule']);

// =========================================================================
// REGISTER WOOCOMMERCE SETTINGS TAB
// =========================================================================
add_filter( 'woocommerce_get_settings_pages', function ( $settings ) {
    $settings[] = new ThaaniyamHub_Settings();
    return $settings;
} );

// Global Admin Post handlers for downloading/clearing logs
add_action( 'admin_post_thaaniyamhub_download_logs', 'thaaniyamhub_download_logs_handler' );
if (!function_exists('thaaniyamhub_download_logs_handler')) {
    function thaaniyamhub_download_logs_handler() {
        check_admin_referer( 'thaaniyamhub_download_logs' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to download this log file.', 'thaaniyamhub-multi-vendor-orders' ) );
        }

        $log_file = thaaniyamhub_get_latest_wc_log_file();

        if ( $log_file && file_exists( $log_file ) ) {
            $filename = basename( $log_file );
            header( 'Content-Description: File Transfer' );
            header( 'Content-Type: text/plain' );
            header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
            header( 'Expires: 0' );
            header( 'Cache-Control: must-revalidate' );
            header( 'Pragma: public' );
            header( 'Content-Length: ' . filesize( $log_file ) );
            readfile( $log_file );
            exit;
        } else {
            wp_die( esc_html__( 'Log file does not exist.', 'thaaniyamhub-multi-vendor-orders' ) );
        }
    }
}

add_action( 'admin_post_thaaniyamhub_clear_logs', 'thaaniyamhub_clear_logs_handler' );
if (!function_exists('thaaniyamhub_clear_logs_handler')) {
    function thaaniyamhub_clear_logs_handler() {
        check_admin_referer( 'thaaniyamhub_clear_logs' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to clear this log file.', 'thaaniyamhub-multi-vendor-orders' ) );
        }

        $log_file = thaaniyamhub_get_latest_wc_log_file();

        if ( $log_file && file_exists( $log_file ) ) {
            @file_put_contents( $log_file, '' );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=thaaniyamhub_settings&section=logs' ) );
        exit;
    }
}

// =========================================================================
// BOOTSTRAP ON plugins_loaded
// =========================================================================
add_action('plugins_loaded', function () {
    // Upgrade tables if schema version is behind (handles updates without
    // requiring deactivation/reactivation).
    ThaaniyamHub_DB_Install::maybe_upgrade();

    // Initialise Thaaniyam Hub modules (register their WordPress hooks).
    if ( class_exists( 'ThaaniyamHub_Order_Splitter' ) ) {
        ThaaniyamHub_Order_Splitter::init();
    }
    if ( class_exists( 'ThaaniyamHub_Dashboard' ) ) {
        ThaaniyamHub_Dashboard::init();
    }
    if ( class_exists( 'ThaaniyamHub_Ledger' ) ) {
        ThaaniyamHub_Ledger::init();
    }
    if ( class_exists( 'ThaaniyamHub_PDF_Report' ) ) {
        ThaaniyamHub_PDF_Report::init();
    }
    if ( class_exists( 'ThaaniyamHub_Order_History' ) ) {
        ThaaniyamHub_Order_History::init();
    }
    if ( class_exists( 'ThaaniyamHub_Cart_Rules' ) ) {
        ThaaniyamHub_Cart_Rules::init();
    }
    if ( class_exists( 'ThaaniyamHub_Order_ID_Handler' ) ) {
        ThaaniyamHub_Order_ID_Handler::init();
    }
    if ( class_exists( 'ThaaniyamHub_Tracker' ) && method_exists( 'ThaaniyamHub_Tracker', 'init' ) ) {
        ThaaniyamHub_Tracker::init();
    }
    if ( class_exists( 'ThaaniyamHub_Pickup_Logger' ) && method_exists( 'ThaaniyamHub_Pickup_Logger', 'init' ) ) {
        ThaaniyamHub_Pickup_Logger::init();
    }
    if ( class_exists( 'ThaaniyamHub_Pickup_Manager' ) && method_exists( 'ThaaniyamHub_Pickup_Manager', 'init' ) ) {
        ThaaniyamHub_Pickup_Manager::init();
    }
    if ( class_exists( 'ThaaniyamHub_Dimension_Manager' ) && method_exists( 'ThaaniyamHub_Dimension_Manager', 'init' ) ) {
        ThaaniyamHub_Dimension_Manager::init();
    }
    if ( class_exists( 'ThaaniyamHub_Dispatch' ) && method_exists( 'ThaaniyamHub_Dispatch', 'init' ) ) {
        ThaaniyamHub_Dispatch::init();
    }
    if ( class_exists( 'ThaaniyamHub_Order_Notes' ) ) {
        ThaaniyamHub_Order_Notes::init();
    }
    if ( class_exists( 'ThaaniyamHub_Cashfree_Webhook' ) ) {
        ThaaniyamHub_Cashfree_Webhook::get_instance();
    }
    if ( class_exists( 'ThaaniyamHub_Payout_Scheduler' ) ) {
        ThaaniyamHub_Payout_Scheduler::init();
    }
    if ( class_exists( 'ThaaniyamHub_Settings' ) ) {
        ThaaniyamHub_Settings::init();
    }
}, 5);

// Ensure Cashfree Gateway is registered in WCFMmp Gateways collection
add_action( 'wcfmmp_init', function() {
    global $WCFMmp;
    if ( isset( $WCFMmp->wcfmmp_gateways ) && class_exists( 'WCFMmp_Gateway_Cashfree' ) ) {
        if ( ! isset( $WCFMmp->wcfmmp_gateways->payment_gateways['cashfree'] ) ) {
            $WCFMmp->wcfmmp_gateways->payment_gateways['cashfree'] = new WCFMmp_Gateway_Cashfree();
        }
    }
} );

// =========================================================================
// ORDER LISTING & UI: Default WooCommerce & WCFM standard display is used.
// (Legacy DOM manipulation scripts removed)
// =========================================================================


// =========================================================================
// WCFM FATAL ERROR PROTECTION
// WCFM's page-analytics hook calls dokan_is_store_page() unconditionally when
// the marketplace type is 'dokan', but Dokan may not be active — causing a
// CRITICAL fatal error in wp_footer that prevents WooCommerce's checkout
// JavaScript (loaded at wp_footer priority 20) from being output.
// Fix: at wp_footer priority 1 (before WCFM's hook fires at priority 10),
// remove WCFM's analytics action when Dokan functions are unavailable.
// =========================================================================
add_action('wp_footer', function () {
    if (function_exists('dokan_is_store_page')) {
        return; // Dokan is active – nothing to fix.
    }
    global $WCFM_Frontend;
    if (!is_object($WCFM_Frontend)) {
        return;
    }
    // Remove the hook that calls dokan_is_store_page() without a function_exists guard.
    remove_action('wp_footer', array($WCFM_Frontend, 'wcfm_save_page_analytics_data'));
}, 1); // Priority 1 = runs before WCFM's default priority 10.

// =========================================================================
// SHIPPING ZONE AND PINCODE HELPER CLASS
// =========================================================================
class ThaaniyamHub_Shipping_Helper {
    public static function get_shipping_zone_from_pincode( $pincode ) {
        $pin = substr( preg_replace( '/[^0-9]/', '', $pincode ), 0, 3 );
        if ( strlen( $pin ) < 3 ) {
            return '';
        }

        $prefix_map = [
            // Zone A: Kongu Belt
            '624' => 'A', // Dindigul
            '633' => 'A', // Krishnagiri/Dharmapuri
            '634' => 'A', // Krishnagiri/Dharmapuri
            '635' => 'A', // Krishnagiri/Dharmapuri
            '636' => 'A', // Salem
            '637' => 'A', // Namakkal
            '638' => 'A', // Erode
            '639' => 'A', // Karur
            '640' => 'A', // Fallback/border
            '641' => 'A', // Coimbatore/Tiruppur
            '642' => 'A', // Pollachi/Anaimalai
            '643' => 'A', // Nilgiris

            // Zone B: South TN
            '615' => 'B', // Theni
            '616' => 'B', // Theni
            '617' => 'B', // Theni
            '618' => 'B', // Theni
            '619' => 'B', // Theni
            '623' => 'B', // Ramanathapuram
            '625' => 'B', // Madurai
            '626' => 'B', // Virudhunagar
            '627' => 'B', // Tirunelveli/Tenkasi
            '628' => 'B', // Thoothukudi
            '629' => 'B', // Kanyakumari
            '630' => 'B', // Sivagangai

            // Zone C: East TN
            '609' => 'C', // Nagapattinam/Mayiladuthurai
            '610' => 'C', // Tiruvarur
            '611' => 'C', // Nagapattinam
            '612' => 'C', // Thanjavur
            '613' => 'C', // Thanjavur
            '614' => 'C', // Thanjavur/Tiruvarur
            '620' => 'C', // Tiruchirappalli
            '621' => 'C', // Trichy/Perambalur/Ariyalur
            '622' => 'C', // Pudukkottai

            // Zone D: North + Northeast
            '600' => 'D', // Chennai
            '601' => 'D', // Tiruvallur/Kanchipuram
            '602' => 'D', // Tiruvallur/Kanchipuram
            '603' => 'D', // Kanchipuram/Chengalpattu
            '604' => 'D', // Villupuram
            '605' => 'D', // Puducherry/Villupuram/Cuddalore
            '606' => 'D', // Tiruvannamalai/Kallakurichi
            '607' => 'D', // Cuddalore
            '608' => 'D', // Cuddalore
            '631' => 'D', // Vellore/Ranipet/Kanchipuram
            '632' => 'D', // Vellore/Ranipet/Tirupattur
        ];

        if ( isset( $prefix_map[ $pin ] ) ) {
            return $prefix_map[ $pin ];
        }

        return ''; // Unknown or not in TN/PY
    }

    public static function parse_zone_0_rules() {
        $rules_text = get_option( 'thaaniyamhub_zone_0_rules', "641 => 641 (exclude: 6416)\n6416 => 6416\n642 => 642\n637 => 637" );
        $rules_text = html_entity_decode( $rules_text );
        $rules_text = preg_replace( '/<br\s*\/?>/i', "\n", $rules_text );
        $rules_text = wp_strip_all_tags( $rules_text );
        $lines = explode( "\n", $rules_text );
        $rules = [];
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( empty( $line ) || strpos( $line, '=>' ) === false ) {
                continue;
            }
            list( $v_part, $c_part ) = explode( '=>', $line, 2 );
            $v_prefix = trim( $v_part );
            
            // Parse customer prefixes and exclusions
            $c_part = trim( $c_part );
            $excludes = [];
            if ( preg_match( '/\(exclude:\s*([^)]+)\)/i', $c_part, $matches ) ) {
                $exclude_str = $matches[1];
                $excludes = array_map( 'trim', explode( ',', $exclude_str ) );
                $c_part = preg_replace( '/\(exclude:\s*[^)]+\)/i', '', $c_part );
            }
            
            $c_prefixes = array_map( 'trim', explode( ',', $c_part ) );
            $c_prefixes = array_filter( $c_prefixes );
            
            $rules[] = [
                'vendor_prefix' => $v_prefix,
                'customer_prefixes' => $c_prefixes,
                'excludes' => $excludes,
            ];
        }
        return $rules;
    }

    public static function is_zone_0( $vendor_pincode, $customer_pincode ) {
        $v_pin = preg_replace( '/[^0-9]/', '', $vendor_pincode );
        $c_pin = preg_replace( '/[^0-9]/', '', $customer_pincode );
        
        if ( empty( $v_pin ) || empty( $c_pin ) ) {
            return false;
        }
        
        $rules = self::parse_zone_0_rules();
        
        // Sort rules by vendor_prefix length descending, so more specific rules (like 6416) match before general ones (like 641)
        usort( $rules, function( $a, $b ) {
            return strlen( $b['vendor_prefix'] ) - strlen( $a['vendor_prefix'] );
        });
        
        foreach ( $rules as $rule ) {
            // If vendor pincode matches this rule's vendor_prefix
            if ( strpos( $v_pin, $rule['vendor_prefix'] ) === 0 ) {
                // Check if customer pincode matches any of the customer prefixes
                $matched_prefix = false;
                foreach ( $rule['customer_prefixes'] as $c_pref ) {
                    if ( strpos( $c_pin, $c_pref ) === 0 ) {
                        $matched_prefix = true;
                        break;
                    }
                }
                
                if ( $matched_prefix ) {
                    // Check exclusions
                    foreach ( $rule['excludes'] as $ex_pref ) {
                        if ( strpos( $c_pin, $ex_pref ) === 0 ) {
                            return false; // Excluded!
                        }
                    }
                    return true; // Match!
                }
                
                // If the vendor prefix matched, but customer didn't match the customer prefix or was excluded,
                // we should not continue checking other rules for this vendor.
                return false;
            }
        }
        
        // Default fallback: match first 3 digits if no rules found
        return substr( $v_pin, 0, 3 ) === substr( $c_pin, 0, 3 );
    }

    public static function normalize_state_code( $state ) {
        $state = trim( $state );
        if ( empty( $state ) ) {
            return '';
        }
        if ( strlen( $state ) === 2 ) {
            return strtoupper( $state );
        }

        $map = [
            'tamil nadu' => 'TN', 'tamilnadu' => 'TN',
            'kerala' => 'KL',
            'karnataka' => 'KA',
            'andhra pradesh' => 'AP', 'andhrapradesh' => 'AP',
            'telangana' => 'TS',
            'maharashtra' => 'MH',
            'goa' => 'GA',
            'gujarat' => 'GJ',
            'rajasthan' => 'RJ',
            'punjab' => 'PB',
            'haryana' => 'HR',
            'himachal pradesh' => 'HP',
            'uttarakhand' => 'UT', 'uttaranchal' => 'UA',
            'uttar pradesh' => 'UP',
            'bihar' => 'BR',
            'jharkhand' => 'JH',
            'west bengal' => 'WB',
            'odisha' => 'OD', 'orissa' => 'OR',
            'chhattisgarh' => 'CG',
            'madhya pradesh' => 'MP',
            'delhi' => 'DL',
            'jammu and kashmir' => 'JK',
            'ladakh' => 'LA',
            'puducherry' => 'PY', 'pondicherry' => 'PY',
        ];

        $lower = strtolower( $state );
        if ( isset( $map[ $lower ] ) ) {
            return $map[ $lower ];
        }

        return strtoupper( substr( $state, 0, 2 ) );
    }
}

// Custom Shiprocket Shipping Method implementation to support WCFM vendors and make it settings-configurable and update-safe.
add_action('woocommerce_shipping_init', 'init_custom_shiprocket_shipping_method', 25);
if (!function_exists('init_custom_shiprocket_shipping_method')) {
function init_custom_shiprocket_shipping_method()
{
    if (!class_exists('Shiprocket_Woocommerce_Shipping_Method')) {
        return;
    }
    if (class_exists('Custom_Shiprocket_Shipping_Method')) {
        return;
    }

    class Custom_Shiprocket_Shipping_Method extends Shiprocket_Woocommerce_Shipping_Method
    {
        public static function get_shipping_zone_from_pincode( $pincode ) {
            return ThaaniyamHub_Shipping_Helper::get_shipping_zone_from_pincode( $pincode );
        }

        public static function parse_zone_0_rules() {
            return ThaaniyamHub_Shipping_Helper::parse_zone_0_rules();
        }

        public static function is_zone_0( $vendor_pincode, $customer_pincode ) {
            return ThaaniyamHub_Shipping_Helper::is_zone_0( $vendor_pincode, $customer_pincode );
        }

        public static function normalize_state_code( $state ) {
            return ThaaniyamHub_Shipping_Helper::normalize_state_code( $state );
        }

        public function __construct($instance_id = 0)
        {
            parent::__construct($instance_id);
        }

        /**
         * Initialize custom WooCommerce settings options for this shipping method.
         */
        public function init_form_fields()
        {
            if ( method_exists( 'Shiprocket_Woocommerce_Shipping_Method', 'init_form_fields' ) ) {
                parent::init_form_fields();
            }
        }

        public function prepare_rate($shipping_method_detail)
        {
            $prepaid_only = thaaniyamhub_get_shipping_setting('prepaid_only', 'yes');
            if ('yes' === $prepaid_only && isset($shipping_method_detail->cod)) {
                $shipping_method_detail->cod = 0;
            }

            $courier_name = !empty($shipping_method_detail->courier_name) ? $shipping_method_detail->courier_name : '';
            if (!empty($courier_name)) {
                $rate_name = $courier_name;
            } else {
                $rate_name = !empty($this->title) ? $this->title : __('Standard Shipping', 'thaaniyamhub-multi-vendor-orders');
            }

            if (isset($shipping_method_detail->carrier_id) && !empty($shipping_method_detail->etd) && in_array($shipping_method_detail->carrier_id, array('fallback_rate', 'flat_rate'), true)) {
                $rate_name = $shipping_method_detail->etd;
            } elseif (!empty($shipping_method_detail->etd)) {
                $rate_name .= ' (Delivery by ' . $shipping_method_detail->etd . ')';
            }

            $rate_id = 1;
            if (isset($shipping_method_detail->courier_company_id)) {
                if (isset($shipping_method_detail->cod) && $shipping_method_detail->cod) {
                    $rate_id = $this->id . '_cod:' . $shipping_method_detail->courier_company_id;
                } else {
                    $rate_id = $this->id . '_prepaid:' . $shipping_method_detail->courier_company_id;
                }
            }

            $rate_cost = $shipping_method_detail->rate;

            $this->found_rates[$rate_id] = array(
                'id' => $rate_id,
                'label' => $rate_name,
                'cost' => $rate_cost,
                'taxes' => !empty(self::$tax_calculation_mode) ? '' : false,
                'calc_tax' => self::$tax_calculation_mode,
                'meta_data' => array(
                    'ph_shiprocket_shipping_rates' => array(
                        'courier_company_id' => $shipping_method_detail->courier_company_id ?? 0,
                        'uniqueId' => WC()->session->get('ph_shiprocket_rates_unique_id'),
                        'serviceId' => $shipping_method_detail->courier_name ?? '',
                        'carrierId' => $shipping_method_detail->courier_company_id ?? 0,
                        'shiprocketTransactionId' => self::$shiprocket_transaction_id,
                    ),
                ),
            );
        }

        public function process_result($body)
        {
            if ((200 === $body->status || '200' === $body->status) && !empty($body->data)) {
                $json_decoded_data = $body->data;
                $display_mode = thaaniyamhub_get_shipping_setting('courier_display_mode', 'recommended');
                $selected_courier = null;
                if ('lowest' === $display_mode) {
                    $selected_courier = $this->select_lowest_courier($json_decoded_data);
                } else {
                    $selected_courier = $this->select_recommended_courier($json_decoded_data);
                }

                if (null !== $selected_courier) {
                    self::log('Selected courier partner: ' . ($selected_courier->courier_name ?? '') . ' (ID: ' . ($selected_courier->courier_company_id ?? '') . ')');
                    $this->prepare_rate($selected_courier);
                }
            }
        }

        public static function log($message)
        {
            thaaniyamhub_log($message);
        }

        /**
         * Group package contents by vendor and create virtual sub-packages.
         */
        public static function split_package_by_vendor($package)
        {
            $sub_packages = array();

            if (empty($package['contents'])) {
                return array($package);
            }

            foreach ($package['contents'] as $key => $cart_item) {
                $product_id = $cart_item['product_id'];
                $vendor_id = 0;
                if (function_exists('wcfm_get_vendor_id_by_post')) {
                    $vendor_id = (int) wcfm_get_vendor_id_by_post($product_id);
                }
                if ($vendor_id <= 0) {
                    $vendor_id = (int) get_post_field('post_author', $product_id);
                }

                if (!isset($sub_packages[$vendor_id])) {
                    $sub_packages[$vendor_id] = $package;
                    $sub_packages[$vendor_id]['contents'] = array();
                    $sub_packages[$vendor_id]['vendor_id'] = $vendor_id;
                }

                $sub_packages[$vendor_id]['contents'][$key] = $cart_item;
            }

            return $sub_packages;
        }

        /**
         * Select the recommended or fallback courier partner from serviceability data.
         */
        public function select_recommended_courier($json_decoded_data)
        {
            $available_courier_companies = $json_decoded_data->available_courier_companies ?? array();
            if (is_array($available_courier_companies) && !empty($available_courier_companies)) {
                // Filter out blocked or suppressed couriers
                $filtered_couriers = array();
                foreach ($available_courier_companies as $courier) {
                    // Skip blocked courier partners
                    if (isset($courier->blocked) && ($courier->blocked == 1 || $courier->blocked === true)) {
                        continue;
                    }

                    // Skip suppressed couriers
                    if (isset($courier->suppression_dates)) {
                        $supp = $courier->suppression_dates;
                        if (!empty($supp->blocked_fm) || !empty($supp->blocked_lm)) {
                            continue;
                        }
                    }

                    $filtered_couriers[] = $courier;
                }

                if (empty($filtered_couriers)) {
                    return null;
                }

                $recommended_id = isset($json_decoded_data->recommended_courier_company_id) ? $json_decoded_data->recommended_courier_company_id : null;
                $selected_courier = null;

                if (!empty($recommended_id)) {
                    foreach ($filtered_couriers as $courier) {
                        if (isset($courier->courier_company_id) && $courier->courier_company_id == $recommended_id) {
                            $selected_courier = $courier;
                            break;
                        }
                    }
                }

                // Fallback to first available if recommended not found or not set
                if (null === $selected_courier) {
                    $selected_courier = $filtered_couriers[0];
                }

                return $selected_courier;
            }
            return null;
        }

        /**
         * Select the lowest cost or fallback courier partner from serviceability data.
         */
        public function select_lowest_courier($json_decoded_data)
        {
            $available_courier_companies = $json_decoded_data->available_courier_companies ?? array();
            if (is_array($available_courier_companies) && !empty($available_courier_companies)) {
                // Filter out blocked or suppressed couriers
                $filtered_couriers = array();
                foreach ($available_courier_companies as $courier) {
                    // Skip blocked courier partners
                    if (isset($courier->blocked) && ($courier->blocked == 1 || $courier->blocked === true)) {
                        continue;
                    }

                    // Skip suppressed couriers
                    if (isset($courier->suppression_dates)) {
                        $supp = $courier->suppression_dates;
                        if (!empty($supp->blocked_fm) || !empty($supp->blocked_lm)) {
                            continue;
                        }
                    }

                    $filtered_couriers[] = $courier;
                }

                if (empty($filtered_couriers)) {
                    return null;
                }

                $lowest_courier = null;
                foreach ($filtered_couriers as $courier) {
                    if (isset($courier->rate)) {
                        if (null === $lowest_courier || floatval($courier->rate) < floatval($lowest_courier->rate)) {
                            $lowest_courier = $courier;
                        }
                    }
                }

                // Fallback to first available if lowest not found
                if (null === $lowest_courier) {
                    $lowest_courier = $filtered_couriers[0];
                }

                return $lowest_courier;
            }
            return null;
        }

        public static function get_district_from_pincode( $pincode ) {
            $pin = substr( preg_replace( '/[^0-9]/', '', $pincode ), 0, 3 );
            if ( strlen( $pin ) < 3 ) {
                return '';
            }

            $map = [
                '641' => 'Coimbatore District',
                '642' => 'Coimbatore District',
                '643' => 'Nilgiris District',
                '638' => 'Erode District',
            ];

            if ( isset( $map[ $pin ] ) ) {
                return $map[ $pin ];
            }

            return $pin; // Fallback to 3-digit prefix
        }

        public function calculate_standard_shipping_rate($package, $sub_packages)
        {
            $zone_0_rate      = (float) get_option( 'thaaniyamhub_shipping_rate_intracity', 50 );
            $zone_a_rate      = (float) get_option( 'thaaniyamhub_shipping_rate_zone_a', 70 );
            $zone_b_rate      = (float) get_option( 'thaaniyamhub_shipping_rate_zone_b', 90 );
            $zone_c_rate      = (float) get_option( 'thaaniyamhub_shipping_rate_zone_c', 90 );
            $zone_d_rate      = (float) get_option( 'thaaniyamhub_shipping_rate_zone_d', 120 );
            $neighboring_rate = (float) get_option( 'thaaniyamhub_shipping_rate_interstate_neighboring', 120 );
            $vendor_increment = (float) get_option( 'thaaniyamhub_shipping_vendor_increment', 40 );
            $free_threshold   = (float) get_option( 'thaaniyamhub_shipping_free_threshold', 899 );
            $free_threshold_d = (float) get_option( 'thaaniyamhub_shipping_free_threshold_zone_d', 1199 );

            $customer_postcode = trim( $package['destination']['postcode'] ?? '' );
            $customer_state    = trim( $package['destination']['state'] ?? '' );
            if ( empty( $customer_postcode ) || empty( $customer_state ) ) {
                self::log( 'calculate_standard_shipping_rate aborted: Customer destination postcode or state is empty.' );
                return [ 'total' => false, 'vendor_rates' => [] ];
            }

            $vendor_individual_rates = [];
            $highest_zone_rate       = 0.0;
            $highest_zone_vendor_id  = null;

            foreach ($sub_packages as $vendor_id => $sub_pkg) {
                $vendor_postcode = '';
                $vendor_state    = '';

                $assigned_pickup = '';
                if ( class_exists( 'ThaaniyamHub_Dispatch' ) && method_exists( 'ThaaniyamHub_Dispatch', 'resolve_pickup_nickname' ) ) {
                    $assigned_pickup = ThaaniyamHub_Dispatch::resolve_pickup_nickname( $vendor_id );
                } else {
                    $assigned_pickup = get_user_meta( $vendor_id, '_shiprocket_pickup_id', true );
                    if ( ! $assigned_pickup ) {
                        $pickup_map      = get_option( 'thaaniyamhub_vendor_pickup_map', [] );
                        $assigned_pickup = $pickup_map[ $vendor_id ] ?? '';
                    }
                }
                if ( $assigned_pickup ) {
                    if ( class_exists( 'ThaaniyamHub_Dashboard' ) && method_exists( 'ThaaniyamHub_Dashboard', 'resolve_pickup_details' ) ) {
                        $loc_details = ThaaniyamHub_Dashboard::resolve_pickup_details( (string) $assigned_pickup, $vendor_id );
                        if ( ! empty( $loc_details['pincode'] ) ) {
                            $vendor_postcode = $loc_details['pincode'];
                        }
                        if ( ! empty( $loc_details['state'] ) ) {
                            $vendor_state = $loc_details['state'];
                        }
                    } elseif ( class_exists( 'ThaaniyamHub_Dashboard' ) && method_exists( 'ThaaniyamHub_Dashboard', 'resolve_pickup_pincode' ) ) {
                        $resolved = ThaaniyamHub_Dashboard::resolve_pickup_pincode( (string) $assigned_pickup, $vendor_id );
                        if ( $resolved ) {
                            $vendor_postcode = $resolved;
                        }
                    }
                }

                if ( empty( $assigned_pickup ) || empty( $vendor_postcode ) ) {
                    self::log( "Shipping calculation aborted: Vendor #{$vendor_id} does not have an assigned and verified Shiprocket pickup location." );
                    return [ 'total' => false, 'vendor_rates' => [] ];
                }
                if ( empty( $vendor_state ) ) {
                    $vendor_state = get_option( 'woocommerce_store_state', '' );
                }

                $v_state_code = self::normalize_state_code( $vendor_state );
                $c_state_code = self::normalize_state_code( $customer_state );

                $zone_rate = 0.0;

                // 1. Check if same city (Zone 0) based on rules
                if ( self::is_zone_0( $vendor_postcode, $customer_postcode ) ) {
                    $zone_rate = $zone_0_rate;
                } 
                // 2. Check if customer is in Tamil Nadu or Pondicherry
                elseif ( $c_state_code === 'TN' || $c_state_code === 'PY' ) {
                    $czone = self::get_shipping_zone_from_pincode( $customer_postcode );
                    if ( $czone === 'A' ) {
                        $zone_rate = $zone_a_rate;
                    } elseif ( $czone === 'B' ) {
                        $zone_rate = $zone_b_rate;
                    } elseif ( $czone === 'C' ) {
                        $zone_rate = $zone_c_rate;
                    } elseif ( $czone === 'D' ) {
                        $zone_rate = $zone_d_rate;
                    } else {
                        $zone_rate = $zone_a_rate; // Fallback to Kongu Belt (Zone A) within TN
                    }
                } 
                // 3. Otherwise, must be in neighboring states (KL, KA, AP)
                else {
                    $zone_rate = $neighboring_rate;
                }

                $vendor_individual_rates[ $vendor_id ] = $zone_rate;
                if ( $zone_rate > $highest_zone_rate || $highest_zone_vendor_id === null ) {
                    $highest_zone_rate      = $zone_rate;
                    $highest_zone_vendor_id = $vendor_id;
                }
            }

            $num_vendors   = count( $sub_packages );
            $extra_vendors = max( 0, $num_vendors - 1 );
            $surcharge     = $extra_vendors * $vendor_increment;

            $standard_total = $highest_zone_rate + $surcharge;

            $cart_subtotal = 0.0;
            foreach ( $package['contents'] as $item ) {
                $cart_subtotal += $item['line_subtotal'];
            }

            // Resolve customer shipping zone to pick the correct free shipping threshold
            $customer_zone = self::get_shipping_zone_from_pincode( $customer_postcode );
            $active_threshold = ( $customer_zone === 'D' ) ? $free_threshold_d : $free_threshold;

            if ( $active_threshold > 0 && $cart_subtotal >= $active_threshold ) {
                $standard_total = 0.0;
            }

            $vendor_shipping_rates = [];
            foreach ( $sub_packages as $vendor_id => $sub_pkg) {
                if ( $standard_total <= 0.0 ) {
                    $rate = 0.0;
                } elseif ( (string) $vendor_id === (string) $highest_zone_vendor_id ) {
                    $rate = round( $highest_zone_rate, 2 );
                } else {
                    $rate = round( $vendor_increment, 2 );
                }
                $vendor_shipping_rates[ $vendor_id ] = $rate;
            }


            return [
                'total'        => $standard_total,
                'vendor_rates' => $vendor_shipping_rates,
            ];
        }

        public function calculate_shipping($package = array())
        {
            $GLOBALS['thaaniyamhub_in_shipping_calculation'] = true;
            try {
                self::log('--- START SHIPPING CALCULATION ---');

                $customer_postcode = trim( $package['destination']['postcode'] ?? '' );
                $customer_state    = trim( $package['destination']['state'] ?? '' );
                if ( empty( $customer_postcode ) || empty( $customer_state ) ) {
                    self::log( 'Shipping calculation aborted: Customer destination postcode or state is empty (awaiting user address input).' );
                    return;
                }
                $c_state_code = self::normalize_state_code( $customer_state );
                $allowed_states = [ 'TN', 'PY', 'KL', 'KA', 'AP' ];
                if ( ! empty( $c_state_code ) && ! in_array( $c_state_code, $allowed_states, true ) ) {
                    self::log("Shipping calculation aborted: Customer state '{$c_state_code}' is not in the allowed list: " . implode(', ', $allowed_states));
                    return;
                }

                self::log('Input Package Destination: ' . print_r($package['destination'] ?? [], true));
                self::log('Input Package Items Summary: ' . print_r(array_map(function($item) {
                     return [
                         'product_id' => $item['product_id'] ?? 0,
                         'quantity' => $item['quantity'] ?? 0,
                         'line_total' => $item['line_total'] ?? 0,
                     ];
                }, $package['contents'] ?? []), true));

                $sub_packages = self::split_package_by_vendor($package);
                self::log('Split cart into ' . count($sub_packages) . ' vendor packages: ' . print_r(array_map(function($sub_pkg) {
                     return [
                         'vendor_id' => $sub_pkg['vendor_id'] ?? 0,
                         'items_count' => count($sub_pkg['contents'] ?? []),
                     ];
                }, $sub_packages), true));

                $this->found_rates = array();

                if (empty(self::$weight_unit)) {
                    self::$weight_unit = get_option('woocommerce_weight_unit');
                }
                if (empty(self::$dimension_unit)) {
                    self::$dimension_unit = get_option('woocommerce_dimension_unit');
                }
                if (empty(self::$currency_code)) {
                    self::$currency_code = get_woocommerce_currency();
                }

                $flat_rate_calculated = false;
                $rates_loaded = false;

                try {
                    // 1. Calculate Standard Shipping (Zone Flat Rate + Surcharge)
                    $std_result = $this->calculate_standard_shipping_rate($package, $sub_packages);
                    self::log('Standard Shipping calculation input/output: ' . print_r($std_result, true));
                    if (isset($std_result['total']) && $std_result['total'] !== false) {
                        $std_rate_id = $this->id . '_standard_shipping';
                        $this->found_rates[$std_rate_id] = array(
                            'id'       => $std_rate_id,
                            'label'    => __('Standard Shipping', 'thaaniyamhub-multi-vendor-orders'),
                            'cost'     => $std_result['total'],
                            'taxes'    => !empty(self::$tax_calculation_mode) ? '' : false,
                            'calc_tax' => self::$tax_calculation_mode,
                        );

                        if (isset(WC()->session)) {
                            WC()->session->set('thaaniyamhub_standard_shipping_vendor_rates', $std_result['vendor_rates']);
                            // Default fallback:
                            WC()->session->set('thaaniyamhub_vendor_shipping_rates', $std_result['vendor_rates']);
                        }
                        $flat_rate_calculated = true;
                        self::log('Standard Shipping Flat Rate successfully registered: ' . $std_result['total']);
                    }
                } catch (Exception $e) {
                    self::log('Standard Shipping Flat Rate calculation failed: ' . $e->getMessage());
                }

                // 2. Fallback: Calculate Low Cost Shipping (Live lowest courier rates from Shiprocket) if Flat Rate failed
                if (!$flat_rate_calculated) {
                    self::log('Fallback: standard flat rate calculation failed/skipped. Attempting live Shiprocket API rates...');
                    if ('yes' === $this->realtime_enabled && !empty(self::$integration_id) && !empty($package['destination']['postcode'])) {
                        $low_cost_vendor_rates = [];
                        $total_low_cost = 0;
                        $courier_names = [];
                        $courier_company_ids = [];
                        $max_etd_date_str = '';
                        $max_etd_timestamp = 0;
                        $success_count = 0;

                        foreach ($sub_packages as $vendor_id => $sub_pkg) {
                            $formatted_sub_pkg = static::get_formatted_data($sub_pkg);
                            self::log("Querying Shiprocket API for rates of vendor #{$vendor_id} package. Payload: " . print_r($formatted_sub_pkg, true));
                            $response = $this->get_rates_from_server($formatted_sub_pkg);
                            self::log("Shiprocket API rates response for vendor #{$vendor_id}: " . print_r($response, true));

                            if (isset($response->status) && (200 === $response->status || '200' === $response->status) && !empty($response->data)) {
                                $selected_courier = $this->select_lowest_courier($response->data);
                                if ($selected_courier) {
                                    $rate_cost = floatval($selected_courier->rate);
                                    $total_low_cost += $rate_cost;
                                    $low_cost_vendor_rates[$vendor_id] = $rate_cost;
                                    $success_count++;

                                    self::log("Lowest courier selected for vendor #{$vendor_id}: " . ($selected_courier->courier_name ?? '') . " (Cost: ₹{$rate_cost})");

                                    if (isset($selected_courier->courier_company_id)) {
                                        $courier_company_ids[] = $selected_courier->courier_company_id;
                                    }
                                    if (isset($selected_courier->courier_name)) {
                                        $courier_names[] = $selected_courier->courier_name;
                                    }
                                    if (!empty($selected_courier->etd)) {
                                        $etd_time = strtotime($selected_courier->etd);
                                        if ($etd_time > $max_etd_timestamp) {
                                            $max_etd_timestamp = $etd_time;
                                            $max_etd_date_str = $selected_courier->etd;
                                        }
                                    }
                                } else {
                                    self::log("No eligible courier selected from response for vendor #{$vendor_id}.");
                                }
                            } else {
                                self::log("Invalid or empty response for vendor #{$vendor_id}. Response: " . print_r($response, true));
                            }
                        }

                        if ($success_count > 0 && $success_count === count($sub_packages)) {
                            $low_rate_id = $this->id . '_low_cost_shipping';
                            $label = __('Low Cost Shipping', 'thaaniyamhub-multi-vendor-orders');
                            if (!empty($max_etd_date_str)) {
                                $label .= ' (Delivery by ' . $max_etd_date_str . ')';
                            }
                            $this->found_rates[$low_rate_id] = array(
                                'id'       => $low_rate_id,
                                'label'    => $label,
                                'cost'     => $total_low_cost,
                                'taxes'    => !empty(self::$tax_calculation_mode) ? '' : false,
                                'calc_tax' => self::$tax_calculation_mode,
                                'meta_data' => array(
                                    'ph_shiprocket_shipping_rates' => array(
                                        'courier_company_id' => implode('_', $courier_company_ids),
                                        'uniqueId' => WC()->session->get('ph_shiprocket_rates_unique_id'),
                                        'serviceId' => implode(', ', $courier_names),
                                        'carrierId' => implode('_', $courier_company_ids),
                                        'shiprocketTransactionId' => self::$shiprocket_transaction_id,
                                    ),
                                ),
                            );
                            if (isset(WC()->session)) {
                                WC()->session->set('thaaniyamhub_low_cost_shipping_vendor_rates', $low_cost_vendor_rates);
                                WC()->session->set('thaaniyamhub_vendor_shipping_rates', $low_cost_vendor_rates);
                            }
                            $rates_loaded = true;
                            self::log('Fallback to Low Cost Shipping successful. Registered total: ' . $total_low_cost);
                        } else {
                            self::log("Fallback to Low Cost Shipping failed because success_count ({$success_count}) != sub_packages count (" . count($sub_packages) . ")");
                        }
                    }
                }

                // 3. Ultimate Fallback: Default fallback rate generator
                if (!$rates_loaded && !$flat_rate_calculated) {
                    self::log('Fallback: Attempting ultimate fallback rate generator...');
                    if (self::$fallback_rate && $this->shipping_title) {
                        $this->fallbackRateGenerator(count($sub_packages));
                        if (isset(WC()->session)) {
                            $fallback_vendor_rates = [];
                            foreach ($sub_packages as $vendor_id => $sub_pkg) {
                                $fallback_vendor_rates[$vendor_id] = floatval(self::$fallback_rate);
                            }
                            WC()->session->set('thaaniyamhub_vendor_shipping_rates', $fallback_vendor_rates);
                        }
                        self::log('Fallback to standard fallback rate generator completed. Fallback rate per vendor: ' . self::$fallback_rate);
                    } else {
                        self::log('Fallback: Fallback rate or shipping title not configured.');
                    }
                }

                self::log('Final Registered WooCommerce Shipping Rates: ' . print_r($this->found_rates, true));
                self::log('--- END SHIPPING CALCULATION ---');
                $this->add_found_rates();
            } finally {
                unset($GLOBALS['thaaniyamhub_in_shipping_calculation']);
            }
        }

        public static function get_formatted_data($package)
        {
            $prepaid_only = thaaniyamhub_get_shipping_setting('prepaid_only', 'yes');
            $custom_site_url = trim(thaaniyamhub_get_shipping_setting('custom_site_url', ''));
            $default_weight = floatval(thaaniyamhub_get_shipping_setting('default_weight', 0.1));
            $additional_weight = floatval(thaaniyamhub_get_shipping_setting('additional_weight', 0.0));

            // Resolve vendor-scoped additional weight override from WCFM settings
            $vendor_id = 0;
            if (isset($package['vendor_id'])) {
                $vendor_id = intval($package['vendor_id']);
            } else {
                foreach ($package['contents'] as $line_item) {
                    $product_id = $line_item['product_id'] ?? 0;
                    if ($product_id <= 0 && isset($line_item['data'])) {
                        $product_id = $line_item['data']->get_id();
                    }
                    if ($product_id > 0) {
                        if (function_exists('wcfm_get_vendor_id_by_post')) {
                            $vendor_id = intval(wcfm_get_vendor_id_by_post($product_id));
                        }
                        if (!$vendor_id) {
                            $vendor_id = intval(get_post_field('post_author', $product_id));
                        }
                        if ($vendor_id > 0) {
                            break;
                        }
                    }
                }
            }
            if ($vendor_id > 0) {
                $vendor_additional_weight = get_user_meta($vendor_id, '_shiprocket_additional_weight', true);
                if ($vendor_additional_weight !== '') {
                    $additional_weight = floatval($vendor_additional_weight);
                }
            }
            $default_length = floatval(thaaniyamhub_get_shipping_setting('default_length', 1.0));
            $default_width = floatval(thaaniyamhub_get_shipping_setting('default_width', 1.0));
            $default_height = floatval(thaaniyamhub_get_shipping_setting('default_height', 1.0));
            $default_pickup_postcode = trim(thaaniyamhub_get_shipping_setting('default_pickup_postcode', ''));
            $volumetric_divisor = intval(thaaniyamhub_get_shipping_setting('volumetric_divisor', 5000));
            if ($volumetric_divisor <= 0) {
                $volumetric_divisor = 5000;
            }

            $declared_value = 0;
            if (isset($package['cart_subtotal'])) {
                $declared_value = $package['cart_subtotal'];
            } else {
                $declared_value = WC()->cart->cart_contents_total + WC()->cart->tax_total;
            }
            $l = 0;
            $b = 0;
            $h = 0;
            $w = 0;
            foreach ($package['contents'] as $key => $line_item) {
                $quantity = $line_item['quantity'];
                if (!empty($line_item['data']->get_weight())) {
                    $w += $line_item['data']->get_weight() * $quantity;
                }
                $temp = array($line_item['data']->get_length(), $line_item['data']->get_width(), $line_item['data']->get_height());
                sort($temp);
                $h += empty($temp[0]) || !is_numeric($temp[0]) ? 0 : $temp[0];
                $l = max($l, empty($temp[1]) || !is_numeric($temp[1]) ? 0 : $temp[1]);
                $b = max($b, empty($temp[2]) || !is_numeric($temp[2]) ? 0 : $temp[2]);
            }

            if (!empty(self::$weight_unit)) {
                $weight_unit_lower = strtolower(self::$weight_unit);
                if (in_array($weight_unit_lower, array('g', 'grams'), true)) {
                    $w /= 1000;
                } elseif ('lbs' === $weight_unit_lower) {
                    $w *= 0.45359237;
                } elseif ('oz' === $weight_unit_lower) {
                    $w *= 0.028349523;
                }
            }

            // Ensure fallback weight if empty or 0
            if (empty($w) || $w <= 0) {
                $w = $default_weight;
            }

            // Add global default additional weight (packaging weight)
            $w += $additional_weight;

            if (!empty(self::$dimension_unit)) {
                $dimension_unit_lower = strtolower(self::$dimension_unit);
                if (in_array($dimension_unit_lower, array('in', 'inches'), true)) {
                    $l *= 2.54;
                    $b *= 2.54;
                    $h *= 2.54;
                } elseif ('m' === $dimension_unit_lower) {
                    $l *= 100;
                    $b *= 100;
                    $h *= 100;
                } elseif ('mm' === $dimension_unit_lower) {
                    $l /= 10;
                    $b /= 10;
                    $h /= 10;
                } elseif ('yd' === $dimension_unit_lower) {
                    $l *= 91.44;
                    $b *= 91.44;
                    $h *= 91.44;
                }
            }

            // Ensure fallback dimensions if empty or 0
            if (empty($l) || $l <= 0) {
                $l = $default_length;
            }
            if (empty($b) || $b <= 0) {
                $b = $default_width;
            }
            if (empty($h) || $h <= 0) {
                $h = $default_height;
            }

            // -------------------------------------------------------
            // Applicable weight = max(actual weight, volumetric weight)
            // Volumetric weight formula: L (cm) × W (cm) × H (cm) ÷ divisor
            // -------------------------------------------------------
            $volumetric_weight = round(($l * $b * $h) / $volumetric_divisor, 4);
            $applicable_weight = max($w, $volumetric_weight);
            self::log(
                sprintf(
                    'Weight calculation — Actual: %s kg | Volumetric: %s kg (L=%s × W=%s × H=%s ÷ %s) | Applicable: %s kg',
                    $w,
                    $volumetric_weight,
                    $l,
                    $b,
                    $h,
                    $volumetric_divisor,
                    $applicable_weight
                )
            );

            // Determine Site URL Override
            $store_url = $custom_site_url;
            if (empty($store_url)) {
                $store_url = get_site_url();
            }

            // Strict Pickup Postcode Resolution: Must be resolved from assigned Shiprocket pickup location.
            $pickup_postcode = '';
            $vid = isset( $package['vendor_id'] ) ? (int) $package['vendor_id'] : 0;
            if ( ! $vid && ! empty( $package['contents'] ) ) {
                foreach ( $package['contents'] as $cart_item ) {
                    if ( isset( $cart_item['data'] ) ) {
                        $pid = $cart_item['data']->get_id();
                        $vid = (int) get_post_field( 'post_author', $pid );
                        if ( $vid > 0 ) {
                            break;
                        }
                    }
                }
            }
            if ( $vid > 0 ) {
                $assigned_pickup = '';
                if ( class_exists( 'ThaaniyamHub_Dispatch' ) && method_exists( 'ThaaniyamHub_Dispatch', 'resolve_pickup_nickname' ) ) {
                    $assigned_pickup = ThaaniyamHub_Dispatch::resolve_pickup_nickname( $vid );
                } else {
                    $assigned_pickup = get_user_meta( $vid, '_shiprocket_pickup_id', true );
                    if ( ! $assigned_pickup ) {
                        $pickup_map      = get_option( 'thaaniyamhub_vendor_pickup_map', [] );
                        $assigned_pickup = $pickup_map[ $vid ] ?? '';
                    }
                }
                if ( $assigned_pickup ) {
                    if ( class_exists( 'ThaaniyamHub_Dashboard' ) && method_exists( 'ThaaniyamHub_Dashboard', 'resolve_pickup_pincode' ) ) {
                        $pickup_postcode = ThaaniyamHub_Dashboard::resolve_pickup_pincode( (string) $assigned_pickup, $vid );
                        if ( $pickup_postcode ) {
                            self::log( 'Pickup postcode (assigned pickup "' . $assigned_pickup . '" for vendor ' . $vid . '): ' . $pickup_postcode );
                        }
                    }
                }
            }

            if ( empty( $pickup_postcode ) ) {
                self::log( 'get_formatted_data: Vendor #' . $vid . ' does not have a verified assigned Shiprocket pickup location. Shipping rate calculation skipped.' );
            }

            // Get customer phone number: shipping phone if available, else billing phone
            $phone = '';

            // 1. Try from WooCommerce Customer shipping/billing phone
            if (isset(WC()->customer)) {
                if (is_callable(array(WC()->customer, 'get_shipping_phone'))) {
                    $phone = WC()->customer->get_shipping_phone();
                }
                if (empty($phone)) {
                    $phone = WC()->customer->get_meta('shipping_phone');
                }
                if (empty($phone)) {
                    $phone = WC()->customer->get_meta('_shipping_phone');
                }
                if (empty($phone)) {
                    $phone = WC()->customer->get_billing_phone();
                }
            }

            // 2. Try from POST data (checkout AJAX fallback)
            if (empty($phone)) {
                if (isset($_POST['shipping_phone']) && !empty($_POST['shipping_phone'])) {
                    $phone = wc_clean($_POST['shipping_phone']);
                } elseif (isset($_POST['billing_phone']) && !empty($_POST['billing_phone'])) {
                    $phone = wc_clean($_POST['billing_phone']);
                }
            }

            // 3. Try from package destination details
            if (empty($phone) && isset($package['destination']['phone'])) {
                $phone = $package['destination']['phone'];
            }

            $data_to_send = array(
                'length' => $l,
                'width' => $b,
                'height' => $h,
                'weight' => $applicable_weight,   // Applicable = max(actual, volumetric)
                'declared_value' => $declared_value,
                'store_url' => $store_url,
                'unit' => 'kg',
                'pickup_postcode' => $pickup_postcode,
            );

            if (!empty($phone)) {
                $data_to_send['phone'] = $phone;
                self::log('Customer phone retrieved and sent to Shiprocket API: ' . $phone);
            } else {
                self::log('Customer phone not available during checkout rates calculation.');
            }

            $data_to_send['cod'] = ('yes' === $prepaid_only) ? '0' : '1';
            $data_to_send['currency'] = self::$currency_code;
            $data_to_send['declared_value'] = $declared_value;
            $data_to_send['delivery_postcode'] = $package['destination']['postcode'];
            $data_to_send['reference_id'] = uniqid();
            $data_to_send['merchant_id'] = self::$integration_id;

            WC()->session->set('ph_shiprocket_rates_unique_id', $data_to_send['reference_id']);
            return $data_to_send;
        }

        public function fallbackRateGenerator($qty = 1)
        {
            $shipping_method_detail = new stdClass();
            $shipping_method_detail->display_name = $this->shipping_title;
            $shipping_method_detail->rate = floatval(self::$fallback_rate) * $qty;
            $shipping_method_detail->rule_name = $this->shipping_title;
            $shipping_method_detail->rule_id = null;
            $shipping_method_detail->service_id = null;
            $shipping_method_detail->etd = $this->shipping_title;
            $shipping_method_detail->carrier_id = 'fallback_rate';
            $this->prepare_rate($shipping_method_detail);
        }

        public function flatRateGenerator()
        {
            $shipping_method_detail = new stdClass();
            $shipping_method_detail->rule_name = $this->shipping_title_flat;
            $shipping_method_detail->display_name = $this->shipping_title_flat;
            $shipping_method_detail->rate = self::$flat_rate;
            $shipping_method_detail->rule_id = null;
            $shipping_method_detail->service_id = null;
            $shipping_method_detail->etd = $this->shipping_title_flat;
            $shipping_method_detail->carrier_id = 'flat_rate';
            $this->prepare_rate($shipping_method_detail);
        }

        public function shiprocket_update_shipping_charges()
        {
            ?>
            <style type="text/css">
                /* Override theme's display: flex/block styles to restore standard table behavior for shipping row */
                .checkout-right-info .woocommerce-checkout-review-order-table tfoot tr.shipping,
                .checkout-right-info .woocommerce-checkout-review-order-table tfoot tr.woocommerce-shipping-totals {
                    display: table-row !important;
                    flex-direction: row !important;
                    padding: 0 !important;
                }

                .checkout-right-info .woocommerce-checkout-review-order-table tfoot tr.shipping th,
                .checkout-right-info .woocommerce-checkout-review-order-table tfoot tr.woocommerce-shipping-totals th {
                    display: table-cell !important;
                    width: auto !important;
                    text-align: left !important;
                    vertical-align: middle !important;
                    border-top: 1px solid #ede5cc !important;
                    padding: 16px 20px !important;
                }

                .checkout-right-info .woocommerce-checkout-review-order-table tfoot tr.shipping td,
                .checkout-right-info .woocommerce-checkout-review-order-table tfoot tr.woocommerce-shipping-totals td {
                    display: table-cell !important;
                    width: auto !important;
                    text-align: right !important;
                    vertical-align: middle !important;
                    border-top: 1px solid #ede5cc !important;
                    padding: 16px 20px !important;
                }
            </style>
            <script type="text/javascript">
                (function ($) {
                    // Update checkout when payment method changes
                    $('form.checkout').on('change', 'input[name^="payment_method"]', function () {
                        $('body').trigger('update_checkout');
                    });
                })(jQuery);
            </script>
            <?php
        }
    }
}
}


add_filter('woocommerce_shipping_methods', 'register_custom_shiprocket_shipping_method', 20);
if (!function_exists('register_custom_shiprocket_shipping_method')) {
function register_custom_shiprocket_shipping_method($methods)
{
    $methods['shiprocket_woocommerce_shipping'] = 'Custom_Shiprocket_Shipping_Method';
    return $methods;
}
}



/**
 * Prevent WCFM from splitting the cart into per-vendor packages.
 *
 * WCFM's wcfmmp_split_shipping_packages (hooked at priority 0) splits the cart
 * into one package per vendor. Our Custom_Shiprocket_Shipping_Method already
 * handles vendor splitting internally via split_package_by_vendor(). Allowing
 * WCFM to split first means each calculate_shipping() call sees only 1 vendor,
 * making extra_vendors = 0 and surcharge = ₹0 every time.
 *
 * We remove WCFM's split hook and replace it with our own filter that passes the
 * full cart as a single WooCommerce-standard package, letting our method count
 * all vendors and apply the per-additional-vendor surcharge correctly.
 */
add_action('plugins_loaded', 'thaaniyamhub_disable_wcfm_package_split', 20);
if (!function_exists('thaaniyamhub_disable_wcfm_package_split')) {
function thaaniyamhub_disable_wcfm_package_split()
{
    // Remove WCFM Marketplace's package split hook (registered in WCFMmp_Shipping::__construct).
    global $WCFMmp;
    if (isset($WCFMmp) && is_object($WCFMmp) && isset($WCFMmp->wcfmmp_shipping)) {
        remove_filter('woocommerce_cart_shipping_packages', array($WCFMmp->wcfmmp_shipping, 'wcfmmp_split_shipping_packages'), 0);
        // thaaniyamhub_log('thaaniyamhub_disable_wcfm_package_split: Removed WCFM wcfmmp_split_shipping_packages hook.');
    }
}
}

add_filter('woocommerce_cart_shipping_packages', 'thaaniyamhub_single_consolidated_package', 0);
if (!function_exists('thaaniyamhub_single_consolidated_package')) {
function thaaniyamhub_single_consolidated_package($packages)
{
    // Only act on the frontend cart/checkout and when cart is available.
    if (is_admin() || !isset(WC()->cart) || !WC()->cart || WC()->cart->is_empty()) {
        return $packages;
    }

    // Build a single WooCommerce-standard package from all cart items.
    // This is identical to what WC()->cart->get_shipping_packages() would
    // produce without any package-split plugins active.
    $cart_contents = WC()->cart->get_cart();
    $shipping_items = [];
    $contents_cost  = 0.0;

    foreach ($cart_contents as $cart_item_key => $cart_item) {
        if (isset($cart_item['data']) && $cart_item['data']->needs_shipping()) {
            $shipping_items[$cart_item_key] = $cart_item;
            $contents_cost += isset($cart_item['line_total']) ? (float) $cart_item['line_total'] : 0.0;
        }
    }

    if (empty($shipping_items)) {
        return $packages;
    }

    $consolidated = [
        'contents'        => $shipping_items,
        'contents_cost'   => $contents_cost,
        'applied_coupons' => WC()->cart->get_applied_coupons(),
        'user'            => ['ID' => get_current_user_id()],
        'destination'     => [
            'country'   => WC()->customer->get_shipping_country(),
            'state'     => WC()->customer->get_shipping_state(),
            'postcode'  => WC()->customer->get_shipping_postcode(),
            'city'      => WC()->customer->get_shipping_city(),
            'address'   => WC()->customer->get_shipping_address(),
            'address_2' => WC()->customer->get_shipping_address_2(),
        ],
        'cart_subtotal'   => WC()->cart->get_cart_contents_total(),
    ];

    $vendor_count = 0;
    $seen_vendors = [];
    foreach ($shipping_items as $item) {
        $pid = $item['product_id'] ?? 0;
        $vid = function_exists('wcfm_get_vendor_id_by_post') ? (int) wcfm_get_vendor_id_by_post($pid) : 0;
        if (!$vid) {
            $vid = (int) get_post_field('post_author', $pid);
        }
        if ($vid > 0 && !in_array($vid, $seen_vendors, true)) {
            $seen_vendors[] = $vid;
            $vendor_count++;
        }
    }

    // thaaniyamhub_log(sprintf(
    //     'thaaniyamhub_single_consolidated_package: Built 1 consolidated package (%d items, %d vendors: %s).',
    //     count($shipping_items),
    //     $vendor_count,
    //     implode(', ', $seen_vendors)
    // ));

    return [$consolidated];
}
}

add_filter('woocommerce_shipping_package_name', 'thaaniyamhub_custom_shipping_package_name', 600, 3);
if (!function_exists('thaaniyamhub_custom_shipping_package_name')) {
function thaaniyamhub_custom_shipping_package_name($package_name, $package_key, $package)
{
    // Check if the package contains products from multiple vendors.
    $seen_vendors = [];
    if (isset($package['contents'])) {
        foreach ($package['contents'] as $item) {
            $pid = $item['product_id'] ?? 0;
            $vid = function_exists('wcfm_get_vendor_id_by_post') ? (int) wcfm_get_vendor_id_by_post($pid) : 0;
            if (!$vid) {
                $vid = (int) get_post_field('post_author', $pid);
            }
            if ($vid > 0 && !in_array($vid, $seen_vendors, true)) {
                $seen_vendors[] = $vid;
            }
        }
    }

    // If there are multiple vendors in this consolidated package, rename it to generic "Shipping"
    if (count($seen_vendors) > 1) {
        return __('Shipping', 'thaaniyamhub-multi-vendor-orders');
    }

    return $package_name;
}
}






/**
 * Format shipping method label HTML: wrap description and price in separate elements.
 */
add_filter('woocommerce_cart_totals_shipping_method_label', 'thaaniyamhub_custom_shipping_method_label', 999, 2);
if (!function_exists('thaaniyamhub_custom_shipping_method_label')) {
function thaaniyamhub_custom_shipping_method_label($label, $method)
{
    if (strpos($method->method_id, 'shiprocket_woocommerce_shipping') !== false) {
        // Match the text/desc part and the price span, removing the colon
        if (preg_match('#(.*)(?::\s*)(<span class="woocommerce-Price-amount[^"]*">.*</span>)#i', $label, $matches)) {
            $text_part = trim($matches[1]);
            $etd_part = '';
            if (preg_match('/(\(Delivery by [^\)]+\))/i', $text_part, $etd_matches)) {
                $etd_part = ' ' . $etd_matches[1];
            }
            $text_part = 'shipping cost' . $etd_part;
            $price_part = trim($matches[2]);
            $label = '<span class="shipping-method-desc">' . $text_part . '</span>' . $price_part;
        }
    }
    return $label;
}
}


// Force WooCommerce to clear ALL shipping session cache entries so stale multi-package
// rates (from before WCFM package merge) do not bleed into recalculation.
add_action('woocommerce_checkout_init', 'clear_shipping_cache_on_checkout');
add_action('woocommerce_before_cart', 'clear_shipping_cache_on_checkout');
if (!function_exists('clear_shipping_cache_on_checkout')) {
function clear_shipping_cache_on_checkout()
{
    if (!is_admin() && isset(WC()->session) && WC()->cart) {
        // Wipe every shipping_for_package_* key in the session, not just the ones
        // returned by get_shipping_packages() (which already reflects our merged package).
        // This ensures stale rates from a pre-merge 2-package session are evicted.
        $session_data = WC()->session->get_session_data();
        if (is_array($session_data)) {
            foreach ($session_data as $key => $value) {
                if (strpos($key, 'shipping_for_package_') === 0) {
                    WC()->session->set($key, false);
                }
            }
        } else {
            // Fallback: clear known numeric package keys 0–9
            for ($i = 0; $i < 10; $i++) {
                WC()->session->set('shipping_for_package_' . $i, false);
            }
        }
    }
}
}



// -------------------------------------------------------------------------
// Multi-Vendor Shiprocket Synchronization & Order Splitting Enhancements
// -------------------------------------------------------------------------

/**
 * Retrieve the WCFM vendor shop name by vendor ID.
 */
if (!function_exists('thaaniyamhub_get_vendor_name_by_vendor_id')) {
function thaaniyamhub_get_vendor_name_by_vendor_id($vendor_id)
{
    if ($vendor_id && function_exists('wcfmmp_get_store')) {
        $store_user = wcfmmp_get_store($vendor_id);
        if ($store_user) {
            $store_info = $store_user->get_shop_info();
            if (isset($store_info['store_name']) && !empty($store_info['store_name'])) {
                return $store_info['store_name'];
            }
        }
    }
    return '';
}
}


/**
 * Inject vendor-specific metadata fields to assist with Shiprocket mapping.
 *
 * pickup_location MUST match exactly the pickup location name configured in
 * Shiprocket → Settings → Manage Pickups.
 * Configure the mapping via WP option 'thaaniyamhub_vendor_pickup_map':
 *   array( vendor_id => 'Exact Shiprocket Pickup Name', ... )
 */
if (!function_exists('thaaniyamhub_inject_vendor_meta')) {
function thaaniyamhub_inject_vendor_meta($meta_data, $vendor_id, $vendor_name, $order_id = 0, $items = array())
{
    // Resolve the pickup_location name for this vendor
    $pickup_location = '';

    // Strict Pickup Location: Must be explicitly assigned by Admin
    if ($vendor_id > 0) {
        $pickup_location = trim((string) get_user_meta($vendor_id, '_shiprocket_pickup_id', true));
        if (empty($pickup_location)) {
            $pickup_map = get_option('thaaniyamhub_vendor_pickup_map', array());
            if (!empty($pickup_map[$vendor_id])) {
                $pickup_location = trim($pickup_map[$vendor_id]);
            }
        }
    }

    if (empty($pickup_location) && $order_id > 0) {
        $order_to_check = wc_get_order($order_id);
        if ($order_to_check) {
            $pickup_location = $order_to_check->get_meta('_shiprocket_pickup_override') ?: $order_to_check->get_meta('pickup_location');
            if (empty($pickup_location)) {
                $pickup_location = $order_to_check->get_meta('_pickup_location');
            }
            $pickup_location = trim((string) $pickup_location);
        }
    }

    $meta_data[] = array(
        'key' => 'vendor_id',
        'value' => strval($vendor_id),
    );
    $meta_data[] = array(
        'key' => 'vendor_shop_name',
        'value' => $vendor_name,
    );
    if (!empty($pickup_location)) {
        $meta_data[] = array(
            'key' => 'pickup_location',
            'value' => $pickup_location,
        );
        $meta_data[] = array(
            'key' => '_pickup_location',
            'value' => $pickup_location,
        );
    }

    // Resolve and add pickup postcode strictly from Shiprocket
    $resolved_postcode = '';
    if (!empty($pickup_location) && class_exists('ThaaniyamHub_Dashboard') && method_exists('ThaaniyamHub_Dashboard', 'resolve_pickup_pincode')) {
        $resolved_postcode = ThaaniyamHub_Dashboard::resolve_pickup_pincode($pickup_location, $vendor_id);
    }

    if (!empty($resolved_postcode)) {
        $meta_data[] = array(
            'key' => 'pickup_postcode',
            'value' => $resolved_postcode,
        );
        $meta_data[] = array(
            'key' => '_shiprocket_pickup_postcode',
            'value' => $resolved_postcode,
        );
    }

    // Calculate vendor package weights
    $additional_weight = 0.0;
    if ($order_id > 0) {
        $order = wc_get_order($order_id);
        if ($order) {
            $additional_weight = $order->get_meta('_additional_weight');
            if ($additional_weight === '') {
                $additional_weight = $order->get_meta('additional_weight');
            }
        }
    }
    if ($additional_weight === '') {
        $additional_weight = floatval(thaaniyamhub_get_shipping_setting('additional_weight', 0.0));
    } else {
        $additional_weight = floatval($additional_weight);
    }

    $items_weight = 0.0;
    if (!empty($items)) {
        $default_weight = floatval(thaaniyamhub_get_shipping_setting('default_weight', 0.1));
        $weight_unit = get_option('woocommerce_weight_unit');
        $weight_unit_lower = !empty($weight_unit) ? strtolower($weight_unit) : 'kg';

        foreach ($items as $item) {
            $product_id = $item['product_id'] ?? 0;
            $quantity = $item['quantity'] ?? 1;
            if ($product_id > 0) {
                $product = wc_get_product($product_id);
                if ($product) {
                    $item_weight = floatval($product->get_weight());
                    if ($item_weight <= 0.0) {
                        if (in_array($weight_unit_lower, array('g', 'grams'), true)) {
                            $item_weight = $default_weight * 1000;
                        } elseif ('lbs' === $weight_unit_lower) {
                            $item_weight = $default_weight / 0.45359237;
                        } elseif ('oz' === $weight_unit_lower) {
                            $item_weight = $default_weight / 0.028349523;
                        } else {
                            $item_weight = $default_weight;
                        }
                    }
                    $items_weight += $item_weight * $quantity;
                }
            }
        }
        // Convert units to kg if needed
        if (in_array($weight_unit_lower, array('g', 'grams'), true)) {
            $items_weight /= 1000;
        } elseif ('lbs' === $weight_unit_lower) {
            $items_weight *= 0.45359237;
        } elseif ('oz' === $weight_unit_lower) {
            $items_weight *= 0.028349523;
        }
    }

    $total_weight = $items_weight + $additional_weight;

    $meta_data[] = array(
        'key' => '_additional_weight',
        'value' => strval($additional_weight),
    );
    $meta_data[] = array(
        'key' => '_total_package_weight',
        'value' => strval($total_weight),
    );
    $meta_data[] = array(
        'key' => 'additional_weight',
        'value' => strval($additional_weight),
    );
    $meta_data[] = array(
        'key' => 'total_package_weight',
        'value' => strval($total_weight),
    );

    thaaniyamhub_log(
        sprintf('inject_vendor_meta: vendor_id=%s, vendor_name=%s, pickup_location=%s, items_weight=%s, additional_weight=%s, total=%s', $vendor_id, $vendor_name, $pickup_location, $items_weight, $additional_weight, $total_weight)
    );

    return $meta_data;
}
}



/**
 * Resolve the vendor ID from a REST API line item array.
 * Priority: 1. wcfm_get_vendor_id_by_post  2. _vendor_id meta_data key  3. post_author
 *
 * @param array $item REST line item (array with product_id, meta_data, etc.)
 * @return int Vendor ID, or 0 if not determinable.
 */
if (!function_exists('thaaniyamhub_get_vendor_id_from_rest_item')) {
function thaaniyamhub_get_vendor_id_from_rest_item($item)
{
    $product_id = $item['product_id'] ?? 0;
    if (!$product_id) {
        return 0;
    }

    // Priority 1: WCFM function
    if (function_exists('wcfm_get_vendor_id_by_post')) {
        $vid = (int) wcfm_get_vendor_id_by_post($product_id);
        if ($vid > 0) {
            return $vid;
        }
    }

    // Priority 2: _vendor_id in line item meta_data (set by WCFM at checkout)
    if (!empty($item['meta_data']) && is_array($item['meta_data'])) {
        foreach ($item['meta_data'] as $meta) {
            $key = is_array($meta) ? ($meta['key'] ?? '') : ($meta->key ?? '');
            $val = is_array($meta) ? ($meta['value'] ?? '') : ($meta->value ?? '');
            if ('_vendor_id' === $key && $val > 0) {
                return (int) $val;
            }
        }
    }

    // Priority 3: post_author
    $author = (int) get_post_field('post_author', $product_id);
    return $author > 0 ? $author : 0;
}
}


// -------------------------------------------------------------------------
// Webhook Payload Splitting for Multi-Vendor Orders → Shiprocket
// -------------------------------------------------------------------------

/**
 * Build a split order array payload for a specific vendor from a full order payload.
 *
 * @param array $order_payload Full WC REST order array.
 * @param int   $vendor_id     The vendor ID to filter to.
 * @return array The split order payload for this vendor.
 */
if (!function_exists('thaaniyamhub_build_vendor_order_payload')) {
function thaaniyamhub_build_vendor_order_payload($order_payload, $vendor_id)
{
    $vendor_name = thaaniyamhub_get_vendor_name_by_vendor_id($vendor_id);
    $suffix = '-' . ($vendor_name ? sanitize_title($vendor_name) : $vendor_id);
    $order_id = $order_payload['id'] ?? 0;

    $split = $order_payload;
    $split['id'] = $order_id . '-' . $vendor_id;
    $split['number'] = ($order_payload['number'] ?? $order_id) . $suffix;

    // Keep only this vendor's line items
    $vendor_items = array();
    if (isset($order_payload['line_items']) && is_array($order_payload['line_items'])) {
        foreach ($order_payload['line_items'] as $item) {
            $item_vendor_id = thaaniyamhub_get_vendor_id_from_rest_item($item);
            if ((int) $item_vendor_id === (int) $vendor_id) {
                $vendor_items[] = $item;
            }
        }
    }
    $split['line_items'] = $vendor_items;

    // Recalculate totals
    $subtotal = 0;
    $total = 0;
    foreach ($vendor_items as $item) {
        $subtotal += floatval($item['subtotal'] ?? 0);
        $total += floatval($item['total'] ?? 0);
    }
    $split['subtotal'] = strval($subtotal);
    $split['total'] = strval($total);

    // Inject vendor metadata
    $split['meta_data'] = thaaniyamhub_inject_vendor_meta($split['meta_data'] ?? array(), $vendor_id, $vendor_name, $order_id, $vendor_items);

    // Update _links.self to the virtual split order URL so Shiprocket's callback hits our URL rewriter
    $split_id = $split['id'];
    $split['_links'] = array(
        'self' => array(
            array('href' => rest_url("wc/v3/orders/{$split_id}")),
        ),
        'collection' => array(
            array('href' => rest_url('wc/v3/orders')),
        ),
    );

    return $split;
}
}


/**
 * Deliver an additional split order payload to Shiprocket webhooks.
 * Called via WP-Cron for vendor sub-orders 2..N in a multi-vendor order.
 *
 * @param array $split_payload The split order payload to deliver.
 */
if (!function_exists('thaaniyamhub_deliver_vendor_webhook_payload')) {
function thaaniyamhub_deliver_vendor_webhook_payload($split_payload)
{
    // Find all active Shiprocket webhooks
    global $wpdb;
    $webhooks = $wpdb->get_results(
        "SELECT webhook_id FROM {$wpdb->prefix}wc_webhooks WHERE status = 'active' AND topic IN ('order.created','order.updated')"
    );

    if (empty($webhooks)) {
        thaaniyamhub_log('thaaniyamhub_deliver_vendor_webhook_payload: No active order webhooks found.');
        return;
    }

    $encoded_body = wp_json_encode($split_payload);

    foreach ($webhooks as $wh_row) {
        $webhook = new WC_Webhook($wh_row->webhook_id);
        $delivery_url = $webhook->get_delivery_url();
        if (empty($delivery_url)) {
            continue;
        }

        $delivery_id = $webhook->get_new_delivery_id();
        $http_args = array(
            'method' => 'POST',
            'timeout' => MINUTE_IN_SECONDS,
            'redirection' => 0,
            'httpversion' => '1.0',
            'blocking' => true,
            'user-agent' => sprintf('WooCommerce/%s Hookshot (WordPress/%s)', WC_VERSION, $GLOBALS['wp_version']),
            'body' => $encoded_body,
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-WC-Webhook-Source' => home_url('/'),
                'X-WC-Webhook-Topic' => $webhook->get_topic(),
                'X-WC-Webhook-Resource' => 'order',
                'X-WC-Webhook-Event' => $webhook->get_event(),
                'X-WC-Webhook-Signature' => $webhook->generate_signature($encoded_body),
                'X-WC-Webhook-ID' => $webhook->get_id(),
                'X-WC-Webhook-Delivery-ID' => $delivery_id,
            ),
            'cookies' => array(),
        );

        $response = wp_safe_remote_request($delivery_url, $http_args);
        $code = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_response_code($response);
        thaaniyamhub_log(
            sprintf(
                'Delivered split vendor sub-order %s to webhook #%s (%s). Response: %s',
                $split_payload['id'] ?? '',
                $webhook->get_id(),
                $delivery_url,
                $code
            )
        );
    }
}
}

add_action('thaaniyamhub_deliver_vendor_webhook', 'thaaniyamhub_deliver_vendor_webhook_payload');

/**
 * Intercept WooCommerce webhook payload for order topics.
 * - For single-vendor orders: inject pickup_location metadata so Shiprocket uses the correct pickup.
 * - For multi-vendor orders: return the first vendor's split payload; schedule the rest via WP action.
 *
 * @param array  $payload     The webhook payload.
 * @param string $resource    Resource type (e.g. 'order').
 * @param int    $resource_id The order ID.
 * @param int    $webhook_id  The webhook ID.
 * @return array Modified payload.
 */
add_filter('woocommerce_webhook_payload', 'thaaniyamhub_split_webhook_payload_by_vendor', 10, 4);
if (!function_exists('thaaniyamhub_split_webhook_payload_by_vendor')) {
function thaaniyamhub_split_webhook_payload_by_vendor($payload, $resource, $resource_id, $webhook_id)
{
    if ('order' !== $resource) {
        return $payload;
    }

    if (!is_array($payload) || empty($payload['line_items'])) {
        return $payload;
    }

    // Group line items by vendor
    $vendor_items = array();
    foreach ($payload['line_items'] as $item) {
        $vendor_id = thaaniyamhub_get_vendor_id_from_rest_item($item);
        $vendor_items[$vendor_id][] = $item;
    }

    $vendor_ids = array_keys($vendor_items);

    thaaniyamhub_log(
        sprintf(
            'Webhook payload for order #%s: %d vendor group(s) — vendor IDs: %s',
            $payload['id'] ?? $resource_id,
            count($vendor_ids),
            implode(', ', $vendor_ids)
        )
    );

    // Single-vendor order: inject pickup_location and return
    if (count($vendor_ids) === 1) {
        $vid = $vendor_ids[0];
        if ($vid > 0) {
            $vname = thaaniyamhub_get_vendor_name_by_vendor_id($vid);
            $suffix = '-' . ($vname ? sanitize_title($vname) : $vid);
            $order_id = $payload['id'] ?? $resource_id;
            $payload['id'] = $order_id . '-' . $vid;
            $payload['number'] = ($payload['number'] ?? $order_id) . $suffix;
            $payload['meta_data'] = thaaniyamhub_inject_vendor_meta($payload['meta_data'] ?? array(), $vid, $vname, intval($payload['id']), $payload['line_items']);
            thaaniyamhub_log(
                sprintf('Single-vendor webhook payload updated: order ID → %s', $payload['id'])
            );
        }
        return $payload;
    }

    // Multi-vendor order: return first vendor's payload; schedule the rest
    $first_vendor_id = array_shift($vendor_ids);
    $first_payload = thaaniyamhub_build_vendor_order_payload($payload, $first_vendor_id);

    thaaniyamhub_log(
        sprintf(
            'Multi-vendor webhook: returning first vendor %d payload (sub-order %s). Scheduling %d more.',
            $first_vendor_id,
            $first_payload['id'] ?? '',
            count($vendor_ids)
        )
    );

    // Schedule subsequent vendor payloads
    foreach ($vendor_ids as $vid) {
        $extra_payload = thaaniyamhub_build_vendor_order_payload($payload, $vid);
        // Use WP-Cron (single event) to deliver with a small delay so the first one arrives first
        wp_schedule_single_event(time() + 5, 'thaaniyamhub_deliver_vendor_webhook', array($extra_payload));
        thaaniyamhub_log(
            sprintf('Scheduled delivery for vendor %d sub-order %s', $vid, $extra_payload['id'] ?? '')
        );
    }

    return $first_payload;
}
}




/**
 * Modify checkout fields: require billing phone and make WCFM location optional.
 */
add_filter('woocommerce_checkout_fields', 'thaaniyamhub_modify_checkout_fields', 999);
if (!function_exists('thaaniyamhub_modify_checkout_fields')) {
function thaaniyamhub_modify_checkout_fields($fields)
{
    if (isset($fields['billing']['billing_phone'])) {
        $fields['billing']['billing_phone']['required'] = true;
    }
    if (isset($fields['shipping']['shipping_phone'])) {
        $fields['shipping']['shipping_phone']['required'] = true;
    }
    if (isset($fields['billing']['wcfmmp_user_location'])) {
        unset($fields['billing']['wcfmmp_user_location']);
    }
    if (isset($fields['shipping']['wcfmmp_user_location'])) {
        unset($fields['shipping']['wcfmmp_user_location']);
    }
    return $fields;
}
}


/**
 * Disable WCFM checkout location map and scripts.
 */
add_action('wp_enqueue_scripts', 'thaaniyamhub_remove_checkout_map', 999);
if (!function_exists('thaaniyamhub_remove_checkout_map')) {
function thaaniyamhub_remove_checkout_map()
{
    global $WCFMmp;
    if (isset($WCFMmp->frontend)) {
        remove_action('woocommerce_after_checkout_billing_form', array($WCFMmp->frontend, 'wcfmmp_checkout_user_location_map'), 50);
    }
    wp_dequeue_script('wcfmmp_checkout_location_js');
}
}


/**
 * Remove official Shiprocket plugin's duplicate/unwanted stylesheet/script hooks on checkout.
 */
add_action('wp', 'thaaniyamhub_remove_official_shiprocket_checkout_styles', 999);
if (!function_exists('thaaniyamhub_remove_official_shiprocket_checkout_styles')) {
function thaaniyamhub_remove_official_shiprocket_checkout_styles()
{
    global $wp_filter;
    $hook_name = 'woocommerce_review_order_before_payment';
    if (isset($wp_filter[$hook_name])) {
        $wp_hook = $wp_filter[$hook_name];
        if (isset($wp_hook->callbacks[10])) {
            foreach ($wp_hook->callbacks[10] as $id => $callback) {
                if (is_array($callback['function'])) {
                    $object = $callback['function'][0];
                    $method = $callback['function'][1];
                    // Remove if object is Shiprocket_Woocommerce_Shipping_Method or any subclass (e.g. Custom_Shiprocket_Shipping_Method)
                    if (is_object($object) && $object instanceof Shiprocket_Woocommerce_Shipping_Method && $method === 'shiprocket_update_shipping_charges') {
                        $wp_hook->remove_filter($hook_name, $callback['function'], 10);
                    }
                }
            }
        }
    }
}
}


// Capture and save selected courier from checkout shipping rate
add_action('woocommerce_checkout_create_order_shipping_item', 'thaaniyamhub_save_selected_courier_on_shipping_item', 10, 4);
if (!function_exists('thaaniyamhub_save_selected_courier_on_shipping_item')) {
function thaaniyamhub_save_selected_courier_on_shipping_item($item, $package_key, $package, $order)
{
    $chosen_methods = WC()->session ? WC()->session->get('chosen_shipping_methods') : null;
    $rate_id = isset($chosen_methods[$package_key]) ? $chosen_methods[$package_key] : '';

    if (empty($rate_id) && isset($package['rates'])) {
        $method_id = $item->get_method_id();
        $instance_id = $item->get_instance_id();
        foreach ($package['rates'] as $r_id => $rate) {
            if ($rate->get_method_id() === $method_id && (int) $rate->get_instance_id() === (int) $instance_id) {
                $rate_id = $r_id;
                break;
            }
        }
    }

    if (!empty($rate_id) && strpos($rate_id, 'shiprocket_woocommerce_shipping') !== false) {
        if (preg_match('/(?:prepaid|cod):(\d+)/', $rate_id, $matches)) {
            $courier_id = (int) $matches[1];
            $order->update_meta_data('_shiprocket_selected_courier_id', $courier_id);
            $item->update_meta_data('_courier_company_id', $courier_id);

            $label = $item->get_name();
            $clean_name = preg_replace('/\s*\(Delivery by.*\)/i', '', $label);
            $order->update_meta_data('_shiprocket_selected_courier_name', $clean_name);
            $item->update_meta_data('_courier_name', $clean_name);

            thaaniyamhub_log(sprintf('Checkout: Saved selected Shiprocket courier ID %d (%s) on order #%d', $courier_id, $clean_name, $order->get_id()));
        }
    }
}
}


/**
 * Build a WC REST-compatible order array payload directly from a WC_Order object.
 * Used in admin context where the REST API may not be fully initialised.
 *
 * @param WC_Order $order
 * @return array
 */
if (!function_exists('thaaniyamhub_build_order_payload_from_order')) {
function thaaniyamhub_build_order_payload_from_order($order)
{
    $order_id = $order->get_id();

    // Build line items array
    $line_items = array();
    foreach ($order->get_items() as $item_id => $item) {
        $product = $item->get_product();
        $product_id = $item->get_product_id();

        // Build meta_data for line item (include _vendor_id)
        $meta_data = array();
        foreach ($item->get_meta_data() as $meta) {
            $meta_data[] = array(
                'id' => $meta->id,
                'key' => $meta->key,
                'value' => $meta->value,
            );
        }

        $line_items[] = array(
            'id' => $item_id,
            'name' => $item->get_name(),
            'product_id' => $product_id,
            'variation_id' => $item->get_variation_id(),
            'quantity' => $item->get_quantity(),
            'subtotal' => $item->get_subtotal(),
            'total' => $item->get_total(),
            'sku' => $product ? $product->get_sku() : '',
            'meta_data' => $meta_data,
        );
    }

    // Build meta_data for order
    $order_meta = array();
    foreach ($order->get_meta_data() as $meta) {
        $order_meta[] = array(
            'id' => $meta->id,
            'key' => $meta->key,
            'value' => $meta->value,
        );
    }

    // Build shipping lines
    $shipping_lines = array();
    foreach ($order->get_items('shipping') as $ship_id => $ship_item) {
        $ship_meta = array();
        foreach ($ship_item->get_meta_data() as $meta) {
            $ship_meta[] = array(
                'id' => $meta->id,
                'key' => $meta->key,
                'value' => $meta->value,
            );
        }
        $shipping_lines[] = array(
            'id' => $ship_id,
            'method_title' => $ship_item->get_method_title(),
            'method_id' => $ship_item->get_method_id(),
            'total' => $ship_item->get_total(),
            'meta_data' => $ship_meta,
        );
    }

    $billing = $order->get_address('billing');
    $shipping = $order->get_address('shipping');

    $payload = array(
        'id' => $order_id,
        'parent_id' => $order->get_parent_id(),
        'number' => $order->get_order_number(),
        'status' => $order->get_status(),
        'currency' => $order->get_currency(),
        'date_created' => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d\TH:i:s') : '',
        'date_modified' => $order->get_date_modified() ? $order->get_date_modified()->date('Y-m-d\TH:i:s') : '',
        'date_created_gmt' => $order->get_date_created() ? gmdate('Y-m-d\TH:i:s', $order->get_date_created()->getTimestamp()) : '',
        'date_modified_gmt' => $order->get_date_modified() ? gmdate('Y-m-d\TH:i:s', $order->get_date_modified()->getTimestamp()) : '',
        'discount_total' => $order->get_discount_total(),
        'shipping_total' => $order->get_shipping_total(),
        'total' => $order->get_total(),
        'total_tax' => $order->get_total_tax(),
        'customer_id' => $order->get_customer_id(),
        'order_key' => $order->get_order_key(),
        'billing' => $billing,
        'shipping' => $shipping,
        'payment_method' => $order->get_payment_method(),
        'payment_method_title' => $order->get_payment_method_title(),
        'transaction_id' => $order->get_transaction_id(),
        'customer_note' => $order->get_customer_note(),
        'date_paid' => $order->get_date_paid() ? $order->get_date_paid()->date('Y-m-d\TH:i:s') : null,
        'date_paid_gmt' => $order->get_date_paid() ? gmdate('Y-m-d\TH:i:s', $order->get_date_paid()->getTimestamp()) : null,
        'date_completed' => $order->get_date_completed() ? $order->get_date_completed()->date('Y-m-d\TH:i:s') : null,
        'date_completed_gmt' => $order->get_date_completed() ? gmdate('Y-m-d\TH:i:s', $order->get_date_completed()->getTimestamp()) : null,
        'created_via' => $order->get_created_via(),
        'version' => $order->get_version(),
        'prices_include_tax' => $order->get_prices_include_tax(),
        'customer_ip_address' => $order->get_customer_ip_address(),
        'customer_user_agent' => $order->get_customer_user_agent(),
        'cart_hash' => $order->get_cart_hash(),
        'line_items' => $line_items,
        'shipping_lines' => $shipping_lines,
        'tax_lines' => array(),
        'fee_lines' => array(),
        'coupon_lines' => array(),
        'refunds' => array(),
        'meta_data' => $order_meta,
        'currency_symbol' => html_entity_decode(get_woocommerce_currency_symbol($order->get_currency())),
        'payment_url' => $order->get_checkout_payment_url(),
        '_links' => array(
            'self' => array(
                array('href' => rest_url("wc/v3/orders/{$order_id}")),
            ),
            'collection' => array(
                array('href' => rest_url('wc/v3/orders')),
            ),
        ),
    );

    return $payload;
}
}


/**
 * Filter WCFM status updates triggered by parent order status changes.
 * Prevents WCFM from resetting already cancelled/refunded sub-orders, while allowing
 * active sub-orders to sync with the parent order's new status.
 */
add_filter('wcfm_is_allow_status_update_by_main_order_status', 'thaaniyamhub_custom_wcfm_status_update', 10, 3);
if (!function_exists('thaaniyamhub_custom_wcfm_status_update')) {
function thaaniyamhub_custom_wcfm_status_update($allow, $order_id, $status_to)
{
    global $wpdb;

    // Fetch all distinct vendors and their current order statuses for this order
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT vendor_id, order_status FROM {$wpdb->prefix}wcfm_marketplace_orders WHERE order_id = %d",
        $order_id
    ));

    if (empty($rows)) {
        return $allow;
    }

    $final_statuses = array('cancelled', 'refunded', 'failed');

    foreach ($rows as $row) {
        $vendor_id = $row->vendor_id;
        $current_status = $row->order_status;

        // Skip updating this vendor if they are already in a final status (e.g. cancelled)
        if (in_array($current_status, $final_statuses)) {
            continue;
        }

        // Update the active vendor's marketplace order status to match the parent order
        $wpdb->update(
            "{$wpdb->prefix}wcfm_marketplace_orders",
            array('commission_status' => $status_to, 'order_status' => $status_to),
            array('order_id' => $order_id, 'vendor_id' => $vendor_id)
        );

        // Update the active vendor's ledger entries
        $commission_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->prefix}wcfm_marketplace_orders WHERE order_id = %d AND vendor_id = %d",
            $order_id,
            $vendor_id
        ));

        global $WCFMmp;
        if (!empty($commission_ids) && isset($WCFMmp->wcfmmp_ledger)) {
            foreach ($commission_ids as $commission_id) {
                $WCFMmp->wcfmmp_ledger->wcfmmp_ledger_status_update($commission_id, $status_to);
            }
        }
    }

    // Return false to prevent WCFM's native blind update query from running
    return false;
}
}


// Prevent WCFM from trashing and hiding cancelled orders from the vendor dashboard
add_filter('wcfm_is_allow_trashed_cancelled_orders', '__return_false');

// Restore "Cancelled" and "Failed" status tabs to the vendor order menus on their dashboard
add_filter('wcfmu_orders_menus', 'thaaniyamhub_restore_vendor_order_menus', 999);
if (!function_exists('thaaniyamhub_restore_vendor_order_menus')) {
function thaaniyamhub_restore_vendor_order_menus($order_menus)
{
    if (function_exists('wcfm_is_vendor') && wcfm_is_vendor()) {
        $order_menus['cancelled'] = __('Cancelled', 'wc-frontend-manager');
        $order_menus['failed'] = __('Failed', 'wc-frontend-manager');
    }
    return $order_menus;
}
}


// Restore "Cancelled" and "Failed" statuses to the allowed order statuses list for vendors
add_filter('wcfm_allowed_order_status', 'thaaniyamhub_restore_vendor_allowed_order_statuses', 999, 2);
if (!function_exists('thaaniyamhub_restore_vendor_allowed_order_statuses')) {
function thaaniyamhub_restore_vendor_allowed_order_statuses($order_statuses, $order_id = 0)
{
    if (function_exists('wcfm_is_vendor') && wcfm_is_vendor()) {
        $order_statuses['wc-cancelled'] = _x('Cancelled', 'Order status', 'woocommerce');
        $order_statuses['wc-failed'] = _x('Failed', 'Order status', 'woocommerce');
    }
    return $order_statuses;
}
}


// -------------------------------------------------------------------------
// Admin Order Edit Weight Fields & Checkout Meta Saver
// -------------------------------------------------------------------------

/**
 * Save default additional weight and calculate total weight on checkout.
 */
add_action('woocommerce_checkout_update_order_meta', 'thaaniyamhub_save_order_package_weights', 10, 2);
if (!function_exists('thaaniyamhub_save_order_package_weights')) {
function thaaniyamhub_save_order_package_weights($order_id, $data)
{
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    // Do not save weights on the parent order if it is a multi-vendor order (which will be split)
    $vendor_ids = array();
    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        if ($product_id > 0) {
            $vendor_id = 0;
            if (function_exists('wcfm_get_vendor_id_by_post')) {
                $vendor_id = (int) wcfm_get_vendor_id_by_post($product_id);
            }
            if (!$vendor_id) {
                $vendor_id = (int) get_post_field('post_author', $product_id);
            }
            if ($vendor_id > 0) {
                $vendor_ids[$vendor_id] = true;
            }
        }
    }
    if (count($vendor_ids) > 1) {
        return;
    }

    // Get default additional weight from settings
    $additional_weight = floatval(thaaniyamhub_get_shipping_setting('additional_weight', 0.0));

    // Get default product weight
    $default_weight = floatval(thaaniyamhub_get_shipping_setting('default_weight', 0.1));

    // Calculate total actual weight of items in the order
    $items_weight = 0.0;
    $weight_unit = get_option('woocommerce_weight_unit');
    $weight_unit_lower = !empty($weight_unit) ? strtolower($weight_unit) : 'kg';

    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if ($product) {
            $item_weight = floatval($product->get_weight());
            if ($item_weight <= 0.0) {
                if (in_array($weight_unit_lower, array('g', 'grams'), true)) {
                    $item_weight = $default_weight * 1000;
                } elseif ('lbs' === $weight_unit_lower) {
                    $item_weight = $default_weight / 0.45359237;
                } elseif ('oz' === $weight_unit_lower) {
                    $item_weight = $default_weight / 0.028349523;
                } else {
                    $item_weight = $default_weight;
                }
            }
            $items_weight += $item_weight * $item->get_quantity();
        }
    }

    // Apply WooCommerce weight unit conversion if needed
    if (in_array($weight_unit_lower, array('g', 'grams'), true)) {
        $items_weight /= 1000;
    } elseif ('lbs' === $weight_unit_lower) {
        $items_weight *= 0.45359237;
    } elseif ('oz' === $weight_unit_lower) {
        $items_weight *= 0.028349523;
    }

    $total_weight = $items_weight + $additional_weight;

    $order->update_meta_data('_additional_weight', $additional_weight);
    $order->update_meta_data('_total_package_weight', $total_weight);
    $order->update_meta_data('additional_weight', $additional_weight);
    $order->update_meta_data('total_package_weight', $total_weight);
    $order->save();
}
}


/**
 * Filter the shipping method string to remove duplicate names.
 */
add_filter('woocommerce_order_shipping_method', 'thaaniyamhub_unique_order_shipping_method', 10, 2);
if (!function_exists('thaaniyamhub_unique_order_shipping_method')) {
function thaaniyamhub_unique_order_shipping_method($method_string, $order)
{
    if (empty($method_string)) {
        return $method_string;
    }
    $names = explode(',', $method_string);
    $names = array_map('trim', $names);
    $unique_names = array_unique($names);
    return implode(', ', $unique_names);
}
}




// =========================================================================
// VENDOR NOTIFICATIONS & SHIPPING: Standard WCFM and WooCommerce flow
// =========================================================================



/**
 * Filter shipping method label on thank you page / emails to display only the delivery date and amount.
 */
add_filter('woocommerce_order_shipping_to_display_shipped_via', 'thaaniyamhub_custom_shipped_via', 99, 2);
if (!function_exists('thaaniyamhub_custom_shipped_via')) {
function thaaniyamhub_custom_shipped_via($shipped_via_html, $order)
{
    $method_name = $order->get_shipping_method();
    
    // Find all "Delivery by ..." dates
    $dates = [];
    if (preg_match_all('/Delivery by\s*([^,\)]+)/i', $method_name, $matches)) {
        foreach ($matches[1] as $date_str) {
            $dates[] = trim($date_str);
        }
    }
    
    // Find the latest date
    $latest_date = '';
    $max_timestamp = 0;
    foreach ($dates as $d) {
        $timestamp = strtotime($d);
        if ($timestamp && $timestamp > $max_timestamp) {
            $max_timestamp = $timestamp;
            $latest_date = $d;
        }
    }
    
    if (empty($latest_date) && !empty($dates)) {
        $latest_date = end($dates);
    }
    
    if (!empty($latest_date)) {
        $ts = strtotime($latest_date);
        if ($ts) {
            $formatted_date = date('M j, Y', $ts);
        } else {
            $formatted_date = $latest_date;
        }
        return ' <small class="shipped_via">(' . sprintf(__('Delivery by %s', 'thaaniyamhub-multi-vendor-orders'), $formatted_date) . ')</small>';
    }
    
    // If it's a Shiprocket shipping method, do not show "via Shiprocket..." or "via shipping cost"
    // Just return empty so it displays the price only.
    $has_shiprocket = false;
    foreach ($order->get_shipping_methods() as $shipping_method) {
        if (strpos($shipping_method->get_method_id(), 'shiprocket_woocommerce_shipping') !== false) {
            $has_shiprocket = true;
            break;
        }
    }
    
    if ($has_shiprocket) {
        return '';
    }
    
    return $shipped_via_html;
}
}


/**
 * Fetch the actual payment method from Razorpay or Cashfree (UPI, Card, NetBanking, etc.)
 * and update the order payment method title and linked split orders.
 */
add_action('woocommerce_thankyou', 'thaaniyamhub_fetch_actual_payment_method', 5, 1);
add_action('woocommerce_payment_complete', 'thaaniyamhub_fetch_actual_payment_method', 5, 1);
if (!function_exists('thaaniyamhub_fetch_actual_payment_method')) {
function thaaniyamhub_fetch_actual_payment_method($order_id)
{
    $order = wc_get_order($order_id);
    if (!$order) {
        return '';
    }

    $existing_actual = $order->get_meta('_actual_payment_method_title');
    if (!empty($existing_actual)) {
        return $existing_actual;
    }

    $payment_method_id = $order->get_payment_method();
    $payment_id        = $order->get_transaction_id();
    $formatted_method  = '';

    // 1. Razorpay
    if ($payment_method_id === 'razorpay' || (!empty($payment_id) && strpos($payment_id, 'pay_') === 0)) {
        try {
            $razorpay_settings = get_option('woocommerce_razorpay_settings', []);
            $key_id = $razorpay_settings['key_id'] ?? '';
            $key_secret = $razorpay_settings['key_secret'] ?? '';

            if (!empty($key_id) && !empty($key_secret) && !empty($payment_id) && class_exists('\Razorpay\Api\Api')) {
                $api = new \Razorpay\Api\Api($key_id, $key_secret);
                $payment = $api->payment->fetch($payment_id);
                
                $method = $payment->method ?? '';

                switch ($method) {
                    case 'card':
                        $card_network = $payment->card->network ?? '';
                        $card_last4 = $payment->card->last4 ?? '';
                        $formatted_method = $card_last4 ? trim("Card ($card_network ending in $card_last4)") : trim("Card ($card_network)");
                        break;
                    case 'upi':
                        $vpa = $payment->vpa ?? '';
                        $formatted_method = !empty($vpa) ? "UPI ($vpa)" : "UPI";
                        break;
                    case 'netbanking':
                        $bank = $payment->bank ?? '';
                        $formatted_method = !empty($bank) ? "Net Banking ($bank)" : "Net Banking";
                        break;
                    case 'wallet':
                        $wallet = $payment->wallet ?? '';
                        $formatted_method = !empty($wallet) ? "Wallet ($wallet)" : "Wallet";
                        break;
                    case 'paylater':
                        $paylater = $payment->provider ?? '';
                        $formatted_method = !empty($paylater) ? "PayLater ($paylater)" : "PayLater";
                        break;
                    default:
                        $formatted_method = !empty($method) ? ucfirst($method) : 'Razorpay';
                        break;
                }
            }
        } catch (\Exception $e) {
            thaaniyamhub_log('Razorpay payment method fetch error: ' . $e->getMessage());
        }
    }

    // 2. Cashfree
    if ($payment_method_id === 'cashfree' || empty($formatted_method)) {
        try {
            $cf_settings = get_option('woocommerce_cashfree_settings', []);
            $app_id     = $cf_settings['app_id'] ?? '';
            $secret_key = $cf_settings['secret_key'] ?? '';

            if (!empty($app_id) && !empty($secret_key)) {
                $is_sandbox  = ($cf_settings['sandbox'] ?? 'yes') === 'yes';
                $base_url    = $is_sandbox ? 'https://sandbox.cashfree.com/pg' : 'https://api.cashfree.com/pg';
                $prefix      = (($cf_settings['order_id_prefix_text'] ?? 'yes') === 'yes') ? substr(md5(home_url()), 0, 4) . '_' : '';
                $cf_order_id = $prefix . $order_id;

                $resp = wp_remote_get($base_url . '/orders/' . $cf_order_id . '/payments', [
                    'headers' => [
                        'x-api-version'   => '2022-09-01',
                        'x-client-id'     => $app_id,
                        'x-client-secret' => $secret_key,
                    ],
                    'timeout' => 15,
                ]);

                if (wp_remote_retrieve_response_code($resp) === 200) {
                    $body = json_decode(wp_remote_retrieve_body($resp));
                    $attempts = is_array($body) ? $body : [$body];
                    $success_attempt = null;
                    foreach ($attempts as $att) {
                        if (isset($att->payment_status) && $att->payment_status === 'SUCCESS') {
                            $success_attempt = $att;
                            break;
                        }
                    }
                    if (!$success_attempt && !empty($attempts)) {
                        $success_attempt = $attempts[0];
                    }

                    if ($success_attempt) {
                        $pm    = $success_attempt->payment_method ?? null;
                        $group = strtolower($success_attempt->payment_group ?? '');

                        if (isset($pm->upi)) {
                            $upi_id  = $pm->upi->upi_id ?? '';
                            $channel = $pm->upi->channel ?? '';
                            $formatted_method = !empty($upi_id) ? "UPI ($upi_id)" : (!empty($channel) ? "UPI (" . ucfirst($channel) . ")" : "UPI");
                        } elseif (isset($pm->card)) {
                            $network = $pm->card->card_network ?? 'Card';
                            $num     = $pm->card->card_number ?? '';
                            $last4   = strlen($num) >= 4 ? substr($num, -4) : '';
                            $formatted_method = $last4 ? "Card ($network ending in $last4)" : "Card ($network)";
                        } elseif (isset($pm->netbanking)) {
                            $bank = $pm->netbanking->netbanking_bank_name ?? '';
                            $formatted_method = $bank ? "Net Banking ($bank)" : "Net Banking";
                        } elseif (isset($pm->app)) {
                            $provider = $pm->app->provider ?? '';
                            $formatted_method = $provider ? "Wallet (" . ucfirst($provider) . ")" : "Wallet";
                        } elseif ($group === 'upi') {
                            $formatted_method = 'UPI';
                        } elseif (strpos($group, 'card') !== false) {
                            $formatted_method = 'Card';
                        } elseif ($group === 'net_banking' || $group === 'netbanking') {
                            $formatted_method = 'Net Banking';
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            thaaniyamhub_log('Cashfree payment method fetch error: ' . $e->getMessage());
        }
    }

    if (!empty($formatted_method)) {
        $order->update_meta_data('_actual_payment_method_title', $formatted_method);
        $order->set_payment_method_title($formatted_method);
        $order->save();

        // Also sync to linked secondary orders
        $sec_ids = $order->get_meta('_thaaniyamhub_secondary_order_ids');
        if (!empty($sec_ids) && is_array($sec_ids)) {
            foreach ($sec_ids as $sid) {
                $sec_o = wc_get_order((int) $sid);
                if ($sec_o) {
                    $sec_o->update_meta_data('_actual_payment_method_title', $formatted_method);
                    $sec_o->set_payment_method_title($formatted_method);
                    $sec_o->save();
                }
            }
        }
    }

    return $formatted_method ?: ($order->get_payment_method_title() ?: 'Online Payment');
}
}


/**
 * Intercept WCFM order status updates to prevent updating the parent order
 * directly, which would sync and overwrite other vendors' sub-order statuses.
 * Instead, update the specific vendor's sub-order status and then consolidate the parent.
 */
// =========================================================================
// WCFM VENDOR DASHBOARD ORDER ACTIONS: Native WCFM flow operates directly
// on standard vendor orders without interceptors.
// =========================================================================



/**
 * Ensure Shiprocket shipping method names display as 'Standard Shipping (Zone)' or
 * 'Standard Shipping (Shiprocket)' in order history, details pages, and emails
 * for customers, vendors, and admins to differentiate the source of the shipping fee.
 */
add_filter( 'woocommerce_order_item_get_name', 'thaaniyamhub_filter_shipping_item_name', 10, 2 );
if (!function_exists('thaaniyamhub_filter_shipping_item_name')) {
function thaaniyamhub_filter_shipping_item_name( $name, $item ) {
    if ( is_a( $item, 'WC_Order_Item_Shipping' ) ) {
        $method_id = $item->get_method_id();
        if ( strpos( $method_id, 'shiprocket_woocommerce_shipping_standard_shipping' ) !== false ) {
            return __( 'Standard Shipping (Zone)', 'thaaniyamhub-multi-vendor-orders' );
        } elseif ( strpos( $method_id, 'shiprocket_woocommerce_shipping' ) !== false ) {
            return __( 'Standard Shipping (Shiprocket)', 'thaaniyamhub-multi-vendor-orders' );
        }
    }
    return $name;
}
}


/**
 * Clear WooCommerce shipping transients and force recalculation when a vendor's
 * default pickup location meta is updated.
 */
add_action( 'updated_user_meta', 'thaaniyamhub_clear_shipping_cache_on_pickup_update', 10, 4 );
add_action( 'added_user_meta', 'thaaniyamhub_clear_shipping_cache_on_pickup_update', 10, 4 );
add_action( 'update_option_thaaniyamhub_vendor_pickup_map', 'thaaniyamhub_clear_shipping_cache_on_option_update', 10, 2 );

if (!function_exists('thaaniyamhub_clear_shipping_cache_on_pickup_update')) {
function thaaniyamhub_clear_shipping_cache_on_pickup_update( $meta_id, $object_id, $meta_key, $_meta_value ) {
    if ( '_shiprocket_pickup_id' === $meta_key ) {
        thaaniyamhub_clear_shipping_transients();
    }
}
}

if (!function_exists('thaaniyamhub_clear_shipping_cache_on_option_update')) {
function thaaniyamhub_clear_shipping_cache_on_option_update( $old_value, $value ) {
    thaaniyamhub_clear_shipping_transients();
}
}

if (!function_exists('thaaniyamhub_clear_shipping_transients')) {
function thaaniyamhub_clear_shipping_transients() {
    if ( class_exists( 'WC_Cache_Helper' ) ) {
        WC_Cache_Helper::get_transient_version( 'shipping', true );
    }
    if ( function_exists( 'WC' ) && WC()->cart ) {
        WC()->cart->calculate_shipping();
    }
}
}

// =========================================================================
// CUSTOMER MY-ACCOUNT VIEW ORDER REFUND DATA DISPLAY & ORDER LIST REFUND POPUP FIX
// =========================================================================

/**
 * Helper to get all refund objects and amounts across an order.
 */
if ( ! function_exists( 'thaaniyamhub_get_order_all_refunds' ) ) {
function thaaniyamhub_get_order_all_refunds( $order ) {
    if ( ! is_a( $order, 'WC_Order' ) ) {
        $order = wc_get_order( $order );
    }
    if ( ! $order ) {
        return [
            'total_refunded' => 0.0,
            'refund_list'    => [],
        ];
    }

    $order_id = $order->get_id();
    $total_refunded = 0.0;
    $refund_list = [];

    // Direct refunds on this order
    $direct_refunds = $order->get_refunds();
    if ( ! empty( $direct_refunds ) ) {
        foreach ( $direct_refunds as $ref ) {
            $amt = abs( (float) $ref->get_amount() );
            $total_refunded += $amt;
            $refund_list[] = [
                'id'          => $ref->get_id(),
                'order_id'    => $order_id,
                'vendor_name' => '',
                'amount'      => $amt,
                'reason'      => $ref->get_reason() ?: __( 'Customer request', 'thaaniyamhub-multi-vendor-orders' ),
                'date'        => $ref->get_date_created() ? $ref->get_date_created()->date_i18n( 'd M Y, H:i' ) : '',
            ];
        }
    }

    return [
        'total_refunded' => round( $total_refunded, 2 ),
        'refund_list'    => $refund_list,
    ];
}
}

/**
 * Filter WooCommerce order item totals on Customer View-Order page to add Refunded & Net Total.
 */
add_filter( 'woocommerce_get_order_item_totals', 'thaaniyamhub_add_refunds_to_order_totals', 20, 3 );
if ( ! function_exists( 'thaaniyamhub_add_refunds_to_order_totals' ) ) {
function thaaniyamhub_add_refunds_to_order_totals( $total_rows, $order, $tax_display = '' ) {
    if ( ! is_a( $order, 'WC_Order' ) ) {
        return $total_rows;
    }

    $refund_data = thaaniyamhub_get_order_all_refunds( $order );
    $total_refunded = $refund_data['total_refunded'];

    if ( $total_refunded > 0 ) {
        // Remove native refund lines if sub-order aggregation is needed
        foreach ( array_keys( $total_rows ) as $k ) {
            if ( strpos( $k, 'refund_' ) === 0 || 'refunded' === $k ) {
                unset( $total_rows[ $k ] );
            }
        }

        // Find insertion point right after order_total
        $new_rows = [];
        $order_total_val = (float) $order->get_total();
        $net_total = max( 0.0, round( $order_total_val - $total_refunded, 2 ) );

        foreach ( $total_rows as $key => $row ) {
            $new_rows[ $key ] = $row;
            if ( 'order_total' === $key ) {
                $new_rows['thaaniyamhub_refund_total'] = [
                    'label' => __( 'Refunded:', 'thaaniyamhub-multi-vendor-orders' ),
                    'value' => '<span style="color:#dc2626;font-weight:700;">-' . wc_price( $total_refunded, [ 'currency' => $order->get_currency() ] ) . '</span>',
                ];
                $new_rows['thaaniyamhub_net_total'] = [
                    'label' => __( 'Net Amount Paid:', 'thaaniyamhub-multi-vendor-orders' ),
                    'value' => '<strong style="color:#059669;font-size:1.1em;">' . wc_price( $net_total, [ 'currency' => $order->get_currency() ] ) . '</strong>',
                ];
            }
        }
        $total_rows = $new_rows;
    }

    return $total_rows;
}
}

/**
 * Add inline refund badge next to refunded line items on Customer View-Order.
 */
add_filter( 'woocommerce_order_item_name', 'thaaniyamhub_append_refund_badge_to_order_item', 20, 2 );
if ( ! function_exists( 'thaaniyamhub_append_refund_badge_to_order_item' ) ) {
function thaaniyamhub_append_refund_badge_to_order_item( $item_name, $item ) {
    if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
        return $item_name;
    }

    $order = $item->get_order();
    if ( ! $order ) {
        return $item_name;
    }

    // Check if item has refunded qty
    $qty_refunded = abs( $order->get_qty_refunded_for_item( $item->get_id() ) );
    $total_refunded = abs( (float) $order->get_total_refunded_for_item( $item->get_id() ) );

    if ( $total_refunded > 0 ) {
        $badge = sprintf(
            '<div class="thaaniyamhub-customer-refund-badge" style="display:inline-block;margin-top:4px;padding:3px 8px;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:4px;font-size:11px;font-weight:600;">' .
                '↩️ %s: -%s' .
            '</div>',
            esc_html__( 'Refund Processed', 'thaaniyamhub-multi-vendor-orders' ),
            wp_strip_all_tags( wc_price( $total_refunded, [ 'currency' => $order->get_currency() ] ) )
        );
        $item_name .= '<br>' . $badge;
    }

    return $item_name;
}
}

/**
 * Render a dedicated Refund Details card after the order table on Customer View Order.
 */
add_action( 'woocommerce_order_details_after_order_table', 'thaaniyamhub_render_customer_order_refund_card', 15, 1 );
if ( ! function_exists( 'thaaniyamhub_render_customer_order_refund_card' ) ) {
function thaaniyamhub_render_customer_order_refund_card( $order ) {
    if ( ! is_a( $order, 'WC_Order' ) ) {
        return;
    }

    $refund_data = thaaniyamhub_get_order_all_refunds( $order );
    if ( empty( $refund_data['refund_list'] ) ) {
        return;
    }

    $refunds = $refund_data['refund_list'];
    ?>
    <section class="woocommerce-order-refund-details" style="margin: 28px 0; padding: 20px 24px; background: #fef2f2; border: 1px solid #fecaca; border-left: 4px solid #dc2626; border-radius: 8px;">
        <h2 class="woocommerce-order-details__title" style="margin: 0 0 12px 0; font-size: 16px; color: #991b1b; display: flex; align-items: center; gap: 8px;">
            <span>↩️</span> <?php esc_html_e( 'Refund Summary', 'thaaniyamhub-multi-vendor-orders' ); ?>
            <span style="font-size: 13px; font-weight: normal; color: #7f1d1d;">
                (Total Refunded: <strong><?php echo wp_strip_all_tags( wc_price( $refund_data['total_refunded'], [ 'currency' => $order->get_currency() ] ) ); ?></strong>)
            </span>
        </h2>
        <div style="overflow-x: auto;">
            <table class="woocommerce-table woocommerce-table--order-details shop_table order_details" style="width: 100%; border-collapse: collapse; font-size: 13px; background: #ffffff; border-radius: 6px; overflow: hidden; border: 1px solid #fecaca;">
                <thead>
                    <tr style="background: #fee2e2; color: #7f1d1d;">
                        <th style="padding: 10px 14px; text-align: left;"><?php esc_html_e( 'Refund #', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                        <th style="padding: 10px 14px; text-align: left;"><?php esc_html_e( 'Date', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                        <th style="padding: 10px 14px; text-align: left;"><?php esc_html_e( 'Store / Vendor', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                        <th style="padding: 10px 14px; text-align: left;"><?php esc_html_e( 'Reason', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                        <th style="padding: 10px 14px; text-align: right;"><?php esc_html_e( 'Amount', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                        <th style="padding: 10px 14px; text-align: center;"><?php esc_html_e( 'Status', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $refunds as $ref ) : ?>
                        <tr style="border-top: 1px solid #fee2e2;">
                            <td style="padding: 10px 14px; font-weight: 600;">#<?php echo esc_html( $ref['id'] ); ?></td>
                            <td style="padding: 10px 14px; color: #64748b;"><?php echo esc_html( $ref['date'] ); ?></td>
                            <td style="padding: 10px 14px; font-weight: 500;"><?php echo esc_html( $ref['vendor_name'] ?: __( 'Main Order', 'thaaniyamhub-multi-vendor-orders' ) ); ?></td>
                            <td style="padding: 10px 14px; color: #475569;"><?php echo esc_html( $ref['reason'] ); ?></td>
                            <td style="padding: 10px 14px; text-align: right; font-weight: 700; color: #dc2626;">
                                -<?php echo wp_strip_all_tags( wc_price( $ref['amount'], [ 'currency' => $order->get_currency() ] ) ); ?>
                            </td>
                            <td style="padding: 10px 14px; text-align: center;">
                                <span style="background: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700;">
                                    <?php esc_html_e( 'Completed', 'thaaniyamhub-multi-vendor-orders' ); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php
}
}

/**
 * Enqueue Colorbox and initialize the Refund Request Popup scripts on WooCommerce My Account pages.
 * Dequeues WCFM's native popup script to prevent duplicate submit event bindings.
 */
add_action( 'wp_enqueue_scripts', 'thaaniyamhub_enqueue_myaccount_refund_scripts', 99 );
if ( ! function_exists( 'thaaniyamhub_enqueue_myaccount_refund_scripts' ) ) {
function thaaniyamhub_enqueue_myaccount_refund_scripts() {
    if ( function_exists( 'is_account_page' ) && is_account_page() && is_user_logged_in() ) {
        global $WCFM, $WCFMmp;

        // 1. Dequeue WCFM's native popup script on my-account page to prevent duplicate event triggers
        wp_dequeue_script( 'wcfmmp_refund_requests_popup_js' );
        wp_deregister_script( 'wcfmmp_refund_requests_popup_js' );

        // 2. Ensure Colorbox Library is loaded
        if ( isset( $WCFM ) && isset( $WCFM->library ) && method_exists( $WCFM->library, 'load_colorbox_lib' ) ) {
            $WCFM->library->load_colorbox_lib();
        } else {
            wp_enqueue_style( 'thaaniyamhub-colorbox-css', 'https://cdnjs.cloudflare.com/ajax/libs/jquery.colorbox/1.6.4/example1/colorbox.min.css', [], '1.6.4' );
            wp_enqueue_script( 'thaaniyamhub-colorbox-js', 'https://cdnjs.cloudflare.com/ajax/libs/jquery.colorbox/1.6.4/jquery.colorbox-min.js', [ 'jquery' ], '1.6.4', true );
        }

        // 3. Ensure BlockUI is loaded
        if ( isset( $WCFM ) && isset( $WCFM->library ) && method_exists( $WCFM->library, 'load_blockui_lib' ) ) {
            $WCFM->library->load_blockui_lib();
        }

        // 4. Localize wcfm_params and globals required by WCFM refund script
        $wcfm_params = [
            'ajax_url'        => admin_url( 'admin-ajax.php' ),
            'wcfm_ajax_nonce' => wp_create_nonce( 'wcfm_ajax_nonce' ),
        ];
        wp_localize_script( 'jquery', 'wcfm_params', $wcfm_params );

        // 5. Inject single hardened handler with strict double-submit prevention
        $custom_inline_js = "
            var \$large_popup_width = '75%';
            var \$popup_width = '50%';
            if (typeof wcfm_notification_sound === 'undefined') {
                window.wcfm_notification_sound = { play: function(){} };
            }

            jQuery(document).ready(function($) {
                var isOpening = false;
                var isSubmitting = false;

                $(document).off('click', '.wcfm-refund-action').on('click', '.wcfm-refund-action', function(e) {
                    e.preventDefault();
                    e.stopImmediatePropagation();

                    if (isOpening) {
                        return false;
                    }

                    var \$btn = $(this);
                    var rawHref = \$btn.attr('href') || '';
                    var orderId = rawHref.replace(/[^0-9]/g, '').trim();

                    if (!orderId) {
                        return false;
                    }

                    isOpening = true;
                    var \$origText = \$btn.html();
                    \$btn.html('⏳ Loading...').prop('disabled', true);

                    $.ajax({
                        type: 'POST',
                        url: (typeof wcfm_params !== 'undefined' ? wcfm_params.ajax_url : '" . esc_url( admin_url( 'admin-ajax.php' ) ) . "'),
                        data: {
                            action: 'wcfmmp_refund_requests_form_html',
                            order_id: orderId,
                            customer_refund: 'yes',
                            wcfm_ajax_nonce: (typeof wcfm_params !== 'undefined' ? wcfm_params.wcfm_ajax_nonce : '')
                        },
                        success: function(response) {
                            isOpening = false;
                            \$btn.html(\$origText).prop('disabled', false);
                            if ($.colorbox) {
                                $.colorbox({
                                    html: response,
                                    width: \$large_popup_width,
                                    height: '70%',
                                    onComplete: function() {
                                        isSubmitting = false;

                                        $('#wcfm_refund_request').off('change').on('change', function() {
                                            if ($(this).val() === 'full') {
                                                $('.wcfm_refund_input_ele, .wcfm_refund_items_ele').addClass('wcfm_custom_hide');
                                            } else {
                                                $('.wcfm_refund_input_ele, .wcfm_refund_items_ele').removeClass('wcfm_custom_hide');
                                            }
                                        }).trigger('change');

                                        $('#wcfm_refund_requests_submit_button').off('click').on('click', function(ev) {
                                            ev.preventDefault();
                                            ev.stopImmediatePropagation();

                                            if (isSubmitting) {
                                                return false;
                                            }

                                            var reason = $.trim($('#wcfm_refund_reason').val());
                                            if (!reason) {
                                                alert('Please provide a reason for the refund request.');
                                                return false;
                                            }

                                            isSubmitting = true;
                                            var \$submitBtn = $(this);
                                            \$submitBtn.prop('disabled', true).attr('disabled', 'disabled').css('opacity', '0.6').text('Submitting...');

                                            $('#wcfm_refund_form_wrapper').block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6 } });

                                            $.ajax({
                                                type: 'POST',
                                                url: wcfm_params.ajax_url,
                                                data: {
                                                    action: 'wcfm_ajax_controller',
                                                    controller: 'wcfm-refund-requests-form',
                                                    wcfm_refund_requests_form: $('#wcfm_refund_requests_form').serialize(),
                                                    wcfm_ajax_nonce: wcfm_params.wcfm_ajax_nonce
                                                },
                                                success: function(res) {
                                                    try {
                                                        var data = typeof res === 'object' ? res : JSON.parse(res);
                                                        if (data.status) {
                                                            $('#wcfm_refund_requests_form .wcfm-message').html('<span class=\"wcicon-status-completed\"></span> ' + data.message).addClass('wcfm-success').slideDown();
                                                            \$submitBtn.hide();
                                                            setTimeout(function() {
                                                                $.colorbox.close();
                                                                window.location.reload();
                                                            }, 1500);
                                                        } else {
                                                            isSubmitting = false;
                                                            \$submitBtn.prop('disabled', false).removeAttr('disabled').css('opacity', '1').text('Submit Request');
                                                            $('#wcfm_refund_requests_form .wcfm-message').html('<span class=\"wcicon-status-cancelled\"></span> ' + data.message).addClass('wcfm-error').slideDown();
                                                        }
                                                    } catch(err) {
                                                        setTimeout(function() {
                                                            $.colorbox.close();
                                                            window.location.reload();
                                                        }, 1500);
                                                    }
                                                    $('#wcfm_refund_form_wrapper').unblock();
                                                },
                                                error: function() {
                                                    isSubmitting = false;
                                                    \$submitBtn.prop('disabled', false).removeAttr('disabled').css('opacity', '1').text('Submit Request');
                                                    $('#wcfm_refund_form_wrapper').unblock();
                                                    alert('Error submitting refund request. Please try again.');
                                                }
                                            });

                                            return false;
                                        });
                                    }
                                });
                            } else {
                                isOpening = false;
                                alert('Colorbox modal could not be initialized. Please refresh the page.');
                            }
                        },
                        error: function() {
                            isOpening = false;
                            \$btn.html(\$origText).prop('disabled', false);
                            alert('Failed to load refund request form. Please try again.');
                        }
                    });
                });
            });
        ";
        wp_add_inline_script( 'jquery', $custom_inline_js );
    }
}
}

/**
 * Backend Concurrency & Idempotency Guard: Prevent duplicate refund requests from being created concurrently.
 */
add_action( 'wp_ajax_wcfm_ajax_controller', 'thaaniyamhub_prevent_duplicate_refund_requests', 1 );
add_action( 'wp_ajax_nopriv_wcfm_ajax_controller', 'thaaniyamhub_prevent_duplicate_refund_requests', 1 );
if ( ! function_exists( 'thaaniyamhub_prevent_duplicate_refund_requests' ) ) {
function thaaniyamhub_prevent_duplicate_refund_requests() {
    if ( isset( $_POST['controller'] ) && 'wcfm-refund-requests-form' === $_POST['controller'] ) {
        if ( ! empty( $_POST['wcfm_refund_requests_form'] ) ) {
            $form_data = [];
            parse_str( $_POST['wcfm_refund_requests_form'], $form_data );
            $order_id = isset( $form_data['wcfm_refund_order_id'] ) ? absint( $form_data['wcfm_refund_order_id'] ) : 0;
            if ( $order_id > 0 ) {
                global $wpdb;
                $table_refunds = $wpdb->prefix . 'wcfm_marketplace_refund_request';
                if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_refunds}'" ) === $table_refunds ) {
                    // Check if a refund request was already created for this order within the last 10 seconds
                    $recent_duplicate = $wpdb->get_var( $wpdb->prepare(
                        "SELECT ID FROM `{$table_refunds}` WHERE `order_id` = %d AND `created` >= DATE_SUB(NOW(), INTERVAL 10 SECOND) LIMIT 1",
                        $order_id
                    ) );

                    if ( $recent_duplicate ) {
                        wp_send_json( [
                            'status'  => true,
                            'message' => __( 'Refund request has been submitted successfully.', 'thaaniyamhub-multi-vendor-orders' ),
                        ] );
                        exit;
                    }
                }
            }
        }
    }
}
}




