<?php
global $layout, $layout_id;

// Get layout data
$content_layout = $layout['collections_layout'] ?? [];

// ACF fields
$title      = $content_layout['collections_title'] ?? '';
$subtitle   = $content_layout['collections_subtitle'] ?? '';
$button     = $content_layout['collections_button'] ?? '';
$categories = $content_layout['product_collections_layout'] ?? [];

// Stop if no categories are checked
if (empty($categories) || is_wp_error($categories)) {
    return;
}
?>

<section class="collections-sec">
  <div class="inner-container">

    <!-- Heading -->
    <div class="Explore-content text-center">
      <?php if ($title): ?>
        <h2><?php echo esc_html($title); ?></h2>
      <?php endif; ?>

      <?php if ($subtitle): ?>
        <p class="subtitle"><?php echo esc_html($subtitle); ?></p>
      <?php endif; ?>

      <?php if (!empty($button['url'])): ?>
        <a href="<?php echo esc_url($button['url']); ?>"
           class="cta-btn"
           target="<?php echo esc_attr($button['target'] ?: '_self'); ?>">
          <?php echo esc_html($button['title']); ?>
        </a>
      <?php endif; ?>
    </div>

    <!-- Slider -->
    <div class="collection-slider">
      <?php foreach ($categories as $category) :

        // Skip Uncategorized
        if ($category->slug === 'uncategorized') {
          continue;
        }

        $cat_link = get_term_link($category);
        if (is_wp_error($cat_link)) {
          continue;
        }

        $thumbnail_id = get_term_meta($category->term_id, 'thumbnail_id', true);
        $image = $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : '';
      ?>

        <div class="collection-slide">
          <a href="<?php echo esc_url($cat_link); ?>" class="collection-list">
            <?php if ($image): ?>
              <img src="<?php echo esc_url($image); ?>"
                   alt="<?php echo esc_attr($category->name); ?>" class="img-fluid">
            <?php endif; ?>
            <p><?php echo esc_html($category->name); ?></p>
          </a>
        </div>

      <?php endforeach; ?>
    </div>

  </div>
</section>