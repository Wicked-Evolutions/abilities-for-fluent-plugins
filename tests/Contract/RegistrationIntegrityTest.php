<?php
/**
 * Layer 1 deterministic gate — RegistrationIntegrityTest (issue #110).
 *
 * Asserts the booted ability surface equals the committed manifest by
 * SET EQUALITY (symmetric difference must be empty) — explicitly NOT a
 * hardcoded `== 26`/`== 1068` count. A count assertion is itself the
 * drift trap (#104 over-strip lesson): two compensating deltas net to
 * the same number. Set-equality names exactly which slugs appeared or
 * vanished, so an unintended add/removal (a reconcile that drops a
 * retained ability, a strip that takes one too many) fails loud with
 * the slug, and a deliberate change is a reviewed manifest diff.
 *
 * Also asserts every registered ability exposes a callable
 * execute_callback (never invoked here).
 *
 * @package Fluent_Abilities\Tests\Contract
 */

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/ContractBootstrap.php';

#[RunClassInSeparateProcess]
#[PreserveGlobalState(false)]
final class RegistrationIntegrityTest extends TestCase {

	/** @var array<string,array> */
	private static $abilities;

	/** @var array{slugs:string[]} */
	private static $manifest;

	public static function setUpBeforeClass(): void {
		self::$abilities = ContractBootstrap::boot();
		$path            = __DIR__ . '/fixtures/registered-abilities.manifest.json';
		$decoded         = json_decode( (string) file_get_contents( $path ), true );
		self::$manifest  = is_array( $decoded ) ? $decoded : array( 'slugs' => array() );
	}

	public function test_manifest_fixture_is_well_formed(): void {
		$this->assertIsArray( self::$manifest['slugs'] ?? null, 'manifest.slugs missing/not a list' );
		$this->assertNotEmpty( self::$manifest['slugs'], 'manifest.slugs is empty' );
		$this->assertSame(
			self::$manifest['slugs'],
			array_values( array_unique( self::$manifest['slugs'] ) ),
			'manifest.slugs contains duplicates'
		);
	}

	/**
	 * The release-gate assertion: booted surface ≡ manifest, by set.
	 * Reports added (in code, not manifest) and removed (in manifest,
	 * not code) separately so the drift direction is unambiguous.
	 */
	public function test_booted_surface_equals_manifest_by_set(): void {
		$booted   = array_keys( self::$abilities );
		$manifest = self::$manifest['slugs'];

		sort( $booted );
		sort( $manifest );

		$added   = array_values( array_diff( $booted, $manifest ) );
		$removed = array_values( array_diff( $manifest, $booted ) );

		$msg = '';
		if ( $added ) {
			$msg .= "\nRegistered but NOT in manifest (" . count( $added ) . " — unreviewed addition/drift):\n  "
				. implode( "\n  ", $added );
		}
		if ( $removed ) {
			$msg .= "\nIn manifest but NOT registered (" . count( $removed ) . " — vanished surface / over-strip):\n  "
				. implode( "\n  ", $removed );
		}
		$msg .= "\n\nIf this change is intended, regenerate the manifest "
			. '(php tests/Contract/tools/dump-manifest.php > '
			. "tests/Contract/fixtures/registered-abilities.manifest.json) and review the diff.";

		$this->assertSame( array(), $added, 'Ability-surface drift.' . $msg );
		$this->assertSame( array(), $removed, 'Ability-surface drift.' . $msg );
	}

	public function test_every_registered_ability_has_callable_callback(): void {
		$bad = array();
		foreach ( self::$abilities as $slug => $args ) {
			if ( empty( $args['execute_callback'] ) || ! is_callable( $args['execute_callback'] ) ) {
				$bad[] = $slug;
			}
		}
		$this->assertSame(
			array(),
			$bad,
			"Abilities with no callable execute_callback:\n  " . implode( "\n  ", $bad )
		);
	}

	/**
	 * Guard against a regression to a magic-number assertion: the
	 * informational count in the manifest, if present, must agree with
	 * the slug list length — but the BINDING check above is set equality,
	 * not this number.
	 */
	public function test_informational_count_is_consistent_not_authoritative(): void {
		if ( ! isset( self::$manifest['count_is_informational_only'] ) ) {
			$this->addToAssertionCount( 1 );
			return;
		}
		$this->assertSame(
			count( self::$manifest['slugs'] ),
			self::$manifest['count_is_informational_only'],
			'manifest informational count disagrees with slug list — regenerate the manifest.'
		);
	}
}
