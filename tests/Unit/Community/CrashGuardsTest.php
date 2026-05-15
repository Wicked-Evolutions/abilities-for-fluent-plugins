<?php
/**
 * Unit tests — v1.4.0 Package 2 (Crash blockers) — FluentCommunity portion.
 *
 * Covers fluent-community/update-privacy-settings V10 signature alignment
 * (F-COM-04). Vendor SettingController::updatePrivacySettings(Request $request)
 * expects a vendor Framework Request, not an array — the prior registrar passed
 * an array and produced a PHP TypeError. Registrar routes through the vendor
 * public helper Utility::updatePrivacySettings($settings) (V3 priority 2 — the
 * same call the controller invokes internally, applies vendor Arr::only
 * allowlist + storage/cache semantics). When the vendor helper is absent,
 * returns WP_Error('vendor_helper_unavailable', …) — no raw update_option()
 * fallback (would bypass V7 whitelist + V3 vendor invariants).
 *
 * @package Fluent_Abilities\Tests\Unit\Community
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { return $text; }
}

// Test-controllable Utility stub aliased into the FluentCommunity namespace
// in the same-process tests. The absent-helper test runs in a separate
// process so the class genuinely does not exist there.
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
	}

	protected function tearDown(): void {
		unset( $GLOBALS['_test_user_caps'], $GLOBALS['_test_current_user_id'] );
		delete_option( 'fluent_abilities_enabled_modules' );
	}

	/**
	 * Helper: alias the Utility stub into the FluentCommunity namespace and
	 * trigger the registrar. Skipped for the absent-helper test, which runs
	 * in a separate process precisely so the alias is NOT in place.
	 */
	private function register_with_utility_stub(): void {
		if ( ! class_exists( 'FluentCommunity\\App\\Functions\\Utility', false ) ) {
			class_alias( 'FluentCommunityCrashGuardsUtilityStub', 'FluentCommunity\\App\\Functions\\Utility' );
		}
		fluent_abilities_register_community_v2();
	}

	public function test_update_privacy_settings_registers() {
		$this->register_with_utility_stub();
		$abilities = wp_get_abilities();
		$this->assertArrayHasKey( 'fluent-community/update-privacy-settings', $abilities );
	}

	public function test_update_privacy_settings_routes_via_utility_helper_with_array() {
		// V10 signature-alignment proof: the callback must pass the settings
		// array straight to Utility::updatePrivacySettings, never to the
		// SettingController (which would require a Request object).
		$this->register_with_utility_stub();
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
		$this->register_with_utility_stub();
		FluentCommunityCrashGuardsUtilityStub::$throw_message = 'simulated vendor TypeError';

		$abilities = wp_get_abilities();
		$cb        = $abilities['fluent-community/update-privacy-settings']['execute_callback'];
		$result    = $cb( array( 'settings' => array( 'who_can_post' => 'all' ) ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'vendor_precondition_failed', $result->get_error_code() );
	}

	/**
	 * Absent-helper path: when FluentCommunity\App\Functions\Utility is not
	 * loaded (vendor inactive), the callback returns WP_Error with code
	 * 'vendor_helper_unavailable' and NEVER falls back to a raw
	 * update_option() write (would bypass vendor allowlist + storage/cache).
	 *
	 * Runs in a separate process so class_alias from other tests does not
	 * leak in. The Utility class must genuinely not exist in this child
	 * process for the test to be meaningful.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_update_privacy_settings_returns_wp_error_when_helper_absent() {
		// Silence the registrar's bootstrap error_log() — it would otherwise
		// surface as child-process stderr and break PHPUnit's serialized-result
		// parsing in separate-process mode.
		ini_set( 'log_errors', '0' );
		ini_set( 'error_log', '/dev/null' );

		// Reload the registrar in this fresh process. The bootstrap stubs are
		// re-included automatically via PHPUnit's process_isolation.
		require_once dirname( __DIR__, 3 ) . '/includes/community/abilities-v2.php';

		global $_wp_registered_abilities;
		$_wp_registered_abilities = array();
		update_option( 'fluent_abilities_enabled_modules', array( 'community' ) );
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'fluent_community_admin' );

		$this->assertFalse(
			class_exists( 'FluentCommunity\\App\\Functions\\Utility', false ),
			'precondition: Utility class must genuinely not exist in this separate process'
		);

		fluent_abilities_register_community_v2();

		$abilities = wp_get_abilities();
		$this->assertArrayHasKey( 'fluent-community/update-privacy-settings', $abilities );

		$cb     = $abilities['fluent-community/update-privacy-settings']['execute_callback'];
		$result = $cb( array( 'settings' => array( 'who_can_post' => 'all', 'evil_arbitrary_key' => 'should-never-persist' ) ) );

		$this->assertInstanceOf(
			WP_Error::class,
			$result,
			'Vendor-absent path must return WP_Error, not silently persist via update_option()'
		);
		$this->assertSame( 'vendor_helper_unavailable', $result->get_error_code() );

		// Belt-and-braces: confirm the raw option write did NOT happen.
		$this->assertFalse(
			get_option( 'fluent_community_privacy_settings', false ),
			'No raw update_option fallback may run on the vendor-absent path'
		);
	}
}
