/**
 * Thaaniyam Refer a Friend — Frontend Interactions
 *
 * @package Thaaniyam\ReferFriend
 * @since   1.0.0
 */

jQuery(document).ready(function($) {
	
	// Reposition the Refer & Earn section dynamically to prevent button layout disruption
	function repositionReferralSection() {
		var $referBox = $('.traf-refer-inline-container');
		if (!$referBox.length) {
			return;
		}

		var $description = $('.woocommerce-product-details__short-description');
		if ($description.length) {
			$referBox.insertAfter($description);
		} else {
			var $price = $('.price').first();
			if ($price.length) {
				$referBox.insertAfter($price);
			} else {
				// Fallback: move below the buttons container to ensure it stays below the CTA line
				var $cartWrapper = $('.product-quantity-cart').last();
				if ($cartWrapper.length) {
					$referBox.insertAfter($cartWrapper);
				}
			}
		}
		// Reveal once positioned correctly
		$referBox.addClass('repositioned');
	}
	repositionReferralSection();

	// Repeat repositioning on variation events to handle WooCommerce AJAX redraws
	$(document).on('found_variation check_variations', function() {
		setTimeout(repositionReferralSection, 50);
	});
	// Open share modal
	$(document).on('click', '#traf-trigger-share-modal', function(e) {
		e.preventDefault();
		$('#traf-product-share-modal').fadeIn(200);
	});

	// Close share modal buttons
	$(document).on('click', '#traf-close-share-btn, #traf-close-share-overlay', function(e) {
		e.preventDefault();
		$('#traf-product-share-modal').fadeOut(200);
	});

	// Auto-close share modal when email invite modal is triggered
	$(document).on('click', '.traf-email-trigger', function() {
		$('#traf-product-share-modal').fadeOut(100);
	});
	
	// My Account page referral link copy button
	$('#traf-copy-btn').on('click', function(e) {
		e.preventDefault();
		var url = $(this).attr('data-url');
		copyToClipboard(url);
		
		var $feedback = $('#traf-copy-feedback');
		var $btnText = $(this).find('.traf-copy-text');
		
		$btnText.text(traf_get_copied_label());
		$feedback.stop(true, true).fadeIn(200);
		
		setTimeout(function() {
			$btnText.text(traf_get_copy_label());
			$feedback.fadeOut(500);
		}, 2000);
	});

	// Single product page referral link copy button
	$('#traf-prod-copy-btn').on('click', function(e) {
		e.preventDefault();
		var url = $(this).attr('data-url');
		copyToClipboard(url);
		
		var $feedback = $('#traf-prod-copy-success');
		var $btn = $(this);
		
		$btn.text(traf_get_copied_label());
		$feedback.stop(true, true).fadeIn(200);
		
		setTimeout(function() {
			$btn.text(traf_get_copy_label());
			$feedback.fadeOut(500);
		}, 2000);
	});

	// Single product page Instagram share copy/redirect
	$('#traf-instagram-share').on('click', function(e) {
		e.preventDefault();
		var url = $(this).attr('data-url');
		copyToClipboard(url);
		
		var $feedback = $('#traf-prod-copy-success');
		var oldText = $feedback.text();
		
		$feedback.text('Link copied! Redirecting to Instagram...');
		$feedback.stop(true, true).fadeIn(200);
		
		setTimeout(function() {
			window.open('https://www.instagram.com/', '_blank');
			$feedback.fadeOut(500, function() {
				$feedback.text(oldText);
			});
		}, 1500);
	});

	// My Account page Instagram share copy/redirect
	$('#traf-instagram-share-account').on('click', function(e) {
		e.preventDefault();
		var url = $(this).attr('data-url');
		copyToClipboard(url);
		
		var $feedback = $('#traf-copy-feedback');
		var oldText = $feedback.text();
		
		$feedback.text('Link copied! Redirecting to Instagram...');
		$feedback.stop(true, true).fadeIn(200);
		
		setTimeout(function() {
			window.open('https://www.instagram.com/', '_blank');
			$feedback.fadeOut(500, function() {
				$feedback.text(oldText);
			});
		}, 1500);
	});

	// History table coupon code copy button
	$('.traf-coupon-copy').on('click', function(e) {
		e.preventDefault();
		var coupon = $(this).attr('data-coupon');
		copyToClipboard(coupon);
		
		var $icon = $(this).find('i');
		$icon.removeClass('far fa-copy').addClass('fas fa-check').css('color', '#7A9E22');
		
		var $btn = $(this);
		setTimeout(function() {
			$icon.removeClass('fas fa-check').addClass('far fa-copy').css('color', '');
		}, 2000);
	});

	// Email share modal triggers
	$('.traf-email-trigger').on('click', function(e) {
		e.preventDefault();
		var productId = $(this).attr('data-product-id') || 0;
		$('#traf-modal-product-id').val(productId);
		
		// Reset form and alerts
		$('#traf-email-invite-form')[0].reset();
		$('#traf-modal-alert').hide().removeClass('success error').text('');
		$('#traf-modal-submit-btn').prop('disabled', false);
		$('#traf-modal-submit-btn .traf-btn-spinner').hide();
		$('#traf-modal-submit-btn .traf-btn-text').show();
		
		$('#traf-email-modal').fadeIn(200);
	});

	// Close modal button
	$('#traf-modal-close-btn').on('click', function(e) {
		e.preventDefault();
		$('#traf-email-modal').fadeOut(200);
	});

	// Close modal clicking outside card
	$('#traf-email-modal').on('click', function(e) {
		if ($(e.target).is('#traf-email-modal')) {
			$('#traf-email-modal').fadeOut(200);
		}
	});

	// Handle invite form AJAX submission
	$('#traf-email-invite-form').on('submit', function(e) {
		e.preventDefault();
		
		var $submitBtn = $('#traf-modal-submit-btn');
		var $spinner = $submitBtn.find('.traf-btn-spinner');
		var $btnText = $submitBtn.find('.traf-btn-text');
		var $alert = $('#traf-modal-alert');
		
		$submitBtn.prop('disabled', true);
		$btnText.hide();
		$spinner.show();
		$alert.hide().removeClass('success error').text('');

		$.ajax({
			url: trafConfig.ajax_url,
			type: 'POST',
			data: {
				action: 'traf_send_email_invite',
				nonce: trafConfig.nonce,
				friend_email: $('#traf-friend-email').val(),
				referrer_name: $('#traf-referrer-name').val(),
				product_id: $('#traf-modal-product-id').val(),
				custom_message: $('#traf-custom-message').val()
			},
			success: function(response) {
				if (response.success) {
					$alert.addClass('success').text(response.data.message).fadeIn(200);
					setTimeout(function() {
						$('#traf-email-modal').fadeOut(200, function() {
							$('#traf-email-invite-form')[0].reset();
							$alert.hide().text('');
							$spinner.hide();
							$btnText.show();
							$submitBtn.prop('disabled', false);
						});
					}, 2000);
				} else {
					$alert.addClass('error').text(response.data.message).fadeIn(200);
					$spinner.hide();
					$btnText.show();
					$submitBtn.prop('disabled', false);
				}
			},
			error: function() {
				$alert.addClass('error').text('An error occurred. Please try again.').fadeIn(200);
				$spinner.hide();
				$btnText.show();
				$submitBtn.prop('disabled', false);
			}
		});
	});

	// Helper function to log sharing actions in the backend
	function logShareAction(shareType) {
		var productId = $('.traf-email-trigger').attr('data-product-id') || 0;
		$.ajax({
			url: trafConfig.ajax_url,
			type: 'POST',
			data: {
				action: 'traf_log_share_action',
				nonce: trafConfig.nonce,
				share_type: shareType,
				product_id: productId
			}
		});
	}

	// Inline WhatsApp click log
	$(document).on('click', '.traf-icon-btn.whatsapp', function() {
		logShareAction('whatsapp');
	});

	// Inline Instagram share
	$(document).on('click', '#traf-instagram-share-inline', function(e) {
		e.preventDefault();
		var url = $(this).attr('data-url');
		copyToClipboard(url);
		logShareAction('instagram');
		
		var $tooltip = $('#traf-inline-tooltip-msg');
		$tooltip.text('Link copied! Opening Instagram...').stop(true, true).fadeIn(200);
		
		setTimeout(function() {
			window.open('https://www.instagram.com/', '_blank');
			$tooltip.fadeOut(500);
		}, 1500);
	});

	// Inline Copy Link button
	$(document).on('click', '#traf-copy-link-inline', function(e) {
		e.preventDefault();
		var url = $(this).attr('data-url');
		copyToClipboard(url);
		logShareAction('copy_link');
		
		var $tooltip = $('#traf-inline-tooltip-msg');
		$tooltip.text('Link copied!').stop(true, true).fadeIn(200);
		
		setTimeout(function() {
			$tooltip.fadeOut(500);
		}, 2000);
	});

	// Copy Coupon Code button
	$(document).on('click', '#traf-copy-coupon-inline', function(e) {
		e.preventDefault();
		var coupon = $(this).attr('data-coupon');
		copyToClipboard(coupon);
		
		var $tooltip = $('#traf-inline-tooltip-msg');
		$tooltip.text('Code copied!').stop(true, true).fadeIn(200);
		
		setTimeout(function() {
			$tooltip.fadeOut(500);
		}, 2000);
	});

	/**
	 * Copy text contents to clipboard using standard text selection.
	 *
	 * @param {string} text Text value to copy.
	 */
	function copyToClipboard(text) {
		var tempInput = document.createElement("input");
		tempInput.style = "position: absolute; left: -9999px; top: -9999px;";
		tempInput.value = text;
		document.body.appendChild(tempInput);
		tempInput.select();
		tempInput.setSelectionRange(0, 99999); /* For mobile devices */
		document.execCommand("copy");
		document.body.removeChild(tempInput);
	}

	/**
	 * Get translation label helper.
	 */
	function traf_get_copy_label() {
		return typeof woocommerce_params !== 'undefined' ? 'Copy' : 'Copy';
	}

	function traf_get_copied_label() {
		return typeof woocommerce_params !== 'undefined' ? 'Copied!' : 'Copied!';
	}
});
