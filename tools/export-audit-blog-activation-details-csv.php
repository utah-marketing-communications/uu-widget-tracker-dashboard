<?php
/**
 * Export one row per active blog for each tracked item.
 *
 * Usage:
 * php tools/export-audit-blog-activation-details-csv.php \
 *   --input=/abs/path/AWS_Plugin_Audit.inventory.csv \
 *   --output=/abs/path/AWS_Plugin_Audit.report-blog-activation-details.v1.csv \
 *   --map=/abs/path/multisite-map.json \
 *   [--snapshot=/abs/path/report-usage-snapshot.json]
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 1 );
}

require_once __DIR__ . '/audit-cli-common.php';
require_once __DIR__ . '/report-runtime.php';

$args = uu_audit_cli_parse_args( $argv );
if ( empty( $args['input'] ) || empty( $args['output'] ) || empty( $args['map'] ) ) {
	uu_audit_cli_usage( 'tools/export-audit-blog-activation-details-csv.php', '--input=/path/input.csv --output=/path/output.csv --map=/path/multisite-map.json' );
	exit( 1 );
}

$input_file  = (string) $args['input'];
$output_file = (string) $args['output'];
$map_file    = (string) $args['map'];
$snapshot_file = uu_report_snapshot_arg( $args );

$map = uu_audit_load_map( $map_file );
list( $header, $rows ) = uu_audit_load_csv_rows( $input_file );

$detail_header = array(
	'Multisite',
	'Plugin Folder',
	'Tracked Item Slug',
	'Category',
	'Signal Type',
	'Activation Status',
	'Network Active',
	'Matched Network Plugins',
	'Active Blog Count',
	'Scanned Blog Count',
	'Active Blog ID',
	'Active Site Name',
	'Active Site URL',
	'Active Network Name',
	'Matched Plugin Files',
	'Lookup Endpoint',
	'Lookup Error',
);

$cache       = uu_report_load_snapshot_cache( $snapshot_file );
$detail_rows = array();
uu_report_each_plugin_context(
	$rows,
	$map,
	$cache,
	function ( array $context ) use ( &$detail_rows ) {
		foreach ( uu_report_plugin_activation_rows( $context ) as $detail_row ) {
			$detail_rows[] = $detail_row;
		}
	}
);

uu_audit_write_csv_rows( $output_file, $detail_header, $detail_rows );
uu_report_save_snapshot_cache( $snapshot_file, $cache );

fwrite( STDOUT, 'Wrote audit blog activation detail CSV to ' . $output_file . ' (' . count( $detail_rows ) . " rows)\n" );
