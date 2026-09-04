<?php
/**
 * Email Header
 *
 * @package WooCommerce/Templates/Emails
 * @version 10.7.0
 */

defined( 'ABSPATH' ) || exit;
?>
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5; padding:20px 0;">
    <tr>
        <td align="center">

            <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:6px; overflow:hidden;">
                
                <!-- Logo -->
                <tr>
                    <td style="text-align:center; padding:20px 20px 10px;">
                        <img src="https://yme.384.myftpupload.com/wp-content/uploads/2026/01/thaaniyam-logo.png" 
                             width="120" 
                             alt="Thaaniyam Hub"
                             style="display:block; margin:0 auto;">
                    </td>
                </tr>

                <!-- Divider -->
                <tr>
                    <td style="border-top:1px solid #eeeeee;"></td>
                </tr>

                <!-- Heading -->
                <tr>
                    <td style="text-align:center; padding:15px 20px;">
                        <h2 style="margin:0; font-size:20px; color:#333;text-align: center;">
                            <?php echo esc_html( $email_heading ); ?>
                        </h2>
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>