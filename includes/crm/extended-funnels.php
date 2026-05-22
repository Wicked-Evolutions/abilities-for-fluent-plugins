<?php
/**
 * FluentCRM Funnel (automation) extension ability surface
 * — §5.9 (9) + §5.10 (8) + §5.11 (9) = 26 abilities.
 *
 * Source-side FunnelPolicy:
 *   GET → fcrm_read_funnels
 *   non-GET → fcrm_write_funnels
 *   delete-tier (delete/removeBulkSubscribers/deleteSubscribers) → fcrm_delete_funnels
 *   handleBulkAction action_name=delete_funnels → fcrm_delete_funnels
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', 'fluent_abilities_crm_register_extended_funnels', 11 );

function fluent_abilities_crm_register_extended_funnels() {

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

	$obj         = array( 'type' => 'object', 'additionalProperties' => true );
	$id_required = function ( array $extra = array() ) {
		return array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array_merge(
				array( 'id' => array( 'type' => 'integer', 'description' => 'Funnel ID.' ) ),
				$extra
			),
		);
	};

	// =========================================================================
	// §5.9 — Funnel atomic operations + clone + trigger (9)
	// =========================================================================

	$reg->read( 'fluent-crm/list-funnel-triggers', array(
		'label'         => 'List CRM Funnel Triggers Dictionary',
		'description'   => 'Registered funnel-trigger dictionary, keyed by trigger name (e.g. `user_register`, `fluent_crm/contact_created`, `fluent_cart/order_paid_done`). Load-bearing — no prior wrapper. Source: FunnelController::getTriggers (GET /funnels/triggers). Capability: fcrm_read_funnels. Response shape: triggers is a name-keyed object (WordPress-native assoc-array serialization), each entry has `category`, `label`, `description`, and rendering hints (icon/svg).',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'triggers' => array(
					'type'                 => 'object',
					'additionalProperties' => true,
					'description'          => 'Trigger map keyed by trigger name.',
				),
			),
		),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/funnels/triggers' );
		},
	) );

	$reg->write( 'fluent-crm/save-funnel-sequences', array(
		'label'         => 'Save CRM Funnel Sequences Atomically',
		'description'   => 'Atomic multi-step save with index reset. Source: FunnelController::saveFunnelSequences (POST /funnels/funnel/save-funnel-sequences). Capability: fcrm_write_funnels.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'funnel_id', 'sequences' ),
			'properties' => array(
				'funnel_id' => array( 'type' => 'integer' ),
				'sequences' => array( 'type' => 'array', 'items' => $obj ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/funnels/funnel/save-funnel-sequences', $input );
		},
	) );

	$reg->write( 'fluent-crm/save-funnel-email-action-fallback', array(
		'label'         => 'Save CRM Funnel Email-Action Fallback Settings',
		'description'   => 'Save email-action fallback settings for one step. Source: FunnelController::saveEmailActionFallback (POST /funnels/funnel/save-email-action-fallback).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'funnel_id', 'step_id' ),
			'properties' => array(
				'funnel_id'         => array( 'type' => 'integer' ),
				'step_id'           => array( 'type' => 'integer' ),
				'fallback_settings' => $obj,
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/funnels/funnel/save-email-action-fallback', $input );
		},
	) );

	$reg->write( 'fluent-crm/save-funnel-sequences-step', array(
		'label'         => 'Save CRM Funnel Step List Atomically',
		'description'   => 'Atomic step list save for one funnel. Source: FunnelController::saveSequences (POST /funnels/{id}/sequences).',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required( array(
			'sequences' => array( 'type' => 'array', 'items' => $obj ),
		) ),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			$q  = $input;
			unset( $q['id'] );
			return $proxy( 'POST', '/fluent-crm/v2/funnels/' . $id . '/sequences', $q );
		},
	) );

	$reg->write( 'fluent-crm/save-funnel-email-action', array(
		'label'         => 'Save CRM Funnel Email Action',
		'description'   => 'Save one email-action step. Source: FunnelController::saveEmailAction (POST /funnels/{id}/sequences/save-email-action).',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required( array(
			'step_id'      => array( 'type' => 'integer' ),
			'email_action' => $obj,
		) ),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			$q  = $input;
			unset( $q['id'] );
			return $proxy( 'POST', '/fluent-crm/v2/funnels/' . $id . '/sequences/save-email-action', $q );
		},
	) );

	$reg->write( 'fluent-crm/clone-funnel', array(
		'label'         => 'Clone CRM Funnel',
		'description'   => 'Clone a funnel. Distinct from v1.1.3 duplicate-automation (#29) — research §5.32.4 + §7.6 documents the design language tension between the two surfaces. Source: FunnelController::cloneFunnel (POST /funnels/{id}/clone).',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required(),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return fluent_abilities_project_response( $proxy( 'POST', '/fluent-crm/v2/funnels/' . (int) ( $input['id'] ?? 0 ) . '/clone' ) );
		},
	) );

	$reg->write( 'fluent-crm/change-funnel-trigger', array(
		'label'         => 'Change CRM Funnel Trigger',
		'description'   => 'Swap a funnel\'s trigger. May invalidate enrolled subscribers — destructive variant noted. Source: FunnelController::changeTrigger (PUT /funnels/{id}/change-trigger).',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required( array(
			'trigger_name' => array( 'type' => 'string' ),
		) ),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			$q  = $input;
			unset( $q['id'] );
			return $proxy( 'PUT', '/fluent-crm/v2/funnels/' . $id . '/change-trigger', $q );
		},
	) );

	$reg->write( 'fluent-crm/update-funnel-title', array(
		'label'         => 'Update CRM Funnel Title',
		'description'   => 'Update funnel title. Note: the identifying field is `id` (the funnel ID, not `funnel_id` or `campaign_id`). Source: FunnelController::updateTitle (PUT /funnels/funnel/{id}/title).',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required( array(
			'title' => array( 'type' => 'string' ),
		) ),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			return $proxy( 'PUT', '/fluent-crm/v2/funnels/funnel/' . $id . '/title', array( 'title' => $input['title'] ?? '' ) );
		},
	) );

	$reg->write( 'fluent-crm/update-funnel-labels', array(
		'label'         => 'Update CRM Funnel Labels',
		'description'   => 'Set label IDs on a funnel. Source: FunnelController::updateLabels (PUT /funnels/{id}/update-labels).',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required( array(
			'labels' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
		) ),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			$q  = $input;
			unset( $q['id'] );
			return $proxy( 'PUT', '/fluent-crm/v2/funnels/' . $id . '/update-labels', $q );
		},
	) );

	// =========================================================================
	// §5.10 — Funnel subscriber state + reports (8)
	// =========================================================================

	$reg->read( 'fluent-crm/list-funnel-subscribers', array(
		'label'         => 'List CRM Funnel Subscribers',
		'description'   => 'Paginated subscribers enrolled in a funnel. Source: FunnelController::getSubscribers (GET /funnels/{id}/subscribers). Capability: fcrm_read_funnels.',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required( array_merge(
			array(
				'status' => array( 'type' => 'string', 'description' => 'active, completed, cancelled' ),
			),
			fluent_abilities_pagination_schema()
		) ),
		'output_schema' => fluent_abilities_schema_list_output( 'subscribers', $obj ),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			$q  = $input;
			unset( $q['id'] );
			return fluent_abilities_unwrap_paginator( $proxy( 'GET', '/fluent-crm/v2/funnels/' . $id . '/subscribers', $q ), 'subscribers' );
		},
	) );

	$reg->delete( 'fluent-crm/delete-funnel-subscribers', array(
		'label'         => 'Delete CRM Funnel Subscribers',
		'description'   => 'Remove subscribers from a funnel. Source: FunnelController::deleteSubscribers (DELETE /funnels/{id}/subscribers). Capability: fcrm_delete_funnels.',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required( array(
			'subscriber_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
		) ),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'removed_count' => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			$q  = $input;
			unset( $q['id'] );
			return $proxy( 'DELETE', '/fluent-crm/v2/funnels/' . $id . '/subscribers', $q );
		},
	) );

	$reg->read( 'fluent-crm/get-funnel-subscriber-detail', array(
		'label'         => 'Get CRM Funnel Subscriber Detail',
		'description'   => 'Per-subscriber funnel progress (current step + history). Source: FunnelController::getSubscriberDetail (GET /funnels/{id}/subscribers/{contact_id}).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id', 'contact_id' ),
			'properties' => array(
				'id'         => array( 'type' => 'integer' ),
				'contact_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			$cid = (int) ( $input['contact_id'] ?? 0 );
			return $proxy( 'GET', '/fluent-crm/v2/funnels/' . $id . '/subscribers/' . $cid );
		},
	) );

	$reg->read( 'fluent-crm/get-funnel-report', array(
		'label'         => 'Get CRM Funnel Aggregate Report',
		'description'   => 'Aggregate metrics over a date range. Source: FunnelController::getReport (GET /funnels/{id}/report).',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required( array(
			'date_from' => array( 'type' => 'string' ),
			'date_to'   => array( 'type' => 'string' ),
		) ),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			$q  = $input;
			unset( $q['id'] );
			return $proxy( 'GET', '/fluent-crm/v2/funnels/' . $id . '/report', $q );
		},
	) );

	$reg->read( 'fluent-crm/get-funnel-email-reports', array(
		'label'         => 'Get CRM Funnel Per-Step Email Metrics',
		'description'   => 'Per-step email metrics for a funnel. Source: FunnelController::getEmailReports (GET /funnels/{id}/email_reports).',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required( array(
			'date_from' => array( 'type' => 'string' ),
			'date_to'   => array( 'type' => 'string' ),
		) ),
		'output_schema' => fluent_abilities_schema_collection_output( 'steps', $obj ),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			$q  = $input;
			unset( $q['id'] );
			return $proxy( 'GET', '/fluent-crm/v2/funnels/' . $id . '/email_reports', $q );
		},
	) );

	$reg->write( 'fluent-crm/update-funnel-subscriber-status', array(
		'label'         => 'Update CRM Funnel Subscriber Status',
		'description'   => 'Move a single funnel subscriber between states (active/paused/cancelled/completed). Source: FunnelController::updateSubscriberStatus (PUT /funnels/{id}/subscribers/{subscriber_id}/status).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id', 'subscriber_id', 'status' ),
			'properties' => array(
				'id'            => array( 'type' => 'integer' ),
				'subscriber_id' => array( 'type' => 'integer' ),
				'status'        => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id  = (int) ( $input['id'] ?? 0 );
			$sid = (int) ( $input['subscriber_id'] ?? 0 );
			return $proxy( 'PUT', '/fluent-crm/v2/funnels/' . $id . '/subscribers/' . $sid . '/status', array( 'status' => $input['status'] ?? '' ) );
		},
	) );

	$reg->write( 'fluent-crm/advance-funnel-subscriber', array(
		'label'         => 'Advance CRM Funnel Subscriber To Step',
		'description'   => 'Jump enrollment to a specific step. Source: FunnelController::advanceSubscriber (POST /funnels/{id}/subscribers/{subscriber_id}/advance).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id', 'subscriber_id', 'to_step_id' ),
			'properties' => array(
				'id'            => array( 'type' => 'integer' ),
				'subscriber_id' => array( 'type' => 'integer' ),
				'to_step_id'    => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id  = (int) ( $input['id'] ?? 0 );
			$sid = (int) ( $input['subscriber_id'] ?? 0 );
			return $proxy( 'POST', '/fluent-crm/v2/funnels/' . $id . '/subscribers/' . $sid . '/advance', array( 'to_step_id' => (int) ( $input['to_step_id'] ?? 0 ) ) );
		},
	) );

	$reg->read( 'fluent-crm/list-subscriber-automations', array(
		'label'         => 'List CRM Subscriber Automations',
		'description'   => 'All funnels a contact is enrolled in. Source: FunnelController::subscriberAutomations (GET /funnels/subscriber/{subscriber_id}/automations).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'subscriber_id' ),
			'properties' => array(
				'subscriber_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'automations', $obj ),
		'callback'      => function ( $input ) use ( $proxy ) {
			$sid = (int) ( $input['subscriber_id'] ?? 0 );
			return fluent_abilities_normalize_collection( $proxy( 'GET', '/fluent-crm/v2/funnels/subscriber/' . $sid . '/automations' ), 'automations' );
		},
	) );

	// =========================================================================
	// §5.11 — Funnel templates + import + sync + bulk (9)
	// =========================================================================

	$reg->read( 'fluent-crm/list-funnel-templates', array(
		'label'         => 'List CRM Funnel Templates',
		'description'   => 'Bundled funnel templates from plugin distribution. Source: FunnelController::getTemplates (GET /funnels/templates). V10: vendor controller may TypeError on absent Pro/template-bundle state; registrar wraps the call and returns WP_Error instead of letting the fatal propagate.',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => fluent_abilities_schema_collection_output( 'templates', $obj ),
		'callback'      => function ( $input ) use ( $proxy ) {
			// V10: convert vendor-side TypeError into a typed WP_Error (P-K pattern).
			try {
				return $proxy( 'GET', '/fluent-crm/v2/funnels/templates' );
			} catch ( \Throwable $e ) {
				return new WP_Error( 'vendor_precondition_failed', 'FluentCRM funnel templates lookup failed: ' . $e->getMessage() );
			}
		},
	) );

	$reg->write( 'fluent-crm/import-funnel', array(
		'label'         => 'Import CRM Funnel From Export Definition',
		'description'   => 'Atomic import from a JSON-encoded funnel export. Source: FunnelController::importFunnel (POST /funnels/import).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'funnel_definition' ),
			'properties' => array(
				'funnel_definition' => $obj,
			),
		),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/funnels/import', $input );
		},
	) );

	$reg->read( 'fluent-crm/get-funnel-all-activities', array(
		'label'         => 'Get CRM Funnel Cross-Funnel Activity Stream',
		'description'   => 'Cross-funnel activity stream over a date range. Source: FunnelController::allActivities (GET /funnels/all-activities).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => array_merge(
				array(
					'date_from' => array( 'type' => 'string' ),
					'date_to'   => array( 'type' => 'string' ),
				),
				fluent_abilities_pagination_schema()
			),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'activities', $obj ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return fluent_abilities_normalize_collection( $proxy( 'GET', '/fluent-crm/v2/funnels/all-activities', $input ), 'activities' );
		},
	) );

	$reg->read( 'fluent-crm/get-funnel-syncable-counts', array(
		'label'         => 'Get CRM Funnel Syncable Subscriber Counts',
		'description'   => 'Number of subscribers eligible for new-steps sync. Source: FunnelController::getSyncableCounts (GET /funnels/{id}/syncable-counts).',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required(),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'count' => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/funnels/' . (int) ( $input['id'] ?? 0 ) . '/syncable-counts' );
		},
	) );

	$reg->write( 'fluent-crm/sync-funnel-new-steps', array(
		'label'         => 'Sync CRM Funnel New-Steps Re-Enrollment',
		'description'   => 'Re-enroll diff after step addition. Algorithmic. Source: FunnelController::syncNewSteps (POST /funnels/{id}/sync-new-steps).',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required(),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'enrolled_count' => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/funnels/' . (int) ( $input['id'] ?? 0 ) . '/sync-new-steps' );
		},
	) );

	$reg->write( 'fluent-crm/send-test-funnel-webhook', array(
		'label'         => 'Send Test CRM Funnel Webhook',
		'description'   => 'Developer-utility: round-trip a test payload to a configured webhook URL. mcp.public: false. Source: FunnelController::sendTestWebhook (POST /funnels/send-test-webhook).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'webhook_url' ),
			'properties' => array(
				'webhook_url' => array( 'type' => 'string' ),
				'payload'     => $obj,
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'response' => $obj,
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/funnels/send-test-webhook', $input );
		},
	) );

}
