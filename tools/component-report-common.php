<?php
/**
 * Shared helpers for combined component audit reports.
 */

require_once __DIR__ . '/audit-cli-common.php';

if ( ! function_exists( 'uu_component_expand_input_files' ) ) {
	/**
	 * Expand an input spec into a list of readable files.
	 *
	 * Supports:
	 * - single absolute file paths
	 * - comma-separated file paths
	 * - glob patterns
	 *
	 * @param string $spec Raw input spec.
	 * @return array<int, string>
	 */
	function uu_component_expand_input_files( $spec ) {
		$spec  = trim( (string) $spec );
		$files = array();

		if ( '' === $spec ) {
			return $files;
		}

		$parts = array_filter(
			array_map(
				'trim',
				explode( ',', $spec )
			)
		);

		foreach ( $parts as $part ) {
			$matches = array();
			if ( false !== strpos( $part, '*' ) || false !== strpos( $part, '?' ) || false !== strpos( $part, '[' ) ) {
				$matches = glob( $part );
				if ( false === $matches ) {
					$matches = array();
				}
			} else {
				$matches = array( $part );
			}

			foreach ( $matches as $match ) {
				if ( is_string( $match ) && '' !== $match && file_exists( $match ) && is_readable( $match ) && ! in_array( $match, $files, true ) ) {
					$files[] = $match;
				}
			}
		}

		sort( $files, SORT_NATURAL );

		return $files;
	}
}

if ( ! function_exists( 'uu_component_base_url_from_endpoint' ) ) {
	/**
	 * Derive a normalized base URL from a lookup endpoint.
	 *
	 * @param string $endpoint Lookup endpoint URL.
	 * @return string
	 */
	function uu_component_base_url_from_endpoint( $endpoint ) {
		$endpoint = trim( (string) $endpoint );
		if ( '' === $endpoint ) {
			return '';
		}

		$parts = parse_url( $endpoint );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		return uu_audit_normalize_url( $parts['scheme'] . '://' . $parts['host'] . '/' );
	}
}

if ( ! function_exists( 'uu_component_plugin_component_type' ) ) {
	/**
	 * Normalize a plugin-audit category to a combined component type.
	 *
	 * @param string $category Source category value.
	 * @return string
	 */
	function uu_component_plugin_component_type( $category ) {
		$category = strtolower( trim( (string) $category ) );

		if ( false !== strpos( $category, 'widget bundle' ) ) {
			return 'Exclude';
		}

		if ( false !== strpos( $category, 'widget' ) ) {
			return 'Widget';
		}

		return 'Standalone Plugin';
	}
}

if ( ! function_exists( 'uu_component_summary_rows_from_widget_report' ) ) {
	/**
	 * Normalize widget summary rows into the combined component schema.
	 *
	 * @param array<int, array<string, mixed>> $rows               Source rows.
	 * @param string                           $environment_family AWS or WP Engine.
	 * @param string                           $source_workflow    Source workflow label.
	 * @return array<int, array<string, string>>
	 */
	function uu_component_summary_rows_from_widget_report( array $rows, $environment_family, $source_workflow ) {
		$output = array();

		foreach ( $rows as $row ) {
			$output[] = array(
				'Environment Family' => (string) $environment_family,
				'Environment Label'  => (string) ( $row['Environment Label'] ?? '' ),
				'Base URL'           => (string) ( $row['Base URL'] ?? '' ),
				'Component Type'     => 'Widget',
				'Source Workflow'    => (string) $source_workflow,
				'Component Slug'     => (string) ( $row['Canonical Slug'] ?? '' ),
				'Component Label'    => (string) ( $row['Preferred Label'] ?? '' ),
				'Widget Type'        => (string) ( $row['Widget Type'] ?? '' ),
				'Widget Class'       => (string) ( $row['Widget Class'] ?? '' ),
				'Classic Widget ID'  => (string) ( $row['Classic Widget ID'] ?? '' ),
				'Bundle / Family'    => (string) ( $row['Bundle / Family'] ?? '' ),
				'Plugin Folder'      => '',
				'Category'           => 'Widget',
				'Signal Type'        => '',
				'Environment Count'  => (string) ( $row['Environment Count'] ?? '' ),
				'Seen In'            => (string) ( $row['Seen In'] ?? '' ),
				'Plugin Activation'  => (string) ( $row['Plugin Activation'] ?? '' ),
				'Matches Found'      => (string) ( $row['Matches Found'] ?? '' ),
				'Confidence'         => (string) ( $row['Confidence'] ?? '' ),
				'Action'             => (string) ( $row['Action'] ?? '' ),
				'Matched By'         => (string) ( $row['Matched By'] ?? '' ),
				'Sample URLs'        => (string) ( $row['Sample URLs'] ?? '' ),
				'Needs Review'       => (string) ( $row['Needs Review'] ?? '' ),
				'Notes'              => (string) ( $row['Notes'] ?? '' ),
				'Lookup Error'       => (string) ( $row['Lookup Error'] ?? '' ),
			);
		}

		return $output;
	}
}

if ( ! function_exists( 'uu_component_summary_rows_from_plugin_report' ) ) {
	/**
	 * Normalize plugin summary rows into the combined component schema.
	 *
	 * @param array<int, array<string, mixed>> $rows               Source rows.
	 * @param string                           $environment_family AWS or WP Engine.
	 * @param string                           $source_workflow    Source workflow label.
	 * @return array<int, array<string, string>>
	 */
	function uu_component_summary_rows_from_plugin_report( array $rows, $environment_family, $source_workflow ) {
		$output = array();

		foreach ( $rows as $row ) {
			$component_type = uu_component_plugin_component_type( $row['Category'] ?? '' );
			if ( 'Exclude' === $component_type ) {
				continue;
			}

			$output[] = array(
				'Environment Family' => (string) $environment_family,
				'Environment Label'  => (string) ( $row['Multisite'] ?? '' ),
				'Base URL'           => '',
				'Component Type'     => $component_type,
				'Source Workflow'    => (string) $source_workflow,
				'Component Slug'     => (string) ( $row['Tracked Item Slug'] ?? '' ),
				'Component Label'    => (string) ( $row['Tracked Item Slug'] ?? '' ),
				'Widget Type'        => '',
				'Widget Class'       => '',
				'Classic Widget ID'  => '',
				'Bundle / Family'    => '',
				'Plugin Folder'      => (string) ( $row['Plugin Folder'] ?? '' ),
				'Category'           => (string) ( $row['Category'] ?? '' ),
				'Signal Type'        => (string) ( $row['Signal Type'] ?? '' ),
				'Environment Count'  => '',
				'Seen In'            => '',
				'Plugin Activation'  => (string) ( $row['Plugin Activation'] ?? '' ),
				'Matches Found'      => (string) ( $row['Matches Found'] ?? '' ),
				'Confidence'         => (string) ( $row['Confidence'] ?? '' ),
				'Action'             => (string) ( $row['Action'] ?? '' ),
				'Matched By'         => (string) ( $row['Matched By'] ?? '' ),
				'Sample URLs'        => (string) ( $row['Sample URLs'] ?? '' ),
				'Needs Review'       => '',
				'Notes'              => (string) ( $row['Notes'] ?? '' ),
				'Lookup Error'       => (string) ( $row['Lookup Error'] ?? '' ),
			);
		}

		return $output;
	}
}

if ( ! function_exists( 'uu_component_matched_rows_from_widget_report' ) ) {
	/**
	 * Normalize widget matched URL rows into the combined component schema.
	 *
	 * @param array<int, array<string, mixed>> $rows               Source rows.
	 * @param string                           $environment_family AWS or WP Engine.
	 * @param string                           $source_workflow    Source workflow label.
	 * @return array<int, array<string, string>>
	 */
	function uu_component_matched_rows_from_widget_report( array $rows, $environment_family, $source_workflow ) {
		$output = array();

		foreach ( $rows as $row ) {
			$output[] = array(
				'Environment Family' => (string) $environment_family,
				'Environment Label'  => (string) ( $row['Environment Label'] ?? '' ),
				'Base URL'           => (string) ( $row['Base URL'] ?? '' ),
				'Component Type'     => 'Widget',
				'Source Workflow'    => (string) $source_workflow,
				'Component Slug'     => (string) ( $row['Canonical Slug'] ?? '' ),
				'Component Label'    => (string) ( $row['Preferred Label'] ?? '' ),
				'Widget Type'        => (string) ( $row['Widget Type'] ?? '' ),
				'Widget Class'       => (string) ( $row['Widget Class'] ?? '' ),
				'Classic Widget ID'  => (string) ( $row['Classic Widget ID'] ?? '' ),
				'Bundle / Family'    => (string) ( $row['Bundle / Family'] ?? '' ),
				'Plugin Folder'      => '',
				'Category'           => 'Widget',
				'Signal Type'        => '',
				'Plugin Activation'  => (string) ( $row['Plugin Activation'] ?? '' ),
				'Site Name'          => (string) ( $row['Site Name'] ?? '' ),
				'Multisite Name'     => (string) ( $row['Multisite Name'] ?? '' ),
				'Blog ID'            => (string) ( $row['Blog ID'] ?? '' ),
				'Post ID'            => (string) ( $row['Post ID'] ?? '' ),
				'Result Title'       => (string) ( $row['Result Title'] ?? '' ),
				'Result Type'        => (string) ( $row['Result Type'] ?? '' ),
				'Matched By'         => (string) ( $row['Matched By'] ?? '' ),
				'Permalink'          => (string) ( $row['Permalink'] ?? '' ),
				'Needs Review'       => (string) ( $row['Needs Review'] ?? '' ),
				'Notes'              => (string) ( $row['Notes'] ?? '' ),
				'Lookup Endpoint'    => (string) ( $row['Lookup Endpoint'] ?? '' ),
				'Lookup Error'       => (string) ( $row['Lookup Error'] ?? '' ),
			);
		}

		return $output;
	}
}

if ( ! function_exists( 'uu_component_matched_rows_from_plugin_report' ) ) {
	/**
	 * Normalize plugin matched URL rows into the combined component schema.
	 *
	 * @param array<int, array<string, mixed>> $rows               Source rows.
	 * @param string                           $environment_family AWS or WP Engine.
	 * @param string                           $source_workflow    Source workflow label.
	 * @return array<int, array<string, string>>
	 */
	function uu_component_matched_rows_from_plugin_report( array $rows, $environment_family, $source_workflow ) {
		$output = array();

		foreach ( $rows as $row ) {
			$component_type = uu_component_plugin_component_type( $row['Category'] ?? '' );
			if ( 'Exclude' === $component_type ) {
				continue;
			}

			$output[] = array(
				'Environment Family' => (string) $environment_family,
				'Environment Label'  => (string) ( $row['Multisite'] ?? '' ),
				'Base URL'           => uu_component_base_url_from_endpoint( $row['Lookup Endpoint'] ?? '' ),
				'Component Type'     => $component_type,
				'Source Workflow'    => (string) $source_workflow,
				'Component Slug'     => (string) ( $row['Tracked Item Slug'] ?? '' ),
				'Component Label'    => (string) ( $row['Tracked Item Slug'] ?? '' ),
				'Widget Type'        => '',
				'Widget Class'       => '',
				'Classic Widget ID'  => '',
				'Bundle / Family'    => '',
				'Plugin Folder'      => (string) ( $row['Plugin Folder'] ?? '' ),
				'Category'           => (string) ( $row['Category'] ?? '' ),
				'Signal Type'        => (string) ( $row['Signal Type'] ?? '' ),
				'Plugin Activation'  => (string) ( $row['Plugin Activation'] ?? '' ),
				'Site Name'          => (string) ( $row['Site Name'] ?? '' ),
				'Multisite Name'     => (string) ( $row['Multisite Name'] ?? '' ),
				'Blog ID'            => (string) ( $row['Blog ID'] ?? '' ),
				'Post ID'            => (string) ( $row['Post ID'] ?? '' ),
				'Result Title'       => (string) ( $row['Result Title'] ?? '' ),
				'Result Type'        => (string) ( $row['Result Type'] ?? '' ),
				'Matched By'         => (string) ( $row['Matched By'] ?? '' ),
				'Permalink'          => (string) ( $row['Permalink'] ?? '' ),
				'Needs Review'       => '',
				'Notes'              => '',
				'Lookup Endpoint'    => (string) ( $row['Lookup Endpoint'] ?? '' ),
				'Lookup Error'       => (string) ( $row['Lookup Error'] ?? '' ),
			);
		}

		return $output;
	}
}

if ( ! function_exists( 'uu_component_sort_summary_rows' ) ) {
	/**
	 * Sort combined summary rows consistently.
	 *
	 * @param array<int, array<string, string>> $rows Rows to sort in place.
	 * @return void
	 */
	function uu_component_sort_summary_rows( array &$rows ) {
		usort(
			$rows,
			function ( $left, $right ) {
				foreach ( array( 'Environment Family', 'Environment Label', 'Component Type', 'Component Slug' ) as $key ) {
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

if ( ! function_exists( 'uu_component_sort_matched_rows' ) ) {
	/**
	 * Sort combined matched URL rows consistently.
	 *
	 * @param array<int, array<string, string>> $rows Rows to sort in place.
	 * @return void
	 */
	function uu_component_sort_matched_rows( array &$rows ) {
		usort(
			$rows,
			function ( $left, $right ) {
				foreach ( array( 'Environment Family', 'Environment Label', 'Component Type', 'Component Slug', 'Permalink' ) as $key ) {
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
