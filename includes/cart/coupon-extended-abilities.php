<?php
/**
 * FluentCart Abilities — Coupon Application & Settings (v2.0.0)
 *
 * Adds cluster 4.13 from FluentCart Ability Registrar Research v1.0
 * (2026-05-13) — 5 abilities (cart-side coupon application + admin settings).
 *
 * NOTE on KD-2: the existing v1.1.3 create-coupon ability uses the wrong
 * column names (discount_type / description / usage_limit / expires_at).
 * That defect is PRESERVED per Stable Contracts; new abilities here operate
 * via the AppliedCoupon model + native helpers, not raw column writes.
 *
 * @package Fluent_Abilities
 * @since 2.0.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'cart' );

	$reg->write( 'fluent-cart/apply-coupon', array(
		'label'       => 'Apply Coupon To Cart',
		'description' => 'Apply a coupon code to a cart session (by cart_hash). Mirrors POST /coupons/apply.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'cart_hash', 'code' ),
			'properties' => array(
				'cart_hash' => array( 'type' => 'string' ),
				'code'      => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'cart_hash' => array( 'type' => 'string' ),
			'code'      => array( 'type' => 'string' ),
			'coupon_id' => array( 'type' => array( 'integer', 'null' ) ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback'    => function( $input ) {
			$cart_hash = sanitize_text_field( $input['cart_hash'] );
			$code      = sanitize_text_field( $input['code'] );
			$coupon    = \FluentCart\App\Models\Coupon::where( 'code', $code )->first();
			if ( ! $coupon ) {
				return fluent_abilities_error( 'not_found', 'Coupon not found.' );
			}
			$cart = \FluentCart\App\Models\Cart::where( 'cart_hash', $cart_hash )->first();
			if ( ! $cart ) {
				return fluent_abilities_error( 'not_found', 'Cart session not found.' );
			}
			$coupons = is_string( $cart->coupons ) ? json_decode( $cart->coupons, true ) : ( $cart->coupons ?? array() );
			$coupons = is_array( $coupons ) ? $coupons : array();
			$coupons[ $code ] = array( 'id' => (int) $coupon->id, 'code' => $code );
			$cart->coupons = wp_json_encode( $coupons );
			$cart->save();
			return array(
				'success'   => true,
				'cart_hash' => $cart_hash,
				'code'      => $code,
				'coupon_id' => (int) $coupon->id,
			);
		},
	) );

	$reg->write( 'fluent-cart/cancel-coupon', array(
		'label'       => 'Cancel Coupon On Cart',
		'description' => 'Remove a coupon from a cart session. Mirrors POST /coupons/cancel.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'cart_hash', 'code' ),
			'properties' => array(
				'cart_hash' => array( 'type' => 'string' ),
				'code'      => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'cart_hash' => array( 'type' => 'string' ),
			'code'      => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$cart_hash = sanitize_text_field( $input['cart_hash'] );
			$code      = sanitize_text_field( $input['code'] );
			$cart      = \FluentCart\App\Models\Cart::where( 'cart_hash', $cart_hash )->first();
			if ( ! $cart ) {
				return fluent_abilities_error( 'not_found', 'Cart session not found.' );
			}
			$coupons = is_string( $cart->coupons ) ? json_decode( $cart->coupons, true ) : ( $cart->coupons ?? array() );
			$coupons = is_array( $coupons ) ? $coupons : array();
			unset( $coupons[ $code ] );
			$cart->coupons = wp_json_encode( $coupons );
			$cart->save();
			return array( 'success' => true, 'cart_hash' => $cart_hash, 'code' => $code );
		},
	) );

	$reg->write( 'fluent-cart/reapply-coupon', array(
		'label'       => 'Re-apply Coupon',
		'description' => 'Re-apply a previously canceled coupon to a cart session. Mirrors POST /coupons/re-apply.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'cart_hash', 'code' ),
			'properties' => array(
				'cart_hash' => array( 'type' => 'string' ),
				'code'      => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'cart_hash' => array( 'type' => 'string' ),
			'code'      => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$cart_hash = sanitize_text_field( $input['cart_hash'] );
			$code      = sanitize_text_field( $input['code'] );
			$coupon    = \FluentCart\App\Models\Coupon::where( 'code', $code )->first();
			if ( ! $coupon ) {
				return fluent_abilities_error( 'not_found', 'Coupon not found.' );
			}
			$cart = \FluentCart\App\Models\Cart::where( 'cart_hash', $cart_hash )->first();
			if ( ! $cart ) {
				return fluent_abilities_error( 'not_found', 'Cart session not found.' );
			}
			$coupons = is_string( $cart->coupons ) ? json_decode( $cart->coupons, true ) : ( $cart->coupons ?? array() );
			$coupons = is_array( $coupons ) ? $coupons : array();
			$coupons[ $code ] = array( 'id' => (int) $coupon->id, 'code' => $code );
			$cart->coupons = wp_json_encode( $coupons );
			$cart->save();
			return array( 'success' => true, 'cart_hash' => $cart_hash, 'code' => $code );
		},
	) );

	$reg->write( 'fluent-cart/check-coupon-product-eligibility', array(
		'label'       => 'Check Coupon Product Eligibility',
		'description' => 'Evaluate whether a coupon applies to a given product set. Mirrors POST /coupons/checkProductEligibility.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'code', 'product_ids' ),
			'properties' => array(
				'code'        => array( 'type' => 'string' ),
				'product_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'code'                 => array( 'type' => 'string' ),
			'eligible_product_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			'ineligible_count'     => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$code   = sanitize_text_field( $input['code'] );
			$coupon = \FluentCart\App\Models\Coupon::where( 'code', $code )->first();
			if ( ! $coupon ) {
				return fluent_abilities_error( 'not_found', 'Coupon not found.' );
			}
			$ids        = array_map( 'intval', (array) $input['product_ids'] );
			$conditions = is_string( $coupon->conditions ) ? json_decode( $coupon->conditions, true ) : ( $coupon->conditions ?? array() );
			$allowed    = isset( $conditions['product_ids'] ) && is_array( $conditions['product_ids'] )
				? array_map( 'intval', $conditions['product_ids'] )
				: $ids;
			$eligible   = array_values( array_intersect( $ids, $allowed ) );
			$ineligible = count( $ids ) - count( $eligible );
			return array(
				'success'              => true,
				'code'                 => $code,
				'eligible_product_ids' => $eligible,
				'ineligible_count'     => $ineligible,
			);
		},
	) );

	$reg->read( 'fluent-cart/get-coupon-settings', array(
		'label'       => 'Get Coupon Settings',
		'description' => 'Get admin-level coupon configuration. Mirrors GET /coupons/getSettings.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'settings' => array( 'type' => 'object' ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$settings = get_option( 'fluent_cart_coupon_settings', array() );
			if ( ! is_array( $settings ) ) {
				$settings = array();
			}
			return array( 'settings' => $settings );
		},
	) );

	$count = 5;
	error_log( "Abilities for Fluent: Registered {$count} Cart Coupon Extended abilities" );

}, 100 );
