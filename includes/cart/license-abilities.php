<?php
/**
 * FluentCart Abilities — Licensing (P0, Pro-gated)
 *
 * Software license management. Requires FluentCart Pro.
 *
 * 1 ability in the 'fluent-cart' category.
 * Registered via Fluent_Abilities_Registrar.
 *
 * @package Fluent_Abilities
 * @since 1.9.0
 */

defined( 'ABSPATH' ) || exit;

// Pro gate: skip entirely if FluentCart Pro licensing module is not available.
if ( ! class_exists( '\\FluentCartPro\\App\\Modules\\Licensing\\Models\\License' ) ) {
	return;
}

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'cart' );

	// =========================================================================
	// LICENSES — READ (Pro)
	// =========================================================================

	$reg->read( 'fluent-cart/list-licenses', array(
		'label'       => 'List Licenses',
		'description' => 'List FluentCart Pro software licenses with filtering by status, product, customer, and order. Requires FluentCart Pro.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'status' => array(
					'type'        => 'string',
					'description' => 'Filter by license status: active, expired, disabled, revoked',
				),
				'product_id' => array(
					'type'        => 'integer',
					'description' => 'Filter by product ID',
				),
				'customer_id' => array(
					'type'        => 'integer',
					'description' => 'Filter by customer ID',
				),
				'order_id' => array(
					'type'        => 'integer',
					'description' => 'Filter by order ID',
				),
				'search' => array(
					'type'        => 'string',
					'description' => 'Search by license key',
				),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'licenses', array(
			'id'               => array( 'type' => 'integer' ),
			'license_key'      => array( 'type' => array( 'string', 'null' ) ),
			'status'           => array( 'type' => array( 'string', 'null' ) ),
			'limit'            => array( 'type' => 'integer' ),
			'activation_count' => array( 'type' => 'integer' ),
			'product_id'       => array( 'type' => 'integer' ),
			'variation_id'     => array( 'type' => 'integer' ),
			'order_id'         => array( 'type' => 'integer' ),
			'customer_id'      => array( 'type' => 'integer' ),
			'subscription_id'  => array( 'type' => 'integer' ),
			'expiration_date'  => array( 'type' => array( 'string', 'null' ) ),
			'created_at'       => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$query = \FluentCartPro\App\Modules\Licensing\Models\License::query();

			if ( ! empty( $input['status'] ) ) {
				$query->where( 'status', sanitize_text_field( $input['status'] ) );
			}

			if ( ! empty( $input['product_id'] ) ) {
				$query->where( 'product_id', (int) $input['product_id'] );
			}

			if ( ! empty( $input['customer_id'] ) ) {
				$query->where( 'customer_id', (int) $input['customer_id'] );
			}

			if ( ! empty( $input['order_id'] ) ) {
				$query->where( 'order_id', (int) $input['order_id'] );
			}

			if ( ! empty( $input['search'] ) ) {
				$search = sanitize_text_field( $input['search'] );
				$query->where( 'license_key', 'LIKE', "%{$search}%" );
			}

			$total    = $query->count();
			$licenses = $query->orderBy( 'id', 'DESC' )
				->offset( $pagination['offset'] )
				->limit( $pagination['per_page'] )
				->get();

			$items = array();
			foreach ( $licenses as $license ) {
				$items[] = array(
					'id'               => (int) $license->id,
					'license_key'      => $license->license_key ?? null,
					'status'           => $license->status ?? null,
					'limit'            => (int) ( $license->limit ?? 0 ),
					'activation_count' => (int) ( $license->activation_count ?? 0 ),
					'product_id'       => (int) ( $license->product_id ?? 0 ),
					'variation_id'     => (int) ( $license->variation_id ?? 0 ),
					'order_id'         => (int) ( $license->order_id ?? 0 ),
					'customer_id'      => (int) ( $license->customer_id ?? 0 ),
					'subscription_id'  => (int) ( $license->subscription_id ?? 0 ),
					'expiration_date'  => $license->expiration_date ?? null,
					'created_at'       => $license->created_at ? (string) $license->created_at : null,
				);
			}

			return array(
				'licenses' => $items,
				'total'    => $total,
				'page'     => $pagination['page'],
				'per_page' => $pagination['per_page'],
			);
		},
	) );

	$count = 1;
	error_log( "Abilities for Fluent: Registered {$count} Cart License abilities (Pro)" );

}, 100 );
