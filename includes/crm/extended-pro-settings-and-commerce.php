<?php
/**
 * FluentCRM Pro settings & commerce-reports clusters:
 *  - §5.29 Pro settings — managers + sms (7 abilities)
 *  - §5.30 Commerce reports (Pro) (2 abilities)
 *
 * License + import_funnel endpoints are DENYLISTED per research §5.29 / §7.9.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', 'fluent_abilities_crm_register_extended_pro_settings_and_commerce', 11 );

function fluent_abilities_crm_register_extended_pro_settings_and_commerce() {

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
	// §5.29 — Pro settings — managers + sms (7)
	// =========================================================================

	$reg->read( 'fluent-crm/list-pro-managers', array(
		'label'         => 'List CRM Pro Sub-Admin Managers',
		'description'   => 'Sub-admin users with FCRM Pro access. Source: CampaignProSettingController::listManagers (GET /campaign-pro-settings/managers). Capability: fcrm_manage_settings.',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => fluent_abilities_schema_collection_output( 'managers', array(
			'id'           => array( 'type' => 'integer' ),
			'user_id'      => array( 'type' => 'integer' ),
			'display_name' => array( 'type' => 'string' ),
			'permissions'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/campaign-pro-settings/managers' );
		},
	) );

	$reg->write( 'fluent-crm/create-pro-manager', array(
		'label'         => 'Create CRM Pro Sub-Admin Manager',
		'description'   => 'Grant a WP user FCRM Pro manager access with permission list. Source: CampaignProSettingController::createManager (POST /campaign-pro-settings/managers).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'user_id' ),
			'properties' => array(
				'user_id'     => array( 'type' => 'integer' ),
				'permissions' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/campaign-pro-settings/managers', $input );
		},
	) );

	$reg->write( 'fluent-crm/update-pro-manager', array(
		'label'         => 'Update CRM Pro Sub-Admin Manager',
		'description'   => 'Update a manager\'s permissions. Source: CampaignProSettingController::updateManager (PUT /campaign-pro-settings/managers/{id}).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'          => array( 'type' => 'integer' ),
				'permissions' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			unset( $input['id'] );
			return $proxy( 'PUT', '/fluent-crm/v2/campaign-pro-settings/managers/' . $id, $input );
		},
	) );

	$reg->delete( 'fluent-crm/delete-pro-manager', array(
		'label'         => 'Delete CRM Pro Sub-Admin Manager',
		'description'   => 'Revoke FCRM Pro manager access. Source: CampaignProSettingController::deleteManager (DELETE /campaign-pro-settings/managers/{id}).',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'required' => array( 'id' ), 'properties' => array( 'id' => array( 'type' => 'integer' ) ) ),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'DELETE', '/fluent-crm/v2/campaign-pro-settings/managers/' . (int) ( $input['id'] ?? 0 ) );
		},
	) );

	$reg->read( 'fluent-crm/get-sms-settings', array(
		'label'         => 'Get CRM Pro SMS Provider Settings',
		'description'   => 'SMS-provider config. Credential redaction inherits from FCRM. Source: CampaignProSettingController::getSms (GET /campaign-pro-settings/sms).',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'provider'    => array( 'type' => array( 'string', 'null' ) ),
			'from_number' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/campaign-pro-settings/sms' );
		},
	) );

	$reg->write( 'fluent-crm/update-sms-settings', array(
		'label'         => 'Update CRM Pro SMS Provider Settings',
		'description'   => 'Save SMS-provider credentials. WRITES CREDENTIALS — mcp.public should be false at adapter layer. Source: CampaignProSettingController::saveSms (POST /campaign-pro-settings/sms).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'provider' ),
			'properties' => array(
				'provider'    => array( 'type' => 'string' ),
				'api_key'     => array( 'type' => 'string' ),
				'from_number' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/campaign-pro-settings/sms', $input );
		},
	) );

	$reg->write( 'fluent-crm/disable-sms-provider', array(
		'label'         => 'Disable CRM Pro SMS Provider',
		'description'   => 'Disable the active SMS provider. Source: CampaignProSettingController::disableSms (POST /campaign-pro-settings/sms/disable).',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/campaign-pro-settings/sms/disable' );
		},
	) );

	// =========================================================================
	// §5.30 — Commerce reports (Pro) — 2
	// =========================================================================

	$reg->read( 'fluent-crm/list-commerce-reports-for-provider', array(
		'label'         => 'List CRM Commerce Reports For Provider (Pro)',
		'description'   => 'Available commerce reports for the named provider (woo, edd, fluent-cart, etc.). Source: CommerceReportController::index (GET /commerce-reports/{provider}). Pro.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'provider' ),
			'properties' => array(
				'provider' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'reports', $obj ),
		'callback'      => function ( $input ) use ( $proxy ) {
			$provider = sanitize_key( (string) ( $input['provider'] ?? '' ) );
			return $proxy( 'GET', '/fluent-crm/v2/commerce-reports/' . $provider );
		},
	) );

	$reg->read( 'fluent-crm/get-commerce-report', array(
		'label'         => 'Get CRM Commerce Report (Pro)',
		'description'   => 'Provider-variant commerce report payload over a date range. Source: CommerceReportController::report (GET /commerce-reports/{provider}/report).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'provider' ),
			'properties' => array(
				'provider'  => array( 'type' => 'string' ),
				'date_from' => array( 'type' => 'string' ),
				'date_to'   => array( 'type' => 'string' ),
			),
		),
		'output_schema' => $obj,
		'callback'      => function ( $input ) use ( $proxy ) {
			$provider = sanitize_key( (string) ( $input['provider'] ?? '' ) );
			$q        = $input;
			unset( $q['provider'] );
			return $proxy( 'GET', '/fluent-crm/v2/commerce-reports/' . $provider . '/report', $q );
		},
	) );

}
