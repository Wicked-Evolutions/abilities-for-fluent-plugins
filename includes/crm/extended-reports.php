<?php
/**
 * FluentCRM Reports — extended ability surface.
 *
 * Wraps the FluentCRM v2 ReportController endpoints (/reports/*).
 * Source-side ReportPolicy: fcrm_view_dashboard baseline; getEmails →
 * fcrm_read_emails; deleteEmails → fcrm_manage_email_delete.
 *
 * Strategy: each ability proxies the underlying REST route via internal
 * rest_do_request(). This preserves the controller's source-side logic,
 * filters, and capability checks (Layered, Not Replaced — Principle 5)
 * while giving operators a typed entry point with curated description.
 *
 * Sprint: Fluent Suite Registrar Bundle Sprint 2026-05-13 (v2.0.0).
 * Research: ABILITY REGISTRAR RESEARCH — FluentCRM 2026-05-13 §5.12.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', 'fluent_abilities_crm_register_extended_reports', 11 );

/**
 * Register the §5.12 Reports cluster (18 abilities).
 *
 * Extracted as a named function so unit tests can invoke registration
 * directly — the unit-test add_action stub does not dispatch callbacks.
 */
function fluent_abilities_crm_register_extended_reports() {

	$reg = new Fluent_Abilities_Registrar( 'crm' );

	/**
	 * Helper local to this function: proxy a GET to the FCRM REST surface and
	 * return either the controller's data array or a WP_Error on failure.
	 *
	 * Internal calls inherit the Policy capability check so the source-side
	 * fcrm_* cap requirement composes with our wrapper-level fluent_crm_read.
	 */
	$proxy_get = static function ( $route, $params = array() ) {
		$req = new WP_REST_Request( 'GET', $route );
		foreach ( (array) $params as $k => $v ) {
			if ( null !== $v && '' !== $v ) {
				$req->set_param( $k, $v );
			}
		}
		$res = rest_do_request( $req );
		if ( $res->is_error() ) {
			return $res->as_error();
		}
		return $res->get_data();
	};

	$proxy_delete = static function ( $route, $params = array() ) {
		$req = new WP_REST_Request( 'DELETE', $route );
		foreach ( (array) $params as $k => $v ) {
			if ( null !== $v && '' !== $v ) {
				$req->set_param( $k, $v );
			}
		}
		$res = rest_do_request( $req );
		if ( $res->is_error() ) {
			return $res->as_error();
		}
		return $res->get_data();
	};

	// Common date-range input fragment (used by every per-date report).
	$date_range_props = array(
		'date_from' => array(
			'type'        => 'string',
			'description' => 'Start date (YYYY-MM-DD). Defaults to 30 days ago when omitted.',
		),
		'date_to' => array(
			'type'        => 'string',
			'description' => 'End date (YYYY-MM-DD). Defaults to today when omitted.',
		),
	);

	$by_date_output = fluent_abilities_schema_item_output( array(
		'by_date' => array(
			'type'  => 'array',
			'items' => array(
				'type'       => 'object',
				'properties' => array(
					'date'  => array( 'type' => 'string' ),
					'count' => array( 'type' => 'integer' ),
				),
			),
		),
		'total' => array( 'type' => 'integer' ),
	) );

	// =========================================================================
	// 5.12.1 — get-report-subscribers
	// =========================================================================
	$reg->read( 'fluent-crm/get-report-subscribers', array(
		'label'        => 'Get CRM Subscribers Growth Report',
		'description'  => 'Per-date contact-signup counts over a date range. Source: ReportController::getSubscribers (GET /reports/subscribers). Capability: fcrm_view_dashboard.',
		'category'     => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => $date_range_props,
		),
		'output_schema' => $by_date_output,
		'callback'      => function ( $input ) use ( $proxy_get ) {
			return $proxy_get( '/fluent-crm/v2/reports/subscribers', $input );
		},
	) );

	// =========================================================================
	// 5.12.2 — get-report-email-sents
	// =========================================================================
	$reg->read( 'fluent-crm/get-report-email-sents', array(
		'label'        => 'Get CRM Email Sent Report',
		'description'  => 'Per-date sent-email counts over a date range. Source: ReportController::emailSents (GET /reports/email-sents). Capability: fcrm_view_dashboard.',
		'category'     => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => $date_range_props,
		),
		'output_schema' => $by_date_output,
		'callback'      => function ( $input ) use ( $proxy_get ) {
			return $proxy_get( '/fluent-crm/v2/reports/email-sents', $input );
		},
	) );

	// =========================================================================
	// 5.12.3 — get-report-email-opens
	// =========================================================================
	$reg->read( 'fluent-crm/get-report-email-opens', array(
		'label'        => 'Get CRM Email Open Report',
		'description'  => 'Per-date open counts. Source: ReportController::emailOpens (GET /reports/email-opens). Capability: fcrm_view_dashboard.',
		'category'     => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => $date_range_props,
		),
		'output_schema' => $by_date_output,
		'callback'      => function ( $input ) use ( $proxy_get ) {
			return $proxy_get( '/fluent-crm/v2/reports/email-opens', $input );
		},
	) );

	// =========================================================================
	// 5.12.4 — get-report-email-clicks
	// =========================================================================
	$reg->read( 'fluent-crm/get-report-email-clicks', array(
		'label'        => 'Get CRM Email Click Report',
		'description'  => 'Per-date click counts. Source: ReportController::emailClicks (GET /reports/email-clicks). Capability: fcrm_view_dashboard.',
		'category'     => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => $date_range_props,
		),
		'output_schema' => $by_date_output,
		'callback'      => function ( $input ) use ( $proxy_get ) {
			return $proxy_get( '/fluent-crm/v2/reports/email-clicks', $input );
		},
	) );

	// =========================================================================
	// 5.12.5 — get-report-email-unsubs
	// =========================================================================
	$reg->read( 'fluent-crm/get-report-email-unsubs', array(
		'label'        => 'Get CRM Email Unsubscribe Report',
		'description'  => 'Per-date unsubscribe counts. Source: ReportController::emailUnsubs (GET /reports/email-unsubs). Capability: fcrm_view_dashboard.',
		'category'     => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => $date_range_props,
		),
		'output_schema' => $by_date_output,
		'callback'      => function ( $input ) use ( $proxy_get ) {
			return $proxy_get( '/fluent-crm/v2/reports/email-unsubs', $input );
		},
	) );

	// =========================================================================
	// 5.12.6 — get-report-email-performance
	// =========================================================================
	$reg->read( 'fluent-crm/get-report-email-performance', array(
		'label'        => 'Get CRM Email Performance Rates',
		'description'  => 'Aggregate engagement rates over a date range. Source: ReportController::emailPerformance (GET /reports/email-performance). Capability: fcrm_view_dashboard. Response shape: `stats.totals` (counts: sent/delivered/opened/clicked/bounced) + `stats.percentages` (open/click/bounce as %).',
		'category'     => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => $date_range_props,
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'stats' => array(
				'type'                 => 'object',
				'additionalProperties' => true,
				'properties'           => array(
					'totals'      => array( 'type' => 'object', 'additionalProperties' => true ),
					'percentages' => array( 'type' => 'object', 'additionalProperties' => true ),
				),
			),
		) ),
		'callback'      => function ( $input ) use ( $proxy_get ) {
			return $proxy_get( '/fluent-crm/v2/reports/email-performance', $input );
		},
	) );

	// =========================================================================
	// 5.12.7 — get-report-taxonomy-terms
	// =========================================================================
	$reg->read( 'fluent-crm/get-report-taxonomy-terms', array(
		'label'        => 'Get WP Taxonomy Term Distribution',
		'description'  => 'WP taxonomy term counts (operator-side helper for filter UIs). Source: ReportController::taxonomyTerms (GET /reports/taxonomy-terms). Capability: fcrm_view_dashboard.',
		'category'     => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'taxonomy' ),
			'properties' => array(
				'taxonomy' => array(
					'type'        => 'string',
					'description' => 'WordPress taxonomy slug (category, post_tag, etc.).',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'terms', array(
			'id'    => array( 'type' => 'integer' ),
			'name'  => array( 'type' => 'string' ),
			'count' => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy_get ) {
			return $proxy_get( '/fluent-crm/v2/reports/taxonomy-terms', $input );
		},
	) );

	// =========================================================================
	// 5.12.8 — list-report-emails (perm: fcrm_read_emails — non-default tier)
	// =========================================================================
	$reg->read( 'fluent-crm/list-report-emails', array(
		'label'        => 'List CRM Per-Recipient Emails (Reports)',
		'description'  => 'Per-recipient sent-email rows from fc_campaign_emails, paginated. Source: ReportController::getEmails (GET /reports/emails). Capability: fcrm_read_emails (overrides ReportPolicy default).',
		'category'     => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge(
				array(
					'campaign_id' => array(
						'type'        => 'integer',
						'description' => 'Filter by campaign_id (optional).',
					),
					'status'      => array(
						'type'        => 'string',
						'description' => 'Filter by send status (sent, scheduled, failed, bounced).',
					),
				),
				fluent_abilities_pagination_schema()
			),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'emails', array(
			'id'            => array( 'type' => 'integer' ),
			'campaign_id'   => array( 'type' => 'integer' ),
			'subscriber_id' => array( 'type' => 'integer' ),
			'email_subject' => array( 'type' => 'string' ),
			'status'        => array( 'type' => 'string' ),
			'created_at'    => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback'      => function ( $input ) use ( $proxy_get ) {
			return fluent_abilities_normalize_collection( $proxy_get( '/fluent-crm/v2/reports/emails', $input ), 'emails' );
		},
	) );

	// =========================================================================
	// 5.12.9 — delete-report-emails (perm: fcrm_manage_email_delete)
	// =========================================================================
	$reg->delete( 'fluent-crm/delete-report-emails', array(
		'label'        => 'Delete CRM Per-Recipient Emails (Reports)',
		'description'  => 'Delete sent/failed per-recipient email rows from fc_campaign_emails. Destructive — provide explicit email_ids. Source: ReportController::deleteEmails (DELETE /reports/emails). Capability: fcrm_manage_email_delete.',
		'category'     => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'email_ids' ),
			'properties' => array(
				'email_ids' => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'integer' ),
					'description' => 'Per-recipient email row IDs to delete (fc_campaign_emails.id).',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'deleted_count' => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy_delete ) {
			return $proxy_delete( '/fluent-crm/v2/reports/emails', $input );
		},
	) );

	// =========================================================================
	// 5.12.10 — get-report-advanced-providers
	// =========================================================================
	$reg->read( 'fluent-crm/get-report-advanced-providers', array(
		'label'        => 'Get CRM Email Routing Provider Counts',
		'description'  => 'Per-provider routing counts (FluentSMTP / sending-provider distribution). Source: ReportController::advancedProviders (GET /reports/advanced-providers). Capability: fcrm_view_dashboard.',
		'category'     => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => $date_range_props,
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'providers', array(
			'provider' => array( 'type' => 'string' ),
			'count'    => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy_get ) {
			return fluent_abilities_normalize_collection( $proxy_get( '/fluent-crm/v2/reports/advanced-providers', $input ), 'providers' );
		},
	) );

	// =========================================================================
	// 5.12.11 — get-report-contacts-by-status
	// =========================================================================
	$reg->read( 'fluent-crm/get-report-contacts-by-status', array(
		'label'        => 'Get CRM Contact Distribution by Status',
		'description'  => 'Counts of subscribers grouped by lifecycle status (subscribed, pending, unsubscribed, bounced, complained). Source: ReportController::contactsByStatus (GET /reports/contacts-by-status). Capability: fcrm_view_dashboard. Response key: `stats` (array of {status, count} entries) + `total`.',
		'category'     => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'stats' => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'status' => array( 'type' => 'string' ),
						'count'  => array( 'type' => array( 'integer', 'string' ) ),
					),
				),
			),
			'total' => array( 'type' => array( 'integer', 'string' ) ),
		) ),
		'callback'      => function ( $input ) use ( $proxy_get ) {
			return $proxy_get( '/fluent-crm/v2/reports/contacts-by-status', $input );
		},
	) );

	// =========================================================================
	// 5.12.12 — get-report-contacts-by-tags
	// =========================================================================
	$reg->read( 'fluent-crm/get-report-contacts-by-tags', array(
		'label'        => 'Get CRM Contact Distribution by Tag',
		'description'  => 'Top tags by subscriber count. Source: ReportController::contactsByTags (GET /reports/contacts-by-tags). Capability: fcrm_view_dashboard.',
		'category'     => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'tags', array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
			'count' => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy_get ) {
			return fluent_abilities_normalize_collection( $proxy_get( '/fluent-crm/v2/reports/contacts-by-tags', $input ), 'tags' );
		},
	) );

	// =========================================================================
	// 5.12.13 — get-report-contacts-by-lists
	// =========================================================================
	$reg->read( 'fluent-crm/get-report-contacts-by-lists', array(
		'label'        => 'Get CRM Contact Distribution by List',
		'description'  => 'Top lists by subscriber count. Source: ReportController::contactsByLists (GET /reports/contacts-by-lists). Capability: fcrm_view_dashboard.',
		'category'     => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'lists', array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
			'count' => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy_get ) {
			return fluent_abilities_normalize_collection( $proxy_get( '/fluent-crm/v2/reports/contacts-by-lists', $input ), 'lists' );
		},
	) );

	// =========================================================================
	// 5.12.14 — get-report-contacts-by-country
	// =========================================================================
	$reg->read( 'fluent-crm/get-report-contacts-by-country', array(
		'label'        => 'Get CRM Contact Distribution by Country',
		'description'  => 'Top countries by subscriber count. Source: ReportController::contactsByCountry (GET /reports/contacts-by-country). Capability: fcrm_view_dashboard.',
		'category'     => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'countries', array(
			'country' => array( 'type' => 'string' ),
			'count'   => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy_get ) {
			return $proxy_get( '/fluent-crm/v2/reports/contacts-by-country', $input );
		},
	) );

	// =========================================================================
	// 5.12.15 — get-report-recent-tags
	// =========================================================================
	$reg->read( 'fluent-crm/get-report-recent-tags', array(
		'label'        => 'Get CRM Recently Created or Applied Tags',
		'description'  => 'Tags created or applied within a recent window. Source: ReportController::recentTags (GET /reports/recent-tags). Capability: fcrm_view_dashboard.',
		'category'     => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'days' => array(
					'type'        => 'integer',
					'description' => 'Look-back window in days (default 30).',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'tags', array(
			'id'         => array( 'type' => 'integer' ),
			'title'      => array( 'type' => 'string' ),
			'count'      => array( 'type' => 'integer' ),
			'created_at' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback'      => function ( $input ) use ( $proxy_get ) {
			return fluent_abilities_normalize_collection( $proxy_get( '/fluent-crm/v2/reports/recent-tags', $input ), 'tags' );
		},
	) );

	// =========================================================================
	// 5.12.16 — get-report-top-campaigns
	// =========================================================================
	$reg->read( 'fluent-crm/get-report-top-campaigns', array(
		'label'        => 'Get CRM Top-Performing Campaigns',
		'description'  => 'Best-performing campaigns by selected metric (open_rate, click_rate, revenue). Source: ReportController::topCampaigns (GET /reports/top-campaigns). Capability: fcrm_view_dashboard.',
		'category'     => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'metric' => array(
					'type'        => 'string',
					'description' => 'Ranking metric (open_rate, click_rate, revenue, sent).',
				),
				'limit' => array(
					'type'        => 'integer',
					'description' => 'Top-N campaigns to return (default 10, max 50).',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'campaigns', array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
			'value' => array( 'type' => array( 'number', 'string' ) ),
		) ),
		'callback'      => function ( $input ) use ( $proxy_get ) {
			return $proxy_get( '/fluent-crm/v2/reports/top-campaigns', $input );
		},
	) );

	// =========================================================================
	// 5.12 supplementary — get-report-automations
	// =========================================================================
	$reg->read( 'fluent-crm/get-report-automations', array(
		'label'        => 'Get CRM Automations Aggregate Report',
		'description'  => 'List automations with aggregate metrics (subscribers, completed, conversion). Source: ReportController::automations (GET /reports/automations). Capability: fcrm_view_dashboard.',
		'category'     => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => $date_range_props,
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'automations', array(
			'id'                => array( 'type' => 'integer' ),
			'title'             => array( 'type' => 'string' ),
			'status'            => array( 'type' => 'string' ),
			'subscribers_count' => array( 'type' => 'integer' ),
			'completed_count'   => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy_get ) {
			return fluent_abilities_normalize_collection( $proxy_get( '/fluent-crm/v2/reports/automations', $input ), 'automations' );
		},
	) );

	// =========================================================================
	// 5.12 supplementary — get-report-automation-steps
	// =========================================================================
	$reg->read( 'fluent-crm/get-report-automation-steps', array(
		'label'        => 'Get CRM Automation Per-Step Metrics',
		'description'  => 'Per-step metrics for one automation. Source: ReportController::automationSteps (GET /reports/automations/{id}/steps). Capability: fcrm_view_dashboard.',
		'category'     => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'        => array(
					'type'        => 'integer',
					'description' => 'Funnel/automation ID.',
				),
				'date_from' => $date_range_props['date_from'],
				'date_to'   => $date_range_props['date_to'],
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'steps', array(
			'step_id'         => array( 'type' => 'integer' ),
			'action_name'     => array( 'type' => 'string' ),
			'sequence'        => array( 'type' => 'integer' ),
			'completed_count' => array( 'type' => 'integer' ),
			'pending_count'   => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy_get ) {
			$id    = (int) ( $input['id'] ?? 0 );
			$query = $input;
			unset( $query['id'] );
			return fluent_abilities_project_response( $proxy_get( '/fluent-crm/v2/reports/automations/' . $id . '/steps', $query ) );
		},
	) );

}
