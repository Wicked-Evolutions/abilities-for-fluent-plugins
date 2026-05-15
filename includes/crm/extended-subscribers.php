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

	$reg->read( 'fluent-crm/get-contact-purchase-history', array(
		'label'         => 'Get CRM Contact Purchase History',
		'description'   => 'Cross-provider order history (Woo/EDD/FluentCart/LifterLMS). Source: SubscriberController::getPurchaseHistory (GET /subscribers/{id}/purchase-history). Capability: fcrm_read_contacts. mcp.public: false (PII).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'       => array( 'type' => 'integer', 'description' => 'Subscriber ID.' ),
				'provider' => array( 'type' => 'string', 'description' => 'Filter by provider (woo, edd, fluent-cart, lifter).' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'orders'        => array( 'type' => 'array', 'items' => $obj ),
			'total'         => array( 'type' => 'integer' ),
			'total_revenue' => array( 'type' => array( 'number', 'string' ) ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			$q  = $input;
			unset( $q['id'] );
			return $proxy( 'GET', '/fluent-crm/v2/subscribers/' . $id . '/purchase-history', $q );
		},
	) );

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
			'tickets' => array( 'type' => 'array', 'items' => $obj ),
			'total'   => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			return $proxy( 'GET', '/fluent-crm/v2/subscribers/' . $id . '/support-tickets' );
		},
	) );

	$reg->read( 'fluent-crm/get-contact-info-widgets', array(
		'label'         => 'Get CRM Contact Sidebar Info Widgets',
		'description'   => 'Sidebar info widgets rendered for a contact in the operator UI. Source: SubscriberController::getInfoWidgets (GET /subscribers/{id}/info-widgets).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array( 'id' => array( 'type' => 'integer' ) ),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'widgets' => array( 'type' => 'array', 'items' => $obj ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			return $proxy( 'GET', '/fluent-crm/v2/subscribers/' . $id . '/info-widgets' );
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
			return $proxy( 'GET', '/fluent-crm/v2/subscribers/' . $id . '/tracking-events', $q );
		},
	) );

	$reg->read( 'fluent-crm/list-subscribers-prev-next-ids', array(
		'label'         => 'Get CRM Subscriber Prev/Next ID Pair',
		'description'   => 'Operator-UI navigation helper. Source: SubscriberController::getPrevNextIds (GET /subscribers/prev-next-ids).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array( 'id' => array( 'type' => 'integer' ) ),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'prev_id' => array( 'type' => array( 'integer', 'null' ) ),
			'next_id' => array( 'type' => array( 'integer', 'null' ) ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/subscribers/prev-next-ids', $input );
		},
	) );

	$reg->read( 'fluent-crm/search-contacts-fast', array(
		'label'         => 'Search CRM Contacts (Typeahead)',
		'description'   => 'Typeahead-style contact search. Distinct from search-contacts-advanced (which is composed AND-filters). Research §7.6 documents the three contact-search surfaces. Source: SubscriberController::searchContacts (GET /subscribers/search-contacts).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'q' ),
			'properties' => array(
				'q'     => array( 'type' => 'string' ),
				'limit' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'contacts', array(
			'id'        => array( 'type' => 'integer' ),
			'email'     => array( 'type' => 'string' ),
			'full_name' => array( 'type' => array( 'string', 'null' ) ),
			'status'    => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/subscribers/search-contacts', $input );
		},
	) );

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

	$reg->write( 'fluent-crm/sync-subscribers-segments', array(
		'label'         => 'Sync CRM Subscribers Tags + Lists Atomically',
		'description'   => 'Atomic add/remove of tags and lists across subscribers. Source: SubscriberController::syncSegments (POST /subscribers/sync-segments). Capability: fcrm_manage_contacts. V10: vendor controller may TypeError on validator/request shape when called via internal REST dispatch; registrar returns WP_Error instead of letting the fatal propagate.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'subscriber_ids' ),
			'properties' => array(
				'subscriber_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'add_tags'       => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'remove_tags'    => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'add_lists'      => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'remove_lists'   => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			// V10: convert vendor-side TypeError into a typed WP_Error (P-K pattern).
			try {
				return $proxy( 'POST', '/fluent-crm/v2/subscribers/sync-segments', $input );
			} catch ( \Throwable $e ) {
				return new WP_Error( 'vendor_precondition_failed', 'FluentCRM sync-subscribers-segments failed: ' . $e->getMessage() );
			}
		},
	) );

	$reg->write( 'fluent-crm/do-bulk-action-contacts', array(
		'label'         => 'Bulk Action On CRM Contacts',
		'description'   => 'Apply bulk action across subscribers (add_tags/remove_tags/add_lists/remove_lists/change_status/delete_contacts/add_to_automation/add_to_email_sequence). Source: SubscriberController::handleBulkActions (POST /subscribers/do-bulk-action). Capability cascades per action: delete_contacts → fcrm_manage_contacts_delete; add_to_email_sequence → fcrm_manage_emails; add_to_automation → fcrm_write_funnels.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'action_name', 'subscriber_ids' ),
			'properties' => array(
				'action_name'    => array(
					'type'        => 'string',
					'description' => 'add_tags, remove_tags, add_lists, remove_lists, change_status, delete_contacts, add_to_automation, add_to_email_sequence',
				),
				'subscriber_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'action_data'    => $obj,
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'message' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/subscribers/do-bulk-action', $input );
		},
	) );

	$reg->write( 'fluent-crm/bulk-add-update-contacts', array(
		'label'         => 'Batch Upsert CRM Contacts',
		'description'   => 'Batch upsert contacts by email. Source: SubscriberController::bulkAddUpdate (POST /subscribers/bulk-add-update). Capability: fcrm_manage_contacts.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'subscribers' ),
			'properties' => array(
				'subscribers' => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'required'             => array( 'email' ),
						'properties'           => array(
							'email'         => array( 'type' => 'string' ),
							'first_name'    => array( 'type' => 'string' ),
							'last_name'     => array( 'type' => 'string' ),
							'status'        => array( 'type' => 'string' ),
							'tags'          => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
							'lists'         => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
							'custom_fields' => $obj,
						),
						'additionalProperties' => true,
					),
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'created' => array( 'type' => 'integer' ),
			'updated' => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/subscribers/bulk-add-update', $input );
		},
	) );

	$reg->read( 'fluent-crm/export-subscribers', array(
		'label'         => 'Export CRM Subscribers',
		'description'   => 'Export subscribers as CSV or JSON. Long-running operation; returns download URL or inline payload. Source: SubscriberController::exportSubscribers (GET|POST /subscribers-export). Capability: fcrm_read_contacts.',
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
