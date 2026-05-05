<?php
/**
 * Unit tests for issue #19 — CLI fallback strict deny.
 *
 * Verifies that fluent_abilities_user_can() denies all destructive levels for
 * anonymous WP-CLI invocations, and only authorizes read-level abilities when
 * the FLUENT_ABILITIES_CLI_ALLOW_ANONYMOUS_READ env-var shim is set.
 *
 * @package Fluent_Abilities
 */

use PHPUnit\Framework\TestCase;

final class SecurityCliFallbackTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		// Anonymous CLI: no resolved WP user, WP_CLI active.
		$GLOBALS['_test_current_user_id'] = 0;
		if ( ! defined( 'WP_CLI' ) ) {
			define( 'WP_CLI', true );
		}

		// Enable a couple of modules so module-toggle path is exercised.
		$GLOBALS['_wp_options_store']                                = array();
		$GLOBALS['_wp_options_store']['fluent_abilities_enabled_modules'] = array( 'crm', 'community' );

		// Clean slate for the env-var shim.
		putenv( 'FLUENT_ABILITIES_CLI_ALLOW_ANONYMOUS_READ' );
	}

	protected function tearDown(): void {
		putenv( 'FLUENT_ABILITIES_CLI_ALLOW_ANONYMOUS_READ' );
		unset( $GLOBALS['_test_current_user_id'], $GLOBALS['_test_user_caps'] );
		parent::tearDown();
	}

	// ── No user, no env var → deny every level ────────────────────────────────

	public function test_anonymous_cli_no_env_denies_read() {
		$this->assertFalse( fluent_abilities_user_can( 'crm', 'read' ) );
	}

	public function test_anonymous_cli_no_env_denies_write() {
		$this->assertFalse( fluent_abilities_user_can( 'crm', 'write' ) );
	}

	public function test_anonymous_cli_no_env_denies_delete() {
		$this->assertFalse( fluent_abilities_user_can( 'crm', 'delete' ) );
	}

	public function test_anonymous_cli_no_env_denies_send() {
		$this->assertFalse( fluent_abilities_user_can( 'crm', 'send' ) );
	}

	public function test_anonymous_cli_no_env_denies_admin() {
		$this->assertFalse( fluent_abilities_user_can( 'community', 'admin' ) );
	}

	// ── No user, env-var shim set → read-only authorization for enabled modules ─

	public function test_env_shim_allows_read_for_enabled_module() {
		putenv( 'FLUENT_ABILITIES_CLI_ALLOW_ANONYMOUS_READ=1' );
		$this->assertTrue( fluent_abilities_user_can( 'crm', 'read' ) );
	}

	public function test_env_shim_denies_read_for_disabled_module() {
		putenv( 'FLUENT_ABILITIES_CLI_ALLOW_ANONYMOUS_READ=1' );
		// 'support' is not in the enabled-modules option set up by setUp().
		$this->assertFalse( fluent_abilities_user_can( 'support', 'read' ) );
	}

	public function test_env_shim_denies_write_even_when_enabled() {
		putenv( 'FLUENT_ABILITIES_CLI_ALLOW_ANONYMOUS_READ=1' );
		$this->assertFalse( fluent_abilities_user_can( 'crm', 'write' ) );
	}

	public function test_env_shim_denies_delete_even_when_enabled() {
		putenv( 'FLUENT_ABILITIES_CLI_ALLOW_ANONYMOUS_READ=1' );
		$this->assertFalse( fluent_abilities_user_can( 'crm', 'delete' ) );
	}

	public function test_env_shim_denies_send_even_when_enabled() {
		putenv( 'FLUENT_ABILITIES_CLI_ALLOW_ANONYMOUS_READ=1' );
		$this->assertFalse( fluent_abilities_user_can( 'crm', 'send' ) );
	}

	public function test_env_shim_denies_admin_even_when_enabled() {
		putenv( 'FLUENT_ABILITIES_CLI_ALLOW_ANONYMOUS_READ=1' );
		$this->assertFalse( fluent_abilities_user_can( 'community', 'admin' ) );
	}

	public function test_env_shim_value_other_than_1_does_not_unlock() {
		putenv( 'FLUENT_ABILITIES_CLI_ALLOW_ANONYMOUS_READ=true' );
		$this->assertFalse( fluent_abilities_user_can( 'crm', 'read' ) );
	}

	// ── Authenticated user → existing per-cap behavior, env-var has no effect ──

	public function test_authenticated_user_with_cap_authorizes() {
		$GLOBALS['_test_current_user_id'] = 5;
		$GLOBALS['_test_user_caps']       = array( 'fluent_crm_write' );
		$this->assertTrue( fluent_abilities_user_can( 'crm', 'write' ) );
	}

	public function test_authenticated_user_without_cap_denies() {
		$GLOBALS['_test_current_user_id'] = 5;
		$GLOBALS['_test_user_caps']       = array( 'fluent_crm_read' );
		$this->assertFalse( fluent_abilities_user_can( 'crm', 'write' ) );
	}

	public function test_authenticated_user_unaffected_by_env_shim() {
		putenv( 'FLUENT_ABILITIES_CLI_ALLOW_ANONYMOUS_READ=1' );
		$GLOBALS['_test_current_user_id'] = 5;
		$GLOBALS['_test_user_caps']       = array(); // No caps.
		// Env shim must not grant access to authenticated users without the cap.
		$this->assertFalse( fluent_abilities_user_can( 'crm', 'read' ) );
	}

	// ── fluent_abilities_is_anonymous_cli() helper ────────────────────────────

	public function test_is_anonymous_cli_true_when_no_user_and_wp_cli() {
		$this->assertTrue( fluent_abilities_is_anonymous_cli() );
	}

	public function test_is_anonymous_cli_false_when_user_resolved() {
		$GLOBALS['_test_current_user_id'] = 5;
		$this->assertFalse( fluent_abilities_is_anonymous_cli() );
	}
}
