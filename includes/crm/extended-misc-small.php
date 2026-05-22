<?php
/**
 * FluentCRM small-cluster ability surface — Labels (§5.17), Webhooks (§5.18),
 * Users (§5.19), Forms (§5.21), Docs (§5.22), Global search (§5.31).
 *
 * 17 abilities total, all simple wrappers around FluentCRM REST routes via
 * internal rest_do_request. Capability mappings inherit from each cluster's
 * documented source-side Policy (research §5).
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', 'fluent_abilities_crm_register_extended_misc_small', 11 );

function fluent_abilities_crm_register_extended_misc_small() {

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

	// =========================================================================
	// §5.17 — Labels (4) — Capability: fcrm_manage_contact_cats (writes),
	//                                  fcrm_manage_contact_cats_delete (delete).
	// =========================================================================

	$label_item = array(
		'id'    => array( 'type' => 'integer' ),
		'title' => array( 'type' => 'string' ),
		'color' => array( 'type' => array( 'string', 'null' ) ),
	);

	$reg->read( 'fluent-crm/list-labels', array(
		'label'         => 'List CRM Labels',
		'description'   => 'List funnel/campaign labels. Source: LabelController::index (GET /labels). Response shape: labels may serialize as array (sequential) or object (name-keyed) depending on FCRM internal storage at query time.',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'labels' => array( 'type' => array( 'array', 'object' ) ),
			),
		),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/labels' );
		},
	) );

	$reg->write( 'fluent-crm/create-label', array(
		'label'         => 'Create CRM Label',
		'description'   => 'Create a new label. Source: GlobalLabelController::create (POST /labels). Capability: fcrm_manage_contact_cats. Vendor controller reads inputs via Arr::get($request->all(), \'label\') — payload nests under a `label` key (see source at vendor app/Http/Controllers/GlobalLabelController.php:32-50).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'title' ),
			'properties' => array(
				'title' => array( 'type' => 'string' ),
				'slug'  => array( 'type' => 'string', 'description' => 'Optional slug (vendor will auto-derive if omitted).' ),
				'color' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( $label_item ),
		'callback'      => function ( $input ) use ( $proxy ) {
			$label = array(
				'title' => isset( $input['title'] ) ? (string) $input['title'] : '',
				'slug'  => isset( $input['slug'] ) ? (string) $input['slug'] : ( isset( $input['title'] ) ? sanitize_title( (string) $input['title'] ) : '' ),
				'color' => isset( $input['color'] ) ? (string) $input['color'] : '',
			);
			return fluent_abilities_project_response( $proxy( 'POST', '/fluent-crm/v2/labels', array( 'label' => $label ) ) );
		},
	) );

	$reg->write( 'fluent-crm/update-label', array(
		'label'         => 'Update CRM Label',
		'description'   => 'Update an existing label. Source: GlobalLabelController::update (PUT /labels/{id}). Vendor controller reads inputs via Arr::get($request->all(), \'label\') — payload nests under a `label` key.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'    => array( 'type' => 'integer' ),
				'title' => array( 'type' => 'string' ),
				'slug'  => array( 'type' => 'string' ),
				'color' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id    = (int) ( $input['id'] ?? 0 );
			$label = array();
			foreach ( array( 'title', 'slug', 'color' ) as $k ) {
				if ( isset( $input[ $k ] ) ) {
					$label[ $k ] = (string) $input[ $k ];
				}
			}
			return fluent_abilities_project_response( $proxy( 'PUT', '/fluent-crm/v2/labels/' . $id, array( 'label' => $label ) ) );
		},
	) );

	$reg->delete( 'fluent-crm/delete-label', array(
		'label'         => 'Delete CRM Label',
		'description'   => 'Delete a label. Source: LabelController::destroy (DELETE /labels/{id}). Capability: fcrm_manage_contact_cats_delete.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array( 'id' => array( 'type' => 'integer' ) ),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'DELETE', '/fluent-crm/v2/labels/' . (int) ( $input['id'] ?? 0 ) );
		},
	) );

	// =========================================================================
	// §5.18 — Webhooks (4) — Capability: fcrm_manage_settings.
	// =========================================================================

	$webhook_item = array(
		'id'           => array( 'type' => 'integer' ),
		'name'         => array( 'type' => 'string' ),
		'provider'     => array( 'type' => array( 'string', 'null' ) ),
		'value'        => array( 'type' => array( 'string', 'null' ) ),
		'webhook_url'  => array( 'type' => array( 'string', 'null' ) ),
		'created_at'   => array( 'type' => array( 'string', 'null' ) ),
	);

	$reg->read( 'fluent-crm/list-webhooks', array(
		'label'         => 'List CRM Webhooks',
		'description'   => 'List FluentCRM webhook endpoints. Source: WebhookController::index (GET /webhooks). Capability: fcrm_manage_settings. Webhook entries carry provider-specific `extra` payload that may serialize as nested object.',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'webhooks' => array( 'type' => array( 'array', 'object' ) ),
			),
		),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/webhooks' );
		},
	) );

	$reg->write( 'fluent-crm/create-webhook', array(
		'label'         => 'Create CRM Webhook',
		'description'   => 'Create a new FluentCRM webhook endpoint. Returns the generated receiver URL. V7: callback whitelists top-level input to schema-declared keys, then calls the vendor public model FluentCrm\\App\\Models\\Webhook::store() (same write path WebhookController::create uses internally). The REST-controller path is intentionally bypassed because vendor Request::all() (vendor framework/src/WPFluent/Http/Request/Request.php inputs() at array_merge($this->request, $json)) re-reads php://input and merges the raw transport body OVER any WP_REST_Request::set_param values, defeating a whitelist applied at rest_do_request. Routing through the model preserves the documented vendor operation (V3 priority 2) while keeping the V7 whitelist binding. Source: WebhookController::create (POST /webhooks) + Webhook::store (app/Models/Webhook.php:67-76).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'name', 'status' ),
			'properties' => array(
				'name'      => array( 'type' => 'string' ),
				'status'    => array( 'type' => 'string', 'description' => 'Subscriber status to apply to incoming contacts (subscribed, pending, unsubscribed).' ),
				'lists'     => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'tags'      => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'companies' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'provider'  => array( 'type' => 'string', 'description' => 'default or sms' ),
				'extra'     => array( 'type' => 'object', 'additionalProperties' => true ),
			),
		),
		'output_schema' => array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'id'      => array( 'type' => 'integer' ),
				'webhook' => array( 'type' => array( 'object', 'null' ), 'additionalProperties' => true ),
				'message' => array( 'type' => 'string' ),
			),
		),
		'callback'      => function ( $input ) {
			// V7: whitelist top-level keys to those declared in input_schema, then
			// pass the cleaned payload directly to the vendor public model. The
			// transport envelope (method/params/jsonrpc/id/toolUseId/_links/_embedded)
			// is never reachable by vendor code because we do not route through the
			// REST controller (whose Request::all() re-reads php://input and would
			// override anything we'd set via WP_REST_Request::set_param).
			$allowed = array( 'name', 'status', 'lists', 'tags', 'companies', 'provider', 'extra' );
			$payload = array();
			foreach ( $allowed as $k ) {
				if ( array_key_exists( $k, $input ) ) {
					$payload[ $k ] = $input[ $k ];
				}
			}
			// V10 typed-error guard: if vendor module is absent at runtime, return
			// WP_Error rather than fataling on a missing class.
			if ( ! class_exists( '\\FluentCrm\\App\\Models\\Webhook' ) ) {
				return new WP_Error(
					'fluent_crm_unavailable',
					'FluentCrm\\App\\Models\\Webhook is not available. FluentCRM must be active for this ability.'
				);
			}
			// Mirror WebhookController::create validation: name + status required.
			foreach ( array( 'name', 'status' ) as $required ) {
				if ( empty( $payload[ $required ] ) ) {
					return new WP_Error(
						'fluent_crm_webhook_missing_field',
						sprintf( 'fluent-crm/create-webhook: required field `%s` is missing or empty.', $required )
					);
				}
			}
			$webhook = ( new \FluentCrm\App\Models\Webhook() )->store( $payload );
			return array(
				'id'      => isset( $webhook->id ) ? (int) $webhook->id : 0,
				'webhook' => isset( $webhook->value ) ? $webhook->value : null,
				'message' => __( 'Successfully created the WebHook', 'fluent-crm' ),
			);
		},
	) );

	$reg->write( 'fluent-crm/update-webhook', array(
		'label'         => 'Update CRM Webhook',
		'description'   => 'Update an existing webhook endpoint. V7: callback whitelists top-level input to schema-declared keys, then calls the vendor public model FluentCrm\\App\\Models\\Webhook::saveChanges() (same write path WebhookController::update uses internally). The REST-controller path is intentionally bypassed because vendor WebhookController::update calls $webhook->saveChanges($request->all()) and the vendor Request::all() (framework/src/WPFluent/Http/Request/Request.php — re-reads php://input) merges the raw transport body OVER any WP_REST_Request::set_param values, defeating a whitelist applied at rest_do_request; saveChanges() then array_merges that whole payload into the stored webhook `value` (app/Models/Webhook.php:88). Same leak + same fix shape as fluent-crm/create-webhook (Package 1, P-I family). Source: WebhookController::update (PUT /webhooks/{id}) + Webhook::saveChanges.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'        => array( 'type' => 'integer' ),
				'name'      => array( 'type' => 'string' ),
				'lists'     => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'tags'      => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'companies' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'extra'     => array( 'type' => 'object', 'additionalProperties' => true ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) {
			$id = (int) ( $input['id'] ?? 0 );
			if ( ! $id ) {
				return new WP_Error(
					'fluent_crm_webhook_missing_field',
					'fluent-crm/update-webhook: required field `id` is missing or empty.'
				);
			}
			// V7: whitelist top-level keys to those declared in input_schema,
			// then pass the cleaned payload directly to the vendor public model
			// (V3 priority 2). The transport envelope
			// (method/params/jsonrpc/id/toolUseId/_links/_embedded) is never
			// reachable by vendor code because we do not route through the REST
			// controller (whose Request::all() re-reads php://input and
			// saveChanges() then array_merges the whole thing into value).
			$allowed = array( 'name', 'lists', 'tags', 'companies', 'extra' );
			$payload = array();
			foreach ( $allowed as $k ) {
				if ( array_key_exists( $k, $input ) ) {
					$payload[ $k ] = $input[ $k ];
				}
			}
			// V10 typed-error guard: absent vendor class → WP_Error, not fatal.
			if ( ! class_exists( '\\FluentCrm\\App\\Models\\Webhook' ) ) {
				return new WP_Error(
					'fluent_crm_unavailable',
					'FluentCrm\\App\\Models\\Webhook is not available. FluentCRM must be active for this ability.'
				);
			}
			$webhook = ( new \FluentCrm\App\Models\Webhook() )->find( $id );
			if ( ! $webhook ) {
				return new WP_Error(
					'fluent_crm_webhook_not_found',
					sprintf( 'Webhook %d not found.', $id )
				);
			}
			// Mirror WebhookController::update — saveChanges() applies the
			// vendor's own tags/lists/companies defaults + value array_merge.
			$webhook->saveChanges( $payload );
			return array( 'success' => true );
		},
	) );

	$reg->delete( 'fluent-crm/delete-webhook', array(
		'label'         => 'Delete CRM Webhook',
		'description'   => 'Delete a webhook endpoint. Source: WebhookController::destroy (DELETE /webhooks/{id}).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array( 'id' => array( 'type' => 'integer' ) ),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'DELETE', '/fluent-crm/v2/webhooks/' . (int) ( $input['id'] ?? 0 ) );
		},
	) );

	// =========================================================================
	// §5.19 — Users (2)
	// =========================================================================

	$reg->read( 'fluent-crm/list-user-roles', array(
		'label'         => 'List WP User Roles (CRM Picker UI)',
		'description'   => 'List WP user roles for picker UIs. Source: UsersController::roles (GET /users/roles). Response shape: roles is a slug-keyed map matching WordPress\'s native get_editable_roles() shape.',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'roles' => array(
					'type'                 => array( 'array', 'object' ),
					'description'          => 'Role map keyed by role slug.',
				),
			),
		),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/users/roles' );
		},
	) );

	// =========================================================================
	// §5.21 — Forms (FluentForms integration, 3)
	// =========================================================================


	$reg->read( 'fluent-crm/list-form-entries', array(
		'label'         => 'List CRM Form Entries',
		'description'   => 'Paginated entries for a Fluent Forms form. Source: FormController::entries (GET /forms/{id}/entries).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array_merge(
				array( 'id' => array( 'type' => 'integer', 'description' => 'Form ID.' ) ),
				fluent_abilities_pagination_schema()
			),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'entries', array(
			'id'           => array( 'type' => 'integer' ),
			'form_id'      => array( 'type' => 'integer' ),
			'created_at'   => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			$q  = $input;
			unset( $q['id'] );
			return $proxy( 'GET', '/fluent-crm/v2/forms/' . $id . '/entries', $q );
		},
	) );

	$reg->read( 'fluent-crm/get-form-entry-detail', array(
		'label'         => 'Get CRM Form Entry Detail',
		'description'   => 'Get a single Fluent Forms entry detail. Source: FormController::entry (GET /forms/{form_id}/entries/{id}).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'form_id', 'id' ),
			'properties' => array(
				'form_id' => array( 'type' => 'integer' ),
				'id'      => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$form_id = (int) ( $input['form_id'] ?? 0 );
			$id      = (int) ( $input['id'] ?? 0 );
			return $proxy( 'GET', '/fluent-crm/v2/forms/' . $form_id . '/entries/' . $id );
		},
	) );

	// =========================================================================
	// §5.22 — Docs (in-app help, 3)
	// =========================================================================


	// =========================================================================
	// §5.31 — Global search (1; namespace-index is denylisted)
	// =========================================================================

}
