<?php
/**
 * My Account Referrals Tab Template.
 *
 * Available variables in scope:
 * @var string    $ref_link             User's custom referral link.
 * @var string    $referee_reward_text  Formatted text describing the referee discount.
 * @var int       $total_referred       Total referrals count.
 * @var int       $successful           Successful referrals count.
 * @var int       $pending              Pending referrals count.
 * @var array     $rewards              Array of generated coupon codes.
 * @var \WP_Query $referrals_query      WP_Query of user's referrals.
 *
 * @package Thaaniyam\ReferFriend
 * @since   1.0.0
 */

declare( strict_types=1 );

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$share_message = sprintf( __( 'Hey! Use my referral link to get %s off your purchase at Thaaniyam! %s', 'thaaniyam-refer-a-friend' ), $referee_reward_text, $ref_link );
?>
<div class="traf-myaccount-wrap">
	<div class="traf-header">
		<h2><?php esc_html_e( 'Refer a Friend & Earn', 'thaaniyam-refer-a-friend' ); ?></h2>
		<p class="traf-intro">
			<?php
			if ( ! empty( $referee_reward_text ) ) {
				printf(
					esc_html__( 'Invite your friends to shop. When they use your link, they will get %s off their first purchase, and you will receive a reward coupon once their order is completed!', 'thaaniyam-refer-a-friend' ),
					'<strong>' . esc_html( $referee_reward_text ) . '</strong>'
				);
			} else {
				esc_html_e( 'Share your unique referral link with your friends and family. You will receive a discount coupon when they complete their first order!', 'thaaniyam-refer-a-friend' );
			}
			?>
		</p>
	</div>

	<!-- Share Card -->
	<div class="traf-share-card">
		<h3><?php esc_html_e( 'Your Referral Link', 'thaaniyam-refer-a-friend' ); ?></h3>
		<div class="traf-link-box">
			<input type="text" id="traf-share-url" value="<?php echo esc_url( $ref_link ); ?>" readonly>
			<button class="traf-copy-btn" id="traf-copy-btn" data-url="<?php echo esc_url( $ref_link ); ?>">
				<span class="traf-copy-text"><?php esc_html_e( 'Copy Link', 'thaaniyam-refer-a-friend' ); ?></span>
			</button>
		</div>
		<div class="traf-copy-feedback" id="traf-copy-feedback" style="display: none;"><?php esc_html_e( 'Copied to clipboard!', 'thaaniyam-refer-a-friend' ); ?></div>

		<!-- Social Share Buttons -->
		<div class="traf-social-share">
			<span class="traf-social-label"><?php esc_html_e( 'Share via:', 'thaaniyam-refer-a-friend' ); ?></span>
			<div class="traf-social-buttons">
				<!-- WhatsApp -->
				<a href="https://api.whatsapp.com/send?text=<?php echo rawurlencode( $share_message ); ?>" target="_blank" rel="noopener" class="traf-social-icon whatsapp" title="<?php esc_attr_e( 'Share on WhatsApp', 'thaaniyam-refer-a-friend' ); ?>">
					<i class="fab fa-whatsapp"></i>
				</a>
				<!-- Instagram -->
				<a href="#" class="traf-social-icon instagram" id="traf-instagram-share-account" data-url="<?php echo esc_url( $ref_link ); ?>" title="<?php esc_attr_e( 'Share on Instagram', 'thaaniyam-refer-a-friend' ); ?>">
					<i class="fab fa-instagram"></i>
				</a>
				<!-- Email -->
				<a href="#" class="traf-social-icon email traf-email-trigger" data-product-id="0" title="<?php esc_attr_e( 'Share via Email', 'thaaniyam-refer-a-friend' ); ?>">
					<i class="fa fa-envelope"></i>
				</a>
			</div>
		</div>
	</div>

	<!-- Stats Grid -->
	<div class="traf-stats-grid">
		<div class="traf-stat-card">
			<div class="traf-stat-icon"><i class="fas fa-users"></i></div>
			<div class="traf-stat-details">
				<span class="traf-stat-num"><?php echo esc_html( (string) $total_referred ); ?></span>
				<span class="traf-stat-label"><?php esc_html_e( 'Total Referrals', 'thaaniyam-refer-a-friend' ); ?></span>
			</div>
		</div>
		<div class="traf-stat-card">
			<div class="traf-stat-icon"><i class="fas fa-shopping-bag"></i></div>
			<div class="traf-stat-details">
				<span class="traf-stat-num"><?php echo esc_html( (string) $successful ); ?></span>
				<span class="traf-stat-label"><?php esc_html_e( 'Purchases Made', 'thaaniyam-refer-a-friend' ); ?></span>
			</div>
		</div>
		<div class="traf-stat-card">
			<div class="traf-stat-icon"><i class="fas fa-ticket-alt"></i></div>
			<div class="traf-stat-details">
				<span class="traf-stat-num"><?php echo esc_html( (string) count( $rewards ) ); ?></span>
				<span class="traf-stat-label"><?php esc_html_e( 'Coupons Earned', 'thaaniyam-refer-a-friend' ); ?></span>
			</div>
		</div>
	</div>

	<!-- History Table -->
	<div class="traf-history-section">
		<h3><?php esc_html_e( 'Referral History & Rewards', 'thaaniyam-refer-a-friend' ); ?></h3>
		
		<?php if ( $referrals_query->have_posts() ) : ?>
			<div class="traf-table-wrapper">
				<table class="traf-history-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Friend', 'thaaniyam-refer-a-friend' ); ?></th>
							<th><?php esc_html_e( 'Date', 'thaaniyam-refer-a-friend' ); ?></th>
							<th><?php esc_html_e( 'Status', 'thaaniyam-refer-a-friend' ); ?></th>
							<th><?php esc_html_e( 'Your Reward Coupon', 'thaaniyam-refer-a-friend' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $referrals_query->posts as $referral_post ) :
							$post_id       = $referral_post->ID;
							$friend_email  = (string) get_post_meta( $post_id, '_referee_email', true );
							$coupon_code   = (string) get_post_meta( $post_id, '_reward_coupon_code', true );
							$reward_amount = (string) get_post_meta( $post_id, '_reward_amount', true );
							
							// Mask email for privacy (e.g. j***@example.com).
							$masked_email = '';
							if ( ! empty( $friend_email ) ) {
								$parts = explode( '@', $friend_email );
								if ( count( $parts ) === 2 ) {
									$name = $parts[0];
									$domain = $parts[1];
									$len = strlen( $name );
									$masked_name = $len > 2 ? substr( $name, 0, 1 ) . str_repeat( '*', $len - 2 ) . substr( $name, -1 ) : substr( $name, 0, 1 ) . '*';
									$masked_email = $masked_name . '@' . $domain;
								} else {
									$masked_email = $friend_email;
								}
							} else {
								$masked_email = __( 'Unknown', 'thaaniyam-refer-a-friend' );
							}

							$date = get_the_date( '', $referral_post );
							?>
							<tr>
								<td data-title="<?php esc_attr_e( 'Friend', 'thaaniyam-refer-a-friend' ); ?>">
									<strong><?php echo esc_html( $masked_email ); ?></strong>
								</td>
								<td data-title="<?php esc_attr_e( 'Date', 'thaaniyam-refer-a-friend' ); ?>">
									<?php echo esc_html( $date ); ?>
								</td>
								<td data-title="<?php esc_attr_e( 'Status', 'thaaniyam-refer-a-friend' ); ?>">
									<?php if ( $coupon_code ) : ?>
										<span class="traf-badge success"><?php esc_html_e( 'Completed', 'thaaniyam-refer-a-friend' ); ?></span>
									<?php else : ?>
										<span class="traf-badge pending"><?php esc_html_e( 'Pending Purchase', 'thaaniyam-refer-a-friend' ); ?></span>
									<?php endif; ?>
								</td>
								<td data-title="<?php esc_attr_e( 'Your Reward Coupon', 'thaaniyam-refer-a-friend' ); ?>" class="traf-reward-td">
									<?php if ( $coupon_code ) : ?>
										<div class="traf-coupon-pill">
											<code><?php echo esc_html( $coupon_code ); ?></code>
											<button class="traf-coupon-copy" data-coupon="<?php echo esc_attr( $coupon_code ); ?>" title="<?php esc_attr_e( 'Copy Coupon', 'thaaniyam-refer-a-friend' ); ?>">
												<i class="far fa-copy"></i>
											</button>
										</div>
										<span class="traf-reward-value"><?php echo esc_html( $reward_amount ); ?> <?php esc_html_e( 'off', 'thaaniyam-refer-a-friend' ); ?></span>
									<?php else : ?>
										<span class="traf-no-coupon">&mdash;</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else : ?>
			<div class="traf-empty-state">
				<div class="traf-empty-icon"><i class="far fa-smile"></i></div>
				<p><?php esc_html_e( "You haven't referred any friends yet. Share your referral link above and invite your friends to start earning!", 'thaaniyam-refer-a-friend' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</div>
