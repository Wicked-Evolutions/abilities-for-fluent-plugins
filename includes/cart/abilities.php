<?php
/**
 * FluentCart Abilities — P1 Read-Only
 *
 * Products, orders, customers, and subscriptions (read-only).
 *
 * 8 abilities in the 'fluent-cart' category.
 * Registered via Fluent_Abilities_Registrar.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

/**
 * Convert BIGINT cents to decimal for display.
 *
 * FluentCart stores all monetary values as integer cents (BIGINT).
 * This helper converts to human-readable decimal format.
 *
 * @param int|null $cents    Amount in cents.
 * @param int      $decimals Number of decimal places.
 * @return float|null
 */
function fluent_cart_format_money( $cents, $decimals = 2 ) {
	if ( $cents === null ) {
		return 0.0;
	}
	return round( (int) $cents / 100, $decimals );
}

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'cart' );

	// =========================================================================
	// PRODUCTS
	// =========================================================================

	$reg->read( 'fluent-cart/list-products', array(
		'label'       => 'List Products',
		'description' => 'List FluentCart products with filtering by status, type (physical/digital), and search. Returns pricing and stock summary.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'search' => array(
					'type'        => 'string',
					'description' => 'Search by product title',
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'Filter by status: draft, active, inactive',
				),
				'type' => array(
					'type'        => 'string',
					'description' => 'Filter by product type: physical, digital',
				),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'products', array(
			'id'             => array( 'type' => 'integer' ),
			'title'          => array( 'type' => 'string' ),
			'slug'           => array( 'type' => 'string' ),
			'status'         => array( 'type' => 'string' ),
			'product_type'   => array( 'type' => 'string' ),
			'price'          => array( 'type' => 'number' ),
			'stock_status'   => array( 'type' => 'string' ),
			'stock_quantity' => array( 'type' => 'integer' ),
			'created_at'     => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$query = \FluentCart\App\Models\Product::query();

			if ( ! empty( $input['search'] ) ) {
				$search = sanitize_text_field( $input['search'] );
				// FluentCart Product uses WP posts CPT — column is post_title.
				$query->where( 'post_title', 'LIKE', "%{$search}%" );
			}

			if ( ! empty( $input['status'] ) ) {
				// FluentCart Product uses WP posts CPT — column is post_status.
				$query->where( 'post_status', sanitize_text_field( $input['status'] ) );
			}

			if ( ! empty( $input['type'] ) ) {
				$query->where( 'product_type', sanitize_text_field( $input['type'] ) );
			}

			$total = $query->count();
			$products = $query->orderBy( 'ID', 'DESC' )
				->offset( $pagination['offset'] )
				->limit( $pagination['per_page'] )
				->get();

			$items = array();
			foreach ( $products as $product ) {
				$detail = $product->detail;
				// FluentCart Product model uses WP posts CPT (table=posts, PK=ID).
				$items[] = array(
					'id'             => (int) $product->ID,
					'title'          => (string) ( $product->post_title ?? '' ),
					'slug'           => (string) ( $product->post_name ?? '' ),
					'status'         => (string) ( $product->post_status ?? '' ),
					'product_type'   => (string) ( $detail->fulfillment_type ?? '' ),
					'price'          => $detail ? fluent_cart_format_money( $detail->min_price ) : 0,
					'stock_status'   => (string) ( $detail->stock_status ?? '' ),
					'stock_quantity' => (int) ( $detail->stock_quantity ?? 0 ),
					'created_at'     => (string) ( $product->post_date ?? '' ),
				);
			}

			return array(
				'products' => $items,
				'total'    => $total,
				'page'     => $pagination['page'],
				'per_page' => $pagination['per_page'],
			);
		},
	) );

	$reg->read( 'fluent-cart/get-product', array(
		'label'       => 'Get Product',
		'description' => 'Get a single FluentCart product by ID, including pricing details, stock info, and variations.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Product ID',
				),
			),
			'required' => array( 'id' ),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'               => array( 'type' => 'integer' ),
			'title'            => array( 'type' => 'string' ),
			'slug'             => array( 'type' => 'string' ),
			'status'           => array( 'type' => 'string' ),
			'product_type'     => array( 'type' => 'string' ),
			'price'            => array( 'type' => 'number' ),
			'compare_price'    => array( 'type' => 'number' ),
			'stock_status'     => array( 'type' => 'string' ),
			'stock_quantity'   => array( 'type' => 'integer' ),
			'is_taxable'       => array( 'type' => 'boolean' ),
			'variations'       => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'variations_count' => array( 'type' => 'integer' ),
			'created_at'       => array( 'type' => 'string' ),
			'updated_at'       => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( empty( $input['id'] ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Product ID is required' );
			}

			$product = \FluentCart\App\Models\Product::with( array( 'detail', 'variants' ) )
				->find( (int) $input['id'] );

			if ( ! $product ) {
				return fluent_abilities_error( 'not_found', 'Product not found' );
			}

			$detail = $product->detail;
			$variations = array();
			if ( $product->variants ) {
				foreach ( $product->variants as $variation ) {
					$variations[] = array(
						'id'             => (int) $variation->id,
						'title'          => $variation->title,
						'price'          => fluent_cart_format_money( $variation->price ),
						'compare_price'  => fluent_cart_format_money( $variation->compare_price ),
						'stock_status'   => $variation->stock_status ?? null,
						'stock_quantity' => $variation->stock_quantity ?? null,
						'status'         => $variation->status ?? null,
					);
				}
			}

			// FluentCart Product model uses WP posts CPT (table=posts, PK=ID).
			return array(
				'id'               => (int) $product->ID,
				'title'            => (string) ( $product->post_title ?? '' ),
				'slug'             => (string) ( $product->post_name ?? '' ),
				'status'           => (string) ( $product->post_status ?? '' ),
				'product_type'     => (string) ( $detail->fulfillment_type ?? '' ),
				'price'            => $detail ? fluent_cart_format_money( $detail->min_price ) : 0,
				'compare_price'    => $detail ? fluent_cart_format_money( $detail->compare_price ) : 0,
				'stock_status'     => (string) ( $detail->stock_status ?? '' ),
				'stock_quantity'   => (int) ( $detail->stock_quantity ?? 0 ),
				'is_taxable'       => (bool) ( $detail->is_taxable ?? false ),
				'variations'       => $variations,
				'variations_count' => count( $variations ),
				'created_at'       => (string) ( $product->post_date ?? '' ),
				'updated_at'       => (string) ( $product->post_modified ?? '' ),
			);
		},
	) );

	// =========================================================================
	// ORDERS
	// =========================================================================

	$reg->read( 'fluent-cart/list-orders', array(
		'label'       => 'List Orders',
		'description' => 'List FluentCart orders with filtering by status, payment_status, customer_id, date range, and search. Money values are converted from cents to decimal.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'search' => array(
					'type'        => 'string',
					'description' => 'Search by order UUID or customer email',
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'Filter by order status: pending, processing, completed, cancelled, refunded, failed',
				),
				'payment_status' => array(
					'type'        => 'string',
					'description' => 'Filter by payment status: paid, unpaid, refunded, partially_refunded',
				),
				'customer_id' => array(
					'type'        => 'integer',
					'description' => 'Filter by FluentCart customer ID',
				),
				'date_from' => array(
					'type'        => 'string',
					'description' => 'Filter orders from this date (YYYY-MM-DD)',
				),
				'date_to' => array(
					'type'        => 'string',
					'description' => 'Filter orders up to this date (YYYY-MM-DD)',
				),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'orders', array(
			'id'             => array( 'type' => 'integer' ),
			'uuid'           => array( 'type' => 'string' ),
			'status'         => array( 'type' => 'string' ),
			'payment_status' => array( 'type' => 'string' ),
			'payment_method' => array( 'type' => 'string' ),
			'total'          => array( 'type' => 'number' ),
			'subtotal'       => array( 'type' => 'number' ),
			'tax_total'      => array( 'type' => 'number' ),
			'discount_total' => array( 'type' => 'number' ),
			'currency'       => array( 'type' => 'string' ),
			'customer_id'    => array( 'type' => 'integer' ),
			'customer_email' => array( 'type' => 'string' ),
			'created_at'     => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$query = \FluentCart\App\Models\Order::query();

			if ( ! empty( $input['search'] ) ) {
				$search = sanitize_text_field( $input['search'] );
				$query->where( function( $q ) use ( $search ) {
					$q->where( 'uuid', 'LIKE', "%{$search}%" )
					  ->orWhere( 'customer_email', 'LIKE', "%{$search}%" );
				});
			}

			if ( ! empty( $input['status'] ) ) {
				$query->where( 'status', sanitize_text_field( $input['status'] ) );
			}

			if ( ! empty( $input['payment_status'] ) ) {
				$query->where( 'payment_status', sanitize_text_field( $input['payment_status'] ) );
			}

			if ( ! empty( $input['customer_id'] ) ) {
				$query->where( 'customer_id', (int) $input['customer_id'] );
			}

			if ( ! empty( $input['date_from'] ) ) {
				$query->where( 'created_at', '>=', sanitize_text_field( $input['date_from'] ) . ' 00:00:00' );
			}

			if ( ! empty( $input['date_to'] ) ) {
				$query->where( 'created_at', '<=', sanitize_text_field( $input['date_to'] ) . ' 23:59:59' );
			}

			$total = $query->count();
			$orders = $query->orderBy( 'id', 'DESC' )
				->offset( $pagination['offset'] )
				->limit( $pagination['per_page'] )
				->get();

			$items = array();
			foreach ( $orders as $order ) {
				$items[] = array(
					'id'             => (int) $order->id,
					'uuid'           => (string) ( $order->uuid ?? '' ),
					'status'         => (string) ( $order->status ?? '' ),
					'payment_status' => (string) ( $order->payment_status ?? '' ),
					'payment_method' => (string) ( $order->payment_method ?? '' ),
					'total'          => fluent_cart_format_money( $order->total ),
					'subtotal'       => fluent_cart_format_money( $order->subtotal ),
					'tax_total'      => fluent_cart_format_money( $order->tax_total ),
					'discount_total' => fluent_cart_format_money( $order->discount_total ),
					'currency'       => (string) ( $order->currency ?? '' ),
					'customer_id'    => (int) ( $order->customer_id ?? 0 ),
					'customer_email' => (string) ( $order->customer_email ?? '' ),
					'created_at'     => (string) $order->created_at,
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

	$reg->read( 'fluent-cart/get-order', array(
		'label'       => 'Get Order',
		'description' => 'Get a single FluentCart order by ID, including line items, transactions, and addresses. Money values are converted from cents to decimal.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Order ID',
				),
			),
			'required' => array( 'id' ),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'              => array( 'type' => 'integer' ),
			'uuid'            => array( 'type' => 'string' ),
			'status'          => array( 'type' => 'string' ),
			'payment_status'  => array( 'type' => 'string' ),
			'payment_method'  => array( 'type' => 'string' ),
			'total'           => array( 'type' => 'number' ),
			'subtotal'        => array( 'type' => 'number' ),
			'tax_total'       => array( 'type' => 'number' ),
			'discount_total'  => array( 'type' => 'number' ),
			'currency'        => array( 'type' => 'string' ),
			'customer_id'     => array( 'type' => 'integer' ),
			'customer_email'  => array( 'type' => 'string' ),
			'items'           => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'transactions'    => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'addresses'       => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'note'            => array( 'type' => 'string' ),
			'created_at'      => array( 'type' => 'string' ),
			'updated_at'      => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( empty( $input['id'] ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Order ID is required' );
			}

			$order = \FluentCart\App\Models\Order::with( array( 'order_items', 'transactions', 'order_addresses' ) )
				->find( (int) $input['id'] );

			if ( ! $order ) {
				return fluent_abilities_error( 'not_found', 'Order not found' );
			}

			$line_items = array();
			if ( $order->order_items ) {
				foreach ( $order->order_items as $item ) {
					$line_items[] = array(
						'id'           => (int) $item->id,
						'product_id'   => $item->product_id,
						'product_name' => $item->item_name ?? $item->product_name ?? null,
						'variation_id' => $item->variation_id ?? null,
						'quantity'     => $item->quantity,
						'unit_price'   => fluent_cart_format_money( $item->unit_price ),
						'line_total'   => fluent_cart_format_money( $item->line_total ),
						'tax_total'    => fluent_cart_format_money( $item->tax_total ),
					);
				}
			}

			$transactions = array();
			if ( $order->transactions ) {
				foreach ( $order->transactions as $txn ) {
					$transactions[] = array(
						'id'             => (int) $txn->id,
						'type'           => $txn->transaction_type,
						'payment_method' => $txn->payment_method,
						'amount'         => fluent_cart_format_money( $txn->amount ),
						'status'         => $txn->status,
						'created_at'     => (string) $txn->created_at,
					);
				}
			}

			$addresses = array();
			if ( $order->order_addresses ) {
				foreach ( $order->order_addresses as $addr ) {
					$addresses[] = array(
						'type'         => $addr->address_type,
						'first_name'   => $addr->first_name,
						'last_name'    => $addr->last_name,
						'address_1'    => $addr->address_1,
						'address_2'    => $addr->address_2,
						'city'         => $addr->city,
						'state'        => $addr->state,
						'postcode'     => $addr->postcode,
						'country'      => $addr->country,
					);
				}
			}

			return array(
				'id'              => (int) $order->id,
				'uuid'            => (string) ( $order->uuid ?? '' ),
				'status'          => (string) ( $order->status ?? '' ),
				'payment_status'  => (string) ( $order->payment_status ?? '' ),
				'payment_method'  => (string) ( $order->payment_method ?? '' ),
				'total'           => fluent_cart_format_money( $order->total ),
				'subtotal'        => fluent_cart_format_money( $order->subtotal ),
				'tax_total'       => fluent_cart_format_money( $order->tax_total ),
				'discount_total'  => fluent_cart_format_money( $order->discount_total ),
				'currency'        => (string) ( $order->currency ?? '' ),
				'customer_id'     => (int) ( $order->customer_id ?? 0 ),
				'customer_email'  => (string) ( $order->customer_email ?? '' ),
				'items'           => $line_items,
				'transactions'    => $transactions,
				'addresses'       => $addresses,
				'note'            => (string) ( $order->note ?? '' ),
				'created_at'      => (string) $order->created_at,
				'updated_at'      => (string) $order->updated_at,
			);
		},
	) );

	// =========================================================================
	// CUSTOMERS
	// =========================================================================

	$reg->read( 'fluent-cart/list-customers', array(
		'label'       => 'List Customers',
		'description' => 'List FluentCart customers with filtering by status and search (name/email). Monetary values are converted from cents to decimal.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'search' => array(
					'type'        => 'string',
					'description' => 'Search by customer name or email',
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'Filter by customer status: active, inactive',
				),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'customers', array(
			'id'                => array( 'type' => 'integer' ),
			'email'             => array( 'type' => 'string' ),
			'first_name'        => array( 'type' => 'string' ),
			'last_name'         => array( 'type' => 'string' ),
			'status'            => array( 'type' => 'string' ),
			'total_order_count' => array( 'type' => 'integer' ),
			'lifetime_value'    => array( 'type' => 'number' ),
			'contact_id'        => array( 'type' => 'integer' ),
			'created_at'        => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$query = \FluentCart\App\Models\Customer::query();

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

			$total = $query->count();
			$customers = $query->orderBy( 'id', 'DESC' )
				->offset( $pagination['offset'] )
				->limit( $pagination['per_page'] )
				->get();

			$items = array();
			foreach ( $customers as $customer ) {
				$items[] = array(
					'id'                => (int) $customer->id,
					'email'             => (string) ( $customer->email ?? '' ),
					'first_name'        => (string) ( $customer->first_name ?? '' ),
					'last_name'         => (string) ( $customer->last_name ?? '' ),
					'status'            => (string) ( $customer->status ?? '' ),
					'total_order_count' => $customer->total_order_count ?? 0,
					'lifetime_value'    => fluent_cart_format_money( $customer->lifetime_value ),
					'contact_id'        => $customer->contact_id ?? null,
					'created_at'        => (string) $customer->created_at,
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

	$reg->read( 'fluent-cart/get-customer', array(
		'label'       => 'Get Customer',
		'description' => 'Get a single FluentCart customer by ID or email, including addresses, recent orders, and active subscription count. Monetary values are converted from cents to decimal.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Customer ID',
				),
				'email' => array(
					'type'        => 'string',
					'description' => 'Customer email (alternative to ID)',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'                   => array( 'type' => 'integer' ),
			'email'                => array( 'type' => 'string' ),
			'first_name'           => array( 'type' => 'string' ),
			'last_name'            => array( 'type' => 'string' ),
			'status'               => array( 'type' => 'string' ),
			'total_order_count'    => array( 'type' => 'integer' ),
			'lifetime_value'       => array( 'type' => 'number' ),
			'contact_id'           => array( 'type' => 'integer' ),
			'user_id'              => array( 'type' => array( 'integer', 'null' ) ),
			'recent_orders'        => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'active_subscriptions' => array( 'type' => 'integer' ),
			'created_at'           => array( 'type' => 'string' ),
			'updated_at'           => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! empty( $input['id'] ) ) {
				$customer = \FluentCart\App\Models\Customer::find( (int) $input['id'] );
			} elseif ( ! empty( $input['email'] ) ) {
				$customer = \FluentCart\App\Models\Customer::where( 'email', sanitize_email( $input['email'] ) )->first();
			} else {
				return fluent_abilities_error( 'ability_invalid_input', 'Provide either id or email' );
			}

			if ( ! $customer ) {
				return fluent_abilities_error( 'not_found', 'Customer not found' );
			}

			// Recent orders (last 5).
			$recent_orders = \FluentCart\App\Models\Order::where( 'customer_id', $customer->id )
				->orderBy( 'id', 'DESC' )
				->limit( 5 )
				->get()
				->map( function( $order ) {
					return array(
						'id'             => (int) $order->id,
						'uuid'           => (string) ( $order->uuid ?? '' ),
						'status'         => (string) ( $order->status ?? '' ),
						'payment_status' => (string) ( $order->payment_status ?? '' ),
						'total'          => fluent_cart_format_money( $order->total ),
						'currency'       => (string) ( $order->currency ?? '' ),
						'created_at'     => (string) $order->created_at,
					);
				})->toArray();

			// Active subscriptions count.
			$active_subscriptions = \FluentCart\App\Models\Subscription::where( 'customer_id', $customer->id )
				->where( 'status', 'active' )
				->count();

			return array(
				'id'                     => (int) $customer->id,
				'email'                  => (string) ( $customer->email ?? '' ),
				'first_name'             => (string) ( $customer->first_name ?? '' ),
				'last_name'              => (string) ( $customer->last_name ?? '' ),
				'status'                 => (string) ( $customer->status ?? '' ),
				'total_order_count'      => $customer->total_order_count ?? 0,
				'lifetime_value'         => fluent_cart_format_money( $customer->lifetime_value ),
				'contact_id'             => $customer->contact_id ?? null,
				'user_id'                => $customer->user_id ?? null,
				'recent_orders'          => $recent_orders,
				'active_subscriptions'   => $active_subscriptions,
				'created_at'             => (string) $customer->created_at,
				'updated_at'             => (string) $customer->updated_at,
			);
		},
	) );

	// =========================================================================
	// SUBSCRIPTIONS
	// =========================================================================

	$reg->read( 'fluent-cart/list-subscriptions', array(
		'label'       => 'List Subscriptions',
		'description' => 'List FluentCart subscriptions with filtering by status, customer_id, and product_id. Monetary values are converted from cents to decimal.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'status' => array(
					'type'        => 'string',
					'description' => 'Filter by subscription status: active, cancelled, expired, paused, pending',
				),
				'customer_id' => array(
					'type'        => 'integer',
					'description' => 'Filter by FluentCart customer ID',
				),
				'product_id' => array(
					'type'        => 'integer',
					'description' => 'Filter by product ID',
				),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'subscriptions', array(
			'id'                => array( 'type' => 'integer' ),
			'status'            => array( 'type' => 'string' ),
			'billing_interval'  => array( 'type' => 'integer' ),
			'billing_period'    => array( 'type' => 'string' ),
			'recurring_amount'  => array( 'type' => 'number' ),
			'currency'          => array( 'type' => 'string' ),
			'customer_id'       => array( 'type' => 'integer' ),
			'product_id'        => array( 'type' => 'integer' ),
			'next_billing_date' => array( 'type' => 'string' ),
			'created_at'        => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$query = \FluentCart\App\Models\Subscription::query();

			if ( ! empty( $input['status'] ) ) {
				$query->where( 'status', sanitize_text_field( $input['status'] ) );
			}

			if ( ! empty( $input['customer_id'] ) ) {
				$query->where( 'customer_id', (int) $input['customer_id'] );
			}

			if ( ! empty( $input['product_id'] ) ) {
				$query->where( 'product_id', (int) $input['product_id'] );
			}

			$total = $query->count();
			$subscriptions = $query->orderBy( 'id', 'DESC' )
				->offset( $pagination['offset'] )
				->limit( $pagination['per_page'] )
				->get();

			$items = array();
			foreach ( $subscriptions as $sub ) {
				$items[] = array(
					'id'                => (int) $sub->id,
					'status'            => (string) ( $sub->status ?? '' ),
					'billing_interval'  => $sub->billing_interval,
					'billing_period'    => $sub->billing_period ?? null,
					'recurring_amount'  => fluent_cart_format_money( $sub->recurring_amount ),
					'currency'          => $sub->currency ?? null,
					'customer_id'       => $sub->customer_id,
					'product_id'        => $sub->product_id,
					'next_billing_date' => $sub->next_billing_date ?? null,
					'created_at'        => (string) $sub->created_at,
				);
			}

			return array(
				'subscriptions' => $items,
				'total'         => $total,
				'page'          => $pagination['page'],
				'per_page'      => $pagination['per_page'],
			);
		},
	) );

	$reg->read( 'fluent-cart/get-subscription', array(
		'label'       => 'Get Subscription',
		'description' => 'Get a single FluentCart subscription by ID, including customer info, product info, and transaction history. Monetary values are converted from cents to decimal.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Subscription ID',
				),
			),
			'required' => array( 'id' ),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'                => array( 'type' => 'integer' ),
			'status'            => array( 'type' => 'string' ),
			'billing_interval'  => array( 'type' => 'integer' ),
			'billing_period'    => array( 'type' => 'string' ),
			'recurring_amount'  => array( 'type' => 'number' ),
			'initial_amount'    => array( 'type' => 'number' ),
			'currency'          => array( 'type' => 'string' ),
			'payment_method'    => array( 'type' => 'string' ),
			'customer_id'       => array( 'type' => 'integer' ),
			'product_id'        => array( 'type' => 'integer' ),
			'variation_id'      => array( 'type' => 'integer' ),
			'next_billing_date' => array( 'type' => 'string' ),
			'trial_end_date'    => array( 'type' => 'string' ),
			'customer'          => array( 'type' => 'object' ),
			'product'           => array( 'type' => 'object' ),
			'renewal_orders'    => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'created_at'        => array( 'type' => 'string' ),
			'updated_at'        => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( empty( $input['id'] ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Subscription ID is required' );
			}

			$sub = \FluentCart\App\Models\Subscription::with( array( 'customer', 'product' ) )
				->find( (int) $input['id'] );

			if ( ! $sub ) {
				return fluent_abilities_error( 'not_found', 'Subscription not found' );
			}

			$customer_info = null;
			if ( $sub->customer ) {
				$customer_info = array(
					'id'         => (int) $sub->customer->id,
					'email'      => $sub->customer->email,
					'first_name' => $sub->customer->first_name,
					'last_name'  => $sub->customer->last_name,
				);
			}

			$product_info = null;
			if ( $sub->product ) {
				$product_info = array(
					'id'    => (int) $sub->product->id,
					'title' => $sub->product->title,
					'type'  => $sub->product->product_type,
				);
			}

			// Transaction history for this subscription.
			$transactions = \FluentCart\App\Models\Order::where( 'subscription_id', $sub->id )
				->orderBy( 'id', 'DESC' )
				->limit( 20 )
				->get()
				->map( function( $order ) {
					return array(
						'order_id'       => (int) $order->id,
						'status'         => (string) ( $order->status ?? '' ),
						'payment_status' => (string) ( $order->payment_status ?? '' ),
						'total'          => fluent_cart_format_money( $order->total ),
						'currency'       => (string) ( $order->currency ?? '' ),
						'created_at'     => (string) $order->created_at,
					);
				})->toArray();

			return array(
				'id'                => (int) $sub->id,
				'status'            => (string) ( $sub->status ?? '' ),
				'billing_interval'  => $sub->billing_interval,
				'billing_period'    => $sub->billing_period ?? null,
				'recurring_amount'  => fluent_cart_format_money( $sub->recurring_amount ),
				'initial_amount'    => fluent_cart_format_money( $sub->initial_amount ),
				'currency'          => $sub->currency ?? null,
				'payment_method'    => $sub->payment_method ?? null,
				'customer_id'       => $sub->customer_id,
				'product_id'        => $sub->product_id,
				'variation_id'      => $sub->variation_id ?? null,
				'next_billing_date' => $sub->next_billing_date ?? null,
				'trial_end_date'    => $sub->trial_end_date ?? null,
				'customer'          => $customer_info,
				'product'           => $product_info,
				'renewal_orders'    => $transactions,
				'created_at'        => (string) $sub->created_at,
				'updated_at'        => (string) $sub->updated_at,
			);
		},
	) );

	$count = 8;
	error_log( "Abilities for Fluent: Registered {$count} Cart abilities" );

}, 100 );
