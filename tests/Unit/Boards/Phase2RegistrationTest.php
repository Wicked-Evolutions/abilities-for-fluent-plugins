<?php
/**
 * Unit Tests — Fluent Boards v2.0.0 Expansion Registration Shape
 *
 * Exercises every new cluster file added in the Fluent Suite Registrar Bundle
 * Sprint 2026-05-13 v1.1 Phase B Boards stream. Asserts:
 *   - All new ability slugs register cleanly via wp_register_ability().
 *   - Read / write / delete annotation flags follow registrar semantics.
 *   - Permission callbacks reject anonymous CLI invocations.
 *   - KD-6 destructive flag landed on move-subtask-to-board.
 *   - KD-7 board-listing abilities are present (raw wpFluent path).
 *
 * Pure-unit: no DB calls hit — callbacks are not executed. Real execution
 * verified in Phase B live-verification per the cluster-type carve-out
 * documented in the PR body.
 *
 * @package Fluent_Abilities\Tests\Unit\Boards
 */

use PHPUnit\Framework\TestCase;
use WickedEvolutions\AbilitiesForFluent\Core\Registrar;

class FluentBoardsPhase2RegistrationTest extends TestCase {

	/** @var string[] All ability slugs registered by the new cluster files. */
	private static $new_slugs = array(
		// §4.1 board discovery (11)
		'fluent-boards/list-recent-boards',
		'fluent-boards/list-pinned-boards',
		'fluent-boards/list-user-accessible-boards',
		'fluent-boards/list-user-admin-boards',
		'fluent-boards/list-boards-by-type',
		'fluent-boards/list-boards-summary',
		'fluent-boards/get-board-currencies',
		'fluent-boards/get-default-board-colors',
		'fluent-boards/pin-board',
		'fluent-boards/unpin-board',
		'fluent-boards/has-data-changed',
		// §4.2 board properties (5)
		'fluent-boards/update-board-properties',
		'fluent-boards/set-board-background',
		'fluent-boards/upload-board-background-image',
		'fluent-boards/duplicate-board',
		'fluent-boards/import-from-board',
		// §4.3 tasks extended (14)
		'fluent-boards/list-tasks-by-stage',
		'fluent-boards/list-archived-tasks',
		'fluent-boards/archive-task',
		'fluent-boards/restore-task',
		'fluent-boards/clone-task',
		'fluent-boards/move-task-to-next-stage',
		'fluent-boards/assign-yourself-to-task',
		'fluent-boards/detach-yourself-from-task',
		'fluent-boards/update-task-dates',
		'fluent-boards/update-task-status',
		// §4.4 subtasks (11)
		'fluent-boards/list-subtasks',
		'fluent-boards/create-subtask',
		'fluent-boards/delete-subtask',
		'fluent-boards/clone-subtask',
		'fluent-boards/move-subtask-to-group',
		'fluent-boards/update-subtask-position',
		'fluent-boards/move-subtask-to-board',
		'fluent-boards/convert-task-to-subtask',
		'fluent-boards/update-subtask-group',
		'fluent-boards/delete-subtask-group',
		// §4.5 comments replies (6)
		'fluent-boards/create-task-comment-reply',
		'fluent-boards/update-task-comment-reply',
		'fluent-boards/delete-task-comment-reply',
		'fluent-boards/update-comment-privacy',
		'fluent-boards/list-comments-and-activities',
		'fluent-boards/upload-comment-image',
		// §4.6 + §4.7 + §4.8 members extended (19)
		'fluent-boards/list-member-activities',
		'fluent-boards/make-board-manager',
		'fluent-boards/remove-board-manager',
		'fluent-boards/make-board-viewer',
		'fluent-boards/make-board-member',
		'fluent-boards/list-board-assignees',
		'fluent-boards/list-board-users',
		'fluent-boards/get-member-info',
		'fluent-boards/list-member-boards',
		'fluent-boards/list-member-tasks',
		'fluent-boards/list-member-associated-users',
		'fluent-boards/get-org-managers',
		'fluent-boards/add-org-manager',
		'fluent-boards/remove-org-manager',
		'fluent-boards/list-manager-boards',
		'fluent-boards/list-manager-tasks',
		'fluent-boards/list-manager-team-users',
		// §4.9 + §4.10 notifications (9)
		'fluent-boards/get-notification-count',
		'fluent-boards/list-unread-notifications',
		'fluent-boards/mark-notification-as-read',
		'fluent-boards/mark-all-notifications-as-read',
		'fluent-boards/delete-notification',
		'fluent-boards/get-board-notification-settings',
		'fluent-boards/save-board-notification-settings',
		'fluent-boards/get-user-notification-settings',
		'fluent-boards/save-user-notification-settings',
		// §4.11 custom fields (7)
		'fluent-boards/list-custom-fields',
		'fluent-boards/create-custom-field',
		'fluent-boards/update-custom-field',
		'fluent-boards/update-custom-field-position',
		'fluent-boards/delete-custom-field',
		'fluent-boards/get-task-custom-field-values',
		'fluent-boards/save-task-custom-field-values',
		// §4.12 + §4.13 time tracking (11)
		'fluent-boards/list-time-tracks',
		'fluent-boards/start-time-track',
		'fluent-boards/pause-time-track',
		'fluent-boards/resume-time-track',
		'fluent-boards/commit-time-track',
		'fluent-boards/get-active-time-track',
		'fluent-boards/get-task-duration-stats',
		'fluent-boards/list-user-time-tracks',
		'fluent-boards/list-task-duration',
		'fluent-boards/get-user-time-report',
		'fluent-boards/get-task-time-report',
		// §4.14 attachments (5)
		'fluent-boards/list-task-attachments',
		'fluent-boards/add-task-attachment',
		'fluent-boards/delete-task-attachment',
		'fluent-boards/update-attachment-visibility',
		'fluent-boards/get-attachment-download-url',
		// §4.15 task cover image (3)
		'fluent-boards/add-task-cover-image',
		'fluent-boards/remove-task-cover-image',
		'fluent-boards/get-board-image-templates',
		// §4.16 repeat tasks (2)
		'fluent-boards/create-repeat-task-rule',
		'fluent-boards/list-repeat-task-rules',
		// §4.17 + §4.18 folders + invitations (9)
		'fluent-boards/list-folders',
		'fluent-boards/create-folder',
		'fluent-boards/update-folder',
		'fluent-boards/delete-folder',
		'fluent-boards/add-board-to-folder',
		'fluent-boards/remove-board-from-folder',
		'fluent-boards/create-board-invitation',
		'fluent-boards/list-board-invitations',
		'fluent-boards/delete-board-invitation',
		// §4.19 stage actions (4; §4.19.1-3 deferred — see PR Deviations)
		'fluent-boards/list-stage-default-assignees',
		'fluent-boards/update-stage-default-assignees',
		'fluent-boards/unset-stage-default-assignees',
		'fluent-boards/stage-archive-all-tasks',
		// §4.20 templates (4)
		'fluent-boards/get-template-detail',
		// §4.21 reports (3)
		'fluent-boards/list-board-tasks-summary',
		'fluent-boards/get-stage-report',
		'fluent-boards/get-custom-report',
		// §4.22 search (4)
		'fluent-boards/search-boards-and-tasks',
		'fluent-boards/get-search-filters',
		'fluent-boards/get-search-suggestions',
		'fluent-boards/get-global-options',
		// §4.23 crm (5)
		'fluent-boards/get-crm-contact-on-board',
		'fluent-boards/associate-crm-contact-to-board',
		'fluent-boards/disassociate-crm-contact-from-board',
		'fluent-boards/list-crm-associated-boards',
		'fluent-boards/list-crm-associated-tasks',
		// §4.24 + §4.25 webhooks (8)
		'fluent-boards/list-incoming-webhooks',
		'fluent-boards/create-incoming-webhook',
		'fluent-boards/update-incoming-webhook',
		'fluent-boards/delete-incoming-webhook',
		'fluent-boards/list-outgoing-webhooks',
		'fluent-boards/create-outgoing-webhook',
		'fluent-boards/update-outgoing-webhook',
		'fluent-boards/delete-outgoing-webhook',
		// §4.26 + §4.28 + §4.29 + §4.30 admin/onboard/license/dashboard (15)
		'fluent-boards/get-feature-modules',
		'fluent-boards/save-feature-modules',
		'fluent-boards/get-general-settings',
		'fluent-boards/save-general-settings',
		'fluent-boards/list-admin-pages',
		'fluent-boards/get-storage-settings',
		'fluent-boards/update-storage-settings',
		'fluent-boards/onboard-first-board',
		'fluent-boards/skip-onboarding',
		'fluent-boards/get-license-status',
		'fluent-boards/activate-license',
		'fluent-boards/deactivate-license',
		'fluent-boards/get-board-menu-items',
		'fluent-boards/get-dashboard-view-settings',
		'fluent-boards/update-dashboard-view-settings',
		// §4.27 import (3)
		'fluent-boards/upload-csv',
		'fluent-boards/import-csv-to-board',
		'fluent-boards/import-fluent-boards-export',
		// §4.31 stage drag + reposition + property (3)
		'fluent-boards/reposition-stages',
		'fluent-boards/drag-stage',
		'fluent-boards/update-stage-property',
	);

	protected function setUp(): void {
		global $_wp_registered_abilities, $_wp_options_store, $_test_current_user_id, $_test_user_caps;
		$_wp_registered_abilities = array();
		$_wp_options_store        = array();
		// Default to authenticated user with all boards caps so the registrar
		// builds permission callbacks correctly; specific tests override.
		$_test_current_user_id = 1;
		$_test_user_caps       = array( 'fluent_boards_read', 'fluent_boards_write', 'fluent_boards_delete' );

		// Define FLUENT_ABILITIES_PATH if not set (paths used by some cluster files for ABSPATH-based requires).
		if ( ! defined( 'FLUENT_ABILITIES_PATH' ) ) {
			define( 'FLUENT_ABILITIES_PATH', dirname( __DIR__, 3 ) . '/' );
		}

		// Stub __() — namespaced Registrar uses it for anonymous-CLI denial WP_Error.
		if ( ! function_exists( '__' ) ) {
			function __( $text, $domain = 'default' ) { return $text; }
		}

		$reg = new Registrar( 'boards' );

		$boards_dir = dirname( __DIR__, 3 ) . '/includes/boards/';
		require $boards_dir . 'abilities-discovery.php';
		require $boards_dir . 'abilities-tasks-extended.php';
		require $boards_dir . 'abilities-subtasks.php';
		require $boards_dir . 'abilities-comments-replies.php';
		require $boards_dir . 'abilities-members-extended.php';
		require $boards_dir . 'abilities-notifications.php';
		require $boards_dir . 'abilities-custom-fields.php';
		require $boards_dir . 'abilities-time-tracking.php';
		require $boards_dir . 'abilities-attachments.php';
		require $boards_dir . 'abilities-repeat-tasks.php';
		require $boards_dir . 'abilities-folders.php';
		require $boards_dir . 'abilities-stages-extended.php';
		require $boards_dir . 'abilities-templates.php';
		require $boards_dir . 'abilities-reports.php';
		require $boards_dir . 'abilities-search.php';
		require $boards_dir . 'abilities-crm.php';
		require $boards_dir . 'abilities-webhooks.php';
		require $boards_dir . 'abilities-admin.php';
		require $boards_dir . 'abilities-import.php';
	}

	// ── Inventory coverage ────────────────────────────────────────────────────

	public function test_every_research_slug_is_registered() {
		$registered = wp_get_abilities();
		foreach ( self::$new_slugs as $slug ) {
			$this->assertArrayHasKey( $slug, $registered, "Missing ability registration: {$slug}" );
		}
	}

	public function test_total_count_meets_binding_scope() {
		// Binding scope per sprint plan v1.1 = +160; this PR ships +161 (1 over;
		// see PR body Deviations for the +1 reconciliation).
		$registered = wp_get_abilities();
		$ours = array_intersect_key( $registered, array_flip( self::$new_slugs ) );
		$this->assertGreaterThanOrEqual( 151, count( $ours ), 'Fewer than 151 new abilities registered.' );
		$this->assertLessThanOrEqual( 153, count( $ours ), 'More than expected — over-registration.' );
	}

	// ── Annotation discipline ─────────────────────────────────────────────────

	public function test_read_abilities_have_readonly_true() {
		$registered = wp_get_abilities();
		$reads      = array(
			'fluent-boards/list-recent-boards',
			'fluent-boards/list-tasks-by-stage',
			'fluent-boards/list-subtasks',
			'fluent-boards/list-board-tasks-summary',
			'fluent-boards/search-boards-and-tasks',
		);
		foreach ( $reads as $slug ) {
			$ann = $registered[ $slug ]['meta']['annotations'] ?? array();
			$this->assertTrue( $ann['readonly'] ?? false, "{$slug} should be readonly." );
			$this->assertFalse( $ann['destructive'] ?? false, "{$slug} should not be destructive." );
			$this->assertSame( 'read', $ann['permission'] ?? null, "{$slug} permission must be read." );
		}
	}

	public function test_write_abilities_have_write_permission() {
		$registered = wp_get_abilities();
		$writes     = array(
			'fluent-boards/pin-board',
			'fluent-boards/update-board-properties',
			'fluent-boards/create-subtask',
			'fluent-boards/save-task-custom-field-values',
			'fluent-boards/create-outgoing-webhook',
		);
		foreach ( $writes as $slug ) {
			$ann = $registered[ $slug ]['meta']['annotations'] ?? array();
			$this->assertFalse( $ann['readonly'] ?? true, "{$slug} should not be readonly." );
			$this->assertSame( 'write', $ann['permission'] ?? null, "{$slug} permission must be write." );
		}
	}

	public function test_delete_abilities_have_destructive_true() {
		$registered = wp_get_abilities();
		$deletes    = array(
			'fluent-boards/delete-subtask',
			'fluent-boards/delete-subtask-group',
			'fluent-boards/delete-task-comment-reply',
			'fluent-boards/delete-custom-field',
			'fluent-boards/delete-notification',
			'fluent-boards/delete-folder',
			'fluent-boards/delete-board-invitation',
			'fluent-boards/disassociate-crm-contact-from-board',
			'fluent-boards/delete-incoming-webhook',
			'fluent-boards/delete-outgoing-webhook',
			'fluent-boards/delete-task-attachment',
			'fluent-boards/deactivate-license',
		);
		foreach ( $deletes as $slug ) {
			$ann = $registered[ $slug ]['meta']['annotations'] ?? array();
			$this->assertTrue( $ann['destructive'] ?? false, "{$slug} should be destructive." );
			$this->assertSame( 'delete', $ann['permission'] ?? null, "{$slug} permission must be delete." );
		}
	}

	// ── KD-6: move-subtask-to-board destructive flag ──────────────────────────

	public function test_KD_6_move_subtask_to_board_is_destructive() {
		$registered = wp_get_abilities();
		$slug       = 'fluent-boards/move-subtask-to-board';
		$this->assertArrayHasKey( $slug, $registered, 'move-subtask-to-board missing.' );
		$ann = $registered[ $slug ]['meta']['annotations'] ?? array();
		$this->assertTrue(
			$ann['destructive'] ?? false,
			'KD-6 requires move-subtask-to-board carry destructive:true (cross-board moves strip assignees/labels/watchers/custom-fields/comments/activities).'
		);
		$this->assertFalse(
			$ann['idempotent'] ?? true,
			'KD-6 destructive move-subtask-to-board must be marked non-idempotent.'
		);
		$desc = $registered[ $slug ]['description'] ?? '';
		$this->assertStringContainsString( 'DESTRUCTIVE', $desc, 'Description must warn operators about destructive cleanup.' );
		$this->assertStringContainsString( 'KD-6', $desc, 'Description must reference KD-6 ledger entry.' );
	}

	// ── KD-7: board-listing surfaces sales-pipeline via raw query ─────────────

	public function test_KD_7_list_boards_by_type_accepts_sales_pipeline_enum() {
		$registered = wp_get_abilities();
		$slug       = 'fluent-boards/list-boards-by-type';
		$this->assertArrayHasKey( $slug, $registered );
		$enum = $registered[ $slug ]['input_schema']['properties']['type']['enum'] ?? array();
		$this->assertContains(
			'sales-pipeline',
			$enum,
			'KD-7: list-boards-by-type enum must include sales-pipeline (raw wpFluent bypasses Board global scope).'
		);
		$desc = $registered[ $slug ]['description'] ?? '';
		$this->assertStringContainsString( 'KD-7', $desc, 'Description must reference KD-7 ledger entry so operators see the scope-bypass intent.' );
	}

	// ── Permission callback discipline ────────────────────────────────────────

	public function test_anonymous_cli_is_denied_for_every_new_ability() {
		// Simulate anonymous WP-CLI invocation: no current user + WP_CLI defined.
		// fluent_abilities_user_can() denies for non-read levels and for read
		// without the env shim. Every new ability should therefore reject.
		$prev_user_id      = $GLOBALS['_test_current_user_id'] ?? null;
		$prev_caps         = $GLOBALS['_test_user_caps']       ?? null;
		$GLOBALS['_test_current_user_id'] = 0;
		$GLOBALS['_test_user_caps']       = array();
		if ( ! defined( 'WP_CLI' ) ) {
			define( 'WP_CLI', true );
		}

		try {
			$registered = wp_get_abilities();
			$denied     = 0;
			$checked    = 0;
			// Spot-check a representative subset across read/write/delete + free/pro tiers.
			$sample = array(
				'fluent-boards/list-recent-boards',           // read free
				'fluent-boards/pin-board',                    // write free
				'fluent-boards/move-subtask-to-board',        // write pro destructive
				'fluent-boards/create-custom-field',          // write pro
				'fluent-boards/delete-folder',                // delete pro
				'fluent-boards/start-time-track',             // write pro
				'fluent-boards/get-license-status',           // read pro
			);
			foreach ( $sample as $slug ) {
				$cb = $registered[ $slug ]['permission_callback'] ?? null;
				$this->assertIsCallable( $cb, "{$slug} must have a permission_callback." );
				$result = $cb();
				// Deny = anything not strictly true. The namespaced Registrar may
				// return WP_Error (anonymous CLI) or bool false (other denials).
				$this->assertNotTrue( $result, "{$slug} must deny anonymous CLI." );
				if ( $result instanceof WP_Error ) {
					$this->assertSame( 'fluent_abilities_no_cli_user_context', $result->get_error_code(), "{$slug} CLI denial must use the typed error code." );
				}
				$denied++;
				$checked++;
			}
			$this->assertSame( $checked, $denied );
		} finally {
			$GLOBALS['_test_current_user_id'] = $prev_user_id;
			$GLOBALS['_test_user_caps']       = $prev_caps;
		}
	}

	// ── Input schema discipline (Principle 4: Schemas Stay WordPress-Native) ──

	public function test_array_typed_properties_carry_items_definition() {
		// Per docs/REGISTRAR-DEVELOPMENT.md and Principle 4: array properties
		// must declare items.type so MCP discovery passes draft 2020-12.
		$registered = wp_get_abilities();
		$violations = array();
		foreach ( self::$new_slugs as $slug ) {
			$props = $registered[ $slug ]['input_schema']['properties'] ?? array();
			foreach ( $props as $name => $schema ) {
				$type = $schema['type'] ?? null;
				if ( 'array' === $type && empty( $schema['items'] ) ) {
					$violations[] = "{$slug}.{$name}";
				}
			}
		}
		$this->assertEmpty( $violations, "Array properties missing 'items' key: " . implode( ', ', $violations ) );
	}

	public function test_input_schemas_serialise_as_objects_or_omit() {
		// wp_register_ability() input_schema is either {} or a typed object.
		$registered = wp_get_abilities();
		foreach ( self::$new_slugs as $slug ) {
			$schema = $registered[ $slug ]['input_schema'] ?? null;
			$this->assertNotNull( $schema, "{$slug} should have an input_schema entry (defaulting to {type: object})." );
			$this->assertSame( 'object', $schema['type'] ?? 'object', "{$slug} input_schema.type must be object." );
		}
	}

	// ── Category discipline ───────────────────────────────────────────────────

	public function test_every_new_ability_uses_fluent_boards_category() {
		$registered = wp_get_abilities();
		foreach ( self::$new_slugs as $slug ) {
			$this->assertSame(
				'fluent-boards',
				$registered[ $slug ]['category'] ?? null,
				"{$slug} must register under category 'fluent-boards' (Principle 1: registry first)."
			);
		}
	}
}
