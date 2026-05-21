<?php
/**
 * Build a WP Engine standalone-plugin inventory from an existing plugin audit inventory.
 *
 * Usage:
 * php tools/build-wpengine-standalone-plugin-inventory-csv.php \
 *   --source=/abs/path/AWS_Plugin_Audit.inventory.v1.csv \
 *   --map=/abs/path/wpengine-production-sites.v1.json \
 *   --output=/abs/path/WP_Engine_Standalone_Plugin_Audit.inventory.v1.csv \
 *   [--categories="Standalone plugin,Standalone/classic widget"]
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 1 );
}

require_once __DIR__ . '/audit-cli-common.php';

$args = uu_audit_cli_parse_args( $argv );
if ( empty( $args['source'] ) || empty( $args['output'] ) || empty( $args['map'] ) ) {
	uu_audit_cli_usage( 'tools/build-wpengine-standalone-plugin-inventory-csv.php', '--source=/path/source.csv --output=/path/output.csv --map=/path/wpengine-map.json' );
	exit( 1 );
}

$source_file = (string) $args['source'];
$output_file = (string) $args['output'];
$map_file    = (string) $args['map'];
$categories  = isset( $args['categories'] )
	? array_filter( array_map( 'trim', explode( ',', (string) $args['categories'] ) ) )
	: array( 'Standalone plugin', 'Standalone/classic widget' );

$map = uu_audit_load_map( $map_file );
list( $source_header, $source_rows ) = uu_audit_load_csv_rows( $source_file );

$required_columns = array( 'Multisite', 'Plugin Folder', 'Tracked Item Slug', 'Category', 'Signal Type', 'Notes' );
foreach ( $required_columns as $column ) {
	if ( ! in_array( $column, $source_header, true ) ) {
		fwrite( STDERR, "Source CSV is missing required column: {$column}\n" );
		exit( 1 );
	}
}

$items = array();
foreach ( $source_rows as $row ) {
	$category = trim( (string) ( $row['Category'] ?? '' ) );
	if ( ! in_array( $category, $categories, true ) ) {
		continue;
	}

	$plugin_folder = trim( (string) ( $row['Plugin Folder'] ?? '' ) );
	$item_slug     = trim( (string) ( $row['Tracked Item Slug'] ?? '' ) );
	if ( '' === $plugin_folder || '' === $item_slug ) {
		continue;
	}

	$key = strtolower( $plugin_folder . '|' . $item_slug . '|' . $category );
	if ( empty( $items[ $key ] ) ) {
		$items[ $key ] = array(
			'Plugin Folder'     => $plugin_folder,
			'Tracked Item Slug' => $item_slug,
			'Category'          => $category,
			'Signal Type'       => array(),
			'Notes'             => array(),
			'Source Multisites' => array(),
			'Source Row Count'  => 0,
		);
	}

	foreach ( array( 'Signal Type', 'Notes' ) as $merge_column ) {
		$value = trim( (string) ( $row[ $merge_column ] ?? '' ) );
		if ( '' !== $value && ! in_array( $value, $items[ $key ][ $merge_column ], true ) ) {
			$items[ $key ][ $merge_column ][] = $value;
		}
	}

	$source_multisite = trim( (string) ( $row['Multisite'] ?? '' ) );
	if ( '' !== $source_multisite && ! in_array( $source_multisite, $items[ $key ]['Source Multisites'], true ) ) {
		$items[ $key ]['Source Multisites'][] = $source_multisite;
	}

	$items[ $key ]['Source Row Count']++;
}

uasort(
	$items,
	function ( $left, $right ) {
		foreach ( array( 'Plugin Folder', 'Tracked Item Slug', 'Category' ) as $column ) {
			$compare = strnatcasecmp( (string) $left[ $column ], (string) $right[ $column ] );
			if ( 0 !== $compare ) {
				return $compare;
			}
		}

		return 0;
	}
);

$environment_labels = array_keys( $map );
natcasesort( $environment_labels );

$header = array(
	'Multisite',
	'Plugin Folder',
	'Tracked Item Slug',
	'Category',
	'Signal Type',
	'Notes',
	'Source Multisites',
	'Source Row Count',
);

$output_rows = array();
foreach ( $environment_labels as $environment_label ) {
	foreach ( $items as $item ) {
		sort( $item['Signal Type'], SORT_NATURAL );
		sort( $item['Notes'], SORT_NATURAL );
		sort( $item['Source Multisites'], SORT_NATURAL );

		$output_rows[] = array(
			'Multisite'         => (string) $environment_label,
			'Plugin Folder'     => (string) $item['Plugin Folder'],
			'Tracked Item Slug' => (string) $item['Tracked Item Slug'],
			'Category'          => (string) $item['Category'],
			'Signal Type'       => implode( ' | ', $item['Signal Type'] ),
			'Notes'             => implode( ' | ', $item['Notes'] ),
			'Source Multisites' => implode( ' | ', $item['Source Multisites'] ),
			'Source Row Count'  => (string) $item['Source Row Count'],
		);
	}
}

uu_audit_write_csv_rows( $output_file, $header, $output_rows );

fwrite(
	STDOUT,
	sprintf(
		"Wrote WP Engine standalone plugin inventory CSV to %s (%d items x %d environments = %d rows)\n",
		$output_file,
		count( $items ),
		count( $environment_labels ),
		count( $output_rows )
	)
);
