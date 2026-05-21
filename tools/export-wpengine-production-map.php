<?php
/**
 * Export a WP Engine production-site map JSON for local report scripts.
 *
 * Usage:
 * php tools/export-wpengine-production-map.php \
 *   --output=/abs/path/wpengine-production-sites.v1.json \
 *   --username=api_username \
 *   --password=api_password \
 *   [--account-id=uuid1,uuid2]
 *
 * Credentials may also be provided with:
 * - WPE_API_USERNAME
 * - WPE_API_PASSWORD
 * - a local .env file in the plugin root
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 1 );
}

require_once __DIR__ . '/audit-cli-common.php';

/**
 * Print usage help for the WP Engine production-map export.
 *
 * @return void
 */
function uu_wpengine_cli_usage() {
	$usage = <<<TXT
Usage:
  php tools/export-wpengine-production-map.php --output=/path/wpengine-production-sites.v1.json --username=api_username --password=api_password [--account-id=uuid1,uuid2]

Options:
  --output         Required. JSON map file to write.
  --username       Optional if WPE_API_USERNAME is set.
  --password       Optional if WPE_API_PASSWORD is set.
  --account-id     Optional comma-separated list of account UUIDs to limit the export.
  --skip-domains   Optional. When present, do not query /installs/{id}/domains and rely on install fields only.

Environment variables:
  WPE_API_USERNAME
  WPE_API_PASSWORD

Local .env support:
  Create a .env file in the plugin root with:
  WPE_API_USERNAME=your_username
  WPE_API_PASSWORD=your_password
TXT;

	fwrite( STDERR, $usage . "\n" );
}

/**
 * Load simple KEY=VALUE pairs from a local .env file.
 *
 * @param string $plugin_root Plugin root directory.
 * @return void
 */
function uu_wpengine_load_local_env( $plugin_root ) {
	$env_file = rtrim( (string) $plugin_root, '/' ) . '/.env';
	if ( ! file_exists( $env_file ) || ! is_readable( $env_file ) ) {
		return;
	}

	$lines = file( $env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
	if ( ! is_array( $lines ) ) {
		return;
	}

	foreach ( $lines as $line ) {
		$line = trim( (string) $line );
		if ( '' === $line || '#' === substr( $line, 0, 1 ) ) {
			continue;
		}

		$parts = explode( '=', $line, 2 );
		if ( 2 !== count( $parts ) ) {
			continue;
		}

		$key   = trim( $parts[0] );
		$value = trim( $parts[1] );
		if ( '' === $key ) {
			continue;
		}

		if ( '"' === substr( $value, 0, 1 ) && '"' === substr( $value, -1 ) ) {
			$value = substr( $value, 1, -1 );
		} elseif ( "'" === substr( $value, 0, 1 ) && "'" === substr( $value, -1 ) ) {
			$value = substr( $value, 1, -1 );
		}

		if ( false === getenv( $key ) ) {
			putenv( $key . '=' . $value );
			$_ENV[ $key ]    = $value;
			$_SERVER[ $key ] = $value;
		}
	}
}

uu_wpengine_load_local_env( dirname( __DIR__ ) );

/**
 * Return HTTP status code from the last stream response headers.
 *
 * @param array<int, string> $headers Response headers.
 * @return int
 */
function uu_wpengine_http_status_code( array $headers ) {
	foreach ( $headers as $header_line ) {
		if ( preg_match( '#^HTTP/\S+\s+(\d{3})#', (string) $header_line, $matches ) ) {
			return (int) $matches[1];
		}
	}

	return 0;
}

/**
 * Perform a WP Engine API GET request and decode JSON.
 *
 * @param string $path     API path beginning with /.
 * @param string $username API username.
 * @param string $password API password.
 * @param array<string, scalar> $query Optional query params.
 * @return array{url:string,status:int,data:array<string,mixed>|null,error:string}
 */
function uu_wpengine_api_get_json( $path, $username, $password, array $query = array() ) {
	$base_url = 'https://api.wpengineapi.com/v1';
	$url      = $base_url . $path;

	if ( ! empty( $query ) ) {
		$url .= '?' . http_build_query( $query );
	}

	$auth    = base64_encode( $username . ':' . $password );
	$context = stream_context_create(
		array(
			'http' => array(
				'method'        => 'GET',
				'timeout'       => 30,
				'ignore_errors' => true,
				'header'        => implode(
					"\r\n",
					array(
						'Accept: application/json',
						'Authorization: Basic ' . $auth,
						'User-Agent: UU-Widget-Tracker-Dashboard/0.1',
					)
				) . "\r\n",
			),
		)
	);

	$body    = @file_get_contents( $url, false, $context );
	$headers = array();
	if ( function_exists( 'http_get_last_response_headers' ) ) {
		$last_headers = http_get_last_response_headers();
		if ( is_array( $last_headers ) ) {
			$headers = $last_headers;
		}
	}
	$status  = uu_wpengine_http_status_code( $headers );

	if ( false === $body ) {
		return array(
			'url'    => $url,
			'status' => $status,
			'data'   => null,
			'error'  => 'Request failed.',
		);
	}

	$data = json_decode( $body, true );
	if ( ! is_array( $data ) ) {
		return array(
			'url'    => $url,
			'status' => $status,
			'data'   => null,
			'error'  => 'Response was not valid JSON.',
		);
	}

	if ( $status >= 400 ) {
		$message = isset( $data['message'] ) ? (string) $data['message'] : 'HTTP ' . $status;
		return array(
			'url'    => $url,
			'status' => $status,
			'data'   => $data,
			'error'  => $message,
		);
	}

	return array(
		'url'    => $url,
		'status' => $status,
		'data'   => $data,
		'error'  => '',
	);
}

/**
 * Fetch all paginated results from a WP Engine API collection.
 *
 * @param string               $path     API path beginning with /.
 * @param string               $username API username.
 * @param string               $password API password.
 * @param array<string, scalar> $query    Query parameters.
 * @return array{results:array<int,array<string,mixed>>,error:string}
 */
function uu_wpengine_api_get_all_results( $path, $username, $password, array $query = array() ) {
	$limit   = 100;
	$offset  = 0;
	$results = array();

	while ( true ) {
		$page_query           = $query;
		$page_query['limit']  = $limit;
		$page_query['offset'] = $offset;
		$response             = uu_wpengine_api_get_json( $path, $username, $password, $page_query );

		if ( ! empty( $response['error'] ) ) {
			return array(
				'results' => $results,
				'error'   => $response['error'] . ' [' . $response['url'] . ']',
			);
		}

		$data         = is_array( $response['data'] ) ? $response['data'] : array();
		$page_results = array();
		if ( ! empty( $data['results'] ) && is_array( $data['results'] ) ) {
			$page_results = $data['results'];
		}

		foreach ( $page_results as $page_result ) {
			if ( is_array( $page_result ) ) {
				$results[] = $page_result;
			}
		}

		$next = isset( $data['next'] ) ? trim( (string) $data['next'] ) : '';
		if ( '' === $next || count( $page_results ) < $limit ) {
			break;
		}

		$offset += $limit;
	}

	return array(
		'results' => $results,
		'error'   => '',
	);
}

/**
 * Return account IDs to query.
 *
 * @param array<string, string|bool> $args Parsed CLI args.
 * @param string                     $username API username.
 * @param string                     $password API password.
 * @return array<int, string>
 */
function uu_wpengine_account_ids( array $args, $username, $password ) {
	if ( ! empty( $args['account-id'] ) ) {
		$values = array_filter( array_map( 'trim', explode( ',', (string) $args['account-id'] ) ) );
		return array_values( $values );
	}

	$response = uu_wpengine_api_get_all_results( '/accounts', $username, $password );
	if ( ! empty( $response['error'] ) ) {
		fwrite( STDERR, "Unable to list WP Engine accounts: {$response['error']}\n" );
		exit( 1 );
	}

	$account_ids = array();
	foreach ( $response['results'] as $account ) {
		if ( ! empty( $account['id'] ) ) {
			$account_ids[] = (string) $account['id'];
		}
	}

	if ( empty( $account_ids ) ) {
		fwrite( STDERR, "No WP Engine accounts were returned by the API.\n" );
		exit( 1 );
	}

	return $account_ids;
}

/**
 * Determine whether an install/environment should be treated as production.
 *
 * @param array<string, mixed> $install Install payload.
 * @return bool
 */
function uu_wpengine_is_production_install( array $install ) {
	$environment = strtolower( trim( (string) ( $install['environment'] ?? '' ) ) );
	return in_array( $environment, array( 'production', 'prd' ), true );
}

/**
 * Return true when a domain looks like a temporary WP Engine domain.
 *
 * @param string $domain Domain candidate.
 * @return bool
 */
function uu_wpengine_is_temporary_domain( $domain ) {
	$domain = strtolower( trim( (string) $domain ) );
	if ( '' === $domain ) {
		return true;
	}

	return false !== strpos( $domain, '.wpenginepowered.com' )
		|| false !== strpos( $domain, '.wpengine.com' );
}

/**
 * Normalize a domain string into an https URL.
 *
 * @param string $domain Domain candidate.
 * @return string
 */
function uu_wpengine_domain_to_url( $domain ) {
	$domain = trim( (string) $domain );
	if ( '' === $domain ) {
		return '';
	}

	$domain = preg_replace( '#^https?://#i', '', $domain );
	return uu_audit_normalize_url( 'https://' . $domain );
}

/**
 * Pick the best available production URL for an install.
 *
 * @param array<string, mixed> $install Install payload.
 * @param string               $username API username.
 * @param string               $password API password.
 * @param bool                 $skip_domains Whether to skip per-install domain lookups.
 * @return string
 */
function uu_wpengine_best_install_url( array $install, $username, $password, $skip_domains ) {
	$primary_domain = trim( (string) ( $install['primary_domain'] ?? '' ) );
	if ( '' !== $primary_domain && ! uu_wpengine_is_temporary_domain( $primary_domain ) ) {
		return uu_wpengine_domain_to_url( $primary_domain );
	}

	if ( ! $skip_domains && ! empty( $install['id'] ) ) {
		$response = uu_wpengine_api_get_all_results(
			'/installs/' . rawurlencode( (string) $install['id'] ) . '/domains',
			$username,
			$password
		);

		if ( empty( $response['error'] ) ) {
			$primary_fallback = '';
			$any_fallback     = '';
			foreach ( $response['results'] as $domain_row ) {
				$name = trim( (string) ( $domain_row['name'] ?? '' ) );
				if ( '' === $name ) {
					continue;
				}

				if ( ! uu_wpengine_is_temporary_domain( $name ) ) {
					if ( ! empty( $domain_row['primary'] ) ) {
						return uu_wpengine_domain_to_url( $name );
					}
					if ( '' === $any_fallback ) {
						$any_fallback = $name;
					}
				}

				if ( '' === $primary_fallback && ! empty( $domain_row['primary'] ) ) {
					$primary_fallback = $name;
				}
			}

			if ( '' !== $any_fallback ) {
				return uu_wpengine_domain_to_url( $any_fallback );
			}

			if ( '' !== $primary_fallback ) {
				return uu_wpengine_domain_to_url( $primary_fallback );
			}
		}
	}

	if ( '' !== $primary_domain ) {
		return uu_wpengine_domain_to_url( $primary_domain );
	}

	if ( ! empty( $install['cname'] ) ) {
		return uu_wpengine_domain_to_url( (string) $install['cname'] );
	}

	return '';
}

/**
 * Build a readable, mostly stable label for an install.
 *
 * @param array<string, mixed> $install Install payload.
 * @param string               $url     Chosen install URL.
 * @return string
 */
function uu_wpengine_install_label( array $install, $url ) {
	$host = parse_url( $url, PHP_URL_HOST );
	if ( is_string( $host ) && '' !== $host ) {
		return $host;
	}

	if ( ! empty( $install['name'] ) ) {
		return (string) $install['name'];
	}

	if ( ! empty( $install['site']['name'] ) ) {
		return (string) $install['site']['name'];
	}

	if ( ! empty( $install['id'] ) ) {
		return 'install-' . (string) $install['id'];
	}

	return 'unknown-install';
}

/**
 * Ensure labels are unique inside the generated map.
 *
 * @param array<string, string> $existing_map Current map.
 * @param string                $label        Proposed label.
 * @return string
 */
function uu_wpengine_unique_label( array $existing_map, $label ) {
	$label = trim( (string) $label );
	if ( '' === $label ) {
		$label = 'unnamed-site';
	}

	if ( ! array_key_exists( $label, $existing_map ) ) {
		return $label;
	}

	$index = 2;
	while ( array_key_exists( $label . ' (' . $index . ')', $existing_map ) ) {
		$index++;
	}

	return $label . ' (' . $index . ')';
}

$args = uu_audit_cli_parse_args( $argv );
if ( empty( $args['output'] ) ) {
	uu_wpengine_cli_usage();
	exit( 1 );
}

$output_file = (string) $args['output'];
$username    = isset( $args['username'] ) ? trim( (string) $args['username'] ) : trim( (string) getenv( 'WPE_API_USERNAME' ) );
$password    = isset( $args['password'] ) ? trim( (string) $args['password'] ) : trim( (string) getenv( 'WPE_API_PASSWORD' ) );
$skip_domains = isset( $args['skip-domains'] );

if ( '' === $username || '' === $password ) {
	uu_wpengine_cli_usage();
	fwrite( STDERR, "WP Engine API credentials are required.\n" );
	exit( 1 );
}

$account_ids = uu_wpengine_account_ids( $args, $username, $password );
$map         = array();
$seen_urls   = array();
$install_count = 0;

foreach ( $account_ids as $account_id ) {
	$response = uu_wpengine_api_get_all_results(
		'/installs',
		$username,
		$password,
		array(
			'account_id' => $account_id,
		)
	);

	if ( ! empty( $response['error'] ) ) {
		fwrite( STDERR, "Unable to list installs for account {$account_id}: {$response['error']}\n" );
		exit( 1 );
	}

	foreach ( $response['results'] as $install ) {
		if ( ! uu_wpengine_is_production_install( $install ) ) {
			continue;
		}

		$url = uu_wpengine_best_install_url( $install, $username, $password, $skip_domains );
		if ( '' === $url || isset( $seen_urls[ $url ] ) ) {
			continue;
		}

		$label       = uu_wpengine_unique_label( $map, uu_wpengine_install_label( $install, $url ) );
		$map[ $label ]  = $url;
		$seen_urls[ $url ] = true;
		$install_count++;
	}
}

ksort( $map, SORT_NATURAL | SORT_FLAG_CASE );

$dir = dirname( $output_file );
if ( ! is_dir( $dir ) ) {
	mkdir( $dir, 0775, true );
}

$json = json_encode( $map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
if ( false === $json ) {
	fwrite( STDERR, "Unable to encode WP Engine production map JSON.\n" );
	exit( 1 );
}

file_put_contents( $output_file, $json . "\n" );

fwrite( STDOUT, 'Wrote WP Engine production map to ' . $output_file . ' (' . count( $map ) . " sites from {$install_count} production installs)\n" );
