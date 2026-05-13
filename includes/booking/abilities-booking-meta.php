<?php
/**
 * FluentBooking — Booking meta write surface (cluster 4.4).
 *
 * Complements the existing `fluent-booking/get-booking-meta` reader at
 * includes/booking/abilities-bookings.php:364-399. Storage is fcal_booking_meta
 * (booking_id, meta_key, value LONGTEXT serialized).
 *
 *   - fluent-booking/set-booking-meta     (write — insert-or-update by (booking_id, meta_key))
 *   - fluent-booking/delete-booking-meta  (delete)
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

function fluent_booking_register_booking_meta_abilities() {

	$reg = new Fluent_Abilities_Registrar( 'booking' );

	// =========================================================================
	// 4.4.1 — SET BOOKING META
	// =========================================================================

	$reg->write( 'fluent-booking/set-booking-meta', array(
		'label'       => 'Set Booking Meta',
		'description' => 'Insert or update a meta key/value pair on a booking. Values are serialized via maybe_serialize. Matches existing fcal_booking_meta storage shape.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'booking_id', 'meta_key' ),
			'properties' => array(
				'booking_id' => array( 'type' => 'integer', 'description' => 'Booking ID' ),
				'meta_key'   => array( 'type' => 'string', 'description' => 'Meta key (namespaced is recommended)' ),
				'value'      => array(
					'type'        => array( 'string', 'integer', 'number', 'boolean', 'array', 'object', 'null' ),
					'description' => 'Meta value (any JSON-serializable type)',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'booking_id' => array( 'type' => 'integer' ),
			'meta_key'   => array( 'type' => 'string' ),
			'created'    => array( 'type' => 'boolean', 'description' => 'true if new row inserted, false if existing row updated' ),
		) ),
		'callback' => function( $input ) {
			$booking_id = (int) $input['booking_id'];
			$meta_key   = sanitize_text_field( $input['meta_key'] );
			$value      = $input['value'] ?? null;

			if ( $booking_id <= 0 || $meta_key === '' ) {
				return fluent_abilities_error( 'ability_invalid_input', 'booking_id and meta_key are required' );
			}

			$existing = wpFluent()->table( 'fcal_booking_meta' )
				->where( 'booking_id', $booking_id )
				->where( 'meta_key', $meta_key )
				->first();

			$serialized = maybe_serialize( $value );

			if ( $existing ) {
				wpFluent()->table( 'fcal_booking_meta' )
					->where( 'id', $existing->id )
					->update( array(
						'value'      => $serialized,
						'updated_at' => current_time( 'mysql' ),
					) );
				$created = false;
			} else {
				wpFluent()->table( 'fcal_booking_meta' )
					->insert( array(
						'booking_id' => $booking_id,
						'meta_key'   => $meta_key,
						'value'      => $serialized,
						'created_at' => current_time( 'mysql' ),
						'updated_at' => current_time( 'mysql' ),
					) );
				$created = true;
			}

			return array(
				'success'    => true,
				'booking_id' => $booking_id,
				'meta_key'   => $meta_key,
				'created'    => $created,
			);
		},
	) );

	// =========================================================================
	// 4.4.2 — DELETE BOOKING META
	// =========================================================================

	$reg->delete( 'fluent-booking/delete-booking-meta', array(
		'label'       => 'Delete Booking Meta',
		'description' => 'Remove a meta row from a booking (matched by booking_id + meta_key).',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'booking_id', 'meta_key' ),
			'properties' => array(
				'booking_id' => array( 'type' => 'integer', 'description' => 'Booking ID' ),
				'meta_key'   => array( 'type' => 'string', 'description' => 'Meta key to delete' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'deleted' => array( 'type' => 'integer', 'description' => 'Number of rows deleted (0 or 1)' ),
		) ),
		'callback' => function( $input ) {
			$booking_id = (int) $input['booking_id'];
			$meta_key   = sanitize_text_field( $input['meta_key'] );

			if ( $booking_id <= 0 || $meta_key === '' ) {
				return fluent_abilities_error( 'ability_invalid_input', 'booking_id and meta_key are required' );
			}

			$deleted = wpFluent()->table( 'fcal_booking_meta' )
				->where( 'booking_id', $booking_id )
				->where( 'meta_key', $meta_key )
				->delete();

			return array(
				'success' => true,
				'deleted' => (int) $deleted,
			);
		},
	) );

}
add_action( 'wp_abilities_api_init', 'fluent_booking_register_booking_meta_abilities' );
