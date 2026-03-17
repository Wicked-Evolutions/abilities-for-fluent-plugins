<?php
/**
 * Fluent Boards — Activity Log Abilities
 *
 * Board and task activity logs.
 * 2 abilities in the 'fluent-boards' category.
 *
 * Activities are stored in fbs_activities.
 * object_type: 'board_activity' (board-level), 'task_activity' (task-level).
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

// This file is loaded inside the wp_abilities_api_init callback in abilities.php.
// $reg (Fluent_Abilities_Registrar) is already available in scope.

// =========================================================================
// LIST BOARD ACTIVITIES
// =========================================================================

$reg->read( 'fluent-boards/list-board-activities', array(
	'label'       => 'List Board Activities',
	'description' => 'List the activity log for a board with pagination.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id' ),
		'properties' => array_merge( array(
			'board_id' => array( 'type' => 'integer', 'description' => 'Board ID (required)' ),
		), fluent_abilities_pagination_schema() ),
	),
	'output_schema' => fluent_abilities_schema_list_output( 'activities', array(
		'id'          => array( 'type' => 'integer' ),
		'action'      => array( 'type' => array( 'string', 'null' ) ),
		'column'      => array( 'type' => array( 'string', 'null' ) ),
		'old_value'   => array( 'type' => array( 'string', 'null' ) ),
		'new_value'   => array( 'type' => array( 'string', 'null' ) ),
		'description' => array( 'type' => array( 'string', 'null' ) ),
		'created_by'  => array( 'type' => array( 'integer', 'null' ) ),
		'author_name' => array( 'type' => array( 'string', 'null' ) ),
		'created_at'  => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$board_id   = (int) $input['board_id'];
		$pagination = fluent_abilities_pagination( $input, 40 );

		$board = wpFluent()->table( 'fbs_boards' )->where( 'id', $board_id )->first();
		if ( ! $board ) {
			return fluent_abilities_error( 'not_found', 'Board not found' );
		}

		$query = wpFluent()->table( 'fbs_activities' )
			->where( 'object_id', $board_id )
			->where( 'object_type', 'board_activity' );

		$total = $query->count();

		$activities = $query
			->orderBy( 'id', 'DESC' )
			->offset( $pagination['offset'] )
			->limit( $pagination['per_page'] )
			->get();

		$items = array();
		foreach ( $activities as $act ) {
			$user = $act->created_by ? get_userdata( (int) $act->created_by ) : null;

			$items[] = array(
				'id'           => (int) $act->id,
				'action'       => $act->action ?? '',
				'column'       => $act->column ?? null,
				'old_value'    => $act->old_value ?? null,
				'new_value'    => $act->new_value ?? null,
				'description'  => $act->description ?? null,
				'created_by'   => $act->created_by ? (int) $act->created_by : null,
				'author_name'  => $user ? $user->display_name : null,
				'created_at'   => $act->created_at ? (string) $act->created_at : null,
			);
		}

		return array(
			'activities' => $items,
			'total'      => $total,
			'page'       => $pagination['page'],
			'per_page'   => $pagination['per_page'],
		);
	},
) );

// =========================================================================
// LIST TASK ACTIVITIES
// =========================================================================

$reg->read( 'fluent-boards/list-task-activities', array(
	'label'       => 'List Task Activities',
	'description' => 'List the activity log for a specific task with pagination.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'task_id' ),
		'properties' => array_merge( array(
			'task_id' => array( 'type' => 'integer', 'description' => 'Task ID (required)' ),
		), fluent_abilities_pagination_schema() ),
	),
	'output_schema' => fluent_abilities_schema_list_output( 'activities', array(
		'id'          => array( 'type' => 'integer' ),
		'action'      => array( 'type' => array( 'string', 'null' ) ),
		'column'      => array( 'type' => array( 'string', 'null' ) ),
		'old_value'   => array( 'type' => array( 'string', 'null' ) ),
		'new_value'   => array( 'type' => array( 'string', 'null' ) ),
		'description' => array( 'type' => array( 'string', 'null' ) ),
		'created_by'  => array( 'type' => array( 'integer', 'null' ) ),
		'author_name' => array( 'type' => array( 'string', 'null' ) ),
		'created_at'  => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$task_id    = (int) $input['task_id'];
		$pagination = fluent_abilities_pagination( $input, 40 );

		$task = wpFluent()->table( 'fbs_tasks' )->where( 'id', $task_id )->first();
		if ( ! $task ) {
			return fluent_abilities_error( 'not_found', 'Task not found' );
		}

		$query = wpFluent()->table( 'fbs_activities' )
			->where( 'object_id', $task_id )
			->where( 'object_type', 'task_activity' );

		$total = $query->count();

		$activities = $query
			->orderBy( 'id', 'DESC' )
			->offset( $pagination['offset'] )
			->limit( $pagination['per_page'] )
			->get();

		$items = array();
		foreach ( $activities as $act ) {
			$user = $act->created_by ? get_userdata( (int) $act->created_by ) : null;

			$items[] = array(
				'id'           => (int) $act->id,
				'action'       => $act->action ?? '',
				'column'       => $act->column ?? null,
				'old_value'    => $act->old_value ?? null,
				'new_value'    => $act->new_value ?? null,
				'description'  => $act->description ?? null,
				'created_by'   => $act->created_by ? (int) $act->created_by : null,
				'author_name'  => $user ? $user->display_name : null,
				'created_at'   => $act->created_at ? (string) $act->created_at : null,
			);
		}

		return array(
			'activities' => $items,
			'total'      => $total,
			'page'       => $pagination['page'],
			'per_page'   => $pagination['per_page'],
		);
	},
) );
