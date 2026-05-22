<?php
/**
 * FluentBooking — Booking CRUD, Activities, Meta, Notes, Group Attendees, Confirmation Email
 *
 * 8 abilities in the 'fluent-booking' category.
 * Registered via Fluent_Abilities_Registrar.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'booking' );

	// =========================================================================
	// CREATE BOOKING (P0) — uses BookingService for hook safety
	// =========================================================================

	$reg->write( 'fluent-booking/create-booking', array(
		'label'       => 'Create Booking',
		'description' => 'Create a new booking for an event. Uses BookingService::createBooking() to ensure slot validation, conflict checking, CRM sync, and all hooks fire correctly. Requires email, start_time, person_time_zone, and event_id.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'event_id', 'email', 'start_time', 'person_time_zone' ),
			'properties' => array(
				'event_id'         => array( 'type' => 'integer', 'description' => 'Event (calendar slot) ID' ),
				'email'            => array( 'type' => 'string', 'description' => 'Attendee email address' ),
				'first_name'       => array( 'type' => 'string', 'description' => 'Attendee first name' ),
				'last_name'        => array( 'type' => 'string', 'description' => 'Attendee last name' ),
				'start_time'       => array( 'type' => 'string', 'description' => 'Booking start time in Y-m-d H:i:s UTC format' ),
				'end_time'         => array( 'type' => 'string', 'description' => 'Booking end time in Y-m-d H:i:s UTC format (optional — auto-calculated from event duration)' ),
				'person_time_zone' => array( 'type' => 'string', 'description' => 'Attendee timezone (e.g. America/New_York)' ),
				'phone'            => array( 'type' => 'string', 'description' => 'Attendee phone number (optional)' ),
				'message'          => array( 'type' => 'string', 'description' => 'Additional note from attendee (optional)' ),
				'status'           => array(
					'type'        => 'string',
					'description' => 'Booking status (default: scheduled)',
					'enum'        => array( 'scheduled', 'pending' ),
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'         => array( 'type' => 'integer' ),
			'event_id'   => array( 'type' => 'integer' ),
			'email'      => array( 'type' => 'string' ),
			'status'     => array( 'type' => 'string' ),
			'start_time' => array( 'type' => 'string' ),
			'end_time'   => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Services\BookingService' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking BookingService class not found' );
			}
			if ( ! class_exists( '\FluentBooking\App\Models\CalendarSlot' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking CalendarSlot model not found' );
			}

			$event_id = (int) $input['event_id'];
			$calendarSlot = \FluentBooking\App\Models\CalendarSlot::find( $event_id );

			if ( ! $calendarSlot ) {
				return fluent_abilities_error( 'not_found', 'Event (calendar slot) not found' );
			}

			$data = array(
				'event_id'         => $event_id,
				'email'            => sanitize_email( $input['email'] ),
				'start_time'       => sanitize_text_field( $input['start_time'] ),
				'person_time_zone' => sanitize_text_field( $input['person_time_zone'] ),
				'first_name'       => sanitize_text_field( $input['first_name'] ?? '' ),
				'last_name'        => sanitize_text_field( $input['last_name'] ?? '' ),
				'phone'            => sanitize_text_field( $input['phone'] ?? '' ),
				'message'          => sanitize_textarea_field( $input['message'] ?? '' ),
				'status'           => sanitize_text_field( $input['status'] ?? 'scheduled' ),
				'source'           => 'api',
			);

			if ( ! empty( $input['end_time'] ) ) {
				$data['end_time'] = sanitize_text_field( $input['end_time'] );
			}

			try {
				$booking = \FluentBooking\App\Services\BookingService::createBooking( $data, $calendarSlot );
			} catch ( \Exception $e ) {
				return fluent_abilities_error( 'booking_failed', $e->getMessage() );
			}

			if ( is_wp_error( $booking ) ) {
				return fluent_abilities_error( 'booking_failed', $booking->get_error_message() );
			}

			return array(
				'success'    => true,
				'id'         => (int) $booking->id,
				'event_id'   => (int) $booking->event_id,
				'email'      => $booking->email ?? '',
				'status'     => $booking->status ?? '',
				'start_time' => (string) ( $booking->start_time ?? '' ),
				'end_time'   => (string) ( $booking->end_time ?? '' ),
			);
		},
	) );

	// =========================================================================
	// UPDATE BOOKING (P0) — uses Booking model for hook safety
	// =========================================================================

	$reg->write( 'fluent-booking/update-booking', array(
		'label'       => 'Update Booking',
		'description' => 'Update one or more fields on a booking. Supports: first_name, last_name, email, phone, internal_note, status, payment_status. Uses the Booking model to ensure hooks fire.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'             => array( 'type' => 'integer', 'description' => 'Booking ID' ),
				'first_name'     => array( 'type' => 'string', 'description' => 'Attendee first name' ),
				'last_name'      => array( 'type' => 'string', 'description' => 'Attendee last name' ),
				'email'          => array( 'type' => 'string', 'description' => 'Attendee email' ),
				'phone'          => array( 'type' => 'string', 'description' => 'Attendee phone' ),
				'internal_note'  => array( 'type' => 'string', 'description' => 'Internal note (visible to hosts only)' ),
				'status'         => array(
					'type'        => 'string',
					'description' => 'Booking status',
					'enum'        => array( 'scheduled', 'completed', 'cancelled', 'rejected', 'no_show' ),
				),
				'payment_status' => array(
					'type'        => 'string',
					'description' => 'Payment status',
					'enum'        => array( 'pending', 'paid' ),
				),
				'cancel_reason'  => array( 'type' => 'string', 'description' => 'Reason for cancellation (used when status=cancelled)' ),
				'reject_reason'  => array( 'type' => 'string', 'description' => 'Reason for rejection (used when status=rejected)' ),
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

			$oldBooking = clone $booking;

			$valid_columns = array( 'first_name', 'last_name', 'email', 'phone', 'internal_note', 'status', 'payment_status' );
			$updated       = array();

			foreach ( $valid_columns as $col ) {
				if ( ! isset( $input[ $col ] ) ) {
					continue;
				}

				$value = $input[ $col ];

				if ( $col === 'email' ) {
					$value = sanitize_email( $value );
					if ( ! is_email( $value ) ) {
						return fluent_abilities_error( 'invalid_email', 'Invalid email address' );
					}
				} else {
					$value = sanitize_text_field( $value );
				}

				// Handle status changes through model methods for hook safety.
				if ( $col === 'status' ) {
					if ( $value === 'cancelled' ) {
						$reason = sanitize_text_field( $input['cancel_reason'] ?? '' );
						$booking->cancelMeeting( $reason, 'host', get_current_user_id() );
						return array( 'success' => true, 'id' => (int) $booking->id, 'status' => 'cancelled' );
					}
					if ( $value === 'rejected' ) {
						$reason = sanitize_text_field( $input['reject_reason'] ?? '' );
						$booking->rejectMeeting( $reason, get_current_user_id() );
						return array( 'success' => true, 'id' => (int) $booking->id, 'status' => 'rejected' );
					}
				}

				$updated[ $col ] = $value;
			}

			if ( empty( $updated ) ) {
				return fluent_abilities_error( 'no_changes', 'No valid fields provided for update' );
			}

			do_action( 'fluent_booking/before_patch_booking_schedule', $booking, $updated );

			$booking->fill( $updated );
			$booking->save();

			// Fire status hooks if status changed.
			if ( isset( $updated['status'] ) ) {
				do_action( 'fluent_booking/booking_schedule_' . $updated['status'], $booking, $booking->calendar_event );
				do_action( 'fluent_booking/pre_after_booking_' . $updated['status'], $booking, $booking->calendar_event );
				$booking = \FluentBooking\App\Models\Booking::with( array( 'calendar_event', 'calendar' ) )->find( $booking->id );
				do_action( 'fluent_booking/after_booking_' . $booking->status, $booking, $booking->calendar_event, $booking );
			}

			do_action( 'fluent_booking/after_patch_booking_schedule', $booking, $oldBooking );

			return array(
				'success' => true,
				'id'      => (int) $booking->id,
				'status'  => $booking->status ?? '',
			);
		},
	) );

	// =========================================================================
	// DELETE BOOKING (P0)
	// =========================================================================

	$reg->delete( 'fluent-booking/delete-booking', array(
		'label'       => 'Delete Booking',
		'description' => 'Permanently delete a booking by ID. Fires before_delete_booking and after_delete_booking hooks. Also deletes group bookings if applicable.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Booking ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Models\Booking' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking Booking model not found' );
			}

			$booking = \FluentBooking\App\Models\Booking::find( (int) $input['id'] );
			if ( ! $booking ) {
				return fluent_abilities_error( 'not_found', 'Booking not found' );
			}

			$booking_id = (int) $booking->id;

			do_action( 'fluent_booking/before_delete_booking', $booking );

			$booking->delete();

			do_action( 'fluent_booking/after_delete_booking', $booking_id );

			// Delete group bookings if applicable (matches SchedulesController pattern).
			if ( method_exists( $booking, 'isMultiGuestBooking' ) && $booking->isMultiGuestBooking() ) {
				\FluentBooking\App\Models\Booking::where( 'event_id', $booking->event_id )
					->where( 'group_id', $booking->group_id )
					->delete();
			}

			return array( 'success' => true, 'id' => $booking_id );
		},
	) );

	// =========================================================================
	// GET BOOKING ACTIVITIES (P1)
	// =========================================================================

	$reg->read( 'fluent-booking/get-booking-activities', array(
		'label'       => 'Get Booking Activities',
		'description' => 'Get the activity/audit log for a booking. Returns notes, status changes, email logs, and system events. Input: pass the booking ID as `id` (an integer) — the field is `id`, NOT `booking_id`.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Booking ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'activities', array(
			'id'          => array( 'type' => 'integer' ),
			'booking_id'  => array( 'type' => 'integer' ),
			'type'        => array( 'type' => array( 'string', 'null' ) ),
			'status'      => array( 'type' => array( 'string', 'null' ) ),
			'title'       => array( 'type' => array( 'string', 'null' ) ),
			'description' => array( 'type' => array( 'string', 'null' ) ),
			'created_by'  => array( 'type' => array( 'integer', 'null' ) ),
			'created_at'  => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			// Verify booking exists.
			$booking = wpFluent()->table( 'fcal_bookings' )->where( 'id', (int) $input['id'] )->first();
			if ( ! $booking ) {
				return fluent_abilities_error( 'not_found', 'Booking not found' );
			}

			$activities = wpFluent()->table( 'fcal_booking_activity' )
				->where( 'booking_id', (int) $input['id'] )
				->orderBy( 'id', 'DESC' )
				->get();

			$items = array();
			foreach ( $activities as $activity ) {
				$items[] = array(
					'id'          => (int) $activity->id,
					'booking_id'  => (int) $activity->booking_id,
					'type'        => $activity->type ?? null,
					'status'      => $activity->status ?? null,
					'title'       => $activity->title ?? null,
					'description' => wp_unslash( $activity->description ?? '' ),
					'created_by'  => $activity->created_by ? (int) $activity->created_by : null,
					'created_at'  => $activity->created_at ? (string) $activity->created_at : null,
				);
			}

			return array( 'activities' => $items, 'total' => count( $items ) );
		},
	) );

	// =========================================================================
	// ADD BOOKING NOTE (P1)
	// =========================================================================

	$reg->write( 'fluent-booking/add-booking-note', array(
		'label'       => 'Add Internal Note to Booking',
		'description' => 'Add an internal note to a booking\'s activity log. Returns the persisted activity_id of the new entry so callers can reference it in subsequent reads. Input: pass the booking ID as `id` (an integer, NOT `booking_id`) plus `note` (string).',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id', 'note' ),
			'properties' => array(
				'id'   => array( 'type' => 'integer', 'description' => 'Booking ID' ),
				'note' => array( 'type' => 'string', 'description' => 'Note content' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'activity_id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			// Verify booking exists.
			$booking = wpFluent()->table( 'fcal_bookings' )->where( 'id', (int) $input['id'] )->first();
			if ( ! $booking ) {
				return fluent_abilities_error( 'not_found', 'Booking not found' );
			}

			// Single canonical write. Previously this did BOTH a direct
			// wpFluent insertGetId() AND do_action('fluent_booking/log_booking_note'),
			// and the vendor LogHandler (app/Hooks/Handlers/LogHandler.php)
			// listens to that action and ALSO calls BookingActivity::create() —
			// so every call persisted TWO fcal_booking_activity rows. Routing
			// once through the vendor canonical model
			// (\FluentBooking\App\Models\BookingActivity, the same model the
			// LogHandler uses) writes exactly one row and still returns the real
			// persisted id for the V9/P-N round-trip. The do_action is dropped
			// because LogHandler only persists — it has no other side effect to
			// preserve.
			if ( ! class_exists( '\\FluentBooking\\App\\Models\\BookingActivity' ) ) {
				return new WP_Error( 'vendor_helper_unavailable', 'FluentBooking\\App\\Models\\BookingActivity is not available. FluentBooking must be active for this ability.' );
			}

			try {
				$activity = \FluentBooking\App\Models\BookingActivity::create( array(
					'booking_id'  => (int) $input['id'],
					'status'      => 'open',
					'type'        => 'note',
					'title'       => 'Internal Note',
					'description' => sanitize_textarea_field( $input['note'] ),
				) );
			} catch ( \Throwable $e ) {
				return new WP_Error( 'vendor_precondition_failed', 'FluentBooking BookingActivity::create failed: ' . $e->getMessage() );
			}

			return array( 'success' => true, 'activity_id' => (int) $activity->id );
		},
	) );

	// =========================================================================
	// GET BOOKING META (P1)
	// =========================================================================

	$reg->read( 'fluent-booking/get-booking-meta', array(
		'label'       => 'Get Booking Metadata',
		'description' => 'Get all metadata (custom field data, additional guests, etc.) for a booking. Input: pass the booking ID as `id` (an integer) — the field is `id`, NOT `booking_id`.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Booking ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'booking_id' => array( 'type' => 'integer' ),
			'meta'       => array( 'type' => 'object' ),
		) ),
		'callback' => function( $input ) {
			$booking = wpFluent()->table( 'fcal_bookings' )->where( 'id', (int) $input['id'] )->first();
			if ( ! $booking ) {
				return fluent_abilities_error( 'not_found', 'Booking not found' );
			}

			// fcal_booking_meta uses meta_key and value columns (not meta_value).
			$meta_rows = wpFluent()->table( 'fcal_booking_meta' )
				->where( 'booking_id', (int) $input['id'] )
				->get();

			$meta = array();
			foreach ( $meta_rows as $row ) {
				$meta[ $row->meta_key ] = fluent_abilities_safe_array( maybe_unserialize( $row->value ) );
			}

			return array(
				'booking_id' => (int) $input['id'],
				'meta'       => ! empty( $meta ) ? $meta : (object) array(),
			);
		},
	) );

	// =========================================================================
	// GET GROUP ATTENDEES (P1)
	// =========================================================================

	$reg->read( 'fluent-booking/get-group-attendees', array(
		'label'       => 'Get Group Booking Attendees',
		'description' => 'List all attendees in a group booking by group ID. For group events only.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'group_id' ),
			'properties' => array(
				'group_id' => array( 'type' => 'integer', 'description' => 'Group booking ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'attendees', array(
			'id'         => array( 'type' => 'integer' ),
			'first_name' => array( 'type' => array( 'string', 'null' ) ),
			'last_name'  => array( 'type' => array( 'string', 'null' ) ),
			'email'      => array( 'type' => array( 'string', 'null' ) ),
			'phone'      => array( 'type' => array( 'string', 'null' ) ),
			'status'     => array( 'type' => 'string' ),
			'start_time' => array( 'type' => array( 'string', 'null' ) ),
			'end_time'   => array( 'type' => array( 'string', 'null' ) ),
			'created_at' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$group_id = (int) $input['group_id'];

			$attendees = wpFluent()->table( 'fcal_bookings' )
				->where( 'group_id', $group_id )
				->orderBy( 'id', 'ASC' )
				->get();

			if ( empty( $attendees ) ) {
				return fluent_abilities_error( 'not_found', 'No attendees found for this group ID' );
			}

			$items = array();
			foreach ( $attendees as $att ) {
				$items[] = array(
					'id'         => (int) $att->id,
					'first_name' => $att->first_name ?? null,
					'last_name'  => $att->last_name ?? null,
					'email'      => $att->email ?? null,
					'phone'      => $att->phone ?? null,
					'status'     => $att->status ?? '',
					'start_time' => $att->start_time ? (string) $att->start_time : null,
					'end_time'   => $att->end_time ? (string) $att->end_time : null,
					'created_at' => $att->created_at ? (string) $att->created_at : null,
				);
			}

			return array( 'attendees' => $items, 'total' => count( $items ) );
		},
	) );

	// =========================================================================
	// SEND CONFIRMATION EMAIL (P1)
	// =========================================================================

	$reg->write( 'fluent-booking/send-confirmation-email', array(
		'label'       => 'Resend Confirmation Email',
		'description' => 'Resend booking confirmation email to the guest or host.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'       => array( 'type' => 'integer', 'description' => 'Booking ID' ),
				'email_to' => array(
					'type'        => 'string',
					'description' => 'Send to guest or host (default: guest)',
					'enum'        => array( 'guest', 'host' ),
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Models\Booking' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking Booking model not found' );
			}
			if ( ! class_exists( '\FluentBooking\App\Services\EmailNotificationService' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking EmailNotificationService not found' );
			}

			$booking = \FluentBooking\App\Models\Booking::with( array( 'calendar', 'calendar_event' ) )
				->find( (int) $input['id'] );

			if ( ! $booking ) {
				return fluent_abilities_error( 'not_found', 'Booking not found' );
			}

			$email_to = sanitize_text_field( $input['email_to'] ?? 'guest' );

			$notifications = $booking->calendar_event->getNotifications();

			if ( $email_to === 'host' ) {
				$email = \FluentBooking\Framework\Support\Arr::get( $notifications, 'booking_conf_host.email', array() );
			} else {
				$email = \FluentBooking\Framework\Support\Arr::get( $notifications, 'booking_conf_attendee.email', array() );
			}

			$result = \FluentBooking\App\Services\EmailNotificationService::emailOnBooked(
				$booking, $email, $email_to, 'scheduled', true
			);

			if ( ! $result ) {
				return fluent_abilities_error( 'send_failed', 'Notification sending failed' );
			}

			return array( 'success' => true );
		},
	) );

	$count = 8;
	error_log( "Abilities for Fluent: Registered {$count} Booking (bookings sub-module) abilities" );

}, 100 );
