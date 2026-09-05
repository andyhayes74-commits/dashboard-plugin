<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hayfam_Dashboard_Sheets_Client {
	public function get_value( $source_url, $sheet, $cell ) {
		$source_url = esc_url_raw( trim( (string) $source_url ) );
		$sheet      = sanitize_text_field( (string) $sheet );
		$cell       = strtoupper( preg_replace( '/\s+/', '', (string) $cell ) );

		if ( ! $source_url || ! self::is_supported_url( $source_url ) ) {
			return $this->failure( 'invalid_source' );
		}

		if ( ! preg_match( '/^[A-Z]+[1-9][0-9]*$/', $cell ) ) {
			return $this->failure( 'invalid_cell' );
		}

		$response = wp_safe_remote_get(
			$this->build_request_url( $source_url, $sheet, $cell ),
			array(
				'timeout'     => 10,
				'redirection' => 3,
				'headers'     => array(
					'Accept'        => 'text/csv,text/plain;q=0.9,*/*;q=0.8',
					'Cache-Control' => 'no-cache',
					'Pragma'        => 'no-cache',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log( 'Google Sheets request failed: ' . $response->get_error_message() );
			return $this->failure( 'request_failed' );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );

		if ( $status < 200 || $status >= 300 || '' === trim( $body ) ) {
			$this->log( 'Google Sheets returned HTTP status ' . $status );
			return $this->failure( 'empty_response' );
		}

		$value = $this->parse_csv_value( $body, $cell );
		if ( null === $value || '' === trim( (string) $value ) ) {
			$this->log( 'Could not find a value in cell ' . $cell . '.' );
			return $this->failure( null === $value ? 'value_not_found' : 'empty_value' );
		}

		$result = array(
			'success'    => true,
			'value'      => $value,
			'fetched_at' => current_time( 'timestamp', true ),
			'cached'     => false,
		);

		return $result;
	}

	public static function is_supported_url( $url ) {
		$parts = wp_parse_url( $url );
		$host  = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
		$path  = isset( $parts['path'] ) ? $parts['path'] : '';
		$hosts = array( 'docs.google.com', 'docs.googleusercontent.com', 'spreadsheets.google.com' );

		foreach ( $hosts as $allowed ) {
			if ( ( $host === $allowed || substr( $host, -strlen( '.' . $allowed ) ) === '.' . $allowed ) && false !== strpos( $path, '/spreadsheets/' ) ) {
				return true;
			}
		}

		return false;
	}

	private function build_request_url( $source_url, $sheet, $cell ) {
		$args = array(
			'output'         => 'csv',
			'range'          => $cell,
			'_hayfam_refresh' => microtime( true ),
		);

		if ( $sheet ) {
			$args['sheet'] = $sheet;
		}

		return add_query_arg( $args, $source_url );
	}

	private function parse_csv_value( $body, $cell ) {
		$body  = preg_replace( '/^\xEF\xBB\xBF/', '', $body );
		$lines = preg_split( "/\r\n|\n|\r/", trim( $body ) );

		if ( count( $lines ) === 1 ) {
			$row = str_getcsv( $lines[0] );
			return isset( $row[0] ) ? trim( $row[0] ) : null;
		}

		preg_match( '/^([A-Z]+)([1-9][0-9]*)$/', $cell, $matches );
		$column    = $this->column_to_index( $matches[1] );
		$row_index = absint( $matches[2] ) - 1;

		if ( ! isset( $lines[ $row_index ] ) ) {
			return null;
		}

		$row = str_getcsv( $lines[ $row_index ] );
		return isset( $row[ $column ] ) ? trim( $row[ $column ] ) : null;
	}

	private function column_to_index( $letters ) {
		$index = 0;

		foreach ( str_split( $letters ) as $letter ) {
			$index = ( $index * 26 ) + ( ord( $letter ) - 64 );
		}

		return $index - 1;
	}

	private function failure( $code ) {
		return array(
			'success' => false,
			'error'   => sanitize_key( $code ),
		);
	}

	private function log( $message ) {
		$settings = get_option( HAYFAM_DASHBOARD_SETTINGS_OPTION, array() );

		if ( ! empty( $settings['debug'] ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Hayfam Dashboard Plugin] ' . $message );
		}
	}
}
