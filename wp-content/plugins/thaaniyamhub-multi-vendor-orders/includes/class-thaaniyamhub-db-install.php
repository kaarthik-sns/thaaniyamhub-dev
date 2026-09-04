<?php
/**
 * Thaaniyam Hub Marketplace — Database Install
 *
 * Creates and upgrades the custom tables that power the hybrid
 * database engine.
 *
 * Tables created:
 *   wp_thaaniyamhub_vendor_ledger  — immutable financial double-entry ledger
 *
 * @package thaaniyamhub-multi-vendor-orders
 */

defined('ABSPATH') || exit;

class ThaaniyamHub_DB_Install {

    /**
     * Current schema version. Bump this whenever a column is added/changed.
     */
    const SCHEMA_VERSION = '2.3.0';

    /**
     * Option key used to store the installed schema version.
     */
    const VERSION_OPTION = 'thaaniyamhub_db_schema_version';

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Called by register_activation_hook — installs or upgrades tables.
     */
    public static function run() {
        self::migrate_legacy_data();
        self::create_tables();
        update_option( self::VERSION_OPTION, self::SCHEMA_VERSION );
    }

    /**
     * Called on plugins_loaded — upgrades if schema version is behind.
     */
    public static function maybe_upgrade() {
        $installed = get_option( self::VERSION_OPTION, '0.0.0' );
        if ( version_compare( $installed, self::SCHEMA_VERSION, '<' ) ) {
            self::migrate_legacy_data();
            self::create_tables();
            update_option( self::VERSION_OPTION, self::SCHEMA_VERSION );
        }
        // Always ensure shipping & financial defaults exist in DB, even without a schema version bump.
        self::seed_defaults();
    }

    /**
     * Insert shipping-rate, cart-rule, and financial option defaults into the DB on first run.
     *
     * Uses add_option() which is a no-op when the option already exists, so it
     * is safe to call on every plugins_loaded without overwriting admin-saved values.
     */
    public static function seed_defaults() {
        $defaults = [
            'thaaniyamhub_shipping_rate_intracity'              => '50',
            'thaaniyamhub_shipping_rate_zone_a'                 => '70',
            'thaaniyamhub_shipping_rate_zone_b'                 => '90',
            'thaaniyamhub_shipping_rate_zone_c'                 => '90',
            'thaaniyamhub_shipping_rate_zone_d'                 => '120',
            'thaaniyamhub_shipping_rate_interstate_neighboring' => '120',
            'thaaniyamhub_shipping_vendor_increment'            => '40',
            'thaaniyamhub_shipping_free_threshold'              => '899',
            'thaaniyamhub_shipping_free_threshold_zone_d'       => '1199',
            'thaaniyamhub_zone_0_rules'                         => "641 => 641 (exclude: 6416)\n6416 => 6416\n642 => 642\n637 => 637",
            'thaaniyamhub_minimum_cart_value'                   => '0',
            // Financial Ledger & Service Cost Defaults
            'thaaniyamhub_commission_tax_rate'                  => '18.00',
            'thaaniyamhub_cashfree_pg_fee_percent'              => '2.00',
            'thaaniyamhub_cashfree_pg_fixed_fee'                => '0.00',
            'thaaniyamhub_cashfree_pg_gst_percent'              => '18.00',
            'thaaniyamhub_cashfree_payout_fee'                  => '2.50',
            'thaaniyamhub_cashfree_payout_gst_percent'          => '18.00',
            'thaaniyamhub_financial_shipping_model'             => 'admin_retains',
        ];
        foreach ( $defaults as $key => $value ) {
            // add_option() does nothing if the key already exists.
            add_option( $key, $value, '', 'no' );
        }

        // Auto-repair any legacy ledger rows where new calculation columns were left at 0.00
        global $wpdb;
        $ledger_table = $wpdb->prefix . 'thaaniyamhub_vendor_ledger';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$ledger_table}'" ) === $ledger_table ) {
            // Check if refunded_amount column exists
            $column_exists = $wpdb->get_results( "SHOW COLUMNS FROM `{$ledger_table}` LIKE 'refunded_amount'" );
            if ( empty( $column_exists ) ) {
                $wpdb->query( "ALTER TABLE `{$ledger_table}` ADD `refunded_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `tax_amount`" );
            }

            $wpdb->query( "
                UPDATE {$ledger_table}
                SET 
                    discount_total = IF(discount_total <= 0 AND item_subtotal > gross_sales, ROUND(item_subtotal - gross_sales, 2), discount_total),
                    item_subtotal = IF(item_subtotal <= 0 AND gross_sales > 0, ROUND(gross_sales + discount_total, 2), item_subtotal),
                    total_incoming = IF(total_incoming <= 0, gross_sales + shipping_charge + tax_amount, total_incoming),
                    commission_tax = IF(commission_tax <= 0 AND commission_deducted > 0, ROUND(commission_deducted * 0.18, 2), commission_tax),
                    shiprocket_shipping_cost = IF(shiprocket_shipping_cost <= 0 AND shipping_charge > 0, shipping_charge, shiprocket_shipping_cost),
                    gateway_fee = IF(gateway_fee <= 0 AND LOWER(payment_method) != 'cod' AND total_incoming > 0, ROUND(total_incoming * 0.02, 2), gateway_fee),
                    gateway_tax = IF(gateway_tax <= 0 AND gateway_fee > 0, ROUND(gateway_fee * 0.18, 2), gateway_tax),
                    other_service_cost = IF(other_service_cost <= 0, 2.95, other_service_cost),
                    net_profit = IF(net_profit = 0, ROUND(total_incoming - total_outgoing - refunded_amount, 2), net_profit),
                    profit_margin = IF((total_incoming - refunded_amount) > 0, ROUND((net_profit / (total_incoming - refunded_amount)) * 100, 2), 0.00)
                WHERE item_subtotal <= 0 OR total_incoming <= 0 OR total_outgoing <= 0 OR (discount_total <= 0 AND item_subtotal > gross_sales)
            " );
        }

        if ( class_exists('ThaaniyamHub_Order_Notes') ) {
            ThaaniyamHub_Order_Notes::clean_existing_duplicate_notes();
        }

        // Auto-add manifest_url, pickup_scheduled_date, and pickup_token_number to shiprocket fulfillment table if missing
        $fulfillment_table = $wpdb->prefix . 'thaaniyamhub_shiprocket_fulfillment';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$fulfillment_table}'" ) === $fulfillment_table ) {
            $manifest_col = $wpdb->get_results( "SHOW COLUMNS FROM `{$fulfillment_table}` LIKE 'manifest_url'" );
            if ( empty( $manifest_col ) ) {
                $wpdb->query( "ALTER TABLE `{$fulfillment_table}` ADD `manifest_url` TEXT DEFAULT NULL AFTER `commercial_invoice_url`" );
            }
            $pickup_date_col = $wpdb->get_results( "SHOW COLUMNS FROM `{$fulfillment_table}` LIKE 'pickup_scheduled_date'" );
            if ( empty( $pickup_date_col ) ) {
                $wpdb->query( "ALTER TABLE `{$fulfillment_table}` ADD `pickup_scheduled_date` VARCHAR(50) DEFAULT NULL AFTER `manifest_url`" );
            }
            $pickup_token_col = $wpdb->get_results( "SHOW COLUMNS FROM `{$fulfillment_table}` LIKE 'pickup_token_number'" );
            if ( empty( $pickup_token_col ) ) {
                $wpdb->query( "ALTER TABLE `{$fulfillment_table}` ADD `pickup_token_number` VARCHAR(100) DEFAULT NULL AFTER `pickup_scheduled_date`" );
            }
        }
    }

    /**
     * Migrate legacy Antigravity database tables, options, and metadata.
     */
    public static function migrate_legacy_data() {
        global $wpdb;

        // 1. Rename table if old table exists and new one does not
        $old_ledger = "{$wpdb->prefix}antigravity_vendor_ledger";
        $new_ledger = "{$wpdb->prefix}thaaniyamhub_vendor_ledger";
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$old_ledger'" ) === $old_ledger ) {
            if ( $wpdb->get_var( "SHOW TABLES LIKE '$new_ledger'" ) !== $new_ledger ) {
                $wpdb->query( "RENAME TABLE $old_ledger TO $new_ledger" );
                thaaniyamhub_log( "ThaaniyamHub_DB_Install: Migrated table $old_ledger to $new_ledger" );
            }
        }

        // 2. Rename Shiprocket fulfillment table
        $old_fulfillment = "{$wpdb->prefix}antigravity_shiprocket_fulfillment";
        $new_fulfillment = "{$wpdb->prefix}thaaniyamhub_shiprocket_fulfillment";
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$old_fulfillment'" ) === $old_fulfillment ) {
            if ( $wpdb->get_var( "SHOW TABLES LIKE '$new_fulfillment'" ) !== $new_fulfillment ) {
                $wpdb->query( "RENAME TABLE $old_fulfillment TO $new_fulfillment" );
                thaaniyamhub_log( "ThaaniyamHub_DB_Install: Migrated table $old_fulfillment to $new_fulfillment" );
            }
        }

        // 3. Rename Shiprocket API logs table
        $old_api_logs = "{$wpdb->prefix}antigravity_shiprocket_api_logs";
        $new_api_logs = "{$wpdb->prefix}thaaniyamhub_shiprocket_api_logs";
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$old_api_logs'" ) === $old_api_logs ) {
            if ( $wpdb->get_var( "SHOW TABLES LIKE '$new_api_logs'" ) !== $new_api_logs ) {
                $wpdb->query( "RENAME TABLE $old_api_logs TO $new_api_logs" );
                thaaniyamhub_log( "ThaaniyamHub_DB_Install: Migrated table $old_api_logs to $new_api_logs" );
            }
        }

        // 4. Migrate legacy options
        $option_map = [
            'ag_db_schema_version'         => 'thaaniyamhub_db_schema_version',
            'ag_enable_suborder_split'     => 'thaaniyamhub_enable_suborder_split',
            'ag_sf_db_schema_version'      => 'thaaniyamhub_sf_db_schema_version',
            'ag_enable_direct_dispatch'    => 'thaaniyamhub_enable_direct_dispatch',
            'ag_shiprocket_api_email'      => 'thaaniyamhub_shiprocket_api_email',
            'ag_shiprocket_api_password'   => 'thaaniyamhub_shiprocket_api_password',
            'ag_shiprocket_webhook_secret' => 'thaaniyamhub_shiprocket_webhook_secret',
            'ag_shiprocket_token'          => 'thaaniyamhub_shiprocket_token',
            'ag_shiprocket_token_expiry'   => 'thaaniyamhub_shiprocket_token_expiry',
        ];
        foreach ( $option_map as $old_key => $new_key ) {
            $val = get_option( $old_key, null );
            if ( $val !== null ) {
                if ( get_option( $new_key, null ) === null ) {
                    update_option( $new_key, $val );
                    thaaniyamhub_log( "ThaaniyamHub_DB_Install: Migrated option $old_key to $new_key" );
                }
                delete_option( $old_key );
            }
        }

        // 5. Migrate legacy metadata
        $wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_key = '_is_thaaniyamhub_suborder' WHERE meta_key = '_is_ag_suborder'" );
        $wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_key = '_thaaniyamhub_suborders_created' WHERE meta_key = '_ag_suborders_created'" );
    }

    // -------------------------------------------------------------------------
    // Private: DDL
    // -------------------------------------------------------------------------

    /**
     * Run all CREATE TABLE statements through dbDelta().
     * dbDelta() is safe to call repeatedly — it only applies missing changes.
     */
    private static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // -----------------------------------------------------------------
        // Table 1: Financial Ledger
        // -----------------------------------------------------------------
        $sql_ledger = "CREATE TABLE {$wpdb->prefix}thaaniyamhub_vendor_ledger (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            parent_order_id BIGINT(20) NOT NULL,
            sub_order_id BIGINT(20) NOT NULL,
            vendor_id BIGINT(20) NOT NULL,
            item_subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            discount_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            coupon_codes VARCHAR(255) NOT NULL DEFAULT '',
            gross_sales DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            shipping_charge DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            refunded_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total_incoming DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            commission_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            commission_deducted DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            commission_tax DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            vendor_net_payout DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            shiprocket_shipping_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            shiprocket_courier_name VARCHAR(100) NOT NULL DEFAULT '',
            shiprocket_awb VARCHAR(100) NOT NULL DEFAULT '',
            gateway_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            gateway_tax DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            other_service_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total_outgoing DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            net_profit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            profit_margin DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            payment_method VARCHAR(50) NOT NULL DEFAULT '',
            payout_status VARCHAR(50) NOT NULL DEFAULT 'pending',
            order_status VARCHAR(50) NOT NULL DEFAULT 'processing',
            customer_name VARCHAR(255) NOT NULL DEFAULT '',
            customer_city VARCHAR(100) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY sub_order_vendor (sub_order_id, vendor_id),
            KEY vendor_id (vendor_id),
            KEY parent_order_id (parent_order_id),
            KEY payout_status (payout_status),
            KEY order_status (order_status),
            KEY created_at (created_at)
        ) $charset;";

        dbDelta( $sql_ledger );

        // -----------------------------------------------------------------
        // Table 2: Order History
        // -----------------------------------------------------------------
        $sql_history = "CREATE TABLE {$wpdb->prefix}thaaniyamhub_order_history (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            order_id BIGINT(20) NOT NULL,
            sub_order_id BIGINT(20) NOT NULL DEFAULT 0,
            customer_id BIGINT(20) NOT NULL,
            vendor_id BIGINT(20) NOT NULL,
            product_id BIGINT(20) NOT NULL,
            product_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            weight DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
            additional_weight DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
            length DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            width DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            height DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            pickup_location VARCHAR(255) NOT NULL DEFAULT '',
            delivery_location VARCHAR(255) NOT NULL DEFAULT '',
            courier_name VARCHAR(100) NOT NULL DEFAULT '',
            courier_id VARCHAR(100) NOT NULL DEFAULT '',
            order_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            pickup_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            shipping_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            tax DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            commission_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            commission_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            order_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            payment_method VARCHAR(50) NOT NULL DEFAULT '',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY sub_order_id (sub_order_id),
            KEY vendor_id (vendor_id),
            KEY product_id (product_id)
        ) $charset;";

        dbDelta( $sql_history );

        // -----------------------------------------------------------------
        // Table 3: Shiprocket Fulfillment Engine
        // -----------------------------------------------------------------
        $sql_fulfillment = "CREATE TABLE {$wpdb->prefix}thaaniyamhub_shiprocket_fulfillment (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            sub_order_id BIGINT(20) NOT NULL,
            vendor_id BIGINT(20) NOT NULL,
            shiprocket_order_id VARCHAR(100) NOT NULL DEFAULT '',
            shiprocket_shipment_id VARCHAR(100) NOT NULL DEFAULT '',
            awb_code VARCHAR(100) DEFAULT NULL,
            courier_name VARCHAR(100) DEFAULT NULL,
            pickup_location_nickname VARCHAR(100) NOT NULL DEFAULT '',
            shipping_label_url TEXT DEFAULT NULL,
            commercial_invoice_url TEXT DEFAULT NULL,
            manifest_url TEXT DEFAULT NULL,
            pickup_scheduled_date VARCHAR(50) DEFAULT NULL,
            pickup_token_number VARCHAR(100) DEFAULT NULL,
            fulfillment_status VARCHAR(100) NOT NULL DEFAULT 'dispatched',
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY sub_order_id (sub_order_id),
            KEY vendor_id (vendor_id),
            KEY shiprocket_order_id (shiprocket_order_id)
        ) $charset;";

        dbDelta( $sql_fulfillment );

        // -----------------------------------------------------------------
        // Table 4: Failsafe API Log
        // -----------------------------------------------------------------
        $sql_api_logs = "CREATE TABLE {$wpdb->prefix}thaaniyamhub_shiprocket_api_logs (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            sub_order_id BIGINT(20) DEFAULT NULL,
            endpoint_requested VARCHAR(255) NOT NULL DEFAULT '',
            payload_sent LONGTEXT NOT NULL,
            payload_received LONGTEXT NOT NULL,
            http_status_code INT(5) NOT NULL DEFAULT 0,
            executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY sub_order_id (sub_order_id),
            KEY http_status_code (http_status_code)
        ) $charset;";

        dbDelta( $sql_api_logs );

        thaaniyamhub_log( 'ThaaniyamHub_DB_Install: Tables created/verified at schema v' . self::SCHEMA_VERSION );
    }

    // -------------------------------------------------------------------------
    // Utility: check if tables exist (useful for health check)
    // -------------------------------------------------------------------------

    /**
     * Returns true if all custom tables are present in the database.
     *
     * @return bool
     */
    public static function tables_exist() {
        global $wpdb;
        $tables = [
            "{$wpdb->prefix}thaaniyamhub_vendor_ledger",
            "{$wpdb->prefix}thaaniyamhub_order_history",
            "{$wpdb->prefix}thaaniyamhub_shiprocket_fulfillment",
            "{$wpdb->prefix}thaaniyamhub_shiprocket_api_logs",
        ];
        foreach ( $tables as $table ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
                return false;
            }
        }
        return true;
    }
}
