<?php
/**
 * FluentBooking — Availability Schedule CRUD + Event Availability
 *
 * 8 abilities in the 'fluent-booking' category.
 * Registered via Fluent_Abilities_Registrar.
 *
 * Availability schedules live in fcal_meta (object_type = 'availability').
 * The Availability model scopes to this object_type automatically.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'booking' );

	// =========================================================================
	// LIST AVAILABILITY SCHEDULES (P1)
	// =========================================================================

	$reg->read( 'fluent-booking/list-availability', array(
		'label'       => 'List Availability Schedules',
		'description' => 'List all availability schedules. Each schedule contains weekly time slots and date overrides that events can reference.',
		'input_schema' => array(
			'type' => 'object',
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'schedules', array(
			'id'         => array( 'type' => 'integer' ),
			'title'      => array( 'type' => 'string' ),
			'user_id'    => array( 'type' => 'integer' ),
			'is_default' => array( 'type' => 'boolean' ),
			'timezone'   => array( 'type' => 'string' ),
			'created_at' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$schedules = wpFluent()->table( 'fcal_meta' )
				->where( 'object_type', 'availability' )
				->orderBy( 'id', 'DESC' )
				->get();

			$items = array();
			foreach ( $schedules as $schedule ) {
				$value    = maybe_unserialize( $schedule->value );
				$value    = is_array( $value ) ? $value : array();
				$items[]  = array(
					'id'         => (int) $schedule->id,
					'title'      => $schedule->key ?? '',
					'user_id'    => (int) ( $schedule->object_id ?? 0 ),
					'is_default' => ! empty( $value['default'] ),
					'timezone'   => $value['timezone'] ?? 'UTC',
					'created_at' => $schedule->created_at ? (string) $schedule->created_at : null,
				);
			}

			return array( 'schedules' => $items, 'total' => count( $items ) );
		},
	) );

	// =========================================================================
	// GET AVAILABILITY SCHEDULE (P1)
	// =========================================================================

	$reg->read( 'fluent-booking/get-availability', array(
		'label'       => 'Get Availability Schedule',
		'description' => 'Get a single availability schedule by ID with full weekly slots and date overrides.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Availability schedule ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'               => array( 'type' => 'integer' ),
			'title'            => array( 'type' => 'string' ),
			'user_id'          => array( 'type' => 'integer' ),
			'is_default'       => array( 'type' => 'boolean' ),
			'timezone'         => array( 'type' => 'string' ),
			'weekly_schedules' => array( 'type' => 'object' ),
			'date_overrides'   => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'created_at'       => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$schedule = wpFluent()->table( 'fcal_meta' )
				->where( 'id', (int) $input['id'] )
				->where( 'object_type', 'availability' )
				->first();

			if ( ! $schedule ) {
				return fluent_abilities_error( 'not_found', 'Availability schedule not found' );
			}

			$value = maybe_unserialize( $schedule->value );
			$value = is_array( $value ) ? $value : array();

			return array(
				'id'               => (int) $schedule->id,
				'title'            => $schedule->key ?? '',
				'user_id'          => (int) ( $schedule->object_id ?? 0 ),
				'is_default'       => ! empty( $value['default'] ),
				'timezone'         => $value['timezone'] ?? 'UTC',
				'weekly_schedules' => fluent_abilities_safe_array( $value['weekly_schedules'] ?? array() ),
				'date_overrides'   => array_values( (array) fluent_abilities_safe_array( $value['date_overrides'] ?? array() ) ),
				'created_at'       => $schedule->created_at ? (string) $schedule->created_at : null,
			);
		},
	) );

	// =========================================================================
	// CREATE AVAILABILITY SCHEDULE (P1)
	// =========================================================================

	$reg->write( 'fluent-booking/create-availability', array(
		'label'       => 'Create Availability Schedule',
		'description' => 'Create a new availability schedule. Uses AvailabilityService for default weekly schema.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'title' ),
			'properties' => array(
				'title'    => array( 'type' => 'string', 'description' => 'Schedule name' ),
				'timezone' => array( 'type' => 'string', 'description' => 'Timezone (default: UTC)' ),
				'user_id'  => array( 'type' => 'integer', 'description' => 'Host user ID (default: current user)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Models\Availability' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking Availability model not found' );
			}
			if ( ! class_exists( '\FluentBooking\App\Services\AvailabilityService' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking AvailabilityService not found' );
			}

			$title    = sanitize_text_field( $input['title'] );
			$timezone = sanitize_text_field( $input['timezone'] ?? 'UTC' );
			$user_id  = (int) ( $input['user_id'] ?? get_current_user_id() );

			// Check for duplicate title.
			if ( \FluentBooking\App\Services\AvailabilityService::isTitleAlreadyExist( $title, $user_id ) ) {
				return fluent_abilities_error( 'duplicate_title', 'An availability schedule with this title already exists' );
			}

			// Check if user already has an existing schedule (determines default status).
			$existing = \FluentBooking\App\Models\Availability::where( 'object_id', $user_id )->first();

			$schedule_data = \FluentBooking\App\Services\AvailabilityService::createScheduleSchema(
				$user_id, $title, ! $existing, $timezone
			);

			$created = \FluentBooking\App\Models\Availability::create( $schedule_data );

			do_action( 'fluent_booking/availability_schedule_created', $created );

			return array(
				'success' => true,
				'id'      => (int) $created->id,
				'title'   => $created->key ?? '',
			);
		},
	) );

	// =========================================================================
	// UPDATE AVAILABILITY SCHEDULE (P1)
	// =========================================================================

	$reg->write( 'fluent-booking/update-availability', array(
		'label'       => 'Update Availability Schedule',
		'description' => 'Update an availability schedule\'s weekly slots, date overrides, and/or timezone.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'               => array( 'type' => 'integer', 'description' => 'Availability schedule ID' ),
				'title'            => array( 'type' => 'string', 'description' => 'New title (optional)' ),
				'timezone'         => array( 'type' => 'string', 'description' => 'New timezone (optional)' ),
				'weekly_schedules' => array( 'type' => 'object', 'description' => 'Weekly schedule slots keyed by day (optional)' ),
				'date_overrides'   => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'description' => 'Date override entries (optional)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Models\Availability' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking Availability model not found' );
			}

			$schedule = \FluentBooking\App\Models\Availability::find( (int) $input['id'] );
			if ( ! $schedule ) {
				return fluent_abilities_error( 'not_found', 'Availability schedule not found' );
			}

			// Update title if provided.
			if ( isset( $input['title'] ) ) {
				$schedule->key = sanitize_text_field( $input['title'] );
			}

			// Merge value fields.
			$current_value = is_array( $schedule->value ) ? $schedule->value : array();

			if ( isset( $input['timezone'] ) ) {
				$current_value['timezone'] = sanitize_text_field( $input['timezone'] );
			}
			if ( isset( $input['weekly_schedules'] ) ) {
				$current_value['weekly_schedules'] = $input['weekly_schedules'];
			}
			if ( isset( $input['date_overrides'] ) ) {
				$current_value['date_overrides'] = $input['date_overrides'];
			}

			$schedule->value = $current_value;
			$schedule->save();

			// Note: the hook name has a typo in FluentBooking source — intentional match.
			do_action( 'fluent_booking/avaibility_schedule_updated', $schedule, $current_value );

			return array( 'success' => true, 'id' => (int) $schedule->id );
		},
	) );

	// =========================================================================
	// DELETE AVAILABILITY SCHEDULE (P1)
	// =========================================================================

	$reg->delete( 'fluent-booking/delete-availability', array(
		'label'       => 'Delete Availability Schedule',
		'description' => 'Delete an availability schedule. Cannot delete the default schedule if a calendar exists for the user, or if events depend on it.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Availability schedule ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Models\Availability' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking Availability model not found' );
			}

			$schedule = \FluentBooking\App\Models\Availability::find( (int) $input['id'] );
			if ( ! $schedule ) {
				return fluent_abilities_error( 'not_found', 'Availability schedule not found' );
			}

			$value = is_array( $schedule->value ) ? $schedule->value : array();

			// Block deleting default schedule if the user has a calendar.
			if ( ! empty( $value['default'] ) ) {
				if ( class_exists( '\FluentBooking\App\Models\Calendar' ) ) {
					$has_calendar = \FluentBooking\App\Models\Calendar::where( 'user_id', $schedule->object_id )->first();
					if ( $has_calendar ) {
						return fluent_abilities_error( 'cannot_delete', 'Default schedule cannot be deleted while the user has a calendar' );
					}
				}
			}

			// Check if events depend on this schedule.
			if ( class_exists( '\FluentBooking\App\Services\AvailabilityService' ) ) {
				$usage_count = \FluentBooking\App\Services\AvailabilityService::getAvailabilityUsageCount( (int) $input['id'] );
				if ( $usage_count ) {
					return fluent_abilities_error( 'in_use', sprintf( 'Cannot delete: %d events depend on this schedule', $usage_count ) );
				}
			}

			$schedule_id = (int) $schedule->id;

			do_action( 'fluent_booking/before_delete_availability_schedule', $schedule );

			$schedule->delete();

			do_action( 'fluent_booking/after_delete_availability_schedule', $schedule_id );

			return array( 'success' => true, 'id' => $schedule_id );
		},
	) );

	// =========================================================================
	// CLONE AVAILABILITY SCHEDULE (P1)
	// =========================================================================

	$reg->write( 'fluent-booking/clone-availability', array(
		'label'       => 'Clone Availability Schedule',
		'description' => 'Clone an existing availability schedule. The clone is created as non-default with " (Clone)" appended to the title.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Availability schedule ID to clone' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Models\Availability' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking Availability model not found' );
			}

			$original = \FluentBooking\App\Models\Availability::find( (int) $input['id'] );
			if ( ! $original ) {
				return fluent_abilities_error( 'not_found', 'Availability schedule not found' );
			}

			$cloned = $original->replicate();
			$cloned->object_id = get_current_user_id();
			$cloned->key = $original->key . ' (Clone)';

			$cloned_value = is_array( $cloned->value ) ? $cloned->value : array();
			$cloned_value['default'] = false;
			$cloned->value = $cloned_value;

			$cloned->save();

			do_action( 'fluent_booking/availability_schedule_cloned', $cloned );

			return array(
				'success' => true,
				'id'      => (int) $cloned->id,
				'title'   => $cloned->key ?? '',
			);
		},
	) );

	// =========================================================================
	// GET EVENT AVAILABILITY (P1)
	// =========================================================================

	$reg->read( 'fluent-booking/get-event-availability', array(
		'label'       => 'Get Event Availability',
		'description' => 'Get the availability configuration for a specific event, including schedule type, weekly schedules, date overrides, and range settings.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'event_id' ),
			'properties' => array(
				'event_id' => array( 'type' => 'integer', 'description' => 'Event (calendar slot) ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'event_id'          => array( 'type' => 'integer' ),
			'availability_type' => array( 'type' => array( 'string', 'null' ) ),
			'availability_id'   => array( 'type' => array( 'integer', 'null' ) ),
			'settings'          => array( 'type' => 'object' ),
		) ),
		'callback' => function( $input ) {
			$event = wpFluent()->table( 'fcal_calendar_events' )
				->where( 'id', (int) $input['event_id'] )
				->first();

			if ( ! $event ) {
				return fluent_abilities_error( 'not_found', 'Event not found' );
			}

			$settings = fluent_abilities_safe_array( maybe_unserialize( $event->settings ) );

			// Extract availability-related settings.
			$availability_settings = array(
				'schedule_type'    => $settings['schedule_type'] ?? null,
				'weekly_schedules' => fluent_abilities_safe_array( $settings['weekly_schedules'] ?? array() ),
				'date_overrides'   => fluent_abilities_safe_array( $settings['date_overrides'] ?? array() ),
				'range_type'       => $settings['range_type'] ?? null,
				'range_days'       => isset( $settings['range_days'] ) ? (int) $settings['range_days'] : 60,
				'range_date_between' => fluent_abilities_safe_array( $settings['range_date_between'] ?? array() ),
			);

			return array(
				'event_id'          => (int) $event->id,
				'availability_type' => $event->availability_type ?? null,
				'availability_id'   => $event->availability_id ? (int) $event->availability_id : null,
				'settings'          => $availability_settings,
			);
		},
	) );

	// =========================================================================
	// UPDATE EVENT AVAILABILITY (P1)
	// =========================================================================

	$reg->write( 'fluent-booking/update-event-availability', array(
		'label'       => 'Update Event Availability',
		'description' => 'Update the availability configuration for a specific event. Can change the schedule type, weekly schedules, date overrides, and range settings.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'event_id' ),
			'properties' => array(
				'event_id'          => array( 'type' => 'integer', 'description' => 'Event (calendar slot) ID' ),
				'availability_type' => array(
					'type'        => 'string',
					'description' => 'Schedule source type',
					'enum'        => array( 'existing_schedule', 'custom' ),
				),
				'availability_id'   => array( 'type' => 'integer', 'description' => 'Linked availability schedule ID (for existing_schedule type)' ),
				'schedule_type'     => array( 'type' => 'string', 'description' => 'Schedule type (e.g. weekly_schedules)' ),
				'weekly_schedules'  => array( 'type' => 'object', 'description' => 'Weekly schedule slots keyed by day' ),
				'date_overrides'    => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'description' => 'Date override entries' ),
				'range_type'        => array( 'type' => 'string', 'description' => 'Range type (e.g. days, date_range)' ),
				'range_days'        => array( 'type' => 'integer', 'description' => 'Number of days into the future' ),
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

			$settings = is_array( $event->settings ) ? $event->settings : array();

			// Merge provided fields.
			if ( isset( $input['schedule_type'] ) ) {
				$settings['schedule_type'] = sanitize_text_field( $input['schedule_type'] );
			}
			if ( isset( $input['weekly_schedules'] ) ) {
				$settings['weekly_schedules'] = $input['weekly_schedules'];
			}
			if ( isset( $input['date_overrides'] ) ) {
				$settings['date_overrides'] = $input['date_overrides'];
			}
			if ( isset( $input['range_type'] ) ) {
				$settings['range_type'] = sanitize_text_field( $input['range_type'] );
			}
			if ( isset( $input['range_days'] ) ) {
				$settings['range_days'] = (int) $input['range_days'];
			}

			$event->settings = $settings;

			if ( isset( $input['availability_type'] ) ) {
				$event->availability_type = sanitize_text_field( $input['availability_type'] );
			}
			if ( isset( $input['availability_id'] ) ) {
				$event->availability_id = (int) $input['availability_id'];
			}

			$event->save();

			return array( 'success' => true, 'event_id' => (int) $event->id );
		},
	) );

	$count = 8;
	error_log( "Abilities for Fluent: Registered {$count} Booking (availability sub-module) abilities" );

}, 100 );
