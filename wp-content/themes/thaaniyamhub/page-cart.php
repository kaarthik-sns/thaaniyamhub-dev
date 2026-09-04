<?php
/*
Template Name: Cart Page
*/
get_header();
?>

<main id="primary" class="site-main">
    <div class="inner-container">
        <h1 class="page-title">Your Cart</h1>
        <?php echo do_shortcode('[woocommerce_cart]'); ?>
    </div>
</main>

<?php get_footer(); ?>
