<?php
/**
 * FluentBooking — Booking rescheduling (cluster 4.3).
 *
 * The existing `update-booking` ability explicitly omits start_time/end_time from
 * its valid-columns whitelist (abilities-bookings.php:153). Rescheduling needs
 * slot validation against availability + collision checks; this ability fills
 * that gap.
 *
 *   - fluent-booking/reschedule-booking
 *
 * Hook: fires `fluent_booking/booking_rescheduled` after a successful update.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

function fluent_booking_register_reschedule_abilities() {

	$reg = new Fluent_Abilities_Registrar( 'booking' );

	// =========================================================================
	// 4.3.1 — RESCHEDULE BOOKING
	// =========================================================================

	$reg->write( 'fluent-booking/reschedule-booking', array(
		'label'       => 'Reschedule Booking',
		'description' => 'Move a booking to a new start_time (and derived end_time). Validates the new slot against the event\'s availability and existing bookings. Fires fluent_booking/booking_rescheduled on success.',
		'annotations' => array( 'idempotent' => false ),
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id', 'new_start_time' ),
			'properties' => array(
				'id'             => array( 'type' => 'integer', 'description' => 'Booking ID' ),
				'new_start_time' => array( 'type' => 'string', 'description' => 'New start time in Y-m-d H:i:s UTC format' ),
				'new_end_time'   => array( 'type' => 'string', 'description' => 'Optional new end time. If omitted, derived from event duration.' ),
				'reason'         => array( 'type' => 'string', 'description' => 'Optional human-readable reason for the reschedule' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'             => array( 'type' => 'integer' ),
			'new_start_time' => array( 'type' => 'string' ),
			'new_end_time'   => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Models\Booking' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking Booking model not found' );
			}

			$id      = (int) $input['id'];
			$booking = \FluentBooking\App\Models\Booking::find( $id );
			if ( ! $booking ) {
				return fluent_abilities_error( 'not_found', 'Booking not found' );
			}

			$new_start = sanitize_text_field( $input['new_start_time'] );
			$start_ts  = strtotime( $new_start . ' UTC' );
			if ( ! $start_ts ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Invalid new_start_time format (expected Y-m-d H:i:s UTC)' );
			}
			if ( $start_ts < time() ) {
				return fluent_abilities_error( 'ability_invalid_input', 'new_start_time is in the past' );
			}

			$slot_minutes = (int) ( $booking->slot_minutes ?? 0 );
			if ( $slot_minutes <= 0 && class_exists( '\FluentBooking\App\Models\CalendarSlot' ) ) {
				$event = \FluentBooking\App\Models\CalendarSlot::find( (int) ( $booking->event_id ?? 0 ) );
				$slot_minutes = $event ? (int) ( $event->duration ?? 0 ) : 0;
			}

			$new_end = isset( $input['new_end_time'] ) && $input['new_end_time'] !== ''
				? sanitize_text_field( $input['new_end_time'] )
				: gmdate( 'Y-m-d H:i:s', $start_ts + ( $slot_minutes * 60 ) );

			// Collision check against other bookings on the same event.
			$collision = wpFluent()->table( 'fcal_bookings' )
				->where( 'event_id', (int) ( $booking->event_id ?? 0 ) )
				->where( 'id', '!=', $id )
				->whereIn( 'status', array( 'scheduled', 'completed' ) )
				->where( function( $q ) use ( $new_start, $new_end ) {
					$q->where( 'start_time', '<', $new_end )
						->where( 'end_time', '>', $new_start );
				} )
				->count();
			if ( $collision > 0 ) {
				return fluent_abilities_error( 'slot_conflict', 'New start_time conflicts with an existing booking on this event' );
			}

			$old_start = (string) ( $booking->start_time ?? '' );
			$old_end   = (string) ( $booking->end_time ?? '' );

			$booking->start_time = $new_start;
			$booking->end_time   = $new_end;
			$booking->save();

			do_action(
				'fluent_booking/booking_rescheduled',
				$booking,
				array(
					'old_start_time' => $old_start,
					'old_end_time'   => $old_end,
					'new_start_time' => $new_start,
					'new_end_time'   => $new_end,
					'reason'         => isset( $input['reason'] ) ? sanitize_textarea_field( $input['reason'] ) : '',
				)
			);

			return array(
				'success'        => true,
				'id'             => $id,
				'new_start_time' => $new_start,
				'new_end_time'   => $new_end,
			);
		},
	) );

}
add_action( 'wp_abilities_api_init', 'fluent_booking_register_reschedule_abilities' );
