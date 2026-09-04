<?php get_header(); ?>

<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
<?php
    $post_id = get_the_ID();
    $post_title = get_the_title($post_id);
    $banner_image = get_field( 'banner_image', $post_id);
    $blog_description = get_post_meta($post_id, 'blog_description', true);
    $detail_content = get_field('detail_content', $post_id);

?>

<div class="blog-view-banner">
    <div class="inner-container">
        <div class="row common-banner-bg"
            style="background-image:url('<?php 
                echo !empty($banner_image['url']) 
                    ? esc_url($banner_image['url']) 
                    : esc_url(''); 
            ?>');
            background-repeat:no-repeat;
            background-position:center;
            background-size:cover;">
            
            <div class="col-md-12">
                <div class="common-banner-content left-service-header">
                    <div class="breadcrumb_align">
                        <div class="breadcrumb_list">
                            <?php the_breadcrumb(); ?>
                        </div>
                    </div>
                    <h1><?php echo esc_html($post_title); ?></h1>
                    <p><?php echo esc_html($post_description); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="servies-view-banner" style="<?php echo $bg; ?>">
    <div class="inner-container">
        <div class="row">
			<div class="breadcrumb_align" <?php echo $breadcrumbstyle; ?>>
			  <div class="breadcrumb_list">
				  <?php the_breadcrumb(); ?>
			  </div>
           	</div>
            <div class="col-md-6">
                <div class="left-service-header">
                    <h2><?php echo esc_html($post_title); ?></h2>
                    <p><?php echo esc_html($post_description); ?></p>
                    <div class="service-list-of-heading">
                       <p><?php echo nl2br($blog_description); ?></p> 
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="service-view-media">
                    <img src="<?php echo get_the_post_thumbnail_url(get_the_ID(), 'large'); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" />
                </div>
            </div>
        </div>
    </div>
</div>


<div class="service-article-wrapper">
    <?php 
    if(!empty($detail_content)) {
    foreach($detail_content as $detail_content_val) { 
        $display_option = $detail_content_val['display_option'];
        $title = $detail_content_val['title'];
        $subtitle = $detail_content_val['subtitle'];
        $description = $detail_content_val['description'];
        $repeater_points = $detail_content_val['repeater_points'];
        $repeater_question_answer = $detail_content_val['repeater_question_answer'];
        $bottom_description = $detail_content_val['bottom_description'];

    ?>
        <article id="expect-during" class="service-list-info">
            <div class="inner-container">
              <div class="row">
                  <div class="service-inner-desc blogs">
                	<?php if(in_array("title", $display_option) && $title!="" ){ ?>
                    <h3><?php echo $title; ?></h3>
                    <?php } ?>
                    <?php if(in_array("subtitle", $display_option) && $subtitle!="" ){ ?>
                    <h6><?php echo $subtitle; ?></h6>
                    <?php } ?>
                    <?php if(in_array("description", $display_option) && $description!="" ){ ?>
                    <p><?php echo $description; ?></p>
                    <?php } ?>
                    
                    <?php if (in_array("repeater_points", $display_option)) { ?>
                        <ul>
                            <?php foreach($repeater_points as $vals){ 
                                $description = $vals['description'];
                            ?>
                                <li><?php echo $description; ?></li>
                            <?php } ?>
                        </ul>
                    <?php } ?> 
                    
                    <?php if (in_array("repeater_question_ans", $display_option)) { ?>
                        <?php 
                        foreach($repeater_question_answer as $val) { 
                            $repeater_heading = $val['heading'];
                            $description = $val['description'];
                        ?>
                            <h6><?php echo $repeater_heading; ?></h6>
                            <p><?php echo $description; ?></p>
                        <?php 
                        } 
                        ?>
                    <?php } ?>
                     <?php if (in_array("bottom_description", $display_option) && $bottom_description!="" ){ ?>
                     <p><?php echo $bottom_description; ?></p>
                    <?php } ?>
    
                    </div>
              </div>
            </div>
        </article>
    <?php } }?>

</div>

<div class="our-team-members-list blogs">
    <div class="inner-container">
        <!-- 🟢 Blog Posts Wrapper for AJAX content -->
        <div class="blog-posts-containers row">
            <h3>Recents Blogs</h3>
            <?php
            // Get current post ID (single page)
            
            // Get latest post (excluding current)

            
            $paged = get_query_var('paged') ? get_query_var('paged') : 1;
            
            $args = array(
                'post_type'      => $post_type,
                'posts_per_page' => 4,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'paged'          => $paged,
                'post__not_in'   => array($post_id),
            );
            
            $team_query = new WP_Query($args);
            
            if ($team_query->have_posts()) :
                while ($team_query->have_posts()) : $team_query->the_post();
            
                    $post_id = get_the_ID();
                    $blog_description = get_post_meta($post_id, 'blog_description', true);
                    ?>
                    <div class="col-md-3 team-member">
                        <div class="team-member-info">
                            <div class="team-media">
                                <?php if (has_post_thumbnail()) {
                                    $thumbnail_id = get_post_thumbnail_id();
                                    // Try to fetch the alt text set in the Media Library
                                    $image_alt = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
                                    // Fallback to image title if alt text is empty
                                    if (empty($image_alt)) {
                                        $image_alt = get_the_title($thumbnail_id);
                                    }
                                    echo get_the_post_thumbnail(get_the_ID(), 'full', array('alt' => esc_attr($image_alt)));
                                } else { ?>
                                    <img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/images/default-team-image.png" alt="Default Image">
                                <?php } ?>
                            </div>
                            <div class="our-team-member-desc blog">
                                <h6><?php echo get_the_date('F d, Y'); ?></h6>
                                <a href="<?php echo get_permalink($post_id); ?>"><h4><?php the_title(); ?></h4></a>
                                <p><?php echo wp_trim_words($blog_description, 15, '...'); ?></p>
                                <?php if (str_word_count(strip_tags($blog_description)) > 15) { ?>
                                    <a href="<?php echo get_permalink($post_id); ?>" class="know-more-link">Know More</a>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    <?php
                endwhile;
                wp_reset_postdata();
            else :
                echo '<p class="no-posts-found text-center">No Blogs found.</p>';
            endif;
            ?>
        </div>

    </div>
</div>

<?php endwhile;?>
<?php endif; ?>
<?php get_footer(); ?>
<style>
    .custom-breadcrumb .current {font-size:16px;}
    .service-list-of-heading p a{text-decoration:none;font-weight: 700; color: #002f87;}
    .our-team-member-desc.blog a{text-decoration: none;font-weight: 700; color: #002f87;}
    .our-team-member-desc.blog h4{color: #002F87;}
    .service-inner-desc.blogs p a{text-decoration:none;font-weight: 700; color: #002f87;}
    .our-team-member-desc.blog a.know-more-link{text-decoration: underline;color: #d163ce; font-weight: normal;}

</style>