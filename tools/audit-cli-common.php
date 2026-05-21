<?php
/**
 * Shared helpers for UU audit CLI scripts.
 */

if ( ! function_exists( 'uu_audit_cli_parse_args' ) ) {
	function uu_audit_cli_parse_args( array $argv ) {
		$args = array();
		foreach ( $argv as $arg ) {
			if ( 0 !== strpos( $arg, '--' ) ) {
				continue;
			}

			$parts = explode( '=', substr( $arg, 2 ), 2 );
			$key   = $parts[0];
			$value = isset( $parts[1] ) ? $parts[1] : true;
			$args[ $key ] = $value;
		}

		return $args;
	}
}

if ( ! function_exists( 'uu_audit_cli_usage' ) ) {
	function uu_audit_cli_usage( $script_name, $usage_line ) {
		$usage = <<<TXT
Usage:
  php {$script_name} {$usage_line}

Map file format:
{
  "Bryce": "https://bryce.umc.utah.edu/",
  "Capitol Reef": "https://capitolreef.umc.utah.edu/",
  "Zion": "https://zion.umc.utah.edu/"
}
TXT;

		fwrite( STDERR, $usage . "\n" );
	}
}

if ( ! function_exists( 'uu_audit_normalize_url' ) ) {
	function uu_audit_normalize_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}

		return rtrim( $url, '/' ) . '/';
	}
}

if ( ! function_exists( 'uu_audit_fetch_json' ) ) {
	function uu_audit_fetch_json( $base_url, array $paths, $timeout = 20 ) {
		$base_url = uu_audit_normalize_url( $base_url );
		foreach ( $paths as $path ) {
			$url     = $base_url . ltrim( $path, '/' );
			$context = stream_context_create(
				array(
					'http' => array(
						'method'  => 'GET',
						'timeout' => $timeout,
						'header'  => "Accept: application/json\r\nUser-Agent: UU-Audit-Fill/0.1\r\n",
					),
				)
			);

			$body = @file_get_contents( $url, false, $context );
			if ( false === $body ) {
				continue;
			}

			$data = json_decode( $body, true );
			if ( is_array( $data ) ) {
				return array( 'url' => $url, 'data' => $data, 'error' => '' );
			}
		}

		return array( 'url' => '', 'data' => null, 'error' => 'Unable to fetch JSON from remote tracker.' );
	}
}

if ( ! function_exists( 'uu_audit_load_map' ) ) {
	function uu_audit_load_map( $map_file ) {
		if ( ! file_exists( $map_file ) ) {
			fwrite( STDERR, "Map JSON not found: {$map_file}\n" );
			exit( 1 );
		}

		$map = json_decode( (string) file_get_contents( $map_file ), true );
		if ( ! is_array( $map ) ) {
			fwrite( STDERR, "Map JSON is invalid.\n" );
			exit( 1 );
		}

		return $map;
	}
}

if ( ! function_exists( 'uu_audit_load_csv_rows' ) ) {
	function uu_audit_load_csv_rows( $input_file ) {
		if ( ! file_exists( $input_file ) ) {
			fwrite( STDERR, "Input CSV not found: {$input_file}\n" );
			exit( 1 );
		}

		$in = fopen( $input_file, 'r' );
		if ( false === $in ) {
			fwrite( STDERR, "Unable to open input CSV.\n" );
			exit( 1 );
		}

		$header = fgetcsv( $in, 0, ',', '"', '' );
		if ( ! is_array( $header ) ) {
			fclose( $in );
			fwrite( STDERR, "Input CSV is empty or invalid.\n" );
			exit( 1 );
		}

		$rows = array();
		while ( ( $row = fgetcsv( $in, 0, ',', '"', '' ) ) !== false ) {
			if ( 1 === count( $row ) && null === $row[0] ) {
				continue;
			}

			if ( 0 === count( array_filter( $row, function ( $value ) {
				return '' !== trim( (string) $value );
			} ) ) ) {
				continue;
			}

			$assoc = array();
			foreach ( $header as $index => $column ) {
				$assoc[ $column ] = isset( $row[ $index ] ) ? $row[ $index ] : '';
			}
			$rows[] = $assoc;
		}
		fclose( $in );

		return array( $header, $rows );
	}
}

if ( ! function_exists( 'uu_audit_write_csv_rows' ) ) {
	function uu_audit_write_csv_rows( $output_file, array $header, array $rows ) {
		$out = fopen( $output_file, 'w' );
		if ( false === $out ) {
			fwrite( STDERR, "Unable to open output CSV for writing.\n" );
			exit( 1 );
		}

		fputcsv( $out, $header, ',', '"', '' );
		foreach ( $rows as $row ) {
			$line = array();
			foreach ( $header as $column ) {
				$line[] = isset( $row[ $column ] ) ? $row[ $column ] : '';
			}
			fputcsv( $out, $line, ',', '"', '' );
		}
		fclose( $out );
	}
}
