<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hayfam_Dashboard_Settings {
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_post_hayfam_dashboard_clear_cache', array( __CLASS__, 'clear_cache' ) );
	}

	public static function defaults() {
		return array(
			'source_url'    => '',
			'default_sheet' => 'WebsiteData',
			'cache_ttl'     => 300,
			'debug'         => 0,
		);
	}

	public static function get_all() {
		return wp_parse_args( get_option( HAYFAM_DASHBOARD_SETTINGS_OPTION, array() ), self::defaults() );
	}

	public static function register_settings() {
		register_setting( 'hayfam_dashboard_settings_group', HAYFAM_DASHBOARD_SETTINGS_OPTION, array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ), 'default' => self::defaults() ) );

		add_settings_section( 'hayfam_dashboard_source_section', __( 'Google Sheet source', 'dashboard-plugin' ), function () {
			echo '<p>' . esc_html__( 'Use a published, non-sensitive Google Sheet or output tab. This shortcode plugin does not require Elementor or Google credentials.', 'dashboard-plugin' ) . '</p>';
		}, 'hayfam-dashboard-plugin' );

		add_settings_field( 'source_url', __( 'Published sheet URL', 'dashboard-plugin' ), array( __CLASS__, 'text_field' ), 'hayfam-dashboard-plugin', 'hayfam_dashboard_source_section', array( 'key' => 'source_url', 'placeholder' => 'https://docs.google.com/spreadsheets/d/.../pub' ) );
		add_settings_field( 'default_sheet', __( 'Default worksheet/tab', 'dashboard-plugin' ), array( __CLASS__, 'text_field' ), 'hayfam-dashboard-plugin', 'hayfam_dashboard_source_section', array( 'key' => 'default_sheet' ) );
		add_settings_field( 'cache_ttl', __( 'Cache duration (seconds)', 'dashboard-plugin' ), array( __CLASS__, 'number_field' ), 'hayfam-dashboard-plugin', 'hayfam_dashboard_source_section', array( 'key' => 'cache_ttl', 'min' => 60 ) );
		add_settings_field( 'debug', __( 'Debug logging', 'dashboard-plugin' ), array( __CLASS__, 'checkbox_field' ), 'hayfam-dashboard-plugin', 'hayfam_dashboard_source_section', array( 'key' => 'debug' ) );
	}

	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();
		$url      = isset( $input['source_url'] ) ? esc_url_raw( trim( $input['source_url'] ) ) : '';

		if ( $url && ! Hayfam_Dashboard_Sheets_Client::is_supported_url( $url ) ) {
			add_settings_error( HAYFAM_DASHBOARD_SETTINGS_OPTION, 'invalid_url', __( 'The published sheet URL must be a Google Sheets URL.', 'dashboard-plugin' ) );
			$url = '';
		}

		return array(
			'source_url'    => $url,
			'default_sheet' => isset( $input['default_sheet'] ) ? sanitize_text_field( $input['default_sheet'] ) : $defaults['default_sheet'],
			'cache_ttl'     => max( 60, absint( $input['cache_ttl'] ?? $defaults['cache_ttl'] ) ),
			'debug'         => empty( $input['debug'] ) ? 0 : 1,
		);
	}

	public static function register_menu() {
		add_options_page( __( 'Dashboard Plugin', 'dashboard-plugin' ), __( 'Dashboard Plugin', 'dashboard-plugin' ), 'manage_options', 'hayfam-dashboard-plugin', array( __CLASS__, 'render_page' ) );
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Dashboard Plugin v2', 'dashboard-plugin' ); ?></h1>
			<p><?php echo esc_html__( 'Configure the default public Google Sheet used by the dashboard_metric shortcode.', 'dashboard-plugin' ); ?></p>
			<p><code>[dashboard_metric cell="B2" before="So far we have saved" suffix=" KG" after="Of food from landfill"]</code></p>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'hayfam_dashboard_settings_group' );
				do_settings_sections( 'hayfam-dashboard-plugin' );
				submit_button();
				?>
			</form>
			<hr>
			<h2><?php echo esc_html__( 'Cache', 'dashboard-plugin' ); ?></h2>
			<p><?php echo esc_html__( 'Clear cached values after making an important change to the source sheet.', 'dashboard-plugin' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="hayfam_dashboard_clear_cache">
				<?php wp_nonce_field( 'hayfam_dashboard_clear_cache' ); ?>
				<?php submit_button( __( 'Clear cached values', 'dashboard-plugin' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	public static function clear_cache() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'dashboard-plugin' ) );
		}

		check_admin_referer( 'hayfam_dashboard_clear_cache' );
		Hayfam_Dashboard_Cache::clear();
		wp_safe_redirect( add_query_arg( array( 'page' => 'hayfam-dashboard-plugin', 'cache_cleared' => 1 ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	public static function text_field( $args ) {
		$settings = self::get_all();
		$key      = $args['key'];
		printf( '<input class="regular-text" type="text" name="%s[%s]" value="%s" placeholder="%s">', esc_attr( HAYFAM_DASHBOARD_SETTINGS_OPTION ), esc_attr( $key ), esc_attr( $settings[ $key ] ), esc_attr( $args['placeholder'] ?? '' ) );
	}

	public static function number_field( $args ) {
		$settings = self::get_all();
		$key      = $args['key'];
		printf( '<input type="number" name="%s[%s]" value="%d" min="%d">', esc_attr( HAYFAM_DASHBOARD_SETTINGS_OPTION ), esc_attr( $key ), absint( $settings[ $key ] ), absint( $args['min'] ?? 0 ) );
	}

	public static function checkbox_field( $args ) {
		$settings = self::get_all();
		$key      = $args['key'];
		printf( '<label><input type="checkbox" name="%s[%s]" value="1" %s> %s</label>', esc_attr( HAYFAM_DASHBOARD_SETTINGS_OPTION ), esc_attr( $key ), checked( ! empty( $settings[ $key ] ), true, false ), esc_html__( 'Write diagnostic messages when WP_DEBUG is enabled.', 'dashboard-plugin' ) );
	}
}

