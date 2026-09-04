<?php
/**
 * Thaaniyam Hub Marketplace — Vendor Label & Order Item Handler
 *
 * Appends vendor store name cleanly under product titles in order items
 * (emails, invoices, and order details).
 *
 * @package thaaniyamhub-multi-vendor-orders
 */

defined('ABSPATH') || exit;

class ThaaniyamHub_Order_ID_Handler
{
    public static function init()
    {
        // Allow re-sending new order emails for testing & manual admin resend actions
        add_filter('woocommerce_new_order_email_allows_resend', '__return_false');

        // Append Vendor Name under Product Name in Order Item tables (emails and order details)
        add_filter('woocommerce_order_item_name', [__CLASS__, 'append_vendor_name_to_order_item_name'], 10, 3);
    }

    /**
     * Append Vendor Name under Product Name in WooCommerce Email and Order Item Tables.
     *
     * @param string $item_name
     * @param WC_Order_Item $item
     * @param bool $is_visible
     * @return string
     */
    public static function append_vendor_name_to_order_item_name($item_name, $item, $is_visible = true)
    {
        if (!is_object($item) || !method_exists($item, 'get_product')) {
            return $item_name;
        }

        // Avoid duplicating if template already added Vendor: tag
        if (strpos($item_name, 'Vendor:') !== false || strpos($item_name, 'Sold by:') !== false) {
            return $item_name;
        }

        $_product = $item->get_product();
        if ($_product) {
            $product_id = $_product->get_id();
            $vendor_id = 0;
            if (method_exists($item, 'get_meta') && $item->get_meta('_vendor_id')) {
                $vendor_id = (int) $item->get_meta('_vendor_id');
            }
            if (!$vendor_id && function_exists('wcfm_get_vendor_id_by_post')) {
                $vendor_id = (int) wcfm_get_vendor_id_by_post($product_id);
            }
            if (!$vendor_id) {
                $vendor_id = (int) get_post_field('post_author', $product_id);
            }

            if ($vendor_id) {
                $vendor_name = '';
                if (function_exists('wcfm_get_vendor_store_name')) {
                    $vendor_name = wcfm_get_vendor_store_name($vendor_id);
                }
                if (!$vendor_name && function_exists('thaaniyamhub_get_vendor_name_by_vendor_id')) {
                    $vendor_name = thaaniyamhub_get_vendor_name_by_vendor_id($vendor_id);
                }

                if ($vendor_name) {
                    $item_name .= '<br/><span style="font-size:11px; color:#1E4D2B; font-weight:700; font-family:Arial,Helvetica,sans-serif;">Vendor: ' . esc_html($vendor_name) . '</span>';
                }
            }
        }

        return $item_name;
    }
}

