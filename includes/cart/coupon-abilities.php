<?php
/**
 * FluentCart Abilities — Coupon Read (P0)
 *
 * Fixes broken CRUD surface: create/update/delete existed without list/get.
 *
 * 2 abilities in the 'fluent-cart' category.
 * Registered via Fluent_Abilities_Registrar.
 *
 * @package Fluent_Abilities
 * @since 1.9.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'cart' );

	// =========================================================================
	// COUPONS — READ
	// =========================================================================

	$reg->read( 'fluent-cart/list-coupons', array(
		'label'       => 'List Coupons',
		'description' => 'List FluentCart coupons with filtering by status, type, and search. Returns code, discount details, and usage stats.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'search' => array(
					'type'        => 'string',
					'description' => 'Search by coupon code or title',
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'Filter by status: active, inactive, expired',
				),
				'type' => array(
					'type'        => 'string',
					'description' => 'Filter by discount type: percent, fixed',
				),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'coupons', array(
			'id'               => array( 'type' => 'integer' ),
			'title'            => array( 'type' => 'string' ),
			'code'             => array( 'type' => 'string' ),
			'type'             => array( 'type' => 'string' ),
			'amount'           => array( 'type' => 'number' ),
			'status'           => array( 'type' => 'string' ),
			'use_count'        => array( 'type' => array( 'integer', 'null' ) ),
			'stackable'        => array( 'type' => 'string' ),
			'show_on_checkout' => array( 'type' => 'string' ),
			'start_date'       => array( 'type' => array( 'string', 'null' ) ),
			'end_date'         => array( 'type' => array( 'string', 'null' ) ),
			'created_at'       => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$query = \FluentCart\App\Models\Coupon::query();

			if ( ! empty( $input['search'] ) ) {
				$search = sanitize_text_field( $input['search'] );
				$query->where( function( $q ) use ( $search ) {
					$q->where( 'code', 'LIKE', "%{$search}%" )
					  ->orWhere( 'title', 'LIKE', "%{$search}%" );
				} );
			}

			if ( ! empty( $input['status'] ) ) {
				$query->where( 'status', sanitize_text_field( $input['status'] ) );
			}

			if ( ! empty( $input['type'] ) ) {
				$query->where( 'type', sanitize_text_field( $input['type'] ) );
			}

			$total   = $query->count();
			$coupons = $query->orderBy( 'id', 'DESC' )
				->offset( $pagination['offset'] )
				->limit( $pagination['per_page'] )
				->get();

			$items = array();
			foreach ( $coupons as $coupon ) {
				$items[] = array(
					'id'               => (int) $coupon->id,
					'title'            => (string) ( $coupon->title ?? '' ),
					'code'             => (string) ( $coupon->code ?? '' ),
					'type'             => (string) ( $coupon->type ?? '' ),
					'amount'           => (float) ( $coupon->amount ?? 0 ),
					'status'           => (string) ( $coupon->status ?? '' ),
					'use_count'        => $coupon->use_count !== null ? (int) $coupon->use_count : null,
					'stackable'        => (string) ( $coupon->stackable ?? '' ),
					'show_on_checkout' => (string) ( $coupon->show_on_checkout ?? '' ),
					'start_date'       => $coupon->start_date ?? null,
					'end_date'         => $coupon->end_date ?? null,
					'created_at'       => $coupon->created_at ? (string) $coupon->created_at : null,
				);
			}

			return array(
				'coupons'  => $items,
				'total'    => $total,
				'page'     => $pagination['page'],
				'per_page' => $pagination['per_page'],
			);
		},
	) );

	$reg->read( 'fluent-cart/get-coupon', array(
		'label'       => 'Get Coupon',
		'description' => 'Get a single FluentCart coupon by ID or code, including conditions, usage stats, and date restrictions.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Coupon ID',
				),
				'code' => array(
					'type'        => 'string',
					'description' => 'Coupon code (alternative to ID)',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'               => array( 'type' => 'integer' ),
			'title'            => array( 'type' => 'string' ),
			'code'             => array( 'type' => 'string' ),
			'type'             => array( 'type' => 'string' ),
			'amount'           => array( 'type' => 'number' ),
			'status'           => array( 'type' => 'string' ),
			'priority'         => array( 'type' => array( 'integer', 'null' ) ),
			'conditions'       => array( 'type' => array( 'object', 'array', 'string', 'null' ) ),
			'use_count'        => array( 'type' => array( 'integer', 'null' ) ),
			'stackable'        => array( 'type' => 'string' ),
			'show_on_checkout' => array( 'type' => 'string' ),
			'notes'            => array( 'type' => 'string' ),
			'start_date'       => array( 'type' => array( 'string', 'null' ) ),
			'end_date'         => array( 'type' => array( 'string', 'null' ) ),
			'created_at'       => array( 'type' => array( 'string', 'null' ) ),
			'updated_at'       => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! empty( $input['id'] ) ) {
				$coupon = \FluentCart\App\Models\Coupon::find( (int) $input['id'] );
			} elseif ( ! empty( $input['code'] ) ) {
				$coupon = \FluentCart\App\Models\Coupon::where( 'code', sanitize_text_field( $input['code'] ) )->first();
			} else {
				return fluent_abilities_error( 'ability_invalid_input', 'Provide either id or code' );
			}

			if ( ! $coupon ) {
				return fluent_abilities_error( 'not_found', 'Coupon not found' );
			}

			return array(
				'id'               => (int) $coupon->id,
				'title'            => (string) ( $coupon->title ?? '' ),
				'code'             => (string) ( $coupon->code ?? '' ),
				'type'             => (string) ( $coupon->type ?? '' ),
				'amount'           => (float) ( $coupon->amount ?? 0 ),
				'status'           => (string) ( $coupon->status ?? '' ),
				'priority'         => $coupon->priority !== null ? (int) $coupon->priority : null,
				'conditions'       => $coupon->conditions ?? null,
				'use_count'        => $coupon->use_count !== null ? (int) $coupon->use_count : null,
				'stackable'        => (string) ( $coupon->stackable ?? '' ),
				'show_on_checkout' => (string) ( $coupon->show_on_checkout ?? '' ),
				'notes'            => (string) ( $coupon->notes ?? '' ),
				'start_date'       => $coupon->start_date ?? null,
				'end_date'         => $coupon->end_date ?? null,
				'created_at'       => $coupon->created_at ? (string) $coupon->created_at : null,
				'updated_at'       => $coupon->updated_at ? (string) $coupon->updated_at : null,
			);
		},
	) );

	$count = 2;
	error_log( "Abilities for Fluent: Registered {$count} Cart Coupon abilities" );

}, 100 );
