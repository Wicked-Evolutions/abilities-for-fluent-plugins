<?php
/**
 * FluentBooking — Calendar meta + landing URL (cluster 4.5).
 *
 * Calendar meta lives in fcal_meta with object_type='Calendar' (capitalized).
 * Wraps Calendar::getMeta($key) / updateMeta($key, $value) / metas() relation.
 *
 *   - fluent-booking/get-calendar-meta          (read — single key or all)
 *   - fluent-booking/set-calendar-meta          (write — Calendar::updateMeta)
 *   - fluent-booking/delete-calendar-meta       (delete — Meta::where(...)->delete())
 *   - fluent-booking/get-calendar-landing-url   (read — Calendar::getLandingPageUrl)
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

function fluent_booking_register_calendar_meta_abilities() {

	$reg = new Fluent_Abilities_Registrar( 'booking' );

	// =========================================================================
	// 4.5.1 — GET CALENDAR META
	// =========================================================================

	$reg->read( 'fluent-booking/get-calendar-meta', array(
		'label'       => 'Get Calendar Meta',
		'description' => 'Read calendar metadata. If `key` is omitted, returns all meta rows for the calendar.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'calendar_id' ),
			'properties' => array(
				'calendar_id' => array( 'type' => 'integer', 'description' => 'Calendar ID' ),
				'key'         => array( 'type' => 'string', 'description' => 'Optional meta key. If omitted, all meta is returned.' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'calendar_id' => array( 'type' => 'integer' ),
			'key'         => array( 'type' => array( 'string', 'null' ) ),
			'value'       => array( 'type' => array( 'string', 'integer', 'number', 'boolean', 'array', 'object', 'null' ) ),
			'meta'        => array( 'type' => array( 'array', 'null' ), 'items' => array( 'type' => 'object' ) ),
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

			if ( isset( $input['key'] ) && $input['key'] !== '' ) {
				$key   = sanitize_text_field( $input['key'] );
				$value = $calendar->getMeta( $key );
				return array(
					'calendar_id' => $calendar_id,
					'key'         => $key,
					'value'       => fluent_abilities_safe_array( $value ),
					'meta'        => null,
				);
			}

			$rows = wpFluent()->table( 'fcal_meta' )
				->where( 'object_type', 'Calendar' )
				->where( 'object_id', $calendar_id )
				->get();

			$meta = array();
			foreach ( $rows as $row ) {
				$meta[] = array(
					'key'   => $row->key,
					'value' => fluent_abilities_safe_array( maybe_unserialize( $row->value ) ),
				);
			}

			return array(
				'calendar_id' => $calendar_id,
				'key'         => null,
				'value'       => null,
				'meta'        => $meta,
			);
		},
	) );

	// =========================================================================
	// 4.5.2 — SET CALENDAR META
	// =========================================================================

	$reg->write( 'fluent-booking/set-calendar-meta', array(
		'label'       => 'Set Calendar Meta',
		'description' => 'Insert or update a meta key/value pair on a calendar via Calendar::updateMeta().',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'calendar_id', 'key' ),
			'properties' => array(
				'calendar_id' => array( 'type' => 'integer', 'description' => 'Calendar ID' ),
				'key'         => array( 'type' => 'string', 'description' => 'Meta key' ),
				'value'       => array(
					'type'        => array( 'string', 'integer', 'number', 'boolean', 'array', 'object', 'null' ),
					'description' => 'Meta value',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'calendar_id' => array( 'type' => 'integer' ),
			'key'         => array( 'type' => 'string' ),
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

			$key   = sanitize_text_field( $input['key'] );
			$value = $input['value'] ?? null;

			$calendar->updateMeta( $key, $value );

			return array(
				'success'     => true,
				'calendar_id' => $calendar_id,
				'key'         => $key,
			);
		},
	) );

	// =========================================================================
	// 4.5.3 — DELETE CALENDAR META
	// =========================================================================

	$reg->delete( 'fluent-booking/delete-calendar-meta', array(
		'label'       => 'Delete Calendar Meta',
		'description' => 'Remove a meta row from a calendar (matched by object_type=Calendar + object_id + key).',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'calendar_id', 'key' ),
			'properties' => array(
				'calendar_id' => array( 'type' => 'integer', 'description' => 'Calendar ID' ),
				'key'         => array( 'type' => 'string', 'description' => 'Meta key to delete' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'deleted' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$calendar_id = (int) $input['calendar_id'];
			$key         = sanitize_text_field( $input['key'] );

			if ( $calendar_id <= 0 || $key === '' ) {
				return fluent_abilities_error( 'ability_invalid_input', 'calendar_id and key are required' );
			}

			$deleted = wpFluent()->table( 'fcal_meta' )
				->where( 'object_type', 'Calendar' )
				->where( 'object_id', $calendar_id )
				->where( 'key', $key )
				->delete();

			return array(
				'success' => true,
				'deleted' => (int) $deleted,
			);
		},
	) );

	// =========================================================================
	// 4.5.4 — GET CALENDAR LANDING URL
	// =========================================================================

	$reg->read( 'fluent-booking/get-calendar-landing-url', array(
		'label'       => 'Get Calendar Landing URL',
		'description' => 'Return the public booking-page URL for a calendar (wraps Calendar::getLandingPageUrl).',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'calendar_id' ),
			'properties' => array(
				'calendar_id' => array( 'type' => 'integer', 'description' => 'Calendar ID' ),
				'force'       => array( 'type' => 'boolean', 'description' => 'Force regenerate the URL (default false)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'calendar_id'  => array( 'type' => 'integer' ),
			'landing_url'  => array( 'type' => array( 'string', 'null' ) ),
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

			$force = ! empty( $input['force'] );
			$url   = method_exists( $calendar, 'getLandingPageUrl' ) ? $calendar->getLandingPageUrl( $force ) : null;

			return array(
				'calendar_id' => $calendar_id,
				'landing_url' => $url ? (string) $url : null,
			);
		},
	) );

}
add_action( 'wp_abilities_api_init', 'fluent_booking_register_calendar_meta_abilities' );
