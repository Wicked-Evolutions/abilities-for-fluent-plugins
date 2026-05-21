<?php
/**
 * FluentBooking — Booking import (cluster 4.17).
 *
 * Wraps app/Services/ImportService.php (free + pro). Supports CSV / JSON payloads
 * with a dry_run mode that validates without writing.
 *
 *   - fluent-booking/import-bookings (write)
 *
 * Capability override: manage_options (data-import is admin-only).
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

function fluent_booking_register_import_abilities() {

	$reg = new Fluent_Abilities_Registrar( 'booking' );

	// =========================================================================
	// 4.17.1 — IMPORT BOOKINGS
	// =========================================================================

	$reg->write( 'fluent-booking/import-bookings', array(
		'label'       => 'Import Bookings',
		'description' => 'Import bookings for an event from CSV or JSON. Returns per-row imported_count / skipped / errors. Use dry_run=true to validate without writing.',
		'capability'  => 'manage_options',
		'annotations' => array( 'idempotent' => false ),
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'event_id', 'source', 'data' ),
			'properties' => array(
				'event_id' => array( 'type' => 'integer', 'description' => 'Target event ID (CalendarSlot)' ),
				'source'   => array(
					'type' => 'string',
					'enum' => array( 'csv', 'json' ),
				),
				'data'     => array(
					'type'        => array( 'string', 'array' ),
					'description' => 'CSV string (with header row) or JSON array of booking objects',
				),
				'dry_run'  => array( 'type' => 'boolean', 'description' => 'If true, validate but do not insert (default: false)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'event_id'       => array( 'type' => 'integer' ),
			'imported_count' => array( 'type' => 'integer' ),
			'skipped'        => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'errors'         => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'dry_run'        => array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentBooking\App\Services\ImportService' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking ImportService not found' );
			}
			if ( ! class_exists( '\FluentBooking\App\Models\CalendarSlot' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentBooking CalendarSlot model not found' );
			}

			$event_id = (int) $input['event_id'];
			$event    = \FluentBooking\App\Models\CalendarSlot::find( $event_id );
			if ( ! $event ) {
				return fluent_abilities_error( 'not_found', 'Event (calendar slot) not found' );
			}

			$source  = sanitize_text_field( $input['source'] );
			$dry_run = ! empty( $input['dry_run'] );
			$rows    = array();

			if ( $source === 'json' ) {
				$data = $input['data'];
				if ( is_string( $data ) ) {
					$decoded = json_decode( $data, true );
					if ( JSON_ERROR_NONE !== json_last_error() ) {
						return fluent_abilities_error( 'ability_invalid_input', 'Invalid JSON: ' . json_last_error_msg() );
					}
					$rows = is_array( $decoded ) ? $decoded : array();
				} else {
					$rows = is_array( $data ) ? $data : array();
				}
			} elseif ( $source === 'csv' ) {
				$data = is_string( $input['data'] ) ? $input['data'] : '';
				$lines = preg_split( "/\r\n|\n|\r/", trim( $data ) );
				if ( count( $lines ) < 2 ) {
					return fluent_abilities_error( 'ability_invalid_input', 'CSV must contain a header row and at least one data row' );
				}
				$header = str_getcsv( array_shift( $lines ) );
				foreach ( $lines as $line ) {
					if ( $line === '' ) {
						continue;
					}
					$values = str_getcsv( $line );
					if ( count( $values ) !== count( $header ) ) {
						continue;
					}
					$rows[] = array_combine( $header, $values );
				}
			}

			$imported = 0;
			$skipped  = array();
			$errors   = array();

			foreach ( $rows as $i => $row ) {
				if ( ! is_array( $row ) ) {
					$errors[] = array( 'row' => $i, 'reason' => 'not_an_object' );
					continue;
				}
				if ( empty( $row['email'] ) || empty( $row['start_time'] ) ) {
					$skipped[] = array( 'row' => $i, 'reason' => 'missing_required_fields' );
					continue;
				}
				if ( $dry_run ) {
					$imported++;
					continue;
				}

				$insert = array(
					'event_id'         => $event_id,
					'calendar_id'      => (int) ( $event->calendar_id ?? 0 ),
					'host_user_id'     => (int) ( $event->user_id ?? 0 ),
					'email'            => sanitize_email( $row['email'] ),
					'first_name'       => sanitize_text_field( $row['first_name'] ?? '' ),
					'last_name'        => sanitize_text_field( $row['last_name'] ?? '' ),
					'phone'            => sanitize_text_field( $row['phone'] ?? '' ),
					'message'          => sanitize_textarea_field( $row['message'] ?? '' ),
					'start_time'       => sanitize_text_field( $row['start_time'] ),
					'end_time'         => sanitize_text_field( $row['end_time'] ?? '' ),
					'person_time_zone' => sanitize_text_field( $row['person_time_zone'] ?? ( $event->author_timezone ?? 'UTC' ) ),
					'status'           => sanitize_text_field( $row['status'] ?? 'scheduled' ),
					'source'           => 'import',
					'slot_minutes'     => (int) ( $event->duration ?? 0 ),
					'created_at'       => current_time( 'mysql' ),
					'updated_at'       => current_time( 'mysql' ),
				);

				try {
					wpFluent()->table( 'fcal_bookings' )->insert( $insert );
					$imported++;
				} catch ( \Exception $e ) {
					$errors[] = array( 'row' => $i, 'reason' => $e->getMessage() );
				}
			}

			return array(
				'success'        => true,
				'event_id'       => $event_id,
				'imported_count' => $imported,
				'skipped'        => $skipped,
				'errors'         => $errors,
				'dry_run'        => $dry_run,
			);
		},
	) );

}
add_action( 'wp_abilities_api_init', 'fluent_booking_register_import_abilities' );
