<?php
/**
 * Fluent Boards — Templates (Research §4.20)
 *
 * 4 abilities. Tier: pro.
 *
 * Templates stored in fbs_boards rows with type='template' (Pro discriminator).
 * Template structure (stages, tasks, labels, custom fields) is captured at
 * duplicate-board-as-template time and replayed at create-board-from-template.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

// =========================================================================
// §4.20.1 — list-templates
// =========================================================================
// =========================================================================
// §4.20.2 — get-template-detail
// =========================================================================
$reg->read( 'fluent-boards/get-template-detail', array(
	'label'       => 'Get Template Detail (Pro)',
	'description' => 'Get the full structure of a template (stages, tasks, labels, custom fields). Note: within `stages`, the vendor returns `id` and `position` as strings (e.g. "220", "1.00"), unlike sibling board abilities which return them as integers (vendor type drift).',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'template_id' ),
		'properties' => array(
			'template_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'template_id' => array( 'type' => 'integer' ),
			'title'       => array( 'type' => array( 'string', 'null' ) ),
			'stages'      => array( 'type' => 'array' ),
			'tasks'       => array( 'type' => 'array' ),
			'labels'      => array( 'type' => 'array' ),
			'custom_fields'=> array( 'type' => 'array' ),
		),
	),
	'callback' => function( $input ) {
		$template_id = (int) $input['template_id'];
		$template    = wpFluent()->table( 'fbs_boards' )->where( 'id', $template_id )->where( 'type', 'template' )->first();
		if ( ! $template ) {
			return fluent_abilities_error( 'not_found', 'Template not found.' );
		}
		$stages_raw   = wpFluent()->table( 'fbs_board_terms' )->where( 'board_id', $template_id )->where( 'type', 'stage' )->orderBy( 'position', 'ASC' )->get();
		$labels_raw   = wpFluent()->table( 'fbs_board_terms' )->where( 'board_id', $template_id )->where( 'type', 'label' )->get();
		$cfs_raw      = wpFluent()->table( 'fbs_board_terms' )->where( 'board_id', $template_id )->where( 'type', 'custom-field' )->get();
		$tasks_raw    = wpFluent()->table( 'fbs_tasks' )->where( 'board_id', $template_id )->whereNull( 'parent_id' )->get();
		$cb           = function( $r, $keys ) {
			$out = array();
			foreach ( $keys as $k ) { $out[ $k ] = $r->$k ?? null; }
			return $out;
		};
		return array(
			'template_id'   => $template_id,
			'title'         => $template->title ?? '',
			'stages'        => array_map( function( $s ) use ( $cb ) { return $cb( $s, array( 'id', 'title', 'position' ) ); }, iterator_to_array( ( function() use ( $stages_raw ) { foreach ( $stages_raw as $r ) { yield $r; } } )() ) ),
			'tasks'         => array_map( function( $t ) use ( $cb ) { return $cb( $t, array( 'id', 'title', 'stage_id', 'priority' ) ); }, iterator_to_array( ( function() use ( $tasks_raw ) { foreach ( $tasks_raw as $r ) { yield $r; } } )() ) ),
			'labels'        => array_map( function( $l ) use ( $cb ) { return $cb( $l, array( 'id', 'title' ) ); }, iterator_to_array( ( function() use ( $labels_raw ) { foreach ( $labels_raw as $r ) { yield $r; } } )() ) ),
			'custom_fields' => array_map( function( $c ) use ( $cb ) { return $cb( $c, array( 'id', 'title' ) ); }, iterator_to_array( ( function() use ( $cfs_raw ) { foreach ( $cfs_raw as $r ) { yield $r; } } )() ) ),
		);
	},
) );

// =========================================================================
// §4.20.3 — create-board-from-template (idempotent:false)
// =========================================================================
// =========================================================================
// §4.20.4 — duplicate-board-as-template (idempotent:false)
// =========================================================================
