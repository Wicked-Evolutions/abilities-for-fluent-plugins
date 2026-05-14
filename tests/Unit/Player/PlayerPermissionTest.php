<?php
/**
 * Unit tests — FluentPlayer permission_callback rejection paths.
 *
 * Asserts that every fluent-player ability's permission_callback denies access
 * when the current user does NOT have the required capability. License-cluster
 * abilities use the `manage_options` override; all others use the player module
 * `fluent_player_{read|write|delete}` caps.
 *
 * @package Fluent_Abilities\Tests\Unit\Player
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/PlayerRegistrationTest.php';

class PlayerPermissionTest extends TestCase {

	private static $registered = array();

	public static function setUpBeforeClass(): void {
		global $_wp_registered_abilities;
		$_wp_registered_abilities = array();

		if ( ! defined( 'FLUENT_PLAYER_VERSION' ) ) {
			define( 'FLUENT_PLAYER_VERSION', '1.0.5' );
		}
		if ( ! defined( 'FLUENT_PLAYER_PRO_VERSION' ) ) {
			define( 'FLUENT_PLAYER_PRO_VERSION', '1.0.5' );
		}

		require_once dirname( __DIR__, 3 ) . '/includes/player/abilities.php';

		foreach ( array(
			'fluent_abilities_player_register_media_abilities',
			'fluent_abilities_player_register_presets_abilities',
			'fluent_abilities_player_register_email_abilities',
			'fluent_abilities_player_register_playlists_abilities',
			'fluent_abilities_player_register_analytics_abilities',
			'fluent_abilities_player_register_bunny_abilities',
			'fluent_abilities_player_register_mux_abilities',
			'fluent_abilities_player_register_license_abilities',
		) as $fn ) {
			if ( function_exists( $fn ) ) {
				$fn();
			}
		}

		self::$registered = wp_get_abilities();
	}

	protected function setUp(): void {
		// Authenticated user with NO caps granted — the rejection control.
		$GLOBALS['_test_current_user_id'] = 99;
		$GLOBALS['_test_user_caps']       = array();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['_test_current_user_id'], $GLOBALS['_test_user_caps'] );
	}

	/**
	 * @dataProvider player_slug_provider
	 */
	public function test_permission_rejects_unauthorized_user( string $slug ): void {
		$ability = self::$registered[ $slug ] ?? null;
		$this->assertIsArray( $ability, "Ability not registered: {$slug}" );

		$result = call_user_func( $ability['permission_callback'] );
		$this->assertFalse(
			$result,
			"permission_callback should reject user without caps: {$slug}"
		);
	}

	/**
	 * @dataProvider player_slug_provider
	 */
	public function test_permission_grants_with_caps( string $slug ): void {
		$ability = self::$registered[ $slug ] ?? null;
		$op      = $ability['meta']['annotations']['permission'] ?? 'read';
		// License cluster overrides to manage_options.
		$is_license_slug = in_array( $slug, array(
			'fluent-player/get-license-details',
			'fluent-player/activate-license',
			'fluent-player/deactivate-license',
		), true );

		$GLOBALS['_test_user_caps'] = $is_license_slug
			? array( 'manage_options' )
			: array( "fluent_player_{$op}" );

		$result = call_user_func( $ability['permission_callback'] );
		$this->assertTrue(
			(bool) $result,
			"permission_callback should grant user with required cap: {$slug}"
		);
	}

	public static function player_slug_provider(): iterable {
		foreach ( PlayerRegistrationTest::expected_slugs() as $slug ) {
			yield $slug => array( $slug );
		}
	}
}
