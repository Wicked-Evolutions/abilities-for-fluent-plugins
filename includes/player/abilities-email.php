<?php
/**
 * FluentPlayer Abilities — Email Collections, Integrations, Email Providers,
 * YouTube, and Layers/Smartcodes.
 *
 * 15 abilities in the `fluent-player` category:
 *  - Cluster 5 Email Collections (4, free, PII-bearing — `wp_flp_email_collections`)
 *  - Cluster 6 Integrations (4, free)
 *  - Cluster 7 Email Providers (3 free + 1 Pro = 4)
 *  - Cluster 8 YouTube (1, free)
 *  - Cluster 9 Layers/Smartcodes (2, free)
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

function fluent_abilities_player_register_email_abilities() {

	$reg = new Fluent_Abilities_Registrar( 'player' );

	// ─── Cluster 5: Email Collections (free, PII) ──────────────────────────

	// SECURITY NOTE: response contains viewer email PII — flag for redaction in v1.2 meta-override.
	$reg->read( 'fluent-player/list-email-collections', array(
		'label'         => 'List email collections',
		'description'   => 'Paginated list of captured viewer emails from FluentPlayer email-capture overlays. Returns PII.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => array(
				'per_page'    => array( 'type' => 'integer', 'description' => 'Items per page (default 20, max 100).', 'default' => 20 ),
				'page'        => array( 'type' => 'integer', 'description' => 'Page number (default 1).', 'default' => 1 ),
				'media_id'    => array( 'type' => 'integer', 'description' => 'Filter by media ID.' ),
				'preset_slug' => array( 'type' => 'string', 'description' => 'Filter by preset slug.' ),
				'email'       => array( 'type' => 'string', 'description' => 'Filter by email (LIKE).' ),
				'start_date'  => array( 'type' => 'string', 'description' => 'ISO date inclusive.' ),
				'end_date'    => array( 'type' => 'string', 'description' => 'ISO date inclusive.' ),
			),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'email_collections', array(
			'id'          => array( 'type' => 'integer' ),
			'email'       => array( 'type' => 'string' ),
			'media_id'    => array( 'type' => array( 'integer', 'null' ) ),
			'preset_slug' => array( 'type' => array( 'string', 'null' ) ),
			'layer_id'    => array( 'type' => array( 'integer', 'null' ) ),
			'user_id'     => array( 'type' => array( 'integer', 'null' ) ),
			'video_time'  => array( 'type' => array( 'number', 'null' ) ),
			'ip_address'  => array( 'type' => array( 'string', 'null' ) ),
			'device'      => array( 'type' => array( 'string', 'null' ) ),
			'browser'     => array( 'type' => array( 'string', 'null' ) ),
			'meta'        => array( 'type' => array( 'string', 'object', 'null' ) ),
			'created_at'  => array( 'type' => array( 'string', 'null' ) ),
			'updated_at'  => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback'      => function ( $input ) {
			if ( ! function_exists( 'wpFluent' ) ) {
				return fluent_abilities_error( 'missing_class', 'wpFluent (Fluent Framework) not available.' );
			}
			$pg       = fluent_abilities_pagination( $input, 20 );
			try {
				$q = wpFluent()->table( 'flp_email_collections' );
				if ( ! empty( $input['media_id'] ) ) {
					$q->where( 'media_id', absint( $input['media_id'] ) );
				}
				if ( ! empty( $input['preset_slug'] ) ) {
					$q->where( 'preset_slug', sanitize_text_field( $input['preset_slug'] ) );
				}
				if ( ! empty( $input['email'] ) ) {
					$q->where( 'email', 'LIKE', '%' . sanitize_text_field( $input['email'] ) . '%' );
				}
				if ( ! empty( $input['start_date'] ) ) {
					$q->where( 'created_at', '>=', sanitize_text_field( $input['start_date'] ) . ' 00:00:00' );
				}
				if ( ! empty( $input['end_date'] ) ) {
					$q->where( 'created_at', '<=', sanitize_text_field( $input['end_date'] ) . ' 23:59:59' );
				}
				$total = (int) ( clone $q )->count();
				$rows  = $q->orderBy( 'id', 'DESC' )->limit( $pg['per_page'] )->offset( $pg['offset'] )->get();
				$items = array();
				foreach ( $rows as $r ) {
					$items[] = array(
						'id'          => (int) $r->id,
						'email'       => $r->email ?? '',
						'media_id'    => isset( $r->media_id ) ? (int) $r->media_id : null,
						'preset_slug' => $r->preset_slug ?? null,
						'layer_id'    => isset( $r->layer_id ) ? (int) $r->layer_id : null,
						'user_id'     => isset( $r->user_id ) ? (int) $r->user_id : null,
						'video_time'  => isset( $r->video_time ) ? (float) $r->video_time : null,
						'ip_address'  => $r->ip_address ?? null,
						'device'      => $r->device ?? null,
						'browser'     => $r->browser ?? null,
						'meta'        => $r->meta ?? null,
						'created_at'  => $r->created_at ?? null,
						'updated_at'  => $r->updated_at ?? null,
					);
				}
				return fluent_abilities_player_redact( array(
					'total'             => $total,
					'page'              => $pg['page'],
					'per_page'          => $pg['per_page'],
					'email_collections' => $items,
				) );
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'execution_failed', $e->getMessage() );
			}
		},
	) );

	// SECURITY NOTE: response contains viewer email PII — flag for redaction in v1.2 meta-override.
	$reg->read( 'fluent-player/get-email-collection', array(
		'label'         => 'Get email collection',
		'description'   => 'Get a single captured viewer email by ID. Returns PII.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'         => array( 'type' => 'integer' ),
			'email'      => array( 'type' => 'string' ),
			'media_id'   => array( 'type' => array( 'integer', 'null' ) ),
			'meta'       => array( 'type' => array( 'string', 'object', 'null' ) ),
			'created_at' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback'      => function ( $input ) {
			$id = absint( $input['id'] ?? 0 );
			if ( ! $id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'id is required.' );
			}
			if ( ! function_exists( 'wpFluent' ) ) {
				return fluent_abilities_error( 'missing_class', 'wpFluent (Fluent Framework) not available.' );
			}
			try {
				$row = wpFluent()->table( 'flp_email_collections' )->where( 'id', $id )->first();
				if ( ! $row ) {
					return fluent_abilities_error( 'not_found', 'Email collection not found: ' . $id );
				}
				return fluent_abilities_player_redact( array(
					'id'          => (int) $row->id,
					'email'       => $row->email ?? '',
					'media_id'    => isset( $row->media_id ) ? (int) $row->media_id : null,
					'preset_slug' => $row->preset_slug ?? null,
					'layer_id'    => isset( $row->layer_id ) ? (int) $row->layer_id : null,
					'user_id'     => isset( $row->user_id ) ? (int) $row->user_id : null,
					'video_time'  => isset( $row->video_time ) ? (float) $row->video_time : null,
					'ip_address'  => $row->ip_address ?? null,
					'device'      => $row->device ?? null,
					'browser'     => $row->browser ?? null,
					'meta'        => $row->meta ?? null,
					'created_at'  => $row->created_at ?? null,
					'updated_at'  => $row->updated_at ?? null,
				) );
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'execution_failed', $e->getMessage() );
			}
		},
	) );

	// SECURITY NOTE: bulk PII export — flag for redaction + mcp.public=false in v1.2 meta-override.
	$reg->read( 'fluent-player/export-email-collections', array(
		'label'         => 'Export email collections',
		'description'   => 'Bulk export captured viewer emails as CSV-shape rows (headers + rows + filename).',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => array(
				'media_id'    => array( 'type' => 'integer' ),
				'preset_slug' => array( 'type' => 'string' ),
				'start_date'  => array( 'type' => 'string' ),
				'end_date'    => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'headers'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'rows'     => array( 'type' => 'array', 'items' => array( 'type' => 'array' ) ),
			'filename' => array( 'type' => 'string' ),
		) ),
		'callback'      => function ( $input ) {
			if ( ! function_exists( 'wpFluent' ) ) {
				return fluent_abilities_error( 'missing_class', 'wpFluent not available.' );
			}
			try {
				$q = wpFluent()->table( 'flp_email_collections' );
				if ( ! empty( $input['media_id'] ) ) {
					$q->where( 'media_id', absint( $input['media_id'] ) );
				}
				if ( ! empty( $input['preset_slug'] ) ) {
					$q->where( 'preset_slug', sanitize_text_field( $input['preset_slug'] ) );
				}
				if ( ! empty( $input['start_date'] ) ) {
					$q->where( 'created_at', '>=', sanitize_text_field( $input['start_date'] ) . ' 00:00:00' );
				}
				if ( ! empty( $input['end_date'] ) ) {
					$q->where( 'created_at', '<=', sanitize_text_field( $input['end_date'] ) . ' 23:59:59' );
				}
				$rows    = $q->orderBy( 'id', 'DESC' )->get();
				$headers = array( 'id', 'email', 'media_id', 'preset_slug', 'layer_id', 'user_id', 'video_time', 'ip_address', 'device', 'browser', 'created_at' );
				$data    = array();
				foreach ( $rows as $r ) {
					// Email + IP columns redacted in row tuples (per Reviewer pre-flight #1).
					// Operators needing raw PII export should use the database directly under admin scope.
					$has_email = ! empty( $r->email );
					$has_ip    = ! empty( $r->ip_address );
					$data[]    = array(
						(int) $r->id,
						$has_email ? '[REDACTED]' : '',
						isset( $r->media_id ) ? (int) $r->media_id : '',
						$r->preset_slug ?? '',
						isset( $r->layer_id ) ? (int) $r->layer_id : '',
						isset( $r->user_id ) ? (int) $r->user_id : '',
						isset( $r->video_time ) ? (float) $r->video_time : '',
						$has_ip ? '[REDACTED]' : '',
						$r->device ?? '',
						$r->browser ?? '',
						$r->created_at ?? '',
					);
				}
				return array(
					'headers'  => $headers,
					'rows'     => $data,
					'filename' => 'fluent-player-email-collections-' . gmdate( 'Y-m-d' ) . '.csv',
				);
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'execution_failed', $e->getMessage() );
			}
		},
	) );

	// SECURITY NOTE: destructive PII delete — flag for mcp.public=false in v1.2 meta-override.
	$reg->delete( 'fluent-player/delete-email-collection', array(
		'label'         => 'Delete email collection',
		'description'   => 'Permanently delete one captured viewer email record by ID.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'deleted' => array( 'type' => 'boolean' ),
			'id'      => array( 'type' => 'integer' ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function ( $input ) {
			$id = absint( $input['id'] ?? 0 );
			if ( ! $id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'id is required.' );
			}
			if ( ! function_exists( 'wpFluent' ) ) {
				return fluent_abilities_error( 'missing_class', 'wpFluent not available.' );
			}
			try {
				$rows_affected = wpFluent()->table( 'flp_email_collections' )->where( 'id', $id )->delete();
				return array(
					'success' => true,
					'deleted' => (bool) $rows_affected,
					'id'      => $id,
				);
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'execution_failed', $e->getMessage() );
			}
		},
	) );

	// ─── Cluster 6: Integrations ───────────────────────────────────────────

	$reg->read( 'fluent-player/list-integrations', array(
		'label'         => 'List integrations',
		'description'   => 'List FluentPlayer integration registry (YouTube/Bunny Stream/Bunny Storage/Mux) with enabled + configured status. Output note: each integration item carries no stable record ID — use the `slug` field to reference an integration in follow-up calls.',
		'category'      => 'fluent-player',
		'output_schema' => fluent_abilities_schema_collection_output( 'integrations', array(
			'name'       => array( 'type' => 'string' ),
			'slug'       => array( 'type' => 'string' ),
			'enabled'    => array( 'type' => 'boolean' ),
			'configured' => array( 'type' => 'boolean' ),
		) ),
		'callback'      => function ( $input ) {
			$result = fluent_abilities_player_invoke_controller(
				'\FluentPlayer\App\Http\Controllers\IntegrationController',
				'getIntegrations',
				is_array( $input ) ? $input : array()
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$items = is_array( $result ) ? ( $result['integrations'] ?? $result ) : array();
			$items = is_array( $items ) ? array_values( $items ) : array();
			return array( 'integrations' => $items, 'total' => count( $items ) );
		},
	) );

	$reg->read( 'fluent-player/get-integration-fields', array(
		'label'         => 'Get integration fields',
		'description'   => 'Get the field schema for an integration settings form.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'integration' ),
			'properties' => array(
				'integration' => array(
					'type' => 'string',
					'enum' => array( 'youtube', 'bunny_stream', 'bunny_storage', 'mux' ),
				),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'fields' => array( 'type' => 'array' ),
		) ),
		'callback'      => function ( $input ) {
			$integration = isset( $input['integration'] ) ? sanitize_key( $input['integration'] ) : '';
			if ( '' === $integration ) {
				return fluent_abilities_error( 'ability_invalid_input', 'integration is required.' );
			}
			$result = fluent_abilities_player_invoke_controller(
				'\FluentPlayer\App\Http\Controllers\IntegrationController',
				'getIntegrationFields',
				array( 'integration' => $integration ),
				array( 'integration' => $integration )
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			// Vendor returns {integrations: {<slug>: {label, fields: [...]}}};
			// extract just the requested integration's field list.
			$fields = array();
			if ( is_array( $result ) && isset( $result['integrations'][ $integration ]['fields'] ) ) {
				$fields = $result['integrations'][ $integration ]['fields'];
			} elseif ( is_array( $result ) && isset( $result['fields'] ) ) {
				$fields = $result['fields'];
			}
			$fields = is_array( $fields ) ? array_values( $fields ) : array();
			return array( 'fields' => $fields );
		},
	) );

	// SECURITY NOTE: input contains third-party API keys — flag for mcp.public=false + redaction in v1.2 meta-override.
	$reg->write( 'fluent-player/save-integration-settings', array(
		'label'         => 'Save integration settings',
		'description'   => 'Persist credentials/config for a FluentPlayer integration (YouTube/Bunny/Mux). Bunny + Mux fields are Pro-effective even though the ability itself is free-callable.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'integration', 'settings' ),
			'properties' => array(
				'integration' => array( 'type' => 'string' ),
				'settings'    => array( 'type' => 'object' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'message'     => array( 'type' => 'string' ),
			'integration' => array( 'type' => 'object' ),
		) ),
		'callback'      => function ( $input ) {
			$integration = isset( $input['integration'] ) ? sanitize_key( $input['integration'] ) : '';
			$settings    = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : null;
			if ( '' === $integration || null === $settings ) {
				return fluent_abilities_error( 'ability_invalid_input', 'integration and settings are required.' );
			}
			$result = fluent_abilities_player_invoke_controller(
				'\FluentPlayer\App\Http\Controllers\IntegrationController',
				'saveIntegrationSettings',
				array( 'integration' => $integration, 'settings' => $settings ),
				array( 'integration' => $integration )
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			// Response echoes saved settings (which contain the API keys we just received) — redact.
			$result = fluent_abilities_player_redact( is_array( $result ) ? $result : array() );
			return array(
				'success'     => true,
				'message'     => $result['message'] ?? 'Integration settings saved.',
				'integration' => fluent_abilities_safe_array( $result['integration'] ?? $result ),
			);
		},
	) );

	$reg->write( 'fluent-player/test-integration-connection', array(
		'label'         => 'Test integration connection',
		'description'   => "Test an integration's credentials against its remote API. Uses supplied settings (if provided) or falls back to stored.",
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'integration' ),
			'properties' => array(
				'integration' => array( 'type' => 'string' ),
				'settings'    => array( 'type' => 'object', 'description' => 'Optional override of stored settings.' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'message' => array( 'type' => 'string' ),
			'details' => array( 'type' => array( 'object', 'null' ) ),
		) ),
		'callback'      => function ( $input ) {
			$integration = isset( $input['integration'] ) ? sanitize_key( $input['integration'] ) : '';
			if ( '' === $integration ) {
				return fluent_abilities_error( 'ability_invalid_input', 'integration is required.' );
			}
			$payload = array( 'integration' => $integration );
			if ( isset( $input['settings'] ) && is_array( $input['settings'] ) ) {
				$payload['settings'] = $input['settings'];
			}
			$result = fluent_abilities_player_invoke_controller(
				'\FluentPlayer\App\Http\Controllers\IntegrationController',
				'testConnection',
				$payload,
				array( 'integration' => $integration )
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array(
				'success' => is_array( $result ) ? (bool) ( $result['success'] ?? true ) : true,
				'message' => is_array( $result ) ? ( $result['message'] ?? '' ) : '',
				'details' => fluent_abilities_safe_array( is_array( $result ) ? ( $result['details'] ?? null ) : null ),
			);
		},
	) );

	// ─── Cluster 7: Email Providers ────────────────────────────────────────

	// SECURITY NOTE: response includes stored provider API keys — flag for mcp.public=false + redaction in v1.2 meta-override.
	$reg->read( 'fluent-player/list-email-providers', array(
		'label'         => 'List email providers',
		'description'   => 'List registered email providers with configuration status (returns API keys for configured providers). Output note: each provider item carries no stable record ID — use the `slug` field to reference a provider in follow-up calls.',
		'category'      => 'fluent-player',
		'output_schema' => fluent_abilities_schema_collection_output( 'providers', array(
			'slug'       => array( 'type' => 'string' ),
			'name'       => array( 'type' => 'string' ),
			'enabled'    => array( 'type' => 'boolean' ),
			'configured' => array( 'type' => 'boolean' ),
			'logo'       => array( 'type' => array( 'string', 'null' ) ),
			'settings'   => array( 'type' => array( 'object', 'null' ) ),
		) ),
		'callback'      => function ( $input ) {
			$result = fluent_abilities_player_invoke_controller(
				'\FluentPlayer\App\Http\Controllers\EmailProviderController',
				'getProvidersSettings',
				is_array( $input ) ? $input : array()
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$providers = is_array( $result ) ? ( $result['providers'] ?? $result ) : array();
			$providers = is_array( $providers ) ? array_values( $providers ) : array();
			// Each provider entry contains api_key / connectUrl / list_id — redact before returning.
			return fluent_abilities_player_redact( array( 'providers' => $providers, 'total' => count( $providers ) ) );
		},
	) );

	if ( defined( 'FLUENT_PLAYER_PRO_VERSION' ) ) {
		// SECURITY NOTE: input contains email provider API keys — flag for mcp.public=false in v1.2 meta-override.
		$reg->write( 'fluent-player/save-email-provider-settings', array(
			'label'         => 'Save email provider settings',
			'description'   => 'Persist credentials/config for a Pro email provider (e.g. Mailchimp, FluentCRM-direct).',
			'category'      => 'fluent-player',
			'input_schema'  => array(
				'type'       => 'object',
				'required'   => array( 'provider', 'settings' ),
				'properties' => array(
					'provider' => array( 'type' => 'string' ),
					'settings' => array( 'type' => 'object' ),
				),
			),
			'output_schema' => fluent_abilities_schema_success_output( array(
				'message'  => array( 'type' => 'string' ),
				'provider' => array( 'type' => 'object' ),
			) ),
			'callback'      => function ( $input ) {
				$provider = isset( $input['provider'] ) ? sanitize_key( $input['provider'] ) : '';
				$settings = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : null;
				if ( '' === $provider || null === $settings ) {
					return fluent_abilities_error( 'ability_invalid_input', 'provider and settings are required.' );
				}
				$result = fluent_abilities_player_invoke_controller(
					'\FluentPlayer\App\Http\Controllers\EmailProviderController',
					'saveProviderSettings',
					array( 'provider' => $provider, 'settings' => $settings ),
					array( 'provider' => $provider )
				);
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				// Response echoes saved provider settings; redact api_key etc.
				$result = fluent_abilities_player_redact( is_array( $result ) ? $result : array() );
				return array(
					'success'  => true,
					'message'  => $result['message'] ?? 'Provider settings saved.',
					'provider' => fluent_abilities_safe_array( $result['provider'] ?? $result ),
				);
			},
		) );
	}

	$reg->read( 'fluent-player/list-provider-resources', array(
		'label'         => 'List provider resources',
		'description'   => "List a provider's downstream resources (lists / tags / forms) used for select-box population in player overlays.",
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'provider', 'resource' ),
			'properties' => array(
				'provider' => array( 'type' => 'string', 'description' => 'Email provider slug (the registered provider identifier, e.g. the `slug` returned by list-email-providers).' ),
				'resource' => array( 'type' => 'string', 'description' => 'lists | tags | forms' ),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'resource' => array( 'type' => 'string' ),
				'items'    => array( 'type' => array( 'array', 'object' ), 'description' => 'Vendor returns item shape that varies by provider+resource — keep permissive.' ),
			),
		),
		'callback'      => function ( $input ) {
			$provider = isset( $input['provider'] ) ? sanitize_key( $input['provider'] ) : '';
			$resource = isset( $input['resource'] ) ? sanitize_key( $input['resource'] ) : '';
			if ( '' === $provider || '' === $resource ) {
				return fluent_abilities_error( 'ability_invalid_input', 'provider and resource are required.' );
			}
			$result = fluent_abilities_player_invoke_controller(
				'\FluentPlayer\App\Http\Controllers\EmailProviderController',
				'getProviderResource',
				array( 'provider' => $provider, 'resource' => $resource ),
				array( 'provider' => $provider, 'resource' => $resource )
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$items = is_array( $result ) ? ( $result['items'] ?? $result ) : array();
			return array( 'resource' => $resource, 'items' => fluent_abilities_safe_array( $items ) );
		},
	) );

	$reg->read( 'fluent-player/validate-provider-field', array(
		'label'         => 'Validate provider field',
		'description'   => "Validate a single field value for a provider (used by the admin UI's inline form validation).",
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'provider', 'field', 'value' ),
			'properties' => array(
				'provider' => array( 'type' => 'string' ),
				'field'    => array( 'type' => 'string' ),
				'value'    => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'valid'   => array( 'type' => 'boolean' ),
			'message' => array( 'type' => 'string' ),
		) ),
		'callback'      => function ( $input ) {
			$provider = isset( $input['provider'] ) ? sanitize_key( $input['provider'] ) : '';
			$field    = isset( $input['field'] ) ? sanitize_key( $input['field'] ) : '';
			$value    = isset( $input['value'] ) ? (string) $input['value'] : '';
			if ( '' === $provider || '' === $field ) {
				return fluent_abilities_error( 'ability_invalid_input', 'provider and field are required.' );
			}
			$result = fluent_abilities_player_invoke_controller(
				'\FluentPlayer\App\Http\Controllers\EmailProviderController',
				'validateProviderField',
				array( 'provider' => $provider, 'field' => $field, 'value' => $value ),
				array( 'provider' => $provider, 'field' => $field )
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array(
				'valid'   => is_array( $result ) ? (bool) ( $result['valid'] ?? false ) : false,
				'message' => is_array( $result ) ? ( $result['message'] ?? '' ) : '',
			);
		},
	) );

	// ─── Cluster 8: YouTube ────────────────────────────────────────────────

	$reg->read( 'fluent-player/get-youtube-channel-info', array(
		'label'         => 'Get YouTube channel info',
		'description'   => 'Get the connected YouTube channel profile info (third-party YouTube Data API call).',
		'category'      => 'fluent-player',
		'output_schema' => fluent_abilities_schema_item_output( array(
			'channel' => array( 'type' => 'object' ),
		) ),
		'callback'      => function ( $input ) {
			$result = fluent_abilities_player_invoke_controller(
				'\FluentPlayer\App\Http\Controllers\YouTubeController',
				'getChannelInfo',
				is_array( $input ) ? $input : array()
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$channel = is_array( $result ) ? ( $result['channel'] ?? $result ) : array();
			return array( 'channel' => fluent_abilities_safe_array( $channel ) );
		},
	) );

	// ─── Cluster 9: Layers / Smartcodes ────────────────────────────────────

	$reg->read( 'fluent-player/list-layer-forms', array(
		'label'         => 'List layer forms',
		'description'   => 'List form-layer forms available for a given form type slug.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'type' ),
			'properties' => array(
				'type' => array( 'type' => 'string', 'description' => 'Form type slug.' ),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'total' => array( 'type' => 'integer' ),
				'forms' => array( 'type' => 'array' ),
			),
		),
		'callback'      => function ( $input ) {
			$type = isset( $input['type'] ) ? sanitize_key( $input['type'] ) : '';
			if ( '' === $type ) {
				return fluent_abilities_error( 'ability_invalid_input', 'type is required.' );
			}
			$result = fluent_abilities_player_invoke_controller(
				'\FluentPlayer\App\Http\Controllers\LayerController',
				'getForms',
				array( 'type' => $type ),
				array( 'type' => $type )
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$forms = is_array( $result ) ? ( $result['forms'] ?? $result ) : array();
			$forms = is_array( $forms ) ? array_values( $forms ) : array();
			return array( 'forms' => $forms, 'total' => count( $forms ) );
		},
	) );

	$reg->read( 'fluent-player/list-smartcodes', array(
		'label'         => 'List smartcodes',
		'description'   => 'List FluentPlayer smartcode tokens ({{...}} template placeholders) usable in overlays + CTAs.',
		'category'      => 'fluent-player',
		'output_schema' => fluent_abilities_schema_collection_output( 'smartcodes', array(
			'key'   => array( 'type' => 'string' ),
			'label' => array( 'type' => 'string' ),
			'group' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback'      => function ( $input ) {
			$result = fluent_abilities_player_invoke_controller(
				'\FluentPlayer\App\Http\Controllers\SmartcodeController',
				'get',
				is_array( $input ) ? $input : array()
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$items = is_array( $result ) ? ( $result['smartcodes'] ?? $result ) : array();
			$items = is_array( $items ) ? array_values( $items ) : array();
			return array( 'smartcodes' => $items, 'total' => count( $items ) );
		},
	) );
}
add_action( 'wp_abilities_api_init', 'fluent_abilities_player_register_email_abilities', 100 );
