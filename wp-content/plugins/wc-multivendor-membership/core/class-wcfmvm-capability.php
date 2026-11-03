<?php









 
class WCFMvm_Capability {
	
	private $wcfm_capability_options = array();

	public function __construct() {
		global $WCFM, $WCFMvm;
		
		$this->wcfm_capability_options = apply_filters( 'wcfm_capability_options_rules', (array) get_option( 'wcfm_capability_options' ) );
		
		add_filter( 'wcfm_is_allow_membership', array( &$this, 'wcfmcap_is_allow_membership' ), 500 );		
	}
	
   
  function wcfmcap_is_allow_membership( $allow ) {
  	if( wcfm_is_vendor() ) return false;
  	$membership = ( isset( $this->wcfm_capability_options['membership'] ) ) ? $this->wcfm_capability_options['membership'] : 'no';
  	if( $membership == 'yes' ) return false;
  	return $allow;
  }
}