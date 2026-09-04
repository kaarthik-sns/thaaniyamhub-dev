<?php
/**
 * Rebrand announcement notice.
 *
 * Announces the WooGallery to Reno Product Gallery rename on the plugin's own
 * screens. Dismissing it hides the notice for the whole site, matching how the
 * offer banner behaves, so the dismissed state is kept in a site option.
 *
 * @since      3.2.0
 * @link       http://shapedplugin.com
 * @package    Woo_Gallery_Slider/Admin
 * @author     ShapedPlugin <support@shapedplugin.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Woo_Gallery_Slider_Rebrand_Notice' ) ) {
	/**
	 * The class for the rebrand announcement notice.
	 */
	class Woo_Gallery_Slider_Rebrand_Notice {

		/**
		 * The single instance of the class.
		 *
		 * @var self
		 * @since 3.2.0
		 */
		private static $instance = null;

		/**
		 * Site option key holding the dismissed state.
		 *
		 * @var string
		 */
		const DISMISSED_OPTION_KEY = 'sp_wcgs_rebrand_notice_dismissed';

		/**
		 * Blog post explaining the rebrand.
		 *
		 * @var string
		 */
		const ANNOUNCEMENT_URL = 'https://renoproductgallery.com/woogallery-is-now-reno-product-gallery/';

		/**
		 * Class constructor.
		 */
		private function __construct() {
			add_action( 'admin_notices', array( $this, 'render_rebrand_notice' ) );
			add_action( 'wp_ajax_wgs_dismiss_rebrand_notice', array( $this, 'dismiss_rebrand_notice' ) );
		}

		/**
		 * Retrieves the singleton instance of the class.
		 *
		 * @return self The singleton instance of the class.
		 */
		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Capability required to see and dismiss the notice.
		 *
		 * Dismissing writes a site-wide option, so the same permission that
		 * guards the plugin UI guards the notice.
		 *
		 * @return string
		 */
		private function get_capability() {
			return apply_filters( 'wcgs_ui_permission', 'manage_options' );
		}

		/**
		 * Whether the current screen belongs to this plugin.
		 *
		 * Mirrors the screen set used to enqueue the admin assets, so the
		 * notice only shows where the plugin's own styles are loaded.
		 *
		 * @return bool
		 */
		private function is_plugin_screen() {
			if ( ! function_exists( 'get_current_screen' ) ) {
				return false;
			}

			$current_screen = get_current_screen();

			if ( ! is_object( $current_screen ) ) {
				return false;
			}

			// The submenu screen base is prefixed with the sanitized parent menu title, so derive it instead of hardcoding it.
			return 'wcgs_layouts' === $current_screen->post_type
				|| 'toplevel_page_wpgs-settings' === $current_screen->base
				|| get_plugin_page_hookname( 'assign_layout', 'wpgs-settings' ) === $current_screen->base
				|| get_plugin_page_hookname( 'wpgs-help', 'wpgs-settings' ) === $current_screen->base;
		}

		/**
		 * Renders the rebrand notice on the page.
		 *
		 * @return void
		 */
		public function render_rebrand_notice() {
			if ( ! $this->is_plugin_screen() || ! current_user_can( $this->get_capability() ) ) {
				return;
			}

			if ( get_option( self::DISMISSED_OPTION_KEY ) ) {
				return;
			}

			$nonce = wp_create_nonce( 'wgs_rebrand_notice_dismiss' );
			?>
			<div id="wgs-rebrand-notice" class="wgs-rebrand-notice">
				<div class="wgs-rebrand-notice-content">
					<img class="wgs-rebrand-notice-icon" src="<?php echo esc_url( WOO_GALLERY_SLIDER_URL . 'admin/img/rebrand-notice-icon.gif' ); ?>" alt="" width="18" height="18">
					<p class="wgs-rebrand-notice-text">
						<?php
						printf(
							/* translators: 1: old plugin name, 2: new plugin name. */
							esc_html__( '%1$s is now %2$s — Everything else remains the same. Why we rebranded', 'gallery-slider-for-woocommerce' ),
							'<strong>' . esc_html__( 'WooGallery', 'gallery-slider-for-woocommerce' ) . '</strong>',
							'<strong>' . esc_html__( 'Reno Product Gallery', 'gallery-slider-for-woocommerce' ) . '</strong>'
						);
						?>
					</p>
					<a class="wgs-rebrand-notice-link" href="<?php echo esc_url( self::ANNOUNCEMENT_URL ); ?>" target="_blank" rel="noopener noreferrer">
						<span class="screen-reader-text"><?php esc_html_e( 'Read more about the rebranding', 'gallery-slider-for-woocommerce' ); ?></span>
						<svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">
							<path d="M5.25 2.625H2.625A1.167 1.167 0 0 0 1.458 3.79v7.583a1.167 1.167 0 0 0 1.167 1.167h7.583a1.167 1.167 0 0 0 1.167-1.167V8.75" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M8.75 1.458h3.792V5.25M6.125 7.875l6.417-6.417" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</a>
				</div>

				<button type="button" class="wgs-rebrand-notice-dismiss" data-nonce="<?php echo esc_attr( $nonce ); ?>">
					<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice', 'gallery-slider-for-woocommerce' ); ?></span>
					<svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">
						<path d="M10.5 3.5 3.5 10.5M3.5 3.5l7 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
					</svg>
				</button>
			</div>

			<script type="text/javascript">
				(function($){
					$(document).on('click', '#wgs-rebrand-notice .wgs-rebrand-notice-dismiss', function(e){
						e.preventDefault();
						$.post(ajaxurl, {
							action: 'wgs_dismiss_rebrand_notice',
							nonce: $(this).data('nonce')
						});
						$('#wgs-rebrand-notice').fadeOut(300);
					});
				})(jQuery);
			</script>
			<?php
		}

		/**
		 * Handles the AJAX request to dismiss the rebrand notice.
		 *
		 * @return void
		 */
		public function dismiss_rebrand_notice() {
			check_ajax_referer( 'wgs_rebrand_notice_dismiss', 'nonce' );

			if ( ! current_user_can( $this->get_capability() ) ) {
				wp_send_json_error( array( 'error' => esc_html__( 'Authorization failed!', 'gallery-slider-for-woocommerce' ) ), 401 );
			}

			// Not autoloaded: only ever read on this plugin's admin screens.
			update_option( self::DISMISSED_OPTION_KEY, 1, false );

			wp_send_json_success();
		}
	}

	Woo_Gallery_Slider_Rebrand_Notice::instance();
}
