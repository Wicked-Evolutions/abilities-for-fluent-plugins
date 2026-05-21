<?php
/**
 * Unit Tests — Fluent Helper Functions
 *
 * @package Fluent_Abilities\Tests\Unit
 */

use PHPUnit\Framework\TestCase;

class FluentHelpersTest extends TestCase {

	// ── fluent_abilities_pagination() ─────────────────────────────────────────

	public function test_pagination_defaults() {
		$result = fluent_abilities_pagination( array() );
		$this->assertSame( 1, $result['page'] );
		$this->assertSame( 20, $result['per_page'] );
		$this->assertSame( 0, $result['offset'] );
	}

	public function test_pagination_page_3() {
		$result = fluent_abilities_pagination( array( 'page' => 3, 'per_page' => 15 ) );
		$this->assertSame( 3, $result['page'] );
		$this->assertSame( 15, $result['per_page'] );
		$this->assertSame( 30, $result['offset'] );
	}

	public function test_pagination_clamps_per_page_to_100() {
		$result = fluent_abilities_pagination( array( 'per_page' => 500 ) );
		$this->assertSame( 100, $result['per_page'] );
	}

	public function test_pagination_clamps_page_min_to_1() {
		$result = fluent_abilities_pagination( array( 'page' => 0 ) );
		$this->assertSame( 1, $result['page'] );
	}

	public function test_pagination_custom_default_per_page() {
		$result = fluent_abilities_pagination( array(), 50 );
		$this->assertSame( 50, $result['per_page'] );
	}

	// ── fluent_abilities_error() ──────────────────────────────────────────────

	public function test_error_returns_wp_error_instance() {
		$err = fluent_abilities_error( 'not_found', 'Item not found.' );
		$this->assertInstanceOf( WP_Error::class, $err );
		$this->assertSame( 'not_found', $err->get_error_code() );
		$this->assertSame( 'Item not found.', $err->get_error_message() );
	}

	// ── fluent_abilities_pagination_schema() ──────────────────────────────────

	public function test_pagination_schema_has_page_and_per_page() {
		$schema = fluent_abilities_pagination_schema();
		$this->assertArrayHasKey( 'page', $schema );
		$this->assertArrayHasKey( 'per_page', $schema );
	}

	public function test_pagination_schema_types_are_integer() {
		$schema = fluent_abilities_pagination_schema();
		$this->assertSame( 'integer', $schema['page']['type'] );
		$this->assertSame( 'integer', $schema['per_page']['type'] );
	}

	// ── fluent_abilities_get_enabled_modules() ────────────────────────────────

	public function test_get_enabled_modules_returns_array() {
		$modules = fluent_abilities_get_enabled_modules();
		$this->assertIsArray( $modules );
	}

	// ── fluent_abilities_user_can() ───────────────────────────────────────────

	public function test_user_can_returns_bool() {
		$result = fluent_abilities_user_can( 'crm', 'read' );
		$this->assertIsBool( $result );
	}

	// ── fluent_abilities_to_array() (Package 2 — V5 framework boundary, P-A) ──

	public function test_to_array_passthrough_on_plain_array() {
		$this->assertSame( array( 1, 2, 3 ), fluent_abilities_to_array( array( 1, 2, 3 ) ) );
	}

	public function test_to_array_empty_for_null() {
		$this->assertSame( array(), fluent_abilities_to_array( null ) );
	}

	public function test_to_array_uses_to_array_method_when_available() {
		$obj = new class {
			public function toArray() {
				return array( 'a' => 1, 'b' => 2 );
			}
		};
		$this->assertSame( array( 'a' => 1, 'b' => 2 ), fluent_abilities_to_array( $obj ) );
	}

	public function test_to_array_uses_all_method_when_to_array_absent() {
		$obj = new class {
			public function all() {
				return array( 'x', 'y' );
			}
		};
		$this->assertSame( array( 'x', 'y' ), fluent_abilities_to_array( $obj ) );
	}

	public function test_to_array_coerces_traversable_iterator() {
		$it = new ArrayIterator( array( 'p', 'q', 'r' ) );
		$this->assertSame( array( 'p', 'q', 'r' ), fluent_abilities_to_array( $it ) );
	}

	public function test_to_array_result_is_safe_for_array_map() {
		// Simulates the P-A fault: vendor returns a Collection-like object.
		// array_map(callable, $collection) raises a PHP TypeError. Coercing first
		// via the helper produces a plain array that array_map accepts.
		$collection = new class {
			public function toArray() {
				return array(
					(object) array( 'id' => 10 ),
					(object) array( 'id' => 20 ),
					(object) array( 'id' => 30 ),
				);
			}
		};
		$ids = array_map(
			function ( $row ) {
				return (int) $row->id;
			},
			fluent_abilities_to_array( $collection )
		);
		$this->assertSame( array( 10, 20, 30 ), $ids );
	}
}
