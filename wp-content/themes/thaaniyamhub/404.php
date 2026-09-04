<?php
get_header();
?>
<?php $upload_dir = wp_upload_dir(); ?>
<div class="oops-banner-bg" style="background-image: url('<?php echo esc_url( $upload_dir['baseurl'] ); ?>/2026/02/404.png');">
    <div class="inner-container">
        <div class="row">
            <div class="col-md-12">
                    <div class="oops-info-content">
                        <h1>Page not found</h1>
                        <p class="oops-p">Oops! Looks like this page couldn’t be found.</p>
                        <p class="oops-sub-p">The page you’re looking for may have been moved, renamed, or no longer exists.</p>
                       <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Back to home</a>
                    </div>
            </div>
        </div>
    </div>
</div>
<?php get_footer(); ?>

