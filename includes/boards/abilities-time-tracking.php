<?php
/**
 * Fluent Boards — Time Tracking + Reports (Research §4.12 + §4.13)
 *
 * §4.12 Time tracking session state machine — 9 abilities (pro)
 * §4.13 Time-track reports                  — 2 abilities (pro)
 * Total: 11 abilities.
 *
 * fbs_time_tracks rows have status in {active, paused, committed}. At most one
 * active row per (user_id, task_id) is enforced in callbacks.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

// =========================================================================
// §4.12.1 — list-time-tracks
// =========================================================================
$reg->read( 'fluent-boards/list-time-tracks', array(
	'label'       => 'List Time Tracks (Pro)',
	'description' => 'List time-track rows for a task with pagination.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'task_id' ),
		'properties' => array_merge( array(
			'board_id' => array( 'type' => 'integer' ),
			'task_id'  => array( 'type' => 'integer' ),
		), fluent_abilities_pagination_schema() ),
	),
	'output_schema' => fluent_abilities_schema_list_output( 'tracks', array(
		'id'         => array( 'type' => 'integer' ),
		'user_id'    => array( 'type' => array( 'integer', 'null' ) ),
		'status'     => array( 'type' => array( 'string', 'null' ) ),
		'started_at' => array( 'type' => array( 'string', 'null' ) ),
		'ended_at'   => array( 'type' => array( 'string', 'null' ) ),
		'duration'   => array( 'type' => array( 'integer', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$task_id    = (int) $input['task_id'];
		$pagination = fluent_abilities_pagination( $input, 25 );
		$query      = wpFluent()->table( 'fbs_time_tracks' )->where( 'task_id', $task_id )->orderBy( 'id', 'DESC' );
		$total      = (int) $query->count();
		$rows       = $query->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();
		$items      = array();
		foreach ( $rows as $t ) {
			$items[] = array(
				'id'         => (int) $t->id,
				'user_id'    => $t->user_id ? (int) $t->user_id : null,
				'status'     => $t->status ?? null,
				'started_at' => $t->started_at ?? null,
				'ended_at'   => $t->ended_at ?? null,
				'duration'   => isset( $t->duration ) ? (int) $t->duration : null,
			);
		}
		return array( 'tracks' => $items, 'total' => $total, 'page' => $pagination['page'], 'per_page' => $pagination['per_page'] );
	},
) );

// =========================================================================
// §4.12.2 — start-time-track (idempotent:false)
// =========================================================================
$reg->write( 'fluent-boards/start-time-track', array(
	'label'       => 'Start Time Track (Pro)',
	'description' => 'Start a new active time-track session for the current user on a task. Fails if an existing active session exists; pause/commit it first.',
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
		'time_track_id' => array( 'type' => 'integer' ),
		'task_id'       => array( 'type' => 'integer' ),
	) ),
	'annotations' => array( 'idempotent' => false ),
	'callback'    => function( $input ) {
		$task_id = (int) $input['task_id'];
		$user_id = (int) get_current_user_id();
		if ( ! $user_id ) {
			return fluent_abilities_error( 'forbidden', 'Authenticated user required.' );
		}
		$active = wpFluent()->table( 'fbs_time_tracks' )->where( 'task_id', $task_id )->where( 'user_id', $user_id )->where( 'status', 'active' )->first();
		if ( $active ) {
			return fluent_abilities_error( 'conflict', 'An active time-track session already exists for this user/task. Pause or commit it first.' );
		}
		$now    = current_time( 'mysql' );
		$new_id = wpFluent()->table( 'fbs_time_tracks' )->insertGetId( array(
			'task_id'    => $task_id,
			'user_id'    => $user_id,
			'status'     => 'active',
			'started_at' => $now,
			'duration'   => 0,
			'created_at' => $now,
			'updated_at' => $now,
		) );
		return array( 'success' => true, 'time_track_id' => (int) $new_id, 'task_id' => $task_id );
	},
) );

// =========================================================================
// §4.12.3 — pause-time-track
// =========================================================================
$reg->write( 'fluent-boards/pause-time-track', array(
	'label'       => 'Pause Time Track (Pro)',
	'description' => 'Pause the current user\'s active time-track session on a task. Accumulates elapsed seconds into duration.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'task_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
			'task_id'  => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'time_track_id' => array( 'type' => 'integer' ) ) ),
	'callback'     => function( $input ) {
		$task_id = (int) $input['task_id'];
		$user_id = (int) get_current_user_id();
		if ( ! $user_id ) {
			return fluent_abilities_error( 'forbidden', 'Authenticated user required.' );
		}
		$active = wpFluent()->table( 'fbs_time_tracks' )->where( 'task_id', $task_id )->where( 'user_id', $user_id )->where( 'status', 'active' )->first();
		if ( ! $active ) {
			return fluent_abilities_error( 'not_found', 'No active session to pause.' );
		}
		$now      = current_time( 'mysql' );
		$elapsed  = max( 0, (int) ( strtotime( $now ) - strtotime( (string) $active->started_at ) ) );
		$duration = (int) ( $active->duration ?? 0 ) + $elapsed;
		wpFluent()->table( 'fbs_time_tracks' )->where( 'id', $active->id )->update( array(
			'status'     => 'paused',
			'duration'   => $duration,
			'ended_at'   => $now,
			'updated_at' => $now,
		) );
		return array( 'success' => true, 'time_track_id' => (int) $active->id );
	},
) );

// =========================================================================
// §4.12.4 — resume-time-track
// =========================================================================
$reg->write( 'fluent-boards/resume-time-track', array(
	'label'       => 'Resume Time Track (Pro)',
	'description' => 'Resume a previously paused time-track session (latest paused if time_track_id omitted).',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'task_id' ),
		'properties' => array(
			'board_id'      => array( 'type' => 'integer' ),
			'task_id'       => array( 'type' => 'integer' ),
			'time_track_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'time_track_id' => array( 'type' => 'integer' ) ) ),
	'callback'     => function( $input ) {
		$task_id = (int) $input['task_id'];
		$user_id = (int) get_current_user_id();
		if ( ! $user_id ) {
			return fluent_abilities_error( 'forbidden', 'Authenticated user required.' );
		}
		$query = wpFluent()->table( 'fbs_time_tracks' )->where( 'task_id', $task_id )->where( 'user_id', $user_id )->where( 'status', 'paused' );
		if ( ! empty( $input['time_track_id'] ) ) {
			$query->where( 'id', (int) $input['time_track_id'] );
		}
		$paused = $query->orderBy( 'id', 'DESC' )->first();
		if ( ! $paused ) {
			return fluent_abilities_error( 'not_found', 'No paused session found.' );
		}
		$now = current_time( 'mysql' );
		wpFluent()->table( 'fbs_time_tracks' )->where( 'id', $paused->id )->update( array(
			'status'     => 'active',
			'started_at' => $now,
			'ended_at'   => null,
			'updated_at' => $now,
		) );
		return array( 'success' => true, 'time_track_id' => (int) $paused->id );
	},
) );

// =========================================================================
// §4.12.5 — commit-time-track
// =========================================================================
$reg->write( 'fluent-boards/commit-time-track', array(
	'label'       => 'Commit Time Track (Pro)',
	'description' => 'Commit a time-track session (terminal state). If currently active, accumulates final elapsed seconds.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'task_id' ),
		'properties' => array(
			'board_id'      => array( 'type' => 'integer' ),
			'task_id'       => array( 'type' => 'integer' ),
			'time_track_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'time_track_id' => array( 'type' => 'integer' ),
		'duration'      => array( 'type' => 'integer' ),
	) ),
	'callback' => function( $input ) {
		$task_id = (int) $input['task_id'];
		$user_id = (int) get_current_user_id();
		if ( ! $user_id ) {
			return fluent_abilities_error( 'forbidden', 'Authenticated user required.' );
		}
		$query = wpFluent()->table( 'fbs_time_tracks' )->where( 'task_id', $task_id )->where( 'user_id', $user_id )->whereIn( 'status', array( 'active', 'paused' ) );
		if ( ! empty( $input['time_track_id'] ) ) {
			$query->where( 'id', (int) $input['time_track_id'] );
		}
		$row = $query->orderBy( 'id', 'DESC' )->first();
		if ( ! $row ) {
			return fluent_abilities_error( 'not_found', 'No active/paused session to commit.' );
		}
		$now      = current_time( 'mysql' );
		$duration = (int) ( $row->duration ?? 0 );
		if ( 'active' === $row->status ) {
			$duration += max( 0, (int) ( strtotime( $now ) - strtotime( (string) $row->started_at ) ) );
		}
		wpFluent()->table( 'fbs_time_tracks' )->where( 'id', $row->id )->update( array(
			'status'     => 'committed',
			'duration'   => $duration,
			'ended_at'   => $now,
			'updated_at' => $now,
		) );
		return array( 'success' => true, 'time_track_id' => (int) $row->id, 'duration' => $duration );
	},
) );

// =========================================================================
// §4.12.6 — get-active-time-track
// =========================================================================
$reg->read( 'fluent-boards/get-active-time-track', array(
	'label'       => 'Get Active Time Track (Pro)',
	'description' => 'Get the current user\'s active time-track session for a task (if any).',
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
			'active' => array( 'type' => array( 'object', 'null' ) ),
		),
	),
	'callback' => function( $input ) {
		$task_id = (int) $input['task_id'];
		$user_id = (int) get_current_user_id();
		if ( ! $user_id ) {
			return array( 'active' => null );
		}
		$row = wpFluent()->table( 'fbs_time_tracks' )->where( 'task_id', $task_id )->where( 'user_id', $user_id )->where( 'status', 'active' )->first();
		if ( ! $row ) {
			return array( 'active' => null );
		}
		return array( 'active' => array(
			'id'         => (int) $row->id,
			'started_at' => $row->started_at ?? null,
			'duration'   => (int) ( $row->duration ?? 0 ),
		) );
	},
) );

// =========================================================================
// §4.12.7 — get-task-duration-stats
// =========================================================================
$reg->read( 'fluent-boards/get-task-duration-stats', array(
	'label'       => 'Get Task Duration Stats (Pro)',
	'description' => 'Get aggregated time-track statistics for a task across all users (totals + active).',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'task_id' ),
		'properties' => array(
			'task_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'task_id'              => array( 'type' => 'integer' ),
			'total_committed'      => array( 'type' => 'integer' ),
			'total_active_running' => array( 'type' => 'integer' ),
			'tracker_count'        => array( 'type' => 'integer' ),
		),
	),
	'callback' => function( $input ) {
		$task_id        = (int) $input['task_id'];
		$rows           = wpFluent()->table( 'fbs_time_tracks' )->where( 'task_id', $task_id )->get();
		$total_committed= 0;
		$total_active   = 0;
		$users          = array();
		foreach ( $rows as $r ) {
			$users[ (int) $r->user_id ] = true;
			$dur = (int) ( $r->duration ?? 0 );
			if ( 'active' === $r->status ) {
				$dur += max( 0, (int) ( strtotime( current_time( 'mysql' ) ) - strtotime( (string) $r->started_at ) ) );
				$total_active += $dur;
			} else {
				$total_committed += $dur;
			}
		}
		return array(
			'task_id'              => $task_id,
			'total_committed'      => $total_committed,
			'total_active_running' => $total_active,
			'tracker_count'        => count( $users ),
		);
	},
) );

// =========================================================================
// §4.12.8 — list-user-time-tracks
// =========================================================================
$reg->read( 'fluent-boards/list-user-time-tracks', array(
	'label'       => 'List User Time Tracks (Pro)',
	'description' => 'List time-track rows authored by a user across all tasks.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'user_id' ),
		'properties' => array_merge( array(
			'user_id' => array( 'type' => 'integer' ),
		), fluent_abilities_pagination_schema() ),
	),
	'output_schema' => fluent_abilities_schema_list_output( 'tracks', array(
		'id'        => array( 'type' => 'integer' ),
		'task_id'   => array( 'type' => array( 'integer', 'null' ) ),
		'status'    => array( 'type' => array( 'string', 'null' ) ),
		'duration'  => array( 'type' => array( 'integer', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$user_id    = (int) $input['user_id'];
		$pagination = fluent_abilities_pagination( $input, 25 );
		$query      = wpFluent()->table( 'fbs_time_tracks' )->where( 'user_id', $user_id )->orderBy( 'id', 'DESC' );
		$total      = (int) $query->count();
		$rows       = $query->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();
		$items      = array();
		foreach ( $rows as $t ) {
			$items[] = array(
				'id'       => (int) $t->id,
				'task_id'  => $t->task_id ? (int) $t->task_id : null,
				'status'   => $t->status ?? null,
				'duration' => isset( $t->duration ) ? (int) $t->duration : null,
			);
		}
		return array( 'tracks' => $items, 'total' => $total, 'page' => $pagination['page'], 'per_page' => $pagination['per_page'] );
	},
) );

// =========================================================================
// §4.12.9 — list-task-duration
// =========================================================================
$reg->read( 'fluent-boards/list-task-duration', array(
	'label'       => 'List Task Duration By User (Pro)',
	'description' => 'List a task\'s total committed duration broken down by user.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'task_id' ),
		'properties' => array(
			'task_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_collection_output( 'durations', array(
		'user_id'         => array( 'type' => 'integer' ),
		'display_name'    => array( 'type' => array( 'string', 'null' ) ),
		'total_duration'  => array( 'type' => 'integer' ),
	) ),
	'callback' => function( $input ) {
		$task_id = (int) $input['task_id'];
		$rows    = wpFluent()->table( 'fbs_time_tracks' )->where( 'task_id', $task_id )->where( 'status', 'committed' )->get();
		$by_user = array();
		foreach ( $rows as $r ) {
			$uid              = (int) ( $r->user_id ?? 0 );
			$by_user[ $uid ]  = ( $by_user[ $uid ] ?? 0 ) + (int) ( $r->duration ?? 0 );
		}
		$items = array();
		foreach ( $by_user as $uid => $total ) {
			$u       = $uid ? get_userdata( $uid ) : null;
			$items[] = array(
				'user_id'        => $uid,
				'display_name'   => $u ? $u->display_name : null,
				'total_duration' => (int) $total,
			);
		}
		return array( 'durations' => $items, 'total' => count( $items ) );
	},
) );

// =========================================================================
// §4.13.1 — get-user-time-report
// =========================================================================
$reg->read( 'fluent-boards/get-user-time-report', array(
	'label'       => 'Get User Time Report (Pro)',
	'description' => 'Aggregated committed-time report for a user, optionally bounded by date_range.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'user_id' ),
		'properties' => array(
			'user_id'    => array( 'type' => 'integer' ),
			'date_range' => array(
				'type'       => 'object',
				'properties' => array(
					'start' => array( 'type' => 'string' ),
					'end'   => array( 'type' => 'string' ),
				),
			),
		),
	),
	'output_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'user_id'        => array( 'type' => 'integer' ),
			'total_duration' => array( 'type' => 'integer' ),
			'task_count'     => array( 'type' => 'integer' ),
		),
	),
	'callback' => function( $input ) {
		$user_id = (int) $input['user_id'];
		$query   = wpFluent()->table( 'fbs_time_tracks' )->where( 'user_id', $user_id )->where( 'status', 'committed' );
		if ( ! empty( $input['date_range']['start'] ) ) {
			$query->where( 'started_at', '>=', sanitize_text_field( $input['date_range']['start'] ) );
		}
		if ( ! empty( $input['date_range']['end'] ) ) {
			$query->where( 'started_at', '<=', sanitize_text_field( $input['date_range']['end'] ) );
		}
		$rows  = $query->get();
		$total = 0;
		$tasks = array();
		foreach ( $rows as $r ) {
			$total           += (int) ( $r->duration ?? 0 );
			$tasks[ (int) $r->task_id ] = true;
		}
		return array( 'user_id' => $user_id, 'total_duration' => $total, 'task_count' => count( $tasks ) );
	},
) );

// =========================================================================
// §4.13.2 — get-task-time-report
// =========================================================================
$reg->read( 'fluent-boards/get-task-time-report', array(
	'label'       => 'Get Task Time Report (Pro)',
	'description' => 'Time-report breakdown for a task by assignee, optionally bounded by date_range.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'task_id' ),
		'properties' => array(
			'task_id'    => array( 'type' => 'integer' ),
			'date_range' => array(
				'type'       => 'object',
				'properties' => array(
					'start' => array( 'type' => 'string' ),
					'end'   => array( 'type' => 'string' ),
				),
			),
		),
	),
	'output_schema' => fluent_abilities_schema_collection_output( 'by_user', array(
		'user_id'        => array( 'type' => 'integer' ),
		'total_duration' => array( 'type' => 'integer' ),
	) ),
	'callback' => function( $input ) {
		$task_id = (int) $input['task_id'];
		$query   = wpFluent()->table( 'fbs_time_tracks' )->where( 'task_id', $task_id )->where( 'status', 'committed' );
		if ( ! empty( $input['date_range']['start'] ) ) {
			$query->where( 'started_at', '>=', sanitize_text_field( $input['date_range']['start'] ) );
		}
		if ( ! empty( $input['date_range']['end'] ) ) {
			$query->where( 'started_at', '<=', sanitize_text_field( $input['date_range']['end'] ) );
		}
		$rows    = $query->get();
		$by_user = array();
		foreach ( $rows as $r ) {
			$uid              = (int) ( $r->user_id ?? 0 );
			$by_user[ $uid ]  = ( $by_user[ $uid ] ?? 0 ) + (int) ( $r->duration ?? 0 );
		}
		$items = array();
		foreach ( $by_user as $uid => $total ) {
			$items[] = array( 'user_id' => $uid, 'total_duration' => (int) $total );
		}
		return array( 'by_user' => $items, 'total' => count( $items ) );
	},
) );
