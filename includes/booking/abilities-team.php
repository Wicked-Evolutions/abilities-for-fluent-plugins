<?php
/**
 * FluentBooking — Pro Team / event-host roster management (cluster 4.15).
 *
 * Distinct from cluster 4.2 (multi-host *booking* writes). This is event-level
 * team **roster** management: team_members[] stored in CalendarSlot.settings
 * (per PermissionManager.php:74), plus team-calendar (type='team') management.
 *
 *   - fluent-booking/list-team-events             (read)
 *   - fluent-booking/list-event-team-members      (read)
 *   - fluent-booking/add-event-team-member        (write)
 *   - fluent-booking/remove-event-team-member    (delete)
 *   - fluent-booking/list-team-calendars          (read)
 *   - fluent-booking/update-team-calendar-members (write)
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

function fluent_booking_register_team_abilities() {

	$reg = new Fluent_Abilities_Registrar( 'booking' );

	// =========================================================================
	// 4.15.1 — LIST TEAM EVENTS
	// =========================================================================

	$reg->read( 'fluent-booking/list-team-events', array(
		'label'       => 'List Team-Type Events',
		'description' => 'List events with event_type in (round_robin, collective) — the team-event types that use team_members rosters. Optionally filter by calendar_id.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'calendar_id' => array( 'type' => 'integer', 'description' => 'Optional calendar filter' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'events', array(
			'id'              => array( 'type' => 'integer' ),
			'calendar_id'     => array( 'type' => 'integer' ),
			'title'           => array( 'type' => 'string' ),
			'event_type'      => array( 'type' => 'string' ),
			'team_member_count' => array( 'type' => 'integer' ),
			'created_at'      => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$query = wpFluent()->table( 'fcal_calendar_events' )
				->whereIn( 'event_type', array( 'round_robin', 'collective' ) );

			if ( ! empty( $input['calendar_id'] ) ) {
				$query->where( 'calendar_id', (int) $input['calendar_id'] );
			}

			$rows = $query->orderBy( 'id', 'DESC' )->get();

			$events = array();
			foreach ( $rows as $row ) {
				$settings = maybe_unserialize( $row->settings ?? '' );
				$team     = is_array( $settings ) && isset( $settings['team_members'] ) ? (array) $settings['team_members'] : array();

				$events[] = array(
					'id'                => (int) $row->id,
					'calendar_id'       => (int) $row->calendar_id,
					'title'             => (string) ( $row->title ?? '' ),
					'event_type'        => (string) ( $row->event_type ?? '' ),
					'team_member_count' => count( $team ),
					'created_at'        => $row->created_at ? (string) $row->created_at : null,
				);
			}

			return array( 'events' => $events, 'total' => count( $events ) );
		},
	) );

	// =========================================================================
	// 4.15.2 — LIST EVENT TEAM MEMBERS
	// =========================================================================

	$reg->read( 'fluent-booking/list-event-team-members', array(
		'label'       => 'List Event Team Members',
		'description' => 'List the team_members[] roster for a team-type event (round_robin / collective). Joins to wp_users for display_name + email.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'event_id' ),
			'properties' => array(
				'event_id' => array( 'type' => 'integer', 'description' => 'CalendarSlot (event) ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'members', array(
			'user_id'      => array( 'type' => 'integer' ),
			'display_name' => array( 'type' => array( 'string', 'null' ) ),
			'email'        => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Models\CalendarSlot' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking CalendarSlot model not found' );
			}

			$event = \FluentBooking\App\Models\CalendarSlot::find( (int) $input['event_id'] );
			if ( ! $event ) {
				return fluent_abilities_error( 'not_found', 'Event (calendar slot) not found' );
			}

			$settings = maybe_unserialize( $event->settings ?? '' );
			$ids      = is_array( $settings ) && isset( $settings['team_members'] ) ? array_values( (array) $settings['team_members'] ) : array();

			$members = array();
			foreach ( $ids as $entry ) {
				$user_id = is_array( $entry ) ? (int) ( $entry['user_id'] ?? $entry['id'] ?? 0 ) : (int) $entry;
				if ( $user_id <= 0 ) {
					continue;
				}
				$user = get_user_by( 'ID', $user_id );
				$members[] = array(
					'user_id'      => $user_id,
					'display_name' => $user ? $user->display_name : null,
					'email'        => $user ? $user->user_email : null,
				);
			}

			return array( 'members' => $members, 'total' => count( $members ) );
		},
	) );

	// =========================================================================
	// 4.15.3 — ADD EVENT TEAM MEMBER
	// =========================================================================

	$reg->write( 'fluent-booking/add-event-team-member', array(
		'label'       => 'Add Event Team Member',
		'description' => 'Append a user to the team_members[] roster on an event. Idempotent: existing membership returns success without duplication.',
		'level'       => 'admin',
		'annotations' => array( 'idempotent' => true ),
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'event_id', 'user_id' ),
			'properties' => array(
				'event_id' => array( 'type' => 'integer' ),
				'user_id'  => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'event_id'      => array( 'type' => 'integer' ),
			'user_id'       => array( 'type' => 'integer' ),
			'member_count'  => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Models\CalendarSlot' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking CalendarSlot model not found' );
			}

			$event_id = (int) $input['event_id'];
			$user_id  = (int) $input['user_id'];

			$event = \FluentBooking\App\Models\CalendarSlot::find( $event_id );
			if ( ! $event ) {
				return fluent_abilities_error( 'not_found', 'Event (calendar slot) not found' );
			}
			if ( ! get_user_by( 'ID', $user_id ) ) {
				return fluent_abilities_error( 'user_not_found', 'User not found' );
			}

			$settings = maybe_unserialize( $event->settings ?? '' );
			$settings = is_array( $settings ) ? $settings : array();
			$members  = isset( $settings['team_members'] ) ? array_values( (array) $settings['team_members'] ) : array();

			$existing_ids = array_map( function( $entry ) {
				return is_array( $entry ) ? (int) ( $entry['user_id'] ?? $entry['id'] ?? 0 ) : (int) $entry;
			}, $members );

			if ( ! in_array( $user_id, $existing_ids, true ) ) {
				$members[] = $user_id;
				$settings['team_members'] = $members;
				// V3: plain array — vendor CalendarSlot::setSettingsAttribute()
				// maybe_serialize()s on set / getSettingsAttribute()
				// maybe_unserialize()s on read. Pre-serializing double-serialized
				// via the mutator (same class as the location_settings #106
				// crash: vendor count() on a string → PHP 8.3 fatal / 500).
				$event->settings = $settings;
				$event->save();
			}

			return array(
				'success'      => true,
				'event_id'     => $event_id,
				'user_id'      => $user_id,
				'member_count' => count( $members ),
			);
		},
	) );

	// =========================================================================
	// 4.15.4 — REMOVE EVENT TEAM MEMBER
	// =========================================================================

	$reg->delete( 'fluent-booking/remove-event-team-member', array(
		'label'       => 'Remove Event Team Member',
		'description' => 'Remove a user from the team_members[] roster on an event.',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'event_id', 'user_id' ),
			'properties' => array(
				'event_id' => array( 'type' => 'integer' ),
				'user_id'  => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'event_id'     => array( 'type' => 'integer' ),
			'user_id'      => array( 'type' => 'integer' ),
			'member_count' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Models\CalendarSlot' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking CalendarSlot model not found' );
			}

			$event_id = (int) $input['event_id'];
			$user_id  = (int) $input['user_id'];

			$event = \FluentBooking\App\Models\CalendarSlot::find( $event_id );
			if ( ! $event ) {
				return fluent_abilities_error( 'not_found', 'Event (calendar slot) not found' );
			}

			$settings = maybe_unserialize( $event->settings ?? '' );
			$settings = is_array( $settings ) ? $settings : array();
			$members  = isset( $settings['team_members'] ) ? array_values( (array) $settings['team_members'] ) : array();

			$filtered = array();
			foreach ( $members as $entry ) {
				$mid = is_array( $entry ) ? (int) ( $entry['user_id'] ?? $entry['id'] ?? 0 ) : (int) $entry;
				if ( $mid !== $user_id ) {
					$filtered[] = $entry;
				}
			}

			$settings['team_members'] = $filtered;
			// V3: plain array — vendor CalendarSlot::setSettingsAttribute()
			// serializes on set (see #106 crash class).
			$event->settings = $settings;
			$event->save();

			return array(
				'success'      => true,
				'event_id'     => $event_id,
				'user_id'      => $user_id,
				'member_count' => count( $filtered ),
			);
		},
	) );

	// =========================================================================
	// 4.15.5 — LIST TEAM CALENDARS
	// =========================================================================

	$reg->read( 'fluent-booking/list-team-calendars', array(
		'label'       => 'List Team Calendars',
		'description' => 'List calendars with type=team (the team-calendar surface from PermissionManager). Returns aggregated host user IDs per calendar.',
		'output_schema' => fluent_abilities_schema_collection_output( 'calendars', array(
			'calendar_id' => array( 'type' => 'integer' ),
			'title'       => array( 'type' => 'string' ),
			'slug'        => array( 'type' => 'string' ),
			'host_user_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			'created_at'  => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$rows = wpFluent()->table( 'fcal_calendars' )
				->where( 'type', 'team' )
				->orderBy( 'id', 'DESC' )
				->get();

			$calendars = array();
			foreach ( $rows as $row ) {
				$settings = maybe_unserialize( $row->settings ?? '' );
				$hosts    = is_array( $settings ) && isset( $settings['team_hosts'] ) ? (array) $settings['team_hosts'] : array();
				$host_ids = array_map( function( $h ) {
					return is_array( $h ) ? (int) ( $h['user_id'] ?? $h['id'] ?? 0 ) : (int) $h;
				}, $hosts );
				$host_ids = array_values( array_filter( $host_ids, function( $id ) { return $id > 0; } ) );

				$calendars[] = array(
					'calendar_id'   => (int) $row->id,
					'title'         => (string) ( $row->title ?? '' ),
					'slug'          => (string) ( $row->slug ?? '' ),
					'host_user_ids' => $host_ids,
					'created_at'    => $row->created_at ? (string) $row->created_at : null,
				);
			}

			return array( 'calendars' => $calendars, 'total' => count( $calendars ) );
		},
	) );

	// =========================================================================
	// 4.15.6 — UPDATE TEAM CALENDAR MEMBERS
	// =========================================================================

	$reg->write( 'fluent-booking/update-team-calendar-members', array(
		'label'       => 'Update Team Calendar Members',
		'description' => 'Replace the team_hosts[] list on a team-type calendar (matched calendar_id, type=team).',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'calendar_id', 'user_ids' ),
			'properties' => array(
				'calendar_id' => array( 'type' => 'integer' ),
				'user_ids'    => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'calendar_id' => array( 'type' => 'integer' ),
			'host_count'  => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Models\Calendar' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking Calendar model not found' );
			}

			$calendar_id = (int) $input['calendar_id'];
			$calendar    = \FluentBooking\App\Models\Calendar::find( $calendar_id );
			if ( ! $calendar ) {
				return fluent_abilities_error( 'not_found', 'Calendar not found' );
			}
			if ( ( $calendar->type ?? '' ) !== 'team' ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Calendar is not a team-type calendar' );
			}

			$user_ids = array_map( 'intval', (array) $input['user_ids'] );
			$user_ids = array_values( array_unique( array_filter( $user_ids, function( $id ) { return $id > 0; } ) ) );

			$settings = maybe_unserialize( $calendar->settings ?? '' );
			$settings = is_array( $settings ) ? $settings : array();
			$settings['team_hosts'] = $user_ids;
			// V3: plain array — vendor Calendar::setSettingsAttribute()
			// maybe_serialize()s on set / getSettingsAttribute() on read
			// (#106 crash class). Pre-serializing double-serialized.
			$calendar->settings = $settings;
			$calendar->save();

			return array(
				'success'     => true,
				'calendar_id' => $calendar_id,
				'host_count'  => count( $user_ids ),
			);
		},
	) );

}
add_action( 'wp_abilities_api_init', 'fluent_booking_register_team_abilities' );
