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

            // Determine the primary order ID under which the Cashfree transaction was created
            $primary_order_id = (int) $order->get_meta('_thaaniyamhub_primary_order_id');
            if (!$primary_order_id) {
                $primary_order_id = (int) $order_id;
            }

            // Generate a unique refund ID per attempt
            $refund_id = 'sub_' . $order_id . '-' . uniqid();

            try {
                $adapter = isset($this->inner_gateway->adapter) ? $this->inner_gateway->adapter : null;
                if (!$adapter && method_exists($this->inner_gateway, 'get_adapter')) {
                    $adapter = $this->inner_gateway->get_adapter();
                }

                if (!$adapter) {
                    return new WP_Error('error', __('Cashfree adapter not available for refund', 'woocommerce'));
                }

                $refund = $adapter->refund($primary_order_id, $refund_id, $amount, $reason);

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
