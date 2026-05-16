<?php
/**
 * FluentCRM Pro marketing clusters:
 *  - §5.24 Sequences (Pro) extras  (8 abilities)
 *  - §5.25 Recurring Campaigns (Pro) (13 abilities)
 *  - §5.26 Dynamic Segments (Pro)   (9 abilities)
 *  - §5.27 Campaigns-Pro (Pro)       (7 abilities)
 *  - §5.28 Smart Links extras (Pro)  (1 ability)
 *
 * Total: 38 abilities. All Pro-tier. Capabilities cascade per cluster header.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', 'fluent_abilities_crm_register_extended_pro_marketing', 11 );

function fluent_abilities_crm_register_extended_pro_marketing() {

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
	// §5.24 — Sequences (Pro) extras — 8
	// SequencePolicy follows CampaignPolicy shape: fcrm_read_emails/fcrm_manage_emails.
	// =========================================================================

	$reg->read( 'fluent-crm/list-sequences-for-subscriber', array(
		'label'         => 'List CRM Sequences For Subscriber (Pro)',
		'description'   => 'All sequences a subscriber is enrolled in. Source: SequenceController::subscriberSequences (GET /sequences/subscriber/{subscriber_id}/sequences). Pro.',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'required' => array( 'subscriber_id' ), 'properties' => array( 'subscriber_id' => array( 'type' => 'integer' ) ) ),
		'output_schema' => fluent_abilities_schema_collection_output( 'sequences', $obj ),
		'callback'      => function ( $input ) use ( $proxy ) {
			$sid = (int) ( $input['subscriber_id'] ?? 0 );
			return fluent_abilities_unwrap_paginator( $proxy( 'GET', '/fluent-crm/v2/sequences/subscriber/' . $sid . '/sequences' ), 'sequences' );
		},
	) );

	$reg->write( 'fluent-crm/duplicate-sequence', array(
		'label'         => 'Duplicate CRM Sequence (Pro)',
		'description'   => 'Clone a sequence as draft. Source: SequenceController::duplicateSequence (POST /sequences/{id}/duplicate).',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'required' => array( 'id' ), 'properties' => array( 'id' => array( 'type' => 'integer' ) ) ),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return fluent_abilities_project_response( $proxy( 'POST', '/fluent-crm/v2/sequences/' . (int) ( $input['id'] ?? 0 ) . '/duplicate' ) );
		},
	) );

	$reg->write( 'fluent-crm/sequence-email-update-create', array(
		'label'         => 'Upsert CRM Sequence Email Atomically (Pro)',
		'description'   => 'Atomic upsert of a sequence email (replaces add-sequence-email + update-sequence-email in one call). Source: SequenceController::sequenceEmailUpdateCreate (POST /sequences/sequence-email-update-create).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'sequence_id', 'email' ),
			'properties' => array(
				'sequence_id' => array( 'type' => 'integer' ),
				'email'       => $obj,
			),
		),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/sequences/sequence-email-update-create', $input );
		},
	) );

	$reg->write( 'fluent-crm/duplicate-sequence-email', array(
		'label'         => 'Duplicate CRM Sequence Email (Pro)',
		'description'   => 'Duplicate a single sequence email. Source: SequenceController::duplicateEmail (POST /sequences/{id}/email/duplicate).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id', 'email_id' ),
			'properties' => array(
				'id'       => array( 'type' => 'integer' ),
				'email_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id  = (int) ( $input['id'] ?? 0 );
			$eid = (int) ( $input['email_id'] ?? 0 );
			return $proxy( 'POST', '/fluent-crm/v2/sequences/' . $id . '/email/duplicate', array( 'email_id' => $eid ) );
		},
	) );

	$reg->write( 'fluent-crm/update-sequence-email-delay', array(
		'label'         => 'Update CRM Sequence Email Delay (Pro)',
		'description'   => 'Update delay for a single sequence email. Source: SequenceController::updateEmailDelay (PATCH /sequences/{id}/email/{email_id}/delay).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id', 'email_id' ),
			'properties' => array(
				'id'         => array( 'type' => 'integer' ),
				'email_id'   => array( 'type' => 'integer' ),
				'delay_days' => array( 'type' => 'integer' ),
				'delay_unit' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id  = (int) ( $input['id'] ?? 0 );
			$eid = (int) ( $input['email_id'] ?? 0 );
			$q   = $input;
			unset( $q['id'], $q['email_id'] );
			return $proxy( 'PATCH', '/fluent-crm/v2/sequences/' . $id . '/email/' . $eid . '/delay', $q );
		},
	) );

	$reg->write( 'fluent-crm/manage-sequence-subscribers', array(
		'label'         => 'Manage CRM Sequence Subscribers (Pro)',
		'description'   => 'Multi-verb manage endpoint. _method=list (GET), enroll (POST), unenroll (DELETE). Source: SequenceController::subscribers (GET|POST|DELETE /sequences/{id}/subscribers).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id', '_method' ),
			'properties' => array(
				'id'             => array( 'type' => 'integer' ),
				'_method'        => array( 'type' => 'string', 'description' => 'list, enroll, unenroll' ),
				'subscriber_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$map = array( 'list' => 'GET', 'enroll' => 'POST', 'unenroll' => 'DELETE' );
			$op  = strtolower( (string) ( $input['_method'] ?? 'list' ) );
			$mtd = $map[ $op ] ?? 'GET';
			$id  = (int) ( $input['id'] ?? 0 );
			unset( $input['id'], $input['_method'] );
			return $proxy( $mtd, '/fluent-crm/v2/sequences/' . $id . '/subscribers', $input );
		},
	) );

	$reg->write( 'fluent-crm/reapply-sequence', array(
		'label'         => 'Reapply CRM Sequence (Pro)',
		'description'   => 'Re-enroll all eligible subscribers. Recomputes eligibility. Source: SequenceController::reapplyEmail (POST /sequences/{id}/reapply).',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'required' => array( 'id' ), 'properties' => array( 'id' => array( 'type' => 'integer' ) ) ),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/sequences/' . (int) ( $input['id'] ?? 0 ) . '/reapply' );
		},
	) );

	$reg->write( 'fluent-crm/do-bulk-action-sequences', array(
		'label'         => 'Bulk Action On CRM Sequences (Pro)',
		'description'   => 'Bulk operation across sequences. Source: SequenceController::bulkAction (POST /sequences/do-bulk-action).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'sequence_ids', 'action_name' ),
			'properties' => array(
				'sequence_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'action_name'  => array( 'type' => 'string' ),
				'action_data'  => $obj,
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/sequences/do-bulk-action', $input );
		},
	) );

	// =========================================================================
	// §5.25 — Recurring Campaigns (Pro) — 13
	// Capability: fcrm_manage_emails (writes), fcrm_read_emails (reads).
	// =========================================================================

	$reg->read( 'fluent-crm/list-recurring-campaigns', array(
		'label'         => 'List CRM Recurring Campaigns (Pro)',
		'description'   => 'Paginated recurring campaigns. Source: RecurringCampaignController::getCampaigns (GET /recurring-campaigns). Response shape: vendor may return campaigns as a sequential array OR a campaign_id-keyed object depending on FCRM internal storage at query time.',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array_merge( array( 'status' => array( 'type' => 'string' ) ), fluent_abilities_pagination_schema() ) ),
		'output_schema' => array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'campaigns' => array( 'type' => array( 'array', 'object' ) ),
				'total'     => array( 'type' => array( 'integer', 'string' ) ),
			),
		),
		'callback'      => function ( $input ) use ( $proxy ) {
			return fluent_abilities_unwrap_paginator( $proxy( 'GET', '/fluent-crm/v2/recurring-campaigns', $input ), 'campaigns' );
		},
	) );

	$reg->write( 'fluent-crm/create-recurring-campaign', array(
		'label'         => 'Create CRM Recurring Campaign (Pro)',
		'description'   => 'Create a recurring campaign as draft (state machine starts at draft; explicit change-recurring-campaign-status call required to activate). Source: RecurringCampaignController::createCampaign (POST /recurring-campaigns). Pattern-B: vendor reads `Helper::parseArrayOrJson($request->get(\'campaign\'))` per source app/Http/Controllers/RecurringCampaignController.php:134; nests under `campaign` key + requires `settings.scheduling_settings.time` + `settings.scheduling_settings.type`.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'title', 'settings' ),
			'properties' => array(
				'title'    => array( 'type' => 'string' ),
				'settings' => array(
					'type'                 => 'object',
					'additionalProperties' => true,
					'properties'           => array(
						'scheduling_settings'  => array(
							'type'                 => 'object',
							'additionalProperties' => true,
							'required'             => array( 'time', 'type' ),
							'properties'           => array(
								'time' => array( 'type' => 'string', 'description' => 'Time-of-day, e.g. 09:00.' ),
								'type' => array( 'type' => 'string', 'description' => 'daily, weekly, monthly, etc.' ),
							),
						),
						'sending_conditions'   => $obj,
						'subscribers_settings' => $obj,
					),
				),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/recurring-campaigns', array( 'campaign' => $input ) );
		},
	) );

	$reg->read( 'fluent-crm/get-recurring-campaign', array(
		'label'         => 'Get CRM Recurring Campaign (Pro)',
		'description'   => 'Get a single recurring campaign. Source: RecurringCampaignController::show (GET /recurring-campaigns/{campaign_id}).',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'required' => array( 'campaign_id' ), 'properties' => array( 'campaign_id' => array( 'type' => 'integer' ) ) ),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return fluent_abilities_project_response( $proxy( 'GET', '/fluent-crm/v2/recurring-campaigns/' . (int) ( $input['campaign_id'] ?? 0 ) ) );
		},
	) );

	$reg->write( 'fluent-crm/update-recurring-campaign-data', array(
		'label'         => 'Update CRM Recurring Campaign Data (Pro)',
		'description'   => 'Update recurring-campaign data (email subject/body/UTM/template). Source: RecurringCampaignController::updateCampaignData (POST /recurring-campaigns/update-campaign-data). Pattern-B: vendor reads `Helper::parseArrayOrJson($request->get(\'campaign\'))` + flat `campaign_id` per source app/Http/Controllers/RecurringCampaignController.php:177-202; requires `email_body` + `email_subject` in payload.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'campaign_id', 'email_subject', 'email_body' ),
			'properties' => array(
				'campaign_id'      => array( 'type' => 'integer' ),
				'title'            => array( 'type' => 'string' ),
				'email_subject'    => array( 'type' => 'string' ),
				'email_body'       => array( 'type' => 'string' ),
				'email_pre_header' => array( 'type' => 'string' ),
				'template_id'      => array( 'type' => array( 'integer', 'string' ) ),
				'utm_status'       => array( 'type' => array( 'string', 'integer' ) ),
				'utm_source'       => array( 'type' => 'string' ),
				'utm_medium'       => array( 'type' => 'string' ),
				'utm_campaign'     => array( 'type' => 'string' ),
				'utm_term'         => array( 'type' => 'string' ),
				'utm_content'      => array( 'type' => 'string' ),
				'design_template'  => array( 'type' => 'string' ),
				'settings'         => $obj,
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$campaign_id = (int) ( $input['campaign_id'] ?? 0 );
			$campaign    = $input;
			unset( $campaign['campaign_id'] );
			return fluent_abilities_project_response( $proxy( 'POST', '/fluent-crm/v2/recurring-campaigns/update-campaign-data', array(
				'campaign_id' => $campaign_id,
				'campaign'    => $campaign,
			) ) );
		},
	) );

	$reg->write( 'fluent-crm/change-recurring-campaign-status', array(
		'label'         => 'Change CRM Recurring Campaign Status (Pro)',
		'description'   => 'State-machine transition: active/paused/archived. Source: RecurringCampaignController::changeStatus (POST /recurring-campaigns/{campaign_id}/change-status).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'campaign_id', 'status' ),
			'properties' => array(
				'campaign_id' => array( 'type' => 'integer' ),
				'status'      => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$cid = (int) ( $input['campaign_id'] ?? 0 );
			return fluent_abilities_project_response( $proxy( 'POST', '/fluent-crm/v2/recurring-campaigns/' . $cid . '/change-status', array( 'status' => $input['status'] ?? '' ) ) );
		},
	) );

	$reg->write( 'fluent-crm/update-recurring-campaign-settings', array(
		'label'         => 'Update CRM Recurring Campaign Settings (Pro)',
		'description'   => 'Update recurrence/settings object. Source: RecurringCampaignController::updateSettings (POST /recurring-campaigns/{campaign_id}/update-settings).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'campaign_id', 'settings' ),
			'properties' => array(
				'campaign_id' => array( 'type' => 'integer' ),
				'settings'    => $obj,
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$cid = (int) ( $input['campaign_id'] ?? 0 );
			return $proxy( 'POST', '/fluent-crm/v2/recurring-campaigns/' . $cid . '/update-settings', array( 'settings' => $input['settings'] ?? array() ) );
		},
	) );

	$reg->write( 'fluent-crm/duplicate-recurring-campaign', array(
		'label'         => 'Duplicate CRM Recurring Campaign (Pro)',
		'description'   => 'Clone a recurring campaign. Source: RecurringCampaignController::duplicate (POST /recurring-campaigns/{campaign_id}/duplicate).',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'required' => array( 'campaign_id' ), 'properties' => array( 'campaign_id' => array( 'type' => 'integer' ) ) ),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return fluent_abilities_project_response( $proxy( 'POST', '/fluent-crm/v2/recurring-campaigns/' . (int) ( $input['campaign_id'] ?? 0 ) . '/duplicate' ) );
		},
	) );

	$reg->read( 'fluent-crm/list-recurring-campaign-emails', array(
		'label'         => 'List CRM Recurring Campaign Emails (Pro)',
		'description'   => 'List per-email payloads for a recurring campaign. Source: RecurringCampaignController::emails (GET /recurring-campaigns/{campaign_id}/emails).',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'required' => array( 'campaign_id' ), 'properties' => array( 'campaign_id' => array( 'type' => 'integer' ) ) ),
		'output_schema' => fluent_abilities_schema_collection_output( 'emails', $obj ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/recurring-campaigns/' . (int) ( $input['campaign_id'] ?? 0 ) . '/emails' );
		},
	) );

	$reg->read( 'fluent-crm/get-recurring-campaign-email', array(
		'label'         => 'Get CRM Recurring Campaign Email (Pro)',
		'description'   => 'Get a single recurring-campaign email. Source: RecurringCampaignController::getEmail (GET /recurring-campaigns/{campaign_id}/emails/{email_id}).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'campaign_id', 'email_id' ),
			'properties' => array(
				'campaign_id' => array( 'type' => 'integer' ),
				'email_id'    => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$cid = (int) ( $input['campaign_id'] ?? 0 );
			$eid = (int) ( $input['email_id'] ?? 0 );
			return $proxy( 'GET', '/fluent-crm/v2/recurring-campaigns/' . $cid . '/emails/' . $eid );
		},
	) );

	$reg->write( 'fluent-crm/update-recurring-campaign-email', array(
		'label'         => 'Update CRM Recurring Campaign Email (Pro)',
		'description'   => 'Update one recurring-campaign email. Source: RecurringCampaignController::updateEmail (PUT /recurring-campaigns/{campaign_id}/emails/{email_id}).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'campaign_id', 'email_id' ),
			'properties' => array(
				'campaign_id' => array( 'type' => 'integer' ),
				'email_id'    => array( 'type' => 'integer' ),
				'email'       => $obj,
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$cid = (int) ( $input['campaign_id'] ?? 0 );
			$eid = (int) ( $input['email_id'] ?? 0 );
			$q   = $input;
			unset( $q['campaign_id'], $q['email_id'] );
			return $proxy( 'PUT', '/fluent-crm/v2/recurring-campaigns/' . $cid . '/emails/' . $eid, $q );
		},
	) );

	$reg->delete( 'fluent-crm/bulk-delete-recurring-campaigns', array(
		'label'         => 'Bulk Delete CRM Recurring Campaigns (Pro)',
		'description'   => 'Bulk-delete recurring campaigns. Source: RecurringCampaignController::bulkDelete (POST /recurring-campaigns/delete-bulk).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'campaign_ids' ),
			'properties' => array(
				'campaign_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'deleted_count' => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/recurring-campaigns/delete-bulk', $input );
		},
	) );

	$reg->write( 'fluent-crm/do-bulk-action-recurring-campaigns', array(
		'label'         => 'Bulk Action On CRM Recurring Campaigns (Pro)',
		'description'   => 'Bulk operation across recurring campaigns. Source: RecurringCampaignController::bulkAction (POST /recurring-campaigns/do-bulk-action).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'campaign_ids', 'action_name' ),
			'properties' => array(
				'campaign_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'action_name'  => array( 'type' => 'string' ),
				'action_data'  => $obj,
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/recurring-campaigns/do-bulk-action', $input );
		},
	) );

	$reg->write( 'fluent-crm/update-recurring-campaign-labels', array(
		'label'         => 'Update CRM Recurring Campaign Labels (Pro)',
		'description'   => 'Update label IDs on a recurring campaign. Source: RecurringCampaignController::updateLabels (PUT /recurring-campaigns/{campaign_id}/update-labels).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'campaign_id', 'labels' ),
			'properties' => array(
				'campaign_id' => array( 'type' => 'integer' ),
				'labels'      => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$cid = (int) ( $input['campaign_id'] ?? 0 );
			return $proxy( 'PUT', '/fluent-crm/v2/recurring-campaigns/' . $cid . '/update-labels', array( 'labels' => $input['labels'] ?? array() ) );
		},
	) );

	// =========================================================================
	// §5.26 — Dynamic Segments (Pro) — 9
	// Capability: fcrm_manage_contact_cats (writes), fcrm_read_contacts (reads).
	// =========================================================================

	$reg->read( 'fluent-crm/list-dynamic-segments', array(
		'label'         => 'List CRM Dynamic Segments (Pro)',
		'description'   => 'Paginated dynamic segments. Source: DynamicSegmentController::index (GET /dynamic-segments).',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => fluent_abilities_pagination_schema() ),
		'output_schema' => fluent_abilities_schema_list_output( 'segments', $obj ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/dynamic-segments', $input );
		},
	) );

	$reg->write( 'fluent-crm/create-dynamic-segment', array(
		'label'         => 'Create CRM Dynamic Segment (Pro)',
		'description'   => 'Create a dynamic segment with conditions (query builder). Source: DynamicSegmentController::store (POST /dynamic-segments).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'title', 'conditions' ),
			'properties' => array(
				'title'       => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
				'conditions'  => $obj,
			),
		),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/dynamic-segments', $input );
		},
	) );

	$reg->read( 'fluent-crm/get-dynamic-segment-stats', array(
		'label'         => 'Get CRM Dynamic Segment Stats (Pro)',
		'description'   => 'Aggregate stats across dynamic segments. Source: DynamicSegmentController::stats (GET /dynamic-segments/stats).',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/dynamic-segments/stats' );
		},
	) );

	$reg->read( 'fluent-crm/estimate-dynamic-segment-contacts', array(
		'label'         => 'Estimate CRM Dynamic Segment Contacts (Pro)',
		'description'   => 'Compute estimated contact-count for a candidate condition set. Source: DynamicSegmentController::estimatedContacts (POST /dynamic-segments/estimated-contacts).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'conditions' ),
			'properties' => array(
				'conditions' => $obj,
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'estimated_count' => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/dynamic-segments/estimated-contacts', $input );
		},
	) );

	$reg->write( 'fluent-crm/update-dynamic-segment', array(
		'label'         => 'Update CRM Dynamic Segment (Pro)',
		'description'   => 'Update a dynamic segment. Source: DynamicSegmentController::update (PUT /dynamic-segments/{id}).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'          => array( 'type' => 'integer' ),
				'title'       => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
				'conditions'  => $obj,
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			unset( $input['id'] );
			return $proxy( 'PUT', '/fluent-crm/v2/dynamic-segments/' . $id, $input );
		},
	) );

	$reg->delete( 'fluent-crm/delete-dynamic-segment', array(
		'label'         => 'Delete CRM Dynamic Segment (Pro)',
		'description'   => 'Delete a dynamic segment. Source: DynamicSegmentController::destroy (DELETE /dynamic-segments/{id}).',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'required' => array( 'id' ), 'properties' => array( 'id' => array( 'type' => 'integer' ) ) ),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'DELETE', '/fluent-crm/v2/dynamic-segments/' . (int) ( $input['id'] ?? 0 ) );
		},
	) );

	$reg->write( 'fluent-crm/duplicate-dynamic-segment', array(
		'label'         => 'Duplicate CRM Dynamic Segment (Pro)',
		'description'   => 'Clone a dynamic segment. Source: DynamicSegmentController::duplicate (POST /dynamic-segments/duplicate/{id}).',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'required' => array( 'id' ), 'properties' => array( 'id' => array( 'type' => 'integer' ) ) ),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/dynamic-segments/duplicate/' . (int) ( $input['id'] ?? 0 ) );
		},
	) );

	$reg->read( 'fluent-crm/get-dynamic-segment-subscriber', array(
		'label'         => 'Get CRM Dynamic Segment Subscriber Match (Pro)',
		'description'   => 'Test whether a subscriber matches a segment slug. Source: DynamicSegmentController::subscriber (GET /dynamic-segments/{slug}/subscribers/{id}).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'slug', 'id' ),
			'properties' => array(
				'slug' => array( 'type' => 'string' ),
				'id'   => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'matches' => array( 'type' => 'boolean' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			$slug = (string) ( $input['slug'] ?? '' );
			$id   = (int) ( $input['id'] ?? 0 );
			return $proxy( 'GET', '/fluent-crm/v2/dynamic-segments/' . rawurlencode( $slug ) . '/subscribers/' . $id );
		},
	) );

	$reg->read( 'fluent-crm/list-dynamic-segment-custom-fields', array(
		'label'         => 'List CRM Dynamic Segment Buildable Custom Fields (Pro)',
		'description'   => 'Custom fields usable in segment conditions. Source: DynamicSegmentController::customFields (GET /dynamic-segments/custom-fields). V10: vendor controller may TypeError when FluentCampaign Pro is inactive or segment registry empty; registrar returns WP_Error instead.',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => fluent_abilities_schema_collection_output( 'fields', $obj ),
		'callback'      => function ( $input ) use ( $proxy ) {
			// V10: convert vendor-side TypeError into a typed WP_Error (P-K pattern).
			try {
				return $proxy( 'GET', '/fluent-crm/v2/dynamic-segments/custom-fields' );
			} catch ( \Throwable $e ) {
				return new WP_Error( 'vendor_precondition_failed', 'FluentCRM dynamic-segment custom-fields lookup failed: ' . $e->getMessage() );
			}
		},
	) );

	// =========================================================================
	// §5.27 — Campaigns-Pro (Pro) — 7
	// Capability: fcrm_manage_emails.
	// =========================================================================

	$reg->write( 'fluent-crm/resend-failed-campaign-emails', array(
		'label'         => 'Resend Failed CRM Campaign Emails (Pro)',
		'description'   => 'Resend campaign emails that failed. Source: CampaignsProController::resendFailed (POST /campaigns-pro/{id}/resend-failed-emails).',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'required' => array( 'id' ), 'properties' => array( 'id' => array( 'type' => 'integer' ) ) ),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'resent_count' => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/campaigns-pro/' . (int) ( $input['id'] ?? 0 ) . '/resend-failed-emails' );
		},
	) );

	$reg->write( 'fluent-crm/resend-unopened-campaign-emails', array(
		'label'         => 'Resend Unopened CRM Campaign Emails (Pro)',
		'description'   => 'Resend campaign emails to recipients who did not open. Source: CampaignsProController::resendUnopened (POST /campaigns-pro/{id}/resend-unopened-emails).',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'required' => array( 'id' ), 'properties' => array( 'id' => array( 'type' => 'integer' ) ) ),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/campaigns-pro/' . (int) ( $input['id'] ?? 0 ) . '/resend-unopened-emails' );
		},
	) );

	$reg->write( 'fluent-crm/resend-campaign-emails', array(
		'label'         => 'Resend CRM Campaign Emails (Pro)',
		'description'   => 'Resend selected per-recipient emails. Source: CampaignsProController::resendEmails (POST /campaigns-pro/{id}/resend-emails).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id', 'recipient_ids' ),
			'properties' => array(
				'id'            => array( 'type' => 'integer' ),
				'recipient_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			return $proxy( 'POST', '/fluent-crm/v2/campaigns-pro/' . $id . '/resend-emails', array( 'recipient_ids' => $input['recipient_ids'] ?? array() ) );
		},
	) );

	$reg->write( 'fluent-crm/tag-actions-on-campaign', array(
		'label'         => 'Configure CRM Campaign Tag Actions (Pro)',
		'description'   => 'Post-send automation rules: add/remove tags on open/click. Source: CampaignsProController::tagActions (POST /campaigns-pro/{id}/tag-actions).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id', 'actions' ),
			'properties' => array(
				'id'      => array( 'type' => 'integer' ),
				'actions' => array( 'type' => 'array', 'items' => $obj ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			return $proxy( 'POST', '/fluent-crm/v2/campaigns-pro/' . $id . '/tag-actions', array( 'actions' => $input['actions'] ?? array() ) );
		},
	) );

	$reg->read( 'fluent-crm/list-campaigns-pro-posts', array(
		'label'         => 'List CRM Campaign-Pro WP Posts (Pro)',
		'description'   => 'WP posts for campaign-pro picker. Source: CampaignsProController::posts (GET /campaigns-pro/posts).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => array(
				'post_type' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'posts', $obj ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/campaigns-pro/posts', $input );
		},
	) );

	$reg->read( 'fluent-crm/list-campaigns-pro-post-taxonomies', array(
		'label'         => 'List CRM Campaign-Pro Post Taxonomies (Pro)',
		'description'   => 'Taxonomies available for campaign-pro post selection. Source: CampaignsProController::taxonomies (GET /campaigns-pro/posts/taxonomies).',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => fluent_abilities_schema_collection_output( 'taxonomies', $obj ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/campaigns-pro/posts/taxonomies' );
		},
	) );

	$reg->read( 'fluent-crm/list-campaigns-pro-products', array(
		'label'         => 'List CRM Campaign-Pro Commerce Products (Pro)',
		'description'   => 'Commerce products for campaign-pro picker. Source: CampaignsProController::products (GET /campaigns-pro/products). V10: vendor controller may TypeError when FluentCampaign Pro is inactive or no commerce provider is wired; registrar returns WP_Error instead.',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => fluent_abilities_schema_collection_output( 'products', $obj ),
		'callback'      => function ( $input ) use ( $proxy ) {
			// V10: convert vendor-side TypeError into a typed WP_Error (P-K pattern).
			try {
				return $proxy( 'GET', '/fluent-crm/v2/campaigns-pro/products' );
			} catch ( \Throwable $e ) {
				return new WP_Error( 'vendor_precondition_failed', 'FluentCRM campaigns-pro products lookup failed: ' . $e->getMessage() );
			}
		},
	) );

	// =========================================================================
	// §5.28 — Smart Links extras (Pro) — 1
	// =========================================================================

	$reg->write( 'fluent-crm/activate-smart-link', array(
		'label'         => 'Activate CRM Smart Link (Pro)',
		'description'   => 'Bulk-activate or per-link activate a smart-link. Source: SmartLinkController::activate (POST /smart-links/activate).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'smart_link_id' ),
			'properties' => array(
				'smart_link_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/smart-links/activate', $input );
		},
	) );

}
