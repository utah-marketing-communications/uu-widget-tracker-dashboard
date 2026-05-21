<?php
/**
 * Plugin Name: UU Widget Tracker Dashboard
 * Description: Central dashboard for current UU widget usage and legacy audit workflows across multiple sites.
 * Version: 1.1.0
 * Author: UMC Digital
 *
 * @package UU_Widget_Tracker_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'UU_WIDGET_TRACKER_DASHBOARD_VERSION', '1.1.0' );
define( 'UU_WIDGET_TRACKER_DASHBOARD_OPTION', 'uu_widget_tracker_dashboard_sites' );
define( 'UU_WIDGET_TRACKER_DASHBOARD_PAGE_CURRENT', 'uu-current-usage' );
define( 'UU_WIDGET_TRACKER_DASHBOARD_PAGE_LEGACY', 'uu-legacy-audit' );

add_action(
	'admin_menu',
	function () {
		add_management_page(
			__( 'UU Current Usage', 'uu-widget-tracker-dashboard' ),
			__( 'UU Current Usage', 'uu-widget-tracker-dashboard' ),
			'manage_options',
			UU_WIDGET_TRACKER_DASHBOARD_PAGE_CURRENT,
			'uu_widget_tracker_dashboard_render_current_page'
		);

		add_submenu_page(
			'tools.php',
			__( 'UU Legacy Audit', 'uu-widget-tracker-dashboard' ),
			__( 'UU Legacy Audit', 'uu-widget-tracker-dashboard' ),
			'manage_options',
			UU_WIDGET_TRACKER_DASHBOARD_PAGE_LEGACY,
			'uu_widget_tracker_dashboard_render_legacy_page'
		);
	}
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook_suffix ) {
		$page_mode = '';
		if ( 'tools_page_' . UU_WIDGET_TRACKER_DASHBOARD_PAGE_CURRENT === $hook_suffix ) {
			$page_mode = 'current';
		} elseif ( 'tools_page_' . UU_WIDGET_TRACKER_DASHBOARD_PAGE_LEGACY === $hook_suffix ) {
			$page_mode = 'legacy';
		}

		if ( '' === $page_mode ) {
			return;
		}

		wp_add_inline_style(
			'wp-admin',
			'
			.uu-widget-tracker-spinner-wrap { display: none; align-items: center; gap: 8px; margin: 12px 0; }
			.uu-widget-tracker-spinner-wrap.is-active { display: flex; }
			.uu-widget-tracker-spinner { width: 24px; height: 24px; border: 3px solid #c3c4c7; border-top-color: #2271b1; border-radius: 50%; animation: uu-widget-tracker-spin 0.8s linear infinite; }
			@keyframes uu-widget-tracker-spin { to { transform: rotate(360deg); } }
			.uu-widget-tracker-panel { margin-top: 24px; }
			.uu-widget-tracker-panel-intro { max-width: 880px; }
			.uu-widget-tracker-lookup-method-panel { display: none; margin-top: 8px; }
			.uu-widget-tracker-lookup-method-panel.is-active { display: block; }
			.uu-widget-tracker-results { margin-top: 16px; }
			.uu-widget-tracker-results .uu-widget-tracker-error { color: #d63638; }
			.uu-widget-tracker-discovery-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); margin-top: 16px; }
			.uu-widget-tracker-discovery-card { padding: 12px; background: #fff; border: 1px solid #dcdcde; border-radius: 4px; }
			'
		);

		$script_url  = plugin_dir_url( __FILE__ ) . 'js/dashboard.js';
		$script_path = plugin_dir_path( __FILE__ ) . 'js/dashboard.js';
		$script_ver  = file_exists( $script_path ) ? (string) filemtime( $script_path ) : UU_WIDGET_TRACKER_DASHBOARD_VERSION;
		wp_enqueue_script( 'uu-widget-tracker-dashboard', $script_url, array(), $script_ver, true );
		wp_add_inline_script(
			'uu-widget-tracker-dashboard',
			'var uuWidgetTrackerDashboard = ' . wp_json_encode(
				array(
					'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'uu_widget_tracker_dashboard_fetch' ),
					'pageMode' => $page_mode,
					'i18n'     => array(
						'fetching'            => __( 'Fetching usage…', 'uu-widget-tracker-dashboard' ),
						'fetchingCatalog'     => __( 'Fetching tracked item list…', 'uu-widget-tracker-dashboard' ),
						'fetchingDiscovery'   => __( 'Fetching discovery signals…', 'uu-widget-tracker-dashboard' ),
						'auditing'            => __( 'Auditing tracked items…', 'uu-widget-tracker-dashboard' ),
						'error'               => __( 'Request failed.', 'uu-widget-tracker-dashboard' ),
						'noPosts'             => __( 'No matching uses found for this item.', 'uu-widget-tracker-dashboard' ),
						'view'                => __( 'View', 'uu-widget-tracker-dashboard' ),
						'exportAuditCsv'      => __( 'Export Audit CSV', 'uu-widget-tracker-dashboard' ),
						'bulkAuditHeading'    => __( 'Audit summary', 'uu-widget-tracker-dashboard' ),
						'auditProgress'       => __( 'Auditing item', 'uu-widget-tracker-dashboard' ),
						'trackedItemsFound'   => __( 'Tracked items discovered', 'uu-widget-tracker-dashboard' ),
						'matchesFound'        => __( 'matching pages found', 'uu-widget-tracker-dashboard' ),
						'noMatchesFound'      => __( 'No matching pages found.', 'uu-widget-tracker-dashboard' ),
						'used'                => __( 'Used', 'uu-widget-tracker-dashboard' ),
						'noMatches'           => __( 'No matches', 'uu-widget-tracker-dashboard' ),
						'outOfScope'          => __( 'Out of scope', 'uu-widget-tracker-dashboard' ),
						'statusError'         => __( 'Error', 'uu-widget-tracker-dashboard' ),
						'confidenceHigh'      => __( 'High', 'uu-widget-tracker-dashboard' ),
						'confidenceMedium'    => __( 'Medium', 'uu-widget-tracker-dashboard' ),
						'confidenceLow'       => __( 'Low', 'uu-widget-tracker-dashboard' ),
						'widgetAreaOnly'      => __( 'Widget area only', 'uu-widget-tracker-dashboard' ),
						'publishedOnly'       => __( 'Published content only', 'uu-widget-tracker-dashboard' ),
						'remoteSiteUrls'      => __( 'Remote site URLs', 'uu-widget-tracker-dashboard' ),
						'remoteSitesProcessed'=> __( 'Remote sites processed', 'uu-widget-tracker-dashboard' ),
						'multisiteBlogsScanned' => __( 'Multisite blogs scanned', 'uu-widget-tracker-dashboard' ),
						'activationStatus'    => __( 'Plugin activation', 'uu-widget-tracker-dashboard' ),
						'activeBlogs'         => __( 'Blogs with plugin active', 'uu-widget-tracker-dashboard' ),
						'matchedBy'           => __( 'Matched by', 'uu-widget-tracker-dashboard' ),
						'discoveryHeading'    => __( 'Discovery signals', 'uu-widget-tracker-dashboard' ),
						'discoveryEmpty'      => __( 'No candidate signals were returned.', 'uu-widget-tracker-dashboard' ),
						'classicWidgetIds'    => __( 'Classic widget IDs', 'uu-widget-tracker-dashboard' ),
						'siteoriginClasses'   => __( 'SiteOrigin classes', 'uu-widget-tracker-dashboard' ),
						'contentMarkers'      => __( 'Content markers', 'uu-widget-tracker-dashboard' ),
					),
				)
			) . ';',
			'before'
		);
	}
);

add_action(
	'admin_init',
	function () {
		register_setting(
			'uu_widget_tracker_dashboard',
			UU_WIDGET_TRACKER_DASHBOARD_OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => function ( $value ) {
					$raw   = is_string( $value ) ? $value : '';
					$raw   = str_replace( array( "\r\n", "\r" ), "\n", $raw );
					$lines = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
					$keep  = array();

					foreach ( $lines as $line ) {
						if ( '' === $line ) {
							continue;
						}
						if ( preg_match( '#^https?://#i', $line ) ) {
							$keep[] = $line;
							continue;
						}
						if ( preg_match( '#^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$#i', $line ) && false === strpos( $line, ' ' ) ) {
							$keep[] = 'https://' . $line;
							continue;
						}
						$keep[] = $line;
					}

					return implode( "\n", array_unique( $keep ) );
				},
			)
		);
	}
);

function uu_widget_tracker_dashboard_fetch_time_limit() {
	$limit = (int) apply_filters( 'uu_widget_tracker_dashboard_fetch_time_limit', 600 );
	return max( 0, $limit );
}

function uu_widget_tracker_dashboard_get_configured_site_urls() {
	$option_value = get_option( UU_WIDGET_TRACKER_DASHBOARD_OPTION, '' );
	return array_values( array_filter( array_map( 'trim', explode( "\n", $option_value ) ) ) );
}

function uu_widget_tracker_dashboard_normalize_base_url( $base_url ) {
	return untrailingslashit( trailingslashit( trim( (string) $base_url ) ) );
}

function uu_widget_tracker_dashboard_get_tracker_class_name() {
	if ( class_exists( 'UU_Usage_Tracker' ) ) {
		return 'UU_Usage_Tracker';
	}
	if ( class_exists( 'UU_Widget_Usage_Tracker' ) ) {
		return 'UU_Widget_Usage_Tracker';
	}
	return '';
}

function uu_widget_tracker_dashboard_resolve_site_urls_from_request() {
	$site_urls = uu_widget_tracker_dashboard_get_configured_site_urls();
	if ( empty( $site_urls ) ) {
		wp_send_json_error( array( 'message' => __( 'Add and save site URLs above first.', 'uu-widget-tracker-dashboard' ) ) );
	}

	$requested_site_url = isset( $_POST['site_url'] ) ? uu_widget_tracker_dashboard_normalize_base_url( sanitize_text_field( wp_unslash( $_POST['site_url'] ) ) ) : '';
	if ( '' === $requested_site_url ) {
		return $site_urls;
	}

	$normalized_site_urls = array_map( 'uu_widget_tracker_dashboard_normalize_base_url', $site_urls );
	$matching_index       = array_search( $requested_site_url, $normalized_site_urls, true );
	if ( false === $matching_index ) {
		wp_send_json_error( array( 'message' => __( 'Selected site URL is not in the saved site list.', 'uu-widget-tracker-dashboard' ) ) );
	}

	return array( $normalized_site_urls[ $matching_index ] );
}

function uu_widget_tracker_dashboard_remote_get_json( $base_url, $candidates ) {
	foreach ( $candidates as $candidate ) {
		$path = isset( $candidate['path'] ) ? (string) $candidate['path'] : '';
		$args = isset( $candidate['args'] ) && is_array( $candidate['args'] ) ? $candidate['args'] : array();
		$url  = trailingslashit( uu_widget_tracker_dashboard_normalize_base_url( $base_url ) ) . ltrim( $path, '/' );
		if ( ! empty( $args ) ) {
			$url = add_query_arg( $args, $url );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$last_error = $response->get_error_message();
			continue;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		if ( 200 !== $code ) {
			$last_error = "HTTP {$code}: " . substr( strip_tags( $body ), 0, 200 );
			continue;
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			$last_error = 'Invalid JSON response';
			continue;
		}

		return array(
			'data'      => $data,
			'endpoint'  => $path,
			'error'     => '',
		);
	}

	return array(
		'data'     => array(),
		'endpoint' => '',
		'error'    => isset( $last_error ) ? $last_error : __( 'No compatible tracker endpoint responded.', 'uu-widget-tracker-dashboard' ),
	);
}

function uu_widget_tracker_dashboard_fetch_site( $base_url, $item_slug = null ) {
	$candidates = array(
		array(
			'path' => 'wp-json/uu-usage-tracker/v1/usage',
			'args' => array_filter(
				array(
					'item' => $item_slug,
				)
			),
		),
		array(
			'path' => 'wp-json/uu-widget-tracker/v1/usage',
			'args' => array_filter(
				array(
					'widget' => $item_slug,
				)
			),
		),
	);

	$result = uu_widget_tracker_dashboard_remote_get_json( $base_url, $candidates );
	return '' === $result['error'] ? $result['data'] : array( 'error' => $result['error'] );
}

function uu_widget_tracker_dashboard_fetch_site_items( $base_url ) {
	$result = uu_widget_tracker_dashboard_remote_get_json(
		$base_url,
		array(
			array( 'path' => 'wp-json/uu-usage-tracker/v1/items' ),
			array( 'path' => 'wp-json/uu-widget-tracker/v1/widgets' ),
		)
	);

	if ( '' !== $result['error'] ) {
		return array( 'error' => $result['error'] );
	}

	$data  = $result['data'];
	$items = array();
	if ( isset( $data['items'] ) && is_array( $data['items'] ) ) {
		$items = $data['items'];
	} elseif ( isset( $data['widgets'] ) && is_array( $data['widgets'] ) ) {
		$items = $data['widgets'];
	}

	if ( empty( $items ) && ! empty( $data ) ) {
		return array( 'error' => 'Invalid items response' );
	}

	return array(
		'items'   => $items,
		'widgets' => $items,
	);
}

function uu_widget_tracker_dashboard_fetch_site_discovery( $base_url ) {
	$siteorigin = uu_widget_tracker_dashboard_remote_get_json(
		$base_url,
		array(
			array( 'path' => 'wp-json/uu-usage-tracker/v1/discovery/siteorigin-classes' ),
			array( 'path' => 'wp-json/uu-widget-tracker/v1/widget-classes-seen' ),
		)
	);

	$classics = uu_widget_tracker_dashboard_remote_get_json(
		$base_url,
		array(
			array( 'path' => 'wp-json/uu-usage-tracker/v1/discovery/classic-widget-ids' ),
		)
	);

	$content = uu_widget_tracker_dashboard_remote_get_json(
		$base_url,
		array(
			array( 'path' => 'wp-json/uu-usage-tracker/v1/discovery/content-markers' ),
		)
	);

	return array(
		'siteorigin_classes' => '' === $siteorigin['error'] ? $siteorigin['data'] : array( 'error' => $siteorigin['error'] ),
		'classic_widget_ids' => '' === $classics['error'] ? $classics['data'] : array( 'error' => $classics['error'] ),
		'content_markers'    => '' === $content['error'] ? $content['data'] : array( 'error' => $content['error'] ),
	);
}

function uu_widget_tracker_dashboard_normalize_lookup_token( $value ) {
	return strtolower( trim( (string) $value ) );
}

function uu_widget_tracker_dashboard_items_catalog_has_item( $items, $item_filter ) {
	$needle = uu_widget_tracker_dashboard_normalize_lookup_token( $item_filter );
	if ( '' === $needle ) {
		return false;
	}

	foreach ( $items as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}

		$candidates = array(
			isset( $item['slug'] ) ? $item['slug'] : '',
			isset( $item['class'] ) ? $item['class'] : '',
			isset( $item['search_for'] ) ? $item['search_for'] : '',
		);

		foreach ( $candidates as $candidate ) {
			if ( uu_widget_tracker_dashboard_normalize_lookup_token( $candidate ) === $needle ) {
				return true;
			}
		}
	}

	return false;
}

function uu_widget_tracker_dashboard_get_tracking_definition_status( $base_url, $item_filter ) {
	$items_data = uu_widget_tracker_dashboard_fetch_site_items( $base_url );
	if ( ! empty( $items_data['error'] ) ) {
		return array(
			'tracking_definition_status'     => __( 'Unknown (catalog fetch failed)', 'uu-widget-tracker-dashboard' ),
			'tracking_definition_note'       => sprintf(
				__( 'Could not fetch the remote item catalog: %s', 'uu-widget-tracker-dashboard' ),
				$items_data['error']
			),
			'tracking_definition_registered' => null,
		);
	}

	$is_registered = uu_widget_tracker_dashboard_items_catalog_has_item( $items_data['items'], $item_filter );
	return array(
		'tracking_definition_status'     => $is_registered ? __( 'Registered', 'uu-widget-tracker-dashboard' ) : __( 'Not registered on site', 'uu-widget-tracker-dashboard' ),
		'tracking_definition_note'       => $is_registered
			? __( 'Item found in remote tracker catalog.', 'uu-widget-tracker-dashboard' )
			: __( 'Item missing from remote tracker catalog; this site may need a global definition, legacy pack, or network-specific add-on.', 'uu-widget-tracker-dashboard' ),
		'tracking_definition_registered' => $is_registered,
	);
}

function uu_widget_tracker_dashboard_get_local_item_list() {
	$tracker_class = uu_widget_tracker_dashboard_get_tracker_class_name();
	if ( '' === $tracker_class ) {
		return array();
	}

	if ( method_exists( $tracker_class, 'get_usage_items' ) ) {
		$list = $tracker_class::get_usage_items();
		usort(
			$list,
			function ( $a, $b ) {
				return strnatcasecmp( $a['slug'], $b['slug'] );
			}
		);
		return $list;
	}

	return array();
}

add_action(
	'wp_ajax_uu_widget_tracker_dashboard_fetch',
	function () {
		if ( ! current_user_can( 'manage_options' ) || empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'uu_widget_tracker_dashboard_fetch' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'uu-widget-tracker-dashboard' ) ) );
		}

		$item = isset( $_POST['widget'] ) ? sanitize_text_field( wp_unslash( $_POST['widget'] ) ) : '';
		if ( '' === $item ) {
			wp_send_json_error( array( 'message' => __( 'Please select a tracked item.', 'uu-widget-tracker-dashboard' ) ) );
		}

		$site_urls           = uu_widget_tracker_dashboard_resolve_site_urls_from_request();
		$total_sites         = count( $site_urls );
		$offset              = isset( $_POST['offset'] ) ? max( 0, (int) $_POST['offset'] ) : 0;
		$batch_size          = isset( $_POST['batch_size'] ) ? min( 50, max( 5, (int) $_POST['batch_size'] ) ) : 20;
		$batch               = array_slice( $site_urls, $offset, $batch_size );
		$time_limit_seconds  = uu_widget_tracker_dashboard_fetch_time_limit();
		$previous_limit      = ini_get( 'max_execution_time' );
		$start_time          = microtime( true );
		$results             = array();
		$sites_ok            = 0;
		$sites_error         = 0;
		$total_posts         = 0;
		$public_only_default = true;

		if ( $time_limit_seconds > 0 ) {
			@set_time_limit( $time_limit_seconds );
		} else {
			@set_time_limit( 0 );
		}

		foreach ( $batch as $base_url ) {
			$base_url = uu_widget_tracker_dashboard_normalize_base_url( $base_url );
			$result   = uu_widget_tracker_dashboard_fetch_site( $base_url, $item );
			$result   = array_merge( $result, uu_widget_tracker_dashboard_get_tracking_definition_status( $base_url, $item ) );
			if ( isset( $result['public_only'] ) ) {
				$public_only_default = (bool) $result['public_only'];
			}
			$results[] = array( 'url' => $base_url, 'data' => $result );

			if ( isset( $result['error'] ) ) {
				$sites_error++;
				continue;
			}

			$sites_ok++;
			$posts       = isset( $result['posts'] ) && is_array( $result['posts'] ) ? $result['posts'] : array();
			$total_posts += count( $posts );
		}

		$error_urls = array();
		foreach ( $results as $result_row ) {
			if ( ! empty( $result_row['data']['error'] ) ) {
				$error_urls[] = array(
					'url'     => $result_row['url'],
					'message' => $result_row['data']['error'],
				);
			}
		}

		$processed   = count( $results );
		$has_more    = ( $offset + $processed ) < $total_sites;
		$next_offset = $offset + $processed;

		wp_send_json_success(
			array(
				'widget'  => $item,
				'results' => $results,
				'debug'   => array(
					'total_sites'           => $total_sites,
					'offset'                => $offset,
					'processed'             => $processed,
					'has_more'              => $has_more,
					'next_offset'           => $has_more ? $next_offset : null,
					'sites_ok'              => $sites_ok,
					'sites_error'           => $sites_error,
					'execution_time_seconds'=> round( microtime( true ) - $start_time, 2 ),
					'php_time_limit_set'    => $time_limit_seconds,
					'php_time_limit_was'    => $previous_limit ? (int) $previous_limit : null,
					'total_posts_found'     => $total_posts,
					'error_urls'            => $error_urls,
					'public_only'           => $public_only_default,
				),
			)
		);
	}
);

add_action(
	'wp_ajax_uu_widget_tracker_dashboard_fetch_widget_catalog',
	function () {
		if ( ! current_user_can( 'manage_options' ) || empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'uu_widget_tracker_dashboard_fetch' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'uu-widget-tracker-dashboard' ) ) );
		}

		$site_urls = uu_widget_tracker_dashboard_resolve_site_urls_from_request();
		$results   = array();
		$sites_ok  = 0;
		$sites_err = 0;
		$start     = microtime( true );

		foreach ( $site_urls as $base_url ) {
			$base_url = uu_widget_tracker_dashboard_normalize_base_url( $base_url );
			$result   = uu_widget_tracker_dashboard_fetch_site_items( $base_url );
			$results[] = array( 'url' => $base_url, 'data' => $result );

			if ( isset( $result['error'] ) ) {
				$sites_err++;
			} else {
				$sites_ok++;
			}
		}

		$error_urls = array();
		foreach ( $results as $result_row ) {
			if ( ! empty( $result_row['data']['error'] ) ) {
				$error_urls[] = array(
					'url'     => $result_row['url'],
					'message' => $result_row['data']['error'],
				);
			}
		}

		wp_send_json_success(
			array(
				'results' => $results,
				'debug'   => array(
					'total_sites'            => count( $site_urls ),
					'sites_ok'               => $sites_ok,
					'sites_error'            => $sites_err,
					'execution_time_seconds' => round( microtime( true ) - $start, 2 ),
					'error_urls'             => $error_urls,
				),
			)
		);
	}
);

add_action(
	'wp_ajax_uu_widget_tracker_dashboard_fetch_discovery',
	function () {
		if ( ! current_user_can( 'manage_options' ) || empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'uu_widget_tracker_dashboard_fetch' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'uu-widget-tracker-dashboard' ) ) );
		}

		$site_urls = uu_widget_tracker_dashboard_resolve_site_urls_from_request();
		if ( count( $site_urls ) !== 1 ) {
			wp_send_json_error( array( 'message' => __( 'Choose one saved site URL for discovery.', 'uu-widget-tracker-dashboard' ) ) );
		}

		wp_send_json_success(
			array(
				'url'       => $site_urls[0],
				'discovery' => uu_widget_tracker_dashboard_fetch_site_discovery( $site_urls[0] ),
			)
		);
	}
);

function uu_widget_tracker_dashboard_render_settings_form( $option_value ) {
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" style="max-width: 880px; margin-bottom: 24px;">
		<?php settings_fields( 'uu_widget_tracker_dashboard' ); ?>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="uu_widget_tracker_sites"><?php esc_html_e( 'Site URLs', 'uu-widget-tracker-dashboard' ); ?></label></th>
				<td>
					<textarea name="<?php echo esc_attr( UU_WIDGET_TRACKER_DASHBOARD_OPTION ); ?>" id="uu_widget_tracker_sites" rows="6" class="large-text code"><?php echo esc_textarea( $option_value ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One URL per line. These remote sites should have the new UU Usage Tracker plugin or the legacy tracker endpoints available.', 'uu-widget-tracker-dashboard' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Save URLs', 'uu-widget-tracker-dashboard' ) ); ?>
	</form>
	<?php
}

function uu_widget_tracker_dashboard_render_lookup_form( $mode, $items_list, $site_urls ) {
	$is_legacy = 'legacy' === $mode;
	?>
	<div class="uu-widget-tracker-panel">
		<h2><?php echo esc_html( $is_legacy ? __( 'Legacy Lookup', 'uu-widget-tracker-dashboard' ) : __( 'Current Usage Lookup', 'uu-widget-tracker-dashboard' ) ); ?></h2>
		<p class="description uu-widget-tracker-panel-intro">
			<?php echo esc_html( $is_legacy ? __( 'Use this page for single-network legacy audits, manual slug lookups, and candidate-signal discovery.', 'uu-widget-tracker-dashboard' ) : __( 'Use this page for current supported widgets and plugins across your saved URLs. It favors page-level results and high-confidence signals.', 'uu-widget-tracker-dashboard' ) ); ?>
		</p>
		<form id="uu-widget-tracker-fetch-form" data-page-mode="<?php echo esc_attr( $mode ); ?>">
			<?php if ( ! $is_legacy && ! empty( $items_list ) ) : ?>
				<p>
					<strong><?php esc_html_e( 'Lookup method', 'uu-widget-tracker-dashboard' ); ?></strong><br />
					<label style="display:block; margin:6px 0;">
						<input type="radio" name="lookup_method" value="known" checked />
						<?php esc_html_e( 'Choose known tracked item', 'uu-widget-tracker-dashboard' ); ?>
					</label>
					<label style="display:block; margin:6px 0;">
						<input type="radio" name="lookup_method" value="manual" />
						<?php esc_html_e( 'Enter slug manually', 'uu-widget-tracker-dashboard' ); ?>
					</label>
				</p>
				<div id="uu-widget-tracker-lookup-known-panel" class="uu-widget-tracker-lookup-method-panel is-active" data-lookup-method-panel="known">
					<p>
						<label for="uu_widget_slug"><?php esc_html_e( 'Tracked item', 'uu-widget-tracker-dashboard' ); ?></label>
						<select name="widget" id="uu_widget_slug">
							<option value=""><?php esc_html_e( '— Select —', 'uu-widget-tracker-dashboard' ); ?></option>
							<?php foreach ( $items_list as $item ) : ?>
								<option value="<?php echo esc_attr( $item['slug'] ); ?>"><?php echo esc_html( ! empty( $item['label'] ) ? $item['label'] : $item['slug'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
				</div>
				<div id="uu-widget-tracker-lookup-manual-panel" class="uu-widget-tracker-lookup-method-panel" data-lookup-method-panel="manual">
					<p>
						<label for="uu_widget_slug_custom"><?php esc_html_e( 'Manual item', 'uu-widget-tracker-dashboard' ); ?></label>
						<input type="text" name="widget_custom" id="uu_widget_slug_custom" class="regular-text" placeholder="uu-law-directory, uu-accordion-widget, ..." />
					</p>
				</div>
			<?php else : ?>
				<p>
					<label for="uu_widget_slug"><?php esc_html_e( 'Manual item', 'uu-widget-tracker-dashboard' ); ?></label>
					<input type="text" name="widget" id="uu_widget_slug" class="regular-text" placeholder="uu-law-directory, legacy-card-widget, ..." required />
				</p>
			<?php endif; ?>

			<?php if ( ! empty( $site_urls ) ) : ?>
				<p>
					<strong><?php esc_html_e( 'Search scope', 'uu-widget-tracker-dashboard' ); ?></strong><br />
					<label style="display:block; margin:6px 0;">
						<input type="radio" name="search_scope" value="all" <?php checked( ! $is_legacy ); ?> />
						<?php esc_html_e( 'All saved URLs', 'uu-widget-tracker-dashboard' ); ?>
					</label>
					<label style="display:block; margin:6px 0;">
						<input type="radio" name="search_scope" value="single" <?php checked( $is_legacy ); ?> />
						<?php esc_html_e( 'Only this site/network', 'uu-widget-tracker-dashboard' ); ?>
					</label>
					<select id="uu_widget_site_scope" name="site_scope" class="regular-text" <?php disabled( ! $is_legacy ); ?> style="margin-top:8px;">
						<option value=""><?php esc_html_e( '— Select saved URL —', 'uu-widget-tracker-dashboard' ); ?></option>
						<?php foreach ( $site_urls as $site_url ) : ?>
							<option value="<?php echo esc_attr( uu_widget_tracker_dashboard_normalize_base_url( $site_url ) ); ?>"><?php echo esc_html( uu_widget_tracker_dashboard_normalize_base_url( $site_url ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
			<?php endif; ?>

			<p>
				<?php submit_button( __( 'Find matching pages', 'uu-widget-tracker-dashboard' ), 'primary', '', false ); ?>
			</p>
		</form>
		<div id="uu-widget-tracker-lookup-spinner-wrap" class="uu-widget-tracker-spinner-wrap" aria-hidden="true">
			<span class="uu-widget-tracker-spinner"></span>
			<span class="uu-widget-tracker-spinner-text"><?php esc_html_e( 'Fetching usage…', 'uu-widget-tracker-dashboard' ); ?></span>
		</div>
		<div id="uu-widget-tracker-lookup-results" class="uu-widget-tracker-results"></div>
	</div>
	<?php
}

function uu_widget_tracker_dashboard_render_audit_panel( $mode, $site_urls ) {
	$is_legacy = 'legacy' === $mode;
	?>
	<div class="uu-widget-tracker-panel">
		<h2><?php echo esc_html( $is_legacy ? __( 'Legacy Audit Export', 'uu-widget-tracker-dashboard' ) : __( 'Current Usage Audit Export', 'uu-widget-tracker-dashboard' ) ); ?></h2>
		<p class="description uu-widget-tracker-panel-intro">
			<?php echo esc_html( $is_legacy ? __( 'Run a scoped legacy audit for one selected network. Classic widget hits will be flagged as widget-area usage rather than exact page URLs.', 'uu-widget-tracker-dashboard' ) : __( 'Run a cross-site audit of the remote item catalog to build a spreadsheet-ready usage summary.', 'uu-widget-tracker-dashboard' ) ); ?>
		</p>
		<?php if ( $is_legacy && ! empty( $site_urls ) ) : ?>
			<p>
				<label for="uu_widget_audit_scope"><?php esc_html_e( 'Audit this site/network', 'uu-widget-tracker-dashboard' ); ?></label>
				<select id="uu_widget_audit_scope" class="regular-text">
					<option value=""><?php esc_html_e( '— Select saved URL —', 'uu-widget-tracker-dashboard' ); ?></option>
					<?php foreach ( $site_urls as $site_url ) : ?>
						<option value="<?php echo esc_attr( uu_widget_tracker_dashboard_normalize_base_url( $site_url ) ); ?>"><?php echo esc_html( uu_widget_tracker_dashboard_normalize_base_url( $site_url ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
		<?php endif; ?>
		<p>
			<button type="button" class="button button-secondary" id="uu-widget-tracker-audit-button"><?php esc_html_e( 'Run audit export', 'uu-widget-tracker-dashboard' ); ?></button>
		</p>
		<div id="uu-widget-tracker-audit-spinner-wrap" class="uu-widget-tracker-spinner-wrap" aria-hidden="true">
			<span class="uu-widget-tracker-spinner"></span>
			<span class="uu-widget-tracker-spinner-text"><?php esc_html_e( 'Auditing tracked items…', 'uu-widget-tracker-dashboard' ); ?></span>
		</div>
		<div id="uu-widget-tracker-audit-results" class="uu-widget-tracker-results"></div>
	</div>
	<?php
}

function uu_widget_tracker_dashboard_render_discovery_panel( $site_urls ) {
	?>
	<div class="uu-widget-tracker-panel">
		<h2><?php esc_html_e( 'Discovery Signals', 'uu-widget-tracker-dashboard' ); ?></h2>
		<p class="description uu-widget-tracker-panel-intro"><?php esc_html_e( 'Use discovery to collect candidate SiteOrigin classes, classic widget IDs, and shortcode-like content markers from one selected network. These are hints for building legacy definition packs, not confirmed page-usage results.', 'uu-widget-tracker-dashboard' ); ?></p>
		<?php if ( ! empty( $site_urls ) ) : ?>
			<p>
				<label for="uu_widget_discovery_scope"><?php esc_html_e( 'Discover on this site/network', 'uu-widget-tracker-dashboard' ); ?></label>
				<select id="uu_widget_discovery_scope" class="regular-text">
					<option value=""><?php esc_html_e( '— Select saved URL —', 'uu-widget-tracker-dashboard' ); ?></option>
					<?php foreach ( $site_urls as $site_url ) : ?>
						<option value="<?php echo esc_attr( uu_widget_tracker_dashboard_normalize_base_url( $site_url ) ); ?>"><?php echo esc_html( uu_widget_tracker_dashboard_normalize_base_url( $site_url ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
		<?php endif; ?>
		<p>
			<button type="button" class="button" id="uu-widget-tracker-discovery-button"><?php esc_html_e( 'Fetch discovery signals', 'uu-widget-tracker-dashboard' ); ?></button>
		</p>
		<div id="uu-widget-tracker-discovery-spinner-wrap" class="uu-widget-tracker-spinner-wrap" aria-hidden="true">
			<span class="uu-widget-tracker-spinner"></span>
			<span class="uu-widget-tracker-spinner-text"><?php esc_html_e( 'Fetching discovery signals…', 'uu-widget-tracker-dashboard' ); ?></span>
		</div>
		<div id="uu-widget-tracker-discovery-results" class="uu-widget-tracker-results"></div>
	</div>
	<?php
}

function uu_widget_tracker_dashboard_render_page( $mode ) {
	$option_value = get_option( UU_WIDGET_TRACKER_DASHBOARD_OPTION, '' );
	$site_urls    = uu_widget_tracker_dashboard_get_configured_site_urls();
	$items_list   = uu_widget_tracker_dashboard_get_local_item_list();
	$is_legacy    = 'legacy' === $mode;
	?>
	<div class="wrap">
		<h1><?php echo esc_html( $is_legacy ? __( 'UU Legacy Audit', 'uu-widget-tracker-dashboard' ) : __( 'UU Current Usage', 'uu-widget-tracker-dashboard' ) ); ?></h1>
		<?php uu_widget_tracker_dashboard_render_settings_form( $option_value ); ?>
		<hr />
		<?php uu_widget_tracker_dashboard_render_lookup_form( $mode, $items_list, $site_urls ); ?>
		<?php uu_widget_tracker_dashboard_render_audit_panel( $mode, $site_urls ); ?>
		<?php if ( $is_legacy ) : ?>
			<?php uu_widget_tracker_dashboard_render_discovery_panel( $site_urls ); ?>
		<?php endif; ?>
	</div>
	<?php
}

function uu_widget_tracker_dashboard_render_current_page() {
	uu_widget_tracker_dashboard_render_page( 'current' );
}

function uu_widget_tracker_dashboard_render_legacy_page() {
	uu_widget_tracker_dashboard_render_page( 'legacy' );
}
