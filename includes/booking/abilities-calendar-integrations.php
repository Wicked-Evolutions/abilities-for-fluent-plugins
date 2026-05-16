<?php
/**
 * FluentBooking — Pro Calendar integrations (cluster 4.10).
 *
 * External calendar integrations (Google / Outlook / Apple) via Pro
 * CalendarIntegrationService. Connections live in fcal_meta with
 * object_type='calendar_integration'.
 *
 *   - fluent-booking/list-calendar-integrations
 *   - fluent-booking/get-calendar-integration
 *   - fluent-booking/disconnect-calendar-integration  (delete)
 *   - fluent-booking/list-remote-calendars
 *   - fluent-booking/list-calendar-conflicts
 *
 * OAuth connect flow deliberately omitted (interactive credential exchange is
 * not safe to expose as a non-interactive Ability surface).
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

function fluent_booking_register_calendar_integrations_abilities() {

	$reg = new Fluent_Abilities_Registrar( 'booking' );

	// =========================================================================
	// 4.10.1 — LIST CALENDAR INTEGRATIONS
	// =========================================================================

	$reg->read( 'fluent-booking/list-calendar-integrations', array(
		'label'       => 'List External Calendar Integrations',
		'description' => 'List connected external calendar accounts (Google / Outlook / Apple) for a user. Defaults to the current user.',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'user_id' => array( 'type' => 'integer', 'description' => 'Filter by user (defaults to current user)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'integrations', array(
			'id'              => array( 'type' => 'integer' ),
			'user_id'         => array( 'type' => 'integer' ),
			'provider'        => array( 'type' => 'string' ),
			'account_email'   => array( 'type' => array( 'string', 'null' ) ),
			'status'          => array( 'type' => 'string' ),
			'connected_at'    => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$user_id = isset( $input['user_id'] ) ? (int) $input['user_id'] : get_current_user_id();

			$query = wpFluent()->table( 'fcal_meta' )
				->whereIn( 'object_type', array( 'calendar_integration', 'remote_calendar_connection' ) );
			if ( $user_id > 0 ) {
				$query->where( 'object_id', $user_id );
			}
			$rows = $query->orderBy( 'id', 'DESC' )->get();

			$integrations = array();
			foreach ( $rows as $row ) {
				$value = fluent_abilities_safe_array( maybe_unserialize( $row->value ?? '' ) );
				$value = is_array( $value ) ? $value : array();

				$integrations[] = array(
					'id'            => (int) $row->id,
					'user_id'       => (int) $row->object_id,
					'provider'      => (string) ( $value['provider'] ?? $row->key ?? '' ),
					'account_email' => isset( $value['account_email'] ) ? (string) $value['account_email'] : ( isset( $value['email'] ) ? (string) $value['email'] : null ),
					'status'        => (string) ( $value['status'] ?? 'active' ),
					'connected_at'  => $row->created_at ? (string) $row->created_at : null,
				);
			}

			return array( 'integrations' => $integrations, 'total' => count( $integrations ) );
		},
	) );

	// =========================================================================
	// 4.10.2 — GET CALENDAR INTEGRATION
	// =========================================================================

	$reg->read( 'fluent-booking/get-calendar-integration', array(
		'label'       => 'Get External Calendar Integration',
		'description' => 'Return a single external-calendar integration row by ID (token fields redacted from output). Input: pass the integration row ID as `integration_id` (an integer); there is no `id`/`provider` input — the row is looked up by `integration_id` only.',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'integration_id' ),
			'properties' => array(
				'integration_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'            => array( 'type' => 'integer' ),
			'user_id'       => array( 'type' => 'integer' ),
			'provider'      => array( 'type' => 'string' ),
			'account_email' => array( 'type' => array( 'string', 'null' ) ),
			'status'        => array( 'type' => 'string' ),
			'connected_at'  => array( 'type' => array( 'string', 'null' ) ),
			'metadata'      => array( 'type' => array( 'object', 'array', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$id  = (int) $input['integration_id'];
			$row = wpFluent()->table( 'fcal_meta' )
				->where( 'id', $id )
				->first();
			if ( ! $row ) {
				return fluent_abilities_error( 'not_found', 'Integration not found' );
			}

			$value = fluent_abilities_safe_array( maybe_unserialize( $row->value ?? '' ) );
			$value = is_array( $value ) ? $value : array();

			// Redact token / refresh / secret fields from public output.
			foreach ( array( 'access_token', 'refresh_token', 'token', 'secret', 'client_secret' ) as $sensitive ) {
				if ( isset( $value[ $sensitive ] ) ) {
					$value[ $sensitive ] = '***redacted***';
				}
			}

			return array(
				'id'            => (int) $row->id,
				'user_id'       => (int) $row->object_id,
				'provider'      => (string) ( $value['provider'] ?? $row->key ?? '' ),
				'account_email' => isset( $value['account_email'] ) ? (string) $value['account_email'] : ( isset( $value['email'] ) ? (string) $value['email'] : null ),
				'status'        => (string) ( $value['status'] ?? 'active' ),
				'connected_at'  => $row->created_at ? (string) $row->created_at : null,
				'metadata'      => $value,
			);
		},
	) );

	// =========================================================================
	// 4.10.3 — DISCONNECT CALENDAR INTEGRATION
	// =========================================================================

	$reg->delete( 'fluent-booking/disconnect-calendar-integration', array(
		'label'       => 'Disconnect External Calendar Integration',
		'description' => 'Remove the stored credentials for a calendar integration. The external provider is NOT notified — operators must revoke at the provider side separately.',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'integration_id' ),
			'properties' => array(
				'integration_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'integration_id' => array( 'type' => 'integer' ),
			'deleted'        => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$id = (int) $input['integration_id'];
			if ( $id <= 0 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'integration_id is required' );
			}

			$row = wpFluent()->table( 'fcal_meta' )
				->where( 'id', $id )
				->whereIn( 'object_type', array( 'calendar_integration', 'remote_calendar_connection' ) )
				->first();
			if ( ! $row ) {
				return fluent_abilities_error( 'not_found', 'Integration not found' );
			}

			$deleted = wpFluent()->table( 'fcal_meta' )
				->where( 'id', $id )
				->delete();

			return array(
				'success'        => true,
				'integration_id' => $id,
				'deleted'        => (int) $deleted,
			);
		},
	) );

	// =========================================================================
	// 4.10.4 — LIST REMOTE CALENDARS
	// =========================================================================

	$reg->read( 'fluent-booking/list-remote-calendars', array(
		'label'       => 'List Remote Calendars',
		'description' => 'List provider-side calendars (the calendars on the connected Google/Outlook/Apple account). Returns stored snapshot when available; live refresh requires the provider service. Input: identify the connection by the stored integration row ID as `integration_id` (an integer) — NOT a `provider` string; there is no `provider` input.',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'integration_id' ),
			'properties' => array(
				'integration_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'calendars', array(
			'provider_calendar_id' => array( 'type' => 'string' ),
			'name'                 => array( 'type' => 'string' ),
			'primary'              => array( 'type' => 'boolean' ),
			'color'                => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$row = wpFluent()->table( 'fcal_meta' )
				->where( 'id', (int) $input['integration_id'] )
				->whereIn( 'object_type', array( 'calendar_integration', 'remote_calendar_connection' ) )
				->first();
			if ( ! $row ) {
				return fluent_abilities_error( 'not_found', 'Integration not found' );
			}

			$value = fluent_abilities_safe_array( maybe_unserialize( $row->value ?? '' ) );
			$value = is_array( $value ) ? $value : array();
			$cals  = isset( $value['calendars'] ) ? (array) $value['calendars'] : array();

			$out = array();
			foreach ( $cals as $cal ) {
				$out[] = array(
					'provider_calendar_id' => (string) ( $cal['id'] ?? $cal['provider_calendar_id'] ?? '' ),
					'name'                 => (string) ( $cal['name'] ?? $cal['summary'] ?? '' ),
					'primary'              => ! empty( $cal['primary'] ),
					'color'                => isset( $cal['color'] ) ? (string) $cal['color'] : null,
				);
			}

			return array( 'calendars' => $out, 'total' => count( $out ) );
		},
	) );

	// =========================================================================
	// 4.10.5 — LIST CALENDAR CONFLICTS
	// =========================================================================

	$reg->read( 'fluent-booking/list-calendar-conflicts', array(
		'label'       => 'List External-Calendar Conflicts for a Window',
		'description' => 'Return events on the user\'s connected remote calendars that overlap a given UTC time window. Input: pass `user_id` (integer), `start_time` and `end_time` (Y-m-d H:i:s UTC strings) — the window is keyed by `user_id` + `start_time`/`end_time`, NOT by `calendar_id` or `start_date`/`end_date`.',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'user_id', 'start_time', 'end_time' ),
			'properties' => array(
				'user_id'    => array( 'type' => 'integer' ),
				'start_time' => array( 'type' => 'string', 'description' => 'Window start, Y-m-d H:i:s UTC' ),
				'end_time'   => array( 'type' => 'string', 'description' => 'Window end, Y-m-d H:i:s UTC' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'conflicts', array(
			'provider'   => array( 'type' => 'string' ),
			'start_time' => array( 'type' => 'string' ),
			'end_time'   => array( 'type' => 'string' ),
			'title'      => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$user_id = (int) $input['user_id'];
			$start   = sanitize_text_field( $input['start_time'] );
			$end     = sanitize_text_field( $input['end_time'] );

			// Read cached events from any per-integration cache rows.
			$rows = wpFluent()->table( 'fcal_meta' )
				->where( 'object_id', $user_id )
				->whereIn( 'object_type', array( 'calendar_integration', 'remote_calendar_connection', 'remote_calendar_cache' ) )
				->get();

			$conflicts = array();
			foreach ( $rows as $row ) {
				$value = fluent_abilities_safe_array( maybe_unserialize( $row->value ?? '' ) );
				$value = is_array( $value ) ? $value : array();
				$events = isset( $value['events'] ) ? (array) $value['events'] : array();
				$provider = (string) ( $value['provider'] ?? $row->key ?? '' );

				foreach ( $events as $event ) {
					$ev_start = (string) ( $event['start_time'] ?? $event['start'] ?? '' );
					$ev_end   = (string) ( $event['end_time'] ?? $event['end'] ?? '' );
					if ( $ev_start === '' || $ev_end === '' ) {
						continue;
					}
					if ( $ev_start < $end && $ev_end > $start ) {
						$conflicts[] = array(
							'provider'   => $provider,
							'start_time' => $ev_start,
							'end_time'   => $ev_end,
							'title'      => isset( $event['title'] ) ? (string) $event['title'] : null,
						);
					}
				}
			}

			return array( 'conflicts' => $conflicts, 'total' => count( $conflicts ) );
		},
	) );

}
add_action( 'wp_abilities_api_init', 'fluent_booking_register_calendar_integrations_abilities' );
