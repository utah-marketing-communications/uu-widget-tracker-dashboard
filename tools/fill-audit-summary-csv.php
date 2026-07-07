<?php
/**
 * Fill the audit summary CSV from remote UU Usage Tracker endpoints.
 *
 * Usage:
 * php tools/fill-audit-summary-csv.php \
 *   --input=/abs/path/AWS_Plugin_Audit.csv \
 *   --output=/abs/path/AWS_Plugin_Audit.filled.summary.csv \
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
	uu_audit_cli_usage( 'tools/fill-audit-summary-csv.php', '--input=/path/input.csv --output=/path/output.csv --map=/path/multisite-map.json' );
	exit( 1 );
}

$input_file  = (string) $args['input'];
$output_file = (string) $args['output'];
$map_file    = (string) $args['map'];
$snapshot_file = uu_report_snapshot_arg( $args );

$map = uu_audit_load_map( $map_file );
list( $header, $rows ) = uu_audit_load_csv_rows( $input_file );

$required_extra_columns = array(
	'Usage Status',
	'Plugin Activation',
	'Matches Found',
	'Confidence',
	'Action',
	'Matched By',
	'Sample URLs',
	'Lookup Error',
);
foreach ( $required_extra_columns as $column ) {
	if ( ! in_array( $column, $header, true ) ) {
		$header[] = $column;
	}
}

$cache = uu_report_load_snapshot_cache( $snapshot_file );
$updated_rows = array();
uu_report_each_plugin_context(
	$rows,
	$map,
	$cache,
	function ( array $context ) use ( &$updated_rows ) {
		$updated_rows[] = uu_report_plugin_summary_row( $context );
	}
);

$rows = $updated_rows;

uu_audit_write_csv_rows( $output_file, $header, $rows );
uu_report_save_snapshot_cache( $snapshot_file, $cache );

fwrite( STDOUT, "Wrote filled audit summary CSV to {$output_file}\n" );
