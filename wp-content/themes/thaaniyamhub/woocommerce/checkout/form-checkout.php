<?php
/**
 * Checkout Form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/form-checkout.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does happen.
 *
 * @package WooCommerce/Templates
 * @version 9.4.0
 */
 
defined('ABSPATH') || exit;

do_action('woocommerce_before_checkout_form', $checkout);

if (! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in()) {
    echo esc_html(apply_filters('woocommerce_checkout_must_be_logged_in_message', __('You must be logged in to checkout.', 'woocommerce')));
    return;
}
?>

<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url(wc_get_checkout_url()); ?>" enctype="multipart/form-data">
    <div class="row">
        <div class="col-sm-12 col-md-12 col-lg-6 checkout-col-left-info ">
            <?php if ($checkout->get_checkout_fields()) : ?>

                <?php do_action('woocommerce_checkout_before_customer_details'); ?>

                <div class="customer-details-wrapper">

                    <!-- BILLING -->
                    <div class="woocommerce-billing-fields">
                        <?php do_action('woocommerce_checkout_billing'); ?>
                    </div>

                    <!-- SHIPPING -->
                    <?php if (WC()->cart->needs_shipping_address()) : ?>
                        <div class="woocommerce-shipping-fields">
                            <?php do_action('woocommerce_checkout_shipping'); ?>
                        </div>
                    <?php endif; ?>

                </div>

                <?php do_action('woocommerce_checkout_after_customer_details'); ?>

            <?php endif; ?>
        </div>
        <div class="col-sm-12 col-md-12 col-lg-6">
            <div class="checkout-right-info">
            <div class="order-review-heading-wrapper" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 id="order_review_heading" style="margin-bottom: 0;"><?php esc_html_e('Your order', 'woocommerce'); ?></h3>
                <a href="<?php echo esc_url(wc_get_cart_url()); ?>" class="button view-cart-btn" style="text-decoration: none; font-size: 14px; padding: 5px 15px; border-radius: 5px; background-color: #0b4d45; color: #fff;">View Cart</a>
            </div>

            <?php do_action('woocommerce_checkout_before_order_review'); ?>

            <div id="order_review" class="woocommerce-checkout-review-order">
                <?php do_action('woocommerce_checkout_order_review'); ?>
            </div>

            <?php do_action('woocommerce_checkout_after_order_review'); ?>
            </div>
        </div>
    </div>

</form>

<?php do_action('woocommerce_after_checkout_form', $checkout); ?>