<?php
/**
 * Admin manager — registers the settings page and handles the Settings API.
 *
 * @package Thaaniyam\LaunchPopup\Admin
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace Thaaniyam\LaunchPopup\Admin;

use Thaaniyam\LaunchPopup\Settings\Manager as Settings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles everything related to the WordPress admin area.
 *
 * @since 1.0.0
 */
class Manager {

	/**
	 * Settings manager instance.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * WordPress settings section ID.
	 *
	 * @var string
	 */
	private const SECTION_ID = 'tlp_main_section';

	/**
	 * WordPress settings page slug.
	 *
	 * @var string
	 */
	private const PAGE_SLUG = 'tlp-settings';

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings manager.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register all admin hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_tlp_upload_image', array( $this, 'handle_image_upload' ) );
		add_action( 'wp_ajax_tlp_get_coupon_expiry', array( $this, 'ajax_get_coupon_expiry' ) );
		add_action( 'wp_ajax_tlp_update_coupon_expiry', array( $this, 'ajax_update_coupon_expiry' ) );
	}

	/**
	 * Add custom menu item under WooCommerce (or Settings) in Admin sidebar.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		add_menu_page(
			__( 'Launch Popup Settings', 'thaaniyam-launch-popup' ),
			__( 'Launch Popup', 'thaaniyam-launch-popup' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' ),
			'dashicons-megaphone',
			58 // Position below WooCommerce.
		);
	}

	/**
	 * Enqueue admin CSS and JS.
	 *
	 * @param string $hook Page hook identifier.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		// Only load assets on our settings page.
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'tlp-admin-style',
			TLP_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			TLP_VERSION
		);

		wp_enqueue_script(
			'tlp-admin-script',
			TLP_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			TLP_VERSION,
			true
		);

		wp_localize_script(
			'tlp-admin-script',
			'tlpAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'tlp_admin_nonce' ),
			)
		);
	}

	/**
	 * Register settings, sections and fields via WordPress Settings API.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			TLP_OPTION_KEY,
			TLP_OPTION_KEY,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		add_settings_section(
			self::SECTION_ID,
			__( 'Configure Popup Settings', 'thaaniyam-launch-popup' ),
			array( $this, 'render_section_info' ),
			self::PAGE_SLUG
		);

		$fields = Settings::get_fields();
		foreach ( $fields as $key => $field ) {
			add_settings_field(
				'tlp_field_' . $key,
				$field['label'],
				array( $this, 'render_field' ),
				self::PAGE_SLUG,
				self::SECTION_ID,
				array(
					'key'   => $key,
					'field' => $field,
				)
			);
		}
	}

	/**
	 * Section description helper callback.
	 *
	 * @return void
	 */
	public function render_section_info(): void {
		echo '<p>' . esc_html__( 'Use the form below to configure the behavior, design, and target options for your launch popup.', 'thaaniyam-launch-popup' ) . '</p>';
	}

	/**
	 * Render settings page wrapper.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="tlp-admin-wrap wrap">
			<div class="tlp-admin-header">
				<div class="tlp-admin-header__inner">
					<span class="tlp-admin-header__icon">🚀</span>
					<div>
						<h1 class="tlp-admin-header__title"><?php esc_html_e( 'Thaaniyam Launch Popup', 'thaaniyam-launch-popup' ); ?></h1>
						<p class="tlp-admin-header__subtitle"><?php esc_html_e( 'Premium Launch Offer popup management for WooCommerce', 'thaaniyam-launch-popup' ); ?></p>
					</div>
				</div>
			</div>

			<form method="post" action="options.php">
				<?php
				settings_fields( TLP_OPTION_KEY );
				?>
				<div class="tlp-admin-body">
					<!-- Main Settings Form Panel -->
					<div class="tlp-card tlp-fields">
						<h2 class="tlp-card__title"><?php esc_html_e( 'General Settings', 'thaaniyam-launch-popup' ); ?></h2>
						<?php do_settings_sections( self::PAGE_SLUG ); ?>
					</div>

					<!-- Sidebar Action Box -->
					<div class="tlp-card tlp-sidebar">
						<h2 class="tlp-card__title"><?php esc_html_e( 'Actions', 'thaaniyam-launch-popup' ); ?></h2>
						<p class="tlp-sidebar__text">
							<?php esc_html_e( 'Save changes for settings to take effect on the storefront.', 'thaaniyam-launch-popup' ); ?>
						</p>
						<div class="submit"><?php submit_button( __( 'Save Options', 'thaaniyam-launch-popup' ), 'primary large', 'submit', false ); ?></div>
					</div>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Sanitize and validate all settings input fields.
	 *
	 * @param array<string, mixed> $input Raw options input array.
	 * @return array<string, mixed> Sanitized options array.
	 */
	public function sanitize_settings( array $input ): array {
		$clean  = array();
		$fields = Settings::get_fields();

		foreach ( $fields as $key => $field ) {
			$value = $input[ $key ] ?? '';

			switch ( $field['type'] ) {
				case 'checkbox':
					$clean[ $key ] = isset( $input[ $key ] ) ? '1' : '0';
					break;

				case 'url':
				case 'image':
					$clean[ $key ] = esc_url_raw( (string) $value );
					break;

				case 'number':
					$num           = (int) $value;
					$min           = isset( $field['min'] ) ? (int) $field['min'] : 0;
					$max           = isset( $field['max'] ) ? (int) $field['max'] : PHP_INT_MAX;
					$clean[ $key ] = max( $min, min( $max, $num ) );
					break;

				case 'textarea':
					$clean[ $key ] = sanitize_textarea_field( (string) $value );
					break;

				case 'page_multiselect':
					if ( is_array( $value ) ) {
						$clean[ $key ] = array_map( 'absint', $value );
					} else {
						$clean[ $key ] = array();
					}
					break;

				case 'select':
				case 'text':
				default:
					$clean[ $key ] = sanitize_text_field( (string) $value );
					break;
			}
		}

		return $clean;
	}

	/**
	 * Render an individual settings field.
	 *
	 * @param array<string, mixed> $args Field arguments passed from add_settings_field().
	 * @return void
	 */
	public function render_field( array $args ): void {
		$key   = $args['key'];
		$field = $args['field'];
		$value = $this->settings->get( $key, $field['default'] ?? '' );
		$name  = TLP_OPTION_KEY . '[' . $key . ']';
		$id    = 'tlp_field_' . $key;

		switch ( $field['type'] ) {
			case 'checkbox':
				printf(
					'<label class="tlp-toggle"><input type="checkbox" id="%s" name="%s" value="1" %s><span class="tlp-toggle__slider"></span></label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( '1', $value, false )
				);
				break;

			case 'select':
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" class="tlp-input">';
				if ( ! empty( $field['options'] ) ) {
					foreach ( $field['options'] as $option_val => $option_label ) {
						printf(
							'<option value="%s" %s>%s</option>',
							esc_attr( $option_val ),
							selected( $value, $option_val, false ),
							esc_html( $option_label )
						);
					}
				}
				echo '</select>';
				break;

			case 'textarea':
				printf(
					'<textarea id="%s" name="%s" rows="4" class="tlp-input tlp-textarea" placeholder="%s">%s</textarea>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $field['placeholder'] ?? '' ),
					esc_textarea( (string) $value )
				);
				break;

			case 'page_multiselect':
				$all_pages = Settings::get_all_pages();
				$selected_ids = is_array( $value ) ? array_map( 'intval', $value ) : array();
				?>
				<div class="tlp-multiselect-container" id="<?php echo esc_attr( $id . '_container' ); ?>">
					<!-- Display Button -->
					<div class="tlp-multiselect-trigger" id="<?php echo esc_attr( $id . '_trigger' ); ?>">
						<span class="tlp-multiselect-placeholder"><?php 
							if ( empty( $selected_ids ) ) {
								esc_html_e( 'Select pages...', 'thaaniyam-launch-popup' );
							} else {
								printf( esc_html( _n( '%d page selected', '%d pages selected', count( $selected_ids ), 'thaaniyam-launch-popup' ) ), count( $selected_ids ) );
							}
						?></span>
						<span class="tlp-multiselect-arrow">▼</span>
					</div>

					<!-- Dropdown panel -->
					<div class="tlp-multiselect-dropdown" id="<?php echo esc_attr( $id . '_dropdown' ); ?>" style="display: none;">
						<div class="tlp-multiselect-search-wrap">
							<input type="text" class="tlp-multiselect-search" placeholder="<?php esc_attr_e( 'Search pages...', 'thaaniyam-launch-popup' ); ?>">
						</div>
						<div class="tlp-multiselect-options">
							<?php if ( ! empty( $all_pages ) ) : ?>
								<?php foreach ( $all_pages as $page_id => $page_title ) : ?>
									<?php $checked = in_array( $page_id, $selected_ids, true ); ?>
									<label class="tlp-multiselect-option" data-title="<?php echo esc_attr( strtolower( $page_title ) ); ?>">
										<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( (string) $page_id ); ?>" <?php checked( $checked ); ?>>
										<span class="tlp-multiselect-checkbox-label"><?php echo esc_html( $page_title ); ?></span>
									</label>
								<?php endforeach; ?>
							<?php else : ?>
								<div class="tlp-multiselect-no-results"><?php esc_html_e( 'No pages found', 'thaaniyam-launch-popup' ); ?></div>
							<?php endif; ?>
						</div>
					</div>
				</div>
				<?php
				break;

			case 'number':
				printf(
					'<input type="number" id="%s" name="%s" value="%s" min="%s" max="%s" class="tlp-input tlp-input--short" placeholder="%s">',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					esc_attr( (string) ( $field['min'] ?? 0 ) ),
					esc_attr( (string) ( $field['max'] ?? 60 ) ),
					esc_attr( $field['placeholder'] ?? '' )
				);
				echo '<span class="tlp-input-suffix">' . esc_html__( 'seconds', 'thaaniyam-launch-popup' ) . '</span>';
				break;

			case 'url':
				printf(
					'<input type="url" id="%s" name="%s" value="%s" class="tlp-input" placeholder="%s">',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					esc_attr( $field['placeholder'] ?? '' )
				);
				break;

			case 'image':
				$image_url = (string) $value;
				?>
				<div class="tlp-image-upload" id="<?php echo esc_attr( $id . '_wrap' ); ?>">
					<input type="hidden" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $image_url ); ?>">
					<div class="tlp-image-preview" id="<?php echo esc_attr( $id . '_preview' ); ?>">
						<?php if ( $image_url ) : ?>
							<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php esc_attr_e( 'Popup image', 'thaaniyam-launch-popup' ); ?>">
						<?php else : ?>
							<span class="tlp-image-placeholder"><?php esc_html_e( 'No image selected', 'thaaniyam-launch-popup' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="tlp-image-actions">
						<button type="button" class="button tlp-media-upload-btn" data-target="<?php echo esc_attr( $id ); ?>" data-preview="<?php echo esc_attr( $id . '_preview' ); ?>">
							<?php esc_html_e( '📁 Select Image', 'thaaniyam-launch-popup' ); ?>
						</button>
						<?php if ( $image_url ) : ?>
							<button type="button" class="button tlp-media-remove-btn" data-target="<?php echo esc_attr( $id ); ?>" data-preview="<?php echo esc_attr( $id . '_preview' ); ?>">
								<?php esc_html_e( '✕ Remove', 'thaaniyam-launch-popup' ); ?>
							</button>
						<?php endif; ?>
					</div>
				</div>
				<?php
				break;

			default: // text.
				printf(
					'<input type="text" id="%s" name="%s" value="%s" class="tlp-input" placeholder="%s">',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					esc_attr( $field['placeholder'] ?? '' )
				);
				break;
		}

		if ( 'coupon_code' === $key ) {
			$this->render_coupon_expiry_section( (string) $value );
		}

		if ( ! empty( $field['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $field['description'] ) );
		}
	}

	/**
	 * Handle image upload via WordPress media AJAX (not used directly — media library handles it).
	 *
	 * @return void
	 */
	public function handle_image_upload(): void {
		// Media library handles uploads natively; this hook is reserved for future use.
		wp_die();
	}

	/**
	 * Render the coupon expiry details and edit form below the coupon code select/text field.
	 *
	 * @param string $coupon_code Selected coupon code.
	 * @return void
	 */
	private function render_coupon_expiry_section( string $coupon_code ): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$expiry_info = $this->get_coupon_expiry_data( $coupon_code );
		$display_date = $expiry_info['formatted'];
		$raw_value = $expiry_info['raw'];

		?>
		<div class="tlp-coupon-expiry-wrap" id="tlp-coupon-expiry-wrap" data-coupon="<?php echo esc_attr( $coupon_code ); ?>" style="<?php echo empty( $coupon_code ) ? 'display: none;' : ''; ?>">
			<div class="tlp-coupon-expiry-display">
				<span class="tlp-coupon-expiry-label"><?php esc_html_e( '📅 Coupon Expiry:', 'thaaniyam-launch-popup' ); ?></span>
				<span class="tlp-coupon-expiry-value" id="tlp-coupon-expiry-value">
					<?php echo esc_html( $display_date ? $display_date : __( 'No expiry date set', 'thaaniyam-launch-popup' ) ); ?>
				</span>
				<?php if ( ! empty( $coupon_code ) ) : ?>
					<button type="button" class="button button-link tlp-edit-expiry-btn" id="tlp-edit-expiry-btn">
						<?php esc_html_e( '✏️ Change', 'thaaniyam-launch-popup' ); ?>
					</button>
				<?php endif; ?>
			</div>

			<div class="tlp-coupon-expiry-edit" id="tlp-coupon-expiry-edit" style="display: none;">
				<input type="datetime-local" id="tlp-coupon-expiry-input" class="tlp-input tlp-input--datetime" value="<?php echo esc_attr( $raw_value ); ?>">
				<button type="button" class="button button-secondary tlp-save-expiry-btn" id="tlp-save-expiry-btn">
					<?php esc_html_e( 'Save Expiry', 'thaaniyam-launch-popup' ); ?>
				</button>
				<button type="button" class="button button-link tlp-cancel-expiry-btn" id="tlp-cancel-expiry-btn">
					<?php esc_html_e( 'Cancel', 'thaaniyam-launch-popup' ); ?>
				</button>
				<span class="spinner" id="tlp-expiry-spinner"></span>
				<span class="tlp-expiry-success-msg" id="tlp-expiry-success-msg" style="display: none;">✓ Saved</span>
			</div>
		</div>
		<?php
	}

	/**
	 * Retrieve expiry data for a given WooCommerce coupon.
	 *
	 * @param string $coupon_code Coupon code.
	 * @return array{formatted: string, raw: string}
	 */
	private function get_coupon_expiry_data( string $coupon_code ): array {
		$data = array(
			'formatted' => '',
			'raw'       => '',
		);

		if ( empty( $coupon_code ) || ! class_exists( 'WC_Coupon' ) ) {
			return $data;
		}

		$coupon = new \WC_Coupon( $coupon_code );
		if ( $coupon->get_id() > 0 ) {
			$expiry_date = $coupon->get_date_expires();
			if ( $expiry_date ) {
				$date_format = get_option( 'date_format' );
				$time_format = get_option( 'time_format' );
				$data['formatted'] = $expiry_date->date_i18n( $date_format . ' ' . $time_format );
				$data['raw'] = $expiry_date->date( 'Y-m-d\TH:i' );
			}
		}

		return $data;
	}

	/**
	 * AJAX handler to retrieve coupon expiry date.
	 *
	 * @return void
	 */
	public function ajax_get_coupon_expiry(): void {
		check_ajax_referer( 'tlp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'thaaniyam-launch-popup' ) ) );
		}

		$coupon_code = isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : '';
		$expiry_data = $this->get_coupon_expiry_data( $coupon_code );

		wp_send_json_success( $expiry_data );
	}

	/**
	 * AJAX handler to update coupon expiry date.
	 *
	 * @return void
	 */
	public function ajax_update_coupon_expiry(): void {
		check_ajax_referer( 'tlp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'thaaniyam-launch-popup' ) ) );
		}

		$coupon_code = isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : '';
		$expiry_date = isset( $_POST['expiry_date'] ) ? sanitize_text_field( wp_unslash( $_POST['expiry_date'] ) ) : '';

		if ( empty( $coupon_code ) || ! class_exists( 'WC_Coupon' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid coupon or WooCommerce is not active.', 'thaaniyam-launch-popup' ) ) );
		}

		$coupon = new \WC_Coupon( $coupon_code );
		if ( $coupon->get_id() <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Coupon not found.', 'thaaniyam-launch-popup' ) ) );
		}

		if ( empty( $expiry_date ) ) {
			$coupon->set_date_expires( null );
		} else {
			try {
				$datetime = new \DateTime( $expiry_date );
				$coupon->set_date_expires( $datetime->getTimestamp() );
			} catch ( \Exception $e ) {
				wp_send_json_error( array( 'message' => __( 'Invalid date format.', 'thaaniyam-launch-popup' ) ) );
			}
		}

		$coupon->save();

		$new_expiry = $this->get_coupon_expiry_data( $coupon_code );
		wp_send_json_success( $new_expiry );
	}
}
