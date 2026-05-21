<?php
/**
 * Layer 1 deterministic gate — SchemaValidityTest (issue #110).
 *
 * Reflects EVERY registered ability's input_schema / output_schema
 * (registrar booted; callbacks NEVER invoked) and validates each as a
 * document against the offline JSON Schema draft 2020-12 meta-schema.
 *
 * Deterministically catches: output[x] declared not-an-array, a schema
 * leaf that is a string where an object is required, a `properties`
 * value that is not a schema object, malformed/illegal keyword shapes,
 * missing-anyOf/oneOf structural errors — i.e. the schema-shape defect
 * class (P-B/P-D/P-H family) BEFORE it reaches a live validate_output().
 *
 * Does NOT assert semantic correctness of a well-formed schema vs the
 * vendor (that is VendorContractDiffTest's narrower, pinned scope).
 *
 * @package Fluent_Abilities\Tests\Contract
 */

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/ContractBootstrap.php';
require_once __DIR__ . '/Support/JsonSchemaMeta.php';

#[RunClassInSeparateProcess]
#[PreserveGlobalState(false)]
final class SchemaValidityTest extends TestCase {

	/** @var array<string,array> */
	private static $abilities;

	public static function setUpBeforeClass(): void {
		self::$abilities = ContractBootstrap::boot();
	}

	public function test_surface_is_non_empty(): void {
		$this->assertNotEmpty(
			self::$abilities,
			'Registrar booted zero abilities — bootstrap or loader is broken.'
		);
	}

	/**
	 * Every declared input_schema and output_schema is a structurally
	 * valid JSON Schema 2020-12 document.
	 */
	public function test_every_declared_schema_is_valid_2020_12(): void {
		$validator = JsonSchemaMeta::validator();
		$meta      = JsonSchemaMeta::META_URI;
		$failures  = array();

		foreach ( self::$abilities as $slug => $args ) {
			foreach ( array( 'input_schema', 'output_schema' ) as $key ) {
				if ( ! array_key_exists( $key, $args ) || null === $args[ $key ] ) {
					continue;
				}
				$doc    = JsonSchemaMeta::toData( $args[ $key ] );
				$result = $validator->validate( $doc, $meta );
				if ( ! $result->isValid() ) {
					$err        = $result->error();
					$failures[] = sprintf(
						'%s :: %s — %s',
						$slug,
						$key,
						$err ? $err->message() . ' @ /' . implode( '/', $err->data()->path() ) : 'invalid'
					);
				}
			}
		}

		$this->assertSame(
			array(),
			$failures,
			"Invalid ability schema document(s) vs JSON Schema 2020-12:\n  "
			. implode( "\n  ", $failures )
		);
	}

	/**
	 * The string-vs-object class, asserted explicitly: a declared
	 * input_schema/output_schema must be an array/object schema document,
	 * never a bare scalar/string. (Meta-validation also rejects this;
	 * the explicit assertion documents intent and pinpoints the slug.)
	 */
	public function test_no_schema_is_a_bare_scalar(): void {
		$failures = array();

		foreach ( self::$abilities as $slug => $args ) {
			foreach ( array( 'input_schema', 'output_schema' ) as $key ) {
				if ( ! array_key_exists( $key, $args ) || null === $args[ $key ] ) {
					continue;
				}
				if ( ! is_array( $args[ $key ] ) && ! is_object( $args[ $key ] ) ) {
					$failures[] = sprintf(
						'%s :: %s is a bare %s, not a schema document',
						$slug,
						$key,
						gettype( $args[ $key ] )
					);
				}
			}
		}

		$this->assertSame(
			array(),
			$failures,
			"Bare-scalar schema(s):\n  " . implode( "\n  ", $failures )
		);
	}
}
