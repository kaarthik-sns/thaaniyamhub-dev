<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Woo_Gallery_Slider
 * @subpackage Woo_Gallery_Slider/admin
 * @author     ShapedPlugin <support@shapedplugin.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}  // if direct access.

use Automattic\WooCommerce\Admin\Features\ProductBlockEditor\BlockRegistry;

/**
 * WooGallery Admin class
 */
class Woo_Gallery_Slider_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string $plugin_name       The name of this plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 10 );
		// Autoloading system.
		spl_autoload_register( array( $this, 'autoload' ) );

		add_action( 'after_setup_theme', array( $this, 'wcgs_framework_config' ) );
		add_filter( 'custom_menu_order', array( $this, 'reorder_submenus' ) );
		add_filter( 'post_row_actions', array( $this, 'wcgs_remove_defaults_action' ), 10, 2 );
		add_filter( 'manage_wcgs_layouts_posts_columns', array( $this, 'add_wcgs_layouts_column' ) );
		add_action( 'manage_wcgs_layouts_posts_custom_column', array( $this, 'add_wcgs_layouts_extra_column' ), 10, 2 );
		add_filter( 'post_updated_messages', array( $this, 'layout_updated_messages' ), 10, 2 );
		add_action( 'load-post-new.php', array( $this, 'check_wcgs_layouts_limit' ) );
		add_filter( 'admin_footer_text', array( $this, 'sp_woo_review_text' ), 1, 2 );
	}


	/**
	 * Config framework options
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function wcgs_framework_config() {
		WCGS_Settings::options( 'wcgs_settings' );
		if ( class_exists( 'WCGS_Metaboxs' ) ) {
			WCGS_Metaboxs::options( 'wcgs_metabox' );
		}
		WCGS_Assigns::options( 'wcgs_assigns' );
	}

	/**
	 * Register wcgs_layouts custom post type
	 *
	 * @since
	 */
	public function wcgs_layouts_post_type() {
		$labels     = array(
			'name'               => __( 'Manage Layouts', 'gallery-slider-for-woocommerce' ),
			'singular_name'      => __( 'Layout', 'gallery-slider-for-woocommerce' ),
			'add_new'            => __( 'Add New', 'gallery-slider-for-woocommerce' ),
			'add_new_item'       => __( 'Add New Layout', 'gallery-slider-for-woocommerce' ),
			'edit_item'          => __( 'Edit', 'gallery-slider-for-woocommerce' ),
			'new_item'           => __( 'New Layout', 'gallery-slider-for-woocommerce' ),
			'all_items'          => __( 'Manage Layouts', 'gallery-slider-for-woocommerce' ),
			'search_items'       => __( 'Search', 'gallery-slider-for-woocommerce' ),
			'not_found'          => __( 'No Layout Found', 'gallery-slider-for-woocommerce' ),
			'not_found_in_trash' => __( 'No Layout Found in Trash', 'gallery-slider-for-woocommerce' ),
			'parent_item_colon'  => null,
		);
		$menu_icon  = 'data:image/svg+xml;base64,PHN2ZyB2ZXJzaW9uPSIxLjEiIGlkPSJMYXllcl8xIiBmb2N1c2FibGU9ImZhbHNlIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIgoJIHg9IjBweCIgeT0iMHB4IiB2aWV3Qm94PSIwIDAgMjQgMjQiIHN0eWxlPSJlbmFibGUtYmFja2dyb3VuZDpuZXcgMCAwIDI0IDI0OyIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSI+CjxzdHlsZSB0eXBlPSJ0ZXh0L2NzcyI+Cgkuc3Qwe2ZpbGw6I0ZGRkZGRjt9Cjwvc3R5bGU+CjxnPgoJPHBhdGggY2xhc3M9InN0MCIgZD0iTTAsMS45djIwLjFDMCwyMy4xLDAuOSwyNCwxLjksMjRoMjAuMWMxLjEsMCwxLjktMC45LDEuOS0xLjlWMS45QzI0LDAuOSwyMy4xLDAsMjIuMSwwSDEuOUMwLjksMCwwLDAuOSwwLDEuOQoJCXogTTIxLjQsMjIuM0gyLjZjLTAuNSwwLTEtMC40LTEtMVYyLjZjMC0wLjUsMC40LTEsMS0xaDE4LjdjMC41LDAsMSwwLjQsMSwxdjE4LjdDMjIuMywyMS45LDIxLjksMjIuMywyMS40LDIyLjN6Ii8+Cgk8cGF0aCBjbGFzcz0ic3QwIiBkPSJNNy45LDE3LjR2Mi44YzAsMC4zLTAuMiwwLjUtMC41LDAuNUgzLjhjLTAuMywwLTAuNS0wLjItMC41LTAuNXYtMi44YzAtMC4zLDAuMi0wLjUsMC41LTAuNWgzLjUKCQlDNy42LDE2LjksNy45LDE3LjEsNy45LDE3LjR6Ii8+Cgk8cGF0aCBjbGFzcz0ic3QwIiBkPSJNMTQuNSwxNy40djIuOGMwLDAuMy0wLjIsMC41LTAuNSwwLjVoLTRjLTAuMywwLTAuNS0wLjItMC41LTAuNXYtMi44YzAtMC4zLDAuMi0wLjUsMC41LTAuNWg0CgkJQzE0LjIsMTYuOSwxNC41LDE3LjEsMTQuNSwxNy40eiIvPgoJPHBhdGggY2xhc3M9InN0MCIgZD0iTTIwLjYsMTcuNHYyLjhjMCwwLjMtMC4yLDAuNS0wLjUsMC41aC0zLjVjLTAuMywwLTAuNS0wLjItMC41LTAuNXYtMi44YzAtMC4zLDAuMi0wLjUsMC41LTAuNWgzLjUKCQlDMjAuNCwxNi45LDIwLjYsMTcuMSwyMC42LDE3LjR6Ii8+Cgk8cGF0aCBjbGFzcz0ic3QwIiBkPSJNMy40LDMuOHYxMC45YzAsMC4zLDAuMiwwLjUsMC41LDAuNWgxNi4zYzAuMywwLDAuNS0wLjIsMC41LTAuNVYzLjhjMC0wLjMtMC4yLTAuNS0wLjUtMC41SDMuOAoJCUMzLjYsMy40LDMuNCwzLjYsMy40LDMuOHogTTUuNCwxMi44bDMuOC03YzAuMi0wLjMsMC43LTAuMywwLjgsMGwyLjcsNC45YzAuMiwwLjMsMC43LDAuMywwLjgsMGwwLjQtMC43YzAuMi0wLjMsMC43LTAuMywwLjgsMAoJCWwxLjUsMi43YzAuMiwwLjMtMC4xLDAuNy0wLjQsMC43aC0xMEM1LjUsMTMuNSw1LjMsMTMuMSw1LjQsMTIuOHogTTE2LjgsOS40Yy0xLjIsMC0yLjItMS0yLjItMi4yYzAtMS4yLDEtMi4xLDIuMS0yLjEKCQlDMTgsNSwxOSw2LDE5LDcuMkMxOC45LDguNCwxOCw5LjMsMTYuOCw5LjR6Ii8+CjwvZz4KPC9zdmc+';
		$capability = apply_filters( 'wcgs_ui_permission', 'manage_options' );
		$show_ui    = current_user_can( $capability ) ? true : false;
		register_post_type(
			'wcgs_layouts',
			array(
				'labels'              => $labels,
				'capability_type'     => 'post',
				'supports'            => array( 'title' ),
				'show_in_menu'        => 'wpgs-settings', // This makes it a submenu under.
				'public'              => true,
				'publicly_queryable'  => false,
				'show_ui'             => $show_ui,
				'exclude_from_search' => true,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'has_archive'         => false,
				'show_in_rest'        => true,
				'priority'            => 1,
			)
		);
	}
	/**
	 * Add menu items.
	 */
	public function admin_menu() {
		$capability = apply_filters( 'wcgs_ui_permission', 'manage_options' );
		$menu_icon  = 'data:image/svg+xml;base64,PHN2ZyB2ZXJzaW9uPSIxLjEiIGlkPSJMYXllcl8xIiBmb2N1c2FibGU9ImZhbHNlIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIgoJIHg9IjBweCIgeT0iMHB4IiB2aWV3Qm94PSIwIDAgMjQgMjQiIHN0eWxlPSJlbmFibGUtYmFja2dyb3VuZDpuZXcgMCAwIDI0IDI0OyIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSI+CjxzdHlsZSB0eXBlPSJ0ZXh0L2NzcyI+Cgkuc3Qwe2ZpbGw6I0ZGRkZGRjt9Cjwvc3R5bGU+CjxnPgoJPHBhdGggY2xhc3M9InN0MCIgZD0iTTAsMS45djIwLjFDMCwyMy4xLDAuOSwyNCwxLjksMjRoMjAuMWMxLjEsMCwxLjktMC45LDEuOS0xLjlWMS45QzI0LDAuOSwyMy4xLDAsMjIuMSwwSDEuOUMwLjksMCwwLDAuOSwwLDEuOQoJCXogTTIxLjQsMjIuM0gyLjZjLTAuNSwwLTEtMC40LTEtMVYyLjZjMC0wLjUsMC40LTEsMS0xaDE4LjdjMC41LDAsMSwwLjQsMSwxdjE4LjdDMjIuMywyMS45LDIxLjksMjIuMywyMS40LDIyLjN6Ii8+Cgk8cGF0aCBjbGFzcz0ic3QwIiBkPSJNNy45LDE3LjR2Mi44YzAsMC4zLTAuMiwwLjUtMC41LDAuNUgzLjhjLTAuMywwLTAuNS0wLjItMC41LTAuNXYtMi44YzAtMC4zLDAuMi0wLjUsMC41LTAuNWgzLjUKCQlDNy42LDE2LjksNy45LDE3LjEsNy45LDE3LjR6Ii8+Cgk8cGF0aCBjbGFzcz0ic3QwIiBkPSJNMTQuNSwxNy40djIuOGMwLDAuMy0wLjIsMC41LTAuNSwwLjVoLTRjLTAuMywwLTAuNS0wLjItMC41LTAuNXYtMi44YzAtMC4zLDAuMi0wLjUsMC41LTAuNWg0CgkJQzE0LjIsMTYuOSwxNC41LDE3LjEsMTQuNSwxNy40eiIvPgoJPHBhdGggY2xhc3M9InN0MCIgZD0iTTIwLjYsMTcuNHYyLjhjMCwwLjMtMC4yLDAuNS0wLjUsMC41aC0zLjVjLTAuMywwLTAuNS0wLjItMC41LTAuNXYtMi44YzAtMC4zLDAuMi0wLjUsMC41LTAuNWgzLjUKCQlDMjAuNCwxNi45LDIwLjYsMTcuMSwyMC42LDE3LjR6Ii8+Cgk8cGF0aCBjbGFzcz0ic3QwIiBkPSJNMy40LDMuOHYxMC45YzAsMC4zLDAuMiwwLjUsMC41LDAuNWgxNi4zYzAuMywwLDAuNS0wLjIsMC41LTAuNVYzLjhjMC0wLjMtMC4yLTAuNS0wLjUtMC41SDMuOAoJCUMzLjYsMy40LDMuNCwzLjYsMy40LDMuOHogTTUuNCwxMi44bDMuOC03YzAuMi0wLjMsMC43LTAuMywwLjgsMGwyLjcsNC45YzAuMiwwLjMsMC43LDAuMywwLjgsMGwwLjQtMC43YzAuMi0wLjMsMC43LTAuMywwLjgsMAoJCWwxLjUsMi43YzAuMiwwLjMtMC4xLDAuNy0wLjQsMC43aC0xMEM1LjUsMTMuNSw1LjMsMTMuMSw1LjQsMTIuOHogTTE2LjgsOS40Yy0xLjIsMC0yLjItMS0yLjItMi4yYzAtMS4yLDEtMi4xLDIuMS0yLjEKCQlDMTgsNSwxOSw2LDE5LDcuMkMxOC45LDguNCwxOCw5LjMsMTYuOCw5LjR6Ii8+CjwvZz4KPC9zdmc+';
		add_menu_page( __( 'Product Gallery', 'gallery-slider-for-woocommerce' ), __( 'Product Gallery', 'gallery-slider-for-woocommerce' ), $capability, 'wpgs-settings', null, $menu_icon, '58' );
		add_submenu_page( 'wpgs-settings', 'Help', 'Get Help', 'edit_posts', 'wpgs-help', array( $this, 'help_page_callback' ) );
	}
	/**
	 * Review Text
	 *
	 * @param string $text text.
	 *
	 * @return string
	 */
	public function sp_woo_review_text( $text ) {
		$current_screen = get_current_screen();

		if ( is_object( $current_screen ) && ( 'wcgs_layouts' === $current_screen->post_type || get_plugin_page_hookname( 'wpgs-help', 'wpgs-settings' ) === $current_screen->base ) ) {

			$text = sprintf(
					/* translators: 1: start strong tag, 2: close strong tag, 3: span tag start, 4: span tag end, 5: anchor tag start, 6: anchor tag ended. */
				__( 'Enjoying %1$sReno Product Gallery?%2$s Please rate us %3$s★★★★★%4$s %5$sWordPress.org.%6$s Your positive feedback will help us grow more. Thank you! 😊', 'gallery-slider-for-woocommerce' ),
				'<strong>',
				'</strong>',
				'<span class="spwpcp-footer-text-star">',
				'</span>',
				'<a href="https://wordpress.org/support/plugin/gallery-slider-for-woocommerce/reviews/" target="_blank">',
				'</a>'
			);
		}
		return $text;
	}

	/**
	 * Help page callback.
	 */
	public function help_page_callback() {
		Woo_Gallery_Slider_Help::instance();
	}

	/**
	 * Add notification custom column.
	 *
	 * @return array
	 */
	public function add_wcgs_layouts_column() {
		$new_columns['cb']          = '<input type="checkbox" />';
		$new_columns['title']       = __( 'Title', 'gallery-slider-for-woocommerce' );
		$new_columns['layout_type'] = __( 'Gallery Layout', 'gallery-slider-for-woocommerce' );
		$new_columns['date']        = __( 'Date', 'gallery-slider-for-woocommerce' );

		return $new_columns;
	}

	/**
	 * Save product assign options.
	 *
	 * @param string $post_id post ID.
	 * @param string $post post.
	 */
	public function save_product_assign_options( $post_id, $post ) {
		if ( 'product' !== $post->post_type ) {
			return;
		}

		$wcgs_options_nonce = isset( $_POST['wcgs_options_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wcgs_options_nonce'] ) ) : '';
		if ( $wcgs_options_nonce && wp_verify_nonce( $wcgs_options_nonce, 'wcgs_options_nonce' ) ) {
			if ( $post_id && isset( $_POST['wcgs_assign_layout_settings'] ) ) {
				$wcgs_assign_gallery_layout = isset( $_POST['wcgs_assign_layout_settings'] ) ? sanitize_text_field( wp_unslash( $_POST['wcgs_assign_layout_settings'] ) ) : 0;
				update_post_meta( $post_id, 'wcgs_assign_layout_settings', $wcgs_assign_gallery_layout );
			} else {
				delete_post_meta( $post_id, 'wcgs_assign_layout_settings' );
			}
		}
	}

	/**
	 * Product options meta box.
	 */
	public function check_wcgs_layouts_limit() {
		global $typenow;

		if ( 'wcgs_layouts' !== $typenow ) {
			return;
		}
		$existing_posts = get_posts(
			array(
				'post_type'      => 'wcgs_layouts',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'post_status'    => array( 'publish', 'pending', 'draft', 'future', 'private', 'trash' ),
			)
		);

		if ( ! empty( $existing_posts ) ) {
			wp_safe_redirect( admin_url( 'edit.php?post_type=wcgs_layouts' ) );
			exit;
		}
	}

	/**
	 * Add Notification column data.
	 *
	 * @param array  $column column.
	 * @param string $post_id id.
	 */
	public function add_wcgs_layouts_extra_column( $column, $post_id ) {
		$wcgs_layouts_meta = get_post_meta( $post_id, 'wcgs_layouts_metaboxes', true );
		$post              = get_post( $post_id );
		switch ( $column ) {
			case 'layout_type':
				$wcgs_layouts_meta     = get_post_meta( $post_id, 'wcgs_metabox', true );
				$wcgs_layouts_type     = isset( $wcgs_layouts_meta['gallery_layout'] ) ? $wcgs_layouts_meta['gallery_layout'] : 'horizontal';
				$grid_orientation      = isset( $wcgs_layouts_meta['grid_orientation'] ) ? $wcgs_layouts_meta['grid_orientation'] : '';
				$grid_orientation_name = '';
				$pro_popup_content     = sprintf(
					'<div id="BuyProPopupContent" style="display: none;"> <div class="wcgs-popup-content"><h3>%1$s</h3>
					<p class="wcgs-popup-p">%2$s 🚀</p>
					<p> <a href="%3$s" target="_blank" class="btn">%4$s</a> </p> </div> </div>',
					__( 'Create unlimited custom product gallery layouts and assign them to specific products and categories.', 'gallery-slider-for-woocommerce' ), // %4$s - H3 Subheading
					sprintf(
					/* translators: 1: strong open tag, 2: strong close tag. */
						__( 'Take your online shop\'s product page experience to the next level with many premium features and %1$sBoost Sales!%2$s %3$s', 'gallery-slider-for-woocommerce' ),
						'<strong>',
						'</strong>',
						'<span class="pro-boost-icon">🚀</span>'
					), // %5$s - Paragraph text
					esc_url( WOO_GALLERY_SLIDER_PRO_LINK ), // %6$s - Pro upgrade link
					__( 'Upgrade to Pro Now', 'gallery-slider-for-woocommerce' ) // %7$s - Button text
				);

				echo wp_kses_post( $pro_popup_content );

				echo '<p class="wcgs-gallery_layout">' . esc_html( $this->get_gallery_layout_name( $wcgs_layouts_type ) . $grid_orientation_name ) . '</p>';
				break;
			case 'action':
				// Edit link.
				$edit_link = get_edit_post_link( $post_id );
				// Delete link.
				$delete_link = get_delete_post_link( $post_id );

				// Output action links.
				echo '<div class="custom-row-actions">';
					// Edit link.
					echo '<span class="edit">';
						echo '<a href="' . esc_url( $edit_link ) . '" class="custom-edit-link"> <i class="icon-edit"></> Edit</a>';
					echo '</span> | ';
					// Delete link.
					echo ' <span class="trash">';
					echo '<a href="' . esc_url( $delete_link ) . '" class="submitdelete custom-trash-link"><i class="icon-delete-2"></i>Trash</a>';
					echo '</span>';
				echo '</div>';
				break;
		} // end switch
	}
	/**
	 * Get_gallery_layout_name.
	 *
	 * @param  string $gallery_layout gallery_layout.
	 * @return string
	 */
	private function get_gallery_layout_name( $gallery_layout ) {
		switch ( $gallery_layout ) {
			case 'horizontal':
				return __( 'Thumbs Bottom', 'gallery-slider-for-woocommerce' );

			case 'vertical':
				return __( 'Thumbs Left', 'gallery-slider-for-woocommerce' );

			case 'grid':
				return __( 'Grid', 'gallery-slider-for-woocommerce' );

			case 'modern':
				return __( 'Hierarchy Grid', 'gallery-slider-for-woocommerce' );

			case 'anchor_navigation':
				return __( 'Anchor Nav', 'gallery-slider-for-woocommerce' );

			case 'vertical_right':
				return __( 'Thumbs Right', 'gallery-slider-for-woocommerce' );

			case 'horizontal_top':
				return __( 'Thumbs Top', 'gallery-slider-for-woocommerce' );

			case 'multi_row_thumb':
				return __( 'Multi Row Thumbs', 'gallery-slider-for-woocommerce' );

			case 'modern_grid':
				return __( 'Modern Grid', 'gallery-slider-for-woocommerce' );

			case 'hide_thumb':
				return __( 'Slider', 'gallery-slider-for-woocommerce' );

			default:
				return $gallery_layout;
		}
	}
	/**
	 * Change Carousel updated messages.
	 *
	 * @param string $messages The Update messages.
	 * @return statement
	 */
	public function layout_updated_messages( $messages ) {
		global $post, $post_ID;
		$revision_id = isset( $_GET['revision'] ) ? absint( $_GET['revision'] ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Safe read-only access

		$messages['wcgs_layouts'] = array(
			0  => '', // Unused. Messages start at index 1.
			1  => __( 'Layout updated.', 'gallery-slider-for-woocommerce' ),
			2  => '',
			3  => '',
			4  => __( 'Layout updated.', 'gallery-slider-for-woocommerce' ),
			/* translators: %s: revision */
			5  => $revision_id ? sprintf( __( 'Layout restored to revision from %s', 'gallery-slider-for-woocommerce' ), wp_post_revision_title( $revision_id, false ) ) : false,
			6  => __( 'Layout published.', 'gallery-slider-for-woocommerce' ),
			7  => __( 'Layout saved.', 'gallery-slider-for-woocommerce' ),
			8  => __( 'Layout submitted.', 'gallery-slider-for-woocommerce' ),
			/* translators: %1$s: time */
			9  => sprintf( __( 'Layout scheduled for: <strong>%1$s</strong>.', 'gallery-slider-for-woocommerce' ), date_i18n( __( 'M j, Y @ G:i', 'gallery-slider-for-woocommerce' ), strtotime( $post->post_date ) ) ),
			10 => __( 'Layout draft updated.', 'gallery-slider-for-woocommerce' ),
		);
		return $messages;
	}
	/**
	 * Function to duplicate notification post.
	 *
	 * @param  array  $actions  actions.
	 * @param  object $post post.
	 * @return array
	 * @since    0.0.1
	 */
	public function wcgs_remove_defaults_action( $actions, $post ) {
		$capability = apply_filters( 'wcgs_ui_permission', 'manage_options' );
		$show_ui    = current_user_can( $capability ) ? true : false;
		if ( $show_ui && 'wcgs_layouts' === $post->post_type ) {
			if ( isset( $actions['edit'] ) ) {
				$actions['edit'] = '<a class="wcgs_tooltip-link" data-tooltip-title="Edit" href="' . esc_url( get_edit_post_link( $post->ID ) ) . '" class="custom-edit-link"><i class="icon-edit"></i> Edit</a>';
			}

			if ( isset( $actions['trash'] ) ) {
				$trash_post = '<span class="trash"><a href="' . esc_url( get_delete_post_link( $post->ID ) ) . '" class="submitdelete custom-trash-link" data-tooltip-title="Trash"><i class="icon-delete-2"></i>Trash</a></span></div>';
				unset( $actions['trash'] );
			}
			// Take the position of trash button after the duplicate button.
			if ( isset( $trash_post ) ) {
				$actions['trash'] = $trash_post;
			}
		}
		return $actions;
	}

	/**
	 * Reorder submenus.
	 *
	 * @param  array $menu_ord Menu order.
	 * @return array Menu
	 */
	public function reorder_submenus( $menu_ord ) {
		global $submenu;
		if ( isset( $submenu['wpgs-settings'] ) ) {
			$wpgs_menu = $submenu['wpgs-settings'];
			usort( $wpgs_menu, array( $this, 'sort_submenus' ) );
			$submenu['wpgs-settings'] = $wpgs_menu; // phpcs:ignore -- this is variable is inside class based function, so it is safe to use.
		}

		return $menu_ord;
	}
		/**
		 * Sort submenus.
		 *
		 * @param  array $a array of.
		 * @param  array $b Array of.
		 * @return int
		 */
	private function sort_submenus( $a, $b ) {
		$priority_order = array(
			'wpgs-settings'                   => 1,
			'edit.php?post_type=wcgs_layouts' => 2,
			'assign_layout'                   => 3,
			'wpgs-help'                       => 4,
		);
		$priority_a     = isset( $priority_order[ $a[2] ] ) ? $priority_order[ $a[2] ] : 99;
		$priority_b     = isset( $priority_order[ $b[2] ] ) ? $priority_order[ $b[2] ] : 99;
		return $priority_a - $priority_b;
	}
	/**
	 * Autoload class files on demand
	 *
	 * @since 1.0.0
	 * @access private
	 * @param string $class requested class name.
	 */
	private function autoload( $class ) {
		$name = explode( '_', $class );
		if ( isset( $name[1] ) ) {
			$class_name        = strtolower( $name[1] );
			$spto_config_paths = array( 'partials', 'partials/sections' );
			$wcgs_plugin_path  = plugin_dir_path( __FILE__ );

			foreach ( $spto_config_paths as $sptp_path ) {
				$filename = $wcgs_plugin_path . $sptp_path . '/class-wcgs-' . $class_name . '.php';
				if ( file_exists( $filename ) ) {
					require_once $filename;
				}
			}
		}
	}

	/**
	 * Implementaion of yield for better performance.
	 * wcgs_reduce_processor_use
	 *
	 * @param array $array array.
	 */
	public function wcgs_reduce_processor_use( $array ) {
		$array_length = count( $array );
		for ( $i = 0; $i < $array_length; $i++ ) {
			yield $array[ $i ];
		}
	}

	/**
	 * Add attachment video field.
	 *
	 * @access public
	 * @since 2.0.0
	 * @param  mixed  $form_fields form_fields.
	 * @param  object $post post.
	 */
	public function wcgs_add_media_custom_field( $form_fields, $post ) {
		$wcgs_video                 = get_post_meta( $post->ID, 'wcgs_video', true );
		$form_fields['wcgs_notice'] = array(
			'input' => 'html',
			'label' => '',
			'html'  => '<h2>' . esc_html__( 'Reno Product Gallery Video (Youtube)', 'gallery-slider-for-woocommerce' ) . '</h2>',
		);
		$form_fields['wcgs_video']  = array(
			'value' => $wcgs_video ? $wcgs_video : '',
			'label' => esc_html__( 'Video Link', 'gallery-slider-for-woocommerce' ),
			'input' => 'text',
			'helps' => sprintf(
				/* translators: 1: start link and strong tag, 2: close link and strong tag. 3: start link and strong tag, 4: close link and strong tag. */
				__( 'To show this video on the %1$sShop page%2$s, %3$s Upgrade to Pro!%2$s', 'gallery-slider-for-woocommerce' ),
				'<a href="https://demo.renoproductgallery.com/" target="_blank" class="btn"><strong>',
				'</strong></a>',
				'<a href="https://renoproductgallery.com/pricing/?ref=143" target="_blank" class="btn"><strong>'
			),
		);
		return $form_fields;
	}

	/**
	 * Save attachment having video field.
	 *
	 * @since 2.0.0
	 * @access public
	 * @param  integer $attachment_id attachment id.
	 */
	public function wcgs_add_media_custom_field_save( $attachment_id ) {
		if ( isset( $_REQUEST['attachments'][ $attachment_id ]['wcgs_video'] ) && function_exists( 'esc_url_raw' ) ) {
			$video = esc_url_raw( wp_unslash( $_REQUEST['attachments'][ $attachment_id ]['wcgs_video'] ) );
			update_post_meta( $attachment_id, 'wcgs_video', $video );
		}
	}

	/**
	 * Add WooCommerce Product Variation Gallery field from WCGS plugin.
	 *
	 * @param string $loop Product Variation id.
	 * @param mixed  $variation_data Product variation data.
	 * @param object $variation Product variation.
	 * @since    1.0.0
	 * @access public
	 */
	public function woocommerce_add_gallery_product_variation( $loop, $variation_data, $variation ) {
		?>
		<div class="wcgs-variation-gallery form-row form-row-full">
		<h4><?php esc_html_e( 'Variation Image Gallery by Reno Product Gallery', 'gallery-slider-for-woocommerce' ); ?><h4>
		<div class="wcgs-gallery-items" id="<?php echo esc_attr( $variation->ID ); ?>">
		<?php
		$variation_gallery     = get_post_meta( $variation->ID, 'woo_gallery_slider', true );
		$variation_gallery_arr = strpos( $variation_gallery, ']' ) !== false ? substr( $variation_gallery, 1, -1 ) : $variation_gallery;
		if ( ! empty( $variation_gallery_arr ) ) {
			$image_ids = explode( ',', $variation_gallery_arr );

			$yield_image_ids = $this->wcgs_reduce_processor_use( $image_ids );
			$count           = 1;
			foreach ( $yield_image_ids as $image_id ) {
				$image_attachment = wp_get_attachment_image_src( $image_id )[0];
				$video_url        = get_post_meta( $image_id, 'wcgs_video', true );
				if ( 2 >= $count ) {
					?>
						<div class="wcgs-image <?php echo $video_url ? 'wcgs-video' : ''; ?>" data-attachmentid="<?php echo esc_attr( $image_id ); ?>">
							<img src="<?php echo esc_attr( $image_attachment ); ?>" style="max-width:100%;display:inline-block;" />
							<div class="wcgs-image-remover"><span class="dashicons dashicons-no"></span></div>
						<?php
						if ( $video_url ) {
							?>
								<div class="wcgs-video-icons"><i class="dashicons dashicons-video-alt3"></i></div>
							<?php
						}
						?>
						</div>
						<?php
				}
				++$count;
			}
		}
		?>
		</div>
		<?php
			$_variation_gallery_attr    = empty( $variation_gallery_arr ) ? 'hidden' : '';
			$_variation_upload_img_attr = ! empty( $variation_gallery_arr ) ? 'hidden' : '';
		?>
		<p>
<button class="wcgs-remove-all-images button <?php echo esc_attr( $_variation_gallery_attr ); ?>">
			<?php esc_html_e( 'Remove all', 'gallery-slider-for-woocommerce' ); ?></button>
			<button class="wcgs-upload-image button <?php echo esc_attr( $_variation_upload_img_attr ); ?>
			" id="<?php echo 'wcgs-upload-' . esc_attr( $variation->ID ); ?>"><?php esc_html_e( 'Add Gallery Images', 'gallery-slider-for-woocommerce' ); ?></button>
			<button class="wcgs-upload-more-image button <?php echo esc_attr( $_variation_gallery_attr ); ?>
			"><?php esc_html_e( 'Add more', 'gallery-slider-for-woocommerce' ); ?></button>
			<button class="wcgs-edit button <?php echo esc_attr( $_variation_gallery_attr ); ?>">
			<?php esc_html_e( 'Edit Gallery', 'gallery-slider-for-woocommerce' ); ?>
			</button>
			<span class="wcgs-pro-notice
			<?php
			$image_ids = explode( ',', $variation_gallery_arr );
			if ( 2 >= count( $image_ids ) ) {
				echo 'hidden';
			}
			?>
			" style="color:red;">To add more images & videos, <a href="https://renoproductgallery.com/pricing/?ref=143" target="_blank" style="font-style: italic;">Upgrade to Pro!</a></span>

		</p>
		<script type="text/javascript">
		jQuery(document).ready( function($) {
			$('.wcgs-gallery-items').sortable({
				placeholder: "ui-state-highlight",
				stop: function() {
					var variableID = $(this).parents('.woocommerce_variation').find('.variable_post_id').val();
					variableID = '#'+variableID;
					var newWcgsArr = [];
					var _newWcgsArrLength = $('.wcgs-gallery-items'+variableID).find('.wcgs-image').length;
					$('.wcgs-gallery-items'+variableID).find('.wcgs-image').each( function() {
						var imageID = $(this).data('attachmentid');
						newWcgsArr.push(imageID);
					});
					$('.wcgs-gallery-items'+variableID).parents('.woocommerce_variable_attributes').find('.wcgs-gallery').val(JSON.stringify(newWcgsArr)).trigger('change');
				}
			});
		});
		</script>
		<div class="hidden">
			<?php
			woocommerce_wp_text_input(
				array(
					'id'    => 'woo_gallery_slider[' . $loop . ']',
					'class' => 'wcgs-gallery',
					'label' => '',
					'value' => get_post_meta( $variation->ID, 'woo_gallery_slider', true ),
				)
			);
			?>
		</div>
		</div>
		<?php
	}

	/**
	 * WooCommerce save gallery product variation.
	 *
	 * @param string $variation_id save gallery product.
	 * @param string $i save gallery product id.
	 * @return void
	 */
	public function woocommerce_save_gallery_product_variation( $variation_id, $i ) {
		if ( isset( $_POST['woo_gallery_slider'][ $i ] ) ) { // phpcs:ignore -- No nonce check needed — this hook fires inside WooCommerce's product save flow, which already verifies the nonce and user capability before dispatch.
			$custom_field = sanitize_text_field( wp_unslash( $_POST['woo_gallery_slider'][ $i ] ) ); // phpcs:ignore -- read the above comment.
			update_post_meta( $variation_id, 'woo_gallery_slider', $custom_field );
		}
	}

	/**
	 * Product variation transient clear.
	 *
	 * @param int $id product id.
	 * @return void
	 */
	public function spwg_product_variation_transient_data_clear( $id ) {
		if ( 'product' === get_post_type( $id ) ) {
			$spwg_product_variation = 'spwg_product_variation_' . $id;
			$spwg_product_html_id   = 'wcgsf_woo_gallery_' . $id . WOO_GALLERY_SLIDER_VERSION;
			if ( is_multisite() ) {
				$spwg_product_variation = 'site_' . get_current_blog_id() . $spwg_product_variation;
				if ( get_site_transient( $spwg_product_variation ) ) {
					delete_site_transient( $spwg_product_variation );
				}
				$spwg_product_html_site_id = 'site_' . get_current_blog_id() . $spwg_product_html_id;
				if ( get_site_transient( $spwg_product_html_site_id ) ) {
					delete_site_transient( $spwg_product_html_site_id );
				}
			} else {
				if ( get_transient( $spwg_product_html_id ) ) {
					delete_transient( $spwg_product_html_id );
				}
				if ( get_transient( $spwg_product_variation ) ) {
					delete_transient( $spwg_product_variation );
				}
			}
		}
	}

	/**
	 * WooCommerce add gallery product variation data
	 *
	 * @param array $variations gallery product variation data.
	 * @return array
	 */
	public function woocommerce_add_gallery_product_variation_data( $variations ) {
		$variations['woo_gallery_slider'] = get_post_meta( $variations['variation_id'], 'woo_gallery_slider', true );
		return $variations;
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 * @access public
	 */
	public function enqueue_styles() {

		/**
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Woo_Gallery_Slider_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Woo_Gallery_Slider_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */
		$current_screen = get_current_screen();

		// The submenu screen base is prefixed with the sanitized parent menu title, so derive it instead of hardcoding it.
		$assign_layout_base = get_plugin_page_hookname( 'assign_layout', 'wpgs-settings' );
		$help_base          = get_plugin_page_hookname( 'wpgs-help', 'wpgs-settings' );

		if ( is_object( $current_screen ) && ( 'wcgs_layouts' === $current_screen->post_type || 'toplevel_page_wpgs-settings' === $current_screen->base || $assign_layout_base === $current_screen->base || $help_base === $current_screen->base ) ) {
			wp_enqueue_style( 'wp-jquery-ui' );
			wp_enqueue_style( 'sp_wcgs-help-fontello', WOO_GALLERY_SLIDER_URL . 'admin/help-page/css/fontello.min.css', array(), WOO_GALLERY_SLIDER_VERSION, 'all' );
			wp_enqueue_style( 'sp_wcgs-help-page', WOO_GALLERY_SLIDER_URL . 'admin/help-page/css/help-page.min.css', array(), WOO_GALLERY_SLIDER_VERSION, 'all' );

			wp_enqueue_style( 'sp_wcgs-fontello-icons', WOO_GALLERY_SLIDER_URL . 'admin/css/fontello.min.css', array(), WOO_GALLERY_SLIDER_VERSION, 'all' );

		}
		wp_enqueue_style( 'sp-wcgs-notices', WOO_GALLERY_SLIDER_URL . 'admin/css/notices.min.css', array(), WOO_GALLERY_SLIDER_VERSION, 'all' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 * @access public
	 */
	public function enqueue_scripts() {

		/**
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Woo_Gallery_Slider_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Woo_Gallery_Slider_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */
		$current_screen = get_current_screen();

		if ( ! did_action( 'wp_enqueue_media' ) ) {
			wp_enqueue_media();
		}
		add_thickbox();
		wp_enqueue_script( $this->plugin_name . '-admin', WOO_GALLERY_SLIDER_URL . 'admin/js/woo-gallery-slider-admin.min.js', array( 'jquery' ), $this->version, true );
		wp_enqueue_style( $this->plugin_name . '-admin', WOO_GALLERY_SLIDER_URL . 'admin/css/woo-gallery-slider-admin.min.css', array(), WOO_GALLERY_SLIDER_VERSION, 'all' );
		if ( is_object( $current_screen ) && get_plugin_page_hookname( 'wpgs-help', 'wpgs-settings' ) === $current_screen->base ) {
			wp_enqueue_script( 'sp-wcgs-help-page', WOO_GALLERY_SLIDER_URL . 'admin/help-page/js/help-page.min.js', array( 'jquery' ), $this->version, true );
		}
	}

	/**
	 * Add a stable body class on the plugin's admin screens.
	 *
	 * The screen base WordPress puts on the body is prefixed with the
	 * sanitized parent menu title, which changes once that title is
	 * translated. Styles therefore hook onto this class instead.
	 *
	 * @param string $classes Space separated list of body classes.
	 * @return string
	 */
	public function wcgs_admin_body_class( $classes ) {
		$current_screen = get_current_screen();

		if ( ! is_object( $current_screen ) ) {
			return $classes;
		}

		if ( 'wcgs_layouts' === $current_screen->post_type
			|| 'toplevel_page_wpgs-settings' === $current_screen->base
			|| get_plugin_page_hookname( 'assign_layout', 'wpgs-settings' ) === $current_screen->base
			|| get_plugin_page_hookname( 'wpgs-help', 'wpgs-settings' ) === $current_screen->base ) {
			$classes .= ' wcgs-admin-page';
		}

		return $classes;
	}

	/**
	 * Footer version function
	 *
	 * @param string $text Plugin version.
	 * @return string
	 */
	public function wcgs_footer_version( $text ) {
		$current_screen = get_current_screen();
		if ( 'toplevel_page_wpgs-settings' === $current_screen->base ) {
			$text = 'Reno Product Gallery ' . $this->version;
		}
		return $text;
	}
}
