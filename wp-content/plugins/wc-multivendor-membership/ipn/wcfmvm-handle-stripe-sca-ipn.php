<?php

class wcfm_stripe_sca_ipn_handler {
	
	var $stripe_published_key_live;
	var $stripe_secret_key_live;
	var $stripe_published_key_test;
	var $stripe_secret_key_test;
	var $sandbox_mode = false;
	
	public function __construct() {
			
		$wcfm_membership_options = get_option( 'wcfm_membership_options', array() );
		$membership_payment_settings = array();
		if( isset( $wcfm_membership_options['membership_payment_settings'] ) ) $membership_payment_settings = $wcfm_membership_options['membership_payment_settings'];
		$this->stripe_published_key_live = isset( $membership_payment_settings['stripe_published_key_live'] ) ? $membership_payment_settings['stripe_published_key_live'] : '';
		$this->stripe_secret_key_live = isset( $membership_payment_settings['stripe_secret_key_live'] ) ? $membership_payment_settings['stripe_secret_key_live'] : '';
		$this->stripe_published_key_test = isset( $membership_payment_settings['stripe_published_key_test'] ) ? $membership_payment_settings['stripe_published_key_test'] : '';
		$this->stripe_secret_key_test = isset( $membership_payment_settings['stripe_secret_key_test'] ) ? $membership_payment_settings['stripe_secret_key_test'] : '';
		$this->sandbox_mode = isset( $membership_payment_settings['paypal_sandbox'] ) ? true : false;
		
		$this->handle_stripe_ipn();
	}
	
	public function handle_stripe_ipn(){
		global $WCFM, $WCFMvm, $wpdb;
		
		wcfmvm_create_log("Stripe SCA Subs IPN received. Processing request...");
		
		 
		if( !class_exists( 'Stripe\Stripe' ) ) {
			include( $WCFMvm->plugin_path . 'includes/libs/stripe-gateway/init.php');
		}
		
		$ref_id = filter_input( INPUT_GET, 'ref_id', FILTER_DEFAULT );

		if ( empty( $ref_id ) ) {
			 
			wcfmvm_create_log( 'Fatal Error! No ref_id provied.' );
			wp_die( esc_html( 'Fatal Error! No ref_id provied.' ) );

		}
		
		$token = '';

		$trans_info = explode( '|', $ref_id );
		
		 
		$membership_id = isset( $trans_info[1] ) ? absint( $trans_info[1] ) : false;
		$membership_id = absint($membership_id);
		
		$member_id = isset( $trans_info[2] ) ? absint( $trans_info[2] ) : false;
		
		if( $membership_id && $member_id ) {
			 
			$button_cpt = get_post($membership_id); 
			if(!$button_cpt){
				 
				wcfmvm_create_log("Fatal Error! Failed to retrieve the membership post object for the given Membership ID: ". $membership_id);
				wp_die("Fatal Error! Membership (ID: ".$membership_id.") does not exist. This request will fail.");
			}
			
			$subscription = (array) get_post_meta( $membership_id, 'subscription', true );
			$plan_id = isset( $subscription['stripe_plan_id'] ) ? $subscription['stripe_plan_id'] : '';
			$descr = 'Subscription to "' . $plan_id . '" plan';
				
			 
			
			 
			if( $this->sandbox_mode ) {
				wcfmvm_create_log("Sandbox payment mode is enabled. Using test API key details.");
				$secret_key = $this->stripe_secret_key_test;  
			} else {
				$secret_key = $this->stripe_secret_key_live;  
			}
	
			try {
				 
				\Stripe\Stripe::setApiKey($secret_key);
	
				$events = \Stripe\Event::all(
					array(
						'type'    => 'checkout.session.completed',
						'created' => array(
							'gte' => time() - 60 * 60,
						),
					)
				);
	
				$sess = false;
	
				foreach ( $events->autoPagingIterator() as $event ) {
					$session = $event->data->object;
					if ( isset( $session->client_reference_id ) && $session->client_reference_id === $ref_id ) {
						$sess = $session;
						break;
					}
				}
	
				if ( false === $sess ) {
					 
					$error_msg = sprintf( "Fatal error! Payment with ref_id %s can't be found", $ref_id );
					wcfmvm_create_log( $error_msg );
					wp_die( esc_html( $error_msg ) );
				}
				
				$pi_id = $sess->payment_intent;

				$pi = \Stripe\PaymentIntent::retrieve( $pi_id );
	
				$charge = $pi->charges;

				 
				$txn_id = $charge->data[0]->id;
				
				
				$wcfm_membership = get_user_meta( $member_id, 'temp_wcfm_membership', true );
				if( $wcfm_membership ) {
					update_user_meta( $member_id, 'wcfm_membership_paymode', 'stripe' );
					$required_approval = get_post_meta( $wcfm_membership, 'required_approval', true ) ? get_post_meta( $wcfm_membership, 'required_approval', true ) : 'no';
					if( $required_approval != 'yes' ) {
						$WCFMvm->register_vendor( $member_id );
						$WCFMvm->store_subscription_data( $member_id, 'stripe', $txn_id, 'stripe_subscription', 'Completed', $token );
					} else {
						$WCFMvm->send_approval_reminder_admin( $member_id );
						$WCFMvm->store_subscription_data( $member_id, 'stripe', $txn_id, 'stripe_subscription', 'Completed', $token );
					}
				}
			} catch ( Exception $e ) {
				$error_msg = 'Error occurred: ' . $e->getMessage();
				wcfmvm_create_log( $error_msg );
				wp_die( esc_html( $error_msg ) );
			}
			
		}
		
		 
		if( WC()->session && WC()->session->get( 'wcfm_membership' ) ) {
			WC()->session->__unset( 'wcfm_membership' );
		}
		
		wcfmvm_create_log('Transaction data saved.');
		
		 
		do_action('wcfmvm_stripe_ipn_processed', $token);
		
		do_action('wcfmvm_payment_ipn_processed', $token);
		
		 
		$return_url = apply_filters( 'wcfm_registration_thankyou_url', add_query_arg( 'vmstep', 'thankyou', get_wcfm_membership_url() ) );
		wcfmvm_create_log("Redirecting customer to: ".$return_url);
		wcfmvm_create_log("End of Stripe Buy Now IPN processing.");
		wp_safe_redirect( $return_url );
	}
}

$wcfm_stripe_sca_buy_ipn = new wcfm_stripe_sca_ipn_handler();