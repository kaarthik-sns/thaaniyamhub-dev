<?php
/**
 * Framework image_select field.
 *
 * @package    Woo_Gallery_Slider
 * @subpackage Woo_Gallery_Slider/public
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
} // Cannot access directly.

if ( ! class_exists( 'WCGS_Field_image_select' ) ) {
	/**
	 *
	 * Field: image_select
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 */
	class WCGS_Field_image_select extends WCGS_Fields {

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
					'multiple' => false,
					'options'  => array(),
				)
			);

			$value = ( is_array( $this->value ) ) ? $this->value : array_filter( (array) $this->value );

			echo wp_kses_post( $this->field_before() );

			if ( ! empty( $args['options'] ) ) {

				echo '<div class="wcgs-siblings wcgs--image-group" data-multiple="' . esc_attr( $args['multiple'] ) . '">';

				$num = 1;

				foreach ( $args['options'] as $key => $option ) {

					$type           = ( $args['multiple'] ) ? 'checkbox' : 'radio';
					$extra          = ( $args['multiple'] ) ? '[]' : '';
					$active         = ( in_array( $key, $value ) ) ? ' wcgs--active' : '';
					$checked        = ( in_array( $key, $value ) ) ? ' checked' : '';
					$pro_only_class = isset( $option['pro_only'] ) ? ' wcgs-pro-only' : '';
					$disable_field  = ( isset( $option['pro_only'] ) && $option['pro_only'] ) ? ' disabled' : '';

					echo '<div class="wcgs--sibling wcgs--image' . esc_attr( $active . $pro_only_class ) . '">';
					echo '<div class="wcgs--image-select">';
					echo '<img src="' . esc_url( $option['image'] ) . '" alt="img-' . esc_attr( $num++ ) . '" />';
					echo '<input ' . $disable_field . ' type="' . esc_attr( $type ) . '" name="' . esc_attr( $this->field_name( $extra ) ) . '" value="' . esc_attr( $key ) . '"' . $this->field_attributes() . esc_attr( $checked ) . '/>'; // phpcs:ignore
					echo '</div>';
					// echo '<p><b>' . esc_html( $option['option_name'] ) . '</b></p>';
					if ( isset( $option['option_name'] ) && ! isset( $option['option_demo_url'] ) ) {
						echo '<p class="wcgs-img-title"><b>' . esc_html( $option['option_name'] ) . '</b></p>';
					}
					if ( isset( $option['option_demo_url'] ) ) {
						echo '<p class="wcgs-img-title"><b>' . esc_html( $option['option_name'] ) . '</b><a href="' . esc_url( $option['option_demo_url'] ) . '" tooltip="Demo" class="wcgs-live-demo-icon" target="_blank"><i class="sp_wgs-icon-external_link"></i></a></p>';
					}
					echo '</div>';
				}
				echo '</div>';
			}

			echo '<div class="clear"></div>';

			echo wp_kses_post( $this->field_after() );
		}
	}
}
