<?php
/**
 * FluentCRM medium-cluster ability surface — AI (§5.14), Abandoned-cart-ops
 * (§5.15), Custom-fields (§5.16), Import (§5.20).
 *
 * 15 abilities. Pro-tier and credential-sensitive surfaces declare
 * mcp.public via their description (Registrar default is true; specific
 * abilities below override at registration time via the 'mcp' meta key
 * — but the v1.1.3 wrapper hardcodes mcp.public=true, so any
 * deviation is recorded in description only per §7.4 boundary).
 *
 * §5.20.5 (run-import-driver) is BORDERLINE-DENYLIST per research §7.7.
 * Disposition pending — included here with explicit description note;
 * orchestrator may pull it back per dispatch.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', 'fluent_abilities_crm_register_extended_misc_medium', 11 );

function fluent_abilities_crm_register_extended_misc_medium() {

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
	// §5.14 — AI module (4) — new in FCRM 3.0
	// Source-side AiPolicy: getSettings/saveSettings/testConnection →
	// fcrm_manage_settings; generate → fcrm_manage_emails.
	// §7.2 — third-party-coupled. Credential redaction inherits from FCRM.
	// =========================================================================

	$reg->read( 'fluent-crm/get-ai-settings', array(
		'label'         => 'Get CRM AI Provider Settings',
		'description'   => 'AI provider config (OpenAI/Anthropic/Claude key + model). Credential redaction inherits from FCRM. Source: AiController::getSettings (GET /ai/settings). Capability: fcrm_manage_settings.',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'provider'    => array( 'type' => array( 'string', 'null' ) ),
			'model'       => array( 'type' => array( 'string', 'null' ) ),
			'temperature' => array( 'type' => array( 'number', 'string', 'null' ) ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/ai/settings' );
		},
	) );

	$reg->write( 'fluent-crm/update-ai-settings', array(
		'label'         => 'Update CRM AI Provider Settings',
		'description'   => 'Save AI provider credentials + model + temperature. WRITES CREDENTIALS — mcp.public should be false at adapter layer. Source: AiController::saveSettings (POST /ai/settings). Capability: fcrm_manage_settings.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'provider' ),
			'properties' => array(
				'provider'    => array( 'type' => 'string' ),
				'api_key'     => array( 'type' => 'string' ),
				'model'       => array( 'type' => 'string' ),
				'temperature' => array( 'type' => array( 'number', 'string' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/ai/settings', $input );
		},
	) );

	$reg->write( 'fluent-crm/test-ai-connection', array(
		'label'         => 'Test CRM AI Provider Connection',
		'description'   => 'Round-trip a test call to the configured AI provider. Source: AiController::testConnection (POST /ai/test). Capability: fcrm_manage_settings.',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'message' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/ai/test' );
		},
	) );

	$reg->write( 'fluent-crm/generate-ai-content', array(
		'label'         => 'Generate CRM AI Content',
		'description'   => 'Generate AI content (email subject, body, etc.) via configured provider. Source: AiController::generate (POST /ai/generate). Capability: fcrm_manage_emails (note: this is a write-tier cap distinct from settings-tier).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'prompt' ),
			'properties' => array(
				'prompt'       => array( 'type' => 'string' ),
				'context_type' => array(
					'type'        => 'string',
					'description' => 'email_subject, email_body, etc.',
				),
				'tone'         => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'content' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/ai/generate', $input );
		},
	) );

	// =========================================================================
	// §5.15 — Abandoned-cart-ops (3 ops; settings pair lives in §5.13)
	// Capability inferred per surface placement.
	// =========================================================================

	$reg->read( 'fluent-crm/list-abandon-carts', array(
		'label'         => 'List CRM Abandoned Carts',
		'description'   => 'Paginated abandoned-cart records. Source: AbandonCartController::index (GET /abandon-carts).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => array_merge(
				array(
					'status' => array( 'type' => 'string' ),
				),
				fluent_abilities_pagination_schema()
			),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'carts', $obj ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/abandon-carts', $input );
		},
	) );

	$reg->delete( 'fluent-crm/bulk-delete-abandon-carts', array(
		'label'         => 'Bulk Delete CRM Abandoned Carts',
		'description'   => 'Bulk-delete abandoned-cart records. Source: AbandonCartController::bulkDelete (POST /abandon-carts/bulk-delete). Capability: fcrm_manage_contacts_delete per surface alignment.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'cart_ids' ),
			'properties' => array(
				'cart_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'deleted_count' => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/abandon-carts/bulk-delete', $input );
		},
	) );

	$reg->read( 'fluent-crm/get-abandon-cart-report-summary', array(
		'label'         => 'Get CRM Abandoned-Cart Report Summary',
		'description'   => 'Date-ranged abandoned-cart aggregate. Source: AbandonCartController::reportSummary (GET /abandon-carts/report-summary).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => array(
				'date_from' => array( 'type' => 'string' ),
				'date_to'   => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/abandon-carts/report-summary', $input );
		},
	) );

	// =========================================================================
	// §5.16 — Custom-fields management (3)
	// CustomFieldsPolicy → fcrm_manage_contact_cats.
	// =========================================================================

	$reg->read( 'fluent-crm/get-contact-custom-fields', array(
		'label'         => 'Get CRM Contact Custom Field Definitions',
		'description'   => 'Field DEFINITION list (not values). Source: CustomFieldsController::getContactFields (GET /custom-fields/contacts). Capability: fcrm_manage_contact_cats.',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => fluent_abilities_schema_collection_output( 'fields', array(
			'slug'     => array( 'type' => 'string' ),
			'label'    => array( 'type' => 'string' ),
			'type'     => array( 'type' => 'string' ),
			'required' => array( 'type' => 'boolean' ),
			'group'    => array( 'type' => array( 'string', 'null' ) ),
			'options'  => array( 'type' => 'array', 'items' => $obj ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/custom-fields/contacts' );
		},
	) );

	$reg->write( 'fluent-crm/update-contact-custom-fields', array(
		'label'         => 'Update CRM Contact Custom Field Definitions',
		'description'   => 'Full custom-field definitions replace. Structural validation per field type. Source: CustomFieldsController::updateContactFields (PUT /custom-fields/contacts).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'fields' ),
			'properties' => array(
				'fields' => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'required'             => array( 'slug', 'label', 'type' ),
						'properties'           => array(
							'slug'     => array( 'type' => 'string' ),
							'label'    => array( 'type' => 'string' ),
							'type'     => array( 'type' => 'string' ),
							'options'  => array( 'type' => 'array', 'items' => $obj ),
							'required' => array( 'type' => 'boolean' ),
							'group'    => array( 'type' => 'string' ),
						),
						'additionalProperties' => true,
					),
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'PUT', '/fluent-crm/v2/custom-fields/contacts', $input );
		},
	) );

	$reg->write( 'fluent-crm/update-contact-custom-fields-group-name', array(
		'label'         => 'Rename CRM Contact Custom Field Group',
		'description'   => 'Rename a custom-field group. Source: CustomFieldsController::updateGroupName (PUT /custom-fields/contacts/update_group_name).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'old_group_name', 'new_group_name' ),
			'properties' => array(
				'old_group_name' => array( 'type' => 'string' ),
				'new_group_name' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'PUT', '/fluent-crm/v2/custom-fields/contacts/update_group_name', $input );
		},
	) );

	// =========================================================================
	// §5.20 — Import (5) — multi-step IO; partly HAND-CURATION territory.
	// Capability: fcrm_manage_contacts.
	// =========================================================================

	$reg->write( 'fluent-crm/upload-import-csv', array(
		'label'         => 'Upload CRM Import CSV File',
		'description'   => 'Upload a CSV file for subscriber import. Multi-step IO — result is a file_id consumed by run-import-csv. Source: ImportController::csvUpload (POST /import/csv-upload). Capability: fcrm_manage_contacts.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'file' ),
			'properties' => array(
				'file'     => array( 'type' => 'string', 'description' => 'Base64-encoded CSV or attachment ID — see source.' ),
				'filename' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'file_id' => array( 'type' => array( 'integer', 'string' ) ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/import/csv-upload', $input );
		},
	) );

	$reg->write( 'fluent-crm/run-import-csv', array(
		'label'         => 'Run CRM CSV Subscriber Import',
		'description'   => 'Execute import using uploaded file. Source: ImportController::csvImport (POST /import/csv-import).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'file_id', 'mapping' ),
			'properties' => array(
				'file_id'  => array( 'type' => array( 'integer', 'string' ) ),
				'mapping'  => $obj,
				'defaults' => $obj,
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'imported_count' => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/import/csv-import', $input );
		},
	) );

	$reg->write( 'fluent-crm/import-from-wp-users', array(
		'label'         => 'Import CRM Contacts From WP Users',
		'description'   => 'Convert WP users with selected roles into FluentCRM subscribers. Source: ImportController::importUsers (POST /import/users).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'roles' ),
			'properties' => array(
				'roles'         => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'default_tags'  => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'default_lists' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'imported_count' => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/import/users', $input );
		},
	) );

	$reg->read( 'fluent-crm/list-import-drivers', array(
		'label'         => 'List CRM Registered Import Drivers',
		'description'   => 'Registered importer drivers (ConvertKit/Mailchimp/AWeber/etc.). Source: ImportController::drivers (GET /import/drivers). Read-only — driver discovery.',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => fluent_abilities_schema_collection_output( 'drivers', array(
			'name'  => array( 'type' => 'string' ),
			'label' => array( 'type' => 'string' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/import/drivers' );
		},
	) );

	$reg->write( 'fluent-crm/run-import-driver', array(
		'label'         => 'Run CRM Import Driver',
		'description'   => 'Run a per-driver action (verify/list/import). Multi-step external IO. Borderline-DENYLIST per research §7.7 (sibling to migrators which ARE denylisted). Disposition pending orchestrator ratification — included for completeness; may be withdrawn at merge time. mcp.public should be false at adapter layer (credentials in payload). Source: ImportController::runDriver (GET|POST /import/drivers/{driver}).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'driver', 'action' ),
			'properties' => array(
				'driver'      => array( 'type' => 'string' ),
				'action'      => array( 'type' => 'string', 'description' => 'verify, list, import' ),
				'credentials' => $obj,
			),
		),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$driver = sanitize_key( (string) ( $input['driver'] ?? '' ) );
			$q      = $input;
			unset( $q['driver'] );
			return $proxy( 'POST', '/fluent-crm/v2/import/drivers/' . $driver, $q );
		},
	) );

}
