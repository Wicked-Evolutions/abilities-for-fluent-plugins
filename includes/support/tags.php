<?php
/**
 * Fluent Support — Tag Abilities
 *
 * 7 abilities: list/get/create/update/delete tag + add/remove/get ticket tags.
 * Tags live in fs_taggables. Ticket-tag pivot in fs_tag_pivot (source_type = 'ticket_tag').
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function () {

	$reg = new Fluent_Abilities_Registrar( 'support' );

	// =========================================================================
	// TAGS (entity CRUD)
	// =========================================================================

	$reg->read( 'fluent-support/list-tags', array(
		'label'       => 'List Support Tags',
		'description' => 'List all support ticket tags.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type' => 'object',
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'tags', array(
			'id'          => array( 'type' => 'integer' ),
			'title'       => array( 'type' => 'string' ),
			'slug'        => array( 'type' => 'string' ),
			'description' => array( 'type' => array( 'string', 'null' ) ),
			'created_at'  => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function ( $input ) {
			$tags = wpFluent()->table( 'fs_taggables' )
				->orderBy( 'title', 'ASC' )
				->get();

			$items = array();
			foreach ( $tags as $tag ) {
				$ticket_count = wpFluent()->table( 'fs_tag_pivot' )
					->where( 'tag_id', (int) $tag->id )
					->where( 'source_type', 'ticket_tag' )
					->count();

				$items[] = array(
					'id'           => (int) $tag->id,
					'title'        => $tag->title,
					'slug'         => $tag->slug,
					'description'  => $tag->description,
					'ticket_count' => $ticket_count,
					'created_at'   => $tag->created_at ? (string) $tag->created_at : null,
				);
			}

			return array( 'tags' => $items, 'total' => count( $items ) );
		},
	) );

	$reg->read( 'fluent-support/get-tag', array(
		'label'       => 'Get Support Tag',
		'description' => 'Get a single support tag by ID with ticket count.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Tag ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'           => array( 'type' => 'integer' ),
			'title'        => array( 'type' => 'string' ),
			'slug'         => array( 'type' => 'string' ),
			'description'  => array( 'type' => array( 'string', 'null' ) ),
			'tag_type'     => array( 'type' => array( 'string', 'null' ) ),
			'ticket_count' => array( 'type' => 'integer' ),
			'created_at'   => array( 'type' => array( 'string', 'null' ) ),
			'updated_at'   => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function ( $input ) {
			$tag = wpFluent()->table( 'fs_taggables' )
				->where( 'id', (int) $input['id'] )
				->first();

			if ( ! $tag ) {
				return fluent_abilities_error( 'not_found', 'Tag not found' );
			}

			$ticket_count = wpFluent()->table( 'fs_tag_pivot' )
				->where( 'tag_id', (int) $tag->id )
				->where( 'source_type', 'ticket_tag' )
				->count();

			return array(
				'id'           => (int) $tag->id,
				'title'        => $tag->title,
				'slug'         => $tag->slug,
				'description'  => $tag->description,
				'tag_type'     => $tag->tag_type,
				'ticket_count' => $ticket_count,
				'created_at'   => $tag->created_at ? (string) $tag->created_at : null,
				'updated_at'   => $tag->updated_at ? (string) $tag->updated_at : null,
			);
		},
	) );

	$reg->write( 'fluent-support/create-tag', array(
		'label'       => 'Create Support Tag',
		'description' => 'Create a new support ticket tag.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'title' ),
			'properties' => array(
				'title'       => array( 'type' => 'string', 'description' => 'Tag title (required)' ),
				'description' => array( 'type' => 'string', 'description' => 'Tag description' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function ( $input ) {
			$data = array(
				'title'       => sanitize_text_field( $input['title'] ),
				'description' => sanitize_textarea_field( $input['description'] ?? '' ),
			);

			$result = FluentSupportApi( 'tags' )->createTag( $data );

			if ( ! $result ) {
				return fluent_abilities_error( 'creation_failed', 'Tag creation failed — title may be empty' );
			}

			return array(
				'success' => true,
				'id'      => (int) $result->id,
				'title'   => $result->title,
			);
		},
	) );

	$reg->write( 'fluent-support/update-tag', array(
		'label'       => 'Update Support Tag',
		'description' => 'Update an existing support tag. Only provided fields are changed.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'          => array( 'type' => 'integer', 'description' => 'Tag ID' ),
				'title'       => array( 'type' => 'string', 'description' => 'Tag title' ),
				'description' => array( 'type' => 'string', 'description' => 'Tag description' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function ( $input ) {
			$id = (int) $input['id'];

			$tag = wpFluent()->table( 'fs_taggables' )
				->where( 'id', $id )
				->first();

			if ( ! $tag ) {
				return fluent_abilities_error( 'not_found', 'Tag not found' );
			}

			$data    = array();
			$updated = array();

			if ( isset( $input['title'] ) ) {
				$data['title'] = sanitize_text_field( $input['title'] );
				$data['slug']  = sanitize_title( $input['title'] );
				$updated[]     = 'title';
			}

			if ( isset( $input['description'] ) ) {
				$data['description'] = sanitize_textarea_field( $input['description'] );
				$updated[]           = 'description';
			}

			if ( empty( $data ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'No fields provided to update' );
			}

			FluentSupportApi( 'tags' )->updateTag( $id, $data );

			return array(
				'success' => true,
				'id'      => $id,
				'updated' => $updated,
			);
		},
	) );

	$reg->delete( 'fluent-support/delete-tag', array(
		'label'       => 'Delete Support Tag',
		'description' => 'Permanently delete a support tag. Removes tag from all tickets.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Tag ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function ( $input ) {
			$id = (int) $input['id'];

			$tag = wpFluent()->table( 'fs_taggables' )
				->where( 'id', $id )
				->first();

			if ( ! $tag ) {
				return fluent_abilities_error( 'not_found', 'Tag not found' );
			}

			// Remove pivot entries first.
			wpFluent()->table( 'fs_tag_pivot' )
				->where( 'tag_id', $id )
				->delete();

			FluentSupportApi( 'tags' )->deleteTag( $id );

			return array(
				'success' => true,
				'id'      => $id,
			);
		},
	) );

	// =========================================================================
	// TICKET-TAG ASSIGNMENT
	// =========================================================================

	$reg->write( 'fluent-support/add-ticket-tag', array(
		'label'       => 'Add Tag to Ticket',
		'description' => 'Assign a tag to a support ticket.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'ticket_id', 'tag_id' ),
			'properties' => array(
				'ticket_id' => array( 'type' => 'integer', 'description' => 'Ticket ID' ),
				'tag_id'    => array( 'type' => 'integer', 'description' => 'Tag ID to assign' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'annotations' => array( 'idempotent' => true ),
		'callback' => function ( $input ) {
			$ticket_id = (int) $input['ticket_id'];
			$tag_id    = (int) $input['tag_id'];

			// Verify ticket exists.
			$ticket = wpFluent()->table( 'fs_tickets' )
				->where( 'id', $ticket_id )
				->first();

			if ( ! $ticket ) {
				return fluent_abilities_error( 'not_found', 'Ticket not found' );
			}

			// Verify tag exists.
			$tag = wpFluent()->table( 'fs_taggables' )
				->where( 'id', $tag_id )
				->first();

			if ( ! $tag ) {
				return fluent_abilities_error( 'not_found', 'Tag not found' );
			}

			// Check if already assigned.
			$exists = wpFluent()->table( 'fs_tag_pivot' )
				->where( 'tag_id', $tag_id )
				->where( 'source_id', $ticket_id )
				->where( 'source_type', 'ticket_tag' )
				->first();

			if ( ! $exists ) {
				$now = current_time( 'mysql' );
				wpFluent()->table( 'fs_tag_pivot' )->insert( array(
					'tag_id'      => $tag_id,
					'source_id'   => $ticket_id,
					'source_type' => 'ticket_tag',
					'created_at'  => $now,
					'updated_at'  => $now,
				) );
			}

			return array( 'success' => true );
		},
	) );

	$reg->delete( 'fluent-support/remove-ticket-tag', array(
		'label'       => 'Remove Tag from Ticket',
		'description' => 'Remove a tag from a support ticket.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'ticket_id', 'tag_id' ),
			'properties' => array(
				'ticket_id' => array( 'type' => 'integer', 'description' => 'Ticket ID' ),
				'tag_id'    => array( 'type' => 'integer', 'description' => 'Tag ID to remove' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'annotations' => array( 'idempotent' => true ),
		'callback' => function ( $input ) {
			$ticket_id = (int) $input['ticket_id'];
			$tag_id    = (int) $input['tag_id'];

			// Verify ticket exists.
			$ticket = wpFluent()->table( 'fs_tickets' )
				->where( 'id', $ticket_id )
				->first();

			if ( ! $ticket ) {
				return fluent_abilities_error( 'not_found', 'Ticket not found' );
			}

			wpFluent()->table( 'fs_tag_pivot' )
				->where( 'tag_id', $tag_id )
				->where( 'source_id', $ticket_id )
				->where( 'source_type', 'ticket_tag' )
				->delete();

			return array( 'success' => true );
		},
	) );

	$reg->read( 'fluent-support/get-ticket-tags', array(
		'label'       => 'Get Ticket Tags',
		'description' => 'Get all tags assigned to a specific ticket.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'ticket_id' ),
			'properties' => array(
				'ticket_id' => array( 'type' => 'integer', 'description' => 'Ticket ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'tags', array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
			'slug'  => array( 'type' => 'string' ),
		) ),
		'callback' => function ( $input ) {
			$ticket_id = (int) $input['ticket_id'];

			// Verify ticket exists.
			$ticket = wpFluent()->table( 'fs_tickets' )
				->where( 'id', $ticket_id )
				->first();

			if ( ! $ticket ) {
				return fluent_abilities_error( 'not_found', 'Ticket not found' );
			}

			$tag_ids = wpFluent()->table( 'fs_tag_pivot' )
				->where( 'source_id', $ticket_id )
				->where( 'source_type', 'ticket_tag' )
				->select( 'tag_id' )
				->get();

			$items = array();
			if ( ! empty( $tag_ids ) ) {
				$ids  = array_map( function ( $row ) { return (int) $row->tag_id; }, $tag_ids );
				$tags = wpFluent()->table( 'fs_taggables' )
					->whereIn( 'id', $ids )
					->get();

				foreach ( $tags as $tag ) {
					$items[] = array(
						'id'    => (int) $tag->id,
						'title' => $tag->title,
						'slug'  => $tag->slug,
					);
				}
			}

			return array( 'tags' => $items, 'total' => count( $items ) );
		},
	) );

}, 100 );
