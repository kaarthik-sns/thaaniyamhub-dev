<?php
/**
 * Framework radio field.
 *
 * @package    Woo_Gallery_Slider
 * @subpackage Woo_Gallery_Slider/public
 */

if ( ! defined( 'ABSPATH' ) ) {
	die; } // Cannot access directly.

if ( ! class_exists( 'WCGS_Field_radio' ) ) {
	/**
	 *
	 * Field: radio
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 */
	class WCGS_Field_radio extends WCGS_Fields {

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

			$args = wp_parse_args(
				$this->field,
				array(
					'inline' => false,
				)
			);

			$inline_class = ( $args['inline'] ) ? ' class="wcgs--inline-list"' : '';

			echo wp_kses_post( $this->field_before() );

			if ( isset( $this->field['options'] ) ) {

				$options = $this->field['options'];
				$options = ( is_array( $options ) ) ? $options : array_filter( $this->field_data( $options ) );
				if ( is_array( $options ) && ! empty( $options ) ) {

					echo '<ul' . wp_kses_post( $inline_class ) . '>';
					foreach ( $options as $option_key => $option_value ) {
						$disabled = '';
						if ( is_array( $option_value ) && ! empty( $option_value ) ) {
							$disabled = isset( $option_value['pro_only'] ) && $option_value['pro_only'] ? 'disabled' : '';
							$checked  = ( $option_key === $this->value ) ? ' checked' : '';
							echo '<li><label class="' . esc_attr( $disabled  ) . '" ><input type="radio" ' . esc_attr( $disabled  ) . ' name="' . esc_attr( $this->field_name() ) . '" value="' . esc_attr( $option_key ) . '"' . $this->field_attributes() . esc_attr( $checked ) . '/> <span>' . esc_html( isset( $option_value['option_name'] ) ? $option_value['option_name'] : '' ) . '</span></label></li>'; // phpcs:ignore
						} else {
							$checked = ( $option_key === $this->value ) ? ' checked' : '';
							echo '<li><label><input type="radio" name="' . esc_attr( $this->field_name() ) . '" value="' . esc_attr( $option_key ) . '"' . $this->field_attributes() . esc_attr( $checked ) . '/> <span>' . esc_html( $option_value ) . '<span></label></li>'; // phpcs:ignore
						}
					}
					echo '</ul>';

				} else {

					echo ! empty( $this->field['empty_message'] ) ? esc_html( $this->field['empty_message'] ) : esc_html__( 'No data provided for this option type.', 'gallery-slider-for-woocommerce' );
				}
			} else {
				$label = ( isset( $this->field['label'] ) ) ? $this->field['label'] : '';
				echo '<label><input type="radio" name="' . esc_attr( $this->field_name() ) . '" value="1"' . $this->field_attributes() . checked( $this->value, 1, false ) . '/> ' . esc_html( $label ) . '</label>'; // phpcs:ignore
			}

			echo wp_kses_post( $this->field_after() );
		}
	}
}
