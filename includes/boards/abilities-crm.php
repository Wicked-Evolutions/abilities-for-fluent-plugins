<?php
/**
 * Fluent Boards — FluentCRM Contacts Integration (Research §4.23)
 *
 * 5 abilities. Tier: free.
 *
 * Fluent Boards links FluentCRM contacts via fbs_tasks.crm_contact_id and
 * fbs_metas with object_type='crm_board_link'.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

// =========================================================================
// §4.23.1 — get-crm-contact-on-board
// =========================================================================
$reg->read( 'fluent-boards/get-crm-contact-on-board', array(
	'label'       => 'Get CRM Contact On Board',
	'description' => 'Get a board\'s link entry for a specific FluentCRM contact.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'contact_id' ),
		'properties' => array(
			'board_id'   => array( 'type' => 'integer' ),
			'contact_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'board_id'      => array( 'type' => 'integer' ),
			'contact_id'    => array( 'type' => 'integer' ),
			'linked'        => array( 'type' => 'boolean' ),
			'contact_email' => array( 'type' => array( 'string', 'null' ) ),
			'contact_name'  => array( 'type' => array( 'string', 'null' ) ),
		),
	),
	'callback' => function( $input ) {
		$board_id   = (int) $input['board_id'];
		$contact_id = (int) $input['contact_id'];
		$row        = wpFluent()->table( 'fbs_metas' )->where( 'object_type', 'crm_board_link' )->where( 'object_id', $board_id )->where( 'value', (string) $contact_id )->first();
		$contact_email = null;
		$contact_name  = null;
		if ( function_exists( 'FluentCrmApi' ) ) {
			$c = FluentCrmApi( 'contacts' )->getContact( $contact_id );
			if ( $c ) {
				$contact_email = $c->email ?? null;
				$contact_name  = trim( ( $c->first_name ?? '' ) . ' ' . ( $c->last_name ?? '' ) );
			}
		}
		return array(
			'board_id'      => $board_id,
			'contact_id'    => $contact_id,
			'linked'        => (bool) $row,
			'contact_email' => $contact_email,
			'contact_name'  => $contact_name,
		);
	},
) );

// =========================================================================
// §4.23.2 — associate-crm-contact-to-board (idempotent:true)
// =========================================================================
$reg->write( 'fluent-boards/associate-crm-contact-to-board', array(
	'label'       => 'Associate CRM Contact To Board',
	'description' => 'Link a FluentCRM contact to a board. Idempotent.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'contact_id' ),
		'properties' => array(
			'board_id'   => array( 'type' => 'integer' ),
			'contact_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'board_id'   => array( 'type' => 'integer' ),
		'contact_id' => array( 'type' => 'integer' ),
	) ),
	'annotations' => array( 'idempotent' => true ),
	'callback'    => function( $input ) {
		$board_id   = (int) $input['board_id'];
		$contact_id = (int) $input['contact_id'];
		if ( ! wpFluent()->table( 'fbs_boards' )->where( 'id', $board_id )->first() ) {
			return fluent_abilities_error( 'not_found', 'Board not found.' );
		}
		$exists = wpFluent()->table( 'fbs_metas' )->where( 'object_type', 'crm_board_link' )->where( 'object_id', $board_id )->where( 'value', (string) $contact_id )->first();
		if ( ! $exists ) {
			$now = current_time( 'mysql' );
			wpFluent()->table( 'fbs_metas' )->insert( array(
				'object_id'   => $board_id,
				'object_type' => 'crm_board_link',
				'key'         => 'crm_contact_id',
				'value'       => (string) $contact_id,
				'created_at'  => $now,
				'updated_at'  => $now,
			) );
		}
		return array( 'success' => true, 'board_id' => $board_id, 'contact_id' => $contact_id );
	},
) );

// =========================================================================
// §4.23.3 — disassociate-crm-contact-from-board
// =========================================================================
$reg->delete( 'fluent-boards/disassociate-crm-contact-from-board', array(
	'label'       => 'Disassociate CRM Contact From Board',
	'description' => 'Remove the link between a FluentCRM contact and a board.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'contact_id' ),
		'properties' => array(
			'board_id'   => array( 'type' => 'integer' ),
			'contact_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'board_id'   => array( 'type' => 'integer' ),
		'contact_id' => array( 'type' => 'integer' ),
	) ),
	'callback' => function( $input ) {
		$board_id   = (int) $input['board_id'];
		$contact_id = (int) $input['contact_id'];
		wpFluent()->table( 'fbs_metas' )->where( 'object_type', 'crm_board_link' )->where( 'object_id', $board_id )->where( 'value', (string) $contact_id )->delete();
		return array( 'success' => true, 'board_id' => $board_id, 'contact_id' => $contact_id );
	},
) );

// =========================================================================
// §4.23.4 — list-crm-associated-boards
// =========================================================================
$reg->read( 'fluent-boards/list-crm-associated-boards', array(
	'label'       => 'List CRM Associated Boards',
	'description' => 'List boards linked to a given FluentCRM contact.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'contact_id' ),
		'properties' => array(
			'contact_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_collection_output( 'boards', array(
		'id'    => array( 'type' => 'integer' ),
		'title' => array( 'type' => array( 'string', 'null' ) ),
		'type'  => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$contact_id = (int) $input['contact_id'];
		$rows       = wpFluent()->table( 'fbs_metas' )->where( 'object_type', 'crm_board_link' )->where( 'value', (string) $contact_id )->get();
		// V5: coerce vendor Collection to array before array_map (P-A pattern).
		$ids        = array_map( function( $r ) { return (int) $r->object_id; }, fluent_abilities_to_array( $rows ) );
		if ( empty( $ids ) ) {
			return array( 'boards' => array(), 'total' => 0 );
		}
		$boards = wpFluent()->table( 'fbs_boards' )->whereIn( 'id', $ids )->get();
		$items  = array();
		foreach ( $boards as $b ) {
			$items[] = array( 'id' => (int) $b->id, 'title' => $b->title ?? '', 'type' => $b->type ?? null );
		}
		return array( 'boards' => $items, 'total' => count( $items ) );
	},
) );

// =========================================================================
// §4.23.5 — list-crm-associated-tasks
// =========================================================================
$reg->read( 'fluent-boards/list-crm-associated-tasks', array(
	'label'       => 'List CRM Associated Tasks',
	'description' => 'List tasks where fbs_tasks.crm_contact_id matches the given contact_id.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'contact_id' ),
		'properties' => array_merge( array(
			'contact_id' => array( 'type' => 'integer' ),
		), fluent_abilities_pagination_schema() ),
	),
	'output_schema' => fluent_abilities_schema_list_output( 'tasks', array(
		'id'       => array( 'type' => 'integer' ),
		'board_id' => array( 'type' => 'integer' ),
		'title'    => array( 'type' => array( 'string', 'null' ) ),
		'status'   => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$contact_id = (int) $input['contact_id'];
		$pagination = fluent_abilities_pagination( $input, 25 );
		$query      = wpFluent()->table( 'fbs_tasks' )->where( 'crm_contact_id', $contact_id )->whereNull( 'parent_id' );
		$total      = (int) $query->count();
		$rows       = $query->orderBy( 'id', 'DESC' )->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();
		$items      = array();
		foreach ( $rows as $t ) {
			$items[] = array(
				'id'       => (int) $t->id,
				'board_id' => (int) $t->board_id,
				'title'    => $t->title ?? '',
				'status'   => $t->status ?? null,
			);
		}
		return array( 'tasks' => $items, 'total' => $total, 'page' => $pagination['page'], 'per_page' => $pagination['per_page'] );
	},
) );
