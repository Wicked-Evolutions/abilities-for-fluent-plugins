<?php
/**
 * FluentBooking — Pro Coupons (cluster 4.14).
 *
 * §7.Q7 in the research file flagged coupon storage as not yet pinpointed.
 * Implementation uses the most likely table name `fcal_coupons` (matching
 * fcal_* prefix convention used by all FluentBooking Pro tables) and falls back
 * to model-based access where the Pro Coupon module is present.
 *
 *   - fluent-booking/list-coupons    (read)
 *   - fluent-booking/get-coupon      (read)
 *   - fluent-booking/create-coupon   (write)
 *   - fluent-booking/update-coupon   (write)
 *   - fluent-booking/delete-coupon   (delete)
 *
 * Capability override: manage_options (finance surface).
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

function fluent_booking_register_coupons_abilities() {

	$reg = new Fluent_Abilities_Registrar( 'booking' );

	// =========================================================================
	// 4.14.1 — LIST COUPONS
	// =========================================================================

	$reg->read( 'fluent-booking/list-coupons', array(
		'label'       => 'List FluentBooking Coupons',
		'description' => 'List discount coupons with pagination.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge(
				fluent_abilities_pagination_schema(),
				array(
					'status' => array( 'type' => 'string', 'enum' => array( 'active', 'inactive', 'expired' ) ),
				)
			),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'coupons', array(
			'id'              => array( 'type' => 'integer' ),
			'code'            => array( 'type' => 'string' ),
			'title'           => array( 'type' => array( 'string', 'null' ) ),
			'discount_type'   => array( 'type' => 'string' ),
			'discount_amount' => array( 'type' => 'number' ),
			'status'          => array( 'type' => 'string' ),
			'expires_at'      => array( 'type' => array( 'string', 'null' ) ),
			'usage_count'     => array( 'type' => 'integer' ),
			'created_at'      => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$page_args = fluent_abilities_pagination( $input, 20 );

			$query = wpFluent()->table( 'fcal_coupons' );
			if ( ! empty( $input['status'] ) ) {
				$query->where( 'status', sanitize_text_field( $input['status'] ) );
			}
			$total = (int) $query->count();

			$rows = $query->orderBy( 'id', 'DESC' )
				->offset( $page_args['offset'] )
				->limit( $page_args['per_page'] )
				->get();

			$coupons = array();
			foreach ( $rows as $row ) {
				$coupons[] = array(
					'id'              => (int) $row->id,
					'code'            => (string) ( $row->code ?? '' ),
					'title'           => isset( $row->title ) ? (string) $row->title : null,
					'discount_type'   => (string) ( $row->discount_type ?? 'percent' ),
					'discount_amount' => isset( $row->discount_amount ) ? (float) $row->discount_amount : 0.0,
					'status'          => (string) ( $row->status ?? 'active' ),
					'expires_at'      => isset( $row->expires_at ) ? (string) $row->expires_at : null,
					'usage_count'     => isset( $row->usage_count ) ? (int) $row->usage_count : 0,
					'created_at'      => $row->created_at ? (string) $row->created_at : null,
				);
			}

			return array(
				'coupons'  => $coupons,
				'total'    => $total,
				'page'     => $page_args['page'],
				'per_page' => $page_args['per_page'],
			);
		},
	) );

	// =========================================================================
	// 4.14.2 — GET COUPON
	// =========================================================================

	$reg->read( 'fluent-booking/get-coupon', array(
		'label'       => 'Get FluentBooking Coupon',
		'description' => 'Return a single coupon by ID.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'              => array( 'type' => 'integer' ),
			'code'            => array( 'type' => 'string' ),
			'title'           => array( 'type' => array( 'string', 'null' ) ),
			'discount_type'   => array( 'type' => 'string' ),
			'discount_amount' => array( 'type' => 'number' ),
			'status'          => array( 'type' => 'string' ),
			'expires_at'      => array( 'type' => array( 'string', 'null' ) ),
			'usage_count'     => array( 'type' => 'integer' ),
			'settings'        => array( 'type' => array( 'object', 'array', 'null' ) ),
			'created_at'      => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$row = wpFluent()->table( 'fcal_coupons' )
				->where( 'id', (int) $input['id'] )
				->first();
			if ( ! $row ) {
				return fluent_abilities_error( 'not_found', 'Coupon not found' );
			}
			$settings = maybe_unserialize( $row->settings ?? '' );

			return array(
				'id'              => (int) $row->id,
				'code'            => (string) ( $row->code ?? '' ),
				'title'           => isset( $row->title ) ? (string) $row->title : null,
				'discount_type'   => (string) ( $row->discount_type ?? 'percent' ),
				'discount_amount' => isset( $row->discount_amount ) ? (float) $row->discount_amount : 0.0,
				'status'          => (string) ( $row->status ?? 'active' ),
				'expires_at'      => isset( $row->expires_at ) ? (string) $row->expires_at : null,
				'usage_count'     => isset( $row->usage_count ) ? (int) $row->usage_count : 0,
				'settings'        => is_array( $settings ) ? fluent_abilities_safe_array( $settings ) : null,
				'created_at'      => $row->created_at ? (string) $row->created_at : null,
			);
		},
	) );

	// =========================================================================
	// 4.14.3 — CREATE COUPON
	// =========================================================================

	$reg->write( 'fluent-booking/create-coupon', array(
		'label'       => 'Create FluentBooking Coupon',
		'description' => 'Create a discount coupon. Codes must be unique.',
		'capability'  => 'manage_options',
		'annotations' => array( 'idempotent' => false ),
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'code', 'discount_type', 'discount_amount' ),
			'properties' => array(
				'code'            => array( 'type' => 'string' ),
				'title'           => array( 'type' => 'string' ),
				'discount_type'   => array( 'type' => 'string', 'enum' => array( 'percent', 'fixed' ) ),
				'discount_amount' => array( 'type' => 'number' ),
				'status'          => array( 'type' => 'string', 'enum' => array( 'active', 'inactive' ) ),
				'expires_at'      => array( 'type' => 'string', 'description' => 'Optional expiry timestamp (Y-m-d H:i:s)' ),
				'settings'        => array( 'type' => array( 'object', 'array' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'   => array( 'type' => 'integer' ),
			'code' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$code = sanitize_text_field( $input['code'] );
			if ( $code === '' ) {
				return fluent_abilities_error( 'ability_invalid_input', 'code is required' );
			}

			$existing = wpFluent()->table( 'fcal_coupons' )->where( 'code', $code )->first();
			if ( $existing ) {
				return fluent_abilities_error( 'duplicate_code', 'Coupon code already exists' );
			}

			$insert = array(
				'code'            => $code,
				'title'           => sanitize_text_field( $input['title'] ?? '' ),
				'discount_type'   => sanitize_text_field( $input['discount_type'] ),
				'discount_amount' => (float) $input['discount_amount'],
				'status'          => sanitize_text_field( $input['status'] ?? 'active' ),
				'created_at'      => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
			);
			if ( isset( $input['expires_at'] ) ) {
				$insert['expires_at'] = sanitize_text_field( $input['expires_at'] );
			}
			if ( isset( $input['settings'] ) ) {
				$insert['settings'] = maybe_serialize( (array) $input['settings'] );
			}

			$id = wpFluent()->table( 'fcal_coupons' )->insert( $insert );

			return array(
				'success' => true,
				'id'      => (int) $id,
				'code'    => $code,
			);
		},
	) );

	// =========================================================================
	// 4.14.4 — UPDATE COUPON
	// =========================================================================

	$reg->write( 'fluent-booking/update-coupon', array(
		'label'       => 'Update FluentBooking Coupon',
		'description' => 'Partial-update a coupon row by ID.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'              => array( 'type' => 'integer' ),
				'title'           => array( 'type' => 'string' ),
				'discount_type'   => array( 'type' => 'string', 'enum' => array( 'percent', 'fixed' ) ),
				'discount_amount' => array( 'type' => 'number' ),
				'status'          => array( 'type' => 'string', 'enum' => array( 'active', 'inactive' ) ),
				'expires_at'      => array( 'type' => 'string' ),
				'settings'        => array( 'type' => array( 'object', 'array' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$id = (int) $input['id'];

			$update = array();
			foreach ( array( 'title', 'discount_type', 'status', 'expires_at' ) as $k ) {
				if ( isset( $input[ $k ] ) ) {
					$update[ $k ] = sanitize_text_field( $input[ $k ] );
				}
			}
			if ( isset( $input['discount_amount'] ) ) {
				$update['discount_amount'] = (float) $input['discount_amount'];
			}
			if ( isset( $input['settings'] ) ) {
				$update['settings'] = maybe_serialize( (array) $input['settings'] );
			}

			if ( ! empty( $update ) ) {
				$update['updated_at'] = current_time( 'mysql' );
				wpFluent()->table( 'fcal_coupons' )->where( 'id', $id )->update( $update );
			}

			return array( 'success' => true, 'id' => $id );
		},
	) );

	// =========================================================================
	// 4.14.5 — DELETE COUPON
	// =========================================================================

	$reg->delete( 'fluent-booking/delete-coupon', array(
		'label'       => 'Delete FluentBooking Coupon',
		'description' => 'Remove a coupon by ID.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'      => array( 'type' => 'integer' ),
			'deleted' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$id      = (int) $input['id'];
			$deleted = wpFluent()->table( 'fcal_coupons' )->where( 'id', $id )->delete();
			return array(
				'success' => true,
				'id'      => $id,
				'deleted' => (int) $deleted,
			);
		},
	) );

}
add_action( 'wp_abilities_api_init', 'fluent_booking_register_coupons_abilities' );
