<?php
/**
 * FluentCart Abilities — Customer CRUD, WP-user Linkage, Address Book (v2.0.0)
 *
 * Adds clusters 4.4, 4.5, 4.6 from FluentCart Ability Registrar Research
 * v1.0 (2026-05-13) — 13 abilities total.
 *
 * Avoids KD-2 patterns: fct_customers exposes purchase_count + ltv + aov as
 * the canonical aggregate columns. user_id FK targets wp_users.
 *
 * @package Fluent_Abilities
 * @since 2.0.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'cart' );

	// =========================================================================
	// 4.4 CUSTOMER CRUD BEYOND UPDATE (5)
	// =========================================================================

	$reg->write( 'fluent-cart/create-customer', array(
		'label'       => 'Create Customer',
		'description' => 'Create a new FluentCart customer record (fct_customers).',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'email' ),
			'properties' => array(
				'email'      => array( 'type' => 'string' ),
				'first_name' => array( 'type' => 'string' ),
				'last_name'  => array( 'type' => 'string' ),
				'user_id'    => array( 'type' => 'integer', 'description' => 'Optional WP user ID to link' ),
				'contact_id' => array( 'type' => 'integer', 'description' => 'Optional FluentCRM contact ID' ),
				'country'    => array( 'type' => 'string' ),
				'city'       => array( 'type' => 'string' ),
				'state'      => array( 'type' => 'string' ),
				'postcode'   => array( 'type' => 'string' ),
				'status'     => array( 'type' => 'string', 'description' => 'Default: active' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'    => array( 'type' => 'integer' ),
			'email' => array( 'type' => 'string' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback'    => function( $input ) {
			$email = sanitize_email( $input['email'] );
			if ( ! $email ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Valid email required.' );
			}
			$data = array(
				'email'      => $email,
				'first_name' => sanitize_text_field( $input['first_name'] ?? '' ),
				'last_name'  => sanitize_text_field( $input['last_name'] ?? '' ),
				'status'     => sanitize_text_field( $input['status'] ?? 'active' ),
			);
			foreach ( array( 'user_id', 'contact_id' ) as $f ) {
				if ( isset( $input[ $f ] ) ) {
					$data[ $f ] = (int) $input[ $f ];
				}
			}
			foreach ( array( 'country', 'city', 'state', 'postcode' ) as $f ) {
				if ( isset( $input[ $f ] ) ) {
					$data[ $f ] = sanitize_text_field( $input[ $f ] );
				}
			}
			$customer = \FluentCart\App\Models\Customer::create( $data );
			return array( 'success' => true, 'id' => (int) $customer->id, 'email' => (string) $customer->email );
		},
	) );

	$reg->delete( 'fluent-cart/delete-customer', array(
		'label'       => 'Delete Customer',
		'description' => 'Delete a FluentCart customer record. Cascades to fct_customer_addresses and fct_customer_meta; orders retain customer_id reference and remain.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback'    => function( $input ) {
			$customer = \FluentCart\App\Models\Customer::find( (int) $input['id'] );
			if ( ! $customer ) {
				return fluent_abilities_error( 'not_found', 'Customer not found.' );
			}
			$id = (int) $customer->id;
			\FluentCart\App\Models\CustomerAddresses::where( 'customer_id', $id )->delete();
			$customer->delete();
			return array( 'success' => true, 'id' => $id );
		},
	) );

	$reg->read( 'fluent-cart/get-customer-stats', array(
		'label'       => 'Get Customer Stats',
		'description' => 'Aggregate purchase stats (purchase_count, ltv, aov) for one customer. Mirrors GET /customers/get-stats/{customer}.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'                  => array( 'type' => 'integer' ),
			'purchase_count'      => array( 'type' => 'integer' ),
			'ltv'                 => array( 'type' => 'number' ),
			'aov'                 => array( 'type' => 'number' ),
			'first_purchase_date' => array( 'type' => array( 'string', 'null' ) ),
			'last_purchase_date'  => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$customer = \FluentCart\App\Models\Customer::find( (int) $input['id'] );
			if ( ! $customer ) {
				return fluent_abilities_error( 'not_found', 'Customer not found.' );
			}
			return array(
				'id'                  => (int) $customer->id,
				'purchase_count'      => (int) ( $customer->purchase_count ?? 0 ),
				'ltv'                 => fluent_cart_format_money( $customer->ltv ),
				'aov'                 => fluent_cart_format_money( $customer->aov ),
				'first_purchase_date' => $customer->first_purchase_date ?? null,
				'last_purchase_date'  => $customer->last_purchase_date ?? null,
			);
		},
	) );

	$reg->write( 'fluent-cart/recalculate-customer-ltv', array(
		'label'       => 'Recalculate Customer LTV',
		'description' => 'Recompute ltv / purchase_count / aov from order history. Mirrors POST /customers/{customerId}/recalculate-ltv.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'             => array( 'type' => 'integer' ),
			'purchase_count' => array( 'type' => 'integer' ),
			'ltv'            => array( 'type' => 'number' ),
			'aov'            => array( 'type' => 'number' ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$customer = \FluentCart\App\Models\Customer::find( (int) $input['id'] );
			if ( ! $customer ) {
				return fluent_abilities_error( 'not_found', 'Customer not found.' );
			}
			$orders = \FluentCart\App\Models\Order::where( 'customer_id', $customer->id )
				->where( 'payment_status', 'paid' )->get();
			$count = $orders->count();
			$ltv   = 0;
			foreach ( $orders as $order ) {
				$ltv += (int) ( $order->total_amount ?? 0 );
			}
			$aov = $count > 0 ? intdiv( $ltv, $count ) : 0;
			$customer->purchase_count = $count;
			$customer->ltv            = $ltv;
			$customer->aov            = $aov;
			$customer->save();
			return array(
				'success'        => true,
				'id'             => (int) $customer->id,
				'purchase_count' => $count,
				'ltv'            => fluent_cart_format_money( $ltv ),
				'aov'            => fluent_cart_format_money( $aov ),
			);
		},
	) );

	$reg->read( 'fluent-cart/list-customer-orders', array(
		'label'       => 'List Customer Orders',
		'description' => 'List orders for a specific customer. Mirrors GET /customers/{customerId}/orders.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'customer_id' ),
			'properties' => array_merge( array(
				'customer_id' => array( 'type' => 'integer' ),
				'status'      => array( 'type' => 'string' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'orders', array(
			'id'             => array( 'type' => 'integer' ),
			'uuid'           => array( 'type' => array( 'string', 'null' ) ),
			'status'         => array( 'type' => 'string' ),
			'payment_status' => array( 'type' => array( 'string', 'null' ) ),
			'total_amount'   => array( 'type' => 'number' ),
			'currency'       => array( 'type' => array( 'string', 'null' ) ),
			'created_at'     => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$query = \FluentCart\App\Models\Order::where( 'customer_id', (int) $input['customer_id'] );
			if ( ! empty( $input['status'] ) ) {
				$query->where( 'status', sanitize_text_field( $input['status'] ) );
			}
			$total = $query->count();
			$rows  = $query->orderBy( 'id', 'DESC' )
				->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();
			$items = array();
			foreach ( $rows as $o ) {
				$items[] = array(
					'id'             => (int) $o->id,
					'uuid'           => $o->uuid ?? null,
					'status'         => (string) ( $o->status ?? '' ),
					'payment_status' => $o->payment_status ?? null,
					'total_amount'   => fluent_cart_format_money( $o->total_amount ),
					'currency'       => $o->currency ?? null,
					'created_at'     => $o->created_at ? (string) $o->created_at : null,
				);
			}
			return array(
				'orders'   => $items,
				'total'    => $total,
				'page'     => $pagination['page'],
				'per_page' => $pagination['per_page'],
			);
		},
	) );

	// =========================================================================
	// 4.5 CUSTOMER ↔ WP-USER LINKAGE (3)
	// =========================================================================

	$reg->read( 'fluent-cart/list-attachable-users', array(
		'label'       => 'List Attachable WP Users',
		'description' => 'List WP users that are not yet linked to a FluentCart customer (fct_customers.user_id is unique). Mirrors GET /customers/attachable-user.',
		'input_schema' => array(
			'type'     => 'object',
			'properties' => array_merge( array(
				'search' => array( 'type' => 'string', 'description' => 'Search user_email or display_name' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'users', array(
			'id'           => array( 'type' => 'integer' ),
			'email'        => array( 'type' => 'string' ),
			'display_name' => array( 'type' => 'string' ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$linked_ids = \FluentCart\App\Models\Customer::whereNotNull( 'user_id' )
				->pluck( 'user_id' )->all();
			$args = array(
				'exclude' => $linked_ids,
				'number'  => $pagination['per_page'],
				'offset'  => $pagination['offset'],
				'fields'  => array( 'ID', 'user_email', 'display_name' ),
			);
			if ( ! empty( $input['search'] ) ) {
				$args['search']         = '*' . sanitize_text_field( $input['search'] ) . '*';
				$args['search_columns'] = array( 'user_email', 'display_name', 'user_login' );
			}
			$users = get_users( $args );

			$count_args = array( 'exclude' => $linked_ids, 'fields' => 'ID', 'number' => -1 );
			$total      = count( get_users( $count_args ) );

			$items = array();
			foreach ( $users as $u ) {
				$items[] = array(
					'id'           => (int) $u->ID,
					'email'        => (string) $u->user_email,
					'display_name' => (string) $u->display_name,
				);
			}
			return array(
				'users'    => $items,
				'total'    => $total,
				'page'     => $pagination['page'],
				'per_page' => $pagination['per_page'],
			);
		},
	) );

	$reg->write( 'fluent-cart/attach-wp-user-to-customer', array(
		'label'       => 'Attach WP User To Customer',
		'description' => 'Link a FluentCart customer to a WP user record. Mirrors POST /customers/{customerId}/attachable-user.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'customer_id', 'user_id' ),
			'properties' => array(
				'customer_id' => array( 'type' => 'integer' ),
				'user_id'     => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'customer_id' => array( 'type' => 'integer' ),
			'user_id'     => array( 'type' => 'integer' ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$customer = \FluentCart\App\Models\Customer::find( (int) $input['customer_id'] );
			if ( ! $customer ) {
				return fluent_abilities_error( 'not_found', 'Customer not found.' );
			}
			$user_id = (int) $input['user_id'];
			$user    = get_userdata( $user_id );
			if ( ! $user ) {
				return fluent_abilities_error( 'not_found', 'WP user not found.' );
			}
			$customer->user_id = $user_id;
			$customer->save();
			return array( 'success' => true, 'customer_id' => (int) $customer->id, 'user_id' => $user_id );
		},
	) );

	$reg->write( 'fluent-cart/detach-wp-user-from-customer', array(
		'label'       => 'Detach WP User From Customer',
		'description' => 'Clear the user_id link on a FluentCart customer. Mirrors POST /customers/{customerId}/detach-user.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'customer_id' ),
			'properties' => array(
				'customer_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'customer_id' => array( 'type' => 'integer' ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$customer = \FluentCart\App\Models\Customer::find( (int) $input['customer_id'] );
			if ( ! $customer ) {
				return fluent_abilities_error( 'not_found', 'Customer not found.' );
			}
			$customer->user_id = null;
			$customer->save();
			return array( 'success' => true, 'customer_id' => (int) $customer->id );
		},
	) );

	// =========================================================================
	// 4.6 CUSTOMER ADDRESS BOOK CRUD (5)
	// =========================================================================

	$reg->write( 'fluent-cart/create-customer-address', array(
		'label'       => 'Create Customer Address',
		'description' => 'Add an address to a customer address book (fct_customer_addresses). Mirrors POST /customers/{customerId}/address.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'customer_id' ),
			'properties' => array(
				'customer_id' => array( 'type' => 'integer' ),
				'label'       => array( 'type' => 'string' ),
				'is_primary'  => array( 'type' => 'boolean' ),
				'type'        => array( 'type' => 'string', 'description' => 'billing | shipping' ),
				'name'        => array( 'type' => 'string' ),
				'address_1'   => array( 'type' => 'string' ),
				'address_2'   => array( 'type' => 'string' ),
				'city'        => array( 'type' => 'string' ),
				'state'       => array( 'type' => 'string' ),
				'postcode'    => array( 'type' => 'string' ),
				'country'     => array( 'type' => 'string' ),
				'phone'       => array( 'type' => 'string' ),
				'email'       => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback'    => function( $input ) {
			$customer = \FluentCart\App\Models\Customer::find( (int) $input['customer_id'] );
			if ( ! $customer ) {
				return fluent_abilities_error( 'not_found', 'Customer not found.' );
			}
			$data = array(
				'customer_id' => (int) $customer->id,
				'is_primary'  => ! empty( $input['is_primary'] ) ? 1 : 0,
			);
			foreach ( array( 'label', 'type', 'name', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone' ) as $f ) {
				if ( isset( $input[ $f ] ) ) {
					$data[ $f ] = sanitize_text_field( $input[ $f ] );
				}
			}
			if ( isset( $input['email'] ) ) {
				$data['email'] = sanitize_email( $input['email'] );
			}
			$address = \FluentCart\App\Models\CustomerAddresses::create( $data );
			return array( 'success' => true, 'id' => (int) $address->id );
		},
	) );

	$reg->write( 'fluent-cart/update-customer-address', array(
		'label'       => 'Update Customer Address',
		'description' => 'Update an address in a customer address book. Mirrors PUT /customers/{customerId}/address.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'id' ),
			'properties' => array(
				'id'         => array( 'type' => 'integer' ),
				'label'      => array( 'type' => 'string' ),
				'is_primary' => array( 'type' => 'boolean' ),
				'type'       => array( 'type' => 'string' ),
				'name'       => array( 'type' => 'string' ),
				'address_1'  => array( 'type' => 'string' ),
				'address_2'  => array( 'type' => 'string' ),
				'city'       => array( 'type' => 'string' ),
				'state'      => array( 'type' => 'string' ),
				'postcode'   => array( 'type' => 'string' ),
				'country'    => array( 'type' => 'string' ),
				'phone'      => array( 'type' => 'string' ),
				'email'      => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$address = \FluentCart\App\Models\CustomerAddresses::find( (int) $input['id'] );
			if ( ! $address ) {
				return fluent_abilities_error( 'not_found', 'Address not found.' );
			}
			if ( isset( $input['is_primary'] ) ) {
				$address->is_primary = ! empty( $input['is_primary'] ) ? 1 : 0;
			}
			foreach ( array( 'label', 'type', 'name', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone' ) as $f ) {
				if ( isset( $input[ $f ] ) ) {
					$address->{$f} = sanitize_text_field( $input[ $f ] );
				}
			}
			if ( isset( $input['email'] ) ) {
				$address->email = sanitize_email( $input['email'] );
			}
			$address->save();
			return array( 'success' => true, 'id' => (int) $address->id );
		},
	) );

	$reg->delete( 'fluent-cart/delete-customer-address', array(
		'label'       => 'Delete Customer Address',
		'description' => 'Delete an address from a customer address book. Mirrors DELETE /customers/{customerId}/address.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback'    => function( $input ) {
			$address = \FluentCart\App\Models\CustomerAddresses::find( (int) $input['id'] );
			if ( ! $address ) {
				return fluent_abilities_error( 'not_found', 'Address not found.' );
			}
			$id = (int) $address->id;
			$address->delete();
			return array( 'success' => true, 'id' => $id );
		},
	) );

	$reg->write( 'fluent-cart/make-address-primary', array(
		'label'       => 'Make Address Primary',
		'description' => 'Toggle is_primary=1 on one address; clears the flag on sibling addresses for that customer. Mirrors POST /customers/{customerId}/address/make-primary.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Address ID to mark primary' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'          => array( 'type' => 'integer' ),
			'customer_id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$address = \FluentCart\App\Models\CustomerAddresses::find( (int) $input['id'] );
			if ( ! $address ) {
				return fluent_abilities_error( 'not_found', 'Address not found.' );
			}
			\FluentCart\App\Models\CustomerAddresses::where( 'customer_id', $address->customer_id )
				->where( 'id', '!=', $address->id )
				->update( array( 'is_primary' => 0 ) );
			$address->is_primary = 1;
			$address->save();
			return array(
				'success'     => true,
				'id'          => (int) $address->id,
				'customer_id' => (int) $address->customer_id,
			);
		},
	) );

	$reg->read( 'fluent-cart/get-customer-address', array(
		'label'       => 'Get Customer Address',
		'description' => 'Get a single customer address by ID. Mirrors GET /customers/{customerId}/address.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'          => array( 'type' => 'integer' ),
			'customer_id' => array( 'type' => 'integer' ),
			'label'       => array( 'type' => array( 'string', 'null' ) ),
			'is_primary'  => array( 'type' => 'integer' ),
			'type'        => array( 'type' => array( 'string', 'null' ) ),
			'name'        => array( 'type' => array( 'string', 'null' ) ),
			'address_1'   => array( 'type' => array( 'string', 'null' ) ),
			'address_2'   => array( 'type' => array( 'string', 'null' ) ),
			'city'        => array( 'type' => array( 'string', 'null' ) ),
			'state'       => array( 'type' => array( 'string', 'null' ) ),
			'postcode'    => array( 'type' => array( 'string', 'null' ) ),
			'country'     => array( 'type' => array( 'string', 'null' ) ),
			'phone'       => array( 'type' => array( 'string', 'null' ) ),
			'email'       => array( 'type' => array( 'string', 'null' ) ),
			'created_at'  => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$a = \FluentCart\App\Models\CustomerAddresses::find( (int) $input['id'] );
			if ( ! $a ) {
				return fluent_abilities_error( 'not_found', 'Address not found.' );
			}
			return array(
				'id'          => (int) $a->id,
				'customer_id' => (int) $a->customer_id,
				'label'       => $a->label ?? null,
				'is_primary'  => (int) ( $a->is_primary ?? 0 ),
				'type'        => $a->type ?? null,
				'name'        => $a->name ?? null,
				'address_1'   => $a->address_1 ?? null,
				'address_2'   => $a->address_2 ?? null,
				'city'        => $a->city ?? null,
				'state'       => $a->state ?? null,
				'postcode'    => $a->postcode ?? null,
				'country'     => $a->country ?? null,
				'phone'       => $a->phone ?? null,
				'email'       => $a->email ?? null,
				'created_at'  => $a->created_at ? (string) $a->created_at : null,
			);
		},
	) );

	$count = 13;
	error_log( "Abilities for Fluent: Registered {$count} Cart Customer Extended abilities" );

}, 100 );
