<?php
/**
 * The admin settings page of this plugin.
 *
 * Defines various settings of WooGallery.
 *
 * @package    Woo_Gallery_Slider
 * @subpackage Woo_Gallery_Slider/admin
 * @author     Shapedplugin <support@shapedplugin.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
} // Cannot access directly.

if ( ! function_exists( 'wcgs_reset_ajax' ) ) {
	/**
	 *
	 * Reset Ajax
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 */
	function wcgs_reset_ajax() {
		$capability = apply_filters( 'wcgs_ui_permission', 'manage_options' );

		// Capability first (house style) — reject before any nonce work.
		if ( ! current_user_can( $capability ) ) {
			wp_send_json_error( array( 'error' => esc_html__( 'Error: Unauthorized.', 'gallery-slider-for-woocommerce' ) ), 403 );
			return;
		}

		$nonce  = ( ! empty( $_POST['nonce'] ) ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		$unique = ( ! empty( $_POST['unique'] ) ) ? sanitize_text_field( wp_unslash( $_POST['unique'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'wcgs_backup_nonce' ) ) {
			wp_send_json_error( array( 'error' => esc_html__( 'Error: Invalid nonce verification.', 'gallery-slider-for-woocommerce' ) ), 403 );
			return;
		}

		// Allow-list the resettable keys (input validation on the sensitive param) — only the
		// framework's own option(s) may be deleted here; extend the filter for registered instances.
		$allowed = apply_filters(
			'wcgs_reset_allowed_options',
			array( 'wcgs_settings' ) // framework $this->unique values; extend only for registered instances.
		);
		if ( ! in_array( $unique, $allowed, true ) ) {
			wp_send_json_error( array( 'error' => esc_html__( 'Error: Invalid option.', 'gallery-slider-for-woocommerce' ) ), 403 );
			return;
		}

		// Success.
		delete_option( $unique );

		wp_send_json_success();
	}
	add_action( 'wp_ajax_wcgs-reset', 'wcgs_reset_ajax' );
}
if ( ! function_exists( 'wcgs_chosen_ajax' ) ) {
	/**
	 *
	 * Chosen Ajax
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 */
	function wcgs_chosen_ajax() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : null;
		if ( ! wp_verify_nonce( $nonce, 'wcgs_chosen_ajax_nonce' ) ) {
			wp_send_json_error( array( 'error' => esc_html__( 'Error: Invalid nonce verification.', 'gallery-slider-for-woocommerce' ) ) );
		}
		$type  = ( ! empty( $_POST['type'] ) ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
		$term  = ( ! empty( $_POST['term'] ) ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
		$query = ( ! empty( $_POST['query_args'] ) ) ? wp_kses_post_deep( $_POST['query_args'] ) : array();// phpcs:ignore

		if ( empty( $type ) || empty( $term ) ) {
			wp_send_json_error( array( 'error' => esc_html__( 'Error: Invalid term ID.', 'gallery-slider-for-woocommerce' ) ) );
		}

		$capability = apply_filters( 'wcgs_ui_permission', 'manage_options' );

		if ( ! current_user_can( $capability ) ) {
			wp_send_json_error( array( 'error' => esc_html__( 'Error: You do not have permission to do that.', 'gallery-slider-for-woocommerce' ) ) );
		}

		// Success.
		$options = WCGS_Fields::field_data( $type, $term, $query );

		wp_send_json_success( $options );
	}
	add_action( 'wp_ajax_wcgs-chosen', 'wcgs_chosen_ajax' );
}
