<?php
/**
 * Unit tests — FluentPlayer ability registration.
 *
 * Verifies that the 9 sub-files under includes/player/ register the expected
 * 103 abilities with consistent shape (label, description, category, schemas,
 * annotations, permission_callback). Each Pro sub-file is force-loaded by
 * defining FLUENT_PLAYER_PRO_VERSION before calling the register functions.
 *
 * @package Fluent_Abilities\Tests\Unit\Player
 */

use PHPUnit\Framework\TestCase;
use WickedEvolutions\AbilitiesForFluent\Core\Registrar;

class PlayerRegistrationTest extends TestCase {

	private const EXPECTED_TOTAL = 99;

	/**
	 * @var array<string, array> Cached snapshot of registered abilities for the run.
	 */
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

		$register_functions = array(
			'fluent_abilities_player_register_media_abilities',
			'fluent_abilities_player_register_presets_abilities',
			'fluent_abilities_player_register_email_abilities',
			'fluent_abilities_player_register_playlists_abilities',
			'fluent_abilities_player_register_analytics_abilities',
			'fluent_abilities_player_register_bunny_abilities',
			'fluent_abilities_player_register_mux_abilities',
			'fluent_abilities_player_register_license_abilities',
		);
		foreach ( $register_functions as $fn ) {
			if ( function_exists( $fn ) ) {
				$fn();
			}
		}

		self::$registered = wp_get_abilities();
	}

	public function test_all_103_abilities_register(): void {
		$player = array_filter(
			array_keys( self::$registered ),
			static fn( $slug ) => 0 === strpos( $slug, 'fluent-player/' )
		);
		$this->assertCount(
			self::EXPECTED_TOTAL,
			$player,
			'Expected 103 fluent-player abilities; got ' . count( $player )
		);
	}

	public function test_no_duplicate_slugs(): void {
		$player = array_filter(
			array_keys( self::$registered ),
			static fn( $slug ) => 0 === strpos( $slug, 'fluent-player/' )
		);
		$this->assertSame(
			count( $player ),
			count( array_unique( $player ) ),
			'Duplicate fluent-player ability slugs detected.'
		);
	}

	/**
	 * @dataProvider expected_slug_provider
	 */
	public function test_ability_registered( string $slug ): void {
		$this->assertArrayHasKey(
			$slug,
			self::$registered,
			"Expected ability not registered: {$slug}"
		);
	}

	/**
	 * @dataProvider expected_slug_provider
	 */
	public function test_ability_shape( string $slug ): void {
		$ability = self::$registered[ $slug ] ?? null;
		$this->assertIsArray( $ability, "Ability not registered: {$slug}" );

		$this->assertArrayHasKey( 'label', $ability );
		$this->assertNotEmpty( $ability['label'], "Missing label: {$slug}" );

		$this->assertArrayHasKey( 'description', $ability );
		$this->assertNotEmpty( $ability['description'], "Missing description: {$slug}" );

		$this->assertSame( 'fluent-player', $ability['category'] ?? null, "Wrong category: {$slug}" );

		$this->assertArrayHasKey( 'input_schema', $ability );
		$this->assertArrayHasKey( 'execute_callback', $ability );
		$this->assertIsCallable( $ability['execute_callback'], "execute_callback not callable: {$slug}" );

		$this->assertArrayHasKey( 'permission_callback', $ability );
		$this->assertIsCallable( $ability['permission_callback'], "permission_callback not callable: {$slug}" );

		$this->assertArrayHasKey( 'meta', $ability );
		$this->assertArrayHasKey( 'annotations', $ability['meta'] );
		$annotations = $ability['meta']['annotations'];
		$this->assertContains( $annotations['permission'], array( 'read', 'write', 'delete' ), "Bad annotation.permission: {$slug}" );
	}

	/**
	 * @dataProvider expected_slug_provider
	 */
	public function test_slug_naming_convention( string $slug ): void {
		$this->assertMatchesRegularExpression(
			'#^fluent-player/[a-z][a-z0-9-]*$#',
			$slug,
			"Slug does not follow fluent-player/{verb-noun}: {$slug}"
		);
	}

	public function test_read_abilities_marked_readonly(): void {
		foreach ( self::$registered as $slug => $ability ) {
			if ( 0 !== strpos( $slug, 'fluent-player/' ) ) {
				continue;
			}
			if ( 'read' !== ( $ability['meta']['annotations']['permission'] ?? null ) ) {
				continue;
			}
			$this->assertTrue(
				$ability['meta']['annotations']['readonly'] ?? false,
				"Read ability not marked readonly: {$slug}"
			);
			$this->assertFalse(
				$ability['meta']['annotations']['destructive'] ?? true,
				"Read ability marked destructive: {$slug}"
			);
		}
	}

	public function test_delete_abilities_marked_destructive(): void {
		foreach ( self::$registered as $slug => $ability ) {
			if ( 0 !== strpos( $slug, 'fluent-player/' ) ) {
				continue;
			}
			if ( 'delete' !== ( $ability['meta']['annotations']['permission'] ?? null ) ) {
				continue;
			}
			$this->assertTrue(
				$ability['meta']['annotations']['destructive'] ?? false,
				"Delete ability not marked destructive: {$slug}"
			);
		}
	}

	public static function expected_slug_provider(): iterable {
		foreach ( self::expected_slugs() as $slug ) {
			yield $slug => array( $slug );
		}
	}

	/**
	 * Canonical inventory of the 103 fluent-player ability slugs by cluster.
	 *
	 * @return string[]
	 */
	public static function expected_slugs(): array {
		return array(
			// Cluster 1: Media (7).
			'fluent-player/get-media',
			'fluent-player/search-media',
			'fluent-player/create-media',
			'fluent-player/update-media',
			'fluent-player/delete-media',
			'fluent-player/get-media-metadata',

			// Cluster 2: Media Tags (4, Pro).
			'fluent-player/list-media-tags',
			'fluent-player/create-media-tag',
			'fluent-player/rename-media-tag',
			'fluent-player/delete-media-tag',

			// Cluster 3: Presets (5).
			'fluent-player/list-presets',
			'fluent-player/get-preset',
			'fluent-player/create-preset',
			'fluent-player/update-preset',
			'fluent-player/delete-preset',

			// Cluster 4: Settings (4).
			'fluent-player/get-settings',
			'fluent-player/get-settings-section',
			'fluent-player/update-settings',
			'fluent-player/reset-settings',

			// Cluster 5: Email Collections (4).
			'fluent-player/list-email-collections',
			'fluent-player/get-email-collection',
			'fluent-player/export-email-collections',
			'fluent-player/delete-email-collection',

			// Cluster 6: Integrations (4).
			'fluent-player/get-integration-fields',
			'fluent-player/save-integration-settings',
			'fluent-player/test-integration-connection',

			// Cluster 7: Email Providers (4).
			'fluent-player/save-email-provider-settings',
			'fluent-player/list-provider-resources',
			'fluent-player/validate-provider-field',

			// Cluster 8: YouTube (1).
			'fluent-player/get-youtube-channel-info',

			// Cluster 9: Layers / Smartcodes (2).
			'fluent-player/list-layer-forms',
			'fluent-player/list-smartcodes',

			// Cluster 10: Playlists (5).
			'fluent-player/get-playlist',
			'fluent-player/create-playlist',
			'fluent-player/update-playlist',
			'fluent-player/delete-playlist',

			// Cluster 11: Subtitles (5).
			'fluent-player/upload-subtitle',
			'fluent-player/remove-subtitle',
			'fluent-player/get-youtube-captions',
			'fluent-player/import-youtube-captions',
			'fluent-player/generate-youtube-storyboard',

			// Cluster 12: Timed Content (1).
			'fluent-player/update-timed-content',

			// Cluster 13: Analytics (17).
			'fluent-player/analytics-stats',
			'fluent-player/analytics-top-videos',
			'fluent-player/analytics-top-users',
			'fluent-player/analytics-location-breakdown',
			'fluent-player/analytics-new-returning-viewers',
			'fluent-player/analytics-performance-over-time',
			'fluent-player/analytics-retention',
			'fluent-player/analytics-devices',
			'fluent-player/analytics-video-stats',
			'fluent-player/analytics-video-retention',
			'fluent-player/analytics-video-devices',
			'fluent-player/analytics-video-location-breakdown',
			'fluent-player/analytics-video-top-users',
			'fluent-player/analytics-user-info',
			'fluent-player/analytics-user-stats',
			'fluent-player/analytics-user-top-videos',
			'fluent-player/analytics-user-retention',

			// Cluster 14: Bunny Stream (9).
			'fluent-player/bunny-stream-list-libraries',
			'fluent-player/bunny-stream-list-videos',
			'fluent-player/bunny-stream-create-video',
			'fluent-player/bunny-stream-update-video',
			'fluent-player/bunny-stream-delete-video',
			'fluent-player/bunny-stream-list-collections',
			'fluent-player/bunny-stream-create-collection',
			'fluent-player/bunny-stream-update-collection',
			'fluent-player/bunny-stream-delete-collection',

			// Cluster 15: Bunny Storage (4).
			'fluent-player/bunny-storage-list-videos',
			'fluent-player/bunny-storage-get-video',
			'fluent-player/bunny-storage-delete-video',
			'fluent-player/bunny-storage-create-directory',

			// Cluster 16: Mux (24).
			'fluent-player/mux-list-assets',
			'fluent-player/mux-get-asset',
			'fluent-player/mux-create-asset',
			'fluent-player/mux-update-asset',
			'fluent-player/mux-delete-asset',
			'fluent-player/mux-update-asset-mp4-support',
			'fluent-player/mux-create-upload',
			'fluent-player/mux-get-upload-status',
			'fluent-player/mux-create-track',
			'fluent-player/mux-delete-track',
			'fluent-player/mux-generate-track-subtitles',
			'fluent-player/mux-list-live-streams',
			'fluent-player/mux-create-live-stream',
			'fluent-player/mux-get-live-stream',
			'fluent-player/mux-delete-live-stream',
			'fluent-player/mux-reset-stream-key',
			'fluent-player/mux-list-playback-restrictions',
			'fluent-player/mux-create-playback-restriction',
			'fluent-player/mux-delete-playback-restriction',
			'fluent-player/mux-get-delivery-usage',
			'fluent-player/mux-list-signing-keys',
			'fluent-player/mux-create-signing-key',
			'fluent-player/mux-delete-signing-key',
			'fluent-player/mux-get-asset-captions',

			// Cluster 17: License (3).
			'fluent-player/get-license-details',
			'fluent-player/activate-license',
			'fluent-player/deactivate-license',
		);
	}
}
