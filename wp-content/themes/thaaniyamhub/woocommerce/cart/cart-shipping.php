<?php
/**
 * Shipping Methods Display override for checkout table alignment
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/cart/cart-shipping.php.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 8.8.0
 */

defined('ABSPATH') || exit;

$formatted_destination = isset($formatted_destination) ? $formatted_destination : WC()->countries->get_formatted_address($package['destination'], ', ');
$has_calculated_shipping = !empty($has_calculated_shipping);
$show_shipping_calculator = !empty($show_shipping_calculator);
$is_checkout_ajax = (is_ajax() && isset($_REQUEST['wc-ajax']) && in_array($_REQUEST['wc-ajax'], array('update_order_review', 'checkout'), true));

// Determine if we should combine shipping cost visually
$packages = WC()->shipping()->get_packages();
$multiple_packages = count($packages) > 1;
$package_keys = array_keys($packages);
$first_key = reset($package_keys);

if ((is_checkout() || (defined('WOOCOMMERCE_CHECKOUT') && WOOCOMMERCE_CHECKOUT) || $is_checkout_ajax) && !empty($available_methods) && is_array($available_methods)):
    $method = current($available_methods);
    $original_label = $method->get_label();

    // Check if the label contains delivery estimate
    $name = $original_label;
    $etd = '';
    if (preg_match('/\((Delivery by [^\)]+)\)/i', $original_label, $matches)) {
        $etd = $matches[1];
        $name = trim(str_replace($matches[0], '', $original_label));
    }

    if (strpos($method->method_id, 'shiprocket_woocommerce_shipping') !== false) {
        $name = 'Shipping cost';
    }

    if ($multiple_packages) {
        // Render the visual table row only for the first package
        if ($index == $first_key) {
            $cost = WC()->cart->get_shipping_total();
            if (WC()->cart->display_prices_including_tax()) {
                $cost += WC()->cart->get_shipping_tax();
            }
            $price_html = wc_price($cost);
            ?>
            <tr class="woocommerce-shipping-totals shipping">
                <th>
                    <span class="shipping-method-carrier"><?php echo esc_html($name); ?></span>
                    <?php if ($etd): ?>
                        <span class="shipping-method-etd" style="display: block; margin-top: 4px;"><?php echo esc_html($etd); ?></span>
                    <?php endif; ?>
                    <input type="hidden" name="shipping_method[<?php echo $index; ?>]" data-index="<?php echo $index; ?>"
                        id="shipping_method_<?php echo $index; ?>_<?php echo esc_attr(sanitize_title($method->id)); ?>"
                        value="<?php echo esc_attr($method->id); ?>" class="shipping_method" />
                </th>
                <td data-title="<?php echo esc_attr($name); ?>">
                    <span class="shipping-method-price"><?php echo $price_html; ?></span>
                </td>
            </tr>
            <?php
        } else {
            // For subsequent packages, just output the hidden input so WooCommerce is aware of the chosen method
            ?>
            <input type="hidden" name="shipping_method[<?php echo $index; ?>]" data-index="<?php echo $index; ?>"
                id="shipping_method_<?php echo $index; ?>_<?php echo esc_attr(sanitize_title($method->id)); ?>"
                value="<?php echo esc_attr($method->id); ?>" class="shipping_method" />
            <?php
        }
    } else {
        // Standard single package rendering
        $price_html = '';
        if ($method->cost > 0) {
            $cost = $method->cost;
            if (!wc_prices_include_tax()) {
                $cost += $method->get_shipping_tax();
            }
            $price_html = wc_price($cost);
        } else {
            $price_html = '<span class="shipping-free">' . esc_html__('Free', 'woocommerce') . '</span>';
        }
        ?>
        <tr class="woocommerce-shipping-totals shipping">
            <th>
                <span class="shipping-method-carrier"><?php echo esc_html($name); ?></span>
                <?php if ($etd): ?>
                    <span class="shipping-method-etd" style="display: block; margin-top: 4px;"><?php echo esc_html($etd); ?></span>
                <?php endif; ?>
                <input type="hidden" name="shipping_method[<?php echo $index; ?>]" data-index="<?php echo $index; ?>"
                    id="shipping_method_<?php echo $index; ?>_<?php echo esc_attr(sanitize_title($method->id)); ?>"
                    value="<?php echo esc_attr($method->id); ?>" class="shipping_method" />
            </th>
            <td data-title="<?php echo esc_attr($name); ?>">
                <span class="shipping-method-price"><?php echo $price_html; ?></span>
            </td>
        </tr>
        <?php
    }
else: ?>
    <?php if ($multiple_packages && $index !== $first_key) {
        return;
    } ?>
    <tr class="woocommerce-shipping-totals shipping">
        <th><?php echo wp_kses_post($package_name); ?></th>
        <td data-title="<?php echo esc_attr($package_name); ?>">
            <?php if (!empty($available_methods) && is_array($available_methods)): ?>
                <ul id="shipping_method" class="woocommerce-shipping-methods">
                    <?php foreach ($available_methods as $method): ?>
                        <li>
                            <?php
                            if (1 < count($available_methods)) {
                                printf('<input type="radio" name="shipping_method[%1$d]" data-index="%1$d" id="shipping_method_%1$d_%2$s" value="%3$s" class="shipping_method" %4$s />', $index, esc_attr(sanitize_title($method->id)), esc_attr($method->id), checked($method->id, $chosen_method, false)); // WPCS: XSS ok.
                            } else {
                                printf('<input type="hidden" name="shipping_method[%1$d]" data-index="%1$d" id="shipping_method_%1$d_%2$s" value="%3$s" class="shipping_method" />', $index, esc_attr(sanitize_title($method->id)), esc_attr($method->id)); // WPCS: XSS ok.
                            }
                            printf('<label for="shipping_method_%1$s_%2$s">%3$s</label>', $index, esc_attr(sanitize_title($method->id)), wc_cart_totals_shipping_method_label($method)); // WPCS: XSS ok.
                            do_action('woocommerce_after_shipping_rate', $method, $index);
                            ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if (is_cart()): ?>
                    <p class="woocommerce-shipping-destination">
                        <?php
                        if ($formatted_destination) {
                            // Translators: $s shipping destination.
                            printf(esc_html__('Shipping to %s.', 'woocommerce') . ' ', '<strong>' . esc_html($formatted_destination) . '</strong>');
                            $calculator_text = esc_html__('Change address', 'woocommerce');
                        } else {
                            echo wp_kses_post(apply_filters('woocommerce_shipping_estimate_html', __('Shipping options will be updated during checkout.', 'woocommerce')));
                        }
                        ?>
                    </p>
                <?php endif; ?>
                <?php
            elseif (!$has_calculated_shipping || !$formatted_destination):
                if (is_cart() && 'no' === get_option('woocommerce_enable_shipping_calc')) {
                    echo wp_kses_post(apply_filters('woocommerce_shipping_not_enabled_on_cart_html', __('Shipping costs are calculated during checkout.', 'woocommerce')));
                } else {
                    echo wp_kses_post(apply_filters('woocommerce_shipping_may_be_available_html', __('Enter your address to view shipping options.', 'woocommerce')));
                }
            elseif (!is_cart()):
                echo wp_kses_post(apply_filters('woocommerce_no_shipping_available_html', __('There are no shipping options available. Please ensure that your address has been entered correctly, or contact us if you need any help.', 'woocommerce')));
            else:
                echo wp_kses_post(
                    apply_filters(
                        'woocommerce_cart_no_shipping_available_html',
                        sprintf(esc_html__('No shipping options were found for %s.', 'woocommerce') . ' ', '<strong>' . esc_html($formatted_destination) . '</strong>'),
                        $formatted_destination
                    )
                );
                $calculator_text = esc_html__('Enter a different address', 'woocommerce');
            endif;
            ?>

            <?php if ($show_package_details): ?>
                <?php echo '<p class="woocommerce-shipping-contents"><small>' . esc_html($package_details) . '</small></p>'; ?>
            <?php endif; ?>

            <?php if ($show_shipping_calculator): ?>
                <?php woocommerce_shipping_calculator($calculator_text); ?>
            <?php endif; ?>
        </td>
    </tr>
<?php endif; ?>