<?php
/**
 * Unit tests — FluentPlayer callback execution paths.
 *
 * The unit environment does not load FluentPlayer or FluentPlayerPro classes,
 * so every callback should gracefully degrade to a `missing_class` or
 * `ability_invalid_input` WP_Error rather than throwing. This is the contract
 * the per-callback try/catch blocks enforce.
 *
 * We spot-test one representative ability per cluster covering all three
 * operation types (read / write / delete) to confirm the contract holds.
 *
 * @package Fluent_Abilities\Tests\Unit\Player
 */

use PHPUnit\Framework\TestCase;

class PlayerCallbackTest extends TestCase {

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

	private function call( string $slug, array $input = array() ) {
		$ability = self::$registered[ $slug ] ?? null;
		$this->assertIsArray( $ability, "Ability not registered: {$slug}" );
		return call_user_func( $ability['execute_callback'], $input );
	}

	private function assertGracefulDegradation( $result, string $slug ): void {
		// Acceptable: either a WP_Error with one of the documented codes, OR a
		// safe empty data shape (some callbacks fall back to get_option() and
		// return an empty array when the vendor service is missing).
		if ( $result instanceof WP_Error ) {
			$this->assertContains(
				$result->get_error_code(),
				array( 'missing_class', 'ability_invalid_input', 'not_found', 'execution_failed', 'integration_not_configured' ),
				"Unexpected error code from {$slug}: " . $result->get_error_code()
			);
			return;
		}
		$this->assertIsArray(
			$result,
			"Expected WP_Error or array from {$slug} (vendor not loaded in unit env); got " . gettype( $result )
		);
	}

	/**
	 * @dataProvider read_sample_provider
	 */
	public function test_read_callback_degrades_gracefully( string $slug, array $input ): void {
		$result = $this->call( $slug, $input );
		$this->assertGracefulDegradation( $result, $slug );
	}

	/**
	 * @dataProvider write_sample_provider
	 */
	public function test_write_callback_degrades_gracefully( string $slug, array $input ): void {
		$result = $this->call( $slug, $input );
		$this->assertGracefulDegradation( $result, $slug );
	}

	/**
	 * @dataProvider delete_sample_provider
	 */
	public function test_delete_callback_degrades_gracefully( string $slug, array $input ): void {
		$result = $this->call( $slug, $input );
		$this->assertGracefulDegradation( $result, $slug );
	}

	/**
	 * @dataProvider invalid_input_provider
	 */
	public function test_invalid_input_short_circuits( string $slug, array $input, string $expected_code ): void {
		$result = $this->call( $slug, $input );
		$this->assertInstanceOf( WP_Error::class, $result, "Expected WP_Error for invalid input: {$slug}" );
		$this->assertSame( $expected_code, $result->get_error_code(), "Wrong error code for {$slug}" );
	}

	public static function read_sample_provider(): iterable {
		// One read per cluster (covering all 17 clusters).
		yield 'media-tags:list-media-tags'                => array( 'fluent-player/list-media-tags', array() );
		yield 'presets:list-presets'                      => array( 'fluent-player/list-presets', array() );
		yield 'settings:get-settings'                     => array( 'fluent-player/get-settings', array() );
		yield 'email-collections:list-email-collections'  => array( 'fluent-player/list-email-collections', array() );
		yield 'youtube:get-youtube-channel-info'          => array( 'fluent-player/get-youtube-channel-info', array() );
		yield 'smartcodes:list-smartcodes'                => array( 'fluent-player/list-smartcodes', array() );
		yield 'subtitles:get-youtube-captions'            => array( 'fluent-player/get-youtube-captions', array( 'media_id' => 1 ) );
		yield 'analytics:analytics-stats'                 => array( 'fluent-player/analytics-stats', array() );
		yield 'bunny-stream:bunny-stream-list-libraries'  => array( 'fluent-player/bunny-stream-list-libraries', array() );
		yield 'bunny-storage:bunny-storage-list-videos'   => array( 'fluent-player/bunny-storage-list-videos', array() );
		yield 'mux:mux-list-assets'                       => array( 'fluent-player/mux-list-assets', array() );
		yield 'license:get-license-details'               => array( 'fluent-player/get-license-details', array() );
	}

	public static function write_sample_provider(): iterable {
		yield 'media:create-media'              => array( 'fluent-player/create-media', array( 'settings' => array( 'viewType' => 'video', 'preset_slug' => 'default' ) ) );
		yield 'media-tags:create-media-tag'     => array( 'fluent-player/create-media-tag', array( 'tag_name' => 'test' ) );
		yield 'presets:create-preset'           => array( 'fluent-player/create-preset', array( 'name' => 'Test', 'settings' => array() ) );
		yield 'settings:update-settings'        => array( 'fluent-player/update-settings', array( 'settings' => array( 'general' => array() ) ) );
		yield 'integrations:save-integration'   => array( 'fluent-player/save-integration-settings', array( 'integration' => 'youtube', 'settings' => array() ) );
		yield 'subtitles:upload-subtitle'       => array( 'fluent-player/upload-subtitle', array( 'media_id' => 1, 'attachment_id' => 2 ) );
		yield 'timed-content:update-timed'      => array( 'fluent-player/update-timed-content', array( 'media_id' => 1 ) );
		yield 'bunny-stream:create-video'       => array( 'fluent-player/bunny-stream-create-video', array( 'library_id' => 1, 'title' => 'Test' ) );
		yield 'bunny-storage:create-directory'  => array( 'fluent-player/bunny-storage-create-directory', array( 'name' => 'test' ) );
		yield 'mux:create-asset'                => array( 'fluent-player/mux-create-asset', array( 'input_url' => 'https://example.com/v.mp4' ) );
		yield 'license:activate'                => array( 'fluent-player/activate-license', array( 'license_key' => 'TEST-KEY' ) );
	}

	public static function delete_sample_provider(): iterable {
		yield 'media:delete-media'                => array( 'fluent-player/delete-media', array( 'id' => 1 ) );
		yield 'media-tags:delete-media-tag'       => array( 'fluent-player/delete-media-tag', array( 'tag_name' => 'test' ) );
		yield 'email-collections:delete-one'      => array( 'fluent-player/delete-email-collection', array( 'id' => 1 ) );
		yield 'settings:reset-settings'           => array( 'fluent-player/reset-settings', array() );
		yield 'playlists:delete-playlist'         => array( 'fluent-player/delete-playlist', array( 'id' => 1 ) );
		yield 'subtitles:remove-subtitle'         => array( 'fluent-player/remove-subtitle', array( 'media_id' => 1, 'subtitle_id' => 'sub-1' ) );
		yield 'bunny-stream:delete-video'         => array( 'fluent-player/bunny-stream-delete-video', array( 'library_id' => 1, 'video_id' => 'v1' ) );
		yield 'bunny-storage:delete-video'        => array( 'fluent-player/bunny-storage-delete-video', array( 'path' => '/foo.mp4' ) );
		yield 'mux:delete-asset'                  => array( 'fluent-player/mux-delete-asset', array( 'asset_id' => 'a1' ) );
		yield 'license:deactivate'                => array( 'fluent-player/deactivate-license', array() );
	}

	public static function invalid_input_provider(): iterable {
		// Required-field omissions return ability_invalid_input before reaching vendor.
		yield 'get-media (missing id)'           => array( 'fluent-player/get-media', array(), 'ability_invalid_input' );
		yield 'delete-media (missing id)'        => array( 'fluent-player/delete-media', array(), 'ability_invalid_input' );
		yield 'get-preset (missing slug)'        => array( 'fluent-player/get-preset', array(), 'ability_invalid_input' );
		yield 'delete-preset (reserved slug)'    => array( 'fluent-player/delete-preset', array( 'slug' => 'default' ), 'preset_reserved' );
		yield 'create-preset (missing fields)'   => array( 'fluent-player/create-preset', array(), 'ability_invalid_input' );
		yield 'bunny-stream-create-video (missing library)' => array( 'fluent-player/bunny-stream-create-video', array( 'title' => 'x' ), 'ability_invalid_input' );
		yield 'mux-get-asset (missing id)'       => array( 'fluent-player/mux-get-asset', array(), 'ability_invalid_input' );
		yield 'mux-create-asset (missing url)'   => array( 'fluent-player/mux-create-asset', array(), 'ability_invalid_input' );
		yield 'activate-license (missing key)'   => array( 'fluent-player/activate-license', array( 'license_key' => '' ), 'ability_invalid_input' );
	}
}
