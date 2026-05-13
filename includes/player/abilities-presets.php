<?php
/**
 * FluentPlayer Abilities — Presets + Settings
 *
 * 9 abilities in the `fluent-player` category:
 *  - Cluster 3 Presets (5: 2 free reads + 3 Pro writes/delete) — option `fluent_player_presets`
 *  - Cluster 4 Settings (4: free) — option `fluent_player_settings` (9 sections)
 *
 * Presets MUST be mutated via PresetService (vendor decodes JSON + enforces
 * RESERVED_SLUGS); never touch the option directly.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

function fluent_abilities_player_register_presets_abilities() {

	$reg = new Fluent_Abilities_Registrar( 'player' );

	// ─── Cluster 3: Presets — free reads ────────────────────────────────────

	$reg->read( 'fluent-player/list-presets', array(
		'label'         => 'List presets',
		'description'   => 'List all FluentPlayer presets (built-in + custom).',
		'category'      => 'fluent-player',
		'output_schema' => fluent_abilities_schema_collection_output( 'presets', array(
			'name'        => array( 'type' => 'string' ),
			'slug'        => array( 'type' => 'string' ),
			'description' => array( 'type' => array( 'string', 'null' ) ),
			'settings'    => array( 'type' => array( 'object', 'null' ) ),
		) ),
		'callback'      => function ( $input ) {
			if ( ! class_exists( '\FluentPlayer\App\Services\PresetService' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentPlayer PresetService not found.' );
			}
			try {
				$all   = \FluentPlayer\App\Services\PresetService::all();
				$items = array();
				foreach ( (array) $all as $slug => $preset ) {
					$preset            = (array) $preset;
					$preset['slug']    = $preset['slug'] ?? $slug;
					$items[]           = $preset;
				}
				return array( 'presets' => $items, 'total' => count( $items ) );
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'execution_failed', $e->getMessage() );
			}
		},
	) );

	$reg->read( 'fluent-player/get-preset', array(
		'label'         => 'Get preset',
		'description'   => 'Get a single preset by slug.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'slug' ),
			'properties' => array(
				'slug' => array( 'type' => 'string', 'description' => 'Preset slug.' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'name'        => array( 'type' => 'string' ),
			'slug'        => array( 'type' => 'string' ),
			'description' => array( 'type' => array( 'string', 'null' ) ),
			'settings'    => array( 'type' => array( 'object', 'null' ) ),
		) ),
		'callback'      => function ( $input ) {
			$slug = isset( $input['slug'] ) ? sanitize_key( $input['slug'] ) : '';
			if ( '' === $slug ) {
				return fluent_abilities_error( 'ability_invalid_input', 'slug is required.' );
			}
			if ( ! class_exists( '\FluentPlayer\App\Services\PresetService' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentPlayer PresetService not found.' );
			}
			try {
				$preset = \FluentPlayer\App\Services\PresetService::find( $slug );
				if ( empty( $preset ) ) {
					return fluent_abilities_error( 'not_found', 'Preset not found: ' . $slug );
				}
				$preset         = (array) $preset;
				$preset['slug'] = $preset['slug'] ?? $slug;
				return $preset;
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'execution_failed', $e->getMessage() );
			}
		},
	) );

	// ─── Cluster 3: Presets — Pro writes/delete ─────────────────────────────

	if ( defined( 'FLUENT_PLAYER_PRO_VERSION' ) ) {

		$reg->write( 'fluent-player/create-preset', array(
			'label'         => 'Create preset',
			'description'   => 'Create a custom preset (Pro). Slug is auto-generated from name; must not collide with reserved slugs.',
			'category'      => 'fluent-player',
			'input_schema'  => array(
				'type'       => 'object',
				'required'   => array( 'name', 'settings' ),
				'properties' => array(
					'name'     => array( 'type' => 'string', 'description' => 'Human-readable preset name.' ),
					'settings' => array(
						'type'        => 'object',
						'description' => 'Preset settings (skin, controls, behaviors, styles, email_capture, cta, action_bar). 50+ nested fields enforced by Pro sanitizer.',
					),
				),
			),
			'output_schema' => fluent_abilities_schema_success_output( array(
				'message' => array( 'type' => 'string' ),
				'preset'  => array( 'type' => 'object' ),
			) ),
			'annotations'   => array( 'idempotent' => false ),
			'callback'      => function ( $input ) {
				$name = isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '';
				if ( '' === $name ) {
					return fluent_abilities_error( 'ability_invalid_input', 'name is required.' );
				}
				$settings = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : null;
				if ( null === $settings ) {
					return fluent_abilities_error( 'ability_invalid_input', 'settings (object) is required.' );
				}
				if ( ! class_exists( '\FluentPlayerPro\App\Http\Controllers\PresetController' ) ) {
					return fluent_abilities_error( 'missing_class', 'FluentPlayerPro PresetController not found.' );
				}
				try {
					$_REQUEST['name']     = $name;
					$_REQUEST['settings'] = $settings;
					$_POST['name']        = $name;
					$_POST['settings']    = $settings;
					$controller           = new \FluentPlayerPro\App\Http\Controllers\PresetController();
					$result               = $controller->store();
					$message              = is_array( $result ) ? ( $result['message'] ?? 'Preset created.' ) : 'Preset created.';
					$preset               = is_array( $result ) ? ( $result['preset'] ?? array() ) : array();
					return array(
						'success' => true,
						'message' => $message,
						'preset'  => fluent_abilities_safe_array( $preset ),
					);
				} catch ( \Throwable $e ) {
					return fluent_abilities_error( 'execution_failed', $e->getMessage() );
				}
			},
		) );

		$reg->write( 'fluent-player/update-preset', array(
			'label'         => 'Update preset',
			'description'   => 'Update a preset (Pro). Slug is immutable; reserved slugs CAN be updated (only deletion is blocked).',
			'category'      => 'fluent-player',
			'input_schema'  => array(
				'type'       => 'object',
				'required'   => array( 'slug', 'name', 'settings' ),
				'properties' => array(
					'slug'     => array( 'type' => 'string' ),
					'name'     => array( 'type' => 'string' ),
					'settings' => array( 'type' => 'object' ),
				),
			),
			'output_schema' => fluent_abilities_schema_success_output( array(
				'message' => array( 'type' => 'string' ),
				'preset'  => array( 'type' => 'object' ),
			) ),
			'callback'      => function ( $input ) {
				$slug = isset( $input['slug'] ) ? sanitize_key( $input['slug'] ) : '';
				$name = isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '';
				if ( '' === $slug || '' === $name ) {
					return fluent_abilities_error( 'ability_invalid_input', 'slug and name are required.' );
				}
				$settings = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : null;
				if ( null === $settings ) {
					return fluent_abilities_error( 'ability_invalid_input', 'settings (object) is required.' );
				}
				if ( ! class_exists( '\FluentPlayerPro\App\Http\Controllers\PresetController' ) ) {
					return fluent_abilities_error( 'missing_class', 'FluentPlayerPro PresetController not found.' );
				}
				try {
					$_REQUEST['name']     = $name;
					$_REQUEST['settings'] = $settings;
					$_POST['name']        = $name;
					$_POST['settings']    = $settings;
					$controller           = new \FluentPlayerPro\App\Http\Controllers\PresetController();
					$result               = $controller->update( $slug );
					$message              = is_array( $result ) ? ( $result['message'] ?? 'Preset updated.' ) : 'Preset updated.';
					$preset               = is_array( $result ) ? ( $result['preset'] ?? array() ) : array();
					return array(
						'success' => true,
						'message' => $message,
						'preset'  => fluent_abilities_safe_array( $preset ),
					);
				} catch ( \Throwable $e ) {
					return fluent_abilities_error( 'execution_failed', $e->getMessage() );
				}
			},
		) );

		$reg->delete( 'fluent-player/delete-preset', array(
			'label'         => 'Delete preset',
			'description'   => 'Delete a custom preset (Pro). Reserved slugs (default, course, simple, minimal, standard, floating, ambient) cannot be deleted.',
			'category'      => 'fluent-player',
			'input_schema'  => array(
				'type'       => 'object',
				'required'   => array( 'slug' ),
				'properties' => array(
					'slug' => array( 'type' => 'string' ),
				),
			),
			'output_schema' => fluent_abilities_schema_success_output( array(
				'message' => array( 'type' => 'string' ),
			) ),
			'annotations'   => array( 'idempotent' => false ),
			'callback'      => function ( $input ) {
				$slug = isset( $input['slug'] ) ? sanitize_key( $input['slug'] ) : '';
				if ( '' === $slug ) {
					return fluent_abilities_error( 'ability_invalid_input', 'slug is required.' );
				}
				$reserved = array( 'default', 'course', 'simple', 'minimal', 'standard', 'floating', 'ambient' );
				if ( in_array( $slug, $reserved, true ) ) {
					return fluent_abilities_error( 'preset_reserved', 'Cannot delete reserved preset: ' . $slug );
				}
				if ( ! class_exists( '\FluentPlayerPro\App\Http\Controllers\PresetController' ) ) {
					return fluent_abilities_error( 'missing_class', 'FluentPlayerPro PresetController not found.' );
				}
				try {
					$controller = new \FluentPlayerPro\App\Http\Controllers\PresetController();
					$result     = $controller->delete( $slug );
					$message    = is_array( $result ) ? ( $result['message'] ?? 'Preset deleted.' ) : 'Preset deleted.';
					return array(
						'success' => true,
						'message' => $message,
					);
				} catch ( \Throwable $e ) {
					return fluent_abilities_error( 'execution_failed', $e->getMessage() );
				}
			},
		) );

	} // end Pro guard for Cluster 3 writes/delete.

	// ─── Cluster 4: Settings (free) ─────────────────────────────────────────

	$reg->read( 'fluent-player/get-settings', array(
		'label'         => 'Get settings (full)',
		'description'   => 'Get full FluentPlayer settings (all sections merged with defaults).',
		'category'      => 'fluent-player',
		'output_schema' => fluent_abilities_schema_item_output( array(
			'settings' => array( 'type' => 'object' ),
		) ),
		'callback'      => function ( $input ) {
			if ( class_exists( '\FluentPlayer\App\Services\SettingsService' ) ) {
				try {
					$settings = \FluentPlayer\App\Services\SettingsService::getSettings();
					return array( 'settings' => fluent_abilities_safe_array( $settings ) );
				} catch ( \Throwable $e ) {
					return fluent_abilities_error( 'execution_failed', $e->getMessage() );
				}
			}
			$raw = get_option( 'fluent_player_settings', array() );
			return array( 'settings' => is_array( $raw ) ? $raw : array() );
		},
	) );

	$reg->read( 'fluent-player/get-settings-section', array(
		'label'         => 'Get settings section',
		'description'   => 'Get a specific settings section. Applies the fluent_player/settings_section/{section} filter.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'section' ),
			'properties' => array(
				'section' => array(
					'type'        => 'string',
					'description' => 'Settings section name.',
					'enum'        => array( 'general', 'youtube', 'performance', 'analytics', 'google_analytics', 'email_capture', 'branding', 'subtitle_service' ),
				),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'section'  => array( 'type' => 'string' ),
			'settings' => array( 'type' => 'object' ),
		) ),
		'callback'      => function ( $input ) {
			$section = isset( $input['section'] ) ? sanitize_key( $input['section'] ) : '';
			if ( '' === $section ) {
				return fluent_abilities_error( 'ability_invalid_input', 'section is required.' );
			}
			try {
				if ( class_exists( '\FluentPlayer\App\Services\SettingsService' ) ) {
					$settings = \FluentPlayer\App\Services\SettingsService::getSettings();
				} else {
					$settings = get_option( 'fluent_player_settings', array() );
				}
				$section_data = is_array( $settings ) && isset( $settings[ $section ] ) ? $settings[ $section ] : array();
				$section_data = apply_filters( "fluent_player/settings_section/{$section}", $section_data );
				return array(
					'section'  => $section,
					'settings' => fluent_abilities_safe_array( $section_data ),
				);
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'execution_failed', $e->getMessage() );
			}
		},
	) );

	$reg->write( 'fluent-player/update-settings', array(
		'label'         => 'Update settings',
		'description'   => 'Update FluentPlayer settings (any subset of whitelisted top-level keys). On free installs, Pro-only fields (subtitle_service.service_url / api_token) are stripped by the vendor sanitizer.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'settings' ),
			'properties' => array(
				'settings' => array(
					'type'        => 'object',
					'description' => 'Any subset of whitelisted top-level keys (general, youtube, performance, analytics, google_analytics, email_capture, branding, subtitle_service).',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'settings' => array( 'type' => 'object' ),
		) ),
		'callback'      => function ( $input ) {
			$settings = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : null;
			if ( null === $settings ) {
				return fluent_abilities_error( 'ability_invalid_input', 'settings (object) is required.' );
			}
			if ( ! class_exists( '\FluentPlayer\App\Services\SettingsService' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentPlayer SettingsService not found.' );
			}
			try {
				\FluentPlayer\App\Services\SettingsService::saveSettings( $settings );
				$merged = \FluentPlayer\App\Services\SettingsService::getSettings();
				return array( 'settings' => fluent_abilities_safe_array( $merged ) );
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'execution_failed', $e->getMessage() );
			}
		},
	) );

	$reg->delete( 'fluent-player/reset-settings', array(
		'label'         => 'Reset settings',
		'description'   => 'Reset all FluentPlayer settings to defaults.',
		'category'      => 'fluent-player',
		'output_schema' => fluent_abilities_schema_item_output( array(
			'settings' => array( 'type' => 'object' ),
		) ),
		'annotations'   => array( 'idempotent' => true ),
		'callback'      => function ( $input ) {
			if ( ! class_exists( '\FluentPlayer\App\Services\SettingsService' ) ) {
				delete_option( 'fluent_player_settings' );
				return array( 'settings' => array() );
			}
			try {
				if ( method_exists( '\FluentPlayer\App\Services\SettingsService', 'resetSettings' ) ) {
					\FluentPlayer\App\Services\SettingsService::resetSettings();
				} else {
					delete_option( 'fluent_player_settings' );
				}
				$defaults = \FluentPlayer\App\Services\SettingsService::getSettings();
				return array( 'settings' => fluent_abilities_safe_array( $defaults ) );
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'execution_failed', $e->getMessage() );
			}
		},
	) );
}
add_action( 'wp_abilities_api_init', 'fluent_abilities_player_register_presets_abilities', 100 );
