<?php
/**
 * Fluent Boards Abilities
 *
 * Board management, tasks, stages, and task comments.
 *
 * 9 abilities in the 'fluent-boards' category.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'boards' );

	// =========================================================================
	// BOARDS
	// =========================================================================

	$reg->read( 'fluent-boards/list-boards', array(
		'label'       => 'List Boards',
		'description' => 'List project boards with task and stage counts. Optional status filter.',
		'category'    => 'fluent-boards',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'status' => array(
					'type'        => 'string',
					'description' => 'Filter by board status (e.g., active, archived)',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'boards', array(
			'id'          => array( 'type' => 'integer' ),
			'title'       => array( 'type' => array( 'string', 'null' ) ),
			'description' => array( 'type' => array( 'string', 'null' ) ),
			'type'        => array( 'type' => array( 'string', 'null' ) ),
			'status'      => array( 'type' => array( 'string', 'null' ) ),
			'created_by'  => array( 'type' => array( 'integer', 'null' ) ),
			'created_at'  => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$query = wpFluent()->table( 'fbs_boards' )
				->orderBy( 'id', 'DESC' );

			if ( ! empty( $input['status'] ) ) {
				$query->where( 'status', sanitize_text_field( $input['status'] ) );
			}

			$boards = $query->get();

			$items = array();
			foreach ( $boards as $board ) {
				$task_count = (int) wpFluent()->table( 'fbs_tasks' )
					->where( 'board_id', $board->id )
					->count();

				$stage_count = (int) wpFluent()->table( 'fbs_board_terms' )
					->where( 'board_id', $board->id )
					->where( 'type', 'stage' )
					->count();

				$items[] = array(
					'id'          => (int) $board->id,
					'title'       => $board->title ?? '',
					'description' => $board->description ?? '',
					'type'        => $board->type ?? '',
					'status'      => $board->status ?? '',
					'task_count'  => $task_count,
					'stage_count' => $stage_count,
					'created_by'  => (int) ( $board->created_by ?? 0 ),
					'created_at'  => (string) $board->created_at,
				);
			}

			return array(
				'boards' => $items,
				'total'  => count( $items ),
			);
		},
	) );

	$reg->read( 'fluent-boards/get-board', array(
		'label'       => 'Get Board',
		'description' => 'Get a single board by ID with its stages and task counts per stage.',
		'category'    => 'fluent-boards',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Board ID',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'          => array( 'type' => 'integer' ),
			'title'       => array( 'type' => array( 'string', 'null' ) ),
			'description' => array( 'type' => array( 'string', 'null' ) ),
			'type'        => array( 'type' => array( 'string', 'null' ) ),
			'status'      => array( 'type' => array( 'string', 'null' ) ),
			'currency'    => array( 'type' => array( 'string', 'null' ) ),
			'created_by'  => array( 'type' => array( 'integer', 'null' ) ),
			'created_at'  => array( 'type' => array( 'string', 'null' ) ),
			'updated_at'  => array( 'type' => array( 'string', 'null' ) ),
			'stages'      => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'total_tasks' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$board = wpFluent()->table( 'fbs_boards' )
				->where( 'id', (int) $input['id'] )
				->first();

			if ( ! $board ) {
				return fluent_abilities_error( 'not_found', 'Board not found' );
			}

			$stages = wpFluent()->table( 'fbs_board_terms' )
				->where( 'board_id', $board->id )
				->where( 'type', 'stage' )
				->orderBy( 'position', 'ASC' )
				->get();

			$stage_data = array();
			foreach ( $stages as $stage ) {
				$task_count = (int) wpFluent()->table( 'fbs_tasks' )
					->where( 'board_id', $board->id )
					->where( 'stage_id', $stage->id )
					->count();

				$stage_data[] = array(
					'id'         => (int) $stage->id,
					'title'      => $stage->title ?? '',
					'slug'       => $stage->slug ?? '',
					'position'   => (int) ( $stage->position ?? 0 ),
					'color'      => $stage->color ?? null,
					'bg_color'   => $stage->bg_color ?? null,
					'task_count' => $task_count,
				);
			}

			$total_tasks = (int) wpFluent()->table( 'fbs_tasks' )
				->where( 'board_id', $board->id )
				->count();

			return array(
				'id'          => (int) $board->id,
				'title'       => $board->title ?? '',
				'description' => $board->description ?? '',
				'type'        => $board->type ?? '',
				'status'      => $board->status ?? '',
				'currency'    => $board->currency ?? '',
				'created_by'  => (int) ( $board->created_by ?? 0 ),
				'created_at'  => $board->created_at ? (string) $board->created_at : null,
				'updated_at'  => $board->updated_at ? (string) $board->updated_at : null,
				'stages'      => $stage_data,
				'total_tasks' => $total_tasks,
			);
		},
	) );

	// =========================================================================
	// TASKS
	// =========================================================================

	$reg->read( 'fluent-boards/list-tasks', array(
		'label'       => 'List Board Tasks',
		'description' => 'List tasks for a board with pagination. Filter by stage, status, priority, or assignee.',
		'category'    => 'fluent-boards',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'board_id' ),
			'properties' => array_merge( array(
				'board_id' => array(
					'type'        => 'integer',
					'description' => 'Board ID (required)',
				),
				'stage_id' => array(
					'type'        => 'integer',
					'description' => 'Filter by stage ID',
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'Filter by task status (e.g., open, closed)',
				),
				'priority' => array(
					'type'        => 'string',
					'description' => 'Filter by priority: low, medium, high',
				),
				'assignee' => array(
					'type'        => 'integer',
					'description' => 'Filter by assigned user ID (via relations table)',
				),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'tasks', array(
			'id'             => array( 'type' => 'integer' ),
			'title'          => array( 'type' => array( 'string', 'null' ) ),
			'description'    => array( 'type' => array( 'string', 'null' ) ),
			'stage_id'       => array( 'type' => array( 'integer', 'null' ) ),
			'stage_name'     => array( 'type' => array( 'string', 'null' ) ),
			'status'         => array( 'type' => array( 'string', 'null' ) ),
			'priority'       => array( 'type' => array( 'string', 'null' ) ),
			'position'       => array( 'type' => 'integer' ),
			'due_at'         => array( 'type' => array( 'string', 'null' ) ),
			'created_by'     => array( 'type' => array( 'integer', 'null' ) ),
			'created_at'     => array( 'type' => array( 'string', 'null' ) ),
			'comments_count' => array( 'type' => 'integer' ),
			'subtasks_count' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$board_id   = (int) $input['board_id'];

			// Verify the board exists.
			$board = wpFluent()->table( 'fbs_boards' )
				->where( 'id', $board_id )
				->first();

			if ( ! $board ) {
				return fluent_abilities_error( 'not_found', 'Board not found' );
			}

			$query = wpFluent()->table( 'fbs_tasks' )
				->where( 'board_id', $board_id );

			if ( ! empty( $input['stage_id'] ) ) {
				$query->where( 'stage_id', (int) $input['stage_id'] );
			}

			if ( ! empty( $input['status'] ) ) {
				$query->where( 'status', sanitize_text_field( $input['status'] ) );
			}

			if ( ! empty( $input['priority'] ) ) {
				$query->where( 'priority', sanitize_text_field( $input['priority'] ) );
			}

			// Filter by assignee via relations table.
			if ( ! empty( $input['assignee'] ) ) {
				$assignee_id   = (int) $input['assignee'];
				$assigned_task_ids = wpFluent()->table( 'fbs_relations' )
					->where( 'board_id', $board_id )
					->where( 'object_type', 'task_assignee' )
					->where( 'foreign_id', $assignee_id )
					->select( 'object_id' )
					->get();

				$task_ids = array();
				foreach ( $assigned_task_ids as $row ) {
					$task_ids[] = (int) $row->object_id;
				}

				if ( empty( $task_ids ) ) {
					return array(
						'tasks'    => array(),
						'total'    => 0,
						'page'     => $pagination['page'],
						'per_page' => $pagination['per_page'],
					);
				}

				$query->whereIn( 'id', $task_ids );
			}

			$total = $query->count();
			$tasks = $query->orderBy( 'position', 'ASC' )
				->offset( $pagination['offset'] )
				->limit( $pagination['per_page'] )
				->get();

			// Pre-fetch stage names for the board.
			$stages_raw = wpFluent()->table( 'fbs_board_terms' )
				->where( 'board_id', $board_id )
				->where( 'type', 'stage' )
				->get();

			$stage_names = array();
			foreach ( $stages_raw as $stage ) {
				$stage_names[ (int) $stage->id ] = $stage->title;
			}

			$items = array();
			foreach ( $tasks as $task ) {
				$items[] = array(
					'id'         => (int) $task->id,
					'title'      => $task->title ?? '',
					'description' => $task->description ?? '',
					'stage_id'   => (int) ( $task->stage_id ?? 0 ),
					'stage_name' => $stage_names[ (int) $task->stage_id ] ?? '',
					'status'     => $task->status ?? '',
					'priority'   => $task->priority ?? '',
					'position'   => (int) ( $task->position ?? 0 ),
					'due_at'     => $task->due_at ?? '',
					'created_by' => (int) ( $task->created_by ?? 0 ),
					'created_at' => (string) $task->created_at,
					'comments_count' => (int) wpFluent()->table( 'fbs_comments' )->where( 'task_id', $task->id )->count(),
					'subtasks_count' => (int) wpFluent()->table( 'fbs_tasks' )->where( 'parent_id', $task->id )->count(),
				);
			}

			return array(
				'tasks'    => $items,
				'total'    => $total,
				'page'     => $pagination['page'],
				'per_page' => $pagination['per_page'],
			);
		},
	) );

	$reg->read( 'fluent-boards/get-task', array(
		'label'       => 'Get Board Task',
		'description' => 'Get a single task by ID with full details, comment count, and subtask count.',
		'category'    => 'fluent-boards',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Task ID',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'             => array( 'type' => 'integer' ),
			'title'          => array( 'type' => array( 'string', 'null' ) ),
			'description'    => array( 'type' => array( 'string', 'null' ) ),
			'stage_id'       => array( 'type' => array( 'integer', 'null' ) ),
			'stage_name'     => array( 'type' => array( 'string', 'null' ) ),
			'status'         => array( 'type' => array( 'string', 'null' ) ),
			'priority'       => array( 'type' => array( 'string', 'null' ) ),
			'position'       => array( 'type' => 'integer' ),
			'due_at'         => array( 'type' => array( 'string', 'null' ) ),
			'created_by'     => array( 'type' => array( 'integer', 'null' ) ),
			'created_at'     => array( 'type' => array( 'string', 'null' ) ),
			'comments_count' => array( 'type' => 'integer' ),
			'subtasks_count' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$task = wpFluent()->table( 'fbs_tasks' )
				->where( 'id', (int) $input['id'] )
				->first();

			if ( ! $task ) {
				return fluent_abilities_error( 'not_found', 'Task not found' );
			}

			// Stage name.
			$stage_name = null;
			if ( $task->stage_id ) {
				$stage = wpFluent()->table( 'fbs_board_terms' )
					->where( 'id', (int) $task->stage_id )
					->first();
				$stage_name = $stage ? $stage->title : null;
			}

			// Comment count.
			$comment_count = (int) wpFluent()->table( 'fbs_comments' )
				->where( 'task_id', $task->id )
				->count();

			// Subtask count.
			$subtask_count = (int) wpFluent()->table( 'fbs_tasks' )
				->where( 'parent_id', $task->id )
				->count();

			// Assignees via relations.
			$assignee_rows = wpFluent()->table( 'fbs_relations' )
				->where( 'object_type', 'task_assignee' )
				->where( 'object_id', $task->id )
				->select( 'foreign_id' )
				->get();

			$assignees = array();
			foreach ( $assignee_rows as $row ) {
				$user = get_userdata( (int) $row->foreign_id );
				$assignees[] = array(
					'user_id'      => (int) $row->foreign_id,
					'display_name' => $user ? $user->display_name : null,
				);
			}

			// Labels via board_terms.
			$label_relations = wpFluent()->table( 'fbs_relations' )
				->where( 'object_type', 'task_label' )
				->where( 'object_id', $task->id )
				->select( 'foreign_id' )
				->get();

			$labels = array();
			foreach ( $label_relations as $row ) {
				$label = wpFluent()->table( 'fbs_board_terms' )
					->where( 'id', (int) $row->foreign_id )
					->where( 'type', 'label' )
					->first();
				if ( $label ) {
					$labels[] = array(
						'id'       => (int) $label->id,
						'title'    => $label->title,
						'color'    => $label->color,
						'bg_color' => $label->bg_color,
					);
				}
			}

			return array(
				'id'             => (int) $task->id,
				'board_id'       => (int) $task->board_id,
				'parent_id'      => $task->parent_id ? (int) $task->parent_id : 0,
				'title'          => $task->title ?? '',
				'slug'           => $task->slug ?? '',
				'description'    => $task->description ?? '',
				'type'           => $task->type ?? '',
				'status'         => $task->status ?? '',
				'stage_id'       => (int) ( $task->stage_id ?? 0 ),
				'stage_name'     => $stage_name ?? '',
				'priority'       => $task->priority ?? '',
				'position'       => (int) ( $task->position ?? 0 ),
				'lead_value'     => $task->lead_value ?? '',
				'due_at'         => $task->due_at ?? '',
				'started_at'     => $task->started_at ?? '',
				'created_by'     => (int) ( $task->created_by ?? 0 ),
				'created_at'     => (string) $task->created_at,
				'updated_at'     => (string) $task->updated_at,
				'assignees'      => $assignees,
				'labels'         => $labels,
				'comments_count' => $comment_count,
				'subtasks_count' => $subtask_count,
			);
		},
	) );

	$reg->write( 'fluent-boards/create-task', array(
		'label'       => 'Create Board Task',
		'description' => 'Create a new task on a board. Required: board_id and title. Optionally set stage, priority, due date, and position.',
		'category'    => 'fluent-boards',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'board_id', 'title' ),
			'properties' => array(
				'board_id'    => array( 'type' => 'integer', 'description' => 'Board ID (required)' ),
				'title'       => array( 'type' => 'string',  'description' => 'Task title (required)' ),
				'stage_id'    => array( 'type' => 'integer', 'description' => 'Stage ID to place task in' ),
				'description' => array( 'type' => 'string',  'description' => 'Task description' ),
				'priority'    => array( 'type' => 'string',  'description' => 'Priority: low, medium, high' ),
				'due_at'      => array( 'type' => 'string',  'description' => 'Due date in Y-m-d H:i:s format' ),
				'position'    => array( 'type' => 'integer', 'description' => 'Position within the stage for ordering' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'       => array( 'type' => 'integer' ),
			'board_id' => array( 'type' => 'integer' ),
			'title'    => array( 'type' => 'string' ),
			'status'   => array( 'type' => 'string' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			$board_id = (int) $input['board_id'];

			// Verify the board exists.
			$board = wpFluent()->table( 'fbs_boards' )
				->where( 'id', $board_id )
				->first();

			if ( ! $board ) {
				return fluent_abilities_error( 'not_found', 'Board not found' );
			}

			$now = current_time( 'mysql' );

			$data = array(
				'board_id'   => $board_id,
				'title'      => sanitize_text_field( $input['title'] ),
				'slug'       => sanitize_title( $input['title'] ),
				'status'     => 'open',
				'created_by' => get_current_user_id(),
				'created_at' => $now,
				'updated_at' => $now,
			);

			// Stage: default to first stage if not provided.
			if ( ! empty( $input['stage_id'] ) ) {
				$stage = wpFluent()->table( 'fbs_board_terms' )
					->where( 'id', (int) $input['stage_id'] )
					->where( 'board_id', $board_id )
					->where( 'type', 'stage' )
					->first();

				if ( ! $stage ) {
					return fluent_abilities_error( 'ability_invalid_input', 'Stage not found on this board' );
				}

				$data['stage_id'] = (int) $input['stage_id'];
			} else {
				$first_stage = wpFluent()->table( 'fbs_board_terms' )
					->where( 'board_id', $board_id )
					->where( 'type', 'stage' )
					->orderBy( 'position', 'ASC' )
					->first();

				$data['stage_id'] = $first_stage ? (int) $first_stage->id : 0;
			}

			if ( ! empty( $input['description'] ) ) {
				$data['description'] = wp_kses_post( $input['description'] );
			}

			if ( ! empty( $input['priority'] ) ) {
				$priority = sanitize_text_field( $input['priority'] );
				if ( in_array( $priority, array( 'low', 'medium', 'high' ), true ) ) {
					$data['priority'] = $priority;
				}
			}

			if ( ! empty( $input['due_at'] ) ) {
				$data['due_at'] = sanitize_text_field( $input['due_at'] );
			}

			if ( isset( $input['position'] ) ) {
				$data['position'] = (int) $input['position'];
			} else {
				// Place at end of stage.
				$max_position = wpFluent()->table( 'fbs_tasks' )
					->where( 'board_id', $board_id )
					->where( 'stage_id', $data['stage_id'] )
					->max( 'position' );

				$data['position'] = $max_position ? (int) $max_position + 1 : 0;
			}

			$task_id = wpFluent()->table( 'fbs_tasks' )->insertGetId( $data );

			return array(
				'success'  => true,
				'id'       => (int) $task_id,
				'board_id' => $board_id,
				'stage_id' => (int) $data['stage_id'],
				'title'    => $data['title'],
			);
		},
	) );

	$reg->write( 'fluent-boards/update-task', array(
		'label'       => 'Update Board Task',
		'description' => 'Update a task. Move between stages, change priority, update due date, title, or description. Only provided fields are changed.',
		'category'    => 'fluent-boards',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'          => array( 'type' => 'integer', 'description' => 'Task ID (required)' ),
				'title'       => array( 'type' => 'string',  'description' => 'New task title' ),
				'description' => array( 'type' => 'string',  'description' => 'New task description' ),
				'stage_id'    => array( 'type' => 'integer', 'description' => 'Move task to a different stage' ),
				'status'      => array( 'type' => 'string',  'description' => 'New task status (e.g., open, closed)' ),
				'priority'    => array( 'type' => 'string',  'description' => 'New priority: low, medium, high' ),
				'due_at'      => array( 'type' => 'string',  'description' => 'New due date in Y-m-d H:i:s format' ),
				'position'    => array( 'type' => 'integer', 'description' => 'New position within stage' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'     => array( 'type' => 'integer' ),
			'title'  => array( 'type' => 'string' ),
			'status' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$task = wpFluent()->table( 'fbs_tasks' )
				->where( 'id', (int) $input['id'] )
				->first();

			if ( ! $task ) {
				return fluent_abilities_error( 'not_found', 'Task not found' );
			}

			$data    = array();
			$updated = array();

			if ( isset( $input['title'] ) ) {
				$data['title'] = sanitize_text_field( $input['title'] );
				$data['slug']  = sanitize_title( $input['title'] );
				$updated[]     = 'title';
			}

			if ( isset( $input['description'] ) ) {
				$data['description'] = wp_kses_post( $input['description'] );
				$updated[]           = 'description';
			}

			if ( ! empty( $input['stage_id'] ) ) {
				$new_stage_id = (int) $input['stage_id'];

				// Verify stage exists on the same board.
				$stage = wpFluent()->table( 'fbs_board_terms' )
					->where( 'id', $new_stage_id )
					->where( 'board_id', $task->board_id )
					->where( 'type', 'stage' )
					->first();

				if ( ! $stage ) {
					return fluent_abilities_error( 'ability_invalid_input', 'Stage not found on this board' );
				}

				$data['stage_id'] = $new_stage_id;
				$updated[]        = 'stage_id';
			}

			if ( isset( $input['status'] ) ) {
				$data['status'] = sanitize_text_field( $input['status'] );
				$updated[]      = 'status';

				// Track completion timestamp.
				if ( $input['status'] === 'closed' && ! $task->last_completed_at ) {
					$data['last_completed_at'] = current_time( 'mysql' );
				}
			}

			if ( ! empty( $input['priority'] ) ) {
				$priority = sanitize_text_field( $input['priority'] );
				if ( in_array( $priority, array( 'low', 'medium', 'high' ), true ) ) {
					$data['priority'] = $priority;
					$updated[]        = 'priority';
				}
			}

			if ( isset( $input['due_at'] ) ) {
				$data['due_at'] = sanitize_text_field( $input['due_at'] );
				$updated[]      = 'due_at';
			}

			if ( isset( $input['position'] ) ) {
				$data['position'] = (int) $input['position'];
				$updated[]        = 'position';
			}

			if ( empty( $data ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'No fields to update' );
			}

			$data['updated_at'] = current_time( 'mysql' );

			wpFluent()->table( 'fbs_tasks' )
				->where( 'id', (int) $input['id'] )
				->update( $data );

			return array(
				'success' => true,
				'id'      => (int) $input['id'],
				'updated' => $updated,
			);
		},
	) );

	// =========================================================================
	// STAGES
	// =========================================================================

	$reg->read( 'fluent-boards/list-stages', array(
		'label'       => 'List Board Stages',
		'description' => 'List all stages for a board with task counts per stage.',
		'category'    => 'fluent-boards',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'board_id' ),
			'properties' => array(
				'board_id' => array(
					'type'        => 'integer',
					'description' => 'Board ID (required)',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'stages', array(
			'id'       => array( 'type' => 'integer' ),
			'title'    => array( 'type' => array( 'string', 'null' ) ),
			'position' => array( 'type' => 'integer' ),
			'status'   => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$board_id = (int) $input['board_id'];

			// Verify the board exists.
			$board = wpFluent()->table( 'fbs_boards' )
				->where( 'id', $board_id )
				->first();

			if ( ! $board ) {
				return fluent_abilities_error( 'not_found', 'Board not found' );
			}

			$stages = wpFluent()->table( 'fbs_board_terms' )
				->where( 'board_id', $board_id )
				->where( 'type', 'stage' )
				->orderBy( 'position', 'ASC' )
				->get();

			$items = array();
			foreach ( $stages as $stage ) {
				$task_count = (int) wpFluent()->table( 'fbs_tasks' )
					->where( 'board_id', $board_id )
					->where( 'stage_id', $stage->id )
					->count();

				$items[] = array(
					'id'         => (int) $stage->id,
					'title'      => $stage->title ?? '',
					'slug'       => $stage->slug ?? '',
					'position'   => (int) ( $stage->position ?? 0 ),
					'color'      => $stage->color ?? null,
					'bg_color'   => $stage->bg_color ?? null,
					'task_count' => $task_count,
					'created_at' => $stage->created_at ? (string) $stage->created_at : null,
				);
			}

			return array(
				'stages'   => $items,
				'total'    => count( $items ),
				'board_id' => $board_id,
			);
		},
	) );

	// =========================================================================
	// TASK COMMENTS
	// =========================================================================

	$reg->read( 'fluent-boards/list-task-comments', array(
		'label'       => 'List Task Comments',
		'description' => 'List all comments for a specific task.',
		'category'    => 'fluent-boards',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'task_id' ),
			'properties' => array(
				'task_id' => array(
					'type'        => 'integer',
					'description' => 'Task ID (required)',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'comments', array(
			'id'          => array( 'type' => 'integer' ),
			'task_id'     => array( 'type' => 'integer' ),
			'parent_id'   => array( 'type' => array( 'integer', 'null' ) ),
			'type'        => array( 'type' => array( 'string', 'null' ) ),
			'description' => array( 'type' => array( 'string', 'null' ) ),
			'created_by'  => array( 'type' => array( 'integer', 'null' ) ),
			'author_name' => array( 'type' => array( 'string', 'null' ) ),
			'created_at'  => array( 'type' => array( 'string', 'null' ) ),
			'updated_at'  => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$task_id = (int) $input['task_id'];

			// Verify the task exists.
			$task = wpFluent()->table( 'fbs_tasks' )
				->where( 'id', $task_id )
				->first();

			if ( ! $task ) {
				return fluent_abilities_error( 'not_found', 'Task not found' );
			}

			$comments = wpFluent()->table( 'fbs_comments' )
				->where( 'task_id', $task_id )
				->orderBy( 'created_at', 'ASC' )
				->get();

			$items = array();
			foreach ( $comments as $comment ) {
				$user = $comment->created_by ? get_userdata( (int) $comment->created_by ) : null;

				$items[] = array(
					'id'           => (int) $comment->id,
					'board_id'     => (int) $comment->board_id,
					'task_id'      => (int) $comment->task_id,
					'parent_id'    => $comment->parent_id ? (int) $comment->parent_id : null,
					'type'         => $comment->type ?? null,
					'description'  => $comment->description ?? null,
					'created_by'   => $comment->created_by ? (int) $comment->created_by : null,
					'author_name'  => $user ? $user->display_name : null,
					'created_at'   => $comment->created_at ? (string) $comment->created_at : null,
					'updated_at'   => $comment->updated_at ? (string) $comment->updated_at : null,
				);
			}

			return array(
				'comments' => $items,
				'total'    => count( $items ),
				'task_id'  => $task_id,
			);
		},
	) );

	$reg->write( 'fluent-boards/create-task-comment', array(
		'label'       => 'Create Task Comment',
		'description' => 'Add a comment to a task on a board.',
		'category'    => 'fluent-boards',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'task_id', 'description' ),
			'properties' => array(
				'task_id'     => array( 'type' => 'integer', 'description' => 'Task ID (required)' ),
				'description' => array( 'type' => 'string',  'description' => 'Comment text (required)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'      => array( 'type' => 'integer' ),
			'task_id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			$task_id = (int) $input['task_id'];

			// Verify the task exists.
			$task = wpFluent()->table( 'fbs_tasks' )
				->where( 'id', $task_id )
				->first();

			if ( ! $task ) {
				return fluent_abilities_error( 'not_found', 'Task not found' );
			}

			$now = current_time( 'mysql' );

			$data = array(
				'board_id'    => (int) $task->board_id,
				'task_id'     => $task_id,
				'type'        => 'comment',
				'description' => wp_kses_post( $input['description'] ),
				'created_by'  => get_current_user_id(),
				'created_at'  => $now,
				'updated_at'  => $now,
			);

			$comment_id = wpFluent()->table( 'fbs_comments' )->insertGetId( $data );

			return array(
				'success'  => true,
				'id'       => (int) $comment_id,
				'task_id'  => $task_id,
				'board_id' => (int) $task->board_id,
			);
		},
	) );

	// ===== BOARDS — CREATE/UPDATE/DELETE =====

	$reg->write( 'fluent-boards/create-board', array(
		'label'       => 'Create Board',
		'description' => 'Create a new board in FluentBoards. Supports to-do (default), roadmap, and sales-pipeline types.',
		'category'    => 'fluent-boards',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'title' ),
			'properties' => array(
				'title'       => array( 'type' => 'string', 'description' => 'Board title' ),
				'description' => array( 'type' => 'string', 'description' => 'Board description' ),
				'type'        => array( 'type' => 'string', 'description' => 'Board type: to-do (default), roadmap, or sales-pipeline' ),
				'currency'    => array( 'type' => 'string', 'description' => 'Currency code for sales-pipeline boards (default: USD)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
			'type'  => array( 'type' => 'string' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			$board_type = sanitize_text_field( $input['type'] ?? 'to-do' );
			if ( ! in_array( $board_type, array( 'to-do', 'roadmap', 'sales-pipeline' ), true ) ) {
				$board_type = 'to-do';
			}

			$now  = current_time( 'mysql' );
			$data = array(
				'title'      => sanitize_text_field( $input['title'] ),
				'type'       => $board_type,
				'currency'   => sanitize_text_field( $input['currency'] ?? 'USD' ),
				'created_by' => get_current_user_id(),
				'created_at' => $now,
				'updated_at' => $now,
			);
			if ( ! empty( $input['description'] ) ) {
				$data['description'] = wp_kses_post( $input['description'] );
			}
			$id = wpFluent()->table( 'fbs_boards' )->insertGetId( $data );

			// Create default stages for the new board.
			$default_stages = array(
				array( 'title' => 'Open',        'position' => 1, 'slug' => 'open',        'status' => 'open' ),
				array( 'title' => 'In Progress', 'position' => 2, 'slug' => 'in-progress', 'status' => 'open' ),
				array( 'title' => 'Completed',   'position' => 3, 'slug' => 'completed',   'status' => 'closed' ),
			);
			foreach ( $default_stages as $stage ) {
				wpFluent()->table( 'fbs_board_terms' )->insert( array(
					'board_id'   => (int) $id,
					'title'      => $stage['title'],
					'slug'       => $stage['slug'],
					'type'       => 'stage',
					'position'   => $stage['position'],
					'settings'   => maybe_serialize( array( 'default_task_status' => $stage['status'] ) ),
					'created_at' => $now,
					'updated_at' => $now,
				) );
			}

			// Add creator as board admin.
			wpFluent()->table( 'fbs_relations' )->insert( array(
				'object_id'   => (int) $id,
				'object_type' => 'board_user',
				'foreign_id'  => get_current_user_id(),
				'settings'    => maybe_serialize( array( 'is_admin' => true ) ),
				'preferences' => maybe_serialize( array( 'board_email_task_assign', 'board_email_comment', 'board_email_task_completed', 'board_email_due_date' ) ),
				'created_at'  => $now,
				'updated_at'  => $now,
			) );

			return array( 'success' => true, 'id' => (int) $id, 'title' => $data['title'], 'type' => $board_type );
		},
	) );

	$reg->write( 'fluent-boards/update-board', array(
		'label'       => 'Update Board',
		'description' => 'Update a board title or description.',
		'category'    => 'fluent-boards',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'          => array( 'type' => 'integer', 'description' => 'Board ID' ),
				'title'       => array( 'type' => 'string', 'description' => 'New board title' ),
				'description' => array( 'type' => 'string', 'description' => 'New board description' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$board = wpFluent()->table( 'fbs_boards' )->where( 'id', (int) $input['id'] )->first();
			if ( ! $board ) {
				return fluent_abilities_error( 'not_found', 'Board not found' );
			}
			$update = array( 'updated_at' => current_time( 'mysql' ) );
			if ( isset( $input['title'] ) ) {
				$update['title'] = sanitize_text_field( $input['title'] );
			}
			if ( isset( $input['description'] ) ) {
				$update['description'] = wp_kses_post( $input['description'] );
			}
			wpFluent()->table( 'fbs_boards' )->where( 'id', (int) $input['id'] )->update( $update );
			return array( 'success' => true, 'id' => (int) $input['id'] );
		},
	) );

	$reg->delete( 'fluent-boards/delete-board', array(
		'label'       => 'Delete Board',
		'description' => 'Permanently delete a board and all its tasks, stages, and comments.',
		'category'    => 'fluent-boards',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Board ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			$board_id = (int) $input['id'];
			$board = wpFluent()->table( 'fbs_boards' )->where( 'id', $board_id )->first();
			if ( ! $board ) {
				return fluent_abilities_error( 'not_found', 'Board not found' );
			}

			// Get all task IDs for cascade cleanup.
			$task_ids = wpFluent()->table( 'fbs_tasks' )
				->where( 'board_id', $board_id )
				->select( 'id' )
				->get();
			$task_id_list = array_map( function( $t ) { return (int) $t->id; }, $task_ids );

			// Cascade: task-level relations (assignees, labels, watchers, custom fields).
			if ( ! empty( $task_id_list ) ) {
				wpFluent()->table( 'fbs_relations' )
					->whereIn( 'object_id', $task_id_list )
					->whereIn( 'object_type', array( 'task_assignee', 'task_label', 'task_user_watch', 'task_custom_field' ) )
					->delete();
				wpFluent()->table( 'fbs_activities' )
					->whereIn( 'object_id', $task_id_list )
					->where( 'object_type', 'task_activity' )
					->delete();
			}

			// Cascade: board-level relations (board_user).
			wpFluent()->table( 'fbs_relations' )
				->where( 'object_id', $board_id )
				->where( 'object_type', 'board_user' )
				->delete();

			// Cascade: board activities.
			wpFluent()->table( 'fbs_activities' )
				->where( 'object_id', $board_id )
				->where( 'object_type', 'board_activity' )
				->delete();

			// Cascade: comments, tasks, stages/labels (board_terms), then board.
			wpFluent()->table( 'fbs_comments' )->where( 'board_id', $board_id )->delete();
			wpFluent()->table( 'fbs_tasks' )->where( 'board_id', $board_id )->delete();
			wpFluent()->table( 'fbs_board_terms' )->where( 'board_id', $board_id )->delete();
			wpFluent()->table( 'fbs_boards' )->where( 'id', $board_id )->delete();

			return array( 'success' => true, 'id' => $board_id );
		},
	) );

	$reg->delete( 'fluent-boards/delete-task', array(
		'label'       => 'Delete Board Task',
		'description' => 'Permanently delete a task with all its comments, relations, and subtasks.',
		'category'    => 'fluent-boards',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Task ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			$task_id = (int) $input['id'];
			$task = wpFluent()->table( 'fbs_tasks' )->where( 'id', $task_id )->first();
			if ( ! $task ) {
				return fluent_abilities_error( 'not_found', 'Task not found' );
			}

			// Delete subtasks first (recursive cascade).
			$subtask_ids = wpFluent()->table( 'fbs_tasks' )
				->where( 'parent_id', $task_id )
				->select( 'id' )
				->get();
			$sub_id_list = array_map( function( $t ) { return (int) $t->id; }, $subtask_ids );

			$all_task_ids = array_merge( array( $task_id ), $sub_id_list );

			// Cascade: relations (assignees, labels, watchers, custom fields).
			wpFluent()->table( 'fbs_relations' )
				->whereIn( 'object_id', $all_task_ids )
				->whereIn( 'object_type', array( 'task_assignee', 'task_label', 'task_user_watch', 'task_custom_field' ) )
				->delete();

			// Cascade: activities.
			wpFluent()->table( 'fbs_activities' )
				->whereIn( 'object_id', $all_task_ids )
				->where( 'object_type', 'task_activity' )
				->delete();

			// Cascade: comments (including subtask comments).
			wpFluent()->table( 'fbs_comments' )
				->whereIn( 'task_id', $all_task_ids )
				->delete();

			// Delete subtasks then task.
			if ( ! empty( $sub_id_list ) ) {
				wpFluent()->table( 'fbs_tasks' )->whereIn( 'id', $sub_id_list )->delete();
			}
			wpFluent()->table( 'fbs_tasks' )->where( 'id', $task_id )->delete();

			return array( 'success' => true, 'id' => $task_id );
		},
	) );

	// =========================================================================
	// STAGES — CREATE / UPDATE (P0)
	// =========================================================================

	$reg->write( 'fluent-boards/create-stage', array(
		'label'       => 'Create Stage',
		'description' => 'Create a new stage on a board. Position defaults to the end.',
		'category'    => 'fluent-boards',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'board_id', 'title' ),
			'properties' => array(
				'board_id' => array( 'type' => 'integer', 'description' => 'Board ID (required)' ),
				'title'    => array( 'type' => 'string',  'description' => 'Stage title (required)' ),
				'position' => array( 'type' => 'integer', 'description' => 'Position index (default: end of board)' ),
				'status'   => array( 'type' => 'string',  'description' => 'Default task status for this stage: open (default) or closed' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'       => array( 'type' => 'integer' ),
			'board_id' => array( 'type' => 'integer' ),
			'title'    => array( 'type' => 'string' ),
			'position' => array( 'type' => 'number' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			$board_id = (int) $input['board_id'];
			$board = wpFluent()->table( 'fbs_boards' )->where( 'id', $board_id )->first();
			if ( ! $board ) {
				return fluent_abilities_error( 'not_found', 'Board not found' );
			}

			$default_status = sanitize_text_field( $input['status'] ?? 'open' );
			if ( ! in_array( $default_status, array( 'open', 'closed' ), true ) ) {
				$default_status = 'open';
			}

			// Get last position.
			$last = wpFluent()->table( 'fbs_board_terms' )
				->where( 'board_id', $board_id )
				->where( 'type', 'stage' )
				->whereNull( 'archived_at' )
				->max( 'position' );

			$position = $last ? (float) $last + 1 : 1;

			$now = current_time( 'mysql' );
			$stage_id = wpFluent()->table( 'fbs_board_terms' )->insertGetId( array(
				'board_id'   => $board_id,
				'title'      => sanitize_text_field( $input['title'] ),
				'slug'       => sanitize_title( $input['title'] ),
				'type'       => 'stage',
				'position'   => $position,
				'settings'   => maybe_serialize( array(
					'default_task_status'    => $default_status,
					'default_task_assignees' => array(),
				) ),
				'created_at' => $now,
				'updated_at' => $now,
			) );

			return array(
				'success'  => true,
				'id'       => (int) $stage_id,
				'board_id' => $board_id,
				'title'    => sanitize_text_field( $input['title'] ),
				'position' => $position,
			);
		},
	) );

	$reg->write( 'fluent-boards/update-stage', array(
		'label'       => 'Update Stage',
		'description' => 'Update a stage title, color, background color, or default task status.',
		'category'    => 'fluent-boards',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'board_id', 'stage_id' ),
			'properties' => array(
				'board_id' => array( 'type' => 'integer', 'description' => 'Board ID (required)' ),
				'stage_id' => array( 'type' => 'integer', 'description' => 'Stage ID (required)' ),
				'title'    => array( 'type' => 'string',  'description' => 'New stage title' ),
				'color'    => array( 'type' => 'string',  'description' => 'Text color hex code' ),
				'bg_color' => array( 'type' => 'string',  'description' => 'Background color hex code' ),
				'status'   => array( 'type' => 'string',  'description' => 'Default task status: open or closed' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'      => array( 'type' => 'integer' ),
			'updated' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
		) ),
		'callback' => function( $input ) {
			$board_id = (int) $input['board_id'];
			$stage_id = (int) $input['stage_id'];

			$stage = wpFluent()->table( 'fbs_board_terms' )
				->where( 'id', $stage_id )
				->where( 'board_id', $board_id )
				->where( 'type', 'stage' )
				->first();

			if ( ! $stage ) {
				return fluent_abilities_error( 'not_found', 'Stage not found on this board' );
			}

			$data    = array();
			$updated = array();

			if ( isset( $input['title'] ) ) {
				$data['title'] = sanitize_text_field( $input['title'] );
				$updated[]     = 'title';
			}
			if ( isset( $input['color'] ) ) {
				$data['color'] = sanitize_text_field( $input['color'] );
				$updated[]     = 'color';
			}
			if ( isset( $input['bg_color'] ) ) {
				$data['bg_color'] = sanitize_text_field( $input['bg_color'] );
				$updated[]        = 'bg_color';
			}
			if ( isset( $input['status'] ) ) {
				$status = sanitize_text_field( $input['status'] );
				if ( in_array( $status, array( 'open', 'closed' ), true ) ) {
					$settings = maybe_unserialize( $stage->settings ?? '' );
					if ( ! is_array( $settings ) ) {
						$settings = array();
					}
					$settings['default_task_status'] = $status;
					$data['settings'] = maybe_serialize( $settings );
					$updated[]        = 'status';
				}
			}

			if ( empty( $data ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'No fields to update' );
			}

			$data['updated_at'] = current_time( 'mysql' );

			wpFluent()->table( 'fbs_board_terms' )
				->where( 'id', $stage_id )
				->update( $data );

			return array(
				'success' => true,
				'id'      => $stage_id,
				'updated' => $updated,
			);
		},
	) );

	// =========================================================================
	// TASK COMMENTS — UPDATE / DELETE (P0)
	// =========================================================================

	$reg->write( 'fluent-boards/update-task-comment', array(
		'label'       => 'Update Task Comment',
		'description' => 'Update the text of an existing task comment.',
		'category'    => 'fluent-boards',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'comment_id', 'description' ),
			'properties' => array(
				'comment_id'  => array( 'type' => 'integer', 'description' => 'Comment ID (required)' ),
				'description' => array( 'type' => 'string',  'description' => 'New comment text (required)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'      => array( 'type' => 'integer' ),
			'task_id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$comment_id = (int) $input['comment_id'];

			$comment = wpFluent()->table( 'fbs_comments' )
				->where( 'id', $comment_id )
				->first();

			if ( ! $comment ) {
				return fluent_abilities_error( 'not_found', 'Comment not found' );
			}

			wpFluent()->table( 'fbs_comments' )
				->where( 'id', $comment_id )
				->update( array(
					'description' => wp_kses_post( $input['description'] ),
					'updated_at'  => current_time( 'mysql' ),
				) );

			return array(
				'success'  => true,
				'id'       => $comment_id,
				'task_id'  => (int) $comment->task_id,
				'board_id' => (int) $comment->board_id,
			);
		},
	) );

	$reg->delete( 'fluent-boards/delete-task-comment', array(
		'label'       => 'Delete Task Comment',
		'description' => 'Delete a task comment and its replies.',
		'category'    => 'fluent-boards',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'comment_id' ),
			'properties' => array(
				'comment_id' => array( 'type' => 'integer', 'description' => 'Comment ID (required)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			$comment_id = (int) $input['comment_id'];

			$comment = wpFluent()->table( 'fbs_comments' )
				->where( 'id', $comment_id )
				->first();

			if ( ! $comment ) {
				return fluent_abilities_error( 'not_found', 'Comment not found' );
			}

			// Delete replies first.
			wpFluent()->table( 'fbs_comments' )
				->where( 'parent_id', $comment_id )
				->delete();

			// Delete the comment.
			wpFluent()->table( 'fbs_comments' )
				->where( 'id', $comment_id )
				->delete();

			return array( 'success' => true, 'id' => $comment_id );
		},
	) );

	// =========================================================================
	// TASK OPS — MOVE / ASSIGN / UNASSIGN (P1)
	// =========================================================================

	$reg->write( 'fluent-boards/move-task', array(
		'label'       => 'Move Task',
		'description' => 'Move a task to a different stage and/or position within the same board, or to a different board entirely. Cross-board moves reset assignees, labels, and comments.',
		'category'    => 'fluent-boards',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'task_id' ),
			'properties' => array(
				'task_id'      => array( 'type' => 'integer', 'description' => 'Task ID (required)' ),
				'stage_id'     => array( 'type' => 'integer', 'description' => 'Target stage ID' ),
				'position'     => array( 'type' => 'integer', 'description' => 'Position index within stage' ),
				'new_board_id' => array( 'type' => 'integer', 'description' => 'Target board ID for cross-board move' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'       => array( 'type' => 'integer' ),
			'board_id' => array( 'type' => 'integer' ),
			'stage_id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$task_id = (int) $input['task_id'];
			$task = wpFluent()->table( 'fbs_tasks' )->where( 'id', $task_id )->first();
			if ( ! $task ) {
				return fluent_abilities_error( 'not_found', 'Task not found' );
			}

			$board_id = (int) $task->board_id;
			$data     = array( 'updated_at' => current_time( 'mysql' ) );

			// Cross-board move.
			if ( ! empty( $input['new_board_id'] ) && (int) $input['new_board_id'] !== $board_id ) {
				$new_board_id = (int) $input['new_board_id'];
				$new_board = wpFluent()->table( 'fbs_boards' )->where( 'id', $new_board_id )->first();
				if ( ! $new_board ) {
					return fluent_abilities_error( 'not_found', 'Target board not found' );
				}

				$data['board_id'] = $new_board_id;
				$data['type']     = ( $new_board->type ?? '' ) === 'roadmap' ? 'roadmap' : 'task';

				// Default to first stage of new board if no stage_id provided.
				if ( empty( $input['stage_id'] ) ) {
					$first_stage = wpFluent()->table( 'fbs_board_terms' )
						->where( 'board_id', $new_board_id )
						->where( 'type', 'stage' )
						->whereNull( 'archived_at' )
						->orderBy( 'position', 'ASC' )
						->first();
					$data['stage_id'] = $first_stage ? (int) $first_stage->id : 0;
				}

				// Clean up board-specific relations.
				wpFluent()->table( 'fbs_relations' )
					->where( 'object_id', $task_id )
					->whereIn( 'object_type', array( 'task_assignee', 'task_label', 'task_user_watch', 'task_custom_field' ) )
					->delete();
				wpFluent()->table( 'fbs_comments' )->where( 'task_id', $task_id )->delete();
				wpFluent()->table( 'fbs_activities' )->where( 'object_id', $task_id )->where( 'object_type', 'task_activity' )->delete();

				$board_id = $new_board_id;
			}

			// Stage move (same board or explicit stage on new board).
			if ( ! empty( $input['stage_id'] ) ) {
				$target_stage_id = (int) $input['stage_id'];
				$stage = wpFluent()->table( 'fbs_board_terms' )
					->where( 'id', $target_stage_id )
					->where( 'board_id', $board_id )
					->where( 'type', 'stage' )
					->first();
				if ( ! $stage ) {
					return fluent_abilities_error( 'ability_invalid_input', 'Stage not found on target board' );
				}
				$data['stage_id'] = $target_stage_id;
			}

			// Position.
			if ( isset( $input['position'] ) ) {
				$data['position'] = (int) $input['position'];
			}

			wpFluent()->table( 'fbs_tasks' )->where( 'id', $task_id )->update( $data );

			$updated = wpFluent()->table( 'fbs_tasks' )->where( 'id', $task_id )->first();

			return array(
				'success'  => true,
				'id'       => $task_id,
				'board_id' => (int) $updated->board_id,
				'stage_id' => (int) $updated->stage_id,
			);
		},
	) );

	$reg->write( 'fluent-boards/assign-task', array(
		'label'       => 'Assign User to Task',
		'description' => 'Assign a user to a task. The user is also added as a board member if not already.',
		'category'    => 'fluent-boards',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'task_id', 'user_id' ),
			'properties' => array(
				'task_id' => array( 'type' => 'integer', 'description' => 'Task ID (required)' ),
				'user_id' => array( 'type' => 'integer', 'description' => 'WordPress user ID to assign (required)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'task_id' => array( 'type' => 'integer' ),
			'user_id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$task_id = (int) $input['task_id'];
			$user_id = (int) $input['user_id'];

			$task = wpFluent()->table( 'fbs_tasks' )->where( 'id', $task_id )->first();
			if ( ! $task ) {
				return fluent_abilities_error( 'not_found', 'Task not found' );
			}

			$user = get_userdata( $user_id );
			if ( ! $user ) {
				return fluent_abilities_error( 'not_found', 'User not found' );
			}

			// Check if already assigned.
			$already = wpFluent()->table( 'fbs_relations' )
				->where( 'object_id', $task_id )
				->where( 'object_type', 'task_assignee' )
				->where( 'foreign_id', $user_id )
				->first();

			if ( $already ) {
				return array( 'success' => true, 'task_id' => $task_id, 'user_id' => $user_id, 'message' => 'Already assigned' );
			}

			// Ensure user is a board member.
			$is_member = wpFluent()->table( 'fbs_relations' )
				->where( 'object_id', (int) $task->board_id )
				->where( 'object_type', 'board_user' )
				->where( 'foreign_id', $user_id )
				->first();

			if ( ! $is_member ) {
				$now = current_time( 'mysql' );
				wpFluent()->table( 'fbs_relations' )->insert( array(
					'object_id'   => (int) $task->board_id,
					'object_type' => 'board_user',
					'foreign_id'  => $user_id,
					'settings'    => maybe_serialize( array( 'is_admin' => false ) ),
					'preferences' => maybe_serialize( array( 'board_email_task_assign', 'board_email_comment', 'board_email_task_completed', 'board_email_due_date' ) ),
					'created_at'  => $now,
					'updated_at'  => $now,
				) );
			}

			$now = current_time( 'mysql' );

			// Add assignee relation.
			wpFluent()->table( 'fbs_relations' )->insert( array(
				'object_id'   => $task_id,
				'object_type' => 'task_assignee',
				'foreign_id'  => $user_id,
				'created_at'  => $now,
				'updated_at'  => $now,
			) );

			// Add watcher relation.
			$is_watcher = wpFluent()->table( 'fbs_relations' )
				->where( 'object_id', $task_id )
				->where( 'object_type', 'task_user_watch' )
				->where( 'foreign_id', $user_id )
				->first();

			if ( ! $is_watcher ) {
				wpFluent()->table( 'fbs_relations' )->insert( array(
					'object_id'   => $task_id,
					'object_type' => 'task_user_watch',
					'foreign_id'  => $user_id,
					'created_at'  => $now,
					'updated_at'  => $now,
				) );
			}

			return array( 'success' => true, 'task_id' => $task_id, 'user_id' => $user_id );
		},
	) );

	$reg->write( 'fluent-boards/unassign-task', array(
		'label'       => 'Unassign User from Task',
		'description' => 'Remove a user assignment from a task. Also removes the watcher relation.',
		'category'    => 'fluent-boards',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'task_id', 'user_id' ),
			'properties' => array(
				'task_id' => array( 'type' => 'integer', 'description' => 'Task ID (required)' ),
				'user_id' => array( 'type' => 'integer', 'description' => 'WordPress user ID to unassign (required)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'task_id' => array( 'type' => 'integer' ),
			'user_id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$task_id = (int) $input['task_id'];
			$user_id = (int) $input['user_id'];

			$task = wpFluent()->table( 'fbs_tasks' )->where( 'id', $task_id )->first();
			if ( ! $task ) {
				return fluent_abilities_error( 'not_found', 'Task not found' );
			}

			// Remove assignee relation.
			wpFluent()->table( 'fbs_relations' )
				->where( 'object_id', $task_id )
				->where( 'object_type', 'task_assignee' )
				->where( 'foreign_id', $user_id )
				->delete();

			// Remove watcher relation.
			wpFluent()->table( 'fbs_relations' )
				->where( 'object_id', $task_id )
				->where( 'object_type', 'task_user_watch' )
				->where( 'foreign_id', $user_id )
				->delete();

			return array( 'success' => true, 'task_id' => $task_id, 'user_id' => $user_id );
		},
	) );

	// =========================================================================
	// BOARD OPS — ARCHIVE / RESTORE (P1)
	// =========================================================================

	$reg->write( 'fluent-boards/archive-board', array(
		'label'       => 'Archive Board',
		'description' => 'Archive a board by setting its archived_at timestamp.',
		'category'    => 'fluent-boards',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Board ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$board_id = (int) $input['id'];
			$board = wpFluent()->table( 'fbs_boards' )->where( 'id', $board_id )->first();
			if ( ! $board ) {
				return fluent_abilities_error( 'not_found', 'Board not found' );
			}
			wpFluent()->table( 'fbs_boards' )->where( 'id', $board_id )->update( array(
				'archived_at' => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			) );
			return array( 'success' => true, 'id' => $board_id );
		},
	) );

	$reg->write( 'fluent-boards/restore-board', array(
		'label'       => 'Restore Board',
		'description' => 'Restore an archived board by clearing its archived_at timestamp.',
		'category'    => 'fluent-boards',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Board ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$board_id = (int) $input['id'];
			$board = wpFluent()->table( 'fbs_boards' )->where( 'id', $board_id )->first();
			if ( ! $board ) {
				return fluent_abilities_error( 'not_found', 'Board not found' );
			}
			wpFluent()->table( 'fbs_boards' )->where( 'id', $board_id )->update( array(
				'archived_at' => null,
				'updated_at'  => current_time( 'mysql' ),
			) );
			return array( 'success' => true, 'id' => $board_id );
		},
	) );

	// =========================================================================
	// STAGE OPS — ARCHIVE / RESTORE / REORDER (P1)
	// =========================================================================

	$reg->write( 'fluent-boards/archive-stage', array(
		'label'       => 'Archive Stage',
		'description' => 'Archive a stage by setting its archived_at timestamp.',
		'category'    => 'fluent-boards',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'board_id', 'stage_id' ),
			'properties' => array(
				'board_id' => array( 'type' => 'integer', 'description' => 'Board ID (required)' ),
				'stage_id' => array( 'type' => 'integer', 'description' => 'Stage ID (required)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$board_id = (int) $input['board_id'];
			$stage_id = (int) $input['stage_id'];

			$stage = wpFluent()->table( 'fbs_board_terms' )
				->where( 'id', $stage_id )
				->where( 'board_id', $board_id )
				->where( 'type', 'stage' )
				->first();

			if ( ! $stage ) {
				return fluent_abilities_error( 'not_found', 'Stage not found on this board' );
			}

			wpFluent()->table( 'fbs_board_terms' )->where( 'id', $stage_id )->update( array(
				'archived_at' => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			) );

			return array( 'success' => true, 'id' => $stage_id );
		},
	) );

	$reg->write( 'fluent-boards/restore-stage', array(
		'label'       => 'Restore Stage',
		'description' => 'Restore an archived stage by clearing its archived_at timestamp.',
		'category'    => 'fluent-boards',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'board_id', 'stage_id' ),
			'properties' => array(
				'board_id' => array( 'type' => 'integer', 'description' => 'Board ID (required)' ),
				'stage_id' => array( 'type' => 'integer', 'description' => 'Stage ID (required)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$board_id = (int) $input['board_id'];
			$stage_id = (int) $input['stage_id'];

			$stage = wpFluent()->table( 'fbs_board_terms' )
				->where( 'id', $stage_id )
				->where( 'board_id', $board_id )
				->where( 'type', 'stage' )
				->first();

			if ( ! $stage ) {
				return fluent_abilities_error( 'not_found', 'Stage not found on this board' );
			}

			wpFluent()->table( 'fbs_board_terms' )->where( 'id', $stage_id )->update( array(
				'archived_at' => null,
				'updated_at'  => current_time( 'mysql' ),
			) );

			return array( 'success' => true, 'id' => $stage_id );
		},
	) );

	$reg->write( 'fluent-boards/reorder-stages', array(
		'label'       => 'Reorder Stages',
		'description' => 'Set the order of all stages on a board by providing an array of stage IDs in the desired order.',
		'category'    => 'fluent-boards',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'board_id', 'stage_ids' ),
			'properties' => array(
				'board_id'  => array( 'type' => 'integer', 'description' => 'Board ID (required)' ),
				'stage_ids' => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'integer' ),
					'description' => 'Array of stage IDs in the desired display order (required)',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'reordered' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$board_id  = (int) $input['board_id'];
			$stage_ids = $input['stage_ids'] ?? array();

			$board = wpFluent()->table( 'fbs_boards' )->where( 'id', $board_id )->first();
			if ( ! $board ) {
				return fluent_abilities_error( 'not_found', 'Board not found' );
			}

			if ( empty( $stage_ids ) || ! is_array( $stage_ids ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'stage_ids must be a non-empty array' );
			}

			$now     = current_time( 'mysql' );
			$count   = 0;

			foreach ( $stage_ids as $index => $sid ) {
				$sid = (int) $sid;
				$position = $index + 1;

				$affected = wpFluent()->table( 'fbs_board_terms' )
					->where( 'id', $sid )
					->where( 'board_id', $board_id )
					->where( 'type', 'stage' )
					->update( array(
						'position'   => $position,
						'updated_at' => $now,
					) );

				if ( $affected ) {
					$count++;
				}
			}

			return array( 'success' => true, 'reordered' => $count );
		},
	) );

	// =========================================================================
	// Load sub-module files.
	// =========================================================================
	$boards_dir = FLUENT_ABILITIES_PATH . 'includes/boards/';
	require_once $boards_dir . 'abilities-labels.php';
	require_once $boards_dir . 'abilities-members.php';
	require_once $boards_dir . 'abilities-activities.php';

}, 100 );
