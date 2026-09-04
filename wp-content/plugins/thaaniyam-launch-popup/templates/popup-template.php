<?php
/**
 * Popup template — rendered in wp_footer.
 *
 * Variables are escaped and provided by Frontend\Manager::render_popup().
 *
 * @var string $title       Popup title (kses-sanitized).
 * @var string $description Popup description (kses-sanitized).
 * @var string $coupon_code Plain text coupon code.
 * @var string $cta_text    CTA button text.
 * @var string $cta_url     CTA button target URL.
 * @var string $image_url   URL of the uploaded image.
 *
 * @package Thaaniyam\LaunchPopup
 * @since   1.0.0
 */

declare( strict_types=1 );

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- Thaaniyam Launch Popup Overlay Container -->
<div class="tlp-overlay" id="tlp-overlay" role="dialog" aria-modal="true" aria-labelledby="tlp-popup-title" aria-describedby="tlp-popup-desc" style="display: none;">
	<div class="tlp-popup" id="tlp-popup">
		<!-- Corner Ribbon 
		<div class="tlp-ribbon"><span><?php esc_html_e( 'LAUNCH OFFER', 'thaaniyam-launch-popup' ); ?></span></div>-->

		<!-- Close Button -->
		<button class="tlp-close-btn" id="tlp-close-btn" aria-label="<?php esc_attr_e( 'Close popup', 'thaaniyam-launch-popup' ); ?>">&times;</button>

		<div class="tlp-content-grid <?php echo $image_url ? 'tlp-has-image' : 'tlp-no-image'; ?>">
			
			<!-- Image Section (left side or top) -->
			<?php if ( $image_url ) : ?>
				<div class="tlp-image-section">
					<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php esc_attr_e( 'Special Launch Offer', 'thaaniyam-launch-popup' ); ?>">
				</div>
			<?php endif; ?>

			<!-- Content Section (right side or center) -->
			<div class="tlp-text-section">
				<?php if ( $title ) : ?>
					<h2 class="tlp-title" id="tlp-popup-title"><?php echo $title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
				<?php endif; ?>

				<?php if ( $description ) : ?>
					<p class="tlp-description" id="tlp-popup-desc"><?php echo $description; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
				<?php endif; ?>

				<!-- Coupon Display & Copy Section -->
				<?php if ( $coupon_code ) : ?>
					<div class="tlp-coupon-wrapper">
						<span class="tlp-coupon-tag"><?php esc_html_e( 'PROMO CODE', 'thaaniyam-launch-popup' ); ?></span>
						<div class="tlp-coupon-box">
							<span class="tlp-coupon-code" id="tlp-coupon-code"><?php echo esc_html( $coupon_code ); ?></span>
							<button class="tlp-copy-btn" id="tlp-copy-btn" data-coupon="<?php echo esc_attr( $coupon_code ); ?>" aria-label="<?php esc_attr_e( 'Copy coupon code', 'thaaniyam-launch-popup' ); ?>">
								<span class="tlp-copy-text"><?php esc_html_e( 'Copy', 'thaaniyam-launch-popup' ); ?></span>
							</button>
						</div>
						<div class="tlp-copy-success" id="tlp-copy-success" style="display: none;"><?php esc_html_e( 'Copied to clipboard!', 'thaaniyam-launch-popup' ); ?></div>
						<?php if ( ! empty( $expiry_date_formatted ) ) : ?>
							<div class="tlp-coupon-expiry-frontend">
								<?php printf( esc_html__( 'Valid until %s', 'thaaniyam-launch-popup' ), esc_html( $expiry_date_formatted ) ); ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<!-- CTA Action Button -->
				<?php if ( $cta_url && $cta_text ) : ?>
					<div class="tlp-cta-wrapper">
						<a href="<?php echo esc_url( $cta_url ); ?>" class="tlp-cta-btn" id="tlp-cta-btn">
							<?php echo esc_html( $cta_text ); ?>
						</a>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
