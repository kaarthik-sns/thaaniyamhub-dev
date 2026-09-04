<?php
/**
 * Thaaniyam Hub Marketplace — WCFM Cashfree Withdrawal Gateway
 *
 * Extends WCFMmp_Abstract_Gateway to integrate Cashfree Payouts (IMPS, NEFT, UPI)
 * seamlessly into WCFM Marketplace withdrawal flow.
 *
 * @package thaaniyamhub-multi-vendor-orders
 */

defined('ABSPATH') || exit;

if ( ! class_exists( 'WCFMmp_Abstract_Gateway' ) ) {
    global $WCFMmp;
    if ( isset( $WCFMmp->plugin_path ) && file_exists( $WCFMmp->plugin_path . 'core/class-wcfmmp-abstract-gateway.php' ) ) {
        require_once $WCFMmp->plugin_path . 'core/class-wcfmmp-abstract-gateway.php';
    } elseif ( defined( 'WP_PLUGIN_DIR' ) && file_exists( WP_PLUGIN_DIR . '/wc-multivendor-marketplace/core/class-wcfmmp-abstract-gateway.php' ) ) {
        require_once WP_PLUGIN_DIR . '/wc-multivendor-marketplace/core/class-wcfmmp-abstract-gateway.php';
    } elseif ( file_exists( dirname( __DIR__, 2 ) . '/wc-multivendor-marketplace/core/class-wcfmmp-abstract-gateway.php' ) ) {
        require_once dirname( __DIR__, 2 ) . '/wc-multivendor-marketplace/core/class-wcfmmp-abstract-gateway.php';
    }
}

if ( class_exists( 'WCFMmp_Abstract_Gateway' ) && ! class_exists( 'WCFMmp_Gateway_Cashfree' ) ) {

    class WCFMmp_Gateway_Cashfree extends WCFMmp_Abstract_Gateway {

        public $id;
        public $gateway_title;
        public $payment_gateway;
        public $message = [];

        public function __construct() {
            $this->id              = 'cashfree';
            $this->gateway_title   = __( 'Cashfree Payouts (Bank Transfer / UPI)', 'thaaniyamhub-multi-vendor-orders' );
            $this->payment_gateway = $this->id;
        }

        /**
         * Return gateway logo URL.
         *
         * @return string
         */
        public function gateway_logo() {
            return esc_url( 'https://cashfree.com/wp-content/themes/cashfree/assets/images/cf-logo.svg' );
        }

        /**
         * Validate payout request before processing.
         *
         * @return bool
         */
        public function validate_request() {
            $api = ThaaniyamHub_Cashfree_Payout_API::get_instance();

            if ( ! $api->is_configured() ) {
                $this->message[] = [
                    'status'  => false,
                    'message' => __( 'Cashfree Payouts is not configured by the administrator. Please contact site support.', 'thaaniyamhub-multi-vendor-orders' ),
                ];
                return false;
            }

            $vendor_payout_details = $this->get_vendor_payout_details( $this->vendor_id );

            $has_bank = ! empty( $vendor_payout_details['account_number'] ) && ! empty( $vendor_payout_details['ifsc'] );
            $has_upi  = ! empty( $vendor_payout_details['upi_id'] );

            if ( ! $has_bank && ! $has_upi ) {
                $this->message[] = [
                    'status'  => false,
                    'message' => __( 'Vendor bank account details or UPI ID is missing. Please update payout profile under Settings → Payment Setup.', 'thaaniyamhub-multi-vendor-orders' ),
                ];
                return false;
            }

            return true;
        }

        /**
         * Retrieve vendor bank / UPI payout details from user meta.
         *
         * @param int $vendor_id
         * @return array
         */
        public function get_vendor_payout_details( $vendor_id ) {
            $profile_settings = get_user_meta( $vendor_id, 'wcfmmp_profile_settings', true );
            if ( ! is_array( $profile_settings ) ) {
                $profile_settings = [];
            }

            $cashfree_meta = $profile_settings['payment']['cashfree'] ?? [];
            $bank_meta     = $profile_settings['payment']['bank'] ?? [];

            $account_name   = $cashfree_meta['ac_name'] ?? ( $bank_meta['ac_name'] ?? '' );
            $account_number = $cashfree_meta['ac_number'] ?? ( $bank_meta['ac_number'] ?? '' );
            $ifsc           = $cashfree_meta['ifsc'] ?? ( $bank_meta['ifsc'] ?? '' );
            $bank_name      = $cashfree_meta['bank_name'] ?? ( $bank_meta['bank_name'] ?? '' );
            $upi_id         = $cashfree_meta['upi_id'] ?? ( $profile_settings['payment']['upi']['vpa'] ?? '' );
            $payout_pref    = $cashfree_meta['payout_type'] ?? ( ! empty( $upi_id ) && empty( $account_number ) ? 'upi' : 'bank' );

            $user = get_userdata( $vendor_id );
            $vendor_email = $user ? $user->user_email : '';
            $vendor_phone = get_user_meta( $vendor_id, 'billing_phone', true );
            if ( empty( $vendor_phone ) && isset( $profile_settings['phone'] ) ) {
                $vendor_phone = $profile_settings['phone'];
            }

            if ( empty( $account_name ) && $user ) {
                $account_name = $user->display_name;
            }

            return [
                'payout_type'    => $payout_pref,
                'account_name'   => trim( (string) $account_name ),
                'account_number' => preg_replace( '/\s+/', '', (string) $account_number ),
                'ifsc'           => strtoupper( preg_replace( '/\s+/', '', (string) $ifsc ) ),
                'bank_name'      => trim( (string) $bank_name ),
                'upi_id'         => trim( (string) $upi_id ),
                'email'          => $vendor_email,
                'phone'          => preg_replace( '/[^0-9]/', '', (string) $vendor_phone ),
            ];
        }

        /**
         * Process withdrawal payment through Cashfree Payout API.
         *
         * @param int    $withdrawal_id
         * @param int    $vendor_id
         * @param float  $withdraw_amount
         * @param float  $withdraw_charges
         * @param string $transaction_mode
         * @return array|bool
         */
        public function process_payment( $withdrawal_id, $vendor_id, $withdraw_amount, $withdraw_charges = 0, $transaction_mode = 'auto' ) {
            global $WCFMmp, $wpdb;

            $this->withdrawal_id    = (int) $withdrawal_id;
            $this->vendor_id        = (int) $vendor_id;
            $this->withdraw_amount  = round( (float) $withdraw_amount, 2 );
            $this->transaction_mode = $transaction_mode;
            $this->currency         = get_woocommerce_currency();

            $this->message = [];

            if ( ! $this->validate_request() ) {
                return $this->message;
            }

            $details = $this->get_vendor_payout_details( $this->vendor_id );
            $api     = ThaaniyamHub_Cashfree_Payout_API::get_instance();

            // Calculate disbursal amount after charges
            $net_disbursal = max( 0.0, round( $this->withdraw_amount - (float) $withdraw_charges, 2 ) );
            if ( $net_disbursal <= 0 ) {
                return [
                    [
                        'status'  => false,
                        'message' => sprintf( __( 'Net payout amount must be greater than zero (Amount: %s, Charges: %s).', 'thaaniyamhub-multi-vendor-orders' ), $this->withdraw_amount, $withdraw_charges ),
                    ]
                ];
            }

            // Generate unique transfer ID
            $transfer_id = sprintf( 'TH_WDRW_%d_%d', $this->withdrawal_id, time() );
            $remarks     = sprintf( 'ThaaniyamHub Vendor Payout #%d', $this->withdrawal_id );

            $transfer_data = [
                'amount'     => $net_disbursal,
                'transferId' => $transfer_id,
                'name'       => $details['account_name'] ?: ( 'Vendor #' . $this->vendor_id ),
                'email'      => $details['email'] ?: 'vendor@thaaniyamhub.com',
                'phone'      => $details['phone'] ?: '9999999999',
                'remarks'    => $remarks,
            ];

            // Determine if transfer should be via UPI or Bank Account
            if ( 'upi' === $details['payout_type'] && ! empty( $details['upi_id'] ) ) {
                $transfer_data['vpa']          = $details['upi_id'];
                $transfer_data['transferMode'] = 'upi';
            } else {
                $transfer_data['bankAccount']  = $details['account_number'];
                $transfer_data['ifsc']         = $details['ifsc'];
                $transfer_data['transferMode'] = get_option( 'thaaniyamhub_cashfree_payout_transfer_mode', 'banktransfer' );
            }

            thaaniyamhub_log(
                sprintf( 'WCFMmp_Gateway_Cashfree: Initiating payout for Withdrawal #%d (Vendor #%d, Amount: ₹%s, Mode: %s)', $this->withdrawal_id, $this->vendor_id, $net_disbursal, $transfer_data['transferMode'] ),
                'info',
                'thaaniyamhub-cashfree-payout'
            );

            // Execute Direct Transfer
            $response = $api->direct_transfer( $transfer_data );

            if ( is_wp_error( $response ) ) {
                $error_msg = $response->get_error_message();
                thaaniyamhub_log( "WCFMmp_Gateway_Cashfree: Transfer failed for #{$this->withdrawal_id} — {$error_msg}", 'error', 'thaaniyamhub-cashfree-payout' );
                return [
                    [
                        'status'  => false,
                        'message' => sprintf( __( 'Cashfree Payout Failed: %s', 'thaaniyamhub-multi-vendor-orders' ), $error_msg ),
                    ]
                ];
            }

            $transfer_status = $response['transfer_status'] ?? 'PENDING';
            $reference_id    = $response['referenceId'] ?? '';
            $utr             = $response['utr'] ?? '';

            // Update WCFM Withdrawal metadata
            if ( isset( $WCFMmp->wcfmmp_withdraw ) && method_exists( $WCFMmp->wcfmmp_withdraw, 'wcfmmp_update_withdrawal_meta' ) ) {
                $WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $this->withdrawal_id, 'withdraw_amount', $this->withdraw_amount );
                $WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $this->withdrawal_id, 'currency', $this->currency );
                $WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $this->withdrawal_id, 'cashfree_transfer_id', $transfer_id );
                $WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $this->withdrawal_id, 'cashfree_reference_id', $reference_id );
                $WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $this->withdrawal_id, 'cashfree_utr', $utr );
                $WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $this->withdrawal_id, 'cashfree_status', $transfer_status );
                $WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $this->withdrawal_id, 'cashfree_transfer_mode', $transfer_data['transferMode'] );
            }

            // Synchronize with ThaaniyamHub Ledger
            $this->sync_ledger_status( $this->withdrawal_id, $this->vendor_id, $transfer_status, $reference_id, $utr );

            thaaniyamhub_log(
                sprintf( 'WCFMmp_Gateway_Cashfree: Transfer #%s successfully recorded [Status: %s, Ref: %s, UTR: %s]', $transfer_id, $transfer_status, $reference_id, $utr ),
                'info',
                'thaaniyamhub-cashfree-payout'
            );

            return [
                'status'  => true,
                'message' => sprintf(
                    __( 'Cashfree payout initiated successfully! [Transfer ID: %s | Status: %s | UTR: %s]', 'thaaniyamhub-multi-vendor-orders' ),
                    $transfer_id,
                    $transfer_status,
                    $utr ?: 'Pending Processing'
                ),
            ];
        }

        /**
         * Update ThaaniyamHub financial ledger records associated with this withdrawal.
         *
         * @param int    $withdrawal_id
         * @param int    $vendor_id
         * @param string $status 'SUCCESS', 'PENDING', etc.
         * @param string $ref_id
         * @param string $utr
         */
        private function sync_ledger_status( $withdrawal_id, $vendor_id, $status, $ref_id, $utr ) {
            global $wpdb;

            // Get commission IDs linked to this withdrawal request
            $commission_ids_str = $wpdb->get_var( $wpdb->prepare(
                "SELECT commission_ids FROM {$wpdb->prefix}wcfm_marketplace_withdraw_request WHERE ID = %d",
                $withdrawal_id
            ) );

            if ( empty( $commission_ids_str ) ) {
                return;
            }

            $commission_ids = array_filter( array_map( 'intval', explode( ',', $commission_ids_str ) ) );
            if ( empty( $commission_ids ) ) {
                return;
            }

            // Find order_ids from wcfm_marketplace_orders
            $placeholders = implode( ',', array_fill( 0, count( $commission_ids ), '%d' ) );
            $order_ids    = $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT order_id FROM {$wpdb->prefix}wcfm_marketplace_orders WHERE ID IN ($placeholders)",
                ...$commission_ids
            ) );

            if ( empty( $order_ids ) ) {
                return;
            }

            $payout_status = ( 'SUCCESS' === strtoupper( $status ) ) ? 'disbursed' : 'processing';

            $order_placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->prefix}thaaniyamhub_vendor_ledger 
                 SET payout_status = %s 
                 WHERE vendor_id = %d AND (sub_order_id IN ($order_placeholders) OR parent_order_id IN ($order_placeholders))",
                $payout_status,
                $vendor_id,
                ...array_merge( $order_ids, $order_ids )
            ) );
        }
    }
}
