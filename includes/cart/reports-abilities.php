<?php
/**
 * FluentCart Abilities — Reports & Analytics (v2.0.0)
 *
 * Adds cluster 4.20 from FluentCart Ability Registrar Research v1.0
 * (2026-05-13) — 7 abilities. The most operator-facing dashboard summaries
 * out of the ~37 /reports/* REST routes.
 *
 * @package Fluent_Abilities
 * @since 2.0.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'cart' );

	$reg->read( 'fluent-cart/get-dashboard-summary', array(
		'label'       => 'Get Dashboard Summary',
		'description' => 'Aggregate dashboard summary (revenue / orders / customers / refunds). Mirrors GET /reports/get-dashboard-summary.',
		'input_schema' => array(
			'type'     => 'object',
			'properties' => array(
				'from' => array( 'type' => 'string', 'description' => 'Start date (YYYY-MM-DD)' ),
				'to'   => array( 'type' => 'string', 'description' => 'End date (YYYY-MM-DD)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'orders'         => array( 'type' => 'integer' ),
			'paid_orders'    => array( 'type' => 'integer' ),
			'revenue'        => array( 'type' => 'number' ),
			'refunded_total' => array( 'type' => 'number' ),
			'customers'      => array( 'type' => 'integer' ),
			'from'           => array( 'type' => array( 'string', 'null' ) ),
			'to'             => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$order_q  = \FluentCart\App\Models\Order::query();
			$tx_q     = \FluentCart\App\Models\OrderTransaction::query();
			$cust_q   = \FluentCart\App\Models\Customer::query();
			$from     = ! empty( $input['from'] ) ? sanitize_text_field( $input['from'] ) : null;
			$to       = ! empty( $input['to'] ) ? sanitize_text_field( $input['to'] ) : null;
			if ( $from ) {
				$order_q->where( 'created_at', '>=', $from );
				$tx_q->where( 'created_at', '>=', $from );
				$cust_q->where( 'created_at', '>=', $from );
			}
			if ( $to ) {
				$order_q->where( 'created_at', '<=', $to . ' 23:59:59' );
				$tx_q->where( 'created_at', '<=', $to . ' 23:59:59' );
				$cust_q->where( 'created_at', '<=', $to . ' 23:59:59' );
			}
			$orders      = (int) $order_q->count();
			$paid_orders = (int) ( clone $order_q )->where( 'payment_status', 'paid' )->count();
			$revenue     = (int) ( clone $tx_q )->where( 'status', 'paid' )->sum( 'total' );
			$refunded    = (int) ( clone $tx_q )->where( 'transaction_type', 'refund' )->sum( 'total' );
			$customers   = (int) $cust_q->count();
			return array(
				'orders'         => $orders,
				'paid_orders'    => $paid_orders,
				'revenue'        => fluent_cart_format_money( $revenue ),
				'refunded_total' => fluent_cart_format_money( $refunded ),
				'customers'      => $customers,
				'from'           => $from,
				'to'             => $to,
			);
		},
	) );

	$reg->read( 'fluent-cart/get-sales-growth', array(
		'label'       => 'Get Sales Growth',
		'description' => 'Period-over-period sales growth. Mirrors GET /reports/sales-growth.',
		'input_schema' => array(
			'type'     => 'object',
			'properties' => array(
				'period' => array( 'type' => 'string', 'description' => 'day | week | month | year (default: month)' ),
				'count'  => array( 'type' => 'integer', 'description' => 'Number of periods to compare (default: 2)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'period'         => array( 'type' => 'string' ),
			'current_total'  => array( 'type' => 'number' ),
			'previous_total' => array( 'type' => 'number' ),
			'growth_percent' => array( 'type' => 'number' ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$period = sanitize_text_field( $input['period'] ?? 'month' );
			$now    = current_time( 'mysql' );
			$map    = array( 'day' => '-1 day', 'week' => '-1 week', 'month' => '-1 month', 'year' => '-1 year' );
			$delta  = $map[ $period ] ?? '-1 month';
			$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( $delta, strtotime( $now ) ) );
			$double = gmdate( 'Y-m-d H:i:s', strtotime( $delta . ' ' . $delta, strtotime( $now ) ) );

			$current  = (int) \FluentCart\App\Models\OrderTransaction::where( 'status', 'paid' )
				->where( 'created_at', '>=', $cutoff )->sum( 'total' );
			$previous = (int) \FluentCart\App\Models\OrderTransaction::where( 'status', 'paid' )
				->where( 'created_at', '>=', $double )
				->where( 'created_at', '<', $cutoff )->sum( 'total' );
			$growth   = $previous > 0 ? ( ( $current - $previous ) / $previous ) * 100 : 0;
			return array(
				'period'         => $period,
				'current_total'  => fluent_cart_format_money( $current ),
				'previous_total' => fluent_cart_format_money( $previous ),
				'growth_percent' => round( $growth, 2 ),
			);
		},
	) );

	$reg->read( 'fluent-cart/get-revenue-report', array(
		'label'       => 'Get Revenue Report',
		'description' => 'Revenue totals grouped by period. Mirrors GET /reports/revenue + /reports/revenue-by-group.',
		'input_schema' => array(
			'type'     => 'object',
			'properties' => array(
				'from'    => array( 'type' => 'string', 'description' => 'YYYY-MM-DD' ),
				'to'      => array( 'type' => 'string', 'description' => 'YYYY-MM-DD' ),
				'groupBy' => array( 'type' => 'string', 'description' => 'day | week | month' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'series', array(
			'period'  => array( 'type' => 'string' ),
			'revenue' => array( 'type' => 'number' ),
			'orders'  => array( 'type' => 'integer' ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$group_by = sanitize_text_field( $input['groupBy'] ?? 'day' );
			$format   = $group_by === 'month' ? '%Y-%m' : ( $group_by === 'week' ? '%X-%V' : '%Y-%m-%d' );
			$q = \FluentCart\App\Models\OrderTransaction::where( 'status', 'paid' );
			if ( ! empty( $input['from'] ) ) {
				$q->where( 'created_at', '>=', sanitize_text_field( $input['from'] ) );
			}
			if ( ! empty( $input['to'] ) ) {
				$q->where( 'created_at', '<=', sanitize_text_field( $input['to'] ) . ' 23:59:59' );
			}
			$rows = $q->selectRaw( "DATE_FORMAT(created_at, '{$format}') as period, SUM(total) as revenue, COUNT(*) as orders" )
				->groupBy( 'period' )->orderBy( 'period', 'ASC' )->get();
			$series = array();
			foreach ( $rows as $r ) {
				$series[] = array(
					'period'  => (string) ( $r->period ?? '' ),
					'revenue' => fluent_cart_format_money( $r->revenue ),
					'orders'  => (int) ( $r->orders ?? 0 ),
				);
			}
			return array( 'series' => $series, 'total' => count( $series ) );
		},
	) );

	$reg->read( 'fluent-cart/get-top-products-sold', array(
		'label'       => 'Get Top Products Sold',
		'description' => 'Best-selling products by quantity. Mirrors GET /reports/top-products-sold.',
		'input_schema' => array(
			'type'     => 'object',
			'properties' => array(
				'from'  => array( 'type' => 'string' ),
				'to'    => array( 'type' => 'string' ),
				'limit' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'products', array(
			'post_id'          => array( 'type' => 'integer', 'description' => 'Product post ID (canonical fct_order_items.post_id; CPT fluent-products)' ),
			'fulfillment_type' => array( 'type' => 'string' ),
			'total_quantity'   => array( 'type' => 'integer' ),
			'total_revenue'    => array( 'type' => 'number' ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$limit = min( 100, max( 1, (int) ( $input['limit'] ?? 10 ) ) );
			$q     = \FluentCart\App\Models\OrderItem::query();
			if ( ! empty( $input['from'] ) ) {
				$q->where( 'created_at', '>=', sanitize_text_field( $input['from'] ) );
			}
			if ( ! empty( $input['to'] ) ) {
				$q->where( 'created_at', '<=', sanitize_text_field( $input['to'] ) . ' 23:59:59' );
			}
			// fct_order_items has post_id (CPT post id) + fulfillment_type — no object_type
			// column. Polymorphism is via post_id + optional object_id (variation).
			$rows = $q->selectRaw( 'post_id, fulfillment_type, SUM(quantity) as total_quantity, SUM(line_total) as total_revenue' )
				->groupBy( 'post_id', 'fulfillment_type' )
				->orderBy( 'total_quantity', 'DESC' )
				->limit( $limit )->get();
			$items = array();
			foreach ( $rows as $r ) {
				$items[] = array(
					'post_id'          => (int) $r->post_id,
					'fulfillment_type' => (string) ( $r->fulfillment_type ?? '' ),
					'total_quantity'   => (int) ( $r->total_quantity ?? 0 ),
					'total_revenue'    => fluent_cart_format_money( $r->total_revenue ),
				);
			}
			return array( 'products' => $items, 'total' => count( $items ) );
		},
	) );

	$reg->read( 'fluent-cart/get-subscription-cohorts', array(
		'label'       => 'Get Subscription Cohorts',
		'description' => 'Cohort × period retention matrix from fct_retention_snapshots. Mirrors GET /reports/subscription-cohorts.',
		'input_schema' => array(
			'type'     => 'object',
			'properties' => array(
				'cohorts'  => array( 'type' => 'integer', 'description' => 'Number of cohorts to include (default: 12)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'cohorts', array(
			'cohort'             => array( 'type' => 'string', 'description' => 'YYYY-MM' ),
			'period'             => array( 'type' => 'string', 'description' => 'YYYY-MM' ),
			'period_offset'      => array( 'type' => 'integer' ),
			'retained_customers' => array( 'type' => 'integer' ),
			'retained_mrr'       => array( 'type' => 'number' ),
			'churned_customers'  => array( 'type' => 'integer' ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			global $wpdb;
			$table = $wpdb->prefix . 'fct_retention_snapshots';
			if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) ) {
				return array( 'cohorts' => array(), 'total' => 0 );
			}
			$limit = min( 60, max( 1, (int) ( $input['cohorts'] ?? 12 ) ) * 12 );
			$rows  = $wpdb->get_results( $wpdb->prepare(
				"SELECT cohort, period, period_offset, retained_customers, retained_mrr, churned_customers
				 FROM {$table}
				 ORDER BY cohort DESC, period_offset ASC
				 LIMIT %d",
				$limit
			) );
			$items = array();
			foreach ( (array) $rows as $r ) {
				$items[] = array(
					'cohort'             => (string) ( $r->cohort ?? '' ),
					'period'             => (string) ( $r->period ?? '' ),
					'period_offset'      => (int) ( $r->period_offset ?? 0 ),
					'retained_customers' => (int) ( $r->retained_customers ?? 0 ),
					'retained_mrr'       => fluent_cart_format_money( $r->retained_mrr ),
					'churned_customers'  => (int) ( $r->churned_customers ?? 0 ),
				);
			}
			return array( 'cohorts' => $items, 'total' => count( $items ) );
		},
	) );

	$reg->read( 'fluent-cart/get-product-performance', array(
		'label'       => 'Get Product Performance',
		'description' => 'Per-product performance summary (revenue / orders / units). Mirrors GET /reports/product-performance.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'product_id' ),
			'properties' => array(
				'product_id' => array( 'type' => 'integer' ),
				'from'       => array( 'type' => 'string' ),
				'to'         => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'product_id'    => array( 'type' => 'integer' ),
			'units_sold'    => array( 'type' => 'integer' ),
			'orders'        => array( 'type' => 'integer' ),
			'total_revenue' => array( 'type' => 'number' ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$pid = (int) $input['product_id'];
			$q   = \FluentCart\App\Models\OrderItem::where( 'object_id', $pid );
			if ( ! empty( $input['from'] ) ) {
				$q->where( 'created_at', '>=', sanitize_text_field( $input['from'] ) );
			}
			if ( ! empty( $input['to'] ) ) {
				$q->where( 'created_at', '<=', sanitize_text_field( $input['to'] ) . ' 23:59:59' );
			}
			$units    = (int) ( clone $q )->sum( 'quantity' );
			$revenue  = (int) ( clone $q )->sum( 'line_total' );
			$orders   = (int) ( clone $q )->distinct()->count( 'order_id' );
			return array(
				'product_id'    => $pid,
				'units_sold'    => $units,
				'orders'        => $orders,
				'total_revenue' => fluent_cart_format_money( $revenue ),
			);
		},
	) );

	$reg->write( 'fluent-cart/generate-retention-snapshots', array(
		'label'       => 'Generate Retention Snapshots',
		'description' => 'Kick off the retention-snapshot aggregation job. Async — fires fluent_cart/generate_retention_snapshots action. Mirrors POST /reports/retention-snapshots/generate.',
		'input_schema' => array(
			'type'     => 'object',
			'properties' => array(
				'from' => array( 'type' => 'string', 'description' => 'Cohort start (YYYY-MM)' ),
				'to'   => array( 'type' => 'string', 'description' => 'Cohort end (YYYY-MM)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'queued' => array( 'type' => 'boolean' ),
			'from'   => array( 'type' => array( 'string', 'null' ) ),
			'to'     => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'capability'  => 'manage_options',
		'callback'    => function( $input ) {
			$from = ! empty( $input['from'] ) ? sanitize_text_field( $input['from'] ) : null;
			$to   = ! empty( $input['to'] ) ? sanitize_text_field( $input['to'] ) : null;
			do_action( 'fluent_cart/generate_retention_snapshots', $from, $to );
			return array( 'success' => true, 'queued' => true, 'from' => $from, 'to' => $to );
		},
	) );

	$count = 7;
	error_log( "Abilities for Fluent: Registered {$count} Cart Reports abilities" );

}, 100 );
