<?php
/**
 * WCFM New Enquiry Email Template — Thaaniyam Hub
 * Design matches Thaaniyam Hub email template system
 * Override: yourtheme/wcfm/emails/new-enquiry.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;  
}

global $WCFM;
?>
<!-- Outer wrapper -->
<table width="100%" cellpadding="0" cellspacing="0" border="0"
	style="background:#f4f6f8; margin:0; padding:0; border-collapse:collapse;">
	<tr>
		<td align="center" style="padding:40px 20px;">

			<!-- Email card -->
			<table width="100%" cellpadding="0" cellspacing="0" border="0"
				style="max-width:650px; width:100%; background:#ffffff; border-collapse:collapse; margin:0 auto; border-radius:12px; border:1px solid #E0EBD8;">

				<!-- ═══ HEADER ═══ -->
				<tr>
					<td style="background:#edf5ee; border-radius:12px 12px 0 0; padding:40px 44px; text-align:center;">
						<div style="margin-bottom:22px;">
							<img src="https://yme.384.myftpupload.com/wp-content/uploads/2026/01/thaaniyam-logo.png"
								alt="Thaaniyam Hub"
								style="display:inline-block; max-width:200px; height:auto; border:0;" />
						</div>
						<!-- BADGE -->
						<div style="display:inline-block; background:#2D6E3E; border:1px solid #4A9E5C; border-radius:20px; padding:8px 20px; margin-bottom:18px;">
							<span style="display:inline-block; width:8px; height:8px; background:#6FCF97; border-radius:50%; vertical-align:middle; margin-right:8px;"></span>
							<span style="color:#A8D4A8; font-size:11px; letter-spacing:2px; text-transform:uppercase; font-family:Arial,Helvetica,sans-serif; vertical-align:middle;">Customer Inquiry</span>
						</div>
						<h1 style="color:#1E3A24; font-family:Arial,Helvetica,sans-serif; font-size:26px; font-weight:700; line-height:1.35; margin:0 0 10px 0; text-align:center;">
							New Customer Inquiry
						</h1>
						<p style="color:#3A5A3A; font-size:15px; line-height:1.7; font-family:Arial,Helvetica,sans-serif; margin:0;">
							You have received a new inquiry for <strong><?php 
								$styled_enquiry_for = preg_replace(
									'/<a /i',
									'<a style="color:#2D6E3E; font-weight:bold; text-decoration:underline;" ',
									$enquiry_for
								);
								echo wp_kses_post( $styled_enquiry_for ); 
							?></strong>
						</p>
					</td>
				</tr>

				<!-- ═══ BODY CONTENT ═══ -->
				<tr>
					<td style="background:#ffffff; border-left:1px solid #E0EBD8; border-right:1px solid #E0EBD8; padding:30px 36px;">

						<?php do_action( 'wcfm_enquiry_email_before', $enquiry_id ); ?>

						<div style="font-size:11px; letter-spacing:2.5px; text-transform:uppercase; color:#8BA888; font-family:Arial,Helvetica,sans-serif; margin-bottom:16px; text-align:center;">
							&#128172; Inquiry Message
						</div>

						<?php do_action( 'wcfm_enquiry_email_before_enquiry', $enquiry_id ); ?>

						<div style="background:#F4FAF5; border-left:4px solid #2D6E3E; border-radius:8px; padding:20px 24px; font-size:14px; color:#1E3A24; font-family:Arial,Helvetica,sans-serif; line-height:1.7; font-style:italic; margin-bottom:20px;">
							<?php echo wpautop( wptexturize( make_clickable( $enquiry ) ) ); ?>
						</div>

						<?php do_action( 'wcfm_enquiry_email_after_enquiry', $enquiry_id ); ?>

						<?php if ( ! empty( $additional_info ) ) : ?>
							<?php do_action( 'wcfm_enquiry_email_before_additonal_info', $enquiry_id ); ?>
							<div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:16px 20px; font-size:13px; color:#475569; font-family:Arial,Helvetica,sans-serif; line-height:1.6; margin-bottom:20px;">
								<strong style="color:#1E293B;">Additional Info:</strong><br>
								<?php echo wp_kses_post( wpautop( wptexturize( $additional_info ) ) ); ?>
							</div>
							<?php do_action( 'wcfm_enquiry_email_after_additonal_info', $enquiry_id ); ?>
						<?php endif; ?>

					</td>
				</tr>

				<?php if ( ! empty( $enquiry_url ) ) : ?>
				<!-- ═══ CTA BUTTON ═══ -->
				<tr>
					<td style="text-align:center; padding:10px 36px 36px 36px; background:#ffffff; border-left:1px solid #E0EBD8; border-right:1px solid #E0EBD8;">
						<a href="<?php echo esc_url( $enquiry_url ); ?>"
							style="display:inline-block; background:#1E4D2B; color:#ffffff; font-family:Arial,Helvetica,sans-serif; font-size:13px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; padding:15px 40px; border-radius:6px; text-decoration:none;">
							View &amp; Respond to Inquiry &rarr;
						</a>
					</td>
				</tr>
				<?php endif; ?>

				<?php do_action( 'wcfm_enquiry_email_after', $enquiry_id ); ?>

				<!-- ═══ FOOTER ═══ -->
				<tr>
					<td style="background:#1b4332; border-radius:0 0 12px 12px; padding:25px 20px; text-align:center;">
						<p style="font-size:12px; color:#ffffff; font-family:Arial,Helvetica,sans-serif; line-height:1.9; margin:0; text-align:center;">
							Thank you for using Thaaniyam Hub &nbsp;&middot;&nbsp; &copy; <?php echo date('Y'); ?> Thaaniyam Hub &middot; All rights reserved
						</p>
					</td>
				</tr>

			</table>
		</td>
	</tr>
</table>
