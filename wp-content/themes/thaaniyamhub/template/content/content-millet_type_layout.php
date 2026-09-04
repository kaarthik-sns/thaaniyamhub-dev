<?php
global $layout, $layout_id;

// Get layout data
$content_layout = $layout['millet_type_layout'] ?? [];

// ACF fields
$title      = $content_layout['product_millet_title'] ?? '';
$button     = $content_layout['product_millet_button'] ?? '';
$categories = $content_layout['product_millet_type_layout'] ?? [];
$subtitle  = $content_layout['subtitle'] ?? '';
$page_type  = $content_layout['page_type'] ?? '';

// Stop if no categories are checked
if (empty($categories) || is_wp_error($categories)) {
    return;
}
?>

<?php if ($page_type == 'home') { ?>

<!-- ================= HOME PAGE TYPE ================= -->

<section class="frequent-order-sec home-millet-sec">
    <div class="inner-container">

        <div class="section-header">
            <?php if ($title): ?>
                <h2><?php echo esc_html($title); ?></h2>
            <?php endif; ?>

            <?php if (!empty($button['url'])) : ?>
                <a href="<?php echo esc_url($button['url']); ?>">
                    <?php echo esc_html($button['title']); ?>
                </a>
            <?php endif; ?>
        </div>
	<div class="millet-type">

    <?php

    $allowed_slugs = [
        'finger-millet',
        'pearl-millet',
        'barnyard-millet',
        'foxtail-millet',
        'little-millet'
    ];

    $terms = get_terms([
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
        'slug'       => $allowed_slugs,
    ]);

    $ordered_terms = [];

    if (!empty($terms) && !is_wp_error($terms)) {

        foreach ($allowed_slugs as $slug) {

            foreach ($terms as $term) {

                if ($term->slug === $slug) {
                    $ordered_terms[] = $term;
                    break;
                }
            }
        }

        foreach ($ordered_terms as $category) :

            $cat_link = get_term_link($category);

            if (is_wp_error($cat_link)) {
                continue;
            }

            // WooCommerce Category Image
            $thumbnail_id = get_term_meta($category->term_id, 'thumbnail_id', true);

            $image = '';

            if ($thumbnail_id) {
                $image = wp_get_attachment_url($thumbnail_id);
            }
    ?>

            <div class="millet-type-items">

                <a href="<?php echo esc_url($cat_link); ?>" class="millet-type-list">

                    <div class="millet-type-media">

                        <?php if ($image) : ?>

                            <img src="<?php echo esc_url($image); ?>"
                                decoding="async" alt="<?php echo esc_attr($category->name); ?>"
                                class="img-fluid">

                        <?php else : ?>

                            <div class="millet-empty-image"></div>

                        <?php endif; ?>

                    </div>

                    <div class="millet-type-info">
                        <h3><?php echo esc_html($category->name); ?></h3>
                    </div>

                </a>

            </div>

    <?php
        endforeach;
    }
    ?>

</div>
    </div>
</section>

<?php } elseif ($page_type == 'collection') { ?>

<!-- ================= COLLECTION PAGE TYPE ================= -->
<section class="collection-section">
     <!-- Corner Decorative Images -->
    <div class="collection-corner-left">
        <img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/images/freshly-harvested-millet-bundles.png" decoding="async" alt="Millet Left" class="img-fluid">
    </div>

    <div class="collection-corner-right">
        <img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/images/millet-plant.png" alt="Millet Right" decoding="async" class="img-fluid">
    </div>

    <div class="inner-container">

        <!-- Section Heading -->
        <div class="Explore-content text-center">
            <?php if ($title): ?>
                <h2 class="collection-main-title"><?php echo esc_html($title); ?></h2>
            <?php endif; ?>

            <?php if (!empty($content_layout['subtitle'])) : ?>
                <p class="collection-sub-title"><?php echo esc_html($content_layout['subtitle']); ?></p>
            <?php endif; ?>
        </div>

        <!-- Collection Grid -->
        <div class="row collection-grid">

            <?php foreach ($categories as $category) :

                if ($category->slug === 'uncategorized') continue;

                $cat_link = get_term_link($category);
                if (is_wp_error($cat_link)) continue;

                // Collection Image
                $collection_image_id = get_term_meta($category->term_id, 'collection_image_id', true);
                $image = $collection_image_id ? wp_get_attachment_url($collection_image_id) : '';
            ?>

                <div class="col-md-4 col-sm-6 collection-item">
                    <a href="<?php echo esc_url($cat_link); ?>" class="collection-card">

                        <?php if ($image): ?>
                            <div class="collection-img-wrap">
                                <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($category->name); ?>" decoding="async" class="img-fluid">
                            </div>
                        <?php endif; ?>

                        <!-- Overlay strip -->
                        <div class="collection-overlay"></div>

                        <!-- Title on overlay -->
                        <div class="collection-title-overlay">
                            <?php echo esc_html($category->name); ?>
                        </div>

                    </a>
                </div>

            <?php endforeach; ?>

        </div>

    </div>
</section>

<?php } ?>
<style>
 
</style>
