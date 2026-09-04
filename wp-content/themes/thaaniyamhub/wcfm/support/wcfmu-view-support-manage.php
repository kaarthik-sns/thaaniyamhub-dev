<?php
/**
 * WCFM plugin view
 *
 * wcfm Support Manage View
 *
 * @author  Squiz Pty Ltd <products@squiz.net>
 * @package wcfmu/views/support
 * @version 4.0.3
 */

global $wp, $WCFM, $WCFMu, $wpdb, $blog_id;

if (! apply_filters('wcfm_is_pref_support', true) || ! apply_filters('wcfm_is_allow_support', true) || ! apply_filters('wcfm_is_allow_manage_support', true)) {
    wcfm_restriction_message_show('Manage Support');
    return;
}

$support_id             = 0;
$support_ticket_title   = '';
$support_ticket_content = '';
$allow_reply            = 'no';
$close_new_reply        = 'no';

if (isset($wp->query_vars['wcfm-support-manage']) && ! empty($wp->query_vars['wcfm-support-manage'])) {
    $support_id   = $wp->query_vars['wcfm-support-manage'];
    $support_post = $wpdb->get_row("SELECT * from {$wpdb->prefix}wcfm_support WHERE `ID` = ".$support_id);
    // Fetching Support Data
    if ($support_post && ! empty($support_post)) {
        $support_ticket_content = $support_post->query;
        $support_order_id       = $support_post->order_id;
        $support_item_id        = $support_post->item_id;
        $support_product_id     = $support_post->product_id;
        $support_vendor_id      = $support_post->vendor_id;
        $support_customer_id    = $support_post->customer_id;
        $support_customer_name  = $support_post->customer_name;
        $support_customer_email = $support_post->customer_email;
    } else {
        wcfm_restriction_message_show('Invalid Ticket');
        return;
    }
} else {
    wcfm_restriction_message_show('Invalid Ticket');
    return;
}

$support_categories     = $WCFMu->wcfmu_support->wcfm_support_categories();
$support_priority_types = $WCFMu->wcfmu_support->wcfm_support_priority_types();
$support_status_types   = $WCFMu->wcfmu_support->wcfm_support_status_types();

if (wcfm_is_vendor()) {
    $is_ticket_for_vendor = $WCFM->wcfm_vendor_support->wcfm_is_component_for_vendor($support_id, 'support');
    if (! $is_ticket_for_vendor) {
        if (apply_filters('wcfm_is_show_support_ticket_restrict_message', true, $support_id)) {
            wcfm_restriction_message_show('Restricted Ticket');
        } else {
            echo apply_filters('wcfm_show_custom_support_ticket_restrict_message', '', $support_id);
        }

        return;
    }
}

$wcfm_options                  = $WCFM->wcfm_options;
$wcfm_support_allow_attachment = isset($wcfm_options['wcfm_support_allow_attachment']) ? $wcfm_options['wcfm_support_allow_attachment'] : 'yes';

// Customer avatar resolution
$customer_avatar = '';
if ($support_customer_id) {
    $wp_user_avatar_id = get_user_meta($support_customer_id, $wpdb->get_blog_prefix($blog_id).'user_avatar', true);
    $customer_avatar   = wp_get_attachment_url($wp_user_avatar_id);
    if (! $customer_avatar) {
        $customer_avatar = get_avatar_url($support_customer_id);
    }
}
if (! $customer_avatar) {
    $customer_avatar = apply_filters('wcfm_default_user_image', $WCFM->plugin_url.'assets/images/user.png');
}

do_action('before_wcfm_support_manage');

?>

<div class="collapse wcfm-collapse">
  <div class="wcfm-page-headig">
        <span class="wcfmfa fa-life-ring"></span>
        <span class="wcfm-page-heading-text"><?php _e('Support Ticket', 'wc-frontend-manager-ultimate'); ?></span>
        <?php do_action('wcfm_page_heading'); ?>
    </div>
    <div class="wcfm-collapse-content">
      <div id="wcfm_page_load"></div>
        
        <!-- Header Bar -->
        <div class="wcfm-container wcfm-top-element-container th-support-header-bar">
            <div class="th-ticket-title-wrap">
                <h2><?php echo __('Ticket', 'wc-frontend-manager-ultimate').' #'.sprintf('%06u', $support_id); ?></h2>
                <span class="support-priority support-priority-<?php echo esc_attr($support_post->priority); ?>"><?php echo esc_html($support_priority_types[$support_post->priority] ?? $support_post->priority); ?></span>
                <span class="th-support-status-badge status-<?php echo esc_attr($support_post->status); ?>">
                    <i class="wcfmfa <?php echo ($support_post->status == 'open') ? 'fa-folder-open' : 'fa-check-circle'; ?>"></i>
                    <?php echo ($support_post->status == 'open') ? __('Open', 'wc-frontend-manager-ultimate') : __('Closed', 'wc-frontend-manager-ultimate'); ?>
                </span>
            </div>
            
            <div class="th-ticket-actions">
                <a id="add_new_support_dashboard" class="add_new_wcfm_ele_dashboard text_tip" href="<?php echo wcfm_support_url(); ?>" data-tip="<?php _e('Support Tickets', 'wc-frontend-manager-ultimate'); ?>">
                    <span class="wcfmfa fa-life-ring"></span><span class="text"><?php _e('Back to Tickets', 'wc-frontend-manager-ultimate'); ?></span>
                </a>
            </div>
            <div class="wcfm-clearfix"></div>
        </div>
        <div class="wcfm-clearfix"></div><br />
        
        <?php do_action('begin_wcfm_support_manage_form'); ?>

        <!-- 2 Column Layout Grid -->
        <div class="th-support-grid">
            
            <!-- Left Column: Main Message & Conversation -->
            <div class="th-support-main-col">
                
                <!-- Initial Ticket Request Card -->
                <div class="wcfm-container th-ticket-card th-initial-ticket-card">
                    <div class="th-card-header">
                        <div class="th-author-avatar">
                            <img src="<?php echo esc_url($customer_avatar); ?>" alt="<?php echo esc_attr($support_customer_name); ?>" />
                        </div>
                        <div class="th-author-meta">
                            <div class="th-author-name">
                                <strong><?php echo esc_html($support_customer_name); ?></strong>
                                <span class="th-role-badge customer-role"><?php _e('Customer', 'wc-frontend-manager-ultimate'); ?></span>
                            </div>
                            <div class="th-ticket-date">
                                <span class="wcfmfa fa-clock"></span> <?php echo date_i18n(wc_date_format().' '.wc_time_format(), strtotime($support_post->posted)); ?>
                            </div>
                        </div>
                    </div>
                    <div class="th-card-body">
                        <div class="support_ticket_content">
                            <?php echo wp_kses_post($support_ticket_content); ?>
                        </div>
                    </div>
                </div>

                <!-- Replies Section -->
                <?php
                if ($wcfm_is_allow_view_support_reply_view = apply_filters('wcfmcap_is_allow_support_reply_view', true)) {
                    $wcfm_support_replies = $wpdb->get_results("SELECT * from {$wpdb->prefix}wcfm_support_response WHERE `support_id` = ".$support_id);
                ?>
                    <div class="th-replies-heading">
                        <h3><span class="wcfmfa fa-comments"></span> <?php _e('Replies', 'wc-frontend-manager-ultimate'); ?> (<?php echo count($wcfm_support_replies); ?>)</h3>
                    </div>

                    <?php if (! empty($wcfm_support_replies)) {
                        foreach ($wcfm_support_replies as $wcfm_support_reply) {
                            $author_id   = $wcfm_support_reply->reply_by;
                            $author_role = 'Customer';
                            if (wcfm_is_vendor($author_id)) {
                                $author_role    = 'Vendor';
                                $wp_user_avatar = $WCFM->wcfm_vendor_support->wcfm_get_vendor_logo_by_vendor($author_id);
                                if (! $wp_user_avatar) {
                                    $wp_user_avatar = apply_filters('wcfmmp_store_default_logo', $WCFM->plugin_url.'assets/images/wcfmmp.png');
                                }
                                $author_display_name = $WCFM->wcfm_vendor_support->wcfm_get_vendor_store_name_by_vendor($author_id);
                            } else if ($author_id != $wcfm_support_reply->customer_id) {
                                $author_role       = 'Support';
                                $wp_user_avatar_id = get_user_meta($author_id, $wpdb->get_blog_prefix($blog_id).'user_avatar', true);
                                $wp_user_avatar    = wp_get_attachment_url($wp_user_avatar_id);
                                if (! $wp_user_avatar) {
                                    $wp_user_avatar = apply_filters('wcfm_default_user_image', $WCFM->plugin_url.'assets/images/user.png');
                                }
                                $author_display_name = get_bloginfo('name');
                            } else {
                                $author_role       = 'Customer';
                                $wp_user_avatar_id = get_user_meta($author_id, $wpdb->get_blog_prefix($blog_id).'user_avatar', true);
                                $wp_user_avatar    = wp_get_attachment_url($wp_user_avatar_id);
                                if (! $wp_user_avatar) {
                                    $wp_user_avatar = apply_filters('wcfm_default_user_image', $WCFM->plugin_url.'assets/images/user.png');
                                }
                                $userdata            = get_userdata($author_id);
                                $author_display_name = $userdata ? ($userdata->first_name ? $userdata->first_name.' '.$userdata->last_name : $userdata->display_name) : $support_customer_name;
                            }
                    ?>
                        <div class="wcfm-container th-ticket-card th-reply-card" id="support_ticket_reply_<?php echo $wcfm_support_reply->ID; ?>">
                            <div class="th-card-header">
                                <div class="th-author-avatar">
                                    <img src="<?php echo esc_url($wp_user_avatar); ?>" alt="<?php echo esc_attr($author_display_name); ?>" />
                                </div>
                                <div class="th-author-meta">
                                    <div class="th-author-name">
                                        <strong><?php echo esc_html($author_display_name); ?></strong>
                                        <span class="th-role-badge role-<?php echo strtolower($author_role); ?>"><?php echo esc_html($author_role); ?></span>
                                    </div>
                                    <div class="th-ticket-date">
                                        <span class="wcfmfa fa-clock"></span> <?php echo date_i18n(wc_date_format().' '.wc_time_format(), strtotime($wcfm_support_reply->posted)); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="th-card-body">
                                <div class="support_ticket_reply_content">
                                    <?php echo wp_kses_post($wcfm_support_reply->reply); ?>
                                    <?php $WCFMu->wcfmu_support->wcfm_support_reply_attachments($wcfm_support_reply->ID); ?>
                                </div>
                            </div>
                        </div>
                    <?php } } ?>
                <?php } ?>

                <!-- New Reply Form -->
                <?php if ($wcfm_is_allow_view_support_reply = apply_filters('wcfmcap_is_allow_support_reply', true)) { ?>
                    <?php do_action('before_wcfm_support_reply_form'); ?>
                    <form id="wcfm_support_ticket_reply_form" class="wcfm">
                        <div class="wcfm-container th-reply-form-card">
                            <div class="th-card-title-bar">
                                <h3><span class="wcfmfa fa-reply"></span> <?php _e('Post a Reply', 'wc-frontend-manager-ultimate'); ?></h3>
                            </div>
                            <div id="wcfm_new_reply_listing_expander" class="wcfm-content">
                                <?php
                                $rich_editor = apply_filters('wcfm_is_allow_rich_editor', 'rich_editor');
                                $wpeditor    = apply_filters('wcfm_is_allow_profile_wpeditor', 'wpeditor');
                                if ($wpeditor && $rich_editor) {
                                    $rich_editor = 'wcfm_wpeditor';
                                } else {
                                    $wpeditor = 'textarea';
                                }

                                $wcfm_support_ticket_reply_fields = apply_filters(
                                    'wcfm_support_ticket_reply_fields',
                                    [
                                        'support_ticket_reply'   => [
                                            'label'         => __('Message', 'wc-frontend-manager'),
                                            'type'          => $wpeditor,
                                            'class'         => 'wcfm-textarea wcfm_ele wcfm_full_ele '.$rich_editor,
                                            'label_class'   => 'wcfm_title wcfm_full_ele_title',
                                            'media_buttons' => false,
                                            'teeny'         => true,
                                        ],
                                        'support_reply_break1'   => [
                                            'type'  => 'html',
                                            'value' => '<div class="wcfm-clearfix" style="margin-bottom: 25px;"></div>',
                                        ],
                                        'support_attachments'    => [
                                            'label'       => __('Attachment(s)', 'wc-frontend-manager'),
                                            'type'        => 'multiinput',
                                            'class'       => 'wcfm-text wcfm_ele wcfm_non_sortable',
                                            'label_class' => 'wcfm_title',
                                            'value'       => [],
                                            'options'     => [
                                                'file' => [
                                                    'label'       => __('Add File', 'wc-frontend-manager'),
                                                    'type'        => 'file',
                                                    'class'       => 'wcfm-text wcfm_ele',
                                                    'label_class' => 'wcfm_title',
                                                ],
                                            ],
                                            'desc'        => sprintf(__('Allowed file types: %1$s', 'wc-frontend-manager'), '<b style="color:#f86c6b;">'.implode(', ', array_keys(wcfm_get_allowed_mime_types())).'</b>'),
                                        ],
                                        'support_reply_break2'   => [
                                            'type'  => 'html',
                                            'value' => '<div class="wcfm-clearfix" style="margin-bottom: 15px;"></div>',
                                        ],
                                        'support_priority'       => [
                                            'label'       => __('Priority', 'wc-frontend-manager-ultimate'),
                                            'type'        => 'select',
                                            'class'       => 'wcfm-select wcfm_ele',
                                            'label_class' => 'wcfm_title',
                                            'options'     => $support_priority_types,
                                            'value'       => $support_post->priority,
                                        ],
                                        'support_status'         => [
                                            'label'       => __('Status', 'wc-frontend-manager-ultimate'),
                                            'type'        => 'select',
                                            'class'       => 'wcfm-select wcfm_ele',
                                            'label_class' => 'wcfm_title',
                                            'options'     => $support_status_types,
                                            'value'       => $support_post->status,
                                        ],
                                        'support_ticket_id'      => [
                                            'type'  => 'hidden',
                                            'value' => $support_id,
                                        ],
                                        'support_order_id'       => [
                                            'type'  => 'hidden',
                                            'value' => $support_order_id,
                                        ],
                                        'support_item_id'        => [
                                            'type'  => 'hidden',
                                            'value' => $support_item_id,
                                        ],
                                        'support_product_id'     => [
                                            'type'  => 'hidden',
                                            'value' => $support_product_id,
                                        ],
                                        'support_vendor_id'      => [
                                            'type'  => 'hidden',
                                            'value' => $support_vendor_id,
                                        ],
                                        'support_customer_id'    => [
                                            'type'  => 'hidden',
                                            'value' => $support_customer_id,
                                        ],
                                        'support_customer_name'  => [
                                            'type'  => 'hidden',
                                            'value' => $support_customer_name,
                                        ],
                                        'support_customer_email' => [
                                            'type'  => 'hidden',
                                            'value' => $support_customer_email,
                                        ],
                                    ],
                                    $support_id
                                );

                                if (( $wcfm_support_allow_attachment == 'no' ) || ! apply_filters('wcfm_is_allow_support_reply_attachment', true)) {
                                    unset($wcfm_support_ticket_reply_fields['support_attachments'], $wcfm_support_ticket_reply_fields['support_reply_break2']);
                                }

                                $WCFM->wcfm_fields->wcfm_generate_form_field($wcfm_support_ticket_reply_fields);
                                ?>
                                <div class="wcfm-clearfix"></div>
                                <div class="wcfm-message" tabindex="-1"></div>
                                <div class="wcfm-clearfix"></div>
                                <div id="wcfm_support_reply_submit">
                                    <input type="submit" name="save-data" value="<?php _e('Send Reply', 'wc-frontend-manager-ultimate'); ?>" id="wcfm_reply_send_button" class="wcfm_submit_button" />
                                </div>
                                <div class="wcfm-clearfix"></div>
                            </div>
                        </div>
                    </form>
                    <?php do_action('after_wcfm_support_reply_form'); ?>
                <?php } ?>

            </div>

            <!-- Right Column: Sidebar Details Card -->
            <div class="th-support-sidebar-col">
                <div class="wcfm-container th-sidebar-card">
                    <div class="th-card-title-bar">
                        <h3><span class="wcfmfa fa-info-circle"></span> <?php _e('Ticket Details', 'wc-frontend-manager-ultimate'); ?></h3>
                    </div>
                    <div class="th-card-body">
                        
                        <!-- Product Info -->
                        <?php if ($support_product_id) {
                            $post_obj = get_post($support_product_id);
                            if ($post_obj && $post_obj->post_type == 'product') {
                                $the_product    = wc_get_product($support_product_id);
                                $thumbnail_html = $the_product ? $the_product->get_image('thumbnail', ['class' => 'th-product-thumb']) : '';
                            } else {
                                $thumbnail_html = '';
                            }
                        ?>
                            <div class="th-detail-item th-detail-product">
                                <label><?php _e('Product', 'wc-frontend-manager-ultimate'); ?></label>
                                <div class="th-detail-value">
                                    <?php echo $thumbnail_html; ?>
                                    <a href="<?php echo get_permalink($support_product_id); ?>" target="_blank" class="th-product-title">
                                        <?php echo get_the_title($support_product_id); ?>
                                    </a>
                                </div>
                            </div>
                        <?php } ?>

                        <!-- Store / Vendor Info -->
                        <?php if ($support_vendor_id) {
                            $store_name = apply_filters('wcfmmp_is_allow_sold_by_linked', true) 
                                ? $WCFM->wcfm_vendor_support->wcfm_get_vendor_store_by_vendor(absint($support_vendor_id))
                                : $WCFM->wcfm_vendor_support->wcfm_get_vendor_store_name_by_vendor(absint($support_vendor_id));
                            $store_logo = $WCFM->wcfm_vendor_support->wcfm_get_vendor_logo_by_vendor(absint($support_vendor_id));
                        ?>
                            <div class="th-detail-item th-detail-store">
                                <label><?php _e('Store', 'wc-frontend-manager-ultimate'); ?></label>
                                <div class="th-detail-value">
                                    <img src="<?php echo esc_url($store_logo); ?>" class="th-vendor-logo" alt="<?php echo esc_attr(strip_tags($store_name)); ?>" />
                                    <span class="th-store-name"><?php echo $store_name; ?></span>
                                </div>
                            </div>
                        <?php } ?>

                        <!-- Order Info -->
                        <?php if ($support_order_id) { ?>
                            <div class="th-detail-item">
                                <label><?php _e('Order', 'wc-frontend-manager-ultimate'); ?></label>
                                <div class="th-detail-value">
                                    <?php if (apply_filters('wcfm_is_allow_order_details', true) && $WCFM->wcfm_vendor_support->wcfm_is_order_for_vendor($support_order_id)) { ?>
                                        <a class="th-order-badge" target="_blank" href="<?php echo get_wcfm_view_order_url($support_order_id); ?>">#<?php echo $support_order_id; ?></a>
                                    <?php } else { ?>
                                        <span class="th-order-badge">#<?php echo $support_order_id; ?></span>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>

                        <!-- Category -->
                        <div class="th-detail-item">
                            <label><?php _e('Category', 'wc-frontend-manager-ultimate'); ?></label>
                            <div class="th-detail-value">
                                <span class="th-category-badge"><?php echo esc_html($support_post->category); ?></span>
                            </div>
                        </div>

                        <!-- Customer Details -->
                        <?php if (apply_filters('wcfm_allow_view_customer_name', true)) { ?>
                            <div class="th-detail-item">
                                <label><?php _e('Customer', 'wc-frontend-manager-ultimate'); ?></label>
                                <div class="th-detail-value th-customer-info">
                                    <div class="th-customer-name">
                                        <?php if ($support_customer_id && apply_filters('wcfm_is_allow_view_customer', true)) { ?>
                                            <a target="_blank" href="<?php echo get_wcfm_customers_details_url($support_customer_id); ?>"><?php echo esc_html($support_customer_name); ?></a>
                                        <?php } else { ?>
                                            <?php echo esc_html($support_customer_name); ?>
                                        <?php } ?>
                                    </div>
                                    <?php if (apply_filters('wcfm_allow_view_customer_email', true) && ! empty($support_customer_email)) { ?>
                                        <div class="th-customer-email"><?php echo esc_html($support_customer_email); ?></div>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>

                    </div>
                </div>
            </div>

        </div>
        <!-- End 2 Column Grid -->

        <?php do_action('end_wcfm_support_manage_form'); ?>
        <?php do_action('after_wcfm_support_manage'); ?>

    </div>
</div>
