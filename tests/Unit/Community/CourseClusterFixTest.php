<?php
/**
 * Unit tests — v1.4.0 — FluentCommunity course sub-cluster (#124).
 *
 * Sibling to the Package 3 course-model fixes (P3NamespaceFixesTest): that pass
 * fixed the COURSE layer (create/update-course → canonical Course model). This
 * covers the LESSON layer that shares the same root:
 *
 *   create-lesson  — persisted a Feed with type=lesson, which the CourseLesson
 *                    type scope (type=course_lesson) can never read back, so it
 *                    returned success + a phantom id. Now persists through the
 *                    canonical CourseLesson model and verifies read-back.
 *   get-course-progress — counted lessons via CourseLesson::where('course_id'),
 *                    a column that does not exist on fcom_posts, leaking raw SQL
 *                    to the client. Now keys on space_id and guards the DB block.
 *
 * Source-level tests verify the correct class/column paths. Absent-vendor tests
 * run in separate processes so no real Fluent vendor class is loaded — a correct
 * guard MUST return a typed WP_Error, never fatal.
 *
 * @package Fluent_Abilities\Tests\Unit\Community
 */

use PHPUnit\Framework\TestCase;

class CourseClusterFixTest extends TestCase {

	/**
	 * Return the source of the create-lesson registration block only (from its
	 * slug to the next $reg-> call), so assertions cannot be satisfied by an
	 * unrelated ability.
	 */
	private function abilityBlock( $slug ) {
		$src   = file_get_contents( dirname( __DIR__, 3 ) . '/includes/community/abilities.php' );
		$start = strpos( $src, "'{$slug}'" );
		$this->assertNotFalse( $start, "{$slug} registration not found" );
		$end = strpos( $src, '$reg->', $start + 10 );
		return false === $end ? substr( $src, $start ) : substr( $src, $start, $end - $start );
	}

	// ── Source-level: create-lesson persists via canonical CourseLesson ───────

	public function test_create_lesson_persists_via_canonical_course_lesson_model() {
		$block = $this->abilityBlock( 'fluent-community/create-lesson' );

		$this->assertStringContainsString(
			'\\FluentCommunity\\Modules\\Course\\Model\\CourseLesson::create(',
			$block,
			'create-lesson must persist through the canonical CourseLesson model'
		);
		$this->assertStringNotContainsString(
			'Feed::create',
			$block,
			'create-lesson must not write the lesson as a Feed (type=lesson is invisible to the CourseLesson scope — phantom-id bug)'
		);
		$this->assertStringNotContainsString(
			"'lesson'",
			$block,
			"create-lesson must not set type='lesson'; the CourseLesson creating-hook forces type=course_lesson"
		);
	}

	public function test_create_lesson_verifies_read_back_before_success() {
		$block = $this->abilityBlock( 'fluent-community/create-lesson' );
		$this->assertStringContainsString(
			'fluent_community_lesson_not_persisted',
			$block,
			'create-lesson must return a typed WP_Error when the lesson does not read back, never success-with-phantom-id'
		);
	}

	// ── Source-level: get-course-progress keys on space_id, not course_id ─────

	public function test_get_course_progress_uses_space_id_not_course_id() {
		$block = $this->abilityBlock( 'fluent-community/get-course-progress' );

		$this->assertStringNotContainsString(
			"where( 'course_id'",
			$block,
			"get-course-progress must not query CourseLesson by course_id (no such column on fcom_posts — raw SQL leak #124)"
		);
		$this->assertStringContainsString(
			"where( 'space_id'",
			$block,
			'get-course-progress must key lessons on space_id (the real CourseLesson course key)'
		);
		$this->assertStringNotContainsString(
			"whereHas( 'post'",
			$block,
			'get-course-progress must not use Reaction->post() — no such relationship exists on the Reaction model'
		);
	}

	// ── Absent-vendor guard: create-lesson ────────────────────────────────────
	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_create_lesson_returns_wp_error_when_course_lesson_model_absent() {
		ini_set( 'log_errors', '0' );
		ini_set( 'error_log', '/dev/null' );

		require_once dirname( __DIR__, 3 ) . '/includes/community/abilities.php';

		global $_wp_registered_abilities;
		$_wp_registered_abilities = array();
		update_option( 'fluent_abilities_enabled_modules', array( 'community' ) );
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'fluent_community_admin', 'manage_options' );

		$this->assertFalse(
			class_exists( 'FluentCommunity\\Modules\\Course\\Model\\CourseLesson', false ),
			'precondition: CourseLesson model must not exist in this process'
		);

		do_action( 'wp_abilities_api_init' );

		$abilities = wp_get_abilities();
		$cb        = $abilities['fluent-community/create-lesson']['execute_callback'];
		$result    = $cb( array( 'course_id' => 1, 'title' => 'Test Lesson' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'vendor_helper_unavailable', $result->get_error_code() );
	}

	// ── Absent-vendor guard: get-course-progress ──────────────────────────────
	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_course_progress_returns_wp_error_when_course_lesson_model_absent() {
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
		$cb        = $abilities['fluent-community/get-course-progress']['execute_callback'];
		$result    = $cb( array( 'course_id' => 1 ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'vendor_helper_unavailable', $result->get_error_code() );
	}

	// ── Same-root siblings (#124 extension): list/get/delete-lesson ────────────

	// Source-level: canonical model + space_id; no wrong App\Models namespace.

	public function test_list_lessons_uses_canonical_model_and_space_id() {
		$block = $this->abilityBlock( 'fluent-community/list-lessons' );
		$this->assertStringContainsString(
			"\\FluentCommunity\\Modules\\Course\\Model\\CourseLesson::where( 'space_id'",
			$block,
			'list-lessons must query CourseLesson by space_id'
		);
		$this->assertStringNotContainsString( 'App\\Models\\CourseLesson', $block, 'list-lessons must not reference the non-existent App\\Models\\CourseLesson' );
		$this->assertStringNotContainsString( "where( 'course_id'", $block, 'list-lessons must not query by course_id' );
		$this->assertStringNotContainsString( "where( 'type', 'lesson' )", $block, 'list-lessons must not fall back to Feed type=lesson' );
	}

	public function test_get_lesson_uses_canonical_model() {
		$block = $this->abilityBlock( 'fluent-community/get-lesson' );
		$this->assertStringContainsString(
			"\\FluentCommunity\\Modules\\Course\\Model\\CourseLesson::find(",
			$block,
			'get-lesson must load via the canonical CourseLesson model'
		);
		$this->assertStringNotContainsString( 'App\\Models\\CourseLesson', $block, 'get-lesson must not reference the non-existent App\\Models\\CourseLesson' );
		$this->assertStringNotContainsString( "where( 'type', 'lesson' )", $block, 'get-lesson must not fall back to Feed type=lesson' );
	}

	public function test_delete_lesson_uses_canonical_model_and_confirms_gone() {
		$block = $this->abilityBlock( 'fluent-community/delete-lesson' );
		$this->assertStringContainsString(
			"\\FluentCommunity\\Modules\\Course\\Model\\CourseLesson::find(",
			$block,
			'delete-lesson must load via the canonical CourseLesson model'
		);
		$this->assertStringNotContainsString( 'App\\Models\\CourseLesson', $block, 'delete-lesson must not reference the non-existent App\\Models\\CourseLesson (it hard-failed for everyone)' );
		$this->assertStringContainsString( 'fluent_community_lesson_not_deleted', $block, 'delete-lesson must confirm read-back-gone before reporting success' );
	}

	public function test_no_wrong_namespace_courselesson_anywhere_in_module() {
		$src = file_get_contents( dirname( __DIR__, 3 ) . '/includes/community/abilities.php' );
		$this->assertStringNotContainsString(
			'App\\Models\\CourseLesson',
			$src,
			'No community ability may reference the non-existent \\FluentCommunity\\App\\Models\\CourseLesson namespace; the canonical model is \\FluentCommunity\\Modules\\Course\\Model\\CourseLesson'
		);
	}

	// Absent-vendor: each sibling returns a typed WP_Error, never a fatal.

	private function bootCommunityAbilities() {
		ini_set( 'log_errors', '0' );
		ini_set( 'error_log', '/dev/null' );

		require_once dirname( __DIR__, 3 ) . '/includes/community/abilities.php';

		global $_wp_registered_abilities;
		$_wp_registered_abilities = array();
		update_option( 'fluent_abilities_enabled_modules', array( 'community' ) );
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'fluent_community_admin', 'manage_options' );

		do_action( 'wp_abilities_api_init' );

		return wp_get_abilities();
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_list_lessons_returns_wp_error_when_course_lesson_model_absent() {
		$abilities = $this->bootCommunityAbilities();
		$result    = $abilities['fluent-community/list-lessons']['execute_callback']( array( 'course_id' => 1 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'vendor_helper_unavailable', $result->get_error_code() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_lesson_returns_wp_error_when_course_lesson_model_absent() {
		$abilities = $this->bootCommunityAbilities();
		$result    = $abilities['fluent-community/get-lesson']['execute_callback']( array( 'id' => 1 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'vendor_helper_unavailable', $result->get_error_code() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_delete_lesson_returns_wp_error_when_course_lesson_model_absent() {
		$abilities = $this->bootCommunityAbilities();
		$result    = $abilities['fluent-community/delete-lesson']['execute_callback']( array( 'id' => 1 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'vendor_helper_unavailable', $result->get_error_code() );
	}

	// Runtime: delete-lesson really deletes and confirms the row is gone.
	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_delete_lesson_really_deletes_and_confirms_gone() {
		require_once __DIR__ . '/fakes/CourseLessonFake.php';
		\FluentCommunity\Modules\Course\Model\CourseLesson::$rows = array(
			5 => array( 'id' => 5, 'title' => 'Doomed Lesson', 'space_id' => 1, 'status' => 'published' ),
		);

		$abilities = $this->bootCommunityAbilities();
		$cb        = $abilities['fluent-community/delete-lesson']['execute_callback'];

		// Real delete.
		$result = $cb( array( 'id' => 5 ) );
		$this->assertIsArray( $result, 'delete-lesson should return a success array for an existing lesson' );
		$this->assertTrue( $result['success'] );
		$this->assertSame( 5, $result['id'] );

		// Read-back-gone.
		$this->assertArrayNotHasKey(
			5,
			\FluentCommunity\Modules\Course\Model\CourseLesson::$rows,
			'delete-lesson must actually remove the lesson row'
		);

		// Second delete must report not_found — never a phantom success.
		$again = $cb( array( 'id' => 5 ) );
		$this->assertInstanceOf( WP_Error::class, $again );
		$this->assertSame( 'not_found', $again->get_error_code() );
	}
}
