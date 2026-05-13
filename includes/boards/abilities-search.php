<?php
/**
 * Fluent Boards — Search & Options (Research §4.22)
 *
 * 4 abilities. Tier: free.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

// =========================================================================
// §4.22.1 — search-boards-and-tasks
// =========================================================================
$reg->read( 'fluent-boards/search-boards-and-tasks', array(
	'label'       => 'Search Boards And Tasks',
	'description' => 'Full-text search across board titles and task titles/descriptions with optional filters.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'query' ),
		'properties' => array(
			'query'   => array( 'type' => 'string', 'minLength' => 1 ),
			'filters' => array(
				'type'       => 'object',
				'properties' => array(
					'board_id'    => array( 'type' => 'integer' ),
					'stage_id'    => array( 'type' => 'integer' ),
					'assigned_to' => array( 'type' => 'integer' ),
					'status'      => array( 'type' => 'string' ),
					'priority'    => array( 'type' => 'string' ),
				),
			),
		),
	),
	'output_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'boards' => array( 'type' => 'array' ),
			'tasks'  => array( 'type' => 'array' ),
			'total'  => array( 'type' => 'integer' ),
		),
	),
	'callback' => function( $input ) {
		$q       = sanitize_text_field( $input['query'] ?? '' );
		$filters = (array) ( $input['filters'] ?? array() );
		if ( '' === $q ) {
			return fluent_abilities_error( 'ability_invalid_input', 'query must be non-empty.' );
		}
		$like = '%' . $q . '%';
		// Boards.
		$boards = wpFluent()->table( 'fbs_boards' )->where( 'title', 'LIKE', $like )->limit( 25 )->get();
		// Tasks.
		$tquery = wpFluent()->table( 'fbs_tasks' )
			->where( function( $w ) use ( $like ) {
				$w->where( 'title', 'LIKE', $like )->orWhere( 'description', 'LIKE', $like );
			} )
			->whereNull( 'parent_id' )
			->whereNull( 'archived_at' );
		if ( ! empty( $filters['board_id'] ) ) { $tquery->where( 'board_id', (int) $filters['board_id'] ); }
		if ( ! empty( $filters['stage_id'] ) ) { $tquery->where( 'stage_id', (int) $filters['stage_id'] ); }
		if ( ! empty( $filters['status'] ) )   { $tquery->where( 'status', sanitize_text_field( $filters['status'] ) ); }
		if ( ! empty( $filters['priority'] ) ) { $tquery->where( 'priority', sanitize_text_field( $filters['priority'] ) ); }
		$tasks = $tquery->limit( 50 )->get();
		if ( ! empty( $filters['assigned_to'] ) ) {
			$uid    = (int) $filters['assigned_to'];
			$tids   = array_map( function( $t ) { return (int) $t->id; }, $tasks );
			if ( $tids ) {
				$ok   = wpFluent()->table( 'fbs_relations' )->where( 'object_type', 'task_assignee' )->whereIn( 'object_id', $tids )->where( 'foreign_id', $uid )->get();
				$keep = array();
				foreach ( $ok as $r ) { $keep[ (int) $r->object_id ] = true; }
				$tasks = array_values( array_filter( $tasks, function( $t ) use ( $keep ) { return isset( $keep[ (int) $t->id ] ); } ) );
			}
		}
		$boards_out = array();
		foreach ( $boards as $b ) {
			$boards_out[] = array( 'id' => (int) $b->id, 'title' => $b->title ?? '', 'type' => $b->type ?? null );
		}
		$tasks_out = array();
		foreach ( $tasks as $t ) {
			$tasks_out[] = array( 'id' => (int) $t->id, 'board_id' => (int) $t->board_id, 'title' => $t->title ?? '' );
		}
		return array( 'boards' => $boards_out, 'tasks' => $tasks_out, 'total' => count( $boards_out ) + count( $tasks_out ) );
	},
) );

// =========================================================================
// §4.22.2 — get-search-filters
// =========================================================================
$reg->read( 'fluent-boards/get-search-filters', array(
	'label'         => 'Get Search Filters',
	'description'   => 'Return the available filter set (statuses, priorities, custom-field values) for the search UI.',
	'category'      => 'fluent-boards',
	'output_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'statuses'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'priorities' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
		),
	),
	'callback' => function() {
		return array(
			'statuses'   => array( 'open', 'closed', 'won', 'lost' ),
			'priorities' => array( 'high', 'medium', 'low' ),
		);
	},
) );

// =========================================================================
// §4.22.3 — get-search-suggestions
// =========================================================================
$reg->read( 'fluent-boards/get-search-suggestions', array(
	'label'       => 'Get Search Suggestions',
	'description' => 'Typeahead suggestions for the user-facing search input: matching task titles, board titles, and user names.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'query' ),
		'properties' => array(
			'query' => array( 'type' => 'string', 'minLength' => 1 ),
		),
	),
	'output_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'suggestions' => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'kind'  => array( 'type' => 'string', 'enum' => array( 'board', 'task', 'user' ) ),
						'id'    => array( 'type' => 'integer' ),
						'label' => array( 'type' => 'string' ),
					),
				),
			),
		),
	),
	'callback' => function( $input ) {
		$q = sanitize_text_field( $input['query'] ?? '' );
		if ( '' === $q ) {
			return fluent_abilities_error( 'ability_invalid_input', 'query must be non-empty.' );
		}
		$like = '%' . $q . '%';
		$out  = array();
		foreach ( wpFluent()->table( 'fbs_boards' )->where( 'title', 'LIKE', $like )->limit( 8 )->get() as $b ) {
			$out[] = array( 'kind' => 'board', 'id' => (int) $b->id, 'label' => $b->title ?? '' );
		}
		foreach ( wpFluent()->table( 'fbs_tasks' )->where( 'title', 'LIKE', $like )->whereNull( 'parent_id' )->limit( 8 )->get() as $t ) {
			$out[] = array( 'kind' => 'task', 'id' => (int) $t->id, 'label' => $t->title ?? '' );
		}
		$users = get_users( array( 'search' => '*' . $q . '*', 'number' => 5, 'fields' => array( 'ID', 'display_name' ) ) );
		foreach ( (array) $users as $u ) {
			$out[] = array( 'kind' => 'user', 'id' => (int) $u->ID, 'label' => (string) $u->display_name );
		}
		return array( 'suggestions' => $out );
	},
) );

// =========================================================================
// §4.22.4 — get-global-options
// =========================================================================
$reg->read( 'fluent-boards/get-global-options', array(
	'label'         => 'Get Global Options',
	'description'   => 'Return global option sets used by the UI: statuses, priorities, and aggregated custom-field select options across boards.',
	'category'      => 'fluent-boards',
	'output_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'statuses'           => array( 'type' => 'array' ),
			'priorities'         => array( 'type' => 'array' ),
			'custom_field_types' => array( 'type' => 'array' ),
		),
	),
	'callback' => function() {
		return array(
			'statuses'           => array( 'open', 'closed', 'won', 'lost' ),
			'priorities'         => array( 'high', 'medium', 'low' ),
			'custom_field_types' => array( 'text', 'number', 'date', 'select', 'multi-select', 'checkbox' ),
		);
	},
) );
