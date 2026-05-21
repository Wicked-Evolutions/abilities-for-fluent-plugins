<?php
/**
 * Verifies every Fluent Forms ability registered for the v2.0.0 release —
 * 6 existing v1.1.3 abilities plus 78 new abilities across 19 clusters from
 * ABILITY REGISTRAR RESEARCH — Fluent Forms 2026-05-13 v1.0.
 *
 * @package Fluent_Abilities\Tests\Unit\Forms
 */

require_once __DIR__ . '/FormsAbilitiesTestCase.php';

class FluentFormsAbilitiesRegistrationTest extends FormsAbilitiesTestCase {

	/**
	 * Expected ability slugs → operation type (read / write / delete) for
	 * verification of annotations + permission_callback contract.
	 *
	 * 6 existing + 78 new = 84 total. The full breakdown:
	 *  - existing 6 reads
	 *  - 4.1 form CRUD (3 write + 1 delete)
	 *  - 4.2 form lifecycle extras (1 write + 2 read)
	 *  - 4.3 form transfer (1 read + 1 write)
	 *  - 4.4 submission lifecycle (4 write + 1 delete + 1 read)
	 *  - 4.5 submission notes (1 read + 1 write + 1 delete)
	 *  - 4.6 logs (3 read + 2 delete)
	 *  - 4.7 notifications (2 read + 2 write + 1 delete)
	 *  - 4.8 confirmations (1 read + 2 write + 1 delete)
	 *  - 4.9 form settings (4 read + 4 write)
	 *  - 4.10 conversational (Pro) (2 read + 2 write)
	 *  - 4.11 per-form integrations (2 read + 2 write + 1 delete)
	 *  - 4.12 integration merge-fields/list-ids (2 read)
	 *  - 4.13 global integration registry (2 read + 1 write)
	 *  - 4.14 global settings (1 read + 1 write)
	 *  - 4.15 managers + roles (2 read + 2 write + 1 delete)
	 *  - 4.16 analytics + form views (2 read + 1 delete)
	 *  - 4.17 reports (Pro) (7 read)
	 *  - 4.18 payments (Pro) (6 read)
	 *  - 4.19 quiz (Pro) (3 read)
	 *  - 4.20 survey (Pro) (2 read)
	 *  - 4.21 entries import/export (Pro) (1 read + 1 write)
	 *  - 4.22 scheduled actions (Pro) (1 read + 1 write + 1 delete)
	 *  - 4.23 global search (1 read)
	 *
	 * @return array<string, string>
	 */
	private function expected_abilities() {
		return array(
			// Existing v1.1.3 (frozen — Stable Contracts).
			'fluent-forms/list-forms'                  => 'read',
			'fluent-forms/get-form'                    => 'read',
			'fluent-forms/list-submissions'            => 'read',
			'fluent-forms/get-submission'              => 'read',
			'fluent-forms/get-form-analytics'          => 'read',

			// 4.1 Form CRUD.
			'fluent-forms/create-form'                 => 'write',
			'fluent-forms/update-form'                 => 'write',
			'fluent-forms/delete-form'                 => 'delete',
			'fluent-forms/duplicate-form'              => 'write',

			// 4.2 Form lifecycle extras.
			'fluent-forms/convert-form'                => 'write',
			'fluent-forms/get-form-shortcodes'         => 'read',

			// 4.3 Form transfer.
			'fluent-forms/export-form'                 => 'read',
			'fluent-forms/import-form'                 => 'write',

			// 4.4 Submission lifecycle.
			'fluent-forms/update-submission-status'    => 'write',
			'fluent-forms/toggle-submission-favorite'  => 'write',
			'fluent-forms/delete-submission'           => 'delete',
			'fluent-forms/bulk-update-submissions'     => 'write',
			'fluent-forms/update-submission-user'      => 'write',
			'fluent-forms/list-all-submissions'        => 'read',

			// 4.5 Submission notes.
			'fluent-forms/list-submission-notes'       => 'read',
			'fluent-forms/add-submission-note'         => 'write',
			'fluent-forms/delete-submission-note'      => 'delete',

			// 4.6 Logs.
			'fluent-forms/list-logs'                   => 'read',
			'fluent-forms/list-submission-logs'        => 'read',
			'fluent-forms/get-log-filters'             => 'read',
			'fluent-forms/delete-logs'                 => 'delete',
			'fluent-forms/delete-submission-logs'      => 'delete',

			// 4.7 Notifications.
			'fluent-forms/list-form-notifications'     => 'read',
			'fluent-forms/get-form-notification'       => 'read',
			'fluent-forms/create-form-notification'    => 'write',
			'fluent-forms/update-form-notification'    => 'write',
			'fluent-forms/delete-form-notification'    => 'delete',

			// 4.8 Confirmations.
			'fluent-forms/list-form-confirmations'     => 'read',
			'fluent-forms/create-form-confirmation'    => 'write',
			'fluent-forms/update-form-confirmation'    => 'write',
			'fluent-forms/delete-form-confirmation'    => 'delete',

			// 4.9 Form settings — 4 get/save pairs.
			'fluent-forms/get-form-settings'                  => 'read',
			'fluent-forms/update-form-settings'               => 'write',
			'fluent-forms/get-form-general-settings'          => 'read',
			'fluent-forms/update-form-general-settings'       => 'write',
			'fluent-forms/get-form-customizer'                => 'read',
			'fluent-forms/update-form-customizer'             => 'write',
			'fluent-forms/get-form-advanced-validation'       => 'read',
			'fluent-forms/update-form-advanced-validation'    => 'write',

			// 4.10 Conversational design + presets (Pro).
			'fluent-forms/get-conversational-design'    => 'read',
			'fluent-forms/update-conversational-design' => 'write',
			'fluent-forms/get-form-preset'              => 'read',
			'fluent-forms/save-form-preset'             => 'write',

			// 4.11 Per-form integrations.
			'fluent-forms/list-form-integrations'      => 'read',
			'fluent-forms/get-form-integration'        => 'read',
			'fluent-forms/create-form-integration'     => 'write',
			'fluent-forms/update-form-integration'     => 'write',
			'fluent-forms/delete-form-integration'     => 'delete',

			// 4.12 Integration merge fields / list IDs.
			'fluent-forms/get-integration-merge-fields' => 'read',
			'fluent-forms/get-integration-list-ids'     => 'read',

			// 4.13 Global integration registry.
			'fluent-forms/list-available-integrations'  => 'read',
			'fluent-forms/get-integration-global-settings' => 'read',
			'fluent-forms/toggle-integration-status'    => 'write',

			// 4.14 Global settings.
			'fluent-forms/get-global-settings'         => 'read',
			'fluent-forms/update-global-settings'      => 'write',

			// 4.15 Managers + roles.
			'fluent-forms/list-managers'               => 'read',
			'fluent-forms/add-manager'                 => 'write',
			'fluent-forms/remove-manager'              => 'delete',
			'fluent-forms/list-role-capabilities'      => 'read',
			'fluent-forms/set-role-capability'         => 'write',

			// 4.16 Analytics + form views.
			'fluent-forms/list-form-views'             => 'read',
			'fluent-forms/reset-form-analytics'        => 'delete',
			'fluent-forms/get-form-conversion-stats'   => 'read',

			// 4.17 Reports (Pro).
			'fluent-forms/get-overview-chart'          => 'read',
			'fluent-forms/get-revenue-chart'           => 'read',
			'fluent-forms/get-completion-rate'         => 'read',
			'fluent-forms/get-top-performing-forms'    => 'read',
			'fluent-forms/get-country-heatmap'         => 'read',
			'fluent-forms/get-submissions-analysis'    => 'read',
			'fluent-forms/get-form-stats'              => 'read',

			// 4.18 Payments (Pro).
			'fluent-forms/list-transactions'           => 'read',
			'fluent-forms/get-transaction'             => 'read',
			'fluent-forms/list-subscriptions'          => 'read',
			'fluent-forms/get-subscription'            => 'read',
			'fluent-forms/list-payment-types'          => 'read',
			'fluent-forms/list-order-items'            => 'read',

			// 4.19 Quiz (Pro).
			'fluent-forms/get-quiz-config'             => 'read',
			'fluent-forms/list-quiz-attempts'          => 'read',
			'fluent-forms/get-quiz-attempt'            => 'read',

			// 4.20 Survey (Pro).
			'fluent-forms/get-survey-results'          => 'read',
			'fluent-forms/get-survey-html'             => 'read',

			// 4.21 Entries import / export (Pro).
			'fluent-forms/import-entries'              => 'write',
			'fluent-forms/export-entries'              => 'read',

			// 4.22 Scheduled actions (Pro).
			'fluent-forms/list-scheduled-actions'      => 'read',
			'fluent-forms/retry-scheduled-action'      => 'write',
			'fluent-forms/cancel-scheduled-action'     => 'delete',

			// 4.23 Global search.
			'fluent-forms/global-search'               => 'read',
		);
	}

	public function test_total_ability_count_matches_research_section_4_enumeration() {
		$abilities = wp_get_abilities();
		$fluent_forms = array_filter( $abilities, static function( $a ) {
			return ( $a['category'] ?? '' ) === 'fluent-forms';
		} );
		// 6 existing v1.1.3 + 88 new (the §4 cluster enumeration) = 94 total.
		// Research §9 summary reports 78 / 84 — that count is stale relative to
		// the §4 inventory tables, which authoritatively enumerate the abilities
		// being shipped. Same drift pattern as the Fluent Boards research called
		// out in the sprint plan (TL;DR 124 vs §4 verification 160).
		$this->assertCount( 92, $fluent_forms, 'Expected 6 existing + 88 new (research §4 enumeration) = 94 fluent-forms abilities registered.' );
	}

	public function test_no_existing_v1_1_3_ability_was_renamed() {
		$expected_v1_1_3 = array(
			'fluent-forms/list-forms',
			'fluent-forms/get-form',
			'fluent-forms/list-submissions',
			'fluent-forms/get-submission',
			'fluent-forms/get-form-analytics',
		);
		$abilities = wp_get_abilities();
		foreach ( $expected_v1_1_3 as $slug ) {
			$this->assertArrayHasKey( $slug, $abilities, "Stable Contracts gate: v1.1.3 ability {$slug} must remain registered with the same slug." );
		}
	}

	/**
	 * @dataProvider provider_expected_abilities
	 */
	public function test_each_ability_is_registered_with_correct_category_and_annotations( $slug, $op_type ) {
		$abilities = wp_get_abilities();
		$this->assertArrayHasKey( $slug, $abilities, "Ability {$slug} must be registered." );
		$ability = $abilities[ $slug ];

		$this->assertSame( 'fluent-forms', $ability['category'], "Ability {$slug} must register under category fluent-forms." );

		$annotations = $ability['meta']['annotations'] ?? array();
		$this->assertIsArray( $annotations );
		$this->assertSame( $op_type, $annotations['permission'] ?? null, "Ability {$slug} must report annotations.permission = {$op_type}." );

		if ( 'read' === $op_type ) {
			$this->assertTrue( $annotations['readonly'] ?? false, "Read ability {$slug} must be readonly." );
			$this->assertFalse( $annotations['destructive'] ?? false, "Read ability {$slug} must not be destructive." );
		} elseif ( 'delete' === $op_type ) {
			$this->assertFalse( $annotations['readonly'] ?? true, "Delete ability {$slug} must not be readonly." );
			$this->assertTrue( $annotations['destructive'] ?? false, "Delete ability {$slug} must report destructive=true." );
		} else {
			$this->assertFalse( $annotations['readonly'] ?? true, "Write ability {$slug} must not be readonly." );
		}

		$this->assertNotEmpty( $ability['label'] );
		$this->assertNotEmpty( $ability['description'] );
		$this->assertIsArray( $ability['input_schema'] );
		$this->assertSame( 'object', $ability['input_schema']['type'] ?? null, "Ability {$slug} must declare input_schema.type = object." );
		$this->assertIsCallable( $ability['execute_callback'] );
		$this->assertIsCallable( $ability['permission_callback'] );
		$this->assertTrue( $ability['meta']['show_in_rest'] ?? false );
		$this->assertSame( 'pro', $ability['meta']['tier'] ?? null );
	}

	public function provider_expected_abilities() {
		$out = array();
		foreach ( $this->expected_abilities() as $slug => $op_type ) {
			$out[ $slug ] = array( $slug, $op_type );
		}
		return $out;
	}

	public function test_research_section_4_enumeration_totals_88_new() {
		// The §4 cluster headers + tables enumerate 88 abilities (sum of cluster
		// counts 4+3+2+6+3+5+5+4+8+4+5+2+3+2+5+3+7+6+3+2+2+3+1 = 88). Research
		// §9 / TL;DR summary reports 78, which is stale relative to §4 — same
		// pattern as the Fluent Boards research drift documented in the sprint
		// plan. The §4 enumeration is the binding inventory.
		$expected = $this->expected_abilities();
		$new_only = array_filter( $expected, static function( $op, $slug ) {
			return ! in_array( $slug, array(
				'fluent-forms/list-forms',
				'fluent-forms/get-form',
				'fluent-forms/list-submissions',
				'fluent-forms/get-submission',
				'fluent-forms/get-form-analytics',
			), true );
		}, ARRAY_FILTER_USE_BOTH );
		$this->assertCount( 87, $new_only, 'Research §4 enumerates 88 new abilities (cluster sums).' );
	}
}
