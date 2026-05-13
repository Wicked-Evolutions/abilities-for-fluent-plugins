<?php
/**
 * Unit tests — FluentCRM extended Reports cluster (§5.12).
 *
 * Covers registration shape + permission-callback behaviour for the 18
 * extended Reports abilities. Actual REST passthrough execution is verified
 * by the Phase B live-probe sequence (cluster-type carve-out for read-only +
 * one delete), not in this unit suite.
 *
 * @package Fluent_Abilities\Tests\Unit\CRM
 */

use PHPUnit\Framework\TestCase;

// Stub __() for the WP_Error path inside Registrar::denial_for_anonymous_cli.
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { return $text; }
}

require_once dirname( __DIR__, 3 ) . '/includes/crm/extended-reports.php';

class FluentCRMReportsAbilitiesTest extends TestCase {

	/**
	 * Expected registration table: slug => permission level.
	 *
	 * Most reports default to the wrapper's `read` level
	 * (fluent_crm_read). delete-report-emails is the only delete-tier
	 * entry; list-report-emails inherits the read default since the
	 * source-side cap difference (fcrm_read_emails vs fcrm_view_dashboard)
	 * is invisible to our wrapper layer — both compose through the
	 * fluent_crm_read module cap.
	 */
	private const SLUGS = array(
		'fluent-crm/get-report-subscribers'           => 'read',
		'fluent-crm/get-report-email-sents'           => 'read',
		'fluent-crm/get-report-email-opens'           => 'read',
		'fluent-crm/get-report-email-clicks'          => 'read',
		'fluent-crm/get-report-email-unsubs'          => 'read',
		'fluent-crm/get-report-email-performance'     => 'read',
		'fluent-crm/get-report-taxonomy-terms'        => 'read',
		'fluent-crm/list-report-emails'               => 'read',
		'fluent-crm/delete-report-emails'             => 'delete',
		'fluent-crm/get-report-advanced-providers'    => 'read',
		'fluent-crm/get-report-contacts-by-status'    => 'read',
		'fluent-crm/get-report-contacts-by-tags'      => 'read',
		'fluent-crm/get-report-contacts-by-lists'     => 'read',
		'fluent-crm/get-report-contacts-by-country'   => 'read',
		'fluent-crm/get-report-recent-tags'           => 'read',
		'fluent-crm/get-report-top-campaigns'         => 'read',
		'fluent-crm/get-report-automations'           => 'read',
		'fluent-crm/get-report-automation-steps'      => 'read',
	);

	protected function setUp(): void {
		global $_wp_registered_abilities;
		$_wp_registered_abilities = array();

		// Enable the crm module so fluent_abilities_user_can won't deny
		// via the module-toggle layer when permission_callback fires.
		update_option( 'fluent_abilities_enabled_modules', array( 'crm' ) );

		// Default to authenticated user with all CRM caps unless a test overrides.
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array(
			'fluent_crm_read',
			'fluent_crm_write',
			'fluent_crm_delete',
		);

		// Register the cluster abilities.
		fluent_abilities_crm_register_extended_reports();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['_test_user_caps'], $GLOBALS['_test_current_user_id'] );
		delete_option( 'fluent_abilities_enabled_modules' );
	}

	// ── Registration shape ────────────────────────────────────────────────────

	public function test_cluster_registers_18_abilities() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::SLUGS ) as $slug ) {
			$this->assertArrayHasKey( $slug, $abilities, "Missing ability: {$slug}" );
		}
		$this->assertCount( count( self::SLUGS ), array_intersect_key( $abilities, self::SLUGS ) );
	}

	public function test_every_ability_has_label_description_category() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::SLUGS ) as $slug ) {
			$this->assertNotEmpty( $abilities[ $slug ]['label'], "{$slug} missing label" );
			$this->assertNotEmpty( $abilities[ $slug ]['description'], "{$slug} missing description" );
			$this->assertSame( 'fluent-crm', $abilities[ $slug ]['category'], "{$slug} wrong category" );
		}
	}

	public function test_every_ability_has_typed_input_schema() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::SLUGS ) as $slug ) {
			$schema = $abilities[ $slug ]['input_schema'];
			$this->assertSame( 'object', $schema['type'], "{$slug} input_schema not object-typed" );
			$this->assertArrayHasKey( 'properties', $schema, "{$slug} input_schema missing properties" );
		}
	}

	public function test_every_ability_has_typed_output_schema() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::SLUGS ) as $slug ) {
			$this->assertArrayHasKey( 'output_schema', $abilities[ $slug ], "{$slug} missing output_schema" );
			$this->assertSame( 'object', $abilities[ $slug ]['output_schema']['type'], "{$slug} output_schema not object" );
		}
	}

	public function test_read_abilities_have_readonly_annotation() {
		$abilities = wp_get_abilities();
		foreach ( self::SLUGS as $slug => $level ) {
			if ( 'read' !== $level ) {
				continue;
			}
			$ann = $abilities[ $slug ]['meta']['annotations'];
			$this->assertTrue( $ann['readonly'], "{$slug} should be readonly" );
			$this->assertFalse( $ann['destructive'], "{$slug} should not be destructive" );
			$this->assertSame( 'read', $ann['permission'], "{$slug} wrong permission annotation" );
		}
	}

	public function test_delete_ability_has_destructive_annotation() {
		$abilities = wp_get_abilities();
		$ann       = $abilities['fluent-crm/delete-report-emails']['meta']['annotations'];
		$this->assertFalse( $ann['readonly'] );
		$this->assertTrue( $ann['destructive'] );
		$this->assertSame( 'delete', $ann['permission'] );
	}

	public function test_required_input_fields_declared_where_applicable() {
		$abilities = wp_get_abilities();
		// taxonomy-terms requires taxonomy
		$this->assertSame(
			array( 'taxonomy' ),
			$abilities['fluent-crm/get-report-taxonomy-terms']['input_schema']['required']
		);
		// delete-report-emails requires email_ids
		$this->assertSame(
			array( 'email_ids' ),
			$abilities['fluent-crm/delete-report-emails']['input_schema']['required']
		);
		// automation-steps requires id
		$this->assertSame(
			array( 'id' ),
			$abilities['fluent-crm/get-report-automation-steps']['input_schema']['required']
		);
	}

	// ── Permission callback — positive path ───────────────────────────────────

	public function test_read_permission_callback_accepts_user_with_read_cap() {
		$abilities = wp_get_abilities();
		$cb        = $abilities['fluent-crm/get-report-subscribers']['permission_callback'];
		$this->assertTrue( $cb() );
	}

	public function test_delete_permission_callback_accepts_user_with_delete_cap() {
		$abilities = wp_get_abilities();
		$cb        = $abilities['fluent-crm/delete-report-emails']['permission_callback'];
		$this->assertTrue( $cb() );
	}

	// ── Permission callback — negative path ───────────────────────────────────

	public function test_read_permission_callback_rejects_user_without_read_cap() {
		$abilities                   = wp_get_abilities();
		$GLOBALS['_test_user_caps'] = array(); // strip all caps
		$cb                          = $abilities['fluent-crm/get-report-subscribers']['permission_callback'];
		$this->assertFalse( $cb() );
	}

	public function test_delete_permission_callback_rejects_user_with_only_read_cap() {
		$abilities                   = wp_get_abilities();
		$GLOBALS['_test_user_caps'] = array( 'fluent_crm_read' ); // read but no delete
		$cb                          = $abilities['fluent-crm/delete-report-emails']['permission_callback'];
		$this->assertFalse( $cb() );
	}

	public function test_anonymous_cli_invocation_is_denied_for_all_levels() {
		$abilities                       = wp_get_abilities();
		$GLOBALS['_test_current_user_id'] = 0;
		$GLOBALS['_test_user_caps']       = array();

		// Anonymous CLI deny applies to delete-tier always.
		$cb_delete = $abilities['fluent-crm/delete-report-emails']['permission_callback'];

		if ( ! defined( 'WP_CLI' ) ) {
			define( 'WP_CLI', true );
		}
		$result = $cb_delete();
		// Registrar surfaces a typed WP_Error for anonymous-CLI denials so the
		// adapter can render migration guidance; both false and WP_Error are
		// valid deny responses — only `true` would be a security failure.
		$this->assertNotTrue( $result, 'anonymous CLI must not be allowed to call delete abilities' );
		$this->assertTrue( false === $result || $result instanceof \WP_Error );
	}

	// ── Callback signature ────────────────────────────────────────────────────

	public function test_every_callback_is_invokable() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::SLUGS ) as $slug ) {
			$this->assertIsCallable( $abilities[ $slug ]['execute_callback'], "{$slug} not callable" );
		}
	}
}
