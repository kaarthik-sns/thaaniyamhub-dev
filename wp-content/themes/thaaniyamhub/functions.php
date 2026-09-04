<?php

function thaaniyamhub_enqueue_styles()
{
    // Get the current theme version
    $theme_version = wp_get_theme()->get('Version');
    // Additional custom image sizes
    add_image_size('small-thumbnail', 150, 150, true);  // Crop to 150x150 pixels
    add_image_size('medium-thumbnail', 320, 320, true); // Crop to 300x200 pixels
    add_image_size('large-thumbnail', 600, 400, false); // R



    // Enqueue additional styles from the child theme

    wp_enqueue_style('parent-fontawesome-style', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css', array(), $theme_version);

    wp_enqueue_style('parent-bootstrap-style', get_stylesheet_directory_uri() . '/assets/css/bootstrap.min.css', array(), $theme_version);

    wp_enqueue_style('parent-slick-style', get_stylesheet_directory_uri() . '/assets/css/slick.css', array(), $theme_version);

    wp_enqueue_style('parent-aos-style', 'https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css', array(), $theme_version);

    // wp_enqueue_style('parent-styles-style', get_stylesheet_directory_uri() . '/assets/css/default.css', array(), $theme_version);

    $styles_css_path = get_stylesheet_directory() . '/assets/css/styles.css';
    $styles_version = file_exists($styles_css_path) ? filemtime($styles_css_path) : $theme_version;
    wp_enqueue_style('parent-styles-custom', get_stylesheet_directory_uri() . '/assets/css/styles.css', array(), $styles_version);

    $responsive_css_path = get_stylesheet_directory() . '/assets/css/responsive.css';
    $responsive_version = file_exists($responsive_css_path) ? filemtime($responsive_css_path) : $theme_version;
    wp_enqueue_style('parent-responsive-style', get_stylesheet_directory_uri() . '/assets/css/responsive.css', array(), $responsive_version);

    $inner_css_path = get_stylesheet_directory() . '/assets/css/inner-styles.css';
    $inner_version = file_exists($inner_css_path) ? filemtime($inner_css_path) : $theme_version;
    wp_enqueue_style('parent-inner-styles-custom', get_stylesheet_directory_uri() . '/assets/css/inner-styles.css', array(), $inner_version);


    // Load WordPress jQuery only
    wp_enqueue_script('jquery');

    // Load qTip (needed for WCFM)
    wp_enqueue_script(
        'qtip',
        'https://cdnjs.cloudflare.com/ajax/libs/qtip2/3.0.3/jquery.qtip.min.js',
        ['jquery'],
        null,
        true
    );

    // Your scripts
    wp_enqueue_script(
        'parent-popper-js',
        'https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js',
        [],
        $theme_version,
        true
    );

    wp_enqueue_script(
        'parent-bootstrapbundle-js',
        get_stylesheet_directory_uri() . '/assets/js/bootstrap.bundle.min.js',
        ['jquery'],
        $theme_version,
        true
    );

    wp_enqueue_script(
        'parent-slickmin-js',
        get_stylesheet_directory_uri() . '/assets/js/slick.min.js',
        ['jquery'],
        $theme_version,
        true
    );

    wp_enqueue_script(
        'parent-aos-js',
        'https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js',
        [],
        $theme_version,
        true
    );

    wp_enqueue_script(
        'custom-ajax-add-to-cart',
        get_stylesheet_directory_uri() . '/assets/js/custom-ajax-add-to-cart.js',
        array('jquery'),
        $theme_version,
        true
    );

    wp_localize_script(
        'custom-ajax-add-to-cart',
        'custom_ajax_cart_params',
        array(
            'ajax_url' => esc_url(home_url('/?wc-ajax=add_to_cart'))
        )
    );

}

add_action('wp_enqueue_scripts', 'thaaniyamhub_enqueue_styles');

/**
 * Output product ID marker in FunnelKit Cart items so custom JS can accurately track items in cart.
 */
add_action('fkcart_before_cart_item', function($cart_item, $cart_item_key) {
    $product_id = !empty($cart_item['product_id']) ? $cart_item['product_id'] : (!empty($cart_item['product']) ? $cart_item['product']->get_id() : 0);
    if ($product_id) {
        echo '<span class="fkcart-item-product-id-marker" data-product_id="' . esc_attr($product_id) . '" style="display:none;"></span>';
    }
}, 10, 2);



/**
 * Customizer settings
 */
function thaaniyamhub_customize_register($wp_customize)
{
    $wp_customize->add_setting(
        'logo',
        array(
            'default' => '',
            'transport' => 'refresh',
            'sanitize_callback' => 'esc_url_raw'
        )
    );

    $wp_customize->add_control(new WP_Customize_Image_Control(
        $wp_customize,
        'logo',
        array(
            'label' => __('Company Logo Image'),
            'description' => esc_html__('This is the description for the Image Control'),
            'section' => 'title_tagline', // This is the Site Identity section
            'button_labels' => array( // Optional.
                'select' => __('Select Image'),
                'change' => __('Change Image'),
                'remove' => __('Remove'),
                'default' => __('Default'),
                'placeholder' => __('No image selected'),
                'frame_title' => __('Select Image'),
                'frame_button' => __('Choose Image'),
            )
        )
    ));

    $wp_customize->add_setting('top_header_text', array(
        'default' => ''
    ));

    $wp_customize->add_control('top_header_text', array(
        'label' => 'Top Header Text',
        'description' => '',
        'section' => 'title_tagline',
        'type' => 'textarea',
    ));

    $wp_customize->add_section('thaaniyamhub_footer_settings', array(
        'title' => 'Footer Settings',
    ));

    $wp_customize->add_setting(
        'footer_logo',
        array(
            'default' => '',
            'transport' => 'refresh',
            'sanitize_callback' => 'esc_url_raw'
        )
    );

    $wp_customize->add_control(new WP_Customize_Image_Control(
        $wp_customize,
        'footer_logo',
        array(
            'label' => __('Footer Logo Image'),
            'description' => esc_html__('This is the description for the Image Control'),
            'section' => 'thaaniyamhub_footer_settings',
            'button_labels' => array( // Optional.
                'select' => __('Select Image'),
                'change' => __('Change Image'),
                'remove' => __('Remove'),
                'default' => __('Default'),
                'placeholder' => __('No image selected'),
                'frame_title' => __('Select Image'),
                'frame_button' => __('Choose Image'),
            )
        )
    ));


    $wp_customize->add_setting('address', array(
        'default' => ''
    ));

    $wp_customize->add_control('address', array(
        'label' => 'Address',
        'description' => '',
        'section' => 'thaaniyamhub_footer_settings',
        'type' => 'textarea',
    ));
    $wp_customize->add_setting('pageaddress', array(
        'default' => ''
    ));

    $wp_customize->add_control('pageaddress', array(
        'label' => 'Address2',
        'description' => '',
        'section' => 'thaaniyamhub_footer_settings',
        'type' => 'textarea',
    ));



    $wp_customize->add_setting('email_address', array(
        'default' => ''
    ));

    $wp_customize->add_control('email_address', array(
        'label' => 'Email Address',
        'description' => '',
        'section' => 'thaaniyamhub_footer_settings',
        'type' => 'text',
    ));

    $wp_customize->add_setting('support_email', array(
        'default' => ''
    ));

    $wp_customize->add_control('support_email', array(
        'label' => 'Support Email',
        'description' => '',
        'section' => 'thaaniyamhub_footer_settings',
        'type' => 'text',
    ));


    $wp_customize->add_setting('phone_number', array(
        'default' => ''
    ));

    $wp_customize->add_control('phone_number', array(
        'label' => 'Phone Number',
        'description' => '',
        'section' => 'thaaniyamhub_footer_settings',
        'type' => 'text',
    ));



    $wp_customize->add_section('thaaniyamhub_social_link_settings', array(
        'title' => 'Social Link Settings',
    ));

    $wp_customize->add_setting('social_link_1', array(
        'default' => ''
    ));

    $wp_customize->add_control('social_link_1', array(
        'label' => 'Facebook Link',
        'description' => '',
        'section' => 'thaaniyamhub_social_link_settings',
        'type' => 'text',
    ));

    $wp_customize->add_setting('social_link_2', array(
        'default' => ''
    ));

    $wp_customize->add_control('social_link_2', array(
        'label' => 'Twitter Link',
        'description' => '',
        'section' => 'thaaniyamhub_social_link_settings',
        'type' => 'text',
    ));

    $wp_customize->add_setting('social_link_3', array(
        'default' => ''
    ));

    $wp_customize->add_control('social_link_3', array(
        'label' => 'Instagram Link',
        'description' => '',
        'section' => 'thaaniyamhub_social_link_settings',
        'type' => 'text',
    ));

    $wp_customize->add_setting('social_link_4', array(
        'default' => ''
    ));

    $wp_customize->add_control('social_link_4', array(
        'label' => 'Linkedin Link',
        'description' => '',
        'section' => 'thaaniyamhub_social_link_settings',
        'type' => 'text',
    ));

    $wp_customize->add_setting('social_link_5', array(
        'default' => ''
    ));

    $wp_customize->add_control('social_link_5', array(
        'label' => 'Google My Business Link',
        'description' => '',
        'section' => 'thaaniyamhub_social_link_settings',
        'type' => 'text',
    ));

    $wp_customize->add_section('thaaniyamhub_copyright_settings', array(
        'title' => 'Copyright Settings',
    ));
    $wp_customize->add_setting('copyright_text', array(
        'default' => ''
    ));
    $wp_customize->add_control('copyright_text', array(
        'label' => 'Copyright Text',
        'description' => '',
        'section' => 'thaaniyamhub_copyright_settings',
        'type' => 'textarea',
    ));


}
add_action('customize_register', 'thaaniyamhub_customize_register');

function thaaniyamhub_register_menus()
{
    register_nav_menus(
        array(
            'primary-menu' => __('Primary Menu', 'thaaniyamhub'),
            'first-footer-menu' => __('First Footer Menu', 'thaaniyamhub'),
            'second-footer-menu' => __('Second Footer Menu', 'thaaniyamhub'),
            'third-footer-menu' => __('Third Footer Menu', 'thaaniyamhub'),
            'fourth-footer-menu' => __('Fourth Footer Menu', 'thaaniyamhub'),
            'fifth-footer-menu' => __('Fifth Footer Menu', 'thaaniyamhub')
        )
    );
}
add_action('init', 'thaaniyamhub_register_menus');
class Custom_Nav_Walker extends Walker_Nav_Menu
{
    function start_lvl(&$output, $depth = 0, $args = array())
    {
        $indent = str_repeat("\t", $depth);

        if ($depth == 0) {
            $output .= "\n$indent<ul class=\"sub-menu dropdown-menu\">\n";
        } else {
            $output .= "\n$indent<ul class=\"sub-sub-menu\">\n";
        }
    }

    function end_lvl(&$output, $depth = 0, $args = array())
    {
        $indent = str_repeat("\t", $depth);
        $output .= "$indent</ul>\n";
    }

    function start_el(&$output, $item, $depth = 0, $args = array(), $id = 0)
    {
        $indent = ($depth) ? str_repeat("\t", $depth) : '';

        $classes = empty($item->classes) ? array() : (array) $item->classes;
        $classes[] = 'menu-item-' . $item->ID;

        $has_children = in_array('menu-item-has-children', $classes);

        if ($has_children && $depth == 0) {
            $classes[] = 'dropdown';
        }

        $class_names = implode(' ', array_filter($classes));

        $output .= $indent . '<li class="' . esc_attr($class_names) . '">';

        $attributes = '';
        $attributes .= !empty($item->url) ? ' href="' . esc_url($item->url) . '"' : '';
        $attributes .= !empty($item->target) ? ' target="' . esc_attr($item->target) . '"' : '';

        $link_class = 'nav-link';

        if ($has_children && $depth == 0) {
            $link_class .= ' dropdown-toggle';
        }

        $output .= '<a class="' . $link_class . '"' . $attributes . '>';

        $output .= esc_html($item->title);

        $output .= '</a>';
    }

    function end_el(&$output, $item, $depth = 0, $args = array())
    {
        $output .= "</li>\n";
    }
}

add_action('after_setup_theme', 'childtheme_add_woocommerce_support');
function childtheme_add_woocommerce_support()
{
    add_theme_support('woocommerce');
}

add_filter('use_block_editor_for_post_type', function ($use, $post_type) {
    if ($post_type === 'page') {
        return false;
    }
    return $use;
}, 10, 2);

add_theme_support('wc-product-gallery-zoom');
add_theme_support('wc-product-gallery-lightbox');
add_theme_support('wc-product-gallery-slider');

remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_title', 5);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50);

// Remove default Woo wrappers
remove_action('woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10);
remove_action('woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10);

// Add our own wrappers
add_action('woocommerce_before_main_content', function () {
    echo '<div class="custom-woo-wrapper">';
}, 10);

add_action('woocommerce_after_main_content', function () {
    echo '</div>';
}, 10);
add_theme_support('woocommerce');

add_filter('woocommerce_ship_to_different_address_checked', '__return_false');
add_action('wp_footer', function () {
    if (function_exists('is_checkout') && is_checkout()) {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#ship-to-different-address-checkbox').prop('checked', false);
            $('.shipping_address').hide();
        });
        </script>
        <?php
    }
});

add_filter('woocommerce_account_menu_items', 'thaaniyam_remove_downloads_menu');
function thaaniyam_remove_downloads_menu($items)
{
    unset($items['downloads']);
    return $items;
}
// add_filter( 'yith_wcwl_button_label', function() {
//     return '<i class="fa fa-heart"></i>';
// });



add_action('woocommerce_before_quantity_input_field', 'custom_quantity_minus');
function custom_quantity_minus()
{
    echo '<button type="button" class="qty-minus">−</button>';
}

add_action('woocommerce_after_quantity_input_field', 'custom_quantity_plus');
function custom_quantity_plus()
{
    echo '<button type="button" class="qty-plus">+</button>';
}

// price filter fuction
add_action('woocommerce_product_query', 'apply_custom_price_filter');
function apply_custom_price_filter($query)
{

    if (is_admin() || !is_shop() && !is_product_category()) {
        return;
    }

    $min = isset($_GET['min_price']) ? floatval($_GET['min_price']) : '';
    $max = isset($_GET['max_price']) ? floatval($_GET['max_price']) : '';

    if ($min === '' && $max === '') {
        return;
    }

    $meta_query = (array) $query->get('meta_query');

    $meta_query[] = [
        'key' => '_price',
        'value' => [
            $min !== '' ? $min : 0,
            $max !== '' ? $max : PHP_INT_MAX
        ],
        'compare' => 'BETWEEN',
        'type' => 'NUMERIC'
    ];

    $query->set('meta_query', $meta_query);
}

add_filter('woocommerce_has_block_template', '__return_false');

// ADD FIELD ON "ADD NEW CATEGORY" PAGE
add_action('product_cat_add_form_fields', 'add_collection_image_field', 10, 2);
function add_collection_image_field()
{
    ?>
    <div class="form-field term-group">
        <label for="collection_image_id">Collection Image</label>
        <input type="hidden" id="collection_image_id" name="collection_image_id" value="">
        <div id="collection_image_wrapper"></div>
        <p>
            <input type="button" class="button button-secondary collection_image_upload" value="Add Collection Image" />
            <input type="button" class="button button-secondary collection_image_remove" value="Remove Image" />
        </p>
    </div>
    <?php
}

// ADD FIELD ON "EDIT CATEGORY" PAGE
add_action('product_cat_edit_form_fields', 'edit_collection_image_field', 10, 2);
function edit_collection_image_field($term, $taxonomy)
{
    $collection_image_id = get_term_meta($term->term_id, 'collection_image_id', true);
    ?>
    <tr class="form-field term-group-wrap">
        <th scope="row"><label for="collection_image_id">Collection Image</label></th>
        <td>
            <input type="hidden" id="collection_image_id" name="collection_image_id"
                value="<?php echo esc_attr($collection_image_id); ?>">
            <div id="collection_image_wrapper">
                <?php if ($collection_image_id) {
                    echo wp_get_attachment_image($collection_image_id, 'thumbnail');
                } ?>
            </div>
            <p>
                <input type="button" class="button button-secondary collection_image_upload" value="Add Collection Image" />
                <input type="button" class="button button-secondary collection_image_remove" value="Remove Image" />
            </p>
        </td>
    </tr>
    <?php
}
add_action('created_product_cat', 'save_collection_image_field', 10, 2);
add_action('edited_product_cat', 'save_collection_image_field', 10, 2);

function save_collection_image_field($term_id)
{
    if (isset($_POST['collection_image_id'])) {
        update_term_meta($term_id, 'collection_image_id', absint($_POST['collection_image_id']));
    }
}
add_action('admin_footer', 'collection_image_media_script');
function collection_image_media_script()
{
    ?>
    <script>
        jQuery(document).ready(function ($) {

            let mediaUploader;

            $('.collection_image_upload').on('click', function (e) {
                e.preventDefault();

                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }

                mediaUploader = wp.media({
                    title: 'Choose Collection Image',
                    button: { text: 'Use this image' },
                    multiple: false
                });

                mediaUploader.on('select', function () {
                    let attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#collection_image_id').val(attachment.id);
                    $('#collection_image_wrapper').html('<img src="' + attachment.sizes.thumbnail.url + '" />');
                });

                mediaUploader.open();
            });

            $('.collection_image_remove').on('click', function () {
                $('#collection_image_id').val('');
                $('#collection_image_wrapper').html('');
            });

        });
    </script>
    <?php
}

function top_picks_slider_assets()
{

    wp_enqueue_style(
        'fontawesome',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css'
    );

    wp_enqueue_style(
        'slick-css',
        'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css'
    );

    wp_enqueue_style(
        'slick-theme-css',
        'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick-theme.css'
    );

    wp_enqueue_script('jquery');

    wp_enqueue_script(
        'slick-js',
        'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js',
        array('jquery'),
        null,
        true
    );

    wp_add_inline_script('slick-js', "
        jQuery(document).ready(function($){
            $('.top-picks-slider').slick({
                slidesToShow: 4,
                slidesToScroll: 1,
                arrows: true,
                dots: false,
                autoplay: true,
                autoplaySpeed: 3000,
                infinite: true,
                prevArrow: '<button type=\"button\" class=\"slick-prev custom-arrow\"><i class=\"fa-solid fa-chevron-left\"></i></button>',
                nextArrow: '<button type=\"button\" class=\"slick-next custom-arrow\"><i class=\"fa-solid fa-chevron-right\"></i></button>',
                responsive: [
                    { breakpoint: 1500, settings: { slidesToShow: 3 } },
                    { breakpoint: 1000, settings: { slidesToShow: 2 } },
                    { breakpoint: 480, settings: { slidesToShow: 1 } }
                ]
            });
        });
    ");
}
add_action('wp_enqueue_scripts', 'top_picks_slider_assets');

add_action('pre_get_posts', 'show_all_products_no_limit');
function show_all_products_no_limit($query)
{
    if (!is_admin() && $query->is_main_query() && (is_shop() || is_product_category() || is_product_tag())) {
        $query->set('posts_per_page', 12);
    }
}
//checkout page product image show
/**
 * CHECKOUT – Product Image + Name + Remove Icon (WORKING)
 */
add_filter('woocommerce_cart_item_name', 'thaaniyam_checkout_product_clean_layout', 10, 3);
function thaaniyam_checkout_product_clean_layout($product_name, $cart_item, $cart_item_key)
{

    if (!is_checkout()) {
        return $product_name;
    }

    $product = $cart_item['data'];
    if (!$product) {
        return $product_name;
    }

    $image = $product->get_image('thumbnail', [
        'class' => 'checkout-product-image'
    ]);

    $remove_url = wc_get_cart_remove_url($cart_item_key);
    $quantity = $cart_item['quantity'];

    // Quantity badge text pill
    $qty_badge = '<span class="checkout-qty-badge">Qty: ' . esc_html($quantity) . '</span>';

    // Inline store link
    $store_html = '';
    $vendor_id = get_post_field('post_author', $product->get_id());
    if ($vendor_id) {
        $vendor = get_user_by('id', $vendor_id);
        if ($vendor) {
            $store_name = function_exists('wcfm_get_vendor_store_name')
                ? wcfm_get_vendor_store_name($vendor_id)
                : $vendor->display_name;
            $store_url = function_exists('wcfmmp_get_store_url')
                ? wcfmmp_get_store_url($vendor_id)
                : get_author_posts_url($vendor_id);

            if ($store_name) {
                $store_html = '<div class="checkout-store-link">Store: <a href="' . esc_url($store_url) . '">' . esc_html($store_name) . '</a></div>';
            }
        }
    }

    return '
    <div class="checkout-product-wrap">
        <div class="checkout-product-img">
            <div class="checkout-product-img-wrap">
                ' . $image . '
            </div>
        </div>

        <div class="checkout-product-content">
            <div class="checkout-product-name-row">
                <span class="checkout-product-name">' . $product_name . '</span>
                <a href="' . esc_url($remove_url) . '" class="checkout-remove-item" title="Remove product" aria-label="Remove product">x</a>
            </div>
            <div class="checkout-product-meta-row">
                ' . $qty_badge . '
                ' . $store_html . '
            </div>
        </div>
    </div>';
}

add_filter('woocommerce_checkout_cart_item_quantity', 'thaaniyam_checkout_qty_badge', 10, 3);
function thaaniyam_checkout_qty_badge($quantity, $cart_item, $cart_item_key)
{

    if (!is_checkout()) {
        return $quantity;
    }

    return '';
}

// Disable default WCFM store link on checkout to prevent duplicate vendor listing
add_filter('wcfmmp_is_allow_cart_sold_by', 'disable_wcfm_default_store_on_checkout');
function disable_wcfm_default_store_on_checkout($allow)
{
    if (is_checkout() || (defined('WOOCOMMERCE_CHECKOUT') && WOOCOMMERCE_CHECKOUT)) {
        return false;
    }
    return $allow;
}

// Strip duplicate Store and Sold By metadata from checkout item data array
add_filter('woocommerce_get_item_data', 'remove_store_from_checkout_item_data', 99, 2);
function remove_store_from_checkout_item_data($item_data, $cart_item)
{
    if (is_checkout() || (defined('WOOCOMMERCE_CHECKOUT') && WOOCOMMERCE_CHECKOUT)) {
        if (is_array($item_data)) {
            foreach ($item_data as $key => $data) {
                if (
                    isset($data['name']) && (
                        $data['name'] === 'Store' ||
                        $data['name'] === 'Sold by' ||
                        strpos(strtolower($data['name']), 'store') !== false ||
                        strpos(strtolower($data['name']), 'sold') !== false
                    )
                ) {
                    unset($item_data[$key]);
                }
            }
        }
    }
    return $item_data;
}

add_filter('woocommerce_available_variation', function ($variation_data, $product, $variation) {

    // Get WooGallery images for this variation
    $gallery = get_post_meta($variation->get_id(), '_wpgallery_images', true);

    if (!empty($gallery)) {
        $variation_data['wpgallery_images'] = array_map('wp_get_attachment_url', $gallery);
    }

    return $variation_data;

}, 10, 3);
add_filter('woocommerce_email_styles', function ($css) {

    $css .= "
        #wrapper {
            width: 100% !important;
            background-color: #f7f7f7;
            padding: 20px 0;
        }

        #body_content {
            width: 100% !important;
        }

        #body_content_inner {
            max-width: 600px !important;
            margin: 0 auto !important;
            background: #ffffff;
            padding: 20px;
        }

        table {
            width: 100% !important;
            border-collapse: collapse;
            margin-bottom:20px;
        }

        th, td {
            padding: 8px;
            text-align: left;
        }

        th {
            background: #f2f2f2;
        }
    ";

    return $css;
});

// brand in shop Page
/* Filter products on SHOP page by Brand */
add_action('woocommerce_product_query', 'th_filter_shop_by_brand');
function th_filter_shop_by_brand($q)
{

    if (!is_shop() || empty($_GET['product_brand'])) {
        return;
    }

    $q->set('tax_query', [
        [
            'taxonomy' => 'product_brand',
            'field' => 'slug',
            'terms' => sanitize_text_field($_GET['product_brand']),
        ]
    ]);
}
/* Brand Header on Shop Page */
add_action('woocommerce_before_shop_loop', 'th_brand_header_on_shop', 1);
function th_brand_header_on_shop()
{

    if (!is_shop() || empty($_GET['product_brand'])) {
        return;
    }

    $term = get_term_by(
        'slug',
        sanitize_text_field($_GET['product_brand']),
        'product_brand'
    );

    if (!$term || is_wp_error($term))
        return;

    $thumbnail_id = get_term_meta($term->term_id, 'thumbnail_id', true);
    $brand_image = $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : '';

    echo '<div class="brand-archive-page"><div class="inner-container">';
    echo '<div class="brand-header">';

    if ($brand_image) {
        echo '<div class="brand-logo">
                <img src="' . esc_url($brand_image) . '" alt="' . esc_attr($term->name) . '">
              </div>';
    }

    echo '<div class="brand-info">';
    echo '<h1>' . esc_html($term->name) . '</h1>';
    echo wpautop($term->description);
    echo '</div>';

    echo '</div>';
}
/* Close wrapper */
add_action('woocommerce_after_shop_loop', 'th_close_brand_wrapper', 99);
function th_close_brand_wrapper()
{
    if (is_shop() && !empty($_GET['product_brand'])) {
        echo '</div></div>';
    }
}


// bundle products to add cart
add_filter('woocommerce_loop_add_to_cart_link', function ($html, $product) {
    // YITH Smart Bundle product
    if ($product->get_type() === 'yith_bundle') {

        return sprintf(
            '<a href="%s" class="button add_to_cart_button">%s</a>',
            esc_url(get_permalink($product->get_id())),
            esc_html__('Add to cart', 'woocommerce')
        );
    }
    return $html;
}, 99, 2);


// Combo Poducts in shop Page
add_action('pre_get_posts', function ($q) {
    if (is_admin() || !$q->is_main_query()) {
        return;
    }
    // Only on shop & archive pages
    if (is_shop() || is_product_category() || is_product_tag()) {

        if (isset($_GET['product_type']) && $_GET['product_type'] === 'woosb') {

            $tax_query = (array) $q->get('tax_query');

            $tax_query[] = [
                'taxonomy' => 'product_type',
                'field' => 'slug',
                'terms' => 'woosb',
            ];

            $q->set('tax_query', $tax_query);
        }
    }
});

add_action('wp_enqueue_scripts', function () {
    if (is_product()) {
        wp_enqueue_script('wc-add-to-cart');
        wp_enqueue_script('wc-cart-fragments');
        wp_enqueue_script('wc-add-to-cart-variation');
    }
});

// Add Clear Cart button near Update Cart
add_action('woocommerce_cart_actions', function () {
    echo '<a href="' . esc_url(add_query_arg('clear-cart', '1')) . '" 
            class="button clear-cart-btn"
            onclick="return confirm(\'Are you sure you want to clear the cart?\')">
            Clear Cart
          </a>';
});
// Clear cart logic
add_action('init', function () {
    if (isset($_GET['clear-cart']) && $_GET['clear-cart'] == '1') {
        WC()->cart->empty_cart();
        wp_safe_redirect(wc_get_cart_url());
        exit;
    }
});




// function tt5_get_wishlist_count() {

//     check_ajax_referer('wishlist_nonce', 'security');

//     if ( function_exists( 'YITH_WCWL' ) ) {
//         $count = YITH_WCWL()->count_products();
//     } else {
//         $count = 0;
//     }

//     wp_send_json_success( $count );
// }




add_filter('yith_wcwl_fragments', function ($fragments) {

    if (function_exists('YITH_WCWL')) {

        $count = YITH_WCWL()->count_products();

        ob_start();
        ?>
        <span class="custom-wishlist-count badge">
            <?php echo $count; ?>
        </span>
        <?php

        $fragments['.custom-wishlist-count'] = ob_get_clean();
    }

    return $fragments;
});


function tt5_get_wishlist_count()
{

    if (function_exists('YITH_WCWL')) {
        $count = YITH_WCWL()->count_products();
    } else {
        $count = 0;
    }

    wp_send_json_success($count);
}
add_action('wp_ajax_tt5_get_wishlist_count', 'tt5_get_wishlist_count');
add_action('wp_ajax_nopriv_tt5_get_wishlist_count', 'tt5_get_wishlist_count');


add_filter('rest_post_dispatch', function ($result, $server, $request) {
    if (strpos($request->get_route(), '/yith/wishlist/v1/items') !== false) {
        $data = $result->get_data();
        if (is_array($data)) {
            if (function_exists('YITH_WCWL')) {
                $data['total_wishlist_count'] = YITH_WCWL()->count_products();
            } else {
                $data['total_wishlist_count'] = 0;
            }
            $result->set_data($data);
        }
    }
    return $result;
}, 10, 3);


function tt5_yith_header_sync()
{

    wp_enqueue_script(
        'yith-header-sync',
        get_stylesheet_directory_uri() . '/assets/js/yith-refresh.js',
        array('jquery'),
        time(),
        true
    );

    wp_localize_script(
        'yith-header-sync',
        'thaaniyam_ajax',
        array(
            'ajax_url' => admin_url('admin-ajax.php')
        )
    );
}
add_action('wp_enqueue_scripts', 'tt5_yith_header_sync');

/**
 * Modern Split Layout for Shipping Options
 * Splits carrier name, delivery ETD, and price into structured HTML tags
 */
add_filter('woocommerce_cart_shipping_method_full_label', 'custom_checkout_shipping_method_full_label', 10, 2);
function custom_checkout_shipping_method_full_label($label, $method)
{
    $original_label = $method->get_label();

    // Calculate price HTML
    $price_html = '';
    if ($method->cost > 0) {
        $cost = $method->cost;
        if (!wc_prices_include_tax()) {
            $cost += $method->get_shipping_tax();
        }
        $price_html = wc_price($cost);
    } else {
        $price_html = '<span class="shipping-free">' . esc_html__('Free', 'woocommerce') . '</span>';
    }

    // Check if the label contains the delivery estimate (e.g. "(Delivery by May 31, 2026)")
    $name = $original_label;
    $etd = '';

    if (preg_match('/\((Delivery by [^\)]+)\)/i', $original_label, $matches)) {
        $etd = $matches[1]; // e.g. "Delivery by May 31, 2026"
        $name = trim(str_replace($matches[0], '', $original_label)); // Remove "(Delivery by ...)"
    }

    $html = '<span class="shipping-method-details">';
    $html .= '<span class="shipping-method-carrier">' . esc_html($name) . '</span>';
    if ($etd) {
        $html .= '<span class="shipping-method-etd">' . esc_html($etd) . '</span>';
    }
    $html .= '</span>';
    $html .= '<span class="shipping-method-price">' . $price_html . '</span>';

    return $html;
}
add_action('wp_footer', function () {
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('a').forEach(function (el) {
                if (el.textContent.trim() === 'Support' && !el.classList.contains('wcfm-support-action') && !el.closest('#wcfm_menu')) {
                    el.href = '<?php echo site_url('/contact-us/'); ?>';
                }
            });
        });
    </script>
    <?php
});
add_action(
    'woocommerce_order_refunded',
    'th_refund_handler',
    10,
    2
);

function th_refund_handler($order_id, $refund_id)
{
    $order = wc_get_order($order_id);
    $refund = wc_get_order($refund_id);

    if (!$order || !$refund) {
        return;
    }

    $refund_amount = abs($refund->get_amount());

    // Create refund record
    // Reverse vendor earnings
    // Create Shiprocket return
}

add_filter('wcfm_menus', function ($menus) {

    foreach ($menus as $key => $menu) {

        if (
            isset($menu['url']) &&
            strpos($menu['url'], 'add-to-my-store-catalog') !== false
        ) {
            unset($menus[$key]);
        }
    }

    return $menus;

}, 999);

add_action('template_redirect', function () {

    if (strpos($_SERVER['REQUEST_URI'], 'add-to-my-store-catalog') !== false) {
        wp_redirect(home_url('/dashboard'));
        exit;
    }

});


add_action('wp_ajax_live_product_search', 'live_product_search');
add_action('wp_ajax_nopriv_live_product_search', 'live_product_search');

function live_product_search()
{
    $keyword = sanitize_text_field($_POST['keyword']);

    $args = array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => 5,
        's' => $keyword
    );

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        while ($query->have_posts()) {

            $query->the_post();
            global $product;
            // Product category
            $terms = get_the_terms(get_the_ID(), 'product_cat');
            $category = '';

            if (!empty($terms) && !is_wp_error($terms)) {
                $category = $terms[0]->name;
            }
            ?>

            <a href="<?php the_permalink(); ?>" class="live-product">

                <div class="live-product-image">
                    <?php the_post_thumbnail('thumbnail'); ?>
                </div>

                <div class="live-product-content">
                    <h4><?php the_title(); ?></h4>
                    <span class="product-category"><?php echo esc_html($category); ?></span>
                </div>

                <div class="live-product-price">
                    <?php echo wp_kses_post($product->get_price_html()); ?>
                </div>

            </a>

            <?php

        }
        ?>
        <a class="view-all" href="<?php echo esc_url(home_url('/?s=' . urlencode($keyword) . '&post_type=product')); ?>">
            View all results for "<strong><?php echo esc_html($keyword); ?></strong>"
        </a>
        <?php
    } else {
        ?>
        <div class="no-result">
            <strong>No products found</strong>
            <p>Try searching with another keyword.</p>
        </div>
        <?php
    }

    wp_reset_postdata();
    wp_die();
}

// Remove Emoji Scripts
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('admin_print_scripts', 'print_emoji_detection_script');
remove_action('admin_print_styles', 'print_emoji_styles');

function remove_jquery_migrate($scripts)
{
    if (!is_admin() && isset($scripts->registered['jquery'])) {
        $script = $scripts->registered['jquery'];

        if ($script->deps) {
            $script->deps = array_diff($script->deps, array('jquery-migrate'));
        }
    }
}
add_action('wp_default_scripts', 'remove_jquery_migrate');

function theme_scripts()
{

    $js_path = get_stylesheet_directory() . '/assets/js/main.js';
    $js_uri = get_stylesheet_directory_uri() . '/assets/js/main.js';

    if (!file_exists($js_path)) {
        $js_path = get_template_directory() . '/assets/js/main.js';
        $js_uri = get_template_directory_uri() . '/assets/js/main.js';
    }

    if (file_exists($js_path)) {
        wp_enqueue_script(
            'main-js',
            $js_uri,
            array(),
            filemtime($js_path),
            true
        );
    }

}
add_action('wp_enqueue_scripts', 'theme_scripts');

function add_defer_attribute($tag, $handle)
{

    $defer_scripts = array(
        'main-js',
        'bootstrap',
        'slick',
        'aos'
    );

    if (in_array($handle, $defer_scripts)) {
        return str_replace(' src', ' defer src', $tag);
    }

    return $tag;
}
add_filter('script_loader_tag', 'add_defer_attribute', 10, 2);

function async_scripts($tag, $handle)
{

    if ($handle == 'google-analytics') {

        return str_replace(
            ' src',
            ' async src',
            $tag
        );

    }

    return $tag;
}
add_filter('script_loader_tag', 'async_scripts', 10, 2);

function remove_cssjs_ver($src)
{

    if (strpos($src, '?ver=')) {
        $src = remove_query_arg('ver', $src);
    }

    return $src;

}

add_filter('style_loader_src', 'remove_cssjs_ver', 9999);
add_filter('script_loader_src', 'remove_cssjs_ver', 9999);

function preconnect_fonts()
{
    ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <?php
}
add_action('wp_head', 'preconnect_fonts', 1);

function dns_prefetch()
{
    ?>

    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="dns-prefetch" href="//fonts.gstatic.com">

    <?php
}
add_action('wp_head', 'dns_prefetch');

function remove_dashicons()
{

    if (!is_user_logged_in()) {

        wp_deregister_style('dashicons');

    }

}

add_action('wp_enqueue_scripts', 'remove_dashicons');

add_filter('rest_authentication_errors', function ($result) {

    if (!empty($result)) {
        return $result;
    }

    // Allow public webhooks (e.g., Cashfree payout webhook)
    $rest_route = $GLOBALS['wp']->query_vars['rest_route'] ?? '';
    if (empty($rest_route) && isset($_SERVER['REQUEST_URI'])) {
        $rest_route = $_SERVER['REQUEST_URI'];
    }

    if (strpos($rest_route, 'thaaniyamhub/v1') !== false) {
        return $result;
    }

    if (!is_user_logged_in()) {

        return new WP_Error(
            'rest_disabled',
            'REST API disabled.',
            array('status' => 403)
        );

    }

    return $result;

});

/**
 * Product Validation (Weight and Dimensions mandatory)
 */
// Inject validation script directly in frontend and backend footer to bypass enqueuing/routing issues
add_action('wp_footer', 'thaaniyamhub_inject_product_validation_inline', 9999);
add_action('admin_footer', 'thaaniyamhub_inject_product_validation_inline', 9999);
function thaaniyamhub_inject_product_validation_inline()
{
    $js_path = get_stylesheet_directory() . '/assets/js/product-validation.js';
    if (file_exists($js_path)) {
        ?>
        <script type="text/javascript">
            <?php readfile($js_path); ?>
        </script>
        <?php
    }
}


/**
 * Restrict WooCommerce checkout countries and states
 * Country: India (IN) only
 * State: Tamil Nadu (TN) only
 */
if (!is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
    add_filter('woocommerce_countries', 'thaaniyamhub_restrict_checkout_countries', 9999);
    add_filter('woocommerce_allowed_countries', 'thaaniyamhub_restrict_checkout_countries', 9999);
    add_filter('woocommerce_shipping_countries', 'thaaniyamhub_restrict_checkout_countries', 9999);
    add_filter('woocommerce_states', 'thaaniyamhub_restrict_checkout_states', 9999);

    // Force country to render as select dropdown even if only one country is allowed
    add_filter('woocommerce_default_address_fields', 'thaaniyamhub_force_country_dropdown', 9999);

    // Set default checkout values
    add_filter('default_checkout_billing_country', 'thaaniyamhub_default_checkout_country', 9999);
    add_filter('default_checkout_shipping_country', 'thaaniyamhub_default_checkout_country', 9999);
    add_filter('default_checkout_billing_state', 'thaaniyamhub_default_checkout_state', 9999);
    add_filter('default_checkout_shipping_state', 'thaaniyamhub_default_checkout_state', 9999);
}

function thaaniyamhub_restrict_checkout_countries($countries)
{
    return array('IN' => 'India');
}

function thaaniyamhub_restrict_checkout_states($states)
{
    $states['IN'] = array(
        'TN' => 'Tamil Nadu',
    );
    return $states;
}

function thaaniyamhub_force_country_dropdown($fields)
{
    if (isset($fields['country'])) {
        $fields['country']['type'] = 'select';
        $fields['country']['options'] = array(
            'IN' => 'India'
        );
        $fields['country']['input_class'] = array('country_to_state', 'country_select');
    }
    return $fields;
}

function thaaniyamhub_default_checkout_country()
{
    return 'IN';
}

function thaaniyamhub_default_checkout_state()
{
    return 'TN';
}

// Enqueue native ACF assets on WCFM pages
add_action('wp_enqueue_scripts', function () {
    $is_wcfm = function_exists('is_wcfm_page') && is_wcfm_page();
    error_log("ACF WCFM Debug: wp_enqueue_scripts fired. is_wcfm_page: " . ($is_wcfm ? 'true' : 'false'));
    if ($is_wcfm) {
        if (function_exists('acf_enqueue_scripts')) {
            error_log("ACF WCFM Debug: Calling acf_enqueue_scripts() and wp_enqueue_media()");
            acf_enqueue_scripts();
            wp_enqueue_media();
        } else {
            error_log("ACF WCFM Debug: acf_enqueue_scripts function NOT found!");
        }
    }
}, 20);

// Remove default WCFM ACF manage views/controllers and replace with native ACF rendering/saving
add_action('wp_loaded', function () {
    global $WCFMu;
    if (isset($WCFMu) && isset($WCFMu->wcfmu_integrations)) {
        // Remove WCFM's default ACF product manage view hooks
        remove_action('after_wcfm_products_manage_tabs_content', array($WCFMu->wcfmu_integrations, 'wcfm_acf_product_manage_fields'), 60);
        remove_action('after_wcfm_products_manage_tabs_content', array($WCFMu->wcfmu_integrations, 'wcfm_acf_pro_product_manage_fields'), 60);

        // Remove WCFM's default ACF product manage controllers
        global $wp_filter;
        $hook_name = 'after_wcfm_products_manage_meta_save';
        $priority = 160;
        if (isset($wp_filter[$hook_name]) && isset($wp_filter[$hook_name]->callbacks[$priority])) {
            foreach ($wp_filter[$hook_name]->callbacks[$priority] as $unique_id => $callback) {
                if (is_array($callback['function']) && is_object($callback['function'][0])) {
                    $class_name = get_class($callback['function'][0]);
                    if ($class_name === 'WCFMu_ACF_Products_Manage_Controller' || $class_name === 'WCFMu_ACF_Pro_Products_Manage_Controller') {
                        unset($wp_filter[$hook_name]->callbacks[$priority][$unique_id]);
                    }
                }
            }
        }
    }
}, 999);

// Render ACF fields natively below Description field (inside main product content area)
add_action('wcfm_product_manager_left_panel_after', function ($product_id = 0) {
    error_log("ACF WCFM Debug: rendering callback fired for product_id: $product_id");

    // Output mock post_type for ACF JS location rule evaluation
    echo '<input type="hidden" id="post_type" value="product" />';

    if (!function_exists('acf_get_field_groups') || !function_exists('acf_render_fields')) {
        error_log("ACF WCFM Debug: acf functions not found in render!");
        return;
    }

    // Get field groups for this product
    $field_groups = acf_get_field_groups(array('post_id' => $product_id));
    if (empty($field_groups)) {
        // Fallback to post_type product (e.g. for new products)
        $field_groups = acf_get_field_groups(array('post_type' => 'product'));
    }

    if (empty($field_groups)) {
        return;
    }

    foreach ($field_groups as $field_group) {
        if (!$field_group['active']) {
            continue;
        }

        // Apply WCFM's capability / access filters
        $is_allowed = apply_filters('wcfm_is_allowed_acf_field_group', true, $field_group['ID']);
        if (!$is_allowed) {
            continue;
        }

        $fields = acf_get_fields($field_group['ID']);
        if (empty($fields)) {
            continue;
        }

        $title = $field_group['title'];
        ?>
        <div class="wcfm-acf-inline-section simple variable external grouped booking" style="margin-top:20px;">
            <p class="wcfm_title"><strong><?php echo esc_html($title); ?></strong></p>
            <div class="wcfm_clearfix"></div>
            <div class="acf-fields" data-post-id="<?php echo esc_attr($product_id); ?>">
                <?php acf_render_fields($fields, $product_id, 'div', 'label'); ?>
            </div>
        </div>
        <div class="wcfm_clearfix"></div>
        <?php
    }
}, 60);

// Save ACF fields natively on products manage page save
add_action('after_wcfm_products_manage_meta_save', function ($product_id, $wcfm_products_manage_form_data) {
    if (!function_exists('acf_save_post')) {
        return;
    }

    // Set $_POST['acf'] to match WCFM's submitted form data
    if (isset($wcfm_products_manage_form_data['acf']) && is_array($wcfm_products_manage_form_data['acf'])) {
        $_POST['acf'] = $wcfm_products_manage_form_data['acf'];
        acf_save_post($product_id);
    }
}, 160, 2);

// Add styling for ACF elements inside WCFM dashboard
add_action('wp_head', function () {
    if (function_exists('is_wcfm_page') && is_wcfm_page()) {
        ?>
        <script type="text/javascript">
            var wcfm_cat_based_acf_fields = wcfm_cat_based_acf_fields || {};
        </script>
        <?php
        // Only inject the beforeunload fix on WCFM products-manage pages
        if (strpos($_SERVER['REQUEST_URI'], 'products-manage') !== false) {
            ?>
            <script type="text/javascript">
                // Clear ACF's beforeunload warning ONLY on products-manage page, to prevent
                // the "Do you want to leave this page?" dialog after a successful WCFM save.
                // ACF uses jQuery $(window).on('beforeunload') so we must use $(window).off() to clear it.
                jQuery(document).ready(function ($) {

                    function clearAcfDirty() {
                        // Remove ALL beforeunload handlers (ACF binds via jQuery)
                        $(window).off('beforeunload');
                        window.onbeforeunload = null;
                        // Also reset ACF internal changed flag
                        if (typeof acf !== 'undefined') {
                            acf.set('changed', false);
                            // Override the unload method directly in case ACF re-registers
                            if (typeof acf.unload === 'function') {
                                acf.unload = function () { return undefined; };
                            }
                        }
                    }

                    // Clear on WCFM submit/draft button click (before AJAX fires)
                    $(document).on('click', '#wcfm_products_simple_submit, #wcfm_products_simple_draft_button', function () {
                        clearAcfDirty();
                    });

                    // Also clear on successful WCFM AJAX save response
                    $(document.body).on('wcfm_product_saved wcfm_product_created', function () {
                        clearAcfDirty();
                    });

                    // Trim whitespace from Submit for Review button value
                    var submitBtn = $('#wcfm_products_simple_submit_button');
                    if (submitBtn.length && submitBtn.val()) {
                        submitBtn.val($.trim(submitBtn.val()));
                    }
                });
            </script>
        <?php } ?>
        <style>
            /* ACF inline section styling below Description */
            .wcfm-acf-inline-section {
                width: 100% !important;
                box-sizing: border-box !important;
            }

            .wcfm-acf-inline-section .acf-fields {
                width: 100% !important;
                box-sizing: border-box !important;
            }

            /* Restore standard browser checkbox UI inside ACF fields (WCFM style hides checkmarks by default) */
            .acf-fields input[type="checkbox"] {
                -webkit-appearance: checkbox !important;
                -moz-appearance: checkbox !important;
                appearance: checkbox !important;
                width: 16px !important;
                height: 16px !important;
                min-width: 16px !important;
                box-shadow: none !important;
                margin-right: 5px !important;
                vertical-align: middle !important;
            }

            /* Style ACF Buttons to match WCFM style */
            .acf-fields .acf-button.button-primary,
            .acf-fields .acf-button.button {
                background: #1C2B36 !important;
                color: #fff !important;
                font-weight: 700 !important;
                padding: 8px 15px !important;
                text-transform: uppercase !important;
                border-radius: 4px !important;
                border: none !important;
                cursor: pointer !important;
                text-shadow: none !important;
                box-shadow: none !important;
                height: auto !important;
                line-height: 1.4 !important;
                display: inline-block !important;
            }

            .acf-fields .acf-button.button-primary:hover,
            .acf-fields .acf-button.button:hover {
                background: #17a2b8 !important;
                color: #fff !important;
            }

            /* Contain ACF fields width to prevent horizontal overflow */
            .acf-fields .acf-field {
                border-top: 1px solid #f0f0f0 !important;
                padding: 15px 0 !important;
                box-sizing: border-box !important;
                width: 100% !important;
                max-width: 100% !important;
            }

            .acf-fields .acf-field:first-child {
                border-top: none !important;
            }

            .acf-fields .acf-input input[type="text"],
            .acf-fields .acf-input textarea,
            .acf-fields .acf-input select {
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }

            .acf-fields .acf-repeater .acf-table {
                width: 100% !important;
                table-layout: auto !important;
                border: 1px solid #ccc !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }

            .acf-fields .acf-label label {
                font-weight: 600 !important;
                color: #555 !important;
            }

            .acf-fields .acf-repeater .acf-table {
                width: 100% !important;
                border: 1px solid #ccc !important;
            }

            .acf-fields .acf-repeater .acf-row-handle {
                background: #f9f9f9 !important;
                vertical-align: middle !important;
            }

            /* -------------------------------------------------------
             * WCFM Action-Icon Tooltips (CSS fallback + enhancement)
             * Shows data-tip content on hover via ::after pseudo-element.
             * NOTE: We intentionally avoid ::before on icon spans because
             * Font Awesome uses ::before to render the icon glyph — overriding
             * it would erase the icon. Only ::after is safe here.
             * ------------------------------------------------------- */
            span.text_tip[data-tip],
            a.text_tip[data-tip],
            i.text_tip[data-tip],
            .wcfm-action-icon {
                position: relative;
            }

            /* Tooltip bubble — uses ::after only (safe for Font Awesome icons) */
            span.text_tip[data-tip]::after,
            a.text_tip[data-tip]::after,
            i.text_tip[data-tip]::after,
            .wcfm-action-icon .text_tip[data-tip]::after {
                content: attr(data-tip);
                position: absolute;
                bottom: calc(100% + 8px);
                left: 50%;
                transform: translateX(-50%) translateY(4px);
                background: #1a1a2e;
                color: #fff;
                font-size: 11px;
                font-weight: 500;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                line-height: 1.4;
                white-space: nowrap;
                padding: 5px 10px;
                border-radius: 5px;
                pointer-events: none;
                opacity: 0;
                transition: opacity 0.18s ease, transform 0.18s ease;
                z-index: 99999;
                box-shadow: 0 2px 8px rgba(0,0,0,0.25);
                letter-spacing: 0.01em;
            }

            /* Show tooltip bubble on hover */
            span.text_tip[data-tip]:hover::after,
            a.text_tip[data-tip]:hover::after,
            i.text_tip[data-tip]:hover::after,
            .wcfm-action-icon:hover .text_tip[data-tip]::after,
            .wcfm-action-icon .text_tip[data-tip]:hover::after {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }

            /* Reduce button width and height for WCFM simple submit buttons */
            #wcfm_products_simple_submit input.wcfm_submit_button,
            #wcfm_products_simple_submit a input.wcfm_submit_button,
            #wcfm_products_simple_submit_button,
            #wcfm_products_simple_draft_button {
                width: auto !important;
                height: 38px !important;
                min-width: 90px !important;
                padding: 0 20px !important;
                font-size: 13px !important;
                font-weight: 700 !important;
                line-height: 38px !important;
                text-transform: uppercase !important;
                border-radius: 4px !important;
                margin: 11px 5px !important;
                box-sizing: border-box !important;
                display: inline-block !important;
                float: right !important;
            }
        </style>
        <?php
    }
});

// Request shutdown log
add_action('shutdown', function () {
    if (isset($_SERVER['REQUEST_URI'])) {
        $is_wcfm = function_exists('is_wcfm_page') && is_wcfm_page();
        error_log("ACF WCFM Debug: Request shutdown. URI: " . $_SERVER['REQUEST_URI'] . ", is_wcfm_page: " . ($is_wcfm ? 'true' : 'false'));
    }
});

/**
 * Prevent Buy Now (add-to-cart POST/GET to Checkout page) from adding products
 * repeatedly on page refresh by redirecting to a clean Checkout page URL.
 */
add_action('template_redirect', 'thaaniyamhub_redirect_post_add_to_cart_checkout');
function thaaniyamhub_redirect_post_add_to_cart_checkout()
{
    if (is_checkout() && !is_wc_endpoint_url('order-pay') && !is_wc_endpoint_url('order-received') && isset($_REQUEST['add-to-cart'])) {
        wp_safe_redirect(wc_get_checkout_url());
        exit;
    }
}

/**
 * Add a filter dropdown for WCFM Store/Vendor on the product list page in WP Admin.
 */
add_action('restrict_manage_posts', 'thaaniyamhub_add_product_store_filter');
function thaaniyamhub_add_product_store_filter()
{
    global $typenow;
    if ('product' !== $typenow) {
        return;
    }

    global $WCFM;
    if (!$WCFM || !isset($WCFM->wcfm_vendor_support)) {
        return;
    }

    // Retrieve WCFM vendors list (array of vendor_id => vendor_name/details)
    $vendors = $WCFM->wcfm_vendor_support->wcfm_get_vendor_list(true);
    if (empty($vendors)) {
        return;
    }

    $selected = isset($_GET['wcfm_store_filter']) ? $_GET['wcfm_store_filter'] : '';

    echo '<select name="wcfm_store_filter" id="wcfm-store-filter">';
    echo '<option value="">' . esc_html__('Filter by Store', 'twentytwentyone-child') . '</option>';

    foreach ($vendors as $vendor_id => $vendor_name) {
        if (!$vendor_id) {
            continue; // Skip the "All" placeholder option from the WCFM array
        }

        // Retrieve the clean store name
        $store_name = function_exists('wcfm_get_vendor_store_name') ? wcfm_get_vendor_store_name($vendor_id) : '';
        if (!$store_name) {
            $user = get_userdata($vendor_id);
            $store_name = $user ? $user->display_name : $vendor_name;
        }

        printf(
            '<option value="%s" %s>%s</option>',
            esc_attr($vendor_id),
            selected($selected, $vendor_id, false),
            esc_html($store_name)
        );
    }
    echo '</select>';
}

/**
 * Handle filtering the WP Admin product list by the selected store/vendor.
 */
add_filter('request', 'thaaniyamhub_filter_products_by_store_in_admin');
function thaaniyamhub_filter_products_by_store_in_admin($vars)
{
    global $pagenow;
    if (is_admin() && 'edit.php' === $pagenow && isset($vars['post_type']) && 'product' === $vars['post_type']) {
        if (!empty($_GET['wcfm_store_filter'])) {
            $vars['author'] = intval($_GET['wcfm_store_filter']);
        }
    }
    return $vars;
}

/**
 * 1. Add WhatsApp Number Field to the EXISTING 'Footer Settings' Section
 */
function mytheme_whatsapp_customizer_settings($wp_customize)
{

    // Register the WhatsApp number setting
    $wp_customize->add_setting('whatsapp_number', array(
        'default' => '',
        'sanitize_callback' => 'sanitize_text_field',
    ));

    // Add the input control and attach it to the existing section
    $wp_customize->add_control('whatsapp_number', array(
        'label' => __('WhatsApp Number', 'mytheme'),
        'description' => __('Enter your number with country code (e.g., 91155552671). Leave blank to hide the icon.', 'mytheme'),
        'section' => 'thaaniyamhub_footer_settings',
        'type' => 'text',
        'priority' => 999, // High priority puts it at the bottom of the existing settings
    ));
}
add_action('customize_register', 'mytheme_whatsapp_customizer_settings', 20);

/**
 * 2. Output the WhatsApp Floating Icon in the Footer
 */
function mytheme_display_whatsapp_icon()
{
    $whatsapp_number = get_theme_mod('whatsapp_number');

    if (!empty($whatsapp_number)) {
        $clean_number = preg_replace('/[^0-9]/', '', $whatsapp_number);

        if (!empty($clean_number)) {
            ?>
            <!-- WhatsApp Floating Button -->
            <a href="https://wa.me/<?php echo esc_attr($clean_number); ?>" class="whatsapp-float" target="_blank"
                rel="noopener noreferrer" aria-label="Chat on WhatsApp">
                <img src="https://upload.wikimedia.org/wikipedia/commons/6/6b/WhatsApp.svg" alt="WhatsApp Icon" />
            </a>

            <!-- WhatsApp Floating Button Styles -->
            <style>
                .whatsapp-float {
                    position: fixed;
                    bottom: 130px;
                    right: 50px;
                    width: 60px;
                    height: 60px;
                    background-color: #25D366;
                    border-radius: 50%;
                    box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.2);
                    z-index: 9999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: transform 0.3s ease;
                }

                .whatsapp-float:hover {
                    transform: scale(1.1);
                }

                .whatsapp-float img {
                    width: 35px;
                    height: 35px;
                }

                @media screen and (max-width: 767px) {
                    .whatsapp-float {
                        width: 50px;
                        height: 50px;
                        bottom: 100px;
                        right: 40px;
                    }

                    .whatsapp-float img {
                        width: 30px;
                        height: 30px;
                    }
                }
            </style>
            <?php
        }
    }
}
add_action('wp_footer', 'mytheme_display_whatsapp_icon');

/**
 * Force Phone fields (Billing and Shipping) to be required on checkout and remove optional text.
 */
add_filter('woocommerce_billing_fields', 'thaaniyamhub_make_phone_required_billing', 9999);
function thaaniyamhub_make_phone_required_billing($fields) {
    if (isset($fields['billing_phone'])) {
        $fields['billing_phone']['required'] = true;
        $fields['billing_phone']['label']    = __('Phone', 'woocommerce');
    }
    return $fields;
}

add_filter('woocommerce_shipping_fields', 'thaaniyamhub_make_phone_required_shipping', 9999);
function thaaniyamhub_make_phone_required_shipping($fields) {
    if (isset($fields['shipping_phone'])) {
        $fields['shipping_phone']['required'] = true;
        $fields['shipping_phone']['label']    = __('Phone', 'woocommerce');
    }
    return $fields;
}

add_filter('woocommerce_default_address_fields', 'thaaniyamhub_make_phone_required_default', 9999);
function thaaniyamhub_make_phone_required_default($fields) {
    if (isset($fields['phone'])) {
        $fields['phone']['required'] = true;
    }
    return $fields;
}

add_filter('woocommerce_get_country_locale_default', 'thaaniyamhub_make_phone_required_locale', 9999);
add_filter('woocommerce_get_country_locale', 'thaaniyamhub_make_phone_required_locale', 9999);
function thaaniyamhub_make_phone_required_locale($locale) {
    foreach ($locale as $country => $fields) {
        if (isset($locale[$country]['phone'])) {
            $locale[$country]['phone']['required'] = true;
        }
    }
    return $locale;
}

add_filter('woocommerce_checkout_fields', 'thaaniyamhub_make_phone_required_checkout', 9999);
function thaaniyamhub_make_phone_required_checkout($fields) {
    if (isset($fields['billing']['billing_phone'])) {
        $fields['billing']['billing_phone']['required'] = true;
    }
    if (isset($fields['shipping']['shipping_phone'])) {
        $fields['shipping']['shipping_phone']['required'] = true;
    }
    return $fields;
}

add_filter('woocommerce_form_field_args', 'thaaniyamhub_make_phone_required_field_args', 9999, 3);
function thaaniyamhub_make_phone_required_field_args($args, $key, $value) {
    if ($key === 'billing_phone' || $key === 'shipping_phone') {
        $args['required'] = true;
    }
    return $args;
}

/**
 * Clear WooCommerce cart, session, and FunnelKit cart cookies when a user logs out.
 * Prevents cart count and cart contents mismatch when visiting as guest after logout.
 */
add_action('wp_logout', 'thaaniyamhub_clear_cart_on_logout', 1);
function thaaniyamhub_clear_cart_on_logout() {
    if (function_exists('WC')) {
        if (WC()->cart) {
            WC()->cart->empty_cart(true);
        }
        if (WC()->session) {
            WC()->session->destroy_session();
        }
    }

    $cookies_to_clear = array(
        'fkcart_cart_qty',
        'fkcart_cart_total',
        'fkcart_qty',
        'fkcart_total',
        'woocommerce_items_in_cart',
        'woocommerce_cart_hash',
        'fkcart_cart_hash',
    );

    foreach ($_COOKIE as $key => $val) {
        if (strpos($key, 'wp_woocommerce_session_') === 0 || strpos($key, 'fkcart_') === 0) {
            $cookies_to_clear[] = $key;
        }
    }

    $cookies_to_clear = array_unique($cookies_to_clear);
    $domain = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    $path_uri = !empty($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';

    foreach ($cookies_to_clear as $cookie_name) {
        if (isset($_COOKIE[$cookie_name])) {
            unset($_COOKIE[$cookie_name]);
        }
        setcookie($cookie_name, '', time() - 3600, '/');
        setcookie($cookie_name, '', time() - 3600, '/', $domain);
        if ($path_uri !== '/') {
            setcookie($cookie_name, '', time() - 3600, $path_uri);
        }
    }

    setcookie('thaaniyam_just_logged_out', '1', time() + 300, '/');
}

/**
 * Ensure empty cart fragments for guests when cart is empty.
 * Prevents stale cart item markup from popping up in FunnelKit side cart drawer.
 */
add_filter('fkcart_fragments', 'thaaniyamhub_sanitize_fkcart_fragments', 999);
function thaaniyamhub_sanitize_fkcart_fragments($fragments) {
    if (!is_user_logged_in() && function_exists('WC') && (is_null(WC()->cart) || WC()->cart->is_empty())) {
        $fragments['fkcart_qty'] = 0;
        $fragments['fkcart_total'] = 0;

        if (isset($fragments['.fkcart-modal-container'])) {
            $fragments['.fkcart-modal-container'] = str_replace('fkcart-has-items', '', $fragments['.fkcart-modal-container']);
        }
    }
    return $fragments;
}

/**
 * Optimize WCFM Support Action button responsiveness and speed up external resource fetching
 * without modifying core plugin files or popup UI.
 */
add_action('wp_head', function() {
    if (function_exists('is_account_page') && is_account_page()) {
        echo '<link rel="dns-prefetch" href="https://www.google.com" />' . "\n";
        echo '<link rel="preconnect" href="https://www.google.com" crossorigin />' . "\n";
        echo '<link rel="dns-prefetch" href="https://www.gstatic.com" />' . "\n";
        echo '<link rel="preconnect" href="https://www.gstatic.com" crossorigin />' . "\n";
    }
});

add_action('wp_footer', function() {
    if (function_exists('is_account_page') && is_account_page() && is_user_logged_in()) {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $(document).on('click', '.wcfm-support-action', function() {
                var $btn = $(this);
                if ($btn.data('wcfm-loading')) {
                    return false;
                }
                $btn.data('wcfm-loading', true);
                var origText = $btn.text();
                $btn.css({'opacity': '0.6', 'pointer-events': 'none'}).text('Loading...');

                $(document).one('cbox_complete cbox_closed', function() {
                    $btn.data('wcfm-loading', false);
                    $btn.css({'opacity': '1', 'pointer-events': 'auto'}).text(origText);
                });

                // Fallback reset if popup doesn't open within 8 seconds
                setTimeout(function() {
                    if ($btn.data('wcfm-loading')) {
                        $btn.data('wcfm-loading', false);
                        $btn.css({'opacity': '1', 'pointer-events': 'auto'}).text(origText);
                    }
                }, 8000);
            });
        });
        </script>
        <?php
    }
});

/**
 * Include processing, completed, and on-hold orders in WCFM Sales Reports & Dashboard stats.
 */
add_filter( 'wcfm_marketplace_active_withdrawal_order_status', function( $statuses ) {
    if ( ! is_array( $statuses ) ) {
        $statuses = array();
    }
    $statuses['wc-processing'] = __( 'Processing', 'woocommerce' );
    $statuses['wc-completed']  = __( 'Completed', 'woocommerce' );
    $statuses['wc-on-hold']    = __( 'On hold', 'woocommerce' );
    return $statuses;
} );

/**
 * Fix WCFM Sales by Date report & Dashboard JS errors globally without modifying plugin core files.
 * Also logs diagnostic info to wp-content/uploads/wcfm_report_debug.log
 */
add_action( 'init', function() {
    $uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
    if ( strpos( $uri, 'dashboard' ) !== false || strpos( $uri, 'reports' ) !== false || strpos( $uri, 'wcfm' ) !== false ) {
        $log_file = WP_CONTENT_DIR . '/uploads/wcfm_report_debug.log';
        $timestamp = date( 'Y-m-d H:i:s' );
        $user_id = get_current_user_id();
        global $WCFMmp, $wpdb;
        $vendor_id = isset( $WCFMmp->vendor_id ) ? $WCFMmp->vendor_id : $user_id;
        $table_name = $wpdb->prefix . 'wcfm_marketplace_orders';
        $order_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE vendor_id = %d", $vendor_id ) );

        @file_put_contents( $log_file, "[{$timestamp}] REQUEST: {$uri} | User: {$user_id} | Vendor: {$vendor_id} | Orders in DB: {$order_count}\n", FILE_APPEND );
    }

    ob_start( function( $buffer ) {
        if ( empty( $buffer ) ) {
            return $buffer;
        }

        // Fix 1: Clean up multiline JS label strings outputted by WCFM reports & dashboard inline JS
        $buffer = preg_replace_callback( '/label:\s*"([^"]+)"/s', function( $matches ) {
            $clean_str = trim( preg_replace( '/\s+/', ' ', $matches[1] ) );
            return 'label: "' . $clean_str . '"';
        }, $buffer );

        $buffer = preg_replace_callback( '/label:\s*\'([^\']+)\'/s', function( $matches ) {
            $clean_str = trim( preg_replace( '/\s+/', ' ', $matches[1] ) );
            return "label: '" . $clean_str . "'";
        }, $buffer );

        // Fix 2: Ensure window.chartColors and Chart.helpers.color don't throw JavaScript errors
        $chart_helpers_fix = 'window.chartColors = window.chartColors || { red: "rgb(255, 99, 132)", orange: "rgb(255, 159, 64)", yellow: "rgb(255, 205, 86)", green: "rgb(75, 192, 192)", blue: "rgb(54, 162, 235)", purple: "rgb(153, 102, 255)", grey: "rgb(201, 203, 207)", withdrawal: "rgb(32, 201, 151)", refund: "rgb(232, 62, 140)", tax: "rgb(115, 129, 143)", shipping: "rgb(111, 66, 193)" }; var color = (typeof Chart !== "undefined" && Chart.helpers && typeof Chart.helpers.color === "function") ? Chart.helpers.color : function(c){ return { alpha: function(){ return { rgbString: function(){ return c || "rgba(0,0,0,0.1)"; } }; } }; };';

        $buffer = str_replace(
            'var color = Chart.helpers.color;',
            $chart_helpers_fix,
            $buffer
        );

        // Fix 3: Fix `var show_legend = ;` syntax error if $show_legend was undefined in PHP
        $buffer = preg_replace( '/var\s+show_legend\s*=\s*;/i', 'var show_legend = false;', $buffer );

        return $buffer;
    } );
} );

/**
 * Fix WCFM Store Page & Product Page Inquiry and Follow options.
 * Enqueues Colorbox, BlockUI, and Login popup libraries which are required for opening modal popups & block actions.
 */
add_action( 'wp_enqueue_scripts', function() {
    global $WCFM;

    if ( isset( $WCFM ) && isset( $WCFM->library ) ) {
        $is_store_page   = function_exists( 'wcfmmp_is_store_page' ) && wcfmmp_is_store_page();
        $is_product_page = is_product();

        if ( $is_store_page || $is_product_page ) {
            $WCFM->library->load_colorbox_lib();
            $WCFM->library->load_blockui_lib();

            if ( ! is_user_logged_in() ) {
                $WCFM->library->load_wcfm_login_popup_lib();
            }

            wp_enqueue_style( 'wcfm_enquiry_button_css', $WCFM->library->css_lib_url_min . 'enquiry/wcfm-style-enquiry-button.css', array(), $WCFM->version );
        }
    }
}, 25 );

/**
 * Format WCFM Direct Notification emails (e.g. New Follower) with the signature Thaaniyam Hub Email Design System.
 */
function thaaniyamhub_email_content_wrapper( $content_body, $email_heading = '' ) {
    // If already formatted using the Thaaniyam Hub template system, do not re-wrap
    if ( strpos( $content_body, 'background:#edf5ee' ) !== false || strpos( $content_body, 'border:1px solid #E0EBD8' ) !== false || strpos( $content_body, 'max-width:650px' ) !== false ) {
        return $content_body;
    }

    // Default heading fallback if empty
    if ( empty( $email_heading ) ) {
        $email_heading = __( 'Notification', 'wc-frontend-manager' );
    }

    $display_heading = preg_replace( '/^Notification\s*[\-\:\—]\s*/i', '', $email_heading );
    $display_heading = str_replace( array( '[Thaaniyam Hub] - ', 'Thaaniyam Hub - ', '[Thaaniyam Hub] ', 'Thaaniyam Hub: ' ), '', $display_heading );
    if ( strtolower( trim( $display_heading ) ) === 'review' ) {
        $display_heading = 'Store Review';
    }
    if ( empty( trim( $display_heading ) ) ) {
        $display_heading = 'Notification';
    }

    // Determine a short, clean badge text based on the heading
    $badge_text = 'Notification';
    $lower_heading = strtolower( $display_heading );
    if ( strpos( $lower_heading, 'order' ) !== false ) {
        $badge_text = 'Order';
    } elseif ( strpos( $lower_heading, 'contact' ) !== false || strpos( $lower_heading, 'enquiry' ) !== false || strpos( $lower_heading, 'inquiry' ) !== false ) {
        $badge_text = 'Enquiry';
    } elseif ( strpos( $lower_heading, 'review' ) !== false ) {
        $badge_text = 'Review';
    } elseif ( strpos( $lower_heading, 'account' ) !== false || strpos( $lower_heading, 'password' ) !== false || strpos( $lower_heading, 'login' ) !== false ) {
        $badge_text = 'Account';
    } elseif ( strpos( $lower_heading, 'ship' ) !== false || strpos( $lower_heading, 'deliver' ) !== false || strpos( $lower_heading, 'track' ) !== false ) {
        $badge_text = 'Shipping';
    }

    // Extract inner content from default header/footer wrapper if present
    $inner_body = $content_body;
    if ( preg_match( '/<td style="text-align:center; padding:15px 20px;">.*?<\/td>\s*<\/tr>\s*<\/table>\s*<\/td>\s*<\/tr>\s*<\/table>(.*?)<\/p>\s*<p style="text-align:center;/s', $content_body, $matches ) ) {
        $inner_body = trim( $matches[1] );
    } elseif ( preg_match( '/<\/h2>\s*<\/td>\s*<\/tr>\s*<\/table>\s*<\/td>\s*<\/tr>\s*<\/table>(.*)/s', $content_body, $matches ) ) {
        $inner_body = trim( $matches[1] );
        $inner_body = preg_replace( '/<p style="text-align:center; font-size:13px; color:#777;.*$/s', '', $inner_body );
    }

    // Add inline styling to links within the notification body
    $styled_inner_body = preg_replace(
        '/<a /i',
        '<a style="color:#2D6E3E; font-weight:bold; text-decoration:underline;" ',
        $inner_body
    );

    $logo_url = 'https://yme.384.myftpupload.com/wp-content/uploads/2026/01/thaaniyam-logo.png';
    $year     = date( 'Y' );

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Thaaniyam Hub</title>
<style type="text/css">
/* ── Reset ── */
body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
img { -ms-interpolation-mode: bicubic; }
/* ── Base ── */
body { margin: 0 !important; padding: 0 !important; background-color: #f4f6f8 !important; }
.email-outer { width: 100% !important; background-color: #f4f6f8 !important; }
.email-card { width: 100% !important; max-width: 640px !important; margin: 0 auto !important; }
/* ── 2-col info blocks ── */
.two-col-row td { vertical-align: top !important; }
.two-col-cell { display: table-cell !important; }
.two-col-divider { display: table-cell !important; }
/* ── Tables inside email body ── */
.order-items-table { border-collapse: collapse !important; }
.order-items-table td, .order-items-table th { word-break: break-word !important; }
/* ── Mobile ── */
@media only screen and (max-width: 640px) {
  .email-outer-pad { padding: 10px 0 !important; }
  .email-card { border-radius: 0 !important; border-left: none !important; border-right: none !important; }
  /* Stack 2-col rows */
  .two-col-row, .two-col-row tr { display: block !important; width: 100% !important; }
  .two-col-cell {
    display: block !important;
    width: 100% !important;
    padding: 16px 20px !important;
    box-sizing: border-box !important;
  }
  .two-col-divider { display: none !important; }
  /* Header & body padding */
  .email-header-td { padding: 24px 20px !important; }
  .email-body-td { padding: 20px 16px !important; }
  .email-info-td { padding: 16px 20px !important; }
  .email-footer-td { padding: 20px !important; }
  /* Order items table */
  .order-items-table { font-size: 13px !important; }
  .order-items-table td, .order-items-table th { padding: 8px 6px !important; }
  /* WooCommerce default email containers */
  table[width="600"], #outer_wrapper table, #inner_wrapper, #template_container,
  #template_body, #body_content table, #body_content_inner_cell {
    width: 100% !important;
    max-width: 100% !important;
  }
  #body_content_inner_cell { padding: 20px 15px !important; }
  /* Generic responsive rules for inline-padded tables/tds */
  td[style*="padding:30px 36px"], td[style*="padding: 30px 36px"],
  td[style*="padding:24px 36px"], td[style*="padding: 24px 36px"],
  div[style*="padding:30px 36px"], div[style*="padding: 30px 36px"],
  div[style*="padding:24px 36px"], div[style*="padding: 24px 36px"] {
    padding: 20px 15px !important;
  }
  td[style*="padding:40px 44px"], td[style*="padding: 40px 44px"] {
    padding: 24px 20px !important;
  }
  td[width="50%"], td[style*="width:50%"], td[style*="width: 50%"] {
    display: block !important;
    width: 100% !important;
    padding-left: 16px !important;
    padding-right: 16px !important;
    box-sizing: border-box !important;
  }
  td[style*="min-width:1px"], td[style*="width:1px"], td[style*="width: 1px"] {
    display: none !important;
  }
  img { max-width: 100% !important; height: auto !important; }
  h1 { font-size: 20px !important; }
  h2 { font-size: 17px !important; }
}
</style>
</head>
<body class="email-outer">
<table class="email-outer" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f6f8; margin:0; padding:0; border-collapse:collapse;">
    <tr>
        <td align="center" class="email-outer-pad" style="padding:32px 16px;">
            <table class="email-card" width="640" cellpadding="0" cellspacing="0" border="0" style="max-width:640px; width:100%; background:#ffffff; border-collapse:collapse; margin:0 auto; border-radius:12px; border:1px solid #E0EBD8;">

                <!-- ═══ HEADER ═══ -->
                <tr>
                    <td class="email-header-td" align="center" style="background:#edf5ee; border-radius:12px 12px 0 0; padding:24px 30px; text-align:center;">
                        <div style="margin-bottom:12px; text-align:center;">
                            <img class="email-logo" src="<?php echo esc_url( $logo_url ); ?>" alt="Thaaniyam Hub" style="display:inline-block; margin:0 auto; max-width:140px; width:140px; height:auto; border:0;" />
                        </div>
                        <!-- BADGE -->
                        <?php if ( ! empty( $badge_text ) ) : ?>
                        <table align="center" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse; margin:0 auto 12px; width:auto !important;">
                            <tr>
                                <td style="background-color:#2D6E3E; border:none; border-radius:20px; padding:6px 16px; text-align:center;" bgcolor="#2D6E3E">
                                    <span style="display:inline-block; width:6px; height:6px; background:#6FCF97; border-radius:50%; vertical-align:middle; margin-right:6px;"></span>
                                    <span style="color:#A8D4A8; font-size:10px; letter-spacing:2px; text-transform:uppercase; font-family:Arial,Helvetica,sans-serif; vertical-align:middle; font-weight:700;"><?php echo esc_html( $badge_text ); ?></span>
                                </td>
                            </tr>
                        </table>
                        <?php endif; ?>
                        <h1 class="email-h1" style="color:#1E3A24; font-family:Arial,Helvetica,sans-serif; font-size:22px; font-weight:700; line-height:1.35; margin:0; text-align:center;">
                            <?php echo esc_html( $display_heading ); ?>
                        </h1>
                    </td>
                </tr>

                <!-- ═══ BODY CONTENT ═══ -->
                <tr>
                    <td class="email-body-td" style="background:#ffffff; border-left:1px solid #E0EBD8; border-right:1px solid #E0EBD8; padding:28px 32px;">
                        <div style="background:#F4FAF5; border-left:4px solid #2D6E3E; border-radius:8px; padding:20px 24px; font-size:14px; color:#1E3A24; font-family:Arial,Helvetica,sans-serif; line-height:1.7; margin-bottom:10px;">
                            <?php echo wp_kses_post( $styled_inner_body ); ?>
                        </div>
                    </td>
                </tr>

                <!-- ═══ FOOTER ═══ -->
                <tr>
                    <td class="email-footer-td" style="background:#1b4332; border-radius:0 0 12px 12px; padding:24px 20px; text-align:center;" bgcolor="#1b4332">
                        <p style="font-size:12px; color:#ffffff; font-family:Arial,Helvetica,sans-serif; line-height:1.8; margin:0 0 6px 0; text-align:center;">
                            Need help? <a href="mailto:<?php echo esc_attr( get_theme_mod( 'support_email', 'support@thaaniyamhub.com' ) ); ?>" style="color:#6FCF97; text-decoration:underline;"><?php echo esc_html( get_theme_mod( 'support_email', 'support@thaaniyamhub.com' ) ); ?></a>
                        </p>
                        <p style="font-size:11px; color:#9dc8a8; font-family:Arial,Helvetica,sans-serif; line-height:1.6; margin:0; text-align:center;">
                            <?php echo wp_kses_post( get_theme_mod( 'copyright_text', '&copy; ' . $year . ' Thaaniyam Hub &middot; All rights reserved.' ) ); ?>
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
    <?php
    return ob_get_clean();
}
add_filter( 'wcfm_email_content_wrapper', 'thaaniyamhub_email_content_wrapper', 20, 2 );

/**
 * Automatically wrap general HTML emails in the Thaaniyam Hub brand UI container.
 */
add_filter( 'wp_mail', function( $args ) {
    $is_html = false;
    if ( isset( $args['headers'] ) ) {
        if ( is_array( $args['headers'] ) ) {
            foreach ( $args['headers'] as $header ) {
                if ( stripos( $header, 'Content-Type: text/html' ) !== false ) {
                    $is_html = true;
                    break;
                }
            }
        } elseif ( is_string( $args['headers'] ) ) {
            if ( stripos( $args['headers'], 'Content-Type: text/html' ) !== false ) {
                $is_html = true;
            }
        }
    }

    if ( $is_html && is_string( $args['message'] ) ) {

        // ── STEP 1: Check if already wrapped in our brand template ──────────
        $normalized = str_replace( array( ' ', "\t", "\r", "\n" ), '', strtolower( $args['message'] ) );
        $already_wrapped = (
            strpos( $normalized, 'background:#edf5ee' ) !== false ||
            strpos( $normalized, 'thaaniyam-logo.png' ) !== false ||
            strpos( $normalized, 'email-head.php' ) !== false
        );

        // ── STEP 2: Wrap plain / WCFM / third-party emails ──────────────────
        if ( ! $already_wrapped ) {
            $heading = 'Notification';
            if ( ! empty( $args['subject'] ) ) {
                if ( preg_match( '/\]\s*(.*)$/', $args['subject'], $matches ) ) {
                    $heading = trim( $matches[1] );
                } else {
                    $heading = $args['subject'];
                }
            }
            $heading = str_replace( array( '[Thaaniyam Hub] - ', 'Thaaniyam Hub - ', '[Thaaniyam Hub] ', 'Thaaniyam Hub: ' ), '', $heading );

            $body = $args['message'];
            // Strip any leftover raw <style> that was accidentally in the body
            $body = preg_replace( '/<style[^>]*>.*?<\/style>/is', '', $body );
            if ( strpos( $body, '<p>' ) === false ) {
                $body = wpautop( $body );
            }
            $args['message'] = thaaniyamhub_email_content_wrapper( $body, $heading );
        }

        // ── STEP 3: Inject responsive CSS into <head> of the final document ─
        // The email is now guaranteed to have a proper <head> (either from our
        // email-head.php partial or from thaaniyamhub_email_content_wrapper).
        // Never prepend CSS as raw text before <html>.
        if ( stripos( $args['message'], '</head>' ) !== false ) {
            // Only inject viewport meta if missing
            if ( stripos( $args['message'], 'name="viewport"' ) === false ) {
                $args['message'] = str_ireplace(
                    '</head>',
                    '<meta name="viewport" content="width=device-width, initial-scale=1.0" /></head>',
                    $args['message']
                );
            }
            // Only inject responsive style if our class-based CSS isn't already there
            if ( stripos( $args['message'], '.email-card' ) === false ) {
                $responsive_style = '<style type="text/css">
@media screen and (max-width:600px){
  .email-card{width:100%!important;}
  .email-header-td{padding:28px 20px!important;}
  .email-body-td{padding:20px 16px!important;}
  .email-section-td{padding:18px 16px!important;}
  .email-footer-td{padding:20px 16px!important;}
  .email-logo{max-width:140px!important;}
  h1.email-h1{font-size:20px!important;}
  .order-items-table td,.order-items-table th{padding:10px 6px!important;font-size:12px!important;}
  table[width="600"],#outer_wrapper>table,#template_container,#template_body,#body_content table{width:100%!important;max-width:100%!important;}
  #body_content_inner_cell,#body_content td{padding:20px 15px!important;}
}
</style>';
                $args['message'] = str_ireplace( '</head>', $responsive_style . '</head>', $args['message'] );
            }
        }
    }
    return $args;
}, 99 );

/**
 * Route low stock/no stock notifications to the product's vendor.
 * Runs at priority 99 to execute AFTER WCFM's filter (priority 50), merging both admin and vendor.
 */
function thaaniyamhub_route_stock_email_to_vendor( $recipient, $product, $email_obj = null ) {
    if ( ! $product ) {
        return $recipient;
    }

    $product_id = method_exists( $product, 'get_id' ) ? $product->get_id() : $product;
    if ( ! $product_id ) {
        return $recipient;
    }

    // Get Admin Recipient(s) from WooCommerce settings
    $admin_recipients_str = get_option( 'woocommerce_stock_email_recipient' );
    if ( empty( $admin_recipients_str ) ) {
        $admin_recipients_str = get_option( 'admin_email' );
    }
    $admin_recipients = array_map( 'trim', explode( ',', $admin_recipients_str ) );

    // Get Vendor email
    $vendor_email = '';
    $vendor_id    = 0;
    if ( function_exists( 'wcfm_get_vendor_id_by_post' ) ) {
        $vendor_id = (int) wcfm_get_vendor_id_by_post( $product_id );
    }
    if ( ! $vendor_id ) {
        $vendor_id = (int) get_post_field( 'post_author', $product_id );
    }

    if ( $vendor_id ) {
        $vendor_user = get_userdata( $vendor_id );
        if ( $vendor_user && ! empty( $vendor_user->user_email ) ) {
            $vendor_email = trim( $vendor_user->user_email );
        }
    }

    // Combine existing recipients, admin recipients, and vendor email
    $all_recipients = array();

    // 1. Add current recipient list
    if ( ! empty( $recipient ) ) {
        $all_recipients = array_merge( $all_recipients, array_map( 'trim', explode( ',', $recipient ) ) );
    }

    // 2. Add admin recipients
    if ( ! empty( $admin_recipients ) ) {
        $all_recipients = array_merge( $all_recipients, $admin_recipients );
    }

    // 3. Add vendor recipient
    if ( ! empty( $vendor_email ) ) {
        $all_recipients[] = $vendor_email;
    }

    // Clean and unique the array
    $all_recipients = array_unique( array_filter( array_map( 'trim', $all_recipients ) ) );

    return implode( ', ', $all_recipients );
}
add_filter( 'woocommerce_email_recipient_low_stock', 'thaaniyamhub_route_stock_email_to_vendor', 99, 2 );
add_filter( 'woocommerce_email_recipient_no_stock', 'thaaniyamhub_route_stock_email_to_vendor', 99, 2 );


/**
 * Ensure stock emails send as HTML.
 */
add_filter( 'woocommerce_email_headers', function( $headers, $id ) {
    if ( in_array( $id, [ 'low_stock', 'no_stock' ], true ) ) {
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    }
    return $headers;
}, 20, 2 );

/**
 * Format stock email messages in the beautiful Thaaniyam Hub container.
 */
function thaaniyamhub_format_stock_email_content( $message, $product ) {
    $heading = strpos( current_filter(), 'no_stock' ) !== false ? 'Out of Stock Alert' : 'Low Stock Alert';
    $body    = wpautop( $message );
    return thaaniyamhub_email_content_wrapper( $body, $heading );
}
add_filter( 'woocommerce_email_content_low_stock', 'thaaniyamhub_format_stock_email_content', 20, 2 );
add_filter( 'woocommerce_email_content_no_stock', 'thaaniyamhub_format_stock_email_content', 20, 2 );

/**
 * Remove strikethrough/strike-out prices (<del>...</del>) and underlines (<ins>...</ins>) across all emails.
 */
function thaaniyamhub_remove_strikethrough_price( $price_html ) {
    if ( empty( $price_html ) || ! is_string( $price_html ) ) {
        return $price_html;
    }
    // Strip <del>...</del> completely
    $clean = preg_replace( '/<del[^>]*>.*?<\/del>\s*/is', '', $price_html );
    // Strip <ins> tag wrapper
    $clean = preg_replace( '/<ins[^>]*>(.*?)<\/ins>/is', '$1', $clean );
    return $clean;
}
add_filter( 'woocommerce_get_formatted_order_total', 'thaaniyamhub_remove_strikethrough_price', 99, 1 );

add_filter( 'woocommerce_get_order_item_totals', function( $totals, $order, $tax_display ) {
    if ( is_array( $totals ) ) {
        foreach ( $totals as $key => &$total ) {
            if ( isset( $total['value'] ) ) {
                $total['value'] = thaaniyamhub_remove_strikethrough_price( $total['value'] );
            }
        }
    }
    return $totals;
}, 99, 3 );

add_filter( 'woocommerce_email_styles', function( $css ) {
    $css .= ' del { display: none !important; visibility: hidden !important; height: 0 !important; max-height: 0 !important; font-size: 0 !important; line-height: 0 !important; } ins { text-decoration: none !important; border: none !important; } ';
    return $css;
}, 99 );

add_action( 'wp_footer', 'wcfmmp_custom_store_sidebar_toggle_script' );
function wcfmmp_custom_store_sidebar_toggle_script() {
    if ( ! function_exists( 'wcfmmp_is_store_page' ) || ! wcfmmp_is_store_page() ) {
        return;
    }
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Toggle categories dropdown on mobile when clicking header added by navaneetham - categories in store page
        $('.wcfm-store-page #wcfmmp-store .left_sidebar .wcfmmp-store-category .sidebar_heading').on('click', function() {
            if ($(window).width() <= 767) {
                var $widget = $(this).closest('.wcfmmp-store-category');
                $widget.toggleClass('open');
                $widget.find('.categories_list').slideToggle(300);
            }
        });
    });
    </script>
     <?php
}

/**
 * Ensure Reply-To header in admin and vendor emails reflects the registered customer's user account email.
 */
add_filter( 'woocommerce_email_headers', function( $headers, $email_id, $order = null ) {
    if ( is_a( $order, 'WC_Order' ) && in_array( $email_id, [ 'new_order', 'cancelled_order', 'failed_order', 'store_new_order' ], true ) ) {
        $user = $order->get_user();
        if ( $user && ! empty( $user->user_email ) ) {
            $full_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
            if ( empty( $full_name ) ) {
                $full_name = $user->display_name;
            }
            $lines = explode( "\r\n", $headers );
            $filtered_lines = array();
            foreach ( $lines as $line ) {
                if ( stripos( $line, 'Reply-To:' ) === false && ! empty( trim( $line ) ) ) {
                    $filtered_lines[] = $line;
                }
            }
            $filtered_lines[] = 'Reply-To: ' . ( $full_name ? $full_name . ' ' : '' ) . '<' . $user->user_email . '>';
            $headers = implode( "\r\n", $filtered_lines ) . "\r\n";
        }
    }
    return $headers;
}, 50, 3 );
