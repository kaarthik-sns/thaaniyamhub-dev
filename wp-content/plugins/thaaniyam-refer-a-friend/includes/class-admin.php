<?php
/**
 * Admin Manager class.
 *
 * @package Thaaniyam\ReferFriend\Admin
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace Thaaniyam\ReferFriend\Admin;

use Thaaniyam\ReferFriend\Settings\Manager as Settings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles WordPress admin area components (menus, CPT logs, settings saving).
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
	 * Register admin-related hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register_referral_cpt' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Custom columns for the referral CPT list table.
		add_filter( 'manage_thaaniyam_referral_posts_columns', array( $this, 'set_custom_referral_columns' ) );
		add_action( 'manage_thaaniyam_referral_posts_custom_column', array( $this, 'render_custom_referral_columns' ), 10, 2 );

		// Handle saving settings.
		add_action( 'admin_init', array( $this, 'save_settings' ) );
	}

	/**
	 * Register Custom Post Type for Referral log.
	 *
	 * @return void
	 */
	public function register_referral_cpt(): void {
		$labels = array(
			'name'               => _x( 'Referrals', 'post type general name', 'thaaniyam-refer-a-friend' ),
			'singular_name'      => _x( 'Referral', 'post type singular name', 'thaaniyam-refer-a-friend' ),
			'menu_name'          => _x( 'Referrals Log', 'admin menu', 'thaaniyam-refer-a-friend' ),
			'name_admin_bar'     => _x( 'Referral', 'add new on admin bar', 'thaaniyam-refer-a-friend' ),
			'add_new'            => _x( 'Add New', 'referral', 'thaaniyam-refer-a-friend' ),
			'add_new_item'       => __( 'Add New Referral', 'thaaniyam-refer-a-friend' ),
			'new_item'           => __( 'New Referral', 'thaaniyam-refer-a-friend' ),
			'edit_item'          => __( 'Edit Referral', 'thaaniyam-refer-a-friend' ),
			'view_item'          => __( 'View Referral', 'thaaniyam-refer-a-friend' ),
			'all_items'          => __( 'Referrals Log', 'thaaniyam-refer-a-friend' ),
			'search_items'       => __( 'Search Referrals', 'thaaniyam-refer-a-friend' ),
			'not_found'          => __( 'No referrals found.', 'thaaniyam-refer-a-friend' ),
			'not_found_in_trash' => __( 'No referrals found in Trash.', 'thaaniyam-refer-a-friend' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => 'woocommerce',
			'query_var'          => false,
			'rewrite'            => false,
			'capability_type'    => 'post',
			'capabilities'       => array(
				'create_posts' => 'do_not_allow', // Disable manually adding referrals.
			),
			'map_meta_cap'       => true,
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => array( 'title' ),
		);

		register_post_type( 'thaaniyam_referral', $args );
	}

	/**
	 * Add Settings Submenu under WooCommerce.
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Refer a Friend Settings', 'thaaniyam-refer-a-friend' ),
			__( 'Refer a Friend', 'thaaniyam-refer-a-friend' ),
			'manage_woocommerce',
			'traf-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin styles and scripts.
	 *
	 * @param string $hook Admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		// Enqueue on settings page and CPT list edit page.
		$is_cpt_page = ( 'edit.php' === $hook && isset( $_GET['post_type'] ) && 'thaaniyam_referral' === $_GET['post_type'] );
		if ( 'woocommerce_page_traf-settings' === $hook || $is_cpt_page ) {
			wp_enqueue_style(
				'traf-admin-style',
				TRAF_PLUGIN_URL . 'assets/css/admin.css',
				array(),
				TRAF_VERSION
			);

			wp_enqueue_script(
				'traf-admin-script',
				TRAF_PLUGIN_URL . 'assets/js/admin.js',
				array( 'jquery' ),
				TRAF_VERSION,
				true
			);
		}
	}

	/**
	 * Set custom columns for referrals log screen.
	 *
	 * @param array $columns Existing columns.
	 * @return array Updated columns.
	 */
	public function set_custom_referral_columns( array $columns ): array {
		// Replace default title column.
		unset( $columns['date'] );
		
		$new_columns = array(
			'cb'            => $columns['cb'],
			'title'         => __( 'Referral ID', 'thaaniyam-refer-a-friend' ),
			'referrer'      => __( 'Referrer', 'thaaniyam-refer-a-friend' ),
			'referee'       => __( 'Referee Friend', 'thaaniyam-refer-a-friend' ),
			'order'         => __( 'Order', 'thaaniyam-refer-a-friend' ),
			'reward_coupon' => __( 'Issued Coupon', 'thaaniyam-refer-a-friend' ),
			'date'          => __( 'Date', 'thaaniyam-refer-a-friend' ),
		);

		return $new_columns;
	}

	/**
	 * Render custom column content.
	 *
	 * @param string $column  Column identifier.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_custom_referral_columns( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'referrer':
				$referrer_id = (int) get_post_meta( $post_id, '_referrer_id', true );
				$user        = get_userdata( $referrer_id );
				if ( $user ) {
					$edit_link = get_edit_user_link( $referrer_id );
					printf(
						'<a href="%s"><strong>%s</strong></a><br><small>%s</small>',
						esc_url( $edit_link ),
						esc_html( $user->display_name ),
						esc_html( $user->user_email )
					);
				} else {
					echo '<span class="na">&mdash;</span>';
				}
				break;

			case 'referee':
				$referee_email = get_post_meta( $post_id, '_referee_email', true );
				if ( $referee_email ) {
					echo esc_html( $referee_email );
				} else {
					echo '<span class="na">&mdash;</span>';
				}
				break;

			case 'order':
				$order_id = (int) get_post_meta( $post_id, '_referee_order_id', true );
				$order    = wc_get_order( $order_id );
				if ( $order ) {
					printf(
						'<a href="%s"><strong>#%s</strong></a><br><span class="status-%s">%s</span>',
						esc_url( $order->get_edit_order_url() ),
						esc_html( $order->get_order_number() ),
						esc_attr( $order->get_status() ),
						esc_html( wc_get_order_status_name( $order->get_status() ) )
					);
				} else {
					echo '<span class="na">&mdash;</span>';
				}
				break;

			case 'reward_coupon':
				$coupon_code = get_post_meta( $post_id, '_reward_coupon_code', true );
				$reward_amount = get_post_meta( $post_id, '_reward_amount', true );
				if ( $coupon_code ) {
					printf(
						'<code style="font-size:13px; font-weight:bold; background:#e8f4d9; color:#4a6809; padding:4px 8px; border-radius:4px;">%s</code><br><small>%s reward</small>',
						esc_html( $coupon_code ),
						esc_html( $reward_amount )
					);
				} else {
					$post_status = get_post_status( $post_id );
					if ( 'draft' === $post_status ) {
						$share_type = get_post_meta( $post_id, '_share_type', true );
						$type_label = ucwords( str_replace( '_', ' ', (string) $share_type ) );
						printf(
							'<span style="background:#fff3cd; color:#856404; padding:4px 8px; border-radius:4px; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.5px;">Pending (%s)</span>',
							esc_html( $type_label )
						);
					} else {
						echo '<span class="na" style="color:#e06d6d;">Failed / Pending</span>';
					}
				}
				break;
		}
	}

	/**
	 * Save admin settings.
	 *
	 * @return void
	 */
	public function save_settings(): void {
		if ( ! isset( $_POST['traf_save_settings_nonce'] ) || ! wp_verify_nonce( $_POST['traf_save_settings_nonce'], 'traf_save_settings' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
		$updates     = array();

		if ( 'general' === $current_tab ) {
			$updates['enabled']       = isset( $_POST['enabled'] ) ? '1' : '0';
			$updates['cookie_expiry'] = isset( $_POST['cookie_expiry'] ) ? max( 1, (int) $_POST['cookie_expiry'] ) : 30;
			$updates['param_name']    = isset( $_POST['param_name'] ) ? sanitize_title( $_POST['param_name'] ) : 'ref';
			if ( empty( $updates['param_name'] ) ) {
				$updates['param_name'] = 'ref';
			}
		} elseif ( 'referrer' === $current_tab ) {
			$referrer_type = isset( $_POST['referrer_reward_type'] ) ? sanitize_text_field( $_POST['referrer_reward_type'] ) : 'none';
			$updates['referrer_reward_type']   = in_array( $referrer_type, array( 'none', 'percent', 'fixed' ), true ) ? $referrer_type : 'none';
			$updates['referrer_reward_value']  = isset( $_POST['referrer_reward_value'] ) ? max( 0.0, (float) $_POST['referrer_reward_value'] ) : 0.0;
			$updates['referrer_coupon_expiry'] = isset( $_POST['referrer_coupon_expiry'] ) ? max( 1, (int) $_POST['referrer_coupon_expiry'] ) : 30;
			$updates['email_subject']          = isset( $_POST['email_subject'] ) ? sanitize_text_field( $_POST['email_subject'] ) : '';
			$updates['email_content']          = isset( $_POST['email_content'] ) ? wp_kses_post( $_POST['email_content'] ) : '';
		} elseif ( 'referee' === $current_tab ) {
			$referee_type = isset( $_POST['referee_reward_type'] ) ? sanitize_text_field( $_POST['referee_reward_type'] ) : 'none';
			$updates['referee_reward_type']    = in_array( $referee_type, array( 'none', 'percent', 'fixed' ), true ) ? $referee_type : 'none';
			$updates['referee_reward_value']   = isset( $_POST['referee_reward_value'] ) ? max( 0.0, (float) $_POST['referee_reward_value'] ) : 0.0;
			$updates['referee_discount_label'] = isset( $_POST['referee_discount_label'] ) ? sanitize_text_field( $_POST['referee_discount_label'] ) : '';
		}

		$this->settings->update( $updates );

		// Redirect to prevent form resubmission.
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'traf-settings',
					'tab'     => $current_tab,
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render the Settings Page HTML.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
		$updated    = isset( $_GET['updated'] );
		?>
		<div class="wrap traf-settings-wrap">
			<h1><?php esc_html_e( 'Thaaniyam Refer a Friend', 'thaaniyam-refer-a-friend' ); ?></h1>

			<?php if ( $updated ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings updated successfully.', 'thaaniyam-refer-a-friend' ); ?></p>
				</div>
			<?php endif; ?>

			<h2 class="nav-tab-wrapper">
				<a href="?page=traf-settings&tab=general" class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'General Settings', 'thaaniyam-refer-a-friend' ); ?>
				</a>
				<a href="?page=traf-settings&tab=referrer" class="nav-tab <?php echo 'referrer' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Referrer Rewards (Existing User)', 'thaaniyam-refer-a-friend' ); ?>
				</a>
				<a href="?page=traf-settings&tab=referee" class="nav-tab <?php echo 'referee' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Referee Rewards (Friend)', 'thaaniyam-refer-a-friend' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=thaaniyam_referral' ) ); ?>" class="nav-tab">
					<?php esc_html_e( 'Referrals Log', 'thaaniyam-refer-a-friend' ); ?>
				</a>
			</h2>

			<form method="post" action="" class="traf-settings-form">
				<?php wp_nonce_field( 'traf_save_settings', 'traf_save_settings_nonce' ); ?>

				<div class="traf-tab-content traf-card">
					<?php if ( 'general' === $active_tab ) : ?>
						
						<h3><?php esc_html_e( 'General Settings', 'thaaniyam-refer-a-friend' ); ?></h3>
						<table class="form-table">
							<tr>
								<th scope="row"><label for="enabled"><?php esc_html_e( 'Enable Referral Program', 'thaaniyam-refer-a-friend' ); ?></label></th>
								<td>
									<label class="traf-switch">
										<input type="checkbox" name="enabled" id="enabled" value="1" <?php checked( $this->settings->get( 'enabled', '1' ), '1' ); ?>>
										<span class="traf-slider round"></span>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="param_name"><?php esc_html_e( 'Referral URL Parameter', 'thaaniyam-refer-a-friend' ); ?></label></th>
								<td>
									<input type="text" name="param_name" id="param_name" value="<?php echo esc_attr( (string) $this->settings->get( 'param_name', 'ref' ) ); ?>" class="regular-text">
									<p class="description"><?php esc_html_e( 'The query parameter name to track in URLs (e.g. yoursite.com/?ref=username). Keep it lowercase and alphanumeric.', 'thaaniyam-refer-a-friend' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="cookie_expiry"><?php esc_html_e( 'Referral Cookie Expiry (Days)', 'thaaniyam-refer-a-friend' ); ?></label></th>
								<td>
									<input type="number" name="cookie_expiry" id="cookie_expiry" value="<?php echo esc_attr( (string) $this->settings->get( 'cookie_expiry', 30 ) ); ?>" class="small-text" min="1">
									<p class="description"><?php esc_html_e( 'Number of days the referral tracking cookie will last after the referee clicks the link.', 'thaaniyam-refer-a-friend' ); ?></p>
								</td>
							</tr>
						</table>

					<?php elseif ( 'referrer' === $active_tab ) : ?>

						<h3><?php esc_html_e( 'Referrer Reward Settings', 'thaaniyam-refer-a-friend' ); ?></h3>
						<p><?php esc_html_e( 'Configure the reward coupon that is automatically generated and sent to the existing customer who refers a friend when the friend makes their first purchase.', 'thaaniyam-refer-a-friend' ); ?></p>
						<table class="form-table">
							<tr>
								<th scope="row"><label for="referrer_reward_type"><?php esc_html_e( 'Coupon Discount Type', 'thaaniyam-refer-a-friend' ); ?></label></th>
								<td>
									<select name="referrer_reward_type" id="referrer_reward_type">
										<option value="percent" <?php selected( $this->settings->get( 'referrer_reward_type' ), 'percent' ); ?>><?php esc_html_e( 'Percentage Discount (%)', 'thaaniyam-refer-a-friend' ); ?></option>
										<option value="fixed" <?php selected( $this->settings->get( 'referrer_reward_type' ), 'fixed' ); ?>><?php esc_html_e( 'Fixed Cart Discount (currency unit)', 'thaaniyam-refer-a-friend' ); ?></option>
										<option value="none" <?php selected( $this->settings->get( 'referrer_reward_type' ), 'none' ); ?>><?php esc_html_e( 'No Reward', 'thaaniyam-refer-a-friend' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="referrer_reward_value"><?php esc_html_e( 'Discount Amount', 'thaaniyam-refer-a-friend' ); ?></label></th>
								<td>
									<input type="number" step="any" min="0" name="referrer_reward_value" id="referrer_reward_value" value="<?php echo esc_attr( (string) $this->settings->get( 'referrer_reward_value', 10 ) ); ?>" class="small-text">
									<p class="description"><?php esc_html_e( 'Discount percentage or flat discount amount.', 'thaaniyam-refer-a-friend' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="referrer_coupon_expiry"><?php esc_html_e( 'Coupon Validity (Days)', 'thaaniyam-refer-a-friend' ); ?></label></th>
								<td>
									<input type="number" name="referrer_coupon_expiry" id="referrer_coupon_expiry" value="<?php echo esc_attr( (string) $this->settings->get( 'referrer_coupon_expiry', 30 ) ); ?>" class="small-text" min="1">
									<p class="description"><?php esc_html_e( 'Number of days the generated coupon remains valid after it is issued.', 'thaaniyam-refer-a-friend' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="email_subject"><?php esc_html_e( 'Reward Email Subject', 'thaaniyam-refer-a-friend' ); ?></label></th>
								<td>
									<input type="text" name="email_subject" id="email_subject" value="<?php echo esc_attr( (string) $this->settings->get( 'email_subject', '' ) ); ?>" class="large-text">
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="email_content"><?php esc_html_e( 'Reward Email Content', 'thaaniyam-refer-a-friend' ); ?></label></th>
								<td>
									<?php
									$content   = $this->settings->get( 'email_content', '' );
									$editor_id = 'email_content';
									$settings  = array(
										'media_buttons' => false,
										'textarea_rows' => 12,
										'teeny'         => true,
									);
									wp_editor( $content, $editor_id, $settings );
									?>
									<p class="description">
										<?php esc_html_e( 'Placeholders available:', 'thaaniyam-refer-a-friend' ); ?><br>
										<code>{referrer_name}</code> &mdash; <?php esc_html_e( "Referrer's display name", 'thaaniyam-refer-a-friend' ); ?><br>
										<code>{friend_email}</code> &mdash; <?php esc_html_e( "Referred friend's email", 'thaaniyam-refer-a-friend' ); ?><br>
										<code>{coupon_code}</code> &mdash; <?php esc_html_e( 'Generated discount coupon code', 'thaaniyam-refer-a-friend' ); ?><br>
										<code>{discount_value}</code> &mdash; <?php esc_html_e( 'Formatted value of the discount (e.g. 10% or $10.00)', 'thaaniyam-refer-a-friend' ); ?><br>
										<code>{expiry_days}</code> &mdash; <?php esc_html_e( 'Number of days coupon is valid for', 'thaaniyam-refer-a-friend' ); ?>
									</p>
								</td>
							</tr>
						</table>

					<?php elseif ( 'referee' === $active_tab ) : ?>

						<h3><?php esc_html_e( 'Referee (Friend) Reward Settings', 'thaaniyam-refer-a-friend' ); ?></h3>
						<p><?php esc_html_e( 'Configure the discount applied automatically to the shopping cart when a visitor shops using a referral link.', 'thaaniyam-refer-a-friend' ); ?></p>
						<table class="form-table">
							<tr>
								<th scope="row"><label for="referee_reward_type"><?php esc_html_e( 'Discount Type', 'thaaniyam-refer-a-friend' ); ?></label></th>
								<td>
									<select name="referee_reward_type" id="referee_reward_type">
										<option value="percent" <?php selected( $this->settings->get( 'referee_reward_type' ), 'percent' ); ?>><?php esc_html_e( 'Percentage Discount (%)', 'thaaniyam-refer-a-friend' ); ?></option>
										<option value="fixed" <?php selected( $this->settings->get( 'referee_reward_type' ), 'fixed' ); ?>><?php esc_html_e( 'Fixed Discount (currency unit)', 'thaaniyam-refer-a-friend' ); ?></option>
										<option value="none" <?php selected( $this->settings->get( 'referee_reward_type' ), 'none' ); ?>><?php esc_html_e( 'No Reward', 'thaaniyam-refer-a-friend' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="referee_reward_value"><?php esc_html_e( 'Discount Amount', 'thaaniyam-refer-a-friend' ); ?></label></th>
								<td>
									<input type="number" step="any" min="0" name="referee_reward_value" id="referee_reward_value" value="<?php echo esc_attr( (string) $this->settings->get( 'referee_reward_value', 10 ) ); ?>" class="small-text">
									<p class="description"><?php esc_html_e( 'Discount percentage or flat discount amount.', 'thaaniyam-refer-a-friend' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="referee_discount_label"><?php esc_html_e( 'Cart Discount Label', 'thaaniyam-refer-a-friend' ); ?></label></th>
								<td>
									<input type="text" name="referee_discount_label" id="referee_discount_label" value="<?php echo esc_attr( (string) $this->settings->get( 'referee_discount_label', 'Referral Discount' ) ); ?>" class="regular-text">
									<p class="description"><?php esc_html_e( 'The label shown next to the discount in the cart/checkout totals (e.g. "Friend Referral Offer").', 'thaaniyam-refer-a-friend' ); ?></p>
								</td>
							</tr>
						</table>

					<?php endif; ?>
				</div>

				<div class="submit-button-container">
					<input type="submit" name="submit" id="submit" class="button button-primary traf-save-btn" value="<?php esc_attr_e( 'Save Changes', 'thaaniyam-refer-a-friend' ); ?>">
				</div>
			</form>
		</div>
		<?php
	}
}
