<?php
/**
 * FluentCart Abilities — Attribute Groups & Terms (v2.0.0)
 *
 * Adds cluster 4.12 from FluentCart Ability Registrar Research v1.0
 * (2026-05-13) — 8 abilities.
 *
 * fct_atts_groups / fct_atts_terms / fct_atts_obj_rels are FluentCart's
 * product-attribute taxonomy — distinct from WP taxonomies product-categories
 * and product-brands (which are bridge-reachable via core taxonomies/* ).
 *
 * @package Fluent_Abilities
 * @since 2.0.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'cart' );

	$reg->read( 'fluent-cart/list-attribute-groups', array(
		'label'       => 'List Attribute Groups',
		'description' => 'List FluentCart product attribute groups (fct_atts_groups). Mirrors GET /options/attr/groups.',
		'input_schema' => array(
			'type'     => 'object',
			'properties' => array_merge( array(
				'search' => array( 'type' => 'string' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'groups', array(
			'id'          => array( 'type' => 'integer' ),
			'title'       => array( 'type' => 'string' ),
			'slug'        => array( 'type' => 'string' ),
			'description' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$model      = '\\FluentCart\\App\\Models\\AttributeGroup';
			if ( ! class_exists( $model ) ) {
				return array( 'groups' => array(), 'total' => 0, 'page' => $pagination['page'], 'per_page' => $pagination['per_page'] );
			}
			$query = $model::query();
			if ( ! empty( $input['search'] ) ) {
				$search = sanitize_text_field( $input['search'] );
				$query->where( 'title', 'LIKE', "%{$search}%" );
			}
			$total = $query->count();
			$rows  = $query->orderBy( 'id', 'DESC' )
				->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();
			$items = array();
			foreach ( $rows as $g ) {
				$items[] = array(
					'id'          => (int) $g->id,
					'title'       => (string) ( $g->title ?? '' ),
					'slug'        => (string) ( $g->slug ?? '' ),
					'description' => $g->description ?? null,
				);
			}
			return array(
				'groups'   => $items,
				'total'    => $total,
				'page'     => $pagination['page'],
				'per_page' => $pagination['per_page'],
			);
		},
	) );

	$reg->write( 'fluent-cart/create-attribute-group', array(
		'label'       => 'Create Attribute Group',
		'description' => 'Create a new product attribute group (fct_atts_groups). Mirrors POST /options/attr/group.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'title' ),
			'properties' => array(
				'title'       => array( 'type' => 'string' ),
				'slug'        => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'    => array( 'type' => 'integer' ),
			'slug'  => array( 'type' => 'string' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback'    => function( $input ) {
			$model = '\\FluentCart\\App\\Models\\AttributeGroup';
			if ( ! class_exists( $model ) ) {
				return fluent_abilities_error( 'not_found', 'AttributeGroup model not available.' );
			}
			$title = sanitize_text_field( $input['title'] );
			$slug  = ! empty( $input['slug'] ) ? sanitize_title( $input['slug'] ) : sanitize_title( $title );
			$group = $model::create( array(
				'title'       => $title,
				'slug'        => $slug,
				'description' => sanitize_text_field( $input['description'] ?? '' ),
			) );
			return array( 'success' => true, 'id' => (int) $group->id, 'slug' => (string) $group->slug );
		},
	) );

	$reg->read( 'fluent-cart/get-attribute-group', array(
		'label'       => 'Get Attribute Group',
		'description' => 'Get a single attribute group. Mirrors GET /options/attr/group/{group_id}.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'          => array( 'type' => 'integer' ),
			'title'       => array( 'type' => 'string' ),
			'slug'        => array( 'type' => 'string' ),
			'description' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$model = '\\FluentCart\\App\\Models\\AttributeGroup';
			if ( ! class_exists( $model ) ) {
				return fluent_abilities_error( 'not_found', 'AttributeGroup model not available.' );
			}
			$g = $model::find( (int) $input['id'] );
			if ( ! $g ) {
				return fluent_abilities_error( 'not_found', 'Attribute group not found.' );
			}
			return array(
				'id'          => (int) $g->id,
				'title'       => (string) ( $g->title ?? '' ),
				'slug'        => (string) ( $g->slug ?? '' ),
				'description' => $g->description ?? null,
			);
		},
	) );

	$reg->write( 'fluent-cart/update-attribute-group', array(
		'label'       => 'Update Attribute Group',
		'description' => 'Update an attribute group. Mirrors PUT /options/attr/group/{group_id}.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'id' ),
			'properties' => array(
				'id'          => array( 'type' => 'integer' ),
				'title'       => array( 'type' => 'string' ),
				'slug'        => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$model = '\\FluentCart\\App\\Models\\AttributeGroup';
			if ( ! class_exists( $model ) ) {
				return fluent_abilities_error( 'not_found', 'AttributeGroup model not available.' );
			}
			$g = $model::find( (int) $input['id'] );
			if ( ! $g ) {
				return fluent_abilities_error( 'not_found', 'Attribute group not found.' );
			}
			if ( isset( $input['title'] ) ) {
				$g->title = sanitize_text_field( $input['title'] );
			}
			if ( isset( $input['slug'] ) ) {
				$g->slug = sanitize_title( $input['slug'] );
			}
			if ( isset( $input['description'] ) ) {
				$g->description = sanitize_text_field( $input['description'] );
			}
			$g->save();
			return array( 'success' => true, 'id' => (int) $g->id );
		},
	) );

	$reg->delete( 'fluent-cart/delete-attribute-group', array(
		'label'       => 'Delete Attribute Group',
		'description' => 'Delete an attribute group. Cascades to fct_atts_terms and fct_atts_obj_rels. Mirrors DELETE /options/attr/group/{group_id}.',
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
			$group_model = '\\FluentCart\\App\\Models\\AttributeGroup';
			$term_model  = '\\FluentCart\\App\\Models\\AttributeTerm';
			if ( ! class_exists( $group_model ) ) {
				return fluent_abilities_error( 'not_found', 'AttributeGroup model not available.' );
			}
			$g = $group_model::find( (int) $input['id'] );
			if ( ! $g ) {
				return fluent_abilities_error( 'not_found', 'Attribute group not found.' );
			}
			$id = (int) $g->id;
			if ( class_exists( $term_model ) ) {
				$term_model::where( 'group_id', $id )->delete();
			}
			$g->delete();
			return array( 'success' => true, 'id' => $id );
		},
	) );

	$reg->read( 'fluent-cart/list-attribute-terms', array(
		'label'       => 'List Attribute Terms',
		'description' => 'List terms in an attribute group (fct_atts_terms). Mirrors GET /options/attr/group/{group_id}/terms.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'group_id' ),
			'properties' => array_merge( array(
				'group_id' => array( 'type' => 'integer' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'terms', array(
			'id'       => array( 'type' => 'integer' ),
			'group_id' => array( 'type' => 'integer' ),
			'title'    => array( 'type' => 'string' ),
			'slug'     => array( 'type' => 'string' ),
			'serial'   => array( 'type' => array( 'integer', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$model      = '\\FluentCart\\App\\Models\\AttributeTerm';
			if ( ! class_exists( $model ) ) {
				return array( 'terms' => array(), 'total' => 0, 'page' => $pagination['page'], 'per_page' => $pagination['per_page'] );
			}
			$query = $model::where( 'group_id', (int) $input['group_id'] );
			$total = $query->count();
			$rows  = $query->orderBy( 'serial', 'ASC' )
				->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();
			$items = array();
			foreach ( $rows as $t ) {
				$items[] = array(
					'id'       => (int) $t->id,
					'group_id' => (int) $t->group_id,
					'title'    => (string) ( $t->title ?? '' ),
					'slug'     => (string) ( $t->slug ?? '' ),
					'serial'   => isset( $t->serial ) ? (int) $t->serial : null,
				);
			}
			return array(
				'terms'    => $items,
				'total'    => $total,
				'page'     => $pagination['page'],
				'per_page' => $pagination['per_page'],
			);
		},
	) );

	$reg->write( 'fluent-cart/create-attribute-term', array(
		'label'       => 'Create Attribute Term',
		'description' => 'Create a term within an attribute group. Mirrors POST /options/attr/group/{group_id}/term.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'group_id', 'title' ),
			'properties' => array(
				'group_id' => array( 'type' => 'integer' ),
				'title'    => array( 'type' => 'string' ),
				'slug'     => array( 'type' => 'string' ),
				'serial'   => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback'    => function( $input ) {
			$model = '\\FluentCart\\App\\Models\\AttributeTerm';
			if ( ! class_exists( $model ) ) {
				return fluent_abilities_error( 'not_found', 'AttributeTerm model not available.' );
			}
			$title = sanitize_text_field( $input['title'] );
			$slug  = ! empty( $input['slug'] ) ? sanitize_title( $input['slug'] ) : sanitize_title( $title );
			$term  = $model::create( array(
				'group_id' => (int) $input['group_id'],
				'title'    => $title,
				'slug'     => $slug,
				'serial'   => isset( $input['serial'] ) ? (int) $input['serial'] : 0,
			) );
			return array( 'success' => true, 'id' => (int) $term->id );
		},
	) );

	$reg->write( 'fluent-cart/reorder-attribute-term', array(
		'label'       => 'Reorder Attribute Term',
		'description' => 'Set the serial (ordering) of a term within its group. Note: `id` is the attribute TERM id (the {term_id} in the route, resolved via AttributeTerm::find — NOT the group_id), and `serial` is the absolute integer ordinal position to assign to that term. Mirrors POST /options/attr/group/{group_id}/term/{term_id}/serial via \FluentCart\App\Models\AttributeTerm::find/save.',
		'input_schema' => array(
			'type'     => 'object',
			'required' => array( 'id', 'serial' ),
			'properties' => array(
				'id'     => array( 'type' => 'integer' ),
				'serial' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'     => array( 'type' => 'integer' ),
			'serial' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$model = '\\FluentCart\\App\\Models\\AttributeTerm';
			if ( ! class_exists( $model ) ) {
				return fluent_abilities_error( 'not_found', 'AttributeTerm model not available.' );
			}
			$t = $model::find( (int) $input['id'] );
			if ( ! $t ) {
				return fluent_abilities_error( 'not_found', 'Attribute term not found.' );
			}
			$t->serial = (int) $input['serial'];
			$t->save();
			return array( 'success' => true, 'id' => (int) $t->id, 'serial' => (int) $t->serial );
		},
	) );

	$count = 8;
	error_log( "Abilities for Fluent: Registered {$count} Cart Attribute abilities" );

}, 100 );
