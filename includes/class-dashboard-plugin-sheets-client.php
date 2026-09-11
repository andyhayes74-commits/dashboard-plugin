<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hayfam_Dashboard_Sheets_Client {
	private const MAX_ATTEMPTS = 3;
	private const RETRY_DELAY_MICROSECONDS = 250000;

	public function get_value( $source_url, $sheet, $cell ) {
		$key    = self::value_key( $source_url, $sheet, $cell );
		$values = $this->get_values(
			array(
				array(
					'source_url' => $source_url,
					'sheet'      => $sheet,
					'cell'       => $cell,
				),
			)
		);

		return isset( $values[ $key ] ) ? $values[ $key ] : $this->failure( 'request_failed' );
	}

	public function get_values( $requests ) {
		$results = array();
		$groups  = array();

		foreach ( $requests as $request ) {
			$normalised = $this->normalise_request( $request );
			$key        = $normalised['key'];

			if ( $normalised['error'] ) {
				$results[ $key ] = $this->failure( $normalised['error'] );
				continue;
			}

			$group_key = md5( $normalised['source_url'] . '|' . $normalised['sheet'] );
			if ( ! isset( $groups[ $group_key ] ) ) {
				$groups[ $group_key ] = array(
					'source_url' => $normalised['source_url'],
					'sheet'      => $normalised['sheet'],
					'cells'      => array(),
				);
			}
			$groups[ $group_key ]['cells'][ $key ] = $normalised['cell'];
		}

		foreach ( $groups as $group ) {
			$response = $this->fetch_sheet( $group['source_url'], $group['sheet'] );

			if ( empty( $response['success'] ) ) {
				foreach ( $group['cells'] as $key => $cell ) {
					$results[ $key ] = $this->failure( $response['error'] );
				}
				continue;
			}

			foreach ( $group['cells'] as $key => $cell ) {
				$value = $this->parse_csv_value( $response['body'], $cell );

				if ( null === $value || '' === trim( (string) $value ) ) {
					$this->log( 'Could not find a value in cell ' . $cell . '.' );
					$results[ $key ] = $this->failure( null === $value ? 'value_not_found' : 'empty_value' );
				} elseif ( $this->is_spreadsheet_error( $value ) ) {
					$this->log( 'Google Sheets returned ' . $value . ' for cell ' . $cell . '.' );
					$results[ $key ] = $this->failure( 'spreadsheet_error' );
				} else {
					$results[ $key ] = array(
						'success'    => true,
						'value'      => $value,
						'fetched_at' => current_time( 'timestamp', true ),
						'cached'     => false,
					);
				}
			}
		}

		return $results;
	}

	public static function value_key( $source_url, $sheet, $cell ) {
		$source_url = esc_url_raw( trim( (string) $source_url ) );
		$sheet      = sanitize_text_field( (string) $sheet );
		$cell       = strtoupper( preg_replace( '/\s+/', '', (string) $cell ) );

		return md5( $source_url . '|' . $sheet . '|' . $cell );
	}

	private function normalise_request( $request ) {
		$source_url = isset( $request['source_url'] ) ? esc_url_raw( trim( (string) $request['source_url'] ) ) : '';
		$sheet      = isset( $request['sheet'] ) ? sanitize_text_field( (string) $request['sheet'] ) : '';
		$cell       = isset( $request['cell'] ) ? strtoupper( preg_replace( '/\s+/', '', (string) $request['cell'] ) ) : '';
		$error      = '';

		if ( ! $source_url || ! self::is_supported_url( $source_url ) ) {
			$error = 'invalid_source';
		} elseif ( ! preg_match( '/^[A-Z]+[1-9][0-9]*$/', $cell ) ) {
			$error = 'invalid_cell';
		}

		return array(
			'source_url' => $source_url,
			'sheet'      => $sheet,
			'cell'       => $cell,
			'key'        => self::value_key( $source_url, $sheet, $cell ),
			'error'      => $error,
		);
	}

	private function fetch_sheet( $source_url, $sheet ) {
		$request_url = $this->build_request_url( $source_url, $sheet );
		$last_error  = 'request_failed';

		for ( $attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++ ) {
			$response = wp_safe_remote_get(
				$request_url,
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
				$last_error = 'request_failed';
				$this->log( 'Google Sheets request attempt ' . $attempt . ' failed: ' . $response->get_error_message() );
			} else {
				$status = wp_remote_retrieve_response_code( $response );
				$body   = wp_remote_retrieve_body( $response );

				if ( $status >= 200 && $status < 300 && '' !== trim( $body ) ) {
					return array(
						'success' => true,
						'body'    => $body,
					);
				}

				$last_error = 'empty_response';
				$this->log( 'Google Sheets attempt ' . $attempt . ' returned HTTP status ' . $status . '.' );
			}

			if ( $attempt < self::MAX_ATTEMPTS ) {
				usleep( self::RETRY_DELAY_MICROSECONDS );
			}
		}

		return $this->failure( $last_error );
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

	private function build_request_url( $source_url, $sheet, $cell = '' ) {
		$args = array(
			'output'          => 'csv',
			'_hayfam_refresh' => microtime( true ),
		);

		if ( $cell ) {
			$args['range'] = $cell;
		}

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

	private function is_spreadsheet_error( $value ) {
		return (bool) preg_match( '/^#(?:DIV\/0!|REF!|VALUE!|N\/A|NAME\?|NUM!|NULL!|ERROR!)$/i', trim( (string) $value ) );
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
