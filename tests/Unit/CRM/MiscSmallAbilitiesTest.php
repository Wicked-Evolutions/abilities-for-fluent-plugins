<?php
/**
 * Unit tests — FluentCRM extended small clusters: Labels (§5.17),
 * Webhooks (§5.18), Users (§5.19), Forms (§5.21), Docs (§5.22),
 * Global search (§5.31).
 *
 * @package Fluent_Abilities\Tests\Unit\CRM
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { return $text; }
}

require_once dirname( __DIR__, 3 ) . '/includes/crm/extended-misc-small.php';

class FluentCRMMiscSmallAbilitiesTest extends TestCase {

	private const SLUGS = array(
		// §5.17 Labels
		'fluent-crm/list-labels'                => 'read',
		'fluent-crm/create-label'               => 'write',
		'fluent-crm/update-label'               => 'write',
		'fluent-crm/delete-label'               => 'delete',
		// §5.18 Webhooks
		'fluent-crm/list-webhooks'              => 'read',
		'fluent-crm/create-webhook'             => 'write',
		'fluent-crm/update-webhook'             => 'write',
		'fluent-crm/delete-webhook'             => 'delete',
		// §5.19 Users
		'fluent-crm/list-user-roles'            => 'read',
		// §5.21 Forms
		'fluent-crm/list-form-entries'          => 'read',
		'fluent-crm/get-form-entry-detail'      => 'read',
		// §5.22 Docs
		'fluent-crm/list-docs'                  => 'read',
		'fluent-crm/get-doc'                    => 'read',
		// §5.31 Global search
	);

	protected function setUp(): void {
		global $_wp_registered_abilities;
		$_wp_registered_abilities = array();
		update_option( 'fluent_abilities_enabled_modules', array( 'crm' ) );
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'fluent_crm_read', 'fluent_crm_write', 'fluent_crm_delete' );
		fluent_abilities_crm_register_extended_misc_small();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['_test_user_caps'], $GLOBALS['_test_current_user_id'] );
		delete_option( 'fluent_abilities_enabled_modules' );
	}

	public function test_all_slugs_register() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::SLUGS ) as $slug ) {
			$this->assertArrayHasKey( $slug, $abilities, "Missing: {$slug}" );
		}
	}

	public function test_annotations_match_tier() {
		$abilities = wp_get_abilities();
		foreach ( self::SLUGS as $slug => $tier ) {
			$ann = $abilities[ $slug ]['meta']['annotations'];
			$this->assertSame( $tier, $ann['permission'], "{$slug} wrong permission" );
			$this->assertSame( 'read' === $tier, $ann['readonly'], "{$slug} wrong readonly" );
			$this->assertSame( 'delete' === $tier, $ann['destructive'], "{$slug} wrong destructive" );
		}
	}

	public function test_delete_label_rejects_user_with_only_read_cap() {
		$abilities                  = wp_get_abilities();
		$GLOBALS['_test_user_caps'] = array( 'fluent_crm_read' );
		$cb                         = $abilities['fluent-crm/delete-label']['permission_callback'];
		$this->assertFalse( $cb() );
	}

	public function test_get_doc_requires_doc_id() {
		$abilities = wp_get_abilities();
		$this->assertContains( 'doc_id', $abilities['fluent-crm/get-doc']['input_schema']['required'] );
	}

	public function test_form_entry_detail_requires_form_id_and_id() {
		$abilities = wp_get_abilities();
		$req       = $abilities['fluent-crm/get-form-entry-detail']['input_schema']['required'];
		$this->assertContains( 'form_id', $req );
		$this->assertContains( 'id', $req );
	}

	public function test_callbacks_all_invokable() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::SLUGS ) as $slug ) {
			$this->assertIsCallable( $abilities[ $slug ]['execute_callback'] );
		}
	}
}
