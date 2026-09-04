<?php get_header(); ?>

<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
<?php
    $post_id = get_the_ID();
    $post_title = get_the_title($post_id);
    $blog_description = get_post_meta($post_id, 'blog_description', true);
    $detail_content = get_field('detail_content', $post_id);

?>

    <div class="inner-banner-sec" style="background:url(<?php echo esc_url( site_url( '/wp-content/uploads/2026/02/inner-blog-banner.png' ) ); ?>) no-repeat;">
        <div class="inner-container">
            <div class="row ">
                <div class="col-md-12">
                    <div class="inner-banner-content left-service-header">
                        <h1><?php echo esc_html($post_title); ?></h1>
                        <p><?php echo esc_html($post_description); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="service-article-wrapper blog-article-info">
        <?php 
        if(!empty($detail_content)) {
        foreach($detail_content as $detail_content_val) { 
            $display_options = $detail_content_val['display_options'];
            $title = $detail_content_val['title'];
            $subtitle = $detail_content_val['subtitle'];
            $description = $detail_content_val['description'];
            $image = $detail_content_val['image'];
            $repeater_points = $detail_content_val['repeater_points'];
            $repeater_question_answer = $detail_content_val['repeater_question_answer'];
            $bottom_description = $detail_content_val['bottom_description'];

        ?>
            <article id="expect-during" class="blog-list-info">
                <div class="inner-container">
                    <div class="row">
                        <div class="service-inner-desc blogs">
                            <?php if(in_array("title", $display_options) && $title!="" ){ ?>
                                <h3><?php echo $title; ?></h3>
                            <?php } ?>
                            <?php if(in_array("subtitle", $display_options) && $subtitle!="" ){ ?>
                                <h6><?php echo $subtitle; ?></h6>
                            <?php } ?>
                            <?php if(in_array("description", $display_options) && $description!="" ){ ?>
                                <p><?php echo $description; ?></p>
                            <?php } ?>
                            <?php if(in_array("image", $display_options) && $image!="" ){ ?>
                               <img src="<?php echo $image['url']; ?>" alt="<?php echo $image['title']; ?>" class="img-fluid"/>
                            <?php } ?>
                            
                            <?php if (in_array("repeater_points", $display_options)) { ?>
                                <ul>
                                    <?php foreach($repeater_points as $vals){ 
                                        $list_desc = $vals['list_desc'];
                                    ?>
                                        <li><?php echo $list_desc; ?></li>
                                    <?php } ?>
                                </ul>
                            <?php } ?> 
                            
                            <?php if (in_array("repeater_question_answer", $display_options)) { ?>
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
                            <?php if (in_array("bottom_description", $display_options) && $bottom_description!="" ){ ?>
                                <p><?php echo $bottom_description; ?></p>
                            <?php } ?>
            
                        </div>
                    </div>
                </div>
            </article>
        <?php } }?>
    </div>

    <!-- <div class="our-team-members-list blogs">
        <div class="inner-container">
            <div class="blog-posts-containers row">
                <h3>Recents Blogs</h3>
                <?php            
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
                                        <img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/images/default-team-image.png" alt="Default Image" class="img-fluid">
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
    </div> -->
    

<?php endwhile;?>
<?php endif; ?>
<?php get_footer(); ?>

<style>

</style>