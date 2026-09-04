<?php
/**
 * Thaaniyam Hub — Email Head Partial
 *
 * Outputs a complete <!DOCTYPE html> + <head> block with:
 *  - Proper email client resets
 *  - Minimal <style> block for media query enhancement (non-layout)
 *  - MSO conditional comments for Outlook
 *  - Viewport meta for iOS/Android
 *
 * Include at the very top of every standalone email template.
 * Usage: include( get_stylesheet_directory() . '/woocommerce/emails/email-head.php' );
 *
 * @package ThaaniyamHub
 */
defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="x-apple-disable-message-reformatting" />
<meta name="format-detection" content="telephone=no, date=no, address=no, email=no" />
<title>Thaaniyam Hub</title>
<!--[if mso]>
<noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
<![endif]-->
<style type="text/css">
body, #bodyTable { margin:0 !important; padding:0 !important; width:100% !important; }
body { background-color:#f4f6f8 !important; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%; }
img { border:0; outline:none; text-decoration:none; -ms-interpolation-mode:bicubic; display:block; }
table { border-collapse:collapse !important; mso-table-lspace:0pt; mso-table-rspace:0pt; }
a { text-decoration:none; }
/* Cosmetic progressive enhancement — layout never depends on these */
@media screen and (max-width:600px) {
  .email-card       { width:100% !important; }
  .email-header-td  { padding:20px 16px !important; }
  .email-body-td    { padding:20px 16px !important; }
  .email-section-td { padding:18px 16px !important; }
  .email-footer-td  { padding:20px 16px !important; }
  .email-logo       { max-width:110px !important; width:110px !important; }
  h1.email-h1       { font-size:20px !important; }
  .order-items-table td,
  .order-items-table th { padding:10px 6px !important; font-size:12px !important; }
}
</style>
</head>
<body style="margin:0;padding:0;background-color:#f4f6f8;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;" bgcolor="#f4f6f8">
<table id="bodyTable" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0;padding:0;background-color:#f4f6f8;border-collapse:collapse;" bgcolor="#f4f6f8">
  <tr>
    <td align="center" valign="top" style="padding:30px 10px;">
      <!--[if (gte mso 9)|(IE)]><table width="600" align="center" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
      <table class="email-card" align="center" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;width:100%;background-color:#ffffff;border-collapse:collapse;border-radius:12px;border:1px solid #E0EBD8;mso-table-lspace:0pt;mso-table-rspace:0pt;">
