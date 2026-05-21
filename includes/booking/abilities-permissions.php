<?php
/**
 * FluentBooking — PermissionManager grants (cluster 4.9).
 *
 * Per-user FluentBooking permission grants. Storage: fcal_meta with
 * object_type='user_meta', object_id=$user_id, key='_access_permissions',
 * value=[<permission slug>, ...].
 *
 * All abilities in this cluster require WordPress `manage_options` capability
 * (super-admin only) — they manage operator authorization itself, which is a
 * privilege-escalation surface.
 *
 *   - fluent-booking/list-permission-sets         (read — full slug => label map)
 *   - fluent-booking/get-user-permissions         (read — specific user)
 *   - fluent-booking/get-current-user-permissions (read — current user)
 *   - fluent-booking/set-user-permissions         (write — replace grants list)
 *   - fluent-booking/revoke-user-permissions      (delete — drop all grants for user)
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

function fluent_booking_register_permissions_abilities() {

	$reg = new Fluent_Abilities_Registrar( 'booking' );

	// =========================================================================
	// 4.9.1 — LIST PERMISSION SETS
	// =========================================================================

	$reg->read( 'fluent-booking/list-permission-sets', array(
		'label'       => 'List FluentBooking Permission Sets',
		'description' => 'Return the 8-key FluentBooking permission slug-to-label map (PermissionManager::allPermissionSets).',
		'capability'  => 'manage_options',
		'output_schema' => fluent_abilities_schema_item_output( array(
			'permission_sets' => array( 'type' => 'object', 'description' => 'Map of permission slug => human-readable label' ),
			'total'           => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Services\PermissionManager' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking PermissionManager not found' );
			}

			$sets = \FluentBooking\App\Services\PermissionManager::allPermissionSets();

			return array(
				'permission_sets' => (object) ( is_array( $sets ) ? $sets : array() ),
				'total'           => is_array( $sets ) ? count( $sets ) : 0,
			);
		},
	) );

	// =========================================================================
	// 4.9.2 — GET USER PERMISSIONS
	// =========================================================================

	$reg->read( 'fluent-booking/get-user-permissions', array(
		'label'       => 'Get User FluentBooking Permissions',
		'description' => 'Return a user\'s stored FluentBooking permissions plus their formatted labels and super-admin status.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'user_id' => array( 'type' => 'integer', 'description' => 'User ID (defaults to current user if omitted)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'user_id'        => array( 'type' => 'integer' ),
			'permissions'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'is_super_admin' => array( 'type' => 'boolean' ),
			'formatted'      => array( 'type' => array( 'object', 'array' ), 'description' => 'Map of granted permission slug => label' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Services\PermissionManager' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking PermissionManager not found' );
			}

			$user_id = isset( $input['user_id'] ) ? (int) $input['user_id'] : get_current_user_id();
			$user    = get_user_by( 'ID', $user_id );
			if ( ! $user ) {
				return fluent_abilities_error( 'user_not_found', 'User not found' );
			}

			$data = \FluentBooking\App\Services\PermissionManager::getUserPermissions( $user, true );

			return array(
				'user_id'        => $user_id,
				'permissions'    => isset( $data['permissions'] ) && is_array( $data['permissions'] ) ? array_values( $data['permissions'] ) : array(),
				'is_super_admin' => ! empty( $data['is_super_admin'] ),
				'formatted'      => isset( $data['formatted'] ) ? (object) $data['formatted'] : (object) array(),
			);
		},
	) );

	// =========================================================================
	// 4.9.3 — GET CURRENT USER PERMISSIONS
	// =========================================================================

	$reg->read( 'fluent-booking/get-current-user-permissions', array(
		'label'       => 'Get Current User FluentBooking Permissions',
		'description' => 'Return the current user\'s FluentBooking permissions, formatted labels, and super-admin status.',
		'capability'  => 'manage_options',
		'output_schema' => fluent_abilities_schema_item_output( array(
			'user_id'        => array( 'type' => 'integer' ),
			'permissions'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'is_super_admin' => array( 'type' => 'boolean' ),
			'formatted'      => array( 'type' => array( 'object', 'array' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Services\PermissionManager' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking PermissionManager not found' );
			}

			$user_id = get_current_user_id();
			$data    = \FluentBooking\App\Services\PermissionManager::getUserPermissions();

			return array(
				'user_id'        => $user_id,
				'permissions'    => isset( $data['permissions'] ) && is_array( $data['permissions'] ) ? array_values( $data['permissions'] ) : array(),
				'is_super_admin' => ! empty( $data['is_super_admin'] ),
				'formatted'      => isset( $data['formatted'] ) ? (object) $data['formatted'] : (object) array(),
			);
		},
	) );

	// =========================================================================
	// 4.9.4 — SET USER PERMISSIONS
	// =========================================================================

	$reg->write( 'fluent-booking/set-user-permissions', array(
		'label'       => 'Set User FluentBooking Permissions',
		'description' => 'Replace the FluentBooking permissions list for a user. All slugs must be valid FluentBooking permission keys (see fluent-booking/list-permission-sets).',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'user_id', 'permissions' ),
			'properties' => array(
				'user_id'     => array( 'type' => 'integer', 'description' => 'Target user ID' ),
				'permissions' => array(
					'type'        => 'array',
					'description' => 'Array of FluentBooking permission slugs to grant',
					'items'       => array( 'type' => 'string' ),
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'user_id'     => array( 'type' => 'integer' ),
			'permissions' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Services\PermissionManager' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking PermissionManager not found' );
			}

			$user_id = (int) $input['user_id'];
			$user    = get_user_by( 'ID', $user_id );
			if ( ! $user ) {
				return fluent_abilities_error( 'user_not_found', 'User not found' );
			}

			$requested = isset( $input['permissions'] ) ? (array) $input['permissions'] : array();
			$requested = array_values( array_filter( array_map( 'sanitize_text_field', $requested ) ) );

			$valid_sets = \FluentBooking\App\Services\PermissionManager::allPermissionSets();
			$valid_keys = is_array( $valid_sets ) ? array_keys( $valid_sets ) : array();

			$invalid = array_diff( $requested, $valid_keys );
			if ( ! empty( $invalid ) ) {
				return fluent_abilities_error(
					'ability_invalid_input',
					'Invalid permission slug(s): ' . implode( ', ', $invalid )
				);
			}

			$existing = wpFluent()->table( 'fcal_meta' )
				->where( 'object_type', 'user_meta' )
				->where( 'object_id', $user_id )
				->where( 'key', '_access_permissions' )
				->first();

			$serialized = maybe_serialize( $requested );
			if ( $existing ) {
				wpFluent()->table( 'fcal_meta' )
					->where( 'id', $existing->id )
					->update( array(
						'value'      => $serialized,
						'updated_at' => current_time( 'mysql' ),
					) );
			} else {
				wpFluent()->table( 'fcal_meta' )
					->insert( array(
						'object_type' => 'user_meta',
						'object_id'   => $user_id,
						'key'         => '_access_permissions',
						'value'       => $serialized,
						'created_at'  => current_time( 'mysql' ),
						'updated_at'  => current_time( 'mysql' ),
					) );
			}

			return array(
				'success'     => true,
				'user_id'     => $user_id,
				'permissions' => $requested,
			);
		},
	) );

	// =========================================================================
	// 4.9.5 — REVOKE USER PERMISSIONS
	// =========================================================================

	$reg->delete( 'fluent-booking/revoke-user-permissions', array(
		'label'       => 'Revoke User FluentBooking Permissions',
		'description' => 'Remove ALL FluentBooking permission grants for a user (deletes the _access_permissions meta row).',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'user_id' ),
			'properties' => array(
				'user_id' => array( 'type' => 'integer', 'description' => 'Target user ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'user_id' => array( 'type' => 'integer' ),
			'deleted' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$user_id = (int) $input['user_id'];
			if ( $user_id <= 0 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'user_id is required' );
			}

			$deleted = wpFluent()->table( 'fcal_meta' )
				->where( 'object_type', 'user_meta' )
				->where( 'object_id', $user_id )
				->where( 'key', '_access_permissions' )
				->delete();

			return array(
				'success' => true,
				'user_id' => $user_id,
				'deleted' => (int) $deleted,
			);
		},
	) );

}
add_action( 'wp_abilities_api_init', 'fluent_booking_register_permissions_abilities' );
