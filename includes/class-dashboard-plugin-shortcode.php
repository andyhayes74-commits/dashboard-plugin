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

		$output  = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '"' . self::style_attribute( $styles ) . '>';
		$output .= '<div class="hayfam-dashboard-metric__before"' . self::style_attribute( self::element_styles( $dashboard, 'before' ) ) . '>' . self::text( $attributes['before'] ) . '</div>';
		$output .= '<div class="hayfam-dashboard-metric__value"' . self::style_attribute( self::element_styles( $dashboard, 'value' ) ) . '>' . esc_html( $prefix . $value . $suffix ) . '</div>';
		$output .= '<div class="hayfam-dashboard-metric__after"' . self::style_attribute( self::element_styles( $dashboard, 'after' ) ) . '>' . self::text( $attributes['after'] ) . '</div>';
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
		$styles = array(
			'display'        => 'flex',
			'flex-direction' => 'column',
			'align-items'    => 'stretch',
			'box-sizing'     => 'border-box',
		);

		if ( isset( $font_families[ $dashboard['font_family'] ] ) && 'inherit' !== $dashboard['font_family'] ) {
			$styles['--hayfam-dashboard-font-family'] = $font_families[ $dashboard['font_family'] ];
			$styles['font-family'] = $font_families[ $dashboard['font_family'] ] . ' !important';
		}

		$properties = array(
			'font_size'         => array( '--hayfam-dashboard-font-size', 'font-size' ),
			'font_weight'       => array( '--hayfam-dashboard-font-weight', 'font-weight' ),
			'line_height'       => array( '--hayfam-dashboard-line-height', 'line-height' ),
			'text_align'        => array( '--hayfam-dashboard-text-align', 'text-align' ),
			'background_color'  => array( '--hayfam-dashboard-background-color', 'background-color' ),
			'gap'               => array( '--hayfam-dashboard-gap', 'gap' ),
			'padding'           => array( '--hayfam-dashboard-padding', 'padding' ),
			'border_radius'     => array( '--hayfam-dashboard-border-radius', 'border-radius' ),
		);

		foreach ( $properties as $setting => $property_names ) {
			if ( isset( $dashboard[ $setting ] ) && '' !== (string) $dashboard[ $setting ] ) {
				$value = sanitize_text_field( $dashboard[ $setting ] );
				$styles[ $property_names[0] ] = $value;
				$styles[ $property_names[1] ] = $value . ' !important';
			}
		}

		return $styles;
	}

	private static function element_styles( $dashboard, $element ) {
		$styles = array();
		$font_families = array(
			'inherit'   => 'inherit',
			'system'    => 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
			'arial'     => 'Arial, Helvetica, sans-serif',
			'georgia'   => 'Georgia, "Times New Roman", serif',
			'courier'   => '"Courier New", Courier, monospace',
			'trebuchet' => '"Trebuchet MS", Arial, sans-serif',
			'verdana'   => 'Verdana, Arial, sans-serif',
		);

		if ( isset( $font_families[ $dashboard['font_family'] ] ) && 'inherit' !== $dashboard['font_family'] ) {
			$styles['font-family'] = $font_families[ $dashboard['font_family'] ] . ' !important';
		}

		if ( ! empty( $dashboard['font_size'] ) ) {
			$styles['font-size'] = sanitize_text_field( $dashboard['font_size'] ) . ' !important';
		}

		if ( ! empty( $dashboard['line_height'] ) ) {
			$styles['line-height'] = sanitize_text_field( $dashboard['line_height'] ) . ' !important';
		}

		$colour_settings = array(
			'before' => 'before_color',
			'value'  => 'value_color',
			'after'  => 'after_color',
		);

		if ( isset( $colour_settings[ $element ], $dashboard[ $colour_settings[ $element ] ] ) && '' !== (string) $dashboard[ $colour_settings[ $element ] ] ) {
			$styles['color'] = sanitize_hex_color( $dashboard[ $colour_settings[ $element ] ] ) . ' !important';
		}

		if ( 'value' === $element ) {
			if ( ! empty( $dashboard['value_font_size'] ) ) {
				$styles['font-size'] = sanitize_text_field( $dashboard['value_font_size'] ) . ' !important';
			}
			if ( ! empty( $dashboard['value_font_weight'] ) ) {
				$styles['font-weight'] = sanitize_text_field( $dashboard['value_font_weight'] ) . ' !important';
			}
		}

		return $styles;
	}

	private static function style_attribute( $styles ) {
		if ( empty( $styles ) || ! is_array( $styles ) ) {
			return '';
		}

		$output = array();
		foreach ( $styles as $property => $value ) {
			if ( '' !== (string) $value ) {
				$output[] = $property . ':' . $value;
			}
		}

		return $output ? ' style="' . esc_attr( implode( ';', $output ) ) . '"' : '';
	}
}
