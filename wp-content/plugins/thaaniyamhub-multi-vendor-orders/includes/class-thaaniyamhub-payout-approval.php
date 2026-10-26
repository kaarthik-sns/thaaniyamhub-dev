<?php
/**
 * Thaaniyam Hub Marketplace — Payouts & Approvals Dashboard
 *
 * Centralized dashboard for marketplace administrators:
 *   Tab 1: Ready to Approve & Disburse (1-click vendor Cashfree payouts & ceiling reviews)
 *   Tab 2: Payout History & Bank Logs (historical disbursements, Cashfree Transfer IDs, UTRs, filters)
 *
 * @package thaaniyamhub-multi-vendor-orders
 */

defined( 'ABSPATH' ) || exit;

class ThaaniyamHub_Payout_Approval {

    /** DB table name (without prefix). */
    const TABLE = 'thaaniyamhub_payout_approval_queue';

    /** WP option used to store pending count for fast badge render. */
    const PENDING_COUNT_OPTION = 'thaaniyamhub_payout_approval_pending_count';

    /** Admin page slug. */
    const PAGE_SLUG = 'thaaniyamhub-payout-approvals';

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    /**
     * Register all WordPress hooks.
     */
    public static function init(): void {
        // Admin UI & CSV Export
        add_action( 'admin_menu',    [ __CLASS__, 'register_admin_menu' ] );
        add_action( 'admin_init',    [ __CLASS__, 'maybe_export_csv' ] );
        add_action( 'admin_notices', [ __CLASS__, 'maybe_show_pending_notice' ] );

        // AJAX handlers
        add_action( 'wp_ajax_thaaniyamhub_approve_payout',          [ __CLASS__, 'ajax_approve' ] );
        add_action( 'wp_ajax_thaaniyamhub_reject_payout',           [ __CLASS__, 'ajax_reject' ] );
        add_action( 'wp_ajax_thaaniyamhub_disburse_vendor_instant', [ __CLASS__, 'ajax_disburse_vendor_instant' ] );
        add_action( 'wp_ajax_thaaniyamhub_disburse_all_ready',      [ __CLASS__, 'ajax_disburse_all_ready' ] );

        // Inline styles / scripts for the approval page
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
    }

    // -------------------------------------------------------------------------
    // DB: Table creation (called from ThaaniyamHub_DB_Install)
    // -------------------------------------------------------------------------

    /**
     * Create or upgrade the approval queue table.
     * Safe to call repeatedly — uses dbDelta internally.
     */
    public static function create_table(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = $wpdb->prefix . self::TABLE;
        $sql   = "CREATE TABLE {$table} (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            vendor_id BIGINT(20) NOT NULL,
            vendor_name VARCHAR(255) NOT NULL DEFAULT '',
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            ceiling_limit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            order_ids LONGTEXT NOT NULL DEFAULT '',
            commission_ids LONGTEXT NOT NULL DEFAULT '',
            payout_profile LONGTEXT NOT NULL DEFAULT '',
            source VARCHAR(50) NOT NULL DEFAULT 'cron',
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            admin_note TEXT NOT NULL DEFAULT '',
            transfer_id VARCHAR(200) NOT NULL DEFAULT '',
            cashfree_status VARCHAR(100) NOT NULL DEFAULT '',
            utr VARCHAR(100) NOT NULL DEFAULT '',
            actioned_by BIGINT(20) NOT NULL DEFAULT 0,
            actioned_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY vendor_id (vendor_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset};";

        dbDelta( $sql );
    }

    // -------------------------------------------------------------------------
    // Public API: Enqueue a blocked payout
    // -------------------------------------------------------------------------

    /**
     * Save a ceiling-blocked payout into the approval queue.
     *
     * @param int    $vendor_id
     * @param float  $amount
     * @param float  $ceiling_limit
     * @param array  $commission_ids
     * @param array  $order_ids
     * @param array  $payout_profile
     * @param string $source
     * @return int|false
     */
    public static function enqueue_pending(
        int    $vendor_id,
        float  $amount,
        float  $ceiling_limit,
        array  $commission_ids,
        array  $order_ids,
        array  $payout_profile,
        string $source = 'cron'
    ) {
        global $wpdb;

        $vendor_user = get_userdata( $vendor_id );
        $vendor_name = $vendor_user ? $vendor_user->display_name : ( 'Vendor #' . $vendor_id );

        $result = $wpdb->insert(
            $wpdb->prefix . self::TABLE,
            [
                'vendor_id'      => $vendor_id,
                'vendor_name'    => $vendor_name,
                'amount'         => $amount,
                'ceiling_limit'  => $ceiling_limit,
                'order_ids'      => wp_json_encode( array_values( array_map( 'intval', $order_ids ) ) ),
                'commission_ids' => wp_json_encode( array_values( array_map( 'intval', $commission_ids ) ) ),
                'payout_profile' => wp_json_encode( $payout_profile ),
                'source'         => sanitize_text_field( $source ),
                'status'         => 'pending',
            ],
            [ '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s' ]
        );

        if ( false === $result ) {
            thaaniyamhub_log(
                'ThaaniyamHub_Payout_Approval: Failed to enqueue pending payout for Vendor #' . $vendor_id . ': ' . $wpdb->last_error,
                'error',
                'thaaniyamhub-cashfree-payout'
            );
            return false;
        }

        $row_id = (int) $wpdb->insert_id;
        self::refresh_pending_count();

        thaaniyamhub_log(
            sprintf(
                'ThaaniyamHub_Payout_Approval: Payout queued for admin approval (Queue ID: %d, Vendor #%d, Amount: %s, Ceiling: %s)',
                $row_id,
                $vendor_id,
                number_format( $amount, 2 ),
                number_format( $ceiling_limit, 2 )
            ),
            'warning',
            'thaaniyamhub-cashfree-payout'
        );

        return $row_id;
    }

    // -------------------------------------------------------------------------
    // Admin Menu
    // -------------------------------------------------------------------------

    /**
     * Register the Payout Approvals submenu under WooCommerce.
     */
    public static function register_admin_menu(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $pending = (int) get_option( self::PENDING_COUNT_OPTION, 0 );
        $label   = __( 'Payout Approvals', 'thaaniyamhub-multi-vendor-orders' );
        if ( $pending > 0 ) {
            $label .= ' <span class="awaiting-mod count-' . $pending . '"><span class="pending-count">'
                      . $pending . '</span></span>';
        }

        add_submenu_page(
            'woocommerce',
            __( 'Payout Approvals & History — ThaaniyamHub', 'thaaniyamhub-multi-vendor-orders' ),
            $label,
            'manage_woocommerce',
            self::PAGE_SLUG,
            [ __CLASS__, 'render_page' ]
        );
    }

    // -------------------------------------------------------------------------
    // Admin Notice
    // -------------------------------------------------------------------------

    /**
     * Show an admin notice when there are ceiling-held payouts waiting for approval.
     */
    public static function maybe_show_pending_notice(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $pending = (int) get_option( self::PENDING_COUNT_OPTION, 0 );
        if ( $pending <= 0 ) {
            return;
        }

        $screen = get_current_screen();
        if ( $screen && false !== strpos( $screen->id ?? '', self::PAGE_SLUG ) ) {
            return;
        }

        $url = esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=ready' ) );
        printf(
            '<div class="notice notice-warning"><p>&#9888;&#65039; <strong>%s</strong> &mdash; <a href="%s">%s</a></p></div>',
            sprintf(
                esc_html( _n(
                    '%d vendor payout is waiting for manual administrator approval (exceeded ceiling limit).',
                    '%d vendor payouts are waiting for manual administrator approval (exceeded ceiling limit).',
                    $pending,
                    'thaaniyamhub-multi-vendor-orders'
                ) ),
                $pending
            ),
            $url,
            esc_html__( 'Review & Approve Payouts', 'thaaniyamhub-multi-vendor-orders' )
        );
    }

    // -------------------------------------------------------------------------
    // Page Assets & Styles
    // -------------------------------------------------------------------------

    public static function enqueue_assets( string $hook ): void {
        if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
            return;
        }

        $css = '
            .th-payout-wrap { max-width: 1300px; margin-top: 15px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; }
            .th-header { margin-bottom: 20px; }
            .th-header h1 { font-size: 22px; font-weight: 700; color: #0f172a; margin: 0 0 6px 0; display: flex; align-items: center; gap: 10px; }
            .th-header p { margin: 0; color: #64748b; font-size: 13px; }
            
            /* Tabs Navigation */
            .th-nav-tabs { display: flex; gap: 8px; border-bottom: 2px solid #e2e8f0; margin-bottom: 20px; }
            .th-nav-tab { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; font-size: 14px; font-weight: 600; color: #64748b; text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.2s; }
            .th-nav-tab:hover { color: #0284c7; }
            .th-nav-tab.active { color: #0284c7; border-bottom-color: #0284c7; background: #f0f9ff; border-radius: 6px 6px 0 0; }
            .th-pill { background: #e2e8f0; color: #475569; font-size: 11px; padding: 2px 8px; border-radius: 12px; font-weight: 700; }
            .th-nav-tab.active .th-pill { background: #0284c7; color: #fff; }
            .th-pill-danger { background: #fee2e2; color: #dc2626; }
            
            /* Metric Cards Grid */
            .th-kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 14px; margin-bottom: 22px; }
            .th-kpi-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
            .th-kpi-lbl { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 4px; }
            .th-kpi-val { font-size: 20px; font-weight: 800; color: #0f172a; }
            .th-kpi-card.emerald { border-left: 4px solid #10b981; }
            .th-kpi-card.emerald .th-kpi-val { color: #059669; }
            .th-kpi-card.blue { border-left: 4px solid #0284c7; }
            .th-kpi-card.blue .th-kpi-val { color: #0284c7; }
            .th-kpi-card.amber { border-left: 4px solid #f59e0b; }
            .th-kpi-card.amber .th-kpi-val { color: #d97706; }
            .th-kpi-card.purple { border-left: 4px solid #8b5cf6; }
            .th-kpi-card.purple .th-kpi-val { color: #7c3aed; }
            .th-kpi-card.red { border-left: 4px solid #ef4444; }
            .th-kpi-card.red .th-kpi-val { color: #dc2626; }

            /* Action / Filter Bar */
            .th-action-bar { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
            
            /* Professional Filter Suite */
            .th-filter-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05); }
            .th-filter-top-bar { display: flex; gap: 12px; align-items: center; margin-bottom: 16px; flex-wrap: wrap; }
            .th-search-box-wrap { flex: 1; min-width: 280px; position: relative; display: flex; align-items: center; }
            .th-search-icon { position: absolute; left: 12px; color: #94a3b8; pointer-events: none; font-size: 18px; width: 18px; height: 18px; }
            .th-search-input { width: 100% !important; height: 38px !important; padding: 0 12px 0 38px !important; border: 1px solid #cbd5e1 !important; border-radius: 6px !important; font-size: 13px !important; color: #1e293b !important; background: #fff !important; transition: all 0.15s ease !important; box-sizing: border-box !important; }
            .th-search-input:focus { border-color: #0284c7 !important; box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15) !important; outline: none !important; }
            .th-filter-actions { display: flex; gap: 8px; align-items: center; }
            
            .th-filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 16px; }
            .th-form-group { display: flex; flex-direction: column; }
            .th-form-group label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #475569; margin-bottom: 6px; display: block; }
            .th-form-group select, .th-form-group input { height: 38px !important; width: 100% !important; padding: 0 10px !important; border: 1px solid #cbd5e1 !important; border-radius: 6px !important; font-size: 13px !important; color: #1e293b !important; background-color: #fff !important; box-sizing: border-box !important; transition: all 0.15s ease !important; }
            .th-form-group select:focus, .th-form-group input:focus { border-color: #0284c7 !important; box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15) !important; outline: none !important; }
            
            .th-date-toolbar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; padding-top: 14px; border-top: 1px solid #f1f5f9; }
            .th-date-inputs { display: flex; align-items: center; gap: 8px; }
            .th-date-inputs input[type="date"] { height: 34px !important; border: 1px solid #cbd5e1 !important; border-radius: 6px !important; font-size: 12px !important; padding: 0 8px !important; color: #1e293b !important; background: #fff !important; }
            .th-date-sep { font-size: 12px; color: #64748b; font-weight: 500; }
            .th-presets-group { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
            .th-preset-pill { display: inline-flex; align-items: center; padding: 5px 12px; border-radius: 20px; border: 1px solid #e2e8f0; background: #f8fafc; color: #475569; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.15s; line-height: 1; }
            .th-preset-pill:hover { background: #e2e8f0; color: #0f172a; }
            .th-preset-pill.active { background: #0284c7; color: #fff; border-color: #0284c7; }
            
            /* Active Filter Chips Bar */
            .th-active-filters-bar { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; }
            .th-active-filters-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-right: 4px; }
            .th-filter-chip { display: inline-flex; align-items: center; gap: 6px; background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; padding: 3px 10px; border-radius: 16px; font-size: 12px; font-weight: 600; }
            .th-chip-del { display: inline-flex; align-items: center; justify-content: center; width: 16px; height: 16px; border-radius: 50%; background: #0284c7; color: #fff !important; text-decoration: none; font-size: 12px; line-height: 1; font-weight: 700; margin-left: 2px; }
            .th-chip-del:hover { background: #0369a1; color: #fff !important; }
            .th-clear-all-btn { font-size: 12px; font-weight: 600; color: #ef4444; text-decoration: none; margin-left: auto; padding: 4px 8px; border-radius: 4px; transition: background 0.15s; }
            .th-clear-all-btn:hover { background: #fee2e2; color: #dc2626; }
            
            /* Permanent Enterprise Pagination Bar */
            .th-pagination-bar { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 18px; margin-top: 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
            .th-pagination-left { font-size: 13px; color: #475569; font-weight: 500; }
            .th-pagination-center { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #475569; }
            .th-pagination-right { display: flex; align-items: center; gap: 4px; }
            .th-page-btn { display: inline-flex; align-items: center; justify-content: center; min-width: 34px; height: 34px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; font-weight: 600; color: #334155; background: #fff; text-decoration: none; transition: all 0.15s; }
            .th-page-btn:hover:not(.disabled):not(.active) { background: #f1f5f9; border-color: #94a3b8; color: #0f172a; }
            .th-page-btn.active { background: #0284c7; border-color: #0284c7; color: #fff; cursor: default; }
            .th-page-btn.disabled { opacity: 0.45; cursor: not-allowed; pointer-events: none; background: #f8fafc; border-color: #e2e8f0; color: #94a3b8; }

            /* Tables */
            .th-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); margin-bottom: 24px; overflow: hidden; }
            .th-card-header { padding: 12px 18px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: #fafafa; }
            .th-card-title { font-size: 14px; font-weight: 700; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 8px; }
            .th-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; }
            .th-table th { background: #f8fafc; color: #475569; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; padding: 10px 14px; border-bottom: 1px solid #e2e8f0; }
            .th-table td { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; color: #1e293b; }
            .th-table tr:hover td { background: #fbfcfe; }
            .th-table tr:last-child td { border-bottom: none; }

            /* Badges */
            .th-status-pill { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
            .th-status-pill.success { background: #dcfce7; color: #15803d; }
            .th-status-pill.pending { background: #fef3c7; color: #b45309; }
            .th-status-pill.failed  { background: #fee2e2; color: #b91c1c; }
            .th-status-pill.ceiling { background: #ede9fe; color: #6d28d9; }
            .th-order-tag { display: inline-block; background: #f1f5f9; color: #0f172a; font-size: 11px; font-weight: 600; padding: 1px 6px; border-radius: 4px; text-decoration: none; border: 1px solid #e2e8f0; margin-right: 3px; margin-bottom: 2px; }
            .th-order-tag:hover { background: #0284c7; color: #fff; border-color: #0284c7; }

            /* Buttons */
            .th-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 6px 14px; border-radius: 5px; font-size: 12px; font-weight: 600; cursor: pointer; border: 1px solid transparent; transition: all 0.15s; text-decoration: none; }
            .th-btn-primary { background: #0284c7; color: #fff; border-color: #0284c7; }
            .th-btn-primary:hover { background: #0369a1; border-color: #0369a1; color: #fff; }
            .th-btn-success { background: #10b981; color: #fff; border-color: #10b981; }
            .th-btn-success:hover { background: #059669; border-color: #059669; color: #fff; }
            .th-btn-danger  { background: #ef4444; color: #fff; border-color: #ef4444; }
            .th-btn-danger:hover  { background: #dc2626; border-color: #dc2626; color: #fff; }
            .th-btn:disabled { opacity: 0.6; cursor: not-allowed; }

            /* Empty state */
            .th-empty { text-align: center; padding: 45px 20px; color: #64748b; }
            .th-empty-icon { font-size: 36px; margin-bottom: 10px; display: block; }
            .th-empty p { font-size: 14px; margin: 4px 0 12px 0; }

            /* Spin animation */
            .th-spin { animation: th-spin 1s infinite linear; }
            @keyframes th-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        ';
        wp_add_inline_style( 'wp-admin', $css );

        $js_data = wp_json_encode( [
            'nonce'         => wp_create_nonce( 'thaaniyamhub_payout_approval_action' ),
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'disburseMsg'   => __( 'Initiate live Cashfree transfer for this vendor now?', 'thaaniyamhub-multi-vendor-orders' ),
            'disburseAllMsg'=> __( 'Disburse payouts to ALL eligible vendors right now via Cashfree?', 'thaaniyamhub-multi-vendor-orders' ),
            'approveMsg'    => __( 'Approve this ceiling-held payout and send funds via Cashfree?', 'thaaniyamhub-multi-vendor-orders' ),
            'rejectMsg'     => __( 'Reject this payout? The vendor will NOT be paid.', 'thaaniyamhub-multi-vendor-orders' ),
        ] );
        wp_add_inline_script( 'jquery', "var thPayoutApproval = {$js_data};" );
    }

    // -------------------------------------------------------------------------
    // Main Admin Page Render
    // -------------------------------------------------------------------------

    public static function render_page(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'thaaniyamhub-multi-vendor-orders' ) );
        }

        $active_tab = sanitize_text_field( $_GET['tab'] ?? 'ready' );
        if ( ! in_array( $active_tab, [ 'ready', 'history' ], true ) ) {
            $active_tab = 'ready';
        }

        $pending_ceiling_count = (int) get_option( self::PENDING_COUNT_OPTION, 0 );
        $summary               = class_exists( 'ThaaniyamHub_Payout_Scheduler' ) ? ThaaniyamHub_Payout_Scheduler::get_pending_payouts_summary() : [];
        $ready_vendors_count   = count( $summary['vendors_ready'] ?? [] );
        $ready_total_count     = $ready_vendors_count + $pending_ceiling_count;
        ?>
        <div class="wrap th-payout-wrap">
            <div class="th-header">
                <h1>
                    <span class="dashicons dashicons-money-alt" style="font-size: 26px; width: 26px; height: 26px;"></span>
                    <?php esc_html_e( 'Payouts & Approvals Dashboard', 'thaaniyamhub-multi-vendor-orders' ); ?>
                </h1>
                <p><?php esc_html_e( 'Review and approve vendor payouts, disburse funds via Cashfree, and inspect live bank UTR logs.', 'thaaniyamhub-multi-vendor-orders' ); ?></p>
            </div>

            <!-- Tab Navigation -->
            <div class="th-nav-tabs">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=ready' ) ); ?>" class="th-nav-tab <?php echo 'ready' === $active_tab ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e( 'Ready to Approve & Disburse', 'thaaniyamhub-multi-vendor-orders' ); ?>
                    <?php if ( $ready_total_count > 0 ) : ?>
                        <span class="th-pill <?php echo $pending_ceiling_count > 0 ? 'th-pill-danger' : ''; ?>"><?php echo esc_html( $ready_total_count ); ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=history' ) ); ?>" class="th-nav-tab <?php echo 'history' === $active_tab ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-backup"></span>
                    <?php esc_html_e( 'Payout History & Bank Logs', 'thaaniyamhub-multi-vendor-orders' ); ?>
                </a>
            </div>

            <?php
            if ( 'history' === $active_tab ) {
                self::render_history_tab();
            } else {
                self::render_ready_tab( $summary, $pending_ceiling_count );
            }
            ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Tab 1: Ready to Approve & Disburse
    // -------------------------------------------------------------------------

    private static function render_ready_tab( array $summary, int $pending_ceiling_count ): void {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $ceiling_rows = $wpdb->get_results( "SELECT * FROM {$table} WHERE status = 'pending' ORDER BY created_at DESC", ARRAY_A );

        $ready_vendors   = $summary['vendors_ready'] ?? [];
        $missing_vendors = $summary['vendors_missing_info'] ?? [];
        $ready_amount    = (float) ( $summary['eligible_now_amount'] ?? 0.0 );
        $in_maturity_amt = (float) ( $summary['in_maturity_amount'] ?? 0.0 );
        $total_pending   = (float) ( $summary['total_pending_amount'] ?? 0.0 );
        $pending_orders  = (int) ( $summary['total_pending_orders'] ?? 0 );
        ?>
        <!-- KPI Cards -->
        <div class="th-kpi-grid">
            <div class="th-kpi-card emerald">
                <div class="th-kpi-lbl"><?php esc_html_e( 'Ready for Disbursal', 'thaaniyamhub-multi-vendor-orders' ); ?></div>
                <div class="th-kpi-val">₹<?php echo esc_html( number_format( $ready_amount, 2 ) ); ?></div>
            </div>
            <div class="th-kpi-card blue">
                <div class="th-kpi-lbl"><?php esc_html_e( 'Eligible Vendors', 'thaaniyamhub-multi-vendor-orders' ); ?></div>
                <div class="th-kpi-val"><?php echo esc_html( count( $ready_vendors ) ); ?></div>
            </div>
            <div class="th-kpi-card amber">
                <div class="th-kpi-lbl"><?php esc_html_e( 'In Maturity Window', 'thaaniyamhub-multi-vendor-orders' ); ?></div>
                <div class="th-kpi-val">₹<?php echo esc_html( number_format( $in_maturity_amt, 2 ) ); ?></div>
            </div>
            <div class="th-kpi-card purple">
                <div class="th-kpi-lbl"><?php esc_html_e( 'Ceiling Approvals Waiting', 'thaaniyamhub-multi-vendor-orders' ); ?></div>
                <div class="th-kpi-val"><?php echo esc_html( count( $ceiling_rows ) ); ?></div>
            </div>
        </div>

        <?php if ( ! empty( $ready_vendors ) || ! empty( $ceiling_rows ) ) : ?>
            <!-- Quick Disburse All Action Bar -->
            <div class="th-action-bar">
                <div style="font-size: 13px; color: #1e293b;">
                    <strong><?php esc_html_e( 'Quick Action:', 'thaaniyamhub-multi-vendor-orders' ); ?></strong>
                    <?php printf( esc_html__( '%d vendor(s) ready for disbursement totaling ₹%s.', 'thaaniyamhub-multi-vendor-orders' ), count( $ready_vendors ), number_format( $ready_amount, 2 ) ); ?>
                </div>
                <div>
                    <button type="button" class="th-btn th-btn-primary" id="th_btn_disburse_all" onclick="thDisburseAllReady();">
                        <span class="dashicons dashicons-money-alt"></span>
                        <?php esc_html_e( 'Disburse All Ready Payouts', 'thaaniyamhub-multi-vendor-orders' ); ?>
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <?php if ( ! empty( $ceiling_rows ) ) : ?>
            <!-- Ceiling Reviews Card -->
            <div class="th-card" style="border-left: 4px solid #8b5cf6;">
                <div class="th-card-header">
                    <h3 class="th-card-title">
                        <span class="dashicons dashicons-shield-alt" style="color: #7c3aed;"></span>
                        <?php esc_html_e( 'Payout Ceiling Review Queue (Manual Approval Required)', 'thaaniyamhub-multi-vendor-orders' ); ?>
                    </h3>
                    <span class="th-status-pill ceiling"><?php echo esc_html( count( $ceiling_rows ) ); ?> <?php esc_html_e( 'Awaiting Approval', 'thaaniyamhub-multi-vendor-orders' ); ?></span>
                </div>
                <table class="th-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Queue ID', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                            <th><?php esc_html_e( 'Date', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                            <th><?php esc_html_e( 'Vendor', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                            <th><?php esc_html_e( 'Amount', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                            <th><?php esc_html_e( 'Ceiling Limit', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                            <th><?php esc_html_e( 'Orders', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                            <th><?php esc_html_e( 'Action', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $ceiling_rows as $c_row ) :
                            $q_id      = (int) $c_row['id'];
                            $order_ids = json_decode( $c_row['order_ids'], true ) ?: [];
                        ?>
                        <tr id="th-ceiling-row-<?php echo esc_attr( $q_id ); ?>">
                            <td><strong>#Q<?php echo esc_html( $q_id ); ?></strong></td>
                            <td><?php echo esc_html( wp_date( 'd M Y H:i', strtotime( $c_row['created_at'] ) ) ); ?></td>
                            <td>
                                <strong><?php echo esc_html( $c_row['vendor_name'] ); ?></strong>
                                <br><small style="color:#64748b;">(Vendor #<?php echo esc_html( $c_row['vendor_id'] ); ?>)</small>
                            </td>
                            <td><strong style="font-size: 14px; color: #7c3aed;">₹<?php echo esc_html( number_format( (float) $c_row['amount'], 2 ) ); ?></strong></td>
                            <td><span style="color:#dc2626; font-size: 12px; font-weight:600;">₹<?php echo esc_html( number_format( (float) $c_row['ceiling_limit'], 2 ) ); ?></span></td>
                            <td>
                                <?php foreach ( $order_ids as $oid ) : ?>
                                    <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $oid . '&action=edit' ) ); ?>" target="_blank" class="th-order-tag">#<?php echo esc_html( $oid ); ?></a>
                                <?php endforeach; ?>
                            </td>
                            <td id="th-ceiling-actions-<?php echo esc_attr( $q_id ); ?>">
                                <button type="button" class="th-btn th-btn-success" onclick="thApproveCeilingRow(<?php echo esc_attr( $q_id ); ?>);">
                                    &#10003; <?php esc_html_e( 'Approve & Pay', 'thaaniyamhub-multi-vendor-orders' ); ?>
                                </button>
                                <button type="button" class="th-btn th-btn-danger" style="margin-left: 6px;" onclick="thRejectCeilingRow(<?php echo esc_attr( $q_id ); ?>);">
                                    &#10005; <?php esc_html_e( 'Reject', 'thaaniyamhub-multi-vendor-orders' ); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Ready Vendors Card -->
        <div class="th-card">
            <div class="th-card-header" style="flex-wrap: wrap; gap: 10px;">
                <h3 class="th-card-title">
                    <span class="dashicons dashicons-yes-alt" style="color: #059669;"></span>
                    <?php esc_html_e( 'Eligible Vendors Ready for Disbursal', 'thaaniyamhub-multi-vendor-orders' ); ?>
                    <span class="th-status-pill success" id="th_ready_count_pill"><?php echo esc_html( count( $ready_vendors ) ); ?> <?php esc_html_e( 'Ready', 'thaaniyamhub-multi-vendor-orders' ); ?></span>
                </h3>
                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <input type="text" id="th_ready_search" placeholder="<?php esc_attr_e( 'Filter vendor / store...', 'thaaniyamhub-multi-vendor-orders' ); ?>" onkeyup="thFilterReadyTable();" style="padding:4px 8px; font-size:12px; border:1px solid #cbd5e1; border-radius:4px; width:170px;" />
                    <select id="th_ready_rail" onchange="thFilterReadyTable();" style="padding:4px 8px; font-size:12px; border:1px solid #cbd5e1; border-radius:4px;">
                        <option value="all"><?php esc_html_e( 'All Rails', 'thaaniyamhub-multi-vendor-orders' ); ?></option>
                        <option value="bank"><?php esc_html_e( 'Bank Transfer', 'thaaniyamhub-multi-vendor-orders' ); ?></option>
                        <option value="upi"><?php esc_html_e( 'UPI VPA', 'thaaniyamhub-multi-vendor-orders' ); ?></option>
                    </select>
                    <select id="th_ready_sort" onchange="thSortReadyTable();" style="padding:4px 8px; font-size:12px; border:1px solid #cbd5e1; border-radius:4px;">
                        <option value="default"><?php esc_html_e( 'Sort: Default', 'thaaniyamhub-multi-vendor-orders' ); ?></option>
                        <option value="desc"><?php esc_html_e( 'Amount: High to Low', 'thaaniyamhub-multi-vendor-orders' ); ?></option>
                        <option value="asc"><?php esc_html_e( 'Amount: Low to High', 'thaaniyamhub-multi-vendor-orders' ); ?></option>
                        <option value="alpha"><?php esc_html_e( 'Vendor Name: A-Z', 'thaaniyamhub-multi-vendor-orders' ); ?></option>
                    </select>
                </div>
            </div>

            <?php if ( empty( $ready_vendors ) && empty( $ceiling_rows ) ) : ?>
                <div class="th-empty">
                    <span class="th-empty-icon">&#10004;&#65039;</span>
                    <h3><?php esc_html_e( 'All caught up!', 'thaaniyamhub-multi-vendor-orders' ); ?></h3>
                    <p><?php esc_html_e( 'No vendor payouts are currently awaiting approval or disbursal.', 'thaaniyamhub-multi-vendor-orders' ); ?></p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=history' ) ); ?>" class="th-btn th-btn-primary">
                        <?php esc_html_e( 'View Payout History &amp; Bank Logs &rarr;', 'thaaniyamhub-multi-vendor-orders' ); ?>
                    </a>
                </div>
            <?php else : ?>
                <table class="th-table" id="th_ready_table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Vendor / Store', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                            <th><?php esc_html_e( 'Payment Profile & Rail', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                            <th><?php esc_html_e( 'Ready Amount', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                            <th><?php esc_html_e( 'Eligible Orders', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                            <th><?php esc_html_e( 'Action', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $ready_vendors as $v ) :
                            $v_id   = (int) $v['id'];
                            $orders = $v['order_ids'] ?? [];
                        ?>
                        <tr id="th-ready-row-<?php echo esc_attr( $v_id ); ?>" class="th-ready-row" data-name="<?php echo esc_attr( strtolower( $v['name'] . ' ' . $v_id ) ); ?>" data-rail="<?php echo esc_attr( strtolower( $v['type'] ) ); ?>" data-amount="<?php echo esc_attr( (float) $v['amount'] ); ?>">
                            <td>
                                <strong><?php echo esc_html( $v['name'] ); ?></strong><br>
                                <a href="<?php echo esc_url( get_edit_user_link( $v_id ) ); ?>" target="_blank" style="font-size: 11px; color: #0284c7; text-decoration: none;">
                                    Vendor #<?php echo esc_html( $v_id ); ?> &rarr;
                                </a>
                            </td>
                            <td>
                                <span style="font-weight: 600; color: #1e293b;"><?php echo esc_html( $v['type'] ); ?></span><br>
                                <span style="font-size: 11px; color: #64748b;"><?php echo esc_html( $v['details'] ?? '' ); ?></span>
                            </td>
                            <td>
                                <strong style="font-size: 15px; color: #0f172a;">₹<?php echo esc_html( number_format( (float) $v['amount'], 2 ) ); ?></strong>
                            </td>
                            <td>
                                <div style="display: flex; flex-wrap: wrap; gap: 2px;">
                                    <?php if ( ! empty( $orders ) ) : ?>
                                        <?php foreach ( $orders as $oid ) : ?>
                                            <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $oid . '&action=edit' ) ); ?>" target="_blank" class="th-order-tag">#<?php echo esc_html( $oid ); ?></a>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <span style="color:#888;">&mdash;</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="th-status-pill success">
                                    <span class="dashicons dashicons-yes" style="font-size: 14px; width: 14px; height: 14px;"></span>
                                    <?php esc_html_e( 'Ready', 'thaaniyamhub-multi-vendor-orders' ); ?>
                                </span>
                            </td>
                            <td id="th-ready-actions-<?php echo esc_attr( $v_id ); ?>">
                                <button type="button" class="th-btn th-btn-primary" onclick="thDisburseSingleVendor(<?php echo esc_attr( $v_id ); ?>);">
                                    <span class="dashicons dashicons-money-alt"></span>
                                    <?php esc_html_e( 'Approve &amp; Pay Now', 'thaaniyamhub-multi-vendor-orders' ); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <?php if ( ! empty( $missing_vendors ) ) : ?>
            <!-- Vendors Missing Bank Details Card -->
            <div class="th-card" style="border: 1px dashed #f87171;">
                <div class="th-card-header" style="background: #fef2f2;">
                    <h3 class="th-card-title" style="color: #991b1b;">
                        <span class="dashicons dashicons-warning" style="color: #ef4444;"></span>
                        <?php esc_html_e( 'Vendors Missing Bank / UPI Details (Paused)', 'thaaniyamhub-multi-vendor-orders' ); ?>
                    </h3>
                    <span class="th-status-pill failed"><?php echo esc_html( count( $missing_vendors ) ); ?> <?php esc_html_e( 'Vendors', 'thaaniyamhub-multi-vendor-orders' ); ?></span>
                </div>
                <div style="padding: 12px 18px; font-size: 12px; color: #7f1d1d; line-height: 1.6;">
                    <?php esc_html_e( 'These vendors have mature pending orders, but have not configured their Bank Account or UPI VPA inside their WCFM vendor store settings. Payouts cannot be sent until details are added.', 'thaaniyamhub-multi-vendor-orders' ); ?>
                </div>
                <table class="th-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Vendor', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                            <th><?php esc_html_e( 'Pending Balance', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                            <th><?php esc_html_e( 'Action', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $missing_vendors as $mv ) : ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( $mv['name'] ); ?></strong> (ID #<?php echo esc_html( $mv['id'] ); ?>)
                            </td>
                            <td>₹<?php echo esc_html( number_format( (float) $mv['amount'], 2 ) ); ?></td>
                            <td><span class="th-status-pill failed"><?php esc_html_e( 'Missing Payment Info', 'thaaniyamhub-multi-vendor-orders' ); ?></span></td>
                            <td>
                                <a href="<?php echo esc_url( get_edit_user_link( (int) $mv['id'] ) ); ?>" target="_blank" class="th-btn" style="background:#f1f5f9; color:#0f172a;">
                                    <?php esc_html_e( 'Edit Profile &rarr;', 'thaaniyamhub-multi-vendor-orders' ); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <script>
        function thDisburseSingleVendor(vendorId) {
            if (!confirm(thPayoutApproval.disburseMsg)) return;

            var $actionsCell = jQuery('#th-ready-actions-' + vendorId);
            $actionsCell.html('<span style="color:#0284c7; font-weight:600;"><span class="dashicons dashicons-update th-spin"></span> ' + '<?php echo esc_js( __( 'Sending Cashfree transfer...', 'thaaniyamhub-multi-vendor-orders' ) ); ?>' + '</span>');

            jQuery.ajax({
                url: thPayoutApproval.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'thaaniyamhub_disburse_vendor_instant',
                    nonce: thPayoutApproval.nonce,
                    vendor_id: vendorId
                },
                success: function(res) {
                    if (res.success && res.data) {
                        $actionsCell.html('<span style="color:#10b981; font-weight:700;">✓ ' + (res.data.message || 'Disbursed successfully') + '</span>');
                        jQuery('#th-ready-row-' + vendorId).css('background', '#f0fdf4');
                    } else {
                        var err = (res.data && res.data.message) ? res.data.message : 'Transfer failed';
                        $actionsCell.html('<span style="color:#ef4444; font-weight:700;">✗ ' + err + '</span>');
                    }
                },
                error: function() {
                    $actionsCell.html('<span style="color:#ef4444; font-weight:700;">✗ Request error</span>');
                }
            });
        }

        function thDisburseAllReady() {
            if (!confirm(thPayoutApproval.disburseAllMsg)) return;

            var $btn = jQuery('#th_btn_disburse_all');
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update th-spin"></span> ' + '<?php echo esc_js( __( 'Processing all vendor transfers...', 'thaaniyamhub-multi-vendor-orders' ) ); ?>');

            jQuery.ajax({
                url: thPayoutApproval.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'thaaniyamhub_disburse_all_ready',
                    nonce: thPayoutApproval.nonce
                },
                success: function(res) {
                    if (res.success && res.data) {
                        alert(res.data.message || 'Batch payouts initiated successfully.');
                        window.location.reload();
                    } else {
                        alert((res.data && res.data.message) ? res.data.message : 'Batch disbursal failed.');
                        $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Disburse All Ready Payouts', 'thaaniyamhub-multi-vendor-orders' ) ); ?>');
                    }
                },
                error: function() {
                    alert('AJAX request failed.');
                    $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Disburse All Ready Payouts', 'thaaniyamhub-multi-vendor-orders' ) ); ?>');
                }
            });
        }

        function thApproveCeilingRow(queueId) {
            if (!confirm(thPayoutApproval.approveMsg)) return;

            var $cell = jQuery('#th-ceiling-actions-' + queueId);
            $cell.html('<span style="color:#0284c7; font-weight:600;"><span class="dashicons dashicons-update th-spin"></span> Processing...</span>');

            jQuery.ajax({
                url: thPayoutApproval.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'thaaniyamhub_approve_payout',
                    nonce: thPayoutApproval.nonce,
                    queue_id: queueId
                },
                success: function(res) {
                    if (res.success && res.data) {
                        $cell.html('<span style="color:#10b981; font-weight:700;">✓ ' + (res.data.message || 'Approved & Paid') + '</span>');
                        jQuery('#th-ceiling-row-' + queueId).css('background', '#f0fdf4');
                    } else {
                        $cell.html('<span style="color:#ef4444; font-weight:700;">✗ ' + ((res.data && res.data.message) ? res.data.message : 'Failed') + '</span>');
                    }
                },
                error: function() {
                    $cell.html('<span style="color:#ef4444;">✗ Request error</span>');
                }
            });
        }

        function thRejectCeilingRow(queueId) {
            if (!confirm(thPayoutApproval.rejectMsg)) return;

            var $cell = jQuery('#th-ceiling-actions-' + queueId);
            $cell.html('<span style="color:#64748b;">Processing...</span>');

            jQuery.ajax({
                url: thPayoutApproval.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'thaaniyamhub_reject_payout',
                    nonce: thPayoutApproval.nonce,
                    queue_id: queueId
                },
                success: function(res) {
                    if (res.success) {
                        $cell.html('<span style="color:#dc2626; font-weight:700;">✗ Rejected</span>');
                        jQuery('#th-ceiling-row-' + queueId).css('opacity', '0.5');
                    } else {
                        $cell.html('<span style="color:#ef4444;">' + (res.data.message || 'Error') + '</span>');
                    }
                }
            });
        }
        function thFilterReadyTable() {
            var searchVal = (document.getElementById('th_ready_search').value || '').toLowerCase().trim();
            var railVal = (document.getElementById('th_ready_rail').value || 'all').toLowerCase();
            var rows = document.querySelectorAll('tr.th-ready-row');
            var visibleCount = 0;

            rows.forEach(function(row) {
                var name = (row.getAttribute('data-name') || '').toLowerCase();
                var rail = (row.getAttribute('data-rail') || '').toLowerCase();
                var matchesSearch = !searchVal || name.indexOf(searchVal) !== -1;
                var matchesRail = (railVal === 'all') || (rail.indexOf(railVal) !== -1);

                if (matchesSearch && matchesRail) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            var countBadge = document.getElementById('th_ready_count_pill');
            if (countBadge) {
                countBadge.textContent = visibleCount + ' <?php esc_html_e( 'Ready', 'thaaniyamhub-multi-vendor-orders' ); ?>';
            }
        }

        function thSortReadyTable() {
            var sortVal = document.getElementById('th_ready_sort').value;
            var tbody = document.querySelector('#th_ready_table tbody');
            if (!tbody) return;
            var rows = Array.from(tbody.querySelectorAll('tr.th-ready-row'));

            rows.sort(function(a, b) {
                if (sortVal === 'desc') {
                    return parseFloat(b.getAttribute('data-amount') || 0) - parseFloat(a.getAttribute('data-amount') || 0);
                } else if (sortVal === 'asc') {
                    return parseFloat(a.getAttribute('data-amount') || 0) - parseFloat(b.getAttribute('data-amount') || 0);
                } else if (sortVal === 'alpha') {
                    return (a.getAttribute('data-name') || '').localeCompare(b.getAttribute('data-name') || '');
                }
                return 0;
            });

            rows.forEach(function(row) {
                tbody.appendChild(row);
            });
        }
        </script>
        <?php
    }

    // -------------------------------------------------------------------------
    // Tab 2: Payout History & Bank Logs
    // -------------------------------------------------------------------------

    private static function render_history_tab(): void {
        $filter_status = sanitize_text_field( $_GET['th_status'] ?? 'all' );
        $vendor_id     = absint( $_GET['th_vendor'] ?? 0 );
        $search        = sanitize_text_field( $_GET['th_search'] ?? '' );
        $date_from     = sanitize_text_field( $_GET['th_from'] ?? '' );
        $date_to       = sanitize_text_field( $_GET['th_to'] ?? '' );
        $rail          = sanitize_text_field( $_GET['th_rail'] ?? 'all' );
        $orderby       = sanitize_text_field( $_GET['th_orderby'] ?? 'date_desc' );
        $min_amount    = ! empty( $_GET['th_min_amount'] ) ? (float) $_GET['th_min_amount'] : 0.0;
        $max_amount    = ! empty( $_GET['th_max_amount'] ) ? (float) $_GET['th_max_amount'] : 0.0;
        $paged         = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $per_page      = absint( $_GET['th_per_page'] ?? 25 );
        if ( ! in_array( $per_page, [ 10, 25, 50, 100 ], true ) ) {
            $per_page = 25;
        }

        $history_args = [
            'status'     => $filter_status,
            'vendor_id'  => $vendor_id,
            'search'     => $search,
            'date_from'  => $date_from,
            'date_to'    => $date_to,
            'rail'       => $rail,
            'min_amount' => $min_amount,
            'max_amount' => $max_amount,
            'orderby'    => $orderby,
            'paged'      => $paged,
            'per_page'   => $per_page,
        ];

        $history_data = self::get_payout_history( $history_args );
        $stats        = self::get_history_stats( $history_args );
        $vendors      = self::get_all_vendors();
        $rows         = $history_data['rows'];

        $has_active_filters = ( 'all' !== $filter_status || $vendor_id > 0 || ! empty( $date_from ) || ! empty( $date_to ) || 'all' !== $rail || $min_amount > 0 || ! empty( $search ) || 'date_desc' !== $orderby );

        $current_filter_args = [
            'page'          => self::PAGE_SLUG,
            'tab'           => 'history',
            'th_vendor'     => $vendor_id ?: false,
            'th_status'     => 'all' !== $filter_status ? $filter_status : false,
            'th_rail'       => 'all' !== $rail ? $rail : false,
            'th_from'       => $date_from ?: false,
            'th_to'         => $date_to ?: false,
            'th_min_amount' => $min_amount > 0 ? $min_amount : false,
            'th_search'     => $search ?: false,
            'th_orderby'    => 'date_desc' !== $orderby ? $orderby : false,
            'th_per_page'   => 25 !== $per_page ? $per_page : false,
        ];
        $clear_single_filter = function( string $param_to_remove ) use ( $current_filter_args ) {
            $args = $current_filter_args;
            if ( 'th_dates' === $param_to_remove ) {
                unset( $args['th_from'], $args['th_to'] );
            } else {
                unset( $args[ $param_to_remove ] );
            }
            return add_query_arg( array_filter( $args ), admin_url( 'admin.php' ) );
        };

        $export_params = array_merge( $current_filter_args, [
            'th_export_csv'   => 1,
            'th_export_nonce' => wp_create_nonce( 'thaaniyamhub_export_payouts_csv' ),
        ] );
        $export_csv_url = add_query_arg( array_filter( $export_params ), admin_url( 'admin.php' ) );
        ?>
        <!-- History Statistics Grid -->
        <div class="th-kpi-grid">
            <div class="th-kpi-card emerald">
                <div class="th-kpi-lbl">
                    <?php esc_html_e( 'Total Disbursed (Completed)', 'thaaniyamhub-multi-vendor-orders' ); ?>
                    <?php if ( $has_active_filters ) : ?>
                        <span style="font-size: 10px; color: #059669; font-weight: normal;">(Filtered)</span>
                    <?php endif; ?>
                </div>
                <div class="th-kpi-val">₹<?php echo esc_html( number_format( (float) $stats['total_disbursed'], 2 ) ); ?></div>
            </div>
            <div class="th-kpi-card blue">
                <div class="th-kpi-lbl"><?php esc_html_e( 'Successful Transfers', 'thaaniyamhub-multi-vendor-orders' ); ?></div>
                <div class="th-kpi-val"><?php echo esc_html( $stats['completed_count'] ); ?></div>
            </div>
            <div class="th-kpi-card amber">
                <div class="th-kpi-lbl"><?php esc_html_e( 'Pending / Awaiting Bank', 'thaaniyamhub-multi-vendor-orders' ); ?></div>
                <div class="th-kpi-val"><?php echo esc_html( $stats['pending_count'] ); ?></div>
            </div>
            <div class="th-kpi-card red">
                <div class="th-kpi-lbl"><?php esc_html_e( 'Cancelled / Failed', 'thaaniyamhub-multi-vendor-orders' ); ?></div>
                <div class="th-kpi-val"><?php echo esc_html( $stats['failed_count'] ); ?></div>
            </div>
        </div>

        <?php if ( $has_active_filters ) : ?>
            <!-- Active Filter Chips Bar -->
            <div class="th-active-filters-bar">
                <span class="th-active-filters-title"><?php esc_html_e( 'Active Filters:', 'thaaniyamhub-multi-vendor-orders' ); ?></span>
                <?php if ( $vendor_id > 0 && isset( $vendors[ $vendor_id ] ) ) : ?>
                    <span class="th-filter-chip">
                        <span><strong><?php esc_html_e( 'Vendor:', 'thaaniyamhub-multi-vendor-orders' ); ?></strong> <?php echo esc_html( $vendors[ $vendor_id ] ); ?></span>
                        <a href="<?php echo esc_url( $clear_single_filter( 'th_vendor' ) ); ?>" class="th-chip-del" title="<?php esc_attr_e( 'Remove this filter', 'thaaniyamhub-multi-vendor-orders' ); ?>">&times;</a>
                    </span>
                <?php endif; ?>
                <?php if ( 'all' !== $filter_status ) : ?>
                    <span class="th-filter-chip">
                        <span><strong><?php esc_html_e( 'Status:', 'thaaniyamhub-multi-vendor-orders' ); ?></strong> <?php echo esc_html( ucfirst( $filter_status ) ); ?></span>
                        <a href="<?php echo esc_url( $clear_single_filter( 'th_status' ) ); ?>" class="th-chip-del" title="<?php esc_attr_e( 'Remove this filter', 'thaaniyamhub-multi-vendor-orders' ); ?>">&times;</a>
                    </span>
                <?php endif; ?>
                <?php if ( 'all' !== $rail ) : ?>
                    <span class="th-filter-chip">
                        <span><strong><?php esc_html_e( 'Rail:', 'thaaniyamhub-multi-vendor-orders' ); ?></strong> <?php echo esc_html( 'upi' === $rail ? 'UPI' : 'Bank Transfer' ); ?></span>
                        <a href="<?php echo esc_url( $clear_single_filter( 'th_rail' ) ); ?>" class="th-chip-del" title="<?php esc_attr_e( 'Remove this filter', 'thaaniyamhub-multi-vendor-orders' ); ?>">&times;</a>
                    </span>
                <?php endif; ?>
                <?php if ( ! empty( $date_from ) || ! empty( $date_to ) ) : ?>
                    <span class="th-filter-chip">
                        <span><strong><?php esc_html_e( 'Date:', 'thaaniyamhub-multi-vendor-orders' ); ?></strong> <?php echo esc_html( ( $date_from ?: 'Start' ) . ' → ' . ( $date_to ?: 'Now' ) ); ?></span>
                        <a href="<?php echo esc_url( $clear_single_filter( 'th_dates' ) ); ?>" class="th-chip-del" title="<?php esc_attr_e( 'Remove this filter', 'thaaniyamhub-multi-vendor-orders' ); ?>">&times;</a>
                    </span>
                <?php endif; ?>
                <?php if ( $min_amount > 0 ) : ?>
                    <span class="th-filter-chip">
                        <span><strong><?php esc_html_e( 'Min:', 'thaaniyamhub-multi-vendor-orders' ); ?></strong> ₹<?php echo esc_html( number_format( $min_amount, 2 ) ); ?></span>
                        <a href="<?php echo esc_url( $clear_single_filter( 'th_min_amount' ) ); ?>" class="th-chip-del" title="<?php esc_attr_e( 'Remove this filter', 'thaaniyamhub-multi-vendor-orders' ); ?>">&times;</a>
                    </span>
                <?php endif; ?>
                <?php if ( ! empty( $search ) ) : ?>
                    <span class="th-filter-chip">
                        <span><strong><?php esc_html_e( 'Search:', 'thaaniyamhub-multi-vendor-orders' ); ?></strong> "<?php echo esc_html( $search ); ?>"</span>
                        <a href="<?php echo esc_url( $clear_single_filter( 'th_search' ) ); ?>" class="th-chip-del" title="<?php esc_attr_e( 'Remove this filter', 'thaaniyamhub-multi-vendor-orders' ); ?>">&times;</a>
                    </span>
                <?php endif; ?>
                <?php if ( 'date_desc' !== $orderby ) : ?>
                    <span class="th-filter-chip">
                        <span><strong><?php esc_html_e( 'Sort:', 'thaaniyamhub-multi-vendor-orders' ); ?></strong> <?php echo esc_html( str_replace( '_', ' ', $orderby ) ); ?></span>
                        <a href="<?php echo esc_url( $clear_single_filter( 'th_orderby' ) ); ?>" class="th-chip-del" title="<?php esc_attr_e( 'Remove this filter', 'thaaniyamhub-multi-vendor-orders' ); ?>">&times;</a>
                    </span>
                <?php endif; ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=history' ) ); ?>" class="th-clear-all-btn">
                    &times; <?php esc_html_e( 'Clear All Filters', 'thaaniyamhub-multi-vendor-orders' ); ?>
                </a>
            </div>
        <?php endif; ?>

        <!-- Professional Filter Suite Card -->
        <div class="th-filter-card">
            <form method="GET" id="th_history_filter_form">
                <input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
                <input type="hidden" name="tab" value="history" />
                <input type="hidden" name="th_per_page" value="<?php echo esc_attr( $per_page ); ?>" />

                <!-- Top Bar: Search & Primary Action Buttons -->
                <div class="th-filter-top-bar">
                    <div class="th-search-box-wrap">
                        <span class="dashicons dashicons-search th-search-icon"></span>
                        <input type="text" name="th_search" value="<?php echo esc_attr( $search ); ?>" class="th-search-input" placeholder="<?php esc_attr_e( 'Search by Order #, Bank UTR, Cashfree Transfer ID, or Vendor Name...', 'thaaniyamhub-multi-vendor-orders' ); ?>" />
                    </div>
                    <div class="th-filter-actions">
                        <button type="submit" class="th-btn th-btn-primary" style="height: 38px; padding: 0 16px;">
                            <span class="dashicons dashicons-filter"></span>
                            <?php esc_html_e( 'Filter', 'thaaniyamhub-multi-vendor-orders' ); ?>
                        </button>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=history' ) ); ?>" class="th-btn" style="height: 38px; padding: 0 14px; background: #f8fafc; color: #475569; border: 1px solid #cbd5e1;">
                            <span class="dashicons dashicons-undo"></span>
                            <?php esc_html_e( 'Reset', 'thaaniyamhub-multi-vendor-orders' ); ?>
                        </a>
                        <a href="<?php echo esc_url( $export_csv_url ); ?>" class="th-btn th-btn-success" style="height: 38px; padding: 0 16px;">
                            <span class="dashicons dashicons-download"></span>
                            <?php esc_html_e( 'Export CSV', 'thaaniyamhub-multi-vendor-orders' ); ?>
                        </a>
                    </div>
                </div>

                <!-- 5 Structured Columns Grid -->
                <div class="th-filter-grid">
                    <div class="th-form-group">
                        <label for="th_vendor_select"><?php esc_html_e( 'Vendor', 'thaaniyamhub-multi-vendor-orders' ); ?></label>
                        <select id="th_vendor_select" name="th_vendor">
                            <option value="0"><?php esc_html_e( 'All Vendors', 'thaaniyamhub-multi-vendor-orders' ); ?></option>
                            <?php foreach ( $vendors as $vid => $vname ) : ?>
                                <option value="<?php echo esc_attr( $vid ); ?>" <?php selected( $vendor_id, $vid ); ?>>
                                    <?php echo esc_html( $vname . ' (#' . $vid . ')' ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="th-form-group">
                        <label for="th_status_select"><?php esc_html_e( 'Payout Status', 'thaaniyamhub-multi-vendor-orders' ); ?></label>
                        <select id="th_status_select" name="th_status">
                            <option value="all" <?php selected( $filter_status, 'all' ); ?>><?php esc_html_e( 'All Statuses', 'thaaniyamhub-multi-vendor-orders' ); ?></option>
                            <option value="completed" <?php selected( $filter_status, 'completed' ); ?>><?php esc_html_e( 'Completed / Success', 'thaaniyamhub-multi-vendor-orders' ); ?></option>
                            <option value="pending" <?php selected( $filter_status, 'pending' ); ?>><?php esc_html_e( 'Pending / In-Transit', 'thaaniyamhub-multi-vendor-orders' ); ?></option>
                            <option value="failed" <?php selected( $filter_status, 'failed' ); ?>><?php esc_html_e( 'Cancelled / Failed', 'thaaniyamhub-multi-vendor-orders' ); ?></option>
                        </select>
                    </div>

                    <div class="th-form-group">
                        <label for="th_rail_select"><?php esc_html_e( 'Payment Rail', 'thaaniyamhub-multi-vendor-orders' ); ?></label>
                        <select id="th_rail_select" name="th_rail">
                            <option value="all" <?php selected( $rail, 'all' ); ?>><?php esc_html_e( 'All Rails', 'thaaniyamhub-multi-vendor-orders' ); ?></option>
                            <option value="banktransfer" <?php selected( $rail, 'banktransfer' ); ?>><?php esc_html_e( 'Bank Transfer (IMPS/NEFT)', 'thaaniyamhub-multi-vendor-orders' ); ?></option>
                            <option value="upi" <?php selected( $rail, 'upi' ); ?>><?php esc_html_e( 'UPI VPA', 'thaaniyamhub-multi-vendor-orders' ); ?></option>
                        </select>
                    </div>

                    <div class="th-form-group">
                        <label for="th_orderby_select"><?php esc_html_e( 'Sort By', 'thaaniyamhub-multi-vendor-orders' ); ?></label>
                        <select id="th_orderby_select" name="th_orderby">
                            <option value="date_desc" <?php selected( $orderby, 'date_desc' ); ?>><?php esc_html_e( 'Date: Newest First', 'thaaniyamhub-multi-vendor-orders' ); ?></option>
                            <option value="date_asc" <?php selected( $orderby, 'date_asc' ); ?>><?php esc_html_e( 'Date: Oldest First', 'thaaniyamhub-multi-vendor-orders' ); ?></option>
                            <option value="amount_desc" <?php selected( $orderby, 'amount_desc' ); ?>><?php esc_html_e( 'Amount: High to Low', 'thaaniyamhub-multi-vendor-orders' ); ?></option>
                            <option value="amount_asc" <?php selected( $orderby, 'amount_asc' ); ?>><?php esc_html_e( 'Amount: Low to High', 'thaaniyamhub-multi-vendor-orders' ); ?></option>
                            <option value="vendor_asc" <?php selected( $orderby, 'vendor_asc' ); ?>><?php esc_html_e( 'Vendor Name: A-Z', 'thaaniyamhub-multi-vendor-orders' ); ?></option>
                        </select>
                    </div>

                    <div class="th-form-group">
                        <label for="th_min_amount_input"><?php esc_html_e( 'Min Amount (₹)', 'thaaniyamhub-multi-vendor-orders' ); ?></label>
                        <input type="number" step="0.01" min="0" id="th_min_amount_input" name="th_min_amount" value="<?php echo $min_amount > 0 ? esc_attr( $min_amount ) : ''; ?>" placeholder="0.00" />
                    </div>
                </div>

                <!-- Date Range Toolbar with Quick Presets -->
                <div class="th-date-toolbar">
                    <div class="th-date-inputs">
                        <span class="dashicons dashicons-calendar-alt" style="color: #64748b; font-size: 18px; width: 18px; height: 18px;"></span>
                        <span style="font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase;"><?php esc_html_e( 'Date Range:', 'thaaniyamhub-multi-vendor-orders' ); ?></span>
                        <input type="date" id="th_filter_from" name="th_from" value="<?php echo esc_attr( $date_from ); ?>" />
                        <span class="th-date-sep">&rarr;</span>
                        <input type="date" id="th_filter_to" name="th_to" value="<?php echo esc_attr( $date_to ); ?>" />
                    </div>

                    <div class="th-presets-group">
                        <span style="font-size: 11px; font-weight: 600; color: #64748b; margin-right: 2px;"><?php esc_html_e( 'Presets:', 'thaaniyamhub-multi-vendor-orders' ); ?></span>
                        <button type="button" class="th-preset-pill <?php echo ( ! empty( $date_from ) && $date_from === current_time( 'Y-m-d' ) && $date_to === current_time( 'Y-m-d' ) ) ? 'active' : ''; ?>" onclick="thSetDatePreset('today');"><?php esc_html_e( 'Today', 'thaaniyamhub-multi-vendor-orders' ); ?></button>
                        <button type="button" class="th-preset-pill" onclick="thSetDatePreset('yesterday');"><?php esc_html_e( 'Yesterday', 'thaaniyamhub-multi-vendor-orders' ); ?></button>
                        <button type="button" class="th-preset-pill" onclick="thSetDatePreset('7d');"><?php esc_html_e( 'Last 7 Days', 'thaaniyamhub-multi-vendor-orders' ); ?></button>
                        <button type="button" class="th-preset-pill <?php echo ( ! empty( $date_from ) && $date_from === current_time( 'Y-m-01' ) && $date_to === current_time( 'Y-m-d' ) ) ? 'active' : ''; ?>" onclick="thSetDatePreset('month');"><?php esc_html_e( 'This Month', 'thaaniyamhub-multi-vendor-orders' ); ?></button>
                        <?php if ( ! empty( $date_from ) || ! empty( $date_to ) ) : ?>
                            <button type="button" class="th-preset-pill" style="color: #ef4444; border-color: #fecaca; background: #fff;" onclick="thSetDatePreset('clear');"><?php esc_html_e( 'Clear Dates', 'thaaniyamhub-multi-vendor-orders' ); ?></button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <script>
        function thSetDatePreset(preset) {
            var now = new Date();
            var formatDate = function(d) {
                var year = d.getFullYear();
                var month = ('0' + (d.getMonth() + 1)).slice(-2);
                var day = ('0' + d.getDate()).slice(-2);
                return year + '-' + month + '-' + day;
            };
            var toInput = document.getElementById('th_filter_to');
            var fromInput = document.getElementById('th_filter_from');
            if (!toInput || !fromInput) return;

            if (preset === 'today') {
                toInput.value = formatDate(now);
                fromInput.value = formatDate(now);
            } else if (preset === 'yesterday') {
                var yest = new Date();
                yest.setDate(now.getDate() - 1);
                toInput.value = formatDate(yest);
                fromInput.value = formatDate(yest);
            } else if (preset === '7d') {
                toInput.value = formatDate(now);
                var past = new Date();
                past.setDate(now.getDate() - 7);
                fromInput.value = formatDate(past);
            } else if (preset === 'month') {
                toInput.value = formatDate(now);
                var firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
                fromInput.value = formatDate(firstDay);
            } else if (preset === 'clear') {
                toInput.value = '';
                fromInput.value = '';
            }
        }
        </script>

        <!-- Summary Bar Above Table -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; font-size: 13px; color: #64748b;">
            <div>
                <strong><?php echo esc_html( $history_data['total_count'] ); ?></strong>
                <?php echo esc_html( _n( 'disbursal record found', 'disbursal records found', $history_data['total_count'], 'thaaniyamhub-multi-vendor-orders' ) ); ?>
                <?php if ( $has_active_filters ) : ?>
                    <span style="font-size: 11px; background: #f1f5f9; color: #475569; padding: 2px 6px; border-radius: 4px; margin-left: 4px;"><?php esc_html_e( 'Filtered', 'thaaniyamhub-multi-vendor-orders' ); ?></span>
                <?php endif; ?>
            </div>
            <?php if ( ! empty( $stats['total_disbursed'] ) && (float) $stats['total_disbursed'] > 0 ) : ?>
                <div style="font-size: 12px; color: #334155;">
                    <?php esc_html_e( 'Disbursed in view:', 'thaaniyamhub-multi-vendor-orders' ); ?>
                    <strong style="color: #059669; font-size: 13px;">₹<?php echo esc_html( number_format( (float) $stats['total_disbursed'], 2 ) ); ?></strong>
                </div>
            <?php endif; ?>
        </div>

        <!-- Disbursals Table Card -->
        <div class="th-card">
            <?php if ( empty( $rows ) ) : ?>
                <div class="th-empty">
                    <span class="th-empty-icon">&#128196;</span>
                    <h3><?php esc_html_e( 'No payout records found', 'thaaniyamhub-multi-vendor-orders' ); ?></h3>
                    <p><?php esc_html_e( 'Try clearing your filters to see historical transfers.', 'thaaniyamhub-multi-vendor-orders' ); ?></p>
                </div>
            <?php else : ?>
                <table class="th-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'ID', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                            <th><?php esc_html_e( 'Disbursed Date', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                            <th><?php esc_html_e( 'Vendor', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                            <th><?php esc_html_e( 'Amount', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                            <th><?php esc_html_e( 'Cashfree Transfer ID', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                            <th><?php esc_html_e( 'Bank UTR / Ref', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                            <th><?php esc_html_e( 'Linked Orders', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                            <th><?php esc_html_e( 'Source Mode', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $rows as $r ) :
                            $w_id        = (int) $r['ID'];
                            $vid         = (int) $r['vendor_id'];
                            $v_name      = $r['vendor_name'] ?: ( 'Vendor #' . $vid );
                            $status_raw  = strtolower( (string) $r['withdraw_status'] );
                            $cf_status   = strtoupper( (string) ( $r['cashfree_status'] ?? '' ) );
                            $pill_class  = 'pending';
                            $disp_status = 'PENDING';

                            if ( 'completed' === $status_raw || 'SUCCESS' === $cf_status ) {
                                $pill_class  = 'success';
                                $disp_status = 'SUCCESS';
                            } elseif ( 'cancelled' === $status_raw || in_array( $cf_status, [ 'FAILED', 'REVERSED' ], true ) ) {
                                $pill_class  = 'failed';
                                $disp_status = $cf_status ?: 'FAILED';
                            } else {
                                $pill_class  = 'pending';
                                $disp_status = $cf_status ?: 'PENDING';
                            }

                            $order_ids = array_filter( array_map( 'intval', explode( ',', (string) $r['order_ids'] ) ) );
                            $date_show = ! empty( $r['withdraw_paid_date'] ) ? $r['withdraw_paid_date'] : $r['created'];
                        ?>
                        <tr>
                            <td><strong>#W<?php echo esc_html( $w_id ); ?></strong></td>
                            <td>
                                <?php echo esc_html( wp_date( 'd M Y, H:i', strtotime( $date_show ) ) ); ?>
                            </td>
                            <td>
                                <strong><?php echo esc_html( $v_name ); ?></strong><br>
                                <a href="<?php echo esc_url( get_edit_user_link( $vid ) ); ?>" target="_blank" style="font-size: 11px; color: #0284c7; text-decoration: none;">
                                    ID #<?php echo esc_html( $vid ); ?>
                                </a>
                            </td>
                            <td>
                                <strong style="font-size: 14px; color: #0f172a;">₹<?php echo esc_html( number_format( (float) $r['withdraw_amount'], 2 ) ); ?></strong>
                            </td>
                            <td>
                                <span style="font-family: Consolas, Monaco, monospace; font-size: 11px; color: #334155;">
                                    <?php echo esc_html( $r['cashfree_transfer_id'] ?: '—' ); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ( ! empty( $r['cashfree_utr'] ) ) : ?>
                                    <span style="font-weight: 700; color: #059669; font-size: 12px; font-family: monospace;">
                                        <?php echo esc_html( $r['cashfree_utr'] ); ?>
                                    </span>
                                <?php else : ?>
                                    <span style="color:#94a3b8; font-size: 11px;">Awaiting UTR</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="th-status-pill <?php echo esc_attr( $pill_class ); ?>">
                                    <?php echo esc_html( $disp_status ); ?>
                                </span>
                            </td>
                            <td>
                                <div style="display:flex; flex-wrap:wrap; gap:2px; max-width: 140px;">
                                    <?php foreach ( $order_ids as $oid ) : ?>
                                        <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $oid . '&action=edit' ) ); ?>" target="_blank" class="th-order-tag">#<?php echo esc_html( $oid ); ?></a>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td>
                                <span style="font-size: 11px; color: #64748b;">
                                    <?php echo esc_html( str_replace( [ 'by_auto_schedule_manual', 'by_auto_schedule', 'by_admin_approval' ], [ 'Manual Run', 'Auto Cron', 'Admin Approval' ], (string) $r['withdraw_mode'] ) ); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <?php
        // Permanent Enterprise Pagination Bar
        $total_count  = (int) ( $history_data['total_count'] ?? 0 );
        $total_pages  = max( 1, (int) ( $history_data['total_pages'] ?? 1 ) );
        $start_record = $total_count > 0 ? ( ( $paged - 1 ) * $per_page ) + 1 : 0;
        $end_record   = min( $total_count, $paged * $per_page );

        $pagination_params = [
            'page'          => self::PAGE_SLUG,
            'tab'           => 'history',
            'th_status'     => 'all' !== $filter_status ? $filter_status : false,
            'th_vendor'     => $vendor_id ?: false,
            'th_from'       => $date_from ?: false,
            'th_to'         => $date_to ?: false,
            'th_rail'       => 'all' !== $rail ? $rail : false,
            'th_min_amount' => $min_amount > 0 ? $min_amount : false,
            'th_orderby'    => 'date_desc' !== $orderby ? $orderby : false,
            'th_search'     => $search ?: false,
            'th_per_page'   => 25 !== $per_page ? $per_page : false,
        ];
        $pagination_params = array_filter( $pagination_params );

        $build_page_url = function( int $target_page ) use ( $pagination_params ) {
            return add_query_arg( array_merge( $pagination_params, [ 'paged' => $target_page ] ), admin_url( 'admin.php' ) );
        };
        ?>
        <div class="th-pagination-bar">
            <div class="th-pagination-left">
                <?php if ( $total_count > 0 ) : ?>
                    <?php printf( esc_html__( 'Showing %1$d–%2$d of %3$d records', 'thaaniyamhub-multi-vendor-orders' ), $start_record, $end_record, $total_count ); ?>
                <?php else : ?>
                    <?php esc_html_e( 'Showing 0 records', 'thaaniyamhub-multi-vendor-orders' ); ?>
                <?php endif; ?>
            </div>

            <div class="th-pagination-center">
                <label for="th_footer_per_page" style="font-size: 12px; color: #64748b; font-weight: 600;"><?php esc_html_e( 'Rows per page:', 'thaaniyamhub-multi-vendor-orders' ); ?></label>
                <select id="th_footer_per_page" onchange="window.location.href = this.value;" style="height: 32px; padding: 2px 8px; font-size: 12px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff;">
                    <?php foreach ( [ 10, 25, 50, 100 ] as $opt ) : 
                        $opt_params = array_merge( $pagination_params, [ 'th_per_page' => $opt, 'paged' => 1 ] );
                        $opt_url = add_query_arg( $opt_params, admin_url( 'admin.php' ) );
                    ?>
                        <option value="<?php echo esc_url( $opt_url ); ?>" <?php selected( $per_page, $opt ); ?>><?php echo esc_html( $opt ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="th-pagination-right">
                <!-- First Page -->
                <a href="<?php echo ( $paged > 1 ) ? esc_url( $build_page_url( 1 ) ) : '#'; ?>" 
                   class="th-page-btn <?php echo ( $paged <= 1 ) ? 'disabled' : ''; ?>" 
                   title="<?php esc_attr_e( 'First Page', 'thaaniyamhub-multi-vendor-orders' ); ?>">
                    &laquo; <?php esc_html_e( 'First', 'thaaniyamhub-multi-vendor-orders' ); ?>
                </a>

                <!-- Prev Page -->
                <a href="<?php echo ( $paged > 1 ) ? esc_url( $build_page_url( $paged - 1 ) ) : '#'; ?>" 
                   class="th-page-btn <?php echo ( $paged <= 1 ) ? 'disabled' : ''; ?>" 
                   title="<?php esc_attr_e( 'Previous Page', 'thaaniyamhub-multi-vendor-orders' ); ?>">
                    &lsaquo; <?php esc_html_e( 'Prev', 'thaaniyamhub-multi-vendor-orders' ); ?>
                </a>

                <!-- Page Numbers -->
                <?php
                $start_p = max( 1, $paged - 2 );
                $end_p   = min( $total_pages, $paged + 2 );
                for ( $p = $start_p; $p <= $end_p; $p++ ) :
                ?>
                    <a href="<?php echo ( $p === $paged ) ? '#' : esc_url( $build_page_url( $p ) ); ?>" 
                       class="th-page-btn <?php echo ( $p === $paged ) ? 'active' : ''; ?>">
                        <?php echo esc_html( $p ); ?>
                    </a>
                <?php endfor; ?>

                <!-- Next Page -->
                <a href="<?php echo ( $paged < $total_pages ) ? esc_url( $build_page_url( $paged + 1 ) ) : '#'; ?>" 
                   class="th-page-btn <?php echo ( $paged >= $total_pages ) ? 'disabled' : ''; ?>" 
                   title="<?php esc_attr_e( 'Next Page', 'thaaniyamhub-multi-vendor-orders' ); ?>">
                    <?php esc_html_e( 'Next', 'thaaniyamhub-multi-vendor-orders' ); ?> &rsaquo;
                </a>

                <!-- Last Page -->
                <a href="<?php echo ( $paged < $total_pages ) ? esc_url( $build_page_url( $total_pages ) ) : '#'; ?>" 
                   class="th-page-btn <?php echo ( $paged >= $total_pages ) ? 'disabled' : ''; ?>" 
                   title="<?php esc_attr_e( 'Last Page', 'thaaniyamhub-multi-vendor-orders' ); ?>">
                    <?php esc_html_e( 'Last', 'thaaniyamhub-multi-vendor-orders' ); ?> &raquo;
                </a>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // CSV Export Handler
    // -------------------------------------------------------------------------

    public static function maybe_export_csv(): void {
        if ( ! isset( $_GET['page'] ) || self::PAGE_SLUG !== $_GET['page'] ) {
            return;
        }
        if ( empty( $_GET['th_export_csv'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'thaaniyamhub-multi-vendor-orders' ) );
        }
        check_admin_referer( 'thaaniyamhub_export_payouts_csv', 'th_export_nonce' );

        $filter_status = sanitize_text_field( $_GET['th_status'] ?? 'all' );
        $vendor_id     = absint( $_GET['th_vendor'] ?? 0 );
        $search        = sanitize_text_field( $_GET['th_search'] ?? '' );
        $date_from     = sanitize_text_field( $_GET['th_from'] ?? '' );
        $date_to       = sanitize_text_field( $_GET['th_to'] ?? '' );
        $rail          = sanitize_text_field( $_GET['th_rail'] ?? 'all' );
        $orderby       = sanitize_text_field( $_GET['th_orderby'] ?? 'date_desc' );
        $min_amount    = ! empty( $_GET['th_min_amount'] ) ? (float) $_GET['th_min_amount'] : 0.0;
        $max_amount    = ! empty( $_GET['th_max_amount'] ) ? (float) $_GET['th_max_amount'] : 0.0;

        $history_data = self::get_payout_history( [
            'status'     => $filter_status,
            'vendor_id'  => $vendor_id,
            'search'     => $search,
            'date_from'  => $date_from,
            'date_to'    => $date_to,
            'rail'       => $rail,
            'min_amount' => $min_amount,
            'max_amount' => $max_amount,
            'orderby'    => $orderby,
            'paged'      => 1,
            'per_page'   => 5000,
        ] );

        $filename = 'thaaniyamhub-disbursals-' . gmdate( 'Y-m-d' ) . '.csv';
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );
        // UTF-8 BOM for Excel
        fprintf( $output, chr(0xEF) . chr(0xBB) . chr(0xBF) );

        fputcsv( $output, [
            'Withdrawal ID',
            'Disbursed Date',
            'Vendor ID',
            'Vendor Name',
            'Amount (INR)',
            'Status',
            'Cashfree Transfer ID',
            'Bank UTR / Ref',
            'Payment Rail',
            'Linked Orders',
            'Source Mode',
        ] );

        foreach ( $history_data['rows'] as $r ) {
            $w_id      = (int) $r['ID'];
            $vid       = (int) $r['vendor_id'];
            $v_name    = $r['vendor_name'] ?: ( 'Vendor #' . $vid );
            $status    = strtoupper( $r['cashfree_status'] ?: $r['withdraw_status'] );
            $date_show = ! empty( $r['withdraw_paid_date'] ) ? $r['withdraw_paid_date'] : $r['created'];

            fputcsv( $output, [
                '#W' . $w_id,
                $date_show,
                $vid,
                $v_name,
                number_format( (float) $r['withdraw_amount'], 2, '.', '' ),
                $status,
                $r['cashfree_transfer_id'] ?: '',
                $r['cashfree_utr'] ?: '',
                $r['cashfree_transfer_mode'] ?: ( 'upi' === $r['payment_method'] ? 'upi' : 'banktransfer' ),
                $r['order_ids'],
                $r['withdraw_mode'],
            ] );
        }

        fclose( $output );
        exit;
    }

    // -------------------------------------------------------------------------
    // AJAX: Disburse Single Vendor (Instant 1-Click Pay)
    // -------------------------------------------------------------------------

    public static function ajax_disburse_vendor_instant(): void {
        check_ajax_referer( 'thaaniyamhub_payout_approval_action', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'thaaniyamhub-multi-vendor-orders' ) ] );
        }

        $vendor_id = absint( $_POST['vendor_id'] ?? 0 );
        if ( ! $vendor_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid vendor ID.', 'thaaniyamhub-multi-vendor-orders' ) ] );
        }

        $result = ThaaniyamHub_Payout_Scheduler::disburse_single_vendor( $vendor_id, true, 'manual' );
        self::refresh_pending_count();

        if ( ! $result['success'] ) {
            wp_send_json_error( [ 'message' => $result['error'] ?? __( 'Payout initiation failed.', 'thaaniyamhub-multi-vendor-orders' ) ] );
        }

        wp_send_json_success( [
            'message'         => sprintf(
                __( 'Disbursed ₹%s to Vendor #%d via Cashfree (Transfer ID: %s)', 'thaaniyamhub-multi-vendor-orders' ),
                number_format( (float) ( $result['amount'] ?? 0 ), 2 ),
                $vendor_id,
                $result['transfer_id'] ?? '-'
            ),
            'transfer_id'     => $result['transfer_id'] ?? '',
            'cashfree_status' => $result['status'] ?? 'SUCCESS',
            'utr'             => $result['utr'] ?? '',
            'amount'          => $result['amount'] ?? 0,
        ] );
    }

    // -------------------------------------------------------------------------
    // AJAX: Disburse All Ready Payouts (Bulk)
    // -------------------------------------------------------------------------

    public static function ajax_disburse_all_ready(): void {
        check_ajax_referer( 'thaaniyamhub_payout_approval_action', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'thaaniyamhub-multi-vendor-orders' ) ] );
        }

        $result = ThaaniyamHub_Payout_Scheduler::execute_batch_payouts( 'manual', true );
        self::refresh_pending_count();

        if ( ! $result['success'] ) {
            wp_send_json_error( [ 'message' => $result['message'] ?? __( 'Batch payout execution failed.', 'thaaniyamhub-multi-vendor-orders' ) ] );
        }

        wp_send_json_success( $result );
    }

    // -------------------------------------------------------------------------
    // AJAX: Approve Ceiling Queue Entry
    // -------------------------------------------------------------------------

    public static function ajax_approve(): void {
        check_ajax_referer( 'thaaniyamhub_payout_approval_action', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'thaaniyamhub-multi-vendor-orders' ) ] );
        }

        $queue_id   = absint( $_POST['queue_id'] ?? 0 );
        $admin_note = sanitize_textarea_field( $_POST['admin_note'] ?? '' );

        if ( ! $queue_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid queue ID.', 'thaaniyamhub-multi-vendor-orders' ) ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $queue_id ), ARRAY_A );

        if ( ! $row ) {
            wp_send_json_error( [ 'message' => __( 'Queue entry not found.', 'thaaniyamhub-multi-vendor-orders' ) ] );
        }
        if ( 'pending' !== $row['status'] ) {
            wp_send_json_error( [
                'message' => sprintf( __( 'This entry has already been %s.', 'thaaniyamhub-multi-vendor-orders' ), $row['status'] ),
            ] );
        }

        $vendor_id      = (int)   $row['vendor_id'];
        $amount         = (float) $row['amount'];
        $commission_ids = json_decode( $row['commission_ids'], true ) ?: [];
        $order_ids      = json_decode( $row['order_ids'],      true ) ?: [];
        $payout_profile = json_decode( $row['payout_profile'], true ) ?: [];
        $source         = $row['source'];

        $result = self::execute_approved_payout( $vendor_id, $amount, $commission_ids, $order_ids, $payout_profile, $source );

        if ( ! $result['success'] ) {
            $wpdb->update(
                $table,
                [
                    'admin_note' => 'Approve failed: ' . ( $result['error'] ?? '' ),
                    'updated_at' => current_time( 'mysql' ),
                ],
                [ 'id' => $queue_id ],
                [ '%s', '%s' ],
                [ '%d' ]
            );
            wp_send_json_error( [ 'message' => $result['error'] ?? __( 'Payout failed. Check logs.', 'thaaniyamhub-multi-vendor-orders' ) ] );
        }

        $wpdb->update(
            $table,
            [
                'status'          => 'approved',
                'admin_note'      => $admin_note,
                'transfer_id'     => $result['transfer_id']     ?? '',
                'cashfree_status' => $result['cashfree_status'] ?? '',
                'utr'             => $result['utr']             ?? '',
                'actioned_by'     => get_current_user_id(),
                'actioned_at'     => current_time( 'mysql' ),
                'updated_at'      => current_time( 'mysql' ),
            ],
            [ 'id' => $queue_id ],
            [ '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ],
            [ '%d' ]
        );

        self::refresh_pending_count();

        wp_send_json_success( [
            'message'         => sprintf(
                __( 'Payout approved and transfer initiated (Transfer ID: %s)', 'thaaniyamhub-multi-vendor-orders' ),
                $result['transfer_id'] ?? '-'
            ),
            'transfer_id'     => $result['transfer_id']     ?? '',
            'cashfree_status' => $result['cashfree_status'] ?? '',
            'utr'             => $result['utr']             ?? '',
            'pending_count'   => (int) get_option( self::PENDING_COUNT_OPTION, 0 ),
        ] );
    }

    // -------------------------------------------------------------------------
    // AJAX: Reject Ceiling Queue Entry
    // -------------------------------------------------------------------------

    public static function ajax_reject(): void {
        check_ajax_referer( 'thaaniyamhub_payout_approval_action', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'thaaniyamhub-multi-vendor-orders' ) ] );
        }

        $queue_id   = absint( $_POST['queue_id'] ?? 0 );
        $admin_note = sanitize_textarea_field( $_POST['admin_note'] ?? '' );

        if ( ! $queue_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid queue ID.', 'thaaniyamhub-multi-vendor-orders' ) ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $queue_id ), ARRAY_A );

        if ( ! $row ) {
            wp_send_json_error( [ 'message' => __( 'Queue entry not found.', 'thaaniyamhub-multi-vendor-orders' ) ] );
        }
        if ( 'pending' !== $row['status'] ) {
            wp_send_json_error( [
                'message' => sprintf( __( 'This entry has already been %s.', 'thaaniyamhub-multi-vendor-orders' ), $row['status'] ),
            ] );
        }

        $wpdb->update(
            $table,
            [
                'status'      => 'rejected',
                'admin_note'  => $admin_note ?: __( 'Rejected by administrator.', 'thaaniyamhub-multi-vendor-orders' ),
                'actioned_by' => get_current_user_id(),
                'actioned_at' => current_time( 'mysql' ),
                'updated_at'  => current_time( 'mysql' ),
            ],
            [ 'id' => $queue_id ],
            [ '%s', '%s', '%d', '%s', '%s' ],
            [ '%d' ]
        );

        self::refresh_pending_count();

        wp_send_json_success( [
            'message'       => __( 'Payout rejected. No transfer was made.', 'thaaniyamhub-multi-vendor-orders' ),
            'pending_count' => (int) get_option( self::PENDING_COUNT_OPTION, 0 ),
        ] );
    }

    // -------------------------------------------------------------------------
    // Core: Execute an approved payout (ceiling bypass)
    // -------------------------------------------------------------------------

    private static function execute_approved_payout(
        int    $vendor_id,
        float  $amount,
        array  $commission_ids,
        array  $order_ids,
        array  $payout_profile,
        string $source = 'admin_approval'
    ): array {
        global $wpdb, $WCFMmp;

        $order_ids      = array_values( array_filter( array_unique( array_map( 'intval', $order_ids ) ), fn( $id ) => $id > 0 ) );
        $commission_ids = array_values( array_filter( array_unique( array_map( 'intval', $commission_ids ) ), fn( $id ) => $id > 0 ) );

        if ( empty( $commission_ids ) ) {
            return [ 'success' => false, 'error' => __( 'Empty commission IDs. Cannot process.', 'thaaniyamhub-multi-vendor-orders' ) ];
        }

        $comm_placeholders = implode( ',', array_fill( 0, count( $commission_ids ), '%d' ) );

        // --- Lock commissions ---
        $locked = $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->prefix}wcfm_marketplace_orders
             SET withdraw_status = 'requested'
             WHERE ID IN ({$comm_placeholders}) AND withdraw_status = 'pending'",
            ...$commission_ids
        ) );

        if ( $locked <= 0 ) {
            return [ 'success' => false, 'error' => __( 'Commissions could not be locked (may already be claimed).', 'thaaniyamhub-multi-vendor-orders' ) ];
        }

        // --- Lock ledger rows ---
        if ( ! empty( $order_ids ) ) {
            $order_placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->prefix}thaaniyamhub_vendor_ledger
                 SET payout_status = 'processing'
                 WHERE vendor_id = %d AND order_id IN ({$order_placeholders}) AND payout_status = 'pending'",
                $vendor_id,
                ...$order_ids
            ) );
        }

        // --- Create WCFM withdrawal record ---
        $order_ids_str      = implode( ',', $order_ids );
        $commission_ids_str = implode( ',', $commission_ids );

        $insert_res = $wpdb->insert(
            "{$wpdb->prefix}wcfm_marketplace_withdraw_request",
            [
                'vendor_id'          => $vendor_id,
                'order_ids'          => $order_ids_str,
                'commission_ids'     => $commission_ids_str,
                'payment_method'     => 'cashfree',
                'withdraw_amount'    => $amount,
                'withdraw_charges'   => 0.00,
                'withdraw_status'    => 'requested',
                'withdraw_mode'      => 'by_admin_approval',
                'is_auto_withdrawal' => 1,
                'created'            => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%d', '%s' ]
        );

        if ( false === $insert_res ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->prefix}wcfm_marketplace_orders SET withdraw_status = 'pending' WHERE ID IN ({$comm_placeholders})",
                ...$commission_ids
            ) );
            return [ 'success' => false, 'error' => 'DB error creating withdrawal: ' . $wpdb->last_error ];
        }

        $withdrawal_id   = (int) $wpdb->insert_id;
        $transfer_id     = sprintf( 'TH_APPR_V%d_W%d_%d', $vendor_id, $withdrawal_id, time() );
        $order_ids_clean = str_replace( ',', ' ', $order_ids_str );
        $remarks         = sprintf( 'ThaaniyamHub Admin Approved Payout %d Orders %s', $withdrawal_id, $order_ids_clean );

        $transfer_data = [
            'amount'     => $amount,
            'transferId' => $transfer_id,
            'name'       => $payout_profile['account_name'] ?: ( 'Vendor ' . $vendor_id ),
            'email'      => $payout_profile['email']        ?: 'vendor@thaaniyamhub.com',
            'phone'      => $payout_profile['phone']        ?: '9999999999',
            'remarks'    => substr( $remarks, 0, 70 ),
        ];

        if ( 'upi' === ( $payout_profile['payout_type'] ?? '' ) && ! empty( $payout_profile['upi_id'] ) ) {
            $transfer_data['vpa']          = $payout_profile['upi_id'];
            $transfer_data['transferMode'] = 'upi';
        } else {
            $transfer_data['bankAccount']  = $payout_profile['account_number'] ?? '';
            $transfer_data['ifsc']         = $payout_profile['ifsc']           ?? '';
            $transfer_data['transferMode'] = get_option( 'thaaniyamhub_cashfree_payout_transfer_mode', 'banktransfer' );
        }

        // --- Call Cashfree ---
        $api      = ThaaniyamHub_Cashfree_Payout_API::get_instance();
        $response = $api->direct_transfer( $transfer_data );

        if ( is_wp_error( $response ) ) {
            $err_msg = $response->get_error_message();

            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->prefix}wcfm_marketplace_orders SET withdraw_status = 'pending' WHERE ID IN ({$comm_placeholders})",
                ...$commission_ids
            ) );
            if ( ! empty( $order_ids ) ) {
                $op = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}thaaniyamhub_vendor_ledger
                     SET payout_status = 'failed'
                     WHERE vendor_id = %d AND order_id IN ({$op})",
                    $vendor_id, ...$order_ids
                ) );
            }
            $wpdb->update(
                "{$wpdb->prefix}wcfm_marketplace_withdraw_request",
                [ 'withdraw_status' => 'cancelled', 'withdraw_note' => 'Cashfree Error: ' . $err_msg ],
                [ 'ID' => $withdrawal_id ]
            );

            return [ 'success' => false, 'error' => $err_msg ];
        }

        $cf_status    = $response['transfer_status'] ?? 'PENDING';
        $reference_id = $response['referenceId'] ?? '';
        $utr          = $response['utr'] ?? '';

        // --- Update WCFM metadata ---
        if ( isset( $WCFMmp->wcfmmp_withdraw ) && method_exists( $WCFMmp->wcfmmp_withdraw, 'wcfmmp_update_withdrawal_meta' ) ) {
            $WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $withdrawal_id, 'withdraw_amount',        $amount );
            $WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $withdrawal_id, 'currency',               get_woocommerce_currency() );
            $WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $withdrawal_id, 'cashfree_transfer_id',   $transfer_id );
            $WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $withdrawal_id, 'cashfree_reference_id',  $reference_id );
            $WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $withdrawal_id, 'cashfree_utr',           $utr );
            $WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $withdrawal_id, 'cashfree_status',        $cf_status );
            $WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $withdrawal_id, 'cashfree_transfer_mode', $transfer_data['transferMode'] ?? '' );
            $WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $withdrawal_id, 'admin_approved',         '1' );
        }

        // --- Finalize statuses on immediate SUCCESS ---
        if ( 'SUCCESS' === $cf_status ) {
            $note = sprintf(
                __( 'Admin-Approved Payout Completed. Transfer ID: %s | UTR: %s', 'thaaniyamhub-multi-vendor-orders' ),
                $transfer_id, $utr
            );
            if ( isset( $WCFMmp->wcfmmp_withdraw ) && method_exists( $WCFMmp->wcfmmp_withdraw, 'wcfmmp_withdraw_status_update_by_withdrawal' ) ) {
                $WCFMmp->wcfmmp_withdraw->wcfmmp_withdraw_status_update_by_withdrawal( $withdrawal_id, 'completed', $note );
            } else {
                $wpdb->update(
                    "{$wpdb->prefix}wcfm_marketplace_withdraw_request",
                    [ 'withdraw_status' => 'completed', 'withdraw_note' => $note, 'withdraw_paid_date' => current_time( 'mysql' ) ],
                    [ 'ID' => $withdrawal_id ]
                );
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}wcfm_marketplace_orders
                     SET withdraw_status = 'completed', commission_paid_date = %s
                     WHERE ID IN ({$comm_placeholders})",
                    current_time( 'mysql' ),
                    ...$commission_ids
                ) );
            }
            if ( ! empty( $order_ids ) ) {
                $op = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}thaaniyamhub_vendor_ledger
                     SET payout_status = 'disbursed'
                     WHERE vendor_id = %d AND order_id IN ({$op})",
                    $vendor_id, ...$order_ids
                ) );
            }
        }

        return [
            'success'        => true,
            'transfer_id'    => $transfer_id,
            'cashfree_status'=> $cf_status,
            'utr'            => $utr,
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers & History Queries
    // -------------------------------------------------------------------------

    /**
     * Query all processed disbursements from wcfm_marketplace_withdraw_request.
     *
     * @param array $args
     * @return array
     */
    /**
     * Helper to build subqueries for the latest withdrawal metadata row per key.
     * Prevents duplicate rows caused by multiple meta entries per withdrawal ID.
     */
    private static function get_meta_subqueries_sql(): string {
        global $wpdb;
        $meta_tbl = "{$wpdb->prefix}wcfm_marketplace_withdraw_request_meta";

        return "
            LEFT JOIN (
                SELECT m1.withdraw_id, m1.value
                FROM {$meta_tbl} m1
                INNER JOIN (
                    SELECT MAX(ID) AS max_id
                    FROM {$meta_tbl}
                    WHERE `key` = 'cashfree_transfer_id'
                    GROUP BY withdraw_id
                ) m2 ON m1.ID = m2.max_id
            ) meta_tid ON meta_tid.withdraw_id = w.ID

            LEFT JOIN (
                SELECT m1.withdraw_id, m1.value
                FROM {$meta_tbl} m1
                INNER JOIN (
                    SELECT MAX(ID) AS max_id
                    FROM {$meta_tbl}
                    WHERE `key` = 'cashfree_status'
                    GROUP BY withdraw_id
                ) m2 ON m1.ID = m2.max_id
            ) meta_status ON meta_status.withdraw_id = w.ID

            LEFT JOIN (
                SELECT m1.withdraw_id, m1.value
                FROM {$meta_tbl} m1
                INNER JOIN (
                    SELECT MAX(ID) AS max_id
                    FROM {$meta_tbl}
                    WHERE `key` = 'cashfree_utr'
                    GROUP BY withdraw_id
                ) m2 ON m1.ID = m2.max_id
            ) meta_utr ON meta_utr.withdraw_id = w.ID

            LEFT JOIN (
                SELECT m1.withdraw_id, m1.value
                FROM {$meta_tbl} m1
                INNER JOIN (
                    SELECT MAX(ID) AS max_id
                    FROM {$meta_tbl}
                    WHERE `key` = 'cashfree_transfer_mode'
                    GROUP BY withdraw_id
                ) m2 ON m1.ID = m2.max_id
            ) meta_mode ON meta_mode.withdraw_id = w.ID
        ";
    }

    /**
     * Shared WHERE builder for historical payout queries.
     *
     * @param array $args
     * @return array [ string $where_sql, array $params ]
     */
    private static function build_history_where_clause( array $args ): array {
        global $wpdb;

        $status     = sanitize_text_field( $args['status'] ?? '' );
        $vendor_id  = absint( $args['vendor_id'] ?? 0 );
        $search     = sanitize_text_field( $args['search'] ?? '' );
        $date_from  = sanitize_text_field( $args['date_from'] ?? '' );
        $date_to    = sanitize_text_field( $args['date_to'] ?? '' );
        $rail       = sanitize_text_field( $args['rail'] ?? 'all' );
        $min_amount = ! empty( $args['min_amount'] ) ? (float) $args['min_amount'] : 0.0;
        $max_amount = ! empty( $args['max_amount'] ) ? (float) $args['max_amount'] : 0.0;

        $where  = [ '1=1' ];
        $params = [];

        // 1. Status Filter (Strict, mutually exclusive)
        if ( ! empty( $status ) && 'all' !== $status ) {
            if ( 'completed' === $status || 'success' === $status ) {
                $where[] = "( (w.withdraw_status = 'completed' OR UPPER(COALESCE(meta_status.value, '')) = 'SUCCESS') AND w.withdraw_status != 'cancelled' )";
            } elseif ( 'pending' === $status || 'requested' === $status ) {
                $where[] = "( (w.withdraw_status = 'requested' OR UPPER(COALESCE(meta_status.value, '')) IN ('PENDING', 'PENDING_VERIFICATION', 'PROCESSING', 'RECEIVED')) AND w.withdraw_status != 'completed' AND UPPER(COALESCE(meta_status.value, '')) != 'SUCCESS' AND w.withdraw_status != 'cancelled' )";
            } elseif ( 'failed' === $status || 'cancelled' === $status ) {
                $where[] = "( w.withdraw_status = 'cancelled' OR UPPER(COALESCE(meta_status.value, '')) IN ('FAILED', 'REVERSED') )";
            }
        }

        // 2. Vendor Filter
        if ( $vendor_id > 0 ) {
            $where[]  = 'w.vendor_id = %d';
            $params[] = $vendor_id;
        }

        // 3. Date Range Filter
        if ( ! empty( $date_from ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
            $where[]  = "COALESCE(NULLIF(w.withdraw_paid_date, ''), w.created) >= %s";
            $params[] = $date_from . ' 00:00:00';
        }
        if ( ! empty( $date_to ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
            $where[]  = "COALESCE(NULLIF(w.withdraw_paid_date, ''), w.created) <= %s";
            $params[] = $date_to . ' 23:59:59';
        }

        // 4. Transfer Rail Filter
        if ( 'banktransfer' === $rail ) {
            $where[] = "( meta_mode.value IN ('banktransfer', 'bank_transfer', 'imps', 'neft', 'rtgs') OR (w.payment_method IN ('cashfree', 'bank_transfer') AND (meta_mode.value IS NULL OR meta_mode.value != 'upi')) )";
        } elseif ( 'upi' === $rail ) {
            $where[] = "( meta_mode.value = 'upi' OR w.payment_method = 'upi' )";
        }

        // 5. Amount Filter
        if ( $min_amount > 0 ) {
            $where[]  = 'w.withdraw_amount >= %f';
            $params[] = $min_amount;
        }
        if ( $max_amount > 0 ) {
            $where[]  = 'w.withdraw_amount <= %f';
            $params[] = $max_amount;
        }

        // 6. Search Filter
        if ( ! empty( $search ) ) {
            $where[]     = "( w.ID = %d OR w.order_ids LIKE %s OR meta_tid.value LIKE %s OR meta_utr.value LIKE %s OR u.display_name LIKE %s )";
            $search_wild = '%' . $wpdb->esc_like( $search ) . '%';
            $params[]    = absint( $search );
            $params[]    = $search_wild;
            $params[]    = $search_wild;
            $params[]    = $search_wild;
            $params[]    = $search_wild;
        }

        return [ implode( ' AND ', $where ), $params ];
    }

    /**
     * Query all processed disbursements from wcfm_marketplace_withdraw_request.
     *
     * @param array $args
     * @return array
     */
    public static function get_payout_history( array $args = [] ): array {
        global $wpdb;

        $paged    = max( 1, absint( $args['paged'] ?? 1 ) );
        $per_page = max( 10, min( 5000, absint( $args['per_page'] ?? 25 ) ) );
        $offset   = ( $paged - 1 ) * $per_page;
        $orderby  = sanitize_text_field( $args['orderby'] ?? 'date_desc' );

        list( $where_sql, $params ) = self::build_history_where_clause( $args );
        $meta_joins                 = self::get_meta_subqueries_sql();

        $count_sql = "SELECT COUNT(DISTINCT w.ID)
                      FROM {$wpdb->prefix}wcfm_marketplace_withdraw_request w
                      LEFT JOIN {$wpdb->users} u ON u.ID = w.vendor_id
                      {$meta_joins}
                      WHERE {$where_sql}";

        $total_count = ! empty( $params )
            ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) )
            : (int) $wpdb->get_var( $count_sql );

        $order_sql = 'w.ID DESC';
        switch ( $orderby ) {
            case 'date_asc':
                $order_sql = 'w.ID ASC';
                break;
            case 'amount_desc':
                $order_sql = 'w.withdraw_amount DESC, w.ID DESC';
                break;
            case 'amount_asc':
                $order_sql = 'w.withdraw_amount ASC, w.ID DESC';
                break;
            case 'vendor_asc':
                $order_sql = 'u.display_name ASC, w.ID DESC';
                break;
            case 'date_desc':
            default:
                $order_sql = 'w.ID DESC';
                break;
        }

        $query_sql = "SELECT w.*,
                             u.display_name AS vendor_name,
                             meta_tid.value AS cashfree_transfer_id,
                             meta_status.value AS cashfree_status,
                             meta_utr.value AS cashfree_utr,
                             meta_mode.value AS cashfree_transfer_mode
                      FROM {$wpdb->prefix}wcfm_marketplace_withdraw_request w
                      LEFT JOIN {$wpdb->users} u ON u.ID = w.vendor_id
                      {$meta_joins}
                      WHERE {$where_sql}
                      GROUP BY w.ID
                      ORDER BY {$order_sql}
                      LIMIT %d OFFSET %d";

        $query_params = array_merge( $params, [ $per_page, $offset ] );
        $rows = $wpdb->get_results( $wpdb->prepare( $query_sql, ...$query_params ), ARRAY_A );

        return [
            'rows'        => $rows ?: [],
            'total_count' => $total_count,
            'paged'       => $paged,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil( $total_count / $per_page ),
        ];
    }

    /**
     * Statistics for processed disbursements (supports all active filter criteria).
     *
     * @param array $args
     * @return array
     */
    public static function get_history_stats( array $args = [] ): array {
        global $wpdb;

        list( $where_sql, $params ) = self::build_history_where_clause( $args );
        $meta_joins                 = self::get_meta_subqueries_sql();

        $sql = "SELECT 
                    COUNT(DISTINCT w.ID) as total_count,
                    COALESCE(SUM(CASE WHEN (w.withdraw_status = 'completed' OR UPPER(COALESCE(meta_status.value, '')) = 'SUCCESS') AND w.withdraw_status != 'cancelled' THEN w.withdraw_amount ELSE 0 END), 0) as total_disbursed,
                    COUNT(DISTINCT CASE WHEN (w.withdraw_status = 'completed' OR UPPER(COALESCE(meta_status.value, '')) = 'SUCCESS') AND w.withdraw_status != 'cancelled' THEN w.ID END) as completed_count,
                    COUNT(DISTINCT CASE WHEN (w.withdraw_status = 'requested' OR UPPER(COALESCE(meta_status.value, '')) IN ('PENDING', 'PENDING_VERIFICATION', 'PROCESSING', 'RECEIVED')) AND w.withdraw_status != 'completed' AND UPPER(COALESCE(meta_status.value, '')) != 'SUCCESS' AND w.withdraw_status != 'cancelled' THEN w.ID END) as pending_count,
                    COUNT(DISTINCT CASE WHEN w.withdraw_status = 'cancelled' OR UPPER(COALESCE(meta_status.value, '')) IN ('FAILED', 'REVERSED') THEN w.ID END) as failed_count
                FROM {$wpdb->prefix}wcfm_marketplace_withdraw_request w
                LEFT JOIN {$wpdb->users} u ON u.ID = w.vendor_id
                {$meta_joins}
                WHERE {$where_sql}";

        $row = ! empty( $params )
            ? $wpdb->get_row( $wpdb->prepare( $sql, ...$params ), ARRAY_A )
            : $wpdb->get_row( $sql, ARRAY_A );

        return $row ?: [
            'total_count'     => 0,
            'total_disbursed' => 0.0,
            'completed_count' => 0,
            'pending_count'   => 0,
            'failed_count'    => 0,
        ];
    }

    /**
     * Retrieve all vendors for dropdown filters (WCFM + all past disbursed vendors).
     */
    public static function get_all_vendors(): array {
        global $wpdb;
        $list = [];

        // 1. Try WCFM helper
        if ( function_exists( 'wcfm_get_vendor_list' ) ) {
            $wcfm_list = wcfm_get_vendor_list();
            if ( ! empty( $wcfm_list ) && is_array( $wcfm_list ) ) {
                foreach ( $wcfm_list as $id => $name ) {
                    $list[ (int) $id ] = $name ?: ( 'Vendor #' . $id );
                }
            }
        }

        // 2. Query distinct vendors from withdrawal table to ensure historical completeness
        $historical_vendor_ids = $wpdb->get_col(
            "SELECT DISTINCT vendor_id FROM {$wpdb->prefix}wcfm_marketplace_withdraw_request WHERE vendor_id > 0"
        );
        if ( ! empty( $historical_vendor_ids ) ) {
            foreach ( $historical_vendor_ids as $hid ) {
                $hid = (int) $hid;
                if ( ! isset( $list[ $hid ] ) ) {
                    $u = get_userdata( $hid );
                    $list[ $hid ] = $u ? $u->display_name : ( 'Vendor #' . $hid );
                }
            }
        }

        // 3. Fallback to WP users with vendor roles if still empty
        if ( empty( $list ) ) {
            $users = get_users( [ 'role__in' => [ 'wcfm_vendor', 'seller', 'administrator' ], 'fields' => [ 'ID', 'display_name' ] ] );
            foreach ( $users as $u ) {
                $list[ (int) $u->ID ] = $u->display_name;
            }
        }

        asort( $list, SORT_NATURAL | SORT_FLAG_CASE );
        return $list;
    }

    /**
     * Recount and cache the pending ceiling queue size.
     */
    public static function refresh_pending_count(): void {
        global $wpdb;
        $count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}" . self::TABLE . " WHERE status = 'pending'"
        );
        update_option( self::PENDING_COUNT_OPTION, $count, 'no' );
    }
}
