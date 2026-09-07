<?php
/**
 * Thaaniyam Hub Marketplace — Comprehensive Order Lifecycle & Duplication Debug Logger
 *
 * Captures in-depth telemetry for order creation, checkout submissions, payment gateway
 * callbacks, and status transitions to pinpoint duplicate order triggers.
 *
 * Writes to:
 *   1. wp-content/thaaniyamhub-order-debug.log (Direct atomic append with file lock)
 *   2. WooCommerce Logger (source: 'thaaniyamhub-order-debug')
 *
 * @package thaaniyamhub-multi-vendor-orders
 */

defined('ABSPATH') || exit;

class ThaaniyamHub_Order_Debug_Logger
{
    /**
     * Unique request identifier for correlating logs within the same PHP lifecycle.
     *
     * @var string
     */
    private static $request_id = '';

    /**
     * Request start timestamp with microseconds.
     *
     * @var float
     */
    private static $start_time = 0.0;

    /**
     * Filepath for the direct text debug log.
     *
     * @var string
     */
    private static $log_file = '';

    /**
     * Initialise hooks and setup request context.
     */
    public static function init()
    {
        self::$start_time = microtime(true);
        self::$request_id = 'REQ-' . substr(md5(uniqid((string) mt_rand(), true)), 0, 8);
        self::$log_file   = WP_CONTENT_DIR . '/thaaniyamhub-order-debug.log';

        // 1. Order Birth: Fires whenever ANY order is created across entire WooCommerce
        add_action('woocommerce_new_order', [__CLASS__, 'on_new_order'], 1, 2);

        // 2. Checkout Lifecycle Hooks
        add_action('woocommerce_before_checkout_process', [__CLASS__, 'on_before_checkout_process'], 1);
        add_action('woocommerce_checkout_order_processed', [__CLASS__, 'on_checkout_order_processed_early'], 1, 3);
        add_action('woocommerce_checkout_order_processed', [__CLASS__, 'on_checkout_order_processed_late'], 99, 3);
        add_action('woocommerce_store_api_checkout_order_processed', [__CLASS__, 'on_store_api_order_processed'], 1, 1);

        // 3. Payment & Status Lifecycle Hooks
        add_action('woocommerce_payment_complete', [__CLASS__, 'on_payment_complete'], 1, 1);
        add_action('woocommerce_order_status_changed', [__CLASS__, 'on_order_status_changed'], 1, 4);

        // 4. Order Received (Thank-you Page)
        add_action('woocommerce_thankyou', [__CLASS__, 'on_thankyou'], 1, 1);

        // 5. Payment Gateway Webhooks & Redirects
        add_action('wp_loaded', [__CLASS__, 'intercept_gateway_callbacks'], 1);

        // 6. Client-Side Click & Double-Submit Tracking Script on Checkout
        add_action('wp_footer', [__CLASS__, 'inject_checkout_click_logger'], 99);
    }

    /**
     * Main logging driver: writes cleanly formatted entry to file and WC logger.
     *
     * @param string $category
     * @param string $title
     * @param array  $data
     * @param bool   $include_trace
     * @param int    $trace_limit
     */
    public static function log($category, $title, $data = [], $include_trace = false, $trace_limit = 12)
    {
        $now = microtime(true);
        $elapsed = sprintf('%.4fs', $now - self::$start_time);
        $date_str = date('Y-m-d H:i:s') . '.' . sprintf('%03d', ($now - floor($now)) * 1000);

        $lines = [];
        $lines[] = '================================================================================';
        $lines[] = sprintf('[%s] [%s] [+%s] [%s] %s', $date_str, self::$request_id, $elapsed, strtoupper($category), $title);
        $lines[] = '--------------------------------------------------------------------------------';

        // HTTP Context
        $lines[] = 'REQUEST CONTEXT:';
        $lines[] = '  - Method: ' . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN');
        $lines[] = '  - URI: ' . ($_SERVER['REQUEST_URI'] ?? 'UNKNOWN');
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $lines[] = '  - Referer: ' . $_SERVER['HTTP_REFERER'];
        }
        $lines[] = '  - Remote IP: ' . self::get_client_ip();
        $lines[] = '  - User Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'None');

        $req_type = [];
        if (wp_doing_ajax()) {
            $req_type[] = 'AJAX';
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            $req_type[] = 'REST';
        }
        if (wp_doing_cron()) {
            $req_type[] = 'CRON';
        }
        if (is_admin()) {
            $req_type[] = 'ADMIN';
        }
        $lines[] = '  - Type: ' . (!empty($req_type) ? implode(' | ', $req_type) : 'STANDARD_PAGE');

        // Customer & Session Context
        $lines[] = '  - Current User: #' . get_current_user_id();
        if (isset(WC()->session)) {
            $lines[] = '  - WC Session Active: YES';
            $awaiting = WC()->session->get('order_awaiting_payment');
            if ($awaiting) {
                $lines[] = '  - Session order_awaiting_payment: #' . $awaiting;
            }
        }

        // Custom Click/Submission Token from frontend
        if (!empty($_POST['_th_click_count'])) {
            $lines[] = '  - [CLIENT] Button Click Count: ' . intval($_POST['_th_click_count']);
        }
        if (!empty($_POST['_th_client_uuid'])) {
            $lines[] = '  - [CLIENT] Checkout Token: ' . sanitize_text_field($_POST['_th_client_uuid']);
        }

        // Payload data
        if (!empty($data)) {
            $lines[] = 'DATA:';
            foreach ($data as $key => $val) {
                if (is_array($val) || is_object($val)) {
                    $val_str = print_r($val, true);
                    $val_lines = explode("\n", trim($val_str));
                    $lines[] = '  - ' . $key . ':';
                    foreach ($val_lines as $vl) {
                        $lines[] = '      ' . $vl;
                    }
                } else {
                    $lines[] = '  - ' . $key . ': ' . (string) $val;
                }
            }
        }

        // Stack trace if requested
        if ($include_trace) {
            $lines[] = 'CALL STACK (BACKTRACE):';
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $trace_limit + 3);
            // Skip the current logger calls
            $trace = array_slice($trace, 2, $trace_limit);
            $idx = 0;
            foreach ($trace as $t) {
                $file = isset($t['file']) ? wp_normalize_path($t['file']) : '[internal]';
                // Shorten common path prefixes for readability
                $file = str_replace(wp_normalize_path(ABSPATH), '', $file);
                $line = $t['line'] ?? '?';
                $func = (isset($t['class']) ? $t['class'] . $t['type'] : '') . ($t['function'] ?? '');
                $lines[] = sprintf('    #%d %s:%s -> %s()', $idx++, $file, $line, $func);
            }
        }

        $lines[] = '================================================================================' . "\n";
        $entry = implode("\n", $lines);

        // 1. Direct file write (atomic with LOCK_EX)
        if (!empty(self::$log_file)) {
            @file_put_contents(self::$log_file, $entry, FILE_APPEND | LOCK_EX);
        }

        // 2. WooCommerce Logger write
        if (function_exists('wc_get_logger')) {
            try {
                $logger = wc_get_logger();
                $logger->info(sprintf('[%s] %s | %s', self::$request_id, $title, wp_json_encode($data)), ['source' => 'thaaniyamhub-order-debug']);
            } catch (\Exception $e) {
                // Silently bypass logger error
            }
        }
    }

    // =========================================================================
    // HOOK HANDLERS
    // =========================================================================

    /**
     * 1. Order Birth: Fires whenever an order is created.
     *
     * @param int            $order_id
     * @param WC_Order|false $order
     */
    public static function on_new_order($order_id, $order = false)
    {
        if (!$order || !is_a($order, 'WC_Order')) {
            $order = wc_get_order($order_id);
        }
        if (!$order) {
            return;
        }

        $items_data = [];
        $item_summary = [];
        $total_items_count = 0;
        foreach ($order->get_items('line_item') as $item) {
            $p_id = $item->get_product_id();
            $qty  = $item->get_quantity();
            $name = $item->get_name();
            $total = $item->get_total();
            $total_items_count += $qty;

            $vendor_id = 0;
            if (function_exists('wcfm_get_vendor_id_by_post')) {
                $vendor_id = (int) wcfm_get_vendor_id_by_post($p_id);
            }
            if (!$vendor_id) {
                $vendor_id = (int) get_post_field('post_author', $p_id);
            }

            $items_data[] = [
                'item_id'    => $item->get_id(),
                'product_id' => $p_id,
                'name'       => $name,
                'qty'        => $qty,
                'total'      => $total,
                'vendor_id'  => $vendor_id,
            ];
            $item_summary[] = sprintf('%s (#%d) x %d = ₹%s [Vendor: %d]', $name, $p_id, $qty, $total, $vendor_id);
        }

        $shipping_summary = [];
        foreach ($order->get_items('shipping') as $s_item) {
            $shipping_summary[] = sprintf('%s = ₹%s [Method: %s, Vendor: %s]', 
                $s_item->get_name(), 
                $s_item->get_total(), 
                $s_item->get_method_id(),
                var_export($s_item->get_meta('vendor_id'), true)
            );
        }

        $post_params = $_POST;
        // Redact sensitive keys
        foreach (['password', 'pwd', 'secret', 'client_secret', 'token', 'key'] as $sec_k) {
            if (isset($post_params[$sec_k])) {
                $post_params[$sec_k] = '***REDACTED***';
            }
        }

        $details = [
            'Order ID'             => '#' . $order_id,
            'Status'               => $order->get_status(),
            'Total'                => '₹' . $order->get_total(),
            'Currency'             => $order->get_currency(),
            'Payment Method'       => $order->get_payment_method() . ' (' . $order->get_payment_method_title() . ')',
            'Created Via'          => $order->get_created_via(),
            'Parent Order ID'      => $order->get_parent_id(),
            'Cart Hash'            => $order->get_cart_hash(),
            'Order Key'            => $order->get_order_key(),
            'Customer ID'          => $order->get_customer_id(),
            'Customer Email'       => $order->get_billing_email(),
            'Customer Phone'       => $order->get_billing_phone(),
            'Customer Name'        => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
            'Line Items Count'     => $total_items_count,
            'Line Items'           => $item_summary,
            'Shipping'             => $shipping_summary,
            'POST Data (Sanitized)'=> $post_params,
        ];

        // Check for potential duplicate order
        $duplicate_info = self::detect_duplicate_order($order_id, $order, $items_data);
        if (!empty($duplicate_info)) {
            $details['🚨 DUPLICATE ALERT 🚨'] = $duplicate_info;
        }

        self::log('ORDER_CREATED', "New Order #{$order_id} Born! (Total: ₹{$order->get_total()} for {$total_items_count} item(s))", $details, true, 16);
    }

    /**
     * Check whether an identical or nearly identical order was created recently.
     *
     * @param int      $current_order_id
     * @param WC_Order $order
     * @param array    $items_data
     * @return array
     */
    private static function detect_duplicate_order($current_order_id, WC_Order $order, array $items_data)
    {
        $cache_key = 'thaaniyamhub_recent_orders_tracker';
        $recent = get_transient($cache_key);
        if (!is_array($recent)) {
            $recent = [];
        }

        $now = time();
        // Prune entries older than 10 minutes (600s)
        foreach ($recent as $oid => $info) {
            if ($now - $info['time'] > 600) {
                unset($recent[$oid]);
            }
        }

        $current_pids = [];
        foreach ($items_data as $it) {
            $current_pids[] = $it['product_id'] . ':' . $it['qty'];
        }
        sort($current_pids);
        $current_items_sig = implode('|', $current_pids);

        $duplicate_match = [];
        foreach ($recent as $prev_id => $prev) {
            if ((int) $prev_id === (int) $current_order_id) {
                continue;
            }

            $diff_seconds = $now - $prev['time'];
            $same_customer = ($prev['customer_id'] > 0 && $prev['customer_id'] === $order->get_customer_id()) || 
                             (!empty($prev['email']) && strtolower($prev['email']) === strtolower($order->get_billing_email()));
            $same_total = abs((float) $prev['total'] - (float) $order->get_total()) < 0.01;
            $same_items = ($prev['items_sig'] === $current_items_sig);

            if ($same_total && $same_items) {
                $duplicate_match = [
                    'MATCHED_ORDER'        => '#' . $prev_id,
                    'SECONDS_APART'        => $diff_seconds . 's',
                    'SAME_CUSTOMER'        => $same_customer ? 'YES' : 'NO',
                    'SAME_TOTAL'           => 'YES (₹' . $order->get_total() . ')',
                    'SAME_ITEMS'           => 'YES (' . $current_items_sig . ')',
                    'SAME_IP'              => ($prev['ip'] === self::get_client_ip()) ? 'YES (' . $prev['ip'] . ')' : 'NO',
                    'SAME_REQUEST_ID'      => ($prev['req_id'] === self::$request_id) ? 'YES (SAME HTTP REQUEST!)' : 'NO (Different HTTP Request: ' . $prev['req_id'] . ' vs ' . self::$request_id . ')',
                    'PREVIOUS_CREATED_VIA' => $prev['created_via'],
                ];
                break;
            }
        }

        // Record current order into tracker
        $recent[$current_order_id] = [
            'time'        => $now,
            'customer_id' => $order->get_customer_id(),
            'email'       => $order->get_billing_email(),
            'total'       => (float) $order->get_total(),
            'items_sig'   => $current_items_sig,
            'ip'          => self::get_client_ip(),
            'req_id'      => self::$request_id,
            'created_via' => $order->get_created_via(),
        ];
        set_transient($cache_key, $recent, 600);

        return $duplicate_match;
    }

    /**
     * 2. Checkout before processing.
     */
    public static function on_before_checkout_process()
    {
        $cart = WC()->cart;
        $cart_items = [];
        if ($cart) {
            foreach ($cart->get_cart() as $ck => $ci) {
                $cart_items[] = sprintf('%s (#%d) x %d', $ci['data']->get_name(), $ci['product_id'], $ci['quantity']);
            }
        }

        self::log('CHECKOUT_STARTED', 'Customer initiated checkout form submission', [
            'Cart Total'  => $cart ? '₹' . $cart->get_total('edit') : 'N/A',
            'Items Count' => $cart ? $cart->get_cart_contents_count() : 0,
            'Cart Items'  => $cart_items,
            'Cart Hash'   => $cart ? $cart->get_cart_hash() : 'N/A',
        ]);
    }

    /**
     * 3. Classic Checkout Order Processed (Early - priority 1).
     */
    public static function on_checkout_order_processed_early($order_id, $posted_data, $order)
    {
        self::log('CHECKOUT_PROCESSED_EARLY', "woocommerce_checkout_order_processed fired (early priority 1) for Order #{$order_id}", [
            'Order ID'       => '#' . $order_id,
            'Payment Method' => is_a($order, 'WC_Order') ? $order->get_payment_method() : 'N/A',
            'Needs Payment'  => is_a($order, 'WC_Order') ? ($order->needs_payment() ? 'YES' : 'NO') : 'N/A',
            'Status'         => is_a($order, 'WC_Order') ? $order->get_status() : 'N/A',
        ]);
    }

    /**
     * 4. Classic Checkout Order Processed (Late - priority 99).
     */
    public static function on_checkout_order_processed_late($order_id, $posted_data, $order)
    {
        self::log('CHECKOUT_PROCESSED_LATE', "woocommerce_checkout_order_processed finished (late priority 99) for Order #{$order_id}", [
            'Order ID'       => '#' . $order_id,
            'Status'         => is_a($order, 'WC_Order') ? $order->get_status() : 'N/A',
            'Split Done'     => is_a($order, 'WC_Order') ? var_export($order->get_meta('_thaaniyamhub_order_split_done'), true) : 'N/A',
            'Secondary IDs'  => is_a($order, 'WC_Order') ? var_export($order->get_meta('_thaaniyamhub_secondary_order_ids'), true) : 'N/A',
        ]);
    }

    /**
     * 5. Blocks / Store API Checkout Order Processed.
     */
    public static function on_store_api_order_processed($order)
    {
        if (is_a($order, 'WC_Order')) {
            self::log('STORE_API_CHECKOUT', "woocommerce_store_api_checkout_order_processed fired for Order #{$order->get_id()}", [
                'Order ID' => '#' . $order->get_id(),
                'Status'   => $order->get_status(),
                'Total'    => '₹' . $order->get_total(),
            ]);
        }
    }

    /**
     * 6. Payment Complete Hook.
     */
    public static function on_payment_complete($order_id)
    {
        $order = wc_get_order($order_id);
        self::log('PAYMENT_COMPLETE', "woocommerce_payment_complete fired for Order #{$order_id}", [
            'Order ID'       => '#' . $order_id,
            'Status'         => $order ? $order->get_status() : 'N/A',
            'Transaction ID' => $order ? $order->get_transaction_id() : 'N/A',
            'Payment Method' => $order ? $order->get_payment_method() : 'N/A',
        ], true, 10);
    }

    /**
     * 7. Order Status Changed Hook.
     */
    public static function on_order_status_changed($order_id, $old_status, $new_status, $order = null)
    {
        self::log('STATUS_CHANGED', "Order #{$order_id} status changed: [{$old_status}] -> [{$new_status}]", [
            'Order ID'   => '#' . $order_id,
            'Old Status' => $old_status,
            'New Status' => $new_status,
        ], true, 12);
    }

    /**
     * 8. Thankyou Page.
     */
    public static function on_thankyou($order_id)
    {
        $order = wc_get_order($order_id);
        self::log('THANKYOU_PAGE', "Customer loaded Thankyou page for Order #{$order_id}", [
            'Order ID'       => '#' . $order_id,
            'Status'         => $order ? $order->get_status() : 'N/A',
            'Payment Method' => $order ? $order->get_payment_method() : 'N/A',
            'GET Params'     => $_GET,
        ]);
    }

    /**
     * 9. Intercept Payment Gateway Webhooks & Redirects.
     */
    public static function intercept_gateway_callbacks()
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        // Check for Cashfree or Razorpay callback/webhook
        if (strpos($uri, 'cashfree') !== false || strpos($uri, 'wc-api') !== false || isset($_GET['cf_id']) || isset($_GET['order_id'])) {
            if (strpos($uri, 'wp-admin') === false && strpos($uri, 'wp-json') === false) {
                self::log('GATEWAY_CALLBACK_HIT', "Incoming Payment Gateway Callback URL hit: {$uri}", [
                    'GET'  => $_GET,
                    'POST' => array_diff_key($_POST, array_flip(['secret', 'key', 'password'])),
                ]);
            }
        }
    }

    /**
     * 10. Inject Client-Side Click Tracking Script on Checkout Page.
     */
    public static function inject_checkout_click_logger()
    {
        if (!is_checkout() || is_order_received_page()) {
            return;
        }
        ?>
        <script type="text/javascript">
        (function() {
            var clickCount = 0;
            var clientUuid = 'cli_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();

            function attachCheckoutTracking() {
                var $form = jQuery('form.checkout');
                if (!$form.length) return;

                // Ensure hidden fields exist in the form
                if (!$form.find('input[name="_th_click_count"]').length) {
                    $form.append('<input type="hidden" name="_th_click_count" value="0" />');
                    $form.append('<input type="hidden" name="_th_client_uuid" value="' + clientUuid + '" />');
                }

                jQuery(document).on('click', '#place_order', function(e) {
                    clickCount++;
                    $form.find('input[name="_th_click_count"]').val(clickCount);
                    console.warn('[ThaaniyamHub Diagnostic] Place Order button clicked! Click count: ' + clickCount + ' | UUID: ' + clientUuid + ' | Time: ' + new Date().toISOString());

                    if (clickCount > 1) {
                        console.error('[ThaaniyamHub Diagnostic] WARNING: MULTIPLE CLICKS DETECTED on Place Order button! Attempt: ' + clickCount);
                    }
                });
            }

            if (window.jQuery) {
                jQuery(document).ready(attachCheckoutTracking);
            } else {
                document.addEventListener('DOMContentLoaded', attachCheckoutTracking);
            }
        })();
        </script>
        <?php
    }

    /**
     * Resolve reliable client IP.
     *
     * @return string
     */
    private static function get_client_ip()
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $header) {
            if (!empty($_SERVER[$header])) {
                $parts = explode(',', $_SERVER[$header]);
                return trim($parts[0]);
            }
        }
        return 'UNKNOWN';
    }
}
