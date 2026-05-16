<?php
/**
 * Unit tests — v1.4.0 Package 3 — Community namespace fixes.
 *
 * Covers:
 *   V2 canonical namespace (F-COM-03): FluentCommunity\\App\\Functions\\Utility
 *   and FluentCommunity\\App\\Services\\NotificationPref absent-vendor guards.
 *   V2 canonical namespace (F-COM-01): FluentCommunity\\Modules\\Course\\Model\\Course
 *   absent-vendor guards for create-course and update-course.
 *
 * Source-level tests verify the correct class paths are referenced.
 * Absent-vendor tests run in separate processes so class_alias from other
 * tests does not bleed in.
 *
 * @package Fluent_Abilities\Tests\Unit\Community
 */

use PHPUnit\Framework\TestCase;

class P3NamespaceFixesTest extends TestCase {

	// ── Source-level: canonical Utility namespace ─────────────────────────────

	public function test_abilities_v2_uses_canonical_utility_namespace() {
		$file = dirname( __DIR__, 3 ) . '/includes/community/abilities-v2.php';
		$src  = file_get_contents( $file );

		$this->assertStringContainsString(
			'FluentCommunity\\App\\Functions\\Utility',
			$src,
			'abilities-v2.php must reference canonical Utility at FluentCommunity\\App\\Functions\\Utility'
		);

		$this->assertStringNotContainsString(
			'App\\Services\\Helper\\Utility',
			$src,
			'abilities-v2.php must not reference drifted namespace App\\Services\\Helper\\Utility'
		);
	}

	public function test_abilities_v2_uses_canonical_notificationpref_namespace() {
		$file = dirname( __DIR__, 3 ) . '/includes/community/abilities-v2.php';
		$src  = file_get_contents( $file );

		$this->assertStringContainsString(
			'FluentCommunity\\App\\Services\\NotificationPref',
			$src,
			'abilities-v2.php must reference canonical NotificationPref at App\\Services\\NotificationPref'
		);

		$this->assertStringNotContainsString(
			'App\\Models\\NotificationPref',
			$src,
			'abilities-v2.php must not reference drifted namespace App\\Models\\NotificationPref'
		);
	}

	// ── Source-level: canonical Course model namespace ────────────────────────

	public function test_create_course_uses_canonical_course_model() {
		$file = dirname( __DIR__, 3 ) . '/includes/community/abilities.php';
		$src  = file_get_contents( $file );

		$this->assertStringContainsString(
			'FluentCommunity\\Modules\\Course\\Model\\Course::create',
			$src,
			'create-course must use canonical Course model, not Space::create'
		);
	}

	public function test_create_course_does_not_pass_explicit_type_to_course_model() {
		$file = dirname( __DIR__, 3 ) . '/includes/community/abilities.php';
		$src  = file_get_contents( $file );

		// Find the Course::create( block and verify 'type' is not passed.
		$pos = strpos( $src, 'Course::create(' );
		$this->assertNotFalse( $pos, 'Course::create() call must exist' );
		$snippet = substr( $src, $pos, 300 );
		$this->assertStringNotContainsString(
			"'type' =>",
			$snippet,
			'create-course must not pass explicit type => value to Course::create() — model static $type handles it'
		);
	}

	// ── Source-level: F-COM-01 cascade reads use canonical Course model ──────

	public function test_course_cascade_reads_do_not_use_space_type_course() {
		// The Space model's BaseSpace::boot global scope forces type='community',
		// so Space::where('type','course') is always empty. All course reads
		// must go through the canonical Course model.
		$file = dirname( __DIR__, 3 ) . '/includes/community/abilities.php';
		$src  = file_get_contents( $file );

		$this->assertStringNotContainsString(
			"\\FluentCommunity\\App\\Models\\Space::where( 'type', 'course' )",
			$src,
			'No course read may use Space::where(type,course) — the Space global scope forces type=community (F-COM-01 cascade)'
		);

		$this->assertStringContainsString(
			"\\FluentCommunity\\Modules\\Course\\Model\\Course::where( 'type', 'course' )",
			$src,
			'Course reads must use canonical Course model (F-COM-01 cascade fix)'
		);
	}

	// ── Source-level: update-customization-settings is non-destructive ───────

	public function test_update_customization_settings_merges_over_current() {
		// Vendor Utility::updateCustomizationSettings() is a full replace
		// (Arr::only + updateOption). The registrar must read current settings
		// and merge incoming over them so a partial update is non-destructive.
		$file = dirname( __DIR__, 3 ) . '/includes/community/abilities-v2.php';
		$src  = file_get_contents( $file );

		$pos = strpos( $src, "'fluent-community/update-customization-settings'" );
		$this->assertNotFalse( $pos, 'update-customization-settings registration must exist' );
		$block = substr( $src, $pos, 3400 );

		$this->assertStringContainsString(
			'Utility::getCustomizationSettings()',
			$block,
			'update-customization-settings must read current settings before writing (merge, not full replace)'
		);
		$this->assertStringContainsString(
			'array_merge( $current, $sanitized )',
			$block,
			'update-customization-settings must merge sanitized incoming over current persisted settings'
		);
		$this->assertStringContainsString(
			'updateCustomizationSettings( $merged )',
			$block,
			'the merged (not partial) array must be forwarded to the vendor full-replace method'
		);

		// V10/V11(d): the merge depends on BOTH vendor methods. The guard must
		// check getCustomizationSettings too, and the read-current call must be
		// inside the try so an absent/throwing helper returns a typed WP_Error
		// rather than a PHP fatal before the guarded write.
		$this->assertStringContainsString(
			"method_exists( '\\\\FluentCommunity\\\\App\\\\Functions\\\\Utility', 'getCustomizationSettings' )",
			$block,
			'guard must also assert getCustomizationSettings (read-current dependency) before vendor use'
		);
		$try_pos = strpos( $block, 'try {' );
		$get_pos = strpos( $block, 'Utility::getCustomizationSettings()' );
		$this->assertNotFalse( $try_pos );
		$this->assertNotFalse( $get_pos );
		$this->assertGreaterThan(
			$try_pos,
			$get_pos,
			'getCustomizationSettings() read-current must be inside the try block (typed-error, not fatal)'
		);
	}

	// ── Absent-vendor guard: get-customization-settings ──────────────────────
	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_customization_settings_returns_wp_error_when_utility_absent() {
		ini_set( 'log_errors', '0' );
		ini_set( 'error_log', '/dev/null' );

		require_once dirname( __DIR__, 3 ) . '/includes/community/abilities-v2.php';

		global $_wp_registered_abilities;
		$_wp_registered_abilities = array();
		update_option( 'fluent_abilities_enabled_modules', array( 'community' ) );
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'fluent_community_admin' );

		$this->assertFalse(
			class_exists( 'FluentCommunity\\App\\Functions\\Utility', false ),
			'precondition: Utility class must not exist in this process'
		);

		fluent_abilities_register_community_v2();
		$abilities = wp_get_abilities();

		$cb     = $abilities['fluent-community/get-customization-settings']['execute_callback'];
		$result = $cb( array() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'vendor_helper_unavailable', $result->get_error_code() );
	}

	// ── Absent-vendor guard: update-customization-settings ───────────────────
	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_update_customization_settings_returns_wp_error_when_utility_absent() {
		ini_set( 'log_errors', '0' );
		ini_set( 'error_log', '/dev/null' );

		require_once dirname( __DIR__, 3 ) . '/includes/community/abilities-v2.php';

		global $_wp_registered_abilities;
		$_wp_registered_abilities = array();
		update_option( 'fluent_abilities_enabled_modules', array( 'community' ) );
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'fluent_community_admin' );

		fluent_abilities_register_community_v2();
		$abilities = wp_get_abilities();

		$cb     = $abilities['fluent-community/update-customization-settings']['execute_callback'];
		$result = $cb( array( 'settings' => array( 'dark_mode' => 'yes' ) ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'vendor_helper_unavailable', $result->get_error_code() );
	}

	// ── Absent-vendor guard: get-privacy-settings ────────────────────────────
	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_privacy_settings_returns_wp_error_when_utility_absent() {
		ini_set( 'log_errors', '0' );
		ini_set( 'error_log', '/dev/null' );

		require_once dirname( __DIR__, 3 ) . '/includes/community/abilities-v2.php';

		global $_wp_registered_abilities;
		$_wp_registered_abilities = array();
		update_option( 'fluent_abilities_enabled_modules', array( 'community' ) );
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'fluent_community_admin' );

		fluent_abilities_register_community_v2();
		$abilities = wp_get_abilities();

		$cb     = $abilities['fluent-community/get-privacy-settings']['execute_callback'];
		$result = $cb( array() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'vendor_helper_unavailable', $result->get_error_code() );
	}

	// ── Absent-vendor guard: get-notification-prefs ──────────────────────────
	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_notification_prefs_returns_wp_error_when_notificationpref_absent() {
		ini_set( 'log_errors', '0' );
		ini_set( 'error_log', '/dev/null' );

		require_once dirname( __DIR__, 3 ) . '/includes/community/abilities-v2.php';

		global $_wp_registered_abilities;
		$_wp_registered_abilities = array();
		update_option( 'fluent_abilities_enabled_modules', array( 'community' ) );
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'fluent_community_admin' );

		$this->assertFalse(
			class_exists( 'FluentCommunity\\App\\Services\\NotificationPref', false ),
			'precondition: NotificationPref class must not exist in this process'
		);

		fluent_abilities_register_community_v2();
		$abilities = wp_get_abilities();

		$cb     = $abilities['fluent-community/get-notification-prefs']['execute_callback'];
		$result = $cb( array() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'vendor_helper_unavailable', $result->get_error_code() );
	}

	// ── Absent-vendor guard: update-notification-prefs ───────────────────────
	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_update_notification_prefs_returns_wp_error_when_notificationpref_absent() {
		ini_set( 'log_errors', '0' );
		ini_set( 'error_log', '/dev/null' );

		require_once dirname( __DIR__, 3 ) . '/includes/community/abilities-v2.php';

		global $_wp_registered_abilities;
		$_wp_registered_abilities = array();
		update_option( 'fluent_abilities_enabled_modules', array( 'community' ) );
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'fluent_community_admin' );

		fluent_abilities_register_community_v2();
		$abilities = wp_get_abilities();

		$cb     = $abilities['fluent-community/update-notification-prefs']['execute_callback'];
		$result = $cb( array( 'prefs' => array( 'mention_mail' => 'yes' ) ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'vendor_helper_unavailable', $result->get_error_code() );
	}

	// ── Absent-vendor guard: create-course ───────────────────────────────────
	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_create_course_returns_wp_error_when_course_model_absent() {
		ini_set( 'log_errors', '0' );
		ini_set( 'error_log', '/dev/null' );

		require_once dirname( __DIR__, 3 ) . '/includes/community/abilities.php';

		global $_wp_registered_abilities;
		$_wp_registered_abilities = array();
		update_option( 'fluent_abilities_enabled_modules', array( 'community' ) );
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'fluent_community_admin', 'manage_options' );

		$this->assertFalse(
			class_exists( 'FluentCommunity\\Modules\\Course\\Model\\Course', false ),
			'precondition: Course model must not exist in this process'
		);

		do_action( 'wp_abilities_api_init' );

		$abilities = wp_get_abilities();
		$cb        = $abilities['fluent-community/create-course']['execute_callback'];
		$result    = $cb( array( 'title' => 'Test Course' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'vendor_helper_unavailable', $result->get_error_code() );
	}

	// ── Absent-vendor guard: update-course ───────────────────────────────────
	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_update_course_returns_wp_error_when_course_model_absent() {
		ini_set( 'log_errors', '0' );
		ini_set( 'error_log', '/dev/null' );

		require_once dirname( __DIR__, 3 ) . '/includes/community/abilities.php';

		global $_wp_registered_abilities;
		$_wp_registered_abilities = array();
		update_option( 'fluent_abilities_enabled_modules', array( 'community' ) );
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'fluent_community_admin', 'manage_options' );

		do_action( 'wp_abilities_api_init' );

		$abilities = wp_get_abilities();
		$cb        = $abilities['fluent-community/update-course']['execute_callback'];
		$result    = $cb( array( 'course_id' => 1 ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'vendor_helper_unavailable', $result->get_error_code() );
	}
}
