<?php
/**
 * Unit Tests — Messaging v2.0 Abilities (cluster 4.11)
 *
 * Shape + permission tests for the 8 new abilities under fluent-messaging.
 * Execution-side tests run via Mode C (mcp-adapter-execute-ability) live
 * verification on the probe site; this suite covers registration + auth gates.
 *
 * @package Fluent_Abilities\Tests\Unit\Community
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 3 ) . '/includes/messaging/abilities-v2.php';

class MessagingV2AbilitiesTest extends TestCase {

	private const SLUGS = array(
		'fluent-messaging/create-thread'       => 'write',
		'fluent-messaging/update-thread'       => 'write',
		'fluent-messaging/delete-thread'       => 'delete',
		'fluent-messaging/add-participant'     => 'write',
		'fluent-messaging/remove-participant'  => 'delete',
		'fluent-messaging/update-message'      => 'write',
		'fluent-messaging/delete-message'      => 'delete',
		'fluent-messaging/mark-thread-read'    => 'write',
	);

	protected function setUp(): void {
		global $_wp_registered_abilities, $_wp_options_store;
		$_wp_registered_abilities = array();
		$_wp_options_store        = array();
		$GLOBALS['_test_user_caps']        = null;
		$GLOBALS['_test_current_user_id']  = 1;

		fluent_abilities_register_messaging_v2();
	}

	// ── Registration shape ───────────────────────────────────────────────────

	public function test_all_eight_abilities_register() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::SLUGS ) as $slug ) {
			$this->assertArrayHasKey( $slug, $abilities, "missing: $slug" );
		}
	}

	public function test_all_abilities_use_fluent_messaging_category() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::SLUGS ) as $slug ) {
			$this->assertSame( 'fluent-messaging', $abilities[ $slug ]['category'], "wrong category on $slug" );
		}
	}

	public function test_annotations_match_op_type() {
		$abilities = wp_get_abilities();
		foreach ( self::SLUGS as $slug => $op ) {
			$ann = $abilities[ $slug ]['meta']['annotations'];
			$this->assertSame( $op, $ann['permission'], "permission annotation drift on $slug" );
			$this->assertSame( $op === 'read', $ann['readonly'], "readonly drift on $slug" );
			$this->assertSame( $op === 'delete', $ann['destructive'], "destructive drift on $slug" );
		}
	}

	public function test_all_abilities_have_input_schema() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::SLUGS ) as $slug ) {
			$this->assertNotEmpty( $abilities[ $slug ]['input_schema'], "missing input_schema on $slug" );
			$this->assertSame( 'object', $abilities[ $slug ]['input_schema']['type'] );
		}
	}

	public function test_all_abilities_have_output_schema() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::SLUGS ) as $slug ) {
			$this->assertArrayHasKey( 'output_schema', $abilities[ $slug ], "missing output_schema on $slug" );
		}
	}

	public function test_all_abilities_have_label_and_description() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::SLUGS ) as $slug ) {
			$this->assertNotEmpty( $abilities[ $slug ]['label'], "missing label on $slug" );
			$this->assertNotEmpty( $abilities[ $slug ]['description'], "missing description on $slug" );
		}
	}

	// ── Permission_callback denies anonymous ─────────────────────────────────

	public function test_permission_callback_denies_anonymous_for_all() {
		$GLOBALS['_test_current_user_id'] = 0;
		$GLOBALS['_test_user_caps']       = array();

		$abilities = wp_get_abilities();
		foreach ( array_keys( self::SLUGS ) as $slug ) {
			$cb = $abilities[ $slug ]['permission_callback'];
			$this->assertIsCallable( $cb, "permission_callback not callable on $slug" );
			$this->assertFalse( (bool) call_user_func( $cb ), "permission_callback allowed anonymous on $slug" );
		}
	}

	public function test_delete_thread_requires_manage_options() {
		$abilities = wp_get_abilities();
		$cb = $abilities['fluent-messaging/delete-thread']['permission_callback'];

		$GLOBALS['_test_current_user_id'] = 5;
		$GLOBALS['_test_user_caps']       = array( 'fluent_messaging_write', 'fluent_messaging_read' );
		$this->assertFalse( (bool) call_user_func( $cb ), 'delete-thread accepted non-admin caps' );

		$GLOBALS['_test_user_caps'] = array( 'manage_options' );
		$this->assertTrue( (bool) call_user_func( $cb ), 'delete-thread denied manage_options' );
	}

	public function test_write_abilities_accept_fluent_messaging_write_cap() {
		$abilities = wp_get_abilities();
		$GLOBALS['_test_current_user_id'] = 5;
		$GLOBALS['_test_user_caps']       = array( 'fluent_messaging_write' );

		foreach ( self::SLUGS as $slug => $op ) {
			if ( $op === 'delete' || $slug === 'fluent-messaging/delete-thread' ) {
				continue;
			}
			$cb = $abilities[ $slug ]['permission_callback'];
			$this->assertTrue( (bool) call_user_func( $cb ), "fluent_messaging_write rejected on $slug" );
		}
	}

	// ── Input schema required-fields per spec ────────────────────────────────

	public function test_required_fields_match_research_spec() {
		$abilities = wp_get_abilities();

		$required = array(
			'fluent-messaging/update-thread'      => array( 'id' ),
			'fluent-messaging/delete-thread'      => array( 'id' ),
			'fluent-messaging/add-participant'    => array( 'thread_id', 'user_id' ),
			'fluent-messaging/remove-participant' => array( 'thread_id', 'user_id' ),
			'fluent-messaging/update-message'     => array( 'id', 'text' ),
			'fluent-messaging/delete-message'     => array( 'id' ),
			'fluent-messaging/mark-thread-read'   => array( 'thread_id' ),
		);

		foreach ( $required as $slug => $expected ) {
			$schema = $abilities[ $slug ]['input_schema'];
			$this->assertSame( $expected, $schema['required'], "required-fields drift on $slug" );
		}
	}

	public function test_create_thread_has_optional_participant_ids_array() {
		$abilities = wp_get_abilities();
		$props = $abilities['fluent-messaging/create-thread']['input_schema']['properties'];
		$this->assertArrayHasKey( 'participant_ids', $props );
		$this->assertSame( 'array', $props['participant_ids']['type'] );
		$this->assertSame( 'integer', $props['participant_ids']['items']['type'] );
	}

	public function test_idempotent_flags_match_op_semantics() {
		$abilities = wp_get_abilities();
		$this->assertFalse(
			$abilities['fluent-messaging/create-thread']['meta']['annotations']['idempotent'],
			'create-thread should not be idempotent'
		);
		$this->assertTrue(
			$abilities['fluent-messaging/delete-thread']['meta']['annotations']['idempotent'],
			'delete-thread should be idempotent (re-delete returns not_found error, no side effect)'
		);
	}
}
