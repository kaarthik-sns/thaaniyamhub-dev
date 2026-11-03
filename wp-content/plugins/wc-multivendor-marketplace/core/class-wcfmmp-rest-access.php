<?php


















class WCFMmp_REST_Access {

	


	private $restricted_user_ids = array();

	private $withheld_capabilities = null;

	public function __construct() {
		 
		add_filter('rest_request_before_callbacks', array(&$this, 'wcfmmp_rest_dispatch_start'), 1, 3);
		add_filter('rest_request_after_callbacks', array(&$this, 'wcfmmp_rest_dispatch_end'), PHP_INT_MAX, 3);

		 
		add_filter('user_has_cap', array(&$this, 'wcfmmp_withhold_store_capabilities'), 1, 4);

		 
		add_filter('wc_shipstation_user_can_manage_wc', array(&$this, 'wcfmmp_shipstation_rest_permission'), 50);
	}

	


	public function wcfmmp_rest_dispatch_start($response, $handler, $request) {
		$this->restricted_user_ids[] = $this->wcfmmp_is_restricted_rest_request($request) ? get_current_user_id() : 0;

		return $response;
	}

	


	public function wcfmmp_rest_dispatch_end($response, $handler, $request) {
		array_pop($this->restricted_user_ids);

		return $response;
	}

	


	public function wcfmmp_is_restricted_rest_request($request) {
		if (!is_user_logged_in() || !wcfm_is_vendor()) return false;

		 
		if (array_intersect(array('administrator', 'shop_manager'), (array) wp_get_current_user()->roles)) return false;

		 
		if ($this->wcfmmp_has_backend_access()) return false;

		$route = $request->get_method() . ' ' . untrailingslashit($request->get_route());
		foreach ($this->wcfmmp_vendor_scoped_rest_routes() as $route_pattern) {
			if (preg_match($route_pattern, $route)) return false;
		}

		return true;
	}

	


	public function wcfmmp_has_backend_access() {
		 
		$capability_options = (array) apply_filters('wcfm_capability_options_rules', get_option('wcfm_capability_options', array()));

		 
		$has_backend_access = !in_array('yes', array($capability_options['vnd_wpadmin'] ?? 'no', $capability_options['ss_wpadmin'] ?? 'no'), true);

		return (bool) apply_filters('wcfmmp_rest_vendor_backend_access', $has_backend_access, get_current_user_id());
	}

	


	public function wcfmmp_vendor_scoped_rest_routes() {
		return apply_filters('wcfmmp_rest_vendor_scoped_routes', array(
			'#^[A-Z]+ /wcfmmp/v1(/|$)#',  
		));
	}

	


	private function wcfmmp_restricted_rest_user_id() {
		return (int) end($this->restricted_user_ids);
	}

	


	public function wcfmmp_withhold_store_capabilities($allcaps, $caps, $args, $user) {
		$restricted_user_id = $this->wcfmmp_restricted_rest_user_id();
		if (!$restricted_user_id || !isset($user->ID) || $restricted_user_id !== (int) $user->ID) return $allcaps;

		if (null === $this->withheld_capabilities) {
			$this->withheld_capabilities = (array) apply_filters('wcfmmp_rest_vendor_withheld_capabilities', array(
				'manage_woocommerce',          
				'view_woocommerce_reports',    
				'read_private_shop_orders',    
				'edit_others_shop_orders',
				'publish_shop_orders',         
				'list_users',                  
				'edit_users',
				'moderate_comments',           
				'manage_bookings',             
				'manage_bookings_settings',
				'manage_bookings_timezones',
				'manage_bookings_connection',
				'manage_others_appointments',  
				'manage_options',              
				'edit_others_posts',           
				'edit_others_pages',
				'read_private_posts',
				'read_private_pages',
			));
		}

		foreach ($this->withheld_capabilities as $capability) {
			$allcaps[$capability] = false;
		}

		return $allcaps;
	}

	


	public function wcfmmp_shipstation_rest_permission($permission) {
		$restricted_user_id = $this->wcfmmp_restricted_rest_user_id();
		if ($restricted_user_id && $restricted_user_id === get_current_user_id()) return false;

		return $permission;
	}
}
