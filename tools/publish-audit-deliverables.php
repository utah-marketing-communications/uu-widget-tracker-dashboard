<?php
/**
 * Publish human-facing audit deliverables into one timestamped Desktop folder.
 *
 * This script copies only summary + matched-URL CSVs into:
 *
 *   {output-dir}/AWS/
 *   {output-dir}/WP Engine/
 *   {output-dir}/Combined/
 *
 * It can also merge chunked WP Engine standalone-plugin rerun files into one
 * summary file and one matched-URL file for easier review.
 *
 * Usage:
 * php tools/publish-audit-deliverables.php \
 *   [--output-dir=/Users/brianthurber/Desktop/Component Audit - 2026-06-03 14.30.00]
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 1 );
}

require_once __DIR__ . '/audit-cli-common.php';

/**
 * Return the default timestamped Desktop output directory for this run.
 *
 * @return string
 */
function uu_publish_default_output_dir() {
	$timestamp = date( 'Y-m-d H.i.s' );

	return '/Users/brianthurber/Desktop/Component Audit - ' . $timestamp;
}

/**
 * Return an argument value or a default string.
 *
 * @param array<string, mixed> $args    Parsed CLI args.
 * @param string               $key     Arg key.
 * @param string               $default Default string value.
 * @return string
 */
function uu_publish_arg_or_default( array $args, $key, $default ) {
	if ( ! isset( $args[ $key ] ) || true === $args[ $key ] ) {
		return (string) $default;
	}

	return trim( (string) $args[ $key ] );
}

/**
 * Ensure a directory exists and is writable.
 *
 * @param string $dir Directory path.
 * @return string
 */
function uu_publish_prepare_dir( $dir ) {
	$dir = rtrim( trim( (string) $dir ), '/' );
	if ( '' === $dir ) {
		fwrite( STDERR, "Directory cannot be empty.\n" );
		exit( 1 );
	}

	if ( ! is_dir( $dir ) ) {
		if ( ! mkdir( $dir, 0777, true ) && ! is_dir( $dir ) ) {
			fwrite( STDERR, "Unable to create directory: {$dir}\n" );
			exit( 1 );
		}
	}

	if ( ! is_writable( $dir ) ) {
		fwrite( STDERR, "Directory is not writable: {$dir}\n" );
		exit( 1 );
	}

	return $dir;
}

/**
 * Resolve an input file path by checking a small list of candidates.
 *
 * @param array<int, string> $candidates Candidate file paths.
 * @return string
 */
function uu_publish_first_readable_file( array $candidates ) {
	foreach ( $candidates as $candidate ) {
		$candidate = trim( (string) $candidate );
		if ( '' !== $candidate && is_readable( $candidate ) ) {
			return $candidate;
		}
	}

	return '';
}

/**
 * Resolve a glob spec into readable files.
 *
 * @param string $pattern Glob pattern.
 * @return array<int, string>
 */
function uu_publish_glob_readable_files( $pattern ) {
	$matches = glob( (string) $pattern );
	if ( false === $matches ) {
		return array();
	}

	$files = array();
	foreach ( $matches as $match ) {
		if ( is_readable( $match ) ) {
			$files[] = $match;
		}
	}

	sort( $files, SORT_NATURAL );

	return $files;
}

/**
 * Copy one file into the destination directory using its existing basename.
 *
 * @param string $source Source file.
 * @param string $dest_dir Destination directory.
 * @return string
 */
function uu_publish_copy_file( $source, $dest_dir ) {
	$dest_file = rtrim( $dest_dir, '/' ) . '/' . basename( (string) $source );
	if ( ! copy( $source, $dest_file ) ) {
		fwrite( STDERR, "Unable to copy file: {$source}\n" );
		exit( 1 );
	}

	return $dest_file;
}

/**
 * Merge many same-schema CSV files into one CSV.
 *
 * @param array<int, string> $input_files Input CSV files.
 * @param string             $output_file Output CSV file.
 * @return int Row count written, excluding the header.
 */
function uu_publish_merge_csv_files( array $input_files, $output_file ) {
	$header = array();
	$rows   = array();

	foreach ( $input_files as $input_file ) {
		list( $file_header, $file_rows ) = uu_audit_load_csv_rows( $input_file );

		if ( empty( $header ) ) {
			$header = $file_header;
		}

		foreach ( $file_rows as $file_row ) {
			$rows[] = $file_row;
		}
	}

	if ( empty( $header ) ) {
		fwrite( STDERR, "No CSV header found while merging into {$output_file}\n" );
		exit( 1 );
	}

	uu_audit_write_csv_rows( $output_file, $header, $rows );

	return count( $rows );
}

/**
 * Print usage guidance.
 *
 * @return void
 */
function uu_publish_usage() {
	$usage = <<<TXT
Usage:
  php tools/publish-audit-deliverables.php [--output-dir=/Users/brianthurber/Desktop/Component Audit - 2026-06-03 14.30.00]

Result:
  {output-dir}/AWS/
  {output-dir}/WP Engine/
  {output-dir}/Combined/

Includes only:
  - summary CSVs
  - matched-URL CSVs
TXT;

	fwrite( STDERR, $usage . "\n" );
}

$args = uu_audit_cli_parse_args( $argv );
if ( isset( $args['help'] ) || isset( $args['h'] ) ) {
	uu_publish_usage();
	exit( 0 );
}

$output_dir = uu_publish_arg_or_default( $args, 'output-dir', uu_publish_default_output_dir() );
$output_dir = uu_publish_prepare_dir( $output_dir );

$aws_dir      = uu_publish_prepare_dir( $output_dir . '/AWS' );
$wpengine_dir = uu_publish_prepare_dir( $output_dir . '/WP Engine' );
$combined_dir = uu_publish_prepare_dir( $output_dir . '/Combined' );

$copied = array();

$aws_widget_summary = uu_publish_first_readable_file(
	array(
		'/Users/brianthurber/Desktop/AWS Widget Audit/Widget_Audit.report-summary.v1.csv',
		'/Users/brianthurber/Desktop/DESKTOP/Widget Audit V1/Widget_Audit.report-summary.v1.csv',
	)
);
$aws_widget_matched = uu_publish_first_readable_file(
	array(
		'/Users/brianthurber/Desktop/AWS Widget Audit/Widget_Audit.report-matched-urls.v1.csv',
		'/Users/brianthurber/Desktop/DESKTOP/Widget Audit V1/Widget_Audit.report-matched-urls.v1.csv',
	)
);
$aws_plugin_summary = uu_publish_first_readable_file(
	array(
		'/Users/brianthurber/Desktop/DESKTOP/AWS Audit CSV Docs/AWS_Plugin_Audit.report-summary.v1.csv',
	)
);
$aws_plugin_matched = uu_publish_first_readable_file(
	array(
		'/Users/brianthurber/Desktop/DESKTOP/AWS Audit CSV Docs/AWS_Plugin_Audit.report-matched-urls.v1.csv',
	)
);

$wpengine_widget_summary = uu_publish_first_readable_file(
	array(
		'/Users/brianthurber/Desktop/WP Engine Full Audit/WP_Engine_Widget_Audit.report-summary.v1.csv',
		'/Users/brianthurber/Desktop/DESKTOP/WP Engine Full Audit/WP_Engine_Widget_Audit.report-summary.v1.csv',
	)
);
$wpengine_widget_matched = uu_publish_first_readable_file(
	array(
		'/Users/brianthurber/Desktop/WP Engine Full Audit/WP_Engine_Widget_Audit.report-matched-urls.v1.csv',
		'/Users/brianthurber/Desktop/DESKTOP/WP Engine Full Audit/WP_Engine_Widget_Audit.report-matched-urls.v1.csv',
	)
);
$wpengine_plugin_summary = uu_publish_first_readable_file(
	array(
		'/Users/brianthurber/Desktop/WP Engine Full Audit/WP_Engine_Standalone_Plugin_Audit.report-summary.v1.csv',
	)
);
$wpengine_plugin_matched = uu_publish_first_readable_file(
	array(
		'/Users/brianthurber/Desktop/WP Engine Full Audit/WP_Engine_Standalone_Plugin_Audit.report-matched-urls.v1.csv',
	)
);

$wpengine_plugin_summary_chunks = uu_publish_glob_readable_files(
	'/Users/brianthurber/Sites/umc/wp-content/plugins/uu-widget-tracker-dashboard/reports/runtime/wpengine-standalone-plugin/WP_Engine_Standalone_Plugin_Audit.chunk-*.report-summary.rerun.v1.csv'
);
$wpengine_plugin_matched_chunks = uu_publish_glob_readable_files(
	'/Users/brianthurber/Sites/umc/wp-content/plugins/uu-widget-tracker-dashboard/reports/runtime/wpengine-standalone-plugin/WP_Engine_Standalone_Plugin_Audit.chunk-*.report-matched-urls.rerun.v1.csv'
);

if ( '' !== $aws_widget_summary ) {
	$copied[] = uu_publish_copy_file( $aws_widget_summary, $aws_dir );
}
if ( '' !== $aws_widget_matched ) {
	$copied[] = uu_publish_copy_file( $aws_widget_matched, $aws_dir );
}
if ( '' !== $aws_plugin_summary ) {
	$copied[] = uu_publish_copy_file( $aws_plugin_summary, $aws_dir );
}
if ( '' !== $aws_plugin_matched ) {
	$copied[] = uu_publish_copy_file( $aws_plugin_matched, $aws_dir );
}

if ( '' !== $wpengine_widget_summary ) {
	$copied[] = uu_publish_copy_file( $wpengine_widget_summary, $wpengine_dir );
}
if ( '' !== $wpengine_widget_matched ) {
	$copied[] = uu_publish_copy_file( $wpengine_widget_matched, $wpengine_dir );
}

if ( '' !== $wpengine_plugin_summary ) {
	$copied[] = uu_publish_copy_file( $wpengine_plugin_summary, $wpengine_dir );
} elseif ( ! empty( $wpengine_plugin_summary_chunks ) ) {
	$output_file = $wpengine_dir . '/WP_Engine_Standalone_Plugin_Audit.report-summary.v1.csv';
	uu_publish_merge_csv_files( $wpengine_plugin_summary_chunks, $output_file );
	$copied[] = $output_file;
}

if ( '' !== $wpengine_plugin_matched ) {
	$copied[] = uu_publish_copy_file( $wpengine_plugin_matched, $wpengine_dir );
} elseif ( ! empty( $wpengine_plugin_matched_chunks ) ) {
	$output_file = $wpengine_dir . '/WP_Engine_Standalone_Plugin_Audit.report-matched-urls.v1.csv';
	uu_publish_merge_csv_files( $wpengine_plugin_matched_chunks, $output_file );
	$copied[] = $output_file;
}

$combined_summary = uu_publish_first_readable_file(
	array(
		'/Users/brianthurber/Desktop/DESKTOP/Component Audit/Component_Audit.report-summary.v1.csv',
	)
);
$combined_matched = uu_publish_first_readable_file(
	array(
		'/Users/brianthurber/Desktop/DESKTOP/Component Audit/Component_Audit.report-matched-urls.v1.csv',
	)
);

if ( '' !== $combined_summary ) {
	$copied[] = uu_publish_copy_file( $combined_summary, $combined_dir );
}
if ( '' !== $combined_matched ) {
	$copied[] = uu_publish_copy_file( $combined_matched, $combined_dir );
}

fwrite( STDOUT, "Published deliverables to {$output_dir}\n" );
foreach ( $copied as $copied_file ) {
	fwrite( STDOUT, " - {$copied_file}\n" );
}
