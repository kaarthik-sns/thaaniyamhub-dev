<?php
global $layout, $layout_id;

$content_layout = $layout['blog_post_list'];

$page_type   = $content_layout['page_type']; // 🔥 THIS WAS MISSING
$post_type   = $content_layout['post_type'];
$blog_title  = $content_layout['blog_title'];
$blog_desc   = $content_layout['blog_desc'];
$blog_btn    = $content_layout['blog_btn'];

$args = array(
    'post_type'      => $post_type,
    'posts_per_page' => 4,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'post_status'    => 'publish',
);

$blog_query = new WP_Query($args);
?>

<?php if ($page_type === 'home') : ?>
<section class="learn-hub">
  <div class="inner-container">
    <h2><?php echo esc_html($blog_title); ?></h2>
    <p class="subtitle"><?php echo esc_html($blog_desc); ?></p>

    <?php if ($blog_btn) : ?>
      <a href="<?php echo esc_url($blog_btn['url']); ?>"
         target="<?php echo esc_attr($blog_btn['target']); ?>"
         class="btn-secondary">
         <?php echo esc_html($blog_btn['title']); ?>
      </a>
    <?php endif; ?>

    <div class="row blog-grid">
      <?php if ($blog_query->have_posts()) : ?>
        <?php while ($blog_query->have_posts()) : $blog_query->the_post(); ?>
          <div class="col-md-3 blog-card-items">
            <?php if (has_post_thumbnail()) : ?>
              <?php the_post_thumbnail('full', ['class' => 'img-fluid']); ?>
            <?php else : ?>
              <img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/images/blog1.png" decoding="async "alt="Default Image" class="img-fluid">
            <?php endif; ?>

            <div class="blog-card">
              <span class="meta"><?php echo get_the_date('M j, Y'); ?> | Read 5 min</span>
              <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
              <p><?php the_excerpt(); ?></p>
              <a href="<?php the_permalink(); ?>" class="blog-card-btn">Read More</a>
            </div>
          </div>
        <?php endwhile; wp_reset_postdata(); ?>
      <?php else : ?>
        <p class="no-posts-found text-center">No blogs found.</p>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($page_type === 'blog') : ?>
  <section class="learn-hub">
    <div class="inner-container">
      <h2><?php echo esc_html($blog_title); ?></h2>
      <p class="subtitle"><?php echo esc_html($blog_desc); ?></p>

      <?php if ($blog_btn) : ?>
        <a href="<?php echo esc_url($blog_btn['url']); ?>"
          target="<?php echo esc_attr($blog_btn['target']); ?>"
          class="btn-secondary">
          <?php echo esc_html($blog_btn['title']); ?>
        </a>
      <?php endif; ?>

      <div class="row blog-grid">
        <?php if ($blog_query->have_posts()) : ?>
          <?php while ($blog_query->have_posts()) : $blog_query->the_post(); ?>
            <div class="col-md-4 blog-card-items">
              <?php if (has_post_thumbnail()) : ?>
                <?php the_post_thumbnail('full', ['class' => 'img-fluid']); ?>
              <?php else : ?>
                <img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/images/blog1.png" alt="Default Image" decoding="async" class="img-fluid">
              <?php endif; ?>

              <div class="blog-card blog-inner">
                <h3>
                  <a href="<?php the_permalink(); ?>">
                    <?php the_title(); ?>
                  </a>
                </h3>
                <p><?php the_excerpt(); ?></p>
                <span class="meta">
                  <?php echo get_the_date('M j, Y'); ?> | Read 5 min
                </span>
                <a href="<?php the_permalink(); ?>" class="blog-card-btn">Read More</a>
              </div>
            </div>
          <?php endwhile; wp_reset_postdata(); ?>
        <?php else : ?>
          <p class="no-posts-found text-center">No blogs found.</p>
        <?php endif; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<style>
  .blog-card .meta {
  display: block;
  }
  .blog-inner{
    max-width: 100%;
  }
  .blog-inner h3 a{
    color: #064C50;
  }
  .blog-inner p{
    max-width: 100%;
  }
</style>
