<?php
/**
 * Thaaniyam Hub Marketplace — Consolidated Settings Page
 *
 * Implements a dedicated settings tab under WooCommerce -> Settings -> Thaaniyam Hub.
 * Defines subsections for Cart/Order Rules, Shiprocket API Credentials, and Shipping Rates & Zones.
 *
 * @package thaaniyamhub-multi-vendor-orders
 */

defined('ABSPATH') || exit;

if ( ! class_exists( 'WC_Settings_Page' ) ) {
    if ( defined( 'WC_ABSPATH' ) && file_exists( WC_ABSPATH . 'includes/admin/settings/class-wc-settings-page.php' ) ) {
        require_once WC_ABSPATH . 'includes/admin/settings/class-wc-settings-page.php';
    } elseif ( defined( 'WP_PLUGIN_DIR' ) && file_exists( WP_PLUGIN_DIR . '/woocommerce/includes/admin/settings/class-wc-settings-page.php' ) ) {
        require_once WP_PLUGIN_DIR . '/woocommerce/includes/admin/settings/class-wc-settings-page.php';
    }
}

if ( class_exists( 'WC_Settings_Page' ) ) {

class ThaaniyamHub_Settings extends WC_Settings_Page {

    /**
     * Boot static AJAX and admin hooks.
     */
    public static function init() {
        add_action( 'wp_ajax_thaaniyamhub_get_cf_balance', [ __CLASS__, 'ajax_get_cashfree_balance' ] );
    }

    /**
     * Constructor. Registers page ID and label.
     */
    public function __construct() {
        $this->id    = 'thaaniyamhub_settings';
        $this->label = __( 'Thaaniyam Hub', 'thaaniyamhub-multi-vendor-orders' );

        add_action( 'woocommerce_admin_field_thaaniyamhub_log_viewer', [ $this, 'render_log_viewer' ] );
        add_action( 'woocommerce_admin_field_thaaniyamhub_hidden', [ $this, 'render_hidden_field' ] );
        add_action( 'woocommerce_admin_field_thaaniyamhub_cashfree_balance_widget', [ $this, 'render_cashfree_balance_widget' ] );
        add_action( 'woocommerce_admin_field_thaaniyamhub_cashfree_sync_widget', [ $this, 'render_cashfree_sync_widget' ] );
        add_action( 'woocommerce_admin_field_thaaniyamhub_auto_payout_widget', [ $this, 'render_auto_payout_widget' ] );

        parent::__construct();
    }

    /**
     * Define the sections on the settings tab.
     *
     * @return array
     */
    public function get_sections() {
        return [
            ''          => __( 'General Rules', 'thaaniyamhub-multi-vendor-orders' ),
            'financial' => __( 'Financial & Ledger Rules', 'thaaniyamhub-multi-vendor-orders' ),
            'api'       => __( 'Shiprocket Settings', 'thaaniyamhub-multi-vendor-orders' ),
            'rates'     => __( 'Shipping Rates & Zones', 'thaaniyamhub-multi-vendor-orders' ),
            'payouts'   => __( 'Cashfree Payouts', 'thaaniyamhub-multi-vendor-orders' ),
            'logs'      => __( 'Logs', 'thaaniyamhub-multi-vendor-orders' ),
        ];
    }

    /**
     * Retrieve the settings fields based on the selected section.
     *
     * @param string $current_section Current sub-section slug.
     * @return array settings fields configuration.
     */
    public function get_settings( $current_section = '' ) {
        $settings = [];

        if ( '' === $current_section ) {
            $settings = [
                [
                    'title' => __( 'Cart & Order Constraints', 'thaaniyamhub-multi-vendor-orders' ),
                    'type'  => 'title',
                    'desc'  => __( 'Configure order minimum values and general marketplace subtotal rules.', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'    => 'thaaniyamhub_general_section',
                ],
                [
                    'title'    => __( 'Minimum Cart Value (₹)', 'thaaniyamhub-multi-vendor-orders' ),
                    'desc'     => __( 'Customers cannot proceed to checkout if their total cart subtotal is below this amount.', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'       => 'thaaniyamhub_minimum_cart_value',
                    'type'     => 'number',
                    'default'  => '0',
                    'desc_tip' => true,
                    'custom_attributes' => [
                        'min'  => '0',
                        'step' => '1',
                    ],
                ],
                [
                    'type' => 'sectionend',
                    'id'   => 'thaaniyamhub_general_section',
                ],
            ];
        } elseif ( 'financial' === $current_section ) {
            $settings = [
                [
                    'title' => __( 'Financial Accounting & Service Cost Rules', 'thaaniyamhub-multi-vendor-orders' ),
                    'type'  => 'title',
                    'desc'  => __( 'Configure tax percentages, payment gateway fees, and service charges used to compute real-time order-level incoming, outgoing, and net marketplace profit.', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'    => 'thaaniyamhub_financial_section',
                ],
                [
                    'title'             => __( 'Commission Tax / GST Rate (%)', 'thaaniyamhub-multi-vendor-orders' ),
                    'desc'              => __( 'GST rate levied on platform commission services (Default: 18%).', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'                => 'thaaniyamhub_commission_tax_rate',
                    'type'              => 'number',
                    'default'           => '18.00',
                    'custom_attributes' => [ 'min' => '0', 'max' => '100', 'step' => '0.01' ],
                    'desc_tip'          => true,
                ],
                [
                    'title'             => __( 'Cashfree PG Fee (%)', 'thaaniyamhub-multi-vendor-orders' ),
                    'desc'              => __( 'Payment gateway transaction fee percentage charged by Cashfree on gross customer inflow (Default: 2.00%).', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'                => 'thaaniyamhub_cashfree_pg_fee_percent',
                    'type'              => 'number',
                    'default'           => '2.00',
                    'custom_attributes' => [ 'min' => '0', 'max' => '100', 'step' => '0.01' ],
                    'desc_tip'          => true,
                ],
                [
                    'title'             => __( 'Cashfree PG Fixed Fee (₹)', 'thaaniyamhub-multi-vendor-orders' ),
                    'desc'              => __( 'Fixed per-transaction fee charged by Cashfree (if any, default: ₹0.00).', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'                => 'thaaniyamhub_cashfree_pg_fixed_fee',
                    'type'              => 'number',
                    'default'           => '0.00',
                    'custom_attributes' => [ 'min' => '0', 'step' => '0.01' ],
                    'desc_tip'          => true,
                ],
                [
                    'title'             => __( 'GST on Cashfree PG Fee (%)', 'thaaniyamhub-multi-vendor-orders' ),
                    'desc'              => __( 'GST levied on Cashfree PG transaction fee (Default: 18%).', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'                => 'thaaniyamhub_cashfree_pg_gst_percent',
                    'type'              => 'number',
                    'default'           => '18.00',
                    'custom_attributes' => [ 'min' => '0', 'max' => '100', 'step' => '0.01' ],
                    'desc_tip'          => true,
                ],
                [
                    'title'             => __( 'Cashfree Payout Transfer Fee (₹)', 'thaaniyamhub-multi-vendor-orders' ),
                    'desc'              => __( 'Direct transfer fee per vendor payout disbursement (Default: ₹2.50).', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'                => 'thaaniyamhub_cashfree_payout_fee',
                    'type'              => 'number',
                    'default'           => '2.50',
                    'custom_attributes' => [ 'min' => '0', 'step' => '0.01' ],
                    'desc_tip'          => true,
                ],
                [
                    'title'             => __( 'GST on Payout Fee (%)', 'thaaniyamhub-multi-vendor-orders' ),
                    'desc'              => __( 'GST levied on payout transfer charges (Default: 18%).', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'                => 'thaaniyamhub_cashfree_payout_gst_percent',
                    'type'              => 'number',
                    'default'           => '18.00',
                    'custom_attributes' => [ 'min' => '0', 'max' => '100', 'step' => '0.01' ],
                    'desc_tip'          => true,
                ],
                [
                    'title'    => __( 'Logistics Fulfillment Allocation', 'thaaniyamhub-multi-vendor-orders' ),
                    'desc'     => __( 'How customer shipping charges and actual courier freight are allocated between platform and vendor.', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'       => 'thaaniyamhub_financial_shipping_model',
                    'type'     => 'select',
                    'default'  => 'admin_retains',
                    'options'  => [
                        'admin_retains'   => __( 'Marketplace Fulfills (Admin retains shipping to cover Shiprocket freight)', 'thaaniyamhub-multi-vendor-orders' ),
                        'vendor_delivers' => __( 'Vendor Self-Fulfills (Customer shipping is passed to vendor payout)', 'thaaniyamhub-multi-vendor-orders' ),
                    ],
                    'desc_tip' => true,
                ],
                [
                    'type' => 'sectionend',
                    'id'   => 'thaaniyamhub_financial_section',
                ],
            ];
        } elseif ( 'api' === $current_section ) {
            $settings = [
                [
                    'title' => __( 'Shiprocket API Settings', 'thaaniyamhub-multi-vendor-orders' ),
                    'type'  => 'title',
                    'desc'  => __( 'Manage credentials used for order dispatch, AWB generation, label/invoice printing, and tracking webhook synchronization.', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'    => 'thaaniyamhub_api_section',
                ],
                [
                    'title'    => __( 'Shiprocket Email', 'thaaniyamhub-multi-vendor-orders' ),
                    'type'     => 'email',
                    'id'       => 'thaaniyamhub_shiprocket_api_email',
                    'default'  => '',
                    'desc_tip' => __( 'Registered email address of your Shiprocket merchant account.', 'thaaniyamhub-multi-vendor-orders' ),
                ],
                [
                    'title'       => __( 'Shiprocket Password', 'thaaniyamhub-multi-vendor-orders' ),
                    'type'        => 'password',
                    'id'          => 'thaaniyamhub_shiprocket_api_password_raw',
                    'default'     => '',
                    'desc'        => __( 'Stored AES-encrypted. Leave blank to keep existing password.', 'thaaniyamhub-multi-vendor-orders' ),
                    'placeholder' => __( 'Enter password to update', 'thaaniyamhub-multi-vendor-orders' ),
                ],
                [
                    'title'    => __( 'Shiprocket Channel ID', 'thaaniyamhub-multi-vendor-orders' ),
                    'type'     => 'text',
                    'id'       => 'thaaniyamhub_shiprocket_channel_id',
                    'default'  => '10832781',
                    'desc_tip' => __( 'The Channel ID configured in your Shiprocket merchant account.', 'thaaniyamhub-multi-vendor-orders' ),
                ],
                [
                    'title'    => __( 'Webhook Security Secret', 'thaaniyamhub-multi-vendor-orders' ),
                    'type'     => 'text',
                    'id'       => 'thaaniyamhub_shiprocket_webhook_secret',
                    'default'  => '',
                    'desc'     => __( 'Secret key to authenticate Shiprocket status webhook callbacks. Leave blank to disable validation.', 'thaaniyamhub-multi-vendor-orders' ),
                ],
                [
                    'type' => 'sectionend',
                    'id'   => 'thaaniyamhub_api_section',
                ],
                [
                    'title' => __( 'Custom Live Cost & Fallback Settings', 'thaaniyamhub-multi-vendor-orders' ),
                    'type'  => 'title',
                    'desc'  => __( 'Configure prepaid settings, site URL overrides, and metrics fallbacks for Shiprocket rates.', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'    => 'thaaniyamhub_live_cost_section',
                ],
                [
                    'id'       => 'thaaniyamhub_courier_display_mode',
                    'type'     => 'thaaniyamhub_hidden',
                    'default'  => 'recommended',
                ],
                [
                    'title'    => __( 'Force Prepaid Only', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'       => 'thaaniyamhub_prepaid_only',
                    'type'     => 'checkbox',
                    'default'  => 'no',
                    'desc'     => __( 'Only fetch and consider Prepaid courier rates from Shiprocket.', 'thaaniyamhub-multi-vendor-orders' ),
                ],
                [
                    'title'    => __( 'Customer Website URL (Optional Override)', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'       => 'thaaniyamhub_site_url_override',
                    'type'     => 'text',
                    'default'  => '',
                    'desc'     => __( 'Custom base URL passed to Shiprocket payload (leave blank to auto-detect home_url).', 'thaaniyamhub-multi-vendor-orders' ),
                ],
                [
                    'title'    => __( 'Default Box Dimensions (L x W x H in cm)', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'       => 'thaaniyamhub_default_dimensions',
                    'type'     => 'text',
                    'default'  => '10x10x10',
                    'desc'     => __( 'Fallback volumetric dimensions per item if product has no dimensions set.', 'thaaniyamhub-multi-vendor-orders' ),
                ],
                [
                    'title'    => __( 'Default Weight Fallback (kg)', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'       => 'thaaniyamhub_default_weight',
                    'type'     => 'number',
                    'default'  => '0.5',
                    'custom_attributes' => [ 'step' => '0.01', 'min' => '0.01' ],
                    'desc'     => __( 'Fallback weight per unit if product weight is empty or zero.', 'thaaniyamhub-multi-vendor-orders' ),
                ],
                [
                    'type' => 'sectionend',
                    'id'   => 'thaaniyamhub_live_cost_section',
                ],
            ];
        } elseif ( 'rates' === $current_section ) {
            $settings = [
                [
                    'title' => __( 'Custom Shipping Rates & Regional Zones', 'thaaniyamhub-multi-vendor-orders' ),
                    'type'  => 'title',
                    'desc'  => __( 'Define flat base shipping rates for intracity and regional zones across Tamil Nadu, Pondicherry, and neighboring states.', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'    => 'thaaniyamhub_rates_section',
                ],
                [
                    'title'   => __( 'Zone 0 (Intracity / Local) Flat Rate (₹)', 'thaaniyamhub-multi-vendor-orders' ),
                    'desc'    => __( 'Applied when vendor and customer are in the same local pincode cluster.', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'      => 'thaaniyamhub_shipping_rate_intracity',
                    'type'    => 'number',
                    'default' => '50',
                    'custom_attributes' => [ 'min' => '0' ],
                ],
                [
                    'title'   => __( 'Zone A (Kongu Belt) Flat Rate (₹)', 'thaaniyamhub-multi-vendor-orders' ),
                    'desc'    => __( 'Applied to Kongu belt districts (Nilgiris, Coimbatore, Tiruppur, Erode, Salem, Namakkal, Karur, Dharmapuri, Dindigul, Krishnagiri).', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'      => 'thaaniyamhub_shipping_rate_zone_a',
                    'type'    => 'number',
                    'default' => '70',
                    'custom_attributes' => [ 'min' => '0' ],
                ],
                [
                    'title'   => __( 'Zone B (South TN) Flat Rate (₹)', 'thaaniyamhub-multi-vendor-orders' ),
                    'desc'    => __( 'Applied to South TN districts (Theni, Madurai, Sivagangai, Virudhunagar, Ramanathapuram, Tenkasi, Thoothukudi, Tirunelveli, Kanyakumari).', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'      => 'thaaniyamhub_shipping_rate_zone_b',
                    'type'    => 'number',
                    'default' => '90',
                    'custom_attributes' => [ 'min' => '0' ],
                ],
                [
                    'title'   => __( 'Zone C (East TN) Flat Rate (₹)', 'thaaniyamhub-multi-vendor-orders' ),
                    'desc'    => __( 'Applied to East TN districts (Pudukkottai, Thanjavur, Tiruvarur, Nagapattinam, Tiruchirappalli, Ariyalur, Mayiladuthurai, Perambalur).', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'      => 'thaaniyamhub_shipping_rate_zone_c',
                    'type'    => 'number',
                    'default' => '90',
                    'custom_attributes' => [ 'min' => '0' ],
                ],
                [
                    'title'   => __( 'Zone D (North/Northeast) Flat Rate (₹)', 'thaaniyamhub-multi-vendor-orders' ),
                    'desc'    => __( 'Applied to Chennai, North TN districts, and Pondicherry.', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'      => 'thaaniyamhub_shipping_rate_zone_d',
                    'type'    => 'number',
                    'default' => '120',
                    'custom_attributes' => [ 'min' => '0' ],
                ],
                [
                    'title'   => __( 'Interstate Neighboring Rate (₹)', 'thaaniyamhub-multi-vendor-orders' ),
                    'desc'    => __( 'Applied when vendor and customer are in adjacent/bordering states (outside Tamil Nadu / Pondicherry).', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'      => 'thaaniyamhub_shipping_rate_interstate_neighboring',
                    'type'    => 'number',
                    'default' => '120',
                    'custom_attributes' => [ 'min' => '0' ],
                ],
                [
                    'title'   => __( 'Per-Additional-Vendor Surcharge (₹)', 'thaaniyamhub-multi-vendor-orders' ),
                    'desc'    => __( 'Fee added to shipping total for each extra vendor present in the cart.', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'      => 'thaaniyamhub_shipping_vendor_increment',
                    'type'    => 'number',
                    'default' => '40',
                    'custom_attributes' => [ 'min' => '0' ],
                ],
                [
                    'title'   => __( 'Free Shipping Threshold (Zones 0, A, B, C) (₹)', 'thaaniyamhub-multi-vendor-orders' ),
                    'desc'    => __( 'Subtotal value at which the customer gets free Standard Shipping for Zones 0, A, B, and C. Set to 0 to disable.', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'      => 'thaaniyamhub_shipping_free_threshold',
                    'type'    => 'number',
                    'default' => '899',
                    'custom_attributes' => [ 'min' => '0' ],
                ],
                [
                    'title'   => __( 'Free Shipping Threshold (Zone D) (₹)', 'thaaniyamhub-multi-vendor-orders' ),
                    'desc'    => __( 'Subtotal value at which the customer gets free Standard Shipping for Zone D. Set to 0 to disable.', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'      => 'thaaniyamhub_shipping_free_threshold_zone_d',
                    'type'    => 'number',
                    'default' => '1199',
                    'custom_attributes' => [ 'min' => '0' ],
                ],
                [
                    'title'   => __( 'Zone 0 Pincode Mapping Rules', 'thaaniyamhub-multi-vendor-orders' ),
                    'desc'    => __( 'Define Zone 0 matching rules. Format: vendor_prefix => customer_prefix, ... (exclude: exclude_prefix, ...). One rule per line.', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'      => 'thaaniyamhub_zone_0_rules',
                    'type'    => 'textarea',
                    'css'     => 'width: 100%; height: 100px;',
                    'default' => "641 => 641 (exclude: 6416)\n6416 => 6416\n642 => 642\n637 => 637",
                ],
                [
                    'type' => 'sectionend',
                    'id'   => 'thaaniyamhub_rates_section',
                ],
            ];
        } elseif ( 'payouts' === $current_section ) {
            $webhook_url = home_url( '/wp-json/thaaniyamhub/v1/cashfree-payout-webhook' );
            $settings = [
                [
                    'title' => __( 'Cashfree Vendor Payouts & Disbursals', 'thaaniyamhub-multi-vendor-orders' ),
                    'type'  => 'title',
                    'desc'  => __( 'Configure Cashfree Payout API credentials to enable instant direct vendor commission payouts via Bank Transfer (IMPS/NEFT) and UPI.', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'    => 'thaaniyamhub_cashfree_payout_section',
                ],
                [
                    'type'  => 'thaaniyamhub_cashfree_balance_widget',
                    'title' => __( 'Cashfree Wallet Balance', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'    => 'thaaniyamhub_cashfree_balance_widget',
                ],
                [
                    'title'    => __( 'Environment', 'thaaniyamhub-multi-vendor-orders' ),
                    'desc'     => __( 'Select "Sandbox / Gamma" for testing with test transfers or "Production" for live money disbursals.', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'       => 'thaaniyamhub_cashfree_payout_env',
                    'type'     => 'select',
                    'default'  => 'sandbox',
                    'options'  => [
                        'sandbox'    => __( 'Sandbox / Gamma (Test Mode)', 'thaaniyamhub-multi-vendor-orders' ),
                        'production' => __( 'Production (Live Transfers)', 'thaaniyamhub-multi-vendor-orders' ),
                    ],
                    'desc_tip' => true,
                ],
                [
                    'title'    => __( 'Cashfree Payout Client ID (App ID)', 'thaaniyamhub-multi-vendor-orders' ),
                    'desc'     => __( 'Client ID from your Cashfree Payout Dashboard -> Developers -> API Keys.', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'       => 'thaaniyamhub_cashfree_payout_client_id',
                    'type'     => 'text',
                    'default'  => '',
                    'desc_tip' => true,
                ],
                [
                    'title'       => __( 'Cashfree Payout Client Secret', 'thaaniyamhub-multi-vendor-orders' ),
                    'desc'        => __( 'Secret key from Cashfree Payout Dashboard. Kept secure.', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'          => 'thaaniyamhub_cashfree_payout_client_secret',
                    'type'        => 'password',
                    'default'     => '',
                    'placeholder' => __( 'Enter Cashfree Payout Secret Key', 'thaaniyamhub-multi-vendor-orders' ),
                ],
                [
                    'title'       => __( 'Webhook Secret Key', 'thaaniyamhub-multi-vendor-orders' ),
                    'desc'        => __( 'Secret configured in Cashfree Payout Dashboard -> Webhooks for verifying callbacks.', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'          => 'thaaniyamhub_cashfree_payout_webhook_secret',
                    'type'        => 'password',
                    'default'     => '',
                    'placeholder' => __( 'Enter Webhook Secret (Optional)', 'thaaniyamhub-multi-vendor-orders' ),
                ],
                [
                    'title'    => __( 'Default Bank Transfer Mode', 'thaaniyamhub-multi-vendor-orders' ),
                    'desc'     => __( 'Preferred transfer route for bank account payouts. IMPS/Auto is instant 24x7.', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'       => 'thaaniyamhub_cashfree_payout_transfer_mode',
                    'type'     => 'select',
                    'default'  => 'banktransfer',
                    'options'  => [
                        'banktransfer' => __( 'Auto / IMPS (Instant 24x7)', 'thaaniyamhub-multi-vendor-orders' ),
                        'neft'         => __( 'NEFT (Batch Transfer)', 'thaaniyamhub-multi-vendor-orders' ),
                        'rtgs'         => __( 'RTGS (High Value)', 'thaaniyamhub-multi-vendor-orders' ),
                    ],
                    'desc_tip' => true,
                ],
                [
                    'title'    => __( 'Webhook Callback URL', 'thaaniyamhub-multi-vendor-orders' ),
                    'type'     => 'text',
                    'id'       => 'thaaniyamhub_cashfree_payout_webhook_url_display',
                    'default'  => $webhook_url,
                    'desc'     => __( 'Copy this URL and paste it in Cashfree Payout Dashboard → Developers → Webhooks to receive instant transfer confirmation callbacks.', 'thaaniyamhub-multi-vendor-orders' ),
                    'custom_attributes' => [
                        'readonly' => 'readonly',
                        'onclick'  => 'this.select();',
                    ],
                ],
                [
                    'type'  => 'thaaniyamhub_cashfree_sync_widget',
                    'title' => __( 'Automatic Cron Poller & Manual Status Sync', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'    => 'thaaniyamhub_cashfree_sync_widget',
                ],
                [
                    'type' => 'sectionend',
                    'id'   => 'thaaniyamhub_cashfree_payout_section',
                ],
                [
                    'title' => __( 'Automated Scheduled Vendor Payouts', 'thaaniyamhub-multi-vendor-orders' ),
                    'type'  => 'title',
                    'desc'  => __( 'Automatically aggregate and disburse mature commissions to vendors on a recurring schedule (Daily, Weekly, or Monthly) via Cashfree.', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'    => 'thaaniyamhub_auto_payout_section',
                ],
                [
                    'type'  => 'thaaniyamhub_auto_payout_widget',
                    'title' => __( 'Scheduler Status & Manual Execution', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'    => 'thaaniyamhub_auto_payout_widget',
                ],
                [
                    'title'   => __( 'Enable Scheduled Auto-Payouts', 'thaaniyamhub-multi-vendor-orders' ),
                    'desc'    => __( 'Enable automatic recurring commission disbursals to vendors according to the configured schedule.', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'      => 'thaaniyamhub_auto_payout_enabled',
                    'type'    => 'checkbox',
                    'default' => 'no',
                ],
                [
                    'title'    => __( 'Payout Schedule Frequency', 'thaaniyamhub-multi-vendor-orders' ),
                    'desc'     => __( 'Select how frequently automated payouts should be processed.', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'       => 'thaaniyamhub_auto_payout_schedule',
                    'type'     => 'select',
                    'default'  => 'weekly',
                    'options'  => [
                        'daily'   => __( 'Daily Once (Every 24 Hours)', 'thaaniyamhub-multi-vendor-orders' ),
                        'weekly'  => __( 'Weekly Once (Selected Day of Week)', 'thaaniyamhub-multi-vendor-orders' ),
                        'monthly' => __( 'Monthly Once (1st of Every Month)', 'thaaniyamhub-multi-vendor-orders' ),
                    ],
                    'desc_tip' => true,
                ],
                [
                    'title'    => __( 'Weekly Payout Day', 'thaaniyamhub-multi-vendor-orders' ),
                    'desc'     => __( 'Applicable when weekly schedule is selected.', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'       => 'thaaniyamhub_auto_payout_day_of_week',
                    'type'     => 'select',
                    'default'  => 'monday',
                    'options'  => [
                        'monday'    => __( 'Monday', 'thaaniyamhub-multi-vendor-orders' ),
                        'tuesday'   => __( 'Tuesday', 'thaaniyamhub-multi-vendor-orders' ),
                        'wednesday' => __( 'Wednesday', 'thaaniyamhub-multi-vendor-orders' ),
                        'thursday'  => __( 'Thursday', 'thaaniyamhub-multi-vendor-orders' ),
                        'friday'    => __( 'Friday', 'thaaniyamhub-multi-vendor-orders' ),
                        'saturday'  => __( 'Saturday', 'thaaniyamhub-multi-vendor-orders' ),
                        'sunday'    => __( 'Sunday', 'thaaniyamhub-multi-vendor-orders' ),
                    ],
                    'desc_tip' => true,
                ],
                [
                    'title'    => __( 'Execution Time (24-Hour)', 'thaaniyamhub-multi-vendor-orders' ),
                    'desc'     => __( 'Time of day in store local timezone when the payout batch should trigger.', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'       => 'thaaniyamhub_auto_payout_time',
                    'type'     => 'select',
                    'default'  => '02:00',
                    'options'  => [
                        '00:00' => '12:00 AM (Midnight)',
                        '01:00' => '01:00 AM',
                        '02:00' => '02:00 AM',
                        '03:00' => '03:00 AM',
                        '04:00' => '04:00 AM',
                        '05:00' => '05:00 AM',
                        '06:00' => '06:00 AM',
                        '07:00' => '07:00 AM',
                        '08:00' => '08:00 AM',
                        '09:00' => '09:00 AM',
                        '10:00' => '10:00 AM',
                        '11:00' => '11:00 AM',
                        '12:00' => '12:00 PM (Noon)',
                        '13:00' => '01:00 PM',
                        '14:00' => '02:00 PM',
                        '15:00' => '03:00 PM',
                        '16:00' => '04:00 PM',
                        '17:00' => '05:00 PM',
                        '18:00' => '06:00 PM',
                        '19:00' => '07:00 PM',
                        '20:00' => '08:00 PM',
                        '21:00' => '09:00 PM',
                        '22:00' => '10:00 PM',
                        '23:00' => '11:00 PM',
                    ],
                    'desc_tip' => true,
                ],
                [
                    'title'             => __( 'Order Maturity Delay (Days)', 'thaaniyamhub-multi-vendor-orders' ),
                    'desc'              => __( 'Vendor commissions are only eligible for payout after the order has been in "Completed" status for this many days (protects against returns/disputes). Default: 4 days.', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'                => 'thaaniyamhub_auto_payout_delay_days',
                    'type'              => 'number',
                    'default'           => '4',
                    'custom_attributes' => [ 'min' => '0', 'step' => '1' ],
                    'desc_tip'          => true,
                ],
                [
                    'title'             => __( 'Minimum Payout Threshold (₹)', 'thaaniyamhub-multi-vendor-orders' ),
                    'desc'              => __( 'Minimum accumulated mature commission required for a vendor to initiate a payout. If a vendor\'s total is below this amount, it is deferred to the next cycle. Set 0 for no minimum.', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'                => 'thaaniyamhub_auto_payout_min_amount',
                    'type'              => 'number',
                    'default'           => '0',
                    'custom_attributes' => [ 'min' => '0', 'step' => '1' ],
                    'desc_tip'          => true,
                ],
                [
                    'type' => 'sectionend',
                    'id'   => 'thaaniyamhub_auto_payout_section',
                ],
            ];
        } elseif ( 'logs' === $current_section ) {
            $settings = [
                [
                    'title' => __( 'Plugin System Logs', 'thaaniyamhub-multi-vendor-orders' ),
                    'type'  => 'title',
                    'desc'  => __( 'View and manage system logs. All operations (debug, info, error) are logged automatically.', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'    => 'thaaniyamhub_logs_section',
                ],
                [
                    'type'  => 'thaaniyamhub_log_viewer',
                    'title' => __( 'System Logs', 'thaaniyamhub-multi-vendor-orders' ),
                    'desc'  => __( 'Displays the last 500 lines of today\'s WooCommerce log file (thaaniyamhub-orders-shiprocket). Logs are automatically rotated daily and managed by WooCommerce logger.', 'thaaniyamhub-multi-vendor-orders' ),
                    'id'    => 'thaaniyamhub_log_viewer_field',
                ],
                [
                    'type' => 'sectionend',
                    'id'   => 'thaaniyamhub_logs_section',
                ],
            ];
        }

        return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings, $current_section );
    }

    /**
     * Overrides parent save() to implement credentials encryption and cache invalidation.
     */
    public function save() {
        global $current_section;

        // Custom save handling for encrypted password
        if ( 'api' === $current_section && ! empty( $_POST['thaaniyamhub_shiprocket_api_password_raw'] ) ) {
            $raw_pass = sanitize_text_field( wp_unslash( $_POST['thaaniyamhub_shiprocket_api_password_raw'] ) );
            if ( class_exists( 'ThaaniyamHub_Shiprocket_API' ) ) {
                $encrypted = ThaaniyamHub_Shiprocket_API::encrypt_password( $raw_pass );
                update_option( 'thaaniyamhub_shiprocket_api_password', $encrypted );
            } else {
                update_option( 'thaaniyamhub_shiprocket_api_password', base64_encode( $raw_pass ) );
            }
            unset( $_POST['thaaniyamhub_shiprocket_api_password_raw'] );

            // Reset cached Shiprocket tokens to force authentication retry
            delete_option( 'thaaniyamhub_shiprocket_token' );
            delete_option( 'thaaniyamhub_shiprocket_token_expiry' );
        }

        // Cashfree Payout token cache invalidation on credentials update
        if ( 'payouts' === $current_section ) {
            delete_transient( 'thaaniyamhub_cf_payout_token' );
            if ( class_exists( 'ThaaniyamHub_Cashfree_Payout_API' ) ) {
                $client_id = sanitize_text_field( wp_unslash( $_POST['thaaniyamhub_cashfree_payout_client_id'] ?? '' ) );
                $env       = sanitize_text_field( wp_unslash( $_POST['thaaniyamhub_cashfree_payout_env'] ?? 'sandbox' ) );
                delete_transient( 'thaaniyamhub_cf_payout_token_' . md5( $client_id . '_' . $env ) );
            }
        }

        // Custom save handling for Zone 0 rules to avoid WooCommerce's default wp_kses_post escaping '=>' to '=&gt;'
        if ( isset( $_POST['thaaniyamhub_zone_0_rules'] ) ) {
            $rules_raw = wp_unslash( $_POST['thaaniyamhub_zone_0_rules'] );
            $rules_clean = sanitize_textarea_field( $rules_raw );
            update_option( 'thaaniyamhub_zone_0_rules', $rules_clean );
            unset( $_POST['thaaniyamhub_zone_0_rules'] );
        }

        $settings = $this->get_settings( $current_section );

        // Filter out manually saved settings so WooCommerce doesn't overwrite them or try to save raw password fields
        foreach ( $settings as $key => $setting ) {
            if ( isset( $setting['id'] ) && in_array( $setting['id'], [ 'thaaniyamhub_zone_0_rules', 'thaaniyamhub_shiprocket_api_password_raw' ], true ) ) {
                unset( $settings[ $key ] );
            }
        }

        WC_Admin_Settings::save_fields( $settings );

        // Sync payout scheduler cron when payouts settings are saved
        if ( 'payouts' === $current_section && class_exists( 'ThaaniyamHub_Payout_Scheduler' ) ) {
            ThaaniyamHub_Payout_Scheduler::sync_schedule();
        }
    }

    /**
     * Render the Cashfree Wallet Balance Widget.
     *
     * @param array $value Field details.
     */
    public function render_cashfree_balance_widget( $value ) {
        $env = get_option( 'thaaniyamhub_cashfree_payout_env', 'sandbox' );
        $env_label = ( 'production' === $env ) ? '<span style="background: #10b981; color: #fff; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;">LIVE / PRODUCTION</span>' : '<span style="background: #f59e0b; color: #fff; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;">SANDBOX / GAMMA</span>';
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label><?php echo esc_html( $value['title'] ); ?></label>
            </th>
            <td class="forminp forminp-thaaniyamhub-cashfree-balance">
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px; max-width: 600px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <div>
                            <span style="font-size: 13px; color: #64748b; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;"><?php esc_html_e( 'Payout Wallet Status', 'thaaniyamhub-multi-vendor-orders' ); ?></span>
                            <div style="margin-top: 4px;"><?php echo $env_label; ?></div>
                        </div>
                        <button type="button" id="thaaniyamhub_refresh_cf_balance" class="button button-secondary" style="display: inline-flex; align-items: center; justify-content: center; gap: 6px; line-height: 1; height: 32px; padding: 0 12px; vertical-align: middle;">
                            <span class="dashicons dashicons-update" style="font-size: 16px; width: 16px; height: 16px; line-height: 16px; margin: 0; display: inline-flex; align-items: center; justify-content: center;"></span>
                            <span style="line-height: 1;"><?php esc_html_e( 'Check Live Balance', 'thaaniyamhub-multi-vendor-orders' ); ?></span>
                        </button>
                    </div>

                    <div style="display: flex; gap: 20px; margin-top: 10px;">
                        <div style="flex: 1; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px 16px;">
                            <div style="font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase;"><?php esc_html_e( 'Available Balance', 'thaaniyamhub-multi-vendor-orders' ); ?></div>
                            <div id="cf_avail_balance" style="font-size: 22px; font-weight: 700; color: #0f172a; margin-top: 4px;">--</div>
                        </div>
                        <div style="flex: 1; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px 16px;">
                            <div style="font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase;"><?php esc_html_e( 'Ledger Balance', 'thaaniyamhub-multi-vendor-orders' ); ?></div>
                            <div id="cf_ledger_balance" style="font-size: 22px; font-weight: 700; color: #0f172a; margin-top: 4px;">--</div>
                        </div>
                    </div>

                    <div id="cf_balance_status_msg" style="margin-top: 10px; font-size: 12px; color: #64748b;">
                        <?php esc_html_e( 'Click "Check Live Balance" to fetch real-time funds available for vendor disbursals.', 'thaaniyamhub-multi-vendor-orders' ); ?>
                    </div>
                </div>

                <script>
                jQuery(document).ready(function($) {
                    $('#thaaniyamhub_refresh_cf_balance').on('click', function(e) {
                        e.preventDefault();
                        var $btn = $(this);
                        var $icon = $btn.find('.dashicons');
                        $icon.addClass('spin');
                        $btn.prop('disabled', true);
                        $('#cf_balance_status_msg').html('<span style="color: #0284c7;">' + '<?php echo esc_js( __( 'Connecting to Cashfree Payout API...', 'thaaniyamhub-multi-vendor-orders' ) ); ?>' + '</span>');

                        var clientId     = $('#thaaniyamhub_cashfree_payout_client_id').val();
                        var clientSecret = $('#thaaniyamhub_cashfree_payout_client_secret').val();
                        var env          = $('#thaaniyamhub_cashfree_payout_env').val();

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'thaaniyamhub_get_cf_balance',
                                nonce: '<?php echo wp_create_nonce( 'thaaniyamhub_cf_balance_nonce' ); ?>',
                                client_id: clientId,
                                client_secret: clientSecret,
                                env: env
                            },
                            success: function(response) {
                                $icon.removeClass('spin');
                                $btn.prop('disabled', false);
                                if (response.success && response.data) {
                                    var curr = '<?php echo esc_js( html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) ); ?> ';
                                    function formatCurrency(val) {
                                        var num = parseFloat(val);
                                        if (isNaN(num)) return '0.00';
                                        return num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                    }
                                    $('#cf_avail_balance').text(curr + formatCurrency(response.data.available_balance));
                                    $('#cf_ledger_balance').text(curr + formatCurrency(response.data.balance));
                                    $('#cf_balance_status_msg').html('<span style="color: #10b981; font-weight: 600;">✓ ' + '<?php echo esc_js( __( 'Balance fetched successfully at ', 'thaaniyamhub-multi-vendor-orders' ) ); ?>' + new Date().toLocaleTimeString() + '</span>');
                                } else {
                                    var err = (response.data && response.data.message) ? response.data.message : '<?php echo esc_js( __( 'Failed to retrieve balance. Check API credentials.', 'thaaniyamhub-multi-vendor-orders' ) ); ?>';
                                    $('#cf_balance_status_msg').html('<span style="color: #ef4444; font-weight: 600;">✗ ' + err + '</span>');
                                }
                            },
                            error: function(xhr, status, error) {
                                $icon.removeClass('spin');
                                $btn.prop('disabled', false);
                                var detail = xhr.responseText ? ' (' + xhr.status + ': ' + error + ')' : '';
                                $('#cf_balance_status_msg').html('<span style="color: #ef4444; font-weight: 600;">✗ ' + '<?php echo esc_js( __( 'AJAX request failed', 'thaaniyamhub-multi-vendor-orders' ) ); ?>' + detail + '</span>');
                            }
                        });
                    });
                });
                </script>
                <style>
                .forminp-thaaniyamhub-cashfree-balance .button,
                .forminp-thaaniyamhub-cashfree-sync .button,
                .forminp-thaaniyamhub-auto-payout-widget .button {
                    display: inline-flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    line-height: 1 !important;
                    gap: 6px !important;
                    vertical-align: middle !important;
                }
                .forminp-thaaniyamhub-cashfree-balance .button .dashicons,
                .forminp-thaaniyamhub-cashfree-sync .button .dashicons,
                .forminp-thaaniyamhub-auto-payout-widget .button .dashicons {
                    margin: 0 !important;
                    padding: 0 !important;
                    line-height: 1 !important;
                    display: inline-flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    vertical-align: middle !important;
                }
                .forminp-thaaniyamhub-cashfree-balance .button .dashicons::before,
                .forminp-thaaniyamhub-cashfree-sync .button .dashicons::before,
                .forminp-thaaniyamhub-auto-payout-widget .button .dashicons::before {
                    line-height: 1 !important;
                    font-size: inherit !important;
                    width: inherit !important;
                    height: inherit !important;
                    display: inline-block !important;
                    vertical-align: middle !important;
                }
                .dashicons.spin {
                    animation: dashicons-spin 1s infinite linear;
                    transform-origin: center center;
                }
                @keyframes dashicons-spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(359deg); }
                }
                </style>
            </td>
        </tr>
        <?php
    }

    /**
     * AJAX handler to query live Cashfree Payout wallet balance.
     */
    public static function ajax_get_cashfree_balance() {
        check_ajax_referer( 'thaaniyamhub_cf_balance_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized user.', 'thaaniyamhub-multi-vendor-orders' ) ] );
        }

        if ( ! class_exists( 'ThaaniyamHub_Cashfree_Payout_API' ) ) {
            wp_send_json_error( [ 'message' => __( 'Cashfree API class is missing.', 'thaaniyamhub-multi-vendor-orders' ) ] );
        }

        $client_id     = sanitize_text_field( wp_unslash( $_POST['client_id'] ?? '' ) );
        $client_secret = sanitize_text_field( wp_unslash( $_POST['client_secret'] ?? '' ) );
        $env           = sanitize_text_field( wp_unslash( $_POST['env'] ?? '' ) );

        $api = ThaaniyamHub_Cashfree_Payout_API::get_instance();
        
        $res = $api->get_balance( $client_id, $client_secret, $env );
        if ( is_wp_error( $res ) ) {
            wp_send_json_error( [ 'message' => $res->get_error_message() ] );
        }

        wp_send_json_success( $res );
    }

    /**
     * Render the Cashfree Payout Status Sync & Webhooks Widget.
     *
     * @param array $value Field details.
     */
    public function render_cashfree_sync_widget( $value ) {
        $last_sync_time = get_option( 'thaaniyamhub_cf_last_sync_timestamp', 0 );
        $last_sync_text = $last_sync_time ? human_time_diff( $last_sync_time, time() ) . ' ' . __( 'ago', 'thaaniyamhub-multi-vendor-orders' ) : __( 'Never', 'thaaniyamhub-multi-vendor-orders' );
        $webhook_url    = home_url( '/wp-json/thaaniyamhub/v1/cashfree-payout-webhook' );
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label><?php echo esc_html( $value['title'] ); ?></label>
            </th>
            <td class="forminp forminp-thaaniyamhub-cashfree-sync">
                <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 20px; max-width: 680px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 14px;">
                        <div>
                            <div style="font-size: 15px; font-weight: 700; color: #0f172a;">
                                <?php esc_html_e( 'Cashfree Status Sync & Reconciliation', 'thaaniyamhub-multi-vendor-orders' ); ?>
                            </div>
                            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">
                                <?php esc_html_e( 'Automatic background poller runs every 15 minutes to resolve pending transfers.', 'thaaniyamhub-multi-vendor-orders' ); ?>
                            </div>
                        </div>
                        <button type="button" id="thaaniyamhub_trigger_sync_btn" class="button button-primary" style="display: inline-flex; align-items: center; justify-content: center; gap: 6px; font-weight: 600; line-height: 1; height: 32px; padding: 0 12px; vertical-align: middle;">
                            <span class="dashicons dashicons-update" style="font-size: 16px; width: 16px; height: 16px; line-height: 16px; margin: 0; display: inline-flex; align-items: center; justify-content: center;"></span>
                            <span style="line-height: 1;"><?php esc_html_e( 'Sync Pending Transfers Now', 'thaaniyamhub-multi-vendor-orders' ); ?></span>
                        </button>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px;">
                            <div style="font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase;">
                                <?php esc_html_e( 'Cron Poller Schedule', 'thaaniyamhub-multi-vendor-orders' ); ?>
                            </div>
                            <div style="font-size: 14px; font-weight: 600; color: #059669; margin-top: 4px; display: flex; align-items: center; gap: 6px;">
                                <span style="display: inline-block; width: 8px; height: 8px; background: #10b981; border-radius: 50%;"></span>
                                <?php esc_html_e( 'Active (Every 15 Minutes)', 'thaaniyamhub-multi-vendor-orders' ); ?>
                            </div>
                        </div>
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px;">
                            <div style="font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase;">
                                <?php esc_html_e( 'Last Manual / Cron Sync', 'thaaniyamhub-multi-vendor-orders' ); ?>
                            </div>
                            <div id="cf_last_sync_display" style="font-size: 14px; font-weight: 600; color: #0f172a; margin-top: 4px;">
                                <?php echo esc_html( $last_sync_text ); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Webhook Quick Setup Guide -->
                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 12px 14px; margin-bottom: 14px;">
                        <div style="font-size: 12px; font-weight: 700; color: #166534; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                            <span class="dashicons dashicons-rest-api" style="font-size: 16px; width: 16px; height: 16px; line-height: 16px; margin: 0; display: inline-flex; align-items: center; justify-content: center;"></span>
                            <span style="line-height: 1;"><?php esc_html_e( 'Webhook Instant Updates (Recommended)', 'thaaniyamhub-multi-vendor-orders' ); ?></span>
                        </div>
                        <p style="font-size: 12px; color: #15803d; margin: 0 0 8px 0; line-height: 1.4;">
                            <?php esc_html_e( 'Configure this endpoint in your Cashfree Payout Dashboard to get instant callbacks for completed/failed transfers:', 'thaaniyamhub-multi-vendor-orders' ); ?>
                        </p>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <input type="text" readonly value="<?php echo esc_attr( $webhook_url ); ?>" id="cf_webhook_copy_input" style="flex: 1; font-family: monospace; font-size: 11px; background: #ffffff;" onclick="this.select();" />
                            <button type="button" class="button button-secondary" id="cf_copy_webhook_btn" style="white-space: nowrap; display: inline-flex; align-items: center; justify-content: center; line-height: 1; height: 30px;">
                                <span style="line-height: 1;"><?php esc_html_e( 'Copy URL', 'thaaniyamhub-multi-vendor-orders' ); ?></span>
                            </button>
                        </div>
                        <div style="font-size: 11px; color: #166534; margin-top: 6px;">
                            <?php esc_html_e( 'Subscribed Events: TRANSFER_SUCCESS, TRANSFER_FAILED, TRANSFER_REVERSED, TRANSFER_REJECTED', 'thaaniyamhub-multi-vendor-orders' ); ?>
                        </div>
                    </div>

                    <!-- Live Sync Result Output Box -->
                    <div id="cf_sync_output_container" style="display: none; margin-top: 14px;">
                        <div style="font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                            <?php esc_html_e( 'Sync Results & Activity:', 'thaaniyamhub-multi-vendor-orders' ); ?>
                        </div>
                        <div id="cf_sync_output_log" style="background: #1e293b; color: #f8fafc; font-family: Consolas, Monaco, monospace; font-size: 12px; padding: 12px; border-radius: 6px; max-height: 200px; overflow-y: auto; line-height: 1.6;"></div>
                    </div>
                </div>

                <script>
                jQuery(document).ready(function($) {
                    $('#cf_copy_webhook_btn').on('click', function(e) {
                        e.preventDefault();
                        var $input = $('#cf_webhook_copy_input');
                        $input.select();
                        document.execCommand('copy');
                        var $btn = $(this);
                        var original = $btn.text();
                        $btn.text('<?php echo esc_js( __( 'Copied! ✓', 'thaaniyamhub-multi-vendor-orders' ) ); ?>');
                        setTimeout(function() { $btn.text(original); }, 2000);
                    });

                    $('#thaaniyamhub_trigger_sync_btn').on('click', function(e) {
                        e.preventDefault();
                        var $btn = $(this);
                        var $icon = $btn.find('.dashicons');
                        $icon.addClass('spin');
                        $btn.prop('disabled', true);

                        var $container = $('#cf_sync_output_container');
                        var $log = $('#cf_sync_output_log');
                        $container.show();
                        $log.html('<div style="color: #38bdf8;">▶ ' + '<?php echo esc_js( __( 'Connecting to Cashfree Payout API to check pending transfers...', 'thaaniyamhub-multi-vendor-orders' ) ); ?>' + '</div>');

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'thaaniyamhub_manual_sync_transfers',
                                nonce: '<?php echo wp_create_nonce( 'thaaniyamhub_cf_sync_nonce' ); ?>'
                            },
                            success: function(response) {
                                $icon.removeClass('spin');
                                $btn.prop('disabled', false);

                                if (response.success && response.data) {
                                    var res = response.data;
                                    var html = '<div style="color: #4ade80; font-weight: 600;">✓ ' + (res.message || 'Sync completed successfully.') + '</div>';

                                    if (res.details && res.details.length > 0) {
                                        html += '<div style="margin-top: 8px; border-top: 1px solid #334155; padding-top: 8px;">';
                                        $.each(res.details, function(i, item) {
                                            var color = '#94a3b8';
                                            if (item.status === 'SUCCESS') color = '#4ade80';
                                            else if (item.status === 'FAILED') color = '#f87171';
                                            else if (item.status === 'PENDING') color = '#facc15';

                                            html += '<div style="margin-bottom: 4px;">• Withdrawal #' + item.withdrawal_id + ' (' + item.transfer_id + ') &rarr; <span style="color: ' + color + '; font-weight: 600;">[' + item.status + ']</span> ' + (item.utr ? 'UTR: ' + item.utr : (item.message || '')) + '</div>';
                                        });
                                        html += '</div>';
                                    }

                                    $log.html(html);
                                    $('#cf_last_sync_display').text('<?php echo esc_js( __( 'Just now', 'thaaniyamhub-multi-vendor-orders' ) ); ?>');
                                } else {
                                    var err = (response.data && response.data.message) ? response.data.message : '<?php echo esc_js( __( 'Sync failed. Check Cashfree API keys.', 'thaaniyamhub-multi-vendor-orders' ) ); ?>';
                                    $log.html('<div style="color: #f87171;">✗ ' + err + '</div>');
                                }
                            },
                            error: function(xhr, status, error) {
                                $icon.removeClass('spin');
                                $btn.prop('disabled', false);
                                $log.html('<div style="color: #f87171;">✗ ' + '<?php echo esc_js( __( 'AJAX error:', 'thaaniyamhub-multi-vendor-orders' ) ); ?> ' + error + '</div>');
                            }
                        });
                    });
                });
                </script>
            </td>
        </tr>
        <?php
    }

    /**
     * Render the custom log viewer field.
     *
     * @param array $value Field details.
     */
    public function render_log_viewer( $value ) {
        $log_file = function_exists( 'thaaniyamhub_get_latest_wc_log_file' ) ? thaaniyamhub_get_latest_wc_log_file() : false;
        $log_content = __( 'No active log file found for today.', 'thaaniyamhub-multi-vendor-orders' );
        
        if ( $log_file && file_exists( $log_file ) ) {
            $file_lines = file( $log_file );
            if ( is_array( $file_lines ) ) {
                $line_count = count( $file_lines );
                $limit = 500;
                if ( $line_count > $limit ) {
                    $file_lines = array_slice( $file_lines, $line_count - $limit );
                }
                $log_content = implode( '', $file_lines );
            }
        }
        
        $wc_logs_url = admin_url('admin.php?page=wc-status&tab=logs');
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label><?php echo esc_html( $value['title'] ); ?></label>
            </th>
            <td class="forminp forminp-thaaniyamhub-log-viewer">
                <div style="margin-bottom: 12px;">
                    <a href="<?php echo esc_url( admin_url( 'admin-post.php?action=thaaniyamhub_clear_logs' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Clear Today\'s Log', 'thaaniyamhub-multi-vendor-orders' ); ?></a>
                    <a href="<?php echo esc_url( admin_url( 'admin-post.php?action=thaaniyamhub_download_logs' ) ); ?>" class="button button-secondary" style="margin-left: 5px;"><?php esc_html_e( 'Download Today\'s Log', 'thaaniyamhub-multi-vendor-orders' ); ?></a>
                    <a href="<?php echo esc_url( $wc_logs_url ); ?>" class="button button-primary" style="margin-left: 5px;" target="_blank"><?php esc_html_e( 'View All Logs in WooCommerce Status', 'thaaniyamhub-multi-vendor-orders' ); ?> &rarr;</a>
                </div>
                <textarea readonly style="width: 100%; height: 450px; font-family: Consolas, Monaco, monospace; font-size: 12px; line-height: 1.5; white-space: pre; overflow-wrap: normal; background-color: #1e1e1e; color: #d4d4d4; border: 1px solid #c3c4c7; padding: 12px; border-radius: 4px; box-sizing: border-box;" id="thaaniyamhub_log_text"><?php echo esc_textarea( $log_content ); ?></textarea>
                <p class="description"><?php echo esc_html( $value['desc'] ); ?></p>
                <script>
                    jQuery(document).ready(function($) {
                        var textarea = $('#thaaniyamhub_log_text');
                        if (textarea.length) {
                            textarea.scrollTop(textarea[0].scrollHeight);
                        }
                    });
                </script>
            </td>
        </tr>
        <?php
    }

    /**
     * Render the Automated Scheduled Vendor Payouts Widget.
     *
     * @param array $value Field details.
     */
    public function render_auto_payout_widget( $value ) {
        $enabled     = get_option( 'thaaniyamhub_auto_payout_enabled', 'no' );
        $schedule    = get_option( 'thaaniyamhub_auto_payout_schedule', 'weekly' );
        $day_of_week = ucfirst( get_option( 'thaaniyamhub_auto_payout_day_of_week', 'monday' ) );
        $time_str    = get_option( 'thaaniyamhub_auto_payout_time', '02:00' );
        $delay_days  = absint( get_option( 'thaaniyamhub_auto_payout_delay_days', 4 ) );
        $min_amount  = get_option( 'thaaniyamhub_auto_payout_min_amount', 0 );

        $is_active = ( 'yes' === $enabled );
        $status_badge = $is_active
            ? '<span style="background: #10b981; color: #fff; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; letter-spacing: 0.5px;">ACTIVE / SCHEDULED</span>'
            : '<span style="background: #64748b; color: #fff; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; letter-spacing: 0.5px;">DISABLED / PAUSED</span>';

        $next_run_display = class_exists( 'ThaaniyamHub_Payout_Scheduler' )
            ? ThaaniyamHub_Payout_Scheduler::get_next_scheduled_display()
            : __( 'Not scheduled', 'thaaniyamhub-multi-vendor-orders' );

        $last_run = class_exists( 'ThaaniyamHub_Payout_Scheduler' )
            ? ThaaniyamHub_Payout_Scheduler::get_last_run_stats()
            : null;

        $freq_label = ( 'daily' === $schedule )
            ? sprintf( __( 'Every Day at %s', 'thaaniyamhub-multi-vendor-orders' ), $time_str )
            : ( ( 'weekly' === $schedule )
                ? sprintf( __( 'Every %s at %s', 'thaaniyamhub-multi-vendor-orders' ), $day_of_week, $time_str )
                : sprintf( __( '1st of Every Month at %s', 'thaaniyamhub-multi-vendor-orders' ), $time_str ) );
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label><?php echo esc_html( $value['title'] ); ?></label>
            </th>
            <td class="forminp forminp-thaaniyamhub-auto-payout-widget">
                <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 20px; max-width: 750px; box-shadow: 0 1px 4px rgba(0,0,0,0.06);">
                    
                    <!-- Header -->
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 16px;">
                        <div>
                            <div style="font-size: 14px; font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px;">
                                <?php esc_html_e( 'Automated Payout Engine Status', 'thaaniyamhub-multi-vendor-orders' ); ?>
                            </div>
                            <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                                <?php esc_html_e( 'Auto-settles mature vendor commissions grouped into one bulk payment per vendor.', 'thaaniyamhub-multi-vendor-orders' ); ?>
                            </div>
                        </div>
                        <div>
                            <?php echo $status_badge; ?>
                        </div>
                    </div>

                    <!-- Config & Schedule Overview Grid -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px;">
                            <div style="font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase;"><?php esc_html_e( 'Next Scheduled Execution', 'thaaniyamhub-multi-vendor-orders' ); ?></div>
                            <div style="font-size: 14px; font-weight: 700; color: #0284c7; margin-top: 4px;" id="auto_payout_next_run_display">
                                <?php echo esc_html( $next_run_display ); ?>
                            </div>
                        </div>

                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px;">
                            <div style="font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase;"><?php esc_html_e( 'Recurrence Schedule', 'thaaniyamhub-multi-vendor-orders' ); ?></div>
                            <div style="font-size: 14px; font-weight: 700; color: #334155; margin-top: 4px;">
                                <?php echo esc_html( $freq_label ); ?>
                            </div>
                        </div>

                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px;">
                            <div style="font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase;"><?php esc_html_e( 'Order Maturity Window', 'thaaniyamhub-multi-vendor-orders' ); ?></div>
                            <div style="font-size: 14px; font-weight: 700; color: #334155; margin-top: 4px;">
                                <?php echo sprintf( esc_html__( '%d Days after "Completed" Status', 'thaaniyamhub-multi-vendor-orders' ), $delay_days ); ?>
                            </div>
                        </div>

                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px;">
                            <div style="font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase;"><?php esc_html_e( 'Aggregation Rule', 'thaaniyamhub-multi-vendor-orders' ); ?></div>
                            <div style="font-size: 14px; font-weight: 700; color: #334155; margin-top: 4px;">
                                <?php esc_html_e( '1 Bulk Payout per Vendor', 'thaaniyamhub-multi-vendor-orders' ); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Last Run Details Card -->
                    <?php if ( $last_run ) : ?>
                    <div style="background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px 14px; margin-bottom: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <span style="font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase;"><?php esc_html_e( 'Last Execution Summary', 'thaaniyamhub-multi-vendor-orders' ); ?></span>
                            <span style="font-size: 11px; color: #64748b;"><?php echo esc_html( $last_run['timestamp'] ?? '' ); ?> (<?php echo esc_html( strtoupper( $last_run['source'] ?? 'cron' ) ); ?>)</span>
                        </div>
                        <div style="display: flex; gap: 20px; font-size: 13px; color: #1e293b;">
                            <div><strong><?php esc_html_e( 'Vendors Paid:', 'thaaniyamhub-multi-vendor-orders' ); ?></strong> <?php echo esc_html( $last_run['vendors_paid'] ?? 0 ); ?></div>
                            <div><strong><?php esc_html_e( 'Total Disbursed:', 'thaaniyamhub-multi-vendor-orders' ); ?></strong> ₹<?php echo esc_html( number_format( (float) ( $last_run['total_disbursed'] ?? 0 ), 2 ) ); ?></div>
                            <div><strong><?php esc_html_e( 'Status:', 'thaaniyamhub-multi-vendor-orders' ); ?></strong> <span style="font-weight: 700; color: <?php echo ( 'SUCCESS' === ( $last_run['status'] ?? '' ) ) ? '#10b981' : '#f59e0b'; ?>;"><?php echo esc_html( $last_run['status'] ?? '' ); ?></span></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Action Trigger & Info -->
                    <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 14px 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="max-width: 480px;">
                                <div style="font-weight: 700; color: #1e40af; font-size: 13px;"><?php esc_html_e( 'Manual Disbursal Trigger', 'thaaniyamhub-multi-vendor-orders' ); ?></div>
                                <div style="font-size: 12px; color: #1e3a8a; margin-top: 2px;">
                                    <?php esc_html_e( 'Instantly process all eligible mature orders and send bulk vendor payouts right now.', 'thaaniyamhub-multi-vendor-orders' ); ?>
                                </div>
                            </div>
                            <div>
                                <button type="button" id="thaaniyamhub_trigger_auto_payout_btn" class="button button-primary button-large" style="display: inline-flex; align-items: center; justify-content: center; gap: 6px; background: #0284c7; border-color: #0284c7; line-height: 1; height: 36px; padding: 0 16px; vertical-align: middle;">
                                    <span class="dashicons dashicons-money-alt" style="font-size: 18px; width: 18px; height: 18px; line-height: 18px; margin: 0; display: inline-flex; align-items: center; justify-content: center;"></span>
                                    <span style="line-height: 1;"><?php esc_html_e( 'Run Automated Payouts Now', 'thaaniyamhub-multi-vendor-orders' ); ?></span>
                                </button>
                            </div>
                        </div>

                        <!-- Live Output Container -->
                        <div id="auto_payout_output_container" style="display: none; margin-top: 14px; border-top: 1px solid #dbeafe; padding-top: 12px;">
                            <div style="font-size: 12px; font-weight: 700; color: #1e40af; margin-bottom: 6px;">
                                <?php esc_html_e( 'Execution Activity & Results:', 'thaaniyamhub-multi-vendor-orders' ); ?>
                            </div>
                            <div id="auto_payout_output_log" style="background: #0f172a; color: #f8fafc; font-family: Consolas, Monaco, monospace; font-size: 12px; padding: 12px; border-radius: 6px; max-height: 220px; overflow-y: auto; line-height: 1.6;"></div>
                        </div>
                    </div>
                </div>

                <script>
                jQuery(document).ready(function($) {
                    $('#thaaniyamhub_trigger_auto_payout_btn').on('click', function(e) {
                        e.preventDefault();
                        if (!confirm('<?php echo esc_js( __( 'Are you sure you want to run the automated vendor payout process now? This will initiate real Cashfree payouts for all mature eligible vendor orders.', 'thaaniyamhub-multi-vendor-orders' ) ); ?>')) {
                            return;
                        }

                        var $btn = $(this);
                        var $icon = $btn.find('.dashicons');
                        $icon.addClass('spin');
                        $btn.prop('disabled', true);

                        var $container = $('#auto_payout_output_container');
                        var $log = $('#auto_payout_output_log');
                        $container.show();
                        $log.html('<div style="color: #38bdf8;">▶ ' + '<?php echo esc_js( __( 'Scanning orders, validating maturity delay, and initiating vendor payouts via Cashfree API...', 'thaaniyamhub-multi-vendor-orders' ) ); ?>' + '</div>');

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'thaaniyamhub_trigger_manual_auto_payout',
                                nonce: '<?php echo wp_create_nonce( 'thaaniyamhub_auto_payout_manual_nonce' ); ?>'
                            },
                            success: function(response) {
                                $icon.removeClass('spin');
                                $btn.prop('disabled', false);

                                if (response.success && response.data) {
                                    var res = response.data;
                                    var html = '<div style="color: #4ade80; font-weight: 700;">✓ ' + (res.message || 'Automated payout run completed.') + '</div>';

                                    if (res.payout_details && res.payout_details.length > 0) {
                                        html += '<div style="margin-top: 8px; border-top: 1px solid #334155; padding-top: 8px;">';
                                        $.each(res.payout_details, function(i, item) {
                                            var color = (item.status === 'SUCCESS') ? '#4ade80' : '#facc15';
                                            html += '<div style="margin-bottom: 4px;">• Vendor #' + item.vendor_id + ' &rarr; ₹' + item.amount + ' (' + item.orders_count + ' orders) | Transfer ID: ' + item.transfer_id + ' <span style="color: ' + color + '; font-weight: 700;">[' + item.status + ']</span></div>';
                                        });
                                        html += '</div>';
                                    }

                                    if (res.errors && res.errors.length > 0) {
                                        html += '<div style="margin-top: 8px; border-top: 1px solid #334155; padding-top: 8px; color: #f87171;">';
                                        html += '<div style="font-weight: 700;">Errors / Warnings:</div>';
                                        $.each(res.errors, function(i, err) {
                                            html += '<div>• ' + err + '</div>';
                                        });
                                        html += '</div>';
                                    }

                                    $log.html(html);
                                } else {
                                    var err = (response.data && response.data.message) ? response.data.message : '<?php echo esc_js( __( 'Automated payout process failed. Check Cashfree credentials and system logs.', 'thaaniyamhub-multi-vendor-orders' ) ); ?>';
                                    $log.html('<div style="color: #f87171; font-weight: 700;">✗ ' + err + '</div>');
                                }
                            },
                            error: function(xhr, status, error) {
                                $icon.removeClass('spin');
                                $btn.prop('disabled', false);
                                $log.html('<div style="color: #f87171;">✗ ' + '<?php echo esc_js( __( 'AJAX error:', 'thaaniyamhub-multi-vendor-orders' ) ); ?> ' + error + '</div>');
                            }
                        });
                    });
                });
                </script>
            </td>
        </tr>
        <?php
    }

    /**
     * Render a hidden setting field.
     *
     * @param array $value Field details.
     */
    public function render_hidden_field( $value ) {
        $option_value = get_option( $value['id'], $value['default'] );
        ?>
        <input type="hidden" name="<?php echo esc_attr( $value['id'] ); ?>" id="<?php echo esc_attr( $value['id'] ); ?>" value="<?php echo esc_attr( $option_value ); ?>" />
        <?php
    }

}

}

