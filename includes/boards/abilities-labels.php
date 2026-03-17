<?php
/**
 * Fluent Boards — Label Abilities
 *
 * Board label CRUD + task label assignment/removal.
 * 6 abilities in the 'fluent-boards' category.
 *
 * Labels are stored in fbs_board_terms (type='label').
 * Task-label relations are stored in fbs_relations (object_type='task_label').
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

// This file is loaded inside the wp_abilities_api_init callback in abilities.php.
// $reg (Fluent_Abilities_Registrar) is already available in scope.

// =========================================================================
// LIST LABELS
// =========================================================================

$reg->read( 'fluent-boards/list-labels', array(
	'label'       => 'List Board Labels',
	'description' => 'List all labels for a board.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer', 'description' => 'Board ID (required)' ),
		),
	),
	'output_schema' => fluent_abilities_schema_collection_output( 'labels', array(
		'id'       => array( 'type' => 'integer' ),
		'title'    => array( 'type' => array( 'string', 'null' ) ),
		'slug'     => array( 'type' => array( 'string', 'null' ) ),
		'color'    => array( 'type' => array( 'string', 'null' ) ),
		'bg_color' => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$board_id = (int) $input['board_id'];

		$board = wpFluent()->table( 'fbs_boards' )->where( 'id', $board_id )->first();
		if ( ! $board ) {
			return fluent_abilities_error( 'not_found', 'Board not found' );
		}

		$labels = wpFluent()->table( 'fbs_board_terms' )
			->where( 'board_id', $board_id )
			->where( 'type', 'label' )
			->orderBy( 'created_at', 'ASC' )
			->get();

		$items = array();
		foreach ( $labels as $label ) {
			$items[] = array(
				'id'       => (int) $label->id,
				'title'    => $label->title ?? null,
				'slug'     => $label->slug ?? null,
				'color'    => $label->color ?? null,
				'bg_color' => $label->bg_color ?? null,
			);
		}

		return array(
			'labels'   => $items,
			'total'    => count( $items ),
			'board_id' => $board_id,
		);
	},
) );

// =========================================================================
// CREATE LABEL
// =========================================================================

$reg->write( 'fluent-boards/create-label', array(
	'label'       => 'Create Label',
	'description' => 'Create a new label on a board.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'bg_color' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer', 'description' => 'Board ID (required)' ),
			'title'    => array( 'type' => 'string',  'description' => 'Label title (optional)' ),
			'color'    => array( 'type' => 'string',  'description' => 'Text color hex code' ),
			'bg_color' => array( 'type' => 'string',  'description' => 'Background color hex code (required)' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'id'       => array( 'type' => 'integer' ),
		'board_id' => array( 'type' => 'integer' ),
	) ),
	'annotations' => array( 'idempotent' => false ),
	'callback' => function( $input ) {
		$board_id = (int) $input['board_id'];

		$board = wpFluent()->table( 'fbs_boards' )->where( 'id', $board_id )->first();
		if ( ! $board ) {
			return fluent_abilities_error( 'not_found', 'Board not found' );
		}

		$now = current_time( 'mysql' );

		$label_id = wpFluent()->table( 'fbs_board_terms' )->insertGetId( array(
			'board_id'   => $board_id,
			'title'      => sanitize_text_field( $input['title'] ?? '' ),
			'slug'       => sanitize_title( $input['title'] ?? '' ),
			'type'       => 'label',
			'position'   => 0,
			'bg_color'   => sanitize_text_field( $input['bg_color'] ),
			'color'      => sanitize_text_field( $input['color'] ?? '' ),
			'created_at' => $now,
			'updated_at' => $now,
		) );

		return array( 'success' => true, 'id' => (int) $label_id, 'board_id' => $board_id );
	},
) );

// =========================================================================
// UPDATE LABEL
// =========================================================================

$reg->write( 'fluent-boards/update-label', array(
	'label'       => 'Update Label',
	'description' => 'Update a label title, color, or background color.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'label_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer', 'description' => 'Board ID (required)' ),
			'label_id' => array( 'type' => 'integer', 'description' => 'Label ID (required)' ),
			'title'    => array( 'type' => 'string',  'description' => 'New label title' ),
			'color'    => array( 'type' => 'string',  'description' => 'New text color hex code' ),
			'bg_color' => array( 'type' => 'string',  'description' => 'New background color hex code' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'id'      => array( 'type' => 'integer' ),
		'updated' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
	) ),
	'callback' => function( $input ) {
		$board_id = (int) $input['board_id'];
		$label_id = (int) $input['label_id'];

		$label = wpFluent()->table( 'fbs_board_terms' )
			->where( 'id', $label_id )
			->where( 'board_id', $board_id )
			->where( 'type', 'label' )
			->first();

		if ( ! $label ) {
			return fluent_abilities_error( 'not_found', 'Label not found on this board' );
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

		if ( empty( $data ) ) {
			return fluent_abilities_error( 'ability_invalid_input', 'No fields to update' );
		}

		$data['updated_at'] = current_time( 'mysql' );

		wpFluent()->table( 'fbs_board_terms' )
			->where( 'id', $label_id )
			->update( $data );

		return array( 'success' => true, 'id' => $label_id, 'updated' => $updated );
	},
) );

// =========================================================================
// DELETE LABEL
// =========================================================================

$reg->delete( 'fluent-boards/delete-label', array(
	'label'       => 'Delete Label',
	'description' => 'Delete a label from a board. Also removes the label from all tasks.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'label_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer', 'description' => 'Board ID (required)' ),
			'label_id' => array( 'type' => 'integer', 'description' => 'Label ID (required)' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'id' => array( 'type' => 'integer' ),
	) ),
	'annotations' => array( 'idempotent' => false ),
	'callback' => function( $input ) {
		$board_id = (int) $input['board_id'];
		$label_id = (int) $input['label_id'];

		$label = wpFluent()->table( 'fbs_board_terms' )
			->where( 'id', $label_id )
			->where( 'board_id', $board_id )
			->where( 'type', 'label' )
			->first();

		if ( ! $label ) {
			return fluent_abilities_error( 'not_found', 'Label not found on this board' );
		}

		// Remove all task-label relations for this label.
		wpFluent()->table( 'fbs_relations' )
			->where( 'foreign_id', $label_id )
			->where( 'object_type', 'task_label' )
			->delete();

		// Delete the label.
		wpFluent()->table( 'fbs_board_terms' )
			->where( 'id', $label_id )
			->delete();

		return array( 'success' => true, 'id' => $label_id );
	},
) );

// =========================================================================
// ASSIGN LABEL TO TASK
// =========================================================================

$reg->write( 'fluent-boards/assign-label', array(
	'label'       => 'Assign Label to Task',
	'description' => 'Assign an existing board label to a task.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'task_id', 'label_id' ),
		'properties' => array(
			'task_id'  => array( 'type' => 'integer', 'description' => 'Task ID (required)' ),
			'label_id' => array( 'type' => 'integer', 'description' => 'Label ID (required)' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'task_id'  => array( 'type' => 'integer' ),
		'label_id' => array( 'type' => 'integer' ),
	) ),
	'callback' => function( $input ) {
		$task_id  = (int) $input['task_id'];
		$label_id = (int) $input['label_id'];

		$task = wpFluent()->table( 'fbs_tasks' )->where( 'id', $task_id )->first();
		if ( ! $task ) {
			return fluent_abilities_error( 'not_found', 'Task not found' );
		}

		// Verify label exists on the same board.
		$label = wpFluent()->table( 'fbs_board_terms' )
			->where( 'id', $label_id )
			->where( 'board_id', (int) $task->board_id )
			->where( 'type', 'label' )
			->first();

		if ( ! $label ) {
			return fluent_abilities_error( 'not_found', 'Label not found on this board' );
		}

		// Check if already assigned.
		$already = wpFluent()->table( 'fbs_relations' )
			->where( 'object_id', $task_id )
			->where( 'object_type', 'task_label' )
			->where( 'foreign_id', $label_id )
			->first();

		if ( $already ) {
			return array( 'success' => true, 'task_id' => $task_id, 'label_id' => $label_id, 'message' => 'Already assigned' );
		}

		$now = current_time( 'mysql' );
		wpFluent()->table( 'fbs_relations' )->insert( array(
			'object_id'   => $task_id,
			'object_type' => 'task_label',
			'foreign_id'  => $label_id,
			'created_at'  => $now,
			'updated_at'  => $now,
		) );

		return array( 'success' => true, 'task_id' => $task_id, 'label_id' => $label_id );
	},
) );

// =========================================================================
// REMOVE LABEL FROM TASK
// =========================================================================

$reg->delete( 'fluent-boards/remove-label', array(
	'label'       => 'Remove Label from Task',
	'description' => 'Remove a label from a task.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'task_id', 'label_id' ),
		'properties' => array(
			'task_id'  => array( 'type' => 'integer', 'description' => 'Task ID (required)' ),
			'label_id' => array( 'type' => 'integer', 'description' => 'Label ID (required)' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'task_id'  => array( 'type' => 'integer' ),
		'label_id' => array( 'type' => 'integer' ),
	) ),
	'callback' => function( $input ) {
		$task_id  = (int) $input['task_id'];
		$label_id = (int) $input['label_id'];

		$task = wpFluent()->table( 'fbs_tasks' )->where( 'id', $task_id )->first();
		if ( ! $task ) {
			return fluent_abilities_error( 'not_found', 'Task not found' );
		}

		wpFluent()->table( 'fbs_relations' )
			->where( 'object_id', $task_id )
			->where( 'object_type', 'task_label' )
			->where( 'foreign_id', $label_id )
			->delete();

		return array( 'success' => true, 'task_id' => $task_id, 'label_id' => $label_id );
	},
) );
