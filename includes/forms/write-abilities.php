<?php
/**
 * Fluent Forms — Free-tier write abilities (Phase B, v2.0.0).
 *
 * 47 abilities covering form CRUD, lifecycle extras, transfer, submission
 * mutations, notes, logs, notifications, confirmations, settings, per-form
 * integrations, integration merge-fields/list-ids, global integration registry,
 * global settings option-key bridge, managers + roles, analytics + form views,
 * and global search.
 *
 * Maps to the ABILITY REGISTRAR RESEARCH — Fluent Forms 2026-05-13 v1.0
 * §4.1-§4.9 + §4.11-§4.16 + §4.23 proposed inventory.
 *
 * Native vendor APIs (FluentForm\App\Models, FluentForm\App\Services) are used
 * before raw $wpdb writes wherever the vendor service exposes the operation, so
 * hooks, caches, and cascade-deletes invoked by the vendor stay intact
 * (Principle 2 — Native APIs Over Raw Storage).
 *
 * @package Fluent_Abilities
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'forms' );

	// =========================================================================
	// 4.1 FORM DEFINITIONS CRUD
	// =========================================================================

	$reg->write( 'fluent-forms/create-form', array(
		'label'       => 'Create Form',
		'description' => 'Create a new Fluent Form. Title is required; status defaults to "published"; type defaults to "form". form_fields may be supplied as the full builder JSON object (optional). When template_id is supplied, the form is seeded from a predefined template.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'title' ),
			'properties' => array(
				'title'               => array( 'type' => 'string', 'description' => 'Form title' ),
				'status'              => array( 'type' => 'string', 'enum' => array( 'published', 'unpublished', 'Draft' ), 'description' => 'Form status (default: published)' ),
				'type'                => array( 'type' => 'string', 'description' => 'Form type (default: form)' ),
				'form_fields'         => array( 'type' => 'object', 'description' => 'Builder JSON for the form fields (optional)' ),
				'appearance_settings' => array( 'type' => 'object', 'description' => 'Appearance settings JSON (optional)' ),
				'template_id'         => array( 'type' => 'integer', 'description' => 'Predefined template id to seed from (optional)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'form_id'      => array( 'type' => 'integer' ),
			'title'        => array( 'type' => 'string' ),
			'status'       => array( 'type' => 'string' ),
			'redirect_url' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'write' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to create forms' );
			}

			$title = isset( $input['title'] ) ? sanitize_text_field( (string) $input['title'] ) : '';
			if ( '' === $title ) {
				return fluent_abilities_error( 'ability_invalid_input', 'title is required' );
			}

			if ( ! class_exists( '\\FluentForm\\App\\Models\\Form' ) ) {
				return fluent_abilities_error( 'plugin_missing', 'Fluent Forms is not active.' );
			}

			// Vendor-canonical attribute set (keys consumed by
			// FluentForm\App\Models\Form::prepare()).
			$attributes = array(
				'title' => $title,
			);
			if ( isset( $input['status'] ) ) {
				$attributes['status'] = sanitize_text_field( (string) $input['status'] );
			}
			if ( isset( $input['type'] ) ) {
				$attributes['type'] = sanitize_text_field( (string) $input['type'] );
			}
			if ( isset( $input['form_fields'] ) && is_array( $input['form_fields'] ) ) {
				$attributes['form_fields'] = wp_json_encode( $input['form_fields'] );
			}
			if ( isset( $input['appearance_settings'] ) && is_array( $input['appearance_settings'] ) ) {
				$attributes['appearance_settings'] = wp_json_encode( $input['appearance_settings'] );
			}

			$is_template = isset( $input['template_id'] );
			if ( $is_template ) {
				$attributes['template_id'] = (int) $input['template_id'];
			}

			try {
				if ( $is_template ) {
					// Template/predefined path: FormService::store() is
					// vendor-native here — its ' (#id)' rename and default
					// form-meta seeding are part of the documented
					// template-seed flow (P3c: kept intentionally).
					if ( ! class_exists( '\\FluentForm\\App\\Services\\Form\\FormService' )
						|| ! method_exists( '\\FluentForm\\App\\Services\\Form\\FormService', 'store' ) ) {
						return fluent_abilities_error( 'vendor_precondition_failed', 'Fluent Forms FormService::store() is unavailable; cannot seed a form from a template.' );
					}
					$service = new \FluentForm\App\Services\Form\FormService();
					$created = $service->store( $attributes );
					$form_id = ( is_object( $created ) && isset( $created->id ) )
						? (int) $created->id
						: (int) ( is_array( $created ) ? ( $created['form_id'] ?? $created['id'] ?? 0 ) : 0 );
				} else {
					// Bare create (Variant A, P3c — F-FORMS-01): route through
					// the vendor Form model the same way FormService::store()
					// does internally (Form::prepare() -> model create + save)
					// but WITHOUT the admin-only ' (#id)' rename, so the
					// declared title/status/form_fields persist verbatim.
					if ( ! method_exists( '\\FluentForm\\App\\Models\\Form', 'prepare' ) ) {
						return fluent_abilities_error( 'vendor_precondition_failed', 'Fluent Forms Form::prepare() is unavailable on this build; cannot create a form via the vendor model path.' );
					}
					// The WPFluent ORM resolves create()/find() through
					// __call/__callStatic, so method_exists() is intentionally
					// NOT used to gate them (it would false-negative). A vendor
					// failure surfaces through the surrounding try/catch as a
					// typed ability_execution_failed error (V10).
					$model = new \FluentForm\App\Models\Form();
					$data  = \FluentForm\App\Models\Form::prepare( $attributes );
					$form  = $model->create( $data );
					if ( ! is_object( $form ) || ! isset( $form->id ) ) {
						return fluent_abilities_error( 'ability_execution_failed', 'Fluent Forms did not return a persisted form id.' );
					}
					$form->save();
					$form_id = (int) $form->id;
				}
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'ability_execution_failed', $e->getMessage() );
			}

			if ( $form_id < 1 ) {
				return fluent_abilities_error( 'ability_execution_failed', 'Fluent Forms did not return a persisted form id.' );
			}

			// V3 read-back: the return reflects the PERSISTED vendor object,
			// never an input echo. Re-fetch via the vendor public model.
			$persisted = \FluentForm\App\Models\Form::find( $form_id );
			if ( ! is_object( $persisted ) || ! isset( $persisted->id ) ) {
				return fluent_abilities_error( 'vendor_precondition_failed', 'Form was created but could not be read back for verification.' );
			}

			return array(
				'form_id'      => (int) $persisted->id,
				'title'        => (string) $persisted->title,
				'status'       => (string) $persisted->status,
				'redirect_url' => null,
			);
		},
	) );

	$reg->write( 'fluent-forms/update-form', array(
		'label'       => 'Update Form',
		'description' => 'Update an existing Fluent Form. Any subset of title, status, type, form_fields, appearance_settings, conditions may be supplied.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id' ),
			'properties' => array(
				'form_id'             => array( 'type' => 'integer', 'description' => 'Form ID to update' ),
				'title'               => array( 'type' => 'string' ),
				'status'              => array( 'type' => 'string', 'enum' => array( 'published', 'unpublished', 'Draft' ) ),
				'type'                => array( 'type' => 'string' ),
				'form_fields'         => array( 'type' => 'object' ),
				'appearance_settings' => array( 'type' => 'object' ),
				'conditions'          => array( 'type' => array( 'object', 'array' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'form_id' => array( 'type' => 'integer' ),
			'message' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'write' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to update forms' );
			}

			$form_id = (int) ( $input['form_id'] ?? 0 );
			if ( $form_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id is required' );
			}

			if ( ! class_exists( '\\FluentForm\\App\\Models\\Form' ) ) {
				return fluent_abilities_error( 'plugin_missing', 'Fluent Forms is not active.' );
			}

			$form = \FluentForm\App\Models\Form::find( $form_id );
			if ( ! $form ) {
				return fluent_abilities_error( 'not_found', 'Form not found' );
			}

			if ( isset( $input['title'] ) ) {
				$form->title = sanitize_text_field( (string) $input['title'] );
			}
			if ( isset( $input['status'] ) ) {
				$form->status = sanitize_text_field( (string) $input['status'] );
			}
			if ( isset( $input['type'] ) ) {
				$form->type = sanitize_text_field( (string) $input['type'] );
			}
			if ( isset( $input['form_fields'] ) && is_array( $input['form_fields'] ) ) {
				$form->form_fields = wp_json_encode( $input['form_fields'] );
			}
			if ( isset( $input['appearance_settings'] ) && is_array( $input['appearance_settings'] ) ) {
				$form->appearance_settings = wp_json_encode( $input['appearance_settings'] );
			}
			if ( isset( $input['conditions'] ) ) {
				$form->conditions = wp_json_encode( $input['conditions'] );
			}

			try {
				$form->save();
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'ability_execution_failed', $e->getMessage() );
			}

			return array(
				'form_id' => $form_id,
				'message' => 'updated',
			);
		},
	) );

	$reg->delete( 'fluent-forms/delete-form', array(
		'label'       => 'Delete Form',
		'description' => 'Delete a Fluent Form. Cascade-deletes submissions, submission meta, entry details, form meta, form analytics, logs, and (when payment helper is enabled) related transactions, subscriptions, and order items.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id' ),
			'properties' => array(
				'form_id' => array( 'type' => 'integer', 'description' => 'Form ID to delete' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'form_id'         => array( 'type' => 'integer' ),
			'message'         => array( 'type' => 'string' ),
			'deleted_counts'  => array( 'type' => 'object' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'delete' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to delete forms' );
			}

			$form_id = (int) ( $input['form_id'] ?? 0 );
			if ( $form_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id is required' );
			}

			if ( ! class_exists( '\\FluentForm\\App\\Models\\Form' ) ) {
				return fluent_abilities_error( 'plugin_missing', 'Fluent Forms is not active.' );
			}

			global $wpdb;
			$counts = array(
				'submissions'    => (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}fluentform_submissions WHERE form_id = %d",
					$form_id
				) ),
				'entry_details'  => (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}fluentform_entry_details WHERE form_id = %d",
					$form_id
				) ),
				'submission_meta' => (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}fluentform_submission_meta WHERE form_id = %d",
					$form_id
				) ),
				'form_meta'      => (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}fluentform_form_meta WHERE form_id = %d",
					$form_id
				) ),
				'analytics'      => (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}fluentform_form_analytics WHERE form_id = %d",
					$form_id
				) ),
				'logs'           => (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}fluentform_logs WHERE source_id = %d AND source_type = 'form_item'",
					$form_id
				) ),
			);

			$form = \FluentForm\App\Models\Form::find( $form_id );
			if ( ! $form ) {
				return fluent_abilities_error( 'not_found', 'Form not found' );
			}

			try {
				// Form::remove() is a STATIC vendor method that takes the form id; it
				// cascade-deletes submissions, meta, entry details, analytics, logs,
				// and (when payment helper is enabled) transactions/subscriptions/order
				// items. See FluentForm\App\Models\Form::remove() vendor source.
				\FluentForm\App\Models\Form::remove( $form_id );
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'ability_execution_failed', $e->getMessage() );
			}

			return array(
				'form_id'        => $form_id,
				'message'        => 'deleted',
				'deleted_counts' => $counts,
			);
		},
	) );

	$reg->write( 'fluent-forms/duplicate-form', array(
		'label'       => 'Duplicate Form',
		'description' => 'Duplicate an existing Fluent Form (deep-copies form_fields and configured meta). Optional title_suffix is appended to the source title (default " (Copy)").',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id' ),
			'properties' => array(
				'form_id'      => array( 'type' => 'integer', 'description' => 'Source form ID' ),
				'title_suffix' => array( 'type' => 'string', 'description' => 'Suffix to append to the duplicated title (default " (Copy)")' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'form_id'  => array( 'type' => 'integer' ),
			'title'    => array( 'type' => 'string' ),
			'redirect' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'write' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to duplicate forms' );
			}

			$form_id = (int) ( $input['form_id'] ?? 0 );
			if ( $form_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id is required' );
			}

			if ( ! class_exists( '\\FluentForm\\App\\Services\\Form\\FormService' ) ) {
				return fluent_abilities_error( 'plugin_missing', 'Fluent Forms FormService is not available.' );
			}

			$suffix = isset( $input['title_suffix'] ) ? sanitize_text_field( (string) $input['title_suffix'] ) : ' (Copy)';

			try {
				$service  = new \FluentForm\App\Services\Form\FormService();
				$response = $service->duplicate( array( 'form_id' => $form_id, 'title_suffix' => $suffix ) );
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'ability_execution_failed', $e->getMessage() );
			}

			return array(
				'form_id'  => (int) ( $response['form_id'] ?? $response['id'] ?? 0 ),
				'title'    => (string) ( $response['title'] ?? '' ),
				'redirect' => $response['redirect'] ?? null,
			);
		},
	) );

	// =========================================================================
	// 4.2 FORM LIFECYCLE EXTRAS
	// =========================================================================

	$reg->write( 'fluent-forms/convert-form', array(
		'label'       => 'Convert Form To Conversational',
		'description' => 'Convert a regular Fluent Form into a conversational variant. Sets is_conversion_form on form_meta and reshapes form_fields per the vendor converter.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id' ),
			'properties' => array(
				'form_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'form_id' => array( 'type' => 'integer' ),
			'message' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'write' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to convert forms' );
			}

			$form_id = (int) ( $input['form_id'] ?? 0 );
			if ( $form_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id is required' );
			}

			if ( ! class_exists( '\\FluentForm\\App\\Services\\Form\\FormService' ) ) {
				return fluent_abilities_error( 'plugin_missing', 'Fluent Forms FormService is not available.' );
			}

			try {
				$service = new \FluentForm\App\Services\Form\FormService();
				$service->convert( $form_id );
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'ability_execution_failed', $e->getMessage() );
			}

			return array(
				'form_id' => $form_id,
				'message' => 'converted',
			);
		},
	) );

	$reg->read( 'fluent-forms/list-form-templates', array(
		'label'       => 'List Form Templates',
		'description' => 'List predefined form templates available via Form::getPredefinedForms() that may be used as a seed for create-form (template_id input).',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => new stdClass(),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'templates', array(
			'id'              => array( 'type' => array( 'string', 'integer' ) ),
			'name'            => array( 'type' => 'string' ),
			'category'        => array( 'type' => array( 'string', 'null' ) ),
			'description'     => array( 'type' => array( 'string', 'null' ) ),
			'fields_preview'  => array( 'type' => array( 'array', 'object', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read form templates' );
			}

			$templates = array();
			if ( class_exists( '\\FluentForm\\App\\Services\\Form\\FormService' ) ) {
				try {
					$service  = new \FluentForm\App\Services\Form\FormService();
					$response = $service->templates();
					if ( is_array( $response ) ) {
						$templates = $response;
					} elseif ( is_array( $response['templates'] ?? null ) ) {
						$templates = $response['templates'];
					}
				} catch ( \Throwable $e ) {
					return fluent_abilities_error( 'ability_execution_failed', $e->getMessage() );
				}
			}

			$items = array();
			foreach ( $templates as $key => $tpl ) {
				$tpl = (array) $tpl;
				$items[] = array(
					'id'             => $tpl['id'] ?? $key,
					'name'           => (string) ( $tpl['name'] ?? $tpl['title'] ?? '' ),
					'category'       => $tpl['category'] ?? null,
					'description'    => $tpl['description'] ?? null,
					'fields_preview' => $tpl['fields'] ?? $tpl['preview'] ?? null,
				);
			}

			return array( 'templates' => $items, 'total' => count( $items ) );
		},
	) );

	$reg->read( 'fluent-forms/get-form-shortcodes', array(
		'label'       => 'Get Form Shortcodes',
		'description' => 'Return the shortcodes that embed a given Fluent Form (default plus conversational variants when available).',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id' ),
			'properties' => array(
				'form_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'form_id'    => array( 'type' => 'integer' ),
			'shortcodes' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read form shortcodes' );
			}

			$form_id = (int) ( $input['form_id'] ?? 0 );
			if ( $form_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id is required' );
			}

			$shortcodes = array(
				array(
					'type'        => 'default',
					'code'        => sprintf( '[fluentform id="%d"]', $form_id ),
					'description' => 'Embed the form on a page or post.',
				),
				array(
					'type'        => 'conversational',
					'code'        => sprintf( '[fluentform type="conversational" id="%d"]', $form_id ),
					'description' => 'Conversational variant (requires the form to be converted).',
				),
			);

			if ( class_exists( '\\FluentForm\\App\\Services\\Form\\FormService' ) ) {
				try {
					$service  = new \FluentForm\App\Services\Form\FormService();
					if ( method_exists( $service, 'shortcodes' ) ) {
						$response = $service->shortcodes( $form_id );
						if ( is_array( $response ) ) {
							$shortcodes = $response;
						}
					}
				} catch ( \Throwable $e ) {
					// Fall back to defaults silently.
				}
			}

			return array(
				'form_id'    => $form_id,
				'shortcodes' => $shortcodes,
			);
		},
	) );

	// =========================================================================
	// 4.3 FORM TRANSFER (EXPORT / IMPORT)
	// =========================================================================

	$reg->read( 'fluent-forms/export-form', array(
		'label'       => 'Export Form Definition',
		'description' => 'Export a Fluent Form definition (form_fields + configured meta + notifications + confirmations + integrations metadata) as a JSON object suitable for import-form. Submissions are not exported.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id' ),
			'properties' => array(
				'form_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'form_id' => array( 'type' => 'integer' ),
			'export'  => array( 'type' => 'object' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to export forms' );
			}

			$form_id = (int) ( $input['form_id'] ?? 0 );
			if ( $form_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id is required' );
			}

			global $wpdb;
			$form = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}fluentform_forms WHERE id = %d",
				$form_id
			) );
			if ( ! $form ) {
				return fluent_abilities_error( 'not_found', 'Form not found' );
			}

			$meta_rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT meta_key, value FROM {$wpdb->prefix}fluentform_form_meta WHERE form_id = %d",
				$form_id
			) );
			$meta = array();
			foreach ( $meta_rows as $row ) {
				$decoded = json_decode( $row->value, true );
				$meta[ $row->meta_key ] = ( null !== $decoded ) ? $decoded : $row->value;
			}

			$export = array(
				'title'               => $form->title,
				'status'              => $form->status,
				'type'                => $form->type,
				'form_fields'         => json_decode( $form->form_fields, true ),
				'appearance_settings' => $form->appearance_settings ? json_decode( $form->appearance_settings, true ) : null,
				'conditions'          => json_decode( $form->conditions ?? '[]', true ),
				'has_payment'         => (bool) ( $form->has_payment ?? false ),
				'meta'                => $meta,
			);

			return array(
				'form_id' => $form_id,
				'export'  => $export,
			);
		},
	) );

	$reg->write( 'fluent-forms/import-form', array(
		'label'       => 'Import Form Definition',
		'description' => 'Import a Fluent Form definition produced by export-form (or compatible third-party JSON). Creates a new form and persists declared meta entries.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'data' ),
			'properties' => array(
				'data' => array( 'type' => 'object', 'description' => 'Form definition object (matches export-form output shape)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'form_id' => array( 'type' => 'integer' ),
			'title'   => array( 'type' => 'string' ),
			'message' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'write' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to import forms' );
			}

			$data = isset( $input['data'] ) && is_array( $input['data'] ) ? $input['data'] : array();
			if ( empty( $data['title'] ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'data.title is required' );
			}

			if ( ! class_exists( '\\FluentForm\\App\\Models\\Form' ) || ! class_exists( '\\FluentForm\\App\\Models\\FormMeta' ) ) {
				return fluent_abilities_error( 'plugin_missing', 'Fluent Forms is not active.' );
			}

			try {
				$form = new \FluentForm\App\Models\Form();
				$form->title  = sanitize_text_field( (string) $data['title'] );
				$form->status = sanitize_text_field( (string) ( $data['status'] ?? 'published' ) );
				$form->type   = sanitize_text_field( (string) ( $data['type'] ?? 'form' ) );
				if ( isset( $data['form_fields'] ) ) {
					$form->form_fields = is_array( $data['form_fields'] ) ? wp_json_encode( $data['form_fields'] ) : (string) $data['form_fields'];
				}
				if ( isset( $data['appearance_settings'] ) && is_array( $data['appearance_settings'] ) ) {
					$form->appearance_settings = wp_json_encode( $data['appearance_settings'] );
				}
				if ( isset( $data['conditions'] ) ) {
					$form->conditions = wp_json_encode( $data['conditions'] );
				}
				$form->has_payment = ! empty( $data['has_payment'] ) ? 1 : 0;
				$form->created_by  = get_current_user_id();
				$form->save();

				if ( isset( $data['meta'] ) && is_array( $data['meta'] ) ) {
					foreach ( $data['meta'] as $meta_key => $value ) {
						$encoded = is_array( $value ) ? wp_json_encode( $value ) : (string) $value;
						// Vendor signature: FormMeta::persist($formId, $metaKey, $metaValue).
						\FluentForm\App\Models\FormMeta::persist( $form->id, $meta_key, $encoded );
					}
				}
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'ability_execution_failed', $e->getMessage() );
			}

			return array(
				'form_id' => (int) $form->id,
				'title'   => $form->title,
				'message' => 'imported',
			);
		},
	) );

	// =========================================================================
	// 4.4 SUBMISSION LIFECYCLE MUTATIONS
	// =========================================================================

	$reg->write( 'fluent-forms/update-submission-status', array(
		'label'       => 'Update Submission Status',
		'description' => 'Update a Fluent Forms submission status. Allowed values: read, unread, trashed.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'submission_id', 'status' ),
			'properties' => array(
				'submission_id' => array( 'type' => 'integer' ),
				'status'        => array( 'type' => 'string', 'enum' => array( 'read', 'unread', 'trashed' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'submission_id' => array( 'type' => 'integer' ),
			'status'        => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'write' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to update submissions' );
			}

			$submission_id = (int) ( $input['submission_id'] ?? 0 );
			$status        = isset( $input['status'] ) ? sanitize_text_field( (string) $input['status'] ) : '';
			if ( $submission_id < 1 || '' === $status ) {
				return fluent_abilities_error( 'ability_invalid_input', 'submission_id and status are required' );
			}
			if ( ! in_array( $status, array( 'read', 'unread', 'trashed' ), true ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'status must be one of read, unread, trashed' );
			}

			if ( ! class_exists( '\\FluentForm\\App\\Models\\Submission' ) ) {
				return fluent_abilities_error( 'plugin_missing', 'Fluent Forms is not active.' );
			}

			$submission = \FluentForm\App\Models\Submission::find( $submission_id );
			if ( ! $submission ) {
				return fluent_abilities_error( 'not_found', 'Submission not found' );
			}

			$submission->status = $status;
			try {
				$submission->save();
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'ability_execution_failed', $e->getMessage() );
			}

			return array(
				'submission_id' => $submission_id,
				'status'        => $status,
			);
		},
	) );

	$reg->write( 'fluent-forms/toggle-submission-favorite', array(
		'label'       => 'Toggle Submission Favorite',
		'description' => 'Toggle the is_favourite flag on a Fluent Forms submission.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'submission_id' ),
			'properties' => array(
				'submission_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'submission_id' => array( 'type' => 'integer' ),
			'is_favourite'  => array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'write' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to update submissions' );
			}

			$submission_id = (int) ( $input['submission_id'] ?? 0 );
			if ( $submission_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'submission_id is required' );
			}

			if ( ! class_exists( '\\FluentForm\\App\\Models\\Submission' ) ) {
				return fluent_abilities_error( 'plugin_missing', 'Fluent Forms is not active.' );
			}

			$submission = \FluentForm\App\Models\Submission::find( $submission_id );
			if ( ! $submission ) {
				return fluent_abilities_error( 'not_found', 'Submission not found' );
			}

			$submission->is_favourite = $submission->is_favourite ? 0 : 1;
			try {
				$submission->save();
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'ability_execution_failed', $e->getMessage() );
			}

			return array(
				'submission_id' => $submission_id,
				'is_favourite'  => (bool) $submission->is_favourite,
			);
		},
	) );

	$reg->delete( 'fluent-forms/delete-submission', array(
		'label'       => 'Delete Submission',
		'description' => 'Permanently delete a Fluent Forms submission. Cascade-deletes submission meta, entry details, logs, and (for paid submissions) transactions, subscriptions, order items, and scheduled actions.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'submission_id' ),
			'properties' => array(
				'submission_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'submission_id' => array( 'type' => 'integer' ),
			'message'       => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'delete' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to delete submissions' );
			}

			$submission_id = (int) ( $input['submission_id'] ?? 0 );
			if ( $submission_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'submission_id is required' );
			}

			if ( ! class_exists( '\\FluentForm\\App\\Models\\Submission' ) ) {
				return fluent_abilities_error( 'plugin_missing', 'Fluent Forms is not active.' );
			}

			$submission = \FluentForm\App\Models\Submission::find( $submission_id );
			if ( ! $submission ) {
				return fluent_abilities_error( 'not_found', 'Submission not found' );
			}

			try {
				// Submission::remove() is a STATIC vendor method that takes an array of
				// submission ids; it cascade-deletes submission meta, entry details,
				// submission-scoped logs, plus payment domain rows + scheduled actions.
				// See FluentForm\App\Models\Submission::remove() vendor source.
				\FluentForm\App\Models\Submission::remove( array( $submission_id ) );
				do_action( 'fluentform/submission_deleted', $submission_id );
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'ability_execution_failed', $e->getMessage() );
			}

			return array(
				'submission_id' => $submission_id,
				'message'       => 'deleted',
			);
		},
	) );

	$reg->write( 'fluent-forms/bulk-update-submissions', array(
		'label'       => 'Bulk Update Submissions',
		'description' => 'Apply a bulk action across multiple Fluent Forms submissions. Supported actions: status:read, status:unread, status:trashed, restore, delete-permanently, favorite, unfavorite.',
		'annotations' => array( 'destructive' => true ),
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'submission_ids', 'action' ),
			'properties' => array(
				'submission_ids' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
				'action' => array(
					'type' => 'string',
					'enum' => array( 'status:read', 'status:unread', 'status:trashed', 'restore', 'delete-permanently', 'favorite', 'unfavorite' ),
				),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'count_affected' => array( 'type' => 'integer' ),
			'message'        => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'write' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to update submissions' );
			}

			$ids    = array_filter( array_map( 'intval', (array) ( $input['submission_ids'] ?? array() ) ) );
			$action = isset( $input['action'] ) ? sanitize_text_field( (string) $input['action'] ) : '';
			if ( empty( $ids ) || '' === $action ) {
				return fluent_abilities_error( 'ability_invalid_input', 'submission_ids and action are required' );
			}

			if ( ! class_exists( '\\FluentForm\\App\\Models\\Submission' ) ) {
				return fluent_abilities_error( 'plugin_missing', 'Fluent Forms is not active.' );
			}

			$affected = 0;
			try {
				if ( 'delete-permanently' === $action ) {
					// Batch the static cascade-delete call once for the whole set.
					\FluentForm\App\Models\Submission::remove( array_values( $ids ) );
					foreach ( $ids as $id ) {
						do_action( 'fluentform/submission_deleted', $id );
					}
					$affected = count( $ids );
				} else {
					$submissions = \FluentForm\App\Models\Submission::whereIn( 'id', $ids )->get();
					foreach ( $submissions as $submission ) {
						switch ( $action ) {
							case 'status:read':
							case 'status:unread':
							case 'status:trashed':
								$submission->status = explode( ':', $action )[1];
								$submission->save();
								$affected++;
								break;
							case 'restore':
								$submission->status = 'unread';
								$submission->save();
								$affected++;
								break;
							case 'favorite':
								$submission->is_favourite = 1;
								$submission->save();
								$affected++;
								break;
							case 'unfavorite':
								$submission->is_favourite = 0;
								$submission->save();
								$affected++;
								break;
						}
					}
				}
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'ability_execution_failed', $e->getMessage() );
			}

			return array(
				'count_affected' => $affected,
				'message'        => sprintf( '%s applied to %d submissions', $action, $affected ),
			);
		},
	) );

	$reg->write( 'fluent-forms/update-submission-user', array(
		'label'       => 'Reassign Submission Owner',
		'description' => 'Set the WordPress user owner of a Fluent Forms submission. user_id of 0 clears ownership.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'submission_id', 'user_id' ),
			'properties' => array(
				'submission_id' => array( 'type' => 'integer' ),
				'user_id'       => array( 'type' => 'integer', 'description' => 'WordPress user ID (0 clears ownership)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'submission_id' => array( 'type' => 'integer' ),
			'user_id'       => array( 'type' => array( 'integer', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'write' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to reassign submissions' );
			}

			$submission_id = (int) ( $input['submission_id'] ?? 0 );
			$user_id       = (int) ( $input['user_id'] ?? 0 );
			if ( $submission_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'submission_id is required' );
			}

			if ( ! class_exists( '\\FluentForm\\App\\Models\\Submission' ) ) {
				return fluent_abilities_error( 'plugin_missing', 'Fluent Forms is not active.' );
			}

			$submission = \FluentForm\App\Models\Submission::find( $submission_id );
			if ( ! $submission ) {
				return fluent_abilities_error( 'not_found', 'Submission not found' );
			}

			$submission->user_id = $user_id > 0 ? $user_id : null;
			try {
				$submission->save();
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'ability_execution_failed', $e->getMessage() );
			}

			return array(
				'submission_id' => $submission_id,
				'user_id'       => $user_id > 0 ? $user_id : null,
			);
		},
	) );

	$reg->read( 'fluent-forms/list-all-submissions', array(
		'label'       => 'List All Submissions (cross-form)',
		'description' => 'Paginated list of submissions across forms. Supports form_ids, status, payment_statuses, search, and date_range filters.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'form_ids'         => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'status'           => array( 'type' => 'string' ),
				'payment_statuses' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'search'           => array( 'type' => 'string' ),
				'date_from'        => array( 'type' => 'string' ),
				'date_to'          => array( 'type' => 'string' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'submissions', array(
			'id'             => array( 'type' => 'integer' ),
			'form_id'        => array( 'type' => 'integer' ),
			'form_title'     => array( 'type' => 'string' ),
			'serial_number'  => array( 'type' => 'integer' ),
			'status'         => array( 'type' => 'string' ),
			'payment_status' => array( 'type' => array( 'string', 'null' ) ),
			'user_id'        => array( 'type' => array( 'integer', 'null' ) ),
			'created_at'     => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read submissions' );
			}

			global $wpdb;
			$submissions_table = $wpdb->prefix . 'fluentform_submissions';
			$forms_table       = $wpdb->prefix . 'fluentform_forms';

			$where  = array( '1=1' );
			$params = array();

			$form_ids = array_filter( array_map( 'intval', (array) ( $input['form_ids'] ?? array() ) ) );
			if ( ! empty( $form_ids ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $form_ids ), '%d' ) );
				$where[]      = "s.form_id IN ({$placeholders})";
				$params       = array_merge( $params, $form_ids );
			}

			if ( ! empty( $input['status'] ) ) {
				$where[]  = 's.status = %s';
				$params[] = sanitize_text_field( (string) $input['status'] );
			}

			$payment_statuses = array_filter( array_map( 'sanitize_text_field', (array) ( $input['payment_statuses'] ?? array() ) ) );
			if ( ! empty( $payment_statuses ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $payment_statuses ), '%s' ) );
				$where[]      = "s.payment_status IN ({$placeholders})";
				$params       = array_merge( $params, $payment_statuses );
			}

			if ( ! empty( $input['search'] ) ) {
				$where[]  = "s.response LIKE %s";
				$params[] = '%' . $wpdb->esc_like( sanitize_text_field( (string) $input['search'] ) ) . '%';
			}

			if ( ! empty( $input['date_from'] ) ) {
				$where[]  = 's.created_at >= %s';
				$params[] = sanitize_text_field( (string) $input['date_from'] ) . ' 00:00:00';
			}
			if ( ! empty( $input['date_to'] ) ) {
				$where[]  = 's.created_at <= %s';
				$params[] = sanitize_text_field( (string) $input['date_to'] ) . ' 23:59:59';
			}

			$where_sql  = implode( ' AND ', $where );
			$pagination = fluent_abilities_pagination( $input );

			$count_sql = "SELECT COUNT(*) FROM {$submissions_table} s WHERE {$where_sql}";
			$total = (int) ( ! empty( $params ) ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );

			$list_sql = "SELECT s.id, s.form_id, f.title AS form_title, s.serial_number,
								s.status, s.payment_status, s.user_id, s.created_at
						 FROM {$submissions_table} s
						 LEFT JOIN {$forms_table} f ON f.id = s.form_id
						 WHERE {$where_sql}
						 ORDER BY s.id DESC
						 LIMIT %d OFFSET %d";

			$rows = $wpdb->get_results( $wpdb->prepare(
				$list_sql,
				array_merge( $params, array( $pagination['per_page'], $pagination['offset'] ) )
			) );

			$items = array();
			foreach ( $rows as $r ) {
				$items[] = array(
					'id'             => (int) $r->id,
					'form_id'        => (int) $r->form_id,
					'form_title'     => (string) ( $r->form_title ?? '' ),
					'serial_number'  => (int) $r->serial_number,
					'status'         => $r->status,
					'payment_status' => $r->payment_status,
					'user_id'        => $r->user_id ? (int) $r->user_id : null,
					'created_at'     => (string) $r->created_at,
				);
			}

			return array(
				'submissions' => $items,
				'total'       => $total,
				'page'        => $pagination['page'],
				'per_page'    => $pagination['per_page'],
			);
		},
	) );

	// =========================================================================
	// 4.5 SUBMISSION NOTES (submission_meta key: 'note')
	// =========================================================================

	$reg->read( 'fluent-forms/list-submission-notes', array(
		'label'       => 'List Submission Notes',
		'description' => 'List notes attached to a Fluent Forms submission (paginated). Notes are stored in fluentform_submission_meta with meta_key="note".',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'submission_id' ),
			'properties' => array_merge( array(
				'submission_id' => array( 'type' => 'integer' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'notes', array(
			'id'         => array( 'type' => 'integer' ),
			'content'    => array( 'type' => 'string' ),
			'status'     => array( 'type' => array( 'string', 'null' ) ),
			'author'     => array( 'type' => array( 'string', 'null' ) ),
			'created_at' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read submission notes' );
			}

			$submission_id = (int) ( $input['submission_id'] ?? 0 );
			if ( $submission_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'submission_id is required' );
			}

			global $wpdb;
			$table = $wpdb->prefix . 'fluentform_submission_meta';

			$pagination = fluent_abilities_pagination( $input );
			$total = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE response_id = %d AND meta_key = 'note'",
				$submission_id
			) );

			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, value, status, name, created_at
				 FROM {$table}
				 WHERE response_id = %d AND meta_key = 'note'
				 ORDER BY id DESC
				 LIMIT %d OFFSET %d",
				$submission_id,
				$pagination['per_page'],
				$pagination['offset']
			) );

			$items = array();
			foreach ( $rows as $row ) {
				$items[] = array(
					'id'         => (int) $row->id,
					'content'    => (string) $row->value,
					'status'     => $row->status,
					'author'     => $row->name,
					'created_at' => (string) $row->created_at,
				);
			}

			return array(
				'notes'    => $items,
				'total'    => $total,
				'page'     => $pagination['page'],
				'per_page' => $pagination['per_page'],
			);
		},
	) );

	$reg->write( 'fluent-forms/add-submission-note', array(
		'label'       => 'Add Submission Note',
		'description' => 'Attach a note to a Fluent Forms submission. content is wp_kses_post-sanitized; status is freeform (defaults to "active").',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'submission_id', 'content' ),
			'properties' => array(
				'submission_id' => array( 'type' => 'integer' ),
				'content'       => array( 'type' => 'string' ),
				'status'        => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'         => array( 'type' => 'integer' ),
			'content'    => array( 'type' => 'string' ),
			'status'     => array( 'type' => 'string' ),
			'created_at' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'write' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to add submission notes' );
			}

			$submission_id = (int) ( $input['submission_id'] ?? 0 );
			$content       = isset( $input['content'] ) ? wp_kses_post( (string) $input['content'] ) : '';
			if ( $submission_id < 1 || '' === $content ) {
				return fluent_abilities_error( 'ability_invalid_input', 'submission_id and content are required' );
			}

			global $wpdb;
			$submissions_table = $wpdb->prefix . 'fluentform_submissions';
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$submissions_table} WHERE id = %d",
				$submission_id
			) );
			if ( ! $exists ) {
				return fluent_abilities_error( 'not_found', 'Submission not found' );
			}

			$form_id = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT form_id FROM {$submissions_table} WHERE id = %d",
				$submission_id
			) );

			$status   = isset( $input['status'] ) ? sanitize_text_field( (string) $input['status'] ) : 'active';
			$user     = wp_get_current_user();
			$author   = $user ? $user->display_name : '';
			$now      = current_time( 'mysql' );

			$inserted = $wpdb->insert(
				$wpdb->prefix . 'fluentform_submission_meta',
				array(
					'response_id' => $submission_id,
					'form_id'     => $form_id,
					'meta_key'    => 'note',
					'value'       => $content,
					'status'      => $status,
					'user_id'     => get_current_user_id() ?: null,
					'name'        => $author,
					'created_at'  => $now,
					'updated_at'  => $now,
				)
			);

			if ( false === $inserted ) {
				return fluent_abilities_error( 'ability_execution_failed', 'Note insert failed.' );
			}

			return array(
				'id'         => (int) $wpdb->insert_id,
				'content'    => $content,
				'status'     => $status,
				'created_at' => $now,
			);
		},
	) );

	$reg->delete( 'fluent-forms/delete-submission-note', array(
		'label'       => 'Delete Submission Note',
		'description' => 'Delete a single note (by note_id) belonging to a Fluent Forms submission.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'submission_id', 'note_id' ),
			'properties' => array(
				'submission_id' => array( 'type' => 'integer' ),
				'note_id'       => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'note_id' => array( 'type' => 'integer' ),
			'message' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'delete' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to delete submission notes' );
			}

			$submission_id = (int) ( $input['submission_id'] ?? 0 );
			$note_id       = (int) ( $input['note_id'] ?? 0 );
			if ( $submission_id < 1 || $note_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'submission_id and note_id are required' );
			}

			global $wpdb;
			$table = $wpdb->prefix . 'fluentform_submission_meta';
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$table} WHERE id = %d AND response_id = %d AND meta_key = 'note'",
				$note_id,
				$submission_id
			) );
			if ( ! $exists ) {
				return fluent_abilities_error( 'not_found', 'Note not found for this submission' );
			}

			$wpdb->delete( $table, array( 'id' => $note_id ), array( '%d' ) );

			return array(
				'note_id' => $note_id,
				'message' => 'deleted',
			);
		},
	) );

	// =========================================================================
	// 4.6 LOGS SURFACE
	// =========================================================================

	$reg->read( 'fluent-forms/list-logs', array(
		'label'       => 'List Logs',
		'description' => 'List Fluent Forms logs (paginated). Optional form_id, log_type, and search filters.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'form_id'  => array( 'type' => 'integer' ),
				'log_type' => array( 'type' => 'string', 'description' => 'Filter by source_type (e.g. form_item, submission_item, draft_submission_meta)' ),
				'search'   => array( 'type' => 'string' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'logs', array(
			'id'           => array( 'type' => 'integer' ),
			'source_type'  => array( 'type' => 'string' ),
			'source_id'    => array( 'type' => array( 'integer', 'null' ) ),
			'component'    => array( 'type' => array( 'string', 'null' ) ),
			'status'       => array( 'type' => array( 'string', 'null' ) ),
			'title'        => array( 'type' => array( 'string', 'null' ) ),
			'description'  => array( 'type' => array( 'string', 'null' ) ),
			'created_at'   => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read logs' );
			}

			global $wpdb;
			$table = $wpdb->prefix . 'fluentform_logs';
			$where  = array( '1=1' );
			$params = array();

			$form_id = (int) ( $input['form_id'] ?? 0 );
			if ( $form_id > 0 ) {
				$where[]  = "(source_id = %d AND source_type IN ('form_item', 'submission_item'))";
				$params[] = $form_id;
			}
			if ( ! empty( $input['log_type'] ) ) {
				$where[]  = 'source_type = %s';
				$params[] = sanitize_text_field( (string) $input['log_type'] );
			}
			if ( ! empty( $input['search'] ) ) {
				$where[]  = '(title LIKE %s OR description LIKE %s)';
				$like     = '%' . $wpdb->esc_like( sanitize_text_field( (string) $input['search'] ) ) . '%';
				$params[] = $like;
				$params[] = $like;
			}

			$where_sql  = implode( ' AND ', $where );
			$pagination = fluent_abilities_pagination( $input );

			$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
			$total = (int) ( ! empty( $params ) ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );

			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, source_type, source_id, component, status, title, description, created_at
				 FROM {$table}
				 WHERE {$where_sql}
				 ORDER BY id DESC
				 LIMIT %d OFFSET %d",
				array_merge( $params, array( $pagination['per_page'], $pagination['offset'] ) )
			) );

			$items = array();
			foreach ( $rows as $r ) {
				$items[] = array(
					'id'          => (int) $r->id,
					'source_type' => $r->source_type,
					'source_id'   => $r->source_id ? (int) $r->source_id : null,
					'component'   => $r->component,
					'status'      => $r->status,
					'title'       => $r->title,
					'description' => $r->description,
					'created_at'  => (string) $r->created_at,
				);
			}

			return array(
				'logs'     => $items,
				'total'    => $total,
				'page'     => $pagination['page'],
				'per_page' => $pagination['per_page'],
			);
		},
	) );

	$reg->read( 'fluent-forms/list-submission-logs', array(
		'label'       => 'List Submission Logs',
		'description' => 'List Fluent Forms logs scoped to a single submission (source_type=submission_item).',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'submission_id' ),
			'properties' => array_merge( array(
				'submission_id' => array( 'type' => 'integer' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'logs', array(
			'id'           => array( 'type' => 'integer' ),
			'source_type'  => array( 'type' => 'string' ),
			'component'    => array( 'type' => array( 'string', 'null' ) ),
			'status'       => array( 'type' => array( 'string', 'null' ) ),
			'title'        => array( 'type' => array( 'string', 'null' ) ),
			'description'  => array( 'type' => array( 'string', 'null' ) ),
			'created_at'   => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read submission logs' );
			}

			$submission_id = (int) ( $input['submission_id'] ?? 0 );
			if ( $submission_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'submission_id is required' );
			}

			global $wpdb;
			$table = $wpdb->prefix . 'fluentform_logs';
			$pagination = fluent_abilities_pagination( $input );

			$total = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE source_id = %d AND source_type = 'submission_item'",
				$submission_id
			) );

			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, source_type, component, status, title, description, created_at
				 FROM {$table}
				 WHERE source_id = %d AND source_type = 'submission_item'
				 ORDER BY id DESC
				 LIMIT %d OFFSET %d",
				$submission_id,
				$pagination['per_page'],
				$pagination['offset']
			) );

			$items = array();
			foreach ( $rows as $r ) {
				$items[] = array(
					'id'          => (int) $r->id,
					'source_type' => $r->source_type,
					'component'   => $r->component,
					'status'      => $r->status,
					'title'       => $r->title,
					'description' => $r->description,
					'created_at'  => (string) $r->created_at,
				);
			}

			return array(
				'logs'     => $items,
				'total'    => $total,
				'page'     => $pagination['page'],
				'per_page' => $pagination['per_page'],
			);
		},
	) );

	$reg->read( 'fluent-forms/get-log-filters', array(
		'label'       => 'Get Log Filters',
		'description' => 'Return the distinct source_type / status / component filters available in the logs, optionally scoped to a form.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'form_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'filters' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read log filters' );
			}

			global $wpdb;
			$table = $wpdb->prefix . 'fluentform_logs';
			$form_id = (int) ( $input['form_id'] ?? 0 );

			if ( $form_id > 0 ) {
				$rows = $wpdb->get_results( $wpdb->prepare(
					"SELECT source_type AS key_name, COUNT(*) AS count
					 FROM {$table}
					 WHERE source_id = %d
					 GROUP BY source_type",
					$form_id
				) );
			} else {
				$rows = $wpdb->get_results(
					"SELECT source_type AS key_name, COUNT(*) AS count FROM {$table} GROUP BY source_type"
				);
			}

			$filters = array();
			foreach ( $rows as $r ) {
				$filters[] = array(
					'key'   => $r->key_name,
					'label' => $r->key_name,
					'count' => (int) $r->count,
				);
			}

			return array( 'filters' => $filters );
		},
	) );

	$reg->delete( 'fluent-forms/delete-logs', array(
		'label'       => 'Delete Logs',
		'description' => 'Delete one log (log_id) or multiple logs (log_ids) from the Fluent Forms logs table. Optional type filter further restricts the delete.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'log_id'  => array( 'type' => 'integer' ),
				'log_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'type'    => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'count_deleted' => array( 'type' => 'integer' ),
			'message'       => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'delete' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to delete logs' );
			}

			$ids = array();
			if ( ! empty( $input['log_id'] ) ) {
				$ids[] = (int) $input['log_id'];
			}
			$ids = array_merge( $ids, array_filter( array_map( 'intval', (array) ( $input['log_ids'] ?? array() ) ) ) );
			$ids = array_values( array_unique( array_filter( $ids ) ) );

			if ( empty( $ids ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'log_id or log_ids is required' );
			}

			global $wpdb;
			$table = $wpdb->prefix . 'fluentform_logs';
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$params = $ids;

			$type_sql = '';
			if ( ! empty( $input['type'] ) ) {
				$type_sql = ' AND source_type = %s';
				$params[] = sanitize_text_field( (string) $input['type'] );
			}

			$affected = (int) $wpdb->query( $wpdb->prepare(
				"DELETE FROM {$table} WHERE id IN ({$placeholders}){$type_sql}",
				$params
			) );

			return array(
				'count_deleted' => $affected,
				'message'       => sprintf( 'Deleted %d log entries', $affected ),
			);
		},
	) );

	$reg->delete( 'fluent-forms/delete-submission-logs', array(
		'label'       => 'Delete Submission Logs',
		'description' => 'Wipe the log trail for a single Fluent Forms submission (source_type=submission_item).',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'submission_id' ),
			'properties' => array(
				'submission_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'count_deleted' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'delete' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to delete submission logs' );
			}

			$submission_id = (int) ( $input['submission_id'] ?? 0 );
			if ( $submission_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'submission_id is required' );
			}

			global $wpdb;
			$affected = (int) $wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}fluentform_logs WHERE source_id = %d AND source_type = 'submission_item'",
				$submission_id
			) );

			return array( 'count_deleted' => $affected );
		},
	) );

	// =========================================================================
	// 4.7 NOTIFICATIONS (form_meta key: 'notifications')
	// =========================================================================

	$notification_meta_key = 'notifications';

	$load_form_meta_array = function( $form_id, $meta_key ) {
		global $wpdb;
		$row = $wpdb->get_var( $wpdb->prepare(
			"SELECT value FROM {$wpdb->prefix}fluentform_form_meta WHERE form_id = %d AND meta_key = %s",
			$form_id,
			$meta_key
		) );
		if ( ! $row ) {
			return array();
		}
		$decoded = json_decode( $row, true );
		if ( ! is_array( $decoded ) ) {
			$decoded = maybe_unserialize( $row );
		}
		return is_array( $decoded ) ? $decoded : array();
	};

	$persist_form_meta_array = function( $form_id, $meta_key, $array ) {
		if ( class_exists( '\\FluentForm\\App\\Models\\FormMeta' ) ) {
			// Vendor signature: FormMeta::persist($formId, $metaKey, $metaValue) — see vendor app/Models/FormMeta.php.
			\FluentForm\App\Models\FormMeta::persist( $form_id, $meta_key, wp_json_encode( $array ) );
			return true;
		}
		global $wpdb;
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}fluentform_form_meta WHERE form_id = %d AND meta_key = %s",
			$form_id,
			$meta_key
		) );
		if ( $exists ) {
			$wpdb->update(
				$wpdb->prefix . 'fluentform_form_meta',
				array( 'value' => wp_json_encode( $array ) ),
				array( 'id' => $exists )
			);
		} else {
			$wpdb->insert(
				$wpdb->prefix . 'fluentform_form_meta',
				array(
					'form_id'  => $form_id,
					'meta_key' => $meta_key,
					'value'    => wp_json_encode( $array ),
				)
			);
		}
		return true;
	};

	$reg->read( 'fluent-forms/list-form-notifications', array(
		'label'       => 'List Form Notifications',
		'description' => 'List configured email notifications for a form (stored as fluentform_form_meta meta_key="notifications").',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id' ),
			'properties' => array(
				'form_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'form_id'       => array( 'type' => 'integer' ),
			'notifications' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $input ) use ( $load_form_meta_array, $notification_meta_key ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read notifications' );
			}
			$form_id = (int) ( $input['form_id'] ?? 0 );
			if ( $form_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id is required' );
			}
			$notifications = $load_form_meta_array( $form_id, $notification_meta_key );
			$indexed = array();
			foreach ( array_values( $notifications ) as $i => $n ) {
				$indexed[] = array_merge( array( 'index' => $i ), (array) $n );
			}
			return array(
				'form_id'       => $form_id,
				'notifications' => $indexed,
			);
		},
	) );

	$reg->read( 'fluent-forms/get-form-notification', array(
		'label'       => 'Get Form Notification',
		'description' => 'Return a single notification config by its array index on the form.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id', 'index' ),
			'properties' => array(
				'form_id' => array( 'type' => 'integer' ),
				'index'   => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'form_id'      => array( 'type' => 'integer' ),
			'index'        => array( 'type' => 'integer' ),
			'notification' => array( 'type' => 'object' ),
		) ),
		'callback' => function( $input ) use ( $load_form_meta_array, $notification_meta_key ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read notifications' );
			}
			$form_id = (int) ( $input['form_id'] ?? 0 );
			$index   = (int) ( $input['index'] ?? -1 );
			if ( $form_id < 1 || $index < 0 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id and non-negative index are required' );
			}
			$notifications = array_values( $load_form_meta_array( $form_id, $notification_meta_key ) );
			if ( ! isset( $notifications[ $index ] ) ) {
				return fluent_abilities_error( 'not_found', 'Notification not found' );
			}
			return array(
				'form_id'      => $form_id,
				'index'        => $index,
				'notification' => $notifications[ $index ],
			);
		},
	) );

	$reg->write( 'fluent-forms/create-form-notification', array(
		'label'       => 'Create Form Notification',
		'description' => 'Append a new notification config to a form. Notification structure is opaque to the registrar; the vendor evaluates conditionals + envelope at runtime.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id', 'notification' ),
			'properties' => array(
				'form_id'      => array( 'type' => 'integer' ),
				'notification' => array( 'type' => 'object' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'index'        => array( 'type' => 'integer' ),
			'notification' => array( 'type' => 'object' ),
		) ),
		'callback' => function( $input ) use ( $load_form_meta_array, $persist_form_meta_array, $notification_meta_key ) {
			if ( ! fluent_abilities_user_can( 'forms', 'write' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to create notifications' );
			}
			$form_id      = (int) ( $input['form_id'] ?? 0 );
			$notification = isset( $input['notification'] ) && is_array( $input['notification'] ) ? $input['notification'] : null;
			if ( $form_id < 1 || ! $notification ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id and notification object are required' );
			}
			$notifications = array_values( $load_form_meta_array( $form_id, $notification_meta_key ) );
			$notifications[] = $notification;
			$persist_form_meta_array( $form_id, $notification_meta_key, $notifications );
			$index = count( $notifications ) - 1;
			return array(
				'index'        => $index,
				'notification' => $notification,
			);
		},
	) );

	$reg->write( 'fluent-forms/update-form-notification', array(
		'label'       => 'Update Form Notification',
		'description' => 'Merge updates into a notification config at a specific array index. Top-level keys in the supplied notification object override existing values.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id', 'index', 'notification' ),
			'properties' => array(
				'form_id'      => array( 'type' => 'integer' ),
				'index'        => array( 'type' => 'integer' ),
				'notification' => array( 'type' => 'object' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'index'        => array( 'type' => 'integer' ),
			'notification' => array( 'type' => 'object' ),
		) ),
		'callback' => function( $input ) use ( $load_form_meta_array, $persist_form_meta_array, $notification_meta_key ) {
			if ( ! fluent_abilities_user_can( 'forms', 'write' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to update notifications' );
			}
			$form_id = (int) ( $input['form_id'] ?? 0 );
			$index   = (int) ( $input['index'] ?? -1 );
			$partial = isset( $input['notification'] ) && is_array( $input['notification'] ) ? $input['notification'] : null;
			if ( $form_id < 1 || $index < 0 || ! $partial ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id, index, and notification are required' );
			}
			$notifications = array_values( $load_form_meta_array( $form_id, $notification_meta_key ) );
			if ( ! isset( $notifications[ $index ] ) ) {
				return fluent_abilities_error( 'not_found', 'Notification not found' );
			}
			$notifications[ $index ] = array_merge( (array) $notifications[ $index ], $partial );
			$persist_form_meta_array( $form_id, $notification_meta_key, $notifications );
			return array(
				'index'        => $index,
				'notification' => $notifications[ $index ],
			);
		},
	) );

	$reg->delete( 'fluent-forms/delete-form-notification', array(
		'label'       => 'Delete Form Notification',
		'description' => 'Remove a notification config at the given array index.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id', 'index' ),
			'properties' => array(
				'form_id' => array( 'type' => 'integer' ),
				'index'   => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'index'   => array( 'type' => 'integer' ),
			'message' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) use ( $load_form_meta_array, $persist_form_meta_array, $notification_meta_key ) {
			if ( ! fluent_abilities_user_can( 'forms', 'delete' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to delete notifications' );
			}
			$form_id = (int) ( $input['form_id'] ?? 0 );
			$index   = (int) ( $input['index'] ?? -1 );
			if ( $form_id < 1 || $index < 0 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id and non-negative index are required' );
			}
			$notifications = array_values( $load_form_meta_array( $form_id, $notification_meta_key ) );
			if ( ! isset( $notifications[ $index ] ) ) {
				return fluent_abilities_error( 'not_found', 'Notification not found' );
			}
			array_splice( $notifications, $index, 1 );
			$persist_form_meta_array( $form_id, $notification_meta_key, $notifications );
			return array(
				'index'   => $index,
				'message' => 'deleted',
			);
		},
	) );

	// =========================================================================
	// 4.8 CONFIRMATIONS (form_meta key: 'confirmations')
	// =========================================================================

	$confirmation_meta_key = 'confirmations';

	$reg->read( 'fluent-forms/list-form-confirmations', array(
		'label'       => 'List Form Confirmations',
		'description' => 'List configured confirmation messages for a form (stored as fluentform_form_meta meta_key="confirmations").',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id' ),
			'properties' => array(
				'form_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'form_id'       => array( 'type' => 'integer' ),
			'confirmations' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $input ) use ( $load_form_meta_array, $confirmation_meta_key ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read confirmations' );
			}
			$form_id = (int) ( $input['form_id'] ?? 0 );
			if ( $form_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id is required' );
			}
			$confirmations = array_values( $load_form_meta_array( $form_id, $confirmation_meta_key ) );
			$indexed = array();
			foreach ( $confirmations as $i => $c ) {
				$indexed[] = array_merge( array( 'index' => $i ), (array) $c );
			}
			return array(
				'form_id'       => $form_id,
				'confirmations' => $indexed,
			);
		},
	) );

	$reg->write( 'fluent-forms/create-form-confirmation', array(
		'label'       => 'Create Form Confirmation',
		'description' => 'Append a new confirmation config to a form. Confirmation structure is opaque to the registrar; the vendor ConditionAssessor evaluates the configured conditions at runtime.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id', 'confirmation' ),
			'properties' => array(
				'form_id'      => array( 'type' => 'integer' ),
				'confirmation' => array( 'type' => 'object' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'index'        => array( 'type' => 'integer' ),
			'confirmation' => array( 'type' => 'object' ),
		) ),
		'callback' => function( $input ) use ( $load_form_meta_array, $persist_form_meta_array, $confirmation_meta_key ) {
			if ( ! fluent_abilities_user_can( 'forms', 'write' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to create confirmations' );
			}
			$form_id      = (int) ( $input['form_id'] ?? 0 );
			$confirmation = isset( $input['confirmation'] ) && is_array( $input['confirmation'] ) ? $input['confirmation'] : null;
			if ( $form_id < 1 || ! $confirmation ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id and confirmation object are required' );
			}
			$confirmations   = array_values( $load_form_meta_array( $form_id, $confirmation_meta_key ) );
			$confirmations[] = $confirmation;
			$persist_form_meta_array( $form_id, $confirmation_meta_key, $confirmations );
			$index = count( $confirmations ) - 1;
			return array(
				'index'        => $index,
				'confirmation' => $confirmation,
			);
		},
	) );

	$reg->write( 'fluent-forms/update-form-confirmation', array(
		'label'       => 'Update Form Confirmation',
		'description' => 'Merge updates into a confirmation config at a specific array index.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id', 'index', 'confirmation' ),
			'properties' => array(
				'form_id'      => array( 'type' => 'integer' ),
				'index'        => array( 'type' => 'integer' ),
				'confirmation' => array( 'type' => 'object' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'index'        => array( 'type' => 'integer' ),
			'confirmation' => array( 'type' => 'object' ),
		) ),
		'callback' => function( $input ) use ( $load_form_meta_array, $persist_form_meta_array, $confirmation_meta_key ) {
			if ( ! fluent_abilities_user_can( 'forms', 'write' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to update confirmations' );
			}
			$form_id = (int) ( $input['form_id'] ?? 0 );
			$index   = (int) ( $input['index'] ?? -1 );
			$partial = isset( $input['confirmation'] ) && is_array( $input['confirmation'] ) ? $input['confirmation'] : null;
			if ( $form_id < 1 || $index < 0 || ! $partial ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id, index, and confirmation are required' );
			}
			$confirmations = array_values( $load_form_meta_array( $form_id, $confirmation_meta_key ) );
			if ( ! isset( $confirmations[ $index ] ) ) {
				return fluent_abilities_error( 'not_found', 'Confirmation not found' );
			}
			$confirmations[ $index ] = array_merge( (array) $confirmations[ $index ], $partial );
			$persist_form_meta_array( $form_id, $confirmation_meta_key, $confirmations );
			return array(
				'index'        => $index,
				'confirmation' => $confirmations[ $index ],
			);
		},
	) );

	$reg->delete( 'fluent-forms/delete-form-confirmation', array(
		'label'       => 'Delete Form Confirmation',
		'description' => 'Remove a confirmation config at the given array index.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id', 'index' ),
			'properties' => array(
				'form_id' => array( 'type' => 'integer' ),
				'index'   => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'index'   => array( 'type' => 'integer' ),
			'message' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) use ( $load_form_meta_array, $persist_form_meta_array, $confirmation_meta_key ) {
			if ( ! fluent_abilities_user_can( 'forms', 'delete' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to delete confirmations' );
			}
			$form_id = (int) ( $input['form_id'] ?? 0 );
			$index   = (int) ( $input['index'] ?? -1 );
			if ( $form_id < 1 || $index < 0 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id and non-negative index are required' );
			}
			$confirmations = array_values( $load_form_meta_array( $form_id, $confirmation_meta_key ) );
			if ( ! isset( $confirmations[ $index ] ) ) {
				return fluent_abilities_error( 'not_found', 'Confirmation not found' );
			}
			array_splice( $confirmations, $index, 1 );
			$persist_form_meta_array( $form_id, $confirmation_meta_key, $confirmations );
			return array(
				'index'   => $index,
				'message' => 'deleted',
			);
		},
	) );

	// =========================================================================
	// 4.9 FORM SETTINGS — 4 GET/SAVE PAIRS (8 abilities)
	// =========================================================================

	$form_settings_pairs = array(
		array(
			'meta_key' => 'formSettings',
			'get'      => 'fluent-forms/get-form-settings',
			'save'     => 'fluent-forms/update-form-settings',
			'label_g'  => 'Get Form Settings',
			'label_s'  => 'Update Form Settings',
			'desc_g'   => 'Get the consolidated form settings object (confirmation, restrictions, layout, delete_entry_on_submission, conv_form_per_step_save, conv_form_resume_from_last_step).',
			'desc_s'   => 'Update the form settings object. Any subset of confirmation, restrictions, layout, delete_entry_on_submission, and conversational-form flags may be supplied.',
		),
		array(
			'meta_key' => 'formGeneralSettings',
			'get'      => 'fluent-forms/get-form-general-settings',
			'save'     => 'fluent-forms/update-form-general-settings',
			'label_g'  => 'Get Form General Settings',
			'label_s'  => 'Update Form General Settings',
			'desc_g'   => 'Get the general settings sub-area (label placement, asterisk placement, helpMessagePlacement, etc.).',
			'desc_s'   => 'Update the general settings sub-area.',
		),
		array(
			'meta_key' => '_ff_form_styles',
			'get'      => 'fluent-forms/get-form-customizer',
			'save'     => 'fluent-forms/update-form-customizer',
			'label_g'  => 'Get Form Customizer',
			'label_s'  => 'Update Form Customizer',
			'desc_g'   => 'Get the form customizer values (_custom_form_css, _custom_form_js, _ff_selected_style, _ff_form_styles).',
			'desc_s'   => 'Update the form customizer values.',
		),
		array(
			'meta_key' => 'advancedValidationSettings',
			'get'      => 'fluent-forms/get-form-advanced-validation',
			'save'     => 'fluent-forms/update-form-advanced-validation',
			'label_g'  => 'Get Advanced Validation Settings',
			'label_s'  => 'Update Advanced Validation Settings',
			'desc_g'   => 'Get the advanced validation settings (status, type, conditions, error_message, validation_type).',
			'desc_s'   => 'Update the advanced validation settings.',
		),
	);

	foreach ( $form_settings_pairs as $pair ) {
		$meta_key = $pair['meta_key'];

		$reg->read( $pair['get'], array(
			'label'       => $pair['label_g'],
			'description' => $pair['desc_g'],
			'input_schema' => array(
				'type'       => 'object',
				'required'   => array( 'form_id' ),
				'properties' => array(
					'form_id' => array( 'type' => 'integer' ),
				),
			),
			'output_schema' => fluent_abilities_schema_item_output( array(
				'form_id'  => array( 'type' => 'integer' ),
				'settings' => array( 'type' => array( 'object', 'array', 'null' ) ),
			) ),
			'callback' => function( $input ) use ( $load_form_meta_array, $meta_key ) {
				if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
					return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read form settings' );
				}
				$form_id = (int) ( $input['form_id'] ?? 0 );
				if ( $form_id < 1 ) {
					return fluent_abilities_error( 'ability_invalid_input', 'form_id is required' );
				}
				return array(
					'form_id'  => $form_id,
					'settings' => $load_form_meta_array( $form_id, $meta_key ),
				);
			},
		) );

		$reg->write( $pair['save'], array(
			'label'       => $pair['label_s'],
			'description' => $pair['desc_s'],
			'input_schema' => array(
				'type'       => 'object',
				'required'   => array( 'form_id', 'settings' ),
				'properties' => array(
					'form_id'  => array( 'type' => 'integer' ),
					'settings' => array( 'type' => array( 'object', 'array' ) ),
				),
			),
			'output_schema' => fluent_abilities_schema_item_output( array(
				'form_id' => array( 'type' => 'integer' ),
				'message' => array( 'type' => 'string' ),
			) ),
			'callback' => function( $input ) use ( $load_form_meta_array, $persist_form_meta_array, $meta_key ) {
				if ( ! fluent_abilities_user_can( 'forms', 'write' ) ) {
					return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to update form settings' );
				}
				$form_id = (int) ( $input['form_id'] ?? 0 );
				$settings = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : null;
				if ( $form_id < 1 || null === $settings ) {
					return fluent_abilities_error( 'ability_invalid_input', 'form_id and settings are required' );
				}
				$current = $load_form_meta_array( $form_id, $meta_key );
				$merged  = array_replace_recursive( is_array( $current ) ? $current : array(), $settings );
				$persist_form_meta_array( $form_id, $meta_key, $merged );
				return array(
					'form_id' => $form_id,
					'message' => 'updated',
				);
			},
		) );
	}

	// =========================================================================
	// 4.11 PER-FORM INTEGRATIONS
	// =========================================================================

	$reg->read( 'fluent-forms/list-form-integrations', array(
		'label'       => 'List Form Integrations',
		'description' => 'List configured integration feeds (third-party connectors) for a form. Each feed is stored in fluentform_form_meta with a meta_key prefixed by the integration handle.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id' ),
			'properties' => array(
				'form_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'form_id' => array( 'type' => 'integer' ),
			'feeds'   => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read form integrations' );
			}

			$form_id = (int) ( $input['form_id'] ?? 0 );
			if ( $form_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id is required' );
			}

			global $wpdb;
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, meta_key, value FROM {$wpdb->prefix}fluentform_form_meta
				 WHERE form_id = %d AND meta_key NOT IN ('notifications', 'confirmations', 'formSettings', 'formGeneralSettings', '_ff_form_styles', 'advancedValidationSettings', 'formGeneratedCss', '_pdf_feeds', '_total_views', 'is_conversion_form')",
				$form_id
			) );

			$feeds = array();
			foreach ( $rows as $row ) {
				$value   = json_decode( $row->value, true );
				if ( ! is_array( $value ) || ! isset( $value['enabled'] ) && ! isset( $value['settings'] ) ) {
					continue; // Skip non-integration meta rows.
				}
				$feeds[] = array(
					'id'               => (int) $row->id,
					'integration_name' => $row->meta_key,
					'list_id'          => $value['list_id'] ?? null,
					'enabled'          => ! empty( $value['enabled'] ),
					'settings'         => $value['settings'] ?? $value,
					'conditionals'     => $value['conditionals'] ?? null,
				);
			}

			return array( 'form_id' => $form_id, 'feeds' => $feeds );
		},
	) );

	$reg->read( 'fluent-forms/get-form-integration', array(
		'label'       => 'Get Form Integration',
		'description' => 'Get a single integration feed config by its row id.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id', 'integration_id' ),
			'properties' => array(
				'form_id'        => array( 'type' => 'integer' ),
				'integration_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'integration_id'   => array( 'type' => 'integer' ),
			'integration_name' => array( 'type' => 'string' ),
			'config'           => array( 'type' => array( 'object', 'array', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read form integrations' );
			}
			$form_id        = (int) ( $input['form_id'] ?? 0 );
			$integration_id = (int) ( $input['integration_id'] ?? 0 );
			if ( $form_id < 1 || $integration_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id and integration_id are required' );
			}

			global $wpdb;
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT meta_key, value FROM {$wpdb->prefix}fluentform_form_meta WHERE id = %d AND form_id = %d",
				$integration_id,
				$form_id
			) );
			if ( ! $row ) {
				return fluent_abilities_error( 'not_found', 'Integration not found' );
			}

			return array(
				'integration_id'   => $integration_id,
				'integration_name' => $row->meta_key,
				'config'           => json_decode( $row->value, true ),
			);
		},
	) );

	$reg->write( 'fluent-forms/create-form-integration', array(
		'label'       => 'Create Form Integration',
		'description' => 'Create a new integration feed for a form. Integration handle (integration_name) becomes the fluentform_form_meta meta_key; settings/conditionals are stored as JSON value.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id', 'integration_name', 'settings' ),
			'properties' => array(
				'form_id'          => array( 'type' => 'integer' ),
				'integration_name' => array( 'type' => 'string' ),
				'list_id'          => array( 'type' => array( 'string', 'integer', 'null' ) ),
				'settings'         => array( 'type' => array( 'object', 'array' ) ),
				'conditionals'     => array( 'type' => array( 'object', 'array', 'null' ) ),
				'enabled'          => array( 'type' => 'boolean' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'integration_id' => array( 'type' => 'integer' ),
			'message'        => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'write' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to create form integrations' );
			}

			$form_id          = (int) ( $input['form_id'] ?? 0 );
			$integration_name = isset( $input['integration_name'] ) ? sanitize_key( (string) $input['integration_name'] ) : '';
			$settings         = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : null;
			if ( $form_id < 1 || '' === $integration_name || null === $settings ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id, integration_name, and settings are required' );
			}

			global $wpdb;
			$value = array(
				'list_id'      => $input['list_id'] ?? null,
				'enabled'      => ! empty( $input['enabled'] ),
				'settings'     => $settings,
				'conditionals' => $input['conditionals'] ?? null,
			);
			$wpdb->insert(
				$wpdb->prefix . 'fluentform_form_meta',
				array(
					'form_id'  => $form_id,
					'meta_key' => $integration_name,
					'value'    => wp_json_encode( $value ),
				)
			);
			return array(
				'integration_id' => (int) $wpdb->insert_id,
				'message'        => 'created',
			);
		},
	) );

	$reg->write( 'fluent-forms/update-form-integration', array(
		'label'       => 'Update Form Integration',
		'description' => 'Partially update an integration feed (merge-replaces top-level keys in the stored value object).',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id', 'integration_id' ),
			'properties' => array(
				'form_id'        => array( 'type' => 'integer' ),
				'integration_id' => array( 'type' => 'integer' ),
				'list_id'        => array( 'type' => array( 'string', 'integer', 'null' ) ),
				'settings'       => array( 'type' => array( 'object', 'array' ) ),
				'conditionals'   => array( 'type' => array( 'object', 'array', 'null' ) ),
				'enabled'        => array( 'type' => 'boolean' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'integration_id' => array( 'type' => 'integer' ),
			'message'        => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'write' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to update form integrations' );
			}
			$form_id        = (int) ( $input['form_id'] ?? 0 );
			$integration_id = (int) ( $input['integration_id'] ?? 0 );
			if ( $form_id < 1 || $integration_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id and integration_id are required' );
			}

			global $wpdb;
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT value FROM {$wpdb->prefix}fluentform_form_meta WHERE id = %d AND form_id = %d",
				$integration_id,
				$form_id
			) );
			if ( ! $row ) {
				return fluent_abilities_error( 'not_found', 'Integration not found' );
			}

			$current = json_decode( $row->value, true );
			if ( ! is_array( $current ) ) {
				$current = array();
			}

			foreach ( array( 'list_id', 'settings', 'conditionals', 'enabled' ) as $key ) {
				if ( array_key_exists( $key, $input ) ) {
					$current[ $key ] = $input[ $key ];
				}
			}

			$wpdb->update(
				$wpdb->prefix . 'fluentform_form_meta',
				array( 'value' => wp_json_encode( $current ) ),
				array( 'id' => $integration_id )
			);

			return array(
				'integration_id' => $integration_id,
				'message'        => 'updated',
			);
		},
	) );

	$reg->delete( 'fluent-forms/delete-form-integration', array(
		'label'       => 'Delete Form Integration',
		'description' => 'Delete an integration feed by row id.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id', 'integration_id' ),
			'properties' => array(
				'form_id'        => array( 'type' => 'integer' ),
				'integration_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'integration_id' => array( 'type' => 'integer' ),
			'message'        => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'delete' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to delete form integrations' );
			}
			$form_id        = (int) ( $input['form_id'] ?? 0 );
			$integration_id = (int) ( $input['integration_id'] ?? 0 );
			if ( $form_id < 1 || $integration_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id and integration_id are required' );
			}

			global $wpdb;
			$affected = (int) $wpdb->delete(
				$wpdb->prefix . 'fluentform_form_meta',
				array( 'id' => $integration_id, 'form_id' => $form_id ),
				array( '%d', '%d' )
			);
			if ( 0 === $affected ) {
				return fluent_abilities_error( 'not_found', 'Integration not found' );
			}
			return array(
				'integration_id' => $integration_id,
				'message'        => 'deleted',
			);
		},
	) );

	// =========================================================================
	// 4.12 INTEGRATION MERGE-FIELDS + LIST-IDS
	// =========================================================================

	$reg->read( 'fluent-forms/get-integration-merge-fields', array(
		'label'       => 'Get Integration Merge Fields',
		'description' => 'Return the merge-field map an integration exposes for a given (form, list) pairing. Output shape is integration-specific (the vendor filter fluentform/get_integration_merge_fields_{name} owns the schema).',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id', 'integration_name' ),
			'properties' => array(
				'form_id'          => array( 'type' => 'integer' ),
				'integration_name' => array( 'type' => 'string' ),
				'list_id'          => array( 'type' => array( 'string', 'integer', 'null' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'merge_fields' => array( 'type' => array( 'object', 'array', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read integration merge fields' );
			}
			$form_id          = (int) ( $input['form_id'] ?? 0 );
			$integration_name = isset( $input['integration_name'] ) ? sanitize_key( (string) $input['integration_name'] ) : '';
			$list_id          = $input['list_id'] ?? '';
			if ( $form_id < 1 || '' === $integration_name ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id and integration_name are required' );
			}

			$filter_name = 'fluentform/get_integration_merge_fields_' . $integration_name;
			$merge_fields = apply_filters( $filter_name, array(), $list_id, $form_id );

			return array( 'merge_fields' => $merge_fields );
		},
	) );

	$reg->read( 'fluent-forms/get-integration-list-ids', array(
		'label'       => 'Get Integration List IDs',
		'description' => 'Return the available remote list ids for an integration (mailing list, audience, board, etc.). Output shape is integration-specific.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id', 'integration_name' ),
			'properties' => array(
				'form_id'          => array( 'type' => 'integer' ),
				'integration_name' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'list_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read integration list ids' );
			}
			$form_id          = (int) ( $input['form_id'] ?? 0 );
			$integration_name = isset( $input['integration_name'] ) ? sanitize_key( (string) $input['integration_name'] ) : '';
			if ( $form_id < 1 || '' === $integration_name ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id and integration_name are required' );
			}

			$filter_name = 'fluentform/get_integration_list_ids_' . $integration_name;
			$lists = apply_filters( $filter_name, array(), $form_id );

			$normalized = array();
			foreach ( (array) $lists as $key => $label ) {
				if ( is_array( $label ) ) {
					$normalized[] = array(
						'id'    => $label['id'] ?? $key,
						'label' => $label['label'] ?? $label['name'] ?? (string) $key,
					);
				} else {
					$normalized[] = array( 'id' => $key, 'label' => (string) $label );
				}
			}

			return array( 'list_ids' => $normalized );
		},
	) );

	// =========================================================================
	// 4.13 GLOBAL INTEGRATION REGISTRY
	// =========================================================================

	$reg->read( 'fluent-forms/list-available-integrations', array(
		'label'       => 'List Available Integrations',
		'description' => 'List all integration handles registered with Fluent Forms (output via the fluentform/global_integrations filter).',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => new stdClass(),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'integrations', array(
			'name'        => array( 'type' => 'string' ),
			'title'       => array( 'type' => array( 'string', 'null' ) ),
			'description' => array( 'type' => array( 'string', 'null' ) ),
			'enabled'     => array( 'type' => array( 'boolean', 'null' ) ),
			'category'    => array( 'type' => array( 'string', 'null' ) ),
			'tier'        => array( 'type' => array( 'string', 'null' ) ),
			'settings_url'=> array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read available integrations' );
			}

			$registry = apply_filters( 'fluentform/global_integrations', array() );
			$items = array();
			foreach ( (array) $registry as $name => $config ) {
				$config = (array) $config;
				$items[] = array(
					'name'         => (string) $name,
					'title'        => $config['title'] ?? null,
					'description'  => $config['description'] ?? null,
					'enabled'      => isset( $config['enabled'] ) ? (bool) $config['enabled'] : null,
					'category'     => $config['category'] ?? null,
					'tier'         => $config['tier'] ?? null,
					'settings_url' => $config['settings_url'] ?? null,
				);
			}

			return array(
				'integrations' => $items,
				'total'        => count( $items ),
			);
		},
	) );

	$reg->read( 'fluent-forms/get-integration-global-settings', array(
		'label'       => 'Get Integration Global Settings',
		'description' => 'Return the global settings record for an integration (API keys, OAuth tokens, default lists). Output shape is integration-specific.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'integration_name' ),
			'properties' => array(
				'integration_name' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'integration_name' => array( 'type' => 'string' ),
			'settings'         => array( 'type' => array( 'object', 'array', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read integration settings' );
			}
			$integration_name = isset( $input['integration_name'] ) ? sanitize_key( (string) $input['integration_name'] ) : '';
			if ( '' === $integration_name ) {
				return fluent_abilities_error( 'ability_invalid_input', 'integration_name is required' );
			}

			$option_key = 'fluentform_' . $integration_name . '_settings';
			$settings = get_option( $option_key, null );
			if ( null === $settings ) {
				$settings = apply_filters( 'fluentform/integration_global_settings_' . $integration_name, null );
			}

			return array(
				'integration_name' => $integration_name,
				'settings'         => $settings,
			);
		},
	) );

	$reg->write( 'fluent-forms/toggle-integration-status', array(
		'label'       => 'Toggle Integration Status',
		'description' => 'Enable or disable an integration globally. status is yes or no.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'integration_name', 'status' ),
			'properties' => array(
				'integration_name' => array( 'type' => 'string' ),
				'status'           => array( 'type' => 'string', 'enum' => array( 'yes', 'no' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'integration_name' => array( 'type' => 'string' ),
			'status'           => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'write' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to toggle integrations' );
			}
			$integration_name = isset( $input['integration_name'] ) ? sanitize_key( (string) $input['integration_name'] ) : '';
			$status           = isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : '';
			if ( '' === $integration_name || ! in_array( $status, array( 'yes', 'no' ), true ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'integration_name and status (yes|no) are required' );
			}

			$option_key = 'fluentform_global_modules_status';
			$registry   = get_option( $option_key, array() );
			if ( ! is_array( $registry ) ) {
				$registry = array();
			}
			$registry[ $integration_name ] = $status;
			update_option( $option_key, $registry );

			return array(
				'integration_name' => $integration_name,
				'status'           => $status,
			);
		},
	) );

	// =========================================================================
	// 4.14 GLOBAL SETTINGS (OPTION-KEY BRIDGE)
	// =========================================================================

	$global_settings_keys = array(
		'_fluentform_global_form_settings',
		'_fluentform_form_permission',
		'fluentform_logger_settings',
		'_fluentform_global_reCaptcha_details',
		'_fluentform_global_hCaptcha_details',
		'_fluentform_global_turnstile_details',
	);

	$reg->read( 'fluent-forms/get-global-settings', array(
		'label'       => 'Get Global Settings',
		'description' => 'Return Fluent Forms global plugin settings, keyed by option name. Optional keys[] filter restricts the set.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'keys' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'settings' => array( 'type' => 'object' ),
		) ),
		'callback' => function( $input ) use ( $global_settings_keys ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read global settings' );
			}

			$requested = array_filter( array_map( 'sanitize_text_field', (array) ( $input['keys'] ?? array() ) ) );
			$keys      = ! empty( $requested ) ? array_intersect( $requested, $global_settings_keys ) : $global_settings_keys;

			$settings = array();
			foreach ( $keys as $key ) {
				$settings[ $key ] = get_option( $key, null );
			}

			return array( 'settings' => $settings );
		},
	) );

	$reg->write( 'fluent-forms/update-global-settings', array(
		'label'       => 'Update Global Settings',
		'description' => 'Update one or more Fluent Forms global plugin settings (partial merge per top-level option key). Only the allow-listed global keys may be touched.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'settings' ),
			'properties' => array(
				'settings' => array( 'type' => 'object', 'description' => 'Object keyed by option name -> partial value (object or scalar).' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'message' => array( 'type' => 'string' ),
			'updated' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
		) ),
		'callback' => function( $input ) use ( $global_settings_keys ) {
			if ( ! fluent_abilities_user_can( 'forms', 'write' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to update global settings' );
			}
			$settings = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();
			if ( empty( $settings ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'settings object is required' );
			}

			$updated = array();
			foreach ( $settings as $key => $value ) {
				if ( ! in_array( $key, $global_settings_keys, true ) ) {
					continue;
				}
				if ( is_array( $value ) ) {
					$current = get_option( $key, array() );
					if ( ! is_array( $current ) ) {
						$current = array();
					}
					$value = array_replace_recursive( $current, $value );
				}
				update_option( $key, $value );
				$updated[] = $key;
			}

			return array(
				'message' => 'updated',
				'updated' => $updated,
			);
		},
	) );

	// =========================================================================
	// 4.15 MANAGERS + ROLES
	// =========================================================================

	$ff_caps = array(
		'fluentform_dashboard_access',
		'fluentform_forms_manager',
		'fluentform_entries_viewer',
		'fluentform_manage_entries',
		'fluentform_view_payments',
		'fluentform_manage_payments',
		'fluentform_settings_manager',
		'fluentform_full_access',
	);

	$reg->read( 'fluent-forms/list-managers', array(
		'label'       => 'List Managers',
		'description' => 'List WordPress users that have one or more Fluent Forms capabilities. Each row reports the FF caps granted plus their per-user form scope (allowed_forms + has_specific_forms).',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'search' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'managers', array(
			'user_id'             => array( 'type' => 'integer' ),
			'display_name'        => array( 'type' => 'string' ),
			'user_email'          => array( 'type' => 'string' ),
			'permissions'         => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'allowed_forms'       => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			'has_specific_forms'  => array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $input ) use ( $ff_caps ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read form managers' );
			}

			$args = array(
				'meta_query' => array(
					'relation' => 'OR',
					array( 'key' => 'wp_capabilities', 'value' => 'fluentform_', 'compare' => 'LIKE' ),
					array( 'key' => '_fluent_forms_has_specific_forms_permission', 'compare' => 'EXISTS' ),
				),
				'fields'     => 'all',
				'number'     => 200,
			);
			if ( ! empty( $input['search'] ) ) {
				$args['search']         = '*' . esc_attr( $input['search'] ) . '*';
				$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
			}

			$users = get_users( $args );
			$items = array();

			foreach ( $users as $user ) {
				$user_caps = array_keys( array_filter( $user->allcaps ) );
				$ff_user_caps = array_values( array_intersect( $user_caps, $ff_caps ) );
				$allowed_forms = get_user_meta( $user->ID, '_fluent_forms_allowed_forms', true );
				if ( ! is_array( $allowed_forms ) ) {
					$allowed_forms = is_string( $allowed_forms ) ? array_filter( array_map( 'intval', explode( ',', $allowed_forms ) ) ) : array();
				}
				if ( empty( $ff_user_caps ) && empty( $allowed_forms ) ) {
					continue;
				}
				$items[] = array(
					'user_id'            => (int) $user->ID,
					'display_name'       => $user->display_name,
					'user_email'         => $user->user_email,
					'permissions'        => $ff_user_caps,
					'allowed_forms'      => array_values( array_map( 'intval', (array) $allowed_forms ) ),
					'has_specific_forms' => 'yes' === get_user_meta( $user->ID, '_fluent_forms_has_specific_forms_permission', true ),
				);
			}

			return array(
				'managers' => $items,
				'total'    => count( $items ),
			);
		},
	) );

	$reg->write( 'fluent-forms/add-manager', array(
		'label'       => 'Add Manager',
		'description' => 'Grant Fluent Forms capabilities to a WordPress user. Optionally scope the manager to a specific subset of forms via allowed_forms; when has_specific_forms is true, allowed_forms must be non-empty (per LegacyManagerScopes migration semantics).',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'user_id', 'permissions' ),
			'properties' => array(
				'user_id'            => array( 'type' => 'integer' ),
				'permissions'        => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'allowed_forms'      => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				'has_specific_forms' => array( 'type' => 'boolean' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'user_id'            => array( 'type' => 'integer' ),
			'permissions'        => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'allowed_forms'      => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			'has_specific_forms' => array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $input ) use ( $ff_caps ) {
			if ( ! fluent_abilities_user_can( 'forms', 'write' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to add form managers' );
			}

			$user_id            = (int) ( $input['user_id'] ?? 0 );
			$requested_perms    = array_filter( array_map( 'sanitize_key', (array) ( $input['permissions'] ?? array() ) ) );
			$permissions        = array_values( array_intersect( $requested_perms, $ff_caps ) );
			$allowed_forms      = array_filter( array_map( 'intval', (array) ( $input['allowed_forms'] ?? array() ) ) );
			$has_specific_forms = ! empty( $input['has_specific_forms'] );

			if ( $user_id < 1 || empty( $permissions ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'user_id and at least one permission are required' );
			}

			$user = get_user_by( 'id', $user_id );
			if ( ! $user ) {
				return fluent_abilities_error( 'not_found', 'User not found' );
			}

			// Mirror LegacyManagerScopes migration: empty allowed_forms with has_specific_forms='yes' normalizes to 'no'.
			if ( $has_specific_forms && empty( $allowed_forms ) ) {
				$has_specific_forms = false;
			}

			foreach ( $permissions as $cap ) {
				$user->add_cap( $cap );
			}

			update_user_meta( $user_id, '_fluent_forms_has_specific_forms_permission', $has_specific_forms ? 'yes' : 'no' );
			update_user_meta( $user_id, '_fluent_forms_allowed_forms', $allowed_forms );

			return array(
				'user_id'            => $user_id,
				'permissions'        => $permissions,
				'allowed_forms'      => array_values( $allowed_forms ),
				'has_specific_forms' => $has_specific_forms,
			);
		},
	) );

	$reg->delete( 'fluent-forms/remove-manager', array(
		'label'       => 'Remove Manager',
		'description' => 'Remove all Fluent Forms capabilities and per-user form scope from a WordPress user.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'user_id' ),
			'properties' => array(
				'user_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'user_id' => array( 'type' => 'integer' ),
			'message' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) use ( $ff_caps ) {
			if ( ! fluent_abilities_user_can( 'forms', 'delete' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to remove form managers' );
			}
			$user_id = (int) ( $input['user_id'] ?? 0 );
			if ( $user_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'user_id is required' );
			}
			$user = get_user_by( 'id', $user_id );
			if ( ! $user ) {
				return fluent_abilities_error( 'not_found', 'User not found' );
			}
			foreach ( $ff_caps as $cap ) {
				$user->remove_cap( $cap );
			}
			delete_user_meta( $user_id, '_fluent_forms_has_specific_forms_permission' );
			delete_user_meta( $user_id, '_fluent_forms_allowed_forms' );
			return array(
				'user_id' => $user_id,
				'message' => 'removed',
			);
		},
	) );

	$reg->read( 'fluent-forms/list-role-capabilities', array(
		'label'       => 'List Role Capabilities',
		'description' => 'List which Fluent Forms capabilities are granted to each WordPress role. Optional role search.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'search' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'roles', array(
			'role'         => array( 'type' => 'string' ),
			'name'         => array( 'type' => array( 'string', 'null' ) ),
			'capabilities' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
		) ),
		'callback' => function( $input ) use ( $ff_caps ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read role capabilities' );
			}

			$search    = isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '';
			$wp_roles  = wp_roles();
			$items     = array();
			foreach ( $wp_roles->roles as $slug => $config ) {
				$caps = array_keys( array_filter( $config['capabilities'] ?? array() ) );
				$ff_role_caps = array_values( array_intersect( $caps, $ff_caps ) );
				if ( '' !== $search && stripos( $slug, $search ) === false && stripos( (string) ( $config['name'] ?? '' ), $search ) === false ) {
					continue;
				}
				$items[] = array(
					'role'         => $slug,
					'name'         => $config['name'] ?? null,
					'capabilities' => $ff_role_caps,
				);
			}
			return array(
				'roles' => $items,
				'total' => count( $items ),
			);
		},
	) );

	$reg->write( 'fluent-forms/set-role-capability', array(
		'label'       => 'Set Role Capability',
		'description' => 'Add or remove a single Fluent Forms capability from a WordPress role.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'role', 'capability', 'enabled' ),
			'properties' => array(
				'role'       => array( 'type' => 'string' ),
				'capability' => array( 'type' => 'string' ),
				'enabled'    => array( 'type' => 'boolean' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'role'       => array( 'type' => 'string' ),
			'capability' => array( 'type' => 'string' ),
			'enabled'    => array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $input ) use ( $ff_caps ) {
			if ( ! fluent_abilities_user_can( 'forms', 'write' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to set role capabilities' );
			}
			$role_slug = isset( $input['role'] ) ? sanitize_key( (string) $input['role'] ) : '';
			$cap       = isset( $input['capability'] ) ? sanitize_key( (string) $input['capability'] ) : '';
			$enabled   = ! empty( $input['enabled'] );
			if ( '' === $role_slug || '' === $cap ) {
				return fluent_abilities_error( 'ability_invalid_input', 'role and capability are required' );
			}
			if ( ! in_array( $cap, $ff_caps, true ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'capability must be one of the Fluent Forms caps' );
			}
			$role = get_role( $role_slug );
			if ( ! $role ) {
				return fluent_abilities_error( 'not_found', 'Role not found' );
			}
			if ( $enabled ) {
				$role->add_cap( $cap );
			} else {
				$role->remove_cap( $cap );
			}
			return array(
				'role'       => $role_slug,
				'capability' => $cap,
				'enabled'    => $enabled,
			);
		},
	) );

	// =========================================================================
	// 4.16 ANALYTICS + FORM VIEWS
	// =========================================================================

	$reg->read( 'fluent-forms/list-form-views', array(
		'label'       => 'List Form Views',
		'description' => 'List raw rows from the fluentform_form_analytics table for a given form (paginated). Optional date_from / date_to filters.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id' ),
			'properties' => array_merge( array(
				'form_id'   => array( 'type' => 'integer' ),
				'date_from' => array( 'type' => 'string' ),
				'date_to'   => array( 'type' => 'string' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'views', array(
			'id'          => array( 'type' => 'integer' ),
			'user_id'     => array( 'type' => array( 'integer', 'null' ) ),
			'source_url'  => array( 'type' => array( 'string', 'null' ) ),
			'platform'    => array( 'type' => array( 'string', 'null' ) ),
			'browser'     => array( 'type' => array( 'string', 'null' ) ),
			'city'        => array( 'type' => array( 'string', 'null' ) ),
			'country'     => array( 'type' => array( 'string', 'null' ) ),
			'ip'          => array( 'type' => array( 'string', 'null' ) ),
			'created_at'  => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read form analytics' );
			}
			$form_id = (int) ( $input['form_id'] ?? 0 );
			if ( $form_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id is required' );
			}

			global $wpdb;
			$table = $wpdb->prefix . 'fluentform_form_analytics';
			$where = array( 'form_id = %d' );
			$params = array( $form_id );

			if ( ! empty( $input['date_from'] ) ) {
				$where[]  = 'created_at >= %s';
				$params[] = sanitize_text_field( (string) $input['date_from'] ) . ' 00:00:00';
			}
			if ( ! empty( $input['date_to'] ) ) {
				$where[]  = 'created_at <= %s';
				$params[] = sanitize_text_field( (string) $input['date_to'] ) . ' 23:59:59';
			}
			$where_sql  = implode( ' AND ', $where );
			$pagination = fluent_abilities_pagination( $input );

			$total = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE {$where_sql}",
				$params
			) );
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, user_id, source_url, platform, browser, city, country, ip, created_at
				 FROM {$table} WHERE {$where_sql}
				 ORDER BY id DESC
				 LIMIT %d OFFSET %d",
				array_merge( $params, array( $pagination['per_page'], $pagination['offset'] ) )
			) );

			$items = array();
			foreach ( $rows as $r ) {
				$items[] = array(
					'id'         => (int) $r->id,
					'user_id'    => $r->user_id ? (int) $r->user_id : null,
					'source_url' => $r->source_url,
					'platform'   => $r->platform,
					'browser'    => $r->browser,
					'city'       => $r->city,
					'country'    => $r->country,
					'ip'         => $r->ip,
					'created_at' => (string) $r->created_at,
				);
			}

			return array(
				'views'    => $items,
				'total'    => $total,
				'page'     => $pagination['page'],
				'per_page' => $pagination['per_page'],
			);
		},
	) );

	$reg->delete( 'fluent-forms/reset-form-analytics', array(
		'label'       => 'Reset Form Analytics',
		'description' => 'Delete all rows from fluentform_form_analytics for a given form (clears view tracking).',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id' ),
			'properties' => array(
				'form_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'form_id'       => array( 'type' => 'integer' ),
			'count_deleted' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'delete' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to reset form analytics' );
			}
			$form_id = (int) ( $input['form_id'] ?? 0 );
			if ( $form_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id is required' );
			}
			global $wpdb;
			$affected = (int) $wpdb->delete(
				$wpdb->prefix . 'fluentform_form_analytics',
				array( 'form_id' => $form_id ),
				array( '%d' )
			);
			return array(
				'form_id'       => $form_id,
				'count_deleted' => $affected,
			);
		},
	) );

	$reg->read( 'fluent-forms/get-form-conversion-stats', array(
		'label'       => 'Get Form Conversion Stats',
		'description' => 'Daily conversion stats for a form (views, submissions, conversion_rate) optionally restricted to a date_range.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id' ),
			'properties' => array(
				'form_id'   => array( 'type' => 'integer' ),
				'date_from' => array( 'type' => 'string' ),
				'date_to'   => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'form_id' => array( 'type' => 'integer' ),
			'daily'   => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read conversion stats' );
			}
			$form_id = (int) ( $input['form_id'] ?? 0 );
			if ( $form_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id is required' );
			}

			global $wpdb;
			$analytics_table   = $wpdb->prefix . 'fluentform_form_analytics';
			$submissions_table = $wpdb->prefix . 'fluentform_submissions';

			$range_sql_views = '';
			$range_sql_subs  = '';
			$params_views    = array( $form_id );
			$params_subs     = array( $form_id );

			if ( ! empty( $input['date_from'] ) ) {
				$range_sql_views .= ' AND a.created_at >= %s';
				$range_sql_subs  .= ' AND s.created_at >= %s';
				$params_views[]   = sanitize_text_field( (string) $input['date_from'] ) . ' 00:00:00';
				$params_subs[]    = sanitize_text_field( (string) $input['date_from'] ) . ' 00:00:00';
			}
			if ( ! empty( $input['date_to'] ) ) {
				$range_sql_views .= ' AND a.created_at <= %s';
				$range_sql_subs  .= ' AND s.created_at <= %s';
				$params_views[]   = sanitize_text_field( (string) $input['date_to'] ) . ' 23:59:59';
				$params_subs[]    = sanitize_text_field( (string) $input['date_to'] ) . ' 23:59:59';
			}

			$views = $wpdb->get_results( $wpdb->prepare(
				"SELECT DATE(a.created_at) AS day, COUNT(*) AS count
				 FROM {$analytics_table} a
				 WHERE a.form_id = %d{$range_sql_views}
				 GROUP BY day",
				$params_views
			) );
			$subs = $wpdb->get_results( $wpdb->prepare(
				"SELECT DATE(s.created_at) AS day, COUNT(*) AS count
				 FROM {$submissions_table} s
				 WHERE s.form_id = %d{$range_sql_subs}
				 GROUP BY day",
				$params_subs
			) );

			$daily = array();
			foreach ( $views as $row ) {
				$daily[ $row->day ] = array( 'date' => $row->day, 'views' => (int) $row->count, 'submissions' => 0, 'conversion_rate' => 0 );
			}
			foreach ( $subs as $row ) {
				$daily[ $row->day ]['submissions'] = (int) $row->count;
				if ( empty( $daily[ $row->day ]['date'] ) ) {
					$daily[ $row->day ]['date'] = $row->day;
				}
				$views_count = $daily[ $row->day ]['views'] ?? 0;
				$daily[ $row->day ]['conversion_rate'] = $views_count > 0 ? round( ( $row->count / $views_count ) * 100, 2 ) : 0;
			}

			ksort( $daily );
			return array(
				'form_id' => $form_id,
				'daily'   => array_values( $daily ),
			);
		},
	) );

	// =========================================================================
	// 4.23 GLOBAL SEARCH
	// =========================================================================

	$reg->read( 'fluent-forms/global-search', array(
		'label'       => 'Global Search',
		'description' => 'Search across Fluent Forms titles and submission responses. scope controls which result types are returned (forms / submissions / all).',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'search' ),
			'properties' => array(
				'search' => array( 'type' => 'string' ),
				'scope'  => array( 'type' => 'string', 'enum' => array( 'forms', 'submissions', 'all' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'forms'       => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'submissions' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to use global search' );
			}
			$search = isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '';
			if ( '' === $search ) {
				return fluent_abilities_error( 'ability_invalid_input', 'search is required' );
			}
			$scope = isset( $input['scope'] ) ? sanitize_key( (string) $input['scope'] ) : 'all';
			if ( ! in_array( $scope, array( 'forms', 'submissions', 'all' ), true ) ) {
				$scope = 'all';
			}

			global $wpdb;
			$like   = '%' . $wpdb->esc_like( $search ) . '%';
			$result = array( 'forms' => array(), 'submissions' => array() );

			if ( in_array( $scope, array( 'forms', 'all' ), true ) ) {
				$rows = $wpdb->get_results( $wpdb->prepare(
					"SELECT id, title, status, type FROM {$wpdb->prefix}fluentform_forms
					 WHERE title LIKE %s
					 ORDER BY id DESC LIMIT 25",
					$like
				) );
				foreach ( $rows as $r ) {
					$result['forms'][] = array(
						'id'     => (int) $r->id,
						'title'  => $r->title,
						'status' => $r->status,
						'type'   => $r->type,
					);
				}
			}

			if ( in_array( $scope, array( 'submissions', 'all' ), true ) ) {
				$rows = $wpdb->get_results( $wpdb->prepare(
					"SELECT id, form_id, serial_number, status, created_at
					 FROM {$wpdb->prefix}fluentform_submissions
					 WHERE response LIKE %s
					 ORDER BY id DESC LIMIT 25",
					$like
				) );
				foreach ( $rows as $r ) {
					$result['submissions'][] = array(
						'id'            => (int) $r->id,
						'form_id'       => (int) $r->form_id,
						'serial_number' => (int) $r->serial_number,
						'status'        => $r->status,
						'created_at'    => (string) $r->created_at,
					);
				}
			}

			return $result;
		},
	) );

	error_log( 'Abilities for Fluent: Registered 61 Forms write-tier abilities' );

}, 100 );
