<?php
global $layout;

$content_layout = $layout['frequent_product_layout'] ?? [];
$title  = $content_layout['title'] ?? '';
$button = $content_layout['product_button'] ?? [];

$top_picks = $content_layout['top_pick_product'] ?? [];
$post_ids = [];
if (!empty($top_picks) && is_array($top_picks)) {
    foreach ($top_picks as $post_item) {
        if (is_object($post_item) && isset($post_item->ID)) {
            $post_ids[] = $post_item->ID;
        } elseif (is_numeric($post_item)) {
            $post_ids[] = $post_item;
        }
    }
}

$args = [
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
];

if (!empty($post_ids)) {
    $args['post__in'] = $post_ids;
    $args['orderby']  = 'post__in';
} else {
    $args['order']     = 'ASC';
    $args['tax_query'] = [
        [
            'taxonomy' => 'product_visibility',
            'field'    => 'name',
            'terms'    => ['featured'],
        ],
        [
            'taxonomy' => 'product_type',
            'field'    => 'slug',
            'terms'    => ['bundle'],
            'operator' => 'NOT IN',
        ],
    ];
}

$query = new WP_Query($args);

if ($query->have_posts()) :
?>
<section class="frequent-order-sec">
    <div class="inner-container">

        <div class="section-header">
            <h2><?php echo esc_html($title); ?></h2>

            <?php if (!empty($button['url'])) : ?>
                <a href="<?php echo esc_url($button['url']); ?>">
                    <?php echo esc_html($button['title']); ?>
                </a>
            <?php endif; ?>
        </div>

        <div class="frequent-order-products">

            <?php while ($query->have_posts()) : $query->the_post();
                global $product;
                $product = wc_get_product(get_the_ID());
            ?>
                <div class="product-card">
                    <div class="card">

                        <div class="image-box">
                            <a href="<?php the_permalink(); ?>" class="product-image">
                                <?php
                                $gallery_ids = $product->get_gallery_image_ids();

                                if ( ! empty( $gallery_ids ) ) {
                                    $first_gallery_image_id = $gallery_ids[0];
                                    echo wp_get_attachment_image( 
                                        $first_gallery_image_id, 
                                        'woocommerce_thumbnail', 
                                        false, 
                                        array( 'class' => 'attachment-woocommerce_thumbnail size-woocommerce_thumbnail' )
                                    );
                                } else {
                                    echo $product->get_image( 'woocommerce_thumbnail' );
                                }
                                ?>
                            </a>
                        </div>

                        <!-- <div class="image-box">
                            <a href="<?php the_permalink(); ?>" class="product-image">
                                <?php
                                $home_image = get_field('home_image');

                                if ( $home_image ) {
                                    ?>
                                    <img src="<?php echo esc_url($home_image['url']); ?>" 
                                        alt="<?php echo esc_attr($home_image['alt']); ?>" class="img-fluid">
                                    <?php
                                } else {
                                    echo $product->get_image();
                                }
                                ?>
                            </a>
                        </div> -->

                        <div class="frequent-order-item-info">

                            <h3 class="product-title frequent-order-item-title">
                                <a href="<?php the_permalink(); ?>"> <?php
                                    $title = get_the_title();
                                    echo mb_strimwidth( $title, 0, 31, '...' );
                                    ?>
                                </a>
                            </h3>

                            <span class="price"><?php echo $product->get_price_html(); ?></span>

                              <!-- Product Rating -->
                            <!-- <div class="product-rating">
                                <?php
                                if ( $product->get_rating_count() > 0 ) { 
                                    woocommerce_template_loop_rating();
                                }
                                ?>
                            </div> -->

                            <div class="product-actions">

                                <!-- Add to Cart -->
                                <?php woocommerce_template_loop_add_to_cart(); ?>

								<a href="<?php echo esc_url(
										add_query_arg(
											'add-to-cart',
											$product->get_id(),
											wc_get_checkout_url()
										)
									); ?>" class="button buy-btn buy-now">
										Buy Now
									</a>
                                <!-- Buy Now -->
<!--                                 <a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="button buy-btn buy-now" data-product_id="<?php echo esc_attr( $product->get_id() ); ?>">
                                Buy Now
                                </a> -->

                                <!-- YITH Wishlist -->
                                <?php
                                if ( class_exists( 'YITH_WCWL' ) ) {
                                    echo do_shortcode('[yith_wcwl_add_to_wishlist product_id="' . get_the_ID() . '"]');
                                }
                                ?>

                            </div>
                        </div>

                    </div>
                </div>
            <?php endwhile; ?>

        </div>

    </div>
</section>
<?php
wp_reset_postdata();
endif;
?>


 <style>
    /* Hide YITH wishlist text, keep only icon */
    .yith-wcwl-add-to-wishlist span,
    .yith-wcwl-wishlistexistsbrowse,
    .yith-wcwl-wishlistaddedbrowse,
    .yith-wcwl-add-button > a span {
        display: none !important;
    }

    /* Optional: adjust icon spacing */
    .yith-wcwl-add-to-wishlist a {
        padding: 0 !important;
    }
</style>
<script>
    jQuery(function($){
        $('.buy-now').on('click', function(e){
            e.preventDefault();

            let productID = $(this).data('product_id');

            $.ajax({
                type: 'POST',
                url: wc_add_to_cart_params.ajax_url,
                data: {
                    action: 'woocommerce_add_to_cart',
                    product_id: productID,
                    quantity: 1
                },
                success: function () {
                    window.location.href = "<?php echo wc_get_checkout_url(); ?>";
                }
            });
        });
    });
</script>
