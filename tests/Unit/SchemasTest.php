<?php
/**
 * Unit Tests — Fluent Schema Helpers
 *
 * @package Fluent_Abilities\Tests\Unit
 */

use PHPUnit\Framework\TestCase;

class FluentSchemasTest extends TestCase {

	// ── fluent_abilities_schema_list_output() ─────────────────────────────────

	public function test_list_output_has_total_page_per_page() {
		$schema = fluent_abilities_schema_list_output( 'contacts' );
		$props  = $schema['properties'];
		$this->assertArrayHasKey( 'total', $props );
		$this->assertArrayHasKey( 'page', $props );
		$this->assertArrayHasKey( 'per_page', $props );
	}

	public function test_list_output_has_items_key() {
		$schema = fluent_abilities_schema_list_output( 'tickets' );
		$this->assertArrayHasKey( 'tickets', $schema['properties'] );
	}

	public function test_list_output_items_type_is_array() {
		$schema = fluent_abilities_schema_list_output( 'contacts' );
		$this->assertSame( 'array', $schema['properties']['contacts']['type'] );
	}

	public function test_list_output_with_item_properties() {
		$schema = fluent_abilities_schema_list_output( 'contacts', array(
			'id'    => array( 'type' => 'integer' ),
			'email' => array( 'type' => 'string' ),
		) );
		$item_schema = $schema['properties']['contacts']['items'];
		$this->assertArrayHasKey( 'properties', $item_schema );
		$this->assertArrayHasKey( 'id', $item_schema['properties'] );
	}

	public function test_list_output_no_pages_field() {
		// Fluent Suite intentionally omits 'pages' (unlike WP Suite).
		$schema = fluent_abilities_schema_list_output( 'contacts' );
		$this->assertArrayNotHasKey( 'pages', $schema['properties'] );
	}

	// ── Addendum 27 — schema-fragment vs property-map discriminator ───────────
	//
	// Branch A (FIX): a schema FRAGMENT (string-valued `type`) is used as the
	// item schema directly — NOT stuffed into `properties` (no phantom
	// property named `type` = string "object" → no WP-core validate_output
	// TypeError on populated responses).
	// Branch B (UNCHANGED): a real property MAP keeps current behaviour, even
	// when it legitimately contains a property literally named `type` whose
	// value is a sub-schema ARRAY.

	/** Branch A — the exact affected pattern: the free-form $obj fragment. */
	public function test_list_output_fragment_arg_is_used_as_item_schema_not_properties() {
		$obj    = array( 'type' => 'object', 'additionalProperties' => true );
		$schema = fluent_abilities_schema_list_output( 'unsubscribers', $obj );
		$item   = $schema['properties']['unsubscribers']['items'];
		$this->assertSame( $obj, $item, 'Fragment must be the item schema verbatim.' );
		$this->assertArrayNotHasKey( 'properties', $item, 'No phantom properties map.' );
		// The Addendum 27 fatal signature: properties[type] = string "object".
		$this->assertFalse(
			isset( $item['properties']['type'] ) && is_string( $item['properties']['type'] ),
			'Phantom string-valued `type` property must not be present.'
		);
	}

	/** Branch A — collection + item helpers behave identically. */
	public function test_collection_and_item_output_fragment_arg() {
		$obj  = array( 'type' => 'object', 'additionalProperties' => true );
		$coll = fluent_abilities_schema_collection_output( 'sequences', $obj );
		$this->assertSame( $obj, $coll['properties']['sequences']['items'] );
		$this->assertSame( $obj, fluent_abilities_schema_item_output( $obj ) );
	}

	/** Branch A — a non-object fragment (e.g. array-of-strings) also passes through. */
	public function test_item_output_non_object_fragment_passthrough() {
		$frag = array( 'type' => 'array', 'items' => array( 'type' => 'string' ) );
		$this->assertSame( $frag, fluent_abilities_schema_item_output( $frag ) );
	}

	/** Branch B — a real property MAP is unchanged (regression guard). */
	public function test_list_output_property_map_unchanged() {
		$map    = array(
			'id'    => array( 'type' => 'integer' ),
			'email' => array( 'type' => 'string' ),
		);
		$schema = fluent_abilities_schema_list_output( 'contacts', $map );
		$item   = $schema['properties']['contacts']['items'];
		$this->assertSame( 'object', $item['type'] );
		$this->assertSame( $map, $item['properties'] );
	}

	/**
	 * Branch B — risk edge: a property map containing a REAL property named
	 * `type` whose value is a sub-schema ARRAY. `is_string()` is false, so the
	 * property-map path is taken exactly as before (the 91 risk-edge slugs in
	 * the audit). MUST be behaviour-unchanged or this is a Principle-10 stop.
	 */
	public function test_property_map_with_real_type_property_unchanged() {
		$map = array(
			'id'   => array( 'type' => 'integer' ),
			'type' => array( 'type' => 'string', 'enum' => array( 'lead', 'customer' ) ),
		);
		$item_out = fluent_abilities_schema_item_output( $map );
		$this->assertSame( 'object', $item_out['type'] );
		$this->assertArrayHasKey( 'properties', $item_out );
		$this->assertSame( $map, $item_out['properties'] );
		// The real `type` property survives intact as a sub-schema.
		$this->assertSame( array( 'type' => 'string', 'enum' => array( 'lead', 'customer' ) ), $item_out['properties']['type'] );

		$list = fluent_abilities_schema_list_output( 'boards', $map );
		$this->assertSame( $map, $list['properties']['boards']['items']['properties'] );
	}

	/** Empty / default args — unchanged: a permissive object item. */
	public function test_empty_item_props_unchanged() {
		$this->assertSame( array( 'type' => 'object' ), fluent_abilities_schema_item_output() );
		$list = fluent_abilities_schema_list_output( 'contacts' );
		$this->assertSame( array( 'type' => 'object' ), $list['properties']['contacts']['items'] );
	}

	/** The shared resolver directly — both branches in one place. */
	public function test_item_schema_node_resolver_both_branches() {
		// Fragment (string-valued type) → returned verbatim.
		$frag = array( 'type' => 'object', 'additionalProperties' => true );
		$this->assertSame( $frag, fluent_abilities_item_schema_node( $frag ) );
		// Property map (no string-valued top-level `type`) → wrapped.
		$map = array( 'name' => array( 'type' => 'string' ) );
		$this->assertSame(
			array( 'type' => 'object', 'properties' => $map ),
			fluent_abilities_item_schema_node( $map )
		);
		// Property map WITH a real array-valued `type` property → wrapped (unchanged).
		$map2 = array( 'type' => array( 'type' => 'string' ) );
		$this->assertSame(
			array( 'type' => 'object', 'properties' => $map2 ),
			fluent_abilities_item_schema_node( $map2 )
		);
		// Empty → permissive object.
		$this->assertSame( array( 'type' => 'object' ), fluent_abilities_item_schema_node( array() ) );
	}
}
