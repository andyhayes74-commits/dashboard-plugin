<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

/**
 * Elementor widget that displays before text, a live value, and after text.
 */
class DP_Dashboard_Metric_Widget extends Widget_Base {
	public function get_name() {
		return 'dashboard_metric';
	}

	public function get_title() {
		return esc_html__( 'Dashboard Metric', 'dashboard-plugin' );
	}

	public function get_icon() {
		return 'eicon-counter';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_style_depends() {
		return array( 'dashboard-plugin' );
	}

	protected function register_controls() {
		$this->start_controls_section( 'content_section', array( 'label' => esc_html__( 'Content', 'dashboard-plugin' ) ) );

		$this->add_control( 'before_text', array(
			'label'       => esc_html__( 'Before text', 'dashboard-plugin' ),
			'type'        => Controls_Manager::TEXTAREA,
			'default'     => esc_html__( 'So far we have saved', 'dashboard-plugin' ),
			'placeholder' => esc_html__( 'Text shown above the live value', 'dashboard-plugin' ),
			'dynamic'     => array( 'active' => true ),
		) );

		$this->add_control( 'after_text', array(
			'label'       => esc_html__( 'After text', 'dashboard-plugin' ),
			'type'        => Controls_Manager::TEXTAREA,
			'default'     => esc_html__( 'Of food from landfill', 'dashboard-plugin' ),
			'placeholder' => esc_html__( 'Text shown below the live value', 'dashboard-plugin' ),
			'dynamic'     => array( 'active' => true ),
		) );

		$this->end_controls_section();

		$this->start_controls_section( 'source_section', array( 'label' => esc_html__( 'Google Sheet', 'dashboard-plugin' ) ) );

		$this->add_control( 'source_url', array(
			'label'       => esc_html__( 'Published sheet URL override', 'dashboard-plugin' ),
			'type'        => Controls_Manager::URL,
			'placeholder' => 'https://docs.google.com/spreadsheets/d/.../pub',
			'description' => esc_html__( 'Leave blank to use the URL in Settings → Dashboard Plugin.', 'dashboard-plugin' ),
			'show_external' => false,
		) );

		$this->add_control( 'sheet', array(
			'label'       => esc_html__( 'Worksheet/tab', 'dashboard-plugin' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => '',
			'placeholder' => 'WebsiteData',
		) );

		$this->add_control( 'cell', array(
			'label'       => esc_html__( 'Cell reference', 'dashboard-plugin' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => 'B2',
			'placeholder' => 'B2',
		) );

		$this->add_control( 'fallback', array(
			'label'       => esc_html__( 'Fallback message', 'dashboard-plugin' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => esc_html__( 'Data currently unavailable', 'dashboard-plugin' ),
		) );

		$this->end_controls_section();

		$this->start_controls_section( 'format_section', array( 'label' => esc_html__( 'Value formatting', 'dashboard-plugin' ) ) );

		$this->add_control( 'prefix', array( 'label' => esc_html__( 'Prefix', 'dashboard-plugin' ), 'type' => Controls_Manager::TEXT, 'default' => '' ) );
		$this->add_control( 'suffix', array( 'label' => esc_html__( 'Suffix', 'dashboard-plugin' ), 'type' => Controls_Manager::TEXT, 'default' => ' KG' ) );
		$this->add_control( 'decimal_places', array( 'label' => esc_html__( 'Decimal places', 'dashboard-plugin' ), 'type' => Controls_Manager::NUMBER, 'min' => -1, 'max' => 10, 'step' => 1, 'default' => -1, 'description' => esc_html__( '-1 preserves the source value.', 'dashboard-plugin' ) ) );
		$this->add_control( 'thousands_separator', array( 'label' => esc_html__( 'Thousands separator', 'dashboard-plugin' ), 'type' => Controls_Manager::TEXT, 'default' => ',' ) );
		$this->add_control( 'decimal_separator', array( 'label' => esc_html__( 'Decimal separator', 'dashboard-plugin' ), 'type' => Controls_Manager::TEXT, 'default' => '.' ) );

		$this->end_controls_section();

		$this->add_style_controls( 'before', esc_html__( 'Before text', 'dashboard-plugin' ), '.dashboard-metric__before' );
		$this->add_style_controls( 'value', esc_html__( 'Live value', 'dashboard-plugin' ), '.dashboard-metric__value' );
		$this->add_style_controls( 'after', esc_html__( 'After text', 'dashboard-plugin' ), '.dashboard-metric__after' );

		$this->start_controls_section( 'container_style', array( 'label' => esc_html__( 'Container', 'dashboard-plugin' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control( 'alignment', array( 'label' => esc_html__( 'Alignment', 'dashboard-plugin' ), 'type' => Controls_Manager::CHOOSE, 'options' => array( 'left' => array( 'title' => esc_html__( 'Left', 'dashboard-plugin' ), 'icon' => 'eicon-text-align-left' ), 'center' => array( 'title' => esc_html__( 'Center', 'dashboard-plugin' ), 'icon' => 'eicon-text-align-center' ), 'right' => array( 'title' => esc_html__( 'Right', 'dashboard-plugin' ), 'icon' => 'eicon-text-align-right' ) ), 'selectors' => array( '{{WRAPPER}} .dashboard-metric' => 'text-align: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'line_gap', array( 'label' => esc_html__( 'Line gap', 'dashboard-plugin' ), 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px', 'em', 'rem' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 100 ), 'em' => array( 'min' => 0, 'max' => 10 ), 'rem' => array( 'min' => 0, 'max' => 10 ) ), 'selectors' => array( '{{WRAPPER}} .dashboard-metric' => 'gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'padding', array( 'label' => esc_html__( 'Padding', 'dashboard-plugin' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', '%', 'em', 'rem' ), 'selectors' => array( '{{WRAPPER}} .dashboard-metric' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_group_control( Group_Control_Background::get_type(), array( 'name' => 'background', 'selector' => '{{WRAPPER}} .dashboard-metric' ) );
		$this->add_group_control( Group_Control_Border::get_type(), array( 'name' => 'border', 'selector' => '{{WRAPPER}} .dashboard-metric' ) );
		$this->add_control( 'border_radius', array( 'label' => esc_html__( 'Border radius', 'dashboard-plugin' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', '%', 'em' ), 'selectors' => array( '{{WRAPPER}} .dashboard-metric' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_group_control( Group_Control_Box_Shadow::get_type(), array( 'name' => 'box_shadow', 'selector' => '{{WRAPPER}} .dashboard-metric' ) );
		$this->end_controls_section();
	}

	private function add_style_controls( $key, $label, $selector ) {
		$this->start_controls_section( $key . '_style', array( 'label' => $label, 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => $key . '_typography', 'selector' => '{{WRAPPER}} ' . $selector ) );
		$this->add_control( $key . '_color', array( 'label' => esc_html__( 'Text colour', 'dashboard-plugin' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} ' . $selector => 'color: {{VALUE}};' ) ) );
		$this->add_responsive_control( $key . '_margin', array( 'label' => esc_html__( 'Margin', 'dashboard-plugin' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', '%', 'em', 'rem' ), 'selectors' => array( '{{WRAPPER}} ' . $selector => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$defaults = DP_Settings::get_all();
		$source   = ! empty( $settings['source_url']['url'] ) ? $settings['source_url']['url'] : $defaults['source_url'];
		$sheet    = ! empty( $settings['sheet'] ) ? $settings['sheet'] : $defaults['default_sheet'];
		$cell     = ! empty( $settings['cell'] ) ? $settings['cell'] : 'B2';
		$ttl      = absint( $defaults['cache_ttl'] );

		$result = array( 'success' => false );
		if ( $source ) {
			$result = ( new DP_Sheets_Client() )->get_value( $source, $sheet, $cell, $ttl );
		}

		$is_editor = \Elementor\Plugin::$instance->editor->is_edit_mode();
		$has_value = $result['success'] || ( $is_editor && ! $source );
		$value     = $has_value ? ( $result['success'] ? $result['value'] : '194' ) : ( $settings['fallback'] ?? '' );
		$value     = $has_value ? $this->format_value( $value, $settings ) : trim( wp_strip_all_tags( (string) $value ) );
		$prefix    = $has_value ? ( $settings['prefix'] ?? '' ) : '';
		$suffix    = $has_value ? ( $settings['suffix'] ?? '' ) : '';

		echo '<div class="dashboard-metric">';
		echo '<div class="dashboard-metric__before">' . $this->format_text( $settings['before_text'] ?? '' ) . '</div>';
		echo '<div class="dashboard-metric__value">' . esc_html( $prefix ) . esc_html( $value ) . esc_html( $suffix ) . '</div>';
		echo '<div class="dashboard-metric__after">' . $this->format_text( $settings['after_text'] ?? '' ) . '</div>';
		echo '</div>';
	}

	private function format_value( $value, $settings ) {
		$raw = trim( wp_strip_all_tags( (string) $value ) );
		$decimals = isset( $settings['decimal_places'] ) ? (int) $settings['decimal_places'] : -1;

		if ( -1 === $decimals || ! is_numeric( str_replace( ',', '', $raw ) ) ) {
			return $raw;
		}

		$thousands = isset( $settings['thousands_separator'] ) ? (string) $settings['thousands_separator'] : ',';
		$decimal   = isset( $settings['decimal_separator'] ) ? (string) $settings['decimal_separator'] : '.';

		return number_format( (float) str_replace( ',', '', $raw ), max( 0, $decimals ), $decimal, $thousands );
	}

	private function format_text( $text ) {
		return nl2br( esc_html( (string) $text ) );
	}
}
