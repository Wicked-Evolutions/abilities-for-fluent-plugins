<?php
/**
 * FluentCart Abilities — Order Management (v2.0.0)
 *
 * Order creation, line-item / transaction CRUD, addresses, customer linkage.
 * Adds clusters 4.1, 4.2, 4.3 from FluentCart Ability Registrar Research
 * v1.0 (2026-05-13) — 14 abilities total.
 *
 * Avoids KD-2 patterns: monetary columns named `total_amount` (fct_orders)
 * and `total` (fct_order_transactions); no `customer_email` column on orders.
 *
 * @package Fluent_Abilities
 * @since 2.0.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'cart' );

	// =========================================================================
	// 4.1 ORDER CREATION & MANUAL ORDERS (4)
	// =========================================================================

	$reg->write( 'fluent-cart/create-order', array(
		'label'       => 'Create Order',
		'description' => 'Create a new FluentCart order with line items. Money values in cents.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'customer_id', 'currency' ),
			'properties' => array(
				'customer_id'    => array( 'type' => 'integer', 'description' => 'FluentCart customer ID (fct_customers.id)' ),
				'currency'       => array( 'type' => 'string', 'description' => 'ISO 4217 currency code (e.g. USD, EUR)' ),
				'payment_method' => array( 'type' => 'string', 'description' => 'Payment method slug (stripe, paypal, manual, ...)' ),
				'status'         => array( 'type' => 'string', 'description' => 'Initial order status (default: draft)' ),
				'subtotal'       => array( 'type' => 'integer', 'description' => 'Subtotal in cents' ),
				'total_amount'   => array( 'type' => 'integer', 'description' => 'Order total in cents (fct_orders.total_amount)' ),
				'items'          => array(
					'type'        => 'array',
					'description' => 'Line items',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'object_id'   => array( 'type' => 'integer' ),
							'object_type' => array( 'type' => 'string' ),
							'quantity'    => array( 'type' => 'integer' ),
							'unit_price'  => array( 'type' => 'integer' ),
							'line_total'  => array( 'type' => 'integer' ),
						),
					),
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'   => array( 'type' => 'integer' ),
			'uuid' => array( 'type' => 'string' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback'    => function( $input ) {
			$data = array(
				'customer_id'    => (int) $input['customer_id'],
				'currency'       => sanitize_text_field( $input['currency'] ),
				'payment_method' => sanitize_text_field( $input['payment_method'] ?? 'manual' ),
				'status'         => sanitize_text_field( $input['status'] ?? 'draft' ),
				'subtotal'       => isset( $input['subtotal'] ) ? (int) $input['subtotal'] : 0,
				'total_amount'   => isset( $input['total_amount'] ) ? (int) $input['total_amount'] : 0,
			);
			$order = \FluentCart\App\Models\Order::create( $data );

			if ( ! empty( $input['items'] ) && is_array( $input['items'] ) ) {
				foreach ( $input['items'] as $item ) {
					\FluentCart\App\Models\OrderItem::create( array(
						'order_id'    => $order->id,
						'object_id'   => (int) ( $item['object_id'] ?? 0 ),
						'object_type' => sanitize_text_field( $item['object_type'] ?? 'variation' ),
						'quantity'    => (int) ( $item['quantity'] ?? 1 ),
						'unit_price'  => (int) ( $item['unit_price'] ?? 0 ),
						'line_total'  => (int) ( $item['line_total'] ?? 0 ),
					) );
				}
			}
			return array( 'success' => true, 'id' => (int) $order->id, 'uuid' => (string) ( $order->uuid ?? '' ) );
		},
	) );

	$reg->write( 'fluent-cart/create-custom-order', array(
		'label'       => 'Create Custom Order',
		'description' => 'Admin-side line-item-driven manual order creation. Mirrors POST /orders/{order}/create-custom.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'customer_id', 'currency', 'items' ),
			'properties' => array(
				'customer_id'    => array( 'type' => 'integer' ),
				'currency'       => array( 'type' => 'string' ),
				'payment_method' => array( 'type' => 'string' ),
				'note'           => array( 'type' => 'string' ),
				'items'          => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'object_id'   => array( 'type' => 'integer' ),
							'object_type' => array( 'type' => 'string' ),
							'quantity'    => array( 'type' => 'integer' ),
							'unit_price'  => array( 'type' => 'integer' ),
						),
					),
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'           => array( 'type' => 'integer' ),
			'total_amount' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'capability'  => 'manage_options',
		'callback'    => function( $input ) {
			$items = $input['items'] ?? array();
			$total = 0;
			foreach ( $items as $item ) {
				$total += ( (int) ( $item['unit_price'] ?? 0 ) ) * ( (int) ( $item['quantity'] ?? 1 ) );
			}
			$order = \FluentCart\App\Models\Order::create( array(
				'customer_id'    => (int) $input['customer_id'],
				'currency'       => sanitize_text_field( $input['currency'] ),
				'payment_method' => sanitize_text_field( $input['payment_method'] ?? 'manual' ),
				'status'         => 'pending',
				'subtotal'       => $total,
				'total_amount'   => $total,
			) );
			foreach ( $items as $item ) {
				\FluentCart\App\Models\OrderItem::create( array(
					'order_id'    => $order->id,
					'object_id'   => (int) ( $item['object_id'] ?? 0 ),
					'object_type' => sanitize_text_field( $item['object_type'] ?? 'variation' ),
					'quantity'    => (int) ( $item['quantity'] ?? 1 ),
					'unit_price'  => (int) ( $item['unit_price'] ?? 0 ),
					'line_total'  => ( (int) ( $item['unit_price'] ?? 0 ) ) * ( (int) ( $item['quantity'] ?? 1 ) ),
				) );
			}
			return array( 'success' => true, 'id' => (int) $order->id, 'total_amount' => (int) $total );
		},
	) );

	$reg->write( 'fluent-cart/mark-order-paid', array(
		'label'       => 'Mark Order Paid',
		'description' => 'Mark a FluentCart order as paid and create a transaction row. Mirrors POST /orders/{order}/mark-as-paid.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'id' ),
			'properties' => array(
				'id'             => array( 'type' => 'integer', 'description' => 'Order ID' ),
				'payment_method' => array( 'type' => 'string', 'description' => 'Payment method recorded for the transaction' ),
				'note'           => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'             => array( 'type' => 'integer' ),
			'transaction_id' => array( 'type' => array( 'integer', 'null' ) ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$order = \FluentCart\App\Models\Order::find( (int) $input['id'] );
			if ( ! $order ) {
				return fluent_abilities_error( 'not_found', 'Order not found.' );
			}
			$order->payment_status = 'paid';
			$order->save();
			$tx = \FluentCart\App\Models\OrderTransaction::create( array(
				'order_id'         => $order->id,
				'transaction_type' => 'charge',
				'total'            => (int) ( $order->total_amount ?? 0 ),
				'status'           => 'paid',
				'payment_method'   => sanitize_text_field( $input['payment_method'] ?? ( $order->payment_method ?? 'manual' ) ),
			) );
			return array( 'success' => true, 'id' => (int) $order->id, 'transaction_id' => $tx ? (int) $tx->id : null );
		},
	) );

	$reg->write( 'fluent-cart/sync-order-statuses', array(
		'label'       => 'Sync Order Statuses',
		'description' => 'Recompute payment_status / shipping_status / order status from current state. Mirrors PUT /orders/{order}/sync-statuses.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Order ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'              => array( 'type' => 'integer' ),
			'status'          => array( 'type' => 'string' ),
			'payment_status'  => array( 'type' => 'string' ),
			'shipping_status' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$order = \FluentCart\App\Models\Order::find( (int) $input['id'] );
			if ( ! $order ) {
				return fluent_abilities_error( 'not_found', 'Order not found.' );
			}
			// Recompute payment_status from transactions.
			$paid = \FluentCart\App\Models\OrderTransaction::where( 'order_id', $order->id )
				->where( 'status', 'paid' )->sum( 'total' );
			$total = (int) ( $order->total_amount ?? 0 );
			if ( $paid >= $total && $total > 0 ) {
				$order->payment_status = 'paid';
			} elseif ( $paid > 0 ) {
				$order->payment_status = 'partial';
			} else {
				$order->payment_status = 'pending';
			}
			$order->save();
			return array(
				'success'         => true,
				'id'              => (int) $order->id,
				'status'          => (string) ( $order->status ?? '' ),
				'payment_status'  => (string) ( $order->payment_status ?? '' ),
				'shipping_status' => $order->shipping_status ?? null,
			);
		},
	) );

	// =========================================================================
	// 4.2 ORDER LINE-ITEM & TRANSACTION CRUD (6)
	// =========================================================================

	$reg->write( 'fluent-cart/update-order-item', array(
		'label'       => 'Update Order Item',
		'description' => 'Update a line item on an order (quantity, unit_price, line_total, fulfilled_quantity).',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'id' ),
			'properties' => array(
				'id'                 => array( 'type' => 'integer', 'description' => 'Order item ID' ),
				'quantity'           => array( 'type' => 'integer' ),
				'unit_price'         => array( 'type' => 'integer', 'description' => 'In cents' ),
				'line_total'         => array( 'type' => 'integer', 'description' => 'In cents' ),
				'tax_amount'         => array( 'type' => 'integer', 'description' => 'In cents' ),
				'discount_total'     => array( 'type' => 'integer', 'description' => 'In cents' ),
				'fulfilled_quantity' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$item = \FluentCart\App\Models\OrderItem::find( (int) $input['id'] );
			if ( ! $item ) {
				return fluent_abilities_error( 'not_found', 'Order item not found.' );
			}
			foreach ( array( 'quantity', 'unit_price', 'line_total', 'tax_amount', 'discount_total', 'fulfilled_quantity' ) as $col ) {
				if ( isset( $input[ $col ] ) ) {
					$item->{$col} = (int) $input[ $col ];
				}
			}
			$item->save();
			return array( 'success' => true, 'id' => (int) $item->id );
		},
	) );

	$reg->delete( 'fluent-cart/delete-order-item', array(
		'label'       => 'Delete Order Item',
		'description' => 'Delete a line item from an order. The parent order total is NOT auto-recalculated; call sync-order-statuses afterward.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Order item ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback'    => function( $input ) {
			$item = \FluentCart\App\Models\OrderItem::find( (int) $input['id'] );
			if ( ! $item ) {
				return fluent_abilities_error( 'not_found', 'Order item not found.' );
			}
			$id = (int) $item->id;
			$item->delete();
			return array( 'success' => true, 'id' => $id );
		},
	) );

	$reg->read( 'fluent-cart/list-order-transactions', array(
		'label'       => 'List Order Transactions',
		'description' => 'List transactions scoped to a single order. fct_order_transactions.total is BIGINT cents.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'order_id' ),
			'properties' => array_merge( array(
				'order_id' => array( 'type' => 'integer', 'description' => 'Parent order ID' ),
				'status'   => array( 'type' => 'string', 'description' => 'Filter by status' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'transactions', array(
			'id'               => array( 'type' => 'integer' ),
			'order_id'         => array( 'type' => 'integer' ),
			'transaction_type' => array( 'type' => 'string' ),
			'total'            => array( 'type' => 'number', 'description' => 'Decimal (converted from cents)' ),
			'status'           => array( 'type' => 'string' ),
			'payment_method'   => array( 'type' => 'string' ),
			'card_brand'       => array( 'type' => array( 'string', 'null' ) ),
			'card_last_4'      => array( 'type' => array( 'string', 'null' ) ),
			'created_at'       => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$query = \FluentCart\App\Models\OrderTransaction::where( 'order_id', (int) $input['order_id'] );
			if ( ! empty( $input['status'] ) ) {
				$query->where( 'status', sanitize_text_field( $input['status'] ) );
			}
			$total = $query->count();
			$rows  = $query->orderBy( 'id', 'DESC' )
				->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();

			$items = array();
			foreach ( $rows as $t ) {
				$items[] = array(
					'id'               => (int) $t->id,
					'order_id'         => (int) $t->order_id,
					'transaction_type' => (string) ( $t->transaction_type ?? '' ),
					'total'            => fluent_cart_format_money( $t->total ),
					'status'           => (string) ( $t->status ?? '' ),
					'payment_method'   => (string) ( $t->payment_method ?? '' ),
					'card_brand'       => $t->card_brand ?? null,
					'card_last_4'      => $t->card_last_4 ?? null,
					'created_at'       => $t->created_at ? (string) $t->created_at : null,
				);
			}
			return array(
				'transactions' => $items,
				'total'        => $total,
				'page'         => $pagination['page'],
				'per_page'     => $pagination['per_page'],
			);
		},
	) );

	$reg->read( 'fluent-cart/get-order-transaction', array(
		'label'       => 'Get Order Transaction',
		'description' => 'Get a single transaction by ID. Money returned as decimal (BIGINT cents in storage).',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'                  => array( 'type' => 'integer' ),
			'order_id'            => array( 'type' => 'integer' ),
			'subscription_id'     => array( 'type' => array( 'integer', 'null' ) ),
			'transaction_type'    => array( 'type' => 'string' ),
			'total'               => array( 'type' => 'number' ),
			'status'              => array( 'type' => 'string' ),
			'payment_method'      => array( 'type' => 'string' ),
			'vendor_charge_id'    => array( 'type' => array( 'string', 'null' ) ),
			'card_brand'          => array( 'type' => array( 'string', 'null' ) ),
			'card_last_4'         => array( 'type' => array( 'string', 'null' ) ),
			'created_at'          => array( 'type' => array( 'string', 'null' ) ),
			'updated_at'          => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$t = \FluentCart\App\Models\OrderTransaction::find( (int) $input['id'] );
			if ( ! $t ) {
				return fluent_abilities_error( 'not_found', 'Transaction not found.' );
			}
			return array(
				'id'               => (int) $t->id,
				'order_id'         => (int) $t->order_id,
				'subscription_id'  => isset( $t->subscription_id ) ? (int) $t->subscription_id : null,
				'transaction_type' => (string) ( $t->transaction_type ?? '' ),
				'total'            => fluent_cart_format_money( $t->total ),
				'status'           => (string) ( $t->status ?? '' ),
				'payment_method'   => (string) ( $t->payment_method ?? '' ),
				'vendor_charge_id' => $t->vendor_charge_id ?? null,
				'card_brand'       => $t->card_brand ?? null,
				'card_last_4'      => $t->card_last_4 ?? null,
				'created_at'       => $t->created_at ? (string) $t->created_at : null,
				'updated_at'       => $t->updated_at ? (string) $t->updated_at : null,
			);
		},
	) );

	$reg->write( 'fluent-cart/update-transaction-status', array(
		'label'       => 'Update Transaction Status',
		'description' => 'Update the status of a transaction (e.g. paid, failed, refunded, disputed). Mirrors PUT /orders/{order}/transactions/{transaction}/status.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'id', 'status' ),
			'properties' => array(
				'id'     => array( 'type' => 'integer' ),
				'status' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'     => array( 'type' => 'integer' ),
			'status' => array( 'type' => 'string' ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$t = \FluentCart\App\Models\OrderTransaction::find( (int) $input['id'] );
			if ( ! $t ) {
				return fluent_abilities_error( 'not_found', 'Transaction not found.' );
			}
			$t->status = sanitize_text_field( $input['status'] );
			$t->save();
			return array( 'success' => true, 'id' => (int) $t->id, 'status' => (string) $t->status );
		},
	) );

	$reg->write( 'fluent-cart/accept-dispute', array(
		'label'       => 'Accept Transaction Dispute',
		'description' => 'Accept a payment-vendor dispute on a transaction. Mirrors POST /orders/{order}/transactions/{transaction_id}/accept-dispute.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'id' ),
			'properties' => array(
				'id'     => array( 'type' => 'integer', 'description' => 'Transaction ID' ),
				'reason' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$t = \FluentCart\App\Models\OrderTransaction::find( (int) $input['id'] );
			if ( ! $t ) {
				return fluent_abilities_error( 'not_found', 'Transaction not found.' );
			}
			$t->status = 'dispute_accepted';
			$t->save();
			do_action( 'fluent_cart/transaction_dispute_accepted', $t, $input['reason'] ?? '' );
			return array( 'success' => true, 'id' => (int) $t->id );
		},
	) );

	// =========================================================================
	// 4.3 ORDER ADDRESS & CUSTOMER-LINK OPERATIONS (4)
	// =========================================================================

	$reg->read( 'fluent-cart/get-order-addresses', array(
		'label'       => 'Get Order Addresses',
		'description' => 'Return billing and shipping addresses recorded for an order (fct_order_addresses).',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'order_id' ),
			'properties' => array(
				'order_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'order_id'  => array( 'type' => 'integer' ),
			'billing'   => array( 'type' => array( 'object', 'null' ) ),
			'shipping'  => array( 'type' => array( 'object', 'null' ) ),
			'addresses' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $input ) {
			$order_id  = (int) $input['order_id'];
			$addresses = \FluentCart\App\Models\OrderAddress::where( 'order_id', $order_id )->get();
			$billing   = null;
			$shipping  = null;
			$all       = array();
			foreach ( $addresses as $addr ) {
				$flat = array(
					'id'         => (int) $addr->id,
					'order_id'   => (int) $addr->order_id,
					'type'       => (string) ( $addr->type ?? '' ),
					'name'       => (string) ( $addr->name ?? '' ),
					'address_1'  => (string) ( $addr->address_1 ?? '' ),
					'address_2'  => (string) ( $addr->address_2 ?? '' ),
					'city'       => (string) ( $addr->city ?? '' ),
					'state'      => (string) ( $addr->state ?? '' ),
					'postcode'   => (string) ( $addr->postcode ?? '' ),
					'country'    => (string) ( $addr->country ?? '' ),
					'phone'      => (string) ( $addr->phone ?? '' ),
					'email'      => (string) ( $addr->email ?? '' ),
				);
				$all[] = $flat;
				if ( 'billing' === $addr->type ) {
					$billing = $flat;
				} elseif ( 'shipping' === $addr->type ) {
					$shipping = $flat;
				}
			}
			return array(
				'order_id'  => $order_id,
				'billing'   => $billing,
				'shipping'  => $shipping,
				'addresses' => $all,
			);
		},
	) );

	$reg->write( 'fluent-cart/update-order-customer', array(
		'label'       => 'Reassign Order Customer',
		'description' => 'Reassign an existing order to a different existing FluentCart customer. Mirrors POST /orders/{order_id}/change-customer.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'order_id', 'customer_id' ),
			'properties' => array(
				'order_id'    => array( 'type' => 'integer' ),
				'customer_id' => array( 'type' => 'integer', 'description' => 'New fct_customers.id' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'order_id'    => array( 'type' => 'integer' ),
			'customer_id' => array( 'type' => 'integer' ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$order    = \FluentCart\App\Models\Order::find( (int) $input['order_id'] );
			$customer = \FluentCart\App\Models\Customer::find( (int) $input['customer_id'] );
			if ( ! $order ) {
				return fluent_abilities_error( 'not_found', 'Order not found.' );
			}
			if ( ! $customer ) {
				return fluent_abilities_error( 'not_found', 'Customer not found.' );
			}
			$order->customer_id = $customer->id;
			$order->save();
			return array( 'success' => true, 'order_id' => (int) $order->id, 'customer_id' => (int) $customer->id );
		},
	) );

	$reg->write( 'fluent-cart/create-and-attach-customer-to-order', array(
		'label'       => 'Create Customer And Attach To Order',
		'description' => 'Create a brand-new FluentCart customer record and attach an existing order to it. Mirrors POST /orders/{order_id}/create-and-change-customer.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'order_id', 'email' ),
			'properties' => array(
				'order_id'   => array( 'type' => 'integer' ),
				'email'      => array( 'type' => 'string' ),
				'first_name' => array( 'type' => 'string' ),
				'last_name'  => array( 'type' => 'string' ),
				'country'    => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'order_id'    => array( 'type' => 'integer' ),
			'customer_id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'capability'  => 'manage_options',
		'callback'    => function( $input ) {
			$order = \FluentCart\App\Models\Order::find( (int) $input['order_id'] );
			if ( ! $order ) {
				return fluent_abilities_error( 'not_found', 'Order not found.' );
			}
			$email = sanitize_email( $input['email'] );
			if ( ! $email ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Valid email required.' );
			}
			$customer = \FluentCart\App\Models\Customer::create( array(
				'email'      => $email,
				'first_name' => sanitize_text_field( $input['first_name'] ?? '' ),
				'last_name'  => sanitize_text_field( $input['last_name'] ?? '' ),
				'country'    => sanitize_text_field( $input['country'] ?? '' ),
				'status'     => 'active',
			) );
			$order->customer_id = $customer->id;
			$order->save();
			return array( 'success' => true, 'order_id' => (int) $order->id, 'customer_id' => (int) $customer->id );
		},
	) );

	$reg->write( 'fluent-cart/update-order-address-id', array(
		'label'       => 'Relink Order Address',
		'description' => 'Relink an order to a different stored customer address. Mirrors POST /orders/{order_id}/update-address-id.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'order_id', 'address_id', 'type' ),
			'properties' => array(
				'order_id'   => array( 'type' => 'integer' ),
				'address_id' => array( 'type' => 'integer', 'description' => 'fct_customer_addresses.id' ),
				'type'       => array( 'type' => 'string', 'description' => 'billing | shipping', 'enum' => array( 'billing', 'shipping' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'order_id'   => array( 'type' => 'integer' ),
			'address_id' => array( 'type' => 'integer' ),
			'type'       => array( 'type' => 'string' ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$order   = \FluentCart\App\Models\Order::find( (int) $input['order_id'] );
			$address = \FluentCart\App\Models\CustomerAddress::find( (int) $input['address_id'] );
			if ( ! $order ) {
				return fluent_abilities_error( 'not_found', 'Order not found.' );
			}
			if ( ! $address ) {
				return fluent_abilities_error( 'not_found', 'Address not found.' );
			}
			$type = sanitize_text_field( $input['type'] );
			$row = \FluentCart\App\Models\OrderAddress::where( 'order_id', $order->id )
				->where( 'type', $type )->first();
			$attrs = array(
				'order_id'   => $order->id,
				'type'       => $type,
				'name'       => (string) ( $address->name ?? '' ),
				'address_1'  => (string) ( $address->address_1 ?? '' ),
				'address_2'  => (string) ( $address->address_2 ?? '' ),
				'city'       => (string) ( $address->city ?? '' ),
				'state'      => (string) ( $address->state ?? '' ),
				'postcode'   => (string) ( $address->postcode ?? '' ),
				'country'    => (string) ( $address->country ?? '' ),
				'phone'      => (string) ( $address->phone ?? '' ),
				'email'      => (string) ( $address->email ?? '' ),
			);
			if ( $row ) {
				foreach ( $attrs as $k => $v ) {
					$row->{$k} = $v;
				}
				$row->save();
			} else {
				\FluentCart\App\Models\OrderAddress::create( $attrs );
			}
			return array( 'success' => true, 'order_id' => (int) $order->id, 'address_id' => (int) $address->id, 'type' => $type );
		},
	) );

	$count = 14;
	error_log( "Abilities for Fluent: Registered {$count} Cart Order Management abilities" );

}, 100 );
