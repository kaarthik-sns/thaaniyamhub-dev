/**
 * Thaaniyam Launch Popup — Admin Script
 *
 * Handles WordPress Media Library image upload UI.
 *
 * @package Thaaniyam\LaunchPopup
 * @since   1.0.0
 */

( function ( $ ) {
	'use strict';

	// Debug: confirm script version loaded.
	console.log( '[TLP Admin] admin.js v1.0.1 loaded' );

	/**
	 * Open the WP media frame and populate target inputs on selection.
	 *
	 * @param {string} targetId  Input field ID.
	 * @param {string} previewId Preview wrapper ID.
	 */
	function openMediaFrame( targetId, previewId ) {
		var frame = wp.media( {
			title: 'Select or Upload Popup Image',
			button: {
				text: 'Use this image'
			},
			multiple: false
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();

			// Populate hidden field.
			$( '#' + targetId ).val( attachment.url );

			// Update preview.
			var $preview = $( '#' + previewId );
			$preview.html( '<img src="' + attachment.url + '" alt="Preview">' );

			// Ensure remove button is visible.
			var $removeBtn = $preview.siblings( '.tlp-image-actions' ).find( '.tlp-media-remove-btn' );
			if ( $removeBtn.length === 0 ) {
				$preview.siblings( '.tlp-image-actions' ).append(
					'<button type="button" class="button tlp-media-remove-btn" data-target="' + targetId + '" data-preview="' + previewId + '">✕ Remove</button>'
				);
			}
		} );

		frame.open();
	}

	/**
	 * Clear media values and restore placeholder.
	 *
	 * @param {string} targetId  Input field ID.
	 * @param {string} previewId Preview wrapper ID.
	 * @param {jQuery} $btn      Remove button instance.
	 */
	function removeImage( targetId, previewId, $btn ) {
		$( '#' + targetId ).val( '' );
		$( '#' + previewId ).html( '<span class="tlp-image-placeholder">No image selected</span>' );
		$btn.remove();
	}

	// -------------------------------------------------------------------------
	// Event Bindings
	// -------------------------------------------------------------------------
	$( document ).on( 'click', '.tlp-media-upload-btn', function () {
		var $btn      = $( this );
		var targetId  = $btn.data( 'target' );
		var previewId = $btn.data( 'preview' );
		openMediaFrame( targetId, previewId );
	} );

	$( document ).on( 'click', '.tlp-media-remove-btn', function () {
		var $btn      = $( this );
		var targetId  = $btn.data( 'target' );
		var previewId = $btn.data( 'preview' );
		removeImage( targetId, previewId, $btn );
	} );

	// ---
	// Multiselect dropdown logic
	// ---
	$( document ).on( 'click', '.tlp-multiselect-trigger', function ( e ) {
		e.stopPropagation();
		var $trigger  = $( this );
		var $dropdown = $trigger.siblings( '.tlp-multiselect-dropdown' );
		
		// Close other dropdowns.
		$( '.tlp-multiselect-dropdown' ).not( $dropdown ).hide();
		$dropdown.toggle();
	} );

	$( document ).on( 'click', '.tlp-multiselect-dropdown', function ( e ) {
		e.stopPropagation();
	} );

	$( document ).on( 'click', function () {
		$( '.tlp-multiselect-dropdown' ).hide();
	} );

	// Search filter.
	$( document ).on( 'keyup', '.tlp-multiselect-search', function () {
		var searchVal = $( this ).val().toLowerCase();
		var $dropdown = $( this ).closest( '.tlp-multiselect-dropdown' );
		var $options  = $dropdown.find( '.tlp-multiselect-option' );
		var visibleCount = 0;

		$options.each( function () {
			var title = $( this ).data( 'title' ) || '';
			if ( title.indexOf( searchVal ) > -1 ) {
				$( this ).show();
				visibleCount++;
			} else {
				$( this ).hide();
			}
		} );

		var $noResults = $dropdown.find( '.tlp-multiselect-no-results' );
		if ( visibleCount === 0 ) {
			if ( $noResults.length === 0 ) {
				$dropdown.find( '.tlp-multiselect-options' ).append( '<div class="tlp-multiselect-no-results">No pages found</div>' );
			}
		} else {
			$noResults.remove();
		}
	} );

	// Update trigger label.
	$( document ).on( 'change', '.tlp-multiselect-option input[type="checkbox"]', function () {
		var $dropdown    = $( this ).closest( '.tlp-multiselect-dropdown' );
		var $container   = $dropdown.closest( '.tlp-multiselect-container' );
		var $placeholder = $container.find( '.tlp-multiselect-placeholder' );
		var checkedCount = $dropdown.find( 'input[type="checkbox"]:checked' ).length;

		if ( checkedCount === 0 ) {
			$placeholder.text( 'Select pages...' );
		} else if ( checkedCount === 1 ) {
			$placeholder.text( '1 page selected' );
		} else {
			$placeholder.text( checkedCount + ' pages selected' );
		}
	} );

	// ---
	// Toggle Custom CTA URL Field
	// ---
	function toggleCtaUrlField() {
		var $ctaPage  = $( '#tlp_field_cta_page' );
		var $ctaUrlRow = $( '#tlp_field_cta_url' ).closest( 'tr' );

		if ( $ctaPage.val() === 'custom' ) {
			$ctaUrlRow.show();
		} else {
			$ctaUrlRow.hide();
		}
	}

	$( function() {
		$( '#tlp_field_cta_page' ).on( 'change', toggleCtaUrlField );
		toggleCtaUrlField(); // Run on page load
	} );

	// ---
	// Coupon Expiry — Change / Save / Cancel
	// ---

	// Show the datetime editor when "Change" is clicked.
	$( document ).on( 'click', '#tlp-edit-expiry-btn', function () {
		$( '#tlp-coupon-expiry-edit' ).show();
		$( this ).hide();
	} );

	// Hide the editor on Cancel.
	$( document ).on( 'click', '#tlp-cancel-expiry-btn', function () {
		$( '#tlp-coupon-expiry-edit' ).hide();
		$( '#tlp-edit-expiry-btn' ).show();
		$( '#tlp-expiry-success-msg' ).hide();
	} );

	// Save the new expiry date via AJAX.
	$( document ).on( 'click', '#tlp-save-expiry-btn', function () {
		var couponCode  = $( '#tlp-coupon-expiry-wrap' ).data( 'coupon' );
		var expiryDate  = $( '#tlp-coupon-expiry-input' ).val();
		var $spinner    = $( '#tlp-expiry-spinner' );
		var $successMsg = $( '#tlp-expiry-success-msg' );

		if ( ! couponCode ) {
			return;
		}

		$spinner.addClass( 'is-active' );
		$successMsg.hide();

		$.post(
			tlpAdmin.ajaxUrl,
			{
				action:      'tlp_update_coupon_expiry',
				nonce:       tlpAdmin.nonce,
				coupon_code: couponCode,
				expiry_date: expiryDate
			},
			function ( response ) {
				$spinner.removeClass( 'is-active' );
				if ( response.success ) {
					var formatted = response.data.formatted || 'No expiry date set';
					$( '#tlp-coupon-expiry-value' ).text( formatted );
					$( '#tlp-coupon-expiry-edit' ).hide();
					$( '#tlp-edit-expiry-btn' ).show();
					$successMsg.show();
					setTimeout( function () { $successMsg.hide(); }, 3000 );
				} else {
					var msg = ( response.data && response.data.message ) ? response.data.message : 'Error saving expiry.';
					alert( msg );
				}
			}
		);
	} );

	// When the coupon code select changes, fetch and refresh the expiry display.
	$( document ).on( 'change', '#tlp_field_coupon_code', function () {
		var couponCode = $( this ).val();
		var $wrap      = $( '#tlp-coupon-expiry-wrap' );

		if ( ! couponCode ) {
			$wrap.hide();
			return;
		}

		$wrap.data( 'coupon', couponCode ).show();
		$( '#tlp-coupon-expiry-edit' ).hide();
		$( '#tlp-edit-expiry-btn' ).show();
		$( '#tlp-expiry-success-msg' ).hide();

		$.post(
			tlpAdmin.ajaxUrl,
			{
				action:      'tlp_get_coupon_expiry',
				nonce:       tlpAdmin.nonce,
				coupon_code: couponCode
			},
			function ( response ) {
				if ( response.success ) {
					var formatted = response.data.formatted || 'No expiry date set';
					var raw       = response.data.raw || '';
					$( '#tlp-coupon-expiry-value' ).text( formatted );
					$( '#tlp-coupon-expiry-input' ).val( raw );
				}
			}
		);
	} );

} )( jQuery );
