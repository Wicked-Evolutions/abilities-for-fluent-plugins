<?php
/**
 * Unit Tests — Fluent Abilities Registrar
 *
 * @package Fluent_Abilities\Tests\Unit
 */

use PHPUnit\Framework\TestCase;
use WickedEvolutions\AbilitiesForFluent\Core\Registrar;

class FluentRegistrarTest extends TestCase {

	protected function setUp(): void {
		global $_wp_registered_abilities, $_wp_options_store;
		$_wp_registered_abilities = array();
		$_wp_options_store        = array();
	}

	// ── Class alias ───────────────────────────────────────────────────────────

	public function test_legacy_alias_exists() {
		$this->assertTrue( class_exists( 'Fluent_Abilities_Registrar' ) );
	}

	public function test_legacy_alias_is_same_class_as_namespaced() {
		$this->assertSame(
			Registrar::class,
			get_class( new \Fluent_Abilities_Registrar( 'crm' ) )
		);
	}

	// ── read() ────────────────────────────────────────────────────────────────

	public function test_read_registers_ability() {
		$reg = new Registrar( 'crm' );
		$reg->read( 'fluent-crm/list-contacts', array(
			'label'       => 'List CRM Contacts',
			'description' => 'Returns paginated contacts.',
			'category'    => 'fluent-crm',
			'callback'    => function() { return array(); },
		) );

		$abilities = wp_get_abilities();
		$this->assertArrayHasKey( 'fluent-crm/list-contacts', $abilities );
	}

	public function test_read_sets_readonly_annotation() {
		$reg = new Registrar( 'crm' );
		$reg->read( 'fluent-crm/list-contacts', array(
			'label' => 'T', 'description' => 'T', 'category' => 'fluent-crm',
			'callback' => function() {},
		) );

		$abilities = wp_get_abilities();
		$annotations = $abilities['fluent-crm/list-contacts']['meta']['annotations'];
		$this->assertTrue( $annotations['readonly'] );
		$this->assertFalse( $annotations['destructive'] );
		$this->assertSame( 'read', $annotations['permission'] );
	}

	public function test_read_sets_show_in_rest() {
		$reg = new Registrar( 'crm' );
		$reg->read( 'fluent-crm/list-contacts', array(
			'label' => 'T', 'description' => 'T', 'category' => 'fluent-crm',
			'callback' => function() {},
		) );

		$abilities = wp_get_abilities();
		$this->assertTrue( $abilities['fluent-crm/list-contacts']['meta']['show_in_rest'] );
	}

	public function test_read_uses_module_as_default_category() {
		$reg = new Registrar( 'crm' );
		$reg->read( 'fluent-crm/list-contacts', array(
			'label' => 'T', 'description' => 'T',
			'callback' => function() {},
		) );

		$abilities = wp_get_abilities();
		// No category override — Registrar auto-derives 'fluent-{module}' to
		// match the canonical category slugs registered in
		// includes/ability-categories.php.
		$this->assertSame( 'fluent-crm', $abilities['fluent-crm/list-contacts']['category'] );
	}

	public function test_read_explicit_category_override() {
		$reg = new Registrar( 'crm' );
		$reg->read( 'fluent-crm/list-contacts', array(
			'label' => 'T', 'description' => 'T', 'category' => 'fluent-crm',
			'callback' => function() {},
		) );

		$abilities = wp_get_abilities();
		$this->assertSame( 'fluent-crm', $abilities['fluent-crm/list-contacts']['category'] );
	}

	// ── write() ───────────────────────────────────────────────────────────────

	public function test_write_sets_write_annotation() {
		$reg = new Registrar( 'crm' );
		$reg->write( 'fluent-crm/create-contact', array(
			'label' => 'T', 'description' => 'T', 'category' => 'fluent-crm',
			'callback'    => function() {},
			'annotations' => array( 'idempotent' => false ),
		) );

		$abilities = wp_get_abilities();
		$annotations = $abilities['fluent-crm/create-contact']['meta']['annotations'];
		$this->assertFalse( $annotations['readonly'] );
		$this->assertFalse( $annotations['destructive'] );
		$this->assertSame( 'write', $annotations['permission'] );
	}

	// ── delete() ─────────────────────────────────────────────────────────────

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_delete_sets_destructive_annotation() {
		$reg = new Registrar( 'crm' );
		$reg->delete( 'fluent-crm/delete-tag', array(
			'label' => 'T', 'description' => 'T', 'category' => 'fluent-crm',
			'callback'    => function() {},
			'annotations' => array( 'idempotent' => false ),
		) );

		$abilities = wp_get_abilities();
		$this->assertArrayHasKey( 'fluent-crm/delete-tag', $abilities );
		$annotations = $abilities['fluent-crm/delete-tag']['meta']['annotations'];
		$this->assertTrue( $annotations['destructive'] );
		$this->assertSame( 'delete', $annotations['permission'] );
		$this->assertFalse( $annotations['idempotent'] );
	}

	// ── input_schema ──────────────────────────────────────────────────────────

	public function test_input_schema_default_serializes_as_json_object() {
		$reg = new Registrar( 'crm' );
		$reg->read( 'fluent-crm/list-contacts', array(
			'label' => 'T', 'description' => 'T', 'category' => 'fluent-crm',
			'callback' => function() {},
		) );

		$abilities = wp_get_abilities();
		$this->assertSame(
			'{"type":"object"}',
			json_encode( $abilities['fluent-crm/list-contacts']['input_schema'] )
		);
	}

	// ── output_schema ─────────────────────────────────────────────────────────

	public function test_output_schema_included_when_provided() {
		$reg = new Registrar( 'crm' );
		$reg->read( 'fluent-crm/list-contacts', array(
			'label' => 'T', 'description' => 'T', 'category' => 'fluent-crm',
			'callback'      => function() {},
			'output_schema' => array( 'type' => 'object' ),
		) );

		$abilities = wp_get_abilities();
		$this->assertArrayHasKey( 'output_schema', $abilities['fluent-crm/list-contacts'] );
	}

	public function test_output_schema_omitted_when_absent() {
		$reg = new Registrar( 'crm' );
		$reg->read( 'fluent-crm/list-contacts', array(
			'label' => 'T', 'description' => 'T', 'category' => 'fluent-crm',
			'callback' => function() {},
		) );

		$abilities = wp_get_abilities();
		$this->assertArrayNotHasKey( 'output_schema', $abilities['fluent-crm/list-contacts'] );
	}

	// ── capability override ───────────────────────────────────────────────────

	public function test_capability_override_accepted() {
		$reg = new Registrar( 'fluent' );
		$reg->read( 'fluent/get-active-modules', array(
			'label' => 'T', 'description' => 'T',
			'capability' => 'manage_options',
			'callback'   => function() {},
		) );

		$abilities = wp_get_abilities();
		$this->assertArrayHasKey( 'fluent/get-active-modules', $abilities );
	}
}
