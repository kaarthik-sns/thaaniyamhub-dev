=== Export Media as ZIP ===
Contributors: huzoorbakhsh, freemius
Tags: media, export, zip, download images, backup
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Export images from your WordPress media library as a ZIP file — filter by year and image size before downloading. Premium adds documents, background export, and scheduled exports.

== Description ==

**Export Media as ZIP** is a lightweight plugin that lets administrators export images from the WordPress media library as a ZIP file. Choose exactly what to download using the year and image size filters before exporting.

= Key Features =
* Export JPG, PNG, GIF, JPEG, and WEBP images
* **Filter by year** — select one or more upload years to narrow the export
* **Filter by image size** — choose from Full Size (original) or any intermediate size registered by WordPress core, your active theme, or plugins (e.g. Thumbnail, Medium, Large, custom sizes)
* Live export preview shows how many images match the current filters before you start
* Real-time progress bar during export
* Auto-expiring ZIP file — cleaned up automatically after 5 minutes
* Admin-only access
* No external dependencies

= Premium Features =
* **Document export** — include PDF, Word (doc/docx), Excel (xls/xlsx), and PowerPoint (ppt/pptx) files alongside images
* **Background export** — large exports run via WP-Cron in chunks instead of a single blocking request, so they don't time out; you get an email with the download link when the ZIP is ready
* **Scheduled export** — configure a recurring (daily, weekly, or monthly) export with your preferred filters; each run emails a fresh download link automatically

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/export-media-as-zip` or install via the WordPress Plugin Directory.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Media > Export Media as ZIP**.
4. Use the **Year** and **Image Size** dropdowns to select what to export.
5. Click **Export Images** and download the generated ZIP.

== Frequently Asked Questions ==

= Who can access the export feature? =
Only users with the `manage_options` capability (administrators) can access the export page.

= What file types are included? =
Only image files — `.jpg`, `.jpeg`, `.png`, `.gif`, and `.webp`.

= What image sizes can I export? =
You can export the original uploaded file (Full Size) and any intermediate size WordPress has generated for that image. The available sizes depend on your active theme and plugins. The plugin uses `wp_get_registered_image_subsizes()` to list all registered sizes automatically.

= Can I export images from a specific year only? =
Yes. The Year filter shows all years that have image uploads, with a count per year. Select one or more years before exporting.

= Where is the ZIP file stored? =
The ZIP is written temporarily to your uploads directory and deleted automatically after 5 minutes via a scheduled WP-Cron event.

= Will it export every size variant of every image? =
Only sizes you check in the Image Size dropdown are included. If an intermediate size was never generated for a particular image, that file is silently skipped.

= What document types can Premium export? =
PDF, Word (.doc/.docx), Excel (.xls/.xlsx), and PowerPoint (.ppt/.pptx). Documents are always included at their original file — the Image Size filter only affects images.

= How does background export work? =
Premium users can check "Run in background" before exporting. The export is queued and processed in chunks via WP-Cron so it can complete without keeping the browser tab open or hitting a server timeout. You'll receive an email with the download link (valid for 24 hours) when it's ready.

= How does scheduled export work? =
Premium users can configure a recurring export (daily, weekly, or monthly) using the Year, Image Size, and Document Type filters selected on the page, plus a recipient email. Each run happens automatically in the background and emails a fresh download link.

== Screenshots ==

1. Export page with Year and Image Size filter dropdowns
2. Real-time progress bar during export
3. Download ready with export summary

== Support ==

If you have any questions or need help, please open an issue on GitHub or contact me at huzoorbux@gmail.com.

== Changelog ==

= 2.0 =
* Added Premium document export — PDF, Word, Excel, and PowerPoint files alongside images
* Added Premium background export — chunked WP-Cron processing with email notification, for exports too large for a single request
* Added Premium scheduled export — recurring daily/weekly/monthly exports emailed automatically
* Plugin menu is now a top-level admin menu instead of a submenu under Media

= 1.9 =
* Tested up to WordPress 7.1

= 1.7 =
* Added Year filter — export images from one or more specific upload years
* Added Image Size filter — choose Full Size (original) and/or any intermediate size registered by the theme or plugins
* Live export preview shows image and size count before starting
* Replaced flat checkbox layout with dropdown-style filter UI
* Export now uses WP_Query against the database instead of filesystem scanning, ensuring only real media library attachments are included
* Uses wp_get_registered_image_subsizes() (WP 5.3+) with fallback for older installs

= 1.6 =
* Added media library statistics panel (total images, total size, file type breakdown)
* Added real-time progress bar with file counter
* Added auto-cleanup of expired ZIP files via WP-Cron
* Improved error handling and user messaging

= 1.5 =
* Tested up to WordPress 6.9

= 1.4 =
* Initial release
