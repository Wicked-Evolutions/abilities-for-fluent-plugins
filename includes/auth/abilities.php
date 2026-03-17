<?php
/**
 * FluentAuth Abilities
 *
 * Login log viewing, security statistics, authentication settings,
 * and magic login hash listing. All read-only.
 *
 * 4 abilities in the 'fluent-auth' category.
 * Registered via Fluent_Abilities_Registrar.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'auth' );

	// Guard: 3 of 4 auth abilities require wpFluent() from FluentCRM framework.
	// get-auth-settings uses get_option() and is safe without it.
	$has_fluent_db = function_exists( 'wpFluent' );

	// =========================================================================
	// LOGIN LOGS
	// =========================================================================

	if ( $has_fluent_db ) {

	$reg->read( 'fluent-auth/list-login-logs', array(
		'label'       => 'List Login Logs',
		'description' => 'List login logs with pagination. Filter by status, username, IP, or date range.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'status' => array(
					'type'        => 'string',
					'description' => 'Filter by status: success, failed',
				),
				'username' => array(
					'type'        => 'string',
					'description' => 'Filter by username',
				),
				'ip' => array(
					'type'        => 'string',
					'description' => 'Filter by IP address',
				),
				'date_from' => array(
					'type'        => 'string',
					'description' => 'Start date (YYYY-MM-DD)',
				),
				'date_to' => array(
					'type'        => 'string',
					'description' => 'End date (YYYY-MM-DD)',
				),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'logs', array(
			'id'         => array( 'type' => 'integer' ),
			'username'   => array( 'type' => 'string' ),
			'user_id'    => array( 'type' => 'integer' ),
			'ip'         => array( 'type' => 'string' ),
			'status'     => array( 'type' => 'string' ),
			'browser'    => array( 'type' => 'string' ),
			'device_os'  => array( 'type' => 'string' ),
			'error_code' => array( 'type' => 'string' ),
			'created_at' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$query = wpFluent()->table( 'fls_auth_logs' );

			if ( ! empty( $input['status'] ) ) {
				$query->where( 'status', sanitize_text_field( $input['status'] ) );
			}

			if ( ! empty( $input['username'] ) ) {
				$query->where( 'username', sanitize_text_field( $input['username'] ) );
			}

			if ( ! empty( $input['ip'] ) ) {
				$query->where( 'ip', sanitize_text_field( $input['ip'] ) );
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
				->get();

			$items = array();
			foreach ( $logs as $log ) {
				$items[] = array(
					'id'         => (int) $log->id,
					'username'   => (string) ($log->username ?? ''),
					'user_id'    => (int) $log->user_id,
					'ip'         => (string) ($log->ip ?? ''),
					'status'     => (string) ($log->status ?? ''),
					'browser'    => (string) ($log->browser ?? ''),
					'device_os'  => (string) ($log->device_os ?? ''),
					'error_code' => (string) ($log->error_code ?? ''),
					'created_at' => (string) ($log->created_at ?? ''),
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

	} // end list-login-logs guard

	// =========================================================================
	// SECURITY STATS
	// =========================================================================

	if ( $has_fluent_db ) {

	$reg->read( 'fluent-auth/get-security-stats', array(
		'label'       => 'Get Security Stats',
		'description' => 'Dashboard stats: total login attempts, successful vs failed, unique IPs, failed attempts today, top failed usernames and IPs.',
		'input_schema' => array(
			'type'       => 'object',
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'total_attempts'        => array( 'type' => 'integer' ),
			'successful'            => array( 'type' => 'integer' ),
			'failed'                => array( 'type' => 'integer' ),
			'unique_ips'            => array( 'type' => 'integer' ),
			'failed_today'          => array( 'type' => 'integer' ),
			'top_failed_usernames'  => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'top_failed_ips'        => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $input ) {
			$table = wpFluent()->table( 'fls_auth_logs' );
			$today = current_time( 'Y-m-d' );

			$total           = $table->count();
			$successful      = (clone $table)->where( 'status', 'success' )->count();
			$failed          = (clone $table)->where( 'status', 'failed' )->count();
			$unique_ips      = (clone $table)->select( wpFluent()->raw( 'COUNT(DISTINCT ip) as cnt' ) )->first();
			$failed_today    = (clone $table)->where( 'status', 'failed' )
				->where( 'created_at', '>=', $today . ' 00:00:00' )
				->count();

			// Top 5 failed usernames.
			$top_usernames = (clone $table)->select( array( 'username', wpFluent()->raw( 'COUNT(*) as attempts' ) ) )
				->where( 'status', 'failed' )
				->groupBy( 'username' )
				->orderBy( 'attempts', 'DESC' )
				->limit( 5 )
				->get();

			$top_username_items = array();
			foreach ( $top_usernames as $row ) {
				$top_username_items[] = array(
					'username' => $row->username,
					'attempts' => (int) $row->attempts,
				);
			}

			// Top 5 failed IPs.
			$top_ips = (clone $table)->select( array( 'ip', wpFluent()->raw( 'COUNT(*) as attempts' ) ) )
				->where( 'status', 'failed' )
				->groupBy( 'ip' )
				->orderBy( 'attempts', 'DESC' )
				->limit( 5 )
				->get();

			$top_ip_items = array();
			foreach ( $top_ips as $row ) {
				$top_ip_items[] = array(
					'ip'       => $row->ip,
					'attempts' => (int) $row->attempts,
				);
			}

			return array(
				'total_attempts'    => $total,
				'successful'        => $successful,
				'failed'            => $failed,
				'unique_ips'        => (int) $unique_ips->cnt,
				'failed_today'      => $failed_today,
				'top_failed_usernames' => $top_username_items,
				'top_failed_ips'       => $top_ip_items,
			);
		},
	) );

	} // end get-security-stats guard

	// =========================================================================
	// AUTH SETTINGS
	// =========================================================================

	$reg->read( 'fluent-auth/get-auth-settings', array(
		'label'      => 'Get Auth Settings',
		'description' => 'Read FluentAuth authentication and login settings from wp_options.',
		'capability' => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'auth_settings'  => array( 'type' => 'object' ),
			'login_settings' => array( 'type' => 'object' ),
		) ),
		'callback' => function( $input ) {
			$auth_settings  = get_option( 'fls_auth_settings', array() );
			$login_settings = get_option( 'fls_login_settings', array() );

			return array(
				'auth_settings'  => is_array( $auth_settings ) ? $auth_settings : array(),
				'login_settings' => is_array( $login_settings ) ? $login_settings : array(),
			);
		},
	) );

	// =========================================================================
	// LOGIN HASHES
	// =========================================================================

	if ( $has_fluent_db ) {

	$reg->read( 'fluent-auth/list-login-hashes', array(
		'label'       => 'List Login Hashes',
		'description' => 'List magic login hashes with pagination. Hash values are excluded for security.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => fluent_abilities_pagination_schema(),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'hashes', array(
			'id'          => array( 'type' => 'integer' ),
			'user_id'     => array( 'type' => 'integer' ),
			'status'      => array( 'type' => 'string' ),
			'use_type'    => array( 'type' => 'string' ),
			'used_count'  => array( 'type' => 'integer' ),
			'use_limit'   => array( 'type' => 'integer' ),
			'ip_address'  => array( 'type' => 'string' ),
			'country'     => array( 'type' => 'string' ),
			'city'        => array( 'type' => 'string' ),
			'valid_till'  => array( 'type' => 'string' ),
			'created_at'  => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$query = wpFluent()->table( 'fls_login_hashes' );

			$total = $query->count();
			$hashes = $query->orderBy( 'id', 'DESC' )
				->offset( $pagination['offset'] )
				->limit( $pagination['per_page'] )
				->get();

			$items = array();
			foreach ( $hashes as $hash ) {
				$items[] = array(
					'id'         => (int) $hash->id,
					'user_id'    => (int) $hash->user_id,
					'status'     => (string) ($hash->status ?? ''),
					'use_type'   => (string) ($hash->use_type ?? ''),
					'used_count' => (int) $hash->used_count,
					'use_limit'  => (int) $hash->use_limit,
					'ip_address' => (string) ($hash->ip_address ?? ''),
					'country'    => (string) ($hash->country ?? ''),
					'city'       => (string) ($hash->city ?? ''),
					'valid_till' => (string) ($hash->valid_till ?? ''),
					'created_at' => (string) ($hash->created_at ?? ''),
				);
			}

			return array(
				'hashes'   => $items,
				'total'    => $total,
				'page'     => $pagination['page'],
				'per_page' => $pagination['per_page'],
			);
		},
	) );

	} // end list-login-hashes guard

	error_log( 'Abilities for Fluent: Registered 4 Auth abilities' );

}, 100 );
