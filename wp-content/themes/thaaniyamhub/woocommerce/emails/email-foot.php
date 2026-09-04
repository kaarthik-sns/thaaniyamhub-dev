<?php
/**
 * Thaaniyam Hub — Email Foot Partial
 *
 * Closes the card table, outputs footer row, closes bodyTable, closes </html>.
 * Must be included at the bottom of every standalone email template
 * that opened with email-head.php.
 *
 * @package ThaaniyamHub
 */
defined( 'ABSPATH' ) || exit;
$_copyright = wp_kses_post( get_theme_mod( 'copyright_text', '&copy; ' . date('Y') . ' Thaaniyam Hub &middot; All rights reserved.' ) );
$_support   = esc_attr( get_theme_mod( 'support_email', 'support@thaaniyamhub.com' ) );
?>
        <!-- FOOTER -->
        <tr>
          <td class="email-footer-td" align="center" style="background-color:#1b4332;border-radius:0 0 12px 12px;padding:24px 20px;text-align:center;" bgcolor="#1b4332">
            <p style="font-size:12px;color:#ffffff;font-family:Arial,Helvetica,sans-serif;line-height:1.8;margin:0 0 6px 0;text-align:center;">
              Need help? <a href="mailto:<?php echo esc_attr( get_theme_mod( 'support_email', 'support@thaaniyamhub.com' ) ); ?>" style="color:#6FCF97;text-decoration:underline;"><?php echo esc_html( get_theme_mod( 'support_email', 'support@thaaniyamhub.com' ) ); ?></a>
            </p>
            <p style="font-size:11px;color:#9dc8a8;font-family:Arial,Helvetica,sans-serif;line-height:1.6;margin:0;text-align:center;">
              <?php echo $_copyright; ?>
            </p>
          </td>
        </tr>

      </table>
      <!--[if (gte mso 9)|(IE)]></td></tr></table><![endif]-->
    </td>
  </tr>
</table>
</body>
</html>
