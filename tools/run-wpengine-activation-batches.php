<?php
/**
 * Plan or execute WP Engine activation batches against the existing GitHub workflow.
 *
 * Usage:
 * php tools/run-wpengine-activation-batches.php \
 *   --map=/abs/path/wpengine-production-sites.v1.json \
 *   [--batch-size=5] \
 *   [--skip-labels=site-a,site-b] \
 *   [--only-labels=site-c,site-d] \
 *   [--repo=utah-marketing-communications/uu-usage-tracker] \
 *   [--workflow=activate-wp-engine-production-batch.yml] \
 *   [--pause-seconds=0] \
 *   [--plan=/abs/path/wpengine-activation-batches.v1.json] \
 *   [--confirm-each-batch] \
 *   [--execute]
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 1 );
}

require_once __DIR__ . '/audit-cli-common.php';

/**
 * Normalize a comma-separated list argument into unique values.
 *
 * @param mixed $value Raw argument value.
 * @return array<int, string>
 */
function uu_wpe_batch_parse_list( $value ) {
	if ( true === $value || null === $value ) {
		return array();
	}

	$parts = preg_split( '/\s*,\s*/', trim( (string) $value ) );
	if ( ! is_array( $parts ) ) {
		return array();
	}

	$values = array();
	foreach ( $parts as $part ) {
		$part = trim( (string) $part );
		if ( '' !== $part && ! in_array( $part, $values, true ) ) {
			$values[] = $part;
		}
	}

	return $values;
}

/**
 * Split a label=>url map into sequential batches.
 *
 * @param array<string, string> $map        Map of install label => base URL.
 * @param int                   $batch_size Number of sites per batch.
 * @return array<int, array{batch_number:int, labels:array<int,string>, urls:array<string,string>, envs_csv:string}>
 */
function uu_wpe_batch_chunk_map( array $map, $batch_size ) {
	$labels  = array_keys( $map );
	$chunks  = array_chunk( $labels, $batch_size );
	$batches = array();

	foreach ( $chunks as $index => $chunk ) {
		$urls = array();
		foreach ( $chunk as $label ) {
			$urls[ $label ] = $map[ $label ];
		}

		$batches[] = array(
			'batch_number' => $index + 1,
			'labels'       => array_values( $chunk ),
			'urls'         => $urls,
			'envs_csv'     => implode( ', ', $chunk ),
		);
	}

	return $batches;
}

/**
 * Write the planned batches to JSON.
 *
 * @param string                                                                 $plan_file Output file path.
 * @param array<int, array{batch_number:int, labels:array<int,string>, urls:array<string,string>, envs_csv:string}> $batches  Planned batches.
 * @return void
 */
function uu_wpe_batch_write_plan( $plan_file, array $batches ) {
	$data = array(
		'generated_at' => gmdate( 'c' ),
		'batch_count'  => count( $batches ),
		'batches'      => $batches,
	);

	$json = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	if ( false === $json ) {
		fwrite( STDERR, "Unable to encode plan JSON.\n" );
		exit( 1 );
	}

	if ( false === file_put_contents( $plan_file, $json . "\n" ) ) {
		fwrite( STDERR, "Unable to write plan file: {$plan_file}\n" );
		exit( 1 );
	}
}

/**
 * Execute one GitHub workflow batch via gh.
 *
 * @param string $repo       Repository slug.
 * @param string $workflow   Workflow file name.
 * @param string $envs_csv   Comma-separated env labels.
 * @param int    $batch_num  Batch number for logging.
 * @return int Exit code.
 */
function uu_wpe_batch_execute_workflow( $repo, $workflow, $envs_csv, $batch_num ) {
	$cmd = sprintf(
		'gh workflow run %s -R %s -f %s',
		escapeshellarg( $workflow ),
		escapeshellarg( $repo ),
		escapeshellarg( 'envs=' . $envs_csv )
	);

	fwrite( STDOUT, sprintf( "Starting batch %d: %s\n", $batch_num, $envs_csv ) );
	passthru( $cmd, $exit_code );

	return (int) $exit_code;
}

/**
 * Prompt before executing a batch.
 *
 * @param array{batch_number:int, labels:array<int,string>, urls:array<string,string>, envs_csv:string} $batch Batch definition.
 * @return bool
 */
function uu_wpe_batch_confirm( array $batch ) {
	$prompt = sprintf(
		"Proceed with batch %d (%d site(s))? [%s] [y/N]: ",
		$batch['batch_number'],
		count( $batch['labels'] ),
		$batch['envs_csv']
	);

	fwrite( STDOUT, $prompt );
	$input = fgets( STDIN );
	if ( false === $input ) {
		return false;
	}

	$input = strtolower( trim( $input ) );

	return in_array( $input, array( 'y', 'yes' ), true );
}

$args = uu_audit_cli_parse_args( $argv );
if ( empty( $args['map'] ) ) {
	uu_audit_cli_usage(
		'tools/run-wpengine-activation-batches.php',
		'--map=/path/wpengine-production-sites.v1.json [--batch-size=5] [--skip-labels=site-a,site-b] [--only-labels=site-c,site-d] [--plan=/path/batches.json] [--confirm-each-batch] [--execute]'
	);
	exit( 1 );
}

$map_file      = (string) $args['map'];
$batch_size    = isset( $args['batch-size'] ) ? max( 1, (int) $args['batch-size'] ) : 5;
$skip_labels   = uu_wpe_batch_parse_list( $args['skip-labels'] ?? '' );
$only_labels   = uu_wpe_batch_parse_list( $args['only-labels'] ?? '' );
$plan_file     = isset( $args['plan'] ) ? (string) $args['plan'] : '';
$execute       = isset( $args['execute'] ) && false !== $args['execute'];
$confirm_each  = isset( $args['confirm-each-batch'] ) && false !== $args['confirm-each-batch'];
$repo          = isset( $args['repo'] ) ? trim( (string) $args['repo'] ) : 'utah-marketing-communications/uu-usage-tracker';
$workflow      = isset( $args['workflow'] ) ? trim( (string) $args['workflow'] ) : 'activate-wp-engine-production-batch.yml';
$pause_seconds = isset( $args['pause-seconds'] ) ? max( 0, (int) $args['pause-seconds'] ) : 0;

$map = uu_audit_load_map( $map_file );
ksort( $map, SORT_NATURAL );

if ( ! empty( $only_labels ) ) {
	$filtered = array();
	foreach ( $only_labels as $label ) {
		if ( isset( $map[ $label ] ) ) {
			$filtered[ $label ] = $map[ $label ];
		}
	}
	$map = $filtered;
}

foreach ( $skip_labels as $label ) {
	unset( $map[ $label ] );
}

if ( empty( $map ) ) {
	fwrite( STDERR, "No sites remain after applying filters.\n" );
	exit( 1 );
}

$batches = uu_wpe_batch_chunk_map( $map, $batch_size );

fwrite( STDOUT, sprintf( "Prepared %d activation batches from %d site(s).\n", count( $batches ), count( $map ) ) );
foreach ( $batches as $batch ) {
	fwrite(
		STDOUT,
		sprintf(
			"Batch %d (%d): %s\n",
			$batch['batch_number'],
			count( $batch['labels'] ),
			$batch['envs_csv']
		)
	);
}

if ( '' !== $plan_file ) {
	uu_wpe_batch_write_plan( $plan_file, $batches );
	fwrite( STDOUT, "Wrote batch plan to {$plan_file}\n" );
}

if ( ! $execute ) {
	exit( 0 );
}

$gh_check = trim( (string) shell_exec( 'command -v gh 2>/dev/null' ) );
if ( '' === $gh_check ) {
	fwrite( STDERR, "GitHub CLI (gh) is not available in PATH.\n" );
	exit( 1 );
}

foreach ( $batches as $batch ) {
	if ( $confirm_each && ! uu_wpe_batch_confirm( $batch ) ) {
		fwrite( STDOUT, sprintf( "Stopping before batch %d.\n", $batch['batch_number'] ) );
		exit( 0 );
	}

	$exit_code = uu_wpe_batch_execute_workflow( $repo, $workflow, $batch['envs_csv'], $batch['batch_number'] );
	if ( 0 !== $exit_code ) {
		fwrite( STDERR, sprintf( "Batch %d failed with exit code %d.\n", $batch['batch_number'], $exit_code ) );
		exit( $exit_code );
	}

	if ( $pause_seconds > 0 && $batch !== end( $batches ) ) {
		fwrite( STDOUT, sprintf( "Sleeping %d second(s) before next batch.\n", $pause_seconds ) );
		sleep( $pause_seconds );
	}
}

fwrite( STDOUT, "All activation batches submitted successfully.\n" );
