<?php
/**
 * Security Hardening against Information Disclosure and Brute-Force Attacks.
 *
 * @package ThaaniyamHub_Multi_Vendor_Orders
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThaaniyamHub_Security_Hardening {

    /**
     * Initialize security hooks.
     */
    public static function init() {
        // 1. Disable XML-RPC in PHP as defense-in-depth
        add_filter( 'xmlrpc_enabled', '__return_false' );
        add_filter( 'xmlrpc_methods', '__return_empty_array' );
        add_filter( 'wp_headers', array( __CLASS__, 'remove_pingback_header' ) );

        // 2. Prevent User Enumeration via author queries & archives
        add_action( 'init', array( __CLASS__, 'block_author_enumeration' ) );
        add_action( 'template_redirect', array( __CLASS__, 'block_author_archive' ) );

        // 3. Genericize login error messages to prevent username harvesting
        add_filter( 'login_errors', array( __CLASS__, 'generic_login_errors' ) );

        // 4. Remove WordPress version disclosure
        remove_action( 'wp_head', 'wp_generator' );
        add_filter( 'the_generator', '__return_empty_string' );

        // 5. Remove script/style version query string for public visitors
        add_filter( 'style_loader_src', array( __CLASS__, 'remove_ver_query_arg' ), 9999 );
        add_filter( 'script_loader_src', array( __CLASS__, 'remove_ver_query_arg' ), 9999 );

        // 6. Restrict SVG uploads strictly to administrators (prevent Stored XSS via SVG scripts)
        add_filter( 'upload_mimes', array( __CLASS__, 'restrict_svg_uploads_to_admins' ), 999 );

        // 7. Prevent REST API user enumeration for non-authenticated visitors
        add_filter( 'rest_endpoints', array( __CLASS__, 'block_rest_user_enumeration' ) );
    }

    /**
     * Remove X-Pingback header.
     *
     * @param array $headers
     * @return array
     */
    public static function remove_pingback_header( $headers ) {
        if ( isset( $headers['X-Pingback'] ) ) {
            unset( $headers['X-Pingback'] );
        }
        return $headers;
    }

    /**
     * Block user enumeration via ?author=N query.
     */
    public static function block_author_enumeration() {
        if ( ! is_admin() && isset( $_REQUEST['author'] ) && is_numeric( $_REQUEST['author'] ) ) {
            wp_safe_redirect( home_url(), 301 );
            exit;
        }
    }

    /**
     * Block public author archive pages.
     */
    public static function block_author_archive() {
        if ( is_author() && ! is_admin() ) {
            wp_safe_redirect( home_url(), 301 );
            exit;
        }
    }

    /**
     * Generic error message on failed login to stop brute-force username enumeration.
     *
     * @return string
     */
    public static function generic_login_errors() {
        return __( 'Invalid username or password.', 'thaaniyamhub' );
    }

    /**
     * Remove ?ver= from CSS and JS URLs for non-logged-in users.
     *
     * @param string $src
     * @return string
     */
    public static function remove_ver_query_arg( $src ) {
        if ( strpos( $src, 'ver=' ) && ! is_user_logged_in() ) {
            $src = remove_query_arg( 'ver', $src );
        }
        return $src;
    }

    /**
     * Restrict SVG uploads strictly to administrators to prevent Stored XSS attacks.
     *
     * @param array $mimes
     * @return array
     */
    public static function restrict_svg_uploads_to_admins( $mimes ) {
        if ( ! current_user_can( 'administrator' ) ) {
            unset( $mimes['svg'], $mimes['svgz'] );
        }
        return $mimes;
    }

    /**
     * Block /wp-json/wp/v2/users endpoint for unauthorized visitors to prevent user enumeration.
     *
     * @param array $endpoints
     * @return array
     */
    public static function block_rest_user_enumeration( $endpoints ) {
        if ( ! is_user_logged_in() || ! current_user_can( 'list_users' ) ) {
            if ( isset( $endpoints['/wp/v2/users'] ) ) {
                unset( $endpoints['/wp/v2/users'] );
            }
            if ( isset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] ) ) {
                unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
            }
        }
        return $endpoints;
    }
}

ThaaniyamHub_Security_Hardening::init();
