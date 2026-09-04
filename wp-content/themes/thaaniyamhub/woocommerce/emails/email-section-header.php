<?php
/**
 * Shared header block for all Thaaniyam Hub emails.
 * Call with $logo_url, $badge_label, $headline, $subheadline (optional).
 *
 * @package ThaaniyamHub
 */
defined( 'ABSPATH' ) || exit;
$_logo_url    = isset( $logo_url )    ? esc_url( $logo_url )    : 'https://yme.384.myftpupload.com/wp-content/uploads/2026/01/thaaniyam-logo.png';
$_badge       = isset( $badge_label ) ? esc_html( $badge_label ) : '';
$_headline    = isset( $headline )    ? esc_html( $headline )    : '';
$_subheadline = isset( $subheadline ) ? wp_kses_post( $subheadline ) : '';
?>
        <!-- HEADER -->
        <tr>
          <td class="email-header-td" align="center" style="background-color:#edf5ee;border-radius:12px 12px 0 0;padding:24px 30px;text-align:center;" bgcolor="#edf5ee">
            <table cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;">
              <tr>
                <td align="center" style="padding-bottom:12px;text-align:center;">
                  <img class="email-logo" src="<?php echo $_logo_url; ?>" alt="Thaaniyam Hub" width="140" style="display:inline-block;margin:0 auto;max-width:140px;width:140px;height:auto;border:0;outline:none;" />
                </td>
              </tr>
              <?php if ( $_badge ) : ?>
              <tr>
                <td align="center" style="padding-bottom:12px;text-align:center;">
                  <table align="center" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin:0 auto;width:auto !important;">
                    <tr>
                      <td style="background-color:#2D6E3E;border:none;border-radius:20px;padding:8px 20px;text-align:center;" bgcolor="#2D6E3E">
                        <span style="display:inline-block;width:8px;height:8px;background-color:#6FCF97;border-radius:50%;vertical-align:middle;margin-right:8px;" bgcolor="#6FCF97">&nbsp;</span><!--
                        --><span style="color:#A8D4A8;font-size:11px;letter-spacing:2px;text-transform:uppercase;font-family:Arial,Helvetica,sans-serif;vertical-align:middle;"><?php echo $_badge; ?></span>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
              <?php endif; ?>
              <?php if ( $_headline ) : ?>
              <tr>
                <td align="center">
                  <h1 class="email-h1" style="color:#1E3A24;font-family:Arial,Helvetica,sans-serif;font-size:22px;font-weight:700;line-height:1.35;margin:0 0 6px 0;text-align:center;"><?php echo $_headline; ?></h1>
                  <?php if ( $_subheadline ) : ?>
                  <p style="color:#3A5A3A;font-size:13px;line-height:1.6;font-family:Arial,Helvetica,sans-serif;margin:0;text-align:center;"><?php echo $_subheadline; ?></p>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endif; ?>
            </table>
          </td>
        </tr>
