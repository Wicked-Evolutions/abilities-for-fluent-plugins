<?php
/**
 * Base test case for Fluent Forms ability tests.
 *
 * Loads the three Forms ability files (existing + the two new sub-files added
 * in the v2.0.0 sprint) once, captures the registered wp_abilities_api_init
 * callbacks, then replays them in setUp so every test gets a fresh
 * $_wp_registered_abilities populated with the full Forms surface.
 *
 * @package Fluent_Abilities\Tests\Unit\Forms
 */

use PHPUnit\Framework\TestCase;

abstract class FormsAbilitiesTestCase extends TestCase {

	/** @var array Captured wp_abilities_api_init callbacks from the Forms files. */
	private static $forms_init_callbacks = array();

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		if ( ! empty( self::$forms_init_callbacks ) ) {
			return;
		}

		// Capture callbacks the Forms files register on wp_abilities_api_init.
		global $_wp_test_action_callbacks;
		$_wp_test_action_callbacks = array();

		$root = dirname( __DIR__, 3 );
		require_once $root . '/includes/forms/abilities.php';
		require_once $root . '/includes/forms/write-abilities.php';
		require_once $root . '/includes/forms/pro-abilities.php';

		self::$forms_init_callbacks = $_wp_test_action_callbacks['wp_abilities_api_init'] ?? array();
	}

	protected function setUp(): void {
		parent::setUp();

		global $_wp_registered_abilities, $_wp_options_store, $_wp_test_action_callbacks;
		$_wp_registered_abilities  = array();
		$_wp_options_store         = array();
		$_wp_test_action_callbacks = array(
			'wp_abilities_api_init' => self::$forms_init_callbacks,
		);

		$GLOBALS['_test_user_caps'] = array(
			'fluent_forms_read',
			'fluent_forms_write',
			'fluent_forms_delete',
		);
		$GLOBALS['_test_current_user_id'] = 1;

		do_action( 'wp_abilities_api_init' );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['_test_user_caps'] );
		unset( $GLOBALS['_test_current_user_id'] );
		parent::tearDown();
	}

	/**
	 * Helper: invoke an ability's permission_callback.
	 *
	 * @param string $ability_name
	 * @return bool|null Null when the ability is not registered.
	 */
	protected function invoke_permission_callback( $ability_name ) {
		$abilities = wp_get_abilities();
		if ( ! isset( $abilities[ $ability_name ] ) ) {
			return null;
		}
		$cb = $abilities[ $ability_name ]['permission_callback'] ?? null;
		if ( ! is_callable( $cb ) ) {
			return null;
		}
		$result = $cb();
		// src/Core/Registrar::denial_for_anonymous_cli() returns a WP_Error when WP_CLI is
		// defined (e.g. by a sibling SecurityCliFallbackTest / CRM reports / Boards phase2
		// test earlier in the run). Treat that envelope as a denial.
		if ( is_wp_error( $result ) ) {
			return false;
		}
		return (bool) $result;
	}

	/**
	 * Helper: invoke an ability's execute_callback with the given input.
	 *
	 * @param string $ability_name
	 * @param array  $input
	 * @return mixed
	 */
	protected function invoke_execute_callback( $ability_name, $input = array() ) {
		$abilities = wp_get_abilities();
		if ( ! isset( $abilities[ $ability_name ] ) ) {
			return null;
		}
		$cb = $abilities[ $ability_name ]['execute_callback'] ?? null;
		if ( ! is_callable( $cb ) ) {
			return null;
		}
		return $cb( $input );
	}
}
