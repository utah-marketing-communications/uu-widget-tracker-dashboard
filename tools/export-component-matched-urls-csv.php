<?php
/**
 * Export a combined page-level matched URL CSV from widget and plugin reports.
 *
 * Usage:
 * php tools/export-component-matched-urls-csv.php \
 *   --aws-widget-matched=/abs/path/Widget_Audit.report-matched-urls.v1.csv \
 *   --aws-plugin-matched=/abs/path/AWS_Plugin_Audit.report-matched-urls.v1.csv \
 *   --wpengine-widget-matched=/abs/path/WP_Engine_Widget_Audit.report-matched-urls.v1.csv \
 *   --wpengine-plugin-matched='/abs/path/WP_Engine_Standalone_Plugin_Audit.chunk-*.report-matched-urls.v1.csv' \
 *   --output=/abs/path/Component_Audit.report-matched-urls.v1.csv
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 1 );
}

require_once __DIR__ . '/component-report-common.php';

/**
 * Load and normalize one matched-url input group.
 *
 * @param string   $input_spec          File path, glob, or comma-separated list.
 * @param callable $normalizer          Row normalizer callback.
 * @param string   $environment_family  Environment family label.
 * @param string   $source_workflow     Source workflow label.
 * @return array<int, array<string, string>>
 */
function uu_component_collect_matched_group( $input_spec, callable $normalizer, $environment_family, $source_workflow ) {
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
	empty( $args['aws-widget-matched'] ) ||
	empty( $args['aws-plugin-matched'] ) ||
	empty( $args['wpengine-widget-matched'] ) ||
	empty( $args['wpengine-plugin-matched'] ) ||
	empty( $args['output'] )
) {
	uu_audit_cli_usage(
		'tools/export-component-matched-urls-csv.php',
		"--aws-widget-matched=/path/aws-widget-matched.csv --aws-plugin-matched=/path/aws-plugin-matched.csv --wpengine-widget-matched=/path/wpengine-widget-matched.csv --wpengine-plugin-matched='/path/wpengine-plugin.chunk-*.csv' --output=/path/component-matched-urls.csv"
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
	'Plugin Activation',
	'Site Name',
	'Multisite Name',
	'Blog ID',
	'Post ID',
	'Result Title',
	'Result Type',
	'Matched By',
	'Permalink',
	'Needs Review',
	'Notes',
	'Lookup Endpoint',
	'Lookup Error',
);

$output_rows = array();
$output_rows = array_merge(
	$output_rows,
	uu_component_collect_matched_group(
		(string) $args['aws-widget-matched'],
		'uu_component_matched_rows_from_widget_report',
		'AWS',
		'Widget Audit'
	),
	uu_component_collect_matched_group(
		(string) $args['aws-plugin-matched'],
		'uu_component_matched_rows_from_plugin_report',
		'AWS',
		'Plugin Audit'
	),
	uu_component_collect_matched_group(
		(string) $args['wpengine-widget-matched'],
		'uu_component_matched_rows_from_widget_report',
		'WP Engine',
		'Widget Audit'
	),
	uu_component_collect_matched_group(
		(string) $args['wpengine-plugin-matched'],
		'uu_component_matched_rows_from_plugin_report',
		'WP Engine',
		'Plugin Audit'
	)
);

uu_component_sort_matched_rows( $output_rows );
uu_audit_write_csv_rows( $output_file, $output_header, $output_rows );

fwrite( STDOUT, 'Wrote combined component matched URL CSV to ' . $output_file . ' (' . count( $output_rows ) . " rows)\n" );
