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
}
