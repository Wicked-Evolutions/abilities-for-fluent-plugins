<?php
/**
 * Verifies the permission_callback for every Fluent Forms ability rejects
 * unauthorized users.
 *
 * @package Fluent_Abilities\Tests\Unit\Forms
 */

require_once __DIR__ . '/FormsAbilitiesTestCase.php';

class FluentFormsPermissionCallbackTest extends FormsAbilitiesTestCase {

	/**
	 * Required cap per ability — derived from the registrar wrapper
	 * (level = op_type by default; delete-tier abilities in this module
	 * override to 'write' until 'fluent_forms_delete' is added to security.php).
	 *
	 * @return array<string, string>
	 */
	private function ability_required_caps() {
		$out = array();
		$expected = $this->expected_abilities_with_levels();
		foreach ( $expected as $slug => $level ) {
			$out[ $slug ] = 'fluent_' . 'forms_' . $level;
		}
		return $out;
	}

	private function expected_abilities_with_levels() {
		// All read abilities require fluent_forms_read.
		// All write abilities require fluent_forms_write.
		// Delete abilities use the 'write' level override (Forms security model
		// doesn't ship fluent_forms_delete in v1.1.3; the cap addition is
		// requested as a scaffold-owned edit in the PR body).
		$abilities = wp_get_abilities();
		$out = array();
		foreach ( $abilities as $slug => $ability ) {
			if ( ( $ability['category'] ?? '' ) !== 'fluent-forms' ) {
				continue;
			}
			$annotations = $ability['meta']['annotations'] ?? array();
			$permission  = $annotations['permission'] ?? 'read';
			// Delete annotations map to the 'write' level today.
			$level = 'delete' === $permission ? 'write' : $permission;
			$out[ $slug ] = $level;
		}
		return $out;
	}

	public function test_anonymous_user_is_rejected_for_every_ability() {
		$GLOBALS['_test_current_user_id'] = 0;
		$GLOBALS['_test_user_caps']       = array();

		foreach ( $this->ability_required_caps() as $slug => $cap ) {
			$result = $this->invoke_permission_callback( $slug );
			$this->assertFalse( $result, "Anonymous request to {$slug} must be rejected by permission_callback." );
		}
	}

	public function test_user_with_only_read_cap_is_rejected_from_writes() {
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'fluent_forms_read' );

		$writes_rejected = 0;
		$reads_allowed   = 0;
		foreach ( $this->ability_required_caps() as $slug => $cap ) {
			$result = $this->invoke_permission_callback( $slug );
			if ( 'fluent_forms_read' === $cap ) {
				$this->assertTrue( $result, "Read ability {$slug} must pass permission_callback with fluent_forms_read." );
				$reads_allowed++;
			} else {
				$this->assertFalse( $result, "Write/delete ability {$slug} must be rejected without fluent_forms_write." );
				$writes_rejected++;
			}
		}
		$this->assertGreaterThan( 0, $reads_allowed );
		$this->assertGreaterThan( 0, $writes_rejected );
	}

	public function test_user_with_read_and_write_caps_passes_every_ability() {
		$GLOBALS['_test_current_user_id'] = 1;
		$GLOBALS['_test_user_caps']       = array( 'fluent_forms_read', 'fluent_forms_write' );

		foreach ( $this->ability_required_caps() as $slug => $cap ) {
			$result = $this->invoke_permission_callback( $slug );
			$this->assertTrue( $result, "Authorized user with read+write must pass permission_callback for {$slug}." );
		}
	}
}
