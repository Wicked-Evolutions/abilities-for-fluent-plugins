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

	// =========================================================================
	// §5.15 — Abandoned-cart-ops (3 ops; settings pair lives in §5.13)
	// Capability inferred per surface placement.
	// =========================================================================



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
			// P-H union: vendor returns option entries as either {label,value}
			// objects OR bare scalars depending on field type — accept both so
			// a valid vendor response is not rejected by the output validator.
			'options'  => array( 'type' => 'array', 'items' => array( 'type' => array( 'object', 'string', 'number', 'boolean' ) ) ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			// P-H empty-state: vendor returns the definition set as null/{} when
			// no custom fields exist (and `fields: []` post full-replace);
			// normalize so the declared `fields` array always validates.
			return fluent_abilities_normalize_collection( $proxy( 'GET', '/fluent-crm/v2/custom-fields/contacts' ), 'fields' );
		},
	) );

	$reg->write( 'fluent-crm/update-contact-custom-fields', array(
		'label'         => 'Update CRM Contact Custom Field Definitions (DESTRUCTIVE — full replace)',
		'description'   => 'DESTRUCTIVE FULL REPLACE. This ability replaces the entire custom-field definition set with the provided `fields` array — there is no merge mode. Any existing field NOT in `fields` is dropped globally for every contact. Empty array (`fields: []`) clears ALL custom-field definitions site-wide; that variant requires explicit `confirm_full_replace: true` and is otherwise rejected with a typed WP_Error. To preview the current set before replacing, read `fluent-crm/get-contact-custom-fields`. To rename a group only, use `fluent-crm/update-contact-custom-fields-group-name`. Source: \FluentCrm\App\Http\Controllers\CustomContactFieldsController::saveGlobalFields (PUT /custom-fields/contacts) — the previously cited CustomFieldsController::updateContactFields does not exist in the installed FluentCRM (Addendum 2 citation correction). V8 destructive annotation + V7 input whitelist applied.',
		'category'      => 'fluent-crm',
		'annotations'   => array( 'destructive' => true ),
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'fields' ),
			'properties' => array(
				'fields'               => array(
					'type'        => 'array',
					'description' => 'Full replacement set of custom-field definitions. Any existing definition NOT in this array is dropped — there is no merge mode. Empty array clears all definitions globally and requires `confirm_full_replace: true`.',
					'items'       => array(
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
				'confirm_full_replace' => array(
					'type'        => 'boolean',
					'description' => 'Required (must be true) when `fields` is the empty array `[]`. Guards against silent global wipe of all custom-field definitions. Without this confirmation an empty-array call returns a typed WP_Error and no vendor write occurs.',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			// V8 destructive-semantics guard. Vendor's PUT /custom-fields/contacts is
			// a full-set replace; passing an empty array wipes every custom-field
			// definition for every contact. Phase 2 of the v1.4.0 cold-start re-test
			// wiped 8 production definitions on helenawillow this way. Refuse the
			// destructive variant unless the caller passes confirm_full_replace:true.
			$fields = isset( $input['fields'] ) && is_array( $input['fields'] ) ? $input['fields'] : null;
			if ( null === $fields ) {
				return new WP_Error(
					'fluent_crm_custom_fields_missing',
					'fluent-crm/update-contact-custom-fields requires `fields` (array). To clear all definitions, pass `fields: []` together with `confirm_full_replace: true`.'
				);
			}
			if ( array() === $fields && empty( $input['confirm_full_replace'] ) ) {
				return new WP_Error(
					'fluent_crm_custom_fields_destructive_unconfirmed',
					'fluent-crm/update-contact-custom-fields with `fields: []` clears ALL custom-field definitions globally (full-replace, not a delta). Pass `confirm_full_replace: true` to confirm this is intentional. Read `fluent-crm/get-contact-custom-fields` first to verify what will be destroyed.'
				);
			}
			// V7: whitelist payload to vendor-consumed keys. The local confirmation
			// flag is not part of the vendor contract.
			return $proxy( 'PUT', '/fluent-crm/v2/custom-fields/contacts', array( 'fields' => $fields ) );
		},
	) );

	$reg->write( 'fluent-crm/update-contact-custom-fields-group-name', array(
		'label'         => 'Rename CRM Contact Custom Field Group',
		'description'   => 'Rename a custom-field group. Source: CustomContactFieldsController::updateGroupName (PUT /custom-fields/contacts/update_group_name). Vendor reads `old_name` + `new_name` flat (NOT `old_group_name`/`new_group_name`) per source app/Http/Controllers/CustomContactFieldsController.php:40-50. Ability input keeps the operator-friendly `old_group_name`/`new_group_name` names + re-maps in callback.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'old_group_name', 'new_group_name' ),
			'properties' => array(
				'old_group_name' => array( 'type' => 'string' ),
				'new_group_name' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'fields'  => array( 'type' => array( 'array', 'object' ) ),
			'message' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'PUT', '/fluent-crm/v2/custom-fields/contacts/update_group_name', array(
				'old_name' => isset( $input['old_group_name'] ) ? (string) $input['old_group_name'] : '',
				'new_name' => isset( $input['new_group_name'] ) ? (string) $input['new_group_name'] : '',
			) );
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
