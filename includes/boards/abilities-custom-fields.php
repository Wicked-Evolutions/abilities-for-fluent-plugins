<?php
/**
 * Fluent Boards — Custom Fields (Research §4.11)
 *
 * 7 abilities. Tier: pro.
 *
 * CustomField is a BoardTerm with type='custom-field'. Field VALUES per task
 * are stored in fbs_relations with object_type=TASK_CUSTOM_FIELD
 * (object_id=task_id, foreign_id=custom_field_id, settings={ value }).
 *
 * §7.Q3 — custom-field type enum is left intentionally open here; vendor
 * supports at least: text, number, date, select, multi-select, checkbox.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

// =========================================================================
// §4.11.1 — list-custom-fields
// =========================================================================
$reg->read( 'fluent-boards/list-custom-fields', array(
	'label'         => 'List Custom Fields (Pro)',
	'description'   => 'List custom-field definitions for a board (fbs_board_terms WHERE type=custom-field).',
	'category'      => 'fluent-boards',
	'input_schema'  => array(
		'type'       => 'object',
		'required'   => array( 'board_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_collection_output( 'custom_fields', array(
		'id'       => array( 'type' => 'integer' ),
		'title'    => array( 'type' => array( 'string', 'null' ) ),
		'type'     => array( 'type' => array( 'string', 'null' ) ),
		'position' => array( 'type' => array( 'number', 'null' ) ),
		'settings' => array( 'type' => array( 'object', 'array', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$board_id = (int) $input['board_id'];
		$rows     = wpFluent()->table( 'fbs_board_terms' )->where( 'board_id', $board_id )->where( 'type', 'custom-field' )->orderBy( 'position', 'ASC' )->get();
		$items    = array();
		foreach ( $rows as $r ) {
			$settings = maybe_unserialize( $r->settings ?? '' );
			$items[]  = array(
				'id'       => (int) $r->id,
				'title'    => $r->title ?? '',
				'type'     => is_array( $settings ) ? ( $settings['type'] ?? null ) : null,
				'position' => $r->position ?? null,
				'settings' => $settings ?: null,
			);
		}
		return array( 'custom_fields' => $items, 'total' => count( $items ) );
	},
) );

// =========================================================================
// §4.11.2 — create-custom-field (idempotent:false)
// =========================================================================
$reg->write( 'fluent-boards/create-custom-field', array(
	'label'       => 'Create Custom Field (Pro)',
	'description' => 'Create a new custom field on a board. type must be one of vendor-supported strings (text, number, date, select, multi-select, checkbox).',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'title', 'type' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
			'title'    => array( 'type' => 'string' ),
			'type'     => array( 'type' => 'string' ),
			'settings' => array(
				'type'       => 'object',
				'description' => 'Type-specific config (e.g. {options: ["a", "b"]} for select; {format: "ISO8601"} for date).',
			),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'id'       => array( 'type' => 'integer' ),
		'board_id' => array( 'type' => 'integer' ),
	) ),
	'annotations' => array( 'idempotent' => false ),
	'callback'    => function( $input ) {
		$board_id = (int) $input['board_id'];
		$title    = sanitize_text_field( $input['title'] ?? '' );
		$type     = sanitize_key( $input['type'] ?? '' );
		if ( ! $title || ! $type ) {
			return fluent_abilities_error( 'ability_invalid_input', 'title and type are required.' );
		}
		$settings              = is_array( $input['settings'] ?? null ) ? $input['settings'] : array();
		$settings['type']      = $type;
		$now                   = current_time( 'mysql' );
		$max_pos               = (int) ( wpFluent()->table( 'fbs_board_terms' )->where( 'board_id', $board_id )->where( 'type', 'custom-field' )->max( 'position' ) ?? 0 );
		$new_id = wpFluent()->table( 'fbs_board_terms' )->insertGetId( array(
			'board_id'   => $board_id,
			'type'       => 'custom-field',
			'title'      => $title,
			'position'   => $max_pos + 1,
			'settings'   => maybe_serialize( $settings ),
			'created_at' => $now,
			'updated_at' => $now,
		) );
		return array( 'success' => true, 'id' => (int) $new_id, 'board_id' => $board_id );
	},
) );

// =========================================================================
// §4.11.3 — update-custom-field
// =========================================================================
$reg->write( 'fluent-boards/update-custom-field', array(
	'label'       => 'Update Custom Field (Pro)',
	'description' => 'Update a custom-field title and/or settings.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'custom_field_id' ),
		'properties' => array(
			'board_id'        => array( 'type' => 'integer' ),
			'custom_field_id' => array( 'type' => 'integer' ),
			'title'           => array( 'type' => 'string' ),
			'settings'        => array( 'type' => 'object' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'custom_field_id' => array( 'type' => 'integer' ) ) ),
	'callback'     => function( $input ) {
		$cf_id    = (int) $input['custom_field_id'];
		$board_id = (int) $input['board_id'];
		$row      = wpFluent()->table( 'fbs_board_terms' )->where( 'id', $cf_id )->where( 'board_id', $board_id )->where( 'type', 'custom-field' )->first();
		if ( ! $row ) {
			return fluent_abilities_error( 'not_found', 'Custom field not found on this board.' );
		}
		$update = array( 'updated_at' => current_time( 'mysql' ) );
		if ( isset( $input['title'] ) ) {
			$update['title'] = sanitize_text_field( $input['title'] );
		}
		if ( isset( $input['settings'] ) && is_array( $input['settings'] ) ) {
			$existing = maybe_unserialize( $row->settings ?? '' );
			$existing = is_array( $existing ) ? $existing : array();
			$update['settings'] = maybe_serialize( array_replace_recursive( $existing, $input['settings'] ) );
		}
		wpFluent()->table( 'fbs_board_terms' )->where( 'id', $cf_id )->update( $update );
		return array( 'success' => true, 'custom_field_id' => $cf_id );
	},
) );

// =========================================================================
// §4.11.4 — update-custom-field-position
// =========================================================================
$reg->write( 'fluent-boards/update-custom-field-position', array(
	'label'       => 'Update Custom Field Position (Pro)',
	'description' => 'Set a custom field\'s position (decimal). Fractional positions preserved for granular reordering without renumbering.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'custom_field_id', 'position' ),
		'properties' => array(
			'board_id'        => array( 'type' => 'integer' ),
			'custom_field_id' => array( 'type' => 'integer' ),
			'position'        => array( 'type' => 'number' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'custom_field_id' => array( 'type' => 'integer' ) ) ),
	'callback'     => function( $input ) {
		$cf_id = (int) $input['custom_field_id'];
		wpFluent()->table( 'fbs_board_terms' )->where( 'id', $cf_id )->where( 'type', 'custom-field' )->update( array(
			'position'   => $input['position'],
			'updated_at' => current_time( 'mysql' ),
		) );
		return array( 'success' => true, 'custom_field_id' => $cf_id );
	},
) );

// =========================================================================
// §4.11.5 — delete-custom-field (idempotent:false; cascades values)
// =========================================================================
$reg->delete( 'fluent-boards/delete-custom-field', array(
	'label'       => 'Delete Custom Field (Pro)',
	'description' => 'Delete a custom-field definition and all task values associated with it.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'custom_field_id' ),
		'properties' => array(
			'board_id'        => array( 'type' => 'integer' ),
			'custom_field_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'custom_field_id' => array( 'type' => 'integer' ),
		'values_deleted'  => array( 'type' => 'integer' ),
	) ),
	'annotations' => array( 'idempotent' => false ),
	'callback'    => function( $input ) {
		$cf_id    = (int) $input['custom_field_id'];
		$board_id = (int) $input['board_id'];
		$row      = wpFluent()->table( 'fbs_board_terms' )->where( 'id', $cf_id )->where( 'board_id', $board_id )->where( 'type', 'custom-field' )->first();
		if ( ! $row ) {
			return fluent_abilities_error( 'not_found', 'Custom field not found on this board.' );
		}
		$deleted = (int) wpFluent()->table( 'fbs_relations' )->where( 'object_type', 'TASK_CUSTOM_FIELD' )->where( 'foreign_id', $cf_id )->delete();
		wpFluent()->table( 'fbs_board_terms' )->where( 'id', $cf_id )->delete();
		return array( 'success' => true, 'custom_field_id' => $cf_id, 'values_deleted' => $deleted );
	},
) );

// =========================================================================
// §4.11.6 — get-task-custom-field-values
// =========================================================================
$reg->read( 'fluent-boards/get-task-custom-field-values', array(
	'label'       => 'Get Task Custom Field Values (Pro)',
	'description' => 'Get all custom-field values for a task. Values stored as fbs_relations.settings (serialized).',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'task_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
			'task_id'  => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_collection_output( 'values', array(
		'custom_field_id' => array( 'type' => 'integer' ),
		'value'           => array( 'type' => array( 'string', 'integer', 'number', 'boolean', 'array', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$task_id = (int) $input['task_id'];
		$rows    = wpFluent()->table( 'fbs_relations' )->where( 'object_type', 'TASK_CUSTOM_FIELD' )->where( 'object_id', $task_id )->get();
		$items   = array();
		foreach ( $rows as $r ) {
			$settings = maybe_unserialize( $r->settings ?? '' );
			$items[]  = array(
				'custom_field_id' => (int) $r->foreign_id,
				'value'           => is_array( $settings ) ? ( $settings['value'] ?? null ) : null,
			);
		}
		return array( 'values' => $items, 'total' => count( $items ) );
	},
) );

// =========================================================================
// §4.11.7 — save-task-custom-field-values (partial-merge)
// =========================================================================
$reg->write( 'fluent-boards/save-task-custom-field-values', array(
	'label'       => 'Save Task Custom Field Values (Pro)',
	'description' => 'Save (partial-merge) custom-field values for a task. Pass an array of {custom_field_id, value} pairs. Existing values not in the payload are preserved; values in the payload upsert.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'task_id', 'values' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
			'task_id'  => array( 'type' => 'integer' ),
			'values'   => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'required'   => array( 'custom_field_id', 'value' ),
					'properties' => array(
						'custom_field_id' => array( 'type' => 'integer' ),
						'value'           => array( 'description' => 'Per-type value (string|int|number|boolean|array).' ),
					),
				),
			),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'task_id'    => array( 'type' => 'integer' ),
		'upserted'   => array( 'type' => 'integer' ),
	) ),
	'callback' => function( $input ) {
		$task_id  = (int) $input['task_id'];
		$values   = (array) ( $input['values'] ?? array() );
		if ( empty( $values ) ) {
			return fluent_abilities_error( 'ability_invalid_input', 'values must be a non-empty array.' );
		}
		$now      = current_time( 'mysql' );
		$upserted = 0;
		foreach ( $values as $v ) {
			$cf_id   = (int) ( $v['custom_field_id'] ?? 0 );
			if ( ! $cf_id ) { continue; }
			$payload = maybe_serialize( array( 'value' => $v['value'] ?? null ) );
			$existing = wpFluent()->table( 'fbs_relations' )->where( 'object_type', 'TASK_CUSTOM_FIELD' )->where( 'object_id', $task_id )->where( 'foreign_id', $cf_id )->first();
			if ( $existing ) {
				wpFluent()->table( 'fbs_relations' )->where( 'id', $existing->id )->update( array(
					'settings'   => $payload,
					'updated_at' => $now,
				) );
			} else {
				wpFluent()->table( 'fbs_relations' )->insert( array(
					'object_id'   => $task_id,
					'object_type' => 'TASK_CUSTOM_FIELD',
					'foreign_id'  => $cf_id,
					'settings'    => $payload,
					'created_at'  => $now,
					'updated_at'  => $now,
				) );
			}
			$upserted++;
		}
		return array( 'success' => true, 'task_id' => $task_id, 'upserted' => $upserted );
	},
) );
