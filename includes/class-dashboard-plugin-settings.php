<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hayfam_Dashboard_Settings {
	const PAGE_SLUG = 'hayfam-dashboard-plugin';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_post_hayfam_dashboard_save', array( __CLASS__, 'save_dashboard' ) );
		add_action( 'admin_post_hayfam_dashboard_add', array( __CLASS__, 'add_dashboard' ) );
		add_action( 'admin_post_hayfam_dashboard_delete', array( __CLASS__, 'delete_dashboard' ) );
		add_action( 'admin_post_hayfam_dashboard_clear_cache', array( __CLASS__, 'clear_cache' ) );
	}

	public static function defaults() {
		return array(
			'source_url'    => '',
			'default_sheet' => 'WebsiteData',
			'cache_ttl'     => 300,
			'debug'         => 0,
			'dashboards'    => array(),
		);
	}

	public static function dashboard_defaults( $id = 'dashboard', $label = 'Dashboard' ) {
		return array(
			'id'        => sanitize_key( $id ),
			'label'     => sanitize_text_field( $label ),
			'shortcode' => 'dashboard_' . sanitize_key( $id ),
			'source_url'=> '',
			'sheet'     => 'WebsiteData',
			'cell'      => 'B2',
			'before'    => 'So far we have saved',
			'after'     => 'Of food from landfill',
			'prefix'    => '',
			'suffix'    => ' KG',
			'decimals'  => '-1',
			'thousands' => ',',
			'decimal'   => '.',
			'fallback'  => 'Data currently unavailable',
			'cache_ttl' => 300,
			'class'     => '',
		);
	}

	public static function get_all() {
		$stored     = get_option( HAYFAM_DASHBOARD_SETTINGS_OPTION, array() );
		$stored     = is_array( $stored ) ? $stored : array();
		$settings   = wp_parse_args( $stored, self::defaults() );
		$dashboards = isset( $stored['dashboards'] ) && is_array( $stored['dashboards'] ) ? $stored['dashboards'] : array();

		// Convert the original v2 single-dashboard settings into the first tab.
		if ( empty( $dashboards ) ) {
			$legacy = self::dashboard_defaults( 'dashboard', 'Dashboard' );
			$legacy['source_url'] = isset( $stored['source_url'] ) ? esc_url_raw( $stored['source_url'] ) : '';
			$legacy['sheet']      = isset( $stored['default_sheet'] ) ? sanitize_text_field( $stored['default_sheet'] ) : $legacy['sheet'];
			$legacy['cache_ttl']  = isset( $stored['cache_ttl'] ) ? max( 60, absint( $stored['cache_ttl'] ) ) : $legacy['cache_ttl'];
			$dashboards           = array( 'dashboard' => $legacy );
		}

		$normalised = array();
		foreach ( $dashboards as $key => $dashboard ) {
			$id = sanitize_key( $key );
			if ( ! $id ) {
				continue;
			}

			$defaults             = self::dashboard_defaults( $id, ucfirst( str_replace( array( '-', '_' ), ' ', $id ) ) );
			$dashboard            = is_array( $dashboard ) ? wp_parse_args( $dashboard, $defaults ) : $defaults;
			$dashboard['id']      = $id;
			$dashboard['label']   = sanitize_text_field( $dashboard['label'] );
			$dashboard['shortcode'] = sanitize_key( $dashboard['shortcode'] );

			if ( ! $dashboard['shortcode'] || 'dashboard_metric' === $dashboard['shortcode'] ) {
				$dashboard['shortcode'] = 'dashboard_' . $id;
			}

			$normalised[ $id ] = $dashboard;
		}

		if ( empty( $normalised ) ) {
			$normalised['dashboard'] = self::dashboard_defaults();
		}

		$settings['source_url']    = esc_url_raw( $settings['source_url'] );
		$settings['default_sheet'] = sanitize_text_field( $settings['default_sheet'] );
		$settings['cache_ttl']     = max( 60, absint( $settings['cache_ttl'] ) );
		$settings['debug']         = empty( $settings['debug'] ) ? 0 : 1;
		$settings['dashboards']    = $normalised;

		return $settings;
	}

	public static function get_dashboards() {
		$settings = self::get_all();
		return $settings['dashboards'];
	}

	public static function get_dashboard( $id = '' ) {
		$dashboards = self::get_dashboards();
		$id         = sanitize_key( $id );

		if ( $id && isset( $dashboards[ $id ] ) ) {
			return $dashboards[ $id ];
		}

		if ( $id ) {
			foreach ( $dashboards as $dashboard ) {
				if ( $id === $dashboard['shortcode'] ) {
					return $dashboard;
				}
			}
		}

		return reset( $dashboards );
	}

	public static function make_id( $label, $existing ) {
		$id     = str_replace( '-', '_', sanitize_title( $label ) );
		$id     = $id ? $id : 'dashboard';
		$base   = $id;
		$number = 2;

		while ( isset( $existing[ $id ] ) ) {
			$id = $base . '_' . $number;
			$number++;
		}

		return $id;
	}

	public static function save_dashboard() {
		self::verify_admin_request( 'hayfam_dashboard_save' );

		$dashboard_id = isset( $_POST['dashboard_id'] ) ? sanitize_key( wp_unslash( $_POST['dashboard_id'] ) ) : '';
		$input        = isset( $_POST['dashboard'] ) && is_array( $_POST['dashboard'] ) ? wp_unslash( $_POST['dashboard'] ) : array();
		$settings     = self::get_all();

		if ( ! $dashboard_id || ! isset( $settings['dashboards'][ $dashboard_id ] ) ) {
			self::redirect( '', 'error' );
		}

		$updated_dashboard = self::sanitize_dashboard( $input, $settings['dashboards'][ $dashboard_id ] );
		$updated_dashboard['shortcode'] = self::unique_shortcode( $updated_dashboard['shortcode'], $dashboard_id, $settings['dashboards'] );
		$settings['dashboards'][ $dashboard_id ] = $updated_dashboard;
		$settings['debug'] = empty( $_POST['debug'] ) ? 0 : 1;
		self::sync_legacy_values( $settings, $dashboard_id );
		update_option( HAYFAM_DASHBOARD_SETTINGS_OPTION, $settings );

		self::redirect( $dashboard_id, 'saved' );
	}

	public static function add_dashboard() {
		self::verify_admin_request( 'hayfam_dashboard_add' );

		$settings = self::get_all();
		$id       = self::make_id( 'New Dashboard', $settings['dashboards'] );
		$settings['dashboards'][ $id ] = self::dashboard_defaults( $id, 'New Dashboard' );
		update_option( HAYFAM_DASHBOARD_SETTINGS_OPTION, $settings );

		self::redirect( $id, 'added' );
	}

	public static function delete_dashboard() {
		self::verify_admin_request( 'hayfam_dashboard_delete' );

		$dashboard_id = isset( $_POST['dashboard_id'] ) ? sanitize_key( wp_unslash( $_POST['dashboard_id'] ) ) : '';
		$settings     = self::get_all();

		if ( count( $settings['dashboards'] ) <= 1 || ! isset( $settings['dashboards'][ $dashboard_id ] ) ) {
			self::redirect( $dashboard_id, 'error' );
		}

		unset( $settings['dashboards'][ $dashboard_id ] );
		$next_id = key( $settings['dashboards'] );
		self::sync_legacy_values( $settings, $next_id );
		update_option( HAYFAM_DASHBOARD_SETTINGS_OPTION, $settings );

		self::redirect( $next_id, 'deleted' );
	}

	public static function clear_cache() {
		self::verify_admin_request( 'hayfam_dashboard_clear_cache' );
		Hayfam_Dashboard_Cache::clear();
		self::redirect( isset( $_POST['dashboard_id'] ) ? sanitize_key( wp_unslash( $_POST['dashboard_id'] ) ) : '', 'cache_cleared' );
	}

	public static function register_menu() {
		add_options_page( __( 'Dashboard Plugin', 'dashboard-plugin' ), __( 'Dashboard Plugin', 'dashboard-plugin' ), 'manage_options', self::PAGE_SLUG, array( __CLASS__, 'render_page' ) );
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings   = self::get_all();
		$dashboards = $settings['dashboards'];
		$current_id = isset( $_GET['dashboard'] ) ? sanitize_key( wp_unslash( $_GET['dashboard'] ) ) : key( $dashboards );
		$current_id = isset( $dashboards[ $current_id ] ) ? $current_id : key( $dashboards );
		$current    = $dashboards[ $current_id ];
		$base_url   = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
		$message    = isset( $_GET['message'] ) ? sanitize_key( wp_unslash( $_GET['message'] ) ) : '';
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Dashboard Plugin v2.1', 'dashboard-plugin' ); ?></h1>
			<p><?php echo esc_html__( 'Create a separate tab and shortcode for each live dashboard metric.', 'dashboard-plugin' ); ?></p>

			<h2 class="nav-tab-wrapper">
				<?php foreach ( $dashboards as $id => $dashboard ) : ?>
					<a class="nav-tab <?php echo $id === $current_id ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'dashboard', $id, $base_url ) ); ?>"><?php echo esc_html( $dashboard['label'] ); ?></a>
				<?php endforeach; ?>
				<a class="nav-tab" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hayfam_dashboard_add' ), 'hayfam_dashboard_add' ) ); ?>">+ <?php echo esc_html__( 'Add dashboard', 'dashboard-plugin' ); ?></a>
			</h2>

			<?php if ( 'saved' === $message ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Dashboard saved.', 'dashboard-plugin' ); ?></p></div><?php endif; ?>
			<?php if ( 'added' === $message ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Dashboard added.', 'dashboard-plugin' ); ?></p></div><?php endif; ?>
			<?php if ( 'deleted' === $message ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Dashboard deleted.', 'dashboard-plugin' ); ?></p></div><?php endif; ?>
			<?php if ( 'cache_cleared' === $message ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Cached dashboard values cleared.', 'dashboard-plugin' ); ?></p></div><?php endif; ?>
			<?php if ( 'error' === $message ) : ?><div class="notice notice-error is-dismissible"><p><?php echo esc_html__( 'The requested dashboard action could not be completed.', 'dashboard-plugin' ); ?></p></div><?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="hayfam_dashboard_save">
				<input type="hidden" name="dashboard_id" value="<?php echo esc_attr( $current_id ); ?>">
				<?php wp_nonce_field( 'hayfam_dashboard_save' ); ?>

				<h2><?php echo esc_html__( 'Dashboard details', 'dashboard-plugin' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="hayfam-dashboard-label"><?php echo esc_html__( 'Dashboard name', 'dashboard-plugin' ); ?></label></th><td><input id="hayfam-dashboard-label" class="regular-text" type="text" name="dashboard[label]" value="<?php echo esc_attr( $current['label'] ); ?>" required><p class="description"><?php echo esc_html__( 'This name appears on the settings tab.', 'dashboard-plugin' ); ?></p></td></tr>
					<tr><th scope="row"><?php echo esc_html__( 'Shortcode', 'dashboard-plugin' ); ?></th><td><code>[<?php echo esc_html( $current['shortcode'] ); ?>]</code><p class="description"><?php echo esc_html__( 'Use this unique shortcode in Elementor, WordPress, or any page builder shortcode block.', 'dashboard-plugin' ); ?></p></td></tr>
					<tr><th scope="row"><label for="hayfam-dashboard-shortcode"><?php echo esc_html__( 'Shortcode name', 'dashboard-plugin' ); ?></label></th><td><input id="hayfam-dashboard-shortcode" class="regular-text" type="text" name="dashboard[shortcode]" value="<?php echo esc_attr( $current['shortcode'] ); ?>" pattern="[A-Za-z0-9_-]+"><p class="description"><?php echo esc_html__( 'Use lowercase letters, numbers, or underscores. The plugin adds the dashboard_ prefix if needed. Avoid changing this after placing the shortcode on a page.', 'dashboard-plugin' ); ?></p></td></tr>
				</table>

				<h2><?php echo esc_html__( 'Google Sheet source', 'dashboard-plugin' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="hayfam-dashboard-source"><?php echo esc_html__( 'Published sheet URL', 'dashboard-plugin' ); ?></label></th><td><input id="hayfam-dashboard-source" class="large-text" type="url" name="dashboard[source_url]" value="<?php echo esc_attr( $current['source_url'] ); ?>" placeholder="https://docs.google.com/spreadsheets/d/.../pub" required><p class="description"><?php echo esc_html__( 'Use a published, non-sensitive Google Sheet or output tab.', 'dashboard-plugin' ); ?></p></td></tr>
					<tr><th scope="row"><label for="hayfam-dashboard-sheet"><?php echo esc_html__( 'Worksheet/tab', 'dashboard-plugin' ); ?></label></th><td><input id="hayfam-dashboard-sheet" class="regular-text" type="text" name="dashboard[sheet]" value="<?php echo esc_attr( $current['sheet'] ); ?>"></td></tr>
					<tr><th scope="row"><label for="hayfam-dashboard-cell"><?php echo esc_html__( 'Cell reference', 'dashboard-plugin' ); ?></label></th><td><input id="hayfam-dashboard-cell" class="small-text" type="text" name="dashboard[cell]" value="<?php echo esc_attr( $current['cell'] ); ?>" required><p class="description"><?php echo esc_html__( 'For example B2 or C5.', 'dashboard-plugin' ); ?></p></td></tr>
				</table>

				<h2><?php echo esc_html__( 'Displayed content', 'dashboard-plugin' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="hayfam-dashboard-before"><?php echo esc_html__( 'Text above value', 'dashboard-plugin' ); ?></label></th><td><textarea id="hayfam-dashboard-before" class="large-text" rows="2" name="dashboard[before]"><?php echo esc_textarea( $current['before'] ); ?></textarea></td></tr>
					<tr><th scope="row"><label for="hayfam-dashboard-after"><?php echo esc_html__( 'Text below value', 'dashboard-plugin' ); ?></label></th><td><textarea id="hayfam-dashboard-after" class="large-text" rows="2" name="dashboard[after]"><?php echo esc_textarea( $current['after'] ); ?></textarea></td></tr>
					<tr><th scope="row"><label for="hayfam-dashboard-prefix"><?php echo esc_html__( 'Value prefix', 'dashboard-plugin' ); ?></label></th><td><input id="hayfam-dashboard-prefix" class="regular-text" type="text" name="dashboard[prefix]" value="<?php echo esc_attr( $current['prefix'] ); ?>"></td></tr>
					<tr><th scope="row"><label for="hayfam-dashboard-suffix"><?php echo esc_html__( 'Value suffix', 'dashboard-plugin' ); ?></label></th><td><input id="hayfam-dashboard-suffix" class="regular-text" type="text" name="dashboard[suffix]" value="<?php echo esc_attr( $current['suffix'] ); ?>"></td></tr>
					<tr><th scope="row"><label for="hayfam-dashboard-fallback"><?php echo esc_html__( 'Fallback message', 'dashboard-plugin' ); ?></label></th><td><input id="hayfam-dashboard-fallback" class="regular-text" type="text" name="dashboard[fallback]" value="<?php echo esc_attr( $current['fallback'] ); ?>"></td></tr>
				</table>

				<h2><?php echo esc_html__( 'Formatting and caching', 'dashboard-plugin' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="hayfam-dashboard-decimals"><?php echo esc_html__( 'Decimal places', 'dashboard-plugin' ); ?></label></th><td><input id="hayfam-dashboard-decimals" class="small-text" type="number" name="dashboard[decimals]" value="<?php echo esc_attr( $current['decimals'] ); ?>" min="-1" max="10"><p class="description"><?php echo esc_html__( '-1 preserves the source value.', 'dashboard-plugin' ); ?></p></td></tr>
					<tr><th scope="row"><label for="hayfam-dashboard-thousands"><?php echo esc_html__( 'Thousands separator', 'dashboard-plugin' ); ?></label></th><td><input id="hayfam-dashboard-thousands" class="small-text" type="text" name="dashboard[thousands]" value="<?php echo esc_attr( $current['thousands'] ); ?>"></td></tr>
					<tr><th scope="row"><label for="hayfam-dashboard-decimal"><?php echo esc_html__( 'Decimal separator', 'dashboard-plugin' ); ?></label></th><td><input id="hayfam-dashboard-decimal" class="small-text" type="text" name="dashboard[decimal]" value="<?php echo esc_attr( $current['decimal'] ); ?>"></td></tr>
					<tr><th scope="row"><label for="hayfam-dashboard-cache"><?php echo esc_html__( 'Cache duration (seconds)', 'dashboard-plugin' ); ?></label></th><td><input id="hayfam-dashboard-cache" class="small-text" type="number" name="dashboard[cache_ttl]" value="<?php echo esc_attr( $current['cache_ttl'] ); ?>" min="60"></td></tr>
					<tr><th scope="row"><label for="hayfam-dashboard-class"><?php echo esc_html__( 'Custom CSS class', 'dashboard-plugin' ); ?></label></th><td><input id="hayfam-dashboard-class" class="regular-text" type="text" name="dashboard[class]" value="<?php echo esc_attr( $current['class'] ); ?>"><p class="description"><?php echo esc_html__( 'Optional class for styling this dashboard separately.', 'dashboard-plugin' ); ?></p></td></tr>
					<tr><th scope="row"><?php echo esc_html__( 'Debug logging', 'dashboard-plugin' ); ?></th><td><label><input type="checkbox" name="debug" value="1" <?php checked( ! empty( $settings['debug'] ), true ); ?>> <?php echo esc_html__( 'Write diagnostic messages when WP_DEBUG is enabled.', 'dashboard-plugin' ); ?></label></td></tr>
				</table>

				<?php submit_button( __( 'Save dashboard', 'dashboard-plugin' ) ); ?>
			</form>

			<hr>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:12px">
				<input type="hidden" name="action" value="hayfam_dashboard_clear_cache">
				<input type="hidden" name="dashboard_id" value="<?php echo esc_attr( $current_id ); ?>">
				<?php wp_nonce_field( 'hayfam_dashboard_clear_cache' ); ?>
				<?php submit_button( __( 'Clear cached values', 'dashboard-plugin' ), 'secondary', 'submit', false ); ?>
			</form>
			<?php if ( count( $dashboards ) > 1 ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block">
					<input type="hidden" name="action" value="hayfam_dashboard_delete">
					<input type="hidden" name="dashboard_id" value="<?php echo esc_attr( $current_id ); ?>">
					<?php wp_nonce_field( 'hayfam_dashboard_delete' ); ?>
					<?php submit_button( __( 'Delete this dashboard', 'dashboard-plugin' ), 'delete', 'submit', false, array( 'onclick' => "return confirm('Delete this dashboard?');" ) ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function sanitize_dashboard( $input, $current ) {
		$dashboard = wp_parse_args( is_array( $input ) ? $input : array(), $current );
		$dashboard['label']      = sanitize_text_field( $dashboard['label'] );
		$shortcode                = sanitize_key( $dashboard['shortcode'] );
		$shortcode                = 'dashboard_metric' === $shortcode ? '' : $shortcode;
		$dashboard['shortcode']   = $shortcode ? ( 0 === strpos( $shortcode, 'dashboard_' ) ? $shortcode : 'dashboard_' . $shortcode ) : $current['shortcode'];
		$dashboard['source_url'] = esc_url_raw( trim( $dashboard['source_url'] ) );
		$dashboard['sheet']      = sanitize_text_field( $dashboard['sheet'] );
		$dashboard['cell']       = strtoupper( preg_replace( '/\s+/', '', sanitize_text_field( $dashboard['cell'] ) ) );
		$dashboard['before']     = sanitize_textarea_field( $dashboard['before'] );
		$dashboard['after']      = sanitize_textarea_field( $dashboard['after'] );
		$dashboard['prefix']     = sanitize_text_field( $dashboard['prefix'] );
		$dashboard['suffix']     = sanitize_text_field( $dashboard['suffix'] );
		$dashboard['decimals']   = (string) max( -1, min( 10, (int) $dashboard['decimals'] ) );
		$dashboard['thousands']  = substr( sanitize_text_field( $dashboard['thousands'] ), 0, 1 );
		$dashboard['decimal']    = substr( sanitize_text_field( $dashboard['decimal'] ), 0, 1 );
		$dashboard['fallback']   = sanitize_text_field( $dashboard['fallback'] );
		$dashboard['cache_ttl']  = max( 60, absint( $dashboard['cache_ttl'] ) );
		$dashboard['class']      = sanitize_text_field( $dashboard['class'] );

		if ( ! preg_match( '/^[A-Z]+[1-9][0-9]*$/', $dashboard['cell'] ) ) {
			$dashboard['cell'] = 'B2';
		}

		if ( ! Hayfam_Dashboard_Sheets_Client::is_supported_url( $dashboard['source_url'] ) ) {
			$dashboard['source_url'] = '';
		}

		return $dashboard;
	}

	private static function unique_shortcode( $shortcode, $dashboard_id, $dashboards ) {
		$base   = $shortcode ? $shortcode : 'dashboard_' . $dashboard_id;
		$unique = $base;
		$number = 2;

		do {
			$collision = false;
			foreach ( $dashboards as $id => $dashboard ) {
				if ( $id !== $dashboard_id && isset( $dashboard['shortcode'] ) && $dashboard['shortcode'] === $unique ) {
					$unique    = $base . '_' . $number;
					$number++;
					$collision = true;
					break;
				}
			}
		} while ( $collision );

		return $unique;
	}

	private static function sync_legacy_values( &$settings, $dashboard_id ) {
		if ( isset( $settings['dashboards'][ $dashboard_id ] ) ) {
			$dashboard                = $settings['dashboards'][ $dashboard_id ];
			$settings['source_url']    = $dashboard['source_url'];
			$settings['default_sheet'] = $dashboard['sheet'];
			$settings['cache_ttl']     = $dashboard['cache_ttl'];
		}
	}

	private static function verify_admin_request( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'dashboard-plugin' ) );
		}

		check_admin_referer( $action );
	}

	private static function redirect( $dashboard_id, $message ) {
		$args = array( 'page' => self::PAGE_SLUG, 'message' => $message );
		if ( $dashboard_id ) {
			$args['dashboard'] = $dashboard_id;
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'options-general.php' ) ) );
		exit;
	}
}
