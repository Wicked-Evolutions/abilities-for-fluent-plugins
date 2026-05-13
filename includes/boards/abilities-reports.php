<?php
/**
 * Fluent Boards — Reports (Research §4.21)
 *
 * 3 abilities (2 free + 1 pro).
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

// =========================================================================
// §4.21.1 — list-board-tasks-summary (free)
// =========================================================================
$reg->read( 'fluent-boards/list-board-tasks-summary', array(
	'label'       => 'List Board Tasks Summary',
	'description' => 'Aggregated task counts on a board by stage, status, and priority.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'board_id'    => array( 'type' => 'integer' ),
			'total'       => array( 'type' => 'integer' ),
			'by_stage'    => array( 'type' => 'array' ),
			'by_status'   => array( 'type' => 'array' ),
			'by_priority' => array( 'type' => 'array' ),
		),
	),
	'callback' => function( $input ) {
		$board_id = (int) $input['board_id'];
		$rows     = wpFluent()->table( 'fbs_tasks' )->where( 'board_id', $board_id )->whereNull( 'parent_id' )->whereNull( 'archived_at' )->get();
		$by_stage = $by_status = $by_priority = array();
		foreach ( $rows as $t ) {
			$sid                    = (int) ( $t->stage_id ?? 0 );
			$st                     = $t->status ?? 'unset';
			$pr                     = $t->priority ?? 'unset';
			$by_stage[ $sid ]       = ( $by_stage[ $sid ] ?? 0 ) + 1;
			$by_status[ $st ]       = ( $by_status[ $st ] ?? 0 ) + 1;
			$by_priority[ $pr ]     = ( $by_priority[ $pr ] ?? 0 ) + 1;
		}
		$shape = function( $bucket ) {
			$out = array();
			foreach ( $bucket as $k => $c ) {
				$out[] = array( 'key' => $k, 'count' => (int) $c );
			}
			return $out;
		};
		return array(
			'board_id'    => $board_id,
			'total'       => count( $rows ),
			'by_stage'    => $shape( $by_stage ),
			'by_status'   => $shape( $by_status ),
			'by_priority' => $shape( $by_priority ),
		);
	},
) );

// =========================================================================
// §4.21.2 — get-stage-report (free)
// =========================================================================
$reg->read( 'fluent-boards/get-stage-report', array(
	'label'       => 'Get Stage Report',
	'description' => 'Stage-level metrics: task count, closed-tasks percentage, burndown breakdown.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'stage_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
			'stage_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'stage_id'      => array( 'type' => 'integer' ),
			'total'         => array( 'type' => 'integer' ),
			'closed'        => array( 'type' => 'integer' ),
			'completion_pct'=> array( 'type' => 'number' ),
		),
	),
	'callback' => function( $input ) {
		$board_id = (int) $input['board_id'];
		$stage_id = (int) $input['stage_id'];
		$rows     = wpFluent()->table( 'fbs_tasks' )->where( 'board_id', $board_id )->where( 'stage_id', $stage_id )->whereNull( 'parent_id' )->whereNull( 'archived_at' )->get();
		$total    = count( $rows );
		$closed   = 0;
		foreach ( $rows as $t ) {
			if ( in_array( $t->status ?? '', array( 'closed', 'won', 'lost' ), true ) ) {
				$closed++;
			}
		}
		return array(
			'stage_id'       => $stage_id,
			'total'          => $total,
			'closed'         => $closed,
			'completion_pct' => $total ? round( ( $closed / $total ) * 100, 2 ) : 0,
		);
	},
) );

// =========================================================================
// §4.21.3 — get-custom-report (pro)
// =========================================================================
$reg->read( 'fluent-boards/get-custom-report', array(
	'label'       => 'Get Custom Report (Pro)',
	'description' => 'Configurable cross-task report. report_config accepts metric filters (status, priority, assignee_user_ids), date_range (start/end), and grouping (stage_id|status|priority|assignee).',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'report_config' ),
		'properties' => array(
			'board_id'      => array( 'type' => 'integer' ),
			'report_config' => array(
				'type'       => 'object',
				'properties' => array(
					'filters'    => array(
						'type'       => 'object',
						'properties' => array(
							'status'              => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
							'priority'            => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
							'assignee_user_ids'   => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
						),
					),
					'date_range' => array(
						'type'       => 'object',
						'properties' => array(
							'start' => array( 'type' => 'string' ),
							'end'   => array( 'type' => 'string' ),
						),
					),
					'group_by'   => array( 'type' => 'string', 'enum' => array( 'stage_id', 'status', 'priority', 'assignee' ) ),
				),
			),
		),
	),
	'output_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
			'group_by' => array( 'type' => array( 'string', 'null' ) ),
			'buckets'  => array( 'type' => 'array' ),
			'total'    => array( 'type' => 'integer' ),
		),
	),
	'callback' => function( $input ) {
		$board_id = (int) $input['board_id'];
		$config   = (array) ( $input['report_config'] ?? array() );
		$filters  = (array) ( $config['filters'] ?? array() );
		$range    = (array) ( $config['date_range'] ?? array() );
		$group_by = sanitize_key( $config['group_by'] ?? 'status' );
		$query    = wpFluent()->table( 'fbs_tasks' )->where( 'board_id', $board_id )->whereNull( 'parent_id' )->whereNull( 'archived_at' );
		if ( ! empty( $filters['status'] ) ) {
			$query->whereIn( 'status', array_map( 'sanitize_text_field', (array) $filters['status'] ) );
		}
		if ( ! empty( $filters['priority'] ) ) {
			$query->whereIn( 'priority', array_map( 'sanitize_text_field', (array) $filters['priority'] ) );
		}
		if ( ! empty( $range['start'] ) ) {
			$query->where( 'created_at', '>=', sanitize_text_field( $range['start'] ) );
		}
		if ( ! empty( $range['end'] ) ) {
			$query->where( 'created_at', '<=', sanitize_text_field( $range['end'] ) );
		}
		$rows = $query->get();
		// Optional assignee filter.
		if ( ! empty( $filters['assignee_user_ids'] ) ) {
			$uids       = array_map( 'intval', (array) $filters['assignee_user_ids'] );
			$task_ids   = array_map( function( $t ) { return (int) $t->id; }, $rows );
			$rels       = empty( $task_ids ) ? array() : wpFluent()->table( 'fbs_relations' )->where( 'object_type', 'task_assignee' )->whereIn( 'object_id', $task_ids )->whereIn( 'foreign_id', $uids )->get();
			$ok_ids     = array();
			foreach ( $rels as $r ) { $ok_ids[ (int) $r->object_id ] = true; }
			$rows = array_values( array_filter( $rows, function( $t ) use ( $ok_ids ) { return isset( $ok_ids[ (int) $t->id ] ); } ) );
		}
		// Group.
		$buckets = array();
		foreach ( $rows as $t ) {
			switch ( $group_by ) {
				case 'stage_id': $k = (int) ( $t->stage_id ?? 0 ); break;
				case 'priority': $k = $t->priority ?? 'unset'; break;
				case 'assignee':
					$first   = wpFluent()->table( 'fbs_relations' )->where( 'object_type', 'task_assignee' )->where( 'object_id', $t->id )->first();
					$k       = $first ? (int) $first->foreign_id : 0;
					break;
				case 'status':
				default:
					$k = $t->status ?? 'unset';
			}
			$buckets[ (string) $k ] = ( $buckets[ (string) $k ] ?? 0 ) + 1;
		}
		$out = array();
		foreach ( $buckets as $k => $c ) {
			$out[] = array( 'group' => $k, 'count' => (int) $c );
		}
		return array( 'board_id' => $board_id, 'group_by' => $group_by, 'buckets' => $out, 'total' => count( $rows ) );
	},
) );
