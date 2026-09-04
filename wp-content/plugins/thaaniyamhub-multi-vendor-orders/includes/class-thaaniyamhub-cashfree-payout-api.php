<?php
/**
 * Thaaniyam Hub Marketplace — Cashfree Payouts API Client (v2)
 *
 * Handles authentication, instant transfers (IMPS/NEFT/UPI),
 * status inquiries, and wallet balance checks via Cashfree Payouts v2 API.
 *
 * @package thaaniyamhub-multi-vendor-orders
 */

defined('ABSPATH') || exit;

class ThaaniyamHub_Cashfree_Payout_API {

    private const SANDBOX_URL = 'https://sandbox.cashfree.com/payout';
    private const PROD_URL    = 'https://api.cashfree.com/payout';

    private const V1_SANDBOX_URL = 'https://payout-gamma.cashfree.com/payout/v1';
    private const V1_PROD_URL    = 'https://payout-api.cashfree.com/payout/v1';

    private const API_VERSION = '2024-01-01';
    private const TOKEN_TRANSIENT_KEY = 'thaaniyamhub_cf_payout_token';

    /**
     * Singleton instance.
     *
     * @var ThaaniyamHub_Cashfree_Payout_API|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return ThaaniyamHub_Cashfree_Payout_API
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get the active environment mode ('sandbox' or 'production').
     *
     * @return string
     */
    public function get_environment() {
        return get_option( 'thaaniyamhub_cashfree_payout_env', 'sandbox' );
    }

    /**
     * Check if sandbox mode is active.
     *
     * @return bool
     */
    public function is_sandbox() {
        return 'sandbox' === $this->get_environment();
    }

    /**
     * Get the v2 API base URL based on active environment.
     *
     * @return string
     */
    public function get_api_base_url() {
        return $this->is_sandbox() ? self::SANDBOX_URL : self::PROD_URL;
    }

    /**
     * Get configured Client ID.
     *
     * @return string
     */
    public function get_client_id() {
        return trim( (string) get_option( 'thaaniyamhub_cashfree_payout_client_id', '' ) );
    }

    /**
     * Get configured Client Secret.
     *
     * @return string
     */
    public function get_client_secret() {
        return trim( (string) get_option( 'thaaniyamhub_cashfree_payout_client_secret', '' ) );
    }

    /**
     * Get configured Webhook Secret.
     *
     * @return string
     */
    public function get_webhook_secret() {
        return trim( (string) get_option( 'thaaniyamhub_cashfree_payout_webhook_secret', '' ) );
    }

    /**
     * Check if Cashfree Payout API credentials are configured.
     *
     * @return bool
     */
    public function is_configured() {
        $client_id     = $this->get_client_id();
        $client_secret = $this->get_client_secret();
        return ! empty( $client_id ) && ! empty( $client_secret );
    }

    /**
     * Build standard headers for Cashfree v2 API requests.
     *
     * @param string $client_id
     * @param string $client_secret
     * @return array
     */
    public function get_v2_headers( $client_id = '', $client_secret = '' ) {
        $client_id     = ! empty( $client_id ) ? trim( (string) $client_id ) : $this->get_client_id();
        $client_secret = ! empty( $client_secret ) ? trim( (string) $client_secret ) : $this->get_client_secret();

        return [
            'x-api-version'   => self::API_VERSION,
            'x-client-id'     => $client_id,
            'x-client-secret' => $client_secret,
            'Content-Type'    => 'application/json',
            'Accept'          => 'application/json',
        ];
    }

    /**
     * Make an authenticated HTTP request to Cashfree Payouts v2 API.
     *
     * @param string $method GET, POST, etc.
     * @param string $endpoint e.g. '/transfers'
     * @param array  $payload Request body
     * @param string $client_id
     * @param string $client_secret
     * @param string $env
     * @return array|WP_Error Decoded response array or WP_Error.
     */
    public function request( $method, $endpoint, $payload = [], $client_id = '', $client_secret = '', $env = '' ) {
        $client_id     = ! empty( $client_id ) ? trim( (string) $client_id ) : $this->get_client_id();
        $client_secret = ! empty( $client_secret ) ? trim( (string) $client_secret ) : $this->get_client_secret();
        $env_to_use    = ! empty( $env ) ? $env : $this->get_environment();

        if ( empty( $client_id ) || empty( $client_secret ) ) {
            return new WP_Error(
                'cashfree_not_configured',
                __( 'Cashfree Payout Client ID and Client Secret are required.', 'thaaniyamhub-multi-vendor-orders' )
            );
        }

        $base_url = ( 'production' === $env_to_use ) ? self::PROD_URL : self::SANDBOX_URL;
        $url      = $base_url . $endpoint;
        $headers  = $this->get_v2_headers( $client_id, $client_secret );

        $args = [
            'method'    => strtoupper( $method ),
            'headers'   => $headers,
            'timeout'   => 45,
            'sslverify' => true,
        ];

        if ( ! empty( $payload ) && in_array( strtoupper( $method ), [ 'POST', 'PUT', 'PATCH' ], true ) ) {
            $args['body'] = wp_json_encode( $payload );
        }

        thaaniyamhub_log(
            sprintf( 'Cashfree_Payout_API (v2): %s %s | Payload: %s', $method, $endpoint, ! empty( $payload ) ? wp_json_encode( $payload ) : 'None' ),
            'info',
            'thaaniyamhub-cashfree-payout'
        );

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            thaaniyamhub_log( "Cashfree_Payout_API HTTP Request Error on {$endpoint}: " . $response->get_error_message(), 'error', 'thaaniyamhub-cashfree-payout' );
            return $response;
        }

        $code     = wp_remote_retrieve_response_code( $response );
        $raw_body = wp_remote_retrieve_body( $response );
        $body     = json_decode( $raw_body, true );

        thaaniyamhub_log(
            sprintf( 'Cashfree_Payout_API (v2): Response from %s [HTTP %d]: %s', $endpoint, $code, $raw_body ),
            ( $code >= 200 && $code < 300 ) ? 'info' : 'warning',
            'thaaniyamhub-cashfree-payout'
        );

        return [
            'http_code' => $code,
            'body'      => is_array( $body ) ? $body : [],
            'raw'       => $raw_body,
        ];
    }

    /**
     * Retrieve Bearer Auth Token from Cashfree Payouts (v1 fallback for balance).
     *
     * @param bool   $force_refresh
     * @param string $client_id
     * @param string $client_secret
     * @param string $env
     * @return string|WP_Error
     */
    public function get_auth_token( $force_refresh = false, $client_id = '', $client_secret = '', $env = '' ) {
        $client_id     = ! empty( $client_id ) ? trim( (string) $client_id ) : $this->get_client_id();
        $client_secret = ! empty( $client_secret ) ? trim( (string) $client_secret ) : $this->get_client_secret();
        $env           = ! empty( $env ) ? trim( (string) $env ) : $this->get_environment();

        if ( empty( $client_id ) || empty( $client_secret ) ) {
            return new WP_Error(
                'cashfree_not_configured',
                __( 'Cashfree Payout Client ID and Client Secret are required.', 'thaaniyamhub-multi-vendor-orders' )
            );
        }

        $transient_key = self::TOKEN_TRANSIENT_KEY . '_' . md5( $client_id . '_' . $env );

        if ( ! $force_refresh ) {
            $cached_token = get_transient( $transient_key );
            if ( ! empty( $cached_token ) ) {
                return $cached_token;
            }
        }

        $base_url = ( 'production' === $env ) ? self::V1_PROD_URL : self::V1_SANDBOX_URL;
        $url      = $base_url . '/authorize';
        $headers  = [
            'X-Client-Id'     => $client_id,
            'X-Client-Secret' => $client_secret,
            'Content-Type'    => 'application/json',
            'Accept'          => 'application/json',
        ];

        $response = wp_remote_post( $url, [
            'headers'   => $headers,
            'timeout'   => 30,
            'sslverify' => true,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code     = wp_remote_retrieve_response_code( $response );
        $raw_body = wp_remote_retrieve_body( $response );
        $body     = json_decode( $raw_body, true );

        if ( 200 !== $code || empty( $body ) || 'SUCCESS' !== ( $body['status'] ?? '' ) ) {
            $err_msg = $body['message'] ?? ( 'Authentication failed with HTTP code ' . $code );
            $subcode = $body['subCode'] ?? $code;
            return new WP_Error( 'cashfree_auth_failed', $err_msg, [ 'subcode' => $subcode, 'body' => $body ] );
        }

        $token  = $body['data']['token'] ?? '';
        $expiry = (int) ( $body['data']['expiry'] ?? ( time() + 86400 ) );

        if ( empty( $token ) ) {
            return new WP_Error( 'cashfree_empty_token', __( 'Cashfree returned an empty authorization token.', 'thaaniyamhub-multi-vendor-orders' ) );
        }

        $ttl = max( 300, ( $expiry - time() ) - 300 );
        set_transient( $transient_key, $token, $ttl );

        return $token;
    }

    /**
     * Get Cashfree Payouts Account Wallet Balance.
     *
     * @param string $client_id
     * @param string $client_secret
     * @param string $env
     * @return array|WP_Error
     */
    public function get_balance( $client_id = '', $client_secret = '', $env = '' ) {
        $token = $this->get_auth_token( false, $client_id, $client_secret, $env );
        if ( is_wp_error( $token ) ) {
            return $token;
        }

        $env_to_use = ! empty( $env ) ? $env : $this->get_environment();
        $base_url   = ( 'production' === $env_to_use ) ? self::V1_PROD_URL : self::V1_SANDBOX_URL;
        $url        = $base_url . '/getBalance';

        $response = wp_remote_get( $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $raw_body = wp_remote_retrieve_body( $response );
        $body     = json_decode( $raw_body, true );

        if ( 'SUCCESS' === ( $body['status'] ?? '' ) && isset( $body['data'] ) ) {
            return [
                'status'            => true,
                'balance'           => (float) ( $body['data']['balance'] ?? 0.0 ),
                'available_balance' => (float) ( $body['data']['availableBalance'] ?? 0.0 ),
                'message'           => $body['message'] ?? '',
            ];
        }

        return new WP_Error(
            'cashfree_balance_error',
            $body['message'] ?? __( 'Failed to fetch Cashfree wallet balance.', 'thaaniyamhub-multi-vendor-orders' ),
            $body
        );
    }

    /**
     * Request a Payout Transfer via Cashfree v2 Transfers API (`POST /transfers`).
     * Supports both Bank Accounts (IMPS / NEFT) and UPI VPAs.
     *
     * @param array $transfer_data
     *   - amount (float|string, required)
     *   - transferId (string, required)
     *   - name (string, required)
     *   - email (string, optional)
     *   - phone (string, optional)
     *   - bankAccount (string) & ifsc (string) OR vpa (string)
     *   - transferMode (string: 'banktransfer', 'imps', 'neft', 'upi')
     *   - remarks (string, optional)
     *   - beneId (string, optional)
     * @return array|WP_Error
     */
    public function direct_transfer( array $transfer_data ) {
        if ( empty( $transfer_data['amount'] ) || empty( $transfer_data['transferId'] ) || empty( $transfer_data['name'] ) ) {
            return new WP_Error( 'invalid_direct_transfer_params', __( 'Amount, transferId, and Name are required.', 'thaaniyamhub-multi-vendor-orders' ) );
        }

        $amount = round( (float) $transfer_data['amount'], 2 );
        if ( $amount <= 0 ) {
            return new WP_Error( 'invalid_transfer_amount', __( 'Payout amount must be greater than zero.', 'thaaniyamhub-multi-vendor-orders' ) );
        }

        $has_bank = ! empty( $transfer_data['bankAccount'] ) && ! empty( $transfer_data['ifsc'] );
        $has_upi  = ! empty( $transfer_data['vpa'] );
        $bene_id  = ! empty( $transfer_data['beneId'] ) ? sanitize_text_field( $transfer_data['beneId'] ) : '';

        if ( ! $has_bank && ! $has_upi && empty( $bene_id ) ) {
            return new WP_Error( 'missing_destination', __( 'Transfer requires Bank Account + IFSC, UPI VPA, or Beneficiary ID.', 'thaaniyamhub-multi-vendor-orders' ) );
        }

        $transfer_mode = $transfer_data['transferMode'] ?? ( $has_upi ? 'upi' : 'banktransfer' );
        if ( empty( $transfer_mode ) ) {
            $transfer_mode = 'banktransfer';
        }

        $payload = [
            'transfer_id'         => sanitize_text_field( $transfer_data['transferId'] ),
            'transfer_amount'     => (float) number_format( $amount, 2, '.', '' ),
            'transfer_currency'   => 'INR',
            'transfer_mode'       => strtolower( sanitize_text_field( $transfer_mode ) ),
            'remarks'             => sanitize_text_field( $transfer_data['remarks'] ?? 'ThaaniyamHub Vendor Payout' ),
            'beneficiary_details' => [
                'beneficiary_name'  => sanitize_text_field( $transfer_data['name'] ),
                'beneficiary_email' => sanitize_email( $transfer_data['email'] ?? 'vendor@thaaniyamhub.com' ),
                'beneficiary_phone' => preg_replace( '/[^0-9]/', '', $transfer_data['phone'] ?? '9999999999' ),
            ],
        ];

        if ( ! empty( $bene_id ) && ! $has_bank && ! $has_upi ) {
            $payload['beneficiary_details']['beneficiary_id'] = $bene_id;
        } elseif ( $has_bank ) {
            $payload['beneficiary_details']['beneficiary_instrument_details'] = [
                'bank_account_number' => preg_replace( '/\s+/', '', sanitize_text_field( $transfer_data['bankAccount'] ) ),
                'bank_ifsc'           => strtoupper( preg_replace( '/\s+/', '', sanitize_text_field( $transfer_data['ifsc'] ) ) ),
            ];
        } elseif ( $has_upi ) {
            $payload['beneficiary_details']['beneficiary_instrument_details'] = [
                'vpa' => sanitize_text_field( $transfer_data['vpa'] ),
            ];
            $payload['transfer_mode'] = 'upi';
        }

        $res = $this->request( 'POST', '/transfers', $payload );
        if ( is_wp_error( $res ) ) {
            return $res;
        }

        $code = (int) ( $res['http_code'] ?? 0 );
        $body = $res['body'] ?? [];

        $status = strtoupper( (string) ( $body['status'] ?? ( $body['status_code'] ?? '' ) ) );

        // Cashfree v2 returns 200/201 with status RECEIVED, PENDING, or SUCCESS
        if ( in_array( $status, [ 'RECEIVED', 'PENDING', 'SUCCESS' ], true ) || ( 200 === $code && ! empty( $body['transfer_id'] ) ) ) {
            return [
                'status'          => true,
                'transfer_status' => ( 'SUCCESS' === $status ) ? 'SUCCESS' : 'PENDING',
                'transferId'      => $body['transfer_id'] ?? $transfer_data['transferId'],
                'referenceId'     => (string) ( $body['cf_transfer_id'] ?? ( $body['referenceId'] ?? '' ) ),
                'utr'             => (string) ( $body['utr'] ?? '' ),
                'acknowledged'    => 1,
                'message'         => $body['status_description'] ?? ( $body['message'] ?? __( 'Transfer request submitted successfully.', 'thaaniyamhub-multi-vendor-orders' ) ),
                'raw'             => $body,
            ];
        }

        $err_message = $body['message'] ?? ( $body['status_description'] ?? ( $body['error_description'] ?? __( 'Cashfree transfer request failed.', 'thaaniyamhub-multi-vendor-orders' ) ) );
        $subcode     = $body['subCode'] ?? ( $body['code'] ?? $code );

        return new WP_Error(
            'cashfree_transfer_failed',
            $err_message,
            [ 'subcode' => $subcode, 'body' => $body ]
        );
    }

    /**
     * Legacy wrapper for request_transfer.
     *
     * @param array $transfer_data
     * @return array|WP_Error
     */
    public function request_transfer( array $transfer_data ) {
        return $this->direct_transfer( $transfer_data );
    }

    /**
     * Query Real-Time Status of a Transfer via Cashfree v2 (`GET /transfers?transfer_id={id}`).
     *
     * @param string $transfer_id
     * @param string $reference_id
     * @return array|WP_Error
     */
    public function get_transfer_status( string $transfer_id = '', string $reference_id = '' ) {
        if ( empty( $transfer_id ) && empty( $reference_id ) ) {
            return new WP_Error( 'missing_transfer_identifier', __( 'transferId or referenceId is required to query transfer status.', 'thaaniyamhub-multi-vendor-orders' ) );
        }

        $query = [];
        if ( ! empty( $transfer_id ) ) {
            $query['transfer_id'] = $transfer_id;
        } elseif ( ! empty( $reference_id ) ) {
            $query['cf_transfer_id'] = $reference_id;
        }

        $endpoint = '/transfers?' . http_build_query( $query );
        $res = $this->request( 'GET', $endpoint );
        if ( is_wp_error( $res ) ) {
            return $res;
        }

        $code = (int) ( $res['http_code'] ?? 0 );
        $body = $res['body'] ?? [];

        if ( 200 === $code && ( ! empty( $body['transfer_id'] ) || ! empty( $body['cf_transfer_id'] ) ) ) {
            $transfer_status = strtoupper( (string) ( $body['status'] ?? ( $body['status_code'] ?? 'PENDING' ) ) );
            return [
                'status'          => true,
                'transfer_status' => $transfer_status,
                'transferId'      => $body['transfer_id'] ?? $transfer_id,
                'referenceId'     => (string) ( $body['cf_transfer_id'] ?? $reference_id ),
                'utr'             => (string) ( $body['utr'] ?? '' ),
                'amount'          => (float) ( $body['transfer_amount'] ?? 0.0 ),
                'fee'             => (float) ( $body['transfer_service_charge'] ?? 0.0 ),
                'transferMode'    => $body['transfer_mode'] ?? '',
                'addedOn'         => $body['added_on'] ?? '',
                'updatedOn'       => $body['updated_on'] ?? '',
                'reason'          => $body['status_description'] ?? '',
                'raw'             => $body,
            ];
        }

        return new WP_Error(
            'get_transfer_status_failed',
            $body['message'] ?? ( $body['status_description'] ?? __( 'Failed to retrieve transfer status.', 'thaaniyamhub-multi-vendor-orders' ) ),
            $body
        );
    }

    /**
     * Verify Webhook Signature.
     * Cashfree v2 signs "$timestamp . $raw_body" using the Client Secret or Webhook Secret.
     *
     * @param string $raw_body
     * @param string $signature
     * @param string $timestamp
     * @return bool
     */
    public function verify_webhook_signature( string $raw_body, string $signature, string $timestamp = '' ) {
        $secret = $this->get_webhook_secret();
        if ( empty( $secret ) ) {
            $secret = $this->get_client_secret();
        }

        // If no secret configured at all, skip signature verification in development/sandbox
        if ( empty( $secret ) ) {
            thaaniyamhub_log( 'Cashfree_Payout_API: No Webhook or Client Secret configured. Skipping signature verification.', 'warning', 'thaaniyamhub-cashfree-payout' );
            return true;
        }

        if ( empty( $signature ) ) {
            thaaniyamhub_log( 'Cashfree_Payout_API: Webhook request missing signature header.', 'warning', 'thaaniyamhub-cashfree-payout' );
            return false;
        }

        $secrets_to_try = array_unique( array_filter( [ $this->get_webhook_secret(), $this->get_client_secret() ] ) );

        foreach ( $secrets_to_try as $sec ) {
            // 1. Cashfree v2 standard: base64(hmac_sha256(timestamp . raw_body, secret))
            if ( ! empty( $timestamp ) ) {
                $computed = base64_encode( hash_hmac( 'sha256', $timestamp . $raw_body, $sec, true ) );
                if ( hash_equals( $computed, $signature ) ) {
                    return true;
                }
                // Hex format check
                $computed_hex = hash_hmac( 'sha256', $timestamp . $raw_body, $sec );
                if ( hash_equals( $computed_hex, $signature ) ) {
                    return true;
                }
            }

            // 2. Fallback: base64(hmac_sha256(raw_body, secret))
            $computed_raw = base64_encode( hash_hmac( 'sha256', $raw_body, $sec, true ) );
            if ( hash_equals( $computed_raw, $signature ) ) {
                return true;
            }

            // 3. Fallback: hex(hmac_sha256(raw_body, secret))
            $computed_raw_hex = hash_hmac( 'sha256', $raw_body, $sec );
            if ( hash_equals( $computed_raw_hex, $signature ) ) {
                return true;
            }
        }

        thaaniyamhub_log( 'Cashfree_Payout_API: Webhook signature mismatch.', 'error', 'thaaniyamhub-cashfree-payout' );
        return false;
    }
}
