<?php
/**
 * Unit tests — FluentCRM medium clusters: AI (§5.14), Abandoned-cart-ops
 * (§5.15), Custom-fields (§5.16), Import (§5.20).
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

require_once dirname( __DIR__, 3 ) . '/includes/crm/extended-misc-medium.php';

class FluentCRMMiscMediumAbilitiesTest extends TestCase {

	private const SLUGS = array(
		// §5.14 AI
		'fluent-crm/get-ai-settings'                        => 'read',
		'fluent-crm/update-ai-settings'                     => 'write',
		'fluent-crm/test-ai-connection'                     => 'write',
		'fluent-crm/generate-ai-content'                    => 'write',
		// §5.15 Abandoned-cart ops
		// §5.16 Custom fields
		'fluent-crm/get-contact-custom-fields'              => 'read',
		'fluent-crm/update-contact-custom-fields'           => 'write',
		'fluent-crm/update-contact-custom-fields-group-name' => 'write',
		// §5.20 Import
		'fluent-crm/upload-import-csv'                      => 'write',
		'fluent-crm/run-import-csv'                         => 'write',
		'fluent-crm/import-from-wp-users'                   => 'write',
		'fluent-crm/run-import-driver'                      => 'write',
	);

	protected function setUp(): void {
		global $_wp_registered_abilities;
		$_wp_registered_abilities = array();
		update_option( 'fluent_abilities_enabled_modules', array( 'crm' ) );
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'fluent_crm_read', 'fluent_crm_write', 'fluent_crm_delete' );
		fluent_abilities_crm_register_extended_misc_medium();
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
		}
	}

	public function test_update_ai_settings_requires_provider() {
		$abilities = wp_get_abilities();
		$this->assertContains( 'provider', $abilities['fluent-crm/update-ai-settings']['input_schema']['required'] );
	}

	public function test_run_import_driver_requires_driver_and_action() {
		$abilities = wp_get_abilities();
		$req       = $abilities['fluent-crm/run-import-driver']['input_schema']['required'];
		$this->assertContains( 'driver', $req );
		$this->assertContains( 'action', $req );
	}

	public function test_callbacks_all_invokable() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::SLUGS ) as $slug ) {
			$this->assertIsCallable( $abilities[ $slug ]['execute_callback'] );
		}
	}
}
