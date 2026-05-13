<?php
/**
 * Fluent Boards — Subtasks + Subtask Groups (Research §4.4)
 *
 * 11 abilities. Tier: pro (requires Fluent Boards Pro for SubtaskController).
 *
 * Subtasks live in fbs_tasks with parent_id = parent_task.id.
 * Subtask groups live in fbs_task_metas with key=SUBTASK_GROUP_NAME (definitions)
 * and key=SUBTASK_GROUP_CHILD (subtask → group_id mapping).
 *
 * KD-6 ([#50]) — §4.4.7 move-subtask-to-board is DESTRUCTIVE: when a subtask is
 *   promoted to a top-level task on a different board, vendor cleans assignees,
 *   labels, watchers, custom fields, comments, and activities. Marked
 *   destructive=true with explicit warning.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

// =========================================================================
// §4.4.1 — list-subtasks
// =========================================================================
$reg->read( 'fluent-boards/list-subtasks', array(
	'label'       => 'List Subtasks',
	'description' => 'List subtasks of a task, grouped by subtask group. Returns {subtaskGroups: [{id, value, task_id, subtasks: [...]}]}.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'task_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
			'task_id'  => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'task_id'       => array( 'type' => 'integer' ),
			'subtaskGroups' => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'id'        => array( 'type' => array( 'integer', 'string' ) ),
						'value'     => array( 'type' => array( 'string', 'null' ) ),
						'task_id'   => array( 'type' => 'integer' ),
						'subtasks'  => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'       => array( 'type' => 'integer' ),
									'title'    => array( 'type' => array( 'string', 'null' ) ),
									'status'   => array( 'type' => array( 'string', 'null' ) ),
									'position' => array( 'type' => array( 'number', 'null' ) ),
								),
							),
						),
					),
				),
			),
		),
	),
	'callback' => function( $input ) {
		$task_id = (int) $input['task_id'];
		// Group definitions.
		$groups = wpFluent()->table( 'fbs_task_metas' )->where( 'task_id', $task_id )->where( 'key', 'subtask_group_name' )->get();
		$by_grp = array();
		foreach ( $groups as $g ) {
			$by_grp[ (int) $g->id ] = array(
				'id'       => (int) $g->id,
				'value'    => $g->value ?? null,
				'task_id'  => $task_id,
				'subtasks' => array(),
			);
		}
		// Subtask → group mapping.
		$child_meta = wpFluent()->table( 'fbs_task_metas' )->where( 'task_id', $task_id )->where( 'key', 'subtask_group_child' )->get();
		$subtask_to_group = array();
		foreach ( $child_meta as $cm ) {
			$payload = maybe_unserialize( $cm->value ?? '' );
			if ( is_array( $payload ) && isset( $payload['subtask_id'], $payload['group_id'] ) ) {
				$subtask_to_group[ (int) $payload['subtask_id'] ] = (int) $payload['group_id'];
			}
		}
		// Actual subtask rows.
		$subtasks = wpFluent()->table( 'fbs_tasks' )->where( 'parent_id', $task_id )->orderBy( 'position', 'ASC' )->get();
		foreach ( $subtasks as $st ) {
			$gid = $subtask_to_group[ (int) $st->id ] ?? 0;
			if ( ! isset( $by_grp[ $gid ] ) ) {
				$by_grp[ $gid ] = array( 'id' => $gid, 'value' => 'Uncategorized', 'task_id' => $task_id, 'subtasks' => array() );
			}
			$by_grp[ $gid ]['subtasks'][] = array(
				'id'       => (int) $st->id,
				'title'    => $st->title ?? '',
				'status'   => $st->status ?? null,
				'position' => $st->position ?? null,
			);
		}
		return array( 'task_id' => $task_id, 'subtaskGroups' => array_values( $by_grp ) );
	},
) );

// =========================================================================
// §4.4.2 — create-subtask (idempotent:false)
// =========================================================================
$reg->write( 'fluent-boards/create-subtask', array(
	'label'       => 'Create Subtask',
	'description' => 'Create a subtask under a parent task within a subtask group.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'task_id', 'title', 'group_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
			'task_id'  => array( 'type' => 'integer' ),
			'title'    => array( 'type' => 'string' ),
			'group_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'subtask_id' => array( 'type' => 'integer' ),
		'task_id'    => array( 'type' => 'integer' ),
	) ),
	'annotations' => array( 'idempotent' => false ),
	'callback'    => function( $input ) {
		$board_id = (int) $input['board_id'];
		$task_id  = (int) $input['task_id'];
		$title    = sanitize_text_field( $input['title'] ?? '' );
		$group_id = (int) ( $input['group_id'] ?? 0 );
		if ( ! $title ) {
			return fluent_abilities_error( 'ability_invalid_input', 'title is required.' );
		}
		$parent = wpFluent()->table( 'fbs_tasks' )->where( 'board_id', $board_id )->where( 'id', $task_id )->first();
		if ( ! $parent ) {
			return fluent_abilities_error( 'not_found', 'Parent task not found on this board.' );
		}
		$now    = current_time( 'mysql' );
		$new_id = wpFluent()->table( 'fbs_tasks' )->insertGetId( array(
			'board_id'   => $board_id,
			'stage_id'   => $parent->stage_id ?? 0,
			'parent_id'  => $task_id,
			'type'       => 'subtask',
			'title'      => $title,
			'status'     => 'open',
			'position'   => 0,
			'created_by' => (int) get_current_user_id(),
			'created_at' => $now,
			'updated_at' => $now,
		) );
		// Record group mapping.
		wpFluent()->table( 'fbs_task_metas' )->insert( array(
			'task_id'    => $task_id,
			'key'        => 'subtask_group_child',
			'value'      => maybe_serialize( array( 'subtask_id' => (int) $new_id, 'group_id' => $group_id ) ),
			'created_at' => $now,
			'updated_at' => $now,
		) );
		return array( 'success' => true, 'subtask_id' => (int) $new_id, 'task_id' => $task_id );
	},
) );

// =========================================================================
// §4.4.3 — delete-subtask (idempotent:false)
// =========================================================================
$reg->delete( 'fluent-boards/delete-subtask', array(
	'label'       => 'Delete Subtask',
	'description' => 'Permanently delete a subtask and its group-mapping meta.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'subtask_id' ),
		'properties' => array(
			'board_id'   => array( 'type' => 'integer' ),
			'subtask_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'subtask_id' => array( 'type' => 'integer' ) ) ),
	'annotations'  => array( 'idempotent' => false ),
	'callback'     => function( $input ) {
		$subtask_id = (int) $input['subtask_id'];
		$subtask    = wpFluent()->table( 'fbs_tasks' )->where( 'id', $subtask_id )->whereNotNull( 'parent_id' )->first();
		if ( ! $subtask ) {
			return fluent_abilities_error( 'not_found', 'Subtask not found.' );
		}
		// Remove group-mapping meta first.
		$metas = wpFluent()->table( 'fbs_task_metas' )->where( 'task_id', $subtask->parent_id )->where( 'key', 'subtask_group_child' )->get();
		foreach ( $metas as $m ) {
			$payload = maybe_unserialize( $m->value ?? '' );
			if ( is_array( $payload ) && (int) ( $payload['subtask_id'] ?? 0 ) === $subtask_id ) {
				wpFluent()->table( 'fbs_task_metas' )->where( 'id', $m->id )->delete();
			}
		}
		wpFluent()->table( 'fbs_tasks' )->where( 'id', $subtask_id )->delete();
		return array( 'success' => true, 'subtask_id' => $subtask_id );
	},
) );

// =========================================================================
// §4.4.4 — clone-subtask (idempotent:false)
// =========================================================================
$reg->write( 'fluent-boards/clone-subtask', array(
	'label'       => 'Clone Subtask',
	'description' => 'Duplicate a subtask under the same parent task and group.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'subtask_id' ),
		'properties' => array(
			'board_id'   => array( 'type' => 'integer' ),
			'subtask_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'subtask_id'     => array( 'type' => 'integer' ),
		'new_subtask_id' => array( 'type' => 'integer' ),
	) ),
	'annotations' => array( 'idempotent' => false ),
	'callback'    => function( $input ) {
		$subtask_id = (int) $input['subtask_id'];
		$src        = wpFluent()->table( 'fbs_tasks' )->where( 'id', $subtask_id )->whereNotNull( 'parent_id' )->first();
		if ( ! $src ) {
			return fluent_abilities_error( 'not_found', 'Subtask not found.' );
		}
		$now    = current_time( 'mysql' );
		$new_id = wpFluent()->table( 'fbs_tasks' )->insertGetId( array(
			'board_id'   => $src->board_id ?? 0,
			'stage_id'   => $src->stage_id ?? 0,
			'parent_id'  => $src->parent_id,
			'type'       => 'subtask',
			'title'      => ( $src->title ?? '' ) . ' (copy)',
			'status'     => $src->status ?? 'open',
			'position'   => $src->position ?? 0,
			'created_by' => (int) get_current_user_id(),
			'created_at' => $now,
			'updated_at' => $now,
		) );
		// Preserve group mapping.
		$gmap = wpFluent()->table( 'fbs_task_metas' )->where( 'task_id', $src->parent_id )->where( 'key', 'subtask_group_child' )->get();
		foreach ( $gmap as $m ) {
			$payload = maybe_unserialize( $m->value ?? '' );
			if ( is_array( $payload ) && (int) ( $payload['subtask_id'] ?? 0 ) === $subtask_id ) {
				wpFluent()->table( 'fbs_task_metas' )->insert( array(
					'task_id'    => $src->parent_id,
					'key'        => 'subtask_group_child',
					'value'      => maybe_serialize( array( 'subtask_id' => (int) $new_id, 'group_id' => (int) ( $payload['group_id'] ?? 0 ) ) ),
					'created_at' => $now,
					'updated_at' => $now,
				) );
			}
		}
		return array( 'success' => true, 'subtask_id' => $subtask_id, 'new_subtask_id' => (int) $new_id );
	},
) );

// =========================================================================
// §4.4.5 — move-subtask-to-group
// =========================================================================
$reg->write( 'fluent-boards/move-subtask-to-group', array(
	'label'       => 'Move Subtask To Group',
	'description' => 'Move a subtask from its current subtask-group to another group on the same parent task.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'task_id', 'subtask_id', 'group_id' ),
		'properties' => array(
			'board_id'   => array( 'type' => 'integer' ),
			'task_id'    => array( 'type' => 'integer' ),
			'subtask_id' => array( 'type' => 'integer' ),
			'group_id'   => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'subtask_id' => array( 'type' => 'integer' ),
		'group_id'   => array( 'type' => 'integer' ),
	) ),
	'callback' => function( $input ) {
		$task_id    = (int) $input['task_id'];
		$subtask_id = (int) $input['subtask_id'];
		$group_id   = (int) $input['group_id'];
		$now        = current_time( 'mysql' );
		$updated    = false;
		$metas      = wpFluent()->table( 'fbs_task_metas' )->where( 'task_id', $task_id )->where( 'key', 'subtask_group_child' )->get();
		foreach ( $metas as $m ) {
			$payload = maybe_unserialize( $m->value ?? '' );
			if ( is_array( $payload ) && (int) ( $payload['subtask_id'] ?? 0 ) === $subtask_id ) {
				wpFluent()->table( 'fbs_task_metas' )->where( 'id', $m->id )->update( array(
					'value'      => maybe_serialize( array( 'subtask_id' => $subtask_id, 'group_id' => $group_id ) ),
					'updated_at' => $now,
				) );
				$updated = true;
			}
		}
		if ( ! $updated ) {
			wpFluent()->table( 'fbs_task_metas' )->insert( array(
				'task_id'    => $task_id,
				'key'        => 'subtask_group_child',
				'value'      => maybe_serialize( array( 'subtask_id' => $subtask_id, 'group_id' => $group_id ) ),
				'created_at' => $now,
				'updated_at' => $now,
			) );
		}
		return array( 'success' => true, 'subtask_id' => $subtask_id, 'group_id' => $group_id );
	},
) );

// =========================================================================
// §4.4.6 — update-subtask-position
// =========================================================================
$reg->write( 'fluent-boards/update-subtask-position', array(
	'label'       => 'Update Subtask Position',
	'description' => 'Update a subtask\'s position and optionally its containing group.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'subtask_id', 'newPosition' ),
		'properties' => array(
			'board_id'             => array( 'type' => 'integer' ),
			'subtask_id'           => array( 'type' => 'integer' ),
			'newPosition'          => array( 'type' => 'number' ),
			'newSubtasksGroupId'   => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'subtask_id' => array( 'type' => 'integer' ) ) ),
	'callback'     => function( $input ) {
		$subtask_id = (int) $input['subtask_id'];
		$position   = $input['newPosition'] ?? 0;
		$subtask    = wpFluent()->table( 'fbs_tasks' )->where( 'id', $subtask_id )->whereNotNull( 'parent_id' )->first();
		if ( ! $subtask ) {
			return fluent_abilities_error( 'not_found', 'Subtask not found.' );
		}
		wpFluent()->table( 'fbs_tasks' )->where( 'id', $subtask_id )->update( array(
			'position'   => $position,
			'updated_at' => current_time( 'mysql' ),
		) );
		if ( isset( $input['newSubtasksGroupId'] ) ) {
			$group_id = (int) $input['newSubtasksGroupId'];
			$now      = current_time( 'mysql' );
			$metas    = wpFluent()->table( 'fbs_task_metas' )->where( 'task_id', $subtask->parent_id )->where( 'key', 'subtask_group_child' )->get();
			$updated  = false;
			foreach ( $metas as $m ) {
				$payload = maybe_unserialize( $m->value ?? '' );
				if ( is_array( $payload ) && (int) ( $payload['subtask_id'] ?? 0 ) === $subtask_id ) {
					wpFluent()->table( 'fbs_task_metas' )->where( 'id', $m->id )->update( array(
						'value'      => maybe_serialize( array( 'subtask_id' => $subtask_id, 'group_id' => $group_id ) ),
						'updated_at' => $now,
					) );
					$updated = true;
				}
			}
			if ( ! $updated ) {
				wpFluent()->table( 'fbs_task_metas' )->insert( array(
					'task_id'    => $subtask->parent_id,
					'key'        => 'subtask_group_child',
					'value'      => maybe_serialize( array( 'subtask_id' => $subtask_id, 'group_id' => $group_id ) ),
					'created_at' => $now,
					'updated_at' => $now,
				) );
			}
		}
		return array( 'success' => true, 'subtask_id' => $subtask_id );
	},
) );

// =========================================================================
// §4.4.7 — move-subtask-to-board (DESTRUCTIVE — KD-6)
// =========================================================================
$reg->write( 'fluent-boards/move-subtask-to-board', array(
	'label'       => 'Move Subtask To Board (DESTRUCTIVE)',
	'description' => 'Promote a subtask to a top-level task on a target board and stage. DESTRUCTIVE: vendor strips assignees, labels, watchers, custom-field values, comments, and activity-log entries from the promoted task because it no longer belongs to the original board\'s relations (per KD-6 [#50]). Cross-board moves cannot be reverted.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'subtask_id', 'stage_id' ),
		'properties' => array(
			'board_id'   => array( 'type' => 'integer', 'description' => 'Target board id.' ),
			'subtask_id' => array( 'type' => 'integer' ),
			'stage_id'   => array( 'type' => 'integer', 'description' => 'Stage on the target board.' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'subtask_id'    => array( 'type' => 'integer' ),
		'new_board_id'  => array( 'type' => 'integer' ),
		'new_task_id'   => array( 'type' => 'integer' ),
	) ),
	'annotations' => array( 'destructive' => true, 'idempotent' => false ),
	'callback'    => function( $input ) {
		$tgt_board = (int) $input['board_id'];
		$subtask_id = (int) $input['subtask_id'];
		$stage_id   = (int) $input['stage_id'];
		$subtask    = wpFluent()->table( 'fbs_tasks' )->where( 'id', $subtask_id )->whereNotNull( 'parent_id' )->first();
		if ( ! $subtask ) {
			return fluent_abilities_error( 'not_found', 'Subtask not found.' );
		}
		$src_parent = (int) $subtask->parent_id;
		// Strip relations + comments + activities (mirrors vendor cleanup).
		wpFluent()->table( 'fbs_comments' )->where( 'task_id', $subtask_id )->delete();
		wpFluent()->table( 'fbs_activities' )->where( 'object_id', $subtask_id )->where( 'object_type', 'task_activity' )->delete();
		wpFluent()->table( 'fbs_relations' )->where( 'object_id', $subtask_id )->whereIn( 'object_type', array( 'task_assignee', 'task_user_watch', 'task_label', 'TASK_CUSTOM_FIELD' ) )->delete();
		wpFluent()->table( 'fbs_task_metas' )->where( 'task_id', $src_parent )->where( 'key', 'subtask_group_child' )->where( 'value', 'LIKE', '%i:' . $subtask_id . ';%' )->delete();
		// Promote: clear parent_id, set new board/stage.
		wpFluent()->table( 'fbs_tasks' )->where( 'id', $subtask_id )->update( array(
			'parent_id'  => null,
			'board_id'   => $tgt_board,
			'stage_id'   => $stage_id,
			'type'       => 'task',
			'updated_at' => current_time( 'mysql' ),
		) );
		return array( 'success' => true, 'subtask_id' => $subtask_id, 'new_board_id' => $tgt_board, 'new_task_id' => $subtask_id );
	},
) );

// =========================================================================
// §4.4.8 — convert-task-to-subtask
// =========================================================================
$reg->write( 'fluent-boards/convert-task-to-subtask', array(
	'label'       => 'Convert Task To Subtask',
	'description' => 'Convert a top-level task into a subtask of parent_id. Optionally place it in a subtask group and reassign assignee.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'task_id', 'parent_id' ),
		'properties' => array(
			'board_id'        => array( 'type' => 'integer' ),
			'task_id'         => array( 'type' => 'integer' ),
			'parent_id'       => array( 'type' => 'integer' ),
			'subtaskGroupId'  => array( 'type' => 'integer' ),
			'assigneeId'      => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'task_id'   => array( 'type' => 'integer' ),
		'parent_id' => array( 'type' => 'integer' ),
	) ),
	'callback' => function( $input ) {
		$task_id   = (int) $input['task_id'];
		$parent_id = (int) $input['parent_id'];
		$task      = wpFluent()->table( 'fbs_tasks' )->where( 'id', $task_id )->first();
		if ( ! $task ) {
			return fluent_abilities_error( 'not_found', 'Task not found.' );
		}
		wpFluent()->table( 'fbs_tasks' )->where( 'id', $task_id )->update( array(
			'parent_id'  => $parent_id,
			'type'       => 'subtask',
			'updated_at' => current_time( 'mysql' ),
		) );
		if ( ! empty( $input['subtaskGroupId'] ) ) {
			$now = current_time( 'mysql' );
			wpFluent()->table( 'fbs_task_metas' )->insert( array(
				'task_id'    => $parent_id,
				'key'        => 'subtask_group_child',
				'value'      => maybe_serialize( array( 'subtask_id' => $task_id, 'group_id' => (int) $input['subtaskGroupId'] ) ),
				'created_at' => $now,
				'updated_at' => $now,
			) );
		}
		if ( ! empty( $input['assigneeId'] ) ) {
			$user_id = (int) $input['assigneeId'];
			$now     = current_time( 'mysql' );
			$has     = wpFluent()->table( 'fbs_relations' )->where( 'object_type', 'task_assignee' )->where( 'object_id', $task_id )->where( 'foreign_id', $user_id )->first();
			if ( ! $has ) {
				wpFluent()->table( 'fbs_relations' )->insert( array(
					'object_id' => $task_id, 'object_type' => 'task_assignee', 'foreign_id' => $user_id,
					'created_at' => $now, 'updated_at' => $now,
				) );
			}
		}
		return array( 'success' => true, 'task_id' => $task_id, 'parent_id' => $parent_id );
	},
) );

// =========================================================================
// §4.4.9 — create-subtask-group (idempotent:false)
// =========================================================================
$reg->write( 'fluent-boards/create-subtask-group', array(
	'label'       => 'Create Subtask Group',
	'description' => 'Create a new subtask group on a parent task (stored in fbs_task_metas with key=subtask_group_name).',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'task_id', 'title' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
			'task_id'  => array( 'type' => 'integer' ),
			'title'    => array( 'type' => 'string' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'group_id' => array( 'type' => 'integer' ),
		'task_id'  => array( 'type' => 'integer' ),
	) ),
	'annotations' => array( 'idempotent' => false ),
	'callback'    => function( $input ) {
		$task_id = (int) $input['task_id'];
		$title   = sanitize_text_field( $input['title'] ?? '' );
		if ( ! $title ) {
			return fluent_abilities_error( 'ability_invalid_input', 'title is required.' );
		}
		$now    = current_time( 'mysql' );
		$new_id = wpFluent()->table( 'fbs_task_metas' )->insertGetId( array(
			'task_id'    => $task_id,
			'key'        => 'subtask_group_name',
			'value'      => $title,
			'created_at' => $now,
			'updated_at' => $now,
		) );
		return array( 'success' => true, 'group_id' => (int) $new_id, 'task_id' => $task_id );
	},
) );

// =========================================================================
// §4.4.10 — update-subtask-group
// =========================================================================
$reg->write( 'fluent-boards/update-subtask-group', array(
	'label'       => 'Update Subtask Group',
	'description' => 'Rename a subtask group.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'task_id', 'group_id', 'title' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
			'task_id'  => array( 'type' => 'integer' ),
			'group_id' => array( 'type' => 'integer' ),
			'title'    => array( 'type' => 'string' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'group_id' => array( 'type' => 'integer' ) ) ),
	'callback'     => function( $input ) {
		$group_id = (int) $input['group_id'];
		$title    = sanitize_text_field( $input['title'] ?? '' );
		$existing = wpFluent()->table( 'fbs_task_metas' )->where( 'id', $group_id )->where( 'key', 'subtask_group_name' )->first();
		if ( ! $existing ) {
			return fluent_abilities_error( 'not_found', 'Subtask group not found.' );
		}
		wpFluent()->table( 'fbs_task_metas' )->where( 'id', $group_id )->update( array(
			'value'      => $title,
			'updated_at' => current_time( 'mysql' ),
		) );
		return array( 'success' => true, 'group_id' => $group_id );
	},
) );

// =========================================================================
// §4.4.11 — delete-subtask-group (idempotent:false; cascades subtasks → uncategorized)
// =========================================================================
$reg->delete( 'fluent-boards/delete-subtask-group', array(
	'label'       => 'Delete Subtask Group',
	'description' => 'Delete a subtask group. Existing subtasks in the group are cascaded to uncategorized (their group-mapping meta rows are deleted, so they appear in the implicit "uncategorized" bucket on next read).',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'task_id', 'group_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
			'task_id'  => array( 'type' => 'integer' ),
			'group_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'group_id'         => array( 'type' => 'integer' ),
		'subtasks_moved'   => array( 'type' => 'integer' ),
	) ),
	'annotations' => array( 'idempotent' => false ),
	'callback'    => function( $input ) {
		$task_id  = (int) $input['task_id'];
		$group_id = (int) $input['group_id'];
		// Find subtasks in this group, delete their group mapping rows.
		$metas = wpFluent()->table( 'fbs_task_metas' )->where( 'task_id', $task_id )->where( 'key', 'subtask_group_child' )->get();
		$moved = 0;
		foreach ( $metas as $m ) {
			$payload = maybe_unserialize( $m->value ?? '' );
			if ( is_array( $payload ) && (int) ( $payload['group_id'] ?? 0 ) === $group_id ) {
				wpFluent()->table( 'fbs_task_metas' )->where( 'id', $m->id )->delete();
				$moved++;
			}
		}
		wpFluent()->table( 'fbs_task_metas' )->where( 'id', $group_id )->where( 'key', 'subtask_group_name' )->delete();
		return array( 'success' => true, 'group_id' => $group_id, 'subtasks_moved' => $moved );
	},
) );
