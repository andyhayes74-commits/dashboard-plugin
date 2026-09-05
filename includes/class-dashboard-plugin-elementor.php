<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor integration.
 */
class DP_Elementor_Integration {
	public static function init() {
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widgets' ) );
	}

	public static function register_widgets( $widgets_manager ) {
		$widgets_manager->register( new DP_Dashboard_Metric_Widget() );
	}
}

