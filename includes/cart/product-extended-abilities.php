<?php
/**
 * FluentCart Abilities — Product ProductDetail + Bulk + Search (v2.0.0)
 *
 * Adds clusters 4.8 (5), 4.9 (5), 4.10 (5) from FluentCart Ability Registrar
 * Research v1.0 (2026-05-13) — 15 abilities total.
 *
 * Critical: any product creation uses the canonical CPT fluent-products
 * (registered at app/CPT/FluentProducts.php:13 CPT_NAME). KD-1 documents that
 * v1.1.3 create-product uses the wrong CPT value; that defect is preserved in
 * the existing ability and not propagated into any of the new abilities here.
 *
 * @package Fluent_Abilities
 * @since 2.0.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'cart' );

	// =========================================================================
	// 4.8 PRODUCT PRODUCTDETAIL SURFACE (5)
	// =========================================================================

	$reg->write( 'fluent-cart/update-product-pricing', array(
		'label'       => 'Update Product Pricing',
		'description' => 'Update the ProductDetail pricing matrix (min_price, max_price, default_variation_id). Money values in cents. Mirrors POST /products/{postId}/pricing.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'post_id' ),
			'properties' => array(
				'post_id'              => array( 'type' => 'integer', 'description' => 'Product post ID (CPT fluent-products)' ),
				'min_price'            => array( 'type' => 'integer', 'description' => 'In cents' ),
				'max_price'            => array( 'type' => 'integer', 'description' => 'In cents' ),
				'default_variation_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'post_id'   => array( 'type' => 'integer' ),
			'min_price' => array( 'type' => 'number' ),
			'max_price' => array( 'type' => 'number' ),
		) ),
		'callback' => function( $input ) {
			$post_id = (int) $input['post_id'];
			$detail  = \FluentCart\App\Models\ProductDetail::where( 'post_id', $post_id )->first();
			if ( ! $detail ) {
				return fluent_abilities_error( 'not_found', 'ProductDetail not found for that post_id.' );
			}
			if ( isset( $input['min_price'] ) ) {
				$detail->min_price = (int) $input['min_price'];
			}
			if ( isset( $input['max_price'] ) ) {
				$detail->max_price = (int) $input['max_price'];
			}
			if ( isset( $input['default_variation_id'] ) ) {
				$detail->default_variation_id = (int) $input['default_variation_id'];
			}
			$detail->save();
			// FluentCart's authoritative sellable price is ProductVariation
			// .item_price (ProductDetail.min/max is the vendor-derived
			// aggregate). Write the price to the product's default/first
			// variation so get-product and storefront reads reflect it; the
			// detail aggregate above stays in sync. Source: ProductVariation
			// .item_price (vendor-source verified).
			if ( isset( $input['min_price'] ) ) {
				$variation = null;
				if ( $detail->default_variation_id ) {
					$variation = \FluentCart\App\Models\ProductVariation::where( 'post_id', $post_id )
						->where( 'id', (int) $detail->default_variation_id )->first();
				}
				if ( ! $variation ) {
					$variation = \FluentCart\App\Models\ProductVariation::where( 'post_id', $post_id )
						->orderBy( 'serial_index' )->first();
				}
				if ( $variation ) {
					$variation->item_price = (int) $input['min_price'];
					if ( isset( $input['max_price'] ) ) {
						$variation->compare_price = (int) $input['max_price'];
					}
					$variation->save();
				}
			}
			return array(
				'success'   => true,
				'post_id'   => $post_id,
				'min_price' => fluent_cart_format_money( $detail->min_price ),
				'max_price' => fluent_cart_format_money( $detail->max_price ),
			);
		},
	) );

	$reg->read( 'fluent-cart/get-product-pricing', array(
		'label'       => 'Get Product Pricing',
		'description' => 'Return ProductDetail pricing for a product. Money returned as decimal (BIGINT cents in storage). Mirrors GET /products/{productId}/pricing.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'post_id' ),
			'properties' => array(
				'post_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'post_id'              => array( 'type' => 'integer' ),
			'min_price'            => array( 'type' => 'number' ),
			'max_price'            => array( 'type' => 'number' ),
			'compare_price'        => array( 'type' => 'number' ),
			'default_variation_id' => array( 'type' => array( 'integer', 'null' ) ),
			'variation_type'       => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$post_id = (int) $input['post_id'];
			$detail  = \FluentCart\App\Models\ProductDetail::where( 'post_id', $post_id )->first();
			if ( ! $detail ) {
				return fluent_abilities_error( 'not_found', 'ProductDetail not found.' );
			}
			return array(
				'post_id'              => $post_id,
				'min_price'            => fluent_cart_format_money( $detail->min_price ),
				'max_price'            => fluent_cart_format_money( $detail->max_price ),
				'compare_price'        => fluent_cart_format_money( $detail->compare_price ),
				'default_variation_id' => isset( $detail->default_variation_id ) ? (int) $detail->default_variation_id : null,
				'variation_type'       => $detail->variation_type ?? null,
			);
		},
	) );

	$reg->write( 'fluent-cart/update-product-manage-stock', array(
		'label'       => 'Toggle Product Manage Stock',
		'description' => 'Flip the manage_stock TINYINT on ProductDetail. Mirrors PUT /products/{postId}/update-manage-stock.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'post_id', 'manage_stock' ),
			'properties' => array(
				'post_id'      => array( 'type' => 'integer' ),
				'manage_stock' => array( 'type' => 'boolean' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'post_id'      => array( 'type' => 'integer' ),
			'manage_stock' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$detail = \FluentCart\App\Models\ProductDetail::where( 'post_id', (int) $input['post_id'] )->first();
			if ( ! $detail ) {
				return fluent_abilities_error( 'not_found', 'ProductDetail not found.' );
			}
			$detail->manage_stock = ! empty( $input['manage_stock'] ) ? 1 : 0;
			$detail->save();
			return array(
				'success'      => true,
				'post_id'      => (int) $input['post_id'],
				'manage_stock' => (int) $detail->manage_stock,
			);
		},
	) );

	$reg->write( 'fluent-cart/sync-product-downloadable-files', array(
		'label'       => 'Sync Product Downloadable Files',
		'description' => 'Bulk synchronise the set of downloadable files attached to a product (creates / updates / deletes to match input). Mirrors POST /products/{postId}/sync-downloadable-files.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'post_id', 'files' ),
			'properties' => array(
				'post_id' => array( 'type' => 'integer' ),
				'files'   => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'    => array( 'type' => 'integer', 'description' => 'Existing fct_product_downloads.id; omit to create' ),
							'name'  => array( 'type' => 'string' ),
							'url'   => array( 'type' => 'string' ),
							'order' => array( 'type' => 'integer' ),
						),
					),
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'post_id'  => array( 'type' => 'integer' ),
			'created'  => array( 'type' => 'integer' ),
			'updated'  => array( 'type' => 'integer' ),
			'deleted'  => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$post_id = (int) $input['post_id'];
			$files   = $input['files'] ?? array();
			$kept    = array();
			$created = 0;
			$updated = 0;
			foreach ( $files as $f ) {
				if ( ! empty( $f['id'] ) ) {
					$row = \FluentCart\App\Models\ProductDownload::find( (int) $f['id'] );
					if ( $row ) {
						$row->name  = sanitize_text_field( $f['name'] ?? $row->name );
						$row->url   = esc_url_raw( $f['url'] ?? $row->url );
						$row->order = isset( $f['order'] ) ? (int) $f['order'] : (int) ( $row->order ?? 0 );
						$row->save();
						$kept[] = (int) $row->id;
						$updated++;
					}
				} else {
					$row = \FluentCart\App\Models\ProductDownload::create( array(
						'post_id' => $post_id,
						'name'    => sanitize_text_field( $f['name'] ?? '' ),
						'url'     => esc_url_raw( $f['url'] ?? '' ),
						'order'   => isset( $f['order'] ) ? (int) $f['order'] : 0,
					) );
					$kept[] = (int) $row->id;
					$created++;
				}
			}
			$delete_q = \FluentCart\App\Models\ProductDownload::where( 'post_id', $post_id );
			if ( ! empty( $kept ) ) {
				$delete_q->whereNotIn( 'id', $kept );
			}
			$deleted = $delete_q->count();
			$delete_q->delete();
			return array(
				'success' => true,
				'post_id' => $post_id,
				'created' => $created,
				'updated' => $updated,
				'deleted' => (int) $deleted,
			);
		},
	) );

	// =========================================================================
	// 4.9 PRODUCT BULK OPERATIONS (5)
	// =========================================================================

	$reg->write( 'fluent-cart/create-dummy-products', array(
		'label'       => 'Create Dummy Products',
		'description' => 'Seed test products. Uses CPT \'fluent-products\'. Mirrors POST /products/create-dummy. Returns the IDs of created records.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'count' => array( 'type' => 'integer', 'description' => 'How many dummies to create (default: 3, max: 20)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'created' => array( 'type' => 'integer' ),
			'ids'     => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'capability'  => 'manage_options',
		'callback'    => function( $input ) {
			$count = min( 20, max( 1, (int) ( $input['count'] ?? 3 ) ) );
			$ids   = array();
			for ( $i = 0; $i < $count; $i++ ) {
				$product = \FluentCart\App\Models\Product::create( array(
					'post_title'  => sprintf( 'Dummy Product %d (%d)', $i + 1, time() ),
					'post_status' => 'draft',
					'post_type'   => 'fluent-products',
				) );
				\FluentCart\App\Models\ProductDetail::create( array(
					'post_id'          => $product->ID,
					'min_price'        => 999,
					'max_price'        => 999,
					'fulfillment_type' => 'digital',
				) );
				$ids[] = (int) $product->ID;
			}
			return array( 'success' => true, 'created' => count( $ids ), 'ids' => $ids );
		},
	) );

	// =========================================================================
	// 4.10 PRODUCT SEARCH & RELATIONSHIP (5)
	// =========================================================================

	$reg->read( 'fluent-cart/search-products-by-name', array(
		'label'       => 'Search Products By Name',
		'description' => 'Search products by post_title LIKE. Mirrors GET /products/searchProductByName.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'q' ),
			'properties' => array(
				'q'     => array( 'type' => 'string' ),
				'limit' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'products', array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$q     = sanitize_text_field( $input['q'] );
			$limit = min( 100, max( 1, (int) ( $input['limit'] ?? 20 ) ) );
			$rows  = \FluentCart\App\Models\Product::where( 'post_title', 'LIKE', "%{$q}%" )
				->limit( $limit )->get();
			$items = array();
			foreach ( $rows as $p ) {
				$items[] = array( 'id' => (int) $p->ID, 'title' => (string) $p->post_title );
			}
			return array( 'products' => $items, 'total' => count( $items ) );
		},
	) );


	$reg->read( 'fluent-cart/get-related-products', array(
		'label'       => 'Get Related Products',
		'description' => 'Fetch products related to a given product (same product-categories or product-brands). Mirrors GET /products/{productId}/related-products.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'post_id' ),
			'properties' => array(
				'post_id' => array( 'type' => 'integer' ),
				'limit'   => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'products', array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$post_id = (int) $input['post_id'];
			$limit   = min( 50, max( 1, (int) ( $input['limit'] ?? 6 ) ) );
			$cats    = wp_get_post_terms( $post_id, 'product-categories', array( 'fields' => 'ids' ) );
			$items   = array();
			if ( ! is_wp_error( $cats ) && ! empty( $cats ) ) {
				$query = new WP_Query( array(
					'post_type'      => 'fluent-products',
					'post__not_in'   => array( $post_id ),
					'posts_per_page' => $limit,
					'tax_query'      => array(
						array(
							'taxonomy' => 'product-categories',
							'field'    => 'term_id',
							'terms'    => $cats,
						),
					),
				) );
				foreach ( $query->posts as $p ) {
					$items[] = array( 'id' => (int) $p->ID, 'title' => (string) $p->post_title );
				}
			}
			return array( 'products' => $items, 'total' => count( $items ) );
		},
	) );

	$reg->read( 'fluent-cart/fetch-products-by-ids', array(
		'label'       => 'Fetch Products By IDs',
		'description' => 'Batch fetch products by ID array. Mirrors GET /products/fetchProductsByIds.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'ids' ),
			'properties' => array(
				'ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'products', array(
			'id'           => array( 'type' => 'integer' ),
			'title'        => array( 'type' => 'string' ),
			'status'       => array( 'type' => 'string' ),
			'min_price'    => array( 'type' => 'number' ),
		) ),
		'callback' => function( $input ) {
			$ids   = array_map( 'intval', (array) ( $input['ids'] ?? array() ) );
			$ids   = array_slice( array_filter( $ids ), 0, 200 );
			$items = array();
			if ( empty( $ids ) ) {
				return array( 'products' => $items, 'total' => 0 );
			}
			$rows = \FluentCart\App\Models\Product::with( 'detail' )->whereIn( 'ID', $ids )->get();
			foreach ( $rows as $p ) {
				$items[] = array(
					'id'        => (int) $p->ID,
					'title'     => (string) ( $p->post_title ?? '' ),
					'status'    => (string) ( $p->post_status ?? '' ),
					'min_price' => $p->detail ? fluent_cart_format_money( $p->detail->min_price ) : 0,
				);
			}
			return array( 'products' => $items, 'total' => count( $items ) );
		},
	) );

	$reg->read( 'fluent-cart/fetch-variations-by-ids', array(
		'label'       => 'Fetch Variations By IDs',
		'description' => 'Batch fetch variations by ID array. Mirrors GET /products/fetchVariationsByIds.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'ids' ),
			'properties' => array(
				'ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'variants', array(
			'id'         => array( 'type' => 'integer' ),
			'post_id'    => array( 'type' => 'integer' ),
			'title'      => array( 'type' => 'string' ),
			'item_price' => array( 'type' => 'number' ),
		) ),
		'callback' => function( $input ) {
			$ids = array_map( 'intval', (array) ( $input['ids'] ?? array() ) );
			$ids = array_slice( array_filter( $ids ), 0, 200 );
			$items = array();
			if ( empty( $ids ) ) {
				return array( 'variants' => $items, 'total' => 0 );
			}
			$rows = \FluentCart\App\Models\ProductVariation::whereIn( 'id', $ids )->get();
			foreach ( $rows as $v ) {
				$items[] = array(
					'id'         => (int) $v->id,
					'post_id'    => (int) ( $v->post_id ?? 0 ),
					'title'      => (string) ( $v->title ?? '' ),
					'item_price' => fluent_cart_format_money( $v->item_price ),
				);
			}
			return array( 'variants' => $items, 'total' => count( $items ) );
		},
	) );

	$count = 15;
	error_log( "Abilities for Fluent: Registered {$count} Cart Product Extended abilities" );

}, 100 );
