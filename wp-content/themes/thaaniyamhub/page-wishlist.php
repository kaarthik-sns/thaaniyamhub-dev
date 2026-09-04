<?php
/**
 * Template for the Wishlist page (slug: wishlist)
 * Forces header/footer rendering via PHP template
 * instead of FSE block template path.
 */

// Add a unique body class BEFORE get_header() so it's included in body_class().
// The CSS below is scoped to this class → safe even if server caches this page.
add_filter('body_class', function ($classes) {
    $classes[] = 'wishlist-sticky-header';
    return $classes;
});

get_header();
?>

<style>
    /*
     * Sticky header fix — scoped via .wishlist-sticky-header body class.
     * This class is only added on the wishlist page template (page-wishlist.php).
     * Safe against server-side full-page caching: the style only applies when
     * this body class is present, which never happens on any other page.
     */
    .wishlist-sticky-header .top-header,
    .wishlist-sticky-header .top-header.fixed {
        position: sticky !important;
        top: 0 !important;
    }
</style>

<main id="primary" class="site-main wishlist-page">
    <div class="inner-container">

        <?php echo do_shortcode('[yith_wcwl_wishlist]'); ?>

    </div>
</main>

<?php get_footer(); ?>