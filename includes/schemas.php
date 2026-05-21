<?php
/**
 * Shared Output Schema Helpers — Fluent Abilities
 *
 * Centralised JSON Schema building blocks used across all Fluent ability modules.
 * Mirrors the pattern established in Abilities for WordPress (schemas.php).
 *
 * Naming convention: fluent_abilities_schema_*()
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

// ============================================================
// OUTPUT SCHEMA HELPERS
// ============================================================

/**
 * Standard paginated list response output_schema.
 *
 * Returns a schema for: { total, page, per_page, items_key: [...] }
 *
 * Note: Fluent Suite uses { items_key, total, page, per_page } without a 'pages' field,
 * unlike WP Suite. This is intentional — do not add 'pages'.
 *
 * Usage:
 *   'output_schema' => fluent_abilities_schema_list_output( 'contacts', array(
 *       'id'    => array( 'type' => 'integer' ),
 *       'email' => array( 'type' => 'string' ),
 *   ) )
 *
 * @param string $items_key  Key name for the items array (e.g. 'contacts', 'tickets').
 * @param array  $item_props Properties of a single item object.
 * @return array output_schema definition.
 */
function fluent_abilities_schema_list_output( $items_key = 'items', $item_props = array() ) {
	$item_schema = array( 'type' => 'object' );
	if ( ! empty( $item_props ) ) {
		$item_schema['properties'] = $item_props;
	}

	return array(
		'type'       => 'object',
		'properties' => array(
			'total'    => array( 'type' => 'integer', 'description' => 'Total matching items' ),
			'page'     => array( 'type' => 'integer', 'description' => 'Current page number' ),
			'per_page' => array( 'type' => 'integer', 'description' => 'Items per page' ),
			$items_key => array( 'type' => 'array', 'items' => $item_schema ),
		),
	);
}

/**
 * Standard non-paginated collection output_schema.
 *
 * Returns a schema for: { total, items_key: [...] }
 * Use for full collections that don't paginate (tags, lists, spaces, etc.)
 *
 * @param string $items_key  Key name for the items array.
 * @param array  $item_props Properties of a single item object.
 * @return array output_schema definition.
 */
function fluent_abilities_schema_collection_output( $items_key = 'items', $item_props = array() ) {
	$item_schema = array( 'type' => 'object' );
	if ( ! empty( $item_props ) ) {
		$item_schema['properties'] = $item_props;
	}

	return array(
		'type'       => 'object',
		'properties' => array(
			'total'    => array( 'type' => 'integer', 'description' => 'Total number of items' ),
			$items_key => array( 'type' => 'array', 'items' => $item_schema ),
		),
	);
}

/**
 * Standard success response output_schema.
 *
 * Returns a schema for: { success: bool, ... }
 *
 * @param array $extra_props Additional properties beyond 'success'.
 * @return array output_schema definition.
 */
function fluent_abilities_schema_success_output( $extra_props = array() ) {
	return array(
		'type'       => 'object',
		'properties' => array_merge(
			array(
				'success' => array( 'type' => 'boolean', 'description' => 'Whether the operation succeeded' ),
			),
			$extra_props
		),
	);
}

/**
 * Standard single-item lookup output_schema.
 *
 * Returns a schema for a single object with known properties.
 *
 * @param array $item_props Properties of the returned object.
 * @return array output_schema definition.
 */
function fluent_abilities_schema_item_output( $item_props = array() ) {
	$schema = array( 'type' => 'object' );
	if ( ! empty( $item_props ) ) {
		$schema['properties'] = $item_props;
	}
	return $schema;
}

// ============================================================
// COMMON PROPERTY FRAGMENTS
// ============================================================

/**
 * Standard timestamp properties (created_at, updated_at).
 *
 * @return array Schema property definitions.
 */
function fluent_abilities_schema_timestamps() {
	return array(
		'created_at' => array( 'type' => 'string', 'description' => 'Creation timestamp' ),
		'updated_at' => array( 'type' => 'string', 'description' => 'Last update timestamp' ),
	);
}

/**
 * Standard integer ID property.
 *
 * @param string $description Optional description.
 * @return array Schema property definition.
 */
function fluent_abilities_schema_id( $description = 'Record ID' ) {
	return array( 'type' => 'integer', 'description' => $description );
}
