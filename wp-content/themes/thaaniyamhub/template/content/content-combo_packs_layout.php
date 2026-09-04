<?php
defined('ABSPATH') || exit;

global $layout;

/* ==============================
   Layout Fields
============================== */
$content_layout = $layout['combo_packs_layout'] ?? [];

$title       = $content_layout['combo_title'] ?? '';
$description = $content_layout['combo_description'] ?? '';
$button      = $content_layout['combo_button'] ?? [];
$page_type   = $content_layout['page_type'] ?? '';

?>

<!-- ======================================================
     COMBO PAGE → WooSB Bundle Products
====================================================== -->
<?php if ($page_type === 'combo') : ?>

<?php
  $args = [
      'post_type'      => 'product',
      'post_status'    => 'publish',
      'posts_per_page' => -1,
      'tax_query'      => [
          [
              'taxonomy' => 'product_type',
              'field'    => 'slug',
              'terms'    => ['woosb'],
          ],
      ],
  ];

  $query = new WP_Query($args);
?>

    <section class="combo-section"> 
        <div class="inner-container"> 
            <h2 class="combo-title"><?php echo esc_html($title); ?></h2> 
            <p class="combo-subtitle"><?php echo esc_html($description); ?></p> <?php if (!empty($button['url'])) : ?> 
            <a href="<?php echo esc_url($button['url']); ?>" class="combo-btn"> <?php echo esc_html($button['title']); ?> </a> <?php endif; ?> 
            <div class="combo-wrapper"> 
                <?php if ($query->have_posts()) : ?> <?php while ($query->have_posts()) : $query->the_post(); $product = wc_get_product(get_the_ID()); if (!$product) continue; ?> 
                <div class="combo-card-items"> 
                    <div class="combo-card"> 
                        <div class="combo-card-media">
                            <a href="<?php the_permalink(); ?>">

                                <?php
                                $combo_image = get_field('combo_product_image'); // ACF field

                                if (!empty($combo_image)) {

                                    // If return format = Image Array
                                    if (is_array($combo_image)) {
                                        echo wp_get_attachment_image($combo_image['ID'], 'medium');
                                    }

                                    // If return format = Image ID
                                    elseif (is_numeric($combo_image)) {
                                        echo wp_get_attachment_image($combo_image, 'medium');
                                    }

                                    // If return format = Image URL
                                    else {
                                        echo '<img src="' . esc_url($combo_image) . '" alt="' . get_the_title() . '">';
                                    }

                                } else {
                                    // Fallback to WooCommerce product image
                                    echo $product->get_image('medium');
                                }
                                ?>

                            </a>
                        </div>
                 
                        <div class="combo-card-title"> 
                            <a href="<?php the_permalink(); ?>"> <h3><?php the_title(); ?></h3> </a>
                        </div> 
                    </div> 
                </div> 
                <?php endwhile; ?> <?php wp_reset_postdata(); ?> <?php endif; ?> 
            </div> 
        </div> 
    </section>

<!-- ======================================================
     BRAND PAGE → Products by Product Brand
====================================================== -->
<?php elseif ($page_type === 'brand') : ?>

<section class="frequent-order-sec home-millet-sec">
    <div class="inner-container">

        <div class="section-header">
            <?php if ($title): ?>
                <h2><?php echo esc_html($title); ?></h2>
            <?php endif; ?>
            <?php if ($description): ?>
              <p class="combo-subtitle"><?php echo esc_html($description); ?></p>
          <?php endif; ?>

            <?php if (!empty($button['url'])) : ?>
                <a href="<?php echo esc_url($button['url']); ?>">
                    <?php echo esc_html($button['title']); ?>
                </a>
            <?php endif; ?>
        </div>

        <?php
        /* ==============================
           Get ALL Brands
        ============================== */
        $brands = get_terms([
            'taxonomy'   => 'product_brand',
            'hide_empty' => true,
        ]);
        ?>

        <?php if (!empty($brands) && !is_wp_error($brands)) : ?>
            <?php foreach ($brands as $brand) : ?>

                <h3 class="brand-title"><?php echo esc_html($brand->name); ?></h3>

                <div class="millet-type">

                    <?php
                    $brand_query = new WP_Query([
                        'post_type'      => 'product',
                        'post_status'    => 'publish',
                        'posts_per_page' => -1,
                        'tax_query'      => [
                            [
                                'taxonomy' => 'product_brand',
                                'field'    => 'term_id',
                                'terms'    => [$brand->term_id],
                            ],
                        ],
                    ]);
                    ?>

                    <?php if ($brand_query->have_posts()) : ?>
                        <?php while ($brand_query->have_posts()) : $brand_query->the_post(); ?>
                            <div class="brand-product">
                                <a href="<?php the_permalink(); ?>">
                                    <?php woocommerce_template_loop_product_thumbnail(); ?>
                                    <h4><?php the_title(); ?></h4>
                                </a>
                                <?php woocommerce_template_loop_price(); ?>
                            </div>
                        <?php endwhile; wp_reset_postdata(); ?>
                    <?php endif; ?>

                </div>

            <?php endforeach; ?>
        <?php else : ?>
            <p>No brands found.</p>
        <?php endif; ?>

    </div>
</section>

<?php endif; ?>
