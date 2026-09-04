<?php
/**
 * Thaaniyam Hub Marketplace — Cashfree Refund Handler
 *
 * Wraps the Cashfree WooCommerce payment gateway process_refund method
 * so that refunds on split vendor orders target the Primary Cashfree Order ID
 * registered with Cashfree Payment Gateway during checkout.
 *
 * @package thaaniyamhub-multi-vendor-orders
 */

defined('ABSPATH') || exit;

class ThaaniyamHub_Cashfree_Refund_Handler
{
    /**
     * Initialize hooks.
     */
    public static function init()
    {
        add_filter('woocommerce_payment_gateways', [__CLASS__, 'register_gateway_wrapper'], 999);
    }

    /**
     * Intercept and wrap Cashfree gateway in WooCommerce payment gateway registry.
     *
     * @param array $gateways Registered gateway class names or instances.
     * @return array
     */
    public static function register_gateway_wrapper($gateways)
    {
        if (!class_exists('WC_Cashfree_Payments')) {
            return $gateways;
        }

        foreach ($gateways as $key => $gateway) {
            $is_cashfree = false;
            if (is_string($gateway) && 'WC_Cashfree_Payments' === $gateway) {
                $is_cashfree = true;
            } elseif (is_object($gateway) && is_a($gateway, 'WC_Cashfree_Payments')) {
                $is_cashfree = true;
            }

            if ($is_cashfree) {
                $gateways[$key] = 'ThaaniyamHub_WC_Cashfree_Payments_Wrapper';
            }
        }

        return $gateways;
    }
}

/**
 * Custom Cashfree Payments Gateway Wrapper for Multi-Vendor Split Orders.
 */
if (class_exists('WC_Cashfree_Payments')) {
    class ThaaniyamHub_WC_Cashfree_Payments_Wrapper extends WC_Cashfree_Payments
    {
        /**
         * Process a refund via Cashfree Payment Gateway.
         * Maps split vendor order ID to the Primary Cashfree Order ID.
         *
         * @param int    $order_id Order ID.
         * @param float  $amount   Refund amount.
         * @param string $reason   Refund reason.
         * @return bool|WP_Error
         */
        public function process_refund($order_id, $amount = null, $reason = '')
        {
            $order = wc_get_order($order_id);
            if (!$order) {
                return new WP_Error('error', __('Refund failed: Invalid order ID', 'woocommerce'));
            }

            $transaction_id = $order->get_transaction_id();
            if (!$transaction_id) {
                return new WP_Error('error', __('Refund failed: No transaction ID', 'woocommerce'));
            }

            // Determine the primary order ID under which the Cashfree transaction was created
            $primary_order_id = (int) $order->get_meta('_thaaniyamhub_primary_order_id');
            if (!$primary_order_id) {
                $primary_order_id = (int) $order_id;
            }

            // Generate a unique refund ID per attempt
            $refund_id = 'sub_' . $order_id . '-' . uniqid();

            try {
                $refund = $this->adapter->refund($primary_order_id, $refund_id, $amount, $reason);

                $order->add_order_note(
                    sprintf(
                        __('Cashfree Refund Processed. Refund ID: %s (Primary Order #%d)', 'thaaniyamhub-multi-vendor-orders'),
                        isset($refund->cf_refund_id) ? $refund->cf_refund_id : $refund_id,
                        $primary_order_id
                    )
                );

                do_action('woo_cashfree_refund_success', isset($refund->cf_refund_id) ? $refund->cf_refund_id : $refund_id, $order_id, $refund);

                return true;
            } catch (\Exception $e) {
                return new WP_Error(
                    'error',
                    sprintf(
                        __('Cashfree refund failed. ID: %1$s. Code: %2$s.', 'cashfree'),
                        $transaction_id,
                        $e->getMessage()
                    )
                );
            }
        }
    }
}
