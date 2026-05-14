<?php
/**
 * FluentPlayer Abilities — Mux (all Pro)
 *
 * 24 abilities in the `fluent-player` category wrapping the Pro MuxController.
 * Covers assets, direct uploads, tracks (subtitles), live streams, playback
 * restrictions, delivery usage, signing keys, and asset captions.
 *
 * The public no-auth `mux/webhook` route is intentionally NOT registered
 * (frontend ingestion endpoint, not an operator action).
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

function fluent_abilities_player_register_mux_abilities() {

	if ( ! defined( 'FLUENT_PLAYER_PRO_VERSION' ) ) {
		return;
	}

	$reg = new Fluent_Abilities_Registrar( 'player' );

	$mux_call = function ( $method, $input, $extra_args = array() ) {
		// Mux $extra_args are positional after Request — but Mux controller
		// methods name them consistently ($id for asset/upload/stream/restriction/
		// signing_key, $assetId/$trackId for two-arg methods). Map positionals
		// to canonical names so the container resolves them by name.
		$params = array();
		if ( ! empty( $extra_args ) ) {
			if ( count( $extra_args ) === 1 ) {
				$params['id'] = $extra_args[0];
			} elseif ( count( $extra_args ) === 2 ) {
				$params['assetId'] = $extra_args[0];
				$params['trackId'] = $extra_args[1];
			}
		}
		$result = fluent_abilities_player_invoke_controller(
			'\FluentPlayerPro\App\Http\Controllers\MuxController',
			$method,
			is_array( $input ) ? $input : array(),
			$params
		);
		// Signing-key endpoints return private_key (only on create); list/delete
		// surfaces don't. Redact via shared helper — idempotent on non-secret
		// responses. Per Reviewer pre-flight #1.
		return is_wp_error( $result ) ? $result : fluent_abilities_player_redact( $result );
	};

	// ─── Assets ────────────────────────────────────────────────────────────

	$reg->read( 'fluent-player/mux-list-assets', array(
		'label'         => 'Mux — list assets',
		'description'   => 'Paginated list of Mux assets on the connected account.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => array(
				'page'  => array( 'type' => 'integer', 'default' => 1 ),
				'limit' => array( 'type' => 'integer', 'default' => 25 ),
			),
		),
		'output_schema' => fluent_abilities_player_loose_collection_schema( 'data' ),
		'callback'      => function ( $input ) use ( $mux_call ) {
			$result = $mux_call( 'getAssets', $input, array() );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$items = $result['data'] ?? ( is_array( $result ) ? array_values( $result ) : array() );
			return array( 'data' => $items, 'total' => is_array( $items ) ? count( $items ) : 0 );
		},
	) );

	$reg->read( 'fluent-player/mux-get-asset', array(
		'label'         => 'Mux — get asset',
		'description'   => 'Get a single Mux asset by ID.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'asset_id' ),
			'properties' => array( 'asset_id' => array( 'type' => 'string' ) ),
		),
		'output_schema' => fluent_abilities_schema_item_output( array( 'data' => array( 'type' => array( 'object', 'null' ) ) ) ),
		'callback'      => function ( $input ) use ( $mux_call ) {
			$id = isset( $input['asset_id'] ) ? sanitize_text_field( $input['asset_id'] ) : '';
			if ( '' === $id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'asset_id is required.' );
			}
			$r = $mux_call( 'getAsset', $input, array( $id ) );
			return is_wp_error( $r ) ? $r : array( 'data' => $r['data'] ?? $r );
		},
	) );

	$reg->write( 'fluent-player/mux-create-asset', array(
		'label'         => 'Mux — create asset',
		'description'   => 'Async ingest from an input URL. Success means the asset is queued, not ready — poll mux-get-asset to learn final status.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'input_url' ),
			'properties' => array(
				'input_url'       => array( 'type' => 'string', 'format' => 'uri' ),
				'playback_policy' => array( 'type' => 'string', 'enum' => array( 'public', 'signed' ), 'default' => 'public' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array( 'data' => array( 'type' => array( 'object', 'null' ) ) ) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function ( $input ) use ( $mux_call ) {
			if ( empty( $input['input_url'] ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'input_url is required.' );
			}
			$r = $mux_call( 'createAsset', $input, array() );
			return is_wp_error( $r ) ? $r : array( 'success' => true, 'data' => $r['data'] ?? $r );
		},
	) );

	$reg->write( 'fluent-player/mux-update-asset', array(
		'label'         => 'Mux — update asset (passthrough)',
		'description'   => 'Update a Mux asset passthrough string (caller-defined metadata).',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'asset_id' ),
			'properties' => array(
				'asset_id'    => array( 'type' => 'string' ),
				'passthrough' => array( 'type' => 'string', 'maxLength' => 255 ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array( 'data' => array( 'type' => array( 'object', 'null' ) ) ) ),
		'callback'      => function ( $input ) use ( $mux_call ) {
			$id = isset( $input['asset_id'] ) ? sanitize_text_field( $input['asset_id'] ) : '';
			if ( '' === $id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'asset_id is required.' );
			}
			$r = $mux_call( 'updateAsset', $input, array( $id ) );
			return is_wp_error( $r ) ? $r : array( 'success' => true, 'data' => $r['data'] ?? $r );
		},
	) );

	$reg->delete( 'fluent-player/mux-delete-asset', array(
		'label'         => 'Mux — delete asset',
		'description'   => 'Permanently delete a Mux asset.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'asset_id' ),
			'properties' => array( 'asset_id' => array( 'type' => 'string' ) ),
		),
		'output_schema' => fluent_abilities_schema_success_output( array( 'message' => array( 'type' => 'string' ) ) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function ( $input ) use ( $mux_call ) {
			$id = isset( $input['asset_id'] ) ? sanitize_text_field( $input['asset_id'] ) : '';
			if ( '' === $id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'asset_id is required.' );
			}
			$r = $mux_call( 'deleteAsset', $input, array( $id ) );
			return is_wp_error( $r ) ? $r : array( 'success' => true, 'message' => $r['message'] ?? 'Asset deleted.' );
		},
	) );

	$reg->write( 'fluent-player/mux-update-asset-mp4-support', array(
		'label'         => 'Mux — update asset MP4 support',
		'description'   => "Update an asset's MP4 support tier (controls whether Mux generates downloadable MP4 renditions).",
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'asset_id', 'mp4_support' ),
			'properties' => array(
				'asset_id'    => array( 'type' => 'string' ),
				'mp4_support' => array( 'type' => 'string', 'enum' => array( 'standard', 'capped-1080p', 'audio-only', 'none' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array( 'data' => array( 'type' => array( 'object', 'null' ) ) ) ),
		'callback'      => function ( $input ) use ( $mux_call ) {
			$id = isset( $input['asset_id'] ) ? sanitize_text_field( $input['asset_id'] ) : '';
			if ( '' === $id || empty( $input['mp4_support'] ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'asset_id and mp4_support are required.' );
			}
			$r = $mux_call( 'updateMp4Support', $input, array( $id ) );
			return is_wp_error( $r ) ? $r : array( 'success' => true, 'data' => $r['data'] ?? $r );
		},
	) );

	// ─── Direct uploads ────────────────────────────────────────────────────

	$reg->write( 'fluent-player/mux-create-upload', array(
		'label'         => 'Mux — create direct upload URL',
		'description'   => 'Issue a signed Mux upload URL for browser-direct upload. The file is NOT transferred through WordPress; the browser POSTs directly to Mux.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => array(
				'passthrough' => array( 'type' => 'string' ),
				'cors_origin' => array( 'type' => 'string', 'format' => 'uri' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array( 'data' => array( 'type' => array( 'object', 'null' ) ) ) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function ( $input ) use ( $mux_call ) {
			$r = $mux_call( 'createUpload', $input, array() );
			return is_wp_error( $r ) ? $r : array( 'success' => true, 'data' => $r['data'] ?? $r );
		},
	) );

	$reg->read( 'fluent-player/mux-get-upload-status', array(
		'label'         => 'Mux — get upload status',
		'description'   => 'Poll a Mux direct-upload by ID to learn its status + resolved asset_id.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'upload_id' ),
			'properties' => array( 'upload_id' => array( 'type' => 'string' ) ),
		),
		'output_schema' => fluent_abilities_schema_item_output( array( 'data' => array( 'type' => array( 'object', 'null' ) ) ) ),
		'callback'      => function ( $input ) use ( $mux_call ) {
			$id = isset( $input['upload_id'] ) ? sanitize_text_field( $input['upload_id'] ) : '';
			if ( '' === $id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'upload_id is required.' );
			}
			$r = $mux_call( 'getUploadStatus', $input, array( $id ) );
			return is_wp_error( $r ) ? $r : array( 'data' => $r['data'] ?? $r );
		},
	) );

	// ─── Tracks ────────────────────────────────────────────────────────────

	$reg->write( 'fluent-player/mux-create-track', array(
		'label'         => 'Mux — create asset track',
		'description'   => 'Add a subtitle / caption / chapters / metadata track to an asset by URL.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'asset_id', 'url', 'type', 'text_type', 'language_code' ),
			'properties' => array(
				'asset_id'      => array( 'type' => 'string' ),
				'url'           => array( 'type' => 'string', 'format' => 'uri' ),
				'type'          => array( 'type' => 'string', 'enum' => array( 'text', 'video', 'audio' ) ),
				'text_type'     => array( 'type' => 'string', 'enum' => array( 'subtitles', 'captions', 'descriptions', 'chapters', 'metadata' ) ),
				'language_code' => array( 'type' => 'string', 'description' => 'BCP 47 language code.' ),
				'name'          => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array( 'data' => array( 'type' => array( 'object', 'null' ) ) ) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function ( $input ) use ( $mux_call ) {
			$id = isset( $input['asset_id'] ) ? sanitize_text_field( $input['asset_id'] ) : '';
			if ( '' === $id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'asset_id is required.' );
			}
			$r = $mux_call( 'createTrack', $input, array( $id ) );
			return is_wp_error( $r ) ? $r : array( 'success' => true, 'data' => $r['data'] ?? $r );
		},
	) );

	$reg->delete( 'fluent-player/mux-delete-track', array(
		'label'         => 'Mux — delete asset track',
		'description'   => 'Remove a track from an asset.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'asset_id', 'track_id' ),
			'properties' => array(
				'asset_id' => array( 'type' => 'string' ),
				'track_id' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array( 'message' => array( 'type' => 'string' ) ) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function ( $input ) use ( $mux_call ) {
			$asset_id = isset( $input['asset_id'] ) ? sanitize_text_field( $input['asset_id'] ) : '';
			$track_id = isset( $input['track_id'] ) ? sanitize_text_field( $input['track_id'] ) : '';
			if ( '' === $asset_id || '' === $track_id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'asset_id and track_id are required.' );
			}
			$r = $mux_call( 'deleteTrack', $input, array( $asset_id, $track_id ) );
			return is_wp_error( $r ) ? $r : array( 'success' => true, 'message' => $r['message'] ?? 'Track deleted.' );
		},
	) );

	$reg->write( 'fluent-player/mux-generate-track-subtitles', array(
		'label'         => 'Mux — generate subtitles for track',
		'description'   => 'Trigger Mux subtitle auto-generation for an existing track.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'asset_id', 'track_id' ),
			'properties' => array(
				'asset_id'      => array( 'type' => 'string' ),
				'track_id'      => array( 'type' => 'string' ),
				'language_code' => array( 'type' => 'string', 'default' => 'en' ),
				'name'          => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array( 'data' => array( 'type' => array( 'object', 'null' ) ) ) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function ( $input ) use ( $mux_call ) {
			$asset_id = isset( $input['asset_id'] ) ? sanitize_text_field( $input['asset_id'] ) : '';
			$track_id = isset( $input['track_id'] ) ? sanitize_text_field( $input['track_id'] ) : '';
			if ( '' === $asset_id || '' === $track_id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'asset_id and track_id are required.' );
			}
			$r = $mux_call( 'generateSubtitles', $input, array( $asset_id, $track_id ) );
			return is_wp_error( $r ) ? $r : array( 'success' => true, 'data' => $r['data'] ?? $r );
		},
	) );

	// ─── Live streams ──────────────────────────────────────────────────────

	$reg->read( 'fluent-player/mux-list-live-streams', array(
		'label'         => 'Mux — list live streams',
		'description'   => 'List Mux live streams on the connected account.',
		'category'      => 'fluent-player',
		'output_schema' => fluent_abilities_player_loose_collection_schema( 'data' ),
		'callback'      => function ( $input ) use ( $mux_call ) {
			$r = $mux_call( 'getLiveStreams', $input );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			$items = $r['data'] ?? ( is_array( $r ) ? array_values( $r ) : array() );
			return array( 'data' => $items, 'total' => is_array( $items ) ? count( $items ) : 0 );
		},
	) );

	$reg->write( 'fluent-player/mux-create-live-stream', array(
		'label'         => 'Mux — create live stream',
		'description'   => 'Provision a new Mux live stream. Returns stream key + playback IDs.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => array(
				'playback_policy'  => array( 'type' => 'string', 'enum' => array( 'public', 'signed' ), 'default' => 'public' ),
				'reconnect_window' => array( 'type' => 'integer', 'default' => 60 ),
				'latency_mode'     => array( 'type' => 'string', 'enum' => array( 'low', 'reduced', 'standard' ), 'default' => 'standard' ),
				'name'             => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array( 'data' => array( 'type' => array( 'object', 'null' ) ) ) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function ( $input ) use ( $mux_call ) {
			$r = $mux_call( 'createLiveStream', $input, array() );
			return is_wp_error( $r ) ? $r : array( 'success' => true, 'data' => $r['data'] ?? $r );
		},
	) );

	$reg->read( 'fluent-player/mux-get-live-stream', array(
		'label'         => 'Mux — get live stream',
		'description'   => 'Get a single Mux live stream by ID.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'stream_id' ),
			'properties' => array( 'stream_id' => array( 'type' => 'string' ) ),
		),
		'output_schema' => fluent_abilities_schema_item_output( array( 'data' => array( 'type' => array( 'object', 'null' ) ) ) ),
		'callback'      => function ( $input ) use ( $mux_call ) {
			$id = isset( $input['stream_id'] ) ? sanitize_text_field( $input['stream_id'] ) : '';
			if ( '' === $id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'stream_id is required.' );
			}
			$r = $mux_call( 'getLiveStream', $input, array( $id ) );
			return is_wp_error( $r ) ? $r : array( 'data' => $r['data'] ?? $r );
		},
	) );

	$reg->delete( 'fluent-player/mux-delete-live-stream', array(
		'label'         => 'Mux — delete live stream',
		'description'   => 'Permanently delete a Mux live stream.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'stream_id' ),
			'properties' => array( 'stream_id' => array( 'type' => 'string' ) ),
		),
		'output_schema' => fluent_abilities_schema_success_output( array( 'message' => array( 'type' => 'string' ) ) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function ( $input ) use ( $mux_call ) {
			$id = isset( $input['stream_id'] ) ? sanitize_text_field( $input['stream_id'] ) : '';
			if ( '' === $id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'stream_id is required.' );
			}
			$r = $mux_call( 'deleteLiveStream', $input, array( $id ) );
			return is_wp_error( $r ) ? $r : array( 'success' => true, 'message' => $r['message'] ?? 'Live stream deleted.' );
		},
	) );

	$reg->write( 'fluent-player/mux-reset-stream-key', array(
		'label'         => 'Mux — reset stream key',
		'description'   => 'Rotate the stream key for a Mux live stream.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'stream_id' ),
			'properties' => array( 'stream_id' => array( 'type' => 'string' ) ),
		),
		'output_schema' => fluent_abilities_schema_success_output( array( 'data' => array( 'type' => array( 'object', 'null' ) ) ) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function ( $input ) use ( $mux_call ) {
			$id = isset( $input['stream_id'] ) ? sanitize_text_field( $input['stream_id'] ) : '';
			if ( '' === $id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'stream_id is required.' );
			}
			$r = $mux_call( 'resetStreamKey', $input, array( $id ) );
			return is_wp_error( $r ) ? $r : array( 'success' => true, 'data' => $r['data'] ?? $r );
		},
	) );

	// ─── Playback restrictions ─────────────────────────────────────────────

	$reg->read( 'fluent-player/mux-list-playback-restrictions', array(
		'label'         => 'Mux — list playback restrictions',
		'description'   => 'List Mux playback restrictions on the connected account.',
		'category'      => 'fluent-player',
		'output_schema' => fluent_abilities_player_loose_collection_schema( 'data' ),
		'callback'      => function ( $input ) use ( $mux_call ) {
			$r = $mux_call( 'getPlaybackRestrictions', $input );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			$items = $r['data'] ?? ( is_array( $r ) ? array_values( $r ) : array() );
			return array( 'data' => $items, 'total' => is_array( $items ) ? count( $items ) : 0 );
		},
	) );

	$reg->write( 'fluent-player/mux-create-playback-restriction', array(
		'label'         => 'Mux — create playback restriction',
		'description'   => 'Create a Mux playback restriction with an allowed-domain list.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'allowed_domains' ),
			'properties' => array(
				'allowed_domains' => array(
					'type'        => 'string',
					'description' => 'Comma-separated allowed domain list (e.g. "example.com,*.example.com").',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array( 'data' => array( 'type' => array( 'object', 'null' ) ) ) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function ( $input ) use ( $mux_call ) {
			if ( empty( $input['allowed_domains'] ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'allowed_domains is required.' );
			}
			$r = $mux_call( 'createPlaybackRestriction', $input, array() );
			return is_wp_error( $r ) ? $r : array( 'success' => true, 'data' => $r['data'] ?? $r );
		},
	) );

	$reg->delete( 'fluent-player/mux-delete-playback-restriction', array(
		'label'         => 'Mux — delete playback restriction',
		'description'   => 'Permanently delete a Mux playback restriction.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'restriction_id' ),
			'properties' => array( 'restriction_id' => array( 'type' => 'string' ) ),
		),
		'output_schema' => fluent_abilities_schema_success_output( array( 'message' => array( 'type' => 'string' ) ) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function ( $input ) use ( $mux_call ) {
			$id = isset( $input['restriction_id'] ) ? sanitize_text_field( $input['restriction_id'] ) : '';
			if ( '' === $id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'restriction_id is required.' );
			}
			$r = $mux_call( 'deletePlaybackRestriction', $input, array( $id ) );
			return is_wp_error( $r ) ? $r : array( 'success' => true, 'message' => $r['message'] ?? 'Playback restriction deleted.' );
		},
	) );

	// ─── Delivery usage ────────────────────────────────────────────────────

	$reg->read( 'fluent-player/mux-get-delivery-usage', array(
		'label'         => 'Mux — get delivery usage',
		'description'   => 'Get Mux delivery usage breakdown for a timeframe. Vendor expects timeframe[] array form internally — pass the timeframe value verbatim.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => array(
				'timeframe' => array(
					'type'        => 'string',
					'description' => 'Mux timeframe value (e.g. "24:hours", "7:days", "30:days").',
					'default'     => '7:days',
				),
			),
		),
		'output_schema' => fluent_abilities_player_loose_collection_schema( 'data' ),
		'callback'      => function ( $input ) use ( $mux_call ) {
			$r = $mux_call( 'getDeliveryUsage', $input, array() );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			$items = $r['data'] ?? ( is_array( $r ) ? array_values( $r ) : array() );
			return array( 'data' => $items, 'total' => is_array( $items ) ? count( $items ) : 0 );
		},
	) );

	// ─── Signing keys (secret-bearing) ─────────────────────────────────────

	// SECURITY NOTE: signing-key surface — flag for mcp.public=false in v1.2 meta-override.
	$reg->read( 'fluent-player/mux-list-signing-keys', array(
		'label'         => 'Mux — list signing keys',
		'description'   => 'List Mux signing keys on the connected account (IDs + created_at only; private keys never re-exposed).',
		'category'      => 'fluent-player',
		'output_schema' => fluent_abilities_player_loose_collection_schema( 'data' ),
		'callback'      => function ( $input ) use ( $mux_call ) {
			$r = $mux_call( 'getSigningKeys', $input );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			$items = $r['data'] ?? ( is_array( $r ) ? array_values( $r ) : array() );
			return array( 'data' => $items, 'total' => is_array( $items ) ? count( $items ) : 0 );
		},
	) );

	// SECURITY NOTE: response contains Mux signing-key private material (only returned once) — flag for mcp.public=false + redaction in v1.2 meta-override.
	$reg->write( 'fluent-player/mux-create-signing-key', array(
		'label'         => 'Mux — create signing key',
		'description'   => 'Generate a new Mux signing key. Private key is only returned at creation; set store=true to persist it in FluentPlayer settings via createAndStoreSigningKey.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => array(
				'store' => array(
					'type'        => 'boolean',
					'description' => 'When true, calls createAndStoreSigningKey() to also persist the key.',
					'default'     => false,
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array( 'data' => array( 'type' => array( 'object', 'null' ) ) ) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function ( $input ) use ( $mux_call ) {
			$store = ! empty( $input['store'] );
			$r     = $mux_call( $store ? 'createAndStoreSigningKey' : 'createSigningKey', $input );
			return is_wp_error( $r ) ? $r : array( 'success' => true, 'data' => $r['data'] ?? $r );
		},
	) );

	// SECURITY NOTE: invalidates Mux JWTs immediately — flag for mcp.public=false in v1.2 meta-override.
	$reg->delete( 'fluent-player/mux-delete-signing-key', array(
		'label'         => 'Mux — delete signing key',
		'description'   => 'Delete a Mux signing key. Immediately invalidates any JWTs signed with it.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'signing_key_id' ),
			'properties' => array( 'signing_key_id' => array( 'type' => 'string' ) ),
		),
		'output_schema' => fluent_abilities_schema_success_output( array( 'message' => array( 'type' => 'string' ) ) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function ( $input ) use ( $mux_call ) {
			$id = isset( $input['signing_key_id'] ) ? sanitize_text_field( $input['signing_key_id'] ) : '';
			if ( '' === $id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'signing_key_id is required.' );
			}
			$r = $mux_call( 'deleteSigningKey', $input, array( $id ) );
			return is_wp_error( $r ) ? $r : array( 'success' => true, 'message' => $r['message'] ?? 'Signing key deleted.' );
		},
	) );

	// ─── Asset captions ────────────────────────────────────────────────────

	$reg->read( 'fluent-player/mux-get-asset-captions', array(
		'label'         => 'Mux — get asset captions',
		'description'   => 'List caption / subtitle tracks attached to a Mux asset.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'asset_id' ),
			'properties' => array( 'asset_id' => array( 'type' => 'string' ) ),
		),
		'output_schema' => fluent_abilities_player_loose_collection_schema( 'items' ),
		'callback'      => function ( $input ) use ( $mux_call ) {
			$id = isset( $input['asset_id'] ) ? sanitize_text_field( $input['asset_id'] ) : '';
			if ( '' === $id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'asset_id is required.' );
			}
			$r = $mux_call( 'getAssetCaptions', $input, array( $id ) );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			$items = $r['items'] ?? ( is_array( $r ) ? array_values( $r ) : array() );
			return array( 'items' => $items, 'total' => is_array( $items ) ? count( $items ) : 0 );
		},
	) );
}
add_action( 'wp_abilities_api_init', 'fluent_abilities_player_register_mux_abilities', 100 );
