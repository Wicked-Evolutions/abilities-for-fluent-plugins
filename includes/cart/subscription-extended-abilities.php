<?php
/**
 * FluentCart Abilities — Subscription Lifecycle Extended (v2.0.0)
 *
 * Adds cluster 4.7 from FluentCart Ability Registrar Research v1.0 (2026-05-13)
 * — 4 abilities. Existing v1.1.3 covers cancel/pause/resume/update; this file
 * adds reactivate, vendor fetch, early-payment-link, and cancel-auto-renew.
 *
 * fct_subscriptions schema verified: signup_fee (renamed from initial_amount),
 * recurring_amount, bill_times / bill_count, canceled_at / restored_at,
 * next_billing_date, vendor_subscription_id.
 *
 * @package Fluent_Abilities
 * @since 2.0.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'cart' );

	$reg->write( 'fluent-cart/reactivate-subscription', array(
		'label'       => 'Reactivate Subscription',
		'description' => 'Reactivate a canceled or expired subscription (distinct from resume — restores after expiration). Mirrors PUT /orders/{order}/subscriptions/{subscription}/reactivate. Sets restored_at; clears canceled_at; flips status to active.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Subscription ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'           => array( 'type' => 'integer' ),
			'status'       => array( 'type' => 'string' ),
			'restored_at'  => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$sub = \FluentCart\App\Models\Subscription::find( (int) $input['id'] );
			if ( ! $sub ) {
				return fluent_abilities_error( 'not_found', 'Subscription not found.' );
			}
			$now              = current_time( 'mysql' );
			$sub->status      = 'active';
			$sub->restored_at = $now;
			$sub->canceled_at = null;
			$sub->save();
			do_action( 'fluent_cart/subscription_reactivated', $sub );
			return array(
				'success'     => true,
				'id'          => (int) $sub->id,
				'status'      => (string) $sub->status,
				'restored_at' => $now,
			);
		},
	) );

	$reg->write( 'fluent-cart/fetch-subscription-from-vendor', array(
		'label'       => 'Fetch Subscription From Vendor',
		'description' => 'Sync vendor-of-record state (Stripe / PayPal / etc.) into the local subscription record. Mirrors PUT /orders/{order}/subscriptions/{subscription}/fetch. Returns the updated local record.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Subscription ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'                     => array( 'type' => 'integer' ),
			'vendor_subscription_id' => array( 'type' => array( 'string', 'null' ) ),
			'status'                 => array( 'type' => 'string' ),
			'next_billing_date'      => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$sub = \FluentCart\App\Models\Subscription::find( (int) $input['id'] );
			if ( ! $sub ) {
				return fluent_abilities_error( 'not_found', 'Subscription not found.' );
			}
			// Allow vendor sync via action — payment-method modules listen and update the record.
			do_action( 'fluent_cart/fetch_subscription_from_vendor', $sub );
			$sub = $sub->fresh();
			return array(
				'success'                => true,
				'id'                     => (int) $sub->id,
				'vendor_subscription_id' => $sub->vendor_subscription_id ?? null,
				'status'                 => (string) ( $sub->status ?? '' ),
				'next_billing_date'      => $sub->next_billing_date ?? null,
			);
		},
	) );

	$reg->write( 'fluent-cart/generate-subscription-early-payment-link', array(
		'label'       => 'Generate Subscription Early-Payment Link',
		'description' => 'Generate a checkout link that lets a customer pay the next subscription installment ahead of next_billing_date. Mirrors POST /orders/{order}/subscriptions/{subscription}/early-payment-link.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'id' ),
			'properties' => array(
				'id'           => array( 'type' => 'integer', 'description' => 'Subscription ID' ),
				'expires_in'   => array( 'type' => 'integer', 'description' => 'Link TTL in seconds (default: 86400)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'   => array( 'type' => 'integer' ),
			'url'  => array( 'type' => 'string' ),
			'hash' => array( 'type' => 'string' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback'    => function( $input ) {
			$sub = \FluentCart\App\Models\Subscription::find( (int) $input['id'] );
			if ( ! $sub ) {
				return fluent_abilities_error( 'not_found', 'Subscription not found.' );
			}
			$hash = wp_generate_password( 32, false );
			$ttl  = max( 60, (int) ( $input['expires_in'] ?? 86400 ) );
			set_transient( 'fct_early_payment_' . $hash, array( 'subscription_id' => $sub->id ), $ttl );
			$url = add_query_arg(
				array(
					'fct_early_payment' => $hash,
				),
				home_url( '/checkout/' )
			);
			return array(
				'success' => true,
				'id'      => (int) $sub->id,
				'url'     => esc_url_raw( $url ),
				'hash'    => $hash,
			);
		},
	) );

	$reg->write( 'fluent-cart/cancel-subscription-auto-renew', array(
		'label'       => 'Cancel Subscription Auto-Renew',
		'description' => 'Customer-facing variant: stop automatic renewal but leave the subscription active until next_billing_date. Mirrors POST /customer-profile/subscriptions/{uuid}/cancel-auto-renew. Uses bill_times to cap renewals at bill_count.',
		'input_schema' => array(
			'type'     => 'object',
			'properties' => array(
				'id'   => array( 'type' => 'integer', 'description' => 'Subscription ID' ),
				'uuid' => array( 'type' => 'string', 'description' => 'Subscription UUID (alternative to id)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'         => array( 'type' => 'integer' ),
			'bill_times' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			if ( ! empty( $input['id'] ) ) {
				$sub = \FluentCart\App\Models\Subscription::find( (int) $input['id'] );
			} elseif ( ! empty( $input['uuid'] ) ) {
				$sub = \FluentCart\App\Models\Subscription::where( 'uuid', sanitize_text_field( $input['uuid'] ) )->first();
			} else {
				return fluent_abilities_error( 'ability_invalid_input', 'Provide either id or uuid.' );
			}
			if ( ! $sub ) {
				return fluent_abilities_error( 'not_found', 'Subscription not found.' );
			}
			$sub->bill_times = (int) ( $sub->bill_count ?? 0 );
			$sub->save();
			do_action( 'fluent_cart/subscription_auto_renew_canceled', $sub );
			return array(
				'success'    => true,
				'id'         => (int) $sub->id,
				'bill_times' => (int) $sub->bill_times,
			);
		},
	) );

	$count = 4;
	error_log( "Abilities for Fluent: Registered {$count} Cart Subscription Extended abilities" );

}, 100 );
