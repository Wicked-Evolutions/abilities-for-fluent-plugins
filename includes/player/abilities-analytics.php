<?php
/**
 * FluentPlayer Abilities — Analytics (all Pro)
 *
 * 17 abilities in the `fluent-player` category wrapping the Pro AnalyticsController.
 * Backed by the `wp_flp_visits` custom table.
 *
 * Per-user / top-viewer abilities are PII-bearing — flagged in code comments for
 * v1.2 meta-override (mcp.public=false, contains_pii=true).
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

function fluent_abilities_player_register_analytics_abilities() {

	if ( ! defined( 'FLUENT_PLAYER_PRO_VERSION' ) ) {
		return;
	}

	$reg = new Fluent_Abilities_Registrar( 'player' );

	$invoke = function ( $method, $input, $extra = null ) {
		if ( null === $extra ) {
			$params = array();
		} elseif ( is_array( $extra ) ) {
			$params = $extra;
		} else {
			$params = array( 'id' => $extra );
		}
		$result = fluent_abilities_player_invoke_controller(
			'\FluentPlayerPro\App\Http\Controllers\AnalyticsController',
			$method,
			is_array( $input ) ? $input : array(),
			$params
		);
		// Top-users + per-user reports return display_name / email — redact via the
		// shared helper (idempotent on non-PII responses; only matching keys swap to
		// [REDACTED]). Per Reviewer pre-flight #1.
		return is_wp_error( $result ) ? $result : fluent_abilities_player_redact( $result );
	};

	$date_range_schema = array(
		'start' => array( 'type' => 'string', 'description' => 'YYYY-MM-DD inclusive.' ),
		'end'   => array( 'type' => 'string', 'description' => 'YYYY-MM-DD inclusive.' ),
	);

	// ─── Global stats ──────────────────────────────────────────────────────

	$reg->read( 'fluent-player/analytics-stats', array(
		'label'         => 'Analytics — overall stats',
		'description'   => 'Global analytics totals for a date range (views, unique viewers, watch time, avg watch time).',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => $date_range_schema,
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'total_views'      => array( 'type' => 'integer' ),
			'unique_viewers'   => array( 'type' => 'integer' ),
			'total_watch_time' => array( 'type' => array( 'integer', 'number' ) ),
			'avg_watch_time'   => array( 'type' => array( 'integer', 'number' ) ),
		) ),
		'callback'      => function ( $input ) use ( $invoke ) {
			return $invoke( 'getStats', $input );
		},
	) );

	$reg->read( 'fluent-player/analytics-top-videos', array(
		'label'         => 'Analytics — top videos',
		'description'   => 'Top videos by views in a date range.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => array_merge( $date_range_schema, array(
				'per_page' => array( 'type' => 'integer', 'default' => 10 ),
				'page'     => array( 'type' => 'integer', 'default' => 1 ),
			) ),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'videos', array(
			'media_id'         => array( 'type' => 'integer' ),
			'post_title'       => array( 'type' => 'string' ),
			'views'            => array( 'type' => 'integer' ),
			'total_watch_time' => array( 'type' => array( 'integer', 'number' ) ),
			'avg_watch_time'   => array( 'type' => array( 'integer', 'number' ) ),
		) ),
		'callback'      => function ( $input ) use ( $invoke ) {
			return $invoke( 'getTopVideos', $input );
		},
	) );

	// SECURITY NOTE: response contains user-identifying analytics PII — flag for redaction in v1.2 meta-override.
	$reg->read( 'fluent-player/analytics-top-users', array(
		'label'         => 'Analytics — top viewers',
		'description'   => 'Top viewer users by views in a date range. Returns user PII (display_name, email).',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => array_merge( $date_range_schema, array(
				'per_page' => array( 'type' => 'integer', 'default' => 10 ),
				'page'     => array( 'type' => 'integer', 'default' => 1 ),
			) ),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'users', array(
			'user_id'          => array( 'type' => 'integer' ),
			'display_name'     => array( 'type' => 'string' ),
			'email'            => array( 'type' => 'string' ),
			'views'            => array( 'type' => 'integer' ),
			'total_watch_time' => array( 'type' => array( 'integer', 'number' ) ),
		) ),
		'callback'      => function ( $input ) use ( $invoke ) {
			return $invoke( 'getTopUsers', $input );
		},
	) );

	$reg->read( 'fluent-player/analytics-location-breakdown', array(
		'label'         => 'Analytics — location breakdown',
		'description'   => 'Views by country in a date range.',
		'category'      => 'fluent-player',
		'input_schema'  => array( 'type' => 'object', 'properties' => $date_range_schema ),
		'output_schema' => fluent_abilities_schema_collection_output( 'countries', array(
			'country'          => array( 'type' => 'string' ),
			'views'            => array( 'type' => 'integer' ),
			'total_watch_time' => array( 'type' => array( 'integer', 'number' ) ),
		) ),
		'callback'      => function ( $input ) use ( $invoke ) {
			return $invoke( 'getLocationBreakdown', $input );
		},
	) );

	$reg->read( 'fluent-player/analytics-new-returning-viewers', array(
		'label'         => 'Analytics — new vs returning viewers',
		'description'   => 'New vs returning viewer ratio in a date range.',
		'category'      => 'fluent-player',
		'input_schema'  => array( 'type' => 'object', 'properties' => $date_range_schema ),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'new_viewers'       => array( 'type' => 'integer' ),
			'returning_viewers' => array( 'type' => 'integer' ),
			'ratio'             => array( 'type' => 'number' ),
		) ),
		'callback'      => function ( $input ) use ( $invoke ) {
			return $invoke( 'getNewReturningViewers', $input );
		},
	) );

	$reg->read( 'fluent-player/analytics-performance-over-time', array(
		'label'         => 'Analytics — performance over time',
		'description'   => 'Time-series performance broken down by media provider. scope=global (default) is the dashboard view; scope=video|user requires id. Vendor AnalyticsController::getPerformanceOverTime treats a null/absent scope as the global dashboard path — "global" is mapped to null here (a non-empty scope that is not video|user is rejected vendor-side). Dates: start/end (YYYY-MM-DD) map to the vendor start_date/end_date; metric is plays|watch_time.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => array_merge( $date_range_schema, array(
				'scope'       => array( 'type' => 'string', 'enum' => array( 'global', 'video', 'user' ), 'default' => 'global' ),
				'id'          => array( 'type' => 'integer', 'description' => 'Required when scope = video or user.' ),
				'metric'      => array( 'type' => 'string', 'enum' => array( 'plays', 'watch_time' ), 'default' => 'plays' ),
				'granularity' => array( 'type' => 'string', 'enum' => array( 'day', 'week', 'month' ) ),
			) ),
		),
		// V5 P-H: vendor sendSuccess({data:{dates,series},metric,start_date,
		// end_date}); series = [{provider_key,name,data:[number]}] aligned to
		// dates[] (AnalyticsService::getPerformanceOverTime ->
		// buildSeriesByProvider, vendor-source-verified). The pre-P8 schema
		// declared a points[] collection the vendor never emits.
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'metric'     => array( 'type' => 'string' ),
				'start_date' => array( 'type' => 'string' ),
				'end_date'   => array( 'type' => 'string' ),
				'dates'      => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				'series'     => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'provider_key' => array( 'type' => 'string' ),
							'name'         => array( 'type' => 'string' ),
							'data'         => array( 'type' => 'array', 'items' => array( 'type' => array( 'integer', 'number' ) ) ),
						),
					),
				),
			),
		),
		'callback'      => function ( $input ) use ( $invoke ) {
			$scope = isset( $input['scope'] ) ? sanitize_key( $input['scope'] ) : 'global';
			$id    = isset( $input['id'] ) ? absint( $input['id'] ) : 0;
			if ( in_array( $scope, array( 'video', 'user' ), true ) && ! $id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'id is required when scope = ' . $scope );
			}
			// V10: the vendor rejects any non-empty scope that is not
			// video|user ("Invalid scope for performance over time."). The
			// global/dashboard path is selected by a NULL scope vendor-side.
			$vendor_scope = in_array( $scope, array( 'video', 'user' ), true ) ? $scope : null;
			// Map operator start/end -> vendor start_date/end_date (the vendor
			// reads start_date/end_date via getDateRangeFromRequest).
			if ( isset( $input['start'] ) && ! isset( $input['start_date'] ) ) {
				$input['start_date'] = $input['start'];
			}
			if ( isset( $input['end'] ) && ! isset( $input['end_date'] ) ) {
				$input['end_date'] = $input['end'];
			}
			$result = $invoke( 'getPerformanceOverTime', $input, array( 'scope' => $vendor_scope, 'id' => $id ) );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$result = is_array( $result ) ? $result : array();
			$series_raw = array();
			if ( isset( $result['data']['series'] ) && is_array( $result['data']['series'] ) ) {
				$series_raw = $result['data']['series'];
			} elseif ( isset( $result['series'] ) && is_array( $result['series'] ) ) {
				$series_raw = $result['series'];
			}
			$dates = array();
			if ( isset( $result['data']['dates'] ) && is_array( $result['data']['dates'] ) ) {
				$dates = array_values( array_map( 'strval', $result['data']['dates'] ) );
			} elseif ( isset( $result['dates'] ) && is_array( $result['dates'] ) ) {
				$dates = array_values( array_map( 'strval', $result['dates'] ) );
			}
			$series = array();
			foreach ( $series_raw as $s ) {
				$s        = (array) $s;
				$series[] = array(
					'provider_key' => (string) ( $s['provider_key'] ?? '' ),
					'name'         => (string) ( $s['name'] ?? '' ),
					'data'         => isset( $s['data'] ) && is_array( $s['data'] ) ? array_values( $s['data'] ) : array(),
				);
			}
			return array(
				'metric'     => (string) ( $result['metric'] ?? ( isset( $input['metric'] ) ? $input['metric'] : 'plays' ) ),
				'start_date' => (string) ( $result['start_date'] ?? '' ),
				'end_date'   => (string) ( $result['end_date'] ?? '' ),
				'dates'      => $dates,
				'series'     => $series,
			);
		},
	) );

	$reg->read( 'fluent-player/analytics-retention', array(
		'label'         => 'Analytics — retention curve',
		'description'   => 'Global retention curve across all videos.',
		'category'      => 'fluent-player',
		'input_schema'  => array( 'type' => 'object', 'properties' => $date_range_schema ),
		'output_schema' => fluent_abilities_schema_collection_output( 'retention_curve', array(
			'percentage'   => array( 'type' => 'integer' ),
			'viewer_count' => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $invoke ) {
			return $invoke( 'getRetention', $input );
		},
	) );

	$reg->read( 'fluent-player/analytics-devices', array(
		'label'         => 'Analytics — devices / browsers',
		'description'   => 'Device + browser breakdown in a date range.',
		'category'      => 'fluent-player',
		'input_schema'  => array( 'type' => 'object', 'properties' => $date_range_schema ),
		'output_schema' => fluent_abilities_schema_collection_output( 'devices', array(
			'device'  => array( 'type' => 'string' ),
			'browser' => array( 'type' => 'string' ),
			'views'   => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $invoke ) {
			return $invoke( 'getDevices', $input );
		},
	) );

	// ─── Per-video ─────────────────────────────────────────────────────────

	$media_id_schema = array(
		'type'       => 'object',
		'required'   => array( 'media_id' ),
		'properties' => array_merge(
			array( 'media_id' => array( 'type' => 'integer' ) ),
			$date_range_schema
		),
	);

	$with_id_callback = function ( $method ) use ( $invoke ) {
		return function ( $input ) use ( $invoke, $method ) {
			$id = absint( $input['media_id'] ?? 0 );
			if ( ! $id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'media_id is required.' );
			}
			return $invoke( $method, $input, $id );
		};
	};

	$reg->read( 'fluent-player/analytics-video-stats', array(
		'label'         => 'Analytics — single video stats',
		'description'   => 'Per-video analytics totals. Input: pass the media ID as `media_id` (an integer) — the field is `media_id`, NOT `id`.',
		'category'      => 'fluent-player',
		'input_schema'  => $media_id_schema,
		'output_schema' => fluent_abilities_schema_item_output( array(
			'media_id'         => array( 'type' => 'integer' ),
			'total_views'      => array( 'type' => 'integer' ),
			'unique_viewers'   => array( 'type' => 'integer' ),
			'total_watch_time' => array( 'type' => array( 'integer', 'number' ) ),
			'avg_watch_time'   => array( 'type' => array( 'integer', 'number' ) ),
		) ),
		'callback'      => $with_id_callback( 'getVideoStats' ),
	) );

	$reg->read( 'fluent-player/analytics-video-retention', array(
		'label'         => 'Analytics — single video retention',
		'description'   => 'Per-video retention curve.',
		'category'      => 'fluent-player',
		'input_schema'  => $media_id_schema,
		'output_schema' => fluent_abilities_schema_item_output( array(
			'media_id'        => array( 'type' => 'integer' ),
			'retention_curve' => array( 'type' => 'array' ),
		) ),
		'callback'      => $with_id_callback( 'getVideoRetention' ),
	) );

	$reg->read( 'fluent-player/analytics-video-devices', array(
		'label'         => 'Analytics — single video devices',
		'description'   => 'Per-video device + browser breakdown.',
		'category'      => 'fluent-player',
		'input_schema'  => $media_id_schema,
		'output_schema' => fluent_abilities_schema_item_output( array(
			'media_id' => array( 'type' => 'integer' ),
			'devices'  => array( 'type' => 'array' ),
		) ),
		'callback'      => $with_id_callback( 'getVideoDevices' ),
	) );

	$reg->read( 'fluent-player/analytics-video-location-breakdown', array(
		'label'         => 'Analytics — single video location breakdown',
		'description'   => 'Per-video country breakdown.',
		'category'      => 'fluent-player',
		'input_schema'  => $media_id_schema,
		'output_schema' => fluent_abilities_schema_item_output( array(
			'media_id'  => array( 'type' => 'integer' ),
			'countries' => array( 'type' => 'array' ),
		) ),
		'callback'      => $with_id_callback( 'getVideoLocationBreakdown' ),
	) );

	// SECURITY NOTE: response contains per-video viewer PII — flag for redaction in v1.2 meta-override.
	$reg->read( 'fluent-player/analytics-video-top-users', array(
		'label'         => 'Analytics — single video top viewers',
		'description'   => 'Top viewers of one video. Returns viewer PII (display_name, email).',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'media_id' ),
			'properties' => array_merge(
				array( 'media_id' => array( 'type' => 'integer' ) ),
				$date_range_schema,
				array(
					'per_page' => array( 'type' => 'integer', 'default' => 10 ),
					'page'     => array( 'type' => 'integer', 'default' => 1 ),
				)
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'media_id' => array( 'type' => 'integer' ),
			'viewers'  => array( 'type' => 'array' ),
		) ),
		'callback'      => $with_id_callback( 'getVideoTopViewers' ),
	) );

	// ─── Per-user ──────────────────────────────────────────────────────────

	$user_id_schema = array(
		'type'       => 'object',
		'required'   => array( 'user_id' ),
		'properties' => array_merge(
			array( 'user_id' => array( 'type' => 'integer' ) ),
			$date_range_schema
		),
	);

	$with_user_callback = function ( $method ) use ( $invoke ) {
		return function ( $input ) use ( $invoke, $method ) {
			$uid = absint( $input['user_id'] ?? 0 );
			if ( ! $uid ) {
				return fluent_abilities_error( 'ability_invalid_input', 'user_id is required.' );
			}
			return $invoke( $method, $input, $uid );
		};
	};

	// SECURITY NOTE: response contains user PII (display_name, email, viewing history) — flag for redaction in v1.2 meta-override.
	$reg->read( 'fluent-player/analytics-user-info', array(
		'label'         => 'Analytics — user info',
		'description'   => 'Viewer profile + lifetime totals. Returns user PII.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'user_id' ),
			'properties' => array( 'user_id' => array( 'type' => 'integer' ) ),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'user_id'          => array( 'type' => 'integer' ),
			'display_name'     => array( 'type' => 'string' ),
			'email'            => array( 'type' => 'string' ),
			'first_seen'       => array( 'type' => array( 'string', 'null' ) ),
			'last_seen'        => array( 'type' => array( 'string', 'null' ) ),
			'total_views'      => array( 'type' => 'integer' ),
			'total_watch_time' => array( 'type' => array( 'integer', 'number' ) ),
		) ),
		'callback'      => function ( $input ) use ( $invoke ) {
			$uid = absint( $input['user_id'] ?? 0 );
			if ( ! $uid ) {
				return fluent_abilities_error( 'ability_invalid_input', 'user_id is required.' );
			}
			return $invoke( 'getUser', $input, $uid );
		},
	) );

	// SECURITY NOTE: response contains per-user analytics PII — flag for redaction in v1.2 meta-override.
	$reg->read( 'fluent-player/analytics-user-stats', array(
		'label'         => 'Analytics — user stats',
		'description'   => 'Per-user analytics totals in a date range. Returns user PII.',
		'category'      => 'fluent-player',
		'input_schema'  => $user_id_schema,
		'output_schema' => fluent_abilities_schema_item_output( array(
			'user_id'          => array( 'type' => 'integer' ),
			'total_views'      => array( 'type' => 'integer' ),
			'total_watch_time' => array( 'type' => array( 'integer', 'number' ) ),
			'avg_watch_time'   => array( 'type' => array( 'integer', 'number' ) ),
		) ),
		'callback'      => $with_user_callback( 'getUserStats' ),
	) );

	// SECURITY NOTE: response contains per-user viewing history PII — flag for redaction in v1.2 meta-override.
	$reg->read( 'fluent-player/analytics-user-top-videos', array(
		'label'         => 'Analytics — user top videos',
		'description'   => 'Top videos watched by one user. Returns user PII.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'user_id' ),
			'properties' => array_merge(
				array( 'user_id' => array( 'type' => 'integer' ) ),
				$date_range_schema,
				array(
					'per_page' => array( 'type' => 'integer', 'default' => 10 ),
					'page'     => array( 'type' => 'integer', 'default' => 1 ),
				)
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'user_id' => array( 'type' => 'integer' ),
			'videos'  => array( 'type' => 'array' ),
		) ),
		'callback'      => $with_user_callback( 'getUserTopVideos' ),
	) );

	// SECURITY NOTE: response contains per-user retention PII — flag for redaction in v1.2 meta-override.
	$reg->read( 'fluent-player/analytics-user-retention', array(
		'label'         => 'Analytics — user retention',
		'description'   => 'Per-user retention curve. Returns user PII.',
		'category'      => 'fluent-player',
		'input_schema'  => $user_id_schema,
		'output_schema' => fluent_abilities_schema_item_output( array(
			'user_id'         => array( 'type' => 'integer' ),
			'retention_curve' => array( 'type' => 'array' ),
		) ),
		'callback'      => $with_user_callback( 'getUserRetention' ),
	) );
}
add_action( 'wp_abilities_api_init', 'fluent_abilities_player_register_analytics_abilities', 100 );
