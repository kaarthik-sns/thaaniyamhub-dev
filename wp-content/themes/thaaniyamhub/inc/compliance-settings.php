<?php
/**
 * Thaaniyam Hub — Business Identity & Regulatory Compliance Module
 * 
 * Provides centralized management for:
 * - Legal Entity Details (Srukshara Agrowork Ventures LLP)
 * - Tax & Registration Identifiers (GSTIN, PAN, LLPIN, Udyam)
 * - Food Safety Compliance (FSSAI 14-digit License & Badge)
 * - Statutory Grievance Redressal Desk (Consumer Protection E-Commerce Rules 2020)
 * - Cashfree Payments & RBI Trust Badges
 * 
 * @package ThaaniyamHub
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Return default compliance settings
 */
function thaaniyamhub_get_compliance_defaults() {
    return array(
        'legal_entity_name'          => 'Srukshara Agrowork Ventures LLP',
        'brand_name'                 => 'Thaaniyam Hub',
        'gstin'                      => '33AAAFS1234M1Z5',
        'pan'                        => '',
        'llpin'                      => '',
        'udyam'                      => '',
        'fssai_number'               => '12424008000123',
        'fssai_logo_enable'          => '1',
        'registered_address'         => 'No. 1, Aranmanaiyar Thottam Main Road, Ramachandrapuram Anaimalai (Tk), Coimbatore - 642104, TN, India',
        'operational_address'        => '',
        'support_email'              => 'support@thaaniyamhub.com',
        'support_phone'              => '+91 8870007353',
        'support_hours'              => 'Monday – Saturday: 9:00 AM – 6:00 PM IST',
        'grievance_officer_name'     => 'Kaarthik S.',
        'grievance_officer_designation' => 'Nodal Grievance Officer',
        'grievance_officer_email'    => 'grievance@thaaniyamhub.com',
        'grievance_officer_phone'    => '+91 8870007353',
        'grievance_sla'              => 'Acknowledgement within 48 hours, resolution within 30 days',
        'display_contact_gst'        => '1',
        'display_grievance_desk'     => '1',
        'display_footer_compliance'  => '1',
        'display_product_fssai'      => '1',
    );
}

/**
 * Get a specific compliance setting with fallback
 */
function thaaniyamhub_get_compliance( $key, $default = '' ) {
    $defaults = thaaniyamhub_get_compliance_defaults();
    $options  = get_option( 'thaaniyamhub_compliance_settings', array() );

    if ( ! is_array( $options ) ) {
        $options = array();
    }

    if ( isset( $options[ $key ] ) && $options[ $key ] !== '' ) {
        return $options[ $key ];
    }

    if ( $default !== '' ) {
        return $default;
    }

    return isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
}

/**
 * Register Admin Settings in WordPress
 */
function thaaniyamhub_register_compliance_settings() {
    register_setting(
        'thaaniyamhub_compliance_group',
        'thaaniyamhub_compliance_settings',
        array(
            'type'              => 'array',
            'sanitize_callback' => 'thaaniyamhub_sanitize_compliance_settings',
            'default'           => thaaniyamhub_get_compliance_defaults(),
        )
    );
}
add_action( 'admin_init', 'thaaniyamhub_register_compliance_settings' );

/**
 * Sanitize settings inputs
 */
function thaaniyamhub_sanitize_compliance_settings( $input ) {
    $defaults = thaaniyamhub_get_compliance_defaults();
    $clean    = array();

    $clean['legal_entity_name']          = sanitize_text_field( isset( $input['legal_entity_name'] ) ? $input['legal_entity_name'] : $defaults['legal_entity_name'] );
    $clean['brand_name']                 = sanitize_text_field( isset( $input['brand_name'] ) ? $input['brand_name'] : $defaults['brand_name'] );
    
    // Uppercase alphanumeric sanitization for GSTIN, PAN, Udyam
    $clean['gstin']                      = strtoupper( preg_replace( '/[^a-zA-Z0-9]/', '', isset( $input['gstin'] ) ? $input['gstin'] : '' ) );
    $clean['pan']                        = strtoupper( preg_replace( '/[^a-zA-Z0-9]/', '', isset( $input['pan'] ) ? $input['pan'] : '' ) );
    $clean['llpin']                      = strtoupper( sanitize_text_field( isset( $input['llpin'] ) ? $input['llpin'] : '' ) );
    $clean['udyam']                      = strtoupper( sanitize_text_field( isset( $input['udyam'] ) ? $input['udyam'] : '' ) );
    
    // FSSAI 14 digits
    $clean['fssai_number']               = preg_replace( '/[^0-9]/', '', isset( $input['fssai_number'] ) ? $input['fssai_number'] : '' );
    $clean['fssai_logo_enable']          = ! empty( $input['fssai_logo_enable'] ) ? '1' : '0';

    $clean['registered_address']         = sanitize_textarea_field( isset( $input['registered_address'] ) ? $input['registered_address'] : $defaults['registered_address'] );
    $clean['operational_address']        = sanitize_textarea_field( isset( $input['operational_address'] ) ? $input['operational_address'] : '' );
    
    $clean['support_email']              = sanitize_email( isset( $input['support_email'] ) ? $input['support_email'] : $defaults['support_email'] );
    $clean['support_phone']              = sanitize_text_field( isset( $input['support_phone'] ) ? $input['support_phone'] : $defaults['support_phone'] );
    $clean['support_hours']              = sanitize_text_field( isset( $input['support_hours'] ) ? $input['support_hours'] : $defaults['support_hours'] );
    
    $clean['grievance_officer_name']     = sanitize_text_field( isset( $input['grievance_officer_name'] ) ? $input['grievance_officer_name'] : '' );
    $clean['grievance_officer_designation'] = sanitize_text_field( isset( $input['grievance_officer_designation'] ) ? $input['grievance_officer_designation'] : $defaults['grievance_officer_designation'] );
    $clean['grievance_officer_email']    = sanitize_email( isset( $input['grievance_officer_email'] ) ? $input['grievance_officer_email'] : $defaults['grievance_officer_email'] );
    $clean['grievance_officer_phone']    = sanitize_text_field( isset( $input['grievance_officer_phone'] ) ? $input['grievance_officer_phone'] : $defaults['grievance_officer_phone'] );
    $clean['grievance_sla']              = sanitize_text_field( isset( $input['grievance_sla'] ) ? $input['grievance_sla'] : $defaults['grievance_sla'] );

    $clean['display_contact_gst']        = ! empty( $input['display_contact_gst'] ) ? '1' : '0';
    $clean['display_grievance_desk']     = ! empty( $input['display_grievance_desk'] ) ? '1' : '0';
    $clean['display_footer_compliance']  = ! empty( $input['display_footer_compliance'] ) ? '1' : '0';
    $clean['display_product_fssai']      = ! empty( $input['display_product_fssai'] ) ? '1' : '0';

    return $clean;
}

/**
 * Register Admin Menu Pages
 */
function thaaniyamhub_add_compliance_admin_menu() {
    add_menu_page(
        __( 'Compliance & Business Info', 'thaaniyamhub' ),
        __( 'Business Compliance', 'thaaniyamhub' ),
        'manage_options',
        'thaaniyamhub-compliance',
        'thaaniyamhub_render_compliance_admin_page',
        'dashicons-shield-alt',
        57
    );

    if ( class_exists( 'WooCommerce' ) ) {
        add_submenu_page(
            'woocommerce',
            __( 'Business Compliance & Cashfree', 'thaaniyamhub' ),
            __( 'Business Compliance', 'thaaniyamhub' ),
            'manage_options',
            'thaaniyamhub-compliance',
            'thaaniyamhub_render_compliance_admin_page'
        );
    }
}
add_action( 'admin_menu', 'thaaniyamhub_add_compliance_admin_menu' );

/**
 * Render Admin Settings Screen
 */
function thaaniyamhub_render_compliance_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $settings = get_option( 'thaaniyamhub_compliance_settings', thaaniyamhub_get_compliance_defaults() );
    if ( ! is_array( $settings ) ) {
        $settings = thaaniyamhub_get_compliance_defaults();
    }
    $defaults = thaaniyamhub_get_compliance_defaults();
    ?>
    <style>
        .th-compliance-wrap {
            max-width: 1180px;
            margin: 20px 20px 40px 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        }
        .th-cards-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
        }
        .th-card {
            background: #fff;
            border-radius: 10px;
            padding: 24px;
            border: 1px solid #dcdcde;
            box-shadow: 0 2px 6px rgba(0,0,0,0.03);
            margin-bottom: 24px;
        }
        .th-card-title {
            font-size: 16px;
            font-weight: 600;
            color: #064c50;
            margin: 0 0 18px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .th-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }
        .th-form-group {
            margin-bottom: 16px;
        }
        .th-form-group.full-width {
            grid-column: 1 / -1;
        }
        .th-form-group label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            color: #2c3338;
            margin-bottom: 6px;
        }
        .th-form-group input[type="text"],
        .th-form-group input[type="email"],
        .th-form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #8c8f94;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        .th-form-group input:focus,
        .th-form-group textarea:focus {
            border-color: #064c50;
            box-shadow: 0 0 0 1px #064c50;
            outline: none;
        }
        .th-form-help {
            font-size: 12px;
            color: #646970;
            margin-top: 4px;
        }
        .th-toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f1;
        }
        .th-toggle-row:last-child {
            border-bottom: none;
        }
        .th-save-bar {
            position: sticky;
            bottom: 20px;
            background: #fff;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 -2px 15px rgba(0,0,0,0.08);
            border: 1px solid #c3c4c7;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 25px;
            z-index: 100;
        }
    </style>

    <div class="wrap th-compliance-wrap">
        <?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) : ?>
            <div class="notice notice-success is-dismissible">
                <p><strong><?php _e( 'Compliance and regulatory details updated successfully!', 'thaaniyamhub' ); ?></strong> Changes are now live on your Contact Us page and Footer.</p>
            </div>
        <?php endif; ?>

        <div style="margin-bottom: 24px; padding-bottom: 14px; border-bottom: 1px solid #c3c4c7;">
            <h1 style="font-size: 24px; font-weight: 700; color: #064c50; display: flex; align-items: center; gap: 10px; margin: 0 0 6px 0;">
                <span class="dashicons dashicons-shield-alt" style="font-size: 28px; width: 28px; height: 28px;"></span> 
                <?php _e( 'Business Compliance Settings', 'thaaniyamhub' ); ?>
            </h1>
            <p style="color: #646970; font-size: 13px; margin: 0;">
                <?php _e( 'Configure business registration, GSTIN, FSSAI, registered address, and customer support details.', 'thaaniyamhub' ); ?>
            </p>
        </div>

        <form method="post" action="options.php">
            <?php settings_fields( 'thaaniyamhub_compliance_group' ); ?>

            <div class="th-cards-container">
                <div class="th-main-column">

                    <!-- CARD 1: Corporate & Tax -->
                    <div class="th-card">
                        <h2 class="th-card-title"><span class="dashicons dashicons-building"></span> 1. Legal Entity & Tax Registrations</h2>
                        <div class="th-form-grid">
                            <div class="th-form-group">
                                <label for="legal_entity_name">Registered Legal Entity Name <span style="color:#d63638;">*</span></label>
                                <input type="text" id="legal_entity_name" name="thaaniyamhub_compliance_settings[legal_entity_name]" value="<?php echo esc_attr( $settings['legal_entity_name'] ); ?>" required />
                                <div class="th-form-help">Must exactly match your Bank Account & PAN KYC (e.g. Srukshara Agrowork Ventures LLP).</div>
                            </div>
                            <div class="th-form-group">
                                <label for="brand_name">Brand / Trade Name</label>
                                <input type="text" id="brand_name" name="thaaniyamhub_compliance_settings[brand_name]" value="<?php echo esc_attr( $settings['brand_name'] ); ?>" />
                                <div class="th-form-help">Your commercial consumer brand name (e.g. Thaaniyam Hub).</div>
                            </div>
                            <div class="th-form-group">
                                <label for="gstin">GSTIN (15 Digits) <span style="color:#d63638;">*</span></label>
                                <input type="text" id="gstin" name="thaaniyamhub_compliance_settings[gstin]" value="<?php echo esc_attr( $settings['gstin'] ); ?>" maxlength="15" placeholder="e.g. 33XXXXX1234X1ZX" style="font-family:monospace; text-transform:uppercase;" />
                                <div class="th-form-help">Goods & Services Tax Identification Number (Crucial for Cashfree approval).</div>
                            </div>
                        </div>
                    </div>

                    <!-- CARD 2: FSSAI Food Safety (Critical) -->
                    <div class="th-card" style="border-left: 4px solid #064c50;">
                        <h2 class="th-card-title"><span class="dashicons dashicons-carrot"></span> 2. Food Safety & Standards Authority of India (FSSAI)</h2>
                        <div class="th-form-grid">
                            <div class="th-form-group">
                                <label for="fssai_number">FSSAI License / Registration No. (14 Digits) <span style="color:#d63638;">*</span></label>
                                <input type="text" id="fssai_number" name="thaaniyamhub_compliance_settings[fssai_number]" value="<?php echo esc_attr( $settings['fssai_number'] ); ?>" maxlength="14" placeholder="e.g. 124XXXXXXXXXXX" style="font-family:monospace; font-size:16px; letter-spacing:1px;" />
                                <div class="th-form-help"><strong>Mandatory for Food & Millets:</strong> Cashfree underwriters reject food sites without a verified 14-digit FSSAI license.</div>
                            </div>
                            <div class="th-form-group" style="display:flex; flex-direction:column; justify-content:center;">
                                <label style="margin-bottom:8px;">FSSAI Logo Badge Display</label>
                                <label style="font-weight:normal; display:flex; align-items:center; gap:8px;">
                                    <input type="checkbox" name="thaaniyamhub_compliance_settings[fssai_logo_enable]" value="1" <?php checked( $settings['fssai_logo_enable'], '1' ); ?> />
                                    Show official FSSAI Emblem with license number in the footer & Contact Us page
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- CARD 3: Address & Support -->
                    <div class="th-card">
                        <h2 class="th-card-title"><span class="dashicons dashicons-location"></span> 3. Registered Physical Address & Customer Support</h2>
                        <div class="th-form-group">
                            <label for="registered_address">Registered Physical Office Address <span style="color:#d63638;">*</span></label>
                            <textarea id="registered_address" name="thaaniyamhub_compliance_settings[registered_address]" rows="3"><?php echo esc_textarea( $settings['registered_address'] ); ?></textarea>
                            <div class="th-form-help">Must include Building/Door No., Street, City, State, and 6-digit PIN code matching your GST / LLP registration.</div>
                        </div>
                        <div class="th-form-grid">
                            <div class="th-form-group">
                                <label for="support_email">Customer Care Email <span style="color:#d63638;">*</span></label>
                                <input type="email" id="support_email" name="thaaniyamhub_compliance_settings[support_email]" value="<?php echo esc_attr( $settings['support_email'] ); ?>" />
                            </div>
                            <div class="th-form-group">
                                <label for="support_phone">Customer Care Phone <span style="color:#d63638;">*</span></label>
                                <input type="text" id="support_phone" name="thaaniyamhub_compliance_settings[support_phone]" value="<?php echo esc_attr( $settings['support_phone'] ); ?>" />
                            </div>
                            <div class="th-form-group full-width">
                                <label for="support_hours">Customer Support Working Hours</label>
                                <input type="text" id="support_hours" name="thaaniyamhub_compliance_settings[support_hours]" value="<?php echo esc_attr( $settings['support_hours'] ); ?>" />
                                <div class="th-form-help">e.g. Monday – Saturday: 9:00 AM – 6:00 PM IST</div>
                            </div>
                        </div>
                    </div>

                    <!-- CARD 4: Grievance Redressal Desk -->
                    <div class="th-card">
                        <h2 class="th-card-title"><span class="dashicons dashicons-businessman"></span> 4. Statutory Grievance Redressal Officer (Rule 5(9) E-Commerce Rules)</h2>
                        <p style="font-size:13px; color:#50575e; margin-top:-8px; margin-bottom:15px;">Mandated by the <em>Consumer Protection (E-Commerce) Rules, 2020</em>. Cashfree underwriters check for this on your Contact Us page.</p>
                        <div class="th-form-grid">
                            <div class="th-form-group">
                                <label for="grievance_officer_name">Grievance Officer Name <span style="color:#d63638;">*</span></label>
                                <input type="text" id="grievance_officer_name" name="thaaniyamhub_compliance_settings[grievance_officer_name]" value="<?php echo esc_attr( $settings['grievance_officer_name'] ); ?>" placeholder="e.g. Kaarthik / Nodal Officer Name" />
                            </div>
                            <div class="th-form-group">
                                <label for="grievance_officer_designation">Designation</label>
                                <input type="text" id="grievance_officer_designation" name="thaaniyamhub_compliance_settings[grievance_officer_designation]" value="<?php echo esc_attr( $settings['grievance_officer_designation'] ); ?>" />
                            </div>
                            <div class="th-form-group">
                                <label for="grievance_officer_email">Direct Grievance Email <span style="color:#d63638;">*</span></label>
                                <input type="email" id="grievance_officer_email" name="thaaniyamhub_compliance_settings[grievance_officer_email]" value="<?php echo esc_attr( $settings['grievance_officer_email'] ); ?>" />
                            </div>
                            <div class="th-form-group">
                                <label for="grievance_officer_phone">Direct Contact Phone</label>
                                <input type="text" id="grievance_officer_phone" name="thaaniyamhub_compliance_settings[grievance_officer_phone]" value="<?php echo esc_attr( $settings['grievance_officer_phone'] ); ?>" />
                            </div>
                            <div class="th-form-group full-width">
                                <label for="grievance_sla">Grievance Escalation SLA</label>
                                <input type="text" id="grievance_sla" name="thaaniyamhub_compliance_settings[grievance_sla]" value="<?php echo esc_attr( $settings['grievance_sla'] ); ?>" />
                                <div class="th-form-help">Standard statutory SLA: Acknowledgement within 48 hours, resolution within 30 days.</div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- SIDEBAR: Display Toggles -->
                <div class="th-side-column">

                    <div class="th-card">
                        <h2 class="th-card-title"><span class="dashicons dashicons-visibility"></span> Frontend Display Toggles</h2>
                        <div class="th-toggle-row">
                            <div>
                                <strong>Show GSTIN on Contact Page</strong>
                                <div class="th-form-help">Toggle GST number on Contact Us</div>
                            </div>
                            <input type="checkbox" name="thaaniyamhub_compliance_settings[display_contact_gst]" value="1" <?php checked( $settings['display_contact_gst'], '1' ); ?> />
                        </div>
                        <div class="th-toggle-row">
                            <div>
                                <strong>Statutory Grievance Desk</strong>
                                <div class="th-form-help">Toggle Grievance card on Contact Us</div>
                            </div>
                            <input type="checkbox" name="thaaniyamhub_compliance_settings[display_grievance_desk]" value="1" <?php checked( $settings['display_grievance_desk'], '1' ); ?> />
                        </div>
                        <div class="th-toggle-row">
                            <div>
                                <strong>Global Footer Bar</strong>
                                <div class="th-form-help">Show compliance line & FSSAI in footer</div>
                            </div>
                            <input type="checkbox" name="thaaniyamhub_compliance_settings[display_footer_compliance]" value="1" <?php checked( $settings['display_footer_compliance'], '1' ); ?> />
                        </div>
                        <div class="th-toggle-row">
                            <div>
                                <strong>Product Page FSSAI</strong>
                                <div class="th-form-help">Display FSSAI badge on product details</div>
                            </div>
                            <input type="checkbox" name="thaaniyamhub_compliance_settings[display_product_fssai]" value="1" <?php checked( $settings['display_product_fssai'], '1' ); ?> />
                        </div>
                    </div>

                </div>
            </div>

            <div class="th-save-bar">
                <span style="font-size:13px; color:#50575e;">
                    <strong>Tip:</strong> Once saved, all updates reflect instantly on <a href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>" target="_blank">Contact Us</a> and the global site footer.
                </span>
                <?php submit_button( __( 'Save Compliance Changes', 'thaaniyamhub' ), 'primary large', 'submit', false ); ?>
            </div>
        </form>
    </div>
    <?php
}

/**
 * Expose settings to WordPress Customizer for live preview
 */
function thaaniyamhub_register_compliance_customizer( $wp_customize ) {
    $wp_customize->add_section(
        'thaaniyamhub_compliance_customizer_section',
        array(
            'title'       => __( 'Business Compliance & FSSAI', 'thaaniyamhub' ),
            'priority'    => 35,
            'description' => __( 'Manage statutory identifiers (GSTIN & FSSAI) for payment gateway compliance.', 'thaaniyamhub' ),
        )
    );

    $fields = array(
        'legal_entity_name' => array( 'label' => 'Legal Entity Name', 'type' => 'text' ),
        'brand_name'        => array( 'label' => 'Brand / Trade Name', 'type' => 'text' ),
        'gstin'             => array( 'label' => 'GSTIN Number', 'type' => 'text' ),
        'fssai_number'      => array( 'label' => 'FSSAI License No. (14 Digits)', 'type' => 'text' ),
    );

    foreach ( $fields as $key => $data ) {
        $setting_id = "thaaniyamhub_compliance_settings[{$key}]";
        $wp_customize->add_setting(
            $setting_id,
            array(
                'type'              => 'option',
                'capability'        => 'manage_options',
                'default'           => thaaniyamhub_get_compliance( $key ),
                'sanitize_callback' => 'sanitize_text_field',
            )
        );

        $wp_customize->add_control(
            $setting_id,
            array(
                'label'    => $data['label'],
                'section'  => 'thaaniyamhub_compliance_customizer_section',
                'type'     => $data['type'],
                'settings' => $setting_id,
            )
        );
    }
}
add_action( 'customize_register', 'thaaniyamhub_register_compliance_customizer' );

/**
 * Render Official Real FSSAI Logo
 */
function thaaniyamhub_render_fssai_svg( $width = 65, $height = 28 ) {
    $logo_url = get_stylesheet_directory_uri() . '/assets/images/fssai-logo.png';
    $h = intval( $height ) > 0 ? intval( $height ) : 28;
    return '<img src="' . esc_url( $logo_url ) . '" alt="FSSAI" width="' . intval( $width ) . '" height="' . $h . '" style="height:' . $h . 'px; width:auto; vertical-align:middle; display:inline-block; object-fit:contain;" />';
}

/**
 * Render Payment Partner Badges (Cashfree, UPI, RuPay, Visa, MC)
 */
function thaaniyamhub_render_payment_trust_badges() {
    $out  = '<div class="th-payment-trust-badges" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">';
    
    // Cashfree Secured Badge
    if ( thaaniyamhub_get_compliance( 'display_cashfree_badge', '1' ) === '1' ) {
        $out .= '<div class="th-badge-cashfree" style="background:rgba(255,255,255,0.12); border:1px solid rgba(255,255,255,0.22); padding:4px 10px; border-radius:6px; font-size:11px; font-weight:600; color:#fff; display:inline-flex; align-items:center; gap:6px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#26a69a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <span>Secured by <strong>Cashfree</strong></span>
        </div>';
    }

    // Payment Methods
    if ( thaaniyamhub_get_compliance( 'display_payment_badges', '1' ) === '1' ) {
        $modes = array( 'UPI', 'RuPay', 'Visa', 'Mastercard', 'NetBanking' );
        foreach ( $modes as $mode ) {
            $out .= '<span style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.18); padding:3px 8px; border-radius:4px; font-size:10px; font-weight:700; color:#e0f2f1; text-transform:uppercase; letter-spacing:0.4px;">' . esc_html( $mode ) . '</span>';
        }
    }

    $out .= '</div>';
    return $out;
}

/**
 * Render the Corporate & Regulatory Compliance section on the Contact Us page
 */
function thaaniyamhub_render_contact_compliance_box( $atts = array() ) {
    $a = shortcode_atts( array(
        'show_gst'       => '',
        'show_grievance' => '',
    ), $atts );

    $show_gst = thaaniyamhub_get_compliance( 'display_contact_gst', '1' ) === '1';
    if ( $a['show_gst'] !== '' ) {
        $show_gst = filter_var( $a['show_gst'], FILTER_VALIDATE_BOOLEAN );
    }

    $show_grievance = thaaniyamhub_get_compliance( 'display_grievance_desk', '1' ) === '1';
    if ( $a['show_grievance'] !== '' ) {
        $show_grievance = filter_var( $a['show_grievance'], FILTER_VALIDATE_BOOLEAN );
    }

    $legal_entity = thaaniyamhub_get_compliance( 'legal_entity_name' );
    $brand_name   = thaaniyamhub_get_compliance( 'brand_name' );
    $gstin        = thaaniyamhub_get_compliance( 'gstin' );
    $fssai        = thaaniyamhub_get_compliance( 'fssai_number' );
    $fssai_logo   = thaaniyamhub_get_compliance( 'fssai_logo_enable', '1' );
    $address      = thaaniyamhub_get_compliance( 'registered_address' );
    $hours        = thaaniyamhub_get_compliance( 'support_hours' );
    
    $officer_name = thaaniyamhub_get_compliance( 'grievance_officer_name' );
    $officer_desg = thaaniyamhub_get_compliance( 'grievance_officer_designation' );
    $officer_mail = thaaniyamhub_get_compliance( 'grievance_officer_email' );
    $officer_phone= thaaniyamhub_get_compliance( 'grievance_officer_phone' );
    $grievance_sla= thaaniyamhub_get_compliance( 'grievance_sla' );

    $corp_col_class = $show_grievance ? 'col-md-7' : 'col-md-12';

    ob_start();
    ?>
    <style id="th-compliance-contact-styles">
        .th-compliance-contact-block {
            margin-top: 45px;
            margin-bottom: 35px;
            font-family: 'Montserrat', sans-serif;
        }
        .th-compliance-contact-block * {
            box-sizing: border-box;
        }
        .th-contact-compliance-card {
            background: #FFFFFF;
            border: 1.5px solid #064C50;
            border-radius: 22px;
            padding: 30px;
            height: 100%;
            box-shadow: 0 6px 20px rgba(6, 76, 80, 0.05);
            display: flex;
            flex-direction: column;
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        }
        .th-contact-compliance-card:hover {
            box-shadow: 0 10px 28px rgba(6, 76, 80, 0.09);
        }
        .th-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(6, 76, 80, 0.15);
            padding-bottom: 16px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .th-card-title-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .th-card-icon-box {
            width: 44px;
            height: 44px;
            border: 1.5px solid #1F6F63;
            border-radius: 10px;
            background: #FFFAED;
            color: #064C50;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .th-card-title {
            color: #064C50;
            font-family: 'Museo', serif;
            font-size: 24px;
            font-weight: 500;
            line-height: 1.25;
            margin: 0;
        }
        .th-brand-entity-line {
            font-family: 'Montserrat', sans-serif;
            font-size: 15px;
            color: #242424;
            line-height: 1.65;
            margin-bottom: 18px;
        }
        .th-brand-entity-line strong {
            font-weight: 600;
        }
        .th-identifiers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }
        .th-identifier-pill {
            background: #FFFAED;
            border: 1px solid rgba(6, 76, 80, 0.22);
            border-radius: 12px;
            padding: 10px 14px;
        }
        .th-identifier-label {
            font-family: 'Montserrat', sans-serif;
            font-size: 11px;
            text-transform: uppercase;
            color: #1F6F63;
            font-weight: 700;
            letter-spacing: 0.6px;
            margin-bottom: 4px;
        }
        .th-identifier-val {
            font-family: 'Montserrat', sans-serif;
            font-size: 15px;
            font-weight: 700;
            color: #064C50;
            letter-spacing: 0.5px;
            word-break: break-all;
        }
        .th-fssai-pill {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .th-fssai-pill-logo {
            flex-shrink: 0;
            display: flex;
            align-items: center;
        }
        .th-office-address {
            font-family: 'Montserrat', sans-serif;
            font-size: 14px;
            color: #242424;
            line-height: 1.6;
            margin-top: 4px;
        }
        .th-office-address strong {
            color: #064C50;
            font-weight: 600;
        }
        .th-support-hours-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 14px;
            font-family: 'Montserrat', sans-serif;
            font-size: 13px;
            color: #064C50;
            font-weight: 600;
            background: #FFFAED;
            border: 1px solid rgba(6, 76, 80, 0.2);
            padding: 6px 14px;
            border-radius: 20px;
        }
        .th-grievance-intro {
            font-family: 'Montserrat', sans-serif;
            font-size: 12px;
            color: #555555;
            margin-bottom: 16px;
            line-height: 1.5;
        }
        .th-officer-card {
            background: #FFFAED;
            border: 1px solid rgba(6, 76, 80, 0.22);
            border-radius: 14px;
            padding: 16px 18px;
            margin-bottom: 14px;
        }
        .th-officer-name {
            font-family: 'Museo', serif;
            font-weight: 500;
            font-size: 19px;
            color: #064C50;
            margin-bottom: 2px;
        }
        .th-officer-desg {
            font-family: 'Montserrat', sans-serif;
            font-size: 12px;
            color: #1F6F63;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        .th-officer-contact-line {
            font-family: 'Montserrat', sans-serif;
            font-size: 14px;
            color: #242424;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
        }
        .th-officer-contact-line a {
            color: #064C50;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.15s ease;
        }
        .th-officer-contact-line a:hover {
            color: #1F6F63;
            text-decoration: underline;
        }
        .th-sla-text {
            font-family: 'Montserrat', sans-serif;
            font-size: 12px;
            color: #555555;
            line-height: 1.5;
            margin-top: auto;
        }
        .th-sla-text strong {
            color: #064C50;
        }
        @media (max-width: 767px) {
            .th-contact-compliance-card {
                padding: 22px 18px;
                border-radius: 18px;
            }
            .th-card-title {
                font-size: 20px;
            }
        }
    </style>

    <div class="th-compliance-contact-block">
        <div class="row">
            
            <!-- Corporate Identification Card (GST & FSSAI Only) -->
            <div class="<?php echo esc_attr( $corp_col_class ); ?> mb-4">
                <div class="th-contact-compliance-card">
                    <div class="th-card-header">
                        <div class="th-card-title-group">
                            <div class="th-card-icon-box">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#064C50" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect>
                                    <line x1="9" y1="22" x2="9" y2="22.01"></line>
                                    <line x1="15" y1="22" x2="15" y2="22.01"></line>
                                    <line x1="9" y1="6" x2="9" y2="6.01"></line>
                                    <line x1="15" y1="6" x2="15" y2="6.01"></line>
                                    <line x1="9" y1="10" x2="9" y2="10.01"></line>
                                    <line x1="15" y1="10" x2="15" y2="10.01"></line>
                                    <line x1="9" y1="14" x2="9" y2="14.01"></line>
                                    <line x1="15" y1="14" x2="15" y2="14.01"></line>
                                    <line x1="9" y1="18" x2="9" y2="18.01"></line>
                                    <line x1="15" y1="18" x2="15" y2="18.01"></line>
                                </svg>
                            </div>
                            <h4 class="th-card-title">Corporate &amp; Regulatory Information</h4>
                        </div>
                    </div>
                    
                    <p class="th-brand-entity-line">
                        <strong><?php echo esc_html( ! empty( $brand_name ) ? $brand_name : 'Thaaniyam Hub' ); ?></strong> is owned and operated by <strong style="color:#064C50;"><?php echo esc_html( ! empty( $legal_entity ) ? $legal_entity : 'Srukshara Agrowork Ventures LLP' ); ?></strong>.
                    </p>

                    <?php if ( ( $show_gst && ! empty( $gstin ) ) || ! empty( $fssai ) ) : ?>
                    <div class="th-identifiers-grid">
                        <?php if ( $show_gst && ! empty( $gstin ) ) : ?>
                            <div class="th-identifier-pill">
                                <div class="th-identifier-label">GSTIN (Goods &amp; Services Tax)</div>
                                <div class="th-identifier-val"><?php echo esc_html( $gstin ); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if ( ! empty( $fssai ) ) : ?>
                            <div class="th-identifier-pill th-fssai-pill">
                                <div>
                                    <div class="th-identifier-label">FSSAI License No.</div>
                                    <div class="th-identifier-val"><?php echo esc_html( $fssai ); ?></div>
                                </div>
                                <?php if ( $fssai_logo === '1' ) : ?>
                                    <div class="th-fssai-pill-logo">
                                        <?php echo thaaniyamhub_render_fssai_svg(65, 26); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ( ! empty( $address ) ) : ?>
                        <div class="th-office-address">
                            <strong>Registered Office:</strong> <?php echo esc_html( $address ); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ( ! empty( $hours ) ) : ?>
                        <div>
                            <div class="th-support-hours-badge">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#064C50" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                                <span>Support Hours: <?php echo esc_html( $hours ); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Grievance Redressal Desk Card (Conditional) -->
            <?php if ( $show_grievance ) : ?>
            <div class="col-md-5 mb-4">
                <div class="th-contact-compliance-card">
                    <div class="th-card-header">
                        <div class="th-card-title-group">
                            <div class="th-card-icon-box">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#064C50" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                            </div>
                            <h4 class="th-card-title">Statutory Grievance Desk</h4>
                        </div>
                    </div>

                    <p class="th-grievance-intro">
                        In accordance with Rule 5(9) of the <em>Consumer Protection (E-Commerce) Rules, 2020</em>:
                    </p>

                    <div class="th-officer-card">
                        <div class="th-officer-name">
                            <?php echo ! empty( $officer_name ) ? esc_html( $officer_name ) : esc_html( $legal_entity . ' Nodal Desk' ); ?>
                        </div>
                        <div class="th-officer-desg">
                            <?php echo esc_html( $officer_desg ); ?>
                        </div>
                        <div class="th-officer-contact-line">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#064C50" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                <polyline points="22,6 12,13 2,6"></polyline>
                            </svg>
                            <a href="mailto:<?php echo esc_attr( $officer_mail ); ?>"><?php echo esc_html( $officer_mail ); ?></a>
                        </div>
                        <?php if ( ! empty( $officer_phone ) ) : ?>
                            <div class="th-officer-contact-line">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#064C50" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                </svg>
                                <a href="tel:<?php echo esc_attr( $officer_phone ); ?>"><?php echo esc_html( $officer_phone ); ?></a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="th-sla-text">
                        <strong>SLA Guarantee:</strong> <?php echo esc_html( $grievance_sla ); ?>.
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'thaaniyamhub_compliance_box', 'thaaniyamhub_render_contact_compliance_box' );
add_shortcode( 'thaaniyamhub_contact_compliance', 'thaaniyamhub_render_contact_compliance_box' );

/**
 * Display FSSAI Badge on WooCommerce Single Product Page
 */
function thaaniyamhub_display_product_fssai_badge() {
    if ( thaaniyamhub_get_compliance( 'display_product_fssai', '1' ) !== '1' ) {
        return;
    }
    $fssai = thaaniyamhub_get_compliance( 'fssai_number' );
    if ( empty( $fssai ) ) {
        return;
    }
    ?>
    <div class="th-product-fssai-pill" style="display:inline-flex; align-items:center; gap:8px; background:#f0f9f8; border:1px solid #b2dfdb; border-radius:6px; padding:6px 12px; margin:12px 0; font-size:12px;">
        <?php echo thaaniyamhub_render_fssai_svg(54, 22); ?>
        <span style="color:#064c50; font-weight:600;">Lic. No: <strong style="font-family:monospace; letter-spacing:0.5px;"><?php echo esc_html( $fssai ); ?></strong></span>
        <span style="color:#00796b; font-size:11px;">(100% Certified Food Safety)</span>
    </div>
    <?php
}
add_action( 'woocommerce_single_product_summary', 'thaaniyamhub_display_product_fssai_badge', 25 );
