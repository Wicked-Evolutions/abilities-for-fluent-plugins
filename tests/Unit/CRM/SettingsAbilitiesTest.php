<?php
/**
 * Unit tests — FluentCRM extended Settings cluster (§5.13).
 *
 * @package Fluent_Abilities\Tests\Unit\CRM
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { return $text; }
}

require_once dirname( __DIR__, 3 ) . '/includes/crm/extended-settings.php';

class FluentCRMSettingsAbilitiesTest extends TestCase {

	private const SLUGS = array(
		'fluent-crm/get-settings'                       => 'read',
		'fluent-crm/update-settings'                    => 'write',
		'fluent-crm/get-double-optin-config'            => 'read',
		'fluent-crm/update-double-optin-config'         => 'write',
		'fluent-crm/get-bounce-configs'                 => 'read',
		'fluent-crm/get-auto-subscribe-settings'        => 'read',
		'fluent-crm/update-auto-subscribe-settings'     => 'write',
		'fluent-crm/get-integrations-config'            => 'read',
		'fluent-crm/update-integrations-config'         => 'write',
		'fluent-crm/get-compliance-settings'            => 'read',
		'fluent-crm/update-compliance-settings'         => 'write',
		'fluent-crm/get-experiments-config'             => 'read',
		'fluent-crm/update-experiments-config'          => 'write',
		'fluent-crm/list-experiments-campaigns'         => 'read',
		'fluent-crm/get-system-logs'                    => 'read',
		'fluent-crm/get-cron-status'                    => 'read',
		'fluent-crm/get-old-logs'                       => 'read',
		'fluent-crm/get-abandon-cart-settings'          => 'read',
		'fluent-crm/update-abandon-cart-settings'       => 'write',
	);

	protected function setUp(): void {
		global $_wp_registered_abilities;
		$_wp_registered_abilities = array();
		update_option( 'fluent_abilities_enabled_modules', array( 'crm' ) );
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'fluent_crm_read', 'fluent_crm_write', 'fluent_crm_delete' );
		fluent_abilities_crm_register_extended_settings();
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

	public function test_every_ability_has_object_input_schema() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::SLUGS ) as $slug ) {
			$this->assertSame( 'object', $abilities[ $slug ]['input_schema']['type'], "{$slug} input not object" );
		}
	}

	public function test_write_settings_requires_settings_field() {
		$abilities = wp_get_abilities();
		$this->assertContains( 'settings', $abilities['fluent-crm/update-settings']['input_schema']['required'] );
	}

	public function test_write_permission_callback_accepts_user_with_write_cap() {
		$abilities = wp_get_abilities();
		$cb        = $abilities['fluent-crm/update-settings']['permission_callback'];
		$this->assertTrue( $cb() );
	}

	public function test_write_permission_callback_rejects_user_with_only_read_cap() {
		$abilities                  = wp_get_abilities();
		$GLOBALS['_test_user_caps'] = array( 'fluent_crm_read' );
		$cb                         = $abilities['fluent-crm/update-settings']['permission_callback'];
		$this->assertFalse( $cb() );
	}

	public function test_read_permission_callback_rejects_user_without_any_cap() {
		$abilities                  = wp_get_abilities();
		$GLOBALS['_test_user_caps'] = array();
		$cb                         = $abilities['fluent-crm/get-settings']['permission_callback'];
		$this->assertFalse( $cb() );
	}

	public function test_callbacks_all_invokable() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::SLUGS ) as $slug ) {
			$this->assertIsCallable( $abilities[ $slug ]['execute_callback'] );
		}
	}
}
