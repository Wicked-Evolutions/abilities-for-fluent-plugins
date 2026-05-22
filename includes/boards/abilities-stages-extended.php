<?php
/**
 * Fluent Boards — Stages Extended (Research §4.19 + §4.31)
 *
 * §4.19 Stage actions                       — 7 abilities (6 free, 1 pro)
 * §4.31 Stage drag + reposition + property  — 3 abilities (free)
 * Total: 10 abilities.
 *
 * §6.6 — fbs_board_terms.position is decimal(10,2). Preserve fractional
 * positions so callers can insert without renumbering.
 *
 * Note: existing v1.1.3 abilities.php registers create-stage/update-stage/
 * delete-stage at slugs `fluent-boards/create-stage` etc. These v2.0.0 slugs
 * are differentiated: `*-stage-v2-default-assignees` and friends, and the
 * §4.19.1-§4.19.3 entries are listed in the research but the v1.1.3 slugs
 * cover the same surface. To keep Stable Contracts intact we skip re-adding
 * create-stage/update-stage/delete-stage here (they're already v1.1.3).
 *
 * §4.19.1, §4.19.2, §4.19.3 in research are the v1.1.3 frozen abilities
 * (`fluent-boards/create-stage` :1084, `update-stage` :1151, `delete-stage`
 * :1231 in includes/boards/abilities.php). DO NOT RE-REGISTER — Stable
 * Contracts forbids slug collision. PR body Deviations records this.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

// =========================================================================
// §4.19.4 — list-stage-default-assignees
// =========================================================================
$reg->read( 'fluent-boards/list-stage-default-assignees', array(
	'label'       => 'List Stage Default Assignees',
	'description' => 'List the users automatically assigned to new tasks created in this stage. Stored in fbs_board_terms.settings.default_assignees as user ids.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'stage_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
			'stage_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_collection_output( 'assignees', array(
		'user_id'      => array( 'type' => 'integer' ),
		'display_name' => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$stage_id = (int) $input['stage_id'];
		$board_id = (int) $input['board_id'];
		$stage    = wpFluent()->table( 'fbs_board_terms' )->where( 'id', $stage_id )->where( 'board_id', $board_id )->where( 'type', 'stage' )->first();
		if ( ! $stage ) {
			return fluent_abilities_error( 'not_found', 'Stage not found on this board.' );
		}
		$settings  = maybe_unserialize( $stage->settings ?? '' );
		$ids       = is_array( $settings ) && is_array( $settings['default_assignees'] ?? null ) ? array_map( 'intval', $settings['default_assignees'] ) : array();
		$items     = array();
		foreach ( $ids as $uid ) {
			$u       = get_userdata( $uid );
			$items[] = array( 'user_id' => $uid, 'display_name' => $u ? $u->display_name : null );
		}
		return array( 'assignees' => $items, 'total' => count( $items ) );
	},
) );

// =========================================================================
// §4.19.5 — update-stage-default-assignees
// =========================================================================
$reg->write( 'fluent-boards/update-stage-default-assignees', array(
	'label'       => 'Update Stage Default Assignees',
	'description' => 'Replace the set of users automatically assigned to new tasks in this stage.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'stage_id', 'user_ids' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
			'stage_id' => array( 'type' => 'integer' ),
			'user_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'stage_id' => array( 'type' => 'integer' ),
		'count'    => array( 'type' => 'integer' ),
	) ),
	'callback' => function( $input ) {
		$stage_id = (int) $input['stage_id'];
		$board_id = (int) $input['board_id'];
		$ids      = array_values( array_unique( array_filter( array_map( 'intval', (array) ( $input['user_ids'] ?? array() ) ) ) ) );
		$stage    = wpFluent()->table( 'fbs_board_terms' )->where( 'id', $stage_id )->where( 'board_id', $board_id )->where( 'type', 'stage' )->first();
		if ( ! $stage ) {
			return fluent_abilities_error( 'not_found', 'Stage not found on this board.' );
		}
		$settings                       = maybe_unserialize( $stage->settings ?? '' );
		$settings                       = is_array( $settings ) ? $settings : array();
		$settings['default_assignees']  = $ids;
		wpFluent()->table( 'fbs_board_terms' )->where( 'id', $stage_id )->update( array(
			'settings'   => maybe_serialize( $settings ),
			'updated_at' => current_time( 'mysql' ),
		) );
		return array( 'success' => true, 'stage_id' => $stage_id, 'count' => count( $ids ) );
	},
) );

// =========================================================================
// §4.19.6 — unset-stage-default-assignees
// =========================================================================
$reg->write( 'fluent-boards/unset-stage-default-assignees', array(
	'label'       => 'Unset Stage Default Assignees',
	'description' => 'Clear all default assignees on a stage.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'stage_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
			'stage_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'stage_id' => array( 'type' => 'integer' ) ) ),
	'callback'     => function( $input ) {
		$stage_id = (int) $input['stage_id'];
		$board_id = (int) $input['board_id'];
		$stage    = wpFluent()->table( 'fbs_board_terms' )->where( 'id', $stage_id )->where( 'board_id', $board_id )->where( 'type', 'stage' )->first();
		if ( ! $stage ) {
			return fluent_abilities_error( 'not_found', 'Stage not found on this board.' );
		}
		$settings = maybe_unserialize( $stage->settings ?? '' );
		$settings = is_array( $settings ) ? $settings : array();
		unset( $settings['default_assignees'] );
		wpFluent()->table( 'fbs_board_terms' )->where( 'id', $stage_id )->update( array(
			'settings'   => maybe_serialize( $settings ),
			'updated_at' => current_time( 'mysql' ),
		) );
		return array( 'success' => true, 'stage_id' => $stage_id );
	},
) );

// =========================================================================
// §4.19.7 — stage-archive-all-tasks (pro)
// =========================================================================
$reg->write( 'fluent-boards/stage-archive-all-tasks', array(
	'label'       => 'Archive All Tasks In Stage (Pro)',
	'description' => 'Archive every active task in a single stage (sets archived_at on each).',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'stage_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
			'stage_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'stage_id' => array( 'type' => 'integer' ),
		'archived' => array( 'type' => 'integer' ),
	) ),
	'callback' => function( $input ) {
		$board_id = (int) $input['board_id'];
		$stage_id = (int) $input['stage_id'];
		$affected = (int) wpFluent()->table( 'fbs_tasks' )
			->where( 'board_id', $board_id )
			->where( 'stage_id', $stage_id )
			->whereNull( 'archived_at' )
			->update( array( 'archived_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ) );
		return array( 'success' => true, 'stage_id' => $stage_id, 'archived' => $affected );
	},
) );

// =========================================================================
// §4.31.1 — reposition-stages (bulk, decimal positions preserved)
// =========================================================================
$reg->write( 'fluent-boards/reposition-stages', array(
	'label'       => 'Reposition Stages (Bulk)',
	'description' => 'Set positions for many stages at once using granular decimal positions. Preserves fractional gradients for inserts-without-renumbering (§6.6).',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'positions' ),
		'properties' => array(
			'board_id'  => array( 'type' => 'integer' ),
			'positions' => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'required'   => array( 'stage_id', 'position' ),
					'properties' => array(
						'stage_id' => array( 'type' => 'integer' ),
						'position' => array( 'type' => 'number' ),
					),
				),
			),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'board_id'    => array( 'type' => 'integer' ),
		'repositioned'=> array( 'type' => 'integer' ),
	) ),
	'callback' => function( $input ) {
		$board_id  = (int) $input['board_id'];
		$positions = (array) ( $input['positions'] ?? array() );
		$count     = 0;
		$now       = current_time( 'mysql' );
		foreach ( $positions as $p ) {
			$sid = (int) ( $p['stage_id'] ?? 0 );
			if ( ! $sid ) { continue; }
			$updated = wpFluent()->table( 'fbs_board_terms' )
				->where( 'id', $sid )
				->where( 'board_id', $board_id )
				->where( 'type', 'stage' )
				->update( array( 'position' => $p['position'] ?? 0, 'updated_at' => $now ) );
			if ( $updated ) {
				$count++;
			}
		}
		return array( 'success' => true, 'board_id' => $board_id, 'repositioned' => $count );
	},
) );

// =========================================================================
// §4.31.2 — drag-stage (single-stage decimal position update)
// =========================================================================
$reg->write( 'fluent-boards/drag-stage', array(
	'label'       => 'Drag Stage',
	'description' => 'Persist a single stage\'s new position after a drag interaction. Decimal positions accepted.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'stage_id', 'new_position' ),
		'properties' => array(
			'board_id'     => array( 'type' => 'integer' ),
			'stage_id'     => array( 'type' => 'integer' ),
			'new_position' => array( 'type' => 'number' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'stage_id' => array( 'type' => 'integer' ) ) ),
	'callback'     => function( $input ) {
		$board_id = (int) $input['board_id'];
		$stage_id = (int) $input['stage_id'];
		$updated  = wpFluent()->table( 'fbs_board_terms' )
			->where( 'id', $stage_id )
			->where( 'board_id', $board_id )
			->where( 'type', 'stage' )
			->update( array( 'position' => $input['new_position'], 'updated_at' => current_time( 'mysql' ) ) );
		if ( ! $updated ) {
			return fluent_abilities_error( 'not_found', 'Stage not found on this board.' );
		}
		return array( 'success' => true, 'stage_id' => $stage_id );
	},
) );

// =========================================================================
// §4.31.3 — update-stage-property (single-property patch)
// =========================================================================
$reg->write( 'fluent-boards/update-stage-property', array(
	'label'       => 'Update Stage Property',
	'description' => 'Patch a single column or settings sub-key on a stage. Lighter than update-stage. Allowed columns: title, description, color, settings (full object). Other property names patch under fbs_board_terms.settings.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'stage_id', 'property', 'value' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
			'stage_id' => array( 'type' => 'integer' ),
			'property' => array( 'type' => 'string' ),
			'value'    => array( 'description' => 'Value to set (type depends on property).' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'stage_id' => array( 'type' => 'integer' ),
		'property' => array( 'type' => 'string' ),
	) ),
	'callback' => function( $input ) {
		$board_id = (int) $input['board_id'];
		$stage_id = (int) $input['stage_id'];
		$prop     = sanitize_key( $input['property'] ?? '' );
		$value    = $input['value'] ?? null;
		$stage    = wpFluent()->table( 'fbs_board_terms' )->where( 'id', $stage_id )->where( 'board_id', $board_id )->where( 'type', 'stage' )->first();
		if ( ! $stage ) {
			return fluent_abilities_error( 'not_found', 'Stage not found on this board.' );
		}
		$now    = current_time( 'mysql' );
		$direct = array( 'title', 'description', 'color', 'position' );
		if ( in_array( $prop, $direct, true ) ) {
			wpFluent()->table( 'fbs_board_terms' )->where( 'id', $stage_id )->update( array(
				$prop        => is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '',
				'updated_at' => $now,
			) );
		} else {
			$settings              = maybe_unserialize( $stage->settings ?? '' );
			$settings              = is_array( $settings ) ? $settings : array();
			$settings[ $prop ]     = $value;
			wpFluent()->table( 'fbs_board_terms' )->where( 'id', $stage_id )->update( array(
				'settings'   => maybe_serialize( $settings ),
				'updated_at' => $now,
			) );
		}
		return array( 'success' => true, 'stage_id' => $stage_id, 'property' => $prop );
	},
) );
