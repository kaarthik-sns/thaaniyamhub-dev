<?php
/**
 * Product Loop Content
 *
 * @package WooCommerce/Templates
 * @version 9.4.0
 */

defined( 'ABSPATH' ) || exit;
global $product;
?>

<li <?php wc_product_class( 'custom-product-card', $product ); ?> class="custom-mobile-product-card">
    <div class="product-card-inner custom-product-card-inner">

        <!-- PRODUCT IMAGE -->
        <div class="product-image-wrap">
            <a href="<?php the_permalink(); ?>" class="product-image">
                <?php woocommerce_template_loop_product_thumbnail(); ?>
            </a>
            <!-- HOVER ICONS -->
            <div class="hover-icons">
                <!-- ADD TO CART -->
                <a href="<?php echo esc_url( $product->add_to_cart_url() ); ?>" class="custom_hover_add_to_cart">
                    <i class="fas fa-cart-plus"></i>
                </a>

                <!-- WISHLIST (AJAX) -->
                <?php if ( class_exists( 'YITH_WCWL' ) ) : ?>
                    <?php echo do_shortcode( '[yith_wcwl_add_to_wishlist product_id="' . get_the_ID() . '" link_classes="yith-wcwl-add-to-wishlist"]' ); ?>
                <?php endif; ?>

                
            </div>
            
        </div>

        <!-- PRODUCT INFO -->
        <div class="product-info">

            <!-- PRODUCT TITLE -->
            <h3 class="product-title custom-product-title">
                <a href="<?php the_permalink(); ?>">
                    <?php
                        $title = get_the_title();
                        echo mb_strimwidth( $title, 0, 30, '...' );
                    ?>
                </a>
            </h3>

            <!-- VENDOR NAME -->
            <?php
            $vendor_id = get_post_field( 'post_author', get_the_ID() );
            if ( $vendor_id ) {
                $vendor = get_user_by( 'id', $vendor_id );
                if ( $vendor ) {
                    if ( function_exists( 'wcfmmp_get_store_url' ) ) {
                        $store_name = function_exists( 'wcfm_get_vendor_store_name' ) 
                            ? wcfm_get_vendor_store_name( $vendor_id ) 
                            : $vendor->display_name;
                        $store_url  = wcfmmp_get_store_url( $vendor_id );
                    } else {
                        $store_name = $vendor->display_name;
                        $store_url  = get_author_posts_url( $vendor_id );
                    }
                    echo '<div class="product-vendor">By <a href="' . esc_url( $store_url ) . '">' . esc_html( $store_name ) . '</a></div>';
                }
            }
            ?>

            <!-- RATING -->
            <?php if ( $product && $product->get_rating_count() > 0 ) : ?>
                <div class="product-rating custom-product-rating">
                    <?php woocommerce_template_loop_rating(); ?>
                </div>
            <?php else : ?>
                <div class="product-rating custom-product-rating empty-rating">
                    <span class="star-outline"></span>
                </div>
            <?php endif; ?>

            <!-- PRICE -->
            <div class="product-price custom-product-price">
                <?php woocommerce_template_loop_price(); ?>
            </div>

            <!-- ACTIONS -->
            <div class="product-actions custom-product-actions shop-add-whislist">

                <!-- Add to Cart (POST – no ?add-to-cart in URL) -->
                <form method="post" class="shop-add-cart-form">
                    <input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>">
                    <button type="submit" class="shop-add-cart-btn">
                        Add to Cart
                    </button>
                </form>

                <!-- Wishlist -->
                <?php
            if ( class_exists( 'YITH_WCWL' ) ) {
                    echo do_shortcode('[yith_wcwl_add_to_wishlist]');
                }

                ?>

            </div>



        </div>
    </div>
</li>