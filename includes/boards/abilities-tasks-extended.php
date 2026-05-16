<?php
/**
 * Fluent Boards — Tasks Extended (Research §4.3 + §4.15)
 *
 * §4.3 Tasks discovery / archive / bulk / quick actions  — 14 abilities (free)
 * §4.15 Task cover image + from-image                    — 3 abilities (free)
 * Total: 17 abilities.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

// Loaded inside the wp_abilities_api_init callback in abilities.php.
// $reg (Fluent_Abilities_Registrar) is already available in scope.

// =========================================================================
// §4.3.1 — list-tasks-by-stage
// =========================================================================
$reg->read( 'fluent-boards/list-tasks-by-stage', array(
	'label'       => 'List Tasks By Stage',
	'description' => 'List active tasks on a board filtered to a single stage. Excludes archived tasks by default. Note: `position` is returned by the vendor as a numeric string (e.g. "0.00").',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'stage_id' ),
		'properties' => array_merge( array(
			'board_id' => array( 'type' => 'integer' ),
			'stage_id' => array( 'type' => 'integer' ),
		), fluent_abilities_pagination_schema() ),
	),
	'output_schema' => fluent_abilities_schema_list_output( 'tasks', array(
		'id'        => array( 'type' => 'integer' ),
		'title'     => array( 'type' => array( 'string', 'null' ) ),
		'priority'  => array( 'type' => array( 'string', 'null' ) ),
		'position'  => array( 'type' => array( 'number', 'string', 'null' ) ),
		'due_at'    => array( 'type' => array( 'string', 'null' ) ),
		'status'    => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$board_id   = (int) $input['board_id'];
		$stage_id   = (int) $input['stage_id'];
		$pagination = fluent_abilities_pagination( $input, 25 );
		$query      = wpFluent()->table( 'fbs_tasks' )
			->where( 'board_id', $board_id )
			->where( 'stage_id', $stage_id )
			->whereNull( 'archived_at' )
			->whereNull( 'parent_id' )
			->orderBy( 'position', 'ASC' );
		$total = (int) $query->count();
		$rows  = $query->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();
		$items = array();
		foreach ( $rows as $t ) {
			$items[] = array(
				'id'       => (int) $t->id,
				'title'    => $t->title ?? '',
				'priority' => $t->priority ?? null,
				'position' => $t->position ?? null,
				'due_at'   => $t->due_at ?? null,
				'status'   => $t->status ?? null,
			);
		}
		return array( 'tasks' => $items, 'total' => $total, 'page' => $pagination['page'], 'per_page' => $pagination['per_page'] );
	},
) );

// =========================================================================
// §4.3.2 — list-archived-tasks
// =========================================================================
$reg->read( 'fluent-boards/list-archived-tasks', array(
	'label'       => 'List Archived Tasks',
	'description' => 'List archived tasks on a board (archived_at IS NOT NULL).',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id' ),
		'properties' => array_merge( array(
			'board_id' => array( 'type' => 'integer' ),
		), fluent_abilities_pagination_schema() ),
	),
	'output_schema' => fluent_abilities_schema_list_output( 'tasks', array(
		'id'          => array( 'type' => 'integer' ),
		'title'       => array( 'type' => array( 'string', 'null' ) ),
		'archived_at' => array( 'type' => array( 'string', 'null' ) ),
		'stage_id'    => array( 'type' => array( 'integer', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$board_id   = (int) $input['board_id'];
		$pagination = fluent_abilities_pagination( $input, 25 );
		$query      = wpFluent()->table( 'fbs_tasks' )
			->where( 'board_id', $board_id )
			->whereNotNull( 'archived_at' )
			->whereNull( 'parent_id' )
			->orderBy( 'archived_at', 'DESC' );
		$total = (int) $query->count();
		$rows  = $query->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();
		$items = array();
		foreach ( $rows as $t ) {
			$items[] = array(
				'id'          => (int) $t->id,
				'title'       => $t->title ?? '',
				'archived_at' => $t->archived_at ?? null,
				'stage_id'    => $t->stage_id ? (int) $t->stage_id : null,
			);
		}
		return array( 'tasks' => $items, 'total' => $total, 'page' => $pagination['page'], 'per_page' => $pagination['per_page'] );
	},
) );

// =========================================================================
// §4.3.3 — archive-task
// =========================================================================
$reg->write( 'fluent-boards/archive-task', array(
	'label'       => 'Archive Task',
	'description' => 'Set archived_at on a task. Reversible via restore-task. Note: the task identifier parameter is `id` (sibling task abilities use `task_id` for the same value).',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'id' ),
		'properties' => array(
			'id' => array( 'type' => 'integer', 'description' => 'Task id.' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'id' => array( 'type' => 'integer' ) ) ),
	'callback'     => function( $input ) {
		$id = (int) ( $input['id'] ?? 0 );
		if ( ! wpFluent()->table( 'fbs_tasks' )->where( 'id', $id )->first() ) {
			return fluent_abilities_error( 'not_found', 'Task not found.' );
		}
		wpFluent()->table( 'fbs_tasks' )->where( 'id', $id )->update( array(
			'archived_at' => current_time( 'mysql' ),
			'updated_at'  => current_time( 'mysql' ),
		) );
		return array( 'success' => true, 'id' => $id );
	},
) );

// =========================================================================
// §4.3.4 — restore-task
// =========================================================================
$reg->write( 'fluent-boards/restore-task', array(
	'label'       => 'Restore Task',
	'description' => 'Clear archived_at on a previously-archived task.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'id' ),
		'properties' => array(
			'id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'id' => array( 'type' => 'integer' ) ) ),
	'callback'     => function( $input ) {
		$id = (int) ( $input['id'] ?? 0 );
		if ( ! wpFluent()->table( 'fbs_tasks' )->where( 'id', $id )->first() ) {
			return fluent_abilities_error( 'not_found', 'Task not found.' );
		}
		wpFluent()->table( 'fbs_tasks' )->where( 'id', $id )->update( array(
			'archived_at' => null,
			'updated_at'  => current_time( 'mysql' ),
		) );
		return array( 'success' => true, 'id' => $id );
	},
) );

// =========================================================================
// §4.3.5 — bulk-archive-tasks
// =========================================================================
$reg->write( 'fluent-boards/bulk-archive-tasks', array(
	'label'       => 'Bulk Archive Tasks',
	'description' => 'Set archived_at on every task in task_ids that belongs to board_id.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'task_ids' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
			'task_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'board_id' => array( 'type' => 'integer' ),
		'archived' => array( 'type' => 'integer' ),
	) ),
	'callback' => function( $input ) {
		$board_id = (int) $input['board_id'];
		$ids      = array_map( 'intval', (array) ( $input['task_ids'] ?? array() ) );
		if ( empty( $ids ) ) {
			return fluent_abilities_error( 'ability_invalid_input', 'task_ids must be a non-empty array.' );
		}
		$affected = wpFluent()->table( 'fbs_tasks' )
			->where( 'board_id', $board_id )
			->whereIn( 'id', $ids )
			->update( array( 'archived_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ) );
		return array( 'success' => true, 'board_id' => $board_id, 'archived' => (int) $affected );
	},
) );

// =========================================================================
// §4.3.6 — bulk-restore-tasks
// =========================================================================
$reg->write( 'fluent-boards/bulk-restore-tasks', array(
	'label'       => 'Bulk Restore Tasks',
	'description' => 'Clear archived_at on every task in task_ids that belongs to board_id.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'task_ids' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
			'task_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'board_id' => array( 'type' => 'integer' ),
		'restored' => array( 'type' => 'integer' ),
	) ),
	'callback' => function( $input ) {
		$board_id = (int) $input['board_id'];
		$ids      = array_map( 'intval', (array) ( $input['task_ids'] ?? array() ) );
		if ( empty( $ids ) ) {
			return fluent_abilities_error( 'ability_invalid_input', 'task_ids must be a non-empty array.' );
		}
		$affected = wpFluent()->table( 'fbs_tasks' )
			->where( 'board_id', $board_id )
			->whereIn( 'id', $ids )
			->update( array( 'archived_at' => null, 'updated_at' => current_time( 'mysql' ) ) );
		return array( 'success' => true, 'board_id' => $board_id, 'restored' => (int) $affected );
	},
) );

// =========================================================================
// §4.3.7 — bulk-delete-tasks
// =========================================================================
$reg->delete( 'fluent-boards/bulk-delete-tasks', array(
	'label'       => 'Bulk Delete Tasks',
	'description' => 'Permanently delete every task in task_ids that belongs to board_id, plus their relations and comments. Not idempotent.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'task_ids' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
			'task_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'board_id' => array( 'type' => 'integer' ),
		'deleted'  => array( 'type' => 'integer' ),
	) ),
	'annotations' => array( 'idempotent' => false ),
	'callback'    => function( $input ) {
		$board_id = (int) $input['board_id'];
		$ids      = array_map( 'intval', (array) ( $input['task_ids'] ?? array() ) );
		if ( empty( $ids ) ) {
			return fluent_abilities_error( 'ability_invalid_input', 'task_ids must be a non-empty array.' );
		}
		$existing = wpFluent()->table( 'fbs_tasks' )->where( 'board_id', $board_id )->whereIn( 'id', $ids )->get();
		// V5: coerce vendor Collection to array before array_map (P-A pattern).
		$valid    = array_map( function( $t ) { return (int) $t->id; }, fluent_abilities_to_array( $existing ) );
		if ( empty( $valid ) ) {
			return array( 'success' => true, 'board_id' => $board_id, 'deleted' => 0 );
		}
		wpFluent()->table( 'fbs_comments' )->whereIn( 'task_id', $valid )->delete();
		wpFluent()->table( 'fbs_activities' )->whereIn( 'object_id', $valid )->where( 'object_type', 'task_activity' )->delete();
		wpFluent()->table( 'fbs_relations' )->whereIn( 'object_id', $valid )->whereIn( 'object_type', array( 'task_assignee', 'task_user_watch', 'task_label', 'TASK_CUSTOM_FIELD' ) )->delete();
		$deleted = wpFluent()->table( 'fbs_tasks' )->where( 'board_id', $board_id )->whereIn( 'id', $valid )->delete();
		return array( 'success' => true, 'board_id' => $board_id, 'deleted' => (int) $deleted );
	},
) );

// =========================================================================
// §4.3.8 — bulk-task-actions (action enum, see §7.Q6)
// =========================================================================
$reg->write( 'fluent-boards/bulk-task-actions', array(
	'label'       => 'Bulk Task Actions',
	'description' => 'Apply an action to many tasks in one call. Supported actions: move-to-stage, assign, set-priority, set-due-date, add-label, remove-label. action_payload shape depends on action (e.g. {stage_id: int} for move-to-stage; {user_id: int} for assign; {priority: string} for set-priority; {due_at: string} for set-due-date; {label_id: int} for add/remove-label).',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'task_ids', 'action' ),
		'properties' => array(
			'board_id'       => array( 'type' => 'integer' ),
			'task_ids'       => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			'action'         => array( 'type' => 'string', 'enum' => array( 'move-to-stage', 'assign', 'set-priority', 'set-due-date', 'add-label', 'remove-label' ) ),
			'action_payload' => array( 'type' => 'object', 'description' => 'Action-specific payload — see ability description.' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'board_id' => array( 'type' => 'integer' ),
		'action'   => array( 'type' => 'string' ),
		'affected' => array( 'type' => 'integer' ),
	) ),
	'callback' => function( $input ) {
		$board_id = (int) $input['board_id'];
		$ids      = array_map( 'intval', (array) ( $input['task_ids'] ?? array() ) );
		$action   = sanitize_text_field( $input['action'] ?? '' );
		$payload  = (array) ( $input['action_payload'] ?? array() );
		if ( empty( $ids ) ) {
			return fluent_abilities_error( 'ability_invalid_input', 'task_ids must be a non-empty array.' );
		}
		$now      = current_time( 'mysql' );
		$affected = 0;
		switch ( $action ) {
			case 'move-to-stage':
				$stage_id = (int) ( $payload['stage_id'] ?? 0 );
				if ( ! $stage_id ) {
					return fluent_abilities_error( 'ability_invalid_input', 'action_payload.stage_id is required for move-to-stage.' );
				}
				$affected = wpFluent()->table( 'fbs_tasks' )->where( 'board_id', $board_id )->whereIn( 'id', $ids )
					->update( array( 'stage_id' => $stage_id, 'updated_at' => $now ) );
				break;
			case 'assign':
				$user_id = (int) ( $payload['user_id'] ?? 0 );
				if ( ! $user_id ) {
					return fluent_abilities_error( 'ability_invalid_input', 'action_payload.user_id is required for assign.' );
				}
				foreach ( $ids as $tid ) {
					$exists = wpFluent()->table( 'fbs_relations' )->where( 'object_type', 'task_assignee' )->where( 'object_id', $tid )->where( 'foreign_id', $user_id )->first();
					if ( ! $exists ) {
						wpFluent()->table( 'fbs_relations' )->insert( array( 'object_id' => $tid, 'object_type' => 'task_assignee', 'foreign_id' => $user_id, 'created_at' => $now, 'updated_at' => $now ) );
						$affected++;
					}
				}
				break;
			case 'set-priority':
				$priority = sanitize_text_field( $payload['priority'] ?? '' );
				$affected = wpFluent()->table( 'fbs_tasks' )->where( 'board_id', $board_id )->whereIn( 'id', $ids )
					->update( array( 'priority' => $priority, 'updated_at' => $now ) );
				break;
			case 'set-due-date':
				$due_at   = sanitize_text_field( $payload['due_at'] ?? '' );
				$affected = wpFluent()->table( 'fbs_tasks' )->where( 'board_id', $board_id )->whereIn( 'id', $ids )
					->update( array( 'due_at' => $due_at, 'updated_at' => $now ) );
				break;
			case 'add-label':
			case 'remove-label':
				$label_id = (int) ( $payload['label_id'] ?? 0 );
				if ( ! $label_id ) {
					return fluent_abilities_error( 'ability_invalid_input', 'action_payload.label_id is required for add/remove-label.' );
				}
				foreach ( $ids as $tid ) {
					if ( 'add-label' === $action ) {
						$has = wpFluent()->table( 'fbs_relations' )->where( 'object_type', 'task_label' )->where( 'object_id', $tid )->where( 'foreign_id', $label_id )->first();
						if ( ! $has ) {
							wpFluent()->table( 'fbs_relations' )->insert( array( 'object_id' => $tid, 'object_type' => 'task_label', 'foreign_id' => $label_id, 'created_at' => $now, 'updated_at' => $now ) );
							$affected++;
						}
					} else {
						$affected += (int) wpFluent()->table( 'fbs_relations' )->where( 'object_type', 'task_label' )->where( 'object_id', $tid )->where( 'foreign_id', $label_id )->delete();
					}
				}
				break;
			default:
				return fluent_abilities_error( 'ability_invalid_input', "Unsupported action: {$action}" );
		}
		return array( 'success' => true, 'board_id' => $board_id, 'action' => $action, 'affected' => (int) $affected );
	},
) );

// =========================================================================
// §4.3.9 — clone-task (idempotent:false)
// =========================================================================
$reg->write( 'fluent-boards/clone-task', array(
	'label'       => 'Clone Task',
	'description' => 'Copy a task within the same board (or to target_stage_id). Each call creates a new task id; not idempotent.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'task_id' ),
		'properties' => array(
			'board_id'        => array( 'type' => 'integer' ),
			'task_id'         => array( 'type' => 'integer' ),
			'new_title'       => array( 'type' => 'string' ),
			'target_stage_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'task_id'     => array( 'type' => 'integer' ),
		'new_task_id' => array( 'type' => 'integer' ),
	) ),
	'annotations' => array( 'idempotent' => false ),
	'callback'    => function( $input ) {
		$board_id = (int) $input['board_id'];
		$task_id  = (int) $input['task_id'];
		$task     = wpFluent()->table( 'fbs_tasks' )->where( 'board_id', $board_id )->where( 'id', $task_id )->first();
		if ( ! $task ) {
			return fluent_abilities_error( 'not_found', 'Task not found on this board.' );
		}
		$now    = current_time( 'mysql' );
		$new_id = wpFluent()->table( 'fbs_tasks' )->insertGetId( array(
			'board_id'   => $board_id,
			'stage_id'   => (int) ( $input['target_stage_id'] ?? ( $task->stage_id ?? 0 ) ),
			'type'       => $task->type ?? 'task',
			'title'      => sanitize_text_field( $input['new_title'] ?? ( ( $task->title ?? '' ) . ' (copy)' ) ),
			'description'=> $task->description ?? '',
			'priority'   => $task->priority ?? null,
			'position'   => $task->position ?? 0,
			'created_by' => (int) get_current_user_id(),
			'created_at' => $now,
			'updated_at' => $now,
		) );
		return array( 'success' => true, 'task_id' => $task_id, 'new_task_id' => (int) $new_id );
	},
) );

// =========================================================================
// §4.3.10 — move-task-to-next-stage
// =========================================================================
$reg->write( 'fluent-boards/move-task-to-next-stage', array(
	'label'       => 'Move Task To Next Stage',
	'description' => 'Move a task to the stage with the next-higher position on the same board.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'task_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
			'task_id'  => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'task_id'  => array( 'type' => 'integer' ),
		'stage_id' => array( 'type' => array( 'integer', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$board_id = (int) $input['board_id'];
		$task_id  = (int) $input['task_id'];
		$task     = wpFluent()->table( 'fbs_tasks' )->where( 'board_id', $board_id )->where( 'id', $task_id )->first();
		if ( ! $task ) {
			return fluent_abilities_error( 'not_found', 'Task not found on this board.' );
		}
		$current = wpFluent()->table( 'fbs_board_terms' )->where( 'id', $task->stage_id )->where( 'type', 'stage' )->first();
		if ( ! $current ) {
			return fluent_abilities_error( 'not_found', 'Current stage not found.' );
		}
		$next = wpFluent()->table( 'fbs_board_terms' )
			->where( 'board_id', $board_id )
			->where( 'type', 'stage' )
			->where( 'position', '>', $current->position )
			->orderBy( 'position', 'ASC' )
			->first();
		if ( ! $next ) {
			return fluent_abilities_error( 'not_found', 'No next stage exists; task is already in the last stage.' );
		}
		wpFluent()->table( 'fbs_tasks' )->where( 'id', $task_id )->update( array(
			'stage_id'   => (int) $next->id,
			'updated_at' => current_time( 'mysql' ),
		) );
		return array( 'success' => true, 'task_id' => $task_id, 'stage_id' => (int) $next->id );
	},
) );

// =========================================================================
// §4.3.11 — assign-yourself-to-task
// =========================================================================
$reg->write( 'fluent-boards/assign-yourself-to-task', array(
	'label'       => 'Assign Yourself To Task',
	'description' => 'Add the current user as an assignee on a task. Idempotent.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'task_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
			'task_id'  => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'task_id' => array( 'type' => 'integer' ),
		'user_id' => array( 'type' => 'integer' ),
	) ),
	'annotations' => array( 'idempotent' => true ),
	'callback'    => function( $input ) {
		$board_id = (int) $input['board_id'];
		$task_id  = (int) $input['task_id'];
		$user_id  = (int) get_current_user_id();
		if ( ! $user_id ) {
			return fluent_abilities_error( 'forbidden', 'Authenticated user required.' );
		}
		$task = wpFluent()->table( 'fbs_tasks' )->where( 'board_id', $board_id )->where( 'id', $task_id )->first();
		if ( ! $task ) {
			return fluent_abilities_error( 'not_found', 'Task not found on this board.' );
		}
		$has = wpFluent()->table( 'fbs_relations' )->where( 'object_type', 'task_assignee' )->where( 'object_id', $task_id )->where( 'foreign_id', $user_id )->first();
		if ( ! $has ) {
			$now = current_time( 'mysql' );
			wpFluent()->table( 'fbs_relations' )->insert( array(
				'object_id'   => $task_id,
				'object_type' => 'task_assignee',
				'foreign_id'  => $user_id,
				'created_at'  => $now,
				'updated_at'  => $now,
			) );
		}
		return array( 'success' => true, 'task_id' => $task_id, 'user_id' => $user_id );
	},
) );

// =========================================================================
// §4.3.12 — detach-yourself-from-task
// =========================================================================
$reg->write( 'fluent-boards/detach-yourself-from-task', array(
	'label'       => 'Detach Yourself From Task',
	'description' => 'Remove the current user from a task\'s assignee list. Idempotent.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'task_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
			'task_id'  => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'task_id' => array( 'type' => 'integer' ),
		'user_id' => array( 'type' => 'integer' ),
	) ),
	'annotations' => array( 'idempotent' => true ),
	'callback'    => function( $input ) {
		$board_id = (int) $input['board_id'];
		$task_id  = (int) $input['task_id'];
		$user_id  = (int) get_current_user_id();
		if ( ! $user_id ) {
			return fluent_abilities_error( 'forbidden', 'Authenticated user required.' );
		}
		wpFluent()->table( 'fbs_relations' )
			->where( 'object_type', 'task_assignee' )
			->where( 'object_id', $task_id )
			->where( 'foreign_id', $user_id )
			->delete();
		return array( 'success' => true, 'task_id' => $task_id, 'user_id' => $user_id );
	},
) );

// =========================================================================
// §4.3.13 — update-task-dates
// =========================================================================
$reg->write( 'fluent-boards/update-task-dates', array(
	'label'       => 'Update Task Dates',
	'description' => 'Update started_at, due_at, remind_at, and reminder_type on a task. Pass only the fields you want to change.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'task_id' ),
		'properties' => array(
			'task_id'       => array( 'type' => 'integer' ),
			'started_at'    => array( 'type' => 'string' ),
			'due_at'        => array( 'type' => 'string' ),
			'remind_at'     => array( 'type' => 'string' ),
			'reminder_type' => array( 'type' => 'string', 'enum' => array( 'none', 'at_time', 'before_due' ) ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'task_id' => array( 'type' => 'integer' ) ) ),
	'callback'     => function( $input ) {
		$task_id = (int) $input['task_id'];
		if ( ! wpFluent()->table( 'fbs_tasks' )->where( 'id', $task_id )->first() ) {
			return fluent_abilities_error( 'not_found', 'Task not found.' );
		}
		$update = array( 'updated_at' => current_time( 'mysql' ) );
		foreach ( array( 'started_at', 'due_at', 'remind_at', 'reminder_type' ) as $f ) {
			if ( array_key_exists( $f, $input ) ) {
				$update[ $f ] = sanitize_text_field( (string) $input[ $f ] );
			}
		}
		wpFluent()->table( 'fbs_tasks' )->where( 'id', $task_id )->update( $update );
		return array( 'success' => true, 'task_id' => $task_id );
	},
) );

// =========================================================================
// §4.3.14 — update-task-status
// =========================================================================
$reg->write( 'fluent-boards/update-task-status', array(
	'label'       => 'Update Task Status',
	'description' => 'Set task status. For board type=to-do/roadmap use open/closed; for type=sales-pipeline use won/lost.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'task_id', 'status' ),
		'properties' => array(
			'task_id' => array( 'type' => 'integer' ),
			'status'  => array( 'type' => 'string', 'enum' => array( 'open', 'closed', 'won', 'lost' ) ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'task_id' => array( 'type' => 'integer' ), 'status' => array( 'type' => 'string' ) ) ),
	'callback'     => function( $input ) {
		$task_id = (int) $input['task_id'];
		$status  = sanitize_text_field( $input['status'] ?? '' );
		if ( ! in_array( $status, array( 'open', 'closed', 'won', 'lost' ), true ) ) {
			return fluent_abilities_error( 'ability_invalid_input', 'status must be one of open, closed, won, lost.' );
		}
		if ( ! wpFluent()->table( 'fbs_tasks' )->where( 'id', $task_id )->first() ) {
			return fluent_abilities_error( 'not_found', 'Task not found.' );
		}
		wpFluent()->table( 'fbs_tasks' )->where( 'id', $task_id )->update( array(
			'status'     => $status,
			'updated_at' => current_time( 'mysql' ),
		) );
		return array( 'success' => true, 'task_id' => $task_id, 'status' => $status );
	},
) );

// =========================================================================
// §4.15.1 — add-task-cover-image
// =========================================================================
$reg->write( 'fluent-boards/add-task-cover-image', array(
	'label'       => 'Add Task Cover Image',
	'description' => 'Set a cover image on a task. Provide at least one of `attachment_id` or `image_url` (both may be supplied — `attachment_id` takes precedence; the handler rejects only when NEITHER resolves). Stored in task.settings.cover_image. Schema declares this via `anyOf` (P5 factually-corrective per installed-handler precedence chain, not exactly-one).',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'task_id' ),
		'properties' => array(
			'task_id'       => array( 'type' => 'integer' ),
			'attachment_id' => array( 'type' => 'integer' ),
			'image_url'     => array( 'type' => 'string' ),
		),
		// P5 factually-corrective (NOT oneOf): handler precedence
		// if($attachment_id) elseif($image_url) — both accepted, only
		// neither rejected. anyOf = "at least one".
		'anyOf'      => array(
			array( 'required' => array( 'attachment_id' ) ),
			array( 'required' => array( 'image_url' ) ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'task_id'   => array( 'type' => 'integer' ),
		'image_url' => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$task_id = (int) $input['task_id'];
		$task    = wpFluent()->table( 'fbs_tasks' )->where( 'id', $task_id )->first();
		if ( ! $task ) {
			return fluent_abilities_error( 'not_found', 'Task not found.' );
		}
		$attachment_id = (int) ( $input['attachment_id'] ?? 0 );
		$image_url     = '';
		if ( $attachment_id ) {
			$image_url = (string) wp_get_attachment_url( $attachment_id );
		} elseif ( ! empty( $input['image_url'] ) ) {
			$validated = fluent_abilities_validate_url( $input['image_url'] );
			if ( is_wp_error( $validated ) ) {
				return $validated;
			}
			if ( ! function_exists( 'media_sideload_image' ) ) {
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}
			$sideload = media_sideload_image( $validated, 0, null, 'src' );
			if ( is_wp_error( $sideload ) ) {
				return $sideload;
			}
			$image_url = (string) $sideload;
		}
		if ( ! $image_url ) {
			return fluent_abilities_error( 'ability_invalid_input', 'Provide attachment_id or image_url.' );
		}
		$settings = maybe_unserialize( $task->settings ?? '' );
		$settings = is_array( $settings ) ? $settings : array();
		$settings['cover_image'] = array( 'attachment_id' => $attachment_id ?: null, 'image_url' => $image_url );
		wpFluent()->table( 'fbs_tasks' )->where( 'id', $task_id )->update( array(
			'settings'   => maybe_serialize( $settings ),
			'updated_at' => current_time( 'mysql' ),
		) );
		return array( 'success' => true, 'task_id' => $task_id, 'image_url' => $image_url );
	},
) );

// =========================================================================
// §4.15.2 — remove-task-cover-image
// =========================================================================
$reg->write( 'fluent-boards/remove-task-cover-image', array(
	'label'       => 'Remove Task Cover Image',
	'description' => 'Remove cover_image from task settings. Idempotent.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'task_id' ),
		'properties' => array(
			'task_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'task_id' => array( 'type' => 'integer' ) ) ),
	'annotations'  => array( 'idempotent' => true ),
	'callback'     => function( $input ) {
		$task_id = (int) $input['task_id'];
		$task    = wpFluent()->table( 'fbs_tasks' )->where( 'id', $task_id )->first();
		if ( ! $task ) {
			return fluent_abilities_error( 'not_found', 'Task not found.' );
		}
		$settings = maybe_unserialize( $task->settings ?? '' );
		$settings = is_array( $settings ) ? $settings : array();
		unset( $settings['cover_image'] );
		wpFluent()->table( 'fbs_tasks' )->where( 'id', $task_id )->update( array(
			'settings'   => maybe_serialize( $settings ),
			'updated_at' => current_time( 'mysql' ),
		) );
		return array( 'success' => true, 'task_id' => $task_id );
	},
) );

// =========================================================================
// §4.15.3 — get-board-image-templates
// =========================================================================
$reg->read( 'fluent-boards/get-board-image-templates', array(
	'label'         => 'Get Board Image Templates',
	'description'   => 'Return vendor-provided cover-image templates available for new tasks on a board.',
	'category'      => 'fluent-boards',
	'input_schema'  => array(
		'type'       => 'object',
		'required'   => array( 'board_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_collection_output( 'templates', array(
		'id'        => array( 'type' => array( 'string', 'integer' ) ),
		'image_url' => array( 'type' => 'string' ),
		'label'     => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$board_id = (int) ( $input['board_id'] ?? 0 );
		$board    = wpFluent()->table( 'fbs_boards' )->where( 'id', $board_id )->first();
		if ( ! $board ) {
			return fluent_abilities_error( 'not_found', 'Board not found.' );
		}
		$templates = array();
		if ( class_exists( '\\FluentBoardsPro\\App\\Services\\TaskCoverService' ) && method_exists( '\\FluentBoardsPro\\App\\Services\\TaskCoverService', 'getTemplates' ) ) {
			$raw = \FluentBoardsPro\App\Services\TaskCoverService::getTemplates( $board_id );
			foreach ( (array) $raw as $k => $t ) {
				$templates[] = array(
					'id'        => is_array( $t ) ? ( $t['id'] ?? $k ) : $k,
					'image_url' => is_array( $t ) ? ( $t['image_url'] ?? '' ) : (string) $t,
					'label'     => is_array( $t ) ? ( $t['label'] ?? null ) : null,
				);
			}
		}
		return array( 'templates' => $templates, 'total' => count( $templates ) );
	},
) );
