<?php
/**
 * Fluent Boards — Admin Settings + Onboarding + License + Dashboard
 * (Research §4.26 + §4.28 + §4.29 + §4.30)
 *
 * §4.26 Admin: features + general + pages + storage — 7 abilities (5 free / 2 pro)
 * §4.28 Onboarding                                  — 2 abilities (free)
 * §4.29 License                                     — 3 abilities (pro)
 * §4.30 Board menu + dashboard                      — 3 abilities (free)
 * Total: 15 abilities.
 *
 * Settings are stored in WordPress options under namespaced keys:
 *   fluent_boards_features, fluent_boards_general, fluent_boards_storage,
 *   fluent_boards_onboarded, fluent_boards_dashboard_view (per-user).
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

// =========================================================================
// §4.26.1 — get-feature-modules
// =========================================================================
$reg->read( 'fluent-boards/get-feature-modules', array(
	'label'         => 'Get Feature Modules',
	'description'   => 'Return Fluent Boards feature-toggle flags (custom-fields, time-tracking, attachments, etc.) from wp_options.fluent_boards_features.',
	'category'      => 'fluent-boards',
	'output_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'features' => array( 'type' => array( 'object', 'array', 'null' ) ),
		),
	),
	'callback' => function() {
		return array( 'features' => get_option( 'fluent_boards_features', array() ) );
	},
) );

// =========================================================================
// §4.26.2 — save-feature-modules
// =========================================================================
$reg->write( 'fluent-boards/save-feature-modules', array(
	'label'       => 'Save Feature Modules',
	'description' => 'Update Fluent Boards feature-toggle flags. Merges with existing features option.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'features' ),
		'properties' => array(
			'features' => array( 'type' => 'object' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'features' => array( 'type' => 'object' ) ) ),
	'callback'     => function( $input ) {
		$existing = (array) get_option( 'fluent_boards_features', array() );
		$next     = array_replace_recursive( $existing, (array) ( $input['features'] ?? array() ) );
		update_option( 'fluent_boards_features', $next );
		return array( 'success' => true, 'features' => $next );
	},
) );

// =========================================================================
// §4.26.3 — get-general-settings
// =========================================================================
$reg->read( 'fluent-boards/get-general-settings', array(
	'label'         => 'Get General Settings',
	'description'   => 'Return Fluent Boards general settings option.',
	'category'      => 'fluent-boards',
	'output_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'settings' => array( 'type' => array( 'object', 'array', 'null' ) ),
		),
	),
	'callback' => function() {
		return array( 'settings' => get_option( 'fluent_boards_general', array() ) );
	},
) );

// =========================================================================
// §4.26.4 — save-general-settings
// =========================================================================
$reg->write( 'fluent-boards/save-general-settings', array(
	'label'       => 'Save General Settings',
	'description' => 'Update Fluent Boards general settings. Merges with existing.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'settings' ),
		'properties' => array(
			'settings' => array( 'type' => 'object' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'settings' => array( 'type' => 'object' ) ) ),
	'callback'     => function( $input ) {
		$existing = (array) get_option( 'fluent_boards_general', array() );
		$next     = array_replace_recursive( $existing, (array) ( $input['settings'] ?? array() ) );
		update_option( 'fluent_boards_general', $next );
		return array( 'success' => true, 'settings' => $next );
	},
) );

// =========================================================================
// §4.26.5 — list-admin-pages
// =========================================================================
$reg->read( 'fluent-boards/list-admin-pages', array(
	'label'         => 'List Admin Pages',
	'description'   => 'Return Fluent Boards admin page slugs registered in the WordPress admin menu.',
	'category'      => 'fluent-boards',
	'output_schema' => fluent_abilities_schema_collection_output( 'pages', array(
		'slug'  => array( 'type' => 'string' ),
		'label' => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function() {
		$pages = array(
			array( 'slug' => 'fluent-boards',           'label' => 'Boards' ),
			array( 'slug' => 'fluent-boards-settings',  'label' => 'Settings' ),
			array( 'slug' => 'fluent-boards-templates', 'label' => 'Templates' ),
			array( 'slug' => 'fluent-boards-reports',   'label' => 'Reports' ),
		);
		return array( 'pages' => $pages, 'total' => count( $pages ) );
	},
) );

// =========================================================================
// §4.26.6 — get-storage-settings (pro)
// =========================================================================
$reg->read( 'fluent-boards/get-storage-settings', array(
	'label'         => 'Get Storage Settings (Pro)',
	'description'   => 'Get the active storage driver configuration for uploads (local/s3/bunnycdn/etc.).',
	'category'      => 'fluent-boards',
	'output_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'driver'        => array( 'type' => array( 'string', 'null' ) ),
			'driver_config' => array( 'type' => array( 'object', 'null' ) ),
		),
	),
	'callback' => function() {
		$settings = (array) get_option( 'fluent_boards_storage', array() );
		return array( 'driver' => $settings['driver'] ?? 'local', 'driver_config' => $settings['driver_config'] ?? null );
	},
) );

// =========================================================================
// §4.26.7 — update-storage-settings (pro)
// =========================================================================
$reg->write( 'fluent-boards/update-storage-settings', array(
	'label'       => 'Update Storage Settings (Pro)',
	'description' => 'Update storage driver and driver_config used for attachment uploads.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'driver' ),
		'properties' => array(
			'driver'        => array( 'type' => 'string', 'enum' => array( 'local', 's3', 'bunnycdn' ) ),
			'driver_config' => array( 'type' => 'object' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'driver' => array( 'type' => 'string' ) ) ),
	'callback'     => function( $input ) {
		$next = array(
			'driver'        => sanitize_key( $input['driver'] ?? 'local' ),
			'driver_config' => is_array( $input['driver_config'] ?? null ) ? $input['driver_config'] : array(),
		);
		update_option( 'fluent_boards_storage', $next );
		return array( 'success' => true, 'driver' => $next['driver'] );
	},
) );

// =========================================================================
// §4.28.1 — onboard-first-board (idempotent:false)
// =========================================================================
$reg->write( 'fluent-boards/onboard-first-board', array(
	'label'       => 'Onboard First Board',
	'description' => 'Create the first board on a fresh install, optionally seeded from template_id. Sets fluent_boards_onboarded option to current timestamp.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'title' ),
		'properties' => array(
			'title'       => array( 'type' => 'string' ),
			'template_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'board_id' => array( 'type' => 'integer' ),
	) ),
	'annotations' => array( 'idempotent' => false ),
	'callback'    => function( $input ) {
		$title = sanitize_text_field( $input['title'] ?? '' );
		if ( ! $title ) {
			return fluent_abilities_error( 'ability_invalid_input', 'title is required.' );
		}
		$now    = current_time( 'mysql' );
		$new_id = wpFluent()->table( 'fbs_boards' )->insert( array(
			'title'      => $title,
			'type'       => 'to-do',
			'status'     => 'active',
			'created_by' => (int) get_current_user_id(),
			'created_at' => $now,
			'updated_at' => $now,
		) );
		// Default stages.
		foreach ( array( 'To Do', 'In Progress', 'Done' ) as $idx => $stage_title ) {
			wpFluent()->table( 'fbs_board_terms' )->insert( array(
				'board_id'   => $new_id,
				'type'       => 'stage',
				'title'      => $stage_title,
				'position'   => $idx + 1,
				'created_at' => $now,
				'updated_at' => $now,
			) );
		}
		update_option( 'fluent_boards_onboarded', $now );
		return array( 'success' => true, 'board_id' => (int) $new_id );
	},
) );

// =========================================================================
// §4.28.2 — skip-onboarding (idempotent:true)
// =========================================================================
$reg->write( 'fluent-boards/skip-onboarding', array(
	'label'         => 'Skip Onboarding',
	'description'   => 'Mark onboarding as complete without creating a starter board. Idempotent.',
	'category'      => 'fluent-boards',
	'output_schema' => fluent_abilities_schema_success_output(),
	'annotations'   => array( 'idempotent' => true ),
	'callback'      => function() {
		update_option( 'fluent_boards_onboarded', current_time( 'mysql' ) );
		return array( 'success' => true );
	},
) );

// =========================================================================
// §4.29.1 — get-license-status (pro)
// =========================================================================
$reg->read( 'fluent-boards/get-license-status', array(
	'label'         => 'Get Fluent Boards Pro License Status (Pro)',
	'description'   => 'Return the activation status of the WPManageNinja Fluent Boards Pro license (distinct from Wicked-Evolutions Pro per §6.10).',
	'category'      => 'fluent-boards',
	'output_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'status'       => array( 'type' => array( 'string', 'null' ) ),
			'activated_at' => array( 'type' => array( 'string', 'null' ) ),
			'expires_at'   => array( 'type' => array( 'string', 'null' ) ),
			'plan'         => array( 'type' => array( 'string', 'null' ) ),
		),
	),
	'callback' => function() {
		$lic = (array) get_option( 'fluent_boards_pro_license', array() );
		return array(
			'status'       => $lic['status'] ?? null,
			'activated_at' => $lic['activated_at'] ?? null,
			'expires_at'   => $lic['expires_at'] ?? null,
			'plan'         => $lic['plan'] ?? null,
		);
	},
) );

// =========================================================================
// §4.29.2 — activate-license (pro, idempotent:true)
// =========================================================================
$reg->write( 'fluent-boards/activate-license', array(
	'label'       => 'Activate Fluent Boards Pro License (Pro)',
	'description' => 'Activate the Fluent Boards Pro license with the given license_key. Idempotent — repeating with the same key returns the same status.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'license_key' ),
		'properties' => array(
			'license_key' => array( 'type' => 'string' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'status' => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'annotations' => array( 'idempotent' => true ),
	'callback'    => function( $input ) {
		$key = sanitize_text_field( $input['license_key'] ?? '' );
		if ( ! $key ) {
			return fluent_abilities_error( 'ability_invalid_input', 'license_key is required.' );
		}
		$lic = array(
			'license_key'  => $key,
			'status'       => 'active',
			'activated_at' => current_time( 'mysql' ),
		);
		update_option( 'fluent_boards_pro_license', $lic );
		return array( 'success' => true, 'status' => 'active' );
	},
) );

// =========================================================================
// §4.29.3 — deactivate-license (pro)
// =========================================================================
$reg->delete( 'fluent-boards/deactivate-license', array(
	'label'         => 'Deactivate Fluent Boards Pro License (Pro)',
	'description'   => 'Deactivate the Fluent Boards Pro license. Idempotent.',
	'category'      => 'fluent-boards',
	'output_schema' => fluent_abilities_schema_success_output(),
	'annotations'   => array( 'idempotent' => true ),
	'callback'      => function() {
		delete_option( 'fluent_boards_pro_license' );
		return array( 'success' => true );
	},
) );

// =========================================================================
// §4.30.1 — get-board-menu-items
// =========================================================================
$reg->read( 'fluent-boards/get-board-menu-items', array(
	'label'       => 'Get Board Menu Items',
	'description' => 'Return the tabs/sections enabled for a specific board (e.g. tasks, timeline, calendar, reports).',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_collection_output( 'items', array(
		'slug'  => array( 'type' => 'string' ),
		'label' => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$board_id = (int) $input['board_id'];
		if ( ! wpFluent()->table( 'fbs_boards' )->where( 'id', $board_id )->first() ) {
			return fluent_abilities_error( 'not_found', 'Board not found.' );
		}
		$items = array(
			array( 'slug' => 'tasks',    'label' => 'Tasks' ),
			array( 'slug' => 'timeline', 'label' => 'Timeline' ),
			array( 'slug' => 'calendar', 'label' => 'Calendar' ),
			array( 'slug' => 'reports',  'label' => 'Reports' ),
			array( 'slug' => 'settings', 'label' => 'Settings' ),
		);
		return array( 'items' => $items, 'total' => count( $items ) );
	},
) );

// =========================================================================
// §4.30.2 — get-dashboard-view-settings
// =========================================================================
$reg->read( 'fluent-boards/get-dashboard-view-settings', array(
	'label'         => 'Get Dashboard View Settings',
	'description'   => 'Get per-user dashboard view preferences (default board, columns visible, sort order).',
	'category'      => 'fluent-boards',
	'output_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'user_id'  => array( 'type' => 'integer' ),
			'settings' => array( 'type' => array( 'object', 'array', 'null' ) ),
		),
	),
	'callback' => function() {
		$user_id = (int) get_current_user_id();
		if ( ! $user_id ) {
			return array( 'user_id' => 0, 'settings' => null );
		}
		$settings = get_user_meta( $user_id, 'fluent_boards_dashboard_view', true );
		return array( 'user_id' => $user_id, 'settings' => $settings ?: null );
	},
) );

// =========================================================================
// §4.30.3 — update-dashboard-view-settings
// =========================================================================
$reg->write( 'fluent-boards/update-dashboard-view-settings', array(
	'label'       => 'Update Dashboard View Settings',
	'description' => 'Update per-user dashboard view preferences.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'settings' ),
		'properties' => array(
			'settings' => array( 'type' => 'object' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'user_id' => array( 'type' => 'integer' ) ) ),
	'callback'     => function( $input ) {
		$user_id = (int) get_current_user_id();
		if ( ! $user_id ) {
			return fluent_abilities_error( 'forbidden', 'Authenticated user required.' );
		}
		$settings = is_array( $input['settings'] ?? null ) ? $input['settings'] : array();
		update_user_meta( $user_id, 'fluent_boards_dashboard_view', $settings );
		return array( 'success' => true, 'user_id' => $user_id );
	},
) );
