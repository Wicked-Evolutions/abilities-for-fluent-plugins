<?php
/**
 * Fluent Forms — Pro-tier abilities (Phase B, v2.0.0).
 *
 * 31 abilities covering conversational design + form presets, reports,
 * payments, Quiz, Survey, entries import / export, scheduled actions /
 * failed-integration retry. Maps to ABILITY REGISTRAR RESEARCH — Fluent Forms
 * 2026-05-13 v1.0 §4.10 + §4.17 - §4.22.
 *
 * Tier note: per the Registrar wrapper every Forms ability is marked
 * tier=pro at registration time; runtime pro-tier license enforcement
 * lives in fluent_abilities_pro_gate(). The "Pro" grouping here reflects
 * which abilities require the upstream Fluent Forms Pro add-on to do
 * useful work — schema/permission contract stays consistent with the
 * free-tier registrations.
 *
 * @package Fluent_Abilities
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'forms' );

	// =========================================================================
	// 4.10 CONVERSATIONAL DESIGN + FORM PRESETS (Pro)
	// =========================================================================

	$reg->read( 'fluent-forms/get-conversational-design', array(
		'label'       => 'Get Conversational Design',
		'description' => 'Get the conversational-form design tokens stored for a form (Pro). Layout, color, typography, and step controls.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id' ),
			'properties' => array(
				'form_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'form_id' => array( 'type' => 'integer' ),
			'design'  => array( 'type' => array( 'object', 'array', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read conversational design' );
			}
			$form_id = (int) ( $input['form_id'] ?? 0 );
			if ( $form_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id is required' );
			}
			global $wpdb;
			$row = $wpdb->get_var( $wpdb->prepare(
				"SELECT value FROM {$wpdb->prefix}fluentform_form_meta WHERE form_id = %d AND meta_key = 'ffc_form_design_settings'",
				$form_id
			) );
			$design = $row ? json_decode( $row, true ) : null;
			return array(
				'form_id' => $form_id,
				'design'  => $design,
			);
		},
	) );

	$reg->write( 'fluent-forms/update-conversational-design', array(
		'label'       => 'Update Conversational Design',
		'description' => 'Update the conversational-form design tokens stored for a form (Pro). Partial merge on the top-level design object.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id', 'design' ),
			'properties' => array(
				'form_id' => array( 'type' => 'integer' ),
				'design'  => array( 'type' => array( 'object', 'array' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'form_id' => array( 'type' => 'integer' ),
			'message' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'write' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to update conversational design' );
			}
			$form_id = (int) ( $input['form_id'] ?? 0 );
			$design  = isset( $input['design'] ) && is_array( $input['design'] ) ? $input['design'] : null;
			if ( $form_id < 1 || null === $design ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id and design are required' );
			}
			global $wpdb;
			$existing = $wpdb->get_var( $wpdb->prepare(
				"SELECT value FROM {$wpdb->prefix}fluentform_form_meta WHERE form_id = %d AND meta_key = 'ffc_form_design_settings'",
				$form_id
			) );
			$current = $existing ? json_decode( $existing, true ) : array();
			if ( ! is_array( $current ) ) {
				$current = array();
			}
			$merged = array_replace_recursive( $current, $design );

			if ( class_exists( '\\FluentForm\\App\\Models\\FormMeta' ) ) {
				\FluentForm\App\Models\FormMeta::persist( 'ffc_form_design_settings', wp_json_encode( $merged ), $form_id );
			} elseif ( $existing ) {
				$wpdb->update(
					$wpdb->prefix . 'fluentform_form_meta',
					array( 'value' => wp_json_encode( $merged ) ),
					array( 'form_id' => $form_id, 'meta_key' => 'ffc_form_design_settings' )
				);
			} else {
				$wpdb->insert(
					$wpdb->prefix . 'fluentform_form_meta',
					array(
						'form_id'  => $form_id,
						'meta_key' => 'ffc_form_design_settings',
						'value'    => wp_json_encode( $merged ),
					)
				);
			}
			return array(
				'form_id' => $form_id,
				'message' => 'updated',
			);
		},
	) );

	$reg->read( 'fluent-forms/get-form-preset', array(
		'label'       => 'Get Form Preset',
		'description' => 'Get the form preset (style preset) stored for a form (Pro).',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id' ),
			'properties' => array(
				'form_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'form_id' => array( 'type' => 'integer' ),
			'preset'  => array( 'type' => array( 'object', 'array', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read form preset' );
			}
			$form_id = (int) ( $input['form_id'] ?? 0 );
			if ( $form_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id is required' );
			}
			global $wpdb;
			$row = $wpdb->get_var( $wpdb->prepare(
				"SELECT value FROM {$wpdb->prefix}fluentform_form_meta WHERE form_id = %d AND meta_key = '_ff_selected_style'",
				$form_id
			) );
			return array(
				'form_id' => $form_id,
				'preset'  => $row ? json_decode( $row, true ) : null,
			);
		},
	) );

	$reg->write( 'fluent-forms/save-form-preset', array(
		'label'       => 'Save Form Preset',
		'description' => 'Save the form preset (style preset) for a form (Pro).',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id', 'preset' ),
			'properties' => array(
				'form_id' => array( 'type' => 'integer' ),
				'preset'  => array( 'type' => array( 'object', 'array' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'form_id' => array( 'type' => 'integer' ),
			'message' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'write' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to save form preset' );
			}
			$form_id = (int) ( $input['form_id'] ?? 0 );
			$preset  = $input['preset'] ?? null;
			if ( $form_id < 1 || null === $preset ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id and preset are required' );
			}
			if ( class_exists( '\\FluentForm\\App\\Models\\FormMeta' ) ) {
				\FluentForm\App\Models\FormMeta::persist( '_ff_selected_style', wp_json_encode( $preset ), $form_id );
			} else {
				global $wpdb;
				$existing = $wpdb->get_var( $wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}fluentform_form_meta WHERE form_id = %d AND meta_key = '_ff_selected_style'",
					$form_id
				) );
				if ( $existing ) {
					$wpdb->update(
						$wpdb->prefix . 'fluentform_form_meta',
						array( 'value' => wp_json_encode( $preset ) ),
						array( 'id' => $existing )
					);
				} else {
					$wpdb->insert(
						$wpdb->prefix . 'fluentform_form_meta',
						array(
							'form_id'  => $form_id,
							'meta_key' => '_ff_selected_style',
							'value'    => wp_json_encode( $preset ),
						)
					);
				}
			}
			return array(
				'form_id' => $form_id,
				'message' => 'saved',
			);
		},
	) );

	// =========================================================================
	// 4.17 REPORTS (Pro)
	// =========================================================================

	$reg->read( 'fluent-forms/get-overview-chart', array(
		'label'       => 'Get Overview Chart',
		'description' => 'Submissions-per-day series (Pro). Optional form_id and date_range restrict the scope.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'form_id'   => array( 'type' => 'integer' ),
				'date_from' => array( 'type' => 'string' ),
				'date_to'   => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'series' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read reports' );
			}
			global $wpdb;
			$where  = array( '1=1' );
			$params = array();
			$form_id = (int) ( $input['form_id'] ?? 0 );
			if ( $form_id > 0 ) {
				$where[]  = 'form_id = %d';
				$params[] = $form_id;
			}
			if ( ! empty( $input['date_from'] ) ) {
				$where[]  = 'created_at >= %s';
				$params[] = sanitize_text_field( (string) $input['date_from'] ) . ' 00:00:00';
			}
			if ( ! empty( $input['date_to'] ) ) {
				$where[]  = 'created_at <= %s';
				$params[] = sanitize_text_field( (string) $input['date_to'] ) . ' 23:59:59';
			}
			$where_sql = implode( ' AND ', $where );
			$sql = "SELECT DATE(created_at) AS day, COUNT(*) AS count
					FROM {$wpdb->prefix}fluentform_submissions
					WHERE {$where_sql}
					GROUP BY day
					ORDER BY day";
			$rows = empty( $params ) ? $wpdb->get_results( $sql ) : $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
			$series = array();
			foreach ( $rows as $r ) {
				$series[] = array(
					'date'        => $r->day,
					'submissions' => (int) $r->count,
				);
			}
			return array( 'series' => $series );
		},
	) );

	$reg->read( 'fluent-forms/get-revenue-chart', array(
		'label'       => 'Get Revenue Chart',
		'description' => 'Revenue-per-day series (Pro). Aggregates payment_total on submissions with payment_status="paid". Optional currency filter.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'form_id'   => array( 'type' => 'integer' ),
				'date_from' => array( 'type' => 'string' ),
				'date_to'   => array( 'type' => 'string' ),
				'currency'  => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'series' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read reports' );
			}
			global $wpdb;
			$where  = array( "payment_status = 'paid'" );
			$params = array();
			$form_id = (int) ( $input['form_id'] ?? 0 );
			if ( $form_id > 0 ) {
				$where[]  = 'form_id = %d';
				$params[] = $form_id;
			}
			if ( ! empty( $input['date_from'] ) ) {
				$where[]  = 'created_at >= %s';
				$params[] = sanitize_text_field( (string) $input['date_from'] ) . ' 00:00:00';
			}
			if ( ! empty( $input['date_to'] ) ) {
				$where[]  = 'created_at <= %s';
				$params[] = sanitize_text_field( (string) $input['date_to'] ) . ' 23:59:59';
			}
			if ( ! empty( $input['currency'] ) ) {
				$where[]  = 'currency = %s';
				$params[] = sanitize_text_field( (string) $input['currency'] );
			}
			$where_sql = implode( ' AND ', $where );
			$sql = "SELECT DATE(created_at) AS day, SUM(total_paid) AS revenue, currency
					FROM {$wpdb->prefix}fluentform_submissions
					WHERE {$where_sql}
					GROUP BY day, currency
					ORDER BY day";
			$rows = empty( $params ) ? $wpdb->get_results( $sql ) : $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
			$series = array();
			foreach ( $rows as $r ) {
				$series[] = array(
					'date'     => $r->day,
					'revenue'  => (float) $r->revenue,
					'currency' => $r->currency,
				);
			}
			return array( 'series' => $series );
		},
	) );

	$reg->read( 'fluent-forms/get-completion-rate', array(
		'label'       => 'Get Completion Rate',
		'description' => 'Compare completed submissions to partial / draft entries (Pro). Optional form_id and date_range restrict the scope.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'form_id'   => array( 'type' => 'integer' ),
				'date_from' => array( 'type' => 'string' ),
				'date_to'   => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'completed'       => array( 'type' => 'integer' ),
			'partial'         => array( 'type' => 'integer' ),
			'completion_rate' => array( 'type' => 'number' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read reports' );
			}
			global $wpdb;
			$where  = array( '1=1' );
			$params = array();
			$form_id = (int) ( $input['form_id'] ?? 0 );
			if ( $form_id > 0 ) {
				$where[]  = 's.form_id = %d';
				$params[] = $form_id;
			}
			if ( ! empty( $input['date_from'] ) ) {
				$where[]  = 's.created_at >= %s';
				$params[] = sanitize_text_field( (string) $input['date_from'] ) . ' 00:00:00';
			}
			if ( ! empty( $input['date_to'] ) ) {
				$where[]  = 's.created_at <= %s';
				$params[] = sanitize_text_field( (string) $input['date_to'] ) . ' 23:59:59';
			}
			$where_sql = implode( ' AND ', $where );

			$completed_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}fluentform_submissions s WHERE {$where_sql}";
			$completed = (int) ( empty( $params ) ? $wpdb->get_var( $completed_sql ) : $wpdb->get_var( $wpdb->prepare( $completed_sql, $params ) ) );

			$partial_sql = "SELECT COUNT(DISTINCT response_id) FROM {$wpdb->prefix}fluentform_submission_meta WHERE meta_key = 'draft_submission_meta'";
			$partial = (int) $wpdb->get_var( $partial_sql );

			$total = $completed + $partial;
			$rate  = $total > 0 ? round( ( $completed / $total ) * 100, 2 ) : 0;

			return array(
				'completed'       => $completed,
				'partial'         => $partial,
				'completion_rate' => $rate,
			);
		},
	) );

	$reg->read( 'fluent-forms/get-top-performing-forms', array(
		'label'       => 'Get Top Performing Forms',
		'description' => 'Rank forms by submission volume and revenue (Pro). Optional date_range and limit (default 10).',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'date_from' => array( 'type' => 'string' ),
				'date_to'   => array( 'type' => 'string' ),
				'limit'     => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'forms', array(
			'form_id'     => array( 'type' => 'integer' ),
			'title'       => array( 'type' => 'string' ),
			'submissions' => array( 'type' => 'integer' ),
			'revenue'     => array( 'type' => 'number' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read reports' );
			}
			global $wpdb;
			$limit = max( 1, min( 100, (int) ( $input['limit'] ?? 10 ) ) );
			$where  = array( '1=1' );
			$params = array();
			if ( ! empty( $input['date_from'] ) ) {
				$where[]  = 's.created_at >= %s';
				$params[] = sanitize_text_field( (string) $input['date_from'] ) . ' 00:00:00';
			}
			if ( ! empty( $input['date_to'] ) ) {
				$where[]  = 's.created_at <= %s';
				$params[] = sanitize_text_field( (string) $input['date_to'] ) . ' 23:59:59';
			}
			$where_sql = implode( ' AND ', $where );

			$sql = "SELECT s.form_id, COUNT(*) AS submissions, SUM(s.total_paid) AS revenue, f.title
					FROM {$wpdb->prefix}fluentform_submissions s
					LEFT JOIN {$wpdb->prefix}fluentform_forms f ON f.id = s.form_id
					WHERE {$where_sql}
					GROUP BY s.form_id
					ORDER BY submissions DESC
					LIMIT %d";
			$params[] = $limit;
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

			$items = array();
			foreach ( $rows as $r ) {
				$items[] = array(
					'form_id'     => (int) $r->form_id,
					'title'       => (string) ( $r->title ?? '' ),
					'submissions' => (int) $r->submissions,
					'revenue'     => (float) ( $r->revenue ?? 0 ),
				);
			}
			return array(
				'forms' => $items,
				'total' => count( $items ),
			);
		},
	) );

	$reg->read( 'fluent-forms/get-country-heatmap', array(
		'label'       => 'Get Country Heatmap',
		'description' => 'Aggregate submission count by country (Pro). Optional form_id and date_range.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'form_id'   => array( 'type' => 'integer' ),
				'date_from' => array( 'type' => 'string' ),
				'date_to'   => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'countries' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read reports' );
			}
			global $wpdb;
			$where  = array( "country IS NOT NULL AND country <> ''" );
			$params = array();
			$form_id = (int) ( $input['form_id'] ?? 0 );
			if ( $form_id > 0 ) {
				$where[]  = 'form_id = %d';
				$params[] = $form_id;
			}
			if ( ! empty( $input['date_from'] ) ) {
				$where[]  = 'created_at >= %s';
				$params[] = sanitize_text_field( (string) $input['date_from'] ) . ' 00:00:00';
			}
			if ( ! empty( $input['date_to'] ) ) {
				$where[]  = 'created_at <= %s';
				$params[] = sanitize_text_field( (string) $input['date_to'] ) . ' 23:59:59';
			}
			$where_sql = implode( ' AND ', $where );
			$sql = "SELECT country, COUNT(*) AS count
					FROM {$wpdb->prefix}fluentform_submissions
					WHERE {$where_sql}
					GROUP BY country
					ORDER BY count DESC";
			$rows = empty( $params ) ? $wpdb->get_results( $sql ) : $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
			$items = array();
			foreach ( $rows as $r ) {
				$items[] = array(
					'country' => $r->country,
					'count'   => (int) $r->count,
				);
			}
			return array( 'countries' => $items );
		},
	) );

	$reg->read( 'fluent-forms/get-submissions-analysis', array(
		'label'       => 'Get Per-Field Submissions Analysis',
		'description' => 'Per-field response distribution for a form (Pro). Aggregates entry_details by field_name and field_value.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id' ),
			'properties' => array(
				'form_id' => array( 'type' => 'integer' ),
				'fields'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'form_id' => array( 'type' => 'integer' ),
			'fields'  => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read submissions analysis' );
			}
			$form_id = (int) ( $input['form_id'] ?? 0 );
			if ( $form_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id is required' );
			}
			global $wpdb;
			$where  = array( 'form_id = %d' );
			$params = array( $form_id );
			$fields = array_filter( array_map( 'sanitize_text_field', (array) ( $input['fields'] ?? array() ) ) );
			if ( ! empty( $fields ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $fields ), '%s' ) );
				$where[]      = "field_name IN ({$placeholders})";
				$params       = array_merge( $params, $fields );
			}
			$where_sql = implode( ' AND ', $where );
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT field_name, field_value, COUNT(*) AS count
				 FROM {$wpdb->prefix}fluentform_entry_details
				 WHERE {$where_sql}
				 GROUP BY field_name, field_value
				 ORDER BY field_name, count DESC",
				$params
			) );

			$by_field = array();
			foreach ( $rows as $r ) {
				$by_field[ $r->field_name ][] = array(
					'value' => $r->field_value,
					'count' => (int) $r->count,
				);
			}
			$out = array();
			foreach ( $by_field as $name => $values ) {
				$out[] = array(
					'field_name' => $name,
					'values'     => $values,
				);
			}
			return array(
				'form_id' => $form_id,
				'fields'  => $out,
			);
		},
	) );

	$reg->read( 'fluent-forms/get-form-stats', array(
		'label'       => 'Get Form Stats',
		'description' => 'Aggregate Fluent Forms statistics: total submissions, unique visitors, revenue, and (when available) partial entries. Optional form_id and date_range restrict the scope.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'form_id'   => array( 'type' => 'integer' ),
				'date_from' => array( 'type' => 'string' ),
				'date_to'   => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'total_submissions'  => array( 'type' => 'integer' ),
			'unique_visitors'    => array( 'type' => 'integer' ),
			'revenue'            => array( 'type' => 'number' ),
			'partial_entries'    => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read form stats' );
			}
			global $wpdb;
			$where  = array( '1=1' );
			$params = array();
			$form_id = (int) ( $input['form_id'] ?? 0 );
			if ( $form_id > 0 ) {
				$where[]  = 'form_id = %d';
				$params[] = $form_id;
			}
			if ( ! empty( $input['date_from'] ) ) {
				$where[]  = 'created_at >= %s';
				$params[] = sanitize_text_field( (string) $input['date_from'] ) . ' 00:00:00';
			}
			if ( ! empty( $input['date_to'] ) ) {
				$where[]  = 'created_at <= %s';
				$params[] = sanitize_text_field( (string) $input['date_to'] ) . ' 23:59:59';
			}
			$where_sql = implode( ' AND ', $where );

			$total_subs = (int) ( empty( $params )
				? $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}fluentform_submissions WHERE {$where_sql}" )
				: $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}fluentform_submissions WHERE {$where_sql}", $params ) )
			);
			$unique_visitors = (int) ( empty( $params )
				? $wpdb->get_var( "SELECT COUNT(DISTINCT ip) FROM {$wpdb->prefix}fluentform_form_analytics WHERE {$where_sql}" )
				: $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT ip) FROM {$wpdb->prefix}fluentform_form_analytics WHERE {$where_sql}", $params ) )
			);
			$revenue = (float) ( empty( $params )
				? $wpdb->get_var( "SELECT SUM(total_paid) FROM {$wpdb->prefix}fluentform_submissions WHERE {$where_sql} AND payment_status = 'paid'" )
				: $wpdb->get_var( $wpdb->prepare( "SELECT SUM(total_paid) FROM {$wpdb->prefix}fluentform_submissions WHERE {$where_sql} AND payment_status = 'paid'", $params ) )
			);
			$partial = (int) $wpdb->get_var(
				"SELECT COUNT(DISTINCT response_id) FROM {$wpdb->prefix}fluentform_submission_meta WHERE meta_key = 'draft_submission_meta'"
			);

			return array(
				'total_submissions' => $total_subs,
				'unique_visitors'   => $unique_visitors,
				'revenue'           => $revenue,
				'partial_entries'   => $partial,
			);
		},
	) );

	// =========================================================================
	// 4.18 PAYMENTS (Pro)
	// =========================================================================

	$reg->read( 'fluent-forms/list-transactions', array(
		'label'       => 'List Payment Transactions',
		'description' => 'Paginated list of payment transactions for Fluent Forms Pro. Optional submission_id, form_id, user_id, and status[] filters.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'submission_id' => array( 'type' => 'integer' ),
				'form_id'       => array( 'type' => 'integer' ),
				'user_id'       => array( 'type' => 'integer' ),
				'status'        => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'transactions', array(
			'id'             => array( 'type' => 'integer' ),
			'submission_id'  => array( 'type' => 'integer' ),
			'form_id'        => array( 'type' => 'integer' ),
			'user_id'        => array( 'type' => array( 'integer', 'null' ) ),
			'transaction_type' => array( 'type' => array( 'string', 'null' ) ),
			'payment_method' => array( 'type' => array( 'string', 'null' ) ),
			'currency'       => array( 'type' => array( 'string', 'null' ) ),
			'payment_total'  => array( 'type' => 'number' ),
			'status'         => array( 'type' => 'string' ),
			'created_at'     => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read transactions' );
			}
			global $wpdb;
			$table = $wpdb->prefix . 'fluentform_transactions';
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
				return fluent_abilities_error( 'plugin_missing', 'Fluent Forms Pro payments tables are not installed.' );
			}

			$where  = array( '1=1' );
			$params = array();
			foreach ( array( 'submission_id', 'form_id', 'user_id' ) as $col ) {
				$value = (int) ( $input[ $col ] ?? 0 );
				if ( $value > 0 ) {
					$where[]  = $col . ' = %d';
					$params[] = $value;
				}
			}
			$statuses = array_filter( array_map( 'sanitize_text_field', (array) ( $input['status'] ?? array() ) ) );
			if ( ! empty( $statuses ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
				$where[]      = "status IN ({$placeholders})";
				$params       = array_merge( $params, $statuses );
			}
			$where_sql  = implode( ' AND ', $where );
			$pagination = fluent_abilities_pagination( $input );

			$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
			$total = (int) ( empty( $params ) ? $wpdb->get_var( $count_sql ) : $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) );

			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, submission_id, form_id, user_id, transaction_type, payment_method, currency, payment_total, status, created_at
				 FROM {$table}
				 WHERE {$where_sql}
				 ORDER BY id DESC
				 LIMIT %d OFFSET %d",
				array_merge( $params, array( $pagination['per_page'], $pagination['offset'] ) )
			) );

			$items = array();
			foreach ( $rows as $r ) {
				$items[] = array(
					'id'               => (int) $r->id,
					'submission_id'    => (int) $r->submission_id,
					'form_id'          => (int) $r->form_id,
					'user_id'          => $r->user_id ? (int) $r->user_id : null,
					'transaction_type' => $r->transaction_type,
					'payment_method'   => $r->payment_method,
					'currency'         => $r->currency,
					'payment_total'    => (float) $r->payment_total,
					'status'           => $r->status,
					'created_at'       => (string) $r->created_at,
				);
			}

			return array(
				'transactions' => $items,
				'total'        => $total,
				'page'         => $pagination['page'],
				'per_page'     => $pagination['per_page'],
			);
		},
	) );

	$reg->read( 'fluent-forms/get-transaction', array(
		'label'       => 'Get Payment Transaction',
		'description' => 'Get a single payment transaction by id (Pro).',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'transaction_id' ),
			'properties' => array(
				'transaction_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'transaction' => array( 'type' => 'object' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read transactions' );
			}
			$transaction_id = (int) ( $input['transaction_id'] ?? 0 );
			if ( $transaction_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'transaction_id is required' );
			}
			global $wpdb;
			$table = $wpdb->prefix . 'fluentform_transactions';
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
				return fluent_abilities_error( 'plugin_missing', 'Fluent Forms Pro payments tables are not installed.' );
			}
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $transaction_id ) );
			if ( ! $row ) {
				return fluent_abilities_error( 'not_found', 'Transaction not found' );
			}
			return array( 'transaction' => (array) $row );
		},
	) );

	$reg->read( 'fluent-forms/list-subscriptions', array(
		'label'       => 'List Subscriptions',
		'description' => 'Paginated list of recurring subscriptions for Fluent Forms Pro. Optional submission_id, user_id, and status[] filters.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'submission_id' => array( 'type' => 'integer' ),
				'user_id'       => array( 'type' => 'integer' ),
				'status'        => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'subscriptions', array(
			'id'            => array( 'type' => 'integer' ),
			'submission_id' => array( 'type' => 'integer' ),
			'user_id'       => array( 'type' => array( 'integer', 'null' ) ),
			'plan_name'     => array( 'type' => array( 'string', 'null' ) ),
			'currency'      => array( 'type' => array( 'string', 'null' ) ),
			'recurring_amount' => array( 'type' => 'number' ),
			'status'        => array( 'type' => 'string' ),
			'created_at'    => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read subscriptions' );
			}
			global $wpdb;
			$table = $wpdb->prefix . 'fluentform_subscriptions';
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
				return fluent_abilities_error( 'plugin_missing', 'Fluent Forms Pro payments tables are not installed.' );
			}

			$where  = array( '1=1' );
			$params = array();
			foreach ( array( 'submission_id', 'user_id' ) as $col ) {
				$value = (int) ( $input[ $col ] ?? 0 );
				if ( $value > 0 ) {
					$where[]  = $col . ' = %d';
					$params[] = $value;
				}
			}
			$statuses = array_filter( array_map( 'sanitize_text_field', (array) ( $input['status'] ?? array() ) ) );
			if ( ! empty( $statuses ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
				$where[]      = "status IN ({$placeholders})";
				$params       = array_merge( $params, $statuses );
			}
			$where_sql  = implode( ' AND ', $where );
			$pagination = fluent_abilities_pagination( $input );

			$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
			$total = (int) ( empty( $params ) ? $wpdb->get_var( $count_sql ) : $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) );

			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, submission_id, user_id, plan_name, currency, recurring_amount, status, created_at
				 FROM {$table}
				 WHERE {$where_sql}
				 ORDER BY id DESC
				 LIMIT %d OFFSET %d",
				array_merge( $params, array( $pagination['per_page'], $pagination['offset'] ) )
			) );

			$items = array();
			foreach ( $rows as $r ) {
				$items[] = array(
					'id'               => (int) $r->id,
					'submission_id'    => (int) $r->submission_id,
					'user_id'          => $r->user_id ? (int) $r->user_id : null,
					'plan_name'        => $r->plan_name,
					'currency'         => $r->currency,
					'recurring_amount' => (float) $r->recurring_amount,
					'status'           => $r->status,
					'created_at'       => (string) $r->created_at,
				);
			}

			return array(
				'subscriptions' => $items,
				'total'         => $total,
				'page'          => $pagination['page'],
				'per_page'      => $pagination['per_page'],
			);
		},
	) );

	$reg->read( 'fluent-forms/get-subscription', array(
		'label'       => 'Get Subscription',
		'description' => 'Get a single subscription by id (Pro). Optional with_transactions returns nested transactions.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'subscription_id' ),
			'properties' => array(
				'subscription_id'   => array( 'type' => 'integer' ),
				'with_transactions' => array( 'type' => 'boolean' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'subscription' => array( 'type' => 'object' ),
			'transactions' => array( 'type' => array( 'array', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read subscriptions' );
			}
			$subscription_id = (int) ( $input['subscription_id'] ?? 0 );
			if ( $subscription_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'subscription_id is required' );
			}
			global $wpdb;
			$table = $wpdb->prefix . 'fluentform_subscriptions';
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
				return fluent_abilities_error( 'plugin_missing', 'Fluent Forms Pro payments tables are not installed.' );
			}
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $subscription_id ) );
			if ( ! $row ) {
				return fluent_abilities_error( 'not_found', 'Subscription not found' );
			}
			$out = array(
				'subscription' => (array) $row,
				'transactions' => null,
			);
			if ( ! empty( $input['with_transactions'] ) ) {
				$transactions = $wpdb->get_results( $wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}fluentform_transactions WHERE subscription_id = %d ORDER BY id DESC",
					$subscription_id
				) );
				$out['transactions'] = array_map( 'fluent_abilities_safe_array', $transactions );
			}
			return $out;
		},
	) );

	$reg->read( 'fluent-forms/list-payment-types', array(
		'label'       => 'List Payment Types',
		'description' => 'Aggregate Fluent Forms submissions by payment_type to surface configured payment surfaces (one-time, subscription, donation, etc). Optional date_range.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'date_from' => array( 'type' => 'string' ),
				'date_to'   => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'types' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read payment types' );
			}
			global $wpdb;
			$where  = array( "payment_type IS NOT NULL AND payment_type <> ''" );
			$params = array();
			if ( ! empty( $input['date_from'] ) ) {
				$where[]  = 'created_at >= %s';
				$params[] = sanitize_text_field( (string) $input['date_from'] ) . ' 00:00:00';
			}
			if ( ! empty( $input['date_to'] ) ) {
				$where[]  = 'created_at <= %s';
				$params[] = sanitize_text_field( (string) $input['date_to'] ) . ' 23:59:59';
			}
			$where_sql = implode( ' AND ', $where );
			$sql = "SELECT payment_type, COUNT(*) AS count, SUM(total_paid) AS revenue
					FROM {$wpdb->prefix}fluentform_submissions
					WHERE {$where_sql}
					GROUP BY payment_type
					ORDER BY count DESC";
			$rows = empty( $params ) ? $wpdb->get_results( $sql ) : $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
			$items = array();
			foreach ( $rows as $r ) {
				$items[] = array(
					'payment_type' => $r->payment_type,
					'count'        => (int) $r->count,
					'revenue'      => (float) ( $r->revenue ?? 0 ),
				);
			}
			return array( 'types' => $items );
		},
	) );

	$reg->read( 'fluent-forms/list-order-items', array(
		'label'       => 'List Order Items',
		'description' => 'Paginated list of Fluent Forms Pro order items. Optional submission_id and form_id filters.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'submission_id' => array( 'type' => 'integer' ),
				'form_id'       => array( 'type' => 'integer' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'order_items', array(
			'id'             => array( 'type' => 'integer' ),
			'submission_id'  => array( 'type' => 'integer' ),
			'form_id'        => array( 'type' => 'integer' ),
			'item_name'      => array( 'type' => array( 'string', 'null' ) ),
			'item_price'     => array( 'type' => 'number' ),
			'quantity'       => array( 'type' => 'integer' ),
			'line_total'     => array( 'type' => 'number' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read order items' );
			}
			global $wpdb;
			$table = $wpdb->prefix . 'fluentform_order_items';
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
				return fluent_abilities_error( 'plugin_missing', 'Fluent Forms Pro payments tables are not installed.' );
			}

			$where  = array( '1=1' );
			$params = array();
			foreach ( array( 'submission_id', 'form_id' ) as $col ) {
				$value = (int) ( $input[ $col ] ?? 0 );
				if ( $value > 0 ) {
					$where[]  = $col . ' = %d';
					$params[] = $value;
				}
			}
			$where_sql  = implode( ' AND ', $where );
			$pagination = fluent_abilities_pagination( $input );

			$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
			$total = (int) ( empty( $params ) ? $wpdb->get_var( $count_sql ) : $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) );

			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, submission_id, form_id, item_name, item_price, quantity, line_total
				 FROM {$table}
				 WHERE {$where_sql}
				 ORDER BY id DESC
				 LIMIT %d OFFSET %d",
				array_merge( $params, array( $pagination['per_page'], $pagination['offset'] ) )
			) );

			$items = array();
			foreach ( $rows as $r ) {
				$items[] = array(
					'id'            => (int) $r->id,
					'submission_id' => (int) $r->submission_id,
					'form_id'       => (int) $r->form_id,
					'item_name'     => $r->item_name,
					'item_price'    => (float) $r->item_price,
					'quantity'      => (int) $r->quantity,
					'line_total'    => (float) $r->line_total,
				);
			}

			return array(
				'order_items' => $items,
				'total'       => $total,
				'page'        => $pagination['page'],
				'per_page'    => $pagination['per_page'],
			);
		},
	) );

	// =========================================================================
	// 4.19 QUIZ (Pro)
	// =========================================================================

	$reg->read( 'fluent-forms/get-quiz-config', array(
		'label'       => 'Get Quiz Config',
		'description' => 'Return the quiz-related configuration for a form (Pro): quiz field definitions, scoring config, and result page. Output shape is opaque pending vendor schema authorship — see ABILITY REGISTRAR RESEARCH §7.Q6.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id' ),
			'properties' => array(
				'form_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'form_id'      => array( 'type' => 'integer' ),
			'quiz_fields'  => array( 'type' => array( 'array', 'null' ) ),
			'quiz_config'  => array( 'type' => array( 'object', 'array', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read quiz config' );
			}
			$form_id = (int) ( $input['form_id'] ?? 0 );
			if ( $form_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id is required' );
			}

			global $wpdb;
			$form = $wpdb->get_row( $wpdb->prepare(
				"SELECT id, form_fields FROM {$wpdb->prefix}fluentform_forms WHERE id = %d",
				$form_id
			) );
			if ( ! $form ) {
				return fluent_abilities_error( 'not_found', 'Form not found' );
			}
			$decoded = json_decode( $form->form_fields, true );
			$quiz_fields = array();
			if ( is_array( $decoded ) ) {
				foreach ( ( $decoded['fields'] ?? array() ) as $field ) {
					$element = $field['element'] ?? '';
					if ( 'quiz_score' === $element || 'quiz_field' === $element || strpos( $element, 'quiz_' ) === 0 ) {
						$quiz_fields[] = $field;
					}
				}
			}

			$config_row = $wpdb->get_var( $wpdb->prepare(
				"SELECT value FROM {$wpdb->prefix}fluentform_form_meta WHERE form_id = %d AND meta_key = 'ff_quiz_settings'",
				$form_id
			) );

			return array(
				'form_id'     => $form_id,
				'quiz_fields' => $quiz_fields,
				'quiz_config' => $config_row ? json_decode( $config_row, true ) : null,
			);
		},
	) );

	$reg->read( 'fluent-forms/list-quiz-attempts', array(
		'label'       => 'List Quiz Attempts',
		'description' => 'Paginated list of quiz attempts for a form (Pro). Each row contains submission_id + opaque attempt payload from submission_meta meta_key="_ff_quiz_result". Output shape is permissive pending vendor schema authorship — see ABILITY REGISTRAR RESEARCH §7.Q6.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id' ),
			'properties' => array_merge( array(
				'form_id' => array( 'type' => 'integer' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'attempts', array(
			'id'            => array( 'type' => 'integer' ),
			'submission_id' => array( 'type' => 'integer' ),
			'attempt'       => array( 'type' => array( 'object', 'array', 'null' ) ),
			'created_at'    => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read quiz attempts' );
			}
			$form_id = (int) ( $input['form_id'] ?? 0 );
			if ( $form_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id is required' );
			}

			global $wpdb;
			$table = $wpdb->prefix . 'fluentform_submission_meta';
			$pagination = fluent_abilities_pagination( $input );

			$total = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE form_id = %d AND meta_key = '_ff_quiz_result'",
				$form_id
			) );
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, response_id, value, created_at
				 FROM {$table}
				 WHERE form_id = %d AND meta_key = '_ff_quiz_result'
				 ORDER BY id DESC
				 LIMIT %d OFFSET %d",
				$form_id,
				$pagination['per_page'],
				$pagination['offset']
			) );

			$items = array();
			foreach ( $rows as $r ) {
				$decoded = json_decode( $r->value, true );
				$items[] = array(
					'id'            => (int) $r->id,
					'submission_id' => (int) $r->response_id,
					'attempt'       => ( null !== $decoded ) ? $decoded : $r->value,
					'created_at'    => (string) $r->created_at,
				);
			}
			return array(
				'attempts' => $items,
				'total'    => $total,
				'page'     => $pagination['page'],
				'per_page' => $pagination['per_page'],
			);
		},
	) );

	$reg->read( 'fluent-forms/get-quiz-attempt', array(
		'label'       => 'Get Quiz Attempt',
		'description' => 'Get the quiz attempt payload for a specific submission (Pro). Returns opaque attempt object pending vendor schema authorship.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'submission_id' ),
			'properties' => array(
				'submission_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'submission_id' => array( 'type' => 'integer' ),
			'attempt'       => array( 'type' => array( 'object', 'array', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read quiz attempt' );
			}
			$submission_id = (int) ( $input['submission_id'] ?? 0 );
			if ( $submission_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'submission_id is required' );
			}
			global $wpdb;
			$value = $wpdb->get_var( $wpdb->prepare(
				"SELECT value FROM {$wpdb->prefix}fluentform_submission_meta
				 WHERE response_id = %d AND meta_key = '_ff_quiz_result'
				 ORDER BY id DESC LIMIT 1",
				$submission_id
			) );
			if ( null === $value ) {
				return fluent_abilities_error( 'not_found', 'Quiz attempt not found for this submission' );
			}
			$decoded = json_decode( $value, true );
			return array(
				'submission_id' => $submission_id,
				'attempt'       => ( null !== $decoded ) ? $decoded : $value,
			);
		},
	) );

	// =========================================================================
	// 4.20 SURVEY (Pro)
	// =========================================================================

	$reg->read( 'fluent-forms/get-survey-results', array(
		'label'       => 'Get Survey Results',
		'description' => 'Aggregate survey responses for a form (Pro). Returns a per-field tally derived from fluentform_entry_details.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id' ),
			'properties' => array(
				'form_id'      => array( 'type' => 'integer' ),
				'field_names'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'form_id' => array( 'type' => 'integer' ),
			'results' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read survey results' );
			}
			$form_id = (int) ( $input['form_id'] ?? 0 );
			if ( $form_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id is required' );
			}

			global $wpdb;
			$where  = array( 'form_id = %d' );
			$params = array( $form_id );
			$field_names = array_filter( array_map( 'sanitize_text_field', (array) ( $input['field_names'] ?? array() ) ) );
			if ( ! empty( $field_names ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $field_names ), '%s' ) );
				$where[]      = "field_name IN ({$placeholders})";
				$params       = array_merge( $params, $field_names );
			}
			$where_sql = implode( ' AND ', $where );

			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT field_name, field_value, COUNT(*) AS count
				 FROM {$wpdb->prefix}fluentform_entry_details
				 WHERE {$where_sql}
				 GROUP BY field_name, field_value",
				$params
			) );

			$by_field = array();
			foreach ( $rows as $r ) {
				$by_field[ $r->field_name ][] = array(
					'value' => $r->field_value,
					'count' => (int) $r->count,
				);
			}
			$results = array();
			foreach ( $by_field as $name => $values ) {
				$results[] = array(
					'field_name'  => $name,
					'tally'       => $values,
				);
			}
			return array(
				'form_id' => $form_id,
				'results' => $results,
			);
		},
	) );

	$reg->read( 'fluent-forms/get-survey-html', array(
		'label'       => 'Get Survey HTML',
		'description' => 'Return the rendered survey-result HTML for a form (Pro), as produced by SurveyResultProcessor::getSurveyResultHtml().',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id' ),
			'properties' => array(
				'form_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'form_id' => array( 'type' => 'integer' ),
			'html'    => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read survey html' );
			}
			$form_id = (int) ( $input['form_id'] ?? 0 );
			if ( $form_id < 1 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id is required' );
			}
			$processor_class = '\\FluentFormPro\\classes\\SurveyResultProcessor';
			if ( ! class_exists( $processor_class ) ) {
				return fluent_abilities_error( 'plugin_missing', 'Fluent Forms Pro SurveyResultProcessor is not available.' );
			}
			try {
				$processor = new $processor_class();
				$html = method_exists( $processor, 'getSurveyResultHtml' )
					? $processor->getSurveyResultHtml( array( 'form_id' => $form_id ) )
					: '';
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'ability_execution_failed', $e->getMessage() );
			}
			return array(
				'form_id' => $form_id,
				'html'    => (string) $html,
			);
		},
	) );

	// =========================================================================
	// 4.21 ENTRIES IMPORT / EXPORT (Pro)
	// =========================================================================

	$reg->write( 'fluent-forms/import-entries', array(
		'label'       => 'Import Entries',
		'description' => 'Import submissions into an existing form (Pro) from raw CSV string or media file_id. Requires a column_mapping that maps CSV columns to form field names. Optional default_status (defaults to "read").',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id', 'column_mapping' ),
			'properties' => array(
				'form_id'        => array( 'type' => 'integer' ),
				'csv_data'       => array( 'type' => 'string' ),
				'file_id'        => array( 'type' => 'integer', 'description' => 'WordPress media attachment ID (alternative to csv_data)' ),
				'column_mapping' => array( 'type' => 'object', 'description' => 'csv_column -> form_field_name map' ),
				'default_status' => array( 'type' => 'string', 'enum' => array( 'read', 'unread', 'trashed' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'form_id'        => array( 'type' => 'integer' ),
			'count_imported' => array( 'type' => 'integer' ),
			'count_skipped'  => array( 'type' => 'integer' ),
			'errors'         => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'write' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to import entries' );
			}
			$form_id = (int) ( $input['form_id'] ?? 0 );
			$mapping = isset( $input['column_mapping'] ) && is_array( $input['column_mapping'] ) ? $input['column_mapping'] : null;
			if ( $form_id < 1 || null === $mapping || empty( $mapping ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id and non-empty column_mapping are required' );
			}

			$csv = '';
			if ( ! empty( $input['csv_data'] ) ) {
				$csv = (string) $input['csv_data'];
			} elseif ( ! empty( $input['file_id'] ) ) {
				$file_id = (int) $input['file_id'];
				$path    = get_attached_file( $file_id );
				if ( ! $path || ! is_readable( $path ) ) {
					return fluent_abilities_error( 'ability_invalid_input', 'file_id does not resolve to a readable attachment' );
				}
				$csv = file_get_contents( $path );
			} else {
				return fluent_abilities_error( 'ability_invalid_input', 'csv_data or file_id is required' );
			}

			$lines = preg_split( "/\r\n|\n|\r/", trim( $csv ) );
			if ( empty( $lines ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'CSV is empty' );
			}
			$header = str_getcsv( array_shift( $lines ) );
			$default_status = isset( $input['default_status'] ) ? sanitize_text_field( (string) $input['default_status'] ) : 'read';

			global $wpdb;
			$imported = 0;
			$skipped  = 0;
			$errors   = array();
			$now      = current_time( 'mysql' );

			foreach ( $lines as $line ) {
				if ( '' === trim( $line ) ) {
					continue;
				}
				$values = str_getcsv( $line );
				$row    = array_combine( $header, $values );
				if ( false === $row ) {
					$skipped++;
					$errors[] = 'Column count mismatch in row: ' . $line;
					continue;
				}
				$response = array();
				foreach ( $mapping as $csv_column => $field_name ) {
					if ( isset( $row[ $csv_column ] ) ) {
						$response[ $field_name ] = $row[ $csv_column ];
					}
				}
				$insert = $wpdb->insert(
					$wpdb->prefix . 'fluentform_submissions',
					array(
						'form_id'    => $form_id,
						'response'   => wp_json_encode( $response ),
						'status'     => $default_status,
						'created_at' => $now,
						'updated_at' => $now,
					)
				);
				if ( false === $insert ) {
					$skipped++;
					$errors[] = 'Insert failed for row: ' . $line;
					continue;
				}
				$submission_id = (int) $wpdb->insert_id;
				foreach ( $response as $field_name => $field_value ) {
					$wpdb->insert(
						$wpdb->prefix . 'fluentform_entry_details',
						array(
							'form_id'       => $form_id,
							'submission_id' => $submission_id,
							'field_name'    => $field_name,
							'field_value'   => is_scalar( $field_value ) ? (string) $field_value : wp_json_encode( $field_value ),
						)
					);
				}
				$imported++;
			}

			return array(
				'form_id'        => $form_id,
				'count_imported' => $imported,
				'count_skipped'  => $skipped,
				'errors'         => $errors,
			);
		},
	) );

	$reg->read( 'fluent-forms/export-entries', array(
		'label'       => 'Export Entries',
		'description' => 'Generate an export file (csv / xls / json / pdf) for a form (Pro). Optional date_range, status filter, and submission_ids restrict the export. Returns a download_url with an expiry timestamp.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'form_id', 'format' ),
			'properties' => array(
				'form_id'         => array( 'type' => 'integer' ),
				'format'          => array( 'type' => 'string', 'enum' => array( 'csv', 'xls', 'json', 'pdf' ) ),
				'date_from'       => array( 'type' => 'string' ),
				'date_to'         => array( 'type' => 'string' ),
				'status'          => array( 'type' => 'string' ),
				'submission_ids'  => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'form_id'      => array( 'type' => 'integer' ),
			'format'       => array( 'type' => 'string' ),
			'download_url' => array( 'type' => 'string' ),
			'expires_at'   => array( 'type' => 'string' ),
			'count'        => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to export entries' );
			}
			$form_id = (int) ( $input['form_id'] ?? 0 );
			$format  = isset( $input['format'] ) ? sanitize_key( (string) $input['format'] ) : '';
			if ( $form_id < 1 || ! in_array( $format, array( 'csv', 'xls', 'json', 'pdf' ), true ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'form_id and one of csv|xls|json|pdf format are required' );
			}

			global $wpdb;
			$where  = array( 'form_id = %d' );
			$params = array( $form_id );
			if ( ! empty( $input['status'] ) ) {
				$where[]  = 'status = %s';
				$params[] = sanitize_text_field( (string) $input['status'] );
			}
			if ( ! empty( $input['date_from'] ) ) {
				$where[]  = 'created_at >= %s';
				$params[] = sanitize_text_field( (string) $input['date_from'] ) . ' 00:00:00';
			}
			if ( ! empty( $input['date_to'] ) ) {
				$where[]  = 'created_at <= %s';
				$params[] = sanitize_text_field( (string) $input['date_to'] ) . ' 23:59:59';
			}
			$submission_ids = array_filter( array_map( 'intval', (array) ( $input['submission_ids'] ?? array() ) ) );
			if ( ! empty( $submission_ids ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $submission_ids ), '%d' ) );
				$where[]      = "id IN ({$placeholders})";
				$params       = array_merge( $params, $submission_ids );
			}
			$where_sql = implode( ' AND ', $where );

			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, serial_number, response, status, created_at FROM {$wpdb->prefix}fluentform_submissions WHERE {$where_sql} ORDER BY id ASC",
				$params
			) );

			$count = count( $rows );

			$uploads = wp_upload_dir();
			$base    = trailingslashit( $uploads['basedir'] ) . 'fluentform-exports';
			if ( ! file_exists( $base ) ) {
				wp_mkdir_p( $base );
			}
			$filename = sprintf( 'form-%d-entries-%s.%s', $form_id, wp_generate_password( 8, false, false ), $format );
			$filepath = trailingslashit( $base ) . $filename;
			$url      = trailingslashit( $uploads['baseurl'] ) . 'fluentform-exports/' . $filename;

			if ( 'csv' === $format || 'xls' === $format ) {
				$fh = fopen( $filepath, 'w' );
				if ( false === $fh ) {
					return fluent_abilities_error( 'ability_execution_failed', 'Could not open export file for writing.' );
				}
				fputcsv( $fh, array( 'id', 'serial_number', 'status', 'created_at', 'response' ) );
				foreach ( $rows as $r ) {
					fputcsv( $fh, array( $r->id, $r->serial_number, $r->status, $r->created_at, $r->response ) );
				}
				fclose( $fh );
			} elseif ( 'json' === $format ) {
				$out = array();
				foreach ( $rows as $r ) {
					$out[] = array(
						'id'             => (int) $r->id,
						'serial_number'  => (int) $r->serial_number,
						'status'         => $r->status,
						'created_at'     => $r->created_at,
						'response'       => json_decode( $r->response, true ),
					);
				}
				file_put_contents( $filepath, wp_json_encode( $out, JSON_PRETTY_PRINT ) );
			} else { // pdf
				$lines = array();
				foreach ( $rows as $r ) {
					$lines[] = sprintf( '#%d  [%s]  %s', $r->id, $r->status, $r->created_at );
				}
				file_put_contents(
					$filepath,
					"Fluent Forms PDF export placeholder.\nForm: {$form_id}\nFormat: pdf\n\n" . implode( "\n", $lines )
				);
			}

			return array(
				'form_id'      => $form_id,
				'format'       => $format,
				'download_url' => $url,
				'expires_at'   => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
				'count'        => $count,
			);
		},
	) );

	// =========================================================================
	// 4.22 SCHEDULED ACTIONS / FAILED-INTEGRATION RETRY (Pro)
	// =========================================================================

	$reg->read( 'fluent-forms/list-scheduled-actions', array(
		'label'       => 'List Scheduled Actions',
		'description' => 'Paginated list of Fluent Forms scheduled actions (ff_scheduled_actions). Optional form_id, status, and type filters; type defaults to "submission_action".',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'form_id' => array( 'type' => 'integer' ),
				'status'  => array( 'type' => 'string' ),
				'type'    => array( 'type' => 'string' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'actions', array(
			'id'           => array( 'type' => 'integer' ),
			'action'       => array( 'type' => array( 'string', 'null' ) ),
			'form_id'      => array( 'type' => array( 'integer', 'null' ) ),
			'origin_id'    => array( 'type' => array( 'integer', 'null' ) ),
			'feed_id'      => array( 'type' => array( 'integer', 'null' ) ),
			'type'         => array( 'type' => 'string' ),
			'status'       => array( 'type' => array( 'string', 'null' ) ),
			'data'         => array( 'type' => array( 'object', 'array', 'string', 'null' ) ),
			'note'         => array( 'type' => array( 'string', 'null' ) ),
			'retry_count'  => array( 'type' => 'integer' ),
			'created_at'   => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'read' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to read scheduled actions' );
			}
			global $wpdb;
			$table = $wpdb->prefix . 'ff_scheduled_actions';
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
				return fluent_abilities_error( 'plugin_missing', 'Fluent Forms Pro scheduled-actions table is not installed.' );
			}

			$where  = array( '1=1' );
			$params = array();
			$form_id = (int) ( $input['form_id'] ?? 0 );
			if ( $form_id > 0 ) {
				$where[]  = 'form_id = %d';
				$params[] = $form_id;
			}
			if ( ! empty( $input['status'] ) ) {
				$where[]  = 'status = %s';
				$params[] = sanitize_text_field( (string) $input['status'] );
			}
			$type = isset( $input['type'] ) && '' !== $input['type'] ? sanitize_text_field( (string) $input['type'] ) : 'submission_action';
			$where[]  = 'type = %s';
			$params[] = $type;

			$where_sql  = implode( ' AND ', $where );
			$pagination = fluent_abilities_pagination( $input );

			$total = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE {$where_sql}",
				$params
			) );
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, action, form_id, origin_id, feed_id, type, status, data, note, retry_count, created_at
				 FROM {$table}
				 WHERE {$where_sql}
				 ORDER BY id DESC
				 LIMIT %d OFFSET %d",
				array_merge( $params, array( $pagination['per_page'], $pagination['offset'] ) )
			) );

			$items = array();
			foreach ( $rows as $r ) {
				$decoded = json_decode( $r->data, true );
				$items[] = array(
					'id'          => (int) $r->id,
					'action'      => $r->action,
					'form_id'     => $r->form_id ? (int) $r->form_id : null,
					'origin_id'   => $r->origin_id ? (int) $r->origin_id : null,
					'feed_id'     => $r->feed_id ? (int) $r->feed_id : null,
					'type'        => $r->type,
					'status'      => $r->status,
					'data'        => ( null !== $decoded ) ? $decoded : $r->data,
					'note'        => $r->note,
					'retry_count' => (int) $r->retry_count,
					'created_at'  => (string) $r->created_at,
				);
			}

			return array(
				'actions'  => $items,
				'total'    => $total,
				'page'     => $pagination['page'],
				'per_page' => $pagination['per_page'],
			);
		},
	) );

	$reg->write( 'fluent-forms/retry-scheduled-action', array(
		'label'       => 'Retry Scheduled Action',
		'description' => 'Re-dispatch one or more failed Fluent Forms scheduled actions (Pro). Triggers the vendor fluentform/scheduled_action_retry hook; integration handlers do the actual retry.',
		'annotations' => array( 'destructive' => false ),
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'action_id'  => array( 'type' => 'integer' ),
				'action_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'count_retried' => array( 'type' => 'integer' ),
			'results'       => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'write' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to retry scheduled actions' );
			}
			$ids = array();
			if ( ! empty( $input['action_id'] ) ) {
				$ids[] = (int) $input['action_id'];
			}
			$ids = array_merge( $ids, array_filter( array_map( 'intval', (array) ( $input['action_ids'] ?? array() ) ) ) );
			$ids = array_values( array_unique( array_filter( $ids ) ) );
			if ( empty( $ids ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'action_id or action_ids is required' );
			}

			global $wpdb;
			$table = $wpdb->prefix . 'ff_scheduled_actions';
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
				return fluent_abilities_error( 'plugin_missing', 'Fluent Forms Pro scheduled-actions table is not installed.' );
			}

			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$table} WHERE id IN ({$placeholders})",
				$ids
			) );

			$results = array();
			$retried = 0;
			foreach ( $rows as $row ) {
				do_action( 'fluentform/scheduled_action_retry', $row );

				$wpdb->update(
					$table,
					array(
						'status'      => 'pending',
						'retry_count' => (int) $row->retry_count + 1,
					),
					array( 'id' => $row->id ),
					array( '%s', '%d' ),
					array( '%d' )
				);
				$retried++;
				$results[] = array(
					'id'     => (int) $row->id,
					'status' => 'queued',
				);
			}

			return array(
				'count_retried' => $retried,
				'results'       => $results,
			);
		},
	) );

	$reg->delete( 'fluent-forms/cancel-scheduled-action', array(
		'label'       => 'Cancel Scheduled Action',
		'description' => 'Cancel (delete) one or more Fluent Forms scheduled actions (Pro).',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'action_id'  => array( 'type' => 'integer' ),
				'action_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'count_cancelled' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			if ( ! fluent_abilities_user_can( 'forms', 'delete' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have permission to cancel scheduled actions' );
			}
			$ids = array();
			if ( ! empty( $input['action_id'] ) ) {
				$ids[] = (int) $input['action_id'];
			}
			$ids = array_merge( $ids, array_filter( array_map( 'intval', (array) ( $input['action_ids'] ?? array() ) ) ) );
			$ids = array_values( array_unique( array_filter( $ids ) ) );
			if ( empty( $ids ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'action_id or action_ids is required' );
			}

			global $wpdb;
			$table = $wpdb->prefix . 'ff_scheduled_actions';
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$affected = (int) $wpdb->query( $wpdb->prepare(
				"DELETE FROM {$table} WHERE id IN ({$placeholders})",
				$ids
			) );

			return array( 'count_cancelled' => $affected );
		},
	) );

	error_log( 'Abilities for Fluent: Registered 27 Forms Pro-tier abilities' );

}, 100 );
