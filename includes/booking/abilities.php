<?php
/**
 * FluentBooking Abilities — Calendars, Events, Bookings (read), Hosts
 *
 * 22 abilities in the 'fluent-booking' category.
 * Registered via Fluent_Abilities_Registrar.
 *
 * Existing (7 read + 2 write refactored) + New (4 calendar + 5 event + 2 booking-fields + 1 hosts + 1 clone-event = 13).
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

/**
 * Recursively strip privileged host-only fields from a booking's meta /
 * location_details before they cross an ordinary read boundary (#140).
 *
 * get-booking is an unscoped read; without this, its output disclosed the Zoom
 * host "start as host" token/link (start_url / zak / *_start_link) and the host's
 * Google Calendar links (remote_link / remote_calendar_id) — letting any caller
 * with read access host the meeting or read the host mailbox's calendar.
 * Attendee-safe fields (join_url, password, meeting_id, etc.) are preserved.
 *
 * @param mixed $value Decoded meta value (array or scalar).
 * @return mixed The value with privileged keys removed at any depth.
 */
function fluent_abilities_booking_redact_host_secrets( $value ) {
	if ( ! is_array( $value ) ) {
		return $value;
	}
	$deny = array( 'start_url', 'zak', 'start_link', 'online_platform_start_link', 'remote_link', 'remote_calendar_id' );
	$out  = array();
	foreach ( $value as $k => $v ) {
		if ( is_string( $k ) ) {
			$lk = strtolower( $k );
			if ( in_array( $lk, $deny, true )
				|| substr( $lk, -11 ) === '_start_link'
				|| substr( $lk, -10 ) === '_start_url' ) {
				continue;
			}
		}
		$out[ $k ] = is_array( $v ) ? fluent_abilities_booking_redact_host_secrets( $v ) : $v;
	}
	return $out;
}

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'booking' );

	// =========================================================================
	// CALENDARS — READ
	// =========================================================================

	$reg->read( 'fluent-booking/list-calendars', array(
		'label'       => 'List Booking Calendars',
		'description' => 'List all FluentBooking calendars with event counts.',
		'input_schema' => array(
			'type'       => 'object',
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'calendars', array(
			'id'               => array( 'type' => 'integer' ),
			'title'            => array( 'type' => 'string' ),
			'slug'             => array( 'type' => 'string' ),
			'status'           => array( 'type' => 'string' ),
			'type'             => array( 'type' => 'string' ),
			'event_type'       => array( 'type' => 'string' ),
			'author_timezone'  => array( 'type' => array( 'string', 'null' ) ),
			'event_count'      => array( 'type' => 'integer' ),
			'created_at'       => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$calendars = wpFluent()->table( 'fcal_calendars' )
				->orderBy( 'id', 'ASC' )
				->get();

			$items = array();
			foreach ( $calendars as $calendar ) {
				$event_count = (int) wpFluent()->table( 'fcal_calendar_events' )
					->where( 'calendar_id', $calendar->id )
					->count();

				$items[] = array(
					'id'              => (int) $calendar->id,
					'title'           => $calendar->title ?? '',
					'slug'            => $calendar->slug ?? '',
					'status'          => $calendar->status ?? '',
					'type'            => $calendar->type ?? '',
					'event_type'      => $calendar->event_type ?? '',
					'author_timezone' => $calendar->author_timezone ?? null,
					'event_count'     => $event_count,
					'created_at'      => $calendar->created_at ? (string) $calendar->created_at : null,
				);
			}

			return array( 'calendars' => $items, 'total' => count( $items ) );
		},
	) );

	// =========================================================================
	// GET CALENDAR (P0)
	// =========================================================================

	$reg->read( 'fluent-booking/get-calendar', array(
		'label'       => 'Get Calendar',
		'description' => 'Get a single calendar by ID with full details including settings, description, and user info.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Calendar ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'               => array( 'type' => 'integer' ),
			'title'            => array( 'type' => 'string' ),
			'slug'             => array( 'type' => 'string' ),
			'description'      => array( 'type' => array( 'string', 'null' ) ),
			'status'           => array( 'type' => 'string' ),
			'type'             => array( 'type' => 'string' ),
			'event_type'       => array( 'type' => 'string' ),
			'visibility'       => array( 'type' => 'string' ),
			'author_timezone'  => array( 'type' => array( 'string', 'null' ) ),
			'user_id'          => array( 'type' => 'integer' ),
			'settings'         => array( 'type' => array( 'object', 'null' ) ),
			'event_count'      => array( 'type' => 'integer' ),
			'created_at'       => array( 'type' => array( 'string', 'null' ) ),
			'updated_at'       => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$calendar = wpFluent()->table( 'fcal_calendars' )
				->where( 'id', (int) $input['id'] )
				->first();

			if ( ! $calendar ) {
				return fluent_abilities_error( 'not_found', 'Calendar not found' );
			}

			$event_count = (int) wpFluent()->table( 'fcal_calendar_events' )
				->where( 'calendar_id', $calendar->id )
				->count();

			$settings = fluent_abilities_safe_array( maybe_unserialize( $calendar->settings ?? '' ) );

			return array(
				'id'              => (int) $calendar->id,
				'hash'            => $calendar->hash ?? null,
				'title'           => $calendar->title ?? '',
				'slug'            => $calendar->slug ?? '',
				'description'     => $calendar->description ?? null,
				'status'          => $calendar->status ?? '',
				'type'            => $calendar->type ?? '',
				'event_type'      => $calendar->event_type ?? '',
				'visibility'      => $calendar->visibility ?? 'public',
				'author_timezone' => $calendar->author_timezone ?? null,
				'user_id'         => (int) ( $calendar->user_id ?? 0 ),
				'settings'        => ! empty( $settings ) ? $settings : null,
				'event_count'     => $event_count,
				'created_at'      => $calendar->created_at ? (string) $calendar->created_at : null,
				'updated_at'      => $calendar->updated_at ? (string) $calendar->updated_at : null,
			);
		},
	) );

	// =========================================================================
	// CREATE CALENDAR (P0) — uses Calendar model for hooks
	// =========================================================================

	$reg->write( 'fluent-booking/create-calendar', array(
		'label'       => 'Create Calendar',
		'description' => 'Create a new FluentBooking calendar. For "simple" type (host calendar), each user can only have one. The calendar title is auto-set from the user\'s name for simple calendars.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'author_timezone' ),
			'properties' => array(
				'title'           => array( 'type' => 'string', 'description' => 'Calendar title (required for non-simple types)' ),
				'author_timezone' => array( 'type' => 'string', 'description' => 'Timezone (e.g. America/New_York)' ),
				'type'            => array(
					'type'        => 'string',
					'description' => 'Calendar type (default: simple)',
					'enum'        => array( 'simple', 'team', 'event' ),
				),
				'user_id'         => array( 'type' => 'integer', 'description' => 'Host user ID (default: current user)' ),
				'description'     => array( 'type' => 'string', 'description' => 'Calendar description (optional)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
			'slug'  => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Models\Calendar' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking Calendar model not found' );
			}
			if ( ! class_exists( '\FluentBooking\App\Services\Helper' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking Helper not found' );
			}

			$type    = sanitize_text_field( $input['type'] ?? 'simple' );
			$user_id = (int) ( $input['user_id'] ?? get_current_user_id() );
			$user    = get_user_by( 'ID', $user_id );

			if ( ! $user ) {
				return fluent_abilities_error( 'user_not_found', 'User not found' );
			}

			$is_host = ( $type === 'simple' );

			// Simple calendars: one per user.
			if ( $is_host ) {
				$existing = \FluentBooking\App\Models\Calendar::where( 'user_id', $user->ID )
					->where( 'type', 'simple' )
					->first();
				if ( $existing ) {
					return fluent_abilities_error( 'already_exists', 'This user already has a host calendar. Delete it first to create a new one.' );
				}
			}

			// Generate slug.
			if ( $is_host ) {
				$username = $user->user_login;
				if ( is_email( $username ) ) {
					$username = explode( '@', $username )[0] . '-' . time();
				}
				$slug = sanitize_title( $username, '', 'display' );
			} else {
				$title = sanitize_text_field( $input['title'] ?? '' );
				if ( empty( $title ) ) {
					return fluent_abilities_error( 'missing_title', 'Title is required for non-simple calendar types' );
				}
				$slug = sanitize_title( $title, '', 'display' );
			}

			if ( ! \FluentBooking\App\Services\Helper::isCalendarSlugAvailable( $slug, true ) ) {
				$slug .= '-' . time();
			}

			$person_name = trim( $user->first_name . ' ' . $user->last_name );
			if ( ! $person_name ) {
				$person_name = $user->display_name;
			}

			$calendar_data = array(
				'slug'            => $slug,
				'user_id'         => $user->ID,
				'title'           => $is_host ? $person_name : sanitize_text_field( $input['title'] ),
				'type'            => $type,
				'author_timezone' => sanitize_text_field( $input['author_timezone'] ) ?: 'UTC',
			);

			if ( ! empty( $input['description'] ) ) {
				$calendar_data['description'] = wp_kses_post( $input['description'] );
			}

			$calendar = \FluentBooking\App\Models\Calendar::create( $calendar_data );

			do_action( 'fluent_booking/after_create_calendar', $calendar );

			return array(
				'success' => true,
				'id'      => (int) $calendar->id,
				'title'   => $calendar->title ?? '',
				'slug'    => $calendar->slug ?? '',
			);
		},
	) );

	// =========================================================================
	// UPDATE CALENDAR (P0)
	// =========================================================================

	$reg->write( 'fluent-booking/update-calendar', array(
		'label'       => 'Update Calendar',
		'description' => 'Update a calendar\'s title, description, status, or timezone.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'              => array( 'type' => 'integer', 'description' => 'Calendar ID' ),
				'title'           => array( 'type' => 'string', 'description' => 'New title' ),
				'description'     => array( 'type' => 'string', 'description' => 'New description' ),
				'status'          => array(
					'type'        => 'string',
					'description' => 'New status',
					'enum'        => array( 'active', 'inactive', 'expired' ),
				),
				'author_timezone' => array( 'type' => 'string', 'description' => 'New timezone' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Models\Calendar' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking Calendar model not found' );
			}

			$calendar = \FluentBooking\App\Models\Calendar::find( (int) $input['id'] );
			if ( ! $calendar ) {
				return fluent_abilities_error( 'not_found', 'Calendar not found' );
			}

			$data = array();

			if ( isset( $input['title'] ) ) {
				$calendar->title = sanitize_text_field( $input['title'] );
			}
			if ( isset( $input['description'] ) ) {
				$calendar->description = wp_kses_post( $input['description'] );
			}
			if ( isset( $input['status'] ) ) {
				$calendar->status = sanitize_text_field( $input['status'] );
			}
			if ( isset( $input['author_timezone'] ) ) {
				$calendar->author_timezone = sanitize_text_field( $input['author_timezone'] );
			}

			do_action( 'fluent_booking/before_update_calendar', $calendar, $input );

			$calendar->save();

			do_action( 'fluent_booking/after_update_calendar', $calendar, $input );

			return array( 'success' => true, 'id' => (int) $calendar->id );
		},
	) );

	// =========================================================================
	// DELETE CALENDAR (P0)
	// =========================================================================

	$reg->delete( 'fluent-booking/delete-calendar', array(
		'label'       => 'Delete Calendar',
		'description' => 'Delete a calendar by ID. Cascading deletes of events and bookings are handled by the Calendar model.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Calendar ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Models\Calendar' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking Calendar model not found' );
			}

			$calendar = \FluentBooking\App\Models\Calendar::find( (int) $input['id'] );
			if ( ! $calendar ) {
				return fluent_abilities_error( 'not_found', 'Calendar not found' );
			}

			$calendar_id = (int) $calendar->id;

			do_action( 'fluent_booking/before_delete_calendar', $calendar );

			$calendar->delete();

			do_action( 'fluent_booking/after_delete_calendar', $calendar_id );

			return array( 'success' => true, 'id' => $calendar_id );
		},
	) );

	// =========================================================================
	// EVENTS — READ
	// =========================================================================

	$reg->read( 'fluent-booking/list-events', array(
		'label'       => 'List Booking Events',
		'description' => 'List events (appointment types) with optional calendar filter. Shows duration, type, status, and capacity.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'calendar_id' => array(
					'type'        => 'integer',
					'description' => 'Filter by calendar ID (optional)',
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'Filter by status: active, draft, disabled',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'events', array(
			'id'                => array( 'type' => 'integer' ),
			'calendar_id'       => array( 'type' => 'integer' ),
			'title'             => array( 'type' => 'string' ),
			'slug'              => array( 'type' => 'string' ),
			'duration'          => array( 'type' => 'integer' ),
			'type'              => array( 'type' => 'string' ),
			'event_type'        => array( 'type' => 'string' ),
			'status'            => array( 'type' => 'string' ),
			'max_book_per_slot' => array( 'type' => 'integer' ),
			'color_schema'      => array( 'type' => 'string' ),
			'location_type'     => array( 'type' => 'string' ),
			'created_at'        => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$query = wpFluent()->table( 'fcal_calendar_events' )->orderBy( 'id', 'ASC' );

			if ( ! empty( $input['calendar_id'] ) ) {
				$query->where( 'calendar_id', (int) $input['calendar_id'] );
			}

			if ( ! empty( $input['status'] ) ) {
				$query->where( 'status', sanitize_text_field( $input['status'] ) );
			}

			$events = $query->get();

			$items = array();
			foreach ( $events as $event ) {
				$items[] = array(
					'id'                => (int) $event->id,
					'calendar_id'       => (int) $event->calendar_id,
					'title'             => $event->title ?? '',
					'slug'              => $event->slug ?? '',
					'duration'          => (int) $event->duration,
					'type'              => $event->type ?? '',
					'event_type'        => $event->event_type ?? '',
					'status'            => $event->status ?? '',
					'max_book_per_slot' => (int) $event->max_book_per_slot,
					'color_schema'      => $event->color_schema ?? '',
					'location_type'     => $event->location_type ?? '',
					'created_at'        => $event->created_at ? (string) $event->created_at : null,
				);
			}

			return array( 'events' => $items, 'total' => count( $items ) );
		},
	) );

	// =========================================================================
	// CREATE EVENT (P0) — uses CalendarSlot model for hooks
	// =========================================================================

	$reg->write( 'fluent-booking/create-event', array(
		'label'       => 'Create Event',
		'description' => 'Create a new event (appointment type) on a calendar. Requires title, duration, and status.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'calendar_id', 'title', 'duration' ),
			'properties' => array(
				'calendar_id'      => array( 'type' => 'integer', 'description' => 'Parent calendar ID' ),
				'title'            => array( 'type' => 'string', 'description' => 'Event title' ),
				'duration'         => array( 'type' => 'integer', 'description' => 'Duration in minutes (min: 5)' ),
				'description'      => array( 'type' => 'string', 'description' => 'Event description (optional)' ),
				'status'           => array(
					'type'        => 'string',
					'description' => 'Status (default: active)',
					'enum'        => array( 'active', 'draft' ),
				),
				'event_type'       => array(
					'type'        => 'string',
					'description' => 'Event type (default: single)',
					'enum'        => array( 'single', 'group', 'round_robin' ),
				),
				'color_schema'     => array( 'type' => 'string', 'description' => 'Color hex (default: #0099ff)' ),
				'max_book_per_slot'=> array( 'type' => 'integer', 'description' => 'Max bookings per slot (default: 1, relevant for group events)' ),
				'location_type'    => array( 'type' => 'string', 'description' => 'Location type (e.g. ms_teams, google_meet, zoom, phone_organizer, in_person_organizer, custom)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'          => array( 'type' => 'integer' ),
			'calendar_id' => array( 'type' => 'integer' ),
			'title'       => array( 'type' => 'string' ),
			'slug'        => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Models\Calendar' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking Calendar model not found' );
			}
			if ( ! class_exists( '\FluentBooking\App\Models\CalendarSlot' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking CalendarSlot model not found' );
			}
			if ( ! class_exists( '\FluentBooking\App\Services\Helper' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking Helper not found' );
			}
			if ( ! class_exists( '\FluentBooking\App\Services\AvailabilityService' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking AvailabilityService not found' );
			}

			$calendar = \FluentBooking\App\Models\Calendar::find( (int) $input['calendar_id'] );
			if ( ! $calendar ) {
				return fluent_abilities_error( 'not_found', 'Calendar not found' );
			}

			$duration = (int) $input['duration'];
			if ( $duration < 5 ) {
				return fluent_abilities_error( 'invalid_duration', 'Duration must be at least 5 minutes' );
			}

			$title  = sanitize_text_field( $input['title'] );
			$slug   = \FluentBooking\App\Services\Helper::generateSlotSlug( $duration . 'min', $calendar );
			$status = sanitize_text_field( $input['status'] ?? 'active' );

			// V10: AvailabilityService::getDefaultSchedule() can call reset() on a null
			// schedule list when the calendar user has no configured availability
			// (P-K pattern, F-BOOK-01). Wrap to convert the PHP TypeError into a
			// typed WP_Error rather than letting the fatal propagate.
			try {
				$availability = \FluentBooking\App\Services\AvailabilityService::getDefaultSchedule( $calendar->user_id );
			} catch ( \Throwable $e ) {
				return new WP_Error(
					'vendor_precondition_failed',
					'FluentBooking default-schedule lookup failed for calendar user ' . (int) $calendar->user_id . ': ' . $e->getMessage()
				);
			}

			$slot_data = array(
				'title'             => $title,
				'slug'              => $slug,
				'calendar_id'       => $calendar->id,
				'user_id'           => $calendar->user_id,
				'duration'          => $duration,
				'description'       => wp_kses_post( $input['description'] ?? '' ),
				'status'            => in_array( $status, array( 'active', 'draft' ), true ) ? $status : 'active',
				'color_schema'      => sanitize_text_field( $input['color_schema'] ?? '#0099ff' ),
				'event_type'        => sanitize_text_field( $input['event_type'] ?? 'single' ),
				'availability_type' => 'existing_schedule',
				'availability_id'   => $availability ? $availability->id : null,
				'location_type'     => sanitize_text_field( $input['location_type'] ?? '' ),
				'max_book_per_slot' => (int) ( $input['max_book_per_slot'] ?? 1 ),
			);

			$slot_data = apply_filters( 'fluent_booking/create_calendar_event_data', $slot_data, $calendar );

			do_action( 'fluent_booking/before_create_event', $calendar, $slot_data );

			$slot = \FluentBooking\App\Models\CalendarSlot::create( $slot_data );

			do_action( 'fluent_booking/after_create_event', $calendar, $slot );

			if ( method_exists( $calendar, 'updateEventOrder' ) ) {
				$calendar->updateEventOrder( $slot->id );
			}

			return array(
				'success'     => true,
				'id'          => (int) $slot->id,
				'calendar_id' => (int) $slot->calendar_id,
				'title'       => $slot->title ?? '',
				'slug'        => $slot->slug ?? '',
			);
		},
	) );

	// =========================================================================
	// UPDATE EVENT (P0) — uses CalendarSlot model for hooks
	// =========================================================================

	$reg->write( 'fluent-booking/update-event', array(
		'label'       => 'Update Event',
		'description' => 'Update an event\'s title, duration, description, status, color, and location settings.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'               => array( 'type' => 'integer', 'description' => 'Event ID' ),
				'title'            => array( 'type' => 'string', 'description' => 'New title' ),
				'duration'         => array( 'type' => 'integer', 'description' => 'New duration in minutes' ),
				'description'      => array( 'type' => 'string', 'description' => 'New description' ),
				'status'           => array(
					'type'        => 'string',
					'description' => 'New status',
					'enum'        => array( 'active', 'draft' ),
				),
				'color_schema'     => array( 'type' => 'string', 'description' => 'Color hex' ),
				'max_book_per_slot'=> array( 'type' => 'integer', 'description' => 'Max bookings per slot' ),
				'location_type'    => array( 'type' => 'string', 'description' => 'Location type' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Models\CalendarSlot' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking CalendarSlot model not found' );
			}

			$event = \FluentBooking\App\Models\CalendarSlot::find( (int) $input['id'] );
			if ( ! $event ) {
				return fluent_abilities_error( 'not_found', 'Event not found' );
			}

			if ( isset( $input['title'] ) ) {
				$event->title = sanitize_text_field( $input['title'] );
			}
			if ( isset( $input['duration'] ) ) {
				$duration = (int) $input['duration'];
				if ( $duration < 5 ) {
					return fluent_abilities_error( 'invalid_duration', 'Duration must be at least 5 minutes' );
				}
				$event->duration = $duration;
			}
			if ( isset( $input['description'] ) ) {
				$event->description = wp_kses_post( $input['description'] );
			}
			if ( isset( $input['status'] ) ) {
				$event->status = sanitize_text_field( $input['status'] );
			}
			if ( isset( $input['color_schema'] ) ) {
				$event->color_schema = sanitize_text_field( $input['color_schema'] );
			}
			if ( isset( $input['max_book_per_slot'] ) ) {
				$event->max_book_per_slot = (int) $input['max_book_per_slot'];
			}
			if ( isset( $input['location_type'] ) ) {
				$event->location_type = sanitize_text_field( $input['location_type'] );
			}

			$event->save();

			do_action( 'fluent_booking/after_update_event_details', $event );

			return array( 'success' => true, 'id' => (int) $event->id );
		},
	) );

	// =========================================================================
	// DELETE EVENT (P0) — uses CalendarSlot model for hooks
	// =========================================================================

	$reg->delete( 'fluent-booking/delete-event', array(
		'label'       => 'Delete Event',
		'description' => 'Delete an event (calendar slot) by ID. Fires before/after delete hooks.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Event ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Models\CalendarSlot' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking CalendarSlot model not found' );
			}

			$event = \FluentBooking\App\Models\CalendarSlot::find( (int) $input['id'] );
			if ( ! $event ) {
				return fluent_abilities_error( 'not_found', 'Event not found' );
			}

			$event_id    = (int) $event->id;
			$calendar_id = (int) $event->calendar_id;

			// Update event order on the calendar.
			if ( class_exists( '\FluentBooking\App\Models\Calendar' ) ) {
				$calendar = \FluentBooking\App\Models\Calendar::find( $calendar_id );
				if ( $calendar && method_exists( $calendar, 'updateEventOrder' ) ) {
					$calendar->updateEventOrder( $event_id );
				}
			}

			do_action( 'fluent_booking/before_delete_calendar_event', $event, $calendar ?? null );

			$event->delete();

			do_action( 'fluent_booking/after_delete_calendar_event', $event_id, $calendar ?? null );

			return array( 'success' => true, 'id' => $event_id );
		},
	) );

	// =========================================================================
	// UPDATE EVENT STATUS (P0)
	// =========================================================================

	$reg->write( 'fluent-booking/update-event-status', array(
		'label'       => 'Update Event Status',
		'description' => 'Update the status of an event (active/draft). Uses CalendarSlot model.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id', 'status' ),
			'properties' => array(
				'id'     => array( 'type' => 'integer', 'description' => 'Event ID' ),
				'status' => array(
					'type'        => 'string',
					'description' => 'New event status',
					'enum'        => array( 'active', 'draft' ),
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'     => array( 'type' => 'integer' ),
			'status' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Models\CalendarSlot' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking CalendarSlot model not found' );
			}

			$event = \FluentBooking\App\Models\CalendarSlot::find( (int) $input['id'] );
			if ( ! $event ) {
				return fluent_abilities_error( 'not_found', 'Event not found' );
			}

			$status = sanitize_text_field( $input['status'] );
			$event->status = $status;
			$event->save();

			return array( 'success' => true, 'id' => (int) $event->id, 'status' => $event->status ?? '' );
		},
	) );

	// =========================================================================
	// CLONE EVENT (P1) — uses CalendarSlot replicate for hooks
	// =========================================================================

	$reg->write( 'fluent-booking/clone-event', array(
		'label'       => 'Clone Event',
		'description' => 'Clone an event, optionally to a different calendar. Copies all settings and meta.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'              => array( 'type' => 'integer', 'description' => 'Event ID to clone' ),
				'new_calendar_id' => array( 'type' => 'integer', 'description' => 'Target calendar ID (optional — defaults to same calendar)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'          => array( 'type' => 'integer' ),
			'calendar_id' => array( 'type' => 'integer' ),
			'title'       => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Models\CalendarSlot' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking CalendarSlot model not found' );
			}
			if ( ! class_exists( '\FluentBooking\App\Models\Calendar' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking Calendar model not found' );
			}
			if ( ! class_exists( '\FluentBooking\App\Services\Helper' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking Helper not found' );
			}

			$original = \FluentBooking\App\Models\CalendarSlot::with( 'event_metas' )
				->find( (int) $input['id'] );

			if ( ! $original ) {
				return fluent_abilities_error( 'not_found', 'Event not found' );
			}

			$target_calendar_id = (int) ( $input['new_calendar_id'] ?? $original->calendar_id );
			$calendar = \FluentBooking\App\Models\Calendar::find( $target_calendar_id );
			if ( ! $calendar ) {
				return fluent_abilities_error( 'not_found', 'Target calendar not found' );
			}

			$cloned = $original->replicate();
			$cloned->hash        = null;
			$cloned->calendar_id = $calendar->id;
			$cloned->user_id     = $calendar->user_id;
			$cloned->title       = $original->title . ' (clone)';
			$cloned->slug        = \FluentBooking\App\Services\Helper::generateSlotSlug(
				$cloned->duration . 'min', $calendar
			);
			$cloned->save();

			if ( method_exists( $calendar, 'updateEventOrder' ) ) {
				$calendar->updateEventOrder( $cloned->id );
			}

			// Clone event metas.
			if ( $original->event_metas ) {
				foreach ( $original->event_metas as $meta ) {
					$cloned_meta = $meta->replicate();
					$cloned_meta->object_id = $cloned->id;
					$cloned_meta->save();
				}
			}

			return array(
				'success'     => true,
				'id'          => (int) $cloned->id,
				'calendar_id' => (int) $cloned->calendar_id,
				'title'       => $cloned->title ?? '',
			);
		},
	) );

	// =========================================================================
	// GET EVENT BOOKING FIELDS (P1)
	// =========================================================================

	$reg->read( 'fluent-booking/get-event-booking-fields', array(
		'label'       => 'Get Event Booking Fields',
		'description' => 'Get the custom booking form fields configured on an event.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'event_id' ),
			'properties' => array(
				'event_id' => array( 'type' => 'integer', 'description' => 'Event (calendar slot) ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'event_id' => array( 'type' => 'integer' ),
			'fields'   => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Models\CalendarSlot' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking CalendarSlot model not found' );
			}

			$event = \FluentBooking\App\Models\CalendarSlot::find( (int) $input['event_id'] );
			if ( ! $event ) {
				return fluent_abilities_error( 'not_found', 'Event not found' );
			}

			$fields = method_exists( $event, 'getBookingFields' )
				? $event->getBookingFields()
				: array();

			return array(
				'event_id' => (int) $event->id,
				'fields'   => fluent_abilities_safe_array( $fields ),
			);
		},
	) );

	// =========================================================================
	// UPDATE EVENT BOOKING FIELDS (P1)
	// =========================================================================

	$reg->write( 'fluent-booking/update-event-booking-fields', array(
		'label'       => 'Update Event Booking Fields',
		'description' => 'Update the custom booking form fields on an event. Pass the full array of field configurations.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'event_id', 'fields' ),
			'properties' => array(
				'event_id' => array( 'type' => 'integer', 'description' => 'Event (calendar slot) ID' ),
				'fields'   => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'object' ),
					'description' => 'Array of booking field configurations',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'event_id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Models\CalendarSlot' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking CalendarSlot model not found' );
			}

			$event = \FluentBooking\App\Models\CalendarSlot::find( (int) $input['event_id'] );
			if ( ! $event ) {
				return fluent_abilities_error( 'not_found', 'Event not found' );
			}

			if ( ! method_exists( $event, 'setBookingFields' ) ) {
				return fluent_abilities_error( 'unsupported', 'setBookingFields method not available on this CalendarSlot version' );
			}

			$event->setBookingFields( $input['fields'] );

			return array( 'success' => true, 'event_id' => (int) $event->id );
		},
	) );

	// =========================================================================
	// BOOKINGS — READ
	// =========================================================================

	$reg->read( 'fluent-booking/list-bookings', array(
		'label'       => 'List Bookings',
		'description' => 'List bookings with pagination. Filter by event, status, date range, or attendee email. Includes attendee name, event title, times, and status.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'event_id' => array(
					'type'        => 'integer',
					'description' => 'Filter by event ID',
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'Filter by status: scheduled, completed, cancelled, rejected, no-show',
				),
				'email' => array(
					'type'        => 'string',
					'description' => 'Filter by attendee email',
				),
				'date_from' => array(
					'type'        => 'string',
					'description' => 'Start date filter (Y-m-d format, compared against start_time)',
				),
				'date_to' => array(
					'type'        => 'string',
					'description' => 'End date filter (Y-m-d format, compared against start_time)',
				),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'bookings', array(
			'id'              => array( 'type' => 'integer' ),
			'event_id'        => array( 'type' => 'integer' ),
			'event_title'     => array( 'type' => array( 'string', 'null' ) ),
			'first_name'      => array( 'type' => array( 'string', 'null' ) ),
			'last_name'       => array( 'type' => array( 'string', 'null' ) ),
			'email'           => array( 'type' => array( 'string', 'null' ) ),
			'start_time'      => array( 'type' => array( 'string', 'null' ) ),
			'end_time'        => array( 'type' => array( 'string', 'null' ) ),
			'slot_minutes'    => array( 'type' => 'integer' ),
			'status'          => array( 'type' => 'string' ),
			'booking_type'    => array( 'type' => array( 'string', 'null' ) ),
			'payment_status'  => array( 'type' => array( 'string', 'null' ) ),
			'person_time_zone'=> array( 'type' => array( 'string', 'null' ) ),
			'source'          => array( 'type' => 'string' ),
			'created_at'      => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );

			$query = wpFluent()->table( 'fcal_bookings' )
				->select( array(
					'fcal_bookings.id',
					'fcal_bookings.event_id',
					'fcal_bookings.first_name',
					'fcal_bookings.last_name',
					'fcal_bookings.email',
					'fcal_bookings.start_time',
					'fcal_bookings.end_time',
					'fcal_bookings.slot_minutes',
					'fcal_bookings.status',
					'fcal_bookings.booking_type',
					'fcal_bookings.payment_status',
					'fcal_bookings.person_time_zone',
					'fcal_bookings.source',
					'fcal_bookings.created_at',
					'fcal_calendar_events.title as event_title',
				))
				->leftJoin( 'fcal_calendar_events', 'fcal_calendar_events.id', '=', 'fcal_bookings.event_id' );

			if ( ! empty( $input['event_id'] ) ) {
				$query->where( 'fcal_bookings.event_id', (int) $input['event_id'] );
			}

			if ( ! empty( $input['status'] ) ) {
				$query->where( 'fcal_bookings.status', sanitize_text_field( $input['status'] ) );
			}

			if ( ! empty( $input['email'] ) ) {
				$query->where( 'fcal_bookings.email', sanitize_email( $input['email'] ) );
			}

			if ( ! empty( $input['date_from'] ) ) {
				$date_from = sanitize_text_field( $input['date_from'] ) . ' 00:00:00';
				$query->where( 'fcal_bookings.start_time', '>=', $date_from );
			}

			if ( ! empty( $input['date_to'] ) ) {
				$date_to = sanitize_text_field( $input['date_to'] ) . ' 23:59:59';
				$query->where( 'fcal_bookings.start_time', '<=', $date_to );
			}

			// Count before pagination.
			$count_query = wpFluent()->table( 'fcal_bookings' );

			if ( ! empty( $input['event_id'] ) ) {
				$count_query->where( 'event_id', (int) $input['event_id'] );
			}
			if ( ! empty( $input['status'] ) ) {
				$count_query->where( 'status', sanitize_text_field( $input['status'] ) );
			}
			if ( ! empty( $input['email'] ) ) {
				$count_query->where( 'email', sanitize_email( $input['email'] ) );
			}
			if ( ! empty( $input['date_from'] ) ) {
				$count_query->where( 'start_time', '>=', sanitize_text_field( $input['date_from'] ) . ' 00:00:00' );
			}
			if ( ! empty( $input['date_to'] ) ) {
				$count_query->where( 'start_time', '<=', sanitize_text_field( $input['date_to'] ) . ' 23:59:59' );
			}

			$total = (int) $count_query->count();

			$bookings = $query
				->orderBy( 'fcal_bookings.start_time', 'DESC' )
				->offset( $pagination['offset'] )
				->limit( $pagination['per_page'] )
				->get();

			$items = array();
			foreach ( $bookings as $booking ) {
				$items[] = array(
					'id'               => (int) $booking->id,
					'event_id'         => (int) $booking->event_id,
					'event_title'      => $booking->event_title ?? null,
					'first_name'       => $booking->first_name ?? null,
					'last_name'        => $booking->last_name ?? null,
					'email'            => $booking->email ?? null,
					'start_time'       => $booking->start_time ? (string) $booking->start_time : null,
					'end_time'         => $booking->end_time ? (string) $booking->end_time : null,
					'slot_minutes'     => (int) $booking->slot_minutes,
					'status'           => $booking->status ?? '',
					'booking_type'     => $booking->booking_type ?? null,
					'payment_status'   => $booking->payment_status ?? null,
					'person_time_zone' => $booking->person_time_zone ?? null,
					'source'           => $booking->source ?? '',
					'created_at'       => $booking->created_at ? (string) $booking->created_at : null,
				);
			}

			return array(
				'bookings' => $items,
				'total'    => $total,
				'page'     => $pagination['page'],
				'per_page' => $pagination['per_page'],
			);
		},
	) );

	$reg->read( 'fluent-booking/get-booking', array(
		'label'       => 'Get Booking',
		'description' => 'Get a single booking by ID with full details including parsed location details, event info, and host information.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Booking ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'              => array( 'type' => 'integer' ),
			'event_id'        => array( 'type' => 'integer' ),
			'status'          => array( 'type' => 'string' ),
			'start_time'      => array( 'type' => array( 'string', 'null' ) ),
			'end_time'        => array( 'type' => array( 'string', 'null' ) ),
			'email'           => array( 'type' => array( 'string', 'null' ) ),
			'first_name'      => array( 'type' => array( 'string', 'null' ) ),
			'last_name'       => array( 'type' => array( 'string', 'null' ) ),
			'hosts'           => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'meta'            => array( 'type' => 'object' ),
			'created_at'      => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$booking = wpFluent()->table( 'fcal_bookings' )
				->where( 'id', (int) $input['id'] )
				->first();

			if ( ! $booking ) {
				return fluent_abilities_error( 'not_found', 'Booking not found' );
			}

			// Get event title.
			$event = wpFluent()->table( 'fcal_calendar_events' )
				->where( 'id', $booking->event_id )
				->first();

			// Get hosts for this booking — column is user_id in DB, accessed as host_user_id via wpFluent.
			$hosts = wpFluent()->table( 'fcal_booking_hosts' )
				->where( 'booking_id', $booking->id )
				->get();

			$host_list = array();
			foreach ( $hosts as $host ) {
				$user_id = (int) ( $host->user_id ?? 0 );
				$user = get_userdata( $user_id );
				$host_list[] = array(
					'user_id'      => $user_id,
					'display_name' => $user ? $user->display_name : null,
					'email'        => $user ? $user->user_email : null,
				);
			}

			// #140: location_details can carry the host-only online_platform_start_link.
			$location_details = fluent_abilities_booking_redact_host_secrets(
				fluent_abilities_safe_array( maybe_unserialize( $booking->location_details ?? '' ) )
			);

			// Get booking meta — fcal_booking_meta uses 'value' column (not 'meta_value').
			$meta_rows  = wpFluent()->table( 'fcal_booking_meta' )
				->where( 'booking_id', $booking->id )
				->get();

			$meta = array();
			foreach ( $meta_rows as $row ) {
				$meta[ $row->meta_key ] = fluent_abilities_safe_array( maybe_unserialize( $row->value ?? '' ) );
			}

			// #140: strip privileged host-only fields (Zoom start token/link, host
			// calendar links) from meta before returning to an ordinary read caller.
			$meta = fluent_abilities_booking_redact_host_secrets( $meta );

			return array(
				'id'                 => (int) $booking->id,
				'hash'               => $booking->hash ?? null,
				'calendar_id'        => (int) $booking->calendar_id,
				'event_id'           => (int) $booking->event_id,
				'event_title'        => $event ? ( $event->title ?? null ) : null,
				'group_id'           => $booking->group_id ? (int) $booking->group_id : null,
				'host_user_id'       => (int) ( $booking->host_user_id ?? 0 ),
				'hosts'              => $host_list,
				'person_user_id'     => $booking->person_user_id ? (int) $booking->person_user_id : null,
				'person_contact_id'  => $booking->person_contact_id ? (int) $booking->person_contact_id : null,
				'person_time_zone'   => $booking->person_time_zone ?? null,
				'first_name'         => $booking->first_name ?? null,
				'last_name'          => $booking->last_name ?? null,
				'email'              => $booking->email ?? null,
				'phone'              => $booking->phone ?? null,
				'country'            => $booking->country ?? null,
				'message'            => $booking->message ?? null,
				'start_time'         => $booking->start_time ? (string) $booking->start_time : null,
				'end_time'           => $booking->end_time ? (string) $booking->end_time : null,
				'slot_minutes'       => (int) $booking->slot_minutes,
				'status'             => $booking->status ?? '',
				'booking_type'       => $booking->booking_type ?? null,
				'payment_status'     => $booking->payment_status ?? null,
				'source'             => $booking->source ?? '',
				'ip_address'         => $booking->ip_address ?? null,
				'location_details'   => $location_details,
				'meta'               => ! empty( $meta ) ? $meta : (object) array(),
				'created_at'         => $booking->created_at ? (string) $booking->created_at : null,
				'updated_at'         => $booking->updated_at ? (string) $booking->updated_at : null,
			);
		},
	) );

	// =========================================================================
	// UPCOMING BOOKINGS
	// =========================================================================

	$reg->read( 'fluent-booking/list-upcoming', array(
		'label'       => 'List Upcoming Bookings',
		'description' => 'List upcoming bookings (start_time in the future, status = scheduled). Great for "what\'s scheduled next" queries. Filter by event.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'event_id' => array(
					'type'        => 'integer',
					'description' => 'Filter by event ID (optional)',
				),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'bookings', array(
			'id'         => array( 'type' => 'integer' ),
			'event_id'   => array( 'type' => 'integer' ),
			'status'     => array( 'type' => 'string' ),
			'start_time' => array( 'type' => array( 'string', 'null' ) ),
			'end_time'   => array( 'type' => array( 'string', 'null' ) ),
			'email'      => array( 'type' => array( 'string', 'null' ) ),
			'first_name' => array( 'type' => array( 'string', 'null' ) ),
			'last_name'  => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$now        = current_time( 'mysql', true );

			$query = wpFluent()->table( 'fcal_bookings' )
				->select( array(
					'fcal_bookings.id',
					'fcal_bookings.event_id',
					'fcal_bookings.first_name',
					'fcal_bookings.last_name',
					'fcal_bookings.email',
					'fcal_bookings.start_time',
					'fcal_bookings.end_time',
					'fcal_bookings.slot_minutes',
					'fcal_bookings.status',
					'fcal_bookings.person_time_zone',
					'fcal_bookings.created_at',
					'fcal_calendar_events.title as event_title',
				))
				->leftJoin( 'fcal_calendar_events', 'fcal_calendar_events.id', '=', 'fcal_bookings.event_id' )
				->where( 'fcal_bookings.start_time', '>', $now )
				->where( 'fcal_bookings.status', 'scheduled' );

			if ( ! empty( $input['event_id'] ) ) {
				$query->where( 'fcal_bookings.event_id', (int) $input['event_id'] );
			}

			// Count query (separate to avoid join complications).
			$count_query = wpFluent()->table( 'fcal_bookings' )
				->where( 'start_time', '>', $now )
				->where( 'status', 'scheduled' );

			if ( ! empty( $input['event_id'] ) ) {
				$count_query->where( 'event_id', (int) $input['event_id'] );
			}

			$total = (int) $count_query->count();

			$bookings = $query
				->orderBy( 'fcal_bookings.start_time', 'ASC' )
				->offset( $pagination['offset'] )
				->limit( $pagination['per_page'] )
				->get();

			$items = array();
			foreach ( $bookings as $booking ) {
				$items[] = array(
					'id'               => (int) $booking->id,
					'event_id'         => (int) $booking->event_id,
					'event_title'      => $booking->event_title ?? null,
					'first_name'       => $booking->first_name ?? null,
					'last_name'        => $booking->last_name ?? null,
					'email'            => $booking->email ?? null,
					'start_time'       => $booking->start_time ? (string) $booking->start_time : null,
					'end_time'         => $booking->end_time ? (string) $booking->end_time : null,
					'slot_minutes'     => (int) $booking->slot_minutes,
					'status'           => $booking->status ?? '',
					'person_time_zone' => $booking->person_time_zone ?? null,
					'created_at'       => $booking->created_at ? (string) $booking->created_at : null,
				);
			}

			return array(
				'bookings' => $items,
				'total'    => $total,
				'page'     => $pagination['page'],
				'per_page' => $pagination['per_page'],
			);
		},
	) );

	// =========================================================================
	// BOOKING STATS
	// =========================================================================

	$reg->read( 'fluent-booking/get-booking-stats', array(
		'label'       => 'Booking Statistics',
		'description' => 'Get booking overview: total bookings, bookings by status, bookings by event, and bookings this month.',
		'input_schema' => array(
			'type'       => 'object',
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'total'      => array( 'type' => 'integer' ),
			'by_status'  => array( 'type' => 'object' ),
			'by_event'   => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'this_month' => array( 'type' => 'integer' ),
			'upcoming'   => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$total = (int) wpFluent()->table( 'fcal_bookings' )->count();

			// Bookings by status.
			$statuses = array( 'scheduled', 'completed', 'cancelled', 'rejected', 'no-show' );
			$by_status = array();
			foreach ( $statuses as $status ) {
				$by_status[ $status ] = (int) wpFluent()->table( 'fcal_bookings' )
					->where( 'status', $status )
					->count();
			}

			// Bookings by event.
			$events = wpFluent()->table( 'fcal_calendar_events' )
				->select( array( 'id', 'title' ) )
				->orderBy( 'id', 'ASC' )
				->get();

			$by_event = array();
			foreach ( $events as $event ) {
				$count = (int) wpFluent()->table( 'fcal_bookings' )
					->where( 'event_id', $event->id )
					->count();

				$by_event[] = array(
					'event_id' => (int) $event->id,
					'title'    => $event->title ?? '',
					'count'    => $count,
				);
			}

			// Bookings this month.
			$month_start = gmdate( 'Y-m-01 00:00:00' );
			$month_end   = gmdate( 'Y-m-t 23:59:59' );

			$this_month = (int) wpFluent()->table( 'fcal_bookings' )
				->where( 'start_time', '>=', $month_start )
				->where( 'start_time', '<=', $month_end )
				->count();

			// Upcoming (future + scheduled).
			$now      = current_time( 'mysql', true );
			$upcoming = (int) wpFluent()->table( 'fcal_bookings' )
				->where( 'start_time', '>', $now )
				->where( 'status', 'scheduled' )
				->count();

			return array(
				'total'      => $total,
				'by_status'  => $by_status,
				'by_event'   => $by_event,
				'this_month' => $this_month,
				'upcoming'   => $upcoming,
			);
		},
	) );

	// =========================================================================
	// BOOKING — WRITE (refactored to use model methods for hook safety)
	// =========================================================================

	$reg->write( 'fluent-booking/cancel-booking', array(
		'label'       => 'Cancel Booking',
		'description' => 'Cancel a scheduled booking by ID. Uses the Booking model\'s cancelMeeting() method to ensure all hooks fire (CRM sync, notifications, etc.).',
		'category'    => 'fluent-booking',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'     => array( 'type' => 'integer', 'description' => 'Booking ID' ),
				'reason' => array( 'type' => 'string', 'description' => 'Cancellation reason (optional)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'     => array( 'type' => 'integer' ),
			'status' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Models\Booking' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking Booking model not found' );
			}

			$booking = \FluentBooking\App\Models\Booking::find( (int) $input['id'] );
			if ( ! $booking ) {
				return fluent_abilities_error( 'not_found', 'Booking not found' );
			}

			if ( $booking->status === 'cancelled' ) {
				return array( 'success' => true, 'id' => (int) $booking->id, 'status' => 'cancelled' );
			}

			$reason = sanitize_text_field( $input['reason'] ?? '' );
			$result = $booking->cancelMeeting( $reason, 'host', get_current_user_id() );

			if ( is_wp_error( $result ) ) {
				return fluent_abilities_error( 'cancel_failed', $result->get_error_message() );
			}

			return array( 'success' => true, 'id' => (int) $booking->id, 'status' => 'cancelled' );
		},
	) );

	$reg->write( 'fluent-booking/update-booking-status', array(
		'label'       => 'Update Booking Status',
		'description' => 'Update the status of a booking. Uses the Booking model to ensure all hooks fire. Valid statuses: scheduled, completed, cancelled, rejected, no_show. Input: pass the booking ID as `id` (an integer) — the field is `id`, NOT `booking_id` — plus `status` (one of the valid statuses) and optional `reason`.',
		'category'    => 'fluent-booking',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id', 'status' ),
			'properties' => array(
				'id'     => array( 'type' => 'integer', 'description' => 'Booking ID' ),
				'status' => array(
					'type'        => 'string',
					'description' => 'New booking status',
					'enum'        => array( 'scheduled', 'completed', 'cancelled', 'rejected', 'no_show' ),
				),
				'reason' => array( 'type' => 'string', 'description' => 'Reason for cancellation or rejection (optional)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'     => array( 'type' => 'integer' ),
			'status' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Models\Booking' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking Booking model not found' );
			}

			$booking = \FluentBooking\App\Models\Booking::find( (int) $input['id'] );
			if ( ! $booking ) {
				return fluent_abilities_error( 'not_found', 'Booking not found' );
			}

			$status = sanitize_text_field( $input['status'] );
			$reason = sanitize_text_field( $input['reason'] ?? '' );

			// Use model methods for cancel/reject to ensure hooks fire.
			if ( $status === 'cancelled' ) {
				$result = $booking->cancelMeeting( $reason, 'host', get_current_user_id() );
				if ( is_wp_error( $result ) ) {
					return fluent_abilities_error( 'status_change_failed', $result->get_error_message() );
				}
				return array( 'success' => true, 'id' => (int) $booking->id, 'status' => 'cancelled' );
			}

			if ( $status === 'rejected' ) {
				$booking->rejectMeeting( $reason, get_current_user_id() );
				return array( 'success' => true, 'id' => (int) $booking->id, 'status' => 'rejected' );
			}

			// For other statuses, use model fill + save + hooks.
			$old_booking = clone $booking;

			do_action( 'fluent_booking/before_patch_booking_schedule', $booking, array( 'status' => $status ) );

			$booking->status = $status;
			$booking->save();

			do_action( 'fluent_booking/booking_schedule_' . $status, $booking, $booking->calendar_event );
			do_action( 'fluent_booking/pre_after_booking_' . $status, $booking, $booking->calendar_event );

			$booking = \FluentBooking\App\Models\Booking::with( array( 'calendar_event', 'calendar' ) )->find( $booking->id );

			do_action( 'fluent_booking/after_booking_' . $booking->status, $booking, $booking->calendar_event, $booking );
			do_action( 'fluent_booking/after_patch_booking_schedule', $booking, $old_booking );

			return array( 'success' => true, 'id' => (int) $booking->id, 'status' => $booking->status ?? '' );
		},
	) );

	// =========================================================================
	// HOSTS (P1)
	// =========================================================================

	$reg->read( 'fluent-booking/list-hosts', array(
		'label'       => 'List All Hosts',
		'description' => 'List all users who are calendar hosts in FluentBooking.',
		'input_schema' => array(
			'type' => 'object',
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'hosts', array(
			'id'           => array( 'type' => 'integer' ),
			'name'         => array( 'type' => 'string' ),
			'label'        => array( 'type' => 'string' ),
			'calendar_id'  => array( 'type' => 'integer' ),
			'deleted_user' => array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Models\Calendar' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking Calendar model not found' );
			}

			$hosts = \FluentBooking\App\Models\Calendar::getAllHosts();

			$items = array();
			foreach ( $hosts as $host ) {
				$items[] = array(
					'id'           => (int) ( $host['id'] ?? 0 ),
					'name'         => $host['name'] ?? '',
					'label'        => $host['label'] ?? '',
					'calendar_id'  => (int) ( $host['calendar_id'] ?? 0 ),
					'deleted_user' => ! empty( $host['deleted_user'] ),
				);
			}

			return array( 'hosts' => $items, 'total' => count( $items ) );
		},
	) );

	$count = 22;
	error_log( "Abilities for Fluent: Registered {$count} Booking (main) abilities" );

}, 100 );
