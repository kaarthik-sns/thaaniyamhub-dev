<?php
/**
 * Reward Email HTML Template.
 *
 * Available variables in scope:
 * @var string $email_title   Email title/subject.
 * @var string $email_message Email HTML content with resolved placeholders.
 *
 * @package Thaaniyam\ReferFriend
 * @since   1.0.0
 */

declare( strict_types=1 );

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$logo_url = '';
$header_logo_url = get_theme_mod( 'logo' );

if ( $header_logo_url ) {
	$logo_url = $header_logo_url;
} elseif ( has_custom_logo() ) {
	$custom_logo_id = get_theme_mod( 'custom_logo' );
	$logo = wp_get_attachment_image_src( $custom_logo_id, 'full' );
	if ( $logo ) {
		$logo_url = $logo[0];
	}
} else {
	$logo_url = get_stylesheet_directory_uri() . '/assets/images/thaaniyam-logo.png';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $email_title ); ?></title>
	<style>
		/* Generic resetting for email clients */
		body {
			margin: 0;
			padding: 0;
			font-family: 'Montserrat', Helvetica, Arial, sans-serif;
			background-color: #ffffff;
			color: #2c2a29;
			-webkit-font-smoothing: antialiased;
		}
		table {
			border-collapse: collapse;
			mso-table-lspace: 0pt;
			mso-table-rspace: 0pt;
		}
		img {
			border: 0;
			height: auto;
			line-height: 100%;
			outline: none;
			text-decoration: none;
		}
	</style>
</head>
<body style="background-color: #ffffff; padding: 20px 0; margin: 0;">
	<table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #ffffff;">
		<tr>
			<td align="center">
				<!-- Content Wrapper -->
				<table width="600" border="0" cellspacing="0" cellpadding="0" style="max-width: 600px; width: 100%; background-color: #F7F3EA; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(44, 42, 41, 0.05); border: 1px solid #e2dbcd;">
					
					<!-- Header Banner -->
					<tr>
						<td align="center" style="background-color: #edf5ee; padding: 30px 40px; border-bottom: 4px solid #7A9E22;">
							<?php if ( ! empty( $logo_url ) ) : ?>
								<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" style="max-height: 70px; width: auto; display: block; border: 0; outline: none; text-decoration: none; margin: 0 auto;">
							<?php else : ?>
								<h1 style="color: #2c2a29; margin: 0; font-size: 26px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase;"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
							<?php endif; ?>
							<p style="color: #7A9E22; margin: 8px 0 0 0; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px;"><?php esc_html_e( 'Referral Rewards', 'thaaniyam-refer-a-friend' ); ?></p>
						</td>
					</tr>

					<!-- Body Content -->
					<tr>
						<td style="padding: 40px; font-size: 15px; line-height: 1.6; color: #2c2a29;">
							<h2 style="font-size: 22px; font-weight: 800; color: #2c2a29; margin-top: 0; margin-bottom: 20px; text-align: center; letter-spacing: -0.5px;"><?php echo esc_html( $email_title ); ?></h2>
							
							<div class="email-message-body" style="color: #2c2a29; margin-bottom: 30px;">
								<?php echo wp_kses_post( $email_message ); ?>
							</div>

							<hr style="border: 0; border-top: 1px solid #e2dbcd; margin: 30px 0;">

							<p style="font-size: 12px; color: #706b69; text-align: center; margin: 0;">
								<?php esc_html_e( 'Thank you for spreading the word! Keep sharing to earn more rewards.', 'thaaniyam-refer-a-friend' ); ?>
							</p>
						</td>
					</tr>

					<!-- Footer Banner -->
					<tr>
						<td align="center" style="background-color: #2c2a29; padding: 20px; font-size: 11px; color: #ffffff;">
							<p style="margin: 0; color: #ffffff;">
								&copy; <?php echo esc_html( date( 'Y' ) ); ?> <a href="https://thaaniyamhub.com" style="color: #ffffff !important; text-decoration: none !important;">thaaniyamhub.com</a>. All rights reserved.
							</p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
