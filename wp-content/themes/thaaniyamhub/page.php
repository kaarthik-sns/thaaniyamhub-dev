<?php get_header(); ?>

<?php
if ( have_posts() ) :
    while ( have_posts() ) : the_post();

        $layout_page = get_field('layouts', get_the_ID());

        if ( !empty($layout_page) ) :

            $ly_cnt = 0;
            foreach ( $layout_page as $layout ) :
                $ly_cnt++;
                global $layout, $layout_id;
                $layout_id = $ly_cnt . "-" . get_the_ID();

                if ( !empty($layout) ) {
                    get_template_part( 'template/content/content', $layout['layout_type'] );
                }

            endforeach;

        else :
            // 🔥 FALLBACK: Show normal page content (Wishlist, Cart, etc.)
            echo '<div class="container"><div class="row"><div class="col-12">';
                the_content();
            echo '</div></div></div>';

        endif;

    endwhile;
endif;
?>

<?php get_footer(); ?>
