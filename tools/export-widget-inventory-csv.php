<?php
/**
 * Export a cross-site widget inventory from remote UU Usage Tracker endpoints.
 *
 * Usage:
 * php tools/export-widget-inventory-csv.php \
 *   --output=/abs/path/Widget_Audit.inventory.v1.csv \
 *   --map=/abs/path/multisite-map.json
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 1 );
}

require_once __DIR__ . '/audit-cli-common.php';

/**
 * Normalize a widget-ish string so classes / ids can be matched more flexibly.
 *
 * @param string $value Raw widget identifier.
 * @return string
 */
function uu_widget_inventory_normalize_key( $value ) {
	$value = strtolower( trim( (string) $value ) );
	$value = str_replace( array( '-', ' ' ), '_', $value );

	return preg_replace( '/[^a-z0-9_]/', '', $value );
}

/**
 * Return true when the remote item looks like a widget rather than a legacy plugin marker.
 *
 * @param array<string, mixed> $item Remote item row.
 * @return bool
 */
function uu_widget_inventory_is_widget_item( array $item ) {
	$kind = isset( $item['kind'] ) ? (string) $item['kind'] : '';

	return in_array( $kind, array( 'siteorigin_class', 'classic_widget' ), true );
}

/**
 * Build an inventory row from a catalog item plus discovery state.
 *
 * @param string              $site_label        Friendly environment label from the map file.
 * @param string              $site_url          Base site URL.
 * @param array<string, mixed> $item             Catalog item.
 * @param array<string, bool> $discovered_classes Discovered SiteOrigin classes.
 * @param array<string, bool> $discovered_ids     Discovered classic widget ids.
 * @return array<string, string>
 */
function uu_widget_inventory_build_catalog_row( $site_label, $site_url, array $item, array $discovered_classes, array $discovered_ids ) {
	$kind          = isset( $item['kind'] ) ? (string) $item['kind'] : '';
	$label         = isset( $item['label'] ) ? (string) $item['label'] : '';
	$slug          = isset( $item['slug'] ) ? (string) $item['slug'] : '';
	$widget_class  = isset( $item['class'] ) ? (string) $item['class'] : '';
	$search_for    = isset( $item['search_for'] ) ? $item['search_for'] : '';
	$search_values = is_array( $search_for ) ? array_values( array_filter( array_map( 'strval', $search_for ) ) ) : ( '' !== (string) $search_for ? array( (string) $search_for ) : array() );

	$seen_in          = 'registered_code';
	$discovery_source = 'item_catalog';
	$matched_value    = '';

	if ( 'siteorigin_class' === $kind ) {
		$candidates = array();
		if ( '' !== $widget_class ) {
			$candidates[] = $widget_class;
		}
		foreach ( $search_values as $value ) {
			if ( ! in_array( $value, $candidates, true ) ) {
				$candidates[] = $value;
			}
		}

		foreach ( $candidates as $candidate ) {
			$key = uu_widget_inventory_normalize_key( $candidate );
			if ( isset( $discovered_classes[ $key ] ) ) {
				$seen_in          = 'both';
				$discovery_source = 'item_catalog + panels_data';
				$matched_value    = $candidate;
				break;
			}
		}
	} elseif ( 'classic_widget' === $kind ) {
		foreach ( $search_values as $candidate ) {
			$key = uu_widget_inventory_normalize_key( $candidate );
			if ( isset( $discovered_ids[ $key ] ) ) {
				$seen_in          = 'both';
				$discovery_source = 'item_catalog + active_sidebars';
				$matched_value    = $candidate;
				break;
			}
		}
	}

	return array(
		'Environment Label'   => $site_label,
		'Base URL'            => $site_url,
		'Widget Type'         => 'siteorigin_class' === $kind ? 'siteorigin' : 'classic',
		'Catalog Slug'        => $slug,
		'Widget Label'        => $label,
		'Widget Class'        => $widget_class,
		'Classic Widget ID'   => 'classic_widget' === $kind ? implode( ' | ', $search_values ) : '',
		'Catalog Kind'        => $kind,
		'Search Values'       => implode( ' | ', $search_values ),
		'Seen In'             => $seen_in,
		'Discovery Source'    => $discovery_source,
		'Matched Discovery'   => $matched_value,
		'Plugin / Bundle'     => '',
		'Discovery Status'    => 'catalog_item',
		'Lookup Error'        => '',
	);
}

/**
 * Build inventory rows for discovered widgets that are not represented in the catalog.
 *
 * @param string              $site_label     Friendly environment label from the map file.
 * @param string              $site_url       Base site URL.
 * @param array<string, bool> $discovered     Discovered values keyed by normalized value.
 * @param array<string, bool> $catalog_keys   Known catalog values keyed by normalized value.
 * @param string              $widget_type    Either siteorigin or classic.
 * @param string              $discovery_name Human-readable source label.
 * @return array<int, array<string, string>>
 */
function uu_widget_inventory_build_discovered_only_rows( $site_label, $site_url, array $discovered, array $catalog_keys, $widget_type, $discovery_name ) {
	$rows = array();

	foreach ( $discovered as $normalized_value => $original_value ) {
		if ( isset( $catalog_keys[ $normalized_value ] ) ) {
			continue;
		}

		$rows[] = array(
			'Environment Label'   => $site_label,
			'Base URL'            => $site_url,
			'Widget Type'         => $widget_type,
			'Catalog Slug'        => '',
			'Widget Label'        => '',
			'Widget Class'        => 'siteorigin' === $widget_type ? $original_value : '',
			'Classic Widget ID'   => 'classic' === $widget_type ? $original_value : '',
			'Catalog Kind'        => '',
			'Search Values'       => $original_value,
			'Seen In'             => 'saved_content',
			'Discovery Source'    => $discovery_name,
			'Matched Discovery'   => $original_value,
			'Plugin / Bundle'     => '',
			'Discovery Status'    => 'discovered_only',
			'Lookup Error'        => '',
		);
	}

	return $rows;
}

$args = uu_audit_cli_parse_args( $argv );
if ( empty( $args['output'] ) || empty( $args['map'] ) ) {
	uu_audit_cli_usage( 'tools/export-widget-inventory-csv.php', '--output=/path/output.csv --map=/path/multisite-map.json' );
	exit( 1 );
}

$output_file = (string) $args['output'];
$map_file    = (string) $args['map'];
$map         = uu_audit_load_map( $map_file );

$header = array(
	'Environment Label',
	'Base URL',
	'Widget Type',
	'Catalog Slug',
	'Widget Label',
	'Widget Class',
	'Classic Widget ID',
	'Catalog Kind',
	'Search Values',
	'Seen In',
	'Discovery Source',
	'Matched Discovery',
	'Plugin / Bundle',
	'Discovery Status',
	'Lookup Error',
);

$rows = array();

foreach ( $map as $site_label => $site_url ) {
	$site_url = uu_audit_normalize_url( (string) $site_url );

	$items_payload = uu_audit_fetch_json(
		$site_url,
		array(
			'wp-json/uu-usage-tracker/v1/items',
			'wp-json/uu-widget-tracker/v1/widgets',
		)
	);
	$siteorigin_payload = uu_audit_fetch_json(
		$site_url,
		array(
			'wp-json/uu-usage-tracker/v1/discovery/siteorigin-classes',
			'wp-json/uu-widget-tracker/v1/widget-classes-seen',
		)
	);
	$classic_payload = uu_audit_fetch_json(
		$site_url,
		array(
			'wp-json/uu-usage-tracker/v1/discovery/classic-widget-ids',
		)
	);

	$lookup_errors = array();
	foreach ( array( $items_payload, $siteorigin_payload, $classic_payload ) as $payload ) {
		if ( ! empty( $payload['error'] ) ) {
			$lookup_errors[] = $payload['error'];
		}
	}

	$items_data        = is_array( $items_payload['data'] ) ? $items_payload['data'] : array();
	$siteorigin_data   = is_array( $siteorigin_payload['data'] ) ? $siteorigin_payload['data'] : array();
	$classic_data      = is_array( $classic_payload['data'] ) ? $classic_payload['data'] : array();
	$catalog_items     = array();
	$catalog_class_map = array();
	$catalog_id_map    = array();

	$remote_items = array();
	if ( ! empty( $items_data['items'] ) && is_array( $items_data['items'] ) ) {
		$remote_items = $items_data['items'];
	} elseif ( ! empty( $items_data['widgets'] ) && is_array( $items_data['widgets'] ) ) {
		$remote_items = $items_data['widgets'];
	}

	foreach ( $remote_items as $item ) {
		if ( ! is_array( $item ) || ! uu_widget_inventory_is_widget_item( $item ) ) {
			continue;
		}

		$catalog_items[] = $item;

		if ( isset( $item['kind'] ) && 'siteorigin_class' === $item['kind'] ) {
			$values = array();
			if ( ! empty( $item['class'] ) ) {
				$values[] = (string) $item['class'];
			}
			if ( isset( $item['search_for'] ) ) {
				$values = array_merge( $values, is_array( $item['search_for'] ) ? array_map( 'strval', $item['search_for'] ) : array( (string) $item['search_for'] ) );
			}
			foreach ( $values as $value ) {
				$value = trim( $value );
				if ( '' !== $value ) {
					$catalog_class_map[ uu_widget_inventory_normalize_key( $value ) ] = true;
				}
			}
		} elseif ( isset( $item['kind'] ) && 'classic_widget' === $item['kind'] ) {
			$values = isset( $item['search_for'] ) ? ( is_array( $item['search_for'] ) ? array_map( 'strval', $item['search_for'] ) : array( (string) $item['search_for'] ) ) : array();
			foreach ( $values as $value ) {
				$value = trim( $value );
				if ( '' !== $value ) {
					$catalog_id_map[ uu_widget_inventory_normalize_key( $value ) ] = true;
				}
			}
		}
	}

	$discovered_classes = array();
	if ( ! empty( $siteorigin_data['classes'] ) && is_array( $siteorigin_data['classes'] ) ) {
		foreach ( $siteorigin_data['classes'] as $value ) {
			$value = trim( (string) $value );
			if ( '' !== $value ) {
				$discovered_classes[ uu_widget_inventory_normalize_key( $value ) ] = $value;
			}
		}
	}

	$discovered_ids = array();
	if ( ! empty( $classic_data['classic_widget_ids'] ) && is_array( $classic_data['classic_widget_ids'] ) ) {
		foreach ( $classic_data['classic_widget_ids'] as $value ) {
			$value = trim( (string) $value );
			if ( '' !== $value ) {
				$discovered_ids[ uu_widget_inventory_normalize_key( $value ) ] = $value;
			}
		}
	}

	if ( empty( $catalog_items ) && empty( $discovered_classes ) && empty( $discovered_ids ) ) {
		$rows[] = array(
			'Environment Label'   => (string) $site_label,
			'Base URL'            => $site_url,
			'Widget Type'         => '',
			'Catalog Slug'        => '',
			'Widget Label'        => '',
			'Widget Class'        => '',
			'Classic Widget ID'   => '',
			'Catalog Kind'        => '',
			'Search Values'       => '',
			'Seen In'             => '',
			'Discovery Source'    => '',
			'Matched Discovery'   => '',
			'Plugin / Bundle'     => '',
			'Discovery Status'    => 'empty_site',
			'Lookup Error'        => implode( ' | ', array_unique( $lookup_errors ) ),
		);
		continue;
	}

	foreach ( $catalog_items as $item ) {
		$rows[] = uu_widget_inventory_build_catalog_row( (string) $site_label, $site_url, $item, $discovered_classes, $discovered_ids );
	}

	$rows = array_merge(
		$rows,
		uu_widget_inventory_build_discovered_only_rows( (string) $site_label, $site_url, $discovered_classes, $catalog_class_map, 'siteorigin', 'siteorigin_classes_seen' ),
		uu_widget_inventory_build_discovered_only_rows( (string) $site_label, $site_url, $discovered_ids, $catalog_id_map, 'classic', 'classic_widget_ids_seen' )
	);
}

usort(
	$rows,
	function ( $left, $right ) {
		$keys = array( 'Environment Label', 'Widget Type', 'Catalog Slug', 'Widget Class', 'Classic Widget ID' );
		foreach ( $keys as $key ) {
			$compare = strnatcasecmp( (string) ( $left[ $key ] ?? '' ), (string) ( $right[ $key ] ?? '' ) );
			if ( 0 !== $compare ) {
				return $compare;
			}
		}

		return 0;
	}
);

uu_audit_write_csv_rows( $output_file, $header, $rows );

fwrite( STDOUT, 'Wrote widget inventory CSV to ' . $output_file . ' (' . count( $rows ) . " rows)\n" );
