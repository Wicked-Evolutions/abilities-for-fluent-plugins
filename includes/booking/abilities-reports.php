<?php
/**
 * FluentBooking — Reports (cluster 4.16).
 *
 * Aggregate dashboards over fcal_bookings (+ Pro fcal_orders / fcal_transactions
 * when available). All abilities accept an optional date window and return
 * pre-aggregated shapes — no row-level booking exposure here.
 *
 *   - fluent-booking/get-revenue-report             (Pro — paid bookings via Orders)
 *   - fluent-booking/get-host-report                (per-host counts + revenue)
 *   - fluent-booking/get-event-conversion-report    (per-event scheduled counts)
 *   - fluent-booking/get-time-distribution-report   (hour-of-day + day-of-week)
 *
 * Cluster permission: admin-level. Reports surface aggregate operator-facing
 * data — `level=admin` keeps these gated to operators (Reviewer rule (h)(v)).
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

function fluent_booking_register_reports_abilities() {

	$reg = new Fluent_Abilities_Registrar( 'booking' );

	$date_window_schema = array(
		'date_from' => array( 'type' => 'string', 'description' => 'Optional window start (Y-m-d)' ),
		'date_to'   => array( 'type' => 'string', 'description' => 'Optional window end (Y-m-d)' ),
	);

	// =========================================================================
	// 4.16.1 — REVENUE REPORT
	// =========================================================================

	$reg->read( 'fluent-booking/get-revenue-report', array(
		'label'       => 'Get Revenue Report',
		'description' => 'Aggregate paid-booking revenue for a date window. Returns total + per-day breakdown. Requires Pro orders table (fcal_orders).',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => $date_window_schema,
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'total_revenue' => array( 'type' => 'number' ),
			'currency'      => array( 'type' => array( 'string', 'null' ) ),
			'order_count'   => array( 'type' => 'integer' ),
			'by_day'        => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $input ) {
			$from = isset( $input['date_from'] ) ? sanitize_text_field( $input['date_from'] ) : '';
			$to   = isset( $input['date_to'] ) ? sanitize_text_field( $input['date_to'] ) : '';

			$query = wpFluent()->table( 'fcal_orders' );
			if ( $from !== '' ) {
				$query->where( 'created_at', '>=', $from . ' 00:00:00' );
			}
			if ( $to !== '' ) {
				$query->where( 'created_at', '<=', $to . ' 23:59:59' );
			}

			try {
				$rows = $query->select( 'created_at', 'total', 'currency', 'status' )->get();
			} catch ( \Exception $e ) {
				return fluent_abilities_error( 'orders_table_missing', 'fcal_orders table not present (Pro plugin not installed?)' );
			}

			$total = 0.0;
			$count = 0;
			$by_day = array();
			$currency = null;
			foreach ( $rows as $row ) {
				if ( ! in_array( (string) ( $row->status ?? '' ), array( 'paid', 'completed' ), true ) ) {
					continue;
				}
				$amount = (float) ( $row->total ?? 0 );
				$total += $amount;
				$count++;
				$day = substr( (string) $row->created_at, 0, 10 );
				if ( ! isset( $by_day[ $day ] ) ) {
					$by_day[ $day ] = array( 'date' => $day, 'revenue' => 0.0, 'orders' => 0 );
				}
				$by_day[ $day ]['revenue'] += $amount;
				$by_day[ $day ]['orders']++;
				if ( $currency === null && ! empty( $row->currency ) ) {
					$currency = (string) $row->currency;
				}
			}

			return array(
				'total_revenue' => round( $total, 2 ),
				'currency'      => $currency,
				'order_count'   => $count,
				'by_day'        => array_values( $by_day ),
			);
		},
	) );

	// =========================================================================
	// 4.16.2 — HOST REPORT
	// =========================================================================

	$reg->read( 'fluent-booking/get-host-report', array(
		'label'       => 'Get Host Report',
		'description' => 'Booking counts grouped by host_user_id over a date window.',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => $date_window_schema,
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'hosts', array(
			'host_user_id'  => array( 'type' => 'integer' ),
			'display_name'  => array( 'type' => array( 'string', 'null' ) ),
			'booking_count' => array( 'type' => 'integer' ),
			'cancelled'     => array( 'type' => 'integer' ),
			'no_show'       => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$from = isset( $input['date_from'] ) ? sanitize_text_field( $input['date_from'] ) : '';
			$to   = isset( $input['date_to'] ) ? sanitize_text_field( $input['date_to'] ) : '';

			$query = wpFluent()->table( 'fcal_bookings' )->select( 'host_user_id', 'status' );
			if ( $from !== '' ) {
				$query->where( 'created_at', '>=', $from . ' 00:00:00' );
			}
			if ( $to !== '' ) {
				$query->where( 'created_at', '<=', $to . ' 23:59:59' );
			}

			$rows = $query->get();

			$by_host = array();
			foreach ( $rows as $row ) {
				$host_id = (int) ( $row->host_user_id ?? 0 );
				if ( ! isset( $by_host[ $host_id ] ) ) {
					$by_host[ $host_id ] = array(
						'host_user_id'  => $host_id,
						'display_name'  => null,
						'booking_count' => 0,
						'cancelled'     => 0,
						'no_show'       => 0,
					);
				}
				$by_host[ $host_id ]['booking_count']++;
				$status = (string) ( $row->status ?? '' );
				if ( $status === 'cancelled' ) {
					$by_host[ $host_id ]['cancelled']++;
				}
				// Read shape uses 'no-show' (hyphen) per KD-5; preserve.
				if ( $status === 'no-show' || $status === 'no_show' ) {
					$by_host[ $host_id ]['no_show']++;
				}
			}

			foreach ( $by_host as $host_id => &$entry ) {
				if ( $host_id > 0 ) {
					$user = get_user_by( 'ID', $host_id );
					$entry['display_name'] = $user ? $user->display_name : null;
				}
			}
			unset( $entry );

			$hosts = array_values( $by_host );
			usort( $hosts, function( $a, $b ) { return $b['booking_count'] - $a['booking_count']; } );

			return array( 'hosts' => $hosts, 'total' => count( $hosts ) );
		},
	) );

	// =========================================================================
	// 4.16.3 — EVENT CONVERSION REPORT
	// =========================================================================

	$reg->read( 'fluent-booking/get-event-conversion-report', array(
		'label'       => 'Get Event Conversion Report',
		'description' => 'Per-event booking counts grouped by status. (Note: vendor-side event-view tracking is not exposed on fcal_calendar_events; this report returns booking counts only — view counts deferred until vendor surfaces them.)',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => $date_window_schema,
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'events', array(
			'event_id'      => array( 'type' => 'integer' ),
			'title'         => array( 'type' => 'string' ),
			'booking_count' => array( 'type' => 'integer' ),
			'completed'     => array( 'type' => 'integer' ),
			'cancelled'     => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$from = isset( $input['date_from'] ) ? sanitize_text_field( $input['date_from'] ) : '';
			$to   = isset( $input['date_to'] ) ? sanitize_text_field( $input['date_to'] ) : '';

			$query = wpFluent()->table( 'fcal_bookings' )->select( 'event_id', 'status' );
			if ( $from !== '' ) {
				$query->where( 'created_at', '>=', $from . ' 00:00:00' );
			}
			if ( $to !== '' ) {
				$query->where( 'created_at', '<=', $to . ' 23:59:59' );
			}
			$rows = $query->get();

			$by_event = array();
			foreach ( $rows as $row ) {
				$eid = (int) ( $row->event_id ?? 0 );
				if ( ! isset( $by_event[ $eid ] ) ) {
					$by_event[ $eid ] = array(
						'event_id'      => $eid,
						'title'         => '',
						'booking_count' => 0,
						'completed'     => 0,
						'cancelled'     => 0,
					);
				}
				$by_event[ $eid ]['booking_count']++;
				if ( ( $row->status ?? '' ) === 'completed' ) {
					$by_event[ $eid ]['completed']++;
				}
				if ( ( $row->status ?? '' ) === 'cancelled' ) {
					$by_event[ $eid ]['cancelled']++;
				}
			}

			if ( ! empty( $by_event ) ) {
				$titles = wpFluent()->table( 'fcal_calendar_events' )
					->select( 'id', 'title' )
					->whereIn( 'id', array_keys( $by_event ) )
					->get();
				foreach ( $titles as $t ) {
					if ( isset( $by_event[ (int) $t->id ] ) ) {
						$by_event[ (int) $t->id ]['title'] = (string) ( $t->title ?? '' );
					}
				}
			}

			$events = array_values( $by_event );
			usort( $events, function( $a, $b ) { return $b['booking_count'] - $a['booking_count']; } );
			return array( 'events' => $events, 'total' => count( $events ) );
		},
	) );

	// =========================================================================
	// 4.16.4 — TIME DISTRIBUTION REPORT
	// =========================================================================

	$reg->read( 'fluent-booking/get-time-distribution-report', array(
		'label'       => 'Get Time Distribution Report',
		'description' => 'Booking count distribution by hour-of-day and day-of-week for the date window. Times computed from booking start_time.',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => $date_window_schema,
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'by_hour' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'by_dow'  => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $input ) {
			$from = isset( $input['date_from'] ) ? sanitize_text_field( $input['date_from'] ) : '';
			$to   = isset( $input['date_to'] ) ? sanitize_text_field( $input['date_to'] ) : '';

			$query = wpFluent()->table( 'fcal_bookings' )->select( 'start_time' );
			if ( $from !== '' ) {
				$query->where( 'created_at', '>=', $from . ' 00:00:00' );
			}
			if ( $to !== '' ) {
				$query->where( 'created_at', '<=', $to . ' 23:59:59' );
			}
			$rows = $query->get();

			$by_hour = array_fill( 0, 24, 0 );
			$by_dow  = array_fill( 0, 7, 0 );
			foreach ( $rows as $row ) {
				$ts = strtotime( (string) ( $row->start_time ?? '' ) . ' UTC' );
				if ( ! $ts ) {
					continue;
				}
				$by_hour[ (int) gmdate( 'G', $ts ) ]++;
				$by_dow[ (int) gmdate( 'w', $ts ) ]++;
			}

			$by_hour_out = array();
			foreach ( $by_hour as $h => $c ) {
				$by_hour_out[] = array( 'hour' => $h, 'count' => $c );
			}
			$by_dow_out = array();
			$dow_names  = array( 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' );
			foreach ( $by_dow as $d => $c ) {
				$by_dow_out[] = array( 'dow' => $d, 'name' => $dow_names[ $d ], 'count' => $c );
			}

			return array( 'by_hour' => $by_hour_out, 'by_dow' => $by_dow_out );
		},
	) );

}
add_action( 'wp_abilities_api_init', 'fluent_booking_register_reports_abilities' );
