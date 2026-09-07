<?php
defined('ABSPATH') || exit;

if (class_exists('WC_Payment_Gateway') && !class_exists('ThaaniyamHub_WC_Cashfree_Payments_Wrapper')) {
    class ThaaniyamHub_WC_Cashfree_Payments_Wrapper extends WC_Payment_Gateway
    {
        /**
         * Inner Cashfree gateway instance.
         *
         * @var object
         */
        public $inner_gateway;

        /**
         * Constructor.
         */
        public function __construct()
        {
            if (!class_exists('WC_Cashfree_Payments') && defined('WC_CASHFREE_DIR_PATH')) {
                if (file_exists(WC_CASHFREE_DIR_PATH . 'includes/gateways/class-wc-cashfree-gateway.php')) {
                    include_once WC_CASHFREE_DIR_PATH . 'includes/gateways/class-wc-cashfree-gateway.php';
                }
                if (file_exists(WC_CASHFREE_DIR_PATH . 'includes/gateways/class-wc-cashfree-payments.php')) {
                    include_once WC_CASHFREE_DIR_PATH . 'includes/gateways/class-wc-cashfree-payments.php';
                }
            }

            if (class_exists('WC_Cashfree_Payments')) {
                $this->inner_gateway      = new WC_Cashfree_Payments();
                $this->id                 = $this->inner_gateway->id ?? 'cashfree';
                $this->icon               = $this->inner_gateway->icon ?? '';
                $this->method_title       = $this->inner_gateway->method_title ?? 'Cashfree Payments';
                $this->method_description = $this->inner_gateway->method_description ?? '';
                $this->title              = $this->inner_gateway->title ?? 'Cashfree Payments';
                $this->description        = $this->inner_gateway->description ?? '';
                $this->enabled            = $this->inner_gateway->enabled ?? 'no';
                $this->supports           = $this->inner_gateway->supports ?? array('products', 'refunds');
                $this->form_fields        = $this->inner_gateway->form_fields ?? array();
                $this->settings           = $this->inner_gateway->settings ?? array();
                $this->has_fields         = $this->inner_gateway->has_fields ?? true;
                $this->order_button_text  = $this->inner_gateway->order_button_text ?? __('Pay Now', 'cashfree');
            } else {
                $this->id           = 'cashfree';
                $this->method_title = 'Cashfree Payments';
            }
        }

        /**
         * Magic getter for properties on inner gateway.
         */
        public function __get($name)
        {
            if ($this->inner_gateway && isset($this->inner_gateway->$name)) {
                return $this->inner_gateway->$name;
            }
            return isset($this->$name) ? $this->$name : null;
        }

        /**
         * Magic setter for properties on inner gateway.
         */
        public function __set($name, $value)
        {
            if ($this->inner_gateway) {
                $this->inner_gateway->$name = $value;
            }
            $this->$name = $value;
        }

        /**
         * Magic isset check.
         */
        public function __isset($name)
        {
            return ($this->inner_gateway && isset($this->inner_gateway->$name)) || isset($this->$name);
        }

        /**
         * Forward dynamic method calls to inner gateway.
         */
        public function __call($method, $args)
        {
            if ($this->inner_gateway && method_exists($this->inner_gateway, $method)) {
                return call_user_func_array(array($this->inner_gateway, $method), $args);
            }
            return null;
        }

        /**
         * Check if gateway is available for checkout.
         */
        public function is_available()
        {
            if ($this->inner_gateway && method_exists($this->inner_gateway, 'is_available')) {
                return $this->inner_gateway->is_available();
            }
            return parent::is_available();
        }

        /**
         * Get icon HTML.
         */
        public function get_icon()
        {
            if ($this->inner_gateway && method_exists($this->inner_gateway, 'get_icon')) {
                return $this->inner_gateway->get_icon();
            }
            return parent::get_icon();
        }

        /**
         * Get title.
         */
        public function get_title()
        {
            if ($this->inner_gateway && method_exists($this->inner_gateway, 'get_title')) {
                return $this->inner_gateway->get_title();
            }
            return !empty($this->title) ? $this->title : parent::get_title();
        }

        /**
         * Get description.
         */
        public function get_description()
        {
            if ($this->inner_gateway && method_exists($this->inner_gateway, 'get_description')) {
                return $this->inner_gateway->get_description();
            }
            return !empty($this->description) ? $this->description : parent::get_description();
        }

        /**
         * Render payment fields.
         */
        public function payment_fields()
        {
            if ($this->inner_gateway && method_exists($this->inner_gateway, 'payment_fields')) {
                $this->inner_gateway->payment_fields();
            } else {
                parent::payment_fields();
            }
        }

        /**
         * Validate form fields.
         */
        public function validate_fields()
        {
            if ($this->inner_gateway && method_exists($this->inner_gateway, 'validate_fields')) {
                return $this->inner_gateway->validate_fields();
            }
            return parent::validate_fields();
        }

        /**
         * Check if form fields exist.
         */
        public function has_fields()
        {
            if ($this->inner_gateway && method_exists($this->inner_gateway, 'has_fields')) {
                return $this->inner_gateway->has_fields();
            }
            return !empty($this->has_fields);
        }

        /**
         * Check feature support.
         */
        public function supports($feature)
        {
            if ($this->inner_gateway && method_exists($this->inner_gateway, 'supports')) {
                return $this->inner_gateway->supports($feature);
            }
            return parent::supports($feature);
        }

        /**
         * Get return URL after payment.
         */
        public function get_return_url($order = null)
        {
            if ($this->inner_gateway && method_exists($this->inner_gateway, 'get_return_url')) {
                return $this->inner_gateway->get_return_url($order);
            }
            return parent::get_return_url($order);
        }

        /**
         * Process admin options save.
         */
        public function process_admin_options()
        {
            if ($this->inner_gateway && method_exists($this->inner_gateway, 'process_admin_options')) {
                return $this->inner_gateway->process_admin_options();
            }
            return parent::process_admin_options();
        }

        /**
         * Output settings page HTML.
         */
        public function admin_options()
        {
            if ($this->inner_gateway && method_exists($this->inner_gateway, 'admin_options')) {
                $this->inner_gateway->admin_options();
            } else {
                parent::admin_options();
            }
        }

        /**
         * Process payment.
         */
        public function process_payment($order_id)
        {
            if ($this->inner_gateway && method_exists($this->inner_gateway, 'process_payment')) {
                return $this->inner_gateway->process_payment($order_id);
            }
            return array('result' => 'fail');
        }

        /**
         * Process refund for multi-vendor split orders.
         * Maps split vendor order ID to the Primary Cashfree Order ID.
         *
         * @param int    $order_id Order ID.
         * @param float  $amount   Refund amount.
         * @param string $reason   Refund reason.
         * @return bool|WP_Error
         */
        public function process_refund($order_id, $amount = null, $reason = '')
        {
            if (!$this->inner_gateway) {
                return new WP_Error('error', __('Cashfree gateway not initialized', 'woocommerce'));
            }

            $order = wc_get_order($order_id);
            if (!$order) {
                return new WP_Error('error', __('Refund failed: Invalid order ID', 'woocommerce'));
            }

            $transaction_id = $order->get_transaction_id();
            if (!$transaction_id) {
                return new WP_Error('error', __('Refund failed: No transaction ID', 'woocommerce'));
            }

            // 1. Strict Per-Order Refund Amount Validation:
            // Ensures only this specific order is refunded and cannot exceed its own remaining balance,
            // never refunding or bleeding into another split vendor order's share.
            $order_total    = (float) $order->get_total();
            $total_refunded = (float) $order->get_total_refunded();
            $max_refundable = max(0.0, round($order_total - $total_refunded, 2));

            if (null === $amount || (float) $amount <= 0) {
                $refund_amount = $max_refundable;
            } else {
                $refund_amount = round((float) $amount, 2);
            }

            if ($refund_amount <= 0) {
                return new WP_Error(
                    'error',
                    sprintf(__('Order #%d has no remaining refundable amount.', 'thaaniyamhub-multi-vendor-orders'), $order_id)
                );
            }

            if ($refund_amount > $max_refundable) {
                return new WP_Error(
                    'error',
                    sprintf(
                        __('Requested refund amount (₹%1$s) exceeds remaining balance for Order #%2$d (₹%3$s). Each vendor order can only be refunded up to its own total.', 'thaaniyamhub-multi-vendor-orders'),
                        number_format($refund_amount, 2),
                        $order_id,
                        number_format($max_refundable, 2)
                    )
                );
            }

            // 2. Resolve Primary Cashfree Transaction Order ID:
            // For split secondary orders, the Cashfree charge lives under the primary order ID.
            $primary_order_id = (int) $order->get_meta('_thaaniyamhub_primary_order_id');
            $is_sub_order     = ($primary_order_id > 0 && $primary_order_id !== (int) $order_id);
            if (!$is_sub_order) {
                $primary_order_id = (int) $order_id;
            }

            $primary_order = $is_sub_order ? wc_get_order($primary_order_id) : $order;
            $cf_order_id   = $primary_order ? ($primary_order->get_meta('_cf_order_id') ?: $primary_order->get_meta('_cashfree_order_id')) : '';

            $settings       = is_array($this->settings) ? $this->settings : get_option('woocommerce_cashfree_settings', []);
            $prefix_enabled = ($settings['order_id_prefix_text'] ?? 'no') === 'yes';

            // Resolve exact Cashfree Order ID for API calls
            $target_cf_order_id = !empty($cf_order_id) ? $cf_order_id : '';
            if (empty($target_cf_order_id)) {
                if ($prefix_enabled) {
                    $prefix = substr(md5(home_url()), 0, 4);
                    $target_cf_order_id = $prefix . '_' . $primary_order_id;
                } else {
                    $target_cf_order_id = (string) $primary_order_id;
                }
            }

            // Generate a unique, isolated refund ID per attempt
            $prefix_tag = $is_sub_order ? 'sub_' : 'pri_';
            $refund_id  = $prefix_tag . $order_id . '-' . time() . '-' . wp_rand(100, 999);

            $refund_processed = false;
            $cf_refund_id     = $refund_id;
            $refund_obj       = null;

            // Strategy 1: Clean public WC_Cashfree_Adapter call (without PHP Reflection)
            if (class_exists('WC_Cashfree_Adapter')) {
                try {
                    $adapter = new \WC_Cashfree_Adapter($this->inner_gateway);

                    // Note: WC_Cashfree_Adapter::refund automatically prepends prefix if order_id_prefix_text is 'yes'.
                    // To prevent double-prefixing (e.g. prefix_prefix_1234), pass raw integer ID when prefix is enabled.
                    $target_for_adapter = $prefix_enabled ? (string) $primary_order_id : $target_cf_order_id;

                    $refund_obj = $adapter->refund($target_for_adapter, $refund_id, $refund_amount, $reason);
                    if ($refund_obj) {
                        $refund_processed = true;
                        if (isset($refund_obj->cf_refund_id)) {
                            $cf_refund_id = $refund_obj->cf_refund_id;
                        }
                    }
                } catch (\Throwable $e) {
                    if (function_exists('thaaniyamhub_log')) {
                        thaaniyamhub_log('Cashfree Adapter refund attempt failed: ' . $e->getMessage() . '. Falling back to direct REST API.');
                    }
                }
            }

            // Strategy 2: Direct Official Cashfree PG REST API (v2025-01-01 / v2022-09-01)
            if (!$refund_processed) {
                $app_id     = $settings['app_id'] ?? '';
                $secret_key = $settings['secret_key'] ?? '';
                $is_sandbox = ($settings['sandbox'] ?? 'no') === 'yes';

                if (empty($app_id) || empty($secret_key)) {
                    return new WP_Error('error', __('Cashfree credentials missing for refund processing', 'woocommerce'));
                }

                $base_url = $is_sandbox ? 'https://sandbox.cashfree.com/pg' : 'https://api.cashfree.com/pg';
                $api_url  = $base_url . '/orders/' . rawurlencode($target_cf_order_id) . '/refunds';

                $body_data = [
                    'refund_id'     => $refund_id,
                    'refund_amount' => $refund_amount,
                    'refund_note'   => !empty($reason) ? $reason : sprintf('Refund for Order #%d', $order_id),
                    'refund_speed'  => 'STANDARD',
                ];

                $response = wp_remote_post($api_url, [
                    'timeout' => 30,
                    'headers' => [
                        'x-client-id'     => $app_id,
                        'x-client-secret' => $secret_key,
                        'x-api-version'   => '2025-01-01',
                        'Content-Type'    => 'application/json',
                        'x-request-id'    => 'cf-woo-ref-' . $order_id . '-' . time(),
                    ],
                    'body' => wp_json_encode($body_data),
                ]);

                if (is_wp_error($response)) {
                    return new WP_Error(
                        'error',
                        sprintf(__('Cashfree refund network error: %s', 'cashfree'), $response->get_error_message())
                    );
                }

                $code     = wp_remote_retrieve_response_code($response);
                $res_body = json_decode(wp_remote_retrieve_body($response));

                if ($code >= 200 && $code < 300 && !empty($res_body)) {
                    $refund_processed = true;
                    $refund_obj       = $res_body;
                    $cf_refund_id     = $res_body->cf_refund_id ?? $refund_id;
                } else {
                    $error_msg = $res_body->message ?? wp_remote_retrieve_response_message($response);
                    return new WP_Error(
                        'error',
                        sprintf(__('Cashfree refund failed. Order #%1$d (Tx: %2$s). Error: %3$s', 'cashfree'), $order_id, $transaction_id, $error_msg)
                    );
                }
            }

            if ($refund_processed) {
                $order->add_order_note(
                    sprintf(
                        __('Cashfree Refund Processed for ₹%1$s. Refund ID: %2$s (Target Cashfree Order: %3$s)', 'thaaniyamhub-multi-vendor-orders'),
                        number_format($refund_amount, 2),
                        (string) $cf_refund_id,
                        (string) $target_cf_order_id
                    )
                );

                do_action('woo_cashfree_refund_success', $cf_refund_id, $order_id, $refund_obj);
                return true;
            }

            return new WP_Error('error', __('Cashfree refund could not be processed', 'woocommerce'));
        }
    }
}
