<?php
/**
 * Stripe webhook handler
 *
 * The signed server-to-server channel for everything that happens to a membership
 * AFTER checkout - the browser return handlers (wcfmvm-handle-stripe-sca-*.php)
 * only ever see the customer coming back from the payment page, so renewals,
 * cancellations at Stripe and checkouts the customer never returned from were
 * invisible before this endpoint existed.
 *
 * Endpoint: {membership page}?wcfmvm_process_ipn=stripe_webhook
 * Events consumed: checkout.session.completed, invoice.payment_succeeded,
 * invoice.payment_failed, customer.subscription.deleted.
 *
 * @author 		WC Lovers
 * @package 	wcfmvm/ipn
 */

class wcfmvm_stripe_webhook_handler {

	var $stripe_secret_key = '';
	var $webhook_secret = '';
	var $sandbox_mode = false;

	public function __construct() {

		$wcfm_membership_options = get_option( 'wcfm_membership_options', array() );
		$membership_payment_settings = array();
		if( isset( $wcfm_membership_options['membership_payment_settings'] ) ) $membership_payment_settings = $wcfm_membership_options['membership_payment_settings'];
		$this->sandbox_mode = isset( $membership_payment_settings['paypal_sandbox'] ) ? true : false;

		if( $this->sandbox_mode ) {
			$this->stripe_secret_key = isset( $membership_payment_settings['stripe_secret_key_test'] ) ? $membership_payment_settings['stripe_secret_key_test'] : '';
			$this->webhook_secret    = isset( $membership_payment_settings['stripe_webhook_secret_test'] ) ? $membership_payment_settings['stripe_webhook_secret_test'] : '';
		} else {
			$this->stripe_secret_key = isset( $membership_payment_settings['stripe_secret_key_live'] ) ? $membership_payment_settings['stripe_secret_key_live'] : '';
			$this->webhook_secret    = isset( $membership_payment_settings['stripe_webhook_secret_live'] ) ? $membership_payment_settings['stripe_webhook_secret_live'] : '';
		}

		$this->handle_webhook();
	}

	/**
	 * End the request with a status code - Stripe re-delivers on anything but 2xx.
	 */
	protected function respond( $code, $message ) {
		status_header( $code );
		echo esc_html( $message );
		exit;
	}

	/**
	 * Member a Stripe subscription belongs to.
	 *
	 * Resolved from the metas stored at signup: `wcfm_stripe_subscription_id`
	 * (browser return / checkout webhook) with `wcfm_subscription_profile_id`
	 * (ledger) as fallback.
	 *
	 * @param string $subscription_id Stripe subscription id.
	 * @return int Member (user) ID, 0 when the subscription is not a membership.
	 */
	protected function member_by_subscription( $subscription_id ) {
		if( !$subscription_id ) return 0;

		foreach( array( 'wcfm_stripe_subscription_id', 'wcfm_subscription_profile_id' ) as $meta_key ) {
			$members = get_users( array(
				'meta_key'    => $meta_key,
				'meta_value'  => $subscription_id,
				'number'      => 1,
				'fields'      => 'ID',
				'count_total' => false,
			) );
			if( !empty( $members ) ) return absint( $members[0] );
		}

		return 0;
	}

	/**
	 * Subscription id an invoice belongs to - the field moved between Stripe API
	 * versions, so both shapes are read.
	 */
	protected function invoice_subscription_id( $invoice ) {
		if( isset( $invoice->subscription ) && $invoice->subscription ) {
			return is_string( $invoice->subscription ) ? $invoice->subscription : $invoice->subscription->id;
		}
		if( isset( $invoice->parent->subscription_details->subscription ) && $invoice->parent->subscription_details->subscription ) {
			$subscription = $invoice->parent->subscription_details->subscription;
			return is_string( $subscription ) ? $subscription : $subscription->id;
		}
		return '';
	}

	public function handle_webhook() {
		global $WCFM, $WCFMvm;

		//Include the Stripe library.
		if( !class_exists( 'Stripe\Stripe' ) ) {
			include( $WCFMvm->plugin_path . 'includes/libs/stripe-gateway/init.php');
		}

		if( !$this->webhook_secret ) {
			wcfmvm_create_log( 'Stripe webhook received but no signing secret is configured. Set it under Membership Settings > Payment.' );
			$this->respond( 400, 'Webhook signing secret not configured.' );
		}

		$payload    = file_get_contents( 'php://input' );
		$sig_header = isset( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ? wc_clean( wp_unslash( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ) : '';

		try {
			$event = \Stripe\Webhook::constructEvent( $payload, $sig_header, $this->webhook_secret );
		} catch ( \UnexpectedValueException $e ) {
			wcfmvm_create_log( 'Stripe webhook rejected: invalid payload. ' . $e->getMessage() );
			$this->respond( 400, 'Invalid payload.' );
		} catch ( \Stripe\Exception\SignatureVerificationException $e ) {
			wcfmvm_create_log( 'Stripe webhook rejected: signature verification failed. ' . $e->getMessage() );
			$this->respond( 400, 'Invalid signature.' );
		}

		wcfmvm_create_log( 'Stripe webhook received: ' . $event->type . ' (' . $event->id . ')' );

		switch( $event->type ) {
			case 'checkout.session.completed':
				$this->handle_checkout_session_completed( $event );
			break;

			case 'invoice.payment_succeeded':
				$this->handle_invoice_payment_succeeded( $event );
			break;

			case 'invoice.payment_failed':
				$this->handle_invoice_payment_failed( $event );
			break;

			case 'customer.subscription.deleted':
				$this->handle_subscription_deleted( $event );
			break;
		}

		$this->respond( 200, 'Ignored: ' . $event->type );
	}

	/**
	 * A checkout the customer paid - whether or not they made it back to the site.
	 *
	 * The browser return handlers do this same activation when the customer comes
	 * back; before this event was consumed, a customer who paid and then closed the
	 * tab was charged but never registered. Whichever side runs first deletes
	 * `temp_wcfm_membership`, so the other becomes a no-op.
	 */
	protected function handle_checkout_session_completed( $event ) {
		global $WCFM, $WCFMvm;

		$session = $event->data->object;

		if( !in_array( $session->payment_status, array( 'paid', 'no_payment_required' ), true ) ) {
			$this->respond( 200, 'Session not paid, nothing to do.' );
		}

		// Only sessions this plugin created carry its reference:
		// 'wcfm_{hash}|{membership_id}|{member_id}' - see generate_stripe_request_form().
		// The same Stripe account may serve other checkouts (the marketplace gateway,
		// another plugin), those must pass through untouched.
		$ref_id = isset( $session->client_reference_id ) ? (string) $session->client_reference_id : '';
		$trans_info = explode( '|', $ref_id );
		if( ( count( $trans_info ) !== 3 ) || ( strpos( $trans_info[0], 'wcfm_' ) !== 0 ) ) {
			$this->respond( 200, 'Not a membership checkout session.' );
		}

		$membership_id = absint( $trans_info[1] );
		$member_id     = absint( $trans_info[2] );
		if( !$membership_id || !$member_id || ( get_post_type( $membership_id ) !== 'wcfm_memberships' ) ) {
			$this->respond( 200, 'Not a membership checkout session.' );
		}

		$temp_membership = absint( get_user_meta( $member_id, 'temp_wcfm_membership', true ) );
		if( !$temp_membership ) {
			$this->respond( 200, 'Checkout already processed.' );
		}
		if( $temp_membership !== $membership_id ) {
			wcfmvm_create_log( 'Stripe checkout session ' . $session->id . ' paid for plan ' . $membership_id . ' but member ' . $member_id . ' is registering for plan ' . $temp_membership . '. Left for manual attention.' );
			$this->respond( 200, 'Plan mismatch, left unprocessed.' );
		}

		if( !wcfmvm_claim_transaction( 'stripe', 'session_' . $session->id ) ) {
			$this->respond( 200, 'Session already claimed.' );
		}

		update_user_meta( $member_id, 'wcfm_membership_paymode', 'stripe' );

		$subscr_id = '';
		if( ( 'subscription' === $session->mode ) && isset( $session->subscription ) && $session->subscription ) {
			$subscr_id = is_string( $session->subscription ) ? $session->subscription : $session->subscription->id;
			update_user_meta( $member_id, 'wcfm_stripe_subscription_id', $subscr_id );
		}

		$required_approval = get_post_meta( $membership_id, 'required_approval', true ) ? get_post_meta( $membership_id, 'required_approval', true ) : 'no';
		if( $required_approval != 'yes' ) {
			$WCFMvm->register_vendor( $member_id );
		} else {
			$wcfm_is_send_approval_reminder_admin = get_user_meta( $member_id, 'wcfm_is_send_approval_reminder_admin', true );
			if( !$wcfm_is_send_approval_reminder_admin ) {
				$WCFMvm->send_approval_reminder_admin( $member_id );
				update_user_meta( $member_id, 'wcfm_is_send_approval_reminder_admin', 'yes' );
			}
		}

		if( $subscr_id ) {
			$customer_id = isset( $session->customer ) ? ( is_string( $session->customer ) ? $session->customer : $session->customer->id ) : '';
			$WCFMvm->store_subscription_data( $member_id, 'stripe', $customer_id, 'stripe_reccuring_subscription', 'Completed', '' );
			$WCFMvm->store_subscription_data( $member_id, 'stripe_subs', $subscr_id, 'stripe_reccuring_subscription', 'Completed', '' );
		} else {
			$payment_intent = isset( $session->payment_intent ) ? ( is_string( $session->payment_intent ) ? $session->payment_intent : $session->payment_intent->id ) : $session->id;
			$WCFMvm->store_subscription_data( $member_id, 'stripe', $payment_intent, 'stripe_subscription', 'Completed', '' );
		}

		do_action( 'wcfmvm_stripe_ipn_processed', '' );
		do_action( 'wcfmvm_payment_ipn_processed', '' );

		wcfmvm_create_log( 'Stripe checkout session ' . $session->id . ' activated membership ' . $membership_id . ' for member ' . $member_id . ' (webhook).' );
		$this->respond( 200, 'Checkout processed.' );
	}

	/**
	 * A paid subscription invoice - the renewal signal.
	 */
	protected function handle_invoice_payment_succeeded( $event ) {
		global $WCFM, $WCFMvm;

		$invoice = $event->data->object;

		$subscription_id = $this->invoice_subscription_id( $invoice );
		if( !$subscription_id ) {
			$this->respond( 200, 'Invoice has no subscription.' );
		}

		// The first invoice of a subscription belongs to checkout - activation is done
		// by checkout.session.completed (webhook or browser return), never counted as
		// a renewal.
		$billing_reason = isset( $invoice->billing_reason ) ? $invoice->billing_reason : '';
		if( 'subscription_create' === $billing_reason ) {
			$this->respond( 200, 'Initial invoice, activation handled by the checkout event.' );
		}

		$member_id = $this->member_by_subscription( $subscription_id );
		if( !$member_id ) {
			// Same Stripe account, different product (marketplace vendor subscriptions,
			// another plugin) - or a membership whose metas were lost. Log, do not fail:
			// a non 2xx would make Stripe retry forever and finally disable the endpoint.
			wcfmvm_create_log( 'Stripe invoice ' . $invoice->id . ' paid for subscription ' . $subscription_id . ' which no member is linked to. Ignored.' );
			$this->respond( 200, 'Subscription not linked to a member.' );
		}

		// The charge amount is decided by the Stripe Price/Plan the admin configured,
		// which this plugin never creates - so a difference from the site's plan price
		// is an admin configuration drift, not tampering. Surface it, do not refuse it.
		$wcfm_membership = absint( get_user_meta( $member_id, 'wcfm_membership', true ) );
		if( $wcfm_membership ) {
			$subscription = (array) get_post_meta( $wcfm_membership, 'subscription', true );
			$billing_amt  = isset( $subscription['billing_amt'] ) ? floatval( $subscription['billing_amt'] ) : 0;
			$payment_currency = apply_filters( 'wcfm_membership_payment_currency', strtoupper( get_woocommerce_currency() ) );
			$zero_cents_currency = array( 'JPY', 'MGA', 'VND', 'KRW' );
			$expected_paid = in_array( $payment_currency, $zero_cents_currency, true ) ? (int) round( wcfmvm_membership_tax_price( $billing_amt ) ) : (int) round( wcfmvm_membership_tax_price( $billing_amt ) * 100 );
			$amount_paid   = isset( $invoice->amount_paid ) ? (int) $invoice->amount_paid : 0;
			$invoice_currency = isset( $invoice->currency ) ? strtoupper( $invoice->currency ) : '';
			if( ( $expected_paid !== $amount_paid ) || ( $payment_currency !== $invoice_currency ) ) {
				wcfmvm_create_log( 'Stripe renewal amount differs from the site plan for member ' . $member_id . ': invoice ' . $invoice->id . ' paid ' . $amount_paid . ' ' . $invoice_currency . ', site plan expects ' . $expected_paid . ' ' . $payment_currency . '. Check the Stripe Plan configuration.' );
			}
		}

		// The end of the period this invoice paid for IS the next charge date.
		$next_schedule = 0;
		if( isset( $invoice->lines->data[0]->period->end ) ) {
			$next_schedule = absint( $invoice->lines->data[0]->period->end );
		}
		if( !$next_schedule && $this->stripe_secret_key ) {
			try {
				\Stripe\Stripe::setApiKey( $this->stripe_secret_key );
				$stripe_subscription = \Stripe\Subscription::retrieve( $subscription_id );
				if( isset( $stripe_subscription->current_period_end ) ) {
					$next_schedule = absint( $stripe_subscription->current_period_end );
				}
			} catch ( Exception $e ) {
				wcfmvm_create_log( 'Stripe subscription ' . $subscription_id . ' could not be retrieved for the renewal date: ' . $e->getMessage() );
			}
		}

		// One invoice is one renewal, however many deliveries and event types report it.
		if( !wcfmvm_claim_transaction( 'stripe', 'invoice_' . $invoice->id ) ) {
			$this->respond( 200, 'Invoice already claimed.' );
		}

		$details = wp_json_encode( array(
			'event'        => $event->id,
			'invoice'      => $invoice->id,
			'subscription' => $subscription_id,
			'amount_paid'  => isset( $invoice->amount_paid ) ? $invoice->amount_paid : '',
			'currency'     => isset( $invoice->currency ) ? $invoice->currency : '',
			'billing_reason' => $billing_reason,
		) );

		$renewed = $WCFMvm->wcfmvm_membership_renewal( $member_id, array(
			'paymode'             => 'stripe',
			'transaction_id'      => $invoice->id,
			'transaction_details' => $details,
			'next_schedule'       => $next_schedule,
		) );

		if( !$renewed ) {
			// Refused (no valid membership any more, plan not recurring, ...) - logged by
			// the renewal itself. Release the claim so a manual re-delivery from the
			// Stripe dashboard can be processed once the cause is fixed.
			wcfmvm_release_transaction( 'stripe', 'invoice_' . $invoice->id );
			$this->respond( 200, 'Renewal refused, see membership log.' );
		}

		do_action( 'wcfmvm_stripe_ipn_processed', '' );
		do_action( 'wcfmvm_payment_ipn_processed', '' );

		$this->respond( 200, 'Renewal processed.' );
	}

	/**
	 * A failed subscription collection - Stripe retries on its own schedule and sends
	 * customer.subscription.deleted when it finally gives up, so the membership is
	 * left untouched here.
	 */
	protected function handle_invoice_payment_failed( $event ) {
		$invoice = $event->data->object;

		$subscription_id = $this->invoice_subscription_id( $invoice );
		$member_id = $this->member_by_subscription( $subscription_id );
		if( !$member_id ) {
			$this->respond( 200, 'Subscription not linked to a member.' );
		}

		wcfmvm_create_log( 'Stripe renewal payment FAILED for member ' . $member_id . ' (invoice ' . $invoice->id . ', subscription ' . $subscription_id . '). Stripe will retry per its dunning settings.' );

		$this->respond( 200, 'Failure recorded.' );
	}

	/**
	 * The subscription ended at Stripe - cancelled there, or dunning gave up.
	 */
	protected function handle_subscription_deleted( $event ) {
		global $WCFM, $WCFMvm;

		$stripe_subscription = $event->data->object;
		$subscription_id = $stripe_subscription->id;

		$member_id = $this->member_by_subscription( $subscription_id );
		if( !$member_id ) {
			$this->respond( 200, 'Subscription not linked to a member.' );
		}

		if( !wcfmvm_claim_transaction( 'stripe', 'subdel_' . $subscription_id ) ) {
			$this->respond( 200, 'Cancellation already claimed.' );
		}

		wcfm_log( 'Subscription cancellation Stripe webhook received...' );
		$wcfm_membership_id = get_user_meta( $member_id, 'wcfm_membership', true );
		wcfm_log( 'Membership Expiry by Stripe :: ' . $member_id . ' <=> ' . $wcfm_membership_id . ' <=> ' . $event->type );

		// Ledger first, then drop the profile metas so the membership cancellation
		// below does not issue a redundant cancel call against the already deleted
		// subscription at Stripe.
		$WCFMvm->store_subscription_data( $member_id, 'stripe_subs', $subscription_id, 'subscr_cancel', 'Cancelled', '' );
		delete_user_meta( $member_id, 'wcfm_stripe_subscription_id' );
		delete_user_meta( $member_id, 'wcfm_subscription_profile_id' );
		delete_user_meta( $member_id, 'wcfm_transaction_id' );

		$WCFMvm->wcfmvm_vendor_membership_cancel( $member_id, $wcfm_membership_id );

		$this->respond( 200, 'Cancellation processed.' );
	}
}

$wcfm_stripe_webhook = new wcfmvm_stripe_webhook_handler();
