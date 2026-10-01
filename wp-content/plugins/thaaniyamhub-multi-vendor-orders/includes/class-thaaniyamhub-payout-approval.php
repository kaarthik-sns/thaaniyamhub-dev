<?php
/**
 * Thaaniyam Hub Marketplace — Payout Approval Queue
 *
 * Handles the full lifecycle of payouts that exceed the configured
 * Payout Ceiling Limit and therefore require manual administrator review.
 *
 * Flow:
 *   1. Payout blocked by ceiling check  ->  enqueue_pending() called
 *   2. Admin sees badge + notice in WP Admin
 *   3. Admin visits Payout Approvals screen  ->  reviews each row
 *   4. Admin clicks Approve -> execute_approved_payout() fires the real transfer
 *   5. Admin clicks Reject  -> row marked rejected, no transfer made
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
        // Admin UI
        add_action( 'admin_menu',    [ __CLASS__, 'register_admin_menu' ] );
        add_action( 'admin_notices', [ __CLASS__, 'maybe_show_pending_notice' ] );

        // AJAX handlers
        add_action( 'wp_ajax_thaaniyamhub_approve_payout', [ __CLASS__, 'ajax_approve' ] );
        add_action( 'wp_ajax_thaaniyamhub_reject_payout',  [ __CLASS__, 'ajax_reject' ] );

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
     * Called from the ceiling-check code in the payout scheduler / gateway class
     * instead of simply logging and continuing.
     *
     * @param int    $vendor_id
     * @param float  $amount          Total blocked payout amount
     * @param float  $ceiling_limit   The ceiling that was exceeded
     * @param array  $commission_ids  WCFM commission IDs
     * @param array  $order_ids       WooCommerce order IDs
     * @param array  $payout_profile  Vendor bank/UPI profile array
     * @param string $source          'cron' | 'manual' | 'wcfm_withdrawal'
     * @return int|false  Inserted row ID, or false on failure
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
            __( 'Payout Approvals -- ThaaniyamHub', 'thaaniyamhub-multi-vendor-orders' ),
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
     * Show a yellow admin notice on every WP admin page when there are pending payouts.
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
            return; // Don't double-show on the approval page itself
        }

        $url = esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
        printf(
            '<div class="notice notice-warning"><p>&#9888;&#65039; <strong>%s</strong> &mdash; <a href="%s">%s</a></p></div>',
            sprintf(
                esc_html( _n(
                    '%d vendor payout is waiting for your manual approval (amount exceeded the ceiling limit).',
                    '%d vendor payouts are waiting for your manual approval (amounts exceeded the ceiling limit).',
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
    // Page assets
    // -------------------------------------------------------------------------

    public static function enqueue_assets( string $hook ): void {
        if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
            return;
        }

        $css = '
            .th-approval-wrap { max-width: 1200px; }
            .th-approval-wrap h1 { display: flex; align-items: center; gap: 12px; }
            .th-badge { background: #d63638; color: #fff; border-radius: 12px;
                        font-size: 12px; padding: 2px 8px; font-weight: 700; }
            .th-table { width: 100%; border-collapse: collapse; margin-top: 16px; }
            .th-table th { background: #1d2327; color: #fff; padding: 10px 14px; text-align: left; }
            .th-table td { padding: 10px 14px; border-bottom: 1px solid #e0e0e0; vertical-align: top; }
            .th-table tr:hover td { background: #f8f9fa; }
            .th-status-pending  { color: #996800; font-weight: 600; }
            .th-status-approved { color: #008a20; font-weight: 600; }
            .th-status-rejected { color: #d63638; font-weight: 600; }
            .th-amount  { font-size: 15px; font-weight: 700; }
            .th-ceiling { font-size: 12px; color: #777; }
            .th-btn { display: inline-block; padding: 5px 14px; border-radius: 4px;
                      font-size: 13px; cursor: pointer; border: none; font-weight: 600; }
            .th-btn-approve { background: #008a20; color: #fff; }
            .th-btn-approve:hover { background: #006816; }
            .th-btn-reject  { background: #d63638; color: #fff; margin-left: 6px; }
            .th-btn-reject:hover  { background: #a02020; }
            .th-btn:disabled { opacity: .5; cursor: not-allowed; }
            .th-note-input { width: 200px; padding: 4px 8px; border: 1px solid #ccc;
                             border-radius: 3px; font-size: 12px; }
            .th-empty { padding: 40px; text-align: center; color: #666; font-size: 15px; }
            .th-filters { margin-bottom: 12px; display: flex; gap: 10px; align-items: center; }
            .th-filters select { padding: 5px 8px; border-radius: 3px; border: 1px solid #ccc; }
        ';
        wp_add_inline_style( 'wp-admin', $css );

        $js_data = wp_json_encode( [
            'nonce'      => wp_create_nonce( 'thaaniyamhub_payout_approval_action' ),
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'approveMsg' => __( 'Approving will send the real Cashfree transfer. Continue?', 'thaaniyamhub-multi-vendor-orders' ),
            'rejectMsg'  => __( 'Reject this payout? The vendor will NOT be paid.', 'thaaniyamhub-multi-vendor-orders' ),
        ] );
        wp_add_inline_script( 'jquery', "var thPayoutApproval = {$js_data};" );
    }

    // -------------------------------------------------------------------------
    // Admin Page Render
    // -------------------------------------------------------------------------

    public static function render_page(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'thaaniyamhub-multi-vendor-orders' ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $filter_status    = sanitize_text_field( $_GET['th_status'] ?? 'pending' );
        $allowed_statuses = [ 'all', 'pending', 'approved', 'rejected' ];
        if ( ! in_array( $filter_status, $allowed_statuses, true ) ) {
            $filter_status = 'pending';
        }

        if ( 'all' === $filter_status ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 200", ARRAY_A );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC LIMIT 200", $filter_status ), ARRAY_A );
        }

        $pending_total = (int) get_option( self::PENDING_COUNT_OPTION, 0 );
        ?>
        <div class="wrap th-approval-wrap">
            <h1>
                <?php esc_html_e( 'Payout Approvals', 'thaaniyamhub-multi-vendor-orders' ); ?>
                <?php if ( $pending_total > 0 ) : ?>
                    <span class="th-badge"><?php echo esc_html( $pending_total ); ?> <?php esc_html_e( 'Pending', 'thaaniyamhub-multi-vendor-orders' ); ?></span>
                <?php endif; ?>
            </h1>
            <p><?php esc_html_e( 'These payouts were blocked because their amount exceeded the Payout Ceiling Limit. Review and approve or reject each one.', 'thaaniyamhub-multi-vendor-orders' ); ?></p>

            <div class="th-filters">
                <label><?php esc_html_e( 'Filter:', 'thaaniyamhub-multi-vendor-orders' ); ?></label>
                <select onchange="window.location.href='?page=<?php echo esc_attr( self::PAGE_SLUG ); ?>&th_status='+this.value">
                    <?php foreach ( [ 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All' ] as $val => $lbl ) : ?>
                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $filter_status, $val ); ?>><?php echo esc_html( $lbl ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ( empty( $rows ) ) : ?>
                <div class="th-empty">&#10003; <?php esc_html_e( 'No payouts in this queue.', 'thaaniyamhub-multi-vendor-orders' ); ?></div>
            <?php else : ?>
            <table class="th-table widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ID', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                        <th><?php esc_html_e( 'Date', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                        <th><?php esc_html_e( 'Vendor', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                        <th><?php esc_html_e( 'Amount', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                        <th><?php esc_html_e( 'Orders / Commissions', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                        <th><?php esc_html_e( 'Source', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                        <th><?php esc_html_e( 'Cashfree Result', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                        <th><?php esc_html_e( 'Action', 'thaaniyamhub-multi-vendor-orders' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rows as $row ) :
                        $row_id     = (int) $row['id'];
                        $status     = $row['status'];
                        $is_pending = ( 'pending' === $status );
                        $order_ids  = json_decode( $row['order_ids'],      true ) ?: [];
                        $comm_ids   = json_decode( $row['commission_ids'], true ) ?: [];
                    ?>
                    <tr id="th-row-<?php echo esc_attr( $row_id ); ?>">
                        <td><strong>#<?php echo esc_html( $row_id ); ?></strong></td>
                        <td><?php echo esc_html( wp_date( 'd M Y H:i', strtotime( $row['created_at'] ) ) ); ?></td>
                        <td>
                            <a href="<?php echo esc_url( get_edit_user_link( (int) $row['vendor_id'] ) ); ?>" target="_blank">
                                <?php echo esc_html( $row['vendor_name'] ); ?>
                            </a><br>
                            <small style="color:#888">#<?php echo esc_html( $row['vendor_id'] ); ?></small>
                        </td>
                        <td>
                            <span class="th-amount">&#8377;<?php echo esc_html( number_format( (float) $row['amount'], 2 ) ); ?></span><br>
                            <span class="th-ceiling"><?php echo esc_html( sprintf( __( 'Ceiling: %s', 'thaaniyamhub-multi-vendor-orders' ), number_format( (float) $row['ceiling_limit'], 2 ) ) ); ?></span>
                        </td>
                        <td>
                            <?php if ( ! empty( $order_ids ) ) : ?>
                                <strong><?php esc_html_e( 'Orders:', 'thaaniyamhub-multi-vendor-orders' ); ?></strong>
                                <?php echo esc_html( implode( ', ', $order_ids ) ); ?><br>
                            <?php endif; ?>
                            <?php if ( ! empty( $comm_ids ) ) : ?>
                                <small><strong><?php esc_html_e( 'Comms:', 'thaaniyamhub-multi-vendor-orders' ); ?></strong>
                                <?php echo esc_html( implode( ', ', $comm_ids ) ); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $row['source'] ); ?></td>
                        <td>
                            <span class="th-status-<?php echo esc_attr( $status ); ?>"
                                  id="th-status-<?php echo esc_attr( $row_id ); ?>">
                                <?php echo esc_html( ucfirst( $status ) ); ?>
                            </span>
                            <?php if ( $row['actioned_at'] ) : ?>
                                <br><small style="color:#888"><?php echo esc_html( wp_date( 'd M Y H:i', strtotime( $row['actioned_at'] ) ) ); ?></small>
                            <?php endif; ?>
                        </td>
                        <td id="th-cf-result-<?php echo esc_attr( $row_id ); ?>">
                            <?php if ( ! empty( $row['transfer_id'] ) ) : ?>
                                <small>
                                    <strong>ID:</strong> <?php echo esc_html( $row['transfer_id'] ); ?><br>
                                    <strong>CF:</strong> <?php echo esc_html( $row['cashfree_status'] ); ?><br>
                                    <?php if ( ! empty( $row['utr'] ) ) : ?>
                                        <strong>UTR:</strong> <?php echo esc_html( $row['utr'] ); ?>
                                    <?php endif; ?>
                                </small>
                            <?php elseif ( ! empty( $row['admin_note'] ) ) : ?>
                                <small><?php echo esc_html( $row['admin_note'] ); ?></small>
                            <?php else : ?>
                                <span style="color:#bbb">&#8212;</span>
                            <?php endif; ?>
                        </td>
                        <td id="th-actions-<?php echo esc_attr( $row_id ); ?>">
                            <?php if ( $is_pending ) : ?>
                                <input type="text"
                                       class="th-note-input"
                                       id="th-note-<?php echo esc_attr( $row_id ); ?>"
                                       placeholder="<?php esc_attr_e( 'Note (optional)', 'thaaniyamhub-multi-vendor-orders' ); ?>"
                                /><br><br>
                                <button class="th-btn th-btn-approve"
                                        onclick="thApprovePayoutRow(<?php echo esc_attr( $row_id ); ?>)">
                                    &#10003; <?php esc_html_e( 'Approve &amp; Pay', 'thaaniyamhub-multi-vendor-orders' ); ?>
                                </button>
                                <button class="th-btn th-btn-reject"
                                        onclick="thRejectPayoutRow(<?php echo esc_attr( $row_id ); ?>)">
                                    &#10005; <?php esc_html_e( 'Reject', 'thaaniyamhub-multi-vendor-orders' ); ?>
                                </button>
                            <?php else :
                                $actioner = $row['actioned_by'] ? get_userdata( (int) $row['actioned_by'] ) : null;
                                if ( $actioner ) :
                            ?>
                                <span style="color:#777;font-size:12px">
                                    <?php echo esc_html( sprintf( __( 'By %s', 'thaaniyamhub-multi-vendor-orders' ), $actioner->display_name ) ); ?>
                                </span>
                            <?php endif; endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <script>
        function thApprovePayoutRow(id) {
            if (!confirm(thPayoutApproval.approveMsg)) return;
            thSendPayoutAction(id, 'approve');
        }
        function thRejectPayoutRow(id) {
            if (!confirm(thPayoutApproval.rejectMsg)) return;
            thSendPayoutAction(id, 'reject');
        }
        function thSendPayoutAction(id, action) {
            var note  = document.getElementById('th-note-' + id);
            var actEl = document.getElementById('th-actions-' + id);
            if (actEl) actEl.innerHTML = '<span style="color:#888">&#8987; Processing&hellip;</span>';

            var body = new FormData();
            body.append('action',     'thaaniyamhub_' + action + '_payout');
            body.append('nonce',      thPayoutApproval.nonce);
            body.append('queue_id',   id);
            body.append('admin_note', note ? note.value : '');

            fetch(thPayoutApproval.ajaxUrl, { method: 'POST', body: body })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var newStatus = ('approve' === action) ? 'approved' : 'rejected';
                    var statusEl  = document.getElementById('th-status-' + id);
                    var cfEl      = document.getElementById('th-cf-result-' + id);

                    if (statusEl) {
                        statusEl.className   = 'th-status-' + newStatus;
                        statusEl.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                    }
                    if (actEl) {
                        actEl.innerHTML = data.success
                            ? '<span style="color:#008a20;font-size:12px">' + (data.data && data.data.message ? data.data.message : 'Done') + '</span>'
                            : '<span style="color:#d63638;font-size:12px">&#9888; ' + (data.data && data.data.message ? data.data.message : 'Error') + '</span>';
                    }
                    if (cfEl && data.data && data.data.transfer_id) {
                        cfEl.innerHTML = '<small>'
                            + '<strong>ID:</strong> ' + data.data.transfer_id + '<br>'
                            + '<strong>CF:</strong> ' + (data.data.cashfree_status || '&#8212;')
                            + (data.data.utr ? '<br><strong>UTR:</strong> ' + data.data.utr : '')
                            + '</small>';
                    }
                    // Update badge count in sidebar
                    var badgeEls = document.querySelectorAll('#adminmenu .pending-count');
                    badgeEls.forEach(function(el) {
                        var cnt = data.data && typeof data.data.pending_count !== 'undefined' ? data.data.pending_count : 0;
                        el.textContent = cnt > 0 ? cnt : '';
                    });
                })
                .catch(function(err) {
                    if (actEl) actEl.innerHTML = '<span style="color:#d63638">&#9888; Request failed. See console.</span>';
                    console.error('TH Payout Approval error:', err);
                });
        }
        </script>
        <?php
    }

    // -------------------------------------------------------------------------
    // AJAX: Approve
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

        thaaniyamhub_log(
            sprintf(
                'ThaaniyamHub_Payout_Approval: Queue #%d approved by Admin #%d. Vendor #%d Amount: %s Transfer: %s | CF: %s | UTR: %s',
                $queue_id, get_current_user_id(), $vendor_id,
                number_format( $amount, 2 ),
                $result['transfer_id']     ?? '-',
                $result['cashfree_status'] ?? '-',
                $result['utr']             ?? '-'
            ),
            'info',
            'thaaniyamhub-cashfree-payout'
        );

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
    // AJAX: Reject
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

        thaaniyamhub_log(
            sprintf(
                'ThaaniyamHub_Payout_Approval: Queue #%d rejected by Admin #%d. Vendor #%d Amount: %s Note: %s',
                $queue_id, get_current_user_id(),
                (int)   $row['vendor_id'],
                number_format( (float) $row['amount'], 2 ),
                $admin_note
            ),
            'info',
            'thaaniyamhub-cashfree-payout'
        );

        wp_send_json_success( [
            'message'       => __( 'Payout rejected. No transfer was made.', 'thaaniyamhub-multi-vendor-orders' ),
            'pending_count' => (int) get_option( self::PENDING_COUNT_OPTION, 0 ),
        ] );
    }

    // -------------------------------------------------------------------------
    // Core: Execute an approved payout (ceiling bypass)
    // -------------------------------------------------------------------------

    /**
     * Fire the actual Cashfree transfer for an admin-approved payout.
     *
     * Mirrors ThaaniyamHub_Payout_Scheduler::disburse_vendor_batch() but sets
     * the withdrawal mode to 'by_admin_approval' and skips the ceiling check.
     */
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

        $withdrawal_id = (int) $wpdb->insert_id;
        $transfer_id   = sprintf( 'TH_APPR_V%d_W%d_%d', $vendor_id, $withdrawal_id, time() );
        $remarks       = sprintf( 'ThaaniyamHub Admin-Approved Payout #%d (Orders: %s)', $withdrawal_id, $order_ids_str );

        $transfer_data = [
            'amount'     => $amount,
            'transferId' => $transfer_id,
            'name'       => $payout_profile['account_name'] ?: ( 'Vendor #' . $vendor_id ),
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

        thaaniyamhub_log(
            sprintf(
                'ThaaniyamHub_Payout_Approval: Admin-approved Cashfree transfer Vendor #%d [W#%d Amount: %s Mode: %s TxID: %s]',
                $vendor_id, $withdrawal_id, number_format( $amount, 2 ),
                $transfer_data['transferMode'] ?? '', $transfer_id
            ),
            'info',
            'thaaniyamhub-cashfree-payout'
        );

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
        // PENDING/RECEIVED: Webhook & poller will finalize

        return [
            'success'        => true,
            'transfer_id'    => $transfer_id,
            'cashfree_status'=> $cf_status,
            'utr'            => $utr,
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Recount and cache the pending queue size.
     */
    public static function refresh_pending_count(): void {
        global $wpdb;
        $count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}" . self::TABLE . " WHERE status = 'pending'"
        );
        update_option( self::PENDING_COUNT_OPTION, $count, 'no' );
    }
}
