<?php
/**
 * FluentCart Abilities — Tax Rates, Classes & EU VAT (v2.0.0)
 *
 * Adds cluster 4.17 from FluentCart Ability Registrar Research v1.0
 * (2026-05-13) — 7 abilities. Existing v1.1.3 list-tax-rates is preserved.
 *
 * @package Fluent_Abilities
 * @since 2.0.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'cart' );

	$reg->read( 'fluent-cart/list-tax-classes', array(
		'label'       => 'List Tax Classes',
		'description' => 'List FluentCart tax classes (fct_tax_classes). Mirrors GET /tax/classes.',
		'input_schema' => array(
			'type'     => 'object',
			'properties' => fluent_abilities_pagination_schema(),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'tax_classes', array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
			'slug'  => array( 'type' => 'string' ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$model      = '\\FluentCart\\App\\Models\\TaxClass';
			if ( ! class_exists( $model ) ) {
				return array( 'tax_classes' => array(), 'total' => 0, 'page' => $pagination['page'], 'per_page' => $pagination['per_page'] );
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
				'tax_classes' => $items,
				'total'       => $total,
				'page'        => $pagination['page'],
				'per_page'    => $pagination['per_page'],
			);
		},
	) );

	$reg->write( 'fluent-cart/create-tax-class', array(
		'label'       => 'Create Tax Class',
		'description' => 'Create a tax class. Mirrors POST /tax/classes.',
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
			$model = '\\FluentCart\\App\\Models\\TaxClass';
			if ( ! class_exists( $model ) ) {
				return fluent_abilities_error( 'not_found', 'TaxClass model not available.' );
			}
			$title = sanitize_text_field( $input['title'] );
			$slug  = ! empty( $input['slug'] ) ? sanitize_title( $input['slug'] ) : sanitize_title( $title );
			$row   = $model::create( array( 'title' => $title, 'slug' => $slug ) );
			return array( 'success' => true, 'id' => (int) $row->id );
		},
	) );

	$reg->write( 'fluent-cart/update-tax-class', array(
		'label'       => 'Update Tax Class',
		'description' => 'Update a tax class. Mirrors PUT /tax/classes/{id}.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'id' ),
			'properties' => array(
				'id'    => array( 'type' => 'integer' ),
				'title' => array( 'type' => 'string' ),
				'slug'  => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$model = '\\FluentCart\\App\\Models\\TaxClass';
			if ( ! class_exists( $model ) ) {
				return fluent_abilities_error( 'not_found', 'TaxClass model not available.' );
			}
			$row = $model::find( (int) $input['id'] );
			if ( ! $row ) {
				return fluent_abilities_error( 'not_found', 'Tax class not found.' );
			}
			if ( isset( $input['title'] ) ) {
				$row->title = sanitize_text_field( $input['title'] );
			}
			if ( isset( $input['slug'] ) ) {
				$row->slug = sanitize_title( $input['slug'] );
			}
			$row->save();
			return array( 'success' => true, 'id' => (int) $row->id );
		},
	) );

	$reg->delete( 'fluent-cart/delete-tax-class', array(
		'label'       => 'Delete Tax Class',
		'description' => 'Delete a tax class. Mirrors DELETE /tax/classes/{id}.',
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
			$model = '\\FluentCart\\App\\Models\\TaxClass';
			if ( ! class_exists( $model ) ) {
				return fluent_abilities_error( 'not_found', 'TaxClass model not available.' );
			}
			$row = $model::find( (int) $input['id'] );
			if ( ! $row ) {
				return fluent_abilities_error( 'not_found', 'Tax class not found.' );
			}
			$id = (int) $row->id;
			$row->delete();
			return array( 'success' => true, 'id' => $id );
		},
	) );

	$reg->write( 'fluent-cart/create-tax-rate', array(
		'label'       => 'Create Tax Rate',
		'description' => 'Create a tax rate, optionally scoped to country / state / postcode and a tax class. Mirrors POST /tax/country/rate.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'rate' ),
			'properties' => array(
				'rate'          => array( 'type' => 'number', 'description' => 'Tax rate (percentage)' ),
				'tax_class_id'  => array( 'type' => 'integer' ),
				'country'       => array( 'type' => 'string', 'description' => 'ISO 3166-1 alpha-2' ),
				'state'         => array( 'type' => 'string' ),
				'postcode'      => array( 'type' => 'string' ),
				'name'          => array( 'type' => 'string' ),
				'compound'      => array( 'type' => 'boolean' ),
				'shipping'      => array( 'type' => 'boolean' ),
				'priority'      => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'capability'  => 'manage_options',
		'callback'    => function( $input ) {
			$model = '\\FluentCart\\App\\Models\\TaxRate';
			if ( ! class_exists( $model ) ) {
				return fluent_abilities_error( 'not_found', 'TaxRate model not available.' );
			}
			$data = array( 'rate' => (float) $input['rate'] );
			foreach ( array( 'country', 'state', 'postcode', 'name' ) as $f ) {
				if ( isset( $input[ $f ] ) ) {
					$data[ $f ] = sanitize_text_field( $input[ $f ] );
				}
			}
			foreach ( array( 'tax_class_id', 'priority' ) as $f ) {
				if ( isset( $input[ $f ] ) ) {
					$data[ $f ] = (int) $input[ $f ];
				}
			}
			foreach ( array( 'compound', 'shipping' ) as $f ) {
				if ( isset( $input[ $f ] ) ) {
					$data[ $f ] = ! empty( $input[ $f ] ) ? 1 : 0;
				}
			}
			$row = $model::create( $data );
			return array( 'success' => true, 'id' => (int) $row->id );
		},
	) );

	$reg->read( 'fluent-cart/get-eu-vat-rates', array(
		'label'       => 'Get EU VAT Rates',
		'description' => 'Return the full EU VAT rate matrix. Mirrors GET /tax/configuration/settings/eu-vat/rates.',
		'input_schema' => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'rates' => array( 'type' => 'object', 'description' => 'Country code => rate' ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$rates = get_option( 'fluent_cart_eu_vat_rates', array() );
			if ( ! is_array( $rates ) ) {
				$rates = array();
			}
			return array( 'rates' => $rates );
		},
	) );

	$reg->write( 'fluent-cart/update-eu-vat-config', array(
		'label'       => 'Update EU VAT Configuration',
		'description' => 'Update EU VAT module configuration (rate matrix + OSS-shipping override). Mirrors POST /tax/configuration/settings/eu-vat.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'config' ),
			'properties' => array(
				'config' => array(
					'type'        => 'object',
					'description' => 'Full EU VAT config: enabled, oss_shipping, rates (per-country override)',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'option_key' => array( 'type' => 'string' ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$config = (array) $input['config'];
			update_option( 'fluent_cart_eu_vat_config', $config );
			if ( isset( $config['rates'] ) && is_array( $config['rates'] ) ) {
				update_option( 'fluent_cart_eu_vat_rates', $config['rates'] );
			}
			return array( 'success' => true, 'option_key' => 'fluent_cart_eu_vat_config' );
		},
	) );

	$count = 7;
	error_log( "Abilities for Fluent: Registered {$count} Cart Tax abilities" );

}, 100 );
