<?php
/**
 * Shared runtime helpers for report scripts that query remote usage endpoints.
 */

require_once __DIR__ . '/audit-cli-common.php';

if ( ! function_exists( 'uu_report_snapshot_arg' ) ) {
	/**
	 * Return the optional snapshot file path from parsed CLI args.
	 *
	 * @param array<string, string> $args Parsed CLI args.
	 * @return string
	 */
	function uu_report_snapshot_arg( array $args ) {
		return isset( $args['snapshot'] ) ? trim( (string) $args['snapshot'] ) : '';
	}
}

if ( ! function_exists( 'uu_report_load_snapshot_cache' ) ) {
	/**
	 * Load a cached usage snapshot from disk.
	 *
	 * @param string $snapshot_file Absolute path to snapshot JSON.
	 * @return array<string, array<string, mixed>>
	 */
	function uu_report_load_snapshot_cache( $snapshot_file ) {
		$snapshot_file = trim( (string) $snapshot_file );
		if ( '' === $snapshot_file || ! file_exists( $snapshot_file ) || ! is_readable( $snapshot_file ) ) {
			return array();
		}

		$data = json_decode( (string) file_get_contents( $snapshot_file ), true );
		if ( ! is_array( $data ) ) {
			return array();
		}

		return $data;
	}
}

if ( ! function_exists( 'uu_report_save_snapshot_cache' ) ) {
	/**
	 * Save a cached usage snapshot to disk.
	 *
	 * @param string                              $snapshot_file Absolute path to snapshot JSON.
	 * @param array<string, array<string, mixed>> $cache         Usage payload cache.
	 * @return void
	 */
	function uu_report_save_snapshot_cache( $snapshot_file, array $cache ) {
		$snapshot_file = trim( (string) $snapshot_file );
		if ( '' === $snapshot_file ) {
			return;
		}

		$dir = dirname( $snapshot_file );
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0775, true );
		}

		ksort( $cache, SORT_NATURAL );

		$json = function_exists( 'wp_json_encode' )
			? wp_json_encode( $cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
			: json_encode( $cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		if ( false === $json ) {
			return;
		}

		$tmp_file = $snapshot_file . '.' . getmypid() . '.tmp';
		file_put_contents( $tmp_file, $json );
		rename( $tmp_file, $snapshot_file );
	}
}

if ( ! function_exists( 'uu_report_fetch_usage_cached' ) ) {
	/**
	 * Fetch and cache a usage payload for a specific item on a specific environment.
	 *
	 * @param array<string, array<string, mixed>> $cache    Mutable cache array.
	 * @param string                              $base_url Environment base URL.
	 * @param string                              $item_key Item slug/key.
	 * @return array{url: string, data: array<string, mixed>|null, error: string}
	 */
	function uu_report_fetch_usage_cached( array &$cache, $base_url, $item_key ) {
		$cache_key = uu_audit_normalize_url( (string) $base_url ) . '|' . (string) $item_key;
		if ( ! isset( $cache[ $cache_key ] ) ) {
			$cache[ $cache_key ] = uu_audit_fetch_json(
				$base_url,
				array(
					'wp-json/uu-usage-tracker/v1/usage?item=' . rawurlencode( (string) $item_key ),
					'wp-json/uu-widget-tracker/v1/usage?widget=' . rawurlencode( (string) $item_key ),
				)
			);
		}

		return $cache[ $cache_key ];
	}
}

if ( ! function_exists( 'uu_report_fetch_items_cached' ) ) {
	/**
	 * Fetch and cache the remote tracker item catalog for a specific environment.
	 *
	 * @param array<string, array<string, mixed>> $cache    Mutable cache array.
	 * @param string                              $base_url Environment base URL.
	 * @return array{url: string, data: array<string, mixed>|null, error: string}
	 */
	function uu_report_fetch_items_cached( array &$cache, $base_url ) {
		$cache_key = uu_audit_normalize_url( (string) $base_url ) . '|__items__';
		if ( ! isset( $cache[ $cache_key ] ) ) {
			$cache[ $cache_key ] = uu_audit_fetch_json(
				$base_url,
				array(
					'wp-json/uu-usage-tracker/v1/items',
					'wp-json/uu-widget-tracker/v1/items',
				)
			);
		}

		return $cache[ $cache_key ];
	}
}

if ( ! function_exists( 'uu_report_remote_item_defined' ) ) {
	/**
	 * Determine whether the remote tracker catalog includes a specific item slug.
	 *
	 * @param array<string, mixed>|null $remote_data Remote items payload data.
	 * @param string                    $item_slug   Item slug/key.
	 * @return bool|null True/false when the catalog is readable, null when it is not.
	 */
	function uu_report_remote_item_defined( $remote_data, $item_slug ) {
		if ( ! is_array( $remote_data ) ) {
			return null;
		}

		$item_slug = trim( (string) $item_slug );
		if ( '' === $item_slug ) {
			return false;
		}

		$catalog_items = array();
		foreach ( array( 'items', 'widgets' ) as $catalog_key ) {
			if ( empty( $remote_data[ $catalog_key ] ) || ! is_array( $remote_data[ $catalog_key ] ) ) {
				continue;
			}

			foreach ( $remote_data[ $catalog_key ] as $item ) {
				if ( is_array( $item ) && isset( $item['slug'] ) ) {
					$catalog_items[] = trim( (string) $item['slug'] );
				} elseif ( is_string( $item ) ) {
					$catalog_items[] = trim( $item );
				}
			}
		}

		if ( empty( $catalog_items ) ) {
			return null;
		}

		return in_array( $item_slug, array_unique( array_filter( $catalog_items ) ), true );
	}
}

if ( ! function_exists( 'uu_report_remote_item_for_slug' ) ) {
	/**
	 * Return one item from the remote tracker catalog by slug.
	 *
	 * @param array<string, mixed>|null $remote_data Remote items payload data.
	 * @param string                    $item_slug   Item slug/key.
	 * @return array<string, mixed>
	 */
	function uu_report_remote_item_for_slug( $remote_data, $item_slug ) {
		if ( ! is_array( $remote_data ) ) {
			return array();
		}

		$item_slug = trim( (string) $item_slug );
		if ( '' === $item_slug ) {
			return array();
		}

		foreach ( array( 'items', 'widgets' ) as $catalog_key ) {
			if ( empty( $remote_data[ $catalog_key ] ) || ! is_array( $remote_data[ $catalog_key ] ) ) {
				continue;
			}

			foreach ( $remote_data[ $catalog_key ] as $item ) {
				if ( is_array( $item ) && isset( $item['slug'] ) && $item_slug === trim( (string) $item['slug'] ) ) {
					return $item;
				}
			}
		}

		return array();
	}
}

if ( ! function_exists( 'uu_report_build_plugin_context' ) ) {
	/**
	 * Build a normalized standalone-plugin usage context for one CSV row.
	 *
	 * @param array<string, mixed>                 $row   Source CSV row.
	 * @param array<string, string>                $map   Environment URL map.
	 * @param array<string, array<string, mixed>> &$cache Mutable usage cache.
	 * @return array<string, mixed>
	 */
	function uu_report_build_plugin_context( array $row, array $map, array &$cache ) {
		$multisite = isset( $row['Multisite'] ) ? trim( (string) $row['Multisite'] ) : '';
		$item_slug = isset( $row['Tracked Item Slug'] ) ? trim( (string) $row['Tracked Item Slug'] ) : '';
		$context   = array(
			'row'                 => $row,
			'multisite'           => $multisite,
			'item_slug'           => $item_slug,
			'base_url'            => '',
			'items_payload'       => array( 'url' => '', 'data' => null, 'error' => '' ),
			'remote_item_defined' => null,
			'remote_item'         => array(),
			'payload'             => array( 'url' => '', 'data' => null, 'error' => '' ),
			'data'                => array(),
			'lookup_error'        => '',
		);

		if ( '' === $multisite || '' === $item_slug ) {
			$context['lookup_error'] = 'Missing Multisite or Tracked Item Slug';
			return $context;
		}

		if ( empty( $map[ $multisite ] ) ) {
			$context['lookup_error'] = 'Missing multisite URL mapping';
			return $context;
		}

		$context['base_url']      = uu_audit_normalize_url( (string) $map[ $multisite ] );
		$context['items_payload'] = uu_report_fetch_items_cached( $cache, $context['base_url'] );
		if ( empty( $context['items_payload']['error'] ) ) {
			$context['remote_item_defined'] = uu_report_remote_item_defined( $context['items_payload']['data'], $item_slug );
			if ( false === $context['remote_item_defined'] ) {
				$context['lookup_error'] = 'Tracked item is not defined by remote tracker';
				return $context;
			}
			$context['remote_item'] = uu_report_remote_item_for_slug( $context['items_payload']['data'], $item_slug );
		}

		$context['payload']  = uu_report_fetch_usage_cached( $cache, $context['base_url'], $item_slug );
		$context['data']     = is_array( $context['payload']['data'] ) ? $context['payload']['data'] : array();
		if ( ! empty( $context['payload']['error'] ) ) {
			$context['lookup_error'] = (string) $context['payload']['error'];
		}

		return $context;
	}
}

if ( ! function_exists( 'uu_report_each_plugin_context' ) ) {
	/**
	 * Iterate normalized standalone-plugin usage contexts.
	 *
	 * @param array<int, array<string, mixed>>     $rows   Source CSV rows.
	 * @param array<string, string>                $map    Environment URL map.
	 * @param array<string, array<string, mixed>> &$cache  Mutable usage cache.
	 * @param callable                             $callback Callback receiving context array.
	 * @return void
	 */
	function uu_report_each_plugin_context( array $rows, array $map, array &$cache, callable $callback ) {
		foreach ( $rows as $row ) {
			$callback( uu_report_build_plugin_context( $row, $map, $cache ) );
		}
	}
}

if ( ! function_exists( 'uu_report_build_widget_contexts' ) ) {
	/**
	 * Build normalized widget usage contexts for one registry row across its environments.
	 *
	 * @param array<string, mixed>                 $row   Source registry row.
	 * @param array<string, string>                $map   Environment URL map.
	 * @param array<string, array<string, mixed>> &$cache Mutable usage cache.
	 * @return array<int, array<string, mixed>>
	 */
	function uu_report_build_widget_contexts( array $row, array $map, array &$cache ) {
		$contexts     = array();
		$canonical_slug = isset( $row['Canonical Slug'] ) ? trim( (string) $row['Canonical Slug'] ) : '';
		$environments = array_filter( array_map( 'trim', explode( '|', (string) ( $row['Environment Labels'] ?? '' ) ) ) );

		foreach ( $environments as $environment_label ) {
			$context = array(
				'row'               => $row,
				'environment_label' => $environment_label,
				'canonical_slug'    => $canonical_slug,
				'base_url'          => '',
				'payload'           => array( 'url' => '', 'data' => null, 'error' => '' ),
				'data'              => array(),
				'lookup_error'      => '',
			);

			if ( '' === $canonical_slug ) {
				$context['lookup_error'] = 'Missing Canonical Slug';
				$contexts[]              = $context;
				continue;
			}

			if ( empty( $map[ $environment_label ] ) ) {
				$context['lookup_error'] = 'Missing environment URL mapping';
				$contexts[]              = $context;
				continue;
			}

			$context['base_url'] = uu_audit_normalize_url( (string) $map[ $environment_label ] );
			$context['payload']  = uu_report_fetch_usage_cached( $cache, $context['base_url'], $canonical_slug );
			$context['data']     = is_array( $context['payload']['data'] ) ? $context['payload']['data'] : array();
			if ( ! empty( $context['payload']['error'] ) ) {
				$context['lookup_error'] = (string) $context['payload']['error'];
			}

			$contexts[] = $context;
		}

		return $contexts;
	}
}

if ( ! function_exists( 'uu_report_each_widget_context' ) ) {
	/**
	 * Iterate normalized widget usage contexts from registry rows.
	 *
	 * @param array<int, array<string, mixed>>     $rows   Source registry rows.
	 * @param array<string, string>                $map    Environment URL map.
	 * @param array<string, array<string, mixed>> &$cache  Mutable usage cache.
	 * @param callable                             $callback Callback receiving context array.
	 * @return void
	 */
	function uu_report_each_widget_context( array $rows, array $map, array &$cache, callable $callback ) {
		foreach ( $rows as $row ) {
			foreach ( uu_report_build_widget_contexts( $row, $map, $cache ) as $context ) {
				$callback( $context );
			}
		}
	}
}

if ( ! function_exists( 'uu_report_usage_posts' ) ) {
	/**
	 * Return normalized post rows from a usage payload.
	 *
	 * @param array<string, mixed> $remote_data Remote usage payload.
	 * @return array<int, array<string, mixed>>
	 */
	function uu_report_usage_posts( array $remote_data ) {
		if ( empty( $remote_data['posts'] ) || ! is_array( $remote_data['posts'] ) ) {
			return array();
		}

		return $remote_data['posts'];
	}
}

if ( ! function_exists( 'uu_report_match_count' ) ) {
	/**
	 * Count matched usage rows.
	 *
	 * @param array<string, mixed> $remote_data Remote usage payload.
	 * @return int
	 */
	function uu_report_match_count( array $remote_data ) {
		return count( uu_report_usage_posts( $remote_data ) );
	}
}

if ( ! function_exists( 'uu_report_match_sources' ) ) {
	/**
	 * Return distinct match sources from a usage payload.
	 *
	 * @param array<string, mixed> $remote_data Remote usage payload.
	 * @return array<int, string>
	 */
	function uu_report_match_sources( array $remote_data ) {
		$sources = array();
		foreach ( uu_report_usage_posts( $remote_data ) as $post ) {
			if ( empty( $post['match_sources'] ) || ! is_array( $post['match_sources'] ) ) {
				continue;
			}
			foreach ( $post['match_sources'] as $source ) {
				$source = trim( (string) $source );
				if ( '' !== $source && ! in_array( $source, $sources, true ) ) {
					$sources[] = $source;
				}
			}
		}

		sort( $sources, SORT_NATURAL );

		return $sources;
	}
}

if ( ! function_exists( 'uu_report_match_sources_label' ) ) {
	/**
	 * Return a display label for match sources in a usage payload.
	 *
	 * @param array<string, mixed> $remote_data Remote usage payload.
	 * @return string
	 */
	function uu_report_match_sources_label( array $remote_data ) {
		return implode( ', ', uu_report_match_sources( $remote_data ) );
	}
}

if ( ! function_exists( 'uu_report_post_match_sources_label' ) ) {
	/**
	 * Return a display label for match sources on a single post row.
	 *
	 * @param array<string, mixed> $post Usage post row.
	 * @return string
	 */
	function uu_report_post_match_sources_label( array $post ) {
		if ( empty( $post['match_sources'] ) || ! is_array( $post['match_sources'] ) ) {
			return '';
		}

		$sources = array();
		foreach ( $post['match_sources'] as $source ) {
			$source = trim( (string) $source );
			if ( '' !== $source && ! in_array( $source, $sources, true ) ) {
				$sources[] = $source;
			}
		}

		sort( $sources, SORT_NATURAL );

		return implode( ', ', $sources );
	}
}

if ( ! function_exists( 'uu_report_activation_data' ) ) {
	/**
	 * Return normalized activation data from a usage payload.
	 *
	 * @param array<string, mixed> $remote_data Remote usage payload.
	 * @return array<string, mixed>
	 */
	function uu_report_activation_data( array $remote_data ) {
		if ( empty( $remote_data['activation'] ) || ! is_array( $remote_data['activation'] ) ) {
			return array();
		}

		return $remote_data['activation'];
	}
}

if ( ! function_exists( 'uu_report_activation_label' ) ) {
	/**
	 * Return the activation status label from a usage payload.
	 *
	 * @param array<string, mixed> $remote_data Remote usage payload.
	 * @return string
	 */
	function uu_report_activation_label( array $remote_data ) {
		$activation = uu_report_activation_data( $remote_data );
		if ( empty( $activation ) ) {
			return 'Unknown';
		}

		return isset( $activation['status'] ) ? (string) $activation['status'] : 'Unknown';
	}
}

if ( ! function_exists( 'uu_report_sample_urls' ) ) {
	/**
	 * Return a small list of sample URLs from a usage payload.
	 *
	 * @param array<string, mixed> $remote_data Remote usage payload.
	 * @param int                  $limit       Maximum number of URLs.
	 * @return array<int, string>
	 */
	function uu_report_sample_urls( array $remote_data, $limit = 5 ) {
		$urls = array();
		foreach ( uu_report_usage_posts( $remote_data ) as $post ) {
			if ( ! empty( $post['permalink'] ) && is_string( $post['permalink'] ) && ! in_array( $post['permalink'], $urls, true ) ) {
				$urls[] = $post['permalink'];
			}
			if ( count( $urls ) >= (int) $limit ) {
				break;
			}
		}

		return $urls;
	}
}

if ( ! function_exists( 'uu_report_signal_strength' ) ) {
	/**
	 * Determine confidence from signal strength.
	 *
	 * @param string              $signal_type   Declared signal type text.
	 * @param array<int, string>  $match_sources Observed match sources.
	 * @return string
	 */
	function uu_report_signal_strength( $signal_type, array $match_sources ) {
		$strong_sources = array( 'siteorigin_class', 'content_substring', 'classic_widget', 'page_template' );
		$medium_sources = array( 'page_slug', 'post_meta_key', 'post_meta_value' );
		$weak_sources   = array( 'page_title' );

		foreach ( $match_sources as $source ) {
			if ( in_array( $source, $strong_sources, true ) ) {
				return 'High';
			}
		}

		foreach ( $match_sources as $source ) {
			if ( in_array( $source, $medium_sources, true ) ) {
				return 'Medium';
			}
		}

		foreach ( $match_sources as $source ) {
			if ( in_array( $source, $weak_sources, true ) ) {
				return 'Low';
			}
		}

		$signal_type = strtolower( trim( (string) $signal_type ) );
		if ( false !== strpos( $signal_type, 'siteorigin' ) || false !== strpos( $signal_type, 'shortcode' ) || false !== strpos( $signal_type, 'classic_widget_id' ) || false !== strpos( $signal_type, 'page template' ) ) {
			return 'High';
		}
		if ( false !== strpos( $signal_type, 'page slug' ) || false !== strpos( $signal_type, 'meta' ) ) {
			return 'Medium';
		}

		return 'Low';
	}
}

if ( ! function_exists( 'uu_report_default_confidence' ) ) {
	/**
	 * Generic confidence policy for summary reports.
	 *
	 * @param array<string, mixed> $remote_data Remote usage payload.
	 * @param int                  $match_count Match count.
	 * @param string               $signal_type Signal type label.
	 * @param bool                 $force_low   Force low confidence for ambiguous rows.
	 * @return string
	 */
	function uu_report_default_confidence( array $remote_data, $match_count, $signal_type = '', $force_low = false ) {
		$activation = uu_report_activation_data( $remote_data );

		if ( (int) $match_count > 0 ) {
			return uu_report_signal_strength( $signal_type, uu_report_match_sources( $remote_data ) );
		}

		if ( $force_low ) {
			return 'Low';
		}

		if ( ! empty( $activation['network_active'] ) ) {
			return 'Medium';
		}

		if ( ! empty( $activation['active_blog_count'] ) ) {
			return 'Medium';
		}

		if ( isset( $activation['active_blog_count'], $activation['scanned_blog_count'] ) && 0 === (int) $activation['active_blog_count'] ) {
			return 'High';
		}

		return 'Medium';
	}
}

if ( ! function_exists( 'uu_report_default_action' ) ) {
	/**
	 * Generic action policy for summary reports.
	 *
	 * @param array<string, mixed> $remote_data   Remote usage payload.
	 * @param int                  $match_count   Match count.
	 * @param bool                 $force_review  Force review outcome.
	 * @param bool                 $force_define  Force define outcome.
	 * @return string
	 */
	function uu_report_default_action( array $remote_data, $match_count, $force_review = false, $force_define = false ) {
		$activation = uu_report_activation_data( $remote_data );

		if ( $force_define ) {
			return 'Define';
		}

		if ( (int) $match_count > 0 ) {
			return 'Keep';
		}

		if ( $force_review ) {
			return 'Review';
		}

		if ( ! empty( $activation['network_active'] ) || ! empty( $activation['active_blog_count'] ) ) {
			return 'Review';
		}

		if ( isset( $activation['active_blog_count'] ) && 0 === (int) $activation['active_blog_count'] ) {
			return 'Decommission candidate';
		}

		return 'Review';
	}
}

if ( ! function_exists( 'uu_report_plugin_context_signal_strength' ) ) {
	/**
	 * Return normalized standalone-plugin signal strength for a report context.
	 *
	 * @param array<string, mixed> $context Normalized plugin context.
	 * @return string strong, medium, weak, or missing.
	 */
	function uu_report_plugin_context_signal_strength( array $context ) {
		$remote_item = isset( $context['remote_item'] ) && is_array( $context['remote_item'] ) ? $context['remote_item'] : array();
		if ( ! empty( $remote_item['signal_strength'] ) ) {
			$signal_strength = strtolower( preg_replace( '/[^a-z0-9_-]/', '', (string) $remote_item['signal_strength'] ) );
			if ( in_array( $signal_strength, array( 'strong', 'medium', 'weak', 'missing' ), true ) ) {
				return $signal_strength;
			}
		}

		$row         = isset( $context['row'] ) && is_array( $context['row'] ) ? $context['row'] : array();
		$signal_type = (string) ( $row['Signal Type'] ?? '' );
		if ( '' === trim( $signal_type ) ) {
			return 'missing';
		}

		$strength = uu_report_signal_strength( $signal_type, array() );
		if ( 'High' === $strength ) {
			return 'strong';
		}
		if ( 'Medium' === $strength ) {
			return 'medium';
		}

		return 'weak';
	}
}

if ( ! function_exists( 'uu_report_plugin_usage_status' ) ) {
	/**
	 * Return the tiered truth status for a standalone-plugin context.
	 *
	 * @param array<string, mixed> $context     Normalized plugin context.
	 * @param int|null             $match_count Optional match count.
	 * @return string
	 */
	function uu_report_plugin_usage_status( array $context, $match_count = null ) {
		if ( ! empty( $context['lookup_error'] ) ) {
			if ( 'Tracked item is not defined by remote tracker' === (string) $context['lookup_error'] ) {
				return 'Not Defined By Remote Tracker';
			}

			return 'Remote Lookup Error';
		}

		$data        = isset( $context['data'] ) && is_array( $context['data'] ) ? $context['data'] : array();
		$match_count = null === $match_count ? uu_report_match_count( $data ) : (int) $match_count;
		$activation  = uu_report_activation_data( $data );
		$is_active   = ! empty( $activation['network_active'] ) || ! empty( $activation['active_blog_count'] );
		$strength    = uu_report_plugin_context_signal_strength( $context );

		if ( $match_count > 0 ) {
			return in_array( $strength, array( 'strong', 'medium' ), true ) ? 'Confirmed Page Match' : 'Weak Signal / Needs Marker';
		}

		if ( $is_active && in_array( $strength, array( 'missing', 'weak' ), true ) ) {
			return 'Weak Signal / Needs Marker';
		}

		if ( $is_active ) {
			return 'Active, No Page Match';
		}

		return 'Defined, Not Active';
	}
}

if ( ! function_exists( 'uu_report_plugin_action_for_status' ) ) {
	/**
	 * Return an action label for a tiered standalone-plugin status.
	 *
	 * @param string $status Usage status.
	 * @return string
	 */
	function uu_report_plugin_action_for_status( $status ) {
		switch ( (string) $status ) {
			case 'Confirmed Page Match':
				return 'Keep';
			case 'Active, No Page Match':
				return 'Review';
			case 'Defined, Not Active':
				return 'Decommission candidate';
			case 'Not Defined By Remote Tracker':
				return 'Define';
			case 'Weak Signal / Needs Marker':
				return 'Add Marker';
			case 'Remote Lookup Error':
			default:
				return 'Review';
		}
	}
}

if ( ! function_exists( 'uu_report_plugin_summary_row' ) ) {
	/**
	 * Build a standalone-plugin summary row from a normalized context.
	 *
	 * @param array<string, mixed> $context Normalized plugin context.
	 * @return array<string, mixed>
	 */
	function uu_report_plugin_summary_row( array $context ) {
		$row = $context['row'];

		if ( ! empty( $context['lookup_error'] ) ) {
			$status              = uu_report_plugin_usage_status( $context );
			$row['Usage Status'] = $status;
			$row['Lookup Error'] = (string) $context['lookup_error'];
			$row['Confidence']   = 'Low';
			$row['Action']       = uu_report_plugin_action_for_status( $status );
			$row['Plugin Activation'] = 'Unknown';
			$row['Matches Found']     = 'Tracked item is not defined by remote tracker' === $context['lookup_error'] ? 'Not defined' : 'Error';
			$row['Matched By']        = '';
			$row['Sample URLs']       = '';
			return $row;
		}

		$data        = $context['data'];
		$match_count = uu_report_match_count( $data );
		$status      = uu_report_plugin_usage_status( $context, $match_count );

		$row['Usage Status']      = $status;
		$row['Plugin Activation'] = uu_report_activation_label( $data );
		$row['Matches Found']     = (string) $match_count;
		$row['Matched By']        = uu_report_match_sources_label( $data );
		$row['Sample URLs']       = implode( ' | ', uu_report_sample_urls( $data ) );
		$row['Lookup Error']      = '';
		$row['Confidence']        = 'Weak Signal / Needs Marker' === $status ? 'Low' : uu_report_default_confidence( $data, $match_count, (string) ( $row['Signal Type'] ?? '' ), 'Needs definition work' === ( $row['Category'] ?? '' ) );
		$row['Action']            = uu_report_plugin_action_for_status( $status );

		return $row;
	}
}

if ( ! function_exists( 'uu_report_widget_summary_row' ) ) {
	/**
	 * Build a widget summary row from a normalized context.
	 *
	 * @param array<string, mixed> $context Normalized widget context.
	 * @return array<string, mixed>
	 */
	function uu_report_widget_summary_row( array $context ) {
		$row               = $context['row'];
		$environment_label = (string) ( $context['environment_label'] ?? '' );
		$base_url          = (string) ( $context['base_url'] ?? '' );
		$canonical_slug    = (string) ( $context['canonical_slug'] ?? '' );

		if ( ! empty( $context['lookup_error'] ) ) {
			return array(
				'Environment Label' => $environment_label,
				'Base URL'          => $base_url,
				'Canonical Slug'    => $canonical_slug,
				'Preferred Label'   => (string) ( $row['Preferred Label'] ?? '' ),
				'Widget Type'       => (string) ( $row['Widget Type'] ?? '' ),
				'Widget Class'      => (string) ( $row['Widget Class'] ?? '' ),
				'Classic Widget ID' => (string) ( $row['Classic Widget ID'] ?? '' ),
				'Bundle / Family'   => (string) ( $row['Bundle / Family'] ?? '' ),
				'Environment Count' => (string) ( $row['Environment Count'] ?? '' ),
				'Seen In'           => (string) ( $row['Seen In'] ?? '' ),
				'Plugin Activation' => 'Unknown',
				'Matches Found'     => 'Error',
				'Confidence'        => 'Low',
				'Action'            => 'Review',
				'Matched By'        => '',
				'Sample URLs'       => '',
				'Needs Review'      => (string) ( $row['Needs Review'] ?? '' ),
				'Notes'             => (string) ( $row['Notes'] ?? '' ),
				'Lookup Error'      => (string) $context['lookup_error'],
			);
		}

		$data        = $context['data'];
		$match_count = uu_report_match_count( $data );

		return array(
			'Environment Label' => $environment_label,
			'Base URL'          => $base_url,
			'Canonical Slug'    => $canonical_slug,
			'Preferred Label'   => (string) ( $row['Preferred Label'] ?? '' ),
			'Widget Type'       => (string) ( $row['Widget Type'] ?? '' ),
			'Widget Class'      => (string) ( $row['Widget Class'] ?? '' ),
			'Classic Widget ID' => (string) ( $row['Classic Widget ID'] ?? '' ),
			'Bundle / Family'   => (string) ( $row['Bundle / Family'] ?? '' ),
			'Environment Count' => (string) ( $row['Environment Count'] ?? '' ),
			'Seen In'           => (string) ( $row['Seen In'] ?? '' ),
			'Plugin Activation' => uu_report_activation_label( $data ),
			'Matches Found'     => (string) $match_count,
			'Confidence'        => uu_report_default_confidence( $data, $match_count, '', 'Yes' === (string) ( $row['Needs Review'] ?? '' ) ),
			'Action'            => uu_report_default_action( $data, $match_count, 'Yes' === (string) ( $row['Needs Review'] ?? '' ), false ),
			'Matched By'        => uu_report_match_sources_label( $data ),
			'Sample URLs'       => implode( ' | ', uu_report_sample_urls( $data ) ),
			'Needs Review'      => (string) ( $row['Needs Review'] ?? '' ),
			'Notes'             => (string) ( $row['Notes'] ?? '' ),
			'Lookup Error'      => '',
		);
	}
}

if ( ! function_exists( 'uu_report_plugin_matched_url_rows' ) ) {
	/**
	 * Build standalone-plugin matched URL rows from a normalized context.
	 *
	 * @param array<string, mixed> $context Normalized plugin context.
	 * @return array<int, array<string, mixed>>
	 */
	function uu_report_plugin_matched_url_rows( array $context ) {
		if ( ! empty( $context['lookup_error'] ) ) {
			return array();
		}

		$row  = $context['row'];
		$data = $context['data'];
		if ( empty( $data['posts'] ) || ! is_array( $data['posts'] ) ) {
			return array();
		}

		$detail_rows = array();
		foreach ( $data['posts'] as $post ) {
			$detail_rows[] = array(
				'Multisite'         => (string) $context['multisite'],
				'Plugin Folder'     => isset( $row['Plugin Folder'] ) ? $row['Plugin Folder'] : '',
				'Tracked Item Slug' => (string) $context['item_slug'],
				'Category'          => isset( $row['Category'] ) ? $row['Category'] : '',
				'Signal Type'       => isset( $row['Signal Type'] ) ? $row['Signal Type'] : '',
				'Plugin Activation' => uu_report_activation_label( $data ),
				'Site Name'         => isset( $post['site_name'] ) ? $post['site_name'] : '',
				'Multisite Name'    => isset( $post['network_name'] ) ? $post['network_name'] : '',
				'Blog ID'           => isset( $post['blog_id'] ) ? (string) $post['blog_id'] : '',
				'Post ID'           => isset( $post['post_id'] ) ? (string) $post['post_id'] : '',
				'Result Title'      => isset( $post['title'] ) ? $post['title'] : '',
				'Result Type'       => isset( $post['result_type'] ) ? $post['result_type'] : '',
				'Matched By'        => uu_report_post_match_sources_label( $post ),
				'Permalink'         => isset( $post['permalink'] ) ? $post['permalink'] : '',
				'Lookup Endpoint'   => isset( $context['payload']['url'] ) ? $context['payload']['url'] : '',
				'Lookup Error'      => isset( $context['payload']['error'] ) ? $context['payload']['error'] : '',
			);
		}

		return $detail_rows;
	}
}

if ( ! function_exists( 'uu_report_widget_matched_url_rows' ) ) {
	/**
	 * Build widget matched URL rows from a normalized context.
	 *
	 * @param array<string, mixed> $context Normalized widget context.
	 * @return array<int, array<string, mixed>>
	 */
	function uu_report_widget_matched_url_rows( array $context ) {
		if ( ! empty( $context['lookup_error'] ) ) {
			return array();
		}

		$row  = $context['row'];
		$data = $context['data'];
		if ( empty( $data['posts'] ) || ! is_array( $data['posts'] ) ) {
			return array();
		}

		$output_rows = array();
		foreach ( $data['posts'] as $post ) {
			$output_rows[] = array(
				'Environment Label' => (string) $context['environment_label'],
				'Base URL'          => (string) $context['base_url'],
				'Canonical Slug'    => (string) $context['canonical_slug'],
				'Preferred Label'   => (string) ( $row['Preferred Label'] ?? '' ),
				'Widget Type'       => (string) ( $row['Widget Type'] ?? '' ),
				'Widget Class'      => (string) ( $row['Widget Class'] ?? '' ),
				'Classic Widget ID' => (string) ( $row['Classic Widget ID'] ?? '' ),
				'Bundle / Family'   => (string) ( $row['Bundle / Family'] ?? '' ),
				'Plugin Activation' => uu_report_activation_label( $data ),
				'Site Name'         => (string) ( $post['site_name'] ?? '' ),
				'Multisite Name'    => (string) ( $post['network_name'] ?? '' ),
				'Blog ID'           => isset( $post['blog_id'] ) ? (string) $post['blog_id'] : '',
				'Post ID'           => isset( $post['id'] ) ? (string) $post['id'] : '',
				'Result Title'      => (string) ( $post['title'] ?? '' ),
				'Result Type'       => (string) ( $post['post_type'] ?? ( $post['result_type'] ?? '' ) ),
				'Matched By'        => uu_report_post_match_sources_label( $post ),
				'Permalink'         => (string) ( $post['permalink'] ?? '' ),
				'Needs Review'      => (string) ( $row['Needs Review'] ?? '' ),
				'Notes'             => (string) ( $row['Notes'] ?? '' ),
				'Lookup Endpoint'   => (string) ( $context['payload']['url'] ?? '' ),
				'Lookup Error'      => (string) ( $context['payload']['error'] ?? '' ),
			);
		}

		return $output_rows;
	}
}

if ( ! function_exists( 'uu_report_plugin_activation_rows' ) ) {
	/**
	 * Build standalone-plugin blog activation detail rows from a normalized context.
	 *
	 * @param array<string, mixed> $context Normalized plugin context.
	 * @return array<int, array<string, mixed>>
	 */
	function uu_report_plugin_activation_rows( array $context ) {
		if ( ! empty( $context['lookup_error'] ) ) {
			return array();
		}

		$row        = $context['row'];
		$activation = uu_report_activation_data( $context['data'] );
		if ( empty( $activation['active_blogs'] ) || ! is_array( $activation['active_blogs'] ) ) {
			return array();
		}

		$matched_network_plugins = '';
		if ( ! empty( $activation['matched_network_plugins'] ) && is_array( $activation['matched_network_plugins'] ) ) {
			$matched_network_plugins = implode( ' | ', $activation['matched_network_plugins'] );
		}

		$detail_rows = array();
		foreach ( $activation['active_blogs'] as $blog ) {
			$matches = '';
			if ( ! empty( $blog['matches'] ) && is_array( $blog['matches'] ) ) {
				$matches = implode( ' | ', $blog['matches'] );
			}

			$detail_rows[] = array(
				'Multisite'               => (string) $context['multisite'],
				'Plugin Folder'           => isset( $row['Plugin Folder'] ) ? $row['Plugin Folder'] : '',
				'Tracked Item Slug'       => (string) $context['item_slug'],
				'Category'                => isset( $row['Category'] ) ? $row['Category'] : '',
				'Signal Type'             => isset( $row['Signal Type'] ) ? $row['Signal Type'] : '',
				'Activation Status'       => isset( $activation['status'] ) ? $activation['status'] : 'Unknown',
				'Network Active'          => ! empty( $activation['network_active'] ) ? 'Yes' : 'No',
				'Matched Network Plugins' => $matched_network_plugins,
				'Active Blog Count'       => isset( $activation['active_blog_count'] ) ? (string) $activation['active_blog_count'] : '',
				'Scanned Blog Count'      => isset( $activation['scanned_blog_count'] ) ? (string) $activation['scanned_blog_count'] : '',
				'Active Blog ID'          => isset( $blog['blog_id'] ) ? (string) $blog['blog_id'] : '',
				'Active Site Name'        => isset( $blog['site_name'] ) ? $blog['site_name'] : '',
				'Active Site URL'         => isset( $blog['site_url'] ) ? $blog['site_url'] : '',
				'Active Network Name'     => isset( $blog['network_name'] ) ? $blog['network_name'] : '',
				'Matched Plugin Files'    => $matches,
				'Lookup Endpoint'         => isset( $context['payload']['url'] ) ? $context['payload']['url'] : '',
				'Lookup Error'            => isset( $context['payload']['error'] ) ? $context['payload']['error'] : '',
			);
		}

		return $detail_rows;
	}
}

if ( ! function_exists( 'uu_report_widget_activation_rows' ) ) {
	/**
	 * Build widget blog activation detail rows from a normalized context.
	 *
	 * @param array<string, mixed> $context Normalized widget context.
	 * @return array<int, array<string, mixed>>
	 */
	function uu_report_widget_activation_rows( array $context ) {
		if ( ! empty( $context['lookup_error'] ) ) {
			return array();
		}

		$row        = $context['row'];
		$activation = uu_report_activation_data( $context['data'] );
		if ( empty( $activation['active_blogs'] ) || ! is_array( $activation['active_blogs'] ) ) {
			return array();
		}

		$matched_network_plugins = '';
		if ( ! empty( $activation['matched_network_plugins'] ) && is_array( $activation['matched_network_plugins'] ) ) {
			$matched_network_plugins = implode( ' | ', $activation['matched_network_plugins'] );
		}

		$output_rows = array();
		foreach ( $activation['active_blogs'] as $blog ) {
			$matches = '';
			if ( ! empty( $blog['matches'] ) && is_array( $blog['matches'] ) ) {
				$matches = implode( ' | ', $blog['matches'] );
			}

			$output_rows[] = array(
				'Environment Label'      => (string) $context['environment_label'],
				'Base URL'               => (string) $context['base_url'],
				'Canonical Slug'         => (string) $context['canonical_slug'],
				'Preferred Label'        => (string) ( $row['Preferred Label'] ?? '' ),
				'Widget Type'            => (string) ( $row['Widget Type'] ?? '' ),
				'Widget Class'           => (string) ( $row['Widget Class'] ?? '' ),
				'Classic Widget ID'      => (string) ( $row['Classic Widget ID'] ?? '' ),
				'Bundle / Family'        => (string) ( $row['Bundle / Family'] ?? '' ),
				'Activation Status'      => (string) ( $activation['status'] ?? 'Unknown' ),
				'Network Active'         => ! empty( $activation['network_active'] ) ? 'Yes' : 'No',
				'Matched Network Plugins'=> $matched_network_plugins,
				'Active Blog Count'      => isset( $activation['active_blog_count'] ) ? (string) $activation['active_blog_count'] : '',
				'Scanned Blog Count'     => isset( $activation['scanned_blog_count'] ) ? (string) $activation['scanned_blog_count'] : '',
				'Active Blog ID'         => isset( $blog['blog_id'] ) ? (string) $blog['blog_id'] : '',
				'Active Site Name'       => (string) ( $blog['site_name'] ?? '' ),
				'Active Site URL'        => (string) ( $blog['site_url'] ?? '' ),
				'Active Network Name'    => (string) ( $blog['network_name'] ?? '' ),
				'Matched Plugin Files'   => $matches,
				'Needs Review'           => (string) ( $row['Needs Review'] ?? '' ),
				'Notes'                  => (string) ( $row['Notes'] ?? '' ),
				'Lookup Endpoint'        => (string) ( $context['payload']['url'] ?? '' ),
				'Lookup Error'           => (string) ( $context['payload']['error'] ?? '' ),
			);
		}

		return $output_rows;
	}
}
