<?php
/**
 * FluentBooking — Multi-host booking management (cluster 4.2).
 *
 * `fcal_booking_hosts` is the many-to-many pivot for collective / round-robin /
 * team-event bookings. Existing `get-booking` reader returns hosts inline but
 * provides no write surface — this cluster fills that gap.
 *
 *   - fluent-booking/list-booking-hosts          (read)
 *   - fluent-booking/get-booking-host            (read — single pivot row)
 *   - fluent-booking/add-booking-host            (write)
 *   - fluent-booking/update-booking-host-status  (write)
 *   - fluent-booking/remove-booking-host         (delete)
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

function fluent_booking_register_multi_host_abilities() {

	$reg = new Fluent_Abilities_Registrar( 'booking' );

	// =========================================================================
	// 4.2.1 — LIST BOOKING HOSTS
	// =========================================================================

	$reg->read( 'fluent-booking/list-booking-hosts', array(
		'label'       => 'List Booking Hosts',
		'description' => 'List host pivots for a booking (collective / round-robin / team-event bookings have N hosts). Joins fcal_booking_hosts to wp_users for display_name / email.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'booking_id' ),
			'properties' => array(
				'booking_id' => array( 'type' => 'integer', 'description' => 'Booking ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'hosts', array(
			'user_id'      => array( 'type' => 'integer' ),
			'display_name' => array( 'type' => array( 'string', 'null' ) ),
			'email'        => array( 'type' => array( 'string', 'null' ) ),
			'status'       => array( 'type' => 'string' ),
			'created_at'   => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			global $wpdb;
			$booking_id = (int) $input['booking_id'];
			if ( $booking_id <= 0 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'booking_id is required' );
			}

			$rows = wpFluent()->table( 'fcal_booking_hosts' )
				->where( 'booking_id', $booking_id )
				->get();

			$hosts = array();
			foreach ( $rows as $row ) {
				$user = get_user_by( 'ID', (int) $row->user_id );
				$hosts[] = array(
					'user_id'      => (int) $row->user_id,
					'display_name' => $user ? $user->display_name : null,
					'email'        => $user ? $user->user_email : null,
					'status'       => (string) ( $row->status ?? 'confirmed' ),
					'created_at'   => $row->created_at ? (string) $row->created_at : null,
				);
			}

			return array( 'hosts' => $hosts, 'total' => count( $hosts ) );
		},
	) );

	// =========================================================================
	// 4.2.2 — GET BOOKING HOST
	// =========================================================================

	$reg->read( 'fluent-booking/get-booking-host', array(
		'label'       => 'Get Booking Host Pivot',
		'description' => 'Look up a single booking-host pivot row by (booking_id, user_id).',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'booking_id', 'user_id' ),
			'properties' => array(
				'booking_id' => array( 'type' => 'integer' ),
				'user_id'    => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'booking_id'   => array( 'type' => 'integer' ),
			'user_id'      => array( 'type' => 'integer' ),
			'display_name' => array( 'type' => array( 'string', 'null' ) ),
			'email'        => array( 'type' => array( 'string', 'null' ) ),
			'status'       => array( 'type' => 'string' ),
			'created_at'   => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$booking_id = (int) $input['booking_id'];
			$user_id    = (int) $input['user_id'];

			$row = wpFluent()->table( 'fcal_booking_hosts' )
				->where( 'booking_id', $booking_id )
				->where( 'user_id', $user_id )
				->first();

			if ( ! $row ) {
				return fluent_abilities_error( 'not_found', 'Booking host pivot not found' );
			}

			$user = get_user_by( 'ID', $user_id );

			return array(
				'booking_id'   => $booking_id,
				'user_id'      => $user_id,
				'display_name' => $user ? $user->display_name : null,
				'email'        => $user ? $user->user_email : null,
				'status'       => (string) ( $row->status ?? 'confirmed' ),
				'created_at'   => $row->created_at ? (string) $row->created_at : null,
			);
		},
	) );

	// =========================================================================
	// 4.2.3 — ADD BOOKING HOST
	// =========================================================================

	$reg->write( 'fluent-booking/add-booking-host', array(
		'label'       => 'Add Booking Host',
		'description' => 'Attach an additional host (user) to a booking. Idempotent: existing (booking_id, user_id) pivot is updated, not duplicated.',
		'level'       => 'admin',
		'annotations' => array( 'idempotent' => true ),
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'booking_id', 'user_id' ),
			'properties' => array(
				'booking_id' => array( 'type' => 'integer', 'description' => 'Booking ID' ),
				'user_id'    => array( 'type' => 'integer', 'description' => 'Host user ID' ),
				'status'     => array(
					'type'        => 'string',
					'description' => 'Host status (default: confirmed)',
					'enum'        => array( 'confirmed', 'declined', 'pending' ),
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'booking_id' => array( 'type' => 'integer' ),
			'user_id'    => array( 'type' => 'integer' ),
			'status'     => array( 'type' => 'string' ),
			'created'    => array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $input ) {
			$booking_id = (int) $input['booking_id'];
			$user_id    = (int) $input['user_id'];
			$status     = sanitize_text_field( $input['status'] ?? 'confirmed' );

			if ( $booking_id <= 0 || $user_id <= 0 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'booking_id and user_id are required' );
			}

			if ( ! get_user_by( 'ID', $user_id ) ) {
				return fluent_abilities_error( 'user_not_found', 'User not found' );
			}

			$existing = wpFluent()->table( 'fcal_booking_hosts' )
				->where( 'booking_id', $booking_id )
				->where( 'user_id', $user_id )
				->first();

			if ( $existing ) {
				wpFluent()->table( 'fcal_booking_hosts' )
					->where( 'id', $existing->id )
					->update( array(
						'status'     => $status,
						'updated_at' => current_time( 'mysql' ),
					) );
				$created = false;
			} else {
				wpFluent()->table( 'fcal_booking_hosts' )
					->insert( array(
						'booking_id' => $booking_id,
						'user_id'    => $user_id,
						'status'     => $status,
						'created_at' => current_time( 'mysql' ),
						'updated_at' => current_time( 'mysql' ),
					) );
				$created = true;
			}

			do_action( 'fluent_booking/host_added', $booking_id, $user_id, $status );

			return array(
				'success'    => true,
				'booking_id' => $booking_id,
				'user_id'    => $user_id,
				'status'     => $status,
				'created'    => $created,
			);
		},
	) );

	// =========================================================================
	// 4.2.4 — UPDATE BOOKING HOST STATUS
	// =========================================================================

	$reg->write( 'fluent-booking/update-booking-host-status', array(
		'label'       => 'Update Booking Host Status',
		'description' => 'Change the status of an existing booking-host pivot (confirmed / declined / pending).',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'booking_id', 'user_id', 'status' ),
			'properties' => array(
				'booking_id' => array( 'type' => 'integer' ),
				'user_id'    => array( 'type' => 'integer' ),
				'status'     => array(
					'type' => 'string',
					'enum' => array( 'confirmed', 'declined', 'pending' ),
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'booking_id' => array( 'type' => 'integer' ),
			'user_id'    => array( 'type' => 'integer' ),
			'status'     => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$booking_id = (int) $input['booking_id'];
			$user_id    = (int) $input['user_id'];
			$status     = sanitize_text_field( $input['status'] );

			$existing = wpFluent()->table( 'fcal_booking_hosts' )
				->where( 'booking_id', $booking_id )
				->where( 'user_id', $user_id )
				->first();
			if ( ! $existing ) {
				return fluent_abilities_error( 'not_found', 'Booking host pivot not found' );
			}

			wpFluent()->table( 'fcal_booking_hosts' )
				->where( 'id', $existing->id )
				->update( array(
					'status'     => $status,
					'updated_at' => current_time( 'mysql' ),
				) );

			return array(
				'success'    => true,
				'booking_id' => $booking_id,
				'user_id'    => $user_id,
				'status'     => $status,
			);
		},
	) );

	// =========================================================================
	// 4.2.5 — REMOVE BOOKING HOST
	// =========================================================================

	$reg->delete( 'fluent-booking/remove-booking-host', array(
		'label'       => 'Remove Booking Host',
		'description' => 'Detach a host (user) from a booking by removing the (booking_id, user_id) pivot row.',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'booking_id', 'user_id' ),
			'properties' => array(
				'booking_id' => array( 'type' => 'integer' ),
				'user_id'    => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'booking_id' => array( 'type' => 'integer' ),
			'user_id'    => array( 'type' => 'integer' ),
			'deleted'    => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$booking_id = (int) $input['booking_id'];
			$user_id    = (int) $input['user_id'];

			$deleted = wpFluent()->table( 'fcal_booking_hosts' )
				->where( 'booking_id', $booking_id )
				->where( 'user_id', $user_id )
				->delete();

			return array(
				'success'    => true,
				'booking_id' => $booking_id,
				'user_id'    => $user_id,
				'deleted'    => (int) $deleted,
			);
		},
	) );

}
add_action( 'wp_abilities_api_init', 'fluent_booking_register_multi_host_abilities' );
