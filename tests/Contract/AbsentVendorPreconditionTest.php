<?php
/**
 * Layer 1 deterministic gate — V10 absent-vendor precondition class
 * (issue #110 "New gap …": assigned to Layer 1 via a deliberately-
 * degraded fixture, NOT staging — a parity-maintained staging site
 * always HAS Fluent Pro, so it cannot exercise "vendor absent").
 *
 * The degraded fixture is the unit/contract process itself: NO real
 * Fluent vendor classes are loaded (only WP stubs). So an ability that
 * correctly guards `class_exists( '\Vendor\…' )` MUST, when its
 * callback is invoked here, return a typed WP_Error — never fatal,
 * never reach a vendor write.
 *
 * Safety: only callbacks whose FIRST meaningful statement is the
 * `if ( ! class_exists/function_exists(...) ) return fluent_abilities_
 * error(...)` precondition are invoked, so invocation provably stops
 * at the guard before any state change. Destructive abilities are not
 * exercised (the guard returns before any write; additionally the V8
 * wipe is never run anywhere — StaticGuardsTest is static).
 *
 * @package Fluent_Abilities\Tests\Contract
 */

use PhpParser\Node;
use PhpParser\NodeFinder;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/ContractBootstrap.php';
require_once __DIR__ . '/Support/AbilityCallbackIndex.php';

#[RunClassInSeparateProcess]
#[PreserveGlobalState(false)]
final class AbsentVendorPreconditionTest extends TestCase {

	/** @var array<string,array> */
	private static $abilities;

	/** @var array<int,array{slug:string,guardClass:string}> */
	private static $guardFirst = array();

	public static function setUpBeforeClass(): void {
		self::$abilities = ContractBootstrap::boot();
		$index           = new AbilityCallbackIndex( ContractBootstrap::bootedFiles() );
		$finder          = new NodeFinder();

		foreach ( $index->all() as $e ) {
			$stmts = $e['callback']->getStmts();
			if ( empty( $stmts ) ) {
				continue;
			}
			// Examine the first 1-2 statements only — the guard must be
			// up-front for the invocation to be provably side-effect-free.
			foreach ( array_slice( $stmts, 0, 2 ) as $stmt ) {
				if ( ! $stmt instanceof Node\Stmt\If_ ) {
					continue;
				}
				$cond = $stmt->cond;
				if ( ! $cond instanceof Node\Expr\BooleanNot ) {
					continue;
				}
				$inner = $cond->expr;
				if ( ! $inner instanceof Node\Expr\FuncCall
					|| ! $inner->name instanceof Node\Name
					|| ! in_array(
						ltrim( $inner->name->toString(), '\\' ),
						array( 'class_exists', 'function_exists', 'interface_exists' ),
						true
					) ) {
					continue;
				}
				// Body must return a fluent_abilities_error(...).
				$returnsError = false;
				foreach ( $finder->findInstanceOf( $stmt, Node\Expr\FuncCall::class ) as $fc ) {
					if ( $fc->name instanceof Node\Name
						&& 'fluent_abilities_error' === ltrim( $fc->name->toString(), '\\' ) ) {
						$returnsError = true;
						break;
					}
				}
				if ( ! $returnsError ) {
					continue;
				}
				$guardClass = '';
				if ( isset( $inner->args[0] )
					&& $inner->args[0]->value instanceof Node\Scalar\String_ ) {
					$guardClass = ltrim( $inner->args[0]->value->value, '\\' );
				}
				self::$guardFirst[] = array( 'slug' => $e['slug'], 'guardClass' => $guardClass );
				break;
			}
		}
	}

	public function test_guard_first_abilities_were_discovered(): void {
		$this->assertNotEmpty(
			self::$guardFirst,
			'No guard-first vendor-precondition abilities found — the V10 '
			. 'class is unrepresented; detector or surface regressed.'
		);
	}

	/**
	 * The degraded fixture really is degraded: every guarded vendor
	 * class is genuinely ABSENT in this process (no stub masks it).
	 */
	public function test_degraded_fixture_has_no_real_vendor_classes(): void {
		$present = array();
		foreach ( self::$guardFirst as $g ) {
			if ( '' !== $g['guardClass']
				&& ( class_exists( $g['guardClass'] ) || interface_exists( $g['guardClass'] ) ) ) {
				$present[] = $g['guardClass'];
			}
		}
		$this->assertSame(
			array(),
			array_values( array_unique( $present ) ),
			'Vendor class unexpectedly present — fixture is not degraded; '
			. 'V10 would be a false PASS: ' . implode( ', ', $present )
		);
	}

	/**
	 * THE V10 GATE: invoking each guard-first callback with the vendor
	 * absent yields a typed WP_Error and never a fatal/Throwable.
	 */
	public function test_absent_vendor_yields_typed_error_not_fatal(): void {
		$failures = array();

		foreach ( self::$guardFirst as $g ) {
			$slug = $g['slug'];
			if ( ! isset( self::$abilities[ $slug ]['execute_callback'] ) ) {
				continue;
			}
			$cb = self::$abilities[ $slug ]['execute_callback'];
			try {
				$result = $cb( array() );
			} catch ( \Throwable $t ) {
				$failures[] = sprintf(
					'%s — FATAL instead of typed error: %s: %s',
					$slug,
					get_class( $t ),
					$t->getMessage()
				);
				continue;
			}
			$isTyped = is_wp_error( $result )
				|| ( is_array( $result ) && ( isset( $result['error'] ) || isset( $result['code'] ) ) );
			if ( ! $isTyped ) {
				$failures[] = sprintf(
					'%s — vendor absent but result was not a typed error (got %s)',
					$slug,
					is_object( $result ) ? get_class( $result ) : gettype( $result )
				);
			}
		}

		$this->assertSame(
			array(),
			$failures,
			"V10 — absent-vendor precondition not safely typed:\n  "
			. implode( "\n  ", $failures )
		);
	}
}
