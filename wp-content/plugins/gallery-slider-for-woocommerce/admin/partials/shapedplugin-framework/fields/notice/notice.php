<?php
/**
 * Framework notice field file.
 *
 * @package    Woo_Gallery_Slider_Pro
 * @subpackage Woo_Gallery_Slider_Pro/public
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
} // Cannot access directly.

if ( ! class_exists( 'WCGS_Field_notice' ) ) {
	/**
	 * WCGS_Field_notice
	 */
	class WCGS_Field_notice extends WCGS_Fields {
		/**
		 * Field constructor.
		 *
		 * @param array  $field The field type.
		 * @param string $value The values of the field.
		 * @param string $unique The unique ID for the field.
		 * @param string $where To where show the output CSS.
		 * @param string $parent The parent args.
		 * @return void
		 */
		public function __construct( $field, $value = '', $unique = '', $where = '', $parent = '' ) {
			parent::__construct( $field, $value, $unique, $where, $parent );
		}

		/**
		 * Render
		 *
		 * @return void
		 */
		public function render() {

			$style = ( ! empty( $this->field['style'] ) ) ? $this->field['style'] : 'normal';

			echo ( ! empty( $this->field['content'] ) ) ? '<div class="wcgs-notice wcgs-notice-' . esc_attr( $style ) . '">' . wp_kses_post( $this->field['content'] ) . '</div>' : '';
		}
	}
}
