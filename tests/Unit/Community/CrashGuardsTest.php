<?php
/**
 * Unit tests — v1.4.0 Package 2 (Crash blockers) — FluentCommunity portion.
 *
 * Covers fluent-community/update-privacy-settings V10 signature alignment
 * (F-COM-04). Vendor SettingController::updatePrivacySettings(Request $request)
 * expects a vendor Framework Request, not an array — the prior registrar passed
 * an array and produced a PHP TypeError. Registrar now routes through the
 * vendor public helper Utility::updatePrivacySettings($settings) (V3 priority 2
 * — the same call the controller invokes internally), with a typed WP_Error
 * guard and a direct-option fallback when vendor symbols are absent.
 *
 * @package Fluent_Abilities\Tests\Unit\Community
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { return $text; }
}

// Test-controllable Utility stub aliased into the FluentCommunity namespace.
class FluentCommunityCrashGuardsUtilityStub {
	public static $last_settings = null;
	public static $throw_message = null;
	public static function updatePrivacySettings( $settings ) {
		if ( null !== self::$throw_message ) {
			throw new \TypeError( self::$throw_message );
		}
		self::$last_settings = $settings;
	}
}

require_once dirname( __DIR__, 3 ) . '/includes/community/abilities-v2.php';

class FluentCommunityCrashGuardsTest extends TestCase {

	protected function setUp(): void {
		global $_wp_registered_abilities;
		$_wp_registered_abilities = array();
		update_option( 'fluent_abilities_enabled_modules', array( 'community' ) );
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'fluent_community_read', 'fluent_community_write', 'fluent_community_admin' );

		FluentCommunityCrashGuardsUtilityStub::$last_settings = null;
		FluentCommunityCrashGuardsUtilityStub::$throw_message = null;

		// Alias the Utility stub before triggering the registration function so
		// that callback class_exists() checks see the stubbed class. Done in
		// setUp() (not file include) so each test starts from a known state.
		if ( ! class_exists( 'FluentCommunity\\App\\Functions\\Utility', false ) ) {
			class_alias( 'FluentCommunityCrashGuardsUtilityStub', 'FluentCommunity\\App\\Functions\\Utility' );
		}

		fluent_abilities_register_community_v2();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['_test_user_caps'], $GLOBALS['_test_current_user_id'] );
		delete_option( 'fluent_abilities_enabled_modules' );
	}

	public function test_update_privacy_settings_registers() {
		$abilities = wp_get_abilities();
		$this->assertArrayHasKey( 'fluent-community/update-privacy-settings', $abilities );
	}

	public function test_update_privacy_settings_routes_via_utility_helper_with_array() {
		// V10 signature-alignment proof: the callback must pass the settings
		// array straight to Utility::updatePrivacySettings, never to the
		// SettingController (which would require a Request object).
		FluentCommunityCrashGuardsUtilityStub::$throw_message = null;

		$abilities = wp_get_abilities();
		$cb        = $abilities['fluent-community/update-privacy-settings']['execute_callback'];
		$result    = $cb( array( 'settings' => array( 'who_can_post' => 'all', 'allow_guests' => 'no' ) ) );

		$this->assertNotInstanceOf( WP_Error::class, $result );
		$this->assertTrue( $result['success'] );
		$this->assertSame(
			array( 'who_can_post' => 'all', 'allow_guests' => 'no' ),
			FluentCommunityCrashGuardsUtilityStub::$last_settings,
			'V10 alignment: Utility::updatePrivacySettings must receive the array directly'
		);
	}

	public function test_update_privacy_settings_returns_wp_error_when_utility_throws() {
		FluentCommunityCrashGuardsUtilityStub::$throw_message = 'simulated vendor TypeError';

		$abilities = wp_get_abilities();
		$cb        = $abilities['fluent-community/update-privacy-settings']['execute_callback'];
		$result    = $cb( array( 'settings' => array( 'who_can_post' => 'all' ) ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'vendor_precondition_failed', $result->get_error_code() );
	}
}
