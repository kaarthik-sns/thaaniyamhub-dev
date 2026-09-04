<?php
/**
 * Single Product Template
 *
 * @package WooCommerce/Templates
 * @version 1.6.4
 */

defined('ABSPATH') || exit;
get_header();

while (have_posts()):
    the_post();
    global $product;

    /**
     * IMPORTANT: This initializes WooCommerce scripts, nonce, notices
     */
    do_action('woocommerce_before_single_product');
    $section_class = 'custom-product-page custom-product-single';

    if ($product && $product->is_type('woosb')) {
        $section_class .= ' custom-bundle-product';
    }
    ?>

    <section class="<?php echo esc_attr($section_class); ?>">
        <div class="inner-container">
            <div class="row">

                <!-- LEFT: Product Gallery -->
                <div class="col-md-6 product-gallery-area">
                    <?php do_action('woocommerce_before_single_product_summary'); ?>
                </div>

                <!-- RIGHT: Product Content -->
                <div class="col-md-6 product-content-area">
                    <?php
                    $brands = get_the_terms(get_the_ID(), 'product_brand');

                    if (!empty($brands) && !is_wp_error($brands)): ?>
                        <div class="product-brand">
                            <button><?php echo esc_html($brands[0]->name); ?></button>
                        </div>
                    <?php endif; ?>

                    <h1 class="product-title"><?php the_title(); ?></h1>

                    <?php
                    $weight = '';
                    if ( $product ) {
                        $raw_weight = $product->get_weight();
                        if ( $raw_weight ) {
                            $weight_unit = get_option( 'woocommerce_weight_unit' );
                            $weight_in_grams = floatval( $raw_weight );
                            switch ( $weight_unit ) {
                                case 'kg':
                                    $weight_in_grams *= 1000;
                                    break;
                                case 'lbs':
                                    $weight_in_grams *= 453.59237;
                                    break;
                                case 'oz':
                                    $weight_in_grams *= 28.34952;
                                    break;
                            }
                            if ( round( $weight_in_grams ) == $weight_in_grams ) {
                                $formatted_weight = round( $weight_in_grams );
                            } else {
                                $formatted_weight = wc_format_localized_decimal( round( $weight_in_grams, 2 ) );
                            }
                            $weight = $formatted_weight . ' ' . __( 'g', 'woocommerce' );
                        }
                    }
                    if ( $weight ) : ?>
                        <div class="single-product-weight">
                            <span class="weight-label"><?php esc_html_e('Weight:', 'woocommerce'); ?></span>
                            <span class="weight-val"><?php echo esc_html($weight); ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="product-rating">
                        <?php woocommerce_template_single_rating(); ?>
                    </div>

                    <div class="product-price">
                        <?php woocommerce_template_single_price(); ?>
                    </div>

                    <div class="product-short-desc">
                        <?php woocommerce_template_single_excerpt(); ?>
                    </div>

                    <!-- Quantity + Add to Cart + Buy Now -->
                    <div class="product-quantity-cart">
                        <?php
                        /**
                         * This prints:
                         * - quantity
                         * - add to cart button
                         * - variation form
                         * - handles ajax
                         */
                        woocommerce_template_single_add_to_cart();
                        ?>
                        <form method="post" action="<?php echo esc_url(wc_get_checkout_url()); ?>">
                            <input type="hidden" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>">
                            <button type="submit" class="button buy-now-button">Buy Now</button>
                        </form>
                    </div>

                    <!-- BUY NOW BUTTON -->
                    <div class="product-quantity-cart">

                    </div>


                    <!-- ACF Accordion -->
                    <?php if (have_rows('product_info')): ?>
                        <div class="acf-accordion">
                            <?php while (have_rows('product_info')):
                                the_row(); ?>

                                <?php
                                $display = get_sub_field('display_option');
                                // checkbox → array
                                ?>

                                <div class="acf-accordion-item">

                                    <?php if ($display && in_array('product_title', $display)): ?>
                                        <div class="acf-accordion-header">
                                            <span class="accordion-title">
                                                <?php echo esc_html(get_sub_field('product_title')); ?>
                                            </span>
                                            <span class="accordion-arrow">
                                                <svg width="14" height="8" viewBox="0 0 14 8">
                                                    <path d="M1 1L7 7L13 1" stroke="#333" stroke-width="1.5" />
                                                </svg>
                                            </span>
                                        </div>
                                    <?php endif; ?>

                                    <div class="acf-accordion-content">

                                        <?php if ($display && in_array('product_info', $display)): ?>
                                            <?php echo wp_kses_post(get_sub_field('product_info')); ?>
                                        <?php endif; ?>

                                        <?php if ($display && in_array('product_list', $display) && have_rows('product_list')): ?>
                                            <ul>
                                                <?php while (have_rows('product_list')):
                                                    the_row(); ?>
                                                    <li><?php echo esc_html(get_sub_field('list_items')); ?></li>
                                                <?php endwhile; ?>
                                            </ul>
                                        <?php endif; ?>

                                        <?php if ($display && in_array('product_bottom_info', $display)): ?>
                                            <?php echo wp_kses_post(get_sub_field('product_bottom_info')); ?>
                                        <?php endif; ?>

                                    </div>
                                </div>

                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>

                </div>

            </div>
        </div>
    </section>

    <!-- Reviews -->
    <section class="custom-reviews single-page-product-review">
        <div class="inner-container">
            <?php
            global $product;
            if (!$product)
                return;

            // Total approved reviews
            $total_reviews = get_comments(array(
                'post_id' => $product->get_id(),
                'status' => 'approve',
                'type' => 'review',
                'count' => true
            ));

            // Average rating
            $average = $product->get_average_rating();

            // Star breakdown
            $star_counts = [];
            for ($i = 1; $i <= 5; $i++) {
                $star_counts[$i] = get_comments(array(
                    'post_id' => $product->get_id(),
                    'status' => 'approve',
                    'type' => 'review',
                    'meta_key' => 'rating',
                    'meta_value' => $i,
                    'count' => true
                ));
            }

            // Pagination
            $per_page = 5;
            $current_page = get_query_var('cpage') ? get_query_var('cpage') : 1;
            $offset = ($current_page - 1) * $per_page;

            $reviews = get_comments(array(
                'post_id' => $product->get_id(),
                'status' => 'approve',
                'type' => 'review',
                'number' => $per_page,
                'offset' => $offset,
                'orderby' => 'comment_date',
                'order' => 'DESC'
            ));
            ?>

            <div class="single-product-review-bg">
                <!-- Top Summary -->
                <div class="reviews-top">
                    <div class="average-rating">
                        <div class="stars"><?php echo wc_get_rating_html($average); ?></div>
                        <div class="avg-number"><?php echo number_format($average, 1); ?></div>
                        <a href="#review-form" class="write-review-btn">Write a Review</a>
                    </div>

                    <div class="rating-breakdown">
                        <?php for ($i = 5; $i >= 1; $i--):
                            $count = $star_counts[$i];
                            $percent = $total_reviews ? round(($count / $total_reviews) * 100) : 0;
                            ?>
                            <div class="star-bar">
                                <span class="star-label"><?php echo $i; ?> ★</span>
                                <div class="bar">
                                    <div class="fill" style="width: <?php echo $percent; ?>%;"></div>
                                </div>
                                <span class="star-count"><?php echo $count; ?></span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Reviews List -->
                <ul class="product-review-list">
                    <?php
                    if ($reviews) {
                        foreach ($reviews as $review) {
                            $rating = intval(get_comment_meta($review->comment_ID, 'rating', true));
                            ?>
                            <li class="product-review-item">
                                <h4 class="review-author"><?php echo esc_html($review->comment_author); ?></h4>
                                <span class="review-date"><?php echo esc_html(get_comment_date('d/m/Y', $review)); ?></span>
                                <div class="review-rating">
                                    <?php echo wc_get_rating_html($rating); ?>
                                </div>
                                <p class="review-content"><?php echo esc_html($review->comment_content); ?></p>
                            </li>
                            <?php
                        }
                    } else {
                        echo '<p>No reviews yet for this product.</p>';
                    }
                    ?>
                </ul>
            </div>

            <!-- Pagination -->
            <div class="reviews-pagination">
                <?php
                $pages = ceil($total_reviews / $per_page);
                if ($pages > 1) {
                    echo paginate_comments_links(array(
                        'total' => $pages,
                        'current' => $current_page,
                        'prev_text' => '&lt; Prev',
                        'next_text' => 'Next &gt;',
                    ));
                }
                ?>
            </div>

            <!-- Review Form -->
            <div id="review-form" class="product-review-form">
                <?php
                if (comments_open()) {

                    $commenter = wp_get_current_commenter();

                    comment_form(array(
                        'title_reply' => 'Write a Review',
                        'title_reply_to' => 'Leave a Reply to %s',
                        'comment_notes_after' => '',
                        'label_submit' => 'Submit Review',

                        'fields' => array(
                            'author' =>
                                '<p class="comment-form-author">
                                <label for="author">Name <span class="required">*</span></label>
                                <input id="author" name="author" type="text" value="' . esc_attr($commenter['comment_author']) . '" size="30" required />
                            </p>',

                            'email' =>
                                '<p class="comment-form-email">
                                <label for="email">Email <span class="required">*</span></label>
                                <input id="email" name="email" type="email" value="' . esc_attr($commenter['comment_author_email']) . '" size="30" required />
                            </p>',
                        ),

                        'comment_field' => '
                        <p class="comment-form-rating">
                            <label for="rating">Your Rating</label>
                            <select name="rating" id="rating" required>
                                <option value="">Rate…</option>
                                <option value="5">★★★★★ (Excellent)</option>
                                <option value="4">★★★★☆ (Good)</option>
                                <option value="3">★★★☆☆ (Average)</option>
                                <option value="2">★★☆☆☆ (Poor)</option>
                                <option value="1">★☆☆☆☆ (Very Poor)</option>
                            </select>
                        </p>

                        <p class="comment-form-comment">
                            <label for="comment">Your Review</label>
                            <textarea id="comment" name="comment" cols="45" rows="6" required></textarea>
                        </p>',
                    ));

                } else {
                    echo '<p>Reviews are closed for this product.</p>';
                }
                ?>
            </div>


        </div>
    </section>

    <!-- Smooth Scroll Script -->
    <script>
        document.querySelectorAll('.write-review-btn').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector('#review-form');
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
    </script>




    <!-- Top Picks -->
    <section class="top-picks single-related-product">
        <div class="inner-container">
            <div class="section-header">
                <h2>Top Picks from Thaaniyam Hub</h2>
                <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">View More Products</a>
            </div>

            <div class="top-picks-slider">
                <?php
                $args = array(
                    'post_type' => 'product',
                    'posts_per_page' => 8,
                );
                $loop = new WP_Query($args);

                while ($loop->have_posts()):
                    $loop->the_post();
                    global $product;
                    ?>
                    <div class="product-slide">
                        <?php wc_get_template_part('content', 'product'); ?>
                    </div>
                <?php endwhile;
                wp_reset_postdata(); ?>
            </div>
        </div>
    </section>
    <style>

    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const headers = document.querySelectorAll('.acf-accordion-header');

            headers.forEach(header => {
                header.addEventListener('click', function () {
                    const parent = this.closest('.acf-accordion-item');

                    document.querySelectorAll('.acf-accordion-item').forEach(item => {
                        if (item !== parent) {
                            item.classList.remove('active');
                        }
                    });

                    parent.classList.toggle('active');
                });
            });
        });
    </script>

    <script>
        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('qty-plus')) {
                let input = e.target.closest('.quantity').querySelector('input.qty');
                input.value = parseInt(input.value) + 1;
                input.dispatchEvent(new Event('change'));
            }

            if (e.target.classList.contains('qty-minus')) {
                let input = e.target.closest('.quantity').querySelector('input.qty');
                if (parseInt(input.value) > 1) {
                    input.value = parseInt(input.value) - 1;
                    input.dispatchEvent(new Event('change'));
                }
            }
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.woo-gallery-slider').forEach(el => {
                if (!el.hasAttribute('data-observed')) {
                    el.setAttribute('data-observed', 'true');
                }
            });
        });
    </script>
    <script>
        jQuery(function ($) {

            $('form.variations_form')
                .on('found_variation', function (event, variation) {

                    if (
                        variation &&
                        variation.wpgallery_images &&
                        typeof WooGallery !== 'undefined'
                    ) {
                        WooGallery.loadGallery(variation.wpgallery_images);
                    }
                })
                .on('reset_data', function () {
                    if (typeof WooGallery !== 'undefined') {
                        WooGallery.resetGallery();
                    }
                });

        });
    </script>




    <?php
    do_action('woocommerce_after_single_product');
endwhile;
get_footer();
