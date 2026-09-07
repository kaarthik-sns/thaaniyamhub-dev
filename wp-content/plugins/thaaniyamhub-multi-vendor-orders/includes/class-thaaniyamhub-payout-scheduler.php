<?php
/**
 * Thaaniyam Hub Marketplace — Automated Scheduled Vendor Payouts Engine
 *
 * Implements scheduled automatic vendor commission settlement with:
 *   - Flexible schedule intervals (Daily, Weekly once, Monthly once).
 *   - Per-vendor aggregation (single bulk payout per vendor, sum of all mature orders).
 *   - Configurable order maturity delay (default 4 days after order completed).
 *   - Atomic locking and strict anti-double-payout safeguards.
 *   - Cashfree Payouts API integration (Bank Transfer IMPS/NEFT and UPI).
 *   - Manual on-demand test/execution trigger for Store Administrators.
 *
 * @package thaaniyamhub-multi-vendor-orders
 */

defined( 'ABSPATH' ) || exit;

class ThaaniyamHub_Payout_Scheduler {

    /**
     * Hook name for WP-Cron.
     */
    public const CRON_HOOK = 'thaaniyamhub_auto_vendor_payout_cron';

    /**
     * Option key for last run stats.
     */
    public const LAST_RUN_OPTION = 'thaaniyamhub_auto_payout_last_run_stats';

    /**
     * Initialize the scheduler hooks, cron actions, and AJAX listeners.
     */
    public static function init() {
        add_filter( 'cron_schedules', [ __CLASS__, 'add_cron_intervals' ] );
        add_action( self::CRON_HOOK, [ __CLASS__, 'run_scheduled_payouts' ] );
        add_action( 'wp_ajax_thaaniyamhub_trigger_manual_auto_payout', [ __CLASS__, 'ajax_trigger_manual_payout' ] );

        // Ensure schedule is in sync with settings
        add_action( 'admin_init', [ __CLASS__, 'maybe_sync_schedule' ] );
    }

    /**
     * Register custom cron intervals.
     *
     * @param array $schedules
     * @return array
     */
    public static function add_cron_intervals( $schedules ) {
        if ( ! isset( $schedules['weekly'] ) ) {
            $schedules['weekly'] = [
                'interval' => 7 * DAY_IN_SECONDS,
                'display'  => __( 'Once Weekly', 'thaaniyamhub-multi-vendor-orders' ),
            ];
        }
        if ( ! isset( $schedules['monthly'] ) ) {
            $schedules['monthly'] = [
                'interval' => 30 * DAY_IN_SECONDS,
                'display'  => __( 'Once Monthly (30 Days)', 'thaaniyamhub-multi-vendor-orders' ),
            ];
        }
        return $schedules;
    }

    /**
     * Check and sync schedule on admin load if needed.
     */
    public static function maybe_sync_schedule() {
        $enabled = get_option( 'thaaniyamhub_auto_payout_enabled', 'no' );
        $next_run = wp_next_scheduled( self::CRON_HOOK );

        if ( 'yes' === $enabled && ! $next_run ) {
            self::sync_schedule();
        } elseif ( 'yes' !== $enabled && $next_run ) {
            self::clear_schedule();
        }
    }

    /**
     * Synchronize and register the scheduled cron job according to admin settings.
     */
    public static function sync_schedule() {
        self::clear_schedule();

        $enabled = get_option( 'thaaniyamhub_auto_payout_enabled', 'no' );
        if ( 'yes' !== $enabled ) {
            thaaniyamhub_log( 'ThaaniyamHub_Payout_Scheduler: Automated payouts disabled. Cron schedule cleared.', 'info', 'thaaniyamhub-cashfree-payout' );
            return;
        }

        $schedule    = get_option( 'thaaniyamhub_auto_payout_schedule', 'weekly' );
        $time_str    = get_option( 'thaaniyamhub_auto_payout_time', '02:00' );
        $day_of_week = get_option( 'thaaniyamhub_auto_payout_day_of_week', 'monday' );

        $next_timestamp = self::calculate_next_run_timestamp( $schedule, $time_str, $day_of_week );
        $recurrence     = ( 'daily' === $schedule ) ? 'daily' : ( ( 'weekly' === $schedule ) ? 'weekly' : 'monthly' );

        wp_schedule_event( $next_timestamp, $recurrence, self::CRON_HOOK );

        thaaniyamhub_log(
            sprintf(
                'ThaaniyamHub_Payout_Scheduler: Schedule registered successfully. Mode: %s, Next Run: %s (UTC)',
                $schedule,
                gmdate( 'Y-m-d H:i:s', $next_timestamp )
            ),
            'info',
            'thaaniyamhub-cashfree-payout'
        );
    }

    /**
     * Clear any existing scheduled payout cron jobs.
     */
    public static function clear_schedule() {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        while ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
            $timestamp = wp_next_scheduled( self::CRON_HOOK );
        }
    }

    /**
     * Calculate next run timestamp in local site time converted to UTC timestamp.
     *
     * @param string $schedule 'daily', 'weekly', 'monthly'
     * @param string $time_str 'HH:MM' e.g. '02:00'
     * @param string $day_of_week 'monday', 'tuesday', etc.
     * @return int UTC timestamp
     */
    public static function calculate_next_run_timestamp( $schedule = 'weekly', $time_str = '02:00', $day_of_week = 'monday' ): int {
        $now = current_time( 'timestamp' );
        list( $hour, $minute ) = array_pad( explode( ':', $time_str ), 2, '00' );
        $hour   = (int) $hour;
        $minute = (int) $minute;

        $today_run = mktime( $hour, $minute, 0, (int) date( 'n', $now ), (int) date( 'j', $now ), (int) date( 'Y', $now ) );

        if ( 'daily' === $schedule ) {
            $target = ( $today_run > $now ) ? $today_run : ( $today_run + DAY_IN_SECONDS );
        } elseif ( 'monthly' === $schedule ) {
            $month_run = mktime( $hour, $minute, 0, (int) date( 'n', $now ), 1, (int) date( 'Y', $now ) );
            if ( $month_run > $now ) {
                $target = $month_run;
            } else {
                $target = mktime( $hour, $minute, 0, (int) date( 'n', $now ) + 1, 1, (int) date( 'Y', $now ) );
            }
        } else {
            // Weekly (default)
            $target_day_num  = self::get_day_number( $day_of_week );
            $current_day_num = (int) date( 'N', $now );
            $days_diff       = ( $target_day_num - $current_day_num + 7 ) % 7;

            $target = $today_run + ( $days_diff * DAY_IN_SECONDS );
            if ( 0 === $days_diff && $today_run <= $now ) {
                $target += 7 * DAY_IN_SECONDS;
            }
        }

        // Convert local time timestamp to UTC timestamp for wp_schedule_event
        $time_diff = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
        return $target - $time_diff;
    }

    /**
     * Map day name to ISO-8601 day number (1 for Monday through 7 for Sunday).
     *
     * @param string $day_name
     * @return int
     */
    private static function get_day_number( string $day_name ): int {
        $map = [
            'monday'    => 1,
            'tuesday'   => 2,
            'wednesday' => 3,
            'thursday'  => 4,
            'friday'    => 5,
            'saturday'  => 6,
            'sunday'    => 7,
        ];
        return $map[ strtolower( trim( $day_name ) ) ] ?? 1;
    }

    /**
     * Get human-readable description of next scheduled run time.
     *
     * @return string
     */
    public static function get_next_scheduled_display(): string {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( ! $timestamp ) {
            return __( 'Not scheduled (Feature disabled)', 'thaaniyamhub-multi-vendor-orders' );
        }
        $time_diff = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
        $local_time = $timestamp + $time_diff;
        return date_i18n( 'Y-m-d H:i:s (l)', $local_time );
    }

    /**
     * Cron entry point.
     */
    public static function run_scheduled_payouts() {
        $enabled = get_option( 'thaaniyamhub_auto_payout_enabled', 'no' );
        if ( 'yes' !== $enabled ) {
            thaaniyamhub_log( 'ThaaniyamHub_Payout_Scheduler: Cron triggered but feature is disabled. Skipping execution.', 'info', 'thaaniyamhub-cashfree-payout' );
            return;
        }

        self::execute_batch_payouts( 'cron' );
    }

    /**
     * AJAX handler for Admin "Run Automated Payouts Now" trigger.
     */
    public static function ajax_trigger_manual_payout() {
        check_ajax_referer( 'thaaniyamhub_auto_payout_manual_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'thaaniyamhub-multi-vendor-orders' ) ] );
        }

        $results = self::execute_batch_payouts( 'manual' );
        wp_send_json_success( $results );
    }

    /**
     * Main batch execution engine.
     *
     * Disburses payouts aggregated per vendor for all mature, completed orders.
     *
     * @param string $source 'cron' or 'manual'
     * @return array Summary of execution results
     */
    public static function execute_batch_payouts( string $source = 'cron' ): array {
        global $wpdb, $WCFMmp;

        $start_time  = current_time( 'mysql' );
        $delay_days  = absint( get_option( 'thaaniyamhub_auto_payout_delay_days', 4 ) );
        $min_amount  = max( 0.0, (float) get_option( 'thaaniyamhub_auto_payout_min_amount', 0 ) );

        thaaniyamhub_log(
            sprintf( 'ThaaniyamHub_Payout_Scheduler: Starting automated payout run [Source: %s, Delay: %d days, Min Amount: ₹%s]', $source, $delay_days, $min_amount ),
            'info',
            'thaaniyamhub-cashfree-payout'
        );

        $api = ThaaniyamHub_Cashfree_Payout_API::get_instance();
        if ( ! $api->is_configured() ) {
            $err_msg = __( 'Cashfree Payout API credentials are not configured in settings. Payouts aborted.', 'thaaniyamhub-multi-vendor-orders' );
            thaaniyamhub_log( "ThaaniyamHub_Payout_Scheduler: {$err_msg}", 'error', 'thaaniyamhub-cashfree-payout' );
            self::record_last_run( $start_time, 0, 0.0, 0, [ $err_msg ], $source );
            return [
                'success'        => false,
                'message'        => $err_msg,
                'vendors_paid'   => 0,
                'total_amount'   => 0.0,
                'errors'         => [ $err_msg ],
            ];
        }

        // 1. Fetch all active vendors with eligible commissions
        $eligible_by_vendor = self::get_all_eligible_commissions_grouped_by_vendor( $delay_days );

        if ( empty( $eligible_by_vendor ) ) {
            $msg = sprintf( __( 'No eligible vendor commissions found (Criteria: Completed >= %d days ago, withdraw_status = pending, not in dispute).', 'thaaniyamhub-multi-vendor-orders' ), $delay_days );
            thaaniyamhub_log( "ThaaniyamHub_Payout_Scheduler: {$msg}", 'info', 'thaaniyamhub-cashfree-payout' );
            self::record_last_run( $start_time, 0, 0.0, 0, [], $source );
            return [
                'success'        => true,
                'message'        => $msg,
                'vendors_paid'   => 0,
                'total_amount'   => 0.0,
                'errors'         => [],
            ];
        }

        $vendors_processed = 0;
        $vendors_paid      = 0;
        $total_disbursed   = 0.0;
        $errors            = [];
        $payout_details    = [];

        $gateway = new WCFMmp_Gateway_Cashfree();

        foreach ( $eligible_by_vendor as $vendor_id => $commission_items ) {
            $vendors_processed++;
            $vendor_id = (int) $vendor_id;

            // Check if vendor account is disabled
            if ( get_user_meta( $vendor_id, '_disable_vendor', true ) ) {
                continue;
            }

            $vendor_sum = 0.0;
            $order_ids_set = [];
            $commission_ids_set = [];

            foreach ( $commission_items as $item ) {
                $comm_amount = (float) ( $item->total_commission ?? 0.0 );
                $vendor_sum += $comm_amount;
                $order_ids_set[] = (int) $item->order_id;
                $commission_ids_set[] = (int) $item->ID;
            }

            $vendor_sum = round( $vendor_sum, 2 );
            $order_ids_list = array_values( array_unique( $order_ids_set ) );
            $commission_ids_list = array_values( array_unique( $commission_ids_set ) );

            // Check minimum payout threshold
            if ( $min_amount > 0 && $vendor_sum < $min_amount ) {
                thaaniyamhub_log(
                    sprintf( 'ThaaniyamHub_Payout_Scheduler: Vendor #%d eligible earnings ₹%s is below minimum threshold ₹%s. Skipping.', $vendor_id, $vendor_sum, $min_amount ),
                    'info',
                    'thaaniyamhub-cashfree-payout'
                );
                continue;
            }

            if ( $vendor_sum <= 0 ) {
                continue;
            }

            // Verify vendor payout profile
            $payout_profile = $gateway->get_vendor_payout_details( $vendor_id );
            $has_bank = ! empty( $payout_profile['account_number'] ) && ! empty( $payout_profile['ifsc'] );
            $has_upi  = ! empty( $payout_profile['upi_id'] );

            if ( ! $has_bank && ! $has_upi ) {
                $err = sprintf( __( 'Vendor #%d has no Bank Account or UPI ID configured in payment setup. Skipping payout of ₹%s.', 'thaaniyamhub-multi-vendor-orders' ), $vendor_id, $vendor_sum );
                thaaniyamhub_log( "ThaaniyamHub_Payout_Scheduler: {$err}", 'warning', 'thaaniyamhub-cashfree-payout' );
                $errors[] = $err;
                continue;
            }

            // Process Single Aggregated Disbursal for this Vendor
            $payout_res = self::disburse_vendor_batch(
                $vendor_id,
                $vendor_sum,
                $commission_ids_list,
                $order_ids_list,
                $payout_profile,
                $source
            );

            if ( $payout_res['success'] ) {
                $vendors_paid++;
                $total_disbursed += $vendor_sum;
                $payout_details[] = [
                    'vendor_id'    => $vendor_id,
                    'amount'       => $vendor_sum,
                    'orders_count' => count( $order_ids_list ),
                    'transfer_id'  => $payout_res['transfer_id'],
                    'status'       => $payout_res['status'],
                ];
            } else {
                $errors[] = sprintf( 'Vendor #%d: %s', $vendor_id, $payout_res['error'] );
            }
        }

        self::record_last_run( $start_time, $vendors_paid, $total_disbursed, $vendors_processed, $errors, $source );

        $summary_msg = sprintf(
            __( 'Automated payout complete: %d vendor(s) paid totaling ₹%s across %d eligible vendor(s).', 'thaaniyamhub-multi-vendor-orders' ),
            $vendors_paid,
            number_format( $total_disbursed, 2 ),
            $vendors_processed
        );

        thaaniyamhub_log( "ThaaniyamHub_Payout_Scheduler: {$summary_msg}", 'info', 'thaaniyamhub-cashfree-payout' );

        return [
            'success'        => true,
            'message'        => $summary_msg,
            'vendors_paid'   => $vendors_paid,
            'total_amount'   => $total_disbursed,
            'errors'         => $errors,
            'payout_details' => $payout_details,
        ];
    }

    /**
     * Disburse an aggregated payout for a single vendor with strict atomic locking.
     *
     * @param int    $vendor_id
     * @param float  $amount
     * @param array  $commission_ids
     * @param array  $order_ids
     * @param array  $payout_profile
     * @param string $source
     * @return array
     */
    private static function disburse_vendor_batch( int $vendor_id, float $amount, array $commission_ids, array $order_ids, array $payout_profile, string $source = 'cron' ): array {
        global $wpdb, $WCFMmp;

        $order_ids_str      = implode( ',', $order_ids );
        $commission_ids_str = implode( ',', $commission_ids );
        $comm_placeholders  = implode( ',', array_fill( 0, count( $commission_ids ), '%d' ) );

        // 1. ATOMIC LOCK: Lock commission records to 'requested' so no manual withdrawal or duplicate cron can pick them up
        $locked_count = $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->prefix}wcfm_marketplace_orders 
             SET withdraw_status = 'requested' 
             WHERE ID IN ($comm_placeholders) AND withdraw_status = 'pending'",
            ...$commission_ids
        ) );

        if ( $locked_count <= 0 ) {
            return [
                'success' => false,
                'error'   => __( 'Commissions could not be locked (may have been claimed by another withdrawal request).', 'thaaniyamhub-multi-vendor-orders' ),
            ];
        }

        // 2. Lock matching records in ThaaniyamHub vendor ledger to 'processing'
        if ( ! empty( $order_ids ) ) {
            $order_placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->prefix}thaaniyamhub_vendor_ledger 
                 SET payout_status = 'processing' 
                 WHERE vendor_id = %d AND (sub_order_id IN ($order_placeholders) OR parent_order_id IN ($order_placeholders)) AND payout_status = 'pending'",
                $vendor_id,
                ...array_merge( $order_ids, $order_ids )
            ) );
        }

        // 3. Create WCFM Withdrawal Request record
        $withdraw_mode = ( 'manual' === $source ) ? 'by_auto_schedule_manual' : 'by_auto_schedule';
        $created_date  = current_time( 'mysql' );

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
                'withdraw_mode'      => $withdraw_mode,
                'is_auto_withdrawal' => 1,
                'created'            => $created_date,
            ],
            [ '%d', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%d', '%s' ]
        );

        if ( false === $insert_res ) {
            // Revert lock on DB failure
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->prefix}wcfm_marketplace_orders SET withdraw_status = 'pending' WHERE ID IN ($comm_placeholders)",
                ...$commission_ids
            ) );
            return [
                'success' => false,
                'error'   => 'DB error creating withdrawal request: ' . $wpdb->last_error,
            ];
        }

        $withdrawal_id = (int) $wpdb->insert_id;

        // 4. Generate Idempotent Transfer ID
        $transfer_id = sprintf( 'TH_AUTO_V%d_W%d_%d', $vendor_id, $withdrawal_id, time() );
        $remarks     = sprintf( 'ThaaniyamHub Scheduled Disbursal #%d (Orders: %s)', $withdrawal_id, $order_ids_str );

        $transfer_data = [
            'amount'     => $amount,
            'transferId' => $transfer_id,
            'name'       => $payout_profile['account_name'] ?: ( 'Vendor #' . $vendor_id ),
            'email'      => $payout_profile['email'] ?: 'vendor@thaaniyamhub.com',
            'phone'      => $payout_profile['phone'] ?: '9999999999',
            'remarks'    => substr( $remarks, 0, 70 ),
        ];

        if ( 'upi' === $payout_profile['payout_type'] && ! empty( $payout_profile['upi_id'] ) ) {
            $transfer_data['vpa']          = $payout_profile['upi_id'];
            $transfer_data['transferMode'] = 'upi';
        } else {
            $transfer_data['bankAccount']  = $payout_profile['account_number'];
            $transfer_data['ifsc']         = $payout_profile['ifsc'];
            $transfer_data['transferMode'] = get_option( 'thaaniyamhub_cashfree_payout_transfer_mode', 'banktransfer' );
        }

        thaaniyamhub_log(
            sprintf(
                'ThaaniyamHub_Payout_Scheduler: Initiating Cashfree Direct Transfer for Vendor #%d [Withdrawal #%d, Amount: ₹%s, Mode: %s, TransferID: %s]',
                $vendor_id,
                $withdrawal_id,
                $amount,
                $transfer_data['transferMode'],
                $transfer_id
            ),
            'info',
            'thaaniyamhub-cashfree-payout'
        );

        // 5. Call Cashfree Payout API
        $api = ThaaniyamHub_Cashfree_Payout_API::get_instance();
        $response = $api->direct_transfer( $transfer_data );

        if ( is_wp_error( $response ) ) {
            $error_msg = $response->get_error_message();
            thaaniyamhub_log(
                "ThaaniyamHub_Payout_Scheduler: Payout failed for Vendor #{$vendor_id} (Withdrawal #{$withdrawal_id}) — {$error_msg}",
                'error',
                'thaaniyamhub-cashfree-payout'
            );

            // Revert commission status back to pending so they can be fixed and re-attempted
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->prefix}wcfm_marketplace_orders SET withdraw_status = 'pending' WHERE ID IN ($comm_placeholders)",
                ...$commission_ids
            ) );

            if ( ! empty( $order_ids ) ) {
                $order_placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}thaaniyamhub_vendor_ledger 
                     SET payout_status = 'failed' 
                     WHERE vendor_id = %d AND (sub_order_id IN ($order_placeholders) OR parent_order_id IN ($order_placeholders))",
                    $vendor_id,
                    ...array_merge( $order_ids, $order_ids )
                ) );
            }

            // Mark withdrawal request as cancelled/failed with note
            $wpdb->update(
                "{$wpdb->prefix}wcfm_marketplace_withdraw_request",
                [ 'withdraw_status' => 'cancelled', 'withdraw_note' => 'Cashfree Error: ' . $error_msg ],
                [ 'ID' => $withdrawal_id ]
            );

            return [
                'success' => false,
                'error'   => $error_msg,
            ];
        }

        $transfer_status = $response['transfer_status'] ?? 'PENDING';
        $reference_id    = $response['referenceId'] ?? '';
        $utr             = $response['utr'] ?? '';

        // Update withdrawal metadata
        if ( isset( $WCFMmp->wcfmmp_withdraw ) && method_exists( $WCFMmp->wcfmmp_withdraw, 'wcfmmp_update_withdrawal_meta' ) ) {
            $WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $withdrawal_id, 'withdraw_amount', $amount );
            $WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $withdrawal_id, 'currency', get_woocommerce_currency() );
            $WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $withdrawal_id, 'cashfree_transfer_id', $transfer_id );
            $WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $withdrawal_id, 'cashfree_reference_id', $reference_id );
            $WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $withdrawal_id, 'cashfree_utr', $utr );
            $WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $withdrawal_id, 'cashfree_status', $transfer_status );
            $WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $withdrawal_id, 'cashfree_transfer_mode', $transfer_data['transferMode'] );
            $WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $withdrawal_id, 'scheduled_payout', '1' );
        }

        if ( 'SUCCESS' === $transfer_status ) {
            // Immediate success: mark withdrawal completed, commissions completed, ledger disbursed
            $note = sprintf( __( 'Scheduled Automated Payout Completed. Transfer ID: %s | UTR: %s', 'thaaniyamhub-multi-vendor-orders' ), $transfer_id, $utr );
            if ( isset( $WCFMmp->wcfmmp_withdraw ) && method_exists( $WCFMmp->wcfmmp_withdraw, 'wcfmmp_withdraw_status_update_by_withdrawal' ) ) {
                $WCFMmp->wcfmmp_withdraw->wcfmmp_withdraw_status_update_by_withdrawal( $withdrawal_id, 'completed', $note );
            } else {
                $wpdb->update(
                    "{$wpdb->prefix}wcfm_marketplace_withdraw_request",
                    [ 'withdraw_status' => 'completed', 'withdraw_note' => $note, 'withdraw_paid_date' => current_time( 'mysql' ) ],
                    [ 'ID' => $withdrawal_id ]
                );
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}wcfm_marketplace_orders SET withdraw_status = 'completed', commission_paid_date = %s WHERE ID IN ($comm_placeholders)",
                    current_time( 'mysql' ),
                    ...$commission_ids
                ) );
            }

            if ( ! empty( $order_ids ) ) {
                $order_placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}thaaniyamhub_vendor_ledger 
                     SET payout_status = 'disbursed' 
                     WHERE vendor_id = %d AND (sub_order_id IN ($order_placeholders) OR parent_order_id IN ($order_placeholders))",
                    $vendor_id,
                    ...array_merge( $order_ids, $order_ids )
                ) );
            }
        } else {
            // PENDING / RECEIVED: Retain 'requested' / 'processing' status — Webhook & sync poller will finalize UTR
            thaaniyamhub_log(
                sprintf( 'ThaaniyamHub_Payout_Scheduler: Transfer #%s queued in PENDING status. Awaiting webhook/poller confirmation.', $transfer_id ),
                'info',
                'thaaniyamhub-cashfree-payout'
            );
        }

        return [
            'success'     => true,
            'transfer_id' => $transfer_id,
            'status'      => $transfer_status,
            'utr'         => $utr,
        ];
    }

    /**
     * Query and return all mature eligible commissions grouped by vendor_id.
     *
     * Criteria:
     *   1. Order is 'completed' (or child suborder is completed).
     *   2. Order completed date is >= $delay_days ago.
     *   3. withdraw_status = 'pending'.
     *   4. is_withdrawable = 1, is_refunded = 0, refund_status != 'requested', is_trashed = 0.
     *   5. Not in an existing withdrawal request.
     *   6. Not marked 'disbursed' or 'processing' in ThaaniyamHub ledger.
     *
     * @param int $delay_days
     * @return array [ vendor_id => [ commission_row, ... ] ]
     */
    public static function get_all_eligible_commissions_grouped_by_vendor( int $delay_days = 4 ): array {
        global $wpdb;

        $sql = "SELECT c.*, o.post_date, o.post_status 
                FROM {$wpdb->prefix}wcfm_marketplace_orders AS c
                INNER JOIN {$wpdb->posts} AS o ON c.order_id = o.ID
                WHERE c.withdraw_status = 'pending'
                  AND c.is_withdrawable = 1
                  AND c.is_refunded = 0
                  AND c.is_trashed = 0
                  AND (c.refund_status IS NULL OR c.refund_status != 'requested')
                  AND c.total_commission > 0";

        $rows = $wpdb->get_results( $sql );
        if ( empty( $rows ) ) {
            return [];
        }

        $now_ts    = current_time( 'timestamp' );
        $cutoff_ts = $now_ts - ( $delay_days * DAY_IN_SECONDS );

        $grouped = [];

        foreach ( $rows as $row ) {
            $order_id  = (int) $row->order_id;
            $vendor_id = (int) $row->vendor_id;

            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                continue;
            }

            // Check order status: must be completed
            $status = $order->get_status();
            if ( 'completed' !== $status ) {
                // If this is a split parent order, verify if this specific vendor's sub-order is completed
                $sub_orders = $order->get_meta( '_thaaniyamhub_suborders', true );
                $sub_completed = false;
                if ( is_array( $sub_orders ) && ! empty( $sub_orders ) ) {
                    foreach ( $sub_orders as $sub_id ) {
                        $sub = wc_get_order( $sub_id );
                        if ( $sub && (int) $sub->get_meta( '_thaaniyamhub_vendor_id', true ) === $vendor_id ) {
                            if ( 'completed' === $sub->get_status() ) {
                                $sub_completed = true;
                                $order = $sub; // Use sub-order for date_completed check
                                break;
                            }
                        }
                    }
                }
                if ( ! $sub_completed ) {
                    continue;
                }
            }

            // Check Maturity Delay: Order completion date must be <= $cutoff_ts
            $completed_date = $order->get_date_completed();
            $completed_ts   = $completed_date ? $completed_date->getTimestamp() : 0;

            if ( ! $completed_ts ) {
                // Fallback to order creation date if date_completed is not recorded
                // Uses WC_Order API instead of get_post_field() for HPOS compatibility
                $created_date = $order->get_date_created();
                $completed_ts = $created_date ? $created_date->getTimestamp() : 0;
            }

            if ( $completed_ts > $cutoff_ts ) {
                // Order is completed but has not yet reached maturity delay
                continue;
            }

            // Verify against ThaaniyamHub vendor ledger status
            $ledger_status = $wpdb->get_var( $wpdb->prepare(
                "SELECT payout_status FROM {$wpdb->prefix}thaaniyamhub_vendor_ledger 
                 WHERE vendor_id = %d AND (sub_order_id = %d OR parent_order_id = %d) 
                 ORDER BY id DESC LIMIT 1",
                $vendor_id,
                $order->get_id(),
                $order_id
            ) );

            if ( in_array( $ledger_status, [ 'disbursed', 'processing' ], true ) ) {
                continue;
            }

            if ( ! isset( $grouped[ $vendor_id ] ) ) {
                $grouped[ $vendor_id ] = [];
            }
            $grouped[ $vendor_id ][] = $row;
        }

        return $grouped;
    }

    /**
     * Record execution metrics in WordPress options.
     *
     * @param string $start_time
     * @param int    $vendors_paid
     * @param float  $total_disbursed
     * @param int    $vendors_evaluated
     * @param array  $errors
     * @param string $source
     */
    private static function record_last_run( string $start_time, int $vendors_paid, float $total_disbursed, int $vendors_evaluated, array $errors, string $source ) {
        $stats = [
            'timestamp'          => current_time( 'mysql' ),
            'start_time'         => $start_time,
            'source'             => $source,
            'vendors_paid'       => $vendors_paid,
            'total_disbursed'    => $total_disbursed,
            'vendors_evaluated'  => $vendors_evaluated,
            'errors'             => array_slice( $errors, 0, 10 ),
            'status'             => empty( $errors ) ? 'SUCCESS' : ( $vendors_paid > 0 ? 'PARTIAL' : 'FAILED' ),
        ];
        update_option( self::LAST_RUN_OPTION, $stats, 'no' );
    }

    /**
     * Retrieve stored last run statistics.
     *
     * @return array|null
     */
    public static function get_last_run_stats(): ?array {
        $stats = get_option( self::LAST_RUN_OPTION, null );
        return is_array( $stats ) ? $stats : null;
    }
}
