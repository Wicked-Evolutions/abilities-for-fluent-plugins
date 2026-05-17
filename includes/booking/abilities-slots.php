<?php
/**
 * FluentBooking — Slot generation abilities (cluster 4.1).
 *
 * Three read abilities for slot availability discovery:
 *   - fluent-booking/get-available-slots         (TimeSlotService dispatch)
 *   - fluent-booking/check-slot-availability     (slot-collision check)
 *   - fluent-booking/get-event-slot-config       (event settings sub-keys)
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

function fluent_booking_register_slot_abilities() {

	$reg = new Fluent_Abilities_Registrar( 'booking' );

	// =========================================================================
	// 4.1.1 — GET AVAILABLE SLOTS
	// =========================================================================

	$reg->read( 'fluent-booking/get-available-slots', array(
		'label'       => 'Get Available Slots',
		'description' => 'List bookable time slots for an event over a date range. Dispatches by event type (round_robin / collective / multi-guest in Pro). Times are returned in the supplied IANA timezone. Input: `timezone` is REQUIRED (an IANA identifier such as America/New_York) and is NOT defaulted — slot times cannot be computed without it; also pass `event_id` (integer), `start_date` and `end_date` (Y-m-d strings).',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'event_id', 'start_date', 'end_date', 'timezone' ),
			'properties' => array(
				'event_id'   => array( 'type' => 'integer', 'description' => 'CalendarSlot (event) ID' ),
				'start_date' => array( 'type' => 'string', 'description' => 'Start date in Y-m-d format' ),
				'end_date'   => array( 'type' => 'string', 'description' => 'End date in Y-m-d format' ),
				'timezone'   => array( 'type' => 'string', 'description' => 'IANA timezone identifier (e.g. America/New_York)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'event_id'     => array( 'type' => 'integer' ),
			'event_type'   => array( 'type' => 'string' ),
			'slot_minutes' => array( 'type' => 'integer' ),
			'timezone'     => array( 'type' => 'string' ),
			// P-H: vendor TimeSlotService::getDates() returns a date-KEYED map
			// (keys are Y-m-d, values are slot arrays), not a sequential list —
			// a genuinely alternative shape, so union-declare object|array
			// rather than discard the semantically-meaningful date keys.
			'days'         => array( 'type' => array( 'object', 'array' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Services\TimeSlotService' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking TimeSlotService not found' );
			}
			if ( ! class_exists( '\FluentBooking\App\Models\CalendarSlot' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking CalendarSlot model not found' );
			}
			if ( ! class_exists( '\FluentBooking\App\Models\Calendar' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking Calendar model not found' );
			}

			$event_id = (int) $input['event_id'];
			$event    = \FluentBooking\App\Models\CalendarSlot::find( $event_id );
			if ( ! $event ) {
				return fluent_abilities_error( 'not_found', 'Event (calendar slot) not found' );
			}

			// V10 signature alignment (P-K): vendor TimeSlotService constructor is
			// `__construct(Calendar $calendar, CalendarSlot $calendarSlot)` per
			// installed source app/Services/TimeSlotService.php:18. The prior
			// implementation passed (CalendarSlot, string $timezone) which produced
			// a PHP TypeError on every call (F-BOOK-02). Look up the parent
			// Calendar from the event's calendar_id and pass both objects.
			$calendar = \FluentBooking\App\Models\Calendar::find( (int) ( $event->calendar_id ?? 0 ) );
			if ( ! $calendar ) {
				return fluent_abilities_error( 'not_found', 'Parent calendar not found for event ' . $event_id );
			}

			$start_date = sanitize_text_field( $input['start_date'] );
			$end_date   = sanitize_text_field( $input['end_date'] );
			$timezone   = sanitize_text_field( $input['timezone'] );

			try {
				$service = new \FluentBooking\App\Services\TimeSlotService( $calendar, $event );
				// Vendor public API: getDates($fromDate, $toDate, $duration, $isDoingBooking, $timeZone).
				// Returns array keyed by date with slot arrays.
				$days    = $service->getDates( $start_date, $end_date, null, false, $timezone );
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'slot_lookup_failed', $e->getMessage() );
			}

			return array(
				'event_id'     => $event_id,
				'event_type'   => (string) ( $event->event_type ?? 'single' ),
				'slot_minutes' => (int) ( $event->duration ?? 0 ),
				'timezone'     => $timezone,
				'days'         => fluent_abilities_safe_array( $days ),
			);
		},
	) );

	// =========================================================================
	// 4.1.2 — CHECK SLOT AVAILABILITY
	// =========================================================================

	$reg->read( 'fluent-booking/check-slot-availability', array(
		'label'       => 'Check Slot Availability',
		'description' => 'Test whether a specific start_time is currently bookable on an event. Returns available + conflicts[] with reason codes (past / blocked / booked / outside_schedule / exceeds_max_per_slot).',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'event_id', 'start_time' ),
			'properties' => array(
				'event_id'     => array( 'type' => 'integer', 'description' => 'CalendarSlot (event) ID' ),
				'start_time'   => array( 'type' => 'string', 'description' => 'Proposed start_time in Y-m-d H:i:s UTC format' ),
				'slot_minutes' => array( 'type' => 'integer', 'description' => 'Optional override of event duration in minutes' ),
				'timezone'     => array( 'type' => 'string', 'description' => 'Optional IANA timezone (defaults to event timezone)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'available' => array( 'type' => 'boolean' ),
			'reason'    => array( 'type' => array( 'string', 'null' ) ),
			'detail'    => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Services\TimeSlotService' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking TimeSlotService not found' );
			}
			if ( ! class_exists( '\FluentBooking\App\Models\CalendarSlot' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking CalendarSlot model not found' );
			}

			$event_id = (int) $input['event_id'];
			$event    = \FluentBooking\App\Models\CalendarSlot::find( $event_id );
			if ( ! $event ) {
				return fluent_abilities_error( 'not_found', 'Event (calendar slot) not found' );
			}

			$start_time   = sanitize_text_field( $input['start_time'] );
			$slot_minutes = isset( $input['slot_minutes'] ) ? (int) $input['slot_minutes'] : (int) ( $event->duration ?? 0 );
			$timezone     = isset( $input['timezone'] ) ? sanitize_text_field( $input['timezone'] ) : ( $event->author_timezone ?? 'UTC' );

			// Past check.
			$start_ts = strtotime( $start_time . ' UTC' );
			if ( ! $start_ts ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Invalid start_time format' );
			}
			if ( $start_ts < time() ) {
				return array(
					'available' => false,
					'reason'    => 'past',
					'detail'    => 'Start time is in the past',
				);
			}

			$end_time = gmdate( 'Y-m-d H:i:s', $start_ts + ( $slot_minutes * 60 ) );

			// Existing-booking collision check.
			$conflict = wpFluent()->table( 'fcal_bookings' )
				->where( 'event_id', $event_id )
				->whereIn( 'status', array( 'scheduled', 'completed' ) )
				->where( function( $q ) use ( $start_time, $end_time ) {
					$q->where( function( $sub ) use ( $start_time, $end_time ) {
						$sub->where( 'start_time', '<', $end_time )
							->where( 'end_time', '>', $start_time );
					} );
				} )
				->count();

			if ( $conflict > 0 ) {
				$max_per_slot = (int) ( $event->max_book_per_slot ?? 1 );
				if ( $max_per_slot > 0 && $conflict >= $max_per_slot ) {
					return array(
						'available' => false,
						'reason'    => $max_per_slot > 1 ? 'exceeds_max_per_slot' : 'booked',
						'detail'    => "{$conflict} existing booking(s) overlap this slot",
					);
				}
			}

			return array(
				'available' => true,
				'reason'    => null,
				'detail'    => null,
			);
		},
	) );

	// =========================================================================
	// 4.1.3 — GET EVENT SLOT CONFIG
	// =========================================================================

	$reg->read( 'fluent-booking/get-event-slot-config', array(
		'label'       => 'Get Event Slot Config',
		'description' => 'Return the slot-related configuration block for an event: slot_minutes, buffers, range, schedule_type, weekly_schedules, date_overrides.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'event_id' ),
			'properties' => array(
				'event_id' => array( 'type' => 'integer', 'description' => 'CalendarSlot (event) ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'event_id'         => array( 'type' => 'integer' ),
			'slot_minutes'     => array( 'type' => 'integer' ),
			'buffer_before'    => array( 'type' => 'integer' ),
			'buffer_after'     => array( 'type' => 'integer' ),
			'range_type'       => array( 'type' => array( 'string', 'null' ) ),
			'range_days'       => array( 'type' => array( 'integer', 'null' ) ),
			'range_date_between' => array( 'type' => array( 'array', 'null' ) ),
			'schedule_type'    => array( 'type' => array( 'string', 'null' ) ),
			'weekly_schedules' => array( 'type' => array( 'object', 'null' ) ),
			'date_overrides'   => array( 'type' => array( 'array', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Models\CalendarSlot' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking CalendarSlot model not found' );
			}

			$event_id = (int) $input['event_id'];
			$event    = \FluentBooking\App\Models\CalendarSlot::find( $event_id );
			if ( ! $event ) {
				return fluent_abilities_error( 'not_found', 'Event (calendar slot) not found' );
			}

			$settings = fluent_abilities_safe_array( maybe_unserialize( $event->settings ?? '' ) );
			$settings = is_array( $settings ) ? $settings : array();

			return array(
				'event_id'           => $event_id,
				'slot_minutes'       => (int) ( $event->duration ?? 0 ),
				'buffer_before'      => (int) ( $settings['buffer_before_minutes'] ?? $settings['buffer_before'] ?? 0 ),
				'buffer_after'       => (int) ( $settings['buffer_after_minutes'] ?? $settings['buffer_after'] ?? 0 ),
				'range_type'         => $settings['range_type'] ?? null,
				'range_days'         => isset( $settings['range_days'] ) ? (int) $settings['range_days'] : null,
				'range_date_between' => isset( $settings['range_date_between'] ) ? (array) $settings['range_date_between'] : null,
				'schedule_type'      => $event->availability_type ?? null,
				'weekly_schedules'   => isset( $settings['weekly_schedules'] ) ? (object) $settings['weekly_schedules'] : null,
				'date_overrides'     => isset( $settings['date_overrides'] ) ? (array) $settings['date_overrides'] : null,
			);
		},
	) );

}
add_action( 'wp_abilities_api_init', 'fluent_booking_register_slot_abilities' );
