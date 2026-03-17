<?php
/**
 * FluentCart Abilities — Product Downloads (P1)
 *
 * List and create downloadable files for digital products.
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
	// PRODUCT DOWNLOADS
	// =========================================================================

	$reg->read( 'fluent-cart/list-product-downloads', array(
		'label'       => 'List Product Downloads',
		'description' => 'List downloadable files attached to a FluentCart product.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'product_id' ),
			'properties' => array(
				'product_id' => array(
					'type'        => 'integer',
					'description' => 'Product ID (post_id)',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'downloads', array(
			'id'                    => array( 'type' => 'integer' ),
			'post_id'               => array( 'type' => 'integer' ),
			'product_variation_id'  => array( 'type' => 'string' ),
			'download_identifier'   => array( 'type' => 'string' ),
			'title'                 => array( 'type' => array( 'string', 'null' ) ),
			'type'                  => array( 'type' => array( 'string', 'null' ) ),
			'driver'                => array( 'type' => array( 'string', 'null' ) ),
			'file_name'             => array( 'type' => array( 'string', 'null' ) ),
			'file_url'              => array( 'type' => array( 'string', 'null' ) ),
			'file_size'             => array( 'type' => array( 'string', 'null' ) ),
			'serial'                => array( 'type' => array( 'integer', 'null' ) ),
			'created_at'            => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$downloads = \FluentCart\App\Models\ProductDownload::where( 'post_id', (int) $input['product_id'] )
				->orderBy( 'serial', 'ASC' )
				->get();

			$items = array();
			foreach ( $downloads as $dl ) {
				$items[] = array(
					'id'                   => (int) $dl->id,
					'post_id'              => (int) $dl->post_id,
					'product_variation_id' => (string) ( $dl->product_variation_id ?? '' ),
					'download_identifier'  => (string) ( $dl->download_identifier ?? '' ),
					'title'                => $dl->title ?? null,
					'type'                 => $dl->type ?? null,
					'driver'               => $dl->driver ?? null,
					'file_name'            => $dl->file_name ?? null,
					'file_url'             => $dl->file_url ?? null,
					'file_size'            => $dl->file_size ?? null,
					'serial'               => $dl->serial !== null ? (int) $dl->serial : null,
					'created_at'           => $dl->created_at ? (string) $dl->created_at : null,
				);
			}

			return array( 'downloads' => $items, 'total' => count( $items ) );
		},
	) );

	$reg->write( 'fluent-cart/create-product-download', array(
		'label'       => 'Create Product Download',
		'description' => 'Attach a downloadable file to a FluentCart product.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'product_id', 'title', 'file_url' ),
			'properties' => array(
				'product_id'           => array( 'type' => 'integer', 'description' => 'Product ID (post_id)' ),
				'product_variation_id' => array( 'type' => 'string', 'description' => 'Variation ID(s) this download applies to (comma-separated or empty for all)' ),
				'title'                => array( 'type' => 'string', 'description' => 'Download title' ),
				'file_url'             => array( 'type' => 'string', 'description' => 'URL to the downloadable file' ),
				'file_name'            => array( 'type' => 'string', 'description' => 'Display filename' ),
				'file_size'            => array( 'type' => 'string', 'description' => 'File size (e.g., "2.5MB")' ),
				'type'                 => array( 'type' => 'string', 'description' => 'File type (e.g., "zip", "pdf")' ),
				'driver'               => array( 'type' => 'string', 'description' => 'Storage driver (default: local)' ),
				'serial'               => array( 'type' => 'integer', 'description' => 'Sort order' ),
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
				'post_id'               => (int) $input['product_id'],
				'product_variation_id'  => sanitize_text_field( $input['product_variation_id'] ?? '' ),
				'download_identifier'   => wp_generate_uuid4(),
				'title'                 => sanitize_text_field( $input['title'] ),
				'file_url'              => esc_url_raw( $input['file_url'] ),
			);

			if ( isset( $input['file_name'] ) ) {
				$data['file_name'] = sanitize_file_name( $input['file_name'] );
			}
			if ( isset( $input['file_size'] ) ) {
				$data['file_size'] = sanitize_text_field( $input['file_size'] );
			}
			if ( isset( $input['type'] ) ) {
				$data['type'] = sanitize_text_field( $input['type'] );
			}
			if ( isset( $input['driver'] ) ) {
				$data['driver'] = sanitize_text_field( $input['driver'] );
			}
			if ( isset( $input['serial'] ) ) {
				$data['serial'] = (int) $input['serial'];
			}

			$download = \FluentCart\App\Models\ProductDownload::create( $data );

			return array(
				'success' => true,
				'id'      => (int) $download->id,
				'title'   => $download->title,
			);
		},
	) );

	$count = 2;
	error_log( "Abilities for Fluent: Registered {$count} Cart Download abilities" );

}, 100 );
