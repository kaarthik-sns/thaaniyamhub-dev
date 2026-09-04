<?php
/**
 * Archive Product Template
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/archive-product.php
 *
 * @package WooCommerce/Templates
 * @version 8.6.0
 */
defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

// ==============================
// DYNAMIC PRICE RANGE
// ==============================
global $wpdb;

$prices = $wpdb->get_row("
    SELECT MIN(meta_value+0) AS min_price, MAX(meta_value+0) AS max_price
    FROM {$wpdb->postmeta}
    WHERE meta_key = '_price'
");

$max_price = ceil( $prices->max_price );
$step = 100;
?>

<section class="custom-shop-page">
    <div class="inner-container">
        <div class="row">

            <!-- LEFT SIDEBAR -->
            <div class="col-md-3">
				<div class="shop-sidebar custom-shop-page-sidebar">

					<!-- CATEGORIES -->
					<div class="shop-category-box dropdown-box">
						<h4 class="sidebar-title dropdown-toggle">
							Categories 
						</h4>

						<div class="dropdown-content">

							<div class="shop-all-link">
								<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">
									Shop All
								</a>
							</div>

							<ul class="shop-category-list">
								<?php
								$custom_order = [
									'breakfast-essentials',
									'ready-mix',
									'millet-cookies',
									'millet-noodles',
									'millet-pasta',
									'millet-vermicelli-sevai',
									'millet-sweets',
									'south-indian-snacks',
									'millet-snacks',
									'bread-chips'
								];
								$categories = get_terms([
									'taxonomy'   => 'product_cat',
									'parent'     => 0,
									'hide_empty' => false,
									'slug'       => $custom_order,
								]);
								usort($categories, function($a, $b) use ($custom_order) {
									return array_search($a->slug, $custom_order) - array_search($b->slug, $custom_order);
								});

								// Get current category slug
								$current_cat = get_queried_object();

								if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) :
									foreach ( $categories as $cat ) :
										// Check if this category matches current page
										$is_active = ( is_a( $current_cat, 'WP_Term' ) && $current_cat->slug === $cat->slug ) ? 'active' : '';
								?>
									<li>
										<a href="<?php echo esc_url( get_term_link( $cat ) ); ?>" class="<?php echo $is_active; ?>">
											<?php echo esc_html( $cat->name ); ?>
										</a>
									</li>
								<?php
									endforeach;
								endif;
								?>
							</ul>

						</div>
					</div>

					<!-- VENDORS -->
					<div class="shop-brand-box dropdown-box">
						<h4 class="sidebar-title dropdown-toggle">
							Brands
						</h4>

						<div class="dropdown-content">
							<ul class="shop-brand-list">
								<?php
								$vendors = get_users([
									'role'    => 'wcfm_vendor',
									'orderby' => 'display_name',
									'order'   => 'ASC',
									'fields'  => ['ID', 'display_name']
								]);

								if (!empty($vendors)) :
									foreach ($vendors as $vendor) :

										$store_name = get_user_meta($vendor->ID, 'store_name', true);
										$name = $store_name ? $store_name : $vendor->display_name;

										// WCFM store page link
										$store_url = wcfmmp_get_store_url($vendor->ID);
								?>
									<li>
										<a href="<?php echo esc_url($store_url); ?>">
											<?php echo esc_html($name); ?>
										</a>
									</li>
								<?php
									endforeach;
								endif;
								?>
							</ul>
						</div>
					</div>

					<!-- COMBO PRODUCTS -->
					<div class="shop-combo-box">
						<h4 class="sidebar-title ">
							<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) . '?product_type=woosb' ); ?>">
								Combo Products
							</a>
						</h4>
					</div>


					<!-- FILTER -->
					<div class="shop-filter-box">
						<h4 class="sidebar-title">Filter</h4>

						<!-- Sort -->
						<div class="filter-field">
							<label>Sort By</label>
							<?php woocommerce_catalog_ordering(); ?>
						</div>

						<!-- PRICE -->
						<div class="filter-field">
							<label>Price</label>
							<select id="price-range">
								<option value="">All Prices</option>

								<?php
								for ( $p = 0; $p < $max_price; $p += $step ) :
									$from = $p;
									$to   = $p + $step;
								?>
									<option value="<?php echo esc_attr( "$from-$to" ); ?>">
										₹<?php echo number_format( $from ); ?> – ₹<?php echo number_format( $to ); ?>
									</option>
								<?php endfor; ?>

								<option value="<?php echo esc_attr( $max_price ); ?>+">
									Above ₹<?php echo number_format( $max_price ); ?>
								</option>
							</select>
						</div>
					</div>

				</div>
			</div>


            <!-- PRODUCTS -->
            <div class="col-md-9">
				<div class="shop-products-area">

					<!-- PAGE TITLE -->
					<!-- <h2 class="shop-page-title"><?php woocommerce_page_title(); ?></h2> -->
					 <!-- SEARCH BOX ABOVE PRODUCTS -->
					<div class="shop-top-search">
						<?php get_product_search_form(); ?>
					</div>

                	<?php if ( woocommerce_product_loop() ) : ?>

						<?php do_action( 'woocommerce_before_shop_loop' ); ?>

						<?php woocommerce_product_loop_start(); ?>

						<?php while ( have_posts() ) : the_post(); ?>
							<?php wc_get_template_part( 'content', 'product' ); ?>
						<?php endwhile; ?>

						<?php woocommerce_product_loop_end(); ?>

						<?php do_action( 'woocommerce_after_shop_loop' ); ?>

					<?php else : ?>

						<?php do_action( 'woocommerce_no_products_found' ); ?>

					<?php endif; ?>

            	</div>
			</div>

        </div>
    </div>
</section>

<!-- Top Picks Slider -->
<section class="top-picks">
    <div class="inner-container">
        <div class="section-header">
			<h2>Top Picks for you</h2>
			<a href="#">View More Products</a>
		</div>
        <?php echo do_shortcode('[products limit="4" columns="4" orderby="rand"]'); ?>
    </div>
</section>

<style>
	.top-picks .products {
		display: block;
	}
	.top-picks .product {
		padding: 0 10px;
	}
</style>
<?php get_footer( 'shop' ); ?>

<script>
	document.addEventListener("DOMContentLoaded", function () {
		const select = document.getElementById("price-range");
		if (!select) return;

		select.addEventListener("change", function () {
			const url = new URL(window.location.href);
			const value = this.value;

			url.searchParams.delete("min_price");
			url.searchParams.delete("max_price");

			if (value.includes("+")) {
				url.searchParams.set("min_price", value.replace("+", ""));
			} 
			else if (value.includes("-")) {
				const p = value.split("-");
				url.searchParams.set("min_price", p[0]);
				url.searchParams.set("max_price", p[1]);
			}

			window.location.href = url.toString();
		});

		// keep selected
		const params = new URLSearchParams(window.location.search);
		const min = params.get("min_price");
		const max = params.get("max_price");

		if (min && max) select.value = min + "-" + max;
		else if (min && !max) select.value = min + "+";
	});
</script>

<!-- /* Dropdown Categories & Brands script */ -->
<script>
	function mobileSidebarDropdown() {
		if (window.innerWidth <= 767) {
			document.querySelectorAll('.custom-shop-page-sidebar .dropdown-toggle')
				.forEach(toggle => {
					toggle.onclick = function () {
						this.parentElement.classList.toggle('active');
					};
				});
		}
	}

	mobileSidebarDropdown();
	window.addEventListener('resize', mobileSidebarDropdown);
</script>


<style>
	.shop-vendor-list{
		list-style: none;
	}
</style>