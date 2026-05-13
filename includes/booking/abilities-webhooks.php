<?php
/**
 * FluentBooking — Pro Webhooks (cluster 4.12).
 *
 * Pro Webhook model at fluent-booking-pro/app/Models/Webhook.php. WebhookController
 * provides the management surface; webhook delivery is handled by Pro
 * Services/Integrations/Webhook/.
 *
 * Note on storage: §7.Q1 in the research file flagged that the Webhook table
 * schema is not yet pinpointed. Implementation here uses the model API where
 * available, falling back to wpFluent's table() with table_name 'fcal_webhooks'.
 *
 *   - fluent-booking/list-webhooks      (read)
 *   - fluent-booking/get-webhook        (read)
 *   - fluent-booking/create-webhook     (write)
 *   - fluent-booking/update-webhook     (write)
 *   - fluent-booking/delete-webhook     (delete)
 *   - fluent-booking/test-webhook       (write — fires a synthetic payload)
 *
 * Capability override: manage_options.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolve a Webhook row reader/writer — prefers the Eloquent model when present,
 * otherwise falls back to raw `wpFluent()->table('fcal_webhooks')`.
 *
 * @return string Returns 'model' or 'table'.
 */
function fluent_booking_webhook_mode() {
	if ( class_exists( '\FluentBookingPro\App\Models\Webhook' ) ) {
		return 'model';
	}
	return 'table';
}

function fluent_booking_register_webhooks_abilities() {

	$reg = new Fluent_Abilities_Registrar( 'booking' );

	// =========================================================================
	// 4.12.1 — LIST WEBHOOKS
	// =========================================================================

	$reg->read( 'fluent-booking/list-webhooks', array(
		'label'       => 'List FluentBooking Webhooks',
		'description' => 'List configured FluentBooking webhooks with optional event_type / status filters and pagination.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge(
				fluent_abilities_pagination_schema(),
				array(
					'event_type' => array( 'type' => 'string', 'description' => 'Filter by configured event type (e.g. booking_created)' ),
					'status'     => array( 'type' => 'string', 'enum' => array( 'active', 'inactive' ) ),
				)
			),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'webhooks', array(
			'id'         => array( 'type' => 'integer' ),
			'name'       => array( 'type' => 'string' ),
			'target_url' => array( 'type' => 'string' ),
			'events'     => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'status'     => array( 'type' => 'string' ),
			'created_at' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$page_args = fluent_abilities_pagination( $input, 20 );

			$query = wpFluent()->table( 'fcal_webhooks' );
			if ( ! empty( $input['status'] ) ) {
				$query->where( 'status', sanitize_text_field( $input['status'] ) );
			}
			$total = (int) $query->count();

			$rows = $query->orderBy( 'id', 'DESC' )
				->offset( $page_args['offset'] )
				->limit( $page_args['per_page'] )
				->get();

			$event_filter = isset( $input['event_type'] ) ? sanitize_text_field( $input['event_type'] ) : '';
			$webhooks     = array();
			foreach ( $rows as $row ) {
				$events = maybe_unserialize( $row->events ?? '' );
				if ( ! is_array( $events ) ) {
					$events = array();
				}
				if ( $event_filter !== '' && ! in_array( $event_filter, $events, true ) ) {
					continue;
				}
				$webhooks[] = array(
					'id'         => (int) $row->id,
					'name'       => (string) ( $row->name ?? '' ),
					'target_url' => (string) ( $row->target_url ?? '' ),
					'events'     => array_values( $events ),
					'status'     => (string) ( $row->status ?? 'active' ),
					'created_at' => $row->created_at ? (string) $row->created_at : null,
				);
			}

			return array(
				'webhooks' => $webhooks,
				'total'    => $total,
				'page'     => $page_args['page'],
				'per_page' => $page_args['per_page'],
			);
		},
	) );

	// =========================================================================
	// 4.12.2 — GET WEBHOOK
	// =========================================================================

	$reg->read( 'fluent-booking/get-webhook', array(
		'label'       => 'Get FluentBooking Webhook',
		'description' => 'Return a single webhook by ID. The `secret` field is redacted from output.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'         => array( 'type' => 'integer' ),
			'name'       => array( 'type' => 'string' ),
			'target_url' => array( 'type' => 'string' ),
			'events'     => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'status'     => array( 'type' => 'string' ),
			'has_secret' => array( 'type' => 'boolean' ),
			'headers'    => array( 'type' => array( 'object', 'array', 'null' ) ),
			'created_at' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$row = wpFluent()->table( 'fcal_webhooks' )
				->where( 'id', (int) $input['id'] )
				->first();
			if ( ! $row ) {
				return fluent_abilities_error( 'not_found', 'Webhook not found' );
			}

			$events  = maybe_unserialize( $row->events ?? '' );
			$headers = maybe_unserialize( $row->headers ?? '' );

			return array(
				'id'         => (int) $row->id,
				'name'       => (string) ( $row->name ?? '' ),
				'target_url' => (string) ( $row->target_url ?? '' ),
				'events'     => is_array( $events ) ? array_values( $events ) : array(),
				'status'     => (string) ( $row->status ?? 'active' ),
				'has_secret' => ! empty( $row->secret ),
				'headers'    => is_array( $headers ) ? $headers : null,
				'created_at' => $row->created_at ? (string) $row->created_at : null,
			);
		},
	) );

	// =========================================================================
	// 4.12.3 — CREATE WEBHOOK
	// =========================================================================

	$reg->write( 'fluent-booking/create-webhook', array(
		'label'       => 'Create FluentBooking Webhook',
		'description' => 'Register a new webhook target URL that receives configured events. URL is SSRF-validated (no private / loopback IPs).',
		'capability'  => 'manage_options',
		'annotations' => array( 'idempotent' => false ),
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'name', 'target_url', 'events' ),
			'properties' => array(
				'name'       => array( 'type' => 'string' ),
				'target_url' => array( 'type' => 'string', 'description' => 'HTTPS URL that receives POSTed event payloads' ),
				'events'     => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'secret'     => array( 'type' => 'string', 'description' => 'Optional shared secret for signing outgoing requests' ),
				'status'     => array(
					'type'    => 'string',
					'enum'    => array( 'active', 'inactive' ),
					'default' => 'active',
				),
				'headers'    => array( 'type' => array( 'object', 'array' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'         => array( 'type' => 'integer' ),
			'name'       => array( 'type' => 'string' ),
			'target_url' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$url = fluent_abilities_validate_url( $input['target_url'] );
			if ( is_wp_error( $url ) ) {
				return $url;
			}

			$events = isset( $input['events'] ) ? (array) $input['events'] : array();
			$events = array_values( array_filter( array_map( 'sanitize_text_field', $events ) ) );
			if ( empty( $events ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'events must include at least one event slug' );
			}

			$insert = array(
				'name'       => sanitize_text_field( $input['name'] ),
				'target_url' => esc_url_raw( $url ),
				'events'     => maybe_serialize( $events ),
				'status'     => sanitize_text_field( $input['status'] ?? 'active' ),
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			);

			if ( ! empty( $input['secret'] ) ) {
				$insert['secret'] = sanitize_text_field( $input['secret'] );
			}
			if ( isset( $input['headers'] ) ) {
				$insert['headers'] = maybe_serialize( (array) $input['headers'] );
			}

			$id = wpFluent()->table( 'fcal_webhooks' )->insert( $insert );

			return array(
				'success'    => true,
				'id'         => (int) $id,
				'name'       => $insert['name'],
				'target_url' => $insert['target_url'],
			);
		},
	) );

	// =========================================================================
	// 4.12.4 — UPDATE WEBHOOK
	// =========================================================================

	$reg->write( 'fluent-booking/update-webhook', array(
		'label'       => 'Update FluentBooking Webhook',
		'description' => 'Partial-update a webhook row. Omit fields to preserve their existing values.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'         => array( 'type' => 'integer' ),
				'name'       => array( 'type' => 'string' ),
				'target_url' => array( 'type' => 'string' ),
				'events'     => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'secret'     => array( 'type' => 'string' ),
				'status'     => array( 'type' => 'string', 'enum' => array( 'active', 'inactive' ) ),
				'headers'    => array( 'type' => array( 'object', 'array' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$id   = (int) $input['id'];
			$row  = wpFluent()->table( 'fcal_webhooks' )->where( 'id', $id )->first();
			if ( ! $row ) {
				return fluent_abilities_error( 'not_found', 'Webhook not found' );
			}

			$update = array();
			if ( isset( $input['name'] ) ) {
				$update['name'] = sanitize_text_field( $input['name'] );
			}
			if ( isset( $input['target_url'] ) ) {
				$url = fluent_abilities_validate_url( $input['target_url'] );
				if ( is_wp_error( $url ) ) {
					return $url;
				}
				$update['target_url'] = esc_url_raw( $url );
			}
			if ( isset( $input['events'] ) ) {
				$events = array_values( array_filter( array_map( 'sanitize_text_field', (array) $input['events'] ) ) );
				$update['events'] = maybe_serialize( $events );
			}
			if ( isset( $input['secret'] ) ) {
				$update['secret'] = sanitize_text_field( $input['secret'] );
			}
			if ( isset( $input['status'] ) ) {
				$update['status'] = sanitize_text_field( $input['status'] );
			}
			if ( isset( $input['headers'] ) ) {
				$update['headers'] = maybe_serialize( (array) $input['headers'] );
			}

			if ( ! empty( $update ) ) {
				$update['updated_at'] = current_time( 'mysql' );
				wpFluent()->table( 'fcal_webhooks' )->where( 'id', $id )->update( $update );
			}

			return array( 'success' => true, 'id' => $id );
		},
	) );

	// =========================================================================
	// 4.12.5 — DELETE WEBHOOK
	// =========================================================================

	$reg->delete( 'fluent-booking/delete-webhook', array(
		'label'       => 'Delete FluentBooking Webhook',
		'description' => 'Remove a webhook row by ID.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'      => array( 'type' => 'integer' ),
			'deleted' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$id      = (int) $input['id'];
			$deleted = wpFluent()->table( 'fcal_webhooks' )->where( 'id', $id )->delete();
			return array(
				'success' => true,
				'id'      => $id,
				'deleted' => (int) $deleted,
			);
		},
	) );

	// =========================================================================
	// 4.12.6 — TEST WEBHOOK
	// =========================================================================

	$reg->write( 'fluent-booking/test-webhook', array(
		'label'       => 'Test FluentBooking Webhook',
		'description' => 'POST a synthetic payload to a webhook\'s target_url and report the HTTP response code + body snippet.',
		'capability'  => 'manage_options',
		'annotations' => array( 'idempotent' => false ),
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'      => array( 'type' => 'integer' ),
				'payload' => array( 'type' => array( 'object', 'array' ), 'description' => 'Optional override payload' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'             => array( 'type' => 'integer' ),
			'response_code'  => array( 'type' => array( 'integer', 'null' ) ),
			'response_body'  => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$id  = (int) $input['id'];
			$row = wpFluent()->table( 'fcal_webhooks' )->where( 'id', $id )->first();
			if ( ! $row ) {
				return fluent_abilities_error( 'not_found', 'Webhook not found' );
			}

			$url = fluent_abilities_validate_url( $row->target_url );
			if ( is_wp_error( $url ) ) {
				return $url;
			}

			$payload = isset( $input['payload'] ) ? (array) $input['payload'] : array(
				'event'   => 'test_ping',
				'origin'  => home_url(),
				'sent_at' => gmdate( 'c' ),
			);

			$args = array(
				'method'  => 'POST',
				'timeout' => 10,
				'body'    => wp_json_encode( $payload ),
				'headers' => array(
					'Content-Type' => 'application/json',
					'X-FluentBooking-Webhook-Test' => '1',
				),
			);

			$response = wp_remote_post( $url, $args );
			if ( is_wp_error( $response ) ) {
				return fluent_abilities_error( $response->get_error_code() ?: 'http_failure', $response->get_error_message() );
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$body = (string) wp_remote_retrieve_body( $response );

			return array(
				'success'       => $code >= 200 && $code < 300,
				'id'            => $id,
				'response_code' => $code,
				'response_body' => substr( $body, 0, 500 ),
			);
		},
	) );

}
add_action( 'wp_abilities_api_init', 'fluent_booking_register_webhooks_abilities' );
