<?php
/**
 * Thaaniyam Hub Marketplace — Financial Ledger & Profit/Loss Report Dashboard
 *
 * Provides a comprehensive, order-basis financial reporting dashboard under
 * WooCommerce → Financial Ledger & Reports:
 *   - Real-time KPI summaries (Inflows, Discounts, Vendor Payouts, Logistics, Taxes, Gateway Fees, Net Profit & Margin)
 *   - Granular order-by-order ledger with coupon codes, commission GST, Shiprocket costs, Cashfree fees
 *   - Interactive filtering by Vendor, Search Term, Date Presets / Custom Range, Order Status, Payout Status, Profitability
 *   - Expandable line-item audit drawer with full math breakdown
 *   - One-click CSV export, print-ready PDF statement, and ledger re-sync tool
 *
 * @package thaaniyamhub-multi-vendor-orders
 */

defined('ABSPATH') || exit;

class ThaaniyamHub_PDF_Report
{

    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('wp_ajax_thaaniyamhub_export_pdf', [__CLASS__, 'stream_pdf']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_report_assets']);
    }

    // =========================================================================
    // 1. ADMIN MENU
    // =========================================================================

    public static function register_menu()
    {
        add_submenu_page(
            'woocommerce',
            __('Thaaniyam Hub Financial Ledger & Profit Report', 'thaaniyamhub-multi-vendor-orders'),
            __('Financial Ledger & Reports', 'thaaniyamhub-multi-vendor-orders'),
            'manage_woocommerce',
            'thaaniyamhub-vendor-reports',
            [__CLASS__, 'render_report_page']
        );
    }

    // =========================================================================
    // 2. MAIN REPORT DASHBOARD PAGE
    // =========================================================================

    /**
     * Render the financial ledger and profit/loss report page in wp-admin.
     */
    public static function render_report_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions.', 'thaaniyamhub-multi-vendor-orders'));
        }

        // ---- Filter Inputs ----
        $vendor_id     = (int) ($_GET['thaaniyamhub_vendor_id'] ?? 0);
        $search        = sanitize_text_field($_GET['thaaniyamhub_search'] ?? '');
        $date_range    = sanitize_text_field($_GET['thaaniyamhub_range'] ?? 'month');
        $from          = sanitize_text_field($_GET['thaaniyamhub_from'] ?? '');
        $to            = sanitize_text_field($_GET['thaaniyamhub_to'] ?? '');
        $order_status  = sanitize_text_field($_GET['thaaniyamhub_order_status'] ?? '');
        $payout_status = sanitize_text_field($_GET['thaaniyamhub_payout_status'] ?? '');
        $profitability = sanitize_text_field($_GET['thaaniyamhub_profitability'] ?? 'all');
        $limit         = max(5, min(500, (int) ($_GET['thaaniyamhub_limit'] ?? 25)));
        $paged         = max(1, (int) ($_GET['paged'] ?? 1));
        $orderby       = sanitize_text_field($_GET['thaaniyamhub_orderby'] ?? $_GET['orderby'] ?? 'sub_order_id');
        $order         = strtoupper(sanitize_text_field($_GET['thaaniyamhub_order'] ?? $_GET['order'] ?? 'DESC'));
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'DESC';
        }

        // Resolve date range
        [$from_resolved, $to_resolved] = self::resolve_date_range($date_range, $from, $to);

        // Load all vendors for dropdown
        $vendors = self::get_all_vendors();

        // Build query args — Default sort by order ID DESC (sub_order_id DESC)
        $query_args = [
            'vendor_id'     => $vendor_id,
            'search'        => $search,
            'from'          => $from_resolved,
            'to'            => $to_resolved,
            'order_status'  => $order_status,
            'payout_status' => $payout_status,
            'profitability' => $profitability,
            'limit'         => $limit,
            'paged'         => $paged,
            'orderby'       => $orderby,
            'order'         => $order,
        ];

        // Execute queries
        $ledger_data = ThaaniyamHub_Ledger::query_ledger($query_args);
        $rows        = $ledger_data['rows'];
        $total_count = $ledger_data['total_count'];
        $total_pages = $ledger_data['total_pages'];

        // Aggregate KPI totals
        $summary = ThaaniyamHub_Ledger::get_financial_summary($query_args);

        // Export URLs
        $export_csv_url = add_query_arg(
            array_merge($_GET, [
                'action' => 'thaaniyamhub_export_ledger_csv',
                '_nonce' => wp_create_nonce('thaaniyamhub_export_ledger_csv'),
            ]),
            admin_url('admin-ajax.php')
        );

        $export_pdf_url = add_query_arg(
            [
                'action'                 => 'thaaniyamhub_export_pdf',
                'thaaniyamhub_vendor_id' => $vendor_id,
                'thaaniyamhub_search'    => $search,
                'thaaniyamhub_from'      => $from_resolved,
                'thaaniyamhub_to'        => $to_resolved,
                '_nonce'                 => wp_create_nonce('thaaniyamhub_export_pdf'),
            ],
            admin_url('admin-ajax.php')
        );

        ?>
        <div class="wrap thaaniyamhub-ledger-wrap">
            <!-- Header Bar -->
            <div class="thaaniyamhub-header-flex">
                <div>
                    <h1 class="thaaniyamhub-page-title">
                        <span class="title-icon">📊</span> <?php esc_html_e('Financial Ledger & Profit/Loss Report', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </h1>
                    <p class="thaaniyamhub-subtitle">
                        <?php esc_html_e('Granular order-basis accounting of customer inflows, coupons, commissions, 18% GST taxes, Shiprocket freight, Cashfree gateway fees, and net marketplace profit.', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </p>
                </div>
                <div class="thaaniyamhub-header-actions">
                    <a href="<?php echo esc_url($export_csv_url); ?>" class="button button-secondary" target="_blank">
                        <span class="dashicons dashicons-media-spreadsheet"></span> <?php esc_html_e('Export CSV', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </a>
                    <a href="<?php echo esc_url($export_pdf_url); ?>" class="button button-secondary" target="_blank">
                        <span class="dashicons dashicons-printer"></span> <?php esc_html_e('Printable Statement', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </a>
                </div>
            </div>

            <!-- Advanced Filter Toolbar (Compact & Aligned 4-Column Grid) -->
            <div class="thaaniyamhub-filter-card">
                <form method="GET" action="" id="thaaniyamhub-filter-form">
                    <input type="hidden" name="page" value="thaaniyamhub-vendor-reports">
                    <input type="hidden" name="thaaniyamhub_orderby" value="<?php echo esc_attr($orderby); ?>">
                    <input type="hidden" name="thaaniyamhub_order" value="<?php echo esc_attr($order); ?>">

                    <div class="filter-grid">
                        <!-- Row 1, Col 1: Vendor / Store -->
                        <div class="filter-col">
                            <label for="thaaniyamhub_vendor_id"><?php esc_html_e('Vendor / Store:', 'thaaniyamhub-multi-vendor-orders'); ?></label>
                            <select name="thaaniyamhub_vendor_id" id="thaaniyamhub_vendor_id">
                                <option value="0"><?php esc_html_e('All Vendors & Stores', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                                <?php foreach ($vendors as $v): ?>
                                    <?php
                                    $v_name = $v->display_name;
                                    if (function_exists('wcfm_get_vendor_store_name')) {
                                        $store_n = wcfm_get_vendor_store_name($v->ID);
                                        if ($store_n) $v_name = $store_n . ' (' . $v->display_name . ')';
                                    }
                                    ?>
                                    <option value="<?php echo esc_attr($v->ID); ?>" <?php selected($v->ID, $vendor_id); ?>>
                                        <?php echo esc_html($v_name . ' [#' . $v->ID . ']'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Row 1, Col 2: Search -->
                        <div class="filter-col">
                            <label for="thaaniyamhub_search"><?php esc_html_e('Search:', 'thaaniyamhub-multi-vendor-orders'); ?></label>
                            <input type="text" name="thaaniyamhub_search" id="thaaniyamhub_search" value="<?php echo esc_attr($search); ?>" placeholder="Order #, AWB, Customer, Coupon...">
                        </div>

                        <!-- Row 1, Col 3: Date Period -->
                        <div class="filter-col">
                            <label for="thaaniyamhub_range"><?php esc_html_e('Date Period:', 'thaaniyamhub-multi-vendor-orders'); ?></label>
                            <select name="thaaniyamhub_range" id="thaaniyamhub_range">
                                <option value="today" <?php selected($date_range, 'today'); ?>><?php esc_html_e('Today', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                                <option value="yesterday" <?php selected($date_range, 'yesterday'); ?>><?php esc_html_e('Yesterday', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                                <option value="week" <?php selected($date_range, 'week'); ?>><?php esc_html_e('This Week (7 Days)', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                                <option value="month" <?php selected($date_range, 'month'); ?>><?php esc_html_e('This Month', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                                <option value="last_month" <?php selected($date_range, 'last_month'); ?>><?php esc_html_e('Last Month', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                                <option value="ytd" <?php selected($date_range, 'ytd'); ?>><?php esc_html_e('Year to Date', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                                <option value="all" <?php selected($date_range, 'all'); ?>><?php esc_html_e('All Time', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                                <option value="custom" <?php selected($date_range, 'custom'); ?>><?php esc_html_e('Custom Range', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                            </select>
                        </div>

                        <!-- Row 1, Col 4: Order Status -->
                        <div class="filter-col">
                            <label for="thaaniyamhub_order_status"><?php esc_html_e('Order Status:', 'thaaniyamhub-multi-vendor-orders'); ?></label>
                            <select name="thaaniyamhub_order_status" id="thaaniyamhub_order_status">
                                <option value=""><?php esc_html_e('All Statuses', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                                <option value="processing" <?php selected($order_status, 'processing'); ?>><?php esc_html_e('Processing', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                                <option value="completed" <?php selected($order_status, 'completed'); ?>><?php esc_html_e('Completed', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                                <option value="on-hold" <?php selected($order_status, 'on-hold'); ?>><?php esc_html_e('On Hold', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                                <option value="cancelled" <?php selected($order_status, 'cancelled'); ?>><?php esc_html_e('Cancelled', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                                <option value="refunded" <?php selected($order_status, 'refunded'); ?>><?php esc_html_e('Refunded', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                            </select>
                        </div>

                        <!-- Row 2, Col 1: Payout Status -->
                        <div class="filter-col">
                            <label for="thaaniyamhub_payout_status"><?php esc_html_e('Payout Status:', 'thaaniyamhub-multi-vendor-orders'); ?></label>
                            <select name="thaaniyamhub_payout_status" id="thaaniyamhub_payout_status">
                                <option value=""><?php esc_html_e('All Payouts', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                                <option value="pending" <?php selected($payout_status, 'pending'); ?>><?php esc_html_e('Pending', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                                <option value="processing" <?php selected($payout_status, 'processing'); ?>><?php esc_html_e('Processing', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                                <option value="disbursed" <?php selected($payout_status, 'disbursed'); ?>><?php esc_html_e('Disbursed', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                            </select>
                        </div>

                        <!-- Row 2, Col 2: Profitability -->
                        <div class="filter-col">
                            <label for="thaaniyamhub_profitability"><?php esc_html_e('Profitability:', 'thaaniyamhub-multi-vendor-orders'); ?></label>
                            <select name="thaaniyamhub_profitability" id="thaaniyamhub_profitability">
                                <option value="all" <?php selected($profitability, 'all'); ?>><?php esc_html_e('All Orders', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                                <option value="profitable" <?php selected($profitability, 'profitable'); ?>><?php esc_html_e('Profitable (≥ ₹0)', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                                <option value="loss" <?php selected($profitability, 'loss'); ?>><?php esc_html_e('Loss (< ₹0)', 'thaaniyamhub-multi-vendor-orders'); ?></option>
                            </select>
                        </div>

                        <!-- Row 2, Col 3: Limit / Rows -->
                        <div class="filter-col">
                            <label for="thaaniyamhub_limit"><?php esc_html_e('Rows Per Page:', 'thaaniyamhub-multi-vendor-orders'); ?></label>
                            <select name="thaaniyamhub_limit" id="thaaniyamhub_limit">
                                <option value="5" <?php selected($limit, 5); ?>>5</option>
                                <option value="10" <?php selected($limit, 10); ?>>10</option>
                                <option value="25" <?php selected($limit, 25); ?>>25</option>
                                <option value="50" <?php selected($limit, 50); ?>>50</option>
                                <option value="100" <?php selected($limit, 100); ?>>100</option>
                                <option value="200" <?php selected($limit, 200); ?>>200</option>
                            </select>
                        </div>

                        <!-- Row 2, Col 4: Filter Actions (Apply + Reset) -->
                        <div class="filter-col filter-col-actions">
                            <label class="filter-actions-label">&nbsp;</label>
                            <div class="filter-actions-wrap">
                                <button type="submit" class="button button-primary button-apply">
                                    <span class="dashicons dashicons-filter"></span> <?php esc_html_e('Apply', 'thaaniyamhub-multi-vendor-orders'); ?>
                                </button>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=thaaniyamhub-vendor-reports')); ?>" class="button button-secondary">
                                    <?php esc_html_e('Reset', 'thaaniyamhub-multi-vendor-orders'); ?>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Custom Date Pickers (conditionally displayed) -->
                    <div class="custom-date-row" id="thaaniyamhub-custom-dates" style="display:<?php echo 'custom' === $date_range ? 'flex' : 'none'; ?>;">
                        <div class="custom-date-field">
                            <label for="thaaniyamhub_from"><?php esc_html_e('From Date:', 'thaaniyamhub-multi-vendor-orders'); ?></label>
                            <input type="date" name="thaaniyamhub_from" id="thaaniyamhub_from" value="<?php echo esc_attr($from_resolved); ?>">
                        </div>
                        <div class="custom-date-field">
                            <label for="thaaniyamhub_to"><?php esc_html_e('To Date:', 'thaaniyamhub-multi-vendor-orders'); ?></label>
                            <input type="date" name="thaaniyamhub_to" id="thaaniyamhub_to" value="<?php echo esc_attr($to_resolved); ?>">
                        </div>
                    </div>
                </form>
            </div>

            <!-- KPI Executive Summary Cards (Compact 4x2 Grid) -->
            <div class="thaaniyamhub-kpi-grid">
                <!-- 1. Platform Gross Commission (Admin Revenue) -->
                <div class="thaaniyamhub-kpi-card kpi-commission">
                    <div class="kpi-card-head">
                        <span class="kpi-icon">💼</span>
                        <span class="thaaniyamhub-kpi-label"><?php esc_html_e('Platform Gross Commission', 'thaaniyamhub-multi-vendor-orders'); ?></span>
                    </div>
                    <div class="thaaniyamhub-kpi-value text-indigo"><?php echo wp_kses_post(wc_price($summary['total_commission'])); ?></div>
                    <div class="kpi-subtext">
                        <?php printf(esc_html__('On %s product value', 'thaaniyamhub-multi-vendor-orders'), wp_strip_all_tags(wc_price($summary['total_subtotal']))); ?>
                    </div>
                </div>

                <!-- 2. Admin Discounts / Coupons (Admin Loss) -->
                <div class="thaaniyamhub-kpi-card kpi-discounts">
                    <div class="kpi-card-head">
                        <span class="kpi-icon">🏷️</span>
                        <span class="thaaniyamhub-kpi-label"><?php esc_html_e('Admin Discounts & Coupons', 'thaaniyamhub-multi-vendor-orders'); ?></span>
                    </div>
                    <div class="thaaniyamhub-kpi-value text-red"><?php echo wp_kses_post(wc_price($summary['total_discounts'])); ?></div>
                    <div class="kpi-subtext">
                        <?php esc_html_e('Direct promotional loss', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </div>
                </div>

                <!-- 3. Total Incoming Customer Paid -->
                <div class="thaaniyamhub-kpi-card kpi-inflow">
                    <div class="kpi-card-head">
                        <span class="kpi-icon">📥</span>
                        <span class="thaaniyamhub-kpi-label"><?php esc_html_e('Total Inflow (Customer Paid)', 'thaaniyamhub-multi-vendor-orders'); ?></span>
                    </div>
                    <div class="thaaniyamhub-kpi-value text-emerald"><?php echo wp_kses_post(wc_price($summary['total_incoming'])); ?></div>
                    <div class="kpi-subtext">
                        <?php if (!empty($summary['total_refunds']) && (float)$summary['total_refunds'] > 0): ?>
                            <span style="color:#dc2626;font-weight:700;">-<?php echo wp_strip_all_tags(wc_price($summary['total_refunds'])); ?> <?php esc_html_e('Refunds', 'thaaniyamhub-multi-vendor-orders'); ?></span>
                            <span style="color:#059669;font-weight:700;margin-left:4px;">(Net: <?php echo wp_strip_all_tags(wc_price($summary['total_net_incoming'])); ?>)</span>
                        <?php else: ?>
                            <?php printf(esc_html__('%d orders (Gross: %s)', 'thaaniyamhub-multi-vendor-orders'), $summary['order_count'], wp_strip_all_tags(wc_price($summary['total_gross_sales']))); ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 4. Vendor Net Payouts -->
                <div class="thaaniyamhub-kpi-card kpi-vendor">
                    <div class="kpi-card-head">
                        <span class="kpi-icon">🏪</span>
                        <span class="thaaniyamhub-kpi-label"><?php esc_html_e('Vendor Net Payouts', 'thaaniyamhub-multi-vendor-orders'); ?></span>
                    </div>
                    <div class="thaaniyamhub-kpi-value text-blue"><?php echo wp_kses_post(wc_price($summary['total_vendor_payout'])); ?></div>
                    <div class="kpi-subtext">
                        <?php esc_html_e('Product Price minus Commission', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </div>
                </div>

                <!-- 5. Shiprocket Logistics Outflow -->
                <div class="thaaniyamhub-kpi-card kpi-logistics">
                    <div class="kpi-card-head">
                        <span class="kpi-icon">🚚</span>
                        <span class="thaaniyamhub-kpi-label"><?php esc_html_e('Shiprocket Logistics Cost', 'thaaniyamhub-multi-vendor-orders'); ?></span>
                    </div>
                    <div class="thaaniyamhub-kpi-value text-orange"><?php echo wp_kses_post(wc_price($summary['total_shiprocket_cost'])); ?></div>
                    <div class="kpi-subtext">
                        <?php printf(esc_html__('Customer Shipping: %s', 'thaaniyamhub-multi-vendor-orders'), wp_strip_all_tags(wc_price($summary['total_customer_shipping']))); ?>
                    </div>
                </div>

                <!-- 6. 18% GST on Commission -->
                <div class="thaaniyamhub-kpi-card kpi-tax">
                    <div class="kpi-card-head">
                        <span class="kpi-icon">🏛️</span>
                        <span class="thaaniyamhub-kpi-label"><?php esc_html_e('Tax on Comm. (18% GST)', 'thaaniyamhub-multi-vendor-orders'); ?></span>
                    </div>
                    <div class="thaaniyamhub-kpi-value text-pink"><?php echo wp_kses_post(wc_price($summary['total_commission_tax'])); ?></div>
                    <div class="kpi-subtext">
                        <?php esc_html_e('GST tax liability on commission', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </div>
                </div>

                <!-- 7. Cashfree PG & Service Costs -->
                <div class="thaaniyamhub-kpi-card kpi-gateway">
                    <div class="kpi-card-head">
                        <span class="kpi-icon">💳</span>
                        <span class="thaaniyamhub-kpi-label"><?php esc_html_e('Cashfree PG & Payout Fees', 'thaaniyamhub-multi-vendor-orders'); ?></span>
                    </div>
                    <div class="thaaniyamhub-kpi-value text-purple">
                        <?php echo wp_kses_post(wc_price($summary['total_gateway_fee'] + $summary['total_gateway_tax'] + $summary['total_other_costs'])); ?>
                    </div>
                    <div class="kpi-subtext">
                        <?php printf(esc_html__('PG ₹%s + GST ₹%s', 'thaaniyamhub-multi-vendor-orders'), number_format($summary['total_gateway_fee'], 2), number_format($summary['total_gateway_tax'], 2)); ?>
                    </div>
                </div>

                <!-- 8. NET PLATFORM PROFIT (Hero Card) -->
                <?php
                $is_positive = $summary['total_net_profit'] >= 0;
                $profit_card_class = $is_positive ? 'kpi-profit-positive' : 'kpi-profit-negative';
                $profit_text_class = $is_positive ? 'text-emerald' : 'text-red';
                ?>
                <div class="thaaniyamhub-kpi-card kpi-hero <?php echo esc_attr($profit_card_class); ?>">
                    <div class="kpi-card-head">
                        <span class="kpi-icon"><?php echo $is_positive ? '💰' : '⚠️'; ?></span>
                        <span class="thaaniyamhub-kpi-label"><?php esc_html_e('Net Platform Profit (Admin P&L)', 'thaaniyamhub-multi-vendor-orders'); ?></span>
                        <span class="badge-margin <?php echo $is_positive ? 'badge-margin-pos' : 'badge-margin-neg'; ?>">
                            <?php echo esc_html($summary['overall_margin']); ?>%
                        </span>
                    </div>
                    <div class="thaaniyamhub-kpi-value <?php echo esc_attr($profit_text_class); ?>">
                        <?php echo wp_kses_post(wc_price($summary['total_net_profit'])); ?>
                    </div>
                    <div class="kpi-subtext font-semibold">
                        <?php echo $is_positive ? esc_html__('Net marketplace operating profit', 'thaaniyamhub-multi-vendor-orders') : esc_html__('Net promotional & operating loss', 'thaaniyamhub-multi-vendor-orders'); ?>
                    </div>
                </div>
            </div>

            <!-- Granular Order-by-Order Table (Grouped Structure) -->
            <div class="thaaniyamhub-table-container">
                <div class="table-header-bar">
                    <div class="table-title">
                        <span class="dashicons dashicons-list-view"></span>
                        <strong><?php printf(esc_html__('Order Financial Records (%d orders found)', 'thaaniyamhub-multi-vendor-orders'), $total_count); ?></strong>
                    </div>
                    <div class="table-pagination-info">
                        <?php if ($total_pages > 1): ?>
                            <?php
                            $start_idx = (($paged - 1) * $limit) + 1;
                            $end_idx = min($total_count, $paged * $limit);
                            printf(esc_html__('Showing %d–%d of %d orders (Page %d of %d)', 'thaaniyamhub-multi-vendor-orders'), $start_idx, $end_idx, $total_count, $paged, $total_pages);
                            ?>
                        <?php else: ?>
                            <span><?php printf(esc_html__('Showing all %d orders', 'thaaniyamhub-multi-vendor-orders'), $total_count); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($rows)): ?>
                    <?php
                    // Helper to generate sortable column header links
                    $get_sort_link = function($column_key, $label) use ($orderby, $order) {
                        $is_active = ($orderby === $column_key) || 
                                     ('sub_order_id' === $column_key && in_array($orderby, ['order_id', 'parent_order_id', 'sub_order_id'], true)) ||
                                     ('created_at' === $column_key && in_array($orderby, ['date', 'order_date', 'created_at'], true));
                        $next_order = ($is_active && 'DESC' === $order) ? 'ASC' : 'DESC';
                        $url = add_query_arg([
                            'thaaniyamhub_orderby' => $column_key,
                            'thaaniyamhub_order'   => $next_order,
                            'paged'                => 1,
                        ]);
                        $icon = '';
                        if ($is_active) {
                            $icon = 'DESC' === $order
                                ? ' <span class="dashicons dashicons-arrow-down-alt2 sort-dir-icon"></span>'
                                : ' <span class="dashicons dashicons-arrow-up-alt2 sort-dir-icon"></span>';
                        }
                        return sprintf(
                            '<a href="%s" class="sort-header-link %s" title="%s">%s%s</a>',
                            esc_url($url),
                            $is_active ? 'is-sorted' : '',
                            esc_attr(sprintf(__('Sort by %s', 'thaaniyamhub-multi-vendor-orders'), $label)),
                            esc_html($label),
                            $icon
                        );
                    };
                    ?>
                    <div class="table-scroll-wrapper">
                        <table class="wp-list-table widefat fixed striped thaaniyamhub-ledger-table">
                            <thead>
                                <!-- Tier 1: Grouped Category Banners -->
                                <tr class="header-group-row">
                                    <th colspan="3" class="th-group-order">
                                        <span class="group-title">📋 <?php esc_html_e('ORDER & STORE', 'thaaniyamhub-multi-vendor-orders'); ?></span>
                                    </th>
                                    <th colspan="3" class="th-group-inflow">
                                        <span class="group-title">📥 <?php esc_html_e('SALES & INFLOW', 'thaaniyamhub-multi-vendor-orders'); ?></span>
                                    </th>
                                    <th colspan="2" class="th-group-commission">
                                        <span class="group-title">💼 <?php esc_html_e('PLATFORM SHARE', 'thaaniyamhub-multi-vendor-orders'); ?></span>
                                    </th>
                                    <th colspan="3" class="th-group-outflow">
                                        <span class="group-title">📤 <?php esc_html_e('OUTFLOW DEDUCTIONS', 'thaaniyamhub-multi-vendor-orders'); ?></span>
                                    </th>
                                    <th colspan="2" class="th-group-profit">
                                        <span class="group-title">📊 <?php esc_html_e('ADMIN P&L', 'thaaniyamhub-multi-vendor-orders'); ?></span>
                                    </th>
                                </tr>
                                <!-- Tier 2: Specific Column Headers -->
                                <tr class="header-col-row">
                                    <th style="width: 120px;"><?php echo $get_sort_link('sub_order_id', __('Order / Sub', 'thaaniyamhub-multi-vendor-orders')); ?></th>
                                    <th style="width: 125px;"><?php echo $get_sort_link('created_at', __('Date & Customer', 'thaaniyamhub-multi-vendor-orders')); ?></th>
                                    <th style="width: 120px;" class="col-grp-end"><?php esc_html_e('Vendor / Store', 'thaaniyamhub-multi-vendor-orders'); ?></th>
                                    <th style="width: 105px;" class="col-numeric"><?php echo $get_sort_link('gross_sales', __('Product Price', 'thaaniyamhub-multi-vendor-orders')); ?></th>
                                    <th style="width: 110px;" class="col-numeric"><?php echo $get_sort_link('discount_total', __('Admin Discount', 'thaaniyamhub-multi-vendor-orders')); ?></th>
                                    <th style="width: 115px;" class="col-numeric col-grp-end"><?php echo $get_sort_link('total_incoming', __('Customer Paid', 'thaaniyamhub-multi-vendor-orders')); ?></th>
                                    <th style="width: 110px;" class="col-numeric"><?php echo $get_sort_link('commission_deducted', __('Gross Comm.', 'thaaniyamhub-multi-vendor-orders')); ?></th>
                                    <th style="width: 90px;" class="col-numeric col-grp-end"><?php echo $get_sort_link('commission_tax', __('18% GST', 'thaaniyamhub-multi-vendor-orders')); ?></th>
                                    <th style="width: 115px;" class="col-numeric"><?php echo $get_sort_link('vendor_net_payout', __('Vendor Payout', 'thaaniyamhub-multi-vendor-orders')); ?></th>
                                    <th style="width: 125px;" class="col-numeric"><?php echo $get_sort_link('shiprocket_shipping_cost', __('Shiprocket Freight', 'thaaniyamhub-multi-vendor-orders')); ?></th>
                                    <th style="width: 105px;" class="col-numeric col-grp-end"><?php esc_html_e('Cashfree Fees', 'thaaniyamhub-multi-vendor-orders'); ?></th>
                                    <th style="width: 105px;" class="col-numeric"><?php esc_html_e('Total Outflow', 'thaaniyamhub-multi-vendor-orders'); ?></th>
                                    <th style="width: 120px;" class="col-numeric"><?php echo $get_sort_link('net_profit', __('Net Profit', 'thaaniyamhub-multi-vendor-orders')); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <?php
                                    $order_url = get_edit_post_link((int) $row->sub_order_id) ?: admin_url('admin.php?page=wc-orders&action=edit&id=' . $row->sub_order_id);
                                    $parent_url = get_edit_post_link((int) $row->parent_order_id) ?: admin_url('admin.php?page=wc-orders&action=edit&id=' . $row->parent_order_id);

                                    $v_name = 'Vendor #' . $row->vendor_id;
                                    if (function_exists('wcfm_get_vendor_store_name')) {
                                        $sn = wcfm_get_vendor_store_name($row->vendor_id);
                                        if ($sn) $v_name = $sn;
                                    } elseif ($u = get_userdata($row->vendor_id)) {
                                        $v_name = $u->display_name;
                                    }

                                    $row_profit = (float) $row->net_profit;
                                    $row_margin = (float) $row->profit_margin;
                                    $is_row_profit = $row_profit >= 0;

                                    $payout_badge = 'badge-pending';
                                    if ('disbursed' === $row->payout_status) {
                                        $payout_badge = 'badge-disbursed';
                                    } elseif ('processing' === $row->payout_status) {
                                        $payout_badge = 'badge-processing';
                                    }

                                    $total_pg_cost = (float) $row->gateway_fee + (float) $row->gateway_tax + (float) $row->other_service_cost;
                                    $shipping_variance = (float) $row->shipping_charge - (float) $row->shiprocket_shipping_cost;
                                    ?>
                                    <tr class="ledger-row" id="row-<?php echo esc_attr($row->id); ?>">
                                        <!-- 1. Order / Sub Order (with interactive audit caret toggle) -->
                                        <td>
                                            <div class="order-cell-wrap">
                                                <button type="button" class="btn-toggle-audit" data-row-id="<?php echo esc_attr($row->id); ?>" title="<?php esc_attr_e('View Financial Calculation Audit', 'thaaniyamhub-multi-vendor-orders'); ?>" aria-expanded="false">
                                                    <span class="dashicons dashicons-arrow-down-alt2 toggle-icon"></span>
                                                </button>
                                                <div class="order-id-stack">
                                                    <a href="<?php echo esc_url($order_url); ?>" class="order-sub-link" title="<?php esc_attr_e('View Sub-Order', 'thaaniyamhub-multi-vendor-orders'); ?>">
                                                        <strong>#<?php echo esc_html($row->sub_order_id); ?></strong>
                                                    </a>
                                                    <?php if ($row->parent_order_id !== $row->sub_order_id): ?>
                                                        <span class="parent-ref">
                                                            <?php esc_html_e('Parent:', 'thaaniyamhub-multi-vendor-orders'); ?>
                                                            <a href="<?php echo esc_url($parent_url); ?>" class="parent-link">#<?php echo esc_html($row->parent_order_id); ?></a>
                                                        </span>
                                                    <?php endif; ?>
                                                    <span class="badge-status-pill badge-status-<?php echo esc_attr($row->order_status); ?>">
                                                        <?php echo esc_html(ucfirst($row->order_status)); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- 2. Date & Customer -->
                                        <td>
                                            <div class="cust-info-stack">
                                                <span class="date-text"><?php echo esc_html(date_i18n('d M Y, H:i', strtotime($row->created_at))); ?></span>
                                                <span class="cust-name" title="<?php echo esc_attr($row->customer_name); ?>">
                                                    <?php echo esc_html(wp_trim_words($row->customer_name, 3, '...')); ?>
                                                </span>
                                                <?php if (!empty($row->customer_city)): ?>
                                                    <span class="cust-city"><?php echo esc_html($row->customer_city); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- 3. Vendor / Store -->
                                        <td class="col-grp-end">
                                            <div class="vendor-info-stack">
                                                <span class="vendor-name" title="<?php echo esc_attr($v_name); ?>">
                                                    <?php echo esc_html($v_name); ?>
                                                </span>
                                            </div>
                                        </td>

                                        <!-- 4. Product Price (Catalog Subtotal) -->
                                        <td class="col-numeric">
                                            <strong><?php echo wp_kses_post(wc_price((float)$row->item_subtotal > 0 ? (float)$row->item_subtotal : ((float)$row->gross_sales + (float)$row->discount_total))); ?></strong>
                                        </td>

                                        <!-- 5. Admin Discount (2 Lines: Amount + Coupon Tag) -->
                                        <td class="col-numeric">
                                            <div class="cell-stack-numeric">
                                                <?php 
                                                $row_discount = (float) $row->discount_total;
                                                if ( $row_discount <= 0 && (float) $row->item_subtotal > (float) $row->gross_sales ) {
                                                    $row_discount = round( (float) $row->item_subtotal - (float) $row->gross_sales, 2 );
                                                }
                                                if ( $row_discount > 0 ): ?>
                                                    <span class="amount-primary text-red"><strong>-<?php echo wp_kses_post(wc_price($row_discount)); ?></strong></span>
                                                    <span class="subtag-pill subtag-discount" title="<?php echo esc_attr($row->coupon_codes ?: 'Store Discount'); ?>">
                                                        🏷️ <?php echo esc_html(wp_trim_words($row->coupon_codes ?: 'Discount', 2, '..')); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="amount-primary text-muted">₹0.00</span>
                                                    <span class="subtag-pill subtag-none">—</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- 6. Customer Paid (Total Inflow) -->
                                        <td class="col-numeric col-inflow col-grp-end">
                                            <?php 
                                            $row_refund = (float) ($row->refunded_amount ?? 0);
                                            $row_inflow = (float) ($row->total_incoming > 0 ? $row->total_incoming : ($row->gross_sales + $row->shipping_charge + $row->tax_amount));
                                            $row_net_inflow = max(0.0, round($row_inflow - $row_refund, 2));
                                            ?>
                                            <strong><?php echo wp_kses_post(wc_price($row_inflow)); ?></strong>
                                            <?php if ($row_refund > 0): ?>
                                                <div style="margin-top:2px;">
                                                    <span class="subtag-pill" style="background:#fee2e2;color:#991b1b;font-weight:700;font-size:9.5px;padding:1px 5px;border-radius:3px;">
                                                        ↩️ -<?php echo wp_strip_all_tags(wc_price($row_refund)); ?>
                                                    </span>
                                                </div>
                                                <span class="rate-subtext" style="color:#059669;font-weight:700;display:block;margin-top:1px;">
                                                    Net: <?php echo wp_strip_all_tags(wc_price($row_net_inflow)); ?>
                                                </span>
                                            <?php elseif ((float) $row->shipping_charge > 0): ?>
                                                <span class="rate-subtext"><?php printf(esc_html__('incl. ₹%s ship', 'thaaniyamhub-multi-vendor-orders'), number_format($row->shipping_charge, 2)); ?></span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- 7. Platform Commission (Admin Revenue) -->
                                        <td class="col-numeric col-commission">
                                            <span><strong><?php echo wp_kses_post(wc_price($row->commission_deducted)); ?></strong></span>
                                            <?php if ((float) $row->commission_rate > 0): ?>
                                                <span class="rate-subtext">(<?php echo esc_html($row->commission_rate); ?>%)</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- 8. 18% GST on Commission -->
                                        <td class="col-numeric col-tax col-grp-end">
                                            <span><?php echo wp_kses_post(wc_price((float)$row->commission_tax > 0 ? (float)$row->commission_tax : round((float)$row->commission_deducted * 0.18, 2))); ?></span>
                                        </td>

                                        <!-- 9. Vendor Net Payout (2 Lines: Amount + Status Pill) -->
                                        <td class="col-numeric col-net-payout">
                                            <div class="cell-stack-numeric">
                                                <span class="amount-primary text-blue"><strong><?php echo wp_kses_post(wc_price($row->vendor_net_payout)); ?></strong></span>
                                                <span class="badge-pill <?php echo esc_attr($payout_badge); ?>">
                                                    <?php echo esc_html(strtoupper($row->payout_status ?: 'PENDING')); ?>
                                                </span>
                                            </div>
                                        </td>

                                        <!-- 10. Shiprocket Freight -->
                                        <td class="col-numeric col-logistics">
                                            <span class="sr-cost"><strong><?php echo wp_kses_post(wc_price((float)$row->shiprocket_shipping_cost > 0 ? (float)$row->shiprocket_shipping_cost : (float)$row->shipping_charge)); ?></strong></span>
                                            <?php if (!empty($row->shiprocket_awb)): ?>
                                                <span class="sr-awb-tag" title="<?php echo esc_attr($row->shiprocket_courier_name ?: 'Shiprocket AWB'); ?>">
                                                    📦 <?php echo esc_html($row->shiprocket_awb); ?>
                                                </span>
                                            <?php elseif (!empty($row->shiprocket_courier_name)): ?>
                                                <span class="sr-courier-tag"><?php echo esc_html($row->shiprocket_courier_name); ?></span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- 11. Cashfree & Service Fees -->
                                        <td class="col-numeric col-gateway col-grp-end">
                                            <span class="pg-cost"><?php echo wp_kses_post(wc_price($total_pg_cost > 0 ? $total_pg_cost : round(((float)$row->total_incoming * 0.02) + 2.95, 2))); ?></span>
                                            <?php if ((float) $row->gateway_tax > 0): ?>
                                                <span class="rate-subtext"><?php printf(esc_html__('GST ₹%s', 'thaaniyamhub-multi-vendor-orders'), number_format($row->gateway_tax, 2)); ?></span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- 12. Total Outflow -->
                                        <td class="col-numeric col-outflow">
                                            <span><?php echo wp_kses_post(wc_price((float)$row->total_outgoing > 0 ? (float)$row->total_outgoing : ((float)$row->vendor_net_payout + (float)$row->shiprocket_shipping_cost + (float)$row->commission_tax + $total_pg_cost))); ?></span>
                                        </td>

                                        <!-- 13. Net Admin Profit (2 Lines: Amount + Margin Pill) -->
                                        <td class="col-numeric col-profit <?php echo $is_row_profit ? 'profit-pos' : 'profit-neg'; ?>">
                                            <div class="cell-stack-numeric">
                                                <span class="amount-primary <?php echo $is_row_profit ? 'text-emerald' : 'text-red'; ?>">
                                                    <strong><?php echo wp_kses_post(wc_price($row_profit)); ?></strong>
                                                </span>
                                                <span class="margin-pill <?php echo $is_row_profit ? 'margin-pos' : 'margin-neg'; ?>">
                                                    <?php echo esc_html($row_margin); ?>% margin
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                    <!-- Expandable Audit Drawer Row (13 columns) -->
                                     <tr class="audit-drawer-row" id="audit-drawer-<?php echo esc_attr($row->id); ?>" style="display:none;">
                                         <td colspan="13">
                                             <div class="audit-drawer-content">
                                                 <div class="audit-grid">
                                                     <!-- Box 1: Platform Profit & Loss Breakdown (Commission-Driven) -->
                                                     <div class="audit-box profit-box <?php echo $is_row_profit ? 'profit-box-pos' : 'profit-box-neg'; ?>">
                                                         <div class="audit-box-title">💼 <?php esc_html_e('1. Admin Profit & Loss Breakdown', 'thaaniyamhub-multi-vendor-orders'); ?></div>
                                                         <table class="audit-sub-table">
                                                             <tr>
                                                                 <td><strong><?php esc_html_e('Gross Platform Commission:', 'thaaniyamhub-multi-vendor-orders'); ?></strong></td>
                                                                 <td class="text-right font-bold text-indigo">+<?php echo wp_kses_post(wc_price($row->commission_deducted)); ?></td>
                                                             </tr>
                                                             <?php if ($row_refund > 0): ?>
                                                                 <tr>
                                                                     <td><?php esc_html_e('Customer Refund Deducted:', 'thaaniyamhub-multi-vendor-orders'); ?></td>
                                                                     <td class="text-right text-red font-bold">-<?php echo wp_kses_post(wc_price($row_refund)); ?></td>
                                                                 </tr>
                                                             <?php endif; ?>
                                                             <tr>
                                                                 <td><?php esc_html_e('Admin-Funded Discount Loss:', 'thaaniyamhub-multi-vendor-orders'); ?></td>
                                                                 <td class="text-right text-red">-<?php echo wp_kses_post(wc_price($row->discount_total)); ?></td>
                                                             </tr>
                                                             <tr>
                                                                 <td><?php esc_html_e('18% GST Tax on Commission:', 'thaaniyamhub-multi-vendor-orders'); ?></td>
                                                                 <td class="text-right text-pink">-<?php echo wp_kses_post(wc_price((float)$row->commission_tax > 0 ? (float)$row->commission_tax : round((float)$row->commission_deducted * 0.18, 2))); ?></td>
                                                             </tr>
                                                             <tr>
                                                                 <td><?php esc_html_e('Logistics Margin Variance:', 'thaaniyamhub-multi-vendor-orders'); ?></td>
                                                                 <td class="text-right <?php echo $shipping_variance >= 0 ? 'text-emerald' : 'text-red'; ?>">
                                                                     <?php echo ($shipping_variance >= 0 ? '+' : '') . wp_kses_post(wc_price($shipping_variance)); ?>
                                                                 </td>
                                                             </tr>
                                                             <tr>
                                                                 <td><?php esc_html_e('Cashfree PG & Payout Fees:', 'thaaniyamhub-multi-vendor-orders'); ?></td>
                                                                 <td class="text-right text-purple">-<?php echo wp_kses_post(wc_price($total_pg_cost > 0 ? $total_pg_cost : round(((float)$row->total_incoming * 0.02) + 2.95, 2))); ?></td>
                                                             </tr>
                                                             <tr class="audit-total-row">
                                                                 <td><strong><?php esc_html_e('NET PLATFORM PROFIT:', 'thaaniyamhub-multi-vendor-orders'); ?></strong></td>
                                                                 <td class="text-right font-bold font-xl <?php echo $is_row_profit ? 'text-emerald' : 'text-red'; ?>">
                                                                     <?php echo wp_kses_post(wc_price($row_profit)); ?>
                                                                 </td>
                                                             </tr>
                                                         </table>
                                                         <div class="formula-margin">
                                                             <strong><?php esc_html_e('Net Profit Margin:', 'thaaniyamhub-multi-vendor-orders'); ?></strong> <?php echo esc_html($row_margin); ?>%
                                                         </div>
                                                     </div>

                                                     <!-- Box 2: Total Customer Incoming Inflow -->
                                                     <div class="audit-box inflow-box">
                                                         <div class="audit-box-title">📥 <?php esc_html_e('2. Total Customer Inflow', 'thaaniyamhub-multi-vendor-orders'); ?></div>
                                                         <table class="audit-sub-table">
                                                             <tr>
                                                                 <td><?php esc_html_e('Product Catalog Price:', 'thaaniyamhub-multi-vendor-orders'); ?></td>
                                                                 <td class="text-right"><?php echo wp_kses_post(wc_price((float)$row->item_subtotal > 0 ? (float)$row->item_subtotal : (float)$row->gross_sales)); ?></td>
                                                             </tr>
                                                             <?php if ((float)$row->discount_total > 0): ?>
                                                                 <tr>
                                                                     <td><?php esc_html_e('Less: Admin Discount:', 'thaaniyamhub-multi-vendor-orders'); ?></td>
                                                                     <td class="text-right text-red">-<?php echo wp_kses_post(wc_price($row->discount_total)); ?></td>
                                                                 </tr>
                                                             <?php endif; ?>
                                                             <tr>
                                                                 <td><?php esc_html_e('Post-Discount Order Price:', 'thaaniyamhub-multi-vendor-orders'); ?></td>
                                                                 <td class="text-right"><?php echo wp_kses_post(wc_price($row->gross_sales)); ?></td>
                                                             </tr>
                                                             <tr>
                                                                 <td><?php esc_html_e('Customer Shipping Paid:', 'thaaniyamhub-multi-vendor-orders'); ?></td>
                                                                 <td class="text-right"><?php echo wp_kses_post(wc_price($row->shipping_charge)); ?></td>
                                                             </tr>
                                                             <tr>
                                                                 <td><?php esc_html_e('Product Tax Paid:', 'thaaniyamhub-multi-vendor-orders'); ?></td>
                                                                 <td class="text-right"><?php echo wp_kses_post(wc_price($row->tax_amount)); ?></td>
                                                             </tr>
                                                             <tr>
                                                                 <td><strong><?php esc_html_e('Gross Cash Inflow:', 'thaaniyamhub-multi-vendor-orders'); ?></strong></td>
                                                                 <td class="text-right font-bold"><?php echo wp_kses_post(wc_price($row_inflow)); ?></td>
                                                             </tr>
                                                             <?php if ($row_refund > 0): ?>
                                                                 <tr>
                                                                     <td style="color:#dc2626;"><?php esc_html_e('Less: Customer Refund:', 'thaaniyamhub-multi-vendor-orders'); ?></td>
                                                                     <td class="text-right text-red font-bold">-<?php echo wp_kses_post(wc_price($row_refund)); ?></td>
                                                                 </tr>
                                                             <?php endif; ?>
                                                             <tr class="audit-total-row">
                                                                 <td><strong><?php esc_html_e('NET RETAINED INFLOW:', 'thaaniyamhub-multi-vendor-orders'); ?></strong></td>
                                                                 <td class="text-right font-bold text-emerald"><?php echo wp_kses_post(wc_price($row_net_inflow)); ?></td>
                                                             </tr>
                                                         </table>
                                                        <?php if (!empty($row->coupon_codes)): ?>
                                                            <div style="font-size:10px;color:#92400e;margin-top:6px;">
                                                                🏷️ <?php esc_html_e('Coupon:', 'thaaniyamhub-multi-vendor-orders'); ?> <strong><?php echo esc_html($row->coupon_codes); ?></strong>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>

                                                    <!-- Box 3: Total Marketplace Outflow Deductions -->
                                                    <div class="audit-box outflow-box">
                                                        <div class="audit-box-title">📤 <?php esc_html_e('3. Direct Outflow Costs', 'thaaniyamhub-multi-vendor-orders'); ?></div>
                                                        <table class="audit-sub-table">
                                                            <tr>
                                                                <td><?php esc_html_e('Vendor Net Payout:', 'thaaniyamhub-multi-vendor-orders'); ?></td>
                                                                <td class="text-right font-bold text-blue"><?php echo wp_kses_post(wc_price($row->vendor_net_payout)); ?></td>
                                                            </tr>
                                                            <tr>
                                                                <td><?php esc_html_e('Shiprocket Actual Freight:', 'thaaniyamhub-multi-vendor-orders'); ?></td>
                                                                <td class="text-right text-orange"><?php echo wp_kses_post(wc_price((float)$row->shiprocket_shipping_cost > 0 ? (float)$row->shiprocket_shipping_cost : (float)$row->shipping_charge)); ?></td>
                                                            </tr>
                                                            <tr>
                                                                <td><?php esc_html_e('18% GST on Platform Comm.:', 'thaaniyamhub-multi-vendor-orders'); ?></td>
                                                                <td class="text-right text-pink"><?php echo wp_kses_post(wc_price((float)$row->commission_tax > 0 ? (float)$row->commission_tax : round((float)$row->commission_deducted * 0.18, 2))); ?></td>
                                                            </tr>
                                                            <tr>
                                                                <td><?php esc_html_e('Cashfree PG Fee + 18% GST:', 'thaaniyamhub-multi-vendor-orders'); ?></td>
                                                                <td class="text-right text-purple"><?php echo wp_kses_post(wc_price((float)$row->gateway_fee + (float)$row->gateway_tax)); ?></td>
                                                            </tr>
                                                            <tr>
                                                                <td><?php esc_html_e('Cashfree Payout Transfer Fee + GST:', 'thaaniyamhub-multi-vendor-orders'); ?></td>
                                                                <td class="text-right text-purple"><?php echo wp_kses_post(wc_price($row->other_service_cost)); ?></td>
                                                            </tr>
                                                            <tr class="audit-total-row">
                                                                <td><strong><?php esc_html_e('TOTAL CASH OUTFLOW:', 'thaaniyamhub-multi-vendor-orders'); ?></strong></td>
                                                                <td class="text-right font-bold text-red"><?php echo wp_kses_post(wc_price((float)$row->total_outgoing > 0 ? (float)$row->total_outgoing : ((float)$row->vendor_net_payout + (float)$row->shiprocket_shipping_cost + (float)$row->commission_tax + $total_pg_cost))); ?></td>
                                                            </tr>
                                                        </table>

                                                        <div class="audit-quick-links">
                                                            <a href="<?php echo esc_url($order_url); ?>" class="button button-small" target="_blank">
                                                                <span class="dashicons dashicons-external"></span> <?php esc_html_e('Edit WooCommerce Sub-Order', 'thaaniyamhub-multi-vendor-orders'); ?>
                                                            </a>
                                                            <?php if (!empty($row->shiprocket_awb)): ?>
                                                                <a href="https://shiprocket.co/tracking/<?php echo esc_attr($row->shiprocket_awb); ?>" class="button button-small" target="_blank">
                                                                    <span class="dashicons dashicons-location"></span> <?php esc_html_e('Track Shiprocket AWB', 'thaaniyamhub-multi-vendor-orders'); ?>
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-totals-row">
                                    <td colspan="3" class="col-grp-end"><strong><?php esc_html_e('PAGE TOTALS:', 'thaaniyamhub-multi-vendor-orders'); ?></strong></td>
                                    <!-- 1. Product Price -->
                                    <td class="col-numeric font-bold"><?php echo wp_kses_post(wc_price(array_sum(array_map(function($r){ return (float)($r->item_subtotal > 0 ? $r->item_subtotal : $r->gross_sales); }, (array)$rows)))); ?></td>
                                    <!-- 2. Admin Discount (2 lines) -->
                                    <td class="col-numeric">
                                        <div class="cell-stack-numeric">
                                            <span class="amount-primary font-bold text-red"><?php echo wp_kses_post(wc_price(array_sum(array_column((array) $rows, 'discount_total')))); ?></span>
                                            <span class="subtag-pill subtag-none"><?php esc_html_e('Total Discounts', 'thaaniyamhub-multi-vendor-orders'); ?></span>
                                        </div>
                                    </td>
                                    <!-- 3. Customer Paid (Gross & Refunds) -->
                                    <td class="col-numeric font-bold text-emerald col-grp-end">
                                        <?php 
                                        $page_gross_inflow = array_sum(array_map(function($r){ return (float)($r->total_incoming > 0 ? $r->total_incoming : ($r->gross_sales + $r->shipping_charge + $r->tax_amount)); }, (array)$rows));
                                        $page_refunds = array_sum(array_column((array)$rows, 'refunded_amount'));
                                        $page_net_inflow = max(0.0, $page_gross_inflow - $page_refunds);
                                        ?>
                                        <?php echo wp_kses_post(wc_price($page_gross_inflow)); ?>
                                        <?php if ($page_refunds > 0): ?>
                                            <div style="margin-top:2px;">
                                                <span class="subtag-pill" style="background:#fee2e2;color:#991b1b;font-weight:700;font-size:9.5px;padding:1px 5px;border-radius:3px;">
                                                    ↩️ -<?php echo wp_strip_all_tags(wc_price($page_refunds)); ?>
                                                </span>
                                            </div>
                                            <span class="rate-subtext" style="color:#059669;font-weight:700;display:block;margin-top:1px;">
                                                Net: <?php echo wp_strip_all_tags(wc_price($page_net_inflow)); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <!-- 4. Platform Comm -->
                                    <td class="col-numeric font-bold text-indigo"><?php echo wp_kses_post(wc_price(array_sum(array_column((array) $rows, 'commission_deducted')))); ?></td>
                                    <!-- 5. 18% GST Tax -->
                                    <td class="col-numeric font-bold text-pink col-grp-end"><?php echo wp_kses_post(wc_price(array_sum(array_map(function($r){ return (float)($r->commission_tax > 0 ? $r->commission_tax : round($r->commission_deducted * 0.18, 2)); }, (array)$rows)))); ?></td>
                                    <!-- 6. Vendor Payout (2 lines) -->
                                    <td class="col-numeric">
                                        <div class="cell-stack-numeric">
                                            <span class="amount-primary font-bold text-blue"><?php echo wp_kses_post(wc_price(array_sum(array_column((array) $rows, 'vendor_net_payout')))); ?></span>
                                            <span class="subtag-pill subtag-none"><?php esc_html_e('Net Payouts', 'thaaniyamhub-multi-vendor-orders'); ?></span>
                                        </div>
                                    </td>
                                    <!-- 7. Shiprocket Freight -->
                                    <td class="col-numeric font-bold text-orange"><?php echo wp_kses_post(wc_price(array_sum(array_map(function($r){ return (float)($r->shiprocket_shipping_cost > 0 ? $r->shiprocket_shipping_cost : $r->shipping_charge); }, (array)$rows)))); ?></td>
                                    <!-- 8. Cashfree Fees -->
                                    <td class="col-numeric font-bold text-purple col-grp-end"><?php echo wp_kses_post(wc_price(array_sum(array_map(function($r){ return (float)$r->gateway_fee + (float)$r->gateway_tax + (float)$r->other_service_cost; }, (array)$rows)))); ?></td>
                                    <!-- 9. Total Outflow -->
                                    <td class="col-numeric font-bold text-red"><?php echo wp_kses_post(wc_price(array_sum(array_map(function($r){ return (float)($r->total_outgoing > 0 ? $r->total_outgoing : ($r->vendor_net_payout + $r->shiprocket_shipping_cost + $r->commission_tax + $r->gateway_fee + $r->gateway_tax + $r->other_service_cost)); }, (array)$rows)))); ?></td>
                                    <!-- 10. Net Admin Profit (2 lines) -->
                                    <td class="col-numeric">
                                        <div class="cell-stack-numeric">
                                            <span class="amount-primary font-bold text-emerald"><?php echo wp_kses_post(wc_price(array_sum(array_column((array) $rows, 'net_profit')))); ?></span>
                                            <span class="margin-pill <?php echo $summary['total_net_profit'] >= 0 ? 'margin-pos' : 'margin-neg'; ?>">
                                                <?php echo esc_html($summary['overall_margin']); ?>% margin
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Enhanced Pagination Bar -->
                    <div class="thaaniyamhub-pagination">
                        <div class="pagination-summary">
                            <?php
                            if ($total_count > 0) {
                                $start_idx = (($paged - 1) * $limit) + 1;
                                $end_idx = min($total_count, $paged * $limit);
                                printf(esc_html__('Showing %d to %d of %d entries', 'thaaniyamhub-multi-vendor-orders'), $start_idx, $end_idx, $total_count);
                            }
                            ?>
                        </div>
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination-page-links">
                                <?php
                                $page_links = paginate_links([
                                    'base'      => add_query_arg([
                                        'paged'              => '%#%',
                                        'thaaniyamhub_limit' => $limit,
                                    ]),
                                    'format'    => '',
                                    'prev_text' => __('&laquo; Prev', 'thaaniyamhub-multi-vendor-orders'),
                                    'next_text' => __('Next &raquo;', 'thaaniyamhub-multi-vendor-orders'),
                                    'total'     => $total_pages,
                                    'current'   => $paged,
                                    'type'      => 'plain',
                                ]);
                                echo wp_kses_post($page_links);
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php else: ?>
                    <div class="thaaniyamhub-no-records">
                        <span class="no-records-icon">📭</span>
                        <h3><?php esc_html_e('No financial transactions found', 'thaaniyamhub-multi-vendor-orders'); ?></h3>
                        <p><?php esc_html_e('Try clearing filters or adjusting the date range.', 'thaaniyamhub-multi-vendor-orders'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    // =========================================================================
    // 3. PRINT-READY HTML STATEMENT STREAM
    // =========================================================================

    /**
     * Stream a printable, beautifully formatted financial statement.
     */
    public static function stream_pdf()
    {
        check_ajax_referer('thaaniyamhub_export_pdf', '_nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die('Insufficient permissions', 403);
        }

        $vendor_id     = (int) ($_GET['thaaniyamhub_vendor_id'] ?? 0);
        $search        = sanitize_text_field($_GET['thaaniyamhub_search'] ?? '');
        $from          = sanitize_text_field($_GET['thaaniyamhub_from'] ?? '');
        $to            = sanitize_text_field($_GET['thaaniyamhub_to'] ?? '');
        $order_status  = sanitize_text_field($_GET['thaaniyamhub_order_status'] ?? '');
        $payout_status = sanitize_text_field($_GET['thaaniyamhub_payout_status'] ?? '');
        $profitability = sanitize_text_field($_GET['thaaniyamhub_profitability'] ?? 'all');
        $orderby       = sanitize_text_field($_GET['thaaniyamhub_orderby'] ?? $_GET['orderby'] ?? 'sub_order_id');
        $order         = sanitize_text_field($_GET['thaaniyamhub_order'] ?? $_GET['order'] ?? 'DESC');

        $query_args = [
            'vendor_id'     => $vendor_id,
            'search'        => $search,
            'from'          => $from,
            'to'            => $to,
            'order_status'  => $order_status,
            'payout_status' => $payout_status,
            'profitability' => $profitability,
            'orderby'       => $orderby,
            'order'         => $order,
            'limit'         => 1000,
            'paged'         => 1,
        ];

        $res     = ThaaniyamHub_Ledger::query_ledger($query_args);
        $rows    = $res['rows'];
        $summary = ThaaniyamHub_Ledger::get_financial_summary($query_args);

        $vendor_name = 'All Marketplace Vendors';
        if ($vendor_id) {
            $vendor = get_userdata($vendor_id);
            $vendor_name = $vendor ? $vendor->display_name : 'Vendor #' . $vendor_id;
            if (function_exists('wcfm_get_vendor_store_name')) {
                $sn = wcfm_get_vendor_store_name($vendor_id);
                if ($sn) $vendor_name = $sn;
            }
        }

        $period_label = $from && $to
            ? date_i18n('d M Y', strtotime($from)) . ' — ' . date_i18n('d M Y', strtotime($to))
            : __('All Time', 'thaaniyamhub-multi-vendor-orders');

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Financial Ledger Statement — <?php echo esc_html($vendor_name); ?></title>
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { font-family: 'Helvetica Neue', Arial, sans-serif; font-size: 11px; color: #1e293b; background: #fff; padding: 30px; }
                .report-header { border-bottom: 3px solid #4f46e5; padding-bottom: 16px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-end; }
                .report-header h1 { font-size: 20px; font-weight: 800; color: #4f46e5; }
                .report-header .meta { font-size: 11px; color: #64748b; margin-top: 4px; }
                .summary-matrix { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
                .summary-matrix th { background: #4f46e5; color: #fff; padding: 8px 12px; text-align: left; font-size: 10px; text-transform: uppercase; }
                .summary-matrix td { padding: 8px 12px; border-bottom: 1px solid #e2e8f0; }
                .summary-matrix .amount { text-align: right; font-weight: 600; }
                .summary-matrix .amount.net { color: #059669; font-size: 14px; font-weight: 800; }
                .tx-table { width: 100%; border-collapse: collapse; font-size: 10px; margin-top: 12px; }
                .tx-table th { background: #f8fafc; padding: 6px 8px; text-align: left; font-size: 9px; text-transform: uppercase; color: #475569; border-bottom: 2px solid #cbd5e1; }
                .tx-table td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; }
                .tx-table tr:nth-child(even) td { background: #fafafa; }
                .col-r { text-align: right; }
                .section-title { font-size: 12px; font-weight: 700; color: #334155; text-transform: uppercase; margin: 20px 0 8px; }
                .print-note { font-size: 10px; color: #94a3b8; margin-top: 30px; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 10px; }
                @media print {
                    body { padding: 15px; }
                    .no-print { display: none !important; }
                    @page { margin: 1cm; size: landscape; }
                }
            </style>
        </head>
        <body>
            <div class="no-print" style="margin-bottom:16px;">
                <button onclick="window.print()" style="background:#4f46e5;color:#fff;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;">
                    🖨️ Print Statement / Save PDF
                </button>
                <button onclick="window.close()" style="margin-left:8px;padding:8px 16px;border-radius:6px;cursor:pointer;font-size:13px;">Close</button>
            </div>

            <div class="report-header">
                <div>
                    <h1>Thaaniyam Hub Marketplace — Financial Ledger Statement</h1>
                    <div class="meta">
                        <strong>Scope:</strong> <?php echo esc_html($vendor_name); ?> | <strong>Period:</strong> <?php echo esc_html($period_label); ?> | <strong>Generated:</strong> <?php echo esc_html(date_i18n('d M Y H:i')); ?>
                    </div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:16px;font-weight:800;color:#059669;"><?php echo wp_strip_all_tags(wc_price($summary['total_net_profit'])); ?></div>
                    <div style="font-size:10px;color:#64748b;"><?php echo esc_html($summary['overall_margin']); ?>% Net Platform Margin</div>
                </div>
            </div>

            <div class="section-title">Executive Financial Summary & Platform P&L</div>
            <table class="summary-matrix">
                <thead>
                    <tr>
                        <th>Financial Category</th>
                        <th style="text-align:right;">Amount (INR)</th>
                        <th style="text-align:right;">Accounting Impact</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Gross Platform Commission Earned</strong></td>
                        <td class="amount" style="color:#4f46e5;font-weight:700;">+ <?php echo wp_strip_all_tags(wc_price($summary['total_commission'])); ?></td>
                        <td class="amount">Core Marketplace Revenue (Product Price - Vendor Payout)</td>
                    </tr>
                    <tr>
                        <td>Admin-Funded Discounts & Coupons (Marketing Loss)</td>
                        <td class="amount" style="color:#dc2626;">- <?php echo wp_strip_all_tags(wc_price($summary['total_discounts'])); ?></td>
                        <td class="amount">Direct Promotional Loss Borne by Platform</td>
                    </tr>
                    <tr>
                        <td>Tax Liability on Platform Commission (18% GST)</td>
                        <td class="amount" style="color:#dc2626;">- <?php echo wp_strip_all_tags(wc_price($summary['total_commission_tax'])); ?></td>
                        <td class="amount">18% GST Payable to Government</td>
                    </tr>
                    <tr>
                        <td>Shipping Freight Difference (Customer Paid vs Actual Shiprocket)</td>
                        <?php $ship_diff = (float)$summary['total_customer_shipping'] - (float)$summary['total_shiprocket_cost']; ?>
                        <td class="amount" style="color:<?php echo $ship_diff >= 0 ? '#059669' : '#dc2626'; ?>;">
                            <?php echo $ship_diff >= 0 ? '+' : ''; ?><?php echo wp_strip_all_tags(wc_price($ship_diff)); ?>
                        </td>
                        <td class="amount">Customer Shipping (₹<?php echo number_format($summary['total_customer_shipping'], 2); ?>) vs Courier (₹<?php echo number_format($summary['total_shiprocket_cost'], 2); ?>)</td>
                    </tr>
                    <tr>
                        <td>Cashfree Payment Gateway & Payout Fees (incl. 18% GST)</td>
                        <td class="amount" style="color:#dc2626;">- <?php echo wp_strip_all_tags(wc_price($summary['total_gateway_fee'] + $summary['total_gateway_tax'] + $summary['total_other_costs'])); ?></td>
                        <td class="amount">Banking PG Charges & Bank Payout Transfer Fees</td>
                    </tr>
                    <tr style="background:#faf5ff;font-weight:800;border-top:2px solid #4f46e5;">
                        <td><strong>NET PLATFORM PROFIT (BOTTOM LINE)</strong></td>
                        <td class="amount net"><?php echo wp_strip_all_tags(wc_price($summary['total_net_profit'])); ?></td>
                        <td class="amount net"><?php echo esc_html($summary['overall_margin']); ?>% Net Margin</td>
                    </tr>
                </tbody>
            </table>

            <?php if (!empty($rows)): ?>
                <div class="section-title">Order Ledger Breakdown (<?php echo count($rows); ?> records)</div>
                <table class="tx-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Date</th>
                            <th>Vendor</th>
                            <th class="col-r">Product Price</th>
                            <th class="col-r">Admin Discount</th>
                            <th class="col-r">Customer Paid</th>
                            <th class="col-r">Customer Ship</th>
                            <th class="col-r">Total Inflow</th>
                            <th class="col-r">Commission</th>
                            <th class="col-r">18% GST</th>
                            <th class="col-r">Vendor Payout</th>
                            <th class="col-r">Shiprocket Freight</th>
                            <th class="col-r">PG & Fees</th>
                            <th class="col-r">Total Outflow</th>
                            <th class="col-r">Net Profit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td>#<?php echo esc_html($r->sub_order_id); ?></td>
                                <td><?php echo esc_html(date_i18n('d M Y', strtotime($r->created_at))); ?></td>
                                <td><?php echo esc_html('Store #' . $r->vendor_id); ?></td>
                                <td class="col-r"><?php echo wp_strip_all_tags(wc_price((float)$r->item_subtotal > 0 ? $r->item_subtotal : ((float)$r->gross_sales + (float)$r->discount_total))); ?></td>
                                <td class="col-r" style="color:<?php echo (float)$r->discount_total > 0 ? '#dc2626' : '#64748b'; ?>;">
                                    <?php echo (float)$r->discount_total > 0 ? '-' . wp_strip_all_tags(wc_price($r->discount_total)) : '₹0.00'; ?>
                                    <?php if (!empty($r->coupon_codes)): ?>
                                        <br><small style="color:#b91c1c;">🏷️ <?php echo esc_html($r->coupon_codes); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="col-r font-bold"><?php echo wp_strip_all_tags(wc_price($r->gross_sales)); ?></td>
                                <td class="col-r"><?php echo wp_strip_all_tags(wc_price($r->shipping_charge)); ?></td>
                                <td class="col-r font-bold" style="color:#059669;"><?php echo wp_strip_all_tags(wc_price($r->total_incoming)); ?></td>
                                <td class="col-r"><?php echo wp_strip_all_tags(wc_price($r->commission_deducted)); ?></td>
                                <td class="col-r"><?php echo wp_strip_all_tags(wc_price($r->commission_tax)); ?></td>
                                <td class="col-r"><?php echo wp_strip_all_tags(wc_price($r->vendor_net_payout)); ?></td>
                                <td class="col-r"><?php echo wp_strip_all_tags(wc_price($r->shiprocket_shipping_cost)); ?></td>
                                <td class="col-r"><?php echo wp_strip_all_tags(wc_price((float)$r->gateway_fee + (float)$r->gateway_tax + (float)$r->other_service_cost)); ?></td>
                                <td class="col-r"><?php echo wp_strip_all_tags(wc_price($r->total_outgoing)); ?></td>
                                <td class="col-r font-bold" style="color:<?php echo (float)$r->net_profit >= 0 ? '#059669' : '#dc2626'; ?>;">
                                    <?php echo wp_strip_all_tags(wc_price($r->net_profit)); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <div class="print-note">
                Auto-generated from the Thaaniyam Hub Marketplace Financial Ledger & Accounting Engine.
                &copy; <?php echo date('Y'); ?> Thaaniyam Hub. All rights reserved.
            </div>
        </body>
        </html>
        <?php
        $html = ob_get_clean();
        nocache_headers();
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: inline; filename="ThaaniyamHub-Financial-Statement-' . date('Y-m-d') . '.html"');
        echo $html;
        exit;
    }

    // =========================================================================
    // 4. ASSETS & STYLING
    // =========================================================================

    public static function enqueue_report_assets(string $hook)
    {
        if ('woocommerce_page_thaaniyamhub-vendor-reports' !== $hook) {
            return;
        }

        wp_enqueue_style('google-font-inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap', [], null);
        wp_register_style('thaaniyamhub-vendor-reports', false);
        wp_enqueue_style('thaaniyamhub-vendor-reports');

        $css = '
        /* iOS Glassmorphism Theme - Thaaniyam Hub Financial Ledger */
        .thaaniyamhub-ledger-wrap {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "SF Pro Display", "Inter", "Segoe UI", Roboto, sans-serif;
            color: #1d1d1f;
            padding-right: 20px;
            margin-top: 14px;
            position: relative;
            -webkit-font-smoothing: antialiased;
        }
        .thaaniyamhub-ledger-wrap::before {
            content: "";
            position: absolute;
            top: -20px;
            left: -20px;
            right: 0;
            height: 480px;
            background: radial-gradient(circle at 8% 12%, rgba(99, 102, 241, 0.06) 0%, transparent 45%),
                        radial-gradient(circle at 92% 8%, rgba(16, 185, 129, 0.06) 0%, transparent 45%),
                        radial-gradient(circle at 50% 40%, rgba(14, 165, 233, 0.04) 0%, transparent 55%);
            pointer-events: none;
            z-index: 0;
        }
        .thaaniyamhub-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 12px;
            position: relative;
            z-index: 1;
        }
        .thaaniyamhub-page-title {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 4px 0;
            letter-spacing: -0.025em;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .thaaniyamhub-subtitle {
            font-size: 12px;
            color: #64748b;
            margin: 0;
            font-weight: 400;
            letter-spacing: -0.01em;
        }
        .thaaniyamhub-header-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .thaaniyamhub-header-actions .button,
        .thaaniyamhub-ledger-wrap .button-secondary {
            height: 32px !important;
            line-height: 30px !important;
            border-radius: 8px !important;
            font-size: 12px !important;
            font-weight: 600 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            padding: 0 12px !important;
            vertical-align: middle !important;
            box-sizing: border-box !important;
            background: rgba(255, 255, 255, 0.75) !important;
            backdrop-filter: blur(16px) saturate(180%) !important;
            -webkit-backdrop-filter: blur(16px) saturate(180%) !important;
            border: 1px solid rgba(203, 213, 225, 0.75) !important;
            color: #1e293b !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04), inset 0 1px 0 rgba(255, 255, 255, 0.9) !important;
            transition: all 0.15s cubic-bezier(0.16, 1, 0.3, 1) !important;
        }
        .thaaniyamhub-header-actions .button:hover,
        .thaaniyamhub-ledger-wrap .button-secondary:hover {
            background: rgba(255, 255, 255, 0.95) !important;
            border-color: #cbd5e1 !important;
            transform: translateY(-0.5px);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.07), inset 0 1px 0 rgba(255, 255, 255, 1) !important;
            color: #0f172a !important;
        }
        .thaaniyamhub-header-actions .button:active,
        .thaaniyamhub-ledger-wrap .button-secondary:active,
        .filter-actions-wrap .button-primary:active {
            transform: scale(0.97) !important;
        }
        .thaaniyamhub-filter-card {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.85);
            border-radius: 12px;
            padding: 11px 15px;
            margin-bottom: 14px;
            box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.04), 0 1px 2px 0 rgba(15, 23, 42, 0.02), inset 0 1px 0 rgba(255, 255, 255, 0.95);
            position: relative;
            z-index: 50;
        }
        .thaaniyamhub-filter-card.has-open-select {
            z-index: 100 !important;
        }
        /* Aligned 4-Column Filter Grid */
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px 12px;
            align-items: end;
            position: relative;
        }
        .filter-col {
            display: flex;
            flex-direction: column;
            width: 100%;
            position: relative;
        }
        .filter-col.has-open-select {
            z-index: 100 !important;
        }
        .filter-col label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 4px;
            line-height: 1.1;
            letter-spacing: -0.01em;
        }
        .filter-actions-label {
            visibility: hidden;
        }
        .filter-col select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            width: 100%;
            height: 32px;
            border-radius: 8px;
            border: 1px solid rgba(203, 213, 225, 0.7);
            padding: 0 28px 0 10px;
            font-size: 12px;
            background-color: rgba(248, 250, 252, 0.75);
            background-image: url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'12\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%2364748b\' stroke-width=\'2.5\' stroke-linecap=\'round\' stroke-linejoin=\'round\'%3E%3Cpath d=\'m7 15 5 5 5-5\'/%3E%3Cpath d=\'m7 9 5-5 5 5\'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 9px center;
            background-size: 12px 12px;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: #1e293b;
            box-sizing: border-box;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.03);
        }
        .filter-col select:hover {
            background-color: rgba(255, 255, 255, 0.9);
            border-color: rgba(148, 163, 184, 0.8);
        }
        .filter-col select:focus {
            border-color: #0071e3;
            box-shadow: 0 0 0 3px rgba(0, 113, 227, 0.15), inset 0 1px 1px rgba(0, 0, 0, 0.02);
            background-color: rgba(255, 255, 255, 0.98);
            outline: none;
        }
        .filter-col input[type="text"],
        .filter-col input[type="date"] {
            width: 100%;
            height: 32px;
            border-radius: 8px;
            border: 1px solid rgba(203, 213, 225, 0.7);
            padding: 0 9px;
            font-size: 12px;
            background: rgba(248, 250, 252, 0.75);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: #1e293b;
            box-sizing: border-box;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.03);
        }
        .filter-col input[type="text"]:focus,
        .filter-col input[type="date"]:focus {
            border-color: #0071e3;
            box-shadow: 0 0 0 3px rgba(0, 113, 227, 0.15), inset 0 1px 1px rgba(0, 0, 0, 0.02);
            background: rgba(255, 255, 255, 0.95);
            outline: none;
        }

        /* Custom iOS Floating Dropdown */
        .ios-select-wrapper {
            position: relative;
            width: 100%;
        }
        .ios-select-wrapper.is-open {
            z-index: 100 !important;
        }
        .ios-select-trigger {
            width: 100%;
            height: 32px;
            border-radius: 8px;
            border: 1px solid rgba(203, 213, 225, 0.7);
            padding: 0 28px 0 10px;
            font-size: 12px;
            font-weight: 500;
            background-color: rgba(248, 250, 252, 0.75);
            background-image: url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'12\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%2364748b\' stroke-width=\'2.5\' stroke-linecap=\'round\' stroke-linejoin=\'round\'%3E%3Cpath d=\'m7 15 5 5 5-5\'/%3E%3Cpath d=\'m7 9 5-5 5 5\'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 9px center;
            background-size: 12px 12px;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: #1e293b;
            box-sizing: border-box;
            cursor: pointer;
            display: flex;
            align-items: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            user-select: none;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.03);
        }
        .ios-select-trigger:hover {
            background-color: rgba(255, 255, 255, 0.9);
            border-color: rgba(148, 163, 184, 0.8);
        }
        .ios-select-wrapper.is-open .ios-select-trigger {
            border-color: #0071e3;
            box-shadow: 0 0 0 3px rgba(0, 113, 227, 0.15), inset 0 1px 1px rgba(0, 0, 0, 0.02);
            background-color: rgba(255, 255, 255, 0.98);
        }
        .ios-select-dropdown {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            width: 100%;
            min-width: 220px;
            max-height: 220px;
            overflow-y: auto;
            background: #ffffff !important;
            border: 1px solid rgba(203, 213, 225, 0.9);
            border-radius: 10px;
            padding: 4px;
            box-shadow: 0 16px 36px -4px rgba(15, 23, 42, 0.2), 0 6px 14px -2px rgba(15, 23, 42, 0.1), inset 0 1px 0 rgba(255, 255, 255, 1);
            z-index: 99999 !important;
            display: none;
            box-sizing: border-box;
            animation: iosDropdownIn 0.15s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes iosDropdownIn {
            from { opacity: 0; transform: translateY(-4px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .ios-select-dropdown::-webkit-scrollbar {
            width: 5px;
        }
        .ios-select-dropdown::-webkit-scrollbar-track {
            background: transparent;
        }
        .ios-select-dropdown::-webkit-scrollbar-thumb {
            background: rgba(203, 213, 225, 0.8);
            border-radius: 4px;
        }
        .ios-select-item {
            padding: 7px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            color: #1e293b;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            transition: background 0.12s ease, color 0.12s ease;
            user-select: none;
        }
        .ios-select-item:hover {
            background: rgba(0, 113, 227, 0.08);
            color: #0071e3;
        }
        .ios-select-item.is-selected {
            background: rgba(0, 113, 227, 0.12);
            color: #0071e3;
            font-weight: 600;
        }
        .ios-select-item-check {
            font-size: 11px;
            font-weight: 700;
            color: #0071e3;
            flex-shrink: 0;
        }
        .filter-actions-wrap {
            display: flex;
            gap: 6px;
            width: 100%;
        }
        .filter-actions-wrap .button,
        .filter-actions-wrap .button-primary,
        .filter-actions-wrap .button-secondary {
            flex: 1;
            height: 32px !important;
            line-height: 30px !important;
            padding: 0 10px !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 5px !important;
            vertical-align: middle !important;
            box-sizing: border-box !important;
            text-align: center;
        }
        .filter-actions-wrap .button-primary {
            background: linear-gradient(180deg, #0077ED 0%, #0062CC 100%) !important;
            border: 1px solid rgba(0, 85, 179, 0.4) !important;
            color: #ffffff !important;
            box-shadow: 0 2px 6px rgba(0, 113, 227, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.25) !important;
        }
        .filter-actions-wrap .button-primary:hover {
            background: linear-gradient(180deg, #006ee0 0%, #0056b3 100%) !important;
            border-color: #0056b3 !important;
            box-shadow: 0 3px 8px rgba(0, 113, 227, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.3) !important;
            transform: translateY(-0.5px);
        }
        .custom-date-row {
            display: flex;
            gap: 12px;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px dashed rgba(226, 232, 240, 0.9);
        }
        .custom-date-field {
            flex: 1;
            max-width: 250px;
        }
        .custom-date-field label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 3px;
        }
        .custom-date-field input[type="date"] {
            width: 100%;
            height: 32px;
            border-radius: 8px;
            border: 1px solid rgba(203, 213, 225, 0.7);
            padding: 0 8px;
            font-size: 12px;
            background: rgba(248, 250, 252, 0.75);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-sizing: border-box;
        }
        .thaaniyamhub-ledger-wrap .button .dashicons,
        .thaaniyamhub-ledger-wrap .button-primary .dashicons,
        .thaaniyamhub-ledger-wrap .button-secondary .dashicons {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 14px !important;
            height: 14px !important;
            font-size: 14px !important;
            line-height: 1 !important;
            vertical-align: middle !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        @keyframes thaaniyamSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .dashicons.spin {
            animation: thaaniyamSpin 1s infinite linear;
            display: inline-block !important;
        }
        .thaaniyamhub-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 14px;
            position: relative;
            z-index: 1;
        }
        .thaaniyamhub-kpi-card {
            background: rgba(255, 255, 255, 0.72);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.85);
            border-radius: 12px;
            padding: 11px 13px;
            box-shadow: 0 3px 14px -2px rgba(15, 23, 42, 0.04), 0 1px 2px 0 rgba(15, 23, 42, 0.02), inset 0 1px 0 rgba(255, 255, 255, 0.95);
            position: relative;
            overflow: hidden;
            transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.2s ease;
        }
        .thaaniyamhub-kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px -4px rgba(15, 23, 42, 0.08), 0 3px 8px -2px rgba(15, 23, 42, 0.03), inset 0 1px 0 rgba(255, 255, 255, 1);
            border-color: rgba(255, 255, 255, 1);
        }
        
        .kpi-card-head {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 4px;
        }
        .kpi-icon {
            font-size: 14px;
            line-height: 1;
            flex-shrink: 0;
        }
        .thaaniyamhub-kpi-label {
            font-size: 10px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1;
        }
        .thaaniyamhub-kpi-value {
            font-size: 17px;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 2px;
            letter-spacing: -0.02em;
        }
        .kpi-subtext {
            font-size: 10px;
            color: #94a3b8;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.2;
        }
        .font-semibold { font-weight: 600; }
        .text-emerald { color: #059669 !important; }
        .text-red { color: #dc2626 !important; }
        .text-blue { color: #2563eb !important; }
        .text-amber { color: #d97706 !important; }
        .text-orange { color: #ea580c !important; }
        .text-purple { color: #7c3aed !important; }
        .text-pink { color: #db2777 !important; }
        .text-indigo { color: #4f46e5 !important; }
        .kpi-hero {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.85) 0%, rgba(240, 253, 244, 0.8) 100%);
            border: 1.5px solid rgba(134, 239, 172, 0.85);
            box-shadow: 0 4px 18px -2px rgba(16, 185, 129, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.95);
        }
        .kpi-profit-negative {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.85) 0%, rgba(254, 242, 242, 0.8) 100%) !important;
            border: 1.5px solid rgba(252, 165, 165, 0.85) !important;
            box-shadow: 0 4px 18px -2px rgba(239, 68, 68, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.95) !important;
        }
        .badge-margin {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 9999px;
            font-size: 9.5px;
            font-weight: 700;
            text-transform: uppercase;
            margin-left: auto;
        }
        .badge-margin-pos {
            background: rgba(220, 252, 231, 0.85);
            color: #15803d;
            border: 1px solid rgba(187, 247, 208, 0.8);
            backdrop-filter: blur(4px);
        }
        .badge-margin-neg {
            background: rgba(254, 226, 226, 0.85);
            color: #b91c1c;
            border: 1px solid rgba(254, 202, 202, 0.8);
            backdrop-filter: blur(4px);
        }
        .thaaniyamhub-table-container {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.85);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.04), 0 1px 3px 0 rgba(15, 23, 42, 0.02), inset 0 1px 0 rgba(255, 255, 255, 0.95);
            position: relative;
            z-index: 1;
        }
        .table-header-bar {
            padding: 10px 14px;
            background: rgba(248, 250, 252, 0.7);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.7);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            font-weight: 700;
            color: #334155;
        }
        .table-title { display: flex; align-items: center; gap: 6px; }
        .table-pagination-info { font-size: 11.5px; color: #64748b; font-weight: 600; }
        .table-scroll-wrapper {
            overflow-x: auto;
            max-width: 100%;
        }
        .thaaniyamhub-ledger-table {
            border: none !important;
            margin: 0 !important;
            width: 100% !important;
            min-width: 1180px;
            background: transparent !important;
        }
        /* Grouped Header Rows */
        .header-group-row th {
            font-size: 10px !important;
            font-weight: 800 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            padding: 6px 10px !important;
            border-bottom: 1px solid rgba(203, 213, 225, 0.7) !important;
            border-right: 1px solid rgba(203, 213, 225, 0.7) !important;
            line-height: 1.2 !important;
        }
        .header-group-row th:last-child {
            border-right: none !important;
        }
        .th-group-order { background: rgba(241, 245, 249, 0.75) !important; color: #334155 !important; }
        .th-group-inflow { background: rgba(236, 253, 245, 0.75) !important; color: #065f46 !important; }
        .th-group-commission { background: rgba(238, 242, 255, 0.75) !important; color: #3730a3 !important; }
        .th-group-outflow { background: rgba(254, 243, 199, 0.75) !important; color: #92400e !important; }
        .th-group-profit { background: rgba(240, 253, 244, 0.75) !important; color: #166534 !important; }
        
        .header-col-row th {
            background: rgba(248, 250, 252, 0.75) !important;
            color: #475569 !important;
            font-size: 10.5px !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.02em !important;
            padding: 8px 10px !important;
            border-bottom: 1.5px solid rgba(203, 213, 225, 0.7) !important;
        }
        .sort-header-link {
            color: #475569 !important;
            text-decoration: none !important;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            cursor: pointer;
            transition: color 0.15s ease;
        }
        .sort-header-link:hover {
            color: #0071e3 !important;
        }
        .sort-header-link.is-sorted {
            color: #0071e3 !important;
            font-weight: 800 !important;
        }
        .sort-header-link .sort-dir-icon {
            font-size: 12px !important;
            width: 12px !important;
            height: 12px !important;
            line-height: 12px !important;
            vertical-align: middle;
            color: #0071e3;
        }
        th.col-numeric .sort-header-link {
            justify-content: flex-end;
            width: 100%;
        }
        .col-grp-end {
            border-right: 1px solid rgba(226, 232, 240, 0.7) !important;
        }
        .thaaniyamhub-ledger-table td {
            padding: 8px 10px !important;
            font-size: 11.5px !important;
            color: #334155 !important;
            vertical-align: middle !important;
            border-bottom: 1px solid rgba(241, 245, 249, 0.8) !important;
            background: transparent !important;
        }
        .col-numeric { text-align: right !important; }
        .ledger-row {
            transition: background-color 0.15s ease;
        }
        .ledger-row:hover td {
            background: rgba(241, 245, 249, 0.45) !important;
        }
        
        /* Order Cell Layout with Caret Toggle */
        .order-cell-wrap {
            display: flex;
            align-items: flex-start;
            gap: 6px;
        }
        .btn-toggle-audit {
            background: rgba(241, 245, 249, 0.85);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(203, 213, 225, 0.8);
            border-radius: 6px;
            color: #475569;
            cursor: pointer;
            padding: 0;
            width: 20px;
            height: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-top: 1px;
            flex-shrink: 0;
            transition: all 0.15s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        }
        .btn-toggle-audit:hover,
        .btn-toggle-audit.is-open {
            background: #0071e3;
            border-color: #0071e3;
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(0, 113, 227, 0.3);
            transform: scale(1.05);
        }
        .btn-toggle-audit:active {
            transform: scale(0.95);
        }
        .btn-toggle-audit .dashicons {
            font-size: 14px !important;
            width: 14px !important;
            height: 14px !important;
            line-height: 14px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            margin: 0 !important;
            padding: 0 !important;
            transition: transform 0.2s ease;
        }

        .order-id-stack, .cust-info-stack, .vendor-info-stack {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .order-sub-link { font-size: 12px; font-weight: 700; color: #0071e3 !important; text-decoration: none; }
        .order-sub-link:hover { text-decoration: underline; }
        .parent-ref { font-size: 9.5px; color: #94a3b8; }
        .parent-link { color: #64748b !important; text-decoration: none; }
        .date-text { font-size: 10px; color: #64748b; }
        .cust-name { font-weight: 600; color: #1e293b; font-size: 11.5px; }
        .cust-city { font-size: 9.5px; color: #94a3b8; }
        .vendor-name { font-weight: 600; color: #334155; font-size: 11.5px; }

        /* Unified 2-Line Numeric Stack Layout */
        .cell-stack-numeric {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 2px;
        }
        .amount-primary {
            font-size: 11.5px;
            line-height: 1.2;
        }
        .subtag-pill {
            font-size: 9.5px;
            font-weight: 600;
            padding: 1px 5px;
            border-radius: 4px;
            display: inline-block;
            width: fit-content;
            line-height: 1.2;
        }
        .subtag-discount {
            background: rgba(254, 226, 226, 0.85);
            color: #b91c1c;
            border: 1px solid rgba(254, 202, 202, 0.8);
            backdrop-filter: blur(4px);
        }
        .subtag-none {
            color: #94a3b8;
            font-size: 9.5px;
            font-weight: 500;
        }

        .sr-awb-tag {
            background: rgba(255, 237, 213, 0.85);
            color: #9a3412;
            font-size: 9.5px;
            font-weight: 600;
            padding: 1px 5px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 1px;
            border: 1px solid rgba(254, 215, 170, 0.7);
        }
        .sr-courier-tag { font-size: 9.5px; color: #64748b; }
        .rate-subtext { font-size: 9.5px; color: #94a3b8; display: block; line-height: 1.1; }
        .col-inflow { color: #059669; }
        .col-commission { color: #b91c1c; }
        .col-tax { color: #db2777; }
        .col-net-payout { color: #2563eb; }
        .col-logistics { color: #ea580c; }
        .col-gateway { color: #7c3aed; }
        .col-outflow { color: #dc2626; }
        .col-profit.profit-pos { color: #059669; }
        .col-profit.profit-neg { color: #dc2626; }
        .margin-pill {
            display: inline-block;
            font-size: 9.5px;
            font-weight: 700;
            padding: 1px 5px;
            border-radius: 4px;
            margin-top: 1px;
        }
        .margin-pos {
            background: rgba(220, 252, 231, 0.85);
            color: #166534;
            border: 1px solid rgba(187, 247, 208, 0.7);
        }
        .margin-neg {
            background: rgba(254, 226, 226, 0.85);
            color: #991b1b;
            border: 1px solid rgba(254, 202, 202, 0.7);
        }
        .badge-status-pill {
            font-size: 9px;
            font-weight: 700;
            padding: 1px 5px;
            border-radius: 4px;
            display: inline-block;
            width: fit-content;
            text-transform: uppercase;
        }
        .badge-status-processing { background: rgba(224, 242, 254, 0.85); color: #0369a1; border: 1px solid rgba(186, 230, 253, 0.7); }
        .badge-status-completed { background: rgba(220, 252, 231, 0.85); color: #15803d; border: 1px solid rgba(187, 247, 208, 0.7); }
        .badge-status-cancelled { background: rgba(254, 226, 226, 0.85); color: #b91c1c; border: 1px solid rgba(254, 202, 202, 0.7); }
        .badge-status-on-hold { background: rgba(254, 243, 199, 0.85); color: #b45309; border: 1px solid rgba(253, 230, 138, 0.7); }
        .badge-pill {
            font-size: 9.5px;
            font-weight: 600;
            padding: 1px 6px;
            border-radius: 9999px;
            display: inline-block;
            width: fit-content;
            text-transform: uppercase;
        }
        .badge-disbursed { background: rgba(219, 234, 254, 0.85); color: #1e40af; border: 1px solid rgba(191, 219, 254, 0.7); }
        .badge-processing { background: rgba(224, 242, 254, 0.85); color: #0369a1; border: 1px solid rgba(186, 230, 253, 0.7); }
        .badge-pending { background: rgba(254, 243, 199, 0.85); color: #92400e; border: 1px solid rgba(253, 230, 138, 0.7); }
        
        .audit-drawer-row td {
            background: rgba(248, 250, 252, 0.55) !important;
            padding: 12px 16px !important;
            border-bottom: 2px solid rgba(203, 213, 225, 0.7) !important;
        }
        .audit-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }
        .audit-box {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid rgba(226, 232, 240, 0.8);
            border-radius: 10px;
            padding: 12px 14px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03), inset 0 1px 0 rgba(255, 255, 255, 0.9);
        }
        .audit-box-title {
            font-size: 11px;
            font-weight: 700;
            color: #334155;
            border-bottom: 1px solid rgba(241, 245, 249, 0.9);
            padding-bottom: 6px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .audit-sub-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        .audit-sub-table td {
            padding: 3px 0 !important;
            border: none !important;
        }
        .text-right { text-align: right; }
        .font-bold { font-weight: 700; }
        .font-xl { font-size: 14px; }
        .audit-total-row td {
            border-top: 1px solid rgba(203, 213, 225, 0.7) !important;
            padding-top: 5px !important;
            margin-top: 3px;
        }
        .formula-margin {
            font-size: 10.5px;
            color: #64748b;
            margin-top: 5px;
        }
        .audit-quick-links {
            margin-top: 10px;
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .table-totals-row td {
            background: rgba(248, 250, 252, 0.8) !important;
            backdrop-filter: blur(10px);
            font-size: 11.5px !important;
            padding: 10px !important;
            border-top: 2px solid rgba(203, 213, 225, 0.8) !important;
        }
        .thaaniyamhub-pagination {
            padding: 10px 14px;
            background: rgba(248, 250, 252, 0.7);
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(226, 232, 240, 0.7);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .pagination-summary {
            font-size: 11.5px;
            color: #64748b;
            font-weight: 600;
        }
        .pagination-page-links {
            display: flex;
            gap: 4px;
            align-items: center;
        }
        .pagination-page-links .page-numbers {
            padding: 4px 10px;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(203, 213, 225, 0.7);
            border-radius: 6px;
            text-decoration: none;
            color: #334155;
            font-size: 11.5px;
            font-weight: 600;
            transition: all 0.15s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
        }
        .pagination-page-links .page-numbers:hover {
            background: rgba(255, 255, 255, 0.95);
            border-color: #cbd5e1;
            transform: translateY(-0.5px);
        }
        .pagination-page-links .page-numbers.current {
            background: linear-gradient(180deg, #0077ED 0%, #0062CC 100%);
            border-color: #0062CC;
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(0, 113, 227, 0.25);
        }
        .thaaniyamhub-no-records {
            padding: 40px 20px;
            text-align: center;
            color: #64748b;
        }
        .no-records-icon { font-size: 32px; display: block; margin-bottom: 8px; }
        @media (max-width: 1200px) {
            .thaaniyamhub-kpi-grid { grid-template-columns: repeat(2, 1fr); }
            .audit-grid { grid-template-columns: 1fr; }
            .filter-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .thaaniyamhub-kpi-grid { grid-template-columns: 1fr; }
            .filter-grid { grid-template-columns: 1fr; }
        }
        ';
        wp_add_inline_style('thaaniyamhub-vendor-reports', $css);

        // Interactive scripts
        $js = '
        jQuery(function($) {
            // Transform filter selects into iOS Frosted Glass Custom Dropdowns
            $(".filter-col select").each(function() {
                var $select = $(this);
                if ($select.data("ios-select-initialized")) return;
                $select.data("ios-select-initialized", true);

                $select.hide();

                var $wrapper = $(\'<div class="ios-select-wrapper"></div>\');
                var selectedText = $select.find("option:selected").text() || $select.find("option").first().text();
                var $trigger = $(\'<div class="ios-select-trigger" tabindex="0" role="button" aria-haspopup="listbox"></div>\').text(selectedText);
                var $dropdown = $(\'<div class="ios-select-dropdown" role="listbox"></div>\');

                $select.find("option").each(function() {
                    var $opt = $(this);
                    var val = $opt.val();
                    var txt = $opt.text();
                    var isSelected = $opt.is(":selected");

                    var $item = $(\'<div class="ios-select-item" role="option"></div>\')
                        .attr("data-value", val)
                        .toggleClass("is-selected", isSelected);

                    $item.append($(\'<span></span>\').text(txt));
                    if (isSelected) {
                        $item.append(\'<span class="ios-select-item-check">✓</span>\');
                    }

                    $item.on("click", function(e) {
                        e.stopPropagation();
                        $select.val(val).trigger("change");
                        $trigger.text(txt);
                        $dropdown.find(".ios-select-item").removeClass("is-selected").find(".ios-select-item-check").remove();
                        $item.addClass("is-selected").append(\'<span class="ios-select-item-check">✓</span>\');
                        $dropdown.hide();
                        $wrapper.removeClass("is-open");
                        $(".filter-col").removeClass("has-open-select");
                        $(".thaaniyamhub-filter-card").removeClass("has-open-select");
                    });

                    $dropdown.append($item);
                });

                $trigger.on("click", function(e) {
                    e.stopPropagation();
                    var isOpen = $dropdown.is(":visible");
                    $(".ios-select-dropdown").hide();
                    $(".ios-select-wrapper").removeClass("is-open");
                    $(".filter-col").removeClass("has-open-select");
                    $(".thaaniyamhub-filter-card").removeClass("has-open-select");
                    if (!isOpen) {
                        $dropdown.show();
                        $wrapper.addClass("is-open");
                        $wrapper.closest(".filter-col").addClass("has-open-select");
                        $wrapper.closest(".thaaniyamhub-filter-card").addClass("has-open-select");
                    }
                });

                $trigger.on("keydown", function(e) {
                    if (e.key === "Enter" || e.key === " " || e.key === "ArrowDown") {
                        e.preventDefault();
                        $trigger.trigger("click");
                    }
                });

                $wrapper.append($trigger).append($dropdown);
                $select.after($wrapper);
            });

            // Close iOS dropdowns on outside click or Escape
            $(document).on("click", function() {
                $(".ios-select-dropdown").hide();
                $(".ios-select-wrapper").removeClass("is-open");
                $(".filter-col").removeClass("has-open-select");
                $(".thaaniyamhub-filter-card").removeClass("has-open-select");
            });
            $(document).on("keydown", function(e) {
                if (e.key === "Escape") {
                    $(".ios-select-dropdown").hide();
                    $(".ios-select-wrapper").removeClass("is-open");
                    $(".filter-col").removeClass("has-open-select");
                    $(".thaaniyamhub-filter-card").removeClass("has-open-select");
                }
            });

            // Auto-submit on rows per page change
            $("#thaaniyamhub_limit").on("change", function() {
                $("#thaaniyamhub-filter-form").submit();
            });

            // Toggle custom date range inputs
            $("#thaaniyamhub_range").on("change", function() {
                if ($(this).val() === "custom") {
                    $("#thaaniyamhub-custom-dates").css("display", "flex");
                } else {
                    $("#thaaniyamhub-custom-dates").hide();
                }
            });

            // Toggle audit drawer rows and caret icon
            $(document).on("click", ".btn-toggle-audit", function(e) {
                e.preventDefault();
                var $btn = $(this);
                var rowId = $btn.data("row-id");
                var $drawer = $("#audit-drawer-" + rowId);
                var $icon = $btn.find(".toggle-icon");

                $drawer.toggle();
                var isOpen = $drawer.is(":visible");
                $btn.toggleClass("is-open", isOpen).attr("aria-expanded", isOpen);
                
                if (isOpen) {
                    $icon.removeClass("dashicons-arrow-down-alt2").addClass("dashicons-arrow-up-alt2");
                } else {
                    $icon.removeClass("dashicons-arrow-up-alt2").addClass("dashicons-arrow-down-alt2");
                }
            });
        });
        ';
        wp_add_inline_script('jquery', $js);
    }

    // =========================================================================
    // 5. HELPERS
    // =========================================================================

    /**
     * Resolve start/end dates from named range presets.
     *
     * @param string $range
     * @param string $from
     * @param string $to
     * @return array [ from_string, to_string ]
     */
    private static function resolve_date_range(string $range, string $from, string $to): array
    {
        $today = current_time('Y-m-d');

        switch ($range) {
            case 'today':
                return [$today, $today];
            case 'yesterday':
                $yest = date('Y-m-d', strtotime('-1 day', strtotime($today)));
                return [$yest, $yest];
            case 'week':
                return [date('Y-m-d', strtotime('-7 days', strtotime($today))), $today];
            case 'last_month':
                $first_day_last_month = date('Y-m-01', strtotime('first day of last month', strtotime($today)));
                $last_day_last_month  = date('Y-m-t', strtotime('last day of last month', strtotime($today)));
                return [$first_day_last_month, $last_day_last_month];
            case 'ytd':
                return [date('Y') . '-01-01', $today];
            case 'all':
                return ['', ''];
            case 'custom':
                $from = $from ?: date('Y-m-01');
                $to   = $to ?: $today;
                return [$from, $to];
            case 'month':
            default:
                return [date('Y-m-01'), $today];
        }
    }

    /**
     * Get all vendor users for filtering.
     *
     * @return WP_User[]
     */
    private static function get_all_vendors(): array
    {
        $roles = ['vendor', 'wcfm_vendor', 'seller', 'dc_vendor'];
        $users = get_users(['role__in' => $roles, 'orderby' => 'display_name', 'number' => 300]);

        if (empty($users)) {
            global $wpdb;
            $ids = $wpdb->get_col(
                "SELECT DISTINCT vendor_id FROM {$wpdb->prefix}thaaniyamhub_vendor_ledger ORDER BY vendor_id ASC LIMIT 300"
            );
            if (!empty($ids)) {
                $users = array_filter(array_map('get_userdata', $ids));
            }
        }

        return $users;
    }
}
