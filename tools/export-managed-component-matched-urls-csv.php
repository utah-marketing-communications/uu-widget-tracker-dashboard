<?php
/**
 * Export the primary managed-component matched URL deliverables.
 *
 * Usage:
 * php tools/export-managed-component-matched-urls-csv.php \
 *   --target=all \
 *   --widget-registry=/abs/path/Widget_Audit.registry.v1.csv \
 *   --plugin-inventory=/abs/path/Standalone_Plugin_Audit.inventory.v1.csv \
 *   --map=/abs/path/site-map.json \
 *   --output-dir=/abs/path/output-folder \
 *   [--snapshot=/abs/path/report-usage-snapshot.json]
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 1 );
}

require_once __DIR__ . '/audit-cli-common.php';
require_once __DIR__ . '/report-runtime.php';
require_once __DIR__ . '/component-report-common.php';

if ( ! function_exists( 'uu_managed_matched_usage' ) ) {
	/**
	 * Print usage for the managed matched URL wrapper.
	 *
	 * @return void
	 */
	function uu_managed_matched_usage() {
		$usage = <<<TXT
Usage:
  php tools/export-managed-component-matched-urls-csv.php --target=widgets --widget-registry=/path/Widget_Audit.registry.v1.csv --map=/path/site-map.json --output-dir=/path/output [--snapshot=/path/report-usage-snapshot.json]
  php tools/export-managed-component-matched-urls-csv.php --target=plugins --plugin-inventory=/path/Standalone_Plugin_Audit.inventory.v1.csv --map=/path/site-map.json --output-dir=/path/output [--snapshot=/path/report-usage-snapshot.json]
  php tools/export-managed-component-matched-urls-csv.php --target=all --widget-registry=/path/Widget_Audit.registry.v1.csv --plugin-inventory=/path/Standalone_Plugin_Audit.inventory.v1.csv --map=/path/site-map.json --output-dir=/path/output [--snapshot=/path/report-usage-snapshot.json]

Targets:
  widgets  Writes Widget_Audit.report-matched-urls.v1.csv only.
  plugins  Writes Standalone_Plugin_Audit.report-matched-urls.v1.csv only.
  all      Writes both matched URL reports plus Component_Audit.report-matched-urls.v1.csv.
TXT;

		fwrite( STDERR, $usage . "\n" );
	}
}

if ( ! function_exists( 'uu_managed_matched_require_arg' ) ) {
	/**
	 * Return a required CLI argument or exit with usage.
	 *
	 * @param array<string, mixed> $args CLI args.
	 * @param string               $key  Argument key.
	 * @return string
	 */
	function uu_managed_matched_require_arg( array $args, $key ) {
		if ( empty( $args[ $key ] ) || true === $args[ $key ] ) {
			fwrite( STDERR, "Missing required --{$key} option.\n" );
			uu_managed_matched_usage();
			exit( 1 );
		}

		return (string) $args[ $key ];
	}
}

if ( ! function_exists( 'uu_managed_matched_prepare_output_dir' ) ) {
	/**
	 * Ensure the output directory exists.
	 *
	 * @param string $output_dir Output directory path.
	 * @return string
	 */
	function uu_managed_matched_prepare_output_dir( $output_dir ) {
		$output_dir = rtrim( trim( (string) $output_dir ), '/' );
		if ( '' === $output_dir ) {
			fwrite( STDERR, "Output directory cannot be empty.\n" );
			exit( 1 );
		}

		if ( ! is_dir( $output_dir ) && ! mkdir( $output_dir, 0775, true ) ) {
			fwrite( STDERR, "Unable to create output directory: {$output_dir}\n" );
			exit( 1 );
		}

		if ( ! is_writable( $output_dir ) ) {
			fwrite( STDERR, "Output directory is not writable: {$output_dir}\n" );
			exit( 1 );
		}

		return $output_dir;
	}
}

if ( ! function_exists( 'uu_managed_matched_widget_header' ) ) {
	/**
	 * Return the widget matched URL CSV header.
	 *
	 * @return array<int, string>
	 */
	function uu_managed_matched_widget_header() {
		return array(
			'Environment Label',
			'Base URL',
			'Canonical Slug',
			'Preferred Label',
			'Widget Type',
			'Widget Class',
			'Classic Widget ID',
			'Bundle / Family',
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
	}
}

if ( ! function_exists( 'uu_managed_matched_plugin_header' ) ) {
	/**
	 * Return the standalone plugin matched URL CSV header.
	 *
	 * @return array<int, string>
	 */
	function uu_managed_matched_plugin_header() {
		return array(
			'Multisite',
			'Plugin Folder',
			'Tracked Item Slug',
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
			'Lookup Endpoint',
			'Lookup Error',
		);
	}
}

if ( ! function_exists( 'uu_managed_matched_component_header' ) ) {
	/**
	 * Return the combined component matched URL CSV header.
	 *
	 * @return array<int, string>
	 */
	function uu_managed_matched_component_header() {
		return array(
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
	}
}

if ( ! function_exists( 'uu_managed_matched_sort_widget_rows' ) ) {
	/**
	 * Sort widget matched rows consistently.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows to sort in place.
	 * @return void
	 */
	function uu_managed_matched_sort_widget_rows( array &$rows ) {
		usort(
			$rows,
			function ( $left, $right ) {
				foreach ( array( 'Environment Label', 'Canonical Slug', 'Site Name', 'Result Title', 'Permalink' ) as $key ) {
					$compare = strnatcasecmp( (string) ( $left[ $key ] ?? '' ), (string) ( $right[ $key ] ?? '' ) );
					if ( 0 !== $compare ) {
						return $compare;
					}
				}

				return 0;
			}
		);
	}
}

if ( ! function_exists( 'uu_managed_matched_sort_plugin_rows' ) ) {
	/**
	 * Sort standalone plugin matched rows consistently.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows to sort in place.
	 * @return void
	 */
	function uu_managed_matched_sort_plugin_rows( array &$rows ) {
		usort(
			$rows,
			function ( $left, $right ) {
				foreach ( array( 'Multisite', 'Tracked Item Slug', 'Site Name', 'Result Title', 'Permalink' ) as $key ) {
					$compare = strnatcasecmp( (string) ( $left[ $key ] ?? '' ), (string) ( $right[ $key ] ?? '' ) );
					if ( 0 !== $compare ) {
						return $compare;
					}
				}

				return 0;
			}
		);
	}
}

if ( ! function_exists( 'uu_managed_matched_export_widgets' ) ) {
	/**
	 * Export widget matched URL rows.
	 *
	 * @param string                              $widget_registry Widget registry CSV.
	 * @param array<string, string>               $map             Site map.
	 * @param array<string, array<string, mixed>> &$cache          Shared snapshot cache.
	 * @return array<int, array<string, mixed>>
	 */
	function uu_managed_matched_export_widgets( $widget_registry, array $map, array &$cache ) {
		list( $header, $rows ) = uu_audit_load_csv_rows( $widget_registry );
		unset( $header );

		$output_rows = array();
		uu_report_each_widget_context(
			$rows,
			$map,
			$cache,
			function ( array $context ) use ( &$output_rows ) {
				foreach ( uu_report_widget_matched_url_rows( $context ) as $output_row ) {
					$output_rows[] = $output_row;
				}
			}
		);

		uu_managed_matched_sort_widget_rows( $output_rows );

		return $output_rows;
	}
}

if ( ! function_exists( 'uu_managed_matched_export_plugins' ) ) {
	/**
	 * Export standalone plugin matched URL rows.
	 *
	 * @param string                              $plugin_inventory Standalone plugin inventory CSV.
	 * @param array<string, string>               $map              Site map.
	 * @param array<string, array<string, mixed>> &$cache           Shared snapshot cache.
	 * @return array<int, array<string, mixed>>
	 */
	function uu_managed_matched_export_plugins( $plugin_inventory, array $map, array &$cache ) {
		list( $header, $rows ) = uu_audit_load_csv_rows( $plugin_inventory );
		unset( $header );

		$output_rows = array();
		uu_report_each_plugin_context(
			$rows,
			$map,
			$cache,
			function ( array $context ) use ( &$output_rows ) {
				foreach ( uu_report_plugin_matched_url_rows( $context ) as $output_row ) {
					if ( 'Standalone Plugin' !== uu_component_plugin_component_type( $output_row['Category'] ?? '' ) ) {
						continue;
					}

					$output_rows[] = $output_row;
				}
			}
		);

		uu_managed_matched_sort_plugin_rows( $output_rows );

		return $output_rows;
	}
}

if ( ! function_exists( 'uu_managed_matched_component_rows' ) ) {
	/**
	 * Build combined component matched URL rows.
	 *
	 * @param array<int, array<string, mixed>> $widget_rows Widget matched URL rows.
	 * @param array<int, array<string, mixed>> $plugin_rows Standalone plugin matched URL rows.
	 * @return array<int, array<string, string>>
	 */
	function uu_managed_matched_component_rows( array $widget_rows, array $plugin_rows ) {
		$output_rows = array_merge(
			uu_component_matched_rows_from_widget_report( $widget_rows, 'Managed', 'Widget Audit' ),
			uu_component_matched_rows_from_plugin_report( $plugin_rows, 'Managed', 'Plugin Audit' )
		);

		uu_component_sort_matched_rows( $output_rows );

		return $output_rows;
	}
}

$args = uu_audit_cli_parse_args( $argv );
if ( isset( $args['help'] ) || isset( $args['h'] ) ) {
	uu_managed_matched_usage();
	exit( 0 );
}

$target = isset( $args['target'] ) && true !== $args['target'] ? strtolower( trim( (string) $args['target'] ) ) : 'all';
if ( ! in_array( $target, array( 'widgets', 'plugins', 'all' ), true ) ) {
	fwrite( STDERR, "Invalid --target value: {$target}\n" );
	uu_managed_matched_usage();
	exit( 1 );
}

$output_dir    = uu_managed_matched_prepare_output_dir( uu_managed_matched_require_arg( $args, 'output-dir' ) );
$map_file      = uu_managed_matched_require_arg( $args, 'map' );
$snapshot_file = uu_report_snapshot_arg( $args );
$map           = uu_audit_load_map( $map_file );
$cache         = uu_report_load_snapshot_cache( $snapshot_file );

$widget_rows = array();
$plugin_rows = array();

if ( 'widgets' === $target || 'all' === $target ) {
	$widget_registry = uu_managed_matched_require_arg( $args, 'widget-registry' );
	$widget_rows     = uu_managed_matched_export_widgets( $widget_registry, $map, $cache );
	$widget_output   = $output_dir . '/Widget_Audit.report-matched-urls.v1.csv';

	uu_audit_write_csv_rows( $widget_output, uu_managed_matched_widget_header(), $widget_rows );
	fwrite( STDOUT, 'Wrote widget matched URL CSV to ' . $widget_output . ' (' . count( $widget_rows ) . " rows)\n" );
}

if ( 'plugins' === $target || 'all' === $target ) {
	$plugin_inventory = uu_managed_matched_require_arg( $args, 'plugin-inventory' );
	$plugin_rows      = uu_managed_matched_export_plugins( $plugin_inventory, $map, $cache );
	$plugin_output    = $output_dir . '/Standalone_Plugin_Audit.report-matched-urls.v1.csv';

	uu_audit_write_csv_rows( $plugin_output, uu_managed_matched_plugin_header(), $plugin_rows );
	fwrite( STDOUT, 'Wrote standalone plugin matched URL CSV to ' . $plugin_output . ' (' . count( $plugin_rows ) . " rows)\n" );
}

if ( 'all' === $target ) {
	$component_rows   = uu_managed_matched_component_rows( $widget_rows, $plugin_rows );
	$component_output = $output_dir . '/Component_Audit.report-matched-urls.v1.csv';

	uu_audit_write_csv_rows( $component_output, uu_managed_matched_component_header(), $component_rows );
	fwrite( STDOUT, 'Wrote combined component matched URL CSV to ' . $component_output . ' (' . count( $component_rows ) . " rows)\n" );
}

uu_report_save_snapshot_cache( $snapshot_file, $cache );
