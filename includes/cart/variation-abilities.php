<?php
/**
 * FluentCart Abilities — Product Variations (P0)
 *
 * List, create, and update product variations (pricing plans).
 * Critical for subscription product management.
 *
 * 3 abilities in the 'fluent-cart' category.
 * Registered via Fluent_Abilities_Registrar.
 *
 * @package Fluent_Abilities
 * @since 1.9.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'cart' );

	// =========================================================================
	// PRODUCT VARIATIONS
	// =========================================================================

	$reg->read( 'fluent-cart/list-product-variations', array(
		'label'       => 'List Product Variations',
		'description' => 'List variations (pricing plans) for a FluentCart product. Includes pricing, billing config, stock, and SKU.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'product_id' ),
			'properties' => array_merge( array(
				'product_id' => array(
					'type'        => 'integer',
					'description' => 'Product ID (post_id)',
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'Filter by item_status: active, draft, inactive',
				),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'variations', array(
			'id'                     => array( 'type' => 'integer' ),
			'post_id'                => array( 'type' => 'integer' ),
			'variation_title'        => array( 'type' => 'string' ),
			'variation_identifier'   => array( 'type' => array( 'string', 'null' ) ),
			'sku'                    => array( 'type' => array( 'string', 'null' ) ),
			'item_price'             => array( 'type' => 'number' ),
			'item_cost'              => array( 'type' => 'number' ),
			'compare_price'          => array( 'type' => array( 'number', 'null' ) ),
			'payment_type'           => array( 'type' => array( 'string', 'null' ) ),
			'fulfillment_type'       => array( 'type' => array( 'string', 'null' ) ),
			'item_status'            => array( 'type' => array( 'string', 'null' ) ),
			'stock_status'           => array( 'type' => array( 'string', 'null' ) ),
			'total_stock'            => array( 'type' => array( 'integer', 'null' ) ),
			'available'              => array( 'type' => array( 'integer', 'null' ) ),
			'downloadable'           => array( 'type' => array( 'string', 'null' ) ),
			'serial_index'           => array( 'type' => array( 'integer', 'null' ) ),
			'created_at'             => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$query = \FluentCart\App\Models\ProductVariation::where( 'post_id', (int) $input['product_id'] );

			if ( ! empty( $input['status'] ) ) {
				$query->where( 'item_status', sanitize_text_field( $input['status'] ) );
			}

			$total      = $query->count();
			$variations = $query->orderBy( 'serial_index', 'ASC' )
				->offset( $pagination['offset'] )
				->limit( $pagination['per_page'] )
				->get();

			$items = array();
			foreach ( $variations as $v ) {
				$items[] = array(
					'id'                   => (int) $v->id,
					'post_id'              => (int) $v->post_id,
					'variation_title'      => (string) ( $v->variation_title ?? '' ),
					'variation_identifier' => $v->variation_identifier ?? null,
					'sku'                  => $v->sku ?? null,
					'item_price'           => fluent_cart_format_money( (int) $v->item_price ),
					'item_cost'            => fluent_cart_format_money( (int) $v->item_cost ),
					'compare_price'        => $v->compare_price !== null ? fluent_cart_format_money( (int) $v->compare_price ) : null,
					'payment_type'         => $v->payment_type ?? null,
					'fulfillment_type'     => $v->fulfillment_type ?? null,
					'item_status'          => $v->item_status ?? null,
					'stock_status'         => $v->stock_status ?? null,
					'total_stock'          => $v->total_stock !== null ? (int) $v->total_stock : null,
					'available'            => $v->available !== null ? (int) $v->available : null,
					'downloadable'         => $v->downloadable ?? null,
					'serial_index'         => $v->serial_index !== null ? (int) $v->serial_index : null,
					'created_at'           => $v->created_at ? (string) $v->created_at : null,
				);
			}

			return array(
				'variations' => $items,
				'total'      => $total,
				'page'       => $pagination['page'],
				'per_page'   => $pagination['per_page'],
			);
		},
	) );

	$reg->write( 'fluent-cart/create-product-variation', array(
		'label'       => 'Create Product Variation',
		'description' => 'Create a new variation (pricing plan) for a FluentCart product. Price in cents. Supports subscription billing config.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'product_id', 'variation_title', 'item_price' ),
			'properties' => array(
				'product_id'             => array( 'type' => 'integer', 'description' => 'Product ID (post_id)' ),
				'variation_title'        => array( 'type' => 'string', 'description' => 'Variation title (e.g., "Monthly Plan")' ),
				'variation_identifier'   => array( 'type' => 'string', 'description' => 'Unique identifier slug' ),
				'item_price'             => array( 'type' => 'integer', 'description' => 'Price in cents (e.g., 4999 = $49.99)' ),
				'compare_price'          => array( 'type' => 'integer', 'description' => 'Compare-at price in cents (for showing discount)' ),
				'sku'                    => array( 'type' => 'string', 'description' => 'SKU code' ),
				'payment_type'           => array( 'type' => 'string', 'description' => 'Payment type: one_time, subscription' ),
				'fulfillment_type'       => array( 'type' => 'string', 'description' => 'Fulfillment type: digital, physical' ),
				'item_status'            => array( 'type' => 'string', 'description' => 'Status: active, draft, inactive (default: active)' ),
				'stock_status'           => array( 'type' => 'string', 'description' => 'Stock status: in_stock, out_of_stock' ),
				'total_stock'            => array( 'type' => 'integer', 'description' => 'Total stock quantity' ),
				'manage_stock'           => array( 'type' => 'integer', 'description' => 'Enable stock management: 0 or 1' ),
				'downloadable'           => array( 'type' => 'string', 'description' => 'Is downloadable: yes, no' ),
				'serial_index'           => array( 'type' => 'integer', 'description' => 'Sort order index' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback'    => function( $input ) {
			// Verify product exists.
			$product = \FluentCart\App\Models\Product::find( (int) $input['product_id'] );
			if ( ! $product ) {
				return fluent_abilities_error( 'not_found', 'Product not found.' );
			}

			$data = array(
				'post_id'         => (int) $input['product_id'],
				'variation_title' => sanitize_text_field( $input['variation_title'] ),
				'item_price'      => (int) $input['item_price'],
				'item_cost'       => 0,
			);

			$optional_fields = array(
				'variation_identifier' => 'sanitize_text_field',
				'sku'                  => 'sanitize_text_field',
				'payment_type'         => 'sanitize_text_field',
				'fulfillment_type'     => 'sanitize_text_field',
				'item_status'          => 'sanitize_text_field',
				'stock_status'         => 'sanitize_text_field',
				'downloadable'         => 'sanitize_text_field',
			);

			foreach ( $optional_fields as $field => $sanitizer ) {
				if ( isset( $input[ $field ] ) ) {
					$data[ $field ] = $sanitizer( $input[ $field ] );
				}
			}

			$int_fields = array( 'compare_price', 'total_stock', 'manage_stock', 'serial_index' );
			foreach ( $int_fields as $field ) {
				if ( isset( $input[ $field ] ) ) {
					$data[ $field ] = (int) $input[ $field ];
				}
			}

			if ( ! isset( $data['item_status'] ) ) {
				$data['item_status'] = 'active';
			}

			$variation = \FluentCart\App\Models\ProductVariation::create( $data );

			return array(
				'success' => true,
				'id'      => (int) $variation->id,
				'title'   => $variation->variation_title,
			);
		},
	) );

	$reg->write( 'fluent-cart/update-product-variation', array(
		'label'       => 'Update Product Variation',
		'description' => 'Update an existing FluentCart product variation. Price in cents if provided.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'                     => array( 'type' => 'integer', 'description' => 'Variation ID' ),
				'variation_title'        => array( 'type' => 'string', 'description' => 'Variation title' ),
				'variation_identifier'   => array( 'type' => 'string', 'description' => 'Unique identifier slug' ),
				'item_price'             => array( 'type' => 'integer', 'description' => 'Price in cents' ),
				'compare_price'          => array( 'type' => 'integer', 'description' => 'Compare-at price in cents' ),
				'sku'                    => array( 'type' => 'string', 'description' => 'SKU code' ),
				'payment_type'           => array( 'type' => 'string', 'description' => 'Payment type: one_time, subscription' ),
				'fulfillment_type'       => array( 'type' => 'string', 'description' => 'Fulfillment type: digital, physical' ),
				'item_status'            => array( 'type' => 'string', 'description' => 'Status: active, draft, inactive' ),
				'stock_status'           => array( 'type' => 'string', 'description' => 'Stock status: in_stock, out_of_stock' ),
				'total_stock'            => array( 'type' => 'integer', 'description' => 'Total stock quantity' ),
				'manage_stock'           => array( 'type' => 'integer', 'description' => 'Enable stock management: 0 or 1' ),
				'downloadable'           => array( 'type' => 'string', 'description' => 'Is downloadable: yes, no' ),
				'serial_index'           => array( 'type' => 'integer', 'description' => 'Sort order index' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$variation = \FluentCart\App\Models\ProductVariation::find( (int) $input['id'] );
			if ( ! $variation ) {
				return fluent_abilities_error( 'not_found', 'Variation not found.' );
			}

			$string_fields = array(
				'variation_title', 'variation_identifier', 'sku',
				'payment_type', 'fulfillment_type', 'item_status',
				'stock_status', 'downloadable',
			);

			foreach ( $string_fields as $field ) {
				if ( isset( $input[ $field ] ) ) {
					$variation->$field = sanitize_text_field( $input[ $field ] );
				}
			}

			$int_fields = array( 'item_price', 'compare_price', 'total_stock', 'manage_stock', 'serial_index' );
			foreach ( $int_fields as $field ) {
				if ( isset( $input[ $field ] ) ) {
					$variation->$field = (int) $input[ $field ];
				}
			}

			$variation->save();

			return array( 'success' => true, 'id' => (int) $variation->id );
		},
	) );

	$reg->delete( 'fluent-cart/delete-product-variation', array(
		'label'       => 'Delete Product Variation',
		'description' => 'Delete a product variation (pricing plan) by ID.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Variation ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback'    => function( $input ) {
			$variation = \FluentCart\App\Models\ProductVariation::find( (int) $input['id'] );
			if ( ! $variation ) {
				return fluent_abilities_error( 'not_found', 'Variation not found.' );
			}

			$id = (int) $variation->id;
			$variation->delete();

			return array( 'success' => true, 'id' => $id );
		},
	) );

	$count = 4;
	error_log( "Abilities for Fluent: Registered {$count} Cart Variation abilities" );

}, 100 );
