<?php
/**
 * Fluent Forms Abilities
 *
 * Form management, submissions, field definitions, and analytics.
 * Uses wpFluent() query builder for direct database access.
 *
 * 6 abilities in the 'fluent-forms' category.
 * Registered via Fluent_Abilities_Registrar.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

// v2.0.0 — chain-load additional Forms ability sub-files. Each sub-file
// registers its own abilities on 'wp_abilities_api_init'; the existing 6
// registrations below remain byte-identical (Stable Ability Contracts).
foreach ( array( 'write-abilities', 'pro-abilities' ) as $forms_sub ) {
	$forms_sub_file = __DIR__ . "/{$forms_sub}.php";
	if ( file_exists( $forms_sub_file ) ) {
		require_once $forms_sub_file;
	}
}

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'forms' );

	// =========================================================================
	// FORMS
	// =========================================================================

	$reg->read( 'fluent-forms/list-forms', array(
		'label'       => 'List Forms',
		'description' => 'List Fluent Forms with id, title, status, type, submission count, and creation date. Optional status filter.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'status' => array(
					'type'        => 'string',
					'description' => 'Filter by form status: published, unpublished',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'forms', array(
			'id'               => array( 'type' => 'integer' ),
			'title'            => array( 'type' => 'string' ),
			'status'           => array( 'type' => 'string' ),
			'type'             => array( 'type' => 'string' ),
			'submission_count' => array( 'type' => 'integer' ),
			'created_at'       => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read forms' );
			}

			global $wpdb;
			$forms_table       = $wpdb->prefix . 'fluentform_forms';
			$submissions_table = $wpdb->prefix . 'fluentform_submissions';

			$where  = '';
			$params = array();

			if ( ! empty( $input['status'] ) ) {
				$where    = 'WHERE f.status = %s';
				$params[] = sanitize_text_field( $input['status'] );
			}

			// Build the query with submission counts via LEFT JOIN.
			$sql = "SELECT f.id, f.title, f.status, f.type, f.created_at,
						COALESCE(sc.submission_count, 0) AS submission_count
					FROM {$forms_table} f
					LEFT JOIN (
						SELECT form_id, COUNT(*) AS submission_count
						FROM {$submissions_table}
						GROUP BY form_id
					) sc ON sc.form_id = f.id
					{$where}
					ORDER BY f.id DESC";

			if ( ! empty( $params ) ) {
				$sql = $wpdb->prepare( $sql, $params );
			}

			$forms = $wpdb->get_results( $sql );

			$items = array();
			foreach ( $forms as $form ) {
				$items[] = array(
					'id'               => (int) $form->id,
					'title'            => $form->title,
					'status'           => $form->status,
					'type'             => $form->type,
					'submission_count'  => (int) $form->submission_count,
					'created_at'       => (string) $form->created_at,
				);
			}

			return array( 'forms' => $items, 'total' => count( $items ) );
		},
	) );

	$reg->read( 'fluent-forms/get-form', array(
		'label'       => 'Get Form',
		'description' => 'Get a form by ID with full details including field definitions, notifications, and submission count.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Form ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'                   => array( 'type' => 'integer' ),
			'title'                => array( 'type' => 'string' ),
			'status'               => array( 'type' => 'string' ),
			'type'                 => array( 'type' => 'string' ),
			'form_fields'          => array( 'type' => array( 'object', 'null' ) ),
			'has_payment'          => array( 'type' => 'boolean' ),
			'conditions'           => array( 'type' => array( 'object', 'array', 'null' ) ),
			'appearance_settings'  => array( 'type' => array( 'object', 'null' ) ),
			'submission_count'     => array( 'type' => 'integer' ),
			'meta'                 => array( 'type' => 'object' ),
			'created_at'           => array( 'type' => 'string' ),
			'updated_at'           => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read forms' );
			}

			$form_id = (int) $input['id'];
			if ( $form_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Form ID must be a positive integer' );
			}

			global $wpdb;
			$forms_table       = $wpdb->prefix . 'fluentform_forms';
			$meta_table        = $wpdb->prefix . 'fluentform_form_meta';
			$submissions_table = $wpdb->prefix . 'fluentform_submissions';

			// Get the form record.
			$form = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$forms_table} WHERE id = %d",
				$form_id
			) );

			if ( ! $form ) {
				return fluent_abilities_error( 'not_found', 'Form not found' );
			}

			// Submission count.
			$submission_count = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$submissions_table} WHERE form_id = %d",
				$form_id
			) );

			// Fetch form meta entries for key configuration.
			$meta_keys = array( 'formGeneratedCss', 'notifications', '_pdf_feeds', 'confirmations' );
			$placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
			$meta_params  = array_merge( array( $form_id ), $meta_keys );

			$meta_rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT meta_key, value FROM {$meta_table} WHERE form_id = %d AND meta_key IN ({$placeholders})",
				$meta_params
			) );

			$meta = array();
			foreach ( $meta_rows as $row ) {
				$decoded = json_decode( $row->value, true );
				$meta[ $row->meta_key ] = fluent_abilities_safe_array( ( $decoded !== null ) ? $decoded : maybe_unserialize( $row->value ) );
			}

			// Parse form_fields from the form record (stored as JSON in the forms table).
			$form_fields = json_decode( $form->form_fields, true );

			return array(
				'id'               => (int) $form->id,
				'title'            => $form->title,
				'status'           => $form->status,
				'type'             => $form->type,
				'form_fields'      => $form_fields,
				'has_payment'      => (bool) ( $form->has_payment ?? false ),
				'conditions'       => json_decode( $form->conditions ?? '[]', true ),
				'appearance_settings' => $form->appearance_settings ? json_decode( $form->appearance_settings, true ) : null,
				'submission_count' => $submission_count,
				'meta'             => $meta,
				'created_at'       => (string) $form->created_at,
				'updated_at'       => (string) $form->updated_at,
			);
		},
	) );

	// =========================================================================
	// SUBMISSIONS
	// =========================================================================

	$reg->read( 'fluent-forms/list-submissions', array(
		'label'       => 'List Form Submissions',
		'description' => 'List submissions for a form. Paginated. Includes parsed response data. Optional status filter and date range.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id' ),
			'properties' => array_merge( array(
				'form_id' => array(
					'type'        => 'integer',
					'description' => 'Form ID to list submissions for',
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'Filter by submission status: unread, read, trashed, favourites',
				),
				'date_from' => array(
					'type'        => 'string',
					'description' => 'Start date filter (YYYY-MM-DD format)',
				),
				'date_to' => array(
					'type'        => 'string',
					'description' => 'End date filter (YYYY-MM-DD format)',
				),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'submissions', array(
			'id'            => array( 'type' => 'integer' ),
			'serial_number' => array( 'type' => 'integer' ),
			'response'      => array( 'type' => 'object' ),
			'status'        => array( 'type' => 'string' ),
			'browser'       => array( 'type' => array( 'string', 'null' ) ),
			'device'        => array( 'type' => array( 'string', 'null' ) ),
			'ip'            => array( 'type' => array( 'string', 'null' ) ),
			'city'          => array( 'type' => array( 'string', 'null' ) ),
			'country'       => array( 'type' => array( 'string', 'null' ) ),
			'user_id'       => array( 'type' => array( 'integer', 'null' ) ),
			'created_at'    => array( 'type' => 'string' ),
			'updated_at'    => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read form submissions' );
			}

			$form_id = (int) $input['form_id'];
			if ( $form_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id must be a positive integer' );
			}

			// Verify the form exists.
			global $wpdb;
			$forms_table       = $wpdb->prefix . 'fluentform_forms';
			$submissions_table = $wpdb->prefix . 'fluentform_submissions';

			$form_exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$forms_table} WHERE id = %d",
				$form_id
			) );

			if ( ! $form_exists ) {
				return fluent_abilities_error( 'not_found', 'Form not found' );
			}

			$pagination = fluent_abilities_pagination( $input );

			// Build WHERE clauses.
			$where  = array( 's.form_id = %d' );
			$params = array( $form_id );

			if ( ! empty( $input['status'] ) ) {
				$where[]  = 's.status = %s';
				$params[] = sanitize_text_field( $input['status'] );
			}

			if ( ! empty( $input['date_from'] ) ) {
				$where[]  = 's.created_at >= %s';
				$params[] = sanitize_text_field( $input['date_from'] ) . ' 00:00:00';
			}

			if ( ! empty( $input['date_to'] ) ) {
				$where[]  = 's.created_at <= %s';
				$params[] = sanitize_text_field( $input['date_to'] ) . ' 23:59:59';
			}

			$where_sql = implode( ' AND ', $where );

			// Count total.
			$total = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$submissions_table} s WHERE {$where_sql}",
				$params
			) );

			// Fetch paginated results.
			$limit_params   = array_merge( $params, array( $pagination['per_page'], $pagination['offset'] ) );
			$submissions = $wpdb->get_results( $wpdb->prepare(
				"SELECT s.id, s.serial_number, s.response, s.status, s.browser, s.device,
						s.ip, s.city, s.country, s.user_id, s.created_at, s.updated_at
				 FROM {$submissions_table} s
				 WHERE {$where_sql}
				 ORDER BY s.id DESC
				 LIMIT %d OFFSET %d",
				$limit_params
			) );

			$items = array();
			foreach ( $submissions as $sub ) {
				$response_data = json_decode( $sub->response, true );

				$items[] = array(
					'id'            => (int) $sub->id,
					'serial_number' => (int) $sub->serial_number,
					'response'      => is_array( $response_data ) ? $response_data : array(),
					'status'        => $sub->status,
					'browser'       => $sub->browser,
					'device'        => $sub->device,
					'ip'            => $sub->ip,
					'city'          => $sub->city,
					'country'       => $sub->country,
					'user_id'       => $sub->user_id ? (int) $sub->user_id : null,
					'created_at'    => (string) $sub->created_at,
					'updated_at'    => (string) $sub->updated_at,
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

	$reg->read( 'fluent-forms/get-submission', array(
		'label'       => 'Get Form Submission',
		'description' => 'Get a single submission by ID with full parsed response data and entry details.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Submission ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'            => array( 'type' => 'integer' ),
			'form_id'       => array( 'type' => 'integer' ),
			'serial_number' => array( 'type' => 'integer' ),
			'response'      => array( 'type' => 'object' ),
			'status'        => array( 'type' => 'string' ),
			'browser'       => array( 'type' => array( 'string', 'null' ) ),
			'device'        => array( 'type' => array( 'string', 'null' ) ),
			'ip'            => array( 'type' => array( 'string', 'null' ) ),
			'city'          => array( 'type' => array( 'string', 'null' ) ),
			'country'       => array( 'type' => array( 'string', 'null' ) ),
			'user_id'       => array( 'type' => array( 'integer', 'null' ) ),
			'entry_details' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'meta'          => array( 'type' => 'object' ),
			'created_at'    => array( 'type' => 'string' ),
			'updated_at'    => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read submissions' );
			}

			$submission_id = (int) $input['id'];
			if ( $submission_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Submission ID must be a positive integer' );
			}

			global $wpdb;
			$submissions_table   = $wpdb->prefix . 'fluentform_submissions';
			$entry_details_table = $wpdb->prefix . 'fluentform_entry_details';
			$submission_meta_table = $wpdb->prefix . 'fluentform_submission_meta';

			// Get the submission record.
			$sub = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$submissions_table} WHERE id = %d",
				$submission_id
			) );

			if ( ! $sub ) {
				return fluent_abilities_error( 'not_found', 'Submission not found' );
			}

			// Parse response JSON.
			$response_data = json_decode( $sub->response, true );

			// Get entry details (individual field breakdowns).
			$entry_details = $wpdb->get_results( $wpdb->prepare(
				"SELECT field_name, sub_field_name, field_value
				 FROM {$entry_details_table}
				 WHERE submission_id = %d
				 ORDER BY id ASC",
				$submission_id
			) );

			$details = array();
			foreach ( $entry_details as $detail ) {
				$details[] = array(
					'field_name'     => $detail->field_name,
					'sub_field_name' => $detail->sub_field_name,
					'field_value'    => $detail->field_value,
				);
			}

			// Get submission meta.
			$meta_rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT meta_key, value FROM {$submission_meta_table} WHERE response_id = %d",
				$submission_id
			) );

			$meta = array();
			foreach ( $meta_rows as $row ) {
				$decoded = json_decode( $row->value, true );
				$meta[ $row->meta_key ] = fluent_abilities_safe_array( ( $decoded !== null ) ? $decoded : $row->value );
			}

			return array(
				'id'            => (int) $sub->id,
				'form_id'       => (int) $sub->form_id,
				'serial_number' => (int) $sub->serial_number,
				'response'      => is_array( $response_data ) ? $response_data : array(),
				'status'        => $sub->status,
				'browser'       => $sub->browser,
				'device'        => $sub->device,
				'ip'            => $sub->ip,
				'city'          => $sub->city,
				'country'       => $sub->country,
				'user_id'       => $sub->user_id ? (int) $sub->user_id : null,
				'entry_details' => $details,
				'meta'          => $meta,
				'created_at'    => (string) $sub->created_at,
				'updated_at'    => (string) $sub->updated_at,
			);
		},
	) );

	// =========================================================================
	// ANALYTICS
	// =========================================================================

	$reg->read( 'fluent-forms/get-form-analytics', array(
		'label'       => 'Get Form Analytics',
		'description' => 'Get form analytics (views, conversions, partial entries) from the form analytics table.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id' ),
			'properties' => array(
				'form_id' => array( 'type' => 'integer', 'description' => 'Form ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'form_id'           => array( 'type' => 'integer' ),
			'form_title'        => array( 'type' => 'string' ),
			'total_views'       => array( 'type' => 'integer' ),
			'total_submissions' => array( 'type' => 'integer' ),
			'conversion_rate'   => array( 'type' => 'number' ),
			'by_source_type'    => array( 'type' => 'object' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read form analytics' );
			}

			$form_id = (int) $input['form_id'];
			if ( $form_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id must be a positive integer' );
			}

			global $wpdb;
			$forms_table     = $wpdb->prefix . 'fluentform_forms';
			$analytics_table = $wpdb->prefix . 'fluentform_form_analytics';
			$submissions_table = $wpdb->prefix . 'fluentform_submissions';

			// Verify the form exists.
			$form = $wpdb->get_row( $wpdb->prepare(
				"SELECT id, title FROM {$forms_table} WHERE id = %d",
				$form_id
			) );

			if ( ! $form ) {
				return fluent_abilities_error( 'not_found', 'Form not found' );
			}

			// Aggregate analytics by source_type.
			$analytics = $wpdb->get_results( $wpdb->prepare(
				"SELECT source_type, COUNT(*) AS count
				 FROM {$analytics_table}
				 WHERE form_id = %d
				 GROUP BY source_type",
				$form_id
			) );

			$summary = array();
			foreach ( $analytics as $row ) {
				$summary[ $row->source_type ] = (int) $row->count;
			}

			// Total views (all analytics entries typically represent views/interactions).
			$total_views = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$analytics_table} WHERE form_id = %d",
				$form_id
			) );

			// Total submissions (completed).
			$total_submissions = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$submissions_table} WHERE form_id = %d",
				$form_id
			) );

			// Conversion rate.
			$conversion_rate = $total_views > 0
				? round( ( $total_submissions / $total_views ) * 100, 2 )
				: 0;

			return array(
				'form_id'           => $form_id,
				'form_title'        => $form->title,
				'total_views'       => $total_views,
				'total_submissions' => $total_submissions,
				'conversion_rate'   => $conversion_rate,
				'by_source_type'    => $summary,
			);
		},
	) );

	// =========================================================================
	// FORM FIELDS
	// =========================================================================

	$reg->read( 'fluent-forms/list-form-fields', array(
		'label'       => 'List Form Fields',
		'description' => 'Get field definitions for a form. Returns the structured field configuration including labels, types, and validation rules.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id' ),
			'properties' => array(
				'form_id' => array( 'type' => 'integer', 'description' => 'Form ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'form_id'    => array( 'type' => 'integer' ),
			'form_title' => array( 'type' => 'string' ),
			'fields'     => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'raw'        => array( 'type' => 'object' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read form fields' );
			}

			$form_id = (int) $input['form_id'];
			if ( $form_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id must be a positive integer' );
			}

			global $wpdb;
			$forms_table = $wpdb->prefix . 'fluentform_forms';

			// Get the form record — form_fields is stored as JSON in the forms table.
			$form = $wpdb->get_row( $wpdb->prepare(
				"SELECT id, title, form_fields FROM {$forms_table} WHERE id = %d",
				$form_id
			) );

			if ( ! $form ) {
				return fluent_abilities_error( 'not_found', 'Form not found' );
			}

			$form_fields = json_decode( $form->form_fields, true );

			if ( ! $form_fields || ! is_array( $form_fields ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Could not parse form field definitions' );
			}

			// Extract a flat list of fields with key details.
			$fields = array();
			$raw_fields = $form_fields['fields'] ?? array();

			foreach ( $raw_fields as $field ) {
				$field_info = array(
					'element'    => $field['element'] ?? '',
					'name'       => $field['attributes']['name'] ?? '',
					'label'      => $field['settings']['label'] ?? '',
					'type'       => $field['attributes']['type'] ?? $field['element'] ?? '',
					'required'   => ! empty( $field['settings']['validation_rules']['required']['value'] ),
					'placeholder'=> $field['attributes']['placeholder'] ?? '',
				);

				// Include options for select/radio/checkbox fields.
				if ( ! empty( $field['settings']['advanced_options'] ) ) {
					$field_info['options'] = $field['settings']['advanced_options'];
				} elseif ( ! empty( $field['options'] ) ) {
					$field_info['options'] = $field['options'];
				}

				// Include container/column children.
				if ( ! empty( $field['columns'] ) ) {
					$field_info['columns'] = array();
					foreach ( $field['columns'] as $column ) {
						$col_fields = array();
						foreach ( ( $column['fields'] ?? array() ) as $col_field ) {
							$col_fields[] = array(
								'element'    => $col_field['element'] ?? '',
								'name'       => $col_field['attributes']['name'] ?? '',
								'label'      => $col_field['settings']['label'] ?? '',
								'type'       => $col_field['attributes']['type'] ?? $col_field['element'] ?? '',
								'required'   => ! empty( $col_field['settings']['validation_rules']['required']['value'] ),
							);
						}
						$field_info['columns'][] = $col_fields;
					}
				}

				$fields[] = $field_info;
			}

			return array(
				'form_id'    => $form_id,
				'form_title' => $form->title,
				'fields'     => $fields,
				'raw'        => $form_fields,
			);
		},
	) );

	$count = 6;
	error_log( "Abilities for Fluent: Registered {$count} Forms abilities" );

}, 100 );
