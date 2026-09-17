<?php

/**
 * The plugin bootstrap file
 *
 * @category Backup_Plugin
 * @package  Export_Media_Zip
 * @author   Huzoor Bux <huzoorbakhsh@gmail.com>
 * @license  http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3 or higher
 * @link     https://huzoorbux.com
 * @since    1.0.0
 *
 * @wordpress-plugin
 * Plugin Name:       Export Media as ZIP
 * Description:       Adds a top-level admin menu to export images (and, in Premium, documents) as a ZIP file, with year/size filters, background export, and scheduled export.
 * Version:           2.0
 * Author:            Huzoor Bux
 * Author URI:        https://huzoorbakhsh.com
 * License:           GPL-2.0+
 */
// Prevent direct access
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( !function_exists( 'emaz_fs' ) ) {
    // Create a helper function for easy SDK access.
    function emaz_fs() {
        global $emaz_fs;
        if ( !isset( $emaz_fs ) ) {
            // Include Freemius SDK.
            require_once dirname( __FILE__ ) . '/freemius/start.php';
            $emaz_fs = fs_dynamic_init( array(
                'id'               => '39205',
                'slug'             => 'export-media-as-zip',
                'type'             => 'plugin',
                'public_key'       => 'pk_0ce2f78cd2aa04f65ce5c7e6048a4',
                'is_premium'       => false,
                'premium_suffix'   => 'Professional',
                'has_addons'       => false,
                'has_paid_plans'   => true,
                'is_org_compliant' => true,
                'menu'             => array(
                    'slug' => 'export-media-zip',
                ),
                'is_live'          => true,
            ) );
        }
        return $emaz_fs;
    }

    // Init Freemius.
    emaz_fs();
    // Signal that SDK was initiated.
    do_action( 'emaz_fs_loaded' );
}
// Main plugin class
class EMAZ_Export_Media_Zip {
    private $zip_filename = 'media-images.zip';

    private $zip_expiry = 300;

    // 5 minutes in seconds
    private $job_expiry = DAY_IN_SECONDS;

    // background/scheduled job ZIPs live 24h
    private $job_batch_size = 150;

    // files processed per background cron tick
    // Document mime types available to premium users, grouped by UI-facing category.
    private $doc_mime_groups = array(
        'pdf'        => array(
            'label' => 'PDF',
            'exts'  => array(
                'pdf' => 'application/pdf',
            ),
        ),
        'word'       => array(
            'label' => 'Word',
            'exts'  => array(
                'doc'  => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ),
        ),
        'excel'      => array(
            'label' => 'Excel',
            'exts'  => array(
                'xls'  => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ),
        ),
        'powerpoint' => array(
            'label' => 'PowerPoint',
            'exts'  => array(
                'ppt'  => 'application/vnd.ms-powerpoint',
                'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ),
        ),
    );

    public function __construct() {
        add_action( 'admin_menu', array($this, 'emaz_add_admin_page') );
        add_action( 'admin_enqueue_scripts', array($this, 'emaz_enqueue_scripts') );
        add_action( 'wp_ajax_emaz_export_media_zip', array($this, 'emaz_handle_export') );
        add_action( 'wp_ajax_emaz_get_export_progress', array($this, 'emaz_get_export_progress') );
        add_action( 'wp_ajax_emaz_get_media_stats', array($this, 'emaz_get_media_stats') );
        add_action( 'wp_ajax_emaz_get_filter_options', array($this, 'emaz_get_filter_options') );
        add_action( 'wp_ajax_emaz_preview_export', array($this, 'emaz_preview_export') );
        add_action( 'wp_ajax_emaz_queue_background_export', array($this, 'emaz_queue_background_export') );
        add_action( 'wp_ajax_emaz_get_background_job_status', array($this, 'emaz_get_background_job_status') );
        add_action( 'wp_ajax_emaz_get_export_schedule', array($this, 'emaz_ajax_get_export_schedule') );
        add_action( 'wp_ajax_emaz_save_export_schedule', array($this, 'emaz_ajax_save_export_schedule') );
        add_action( 'wp_ajax_emaz_delete_export_schedule', array($this, 'emaz_ajax_delete_export_schedule') );
        add_action( 'init', array($this, 'emaz_schedule_zip_cleanup') );
        add_action( 'emaz_cleanup_expired_zips', array($this, 'emaz_cleanup_expired_zips') );
        add_action( 'emaz_run_background_export', array($this, 'emaz_process_background_job') );
        add_action( 'emaz_run_scheduled_export', array($this, 'emaz_run_scheduled_export') );
        add_filter( 'cron_schedules', array($this, 'emaz_add_cron_intervals') );
        // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
    }

    // Whether the current site can use premium (paid/licensed) functionality
    private function emaz_is_premium() {
        return function_exists( 'emaz_fs' ) && emaz_fs()->can_use_premium_code();
    }

    // Register weekly/monthly cron intervals for scheduled exports
    public function emaz_add_cron_intervals( $schedules ) {
        if ( !isset( $schedules['emaz_weekly'] ) ) {
            $schedules['emaz_weekly'] = array(
                'interval' => WEEK_IN_SECONDS,
                'display'  => 'Once Weekly (Export Media as ZIP)',
            );
        }
        if ( !isset( $schedules['emaz_monthly'] ) ) {
            $schedules['emaz_monthly'] = array(
                'interval' => 30 * DAY_IN_SECONDS,
                'display'  => 'Once Monthly (Export Media as ZIP)',
            );
        }
        return $schedules;
    }

    // Add top-level admin menu
    public function emaz_add_admin_page() {
        add_menu_page(
            'Export Media as ZIP',
            'Export Media as ZIP',
            'manage_options',
            'export-media-zip',
            array($this, 'emaz_render_admin_page'),
            'dashicons-media-archive',
            80
        );
    }

    // Render the admin page
    public function emaz_render_admin_page() {
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized access.' );
        }
        ?>
		<div class="wrap export-media-wrap">
			<h1>Export Media as ZIP</h1>
			<p>Export images (.jpg, .jpeg, .png, .gif, .webp) from the media library as a ZIP file. <strong>Premium</strong> adds PDF/Word/Excel/PowerPoint documents, background export, and scheduled exports.</p>

			<!-- Media Statistics -->
			<div id="media-stats-container" class="stats-container">
				<h3>Media Library Statistics</h3>
				<div id="media-stats-loading" class="stats-loading">
					<span class="spinner"></span> Loading statistics...
				</div>
				<div id="media-stats-content" style="display: none;">
					<div class="stats-grid">
						<div class="stat-item">
							<span class="stat-number" id="total-images">0</span>
							<span class="stat-label">Total Images</span>
						</div>
						<div class="stat-item">
							<span class="stat-number" id="total-size">0 MB</span>
							<span class="stat-label">Total Size</span>
						</div>
						<div class="stat-item">
							<span class="stat-number" id="file-types">0</span>
							<span class="stat-label">File Types</span>
						</div>
					</div>
					<div id="file-type-breakdown" class="file-types"></div>
				</div>
			</div>

			<!-- Export Filters -->
			<div id="filter-section" class="filter-section">
				<h3>Export Filters</h3>
				<div id="filter-loading" class="stats-loading">
					<span class="spinner"></span> Loading filter options...
				</div>
				<div id="filter-content" style="display: none;">
					<div class="filter-row">

						<!-- Year Dropdown -->
						<div class="filter-field">
							<label class="filter-label">Year</label>
							<div class="emaz-dropdown" id="year-dropdown">
								<button type="button" class="emaz-dropdown-trigger">
									<span class="emaz-dropdown-label">All Years</span>
									<span class="emaz-dropdown-arrow">&#9662;</span>
								</button>
								<div class="emaz-dropdown-panel">
									<div class="emaz-dropdown-actions">
										<button type="button" class="emaz-action-link select-all-years">Select All</button>
										<button type="button" class="emaz-action-link deselect-all-years">None</button>
									</div>
									<div id="year-filters" class="emaz-dropdown-items"></div>
								</div>
							</div>
						</div>

						<!-- Image Size Dropdown -->
						<div class="filter-field">
							<label class="filter-label">Image Size</label>
							<div class="emaz-dropdown" id="size-dropdown">
								<button type="button" class="emaz-dropdown-trigger">
									<span class="emaz-dropdown-label">Full Size (Original)</span>
									<span class="emaz-dropdown-arrow">&#9662;</span>
								</button>
								<div class="emaz-dropdown-panel">
									<div class="emaz-dropdown-actions">
										<button type="button" class="emaz-action-link select-all-sizes">Select All</button>
										<button type="button" class="emaz-action-link deselect-all-sizes">None</button>
									</div>
									<p class="emaz-dropdown-hint">Sizes registered by WordPress core, theme &amp; plugins.</p>
									<div id="size-filters" class="emaz-dropdown-items"></div>
								</div>
							</div>
						</div>

						<!-- Document Type Dropdown (Premium) -->
						<div class="filter-field">
							<label class="filter-label">Document Types <span class="emaz-premium-tag">Premium</span></label>
							<div class="emaz-dropdown" id="doctype-dropdown">
								<button type="button" class="emaz-dropdown-trigger">
									<span class="emaz-dropdown-label">Images Only</span>
									<span class="emaz-dropdown-arrow">&#9662;</span>
								</button>
								<div class="emaz-dropdown-panel">
									<div class="emaz-dropdown-actions">
										<button type="button" class="emaz-action-link select-all-doctypes">Select All</button>
										<button type="button" class="emaz-action-link deselect-all-doctypes">None</button>
									</div>
									<p class="emaz-dropdown-hint">PDF, Word, Excel &amp; PowerPoint files alongside images.</p>
									<div id="doctype-filters" class="emaz-dropdown-items"></div>
								</div>
							</div>
						</div>

					</div>

					<!-- Preview Count -->
					<div id="filter-preview" class="filter-preview">
						<span id="filter-preview-text">Select filters above to see export preview.</span>
					</div>
				</div>
			</div>

			<!-- Background Export Toggle (Premium) -->
			<div class="background-toggle-row">
				<label class="emaz-checkbox-label">
					<input type="checkbox" id="run-in-background-cb" disabled>
					Run in background &amp; email me the link <span class="emaz-premium-tag">Premium</span>
				</label>
			</div>

			<!-- Export Button -->
			<div class="export-section">
				<button id="export-media-zip-button" class="button button-primary button-hero" disabled>
					<span class="button-text">Export Images</span>
					<span class="button-spinner" style="display: none;"></span>
				</button>
			</div>

			<!-- Progress Section -->
			<div id="progress-section" class="progress-section" style="display: none;">
				<h3>Export Progress</h3>
				<div id="progress-bar-container" class="progress-container">
					<div id="progress-bar" class="progress-bar"></div>
					<div class="progress-info">
						<span id="progress-text">0%</span>
						<span id="progress-files">0 / 0 files</span>
					</div>
				</div>
				<div id="current-file" class="current-file"></div>
			</div>

			<!-- Download Section -->
			<div id="download-section" class="download-section" style="display: none;">
				<h3>Download Ready</h3>
				<div id="download-link"></div>
			</div>

			<!-- Scheduled Exports (Premium) -->
			<div id="schedule-section" class="filter-section schedule-section">
				<h3>Scheduled Exports <span class="emaz-premium-tag">Premium</span></h3>
				<p class="emaz-dropdown-hint">Automatically run this export on a recurring basis and email the download link.</p>

				<div id="schedule-loading" class="stats-loading">
					<span class="spinner"></span> Loading schedule...
				</div>

				<div id="schedule-content" style="display: none;">
					<div class="filter-row">

						<div class="filter-field">
							<label class="filter-label">Frequency</label>
							<select id="schedule-frequency" class="emaz-select" disabled>
								<option value="daily">Daily</option>
								<option value="weekly" selected>Weekly</option>
								<option value="monthly">Monthly</option>
							</select>
						</div>

						<div class="filter-field">
							<label class="filter-label">Recipient Email</label>
							<input type="email" id="schedule-recipient" class="emaz-text-input" disabled>
						</div>

						<div class="filter-field">
							<label class="filter-label">&nbsp;</label>
							<label class="emaz-checkbox-label">
								<input type="checkbox" id="schedule-enabled" disabled>
								Enable this schedule
							</label>
						</div>

					</div>

					<p class="emaz-dropdown-hint">Uses the Year, Image Size and Document Type filters selected above.</p>

					<div id="schedule-status" class="emaz-dropdown-hint"></div>

					<div class="schedule-actions">
						<button type="button" id="save-schedule-button" class="button button-primary" disabled>Save Schedule</button>
						<button type="button" id="delete-schedule-button" class="button" disabled>Disable</button>
					</div>
				</div>
			</div>

			<!-- Error Messages -->
			<div id="error-message" class="error-message"></div>
		</div>
		<?php 
    }

    // Enqueue scripts and styles only on the plugin's page
    public function emaz_enqueue_scripts( $hook ) {
        if ( 'toplevel_page_export-media-zip' !== $hook ) {
            return;
        }
        wp_enqueue_script(
            'export-media-zip-js',
            plugin_dir_url( __FILE__ ) . 'scripts/export-media-zip.js',
            array('jquery'),
            '2.0',
            true
        );
        wp_localize_script( 'export-media-zip-js', 'emazExportMediaZip', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'emaz_export_media_zip' ),
        ) );
        wp_enqueue_style(
            'export-media-zip-css',
            plugin_dir_url( __FILE__ ) . 'styles/export-media-zip.css',
            array(),
            '2.0'
        );
    }

    // Return available years and registered image sizes for the filter UI
    public function emaz_get_filter_options() {
        check_ajax_referer( 'emaz_export_media_zip', 'nonce' );
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => 'Unauthorized',
            ) );
        }
        global $wpdb;
        // Years that have at least one image attachment
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $years = $wpdb->get_results( "SELECT YEAR(post_date) AS year, COUNT(*) AS count\n\t\t\t FROM {$wpdb->posts}\n\t\t\t WHERE post_type    = 'attachment'\n\t\t\t   AND post_mime_type LIKE 'image/%'\n\t\t\t   AND post_status  = 'inherit'\n\t\t\t GROUP BY YEAR(post_date)\n\t\t\t ORDER BY year DESC" );
        // All registered image sizes with their dimensions
        $sizes = array();
        // 'full' is the original uploaded file
        $sizes['full'] = array(
            'label'      => 'Full Size (Original)',
            'dimensions' => '',
        );
        // wp_get_registered_image_subsizes() was added in WP 5.3
        if ( function_exists( 'wp_get_registered_image_subsizes' ) ) {
            $registered = wp_get_registered_image_subsizes();
        } else {
            $registered = array();
            foreach ( get_intermediate_image_sizes() as $size_name ) {
                $registered[$size_name] = array(
                    'width'  => (int) get_option( "{$size_name}_size_w" ),
                    'height' => (int) get_option( "{$size_name}_size_h" ),
                    'crop'   => (bool) get_option( "{$size_name}_crop" ),
                );
            }
        }
        foreach ( $registered as $size_name => $size_data ) {
            $label = ucwords( str_replace( array('-', '_'), ' ', $size_name ) );
            $dimensions = '';
            if ( !empty( $size_data['width'] ) || !empty( $size_data['height'] ) ) {
                $w = ( !empty( $size_data['width'] ) ? $size_data['width'] : '?' );
                $h = ( !empty( $size_data['height'] ) ? $size_data['height'] : '?' );
                $crop = ( !empty( $size_data['crop'] ) ? ', cropped' : '' );
                $dimensions = "{$w}×{$h}{$crop}";
            }
            $sizes[$size_name] = array(
                'label'      => $label,
                'dimensions' => $dimensions,
            );
        }
        $doc_types = array();
        foreach ( $this->doc_mime_groups as $key => $group ) {
            $doc_types[$key] = array(
                'label' => $group['label'],
            );
        }
        $is_premium = $this->emaz_is_premium();
        wp_send_json_success( array(
            'years'     => $years,
            'sizes'     => $sizes,
            'doc_types' => $doc_types,
            'premium'   => array(
                'can_use_premium' => $is_premium,
                'upgrade_url'     => ( !$is_premium && function_exists( 'emaz_fs' ) ? emaz_fs()->get_upgrade_url() : '' ),
            ),
        ) );
    }

    // Return a lightweight preview count for the current filter selection
    public function emaz_preview_export() {
        check_ajax_referer( 'emaz_export_media_zip', 'nonce' );
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => 'Unauthorized',
            ) );
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $selected_years = ( isset( $_POST['years'] ) ? array_map( 'intval', (array) $_POST['years'] ) : array() );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $selected_sizes = ( isset( $_POST['sizes'] ) ? array_map( 'sanitize_key', (array) $_POST['sizes'] ) : array('full') );
        $selected_docs = $this->emaz_sanitize_doc_types( ( isset( $_POST['doc_types'] ) ? (array) $_POST['doc_types'] : array() ) );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( empty( $selected_sizes ) && empty( $selected_docs ) ) {
            wp_send_json_success( array(
                'attachment_count' => 0,
                'size_count'       => 0,
            ) );
            return;
        }
        $query_args = $this->emaz_build_attachment_query( $selected_years, $selected_docs );
        $query_args['fields'] = 'ids';
        $query_args['no_found_rows'] = true;
        $attachment_ids = get_posts( $query_args );
        // phpcs:ignore WordPress.VIP.RestrictedFunctions
        wp_send_json_success( array(
            'attachment_count' => count( $attachment_ids ),
            'size_count'       => count( $selected_sizes ),
        ) );
    }

    // Only pass through document type keys that are both valid and actually available (premium)
    private function emaz_sanitize_doc_types( $requested_types ) {
        if ( empty( $requested_types ) || !$this->emaz_is_premium() ) {
            return array();
        }
        $requested_types = array_map( 'sanitize_key', $requested_types );
        return array_values( array_intersect( $requested_types, array_keys( $this->doc_mime_groups ) ) );
    }

    // Flatten the selected document-type categories into a list of mime types
    private function emaz_doc_mime_types_for( $selected_doc_types ) {
        $mimes = array();
        foreach ( $selected_doc_types as $doc_type ) {
            if ( isset( $this->doc_mime_groups[$doc_type] ) ) {
                $mimes = array_merge( $mimes, array_values( $this->doc_mime_groups[$doc_type]['exts'] ) );
            }
        }
        return $mimes;
    }

    // Build a get_posts() args array for image (and, when premium + selected, document) attachments
    private function emaz_build_attachment_query( $selected_years = array(), $selected_doc_types = array() ) {
        $mime_types = array('image');
        $mime_types = array_merge( $mime_types, $this->emaz_doc_mime_types_for( $selected_doc_types ) );
        $args = array(
            'post_type'      => 'attachment',
            'post_mime_type' => $mime_types,
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
        );
        if ( !empty( $selected_years ) ) {
            $args['date_query'] = array(
                'relation' => 'OR',
            );
            foreach ( $selected_years as $year ) {
                $args['date_query'][] = array(
                    'year' => (int) $year,
                );
            }
        }
        return $args;
    }

    // Handle export AJAX request (synchronous, free-tier + ad-hoc foreground export)
    public function emaz_handle_export() {
        if ( !check_ajax_referer( 'emaz_export_media_zip', 'nonce', false ) ) {
            wp_send_json_error( array(
                'message' => 'Security check failed. Please refresh the page and try again.',
            ) );
        }
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => 'You do not have permission to perform this action.',
            ) );
        }
        $params = $this->emaz_parse_export_params_from_post();
        if ( empty( $params['sizes'] ) && empty( $params['doc_types'] ) ) {
            wp_send_json_error( array(
                'message' => 'Please select at least one image size to export.',
            ) );
        }
        $result = $this->emaz_run_export_job( $params );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array(
                'message' => $result->get_error_message(),
            ) );
        }
        wp_send_json_success( $result );
    }

    // Read + sanitize year/size/doc-type filters from $_POST (doc types are dropped for non-premium sites)
    private function emaz_parse_export_params_from_post() {
        return array(
            'years'     => ( isset( $_POST['years'] ) ? array_map( 'intval', (array) $_POST['years'] ) : array() ),
            'sizes'     => ( isset( $_POST['sizes'] ) ? array_map( 'sanitize_key', (array) $_POST['sizes'] ) : array('full') ),
            'doc_types' => $this->emaz_sanitize_doc_types( ( isset( $_POST['doc_types'] ) ? (array) $_POST['doc_types'] : array() ) ),
        );
    }

    // Run an export. $job_id null => synchronous free-tier path (unchanged filename/expiry/options).
    // $job_id set => chunked background/scheduled path, processing one batch per call.
    private function emaz_run_export_job( array $params, $job_id = null ) {
        if ( !class_exists( 'ZipArchive' ) ) {
            return new WP_Error('emaz_no_zip', 'ZipArchive extension is not available on this server. Please contact your hosting provider.');
        }
        if ( !function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        global $wp_filesystem;
        if ( !WP_Filesystem() ) {
            return new WP_Error('emaz_fs_init', 'Failed to initialize WordPress filesystem. Please check file permissions.');
        }
        $upload_dir = wp_upload_dir();
        if ( $upload_dir['error'] ) {
            return new WP_Error('emaz_upload_dir', 'Upload directory error: ' . $upload_dir['error']);
        }
        $base_dir = $upload_dir['basedir'];
        if ( !$wp_filesystem->is_dir( $base_dir ) || !$wp_filesystem->is_readable( $base_dir ) ) {
            return new WP_Error('emaz_dir_unreadable', 'Uploads directory is not accessible. Please check file permissions.');
        }
        if ( !$wp_filesystem->is_writable( $base_dir ) ) {
            return new WP_Error('emaz_dir_unwritable', 'Cannot write to uploads directory. Please check file permissions.');
        }
        if ( $job_id ) {
            $zip_path = $base_dir . '/emaz-export-' . $job_id . '.zip';
            return $this->emaz_run_chunked_export_job(
                $params,
                $job_id,
                $wp_filesystem,
                $upload_dir,
                $zip_path
            );
        }
        $zip_path = $base_dir . '/' . $this->zip_filename;
        return $this->emaz_run_sync_export_job(
            $params,
            $wp_filesystem,
            $upload_dir,
            $zip_path
        );
    }

    // Synchronous export — identical behavior/options/response shape to the original single-request flow
    private function emaz_run_sync_export_job(
        array $params,
        $wp_filesystem,
        $upload_dir,
        $zip_path
    ) {
        $base_dir = $upload_dir['basedir'];
        $query_args = $this->emaz_build_attachment_query( $params['years'], $params['doc_types'] );
        $query_args['fields'] = 'ids';
        $query_args['no_found_rows'] = true;
        $attachment_ids = get_posts( $query_args );
        // phpcs:ignore WordPress.VIP.RestrictedFunctions
        if ( empty( $attachment_ids ) ) {
            return new WP_Error('emaz_no_attachments', 'No files found matching the selected filters.');
        }
        $files_to_zip = $this->emaz_collect_files_to_zip(
            $attachment_ids,
            $params['sizes'],
            $base_dir,
            $wp_filesystem
        );
        if ( empty( $files_to_zip ) ) {
            return new WP_Error('emaz_no_files', 'No image files found for the selected filters and sizes.');
        }
        // Create ZIP archive
        $zip = new ZipArchive();
        $zip_result = $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
        if ( true !== $zip_result ) {
            return new WP_Error('emaz_zip_open', 'Failed to create ZIP file (Code: ' . $zip_result . ')');
        }
        $total_files = count( $files_to_zip );
        $processed_files = 0;
        $failed_files = array();
        update_option( 'emaz_total_files', $total_files );
        update_option( 'emaz_processed_files', 0 );
        foreach ( $files_to_zip as $abs_path => $archive_name ) {
            if ( !$zip->addFile( $abs_path, $archive_name ) ) {
                $failed_files[] = basename( $abs_path );
            }
            ++$processed_files;
            update_option( 'emaz_progress', $processed_files / $total_files * 100 );
            update_option( 'emaz_processed_files', $processed_files );
            update_option( 'emaz_current_file', basename( $abs_path ) );
        }
        if ( !$zip->close() ) {
            return new WP_Error('emaz_zip_close', 'Failed to finalize ZIP file. The archive may be corrupted.');
        }
        if ( !$wp_filesystem->exists( $zip_path ) || 0 === $wp_filesystem->size( $zip_path ) ) {
            return new WP_Error('emaz_zip_empty', 'ZIP file was not created properly or is empty.');
        }
        update_option( 'emaz_zip_time', time() );
        $response_data = array(
            'download_url'    => $upload_dir['baseurl'] . '/' . $this->zip_filename,
            'total_files'     => $total_files,
            'processed_files' => $processed_files,
            'zip_size'        => $this->emaz_format_bytes( $wp_filesystem->size( $zip_path ) ),
        );
        if ( !empty( $failed_files ) ) {
            $response_data['warning'] = 'Some files could not be added: ' . implode( ', ', array_slice( $failed_files, 0, 5 ) );
            if ( count( $failed_files ) > 5 ) {
                $response_data['warning'] .= ' and ' . (count( $failed_files ) - 5) . ' more.';
            }
            $response_data['failed_files_count'] = count( $failed_files );
        }
        return $response_data;
    }

    // Chunked export — processes up to $job_batch_size files per call, persisting a cursor in a
    // per-job option so a WP-Cron tick never has to hold the whole export in one PHP request.
    private function emaz_run_chunked_export_job(
        array $params,
        $job_id,
        $wp_filesystem,
        $upload_dir,
        $zip_path
    ) {
        $base_dir = $upload_dir['basedir'];
        $progress_key = 'emaz_job_progress_' . $job_id;
        $progress = get_option( $progress_key );
        if ( false === $progress ) {
            $query_args = $this->emaz_build_attachment_query( $params['years'], $params['doc_types'] );
            $query_args['fields'] = 'ids';
            $query_args['no_found_rows'] = true;
            $attachment_ids = get_posts( $query_args );
            // phpcs:ignore WordPress.VIP.RestrictedFunctions
            if ( empty( $attachment_ids ) ) {
                return new WP_Error('emaz_no_attachments', 'No files found matching the selected filters.');
            }
            $files_to_zip = $this->emaz_collect_files_to_zip(
                $attachment_ids,
                $params['sizes'],
                $base_dir,
                $wp_filesystem
            );
            if ( empty( $files_to_zip ) ) {
                return new WP_Error('emaz_no_files', 'No files found for the selected filters and sizes.');
            }
            $pending_files = array();
            foreach ( $files_to_zip as $abs_path => $archive_name ) {
                $pending_files[] = array($abs_path, $archive_name);
            }
            $progress = array(
                'pending_files'   => $pending_files,
                'cursor'          => 0,
                'total_files'     => count( $pending_files ),
                'processed_files' => 0,
                'progress'        => 0,
                'current_file'    => '',
                'failed_files'    => array(),
            );
        }
        $zip = new ZipArchive();
        $open_flags = ( 0 === $progress['cursor'] ? ZipArchive::CREATE | ZipArchive::OVERWRITE : ZipArchive::CREATE );
        $zip_result = $zip->open( $zip_path, $open_flags );
        if ( true !== $zip_result ) {
            delete_option( $progress_key );
            return new WP_Error('emaz_zip_open', 'Failed to create ZIP file (Code: ' . $zip_result . ')');
        }
        $chunk = array_slice( $progress['pending_files'], $progress['cursor'], $this->job_batch_size );
        foreach ( $chunk as $file ) {
            list( $abs_path, $archive_name ) = $file;
            if ( !$zip->addFile( $abs_path, $archive_name ) ) {
                $progress['failed_files'][] = basename( $abs_path );
            }
            $progress['current_file'] = basename( $abs_path );
        }
        if ( !$zip->close() ) {
            delete_option( $progress_key );
            return new WP_Error('emaz_zip_close', 'Failed to finalize ZIP file. The archive may be corrupted.');
        }
        $progress['cursor'] += count( $chunk );
        $progress['processed_files'] = $progress['cursor'];
        $progress['progress'] = ( $progress['total_files'] > 0 ? $progress['processed_files'] / $progress['total_files'] * 100 : 100 );
        if ( $progress['cursor'] < $progress['total_files'] ) {
            update_option( $progress_key, $progress, false );
            return array(
                'status'          => 'processing',
                'progress'        => $progress['progress'],
                'processed_files' => $progress['processed_files'],
                'total_files'     => $progress['total_files'],
                'current_file'    => $progress['current_file'],
            );
        }
        // Final chunk done — finalize.
        if ( !$wp_filesystem->exists( $zip_path ) || 0 === $wp_filesystem->size( $zip_path ) ) {
            delete_option( $progress_key );
            return new WP_Error('emaz_zip_empty', 'ZIP file was not created properly or is empty.');
        }
        $result = array(
            'status'             => 'complete',
            'download_url'       => $upload_dir['baseurl'] . '/' . basename( $zip_path ),
            'zip_filename'       => basename( $zip_path ),
            'total_files'        => $progress['total_files'],
            'processed_files'    => $progress['processed_files'],
            'zip_size'           => $this->emaz_format_bytes( $wp_filesystem->size( $zip_path ) ),
            'failed_files_count' => count( $progress['failed_files'] ),
        );
        delete_option( $progress_key );
        return $result;
    }

    // Collect absolute paths -> archive names for the given attachments. Images honor the size
    // filter (original + intermediate sizes); documents have no "sizes" and are always included
    // at their original file whenever their document type was selected, regardless of $selected_sizes.
    private function emaz_collect_files_to_zip(
        $attachment_ids,
        $selected_sizes,
        $base_dir,
        $wp_filesystem
    ) {
        $include_full = in_array( 'full', $selected_sizes, true );
        $other_sizes = array_diff( $selected_sizes, array('full') );
        $image_exts = array(
            'jpg',
            'jpeg',
            'png',
            'gif',
            'webp'
        );
        $doc_exts = $this->emaz_all_doc_extensions();
        $allowed_exts = array_merge( $image_exts, $doc_exts );
        $files_to_zip = array();
        foreach ( $attachment_ids as $attachment_id ) {
            $full_path = get_attached_file( $attachment_id );
            if ( !$full_path || !$wp_filesystem->exists( $full_path ) ) {
                continue;
            }
            $ext = strtolower( pathinfo( $full_path, PATHINFO_EXTENSION ) );
            if ( !in_array( $ext, $allowed_exts, true ) ) {
                continue;
            }
            $is_document = in_array( $ext, $doc_exts, true );
            // Full / original size (documents always export at original size)
            if ( ($is_document || $include_full) && $wp_filesystem->is_readable( $full_path ) ) {
                $relative = ltrim( str_replace( $base_dir, '', $full_path ), '/\\' );
                $files_to_zip[$full_path] = $relative;
            }
            // Intermediate sizes stored in attachment metadata (images only)
            if ( !$is_document && !empty( $other_sizes ) ) {
                $meta = wp_get_attachment_metadata( $attachment_id );
                if ( !empty( $meta['sizes'] ) ) {
                    $original_dir = dirname( $full_path );
                    $relative_dir = ltrim( str_replace( $base_dir, '', $original_dir ), '/\\' );
                    foreach ( $other_sizes as $size_name ) {
                        if ( empty( $meta['sizes'][$size_name]['file'] ) ) {
                            continue;
                        }
                        $size_file = $original_dir . '/' . $meta['sizes'][$size_name]['file'];
                        if ( $wp_filesystem->exists( $size_file ) && $wp_filesystem->is_readable( $size_file ) ) {
                            $archive_name = $relative_dir . '/' . $meta['sizes'][$size_name]['file'];
                            $files_to_zip[$size_file] = $archive_name;
                        }
                    }
                }
            }
        }
        return $files_to_zip;
    }

    // All document file extensions across every doc-type group
    private function emaz_all_doc_extensions() {
        $exts = array();
        foreach ( $this->doc_mime_groups as $group ) {
            $exts = array_merge( $exts, array_keys( $group['exts'] ) );
        }
        return $exts;
    }

    // Get export progress AJAX
    public function emaz_get_export_progress() {
        check_ajax_referer( 'emaz_export_media_zip', 'nonce' );
        wp_send_json_success( array(
            'progress'        => get_option( 'emaz_progress', 0 ),
            'current_file'    => get_option( 'emaz_current_file', '' ),
            'processed_files' => get_option( 'emaz_processed_files', 0 ),
            'total_files'     => get_option( 'emaz_total_files', 0 ),
        ) );
    }

    // Get media statistics AJAX
    public function emaz_get_media_stats() {
        check_ajax_referer( 'emaz_export_media_zip', 'nonce' );
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => 'Unauthorized',
            ) );
        }
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'];
        $image_extensions = array(
            'jpg',
            'jpeg',
            'png',
            'gif',
            'webp'
        );
        $image_files = array();
        $file_types = array();
        $total_size = 0;
        try {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base_dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
            foreach ( $iterator as $file ) {
                if ( $file->isFile() ) {
                    $extension = strtolower( $file->getExtension() );
                    if ( in_array( $extension, $image_extensions, true ) ) {
                        $image_files[] = $file->getPathname();
                        $total_size += $file->getSize();
                        $file_types[$extension] = (( isset( $file_types[$extension] ) ? $file_types[$extension] : 0 )) + 1;
                    }
                }
            }
        } catch ( Exception $e ) {
            wp_send_json_error( array(
                'message' => 'Error scanning media directory: ' . $e->getMessage(),
            ) );
        }
        wp_send_json_success( array(
            'total_images'     => count( $image_files ),
            'total_size'       => $this->emaz_format_bytes( $total_size ),
            'total_size_bytes' => $total_size,
            'file_types'       => $file_types,
            'file_type_count'  => count( $file_types ),
        ) );
    }

    // Helper: format bytes
    private function emaz_format_bytes( $bytes, $precision = 2 ) {
        $units = array(
            'B',
            'KB',
            'MB',
            'GB',
            'TB'
        );
        for ($i = 0; $bytes > 1024 && $i < count( $units ) - 1; $i++) {
            $bytes /= 1024;
        }
        return round( $bytes, $precision ) . ' ' . $units[$i];
    }

    // -------------------------------------------------------------------------
    // Background export (Premium) — user-triggered, queued via WP-Cron
    // -------------------------------------------------------------------------
    // Queue a background export job (Premium)
    public function emaz_queue_background_export() {
        check_ajax_referer( 'emaz_export_media_zip', 'nonce' );
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => 'Unauthorized',
            ) );
        }
        if ( !$this->emaz_is_premium() ) {
            wp_send_json_error( array(
                'message'     => 'Background export is a premium feature.',
                'upgrade_url' => ( function_exists( 'emaz_fs' ) ? emaz_fs()->get_upgrade_url() : '' ),
            ) );
        }
        $params = $this->emaz_parse_export_params_from_post();
        if ( empty( $params['sizes'] ) && empty( $params['doc_types'] ) ) {
            wp_send_json_error( array(
                'message' => 'Please select at least one image size or document type to export.',
            ) );
        }
        $job_id = $this->emaz_create_job_record( $params, 'background' );
        wp_schedule_single_event( time(), 'emaz_run_background_export', array($job_id) );
        if ( function_exists( 'spawn_cron' ) ) {
            spawn_cron();
        }
        wp_send_json_success( array(
            'job_id' => $job_id,
            'status' => 'queued',
        ) );
    }

    // Poll a background/scheduled job's status (Premium)
    public function emaz_get_background_job_status() {
        check_ajax_referer( 'emaz_export_media_zip', 'nonce' );
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => 'Unauthorized',
            ) );
        }
        $job_id = ( isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '' );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $job = ( $job_id ? $this->emaz_get_job( $job_id ) : null );
        if ( !$job ) {
            wp_send_json_error( array(
                'message' => 'Job not found.',
            ) );
        }
        $progress = get_option( 'emaz_job_progress_' . $job_id );
        wp_send_json_success( array(
            'status'          => $job['status'],
            'progress'        => ( $progress ? $progress['progress'] : (( 'complete' === $job['status'] ? 100 : 0 )) ),
            'processed_files' => ( $progress ? $progress['processed_files'] : (( isset( $job['processed_files'] ) ? $job['processed_files'] : 0 )) ),
            'total_files'     => ( $progress ? $progress['total_files'] : (( isset( $job['total_files'] ) ? $job['total_files'] : 0 )) ),
            'current_file'    => ( $progress ? $progress['current_file'] : '' ),
            'download_url'    => ( isset( $job['download_url'] ) ? $job['download_url'] : '' ),
            'zip_size'        => ( isset( $job['zip_size'] ) ? $job['zip_size'] : '' ),
            'error_message'   => ( isset( $job['error_message'] ) ? $job['error_message'] : '' ),
        ) );
    }

    // Create a job registry record (does not schedule processing — callers decide when)
    private function emaz_create_job_record( array $params, $type ) {
        $job_id = uniqid( 'emaz_', true );
        $user = wp_get_current_user();
        $this->emaz_update_job( $job_id, array(
            'job_id'       => $job_id,
            'type'         => $type,
            'status'       => 'queued',
            'created_at'   => time(),
            'filters'      => $params,
            'requested_by' => array(
                'user_id' => ( $user ? $user->ID : 0 ),
                'email'   => ( $user && $user->user_email ? $user->user_email : get_option( 'admin_email' ) ),
            ),
        ) );
        return $job_id;
    }

    // Cron callback: process the next chunk of a background/scheduled job, rescheduling until done
    public function emaz_process_background_job( $job_id ) {
        $job = $this->emaz_get_job( $job_id );
        if ( !$job || !in_array( $job['status'], array('queued', 'processing'), true ) ) {
            return;
            // Already finished/failed, or an unknown/duplicate cron tick — avoid double-processing.
        }
        if ( !$this->emaz_is_premium() ) {
            $this->emaz_update_job( $job_id, array(
                'status'        => 'failed',
                'error_message' => 'Premium license is no longer active.',
                'completed_at'  => time(),
            ) );
            $this->emaz_send_export_email( $job_id );
            return;
        }
        if ( 'queued' === $job['status'] ) {
            $this->emaz_update_job( $job_id, array(
                'status' => 'processing',
            ) );
        }
        $result = $this->emaz_run_export_job( $job['filters'], $job_id );
        if ( is_wp_error( $result ) ) {
            $this->emaz_update_job( $job_id, array(
                'status'        => 'failed',
                'error_message' => $result->get_error_message(),
                'completed_at'  => time(),
            ) );
            $this->emaz_send_export_email( $job_id );
            return;
        }
        if ( 'processing' === $result['status'] ) {
            // Not done yet — schedule the next chunk rather than waiting for organic cron traffic.
            wp_schedule_single_event( time(), 'emaz_run_background_export', array($job_id) );
            if ( function_exists( 'spawn_cron' ) ) {
                spawn_cron();
            }
            return;
        }
        $this->emaz_update_job( $job_id, array(
            'status'          => 'complete',
            'completed_at'    => time(),
            'expires_at'      => time() + $this->job_expiry,
            'download_url'    => $result['download_url'],
            'zip_filename'    => $result['zip_filename'],
            'total_files'     => $result['total_files'],
            'processed_files' => $result['processed_files'],
            'zip_size'        => $result['zip_size'],
        ) );
        $this->emaz_send_export_email( $job_id );
    }

    // Email the job's requester (background) or configured recipient (scheduled) when it finishes
    private function emaz_send_export_email( $job_id ) {
        $job = $this->emaz_get_job( $job_id );
        if ( !$job ) {
            return;
        }
        if ( 'scheduled' === $job['type'] ) {
            $schedule = $this->emaz_get_export_schedule();
            $recipient = ( !empty( $schedule['recipient_email'] ) ? $schedule['recipient_email'] : get_option( 'admin_email' ) );
        } else {
            $recipient = ( isset( $job['requested_by']['email'] ) ? $job['requested_by']['email'] : get_option( 'admin_email' ) );
        }
        if ( !is_email( $recipient ) ) {
            return;
        }
        $site_name = get_bloginfo( 'name' );
        if ( 'complete' === $job['status'] ) {
            $subject = sprintf( '[%s] Your media export is ready', $site_name );
            $message = sprintf(
                "Your media export finished successfully.\n\nFiles: %d\nSize: %s\n\nDownload: %s\n\nThis link expires in 24 hours.",
                ( isset( $job['processed_files'] ) ? $job['processed_files'] : 0 ),
                ( isset( $job['zip_size'] ) ? $job['zip_size'] : 'n/a' ),
                ( isset( $job['download_url'] ) ? $job['download_url'] : '' )
            );
        } else {
            $subject = sprintf( '[%s] Your media export failed', $site_name );
            $message = sprintf( "Your media export could not be completed.\n\nError: %s", ( isset( $job['error_message'] ) ? $job['error_message'] : 'Unknown error.' ) );
        }
        wp_mail( $recipient, $subject, $message );
    }

    // -------------------------------------------------------------------------
    // Job registry — a capped list of recent background/scheduled job summaries
    // -------------------------------------------------------------------------
    private function emaz_get_job_registry() {
        $jobs = get_option( 'emaz_export_jobs', array() );
        return ( is_array( $jobs ) ? $jobs : array() );
    }

    private function emaz_save_job_registry( $jobs ) {
        uasort( $jobs, function ( $a, $b ) {
            $a_time = ( isset( $a['created_at'] ) ? $a['created_at'] : 0 );
            $b_time = ( isset( $b['created_at'] ) ? $b['created_at'] : 0 );
            return $b_time - $a_time;
        } );
        $jobs = array_slice(
            $jobs,
            0,
            20,
            true
        );
        update_option( 'emaz_export_jobs', $jobs, false );
    }

    private function emaz_get_job( $job_id ) {
        $jobs = $this->emaz_get_job_registry();
        return ( isset( $jobs[$job_id] ) ? $jobs[$job_id] : null );
    }

    private function emaz_update_job( $job_id, $data ) {
        $jobs = $this->emaz_get_job_registry();
        $jobs[$job_id] = array_merge( ( isset( $jobs[$job_id] ) ? $jobs[$job_id] : array() ), $data );
        $this->emaz_save_job_registry( $jobs );
        return $jobs[$job_id];
    }

    // -------------------------------------------------------------------------
    // Scheduled export (Premium) — a single recurring export configuration
    // -------------------------------------------------------------------------
    private function emaz_get_export_schedule() {
        $defaults = array(
            'enabled'         => false,
            'frequency'       => 'weekly',
            'years'           => array(),
            'sizes'           => array('full'),
            'doc_types'       => array(),
            'recipient_email' => get_option( 'admin_email' ),
            'updated_at'      => 0,
            'updated_by'      => 0,
        );
        return wp_parse_args( get_option( 'emaz_export_schedule', array() ), $defaults );
    }

    // Get the current schedule config (Premium)
    public function emaz_ajax_get_export_schedule() {
        check_ajax_referer( 'emaz_export_media_zip', 'nonce' );
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => 'Unauthorized',
            ) );
        }
        $schedule = $this->emaz_get_export_schedule();
        $schedule['next_run'] = wp_next_scheduled( 'emaz_run_scheduled_export' );
        wp_send_json_success( $schedule );
    }

    // Save (and (re)schedule) the recurring export config (Premium)
    public function emaz_ajax_save_export_schedule() {
        check_ajax_referer( 'emaz_export_media_zip', 'nonce' );
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => 'Unauthorized',
            ) );
        }
        if ( !$this->emaz_is_premium() ) {
            wp_send_json_error( array(
                'message'     => 'Scheduled export is a premium feature.',
                'upgrade_url' => ( function_exists( 'emaz_fs' ) ? emaz_fs()->get_upgrade_url() : '' ),
            ) );
        }
        $allowed_frequencies = array('daily', 'weekly', 'monthly');
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $frequency = ( isset( $_POST['frequency'] ) ? sanitize_key( $_POST['frequency'] ) : 'weekly' );
        if ( !in_array( $frequency, $allowed_frequencies, true ) ) {
            $frequency = 'weekly';
        }
        $recipient_email = ( isset( $_POST['recipient_email'] ) ? sanitize_email( wp_unslash( $_POST['recipient_email'] ) ) : '' );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( !is_email( $recipient_email ) ) {
            wp_send_json_error( array(
                'message' => 'Please enter a valid recipient email address.',
            ) );
        }
        $params = $this->emaz_parse_export_params_from_post();
        if ( empty( $params['sizes'] ) && empty( $params['doc_types'] ) ) {
            wp_send_json_error( array(
                'message' => 'Please select at least one image size or document type.',
            ) );
        }
        $user = wp_get_current_user();
        $schedule = array(
            'enabled'         => !empty( $_POST['enabled'] ),
            'frequency'       => $frequency,
            'years'           => $params['years'],
            'sizes'           => $params['sizes'],
            'doc_types'       => $params['doc_types'],
            'recipient_email' => $recipient_email,
            'updated_at'      => time(),
            'updated_by'      => ( $user ? $user->ID : 0 ),
        );
        update_option( 'emaz_export_schedule', $schedule, false );
        $interval_map = array(
            'daily'   => 'daily',
            'weekly'  => 'emaz_weekly',
            'monthly' => 'emaz_monthly',
        );
        wp_clear_scheduled_hook( 'emaz_run_scheduled_export' );
        if ( $schedule['enabled'] ) {
            wp_schedule_event( time(), $interval_map[$frequency], 'emaz_run_scheduled_export' );
        }
        $schedule['next_run'] = wp_next_scheduled( 'emaz_run_scheduled_export' );
        wp_send_json_success( $schedule );
    }

    // Disable and clear the recurring export config (Premium)
    public function emaz_ajax_delete_export_schedule() {
        check_ajax_referer( 'emaz_export_media_zip', 'nonce' );
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => 'Unauthorized',
            ) );
        }
        delete_option( 'emaz_export_schedule' );
        wp_clear_scheduled_hook( 'emaz_run_scheduled_export' );
        wp_send_json_success();
    }

    // Cron callback for the recurring scheduled export — creates a job then runs its first chunk now
    public function emaz_run_scheduled_export() {
        if ( !$this->emaz_is_premium() ) {
            return;
        }
        $schedule = $this->emaz_get_export_schedule();
        if ( empty( $schedule['enabled'] ) ) {
            return;
        }
        $params = array(
            'years'     => $schedule['years'],
            'sizes'     => $schedule['sizes'],
            'doc_types' => $this->emaz_sanitize_doc_types( $schedule['doc_types'] ),
        );
        if ( empty( $params['sizes'] ) && empty( $params['doc_types'] ) ) {
            return;
        }
        $job_id = $this->emaz_create_job_record( $params, 'scheduled' );
        $this->emaz_process_background_job( $job_id );
    }

    // Schedule ZIP cleanup
    public function emaz_schedule_zip_cleanup() {
        if ( !wp_next_scheduled( 'emaz_cleanup_expired_zips' ) ) {
            wp_schedule_event( time(), 'hourly', 'emaz_cleanup_expired_zips' );
        }
    }

    // Cleanup expired ZIPs
    public function emaz_cleanup_expired_zips() {
        global $wp_filesystem;
        if ( !WP_Filesystem() ) {
            return;
        }
        $base_dir = wp_upload_dir()['basedir'];
        $zip_path = $base_dir . '/' . $this->zip_filename;
        $zip_time = get_option( 'emaz_zip_time', 0 );
        if ( $wp_filesystem->is_file( $zip_path ) && time() - $zip_time > $this->zip_expiry ) {
            $wp_filesystem->delete( $zip_path );
            delete_option( 'emaz_zip_time' );
            delete_option( 'emaz_progress' );
            delete_option( 'emaz_current_file' );
            delete_option( 'emaz_processed_files' );
            delete_option( 'emaz_total_files' );
        }
        $this->emaz_cleanup_job_zips( $wp_filesystem, $base_dir );
    }

    // Delete expired background/scheduled job ZIPs and prune old job records
    private function emaz_cleanup_job_zips( $wp_filesystem, $base_dir ) {
        $jobs = $this->emaz_get_job_registry();
        $changed = false;
        $now = time();
        foreach ( $jobs as $job_id => $job ) {
            // Drop records older than 7 days entirely.
            if ( isset( $job['created_at'] ) && $now - $job['created_at'] > 7 * DAY_IN_SECONDS ) {
                unset($jobs[$job_id]);
                $changed = true;
                continue;
            }
            $is_expired = isset( $job['status'] ) && 'complete' === $job['status'] && empty( $job['zip_deleted'] ) && !empty( $job['expires_at'] ) && $now > $job['expires_at'];
            if ( $is_expired ) {
                if ( !empty( $job['zip_filename'] ) ) {
                    $job_zip_path = $base_dir . '/' . $job['zip_filename'];
                    if ( $wp_filesystem->is_file( $job_zip_path ) ) {
                        $wp_filesystem->delete( $job_zip_path );
                    }
                }
                delete_option( 'emaz_job_progress_' . $job_id );
                $jobs[$job_id]['zip_deleted'] = true;
                $changed = true;
            }
        }
        if ( $changed ) {
            update_option( 'emaz_export_jobs', $jobs, false );
        }
    }

}

new EMAZ_Export_Media_Zip();