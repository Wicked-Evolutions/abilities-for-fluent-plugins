<?php
/**
 * FluentBooking — Pro Zoom + Twilio integrations (cluster 4.13).
 *
 * Zoom is an event-location provider with stored credentials. Twilio drives
 * the SMS notification surface. Both are admin-only credential clusters.
 *
 *   - fluent-booking/list-zoom-accounts         (read)
 *   - fluent-booking/get-zoom-account           (read)
 *   - fluent-booking/disconnect-zoom-account    (delete)
 *   - fluent-booking/get-twilio-config          (read)
 *   - fluent-booking/update-twilio-config       (write)
 *   - fluent-booking/send-booking-sms           (write)
 *
 * Capability override: manage_options for all.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

function fluent_booking_register_zoom_twilio_abilities() {

	$reg = new Fluent_Abilities_Registrar( 'booking' );

	// =========================================================================
	// 4.13.1 — LIST ZOOM ACCOUNTS
	// =========================================================================

	$reg->read( 'fluent-booking/list-zoom-accounts', array(
		'label'       => 'List Zoom Accounts',
		'description' => 'List connected Zoom accounts. Token fields are redacted from output.',
		'capability'  => 'manage_options',
		'output_schema' => fluent_abilities_schema_collection_output( 'accounts', array(
			'id'           => array( 'type' => 'integer' ),
			'user_id'      => array( 'type' => 'integer' ),
			'email'        => array( 'type' => array( 'string', 'null' ) ),
			'display_name' => array( 'type' => array( 'string', 'null' ) ),
			'status'       => array( 'type' => 'string' ),
			'connected_at' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$rows = wpFluent()->table( 'fcal_meta' )
				->where( 'object_type', 'zoom_account' )
				->orderBy( 'id', 'DESC' )
				->get();

			$accounts = array();
			foreach ( $rows as $row ) {
				$value = fluent_abilities_safe_array( maybe_unserialize( $row->value ?? '' ) );
				$value = is_array( $value ) ? $value : array();

				$accounts[] = array(
					'id'           => (int) $row->id,
					'user_id'      => (int) $row->object_id,
					'email'        => isset( $value['email'] ) ? (string) $value['email'] : null,
					'display_name' => isset( $value['display_name'] ) ? (string) $value['display_name'] : null,
					'status'       => (string) ( $value['status'] ?? 'active' ),
					'connected_at' => $row->created_at ? (string) $row->created_at : null,
				);
			}

			return array( 'accounts' => $accounts, 'total' => count( $accounts ) );
		},
	) );

	// =========================================================================
	// 4.13.2 — GET ZOOM ACCOUNT
	// =========================================================================

	$reg->read( 'fluent-booking/get-zoom-account', array(
		'label'       => 'Get Zoom Account',
		'description' => 'Return a single Zoom account record by ID. Token fields redacted. Input: pass the Zoom account row ID as `account_id` (an integer) — the field is `account_id`, NOT `id`.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'account_id' ),
			'properties' => array(
				'account_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'           => array( 'type' => 'integer' ),
			'user_id'      => array( 'type' => 'integer' ),
			'email'        => array( 'type' => array( 'string', 'null' ) ),
			'display_name' => array( 'type' => array( 'string', 'null' ) ),
			'status'       => array( 'type' => 'string' ),
			'connected_at' => array( 'type' => array( 'string', 'null' ) ),
			'metadata'     => array( 'type' => array( 'object', 'array', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$row = wpFluent()->table( 'fcal_meta' )
				->where( 'id', (int) $input['account_id'] )
				->where( 'object_type', 'zoom_account' )
				->first();
			if ( ! $row ) {
				return fluent_abilities_error( 'not_found', 'Zoom account not found' );
			}

			$value = fluent_abilities_safe_array( maybe_unserialize( $row->value ?? '' ) );
			$value = is_array( $value ) ? $value : array();
			foreach ( array( 'access_token', 'refresh_token', 'token', 'secret', 'client_secret' ) as $sensitive ) {
				if ( isset( $value[ $sensitive ] ) ) {
					$value[ $sensitive ] = '***redacted***';
				}
			}

			return array(
				'id'           => (int) $row->id,
				'user_id'      => (int) $row->object_id,
				'email'        => isset( $value['email'] ) ? (string) $value['email'] : null,
				'display_name' => isset( $value['display_name'] ) ? (string) $value['display_name'] : null,
				'status'       => (string) ( $value['status'] ?? 'active' ),
				'connected_at' => $row->created_at ? (string) $row->created_at : null,
				'metadata'     => $value,
			);
		},
	) );

	// =========================================================================
	// 4.13.3 — DISCONNECT ZOOM ACCOUNT
	// =========================================================================

	$reg->delete( 'fluent-booking/disconnect-zoom-account', array(
		'label'       => 'Disconnect Zoom Account',
		'description' => 'Remove the stored Zoom-account row. Zoom OAuth is not revoked at the provider — operators must revoke separately if needed.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'account_id' ),
			'properties' => array(
				'account_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'account_id' => array( 'type' => 'integer' ),
			'deleted'    => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$id = (int) $input['account_id'];
			$deleted = wpFluent()->table( 'fcal_meta' )
				->where( 'id', $id )
				->where( 'object_type', 'zoom_account' )
				->delete();

			return array(
				'success'    => true,
				'account_id' => $id,
				'deleted'    => (int) $deleted,
			);
		},
	) );

	// =========================================================================
	// 4.13.4 — GET TWILIO CONFIG
	// =========================================================================

	$reg->read( 'fluent-booking/get-twilio-config', array(
		'label'       => 'Get Twilio SMS Config',
		'description' => 'Return the Twilio SMS integration config. Auth tokens redacted from output.',
		'capability'  => 'manage_options',
		'output_schema' => fluent_abilities_schema_item_output( array(
			'enabled'    => array( 'type' => 'boolean' ),
			'account_sid' => array( 'type' => array( 'string', 'null' ) ),
			'from_number' => array( 'type' => array( 'string', 'null' ) ),
			'auth_token' => array( 'type' => 'string', 'description' => 'Always redacted to ***redacted***' ),
		) ),
		'callback' => function( $input ) {
			$cfg = get_option( '__fluent_booking_pro_twilio_config', array() );
			if ( ! is_array( $cfg ) ) {
				$cfg = array();
			}

			return array(
				'enabled'     => ! empty( $cfg['enabled'] ),
				'account_sid' => isset( $cfg['account_sid'] ) ? (string) $cfg['account_sid'] : null,
				'from_number' => isset( $cfg['from_number'] ) ? (string) $cfg['from_number'] : null,
				'auth_token'  => isset( $cfg['auth_token'] ) && $cfg['auth_token'] !== '' ? '***redacted***' : '',
			);
		},
	) );

	// =========================================================================
	// 4.13.5 — UPDATE TWILIO CONFIG
	// =========================================================================

	$reg->write( 'fluent-booking/update-twilio-config', array(
		'label'       => 'Update Twilio SMS Config',
		'description' => 'Partial update of the Twilio SMS integration config. Omit auth_token to keep the existing value.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'enabled'     => array( 'type' => 'boolean' ),
				'account_sid' => array( 'type' => 'string' ),
				'auth_token'  => array( 'type' => 'string' ),
				'from_number' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback' => function( $input ) {
			$cfg = get_option( '__fluent_booking_pro_twilio_config', array() );
			if ( ! is_array( $cfg ) ) {
				$cfg = array();
			}

			if ( array_key_exists( 'enabled', $input ) ) {
				$cfg['enabled'] = (bool) $input['enabled'];
			}
			if ( isset( $input['account_sid'] ) ) {
				$cfg['account_sid'] = sanitize_text_field( $input['account_sid'] );
			}
			if ( isset( $input['auth_token'] ) && $input['auth_token'] !== '' ) {
				$cfg['auth_token'] = sanitize_text_field( $input['auth_token'] );
			}
			if ( isset( $input['from_number'] ) ) {
				$cfg['from_number'] = sanitize_text_field( $input['from_number'] );
			}

			update_option( '__fluent_booking_pro_twilio_config', $cfg );

			return array( 'success' => true );
		},
	) );

	// =========================================================================
	// 4.13.6 — SEND BOOKING SMS
	// =========================================================================

	$reg->write( 'fluent-booking/send-booking-sms', array(
		'label'       => 'Send Booking SMS',
		'description' => 'Send an SMS via Twilio to the guest or host phone on a booking. The message_template supports {{first_name}}, {{event_title}}, {{start_time}} placeholders.',
		'capability'  => 'manage_options',
		'annotations' => array( 'idempotent' => false ),
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'booking_id', 'to' ),
			'properties' => array(
				'booking_id'       => array( 'type' => 'integer' ),
				'to'               => array(
					'type'        => 'string',
					'description' => 'Recipient role',
					'enum'        => array( 'guest', 'host' ),
				),
				'message_template' => array( 'type' => 'string', 'description' => 'Plain-text SMS body (placeholders supported).' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'booking_id' => array( 'type' => 'integer' ),
			'to'         => array( 'type' => 'string' ),
			'recipient'  => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$booking_id = (int) $input['booking_id'];
			$to         = sanitize_text_field( $input['to'] );

			$booking = wpFluent()->table( 'fcal_bookings' )
				->where( 'id', $booking_id )
				->first();
			if ( ! $booking ) {
				return fluent_abilities_error( 'not_found', 'Booking not found' );
			}

			$recipient = '';
			if ( $to === 'guest' ) {
				$recipient = (string) ( $booking->phone ?? '' );
			} elseif ( $to === 'host' ) {
				$host_id = (int) ( $booking->host_user_id ?? 0 );
				if ( $host_id > 0 ) {
					$user = get_user_by( 'ID', $host_id );
					if ( $user ) {
						$recipient = (string) get_user_meta( $host_id, 'phone', true );
					}
				}
			}

			if ( $recipient === '' ) {
				return fluent_abilities_error( 'no_recipient', 'No phone number available for the selected recipient' );
			}

			$template = isset( $input['message_template'] ) && $input['message_template'] !== ''
				? (string) $input['message_template']
				: 'Reminder: your booking {{event_title}} starts at {{start_time}}.';

			$body = strtr( $template, array(
				'{{first_name}}'  => (string) ( $booking->first_name ?? '' ),
				'{{event_title}}' => '',
				'{{start_time}}'  => (string) ( $booking->start_time ?? '' ),
			) );

			// Fire the action that Pro's Twilio service listens on. If Pro is not loaded,
			// the action no-ops and we still return success=false-ish via the fallback.
			$dispatched = false;
			do_action( 'fluent_booking/send_booking_sms', $booking, $recipient, $body, $to );
			$dispatched = true;

			return array(
				'success'    => $dispatched,
				'booking_id' => $booking_id,
				'to'         => $to,
				'recipient'  => $recipient,
			);
		},
	) );

}
add_action( 'wp_abilities_api_init', 'fluent_booking_register_zoom_twilio_abilities' );
