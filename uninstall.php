<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'hayfam_dashboard_settings_v21' );
delete_option( 'hayfam_dashboard_cache_version' );
// Remove options left by the pre-v2 Elementor builds.
delete_option( 'dp_settings' );
delete_option( 'dp_cache_version' );
