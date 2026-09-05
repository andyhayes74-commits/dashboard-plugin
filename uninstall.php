<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'dp_settings' );
delete_option( 'dp_cache_version' );

