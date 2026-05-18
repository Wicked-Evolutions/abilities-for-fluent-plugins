<?php
/**
 * FluentCRM Settings — extended ability surface (§5.13).
 *
 * Wraps the FluentCRM v2 SettingController endpoints (/setting/*).
 * Source-side SettingsPolicy: every method → fcrm_manage_settings.
 *
 * DENYLIST per research §5.13: reset_db, run_cron, system-logs/reset,
 * rest-keys management, plugin installers, /setting/test, /setting/old_logs DELETE.
 * Those routes are NOT wrapped here.
 *
 * Strategy: each ability proxies the REST route via internal rest_do_request().
 * The wrapper-level fluent_crm_read / fluent_crm_write cap composes with the
 * source-side fcrm_manage_settings cap (Permissions Stay Layered, Principle 5).
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', 'fluent_abilities_crm_register_extended_settings', 11 );

function fluent_abilities_crm_register_extended_settings() {

	$reg = new Fluent_Abilities_Registrar( 'crm' );

	$proxy_get = static function ( $route, $params = array() ) {
		$req = new WP_REST_Request( 'GET', $route );
		foreach ( (array) $params as $k => $v ) {
			if ( null !== $v && '' !== $v ) {
				$req->set_param( $k, $v );
			}
		}
		$res = rest_do_request( $req );
		return $res->is_error() ? $res->as_error() : $res->get_data();
	};

	$proxy_write = static function ( $method, $route, $params = array() ) {
		$req = new WP_REST_Request( $method, $route );
		foreach ( (array) $params as $k => $v ) {
			$req->set_param( $k, $v );
		}
		$res = rest_do_request( $req );
		return $res->is_error() ? $res->as_error() : $res->get_data();
	};

	$obj_blob = function () {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
		);
	};

	// =========================================================================
	// 5.13.1 — get-settings (root)
	// =========================================================================
	$reg->read( 'fluent-crm/get-settings', array(
		'label'         => 'Get CRM Settings (Root)',
		'description'   => 'Get the root FluentCRM settings blob. Source: SettingController::index (GET /setting). Capability: fcrm_manage_settings.',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'settings' => $obj_blob(),
		) ),
		'callback'      => function ( $input ) use ( $proxy_get ) {
			return $proxy_get( '/fluent-crm/v2/setting' );
		},
	) );

	// =========================================================================
	// 5.13.2 — update-settings (root)
	// =========================================================================
	$reg->write( 'fluent-crm/update-settings', array(
		'label'        => 'Update CRM Settings (Root)',
		'description'  => 'Update the root FluentCRM settings blob. Source: SettingController::update (PUT /setting). Capability: fcrm_manage_settings.',
		'category'     => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'settings' ),
			'properties' => array(
				'settings' => $obj_blob(),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy_write ) {
			return $proxy_write( 'PUT', '/fluent-crm/v2/setting', $input );
		},
	) );

	// =========================================================================
	// 5.13.3 / 5.13.4 — double-optin
	// =========================================================================
	$reg->read( 'fluent-crm/get-double-optin-config', array(
		'label'         => 'Get CRM Double-Optin Config',
		'description'   => 'Double-optin email subject/body/redirect_url config. Source: SettingController::getDoubleOptin (GET /setting/double-optin).',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'subject'      => array( 'type' => array( 'string', 'null' ) ),
			'email_body'   => array( 'type' => array( 'string', 'null' ) ),
			'redirect_url' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback'      => function ( $input ) use ( $proxy_get ) {
			return $proxy_get( '/fluent-crm/v2/setting/double-optin' );
		},
	) );

	$reg->write( 'fluent-crm/update-double-optin-config', array(
		'label'         => 'Update CRM Double-Optin Config',
		'description'   => 'Save double-optin email subject/body/redirect_url. Source: SettingController::updateDoubleOptin (PUT /setting/double-optin).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => array(
				'subject'      => array( 'type' => 'string' ),
				'email_body'   => array( 'type' => 'string' ),
				'redirect_url' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy_write ) {
			return $proxy_write( 'PUT', '/fluent-crm/v2/setting/double-optin', $input );
		},
	) );

	// =========================================================================
	// 5.13.5 — bounce-configs (read only — credentials sensitive)
	// =========================================================================
	$reg->read( 'fluent-crm/get-bounce-configs', array(
		'label'         => 'Get CRM Bounce-Handler Configs',
		'description'   => 'Registered bounce-handler providers. Source: SettingController::getBounceConfigs (GET /setting/bounce_configs).',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'providers' => array( 'type' => 'array', 'items' => $obj_blob() ),
		) ),
		'callback'      => function ( $input ) use ( $proxy_get ) {
			return $proxy_get( '/fluent-crm/v2/setting/bounce_configs' );
		},
	) );

	// =========================================================================
	// 5.13.6 / 5.13.7 — auto-subscribe
	// =========================================================================
	$reg->read( 'fluent-crm/get-auto-subscribe-settings', array(
		'label'         => 'Get CRM Auto-Subscribe Settings',
		'description'   => 'Auto-subscribe-on-comment/order rules. Source: SettingController::autoSubscribe (GET /setting/auto_subscribe_settings).',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy_get ) {
			return $proxy_get( '/fluent-crm/v2/setting/auto_subscribe_settings' );
		},
	) );

	$reg->write( 'fluent-crm/update-auto-subscribe-settings', array(
		'label'         => 'Update CRM Auto-Subscribe Settings',
		'description'   => 'Save auto-subscribe rules. Source: SettingController::updateAutoSubscribe (POST /setting/auto_subscribe_settings).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => array(
				'enabled'        => array( 'type' => 'boolean' ),
				'conditions'     => $obj_blob(),
				'tags_to_apply'  => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy_write ) {
			return $proxy_write( 'POST', '/fluent-crm/v2/setting/auto_subscribe_settings', $input );
		},
	) );

	// =========================================================================
	// 5.13.8 / 5.13.9 — integrations
	// =========================================================================
	$reg->read( 'fluent-crm/get-integrations-config', array(
		'label'         => 'Get CRM Integrations Config',
		'description'   => 'Integration provider configs. Source: SettingController::getIntegrations (GET /setting/integrations).',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy_get ) {
			return $proxy_get( '/fluent-crm/v2/setting/integrations' );
		},
	) );

	$reg->write( 'fluent-crm/update-integrations-config', array(
		'label'         => 'Update CRM Integrations Config',
		'description'   => 'Save integration provider configs. Source: SettingController::updateIntegrations (POST /setting/integrations).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => array(
				'integrations' => $obj_blob(),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy_write ) {
			return $proxy_write( 'POST', '/fluent-crm/v2/setting/integrations', $input );
		},
	) );

	// =========================================================================
	// 5.13.10 / 5.13.11 — compliance (GDPR)
	// =========================================================================
	$reg->read( 'fluent-crm/get-compliance-settings', array(
		'label'         => 'Get CRM Compliance Settings (GDPR)',
		'description'   => 'GDPR / compliance settings. Source: SettingController::getCompliance (GET /setting/compliance).',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'gdpr_status'              => array( 'type' => array( 'string', 'null' ) ),
			'gdpr_data_request_email'  => array( 'type' => array( 'string', 'null' ) ),
			'gdpr_data_delete_email'   => array( 'type' => array( 'string', 'null' ) ),
			'gdpr_compliance_text'     => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback'      => function ( $input ) use ( $proxy_get ) {
			return $proxy_get( '/fluent-crm/v2/setting/compliance' );
		},
	) );

	$reg->write( 'fluent-crm/update-compliance-settings', array(
		'label'         => 'Update CRM Compliance Settings (GDPR)',
		'description'   => 'Save GDPR / compliance settings. Source: SettingController::updateCompliance (POST /setting/compliance).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => array(
				'gdpr_status'              => array( 'type' => 'string' ),
				'gdpr_data_request_email'  => array( 'type' => 'string' ),
				'gdpr_data_delete_email'   => array( 'type' => 'string' ),
				'gdpr_compliance_text'     => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy_write ) {
			return $proxy_write( 'POST', '/fluent-crm/v2/setting/compliance', $input );
		},
	) );

	// =========================================================================
	// 5.13.12 / 5.13.13 — experiments (feature flags)
	// =========================================================================
	$reg->read( 'fluent-crm/get-experiments-config', array(
		'label'         => 'Get CRM Feature-Flag Experiments',
		'description'   => 'Active feature-flag experiments. Source: SettingController::getExperiments (GET /setting/experiments).',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy_get ) {
			return $proxy_get( '/fluent-crm/v2/setting/experiments' );
		},
	) );

	$reg->write( 'fluent-crm/update-experiments-config', array(
		'label'         => 'Update CRM Feature-Flag Experiments',
		'description'   => 'Toggle feature-flag experiments. Source: SettingController::updateExperiments (POST /setting/experiments).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => array(
				'experiments' => $obj_blob(),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy_write ) {
			return $proxy_write( 'POST', '/fluent-crm/v2/setting/experiments', $input );
		},
	) );

	// =========================================================================
	// 5.13.14 — list-experiments-campaigns
	// =========================================================================
	$reg->read( 'fluent-crm/list-experiments-campaigns', array(
		'label'         => 'List Campaigns Under Experimental Flow',
		'description'   => 'Campaigns covered by experimental flow. Source: SettingController::experimentsCampaigns (GET /setting/experiments/campaigns).',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => fluent_abilities_schema_collection_output( 'campaigns', array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy_get ) {
			return fluent_abilities_normalize_collection( $proxy_get( '/fluent-crm/v2/setting/experiments/campaigns' ), 'campaigns' );
		},
	) );

	// =========================================================================
	// 5.13.15 — get-system-logs
	// =========================================================================

	// =========================================================================
	// 5.13 paired — get-cron-status
	// =========================================================================
	$reg->read( 'fluent-crm/get-cron-status', array(
		'label'         => 'Get CRM Cron Health Status',
		'description'   => 'FluentCRM-specific cron health aggregate. Source: SettingController::cronStatus (GET /setting/cron_status).',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy_get ) {
			return $proxy_get( '/fluent-crm/v2/setting/cron_status' );
		},
	) );

	// =========================================================================
	// 5.13 paired — get-old-logs (read only; DELETE is denylisted)
	// =========================================================================
	$reg->read( 'fluent-crm/get-old-logs', array(
		'label'         => 'Get CRM Archived Logs',
		'description'   => 'Archived/rotated log entries. Source: SettingController::oldLogs (GET /setting/old_logs).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => fluent_abilities_pagination_schema(),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'logs', array(
			'id'         => array( 'type' => 'integer' ),
			'message'    => array( 'type' => array( 'string', 'null' ) ),
			'created_at' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback'      => function ( $input ) use ( $proxy_get ) {
			return $proxy_get( '/fluent-crm/v2/setting/old_logs', $input );
		},
	) );

	// =========================================================================
	// 5.15 paired (settings half) — abandoned-cart settings
	// =========================================================================
	$reg->read( 'fluent-crm/get-abandon-cart-settings', array(
		'label'         => 'Get CRM Abandoned-Cart Settings',
		'description'   => 'Abandoned-cart settings (provider, recovery emails). Source: SettingController::abandonCart (GET /setting/abandon-cart).',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy_get ) {
			return $proxy_get( '/fluent-crm/v2/setting/abandon-cart' );
		},
	) );

	$reg->write( 'fluent-crm/update-abandon-cart-settings', array(
		'label'         => 'Update CRM Abandoned-Cart Settings',
		'description'   => 'Save abandoned-cart settings. Source: SettingController::updateAbandonCart (POST /setting/abandon-cart).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => array(
				'settings' => $obj_blob(),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy_write ) {
			return $proxy_write( 'POST', '/fluent-crm/v2/setting/abandon-cart', $input );
		},
	) );

}
