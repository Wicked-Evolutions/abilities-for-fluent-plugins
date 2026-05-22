<?php
/**
 * Unit tests — FluentCRM extended Funnel clusters (§5.9 + §5.10 + §5.11).
 *
 * @package Fluent_Abilities\Tests\Unit\CRM
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { return $text; }
}

require_once dirname( __DIR__, 3 ) . '/includes/crm/extended-funnels.php';

class FluentCRMFunnelsAbilitiesTest extends TestCase {

	private const SLUGS = array(
		// §5.9
		'fluent-crm/list-funnel-triggers'                 => 'read',
		'fluent-crm/save-funnel-sequences'                => 'write',
		'fluent-crm/save-funnel-email-action-fallback'    => 'write',
		'fluent-crm/save-funnel-sequences-step'           => 'write',
		'fluent-crm/save-funnel-email-action'             => 'write',
		'fluent-crm/clone-funnel'                         => 'write',
		'fluent-crm/change-funnel-trigger'                => 'write',
		'fluent-crm/update-funnel-title'                  => 'write',
		'fluent-crm/update-funnel-labels'                 => 'write',
		// §5.10
		'fluent-crm/list-funnel-subscribers'              => 'read',
		'fluent-crm/delete-funnel-subscribers'            => 'delete',
		'fluent-crm/get-funnel-subscriber-detail'         => 'read',
		'fluent-crm/get-funnel-report'                    => 'read',
		'fluent-crm/get-funnel-email-reports'             => 'read',
		'fluent-crm/update-funnel-subscriber-status'      => 'write',
		'fluent-crm/advance-funnel-subscriber'            => 'write',
		'fluent-crm/list-subscriber-automations'          => 'read',
		// §5.11
		'fluent-crm/list-funnel-templates'                => 'read',
		'fluent-crm/import-funnel'                        => 'write',
		'fluent-crm/get-funnel-all-activities'            => 'read',
		'fluent-crm/get-funnel-syncable-counts'           => 'read',
		'fluent-crm/sync-funnel-new-steps'                => 'write',
		'fluent-crm/send-test-funnel-webhook'             => 'write',
	);

	protected function setUp(): void {
		global $_wp_registered_abilities;
		$_wp_registered_abilities = array();
		update_option( 'fluent_abilities_enabled_modules', array( 'crm' ) );
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'fluent_crm_read', 'fluent_crm_write', 'fluent_crm_delete' );
		fluent_abilities_crm_register_extended_funnels();
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

	public function test_delete_funnel_subscribers_rejects_user_with_only_read_cap() {
		$abilities                  = wp_get_abilities();
		$GLOBALS['_test_user_caps'] = array( 'fluent_crm_read' );
		$cb                         = $abilities['fluent-crm/delete-funnel-subscribers']['permission_callback'];
		$this->assertFalse( $cb() );
	}

	public function test_advance_requires_id_subscriber_and_step() {
		$abilities = wp_get_abilities();
		$req       = $abilities['fluent-crm/advance-funnel-subscriber']['input_schema']['required'];
		$this->assertContains( 'id', $req );
		$this->assertContains( 'subscriber_id', $req );
		$this->assertContains( 'to_step_id', $req );
	}

	public function test_callbacks_all_invokable() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::SLUGS ) as $slug ) {
			$this->assertIsCallable( $abilities[ $slug ]['execute_callback'] );
		}
	}
}
