<?php
/**
 * Thaaniyam Hub Marketplace — Tracking Sync Engine
 *
 * Implements real-time tracking status updates using Shiprocket Webhooks,
 * and a failsafe background cron job to poll Shiprocket's tracking API.
 *
 * Webhook REST endpoint:
 *   POST /wp-json/thaaniyamhub-multi-vendor-orders/v1/tracking-webhook
 *
 * Fallback Cron:
 *   thaaniyamhub_sf_tracking_sync (twice daily)
 *
 * @package thaaniyamhub-multi-vendor-orders
 */

defined('ABSPATH') || exit;

if ( ! class_exists( 'ThaaniyamHub_Tracker' ) ) {
class ThaaniyamHub_Tracker {

    public static function init() {
        // ---- Register Webhook REST Route ----
        add_action( 'rest_api_init', [ __CLASS__, 'register_webhook_route' ] );

        // ---- Register Background Cron ----
        add_action( 'thaaniyamhub_sf_tracking_sync', [ __CLASS__, 'poll_tracking_status' ] );
        if ( ! wp_next_scheduled( 'thaaniyamhub_sf_tracking_sync' ) ) {
            wp_schedule_event( time(), 'twicedaily', 'thaaniyamhub_sf_tracking_sync' );
        }
    }

    // =========================================================================
    // 1. REST WEBHOOK ROUTE
    // =========================================================================

    public static function register_webhook_route() {
        register_rest_route( 'thaaniyamhub-multi-vendor-orders/v1', '/tracking-webhook', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle_webhook_request' ],
            'permission_callback' => [ __CLASS__, 'validate_webhook_signature' ],
        ] );
    }

    /**
     * Validate incoming webhook signature using the configured secret key.
     */
    public static function validate_webhook_signature( WP_REST_Request $request ) {
        $secret = get_option( 'thaaniyamhub_shiprocket_webhook_secret', '' );
        if ( empty( $secret ) ) {
            return true; // Bypass signature check if secret is not configured.
        }

        $signature = $request->get_header( 'x-shiprocket-signature' );
        if ( empty( $signature ) ) {
            thaaniyamhub_log( 'ThaaniyamHub_Tracker Webhook: Request blocked — x-shiprocket-signature header missing.' );
            return false;
        }

        $body     = $request->get_body();
        $computed = hash_hmac( 'sha256', $body, $secret );

        if ( ! hash_equals( $computed, $signature ) ) {
            thaaniyamhub_log( 'ThaaniyamHub_Tracker Webhook: Request blocked — invalid signature match.' );
            return false;
        }

        return true;
    }

    /**
     * Webhook request handler.
     */
    public static function handle_webhook_request( WP_REST_Request $request ) {
        $payload = $request->get_json_params();

        if ( empty( $payload ) ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => 'Empty payload' ], 400 );
        }

        thaaniyamhub_log( 'ThaaniyamHub_Tracker Webhook: Received payload: ' . wp_json_encode( $payload ) );

        $result = self::process_tracking_payload( $payload );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => $result->get_error_message() ], 400 );
        }

        return new WP_REST_Response( [ 'success' => true, 'message' => 'Fulfillment status updated successfully' ], 200 );
    }

    // =========================================================================
    // 2. PAYLOAD PROCESSING & STATUS MAPPING
    // =========================================================================

    /**
     * Process tracking payload (common for both webhook and cron poller).
     *
     * @param array $payload Tracking payload.
     * @return true|WP_Error
     */
    public static function process_tracking_payload( array $payload ) {
        global $wpdb;

        $awb         = sanitize_text_field( $payload['awb'] ?? ( $payload['awb_code'] ?? '' ) );
        $shipment_id = sanitize_text_field( $payload['shipment_id'] ?? '' );
        $order_id    = sanitize_text_field( $payload['order_id'] ?? '' );

        thaaniyamhub_log( "Tracker Sync: Processing tracking payload. AWB: '{$awb}', Shipment ID: '{$shipment_id}', SR Order ID: '{$order_id}'." );

        if ( ! $awb && ! $shipment_id && ! $order_id ) {
            thaaniyamhub_log( "Tracker Sync: Error — No AWB, Shipment ID, or Order ID provided in payload.", 'error' );
            return new WP_Error( 'missing_identifiers', 'No AWB, Shipment ID, or Order ID provided in payload.' );
        }

        // 1. Locate local fulfillment record.
        $record = null;
        $table  = $wpdb->prefix . 'thaaniyamhub_shiprocket_fulfillment';

        if ( $awb ) {
            $record = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE awb_code = %s", $awb ) );
        }
        if ( ! $record && $shipment_id ) {
            $record = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE shiprocket_shipment_id = %s", $shipment_id ) );
        }
        if ( ! $record && $order_id ) {
            // Strip channel prefixes if present, e.g. "AG-123" -> "123".
            $clean_order_id = str_replace( 'AG-', '', $order_id );
            // Strip retry suffixes if present, e.g. "123-R1" -> "123".
            if ( false !== ($pos = strpos($clean_order_id, '-R')) ) {
                $clean_order_id = substr($clean_order_id, 0, $pos);
            }
            $record = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE shiprocket_order_id = %s OR sub_order_id = %d", $order_id, (int)$clean_order_id ) );
        }

        if ( ! $record ) {
            thaaniyamhub_log( "Tracker Sync: Local fulfillment record not found for AWB: {$awb} / Shipment: {$shipment_id} / Order: {$order_id}.", 'warning' );
            return new WP_Error( 'record_not_found', "Fulfillment record not found for AWB: {$awb} / Shipment: {$shipment_id} / Order: {$order_id}" );
        }

        $sub_order_id = (int) $record->sub_order_id;
        thaaniyamhub_log( "Tracker Sync: Found local fulfillment record. Matched Sub-order ID: #{$sub_order_id}, Vendor: #{$record->vendor_id}, Old Status: '{$record->fulfillment_status}'." );

        $order        = wc_get_order( $sub_order_id );

        if ( ! $order ) {
            thaaniyamhub_log( "Tracker Sync: WooCommerce sub-order #{$sub_order_id} not found in database.", 'error' );
            return new WP_Error( 'order_not_found', "WooCommerce sub-order #{$sub_order_id} not found." );
        }

        // Get status fields from payload.
        $status_id   = (int) ( $payload['current_status_id'] ?? ( $payload['status_id'] ?? 0 ) );
        $status_str  = sanitize_text_field( $payload['current_status'] ?? ( $payload['status'] ?? '' ) );
        $status_code = sanitize_text_field( $payload['status_code'] ?? '' );
        $courier     = sanitize_text_field( $payload['courier_name'] ?? ( $payload['courier'] ?? $record->courier_name ) );

        thaaniyamhub_log( "Tracker Sync: Parsing status. ID={$status_id}, Str='{$status_str}', Code='{$status_code}', Courier='{$courier}'." );

        // Map status to local fulfillment status and WooCommerce order status.
        $fulfillment_status = $record->fulfillment_status;
        $wc_status_to       = '';
        $note               = '';

        $is_return = false;
        if ( in_array( $record->fulfillment_status, [ 'return_initiated', 'return_picked_up', 'return_ofd', 'returned', 'return_cancelled' ], true ) || ! empty( $order->get_meta( '_shiprocket_return_order_id' ) ) ) {
            $is_return = true;
        }

        // Standard Shiprocket Status Mapping
        // ID 7 = Delivered, ID 8 = Cancelled, ID 9/10/11 = RTO
        if ( 7 === $status_id || 'DLVD' === $status_code || stripos( $status_str, 'delivered' ) !== false ) {
            if ( $is_return ) {
                $fulfillment_status = 'returned';
                $wc_status_to       = ''; // User requirement: "admin will make refund manually"
                $note               = sprintf( __( '🚚 Return Sync: Return shipment delivered back to vendor. Carrier: %s. AWB: %s.', 'thaaniyamhub-multi-vendor-orders' ), $courier, $awb );
            } else {
                $fulfillment_status = 'delivered';
                $wc_status_to       = 'completed';
                $note               = sprintf( __( '🚚 Delivery Sync: Shipment delivered successfully. Carrier: %s. AWB: %s.', 'thaaniyamhub-multi-vendor-orders' ), $courier, $awb );
            }
        } elseif ( 8 === $status_id || 'CNCL' === $status_code || stripos( $status_str, 'cancelled' ) !== false ) {
            if ( $is_return ) {
                $fulfillment_status = 'return_cancelled';
                $wc_status_to       = '';
                $note               = sprintf( __( '❌ Return Sync: Return shipment cancelled by Shiprocket. Carrier: %s. AWB: %s.', 'thaaniyamhub-multi-vendor-orders' ), $courier, $awb );
            } else {
                $fulfillment_status = 'cancelled';
                $wc_status_to       = 'cancelled';
                $note               = sprintf( __( '❌ Delivery Sync: Shipment cancelled by Shiprocket. Carrier: %s. AWB: %s.', 'thaaniyamhub-multi-vendor-orders' ), $courier, $awb );
            }
        } elseif ( in_array( $status_id, [ 9, 10, 11 ], true ) || stripos( $status_str, 'rto' ) !== false || stripos( $status_str, 'returned' ) !== false ) {
            if ( $is_return ) {
                $fulfillment_status = 'rto';
                $wc_status_to       = '';
                $note               = sprintf( __( '⚠️ Return Sync: Return shipment returned back. Carrier: %s. AWB: %s.', 'thaaniyamhub-multi-vendor-orders' ), $courier, $awb );
            } else {
                $fulfillment_status = 'rto';
                $wc_status_to       = 'failed';
                $note               = sprintf( __( '⚠️ Delivery Sync: Shipment returned to origin (RTO). Reason: %s. Carrier: %s. AWB: %s.', 'thaaniyamhub-multi-vendor-orders' ), $status_str, $courier, $awb );
            }
        } elseif ( stripos( $status_str, 'out for pickup' ) !== false || stripos( $status_str, 'out_for_pickup' ) !== false || 'ROOP' === $status_code ) {
            if ( $is_return ) {
                $fulfillment_status = 'return_initiated';
                $note               = sprintf( __( '🛵 Return Sync: Return shipment is out for pickup from customer. Carrier: %s. AWB: %s.', 'thaaniyamhub-multi-vendor-orders' ), $courier, $awb );
            } else {
                $fulfillment_status = 'out_for_pickup';
                $note               = sprintf( __( '🛵 Delivery Sync: Shipment is out for pickup. Carrier: %s. AWB: %s.', 'thaaniyamhub-multi-vendor-orders' ), $courier, $awb );
            }
        } elseif ( 17 === $status_id || 'OFD' === $status_code || stripos( $status_str, 'out for delivery' ) !== false ) {
            if ( $is_return ) {
                $fulfillment_status = 'return_ofd';
                $note               = sprintf( __( '🛵 Return Sync: Return shipment is out for delivery back to vendor. Carrier: %s. AWB: %s.', 'thaaniyamhub-multi-vendor-orders' ), $courier, $awb );
            } else {
                $fulfillment_status = 'out_for_delivery';
                $note               = sprintf( __( '🛵 Delivery Sync: Shipment is out for delivery. Carrier: %s. AWB: %s.', 'thaaniyamhub-multi-vendor-orders' ), $courier, $awb );
            }
        } elseif ( 6 === $status_id || 'SHPD' === $status_code || 'IT' === $status_code || stripos( $status_str, 'shipped' ) !== false || stripos( $status_str, 'in transit' ) !== false ) {
            if ( $is_return ) {
                $fulfillment_status = 'return_picked_up';
                $note               = sprintf( __( '📦 Return Sync: Return shipment picked up from customer and is in transit. Carrier: %s. AWB: %s.', 'thaaniyamhub-multi-vendor-orders' ), $courier, $awb );
            } else {
                $fulfillment_status = 'picked_up';
                $note               = sprintf( __( '📦 Delivery Sync: Shipment picked up and is in transit. Carrier: %s. AWB: %s.', 'thaaniyamhub-multi-vendor-orders' ), $courier, $awb );
            }
        } else {
            if ( $is_return ) {
                $note = sprintf( __( 'ℹ️ Return Sync: Status updated to "%s" (%s). Carrier: %s. AWB: %s.', 'thaaniyamhub-multi-vendor-orders' ), $status_str, $status_code ?: 'N/A', $courier, $awb );
            } else {
                $note = sprintf( __( 'ℹ️ Delivery Sync: Status updated to "%s" (%s). Carrier: %s. AWB: %s.', 'thaaniyamhub-multi-vendor-orders' ), $status_str, $status_code ?: 'N/A', $courier, $awb );
            }
        }

        thaaniyamhub_log( "Tracker Sync: Mapped to local fulfillment status: '{$fulfillment_status}', target WooCommerce status: '" . ($wc_status_to ?: 'No change') . "'." );

        $last_tracking_status    = (string) $order->get_meta( '_shiprocket_last_tracking_status' );
        $last_tracking_code      = (string) $order->get_meta( '_shiprocket_last_tracking_code' );
        $last_fulfillment_status = (string) $record->fulfillment_status;
        $current_wc_status       = $order->get_status();

        $status_changed          = ( $status_str !== '' && $status_str !== $last_tracking_status );
        $fulfillment_changed     = ( $fulfillment_status !== $last_fulfillment_status );
        $wc_status_changed       = ( ! empty( $wc_status_to ) && $current_wc_status !== $wc_status_to );
        $awb_changed             = ( ! empty( $awb ) && $awb !== (string) $record->awb_code );
        $courier_changed         = ( ! empty( $courier ) && $courier !== (string) $record->courier_name );

        // If nothing changed, skip adding duplicate order notes and saving the order
        if ( ! $status_changed && ! $fulfillment_changed && ! $wc_status_changed && ! $awb_changed && ! $courier_changed ) {
            thaaniyamhub_log( "Tracker Sync: No status change detected for Sub-order #{$sub_order_id} (Status: '{$status_str}', Fulfillment: '{$fulfillment_status}'). Skipping duplicate note." );
            return true;
        }

        // 2. Update local database.
        $db_data = [
            'fulfillment_status' => $fulfillment_status,
        ];
        if ( $awb ) {
            $db_data['awb_code'] = $awb;
        }
        if ( $courier ) {
            $db_data['courier_name'] = $courier;
        }

        $wpdb->update(
            $table,
            $db_data,
            [ 'sub_order_id' => $sub_order_id ],
            null,
            [ '%d' ]
        );

        // Update tracking status metadata on the order
        if ( $status_str !== '' ) {
            $order->update_meta_data( '_shiprocket_last_tracking_status', $status_str );
        }
        if ( $status_code !== '' ) {
            $order->update_meta_data( '_shiprocket_last_tracking_code', $status_code );
        }

        if ( $is_return ) {
            if ( $awb && ! $order->get_meta( '_shiprocket_return_awb_code' ) ) {
                $order->update_meta_data( '_shiprocket_return_awb_code', $awb );
                $order->update_meta_data( '_shiprocket_return_courier_name', $courier );
            }
        } else {
            if ( $awb && ! $order->get_meta( '_shiprocket_awb_code' ) ) {
                $order->update_meta_data( '_shiprocket_awb_code', $awb );
                $order->update_meta_data( '_shiprocket_courier_name', $courier );
            }
        }

        // 3. Add order note only if there was a status change
        if ( ! empty( $note ) && ( $status_changed || $fulfillment_changed || $wc_status_changed ) ) {
            $order->add_order_note( $note );
        }

        // 4. Update WooCommerce order status if transition is set.
        if ( ! empty( $wc_status_to ) && $order->get_status() !== $wc_status_to ) {
            $order->update_status( $wc_status_to, sprintf( __( 'Auto-status sync from Shiprocket tracking (AWB: %s).', 'thaaniyamhub-multi-vendor-orders' ), $awb ) );
            thaaniyamhub_log( "Tracker Sync: WooCommerce order #{$sub_order_id} status updated to '{$wc_status_to}'" );
        }

        $order->save();
        thaaniyamhub_log( "Tracker Sync: Sub-order #{$sub_order_id} successfully saved with updated status '{$status_str}' / '{$fulfillment_status}'." );

        return true;
    }

    // =========================================================================
    // 3. BACKGROUND CRON POLLING (FAILSAFE)
    // =========================================================================

    public static function poll_tracking_status() {
        global $wpdb;
        $table = $wpdb->prefix . 'thaaniyamhub_shiprocket_fulfillment';

        $active_shipments = $wpdb->get_results(
            "SELECT sub_order_id, awb_code, shiprocket_shipment_id, shiprocket_order_id
             FROM {$table}
             WHERE fulfillment_status NOT IN ('delivered', 'cancelled', 'rto', 'returned', 'return_cancelled')
               AND awb_code IS NOT NULL AND awb_code != ''
             ORDER BY updated_at ASC
             LIMIT 40"
        );

        if ( empty( $active_shipments ) ) {
            thaaniyamhub_log( 'ThaaniyamHub_Tracker Poller: No active shipments requiring sync.' );
            return;
        }

        thaaniyamhub_log( 'ThaaniyamHub_Tracker Poller: Syncing status for ' . count( $active_shipments ) . ' active shipment(s)...' );

        $api = new ThaaniyamHub_Shiprocket_API();

        foreach ( $active_shipments as $shipment ) {
            $sub_order_id = (int) $shipment->sub_order_id;
            $awb          = $shipment->awb_code;

            thaaniyamhub_log( "ThaaniyamHub_Tracker Poller: Fetching tracking data for AWB: {$awb} (Sub-order #{$sub_order_id})" );

            $response = $api->track_awb( $awb, $sub_order_id );

            if ( is_wp_error( $response ) ) {
                thaaniyamhub_log( "ThaaniyamHub_Tracker Poller: API error for AWB {$awb} — " . $response->get_error_message() );
                continue;
            }

            if ( isset( $response['tracking_data']['track_status'] ) && (int) $response['tracking_data']['track_status'] === 1 ) {
                $shipment_data = $response['tracking_data']['shipment_track'][0] ?? [];
                if ( ! empty( $shipment_data ) ) {
                    $payload = [
                        'awb'               => $shipment_data['awb_code'] ?? $awb,
                        'shipment_id'       => $shipment_data['shipment_id'] ?? $shipment->shiprocket_shipment_id,
                        'order_id'          => $shipment->shiprocket_order_id,
                        'current_status_id' => $shipment_data['current_status_id'] ?? 0,
                        'current_status'    => $shipment_data['current_status'] ?? '',
                        'status_code'       => $shipment_data['status_code'] ?? '',
                        'courier_name'      => $shipment_data['courier_name'] ?? '',
                    ];

                    self::process_tracking_payload( $payload );
                }
            } else {
                thaaniyamhub_log( "ThaaniyamHub_Tracker Poller: No tracking scans available for AWB {$awb}." );
            }
        }
    }
}
}
