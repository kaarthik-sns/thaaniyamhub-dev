<?php
/**
 * Email Order Details
 *
 * @package WooCommerce/Templates/Emails
 * @version 10.8.0
 */

defined( 'ABSPATH' ) || exit; ?>

<table width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;">
    <tr>
        <td align="center">

            <!-- Container -->
            <table width="600" cellpadding="0" cellspacing="0" style="border:1px solid #e5e5e5; border-collapse:collapse;">

                <!-- Title -->
                <tr>
                    <td style="padding:15px; font-size:16px; font-weight:600; background:#f9f9f9;">
                        📦 Order Summary
                    </td>
                </tr>

                <!-- Table Head -->
                <tr style="background:#f5f5f5;">
                    <th style="padding:10px; text-align:left; border-bottom:1px solid #ddd;">Product</th>
                    <th style="padding:10px; text-align:center; border-bottom:1px solid #ddd;">Qty</th>
                    <th style="padding:10px; text-align:right; border-bottom:1px solid #ddd;">Price</th>
                </tr>

                <!-- Items -->
                <?php foreach ( $order->get_items() as $item ) :
                    $_product   = $item->get_product();
                    $vendor_name= '';
                    if ( $_product ) {
                        $product_id = $_product->get_id();
                        $vendor_id  = 0;
                        if ( method_exists( $item, 'get_meta' ) && $item->get_meta( '_vendor_id' ) ) {
                            $vendor_id = (int) $item->get_meta( '_vendor_id' );
                        }
                        if ( ! $vendor_id && function_exists( 'wcfm_get_vendor_id_by_post' ) ) {
                            $vendor_id = (int) wcfm_get_vendor_id_by_post( $product_id );
                        }
                        if ( ! $vendor_id ) {
                            $vendor_id = (int) get_post_field( 'post_author', $product_id );
                        }
                        if ( $vendor_id ) {
                            if ( function_exists( 'wcfm_get_vendor_store_name' ) ) {
                                $vendor_name = wcfm_get_vendor_store_name( $vendor_id );
                            }
                            if ( ! $vendor_name && function_exists( 'thaaniyamhub_get_vendor_name_by_vendor_id' ) ) {
                                $vendor_name = thaaniyamhub_get_vendor_name_by_vendor_id( $vendor_id );
                            }
                        }
                    }
                ?>
                <tr>
                    <td style="padding:10px; border-bottom:1px solid #eee;">
                        <div style="font-weight:600; font-family:Arial,sans-serif; font-size:14px;"><?php echo esc_html( $item->get_name() ); ?></div>
                        <?php if ( ! empty( $vendor_name ) ) : ?>
                            <div style="font-size:11px; color:#1E4D2B; font-weight:700; font-family:Arial,sans-serif; margin-top:3px;">Vendor: <?php echo esc_html( $vendor_name ); ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="padding:10px; text-align:center; border-bottom:1px solid #eee;">
                        <?php echo esc_html( $item->get_quantity() ); ?>
                    </td>
                    <td style="padding:10px; text-align:right; border-bottom:1px solid #eee;">
                        <?php echo wp_kses_post( wc_price( $item->get_total() ) ); ?>
                    </td>
                </tr>
                <?php endforeach; ?>

                <!-- Subtotal -->
                <tr>
                    <td colspan="2" style="padding:10px; text-align:right;">
                        Subtotal
                    </td>
                    <td style="padding:10px; text-align:right;">
                        <?php echo wp_kses_post( wc_price( $order->get_subtotal() ) ); ?>
                    </td>
                </tr>

                <!-- Discount -->
                <?php if ( $order->get_discount_total() > 0 ) : ?>
                <tr>
                    <td colspan="2" style="padding:10px; text-align:right; color:#d9534f;">
                        Discount
                    </td>
                    <td style="padding:10px; text-align:right; color:#d9534f;">
                        -<?php echo wp_kses_post( wc_price( $order->get_discount_total() ) ); ?>
                    </td>
                </tr>
                <?php endif; ?>

                <!-- Shipping -->
                <?php if ( $order->get_shipping_total() > 0 ) : ?>
                <tr>
                    <td colspan="2" style="padding:10px; text-align:right;">
                        Shipping
                    </td>
                    <td style="padding:10px; text-align:right;">
                        <?php echo wp_kses_post( wc_price( $order->get_shipping_total() ) ); ?>
                    </td>
                </tr>
                <?php endif; ?>

                <!-- Total -->
                <tr style="background:#f9f9f9;">
                    <td colspan="2" style="padding:12px; text-align:right; font-weight:600;">
                        Total
                    </td>
                    <td style="padding:12px; text-align:right; font-weight:600;">
                        <?php echo wp_kses_post( wc_price( $order->get_total() ) ); ?>
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>