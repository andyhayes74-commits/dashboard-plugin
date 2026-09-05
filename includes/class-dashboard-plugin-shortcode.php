<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hayfam_Dashboard_Shortcode {
	public static function init() {
		add_shortcode( 'dashboard_metric', array( __CLASS__, 'render' ) );

		foreach ( Hayfam_Dashboard_Settings::get_dashboards() as $dashboard_id => $dashboard ) {
			if ( empty( $dashboard['shortcode'] ) || 'dashboard_metric' === $dashboard['shortcode'] ) {
				continue;
			}

			add_shortcode(
				$dashboard['shortcode'],
				function ( $attributes ) use ( $dashboard_id ) {
					return self::render_dashboard( $dashboard_id, $attributes );
				}
			);
		}
	}

	public static function render( $attributes = array() ) {
		$dashboard_id = '';

		if ( isset( $attributes['dashboard'] ) ) {
			$dashboard_id = $attributes['dashboard'];
		} elseif ( isset( $attributes['id'] ) ) {
			$dashboard_id = $attributes['id'];
		}

		return self::render_dashboard( $dashboard_id, $attributes );
	}

	public static function render_preview( $dashboard_id ) {
		return self::render_dashboard( $dashboard_id, array() );
	}

	private static function render_dashboard( $dashboard_id, $attributes ) {
		$dashboard = Hayfam_Dashboard_Settings::get_dashboard( $dashboard_id );
		if ( ! $dashboard ) {
			return '';
		}

		$attributes = shortcode_atts(
			array(
				'source_url' => $dashboard['source_url'],
				'sheet'      => $dashboard['sheet'],
				'cell'       => $dashboard['cell'],
				'before'     => $dashboard['before'],
				'after'      => $dashboard['after'],
				'prefix'     => $dashboard['prefix'],
				'suffix'     => $dashboard['suffix'],
				'override'   => $dashboard['override'],
				'decimals'   => $dashboard['decimals'],
				'thousands'  => $dashboard['thousands'],
				'decimal'    => $dashboard['decimal'],
				'fallback'   => $dashboard['fallback'],
				'class'      => $dashboard['class'],
			),
			$attributes,
			'dashboard_metric'
		);

		$source = esc_url_raw( $attributes['source_url'] );
		$sheet  = sanitize_text_field( $attributes['sheet'] );
		$cell   = strtoupper( preg_replace( '/\s+/', '', sanitize_text_field( $attributes['cell'] ) ) );
		$ttl    = absint( $dashboard['cache_ttl'] );
		$override = sanitize_text_field( $attributes['override'] );
		$result   = array( 'success' => false );

		if ( '' !== trim( $override ) ) {
			$result = array(
				'success' => true,
				'value'   => $override,
			);
		} elseif ( $source ) {
			$result = ( new Hayfam_Dashboard_Sheets_Client() )->get_value( $source, $sheet, $cell, $ttl );
		}

		$has_value = ! empty( $result['success'] );
		$value     = $has_value ? self::format_value( $result['value'], $attributes ) : sanitize_text_field( $attributes['fallback'] );
		$prefix    = $has_value ? sanitize_text_field( $attributes['prefix'] ) : '';
		$suffix    = $has_value ? sanitize_text_field( $attributes['suffix'] ) : '';
		$classes   = self::classes( $attributes['class'] );
		$styles    = self::styles( $dashboard );

		wp_enqueue_style( 'hayfam-dashboard-plugin' );

		$output  = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '"' . ( $styles ? ' style="' . esc_attr( $styles ) . '"' : '' ) . '>';
		$output .= '<div class="hayfam-dashboard-metric__before">' . self::text( $attributes['before'] ) . '</div>';
		$output .= '<div class="hayfam-dashboard-metric__value">' . esc_html( $prefix . $value . $suffix ) . '</div>';
		$output .= '<div class="hayfam-dashboard-metric__after">' . self::text( $attributes['after'] ) . '</div>';
		$output .= '</div>';

		return $output;
	}

	private static function format_value( $value, $attributes ) {
		$raw      = trim( wp_strip_all_tags( (string) $value ) );
		$decimals = (int) $attributes['decimals'];

		if ( -1 === $decimals || ! is_numeric( str_replace( ',', '', $raw ) ) ) {
			return $raw;
		}

		$thousands = substr( sanitize_text_field( $attributes['thousands'] ), 0, 1 );
		$decimal   = substr( sanitize_text_field( $attributes['decimal'] ), 0, 1 );

		return number_format( (float) str_replace( ',', '', $raw ), max( 0, $decimals ), $decimal, $thousands );
	}

	private static function text( $value ) {
		return nl2br( esc_html( sanitize_textarea_field( (string) $value ) ) );
	}

	private static function classes( $custom ) {
		$classes = array( 'hayfam-dashboard-metric' );
		$custom  = preg_split( '/\s+/', sanitize_text_field( (string) $custom ), -1, PREG_SPLIT_NO_EMPTY );

		foreach ( $custom as $class ) {
			$sanitized = sanitize_html_class( $class );
			if ( $sanitized ) {
				$classes[] = $sanitized;
			}
		}

		return array_unique( $classes );
	}

	private static function styles( $dashboard ) {
		$font_families = array(
			'inherit'   => 'inherit',
			'system'    => 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
			'arial'     => 'Arial, Helvetica, sans-serif',
			'georgia'   => 'Georgia, "Times New Roman", serif',
			'courier'   => '"Courier New", Courier, monospace',
			'trebuchet' => '"Trebuchet MS", Arial, sans-serif',
			'verdana'   => 'Verdana, Arial, sans-serif',
		);
		$styles = array();

		if ( isset( $font_families[ $dashboard['font_family'] ] ) && 'inherit' !== $dashboard['font_family'] ) {
			$styles['--hayfam-dashboard-font-family'] = $font_families[ $dashboard['font_family'] ];
		}

		$properties = array(
			'font_size'         => '--hayfam-dashboard-font-size',
			'value_font_size'   => '--hayfam-dashboard-value-font-size',
			'font_weight'       => '--hayfam-dashboard-font-weight',
			'value_font_weight' => '--hayfam-dashboard-value-font-weight',
			'line_height'       => '--hayfam-dashboard-line-height',
			'text_align'        => '--hayfam-dashboard-text-align',
			'before_color'      => '--hayfam-dashboard-before-color',
			'value_color'       => '--hayfam-dashboard-value-color',
			'after_color'       => '--hayfam-dashboard-after-color',
			'background_color'  => '--hayfam-dashboard-background-color',
			'gap'               => '--hayfam-dashboard-gap',
			'padding'           => '--hayfam-dashboard-padding',
			'border_radius'     => '--hayfam-dashboard-border-radius',
		);

		foreach ( $properties as $setting => $property ) {
			if ( isset( $dashboard[ $setting ] ) && '' !== (string) $dashboard[ $setting ] ) {
				$styles[ $property ] = sanitize_text_field( $dashboard[ $setting ] );
			}
		}

		$output = array();
		foreach ( $styles as $property => $value ) {
			$output[] = $property . ':' . $value;
		}

		return implode( ';', $output );
	}
}
