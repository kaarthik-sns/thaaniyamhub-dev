<?php
/**
 * The product images.
 *
 * @package    Woo_Gallery_Slider
 * @subpackage Woo_Gallery_Slider/public
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}  // if direct access.

if ( ! class_exists( 'WCGS_Product_Gallery' ) ) {
	/**
	 * WooCommerce Product Gallery Slider.
	 */
	class WCGS_Product_Gallery {
		/**
		 * Gallery settings
		 *
		 * @var array
		 */
		private $settings;

		/**
		 * Assigns data
		 *
		 * @var mixed
		 */
		private $assigns_data;

		/**
		 * Current product
		 *
		 * @var WC_Product
		 */
		private $product;

		/**
		 * Helper functions.
		 *
		 * @var function
		 */
		private $helper;

		/**
		 * Gallery images and videos
		 *
		 * @var array
		 */
		private $gallery = array();

		/**
		 * Product id.
		 *
		 * @var mixed
		 */
		private $product_id = null;

		/**
		 * Product type.
		 *
		 * @var mixed
		 */
		private $product_type = '';

		/**
		 * Constructor
		 */
		public function __construct() {
			$this->product      = $this->get_product();
			$this->product_id   = $this->product->get_id();
			$this->product_type = $this->product->get_type();
			$this->helper       = new WCGS_Public_Helper();
			$this->settings     = get_option( 'wcgs_settings', array() );
			$this->display_gallery_output();
		}
		/**
		 * Get lazy load attribute
		 *
		 * @return string
		 */
		private function get_lazy_load_attribute() {
			return isset( $this->settings['wcgs_image_lazy_load'] ) && 'ondemand' === $this->settings['wcgs_image_lazy_load'] ? 'loading="lazy"' : '';
		}
		/**
		 * Update with Assigned Layout data.
		 *
		 * @return void
		 */
		private function assigns_layout() {
			$assigned_layout = $this->helper->get_assigns_layout( $this->product );
			// Apply first matching layout.
			if ( ! empty( $assigned_layout ) ) {
				$this->apply_assigned_layout( $assigned_layout[0] );
			}
		}

		/**
		 * Apply the assigned layout settings.
		 *
		 * @param int $layout_id Layout post ID.
		 */
		private function apply_assigned_layout( $layout_id ) {
			$assigned_options = get_post_meta( $layout_id, 'wcgs_metabox', true );
			if ( ! empty( $assigned_options ) ) {
				$this->settings = $this->helper->wp_parse_args_recursive( $assigned_options, $this->settings );
			}
		}

		/**
		 * Get current product
		 *
		 * @return WC_Product|null
		 */
		private function get_product() {
			global $product;
			$product = $product ? $product : wc_get_product( get_the_ID() );
			return $product;
		}

		/**
		 * Get slider configuration
		 *
		 * @return array
		 */
		public function get_slider_config() {
			return array(
				'slider_dir'            => $this->settings['slider_dir'] ?? '',
				'thumbnailnavigation'   => $this->settings['thumbnailnavigation'] ?? false,
				'navigation'            => $this->settings['navigation'] ?? true,
				'video_popup_place'     => $this->settings['video_popup_place'] ?? 'popup',
				'preloader'             => $this->settings['preloader'] ?? true,
				'slider_dir_rtl'        => ! empty( $this->settings['slider_dir'] ) ? 'dir=rtl' : '',
				'include_feature_image' => $this->get_feature_image_setting(),
			);
		}

		/**
		 * Get feature image setting
		 *
		 * @return array
		 */
		private function get_feature_image_setting() {
			$include_feature_image = $this->settings['include_feature_image_to_gallery'] ?? array( 'default_gl' );
			if ( is_string( $include_feature_image ) ) {
				return array( $include_feature_image );
			}
			return $include_feature_image;
		}

		/**
		 * Process variable product gallery
		 */
		private function process_variable_gallery() {
			$default_variation = $this->get_default_variation();
			if ( empty( $default_variation ) ) {
				return;
			}
			$this->add_variation_feature_image( $default_variation );
		}

		/**
		 * Get default variation
		 *
		 * @return array
		 */
		private function get_default_variation() {
			$default_attrs = $this->product->get_default_attributes();
			$slug_attr     = apply_filters( 'sp_woo_gallery_slider_use_slug_attr', true );
			if ( $slug_attr ) {
				$selected_attrs = $this->get_selected_attributes();
				if ( ! empty( $selected_attrs ) ) {
					return $selected_attrs;
				}
			}

			return $default_attrs;
		}

		/**
		 * Get selected attributes from request
		 *
		 * @return array
		 */
		private function get_selected_attributes() {
			$selected   = array();
			$attributes = $this->product->get_attributes();
			foreach ( $attributes as $attribute_name => $options ) {
				$key = 'attribute_' . sanitize_title( $attribute_name );
				if ( isset( $_REQUEST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Not strictly necessary here.
					$selected[ $attribute_name ] = wc_clean( wp_unslash( $_REQUEST[ $key ] ) ); // phpcs:ignore
				}
			}
			return $selected;
		}
		/**
		 * Add variation feature image to gallery
		 *
		 * @param array $default_variation The default variation.
		 */
		private function add_variation_feature_image( $default_variation ) {
			$image_id = $this->product->get_image_id();
			if ( is_array( $this->get_feature_image_setting() ) &&
			in_array( 'variable_gl', $this->get_feature_image_setting(), true ) &&
			$image_id ) {
				$this->gallery[] = $this->helper->wcgs_image_meta( $image_id, $this->settings );
			}

			$_temp_variations   = $this->prepare_variation_attributes( $default_variation );
			$variation_id       = $this->find_matching_variation( $_temp_variations );
			$variation_image_id = get_post_thumbnail_id( $variation_id );

			if ( $this->validate_image_id( $variation_image_id ) ) {
				$this->gallery[] = $this->helper->wcgs_image_meta( $variation_image_id, $this->settings );
			}
			$this->add_variation_gallery_images( $variation_id );
		}

		/**
		 * Validate image ID
		 *
		 * @param int $image_id Image ID to validate.
		 * @return bool
		 */
		private function validate_image_id( $image_id ) {
			return ! empty( $image_id ) && wp_attachment_is_image( $image_id );
		}

		/**
		 * Prepare variation attributes
		 *
		 * @param array $default_variation default variation attributes.
		 * @return array
		 */
		private function prepare_variation_attributes( $default_variation ) {
			$_temp_variations = array();
			foreach ( $default_variation as $key => $value ) {
				$_temp_variations[ 'attribute_' . $key ] = $value;
			}
			return $_temp_variations;
		}

		/**
		 * Find matching variation ID
		 *
		 * @param array $attributes product attributes.
		 * @return int
		 */
		private function find_matching_variation( $attributes ) {
			$data_store = WC_Data_Store::load( 'product' );
			return $data_store->find_matching_product_variation( $this->product, $attributes );
		}

		/**
		 * Add variation gallery images
		 *
		 * @param int $variation_id variation id.
		 */
		private function add_variation_gallery_images( $variation_id ) {
			$woo_gallery_slider = get_post_meta( $variation_id, 'woo_gallery_slider', true );
			if ( empty( $woo_gallery_slider ) ) {
				return;
			}
			$gallery_arr = strpos( $woo_gallery_slider, ']' ) !== false ? substr( $woo_gallery_slider, 1, -1 ) : $woo_gallery_slider;
			if ( strpos( $gallery_arr, ',' ) ) {
				$this->add_multiple_gallery_images( $gallery_arr );
			} else {
				$this->add_single_gallery_image( $gallery_arr );
			}
			$this->add_fallback_feature_image();
		}

		/**
		 * Add multiple gallery images
		 *
		 * @param string $gallery_arr Gallery array string.
		 */
		private function add_multiple_gallery_images( $gallery_arr ) {
			$count         = 1;
			$gallery_array = explode( ',', $gallery_arr );
			foreach ( $gallery_array as $gallery_item ) {
				if ( 2 >= $count ) {
					if ( $this->validate_image_id( $gallery_item ) ) {
						$this->gallery[] = $this->helper->wcgs_image_meta( $gallery_item, $this->settings );
					}
				}
				++$count;
			}
		}

		/**
		 * Add single gallery image
		 *
		 * @param string $gallery_arr Gallery item.
		 */
		private function add_single_gallery_image( $gallery_arr ) {
			if ( $this->validate_image_id( $gallery_arr ) ) {
				$this->gallery[] = $this->helper->wcgs_image_meta( $gallery_arr, $this->settings );
			}
		}

		/**
		 * Add fallback feature image if gallery is empty
		 */
		private function add_fallback_feature_image() {
			if ( empty( $this->gallery[0] ) ) {
				$image_id = $this->product->get_image_id();
				if ( $this->validate_image_id( $image_id ) ) {
					$this->gallery[] = $this->helper->wcgs_image_meta( $image_id, $this->settings );
				}
			}
		}

		/**
		 * Process simple product gallery
		 */
		private function process_simple_gallery() {
			$this->add_simple_product_feature_image();
			$this->add_simple_product_gallery_images();
		}

		/**
		 * Add feature image for simple product
		 */
		private function add_simple_product_feature_image() {
			$image_id = $this->product->get_image_id();
			if ( is_array( $this->get_feature_image_setting() ) &&
			in_array( 'default_gl', $this->get_feature_image_setting(), true ) &&
			$this->validate_image_id( $image_id ) ) {
				$this->gallery[] = $this->helper->wcgs_image_meta( $image_id, $this->settings );
			}
		}

		/**
		 * Add gallery images for simple product
		 */
		private function add_simple_product_gallery_images() {
			$gallery_image_source = $this->settings['gallery_image_source'] ?? 'uploaded';
			if ( 'attached' === $gallery_image_source ) {
				$this->add_attached_images();
			} else {
				$this->add_uploaded_images();
			}
		}

		/**
		 * Sort gallery items.
		 */
		private function sort_gallery_items() {
			$video_order = $this->settings['video_order'] ?? '';
			// Sort videos first or last based on settings.
			if ( 'video_come_last' === $video_order ) {
				usort( $this->gallery, array( $this, 'sort_videos_last' ) );
			}
		}

		/**
		 * Sort callback to put videos last.
		 *
		 * @param  array $a List.
		 * @param  array $b List.
		 * @return array
		 */
		private function sort_videos_last( $a, $b ) {
			return ( isset( $a['video'] ) ? 1 : 0 ) - ( isset( $b['video'] ) ? 1 : 0 );
		}

		/**
		 * Add images attached to product content
		 */
		private function add_attached_images() {
			$wgsc_post           = get_post( $this->product_id );
			$wcgs_post_content   = $wgsc_post->post_content;
			$wcgs_search_pattern = '~<img [^\>]*\ />~';
			preg_match_all( $wcgs_search_pattern, $wcgs_post_content, $post_images );
			$wcgs_number_of_images = count( $post_images[0] );
			if ( $wcgs_number_of_images > 0 ) {
				foreach ( $post_images[0] as $image ) {
					$image_id = $this->extract_image_id( $image );
					if ( $image_id && $this->validate_image_id( $image_id ) ) {
						$this->gallery[] = $this->helper->wcgs_image_meta( $image_id, $this->settings );
					}
				}
			}
		}

		/**
		 * Extract image ID from img tag
		 *
		 * @param string $image Image HTML tag.
		 * @return string|null
		 */
		private function extract_image_id( $image ) {
			$class_start     = substr( $image, strpos( $image, 'class="' ) + 7 );
			$class_end       = substr( $class_start, 0, strpos( $class_start, '" ' ) );
			$image_class_pos = strpos( $class_end, 'wp-image-' );
			if ( false !== $image_class_pos ) {
				return substr( $class_end, $image_class_pos + 9 );
			}
			return null;
		}

		/**
		 * Add uploaded gallery images
		 */
		private function add_uploaded_images() {
			$attachment_ids = $this->product->get_gallery_image_ids();
			foreach ( $attachment_ids as $attachment_id ) {
				if ( $this->validate_image_id( $attachment_id ) ) {
					$this->gallery[] = $this->helper->wcgs_image_meta( $attachment_id, $this->settings );
				}
			}
		}

		/**
		 * Build gallery based on product type
		 *
		 * @return array
		 */
		public function build_gallery() {
			try {
				if ( ! empty( $this->get_default_variation() ) ) {
					$this->process_variable_gallery();
				} else {
					$this->process_simple_gallery();
				}

				if ( empty( $this->gallery ) ) {
					$image_id = $this->product->get_image_id();
					if ( $this->validate_image_id( $image_id ) ) {
						$this->gallery[] = $this->helper->wcgs_image_meta( $image_id, $this->settings );
					}
				}

				$this->sort_gallery_items();
			} catch ( Exception $e ) {
				return array();
			}
		}

		/**
		 * Display gallery output.
		 *
		 * @return void
		 */
		public function display_gallery_output() {
			$cache_key  = 'wcgsf_woo_gallery_' . $this->product_id . WOO_GALLERY_SLIDER_VERSION;
			$cache_data = $this->helper->spwg_get_transient( $cache_key );

			ob_start();
				$this->assigns_layout();
				$this->render_gallery();
				$gallery = $this->helper->minify_output( ob_get_clean() );
			echo $gallery; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}


		/**
		 * Render gallery.
		 *
		 * @return void
		 */
		public function render_gallery() {
			$this->build_gallery();
			$gallery              = $this->gallery;
			$slider_config        = $this->get_slider_config();
			$slider_dir_rtl       = $slider_config['slider_dir_rtl'];
			$thumbnailnavigation  = $slider_config['thumbnailnavigation'];
			$navigation           = $slider_config['navigation'];
			$preloader            = $slider_config['preloader'];
			$video_popup_place    = $slider_config['video_popup_place'];
			$product_id           = $this->product_id;
			$settings             = $this->settings;
			$lightbox             = isset( $settings['lightbox'] ) ? $settings['lightbox'] : '';
			$image_size           = isset( $settings['image_sizes'] ) ? $settings['image_sizes'] : 'woocommerce_single';
			$thumb_nav_visibility = isset( $settings['thumb_nav_visibility'] ) ? $settings['thumb_nav_visibility'] : 'hover';
			$_navigation_position = isset( $settings['navigation_position'] ) ? $settings['navigation_position'] : 'center_center';
			$lightbox_icon        = isset( $settings['lightbox_icon'] ) ? $settings['lightbox_icon'] : 'search';

			$layout_class = '';
			if ( isset( $this->settings['gallery_layout'] ) && ( ( 'vertical_right' === $this->settings['gallery_layout'] ) || ( ( 'vertical' === $this->settings['gallery_layout'] ) ) ) ) {
				$layout_class .= 'vertical wcgs-vertical-right';
				if ( isset( $this->settings['slider_height_type'] ) && 'fix_height' === $this->settings['slider_height_type'] ) {
						$layout_class .= ' wcgs-fixed-height';
				}
			} else {
				$layout_class .= 'horizontal';
			}

			if ( 'bottom_left' === $_navigation_position ) {
				$layout_class .= ' wcgs-nav-bottom-left';
			}

			$preloader_style                 = ! empty( $this->settings['preloader_style'] ) ? $this->settings['preloader_style'] : 'normal';
			$thumbnailnavigation_style       = ! empty( $this->settings['thumbnailnavigation_style'] ) ? $this->settings['thumbnailnavigation_style'] : 'custom';
			$thumbnailnavigation_style_class = ' thumbnailnavigation-' . $thumbnailnavigation_style;
			if ( $preloader ) {
				$layout_class .= ' wcgs_preloader_' . $preloader_style;
			}
			$lightbox_icon_position = ! empty( $this->settings['lightbox_icon_position'] ) ? $this->settings['lightbox_icon_position'] : 'top-right';
			$gallery_width          = ! empty( $settings['gallery_width'] ) ? $settings['gallery_width'] : 0;
			?>
	<div id="wpgs-gallery" <?php echo wp_kses_post( $slider_dir_rtl ); ?> class="wcgs-woocommerce-product-gallery wcgs-spswiper-before-init <?php echo esc_html( $layout_class ); ?>" style='min-width: <?php echo esc_attr( $gallery_width ); ?>%; overflow: hidden;' data-id="<?php echo esc_attr( $product_id ); ?>">
		<div class="gallery-navigation-carousel-wrapper <?php echo esc_html( $layout_class ); ?>">
			<div thumbsSlider="" class="gallery-navigation-carousel spswiper <?php echo esc_html( $layout_class . $thumbnailnavigation_style_class ); ?> <?php echo esc_attr( $thumb_nav_visibility ); ?>">
				<div class="spswiper-wrapper">
					<?php
					foreach ( $gallery as $slide ) {
						if ( isset( $slide['full_url'] ) && ! empty( $slide['full_url'] ) ) {
							$video_type = '';
							$has_video  = isset( $slide['video'] ) && ! empty( $slide['video'] );
							if ( $has_video ) {
								$video     = $slide['video'];
								$video_url = wp_parse_url( $video );
								if ( isset( $video_url['host'] ) && strpos( $video_url['host'], 'youtu' ) !== false ) {
									$video_type = 'youtube';
								}
							}
							?>
						<div class="wcgs-thumb spswiper-slide">
							<img alt="<?php echo esc_html( $slide['alt_text'] ); ?>" data-cap="<?php echo esc_html( $slide['cap'] ); ?>" src="<?php echo esc_url( $slide['thumb_url'] ); ?>" data-image="<?php echo esc_url( $slide['full_url'] ); ?>" data-type="<?php echo esc_attr( $video_type ); ?>" width="<?php echo esc_attr( $slide['thumbWidth'] ); ?>" height="<?php echo esc_attr( $slide['thumbHeight'] ); ?>" <?php echo wp_kses_post( $this->get_lazy_load_attribute() ); ?> />
						</div>
							<?php
						}
					}
					?>
				</div>
				<?php if ( $thumbnailnavigation ) { ?>
						<div class="wcgs-spswiper-button-next wcgs-spswiper-arrow"></div>
						<div class="wcgs-spswiper-button-prev wcgs-spswiper-arrow"></div>
				<?php } ?>
			</div>
			<div class="wcgs-border-bottom"></div>
		</div>
		<div class="wcgs-carousel <?php echo esc_html( $layout_class ); ?> spswiper">
			<div class="spswiper-wrapper">
				<?php
				$index = 1;
				foreach ( $gallery as $slide ) {
					if ( isset( $slide['full_url'] ) && ! empty( $slide['full_url'] ) ) {
						?>
						<div class="spswiper-slide">
						<div class="wcgs-slider-image">
						<?php
						$has_video   = isset( $slide['video'] ) && ! empty( $slide['video'] );
						$full_srcset = wp_get_attachment_image_srcset( $slide['id'], $image_size );
						$image_sizes = wp_get_attachment_image_sizes( $slide['id'], $image_size );
						if ( $has_video ) {
							$video     = $slide['video'];
							$video_url = wp_parse_url( $video );
							if ( isset( $video_url['host'] ) && strpos( $video_url['host'], 'youtu' ) !== false ) {
								// Check if it's a YouTube Shorts URL.
								parse_str( $video, $video_query_array );
								$video_id = array_values( $video_query_array )[0];
								?>
									<a  class="wcgs-slider-lightbox" href="<?php echo esc_url( $video ); ?>" data-fancybox="view" data-fancybox-type="iframe" data-fancybox-height="600" data-fancybox-width="400" aria-label="lightbox-icon"></a>
									<?php
									if ( 'inline' === $video_popup_place ) {
										?>
										<div class="wcgs-iframe-wrapper">
										<div class="wcgs-iframe wcgs-youtube-video" data-video-id="<?php echo esc_attr( $video_id ); ?>" data-src="<?php echo esc_attr( $video ); ?>"></div><img class="wcgs-slider-image-tag" style="visibility: hidden" alt="<?php echo esc_html( $slide['alt_text'] ); ?>" data-cap="<?php echo esc_html( $slide['cap'] ); ?>" src="<?php echo esc_url( $slide['url'] ); ?>" data-image="<?php echo esc_url( $slide['full_url'] ); ?>" width="<?php echo esc_attr( $slide['imageWidth'] ); ?>" height="<?php echo esc_attr( $slide['imageHeight'] ); ?>" srcset="<?php echo esc_html( $full_srcset ); ?>" sizes="<?php echo esc_html( $image_sizes ); ?>"  <?php echo 1 === $index ? 'fetchpriority="high" loading="eager"' : wp_kses_post( $this->get_lazy_load_attribute() ); ?> /></div>
										<?php
									} else {
										?>
										<img class="wcgs-slider-image-tag" alt="<?php echo esc_html( $slide['alt_text'] ); ?>" data-cap="<?php echo esc_html( $slide['cap'] ); ?>" src="<?php echo esc_url( $slide['url'] ); ?>" data-image="<?php echo esc_url( $slide['full_url'] ); ?>" width="<?php echo esc_attr( $slide['imageWidth'] ); ?>" height="<?php echo esc_attr( $slide['imageHeight'] ); ?>" data-type="youtube"  <?php echo 1 === $index ? 'fetchpriority="high" loading="eager"' : wp_kses_post( $this->get_lazy_load_attribute() ); ?>  srcset="<?php echo esc_html( $full_srcset ); ?>" sizes="<?php echo esc_html( $image_sizes ); ?>" />
										<?php
									}
							} else {
								?>
									<a class="wcgs-slider-lightbox" data-fancybox="view" href="<?php echo esc_url( $slide['full_url'] ); ?>" aria-label="lightbox" data-caption="<?php echo esc_attr( $slide['cap'] ); ?>"></a>
									<img class="wcgs-slider-image-tag" <?php echo 1 === $index ? 'fetchpriority="high" loading="eager"' : wp_kses_post( $this->get_lazy_load_attribute() ); ?>  alt="<?php echo esc_html( $slide['alt_text'] ); ?>" data-cap="<?php echo esc_html( $slide['cap'] ); ?>" src="<?php echo esc_url( $slide['url'] ); ?>" data-image="<?php echo esc_url( $slide['full_url'] ); ?>" width="<?php echo esc_attr( $slide['imageWidth'] ); ?>" height="<?php echo esc_attr( $slide['imageHeight'] ); ?>" srcset="<?php echo esc_html( $full_srcset ); ?>" sizes="<?php echo esc_html( $image_sizes ); ?>" />
										<?php
							}
						} else {
							?>
								<a class="wcgs-slider-lightbox" data-fancybox="view" href="<?php echo esc_url( $slide['full_url'] ); ?>" aria-label="lightbox" data-caption="<?php echo esc_attr( $slide['cap'] ); ?>"></a>
								<img class="wcgs-slider-image-tag" <?php echo 1 === $index ? 'fetchpriority="high" loading="eager"' : wp_kses_post( $this->get_lazy_load_attribute() ); ?>  alt="<?php echo esc_html( $slide['alt_text'] ); ?>" data-cap="<?php echo esc_html( $slide['cap'] ); ?>" src="<?php echo esc_url( $slide['url'] ); ?>" data-image="<?php echo esc_url( $slide['full_url'] ); ?>" width="<?php echo esc_attr( $slide['imageWidth'] ); ?>" height="<?php echo esc_attr( $slide['imageHeight'] ); ?>" srcset="<?php echo esc_html( $full_srcset ); ?>" sizes="<?php echo esc_html( $image_sizes ); ?>" />
									<?php
						}
						++$index;
						?>
							</div>
						</div>
						<?php
					}
				}
				?>
			</div>
			<div class="spswiper-pagination"></div>
			<?php
			if ( $navigation ) {
				?>
				<div class="wcgs-spswiper-button-next wcgs-spswiper-arrow"></div>
				<div class="wcgs-spswiper-button-prev wcgs-spswiper-arrow"></div>
				<?php
			}
			?>
			<?php
			if ( $lightbox ) {

				$lightbox_icon_attr = '';
				switch ( $lightbox_icon ) {
					case 'search':
						$lightbox_icon_attr = 'sp_wgs-icon-search';
						break;
					case 'search-plus':
						$lightbox_icon_attr = 'sp_wgs-icon-zoom-in-1';
						break;
					case 'plus':
						$lightbox_icon_attr = 'sp_wgs-icon-plus';
						break;
					case 'info':
						$lightbox_icon_attr = 'sp_wgs-icon-info';
						break;
				}
				?>
			<div class="wcgs-lightbox <?php echo esc_html( $lightbox_icon_position ); ?>"><span class="sp_wgs-lightbox"><span class="<?php echo esc_attr( $lightbox_icon_attr ); ?>"></span></span></div>
				<?php
			}
			?>
		</div>
			<?php
			if ( $preloader ) {
				?>
				<div class="wcgs-gallery-preloader" style="opacity: 1; z-index: 9999;"></div>
			<?php } ?>
		</div>
			<?php
		}
	}
}
new WCGS_Product_Gallery();
