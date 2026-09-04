<?php
/**
 * Thaaniyam Hub Marketplace — Pickup Location Audit Logger
 *
 * Provides comprehensive A-Z audit logging for all vendor Shiprocket pickup
 * location assignments, changes, user meta updates, option updates, API syncs,
 * and diagnostic events.
 *
 * @package thaaniyamhub-multi-vendor-orders
 */

defined('ABSPATH') || exit;

if (!class_exists('ThaaniyamHub_Pickup_Logger')) {
class ThaaniyamHub_Pickup_Logger
{
    /**
     * Log file subdirectory inside wp-content/uploads.
     */
    const LOG_DIR_NAME = 'thaaniyamhub-logs';
    const LOG_FILE_NAME = 'pickup-location-audit.log';

    /**
     * Prevent recursion during hook execution.
     *
     * @var bool
     */
    private static $is_logging = false;

    /**
     * Cache previous meta values before update to accurately log old vs new.
     *
     * @var array
     */
    private static $previous_meta = [];

    /**
     * Cache previous option values before update.
     *
     * @var array|null
     */
    private static $previous_option = null;

    /**
     * Bootstrap the logger and register all WordPress hooks.
     */
    public static function init()
    {
        // 1. Hook into user metadata updates and deletions for _shiprocket_pickup_id
        add_filter('update_user_metadata', [__CLASS__, 'capture_pre_user_meta_update'], 10, 5);
        add_action('updated_user_meta', [__CLASS__, 'on_updated_user_meta'], 10, 4);
        add_action('added_user_meta', [__CLASS__, 'on_added_user_meta'], 10, 4);
        add_action('deleted_user_meta', [__CLASS__, 'on_deleted_user_meta'], 10, 4);

        // 2. Hook into option updates for thaaniyamhub_vendor_pickup_map
        add_filter('pre_update_option_thaaniyamhub_vendor_pickup_map', [__CLASS__, 'capture_pre_option_update'], 10, 3);
        add_action('update_option_thaaniyamhub_vendor_pickup_map', [__CLASS__, 'on_updated_option_map'], 10, 3);
        add_action('delete_option_thaaniyamhub_vendor_pickup_map', [__CLASS__, 'on_deleted_option_map'], 10, 1);

        // 3. Admin AJAX endpoints for log management
        add_action('wp_ajax_thaaniyamhub_pm_get_audit_logs', [__CLASS__, 'ajax_get_audit_logs']);
        add_action('wp_ajax_thaaniyamhub_pm_clear_audit_logs', [__CLASS__, 'ajax_clear_audit_logs']);
        add_action('wp_ajax_thaaniyamhub_pm_download_audit_logs', [__CLASS__, 'ajax_download_audit_logs']);
    }

    /**
     * Get the absolute path to the audit log directory.
     *
     * @return string
     */
    public static function get_log_dir(): string
    {
        $upload_dir = function_exists('wp_upload_dir') ? wp_upload_dir() : ['basedir' => defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/uploads' : sys_get_temp_dir()];
        $basedir = !empty($upload_dir['basedir']) ? $upload_dir['basedir'] : (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/uploads' : sys_get_temp_dir());
        $dir = (function_exists('trailingslashit') ? trailingslashit($basedir) : (rtrim($basedir, '/\\') . '/')) . self::LOG_DIR_NAME;

        if (!file_exists($dir)) {
            if (function_exists('wp_mkdir_p')) {
                wp_mkdir_p($dir);
            } else {
                @mkdir($dir, 0755, true);
            }
            // Protect log directory from direct browser access.
            @file_put_contents($dir . '/.htaccess', "Order Deny,Allow\nDeny from all\n");
            @file_put_contents($dir . '/index.php', "<?php // Silence is golden.\n");
        }

        return $dir;
    }

    /**
     * Get the absolute path to the audit log file.
     *
     * @return string
     */
    public static function get_log_file_path(): string
    {
        return self::get_log_dir() . '/' . self::LOG_FILE_NAME;
    }

    /**
     * Capture pre-update user meta to determine previous value.
     */
    public static function capture_pre_user_meta_update($check, $object_id, $meta_key, $meta_value, $prev_value)
    {
        if ('_shiprocket_pickup_id' === $meta_key && !self::$is_logging) {
            $current = get_user_meta((int)$object_id, '_shiprocket_pickup_id', true);
            self::$previous_meta[$object_id] = $current;
        }
        return $check;
    }

    /**
     * Handle WordPress updated_user_meta hook.
     */
    public static function on_updated_user_meta($meta_id, $object_id, $meta_key, $_meta_value)
    {
        if ('_shiprocket_pickup_id' !== $meta_key || self::$is_logging) {
            return;
        }

        $old_value = self::$previous_meta[$object_id] ?? '';
        unset(self::$previous_meta[$object_id]);

        $new_value = (string) $_meta_value;

        // Determine if value actually changed
        $status = ($old_value === $new_value) ? 'NO_CHANGE' : 'CHANGED';

        self::log_event([
            'event_type'  => 'USER_META_UPDATED',
            'vendor_id'   => (int) $object_id,
            'old_value'   => $old_value,
            'new_value'   => $new_value,
            'status'      => $status,
            'message'     => sprintf(
                'User meta "_shiprocket_pickup_id" updated for vendor #%d: "%s" → "%s"',
                $object_id,
                $old_value ?: '[none]',
                $new_value ?: '[none]'
            ),
        ]);
    }

    /**
     * Handle WordPress added_user_meta hook.
     */
    public static function on_added_user_meta($meta_id, $object_id, $meta_key, $_meta_value)
    {
        if ('_shiprocket_pickup_id' !== $meta_key || self::$is_logging) {
            return;
        }

        $new_value = (string) $_meta_value;

        self::log_event([
            'event_type'  => 'USER_META_ADDED',
            'vendor_id'   => (int) $object_id,
            'old_value'   => '',
            'new_value'   => $new_value,
            'status'      => 'CHANGED',
            'message'     => sprintf(
                'User meta "_shiprocket_pickup_id" added for vendor #%d: "%s"',
                $object_id,
                $new_value ?: '[none]'
            ),
        ]);
    }

    /**
     * Handle WordPress deleted_user_meta hook.
     */
    public static function on_deleted_user_meta($meta_ids, $object_id, $meta_key, $_meta_value)
    {
        if ('_shiprocket_pickup_id' !== $meta_key || self::$is_logging) {
            return;
        }

        self::log_event([
            'event_type'  => 'USER_META_DELETED',
            'vendor_id'   => (int) $object_id,
            'old_value'   => (string) $_meta_value,
            'new_value'   => '',
            'status'      => 'CHANGED',
            'message'     => sprintf(
                'User meta "_shiprocket_pickup_id" deleted for vendor #%d (was "%s")',
                $object_id,
                $_meta_value ?: '[none]'
            ),
        ]);
    }

    /**
     * Capture pre-update option for thaaniyamhub_vendor_pickup_map.
     */
    public static function capture_pre_option_update($value, $old_value, $option)
    {
        if (!self::$is_logging) {
            self::$previous_option = $old_value;
        }
        return $value;
    }

    /**
     * Handle WordPress update_option_thaaniyamhub_vendor_pickup_map hook.
     */
    public static function on_updated_option_map($old_value, $value, $option)
    {
        if (self::$is_logging) {
            return;
        }

        $old_map = is_array($old_value) ? $old_value : [];
        $new_map = is_array($value) ? $value : [];

        // Identify which vendors were changed in the map
        $all_vendor_ids = array_unique(array_merge(array_keys($old_map), array_keys($new_map)));
        $changes = [];

        foreach ($all_vendor_ids as $vid) {
            $v_old = $old_map[$vid] ?? '';
            $v_new = $new_map[$vid] ?? '';
            if ($v_old !== $v_new) {
                $changes[$vid] = [
                    'old' => $v_old,
                    'new' => $v_new,
                ];
            }
        }

        self::log_event([
            'event_type'  => 'OPTION_MAP_UPDATED',
            'vendor_id'   => count($changes) === 1 ? (int) array_key_first($changes) : 0,
            'old_value'   => $old_map,
            'new_value'   => $new_map,
            'status'      => empty($changes) ? 'NO_CHANGE' : 'CHANGED',
            'message'     => sprintf(
                'Option "thaaniyamhub_vendor_pickup_map" updated. %d vendor(s) changed: %s',
                count($changes),
                !empty($changes) ? wp_json_encode($changes) : 'No vendor differences'
            ),
            'extra_data'  => ['vendor_changes' => $changes],
        ]);
    }

    /**
     * Handle WordPress delete_option_thaaniyamhub_vendor_pickup_map hook.
     */
    public static function on_deleted_option_map($option)
    {
        if (self::$is_logging) {
            return;
        }

        self::log_event([
            'event_type'  => 'OPTION_MAP_DELETED',
            'vendor_id'   => 0,
            'old_value'   => 'ALL_MAP_OPTIONS',
            'new_value'   => '',
            'status'      => 'CHANGED',
            'message'     => 'Option "thaaniyamhub_vendor_pickup_map" was completely deleted.',
        ]);
    }

    /**
     * Explicitly log any specific pickup event with full A-Z details.
     *
     * @param array $args Custom event details.
     * @return array The complete logged entry.
     */
    public static function log_event(array $args): array
    {
        if (self::$is_logging) {
            return [];
        }

        self::$is_logging = true;

        try {
            $entry = self::build_log_entry($args);
            self::write_to_log_file($entry);
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[ThaaniyamHub Pickup Logger Error] ' . $e->getMessage());
            }
            $entry = [];
        } finally {
            self::$is_logging = false;
        }

        return $entry;
    }

    /**
     * Construct a complete, standardized A-Z log entry.
     *
     * @param array $args
     * @return array
     */
    private static function build_log_entry(array $args): array
    {
        $vendor_id = (int) ($args['vendor_id'] ?? 0);
        $vendor_info = self::get_vendor_info($vendor_id);
        $user_info = self::get_current_actor_info();
        $request_info = self::get_request_info();
        $backtrace = self::get_clean_backtrace();

        $now_utc = gmdate('Y-m-d H:i:s') . ' UTC';
        $now_local = current_time('mysql') . ' (Site Time)';

        return [
            'id'             => 'evt_' . wp_generate_uuid4(),
            'timestamp_utc'  => $now_utc,
            'timestamp_local'=> $now_local,
            'event_type'     => sanitize_key($args['event_type'] ?? 'UNKNOWN_EVENT'),
            'status'         => sanitize_text_field($args['status'] ?? 'INFO'),
            'message'        => sanitize_text_field($args['message'] ?? ''),
            'vendor_id'      => $vendor_id,
            'vendor_name'    => $vendor_info['display_name'] ?? '',
            'vendor_store'   => $vendor_info['store_name'] ?? '',
            'vendor_email'   => $vendor_info['user_email'] ?? '',
            'old_value'      => $args['old_value'] ?? '',
            'new_value'      => $args['new_value'] ?? '',
            'actor'          => $user_info,
            'request'        => $request_info,
            'origin'         => [
                'file'     => $backtrace['caller_file'] ?? 'unknown',
                'line'     => $backtrace['caller_line'] ?? 0,
                'function' => $backtrace['caller_function'] ?? 'unknown',
            ],
            'backtrace'      => $backtrace['stack'] ?? [],
            'extra_data'     => $args['extra_data'] ?? [],
        ];
    }

    /**
     * Retrieve detailed info for the actor performing the action.
     *
     * @return array
     */
    public static function get_current_actor_info(): array
    {
        $is_cli = (defined('WP_CLI') && WP_CLI);
        $is_cron = (defined('DOING_CRON') && DOING_CRON) || wp_doing_cron();
        $is_ajax = wp_doing_ajax();
        $is_rest = (defined('REST_REQUEST') && REST_REQUEST);

        $current_user_id = get_current_user_id();
        if ($current_user_id) {
            $user = get_userdata($current_user_id);
            return [
                'user_id'      => $current_user_id,
                'user_login'   => $user ? $user->user_login : 'unknown',
                'display_name' => $user ? $user->display_name : 'unknown',
                'user_email'   => $user ? $user->user_email : 'unknown',
                'roles'        => $user ? (array) $user->roles : [],
                'context'      => $is_ajax ? 'AJAX' : ($is_rest ? 'REST' : ($is_cron ? 'CRON' : ($is_cli ? 'WP-CLI' : 'WEB_UI'))),
            ];
        }

        $context = 'GUEST / UNAUTHENTICATED';
        if ($is_cron) {
            $context = 'WP_CRON';
        } elseif ($is_cli) {
            $context = 'WP_CLI';
        } elseif ($is_rest) {
            $context = 'REST_API_UNAUTH';
        } elseif ($is_ajax) {
            $context = 'AJAX_UNAUTH';
        }

        return [
            'user_id'      => 0,
            'user_login'   => 'none',
            'display_name' => $context,
            'user_email'   => 'none',
            'roles'        => [],
            'context'      => $context,
        ];
    }

    /**
     * Retrieve vendor store and user information safely.
     *
     * @param int $vendor_id
     * @return array
     */
    private static function get_vendor_info(int $vendor_id): array
    {
        if (!$vendor_id) {
            return [
                'display_name' => '',
                'store_name'   => '',
                'user_email'   => '',
            ];
        }

        $user = get_userdata($vendor_id);
        $store_name = '';

        if (function_exists('wcfmmp_get_store')) {
            $store = wcfmmp_get_store($vendor_id);
            if ($store) {
                $info = $store->get_shop_info();
                $store_name = trim($info['store_name'] ?? '');
            }
        }
        if (!$store_name) {
            $store_name = (string) get_user_meta($vendor_id, 'store_name', true);
        }

        return [
            'display_name' => $user ? $user->display_name : ('User #' . $vendor_id),
            'store_name'   => $store_name,
            'user_email'   => $user ? $user->user_email : '',
        ];
    }

    /**
     * Extract client IP address accurately considering reverse proxies and Cloudflare.
     *
     * @return string
     */
    public static function get_client_ip(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip_list = explode(',', (string)$_SERVER[$header]);
                $ip = trim($ip_list[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Gather request context (IP, URL, method, headers, sanitized payload).
     *
     * @return array
     */
    public static function get_request_info(): array
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? (defined('WP_CLI') && WP_CLI ? 'CLI' : 'UNKNOWN');
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip = self::get_client_ip();

        // Sanitize POST data to hide passwords, tokens, API keys
        $sanitized_post = [];
        if (!empty($_POST) && is_array($_POST)) {
            $sanitized_post = self::sanitize_payload($_POST);
        }

        return [
            'client_ip'   => $ip,
            'http_method' => $method,
            'request_uri' => $uri,
            'referer'     => $referer,
            'user_agent'  => $user_agent,
            'post_params' => $sanitized_post,
            'get_params'  => !empty($_GET) && is_array($_GET) ? self::sanitize_payload($_GET) : [],
        ];
    }

    /**
     * Redact sensitive credentials in request payloads before logging.
     *
     * @param array $data
     * @return array
     */
    private static function sanitize_payload(array $data): array
    {
        $sensitive_keys = [
            'pwd', 'password', 'pass', 'token', 'auth', 'secret', 'key',
            'api_key', 'authorization', '_wpnonce', '_nonce'
        ];

        $cleaned = [];
        foreach ($data as $k => $v) {
            $k_lower = strtolower((string)$k);
            $is_sensitive = false;
            foreach ($sensitive_keys as $s_key) {
                if (strpos($k_lower, $s_key) !== false) {
                    $is_sensitive = true;
                    break;
                }
            }

            if ($is_sensitive) {
                $cleaned[$k] = '[REDACTED]';
            } elseif (is_array($v)) {
                $cleaned[$k] = self::sanitize_payload($v);
            } elseif (is_string($v) && strlen($v) > 500) {
                $cleaned[$k] = substr($v, 0, 500) . '… [TRUNCATED]';
            } else {
                $cleaned[$k] = $v;
            }
        }

        return $cleaned;
    }

    /**
     * Clean and format the PHP backtrace.
     *
     * @return array
     */
    private static function get_clean_backtrace(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 25);
        $stack = [];
        $caller_file = 'unknown';
        $caller_line = 0;
        $caller_function = 'unknown';

        $skip_classes = [
            'ThaaniyamHub_Pickup_Logger',
        ];

        $found_caller = false;

        foreach ($trace as $frame) {
            $class = $frame['class'] ?? '';
            $func = $frame['function'] ?? '';
            $file = $frame['file'] ?? '';
            $line = $frame['line'] ?? 0;

            if (in_array($class, $skip_classes, true)) {
                continue;
            }

            if (!$found_caller) {
                $caller_file = $file ? self::clean_file_path($file) : 'unknown';
                $caller_line = $line;
                $caller_function = ($class ? $class . '::' : '') . $func;
                $found_caller = true;
            }

            $stack[] = [
                'file'     => $file ? self::clean_file_path($file) : '',
                'line'     => $line,
                'call'     => ($class ? $class . '::' : '') . $func . '()',
            ];
        }

        return [
            'caller_file'     => $caller_file,
            'caller_line'     => $caller_line,
            'caller_function' => $caller_function,
            'stack'           => array_slice($stack, 0, 15),
        ];
    }

    /**
     * Convert absolute paths to readable relative paths.
     *
     * @param string $path
     * @return string
     */
    private static function clean_file_path(string $path): string
    {
        $wp_content = wp_normalize_path(WP_CONTENT_DIR);
        $norm_path = wp_normalize_path($path);

        if (strpos($norm_path, $wp_content) === 0) {
            return 'wp-content' . substr($norm_path, strlen($wp_content));
        }

        $abspath = wp_normalize_path(ABSPATH);
        if (strpos($norm_path, $abspath) === 0) {
            return substr($norm_path, strlen($abspath));
        }

        return basename($path);
    }

    /**
     * Append the structured entry to the log file (atomic lock with file rotation).
     *
     * @param array $entry
     */
    private static function write_to_log_file(array $entry)
    {
        $log_file = self::get_log_file_path();

        // Rotate log file if > 15MB
        if (file_exists($log_file) && @filesize($log_file) > 15 * 1024 * 1024) {
            @rename($log_file, $log_file . '.' . date('Y-m-d-His') . '.bak');
        }

        $json_line = wp_json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        @file_put_contents($log_file, $json_line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Read and filter recent audit logs.
     *
     * @param int    $limit
     * @param int    $offset
     * @param array  $filters
     * @return array ['total' => int, 'logs' => array]
     */
    public static function get_recent_logs(int $limit = 100, int $offset = 0, array $filters = []): array
    {
        $log_file = self::get_log_file_path();
        if (!file_exists($log_file) || !is_readable($log_file)) {
            return ['total' => 0, 'logs' => []];
        }

        $lines = @file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return ['total' => 0, 'logs' => []];
        }

        // Parse lines in reverse chronological order (newest first)
        $lines = array_reverse($lines);
        $matched = [];

        $search = strtolower(trim((string)($filters['search'] ?? '')));
        $event_filter = sanitize_key($filters['event_type'] ?? '');
        $status_filter = strtoupper(trim((string)($filters['status'] ?? '')));
        $vendor_filter = (int) ($filters['vendor_id'] ?? 0);

        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if (!$data || !is_array($data)) {
                continue;
            }

            if ($event_filter && ($data['event_type'] ?? '') !== $event_filter) {
                continue;
            }

            if ($status_filter && ($data['status'] ?? '') !== $status_filter) {
                continue;
            }

            if ($vendor_filter && (int)($data['vendor_id'] ?? 0) !== $vendor_filter) {
                continue;
            }

            if ($search) {
                $searchable = strtolower(
                    ($data['message'] ?? '') . ' ' .
                    ($data['vendor_name'] ?? '') . ' ' .
                    ($data['vendor_store'] ?? '') . ' ' .
                    ($data['actor']['display_name'] ?? '') . ' ' .
                    ($data['actor']['user_login'] ?? '') . ' ' .
                    ($data['request']['client_ip'] ?? '') . ' ' .
                    (is_string($data['old_value'] ?? '') ? $data['old_value'] : '') . ' ' .
                    (is_string($data['new_value'] ?? '') ? $data['new_value'] : '')
                );
                if (strpos($searchable, $search) === false) {
                    continue;
                }
            }

            $matched[] = $data;
        }

        $total = count($matched);
        $paginated = array_slice($matched, $offset, $limit);

        return [
            'total' => $total,
            'logs'  => $paginated,
        ];
    }

    /**
     * Clear all records in the audit log file.
     *
     * @return bool
     */
    public static function clear_logs(): bool
    {
        $log_file = self::get_log_file_path();
        if (file_exists($log_file)) {
            return @unlink($log_file) || @file_put_contents($log_file, '') !== false;
        }
        return true;
    }

    /**
     * Get human-readable file size of the log file.
     *
     * @return string
     */
    public static function get_log_file_size_formatted(): string
    {
        $log_file = self::get_log_file_path();
        if (!file_exists($log_file)) {
            return '0 KB';
        }
        $bytes = @filesize($log_file);
        if ($bytes < 1024) {
            return $bytes . ' B';
        } elseif ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        } else {
            return round($bytes / (1024 * 1024), 2) . ' MB';
        }
    }

    // =========================================================================
    // AJAX ACTIONS
    // =========================================================================

    public static function ajax_get_audit_logs()
    {
        check_ajax_referer('thaaniyamhub_pm_nonce', '_nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions.', 403);
        }

        $limit = isset($_POST['limit']) ? min(500, max(10, (int) $_POST['limit'])) : 100;
        $offset = isset($_POST['offset']) ? max(0, (int) $_POST['offset']) : 0;
        $filters = [
            'search'     => sanitize_text_field($_POST['search'] ?? ''),
            'event_type' => sanitize_key($_POST['event_type'] ?? ''),
            'status'     => sanitize_text_field($_POST['status'] ?? ''),
            'vendor_id'  => (int) ($_POST['vendor_id'] ?? 0),
        ];

        $result = self::get_recent_logs($limit, $offset, $filters);
        $result['file_size'] = self::get_log_file_size_formatted();

        wp_send_json_success($result);
    }

    public static function ajax_clear_audit_logs()
    {
        check_ajax_referer('thaaniyamhub_pm_nonce', '_nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions.', 403);
        }

        $cleared = self::clear_logs();
        if ($cleared) {
            self::log_event([
                'event_type' => 'AUDIT_LOG_CLEARED',
                'vendor_id'  => 0,
                'status'     => 'INFO',
                'message'    => 'Pickup Location Audit Logs cleared by Administrator.',
            ]);
            wp_send_json_success(['message' => __('Audit logs cleared successfully.', 'thaaniyamhub-multi-vendor-orders')]);
        } else {
            wp_send_json_error(__('Failed to clear audit log file.', 'thaaniyamhub-multi-vendor-orders'));
        }
    }

    public static function ajax_download_audit_logs()
    {
        check_admin_referer('thaaniyamhub_pm_download_logs');
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Insufficient permissions.', 403);
        }

        $log_file = self::get_log_file_path();
        if (!file_exists($log_file) || !is_readable($log_file)) {
            wp_die('Log file is empty or does not exist.', 404);
        }

        header('Content-Description: File Transfer');
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="thaaniyamhub-pickup-audit-' . date('Y-m-d-His') . '.log"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($log_file));
        readfile($log_file);
        exit;
    }
}
}
