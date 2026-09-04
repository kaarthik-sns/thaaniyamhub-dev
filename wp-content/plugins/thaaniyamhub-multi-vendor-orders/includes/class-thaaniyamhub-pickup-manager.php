<?php
/**
 * Thaaniyam Hub Marketplace — Pickup Location Manager
 *
 * Provides a dedicated WooCommerce admin submenu page:
 *   WooCommerce → Pickup Locations
 *
 * Tabs:
 *   1. 📍 Pickup Locations  — view all Shiprocket pickup locations
 *   2. 👥 Vendor Assignment — assign each WCFM vendor to a pickup location
 *   3. ⚠️ Unassigned        — vendors without any pickup configured
 *   4. 📜 Audit Logs        — full A-Z logging of changes, requests, users & traces
 *
 * @package thaaniyamhub-multi-vendor-orders
 */

defined('ABSPATH') || exit;

if (!class_exists('ThaaniyamHub_Pickup_Manager')) {
class ThaaniyamHub_Pickup_Manager
{
    /** Transient key for cached pickup locations (10 min TTL). */
    const PICKUP_CACHE = 'ag_shiprocket_pickup_locations';

    // =========================================================================
    // BOOTSTRAP
    // =========================================================================

    public static function init()
    {
        // Admin menu.
        add_action('admin_menu', [__CLASS__, 'register_menu']);

        // Admin AJAX handlers.
        add_action('wp_ajax_thaaniyamhub_pm_get_locations', [__CLASS__, 'ajax_get_locations']);
        add_action('wp_ajax_thaaniyamhub_pm_assign_vendor', [__CLASS__, 'ajax_assign_vendor']);
        add_action('wp_ajax_thaaniyamhub_pm_refresh_cache', [__CLASS__, 'ajax_refresh_cache']);
    }

    // =========================================================================
    // ADMIN MENU
    // =========================================================================

    public static function register_menu()
    {
        add_submenu_page(
            'woocommerce',
            __('Pickup Location Manager', 'thaaniyamhub-multi-vendor-orders'),
            __('Pickup Locations', 'thaaniyamhub-multi-vendor-orders'),
            'manage_woocommerce',
            'thaaniyamhub-pickup-manager',
            [__CLASS__, 'render_page']
        );
    }

    // =========================================================================
    // ADMIN PAGE RENDER
    // =========================================================================

    public static function render_page()
    {
        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'locations';

        // Auto-scrub and synchronize WCFM vendor profiles to prevent any stale overrides
        self::sync_and_clean_all_vendor_profiles();

        // Fetch pickup locations (cached).
        $locations = self::get_cached_locations();

        // Build a normalized list of active location nicknames for rapid lookup
        $active_location_names = [];
        if (is_array($locations)) {
            foreach ($locations as $l) {
                if (!empty($l['pickup_location'])) {
                    $active_location_names[] = trim((string)$l['pickup_location']);
                }
            }
        }

        // Fetch all WCFM vendors.
        $vendors = self::get_all_vendors();

        // Separate assigned vs unassigned, and detect orphans.
        $assigned = [];
        $unassigned = [];
        $orphans_detected = 0;

        foreach ($vendors as $vendor) {
            $pickup = trim((string) get_user_meta($vendor->ID, '_shiprocket_pickup_id', true));
            if (!$pickup) {
                // Fallback to pickup map.
                $map = get_option('thaaniyamhub_vendor_pickup_map', []);
                $pickup = trim((string) ($map[$vendor->ID] ?? ''));
            }
            $vendor->current_pickup = $pickup;

            // Check if assigned pickup is in Shiprocket active locations
            $is_orphan = false;
            if ($pickup) {
                $is_orphan = !self::in_locations_case_insensitive($pickup, $active_location_names);
                if ($is_orphan) {
                    $orphans_detected++;
                    // Log orphan detection once per session/view if logger is loaded
                    if (class_exists('ThaaniyamHub_Pickup_Logger')) {
                        ThaaniyamHub_Pickup_Logger::log_event([
                            'event_type'  => 'ORPHAN_LOCATION_DETECTED',
                            'vendor_id'   => (int) $vendor->ID,
                            'old_value'   => $pickup,
                            'new_value'   => '',
                            'status'      => 'WARNING',
                            'message'     => sprintf(
                                'Vendor #%d (%s) is assigned to "%s", which was NOT found in active Shiprocket pickup locations.',
                                $vendor->ID,
                                $vendor->display_name,
                                $pickup
                            ),
                            'extra_data'  => [
                                'available_shiprocket_locations' => $active_location_names,
                            ],
                        ]);
                    }
                }
                $vendor->is_orphan = $is_orphan;
                $assigned[] = $vendor;
            } else {
                $vendor->is_orphan = false;
                $unassigned[] = $vendor;
            }
        }

        // Audit log stats
        $log_stats = ['total' => 0, 'file_size' => '0 KB'];
        if (class_exists('ThaaniyamHub_Pickup_Logger')) {
            $recent_logs = ThaaniyamHub_Pickup_Logger::get_recent_logs(1, 0);
            $log_stats['total'] = $recent_logs['total'];
            $log_stats['file_size'] = ThaaniyamHub_Pickup_Logger::get_log_file_size_formatted();
        }

        self::render_styles();
        ?>
        <div class="wrap thaaniyamhub-pm-wrap">
            <div class="thaaniyamhub-pm-header">
                <div class="thaaniyamhub-pm-header-left">
                    <div class="thaaniyamhub-pm-icon-badge">📍</div>
                    <div>
                        <h1 class="thaaniyamhub-pm-title">
                            <?php esc_html_e('Pickup Location Manager', 'thaaniyamhub-multi-vendor-orders'); ?>
                            <span class="thaaniyamhub-pm-subtitle"><?php esc_html_e('Shiprocket Fulfillment', 'thaaniyamhub-multi-vendor-orders'); ?></span>
                        </h1>
                        <p class="thaaniyamhub-pm-header-desc">
                            <?php esc_html_e('Manage registered Shiprocket pickup locations, vendor fulfillment assignments, and view comprehensive A-Z audit logs.', 'thaaniyamhub-multi-vendor-orders'); ?>
                        </p>
                    </div>
                </div>
                <div class="thaaniyamhub-pm-header-right">
                    <?php if ($orphans_detected > 0): ?>
                        <div class="thaaniyamhub-pm-alert-pill">
                            ⚠️ <?php printf(esc_html__('%d vendor(s) have missing Shiprocket locations', 'thaaniyamhub-multi-vendor-orders'), $orphans_detected); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (is_wp_error($locations)): ?>
                <div class="notice notice-error" style="border-radius: 8px; margin-bottom: 20px;">
                    <p>
                        <strong><?php esc_html_e('Error fetching locations:', 'thaaniyamhub-multi-vendor-orders'); ?></strong>
                        <?php echo esc_html($locations->get_error_message()); ?>
                        &nbsp; <a href="<?php echo esc_url(admin_url('admin.php?page=thaaniyamhub-pickup-manager&tab=locations&thaaniyamhub_refresh=1')); ?>"><?php esc_html_e('Retry', 'thaaniyamhub-multi-vendor-orders'); ?></a>
                    </p>
                </div>
            <?php endif; ?>

            <!-- TAB NAV -->
            <nav class="thaaniyamhub-pm-tabs">
                <a href="?page=thaaniyamhub-pickup-manager&tab=locations"
                    class="thaaniyamhub-pm-tab <?php echo $active_tab === 'locations' ? 'active' : ''; ?>">
                    📍 <?php esc_html_e('Pickup Locations', 'thaaniyamhub-multi-vendor-orders'); ?>
                    <span class="thaaniyamhub-pm-badge"><?php echo is_array($locations) ? count($locations) : 0; ?></span>
                </a>
                <a href="?page=thaaniyamhub-pickup-manager&tab=assigned"
                    class="thaaniyamhub-pm-tab <?php echo $active_tab === 'assigned' ? 'active' : ''; ?>">
                    👥 <?php esc_html_e('Vendor Assignment', 'thaaniyamhub-multi-vendor-orders'); ?>
                    <span class="thaaniyamhub-pm-badge"><?php echo count($assigned); ?></span>
                    <?php if ($orphans_detected > 0): ?>
                        <span class="thaaniyamhub-pm-badge thaaniyamhub-pm-badge-warn" title="<?php esc_attr_e('Orphaned / Missing in Shiprocket', 'thaaniyamhub-multi-vendor-orders'); ?>">⚠️ <?php echo $orphans_detected; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?page=thaaniyamhub-pickup-manager&tab=unassigned"
                    class="thaaniyamhub-pm-tab <?php echo $active_tab === 'unassigned' ? 'active' : ''; ?>">
                    ⚠️ <?php esc_html_e('Unassigned Vendors', 'thaaniyamhub-multi-vendor-orders'); ?>
                    <?php if (count($unassigned) > 0): ?>
                        <span class="thaaniyamhub-pm-badge thaaniyamhub-pm-badge-warn"><?php echo count($unassigned); ?></span>
                    <?php endif; ?>
                </a>
                <a href="?page=thaaniyamhub-pickup-manager&tab=logs"
                    class="thaaniyamhub-pm-tab <?php echo $active_tab === 'logs' ? 'active' : ''; ?>">
                    📜 <?php esc_html_e('Audit Logs (A-Z Tracker)', 'thaaniyamhub-multi-vendor-orders'); ?>
                    <span class="thaaniyamhub-pm-badge thaaniyamhub-pm-badge-info"><?php echo (int) $log_stats['total']; ?></span>
                </a>
            </nav>

            <!-- TAB CONTENT -->
            <div class="thaaniyamhub-pm-content">

                <?php if ($active_tab === 'locations'): ?>
                    <!-- ========== TAB 1: PICKUP LOCATIONS ========== -->
                    <div class="thaaniyamhub-pm-toolbar">
                        <div class="thaaniyamhub-pm-search-box">
                            <span class="thaaniyamhub-pm-search-icon">🔍</span>
                            <input type="search" id="ag-pm-loc-search"
                                placeholder="<?php esc_attr_e('Search locations, contact, city…', 'thaaniyamhub-multi-vendor-orders'); ?>">
                        </div>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span id="ag-pm-refresh-msg" class="thaaniyamhub-pm-msg"></span>
                            <button id="thaaniyamhub-pm-btn-refresh" class="button thaaniyamhub-pm-btn-refresh"
                                title="Refresh from Shiprocket">
                                <span class="thaaniyamhub-pm-spin-icon">↻</span> <?php esc_html_e('Refresh Locations', 'thaaniyamhub-multi-vendor-orders'); ?>
                            </button>
                        </div>
                    </div>

                    <?php if (is_array($locations) && !empty($locations)): ?>
                        <div class="thaaniyamhub-pm-location-grid" id="ag-pm-location-grid">
                            <?php foreach ($locations as $loc):
                                $loc_name = $loc['pickup_location'] ?? '—';
                                $contact_name = $loc['name'] ?? '—';
                                $phone = $loc['phone'] ?? '—';
                                $address = trim(($loc['address'] ?? '') . ' ' . ($loc['address_2'] ?? ''));
                                $city = $loc['city'] ?? '';
                                $state = $loc['state'] ?? '';
                                $pincode = $loc['pin_code'] ?? '';
                                $city_state = trim($city . ($city && $state ? ', ' : '') . $state . ($pincode ? ' — ' . $pincode : ''));

                                $raw_status = $loc['status'] ?? '';
                                $is_active = ('active' === strtolower((string)$raw_status) || 1 === (int) $raw_status || 2 === (int) $raw_status);
                                $is_pending = (2 === (int) $raw_status);

                                if ($is_pending) {
                                    $status_class = 'thaaniyamhub-pm-status-pending';
                                    $status_text = '✓ Active (Pending)';
                                } elseif ($is_active) {
                                    $status_class = 'thaaniyamhub-pm-status-active';
                                    $status_text = '✓ Active';
                                } else {
                                    $status_class = 'thaaniyamhub-pm-status-inactive';
                                    $status_text = '✗ Inactive';
                                }

                                $search_haystack = strtolower($loc_name . ' ' . $contact_name . ' ' . $phone . ' ' . $address . ' ' . $city_state);
                                $vendor_count = self::count_vendors_with_pickup($loc_name, $vendors);
                            ?>
                                <div class="thaaniyamhub-pm-location-card" data-search="<?php echo esc_attr($search_haystack); ?>">
                                    <div class="thaaniyamhub-pm-loc-header">
                                        <div class="thaaniyamhub-pm-loc-title-group">
                                            <div class="thaaniyamhub-pm-loc-icon-wrap">📦</div>
                                            <span class="thaaniyamhub-pm-loc-name" title="<?php echo esc_attr($loc_name); ?>"><?php echo esc_html($loc_name); ?></span>
                                        </div>
                                        <span class="thaaniyamhub-pm-status <?php echo esc_attr($status_class); ?>">
                                            <span class="thaaniyamhub-pm-status-dot"></span>
                                            <?php echo esc_html($status_text); ?>
                                        </span>
                                    </div>
                                    <div class="thaaniyamhub-pm-loc-body">
                                        <div class="thaaniyamhub-pm-info-row">
                                            <span class="thaaniyamhub-pm-info-icon">👤</span>
                                            <div class="thaaniyamhub-pm-info-content">
                                                <div class="thaaniyamhub-pm-info-label"><?php esc_html_e('Contact', 'thaaniyamhub-multi-vendor-orders'); ?></div>
                                                <div class="thaaniyamhub-pm-info-val"><?php echo esc_html($contact_name); ?></div>
                                            </div>
                                        </div>
                                        <div class="thaaniyamhub-pm-info-row">
                                            <span class="thaaniyamhub-pm-info-icon">📞</span>
                                            <div class="thaaniyamhub-pm-info-content">
                                                <div class="thaaniyamhub-pm-info-label"><?php esc_html_e('Phone', 'thaaniyamhub-multi-vendor-orders'); ?></div>
                                                <div class="thaaniyamhub-pm-info-val"><?php echo esc_html($phone); ?></div>
                                            </div>
                                        </div>
                                        <div class="thaaniyamhub-pm-info-row">
                                            <span class="thaaniyamhub-pm-info-icon">📍</span>
                                            <div class="thaaniyamhub-pm-info-content">
                                                <div class="thaaniyamhub-pm-info-label"><?php esc_html_e('Address', 'thaaniyamhub-multi-vendor-orders'); ?></div>
                                                <div class="thaaniyamhub-pm-info-val"><?php echo esc_html($address ?: '—'); ?></div>
                                            </div>
                                        </div>
                                        <div class="thaaniyamhub-pm-info-row">
                                            <span class="thaaniyamhub-pm-info-icon">🏙️</span>
                                            <div class="thaaniyamhub-pm-info-content">
                                                <div class="thaaniyamhub-pm-info-label"><?php esc_html_e('City / State', 'thaaniyamhub-multi-vendor-orders'); ?></div>
                                                <div class="thaaniyamhub-pm-info-val"><?php echo esc_html($city_state ?: '—'); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="thaaniyamhub-pm-loc-footer">
                                        <span class="thaaniyamhub-pm-vendor-pill <?php echo $vendor_count > 0 ? 'assigned' : 'none'; ?>">
                                            👥 <?php printf(_n('%d vendor assigned', '%d vendors assigned', $vendor_count, 'thaaniyamhub-multi-vendor-orders'), $vendor_count); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif (is_array($locations)): ?>
                        <div class="thaaniyamhub-pm-empty">
                            <span>📭</span>
                            <p><?php esc_html_e('No pickup locations found in Shiprocket.', 'thaaniyamhub-multi-vendor-orders'); ?></p>
                        </div>
                    <?php endif; ?>

                <?php elseif ($active_tab === 'assigned'): ?>
                    <!-- ========== TAB 2: VENDOR ASSIGNMENT ========== -->
                    <div class="thaaniyamhub-pm-toolbar">
                        <div class="thaaniyamhub-pm-search-box">
                            <span class="thaaniyamhub-pm-search-icon">🔍</span>
                            <input type="search" id="ag-pm-vendor-search"
                                placeholder="<?php esc_attr_e('Search vendors by name or store…', 'thaaniyamhub-multi-vendor-orders'); ?>">
                        </div>
                        <span id="ag-pm-assign-msg" class="thaaniyamhub-pm-msg"></span>
                    </div>

                    <?php if ($orphans_detected > 0): ?>
                        <div class="notice notice-warning inline" style="margin: 0 0 20px 0; border-radius: 8px;">
                            <p>
                                <strong>⚠️ <?php esc_html_e('Location Name Mismatches Detected:', 'thaaniyamhub-multi-vendor-orders'); ?></strong>
                                <?php printf(
                                    esc_html__('%d vendor(s) have a saved pickup location that no longer exists in Shiprocket (or was renamed). We have preserved these assignments so they are not lost. Please select a valid active location and click Save.', 'thaaniyamhub-multi-vendor-orders'),
                                    $orphans_detected
                                ); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <div class="thaaniyamhub-pm-table-card">
                        <table class="thaaniyamhub-pm-table" id="ag-pm-vendor-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Vendor', 'thaaniyamhub-multi-vendor-orders'); ?></th>
                                    <th><?php esc_html_e('Store', 'thaaniyamhub-multi-vendor-orders'); ?></th>
                                    <th><?php esc_html_e('Pickup Location', 'thaaniyamhub-multi-vendor-orders'); ?></th>
                                    <th><?php esc_html_e('Shiprocket Status', 'thaaniyamhub-multi-vendor-orders'); ?></th>
                                    <th style="width: 140px;"><?php esc_html_e('Action', 'thaaniyamhub-multi-vendor-orders'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vendors as $vendor):
                                    $store_name = self::get_vendor_store_name($vendor->ID);
                                    $current_val = $vendor->current_pickup;
                                    $has_exact_match = false;
                                    ?>
                                    <tr class="thaaniyamhub-pm-vendor-row <?php echo $vendor->is_orphan ? 'thaaniyamhub-pm-row-orphan' : ''; ?>"
                                        data-vendor="<?php echo esc_attr(strtolower($vendor->display_name . ' ' . $store_name . ' ' . $vendor->user_email)); ?>">
                                        <td>
                                            <strong style="color: #0f172a; font-size: 14px;"><?php echo esc_html($vendor->display_name); ?></strong>
                                            <br><small style="color:#64748b;">ID: <?php echo (int) $vendor->ID; ?> · <?php echo esc_html($vendor->user_email); ?></small>
                                        </td>
                                        <td><span style="font-weight: 500; color: #334155;"><?php echo esc_html($store_name ?: '—'); ?></span></td>
                                        <td>
                                            <select class="thaaniyamhub-pm-pickup-select" data-vendor-id="<?php echo (int) $vendor->ID; ?>">
                                                <option value="">
                                                    <?php esc_html_e('— Not Assigned —', 'thaaniyamhub-multi-vendor-orders'); ?>
                                                </option>
                                                <?php if (is_array($locations)): ?>
                                                    <?php foreach ($locations as $loc):
                                                        $nick = $loc['pickup_location'] ?? '';
                                                        $is_selected = (strcasecmp(trim($nick), trim($current_val)) === 0);
                                                        if ($is_selected) {
                                                            $has_exact_match = true;
                                                        }
                                                        ?>
                                                        <option value="<?php echo esc_attr($nick); ?>" <?php selected($is_selected, true); ?>>
                                                            <?php echo esc_html($nick); ?>
                                                            <?php
                                                            $raw_status = $loc['status'] ?? '';
                                                            $is_active = ('active' === strtolower((string)$raw_status) || 1 === (int) $raw_status || 2 === (int) $raw_status);
                                                            if (!$is_active): ?>
                                                                (<?php esc_html_e('Inactive', 'thaaniyamhub-multi-vendor-orders'); ?>)
                                                            <?php endif; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>

                                                <?php if ($current_val && !$has_exact_match): ?>
                                                    <!-- Safeguard: Preserve existing saved value so it is NOT wiped by browser fallback -->
                                                    <option value="<?php echo esc_attr($current_val); ?>" selected class="thaaniyamhub-pm-opt-orphan">
                                                        ⚠️ <?php echo esc_html($current_val); ?> (<?php esc_html_e('Not Found in Shiprocket', 'thaaniyamhub-multi-vendor-orders'); ?>)
                                                    </option>
                                                <?php endif; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <?php if (!$current_val): ?>
                                                <span class="thaaniyamhub-pm-status-pill none">— <?php esc_html_e('Unassigned', 'thaaniyamhub-multi-vendor-orders'); ?></span>
                                            <?php elseif ($vendor->is_orphan): ?>
                                                <span class="thaaniyamhub-pm-status-pill warn" title="<?php esc_attr_e('Assigned location was not found in Shiprocket account. It may have been renamed or deleted.', 'thaaniyamhub-multi-vendor-orders'); ?>">
                                                    ⚠️ <?php esc_html_e('Missing / Renamed', 'thaaniyamhub-multi-vendor-orders'); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="thaaniyamhub-pm-status-pill valid">✓ <?php esc_html_e('Valid & Synced', 'thaaniyamhub-multi-vendor-orders'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" class="button thaaniyamhub-pm-btn-save thaaniyamhub-pm-save-assign"
                                                data-vendor-id="<?php echo (int) $vendor->ID; ?>">
                                                <?php esc_html_e('Save', 'thaaniyamhub-multi-vendor-orders'); ?>
                                            </button>
                                            <span class="thaaniyamhub-pm-row-msg" style="margin-left:8px;"></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($active_tab === 'unassigned'): ?>
                    <!-- ========== TAB 3: UNASSIGNED VENDORS ========== -->
                    <?php if (empty($unassigned)): ?>
                        <div class="thaaniyamhub-pm-empty thaaniyamhub-pm-empty-success">
                            <span>✅</span>
                            <p><?php esc_html_e('All vendors have a pickup location assigned. Great job!', 'thaaniyamhub-multi-vendor-orders'); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="notice notice-warning inline" style="margin:0 0 20px 0; border-radius: 8px;">
                            <p><?php printf(
                                esc_html__('%d vendor(s) have no pickup location. Orders from these vendors cannot be dispatched to Shiprocket until a pickup is assigned.', 'thaaniyamhub-multi-vendor-orders'),
                                count($unassigned)
                            ); ?></p>
                        </div>

                        <div class="thaaniyamhub-pm-table-card">
                            <table class="thaaniyamhub-pm-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Vendor', 'thaaniyamhub-multi-vendor-orders'); ?></th>
                                        <th><?php esc_html_e('Store', 'thaaniyamhub-multi-vendor-orders'); ?></th>
                                        <th><?php esc_html_e('Quick Assign Pickup', 'thaaniyamhub-multi-vendor-orders'); ?></th>
                                        <th style="width: 140px;"><?php esc_html_e('Action', 'thaaniyamhub-multi-vendor-orders'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($unassigned as $vendor):
                                        $store_name = self::get_vendor_store_name($vendor->ID);
                                        ?>
                                        <tr class="thaaniyamhub-pm-vendor-row">
                                            <td>
                                                <strong style="color: #0f172a; font-size: 14px;"><?php echo esc_html($vendor->display_name); ?></strong>
                                                <br><small style="color:#64748b;">ID: <?php echo (int) $vendor->ID; ?></small>
                                            </td>
                                            <td><span style="font-weight: 500; color: #334155;"><?php echo esc_html($store_name ?: '—'); ?></span></td>
                                            <td>
                                                <select class="thaaniyamhub-pm-pickup-select" data-vendor-id="<?php echo (int) $vendor->ID; ?>">
                                                    <option value="">
                                                        <?php esc_html_e('— Select Pickup —', 'thaaniyamhub-multi-vendor-orders'); ?>
                                                    </option>
                                                    <?php if (is_array($locations)): ?>
                                                        <?php foreach ($locations as $loc):
                                                            $nick = $loc['pickup_location'] ?? '';
                                                            ?>
                                                            <option value="<?php echo esc_attr($nick); ?>"><?php echo esc_html($nick); ?></option>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <button type="button" class="button thaaniyamhub-pm-btn-save thaaniyamhub-pm-save-assign"
                                                    data-vendor-id="<?php echo (int) $vendor->ID; ?>">
                                                    <?php esc_html_e('Assign', 'thaaniyamhub-multi-vendor-orders'); ?>
                                                </button>
                                                <span class="thaaniyamhub-pm-row-msg" style="margin-left:8px;"></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                <?php elseif ($active_tab === 'logs'): ?>
                    <!-- ========== TAB 4: AUDIT LOGS (A-Z TRACKER) ========== -->
                    <div class="thaaniyamhub-pm-logs-header">
                        <div class="thaaniyamhub-pm-logs-summary">
                            <div class="thaaniyamhub-pm-stat-box">
                                <span class="num" id="ag-pm-log-total-count"><?php echo (int) $log_stats['total']; ?></span>
                                <span class="lbl"><?php esc_html_e('Total Audit Records', 'thaaniyamhub-multi-vendor-orders'); ?></span>
                            </div>
                            <div class="thaaniyamhub-pm-stat-box">
                                <span class="num" id="ag-pm-log-file-size"><?php echo esc_html($log_stats['file_size']); ?></span>
                                <span class="lbl"><?php esc_html_e('Log File Size', 'thaaniyamhub-multi-vendor-orders'); ?></span>
                            </div>
                        </div>

                        <div class="thaaniyamhub-pm-logs-actions">
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-ajax.php?action=thaaniyamhub_pm_download_audit_logs'), 'thaaniyamhub_pm_download_logs')); ?>"
                                class="button thaaniyamhub-pm-btn-action" target="_blank">
                                ⬇️ <?php esc_html_e('Download Raw Log File', 'thaaniyamhub-multi-vendor-orders'); ?>
                            </a>
                            <button type="button" id="thaaniyamhub-pm-btn-refresh-logs" class="button thaaniyamhub-pm-btn-action">
                                🔄 <?php esc_html_e('Refresh Logs', 'thaaniyamhub-multi-vendor-orders'); ?>
                            </button>
                            <button type="button" id="thaaniyamhub-pm-btn-clear-logs" class="button thaaniyamhub-pm-btn-danger">
                                🗑️ <?php esc_html_e('Clear Audit Logs', 'thaaniyamhub-multi-vendor-orders'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- LOG FILTERS -->
                    <div class="thaaniyamhub-pm-toolbar" style="margin-top: 15px;">
                        <div class="thaaniyamhub-pm-search-box" style="flex: 2;">
                            <span class="thaaniyamhub-pm-search-icon">🔍</span>
                            <input type="search" id="ag-pm-audit-search"
                                placeholder="<?php esc_attr_e('Search by vendor, username, IP, old/new value, message…', 'thaaniyamhub-multi-vendor-orders'); ?>">
                        </div>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <select id="ag-pm-audit-event-filter" class="thaaniyamhub-pm-filter-select">
                                <option value=""><?php esc_html_e('— All Event Types —', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                                <option value="USER_META_UPDATED">USER_META_UPDATED</option>
                                <option value="USER_META_ADDED">USER_META_ADDED</option>
                                <option value="USER_META_DELETED">USER_META_DELETED</option>
                                <option value="OPTION_MAP_UPDATED">OPTION_MAP_UPDATED</option>
                                <option value="AJAX_ASSIGN_VENDOR">AJAX_ASSIGN_VENDOR</option>
                                <option value="WCFM_SETTINGS_SAVE">WCFM_SETTINGS_SAVE</option>
                                <option value="WP_PROFILE_SAVE">WP_PROFILE_SAVE</option>
                                <option value="ORDER_SET_DEFAULT">ORDER_SET_DEFAULT</option>
                                <option value="ORPHAN_LOCATION_DETECTED">ORPHAN_LOCATION_DETECTED</option>
                                <option value="SHIPROCKET_API_FETCH">SHIPROCKET_API_FETCH</option>
                            </select>

                            <select id="ag-pm-audit-status-filter" class="thaaniyamhub-pm-filter-select">
                                <option value=""><?php esc_html_e('— All Statuses —', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                                <option value="CHANGED"><?php esc_html_e('CHANGED', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                                <option value="NO_CHANGE"><?php esc_html_e('NO_CHANGE', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                                <option value="WARNING"><?php esc_html_e('WARNING', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                                <option value="INFO"><?php esc_html_e('INFO', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                                <option value="ERROR"><?php esc_html_e('ERROR', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- AUDIT LOG TABLE -->
                    <div class="thaaniyamhub-pm-table-card" style="margin-top: 15px;">
                        <div id="ag-pm-logs-loading" style="display:none; padding: 20px; text-align: center; color: #64748b;">
                            <span class="thaaniyamhub-pm-spin-icon" style="display:inline-block;">↻</span> <?php esc_html_e('Loading audit records…', 'thaaniyamhub-multi-vendor-orders'); ?>
                        </div>
                        <table class="thaaniyamhub-pm-table" id="ag-pm-audit-table">
                            <thead>
                                <tr>
                                    <th style="width: 170px;"><?php esc_html_e('Time (Local / UTC)', 'thaaniyamhub-multi-vendor-orders'); ?></th>
                                    <th><?php esc_html_e('Event Type', 'thaaniyamhub-multi-vendor-orders'); ?></th>
                                    <th><?php esc_html_e('Target Vendor', 'thaaniyamhub-multi-vendor-orders'); ?></th>
                                    <th><?php esc_html_e('Change (Old ➔ New)', 'thaaniyamhub-multi-vendor-orders'); ?></th>
                                    <th><?php esc_html_e('Actor (Who)', 'thaaniyamhub-multi-vendor-orders'); ?></th>
                                    <th><?php esc_html_e('IP & Request', 'thaaniyamhub-multi-vendor-orders'); ?></th>
                                    <th style="width: 90px; text-align: right;"><?php esc_html_e('Details', 'thaaniyamhub-multi-vendor-orders'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="ag-pm-audit-tbody">
                                <!-- Populated dynamically by JavaScript -->
                            </tbody>
                        </table>
                        <div id="ag-pm-audit-empty" class="thaaniyamhub-pm-empty" style="display:none;">
                            <span>📭</span>
                            <p><?php esc_html_e('No audit records found matching your filters.', 'thaaniyamhub-multi-vendor-orders'); ?></p>
                        </div>
                    </div>

                <?php endif; ?>

            </div><!-- .thaaniyamhub-pm-content -->
        </div><!-- .thaaniyamhub-pm-wrap -->

        <!-- MODAL FOR FULL A-Z LOG DETAILS -->
        <div id="thaaniyamhub-pm-log-modal" class="thaaniyamhub-pm-modal" style="display: none;">
            <div class="thaaniyamhub-pm-modal-overlay"></div>
            <div class="thaaniyamhub-pm-modal-dialog">
                <div class="thaaniyamhub-pm-modal-header">
                    <h3 id="thaaniyamhub-pm-modal-title">🔍 <?php esc_html_e('A-Z Audit Log Details', 'thaaniyamhub-multi-vendor-orders'); ?></h3>
                    <button type="button" class="thaaniyamhub-pm-modal-close">&times;</button>
                </div>
                <div class="thaaniyamhub-pm-modal-body" id="thaaniyamhub-pm-modal-content">
                    <!-- JSON and details injected here -->
                </div>
                <div class="thaaniyamhub-pm-modal-footer">
                    <button type="button" class="button thaaniyamhub-pm-modal-close"><?php esc_html_e('Close', 'thaaniyamhub-multi-vendor-orders'); ?></button>
                </div>
            </div>
        </div>

        <?php self::render_scripts(); ?>
    <?php
    }

    // =========================================================================
    // AJAX HANDLERS
    // =========================================================================

    public static function ajax_refresh_cache()
    {
        check_ajax_referer('thaaniyamhub_pm_nonce', '_nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions.', 403);
        }

        delete_transient(self::PICKUP_CACHE);
        $locations = self::get_cached_locations(true);

        if (is_wp_error($locations)) {
            wp_send_json_error($locations->get_error_message());
        }

        if (class_exists('ThaaniyamHub_Pickup_Logger')) {
            ThaaniyamHub_Pickup_Logger::log_event([
                'event_type' => 'SHIPROCKET_CACHE_REFRESH',
                'vendor_id'  => 0,
                'status'     => 'INFO',
                'message'    => sprintf('Shiprocket pickup locations cache refreshed by admin. Loaded %d location(s).', count($locations)),
                'extra_data' => ['locations_count' => count($locations)],
            ]);
        }

        wp_send_json_success([
            'count' => count($locations),
            'message' => sprintf(
                __('Refreshed — %d pickup locations loaded from Shiprocket.', 'thaaniyamhub-multi-vendor-orders'),
                count($locations)
            ),
        ]);
    }

    public static function ajax_assign_vendor()
    {
        check_ajax_referer('thaaniyamhub_pm_nonce', '_nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions.', 403);
        }

        $vendor_id = (int) ($_POST['vendor_id'] ?? 0);
        $pickup_location = sanitize_text_field($_POST['pickup_location'] ?? '');

        if (!$vendor_id) {
            wp_send_json_error(__('Invalid vendor ID.', 'thaaniyamhub-multi-vendor-orders'));
        }

        $vendor = get_userdata($vendor_id);
        if ($vendor === false) {
            wp_send_json_error(__('Vendor not found.', 'thaaniyamhub-multi-vendor-orders'));
        }

        // Centralized save across user meta, option map, and WCFM profile settings
        self::set_vendor_pickup_location($vendor_id, $pickup_location, 'AJAX_ASSIGN_VENDOR');

        wp_send_json_success([
            'vendor_id' => $vendor_id,
            'pickup' => $pickup_location,
            'message' => $pickup_location
                ? sprintf(__('✅ %s assigned to "%s"', 'thaaniyamhub-multi-vendor-orders'), $vendor->display_name, $pickup_location)
                : sprintf(__('✅ Pickup removed from %s', 'thaaniyamhub-multi-vendor-orders'), $vendor->display_name),
        ]);
    }

    /**
     * Centralized setter to update vendor pickup location across all persistent stores:
     * 1. User meta '_shiprocket_pickup_id'
     * 2. Option 'thaaniyamhub_vendor_pickup_map' & JSON backup
     * 3. WCFM vendor profile settings 'wcfmmp_profile_settings' (keeps 'shiprocket_pickup_id' in sync to prevent stale overrides)
     *
     * @param int $vendor_id
     * @param string $pickup_location
     * @param string $source Context of the update (e.g. AJAX_ASSIGN_VENDOR, WP_PROFILE_SAVE, WCFM_SETTINGS_SAVE, ORDER_SET_DEFAULT)
     * @return array
     */
    public static function set_vendor_pickup_location(int $vendor_id, string $pickup_location, string $source = 'AJAX_ASSIGN_VENDOR'): array
    {
        $vendor_id = (int) $vendor_id;
        $pickup_location = sanitize_text_field(trim($pickup_location));
        $old_pickup = (string) get_user_meta($vendor_id, '_shiprocket_pickup_id', true);

        // 1. Update user meta
        if ($pickup_location) {
            update_user_meta($vendor_id, '_shiprocket_pickup_id', $pickup_location);
        } else {
            delete_user_meta($vendor_id, '_shiprocket_pickup_id');
        }

        // 2. Synchronize option map
        $map = get_option('thaaniyamhub_vendor_pickup_map', []);
        if (!is_array($map)) {
            $map = [];
        }
        if ($pickup_location) {
            $map[$vendor_id] = $pickup_location;
        } else {
            unset($map[$vendor_id]);
        }
        update_option('thaaniyamhub_vendor_pickup_map', $map);
        update_option('thaaniyamhub_vendor_pickup_map_json', wp_json_encode($map, JSON_PRETTY_PRINT));

        // 3. Synchronize WCFM profile settings to remove stale values
        $wcfm_profile = get_user_meta($vendor_id, 'wcfmmp_profile_settings', true);
        if (is_array($wcfm_profile)) {
            if ($pickup_location) {
                $wcfm_profile['shiprocket_pickup_id'] = $pickup_location;
            } else {
                unset($wcfm_profile['shiprocket_pickup_id']);
            }
            update_user_meta($vendor_id, 'wcfmmp_profile_settings', $wcfm_profile);
        }

        // 4. Log event if logger exists
        if (class_exists('ThaaniyamHub_Pickup_Logger')) {
            $vendor = get_userdata($vendor_id);
            $vendor_name = $vendor ? $vendor->display_name : "Vendor #{$vendor_id}";
            ThaaniyamHub_Pickup_Logger::log_event([
                'event_type' => $source,
                'vendor_id'  => $vendor_id,
                'old_value'  => $old_pickup,
                'new_value'  => $pickup_location,
                'status'     => ($old_pickup === $pickup_location) ? 'NO_CHANGE' : 'CHANGED',
                'message'    => sprintf(
                    'Pickup assignment for vendor #%d (%s) set from "%s" to "%s" via %s.',
                    $vendor_id,
                    $vendor_name,
                    $old_pickup ?: '[None]',
                    $pickup_location ?: '[None]',
                    $source
                ),
            ]);
        }

        return [
            'vendor_id'   => $vendor_id,
            'old_pickup'  => $old_pickup,
            'new_pickup'  => $pickup_location,
            'changed'     => ($old_pickup !== $pickup_location),
        ];
    }

    /**
     * Scrub and synchronize all vendor profiles to ensure wcfmmp_profile_settings
     * does not contain stale pickup locations (like 'warehouse' or 'warehouse-1').
     */
    public static function sync_and_clean_all_vendor_profiles(): array
    {
        $vendors = self::get_all_vendors();
        $cleaned = [];

        foreach ($vendors as $vendor) {
            $vid = (int) $vendor->ID;
            $active_pickup = trim((string) get_user_meta($vid, '_shiprocket_pickup_id', true));
            if (!$active_pickup) {
                $map = get_option('thaaniyamhub_vendor_pickup_map', []);
                $active_pickup = trim((string) ($map[$vid] ?? ''));
            }

            $wcfm_profile = get_user_meta($vid, 'wcfmmp_profile_settings', true);
            if (is_array($wcfm_profile)) {
                $stale_val = $wcfm_profile['shiprocket_pickup_id'] ?? null;
                if ($stale_val !== $active_pickup) {
                    if ($active_pickup) {
                        $wcfm_profile['shiprocket_pickup_id'] = $active_pickup;
                    } else {
                        unset($wcfm_profile['shiprocket_pickup_id']);
                    }
                    update_user_meta($vid, 'wcfmmp_profile_settings', $wcfm_profile);
                    $cleaned[$vid] = [
                        'vendor'            => $vendor->display_name,
                        'stale_profile_val' => $stale_val,
                        'active_pickup'     => $active_pickup,
                    ];
                }
            }
        }

        return $cleaned;
    }

    public static function get_cached_locations(bool $force = false)
    {
        if (!$force && isset($_GET['thaaniyamhub_refresh'])) {
            $force = true;
        }

        if (!$force) {
            $cached = get_transient(self::PICKUP_CACHE);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $api = new ThaaniyamHub_Shiprocket_API();
        $response = $api->get_pickup_addresses();

        if (is_wp_error($response)) {
            if (class_exists('ThaaniyamHub_Pickup_Logger')) {
                ThaaniyamHub_Pickup_Logger::log_event([
                    'event_type' => 'SHIPROCKET_API_FETCH',
                    'vendor_id'  => 0,
                    'status'     => 'ERROR',
                    'message'    => 'Failed to fetch Shiprocket pickup addresses: ' . $response->get_error_message(),
                ]);
            }
            return $response;
        }

        $locations = $response['data']['shipping_address'] ?? [];

        usort($locations, fn($a, $b) => strcmp($a['pickup_location'] ?? '', $b['pickup_location'] ?? ''));

        set_transient(self::PICKUP_CACHE, $locations, 10 * MINUTE_IN_SECONDS);

        return $locations;
    }

    public static function get_all_vendors(): array
    {
        if (function_exists('wcfmmp_get_vendor_ids')) {
            $ids = wcfmmp_get_vendor_ids();
            if (!empty($ids)) {
                $users = get_users([
                    'include' => $ids,
                    'orderby' => 'display_name',
                    'order' => 'ASC',
                ]);
                return is_array($users) ? $users : [];
            }
        }

        $roles = ['wcfm_vendor', 'seller', 'vendor'];
        foreach ($roles as $role) {
            $users = get_users([
                'role' => $role,
                'orderby' => 'display_name',
                'order' => 'ASC',
            ]);
            if (!empty($users)) {
                return $users;
            }
        }

        return [];
    }

    private static function in_locations_case_insensitive(string $needle, array $haystack): bool
    {
        $needle_clean = strtolower(trim($needle));
        foreach ($haystack as $item) {
            if (strtolower(trim((string)$item)) === $needle_clean) {
                return true;
            }
        }
        return false;
    }

    private static function get_vendor_store_name(int $vendor_id): string
    {
        if (function_exists('wcfmmp_get_store')) {
            $store = wcfmmp_get_store($vendor_id);
            if ($store) {
                $info = $store->get_shop_info();
                return trim($info['store_name'] ?? '');
            }
        }
        return (string) get_user_meta($vendor_id, 'store_name', true) ?: '';
    }

    private static function count_vendors_with_pickup(string $pickup_name, array $vendors): int
    {
        if (!$pickup_name) {
            return 0;
        }
        $pickup_clean = strtolower(trim($pickup_name));
        return count(array_filter($vendors, function($v) use ($pickup_clean) {
            return strtolower(trim((string)$v->current_pickup)) === $pickup_clean;
        }));
    }

    private static function render_styles()
    {
        ?>
        <style>
            .thaaniyamhub-pm-wrap {
                max-width: 100%;
                margin: 15px 20px 30px 0;
                box-sizing: border-box;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                color: #1e293b;
            }

            .thaaniyamhub-pm-header {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 14px;
                padding: 20px 24px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                box-shadow: 0 1px 3px rgba(0,0,0,0.03);
                margin-bottom: 24px;
            }

            .thaaniyamhub-pm-header-left {
                display: flex;
                align-items: center;
                gap: 16px;
            }

            .thaaniyamhub-pm-icon-badge {
                width: 48px;
                height: 48px;
                background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
                border: 1px solid #bfdbfe;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 24px;
                flex-shrink: 0;
            }

            .thaaniyamhub-pm-title {
                margin: 0;
                font-size: 20px;
                font-weight: 700;
                color: #0f172a;
                display: flex;
                align-items: center;
                gap: 10px;
                line-height: 1.3;
                flex-wrap: wrap;
            }

            .thaaniyamhub-pm-subtitle {
                display: inline-block;
                font-size: 11px;
                font-weight: 600;
                background: #f1f5f9;
                color: #475569;
                border: 1px solid #cbd5e1;
                border-radius: 20px;
                padding: 2px 10px;
                letter-spacing: 0.3px;
                text-transform: uppercase;
            }

            .thaaniyamhub-pm-header-desc {
                margin: 4px 0 0 0;
                font-size: 13px;
                color: #64748b;
            }

            .thaaniyamhub-pm-alert-pill {
                background: #fef2f2;
                border: 1px solid #fecaca;
                color: #b91c1c;
                font-size: 12px;
                font-weight: 600;
                padding: 6px 14px;
                border-radius: 20px;
            }

            .thaaniyamhub-pm-tabs {
                display: flex;
                gap: 8px;
                background: #f8fafc;
                padding: 6px;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                margin-bottom: 24px;
                flex-wrap: wrap;
            }

            .thaaniyamhub-pm-tab {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 10px 18px;
                text-decoration: none;
                color: #64748b;
                font-size: 13px;
                font-weight: 600;
                border-radius: 8px;
                transition: all 0.2s ease;
            }

            .thaaniyamhub-pm-tab:hover {
                color: #0f172a;
                background: rgba(255,255,255,0.6);
            }

            .thaaniyamhub-pm-tab.active {
                color: #2563eb;
                background: #ffffff;
                box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            }

            .thaaniyamhub-pm-badge {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: #e2e8f0;
                color: #334155;
                border-radius: 20px;
                font-size: 11px;
                font-weight: 700;
                padding: 2px 8px;
                min-width: 18px;
            }

            .thaaniyamhub-pm-badge-warn {
                background: #fef3c7 !important;
                color: #b45309 !important;
            }

            .thaaniyamhub-pm-badge-info {
                background: #e0f2fe !important;
                color: #0369a1 !important;
            }

            .thaaniyamhub-pm-tab.active .thaaniyamhub-pm-badge {
                background: #eff6ff;
                color: #2563eb;
            }

            .thaaniyamhub-pm-toolbar {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 16px;
                margin-bottom: 20px;
                flex-wrap: wrap;
            }

            .thaaniyamhub-pm-search-box {
                position: relative;
                flex: 1;
                max-width: 380px;
                min-width: 240px;
            }

            .thaaniyamhub-pm-search-icon {
                position: absolute;
                left: 12px;
                top: 50%;
                transform: translateY(-50%);
                font-size: 14px;
                pointer-events: none;
                opacity: 0.6;
            }

            .thaaniyamhub-pm-search-box input[type="search"] {
                width: 100%;
                padding: 8px 12px 8px 36px !important;
                border: 1px solid #cbd5e1 !important;
                border-radius: 8px !important;
                font-size: 13px !important;
                background: #ffffff !important;
                height: 38px;
            }

            .thaaniyamhub-pm-filter-select {
                padding: 6px 12px !important;
                border: 1px solid #cbd5e1 !important;
                border-radius: 8px !important;
                font-size: 13px !important;
                background: #ffffff !important;
                height: 38px;
                color: #334155;
            }

            .thaaniyamhub-pm-btn-refresh, .thaaniyamhub-pm-btn-action {
                height: 38px !important;
                padding: 0 16px !important;
                border-radius: 8px !important;
                display: inline-flex !important;
                align-items: center !important;
                gap: 6px !important;
                font-size: 13px !important;
                font-weight: 600 !important;
                cursor: pointer !important;
            }

            .thaaniyamhub-pm-btn-danger {
                height: 38px !important;
                padding: 0 16px !important;
                border-radius: 8px !important;
                display: inline-flex !important;
                align-items: center !important;
                gap: 6px !important;
                font-size: 13px !important;
                font-weight: 600 !important;
                background: #fee2e2 !important;
                border-color: #fca5a5 !important;
                color: #991b1b !important;
                cursor: pointer !important;
            }

            .thaaniyamhub-pm-btn-danger:hover {
                background: #fecaca !important;
                color: #7f1d1d !important;
            }

            .thaaniyamhub-pm-table-card {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 14px;
                overflow: hidden;
                box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            }

            .thaaniyamhub-pm-table {
                width: 100%;
                border-collapse: collapse;
                text-align: left;
            }

            .thaaniyamhub-pm-table th {
                background: #f8fafc;
                padding: 12px 18px;
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                color: #475569;
                border-bottom: 1px solid #e2e8f0;
            }

            .thaaniyamhub-pm-table td {
                padding: 14px 18px;
                border-bottom: 1px solid #f1f5f9;
                font-size: 13px;
                color: #334155;
                vertical-align: middle;
            }

            .thaaniyamhub-pm-table tr:hover td {
                background: #f8fafc;
            }

            .thaaniyamhub-pm-row-orphan td {
                background: #fffbeb !important;
            }

            .thaaniyamhub-pm-pickup-select {
                max-width: 260px;
                width: 100%;
                border: 1px solid #cbd5e1 !important;
                border-radius: 8px !important;
                padding: 6px 10px !important;
                font-size: 13px !important;
            }

            .thaaniyamhub-pm-opt-orphan {
                color: #b91c1c;
                font-weight: 700;
                background: #fef2f2;
            }

            .thaaniyamhub-pm-status-pill {
                display: inline-block;
                padding: 3px 10px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 700;
            }

            .thaaniyamhub-pm-status-pill.valid {
                background: #ecfdf5;
                color: #065f46;
                border: 1px solid #a7f3d0;
            }

            .thaaniyamhub-pm-status-pill.warn {
                background: #fffbeb;
                color: #92400e;
                border: 1px solid #fde68a;
            }

            .thaaniyamhub-pm-status-pill.none {
                background: #f1f5f9;
                color: #64748b;
                border: 1px solid #cbd5e1;
            }

            .thaaniyamhub-pm-btn-save {
                background: #2563eb !important;
                border-color: #2563eb !important;
                color: #ffffff !important;
                border-radius: 6px !important;
                font-weight: 600 !important;
                padding: 4px 14px !important;
                height: 32px !important;
            }

            .thaaniyamhub-pm-btn-save:hover {
                background: #1d4ed8 !important;
            }

            /* Event Type Badges */
            .ag-badge-event {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 6px;
                font-size: 10px;
                font-weight: 700;
                letter-spacing: 0.3px;
                font-family: monospace;
            }
            .ag-badge-USER_META_UPDATED { background: #dbeafe; color: #1e40af; }
            .ag-badge-USER_META_DELETED { background: #fee2e2; color: #991b1b; }
            .ag-badge-OPTION_MAP_UPDATED { background: #fef3c7; color: #92400e; }
            .ag-badge-AJAX_ASSIGN_VENDOR { background: #d1fae5; color: #065f46; }
            .ag-badge-WCFM_SETTINGS_SAVE { background: #ede9fe; color: #5b21b6; }
            .ag-badge-WP_PROFILE_SAVE { background: #e0e7ff; color: #3730a3; }
            .ag-badge-ORDER_SET_DEFAULT { background: #fae8ff; color: #86198f; }
            .ag-badge-ORPHAN_LOCATION_DETECTED { background: #ffedd5; color: #9a3412; }
            .ag-badge-SHIPROCKET_API_FETCH { background: #f1f5f9; color: #334155; }

            /* Value Change Diff */
            .ag-diff-val {
                font-family: monospace;
                font-size: 11px;
                padding: 2px 6px;
                border-radius: 4px;
            }
            .ag-diff-old { background: #fee2e2; color: #991b1b; text-decoration: line-through; }
            .ag-diff-new { background: #dcfce7; color: #166534; font-weight: bold; }
            .ag-diff-arrow { color: #94a3b8; font-weight: bold; margin: 0 4px; }

            /* Logs Header summary */
            .thaaniyamhub-pm-logs-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                padding: 16px 20px;
                flex-wrap: wrap;
                gap: 16px;
            }
            .thaaniyamhub-pm-logs-summary {
                display: flex;
                gap: 20px;
            }
            .thaaniyamhub-pm-stat-box {
                display: flex;
                flex-direction: column;
            }
            .thaaniyamhub-pm-stat-box .num {
                font-size: 20px;
                font-weight: 800;
                color: #0f172a;
            }
            .thaaniyamhub-pm-stat-box .lbl {
                font-size: 11px;
                color: #64748b;
                font-weight: 500;
                text-transform: uppercase;
            }
            .thaaniyamhub-pm-logs-actions {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }

            /* Location Grid Cards */
            .thaaniyamhub-pm-location-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
                gap: 20px;
            }
            .thaaniyamhub-pm-location-card {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                padding: 18px;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
            }
            .thaaniyamhub-pm-loc-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 12px;
                padding-bottom: 10px;
                border-bottom: 1px solid #f1f5f9;
            }
            .thaaniyamhub-pm-loc-title-group {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .thaaniyamhub-pm-loc-name {
                font-weight: 700;
                font-size: 14px;
                color: #0f172a;
            }
            .thaaniyamhub-pm-status {
                font-size: 11px;
                font-weight: 700;
                padding: 2px 8px;
                border-radius: 12px;
            }
            .thaaniyamhub-pm-status-active { background: #ecfdf5; color: #065f46; }
            .thaaniyamhub-pm-status-pending { background: #fef3c7; color: #92400e; }
            .thaaniyamhub-pm-status-inactive { background: #fee2e2; color: #991b1b; }
            .thaaniyamhub-pm-info-row {
                display: flex;
                gap: 8px;
                margin-bottom: 8px;
                font-size: 12px;
            }
            .thaaniyamhub-pm-info-label {
                font-size: 10px;
                font-weight: 600;
                color: #94a3b8;
                text-transform: uppercase;
            }
            .thaaniyamhub-pm-vendor-pill {
                display: inline-block;
                padding: 4px 10px;
                border-radius: 20px;
                font-size: 11px;
                font-weight: 600;
            }
            .thaaniyamhub-pm-vendor-pill.assigned { background: #eff6ff; color: #1e40af; }
            .thaaniyamhub-pm-vendor-pill.none { background: #f1f5f9; color: #64748b; }

            /* Empty state */
            .thaaniyamhub-pm-empty {
                text-align: center;
                padding: 48px 24px;
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 14px;
            }
            .thaaniyamhub-pm-empty span {
                font-size: 40px;
                display: block;
                margin-bottom: 10px;
            }

            /* Modal */
            .thaaniyamhub-pm-modal {
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                z-index: 999999;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .thaaniyamhub-pm-modal-overlay {
                position: absolute;
                top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(15, 23, 42, 0.6);
                backdrop-filter: blur(2px);
            }
            .thaaniyamhub-pm-modal-dialog {
                position: relative;
                background: #ffffff;
                border-radius: 14px;
                width: 90%;
                max-width: 860px;
                max-height: 85vh;
                display: flex;
                flex-direction: column;
                box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2);
                overflow: hidden;
            }
            .thaaniyamhub-pm-modal-header {
                padding: 16px 24px;
                border-bottom: 1px solid #e2e8f0;
                display: flex;
                align-items: center;
                justify-content: space-between;
                background: #f8fafc;
            }
            .thaaniyamhub-pm-modal-header h3 {
                margin: 0;
                font-size: 16px;
                font-weight: 700;
                color: #0f172a;
            }
            .thaaniyamhub-pm-modal-close {
                background: transparent;
                border: none;
                font-size: 24px;
                line-height: 1;
                cursor: pointer;
                color: #64748b;
            }
            .thaaniyamhub-pm-modal-body {
                padding: 20px 24px;
                overflow-y: auto;
                font-size: 13px;
            }
            .thaaniyamhub-pm-modal-footer {
                padding: 12px 24px;
                border-top: 1px solid #e2e8f0;
                background: #f8fafc;
                text-align: right;
            }

            .ag-json-box {
                background: #0f172a;
                color: #38bdf8;
                padding: 14px;
                border-radius: 8px;
                font-family: Consolas, monospace;
                font-size: 12px;
                line-height: 1.5;
                overflow-x: auto;
                max-height: 350px;
            }

            .ag-modal-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 14px;
                margin-bottom: 16px;
            }
            .ag-modal-cell {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                padding: 10px 14px;
            }
            .ag-modal-cell-label {
                font-size: 10px;
                font-weight: 700;
                text-transform: uppercase;
                color: #64748b;
                margin-bottom: 4px;
            }
            .ag-modal-cell-val {
                font-weight: 600;
                color: #0f172a;
                word-break: break-all;
            }

            @keyframes ag-pm-spin {
                to { transform: rotate(360deg); }
            }
            .thaaniyamhub-pm-spinning .thaaniyamhub-pm-spin-icon {
                display: inline-block;
                animation: ag-pm-spin 0.8s linear infinite;
            }
        </style>
        <?php
    }

    private static function render_scripts()
    {
        $nonce = wp_create_nonce('thaaniyamhub_pm_nonce');
        ?>
        <script>
            jQuery(function ($) {
                "use strict";

                var NONCE = <?php echo wp_json_encode($nonce); ?>;
                var AJAXURL = ajaxurl;
                var auditLogsCache = [];

                // Refresh button click handler
                $("#thaaniyamhub-pm-btn-refresh").on("click", function () {
                    var $btn = $(this).prop("disabled", true).addClass("thaaniyamhub-pm-spinning");
                    var $msg = $("#ag-pm-refresh-msg").text("").removeClass("error");

                    $.post(AJAXURL, { action: "thaaniyamhub_pm_refresh_cache", _nonce: NONCE }, function (res) {
                        $btn.prop("disabled", false).removeClass("thaaniyamhub-pm-spinning");
                        if (res.success) {
                            $msg.text(res.data.message);
                            setTimeout(function () { location.reload(); }, 800);
                        } else {
                            $msg.addClass("error").text("Error: " + res.data);
                        }
                    }).fail(function () {
                        $btn.prop("disabled", false).removeClass("thaaniyamhub-pm-spinning");
                        $msg.addClass("error").text("Request failed.");
                    });
                });

                // Location live search
                $("#ag-pm-loc-search").on("input", function () {
                    var q = $(this).val().toLowerCase().trim();
                    $("#ag-pm-location-grid .thaaniyamhub-pm-location-card").each(function () {
                        var searchTxt = $(this).data("search") || "";
                        if (!q || searchTxt.indexOf(q) !== -1) {
                            $(this).show();
                        } else {
                            $(this).hide();
                        }
                    });
                });

                // Vendor assignment save handler
                $(document).on("click", ".thaaniyamhub-pm-save-assign", function () {
                    var $btn = $(this).prop("disabled", true).text("Saving…");
                    var vendorId = $btn.data("vendor-id");
                    var pickup = $(".thaaniyamhub-pm-pickup-select[data-vendor-id='" + vendorId + "']").val();
                    var $rowMsg = $btn.closest("tr").find(".thaaniyamhub-pm-row-msg").text("").removeClass("error");

                    $.post(AJAXURL, {
                        action: "thaaniyamhub_pm_assign_vendor",
                        _nonce: NONCE,
                        vendor_id: vendorId,
                        pickup_location: pickup,
                    }, function (res) {
                        $btn.prop("disabled", false).text("<?php esc_attr_e('Save', 'thaaniyamhub-multi-vendor-orders'); ?>");
                        if (res.success) {
                            $rowMsg.text(res.data.message);
                            setTimeout(function () { $rowMsg.text(""); }, 3000);
                        } else {
                            $rowMsg.addClass("error").text("Error: " + res.data);
                        }
                    }).fail(function () {
                        $btn.prop("disabled", false).text("<?php esc_attr_e('Save', 'thaaniyamhub-multi-vendor-orders'); ?>");
                        $rowMsg.addClass("error").text("<?php esc_attr_e('Request failed.', 'thaaniyamhub-multi-vendor-orders'); ?>");
                    });
                });

                // Vendor table live search
                $("#ag-pm-vendor-search").on("input", function () {
                    var q = $(this).val().toLowerCase().trim();
                    $(".thaaniyamhub-pm-vendor-row").each(function () {
                        var txt = $(this).data("vendor") || $(this).text().toLowerCase();
                        $(this).toggleClass("hidden", q.length > 0 && txt.indexOf(q) === -1);
                    });
                });

                // =============================================================
                // TAB 4: AUDIT LOGS AJAX & VIEWER
                // =============================================================

                function loadAuditLogs() {
                    var $tbody = $("#ag-pm-audit-tbody");
                    var $loader = $("#ag-pm-logs-loading");
                    var $empty = $("#ag-pm-audit-empty");

                    if ($tbody.length === 0) return;

                    $loader.show();
                    $empty.hide();

                    var search = $("#ag-pm-audit-search").val();
                    var eventType = $("#ag-pm-audit-event-filter").val();
                    var status = $("#ag-pm-audit-status-filter").val();

                    $.post(AJAXURL, {
                        action: "thaaniyamhub_pm_get_audit_logs",
                        _nonce: NONCE,
                        search: search,
                        event_type: eventType,
                        status: status,
                        limit: 200
                    }, function (res) {
                        $loader.hide();
                        if (res.success) {
                            auditLogsCache = res.data.logs || [];
                            $("#ag-pm-log-total-count").text(res.data.total);
                            $("#ag-pm-log-file-size").text(res.data.file_size);

                            if (auditLogsCache.length === 0) {
                                $tbody.empty();
                                $empty.show();
                                return;
                            }

                            var html = "";
                            $.each(auditLogsCache, function (idx, item) {
                                var eventClass = "ag-badge-" + (item.event_type || "");
                                var oldVal = item.old_value || "";
                                var newVal = item.new_value || "";
                                if (typeof oldVal === "object") oldVal = JSON.stringify(oldVal);
                                if (typeof newVal === "object") newVal = JSON.stringify(newVal);

                                var oldDisp = oldVal ? "<span class=\"ag-diff-val ag-diff-old\">" + escapeHtml(oldVal) + "</span>" : "<span style=\"color:#94a3b8;\">[none]</span>";
                                var newDisp = newVal ? "<span class=\"ag-diff-val ag-diff-new\">" + escapeHtml(newVal) + "</span>" : "<span style=\"color:#94a3b8;\">[none]</span>";

                                var actor = item.actor || {};
                                var req = item.request || {};

                                html += "<tr>";
                                html += "<td><strong>" + escapeHtml(item.timestamp_local || "") + "</strong><br><small style=\"color:#94a3b8;\">" + escapeHtml(item.timestamp_utc || "") + "</small></td>";
                                html += "<td><span class=\"ag-badge-event " + eventClass + "\">" + escapeHtml(item.event_type || "") + "</span></td>";
                                html += "<td>";
                                if (item.vendor_id > 0) {
                                    html += "<strong>#" + item.vendor_id + " " + escapeHtml(item.vendor_name || "") + "</strong>";
                                    if (item.vendor_store) {
                                        html += "<br><small style=\"color:#64748b;\">" + escapeHtml(item.vendor_store) + "</small>";
                                    }
                                } else {
                                    html += "<span style=\"color:#94a3b8;\">— Global / Option —</span>";
                                }
                                html += "</td>";
                                html += "<td>" + oldDisp + " <span class=\"ag-diff-arrow\">➔</span> " + newDisp + "</td>";
                                html += "<td>";
                                html += "<strong>" + escapeHtml(actor.display_name || actor.user_login || "System") + "</strong>";
                                if (actor.user_id) {
                                    html += " <small style=\"color:#94a3b8;\">(#" + actor.user_id + ")</small>";
                                }
                                if (actor.roles && actor.roles.length > 0) {
                                    html += "<br><small style=\"color:#2563eb;\">" + escapeHtml(actor.roles.join(", ")) + "</small>";
                                }
                                html += "</td>";
                                html += "<td>";
                                html += "<code>" + escapeHtml(req.client_ip || "—") + "</code>";
                                if (req.http_method) {
                                    html += " <small style=\"color:#64748b;\">[" + escapeHtml(req.http_method) + "]</small>";
                                }
                                html += "</td>";
                                html += "<td style=\"text-align: right;\">";
                                html += "<button type=\"button\" class=\"button button-small ag-pm-view-log-btn\" data-log-index=\"" + idx + "\">🔍 Details</button>";
                                html += "</td>";
                                html += "</tr>";
                            });

                            $tbody.html(html);
                        } else {
                            $tbody.html("<tr><td colspan=\"7\" style=\"color:#dc2626; text-align:center;\">Error: " + res.data + "</td></tr>");
                        }
                    }).fail(function () {
                        $loader.hide();
                        $tbody.html("<tr><td colspan=\"7\" style=\"color:#dc2626; text-align:center;\">Failed to load audit logs.</td></tr>");
                    });
                }

                // Initial load on logs tab
                if ($("#ag-pm-audit-tbody").length > 0) {
                    loadAuditLogs();
                }

                // Filter & Search debounce
                var logSearchTimer;
                $("#ag-pm-audit-search").on("input", function () {
                    clearTimeout(logSearchTimer);
                    logSearchTimer = setTimeout(loadAuditLogs, 300);
                });

                $("#ag-pm-audit-event-filter, #ag-pm-audit-status-filter").on("change", function () {
                    loadAuditLogs();
                });

                $("#thaaniyamhub-pm-btn-refresh-logs").on("click", function () {
                    loadAuditLogs();
                });

                // Clear audit logs
                $("#thaaniyamhub-pm-btn-clear-logs").on("click", function () {
                    if (!confirm("Are you sure you want to completely clear all pickup location audit logs? This action cannot be undone.")) {
                        return;
                    }
                    var $btn = $(this).prop("disabled", true).text("Clearing…");
                    $.post(AJAXURL, { action: "thaaniyamhub_pm_clear_audit_logs", _nonce: NONCE }, function (res) {
                        $btn.prop("disabled", false).text("🗑️ Clear Audit Logs");
                        if (res.success) {
                            alert(res.data.message);
                            loadAuditLogs();
                        } else {
                            alert("Error: " + res.data);
                        }
                    }).fail(function () {
                        $btn.prop("disabled", false).text("🗑️ Clear Audit Logs");
                        alert("Request failed.");
                    });
                });

                // View Details Modal
                $(document).on("click", ".ag-pm-view-log-btn", function () {
                    var idx = $(this).data("log-index");
                    var item = auditLogsCache[idx];
                    if (!item) return;

                    var modalHtml = "";
                    modalHtml += "<div class=\"ag-modal-grid\">";
                    modalHtml += "<div class=\"ag-modal-cell\"><div class=\"ag-modal-cell-label\">Event Type & ID</div><div class=\"ag-modal-cell-val\"><code>" + escapeHtml(item.event_type) + "</code> (" + escapeHtml(item.id) + ")</div></div>";
                    modalHtml += "<div class=\"ag-modal-cell\"><div class=\"ag-modal-cell-label\">Timestamp</div><div class=\"ag-modal-cell-val\">" + escapeHtml(item.timestamp_local) + "<br><small style=\"color:#64748b;\">" + escapeHtml(item.timestamp_utc) + "</small></div></div>";
                    modalHtml += "<div class=\"ag-modal-cell\"><div class=\"ag-modal-cell-label\">Target Vendor</div><div class=\"ag-modal-cell-val\">#" + item.vendor_id + " " + escapeHtml(item.vendor_name || "N/A") + " (" + escapeHtml(item.vendor_store || "No store") + ")</div></div>";
                    modalHtml += "<div class=\"ag-modal-cell\"><div class=\"ag-modal-cell-label\">Actor / User</div><div class=\"ag-modal-cell-val\">" + escapeHtml((item.actor || {}).display_name || "") + " (ID: " + ((item.actor || {}).user_id || 0) + ")</div></div>";
                    modalHtml += "<div class=\"ag-modal-cell\"><div class=\"ag-modal-cell-label\">Client IP Address</div><div class=\"ag-modal-cell-val\"><code>" + escapeHtml((item.request || {}).client_ip || "—") + "</code></div></div>";
                    modalHtml += "<div class=\"ag-modal-cell\"><div class=\"ag-modal-cell-label\">HTTP Method & URI</div><div class=\"ag-modal-cell-val\"><code>[" + escapeHtml((item.request || {}).http_method || "GET") + "] " + escapeHtml((item.request || {}).request_uri || "/") + "</code></div></div>";
                    modalHtml += "</div>";

                    if (item.origin && item.origin.file) {
                        modalHtml += "<div class=\"ag-modal-cell\" style=\"margin-bottom:14px;\"><div class=\"ag-modal-cell-label\">Origin Code Location</div><div class=\"ag-modal-cell-val\"><code>" + escapeHtml(item.origin.file) + ":" + item.origin.line + " (" + escapeHtml(item.origin.function) + ")</code></div></div>";
                    }

                    modalHtml += "<h4 style=\"margin: 16px 0 8px 0;\">📦 Complete A-Z Event JSON Payload</h4>";
                    modalHtml += "<pre class=\"ag-json-box\">" + escapeHtml(JSON.stringify(item, null, 2)) + "</pre>";

                    $("#thaaniyamhub-pm-modal-content").html(modalHtml);
                    $("#thaaniyamhub-pm-log-modal").fadeIn(150);
                });

                // Close Modal
                $(document).on("click", ".thaaniyamhub-pm-modal-close, .thaaniyamhub-pm-modal-overlay", function () {
                    $("#thaaniyamhub-pm-log-modal").fadeOut(150);
                });

                function escapeHtml(text) {
                    if (text === null || text === undefined) return "";
                    return String(text)
                        .replace(/&/g, "&amp;")
                        .replace(/</g, "&lt;")
                        .replace(/>/g, "&gt;")
                        .replace(/"/g, "&quot;")
                        .replace(/'/g, "&#039;");
                }
            });
        </script>
        <?php
    }
}
}
