<?php
/**
 * Unit tests — FluentCRM Pro settings + commerce reports (§5.29 + §5.30).
 *
 * @package Fluent_Abilities\Tests\Unit\CRM
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { return $text; }
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
	}
}

require_once dirname( __DIR__, 3 ) . '/includes/crm/extended-pro-settings-and-commerce.php';

class FluentCRMProSettingsAndCommerceAbilitiesTest extends TestCase {

	private const SLUGS = array(
		// §5.29
		'fluent-crm/list-pro-managers'                       => 'read',
		'fluent-crm/create-pro-manager'                      => 'write',
		'fluent-crm/update-pro-manager'                      => 'write',
		'fluent-crm/delete-pro-manager'                      => 'delete',
		'fluent-crm/get-sms-settings'                        => 'read',
		'fluent-crm/update-sms-settings'                     => 'write',
		'fluent-crm/disable-sms-provider'                    => 'write',
		// §5.30
		'fluent-crm/list-commerce-reports-for-provider'      => 'read',
		'fluent-crm/get-commerce-report'                     => 'read',
	);

	protected function setUp(): void {
		global $_wp_registered_abilities;
		$_wp_registered_abilities = array();
		update_option( 'fluent_abilities_enabled_modules', array( 'crm' ) );
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'fluent_crm_read', 'fluent_crm_write', 'fluent_crm_delete' );
		fluent_abilities_crm_register_extended_pro_settings_and_commerce();
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
			$this->assertSame( $tier, $abilities[ $slug ]['meta']['annotations']['permission'], "{$slug} wrong tier" );
		}
	}

	public function test_delete_manager_rejects_read_only() {
		$abilities                  = wp_get_abilities();
		$GLOBALS['_test_user_caps'] = array( 'fluent_crm_read' );
		$cb                         = $abilities['fluent-crm/delete-pro-manager']['permission_callback'];
		$this->assertFalse( $cb() );
	}

	public function test_create_manager_requires_user_id() {
		$abilities = wp_get_abilities();
		$this->assertContains( 'user_id', $abilities['fluent-crm/create-pro-manager']['input_schema']['required'] );
	}

	public function test_commerce_report_requires_provider() {
		$abilities = wp_get_abilities();
		$this->assertContains( 'provider', $abilities['fluent-crm/get-commerce-report']['input_schema']['required'] );
	}

	public function test_callbacks_all_invokable() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::SLUGS ) as $slug ) {
			$this->assertIsCallable( $abilities[ $slug ]['execute_callback'] );
		}
	}
}
