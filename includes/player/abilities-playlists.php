<?php
/**
 * FluentPlayer Abilities — Playlists, Subtitles, Timed Content (all Pro)
 *
 * 11 abilities in the `fluent-player` category:
 *  - Cluster 10 Playlists (5, CPT `fluent_playlist`)
 *  - Cluster 11 Subtitles (5, stored in media.settings.subtitles + YouTube captions)
 *  - Cluster 12 Timed Content (1, chapters + overlays mutator)
 *
 * Whole file is Pro-only.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

function fluent_abilities_player_register_playlists_abilities() {

	if ( ! defined( 'FLUENT_PLAYER_PRO_VERSION' ) ) {
		return;
	}

	$reg = new Fluent_Abilities_Registrar( 'player' );

	// ─── Cluster 10: Playlists ─────────────────────────────────────────────

	$reg->read( 'fluent-player/list-playlists', array(
		'label'         => 'List playlists',
		'description'   => 'Paginated list of FluentPlayer Pro playlists (CPT fluent_playlist).',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => array(
				'per_page' => array( 'type' => 'integer', 'default' => 10 ),
				'page'     => array( 'type' => 'integer', 'default' => 1 ),
				'status'   => array( 'type' => 'string' ),
				'query'    => array( 'type' => 'string', 'description' => 'Search query.' ),
				'orderby'  => array( 'type' => 'string', 'enum' => array( 'ID', 'post_title', 'post_date', 'post_status', 'post_name' ) ),
				'order'    => array( 'type' => 'string', 'enum' => array( 'ASC', 'DESC' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'playlists', array(
			'ID'          => array( 'type' => 'integer' ),
			'post_title'  => array( 'type' => 'string' ),
			'post_status' => array( 'type' => 'string' ),
			'post_date'   => array( 'type' => 'string' ),
			'post_name'   => array( 'type' => 'string' ),
			'settings'    => array( 'type' => array( 'object', 'null' ) ),
		) ),
		'callback'      => function ( $input ) {
			if ( ! class_exists( '\FluentPlayerPro\App\Models\Playlist' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentPlayerPro Playlist model not found.' );
			}
			$pg = fluent_abilities_pagination( $input, 10 );
			try {
				$query = \FluentPlayerPro\App\Models\Playlist::query();
				if ( ! empty( $input['status'] ) ) {
					$query->where( 'post_status', sanitize_text_field( $input['status'] ) );
				}
				if ( ! empty( $input['query'] ) ) {
					$search = sanitize_text_field( $input['query'] );
					$query->where( function ( $q ) use ( $search ) {
						$q->where( 'post_title', 'LIKE', '%' . $search . '%' )
							->orWhere( 'post_content', 'LIKE', '%' . $search . '%' );
					} );
				}
				$orderby = ! empty( $input['orderby'] ) ? sanitize_key( $input['orderby'] ) : 'ID';
				$order   = ( ! empty( $input['order'] ) && strtoupper( $input['order'] ) === 'ASC' ) ? 'ASC' : 'DESC';
				$total   = (int) ( clone $query )->count();
				$rows    = $query->orderBy( $orderby, $order )->limit( $pg['per_page'] )->offset( $pg['offset'] )->get();
				$items   = array();
				foreach ( $rows as $r ) {
					$arr           = method_exists( $r, 'toArray' ) ? $r->toArray() : (array) $r;
					$arr['ID']     = (int) ( $arr['ID'] ?? 0 );
					$items[]       = $arr;
				}
				return array(
					'total'     => $total,
					'page'      => $pg['page'],
					'per_page'  => $pg['per_page'],
					'playlists' => $items,
				);
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'execution_failed', $e->getMessage() );
			}
		},
	) );

	$reg->read( 'fluent-player/get-playlist', array(
		'label'         => 'Get playlist',
		'description'   => 'Get a playlist by ID with full settings.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array( 'id' => array( 'type' => 'integer' ) ),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'ID'          => array( 'type' => 'integer' ),
			'post_title'  => array( 'type' => 'string' ),
			'post_status' => array( 'type' => 'string' ),
			'settings'    => array( 'type' => array( 'object', 'null' ) ),
		) ),
		'callback'      => function ( $input ) {
			$id = absint( $input['id'] ?? 0 );
			if ( ! $id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'id is required.' );
			}
			if ( ! class_exists( '\FluentPlayerPro\App\Models\Playlist' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentPlayerPro Playlist model not found.' );
			}
			try {
				$row = \FluentPlayerPro\App\Models\Playlist::find( $id );
				if ( ! $row ) {
					return fluent_abilities_error( 'not_found', 'Playlist not found: ' . $id );
				}
				$arr       = method_exists( $row, 'toArray' ) ? $row->toArray() : (array) $row;
				$arr['ID'] = (int) ( $arr['ID'] ?? $id );
				return $arr;
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'execution_failed', $e->getMessage() );
			}
		},
	) );

	$reg->write( 'fluent-player/create-playlist', array(
		'label'         => 'Create playlist',
		'description'   => 'Create a Pro playlist (standard / grid / learning layouts).',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'title' ),
			'properties' => array(
				'title'    => array( 'type' => 'string' ),
				'settings' => array(
					'type'        => 'object',
					'description' => 'Layout / appearance / behavior / typography / grid / learning sub-objects. Vendor PlaylistSettingsHelper::VALIDATION_RULES is runtime-authoritative.',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'ID'          => array( 'type' => 'integer' ),
			'post_title'  => array( 'type' => 'string' ),
			'post_status' => array( 'type' => 'string' ),
			'settings'    => array( 'type' => array( 'object', 'null' ) ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function ( $input ) {
			$title = isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '';
			if ( '' === $title ) {
				return fluent_abilities_error( 'ability_invalid_input', 'title is required.' );
			}
			$input['title']    = $title;
			$input['settings'] = $input['settings'] ?? array();
			$result            = fluent_abilities_player_invoke_controller(
				'\FluentPlayerPro\App\Http\Controllers\PlaylistController',
				'store',
				$input
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$row = fluent_abilities_safe_array( is_array( $result ) ? ( $result['playlist'] ?? $result ) : array() );
			// V3/V9/P-L: vendor PlaylistController::store does NOT reload the
			// model (contrast update(), which does Playlist::find($id)) — the
			// serialized $playlist often carries no top-level `ID` (→ the
			// pre-P8 `ID:0`, settings:null). Resolve the real persisted id
			// (accept ID|id|post_id from the model) and, if still unresolved,
			// READ BACK the newest matching playlist via the vendor model, so
			// the returned id is the actually-persisted record (V3 read-back).
			$pid = (int) ( $row['ID'] ?? ( $row['id'] ?? ( $row['post_id'] ?? 0 ) ) );
			$persisted = array();
			if ( class_exists( '\FluentPlayerPro\App\Models\Playlist' ) ) {
				try {
					if ( $pid > 0 ) {
						$found = \FluentPlayerPro\App\Models\Playlist::find( $pid );
					} else {
						$found = \FluentPlayerPro\App\Models\Playlist::query()
							->where( 'post_title', $title )
							->orderBy( 'ID', 'DESC' )
							->first();
					}
					if ( $found ) {
						$persisted = method_exists( $found, 'toArray' ) ? $found->toArray() : (array) $found;
						$pid       = (int) ( $persisted['ID'] ?? ( $persisted['id'] ?? $pid ) );
					}
				} catch ( \Throwable $e ) {
					$persisted = array();
				}
			}
			if ( $pid < 1 ) {
				return fluent_abilities_error( 'ability_execution_failed', 'Playlist create did not return a persisted id (vendor store() returned no resolvable ID and read-back found no matching playlist).' );
			}
			return array(
				'success'     => true,
				'ID'          => $pid,
				'post_title'  => (string) ( $persisted['post_title'] ?? ( $row['post_title'] ?? $title ) ),
				'post_status' => (string) ( $persisted['post_status'] ?? ( $row['post_status'] ?? 'publish' ) ),
				'settings'    => $persisted['settings'] ?? ( $row['settings'] ?? null ),
			);
		},
	) );

	$reg->write( 'fluent-player/update-playlist', array(
		'label'         => 'Update playlist',
		'description'   => 'Update an existing playlist (title and/or settings sub-objects).',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'       => array( 'type' => 'integer' ),
				'title'    => array( 'type' => 'string' ),
				'settings' => array( 'type' => 'object' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'ID'         => array( 'type' => 'integer' ),
			'settings'   => array( 'type' => array( 'object', 'null' ) ),
		) ),
		'callback'      => function ( $input ) {
			$id = absint( $input['id'] ?? 0 );
			if ( ! $id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'id is required.' );
			}
			// V10/P-L: vendor PlaylistController::update does NOT 404 on a
			// nonexistent id — preparePlaylist() does `new Playlist(); $p->id
			// = $id; ->save()` (an upsert-by-id) then `Playlist::find($id)`,
			// so a bogus id previously returned success:true with a null
			// playlist. Validate existence first → typed not_found, never a
			// false success on a nonexistent playlist.
			if ( ! class_exists( '\FluentPlayerPro\App\Models\Playlist' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentPlayerPro Playlist model not found.' );
			}
			if ( ! \FluentPlayerPro\App\Models\Playlist::find( $id ) ) {
				return fluent_abilities_error( 'not_found', 'Playlist not found: ' . $id );
			}
			if ( isset( $input['title'] ) ) {
				$input['title'] = sanitize_text_field( $input['title'] );
			}
			$result = fluent_abilities_player_invoke_controller(
				'\FluentPlayerPro\App\Http\Controllers\PlaylistController',
				'update',
				$input,
				array( 'id' => $id )
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$row = fluent_abilities_safe_array( is_array( $result ) ? ( $result['playlist'] ?? $result ) : array() );
			return array(
				'success'  => true,
				'ID'       => (int) ( $row['ID'] ?? ( $row['id'] ?? $id ) ),
				'settings' => $row['settings'] ?? null,
			);
		},
	) );

	$reg->delete( 'fluent-player/delete-playlist', array(
		'label'         => 'Delete playlist',
		'description'   => 'Permanently delete a playlist (hard delete via wp_delete_post).',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array( 'id' => array( 'type' => 'integer' ) ),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'message' => array( 'type' => 'string' ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function ( $input ) {
			$id = absint( $input['id'] ?? 0 );
			if ( ! $id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'id is required.' );
			}
			$result = fluent_abilities_player_invoke_controller(
				'\FluentPlayerPro\App\Http\Controllers\PlaylistController',
				'delete',
				is_array( $input ) ? $input : array(),
				array( 'id' => $id )
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array(
				'success' => true,
				'message' => is_array( $result ) ? ( $result['message'] ?? 'Playlist deleted.' ) : 'Playlist deleted.',
			);
		},
	) );

	// ─── Cluster 11: Subtitles ─────────────────────────────────────────────

	$reg->write( 'fluent-player/upload-subtitle', array(
		'label'         => 'Upload subtitle',
		'description'   => 'Attach a subtitle file (pre-uploaded to WP Media Library) to a media item. File must already exist as an attachment.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'media_id', 'attachment_id' ),
			'properties' => array(
				'media_id'      => array( 'type' => 'integer' ),
				'attachment_id' => array( 'type' => 'integer', 'description' => 'WP Media Library attachment ID for the subtitle file.' ),
				'language'      => array( 'type' => 'string', 'maxLength' => 10 ),
				'label'         => array( 'type' => 'string', 'maxLength' => 100 ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'message' => array( 'type' => 'string' ),
			'data'    => array( 'type' => array( 'object', 'null' ) ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function ( $input ) {
			$media_id      = absint( $input['media_id'] ?? 0 );
			$attachment_id = absint( $input['attachment_id'] ?? 0 );
			if ( ! $media_id || ! $attachment_id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'media_id and attachment_id are required.' );
			}
			$input['attachment_id'] = $attachment_id;
			$input['language']      = isset( $input['language'] ) ? sanitize_text_field( $input['language'] ) : '';
			$input['label']         = isset( $input['label'] ) ? sanitize_text_field( $input['label'] ) : '';
			$result = fluent_abilities_player_invoke_controller(
				'\FluentPlayerPro\App\Http\Controllers\SubtitleController',
				'uploadSubtitle',
				$input,
				array( 'mediaId' => $media_id )
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array(
				'success' => true,
				'message' => is_array( $result ) ? ( $result['message'] ?? 'Subtitle uploaded.' ) : 'Subtitle uploaded.',
				'data'    => fluent_abilities_safe_array( is_array( $result ) ? ( $result['data'] ?? $result ) : null ),
			);
		},
	) );

	$reg->delete( 'fluent-player/remove-subtitle', array(
		'label'         => 'Remove subtitle',
		'description'   => 'Remove a subtitle from a media item by subtitle ID.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'media_id', 'subtitle_id' ),
			'properties' => array(
				'media_id'    => array( 'type' => 'integer' ),
				'subtitle_id' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'removed'        => array( 'type' => 'boolean' ),
			'media_settings' => array( 'type' => array( 'object', 'null' ) ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function ( $input ) {
			$media_id    = absint( $input['media_id'] ?? 0 );
			$subtitle_id = isset( $input['subtitle_id'] ) ? sanitize_text_field( (string) $input['subtitle_id'] ) : '';
			if ( ! $media_id || '' === $subtitle_id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'media_id and subtitle_id are required.' );
			}
			$result = fluent_abilities_player_invoke_controller(
				'\FluentPlayerPro\App\Http\Controllers\SubtitleController',
				'removeSubtitle',
				is_array( $input ) ? $input : array(),
				array( 'mediaId' => $media_id, 'subtitleId' => $subtitle_id )
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array(
				'success'        => true,
				'removed'        => true,
				'media_settings' => fluent_abilities_safe_array( is_array( $result ) ? ( $result['media_settings'] ?? null ) : null ),
			);
		},
	) );

	$reg->read( 'fluent-player/get-youtube-captions', array(
		'label'         => 'Get YouTube captions',
		'description'   => 'List YouTube captions for a YouTube-provider media item (third-party YouTube Data API). Input: pass the media ID as `media_id` (a required integer).',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'media_id' ),
			'properties' => array(
				'media_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'total'    => array( 'type' => 'integer' ),
				'captions' => array( 'type' => 'array' ),
			),
		),
		'callback'      => function ( $input ) {
			$media_id = absint( $input['media_id'] ?? 0 );
			if ( ! $media_id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'media_id is required.' );
			}
			$result = fluent_abilities_player_invoke_controller(
				'\FluentPlayerPro\App\Http\Controllers\SubtitleController',
				'getYouTubeCaptions',
				is_array( $input ) ? $input : array(),
				array( 'mediaId' => $media_id )
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$items = fluent_abilities_safe_array( is_array( $result ) ? ( $result['captions'] ?? $result ) : array() );
			$items = is_array( $items ) ? array_values( $items ) : array();
			return array( 'captions' => $items, 'total' => count( $items ) );
		},
	) );

	$reg->write( 'fluent-player/import-youtube-captions', array(
		'label'         => 'Import YouTube captions',
		'description'   => "Import a YouTube caption track into the media item's subtitle list. Combines YouTube Data API + media settings mutation.",
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'media_id', 'language' ),
			'properties' => array(
				'media_id' => array( 'type' => 'integer' ),
				'language' => array( 'type' => 'string', 'description' => 'Language code (e.g. en, es).' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'subtitle'       => array( 'type' => array( 'object', 'null' ) ),
			'media_settings' => array( 'type' => array( 'object', 'null' ) ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function ( $input ) {
			$media_id = absint( $input['media_id'] ?? 0 );
			$language = isset( $input['language'] ) ? sanitize_text_field( $input['language'] ) : '';
			if ( ! $media_id || '' === $language ) {
				return fluent_abilities_error( 'ability_invalid_input', 'media_id and language are required.' );
			}
			$input['language'] = $language;
			$result            = fluent_abilities_player_invoke_controller(
				'\FluentPlayerPro\App\Http\Controllers\SubtitleController',
				'importYouTubeCaptions',
				$input,
				array( 'mediaId' => $media_id )
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array(
				'success'        => true,
				'subtitle'       => fluent_abilities_safe_array( is_array( $result ) ? ( $result['subtitle'] ?? null ) : null ),
				'media_settings' => fluent_abilities_safe_array( is_array( $result ) ? ( $result['media_settings'] ?? null ) : null ),
			);
		},
	) );

	$reg->write( 'fluent-player/generate-youtube-storyboard', array(
		'label'         => 'Generate YouTube storyboard',
		'description'   => 'Trigger async storyboard generation for a YouTube media item. Returns immediately; status polled separately.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'media_id' ),
			'properties' => array(
				'media_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'storyboard_attachment_id' => array( 'type' => array( 'integer', 'null' ) ),
			'status'                   => array( 'type' => 'string' ),
			'message'                  => array( 'type' => 'string' ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function ( $input ) {
			$media_id = absint( $input['media_id'] ?? 0 );
			if ( ! $media_id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'media_id is required.' );
			}
			$result = fluent_abilities_player_invoke_controller(
				'\FluentPlayerPro\App\Http\Controllers\SubtitleController',
				'generateYouTubeStoryboard',
				is_array( $input ) ? $input : array(),
				array( 'mediaId' => $media_id )
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array(
				'success'                  => true,
				'storyboard_attachment_id' => isset( $result['storyboard_attachment_id'] ) ? (int) $result['storyboard_attachment_id'] : null,
				'status'                   => is_array( $result ) ? ( $result['status'] ?? 'queued' ) : 'queued',
				'message'                  => is_array( $result ) ? ( $result['message'] ?? 'Storyboard generation queued.' ) : 'Storyboard generation queued.',
			);
		},
	) );

	// ─── Cluster 12: Timed Content ─────────────────────────────────────────

	$reg->write( 'fluent-player/update-timed-content', array(
		'label'         => 'Update timed content (chapters + overlays)',
		'description'   => 'Update chapters and overlays for a media item. Mutates nested media.settings keys.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'media_id' ),
			'properties' => array(
				'media_id' => array( 'type' => 'integer' ),
				'chapters' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
				'overlays' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'media_settings' => array( 'type' => array( 'object', 'null' ) ),
		) ),
		'callback'      => function ( $input ) {
			$media_id = absint( $input['media_id'] ?? 0 );
			if ( ! $media_id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'media_id is required.' );
			}
			$result = fluent_abilities_player_invoke_controller(
				'\FluentPlayerPro\App\Http\Controllers\TimedContentController',
				'updateTimedContent',
				is_array( $input ) ? $input : array(),
				array( 'id' => $media_id )
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array(
				'success'        => true,
				'media_settings' => fluent_abilities_safe_array( is_array( $result ) ? ( $result['media_settings'] ?? null ) : null ),
			);
		},
	) );
}
add_action( 'wp_abilities_api_init', 'fluent_abilities_player_register_playlists_abilities', 100 );
