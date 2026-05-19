<?php
/**
 * Layer 1 deterministic gate — CrashClassScanTest (= executable #108).
 *
 * The deterministic #106/#107 catch with zero site:
 *   Phase A  build vendor encoded-attr map from PINNED installed source,
 *            resolving inherited/trait/parent $casts + mutators (binding).
 *   Self-check  the scanner MUST flag a deliberately-bad sample (proves
 *            it is not silently blind — the executable-#108 control).
 *   Phase B  scan the live booted ability surface → GATE = zero hits.
 *
 * Scope is stated honestly (tests/Contract/COVERAGE-SCOPE.md): pinned
 * vendor models + intraprocedural class-resolved detection of the
 * literal #106/#107 shape. Cross-function indirection, un-pinned vendor
 * models, and non-listed encoders are out of deterministic scope and
 * tracked by follow-up #108 — NOT claimed here.
 *
 * @package Fluent_Abilities\Tests\Contract
 */

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/ContractBootstrap.php';
require_once __DIR__ . '/Support/VendorCastMap.php';
require_once __DIR__ . '/Support/CrashClassScanner.php';

#[RunClassInSeparateProcess]
#[PreserveGlobalState(false)]
final class CrashClassScanTest extends TestCase {

	/** @var VendorCastMap */
	private static $map;

	public static function setUpBeforeClass(): void {
		self::$map = new VendorCastMap( __DIR__ . '/fixtures/vendor' );
	}

	// ── Phase A — pinned-vendor resolution (the binding requirement) ──

	/**
	 * The crash-surface models resolve, and their encoded attrs include
	 * the exact #106/#107 columns — proving inherited/parent resolution
	 * works (CalendarSlot/Calendar/Affiliate each extend a pinned base).
	 */
	public function test_pinned_vendor_models_resolve_with_expected_encoded_attrs(): void {
		$map = self::$map->map();

		$expect = array(
			'FluentBooking\App\Models\CalendarSlot' => array( 'location_settings', 'settings' ),
			'FluentBooking\App\Models\Calendar'     => array( 'settings' ),
			'FluentAffiliate\App\Models\Affiliate'  => array( 'settings' ),
		);
		foreach ( $expect as $fqcn => $attrs ) {
			$this->assertArrayHasKey( $fqcn, $map, "Pinned model not resolved: {$fqcn}" );
			foreach ( $attrs as $a ) {
				$this->assertContains(
					$a,
					$map[ $fqcn ],
					"{$fqcn}: expected vendor-encoded attr '{$a}' not detected (parent/mutator resolution regressed)."
				);
			}
		}
	}

	/**
	 * The binding completeness check (#107-Item-4 discipline): every
	 * unresolved ancestor/trait must be a generic framework-internal
	 * concern (the casting ENGINE), never an `App\Models` model that
	 * could carry per-model $casts. An unresolved App-model parent =
	 * silent under-resolution → fail loud, pin it.
	 */
	public function test_unresolved_ancestors_are_only_framework_internals(): void {
		$leaks = array();
		foreach ( self::$map->unresolved() as $ref ) {
			$isFramework = (bool) preg_match(
				'#(\\\\Framework\\\\|^Concerns\\\\|\\\\Concerns\\\\|Trait$|ForwardsCalls$)#',
				$ref
			);
			if ( ! $isFramework ) {
				$leaks[] = $ref;
			}
		}
		$this->assertSame(
			array(),
			$leaks,
			"Unresolved NON-framework ancestor(s) — pin their source so \$casts cannot be silently missed:\n  "
			. implode( "\n  ", $leaks )
		);
	}

	// ── Self-check — scanner must detect the known-bad sample ──

	/**
	 * Negative control. If this ever yields < 4 hits the scanner has
	 * gone blind and Phase B's "zero hits" would be a false PASS.
	 */
	public function test_scanner_detects_known_bad_sample(): void {
		$scanner = new CrashClassScanner( self::$map->map(), self::$map->shortNameIndex() );
		$scanner->scan( array( __DIR__ . '/fixtures/crash-samples/known-bad.php' ) );
		$hits = $scanner->hits();

		$this->assertGreaterThanOrEqual(
			4,
			count( $hits ),
			"Scanner failed to flag the deliberately-bad #106/#107 sample "
			. '(BAD-1..BAD-4) — the gate would be silently blind. Hits: '
			. var_export( $hits, true )
		);
	}

	// ── Phase B — THE GATE: live surface must be clean ──

	public function test_no_crash_class_in_registered_surface(): void {
		ContractBootstrap::boot();
		$files = ContractBootstrap::bootedFiles();
		$this->assertNotEmpty( $files, 'No booted files to scan — bootstrap broken.' );

		$scanner = new CrashClassScanner( self::$map->map(), self::$map->shortNameIndex() );
		$scanner->scan( $files );
		$hits = $scanner->hits();

		$lines = array_map(
			static function ( $h ) {
				return str_replace( dirname( __DIR__, 2 ) . '/', '', $h['file'] )
					. ':' . $h['line'] . ' — ' . $h['detail'];
			},
			$hits
		);

		$this->assertSame(
			array(),
			$lines,
			"#106/#107 crash class — pre-encoded value assigned/passed into a "
			. "vendor-encoded attribute (let the vendor mutator/\$cast encode "
			. "ONCE; pass the plain value):\n  " . implode( "\n  ", $lines )
		);
	}
}
