<?php
/**
 * Build a canonical widget registry from the raw widget inventory export.
 *
 * Usage:
 * php tools/build-widget-registry-csv.php \
 *   --input=/abs/path/Widget_Audit.inventory.v1.csv \
 *   --output=/abs/path/Widget_Audit.registry.v1.csv
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 1 );
}

require_once __DIR__ . '/audit-cli-common.php';

/**
 * Normalize strings so repeated widget sightings collapse into one key.
 *
 * @param string $value Raw identifier.
 * @return string
 */
function uu_widget_registry_normalize_key( $value ) {
	$value = strtolower( trim( (string) $value ) );
	$value = str_replace( array( '-', ' ' ), '_', $value );

	return preg_replace( '/[^a-z0-9_]/', '', $value );
}

/**
 * Return the canonical widget key for a raw inventory row.
 *
 * @param array<string, string> $row Inventory row.
 * @return string
 */
function uu_widget_registry_row_key( array $row ) {
	$widget_type = isset( $row['Widget Type'] ) ? (string) $row['Widget Type'] : '';
	$widget_class = isset( $row['Widget Class'] ) ? (string) $row['Widget Class'] : '';
	$classic_id = isset( $row['Classic Widget ID'] ) ? (string) $row['Classic Widget ID'] : '';
	$catalog_slug = isset( $row['Catalog Slug'] ) ? (string) $row['Catalog Slug'] : '';

	if ( '' !== $catalog_slug ) {
		return $widget_type . ':slug:' . uu_widget_registry_normalize_key( $catalog_slug );
	}

	if ( '' !== $widget_class ) {
		return $widget_type . ':class:' . uu_widget_registry_normalize_key( $widget_class );
	}

	if ( '' !== $classic_id ) {
		return $widget_type . ':id:' . uu_widget_registry_normalize_key( $classic_id );
	}

	return $widget_type . ':slug:' . uu_widget_registry_normalize_key( $catalog_slug );
}

/**
 * Choose the best canonical slug for the widget.
 *
 * @param array<string, mixed> $group Group accumulator.
 * @return string
 */
function uu_widget_registry_choose_slug( array $group ) {
	if ( ! empty( $group['catalog_slugs'] ) ) {
		$slugs = array_keys( $group['catalog_slugs'] );
		usort(
			$slugs,
			function ( $left, $right ) use ( $group ) {
				$left_count  = $group['catalog_slugs'][ $left ];
				$right_count = $group['catalog_slugs'][ $right ];
				if ( $left_count !== $right_count ) {
					return $right_count <=> $left_count;
				}

				return strnatcasecmp( $left, $right );
			}
		);

		return (string) $slugs[0];
	}

	if ( ! empty( $group['widget_class'] ) ) {
		$slug = preg_replace( '/_+/', '-', strtolower( (string) $group['widget_class'] ) );
		$slug = preg_replace( '/-widget$/', '', (string) $slug );

		return trim( (string) $slug, '-' ) . '-widget';
	}

	if ( ! empty( $group['classic_widget_id'] ) ) {
		return trim( str_replace( '_', '-', strtolower( (string) $group['classic_widget_id'] ) ), '-' );
	}

	return '';
}

/**
 * Choose the best human-readable label for the widget.
 *
 * @param array<string, mixed> $group Group accumulator.
 * @return string
 */
function uu_widget_registry_choose_label( array $group ) {
	if ( ! empty( $group['labels'] ) ) {
		$labels = array_keys( $group['labels'] );
		usort(
			$labels,
			function ( $left, $right ) use ( $group ) {
				$left_count  = $group['labels'][ $left ];
				$right_count = $group['labels'][ $right ];
				if ( $left_count !== $right_count ) {
					return $right_count <=> $left_count;
				}

				return strnatcasecmp( $left, $right );
			}
		);

		return (string) $labels[0];
	}

	$slug = uu_widget_registry_choose_slug( $group );
	if ( '' !== $slug ) {
		return ucwords( str_replace( array( '-', '_' ), ' ', $slug ) );
	}

	return '';
}

/**
 * First-pass family inference for registry rows.
 *
 * @param array<string, mixed> $group Group accumulator.
 * @param string               $canonical_slug Chosen slug.
 * @return string
 */
function uu_widget_registry_infer_family( array $group, $canonical_slug ) {
	$slug  = strtolower( (string) $canonical_slug );
	$class = strtolower( (string) ( $group['widget_class'] ?? '' ) );
	$id    = strtolower( (string) ( $group['classic_widget_id'] ?? '' ) );

	$core_classic_ids = array( 'archives', 'categories', 'meta', 'recent-comments', 'recent-posts', 'search', 'block' );
	if ( in_array( $id, $core_classic_ids, true ) ) {
		return 'wp-core';
	}

	if ( false !== strpos( $class, 'siteorigin_widget_' ) ) {
		return 'siteorigin-core';
	}

	if ( false !== strpos( $slug, 'cap-' ) || false !== strpos( $class, 'cap_' ) ) {
		return 'uu-cap-so-widgets';
	}

	if ( false !== strpos( $slug, 'utahfresh-' ) || false !== strpos( $class, 'utahfresh_' ) || false !== strpos( $class, 'ufresh_' ) ) {
		return 'utahfresh-so-widgets';
	}

	if ( in_array( $slug, array( 'degree-programs-widget', 'three-step-widget' ), true ) || false !== strpos( $class, 'degree_programs_' ) || false !== strpos( $class, 'three_step_' ) ) {
		return 'uhesa-so-widgets';
	}

	if ( 'waste-tabs-widget' === $slug || false !== strpos( $class, 'waste_tabs_' ) ) {
		return 'uu-waste-so-widgets';
	}

	if ( 'ped-call-out-widget' === $slug ) {
		return 'uu-pedsccm-so-widgets';
	}

	if ( in_array( $slug, array( 'lectures-loop-widget', 'uu-brand-social-media-search-widget' ), true ) ) {
		return 'uu-brand-so-widgets';
	}

	if ( 'uu-digital-settings-widget' === $slug ) {
		return 'uu-digital-so-widgets';
	}

	if ( 0 === strpos( $slug, 'legacy-' ) || 'umc-team-search-widget' === $slug ) {
		return 'legacy-widget-family';
	}

	if ( 0 === strpos( $slug, 'uu-' ) ) {
		return 'uu-so-widgets';
	}

	if ( 0 === strpos( $slug, 'utah-' ) ) {
		return 'standalone-classic-widget';
	}

	return '';
}

/**
 * Convert a set-like associative array to a stable joined string.
 *
 * @param array<string, mixed> $set Value set.
 * @return string
 */
function uu_widget_registry_join_set( array $set ) {
	$values = array_keys( $set );
	sort( $values, SORT_NATURAL );

	return implode( ' | ', $values );
}

/**
 * Merge two registry row payloads that resolved to the same canonical widget.
 *
 * @param array<string, string> $into Existing registry row.
 * @param array<string, string> $from Incoming registry row.
 * @return array<string, string>
 */
function uu_widget_registry_merge_rows( array $into, array $from ) {
	$set_columns = array(
		'Catalog Slugs',
		'Environment Labels',
		'Seen In',
		'Discovery Sources',
		'Discovery Statuses',
		'Lookup Errors',
	);

	foreach ( $set_columns as $column ) {
		$values = array();
		foreach ( array( $into[ $column ] ?? '', $from[ $column ] ?? '' ) as $joined ) {
			foreach ( preg_split( '/ \| /', (string) $joined ) as $value ) {
				$value = trim( (string) $value );
				if ( '' !== $value ) {
					$values[ $value ] = true;
				}
			}
		}

		$into[ $column ] = uu_widget_registry_join_set( $values );
	}

	if ( empty( $into['Widget Class'] ) && ! empty( $from['Widget Class'] ) ) {
		$into['Widget Class'] = $from['Widget Class'];
	}

	if ( empty( $into['Classic Widget ID'] ) && ! empty( $from['Classic Widget ID'] ) ) {
		$into['Classic Widget ID'] = $from['Classic Widget ID'];
	}

	if ( empty( $into['Bundle / Family'] ) && ! empty( $from['Bundle / Family'] ) ) {
		$into['Bundle / Family'] = $from['Bundle / Family'];
	}

	if ( empty( $into['Preferred Label'] ) && ! empty( $from['Preferred Label'] ) ) {
		$into['Preferred Label'] = $from['Preferred Label'];
	}

	$env_values = array();
	foreach ( preg_split( '/ \| /', (string) ( $into['Environment Labels'] ?? '' ) ) as $value ) {
		$value = trim( (string) $value );
		if ( '' !== $value ) {
			$env_values[ $value ] = true;
		}
	}
	$into['Environment Count'] = (string) count( $env_values );

	if ( 'Yes' === ( $from['Needs Review'] ?? '' ) ) {
		$into['Needs Review'] = 'Yes';
	}

	return $into;
}

$args = uu_audit_cli_parse_args( $argv );
if ( empty( $args['input'] ) || empty( $args['output'] ) ) {
	uu_audit_cli_usage( 'tools/build-widget-registry-csv.php', '--input=/path/input.csv --output=/path/output.csv' );
	exit( 1 );
}

$input_file  = (string) $args['input'];
$output_file = (string) $args['output'];

list( $header, $rows ) = uu_audit_load_csv_rows( $input_file );

$groups = array();
foreach ( $rows as $row ) {
	$key = uu_widget_registry_row_key( $row );
	if ( '' === $key ) {
		continue;
	}

	if ( ! isset( $groups[ $key ] ) ) {
		$groups[ $key ] = array(
			'widget_type'       => (string) ( $row['Widget Type'] ?? '' ),
			'widget_class'      => '',
			'classic_widget_id' => '',
			'catalog_slugs'     => array(),
			'labels'            => array(),
			'environments'      => array(),
			'base_urls'         => array(),
			'seen_in'           => array(),
			'discovery_sources' => array(),
			'discovery_status'  => array(),
			'lookup_errors'     => array(),
		);
	}

	if ( ! empty( $row['Widget Class'] ) ) {
		$groups[ $key ]['widget_class'] = (string) $row['Widget Class'];
	}

	if ( ! empty( $row['Classic Widget ID'] ) ) {
		$groups[ $key ]['classic_widget_id'] = (string) $row['Classic Widget ID'];
	}

	if ( ! empty( $row['Catalog Slug'] ) ) {
		$slug = (string) $row['Catalog Slug'];
		if ( ! isset( $groups[ $key ]['catalog_slugs'][ $slug ] ) ) {
			$groups[ $key ]['catalog_slugs'][ $slug ] = 0;
		}
		$groups[ $key ]['catalog_slugs'][ $slug ]++;
	}

	if ( ! empty( $row['Widget Label'] ) ) {
		$label = (string) $row['Widget Label'];
		if ( ! isset( $groups[ $key ]['labels'][ $label ] ) ) {
			$groups[ $key ]['labels'][ $label ] = 0;
		}
		$groups[ $key ]['labels'][ $label ]++;
	}

	foreach ( array(
		'environments'      => 'Environment Label',
		'base_urls'         => 'Base URL',
		'seen_in'           => 'Seen In',
		'discovery_sources' => 'Discovery Source',
		'discovery_status'  => 'Discovery Status',
		'lookup_errors'     => 'Lookup Error',
	) as $bucket => $column ) {
		if ( ! empty( $row[ $column ] ) ) {
			$groups[ $key ][ $bucket ][ (string) $row[ $column ] ] = true;
		}
	}
}

$registry_rows = array();
foreach ( $groups as $registry_key => $group ) {
	$canonical_slug  = uu_widget_registry_choose_slug( $group );
	$preferred_label = uu_widget_registry_choose_label( $group );
	$bundle_family   = uu_widget_registry_infer_family( $group, $canonical_slug );
	$needs_review    = '';

	if ( empty( $group['catalog_slugs'] ) || empty( $bundle_family ) || isset( $group['discovery_status']['discovered_only'] ) ) {
		$needs_review = 'Yes';
	}

	$registry_rows[] = array(
		'Registry Key'          => $registry_key,
		'Canonical Slug'        => $canonical_slug,
		'Preferred Label'       => $preferred_label,
		'Widget Type'           => (string) $group['widget_type'],
		'Widget Class'          => (string) $group['widget_class'],
		'Classic Widget ID'     => (string) $group['classic_widget_id'],
		'Bundle / Family'       => $bundle_family,
		'Catalog Slugs'         => uu_widget_registry_join_set( $group['catalog_slugs'] ),
		'Environment Labels'    => uu_widget_registry_join_set( $group['environments'] ),
		'Environment Count'     => (string) count( $group['environments'] ),
		'Seen In'               => uu_widget_registry_join_set( $group['seen_in'] ),
		'Discovery Sources'     => uu_widget_registry_join_set( $group['discovery_sources'] ),
		'Discovery Statuses'    => uu_widget_registry_join_set( $group['discovery_status'] ),
		'Needs Review'          => $needs_review,
		'Lookup Errors'         => uu_widget_registry_join_set( $group['lookup_errors'] ),
		'Notes'                 => '',
	);
}

$merged_registry_rows = array();
foreach ( $registry_rows as $row ) {
	$merge_key = strtolower( (string) ( $row['Widget Type'] ?? '' ) ) . ':' . strtolower( (string) ( $row['Canonical Slug'] ?? '' ) );
	if ( '' === trim( (string) ( $row['Canonical Slug'] ?? '' ) ) ) {
		$merge_key .= ':' . strtolower( (string) ( $row['Registry Key'] ?? '' ) );
	}

	if ( ! isset( $merged_registry_rows[ $merge_key ] ) ) {
		$merged_registry_rows[ $merge_key ] = $row;
		continue;
	}

	$merged_registry_rows[ $merge_key ] = uu_widget_registry_merge_rows( $merged_registry_rows[ $merge_key ], $row );
}

$registry_rows = array_values( $merged_registry_rows );

usort(
	$registry_rows,
	function ( $left, $right ) {
		$keys = array( 'Bundle / Family', 'Widget Type', 'Canonical Slug', 'Widget Class', 'Classic Widget ID' );
		foreach ( $keys as $key ) {
			$compare = strnatcasecmp( (string) ( $left[ $key ] ?? '' ), (string) ( $right[ $key ] ?? '' ) );
			if ( 0 !== $compare ) {
				return $compare;
			}
		}

		return 0;
	}
);

$output_header = array(
	'Registry Key',
	'Canonical Slug',
	'Preferred Label',
	'Widget Type',
	'Widget Class',
	'Classic Widget ID',
	'Bundle / Family',
	'Catalog Slugs',
	'Environment Labels',
	'Environment Count',
	'Seen In',
	'Discovery Sources',
	'Discovery Statuses',
	'Needs Review',
	'Lookup Errors',
	'Notes',
);

uu_audit_write_csv_rows( $output_file, $output_header, $registry_rows );

fwrite( STDOUT, 'Wrote widget registry CSV to ' . $output_file . ' (' . count( $registry_rows ) . " rows)\n" );
