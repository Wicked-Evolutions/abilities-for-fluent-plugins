<?php
/**
 * Fluent Support Abilities
 *
 * Tickets, conversations, agents, customers, and support statistics.
 *
 * 20 abilities in this file. 31 total across support module files.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/persons.php';
require_once __DIR__ . '/products.php';
require_once __DIR__ . '/tags.php';

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'support' );

	// =========================================================================
	// TICKETS
	// =========================================================================

	$reg->read( 'fluent-support/list-tickets', array(
		'label'       => 'List Support Tickets',
		'description' => 'List support tickets with pagination. Filter by status, priority, agent_id, or customer_id.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'status' => array(
					'type'        => 'string',
					'description' => 'Filter by status: new, active, closed, resolved',
				),
				'priority' => array(
					'type'        => 'string',
					'description' => 'Filter by priority: normal, medium, critical',
				),
				'agent_id' => array(
					'type'        => 'integer',
					'description' => 'Filter by agent person ID',
				),
				'customer_id' => array(
					'type'        => 'integer',
					'description' => 'Filter by customer person ID',
				),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'tickets', array(
			'id'                     => array( 'type' => 'integer' ),
			'title'                  => array( 'type' => 'string' ),
			'status'                 => array( 'type' => 'string' ),
			'priority'               => array( 'type' => 'string' ),
			'response_count'         => array( 'type' => 'integer' ),
			'waiting_since'          => array( 'type' => array( 'string', 'null' ) ),
			'last_customer_response' => array( 'type' => 'string' ),
			'created_at'             => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );

			$query = wpFluent()->table( 'fs_tickets' )
				->orderBy( 'id', 'DESC' );

			if ( ! empty( $input['status'] ) ) {
				$query->where( 'status', sanitize_text_field( $input['status'] ) );
			}

			if ( ! empty( $input['priority'] ) ) {
				$query->where( 'priority', sanitize_text_field( $input['priority'] ) );
			}

			if ( ! empty( $input['agent_id'] ) ) {
				$query->where( 'agent_id', (int) $input['agent_id'] );
			}

			if ( ! empty( $input['customer_id'] ) ) {
				$query->where( 'customer_id', (int) $input['customer_id'] );
			}

			$total = $query->count();
			$tickets = $query->offset( $pagination['offset'] )
				->limit( $pagination['per_page'] )
				->get();

			// Collect person IDs for batch lookup.
			$person_ids = array();
			foreach ( $tickets as $ticket ) {
				if ( $ticket->customer_id ) {
					$person_ids[] = (int) $ticket->customer_id;
				}
				if ( $ticket->agent_id ) {
					$person_ids[] = (int) $ticket->agent_id;
				}
			}

			$persons = array();
			if ( ! empty( $person_ids ) ) {
				$person_ids = array_unique( $person_ids );
				$rows = wpFluent()->table( 'fs_persons' )
					->whereIn( 'id', $person_ids )
					->get();
				foreach ( $rows as $row ) {
					$persons[ $row->id ] = trim( $row->first_name . ' ' . $row->last_name );
				}
			}

			$items = array();
			foreach ( $tickets as $ticket ) {
				$items[] = array(
					'id'             => (int) $ticket->id,
					'title'          => $ticket->title,
					'status'         => $ticket->status,
					'priority'       => $ticket->priority,
					'customer_id'    => (int) $ticket->customer_id,
					'customer_name'  => $persons[ (int) $ticket->customer_id ] ?? null,
					'agent_id'       => $ticket->agent_id ? (int) $ticket->agent_id : null,
					'agent_name'     => $ticket->agent_id ? ( $persons[ (int) $ticket->agent_id ] ?? null ) : null,
					'response_count' => (int) $ticket->response_count,
					'created_at'     => (string) $ticket->created_at,
				);
			}

			return array(
				'tickets'  => $items,
				'total'    => $total,
				'page'     => $pagination['page'],
				'per_page' => $pagination['per_page'],
			);
		},
	) );

	$reg->read( 'fluent-support/get-ticket', array(
		'label'       => 'Get Support Ticket',
		'description' => 'Get a single support ticket by ID with full details, customer info, agent info, and response count.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Ticket ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'                     => array( 'type' => 'integer' ),
			'title'                  => array( 'type' => 'string' ),
			'slug'                   => array( 'type' => 'string' ),
			'content'                => array( 'type' => 'string' ),
			'status'                 => array( 'type' => 'string' ),
			'priority'               => array( 'type' => 'string' ),
			'client_priority'        => array( 'type' => 'string' ),
			'response_count'         => array( 'type' => 'integer' ),
			'first_response_time'    => array( 'type' => array( 'integer', 'null' ) ),
			'last_customer_response' => array( 'type' => array( 'string', 'null' ) ),
			'last_agent_response'    => array( 'type' => array( 'string', 'null' ) ),
			'waiting_since'          => array( 'type' => array( 'string', 'null' ) ),
			'resolved_at'            => array( 'type' => array( 'string', 'null' ) ),
			'closed_at'              => array( 'type' => array( 'string', 'null' ) ),
			'created_at'             => array( 'type' => 'string' ),
			'updated_at'             => array( 'type' => 'string' ),
			'mailbox_id'             => array( 'type' => array( 'integer', 'null' ) ),
			'mailbox_name'           => array( 'type' => array( 'string', 'null' ) ),
			'product_id'             => array( 'type' => array( 'integer', 'null' ) ),
			'customer'               => array( 'type' => array( 'object', 'null' ) ),
			'agent'                  => array( 'type' => array( 'object', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$ticket = wpFluent()->table( 'fs_tickets' )
				->where( 'id', (int) $input['id'] )
				->first();

			if ( ! $ticket ) {
				return fluent_abilities_error( 'not_found', 'Ticket not found' );
			}

			// Fetch customer.
			$customer = null;
			if ( $ticket->customer_id ) {
				$customer = wpFluent()->table( 'fs_persons' )
					->where( 'id', (int) $ticket->customer_id )
					->first();
			}

			// Fetch agent.
			$agent = null;
			if ( $ticket->agent_id ) {
				$agent = wpFluent()->table( 'fs_persons' )
					->where( 'id', (int) $ticket->agent_id )
					->first();
			}

			// Fetch mailbox name.
			$mailbox_name = null;
			if ( $ticket->mailbox_id ) {
				$mailbox = wpFluent()->table( 'fs_mail_boxes' )
					->where( 'id', (int) $ticket->mailbox_id )
					->first();
				$mailbox_name = $mailbox ? $mailbox->name : null;
			}

			return array(
				'id'                     => (int) $ticket->id,
				'title'                  => $ticket->title,
				'slug'                   => $ticket->slug,
				'content'                => $ticket->content,
				'status'                 => $ticket->status,
				'priority'               => $ticket->priority,
				'client_priority'        => $ticket->client_priority,
				'response_count'         => (int) $ticket->response_count,
				'first_response_time'    => $ticket->first_response_time !== null ? (int) $ticket->first_response_time : null,
				'last_customer_response' => $ticket->last_customer_response,
				'last_agent_response'    => $ticket->last_agent_response,
				'waiting_since'          => $ticket->waiting_since,
				'resolved_at'            => $ticket->resolved_at ?? null,
				'closed_at'              => $ticket->closed_at ?? null,
				'created_at'             => (string) $ticket->created_at,
				'updated_at'             => (string) $ticket->updated_at,
				'mailbox_id'             => $ticket->mailbox_id ? (int) $ticket->mailbox_id : null,
				'mailbox_name'           => $mailbox_name,
				'product_id'             => $ticket->product_id ? (int) $ticket->product_id : null,
				'customer' => $customer ? array(
					'id'         => (int) $customer->id,
					'first_name' => $customer->first_name,
					'last_name'  => $customer->last_name,
					'email'      => $customer->email,
				) : null,
				'agent' => $agent ? array(
					'id'         => (int) $agent->id,
					'first_name' => $agent->first_name,
					'last_name'  => $agent->last_name,
					'email'      => $agent->email,
				) : null,
			);
		},
	) );

	$reg->write( 'fluent-support/create-ticket', array(
		'label'       => 'Create Support Ticket',
		'description' => 'Create a new support ticket. Finds or creates a customer from the provided email address.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'title', 'content', 'customer_email' ),
			'properties' => array(
				'title'          => array( 'type' => 'string', 'description' => 'Ticket title/subject (required)' ),
				'content'        => array( 'type' => 'string', 'description' => 'Ticket body content (required)' ),
				'customer_email' => array( 'type' => 'string', 'description' => 'Customer email — will find existing or create new customer (required)' ),
				'priority'       => array( 'type' => 'string', 'description' => 'Priority: normal, medium, critical (default: normal)' ),
				'mailbox_id'     => array( 'type' => 'integer', 'description' => 'Mailbox ID (uses default mailbox if omitted)' ),
				'product_id'     => array( 'type' => 'integer', 'description' => 'Product ID (optional)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'          => array( 'type' => 'integer' ),
			'customer_id' => array( 'type' => 'integer' ),
			'slug'        => array( 'type' => 'string' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			$email = sanitize_email( $input['customer_email'] );
			if ( ! is_email( $email ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Invalid customer email address' );
			}

			// Find or create the customer person record.
			$customer = wpFluent()->table( 'fs_persons' )
				->where( 'email', $email )
				->where( 'person_type', 'customer' )
				->first();

			if ( ! $customer ) {
				// Try to get name from WordPress user if one exists.
				$wp_user = get_user_by( 'email', $email );
				$first_name = $wp_user ? $wp_user->first_name : '';
				$last_name  = $wp_user ? $wp_user->last_name : '';

				$customer_id = wpFluent()->table( 'fs_persons' )->insertGetId( array(
					'first_name'  => $first_name,
					'last_name'   => $last_name,
					'email'       => $email,
					'person_type' => 'customer',
					'status'      => 'active',
					'created_at'  => current_time( 'mysql' ),
					'updated_at'  => current_time( 'mysql' ),
				));
			} else {
				$customer_id = (int) $customer->id;
			}

			// Resolve mailbox ID — use provided or find the default.
			$mailbox_id = null;
			if ( ! empty( $input['mailbox_id'] ) ) {
				$mailbox_id = (int) $input['mailbox_id'];
			} else {
				$default_box = wpFluent()->table( 'fs_mail_boxes' )
					->where( 'is_default', 'yes' )
					->first();
				if ( $default_box ) {
					$mailbox_id = (int) $default_box->id;
				}
			}

			$now  = current_time( 'mysql' );
			$slug = sanitize_title( $input['title'] ) . '-' . substr( md5( $now . $email ), 0, 8 );
			$hash = md5( $slug . wp_rand() );

			$ticket_id = wpFluent()->table( 'fs_tickets' )->insertGetId( array(
				'customer_id'    => $customer_id,
				'mailbox_id'     => $mailbox_id,
				'product_id'     => ! empty( $input['product_id'] ) ? (int) $input['product_id'] : null,
				'title'          => sanitize_text_field( $input['title'] ),
				'slug'           => $slug,
				'hash'           => $hash,
				'content'        => wp_kses_post( $input['content'] ),
				'status'         => 'new',
				'priority'       => sanitize_text_field( $input['priority'] ?? 'normal' ),
				'client_priority' => 'normal',
				'response_count' => 0,
				'waiting_since'  => $now,
				'created_at'     => $now,
				'updated_at'     => $now,
			));

			return array(
				'success'     => true,
				'id'          => $ticket_id,
				'customer_id' => $customer_id,
				'status'      => 'new',
				'priority'    => sanitize_text_field( $input['priority'] ?? 'normal' ),
			);
		},
	) );

	$reg->write( 'fluent-support/update-ticket', array(
		'label'       => 'Update Support Ticket',
		'description' => 'Update a ticket\'s status, priority, or agent assignment. Only provided fields are changed.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'       => array( 'type' => 'integer', 'description' => 'Ticket ID' ),
				'status'   => array( 'type' => 'string', 'description' => 'New status: new, active, closed, resolved' ),
				'priority' => array( 'type' => 'string', 'description' => 'New priority: normal, medium, critical' ),
				'agent_id' => array( 'type' => 'integer', 'description' => 'Assign to agent by person ID (0 to unassign)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'     => array( 'type' => 'integer' ),
			'status' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$ticket = wpFluent()->table( 'fs_tickets' )
				->where( 'id', (int) $input['id'] )
				->first();

			if ( ! $ticket ) {
				return fluent_abilities_error( 'not_found', 'Ticket not found' );
			}

			$data    = array();
			$updated = array();
			$now     = current_time( 'mysql' );

			if ( isset( $input['status'] ) ) {
				$status = sanitize_text_field( $input['status'] );
				$allowed_statuses = array( 'new', 'active', 'closed', 'resolved' );
				if ( ! in_array( $status, $allowed_statuses, true ) ) {
					return fluent_abilities_error( 'ability_invalid_input', 'Status must be one of: ' . implode( ', ', $allowed_statuses ) );
				}
				$data['status'] = $status;
				$updated[]      = 'status';

				// Set resolved/closed timestamps.
				if ( 'resolved' === $status && ! $ticket->resolved_at ) {
					$data['resolved_at']       = $now;
					$data['resolved_duration'] = strtotime( $now ) - strtotime( $ticket->created_at );
				}
				if ( 'closed' === $status && ! $ticket->closed_at ) {
					$data['closed_at']       = $now;
					$data['closed_by']       = get_current_user_id();
					$data['total_close_time'] = strtotime( $now ) - strtotime( $ticket->created_at );
				}
			}

			if ( isset( $input['priority'] ) ) {
				$priority = sanitize_text_field( $input['priority'] );
				$allowed_priorities = array( 'normal', 'medium', 'critical' );
				if ( ! in_array( $priority, $allowed_priorities, true ) ) {
					return fluent_abilities_error( 'ability_invalid_input', 'Priority must be one of: ' . implode( ', ', $allowed_priorities ) );
				}
				$data['priority'] = $priority;
				$updated[]        = 'priority';
			}

			if ( isset( $input['agent_id'] ) ) {
				$agent_id = (int) $input['agent_id'];
				if ( $agent_id > 0 ) {
					// Verify the agent exists.
					$agent = wpFluent()->table( 'fs_persons' )
						->where( 'id', $agent_id )
						->where( 'person_type', 'agent' )
						->first();
					if ( ! $agent ) {
						return fluent_abilities_error( 'not_found', 'Agent not found' );
					}
				}
				$data['agent_id'] = $agent_id > 0 ? $agent_id : null;
				$updated[]        = 'agent_id';
			}

			if ( empty( $data ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'No fields provided to update' );
			}

			$data['updated_at'] = $now;

			wpFluent()->table( 'fs_tickets' )
				->where( 'id', (int) $input['id'] )
				->update( $data );

			return array(
				'success' => true,
				'id'      => (int) $input['id'],
				'updated' => $updated,
			);
		},
	) );

	// =========================================================================
	// CONVERSATIONS
	// =========================================================================

	$reg->read( 'fluent-support/list-conversations', array(
		'label'       => 'List Ticket Conversations',
		'description' => 'List conversations (replies and notes) for a specific support ticket.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'ticket_id' ),
			'properties' => array_merge( array(
				'ticket_id' => array( 'type' => 'integer', 'description' => 'Ticket ID (required)' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'conversations', array(
			'id'         => array( 'type' => 'integer' ),
			'ticket_id'  => array( 'type' => 'integer' ),
			'person_id'  => array( 'type' => 'integer' ),
			'message'    => array( 'type' => 'string' ),
			'created_at' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$ticket_id = (int) $input['ticket_id'];

			// Verify ticket exists.
			$ticket = wpFluent()->table( 'fs_tickets' )
				->where( 'id', $ticket_id )
				->first();

			if ( ! $ticket ) {
				return fluent_abilities_error( 'not_found', 'Ticket not found' );
			}

			$pagination = fluent_abilities_pagination( $input );

			$query = wpFluent()->table( 'fs_conversations' )
				->where( 'ticket_id', $ticket_id )
				->orderBy( 'id', 'ASC' );

			$total = $query->count();
			$conversations = $query->offset( $pagination['offset'] )
				->limit( $pagination['per_page'] )
				->get();

			// Batch lookup person names.
			$person_ids = array();
			foreach ( $conversations as $conv ) {
				if ( $conv->person_id ) {
					$person_ids[] = (int) $conv->person_id;
				}
			}

			$persons = array();
			if ( ! empty( $person_ids ) ) {
				$person_ids = array_unique( $person_ids );
				$rows = wpFluent()->table( 'fs_persons' )
					->whereIn( 'id', $person_ids )
					->get();
				foreach ( $rows as $row ) {
					$persons[ $row->id ] = array(
						'name'        => trim( $row->first_name . ' ' . $row->last_name ),
						'person_type' => $row->person_type,
					);
				}
			}

			$items = array();
			foreach ( $conversations as $conv ) {
				$person_info = $persons[ (int) $conv->person_id ] ?? null;
				$items[] = array(
					'id'                => (int) $conv->id,
					'person_id'         => $conv->person_id ? (int) $conv->person_id : null,
					'person_name'       => $person_info ? $person_info['name'] : null,
					'person_type'       => $person_info ? $person_info['person_type'] : null,
					'conversation_type' => $conv->conversation_type,
					'content'           => $conv->content,
					'source'            => $conv->source,
					'created_at'        => (string) $conv->created_at,
				);
			}

			return array(
				'conversations' => $items,
				'total'         => $total,
				'page'          => $pagination['page'],
				'per_page'      => $pagination['per_page'],
			);
		},
	) );

	$reg->write( 'fluent-support/reply-to-ticket', array(
		'label'       => 'Reply to Support Ticket',
		'description' => 'Add a reply or internal note to a support ticket. Defaults to agent response.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'ticket_id', 'content' ),
			'properties' => array(
				'ticket_id'    => array( 'type' => 'integer', 'description' => 'Ticket ID (required)' ),
				'content'      => array( 'type' => 'string', 'description' => 'Reply content (required)' ),
				'message_type' => array( 'type' => 'string', 'description' => 'Message type: response or note (default: response)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'              => array( 'type' => 'integer' ),
			'conversation_id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			$ticket_id = (int) $input['ticket_id'];

			$ticket = wpFluent()->table( 'fs_tickets' )
				->where( 'id', $ticket_id )
				->first();

			if ( ! $ticket ) {
				return fluent_abilities_error( 'not_found', 'Ticket not found' );
			}

			$message_type = sanitize_text_field( $input['message_type'] ?? 'response' );
			$allowed_types = array( 'response', 'note' );
			if ( ! in_array( $message_type, $allowed_types, true ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'message_type must be response or note' );
			}

			// Determine conversation_type from message_type.
			$conversation_type = 'note' === $message_type ? 'note' : 'reply';

			// Find the agent person record for the current WP user.
			$current_user = wp_get_current_user();
			$agent = wpFluent()->table( 'fs_persons' )
				->where( 'email', $current_user->user_email )
				->where( 'person_type', 'agent' )
				->first();

			$person_id = $agent ? (int) $agent->id : null;

			if ( ! $person_id ) {
				return fluent_abilities_error( "ability_invalid_input", "Current API user is not registered as a Fluent Support agent" );
			}

			$now     = current_time( 'mysql' );
			$content = wp_kses_post( $input['content'] );

			$conversation_id = wpFluent()->table( 'fs_conversations' )->insertGetId( array(
				'ticket_id'         => $ticket_id,
				'person_id'         => $person_id,
				'conversation_type' => $conversation_type,
				'content'           => $content,
				'source'            => 'abilities_api',
				'content_hash'      => md5( $content ),
				'created_at'        => $now,
				'updated_at'        => $now,
			));

			// Update ticket counters and timestamps.
			$update_data = array(
				'updated_at'          => $now,
				'last_agent_response' => $now,
			);

			// Only bump response_count for actual responses, not notes.
			if ( 'response' === $message_type ) {
				$update_data['response_count'] = (int) $ticket->response_count + 1;

				// Track first response time.
				if ( ! $ticket->first_response_time && $ticket->created_at ) {
					$update_data['first_response_time'] = strtotime( $now ) - strtotime( $ticket->created_at );
				}

				// Move ticket to active if it was new.
				if ( 'new' === $ticket->status ) {
					$update_data['status'] = 'active';
				}
			}

			wpFluent()->table( 'fs_tickets' )
				->where( 'id', $ticket_id )
				->update( $update_data );

			return array(
				'success'         => true,
				'id'              => $conversation_id,
				'conversation_id' => $conversation_id,
			);
		},
	) );

	// =========================================================================
	// AGENTS
	// =========================================================================

	$reg->read( 'fluent-support/list-agents', array(
		'label'       => 'List Support Agents',
		'description' => 'List support agents with their open and total ticket counts.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'agents', array(
			'id'         => array( 'type' => 'integer' ),
			'full_name'  => array( 'type' => 'string' ),
			'email'      => array( 'type' => 'string' ),
			'status'     => array( 'type' => 'string' ),
			'created_at' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$agents = wpFluent()->table( 'fs_persons' )
				->where( 'person_type', 'agent' )
				->where( 'status', 'active' )
				->orderBy( 'first_name', 'ASC' )
				->get();

			$items = array();
			foreach ( $agents as $agent ) {
				$open_tickets = wpFluent()->table( 'fs_tickets' )
					->where( 'agent_id', $agent->id )
					->whereIn( 'status', array( 'new', 'active' ) )
					->count();

				$total_tickets = wpFluent()->table( 'fs_tickets' )
					->where( 'agent_id', $agent->id )
					->count();

				$items[] = array(
					'id'            => (int) $agent->id,
					'first_name'    => $agent->first_name,
					'last_name'     => $agent->last_name,
					'email'         => $agent->email,
					'title'         => $agent->title,
					'status'        => $agent->status,
					'open_tickets'  => $open_tickets,
					'total_tickets' => $total_tickets,
				);
			}

			return array( 'agents' => $items, 'total' => count( $items ) );
		},
	) );

	// =========================================================================
	// CUSTOMERS
	// =========================================================================

	$reg->read( 'fluent-support/list-customers', array(
		'label'       => 'List Support Customers',
		'description' => 'List support customers with pagination and optional search by name or email.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'search' => array(
					'type'        => 'string',
					'description' => 'Search by email, first name, or last name',
				),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'customers', array(
			'id'         => array( 'type' => 'integer' ),
			'first_name' => array( 'type' => 'string' ),
			'last_name'  => array( 'type' => 'string' ),
			'email'      => array( 'type' => 'string' ),
			'status'     => array( 'type' => 'string' ),
			'created_at' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );

			$query = wpFluent()->table( 'fs_persons' )
				->where( 'person_type', 'customer' )
				->orderBy( 'id', 'DESC' );

			if ( ! empty( $input['search'] ) ) {
				$search = sanitize_text_field( $input['search'] );
				$query->where( function( $q ) use ( $search ) {
					$q->where( 'email', 'LIKE', "%{$search}%" )
					  ->orWhere( 'first_name', 'LIKE', "%{$search}%" )
					  ->orWhere( 'last_name', 'LIKE', "%{$search}%" );
				});
			}

			$total = $query->count();
			$customers = $query->offset( $pagination['offset'] )
				->limit( $pagination['per_page'] )
				->get();

			$items = array();
			foreach ( $customers as $customer ) {
				// Count tickets for this customer.
				$ticket_count = wpFluent()->table( 'fs_tickets' )
					->where( 'customer_id', $customer->id )
					->count();

				$items[] = array(
					'id'           => (int) $customer->id,
					'first_name'   => $customer->first_name,
					'last_name'    => $customer->last_name,
					'email'        => $customer->email,
					'status'       => $customer->status,
					'ticket_count' => $ticket_count,
					'created_at'   => (string) $customer->created_at,
				);
			}

			return array(
				'customers' => $items,
				'total'     => $total,
				'page'      => $pagination['page'],
				'per_page'  => $pagination['per_page'],
			);
		},
	) );

	// =========================================================================
	// DASHBOARD STATS
	// =========================================================================

	$reg->read( 'fluent-support/get-support-stats', array(
		'label'       => 'Support Dashboard Stats',
		'description' => 'Get support overview: total tickets by status, average first response time, and agent workload.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'total_tickets'    => array( 'type' => 'integer' ),
			'open_tickets'     => array( 'type' => 'integer' ),
			'resolved_tickets' => array( 'type' => 'integer' ),
			'avg_response_time'=> array( 'type' => 'number' ),
		) ),
		'callback' => function( $input ) {
			$tickets_table = 'fs_tickets';

			// Ticket counts by status.
			$total    = wpFluent()->table( $tickets_table )->count();
			$new      = wpFluent()->table( $tickets_table )->where( 'status', 'new' )->count();
			$active   = wpFluent()->table( $tickets_table )->where( 'status', 'active' )->count();
			$closed   = wpFluent()->table( $tickets_table )->where( 'status', 'closed' )->count();
			$resolved = wpFluent()->table( $tickets_table )->where( 'status', 'resolved' )->count();

			// Average first response time (in seconds, for tickets that have a response).
			$avg_response = wpFluent()->table( $tickets_table )
				->whereNotNull( 'first_response_time' )
				->where( 'first_response_time', '>', 0 )
				->avg( 'first_response_time' );

			// Format average response time into human-readable string.
			$avg_response_formatted = null;
			if ( $avg_response ) {
				$seconds = (int) round( $avg_response );
				$hours   = floor( $seconds / 3600 );
				$minutes = floor( ( $seconds % 3600 ) / 60 );
				$avg_response_formatted = sprintf( '%dh %dm', $hours, $minutes );
			}

			// Agent workload: open tickets per agent.
			$agents = wpFluent()->table( 'fs_persons' )
				->where( 'person_type', 'agent' )
				->where( 'status', 'active' )
				->get();

			$workload = array();
			foreach ( $agents as $agent ) {
				$open = wpFluent()->table( $tickets_table )
					->where( 'agent_id', $agent->id )
					->whereIn( 'status', array( 'new', 'active' ) )
					->count();

				$workload[] = array(
					'agent_id'     => (int) $agent->id,
					'agent_name'   => trim( $agent->first_name . ' ' . $agent->last_name ),
					'open_tickets' => $open,
				);
			}

			// Unassigned open tickets.
			$unassigned = wpFluent()->table( $tickets_table )
				->whereNull( 'agent_id' )
				->whereIn( 'status', array( 'new', 'active' ) )
				->count();

			// Customer and mailbox counts.
			$customer_count = wpFluent()->table( 'fs_persons' )
				->where( 'person_type', 'customer' )
				->count();

			$mailbox_count = wpFluent()->table( 'fs_mail_boxes' )->count();

			return array(
				'tickets' => array(
					'total'    => $total,
					'new'      => $new,
					'active'   => $active,
					'closed'   => $closed,
					'resolved' => $resolved,
				),
				'avg_first_response_seconds'   => $avg_response ? (int) round( $avg_response ) : null,
				'avg_first_response_formatted' => $avg_response_formatted,
				'agent_workload'               => $workload,
				'unassigned_open_tickets'      => $unassigned,
				'total_customers'              => $customer_count,
				'total_mailboxes'              => $mailbox_count,
			);
		},
	) );

	// ===== SUPPORT — DELETE =====

	$reg->delete( 'fluent-support/delete-ticket', array(
		'label'       => 'Delete Support Ticket',
		'description' => 'Permanently delete a support ticket and all its conversations/replies.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Ticket ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			$ticket_id = (int) $input['id'];
			$ticket = wpFluent()->table( 'fs_tickets' )->where( 'id', $ticket_id )->first();
			if ( ! $ticket ) {
				return fluent_abilities_error( 'not_found', 'Ticket not found' );
			}
			// Delete conversations first, then ticket.
			wpFluent()->table( 'fs_conversations' )->where( 'ticket_id', $ticket_id )->delete();
			wpFluent()->table( 'fs_tickets' )->where( 'id', $ticket_id )->delete();
			return array( 'success' => true, 'id' => $ticket_id );
		},
	) );

	// =========================================================================
	// CONVERSATION UPDATES (P1)
	// =========================================================================

	$reg->write( 'fluent-support/update-conversation', array(
		'label'       => 'Update Conversation',
		'description' => 'Update the content of an existing ticket conversation (reply or note).',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'ticket_id', 'conversation_id', 'content' ),
			'properties' => array(
				'ticket_id'       => array( 'type' => 'integer', 'description' => 'Ticket ID' ),
				'conversation_id' => array( 'type' => 'integer', 'description' => 'Conversation ID' ),
				'content'         => array( 'type' => 'string', 'description' => 'Updated content' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function ( $input ) {
			$ticket_id = (int) $input['ticket_id'];
			$conv_id   = (int) $input['conversation_id'];

			$conv = wpFluent()->table( 'fs_conversations' )
				->where( 'id', $conv_id )
				->where( 'ticket_id', $ticket_id )
				->first();

			if ( ! $conv ) {
				return fluent_abilities_error( 'not_found', 'Conversation not found for this ticket' );
			}

			$content = wp_kses_post( $input['content'] );
			$now     = current_time( 'mysql' );

			wpFluent()->table( 'fs_conversations' )
				->where( 'id', $conv_id )
				->update( array(
					'content'      => $content,
					'content_hash' => md5( $content ),
					'updated_at'   => $now,
				) );

			return array(
				'success' => true,
				'id'      => $conv_id,
			);
		},
	) );

	$reg->delete( 'fluent-support/delete-conversation', array(
		'label'       => 'Delete Conversation',
		'description' => 'Delete a conversation (reply or note) from a ticket. Decrements response_count for replies.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'ticket_id', 'conversation_id' ),
			'properties' => array(
				'ticket_id'       => array( 'type' => 'integer', 'description' => 'Ticket ID' ),
				'conversation_id' => array( 'type' => 'integer', 'description' => 'Conversation ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function ( $input ) {
			$ticket_id = (int) $input['ticket_id'];
			$conv_id   = (int) $input['conversation_id'];

			$conv = wpFluent()->table( 'fs_conversations' )
				->where( 'id', $conv_id )
				->where( 'ticket_id', $ticket_id )
				->first();

			if ( ! $conv ) {
				return fluent_abilities_error( 'not_found', 'Conversation not found for this ticket' );
			}

			wpFluent()->table( 'fs_conversations' )
				->where( 'id', $conv_id )
				->delete();

			// Decrement response_count for actual responses (not notes).
			if ( $conv->conversation_type === 'reply' || $conv->conversation_type === 'response' ) {
				$ticket = wpFluent()->table( 'fs_tickets' )
					->where( 'id', $ticket_id )
					->first();

				if ( $ticket && (int) $ticket->response_count > 0 ) {
					wpFluent()->table( 'fs_tickets' )
						->where( 'id', $ticket_id )
						->update( array(
							'response_count' => (int) $ticket->response_count - 1,
							'updated_at'     => current_time( 'mysql' ),
						) );
				}
			}

			return array(
				'success' => true,
				'id'      => $conv_id,
			);
		},
	) );

	// =========================================================================
	// TICKET OPERATIONS (P1)
	// =========================================================================

	$reg->write( 'fluent-support/close-ticket', array(
		'label'       => 'Close Support Ticket',
		'description' => 'Close a support ticket. Fires FluentSupport hooks (notifications, workflows).',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'     => array( 'type' => 'integer', 'description' => 'Ticket ID' ),
				'silent' => array( 'type' => 'boolean', 'description' => 'Close silently without triggering notifications (default: false)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'     => array( 'type' => 'integer' ),
			'status' => array( 'type' => 'string' ),
		) ),
		'callback' => function ( $input ) {
			$ticket_id = (int) $input['id'];

			$ticket = \FluentSupport\App\Models\Ticket::find( $ticket_id );
			if ( ! $ticket ) {
				return fluent_abilities_error( 'not_found', 'Ticket not found' );
			}

			if ( $ticket->status === 'closed' ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Ticket is already closed' );
			}

			$agent = \FluentSupport\App\Services\Helper::getAgentByUserId();
			if ( ! $agent ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Current API user is not registered as a Fluent Support agent' );
			}

			$silently = ! empty( $input['silent'] ) ? 'yes' : 'no';

			$result = ( new \FluentSupport\App\Services\Tickets\TicketService() )->close( $ticket, $agent, '', $silently );

			return array(
				'success' => true,
				'id'      => (int) $result->id,
				'status'  => $result->status,
			);
		},
	) );

	$reg->write( 'fluent-support/reopen-ticket', array(
		'label'       => 'Reopen Support Ticket',
		'description' => 'Reopen a closed support ticket. Fires FluentSupport hooks.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Ticket ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'     => array( 'type' => 'integer' ),
			'status' => array( 'type' => 'string' ),
		) ),
		'callback' => function ( $input ) {
			$ticket_id = (int) $input['id'];

			$ticket = \FluentSupport\App\Models\Ticket::find( $ticket_id );
			if ( ! $ticket ) {
				return fluent_abilities_error( 'not_found', 'Ticket not found' );
			}

			if ( $ticket->status !== 'closed' ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Ticket is not closed' );
			}

			$agent = \FluentSupport\App\Services\Helper::getAgentByUserId();
			if ( ! $agent ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Current API user is not registered as a Fluent Support agent' );
			}

			( new \FluentSupport\App\Services\Tickets\TicketService() )->reopen( $ticket, $agent );

			// Refresh ticket to get updated status.
			$ticket = \FluentSupport\App\Models\Ticket::find( $ticket_id );

			return array(
				'success' => true,
				'id'      => (int) $ticket->id,
				'status'  => $ticket->status,
			);
		},
	) );

	$reg->write( 'fluent-support/bulk-ticket-action', array(
		'label'       => 'Bulk Ticket Action',
		'description' => 'Perform a bulk action on multiple tickets: close or delete.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'ticket_ids', 'action' ),
			'properties' => array(
				'ticket_ids' => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'integer' ),
					'description' => 'Array of ticket IDs',
				),
				'action' => array(
					'type'        => 'string',
					'description' => 'Action to perform: close or delete',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'affected' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function ( $input ) {
			$ticket_ids = array_map( 'intval', $input['ticket_ids'] ?? array() );
			$action     = sanitize_text_field( $input['action'] ?? '' );

			if ( empty( $ticket_ids ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'No ticket IDs provided' );
			}

			$allowed_actions = array( 'close', 'delete' );
			if ( ! in_array( $action, $allowed_actions, true ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Action must be one of: ' . implode( ', ', $allowed_actions ) );
			}

			$agent = \FluentSupport\App\Services\Helper::getAgentByUserId();
			if ( ! $agent ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Current API user is not registered as a Fluent Support agent' );
			}

			$tickets = \FluentSupport\App\Models\Ticket::whereIn( 'id', $ticket_ids )->get();
			$ticket_service = new \FluentSupport\App\Services\Tickets\TicketService();
			$affected = 0;

			foreach ( $tickets as $ticket ) {
				if ( 'close' === $action ) {
					if ( $ticket->status !== 'closed' ) {
						$ticket_service->close( $ticket, $agent );
						$affected++;
					}
				} elseif ( 'delete' === $action ) {
					$ticket_service->deleteTicket( $ticket );
					$affected++;
				}
			}

			return array(
				'success'  => true,
				'affected' => $affected,
			);
		},
	) );

	$reg->write( 'fluent-support/change-ticket-customer', array(
		'label'       => 'Change Ticket Customer',
		'description' => 'Reassign a ticket to a different customer.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'ticket_id', 'customer_id' ),
			'properties' => array(
				'ticket_id'   => array( 'type' => 'integer', 'description' => 'Ticket ID' ),
				'customer_id' => array( 'type' => 'integer', 'description' => 'New customer person ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function ( $input ) {
			$ticket_id   = (int) $input['ticket_id'];
			$customer_id = (int) $input['customer_id'];

			$ticket = wpFluent()->table( 'fs_tickets' )
				->where( 'id', $ticket_id )
				->first();

			if ( ! $ticket ) {
				return fluent_abilities_error( 'not_found', 'Ticket not found' );
			}

			// Verify new customer exists and is a customer.
			$customer = wpFluent()->table( 'fs_persons' )
				->where( 'id', $customer_id )
				->where( 'person_type', 'customer' )
				->first();

			if ( ! $customer ) {
				return fluent_abilities_error( 'not_found', 'Customer not found' );
			}

			wpFluent()->table( 'fs_tickets' )
				->where( 'id', $ticket_id )
				->update( array(
					'customer_id' => $customer_id,
					'updated_at'  => current_time( 'mysql' ),
				) );

			return array(
				'success' => true,
				'id'      => $ticket_id,
			);
		},
	) );

	// =========================================================================
	// MAILBOXES (P1)
	// =========================================================================

	$reg->read( 'fluent-support/list-mailboxes', array(
		'label'       => 'List Support Mailboxes',
		'description' => 'List all support mailboxes (business inboxes).',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type' => 'object',
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'mailboxes', array(
			'id'         => array( 'type' => 'integer' ),
			'name'       => array( 'type' => 'string' ),
			'slug'       => array( 'type' => 'string' ),
			'email'      => array( 'type' => 'string' ),
			'box_type'   => array( 'type' => array( 'string', 'null' ) ),
			'is_default' => array( 'type' => array( 'string', 'null' ) ),
			'created_at' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function ( $input ) {
			$mailboxes = wpFluent()->table( 'fs_mail_boxes' )
				->orderBy( 'name', 'ASC' )
				->get();

			$items = array();
			foreach ( $mailboxes as $box ) {
				$items[] = array(
					'id'         => (int) $box->id,
					'name'       => $box->name,
					'slug'       => $box->slug,
					'email'      => $box->email,
					'box_type'   => $box->box_type,
					'is_default' => $box->is_default,
					'created_at' => $box->created_at ? (string) $box->created_at : null,
				);
			}

			return array( 'mailboxes' => $items, 'total' => count( $items ) );
		},
	) );

	$reg->read( 'fluent-support/get-mailbox', array(
		'label'       => 'Get Support Mailbox',
		'description' => 'Get a single support mailbox by ID.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Mailbox ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'           => array( 'type' => 'integer' ),
			'name'         => array( 'type' => 'string' ),
			'slug'         => array( 'type' => 'string' ),
			'email'        => array( 'type' => 'string' ),
			'mapped_email' => array( 'type' => array( 'string', 'null' ) ),
			'box_type'     => array( 'type' => array( 'string', 'null' ) ),
			'is_default'   => array( 'type' => array( 'string', 'null' ) ),
			'email_footer' => array( 'type' => array( 'string', 'null' ) ),
			'settings'     => array( 'type' => array( 'object', 'null' ) ),
			'created_at'   => array( 'type' => array( 'string', 'null' ) ),
			'updated_at'   => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function ( $input ) {
			$box = wpFluent()->table( 'fs_mail_boxes' )
				->where( 'id', (int) $input['id'] )
				->first();

			if ( ! $box ) {
				return fluent_abilities_error( 'not_found', 'Mailbox not found' );
			}

			return array(
				'id'           => (int) $box->id,
				'name'         => $box->name,
				'slug'         => $box->slug,
				'email'        => $box->email,
				'mapped_email' => $box->mapped_email,
				'box_type'     => $box->box_type,
				'is_default'   => $box->is_default,
				'email_footer' => $box->email_footer,
				'settings'     => $box->settings ? fluent_abilities_safe_array( maybe_unserialize( $box->settings ) ) : null,
				'created_at'   => $box->created_at ? (string) $box->created_at : null,
				'updated_at'   => $box->updated_at ? (string) $box->updated_at : null,
			);
		},
	) );

	// =========================================================================
	// SAVED REPLIES (P1)
	// =========================================================================

	$reg->read( 'fluent-support/list-saved-replies', array(
		'label'       => 'List Saved Replies',
		'description' => 'List saved reply templates for support tickets.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => fluent_abilities_pagination_schema(),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'replies', array(
			'id'         => array( 'type' => 'integer' ),
			'title'      => array( 'type' => array( 'string', 'null' ) ),
			'product_id' => array( 'type' => array( 'integer', 'null' ) ),
			'created_by' => array( 'type' => array( 'integer', 'null' ) ),
			'created_at' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function ( $input ) {
			$pagination = fluent_abilities_pagination( $input );

			$query = wpFluent()->table( 'fs_saved_replies' )
				->orderBy( 'id', 'DESC' );

			$total   = $query->count();
			$replies = $query->offset( $pagination['offset'] )
				->limit( $pagination['per_page'] )
				->get();

			$items = array();
			foreach ( $replies as $reply ) {
				$items[] = array(
					'id'         => (int) $reply->id,
					'title'      => $reply->title,
					'product_id' => $reply->product_id ? (int) $reply->product_id : null,
					'created_by' => $reply->created_by ? (int) $reply->created_by : null,
					'created_at' => $reply->created_at ? (string) $reply->created_at : null,
				);
			}

			return array(
				'replies'  => $items,
				'total'    => $total,
				'page'     => $pagination['page'],
				'per_page' => $pagination['per_page'],
			);
		},
	) );

	$reg->read( 'fluent-support/get-saved-reply', array(
		'label'       => 'Get Saved Reply',
		'description' => 'Get a single saved reply template by ID with full content.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Saved reply ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'         => array( 'type' => 'integer' ),
			'title'      => array( 'type' => array( 'string', 'null' ) ),
			'content'    => array( 'type' => array( 'string', 'null' ) ),
			'product_id' => array( 'type' => array( 'integer', 'null' ) ),
			'mailbox_id' => array( 'type' => array( 'integer', 'null' ) ),
			'created_by' => array( 'type' => array( 'integer', 'null' ) ),
			'created_at' => array( 'type' => array( 'string', 'null' ) ),
			'updated_at' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function ( $input ) {
			$reply = wpFluent()->table( 'fs_saved_replies' )
				->where( 'id', (int) $input['id'] )
				->first();

			if ( ! $reply ) {
				return fluent_abilities_error( 'not_found', 'Saved reply not found' );
			}

			return array(
				'id'         => (int) $reply->id,
				'title'      => $reply->title,
				'content'    => $reply->content,
				'product_id' => $reply->product_id ? (int) $reply->product_id : null,
				'mailbox_id' => $reply->mailbox_id ? (int) $reply->mailbox_id : null,
				'created_by' => $reply->created_by ? (int) $reply->created_by : null,
				'created_at' => $reply->created_at ? (string) $reply->created_at : null,
				'updated_at' => $reply->updated_at ? (string) $reply->updated_at : null,
			);
		},
	) );

	$count = 20;
	error_log( "Abilities for Fluent: Registered {$count} Fluent Support abilities (this file)" );

}, 100 );
