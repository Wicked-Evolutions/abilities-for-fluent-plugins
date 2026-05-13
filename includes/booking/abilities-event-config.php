<?php
/**
 * FluentBooking — Event settings typed sub-schema wrappers (cluster 4.6).
 *
 * Sub-key wrappers over fcal_calendar_events.settings (LONGTEXT serialized).
 * Pattern mirrors the FluentCommunity v2.0 §4.6 typed-sub-schema approach: read
 * and partial-merge writes against a narrow slice of the event settings object.
 *
 *   - fluent-booking/get-event-notifications     (read)
 *   - fluent-booking/update-event-notifications  (write)
 *   - fluent-booking/get-event-buffers           (read)
 *   - fluent-booking/update-event-buffers        (write)
 *   - fluent-booking/get-event-redirect          (read)
 *   - fluent-booking/update-event-redirect       (write)
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

/**
 * Load event settings array (deserialized) for a given event ID.
 *
 * @param int $event_id
 * @return array|WP_Error
 */
function fluent_booking_load_event_settings( $event_id ) {
	if ( ! class_exists( '\FluentBooking\App\Models\CalendarSlot' ) ) {
		return fluent_abilities_error( 'missing_class', 'FluentBooking CalendarSlot model not found' );
	}

	$event = \FluentBooking\App\Models\CalendarSlot::find( (int) $event_id );
	if ( ! $event ) {
		return fluent_abilities_error( 'not_found', 'Event (calendar slot) not found' );
	}

	$settings = maybe_unserialize( $event->settings ?? '' );
	$settings = is_array( $settings ) ? $settings : array();

	return array( 'event' => $event, 'settings' => $settings );
}

/**
 * Merge partial settings into the event's serialized settings and save.
 *
 * @param \FluentBooking\App\Models\CalendarSlot $event
 * @param array $current_settings
 * @param array $partial            Slice keyed at the top level (e.g. ['notifications' => [...]])
 * @return array Merged settings.
 */
function fluent_booking_merge_event_settings( $event, $current_settings, $partial ) {
	$merged = array_replace_recursive( $current_settings, $partial );
	$event->settings = maybe_serialize( $merged );
	$event->save();
	return $merged;
}

function fluent_booking_register_event_config_abilities() {

	$reg = new Fluent_Abilities_Registrar( 'booking' );

	// =========================================================================
	// 4.6.1 — GET EVENT NOTIFICATIONS
	// =========================================================================

	$reg->read( 'fluent-booking/get-event-notifications', array(
		'label'       => 'Get Event Notification Config',
		'description' => 'Return the per-event notification settings (confirmation_attendee, confirmation_host, reminder, follow_up, cancellation, reschedule_email, no_show_email).',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'event_id' ),
			'properties' => array(
				'event_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'event_id'      => array( 'type' => 'integer' ),
			'notifications' => array( 'type' => array( 'object', 'array' ) ),
		) ),
		'callback' => function( $input ) {
			$loaded = fluent_booking_load_event_settings( $input['event_id'] ?? 0 );
			if ( is_wp_error( $loaded ) ) {
				return $loaded;
			}

			$notifications = $loaded['settings']['notifications']
				?? $loaded['settings']['email_notifications']
				?? array();

			// Fall back to direct accessor if available.
			if ( empty( $notifications ) && method_exists( $loaded['event'], 'getNotifications' ) ) {
				$notifications = $loaded['event']->getNotifications();
			}

			return array(
				'event_id'      => (int) $input['event_id'],
				'notifications' => fluent_abilities_safe_array( $notifications ),
			);
		},
	) );

	// =========================================================================
	// 4.6.2 — UPDATE EVENT NOTIFICATIONS
	// =========================================================================

	$reg->write( 'fluent-booking/update-event-notifications', array(
		'label'       => 'Update Event Notification Config',
		'description' => 'Partial-merge update of the event\'s notification settings object. Any sub-key omitted from input is preserved on the event.',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'event_id', 'notifications' ),
			'properties' => array(
				'event_id'      => array( 'type' => 'integer' ),
				'notifications' => array(
					'type'        => array( 'object', 'array' ),
					'description' => 'Partial notifications object — only included keys are merged.',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'event_id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$loaded = fluent_booking_load_event_settings( $input['event_id'] ?? 0 );
			if ( is_wp_error( $loaded ) ) {
				return $loaded;
			}

			$partial = isset( $input['notifications'] ) ? (array) $input['notifications'] : array();
			fluent_booking_merge_event_settings( $loaded['event'], $loaded['settings'], array( 'notifications' => $partial ) );

			return array(
				'success'  => true,
				'event_id' => (int) $input['event_id'],
			);
		},
	) );

	// =========================================================================
	// 4.6.3 — GET EVENT BUFFERS
	// =========================================================================

	$reg->read( 'fluent-booking/get-event-buffers', array(
		'label'       => 'Get Event Buffer Config',
		'description' => 'Return buffer_before_minutes, buffer_after_minutes, slot_interval_minutes, minimum_notice_minutes for an event.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'event_id' ),
			'properties' => array(
				'event_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'event_id'               => array( 'type' => 'integer' ),
			'buffer_before_minutes'  => array( 'type' => 'integer' ),
			'buffer_after_minutes'   => array( 'type' => 'integer' ),
			'slot_interval_minutes'  => array( 'type' => 'integer' ),
			'minimum_notice_minutes' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$loaded = fluent_booking_load_event_settings( $input['event_id'] ?? 0 );
			if ( is_wp_error( $loaded ) ) {
				return $loaded;
			}
			$s = $loaded['settings'];

			return array(
				'event_id'               => (int) $input['event_id'],
				'buffer_before_minutes'  => (int) ( $s['buffer_before_minutes'] ?? $s['buffer_before'] ?? 0 ),
				'buffer_after_minutes'   => (int) ( $s['buffer_after_minutes'] ?? $s['buffer_after'] ?? 0 ),
				'slot_interval_minutes'  => (int) ( $s['slot_interval_minutes'] ?? $s['slot_interval'] ?? 0 ),
				'minimum_notice_minutes' => (int) ( $s['minimum_notice_minutes'] ?? $s['minimum_notice'] ?? 0 ),
			);
		},
	) );

	// =========================================================================
	// 4.6.4 — UPDATE EVENT BUFFERS
	// =========================================================================

	$reg->write( 'fluent-booking/update-event-buffers', array(
		'label'       => 'Update Event Buffer Config',
		'description' => 'Partial-merge update of buffer/notice/slot-interval keys on an event\'s settings object.',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'event_id' ),
			'properties' => array(
				'event_id'               => array( 'type' => 'integer' ),
				'buffer_before_minutes'  => array( 'type' => 'integer' ),
				'buffer_after_minutes'   => array( 'type' => 'integer' ),
				'slot_interval_minutes'  => array( 'type' => 'integer' ),
				'minimum_notice_minutes' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'event_id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$loaded = fluent_booking_load_event_settings( $input['event_id'] ?? 0 );
			if ( is_wp_error( $loaded ) ) {
				return $loaded;
			}

			$partial = array();
			foreach ( array( 'buffer_before_minutes', 'buffer_after_minutes', 'slot_interval_minutes', 'minimum_notice_minutes' ) as $k ) {
				if ( isset( $input[ $k ] ) ) {
					$partial[ $k ] = max( 0, (int) $input[ $k ] );
				}
			}

			if ( ! empty( $partial ) ) {
				fluent_booking_merge_event_settings( $loaded['event'], $loaded['settings'], $partial );
			}

			return array(
				'success'  => true,
				'event_id' => (int) $input['event_id'],
			);
		},
	) );

	// =========================================================================
	// 4.6.5 — GET EVENT REDIRECT
	// =========================================================================

	$reg->read( 'fluent-booking/get-event-redirect', array(
		'label'       => 'Get Event Redirect Config',
		'description' => 'Return the post-booking redirect config for an event (enabled, url, success_message, query_params).',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'event_id' ),
			'properties' => array(
				'event_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'event_id'        => array( 'type' => 'integer' ),
			'enabled'         => array( 'type' => 'boolean' ),
			'url'             => array( 'type' => array( 'string', 'null' ) ),
			'success_message' => array( 'type' => array( 'string', 'null' ) ),
			'query_params'    => array( 'type' => array( 'object', 'array', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$loaded = fluent_booking_load_event_settings( $input['event_id'] ?? 0 );
			if ( is_wp_error( $loaded ) ) {
				return $loaded;
			}
			$redirect = $loaded['settings']['redirect'] ?? array();
			$redirect = is_array( $redirect ) ? $redirect : array();

			return array(
				'event_id'        => (int) $input['event_id'],
				'enabled'         => ! empty( $redirect['enabled'] ),
				'url'             => isset( $redirect['url'] ) ? (string) $redirect['url'] : null,
				'success_message' => isset( $redirect['success_message'] ) ? (string) $redirect['success_message'] : null,
				'query_params'    => isset( $redirect['query_params'] ) ? fluent_abilities_safe_array( $redirect['query_params'] ) : null,
			);
		},
	) );

	// =========================================================================
	// 4.6.6 — UPDATE EVENT REDIRECT
	// =========================================================================

	$reg->write( 'fluent-booking/update-event-redirect', array(
		'label'       => 'Update Event Redirect Config',
		'description' => 'Partial-merge update of the event\'s redirect sub-object (enabled, url, success_message, query_params).',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'event_id' ),
			'properties' => array(
				'event_id'        => array( 'type' => 'integer' ),
				'enabled'         => array( 'type' => 'boolean' ),
				'url'             => array( 'type' => 'string' ),
				'success_message' => array( 'type' => 'string' ),
				'query_params'    => array( 'type' => array( 'object', 'array' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'event_id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$loaded = fluent_booking_load_event_settings( $input['event_id'] ?? 0 );
			if ( is_wp_error( $loaded ) ) {
				return $loaded;
			}

			$partial = array();
			if ( array_key_exists( 'enabled', $input ) ) {
				$partial['enabled'] = (bool) $input['enabled'];
			}
			if ( isset( $input['url'] ) ) {
				$partial['url'] = esc_url_raw( $input['url'] );
			}
			if ( isset( $input['success_message'] ) ) {
				$partial['success_message'] = sanitize_text_field( $input['success_message'] );
			}
			if ( isset( $input['query_params'] ) ) {
				$partial['query_params'] = (array) $input['query_params'];
			}

			if ( ! empty( $partial ) ) {
				fluent_booking_merge_event_settings( $loaded['event'], $loaded['settings'], array( 'redirect' => $partial ) );
			}

			return array(
				'success'  => true,
				'event_id' => (int) $input['event_id'],
			);
		},
	) );

}
add_action( 'wp_abilities_api_init', 'fluent_booking_register_event_config_abilities' );
