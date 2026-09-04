<?php
/**
 * Frontend Manager class.
 *
 * @package Thaaniyam\ReferFriend\Frontend
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace Thaaniyam\ReferFriend\Frontend;

use Thaaniyam\ReferFriend\Settings\Manager as Settings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles product page widgets, WooCommerce My Account tabs, and scripts.
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
	 * Constructor.
	 *
	 * @param Settings $settings Settings manager.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register frontend hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( '1' !== $this->settings->get( 'enabled', '1' ) ) {
			return;
		}

		// Enqueue scripts and styles.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// WooCommerce My Account integrations.
		add_action( 'init', array( $this, 'add_myaccount_endpoint' ) );
		add_filter( 'query_vars', array( $this, 'add_myaccount_query_vars' ), 0 );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_myaccount_menu_item' ) );
		add_action( 'woocommerce_account_refer-a-friend_endpoint', array( $this, 'render_myaccount_content' ) );

		// Single product page integration (supports default WooCommerce templates, theme builders, and custom hooks).
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_product_share_box' ), 25 );
		add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'render_product_share_box' ), 30 );
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'render_product_share_box' ), 15 );
		add_action( 'woocommerce_share', array( $this, 'render_product_share_box' ), 15 );
		add_action( 'woocommerce_product_meta_end', array( $this, 'render_product_share_box' ), 15 );
		add_action( 'woocommerce_after_single_product_summary', array( $this, 'render_product_share_box' ), 5 );

		// Modal structure in footer.
		add_action( 'wp_footer', array( $this, 'render_email_invite_modal' ) );

		// AJAX handler.
		add_action( 'wp_ajax_traf_send_email_invite', array( $this, 'handle_email_invite_ajax' ) );
		add_action( 'wp_ajax_traf_log_share_action', array( $this, 'handle_log_share_action_ajax' ) );
	}

	/**
	 * Enqueue Google Fonts, CSS styles, and JS sharing scripts.
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets(): void {
		// Enqueue Montserrat.
		wp_enqueue_style(
			'traf-google-fonts',
			'https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,400&display=swap',
			array(),
			null
		);

		// Enqueue Plugin styles.
		wp_enqueue_style(
			'traf-frontend-style',
			TRAF_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			TRAF_VERSION
		);

		// Enqueue FontAwesome for clean vector sharing icons.
		wp_enqueue_style(
			'font-awesome',
			'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
			array(),
			'6.4.0'
		);

		// Enqueue JS scripting.
		wp_enqueue_script(
			'traf-frontend-script',
			TRAF_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			TRAF_VERSION,
			true
		);

		// Localize AJAX config variables.
		wp_localize_script(
			'traf-frontend-script',
			'trafConfig',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'traf_frontend_nonce' ),
			)
		);
	}

	/**
	 * Register the rewrite endpoint for "My Account > Refer a Friend".
	 *
	 * @return void
	 */
	public function add_myaccount_endpoint(): void {
		add_rewrite_endpoint( 'refer-a-friend', EP_PAGES );
	}

	/**
	 * Add the custom endpoint as a valid query variable.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function add_myaccount_query_vars( array $vars ): array {
		$vars[] = 'refer-a-friend';
		return $vars;
	}

	/**
	 * Append "Refer a Friend" menu item to WooCommerce My Account menu list.
	 *
	 * @param array $items Existing items.
	 * @return array
	 */
	public function add_myaccount_menu_item( array $items ): array {
		// Place before Logout or just append.
		$logout = isset( $items['customer-logout'] ) ? $items['customer-logout'] : '';
		if ( $logout ) {
			unset( $items['customer-logout'] );
		}

		$items['refer-a-friend'] = __( 'Refer a Friend', 'thaaniyam-refer-a-friend' );

		if ( $logout ) {
			$items['customer-logout'] = $logout;
		}

		return $items;
	}

	/**
	 * Render the content of the My Account > Refer a Friend endpoint.
	 *
	 * @return void
	 */
	public function render_myaccount_content(): void {
		$current_user = wp_get_current_user();
		if ( ! $current_user->ID ) {
			return;
		}

		// Retrieve setting params.
		$param_name = $this->settings->get( 'param_name', 'ref' );
		$ref_link   = add_query_arg( $param_name, $current_user->user_login, home_url( '/' ) );

		// Query current user's referrals.
		$args = array(
			'post_type'      => 'thaaniyam_referral',
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'meta_query'     => array(
				array(
					'key'     => '_referrer_id',
					'value'   => $current_user->ID,
					'compare' => '=',
				),
			),
		);

		$referrals_query = new \WP_Query( $args );

		// Calculate statistics.
		$total_referred = $referrals_query->post_count;
		$successful     = 0;
		$pending        = 0;
		$rewards        = array();

		if ( $referrals_query->have_posts() ) {
			foreach ( $referrals_query->posts as $post ) {
				$coupon = get_post_meta( $post->ID, '_reward_coupon_code', true );
				if ( $coupon ) {
					$successful++;
					$rewards[] = $coupon;
				} else {
					$pending++;
				}
			}
		}

		// Get referee reward details for sharing info.
		$referee_reward_type  = $this->settings->get( 'referee_reward_type', 'none' );
		$referee_reward_value = (float) $this->settings->get( 'referee_reward_value', 0 );
		$referee_reward_text  = '';
		if ( 'percent' === $referee_reward_type ) {
			$referee_reward_text = $referee_reward_value . '% ' . __( 'discount', 'thaaniyam-refer-a-friend' );
		} elseif ( 'fixed' === $referee_reward_type ) {
			$referee_reward_text = html_entity_decode( strip_tags( wc_price( $referee_reward_value ) ) ) . ' ' . __( 'discount', 'thaaniyam-refer-a-friend' );
		}

		// Include template.
		$template_path = TRAF_PLUGIN_DIR . 'templates/myaccount-referrals.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
	}

	/**
	 * Render the Referral box on the single product page.
	 *
	 * @return void
	 */
	public function render_product_share_box(): void {
		// Prevent duplicate output on the same page.
		if ( did_action( 'traf_product_share_box_rendered' ) ) {
			return;
		}

		global $product;
		if ( ! $product ) {
			return;
		}

		do_action( 'traf_product_share_box_rendered' );

		$is_logged_in = is_user_logged_in();
		$current_user = wp_get_current_user();
		$param_name   = $this->settings->get( 'param_name', 'ref' );
		
		$product_link = get_permalink( $product->get_id() );
		$ref_link     = '';

		if ( $is_logged_in ) {
			$ref_link = add_query_arg( $param_name, $current_user->user_login, $product_link );
		} else {
			$ref_link = add_query_arg( 'redirect_to', urlencode( $product_link ), wc_get_page_permalink( 'myaccount' ) );
		}

		// Retrieve Reward values for description context.
		$referee_type  = $this->settings->get( 'referee_reward_type', 'none' );
		$referee_value = (float) $this->settings->get( 'referee_reward_value', 0 );
		$reward_text   = '';

		if ( 'none' !== $referee_type && $referee_value > 0 ) {
			if ( 'percent' === $referee_type ) {
				$reward_text = $referee_value . '%';
			} elseif ( 'fixed' === $referee_type ) {
				$reward_text = html_entity_decode( strip_tags( wc_price( $referee_value ) ) );
			}
		}

		$sharing_coupon = '';
		if ( $is_logged_in ) {
			$rewards_mgr    = new \Thaaniyam\ReferFriend\Rewards\Manager( $this->settings );
			$sharing_coupon = $rewards_mgr->get_or_create_sharing_coupon( $current_user->ID );
		}

		// Load template.
		$template_path = TRAF_PLUGIN_DIR . 'templates/product-share-box.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
	}

	/**
	 * Output the HTML modal structure in the wp_footer.
	 *
	 * @return void
	 */
	public function render_email_invite_modal(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$current_user = wp_get_current_user();
		?>
		<div id="traf-email-modal" class="traf-modal-overlay" style="display: none;">
			<div class="traf-modal-card">
				<button class="traf-modal-close" id="traf-modal-close-btn">&times;</button>
				<h3><?php esc_html_e( 'Share via Email', 'thaaniyam-refer-a-friend' ); ?></h3>
				<p><?php esc_html_e( 'Send a special invitation link directly to your friend\'s inbox.', 'thaaniyam-refer-a-friend' ); ?></p>
				
				<form id="traf-email-invite-form">
					<input type="hidden" name="product_id" id="traf-modal-product-id" value="0">
					
					<div class="traf-modal-field">
						<label for="traf-friend-email"><?php esc_html_e( "Friend's Email Address", 'thaaniyam-refer-a-friend' ); ?></label>
						<input type="email" id="traf-friend-email" required placeholder="friend@example.com">
					</div>
					
					<div class="traf-modal-field">
						<label for="traf-referrer-name"><?php esc_html_e( 'Your Name', 'thaaniyam-refer-a-friend' ); ?></label>
						<input type="text" id="traf-referrer-name" required value="<?php echo esc_attr( $current_user->display_name ); ?>">
					</div>
					
					<div class="traf-modal-field">
						<label for="traf-custom-message"><?php esc_html_e( 'Personal Message (Optional)', 'thaaniyam-refer-a-friend' ); ?></label>
						<textarea id="traf-custom-message" rows="3" placeholder="<?php esc_attr_e( 'Check this out! You will get a discount.', 'thaaniyam-refer-a-friend' ); ?>"></textarea>
					</div>
					
					<button type="submit" class="traf-modal-submit-btn" id="traf-modal-submit-btn">
						<span class="traf-btn-text"><?php esc_html_e( 'Send Invitation', 'thaaniyam-refer-a-friend' ); ?></span>
						<span class="traf-btn-spinner" style="display: none;"><i class="fas fa-spinner fa-spin"></i></span>
					</button>
					
					<div class="traf-modal-alert" id="traf-modal-alert" style="display: none;"></div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle AJAX request for sending invite emails.
	 *
	 * @return void
	 */
	public function handle_email_invite_ajax(): void {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'traf_frontend_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'thaaniyam-refer-a-friend' ) ) );
		}

		$friend_email  = sanitize_email( $_POST['friend_email'] ?? '' );
		$referrer_name = sanitize_text_field( $_POST['referrer_name'] ?? '' );
		$product_id    = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
		$custom_msg    = sanitize_textarea_field( $_POST['custom_message'] ?? '' );

		if ( ! is_email( $friend_email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'thaaniyam-refer-a-friend' ) ) );
		}

		if ( empty( $referrer_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter your name.', 'thaaniyam-refer-a-friend' ) ) );
		}

		$current_user = wp_get_current_user();
		if ( ! $current_user->ID ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to send invitations.', 'thaaniyam-refer-a-friend' ) ) );
		}

		// Resolve sharing link.
		$param_name = $this->settings->get( 'param_name', 'ref' );
		$target_url = home_url( '/' );
		if ( $product_id > 0 ) {
			$target_url = get_permalink( $product_id );
		}
		$ref_link = add_query_arg( $param_name, $current_user->user_login, $target_url );

		// Instantiate rewards manager to generate/retrieve the permanent friend sharing coupon.
		$rewards_mgr    = new \Thaaniyam\ReferFriend\Rewards\Manager( $this->settings );
		$sharing_coupon = $rewards_mgr->get_or_create_sharing_coupon( $current_user->ID );

		// Instantiate email manager and send.
		$emails = new \Thaaniyam\ReferFriend\Emails\Manager( $this->settings );
		$sent   = $emails->send_invite_email( $referrer_name, $friend_email, $ref_link, $product_id, $custom_msg, $sharing_coupon );

		if ( $sent ) {
			// Log the email share action as a draft/pending referral.
			$product_name = '';
			if ( $product_id > 0 ) {
				$product = wc_get_product( $product_id );
				if ( $product ) {
					$product_name = $product->get_name();
				}
			}
			$title = sprintf(
				'Email Invite to %s by %s (%s)',
				$friend_email,
				$current_user->display_name,
				$current_user->user_email
			);
			if ( $product_name ) {
				$title .= sprintf( ' for %s', $product_name );
			}

			$post_id = wp_insert_post( array(
				'post_title'  => $title,
				'post_status' => 'draft',
				'post_type'   => 'thaaniyam_referral',
			) );

			if ( $post_id ) {
				update_post_meta( $post_id, '_referrer_id', $current_user->ID );
				update_post_meta( $post_id, '_share_type', 'email' );
				update_post_meta( $post_id, '_referee_email', $friend_email );
				if ( $product_id > 0 ) {
					update_post_meta( $post_id, '_product_id', $product_id );
				}
			}

			wp_send_json_success( array( 'message' => __( 'Invitation sent successfully!', 'thaaniyam-refer-a-friend' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to send email. Please check your mail configurations.', 'thaaniyam-refer-a-friend' ) ) );
		}
	}

	/**
	 * Log sharing action (WhatsApp, Instagram, Copy Link) via AJAX.
	 *
	 * @return void
	 */
	public function handle_log_share_action_ajax(): void {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'traf_frontend_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'thaaniyam-refer-a-friend' ) ) );
		}

		$current_user = wp_get_current_user();
		if ( ! $current_user->ID ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to log sharing.', 'thaaniyam-refer-a-friend' ) ) );
		}

		$share_type = sanitize_text_field( $_POST['share_type'] ?? '' );
		$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;

		$allowed_types = array( 'whatsapp', 'instagram', 'copy_link' );
		if ( ! in_array( $share_type, $allowed_types, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid share type.', 'thaaniyam-refer-a-friend' ) ) );
		}

		$product_name = '';
		if ( $product_id > 0 ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$product_name = $product->get_name();
			}
		}

		$title = sprintf(
			'%s Share by %s (%s)',
			ucfirst( $share_type ),
			$current_user->display_name,
			$current_user->user_email
		);
		if ( $product_name ) {
			$title .= sprintf( ' for %s', $product_name );
		}

		// Insert draft log entry in thaaniyam_referral.
		$post_id = wp_insert_post( array(
			'post_title'  => $title,
			'post_status' => 'draft',
			'post_type'   => 'thaaniyam_referral',
		) );

		if ( $post_id ) {
			update_post_meta( $post_id, '_referrer_id', $current_user->ID );
			update_post_meta( $post_id, '_share_type', $share_type );
			if ( $product_id > 0 ) {
				update_post_meta( $post_id, '_product_id', $product_id );
			}
			wp_send_json_success();
		}

		wp_send_json_error( array( 'message' => __( 'Failed to log sharing action.', 'thaaniyam-refer-a-friend' ) ) );
	}
}
