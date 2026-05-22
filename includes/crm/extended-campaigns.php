<?php
/**
 * FluentCRM Campaign extension ability surface — §5.3 (10) + §5.4 (10) + §5.5 (8).
 *
 * Source-side CampaignPolicy:
 *   GET → fcrm_read_emails
 *   non-GET → fcrm_manage_emails
 *   delete-tier (delete/deleteCampaignEmails/handleBulkAction) → fcrm_manage_email_delete
 *
 * Campaign state machine: draft → scheduled → working → paused → archived.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', 'fluent_abilities_crm_register_extended_campaigns', 11 );

function fluent_abilities_crm_register_extended_campaigns() {

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
				array( 'id' => array( 'type' => 'integer', 'description' => 'Campaign ID.' ) ),
				$extra
			),
		);
	};

	// =========================================================================
	// §5.3 — Campaign lifecycle (10)
	// =========================================================================

	$reg->write( 'fluent-crm/send-test-email-campaign', array(
		'label'         => 'Send Test Email For CRM Campaign',
		'description'   => 'Send a test send of a campaign email to a specified address. Source: CampaignController::sendTestEmail (POST /campaigns/send-test-email). Capability: fcrm_manage_emails.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'campaign_id', 'to_email' ),
			'properties' => array(
				'campaign_id'      => array( 'type' => 'integer' ),
				'to_email'         => array( 'type' => 'string' ),
				'subject_override' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/campaigns/send-test-email', $input );
		},
	) );

	$reg->write( 'fluent-crm/preview-campaign-email-html', array(
		'label'         => 'Preview CRM Campaign Email HTML',
		'description'   => 'Render preview HTML for a campaign body merged with subscriber smart-codes. Source: CampaignController::previewEmailHtml (POST /campaigns/email-preview-html). Capability: fcrm_manage_emails.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'email_body' ),
			'properties' => array(
				'campaign_id'   => array( 'type' => 'integer' ),
				'subscriber_id' => array( 'type' => 'integer' ),
				'email_body'    => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'html' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/campaigns/email-preview-html', $input );
		},
	) );

	$reg->read( 'fluent-crm/preview-campaign-recipient-email', array(
		'label'         => 'Preview CRM Campaign Recipient Email',
		'description'   => 'Rendered HTML for one per-recipient campaign email row. Source: CampaignController::previewEmailForRecipient (GET /campaigns/emails/{email_id}/preview). Capability: fcrm_read_emails.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'email_id' ),
			'properties' => array(
				'email_id' => array( 'type' => 'integer', 'description' => 'fc_campaign_emails.id row.' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'html' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['email_id'] ?? 0 );
			return $proxy( 'GET', '/fluent-crm/v2/campaigns/emails/' . $id . '/preview' );
		},
	) );

	$reg->read( 'fluent-crm/estimate-campaign-contacts', array(
		'label'         => 'Estimate CRM Campaign Recipient Set',
		'description'   => 'Compute estimated recipient set size from a list/tag selector. Source: CampaignController::getEstimatedContacts (POST /campaigns/estimated-contacts). Capability: fcrm_read_emails.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => array(
				'lists'          => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'tags'           => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'excluded_lists' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'excluded_tags'  => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'filter_type'    => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'estimated_count' => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/campaigns/estimated-contacts', $input );
		},
	) );

	$reg->write( 'fluent-crm/update-single-campaign-property', array(
		'label'         => 'Update Single CRM Campaign Property',
		'description'   => 'Mutate one campaign field (subject, status, sender, etc.). Source: CampaignController::updateSingleCampaign (POST /campaigns/update-single-campaign). Capability: fcrm_manage_emails.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'campaign_id', 'property', 'value' ),
			'properties' => array(
				'campaign_id' => array( 'type' => 'integer' ),
				'property'    => array( 'type' => 'string' ),
				'value'       => array( 'type' => array( 'string', 'integer', 'boolean', 'null' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/campaigns/update-single-campaign', $input );
		},
	) );

	$reg->write( 'fluent-crm/advance-campaign-step', array(
		'label'         => 'Advance CRM Campaign Wizard Step',
		'description'   => 'Wizard step advance for campaign edit flow. Source: CampaignController::nextStep (POST /campaigns/{id}/step). Capability: fcrm_manage_emails.',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required( array(
			'step' => array( 'type' => 'integer', 'description' => 'Step number (1..N).' ),
		) ),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			$q  = $input;
			unset( $q['id'] );
			return $proxy( 'POST', '/fluent-crm/v2/campaigns/' . $id . '/step', $q );
		},
	) );

	$reg->write( 'fluent-crm/pause-campaign', array(
		'label'         => 'Pause CRM Campaign',
		'description'   => 'State transition: working → paused. Source: CampaignController::pauseCampaign (POST /campaigns/{id}/pause).',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required(),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/campaigns/' . (int) ( $input['id'] ?? 0 ) . '/pause' );
		},
	) );

	$reg->write( 'fluent-crm/resume-campaign', array(
		'label'         => 'Resume CRM Campaign',
		'description'   => 'State transition: paused → working. Source: CampaignController::resumeCampaign (POST /campaigns/{id}/resume).',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required(),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/campaigns/' . (int) ( $input['id'] ?? 0 ) . '/resume' );
		},
	) );

	$reg->write( 'fluent-crm/duplicate-campaign', array(
		'label'         => 'Duplicate CRM Campaign',
		'description'   => 'Clone a campaign as draft. Source: CampaignController::duplicateCampaign (POST /campaigns/{id}/duplicate).',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required(),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return fluent_abilities_project_response( $proxy( 'POST', '/fluent-crm/v2/campaigns/' . (int) ( $input['id'] ?? 0 ) . '/duplicate' ) );
		},
	) );

	$reg->write( 'fluent-crm/update-campaign-title', array(
		'label'         => 'Update CRM Campaign Title',
		'description'   => 'Update campaign title only. Source: CampaignController::updateCampaignTitle (PUT /campaigns/{id}/title).',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required( array(
			'title' => array( 'type' => 'string' ),
		) ),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			return fluent_abilities_project_response( $proxy( 'PUT', '/fluent-crm/v2/campaigns/' . $id . '/title', array( 'title' => $input['title'] ?? '' ) ) );
		},
	) );

	// =========================================================================
	// §5.4 — Campaign metrics, recipients, schedule, labels (10)
	// =========================================================================

	$reg->write( 'fluent-crm/draft-campaign-recipients', array(
		'label'         => 'Draft CRM Campaign Recipients',
		'description'   => 'Compute and write the initial recipient set into fc_campaign_emails. Atomic. Source: CampaignController::draftRecipients (POST /campaigns/{id}/draft-recipients).',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required(),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'drafted_count' => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/campaigns/' . (int) ( $input['id'] ?? 0 ) . '/draft-recipients' );
		},
	) );

	$reg->read( 'fluent-crm/get-campaign-estimated-recipient-count', array(
		'label'         => 'Get CRM Campaign Estimated Recipient Count',
		'description'   => 'Estimated recipient count for a configured campaign. Source: CampaignController::getEstimatedRecipientsCount (GET /campaigns/{id}/estimated-recipients-count).',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required(),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'estimated_count' => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/campaigns/' . (int) ( $input['id'] ?? 0 ) . '/estimated-recipients-count' );
		},
	) );

	$reg->read( 'fluent-crm/list-campaign-emails', array(
		'label'         => 'List CRM Campaign Per-Recipient Emails',
		'description'   => 'Per-recipient fc_campaign_emails rows for one campaign. Source: CampaignController::getEmails (GET /campaigns/{id}/emails). Capability: fcrm_read_emails.',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required( array_merge(
			array(
				'status' => array( 'type' => 'string' ),
			),
			fluent_abilities_pagination_schema()
		) ),
		'output_schema' => fluent_abilities_schema_list_output( 'emails', $obj ),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			$q  = $input;
			unset( $q['id'] );
			return fluent_abilities_normalize_collection( $proxy( 'GET', '/fluent-crm/v2/campaigns/' . $id . '/emails', $q ), 'emails' );
		},
	) );

	$reg->delete( 'fluent-crm/delete-campaign-emails', array(
		'label'         => 'Delete CRM Campaign Per-Recipient Emails',
		'description'   => 'Delete sent/failed per-recipient rows. Source: CampaignController::deleteEmails (DELETE /campaigns/{id}/emails). Capability: fcrm_manage_email_delete.',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required( array(
			'email_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
		) ),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'deleted_count' => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			$q  = $input;
			unset( $q['id'] );
			return $proxy( 'DELETE', '/fluent-crm/v2/campaigns/' . $id . '/emails', $q );
		},
	) );

	$reg->write( 'fluent-crm/schedule-campaign', array(
		'label'         => 'Schedule CRM Campaign',
		'description'   => 'Schedule a campaign for future send. State: draft → scheduled. Source: CampaignController::scheduleCampaign (POST /campaigns/{id}/schedule). Capability: fcrm_manage_emails.',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required( array(
			'scheduled_at'       => array( 'type' => 'string', 'description' => 'YYYY-MM-DD HH:MM:SS' ),
			'scheduled_timezone' => array( 'type' => 'string' ),
		) ),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			$q  = $input;
			unset( $q['id'] );
			return fluent_abilities_project_response( $proxy( 'POST', '/fluent-crm/v2/campaigns/' . $id . '/schedule', $q ) );
		},
	) );

	$reg->write( 'fluent-crm/unschedule-campaign', array(
		'label'         => 'Unschedule CRM Campaign',
		'description'   => 'Move scheduled campaign back to draft. Source: CampaignController::unScheduleCampaign (POST /campaigns/{id}/un-schedule).',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required(),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/campaigns/' . (int) ( $input['id'] ?? 0 ) . '/un-schedule' );
		},
	) );

	$reg->read( 'fluent-crm/get-campaign-processing-stat', array(
		'label'         => 'Get CRM Campaign Real-Time Processing Stat',
		'description'   => 'Real-time send progress for a campaign. Source: CampaignController::getProcessingStat (GET /campaigns/{id}/processing-stat).',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required(),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'sent_count'    => array( 'type' => 'integer' ),
			'pending_count' => array( 'type' => 'integer' ),
			'failed_count'  => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return fluent_abilities_project_response( $proxy( 'GET', '/fluent-crm/v2/campaigns/' . (int) ( $input['id'] ?? 0 ) . '/processing-stat' ) );
		},
	) );

	$reg->read( 'fluent-crm/get-campaign-share-url', array(
		'label'         => 'Get CRM Campaign Public Share URL',
		'description'   => 'Public-facing share URL for campaign preview. Source: CampaignController::getShareUrl (GET /campaigns/{id}/share-url).',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required(),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'share_url' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/campaigns/' . (int) ( $input['id'] ?? 0 ) . '/share-url' );
		},
	) );

	$reg->read( 'fluent-crm/get-campaign-status', array(
		'label'         => 'Get CRM Campaign Status (Lightweight)',
		'description'   => 'Lightweight status poll. Source: CampaignController::getStatus (GET /campaigns/{id}/status).',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required(),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'status' => array( 'type' => 'string' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return fluent_abilities_project_response( $proxy( 'GET', '/fluent-crm/v2/campaigns/' . (int) ( $input['id'] ?? 0 ) . '/status' ) );
		},
	) );

	$reg->read( 'fluent-crm/get-campaign-overview-stats', array(
		'label'         => 'Get CRM Campaign Overview Stats',
		'description'   => 'Sends/opens/clicks/CTR/CTOR/unsubs aggregate. Source: CampaignController::getOverviewStats (GET /campaigns/{id}/overview_stats).',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required(),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/campaigns/' . (int) ( $input['id'] ?? 0 ) . '/overview_stats' );
		},
	) );

	// =========================================================================
	// §5.5 — Campaign reports + revenue + bulk (8)
	// =========================================================================

	$reg->read( 'fluent-crm/get-campaign-link-report', array(
		'label'         => 'Get CRM Campaign Per-URL Click Report',
		'description'   => 'Per-URL click breakdown for a campaign. Source: CampaignController::getLinkReport (GET /campaigns/{id}/link-report).',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required(),
		'output_schema' => fluent_abilities_schema_collection_output( 'links', array(
			'url'    => array( 'type' => 'string' ),
			'clicks' => array( 'type' => 'integer' ),
			'unique' => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/campaigns/' . (int) ( $input['id'] ?? 0 ) . '/link-report' );
		},
	) );

	$reg->read( 'fluent-crm/get-campaign-revenues', array(
		'label'         => 'Get CRM Campaign Revenue Attribution',
		'description'   => 'Cross-provider commerce attribution per campaign. Source: CampaignController::getRevenues (GET /campaigns/{id}/revenues).',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required(),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'revenues' => array( 'type' => 'array', 'items' => $obj ),
			'total'    => array( 'type' => array( 'number', 'string' ) ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/campaigns/' . (int) ( $input['id'] ?? 0 ) . '/revenues' );
		},
	) );

	$reg->write( 'fluent-crm/resync-campaign-revenues', array(
		'label'         => 'Resync CRM Campaign Revenue Attribution',
		'description'   => 'Re-run revenue attribution computation for a campaign. Source: CampaignController::resyncRevenues (POST /campaigns/{id}/revenues/resync). Capability: fcrm_manage_emails.',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required(),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/campaigns/' . (int) ( $input['id'] ?? 0 ) . '/revenues/resync' );
		},
	) );

	$reg->read( 'fluent-crm/list-campaign-unsubscribers', array(
		'label'         => 'List CRM Campaign Unsubscribers',
		'description'   => 'Paginated list of contacts who unsubscribed via this campaign. Source: CampaignController::getUnsubscribers (GET /campaigns/{id}/unsubscribers).',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required( fluent_abilities_pagination_schema() ),
		'output_schema' => fluent_abilities_schema_list_output( 'unsubscribers', $obj ),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			$q  = $input;
			unset( $q['id'] );
			return fluent_abilities_unwrap_paginator( $proxy( 'GET', '/fluent-crm/v2/campaigns/' . $id . '/unsubscribers', $q ), 'unsubscribers' );
		},
	) );

	$reg->read( 'fluent-crm/get-campaign-contacts-by-segment', array(
		'label'         => 'Get CRM Campaign Contacts By Engagement Segment',
		'description'   => 'Contacts grouped by send-engagement segment (sent/opened/clicked/bounced/unopened). Source: CampaignController::getContactsBySegment (GET /campaigns/{id}/contacts-by-segment).',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required( array(
			'segment' => array(
				'type'        => 'string',
				'description' => 'sent, opened, clicked, bounced, unopened',
			),
		) ),
		'output_schema' => fluent_abilities_schema_list_output( 'contacts', $obj ),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			$q  = $input;
			unset( $q['id'] );
			return fluent_abilities_unwrap_paginator( $proxy( 'GET', '/fluent-crm/v2/campaigns/' . $id . '/contacts-by-segment', $q ), 'contacts' );
		},
	) );

	$reg->write( 'fluent-crm/update-campaign-labels', array(
		'label'         => 'Update CRM Campaign Labels',
		'description'   => 'Set label IDs on a campaign. Source: CampaignController::updateLabels (PUT /campaigns/{id}/update-labels).',
		'category'      => 'fluent-crm',
		'input_schema'  => $id_required( array(
			'labels' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
		) ),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			$q  = $input;
			unset( $q['id'] );
			return $proxy( 'PUT', '/fluent-crm/v2/campaigns/' . $id . '/update-labels', $q );
		},
	) );

}
