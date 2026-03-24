<?php
/**
 * Plugin Name: UU Widget Tracker Dashboard
 * Description: Central dashboard to find which pages use U of U widgets and plugins across multiple sites. Configure site URLs and query the uu-widget-tracker REST API on each site.
 * Version: 1.0.0
 * Author: UMC Digital
 *
 * @package UU_Widget_Tracker_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'UU_WIDGET_TRACKER_DASHBOARD_VERSION', '1.0.0' );
define( 'UU_WIDGET_TRACKER_DASHBOARD_OPTION', 'uu_widget_tracker_dashboard_sites' );

/**
 * Register admin menu and settings.
 */
add_action( 'admin_menu', function () {
	add_management_page(
		__( 'UU Usage', 'uu-widget-tracker-dashboard' ),
		__( 'UU Usage', 'uu-widget-tracker-dashboard' ),
		'manage_options',
		'uu-widget-tracker-dashboard',
		'uu_widget_tracker_dashboard_render_page'
	);
} );

/**
 * Enqueue script and styles for the dashboard page (spinner + AJAX fetch).
 */
add_action( 'admin_enqueue_scripts', function ( $hook_suffix ) {
	if ( $hook_suffix !== 'tools_page_uu-widget-tracker-dashboard' ) {
		return;
	}
	wp_add_inline_style( 'wp-admin', '
		.uu-widget-tracker-spinner-wrap { display: none; align-items: center; gap: 8px; margin: 12px 0; }
		.uu-widget-tracker-spinner-wrap.is-active { display: flex; }
		.uu-widget-tracker-spinner { width: 24px; height: 24px; border: 3px solid #c3c4c7; border-top-color: #2271b1; border-radius: 50%; animation: uu-widget-tracker-spin 0.8s linear infinite; }
		@keyframes uu-widget-tracker-spin { to { transform: rotate(360deg); } }
		#uu-widget-tracker-results { margin-top: 16px; }
		#uu-widget-tracker-results .uu-widget-tracker-error { color: #d63638; }
	' );
	$script_url  = plugin_dir_url( __FILE__ ) . 'js/dashboard.js';
	$script_path = plugin_dir_path( __FILE__ ) . 'js/dashboard.js';
	$script_ver  = file_exists( $script_path ) ? (string) filemtime( $script_path ) : UU_WIDGET_TRACKER_DASHBOARD_VERSION;
	wp_enqueue_script( 'uu-widget-tracker-dashboard', $script_url, array(), $script_ver, true );
	wp_add_inline_script( 'uu-widget-tracker-dashboard', 'var uuWidgetTrackerDashboard = ' . wp_json_encode( array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'uu_widget_tracker_dashboard_fetch' ),
		'i18n'    => array(
			'fetching' => __( 'Fetching usage…', 'uu-widget-tracker-dashboard' ),
			'error'    => __( 'Request failed.', 'uu-widget-tracker-dashboard' ),
			'noPosts'  => __( 'No posts using this item.', 'uu-widget-tracker-dashboard' ),
			'view'     => __( 'View', 'uu-widget-tracker-dashboard' ),
		),
	) ) . ';', 'before' );
} );

add_action( 'admin_init', function () {
	register_setting( 'uu_widget_tracker_dashboard', UU_WIDGET_TRACKER_DASHBOARD_OPTION, array(
		'type'              => 'string',
		'sanitize_callback' => function ( $value ) {
			$raw   = is_string( $value ) ? $value : '';
			$raw   = str_replace( array( "\r\n", "\r" ), "\n", $raw );
			$lines = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
			$keep  = array();
			foreach ( $lines as $line ) {
				if ( $line === '' ) {
					continue;
				}
				// Already has scheme and validates, or at least starts with http(s)://
				if ( preg_match( '#^https?://#i', $line ) ) {
					$keep[] = $line;
					continue;
				}
				// Hostname-only (e.g. site.utah.edu): normalize to https so fetch works
				if ( preg_match( '#^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$#i', $line ) && strpos( $line, ' ' ) === false ) {
					$keep[] = 'https://' . $line;
					continue;
				}
				// Allow any other non-empty line (e.g. with path, or odd but valid); try adding https:// for use
				$with_scheme = ( preg_match( '#^[a-z0-9]#i', $line ) && strpos( $line, ' ' ) === false ) ? 'https://' . $line : $line;
				$keep[] = $with_scheme;
			}
			return implode( "\n", array_unique( $keep ) );
		},
	) );
} );

/**
 * Default max execution time (seconds) for the fetch AJAX request. Use filter to override.
 *
 * @return int 0 = no limit (if allowed by server), or seconds.
 */
function uu_widget_tracker_dashboard_fetch_time_limit() {
	$limit = (int) apply_filters( 'uu_widget_tracker_dashboard_fetch_time_limit', 600 );
	return max( 0, $limit );
}

/**
 * AJAX: run fetch and return JSON (so fetch only runs on button click, not page load).
 */
add_action( 'wp_ajax_uu_widget_tracker_dashboard_fetch', function () {
	if ( ! current_user_can( 'manage_options' ) || empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'uu_widget_tracker_dashboard_fetch' ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid request.', 'uu-widget-tracker-dashboard' ) ) );
	}
	$widget = isset( $_POST['widget'] ) ? sanitize_text_field( wp_unslash( $_POST['widget'] ) ) : '';
	if ( $widget === '' ) {
		wp_send_json_error( array( 'message' => __( 'Please select a tracked item.', 'uu-widget-tracker-dashboard' ) ) );
	}
	$option_value = get_option( UU_WIDGET_TRACKER_DASHBOARD_OPTION, '' );
	$site_urls    = array_values( array_filter( array_map( 'trim', explode( "\n", $option_value ) ) ) );
	if ( empty( $site_urls ) ) {
		wp_send_json_error( array( 'message' => __( 'Add and save site URLs above first.', 'uu-widget-tracker-dashboard' ) ) );
	}

	$total_sites = count( $site_urls );
	$offset      = isset( $_POST['offset'] ) ? max( 0, (int) $_POST['offset'] ) : 0;
	$batch_size = isset( $_POST['batch_size'] ) ? min( 50, max( 5, (int) $_POST['batch_size'] ) ) : 20;
	$batch      = array_slice( $site_urls, $offset, $batch_size );

	$time_limit_seconds = uu_widget_tracker_dashboard_fetch_time_limit();
	$previous_limit     = ini_get( 'max_execution_time' );
	if ( $time_limit_seconds > 0 ) {
		@set_time_limit( $time_limit_seconds );
	} else {
		@set_time_limit( 0 );
	}

	$start_time   = microtime( true );
	$results      = array();
	$sites_ok     = 0;
	$sites_error  = 0;
	$total_posts  = 0;

	foreach ( $batch as $base_url ) {
		$base_url = untrailingslashit( trailingslashit( trim( $base_url ) ) );
		$result   = uu_widget_tracker_dashboard_fetch_site( $base_url, $widget );
		$results[] = array( 'url' => $base_url, 'data' => $result );
		if ( isset( $result['error'] ) ) {
			$sites_error++;
		} else {
			$sites_ok++;
			$posts = isset( $result['posts'] ) && is_array( $result['posts'] ) ? $result['posts'] : array();
			$total_posts += count( $posts );
		}
	}

	$error_urls   = array();
	foreach ( $results as $item ) {
		if ( ! empty( $item['data']['error'] ) ) {
			$error_urls[] = array( 'url' => $item['url'], 'message' => $item['data']['error'] );
		}
	}

	$execution_time = round( microtime( true ) - $start_time, 2 );
	$processed      = count( $results );
	$has_more       = ( $offset + $processed ) < $total_sites;
	$next_offset    = $offset + $processed;

	$debug = array(
		'total_sites'            => $total_sites,
		'offset'                 => $offset,
		'processed'              => $processed,
		'has_more'                => $has_more,
		'next_offset'             => $has_more ? $next_offset : null,
		'sites_ok'                => $sites_ok,
		'sites_error'             => $sites_error,
		'execution_time_seconds'  => $execution_time,
		'php_time_limit_set'      => $time_limit_seconds,
		'php_time_limit_was'      => $previous_limit ? (int) $previous_limit : null,
		'total_posts_found'       => $total_posts,
		'error_urls'              => $error_urls,
	);

	wp_send_json_success( array( 'widget' => $widget, 'results' => $results, 'debug' => $debug ) );
} );

/**
 * Fetch usage from a single site.
 *
 * @param string      $base_url Site base URL (no trailing slash).
 * @param string|null $widget_slug Widget slug to filter by.
 * @return array{error: string}|array{site_name: string, site_url: string, widget_slug: string|null, posts: array}
 */
function uu_widget_tracker_dashboard_fetch_site( $base_url, $widget_slug = null ) {
	$url = trailingslashit( $base_url ) . 'wp-json/uu-widget-tracker/v1/usage';
	if ( $widget_slug !== null && $widget_slug !== '' ) {
		$url = add_query_arg( 'widget', $widget_slug, $url );
	}

	$response = wp_remote_get( $url, array(
		'timeout' => 15,
		'headers' => array( 'Accept' => 'application/json' ),
	) );

	if ( is_wp_error( $response ) ) {
		return array( 'error' => $response->get_error_message() );
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );

	if ( $code !== 200 ) {
		return array( 'error' => "HTTP {$code}: " . substr( strip_tags( $body ), 0, 200 ) );
	}

	$data = json_decode( $body, true );
	if ( ! is_array( $data ) ) {
		return array( 'error' => 'Invalid JSON response' );
	}

	return $data;
}

/**
 * Get tracked item list from this site (for dropdown). Requires the tracker plugin to be active on the dashboard site.
 *
 * @return array<array{slug: string, class: string}> Empty if tracker not available.
 */
function uu_widget_tracker_dashboard_get_local_widget_list() {
	if ( ! class_exists( 'UU_Widget_Usage_Tracker' ) ) {
		return array();
	}

	if ( method_exists( 'UU_Widget_Usage_Tracker', 'get_usage_items' ) ) {
		$list = UU_Widget_Usage_Tracker::get_usage_items();
		usort( $list, function ( $a, $b ) {
			return strnatcasecmp( $a['slug'], $b['slug'] );
		} );
		return $list;
	}

	$map = UU_Widget_Usage_Tracker::get_uu_widget_class_to_slug_map();
	$slugs = array_unique( array_values( $map ) );
	sort( $slugs, SORT_NATURAL );

	return array_map( function ( $slug ) {
		return array(
			'slug'  => $slug,
			'class' => '',
			'label' => $slug,
			'kind'  => 'siteorigin_class',
		);
	}, $slugs );
}

/**
 * Render the dashboard admin page.
 */
function uu_widget_tracker_dashboard_render_page() {
	$option_value = get_option( UU_WIDGET_TRACKER_DASHBOARD_OPTION, '' );
	$site_urls    = array_filter( array_map( 'trim', explode( "\n", $option_value ) ) );

	// Tracked item list for dropdown: from this site (dashboard) when the tracker plugin is active.
	$widgets_list = uu_widget_tracker_dashboard_get_local_widget_list();

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'UU Usage', 'uu-widget-tracker-dashboard' ); ?></h1>

		<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" style="max-width: 800px; margin-bottom: 24px;">
			<?php settings_fields( 'uu_widget_tracker_dashboard' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="uu_widget_tracker_sites"><?php esc_html_e( 'Site URLs', 'uu-widget-tracker-dashboard' ); ?></label></th>
					<td>
						<textarea name="<?php echo esc_attr( UU_WIDGET_TRACKER_DASHBOARD_OPTION ); ?>" id="uu_widget_tracker_sites" rows="6" class="large-text code"><?php echo esc_textarea( $option_value ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One URL per line (e.g. https://example.utah.edu). These sites must have the tracker plugin enabled so the dashboard can ask for widget and plugin usage data.', 'uu-widget-tracker-dashboard' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save URLs', 'uu-widget-tracker-dashboard' ) ); ?>
		</form>

		<hr />

		<h2><?php esc_html_e( 'Find usage', 'uu-widget-tracker-dashboard' ); ?></h2>
		<form id="uu-widget-tracker-fetch-form">
			<p>
				<label for="uu_widget_slug"><?php esc_html_e( 'Tracked slug', 'uu-widget-tracker-dashboard' ); ?></label>
				<?php if ( ! empty( $widgets_list ) ) : ?>
					<select name="widget" id="uu_widget_slug">
						<option value=""><?php esc_html_e( '— Select —', 'uu-widget-tracker-dashboard' ); ?></option>
						<?php foreach ( $widgets_list as $w ) : ?>
							<option value="<?php echo esc_attr( $w['slug'] ); ?>"><?php echo esc_html( ! empty( $w['label'] ) ? $w['label'] : $w['slug'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<span class="description" style="display:block; margin-top:8px;"><?php esc_html_e( 'Choose a known slug from this site, or type a custom slug below for a remote-only plugin.', 'uu-widget-tracker-dashboard' ); ?></span>
					<input type="text" name="widget_custom" id="uu_widget_slug_custom" placeholder="uu-available-technologies, uu-law-directory, …" class="regular-text" style="margin-top:8px;" />
				<?php else : ?>
					<input type="text" name="widget" id="uu_widget_slug" placeholder="uu-accordion-widget, uu-available-technologies, …" class="regular-text" required />
					<span class="description"><?php esc_html_e( 'Install and activate the tracker plugin on this dashboard site to show the dropdown; otherwise type the slug manually.', 'uu-widget-tracker-dashboard' ); ?></span>
				<?php endif; ?>
			</p>
			<p>
				<?php submit_button( __( 'Fetch usage', 'uu-widget-tracker-dashboard' ), 'primary', '', false ); ?>
			</p>
		</form>

		<div id="uu-widget-tracker-spinner-wrap" class="uu-widget-tracker-spinner-wrap" aria-hidden="true">
			<span class="uu-widget-tracker-spinner"></span>
			<span class="uu-widget-tracker-spinner-text"><?php esc_html_e( 'Fetching usage…', 'uu-widget-tracker-dashboard' ); ?></span>
		</div>
		<div id="uu-widget-tracker-results"></div>
	</div>
	<?php
}
