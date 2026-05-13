<?php
/**
 * FluentCart Abilities — Shipping Zones, Methods & Classes (v2.0.0)
 *
 * Adds cluster 4.18 from FluentCart Ability Registrar Research v1.0
 * (2026-05-13) — 8 abilities.
 *
 * KD-2 note: existing v1.1.3 list-shipping-methods reads a non-existent
 * `cost` column. The verified column is `amount` (DECIMAL(10,2) after
 * ShippingMethodsMigrator::changeAmountToDecimal). New abilities use
 * `amount` directly.
 *
 * @package Fluent_Abilities
 * @since 2.0.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'cart' );

	$reg->read( 'fluent-cart/list-shipping-zones', array(
		'label'       => 'List Shipping Zones',
		'description' => 'List FluentCart shipping zones (fct_shipping_zones). Mirrors GET /shipping/zones.',
		'input_schema' => array(
			'type'     => 'object',
			'properties' => fluent_abilities_pagination_schema(),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'zones', array(
			'id'       => array( 'type' => 'integer' ),
			'title'    => array( 'type' => 'string' ),
			'regions'  => array( 'type' => array( 'array', 'string', 'null' ) ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$model      = '\\FluentCart\\App\\Models\\ShippingZone';
			if ( ! class_exists( $model ) ) {
				return array( 'zones' => array(), 'total' => 0, 'page' => $pagination['page'], 'per_page' => $pagination['per_page'] );
			}
			$query = $model::query();
			$total = $query->count();
			$rows  = $query->orderBy( 'id', 'DESC' )
				->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();
			$items = array();
			foreach ( $rows as $z ) {
				$items[] = array(
					'id'      => (int) $z->id,
					'title'   => (string) ( $z->title ?? '' ),
					'regions' => $z->regions ?? null,
				);
			}
			return array(
				'zones'    => $items,
				'total'    => $total,
				'page'     => $pagination['page'],
				'per_page' => $pagination['per_page'],
			);
		},
	) );

	$reg->write( 'fluent-cart/create-shipping-zone', array(
		'label'       => 'Create Shipping Zone',
		'description' => 'Create a shipping zone. Mirrors POST /shipping/zones.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'title' ),
			'properties' => array(
				'title'   => array( 'type' => 'string' ),
				'regions' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'capability'  => 'manage_options',
		'callback'    => function( $input ) {
			$model = '\\FluentCart\\App\\Models\\ShippingZone';
			if ( ! class_exists( $model ) ) {
				return fluent_abilities_error( 'not_found', 'ShippingZone model not available.' );
			}
			$data = array( 'title' => sanitize_text_field( $input['title'] ) );
			if ( isset( $input['regions'] ) ) {
				$data['regions'] = wp_json_encode( array_map( 'sanitize_text_field', (array) $input['regions'] ) );
			}
			$row = $model::create( $data );
			return array( 'success' => true, 'id' => (int) $row->id );
		},
	) );

	$reg->write( 'fluent-cart/update-shipping-zone', array(
		'label'       => 'Update Shipping Zone',
		'description' => 'Update a shipping zone. Mirrors PUT /shipping/zones/{id}.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'id' ),
			'properties' => array(
				'id'      => array( 'type' => 'integer' ),
				'title'   => array( 'type' => 'string' ),
				'regions' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$model = '\\FluentCart\\App\\Models\\ShippingZone';
			if ( ! class_exists( $model ) ) {
				return fluent_abilities_error( 'not_found', 'ShippingZone model not available.' );
			}
			$row = $model::find( (int) $input['id'] );
			if ( ! $row ) {
				return fluent_abilities_error( 'not_found', 'Shipping zone not found.' );
			}
			if ( isset( $input['title'] ) ) {
				$row->title = sanitize_text_field( $input['title'] );
			}
			if ( isset( $input['regions'] ) ) {
				$row->regions = wp_json_encode( array_map( 'sanitize_text_field', (array) $input['regions'] ) );
			}
			$row->save();
			return array( 'success' => true, 'id' => (int) $row->id );
		},
	) );

	$reg->delete( 'fluent-cart/delete-shipping-zone', array(
		'label'       => 'Delete Shipping Zone',
		'description' => 'Delete a shipping zone. Cascades to fct_shipping_methods. Mirrors DELETE /shipping/zones/{id}.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'capability'  => 'manage_options',
		'callback'    => function( $input ) {
			$zone_model   = '\\FluentCart\\App\\Models\\ShippingZone';
			$method_model = '\\FluentCart\\App\\Models\\ShippingMethod';
			if ( ! class_exists( $zone_model ) ) {
				return fluent_abilities_error( 'not_found', 'ShippingZone model not available.' );
			}
			$row = $zone_model::find( (int) $input['id'] );
			if ( ! $row ) {
				return fluent_abilities_error( 'not_found', 'Shipping zone not found.' );
			}
			$id = (int) $row->id;
			if ( class_exists( $method_model ) ) {
				$method_model::where( 'zone_id', $id )->delete();
			}
			$row->delete();
			return array( 'success' => true, 'id' => $id );
		},
	) );

	$reg->write( 'fluent-cart/create-shipping-method', array(
		'label'       => 'Create Shipping Method',
		'description' => 'Create a shipping method within a zone. Note: fct_shipping_methods uses `amount` DECIMAL(10,2) (not `cost`). Mirrors POST /shipping/methods.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'zone_id', 'title', 'type' ),
			'properties' => array(
				'zone_id' => array( 'type' => 'integer' ),
				'title'   => array( 'type' => 'string' ),
				'type'    => array( 'type' => 'string', 'description' => 'Method type: flat_rate, free_shipping, local_pickup, ...' ),
				'amount'  => array( 'type' => 'number', 'description' => 'Decimal amount (NOT cents; fct_shipping_methods.amount is DECIMAL)' ),
				'settings' => array( 'type' => 'object' ),
				'states'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'capability'  => 'manage_options',
		'callback'    => function( $input ) {
			$model = '\\FluentCart\\App\\Models\\ShippingMethod';
			if ( ! class_exists( $model ) ) {
				return fluent_abilities_error( 'not_found', 'ShippingMethod model not available.' );
			}
			$data = array(
				'zone_id' => (int) $input['zone_id'],
				'title'   => sanitize_text_field( $input['title'] ),
				'type'    => sanitize_text_field( $input['type'] ),
				'amount'  => isset( $input['amount'] ) ? (float) $input['amount'] : 0,
			);
			if ( isset( $input['settings'] ) ) {
				$data['settings'] = wp_json_encode( (array) $input['settings'] );
			}
			if ( isset( $input['states'] ) ) {
				$data['states'] = wp_json_encode( array_map( 'sanitize_text_field', (array) $input['states'] ) );
			}
			$row = $model::create( $data );
			return array( 'success' => true, 'id' => (int) $row->id );
		},
	) );

	$reg->write( 'fluent-cart/update-shipping-method', array(
		'label'       => 'Update Shipping Method',
		'description' => 'Update a shipping method. Mirrors PUT /shipping/methods.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'id' ),
			'properties' => array(
				'id'       => array( 'type' => 'integer' ),
				'title'    => array( 'type' => 'string' ),
				'type'     => array( 'type' => 'string' ),
				'amount'   => array( 'type' => 'number' ),
				'settings' => array( 'type' => 'object' ),
				'states'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$model = '\\FluentCart\\App\\Models\\ShippingMethod';
			if ( ! class_exists( $model ) ) {
				return fluent_abilities_error( 'not_found', 'ShippingMethod model not available.' );
			}
			$row = $model::find( (int) $input['id'] );
			if ( ! $row ) {
				return fluent_abilities_error( 'not_found', 'Shipping method not found.' );
			}
			foreach ( array( 'title', 'type' ) as $f ) {
				if ( isset( $input[ $f ] ) ) {
					$row->{$f} = sanitize_text_field( $input[ $f ] );
				}
			}
			if ( isset( $input['amount'] ) ) {
				$row->amount = (float) $input['amount'];
			}
			if ( isset( $input['settings'] ) ) {
				$row->settings = wp_json_encode( (array) $input['settings'] );
			}
			if ( isset( $input['states'] ) ) {
				$row->states = wp_json_encode( array_map( 'sanitize_text_field', (array) $input['states'] ) );
			}
			$row->save();
			return array( 'success' => true, 'id' => (int) $row->id );
		},
	) );

	$reg->read( 'fluent-cart/list-shipping-classes', array(
		'label'       => 'List Shipping Classes',
		'description' => 'List FluentCart shipping classes (fct_shipping_classes). Mirrors GET /shipping/classes.',
		'input_schema' => array(
			'type'     => 'object',
			'properties' => fluent_abilities_pagination_schema(),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'shipping_classes', array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
			'slug'  => array( 'type' => 'string' ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$model      = '\\FluentCart\\App\\Models\\ShippingClass';
			if ( ! class_exists( $model ) ) {
				return array( 'shipping_classes' => array(), 'total' => 0, 'page' => $pagination['page'], 'per_page' => $pagination['per_page'] );
			}
			$query = $model::query();
			$total = $query->count();
			$rows  = $query->orderBy( 'id', 'DESC' )
				->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();
			$items = array();
			foreach ( $rows as $c ) {
				$items[] = array(
					'id'    => (int) $c->id,
					'title' => (string) ( $c->title ?? '' ),
					'slug'  => (string) ( $c->slug ?? '' ),
				);
			}
			return array(
				'shipping_classes' => $items,
				'total'            => $total,
				'page'             => $pagination['page'],
				'per_page'         => $pagination['per_page'],
			);
		},
	) );

	$reg->write( 'fluent-cart/create-shipping-class', array(
		'label'       => 'Create Shipping Class',
		'description' => 'Create a shipping class. Mirrors POST /shipping/classes.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'title' ),
			'properties' => array(
				'title' => array( 'type' => 'string' ),
				'slug'  => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'capability'  => 'manage_options',
		'callback'    => function( $input ) {
			$model = '\\FluentCart\\App\\Models\\ShippingClass';
			if ( ! class_exists( $model ) ) {
				return fluent_abilities_error( 'not_found', 'ShippingClass model not available.' );
			}
			$title = sanitize_text_field( $input['title'] );
			$slug  = ! empty( $input['slug'] ) ? sanitize_title( $input['slug'] ) : sanitize_title( $title );
			$row   = $model::create( array( 'title' => $title, 'slug' => $slug ) );
			return array( 'success' => true, 'id' => (int) $row->id );
		},
	) );

	$count = 8;
	error_log( "Abilities for Fluent: Registered {$count} Cart Shipping abilities" );

}, 100 );
