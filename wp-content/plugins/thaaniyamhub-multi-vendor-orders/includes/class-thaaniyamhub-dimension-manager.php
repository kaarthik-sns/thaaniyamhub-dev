<?php
/**
 * Thaaniyam Hub Marketplace — Package Dimension Manager
 *
 * Provides a dedicated WooCommerce admin submenu page:
 *   WooCommerce → Package Dimensions
 *
 * Allows configuring multiple package dimension presets (Weight, Length, Width, Height) per vendor.
 * Selected presets can auto-fill package details in the Shiprocket Fulfillment Controls meta box.
 *
 * @package thaaniyamhub-multi-vendor-orders
 */

defined('ABSPATH') || exit;

if (!class_exists('ThaaniyamHub_Dimension_Manager')) {
class ThaaniyamHub_Dimension_Manager
{
    const META_KEY = '_thaaniyamhub_package_dimensions';

    // =========================================================================
    // BOOTSTRAP
    // =========================================================================

    public static function init()
    {
        // Admin menu
        add_action('admin_menu', [__CLASS__, 'register_menu']);

        // Admin AJAX handlers
        add_action('wp_ajax_thaaniyamhub_pdm_save_preset', [__CLASS__, 'ajax_save_preset']);
        add_action('wp_ajax_thaaniyamhub_pdm_delete_preset', [__CLASS__, 'ajax_delete_preset']);
        add_action('wp_ajax_thaaniyamhub_pdm_get_presets', [__CLASS__, 'ajax_get_presets']);
    }

    // =========================================================================
    // ADMIN MENU REGISTRATION
    // =========================================================================

    public static function register_menu()
    {
        add_submenu_page(
            'woocommerce',
            __('Package Dimensions', 'thaaniyamhub-multi-vendor-orders'),
            __('Package Dimensions', 'thaaniyamhub-multi-vendor-orders'),
            'manage_woocommerce',
            'thaaniyamhub-package-dimensions',
            [__CLASS__, 'render_page']
        );
    }

    // =========================================================================
    // DATA HELPERS
    // =========================================================================

    /**
     * Get all saved presets for a vendor.
     *
     * @param int $vendor_id
     * @return array
     */
    public static function get_vendor_presets(int $vendor_id): array
    {
        if (!$vendor_id) {
            return [];
        }

        $presets = get_user_meta($vendor_id, self::META_KEY, true);
        if (!is_array($presets)) {
            return [];
        }

        // Clean & ensure array format
        $clean = [];
        foreach ($presets as $p) {
            if (is_array($p) && !empty($p['name'])) {
                $clean[] = [
                    'id'     => sanitize_key($p['id'] ?? ('pkg_' . uniqid())),
                    'name'   => sanitize_text_field($p['name']),
                    'weight' => (float) ($p['weight'] ?? 0),
                    'length' => (float) ($p['length'] ?? 0),
                    'width'  => (float) ($p['width'] ?? 0),
                    'height' => (float) ($p['height'] ?? 0),
                ];
            }
        }

        return $clean;
    }

    /**
     * Save/update a preset for a vendor.
     *
     * @param int $vendor_id
     * @param array $preset_data
     * @return array Updated presets
     */
    public static function save_vendor_preset(int $vendor_id, array $preset_data): array
    {
        $presets = self::get_vendor_presets($vendor_id);

        $preset_id = !empty($preset_data['id']) ? sanitize_key($preset_data['id']) : ('pkg_' . bin2hex(random_bytes(4)));
        $name      = sanitize_text_field($preset_data['name'] ?? '');
        $weight    = (float) ($preset_data['weight'] ?? 0);
        $length    = (float) ($preset_data['length'] ?? 0);
        $width     = (float) ($preset_data['width'] ?? 0);
        $height    = (float) ($preset_data['height'] ?? 0);

        $new_item = [
            'id'     => $preset_id,
            'name'   => $name,
            'weight' => $weight,
            'length' => $length,
            'width'  => $width,
            'height' => $height,
        ];

        $found = false;
        foreach ($presets as $k => $p) {
            if ($p['id'] === $preset_id) {
                $presets[$k] = $new_item;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $presets[] = $new_item;
        }

        update_user_meta($vendor_id, self::META_KEY, $presets);

        return $presets;
    }

    /**
     * Delete a preset for a vendor.
     *
     * @param int $vendor_id
     * @param string $preset_id
     * @return array Updated presets
     */
    public static function delete_vendor_preset(int $vendor_id, string $preset_id): array
    {
        $presets = self::get_vendor_presets($vendor_id);
        $filtered = [];

        foreach ($presets as $p) {
            if ($p['id'] !== $preset_id) {
                $filtered[] = $p;
            }
        }

        update_user_meta($vendor_id, self::META_KEY, $filtered);

        return $filtered;
    }

    /**
     * Retrieve all vendors.
     *
     * @return array
     */
    public static function get_all_vendors(): array
    {
        if (class_exists('ThaaniyamHub_Pickup_Manager') && method_exists('ThaaniyamHub_Pickup_Manager', 'get_all_vendors')) {
            return ThaaniyamHub_Pickup_Manager::get_all_vendors();
        }

        if (function_exists('wcfmmp_get_vendor_ids')) {
            $ids = wcfmmp_get_vendor_ids();
            if (!empty($ids)) {
                $users = get_users([
                    'include' => $ids,
                    'orderby' => 'display_name',
                    'order'   => 'ASC',
                ]);
                return is_array($users) ? $users : [];
            }
        }

        $roles = ['wcfm_vendor', 'seller', 'vendor'];
        foreach ($roles as $role) {
            $users = get_users([
                'role'    => $role,
                'orderby' => 'display_name',
                'order'   => 'ASC',
            ]);
            if (!empty($users)) {
                return $users;
            }
        }

        return [];
    }

    // =========================================================================
    // AJAX HANDLERS
    // =========================================================================

    public static function ajax_save_preset()
    {
        check_ajax_referer('thaaniyamhub_pdm_nonce', '_nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Insufficient permissions.', 'thaaniyamhub-multi-vendor-orders'), 403);
        }

        $vendor_id = (int) ($_POST['vendor_id'] ?? 0);
        $preset_id = sanitize_key($_POST['preset_id'] ?? '');
        $name      = sanitize_text_field($_POST['name'] ?? '');
        $weight    = (isset($_POST['weight']) && $_POST['weight'] !== '') ? max(0, (float) $_POST['weight']) : 0;
        $length    = (float) ($_POST['length'] ?? 0);
        $width     = (float) ($_POST['width'] ?? 0);
        $height    = (float) ($_POST['height'] ?? 0);

        if (!$vendor_id) {
            wp_send_json_error(__('Please select a valid vendor.', 'thaaniyamhub-multi-vendor-orders'));
        }

        if (empty($name)) {
            wp_send_json_error(__('Preset name is required.', 'thaaniyamhub-multi-vendor-orders'));
        }

        if ($length <= 0 || $width <= 0 || $height <= 0) {
            wp_send_json_error(__('Length, Width, and Height must all be positive numbers (greater than 0).', 'thaaniyamhub-multi-vendor-orders'));
        }

        $updated_presets = self::save_vendor_preset($vendor_id, [
            'id'     => $preset_id,
            'name'   => $name,
            'weight' => $weight,
            'length' => $length,
            'width'  => $width,
            'height' => $height,
        ]);

        wp_send_json_success([
            'message' => __('Package dimension preset saved successfully.', 'thaaniyamhub-multi-vendor-orders'),
            'presets' => $updated_presets,
        ]);
    }

    public static function ajax_delete_preset()
    {
        check_ajax_referer('thaaniyamhub_pdm_nonce', '_nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Insufficient permissions.', 'thaaniyamhub-multi-vendor-orders'), 403);
        }

        $vendor_id = (int) ($_POST['vendor_id'] ?? 0);
        $preset_id = sanitize_key($_POST['preset_id'] ?? '');

        if (!$vendor_id || empty($preset_id)) {
            wp_send_json_error(__('Invalid request parameters.', 'thaaniyamhub-multi-vendor-orders'));
        }

        $updated_presets = self::delete_vendor_preset($vendor_id, $preset_id);

        wp_send_json_success([
            'message' => __('Package dimension preset deleted successfully.', 'thaaniyamhub-multi-vendor-orders'),
            'presets' => $updated_presets,
        ]);
    }

    public static function ajax_get_presets()
    {
        check_ajax_referer('thaaniyamhub_pdm_nonce', '_nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Insufficient permissions.', 'thaaniyamhub-multi-vendor-orders'), 403);
        }

        $vendor_id = (int) ($_GET['vendor_id'] ?? 0);
        if (!$vendor_id) {
            wp_send_json_error(__('Invalid vendor ID.', 'thaaniyamhub-multi-vendor-orders'));
        }

        $presets = self::get_vendor_presets($vendor_id);
        wp_send_json_success([
            'vendor_id' => $vendor_id,
            'presets'   => $presets,
        ]);
    }

    // =========================================================================
    // ADMIN PAGE RENDER
    // =========================================================================

    public static function render_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions.', 'thaaniyamhub-multi-vendor-orders'));
        }

        $vendors = self::get_all_vendors();
        $selected_vendor_id = isset($_GET['vendor_id']) ? (int) $_GET['vendor_id'] : 0;

        if (!$selected_vendor_id && !empty($vendors)) {
            $selected_vendor_id = (int) $vendors[0]->ID;
        }

        $presets = $selected_vendor_id ? self::get_vendor_presets($selected_vendor_id) : [];
        $selected_vendor_user = $selected_vendor_id ? get_userdata($selected_vendor_id) : null;
        $selected_store_name = '';
        if ($selected_vendor_id && function_exists('thaaniyamhub_get_vendor_name_by_vendor_id')) {
            $selected_store_name = thaaniyamhub_get_vendor_name_by_vendor_id($selected_vendor_id);
        }
        if (!$selected_store_name && $selected_vendor_user) {
            $selected_store_name = $selected_vendor_user->display_name;
        }

        self::render_styles();
        ?>
        <div class="wrap thaaniyamhub-pdm-wrap">
            <div class="thaaniyamhub-pdm-header">
                <div class="thaaniyamhub-pdm-header-left">
                    <div class="thaaniyamhub-pdm-icon-badge">📦</div>
                    <div>
                        <h1 class="thaaniyamhub-pdm-title">
                            <?php esc_html_e('Package Dimensions Manager', 'thaaniyamhub-multi-vendor-orders'); ?>
                            <span class="thaaniyamhub-pdm-subtitle"><?php esc_html_e('Vendor Presets', 'thaaniyamhub-multi-vendor-orders'); ?></span>
                        </h1>
                        <p class="thaaniyamhub-pdm-header-desc">
                            <?php esc_html_e('Manage package dimension presets (Weight, Length, Width, Height) per vendor. When fulfilling orders, selecting a preset automatically fills in the dimensions.', 'thaaniyamhub-multi-vendor-orders'); ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- VENDOR SELECTOR & ACTION BAR -->
            <div class="thaaniyamhub-pdm-card thaaniyamhub-pdm-selector-card">
                <div class="thaaniyamhub-pdm-vendor-bar">
                    <div class="thaaniyamhub-pdm-field-group">
                        <label for="thaaniyamhub-pdm-vendor-select">
                            <strong><?php esc_html_e('Select Vendor:', 'thaaniyamhub-multi-vendor-orders'); ?></strong>
                        </label>
                        <select id="thaaniyamhub-pdm-vendor-select" class="thaaniyamhub-pdm-select">
                            <?php if (empty($vendors)): ?>
                                <option value=""><?php esc_html_e('No vendors found', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                            <?php else: ?>
                                <?php foreach ($vendors as $v):
                                    $store_name = '';
                                    if (function_exists('thaaniyamhub_get_vendor_name_by_vendor_id')) {
                                        $store_name = thaaniyamhub_get_vendor_name_by_vendor_id($v->ID);
                                    }
                                    $label = ($store_name ? $store_name . ' (' . $v->display_name . ')' : $v->display_name) . ' #' . $v->ID;
                                ?>
                                    <option value="<?php echo esc_attr($v->ID); ?>" <?php selected($v->ID, $selected_vendor_id); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="thaaniyamhub-pdm-bar-actions">
                        <button type="button" id="thaaniyamhub-pdm-btn-add" class="button button-primary thaaniyamhub-pdm-btn-add">
                            <span class="dashicons dashicons-plus-alt2" style="font-size: 18px; width: 18px; height: 18px; line-height: 18px; margin: 0; display: inline-flex; align-items: center; justify-content: center;"></span>
                            <span style="line-height: 1;"><?php esc_html_e('Add Package Dimension', 'thaaniyamhub-multi-vendor-orders'); ?></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- PRESETS LIST TABLE CARD -->
            <div class="thaaniyamhub-pdm-card">
                <div class="thaaniyamhub-pdm-card-header">
                    <h2 class="thaaniyamhub-pdm-card-title">
                        <?php
                        printf(
                            esc_html__('Configured Package Dimensions for %s', 'thaaniyamhub-multi-vendor-orders'),
                            '<span id="thaaniyamhub-pdm-active-vendor-name" class="thaaniyamhub-pdm-highlight">' . esc_html($selected_store_name ?: __('Selected Vendor', 'thaaniyamhub-multi-vendor-orders')) . '</span>'
                        );
                        ?>
                    </h2>
                    <span id="thaaniyamhub-pdm-preset-count-badge" class="thaaniyamhub-pdm-count-badge">
                        <?php printf(esc_html(_n('%d Dimension Saved', '%d Dimensions Saved', count($presets), 'thaaniyamhub-multi-vendor-orders')), count($presets)); ?>
                    </span>
                </div>

                <div class="thaaniyamhub-pdm-table-wrapper">
                    <table class="wp-list-table widefat fixed striped thaaniyamhub-pdm-table" id="thaaniyamhub-pdm-table">
                        <thead>
                            <tr>
                                <th style="width: 22%;"><?php esc_html_e('Preset Name / Label', 'thaaniyamhub-multi-vendor-orders'); ?></th>
                                <th style="width: 14%;"><?php esc_html_e('Dead Weight', 'thaaniyamhub-multi-vendor-orders'); ?></th>
                                <th style="width: 22%;"><?php esc_html_e('Dimensions (L x W x H cm)', 'thaaniyamhub-multi-vendor-orders'); ?></th>
                                <th style="width: 14%;"><?php esc_html_e('Volumetric Wt', 'thaaniyamhub-multi-vendor-orders'); ?></th>
                                <th style="width: 14%;"><?php esc_html_e('Final Weight', 'thaaniyamhub-multi-vendor-orders'); ?></th>
                                <th style="width: 14%; text-align: right;"><?php esc_html_e('Actions', 'thaaniyamhub-multi-vendor-orders'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="thaaniyamhub-pdm-tbody">
                            <?php if (empty($presets)): ?>
                                <tr class="thaaniyamhub-pdm-empty-row">
                                    <td colspan="6">
                                        <div class="thaaniyamhub-pdm-empty-state">
                                            <span class="thaaniyamhub-pdm-empty-icon">📦</span>
                                            <p><?php esc_html_e('No package dimensions configured for this vendor yet.', 'thaaniyamhub-multi-vendor-orders'); ?></p>
                                            <button type="button" class="button button-secondary thaaniyamhub-pdm-btn-add-inline">
                                                <?php esc_html_e('+ Add First Dimension Preset', 'thaaniyamhub-multi-vendor-orders'); ?>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($presets as $p):
                                    $volumetric = round(($p['length'] * $p['width'] * $p['height']) / 5000, 3);
                                    $final_weight = max((float) $p['weight'], (float) $volumetric);
                                ?>
                                    <tr data-preset-id="<?php echo esc_attr($p['id']); ?>"
                                        data-name="<?php echo esc_attr($p['name']); ?>"
                                        data-weight="<?php echo esc_attr($p['weight']); ?>"
                                        data-length="<?php echo esc_attr($p['length']); ?>"
                                        data-width="<?php echo esc_attr($p['width']); ?>"
                                        data-height="<?php echo esc_attr($p['height']); ?>">
                                        <td>
                                            <strong class="thaaniyamhub-pdm-preset-name"><?php echo esc_html($p['name']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="thaaniyamhub-pdm-value-badge"><?php echo esc_html($p['weight']); ?> kg</span>
                                        </td>
                                        <td>
                                            <span class="thaaniyamhub-pdm-dim-text">
                                                <?php echo esc_html(sprintf('%s cm (L) × %s cm (W) × %s cm (H)', $p['length'], $p['width'], $p['height'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="thaaniyamhub-pdm-vol-text" title="<?php esc_attr_e('(L × W × H) / 5000', 'thaaniyamhub-multi-vendor-orders'); ?>">
                                                ~<?php echo esc_html($volumetric); ?> kg
                                            </span>
                                        </td>
                                        <td>
                                            <span class="thaaniyamhub-pdm-final-badge">
                                                ⚖️ <?php echo esc_html(number_format($final_weight, 3)); ?> kg
                                            </span>
                                        </td>
                                        <td style="text-align: right;">
                                            <button type="button" class="button button-small thaaniyamhub-pdm-btn-edit" title="<?php esc_attr_e('Edit Preset', 'thaaniyamhub-multi-vendor-orders'); ?>">
                                                ✏️ <?php esc_html_e('Edit', 'thaaniyamhub-multi-vendor-orders'); ?>
                                            </button>
                                            <button type="button" class="button button-small button-link-delete thaaniyamhub-pdm-btn-delete" title="<?php esc_attr_e('Delete Preset', 'thaaniyamhub-multi-vendor-orders'); ?>">
                                                🗑️ <?php esc_html_e('Delete', 'thaaniyamhub-multi-vendor-orders'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- MODAL FOR ADD / EDIT PRESET -->
            <div id="thaaniyamhub-pdm-modal" class="thaaniyamhub-pdm-modal-overlay" style="display:none;">
                <div class="thaaniyamhub-pdm-modal-box">
                    <div class="thaaniyamhub-pdm-modal-header">
                        <h3 id="thaaniyamhub-pdm-modal-title"><?php esc_html_e('Add Package Dimension', 'thaaniyamhub-multi-vendor-orders'); ?></h3>
                        <button type="button" class="thaaniyamhub-pdm-modal-close" id="thaaniyamhub-pdm-modal-close">&times;</button>
                    </div>
                    <div class="thaaniyamhub-pdm-modal-body">
                        <input type="hidden" id="pdm_preset_id" value="">

                        <div class="thaaniyamhub-pdm-form-group">
                            <label for="pdm_name">
                                <?php esc_html_e('Preset Name / Label', 'thaaniyamhub-multi-vendor-orders'); ?> <span class="req">*</span>
                            </label>
                            <input type="text" id="pdm_name" class="regular-text" placeholder="<?php esc_attr_e('e.g. Small Box 500g, 1kg Grain Pouch, 5kg Carton', 'thaaniyamhub-multi-vendor-orders'); ?>" style="width:100%;">
                        </div>

                        <div class="thaaniyamhub-pdm-grid-2">
                            <div class="thaaniyamhub-pdm-form-group">
                                <label for="pdm_weight">
                                    <?php esc_html_e('Weight (kg)', 'thaaniyamhub-multi-vendor-orders'); ?> <span style="color:#64748b; font-weight:normal; font-size:11px;">(<?php esc_html_e('Optional', 'thaaniyamhub-multi-vendor-orders'); ?>)</span>
                                </label>
                                <input type="number" id="pdm_weight" step="0.001" min="0" placeholder="<?php esc_attr_e('e.g. 0.500 or leave blank', 'thaaniyamhub-multi-vendor-orders'); ?>" style="width:100%;">
                            </div>
                            <div class="thaaniyamhub-pdm-form-group">
                                <label for="pdm_length">
                                    <?php esc_html_e('Length (cm)', 'thaaniyamhub-multi-vendor-orders'); ?> <span class="req">*</span>
                                </label>
                                <input type="number" id="pdm_length" step="0.1" min="0.5" placeholder="e.g. 20" style="width:100%;">
                            </div>
                        </div>

                        <div class="thaaniyamhub-pdm-grid-2">
                            <div class="thaaniyamhub-pdm-form-group">
                                <label for="pdm_width">
                                    <?php esc_html_e('Width (cm)', 'thaaniyamhub-multi-vendor-orders'); ?> <span class="req">*</span>
                                </label>
                                <input type="number" id="pdm_width" step="0.1" min="0.5" placeholder="e.g. 15" style="width:100%;">
                            </div>
                            <div class="thaaniyamhub-pdm-form-group">
                                <label for="pdm_height">
                                    <?php esc_html_e('Height (cm)', 'thaaniyamhub-multi-vendor-orders'); ?> <span class="req">*</span>
                                </label>
                                <input type="number" id="pdm_height" step="0.1" min="0.5" placeholder="e.g. 10" style="width:100%;">
                            </div>
                        </div>

                        <div class="thaaniyamhub-pdm-weights-box">
                            <div class="thaaniyamhub-pdm-weights-row">
                                <span class="thaaniyamhub-pdm-weights-label">🧮 <?php esc_html_e('Volumetric Weight:', 'thaaniyamhub-multi-vendor-orders'); ?></span>
                                <span class="thaaniyamhub-pdm-weights-val" id="pdm_vol_text">0.000 kg</span>
                            </div>
                            <div class="thaaniyamhub-pdm-weights-row thaaniyamhub-pdm-final-row">
                                <span class="thaaniyamhub-pdm-weights-label">⚖️ <?php esc_html_e('Final Chargeable Weight:', 'thaaniyamhub-multi-vendor-orders'); ?></span>
                                <span class="thaaniyamhub-pdm-final-highlight" id="pdm_final_text">0.000 kg</span>
                            </div>
                        </div>

                        <div id="pdm_modal_error" class="thaaniyamhub-pdm-modal-error" style="display:none;"></div>
                    </div>
                    <div class="thaaniyamhub-pdm-modal-footer">
                        <button type="button" class="button" id="thaaniyamhub-pdm-btn-cancel"><?php esc_html_e('Cancel', 'thaaniyamhub-multi-vendor-orders'); ?></button>
                        <button type="button" class="button button-primary" id="thaaniyamhub-pdm-btn-save"><?php esc_html_e('Save Dimension', 'thaaniyamhub-multi-vendor-orders'); ?></button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var nonce = "<?php echo esc_js(wp_create_nonce('thaaniyamhub_pdm_nonce')); ?>";

            // Recalculate Volumetric & Final weight in modal
            function updateWeights() {
                var weightVal = $.trim($("#pdm_weight").val());
                var act = (weightVal !== "" && !isNaN(parseFloat(weightVal))) ? Math.max(0, parseFloat(weightVal)) : 0;
                var l = parseFloat($("#pdm_length").val()) || 0;
                var wd = parseFloat($("#pdm_width").val()) || 0;
                var h = parseFloat($("#pdm_height").val()) || 0;

                var vol = (l > 0 && wd > 0 && h > 0) ? (l * wd * h) / 5000 : 0;
                var finalWeight = Math.max(act, vol);

                $("#pdm_vol_text").text(vol > 0 ? (vol.toFixed(3) + " kg") : "0.000 kg");
                $("#pdm_final_text").text(finalWeight > 0 ? (finalWeight.toFixed(3) + " kg") : (act > 0 ? (act.toFixed(3) + " kg") : "0.000 kg"));
            }

            $("#pdm_weight, #pdm_length, #pdm_width, #pdm_height").on("input change keyup", updateWeights);

            // Vendor switch
            $("#thaaniyamhub-pdm-vendor-select").on("change", function() {
                var vendorId = $(this).val();
                if (vendorId) {
                    window.location.href = "admin.php?page=thaaniyamhub-package-dimensions&vendor_id=" + vendorId;
                }
            });

            // Open Modal for Add
            function openAddModal() {
                $("#pdm_preset_id").val("");
                $("#pdm_name").val("");
                $("#pdm_weight").val("");
                $("#pdm_length").val("");
                $("#pdm_width").val("");
                $("#pdm_height").val("");
                $("#pdm_modal_error").hide().empty();
                $("#thaaniyamhub-pdm-modal-title").text("<?php echo esc_js(__('Add Package Dimension', 'thaaniyamhub-multi-vendor-orders')); ?>");
                updateWeights();
                $("#thaaniyamhub-pdm-modal").fadeIn(150);
                $("#pdm_name").focus();
            }

            $(document).on("click", "#thaaniyamhub-pdm-btn-add, .thaaniyamhub-pdm-btn-add-inline", function(e) {
                e.preventDefault();
                openAddModal();
            });

            // Open Modal for Edit
            $(document).on("click", ".thaaniyamhub-pdm-btn-edit", function(e) {
                e.preventDefault();
                var $row = $(this).closest("tr");
                var rowWeight = $row.data("weight");
                var numWeight = parseFloat(rowWeight);

                $("#pdm_preset_id").val($row.data("preset-id"));
                $("#pdm_name").val($row.data("name"));
                $("#pdm_weight").val((!isNaN(numWeight) && numWeight > 0) ? numWeight : (numWeight === 0 ? "0" : ""));
                $("#pdm_length").val($row.data("length"));
                $("#pdm_width").val($row.data("width"));
                $("#pdm_height").val($row.data("height"));
                $("#pdm_modal_error").hide().empty();
                $("#thaaniyamhub-pdm-modal-title").text("<?php echo esc_js(__('Edit Package Dimension', 'thaaniyamhub-multi-vendor-orders')); ?>");
                updateWeights();
                $("#thaaniyamhub-pdm-modal").fadeIn(150);
            });

            // Close Modal
            function closeModal() {
                $("#thaaniyamhub-pdm-modal").fadeOut(150);
            }
            $("#thaaniyamhub-pdm-modal-close, #thaaniyamhub-pdm-btn-cancel").on("click", function() {
                closeModal();
            });
            $("#thaaniyamhub-pdm-modal").on("click", function(e) {
                if ($(e.target).is("#thaaniyamhub-pdm-modal")) {
                    closeModal();
                }
            });

            // Save Preset via AJAX
            $("#thaaniyamhub-pdm-btn-save").on("click", function() {
                var $btn = $(this);
                var vendorId = $("#thaaniyamhub-pdm-vendor-select").val();
                var presetId = $("#pdm_preset_id").val();
                var name = $.trim($("#pdm_name").val());
                var weightRaw = $.trim($("#pdm_weight").val());
                var weight = (weightRaw !== "" && !isNaN(parseFloat(weightRaw))) ? Math.max(0, parseFloat(weightRaw)) : 0;
                var length = parseFloat($("#pdm_length").val()) || 0;
                var width = parseFloat($("#pdm_width").val()) || 0;
                var height = parseFloat($("#pdm_height").val()) || 0;

                if (!name) {
                    $("#pdm_modal_error").text("<?php echo esc_js(__('Please enter a preset name.', 'thaaniyamhub-multi-vendor-orders')); ?>").show();
                    $("#pdm_name").focus();
                    return;
                }
                if (length <= 0 || width <= 0 || height <= 0) {
                    $("#pdm_modal_error").text("<?php echo esc_js(__('Length, Width, and Height must all be positive numbers (greater than 0).', 'thaaniyamhub-multi-vendor-orders')); ?>").show();
                    return;
                }

                $btn.prop("disabled", true).text("<?php echo esc_js(__('Saving...', 'thaaniyamhub-multi-vendor-orders')); ?>");
                $("#pdm_modal_error").hide();

                $.post(ajaxurl, {
                    action: "thaaniyamhub_pdm_save_preset",
                    _nonce: nonce,
                    vendor_id: vendorId,
                    preset_id: presetId,
                    name: name,
                    weight: weight,
                    length: length,
                    width: width,
                    height: height
                }, function(res) {
                    $btn.prop("disabled", false).text("<?php echo esc_js(__('Save Dimension', 'thaaniyamhub-multi-vendor-orders')); ?>");
                    if (res.success) {
                        closeModal();
                        renderPresetsTable(res.data.presets);
                    } else {
                        $("#pdm_modal_error").text(res.data || "<?php echo esc_js(__('Error saving preset.', 'thaaniyamhub-multi-vendor-orders')); ?>").show();
                    }
                }).fail(function() {
                    $btn.prop("disabled", false).text("<?php echo esc_js(__('Save Dimension', 'thaaniyamhub-multi-vendor-orders')); ?>");
                    $("#pdm_modal_error").text("<?php echo esc_js(__('Network error occurred.', 'thaaniyamhub-multi-vendor-orders')); ?>").show();
                });
            });

            // Delete Preset via AJAX
            $(document).on("click", ".thaaniyamhub-pdm-btn-delete", function(e) {
                e.preventDefault();
                var $row = $(this).closest("tr");
                var presetId = $row.data("preset-id");
                var presetName = $row.data("name");
                var vendorId = $("#thaaniyamhub-pdm-vendor-select").val();

                if (!confirm('<?php echo esc_js(__('Are you sure you want to delete the package dimension preset: ', 'thaaniyamhub-multi-vendor-orders')); ?>"' + presetName + '"?')) {
                    return;
                }

                $row.css("opacity", "0.4");

                $.post(ajaxurl, {
                    action: "thaaniyamhub_pdm_delete_preset",
                    _nonce: nonce,
                    vendor_id: vendorId,
                    preset_id: presetId
                }, function(res) {
                    if (res.success) {
                        renderPresetsTable(res.data.presets);
                    } else {
                        alert(res.data || "<?php echo esc_js(__('Error deleting preset.', 'thaaniyamhub-multi-vendor-orders')); ?>");
                        $row.css("opacity", "1");
                    }
                }).fail(function() {
                    alert("<?php echo esc_js(__('Network error occurred.', 'thaaniyamhub-multi-vendor-orders')); ?>");
                    $row.css("opacity", "1");
                });
            });

            // Re-render Table helper
            function renderPresetsTable(presets) {
                var $tbody = $("#thaaniyamhub-pdm-tbody");
                $tbody.empty();

                var count = presets ? presets.length : 0;
                var countText = count === 1 ? "1 Dimension Saved" : count + " Dimensions Saved";
                $("#thaaniyamhub-pdm-preset-count-badge").text(countText);

                if (!presets || presets.length === 0) {
                    var emptyHtml = '<tr class="thaaniyamhub-pdm-empty-row">' +
                        '<td colspan="6">' +
                        '<div class="thaaniyamhub-pdm-empty-state">' +
                        '<span class="thaaniyamhub-pdm-empty-icon">📦</span>' +
                        '<p><?php echo esc_js(__('No package dimensions configured for this vendor yet.', 'thaaniyamhub-multi-vendor-orders')); ?></p>' +
                        '<button type="button" class="button button-secondary thaaniyamhub-pdm-btn-add-inline">' +
                        '<?php echo esc_js(__('+ Add First Dimension Preset', 'thaaniyamhub-multi-vendor-orders')); ?>' +
                        '</button>' +
                        '</div>' +
                        '</td>' +
                        '</tr>';
                    $tbody.html(emptyHtml);
                    return;
                }

                $.each(presets, function(i, p) {
                    var act = parseFloat(p.weight);
                    if (isNaN(act) || act < 0) { act = 0; }
                    var vol = parseFloat(((parseFloat(p.length) * parseFloat(p.width) * parseFloat(p.height)) / 5000).toFixed(3)) || 0;
                    var finalWt = Math.max(act, vol).toFixed(3);

                    var tr = $("<tr>")
                        .attr("data-preset-id", p.id)
                        .attr("data-name", p.name)
                        .attr("data-weight", p.weight)
                        .attr("data-length", p.length)
                        .attr("data-width", p.width)
                        .attr("data-height", p.height);

                    var nameTd = $("<td>").append($("<strong>").addClass("thaaniyamhub-pdm-preset-name").text(p.name));
                    var weightText = act > 0 ? (p.weight + " kg") : "0 kg";
                    var weightTd = $("<td>").append($("<span>").addClass("thaaniyamhub-pdm-value-badge").text(weightText));
                    var dimText = p.length + " cm (L) × " + p.width + " cm (W) × " + p.height + " cm (H)";
                    var dimTd = $("<td>").append($("<span>").addClass("thaaniyamhub-pdm-dim-text").text(dimText));
                    var volTd = $("<td>").append($("<span>").addClass("thaaniyamhub-pdm-vol-text").attr("title", "(L × W × H) / 5000").text("~" + vol.toFixed(3) + " kg"));
                    var finalTd = $("<td>").append($("<span>").addClass("thaaniyamhub-pdm-final-badge").text("⚖️ " + finalWt + " kg"));

                    var actTd = $("<td>").css("text-align", "right");
                    var editBtn = $("<button>").attr("type", "button").addClass("button button-small thaaniyamhub-pdm-btn-edit").text("✏️ <?php echo esc_js(__('Edit', 'thaaniyamhub-multi-vendor-orders')); ?> ");
                    var delBtn = $("<button>").attr("type", "button").addClass("button button-small button-link-delete thaaniyamhub-pdm-btn-delete").text("🗑️ <?php echo esc_js(__('Delete', 'thaaniyamhub-multi-vendor-orders')); ?>");

                    actTd.append(editBtn).append(" ").append(delBtn);

                    tr.append(nameTd).append(weightTd).append(dimTd).append(volTd).append(finalTd).append(actTd);
                    $tbody.append(tr);
                });
            }
        });
        </script>
        <?php
    }

    // =========================================================================
    // STYLES
    // =========================================================================

    private static function render_styles()
    {
        ?>
        <style>
            .thaaniyamhub-pdm-wrap {
                max-width: 1200px;
                margin: 20px 20px 0 0;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            }
            .thaaniyamhub-pdm-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
                color: #fff;
                padding: 24px 28px;
                border-radius: 12px;
                margin-bottom: 24px;
                box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
            }
            .thaaniyamhub-pdm-header-left {
                display: flex;
                align-items: center;
                gap: 18px;
            }
            .thaaniyamhub-pdm-icon-badge {
                font-size: 32px;
                background: rgba(255,255,255,0.12);
                border: 1px solid rgba(255,255,255,0.18);
                width: 60px;
                height: 60px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }
            .thaaniyamhub-pdm-title {
                color: #fff;
                font-size: 22px;
                font-weight: 700;
                margin: 0 0 4px 0;
                display: flex;
                align-items: center;
                gap: 10px;
                line-height: 1.2;
            }
            .thaaniyamhub-pdm-subtitle {
                font-size: 11px;
                background: #3b82f6;
                color: #fff;
                font-weight: 600;
                text-transform: uppercase;
                padding: 2px 8px;
                border-radius: 4px;
                letter-spacing: 0.5px;
            }
            .thaaniyamhub-pdm-header-desc {
                color: #cbd5e1;
                font-size: 13px;
                margin: 0;
                max-width: 750px;
                line-height: 1.5;
            }
            .thaaniyamhub-pdm-card {
                background: #fff;
                border: 1px solid #e2e8f0;
                border-radius: 10px;
                padding: 20px 24px;
                margin-bottom: 20px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            }
            .thaaniyamhub-pdm-vendor-bar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 16px;
            }
            .thaaniyamhub-pdm-field-group {
                display: flex;
                align-items: center;
                gap: 12px;
                flex: 1;
                min-width: 280px;
            }
            .thaaniyamhub-pdm-field-group label {
                font-size: 14px;
                color: #334155;
                white-space: nowrap;
            }
            .thaaniyamhub-pdm-select {
                min-width: 320px;
                max-width: 480px;
                height: 38px;
                border-radius: 6px;
                border: 1px solid #cbd5e1;
                font-size: 13px;
                font-weight: 500;
                color: #1e293b;
            }
            .thaaniyamhub-pdm-btn-add {
                height: 38px !important;
                line-height: 1 !important;
                padding: 0 18px !important;
                font-size: 13px !important;
                font-weight: 600 !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 6px;
                border-radius: 6px !important;
                background: #2563eb !important;
                border-color: #1d4ed8 !important;
            }
            .thaaniyamhub-pdm-btn-add .dashicons {
                margin: 0 !important;
                line-height: 1 !important;
                font-size: 18px !important;
                width: 18px !important;
                height: 18px !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
            }
            .thaaniyamhub-pdm-btn-add:hover {
                background: #1d4ed8 !important;
            }
            .thaaniyamhub-pdm-card-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 16px;
                padding-bottom: 14px;
                border-bottom: 1px solid #f1f5f9;
            }
            .thaaniyamhub-pdm-card-title {
                font-size: 16px;
                font-weight: 600;
                color: #1e293b;
                margin: 0;
            }
            .thaaniyamhub-pdm-highlight {
                color: #2563eb;
            }
            .thaaniyamhub-pdm-count-badge {
                font-size: 12px;
                font-weight: 600;
                background: #f1f5f9;
                color: #475569;
                padding: 4px 10px;
                border-radius: 20px;
                border: 1px solid #e2e8f0;
            }
            .thaaniyamhub-pdm-table th {
                font-weight: 600;
                color: #475569;
                font-size: 12px;
                padding: 12px 14px;
            }
            .thaaniyamhub-pdm-table td {
                padding: 12px 14px;
                vertical-align: middle;
            }
            .thaaniyamhub-pdm-preset-name {
                font-size: 13px;
                color: #0f172a;
            }
            .thaaniyamhub-pdm-value-badge {
                display: inline-block;
                background: #eff6ff;
                color: #1d4ed8;
                font-weight: 600;
                font-size: 12px;
                padding: 3px 8px;
                border-radius: 4px;
                border: 1px solid #bfdbfe;
            }
            .thaaniyamhub-pdm-dim-text {
                font-size: 12px;
                color: #334155;
                font-family: monospace;
            }
            .thaaniyamhub-pdm-vol-text {
                font-size: 12px;
                color: #64748b;
            }
            .thaaniyamhub-pdm-empty-state {
                text-align: center;
                padding: 36px 16px;
                color: #64748b;
            }
            .thaaniyamhub-pdm-empty-icon {
                font-size: 40px;
                display: block;
                margin-bottom: 10px;
                opacity: 0.7;
            }
            .thaaniyamhub-pdm-empty-state p {
                font-size: 14px;
                margin-bottom: 14px;
            }
            .thaaniyamhub-pdm-modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(15, 23, 42, 0.6);
                backdrop-filter: blur(2px);
                z-index: 100050;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .thaaniyamhub-pdm-modal-box {
                background: #fff;
                width: 460px;
                max-width: 90vw;
                border-radius: 10px;
                box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2), 0 10px 10px -5px rgba(0,0,0,0.1);
                overflow: hidden;
            }
            .thaaniyamhub-pdm-modal-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 16px 20px;
                border-bottom: 1px solid #e2e8f0;
                background: #f8fafc;
            }
            .thaaniyamhub-pdm-modal-header h3 {
                margin: 0;
                font-size: 16px;
                font-weight: 600;
                color: #0f172a;
            }
            .thaaniyamhub-pdm-modal-close {
                background: none;
                border: none;
                font-size: 24px;
                line-height: 1;
                cursor: pointer;
                color: #94a3b8;
            }
            .thaaniyamhub-pdm-modal-close:hover {
                color: #ef4444;
            }
            .thaaniyamhub-pdm-modal-body {
                padding: 20px;
            }
            .thaaniyamhub-pdm-form-group {
                margin-bottom: 14px;
            }
            .thaaniyamhub-pdm-form-group label {
                display: block;
                font-size: 12px;
                font-weight: 600;
                color: #334155;
                margin-bottom: 5px;
            }
            .thaaniyamhub-pdm-form-group input {
                height: 36px;
                border-radius: 5px;
                border: 1px solid #cbd5e1;
                padding: 0 10px;
                font-size: 13px;
            }
            .thaaniyamhub-pdm-grid-2 {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }
            .thaaniyamhub-pdm-final-badge {
                display: inline-block;
                background: #eff6ff;
                color: #1d4ed8;
                font-weight: 700;
                font-size: 12px;
                padding: 3px 8px;
                border-radius: 4px;
                border: 1px solid #bfdbfe;
                letter-spacing: 0.2px;
            }
            .thaaniyamhub-pdm-weights-box {
                margin-top: 14px;
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                padding: 12px 14px;
            }
            .thaaniyamhub-pdm-weights-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                font-size: 12px;
                color: #475569;
                margin-bottom: 6px;
            }
            .thaaniyamhub-pdm-final-row {
                margin-bottom: 0;
                padding-top: 8px;
                border-top: 1px dashed #cbd5e1;
            }
            .thaaniyamhub-pdm-weights-label {
                font-weight: 500;
                color: #334155;
            }
            .thaaniyamhub-pdm-final-row .thaaniyamhub-pdm-weights-label {
                font-weight: 600;
                color: #0f172a;
            }
            .thaaniyamhub-pdm-weights-val {
                font-weight: 600;
                color: #334155;
            }
            .thaaniyamhub-pdm-final-highlight {
                font-size: 14px;
                font-weight: 700;
                color: #1d4ed8;
                background: #eff6ff;
                border: 1px solid #bfdbfe;
                padding: 2px 8px;
                border-radius: 4px;
            }
            .thaaniyamhub-pdm-modal-error {
                margin-top: 12px;
                padding: 8px 12px;
                background: #fee2e2;
                border: 1px solid #fca5a5;
                color: #b91c1c;
                font-size: 12px;
                border-radius: 5px;
                font-weight: 500;
            }
            .thaaniyamhub-pdm-modal-footer {
                display: flex;
                justify-content: flex-end;
                gap: 10px;
                padding: 14px 20px;
                background: #f8fafc;
                border-top: 1px solid #e2e8f0;
            }
            .req {
                color: #ef4444;
            }
        </style>
        <?php
    }
}
}
