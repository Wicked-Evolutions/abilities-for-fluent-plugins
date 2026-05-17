<?php
/**
 * FluentCRM Abilities
 *
 * Contact management, tags, lists, campaigns, sequences, email templates,
 * automations, companies, smart links, webhooks, notes, activities, and dashboard stats.
 *
 * 53 abilities in the 'fluent-crm' category.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'crm' );

	// =========================================================================
	// CONTACTS
	// =========================================================================

	$reg->read( 'fluent-crm/list-contacts', array(
		'label'       => 'List CRM Contacts',
		'description' => 'Retrieve contacts with filtering by tags, lists, status, and search. Always use filters to avoid large result sets.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'search' => array(
					'type'        => 'string',
					'description' => 'Search by email or name',
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'Filter by status: subscribed, pending, unsubscribed, bounced, complained',
				),
				'tags' => array(
					'type'        => 'string',
					'description' => 'Comma-separated tag IDs to filter by',
				),
				'lists' => array(
					'type'        => 'string',
					'description' => 'Comma-separated list IDs to filter by',
				),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'contacts', array(
			'id'         => array( 'type' => 'integer' ),
			'email'      => array( 'type' => 'string' ),
			'first_name' => array( 'type' => array( 'string', 'null' ) ),
			'last_name'  => array( 'type' => array( 'string', 'null' ) ),
			'status'     => array( 'type' => 'string' ),
			'created_at' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$query = FluentCrmApi( 'contacts' )->getInstance()->newQuery();

			if ( ! empty( $input['search'] ) ) {
				$search = sanitize_text_field( $input['search'] );
				$query->where( function( $q ) use ( $search ) {
					$q->where( 'email', 'LIKE', "%{$search}%" )
					  ->orWhere( 'first_name', 'LIKE', "%{$search}%" )
					  ->orWhere( 'last_name', 'LIKE', "%{$search}%" );
				});
			}

			if ( ! empty( $input['status'] ) ) {
				$query->where( 'status', sanitize_text_field( $input['status'] ) );
			}

			if ( ! empty( $input['tags'] ) ) {
				$tag_ids = array_map( 'intval', explode( ',', $input['tags'] ) );
				$query->filterByTags( $tag_ids );
			}

			if ( ! empty( $input['lists'] ) ) {
				$list_ids = array_map( 'intval', explode( ',', $input['lists'] ) );
				$query->filterByLists( $list_ids );
			}

			$total = $query->count();
			$contacts = $query->orderBy( 'id', 'DESC' )
				->offset( $pagination['offset'] )
				->limit( $pagination['per_page'] )
				->get();

			$items = array();
			foreach ( $contacts as $contact ) {
				$items[] = array(
					'id'         => $contact->id,
					'email'      => $contact->email,
					'first_name' => $contact->first_name,
					'last_name'  => $contact->last_name,
					'status'     => $contact->status,
					'created_at' => (string) $contact->created_at,
				);
			}

			return array(
				'contacts' => $items,
				'total'    => $total,
				'page'     => $pagination['page'],
				'per_page' => $pagination['per_page'],
			);
		},
	));

	$reg->read( 'fluent-crm/get-contact', array(
		'label'       => 'Get CRM Contact',
		'description' => 'Get a single contact by ID or email, including tags, lists, and custom fields.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Contact ID',
				),
				'email' => array(
					'type'        => 'string',
					'description' => 'Contact email (alternative to ID)',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'             => array( 'type' => 'integer' ),
			'email'          => array( 'type' => 'string' ),
			'first_name'     => array( 'type' => array( 'string', 'null' ) ),
			'last_name'      => array( 'type' => array( 'string', 'null' ) ),
			'full_name'      => array( 'type' => array( 'string', 'null' ) ),
			'status'         => array( 'type' => 'string' ),
			'phone'          => array( 'type' => array( 'string', 'null' ) ),
			'address_line_1' => array( 'type' => array( 'string', 'null' ) ),
			'city'           => array( 'type' => array( 'string', 'null' ) ),
			'country'        => array( 'type' => array( 'string', 'null' ) ),
			'tags'           => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'lists'          => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'created_at'     => array( 'type' => array( 'string', 'null' ) ),
			'updated_at'     => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! empty( $input['id'] ) ) {
				$contact = FluentCrmApi( 'contacts' )->getContact( (int) $input['id'] );
			} elseif ( ! empty( $input['email'] ) ) {
				$contact = FluentCrmApi( 'contacts' )->getContactByUserRef( sanitize_email( $input['email'] ) );
			} else {
				return fluent_abilities_error( 'ability_invalid_input', 'Provide either id or email' );
			}

			if ( ! $contact ) {
				return fluent_abilities_error( 'not_found', 'Contact not found' );
			}

			$tags = $contact->tags ? $contact->tags->map( function( $tag ) {
				return array( 'id' => $tag->id, 'title' => $tag->title );
			})->toArray() : array();

			$lists = $contact->lists ? $contact->lists->map( function( $list ) {
				return array( 'id' => $list->id, 'title' => $list->title );
			})->toArray() : array();

			return array(
				'id'            => $contact->id,
				'email'         => $contact->email,
				'first_name'    => $contact->first_name,
				'last_name'     => $contact->last_name,
				'full_name'     => $contact->full_name,
				'status'        => $contact->status,
				'phone'         => $contact->phone,
				'address_line_1'=> $contact->address_line_1,
				'city'          => $contact->city,
				'country'       => $contact->country,
				'tags'          => $tags,
				'lists'         => $lists,
				'created_at'    => (string) $contact->created_at,
				'updated_at'    => (string) $contact->updated_at,
			);
		},
	));

	$reg->write( 'fluent-crm/create-contact', array(
		'label'       => 'Create CRM Contact',
		'description' => 'Create a new contact in FluentCRM. Optionally attach tags and lists.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'email' ),
			'properties' => array(
				'email'         => array( 'type' => 'string', 'description' => 'Contact email (required)' ),
				'first_name'    => array( 'type' => 'string', 'description' => 'First name' ),
				'last_name'     => array( 'type' => 'string', 'description' => 'Last name' ),
				'status'        => array( 'type' => 'string', 'description' => 'Status: subscribed, pending, unsubscribed, transactional (default: subscribed)' ),
				'phone'         => array( 'type' => 'string', 'description' => 'Phone number' ),
				'address_line_1'=> array( 'type' => 'string', 'description' => 'Address' ),
				'city'          => array( 'type' => 'string', 'description' => 'City' ),
				'country'       => array( 'type' => 'string', 'description' => 'Country code (e.g., SE, US)' ),
				'tags'          => array( 'type' => 'string', 'description' => 'Comma-separated tag IDs to attach' ),
				'lists'         => array( 'type' => 'string', 'description' => 'Comma-separated list IDs to attach' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'     => array( 'type' => 'integer' ),
			'email'  => array( 'type' => 'string' ),
			'status' => array( 'type' => 'string' ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			$data = array(
				'email'      => sanitize_email( $input['email'] ),
				'first_name' => sanitize_text_field( $input['first_name'] ?? '' ),
				'last_name'  => sanitize_text_field( $input['last_name'] ?? '' ),
				'status'     => sanitize_text_field( $input['status'] ?? 'subscribed' ),
			);

			foreach ( array( 'phone', 'address_line_1', 'city', 'country' ) as $field ) {
				if ( ! empty( $input[ $field ] ) ) {
					$data[ $field ] = sanitize_text_field( $input[ $field ] );
				}
			}

			$contact = FluentCrmApi( 'contacts' )->createOrUpdate( $data );

			if ( ! $contact || ! $contact->id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Failed to create contact' );
			}

			if ( ! empty( $input['tags'] ) ) {
				$tag_ids = array_map( 'intval', explode( ',', $input['tags'] ) );
				$contact->attachTags( $tag_ids );
			}

			if ( ! empty( $input['lists'] ) ) {
				$list_ids = array_map( 'intval', explode( ',', $input['lists'] ) );
				$contact->attachLists( $list_ids );
			}

			return array(
				'success' => true,
				'id'      => $contact->id,
				'email'   => $contact->email,
				'status'  => $contact->status,
			);
		},
	));

	$reg->write( 'fluent-crm/update-contact', array(
		'label'       => 'Update CRM Contact',
		'description' => 'Update an existing contact by ID. Only provided fields are changed.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'            => array( 'type' => 'integer', 'description' => 'Contact ID' ),
				'email'         => array( 'type' => 'string', 'description' => 'New email' ),
				'first_name'    => array( 'type' => 'string', 'description' => 'First name' ),
				'last_name'     => array( 'type' => 'string', 'description' => 'Last name' ),
				'status'        => array( 'type' => 'string', 'description' => 'Status' ),
				'phone'         => array( 'type' => 'string', 'description' => 'Phone' ),
				'address_line_1'=> array( 'type' => 'string', 'description' => 'Address' ),
				'city'          => array( 'type' => 'string', 'description' => 'City' ),
				'country'       => array( 'type' => 'string', 'description' => 'Country code' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'      => array( 'type' => 'integer' ),
			'updated' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
		) ),
		'callback' => function( $input ) {
			$contact = FluentCrmApi( 'contacts' )->getContact( (int) $input['id'] );
			if ( ! $contact ) {
				return fluent_abilities_error( 'not_found', 'Contact not found' );
			}

			$data = array();
			foreach ( array( 'email', 'first_name', 'last_name', 'status', 'phone', 'address_line_1', 'city', 'country' ) as $field ) {
				if ( isset( $input[ $field ] ) ) {
					$data[ $field ] = $field === 'email'
						? sanitize_email( $input[ $field ] )
						: sanitize_text_field( $input[ $field ] );
				}
			}

			if ( ! empty( $data ) ) {
				$contact->fill( $data )->save();
			}

			return array(
				'success' => true,
				'id'      => $contact->id,
				'updated' => array_keys( $data ),
			);
		},
	));

	$reg->delete( 'fluent-crm/delete-contact', array(
		'label'       => 'Delete CRM Contact',
		'description' => 'Permanently delete a contact by ID.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Contact ID to delete' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'deleted_id' => array( 'type' => 'integer' ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			$contact = FluentCrmApi( 'contacts' )->getContact( (int) $input['id'] );
			if ( ! $contact ) {
				return fluent_abilities_error( 'not_found', 'Contact not found' );
			}

			$contact->delete();

			return array( 'success' => true, 'deleted_id' => (int) $input['id'] );
		},
	));

	// =========================================================================
	// TAGS
	// =========================================================================

	$reg->read( 'fluent-crm/list-tags', array(
		'label'       => 'List CRM Tags',
		'description' => 'List all tags in FluentCRM with optional search.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'search' => array( 'type' => 'string', 'description' => 'Search by tag title' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'tags', array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
			'slug'  => array( 'type' => 'string' ),
			'count' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$query = \FluentCrm\App\Models\Tag::orderBy( 'title', 'ASC' );

			if ( ! empty( $input['search'] ) ) {
				$query->where( 'title', 'LIKE', '%' . sanitize_text_field( $input['search'] ) . '%' );
			}

			$tags = $query->get();
			$items = array();
			foreach ( $tags as $tag ) {
				$items[] = array(
					'id'    => $tag->id,
					'title' => $tag->title,
					'slug'  => $tag->slug,
					'count' => $tag->subscribers ? $tag->subscribers()->count() : 0,
				);
			}

			return array( 'tags' => $items, 'total' => count( $items ) );
		},
	));

	$reg->write( 'fluent-crm/create-tag', array(
		'label'       => 'Create CRM Tag',
		'description' => 'Create a new tag in FluentCRM.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'title' ),
			'properties' => array(
				'title' => array( 'type' => 'string', 'description' => 'Tag title' ),
				'slug'  => array( 'type' => 'string', 'description' => 'Tag slug (auto-generated from title if omitted)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
			'slug'  => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			$data = array( 'title' => sanitize_text_field( $input['title'] ) );
			if ( ! empty( $input['slug'] ) ) {
				$data['slug'] = sanitize_title( $input['slug'] );
			} else {
				$data['slug'] = sanitize_title( $data['title'] );
			}

			$tag = \FluentCrm\App\Models\Tag::create( $data );

			return array( 'success' => true, 'id' => $tag->id, 'title' => $tag->title, 'slug' => $tag->slug ?: sanitize_title( $tag->title ) );
		},
	));

	$reg->write( 'fluent-crm/attach-tag', array(
		'label'       => 'Attach Tag to Contact',
		'description' => 'Attach one or more tags to a contact. Note: `tag_ids` must be a comma-separated string (e.g. "5,8,12"), not a JSON array — the handler splits it via explode(",", ...).',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'contact_id', 'tag_ids' ),
			'properties' => array(
				'contact_id' => array( 'type' => 'integer', 'description' => 'Contact ID' ),
				'tag_ids'    => array( 'type' => 'string', 'description' => 'Comma-separated tag IDs' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'contact_id'    => array( 'type' => 'integer' ),
			'attached_tags' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
		) ),
		'callback' => function( $input ) {
			$contact = FluentCrmApi( 'contacts' )->getContact( (int) $input['contact_id'] );
			if ( ! $contact ) {
				return fluent_abilities_error( 'not_found', 'Contact not found' );
			}

			$tag_ids = array_map( 'intval', explode( ',', $input['tag_ids'] ) );
			$contact->attachTags( $tag_ids );

			return array( 'success' => true, 'contact_id' => $contact->id, 'attached_tags' => $tag_ids );
		},
	));

	$reg->write( 'fluent-crm/detach-tag', array(
		'label'       => 'Detach Tag from Contact',
		'description' => 'Remove one or more tags from a contact. Note: `tag_ids` must be a comma-separated string (e.g. "5,8,12"), not a JSON array — the handler splits it via explode(",", ...).',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'contact_id', 'tag_ids' ),
			'properties' => array(
				'contact_id' => array( 'type' => 'integer', 'description' => 'Contact ID' ),
				'tag_ids'    => array( 'type' => 'string', 'description' => 'Comma-separated tag IDs' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'contact_id'    => array( 'type' => 'integer' ),
			'detached_tags' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
		) ),
		'callback' => function( $input ) {
			$contact = FluentCrmApi( 'contacts' )->getContact( (int) $input['contact_id'] );
			if ( ! $contact ) {
				return fluent_abilities_error( 'not_found', 'Contact not found' );
			}

			$tag_ids = array_map( 'intval', explode( ',', $input['tag_ids'] ) );
			$contact->detachTags( $tag_ids );

			return array( 'success' => true, 'contact_id' => $contact->id, 'detached_tags' => $tag_ids );
		},
	));

	// =========================================================================
	// LISTS
	// =========================================================================

	$reg->read( 'fluent-crm/list-lists', array(
		'label'       => 'List CRM Lists',
		'description' => 'List all contact lists in FluentCRM.',
		'category'    => 'fluent-crm',
		'output_schema' => fluent_abilities_schema_collection_output( 'lists', array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
			'slug'  => array( 'type' => 'string' ),
			'count' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$lists = \FluentCrm\App\Models\Lists::orderBy( 'title', 'ASC' )->get();
			$items = array();
			foreach ( $lists as $list ) {
				$items[] = array(
					'id'    => $list->id,
					'title' => $list->title,
					'slug'  => $list->slug,
					'count' => $list->subscribers ? $list->subscribers()->count() : 0,
				);
			}

			return array( 'lists' => $items, 'total' => count( $items ) );
		},
	));

	$reg->write( 'fluent-crm/attach-list', array(
		'label'       => 'Attach Contact to List',
		'description' => 'Add a contact to one or more lists.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'contact_id', 'list_ids' ),
			'properties' => array(
				'contact_id' => array( 'type' => 'integer', 'description' => 'Contact ID' ),
				'list_ids'   => array( 'type' => 'string', 'description' => 'Comma-separated list IDs' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'contact_id'    => array( 'type' => 'integer' ),
			'attached_lists'=> array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
		) ),
		'callback' => function( $input ) {
			$contact = FluentCrmApi( 'contacts' )->getContact( (int) $input['contact_id'] );
			if ( ! $contact ) {
				return fluent_abilities_error( 'not_found', 'Contact not found' );
			}

			$list_ids = array_map( 'intval', explode( ',', $input['list_ids'] ) );
			$contact->attachLists( $list_ids );

			return array( 'success' => true, 'contact_id' => $contact->id, 'attached_lists' => $list_ids );
		},
	));

	$reg->write( 'fluent-crm/detach-list', array(
		'label'       => 'Detach Contact from List',
		'description' => 'Remove a contact from one or more lists.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'contact_id', 'list_ids' ),
			'properties' => array(
				'contact_id' => array( 'type' => 'integer', 'description' => 'Contact ID' ),
				'list_ids'   => array( 'type' => 'string', 'description' => 'Comma-separated list IDs' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'contact_id'    => array( 'type' => 'integer' ),
			'detached_lists'=> array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
		) ),
		'callback' => function( $input ) {
			$contact = FluentCrmApi( 'contacts' )->getContact( (int) $input['contact_id'] );
			if ( ! $contact ) {
				return fluent_abilities_error( 'not_found', 'Contact not found' );
			}

			$list_ids = array_map( 'intval', explode( ',', $input['list_ids'] ) );
			$contact->detachLists( $list_ids );

			return array( 'success' => true, 'contact_id' => $contact->id, 'detached_lists' => $list_ids );
		},
	));

	// =========================================================================
	// CAMPAIGNS
	// =========================================================================

	$reg->read( 'fluent-crm/list-campaigns', array(
		'label'       => 'List CRM Campaigns',
		'description' => 'List email campaigns with optional status filter.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'status' => array( 'type' => 'string', 'description' => 'Filter: draft, scheduled, working, sent, paused' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'campaigns', array(
			'id'           => array( 'type' => 'integer' ),
			'title'        => array( 'type' => 'string' ),
			'subject'      => array( 'type' => array( 'string', 'null' ) ),
			'status'       => array( 'type' => 'string' ),
			'type'         => array( 'type' => 'string' ),
			'scheduled_at' => array( 'type' => array( 'string', 'null' ) ),
			'created_at'   => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$query = \FluentCrm\App\Models\Campaign::orderBy( 'id', 'DESC' );

			if ( ! empty( $input['status'] ) ) {
				$query->where( 'status', sanitize_text_field( $input['status'] ) );
			}

			$total = $query->count();
			$campaigns = $query->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();

			$items = array();
			foreach ( $campaigns as $campaign ) {
				$items[] = array(
					'id'           => $campaign->id,
					'title'        => $campaign->title,
					'subject'      => $campaign->subject,
					'status'       => $campaign->status,
					'type'         => $campaign->type,
					'scheduled_at' => $campaign->scheduled_at,
					'created_at'   => (string) $campaign->created_at,
				);
			}

			return array(
				'campaigns' => $items,
				'total'     => $total,
				'page'      => $pagination['page'],
				'per_page'  => $pagination['per_page'],
			);
		},
	));

	$reg->read( 'fluent-crm/get-campaign', array(
		'label'       => 'Get CRM Campaign',
		'description' => 'Get campaign details by ID including stats.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Campaign ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'           => array( 'type' => 'integer' ),
			'title'        => array( 'type' => 'string' ),
			'subject'      => array( 'type' => array( 'string', 'null' ) ),
			'status'       => array( 'type' => 'string' ),
			'type'         => array( 'type' => 'string' ),
			'body'         => array( 'type' => array( 'string', 'null' ) ),
			'scheduled_at' => array( 'type' => array( 'string', 'null' ) ),
			'created_at'   => array( 'type' => 'string' ),
			'updated_at'   => array( 'type' => 'string' ),
			'settings'     => array( 'type' => array( 'object', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$campaign = \FluentCrm\App\Models\Campaign::find( (int) $input['id'] );
			if ( ! $campaign ) {
				return fluent_abilities_error( 'not_found', 'Campaign not found' );
			}

			return array(
				'id'           => $campaign->id,
				'title'        => $campaign->title,
				'subject'      => $campaign->email_subject,
				'status'       => $campaign->status,
				'type'         => $campaign->type,
				'body'         => $campaign->email_body,
				'scheduled_at' => $campaign->scheduled_at,
				'created_at'   => (string) $campaign->created_at,
				'updated_at'   => (string) $campaign->updated_at,
				'settings'     => fluent_abilities_safe_array( $campaign->settings ),
			);
		},
	));

	$reg->write( 'fluent-crm/create-campaign', array(
		'label'       => 'Create CRM Campaign',
		'description' => 'Create a new email campaign (draft status). Does NOT send — use separate send ability.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'title', 'subject' ),
			'properties' => array(
				'title'   => array( 'type' => 'string', 'description' => 'Campaign title' ),
				'subject' => array( 'type' => 'string', 'description' => 'Email subject line' ),
				'body'    => array( 'type' => 'string', 'description' => 'Email body HTML' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'     => array( 'type' => 'integer' ),
			'status' => array( 'type' => 'string' ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			$campaign = \FluentCrm\App\Models\Campaign::create( array(
				'title'         => sanitize_text_field( $input['title'] ),
				'email_subject' => sanitize_text_field( $input['subject'] ),
				'email_body'    => wp_kses_post( $input['body'] ?? '' ),
				'status'        => 'draft',
				'type'          => 'campaign',
			));

			return array( 'success' => true, 'id' => $campaign->id, 'status' => 'draft' );
		},
	));

	$reg->write( 'fluent-crm/update-campaign', array(
		'label'       => 'Update CRM Campaign',
		'description' => 'Update a draft campaign title, subject, body, or UTM parameters. Only draft campaigns can be updated — live/scheduled campaigns will be rejected. Note: the identifying field is `campaign_id` (not `id`). Source: \FluentCrm\App\Models\Campaign::find/fill/save.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'campaign_id' ),
			'properties' => array(
				'campaign_id'  => array( 'type' => 'integer', 'description' => 'Campaign ID' ),
				'title'        => array( 'type' => 'string', 'description' => 'Campaign title' ),
				'email_subject' => array( 'type' => 'string', 'description' => 'Email subject line' ),
				'email_body'   => array( 'type' => 'string', 'description' => 'Email body HTML' ),
				'utm_status'   => array( 'type' => 'string', 'description' => 'UTM tracking: yes or no' ),
				'utm_source'   => array( 'type' => 'string', 'description' => 'UTM source parameter' ),
				'utm_medium'   => array( 'type' => 'string', 'description' => 'UTM medium parameter' ),
				'utm_campaign' => array( 'type' => 'string', 'description' => 'UTM campaign parameter' ),
				'utm_term'     => array( 'type' => 'string', 'description' => 'UTM term parameter' ),
				'utm_content'  => array( 'type' => 'string', 'description' => 'UTM content parameter' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'campaign_id' => array( 'type' => 'integer' ),
			'updated'     => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'status'      => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$campaign = \FluentCrm\App\Models\Campaign::find( absint( $input['campaign_id'] ) );
			if ( ! $campaign ) {
				return fluent_abilities_error( 'not_found', 'Campaign not found' );
			}
			if ( $campaign->status !== 'draft' ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Only draft campaigns can be updated. Current status: ' . $campaign->status );
			}

			$updatable = array();
			if ( isset( $input['title'] ) ) {
				$updatable['title'] = sanitize_text_field( $input['title'] );
			}
			if ( isset( $input['email_subject'] ) ) {
				$updatable['subject'] = sanitize_text_field( $input['email_subject'] );
			}
			if ( isset( $input['email_body'] ) ) {
				$updatable['body'] = wp_kses_post( $input['email_body'] );
			}
			$utm_fields = array( 'utm_status', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' );
			foreach ( $utm_fields as $field ) {
				if ( isset( $input[ $field ] ) ) {
					$updatable[ $field ] = sanitize_text_field( $input[ $field ] );
				}
			}

			if ( empty( $updatable ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'No fields provided to update' );
			}

			$campaign->fill( $updatable );
			$campaign->save();

			return array(
				'success'     => true,
				'campaign_id' => $campaign->id,
				'updated'     => array_keys( $updatable ),
				'status'      => $campaign->status,
			);
		},
	));

	// =========================================================================
	// SEQUENCES
	// =========================================================================

	$reg->read( 'fluent-crm/list-sequences', array(
		'label'       => 'List CRM Sequences',
		'description' => 'List email sequences (drip campaigns) with email count.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => fluent_abilities_pagination_schema(),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'sequences', array(
			'id'              => array( 'type' => 'integer' ),
			'title'           => array( 'type' => 'string' ),
			'status'          => array( 'type' => 'string' ),
			'design_template' => array( 'type' => 'string' ),
			'email_count'     => array( 'type' => 'integer' ),
			'settings'        => array( 'type' => 'object' ),
			'created_at'      => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$query = \FluentCampaign\App\Models\Sequence::orderBy( 'id', 'DESC' );
			$total = $query->count();
			$sequences = $query->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();

			$items = array();
			foreach ( $sequences as $seq ) {
				$email_count = \FluentCampaign\App\Models\SequenceMail::where( 'parent_id', $seq->id )->count();
				$items[] = array(
					'id'              => $seq->id,
					'title'           => $seq->title,
					'status'          => $seq->status,
					'design_template' => $seq->design_template,
					'email_count'     => $email_count,
					'settings'        => fluent_abilities_safe_array( $seq->settings ),
					'created_at'      => (string) $seq->created_at,
				);
			}

			return array( 'sequences' => $items, 'total' => $total, 'page' => $pagination['page'] );
		},
	));

	$reg->read( 'fluent-crm/get-sequence', array(
		'label'       => 'Get CRM Sequence',
		'description' => 'Get a single email sequence by ID, including its settings and email count.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Sequence ID',
				),
			),
			'required' => array( 'id' ),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'              => array( 'type' => 'integer' ),
			'title'           => array( 'type' => 'string' ),
			'status'          => array( 'type' => 'string' ),
			'design_template' => array( 'type' => 'string' ),
			'settings'        => array( 'type' => 'object' ),
			'email_count'     => array( 'type' => 'integer' ),
			'emails'          => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'created_at'      => array( 'type' => 'string' ),
			'updated_at'      => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$sequence = \FluentCampaign\App\Models\Sequence::find( intval( $input['id'] ) );
			if ( ! $sequence ) {
				return new \WP_Error( 'not_found', 'Sequence not found' );
			}

			$emails = \FluentCampaign\App\Models\SequenceMail::where( 'parent_id', $sequence->id )
				->orderBy( 'delay', 'ASC' )
				->get();

			$email_items = array();
			foreach ( $emails as $e ) {
				$email_items[] = array(
					'id'              => $e->id,
					'email_subject'   => $e->email_subject,
					'design_template' => $e->design_template,
					'delay'           => $e->delay,
					'delay_days'      => round( $e->delay / 86400, 1 ),
					'status'          => $e->status,
					'body_length'     => strlen( $e->email_body ),
				);
			}

			return array(
				'id'              => $sequence->id,
				'title'           => $sequence->title,
				'status'          => $sequence->status,
				'design_template' => $sequence->design_template,
				'settings'        => fluent_abilities_safe_array( $sequence->settings ),
				'email_count'     => count( $email_items ),
				'emails'          => $email_items,
				'created_at'      => (string) $sequence->created_at,
				'updated_at'      => (string) $sequence->updated_at,
			);
		},
	));

	// =========================================================================
	// SEQUENCE EMAILS
	// =========================================================================

	$reg->read( 'fluent-crm/list-sequence-emails', array(
		'label'       => 'List Sequence Emails',
		'description' => 'List all emails in a sequence with subject, delay, status, template, and body length.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'sequence_id' => array(
					'type'        => 'integer',
					'description' => 'Parent sequence ID',
				),
			),
			'required' => array( 'sequence_id' ),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'emails', array(
			'id'              => array( 'type' => 'integer' ),
			'email_subject'   => array( 'type' => 'string' ),
			'design_template' => array( 'type' => 'string' ),
			'delay'           => array( 'type' => 'integer' ),
			'delay_days'      => array( 'type' => 'number' ),
			'status'          => array( 'type' => 'string' ),
			'body_length'     => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$sequence_id = intval( $input['sequence_id'] );
			$sequence = \FluentCampaign\App\Models\Sequence::find( $sequence_id );
			if ( ! $sequence ) {
				return new \WP_Error( 'not_found', 'Sequence not found' );
			}

			$emails = \FluentCampaign\App\Models\SequenceMail::where( 'parent_id', $sequence_id )
				->orderBy( 'delay', 'ASC' )
				->get();

			$items = array();
			foreach ( $emails as $e ) {
				$timings = isset( $e->settings['timings'] ) ? $e->settings['timings'] : array();
				$items[] = array(
					'id'               => $e->id,
					'title'            => $e->title,
					'email_subject'    => $e->email_subject,
					'email_pre_header' => $e->email_pre_header,
					'design_template'  => $e->design_template,
					'status'           => $e->status,
					'delay_seconds'    => $e->delay,
					'delay_days'       => round( $e->delay / 86400, 1 ),
					'delay_config'     => $timings,
					'body_length'      => strlen( $e->email_body ),
					'created_at'       => (string) $e->created_at,
					'updated_at'       => (string) $e->updated_at,
				);
			}

			return array(
				'sequence_id'    => $sequence_id,
				'sequence_title' => $sequence->title,
				'emails'         => $items,
				'total'          => count( $items ),
			);
		},
	));

	$reg->write( 'fluent-crm/add-sequence-email', array(
		'label'       => 'Add Email to Sequence',
		'description' => 'Create a new email in a sequence. Uses the SequenceMail model which auto-handles serialization, delay calculation, and defaults. Delay is specified in days (converted to seconds automatically). Design template slugs: simple (Simple Boxed), plain (Plain Centered), classic (Plain Left), raw_classic (Classic Editor).',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'sequence_id' => array(
					'type'        => 'integer',
					'description' => 'Parent sequence ID to add the email to',
				),
				'email_subject' => array(
					'type'        => 'string',
					'description' => 'Email subject line (also used as title)',
				),
				'email_body' => array(
					'type'        => 'string',
					'description' => 'Email body content (WordPress block markup)',
				),
				'email_pre_header' => array(
					'type'        => 'string',
					'description' => 'Preview text shown in inbox before opening',
				),
				'delay_days' => array(
					'type'        => 'number',
					'description' => 'Days after sequence start to send this email (0 = immediately)',
				),
				'delay_unit' => array(
					'type'        => 'string',
					'description' => 'Delay unit: days (default), hours, minutes',
					'enum'        => array( 'days', 'hours', 'minutes' ),
				),
				'design_template' => array(
					'type'        => 'string',
					'description' => 'Template slug: simple, plain, classic (Plain Left), raw_classic',
					'enum'        => array( 'simple', 'plain', 'classic', 'raw_classic' ),
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'Email status: draft or published (default: published)',
					'enum'        => array( 'draft', 'published' ),
				),
			),
			'required' => array( 'sequence_id', 'email_subject' ),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'message' => array( 'type' => 'string' ),
			'email'   => array( 'type' => 'object' ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			$sequence_id = intval( $input['sequence_id'] );
			$sequence = \FluentCampaign\App\Models\Sequence::find( $sequence_id );
			if ( ! $sequence ) {
				return new \WP_Error( 'not_found', 'Sequence not found' );
			}

			$delay_unit  = isset( $input['delay_unit'] ) ? sanitize_text_field( $input['delay_unit'] ) : 'days';
			$delay_value = isset( $input['delay_days'] ) ? floatval( $input['delay_days'] ) : 0;

			$email_data = array(
				'parent_id'        => $sequence_id,
				'title'            => sanitize_text_field( $input['email_subject'] ),
				'email_subject'    => sanitize_text_field( $input['email_subject'] ),
				'email_body'       => isset( $input['email_body'] ) ? wp_kses_post( $input['email_body'] ) : '',
				'email_pre_header' => isset( $input['email_pre_header'] ) ? sanitize_text_field( $input['email_pre_header'] ) : '',
				'design_template'  => isset( $input['design_template'] ) ? sanitize_text_field( $input['design_template'] ) : $sequence->design_template,
				'status'           => isset( $input['status'] ) ? sanitize_text_field( $input['status'] ) : 'published',
				'settings'         => array(
					'action_triggers' => array(),
					'timings'         => array(
						'delay_unit'   => $delay_unit,
						'delay'        => $delay_value,
						'is_anytime'   => 'yes',
						'sending_time' => '',
					),
					'template_config'  => \FluentCrm\App\Services\Helper::getTemplateConfig(
						isset( $input['design_template'] ) ? $input['design_template'] : $sequence->design_template
					),
					'mailer_settings' => isset( $sequence->settings['mailer_settings'] )
						? $sequence->settings['mailer_settings']
						: array(
							'from_name'      => '',
							'from_email'     => '',
							'reply_to_name'  => '',
							'reply_to_email' => '',
							'is_custom'      => 'no',
						),
				),
			);

			$email = \FluentCampaign\App\Models\SequenceMail::create( $email_data );

			return array(
				'message'   => 'Sequence email created',
				'email'     => array(
					'id'              => $email->id,
					'email_subject'   => $email->email_subject,
					'design_template' => $email->design_template,
					'delay_seconds'   => $email->delay,
					'delay_days'      => round( $email->delay / 86400, 1 ),
					'status'          => $email->status,
					'parent_id'       => $email->parent_id,
				),
			);
		},
	));

	$reg->write( 'fluent-crm/update-sequence-email', array(
		'label'       => 'Update Sequence Email',
		'description' => 'Update an existing email in a sequence. Only provided fields are updated — omit fields to keep existing values. Design template slugs: simple (Simple Boxed), plain (Plain Centered), classic (Plain Left), raw_classic (Classic Editor).',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'email_id' => array(
					'type'        => 'integer',
					'description' => 'Sequence email ID to update',
				),
				'email_subject' => array(
					'type'        => 'string',
					'description' => 'New email subject line',
				),
				'email_body' => array(
					'type'        => 'string',
					'description' => 'New email body content (WordPress block markup)',
				),
				'email_pre_header' => array(
					'type'        => 'string',
					'description' => 'New preview text',
				),
				'delay_days' => array(
					'type'        => 'number',
					'description' => 'New delay in days from sequence start',
				),
				'delay_unit' => array(
					'type'        => 'string',
					'description' => 'Delay unit: days (default), hours, minutes',
					'enum'        => array( 'days', 'hours', 'minutes' ),
				),
				'design_template' => array(
					'type'        => 'string',
					'description' => 'Template slug: simple, plain, classic (Plain Left), raw_classic',
					'enum'        => array( 'simple', 'plain', 'classic', 'raw_classic' ),
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'Email status: draft or published',
					'enum'        => array( 'draft', 'published' ),
				),
			),
			'required' => array( 'email_id' ),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'email_id' => array( 'type' => 'integer' ),
			'updated'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
		) ),
		'callback' => function( $input ) {
			$email_id = intval( $input['email_id'] );
			$email = \FluentCampaign\App\Models\SequenceMail::find( $email_id );
			if ( ! $email ) {
				return new \WP_Error( 'not_found', 'Sequence email not found' );
			}

			$update_data = array();

			if ( isset( $input['email_subject'] ) ) {
				$update_data['email_subject'] = sanitize_text_field( $input['email_subject'] );
				$update_data['title'] = $update_data['email_subject'];
			}

			if ( isset( $input['email_body'] ) ) {
				$update_data['email_body'] = wp_kses_post( $input['email_body'] );
			}

			if ( isset( $input['email_pre_header'] ) ) {
				$update_data['email_pre_header'] = sanitize_text_field( $input['email_pre_header'] );
			}

			if ( isset( $input['design_template'] ) ) {
				$update_data['design_template'] = sanitize_text_field( $input['design_template'] );
			}

			if ( isset( $input['status'] ) ) {
				$update_data['status'] = sanitize_text_field( $input['status'] );
			}

			// Handle delay/timing updates
			if ( isset( $input['delay_days'] ) || isset( $input['delay_unit'] ) ) {
				$settings = $email->settings;
				$timings = isset( $settings['timings'] ) ? $settings['timings'] : array();

				if ( isset( $input['delay_days'] ) ) {
					$timings['delay'] = floatval( $input['delay_days'] );
				}
				if ( isset( $input['delay_unit'] ) ) {
					$timings['delay_unit'] = sanitize_text_field( $input['delay_unit'] );
				}

				$settings['timings'] = $timings;

				// Update template_config if design_template changed
				if ( isset( $input['design_template'] ) ) {
					$settings['template_config'] = \FluentCrm\App\Services\Helper::getTemplateConfig( $input['design_template'] );
				}

				$update_data['settings'] = $settings;
			} elseif ( isset( $input['design_template'] ) ) {
				// Template changed but not delay — still update template_config
				$settings = $email->settings;
				$settings['template_config'] = \FluentCrm\App\Services\Helper::getTemplateConfig( $input['design_template'] );
				$update_data['settings'] = $settings;
			}

			$email->fill( $update_data )->save();

			return array(
				'message' => 'Sequence email updated',
				'email'   => array(
					'id'              => $email->id,
					'email_subject'   => $email->email_subject,
					'design_template' => $email->design_template,
					'delay_seconds'   => $email->delay,
					'delay_days'      => round( $email->delay / 86400, 1 ),
					'status'          => $email->status,
					'body_length'     => strlen( $email->email_body ),
					'parent_id'       => $email->parent_id,
				),
			);
		},
	));

	$reg->delete( 'fluent-crm/delete-sequence-email', array(
		'label'       => 'Delete Sequence Email',
		'description' => 'Delete an email from a sequence. Also removes any scheduled campaign emails and URL metrics for that email.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'email_id' => array(
					'type'        => 'integer',
					'description' => 'Sequence email ID to delete',
				),
			),
			'required' => array( 'email_id' ),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'deleted_id' => array( 'type' => 'integer' ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			$email_id = intval( $input['email_id'] );
			$email = \FluentCampaign\App\Models\SequenceMail::find( $email_id );
			if ( ! $email ) {
				return new \WP_Error( 'not_found', 'Sequence email not found' );
			}

			$parent_id = $email->parent_id;
			$subject   = $email->email_subject;

			// Delete scheduled campaign emails for this sequence email
			\FluentCrm\App\Models\CampaignEmail::where( 'campaign_id', $email_id )->delete();
			// Delete URL metrics
			\FluentCrm\App\Models\CampaignUrlMetric::where( 'campaign_id', $email_id )->delete();
			// Delete campaign meta
			fluentcrm_delete_campaign_meta( $email_id, '' );
			// Delete the sequence email itself
			$email->delete();

			return array(
				'message'     => 'Sequence email deleted',
				'deleted_id'  => $email_id,
				'subject'     => $subject,
				'sequence_id' => $parent_id,
			);
		},
	));

	// =========================================================================
	// SEQUENCE MANAGEMENT
	// =========================================================================

	$reg->write( 'fluent-crm/update-sequence', array(
		'label'       => 'Update Sequence',
		'description' => 'Update a sequence title or settings.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'sequence_id' ),
			'properties' => array(
				'sequence_id' => array( 'type' => 'integer', 'description' => 'Sequence ID' ),
				'title'       => array( 'type' => 'string', 'description' => 'New sequence title' ),
				'settings'    => array( 'type' => 'object', 'description' => 'Sequence settings to merge (partial update)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'      => array( 'type' => 'integer' ),
			'updated' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
		) ),
		'callback' => function( $input ) {
			$sequence = \FluentCampaign\App\Models\Sequence::find( absint( $input['sequence_id'] ) );
			if ( ! $sequence ) {
				return fluent_abilities_error( 'not_found', 'Sequence not found' );
			}

			$updated = array();
			if ( isset( $input['title'] ) ) {
				$sequence->title = sanitize_text_field( $input['title'] );
				$updated[] = 'title';
			}
			if ( isset( $input['settings'] ) && is_array( $input['settings'] ) ) {
				$existing_settings = $sequence->settings ?: array();
				if ( is_string( $existing_settings ) ) {
					$existing_settings = maybe_unserialize( $existing_settings );
				}
				$sequence->settings = array_merge( $existing_settings, $input['settings'] );
				$updated[] = 'settings';
			}

			if ( empty( $updated ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'No fields provided to update' );
			}

			$sequence->save();

			return array(
				'success'     => true,
				'sequence_id' => $sequence->id,
				'title'       => $sequence->title,
				'updated'     => $updated,
			);
		},
	));

	$reg->write( 'fluent-crm/add-contact-to-sequence', array(
		'label'       => 'Add Contact to Sequence',
		'description' => 'Enroll a contact into an email sequence. Creates scheduled emails for all sequence steps. Contact must be a subscribed FluentCRM contact.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'sequence_id', 'contact_id' ),
			'properties' => array(
				'sequence_id' => array( 'type' => 'integer', 'description' => 'Sequence ID' ),
				'contact_id'  => array( 'type' => 'integer', 'description' => 'FluentCRM contact ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'contact_id'  => array( 'type' => 'integer' ),
			'sequence_id' => array( 'type' => 'integer' ),
			'status'      => array( 'type' => 'string' ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			$sequence = \FluentCampaign\App\Models\Sequence::find( absint( $input['sequence_id'] ) );
			if ( ! $sequence ) {
				return fluent_abilities_error( 'not_found', 'Sequence not found' );
			}

			$subscriber = FluentCrmApi( 'contacts' )->getContact( absint( $input['contact_id'] ) );
			if ( ! $subscriber ) {
				return fluent_abilities_error( 'not_found', 'Contact not found' );
			}

			// Check if already enrolled and active.
			$existing = \FluentCampaign\App\Models\SequenceTracker::where( 'campaign_id', $sequence->id )
				->where( 'subscriber_id', $subscriber->id )
				->where( 'status', 'active' )
				->first();

			if ( $existing ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Contact is already actively enrolled in this sequence' );
			}

			$sequence->subscribe( array( $subscriber ) );

			return array(
				'success'     => true,
				'sequence_id' => $sequence->id,
				'contact_id'  => $subscriber->id,
				'email'       => $subscriber->email,
			);
		},
	));

	$reg->write( 'fluent-crm/remove-contact-from-sequence', array(
		'label'       => 'Remove Contact from Sequence',
		'description' => 'Soft-cancel a contact from a sequence. Sets tracker status to "cancelled" and cancels all pending scheduled emails. Does NOT delete any records.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'sequence_id', 'contact_id' ),
			'properties' => array(
				'sequence_id' => array( 'type' => 'integer', 'description' => 'Sequence ID' ),
				'contact_id'  => array( 'type' => 'integer', 'description' => 'FluentCRM contact ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'contact_id'  => array( 'type' => 'integer' ),
			'sequence_id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$sequence = \FluentCampaign\App\Models\Sequence::find( absint( $input['sequence_id'] ) );
			if ( ! $sequence ) {
				return fluent_abilities_error( 'not_found', 'Sequence not found' );
			}

			$contact_id = absint( $input['contact_id'] );

			// Check if enrolled.
			$tracker = \FluentCampaign\App\Models\SequenceTracker::where( 'campaign_id', $sequence->id )
				->where( 'subscriber_id', $contact_id )
				->first();

			if ( ! $tracker ) {
				return fluent_abilities_error( 'not_found', 'Contact is not enrolled in this sequence' );
			}

			if ( $tracker->status === 'cancelled' ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Contact is already cancelled from this sequence' );
			}

			// Soft cancel via Sequence::unsubscribe().
			$sequence->unsubscribe( array( $contact_id ), 'Removed via Abilities API' );

			return array(
				'success'     => true,
				'sequence_id' => $sequence->id,
				'contact_id'  => $contact_id,
				'new_status'  => 'cancelled',
			);
		},
	));

	$reg->read( 'fluent-crm/get-sequence-subscribers', array(
		'label'       => 'Get Sequence Subscribers',
		'description' => 'List contacts enrolled in a sequence with their enrollment status: active, completed, or cancelled.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'sequence_id' ),
			'properties' => array_merge( array(
				'sequence_id' => array( 'type' => 'integer', 'description' => 'Sequence ID' ),
				'status'      => array( 'type' => 'string', 'description' => 'Filter by tracker status: active, completed, cancelled' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'subscribers', array(
			'subscriber_id' => array( 'type' => 'integer' ),
			'email'         => array( 'type' => 'string' ),
			'first_name'    => array( 'type' => 'string' ),
			'last_name'     => array( 'type' => 'string' ),
			'status'        => array( 'type' => 'string' ),
			'next_step'     => array( 'type' => 'integer' ),
			'scheduled_at'  => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$sequence_id = absint( $input['sequence_id'] );
			$sequence = \FluentCampaign\App\Models\Sequence::find( $sequence_id );
			if ( ! $sequence ) {
				return fluent_abilities_error( 'not_found', 'Sequence not found' );
			}

			$pagination = fluent_abilities_pagination( $input );
			global $wpdb;
			$tracker_table = $wpdb->prefix . 'fc_sequence_tracker';
			$sub_table     = $wpdb->prefix . 'fc_subscribers';

			$where = $wpdb->prepare( "WHERE t.campaign_id = %d", $sequence_id );
			if ( ! empty( $input['status'] ) ) {
				$status = sanitize_text_field( $input['status'] );
				$where .= $wpdb->prepare( " AND t.status = %s", $status );
			}

			$total = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$tracker_table} t {$where}"
			);

			$results = $wpdb->get_results(
				"SELECT t.id as tracker_id, t.subscriber_id, t.status, t.last_executed_time, t.next_execution_time,
				        s.email, s.first_name, s.last_name, s.status as contact_status
				FROM {$tracker_table} t
				LEFT JOIN {$sub_table} s ON t.subscriber_id = s.id
				{$where}
				ORDER BY t.id DESC
				LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}"
			);

			$items = array();
			foreach ( $results as $r ) {
				$items[] = array(
					'tracker_id'          => (int) $r->tracker_id,
					'subscriber_id'       => (int) $r->subscriber_id,
					'email'               => $r->email,
					'name'                => trim( $r->first_name . ' ' . $r->last_name ),
					'contact_status'      => $r->contact_status,
					'sequence_status'     => $r->status,
					'last_executed_time'  => $r->last_executed_time,
					'next_execution_time' => $r->next_execution_time,
				);
			}

			return array(
				'sequence_id' => $sequence_id,
				'title'       => $sequence->title,
				'subscribers' => $items,
				'total'       => $total,
				'page'        => $pagination['page'],
				'per_page'    => $pagination['per_page'],
			);
		},
	));

	// =========================================================================
	// AUTOMATIONS
	// =========================================================================

	$reg->read( 'fluent-crm/list-automations', array(
		'label'       => 'List CRM Automations',
		'description' => 'List automation funnels.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => fluent_abilities_pagination_schema(),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'automations', array(
			'id'           => array( 'type' => 'integer' ),
			'title'        => array( 'type' => 'string' ),
			'status'       => array( 'type' => 'string' ),
			'trigger_name' => array( 'type' => 'string' ),
			'created_at'   => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$query = \FluentCrm\App\Models\Funnel::orderBy( 'id', 'DESC' );
			$total = $query->count();
			$funnels = $query->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();

			$items = array();
			foreach ( $funnels as $funnel ) {
				$items[] = array(
					'id'           => (int) $funnel->id,
					'title'        => (string) ( $funnel->title ?? '' ),
					'status'       => (string) ( $funnel->status ?? '' ),
					'trigger_name' => (string) ( $funnel->trigger_name ?? '' ),
					'created_at'   => $funnel->created_at ? (string) $funnel->created_at : '',
				);
			}

			return array( 'automations' => $items, 'total' => $total, 'page' => $pagination['page'] );
		},
	));

	$reg->write( 'fluent-crm/update-automation-status', array(
		'label'       => 'Update Automation Status',
		'description' => 'Enable or disable an automation funnel. Status must be "draft" (disabled) or "published" (active).',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'funnel_id', 'status' ),
			'properties' => array(
				'funnel_id' => array( 'type' => 'integer', 'description' => 'Automation funnel ID' ),
				'status'    => array( 'type' => 'string', 'description' => 'New status: draft (disabled) or published (active)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'funnel_id' => array( 'type' => 'integer' ),
			'status'    => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$funnel = \FluentCrm\App\Models\Funnel::find( absint( $input['funnel_id'] ) );
			if ( ! $funnel ) {
				return fluent_abilities_error( 'not_found', 'Automation funnel not found' );
			}

			$new_status = sanitize_text_field( $input['status'] );
			if ( ! in_array( $new_status, array( 'draft', 'published' ), true ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Status must be "draft" or "published"' );
			}

			$old_status = $funnel->status;
			$funnel->status = $new_status;
			$funnel->save();

			return array(
				'success'    => true,
				'funnel_id'  => $funnel->id,
				'title'      => $funnel->title,
				'old_status' => $old_status,
				'new_status' => $new_status,
			);
		},
	));

	$reg->write( 'fluent-crm/duplicate-automation', array(
		'label'       => 'Duplicate Automation Funnel',
		'description' => 'Clone an automation funnel including all its sequence steps. The copy is always created in draft status with "[Copy] " prefix in title.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'funnel_id' ),
			'properties' => array(
				'funnel_id' => array( 'type' => 'integer', 'description' => 'Automation funnel ID to duplicate' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'original_id' => array( 'type' => 'integer' ),
			'new_id'      => array( 'type' => 'integer' ),
			'title'       => array( 'type' => 'string' ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			$old_funnel = \FluentCrm\App\Models\Funnel::find( absint( $input['funnel_id'] ) );
			if ( ! $old_funnel ) {
				return fluent_abilities_error( 'not_found', 'Automation funnel not found' );
			}

			// Create the new funnel as draft.
			$new_funnel = \FluentCrm\App\Models\Funnel::create( array(
				'title'        => '[Copy] ' . $old_funnel->title,
				'trigger_name' => $old_funnel->trigger_name,
				'status'       => 'draft',
				'conditions'   => fluent_abilities_safe_array( $old_funnel->conditions ),
				'settings'     => fluent_abilities_safe_array( $old_funnel->settings ),
				'created_by'   => get_current_user_id(),
			));

			// Clone all funnel sequence steps.
			$sequences = \FluentCrm\App\Models\FunnelSequence::where( 'funnel_id', $old_funnel->id )
				->orderBy( 'sequence', 'ASC' )
				->get();

			foreach ( $sequences as $sequence ) {
				$new_seq_data = $sequence->toArray();
				unset( $new_seq_data['id'], $new_seq_data['created_at'], $new_seq_data['updated_at'] );
				$new_seq_data['funnel_id'] = $new_funnel->id;
				$new_seq_data['status']    = 'published';
				\FluentCrm\App\Models\FunnelSequence::create( $new_seq_data );
			}

			// Reset sequence indexes.
			if ( class_exists( '\FluentCrm\App\Services\Funnel\FunnelHandler' ) ) {
				( new \FluentCrm\App\Services\Funnel\FunnelHandler() )->resetFunnelIndexes();
			}

			return array(
				'success'       => true,
				'new_funnel_id' => $new_funnel->id,
				'title'         => $new_funnel->title,
				'status'        => 'draft',
				'steps_copied'  => count( $sequences ),
			);
		},
	));

	// =========================================================================
	// CONTACT NOTES
	// =========================================================================

	$reg->read( 'fluent-crm/list-notes', array(
		'label'       => 'List Contact Notes',
		'description' => 'List notes for a specific contact.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'contact_id' ),
			'properties' => array(
				'contact_id' => array( 'type' => 'integer', 'description' => 'Contact ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'notes', array(
			'id'         => array( 'type' => 'integer' ),
			'contact_id' => array( 'type' => 'integer' ),
			'title'      => array( 'type' => 'string' ),
			'description'=> array( 'type' => 'string' ),
			'type'       => array( 'type' => 'string' ),
			'created_by' => array( 'type' => 'integer' ),
			'created_at' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$contact = FluentCrmApi( 'contacts' )->getContact( (int) $input['contact_id'] );
			if ( ! $contact ) {
				return fluent_abilities_error( 'not_found', 'Contact not found' );
			}

			$notes = $contact->notes()->orderBy( 'id', 'DESC' )->get();
			$items = array();
			foreach ( $notes as $note ) {
				$items[] = array(
					'id'         => $note->id,
					'title'      => $note->title,
					'description'=> $note->description,
					'type'       => $note->type,
					'created_at' => (string) $note->created_at,
				);
			}

			return array( 'notes' => $items, 'total' => count( $items ) );
		},
	));

	$reg->write( 'fluent-crm/create-note', array(
		'label'       => 'Create Contact Note',
		'description' => 'Add a note to a contact.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'contact_id', 'title' ),
			'properties' => array(
				'contact_id'  => array( 'type' => 'integer', 'description' => 'Contact ID' ),
				'title'       => array( 'type' => 'string', 'description' => 'Note title' ),
				'description' => array( 'type' => 'string', 'description' => 'Note body' ),
				'type'        => array( 'type' => 'string', 'description' => 'Note type (default: note)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'         => array( 'type' => 'integer' ),
			'contact_id' => array( 'type' => 'integer' ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			$contact = FluentCrmApi( 'contacts' )->getContact( (int) $input['contact_id'] );
			if ( ! $contact ) {
				return fluent_abilities_error( 'not_found', 'Contact not found' );
			}

			$note = $contact->notes()->create( array(
				'title'       => sanitize_text_field( $input['title'] ),
				'description' => wp_kses_post( $input['description'] ?? '' ),
				'type'        => sanitize_text_field( $input['type'] ?? 'note' ),
			));

			return array( 'success' => true, 'id' => $note->id );
		},
	));

	// =========================================================================
	// COMPANIES
	// =========================================================================

	$reg->read( 'fluent-crm/list-companies', array(
		'label'       => 'List CRM Companies',
		'description' => 'List companies in FluentCRM.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => fluent_abilities_pagination_schema(),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'companies', array(
			'id'          => array( 'type' => 'integer' ),
			'name'        => array( 'type' => 'string' ),
			'website'     => array( 'type' => 'string' ),
			'city'        => array( 'type' => 'string' ),
			'country'     => array( 'type' => 'string' ),
			'contact_count' => array( 'type' => 'integer' ),
			'created_at'  => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentCrm\App\Models\Company' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'Companies feature requires FluentCRM Pro' );
			}

			$pagination = fluent_abilities_pagination( $input );
			$query = \FluentCrm\App\Models\Company::orderBy( 'id', 'DESC' );
			$total = $query->count();
			$companies = $query->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();

			$items = array();
			foreach ( $companies as $company ) {
				$items[] = array(
					'id'         => $company->id,
					'name'       => $company->name,
					'email'      => $company->email ?? null,
					'industry'   => $company->industry ?? null,
					'created_at' => (string) $company->created_at,
				);
			}

			return array( 'companies' => $items, 'total' => $total, 'page' => $pagination['page'] );
		},
	));

	// =========================================================================
	// EMAIL TEMPLATES
	// =========================================================================

	$reg->read( 'fluent-crm/list-templates', array(
		'label'       => 'List Email Templates',
		'description' => 'List email templates available in FluentCRM.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => fluent_abilities_pagination_schema(),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'templates', array(
			'id'         => array( 'type' => 'integer' ),
			'title'      => array( 'type' => 'string' ),
			'subject'    => array( 'type' => 'string' ),
			'created_at' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$query = \FluentCrm\App\Models\Template::orderBy( 'id', 'DESC' );
			$total = $query->count();
			$templates = $query->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();

			$items = array();
			foreach ( $templates as $template ) {
				$items[] = array(
					'id'         => (int) $template->id,
					'title'      => (string) ( $template->post_title ?? $template->title ?? '' ),
					'subject'    => (string) ( $template->post_excerpt ?? $template->subject ?? '' ),
					'created_at' => $template->created_at ? (string) $template->created_at : '',
				);
			}

			return array( 'templates' => $items, 'total' => $total, 'page' => $pagination['page'] );
		},
	));

	// =========================================================================
	// CRM FORMS
	// =========================================================================

	$reg->read( 'fluent-crm/list-forms', array(
		'label'       => 'List CRM Forms',
		'description' => 'List opt-in forms created in FluentCRM.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => fluent_abilities_pagination_schema(),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'forms', array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
			'type'  => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );

			global $wpdb;
			$table = $wpdb->prefix . 'fc_meta';
			$total = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE object_type = %s",
				'FluentForm'
			));

			$forms = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$table} WHERE object_type = %s ORDER BY id DESC LIMIT %d OFFSET %d",
				'FluentForm',
				$pagination['per_page'],
				$pagination['offset']
			));

			$items = array();
			foreach ( $forms as $form ) {
				$value = maybe_unserialize( $form->value );
				$items[] = array(
					'id'    => $form->id,
					'title' => is_array( $value ) ? ( $value['title'] ?? '' ) : '',
					'key'   => $form->key,
				);
			}

			return array( 'forms' => $items, 'total' => $total, 'page' => $pagination['page'] );
		},
	));

	// =========================================================================
	// DASHBOARD STATS
	// =========================================================================

	$reg->read( 'fluent-crm/dashboard-stats', array(
		'label'       => 'CRM Dashboard Stats',
		'description' => 'Get CRM overview: total contacts by status, tag count, list count, campaign count.',
		'category'    => 'fluent-crm',
		'output_schema' => fluent_abilities_schema_item_output( array(
			'contacts'   => array( 'type' => 'object' ),
			'campaigns'  => array( 'type' => 'object' ),
			'tags'       => array( 'type' => 'object' ),
			'lists'      => array( 'type' => 'object' ),
		) ),
		'callback' => function( $input ) {
			$subscriber_model = \FluentCrm\App\Models\Subscriber::class;

			return array(
				'contacts' => array(
					'total'        => $subscriber_model::count(),
					'subscribed'   => $subscriber_model::where( 'status', 'subscribed' )->count(),
					'pending'      => $subscriber_model::where( 'status', 'pending' )->count(),
					'unsubscribed' => $subscriber_model::where( 'status', 'unsubscribed' )->count(),
				),
				'campaigns'  => array( 'total' => \FluentCrm\App\Models\Campaign::count() ),
				'tags'       => array( 'total' => \FluentCrm\App\Models\Tag::count() ),
				'lists'      => array( 'total' => \FluentCrm\App\Models\Lists::count() ),
			);
		},
	));

	// =========================================================================
	// SMART LINK STATS
	// =========================================================================

	$reg->read( 'fluent-crm/get-smart-link-stats', array(
		'label'       => 'Get Smart Link Stats',
		'description' => 'Read click statistics for a smart link: all_clicks (total), contact_clicks (known contacts), plus link details.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Smart link ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'link_id'     => array( 'type' => 'integer' ),
			'total_clicks'=> array( 'type' => 'integer' ),
			'unique_users'=> array( 'type' => 'integer' ),
			'by_date'     => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $input ) {
			$link = \FluentCampaign\App\Models\SmartLink::find( intval( $input['id'] ) );
			if ( ! $link ) {
				return fluent_abilities_error( 'not_found', 'Smart link not found' );
			}

			return array(
				'id'              => $link->id,
				'title'           => $link->title,
				'slug'            => $link->slug,
				'short'           => $link->short,
				'target_url'      => $link->target_url,
				'all_clicks'      => (int) $link->all_clicks,
				'contact_clicks'  => (int) $link->contact_clicks,
				'actions'         => fluent_abilities_safe_array( $link->actions ),
				'notes'           => fluent_abilities_safe_array( $link->notes ),
				'created_at'      => (string) $link->created_at,
			);
		},
	));

	// =========================================================================
	// SMART LINK MANAGEMENT
	// =========================================================================

	$reg->read( 'fluent-crm/list-smart-links', array(
		'label'       => 'List Smart Links',
		'description' => 'List all smart links with click counts.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => fluent_abilities_pagination_schema(),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'smart_links', array(
			'id'          => array( 'type' => 'integer' ),
			'title'       => array( 'type' => 'string' ),
			'short_url'   => array( 'type' => 'string' ),
			'target_url'  => array( 'type' => 'string' ),
			'click_counter' => array( 'type' => 'integer' ),
			'created_at'  => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$query = \FluentCampaign\App\Models\SmartLink::orderBy( 'id', 'DESC' );
			$total = $query->count();
			$links = $query->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();

			$items = array();
			foreach ( $links as $link ) {
				$items[] = array(
					'id'             => $link->id,
					'title'          => $link->title,
					'short'          => $link->short,
					'short_url'      => $link->short_url,
					'target_url'     => $link->target_url,
					'contact_clicks' => (int) $link->contact_clicks,
					'all_clicks'     => (int) $link->all_clicks,
					'created_at'     => (string) $link->created_at,
				);
			}

			return array( 'smart_links' => $items, 'total' => $total, 'page' => $pagination['page'], 'per_page' => $pagination['per_page'] );
		},
	));

	$reg->read( 'fluent-crm/get-smart-link', array(
		'label'       => 'Get Smart Link',
		'description' => 'Get a single smart link by ID, including actions, notes, and click stats.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'smart_link_id' ),
			'properties' => array(
				'smart_link_id' => array( 'type' => 'integer', 'description' => 'Smart link ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'             => array( 'type' => 'integer' ),
			'title'          => array( 'type' => array( 'string', 'null' ) ),
			'short'          => array( 'type' => array( 'string', 'null' ) ),
			'short_url'      => array( 'type' => array( 'string', 'null' ) ),
			'target_url'     => array( 'type' => array( 'string', 'null' ) ),
			'actions'        => array( 'type' => array( 'object', 'null' ) ),
			'notes'          => array( 'type' => array( 'object', 'array', 'null' ) ),
			'contact_clicks' => array( 'type' => 'integer' ),
			'all_clicks'     => array( 'type' => 'integer' ),
			'created_by'     => array( 'type' => 'integer' ),
			'created_at'     => array( 'type' => array( 'string', 'null' ) ),
			'updated_at'     => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$link = \FluentCampaign\App\Models\SmartLink::find( absint( $input['smart_link_id'] ) );
			if ( ! $link ) {
				return fluent_abilities_error( 'not_found', 'Smart link not found' );
			}

			return array(
				'id'             => $link->id,
				'title'          => $link->title,
				'short'          => $link->short,
				'short_url'      => $link->short_url,
				'target_url'     => $link->target_url,
				'actions'        => fluent_abilities_safe_array( $link->actions ),
				'notes'          => fluent_abilities_safe_array( $link->notes ),
				'contact_clicks' => (int) $link->contact_clicks,
				'all_clicks'     => (int) $link->all_clicks,
				'created_by'     => (int) $link->created_by,
				'created_at'     => (string) $link->created_at,
				'updated_at'     => (string) $link->updated_at,
			);
		},
	));

	$reg->write( 'fluent-crm/create-smart-link', array(
		'label'       => 'Create Smart Link',
		'description' => 'Create a new smart link. The short URL slug is auto-generated. Actions can include remove_tags (array of tag IDs), remove_lists (array of list IDs), and auto_login (yes/no).',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'title' ),
			'properties' => array(
				'title'      => array( 'type' => 'string', 'description' => 'Smart link title/name' ),
				'target_url' => array( 'type' => 'string', 'description' => 'Redirect URL after click (optional)' ),
				'notes'      => array( 'type' => 'string', 'description' => 'Internal notes about this smart link' ),
				'actions'    => array( 'type' => 'object', 'description' => 'Actions on click: { remove_tags: [ids], remove_lists: [ids], auto_login: "yes"|"no" }' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'        => array( 'type' => 'integer' ),
			'title'     => array( 'type' => 'string' ),
			'short_url' => array( 'type' => 'string' ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			$data = array(
				'title' => sanitize_text_field( $input['title'] ),
			);

			if ( isset( $input['target_url'] ) ) {
				$data['target_url'] = esc_url_raw( $input['target_url'] );
			}
			if ( isset( $input['notes'] ) ) {
				$data['notes'] = sanitize_text_field( $input['notes'] );
			}
			if ( isset( $input['actions'] ) && is_array( $input['actions'] ) ) {
				$actions = array();
				if ( isset( $input['actions']['remove_tags'] ) ) {
					$actions['remove_tags'] = array_map( 'absint', (array) $input['actions']['remove_tags'] );
				}
				if ( isset( $input['actions']['remove_lists'] ) ) {
					$actions['remove_lists'] = array_map( 'absint', (array) $input['actions']['remove_lists'] );
				}
				if ( isset( $input['actions']['auto_login'] ) ) {
					$actions['auto_login'] = $input['actions']['auto_login'] === 'yes' ? 'yes' : 'no';
				}
				$data['actions'] = $actions;
			}

			$link = \FluentCampaign\App\Models\SmartLink::create( $data );

			return array(
				'success'   => true,
				'id'        => $link->id,
				'title'     => $link->title,
				'short'     => $link->short,
				'short_url' => $link->short_url,
			);
		},
	));

	$reg->write( 'fluent-crm/update-smart-link', array(
		'label'       => 'Update Smart Link',
		'description' => 'Update a smart link title, target URL, notes, or actions. The short URL slug cannot be changed.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'smart_link_id' ),
			'properties' => array(
				'smart_link_id' => array( 'type' => 'integer', 'description' => 'Smart link ID' ),
				'title'         => array( 'type' => 'string', 'description' => 'New title' ),
				'target_url'    => array( 'type' => 'string', 'description' => 'New redirect URL' ),
				'notes'         => array( 'type' => 'string', 'description' => 'Internal notes' ),
				'actions'       => array( 'type' => 'object', 'description' => 'Actions on click (replaces existing actions)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'      => array( 'type' => 'integer' ),
			'updated' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
		) ),
		'callback' => function( $input ) {
			$link = \FluentCampaign\App\Models\SmartLink::find( absint( $input['smart_link_id'] ) );
			if ( ! $link ) {
				return fluent_abilities_error( 'not_found', 'Smart link not found' );
			}

			$updated = array();
			if ( isset( $input['title'] ) ) {
				$link->title = sanitize_text_field( $input['title'] );
				$updated[] = 'title';
			}
			if ( isset( $input['target_url'] ) ) {
				$link->target_url = esc_url_raw( $input['target_url'] );
				$updated[] = 'target_url';
			}
			if ( isset( $input['notes'] ) ) {
				$link->notes = sanitize_text_field( $input['notes'] );
				$updated[] = 'notes';
			}
			if ( isset( $input['actions'] ) && is_array( $input['actions'] ) ) {
				$actions = array();
				if ( isset( $input['actions']['remove_tags'] ) ) {
					$actions['remove_tags'] = array_map( 'absint', (array) $input['actions']['remove_tags'] );
				}
				if ( isset( $input['actions']['remove_lists'] ) ) {
					$actions['remove_lists'] = array_map( 'absint', (array) $input['actions']['remove_lists'] );
				}
				if ( isset( $input['actions']['auto_login'] ) ) {
					$actions['auto_login'] = $input['actions']['auto_login'] === 'yes' ? 'yes' : 'no';
				}
				$link->actions = $actions;
				$updated[] = 'actions';
			}

			if ( empty( $updated ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'No fields provided to update' );
			}

			$link->save();

			return array(
				'success'       => true,
				'smart_link_id' => $link->id,
				'updated'       => $updated,
			);
		},
	));

	$reg->read( 'fluent-crm/generate-smart-link-shortcode', array(
		'label'       => 'Generate Smart Link URL',
		'description' => 'Get the clickable short URL for a smart link in multiple formats: raw URL, HTML link, and the short slug. Useful for embedding in emails or pages.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'smart_link_id' ),
			'properties' => array(
				'smart_link_id' => array( 'type' => 'integer', 'description' => 'Smart link ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'smart_link_id' => array( 'type' => 'integer' ),
			'title'         => array( 'type' => array( 'string', 'null' ) ),
			'short_url'     => array( 'type' => array( 'string', 'null' ) ),
			'html_link'     => array( 'type' => 'string' ),
			'short_slug'    => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$link = \FluentCampaign\App\Models\SmartLink::find( absint( $input['smart_link_id'] ) );
			if ( ! $link ) {
				return fluent_abilities_error( 'not_found', 'Smart link not found' );
			}

			$short_url = $link->short_url;

			return array(
				'smart_link_id' => $link->id,
				'title'         => $link->title,
				'short_url'     => $short_url,
				'html_link'     => '<a href="' . esc_url( $short_url ) . '">' . esc_html( $link->title ) . '</a>',
				'short_slug'    => $link->short,
			);
		},
	));

	// =========================================================================
	// EVENT TRACKING
	// =========================================================================

	$reg->write( 'fluent-crm/track-event', array(
		'label'       => 'Track Custom Event',
		'description' => 'Track a custom event for a contact. Uses FluentCRM Event Tracking API. Same event_key+title increments counter (no duplicates). Requires event tracking to be enabled in FluentCRM experimental features.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'event_key', 'title' ),
			'properties' => array(
				'event_key'     => array( 'type' => 'string', 'description' => 'Unique event identifier (e.g. priestess_product_view)' ),
				'title'         => array( 'type' => 'string', 'description' => 'Human-readable event name' ),
				'value'         => array( 'type' => 'string', 'description' => 'Event data/description' ),
				'email'         => array( 'type' => 'string', 'description' => 'Contact email (provide email OR subscriber_id)' ),
				'subscriber_id' => array( 'type' => 'integer', 'description' => 'Contact ID (alternative to email)' ),
				'provider'      => array( 'type' => 'string', 'description' => 'Source system (default: custom)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'event_key'  => array( 'type' => 'string' ),
			'contact_id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			if ( ! \FluentCrm\App\Services\Helper::isExperimentalEnabled( 'event_tracking' ) ) {
				return fluent_abilities_error( 'not_found', 'Event tracking is not enabled. Enable it in FluentCRM → Settings → Experimental Features.' );
			}

			$params = array(
				'event_key' => sanitize_text_field( $input['event_key'] ),
				'title'     => sanitize_text_field( $input['title'] ),
			);

			if ( isset( $input['value'] ) ) {
				$params['value'] = sanitize_text_field( $input['value'] );
			}
			if ( isset( $input['email'] ) ) {
				$params['email'] = sanitize_email( $input['email'] );
			}
			if ( isset( $input['subscriber_id'] ) ) {
				$params['subscriber_id'] = intval( $input['subscriber_id'] );
			}
			if ( isset( $input['provider'] ) ) {
				$params['provider'] = sanitize_text_field( $input['provider'] );
			}

			$result = FluentCrmApi( 'event_tracker' )->track( $params, true );

			return array(
				'success' => (bool) $result,
				'event_key' => $params['event_key'],
				'title'     => $params['title'],
			);
		},
	));

	$reg->read( 'fluent-crm/list-contact-events', array(
		'label'       => 'List Contact Events',
		'description' => 'Get all tracked events for a specific contact. Returns event_key, title, value, counter (how many times it occurred), provider, and timestamps.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'contact_id' ),
			'properties' => array(
				'contact_id' => array( 'type' => 'integer', 'description' => 'Contact/subscriber ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'events', array(
			'id'         => array( 'type' => 'integer' ),
			'event_type' => array( 'type' => 'string' ),
			'object_id'  => array( 'type' => 'integer' ),
			'notes'      => array( 'type' => 'string' ),
			'created_at' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! \FluentCrm\App\Services\Helper::isExperimentalEnabled( 'event_tracking' ) ) {
				return fluent_abilities_error( 'not_found', 'Event tracking is not enabled.' );
			}

			global $wpdb;
			$table = $wpdb->prefix . 'fc_event_tracking';
			$events = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, event_key, title, value, counter, provider, created_by, created_at, updated_at FROM {$table} WHERE subscriber_id = %d ORDER BY updated_at DESC",
				intval( $input['contact_id'] )
			) );

			$items = array();
			foreach ( $events as $e ) {
				$items[] = array(
					'id'         => (int) $e->id,
					'event_key'  => $e->event_key,
					'title'      => $e->title,
					'value'      => $e->value,
					'counter'    => (int) $e->counter,
					'provider'   => $e->provider,
					'created_at' => (string) $e->created_at,
					'updated_at' => (string) $e->updated_at,
				);
			}

			return array( 'events' => $items, 'total' => count( $items ) );
		},
	));

	$reg->read( 'fluent-crm/list-event-keys', array(
		'label'       => 'List Event Keys',
		'description' => 'List all distinct event keys tracked in the system. Useful for understanding what events exist before querying or filtering.',
		'category'    => 'fluent-crm',
		'output_schema' => fluent_abilities_schema_collection_output( 'event_keys', array(
			'event_key'   => array( 'type' => 'string' ),
			'description' => array( 'type' => 'string' ),
			'count'       => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			if ( ! \FluentCrm\App\Services\Helper::isExperimentalEnabled( 'event_tracking' ) ) {
				return fluent_abilities_error( 'not_found', 'Event tracking is not enabled.' );
			}

			global $wpdb;
			$table = $wpdb->prefix . 'fc_event_tracking';
			$keys = $wpdb->get_results(
				"SELECT DISTINCT event_key, title, provider, COUNT(*) as contact_count, SUM(counter) as total_occurrences FROM {$table} GROUP BY event_key, title, provider ORDER BY total_occurrences DESC"
			);

			$items = array();
			foreach ( $keys as $k ) {
				$items[] = array(
					'event_key'         => $k->event_key,
					'title'             => $k->title,
					'provider'          => $k->provider,
					'contact_count'     => (int) $k->contact_count,
					'total_occurrences' => (int) $k->total_occurrences,
				);
			}

			return array( 'event_keys' => $items, 'total' => count( $items ) );
		},
	));

	// =========================================================================
	// EMAIL ENGAGEMENT ANALYTICS
	// =========================================================================

	$reg->read( 'fluent-crm/get-campaign-stats', array(
		'label'       => 'Get Campaign Email Stats',
		'description' => 'Get aggregated email stats for a campaign or sequence email: total sent, opened, clicked, bounced, failed, plus rates.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'campaign_id' ),
			'properties' => array(
				'campaign_id' => array( 'type' => 'integer', 'description' => 'Campaign or sequence email ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'campaign_id'        => array( 'type' => 'integer' ),
			'title'              => array( 'type' => 'string' ),
			'status'             => array( 'type' => 'string' ),
			'emails_sent'        => array( 'type' => 'integer' ),
			'emails_opened'      => array( 'type' => 'integer' ),
			'emails_clicked'     => array( 'type' => 'integer' ),
			'unsubscribed'       => array( 'type' => 'integer' ),
			'open_rate'          => array( 'type' => 'number' ),
			'click_rate'         => array( 'type' => 'number' ),
		) ),
		'callback' => function( $input ) {
			$campaign_id = intval( $input['campaign_id'] );

			global $wpdb;
			$table = $wpdb->prefix . 'fc_campaign_emails';

			$stats = $wpdb->get_row( $wpdb->prepare(
				"SELECT
					COUNT(*) as total,
					SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
					SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
					SUM(CASE WHEN is_open = 1 THEN 1 ELSE 0 END) as opened,
					SUM(CASE WHEN click_counter > 0 THEN 1 ELSE 0 END) as clicked,
					SUM(click_counter) as total_clicks,
					SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as bounced,
					SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
					SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled
				FROM {$table}
				WHERE campaign_id = %d",
				$campaign_id
			) );

			if ( ! $stats || (int) $stats->total === 0 ) {
				return fluent_abilities_error( 'not_found', 'No email sends found for this campaign' );
			}

			$sent = (int) $stats->sent;

			return array(
				'campaign_id'  => $campaign_id,
				'total'        => (int) $stats->total,
				'sent'         => $sent,
				'pending'      => (int) $stats->pending,
				'scheduled'    => (int) $stats->scheduled,
				'opened'       => (int) $stats->opened,
				'clicked'      => (int) $stats->clicked,
				'total_clicks' => (int) $stats->total_clicks,
				'bounced'      => (int) $stats->bounced,
				'failed'       => (int) $stats->failed,
				'open_rate'    => $sent > 0 ? round( (int) $stats->opened / $sent * 100, 1 ) : 0,
				'click_rate'   => $sent > 0 ? round( (int) $stats->clicked / $sent * 100, 1 ) : 0,
				'bounce_rate'  => $sent > 0 ? round( (int) $stats->bounced / $sent * 100, 1 ) : 0,
			);
		},
	));

	$reg->read( 'fluent-crm/get-sequence-stats', array(
		'label'       => 'Get Sequence Email Stats',
		'description' => 'Get per-email engagement stats for an entire sequence: sent, opened, clicked, bounced per email.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'sequence_id' ),
			'properties' => array(
				'sequence_id' => array( 'type' => 'integer', 'description' => 'Sequence ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'sequence_id'    => array( 'type' => 'integer' ),
			'total_active'   => array( 'type' => 'integer' ),
			'total_complete' => array( 'type' => 'integer' ),
			'total_failed'   => array( 'type' => 'integer' ),
			'emails_sent'    => array( 'type' => 'integer' ),
			'by_email'       => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $input ) {
			$sequence_id = intval( $input['sequence_id'] );
			$sequence = \FluentCampaign\App\Models\Sequence::find( $sequence_id );
			if ( ! $sequence ) {
				return fluent_abilities_error( 'not_found', 'Sequence not found' );
			}

			$emails = \FluentCampaign\App\Models\SequenceMail::where( 'parent_id', $sequence_id )
				->orderBy( 'delay', 'ASC' )->get();

			global $wpdb;
			$table = $wpdb->prefix . 'fc_campaign_emails';

			$email_stats = array();
			foreach ( $emails as $email ) {
				$stats = $wpdb->get_row( $wpdb->prepare(
					"SELECT
						COUNT(*) as total,
						SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
						SUM(CASE WHEN is_open = 1 THEN 1 ELSE 0 END) as opened,
						SUM(CASE WHEN click_counter > 0 THEN 1 ELSE 0 END) as clicked,
						SUM(click_counter) as total_clicks,
						SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as bounced
					FROM {$table}
					WHERE campaign_id = %d",
					$email->id
				) );

				$sent = $stats ? (int) $stats->sent : 0;

				$email_stats[] = array(
					'email_id'      => $email->id,
					'email_subject' => $email->email_subject,
					'delay_days'    => round( $email->delay / 86400, 1 ),
					'status'        => $email->status,
					'sent'          => $sent,
					'opened'        => $stats ? (int) $stats->opened : 0,
					'clicked'       => $stats ? (int) $stats->clicked : 0,
					'total_clicks'  => $stats ? (int) $stats->total_clicks : 0,
					'bounced'       => $stats ? (int) $stats->bounced : 0,
					'open_rate'     => $sent > 0 ? round( (int) $stats->opened / $sent * 100, 1 ) : 0,
					'click_rate'    => $sent > 0 ? round( (int) $stats->clicked / $sent * 100, 1 ) : 0,
				);
			}

			return array(
				'sequence_id'    => $sequence_id,
				'sequence_title' => $sequence->title,
				'emails'         => $email_stats,
				'total_emails'   => count( $email_stats ),
			);
		},
	));

	$reg->read( 'fluent-crm/get-contact-emails', array(
		'label'       => 'Get Contact Email History',
		'description' => 'Get all emails sent to a contact with delivery status, open/click data, and timestamps.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'contact_id' ),
			'properties' => array_merge( array(
				'contact_id' => array( 'type' => 'integer', 'description' => 'Contact/subscriber ID' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'emails', array(
			'id'          => array( 'type' => 'integer' ),
			'subject'     => array( 'type' => 'string' ),
			'status'      => array( 'type' => 'string' ),
			'is_open'     => array( 'type' => 'boolean' ),
			'click_counter' => array( 'type' => 'integer' ),
			'created_at'  => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$contact_id = intval( $input['contact_id'] );

			global $wpdb;
			$table = $wpdb->prefix . 'fc_campaign_emails';

			$total = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE subscriber_id = %d",
				$contact_id
			) );

			$emails = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, campaign_id, email_type, email_subject_id, email_subject, status, is_open, click_counter, scheduled_at, email_address, created_at
				FROM {$table}
				WHERE subscriber_id = %d
				ORDER BY created_at DESC
				LIMIT %d OFFSET %d",
				$contact_id,
				$pagination['per_page'],
				$pagination['offset']
			) );

			$items = array();
			foreach ( $emails as $e ) {
				$items[] = array(
					'id'            => (int) $e->id,
					'campaign_id'   => (int) $e->campaign_id,
					'email_type'    => $e->email_type,
					'email_subject' => $e->email_subject,
					'status'        => $e->status,
					'is_open'       => (bool) $e->is_open,
					'click_counter'   => (int) $e->click_counter,
					'scheduled_at'  => $e->scheduled_at,
					'created_at'    => (string) $e->created_at,
				);
			}

			return array(
				'contact_id' => $contact_id,
				'emails'     => $items,
				'total'      => $total,
				'page'       => $pagination['page'],
				'per_page'   => $pagination['per_page'],
			);
		},
	));

	$reg->read( 'fluent-crm/get-email-link-clicks', array(
		'label'       => 'Get Email Link Clicks',
		'description' => 'Get URL click details for a campaign/sequence email: which links were clicked, how many times, by how many unique contacts.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'campaign_id' ),
			'properties' => array(
				'campaign_id' => array( 'type' => 'integer', 'description' => 'Campaign or sequence email ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'email_id'    => array( 'type' => 'integer' ),
			'total_links' => array( 'type' => 'integer' ),
			'links'       => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $input ) {
			$campaign_id = intval( $input['campaign_id'] );

			global $wpdb;
			$metrics_table = $wpdb->prefix . 'fc_campaign_url_metrics';
			$urls_table    = $wpdb->prefix . 'fc_url_stores';

			$links = $wpdb->get_results( $wpdb->prepare(
				"SELECT
					u.id as url_id,
					u.url,
					COUNT(m.id) as total_clicks,
					COUNT(DISTINCT m.subscriber_id) as unique_contacts,
					MIN(m.created_at) as first_click,
					MAX(m.created_at) as last_click
				FROM {$metrics_table} m
				JOIN {$urls_table} u ON m.url_id = u.id
				WHERE m.campaign_id = %d AND m.type = 'click'
				GROUP BY u.id, u.url
				ORDER BY total_clicks DESC",
				$campaign_id
			) );

			$items = array();
			foreach ( $links as $link ) {
				$items[] = array(
					'url_id'          => (int) $link->url_id,
					'url'             => $link->url,
					'total_clicks'    => (int) $link->total_clicks,
					'unique_contacts' => (int) $link->unique_contacts,
					'first_click'     => $link->first_click,
					'last_click'      => $link->last_click,
				);
			}

			return array(
				'campaign_id' => $campaign_id,
				'links'       => $items,
				'total_links' => count( $items ),
			);
		},
	));

	// =========================================================================
	// JOURNEY & FUNNEL ANALYTICS
	// =========================================================================

	$reg->read( 'fluent-crm/get-contact-journey', array(
		'label'       => 'Get Contact Journey',
		'description' => 'Get a unified timeline of a contact\'s journey: tag applications, list assignments, email sends/opens/clicks, automation steps, custom events, and notes. Sorted chronologically.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'contact_id' ),
			'properties' => array(
				'contact_id' => array( 'type' => 'integer', 'description' => 'Contact/subscriber ID' ),
				'limit'      => array( 'type' => 'integer', 'description' => 'Max events to return (default: 100)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'contact_id'    => array( 'type' => 'integer' ),
			'email'         => array( 'type' => 'string' ),
			'journey'       => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'total_entries' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$contact_id = intval( $input['contact_id'] );
			$limit      = isset( $input['limit'] ) ? min( intval( $input['limit'] ), 500 ) : 100;

			global $wpdb;
			$events = array();

			// 1. Tag/list applications from pivot table
			$pivot_table = $wpdb->prefix . 'fc_subscriber_pivot';
			$pivots = $wpdb->get_results( $wpdb->prepare(
				"SELECT p.object_type, p.object_id, p.created_at,
					CASE
						WHEN p.object_type LIKE '%%Tag' THEN t.title
						WHEN p.object_type LIKE '%%Lists' THEN l.title
						ELSE NULL
					END as object_title
				FROM {$pivot_table} p
				LEFT JOIN {$wpdb->prefix}fc_tags t ON p.object_type LIKE '%%Tag' AND p.object_id = t.id
				LEFT JOIN {$wpdb->prefix}fc_lists l ON p.object_type LIKE '%%Lists' AND p.object_id = l.id
				WHERE p.subscriber_id = %d
				ORDER BY p.created_at DESC",
				$contact_id
			) );

			foreach ( $pivots as $p ) {
				$type = strpos( $p->object_type, 'Tag' ) !== false ? 'tag_applied' : 'list_added';
				$events[] = array(
					'type'       => $type,
					'title'      => $p->object_title,
					'object_id'  => (int) $p->object_id,
					'created_at' => (string) $p->created_at,
				);
			}

			// 2. Email sends/opens/clicks
			$emails_table = $wpdb->prefix . 'fc_campaign_emails';
			$emails = $wpdb->get_results( $wpdb->prepare(
				"SELECT campaign_id, email_type, email_subject, status, is_open, click_counter, scheduled_at, created_at
				FROM {$emails_table}
				WHERE subscriber_id = %d
				ORDER BY created_at DESC",
				$contact_id
			) );

			foreach ( $emails as $e ) {
				$events[] = array(
					'type'         => 'email_' . $e->status,
					'title'        => $e->email_subject,
					'campaign_id'  => (int) $e->campaign_id,
					'email_type'   => $e->email_type,
					'is_open'      => (bool) $e->is_open,
					'click_counter'  => (int) $e->click_counter,
					'created_at'   => (string) $e->created_at,
				);
			}

			// 3. Automation/funnel steps
			$funnel_table = $wpdb->prefix . 'fc_funnel_metrics';
			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$funnel_table}'" ) ) {
				$funnel_steps = $wpdb->get_results( $wpdb->prepare(
					"SELECT fm.funnel_id, fm.action_id, fm.status, fm.created_at, f.title as funnel_title
					FROM {$funnel_table} fm
					LEFT JOIN {$wpdb->prefix}fc_funnels f ON fm.funnel_id = f.id
					WHERE fm.subscriber_id = %d
					ORDER BY fm.created_at DESC",
					$contact_id
				) );

				foreach ( $funnel_steps as $fs ) {
					$events[] = array(
						'type'        => 'automation_' . $fs->status,
						'title'       => $fs->funnel_title,
						'funnel_id'   => (int) $fs->funnel_id,
						'action_id'   => (int) $fs->action_id,
						'created_at'  => (string) $fs->created_at,
					);
				}
			}

			// 4. Custom events
			$event_table = $wpdb->prefix . 'fc_event_tracking';
			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$event_table}'" ) ) {
				$custom_events = $wpdb->get_results( $wpdb->prepare(
					"SELECT event_key, title, value, counter, provider, created_at
					FROM {$event_table}
					WHERE subscriber_id = %d
					ORDER BY created_at DESC",
					$contact_id
				) );

				foreach ( $custom_events as $ce ) {
					$events[] = array(
						'type'       => 'custom_event',
						'title'      => $ce->title,
						'event_key'  => $ce->event_key,
						'value'      => $ce->value,
						'counter'    => (int) $ce->counter,
						'provider'   => $ce->provider,
						'created_at' => (string) $ce->created_at,
					);
				}
			}

			// 5. Notes
			$notes_table = $wpdb->prefix . 'fc_subscriber_notes';
			$notes = $wpdb->get_results( $wpdb->prepare(
				"SELECT title, description, type, created_at
				FROM {$notes_table}
				WHERE subscriber_id = %d
				ORDER BY created_at DESC",
				$contact_id
			) );

			foreach ( $notes as $n ) {
				$events[] = array(
					'type'        => 'note_' . $n->type,
					'title'       => $n->title,
					'description' => $n->description,
					'created_at'  => (string) $n->created_at,
				);
			}

			// Sort all events by created_at descending
			usort( $events, function( $a, $b ) {
				return strcmp( $b['created_at'] ?? '', $a['created_at'] ?? '' );
			});

			$events = array_slice( $events, 0, $limit );

			return array(
				'contact_id'   => $contact_id,
				'events'       => $events,
				'total_events' => count( $events ),
			);
		},
	));

	$reg->read( 'fluent-crm/get-automation-metrics', array(
		'label'       => 'Get Automation Metrics',
		'description' => 'Get funnel/automation conversion metrics: per-step subscriber counts (completed, waiting, skipped) and conversion rates.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'funnel_id' ),
			'properties' => array(
				'funnel_id' => array( 'type' => 'integer', 'description' => 'Automation/funnel ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'funnel_id'     => array( 'type' => 'integer' ),
			'total_entries' => array( 'type' => 'integer' ),
			'completed'     => array( 'type' => 'integer' ),
			'active'        => array( 'type' => 'integer' ),
			'cancelled'     => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$funnel_id = intval( $input['funnel_id'] );
			$funnel = \FluentCrm\App\Models\Funnel::find( $funnel_id );
			if ( ! $funnel ) {
				return fluent_abilities_error( 'not_found', 'Automation not found' );
			}

			global $wpdb;

			// Total subscribers in funnel
			$subs_table = $wpdb->prefix . 'fc_funnel_subscribers';
			$total_subs = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$subs_table} WHERE funnel_id = %d",
				$funnel_id
			) );

			$subs_by_status = $wpdb->get_results( $wpdb->prepare(
				"SELECT status, COUNT(*) as count FROM {$subs_table} WHERE funnel_id = %d GROUP BY status",
				$funnel_id
			) );

			$subscriber_stats = array();
			foreach ( $subs_by_status as $s ) {
				$subscriber_stats[ $s->status ] = (int) $s->count;
			}

			// Per-step metrics
			$metrics_table = $wpdb->prefix . 'fc_funnel_metrics';
			$steps_table   = $wpdb->prefix . 'fc_funnel_sequences';

			$steps = $wpdb->get_results( $wpdb->prepare(
				"SELECT fs.id, fs.action_name, fs.title, fs.sequence as step_order,
					COUNT(fm.id) as total,
					SUM(CASE WHEN fm.status = 'completed' THEN 1 ELSE 0 END) as completed,
					SUM(CASE WHEN fm.status = 'pending' THEN 1 ELSE 0 END) as pending,
					SUM(CASE WHEN fm.status = 'skipped' THEN 1 ELSE 0 END) as skipped
				FROM {$steps_table} fs
				LEFT JOIN {$metrics_table} fm ON fs.id = fm.sequence_id AND fm.funnel_id = %d
				WHERE fs.funnel_id = %d
				GROUP BY fs.id, fs.action_name, fs.title, fs.sequence
				ORDER BY fs.sequence ASC",
				$funnel_id,
				$funnel_id
			) );

			$step_metrics = array();
			foreach ( $steps as $step ) {
				$step_metrics[] = array(
					'step_id'      => (int) $step->id,
					'action_name'  => $step->action_name,
					'title'        => $step->title,
					'step_order'   => (int) $step->step_order,
					'total'        => (int) $step->total,
					'completed'    => (int) $step->completed,
					'pending'      => (int) $step->pending,
					'skipped'      => (int) $step->skipped,
					'completion_rate' => (int) $step->total > 0 ? round( (int) $step->completed / (int) $step->total * 100, 1 ) : 0,
				);
			}

			return array(
				'funnel_id'        => $funnel_id,
				'funnel_title'     => $funnel->title,
				'funnel_status'    => $funnel->status,
				'trigger'          => $funnel->trigger_name,
				'total_subscribers' => $total_subs,
				'subscriber_stats' => $subscriber_stats,
				'steps'            => $step_metrics,
				'total_steps'      => count( $step_metrics ),
			);
		},
	));

	$reg->read( 'fluent-crm/get-sequence-progress', array(
		'label'       => 'Get Sequence Progress',
		'description' => 'Get per-contact progress for a sequence: who is active, completed, or cancelled, and which email they are on.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'sequence_id' ),
			'properties' => array_merge( array(
				'sequence_id' => array( 'type' => 'integer', 'description' => 'Sequence ID' ),
				'status'      => array( 'type' => 'string', 'description' => 'Filter by status: active, completed, cancelled' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'contact_id'         => array( 'type' => 'integer' ),
			'sequence_id'        => array( 'type' => 'integer' ),
			'status'             => array( 'type' => 'string' ),
			'emails_sent'        => array( 'type' => 'integer' ),
			'next_email_subject' => array( 'type' => 'string' ),
			'scheduled_at'       => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$sequence_id = intval( $input['sequence_id'] );
			$pagination  = fluent_abilities_pagination( $input );

			global $wpdb;
			$tracker_table = $wpdb->prefix . 'fc_sequence_tracker';

			$where = $wpdb->prepare( "WHERE campaign_id = %d", $sequence_id );
			if ( ! empty( $input['status'] ) ) {
				$where .= $wpdb->prepare( " AND status = %s", sanitize_text_field( $input['status'] ) );
			}

			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tracker_table} {$where}" );

			$trackers = $wpdb->get_results(
				"SELECT st.subscriber_id, st.last_count, st.status, st.last_executed_at, st.next_execution_at, st.created_at,
					s.email, s.first_name, s.last_name
				FROM {$tracker_table} st
				LEFT JOIN {$wpdb->prefix}fc_subscribers s ON st.subscriber_id = s.id
				{$where}
				ORDER BY st.created_at DESC
				LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}"
			);

			// Summary
			$summary = $wpdb->get_results( $wpdb->prepare(
				"SELECT status, COUNT(*) as count FROM {$tracker_table} WHERE campaign_id = %d GROUP BY status",
				$sequence_id
			) );

			$status_counts = array();
			foreach ( $summary as $s ) {
				$status_counts[ $s->status ] = (int) $s->count;
			}

			$items = array();
			foreach ( $trackers as $t ) {
				$items[] = array(
					'subscriber_id'     => (int) $t->subscriber_id,
					'email'             => $t->email,
					'name'              => trim( $t->first_name . ' ' . $t->last_name ),
					'status'            => $t->status,
					'emails_sent'       => (int) $t->last_count,
					'last_executed_at'  => $t->last_executed_at,
					'next_execution_at' => $t->next_execution_at,
					'started_at'        => (string) $t->created_at,
				);
			}

			return array(
				'sequence_id'   => $sequence_id,
				'status_counts' => $status_counts,
				'subscribers'   => $items,
				'total'         => $total,
				'page'          => $pagination['page'],
				'per_page'      => $pagination['per_page'],
			);
		},
	));

	// =========================================================================
	// REPORTING ENGINE ACCESS
	// =========================================================================

	$reg->read( 'fluent-crm/get-crm-report', array(
		'label'       => 'Get CRM Report',
		'description' => 'Access FluentCRM\'s built-in CRM advanced reports: contact growth, email stats, click stats, unsubscribe stats. Supports date ranges and comparison periods. Auto-adjusts granularity (daily/weekly/monthly) based on range.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'report_type' ),
			'properties' => array(
				'report_type' => array(
					'type'        => 'string',
					'description' => 'Report type: product_growth (contact growth), email_stats, clicks_stats, unsubscribe_stats',
					'enum'        => array( 'product_growth', 'email_stats', 'clicks_stats', 'unsubscribe_stats' ),
				),
				'start_date' => array( 'type' => 'string', 'description' => 'Start date (YYYY-MM-DD). Default: 30 days ago.' ),
				'end_date'   => array( 'type' => 'string', 'description' => 'End date (YYYY-MM-DD). Default: today.' ),
				'sub_type'   => array(
					'type'        => 'string',
					'description' => 'Sub-type for product_growth: all (overall), tag (by tag), list (by list). Default: all.',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'report_type' => array( 'type' => 'string' ),
			'start_date'  => array( 'type' => 'string' ),
			'end_date'    => array( 'type' => 'string' ),
			'frequency'   => array( 'type' => 'string' ),
			'data_points' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'total'       => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$report_type = sanitize_text_field( $input['report_type'] );
			$end_date    = isset( $input['end_date'] ) ? sanitize_text_field( $input['end_date'] ) : date( 'Y-m-d' );
			$start_date  = isset( $input['start_date'] ) ? sanitize_text_field( $input['start_date'] ) : date( 'Y-m-d', strtotime( '-30 days' ) );

			global $wpdb;

			// Determine granularity
			$days = ( strtotime( $end_date ) - strtotime( $start_date ) ) / 86400;
			if ( $days <= 30 ) {
				$group_format = '%Y-%m-%d';
				$frequency = 'daily';
			} elseif ( $days <= 90 ) {
				$group_format = '%x-%v';
				$frequency = 'weekly';
			} else {
				$group_format = '%Y-%m';
				$frequency = 'monthly';
			}

			$data_points = array();

			switch ( $report_type ) {
				case 'product_growth':
					$sub_type = isset( $input['sub_type'] ) ? sanitize_text_field( $input['sub_type'] ) : 'all';

					if ( $sub_type === 'tag' || $sub_type === 'list' ) {
						$pivot_table = $wpdb->prefix . 'fc_subscriber_pivot';
						$object_type = $sub_type === 'tag' ? '%Tag' : '%Lists';

						$results = $wpdb->get_results( $wpdb->prepare(
							"SELECT DATE_FORMAT(created_at, '{$group_format}') as period, COUNT(*) as count
							FROM {$pivot_table}
							WHERE object_type LIKE %s
							AND created_at BETWEEN %s AND %s
							GROUP BY period ORDER BY period ASC",
							$object_type,
							$start_date . ' 00:00:00',
							$end_date . ' 23:59:59'
						) );
					} else {
						$results = $wpdb->get_results( $wpdb->prepare(
							"SELECT DATE_FORMAT(created_at, '{$group_format}') as period, COUNT(*) as count
							FROM {$wpdb->prefix}fc_subscribers
							WHERE created_at BETWEEN %s AND %s
							GROUP BY period ORDER BY period ASC",
							$start_date . ' 00:00:00',
							$end_date . ' 23:59:59'
						) );
					}

					foreach ( $results as $r ) {
						$data_points[] = array( 'period' => $r->period, 'count' => (int) $r->count );
					}
					break;

				case 'email_stats':
					$results = $wpdb->get_results( $wpdb->prepare(
						"SELECT DATE_FORMAT(scheduled_at, '{$group_format}') as period, COUNT(*) as count
						FROM {$wpdb->prefix}fc_campaign_emails
						WHERE status = 'sent'
						AND scheduled_at BETWEEN %s AND %s
						GROUP BY period ORDER BY period ASC",
						$start_date . ' 00:00:00',
						$end_date . ' 23:59:59'
					) );

					foreach ( $results as $r ) {
						$data_points[] = array( 'period' => $r->period, 'count' => (int) $r->count );
					}
					break;

				case 'clicks_stats':
					$results = $wpdb->get_results( $wpdb->prepare(
						"SELECT DATE_FORMAT(created_at, '{$group_format}') as period, COUNT(*) as count
						FROM {$wpdb->prefix}fc_campaign_url_metrics
						WHERE type = 'click'
						AND created_at BETWEEN %s AND %s
						GROUP BY period ORDER BY period ASC",
						$start_date . ' 00:00:00',
						$end_date . ' 23:59:59'
					) );

					foreach ( $results as $r ) {
						$data_points[] = array( 'period' => $r->period, 'count' => (int) $r->count );
					}
					break;

				case 'unsubscribe_stats':
					$results = $wpdb->get_results( $wpdb->prepare(
						"SELECT DATE_FORMAT(created_at, '{$group_format}') as period, COUNT(*) as count
						FROM {$wpdb->prefix}fc_subscriber_meta
						WHERE `key` = 'unsubscribe_reason'
						AND created_at BETWEEN %s AND %s
						GROUP BY period ORDER BY period ASC",
						$start_date . ' 00:00:00',
						$end_date . ' 23:59:59'
					) );

					foreach ( $results as $r ) {
						$data_points[] = array( 'period' => $r->period, 'count' => (int) $r->count );
					}
					break;
			}

			$total_count = array_sum( array_column( $data_points, 'count' ) );

			return array(
				'report_type' => $report_type,
				'start_date'  => $start_date,
				'end_date'    => $end_date,
				'frequency'   => $frequency,
				'data_points' => $data_points,
				'total'       => $total_count,
			);
		},
	));

	// =========================================================================
	// BULK DATA EXTRACTION
	// =========================================================================

	$reg->read( 'fluent-crm/get-tag-timeline', array(
		'label'       => 'Get Tag Application Timeline',
		'description' => 'Get when a specific tag was applied to contacts (for cohort analysis). Returns contacts and timestamps.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'tag_id' ),
			'properties' => array_merge( array(
				'tag_id'     => array( 'type' => 'integer', 'description' => 'Tag ID' ),
				'start_date' => array( 'type' => 'string', 'description' => 'Filter from date (YYYY-MM-DD)' ),
				'end_date'   => array( 'type' => 'string', 'description' => 'Filter to date (YYYY-MM-DD)' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'tag_id'   => array( 'type' => 'integer' ),
			'tag_title'=> array( 'type' => 'string' ),
			'timeline' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'total'    => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$tag_id     = intval( $input['tag_id'] );
			$pagination = fluent_abilities_pagination( $input );

			global $wpdb;
			$pivot_table = $wpdb->prefix . 'fc_subscriber_pivot';

			$where = $wpdb->prepare(
				"WHERE p.object_type LIKE '%%Tag' AND p.object_id = %d",
				$tag_id
			);

			if ( ! empty( $input['start_date'] ) ) {
				$where .= $wpdb->prepare( " AND p.created_at >= %s", sanitize_text_field( $input['start_date'] ) . ' 00:00:00' );
			}
			if ( ! empty( $input['end_date'] ) ) {
				$where .= $wpdb->prepare( " AND p.created_at <= %s", sanitize_text_field( $input['end_date'] ) . ' 23:59:59' );
			}

			$total = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$pivot_table} p {$where}"
			);

			$results = $wpdb->get_results(
				"SELECT p.subscriber_id, p.created_at as applied_at, s.email, s.first_name, s.last_name, s.status
				FROM {$pivot_table} p
				LEFT JOIN {$wpdb->prefix}fc_subscribers s ON p.subscriber_id = s.id
				{$where}
				ORDER BY p.created_at DESC
				LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}"
			);

			$tag = \FluentCrm\App\Models\Tag::find( $tag_id );

			$items = array();
			foreach ( $results as $r ) {
				$items[] = array(
					'subscriber_id' => (int) $r->subscriber_id,
					'email'         => $r->email,
					'name'          => trim( $r->first_name . ' ' . $r->last_name ),
					'status'        => $r->status,
					'applied_at'    => $r->applied_at,
				);
			}

			return array(
				'tag_id'    => $tag_id,
				'tag_title' => $tag ? $tag->title : null,
				'contacts'  => $items,
				'total'     => $total,
				'page'      => $pagination['page'],
				'per_page'  => $pagination['per_page'],
			);
		},
	));

	// =========================================================================
	// DELETE ABILITIES
	// =========================================================================

	$reg->delete( 'fluent-crm/delete-tag', array(
		'label'       => 'Delete CRM Tag',
		'description' => 'Permanently delete a tag by ID. All contacts with this tag will have it removed.',
		'category'    => 'fluent-crm',
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
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function( $input ) {
			$id  = intval( $input['id'] );
			$tag = \FluentCrm\App\Models\Tag::find( $id );
			if ( ! $tag ) {
				return fluent_abilities_error( 'not_found', 'Tag not found.' );
			}

			// Detach all contacts from this tag (clean up pivot table).
			fluentCrmDb()->table( 'fc_subscriber_pivot' )
				->where( 'object_type', 'FluentCrm\\App\\Models\\Tag' )
				->where( 'object_id', $id )
				->delete();

			$tag->delete();

			// Fire FluentCRM's own cleanup hooks (matches TagsController behavior).
			do_action( 'fluentcrm_tag_deleted', $id );
			do_action( 'fluent_crm/tag_deleted', $id );

			return array( 'success' => true, 'id' => $id );
		},
	) );

	$reg->delete( 'fluent-crm/delete-note', array(
		'label'       => 'Delete CRM Note',
		'description' => 'Permanently delete a contact note by ID.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Note ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function( $input ) {
			global $wpdb;
			$id = intval( $input['id'] );
			$table = $wpdb->prefix . 'fc_subscriber_notes';
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d", $id ) );
			if ( ! $exists ) {
				return fluent_abilities_error( 'not_found', 'Note not found.' );
			}
			$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
			return array( 'success' => true, 'id' => $id );
		},
	) );

	$reg->delete( 'fluent-crm/delete-campaign', array(
		'label'       => 'Delete CRM Campaign',
		'description' => 'Permanently delete a campaign by ID. Only draft campaigns can be deleted.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Campaign ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function( $input ) {
			$campaign = \FluentCrm\App\Models\Campaign::find( intval( $input['id'] ) );
			if ( ! $campaign ) {
				return fluent_abilities_error( 'not_found', 'Campaign not found.' );
			}
			if ( ! in_array( $campaign->status, array( 'draft', 'archived' ), true ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Only draft or archived campaigns can be deleted. Current status: ' . $campaign->status );
			}
			$id = $campaign->id;
			$campaign->delete();
			return array( 'success' => true, 'id' => $id );
		},
	) );

	$reg->write( 'fluent-crm/update-tag', array(
		'label'       => 'Update CRM Tag',
		'description' => 'Update a tag title or slug.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'    => array( 'type' => 'integer', 'description' => 'Tag ID' ),
				'title' => array( 'type' => 'string', 'description' => 'New tag title' ),
				'slug'  => array( 'type' => 'string', 'description' => 'New tag slug' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
			'slug'  => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$tag = \FluentCrm\App\Models\Tag::find( intval( $input['id'] ) );
			if ( ! $tag ) {
				return fluent_abilities_error( 'not_found', 'Tag not found.' );
			}
			if ( isset( $input['title'] ) ) {
				$tag->title = sanitize_text_field( $input['title'] );
			}
			if ( isset( $input['slug'] ) ) {
				$tag->slug = sanitize_title( $input['slug'] );
			}
			$tag->save();
			return array( 'success' => true, 'id' => $tag->id, 'title' => $tag->title, 'slug' => $tag->slug );
		},
	) );

	$reg->write( 'fluent-crm/create-list', array(
		'label'       => 'Create CRM List',
		'description' => 'Create a new contact list in FluentCRM.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'title' ),
			'properties' => array(
				'title' => array( 'type' => 'string', 'description' => 'List title' ),
				'slug'  => array( 'type' => 'string', 'description' => 'List slug (auto-generated from title if omitted)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
			'slug'  => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			$data = array( 'title' => sanitize_text_field( $input['title'] ) );
			if ( ! empty( $input['slug'] ) ) {
				$data['slug'] = sanitize_title( $input['slug'] );
			} else {
				$data['slug'] = sanitize_title( $data['title'] );
			}
			$list = \FluentCrm\App\Models\Lists::create( $data );
			return array( 'success' => true, 'id' => $list->id, 'title' => $list->title, 'slug' => $list->slug );
		},
	) );

	$reg->write( 'fluent-crm/update-list', array(
		'label'       => 'Update CRM List',
		'description' => 'Update a contact list title or slug.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'    => array( 'type' => 'integer', 'description' => 'List ID' ),
				'title' => array( 'type' => 'string', 'description' => 'New list title' ),
				'slug'  => array( 'type' => 'string', 'description' => 'New list slug' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
			'slug'  => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$list = \FluentCrm\App\Models\Lists::find( intval( $input['id'] ) );
			if ( ! $list ) {
				return fluent_abilities_error( 'not_found', 'List not found.' );
			}
			if ( isset( $input['title'] ) ) {
				$list->title = sanitize_text_field( $input['title'] );
			}
			if ( isset( $input['slug'] ) ) {
				$list->slug = sanitize_title( $input['slug'] );
			}
			$list->save();
			return array( 'success' => true, 'id' => $list->id, 'title' => $list->title, 'slug' => $list->slug );
		},
	) );

	$reg->delete( 'fluent-crm/delete-list', array(
		'label'       => 'Delete CRM List',
		'description' => 'Permanently delete a contact list by ID. Contacts in this list will have it removed.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'List ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function( $input ) {
			$list = \FluentCrm\App\Models\Lists::find( intval( $input['id'] ) );
			if ( ! $list ) {
				return fluent_abilities_error( 'not_found', 'List not found.' );
			}
			$id = $list->id;
			$list->delete();
			return array( 'success' => true, 'id' => $id );
		},
	) );

	$reg->write( 'fluent-crm/create-sequence', array(
		'label'       => 'Create CRM Sequence',
		'description' => 'Create a new email sequence (drip campaign) in draft status.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'title' ),
			'properties' => array(
				'title' => array( 'type' => 'string', 'description' => 'Sequence title' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'     => array( 'type' => 'integer' ),
			'title'  => array( 'type' => 'string' ),
			'status' => array( 'type' => 'string' ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			$sequence = \FluentCampaign\App\Models\Sequence::create( array(
				'title'  => sanitize_text_field( $input['title'] ),
				'status' => 'draft',
			) );
			return array( 'success' => true, 'id' => $sequence->id, 'title' => $sequence->title, 'status' => 'draft' );
		},
	) );

	$reg->delete( 'fluent-crm/delete-sequence', array(
		'label'       => 'Delete CRM Sequence',
		'description' => 'Permanently delete a sequence by ID. Only draft sequences can be deleted.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Sequence ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function( $input ) {
			$sequence = \FluentCampaign\App\Models\Sequence::find( intval( $input['id'] ) );
			if ( ! $sequence ) {
				return fluent_abilities_error( 'not_found', 'Sequence not found.' );
			}
			if ( $sequence->status !== 'draft' ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Only draft sequences can be deleted. Current status: ' . $sequence->status );
			}
			$id = $sequence->id;
			$sequence->delete();
			return array( 'success' => true, 'id' => $id );
		},
	) );

	$reg->delete( 'fluent-crm/delete-smart-link', array(
		'label'       => 'Delete Smart Link',
		'description' => 'Permanently delete a smart link by ID.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Smart link ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function( $input ) {
			$link = \FluentCampaign\App\Models\SmartLink::find( intval( $input['id'] ) );
			if ( ! $link ) {
				return fluent_abilities_error( 'not_found', 'Smart link not found.' );
			}
			$id = $link->id;
			$link->delete();
			return array( 'success' => true, 'id' => $id );
		},
	) );

	$reg->write( 'fluent-crm/update-note', array(
		'label'       => 'Update Contact Note',
		'description' => 'Update an existing contact note title, description, or type.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'          => array( 'type' => 'integer', 'description' => 'Note ID' ),
				'title'       => array( 'type' => 'string', 'description' => 'New note title' ),
				'description' => array( 'type' => 'string', 'description' => 'New note body' ),
				'type'        => array( 'type' => 'string', 'description' => 'Note type' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			global $wpdb;
			$id    = intval( $input['id'] );
			$table = $wpdb->prefix . 'fc_subscriber_notes';
			$note  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
			if ( ! $note ) {
				return fluent_abilities_error( 'not_found', 'Note not found.' );
			}

			$update = array();
			if ( isset( $input['title'] ) ) {
				$update['title'] = sanitize_text_field( $input['title'] );
			}
			if ( isset( $input['description'] ) ) {
				$update['description'] = wp_kses_post( $input['description'] );
			}
			if ( isset( $input['type'] ) ) {
				$update['type'] = sanitize_text_field( $input['type'] );
			}

			if ( empty( $update ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'No fields provided to update.' );
			}

			$wpdb->update( $table, $update, array( 'id' => $id ) );
			return array( 'success' => true, 'id' => $id );
		},
	) );

}, 100 );
