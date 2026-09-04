<?php
/**
 * Framework color_group field.
 *
 * @package    Woo_Gallery_Slider
 * @subpackage Woo_Gallery_Slider/public
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
} // Cannot access directly.

if ( ! class_exists( 'WCGS_Field_dimensions' ) ) {
	/**
	 *
	 * Field: dimensions
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 */
	if ( ! class_exists( 'WCGS_Field_dimensions' ) ) {
		/**
		 *
		 * Field: dimensions
		 *
		 * @since 1.0.0
		 * @version 1.0.0
		 */
		class WCGS_Field_dimensions extends WCGS_Fields {

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
						'width_icon'         => '<i class="sp_wgs-icon-resize-horizontal"></i>',
						'height_icon'        => '<i class="sp_wgs-icon-resize-vertical"></i>',
						'width_placeholder'  => '',
						'width_text'         => '',
						'height_placeholder' => '',
						'height_text'        => '',
						'width'              => true,
						'width_unit'         => false,
						'height_unit'        => false,
						'height'             => true,
						'unit'               => true,
						'show_units'         => true,
						'units'              => array( 'px', '%', 'em' ),
					)
				);

				$default_values = array(
					'width'       => '',
					'height'      => '',
					'unit'        => 'px',
					'width_unit'  => 'px',
					'height_unit' => '%',
				);
				$value          = wp_parse_args( $this->value, $default_values );
				echo wp_kses_post( $this->field_before() );
				echo '<div class="wcgs--inputs">';
				if ( ! empty( $args['width'] ) ) {
					$placeholder = ( ! empty( $args['width_placeholder'] ) ) ? ' placeholder="' . $args['width_placeholder'] . '"' : '';
					echo '<div class="wcgs--input">';
					echo ! empty( $args['width_text'] ) ? '<div class="wcgs--title">' . wp_kses_post( $args['width_text'] ) . '</div>' : '';
					echo ( ! empty( $args['width_icon'] ) ) ? '<span class="wcgs--label wcgs--label-icon">' . wp_kses_post( $args['width_icon'] ) . '</span>' : '';
					echo '<input type="number" name="' . esc_attr( $this->field_name( '[width]' ) ) . '" value="' . esc_attr( $value['width'] ) . '"' . wp_kses_post( $placeholder ) . ' class="wcgs-number wcgs--is-unit" />';
					echo ( count( $args['units'] ) === 1 && ! empty( $args['unit'] ) ) ? '<span class="wcgs--label wcgs--label-unit">' . esc_html( $args['units'][0] ) . '</span>' : '';
					if ( ! empty( $args['width_unit'] ) && ! empty( $args['show_units'] ) && count( $args['units'] ) > 1 ) {
						echo '<select name="' . esc_attr( $this->field_name( '[width_unit]' ) ) . '">';
						foreach ( $args['units'] as $unit ) {
							$selected = ( $value['width_unit'] === $unit ) ? ' selected' : '';
							echo '<option value="' . esc_attr( $unit ) . '"' . esc_attr( $selected ) . '>' . esc_html( $unit ) . '</option>';
						}
						echo '</select>';
					}
					echo '</div>';
				}

				if ( ! empty( $args['height'] ) ) {
					$placeholder = ( ! empty( $args['height_placeholder'] ) ) ? ' placeholder="' . $args['height_placeholder'] . '"' : '';
					echo '<div class="wcgs--input">';
					echo ( ! empty( $args['height_text'] ) ) ? '<div class="wcgs--title">' . wp_kses_post( $args['height_text'] ) . '</div>' : '';
					echo ( ! empty( $args['height_icon'] ) ) ? '<span class="wcgs--label wcgs--label-icon">' . wp_kses_post( $args['height_icon'] ) . '</span>' : '';
					echo '<input type="number" name="' . esc_attr( $this->field_name( '[height]' ) ) . '" value="' . esc_attr( $value['height'] ) . '"' . wp_kses_post( $placeholder ) . ' class="wcgs-number wcgs--is-unit" />';
					echo ( count( $args['units'] ) === 1 && ! empty( $args['unit'] ) ) ? '<span class="wcgs--label wcgs--label-unit">' . esc_html( $args['units'][0] ) . '</span>' : '';
					if ( ! empty( $args['height_unit'] ) && ! empty( $args['show_units'] ) && count( $args['units'] ) > 1 ) {
						echo '<select name="' . esc_attr( $this->field_name( '[height_unit]' ) ) . '">';
						foreach ( $args['units'] as $unit ) {
							$selected = ( $value['height_unit'] === $unit ) ? ' selected' : '';
							echo '<option value="' . esc_attr( $unit ) . '"' . esc_attr( $selected ) . '>' . esc_html( $unit ) . '</option>';
						}
						echo '</select>';
					}
					echo '</div>';
				}
				echo '</div>';
				if ( ! empty( $args['unit'] ) && ! empty( $args['show_units'] ) && count( $args['units'] ) > 1 ) {
					echo '<select name="' . esc_attr( $this->field_name( '[unit]' ) ) . '">';
					foreach ( $args['units'] as $unit ) {
						$selected = ( $value['unit'] === $unit ) ? ' selected' : '';
						echo '<option value="' . esc_attr( $unit ) . '"' . esc_attr( $selected ) . '>' . esc_html( $unit ) . '</option>';
					}
					echo '</select>';
				}
				echo '<div class="clear"></div>';
				echo wp_kses_post( $this->field_after() );
			}
		}
	}
}
