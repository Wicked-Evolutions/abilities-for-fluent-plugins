<?php
/**
 * FluentBooking — Pro Payment methods, orders, transactions (cluster 4.11).
 *
 * Pro models Order / OrderItems / Transactions at fluent-booking-pro/app/Models/.
 * §7.Q1 in the research file flagged that the Pro table schemas were not yet
 * pinpointed. Implementation uses the fcal_* prefix convention (fcal_orders,
 * fcal_order_items, fcal_transactions) and treats columns defensively (null
 * coalesce on every accessed column).
 *
 *   - fluent-booking/list-payment-methods         (read)
 *   - fluent-booking/get-payment-method           (read)
 *   - fluent-booking/update-payment-method-config (write)
 *   - fluent-booking/enable-payment-method        (write)
 *   - fluent-booking/disable-payment-method       (write)
 *   - fluent-booking/list-orders                  (read)
 *   - fluent-booking/get-order                    (read)
 *   - fluent-booking/list-transactions            (read)
 *   - fluent-booking/get-transaction              (read)
 *   - fluent-booking/refund-transaction           (write)
 *
 * Capability override: manage_options (finance surface).
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

/**
 * Payment methods are stored as an option keyed by provider slug.
 *
 * @return array
 */
function fluent_booking_payment_methods_config() {
	$cfg = get_option( '__fluent_booking_pro_payment_methods', array() );
	return is_array( $cfg ) ? $cfg : array();
}

function fluent_booking_register_orders_abilities() {

	$reg = new Fluent_Abilities_Registrar( 'booking' );

	// =========================================================================
	// 4.11.1 — LIST PAYMENT METHODS
	// =========================================================================

	$reg->read( 'fluent-booking/list-payment-methods', array(
		'label'       => 'List Payment Methods',
		'description' => 'List FluentBooking Pro payment-method providers and their enabled state.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'status' => array( 'type' => 'string', 'enum' => array( 'enabled', 'disabled' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'methods', array(
			'slug'       => array( 'type' => 'string' ),
			'label'      => array( 'type' => 'string' ),
			'enabled'    => array( 'type' => 'boolean' ),
			'mode'       => array( 'type' => array( 'string', 'null' ) ),
			'configured' => array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $input ) {
			$cfg = fluent_booking_payment_methods_config();
			$filter = isset( $input['status'] ) ? sanitize_text_field( $input['status'] ) : '';

			$methods = array();
			foreach ( $cfg as $slug => $row ) {
				$row    = is_array( $row ) ? $row : array();
				$enabled = ! empty( $row['enabled'] );
				if ( $filter === 'enabled' && ! $enabled ) {
					continue;
				}
				if ( $filter === 'disabled' && $enabled ) {
					continue;
				}
				$methods[] = array(
					'slug'       => (string) $slug,
					'label'      => (string) ( $row['label'] ?? ucwords( str_replace( array( '-', '_' ), ' ', (string) $slug ) ) ),
					'enabled'    => $enabled,
					'mode'       => isset( $row['mode'] ) ? (string) $row['mode'] : null,
					'configured' => ! empty( $row['settings'] ),
				);
			}

			return array( 'methods' => $methods, 'total' => count( $methods ) );
		},
	) );

	// =========================================================================
	// 4.11.2 — GET PAYMENT METHOD
	// =========================================================================

	$reg->read( 'fluent-booking/get-payment-method', array(
		'label'       => 'Get Payment Method',
		'description' => 'Return a single payment-method config block. Secret fields are redacted from output.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'slug' ),
			'properties' => array(
				'slug' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'slug'     => array( 'type' => 'string' ),
			'label'    => array( 'type' => 'string' ),
			'enabled'  => array( 'type' => 'boolean' ),
			'mode'     => array( 'type' => array( 'string', 'null' ) ),
			'settings' => array( 'type' => array( 'object', 'array' ) ),
		) ),
		'callback' => function( $input ) {
			$slug = sanitize_text_field( $input['slug'] );
			$cfg  = fluent_booking_payment_methods_config();
			$row  = $cfg[ $slug ] ?? null;
			if ( ! is_array( $row ) ) {
				return fluent_abilities_error( 'not_found', 'Payment method not found' );
			}
			$settings = isset( $row['settings'] ) ? (array) $row['settings'] : array();
			foreach ( array( 'secret_key', 'webhook_secret', 'private_key', 'client_secret', 'api_secret' ) as $sensitive ) {
				if ( isset( $settings[ $sensitive ] ) && $settings[ $sensitive ] !== '' ) {
					$settings[ $sensitive ] = '***redacted***';
				}
			}

			return array(
				'slug'     => $slug,
				'label'    => (string) ( $row['label'] ?? ucwords( str_replace( array( '-', '_' ), ' ', $slug ) ) ),
				'enabled'  => ! empty( $row['enabled'] ),
				'mode'     => isset( $row['mode'] ) ? (string) $row['mode'] : null,
				'settings' => $settings,
			);
		},
	) );

	// =========================================================================
	// 4.11.3 — UPDATE PAYMENT METHOD CONFIG
	// =========================================================================

	$reg->write( 'fluent-booking/update-payment-method-config', array(
		'label'       => 'Update Payment Method Config',
		'description' => 'Partial-merge update of a payment-method config block. Provider-specific settings shape depends on the slug (e.g. stripe expects publishable_key + secret_key).',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'slug', 'settings' ),
			'properties' => array(
				'slug'     => array( 'type' => 'string' ),
				'mode'     => array( 'type' => 'string', 'enum' => array( 'live', 'test' ) ),
				'settings' => array( 'type' => array( 'object', 'array' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'slug' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$slug = sanitize_text_field( $input['slug'] );
			$cfg  = fluent_booking_payment_methods_config();
			$row  = isset( $cfg[ $slug ] ) && is_array( $cfg[ $slug ] ) ? $cfg[ $slug ] : array();

			$settings = isset( $row['settings'] ) && is_array( $row['settings'] ) ? $row['settings'] : array();
			$incoming = isset( $input['settings'] ) ? (array) $input['settings'] : array();
			$row['settings'] = array_replace_recursive( $settings, $incoming );

			if ( isset( $input['mode'] ) ) {
				$row['mode'] = sanitize_text_field( $input['mode'] );
			}

			$cfg[ $slug ] = $row;
			update_option( '__fluent_booking_pro_payment_methods', $cfg );

			return array( 'success' => true, 'slug' => $slug );
		},
	) );

	// =========================================================================
	// 4.11.4 — ENABLE PAYMENT METHOD
	// =========================================================================

	// =========================================================================
	// 4.11.5 — DISABLE PAYMENT METHOD
	// =========================================================================

	$reg->write( 'fluent-booking/disable-payment-method', array(
		'label'       => 'Disable Payment Method',
		'description' => 'Set a payment-method provider to enabled=false.',
		'capability'  => 'manage_options',
		'annotations' => array( 'idempotent' => true ),
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'slug' ),
			'properties' => array(
				'slug' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'slug'    => array( 'type' => 'string' ),
			'enabled' => array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $input ) {
			$slug = sanitize_text_field( $input['slug'] );
			$cfg  = fluent_booking_payment_methods_config();
			$row  = isset( $cfg[ $slug ] ) && is_array( $cfg[ $slug ] ) ? $cfg[ $slug ] : array();
			$row['enabled'] = false;
			$cfg[ $slug ]   = $row;
			update_option( '__fluent_booking_pro_payment_methods', $cfg );
			return array( 'success' => true, 'slug' => $slug, 'enabled' => false );
		},
	) );

	// =========================================================================
	// 4.11.6 — LIST ORDERS
	// =========================================================================

	$reg->read( 'fluent-booking/list-orders', array(
		'label'       => 'List Orders',
		'description' => 'List FluentBooking Pro orders with filters and pagination.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge(
				fluent_abilities_pagination_schema(),
				array(
					'status'         => array( 'type' => 'string' ),
					'payment_status' => array( 'type' => 'string' ),
					'date_from'      => array( 'type' => 'string' ),
					'date_to'        => array( 'type' => 'string' ),
					'customer_email' => array( 'type' => 'string' ),
				)
			),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'orders', array(
			'id'             => array( 'type' => 'integer' ),
			'booking_id'     => array( 'type' => array( 'integer', 'null' ) ),
			'customer_email' => array( 'type' => array( 'string', 'null' ) ),
			'total'          => array( 'type' => 'number' ),
			'currency'       => array( 'type' => array( 'string', 'null' ) ),
			'status'         => array( 'type' => 'string' ),
			'payment_status' => array( 'type' => array( 'string', 'null' ) ),
			'created_at'     => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$page_args = fluent_abilities_pagination( $input, 20 );
			$query     = wpFluent()->table( 'fcal_orders' );

			if ( ! empty( $input['status'] ) ) {
				$query->where( 'status', sanitize_text_field( $input['status'] ) );
			}
			if ( ! empty( $input['payment_status'] ) ) {
				$query->where( 'payment_status', sanitize_text_field( $input['payment_status'] ) );
			}
			if ( ! empty( $input['date_from'] ) ) {
				$query->where( 'created_at', '>=', sanitize_text_field( $input['date_from'] ) . ' 00:00:00' );
			}
			if ( ! empty( $input['date_to'] ) ) {
				$query->where( 'created_at', '<=', sanitize_text_field( $input['date_to'] ) . ' 23:59:59' );
			}
			if ( ! empty( $input['customer_email'] ) ) {
				$query->where( 'customer_email', sanitize_email( $input['customer_email'] ) );
			}

			try {
				$total = (int) $query->count();
				$rows  = $query->orderBy( 'id', 'DESC' )
					->offset( $page_args['offset'] )
					->limit( $page_args['per_page'] )
					->get();
			} catch ( \Exception $e ) {
				return fluent_abilities_error( 'orders_table_missing', 'fcal_orders table not present (Pro plugin not installed?)' );
			}

			$orders = array();
			foreach ( $rows as $row ) {
				$orders[] = array(
					'id'             => (int) $row->id,
					'booking_id'     => isset( $row->booking_id ) ? (int) $row->booking_id : null,
					'customer_email' => isset( $row->customer_email ) ? (string) $row->customer_email : null,
					'total'          => isset( $row->total ) ? (float) $row->total : 0.0,
					'currency'       => isset( $row->currency ) ? (string) $row->currency : null,
					'status'         => (string) ( $row->status ?? '' ),
					'payment_status' => isset( $row->payment_status ) ? (string) $row->payment_status : null,
					'created_at'     => $row->created_at ? (string) $row->created_at : null,
				);
			}

			return array(
				'orders'   => $orders,
				'total'    => $total,
				'page'     => $page_args['page'],
				'per_page' => $page_args['per_page'],
			);
		},
	) );

	// =========================================================================
	// 4.11.7 — GET ORDER
	// =========================================================================

	$reg->read( 'fluent-booking/get-order', array(
		'label'       => 'Get Order',
		'description' => 'Return a single order including items and transactions.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'             => array( 'type' => 'integer' ),
			'booking_id'     => array( 'type' => array( 'integer', 'null' ) ),
			'customer_email' => array( 'type' => array( 'string', 'null' ) ),
			'total'          => array( 'type' => 'number' ),
			'currency'       => array( 'type' => array( 'string', 'null' ) ),
			'status'         => array( 'type' => 'string' ),
			'items'          => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'transactions'   => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'created_at'     => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$id  = (int) $input['id'];
			try {
				$order = wpFluent()->table( 'fcal_orders' )->where( 'id', $id )->first();
			} catch ( \Exception $e ) {
				return fluent_abilities_error( 'orders_table_missing', 'fcal_orders table not present' );
			}
			if ( ! $order ) {
				return fluent_abilities_error( 'not_found', 'Order not found' );
			}

			$items = array();
			try {
				$item_rows = wpFluent()->table( 'fcal_order_items' )->where( 'order_id', $id )->get();
				foreach ( $item_rows as $r ) {
					$items[] = (array) $r;
				}
			} catch ( \Exception $e ) {
				$items = array();
			}

			$txns = array();
			try {
				$txn_rows = wpFluent()->table( 'fcal_transactions' )->where( 'order_id', $id )->get();
				foreach ( $txn_rows as $r ) {
					$txns[] = (array) $r;
				}
			} catch ( \Exception $e ) {
				$txns = array();
			}

			return array(
				'id'             => (int) $order->id,
				'booking_id'     => isset( $order->booking_id ) ? (int) $order->booking_id : null,
				'customer_email' => isset( $order->customer_email ) ? (string) $order->customer_email : null,
				'total'          => isset( $order->total ) ? (float) $order->total : 0.0,
				'currency'       => isset( $order->currency ) ? (string) $order->currency : null,
				'status'         => (string) ( $order->status ?? '' ),
				'items'          => $items,
				'transactions'   => $txns,
				'created_at'     => $order->created_at ? (string) $order->created_at : null,
			);
		},
	) );

	// =========================================================================
	// 4.11.8 — LIST TRANSACTIONS
	// =========================================================================

	$reg->read( 'fluent-booking/list-transactions', array(
		'label'       => 'List Transactions',
		'description' => 'List payment transactions with filters and pagination.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge(
				fluent_abilities_pagination_schema(),
				array(
					'order_id'       => array( 'type' => 'integer' ),
					'status'         => array( 'type' => 'string' ),
					'payment_method' => array( 'type' => 'string' ),
					'date_from'      => array( 'type' => 'string' ),
					'date_to'        => array( 'type' => 'string' ),
				)
			),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'transactions', array(
			'id'             => array( 'type' => 'integer' ),
			'order_id'       => array( 'type' => array( 'integer', 'null' ) ),
			'payment_method' => array( 'type' => array( 'string', 'null' ) ),
			'amount'         => array( 'type' => 'number' ),
			'currency'       => array( 'type' => array( 'string', 'null' ) ),
			'status'         => array( 'type' => 'string' ),
			'created_at'     => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$page_args = fluent_abilities_pagination( $input, 20 );
			$query     = wpFluent()->table( 'fcal_transactions' );

			foreach ( array( 'order_id', 'status', 'payment_method' ) as $f ) {
				if ( ! empty( $input[ $f ] ) ) {
					$query->where( $f, sanitize_text_field( (string) $input[ $f ] ) );
				}
			}
			if ( ! empty( $input['date_from'] ) ) {
				$query->where( 'created_at', '>=', sanitize_text_field( $input['date_from'] ) . ' 00:00:00' );
			}
			if ( ! empty( $input['date_to'] ) ) {
				$query->where( 'created_at', '<=', sanitize_text_field( $input['date_to'] ) . ' 23:59:59' );
			}

			try {
				$total = (int) $query->count();
				$rows  = $query->orderBy( 'id', 'DESC' )
					->offset( $page_args['offset'] )
					->limit( $page_args['per_page'] )
					->get();
			} catch ( \Exception $e ) {
				return fluent_abilities_error( 'transactions_table_missing', 'fcal_transactions table not present (Pro plugin not installed?)' );
			}

			$txns = array();
			foreach ( $rows as $row ) {
				$txns[] = array(
					'id'             => (int) $row->id,
					'order_id'       => isset( $row->order_id ) ? (int) $row->order_id : null,
					'payment_method' => isset( $row->payment_method ) ? (string) $row->payment_method : null,
					'amount'         => isset( $row->amount ) ? (float) $row->amount : 0.0,
					'currency'       => isset( $row->currency ) ? (string) $row->currency : null,
					'status'         => (string) ( $row->status ?? '' ),
					'created_at'     => $row->created_at ? (string) $row->created_at : null,
				);
			}

			return array(
				'transactions' => $txns,
				'total'        => $total,
				'page'         => $page_args['page'],
				'per_page'     => $page_args['per_page'],
			);
		},
	) );

	// =========================================================================
	// 4.11.9 — GET TRANSACTION
	// =========================================================================

	$reg->read( 'fluent-booking/get-transaction', array(
		'label'       => 'Get Transaction',
		'description' => 'Return a single transaction record by ID.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'             => array( 'type' => 'integer' ),
			'order_id'       => array( 'type' => array( 'integer', 'null' ) ),
			'payment_method' => array( 'type' => array( 'string', 'null' ) ),
			'amount'         => array( 'type' => 'number' ),
			'currency'       => array( 'type' => array( 'string', 'null' ) ),
			'status'         => array( 'type' => 'string' ),
			'metadata'       => array( 'type' => array( 'object', 'array', 'null' ) ),
			'created_at'     => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			try {
				$row = wpFluent()->table( 'fcal_transactions' )->where( 'id', (int) $input['id'] )->first();
			} catch ( \Exception $e ) {
				return fluent_abilities_error( 'transactions_table_missing', 'fcal_transactions table not present' );
			}
			if ( ! $row ) {
				return fluent_abilities_error( 'not_found', 'Transaction not found' );
			}

			$metadata = maybe_unserialize( $row->metadata ?? '' );

			return array(
				'id'             => (int) $row->id,
				'order_id'       => isset( $row->order_id ) ? (int) $row->order_id : null,
				'payment_method' => isset( $row->payment_method ) ? (string) $row->payment_method : null,
				'amount'         => isset( $row->amount ) ? (float) $row->amount : 0.0,
				'currency'       => isset( $row->currency ) ? (string) $row->currency : null,
				'status'         => (string) ( $row->status ?? '' ),
				'metadata'       => is_array( $metadata ) ? fluent_abilities_safe_array( $metadata ) : null,
				'created_at'     => $row->created_at ? (string) $row->created_at : null,
			);
		},
	) );

	// =========================================================================
	// 4.11.10 — REFUND TRANSACTION
	// =========================================================================

	$reg->write( 'fluent-booking/refund-transaction', array(
		'label'       => 'Refund Transaction',
		'description' => 'Mark a transaction as refunded and fire the fluent_booking/transaction_refunded action for Pro provider dispatch. The actual provider-side refund is performed by Pro listeners on that hook.',
		'capability'  => 'manage_options',
		'annotations' => array( 'idempotent' => false ),
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'     => array( 'type' => 'integer', 'description' => 'Transaction ID' ),
				'amount' => array( 'type' => 'number', 'description' => 'Optional refund amount (defaults to full transaction amount)' ),
				'reason' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'              => array( 'type' => 'integer' ),
			'refunded_amount' => array( 'type' => 'number' ),
		) ),
		'callback' => function( $input ) {
			$id = (int) $input['id'];
			try {
				$txn = wpFluent()->table( 'fcal_transactions' )->where( 'id', $id )->first();
			} catch ( \Exception $e ) {
				return fluent_abilities_error( 'transactions_table_missing', 'fcal_transactions table not present' );
			}
			if ( ! $txn ) {
				return fluent_abilities_error( 'not_found', 'Transaction not found' );
			}

			$refund_amount = isset( $input['amount'] ) ? (float) $input['amount'] : (float) ( $txn->amount ?? 0 );
			$reason        = isset( $input['reason'] ) ? sanitize_textarea_field( $input['reason'] ) : '';

			wpFluent()->table( 'fcal_transactions' )
				->where( 'id', $id )
				->update( array(
					'status'     => 'refunded',
					'updated_at' => current_time( 'mysql' ),
				) );

			do_action( 'fluent_booking/transaction_refunded', $id, $refund_amount, $reason, $txn );

			return array(
				'success'         => true,
				'id'              => $id,
				'refunded_amount' => $refund_amount,
			);
		},
	) );

}
add_action( 'wp_abilities_api_init', 'fluent_booking_register_orders_abilities' );
