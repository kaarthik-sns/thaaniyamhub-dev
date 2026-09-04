<?php
/**
 * Thaaniyam Hub Marketplace — Shiprocket API Client Wrapper
 *
 * Implements JWT authentication, caching, automatic token renewal, and robust
 * logs for all outbound HTTP requests in a failsafe custom database table.
 *
 * Stored Option for Token:
 *   thaaniyamhub_shiprocket_token
 *   thaaniyamhub_shiprocket_token_expiry
 * Stored Option for Credentials (AES Encrypted):
 *   thaaniyamhub_shiprocket_api_email
 *   thaaniyamhub_shiprocket_api_password
 *
 * @package thaaniyamhub-shiprocket-fulfillment
 */

defined('ABSPATH') || exit;

if ( ! defined( 'LOGIS_SECURE_KEY' ) ) {
    define( 'LOGIS_SECURE_KEY', defined( 'AUTH_KEY' ) ? AUTH_KEY : 'ThaaniyamHubDefaultSecureKey2025' );
}

if ( ! class_exists( 'ThaaniyamHub_Shiprocket_API' ) ) {
class ThaaniyamHub_Shiprocket_API {

    private const API_BASE = 'https://apiv2.shiprocket.in/v1/external/';
    
    // AES cipher details for credential encryption
    private const ENCRYPT_METHOD = 'AES-256-CBC';
    private const ENCRYPT_KEY_SALT = 'ThaaniyamHubLogisticsSalt2025';

    /**
     * Retrieve the JWT authentication token. If token is missing, expired,
     * or expiring in < 5 mins, automatically calls the auth/login API to refresh.
     *
     * @return string|WP_Error JWT token or WP_Error on auth failure.
     */
    public function get_token() {
        $token  = get_option( 'thaaniyamhub_shiprocket_token', '' );
        $expiry = (int) get_option( 'thaaniyamhub_shiprocket_token_expiry', 0 );

        // If token exists and has > 5 minutes of lifetime left, return it.
        if ( $token && $expiry && ( $expiry - time() ) > 300 ) {
            return $token;
        }

        // Otherwise, request a new token.
        return $this->refresh_token();
    }

    /**
     * Authenticates with Shiprocket API and updates stored token options.
     *
     * @return string|WP_Error JWT token or WP_Error.
     */
    private function refresh_token() {
        $email      = get_option( 'thaaniyamhub_shiprocket_api_email', '' );
        $enc_pass   = get_option( 'thaaniyamhub_shiprocket_api_password', '' );

        if ( empty( $email ) || empty( $enc_pass ) ) {
            return new WP_Error( 'missing_credentials', __( 'Shiprocket API Email or Password is not configured in settings.', 'thaaniyamhub-shiprocket-fulfillment' ) );
        }

        $password = self::decrypt_password( $enc_pass );
        if ( ! $password ) {
            return new WP_Error( 'decryption_failed', __( 'Failed to decrypt Shiprocket API password.', 'thaaniyamhub-shiprocket-fulfillment' ) );
        }

        $url = self::API_BASE . 'auth/login';
        $payload = [
            'email'    => $email,
            'password' => $password,
        ];

        // Perform raw POST without auth headers.
        $response = wp_remote_post( $url, [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( $payload ),
            'timeout' => 15,
        ] );

        self::log_api_call( null, 'auth/login', $payload, $response );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $code || empty( $body['token'] ) ) {
            $msg = $body['message'] ?? ( $body['errors'] ? wp_json_encode( $body['errors'] ) : 'Unknown auth error' );
            return new WP_Error( 'auth_failed', sprintf( __( 'Shiprocket Authentication Failed (Code %d): %s', 'thaaniyamhub-shiprocket-fulfillment' ), $code, $msg ) );
        }

        $new_token = sanitize_text_field( $body['token'] );
        // Expire token in 9 days (Shiprocket tokens generally last 10 days)
        $new_expiry = time() + ( 9 * DAY_IN_SECONDS );

        update_option( 'thaaniyamhub_shiprocket_token', $new_token );
        update_option( 'thaaniyamhub_shiprocket_token_expiry', $new_expiry );

        thaaniyamhub_log( 'ThaaniyamHub_Shiprocket_API: Successfully authenticated and refreshed JWT token.' );

        return $new_token;
    }

    // =========================================================================
    // SHIPROCKET ENDPOINTS
    // =========================================================================

    /**
     * Create/Push custom adhoc order to Shiprocket.
     * Endpoint: POST external/orders/create/adhoc
     *
     * @param array $payload Order details payload.
     * @param int   $sub_order_id Local sub-order ID for logger correlation.
     * @return array|WP_Error Response data or WP_Error.
     */
    public function create_order( array $payload, int $sub_order_id ) {
        return $this->request( 'POST', 'orders/create/adhoc', $payload, $sub_order_id );
    }

    /**
     * Create a return order in Shiprocket.
     * Endpoint: POST external/orders/create/return
     *
     * @param array $payload Return order details.
     * @param int   $sub_order_id Local sub-order ID.
     * @return array|WP_Error
     */
    public function create_return_order( array $payload, int $sub_order_id ) {
        return $this->request( 'POST', 'orders/create/return', $payload, $sub_order_id );
    }

    /**
     * Cancel an active order in Shiprocket.
     * Endpoint: POST external/orders/cancel
     *
     * @param array $ids List of Shiprocket order IDs to cancel.
     * @param int   $sub_order_id Local sub-order ID.
     * @return array|WP_Error
     */
    public function cancel_order( array $ids, int $sub_order_id ) {
        $payload = [ 'ids' => $ids ];
        return $this->request( 'POST', 'orders/cancel', $payload, $sub_order_id );
    }

    /**
     * Check serviceability & courier rates between origin and delivery pincodes.
     * Endpoint: GET external/courier/serviceability
     *
     * @param array $params Query parameters.
     * @return array|WP_Error
     */
    public function check_serviceability( array $params ) {
        return $this->request( 'GET', 'courier/serviceability', $params );
    }

    /**
     * Update/Patch order pickup location nickname in Shiprocket.
     * Endpoint: PATCH external/orders/address/pickup
     *
     * @param array  $order_ids List of Shiprocket order IDs.
     * @param string $pickup_nickname Nickname matching registered pickup location.
     * @param int    $sub_order_id Local sub-order ID.
     * @return array|WP_Error
     */
    public function update_order_pickup_location( array $order_ids, string $pickup_nickname, int $sub_order_id ) {
        $payload = [
            'order_id'        => $order_ids,
            'pickup_location' => $pickup_nickname,
        ];
        
        $retries = 3;
        for ($i = 0; $i < $retries; $i++) {
            $res = $this->request( 'PATCH', 'orders/address/pickup', $payload, $sub_order_id );
            if (!is_wp_error($res)) {
                return $res;
            }
            if (strpos($res->get_error_message(), 'Order Id does not exists') !== false) {
                if ($i < $retries - 1) {
                    if (function_exists('thaaniyamhub_log')) {
                        thaaniyamhub_log("Shiprocket API: Order ID is not yet indexed. Sleeping 1.5s before retry " . ($i + 1));
                    }
                    usleep(1500000); // 1.5 seconds
                    continue;
                }
            }
            return $res;
        }
        return new WP_Error('api_retry_failed', 'Failed to update order pickup location after retries');
    }

    /**
     * Request all registered pickup addresses.
     * Endpoint: GET external/settings/company/pickup
     *
     * @return array|WP_Error
     */
    public function get_pickup_addresses() {
        return $this->request( 'GET', 'settings/company/pickup' );
    }

    /**
     * Register a new pickup address in Shiprocket.
     * Endpoint: POST external/settings/company/addpickup
     *
     * @param array $params Address details.
     * @return array|WP_Error
     */
    public function add_pickup_address( array $params ) {
        return $this->request( 'POST', 'settings/company/addpickup', $params );
    }

    /**
     * Request tracking details using an AWB number.
     * Endpoint: GET external/courier/track/awb/{awb_code}
     *
     * @param string $awb AWB number.
     * @param int    $sub_order_id Local sub-order ID.
     * @return array|WP_Error
     */
    public function track_awb( string $awb, int $sub_order_id ) {
        return $this->request( 'GET', 'courier/track/awb/' . rawurlencode( $awb ), [], $sub_order_id );
    }

    /**
     * Generate AWB for a shipment.
     * Endpoint: POST external/courier/assign/awb
     *
     * @param array $payload AWB request parameters.
     * @param int   $sub_order_id Local sub-order ID.
     * @return array|WP_Error
     */
    public function assign_awb( array $payload, int $sub_order_id ) {
        return $this->request( 'POST', 'courier/assign/awb', $payload, $sub_order_id );
    }

    /**
     * Generate Pickup request for a shipment.
     * Endpoint: POST external/courier/generate/pickup
     *
     * @param array $payload Pickup details.
     * @param int   $sub_order_id Local sub-order ID.
     * @return array|WP_Error
     */
    public function request_pickup( array $payload, int $sub_order_id ) {
        return $this->request( 'POST', 'courier/generate/pickup', $payload, $sub_order_id );
    }

    /**
     * Generate Label/Manifest for a shipment.
     * Endpoint: POST external/courier/generate/label
     *
     * @param array $payload Label details.
     * @param int   $sub_order_id Local sub-order ID.
     * @return array|WP_Error
     */
    public function generate_label( array $payload, int $sub_order_id ) {
        return $this->request( 'POST', 'courier/generate/label', $payload, $sub_order_id );
    }

    /**
     * Generate Invoice for a shipment.
     * Endpoint: POST external/courier/generate/invoice
     *
     * @param array $payload Invoice details.
     * @param int   $sub_order_id Local sub-order ID.
     * @return array|WP_Error
     */
    public function generate_invoice( array $payload, int $sub_order_id ) {
        return $this->request( 'POST', 'orders/print/invoice', $payload, $sub_order_id );
    }

    /**
     * Generate Manifest for shipments.
     * Endpoint: POST external/manifests/generate
     *
     * @param array $payload Manifest details (e.g. ['shipment_id' => [12345]]).
     * @param int   $sub_order_id Local sub-order ID.
     * @return array|WP_Error
     */
    public function generate_manifest( array $payload, int $sub_order_id ) {
        return $this->request( 'POST', 'manifests/generate', $payload, $sub_order_id );
    }

    /**
     * Print Manifest for orders.
     * Endpoint: POST external/manifests/print
     *
     * @param array $payload Print details (e.g. ['order_ids' => [12345]]).
     * @param int   $sub_order_id Local sub-order ID.
     * @return array|WP_Error
     */
    public function print_manifest( array $payload, int $sub_order_id ) {
        return $this->request( 'POST', 'manifests/print', $payload, $sub_order_id );
    }

    // =========================================================================
    // CORE HTTP CLIENT
    // =========================================================================

    /**
     * Perform HTTP requests to Shiprocket API.
     *
     * @param string $method HTTP method (GET, POST, PATCH, etc.)
     * @param string $endpoint Sub-resource path relative to API base.
     * @param array  $payload Payload array (sent as JSON body for write, query string for GET).
     * @param int|null $sub_order_id Correlation suborder ID.
     * @return array|WP_Error Response or WP_Error.
     */
    private function request( string $method, string $endpoint, array $payload = [], ?int $sub_order_id = null ) {
        thaaniyamhub_log("Shiprocket API: Requesting {$method} on '{$endpoint}'" . ($sub_order_id ? " (Sub-order #{$sub_order_id})" : "") . ". Payload: " . wp_json_encode($payload));
        $token = $this->get_token();
        if ( is_wp_error( $token ) ) {
            thaaniyamhub_log("Shiprocket API Token retrieval failed: " . $token->get_error_message(), 'error');
            return $token;
        }

        $url = self::API_BASE . $endpoint;
        $args = [
            'method'  => $method,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'timeout' => 20,
        ];

        if ( 'GET' === $method ) {
            if ( ! empty( $payload ) ) {
                $url = add_query_arg( $payload, $url );
            }
        } else {
            $args['body'] = wp_json_encode( $payload );
        }

        $response = wp_remote_request( $url, $args );

        // Failsafe log in custom database table
        self::log_api_call( $sub_order_id, $endpoint, $payload, $response );

        if ( is_wp_error( $response ) ) {
            thaaniyamhub_log("Shiprocket API HTTP request failed: " . $response->get_error_message(), 'error');
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body_raw = wp_remote_retrieve_body( $response );
        $body = json_decode( $body_raw, true );
        thaaniyamhub_log("Shiprocket API Response Status: {$code}. Raw Response: {$body_raw}");

        if ( $code >= 400 ) {
            $msg = $body['message'] ?? ( $body['errors'] ? wp_json_encode( $body['errors'] ) : 'HTTP error code ' . $code );
            thaaniyamhub_log("Shiprocket API returned error code {$code}: {$msg}", 'error');
            return new WP_Error( 'api_error_' . $code, $msg );
        }

        return $body;
    }

    // =========================================================================
    // SYSTEM DATABASE OPERATIONS & METADATA
    // =========================================================================

    /**
     * Retrieve local shipment record.
     */
    public static function get_fulfillment( int $sub_order_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'thaaniyamhub_shiprocket_fulfillment';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE sub_order_id = %d LIMIT 1", $sub_order_id ) );
    }

    /**
     * Save local shipment record.
     */
    public static function save_fulfillment( int $sub_order_id, int $vendor_id, string $sr_order_id, string $sr_shipment_id, string $pickup_name ) {
        global $wpdb;
        $table = $wpdb->prefix . 'thaaniyamhub_shiprocket_fulfillment';

        $wpdb->replace(
            $table,
            [
                'sub_order_id'             => $sub_order_id,
                'vendor_id'                => $vendor_id,
                'shiprocket_order_id'      => $sr_order_id,
                'shiprocket_shipment_id'   => $sr_shipment_id,
                'pickup_location_nickname' => $pickup_name,
                'fulfillment_status'       => 'dispatched',
            ],
            [ '%d', '%d', '%s', '%s', '%s', '%s' ]
        );
    }

    /**
     * Update local shipment status and metadata.
     */
    public static function update_status( int $sub_order_id, string $status, array $extra_fields = [] ) {
        global $wpdb;
        $table = $wpdb->prefix . 'thaaniyamhub_shiprocket_fulfillment';

        $data = array_merge( [ 'fulfillment_status' => $status ], $extra_fields );
        $wpdb->update( $table, $data, [ 'sub_order_id' => $sub_order_id ] );
    }

    /**
     * Logs raw request/response details for failsafe debugging.
     */
    private static function log_api_call( ?int $sub_order_id, string $endpoint, array $payload, $response ) {
        if ( ! empty( $GLOBALS['thaaniyamhub_in_shipping_calculation'] ) ) {
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'thaaniyamhub_shiprocket_api_logs';

        $code = 0;
        $body = '';

        if ( is_wp_error( $response ) ) {
            $body = 'WP_Error: ' . $response->get_error_message();
        } else {
            $code = wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );
        }

        // Truncate payload if extremely long to protect DB overhead
        $payload_str = wp_json_encode( $payload );
        if ( strlen( $payload_str ) > 10000 ) {
            $payload_str = substr( $payload_str, 0, 10000 ) . '...[TRUNCATED]';
        }
        if ( strlen( $body ) > 15000 ) {
            $body = substr( $body, 0, 15000 ) . '...[TRUNCATED]';
        }

        $wpdb->insert(
            $table,
            [
                'sub_order_id'        => $sub_order_id,
                'endpoint_requested'  => sanitize_text_field( $endpoint ),
                'payload_sent'        => $payload_str,
                'payload_received'    => $body,
                'http_status_code'    => $code,
            ],
            [ '%d', '%s', '%s', '%s', '%d' ]
        );
    }

    // =========================================================================
    // ENCRYPTION/DECRYPTION (AES-256-CBC)
    // =========================================================================

    public static function encrypt_password( string $password ): string {
        $key = hash( 'sha256', LOGIS_SECURE_KEY . self::ENCRYPT_KEY_SALT );
        $iv  = substr( hash( 'sha256', LOGIS_SECURE_KEY ), 0, 16 );
        
        $encrypted = openssl_encrypt( $password, self::ENCRYPT_METHOD, $key, 0, $iv );
        return base64_encode( $encrypted );
    }

    public static function decrypt_password( string $encrypted_pass ): string {
        $key = hash( 'sha256', LOGIS_SECURE_KEY . self::ENCRYPT_KEY_SALT );
        $iv  = substr( hash( 'sha256', LOGIS_SECURE_KEY ), 0, 16 );

        $data = base64_decode( $encrypted_pass );
        $decrypted = openssl_decrypt( $data, self::ENCRYPT_METHOD, $key, 0, $iv );
        return (string) $decrypted;
    }
}
}
