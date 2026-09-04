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
        if (!class_exists('WC_Payment_Gateway')) {
            return $gateways;
        }

        if (!class_exists('WC_Cashfree_Payments') && defined('WC_CASHFREE_DIR_PATH')) {
            if (file_exists(WC_CASHFREE_DIR_PATH . 'includes/gateways/class-wc-cashfree-gateway.php')) {
                include_once WC_CASHFREE_DIR_PATH . 'includes/gateways/class-wc-cashfree-gateway.php';
            }
            if (file_exists(WC_CASHFREE_DIR_PATH . 'includes/gateways/class-wc-cashfree-payments.php')) {
                include_once WC_CASHFREE_DIR_PATH . 'includes/gateways/class-wc-cashfree-payments.php';
            }
        }

        if (!class_exists('WC_Cashfree_Payments')) {
            return $gateways;
        }

        $wrapper_file = __DIR__ . '/class-thaaniyamhub-cashfree-payments-wrapper.php';
        if (file_exists($wrapper_file)) {
            include_once $wrapper_file;
        }

        if (!class_exists('ThaaniyamHub_WC_Cashfree_Payments_Wrapper')) {
            return $gateways;
        }

        foreach ($gateways as $key => $gateway) {
            $is_cashfree = false;
            if (is_string($gateway) && 'WC_Cashfree_Payments' === $gateway) {
                $is_cashfree = true;
            } elseif (is_object($gateway) && is_a($gateway, 'WC_Cashfree_Payments') && !is_a($gateway, 'ThaaniyamHub_WC_Cashfree_Payments_Wrapper')) {
                $is_cashfree = true;
            }

            if ($is_cashfree) {
                $gateways[$key] = 'ThaaniyamHub_WC_Cashfree_Payments_Wrapper';
            }
        }

        return $gateways;
    }
}
