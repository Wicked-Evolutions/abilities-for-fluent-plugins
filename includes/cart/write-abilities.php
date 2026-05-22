<?php
/**
 * FluentCart Abilities — P2/P3/P4 Write Operations
 *
 * Orders, customers, subscriptions, products, and related management.
 * All monetary values stored as BIGINT cents; displayed as decimal.
 *
 * 33 abilities in the 'fluent-cart' category.
 * Registered via Fluent_Abilities_Registrar.
 *
 * @package Fluent_Abilities
 * @since 1.8.0
 */

defined( 'ABSPATH' ) || exit;

// Load additional cart ability files.
$cart_ability_files = array(
	'coupon-abilities',
	'variation-abilities',
	'license-abilities',
	'download-abilities',
	// v2.0.0 — Fluent Suite Registrar Bundle Sprint additions (Phase B feat/fluentcart-registrar).
	'order-management-abilities',
	'customer-extended-abilities',
	'subscription-extended-abilities',
	'product-extended-abilities',
	'attribute-abilities',
	'coupon-extended-abilities',
	'license-extended-abilities',
	'settings-abilities',
	'tax-abilities',
	'shipping-abilities',
	'activity-abilities',
	'reports-abilities',
);
foreach ( $cart_ability_files as $cart_sub ) {
	$cart_sub_file = __DIR__ . "/{$cart_sub}.php";
	if ( file_exists( $cart_sub_file ) ) {
		require_once $cart_sub_file;
	}
}

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'cart' );

	// =========================================================================
	// ORDERS
	// =========================================================================

	$reg->write( 'fluent-cart/update-order-status', array(
		'label'       => 'Update Order Status',
		'description' => 'Update the status of a FluentCart order. Valid statuses: pending, processing, completed, cancelled, refunded, failed.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id', 'status' ),
			'properties' => array(
				'id'     => array( 'type' => 'integer', 'description' => 'Order ID' ),
				'status' => array(
					'type'        => 'string',
					'description' => 'New order status',
					'enum'        => array( 'pending', 'processing', 'completed', 'cancelled', 'refunded', 'failed' ),
				),
				'note' => array( 'type' => 'string', 'description' => 'Optional note for the status change' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'         => array( 'type' => 'integer' ),
			'old_status' => array( 'type' => 'string' ),
			'new_status' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$order = \FluentCart\App\Models\Order::find( intval( $input['id'] ) );
			if ( ! $order ) {
				return fluent_abilities_error( 'not_found', 'Order not found.' );
			}
			$old_status = $order->status;
			$order->status = sanitize_text_field( $input['status'] );
			$order->save();
			if ( ! empty( $input['note'] ) ) {
				\FluentCart\App\Models\Activity::create( array(
					'module_type' => 'order',
					'module_id'   => $order->id,
					'module_name' => 'Order #' . $order->id,
					'log_type'    => 'note',
					'status'      => 'open',
					'read_status' => 'unread',
					'content'     => sanitize_text_field( $input['note'] ),
					'created_by'  => (string) get_current_user_id(),
				) );
			}
			return array(
				'success'    => true,
				'id'         => $order->id,
				'old_status' => $old_status,
				'new_status' => $order->status,
			);
		},
	) );

	$reg->write( 'fluent-cart/add-order-note', array(
		'label'       => 'Add Order Note',
		'description' => 'Add a note to a FluentCart order.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id', 'note' ),
			'properties' => array(
				'id'   => array( 'type' => 'integer', 'description' => 'Order ID' ),
				'note' => array( 'type' => 'string', 'description' => 'Note content' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'order_id'   => array( 'type' => 'integer' ),
			'activity_id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback'    => function( $input ) {
			$order = \FluentCart\App\Models\Order::find( intval( $input['id'] ) );
			if ( ! $order ) {
				return fluent_abilities_error( 'not_found', 'Order not found.' );
			}
			$activity = \FluentCart\App\Models\Activity::create( array(
				'module_type' => 'order',
				'module_id'   => $order->id,
				'module_name' => 'Order #' . $order->id,
				'log_type'    => 'note',
				'status'      => 'open',
				'read_status' => 'unread',
				'content'     => sanitize_textarea_field( $input['note'] ),
				'created_by'  => (string) get_current_user_id(),
			) );
			return array(
				'success'     => true,
				'order_id'    => $order->id,
				'activity_id' => $activity->id,
			);
		},
	) );

	$reg->delete( 'fluent-cart/cancel-order', array(
		'label'       => 'Cancel Order',
		'description' => 'Cancel a FluentCart order. Sets status to cancelled.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'   => array( 'type' => 'integer', 'description' => 'Order ID' ),
				'note' => array( 'type' => 'string', 'description' => 'Reason for cancellation' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback'    => function( $input ) {
			$order = \FluentCart\App\Models\Order::find( intval( $input['id'] ) );
			if ( ! $order ) {
				return fluent_abilities_error( 'not_found', 'Order not found.' );
			}
			$order->status = 'cancelled';
			$order->save();
			if ( ! empty( $input['note'] ) ) {
				\FluentCart\App\Models\Activity::create( array(
					'module_type' => 'order',
					'module_id'   => $order->id,
					'module_name' => 'Order #' . $order->id,
					'log_type'    => 'note',
					'status'      => 'open',
					'read_status' => 'unread',
					'content'     => sanitize_text_field( $input['note'] ),
					'created_by'  => (string) get_current_user_id(),
				) );
			}
			return array( 'success' => true, 'id' => $order->id );
		},
	) );

	// =========================================================================
	// CUSTOMERS
	// =========================================================================

	// =========================================================================
	// SUBSCRIPTIONS
	// =========================================================================

	$reg->delete( 'fluent-cart/cancel-subscription', array(
		'label'       => 'Cancel Subscription',
		'description' => 'Cancel a FluentCart subscription by ID. Sets status to cancelled.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'            => array( 'type' => 'integer', 'description' => 'Subscription ID' ),
				'cancel_reason' => array( 'type' => 'string', 'description' => 'Optional cancellation reason' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback'    => function( $input ) {
			$sub = \FluentCart\App\Models\Subscription::find( intval( $input['id'] ) );
			if ( ! $sub ) {
				return fluent_abilities_error( 'not_found', 'Subscription not found.' );
			}
			$sub->status = 'cancelled';
			if ( ! empty( $input['cancel_reason'] ) ) {
				$sub->cancel_reason = sanitize_text_field( $input['cancel_reason'] );
			}
			$sub->save();
			return array( 'success' => true, 'id' => $sub->id );
		},
	) );

	$reg->write( 'fluent-cart/pause-subscription', array(
		'label'       => 'Pause Subscription',
		'description' => 'Pause an active FluentCart subscription.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Subscription ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$sub = \FluentCart\App\Models\Subscription::find( intval( $input['id'] ) );
			if ( ! $sub ) {
				return fluent_abilities_error( 'not_found', 'Subscription not found.' );
			}
			$sub->status = 'paused';
			$sub->save();
			return array( 'success' => true, 'id' => $sub->id );
		},
	) );

	$reg->write( 'fluent-cart/resume-subscription', array(
		'label'       => 'Resume Subscription',
		'description' => 'Resume a paused FluentCart subscription.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Subscription ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$sub = \FluentCart\App\Models\Subscription::find( intval( $input['id'] ) );
			if ( ! $sub ) {
				return fluent_abilities_error( 'not_found', 'Subscription not found.' );
			}
			$sub->status = 'active';
			$sub->save();
			return array( 'success' => true, 'id' => $sub->id );
		},
	) );

	// =========================================================================
	// PRODUCTS
	// =========================================================================

	$reg->write( 'fluent-cart/create-product', array(
		'label'       => 'Create Product',
		'description' => 'Create a new FluentCart product. Price provided in cents (integer).',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'title', 'price' ),
			'properties' => array(
				'title'        => array( 'type' => 'string', 'description' => 'Product title' ),
				'price'        => array( 'type' => 'integer', 'description' => 'Price in cents (e.g., 4999 = $49.99)' ),
				'description'  => array( 'type' => 'string', 'description' => 'Product description' ),
				'status'       => array( 'type' => 'string', 'description' => 'Status: draft, active, inactive (default: draft)' ),
				'product_type' => array( 'type' => 'string', 'description' => 'Product type: physical, digital (default: digital)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback'    => function( $input ) {
			$price = intval( $input['price'] );
			// The vendor ProductController::create persists via wp_insert_post(),
			// which stamps post_date/post_date_gmt. Eloquent Product::create()
			// does not, leaving the WP posts row at the MySQL zero-date — which
			// get-product surfaces as created_at "0000-00-00 00:00:00" (#100
			// item 8). Set the post dates explicitly to match the vendor's
			// wp_insert_post behaviour.
			$now     = current_time( 'mysql' );
			$now_gmt = current_time( 'mysql', true );
			$product = \FluentCart\App\Models\Product::create( array(
				'post_title'    => sanitize_text_field( $input['title'] ),
				'post_content'  => isset( $input['description'] ) ? sanitize_textarea_field( $input['description'] ) : '',
				'post_status'   => sanitize_text_field( $input['status'] ?? 'draft' ),
				'post_type'     => 'fct_product',
				'post_date'     => $now,
				'post_date_gmt' => $now_gmt,
				'post_modified' => $now,
				'post_modified_gmt' => $now_gmt,
			) );
			$fulfillment = sanitize_text_field( $input['product_type'] ?? 'digital' );
			$detail      = \FluentCart\App\Models\ProductDetail::create( array(
				'post_id'          => $product->ID,
				'min_price'        => $price,
				'max_price'        => $price,
				'fulfillment_type' => $fulfillment,
			) );
			// FluentCart's real sellable price lives on a default ProductVariation
			// row (item_price), NOT on ProductDetail.min/max (which the vendor
			// derives from variations). Vendor ProductController::create always
			// creates this default variation; replicate that exact contract so
			// get-product (reads variants + detail) and update-product-pricing
			// (targets the variation) land. Source: FluentCart\App\Http\
			// Controllers\ProductController::create (vendor-source verified).
			$variation = \FluentCart\App\Models\ProductVariation::create( array(
				'post_id'          => $product->ID,
				'serial_index'     => 1,
				'variation_title'  => $product->post_title,
				'stock_status'     => 'in-stock',
				'payment_type'     => 'onetime',
				'total_stock'      => 1,
				'available'        => 1,
				'fulfillment_type' => $fulfillment,
				'item_price'       => $price,
				'compare_price'    => $price,
				'other_info'       => array(
					'description'        => '',
					'payment_type'       => 'onetime',
					'times'              => '',
					'repeat_interval'    => '',
					'trial_days'         => '',
					'billing_summary'    => '',
					'manage_setup_fee'   => 'no',
					'signup_fee_name'    => '',
					'signup_fee'         => '',
					'setup_fee_per_item' => 'no',
					'is_bundle_product'  => 'no',
				),
			) );
			if ( $detail && $variation ) {
				$detail->default_variation_id = (int) $variation->id;
				$detail->save();
			}
			return array( 'success' => true, 'id' => (int) $product->ID, 'title' => $product->post_title );
		},
	) );

	$reg->write( 'fluent-cart/update-product', array(
		'label'       => 'Update Product',
		'description' => 'Update a FluentCart product. Price in cents if provided.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'           => array( 'type' => 'integer', 'description' => 'Product ID' ),
				'title'        => array( 'type' => 'string', 'description' => 'Product title' ),
				'price'        => array( 'type' => 'integer', 'description' => 'Price in cents' ),
				'description'  => array( 'type' => 'string', 'description' => 'Product description' ),
				'status'       => array( 'type' => 'string', 'description' => 'Status: draft, active, inactive' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$product = \FluentCart\App\Models\Product::with( 'detail' )->find( intval( $input['id'] ) );
			if ( ! $product ) {
				return fluent_abilities_error( 'not_found', 'Product not found.' );
			}
			if ( isset( $input['title'] ) ) $product->post_title = sanitize_text_field( $input['title'] );
			if ( isset( $input['description'] ) ) $product->post_content = sanitize_textarea_field( $input['description'] );
			if ( isset( $input['status'] ) ) $product->post_status = sanitize_text_field( $input['status'] );
			$product->save();
			if ( isset( $input['price'] ) && $product->detail ) {
				$price = intval( $input['price'] );
				$product->detail->min_price = $price;
				$product->detail->max_price = $price;
				$product->detail->save();
			}
			return array( 'success' => true, 'id' => (int) $product->ID );
		},
	) );

	$reg->delete( 'fluent-cart/delete-product', array(
		'label'       => 'Delete Product',
		'description' => 'Permanently delete a FluentCart product by ID.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Product ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback'    => function( $input ) {
			$product = \FluentCart\App\Models\Product::find( intval( $input['id'] ) );
			if ( ! $product ) {
				return fluent_abilities_error( 'not_found', 'Product not found.' );
			}
			$id = (int) $product->ID;
			$product->delete();
			return array( 'success' => true, 'id' => $id );
		},
	) );

	$reg->write( 'fluent-cart/duplicate-product', array(
		'label'       => 'Duplicate Product',
		'description' => 'Duplicate an existing FluentCart product. The copy is created in draft status.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'    => array( 'type' => 'integer', 'description' => 'Source product ID' ),
				'title' => array( 'type' => 'string', 'description' => 'Title for the copy (default: "Copy of [original title]")' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback'    => function( $input ) {
			$source = \FluentCart\App\Models\Product::find( intval( $input['id'] ) );
			if ( ! $source ) {
				return fluent_abilities_error( 'not_found', 'Source product not found.' );
			}
			$new_title = ! empty( $input['title'] ) ? sanitize_text_field( $input['title'] ) : 'Copy of ' . $source->post_title;
			$copy = $source->replicate();
			$copy->post_title = $new_title;
			$copy->post_status = 'draft';
			$copy->save();
			return array( 'success' => true, 'id' => (int) $copy->ID, 'title' => $copy->post_title );
		},
	) );

	// =========================================================================
	// COUPONS
	// =========================================================================

	$reg->write( 'fluent-cart/create-coupon', array(
		'label'       => 'Create Coupon',
		'description' => 'Create a new FluentCart coupon/discount code.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'code', 'discount_type', 'amount' ),
			'properties' => array(
				'code'          => array( 'type' => 'string', 'description' => 'Unique coupon code' ),
				'discount_type' => array( 'type' => 'string', 'description' => 'Discount type: percent, fixed', 'enum' => array( 'percent', 'fixed' ) ),
				'amount'        => array( 'type' => 'number', 'description' => 'Discount value (percentage 0-100 or fixed amount in cents)' ),
				'description'   => array( 'type' => 'string', 'description' => 'Coupon description' ),
				'status'        => array( 'type' => 'string', 'description' => 'Status: active, inactive (default: active)' ),
				'usage_limit'   => array( 'type' => 'integer', 'description' => 'Maximum uses (null = unlimited)' ),
				'expires_at'    => array( 'type' => 'string', 'description' => 'Expiry date (YYYY-MM-DD)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'   => array( 'type' => 'integer' ),
			'code' => array( 'type' => 'string' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback'    => function( $input ) {
			$data = array(
				'code'          => sanitize_text_field( $input['code'] ),
				'discount_type' => sanitize_text_field( $input['discount_type'] ),
				'amount'        => (float) $input['amount'],
				'status'        => sanitize_text_field( $input['status'] ?? 'active' ),
			);
			if ( isset( $input['description'] ) ) $data['description'] = sanitize_text_field( $input['description'] );
			if ( isset( $input['usage_limit'] ) ) $data['usage_limit'] = intval( $input['usage_limit'] );
			if ( isset( $input['expires_at'] ) ) $data['expires_at'] = sanitize_text_field( $input['expires_at'] );

			$coupon = \FluentCart\App\Models\Coupon::create( $data );
			return array( 'success' => true, 'id' => $coupon->id, 'code' => $coupon->code );
		},
	) );

	$reg->write( 'fluent-cart/update-coupon', array(
		'label'       => 'Update Coupon',
		'description' => 'Update a FluentCart coupon by ID.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'          => array( 'type' => 'integer', 'description' => 'Coupon ID' ),
				'status'      => array( 'type' => 'string', 'description' => 'Status: active, inactive' ),
				'amount'      => array( 'type' => 'number', 'description' => 'New discount value' ),
				'usage_limit' => array( 'type' => 'integer', 'description' => 'New usage limit' ),
				'expires_at'  => array( 'type' => 'string', 'description' => 'New expiry date (YYYY-MM-DD)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$coupon = \FluentCart\App\Models\Coupon::find( intval( $input['id'] ) );
			if ( ! $coupon ) {
				return fluent_abilities_error( 'not_found', 'Coupon not found.' );
			}
			if ( isset( $input['status'] ) ) $coupon->status = sanitize_text_field( $input['status'] );
			if ( isset( $input['amount'] ) ) $coupon->amount = (float) $input['amount'];
			if ( isset( $input['usage_limit'] ) ) $coupon->usage_limit = intval( $input['usage_limit'] );
			if ( isset( $input['expires_at'] ) ) $coupon->expires_at = sanitize_text_field( $input['expires_at'] );
			$coupon->save();
			return array( 'success' => true, 'id' => $coupon->id );
		},
	) );

	// =========================================================================
	// TRANSACTIONS
	// =========================================================================

	$reg->read( 'fluent-cart/list-transactions', array(
		'label'       => 'List Transactions',
		'description' => 'List FluentCart transactions with optional filtering by order_id, customer_id, status, and date range. Monetary values in decimal.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'order_id'    => array( 'type' => 'integer', 'description' => 'Filter by order ID' ),
				'customer_id' => array( 'type' => 'integer', 'description' => 'Filter by customer ID' ),
				'status'      => array( 'type' => 'string', 'description' => 'Filter by status: paid, pending, failed, refunded' ),
				'date_from'   => array( 'type' => 'string', 'description' => 'Filter from date (YYYY-MM-DD)' ),
				'date_to'     => array( 'type' => 'string', 'description' => 'Filter to date (YYYY-MM-DD)' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'transactions', array(
			'id'         => array( 'type' => 'integer' ),
			'order_id'   => array( 'type' => 'integer' ),
			'amount'     => array( 'type' => 'number' ),
			'status'     => array( 'type' => 'string' ),
			'created_at' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$query = \FluentCart\App\Models\OrderTransaction::query();

			if ( ! empty( $input['order_id'] ) ) $query->where( 'order_id', intval( $input['order_id'] ) );
			if ( ! empty( $input['customer_id'] ) ) $query->where( 'customer_id', intval( $input['customer_id'] ) );
			if ( ! empty( $input['status'] ) ) $query->where( 'status', sanitize_text_field( $input['status'] ) );
			if ( ! empty( $input['date_from'] ) ) $query->where( 'created_at', '>=', sanitize_text_field( $input['date_from'] ) . ' 00:00:00' );
			if ( ! empty( $input['date_to'] ) ) $query->where( 'created_at', '<=', sanitize_text_field( $input['date_to'] ) . ' 23:59:59' );

			$total = $query->count();
			$items = $query->orderBy( 'id', 'DESC' )->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();

			return array(
				'transactions' => array_map( function( $t ) {
					return array(
						'id'          => $t->id,
						'order_id'    => $t->order_id,
						'amount'      => fluent_cart_format_money( $t->amount ),
						'status'      => $t->status,
						'created_at'  => (string) $t->created_at,
					);
				}, $items->all() ),
				'total'    => $total,
				'page'     => $pagination['page'],
				'per_page' => $pagination['per_page'],
			);
		},
	) );

	// =========================================================================
	// REFUNDS
	// =========================================================================

	$reg->write( 'fluent-cart/create-refund', array(
		'label'       => 'Create Refund',
		'description' => 'Create a refund for a FluentCart order. Amount in cents.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'order_id' ),
			'properties' => array(
				'order_id' => array( 'type' => 'integer', 'description' => 'Order ID to refund' ),
				'amount'   => array( 'type' => 'integer', 'description' => 'Refund amount in cents (default: full order amount)' ),
				'reason'   => array( 'type' => 'string', 'description' => 'Reason for refund' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'order_id'      => array( 'type' => 'integer' ),
			'refund_amount' => array( 'type' => 'number' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback'    => function( $input ) {
			$order = \FluentCart\App\Models\Order::find( intval( $input['order_id'] ) );
			if ( ! $order ) {
				return fluent_abilities_error( 'not_found', 'Order not found.' );
			}
			$amount = isset( $input['amount'] ) ? intval( $input['amount'] ) : $order->total;
			\FluentCart\App\Models\OrderTransaction::create( array(
				'order_id'    => $order->id,
				'amount'      => $amount,
				'status'      => 'refunded',
				'type'        => 'refund',
				'note'        => isset( $input['reason'] ) ? sanitize_text_field( $input['reason'] ) : '',
			) );
			$order->status = 'refunded';
			$order->save();
			return array( 'success' => true, 'order_id' => $order->id, 'refund_amount' => fluent_cart_format_money( $amount ) );
		},
	) );

	// =========================================================================
	// SETTINGS
	// =========================================================================

	$reg->read( 'fluent-cart/get-settings', array(
		'label'       => 'Get FluentCart Settings',
		'description' => 'Get FluentCart store settings: currency, payment methods, general configuration.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type' => 'object',
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'currency'        => array( 'type' => 'string' ),
			'currency_symbol' => array( 'type' => 'string' ),
			'settings'        => array( 'type' => 'object' ),
		) ),
		'callback' => function( $input ) {
			$settings = get_option( 'fluent_cart_settings', array() );
			return array(
				'currency'        => $settings['currency'] ?? 'USD',
				'currency_symbol' => $settings['currency_symbol'] ?? '$',
				'settings'        => $settings,
			);
		},
	) );

	// =========================================================================
	// ACTIVITY LOG
	// =========================================================================

	$reg->read( 'fluent-cart/list-order-activities', array(
		'label'       => 'List Order Activities',
		'description' => 'List activity log entries for a FluentCart order (status changes, notes, etc.).',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'order_id' ),
			'properties' => array(
				'order_id' => array( 'type' => 'integer', 'description' => 'Order ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'activities', array(
			'id'         => array( 'type' => 'integer' ),
			'type'       => array( 'type' => 'string' ),
			'note'       => array( 'type' => 'string' ),
			'created_at' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$order = \FluentCart\App\Models\Order::find( intval( $input['order_id'] ) );
			if ( ! $order ) {
				return fluent_abilities_error( 'not_found', 'Order not found.' );
			}
			$activities = \FluentCart\App\Models\Activity::where( 'module_type', 'order' )
				->where( 'module_id', $order->id )
				->orderBy( 'created_at', 'DESC' )
				->get();
			$items = array_map( function( $a ) {
				return array(
					'id'         => $a->id,
					'type'       => $a->log_type,
					'note'       => $a->content ?? '',
					'created_at' => (string) $a->created_at,
				);
			}, $activities->all() );
			return array( 'activities' => $items, 'total' => count( $items ) );
		},
	) );

	// =========================================================================
	// CART ABANDONMENT
	// =========================================================================


	// =========================================================================
	// WEBHOOKS
	// =========================================================================

	$reg->read( 'fluent-cart/list-webhooks', array(
		'label'       => 'List Webhooks',
		'description' => 'List all configured FluentCart webhooks.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type' => 'object',
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'webhooks', array(
			'id'         => array( 'type' => 'integer' ),
			'url'        => array( 'type' => 'string' ),
			'events'     => array( 'type' => 'string' ),
			'status'     => array( 'type' => 'string' ),
			'created_at' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$webhooks = get_option( 'fluent_cart_webhooks', array() );
			if ( ! is_array( $webhooks ) ) $webhooks = array();
			return array( 'webhooks' => $webhooks, 'total' => count( $webhooks ) );
		},
	) );

	$reg->write( 'fluent-cart/create-webhook', array(
		'label'       => 'Create Webhook',
		'description' => 'Register a new FluentCart webhook endpoint.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'url', 'events' ),
			'properties' => array(
				'url'    => array( 'type' => 'string', 'description' => 'Webhook URL (must be https)' ),
				'events' => array( 'type' => 'string', 'description' => 'Comma-separated event names: order.completed, subscription.cancelled, etc.' ),
				'status' => array( 'type' => 'string', 'description' => 'Status: active, inactive (default: active)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback'    => function( $input ) {
			$url = esc_url_raw( $input['url'] );
			if ( strpos( $url, 'https://' ) !== 0 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Webhook URL must use HTTPS.' );
			}
			$webhooks = get_option( 'fluent_cart_webhooks', array() );
			if ( ! is_array( $webhooks ) ) $webhooks = array();
			$new_id = count( $webhooks ) + 1;
			$webhooks[] = array(
				'id'         => $new_id,
				'url'        => $url,
				'events'     => sanitize_text_field( $input['events'] ),
				'status'     => sanitize_text_field( $input['status'] ?? 'active' ),
				'created_at' => current_time( 'mysql' ),
			);
			update_option( 'fluent_cart_webhooks', $webhooks );
			return array( 'success' => true, 'id' => $new_id );
		},
	) );

	$reg->delete( 'fluent-cart/delete-webhook', array(
		'label'       => 'Delete Webhook',
		'description' => 'Delete a FluentCart webhook by ID.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Webhook ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback'    => function( $input ) {
			$id = intval( $input['id'] );
			$webhooks = get_option( 'fluent_cart_webhooks', array() );
			if ( ! is_array( $webhooks ) ) {
				return fluent_abilities_error( 'not_found', 'Webhook not found.' );
			}
			$original_count = count( $webhooks );
			$webhooks = array_values( array_filter( $webhooks, function( $w ) use ( $id ) {
				return (int) $w['id'] !== $id;
			} ) );
			if ( count( $webhooks ) === $original_count ) {
				return fluent_abilities_error( 'not_found', 'Webhook not found.' );
			}
			update_option( 'fluent_cart_webhooks', $webhooks );
			return array( 'success' => true, 'id' => $id );
		},
	) );

	// =========================================================================
	// TAX RATES
	// =========================================================================

	$reg->read( 'fluent-cart/list-tax-rates', array(
		'label'       => 'List Tax Rates',
		'description' => 'List all FluentCart tax rate configurations.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type' => 'object',
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'tax_rates', array(
			'id'       => array( 'type' => 'integer' ),
			'name'     => array( 'type' => 'string' ),
			'rate'     => array( 'type' => 'number' ),
			'country'  => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			global $wpdb;
			$table = $wpdb->prefix . 'fct_tax_rates';
			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
				return array( 'tax_rates' => array(), 'total' => 0 );
			}
			$rows = $wpdb->get_results( "SELECT id, name, rate, country FROM {$table} ORDER BY id ASC" );
			$items = array_map( function( $r ) {
				return array( 'id' => (int) $r->id, 'name' => $r->name, 'rate' => (float) $r->rate, 'country' => $r->country );
			}, $rows ?: array() );
			return array( 'tax_rates' => $items, 'total' => count( $items ) );
		},
	) );

	// =========================================================================
	// SHIPPING
	// =========================================================================


	// =========================================================================
	// RETENTION
	// =========================================================================

	// =========================================================================
	// ORDER ITEMS (P1)
	// =========================================================================

	$reg->read( 'fluent-cart/list-order-items', array(
		'label'       => 'List Order Items',
		'description' => 'List line items for a FluentCart order. Enables per-item queries like "all orders containing product X". Money values in decimal.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'order_id' ),
			'properties' => array(
				'order_id' => array(
					'type'        => 'integer',
					'description' => 'Order ID',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'items', array(
			'id'                 => array( 'type' => 'integer' ),
			'order_id'           => array( 'type' => 'integer' ),
			'post_id'            => array( 'type' => 'integer' ),
			'title'              => array( 'type' => 'string' ),
			'fulfillment_type'   => array( 'type' => 'string' ),
			'payment_type'       => array( 'type' => 'string' ),
			'quantity'           => array( 'type' => 'integer' ),
			'unit_price'         => array( 'type' => 'number' ),
			'subtotal'           => array( 'type' => 'number' ),
			'tax_amount'         => array( 'type' => 'number' ),
			'discount_total'     => array( 'type' => 'number' ),
			'line_total'         => array( 'type' => 'number' ),
			'fulfilled_quantity' => array( 'type' => 'integer' ),
			'object_id'          => array( 'type' => array( 'integer', 'null' ) ),
			'created_at'         => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$order = \FluentCart\App\Models\Order::find( (int) $input['order_id'] );
			if ( ! $order ) {
				return fluent_abilities_error( 'not_found', 'Order not found.' );
			}

			$items = \FluentCart\App\Models\OrderItem::where( 'order_id', $order->id )
				->orderBy( 'cart_index', 'ASC' )
				->get();

			$result = array();
			foreach ( $items as $item ) {
				$result[] = array(
					'id'                 => (int) $item->id,
					'order_id'           => (int) $item->order_id,
					'post_id'            => (int) $item->post_id,
					'title'              => (string) ( $item->title ?? '' ),
					'fulfillment_type'   => (string) ( $item->fulfillment_type ?? '' ),
					'payment_type'       => (string) ( $item->payment_type ?? '' ),
					'quantity'           => (int) $item->quantity,
					'unit_price'         => fluent_cart_format_money( $item->unit_price ),
					'subtotal'           => fluent_cart_format_money( $item->subtotal ),
					'tax_amount'         => fluent_cart_format_money( $item->tax_amount ),
					'discount_total'     => fluent_cart_format_money( $item->discount_total ),
					'line_total'         => fluent_cart_format_money( $item->line_total ),
					'fulfilled_quantity' => (int) $item->fulfilled_quantity,
					'object_id'          => $item->object_id !== null ? (int) $item->object_id : null,
					'created_at'         => $item->created_at ? (string) $item->created_at : null,
				);
			}

			return array( 'items' => $result, 'total' => count( $result ) );
		},
	) );

	$reg->write( 'fluent-cart/add-order-item', array(
		'label'       => 'Add Order Item',
		'description' => 'Add a line item to an existing FluentCart order. For manual orders. Price in cents.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'order_id', 'post_id', 'quantity', 'unit_price' ),
			'properties' => array(
				'order_id'         => array( 'type' => 'integer', 'description' => 'Order ID' ),
				'post_id'          => array( 'type' => 'integer', 'description' => 'Product ID (post_id)' ),
				'quantity'         => array( 'type' => 'integer', 'description' => 'Quantity' ),
				'unit_price'       => array( 'type' => 'integer', 'description' => 'Unit price in cents' ),
				'title'            => array( 'type' => 'string', 'description' => 'Line item title (defaults to product title)' ),
				'fulfillment_type' => array( 'type' => 'string', 'description' => 'Fulfillment type: digital, physical (default: digital)' ),
				'payment_type'     => array( 'type' => 'string', 'description' => 'Payment type: one_time, subscription (default: one_time)' ),
				'object_id'        => array( 'type' => 'integer', 'description' => 'Variation ID (optional)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'item_id'  => array( 'type' => 'integer' ),
			'order_id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback'    => function( $input ) {
			$order = \FluentCart\App\Models\Order::find( (int) $input['order_id'] );
			if ( ! $order ) {
				return fluent_abilities_error( 'not_found', 'Order not found.' );
			}

			$product = \FluentCart\App\Models\Product::find( (int) $input['post_id'] );
			if ( ! $product ) {
				return fluent_abilities_error( 'not_found', 'Product not found.' );
			}

			$unit_price = (int) $input['unit_price'];
			$quantity   = (int) $input['quantity'];
			$line_total = $unit_price * $quantity;

			$title = ! empty( $input['title'] )
				? sanitize_text_field( $input['title'] )
				: ( $product->post_title ?? 'Product #' . $product->ID );

			// Get max cart_index for this order.
			$max_index = \FluentCart\App\Models\OrderItem::where( 'order_id', $order->id )->max( 'cart_index' );

			$item = \FluentCart\App\Models\OrderItem::create( array(
				'order_id'           => $order->id,
				'post_id'            => (int) $input['post_id'],
				'post_title'         => $title,
				'title'              => $title,
				'fulfillment_type'   => sanitize_text_field( $input['fulfillment_type'] ?? 'digital' ),
				'payment_type'       => sanitize_text_field( $input['payment_type'] ?? 'one_time' ),
				'object_id'          => isset( $input['object_id'] ) ? (int) $input['object_id'] : null,
				'cart_index'         => ( $max_index ?? 0 ) + 1,
				'quantity'           => $quantity,
				'unit_price'         => $unit_price,
				'cost'               => 0,
				'subtotal'           => $line_total,
				'tax_amount'         => 0,
				'shipping_charge'    => 0,
				'discount_total'     => 0,
				'line_total'         => $line_total,
				'refund_total'       => 0,
				'rate'               => 0,
				'fulfilled_quantity' => 0,
			) );

			return array(
				'success'  => true,
				'item_id'  => (int) $item->id,
				'order_id' => (int) $order->id,
			);
		},
	) );

	// =========================================================================
	// ORDER ADDRESSES (P1)
	// =========================================================================

	// =========================================================================
	// CUSTOMER ADDRESSES (P1)
	// =========================================================================

	$reg->read( 'fluent-cart/list-customer-addresses', array(
		'label'       => 'List Customer Addresses',
		'description' => 'List address book entries for a FluentCart customer.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'customer_id' ),
			'properties' => array(
				'customer_id' => array(
					'type'        => 'integer',
					'description' => 'Customer ID',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'addresses', array(
			'id'          => array( 'type' => 'integer' ),
			'customer_id' => array( 'type' => 'integer' ),
			'is_primary'  => array( 'type' => 'integer' ),
			'type'        => array( 'type' => 'string' ),
			'status'      => array( 'type' => 'string' ),
			'label'       => array( 'type' => 'string' ),
			'name'        => array( 'type' => array( 'string', 'null' ) ),
			'address_1'   => array( 'type' => array( 'string', 'null' ) ),
			'address_2'   => array( 'type' => array( 'string', 'null' ) ),
			'city'        => array( 'type' => array( 'string', 'null' ) ),
			'state'       => array( 'type' => array( 'string', 'null' ) ),
			'phone'       => array( 'type' => array( 'string', 'null' ) ),
			'email'       => array( 'type' => array( 'string', 'null' ) ),
			'postcode'    => array( 'type' => array( 'string', 'null' ) ),
			'country'     => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$customer = \FluentCart\App\Models\Customer::find( (int) $input['customer_id'] );
			if ( ! $customer ) {
				return fluent_abilities_error( 'not_found', 'Customer not found.' );
			}

			$addresses = \FluentCart\App\Models\CustomerAddresses::where( 'customer_id', $customer->id )
				->orderBy( 'is_primary', 'DESC' )
				->get();

			$items = array();
			foreach ( $addresses as $addr ) {
				$items[] = array(
					'id'          => (int) $addr->id,
					'customer_id' => (int) $addr->customer_id,
					'is_primary'  => (int) $addr->is_primary,
					'type'        => (string) ( $addr->type ?? '' ),
					'status'      => (string) ( $addr->status ?? '' ),
					'label'       => (string) ( $addr->label ?? '' ),
					'name'        => $addr->name ?? null,
					'address_1'   => $addr->address_1 ?? null,
					'address_2'   => $addr->address_2 ?? null,
					'city'        => $addr->city ?? null,
					'state'       => $addr->state ?? null,
					'phone'       => $addr->phone ?? null,
					'email'       => $addr->email ?? null,
					'postcode'    => $addr->postcode ?? null,
					'country'     => $addr->country ?? null,
				);
			}

			return array( 'addresses' => $items, 'total' => count( $items ) );
		},
	) );

	// =========================================================================
	// LABELS (P1)
	// =========================================================================

	$reg->read( 'fluent-cart/list-labels', array(
		'label'       => 'List Labels',
		'description' => 'List all FluentCart labels (tags) used for categorizing orders, customers, and products.',
		'input_schema' => array(
			'type' => 'object',
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'labels', array(
			'id'         => array( 'type' => 'integer' ),
			'value'      => array( 'type' => 'string' ),
			'created_at' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$labels = \FluentCart\App\Models\Label::orderBy( 'value', 'ASC' )->get();
			$items = array();
			foreach ( $labels as $label ) {
				$items[] = array(
					'id'         => (int) $label->id,
					'value'      => (string) ( $label->value ?? '' ),
					'created_at' => $label->created_at ? (string) $label->created_at : null,
				);
			}
			return array( 'labels' => $items, 'total' => count( $items ) );
		},
	) );

	$reg->write( 'fluent-cart/assign-label', array(
		'label'       => 'Assign Label',
		'description' => 'Assign a label to a FluentCart object (order, customer, or product). Polymorphic relationship.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'label_id', 'labelable_id', 'labelable_type' ),
			'properties' => array(
				'label_id'       => array( 'type' => 'integer', 'description' => 'Label ID' ),
				'labelable_id'   => array( 'type' => 'integer', 'description' => 'Object ID (order, customer, or product ID)' ),
				'labelable_type' => array( 'type' => 'string', 'description' => 'Object type: order, customer, product' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'relationship_id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => true ),
		'callback'    => function( $input ) {
			$label = \FluentCart\App\Models\Label::find( (int) $input['label_id'] );
			if ( ! $label ) {
				return fluent_abilities_error( 'not_found', 'Label not found.' );
			}

			$labelable_type = sanitize_text_field( $input['labelable_type'] );
			$allowed_types  = array( 'order', 'customer', 'product' );
			if ( ! in_array( $labelable_type, $allowed_types, true ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'labelable_type must be: order, customer, or product' );
			}

			$labelable_id = (int) $input['labelable_id'];

			// Check if relationship already exists (idempotent).
			$existing = \FluentCart\App\Models\LabelRelationship::where( 'label_id', $label->id )
				->where( 'labelable_id', $labelable_id )
				->where( 'labelable_type', $labelable_type )
				->first();

			if ( $existing ) {
				return array( 'success' => true, 'relationship_id' => (int) $existing->id );
			}

			$rel = \FluentCart\App\Models\LabelRelationship::create( array(
				'label_id'       => $label->id,
				'labelable_id'   => $labelable_id,
				'labelable_type' => $labelable_type,
			) );

			return array( 'success' => true, 'relationship_id' => (int) $rel->id );
		},
	) );

	// =========================================================================
	// SUBSCRIPTION UPDATE (P1)
	// =========================================================================

	$reg->write( 'fluent-cart/update-subscription', array(
		'label'       => 'Update Subscription',
		'description' => 'Update subscription details: next billing date, recurring amount, billing interval. Goes beyond status changes.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'                => array( 'type' => 'integer', 'description' => 'Subscription ID' ),
				'next_billing_date' => array( 'type' => 'string', 'description' => 'Next billing date (YYYY-MM-DD HH:MM:SS)' ),
				'recurring_amount'  => array( 'type' => 'integer', 'description' => 'Recurring amount in cents' ),
				'billing_interval'  => array( 'type' => 'string', 'description' => 'Billing interval (e.g., "1")' ),
				'bill_times'        => array( 'type' => 'integer', 'description' => 'Total number of billing cycles (0 = unlimited)' ),
				'status'            => array( 'type' => 'string', 'description' => 'Subscription status: active, paused, cancelled, expired, pending' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$sub = \FluentCart\App\Models\Subscription::find( (int) $input['id'] );
			if ( ! $sub ) {
				return fluent_abilities_error( 'not_found', 'Subscription not found.' );
			}

			if ( isset( $input['next_billing_date'] ) ) {
				$sub->next_billing_date = sanitize_text_field( $input['next_billing_date'] );
			}
			if ( isset( $input['recurring_amount'] ) ) {
				$sub->recurring_amount = (int) $input['recurring_amount'];
			}
			if ( isset( $input['billing_interval'] ) ) {
				$sub->billing_interval = sanitize_text_field( $input['billing_interval'] );
			}
			if ( isset( $input['bill_times'] ) ) {
				$sub->bill_times = (int) $input['bill_times'];
			}
			if ( isset( $input['status'] ) ) {
				$sub->status = sanitize_text_field( $input['status'] );
			}

			$sub->save();

			return array( 'success' => true, 'id' => (int) $sub->id );
		},
	) );

	// =========================================================================
	// RETENTION
	// =========================================================================


} );
