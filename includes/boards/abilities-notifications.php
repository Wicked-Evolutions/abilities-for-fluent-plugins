<?php
/**
 * Fluent Boards — Notifications + Notification Settings (Research §4.9 + §4.10)
 *
 * §4.9 Notifications: per-user                 — 5 abilities (free)
 * §4.10 Notification settings                  — 4 abilities (free)
 * Total: 9 abilities.
 *
 * Notifications stored in fbs_notifications. Board-level notification settings
 * stored in fbs_relations.preferences (per board_user). User-level global
 * settings stored in fbs_metas (object_type=user_notification, object_id=user_id).
 *
 * §7.Q2 — notification-settings sub-schema is structured as a free-form object
 * here; specific keys can be tightened once vendor Constant.php is sourced.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

// =========================================================================
// §4.9.1 — get-notification-count
// =========================================================================
$reg->read( 'fluent-boards/get-notification-count', array(
	'label'         => 'Get Notification Count',
	'description'   => 'Return unread notification count for the current user.',
	'category'      => 'fluent-boards',
	'output_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'unread'  => array( 'type' => 'integer' ),
			'user_id' => array( 'type' => 'integer' ),
		),
	),
	'callback' => function() {
		$user_id = (int) get_current_user_id();
		if ( ! $user_id ) {
			return array( 'unread' => 0, 'user_id' => 0 );
		}
		$count = (int) wpFluent()->table( 'fbs_notifications' )->where( 'user_id', $user_id )->where( 'is_read', 0 )->count();
		return array( 'unread' => $count, 'user_id' => $user_id );
	},
) );

// =========================================================================
// §4.9.2 — list-unread-notifications
// =========================================================================
$reg->read( 'fluent-boards/list-unread-notifications', array(
	'label'       => 'List Unread Notifications',
	'description' => 'List unread notifications for the current user.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'properties' => fluent_abilities_pagination_schema(),
	),
	'output_schema' => fluent_abilities_schema_list_output( 'notifications', array(
		'id'           => array( 'type' => 'integer' ),
		'action'       => array( 'type' => array( 'string', 'null' ) ),
		'message'      => array( 'type' => array( 'string', 'null' ) ),
		'object_id'    => array( 'type' => array( 'integer', 'null' ) ),
		'object_type'  => array( 'type' => array( 'string', 'null' ) ),
		'created_at'   => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$user_id    = (int) get_current_user_id();
		$pagination = fluent_abilities_pagination( $input, 25 );
		if ( ! $user_id ) {
			return array( 'notifications' => array(), 'total' => 0, 'page' => $pagination['page'], 'per_page' => $pagination['per_page'] );
		}
		$query = wpFluent()->table( 'fbs_notifications' )->where( 'user_id', $user_id )->where( 'is_read', 0 )->orderBy( 'id', 'DESC' );
		$total = (int) $query->count();
		$rows  = $query->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();
		$items = array();
		foreach ( $rows as $n ) {
			$items[] = array(
				'id'          => (int) $n->id,
				'action'      => $n->action ?? null,
				'message'     => $n->message ?? null,
				'object_id'   => $n->object_id ? (int) $n->object_id : null,
				'object_type' => $n->object_type ?? null,
				'created_at'  => $n->created_at ?? null,
			);
		}
		return array( 'notifications' => $items, 'total' => $total, 'page' => $pagination['page'], 'per_page' => $pagination['per_page'] );
	},
) );

// =========================================================================
// §4.9.3 — mark-notification-as-read
// =========================================================================
$reg->write( 'fluent-boards/mark-notification-as-read', array(
	'label'       => 'Mark Notification As Read',
	'description' => 'Mark a single notification as read for the current user. Idempotent.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'notification_id' ),
		'properties' => array(
			'notification_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'notification_id' => array( 'type' => 'integer' ) ) ),
	'annotations'  => array( 'idempotent' => true ),
	'callback'     => function( $input ) {
		$nid     = (int) $input['notification_id'];
		$user_id = (int) get_current_user_id();
		if ( ! $user_id ) {
			return fluent_abilities_error( 'forbidden', 'Authenticated user required.' );
		}
		wpFluent()->table( 'fbs_notifications' )->where( 'id', $nid )->where( 'user_id', $user_id )->update( array(
			'is_read'    => 1,
			'updated_at' => current_time( 'mysql' ),
		) );
		return array( 'success' => true, 'notification_id' => $nid );
	},
) );

// =========================================================================
// §4.9.4 — mark-all-notifications-as-read
// =========================================================================
$reg->write( 'fluent-boards/mark-all-notifications-as-read', array(
	'label'         => 'Mark All Notifications As Read',
	'description'   => 'Mark every unread notification as read for the current user. Idempotent.',
	'category'      => 'fluent-boards',
	'output_schema' => fluent_abilities_schema_success_output( array(
		'updated' => array( 'type' => 'integer' ),
		'user_id' => array( 'type' => 'integer' ),
	) ),
	'annotations'   => array( 'idempotent' => true ),
	'callback'      => function() {
		$user_id = (int) get_current_user_id();
		if ( ! $user_id ) {
			return fluent_abilities_error( 'forbidden', 'Authenticated user required.' );
		}
		$count = (int) wpFluent()->table( 'fbs_notifications' )->where( 'user_id', $user_id )->where( 'is_read', 0 )->update( array(
			'is_read'    => 1,
			'updated_at' => current_time( 'mysql' ),
		) );
		return array( 'success' => true, 'updated' => $count, 'user_id' => $user_id );
	},
) );

// =========================================================================
// §4.9.5 — delete-notification
// =========================================================================
$reg->delete( 'fluent-boards/delete-notification', array(
	'label'       => 'Delete Notification',
	'description' => 'Permanently delete a notification for the current user.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'notification_id' ),
		'properties' => array(
			'notification_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'notification_id' => array( 'type' => 'integer' ) ) ),
	'callback'     => function( $input ) {
		$nid     = (int) $input['notification_id'];
		$user_id = (int) get_current_user_id();
		if ( ! $user_id ) {
			return fluent_abilities_error( 'forbidden', 'Authenticated user required.' );
		}
		wpFluent()->table( 'fbs_notifications' )->where( 'id', $nid )->where( 'user_id', $user_id )->delete();
		return array( 'success' => true, 'notification_id' => $nid );
	},
) );

// =========================================================================
// §4.10.1 — get-board-notification-settings
// =========================================================================
$reg->read( 'fluent-boards/get-board-notification-settings', array(
	'label'         => 'Get Board Notification Settings',
	'description'   => 'Get per-board notification preferences for the current user (or user_id). Stored as fbs_relations.preferences for the (board_user, board_id, user_id) tuple.',
	'category'      => 'fluent-boards',
	'input_schema'  => array(
		'type'       => 'object',
		'required'   => array( 'board_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
			'user_id'  => array( 'type' => 'integer', 'description' => 'Defaults to current WordPress user.' ),
		),
	),
	'output_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'board_id'    => array( 'type' => 'integer' ),
			'user_id'     => array( 'type' => 'integer' ),
			'preferences' => array( 'type' => array( 'array', 'object', 'null' ) ),
		),
	),
	'callback' => function( $input ) {
		$board_id = (int) $input['board_id'];
		$user_id  = ! empty( $input['user_id'] ) ? (int) $input['user_id'] : (int) get_current_user_id();
		if ( ! $user_id ) {
			return fluent_abilities_error( 'ability_invalid_input', 'user_id required.' );
		}
		$rel = wpFluent()->table( 'fbs_relations' )->where( 'object_type', 'board_user' )->where( 'object_id', $board_id )->where( 'foreign_id', $user_id )->first();
		if ( ! $rel ) {
			return fluent_abilities_error( 'not_found', 'User is not a member of this board.' );
		}
		$prefs = maybe_unserialize( $rel->preferences ?? '' );
		return array( 'board_id' => $board_id, 'user_id' => $user_id, 'preferences' => $prefs );
	},
) );

// =========================================================================
// §4.10.2 — save-board-notification-settings
// =========================================================================
$reg->write( 'fluent-boards/save-board-notification-settings', array(
	'label'       => 'Save Board Notification Settings',
	'description' => 'Replace the current user\'s notification preferences on a board. Pass settings.preferences as an array of notification keys to enable (e.g. ["board_email_task_assign", "board_email_comment"]).',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'settings' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
			'user_id'  => array( 'type' => 'integer' ),
			'settings' => array(
				'type'       => 'object',
				'properties' => array(
					'preferences' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					),
				),
			),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'board_id' => array( 'type' => 'integer' ),
		'user_id'  => array( 'type' => 'integer' ),
	) ),
	'callback' => function( $input ) {
		$board_id = (int) $input['board_id'];
		$user_id  = ! empty( $input['user_id'] ) ? (int) $input['user_id'] : (int) get_current_user_id();
		$prefs    = is_array( $input['settings']['preferences'] ?? null ) ? $input['settings']['preferences'] : array();
		if ( ! $user_id ) {
			return fluent_abilities_error( 'ability_invalid_input', 'user_id required.' );
		}
		$rel = wpFluent()->table( 'fbs_relations' )->where( 'object_type', 'board_user' )->where( 'object_id', $board_id )->where( 'foreign_id', $user_id )->first();
		if ( ! $rel ) {
			return fluent_abilities_error( 'not_found', 'User is not a member of this board.' );
		}
		wpFluent()->table( 'fbs_relations' )->where( 'id', $rel->id )->update( array(
			'preferences' => maybe_serialize( array_values( array_map( 'sanitize_key', $prefs ) ) ),
			'updated_at'  => current_time( 'mysql' ),
		) );
		return array( 'success' => true, 'board_id' => $board_id, 'user_id' => $user_id );
	},
) );

// =========================================================================
// §4.10.3 — get-user-notification-settings
// =========================================================================
$reg->read( 'fluent-boards/get-user-notification-settings', array(
	'label'         => 'Get User Notification Settings',
	'description'   => 'Get global notification preferences for the current user (or user_id). Stored in fbs_metas with object_type=user_notification.',
	'category'      => 'fluent-boards',
	'input_schema'  => array(
		'type'       => 'object',
		'properties' => array(
			'user_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'user_id'  => array( 'type' => 'integer' ),
			'settings' => array( 'type' => array( 'object', 'null' ) ),
		),
	),
	'callback' => function( $input ) {
		$user_id = ! empty( $input['user_id'] ) ? (int) $input['user_id'] : (int) get_current_user_id();
		if ( ! $user_id ) {
			return fluent_abilities_error( 'ability_invalid_input', 'user_id required.' );
		}
		$row = wpFluent()->table( 'fbs_metas' )->where( 'object_type', 'user_notification' )->where( 'object_id', $user_id )->first();
		if ( ! $row ) {
			return array( 'user_id' => $user_id, 'settings' => null );
		}
		$settings = maybe_unserialize( $row->value ?? '' );
		return array( 'user_id' => $user_id, 'settings' => $settings );
	},
) );

// =========================================================================
// §4.10.4 — save-user-notification-settings
// =========================================================================
$reg->write( 'fluent-boards/save-user-notification-settings', array(
	'label'       => 'Save User Notification Settings',
	'description' => 'Save the current user\'s global notification preferences (replaces whole settings object).',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'settings' ),
		'properties' => array(
			'user_id'  => array( 'type' => 'integer' ),
			'settings' => array( 'type' => 'object' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'user_id' => array( 'type' => 'integer' ) ) ),
	'callback'     => function( $input ) {
		$user_id  = ! empty( $input['user_id'] ) ? (int) $input['user_id'] : (int) get_current_user_id();
		$settings = is_array( $input['settings'] ?? null ) ? $input['settings'] : array();
		if ( ! $user_id ) {
			return fluent_abilities_error( 'ability_invalid_input', 'user_id required.' );
		}
		$now = current_time( 'mysql' );
		$row = wpFluent()->table( 'fbs_metas' )->where( 'object_type', 'user_notification' )->where( 'object_id', $user_id )->first();
		if ( $row ) {
			wpFluent()->table( 'fbs_metas' )->where( 'id', $row->id )->update( array(
				'value'      => maybe_serialize( $settings ),
				'updated_at' => $now,
			) );
		} else {
			wpFluent()->table( 'fbs_metas' )->insert( array(
				'object_id'   => $user_id,
				'object_type' => 'user_notification',
				'key'         => 'preferences',
				'value'       => maybe_serialize( $settings ),
				'created_at'  => $now,
				'updated_at'  => $now,
			) );
		}
		return array( 'success' => true, 'user_id' => $user_id );
	},
) );
