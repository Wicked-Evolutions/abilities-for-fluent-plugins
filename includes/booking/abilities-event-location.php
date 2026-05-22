<?php
/**
 * FluentBooking — Event location config (cluster 4.7).
 *
 * Reads/writes `fcal_calendar_events.location_type` and `location_settings`
 * (LONGTEXT serialized). Vendor location types observed:
 *   ms_teams, google_meet, zoom, phone_organizer, in_person_organizer,
 *   phone_attendee, in_person_attendee, custom
 *
 *   - fluent-booking/get-event-location-config     (read)
 *   - fluent-booking/update-event-location-config  (write)
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

function fluent_booking_register_event_location_abilities() {

	$reg = new Fluent_Abilities_Registrar( 'booking' );

	$location_type_enum = array(
		'ms_teams',
		'google_meet',
		'zoom',
		'phone_organizer',
		'in_person_organizer',
		'phone_attendee',
		'in_person_attendee',
		'custom',
	);

	// =========================================================================
	// 4.7.1 — GET EVENT LOCATION CONFIG
	// =========================================================================

	$reg->read( 'fluent-booking/get-event-location-config', array(
		'label'       => 'Get Event Location Config',
		'description' => 'Return location_type, location_heading, and provider-specific location_settings for an event.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'event_id' ),
			'properties' => array(
				'event_id' => array( 'type' => 'integer', 'description' => 'CalendarSlot (event) ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'event_id'         => array( 'type' => 'integer' ),
			'location_type'    => array( 'type' => array( 'string', 'null' ) ),
			'location_heading' => array( 'type' => array( 'string', 'null' ) ),
			'location_settings' => array( 'type' => array( 'object', 'array', 'null' ) ),
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

			$settings = maybe_unserialize( $event->location_settings ?? '' );
			$settings = fluent_abilities_safe_array( $settings );

			return array(
				'event_id'          => $event_id,
				'location_type'     => $event->location_type ? (string) $event->location_type : null,
				'location_heading'  => $event->location_heading ? (string) $event->location_heading : null,
				'location_settings' => $settings,
			);
		},
	) );

	// =========================================================================
	// 4.7.2 — UPDATE EVENT LOCATION CONFIG
	// =========================================================================

	$reg->write( 'fluent-booking/update-event-location-config', array(
		'label'       => 'Update Event Location Config',
		'description' => 'Update location_type, location_heading, and provider-specific location_settings on an event. location_settings is provider-shaped (e.g. Zoom requires account id; in_person requires address).',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'event_id', 'location_type' ),
			'properties' => array(
				'event_id'          => array( 'type' => 'integer', 'description' => 'CalendarSlot (event) ID' ),
				'location_type'     => array(
					'type'        => 'string',
					'description' => 'Location provider/type',
					'enum'        => $location_type_enum,
				),
				'location_heading'  => array( 'type' => 'string', 'description' => 'Optional display heading' ),
				'location_settings' => array(
					'type'        => array( 'object', 'array' ),
					'description' => 'Provider-specific settings object (e.g. {account_id, password} for zoom; {address} for in_person)',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'event_id'      => array( 'type' => 'integer' ),
			'location_type' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) use ( $location_type_enum ) {
			if ( ! class_exists( '\FluentBooking\App\Models\CalendarSlot' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking CalendarSlot model not found' );
			}

			$event_id = (int) $input['event_id'];
			$event    = \FluentBooking\App\Models\CalendarSlot::find( $event_id );
			if ( ! $event ) {
				return fluent_abilities_error( 'not_found', 'Event (calendar slot) not found' );
			}

			$location_type = sanitize_text_field( $input['location_type'] );
			if ( ! in_array( $location_type, $location_type_enum, true ) ) {
				return fluent_abilities_error(
					'ability_invalid_input',
					'Invalid location_type. Allowed: ' . implode( ', ', $location_type_enum )
				);
			}

			$update = array( 'location_type' => $location_type );

			if ( isset( $input['location_heading'] ) ) {
				$update['location_heading'] = sanitize_text_field( $input['location_heading'] );
			}

			if ( isset( $input['location_settings'] ) ) {
				$settings = is_array( $input['location_settings'] ) ? $input['location_settings'] : (array) $input['location_settings'];
				// V3: assign the plain array. Vendor CalendarSlot::
				// setLocationSettingsAttribute() maybe_serialize()s on set and
				// getLocationSettingsAttribute() maybe_unserialize()s on read
				// (installed FluentBooking app/Models/CalendarSlot.php:80-87).
				// Pre-serializing here passed an already-serialized string into
				// the mutator → double-serialize; the vendor read then returned
				// a string and vendor count( $this->location_settings ) fataled
				// (PHP 8.3) → FluentBooking front-end calendar 500. Let the
				// vendor mutator perform the single canonical serialization.
				$update['location_settings'] = $settings;
			}

			$event->fill( $update );
			$event->save();

			return array(
				'success'       => true,
				'event_id'      => $event_id,
				'location_type' => $location_type,
			);
		},
	) );

}
add_action( 'wp_abilities_api_init', 'fluent_booking_register_event_location_abilities' );
