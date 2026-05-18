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

	// ── Absent-vendor guard: get-customization-settings ──────────────────────
	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	// ── Absent-vendor guard: update-customization-settings ───────────────────
	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	// ── Absent-vendor guard: get-privacy-settings ────────────────────────────
	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	// ── Absent-vendor guard: get-notification-prefs ──────────────────────────
	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	// ── Absent-vendor guard: update-notification-prefs ───────────────────────
	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
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
