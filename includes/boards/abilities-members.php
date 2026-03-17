<?php
/**
 * Fluent Boards — Board Member Abilities
 *
 * List, add, and remove board members.
 * 3 abilities in the 'fluent-boards' category.
 *
 * Board-user relations are stored in fbs_relations (object_type='board_user').
 * object_id = board_id, foreign_id = user_id.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

// This file is loaded inside the wp_abilities_api_init callback in abilities.php.
// $reg (Fluent_Abilities_Registrar) is already available in scope.

// =========================================================================
// LIST BOARD MEMBERS
// =========================================================================

$reg->read( 'fluent-boards/list-board-members', array(
	'label'       => 'List Board Members',
	'description' => 'List all members of a board with their roles.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer', 'description' => 'Board ID (required)' ),
		),
	),
	'output_schema' => fluent_abilities_schema_collection_output( 'members', array(
		'user_id'      => array( 'type' => 'integer' ),
		'display_name' => array( 'type' => array( 'string', 'null' ) ),
		'email'        => array( 'type' => array( 'string', 'null' ) ),
		'is_admin'     => array( 'type' => 'boolean' ),
		'is_viewer'    => array( 'type' => 'boolean' ),
	) ),
	'callback' => function( $input ) {
		$board_id = (int) $input['board_id'];

		$board = wpFluent()->table( 'fbs_boards' )->where( 'id', $board_id )->first();
		if ( ! $board ) {
			return fluent_abilities_error( 'not_found', 'Board not found' );
		}

		$relations = wpFluent()->table( 'fbs_relations' )
			->where( 'object_id', $board_id )
			->where( 'object_type', 'board_user' )
			->get();

		$items = array();
		foreach ( $relations as $rel ) {
			$user_id  = (int) $rel->foreign_id;
			$user     = get_userdata( $user_id );
			$settings = maybe_unserialize( $rel->settings ?? '' );
			if ( ! is_array( $settings ) ) {
				$settings = array();
			}

			$items[] = array(
				'user_id'      => $user_id,
				'display_name' => $user ? $user->display_name : null,
				'email'        => $user ? $user->user_email : null,
				'is_admin'     => ! empty( $settings['is_admin'] ),
				'is_viewer'    => ! empty( $settings['is_viewer_only'] ),
			);
		}

		return array(
			'members'  => $items,
			'total'    => count( $items ),
			'board_id' => $board_id,
		);
	},
) );

// =========================================================================
// ADD BOARD MEMBER
// =========================================================================

$reg->write( 'fluent-boards/add-board-member', array(
	'label'       => 'Add Board Member',
	'description' => 'Add a WordPress user as a member of a board.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'user_id' ),
		'properties' => array(
			'board_id'       => array( 'type' => 'integer', 'description' => 'Board ID (required)' ),
			'user_id'        => array( 'type' => 'integer', 'description' => 'WordPress user ID to add (required)' ),
			'is_viewer_only' => array( 'type' => 'boolean', 'description' => 'If true, user is added as viewer only (default: false)' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'board_id' => array( 'type' => 'integer' ),
		'user_id'  => array( 'type' => 'integer' ),
	) ),
	'annotations' => array( 'idempotent' => true ),
	'callback' => function( $input ) {
		$board_id = (int) $input['board_id'];
		$user_id  = (int) $input['user_id'];

		$board = wpFluent()->table( 'fbs_boards' )->where( 'id', $board_id )->first();
		if ( ! $board ) {
			return fluent_abilities_error( 'not_found', 'Board not found' );
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return fluent_abilities_error( 'not_found', 'User not found' );
		}

		// Check if already a member.
		$already = wpFluent()->table( 'fbs_relations' )
			->where( 'object_id', $board_id )
			->where( 'object_type', 'board_user' )
			->where( 'foreign_id', $user_id )
			->first();

		if ( $already ) {
			return array( 'success' => true, 'board_id' => $board_id, 'user_id' => $user_id, 'message' => 'Already a member' );
		}

		$is_viewer = ! empty( $input['is_viewer_only'] );
		$settings  = $is_viewer
			? array( 'is_admin' => false, 'is_viewer_only' => true )
			: array( 'is_admin' => false );

		$now = current_time( 'mysql' );
		wpFluent()->table( 'fbs_relations' )->insert( array(
			'object_id'   => $board_id,
			'object_type' => 'board_user',
			'foreign_id'  => $user_id,
			'settings'    => maybe_serialize( $settings ),
			'preferences' => maybe_serialize( array( 'board_email_task_assign', 'board_email_comment', 'board_email_task_completed', 'board_email_due_date' ) ),
			'created_at'  => $now,
			'updated_at'  => $now,
		) );

		return array( 'success' => true, 'board_id' => $board_id, 'user_id' => $user_id );
	},
) );

// =========================================================================
// REMOVE BOARD MEMBER
// =========================================================================

$reg->delete( 'fluent-boards/remove-board-member', array(
	'label'       => 'Remove Board Member',
	'description' => 'Remove a user from a board. Also removes their task assignments and watches on that board.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'user_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer', 'description' => 'Board ID (required)' ),
			'user_id'  => array( 'type' => 'integer', 'description' => 'WordPress user ID to remove (required)' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'board_id' => array( 'type' => 'integer' ),
		'user_id'  => array( 'type' => 'integer' ),
	) ),
	'callback' => function( $input ) {
		$board_id = (int) $input['board_id'];
		$user_id  = (int) $input['user_id'];

		$board = wpFluent()->table( 'fbs_boards' )->where( 'id', $board_id )->first();
		if ( ! $board ) {
			return fluent_abilities_error( 'not_found', 'Board not found' );
		}

		// Remove board membership.
		wpFluent()->table( 'fbs_relations' )
			->where( 'object_id', $board_id )
			->where( 'object_type', 'board_user' )
			->where( 'foreign_id', $user_id )
			->delete();

		// Remove notification relations.
		wpFluent()->table( 'fbs_relations' )
			->where( 'object_id', $board_id )
			->whereIn( 'object_type', array( 'board_user_notification', 'board_user_email' ) )
			->where( 'foreign_id', $user_id )
			->delete();

		// Remove task assignments and watches for this user on this board's tasks.
		$task_ids_raw = wpFluent()->table( 'fbs_tasks' )
			->where( 'board_id', $board_id )
			->select( 'id' )
			->get();
		$task_ids = array_map( function( $t ) { return (int) $t->id; }, $task_ids_raw );

		if ( ! empty( $task_ids ) ) {
			wpFluent()->table( 'fbs_relations' )
				->whereIn( 'object_id', $task_ids )
				->whereIn( 'object_type', array( 'task_assignee', 'task_user_watch' ) )
				->where( 'foreign_id', $user_id )
				->delete();
		}

		return array( 'success' => true, 'board_id' => $board_id, 'user_id' => $user_id );
	},
) );
