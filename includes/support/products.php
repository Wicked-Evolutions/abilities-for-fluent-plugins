<?php
/**
 * Fluent Support — Product Abilities
 *
 * 5 abilities: list/get/create/update/delete product.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function () {

	$reg = new Fluent_Abilities_Registrar( 'support' );

	// =========================================================================
	// PRODUCTS
	// =========================================================================

	$reg->read( 'fluent-support/list-products', array(
		'label'       => 'List Support Products',
		'description' => 'List all support products.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type' => 'object',
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'products', array(
			'id'          => array( 'type' => 'integer' ),
			'title'       => array( 'type' => array( 'string', 'null' ) ),
			'description' => array( 'type' => array( 'string', 'null' ) ),
			'source'      => array( 'type' => array( 'string', 'null' ) ),
			'mailbox_id'  => array( 'type' => array( 'integer', 'null' ) ),
			'created_at'  => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function ( $input ) {
			$products = wpFluent()->table( 'fs_products' )
				->orderBy( 'title', 'ASC' )
				->get();

			$items = array();
			foreach ( $products as $product ) {
				$items[] = array(
					'id'          => (int) $product->id,
					'title'       => $product->title,
					'description' => $product->description,
					'source'      => $product->source,
					'mailbox_id'  => $product->mailbox_id ? (int) $product->mailbox_id : null,
					'created_at'  => $product->created_at ? (string) $product->created_at : null,
				);
			}

			return array( 'products' => $items, 'total' => count( $items ) );
		},
	) );

	$reg->read( 'fluent-support/get-product', array(
		'label'       => 'Get Support Product',
		'description' => 'Get a single support product by ID.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Product ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'          => array( 'type' => 'integer' ),
			'title'       => array( 'type' => array( 'string', 'null' ) ),
			'description' => array( 'type' => array( 'string', 'null' ) ),
			'source'      => array( 'type' => array( 'string', 'null' ) ),
			'source_uid'  => array( 'type' => array( 'integer', 'null' ) ),
			'mailbox_id'  => array( 'type' => array( 'integer', 'null' ) ),
			'settings'    => array( 'type' => array( 'object', 'null' ) ),
			'created_by'  => array( 'type' => array( 'integer', 'null' ) ),
			'created_at'  => array( 'type' => array( 'string', 'null' ) ),
			'updated_at'  => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function ( $input ) {
			$product = wpFluent()->table( 'fs_products' )
				->where( 'id', (int) $input['id'] )
				->first();

			if ( ! $product ) {
				return fluent_abilities_error( 'not_found', 'Product not found' );
			}

			return array(
				'id'          => (int) $product->id,
				'title'       => $product->title,
				'description' => $product->description,
				'source'      => $product->source,
				'source_uid'  => $product->source_uid ? (int) $product->source_uid : null,
				'mailbox_id'  => $product->mailbox_id ? (int) $product->mailbox_id : null,
				'settings'    => $product->settings ? fluent_abilities_safe_array( maybe_unserialize( $product->settings ) ) : null,
				'created_by'  => $product->created_by ? (int) $product->created_by : null,
				'created_at'  => $product->created_at ? (string) $product->created_at : null,
				'updated_at'  => $product->updated_at ? (string) $product->updated_at : null,
			);
		},
	) );

	$reg->write( 'fluent-support/create-product', array(
		'label'       => 'Create Support Product',
		'description' => 'Create a new support product.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'title' ),
			'properties' => array(
				'title'       => array( 'type' => 'string', 'description' => 'Product title (required)' ),
				'description' => array( 'type' => 'string', 'description' => 'Product description' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function ( $input ) {
			$data = array(
				'title'       => sanitize_text_field( $input['title'] ),
				'description' => sanitize_textarea_field( $input['description'] ?? '' ),
			);

			$result = FluentSupportApi( 'products' )->createProduct( $data );

			if ( ! $result ) {
				return fluent_abilities_error( 'creation_failed', 'Product creation failed — title may be empty' );
			}

			return array(
				'success' => true,
				'id'      => (int) $result->id,
				'title'   => $result->title,
			);
		},
	) );

	$reg->write( 'fluent-support/update-product', array(
		'label'       => 'Update Support Product',
		'description' => 'Update an existing support product. Only provided fields are changed.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'          => array( 'type' => 'integer', 'description' => 'Product ID' ),
				'title'       => array( 'type' => 'string', 'description' => 'Product title' ),
				'description' => array( 'type' => 'string', 'description' => 'Product description' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function ( $input ) {
			$id = (int) $input['id'];

			$product = wpFluent()->table( 'fs_products' )
				->where( 'id', $id )
				->first();

			if ( ! $product ) {
				return fluent_abilities_error( 'not_found', 'Product not found' );
			}

			$data    = array();
			$updated = array();

			if ( isset( $input['title'] ) ) {
				$data['title'] = sanitize_text_field( $input['title'] );
				$updated[]     = 'title';
			}

			if ( isset( $input['description'] ) ) {
				$data['description'] = sanitize_textarea_field( $input['description'] );
				$updated[]           = 'description';
			}

			if ( empty( $data ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'No fields provided to update' );
			}

			FluentSupportApi( 'products' )->updateProduct( $id, $data );

			return array(
				'success' => true,
				'id'      => $id,
				'updated' => $updated,
			);
		},
	) );

	$reg->delete( 'fluent-support/delete-product', array(
		'label'       => 'Delete Support Product',
		'description' => 'Permanently delete a support product.',
		'category'    => 'fluent-support',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Product ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function ( $input ) {
			$id = (int) $input['id'];

			$product = wpFluent()->table( 'fs_products' )
				->where( 'id', $id )
				->first();

			if ( ! $product ) {
				return fluent_abilities_error( 'not_found', 'Product not found' );
			}

			FluentSupportApi( 'products' )->deleteProduct( $id );

			return array(
				'success' => true,
				'id'      => $id,
			);
		},
	) );

}, 100 );
