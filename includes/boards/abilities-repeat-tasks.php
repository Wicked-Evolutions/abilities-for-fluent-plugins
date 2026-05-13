<?php
/**
 * Fluent Boards — Repeat Task Rules (Research §4.16)
 *
 * 2 abilities. Tier: pro.
 *
 * Repeat-task rules stored in fbs_metas with object_type='repeat_task_rule'.
 * Vendor cron job runs daily to auto-create tasks matching active rules.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

// =========================================================================
// §4.16.1 — create-repeat-task-rule (idempotent:false)
// =========================================================================
$reg->write( 'fluent-boards/create-repeat-task-rule', array(
	'label'       => 'Create Repeat Task Rule (Pro)',
	'description' => 'Create a rule that auto-creates copies of a source task on a recurrence schedule (daily / weekly / monthly / custom).',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'task_id', 'recurrence' ),
		'properties' => array(
			'board_id'         => array( 'type' => 'integer' ),
			'task_id'          => array( 'type' => 'integer' ),
			'recurrence'       => array( 'type' => 'string', 'enum' => array( 'daily', 'weekly', 'monthly', 'custom' ) ),
			'auto_create_date' => array( 'type' => 'string', 'description' => 'Next scheduled creation date (MySQL datetime).' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'rule_id'  => array( 'type' => 'integer' ),
		'board_id' => array( 'type' => 'integer' ),
		'task_id'  => array( 'type' => 'integer' ),
	) ),
	'annotations' => array( 'idempotent' => false ),
	'callback'    => function( $input ) {
		$board_id   = (int) $input['board_id'];
		$task_id    = (int) $input['task_id'];
		$recurrence = sanitize_key( $input['recurrence'] ?? '' );
		if ( ! in_array( $recurrence, array( 'daily', 'weekly', 'monthly', 'custom' ), true ) ) {
			return fluent_abilities_error( 'ability_invalid_input', 'recurrence must be one of daily, weekly, monthly, custom.' );
		}
		if ( ! wpFluent()->table( 'fbs_tasks' )->where( 'id', $task_id )->where( 'board_id', $board_id )->first() ) {
			return fluent_abilities_error( 'not_found', 'Task not found on this board.' );
		}
		$now    = current_time( 'mysql' );
		$new_id = wpFluent()->table( 'fbs_metas' )->insert( array(
			'object_id'   => $task_id,
			'object_type' => 'repeat_task_rule',
			'key'         => 'rule',
			'value'       => maybe_serialize( array(
				'board_id'         => $board_id,
				'recurrence'       => $recurrence,
				'auto_create_date' => sanitize_text_field( $input['auto_create_date'] ?? '' ),
			) ),
			'created_at'  => $now,
			'updated_at'  => $now,
		) );
		return array( 'success' => true, 'rule_id' => (int) $new_id, 'board_id' => $board_id, 'task_id' => $task_id );
	},
) );

// =========================================================================
// §4.16.2 — list-repeat-task-rules
// =========================================================================
$reg->read( 'fluent-boards/list-repeat-task-rules', array(
	'label'       => 'List Repeat Task Rules (Pro)',
	'description' => 'List repeat-task rules for a board (optionally filtered by task_id).',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
			'task_id'  => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_collection_output( 'rules', array(
		'rule_id'          => array( 'type' => 'integer' ),
		'task_id'          => array( 'type' => 'integer' ),
		'recurrence'       => array( 'type' => array( 'string', 'null' ) ),
		'auto_create_date' => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$board_id = (int) $input['board_id'];
		$rows     = wpFluent()->table( 'fbs_metas' )->where( 'object_type', 'repeat_task_rule' )->get();
		$items    = array();
		foreach ( $rows as $r ) {
			$meta = maybe_unserialize( $r->value ?? '' );
			$meta = is_array( $meta ) ? $meta : array();
			if ( (int) ( $meta['board_id'] ?? 0 ) !== $board_id ) { continue; }
			if ( ! empty( $input['task_id'] ) && (int) $r->object_id !== (int) $input['task_id'] ) { continue; }
			$items[] = array(
				'rule_id'          => (int) $r->id,
				'task_id'          => (int) $r->object_id,
				'recurrence'       => $meta['recurrence'] ?? null,
				'auto_create_date' => $meta['auto_create_date'] ?? null,
			);
		}
		return array( 'rules' => $items, 'total' => count( $items ) );
	},
) );
