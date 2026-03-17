<?php
/**
 * FluentSMTP Abilities
 *
 * Email log browsing, SMTP stats, connection settings, and provider overview.
 * All abilities are read-only. Uses wpFluent() query builder for the fsmpt_email_logs table.
 *
 * 5 abilities in the 'fluent-smtp' category.
 * Registered via Fluent_Abilities_Registrar.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'smtp' );

	// =========================================================================
	// EMAIL LOGS
	// =========================================================================

	$reg->read( 'fluent-smtp/list-email-logs', array(
		'label'       => 'List Email Logs',
		'description' => 'List FluentSMTP email logs with pagination. Filter by status (sent/failed), date range, or recipient email. Body content is excluded for performance.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'status' => array(
					'type'        => 'string',
					'description' => 'Filter by status: sent, failed',
				),
				'to' => array(
					'type'        => 'string',
					'description' => 'Filter by recipient email (partial match)',
				),
				'date_from' => array(
					'type'        => 'string',
					'description' => 'Start date in YYYY-MM-DD format',
				),
				'date_to' => array(
					'type'        => 'string',
					'description' => 'End date in YYYY-MM-DD format',
				),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'logs', array(
			'id'         => array( 'type' => 'integer' ),
			'to'         => array( 'type' => 'string' ),
			'from'       => array( 'type' => 'string' ),
			'subject'    => array( 'type' => 'string' ),
			'status'     => array( 'type' => 'string' ),
			'source'     => array( 'type' => 'string' ),
			'created_at' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$query = wpFluent()->table( 'fsmpt_email_logs' );

			if ( ! empty( $input['status'] ) ) {
				$query->where( 'status', sanitize_text_field( $input['status'] ) );
			}

			if ( ! empty( $input['to'] ) ) {
				$query->where( 'to', 'LIKE', '%' . sanitize_text_field( $input['to'] ) . '%' );
			}

			if ( ! empty( $input['date_from'] ) ) {
				$query->where( 'created_at', '>=', sanitize_text_field( $input['date_from'] ) . ' 00:00:00' );
			}

			if ( ! empty( $input['date_to'] ) ) {
				$query->where( 'created_at', '<=', sanitize_text_field( $input['date_to'] ) . ' 23:59:59' );
			}

			$total = $query->count();

			$logs = $query->orderBy( 'id', 'DESC' )
				->offset( $pagination['offset'] )
				->limit( $pagination['per_page'] )
				->select( array( 'id', 'to', 'from', 'subject', 'status', 'source', 'created_at' ) )
				->get();

			$items = array();
			foreach ( $logs as $log ) {
				$items[] = array(
					'id'         => (int) $log->id,
					'to'         => $log->to,
					'from'       => $log->from,
					'subject'    => $log->subject,
					'status'     => $log->status,
					'source'     => (string) ($log->source ?? ''),
					'created_at' => (string) $log->created_at,
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

	$reg->read( 'fluent-smtp/get-email-log', array(
		'label'       => 'Get Email Log',
		'description' => 'Get a single email log entry by ID with full details including body, parsed headers, response, and extra metadata.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Email log ID',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'            => array( 'type' => 'integer' ),
			'to'            => array( 'type' => 'string' ),
			'from'          => array( 'type' => 'string' ),
			'subject'       => array( 'type' => 'string' ),
			'body'          => array( 'type' => 'string' ),
			'headers'       => array( 'type' => array( 'string', 'object', 'array' ) ),
			'attachments'   => array( 'type' => 'string' ),
			'status'        => array( 'type' => 'string' ),
			'response'      => array( 'type' => array( 'string', 'object', 'array' ) ),
			'extra'         => array( 'type' => array( 'string', 'object', 'array' ) ),
			'retries'       => array( 'type' => 'integer' ),
			'resent_count'  => array( 'type' => 'integer' ),
			'source'        => array( 'type' => 'string' ),
			'created_at'    => array( 'type' => 'string' ),
			'updated_at'    => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$log = wpFluent()->table( 'fsmpt_email_logs' )
				->where( 'id', (int) $input['id'] )
				->first();

			if ( ! $log ) {
				return fluent_abilities_error( 'not_found', 'Email log not found' );
			}

			// Parse headers if stored as serialized/JSON.
			$headers = $log->headers;
			if ( is_string( $headers ) ) {
				$decoded = json_decode( $headers, true );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					$headers = $decoded;
				} else {
					$unserialized = maybe_unserialize( $headers );
					if ( $unserialized !== $headers ) {
						$headers = $unserialized;
					}
				}
			}

			// Parse response if stored as serialized/JSON.
			$response = $log->response;
			if ( is_string( $response ) ) {
				$decoded = json_decode( $response, true );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					$response = $decoded;
				} else {
					$unserialized = maybe_unserialize( $response );
					if ( $unserialized !== $response ) {
						$response = $unserialized;
					}
				}
			}

			// Parse extra if stored as serialized/JSON.
			$extra = $log->extra;
			if ( is_string( $extra ) ) {
				$decoded = json_decode( $extra, true );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					$extra = $decoded;
				} else {
					$unserialized = maybe_unserialize( $extra );
					if ( $unserialized !== $extra ) {
						$extra = $unserialized;
					}
				}
			}

			return array(
				'id'           => (int) $log->id,
				'to'           => $log->to,
				'from'         => $log->from,
				'subject'      => $log->subject,
				'body'         => $log->body,
				'headers'      => fluent_abilities_safe_array( $headers ),
				'attachments'  => $log->attachments,
				'status'       => $log->status,
				'response'     => fluent_abilities_safe_array( $response ),
				'extra'        => fluent_abilities_safe_array( $extra ),
				'retries'      => (int) $log->retries,
				'resent_count' => (int) $log->resent_count,
				'source'       => (string) ($log->source ?? ''),
				'created_at'   => (string) $log->created_at,
				'updated_at'   => (string) $log->updated_at,
			);
		},
	) );

	// =========================================================================
	// STATS
	// =========================================================================

	$reg->read( 'fluent-smtp/get-smtp-stats', array(
		'label'       => 'SMTP Dashboard Stats',
		'description' => 'Get FluentSMTP overview stats: total emails, counts by status (sent/failed), counts by source, and emails sent today, this week, and this month.',
		'input_schema' => array(
			'type'       => 'object',
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'total'        => array( 'type' => 'integer' ),
			'by_status'    => array( 'type' => 'object' ),
			'by_source'    => array( 'type' => 'object' ),
			'today'        => array( 'type' => 'integer' ),
			'this_week'    => array( 'type' => 'integer' ),
			'this_month'   => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$table = wpFluent()->table( 'fsmpt_email_logs' );

			$total  = $table->count();
			$sent   = wpFluent()->table( 'fsmpt_email_logs' )->where( 'status', 'sent' )->count();
			$failed = wpFluent()->table( 'fsmpt_email_logs' )->where( 'status', 'failed' )->count();

			// Emails by time period.
			$now   = current_time( 'mysql' );
			$today = current_time( 'Y-m-d' ) . ' 00:00:00';

			$today_count = wpFluent()->table( 'fsmpt_email_logs' )
				->where( 'created_at', '>=', $today )
				->count();

			$week_start = gmdate( 'Y-m-d', strtotime( 'monday this week', strtotime( current_time( 'Y-m-d' ) ) ) ) . ' 00:00:00';
			$week_count = wpFluent()->table( 'fsmpt_email_logs' )
				->where( 'created_at', '>=', $week_start )
				->count();

			$month_start = current_time( 'Y-m' ) . '-01 00:00:00';
			$month_count = wpFluent()->table( 'fsmpt_email_logs' )
				->where( 'created_at', '>=', $month_start )
				->count();

			// Emails by source.
			$sources_raw = wpFluent()->table( 'fsmpt_email_logs' )
				->select( array( 'source', wpFluent()->raw( 'COUNT(*) as count' ) ) )
				->groupBy( 'source' )
				->get();

			$by_source = array();
			foreach ( $sources_raw as $row ) {
				$source_name = $row->source ?: '(unknown)';
				$by_source[ $source_name ] = (int) $row->count;
			}

			return array(
				'total'       => $total,
				'by_status'   => array(
					'sent'   => $sent,
					'failed' => $failed,
				),
				'by_source'   => $by_source,
				'today'       => $today_count,
				'this_week'   => $week_count,
				'this_month'  => $month_count,
			);
		},
	) );

	// =========================================================================
	// SETTINGS & CONNECTIONS
	// =========================================================================

	$reg->read( 'fluent-smtp/get-smtp-settings', array(
		'label'       => 'Get SMTP Settings',
		'description' => 'Read FluentSMTP settings including connections (with API keys/secrets redacted), mappings, and misc configuration.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'connections' => array( 'type' => 'object' ),
			'mappings'    => array( 'type' => 'object' ),
			'misc'        => array( 'type' => 'object' ),
		) ),
		'callback' => function( $input ) {
			$settings = get_option( 'fluentmail-settings', array() );

			if ( empty( $settings ) ) {
				return fluent_abilities_error( 'not_found', 'FluentSMTP settings not found. Plugin may not be configured.' );
			}

			// Redact sensitive values in connections.
			$connections = $settings['connections'] ?? array();
			$safe_connections = array();
			foreach ( $connections as $key => $connection ) {
				$safe_connection = $connection;

				// Redact known sensitive fields across all providers.
				$sensitive_keys = array(
					'api_key', 'secret_key', 'access_key', 'secret_access_key',
					'password', 'app_password', 'client_secret', 'private_key',
					'token', 'access_token', 'refresh_token', 'api_token',
				);

				if ( isset( $safe_connection['provider_settings'] ) && is_array( $safe_connection['provider_settings'] ) ) {
					foreach ( $sensitive_keys as $sensitive ) {
						if ( ! empty( $safe_connection['provider_settings'][ $sensitive ] ) ) {
							$safe_connection['provider_settings'][ $sensitive ] = '[REDACTED]';
						}
					}
				}

				// Also check top-level keys in the connection.
				foreach ( $sensitive_keys as $sensitive ) {
					if ( ! empty( $safe_connection[ $sensitive ] ) ) {
						$safe_connection[ $sensitive ] = '[REDACTED]';
					}
				}

				$safe_connections[ $key ] = $safe_connection;
			}

			return array(
				'connections' => $safe_connections,
				'mappings'    => $settings['mappings'] ?? array(),
				'misc'        => $settings['misc'] ?? array(),
			);
		},
	) );

	$reg->read( 'fluent-smtp/list-connections', array(
		'label'       => 'List SMTP Connections',
		'description' => 'List configured SMTP connections with provider name, sender email, and status. Sensitive credentials are redacted.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'connections', array(
			'key'          => array( 'type' => 'string' ),
			'title'        => array( 'type' => 'string' ),
			'provider'     => array( 'type' => 'string' ),
			'sender_name'  => array( 'type' => 'string' ),
			'sender_email' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$settings = get_option( 'fluentmail-settings', array() );
			$connections = $settings['connections'] ?? array();

			if ( empty( $connections ) ) {
				return array( 'connections' => array(), 'total' => 0 );
			}

			$items = array();
			foreach ( $connections as $key => $connection ) {
				$provider_settings = $connection['provider_settings'] ?? array();

				$items[] = array(
					'key'         => $key,
					'title'       => $connection['title'] ?? $key,
					'provider'    => $connection['provider'] ?? ( $provider_settings['provider'] ?? 'unknown' ),
					'sender_name' => $connection['sender_name'] ?? ( $provider_settings['sender_name'] ?? '' ),
					'sender_email'=> $connection['sender_email'] ?? ( $provider_settings['sender_email'] ?? '' ),
					'force_from_name'  => $connection['force_from_name'] ?? ( $provider_settings['force_from_name'] ?? 'no' ),
					'force_from_email' => $connection['force_from_email'] ?? ( $provider_settings['force_from_email'] ?? 'no' ),
					'return_path' => $connection['return_path'] ?? ( $provider_settings['return_path'] ?? 'no' ),
				);
			}

			return array(
				'connections' => $items,
				'total'       => count( $items ),
			);
		},
	) );

	error_log( 'Abilities for Fluent: Registered 5 SMTP abilities' );

}, 100 );
