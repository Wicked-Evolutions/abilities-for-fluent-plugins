<?php
/**
 * Layer 1 deterministic gate — V7 + V8 static checks (issue #110,
 * moved into Layer 1 per reviewer).
 *
 * V7  — AST: an ability callback must NOT pass its raw, unfiltered
 *       request param straight into a vendor/DB write sink. The param
 *       must be whitelisted/transformed first (the P1 #82 lesson).
 *
 * V8  — AST guard-PRESENCE: a destructive full-replace ability (its
 *       input_schema declares `confirm_full_replace`) MUST contain,
 *       statically, the confirm/typed-error-on-empty guard. The
 *       destructive wipe is NEVER executed anywhere in this suite —
 *       this test only proves the guard EXISTS in source (the F-CRM-01
 *       lesson encoded: assert the guard, never run the wipe).
 *
 * Honest scope: V7 flags the literal "raw param is a direct argument
 * to a write sink" shape; an intervening sanitise/whitelist of that
 * exact variable clears it. Cross-function laundering is out of
 * deterministic scope (tests/Contract/COVERAGE-SCOPE.md).
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
final class StaticGuardsTest extends TestCase {

	private const WRITE_SINKS = array(
		'create',
		'fill',
		'update',
		'insert',
		'insertgetid',
		'replace',
		'save',
		'updateorcreate',
		'firstorcreate',
	);

	/** @var AbilityCallbackIndex */
	private static $index;

	public static function setUpBeforeClass(): void {
		ContractBootstrap::boot();
		self::$index = new AbilityCallbackIndex( ContractBootstrap::bootedFiles() );
	}

	public function test_index_is_non_empty(): void {
		$this->assertNotEmpty(
			self::$index->all(),
			'No ability callbacks indexed from source — AST index broken.'
		);
	}

	// ── V8 — destructive full-replace guard MUST exist (never run) ──

	public function test_destructive_full_replace_abilities_have_confirm_guard(): void {
		$checked  = 0;
		$missing  = array();

		foreach ( self::$index->all() as $e ) {
			if ( ! AbilityCallbackIndex::schemaDeclaresKey( $e['schema'], 'confirm_full_replace' ) ) {
				continue;
			}
			++$checked;
			$finder = new NodeFinder();

			// Guard token 1: the callback references confirm_full_replace.
			$refsConfirm = false;
			foreach ( $finder->findInstanceOf( $e['callback'], Node\Scalar\String_::class ) as $s ) {
				if ( 'confirm_full_replace' === $s->value ) {
					$refsConfirm = true;
					break;
				}
			}

			// Guard token 2: a typed-error return exists on the path
			// (fluent_abilities_error(...) or a WP_Error) — the
			// reject-unless-confirmed branch.
			$hasTypedError = false;
			foreach ( $finder->findInstanceOf( $e['callback'], Node\Expr\FuncCall::class ) as $fc ) {
				if ( $fc->name instanceof Node\Name
					&& 'fluent_abilities_error' === ltrim( $fc->name->toString(), '\\' ) ) {
					$hasTypedError = true;
					break;
				}
			}
			if ( ! $hasTypedError ) {
				foreach ( $finder->findInstanceOf( $e['callback'], Node\Expr\New_::class ) as $n ) {
					if ( $n->class instanceof Node\Name
						&& 'WP_Error' === ltrim( $n->class->toString(), '\\' ) ) {
						$hasTypedError = true;
						break;
					}
				}
			}

			if ( ! ( $refsConfirm && $hasTypedError ) ) {
				$missing[] = sprintf(
					'%s (%s:%d) — confirm_ref=%s typed_error=%s',
					$e['slug'],
					basename( $e['file'] ),
					$e['line'],
					$refsConfirm ? 'yes' : 'NO',
					$hasTypedError ? 'yes' : 'NO'
				);
			}
		}

		$this->assertGreaterThan(
			0,
			$checked,
			'No confirm_full_replace ability found — the V8 anchor (fluent-crm/'
			. 'update-contact-custom-fields) vanished; RegistrationIntegrityTest '
			. 'should also flag this.'
		);
		$this->assertSame(
			array(),
			$missing,
			"Destructive full-replace ability missing the confirm/typed-error guard "
			. "(V8 — guard MUST exist; the wipe is never executed):\n  "
			. implode( "\n  ", $missing )
		);
	}

	// ── V7 — no unfiltered raw param into a write sink ──

	public function test_no_unfiltered_input_into_vendor_write(): void {
		$hits = array();

		foreach ( self::$index->all() as $e ) {
			$cb = $e['callback'];
			if ( empty( $cb->params ) || ! $cb->params[0]->var instanceof Node\Expr\Variable
				|| ! is_string( $cb->params[0]->var->name ) ) {
				continue;
			}
			$raw    = $cb->params[0]->var->name; // e.g. 'input'
			$finder = new NodeFinder();

			$calls = array_merge(
				$finder->findInstanceOf( $cb, Node\Expr\MethodCall::class ),
				$finder->findInstanceOf( $cb, Node\Expr\StaticCall::class )
			);
			foreach ( $calls as $call ) {
				if ( ! $call->name instanceof Node\Identifier
					|| ! in_array( strtolower( $call->name->toString() ), self::WRITE_SINKS, true ) ) {
					continue;
				}
				foreach ( $call->args as $arg ) {
					if ( $arg->value instanceof Node\Expr\Variable
						&& $arg->value->name === $raw ) {
						$hits[] = sprintf(
							'%s (%s:%d) — $%s passed unfiltered into ->%s()',
							$e['slug'],
							basename( $e['file'] ),
							$call->getLine(),
							$raw,
							strtolower( $call->name->toString() )
						);
					}
				}
			}
		}

		$this->assertSame(
			array(),
			$hits,
			"V7 — raw request param passed unfiltered into a write sink "
			. "(whitelist/transform before persistence):\n  " . implode( "\n  ", $hits )
		);
	}
}
