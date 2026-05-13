<?php
/**
 * Unit Tests — Community v2.0 Abilities (clusters 4.1-4.10, 4.12-4.15)
 *
 * Shape + permission tests for the 53 new abilities under fluent-community.
 * Execution-side tests run via Mode C (mcp-adapter-execute-ability) live
 * verification on the probe site; this suite covers registration + auth gates.
 *
 * @package Fluent_Abilities\Tests\Unit\Community
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 3 ) . '/includes/community/abilities-v2.php';

class CommunityV2AbilitiesTest extends TestCase {

	/**
	 * slug => op_type (read/write/delete).
	 */
	private const SLUGS = array(
		// 4.1 Space membership & roles (6)
		'fluent-community/add-space-member'              => 'write',
		'fluent-community/remove-space-member'           => 'delete',
		'fluent-community/update-space-member-role'      => 'write',
		'fluent-community/get-space-member'              => 'read',
		'fluent-community/bulk-add-space-members'        => 'write',
		'fluent-community/bulk-remove-space-members'     => 'delete',

		// 4.2 Community-level member CRUD (3)
		'fluent-community/create-member'                 => 'write',
		'fluent-community/update-member-status'          => 'write',
		'fluent-community/delete-member'                 => 'delete',

		// 4.3 Space-group CRUD (3)
		'fluent-community/create-space-group'            => 'write',
		'fluent-community/update-space-group'            => 'write',
		'fluent-community/delete-space-group'            => 'delete',

		// 4.4 Reactions (3)
		'fluent-community/add-reaction'                  => 'write',
		'fluent-community/remove-reaction'               => 'delete',
		'fluent-community/list-reactions'                => 'read',

		// 4.5 Notification mutations (4)
		'fluent-community/mark-notification-read'        => 'write',
		'fluent-community/mark-all-notifications-read'   => 'write',
		'fluent-community/mark-feed-notifications-read'  => 'write',
		'fluent-community/list-unread-notifications'     => 'read',

		// 4.6 Settings (12 — 6 get/update pairs)
		'fluent-community/get-features-settings'         => 'read',
		'fluent-community/update-features-settings'      => 'write',
		'fluent-community/get-menu-settings'             => 'read',
		'fluent-community/update-menu-settings'          => 'write',
		'fluent-community/get-customization-settings'    => 'read',
		'fluent-community/update-customization-settings' => 'write',
		'fluent-community/get-privacy-settings'          => 'read',
		'fluent-community/update-privacy-settings'       => 'write',
		'fluent-community/get-crm-tagging-config'        => 'read',
		'fluent-community/update-crm-tagging-config'     => 'write',
		'fluent-community/get-notification-prefs'        => 'read',
		'fluent-community/update-notification-prefs'     => 'write',

		// 4.7 XProfile custom-field user values (2)
		'fluent-community/get-profile-custom-fields'     => 'read',
		'fluent-community/update-profile-custom-fields'  => 'write',

		// 4.8 Course enrollment (4)
		'fluent-community/list-course-students'          => 'read',
		'fluent-community/enroll-user-in-course'         => 'write',
		'fluent-community/unenroll-user-from-course'     => 'delete',
		'fluent-community/get-course-enrollment'         => 'read',

		// 4.9 Following predicate (1, Pro)
		'fluent-community/check-is-following'            => 'read',

		// 4.10 Cross-plugin event emission (1)
		'fluent-community/emit-event'                    => 'write',

		// 4.12 Topics/Terms (7)
		'fluent-community/list-topics'                   => 'read',
		'fluent-community/get-topic'                     => 'read',
		'fluent-community/create-topic'                  => 'write',
		'fluent-community/update-topic'                  => 'write',
		'fluent-community/delete-topic'                  => 'delete',
		'fluent-community/sync-space-topics'             => 'write',
		'fluent-community/sync-feed-topics'              => 'write',

		// 4.13 Surveys/polls (3)
		'fluent-community/cast-survey-vote'              => 'write',
		'fluent-community/get-survey-results'            => 'read',
		'fluent-community/get-survey-voters'             => 'read',

		// 4.14 Quiz Pro (3)
		'fluent-community/list-quiz-attempts'            => 'read',
		'fluent-community/submit-quiz-attempt'           => 'write',
		'fluent-community/get-quiz-results'              => 'read',

		// 4.15 Mention search (1)
		'fluent-community/search-members-mention'        => 'read',
	);

	/**
	 * slug => level override (only abilities with 'level' => 'admin').
	 * Default = op_type (no override).
	 */
	private const LEVELS = array(
		// 4.1 admin overrides (5 of 6 — get-space-member uses default read)
		'fluent-community/add-space-member'              => 'admin',
		'fluent-community/remove-space-member'           => 'admin',
		'fluent-community/update-space-member-role'      => 'admin',
		'fluent-community/bulk-add-space-members'        => 'admin',
		'fluent-community/bulk-remove-space-members'     => 'admin',

		// 4.2 all three admin
		'fluent-community/create-member'                 => 'admin',
		'fluent-community/update-member-status'          => 'admin',
		'fluent-community/delete-member'                 => 'admin',

		// 4.3 all three admin
		'fluent-community/create-space-group'            => 'admin',
		'fluent-community/update-space-group'            => 'admin',
		'fluent-community/delete-space-group'            => 'admin',

		// 4.6 — all admin EXCEPT 4.6.11/4.6.12 (notification-prefs)
		'fluent-community/get-features-settings'         => 'admin',
		'fluent-community/update-features-settings'     => 'admin',
		'fluent-community/get-menu-settings'             => 'admin',
		'fluent-community/update-menu-settings'          => 'admin',
		'fluent-community/get-customization-settings'    => 'admin',
		'fluent-community/update-customization-settings' => 'admin',
		'fluent-community/get-privacy-settings'          => 'admin',
		'fluent-community/update-privacy-settings'       => 'admin',
		'fluent-community/get-crm-tagging-config'        => 'admin',
		'fluent-community/update-crm-tagging-config'     => 'admin',

		// 4.8 all four admin
		'fluent-community/list-course-students'          => 'admin',
		'fluent-community/enroll-user-in-course'         => 'admin',
		'fluent-community/unenroll-user-from-course'     => 'admin',
		'fluent-community/get-course-enrollment'         => 'admin',

		// 4.10 emit-event admin
		'fluent-community/emit-event'                    => 'admin',

		// 4.12 write/delete/sync admin (list/get use default read)
		'fluent-community/create-topic'                  => 'admin',
		'fluent-community/update-topic'                  => 'admin',
		'fluent-community/delete-topic'                  => 'admin',
		'fluent-community/sync-space-topics'             => 'admin',
		'fluent-community/sync-feed-topics'              => 'admin',

		// 4.14 list/get-results admin (submit uses default write)
		'fluent-community/list-quiz-attempts'            => 'admin',
		'fluent-community/get-quiz-results'              => 'admin',
	);

	protected function setUp(): void {
		global $_wp_registered_abilities, $_wp_options_store;
		$_wp_registered_abilities = array();
		$_wp_options_store        = array();
		$GLOBALS['_test_user_caps']       = null;
		$GLOBALS['_test_current_user_id'] = 1;

		fluent_abilities_register_community_v2();
	}

	// ── Registration shape ───────────────────────────────────────────────────

	public function test_all_53_abilities_register() {
		$abilities = wp_get_abilities();
		$this->assertCount( 53, self::SLUGS, 'SLUGS map must contain exactly 53 entries' );
		foreach ( array_keys( self::SLUGS ) as $slug ) {
			$this->assertArrayHasKey( $slug, $abilities, "missing: $slug" );
		}
	}

	public function test_all_abilities_use_fluent_community_category() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::SLUGS ) as $slug ) {
			$this->assertSame( 'fluent-community', $abilities[ $slug ]['category'], "wrong category on $slug" );
		}
	}

	public function test_annotations_match_op_type() {
		$abilities = wp_get_abilities();
		foreach ( self::SLUGS as $slug => $op ) {
			$ann = $abilities[ $slug ]['meta']['annotations'];
			$this->assertSame( $op, $ann['permission'], "permission annotation drift on $slug" );
			$this->assertSame( $op === 'read', $ann['readonly'], "readonly drift on $slug" );
			$this->assertSame( $op === 'delete', $ann['destructive'], "destructive drift on $slug" );
		}
	}

	public function test_all_abilities_have_input_schema() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::SLUGS ) as $slug ) {
			$this->assertNotEmpty( $abilities[ $slug ]['input_schema'], "missing input_schema on $slug" );
			$this->assertSame( 'object', $abilities[ $slug ]['input_schema']['type'], "input_schema.type != object on $slug" );
		}
	}

	public function test_all_abilities_have_output_schema() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::SLUGS ) as $slug ) {
			$this->assertArrayHasKey( 'output_schema', $abilities[ $slug ], "missing output_schema on $slug" );
		}
	}

	public function test_all_abilities_have_label_and_description() {
		$abilities = wp_get_abilities();
		foreach ( array_keys( self::SLUGS ) as $slug ) {
			$this->assertNotEmpty( $abilities[ $slug ]['label'], "missing label on $slug" );
			$this->assertNotEmpty( $abilities[ $slug ]['description'], "missing description on $slug" );
		}
	}

	// ── Permission_callback denies anonymous ─────────────────────────────────

	public function test_permission_callback_denies_anonymous_for_all() {
		$GLOBALS['_test_current_user_id'] = 0;
		$GLOBALS['_test_user_caps']       = array();

		$abilities = wp_get_abilities();
		foreach ( array_keys( self::SLUGS ) as $slug ) {
			$cb = $abilities[ $slug ]['permission_callback'];
			$this->assertIsCallable( $cb, "permission_callback not callable on $slug" );
			$this->assertFalse( (bool) call_user_func( $cb ), "permission_callback allowed anonymous on $slug" );
		}
	}

	// ── Admin-level abilities deny module-level write/read caps ──────────────

	public function test_admin_level_abilities_deny_fluent_community_write_cap() {
		$abilities = wp_get_abilities();

		$GLOBALS['_test_current_user_id'] = 5;
		$GLOBALS['_test_user_caps']       = array( 'fluent_community_write', 'fluent_community_read', 'fluent_community_delete' );

		foreach ( self::LEVELS as $slug => $level ) {
			if ( $level !== 'admin' ) {
				continue;
			}
			$cb = $abilities[ $slug ]['permission_callback'];
			$this->assertFalse(
				(bool) call_user_func( $cb ),
				"admin-level ability $slug accepted non-admin caps (fluent_community_write/read/delete)"
			);
		}
	}

	public function test_admin_level_abilities_accept_fluent_community_admin_cap() {
		$abilities = wp_get_abilities();

		$GLOBALS['_test_current_user_id'] = 5;
		$GLOBALS['_test_user_caps']       = array( 'fluent_community_admin' );

		foreach ( self::LEVELS as $slug => $level ) {
			if ( $level !== 'admin' ) {
				continue;
			}
			$cb = $abilities[ $slug ]['permission_callback'];
			$this->assertTrue(
				(bool) call_user_func( $cb ),
				"admin-level ability $slug denied fluent_community_admin cap"
			);
		}
	}

	// ── Default-level abilities (op_type == level) accept op_type cap ────────

	public function test_default_level_write_abilities_accept_write_cap() {
		$abilities = wp_get_abilities();
		$GLOBALS['_test_current_user_id'] = 5;
		$GLOBALS['_test_user_caps']       = array( 'fluent_community_write' );

		foreach ( self::SLUGS as $slug => $op ) {
			if ( $op !== 'write' || isset( self::LEVELS[ $slug ] ) ) {
				continue;
			}
			$cb = $abilities[ $slug ]['permission_callback'];
			$this->assertTrue(
				(bool) call_user_func( $cb ),
				"default-write ability $slug denied fluent_community_write cap"
			);
		}
	}

	public function test_default_level_read_abilities_accept_read_cap() {
		$abilities = wp_get_abilities();
		$GLOBALS['_test_current_user_id'] = 5;
		$GLOBALS['_test_user_caps']       = array( 'fluent_community_read' );

		foreach ( self::SLUGS as $slug => $op ) {
			if ( $op !== 'read' || isset( self::LEVELS[ $slug ] ) ) {
				continue;
			}
			$cb = $abilities[ $slug ]['permission_callback'];
			$this->assertTrue(
				(bool) call_user_func( $cb ),
				"default-read ability $slug denied fluent_community_read cap"
			);
		}
	}

	public function test_default_level_delete_abilities_accept_delete_cap() {
		$abilities = wp_get_abilities();
		$GLOBALS['_test_current_user_id'] = 5;
		$GLOBALS['_test_user_caps']       = array( 'fluent_community_delete' );

		foreach ( self::SLUGS as $slug => $op ) {
			if ( $op !== 'delete' || isset( self::LEVELS[ $slug ] ) ) {
				continue;
			}
			$cb = $abilities[ $slug ]['permission_callback'];
			$this->assertTrue(
				(bool) call_user_func( $cb ),
				"default-delete ability $slug denied fluent_community_delete cap"
			);
		}
	}

	// ── Cluster boundary spot checks ─────────────────────────────────────────

	public function test_cluster_4_1_admin_overrides_count() {
		// 5 of 6 abilities use admin level (get-space-member uses default read).
		$admin_count = 0;
		foreach ( self::LEVELS as $slug => $lvl ) {
			if ( $lvl === 'admin' && strpos( $slug, 'fluent-community/' ) === 0 && (
				strpos( $slug, 'space-member' ) !== false || strpos( $slug, 'space-members' ) !== false
			) ) {
				$admin_count++;
			}
		}
		$this->assertSame( 5, $admin_count, 'cluster 4.1 must have 5 admin-level abilities' );
	}

	public function test_notification_prefs_use_default_levels() {
		$this->assertArrayNotHasKey(
			'fluent-community/get-notification-prefs',
			self::LEVELS,
			'4.6.11 must NOT have admin override (callback enforces admin-or-self)'
		);
		$this->assertArrayNotHasKey(
			'fluent-community/update-notification-prefs',
			self::LEVELS,
			'4.6.12 must NOT have admin override (callback enforces admin-or-self)'
		);
	}

	public function test_emit_event_is_admin_level() {
		$this->assertSame( 'admin', self::LEVELS['fluent-community/emit-event'] ?? null );
	}

	public function test_search_members_mention_uses_default_read() {
		$this->assertArrayNotHasKey(
			'fluent-community/search-members-mention',
			self::LEVELS,
			'4.15.1 must use default read level (callback rejects anonymous)'
		);
	}

	public function test_create_topic_is_admin_level_and_not_idempotent() {
		$abilities = wp_get_abilities();
		$this->assertSame( 'admin', self::LEVELS['fluent-community/create-topic'] ?? null );
		$this->assertFalse(
			$abilities['fluent-community/create-topic']['meta']['annotations']['idempotent'],
			'create-topic should not be idempotent'
		);
	}

	public function test_delete_member_is_idempotent() {
		$abilities = wp_get_abilities();
		$this->assertTrue(
			$abilities['fluent-community/delete-member']['meta']['annotations']['idempotent'],
			'delete-member should be idempotent'
		);
	}

	// ── Cluster size invariants ──────────────────────────────────────────────

	public function test_cluster_sizes_match_spec() {
		$count_by_prefix = function( $needles ) {
			$n = 0;
			foreach ( array_keys( self::SLUGS ) as $slug ) {
				foreach ( $needles as $needle ) {
					if ( $slug === 'fluent-community/' . $needle ) {
						$n++;
						break;
					}
				}
			}
			return $n;
		};

		$this->assertSame( 6, $count_by_prefix( array(
			'add-space-member', 'remove-space-member', 'update-space-member-role',
			'get-space-member', 'bulk-add-space-members', 'bulk-remove-space-members',
		) ), 'cluster 4.1 size != 6' );

		$this->assertSame( 3, $count_by_prefix( array( 'create-member', 'update-member-status', 'delete-member' ) ), 'cluster 4.2 size != 3' );

		$this->assertSame( 3, $count_by_prefix( array( 'create-space-group', 'update-space-group', 'delete-space-group' ) ), 'cluster 4.3 size != 3' );

		$this->assertSame( 3, $count_by_prefix( array( 'add-reaction', 'remove-reaction', 'list-reactions' ) ), 'cluster 4.4 size != 3' );

		$this->assertSame( 4, $count_by_prefix( array(
			'mark-notification-read', 'mark-all-notifications-read',
			'mark-feed-notifications-read', 'list-unread-notifications',
		) ), 'cluster 4.5 size != 4' );

		$this->assertSame( 12, $count_by_prefix( array(
			'get-features-settings', 'update-features-settings',
			'get-menu-settings', 'update-menu-settings',
			'get-customization-settings', 'update-customization-settings',
			'get-privacy-settings', 'update-privacy-settings',
			'get-crm-tagging-config', 'update-crm-tagging-config',
			'get-notification-prefs', 'update-notification-prefs',
		) ), 'cluster 4.6 size != 12' );

		$this->assertSame( 2, $count_by_prefix( array( 'get-profile-custom-fields', 'update-profile-custom-fields' ) ), 'cluster 4.7 size != 2' );

		$this->assertSame( 4, $count_by_prefix( array(
			'list-course-students', 'enroll-user-in-course',
			'unenroll-user-from-course', 'get-course-enrollment',
		) ), 'cluster 4.8 size != 4' );

		$this->assertSame( 1, $count_by_prefix( array( 'check-is-following' ) ), 'cluster 4.9 size != 1' );

		$this->assertSame( 1, $count_by_prefix( array( 'emit-event' ) ), 'cluster 4.10 size != 1' );

		$this->assertSame( 7, $count_by_prefix( array(
			'list-topics', 'get-topic', 'create-topic', 'update-topic',
			'delete-topic', 'sync-space-topics', 'sync-feed-topics',
		) ), 'cluster 4.12 size != 7' );

		$this->assertSame( 3, $count_by_prefix( array( 'cast-survey-vote', 'get-survey-results', 'get-survey-voters' ) ), 'cluster 4.13 size != 3' );

		$this->assertSame( 3, $count_by_prefix( array( 'list-quiz-attempts', 'submit-quiz-attempt', 'get-quiz-results' ) ), 'cluster 4.14 size != 3' );

		$this->assertSame( 1, $count_by_prefix( array( 'search-members-mention' ) ), 'cluster 4.15 size != 1' );
	}

	// ── No messaging slugs in this file's surface ────────────────────────────

	public function test_no_messaging_slugs_registered_by_this_file() {
		// CommunityV2 must not register anything under fluent-messaging/*.
		// (MessagingV2 has its own file + test.)
		foreach ( array_keys( self::SLUGS ) as $slug ) {
			$this->assertStringStartsWith( 'fluent-community/', $slug, "non-community slug in CommunityV2 map: $slug" );
		}
	}
}
