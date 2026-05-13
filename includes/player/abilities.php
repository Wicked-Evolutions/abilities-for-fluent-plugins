<?php
/**
 * FluentPlayer Abilities — module loader.
 *
 * Greenfield module added for v2.0.0 (Fluent Suite Registrar Bundle Sprint).
 *
 * Registers 103 abilities in the `fluent-player` category — 22 free-tier surfaces
 * (Media, Settings, YouTube, Email Collections, free Presets) plus 81 Pro-tier
 * surfaces (Playlists, Subtitles, Analytics, Bunny CDN Stream + Storage, Mux,
 * Media Tags, License). Pro sub-files are guarded with class_exists() on a Pro
 * sentinel class so they cleanly no-op when FluentPlayer Pro is not installed.
 *
 * Pending scaffold-owned edits (described in PR body, integrated by orchestrator at merge):
 *   - `includes/ability-categories.php` — add `fluent-player` category entry
 *   - `abilities-for-fluent-plugins.php` — add `'player' => 'FLUENT_PLAYER_VERSION'` to $modules
 *   - `includes/security.php` — add `'player' => array(read,write,delete)` to fluent_abilities_get_caps()
 *
 * Until those land, this file defensively self-registers the category and caps
 * so the module is functional on probe sites without central wiring. The self-
 * registration is idempotent: it no-ops if the canonical scaffold edits are
 * present.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

// ─── Defensive category registration ───────────────────────────────────────
// Idempotent: if includes/ability-categories.php already registers fluent-player
// at the canonical hook, this second registration call is overwritten by the
// later/identical args.
add_action(
	'wp_abilities_api_categories_init',
	function () {
		if ( function_exists( 'wp_register_ability_category' ) ) {
			wp_register_ability_category(
				'fluent-player',
				array(
					'label'       => 'FluentPlayer',
					'description' => 'Media items, playlists, presets, settings, analytics, subtitles, email captures, and Bunny CDN / Mux integration for FluentPlayer + FluentPlayer Pro.',
				)
			);
		}
	},
	5
);

// ─── Defensive cap registration ────────────────────────────────────────────
// The shared fluent_abilities_get_caps() in includes/security.php does not yet
// include the 'player' module. Until the scaffold-owned edit lands, grant the
// player caps to the administrator role on init. Idempotent: WP_Role::add_cap()
// is a no-op if the cap is already present.
add_action(
	'init',
	function () {
		if ( ! function_exists( 'get_role' ) ) {
			return;
		}
		$admin_role = get_role( 'administrator' );
		if ( ! $admin_role ) {
			return;
		}
		foreach ( array( 'fluent_player_read', 'fluent_player_write', 'fluent_player_delete' ) as $cap ) {
			$admin_role->add_cap( $cap );
		}
	},
	5
);

// ─── Sub-file loader ───────────────────────────────────────────────────────
// Each sub-file defines a named registration function and hooks it onto
// wp_abilities_api_init. Named functions (rather than inline closures) keep
// the registration logic directly callable from unit tests where add_action
// is stubbed as a no-op.
require_once __DIR__ . '/abilities-media.php';
require_once __DIR__ . '/abilities-presets.php';
require_once __DIR__ . '/abilities-email.php';
require_once __DIR__ . '/abilities-playlists.php';
require_once __DIR__ . '/abilities-analytics.php';
require_once __DIR__ . '/abilities-bunny.php';
require_once __DIR__ . '/abilities-mux.php';
require_once __DIR__ . '/abilities-license.php';
