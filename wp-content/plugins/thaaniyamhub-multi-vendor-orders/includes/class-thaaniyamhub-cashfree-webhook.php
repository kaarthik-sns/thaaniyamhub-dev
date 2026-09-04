<?php
/**
 * Thaaniyam Hub Marketplace — Cashfree Payout Webhook Handler & Status Sync
 *
 * Listens for Cashfree Payout webhook notifications (TRANSFER_SUCCESS, TRANSFER_FAILED, etc.),
 * verifies signatures, updates WCFM withdrawal requests, and synchronizes the financial ledger.
 * Also provides a 15-minute background cron fallback to query pending transfers.
 *
 * @package thaaniyamhub-multi-vendor-orders
 */

defined('ABSPATH') || exit;

class ThaaniyamHub_Cashfree_Webhook {

    private const REST_NAMESPACE = 'thaaniyamhub/v1';
    private const REST_ROUTE     = 'cashfree-payout-webhook';

    /**
     * Singleton instance.
     *
     * @var ThaaniyamHub_Cashfree_Webhook|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return ThaaniyamHub_Cashfree_Webhook
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_webhook_route' ] );
        add_filter( 'cron_schedules', [ $this, 'add_cron_schedules' ] );
        add_action( 'thaaniyamhub_cashfree_payout_sync_pending', [ $this, 'sync_pending_transfers' ] );
        add_action( 'wp_ajax_thaaniyamhub_manual_sync_transfers', [ $this, 'ajax_manual_sync_transfers' ] );

        if ( ! wp_next_scheduled( 'thaaniyamhub_cashfree_payout_sync_pending' ) ) {
            wp_schedule_event( time() + 300, 'every_fifteen_minutes', 'thaaniyamhub_cashfree_payout_sync_pending' );
        }
    }

    /**
     * Add custom 15-minute cron schedule.
     *
     * @param array $schedules
     * @return array
     */
    public function add_cron_schedules( $schedules ) {
        if ( ! isset( $schedules['every_fifteen_minutes'] ) ) {
            $schedules['every_fifteen_minutes'] = [
                'interval' => 900,
                'display'  => __( 'Every 15 Minutes (ThaaniyamHub)', 'thaaniyamhub-multi-vendor-orders' ),
            ];
        }
        return $schedules;
    }

    /**
     * Register REST API Webhook Route.
     */
    public function register_webhook_route() {
        register_rest_route(
            self::REST_NAMESPACE,
            '/' . self::REST_ROUTE,
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_webhook' ],
                'permission_callback' => '__return_true', // Validated via signature
            ]
        );
    }

    /**
     * Handle incoming Cashfree Webhook POST request.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_webhook( WP_REST_Request $request ) {
        $raw_body  = $request->get_body();
        $signature = $request->get_header( 'x-webhook-signature' ) ?: ( $request->get_header( 'x-cashfree-signature' ) ?: '' );
        $timestamp = $request->get_header( 'x-webhook-timestamp' ) ?: '';

        thaaniyamhub_log( "Cashfree_Webhook: Received webhook notification: {$raw_body}", 'info', 'thaaniyamhub-cashfree-payout' );

        $api = ThaaniyamHub_Cashfree_Payout_API::get_instance();
        if ( ! empty( $signature ) && ! $api->verify_webhook_signature( $raw_body, $signature, $timestamp ) ) {
            thaaniyamhub_log( 'Cashfree_Webhook: Invalid signature received!', 'error', 'thaaniyamhub-cashfree-payout' );
            return new WP_REST_Response( [ 'status' => 'error', 'message' => 'Invalid signature' ], 401 );
        }

        $params = $request->get_json_params();
        if ( empty( $params ) ) {
            $params = $request->get_body_params();
        }

        if ( empty( $params ) ) {
            return new WP_REST_Response( [ 'status' => 'success', 'message' => 'Ping acknowledged' ], 200 );
        }

        // Handle Cashfree v2 nested 'data' payload or flat v1 payload
        $data_obj    = is_array( $params['data'] ?? null ) ? $params['data'] : $params;
        $event       = strtoupper( (string) ( $params['event'] ?? ( $params['type'] ?? '' ) ) );
        $transfer_id = sanitize_text_field( $data_obj['transfer_id'] ?? ( $data_obj['transferId'] ?? ( $params['transfer_id'] ?? ( $params['transferId'] ?? '' ) ) ) );
        $ref_id      = sanitize_text_field( $data_obj['cf_transfer_id'] ?? ( $data_obj['referenceId'] ?? ( $params['cf_transfer_id'] ?? ( $params['referenceId'] ?? '' ) ) ) );
        $utr         = sanitize_text_field( $data_obj['utr'] ?? ( $params['utr'] ?? '' ) );
        $status      = strtoupper( (string) ( $data_obj['status'] ?? ( $data_obj['status_code'] ?? ( $params['status'] ?? ( $params['status_code'] ?? '' ) ) ) ) );
        $reason      = sanitize_text_field( $data_obj['status_description'] ?? ( $data_obj['reason'] ?? ( $params['status_description'] ?? ( $params['reason'] ?? '' ) ) ) );

        thaaniyamhub_log(
            sprintf( 'Cashfree_Webhook: Event: %s | Transfer ID: %s | Status: %s | UTR: %s | Ref: %s', $event, $transfer_id, $status, $utr, $ref_id ),
            'info',
            'thaaniyamhub-cashfree-payout'
        );

        if ( empty( $transfer_id ) ) {
            return new WP_REST_Response( [ 'status' => 'success', 'message' => 'Acknowledged (No transferId)' ], 200 );
        }

        $withdrawal_id = $this->find_withdrawal_by_transfer_id( $transfer_id );
        if ( ! $withdrawal_id ) {
            thaaniyamhub_log( "Cashfree_Webhook: No withdrawal matching transferId {$transfer_id} found.", 'warning', 'thaaniyamhub-cashfree-payout' );
            return new WP_REST_Response( [ 'status' => 'success', 'message' => 'Transfer ID not found in system' ], 200 );
        }

        $is_success = ( 'TRANSFER_SUCCESS' === $event || 'SUCCESS' === $status );
        $is_failure = in_array( $event, [ 'TRANSFER_FAILED', 'TRANSFER_REVERSED', 'TRANSFER_REJECTED' ], true ) || in_array( $status, [ 'FAILED', 'REVERSED', 'REJECTED' ], true );

        if ( $is_success ) {
            $this->process_successful_transfer( $withdrawal_id, $transfer_id, $ref_id, $utr );
        } elseif ( $is_failure ) {
            $this->process_failed_transfer( $withdrawal_id, $transfer_id, $ref_id, $reason );
        }

        return new WP_REST_Response( [ 'status' => 'success', 'message' => 'Webhook processed successfully' ], 200 );
    }

    /**
     * Find WCFM withdrawal request ID by Cashfree Transfer ID.
     *
     * @param string $transfer_id
     * @return int
     */
    private function find_withdrawal_by_transfer_id( string $transfer_id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wcfm_marketplace_withdraw_request_meta';
        $withdrawal_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT withdraw_id FROM {$table} WHERE `key` = 'cashfree_transfer_id' AND `value` = %s LIMIT 1",
            $transfer_id
        ) );

        if ( ! $withdrawal_id ) {
            // Check if transferId contains withdrawal ID (TH_WDRW_{id}_timestamp)
            if ( preg_match( '/TH_WDRW_(\d+)_/i', $transfer_id, $matches ) ) {
                $withdrawal_id = (int) $matches[1];
            }
        }

        return $withdrawal_id;
    }

    /**
     * Process a verified successful payout transfer.
     *
     * @param int    $withdrawal_id
     * @param string $transfer_id
     * @param string $ref_id
     * @param string $utr
     */
    public function process_successful_transfer( int $withdrawal_id, string $transfer_id, string $ref_id = '', string $utr = '' ) {
        global $WCFMmp, $wpdb;

        thaaniyamhub_log( "Cashfree_Webhook: Marking Withdrawal #{$withdrawal_id} as COMPLETED. UTR: {$utr}", 'info', 'thaaniyamhub-cashfree-payout' );

        $note = sprintf( __( 'Cashfree Payout Completed. Transfer ID: %s | Ref: %s | UTR: %s', 'thaaniyamhub-multi-vendor-orders' ), $transfer_id, $ref_id, $utr );

        if ( isset( $WCFMmp->wcfmmp_withdraw ) && method_exists( $WCFMmp->wcfmmp_withdraw, 'wcfmmp_withdraw_status_update_by_withdrawal' ) ) {
            $WCFMmp->wcfmmp_withdraw->wcfmmp_withdraw_status_update_by_withdrawal( $withdrawal_id, 'completed', $note );
            $WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $withdrawal_id, 'cashfree_status', 'SUCCESS' );
            if ( $utr ) {
                $WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $withdrawal_id, 'cashfree_utr', $utr );
            }
            if ( $ref_id ) {
                $WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $withdrawal_id, 'cashfree_reference_id', $ref_id );
            }
        } else {
            $wpdb->update(
                "{$wpdb->prefix}wcfm_marketplace_withdraw_request",
                [ 'withdraw_status' => 'completed', 'withdraw_note' => $note, 'withdraw_paid_date' => current_time( 'mysql' ) ],
                [ 'ID' => $withdrawal_id ]
            );
        }

        // Fetch vendor ID and update ThaaniyamHub financial ledger
        $vendor_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT vendor_id FROM {$wpdb->prefix}wcfm_marketplace_withdraw_request WHERE ID = %d",
            $withdrawal_id
        ) );

        if ( $vendor_id ) {
            $commission_ids_str = $wpdb->get_var( $wpdb->prepare(
                "SELECT commission_ids FROM {$wpdb->prefix}wcfm_marketplace_withdraw_request WHERE ID = %d",
                $withdrawal_id
            ) );

            if ( ! empty( $commission_ids_str ) ) {
                $commission_ids = array_filter( array_map( 'intval', explode( ',', $commission_ids_str ) ) );
                if ( ! empty( $commission_ids ) ) {
                    $placeholders = implode( ',', array_fill( 0, count( $commission_ids ), '%d' ) );
                    $order_ids    = $wpdb->get_col( $wpdb->prepare(
                        "SELECT DISTINCT order_id FROM {$wpdb->prefix}wcfm_marketplace_orders WHERE ID IN ($placeholders)",
                        ...$commission_ids
                    ) );

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
                }
            }
        }
    }

    /**
     * Process a failed or reversed payout transfer.
     *
     * @param int    $withdrawal_id
     * @param string $transfer_id
     * @param string $ref_id
     * @param string $reason
     */
    public function process_failed_transfer( int $withdrawal_id, string $transfer_id, string $ref_id = '', string $reason = '' ) {
        global $WCFMmp, $wpdb;

        thaaniyamhub_log( "Cashfree_Webhook: Withdrawal #{$withdrawal_id} FAILED. Reason: {$reason}", 'error', 'thaaniyamhub-cashfree-payout' );

        $note = sprintf( __( 'Cashfree Payout FAILED/CANCELLED. Transfer ID: %s | Reason: %s', 'thaaniyamhub-multi-vendor-orders' ), $transfer_id, $reason );

        if ( isset( $WCFMmp->wcfmmp_withdraw ) && method_exists( $WCFMmp->wcfmmp_withdraw, 'wcfmmp_withdraw_status_update_by_withdrawal' ) ) {
            $WCFMmp->wcfmmp_withdraw->wcfmmp_withdraw_status_update_by_withdrawal( $withdrawal_id, 'cancelled', $note );
            $WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $withdrawal_id, 'cashfree_status', 'FAILED' );
            $WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $withdrawal_id, 'cashfree_failure_reason', $reason );
        } else {
            $wpdb->update(
                "{$wpdb->prefix}wcfm_marketplace_withdraw_request",
                [ 'withdraw_status' => 'cancelled', 'withdraw_note' => $note ],
                [ 'ID' => $withdrawal_id ]
            );
        }

        // Revert ledger status back to pending so vendor commission remains accurate
        $vendor_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT vendor_id FROM {$wpdb->prefix}wcfm_marketplace_withdraw_request WHERE ID = %d",
            $withdrawal_id
        ) );

        if ( $vendor_id ) {
            $commission_ids_str = $wpdb->get_var( $wpdb->prepare(
                "SELECT commission_ids FROM {$wpdb->prefix}wcfm_marketplace_withdraw_request WHERE ID = %d",
                $withdrawal_id
            ) );

            if ( ! empty( $commission_ids_str ) ) {
                $commission_ids = array_filter( array_map( 'intval', explode( ',', $commission_ids_str ) ) );
                if ( ! empty( $commission_ids ) ) {
                    $placeholders = implode( ',', array_fill( 0, count( $commission_ids ), '%d' ) );
                    $order_ids    = $wpdb->get_col( $wpdb->prepare(
                        "SELECT DISTINCT order_id FROM {$wpdb->prefix}wcfm_marketplace_orders WHERE ID IN ($placeholders)",
                        ...$commission_ids
                    ) );

                    if ( ! empty( $order_ids ) ) {
                        $order_placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );
                        $wpdb->query( $wpdb->prepare(
                            "UPDATE {$wpdb->prefix}thaaniyamhub_vendor_ledger 
                             SET payout_status = 'pending' 
                             WHERE vendor_id = %d AND (sub_order_id IN ($order_placeholders) OR parent_order_id IN ($order_placeholders))",
                            $vendor_id,
                            ...array_merge( $order_ids, $order_ids )
                        ) );
                    }
                }
            }
        }
    }

    /**
     * Fallback Background Cron Task / Manual Poller:
     * Queries Cashfree API for all pending transfers and updates status.
     *
     * @return array Status report
     */
    public function sync_pending_transfers() {
        global $wpdb;

        $api = ThaaniyamHub_Cashfree_Payout_API::get_instance();
        if ( ! $api->is_configured() ) {
            return [
                'status'  => false,
                'message' => __( 'Cashfree API credentials are not configured.', 'thaaniyamhub-multi-vendor-orders' ),
                'synced'  => 0,
                'details' => [],
            ];
        }

        $meta_table = $wpdb->prefix . 'wcfm_marketplace_withdraw_request_meta';
        $wdrw_table = $wpdb->prefix . 'wcfm_marketplace_withdraw_request';

        // Find all withdrawals that have a cashfree_transfer_id and are still pending in WCFM or Cashfree
        $pending_transfers = $wpdb->get_results(
            "SELECT DISTINCT r.ID AS withdraw_id, m1.value AS transfer_id, m2.value AS cashfree_status, r.withdraw_status
             FROM {$wdrw_table} r
             INNER JOIN {$meta_table} m1 ON r.ID = m1.withdraw_id AND m1.key = 'cashfree_transfer_id'
             LEFT JOIN {$meta_table} m2 ON r.ID = m2.withdraw_id AND m2.key = 'cashfree_status'
             WHERE (r.withdraw_status IN ('pending', 'processing') 
                    OR m2.value IN ('PENDING', 'RECEIVED', 'PROCESSING')
                    OR m2.value IS NULL)
               AND m1.value != ''
             ORDER BY r.ID DESC
             LIMIT 25"
        );

        if ( empty( $pending_transfers ) ) {
            return [
                'status'  => true,
                'message' => __( 'No pending Cashfree transfers found to sync.', 'thaaniyamhub-multi-vendor-orders' ),
                'synced'  => 0,
                'details' => [],
            ];
        }

        thaaniyamhub_log( sprintf( 'Cashfree_Webhook: Syncing %d pending transfers via API poller...', count( $pending_transfers ) ), 'info', 'thaaniyamhub-cashfree-payout' );

        $synced_count = 0;
        $details      = [];

        foreach ( $pending_transfers as $item ) {
            $withdrawal_id = (int) $item->withdraw_id;
            $transfer_id   = (string) $item->transfer_id;

            $status_res = $api->get_transfer_status( $transfer_id );
            if ( is_wp_error( $status_res ) || ! isset( $status_res['transfer_status'] ) ) {
                $details[] = [
                    'withdrawal_id' => $withdrawal_id,
                    'transfer_id'   => $transfer_id,
                    'status'        => 'ERROR',
                    'message'       => is_wp_error( $status_res ) ? $status_res->get_error_message() : 'Unknown status response',
                ];
                continue;
            }

            $current_status = strtoupper( (string) $status_res['transfer_status'] );
            $utr            = $status_res['utr'] ?? '';
            $ref_id         = $status_res['referenceId'] ?? '';
            $reason         = $status_res['reason'] ?? '';

            if ( 'SUCCESS' === $current_status ) {
                $this->process_successful_transfer( $withdrawal_id, $transfer_id, $ref_id, $utr );
                $synced_count++;
                $details[] = [
                    'withdrawal_id' => $withdrawal_id,
                    'transfer_id'   => $transfer_id,
                    'status'        => 'SUCCESS',
                    'utr'           => $utr,
                    'reference_id'  => $ref_id,
                ];
            } elseif ( in_array( $current_status, [ 'FAILED', 'REVERSED', 'REJECTED' ], true ) ) {
                $this->process_failed_transfer( $withdrawal_id, $transfer_id, $ref_id, $reason );
                $synced_count++;
                $details[] = [
                    'withdrawal_id' => $withdrawal_id,
                    'transfer_id'   => $transfer_id,
                    'status'        => 'FAILED',
                    'reason'        => $reason,
                ];
            } else {
                $details[] = [
                    'withdrawal_id' => $withdrawal_id,
                    'transfer_id'   => $transfer_id,
                    'status'        => $current_status,
                    'message'       => $reason ?: 'Awaiting partner bank confirmation',
                ];
            }
        }

        update_option( 'thaaniyamhub_cf_last_sync_timestamp', time() );
        update_option( 'thaaniyamhub_cf_last_sync_result', [
            'count'   => count( $pending_transfers ),
            'synced'  => $synced_count,
            'time'    => current_time( 'mysql' ),
            'details' => $details,
        ] );

        return [
            'status'  => true,
            'message' => sprintf( __( 'Checked %d transfer(s). %d resolved & updated.', 'thaaniyamhub-multi-vendor-orders' ), count( $pending_transfers ), $synced_count ),
            'synced'  => $synced_count,
            'total'   => count( $pending_transfers ),
            'details' => $details,
        ];
    }

    /**
     * AJAX handler to trigger manual transfer sync from the settings page.
     */
    public function ajax_manual_sync_transfers() {
        check_ajax_referer( 'thaaniyamhub_cf_sync_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized action.', 'thaaniyamhub-multi-vendor-orders' ) ] );
        }

        $result = $this->sync_pending_transfers();
        wp_send_json_success( $result );
    }
}
