<?php
/**
 *
 * Field: group
 *
 * @since 1.0.0
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	die; } // Cannot access directly.

if ( ! class_exists( 'WCGS_Field_group' ) ) {
	/**
	 *
	 * Field: Group
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 */
	class WCGS_Field_group extends WCGS_Fields {
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
		 * Field render.
		 *
		 * @return void
		 */
		public function render() {

			$args = wp_parse_args(
				$this->field,
				array(
					'max'                    => 0,
					'min'                    => 0,
					'fields'                 => array(),
					'button_title'           => esc_html__( 'Add New', 'gallery-slider-for-woocommerce' ),
					'accordion_title_prefix' => '',
					'accordion_title_number' => false,
					'accordion_title_auto'   => true,
				)
			);

			$title_prefix = ( ! empty( $args['accordion_title_prefix'] ) ) ? $args['accordion_title_prefix'] : '';
			$title_number = ( ! empty( $args['accordion_title_number'] ) ) ? true : false;
			$title_auto   = ( ! empty( $args['accordion_title_auto'] ) ) ? true : false;

			if ( preg_match( '/' . preg_quote( '[' . $this->field['id'] . ']' ) . '/', $this->unique ) ) {

				echo '<div class="wcgs-notice wcgs-notice-danger">' . esc_html__( 'Error: Field ID conflict.', 'gallery-slider-for-woocommerce' ) . '</div>';

			} else {

				echo wp_kses_post( $this->field_before() );

				echo '<div class="wcgs-cloneable-item wcgs-cloneable-hidden" data-depend-id="' . esc_attr( $this->field['id'] ) . '">';

				echo '<div class="wcgs-cloneable-helper">';
				echo '<i class="wcgs-cloneable-sort sp_wgs-icon-drag-and-drop"></i>';
				echo '<i class="wcgs-cloneable-clone sp_wgs-icon-clone"></i>';
				echo '<i class="wcgs-cloneable-remove wcgs-confirm sp_wgs-icon-cancel" data-confirm="' . esc_html__( 'Are you sure to delete this item?', 'gallery-slider-for-woocommerce' ) . '"></i>';
				echo '</div>';

				echo '<h4 class="wcgs-cloneable-title">';
				echo '<span class="wcgs-cloneable-text">';
				echo ( $title_number ) ? '<span class="wcgs-cloneable-title-number"></span>' : '';
				echo ( $title_prefix ) ? '<span class="wcgs-cloneable-title-prefix">' . esc_attr( $title_prefix ) . '</span>' : '';
				echo ( $title_auto ) ? '<span class="wcgs-cloneable-value"><span class="wcgs-cloneable-placeholder"></span></span>' : '';
				echo '</span>';
				echo '</h4>';

				echo '<div class="wcgs-cloneable-content">';
				foreach ( $this->field['fields'] as $field ) {

					$field_default = ( isset( $field['default'] ) ) ? $field['default'] : '';
					$field_unique  = ( ! empty( $this->unique ) ) ? $this->unique . '[' . $this->field['id'] . '][0]' : $this->field['id'] . '[0]';
					WCGS::field( $field, $field_default, '___' . $field_unique, 'field/group' );

				}
				echo '</div>';

				echo '</div>';

				echo '<div class="wcgs-cloneable-wrapper wcgs-data-wrapper" data-title-number="' . esc_attr( $title_number ) . '" data-field-id="[' . esc_attr( $this->field['id'] ) . ']" data-max="' . esc_attr( $args['max'] ) . '" data-min="' . esc_attr( $args['min'] ) . '">';
				if ( ! empty( $this->value ) ) {

					$num = 0;

					foreach ( $this->value as $value ) {

						$first_id    = ( isset( $this->field['fields'][0]['id'] ) ) ? $this->field['fields'][0]['id'] : '';
						$first_value = ( isset( $value[ $first_id ] ) ) ? $value[ $first_id ] : '';
						$first_value = ( is_array( $first_value ) ) ? reset( $first_value ) : $first_value;

						echo '<div class="wcgs-cloneable-item">';

							echo '<div class="wcgs-cloneable-helper">';
							echo '<i class="wcgs-cloneable-sort sp_wgs-icon-drag-and-drop"></i>';
							echo '<i class="wcgs-cloneable-clone sp_wgs-icon-clone"></i>';
							echo '<i class="wcgs-cloneable-remove wcgs-confirm sp_wgs-icon-cancel" data-confirm="' . esc_html__( 'Are you sure to delete this item?', 'gallery-slider-for-woocommerce' ) . '"></i>';
							echo '</div>';

							echo '<h4 class="wcgs-cloneable-title">';
							echo '<span class="wcgs-cloneable-text">';
							echo ( $title_number ) ? '<span class="wcgs-cloneable-title-number">' . esc_attr( $num + 1 ) . '.</span>' : '';
							echo ( $title_prefix ) ? '<span class="wcgs-cloneable-title-prefix">' . esc_attr( $title_prefix ) . '</span>' : '';
							echo ( $title_auto ) ? '<span class="wcgs-cloneable-value">' . esc_attr( $first_value ) . '</span>' : '';
							echo '</span>';
							echo '</h4>';

							echo '<div class="wcgs-cloneable-content">';

						foreach ( $this->field['fields'] as $field ) {

							$field_unique = ( ! empty( $this->unique ) ) ? $this->unique . '[' . $this->field['id'] . '][' . $num . ']' : $this->field['id'] . '[' . $num . ']';
							$field_value  = ( isset( $field['id'] ) && isset( $value[ $field['id'] ] ) ) ? $value[ $field['id'] ] : '';

							WCGS::field( $field, $field_value, $field_unique, 'field/group' );

						}

						echo '</div>';

						echo '</div>';

						++$num;

					}
				}

				echo '</div>';

				echo '<div class="wcgs-cloneable-alert wcgs-cloneable-max">' . esc_html__( 'You cannot add more.', 'gallery-slider-for-woocommerce' ) . '</div>';
				echo '<div class="wcgs-cloneable-alert wcgs-cloneable-min">' . esc_html__( 'You cannot remove more.', 'gallery-slider-for-woocommerce' ) . '</div>';
				echo '<a href="#" class="button button-primary wcgs-cloneable-add">' . wp_kses_post( $args['button_title'] ) . '</a>';
				echo wp_kses_post( $this->field_after() );

			}
		}

		/**
		 * Enqueue
		 *
		 * @return void
		 */
		public function enqueue() {

			if ( ! wp_script_is( 'jquery-ui-accordion' ) ) {
				wp_enqueue_script( 'jquery-ui-accordion' );
			}

			if ( ! wp_script_is( 'jquery-ui-sortable' ) ) {
				wp_enqueue_script( 'jquery-ui-sortable' );
			}
		}
	}
}
