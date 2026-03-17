<?php
/**
 * Fluent Support — Person Abilities (Agents + Customers)
 *
 * 7 abilities: get/create/update/delete customer, get/create/update/delete agent.
 * Shared fs_persons table — always filter by person_type.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function () {

	$reg = new Fluent_Abilities_Registrar( 'support' );

	// =========================================================================
	// CUSTOMERS
	// =========================================================================

	$reg->read( 'fluent-support/get-customer', array(
		'label'       => 'Get Support Customer',
		'description' => 'Get a single support customer by ID with address fields, note, and ticket count.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Customer person ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'             => array( 'type' => 'integer' ),
			'first_name'     => array( 'type' => array( 'string', 'null' ) ),
			'last_name'      => array( 'type' => array( 'string', 'null' ) ),
			'email'          => array( 'type' => array( 'string', 'null' ) ),
			'title'          => array( 'type' => array( 'string', 'null' ) ),
			'status'         => array( 'type' => array( 'string', 'null' ) ),
			'address_line_1' => array( 'type' => array( 'string', 'null' ) ),
			'address_line_2' => array( 'type' => array( 'string', 'null' ) ),
			'city'           => array( 'type' => array( 'string', 'null' ) ),
			'state'          => array( 'type' => array( 'string', 'null' ) ),
			'zip'            => array( 'type' => array( 'string', 'null' ) ),
			'country'        => array( 'type' => array( 'string', 'null' ) ),
			'note'           => array( 'type' => array( 'string', 'null' ) ),
			'user_id'        => array( 'type' => array( 'integer', 'null' ) ),
			'ticket_count'   => array( 'type' => 'integer' ),
			'created_at'     => array( 'type' => array( 'string', 'null' ) ),
			'updated_at'     => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function ( $input ) {
			$customer = wpFluent()->table( 'fs_persons' )
				->where( 'id', (int) $input['id'] )
				->where( 'person_type', 'customer' )
				->first();

			if ( ! $customer ) {
				return fluent_abilities_error( 'not_found', 'Customer not found' );
			}

			$ticket_count = wpFluent()->table( 'fs_tickets' )
				->where( 'customer_id', (int) $customer->id )
				->count();

			return array(
				'id'             => (int) $customer->id,
				'first_name'     => $customer->first_name,
				'last_name'      => $customer->last_name,
				'email'          => $customer->email,
				'title'          => $customer->title,
				'status'         => $customer->status,
				'address_line_1' => $customer->address_line_1,
				'address_line_2' => $customer->address_line_2,
				'city'           => $customer->city,
				'state'          => $customer->state,
				'zip'            => $customer->zip,
				'country'        => $customer->country,
				'note'           => $customer->note,
				'user_id'        => $customer->user_id ? (int) $customer->user_id : null,
				'ticket_count'   => $ticket_count,
				'created_at'     => $customer->created_at ? (string) $customer->created_at : null,
				'updated_at'     => $customer->updated_at ? (string) $customer->updated_at : null,
			);
		},
	) );

	$reg->write( 'fluent-support/create-customer', array(
		'label'       => 'Create Support Customer',
		'description' => 'Create a new support customer. Does not create a WordPress user by default.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'email' ),
			'properties' => array(
				'email'          => array( 'type' => 'string', 'description' => 'Customer email (required, must be unique)' ),
				'first_name'     => array( 'type' => 'string', 'description' => 'First name' ),
				'last_name'      => array( 'type' => 'string', 'description' => 'Last name' ),
				'title'          => array( 'type' => 'string', 'description' => 'Title/role' ),
				'address_line_1' => array( 'type' => 'string', 'description' => 'Address line 1' ),
				'address_line_2' => array( 'type' => 'string', 'description' => 'Address line 2' ),
				'city'           => array( 'type' => 'string', 'description' => 'City' ),
				'state'          => array( 'type' => 'string', 'description' => 'State' ),
				'zip'            => array( 'type' => 'string', 'description' => 'ZIP/postal code' ),
				'country'        => array( 'type' => 'string', 'description' => 'Country' ),
				'note'           => array( 'type' => 'string', 'description' => 'Internal note about this customer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'    => array( 'type' => 'integer' ),
			'email' => array( 'type' => 'string' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function ( $input ) {
			$email = sanitize_email( $input['email'] ?? '' );
			if ( ! is_email( $email ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Invalid email address' );
			}

			$data = array(
				'email'          => $email,
				'first_name'     => sanitize_text_field( $input['first_name'] ?? '' ),
				'last_name'      => sanitize_text_field( $input['last_name'] ?? '' ),
				'title'          => sanitize_text_field( $input['title'] ?? '' ),
				'address_line_1' => sanitize_text_field( $input['address_line_1'] ?? '' ),
				'address_line_2' => sanitize_text_field( $input['address_line_2'] ?? '' ),
				'city'           => sanitize_text_field( $input['city'] ?? '' ),
				'state'          => sanitize_text_field( $input['state'] ?? '' ),
				'zip'            => sanitize_text_field( $input['zip'] ?? '' ),
				'country'        => sanitize_text_field( $input['country'] ?? '' ),
				'note'           => sanitize_textarea_field( $input['note'] ?? '' ),
				'person_type'    => 'customer',
				'status'         => 'active',
			);

			$result = FluentSupportApi( 'customers' )->createCustomerWithOrWithoutWpUser( $data, false );

			if ( ! $result ) {
				return fluent_abilities_error( 'creation_failed', 'Customer creation failed — email may already exist' );
			}

			return array(
				'success' => true,
				'id'      => (int) $result->id,
				'email'   => $result->email,
			);
		},
	) );

	$reg->write( 'fluent-support/update-customer', array(
		'label'       => 'Update Support Customer',
		'description' => 'Update a support customer. Only provided fields are changed.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'             => array( 'type' => 'integer', 'description' => 'Customer person ID' ),
				'first_name'     => array( 'type' => 'string', 'description' => 'First name' ),
				'last_name'      => array( 'type' => 'string', 'description' => 'Last name' ),
				'email'          => array( 'type' => 'string', 'description' => 'Email address' ),
				'title'          => array( 'type' => 'string', 'description' => 'Title/role' ),
				'status'         => array( 'type' => 'string', 'description' => 'Status: active or inactive' ),
				'address_line_1' => array( 'type' => 'string', 'description' => 'Address line 1' ),
				'address_line_2' => array( 'type' => 'string', 'description' => 'Address line 2' ),
				'city'           => array( 'type' => 'string', 'description' => 'City' ),
				'state'          => array( 'type' => 'string', 'description' => 'State' ),
				'zip'            => array( 'type' => 'string', 'description' => 'ZIP/postal code' ),
				'country'        => array( 'type' => 'string', 'description' => 'Country' ),
				'note'           => array( 'type' => 'string', 'description' => 'Internal note' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function ( $input ) {
			$id = (int) $input['id'];

			// Verify it's a customer.
			$customer = wpFluent()->table( 'fs_persons' )
				->where( 'id', $id )
				->where( 'person_type', 'customer' )
				->first();

			if ( ! $customer ) {
				return fluent_abilities_error( 'not_found', 'Customer not found' );
			}

			$data    = array();
			$updated = array();
			$fields  = array(
				'first_name'     => 'sanitize_text_field',
				'last_name'      => 'sanitize_text_field',
				'title'          => 'sanitize_text_field',
				'status'         => 'sanitize_text_field',
				'address_line_1' => 'sanitize_text_field',
				'address_line_2' => 'sanitize_text_field',
				'city'           => 'sanitize_text_field',
				'state'          => 'sanitize_text_field',
				'zip'            => 'sanitize_text_field',
				'country'        => 'sanitize_text_field',
				'note'           => 'sanitize_textarea_field',
			);

			foreach ( $fields as $field => $sanitizer ) {
				if ( isset( $input[ $field ] ) ) {
					$data[ $field ] = $sanitizer( $input[ $field ] );
					$updated[]      = $field;
				}
			}

			if ( isset( $input['email'] ) ) {
				$email = sanitize_email( $input['email'] );
				if ( ! is_email( $email ) ) {
					return fluent_abilities_error( 'ability_invalid_input', 'Invalid email address' );
				}
				$data['email'] = $email;
				$updated[]     = 'email';
			}

			if ( empty( $data ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'No fields provided to update' );
			}

			$data['updated_at'] = current_time( 'mysql' );

			FluentSupportApi( 'customers' )->updateCustomer( $data, $id );

			return array(
				'success' => true,
				'id'      => $id,
				'updated' => $updated,
			);
		},
	) );

	$reg->delete( 'fluent-support/delete-customer', array(
		'label'       => 'Delete Support Customer',
		'description' => 'Permanently delete a support customer. Optionally delete all associated tickets.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'             => array( 'type' => 'integer', 'description' => 'Customer person ID' ),
				'delete_tickets' => array( 'type' => 'boolean', 'description' => 'Also delete all tickets by this customer (default: false)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function ( $input ) {
			$id = (int) $input['id'];

			// Verify it's a customer.
			$customer = wpFluent()->table( 'fs_persons' )
				->where( 'id', $id )
				->where( 'person_type', 'customer' )
				->first();

			if ( ! $customer ) {
				return fluent_abilities_error( 'not_found', 'Customer not found' );
			}

			$delete_tickets = ! empty( $input['delete_tickets'] );

			FluentSupportApi( 'customers' )->deleteCustomer( $id, $delete_tickets );

			return array(
				'success' => true,
				'id'      => $id,
			);
		},
	) );

	// =========================================================================
	// AGENTS
	// =========================================================================

	$reg->read( 'fluent-support/get-agent', array(
		'label'       => 'Get Support Agent',
		'description' => 'Get a single support agent by ID with permissions and ticket counts.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Agent person ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'            => array( 'type' => 'integer' ),
			'first_name'    => array( 'type' => array( 'string', 'null' ) ),
			'last_name'     => array( 'type' => array( 'string', 'null' ) ),
			'email'         => array( 'type' => array( 'string', 'null' ) ),
			'title'         => array( 'type' => array( 'string', 'null' ) ),
			'status'        => array( 'type' => array( 'string', 'null' ) ),
			'user_id'       => array( 'type' => array( 'integer', 'null' ) ),
			'open_tickets'  => array( 'type' => 'integer' ),
			'total_tickets' => array( 'type' => 'integer' ),
			'created_at'    => array( 'type' => array( 'string', 'null' ) ),
			'updated_at'    => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function ( $input ) {
			$agent = wpFluent()->table( 'fs_persons' )
				->where( 'id', (int) $input['id'] )
				->where( 'person_type', 'agent' )
				->first();

			if ( ! $agent ) {
				return fluent_abilities_error( 'not_found', 'Agent not found' );
			}

			$open_tickets = wpFluent()->table( 'fs_tickets' )
				->where( 'agent_id', (int) $agent->id )
				->whereIn( 'status', array( 'new', 'active' ) )
				->count();

			$total_tickets = wpFluent()->table( 'fs_tickets' )
				->where( 'agent_id', (int) $agent->id )
				->count();

			return array(
				'id'            => (int) $agent->id,
				'first_name'    => $agent->first_name,
				'last_name'     => $agent->last_name,
				'email'         => $agent->email,
				'title'         => $agent->title,
				'status'        => $agent->status,
				'user_id'       => $agent->user_id ? (int) $agent->user_id : null,
				'open_tickets'  => $open_tickets,
				'total_tickets' => $total_tickets,
				'created_at'    => $agent->created_at ? (string) $agent->created_at : null,
				'updated_at'    => $agent->updated_at ? (string) $agent->updated_at : null,
			);
		},
	) );

	$reg->write( 'fluent-support/create-agent', array(
		'label'       => 'Create Support Agent',
		'description' => 'Create a new support agent. The email must belong to an existing WordPress user or the agent will be created without WP user linkage.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'email' ),
			'properties' => array(
				'email'      => array( 'type' => 'string', 'description' => 'Agent email (required, must be unique among agents)' ),
				'first_name' => array( 'type' => 'string', 'description' => 'First name' ),
				'last_name'  => array( 'type' => 'string', 'description' => 'Last name' ),
				'title'      => array( 'type' => 'string', 'description' => 'Title/role' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'    => array( 'type' => 'integer' ),
			'email' => array( 'type' => 'string' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function ( $input ) {
			$email = sanitize_email( $input['email'] ?? '' );
			if ( ! is_email( $email ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Invalid email address' );
			}

			$data = array(
				'email'       => $email,
				'first_name'  => sanitize_text_field( $input['first_name'] ?? '' ),
				'last_name'   => sanitize_text_field( $input['last_name'] ?? '' ),
				'title'       => sanitize_text_field( $input['title'] ?? '' ),
				'person_type' => 'agent',
				'status'      => 'active',
			);

			$result = FluentSupportApi( 'agents' )->createAgentWithOrWithoutWpUser( $data, false );

			if ( ! $result ) {
				return fluent_abilities_error( 'creation_failed', 'Agent creation failed — email may already exist as an agent' );
			}

			return array(
				'success' => true,
				'id'      => (int) $result->id,
				'email'   => $result->email,
			);
		},
	) );

	$reg->write( 'fluent-support/update-agent', array(
		'label'       => 'Update Support Agent',
		'description' => 'Update a support agent. Only provided fields are changed.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'         => array( 'type' => 'integer', 'description' => 'Agent person ID' ),
				'first_name' => array( 'type' => 'string', 'description' => 'First name' ),
				'last_name'  => array( 'type' => 'string', 'description' => 'Last name' ),
				'title'      => array( 'type' => 'string', 'description' => 'Title/role' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function ( $input ) {
			$id = (int) $input['id'];

			// Verify it's an agent.
			$agent = wpFluent()->table( 'fs_persons' )
				->where( 'id', $id )
				->where( 'person_type', 'agent' )
				->first();

			if ( ! $agent ) {
				return fluent_abilities_error( 'not_found', 'Agent not found' );
			}

			$data    = array();
			$updated = array();
			$fields  = array( 'first_name', 'last_name', 'title' );

			foreach ( $fields as $field ) {
				if ( isset( $input[ $field ] ) ) {
					$data[ $field ] = sanitize_text_field( $input[ $field ] );
					$updated[]      = $field;
				}
			}

			if ( empty( $data ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'No fields provided to update' );
			}

			$data['updated_at'] = current_time( 'mysql' );

			FluentSupportApi( 'agents' )->updateAgent( $data, $id );

			return array(
				'success' => true,
				'id'      => $id,
				'updated' => $updated,
			);
		},
	) );

	$reg->delete( 'fluent-support/delete-agent', array(
		'label'       => 'Delete Support Agent',
		'description' => 'Permanently delete a support agent. Their tickets will become unassigned.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Agent person ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function ( $input ) {
			$id = (int) $input['id'];

			// Verify it's an agent.
			$agent = wpFluent()->table( 'fs_persons' )
				->where( 'id', $id )
				->where( 'person_type', 'agent' )
				->first();

			if ( ! $agent ) {
				return fluent_abilities_error( 'not_found', 'Agent not found' );
			}

			FluentSupportApi( 'agents' )->deleteAgent( $id );

			return array(
				'success' => true,
				'id'      => $id,
			);
		},
	) );

}, 100 );
