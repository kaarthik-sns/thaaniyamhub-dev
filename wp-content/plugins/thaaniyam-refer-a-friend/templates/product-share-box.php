<?php
/**
 * Single Product Share Box Template.
 *
 * Available variables in scope:
 * @var bool   $is_logged_in  True if customer is logged in.
 * @var string $ref_link      Referral link (logged-in) or My Account link (guest).
 * @var string $reward_text   Formatted friend discount amount (e.g. 10% or $10.00).
 *
 * @package Thaaniyam\ReferFriend
 * @since   1.0.0
 */

declare( strict_types=1 );

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$product_name = get_the_title();
if ( ! empty( $reward_text ) ) {
	if ( ! empty( $sharing_coupon ) ) {
		$share_message = sprintf( __( 'Check out this awesome product: %s! Use my coupon code %s to get %s off: %s', 'thaaniyam-refer-a-friend' ), $product_name, $sharing_coupon, $reward_text, $ref_link );
	} else {
		$share_message = sprintf( __( 'Check out this awesome product: %s! Use my referral link to get %s off: %s', 'thaaniyam-refer-a-friend' ), $product_name, $reward_text, $ref_link );
	}
} else {
	$share_message = sprintf( __( 'Check out this awesome product: %s! Use my link to view it: %s', 'thaaniyam-refer-a-friend' ), $product_name, $ref_link );
}
?>
<div class="traf-refer-inline-container">
	<span class="traf-refer-label"><?php esc_html_e( 'Refer & Earn:', 'thaaniyam-refer-a-friend' ); ?></span>
	
	<?php if ( $is_logged_in ) : ?>
		<?php if ( ! empty( $sharing_coupon ) ) : ?>
			<span class="traf-coupon-code-badge" id="traf-copy-coupon-inline" data-coupon="<?php echo esc_attr( $sharing_coupon ); ?>" title="<?php esc_attr_e( 'Click to copy coupon code', 'thaaniyam-refer-a-friend' ); ?>"><?php echo esc_html( $sharing_coupon ); ?></span>
		<?php endif; ?>
		<div class="traf-refer-icons-row">
			<a href="https://api.whatsapp.com/send?text=<?php echo rawurlencode( $share_message ); ?>" target="_blank" rel="noopener" class="traf-icon-btn whatsapp" title="<?php esc_attr_e( 'Share via WhatsApp', 'thaaniyam-refer-a-friend' ); ?>">
				<i class="fab fa-whatsapp"></i>
			</a>
			<a href="#" class="traf-icon-btn instagram" id="traf-instagram-share-inline" data-url="<?php echo esc_url( $ref_link ); ?>" title="<?php esc_attr_e( 'Share via Instagram', 'thaaniyam-refer-a-friend' ); ?>">
				<i class="fab fa-instagram"></i>
			</a>
			<a href="#" class="traf-icon-btn email traf-email-trigger" data-product-id="<?php echo esc_attr( get_the_ID() ); ?>" title="<?php esc_attr_e( 'Share via Email', 'thaaniyam-refer-a-friend' ); ?>">
				<i class="fa fa-envelope"></i>
			</a>
			<a href="#" class="traf-icon-btn copy-link" id="traf-copy-link-inline" data-url="<?php echo esc_url( $ref_link ); ?>" title="<?php esc_attr_e( 'Copy Referral Link', 'thaaniyam-refer-a-friend' ); ?>">
				<i class="fas fa-link"></i>
			</a>
			<span class="traf-inline-tooltip" id="traf-inline-tooltip-msg" style="display: none;"><?php esc_html_e( 'Copied!', 'thaaniyam-refer-a-friend' ); ?></span>
		</div>
	<?php else : ?>
		<a href="<?php echo esc_url( $ref_link ); ?>" class="traf-login-link-inline" title="<?php esc_attr_e( 'Login to Refer & Earn', 'thaaniyam-refer-a-friend' ); ?>">
			<?php esc_html_e( 'Login to Refer', 'thaaniyam-refer-a-friend' ); ?>
		</a>
	<?php endif; ?>
</div>
