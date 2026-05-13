<?php
/**
 * FluentCart Abilities — Product Upgrade Paths (v2.0.0, Pro-gated)
 *
 * Adds cluster 4.11 from FluentCart Ability Registrar Research v1.0
 * (2026-05-13) — 4 abilities, all Pro-only (Promotional module).
 *
 * @package Fluent_Abilities
 * @since 2.0.0
 */

defined( 'ABSPATH' ) || exit;

// Pro gate: skip if FluentCart Pro's Promotional module isn't loaded.
if (
	! class_exists( '\\FluentCartPro\\App\\Modules\\Promotional\\Models\\UpgradePath' )
	&& ! defined( 'FLUENT_CART_PRO_VERSION' )
) {
	return;
}

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'cart' );

	$reg->read( 'fluent-cart/list-product-upgrade-paths', array(
		'label'       => 'List Product Upgrade Paths',
		'description' => 'List upgrade paths for a source product. Requires FluentCart Pro Promotional module. Mirrors GET /products/{id}/upgrade-paths.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'product_id' ),
			'properties' => array_merge( array(
				'product_id' => array( 'type' => 'integer', 'description' => 'Source product ID' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'upgrade_paths', array(
			'id'                => array( 'type' => 'integer' ),
			'source_product_id' => array( 'type' => 'integer' ),
			'target_product_id' => array( 'type' => 'integer' ),
			'discount_type'     => array( 'type' => array( 'string', 'null' ) ),
			'discount_amount'   => array( 'type' => array( 'number', 'null' ) ),
			'status'            => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$model      = '\\FluentCartPro\\App\\Modules\\Promotional\\Models\\UpgradePath';
			if ( ! class_exists( $model ) ) {
				return array( 'upgrade_paths' => array(), 'total' => 0, 'page' => $pagination['page'], 'per_page' => $pagination['per_page'] );
			}
			$query = $model::where( 'source_product_id', (int) $input['product_id'] );
			$total = $query->count();
			$rows  = $query->orderBy( 'id', 'DESC' )
				->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();
			$items = array();
			foreach ( $rows as $r ) {
				$items[] = array(
					'id'                => (int) $r->id,
					'source_product_id' => (int) ( $r->source_product_id ?? 0 ),
					'target_product_id' => (int) ( $r->target_product_id ?? 0 ),
					'discount_type'     => $r->discount_type ?? null,
					'discount_amount'   => isset( $r->discount_amount ) ? (float) $r->discount_amount : null,
					'status'            => $r->status ?? null,
				);
			}
			return array(
				'upgrade_paths' => $items,
				'total'         => $total,
				'page'          => $pagination['page'],
				'per_page'      => $pagination['per_page'],
			);
		},
	) );

	$reg->write( 'fluent-cart/create-upgrade-path', array(
		'label'       => 'Create Product Upgrade Path',
		'description' => 'Define an upgrade path from one product to another. Requires Pro. Mirrors POST /products/{id}/upgrade-path.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'source_product_id', 'target_product_id' ),
			'properties' => array(
				'source_product_id' => array( 'type' => 'integer' ),
				'target_product_id' => array( 'type' => 'integer' ),
				'discount_type'     => array( 'type' => 'string', 'enum' => array( 'percent', 'fixed' ) ),
				'discount_amount'   => array( 'type' => 'number' ),
				'status'            => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback'    => function( $input ) {
			$model = '\\FluentCartPro\\App\\Modules\\Promotional\\Models\\UpgradePath';
			if ( ! class_exists( $model ) ) {
				return fluent_abilities_error( 'not_found', 'FluentCart Pro Promotional module not available.' );
			}
			$path = $model::create( array(
				'source_product_id' => (int) $input['source_product_id'],
				'target_product_id' => (int) $input['target_product_id'],
				'discount_type'     => sanitize_text_field( $input['discount_type'] ?? 'percent' ),
				'discount_amount'   => isset( $input['discount_amount'] ) ? (float) $input['discount_amount'] : 0,
				'status'            => sanitize_text_field( $input['status'] ?? 'active' ),
			) );
			return array( 'success' => true, 'id' => (int) $path->id );
		},
	) );

	$reg->write( 'fluent-cart/update-upgrade-path', array(
		'label'       => 'Update Product Upgrade Path',
		'description' => 'Update an existing upgrade path. Requires Pro. Mirrors POST /products/upgrade-path/{id}/update.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'id' ),
			'properties' => array(
				'id'              => array( 'type' => 'integer' ),
				'discount_type'   => array( 'type' => 'string' ),
				'discount_amount' => array( 'type' => 'number' ),
				'status'          => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$model = '\\FluentCartPro\\App\\Modules\\Promotional\\Models\\UpgradePath';
			if ( ! class_exists( $model ) ) {
				return fluent_abilities_error( 'not_found', 'FluentCart Pro Promotional module not available.' );
			}
			$path = $model::find( (int) $input['id'] );
			if ( ! $path ) {
				return fluent_abilities_error( 'not_found', 'Upgrade path not found.' );
			}
			foreach ( array( 'discount_type', 'status' ) as $f ) {
				if ( isset( $input[ $f ] ) ) {
					$path->{$f} = sanitize_text_field( $input[ $f ] );
				}
			}
			if ( isset( $input['discount_amount'] ) ) {
				$path->discount_amount = (float) $input['discount_amount'];
			}
			$path->save();
			return array( 'success' => true, 'id' => (int) $path->id );
		},
	) );

	$reg->delete( 'fluent-cart/delete-upgrade-path', array(
		'label'       => 'Delete Product Upgrade Path',
		'description' => 'Delete an upgrade path. Requires Pro. Mirrors DELETE /products/upgrade-path/{id}/delete.',
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
		'callback'    => function( $input ) {
			$model = '\\FluentCartPro\\App\\Modules\\Promotional\\Models\\UpgradePath';
			if ( ! class_exists( $model ) ) {
				return fluent_abilities_error( 'not_found', 'FluentCart Pro Promotional module not available.' );
			}
			$path = $model::find( (int) $input['id'] );
			if ( ! $path ) {
				return fluent_abilities_error( 'not_found', 'Upgrade path not found.' );
			}
			$id = (int) $path->id;
			$path->delete();
			return array( 'success' => true, 'id' => $id );
		},
	) );

	$count = 4;
	error_log( "Abilities for Fluent: Registered {$count} Cart Product Upgrade Path abilities (Pro)" );

}, 100 );
