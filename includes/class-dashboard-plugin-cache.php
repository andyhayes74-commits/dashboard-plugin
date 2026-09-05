<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hayfam_Dashboard_Cache {
	const VERSION_OPTION = 'hayfam_dashboard_cache_version';

	public static function get_version() {
		$version = get_option( self::VERSION_OPTION );

		if ( ! $version ) {
			$version = '1';
			add_option( self::VERSION_OPTION, $version, '', false );
		}

		return (string) $version;
	}

	public static function key( $source_url, $sheet, $cell ) {
		$raw = self::get_version() . '|' . $source_url . '|' . $sheet . '|' . $cell;
		return 'hayfam_dashboard_value_' . md5( $raw );
	}

	public static function get( $source_url, $sheet, $cell ) {
		return get_transient( self::key( $source_url, $sheet, $cell ) );
	}

	public static function set( $source_url, $sheet, $cell, $value, $ttl ) {
		return set_transient( self::key( $source_url, $sheet, $cell ), $value, max( 60, absint( $ttl ) ) );
	}

	public static function clear() {
		$version = (int) self::get_version();
		update_option( self::VERSION_OPTION, (string) ( $version + 1 ), false );
	}
}

