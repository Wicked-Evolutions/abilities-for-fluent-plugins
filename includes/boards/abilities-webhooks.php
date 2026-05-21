<?php
/**
 * Fluent Boards — Webhooks (Research §4.24 + §4.25)
 *
 * §4.24 Incoming webhooks  — 4 abilities (free)
 * §4.25 Outgoing webhooks  — 4 abilities (free)
 * Total: 8 abilities.
 *
 * Incoming webhooks: fbs_metas with object_type='webhook'. URL is computed on
 * read (§6.9) as ?fbs=1&route=task&hash={uuid}. Field map describes how the
 * incoming POST body maps to fbs_tasks columns.
 *
 * Outgoing webhooks: fbs_metas with object_type='outgoing_webhook'. Events
 * enum is intentionally permissive here pending §7.Q7 (vendor Hooks/actions.php
 * source read); a representative subset is accepted.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

// ============== Helpers (file-local) =====================================
$boards_incoming_webhook_url = function( $hash ) {
	return add_query_arg( array( 'fbs' => 1, 'route' => 'task', 'hash' => $hash ), site_url( '/' ) );
};

// =========================================================================
// §4.24.1 — list-incoming-webhooks
// =========================================================================
$reg->read( 'fluent-boards/list-incoming-webhooks', array(
	'label'         => 'List Incoming Webhooks',
	'description'   => 'List configured incoming webhooks. URLs are computed on read against the current site_url() (§6.9).',
	'category'      => 'fluent-boards',
	'output_schema' => fluent_abilities_schema_collection_output( 'webhooks', array(
		'id'         => array( 'type' => 'integer' ),
		'name'       => array( 'type' => array( 'string', 'null' ) ),
		'url'        => array( 'type' => array( 'string', 'null' ) ),
		'field_map'  => array( 'type' => array( 'object', 'null' ) ),
		'created_at' => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function() use ( $boards_incoming_webhook_url ) {
		$rows  = wpFluent()->table( 'fbs_metas' )->where( 'object_type', 'webhook' )->orderBy( 'id', 'DESC' )->get();
		$items = array();
		foreach ( $rows as $r ) {
			$meta    = maybe_unserialize( $r->value ?? '' );
			$meta    = is_array( $meta ) ? $meta : array();
			$items[] = array(
				'id'         => (int) $r->id,
				'name'       => $meta['name'] ?? null,
				'url'        => isset( $meta['hash'] ) ? $boards_incoming_webhook_url( $meta['hash'] ) : null,
				'field_map'  => $meta['field_map'] ?? null,
				'created_at' => $r->created_at ?? null,
			);
		}
		return array( 'webhooks' => $items, 'total' => count( $items ) );
	},
) );

// =========================================================================
// §4.24.2 — create-incoming-webhook (idempotent:false)
// =========================================================================
$reg->write( 'fluent-boards/create-incoming-webhook', array(
	'label'       => 'Create Incoming Webhook',
	'description' => 'Create an incoming webhook that maps inbound POST body fields onto fbs_tasks columns. Returns the computed URL and the uuid hash key.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'name', 'field_map' ),
		'properties' => array(
			'name'      => array( 'type' => 'string' ),
			'field_map' => array(
				'type'        => 'object',
				'description' => 'Map of incoming field name → fbs_tasks column (e.g. {title: "subject", description: "body"}).',
			),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'id'   => array( 'type' => 'integer' ),
		'url'  => array( 'type' => 'string' ),
		'hash' => array( 'type' => 'string' ),
	) ),
	'annotations' => array( 'idempotent' => false ),
	'callback'    => function( $input ) use ( $boards_incoming_webhook_url ) {
		$name      = sanitize_text_field( $input['name'] ?? '' );
		$field_map = (array) ( $input['field_map'] ?? array() );
		if ( ! $name ) {
			return fluent_abilities_error( 'ability_invalid_input', 'name is required.' );
		}
		$hash   = wp_generate_uuid4();
		$now    = current_time( 'mysql' );
		$new_id = wpFluent()->table( 'fbs_metas' )->insertGetId( array(
			'object_id'   => 0,
			'object_type' => 'webhook',
			'key'         => $hash,
			'value'       => maybe_serialize( array(
				'name'      => $name,
				'hash'      => $hash,
				'field_map' => $field_map,
			) ),
			'created_at'  => $now,
			'updated_at'  => $now,
		) );
		return array( 'success' => true, 'id' => (int) $new_id, 'url' => $boards_incoming_webhook_url( $hash ), 'hash' => $hash );
	},
) );

// =========================================================================
// §4.24.3 — update-incoming-webhook
// =========================================================================
$reg->write( 'fluent-boards/update-incoming-webhook', array(
	'label'       => 'Update Incoming Webhook',
	'description' => 'Update an incoming webhook\'s name and/or field_map. The hash and URL are preserved.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'id' ),
		'properties' => array(
			'id'        => array( 'type' => 'integer' ),
			'name'      => array( 'type' => 'string' ),
			'field_map' => array( 'type' => 'object' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'id' => array( 'type' => 'integer' ) ) ),
	'callback'     => function( $input ) {
		$id  = (int) $input['id'];
		$row = wpFluent()->table( 'fbs_metas' )->where( 'id', $id )->where( 'object_type', 'webhook' )->first();
		if ( ! $row ) {
			return fluent_abilities_error( 'not_found', 'Webhook not found.' );
		}
		$meta = maybe_unserialize( $row->value ?? '' );
		$meta = is_array( $meta ) ? $meta : array();
		if ( isset( $input['name'] ) ) {
			$meta['name'] = sanitize_text_field( $input['name'] );
		}
		if ( isset( $input['field_map'] ) && is_array( $input['field_map'] ) ) {
			$meta['field_map'] = $input['field_map'];
		}
		wpFluent()->table( 'fbs_metas' )->where( 'id', $id )->update( array(
			'value'      => maybe_serialize( $meta ),
			'updated_at' => current_time( 'mysql' ),
		) );
		return array( 'success' => true, 'id' => $id );
	},
) );

// =========================================================================
// §4.24.4 — delete-incoming-webhook (idempotent:false)
// =========================================================================
$reg->delete( 'fluent-boards/delete-incoming-webhook', array(
	'label'       => 'Delete Incoming Webhook',
	'description' => 'Delete an incoming webhook. After deletion the URL\'s hash no longer resolves and inbound POSTs are rejected.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'id' ),
		'properties' => array(
			'id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'id' => array( 'type' => 'integer' ) ) ),
	'annotations'  => array( 'idempotent' => false ),
	'callback'     => function( $input ) {
		$id = (int) $input['id'];
		wpFluent()->table( 'fbs_metas' )->where( 'id', $id )->where( 'object_type', 'webhook' )->delete();
		return array( 'success' => true, 'id' => $id );
	},
) );

// =========================================================================
// §4.25.1 — list-outgoing-webhooks
// =========================================================================
$reg->read( 'fluent-boards/list-outgoing-webhooks', array(
	'label'         => 'List Outgoing Webhooks',
	'description'   => 'List configured outgoing webhooks (target URL + subscribed events).',
	'category'      => 'fluent-boards',
	'output_schema' => fluent_abilities_schema_collection_output( 'webhooks', array(
		'id'              => array( 'type' => 'integer' ),
		'name'            => array( 'type' => array( 'string', 'null' ) ),
		'target_url'      => array( 'type' => array( 'string', 'null' ) ),
		'events'          => array( 'type' => array( 'array', 'null' ) ),
		'headers'         => array( 'type' => array( 'object', 'null' ) ),
		'payload_template'=> array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function() {
		$rows  = wpFluent()->table( 'fbs_metas' )->where( 'object_type', 'outgoing_webhook' )->orderBy( 'id', 'DESC' )->get();
		$items = array();
		foreach ( $rows as $r ) {
			$meta    = maybe_unserialize( $r->value ?? '' );
			$meta    = is_array( $meta ) ? $meta : array();
			$items[] = array(
				'id'               => (int) $r->id,
				'name'             => $meta['name'] ?? null,
				'target_url'       => $meta['target_url'] ?? null,
				'events'           => $meta['events'] ?? null,
				'headers'          => $meta['headers'] ?? null,
				'payload_template' => $meta['payload_template'] ?? null,
			);
		}
		return array( 'webhooks' => $items, 'total' => count( $items ) );
	},
) );

// =========================================================================
// §4.25.2 — create-outgoing-webhook (idempotent:false)
// =========================================================================
$reg->write( 'fluent-boards/create-outgoing-webhook', array(
	'label'       => 'Create Outgoing Webhook',
	'description' => 'Create an outgoing webhook subscription. events is an array of vendor event names (e.g. task.created, task.updated, task.deleted, task.completed, comment.created). target_url is validated against SSRF rules.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'name', 'target_url', 'events' ),
		'properties' => array(
			'name'            => array( 'type' => 'string' ),
			'target_url'      => array( 'type' => 'string' ),
			'events'          => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'headers'         => array( 'type' => 'object' ),
			'payload_template'=> array( 'type' => 'string' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'id' => array( 'type' => 'integer' ) ) ),
	'annotations'  => array( 'idempotent' => false ),
	'callback'     => function( $input ) {
		$name = sanitize_text_field( $input['name'] ?? '' );
		$url  = $input['target_url'] ?? '';
		if ( ! $name || ! $url ) {
			return fluent_abilities_error( 'ability_invalid_input', 'name and target_url are required.' );
		}
		$validated = fluent_abilities_validate_url( $url );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}
		$events = array_values( array_map( 'sanitize_text_field', (array) ( $input['events'] ?? array() ) ) );
		if ( empty( $events ) ) {
			return fluent_abilities_error( 'ability_invalid_input', 'events must be a non-empty array.' );
		}
		$now    = current_time( 'mysql' );
		$new_id = wpFluent()->table( 'fbs_metas' )->insertGetId( array(
			'object_id'   => 0,
			'object_type' => 'outgoing_webhook',
			'key'         => 'config',
			'value'       => maybe_serialize( array(
				'name'             => $name,
				'target_url'       => $validated,
				'events'           => $events,
				'headers'          => is_array( $input['headers'] ?? null ) ? $input['headers'] : array(),
				'payload_template' => isset( $input['payload_template'] ) ? (string) $input['payload_template'] : null,
			) ),
			'created_at'  => $now,
			'updated_at'  => $now,
		) );
		return array( 'success' => true, 'id' => (int) $new_id );
	},
) );

// =========================================================================
// §4.25.3 — update-outgoing-webhook
// =========================================================================
$reg->write( 'fluent-boards/update-outgoing-webhook', array(
	'label'       => 'Update Outgoing Webhook',
	'description' => 'Patch an outgoing webhook. Any subset of name/target_url/events/headers/payload_template may be provided.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'id' ),
		'properties' => array(
			'id'              => array( 'type' => 'integer' ),
			'name'            => array( 'type' => 'string' ),
			'target_url'      => array( 'type' => 'string' ),
			'events'          => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'headers'         => array( 'type' => 'object' ),
			'payload_template'=> array( 'type' => 'string' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'id' => array( 'type' => 'integer' ) ) ),
	'callback'     => function( $input ) {
		$id  = (int) $input['id'];
		$row = wpFluent()->table( 'fbs_metas' )->where( 'id', $id )->where( 'object_type', 'outgoing_webhook' )->first();
		if ( ! $row ) {
			return fluent_abilities_error( 'not_found', 'Outgoing webhook not found.' );
		}
		$meta = maybe_unserialize( $row->value ?? '' );
		$meta = is_array( $meta ) ? $meta : array();
		if ( isset( $input['name'] ) )       { $meta['name']       = sanitize_text_field( $input['name'] ); }
		if ( isset( $input['target_url'] ) ) {
			$v = fluent_abilities_validate_url( $input['target_url'] );
			if ( is_wp_error( $v ) ) { return $v; }
			$meta['target_url'] = $v;
		}
		if ( isset( $input['events'] ) ) {
			$meta['events'] = array_values( array_map( 'sanitize_text_field', (array) $input['events'] ) );
		}
		if ( isset( $input['headers'] ) && is_array( $input['headers'] ) ) {
			$meta['headers'] = $input['headers'];
		}
		if ( isset( $input['payload_template'] ) ) {
			$meta['payload_template'] = (string) $input['payload_template'];
		}
		wpFluent()->table( 'fbs_metas' )->where( 'id', $id )->update( array(
			'value'      => maybe_serialize( $meta ),
			'updated_at' => current_time( 'mysql' ),
		) );
		return array( 'success' => true, 'id' => $id );
	},
) );

// =========================================================================
// §4.25.4 — delete-outgoing-webhook (idempotent:false)
// =========================================================================
$reg->delete( 'fluent-boards/delete-outgoing-webhook', array(
	'label'       => 'Delete Outgoing Webhook',
	'description' => 'Delete an outgoing webhook. No further events will be dispatched to its target_url.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'id' ),
		'properties' => array(
			'id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'id' => array( 'type' => 'integer' ) ) ),
	'annotations'  => array( 'idempotent' => false ),
	'callback'     => function( $input ) {
		$id = (int) $input['id'];
		wpFluent()->table( 'fbs_metas' )->where( 'id', $id )->where( 'object_type', 'outgoing_webhook' )->delete();
		return array( 'success' => true, 'id' => $id );
	},
) );
