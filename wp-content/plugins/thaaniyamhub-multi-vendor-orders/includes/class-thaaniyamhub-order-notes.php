<?php
/**
 * Thaaniyam Hub Marketplace — Order Notes Deduplicator & Cleaner
 *
 * Prevents duplicate order notes from being created by redundant gateway callbacks,
 * asynchronous webhooks, repeated email failure retries, or duplicate sync actions.
 * Also provides automated cleanup for legacy duplicate order notes.
 *
 * @package thaaniyamhub-multi-vendor-orders
 */

defined('ABSPATH') || exit;

class ThaaniyamHub_Order_Notes
{
    /**
     * Runtime flag indicating if current inserting comment is a duplicate.
     *
     * @var bool
     */
    private static $is_duplicate_note = false;

    /**
     * Boot hooks.
     */
    public static function init()
    {
        add_filter('woocommerce_new_order_note_data', [__CLASS__, 'filter_new_order_note_data'], 10, 2);
        add_action('wp_insert_comment', [__CLASS__, 'purge_duplicate_on_insert'], 1, 2);
    }

    /**
     * Filter new order note data to detect duplicates before insertion.
     *
     * @param array $commentdata Comment data array.
     * @param array $args        Arguments including order_id.
     * @return array
     */
    public static function filter_new_order_note_data($commentdata, $args)
    {
        global $wpdb;

        self::$is_duplicate_note = false;
        $order_id = (int) ($args['order_id'] ?? ($commentdata['comment_post_ID'] ?? 0));
        $content  = trim($commentdata['comment_content'] ?? '');

        if (!$order_id || empty($content)) {
            return $commentdata;
        }

        $normalized_new = self::normalize_note_text($content);

        // Fetch recent notes for this order
        $recent_notes = $wpdb->get_results($wpdb->prepare(
            "SELECT comment_ID, comment_content, comment_date 
             FROM {$wpdb->comments} 
             WHERE comment_post_ID = %d 
               AND comment_type = 'order_note' 
             ORDER BY comment_ID DESC 
             LIMIT 30",
            $order_id
        ));

        if (empty($recent_notes)) {
            return $commentdata;
        }

        foreach ($recent_notes as $existing) {
            $normalized_existing = self::normalize_note_text($existing->comment_content);

            // 1. Exact or normalized text match
            if ($normalized_new === $normalized_existing) {
                self::$is_duplicate_note = true;
                break;
            }

            // 2. Cashfree payment transaction ID duplication (browser capture vs webhook)
            if (preg_match('/transaction\s*id:\s*([0-9a-zA-Z_-]+)/i', $normalized_new, $m_new)) {
                $tx_id = $m_new[1];
                if (preg_match('/transaction\s*id:\s*' . preg_quote($tx_id, '/') . '/i', $normalized_existing)) {
                    if (strpos($normalized_new, 'cashfree') !== false && strpos($normalized_existing, 'cashfree') !== false) {
                        self::$is_duplicate_note = true;
                        break;
                    }
                }
            }

            // 3. Repeated email failure / sent logs
            if (strpos($normalized_new, 'email') !== false && (strpos($normalized_new, 'failed to send') !== false || strpos($normalized_new, 'sent') !== false)) {
                if ($normalized_new === $normalized_existing) {
                    self::$is_duplicate_note = true;
                    break;
                }
            }

            // 4. Status sync / duplicate Shiprocket notes
            if (strpos($normalized_new, 'status synced from parent order') !== false && strpos($normalized_existing, 'status synced from parent order') !== false) {
                if ($normalized_new === $normalized_existing) {
                    self::$is_duplicate_note = true;
                    break;
                }
            }
        }

        return $commentdata;
    }

    /**
     * Purge duplicate comment immediately upon insertion.
     *
     * @param int        $comment_id Comment ID.
     * @param WP_Comment $comment    Comment object.
     */
    public static function purge_duplicate_on_insert($comment_id, $comment = null)
    {
        if (self::$is_duplicate_note && $comment_id > 0) {
            self::$is_duplicate_note = false;
            wp_delete_comment($comment_id, true);
        }
    }

    /**
     * Normalize note text for accurate deduplication comparison.
     *
     * @param string $text Raw note string.
     * @return string
     */
    private static function normalize_note_text($text)
    {
        $text = str_ireplace(['<br>', '<br/>', '<br />', '&nbsp;'], ' ', $text);
        $text = wp_strip_all_tags($text);
        $text = preg_replace('/\s+/', ' ', trim($text));
        return strtolower($text);
    }

    /**
     * Clean up all existing duplicate order notes across the database.
     *
     * @return int Number of duplicate notes deleted.
     */
    public static function clean_existing_duplicate_notes()
    {
        global $wpdb;

        // Fetch all order notes grouped by order
        $all_notes = $wpdb->get_results(
            "SELECT comment_ID, comment_post_ID, comment_content, comment_date 
             FROM {$wpdb->comments} 
             WHERE comment_type = 'order_note' 
             ORDER BY comment_post_ID ASC, comment_ID ASC"
        );

        if (empty($all_notes)) {
            return 0;
        }

        $deleted_count = 0;
        $seen = [];

        foreach ($all_notes as $n) {
            $order_id = (int) $n->comment_post_ID;
            $norm = self::normalize_note_text($n->comment_content);

            if (!isset($seen[$order_id])) {
                $seen[$order_id] = [
                    'texts' => [],
                    'tx_ids' => [],
                ];
            }

            $is_dup = false;

            // Check if text seen
            if (in_array($norm, $seen[$order_id]['texts'], true)) {
                $is_dup = true;
            } else {
                $seen[$order_id]['texts'][] = $norm;
            }

            // Check if Cashfree Transaction ID already recorded
            if (preg_match('/Transaction\s*Id:\s*([0-9a-zA-Z_-]+)/i', $norm, $m)) {
                $tx_id = $m[1];
                if (in_array($tx_id, $seen[$order_id]['tx_ids'], true)) {
                    $is_dup = true;
                } else {
                    $seen[$order_id]['tx_ids'][] = $tx_id;
                }
            }

            if ($is_dup) {
                wp_delete_comment((int) $n->comment_ID, true);
                $deleted_count++;
            }
        }

        return $deleted_count;
    }
}
