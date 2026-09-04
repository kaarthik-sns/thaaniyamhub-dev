<?php
get_header();
?>

<main id="primary" class="site-main" style="padding: 40px 0;">
    <div class="inner-container">
        <h1 class="page-title">Checkout</h1>

        <?php
        if ( function_exists( 'is_checkout' ) && is_checkout() ) {
            echo do_shortcode('[woocommerce_checkout]');
        }
        ?>
    </div>
</main>

<?php
get_footer();
