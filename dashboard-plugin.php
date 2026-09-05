<?php
/**
 * Plugin Name: Dashboard Plugin
 * Description: Creates multiple configurable dashboard shortcodes backed by published Google Sheets.
 * Version: 2.7.0
 * Author: Andy Hayes
 * License: GPL-2.0-or-later
 * Text Domain: dashboard-plugin
 * Requires at least: 6.4
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HAYFAM_DASHBOARD_VERSION', '2.7.0' );
define( 'HAYFAM_DASHBOARD_FILE', __FILE__ );
define( 'HAYFAM_DASHBOARD_PATH', plugin_dir_path( __FILE__ ) );
define( 'HAYFAM_DASHBOARD_URL', plugin_dir_url( __FILE__ ) );
define( 'HAYFAM_DASHBOARD_SETTINGS_OPTION', 'hayfam_dashboard_settings_v21' );
define( 'HAYFAM_DASHBOARD_LEGACY_OPTION', 'hayfam_dashboard_settings' );

require_once HAYFAM_DASHBOARD_PATH . 'includes/class-dashboard-plugin-cache.php';
require_once HAYFAM_DASHBOARD_PATH . 'includes/class-dashboard-plugin-sheets-client.php';
require_once HAYFAM_DASHBOARD_PATH . 'includes/class-dashboard-plugin-settings.php';
require_once HAYFAM_DASHBOARD_PATH . 'includes/class-dashboard-plugin-shortcode.php';

Hayfam_Dashboard_Settings::init();
Hayfam_Dashboard_Shortcode::init();

function hayfam_dashboard_activate() {
	if ( false === get_option( HAYFAM_DASHBOARD_SETTINGS_OPTION, false ) ) {
		$legacy = get_option( HAYFAM_DASHBOARD_LEGACY_OPTION, false );
		if ( is_array( $legacy ) ) {
			update_option( HAYFAM_DASHBOARD_SETTINGS_OPTION, $legacy );
		}
	}
}
register_activation_hook( HAYFAM_DASHBOARD_FILE, 'hayfam_dashboard_activate' );

function hayfam_dashboard_register_assets() {
	wp_register_style(
		'hayfam-dashboard-plugin',
		HAYFAM_DASHBOARD_URL . 'assets/css/dashboard-plugin.css',
		array(),
		HAYFAM_DASHBOARD_VERSION
	);
}
add_action( 'init', 'hayfam_dashboard_register_assets' );
