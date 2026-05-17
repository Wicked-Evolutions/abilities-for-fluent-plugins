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
 * Resolve the per-item schema node for a list/collection/item output helper.
 *
 * The helpers historically treated their `$item_props` argument as a
 * property-name → sub-schema MAP and assigned it verbatim to a JSON-Schema
 * `properties` key. Many callers instead pass a complete schema FRAGMENT —
 * e.g. `array( 'type' => 'object', 'additionalProperties' => true )` for a
 * free-form object item. Stuffing a fragment into `properties` declared a
 * phantom property literally named `type` whose schema was the string
 * `"object"`; WP core `rest_validate_value_from_schema()` then recursed into
 * a string and fatally `TypeError`d in `validate_output()` on any populated
 * response (ledger Addendum 27).
 *
 * Discriminator: if the argument carries a string-valued `type`, it IS a
 * schema node — use it directly as the item schema. Otherwise it is a
 * property map (current, unchanged behaviour). A legitimate property map can
 * itself contain a real property named `type`, but its value is a sub-schema
 * ARRAY (e.g. `array( 'type' => 'string' )`), not a string — so `is_string()`
 * is false and the property-map path is taken exactly as before. A property
 * map with a *string*-valued `type` would be malformed JSON Schema and does
 * not occur in this codebase (verified by an exhaustive registration-time
 * scan of all registered abilities — the only string-in-`properties` cases
 * were precisely the fragment callers this fixes).
 *
 * @param array $item_props Fragment (schema node) or property map.
 * @return array The per-item schema node.
 */
function fluent_abilities_item_schema_node( $item_props ) {
	if ( is_array( $item_props ) && isset( $item_props['type'] ) && is_string( $item_props['type'] ) ) {
		return $item_props;
	}
	$node = array( 'type' => 'object' );
	if ( ! empty( $item_props ) ) {
		$node['properties'] = $item_props;
	}
	return $node;
}

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
	$item_schema = fluent_abilities_item_schema_node( $item_props );

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
	$item_schema = fluent_abilities_item_schema_node( $item_props );

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
	return fluent_abilities_item_schema_node( $item_props );
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
