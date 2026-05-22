<?php
/**
 * FluentCRM Templates + Email Patterns + Editor Patterns — extended ability
 * surface (§5.6 + §5.7 + §5.8).
 *
 * Source-side policies:
 *  - TemplatePolicy: fcrm_manage_emails (writes/general); fcrm_read_emails (GET-only).
 *  - EmailPatternPolicy: parallel to TemplatePolicy.
 *  - EditorPatternPolicy: parallel.
 *  - Deletes inherit fcrm_manage_email_delete.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', 'fluent_abilities_crm_register_extended_templates_and_patterns', 11 );

function fluent_abilities_crm_register_extended_templates_and_patterns() {

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

	$template_item = array(
		'id'              => array( 'type' => 'integer' ),
		'title'           => array( 'type' => 'string' ),
		'email_subject'   => array( 'type' => array( 'string', 'null' ) ),
		'email_body'      => array( 'type' => array( 'string', 'null' ) ),
		'design_template' => array( 'type' => array( 'string', 'null' ) ),
		'created_at'      => array( 'type' => array( 'string', 'null' ) ),
		'updated_at'      => array( 'type' => array( 'string', 'null' ) ),
	);

	// =========================================================================
	// §5.6 — Templates + smart-codes + global style (8 + extras)
	// =========================================================================

	$reg->read( 'fluent-crm/get-template', array(
		'label'         => 'Get CRM Email Template',
		'description'   => 'Get a single email template with body + design + settings. Source: TemplateController::getTemplate (GET /templates/{id}). Capability: fcrm_read_emails.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Template ID.' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( $template_item ),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			// P4b — Addendum 15 (b) same-slug V3 route-correctness. Vendor
			// TemplateController::template() (route GET /templates/{id}; the
			// registrar "getTemplate" citation is V4 description drift)
			// coerces a missing/non-positive id to 0, Template::find(0) is
			// null, and the handler then SILENTLY returns a blank default
			// placeholder with HTTP success — no 404. Guard the id first.
			if ( $id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'id must be a positive template ID' );
			}
			$resp = $proxy( 'GET', '/fluent-crm/v2/templates/' . $id );
			if ( is_wp_error( $resp ) ) {
				return $resp;
			}
			// V3: the vendor route cannot signal not-found — on a miss it
			// fabricates an empty placeholder (post_title/post_content/
			// email_subject all ''), still HTTP success. A genuinely stored
			// template always carries a title (create-template requires
			// `title`). Treat the placeholder sentinel as a typed not-found
			// so callers can distinguish it from a real stored template,
			// instead of silently surfacing the vendor placeholder.
			$tpl = ( is_array( $resp ) && isset( $resp['template'] ) && is_array( $resp['template'] ) ) ? $resp['template'] : null;
			if ( null === $tpl ) {
				return fluent_abilities_error( 'not_found', 'Template ' . $id . ' was not found.' );
			}
			$title   = (string) ( $tpl['post_title'] ?? '' );
			$content = (string) ( $tpl['post_content'] ?? '' );
			$subject = (string) ( $tpl['email_subject'] ?? '' );
			if ( '' === $title && '' === $content && '' === $subject ) {
				return fluent_abilities_error( 'not_found', 'Template ' . $id . ' was not found (vendor returned the default placeholder, not a stored template).' );
			}
			// V5: vendor payload is already plain arrays/scalars (sendSuccess) — pass through.
			return $resp;
		},
	) );

	$reg->write( 'fluent-crm/update-template', array(
		'label'         => 'Update CRM Email Template',
		'description'   => 'Update an existing email template. Source: TemplateController::updateTemplate (PUT /templates/{id}). Capability: fcrm_manage_emails.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'              => array( 'type' => 'integer' ),
				'title'           => array( 'type' => 'string' ),
				'email_subject'   => array( 'type' => 'string' ),
				'email_body'      => array( 'type' => 'string' ),
				'design_template' => array( 'type' => 'string' ),
				'settings'        => $obj,
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			unset( $input['id'] );
			return $proxy( 'PUT', '/fluent-crm/v2/templates/' . $id, $input );
		},
	) );

	$reg->delete( 'fluent-crm/delete-template', array(
		'label'         => 'Delete CRM Email Template',
		'description'   => 'Delete an email template. Source: TemplateController::deleteTemplate (DELETE /templates/{id}). Capability: fcrm_manage_email_delete.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'DELETE', '/fluent-crm/v2/templates/' . (int) ( $input['id'] ?? 0 ) );
		},
	) );

	$reg->read( 'fluent-crm/list-templates-all', array(
		'label'         => 'List All CRM Email Templates (Flat)',
		'description'   => 'Flat list of all email templates plus the registered smart-code dictionary (paired response). Distinct from paginated list-templates. Source: TemplateController::getAllTemplates (GET /templates/all). Capability: fcrm_read_emails. Response carries both `templates` (array) and `smartcodes` (array of category groups) — operator-side editor uses both.',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'templates'  => array( 'type' => array( 'array', 'object' ) ),
				'smartcodes' => array( 'type' => array( 'array', 'object' ) ),
			),
		),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/templates/all' );
		},
	) );

	$reg->read( 'fluent-crm/list-smart-codes', array(
		'label'         => 'List CRM Smart Codes Dictionary',
		'description'   => 'Registered smart-code dictionary keyed by category. Load-bearing — no prior wrapper. Source: TemplateController::getSmartCodes (GET /templates/smartcodes).',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'smart_codes' => array( 'type' => 'array', 'items' => $obj ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/templates/smartcodes' );
		},
	) );

	// fluent-crm/set-global-email-style — REMOVED (v1.4.0 P7 close).
	// Never functional since v2.0.0: schema declared/forwarded `style`, but
	// vendor TemplateController::setGlobalStyle reads `config` — every
	// schema-valid call silently saved an empty style and returned success
	// (input discarded). No working contract to preserve; unregistered.
	// See docs/vendor-map/fluent-crm.json + docs/P7-CLOSE.md.

	$reg->read( 'fluent-crm/list-built-in-templates', array(
		'label'         => 'List CRM Built-In Email Templates',
		'description'   => 'Bundled built-in email templates. Source: TemplateController::getBuiltInTemplates (GET /templates/built-in-templates) — a distinct REST route from list-templates-all (GET /templates/all). Registered as a separate ability per Reviewer Track-2 Q1 ratification 2026-05-13 for explicit route coverage + operator discoverability. Cites research §5.6 prose: "Also covered (within this cluster): templates/built-in-templates GET ... surface as nested in list-templates-all". Wrapper-level cap: fcrm_read_emails (composes via FCRM TemplatePolicy GET).',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => fluent_abilities_schema_collection_output( 'templates', $template_item ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/templates/built-in-templates' );
		},
	) );

	// =========================================================================
	// §5.7 — Email patterns (6 primary + 2 categories = 8)
	// =========================================================================

	$pattern_item = array(
		'id'          => array( 'type' => 'integer' ),
		'title'       => array( 'type' => 'string' ),
		'content'     => array( 'type' => array( 'string', 'null' ) ),
		'category_id' => array( 'type' => array( 'integer', 'null' ) ),
		'created_at'  => array( 'type' => array( 'string', 'null' ) ),
	);


	$reg->write( 'fluent-crm/create-email-pattern', array(
		'label'         => 'Create CRM Email Pattern',
		'description'   => 'Create a new email pattern. Source: EmailPatternController::store (POST /email-patterns). Capability: fcrm_manage_emails.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'title' ),
			'properties' => array(
				'title'       => array( 'type' => 'string' ),
				'content'     => array( 'type' => 'string' ),
				'category_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( $pattern_item ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/email-patterns', $input );
		},
	) );

	$reg->read( 'fluent-crm/get-email-pattern', array(
		'label'         => 'Get CRM Email Pattern',
		'description'   => 'Get a single email pattern. Source: EmailPatternController::show (GET /email-patterns/{id}).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array( 'id' => array( 'type' => 'integer' ) ),
		),
		'output_schema' => fluent_abilities_schema_item_output( $pattern_item ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/email-patterns/' . (int) ( $input['id'] ?? 0 ) );
		},
	) );

	$reg->write( 'fluent-crm/update-email-pattern', array(
		'label'         => 'Update CRM Email Pattern',
		'description'   => 'Update an email pattern. Source: EmailPatternController::update (PUT /email-patterns/{id}).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'          => array( 'type' => 'integer' ),
				'title'       => array( 'type' => 'string' ),
				'content'     => array( 'type' => 'string' ),
				'category_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$id = (int) ( $input['id'] ?? 0 );
			unset( $input['id'] );
			return $proxy( 'PUT', '/fluent-crm/v2/email-patterns/' . $id, $input );
		},
	) );

	$reg->delete( 'fluent-crm/delete-email-pattern', array(
		'label'         => 'Delete CRM Email Pattern',
		'description'   => 'Delete an email pattern. Source: EmailPatternController::destroy (DELETE /email-patterns/{id}). Capability: fcrm_manage_email_delete.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array( 'id' => array( 'type' => 'integer' ) ),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'DELETE', '/fluent-crm/v2/email-patterns/' . (int) ( $input['id'] ?? 0 ) );
		},
	) );

	$reg->read( 'fluent-crm/list-email-pattern-categories', array(
		'label'         => 'List CRM Email-Pattern Categories',
		'description'   => 'List email-pattern categories. Source: EmailPatternController::categories (GET /email-patterns/categories).',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => fluent_abilities_schema_collection_output( 'categories', array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
			'count' => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/email-patterns/categories' );
		},
	) );

	$reg->delete( 'fluent-crm/delete-email-pattern-category', array(
		'label'         => 'Delete CRM Email-Pattern Category',
		'description'   => 'Delete an email-pattern category. Source: EmailPatternController::deleteCategory (DELETE /email-patterns/categories/{id}). Capability: fcrm_manage_email_delete.',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array( 'id' => array( 'type' => 'integer' ) ),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'DELETE', '/fluent-crm/v2/email-patterns/categories/' . (int) ( $input['id'] ?? 0 ) );
		},
	) );

	// =========================================================================
	// §5.8 — Editor patterns (4 + 1 helper = 5)
	// =========================================================================

	$reg->read( 'fluent-crm/list-editor-patterns', array(
		'label'         => 'List CRM Editor Patterns',
		'description'   => 'Block-editor patterns for campaign body (new since FCRM 3.0.0-beta.1). Source: EditorPatternController::index (GET /editor-patterns).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => array(
				'category' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'patterns', array(
			'id'       => array( 'type' => 'integer' ),
			'title'    => array( 'type' => 'string' ),
			'category' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/editor-patterns', $input );
		},
	) );

	$reg->write( 'fluent-crm/create-editor-pattern', array(
		'label'         => 'Create CRM Editor Pattern',
		'description'   => 'Create a new editor pattern. Source: EditorPatternController::store (POST /editor-patterns).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'title', 'pattern' ),
			'properties' => array(
				'title'    => array( 'type' => 'string' ),
				'pattern'  => array( 'type' => 'string' ),
				'category' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'POST', '/fluent-crm/v2/editor-patterns', $input );
		},
	) );

	$reg->write( 'fluent-crm/manage-editor-pattern', array(
		'label'         => 'Manage Single CRM Editor Pattern',
		'description'   => 'Multi-verb manage endpoint for one editor pattern: pass `_method` (PUT/PATCH/DELETE) to control operation. Source: EditorPatternController::manage (GET|POST|PUT|PATCH|DELETE /editor-patterns/{id}).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id', '_method' ),
			'properties' => array(
				'id'       => array( 'type' => 'integer' ),
				'_method'  => array(
					'type'        => 'string',
					'description' => 'Operation: get, update, patch, delete.',
				),
				'title'    => array( 'type' => 'string' ),
				'pattern'  => array( 'type' => 'string' ),
				'category' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback'      => function ( $input ) use ( $proxy ) {
			$method_map = array(
				'get'    => 'GET',
				'update' => 'PUT',
				'patch'  => 'PATCH',
				'delete' => 'DELETE',
			);
			$id  = (int) ( $input['id'] ?? 0 );
			$op  = strtolower( (string) ( $input['_method'] ?? 'get' ) );
			$mtd = $method_map[ $op ] ?? 'GET';
			unset( $input['id'], $input['_method'] );
			return $proxy( $mtd, '/fluent-crm/v2/editor-patterns/' . $id, $input );
		},
	) );

	$reg->read( 'fluent-crm/list-editor-pattern-categories', array(
		'label'         => 'List CRM Editor-Pattern Categories',
		'description'   => 'List editor-pattern categories. Source: EditorPatternController::categories (GET /editor-pattern-categories).',
		'category'      => 'fluent-crm',
		'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
		'output_schema' => fluent_abilities_schema_collection_output( 'categories', array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/editor-pattern-categories' );
		},
	) );

	$reg->read( 'fluent-crm/list-editor-cart-products', array(
		'label'         => 'List CRM Editor Cart Products',
		'description'   => 'Operator-side cart-product picker for editor patterns. Source: EditorController::cartProducts (GET /editor/cart-products).',
		'category'      => 'fluent-crm',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => array(
				'provider' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'products', array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
			'price' => array( 'type' => array( 'number', 'string', 'null' ) ),
		) ),
		'callback'      => function ( $input ) use ( $proxy ) {
			return $proxy( 'GET', '/fluent-crm/v2/editor/cart-products', $input );
		},
	) );

}
