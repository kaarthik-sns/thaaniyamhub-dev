<?php
/**
 * Framework help field.
 *
 * @package    Woo_Gallery_Slider
 * @subpackage Woo_Gallery_Slider/public
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
} // Cannot access directly.
if ( ! class_exists( 'WCGS_Field_sp_help_free' ) ) {
	/**
	 *
	 * Field: help
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 */
	class WCGS_Field_sp_help_free extends WCGS_Fields {

		/**
		 * Help field constructor.
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
		 * Render
		 *
		 * @return void
		 */
		public function render() {
			echo wp_kses_post( $this->field_before() );
			Woo_Gallery_Slider_Help::instance();
			echo wp_kses_post( $this->field_after() );
		}
	}
}
