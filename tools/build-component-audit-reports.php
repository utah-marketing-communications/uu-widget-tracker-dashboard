<?php
/**
 * Build the combined component summary and matched URL reports in one command.
 *
 * Usage:
 * php tools/build-component-audit-reports.php \
 *   [--output-dir=/Users/brianthurber/Desktop/Component Audit - 2026-06-03 14.30.00] \
 *   [--prefix=Component_Audit] \
 *   [--aws-widget-summary=/abs/path/Widget_Audit.report-summary.v1.csv] \
 *   [--aws-plugin-summary=/abs/path/AWS_Plugin_Audit.report-summary.v1.csv] \
 *   [--wpengine-widget-summary=/abs/path/WP_Engine_Widget_Audit.report-summary.v1.csv] \
 *   [--wpengine-plugin-summary='/abs/path/WP_Engine_Standalone_Plugin_Audit.chunk-*.report-summary.v1.csv'] \
 *   [--aws-widget-matched=/abs/path/Widget_Audit.report-matched-urls.v1.csv] \
 *   [--aws-plugin-matched=/abs/path/AWS_Plugin_Audit.report-matched-urls.v1.csv] \
 *   [--wpengine-widget-matched=/abs/path/WP_Engine_Widget_Audit.report-matched-urls.v1.csv] \
 *   [--wpengine-plugin-matched='/abs/path/reports/runtime/wpengine-standalone-plugin/WP_Engine_Standalone_Plugin_Audit.chunk-*.report-matched-urls.rerun.v1.csv']
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 1 );
}

require_once __DIR__ . '/component-report-common.php';

/**
 * Print wrapper-specific usage guidance.
 *
 * @return void
 */
function uu_component_build_usage() {
	$wpengine_runtime_dir = uu_component_default_wpengine_runtime_dir();

	$usage = <<<TXT
Usage:
  php tools/build-component-audit-reports.php [--output-dir=/Users/brianthurber/Desktop/Component Audit - 2026-06-03 14.30.00] [--prefix=Component_Audit]

Default source files:
  AWS widget summary:     /Users/brianthurber/Desktop/DESKTOP/Widget Audit V1/Widget_Audit.report-summary.v1.csv
  AWS widget matched:     /Users/brianthurber/Desktop/DESKTOP/Widget Audit V1/Widget_Audit.report-matched-urls.v1.csv
  AWS plugin summary:     /Users/brianthurber/Desktop/DESKTOP/AWS Audit CSV Docs/AWS_Plugin_Audit.report-summary.v1.csv
  AWS plugin matched:     /Users/brianthurber/Desktop/DESKTOP/AWS Audit CSV Docs/AWS_Plugin_Audit.report-matched-urls.v1.csv
  WPE widget summary:     /Users/brianthurber/Desktop/DESKTOP/WP Engine Full Audit/WP_Engine_Widget_Audit.report-summary.v1.csv
  WPE widget matched:     /Users/brianthurber/Desktop/DESKTOP/WP Engine Full Audit/WP_Engine_Widget_Audit.report-matched-urls.v1.csv
  WPE plugin summary:     {$wpengine_runtime_dir}/WP_Engine_Standalone_Plugin_Audit.chunk-*.report-summary.rerun.v1.csv
  WPE plugin matched:     {$wpengine_runtime_dir}/WP_Engine_Standalone_Plugin_Audit.chunk-*.report-matched-urls.rerun.v1.csv

Outputs:
  {output-dir}/{prefix}.report-summary.v1.csv
  {output-dir}/{prefix}.report-matched-urls.v1.csv
  {output-dir}/{prefix}.report-matched-urls.standalone-plugins-only.v1.csv
TXT;

	fwrite( STDERR, $usage . "\n" );
}

/**
 * Return the default timestamped Desktop output directory for this run.
 *
 * @return string
 */
function uu_component_default_output_dir() {
	$timestamp = date( 'Y-m-d H.i.s' );

	return '/Users/brianthurber/Desktop/Component Audit - ' . $timestamp;
}

/**
 * Return the stable plugin-local folder for WP Engine standalone intermediates.
 *
 * @return string
 */
function uu_component_default_wpengine_runtime_dir() {
	return '/Users/brianthurber/Sites/umc/wp-content/plugins/uu-widget-tracker-dashboard/reports/runtime/wpengine-standalone-plugin';
}

/**
 * Resolve a preferred source path, or fall back to the newest matching file
 * found under the user's Desktop when the file has been moved.
 *
 * @param string $preferred_path Exact path the script prefers to use.
 * @return string
 */
function uu_component_preferred_source_path( $preferred_path ) {
	$preferred_path = trim( (string) $preferred_path );
	if ( '' === $preferred_path ) {
		return $preferred_path;
	}

	if ( false !== strpos( $preferred_path, '*' ) || false !== strpos( $preferred_path, '?' ) ) {
		return $preferred_path;
	}

	if ( is_readable( $preferred_path ) ) {
		return $preferred_path;
	}

	$base_name = basename( $preferred_path );
	if ( '' === $base_name ) {
		return $preferred_path;
	}

	$matches = array();
	$desktop_root = '/Users/brianthurber/Desktop';

	if ( is_dir( $desktop_root ) ) {
		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $desktop_root, FilesystemIterator::SKIP_DOTS )
			);

			foreach ( $iterator as $file_info ) {
				if ( ! $file_info->isFile() ) {
					continue;
				}

				if ( $base_name === $file_info->getFilename() ) {
					$matches[] = $file_info->getPathname();
				}
			}
		} catch ( Exception $e ) {
			return $preferred_path;
		}
	}

	if ( empty( $matches ) ) {
		return $preferred_path;
	}

	usort(
		$matches,
		function ( $a, $b ) {
			return filemtime( $b ) <=> filemtime( $a );
		}
	);

	return (string) $matches[0];
}

/**
 * Return an argument value or a default string.
 *
 * @param array<string, mixed> $args    Parsed CLI args.
 * @param string               $key     Arg key.
 * @param string               $default Default string value.
 * @return string
 */
function uu_component_arg_or_default( array $args, $key, $default ) {
	if ( ! isset( $args[ $key ] ) || true === $args[ $key ] ) {
		return (string) $default;
	}

	return trim( (string) $args[ $key ] );
}

/**
 * Ensure the chosen output directory exists and is writable.
 *
 * @param string $output_dir Directory path.
 * @return string
 */
function uu_component_prepare_output_dir( $output_dir ) {
	$output_dir = rtrim( trim( (string) $output_dir ), '/' );
	if ( '' === $output_dir ) {
		fwrite( STDERR, "Output directory cannot be empty.\n" );
		exit( 1 );
	}

	if ( ! is_dir( $output_dir ) ) {
		if ( ! mkdir( $output_dir, 0777, true ) && ! is_dir( $output_dir ) ) {
			fwrite( STDERR, "Unable to create output directory: {$output_dir}\n" );
			exit( 1 );
		}
	}

	if ( ! is_writable( $output_dir ) ) {
		fwrite( STDERR, "Output directory is not writable: {$output_dir}\n" );
		exit( 1 );
	}

	return $output_dir;
}

/**
 * Expand and validate an input spec.
 *
 * @param string $label      User-facing label.
 * @param string $input_spec File path, comma-separated paths, or glob.
 * @return array<int, string>
 */
function uu_component_require_input_files( $label, $input_spec ) {
	$files = uu_component_expand_input_files( $input_spec );
	if ( empty( $files ) ) {
		fwrite( STDERR, sprintf( "%s did not resolve to any readable files: %s\n", $label, $input_spec ) );
		exit( 1 );
	}

	return $files;
}

/**
 * Load and normalize one summary input group.
 *
 * @param array<int, string> $input_files         Resolved input files.
 * @param callable           $normalizer          Row normalizer callback.
 * @param string             $environment_family  Environment family label.
 * @param string             $source_workflow     Source workflow label.
 * @return array<int, array<string, string>>
 */
function uu_component_build_collect_summary_group( array $input_files, callable $normalizer, $environment_family, $source_workflow ) {
	$output_rows = array();

	foreach ( $input_files as $input_file ) {
		list( $header, $rows ) = uu_audit_load_csv_rows( $input_file );
		unset( $header );

		foreach ( $normalizer( $rows, $environment_family, $source_workflow ) as $output_row ) {
			$output_rows[] = $output_row;
		}
	}

	return $output_rows;
}

/**
 * Load and normalize one matched-url input group.
 *
 * @param array<int, string> $input_files         Resolved input files.
 * @param callable           $normalizer          Row normalizer callback.
 * @param string             $environment_family  Environment family label.
 * @param string             $source_workflow     Source workflow label.
 * @return array<int, array<string, string>>
 */
function uu_component_build_collect_matched_group( array $input_files, callable $normalizer, $environment_family, $source_workflow ) {
	$output_rows = array();

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
if ( isset( $args['help'] ) || isset( $args['h'] ) ) {
	uu_component_build_usage();
	exit( 0 );
}

$output_dir = uu_component_arg_or_default( $args, 'output-dir', uu_component_default_output_dir() );
$prefix     = uu_component_arg_or_default( $args, 'prefix', 'Component_Audit' );
$output_dir = uu_component_prepare_output_dir( $output_dir );

$sources = array(
	'aws-widget-summary'     => uu_component_arg_or_default( $args, 'aws-widget-summary', uu_component_preferred_source_path( '/Users/brianthurber/Desktop/DESKTOP/Widget Audit V1/Widget_Audit.report-summary.v1.csv' ) ),
	'aws-plugin-summary'     => uu_component_arg_or_default( $args, 'aws-plugin-summary', uu_component_preferred_source_path( '/Users/brianthurber/Desktop/DESKTOP/AWS Audit CSV Docs/AWS_Plugin_Audit.report-summary.v1.csv' ) ),
	'wpengine-widget-summary'=> uu_component_arg_or_default( $args, 'wpengine-widget-summary', uu_component_preferred_source_path( '/Users/brianthurber/Desktop/DESKTOP/WP Engine Full Audit/WP_Engine_Widget_Audit.report-summary.v1.csv' ) ),
	'wpengine-plugin-summary'=> uu_component_arg_or_default( $args, 'wpengine-plugin-summary', uu_component_default_wpengine_runtime_dir() . '/WP_Engine_Standalone_Plugin_Audit.chunk-*.report-summary.rerun.v1.csv' ),
	'aws-widget-matched'     => uu_component_arg_or_default( $args, 'aws-widget-matched', uu_component_preferred_source_path( '/Users/brianthurber/Desktop/DESKTOP/Widget Audit V1/Widget_Audit.report-matched-urls.v1.csv' ) ),
	'aws-plugin-matched'     => uu_component_arg_or_default( $args, 'aws-plugin-matched', uu_component_preferred_source_path( '/Users/brianthurber/Desktop/DESKTOP/AWS Audit CSV Docs/AWS_Plugin_Audit.report-matched-urls.v1.csv' ) ),
	'wpengine-widget-matched'=> uu_component_arg_or_default( $args, 'wpengine-widget-matched', uu_component_preferred_source_path( '/Users/brianthurber/Desktop/DESKTOP/WP Engine Full Audit/WP_Engine_Widget_Audit.report-matched-urls.v1.csv' ) ),
	'wpengine-plugin-matched'=> uu_component_arg_or_default( $args, 'wpengine-plugin-matched', uu_component_default_wpengine_runtime_dir() . '/WP_Engine_Standalone_Plugin_Audit.chunk-*.report-matched-urls.rerun.v1.csv' ),
);

$resolved = array();
foreach ( $sources as $key => $spec ) {
	$resolved[ $key ] = uu_component_require_input_files( $key, $spec );
}

$summary_output_file = rtrim( $output_dir, '/' ) . '/' . $prefix . '.report-summary.v1.csv';
$matched_output_file = rtrim( $output_dir, '/' ) . '/' . $prefix . '.report-matched-urls.v1.csv';
$plugin_only_output_file = rtrim( $output_dir, '/' ) . '/' . $prefix . '.report-matched-urls.standalone-plugins-only.v1.csv';

$summary_header = array(
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
	'Usage Status',
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

$matched_header = array(
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

$summary_rows = array_merge(
	uu_component_build_collect_summary_group( $resolved['aws-widget-summary'], 'uu_component_summary_rows_from_widget_report', 'AWS', 'Widget Audit' ),
	uu_component_build_collect_summary_group( $resolved['aws-plugin-summary'], 'uu_component_summary_rows_from_plugin_report', 'AWS', 'Plugin Audit' ),
	uu_component_build_collect_summary_group( $resolved['wpengine-widget-summary'], 'uu_component_summary_rows_from_widget_report', 'WP Engine', 'Widget Audit' ),
	uu_component_build_collect_summary_group( $resolved['wpengine-plugin-summary'], 'uu_component_summary_rows_from_plugin_report', 'WP Engine', 'Plugin Audit' )
);

$matched_rows = array_merge(
	uu_component_build_collect_matched_group( $resolved['aws-widget-matched'], 'uu_component_matched_rows_from_widget_report', 'AWS', 'Widget Audit' ),
	uu_component_build_collect_matched_group( $resolved['aws-plugin-matched'], 'uu_component_matched_rows_from_plugin_report', 'AWS', 'Plugin Audit' ),
	uu_component_build_collect_matched_group( $resolved['wpengine-widget-matched'], 'uu_component_matched_rows_from_widget_report', 'WP Engine', 'Widget Audit' ),
	uu_component_build_collect_matched_group( $resolved['wpengine-plugin-matched'], 'uu_component_matched_rows_from_plugin_report', 'WP Engine', 'Plugin Audit' )
);

uu_component_sort_summary_rows( $summary_rows );
uu_component_sort_matched_rows( $matched_rows );

$plugin_only_matched_rows = array_values(
	array_filter(
		$matched_rows,
		function ( $row ) {
			return 'Standalone Plugin' === (string) ( $row['Component Type'] ?? '' );
		}
	)
);

uu_audit_write_csv_rows( $summary_output_file, $summary_header, $summary_rows );
uu_audit_write_csv_rows( $matched_output_file, $matched_header, $matched_rows );
uu_audit_write_csv_rows( $plugin_only_output_file, $matched_header, $plugin_only_matched_rows );

fwrite( STDOUT, 'Output directory: ' . $output_dir . "\n" );
fwrite( STDOUT, 'Wrote combined component summary CSV to ' . $summary_output_file . ' (' . count( $summary_rows ) . " rows)\n" );
fwrite( STDOUT, 'Wrote combined component matched URL CSV to ' . $matched_output_file . ' (' . count( $matched_rows ) . " rows)\n" );
fwrite( STDOUT, 'Wrote standalone-plugin-only matched URL CSV to ' . $plugin_only_output_file . ' (' . count( $plugin_only_matched_rows ) . " rows)\n" );
