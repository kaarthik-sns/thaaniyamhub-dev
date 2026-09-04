<?php
/**
 * Email Footer
 *
 * @package WooCommerce/Templates/Emails
 * @version 10.4.0
 */

defined('ABSPATH') || exit;
?>

<p style="text-align:center; font-size:13px; color:#777; line-height:1.6; margin-top:20px;">
    <?php echo wp_kses_post( get_theme_mod( 'copyright_text', '&copy; ' . date('Y') . ' Thaaniyam Hub &middot; All rights reserved.' ) ); ?>
</p>