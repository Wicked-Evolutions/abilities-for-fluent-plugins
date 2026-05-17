<?php
/**
 * FluentCRM Companies extension (Pro) — §5.23 (17 abilities).
 *
 * Source-side CompanyPolicy: Helper::isCompanyEnabled() && fcrm_manage_contact_cats
 *   delete/deleteSubscribes → ...&& fcrm_manage_contact_cats_delete
 *
 * Pro-tier (FluentCampaign\App\Models\Company). Runtime fluent_abilities_pro_gate
 * enforces Pro license per the existing wrapper convention. Description carries
 * mcp.public: false (company entities tied to subscriber PII via attach-subscriber).
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', 'fluent_abilities_crm_register_extended_pro_companies', 11 );

function fluent_abilities_crm_register_extended_pro_companies() {

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

	$obj          = array( 'type' => 'object', 'additionalProperties' => true );
	$company_item = array(
		'id'              => array( 'type' => 'integer' ),
		'name'            => array( 'type' => 'string' ),
		'description'     => array( 'type' => array( 'string', 'null' ) ),
		'industry'        => array( 'type' => array( 'string', 'null' ) ),
		'employees_number' => array( 'type' => array( 'integer', 'string', 'null' ) ),
		'type'            => array( 'type' => array( 'string', 'null' ) ),
		'website'         => array( 'type' => array( 'string', 'null' ) ),
		'phone'           => array( 'type' => array( 'string', 'null' ) ),
		'owner_id'        => array( 'type' => array( 'integer', 'null' ) ),
		'created_at'      => array( 'type' => array( 'string', 'null' ) ),
	);

	// 5.23.1 — get-company
	$reg->read( 'fluent-crm/get-company', array(
		'label'         => 'Get CRM Company (Pro)',
		'description'   => 'Get a single Pro company. Source: CompanyController::show (GET /companies/{id}). Pro-tier. mcp.public: false.',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'required' => array( 'id' ), 'properties' => array( 'id' => array( 'type' => 'integer' ) ) ),
		'output_schema' => fluent_abilities_schema_item_output( $company_item ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return fluent_abilities_project_response( $proxy( 'GET', '/fluent-crm/v2/companies/' . (int) ( $input['id'] ?? 0 ) ) );
		},
	) );

	// 5.23.2 — create-company
	$reg->write( 'fluent-crm/create-company', array(
		'label'         => 'Create CRM Company (Pro)',
		'description'   => 'Create a Pro company. Source: CompanyController::store (POST /companies). Capability: fcrm_manage_contact_cats.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'name' ),
			'properties' => array(
				'name'             => array( 'type' => 'string' ),
				'description'      => array( 'type' => 'string' ),
				'industry'         => array( 'type' => 'string' ),
				'employees_number' => array( 'type' => array( 'integer', 'string' ) ),
				'type'             => array( 'type' => 'string' ),
				'address'          => array( 'type' => 'string' ),
				'phone'            => array( 'type' => 'string' ),
				'website'          => array( 'type' => 'string' ),
				'owner_id'         => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( $company_item ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return fluent_abilities_project_response( $proxy( 'POST', '/fluent-crm/v2/companies', $input ) );
		},
	) );

	// 5.23.3 — update-company
	$reg->write( 'fluent-crm/update-company', array(
		'label'         => 'Update CRM Company (Pro)',
		'description'   => 'Update Pro company. Source: CompanyController::update (PUT /companies/{id}).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'               => array( 'type' => 'integer' ),
				'name'             => array( 'type' => 'string' ),
				'description'      => array( 'type' => 'string' ),
				'industry'         => array( 'type' => 'string' ),
				'employees_number' => array( 'type' => array( 'integer', 'string' ) ),
				'type'             => array( 'type' => 'string' ),
				'address'          => array( 'type' => 'string' ),
				'phone'            => array( 'type' => 'string' ),
				'website'          => array( 'type' => 'string' ),
				'owner_id'         => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			unset( $input['id'] );
			return fluent_abilities_project_response( $proxy( 'PUT', '/fluent-crm/v2/companies/' . $id, $input ) );
		},
	) );

	// 5.23.4 — delete-company
	$reg->delete( 'fluent-crm/delete-company', array(
		'label'         => 'Delete CRM Company (Pro)',
		'description'   => 'Delete Pro company. Source: CompanyController::destroy (DELETE /companies/{id}). Capability: fcrm_manage_contact_cats_delete.',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'required' => array( 'id' ), 'properties' => array( 'id' => array( 'type' => 'integer' ) ) ),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'DELETE', '/fluent-crm/v2/companies/' . (int) ( $input['id'] ?? 0 ) );
		},
	) );

	// 5.23.5 — search-companies
	$reg->read( 'fluent-crm/search-companies', array(
		'label'         => 'Search CRM Companies (Pro)',
		'description'   => 'Search Pro companies. Source: CompanyController::search (GET /companies/search).',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'required' => array( 'q' ), 'properties' => array( 'q' => array( 'type' => 'string' ) ) ),
		'output_schema' => fluent_abilities_schema_collection_output( 'companies', $company_item ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/companies/search', $input );
		},
	) );

	// 5.23.6 — search-unattached-contacts-for-company
	$reg->read( 'fluent-crm/search-unattached-contacts-for-company', array(
		'label'         => 'Search Contacts Not In Any Company (Pro)',
		'description'   => 'Contacts that are not yet attached to any Pro company. Source: CompanyController::searchUnattachedContacts (GET /companies/search-unattached-contacts).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'company_id', 'q' ),
			'properties' => array(
				'company_id' => array( 'type' => 'integer' ),
				'q'          => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'contacts', $obj ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/companies/search-unattached-contacts', $input );
		},
	) );

	// 5.23.7 — update-companies-property
	$reg->write( 'fluent-crm/update-companies-property', array(
		'label'         => 'Update CRM Companies Single Property (Pro)',
		'description'   => 'Set one property across many Pro companies. Source: CompanyController::updateProperty (PUT /companies/companies-property). Vendor reads `companies` (NOT `company_ids`) per source app/Http/Controllers/CompanyController.php:269. valid columns: type, logo, owner_id, refetch_logo.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'company_ids', 'property', 'value' ),
			'properties' => array(
				'company_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ), 'description' => 'Operator-facing input — re-mapped to vendor-expected `companies` key in callback.' ),
				'property'    => array( 'type' => 'string' ),
				'value'       => array( 'type' => array( 'string', 'integer', 'boolean', 'null' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$payload = array(
				'companies' => isset( $input['company_ids'] ) ? array_map( 'intval', (array) $input['company_ids'] ) : array(),
				'property'  => isset( $input['property'] ) ? (string) $input['property'] : '',
				'value'     => $input['value'] ?? '',
			);
			return $proxy( 'PUT', '/fluent-crm/v2/companies/companies-property', $payload );
		},
	) );

	// 5.23.8 — attach-subscribers-to-company
	$reg->write( 'fluent-crm/attach-subscribers-to-company', array(
		'label'         => 'Attach Subscribers To CRM Company (Pro)',
		'description'   => 'Attach subscribers to one or more Pro companies. Source: CompanyController::attachSubscribers (POST /companies/attach-subscribers). Vendor reads `subscriber_ids[]` + `company_ids[]` (both plural) per source app/Http/Controllers/CompanyController.php:135-138.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'company_ids', 'subscriber_ids' ),
			'properties' => array(
				'company_ids'    => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'subscriber_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'companies' => array( 'type' => array( 'array', 'object' ) ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/companies/attach-subscribers', array(
				'subscriber_ids' => array_map( 'intval', (array) ( $input['subscriber_ids'] ?? array() ) ),
				'company_ids'    => array_map( 'intval', (array) ( $input['company_ids'] ?? array() ) ),
			) );
		},
	) );

	// 5.23.9 — detach-subscribers-from-company
	$reg->write( 'fluent-crm/detach-subscribers-from-company', array(
		'label'         => 'Detach Subscribers From CRM Company (Pro)',
		'description'   => 'Detach subscribers from one or more Pro companies. Source: CompanyController::detachSubscribers (POST /companies/detach-subscribers). Vendor reads `subscriber_ids[]` + `company_ids[]` (both plural) per source app/Http/Controllers/CompanyController.php:154-155.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'company_ids', 'subscriber_ids' ),
			'properties' => array(
				'company_ids'    => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'subscriber_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/companies/detach-subscribers', array(
				'subscriber_ids' => array_map( 'intval', (array) ( $input['subscriber_ids'] ?? array() ) ),
				'company_ids'    => array_map( 'intval', (array) ( $input['company_ids'] ?? array() ) ),
			) );
		},
	) );

	// 5.23.10 — do-bulk-action-companies
	$reg->write( 'fluent-crm/do-bulk-action-companies', array(
		'label'         => 'Bulk Action On CRM Companies (Pro)',
		'description'   => 'Bulk operation across Pro companies. Source: CompanyController::bulkAction (POST /companies/do-bulk-action).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'company_ids', 'action_name' ),
			'properties' => array(
				'company_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'action_name' => array( 'type' => 'string' ),
				'action_data' => $obj,
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/companies/do-bulk-action', $input );
		},
	) );

	// 5.23.11 — list-company-notes
	$reg->read( 'fluent-crm/list-company-notes', array(
		'label'         => 'List CRM Company Notes (Pro)',
		'description'   => 'Paginated notes attached to a company. Source: CompanyController::listNotes (GET /companies/{id}/notes).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array_merge(
				array( 'id' => array( 'type' => 'integer' ) ),
				fluent_abilities_pagination_schema()
			),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'notes', $obj ),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			$q  = $input;
			unset( $q['id'] );
			return fluent_abilities_normalize_collection( $proxy( 'GET', '/fluent-crm/v2/companies/' . $id . '/notes', $q ), 'notes' );
		},
	) );

	// 5.23.12 — create-company-note
	$reg->write( 'fluent-crm/create-company-note', array(
		'label'         => 'Create CRM Company Note (Pro)',
		'description'   => 'Add a note to a company. Source: CompanyController::createNote (POST /companies/{id}/notes). V10: vendor controller may TypeError when FluentCampaign Pro is inactive or the validator request shape mismatches; registrar returns WP_Error instead.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id', 'description' ),
			'properties' => array(
				'id'          => array( 'type' => 'integer' ),
				'title'       => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
				'type'        => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			unset( $input['id'] );
			// V10: convert vendor-side TypeError into a typed WP_Error (P-K pattern).
			try {
				return $proxy( 'POST', '/fluent-crm/v2/companies/' . $id . '/notes', $input );
			} catch ( \Throwable $e ) {
				return new WP_Error( 'vendor_precondition_failed', 'FluentCRM create-company-note failed: ' . $e->getMessage() );
			}
		},
	) );

	// 5.23.13 — update-company-note
	$reg->write( 'fluent-crm/update-company-note', array(
		'label'         => 'Update CRM Company Note (Pro)',
		'description'   => 'Update a note attached to a company. Source: CompanyController::updateNote (PUT /companies/{id}/notes/{note_id}).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id', 'note_id' ),
			'properties' => array(
				'id'          => array( 'type' => 'integer' ),
				'note_id'     => array( 'type' => 'integer' ),
				'title'       => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
				'type'        => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id  = (int) ( $input['id'] ?? 0 );
			$nid = (int) ( $input['note_id'] ?? 0 );
			$q   = $input;
			unset( $q['id'], $q['note_id'] );
			return $proxy( 'PUT', '/fluent-crm/v2/companies/' . $id . '/notes/' . $nid, $q );
		},
	) );

	// 5.23.14 — delete-company-note
	$reg->delete( 'fluent-crm/delete-company-note', array(
		'label'         => 'Delete CRM Company Note (Pro)',
		'description'   => 'Delete a note attached to a company. Source: CompanyController::deleteNote (DELETE /companies/{id}/notes/{note_id}). Capability: fcrm_manage_contact_cats_delete.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id', 'note_id' ),
			'properties' => array(
				'id'      => array( 'type' => 'integer' ),
				'note_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id  = (int) ( $input['id'] ?? 0 );
			$nid = (int) ( $input['note_id'] ?? 0 );
			return $proxy( 'DELETE', '/fluent-crm/v2/companies/' . $id . '/notes/' . $nid );
		},
	) );

	// 5.23.15 — import-companies-csv
	$reg->write( 'fluent-crm/import-companies-csv', array(
		'label'         => 'Import CRM Companies From CSV (Pro)',
		'description'   => 'Upload + map + import companies from CSV. Multi-step IO; HAND-CURATION-borderline. Source: CompanyController::csvImport (POST /companies/csv-import).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'file' ),
			'properties' => array(
				'file'    => array( 'type' => 'string' ),
				'mapping' => $obj,
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'imported_count' => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/companies/csv-import', $input );
		},
	) );

	// 5.23.16 — get-company-custom-fields (GET|PUT paired)
	$reg->write( 'fluent-crm/get-company-custom-fields', array(
		'label'         => 'Get Or Update CRM Company Custom Field Definitions (Pro)',
		'description'   => 'Paired getter/setter — without `fields` returns definitions; with `fields` writes definitions. Source: CompanyController::customFields (GET|PUT /companies/custom-fields).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => array(
				'fields' => array( 'type' => 'array', 'items' => $obj ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$method = isset( $input['fields'] ) ? 'PUT' : 'GET';
			return $proxy( $method, '/fluent-crm/v2/companies/custom-fields', $input );
		},
	) );

	// 5.23.17 — get-company-custom-tab-view
	$reg->read( 'fluent-crm/get-company-custom-tab-view', array(
		'label'         => 'Get CRM Company Custom Tab View (Pro)',
		'description'   => 'Operator-UI custom tab content for a company. Source: CompanyController::customTabView (GET /companies/{id}/custom_tab_view).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id', 'tab_id' ),
			'properties' => array(
				'id'     => array( 'type' => 'integer' ),
				'tab_id' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			return $proxy( 'GET', '/fluent-crm/v2/companies/' . $id . '/custom_tab_view', array( 'tab_id' => $input['tab_id'] ?? '' ) );
		},
	) );

}
