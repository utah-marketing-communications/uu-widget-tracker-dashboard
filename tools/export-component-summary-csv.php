<?php
/**
 * Export a combined component summary CSV from widget and plugin reports.
 *
 * Usage:
 * php tools/export-component-summary-csv.php \
 *   --aws-widget-summary=/abs/path/Widget_Audit.report-summary.v1.csv \
 *   --aws-plugin-summary=/abs/path/AWS_Plugin_Audit.report-summary.v1.csv \
 *   --wpengine-widget-summary=/abs/path/WP_Engine_Widget_Audit.report-summary.v1.csv \
 *   --wpengine-plugin-summary='/abs/path/WP_Engine_Standalone_Plugin_Audit.chunk-*.report-summary.v1.csv' \
 *   --output=/abs/path/Component_Audit.report-summary.v1.csv
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 1 );
}

require_once __DIR__ . '/component-report-common.php';

/**
 * Load and normalize one input group.
 *
 * @param string   $input_spec          File path, glob, or comma-separated list.
 * @param callable $normalizer          Row normalizer callback.
 * @param string   $environment_family  Environment family label.
 * @param string   $source_workflow     Source workflow label.
 * @return array<int, array<string, string>>
 */
function uu_component_collect_summary_group( $input_spec, callable $normalizer, $environment_family, $source_workflow ) {
	$output_rows = array();
	$input_files = uu_component_expand_input_files( $input_spec );

	foreach ( $input_files as $input_file ) {
		list( $header, $rows ) = uu_audit_load_csv_rows( $input_file );
		unset( $header );

		foreach ( $normalizer( $rows, $environment_family, $source_workflow ) as $output_row ) {
			$output_rows[] = $output_row;
		}
	}

	return $output_rows;
}

$args = uu_audit_cli_parse_args( $argv );
if (
	empty( $args['aws-widget-summary'] ) ||
	empty( $args['aws-plugin-summary'] ) ||
	empty( $args['wpengine-widget-summary'] ) ||
	empty( $args['wpengine-plugin-summary'] ) ||
	empty( $args['output'] )
) {
	uu_audit_cli_usage(
		'tools/export-component-summary-csv.php',
		"--aws-widget-summary=/path/aws-widget-summary.csv --aws-plugin-summary=/path/aws-plugin-summary.csv --wpengine-widget-summary=/path/wpengine-widget-summary.csv --wpengine-plugin-summary='/path/wpengine-plugin.chunk-*.csv' --output=/path/component-summary.csv"
	);
	exit( 1 );
}

$output_file = (string) $args['output'];
$output_header = array(
	'Environment Family',
	'Environment Label',
	'Base URL',
	'Component Type',
	'Source Workflow',
	'Component Slug',
	'Component Label',
	'Widget Type',
	'Widget Class',
	'Classic Widget ID',
	'Bundle / Family',
	'Plugin Folder',
	'Category',
	'Signal Type',
	'Environment Count',
	'Seen In',
	'Plugin Activation',
	'Matches Found',
	'Confidence',
	'Action',
	'Matched By',
	'Sample URLs',
	'Needs Review',
	'Notes',
	'Lookup Error',
);

$output_rows = array();
$output_rows = array_merge(
	$output_rows,
	uu_component_collect_summary_group(
		(string) $args['aws-widget-summary'],
		'uu_component_summary_rows_from_widget_report',
		'AWS',
		'Widget Audit'
	),
	uu_component_collect_summary_group(
		(string) $args['aws-plugin-summary'],
		'uu_component_summary_rows_from_plugin_report',
		'AWS',
		'Plugin Audit'
	),
	uu_component_collect_summary_group(
		(string) $args['wpengine-widget-summary'],
		'uu_component_summary_rows_from_widget_report',
		'WP Engine',
		'Widget Audit'
	),
	uu_component_collect_summary_group(
		(string) $args['wpengine-plugin-summary'],
		'uu_component_summary_rows_from_plugin_report',
		'WP Engine',
		'Plugin Audit'
	)
);

uu_component_sort_summary_rows( $output_rows );
uu_audit_write_csv_rows( $output_file, $output_header, $output_rows );

fwrite( STDOUT, 'Wrote combined component summary CSV to ' . $output_file . ' (' . count( $output_rows ) . " rows)\n" );
