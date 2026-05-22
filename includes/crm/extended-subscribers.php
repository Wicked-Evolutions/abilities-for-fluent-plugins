<?php
/**
 * FluentCRM Subscriber-extension ability surface — §5.1 (10) + §5.2 (5).
 *
 * §5.1 — single-route extension reads (cross-plugin / denormalized views).
 * §5.2 — bulk + property + sync operations.
 *
 * Source-side SubscriberPolicy:
 *   GET → fcrm_read_contacts
 *   non-GET → fcrm_manage_contacts
 *   delete-tier → fcrm_manage_contacts_delete
 *   handleBulkActions special-cases per action_name:
 *     add_to_email_sequence → fcrm_manage_emails
 *     add_to_automation     → fcrm_write_funnels
 *     delete_contacts       → fcrm_manage_contacts_delete
 *     else                  → fcrm_manage_contacts
 *
 * mcp.public: false for all 15 (subscriber PII).
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', 'fluent_abilities_crm_register_extended_subscribers', 11 );

function fluent_abilities_crm_register_extended_subscribers() {

	$reg = new Fluent_Abilities_Registrar( 'crm' );

	$proxy = static function ( $method, $route, $params = array() ) {
		$req = new WP_REST_Request( $method, $route );
		foreach ( (array) $params as $k => $v ) {
			if ( null !== $v && '' !== $v ) {
				$req->set_param( $k, $v );
			}
		}
		$res = rest_do_request( $req );
		return $res->is_error() ? $res->as_error() : $res->get_data();
	};

	$obj = array( 'type' => 'object', 'additionalProperties' => true );

	// =========================================================================
	// §5.1 — Subscriber extension reads (10)
	// =========================================================================

	$reg->read( 'fluent-crm/get-contact-form-submissions', array(
		'label'         => 'Get CRM Contact Form Submissions',
		'description'   => 'Fluent Forms entries submitted by a contact. Source: SubscriberController::getFormSubmissions (GET /subscribers/{id}/form-submissions). Capability: fcrm_read_contacts.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array( 'id' => array( 'type' => 'integer' ) ),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'submissions' => array( 'type' => 'array', 'items' => $obj ),
			'total'       => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			return $proxy( 'GET', '/fluent-crm/v2/subscribers/' . $id . '/form-submissions' );
		},
	) );

	$reg->read( 'fluent-crm/get-contact-support-tickets', array(
		'label'         => 'Get CRM Contact Support Tickets',
		'description'   => 'Fluent Support tickets associated with a contact. Source: SubscriberController::getSupportTickets (GET /subscribers/{id}/support-tickets). Capability: fcrm_read_contacts.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array( 'id' => array( 'type' => 'integer' ) ),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			// P4b item-union (vendor-source verified): SubscriberController::getSupportTickets
			// returns `tickets` as a structured object {data:[],total:int,columns_config:{}}
			// from apply_filters('fluentcrm-get_support_tickets_<provider>', ...) — NOT a
			// row array. Union object|array; do not array-coerce.
			'tickets' => array( 'type' => array( 'object', 'array' ), 'additionalProperties' => true ),
			'total'   => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			return $proxy( 'GET', '/fluent-crm/v2/subscribers/' . $id . '/support-tickets' );
		},
	) );


	$reg->read( 'fluent-crm/get-contact-dynamic-item-view', array(
		'label'         => 'Get CRM Contact Dynamic Item View',
		'description'   => 'Operator-extensible dynamic view payload for a contact. Shape varies by item_type. Source: SubscriberController::getDynamicItemView (GET /subscribers/{id}/dynamic-item-view).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id', 'item_type' ),
			'properties' => array(
				'id'        => array( 'type' => 'integer' ),
				'item_type' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => $obj,
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			$q  = $input;
			unset( $q['id'] );
			return $proxy( 'GET', '/fluent-crm/v2/subscribers/' . $id . '/dynamic-item-view', $q );
		},
	) );

	$reg->write( 'fluent-crm/get-contact-external-view', array(
		'label'         => 'Get Or Set CRM Contact External View',
		'description'   => 'Operator-rendered external-view payload + toggle. GET returns { html, settings }; passing settings switches to POST behavior. Source: SubscriberController::getExternalView / setExternalView (GET|POST /subscribers/{id}/external_view).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'       => array( 'type' => 'integer' ),
				'settings' => $obj,
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'html'     => array( 'type' => array( 'string', 'null' ) ),
			'settings' => $obj,
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id     = (int) ( $input['id'] ?? 0 );
			$method = isset( $input['settings'] ) ? 'POST' : 'GET';
			$q      = $input;
			unset( $q['id'] );
			return $proxy( $method, '/fluent-crm/v2/subscribers/' . $id . '/external_view', $q );
		},
	) );

	$reg->read( 'fluent-crm/get-contact-url-metrics', array(
		'label'         => 'Get CRM Contact Per-URL Click Metrics',
		'description'   => 'Per-link click history for a contact. Source: SubscriberController::getUrlMetrics (GET /subscribers/{id}/url-metrics). Capability: fcrm_read_contacts.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array( 'id' => array( 'type' => 'integer' ) ),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'links' => array( 'type' => 'array', 'items' => $obj ),
			'total' => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			return $proxy( 'GET', '/fluent-crm/v2/subscribers/' . $id . '/url-metrics' );
		},
	) );

	$reg->read( 'fluent-crm/list-subscriber-tracking-events', array(
		'label'         => 'List CRM Subscriber Tracking Events',
		'description'   => 'Route-mapped per-subscriber tracking events. Sister to v1.1.3 list-contact-events (research §7.6 — design language tension noted). Source: SubscriberController::getTrackingEvents (GET /subscribers/{id}/tracking-events).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array_merge(
				array( 'id' => array( 'type' => 'integer' ) ),
				fluent_abilities_pagination_schema()
			),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'events', $obj ),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			$q  = $input;
			unset( $q['id'] );
			return fluent_abilities_normalize_collection( $proxy( 'GET', '/fluent-crm/v2/subscribers/' . $id . '/tracking-events', $q ), 'events' );
		},
	) );

	// fluent-crm/list-subscribers-prev-next-ids — REMOVED (v1.4.0 P7 close).
	// Never functional since v2.0.0: schema's only required field is `id`, but
	// vendor SubscriberController::getPrevNextIds never reads `id` and requires
	// `filter_type` + `current_id` — every schema-valid call was rejected
	// ("filter_type and current_id are required"). No working contract to
	// preserve; unregistered. See docs/vendor-map/fluent-crm.json + docs/P7-CLOSE.md.


	// =========================================================================
	// §5.2 — Subscriber bulk + property + sync operations (5)
	// =========================================================================

	$reg->write( 'fluent-crm/update-subscribers-property', array(
		'label'         => 'Update CRM Subscribers Single Property',
		'description'   => 'Set one property (status/country/source/etc.) across many subscribers. Source: SubscriberController::updateProperty (PUT /subscribers/subscribers-property). Capability: fcrm_manage_contacts.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'subscriber_ids', 'property', 'value' ),
			'properties' => array(
				'subscriber_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'property'       => array( 'type' => 'string' ),
				'value'          => array( 'type' => array( 'string', 'integer', 'boolean', 'null' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'updated_count' => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'PUT', '/fluent-crm/v2/subscribers/subscribers-property', $input );
		},
	) );




	$reg->read( 'fluent-crm/export-subscribers', array(
		'label'         => 'Export CRM Subscribers',
		'description'   => 'Export subscribers as CSV or JSON. Long-running operation; returns download URL or inline payload. Note: the column-selection list is named `fields` here, but the vendor export handler reads the selected columns from a `columns` key — when narrowing exported columns, send the column slugs under `fields` (this wrapper forwards them) understanding the vendor maps them to its `columns` parameter. Source: SubscriberController::exportSubscribers (GET|POST /subscribers-export). Capability: fcrm_read_contacts.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => array(
				'format'  => array(
					'type'        => 'string',
					'description' => 'csv or json (default csv).',
				),
				'filters' => $obj,
				'fields'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/subscribers-export', $input );
		},
	) );

}
