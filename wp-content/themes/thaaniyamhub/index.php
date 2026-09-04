<?php
/**
 * The main template file
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 *
 * @package ThaaniyamHub
 */

get_header();
?>

<main id="primary" class="site-main">
    <div class="inner-container">
        <?php
        if ( have_posts() ) :
            while ( have_posts() ) :
                the_post();
                the_content();
            endwhile;
        else :
            ?>
            <p><?php esc_html_e( 'Sorry, no posts matched your criteria.', 'thaaniyamhub' ); ?></p>
            <?php
        endif;
        ?>
    </div>
</main>

<?php
get_footer();
