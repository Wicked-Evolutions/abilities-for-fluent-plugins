<?php
/**
 * FluentCart Abilities — Activity Log (v2.0.0)
 *
 * Adds cluster 4.19 from FluentCart Ability Registrar Research v1.0
 * (2026-05-13) — 3 abilities. Existing v1.1.3 covers per-order activity;
 * this file adds the global list + per-row mark-read + delete.
 *
 * fct_activities columns: module_type, module_id, module_name, log_type,
 * status, read_status, content, created_by.
 *
 * @package Fluent_Abilities
 * @since 2.0.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'cart' );

	$reg->read( 'fluent-cart/list-activities', array(
		'label'       => 'List Activities (global)',
		'description' => 'Global activity feed across all modules. Mirrors GET /activity.',
		'input_schema' => array(
			'type'     => 'object',
			'properties' => array_merge( array(
				'module_type' => array( 'type' => 'string', 'description' => 'Filter by module_type (e.g. order, customer, product)' ),
				'module_id'   => array( 'type' => 'integer' ),
				'log_type'    => array( 'type' => 'string' ),
				'read_status' => array( 'type' => 'string', 'description' => 'read | unread' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'activities', array(
			'id'           => array( 'type' => 'integer' ),
			'module_type'  => array( 'type' => array( 'string', 'null' ) ),
			'module_id'    => array( 'type' => array( 'integer', 'null' ) ),
			'module_name'  => array( 'type' => array( 'string', 'null' ) ),
			'log_type'     => array( 'type' => array( 'string', 'null' ) ),
			'status'       => array( 'type' => array( 'string', 'null' ) ),
			'read_status'  => array( 'type' => array( 'string', 'null' ) ),
			'content'      => array( 'type' => array( 'string', 'null' ) ),
			'created_by'   => array( 'type' => array( 'integer', 'string', 'null' ) ),
			'created_at'   => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$model      = '\\FluentCart\\App\\Models\\Activity';
			if ( ! class_exists( $model ) ) {
				return array( 'activities' => array(), 'total' => 0, 'page' => $pagination['page'], 'per_page' => $pagination['per_page'] );
			}
			$query = $model::query();
			foreach ( array( 'module_type', 'log_type', 'read_status' ) as $f ) {
				if ( ! empty( $input[ $f ] ) ) {
					$query->where( $f, sanitize_text_field( $input[ $f ] ) );
				}
			}
			if ( ! empty( $input['module_id'] ) ) {
				$query->where( 'module_id', (int) $input['module_id'] );
			}
			$total = $query->count();
			$rows  = $query->orderBy( 'id', 'DESC' )
				->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();
			$items = array();
			foreach ( $rows as $a ) {
				$items[] = array(
					'id'          => (int) $a->id,
					'module_type' => $a->module_type ?? null,
					'module_id'   => isset( $a->module_id ) ? (int) $a->module_id : null,
					'module_name' => $a->module_name ?? null,
					'log_type'    => $a->log_type ?? null,
					'status'      => $a->status ?? null,
					'read_status' => $a->read_status ?? null,
					'content'     => $a->content ?? null,
					'created_by'  => $a->created_by ?? null,
					'created_at'  => $a->created_at ? (string) $a->created_at : null,
				);
			}
			return array(
				'activities' => $items,
				'total'      => $total,
				'page'       => $pagination['page'],
				'per_page'   => $pagination['per_page'],
			);
		},
	) );

	$reg->write( 'fluent-cart/mark-activity-read', array(
		'label'       => 'Mark Activity Read',
		'description' => 'Flip read_status to read on a single activity row. Mirrors PUT /activity/{id}/mark-read.',
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
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$model = '\\FluentCart\\App\\Models\\Activity';
			if ( ! class_exists( $model ) ) {
				return fluent_abilities_error( 'not_found', 'Activity model not available.' );
			}
			$row = $model::find( (int) $input['id'] );
			if ( ! $row ) {
				return fluent_abilities_error( 'not_found', 'Activity not found.' );
			}
			$row->read_status = 'read';
			$row->save();
			return array( 'success' => true, 'id' => (int) $row->id );
		},
	) );

	$reg->delete( 'fluent-cart/delete-activity', array(
		'label'       => 'Delete Activity',
		'description' => 'Delete a single activity row. Mirrors DELETE /activity/{id}.',
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
			$model = '\\FluentCart\\App\\Models\\Activity';
			if ( ! class_exists( $model ) ) {
				return fluent_abilities_error( 'not_found', 'Activity model not available.' );
			}
			$row = $model::find( (int) $input['id'] );
			if ( ! $row ) {
				return fluent_abilities_error( 'not_found', 'Activity not found.' );
			}
			$id = (int) $row->id;
			$row->delete();
			return array( 'success' => true, 'id' => $id );
		},
	) );

	$count = 3;
	error_log( "Abilities for Fluent: Registered {$count} Cart Activity abilities" );

}, 100 );
