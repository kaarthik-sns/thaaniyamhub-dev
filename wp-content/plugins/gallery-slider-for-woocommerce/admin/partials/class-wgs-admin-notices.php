<?php
/**
 * The admin notice file.
 *
 * @link http://shapedplugin.com
 * @since 1.0.10
 * @package Woo_Gallery_Slider.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}  // if direct access.

/**
 * The admin facing notices.
 *
 * @since        1.0.10
 *
 * @package    Woo_Gallery_Slider
 * @subpackage Woo_Gallery_Slider/admin/partials/notices
 * @author     ShapedPlugin<support@shapedplugin.com>
 */
class Woo_Gallery_Slider_Admin_Notices {

	/**
	 * Constructor function the class
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'all_admin_notice' ) );
		add_action( 'wp_ajax_sp-woogs-never-show-review-notice', array( $this, 'dismiss_review_notice' ) );
		add_action( 'wp_ajax_dismiss_smart_swatches_notice', array( $this, 'dismiss_smart_swatches_notice' ) );
	}

	/**
	 * Display all admin notice for backend.
	 *
	 * @return void
	 */
	public function all_admin_notice() {
		$this->display_review_notice();
		$this->smart_swatches_install_admin_notice();
	}

	/**
	 * Display review notice for backend.
	 *
	 * @return void
	 */
	public function display_review_notice() {
		// Show only to Admins.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Variable default value.
		$review = get_option( 'sp_woo_gallery_slider_review_notice_dismiss' );
		$time   = time();
		$load   = false;

		if ( ! $review ) {
			$review = array(
				'time'      => $time,
				'dismissed' => false,
			);
			add_option( 'sp_woo_gallery_slider_review_notice_dismiss', $review );
		} else {
			// Check if it has been dismissed or not.
			if ( ( isset( $review['dismissed'] ) && ! $review['dismissed'] ) && ( isset( $review['time'] ) && ( ( $review['time'] + ( DAY_IN_SECONDS * 3 ) ) <= $time ) ) ) {
				$load = true;
			}
		}

		// If we cannot load, return early.
		if ( ! $load ) {
			return;
		}
		?>
		<div id="sp-woogs-review-notice" class="sp-woogs-review-notice">
			<div class="sp-woogs-plugin-icon">
				<img src="<?php echo esc_url( 'https://ps.w.org/gallery-slider-for-woocommerce/assets/icon-256x256.gif' ); ?>" alt="Reno Product Gallery">
			</div>
			<div class="sp-woogs-notice-text">
				<h3>Enjoying <strong>Reno Product Gallery</strong>?</h3>
				<p>We hope you had a wonderful experience using <strong>Reno Product Gallery</strong>. Please take a moment to leave a review on <a href="https://wordpress.org/support/plugin/gallery-slider-for-woocommerce/reviews/" target="_blank"><strong>WordPress.org</strong></a>.
				Your positive review will help us improve. Thank you! 😊</p>

				<p class="sp-woogs-review-actions">
					<a href="https://wordpress.org/support/plugin/gallery-slider-for-woocommerce/reviews/" target="_blank" class="button button-primary notice-dismissed rate-woo-gallery-slider">Ok, you deserve ★★★★★</a>
					<a href="#" class="notice-dismissed remind-me-later"><span class="dashicons dashicons-clock"></span>Nope, maybe later
					</a>
					<a href="#" class="notice-dismissed never-show-again"><span class="dashicons dashicons-dismiss"></span>Never show again</a>
				</p>
			</div>
		</div>

		<script type='text/javascript'>

			jQuery(document).ready( function($) {
				$(document).on('click', '#sp-woogs-review-notice.sp-woogs-review-notice .notice-dismissed', function( event ) {
					if ( $(this).hasClass('rate-woo-gallery-slider') ) {
						var notice_dismissed_value = "1";
					}
					if ( $(this).hasClass('remind-me-later') ) {
						var notice_dismissed_value =  "2";
						event.preventDefault();
					}
					if ( $(this).hasClass('never-show-again') ) {
						var notice_dismissed_value =  "3";
						event.preventDefault();
					}

					$.post( ajaxurl, {
						action: 'sp-woogs-never-show-review-notice',
						notice_dismissed_data : notice_dismissed_value,
						nonce: '<?php echo esc_attr( wp_create_nonce( 'sp_woogs_review_notice' ) ); ?>'
					});

					$('#sp-woogs-review-notice.sp-woogs-review-notice').hide();
				});
			});

		</script>
		<?php
	}

	/**
	 * Dismiss review notice
	 *
	 * @since  1.0.10
	 *
	 * @return void
	 **/
	public function dismiss_review_notice() {
		$post_data = wp_unslash( $_POST );
		$nonce     = isset( $post_data['nonce'] ) ? sanitize_key( $post_data['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'sp_woogs_review_notice' ) ) {
			return;
		}
		$review = get_option( 'sp_woo_gallery_slider_review_notice_dismiss' );
		if ( ! $review ) {
			$review = array();
		}

		$dismiss_status = isset( $post_data['notice_dismissed_data'] ) ? sanitize_text_field( wp_unslash( $post_data['notice_dismissed_data'] ) ) : '';

		switch ( $dismiss_status ) {
			case '1':
				$review['time']      = time();
				$review['dismissed'] = true;
				break;
			case '2':
				$review['time']      = time();
				$review['dismissed'] = false;
				break;
			case '3':
				$review['time']      = time();
				$review['dismissed'] = true;
				break;
		}
		update_option( 'sp_woo_gallery_slider_review_notice_dismiss', $review );
		die;
	}


	/**
	 * Dismiss Smart Swatches install notice message
	 *
	 * @since 2.4.4
	 *
	 * @return void
	 */
	public function dismiss_smart_swatches_notice() {
		$nonce = isset( $_GET['ajax_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['ajax_nonce'] ) ) : '';
		// Check the update permission and nonce verification.
		if ( ! current_user_can( 'install_plugins' ) || ! wp_verify_nonce( $nonce, 'smart-swatches-notice' ) ) {
			wp_send_json_error( array( 'error' => esc_html__( 'Authorization failed!', 'gallery-slider-for-woocommerce' ) ), 401 );
		}
		update_option( 'sp-smart-swatches-notice-dismissed', 1 );
		die;
	}
	/**
	 * Smart Swatches install admin notice.
	 */
	public function smart_swatches_install_admin_notice() {
		if ( is_plugin_active( 'smart-swatches/smart-swatches.php' ) || is_plugin_active( 'smart-swatches-pro/smart-swatches-pro.php' ) ) {
			return;
		}
		if ( get_option( 'sp-smart-swatches-notice-dismissed' ) ) {
			return;
		}

		$current_screen = get_current_screen();

		// The submenu screen base is prefixed with the sanitized parent menu title, so derive it instead of hardcoding it.
		$assign_layout_base = get_plugin_page_hookname( 'assign_layout', 'wpgs-settings' );
		$help_base          = get_plugin_page_hookname( 'wpgs-help', 'wpgs-settings' );

		if ( current_user_can( 'install_plugins' ) && is_object( $current_screen ) && is_object( $current_screen ) && 'wcgs_layouts' === $current_screen->post_type || 'toplevel_page_wpgs-settings' === $current_screen->base || $assign_layout_base === $current_screen->base || $help_base === $current_screen->base ) {
			$plugins = array_keys( get_plugins() );
			$slug    = 'smart-swatches';
			$icon    = 'https://ps.w.org/smart-swatches/assets/icon-128x128.gif';
			// $icon        = SP_WPS_URL . 'Admin/assets/images/woo-quick-view-notice.svg';
			$text        = esc_html__( 'Install', 'gallery-slider-for-woocommerce' );
			$button_text = esc_html__( 'Install Now', 'gallery-slider-for-woocommerce' );
			$install_url = esc_url( wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=' . $slug ), 'install-plugin_' . $slug ) );
			$arrow       = '<svg width="14" height="10" viewBox="0 0 14 10" fill="#2171B1" xmlns="http://www.w3.org/2000/svg">
			<path d="M13.8425 4.5226L10.465 0.290439C10.3403 0.138808 10.164 0.0428426 9.97274 0.0225711C9.7815 0.00229966 9.59007 0.0592883 9.43833 0.181617C9.29698 0.313072 9.20835 0.494686 9.18999 0.6906C9.17163 0.886513 9.22487 1.08246 9.33917 1.23966L11.7425 4.26263H0.723328C0.531488 4.26263 0.347494 4.3416 0.211843 4.4822C0.0761915 4.62279 0 4.81349 0 5.01232C0 5.21116 0.0761915 5.40182 0.211843 5.54241C0.347494 5.68301 0.531488 5.76202 0.723328 5.76202H11.7425L9.33917 8.78499C9.22616 8.94269 9.17373 9.13831 9.19206 9.33383C9.21038 9.52935 9.29815 9.71082 9.43833 9.84303C9.58951 9.96682 9.78128 10.0247 9.97296 10.0044C10.1646 9.98405 10.3411 9.88716 10.465 9.73421L13.8425 5.50204C13.9447 5.36535 14.0001 5.19731 14.0001 5.02439C14.0001 4.85147 13.9447 4.68347 13.8425 4.54677V4.5226Z"></path>
		</svg>';
			if ( in_array( 'smart-swatches/smart-swatches.php', $plugins, true ) ) {
				$text        = esc_html__( 'Activate', 'gallery-slider-for-woocommerce' );
				$button_text = esc_html__( 'Activate', 'gallery-slider-for-woocommerce' );
				$install_url = esc_url( self_admin_url( 'plugins.php?action=activate&plugin=' . urlencode( 'smart-swatches/smart-swatches.php' ) . '&plugin_status=all&paged=1&s&_wpnonce=' . urlencode( wp_create_nonce( 'activate-plugin_smart-swatches/smart-swatches.php' ) ) ) );
			}

			$popup_woo_url = esc_url(
				add_query_arg(
					array(
						'tab'       => 'plugin-information',
						'plugin'    => 'gallery-slider-for-woocommerce',
						'TB_iframe' => 'true',
						'width'     => '772',
						'height'    => '446',
					),
					admin_url( 'plugin-install.php' )
				)
			);
			$popup_url     = esc_url(
				add_query_arg(
					array(
						'tab'       => 'plugin-information',
						'plugin'    => $slug,
						'TB_iframe' => 'true',
						'width'     => '772',
						'height'    => '446',
					),
					admin_url( 'plugin-install.php' )
				)
			);
			$nonce         = wp_create_nonce( 'smart-swatches-notice' );
			printf(
				'<div class="smart-swatches-notice  is-dismissible" data-nonce="%7$s"><img src="%1$s" />
			<div class="smart-swatches-notice-text">
				<div class="smart-swatches-notice-content">
				Use the <a href="%2$s" class="thickbox open-plugin-details-modal"><strong>Smart Swatches</strong></a> plugin with <a href="%8$s" class="thickbox open-plugin-details-modal"> <strong> Reno Product Gallery</strong></a> to transform dropdowns into <strong> Color, Button, Radio, and Image Swatches</strong>—A Perfect match to <strong> Boost Sales! 🚀 </strong>
				</div>
				<div class="smart-swatches-notice-action">
					<a href="%3$s" rel="noopener" class="smart-swatches-activate-btn">%5$s</a>
				</div>
			</div>
			<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>', esc_url( $icon ), esc_url( $popup_url ), esc_url( $install_url ), esc_html( $text ), esc_html( $button_text ), $arrow, $nonce, esc_url( $popup_woo_url ) ); // phpcs:ignore
		}
	}
}

new Woo_Gallery_Slider_Admin_Notices();
