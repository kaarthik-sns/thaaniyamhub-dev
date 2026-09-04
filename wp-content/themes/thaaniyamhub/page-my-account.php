<?php
get_header();
?>

<main id="primary" class="site-main my-account-page">
    <div class="inner-container">

        <h1 class="page-title">My Account</h1>

        <?php
        if ( function_exists( 'is_account_page' ) && is_account_page() ) {
            echo do_shortcode('[woocommerce_my_account]');
        }
        ?>

    </div>
</main>

<?php
get_footer();
