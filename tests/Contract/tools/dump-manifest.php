<?php
/**
 * Regenerate the registered-abilities manifest (issue #110 Layer 1).
 *
 *   php tests/Contract/tools/dump-manifest.php > \
 *       tests/Contract/fixtures/registered-abilities.manifest.json
 *
 * Then REVIEW THE DIFF. RegistrationIntegrityTest asserts the booted
 * surface equals this manifest by SET equality (not a count) — an
 * unreviewed delta is the #104-over-strip / silent-drift signal the
 * gate exists to catch. Deterministic: same boot, sorted slugs.
 *
 * @package Fluent_Abilities\Tests\Contract
 */

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "CLI only\n" );
	exit( 1 );
}

define( 'FLUENT_ABILITIES_TESTS', true );

$root = dirname( __DIR__, 3 );
require_once $root . '/vendor/autoload.php';
require_once $root . '/tests/stubs/wordpress-stubs.php';
require_once __DIR__ . '/../Support/ContractBootstrap.php';

$abilities = ContractBootstrap::boot();
$slugs     = array_keys( $abilities );
sort( $slugs );

echo json_encode(
	array(
		'_doc'                        => 'Authoritative registered-ability manifest for RegistrationIntegrityTest (issue #110 Layer 1). Asserted by manifest-EQUALITY (symmetric set diff), never a hardcoded count. Regenerate with tests/Contract/tools/dump-manifest.php and review the diff — an unexpected delta is the #104-over-strip / drift signal.',
		'count_is_informational_only' => count( $slugs ),
		'slugs'                       => array_values( $slugs ),
	),
	JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
) . "\n";
