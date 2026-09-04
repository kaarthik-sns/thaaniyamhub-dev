<?php
/**
 * Framework migration button field.
 *
 * @package    Woo_Gallery_Slider
 * @subpackage Woo_Gallery_Slider/admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	die; // Cannot access directly.
}

if ( ! class_exists( 'WCGS_Field_migration' ) ) {

	/**
	 * Field: migration
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 */
	class WCGS_Field_migration extends WCGS_Fields {

		/**
		 * Constructor
		 *
		 * @param array  $field The field type.
		 * @param string $value The value of the field.
		 * @param string $unique The unique ID.
		 * @param string $where Where to output CSS.
		 * @param string $parent Parent args.
		 */
		public function __construct( $field, $value = '', $unique = '', $where = '', $parent = '' ) {
			parent::__construct( $field, $value, $unique, $where, $parent );
		}

		/**
		 * Render the migration button
		 */
		public function render() {
			echo wp_kses_post( $this->field_before() );

			$button_text = ! empty( $this->field['button_text'] ) ? esc_html( $this->field['button_text'] ) : __( 'Run Migration', 'gallery-slider-for-woocommerce' );
			$button_id   = ! empty( $this->field['button_id'] ) ? esc_attr( $this->field['button_id'] ) : 'wcgs-migration-btn';
			echo '<button type="button" id="' . $button_id . '" class="button button-primary">' . $button_text . '</button>'; // phpcs:ignore -- Escaping are handled above using esc_html and esc_attr functions.

			echo '<div id="wcgs-migration-result" style="margin-top: 10px;"></div>';
			echo wp_kses_post( $this->field_after() );
		}
	}
}
