<?php

namespace DeliciousBrains\WPMDB\Common\Http;

use DeliciousBrains\WPMDB\Common\Properties\Properties;
use DeliciousBrains\WPMDB\Common\Util\Util;

class WPMDBRestAPIServer {

	/**
	 * @var Properties
	 */
	private $props;

	public function __construct(
		Properties $props
	) {
		$this->props = $props;
	}

	public function permission_callback() {
		$cap = Util::get_ajax_cap();

		// Restrict endpoint to users who have the required capability.
		if ( ! current_user_can( $cap ) ) {
			return new \WP_Error( 'rest_forbidden', esc_html__( 'Only authenticated users can access endpoint.', 'wp-migrate-db' ), [ 'status' => 401 ] );
		}

		return true;
	}

	public function registerRestRoute( $end_point, $args ) {
		if ( ! isset( $args['permissions_callback'] ) ) {
			$args['permission_callback'] = [ $this, 'permission_callback' ];
		}

		register_rest_route( $this->props->rest_api_base, $end_point, $args );
	}
}

