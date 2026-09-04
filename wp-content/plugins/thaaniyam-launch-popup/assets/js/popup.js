/**
 * Thaaniyam Launch Popup — Frontend Script
 *
 * Responsibilities:
 *  - Show popup after configured delay (localStorage gate: 7-day cooldown)
 *  - Smooth fade + scale via CSS class toggling
 *  - Copy coupon code to clipboard
 *  - Close on button, overlay click, or Escape key
 *  - "Dismiss" stores the gate key immediately
 *
 * @package Thaaniyam\LaunchPopup
 * @since   1.0.0
 */

( function () {
	'use strict';

	// -------------------------------------------------------------------------
	// Constants
	// -------------------------------------------------------------------------
	var userId       = ( window.tlpConfig && typeof tlpConfig.userId !== 'undefined' ) ? tlpConfig.userId : '0';
	var STORAGE_KEY  = 'tlp_last_shown_u' + userId; // Scoped per user so login/logout resets the gate.
	var COOLDOWN_MS  = 7 * 24 * 60 * 60 * 1000; // 7 days in ms

	// tlpConfig is localised via wp_localize_script.
	var delay           = ( window.tlpConfig && typeof tlpConfig.delay !== 'undefined' ) ? parseInt( tlpConfig.delay, 10 ) : 2;
	var couponCode      = ( window.tlpConfig && tlpConfig.couponCode ) ? tlpConfig.couponCode : '';
	var cooldownEnabled  = ( window.tlpConfig && typeof tlpConfig.cooldownEnabled !== 'undefined' ) ? tlpConfig.cooldownEnabled : true;

	// -------------------------------------------------------------------------
	// Cooldown Gates
	// -------------------------------------------------------------------------
	function shouldShow() {
		// Allow bypassing the cooldown via query parameter for testing/preview.
		if ( window.location.search.indexOf( 'tlp_preview=1' ) !== -1 ) {
			return true;
		}
		if ( ! cooldownEnabled ) {
			return true;
		}
		try {
			var lastShown = localStorage.getItem( STORAGE_KEY );
			if ( ! lastShown ) return true;
			return ( Date.now() - parseInt( lastShown, 10 ) ) > COOLDOWN_MS;
		} catch ( e ) {
			// localStorage may be disabled.
			return true;
		}
	}

	function markShown() {
		try {
			localStorage.setItem( STORAGE_KEY, Date.now().toString() );
		} catch ( e ) {
			// Fail silently if storage is full/disabled.
		}
	}

	// -------------------------------------------------------------------------
	// DOM Elements & Event Handlers
	// -------------------------------------------------------------------------
	function init() {
		var overlay  = document.getElementById( 'tlp-overlay' );
		var popup    = document.getElementById( 'tlp-popup' );
		var closeBtn = document.getElementById( 'tlp-close-btn' );
		var copyBtn  = document.getElementById( 'tlp-copy-btn' );

		if ( ! overlay || ! popup ) return;

		// Verify cooldown.
		if ( ! shouldShow() ) return;

		// Show popup after delay.
		var delayMs = isNaN( delay ) ? 2000 : delay * 1000;
		setTimeout( function () {
			showPopup( overlay, popup );
		}, delayMs );

		// Close events.
		closeBtn.addEventListener( 'click', function () {
			closePopup( overlay, popup );
		} );

		overlay.addEventListener( 'click', function ( e ) {
			if ( e.target === overlay ) {
				closePopup( overlay, popup );
			}
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && overlay.classList.contains( 'tlp-active' ) ) {
				closePopup( overlay, popup );
			}
		} );

		// Copy coupon code to clipboard.
		if ( copyBtn ) {
			copyBtn.addEventListener( 'click', function () {
				copyCoupon( copyBtn );
			} );
		}
	}

	// -------------------------------------------------------------------------
	// UI Actions
	// -------------------------------------------------------------------------
	function showPopup( overlay, popup ) {
		// Set to block, wait for browser layout reflow, then apply active class.
		overlay.style.display = 'flex';
		// Force reflow.
		void overlay.offsetWidth;

		overlay.classList.add( 'tlp-active' );
		popup.classList.add( 'tlp-active' );

		// Manage focus for accessibility.
		var focusable = popup.querySelectorAll( 'button, a, input, select, textarea' );
		if ( focusable.length > 0 ) {
			focusable[0].focus();
		}
		
		// Set shown marker on show (so it doesn't trigger on every page view even if closed).
		markShown();
	}

	// Close popup helper.
	function closePopup( overlay, popup ) {
		overlay.classList.remove( 'tlp-active' );
		popup.classList.remove( 'tlp-active' );

		// Wait for CSS transition before hiding display.
		setTimeout( function () {
			overlay.style.display = 'none';
		}, 300 );
	}

	function copyCoupon( btn ) {
		var code = btn.getAttribute( 'data-coupon' );
		if ( ! code ) return;

		var successMsg = document.getElementById( 'tlp-copy-success' );
		var btnText    = btn.querySelector( '.tlp-copy-text' );

		// Copy logic.
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( code ).then( function () {
				showCopyFeedback( successMsg, btnText );
			} ).catch( function () {
				fallbackCopy( code, successMsg, btnText );
			} );
		} else {
			fallbackCopy( code, successMsg, btnText );
		}
	}

	function fallbackCopy( code, successMsg, btnText ) {
		var textArea = document.createElement( 'textarea' );
		textArea.value = code;
		textArea.style.position = 'fixed';
		textArea.style.top      = '0';
		textArea.style.left     = '0';
		textArea.style.opacity  = '0';
		document.body.appendChild( textArea );
		textArea.focus();
		textArea.select();

		try {
			document.execCommand( 'copy' );
			showCopyFeedback( successMsg, btnText );
		} catch ( err ) {
			// Fail silently.
		}

		document.body.removeChild( textArea );
	}

	function showCopyFeedback( successMsg, btnText ) {
		if ( successMsg ) {
			successMsg.style.display = 'block';
			successMsg.style.opacity = '1';
		}
		if ( btnText ) {
			btnText.textContent = 'Copied!';
		}

		setTimeout( function () {
			if ( successMsg ) {
				successMsg.style.opacity = '0';
				setTimeout( function () {
					successMsg.style.display = 'none';
				}, 300 );
			}
			if ( btnText ) {
				btnText.textContent = 'Copy';
			}
		}, 2000 );
	}

	// Initialize on page load.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )();
