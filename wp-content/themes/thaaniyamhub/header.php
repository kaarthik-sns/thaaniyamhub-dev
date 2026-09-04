<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php wp_title('|', true, 'right'); ?></title>
    <script type="text/javascript">
    (function() {
        function getCookie(name) {
            var v = "; " + document.cookie;
            var p = v.split("; " + name + "=");
            if (p.length === 2) return p.pop().split(";").shift();
            return null;
        }
        var logoutSignal = getCookie('thaaniyam_just_logged_out');
        var isGuest = !document.body || !document.body.classList.contains('logged-in');
        var qtyCookie = getCookie('fkcart_cart_qty') || getCookie('fkcart_qty') || getCookie('woocommerce_items_in_cart');
        
        if (logoutSignal || (isGuest && (!qtyCookie || qtyCookie === '0'))) {
            try {
                if (typeof sessionStorage !== 'undefined') {
                    for (var i = sessionStorage.length - 1; i >= 0; i--) {
                        var k = sessionStorage.key(i);
                        if (k && (k.indexOf('fkcart_') === 0 || k.indexOf('wc_') === 0 || k.indexOf('woocommerce_') === 0)) {
                            sessionStorage.removeItem(k);
                        }
                    }
                }
                if (typeof localStorage !== 'undefined') {
                    for (var j = localStorage.length - 1; j >= 0; j--) {
                        var lk = localStorage.key(j);
                        if (lk && (lk.indexOf('fkcart_') === 0 || lk.indexOf('wc_') === 0 || lk.indexOf('woocommerce_') === 0)) {
                            localStorage.removeItem(lk);
                        }
                    }
                }
            } catch(e) {}
        }
    })();
    </script>
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>


    <?php
    $cart_count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;


    $user_logged_in = is_user_logged_in();

    /* ⭐ ADD THIS BLOCK */
    $header_class = get_field('header_bg_class', get_queried_object_id());
    if (empty($header_class)) {
        $header_class = 'header-white-color';
    }
    ?>

    <header class="top-header <?php echo esc_attr($header_class); ?>">
        <div class="inner-container mob-top-header">
            <div class="row">
                <nav class="navbar navbar-expand-lg navbar-light nav-hedare-menu">

                    <div class="header-part-info">

                        <!-- LOGO -->
                        <div class="top-logo">
                            <a class="navbar-brand" href="<?php echo esc_url(home_url('/')); ?>">

                                <?php
                                $header_logo_url = get_theme_mod('logo');

                                if ($header_logo_url) {
                                    // 1. Customizer → Company Logo Image
                                    ?>
                                    <img src="<?php echo esc_url($header_logo_url); ?>" alt="<?php bloginfo('name'); ?>">
                                    <?php
                                } elseif (has_custom_logo()) {
                                    // 2. WordPress built-in custom logo
                                    $custom_logo_id = get_theme_mod('custom_logo');
                                    $logo = wp_get_attachment_image_src($custom_logo_id, 'full');
                                    if ($logo) {
                                        ?>
                                        <img src="<?php echo esc_url($logo[0]); ?>" alt="<?php bloginfo('name'); ?>">
                                        <?php
                                    }
                                } else {
                                    // 3. Default theme asset fallback
                                    ?>
                                    <img src="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/images/thaaniyam-logo.png'); ?>" alt="<?php bloginfo('name'); ?>">
                                    <?php
                                }
                                ?>

                            </a>

                            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas"
                                data-bs-target="#main-menu">
                                <span class="navbar-toggler-icon"></span>
                            </button>
                        </div>

                        <!-- MOBILE MENU -->
                        <div class="right-part">
                            <div class="header-menu-part">
                                <div class="offcanvas offcanvas-end" tabindex="-1" id="main-menu">
                                    <div class="offcanvas-header">
                                        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
                                    </div>

                                    <div class="offcanvas-body">
                                        <ul class="navbar-nav flex-grow-1">
                                            <?php
                                            wp_nav_menu(array(
                                                'container' => '',
                                                'items_wrap' => '%3$s',
                                                'theme_location' => 'primary-menu',
                                                'walker' => new Custom_Nav_Walker(),
                                            ));
                                            ?>
                                        </ul>

                                        <!-- MOBILE ICONS -->
                                        <div class="right-user-part">
                                            <div class="top-right-link">
                                                <ul class="right-user-link d-flex d-lg-none">
                                                    <li class="search-icon">
                                                        <a href="javascript:void(0);" id="mobileSearchToggle">
                                                            <img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/images/search.svg" alt="Search">
                                                        </a>
                                                    </li>

                                                    <!-- USER -->
                                                    <li class="user-icon">
                                                        <a
                                                            href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>">
                                                            <img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/images/user.svg"
                                                                alt="User">
                                                            <?php if ($user_logged_in): ?>
                                                                <span class="badge">1</span>
                                                            <?php endif; ?>
                                                        </a>
                                                    </li>

                                                    <!-- WISHLIST -->
                                                    <li class="wishlist-icon">
                                                        <a href="<?php echo esc_url(site_url('/wishlist')); ?>">
                                                            <img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/images/heart.svg"
                                                                alt="Wishlist">
                                                            <span class="custom-wishlist-count badge">
                                                                <?php echo YITH_WCWL()->count_products(); ?>
                                                            </span>
                                                        </a>
                                                    </li>
                                                    <!-- CART -->
                                                    <li class="cart-icon">
                                                        <a href="<?php echo esc_url(wc_get_cart_url()); ?>">
                                                            <?php echo do_shortcode('[fk_cart_menu]'); ?>
                                                        </a>
                                                    </li>

                                                </ul>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- DESKTOP ICONS -->
                        <div class="right-user-part">
                            <div class="top-right-link">
                                <ul class="right-user-link d-none d-lg-flex">

                                    <li class="search-icon">
                                        <a href="javascript:void(0);" id="searchToggle">
                                            <img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/images/search.svg" alt="Search">
                                        </a>
                                    </li>

                                    <!-- USER -->
                                    <li class="user-icon">
                                        <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>">
                                            <img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/images/user.svg"
                                                alt="User">
                                            <?php if ($user_logged_in): ?>
                                                <span class="badge">1</span>
                                            <?php endif; ?>
                                        </a>
                                    </li>

                                    <!-- WISHLIST -->
                                    <li class="wishlist-icon">
                                        <a href="<?php echo esc_url(site_url('/wishlist')); ?>">
                                            <img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/images/heart.svg"
                                                alt="Wishlist">
                                            <span class="custom-wishlist-count badge">
                                                <?php echo YITH_WCWL()->count_products(); ?>
                                            </span>
                                        </a>
                                    </li>
                                    <!-- CART -->
                                    <li class="cart-icon">
                                        <a href="javascript:void(0)">
                                            <?php echo do_shortcode('[fk_cart_menu]'); ?>
                                        </a>
                                        <!-- <?php echo do_shortcode('[fk_cart_menu]'); ?> -->
                                    </li>

                                </ul>
                            </div>
                        </div>

                    </div>
                </nav>
            </div>
        </div>
        <div class="header-search-wrapper" id="headerSearch">

            <div class="inner-container">

                <div class="search-container">

                    <form class="header-search-form"
                        role="search"
                        method="get"
                        action="<?php echo esc_url(home_url('/')); ?>">

                        <span class="search-icon-left">
                            <img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/images/search.svg">
                        </span>

                        <input
                            type="search"
                            id="product-search"
                            name="s"
                            autocomplete="off"
                            placeholder="Search for millets, readymix, healthy snacks..."
                            value="<?php echo get_search_query(); ?>">

                        <input type="hidden" name="post_type" value="product">

                       <button type="submit" class="search-btn">
                            <img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/images/search.svg" alt="Search">
                        </button>

                        <button type="button" id="searchClose" class="search-close">
                            ×
                        </button>

                    </form>

                    <div id="live-search-results"></div>
                </div>              
            </div>
        </div>
    </header>

<script>
    var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const desktop  = document.getElementById("searchToggle");
    const mobile   = document.getElementById("mobileSearchToggle");
    const search   = document.getElementById("headerSearch");
    const closeBtn = document.getElementById("searchClose");
    const input    = document.getElementById("product-search");
    const results  = document.getElementById("live-search-results");
    function openSearch(e) {
        e.preventDefault();
        e.stopPropagation();
        search.classList.toggle("active");
        if (search.classList.contains("active")) {
            setTimeout(function () {
                input.focus();
                input.click();
            }, 300);

        } else {
            closeSearch();

        }
    }

    function closeSearch() {
        search.classList.remove("active");

        results.style.display = "none";
        results.innerHTML = "";

    }
    if (desktop) {
        desktop.addEventListener("click", openSearch);
    }
    if (mobile) {
        mobile.addEventListener("click", function(e){
            e.preventDefault();
            e.stopPropagation();
            // Close Bootstrap Offcanvas (if open)
            const offcanvasElement = document.getElementById("main-menu");
            if(offcanvasElement){
                const bsOffcanvas = bootstrap.Offcanvas.getInstance(offcanvasElement);
                if(bsOffcanvas){
                    bsOffcanvas.hide();
                }
            }
            setTimeout(function(){
                search.classList.toggle("active");
                if(search.classList.contains("active")){
                    input.focus();
                    input.click();
                }else{
                    closeSearch();
                }
            },300);
        });
    }
    if (closeBtn) {
        closeBtn.addEventListener("click", function(e){
            e.preventDefault();
            closeSearch();
        });
    }
    document.addEventListener("click", function (e) {
        if (
            search.classList.contains("active") &&
            !search.contains(e.target) &&
            (!desktop || !desktop.contains(e.target)) &&
            (!mobile || !mobile.contains(e.target))
        ) {
            closeSearch();
        }
    });
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            closeSearch();
        }
    });
});

jQuery(function ($) {
    $('#product-search').on('keyup', function () {
        var keyword = $(this).val();
        if (keyword.length < 2) {
            $('#live-search-results').hide().html('');
            return;
        }
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'live_product_search',
                keyword: keyword
            },
            beforeSend: function () {
                $('#live-search-results')
                    .html('<div class="loading">Searching...</div>')
                    .show();
            },
            success: function (response) {
                $('#live-search-results').html(response).show();
            },
            error: function () {
                $('#live-search-results')
                    .html('<div class="no-result">Something went wrong.</div>')
                    .show();
            }
        });
    });
    $('#product-search').on('focus', function(){
        if($(this).val().length >= 2){
            $('#live-search-results').show();
        }
    });
    $(document).on('click', function(e){
        if(!$(e.target).closest('.search-container').length){
            $('#live-search-results').hide();
        }
    });
});
</script>