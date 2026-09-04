<?php
/**
 * WCFM Store New Order Email Template
 * Design matches admin-new-order.php perfectly with robust inline styles.
 * Override: yourtheme/wcfm/emails/store-new-order.php
 *
 * @author 		WC Lovers
 * @package 	wcfmmp/views/emails
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_a( $order, 'WC_Order' ) ) {
	if ( isset( $email ) && is_object( $email ) && is_a( $email->object, 'WC_Order' ) ) {
		$order = $email->object;
	} else {
		return;
	}
}

// Get line items and apply WCFM marketplace filters to display only vendor-specific products
$line_items = $order->get_items( 'line_item' );
$line_items = apply_filters( 'wcfm_valid_line_items', $line_items, $order->get_id() );

// Strict vendor item filtering to ensure vendor receives only their own products
if ( function_exists( 'wcfm_get_vendor_id_by_post' ) ) {
	$vendor_id = 0;
	if ( isset( $email ) && isset( $email->vendor_id ) && $email->vendor_id > 0 ) {
		$vendor_id = (int) $email->vendor_id;
	} elseif ( (int) $order->get_meta( '_order_vendor_id' ) > 0 ) {
		$vendor_id = (int) $order->get_meta( '_order_vendor_id' );
	} elseif ( class_exists('ThaaniyamHub_Order_Splitter') ) {
		$vendor_id = ThaaniyamHub_Order_Splitter::get_vendor_id( $order );
	}

	if ( $vendor_id > 0 ) {
		$filtered_items = array();
		foreach ( $line_items as $item_id => $item ) {
			$product_id = is_object($item) && method_exists($item, 'get_product_id') ? $item->get_product_id() : 0;
			if ( ! $product_id ) {
				continue;
			}
			$item_vendor_id = 0;
			if ( method_exists($item, 'get_meta') && $item->get_meta( '_vendor_id' ) ) {
				$item_vendor_id = (int) $item->get_meta( '_vendor_id' );
			} else {
				$item_vendor_id = (int) wcfm_get_vendor_id_by_post( $product_id );
			}
			if ( $item_vendor_id === $vendor_id ) {
				$filtered_items[$item_id] = $item;
			}
		}
		if ( ! empty( $filtered_items ) ) {
			$line_items = $filtered_items;
		}
	}
}

$order_number     = $order->get_order_number();
$order_date       = date_i18n( 'M d, Y', strtotime( $order->get_date_created() ) );
$subtotal         = $order->get_subtotal();
$discount_total   = $order->get_discount_total();
$has_discount     = $discount_total > 0;
$shipping_total   = $order->get_shipping_total();
$order_tax        = $order->get_total_tax();
$refunded_total   = $order->get_total_refunded();
$has_refund       = $refunded_total > 0;
$order_total_val  = $order->get_total();
$net_total        = $has_refund ? ( $order_total_val - $refunded_total ) : $order_total_val;
$order_total      = wc_price( $order_total_val );

$billing_address  = $order->get_formatted_billing_address();
$shipping_address = $order->get_formatted_shipping_address();
$full_name        = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
$user             = $order->get_user();
$customer_email   = ( $user && ! empty( $user->user_email ) ) ? $user->user_email : $order->get_billing_email();
$billing_email    = $customer_email;
$billing_phone    = $order->get_billing_phone();
$order_status     = wc_get_order_status_name( $order->get_status() );
$payment_method   = $order->get_payment_method_title();

// ── Discount / Coupon ──────────────────────────────────────────────────────────
$discount_total   = $order->get_discount_total();
$discount_tax     = $order->get_discount_tax();
$has_discount     = $discount_total > 0;
$coupon_codes     = array();
foreach ( $order->get_items( 'coupon' ) as $coupon_item ) {
	$coupon_codes[] = strtoupper( $coupon_item->get_code() );
}
// ──────────────────────────────────────────────────────────────────────────────

if ( function_exists( 'get_wcfm_view_order_url' ) ) {
	$view_order_url = get_wcfm_view_order_url( $order->get_id() );
} else {
	$view_order_url = admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' );
}
?>
<!-- Outer wrapper -->
<table width="100%" cellpadding="0" cellspacing="0" border="0"
	style="background:#f4f6f8; margin:0; padding:0; border-collapse:collapse;">
	<tr>
		<td align="center" style="padding:40px 20px;">

			<!-- Email card -->
			<table width="100%" cellpadding="0" cellspacing="0" border="0"
				style="max-width:650px; width:100%; background:#ffffff; border-collapse:collapse; margin:0 auto;">

				<!-- ═══ HEADER ═══ -->
				<tr>
					<td style="background:#edf5ee; border-radius:12px 12px 0 0; padding:40px 44px; text-align:center;">
						<div style="margin-bottom:22px;">
							<img src="https://yme.384.myftpupload.com/wp-content/uploads/2026/01/thaaniyam-logo.png"
								alt="Thaaniyam Hub"
								style="display:inline-block; max-width:200px; height:auto; border:0;" />
						</div>
						<div style="display:inline-block; background:#2D6E3E; border:1px solid #4A9E5C; border-radius:20px; padding:8px 20px; margin-bottom:18px;">
							<span style="display:inline-block; width:8px; height:8px; background:#6FCF97; border-radius:50%; vertical-align:middle; margin-right:8px;"></span>
							<span style="color:#A8D4A8; font-size:11px; letter-spacing:2px; text-transform:uppercase; font-family:Arial,Helvetica,sans-serif; vertical-align:middle;">New Order Received</span>
						</div>
						<h1 style="color:#1E3A24; font-family:Arial,Helvetica,sans-serif; font-size:26px; font-weight:700; line-height:1.35; margin:0 0 10px 0; text-align:center;">
							Order from <?php echo esc_html( $full_name ); ?>
						</h1>
						<p style="color:#3A5A3A; font-size:15px; line-height:1.7; font-family:Arial,Helvetica,sans-serif; margin:0;">
							Here are your order details
						</p>
					</td>
				</tr>

				<!-- ═══ META STRIP ═══ -->
				<tr>
					<td style="background:#ffffff; border-left:1px solid #E0EBD8; border-right:1px solid #E0EBD8; border-bottom:1px solid #E0EBD8; padding:0;">
						<table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin-bottom: 0px;">
							<tr>
								<td width="33%" style="padding:20px 16px; border-right:1px solid #E0EBD8; text-align:center;">
									<div style="font-size:12px; letter-spacing:2px; text-transform:uppercase; color:#8BA888; font-family:Arial,Helvetica,sans-serif; margin-bottom:6px;">Order No.</div>
									<div style="font-size:17px; font-weight:700; color:#1E4D2B; font-family:Arial,Helvetica,sans-serif;">
										#<?php echo esc_html( $order_number ); ?>
									</div>
								</td>
								<td width="34%" style="padding:20px 16px; border-right:1px solid #E0EBD8; text-align:center;">
									<div style="font-size:12px; letter-spacing:2px; text-transform:uppercase; color:#8BA888; font-family:Arial,Helvetica,sans-serif; margin-bottom:6px;">Date</div>
									<div style="font-size:14px; font-weight:700; color:#1E4D2B; font-family:Arial,Helvetica,sans-serif;"><?php echo esc_html( $order_date ); ?></div>
								</td>
								<td width="33%" style="padding:20px 16px; text-align:center;">
									<div style="font-size:12px; letter-spacing:2px; text-transform:uppercase; color:#8BA888; font-family:Arial,Helvetica,sans-serif; margin-bottom:6px;"><?php echo $has_refund ? 'Net Paid' : 'Total'; ?></div>
									<div style="font-size:17px; font-weight:700; color:#2D6E3E; font-family:Arial,Helvetica,sans-serif;"><?php echo wc_price( $net_total ); ?></div>
									<?php if ( $has_refund ) : ?>
										<div style="font-size:11px; font-weight:700; color:#C0392B; margin-top:3px; font-family:Arial,Helvetica,sans-serif;">(Refunded: -&nbsp;<?php echo wc_price( $refunded_total ); ?>)</div>
									<?php endif; ?>
								</td>
							</tr>
						</table>
					</td>
				</tr>

				<!-- ═══ ORDER ITEMS ═══ -->
				<tr>
					<td style="background:#ffffff; border-left:1px solid #E0EBD8; border-right:1px solid #E0EBD8; padding:30px 36px;">
						<div style="font-size:11px; letter-spacing:2.5px; text-transform:uppercase; color:#8BA888; font-family:Arial,Helvetica,sans-serif; margin-bottom:20px; text-align:center;">
							🛒 Order Summary<?php if ( $has_discount ) : ?>
							&nbsp;&nbsp;<span style="display:inline-block; background:#FFF3CD; border:1px solid #FFEAA7; color:#856404; border-radius:20px; padding:3px 12px; font-size:10px; letter-spacing:1.5px; font-weight:700; vertical-align:middle;">🏷️ DISCOUNT APPLIED</span>
							<?php endif; ?>
						</div>
						<table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
							<thead>
								<tr>
									<th width="68" style="padding:10px; border-bottom:2px solid #E8F0E4;"></th>
									<th style="font-size:12px; letter-spacing:2px; text-transform:uppercase; color:#8BA888; font-family:Arial,Helvetica,sans-serif; padding:10px; border-bottom:2px solid #E8F0E4; text-align:left; font-weight:600;">Product</th>
									<th width="44" style="font-size:12px; letter-spacing:2px; text-transform:uppercase; color:#8BA888; font-family:Arial,Helvetica,sans-serif; padding:10px; border-bottom:2px solid #E8F0E4; text-align:center; font-weight:600;">Qty</th>
									<th width="90" style="font-size:12px; letter-spacing:2px; text-transform:uppercase; color:#8BA888; font-family:Arial,Helvetica,sans-serif; padding:10px; border-bottom:2px solid #E8F0E4; text-align:right; font-weight:600;">Price</th>
									<th width="90" style="font-size:12px; letter-spacing:2px; text-transform:uppercase; color:#8BA888; font-family:Arial,Helvetica,sans-serif; padding:10px; border-bottom:2px solid #E8F0E4; text-align:right; font-weight:600;">Total</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $line_items as $item_id => $item ) :
									$_product = $item->get_product();
									$qty      = $item->get_quantity();
									$price    = $order->get_item_subtotal( $item, false );
									$total    = $item->get_total();
									$sku      = $_product ? $_product->get_sku() : '';
									$img_src  = '';
									if ( $_product ) {
										$img = wp_get_attachment_image_src( get_post_thumbnail_id( $_product->get_id() ), 'thumbnail' );
										if ( $img ) { $img_src = $img[0]; }
									}

									$vendor_name = '';
									if ( $_product ) {
										$product_id = $_product->get_id();
										$vendor_id  = 0;
										if ( $item->get_meta( '_vendor_id' ) ) {
											$vendor_id = (int) $item->get_meta( '_vendor_id' );
										}
										if ( ! $vendor_id && function_exists( 'wcfm_get_vendor_id_by_post' ) ) {
											$vendor_id = (int) wcfm_get_vendor_id_by_post( $product_id );
										}
										if ( ! $vendor_id ) {
											$vendor_id = (int) get_post_field( 'post_author', $product_id );
										}
										if ( $vendor_id && function_exists( 'thaaniyamhub_get_vendor_name_by_vendor_id' ) ) {
											$vendor_name = thaaniyamhub_get_vendor_name_by_vendor_id( $vendor_id );
										}
									}
								?>
								<tr>
									<td style="padding:15px 12px 15px 0; border-bottom:1px solid #F4F7F0; vertical-align:middle; width:68px;">
										<?php if ( $img_src ) : ?>
											<img src="<?php echo esc_url( $img_src ); ?>" width="56" height="56"
												alt=""
												style="display:block; width:56px; height:56px; border-radius:10px; object-fit:cover; border:0;" />
										<?php else : ?>
											<div style="width:56px; height:56px; border-radius:10px; background:#E8F0E4; text-align:center; line-height:56px; font-size:22px;">🌾</div>
										<?php endif; ?>
									</td>
									<td style="padding:15px 8px 15px 0; border-bottom:1px solid #F4F7F0; vertical-align:middle;">
										<div style="font-size:14px; color:#1E3A24; font-family:Arial,Helvetica,sans-serif; line-height:1.45;"><?php echo esc_html( $item->get_name() ); ?></div>
										<?php if ( $sku ) : ?>
											<div style="font-size:11px; color:#8BA888; font-family:Arial,Helvetica,sans-serif; margin-top:3px;">SKU: <?php echo esc_html( $sku ); ?></div>
										<?php endif; ?>
										<?php if ( ! empty( $vendor_name ) ) : ?>
											<div style="font-size:11px; color:#B33A3A; font-weight:700; font-family:Arial,Helvetica,sans-serif; margin-top:3px;">Vendor: <?php echo esc_html( $vendor_name ); ?></div>
										<?php endif; ?>
									</td>
									<td style="padding:15px 0; border-bottom:1px solid #F4F7F0; vertical-align:middle; text-align:center; width:44px; font-size:13px; color:#5A7A5A; font-family:Arial,Helvetica,sans-serif;">
										<?php echo intval( $qty ); ?>
									</td>
									<td style="padding:15px 0; border-bottom:1px solid #F4F7F0; vertical-align:middle; text-align:right; width:90px; font-size:14px; color:#8BA888; font-family:Arial,Helvetica,sans-serif; white-space:nowrap;">
										<?php echo wc_price( $price ); ?>
									</td>
									<td style="padding:15px 0; border-bottom:1px solid #F4F7F0; vertical-align:middle; text-align:right; width:90px; font-size:14px; color:#2D6E3E; font-family:Arial,Helvetica,sans-serif; white-space:nowrap; font-weight:700;">
										<?php echo wc_price( $total ); ?>
									</td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>

						<!-- Totals -->
						<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:16px; border-collapse:collapse;">
							<tr>
								<td style="padding:7px 0; color:#8BA888; font-size:13px; font-family:Arial,Helvetica,sans-serif;">Items Subtotal</td>
								<td style="padding:7px 0; text-align:right; color:#3A5A3A; font-size:13px; font-family:Arial,Helvetica,sans-serif;"><?php echo wc_price( $subtotal ); ?></td>
							</tr>

							<?php if ( $has_discount ) : ?>
							<!-- ── Discount row ── -->
							<tr>
								<td style="padding:7px 0; color:#856404; font-size:13px; font-family:Arial,Helvetica,sans-serif;">
									🏷️ Discount
									<?php if ( ! empty( $coupon_codes ) ) : ?>
										<?php foreach ( $coupon_codes as $code ) : ?>
											<span style="display:inline-block; background:#FFF3CD; border:1px solid #FFEAA7; color:#856404; border-radius:4px; padding:1px 7px; font-size:10px; font-family:Arial,Helvetica,sans-serif; font-weight:700; margin-left:4px; vertical-align:middle;"><?php echo esc_html( $code ); ?></span>
										<?php endforeach; ?>
									<?php endif; ?>
								</td>
								<td style="padding:7px 0; text-align:right; color:#C0392B; font-size:13px; font-family:Arial,Helvetica,sans-serif; font-weight:700;">
									-<?php echo wc_price( $discount_total ); ?>
								</td>
							</tr>
							<?php endif; ?>

							<tr>
								<td style="padding:7px 0; color:#8BA888; font-size:13px; font-family:Arial,Helvetica,sans-serif;">Shipping</td>
								<?php if ( $shipping_total > 0 ) : ?>
									<td style="padding:7px 0; text-align:right; color:#3A5A3A; font-size:13px; font-family:Arial,Helvetica,sans-serif;"><?php echo wc_price( $shipping_total ); ?></td>
								<?php else : ?>
									<td style="padding:7px 0; text-align:right; color:#6FCF97; font-size:13px; font-family:Arial,Helvetica,sans-serif; font-weight:700;">Free</td>
								<?php endif; ?>
							</tr>
							<?php if ( $order_tax > 0 ) : ?>
							<tr>
								<td style="padding:7px 0; color:#8BA888; font-size:13px; font-family:Arial,Helvetica,sans-serif;">Tax</td>
								<td style="padding:7px 0; text-align:right; color:#3A5A3A; font-size:13px; font-family:Arial,Helvetica,sans-serif;"><?php echo wc_price( $order_tax ); ?></td>
							</tr>
							<?php endif; ?>
							<?php if ( $has_refund ) : ?>
							<tr>
								<td style="padding:12px 0 6px; border-top:1px solid #E8F0E4; color:#5A7A5A; font-size:14px; font-family:Arial,Helvetica,sans-serif; font-weight:600;">Order Total</td>
								<td style="padding:12px 0 6px; border-top:1px solid #E8F0E4; text-align:right; color:#3A5A3A; font-size:15px; font-family:Arial,Helvetica,sans-serif; font-weight:700;"><?php echo wc_price( $order_total_val ); ?></td>
							</tr>
							<tr>
								<td style="padding:6px 0; color:#C0392B; font-size:13px; font-family:Arial,Helvetica,sans-serif; font-weight:600;">Refunded</td>
								<td style="padding:6px 0; text-align:right; color:#C0392B; font-size:14px; font-family:Arial,Helvetica,sans-serif; font-weight:700;">-<?php echo wc_price( $refunded_total ); ?></td>
							</tr>
							<tr>
								<td style="padding:12px 0 0; border-top:2px solid #1E4D2B; color:#1E3A24; font-size:16px; font-family:Arial,Helvetica,sans-serif; font-weight:700;">Net Payment</td>
								<td style="padding:12px 0 0; border-top:2px solid #1E4D2B; text-align:right; color:#1E4D2B; font-size:22px; font-family:Arial,Helvetica,sans-serif; font-weight:700;"><?php echo wc_price( $net_total ); ?></td>
							</tr>
							<?php else : ?>
							<tr>
								<td style="padding:14px 0 0; border-top:2px solid #E8F0E4; color:#1E3A24; font-size:16px; font-family:Arial,Helvetica,sans-serif; font-weight:700;">Order Total</td>
								<td style="padding:14px 0 0; border-top:2px solid #E8F0E4; text-align:right; color:#1E4D2B; font-size:22px; font-family:Arial,Helvetica,sans-serif; font-weight:700;"><?php echo wc_price( $net_total ); ?></td>
							</tr>
							<?php endif; ?>
						</table>

						<?php if ( $has_discount ) : ?>
						<!-- ── Savings callout banner ── -->
						<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:18px; border-collapse:collapse;">
							<tr>
								<td style="background:#FFF8E1; border:1px dashed #F4C842; border-radius:8px; padding:12px 18px; text-align:center;">
									<span style="font-size:13px; color:#856404; font-family:Arial,Helvetica,sans-serif; font-weight:700;">
										&#127881; Customer saved <?php echo wc_price( $discount_total ); ?> on this order!
									</span>
								</td>
							</tr>
						</table>
						<?php endif; ?>

					</td>
				</tr>

				<!-- ═══ CTA BUTTON ═══ -->
				<tr>
					<td style="text-align:center; padding:28px 36px; background:#ffffff; border-left:1px solid #E0EBD8; border-right:1px solid #E0EBD8; border-top:1px solid #F0F5EC;">
						<a href="<?php echo esc_url( $view_order_url ); ?>"
							style="display:inline-block; background:#1E4D2B; color:#ffffff; font-family:Arial,Helvetica,sans-serif; font-size:13px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; padding:15px 40px; border-radius:6px; text-decoration:none;">
							View Full Order &rarr;
						</a>
					</td>
				</tr>

				<!-- ═══ CONTACT + STATUS ═══ -->
				<tr>
					<td style="background:#ffffff; border-left:1px solid #E0EBD8; border-right:1px solid #E0EBD8; border-top:1px solid #F0F5EC; padding:30px 36px;">
						<table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
							<tr>
								<!-- Contact — right border acts as divider -->
								<td width="50%" style="padding-right:24px; vertical-align:top; border-right:1px solid #E8F0E4;">
									<div style="font-size:14px; letter-spacing:2px; text-transform:uppercase; color:#8BA888; font-family:Arial,sans-serif; margin-bottom:10px;">&#128100; Contact</div>
									<div style="font-size:13px; color:#3A5A3A; line-height:1.75; font-family:Arial,sans-serif;">
										<strong><?php echo esc_html( $full_name ); ?></strong><br>
										<a href="mailto:<?php echo esc_attr( $billing_email ); ?>"
											style="color:#2D6E3E; text-decoration:underline; font-family:Arial,Helvetica,sans-serif;"><?php echo esc_html( $billing_email ); ?></a>
									</div>
								</td>
								<!-- Status — padding-left offsets the border -->
								<td width="50%" style="padding-left:24px; vertical-align:top;">
									<div style="font-size:14px; letter-spacing:2px; text-transform:uppercase; color:#8BA888; font-family:Arial,Helvetica,sans-serif; margin-bottom:10px;">&#128230; Status &amp; Payment</div>
									<div style="font-size:13px; color:#3A5A3A; line-height:1.75; font-family:Arial,Helvetica,sans-serif;">
										<span style="display:inline-block; background:#E8F5E9; border:1px solid #C8E6C9; color:#1E4D2B; border-radius:20px; padding:4px 14px; font-size:12px; font-family:Arial,Helvetica,sans-serif; font-weight:700; margin-bottom:8px;">
											<?php echo esc_html( $order_status ); ?>
										</span><br>
										<?php echo esc_html( $payment_method ); ?>
									</div>
								</td>
							</tr>
						</table>
					</td>
				</tr>

				<!-- ═══ FOOTER ═══ -->
				<tr>
					<td style="background:#1b4332; border-radius:0 0 12px 12px; padding:25px 20px; text-align:center;">
						<p style="font-size:12px; color:#ffffff; font-family:Arial,Helvetica,sans-serif; line-height:1.9; margin:0; text-align:center;">
							&copy; <?php echo date('Y'); ?> Thaaniyam Hub &middot; All rights reserved
						</p>
					</td>
				</tr>

			</table>

		</td>
	</tr>
</table>