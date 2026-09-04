<?php
/**
 * Framework text field.
 *
 * @package    Woo_Gallery_Slider
 * @subpackage Woo_Gallery_Slider/public
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
} // Cannot access directly.

if ( ! class_exists( 'WCGS_Field_text' ) ) {
	/**
	 *
	 * Field: text
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 */
	class WCGS_Field_text extends WCGS_Fields {

		/**
		 * Field constructor.
		 *
		 * @param array  $field The field type.
		 * @param string $value The values of the field.
		 * @param string $unique The unique ID for the field.
		 * @param string $where To where show the output CSS.
		 * @param string $parent The parent args.
		 */
		public function __construct( $field, $value = '', $unique = '', $where = '', $parent = '' ) {
			parent::__construct( $field, $value, $unique, $where, $parent );
		}

		/**
		 * Render field
		 *
		 * @return void
		 */
		public function render() {
			$type = ( ! empty( $this->field['attributes']['type'] ) ) ? $this->field['attributes']['type'] : 'text';

			echo $this->field_before(); // phpcs:ignore

			$value = isset( $this->field['shortcode'] ) && ( $this->field['shortcode'] ) ? '[reno_product_gallery]' : $this->value;
			echo '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $this->field_name() ) . '" value="' . esc_attr( $value ) . '"' . $this->field_attributes() . ' />'; // phpcs:ignore

			echo $this->field_after(); // phpcs:ignore
		}
	}
}
