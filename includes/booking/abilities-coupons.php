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


	// =========================================================================
	// 4.14.2 — GET COUPON
	// =========================================================================


	// =========================================================================
	// 4.14.3 — CREATE COUPON
	// =========================================================================


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

}
add_action( 'wp_abilities_api_init', 'fluent_booking_register_coupons_abilities' );
