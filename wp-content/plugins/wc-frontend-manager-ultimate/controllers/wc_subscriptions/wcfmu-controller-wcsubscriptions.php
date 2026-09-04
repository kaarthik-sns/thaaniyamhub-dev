<?php

/**
 * WCFM plugin controllers
 *
 * Plugin WC Subscription Dashboard Controller
 *
 * @author  WC Lovers
 * @package wcfm/controllers/wc_subscriptions
 * @version 4.0.7
 */

use Automattic\WooCommerce\Utilities\OrderUtil;

class WCFMu_WCSubscriptions_Controller {

    public function __construct() {
        $this->processing();
    } //end __construct()


    public function processing() {
        global $WCFM, $_POST;

        $wc_get_subscription_status_name = apply_filters(
            'wcfmu_subscriptions_menus',
            [
                'all'       => __('All', 'wc-frontend-manager-ultimate'),
                'active'    => __('Active', 'wc-frontend-manager-ultimate'),
                'on-hold'   => __('On Hold', 'wc-frontend-manager-ultimate'),
                'pending'   => __('Pending', 'wc-frontend-manager-ultimate'),
                'cancelled' => __('Cancelled', 'wc-frontend-manager-ultimate'),
                'expired'   => __('Expired', 'wc-frontend-manager-ultimate'),
            ]
        );

        $length = $_POST['length'];
        $offset = $_POST['start'];

        if (isset($_POST['subscription_product']) && !empty($_POST['subscription_product'])) {
            $include_subscriptions = wcs_get_subscriptions_for_product($_POST['subscription_product']);
        } else {
            $include_subscriptions = apply_filters('wcfm_wcs_include_subscriptions', '');
        }

        /**
         *  Don't use 'include' parameter in $args
         *  as wc_get_orders() with HPOS is unable to detect it
         *  rather use 'post__in' alias
         * 
         *  Suggestion: always prefer using WP_Query parameters over to get_posts() parameters
         */
        $args = [
            'posts_per_page' => $length,
            'offset'         => $offset,
            'category'       => '',
            'category_name'  => '',
            'orderby'        => 'date',
            'order'          => 'DESC',
            // 'include'        => $include_subscriptions,
            'post__in'       => $include_subscriptions,
            'exclude'        => '',
            'meta_key'       => '',
            'meta_value'     => '',
            'post_type'      => 'shop_subscription',
            'post_mime_type' => '',
            'post_parent'    => '',
            // 'author'     => get_current_user_id(),
            'post_status'    => ['any'],
            // 'suppress_filters' => 0
        ];
        
        if (isset($_POST['search']) && !empty($_POST['search']['value'])) {
            $args['s'] = $_POST['search']['value'];
        }

        if (isset($_POST['subscription_status']) && !empty($_POST['subscription_status'])) {
            $args['post_status'] = 'wc-' . $_POST['subscription_status'];
        }

        if (isset($_POST['subscription_filter']) && !empty($_POST['subscription_filter'])) {
            $args['meta_key']   = '_subscription_product_id';
            $args['meta_value'] = wc_clean($_POST['subscription_filter']);
        }

        if (!empty($_POST['filter_date_form']) && !empty($_POST['filter_date_to'])) {
            $fyear  = absint(substr($_POST['filter_date_form'], 0, 4));
            $fmonth = absint(substr($_POST['filter_date_form'], 5, 2));
            $fday   = absint(substr($_POST['filter_date_form'], 8, 2));

            $tyear  = absint(substr($_POST['filter_date_to'], 0, 4));
            $tmonth = absint(substr($_POST['filter_date_to'], 5, 2));
            $tday   = absint(substr($_POST['filter_date_to'], 8, 2));

            $args['date_query'] = [
                'after'     => [
                    'year'  => $fyear,
                    'month' => $fmonth,
                    'day'   => $fday,
                ],
                'before'    => [
                    'year'  => $tyear,
                    'month' => $tmonth,
                    'day'   => $tday,
                ],
                'inclusive' => true,
            ];
        } //end if

        $args = apply_filters('wcfm_subscriptions_args', $args);

        if (OrderUtil::custom_orders_table_usage_is_enabled()) {
            if (!empty($args['meta_key'])) {
                if (isset($args['meta_query'])) {
                    $args['meta_query'][] = [
                        'key'   => $args['meta_key'],
                        'value' => $args['meta_value']
                    ];
                } else {
                    $args['meta_query'] = [
                        [
                            'key'   => $args['meta_key'],
                            'value' => $args['meta_value']
                        ]
                    ];
                }

                unset($args['meta_key']);
                unset($args['meta_value']);
            }

            $wcfm_subscriptions_array = wc_get_orders($args);
        } else {
            $wcfm_subscriptions_array = get_posts($args);
        }

        // Get Product Count
        $subscription_count          = 0;
        $filtered_subscription_count = 0;
        $subscription_count          = count($wcfm_subscriptions_array);
        // Get Filtered Post Count
        $args['posts_per_page']           = -1;
        $args['offset']                   = 0;

        $wcfm_filterd_subscriptions_array = OrderUtil::custom_orders_table_usage_is_enabled() ? wc_get_orders($args) : get_posts($args);

        $filtered_subscription_count      = count($wcfm_filterd_subscriptions_array);

        // Generate Subscriptions JSON
        $datatable_json = [
            'draw'              => (int) wc_clean($_POST['draw']),
            'recordsTotal'      => (int) $subscription_count,
            'recordsFiltered'   => (int) $filtered_subscription_count,
            'data'              => []
        ];

        if (!empty($wcfm_subscriptions_array)) {
            $index = 0;
            foreach ($wcfm_subscriptions_array as $wcfm_subscriptions_single) {
                $subscription_id = method_exists($wcfm_subscriptions_single, 'get_id') ? $wcfm_subscriptions_single->get_id() : $wcfm_subscriptions_single->ID;
                $the_subscription = wcs_get_subscription($subscription_id);
                $subscription_parent_id = $the_subscription->get_parent_id();
                $the_order        = wc_get_order($subscription_parent_id);

                // Status
                $datatable_json['data'][$index][] = apply_filters('wcfm_subscription_status_label', '<span class="subscription-status tips wcicon-status-' . sanitize_title($the_subscription->get_status()) . ' text_tip" data-tip="' . $wc_get_subscription_status_name[$the_subscription->get_status()] . '"></span>', $subscription_id, $the_subscription);

                // Subscription
                if (apply_filters('wcfm_is_allow_subscription_details', true)) {
                    $subscription_label = '<a href="' . get_wcfm_subscriptions_manage_url($subscription_id, $the_subscription) . '" class="wcfm_dashboard_item_title">' . __('#', 'wc-frontend-manager') . $subscription_id . '</a>';
                } else {
                    $subscription_label = __('#', 'wc-frontend-manager') . $subscription_id;
                }

                if ($the_subscription->get_user_id() && (false !== ($user_info = get_userdata($the_subscription->get_user_id())))) {
                    $subscription_label .= ' by ';
                    if ($the_subscription->get_billing_first_name()) {
                        if ($the_subscription->get_billing_first_name() || $the_subscription->get_billing_last_name()) {
                            $guest_name = esc_html(ucfirst($the_subscription->get_billing_first_name()) . ' ' . ucfirst($the_subscription->get_billing_last_name()));
                        } else if ($user_info->first_name || $user_info->last_name) {
                            $guest_name = esc_html(ucfirst($user_info->first_name) . ' ' . ucfirst($user_info->last_name));
                        } else {
                            $guest_name = esc_html(ucfirst($user_info->display_name));
                        }

                        $guest_name = apply_filters('wcfm_subscription_by_user', $guest_name, $subscription_id, $subscription_parent_id);
                        if (apply_filters('wcfm_allow_view_customer_email', true) && $the_subscription->get_billing_email()) {
                            $subscription_label .= '<a href="mailto:' . $the_subscription->get_billing_email() . '">' . $guest_name . '</a>';
                        } else {
                            $subscription_label .= $guest_name;
                        }
                    } else {
                        $subscription_label .= sprintf(_x('Guest (%s)', 'Guest string with name from subscription order in brackets', 'wc-frontend-manager'), '&ndash;');
                    }
                } else if ($the_subscription->get_billing_first_name() || $the_subscription->get_billing_last_name()) {
                    $guest_name          = trim($the_subscription->get_billing_first_name() . ' ' . $the_subscription->get_billing_last_name());
                    $subscription_label .= ' by ';
                    if (apply_filters('wcfm_allow_view_customer_email', true)) {
                        $subscription_label .= '<a href="mailto:' . $the_subscription->get_billing_email() . '">' . $guest_name . '</a>';
                    } else {
                        $subscription_label .= $guest_name;
                    }
                } //end if

                $datatable_json['data'][$index][] = $subscription_label;

                // Order
                if ($the_order) {
                    if (apply_filters('wcfm_is_allow_order_details', true) && $WCFM->wcfm_vendor_support->wcfm_is_order_for_vendor($subscription_parent_id)) {
                        $datatable_json['data'][$index][] = '<span class="subscription-orderno"><a href="' . get_wcfm_view_order_url($subscription_parent_id, $the_order) . '">#' . $subscription_parent_id . '</a></span><br />' . esc_html(wc_get_order_status_name($the_order->get_status()));
                    } else {
                        $datatable_json['data'][$index][] = '<span class="subscription-orderno">#' . $subscription_parent_id . '</span><br /> ' . esc_html(wc_get_order_status_name($the_order->get_status()));
                    }
                } else {
                    $datatable_json['data'][$index][] = '&ndash;';
                }

                // Items
                $subscription_items = $the_subscription->get_items();
                $item_data          = '';
                switch (count($subscription_items)) {
                    case 0:
                        $item_data .= '&ndash;';
                        break;

                    case 1:
                        foreach ($subscription_items as $item) {
                            $_product     = apply_filters('woocommerce_order_item_product', $item->get_product(), $item);
                            if($_product) {
                                $product_post = get_post($_product->get_ID());
                                $item_data   .= $product_post->post_title;
                            }
                        }
                        break;

                    default:
                        $item_data .= '<a href="#" class="show_order_items">' . esc_html(apply_filters('woocommerce_admin_order_item_count', sprintf(_n('%d item', '%d items', $the_subscription->get_item_count(), 'woocommerce-subscriptions'), $the_subscription->get_item_count()), $the_subscription)) . '</a>';
                        $item_data .= '<table class="order_items" cellspacing="0">';

                        foreach ($subscription_items as $item) {
                            $_product     = apply_filters('woocommerce_order_item_product', $item->get_product(), $item);
                            $product_post = get_post($_product->get_ID());
                            $item_data   .= '<tr><td>' . $product_post->post_title . '</td></tr>';
                        }

                        $item_data .= '</table>';
                        break;
                } //end switch

                $datatable_json['data'][$index][] = $item_data;

                // Total
                $total_data  = esc_html(strip_tags($the_subscription->get_formatted_order_total()));
                $total_data .= '<br/><small class="meta">' . esc_html(sprintf(__('Via %s', 'woocommerce-subscriptions'), $the_subscription->get_payment_method_to_display())) . '</small>';
                $datatable_json['data'][$index][] = $total_data;

                // Start Date
                $datatable_json['data'][$index][] = esc_html($the_subscription->get_date_to_display('date_created'));

                // Trial End
                $datatable_json['data'][$index][] = esc_html($the_subscription->get_date_to_display('trial_end_date'));

                // Next Payment Date
                $datatable_json['data'][$index][] = esc_html($the_subscription->get_date_to_display('next_payment_date'));

                // Last Payment Date
                $datatable_json['data'][$index][] = esc_html($the_subscription->get_date_to_display('last_order_date_created'));

                // End Date
                $datatable_json['data'][$index][] = esc_html($the_subscription->get_date_to_display('end_date'));

                // Additional Info
                if ($the_order) {
                    $datatable_json['data'][$index][] = apply_filters('wcfm_subscriptions_additonal_data', '&ndash;', $subscription_id, $subscription_parent_id);
                } else {
                    $datatable_json['data'][$index][] = apply_filters('wcfm_subscriptions_additonal_data', '&ndash;', $subscription_id, 0);
                }

                // Action
                $actions = '&ndash;';
                if (apply_filters('wcfm_is_allow_subscription_details', true)) {
                    $actions = apply_filters('wcfm_subscriptions_actions', '<a class="wcfm-action-icon" href="' . get_wcfm_subscriptions_manage_url($subscription_id) . '"><span class="wcfmfa fa-eye text_tip" data-tip="' . esc_attr__('View Details', 'wc-frontend-manager') . '"></span></a>', $wcfm_subscriptions_single, $the_subscription);
                }

                $datatable_json['data'][$index][] = $actions;

                $index++;
            } //end foreach
        } //end if

        wp_send_json($datatable_json);
    } //end processing()


}//end class
