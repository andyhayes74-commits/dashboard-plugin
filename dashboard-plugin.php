<?php
/**
 * Plugin Name: Dashboard Plugin
 * Description: Adds an Elementor widget that displays a value from a published Google Sheet between two styled text blocks.
 * Version: 0.1.0
 * Author: Andy Hayes
 * License: GPL-2.0-or-later
 * Text Domain: dashboard-plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DP_VERSION', '0.1.0' );
define( 'DP_FILE', __FILE__ );
define( 'DP_PATH', plugin_dir_path( __FILE__ ) );
define( 'DP_URL', plugin_dir_url( __FILE__ ) );

require_once DP_PATH . 'includes/class-dashboard-plugin-cache.php';
require_once DP_PATH . 'includes/class-dashboard-plugin-sheets-client.php';
require_once DP_PATH . 'includes/class-dashboard-plugin-settings.php';

/**
 * Start the plugin after WordPress has loaded all active plugins.
 */
function dp_boot_plugin() {
	DP_Settings::init();

	if ( did_action( 'elementor/loaded' ) ) {
		dp_boot_elementor();
	} else {
		add_action( 'elementor/loaded', 'dp_boot_elementor' );
	}
}
add_action( 'plugins_loaded', 'dp_boot_plugin' );

/**
 * Register the Elementor integration only when Elementor is available.
 */
function dp_boot_elementor() {
	if ( ! did_action( 'elementor/loaded' ) || ! class_exists( '\Elementor\Plugin' ) ) {
		return;
	}

	require_once DP_PATH . 'includes/class-dashboard-plugin-elementor.php';
	require_once DP_PATH . 'widgets/class-dashboard-metric-widget.php';

	DP_Elementor_Integration::init();
}

/**
 * Register frontend assets used by the widget.
 */
function dp_register_assets() {
	wp_register_style(
		'dashboard-plugin',
		DP_URL . 'assets/css/dashboard-plugin.css',
		array(),
		DP_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'dp_register_assets' );
add_action( 'elementor/editor/before_enqueue_scripts', 'dp_register_assets' );

/**
 * Show a clear admin notice when the widget dependency is missing.
 */
function dp_elementor_dependency_notice() {
	if ( ! current_user_can( 'activate_plugins' ) || class_exists( '\Elementor\Plugin' ) ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && 'plugins' !== $screen->id ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html__( 'Dashboard Plugin is active, but Elementor is not installed or active. The Dashboard Metric widget is unavailable until Elementor is activated.', 'dashboard-plugin' )
	);
}
add_action( 'admin_notices', 'dp_elementor_dependency_notice' );

