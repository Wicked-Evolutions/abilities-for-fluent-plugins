<?php
/**
 * FluentPlayer Abilities — Media + Media Tags
 *
 * 11 abilities in the `fluent-player` category covering core media CRUD/search
 * plus oEmbed metadata (free, 7) and Pro Media Tags (taxonomy `flp_media_tag`, 4).
 * Greenfield module added for v2.0.0.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register media + media-tag abilities. Called via wp_abilities_api_init at priority 100.
 *
 * Extracted as a named function so unit tests (which stub add_action as a no-op)
 * can invoke registration directly.
 */
function fluent_abilities_player_register_media_abilities() {

	$reg = new Fluent_Abilities_Registrar( 'player' );

	// ─── Cluster 1: Media (free, 7 abilities) ──────────────────────────────

	$reg->read( 'fluent-player/list-media', array(
		'label'        => 'List media',
		'description'  => 'Paginated list of FluentPlayer media items (videos/audio).',
		'category'     => 'fluent-player',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'per_page'      => array(
					'type'        => 'integer',
					'description' => 'Items per page, max 100 (default: 20)',
					'default'     => 20,
				),
				'page'          => array(
					'type'        => 'integer',
					'description' => 'Page number (default: 1)',
					'default'     => 1,
				),
				'status'        => array(
					'type'        => 'string',
					'description' => 'Filter by post_status.',
					'enum'        => array( 'publish', 'private', 'draft', 'auto-draft' ),
				),
				'orderby'       => array( 'type' => 'string', 'description' => 'Column to sort by.' ),
				'order'         => array(
					'type'        => 'string',
					'description' => 'Sort direction.',
					'enum'        => array( 'ASC', 'DESC' ),
				),
				'query'         => array( 'type' => 'string', 'description' => 'Search query string.' ),
				'tag'           => array( 'type' => 'string', 'description' => 'Single tag filter.' ),
				'tags'          => array(
					'type'        => 'array',
					'description' => 'Filter by multiple tags.',
					'items'       => array( 'type' => 'string' ),
				),
				'with_settings' => array(
					'type'        => 'boolean',
					'description' => 'Include media settings in response.',
					'default'     => true,
				),
			),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'media', array(
			'ID'          => array( 'type' => 'integer' ),
			'post_title'  => array( 'type' => 'string' ),
			'post_status' => array( 'type' => 'string' ),
			'post_date'   => array( 'type' => 'string' ),
			'settings'    => array( 'type' => array( 'object', 'null' ) ),
		) ),
		'callback' => function ( $input ) {
			if ( ! class_exists( '\FluentPlayer\App\Models\Media' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentPlayer Media model not found.' );
			}

			$pagination = fluent_abilities_pagination( $input, 20 );
			$page       = $pagination['page'];
			$per_page   = $pagination['per_page'];

			try {
				$query = \FluentPlayer\App\Models\Media::query();

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
				$query->orderBy( $orderby, $order );

				$total = (int) $query->count();
				$rows  = $query->offset( $pagination['offset'] )->limit( $per_page )->get();
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'execution_failed', $e->getMessage() );
			}

			$with_settings = ! isset( $input['with_settings'] ) || (bool) $input['with_settings'];

			$items = array();
			foreach ( $rows as $row ) {
				$item = array(
					'ID'          => (int) $row->ID,
					'post_title'  => (string) ( $row->post_title ?? '' ),
					'post_status' => (string) ( $row->post_status ?? '' ),
					'post_date'   => (string) ( $row->post_date ?? '' ),
				);
				if ( $with_settings ) {
					$item['settings'] = fluent_abilities_safe_array( $row->settings ?? null );
				}
				$items[] = $item;
			}

			return array(
				'total'    => $total,
				'page'     => $page,
				'per_page' => $per_page,
				'media'    => $items,
			);
		},
	) );

	$reg->read( 'fluent-player/get-media', array(
		'label'        => 'Get media',
		'description'  => 'Get a single media item by ID with enriched view_url, tags, and post_content. Input: pass the media ID as `id` (an integer).',
		'category'     => 'fluent-player',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Media ID.' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'ID'           => array( 'type' => 'integer' ),
			'post_title'   => array( 'type' => 'string' ),
			'post_status'  => array( 'type' => 'string' ),
			'post_date'    => array( 'type' => 'string' ),
			'settings'     => array( 'type' => array( 'object', 'null' ) ),
			'tags'         => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'view_url'     => array( 'type' => 'string' ),
			'post_content' => array( 'type' => 'string' ),
		) ),
		'callback' => function ( $input ) {
			$id = absint( $input['id'] ?? 0 );
			if ( ! $id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'id is required.' );
			}
			if ( ! class_exists( '\FluentPlayer\App\Models\Media' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentPlayer Media model not found.' );
			}

			try {
				$media = \FluentPlayer\App\Models\Media::find( $id );
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'execution_failed', $e->getMessage() );
			}

			if ( ! $media ) {
				return fluent_abilities_error( 'not_found', 'Media not found.' );
			}

			$tags = array();
			$raw_tags = wp_get_object_terms( $id, 'flp_media_tag', array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $raw_tags ) && is_array( $raw_tags ) ) {
				$tags = array_values( array_map( 'strval', $raw_tags ) );
			}

			$view_url = '';
			if ( method_exists( $media, 'getViewUrl' ) ) {
				try {
					$view_url = (string) $media->getViewUrl();
				} catch ( \Throwable $e ) {
					$view_url = '';
				}
			}
			if ( '' === $view_url ) {
				$permalink = get_permalink( $id );
				$view_url  = $permalink ? (string) $permalink : '';
			}

			return array(
				'ID'           => (int) $media->ID,
				'post_title'   => (string) ( $media->post_title ?? '' ),
				'post_status'  => (string) ( $media->post_status ?? '' ),
				'post_date'    => (string) ( $media->post_date ?? '' ),
				'settings'     => fluent_abilities_safe_array( $media->settings ?? null ),
				'tags'         => $tags,
				'view_url'     => $view_url,
				'post_content' => (string) ( $media->post_content ?? '' ),
			);
		},
	) );

	$reg->read( 'fluent-player/search-media', array(
		'label'        => 'Search media',
		'description'  => 'Search media by query / ID list / status with offset+limit paging. Input: the free-text search keyword field is named `q` (a string) — there is no `query`/`search`/`keyword` field; the handler reads `q` verbatim.',
		'category'     => 'fluent-player',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'q'        => array( 'type' => 'string', 'description' => 'Search keyword.' ),
				'medias'   => array(
					'type'        => 'array',
					'description' => 'Specific media IDs to include.',
					'items'       => array( 'type' => 'integer' ),
				),
				'offset'   => array( 'type' => 'integer', 'description' => 'Pagination offset.' ),
				'limit'    => array( 'type' => 'integer', 'description' => 'Items per page.' ),
				'status'   => array( 'type' => 'string', 'description' => 'Filter by post_status.' ),
				'order_by' => array(
					'type'        => 'string',
					'description' => 'Sort direction (ASC or DESC).',
					'enum'        => array( 'ASC', 'DESC' ),
				),
			),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'items', array(
			'ID'          => array( 'type' => 'integer' ),
			'post_title'  => array( 'type' => 'string' ),
			'post_status' => array( 'type' => 'string' ),
			'post_date'   => array( 'type' => 'string' ),
			'settings'    => array( 'type' => array( 'object', 'null' ) ),
		) ),
		'callback' => function ( $input ) {
			if ( ! class_exists( '\FluentPlayer\App\Models\Media' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentPlayer Media model not found.' );
			}

			$attr = array();
			if ( isset( $input['q'] ) ) {
				$attr['q'] = sanitize_text_field( $input['q'] );
			}
			if ( isset( $input['medias'] ) && is_array( $input['medias'] ) ) {
				$attr['medias'] = array_values( array_map( 'absint', $input['medias'] ) );
			}
			if ( isset( $input['offset'] ) ) {
				$attr['offset'] = max( 0, (int) $input['offset'] );
			}
			if ( isset( $input['limit'] ) ) {
				$attr['limit'] = min( 100, max( 1, (int) $input['limit'] ) );
			}
			if ( isset( $input['status'] ) ) {
				$attr['status'] = sanitize_text_field( $input['status'] );
			}
			if ( isset( $input['order_by'] ) ) {
				$order = strtoupper( sanitize_text_field( $input['order_by'] ) );
				$attr['order_by'] = ( 'ASC' === $order ) ? 'ASC' : 'DESC';
			}

			try {
				$result = \FluentPlayer\App\Models\Media::search( $attr );
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'execution_failed', $e->getMessage() );
			}

			$total = 0;
			$rows  = array();

			if ( is_array( $result ) ) {
				if ( isset( $result['total'] ) ) {
					$total = (int) $result['total'];
				}
				if ( isset( $result['items'] ) && is_array( $result['items'] ) ) {
					$rows = $result['items'];
				} elseif ( isset( $result['data'] ) && is_array( $result['data'] ) ) {
					$rows = $result['data'];
				} else {
					$rows = $result;
					$total = count( $rows );
				}
			} elseif ( is_object( $result ) && method_exists( $result, 'toArray' ) ) {
				$array = $result->toArray();
				$total = (int) ( $array['total'] ?? count( $array['data'] ?? array() ) );
				$rows  = $array['data'] ?? array();
			}

			$items = array();
			foreach ( $rows as $row ) {
				$row = (array) ( is_object( $row ) && method_exists( $row, 'toArray' ) ? $row->toArray() : $row );
				$items[] = array(
					'ID'          => (int) ( $row['ID'] ?? 0 ),
					'post_title'  => (string) ( $row['post_title'] ?? '' ),
					'post_status' => (string) ( $row['post_status'] ?? '' ),
					'post_date'   => (string) ( $row['post_date'] ?? '' ),
					'settings'    => fluent_abilities_safe_array( $row['settings'] ?? null ),
				);
			}

			$limit  = $attr['limit'] ?? count( $items );
			$offset = $attr['offset'] ?? 0;
			$page   = $limit > 0 ? (int) floor( $offset / $limit ) + 1 : 1;

			return array(
				'total'    => $total,
				'page'     => $page,
				'per_page' => (int) $limit,
				'items'    => $items,
			);
		},
	) );

	$reg->write( 'fluent-player/create-media', array(
		'label'        => 'Create media',
		'description'  => 'Create a new media item (video/audio/youtube/vimeo/bunny/mux).',
		'category'     => 'fluent-player',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'settings' ),
			'properties' => array(
				'settings' => array(
					'type'        => 'object',
					'description' => 'Media settings object. Runtime-enforced 14-rule validator in MediaController::prepareMedia() applies — JSON schema is advisory.',
					'properties'  => array(
						'viewType'          => array(
							'type'        => 'string',
							'description' => 'View type.',
							'enum'        => array( 'video', 'audio', 'youtube', 'vimeo' ),
						),
						'preset_slug'       => array( 'type' => 'string', 'description' => 'Preset slug to apply.' ),
						'src'               => array( 'type' => 'string', 'description' => 'Source URL (required for youtube/vimeo/external providers).' ),
						'provider'          => array(
							'type'        => 'string',
							'description' => 'Source provider.',
							'enum'        => array( 'wordpress', 'youtube', 'vimeo', 'bunny', 'mux' ),
						),
						'attachment_id'     => array( 'type' => 'integer', 'description' => 'WP attachment ID (required if provider=wordpress).' ),
						'title'             => array( 'type' => 'string', 'description' => 'Media title.' ),
						'post_status'       => array(
							'type'        => 'string',
							'description' => 'Post status.',
							'enum'        => array( 'publish', 'private', 'draft', 'auto-draft' ),
							'default'     => 'draft',
						),
						'language'          => array( 'type' => array( 'string', 'null' ), 'description' => 'Language code.' ),
						'language_mappings' => array(
							'type'        => 'array',
							'description' => 'Cross-language mappings.',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'language' => array( 'type' => 'string' ),
									'media_id' => array( 'type' => 'integer' ),
									'id'       => array( 'type' => 'integer' ),
								),
							),
						),
						'loadStrategy'      => array(
							'type'        => 'string',
							'description' => 'Player load strategy.',
							'enum'        => array( 'eager', 'visible', 'idle', 'play' ),
						),
						'mediaType'         => array(
							'type'        => 'string',
							'description' => 'Media type.',
							'enum'        => array( 'video', 'audio' ),
						),
						'streamType'        => array(
							'type'        => 'string',
							'description' => 'Stream type.',
							'enum'        => array( 'on-demand', 'live', 'live:dvr' ),
						),
						'aspectRatio'       => array( 'type' => 'string', 'description' => 'Aspect ratio (e.g., 16:9).' ),
						'chapters'          => array( 'type' => 'array', 'description' => 'Chapter markers.', 'items' => array( 'type' => 'object' ) ),
						'overlays'          => array( 'type' => 'array', 'description' => 'Overlay configurations.', 'items' => array( 'type' => 'object' ) ),
						'subtitles'         => array( 'type' => 'array', 'description' => 'Subtitle tracks.', 'items' => array( 'type' => 'object' ) ),
						'poster'            => array( 'type' => 'string', 'description' => 'Poster image URL.' ),
						'thumbnail'         => array( 'type' => 'string', 'description' => 'Thumbnail image URL.' ),
					),
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'message' => array( 'type' => 'string' ),
			'media'   => array(
				'type'       => 'object',
				'properties' => array(
					'ID'          => array( 'type' => 'integer' ),
					'post_title'  => array( 'type' => 'string' ),
					'post_status' => array( 'type' => 'string' ),
					'settings'    => array( 'type' => array( 'object', 'null' ) ),
					'tags'        => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				),
			),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback'    => function ( $input ) {
			$settings = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : null;
			if ( ! $settings ) {
				return fluent_abilities_error( 'ability_invalid_input', 'settings is required.' );
			}

			$response = fluent_abilities_player_invoke_controller(
				'\FluentPlayer\App\Http\Controllers\MediaController',
				'store',
				array( 'settings' => $settings )
			);
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$data  = is_array( $response ) ? $response : array();
			$media = $data['media'] ?? null;
			if ( is_array( $media ) ) {
				$media = array(
					'ID'          => (int) ( $media['ID'] ?? 0 ),
					'post_title'  => (string) ( $media['post_title'] ?? '' ),
					'post_status' => (string) ( $media['post_status'] ?? '' ),
					'settings'    => fluent_abilities_safe_array( $media['settings'] ?? null ),
					'tags'        => isset( $media['tags'] ) && is_array( $media['tags'] ) ? array_values( array_map( 'strval', $media['tags'] ) ) : array(),
				);
			}

			return array(
				'success' => true,
				'message' => (string) ( $data['message'] ?? 'Media created.' ),
				'media'   => $media,
			);
		},
	) );

	$reg->write( 'fluent-player/update-media', array(
		'label'        => 'Update media',
		'description'  => 'Update an existing media item; storyboard-cleanup branch fires when source changes (Pro side-effect).',
		'category'     => 'fluent-player',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'       => array( 'type' => 'integer', 'description' => 'Media ID to update.' ),
				'settings' => array(
					'type'        => 'object',
					'description' => 'Partial settings to update. All sub-keys optional.',
					'properties'  => array(
						'viewType'          => array(
							'type'        => 'string',
							'enum'        => array( 'video', 'audio', 'youtube', 'vimeo' ),
							'description' => 'View type.',
						),
						'preset_slug'       => array( 'type' => 'string', 'description' => 'Preset slug.' ),
						'src'               => array( 'type' => 'string', 'description' => 'Source URL.' ),
						'provider'          => array(
							'type'        => 'string',
							'enum'        => array( 'wordpress', 'youtube', 'vimeo', 'bunny', 'mux' ),
							'description' => 'Source provider.',
						),
						'attachment_id'     => array( 'type' => 'integer', 'description' => 'WP attachment ID.' ),
						'title'             => array( 'type' => 'string', 'description' => 'Media title.' ),
						'post_status'       => array(
							'type'        => 'string',
							'enum'        => array( 'publish', 'private', 'draft', 'auto-draft' ),
							'description' => 'Post status.',
						),
						'language'          => array( 'type' => array( 'string', 'null' ), 'description' => 'Language code.' ),
						'language_mappings' => array(
							'type'        => 'array',
							'description' => 'Cross-language mappings.',
							'items'       => array( 'type' => 'object' ),
						),
						'loadStrategy'      => array(
							'type'        => 'string',
							'enum'        => array( 'eager', 'visible', 'idle', 'play' ),
							'description' => 'Load strategy.',
						),
						'mediaType'         => array(
							'type'        => 'string',
							'enum'        => array( 'video', 'audio' ),
							'description' => 'Media type.',
						),
						'streamType'        => array(
							'type'        => 'string',
							'enum'        => array( 'on-demand', 'live', 'live:dvr' ),
							'description' => 'Stream type.',
						),
						'aspectRatio'       => array( 'type' => 'string', 'description' => 'Aspect ratio.' ),
						'chapters'          => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'description' => 'Chapter markers.' ),
						'overlays'          => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'description' => 'Overlays.' ),
						'subtitles'         => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'description' => 'Subtitle tracks.' ),
						'poster'            => array( 'type' => 'string', 'description' => 'Poster image URL.' ),
						'thumbnail'         => array( 'type' => 'string', 'description' => 'Thumbnail image URL.' ),
					),
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'message' => array( 'type' => 'string' ),
			'media'   => array(
				'type'       => 'object',
				'properties' => array(
					'ID'          => array( 'type' => 'integer' ),
					'post_title'  => array( 'type' => 'string' ),
					'post_status' => array( 'type' => 'string' ),
					'settings'    => array( 'type' => array( 'object', 'null' ) ),
					'tags'        => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				),
			),
		) ),
		'callback' => function ( $input ) {
			$id = absint( $input['id'] ?? 0 );
			if ( ! $id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'id is required.' );
			}
			// V10/P-L: vendor MediaController::update does NOT 404 on a
			// nonexistent id — prepareMedia() falls back to empty existing
			// settings and `new Media(); $m->id=$id; ->save()` (upsert-by-id),
			// returning success:true on a bogus id. Validate existence first
			// → typed not_found, never a false success on nonexistent media.
			if ( ! class_exists( '\FluentPlayer\App\Models\Media' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentPlayer Media model not found.' );
			}
			if ( ! \FluentPlayer\App\Models\Media::find( $id ) ) {
				return fluent_abilities_error( 'not_found', 'Media not found: ' . $id );
			}

			$settings = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();

			$response = fluent_abilities_player_invoke_controller(
				'\FluentPlayer\App\Http\Controllers\MediaController',
				'update',
				array( 'settings' => $settings, 'id' => $id ),
				array( 'id' => $id )
			);
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$data  = is_array( $response ) ? $response : array();
			$media = $data['media'] ?? null;
			if ( is_array( $media ) ) {
				$media = array(
					'ID'          => (int) ( $media['ID'] ?? 0 ),
					'post_title'  => (string) ( $media['post_title'] ?? '' ),
					'post_status' => (string) ( $media['post_status'] ?? '' ),
					'settings'    => fluent_abilities_safe_array( $media['settings'] ?? null ),
					'tags'        => isset( $media['tags'] ) && is_array( $media['tags'] ) ? array_values( array_map( 'strval', $media['tags'] ) ) : array(),
				);
			}

			return array(
				'success' => true,
				'message' => (string) ( $data['message'] ?? 'Media updated.' ),
				'media'   => $media,
			);
		},
	) );

	$reg->delete( 'fluent-player/delete-media', array(
		'label'        => 'Delete media',
		'description'  => 'Delete a media item and cascade email collections rows.',
		'category'     => 'fluent-player',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Media ID to delete.' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'message' => array( 'type' => 'string' ),
		) ),
		'callback' => function ( $input ) {
			$id = absint( $input['id'] ?? 0 );
			if ( ! $id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'id is required.' );
			}
			$response = fluent_abilities_player_invoke_controller(
				'\FluentPlayer\App\Http\Controllers\MediaController',
				'delete',
				is_array( $input ) ? $input : array(),
				array( 'id' => $id )
			);
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$data    = is_array( $response ) ? $response : array();
			$message = $data['message'] ?? 'Media deleted.';
			return array(
				'success' => true,
				'message' => (string) $message,
			);
		},
	) );

	$reg->read( 'fluent-player/get-media-metadata', array(
		'label'        => 'Get media metadata (oEmbed/YouTube)',
		'description'  => 'Fetch oEmbed-style metadata for a URL (used by editor previews). Input: this is a URL oEmbed inspector — pass the source URL as `url` (a required string); it does NOT look media up by `id`/`media_id`, there is no media-by-id mode.',
		'category'     => 'fluent-player',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'url' ),
			'properties' => array(
				'url' => array( 'type' => 'string', 'description' => 'Source URL to inspect.' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'metaData' => array(
				'type'       => 'object',
				'properties' => array(
					'url'           => array( 'type' => 'string' ),
					'title'         => array( 'type' => 'string' ),
					'thumbnail_url' => array( 'type' => 'string' ),
					'provider_name' => array( 'type' => 'string' ),
					'type'          => array( 'type' => 'string' ),
				),
			),
		) ),
		'callback' => function ( $input ) {
			$url = esc_url_raw( $input['url'] ?? '' );
			if ( ! $url ) {
				return fluent_abilities_error( 'ability_invalid_input', 'url is required.' );
			}
			$response = fluent_abilities_player_invoke_controller(
				'\FluentPlayer\App\Http\Controllers\MediaController',
				'getMetadata',
				array( 'url' => $url )
			);
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$data = is_array( $response ) ? $response : array();
			$meta = isset( $data['metaData'] ) && is_array( $data['metaData'] ) ? $data['metaData'] : array();

			return array(
				'success'  => (bool) ( $data['success'] ?? true ),
				'metaData' => array(
					'url'           => (string) ( $meta['url'] ?? $url ),
					'title'         => (string) ( $meta['title'] ?? '' ),
					'thumbnail_url' => (string) ( $meta['thumbnail_url'] ?? '' ),
					'provider_name' => (string) ( $meta['provider_name'] ?? '' ),
					'type'          => (string) ( $meta['type'] ?? '' ),
				),
			);
		},
	) );

	// ─── Cluster 2: Media Tags (pro, 4 abilities) ──────────────────────────

	if ( defined( 'FLUENT_PLAYER_PRO_VERSION' ) ) {

		$reg->read( 'fluent-player/list-media-tags', array(
			'label'        => 'List media tags',
			'description'  => 'List Pro media tags (taxonomy flp_media_tag) with optional counts.',
			'category'     => 'fluent-player',
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'with_counts' => array(
						'type'        => 'boolean',
						'description' => 'Include term usage counts.',
						'default'     => false,
					),
				),
			),
			// V5 serialization: vendor TagService::getTags() returns a FLAT
			// string[] of names when with_counts is falsy (wp_list_pluck) and
			// [{name,count}] only when truthy; getTagOptions() always returns
			// [{name,slug}] (assoc arrays). The pre-P8 callback (a) read
			// $tag['name'] off a string → empty name, and (b) strval()'d each
			// {name,slug} option → the literal "Array". Vendor-grounded fix:
			// always request with_counts so `tags` is uniformly objects, and
			// declare/emit tagOptions as {name,slug} objects (slug is the only
			// stable tag identifier the vendor exposes — no numeric term id).
			'output_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'tags'       => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'name'  => array( 'type' => 'string' ),
								'count' => array( 'type' => 'integer' ),
							),
						),
					),
					'tagOptions' => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'name' => array( 'type' => 'string' ),
								'slug' => array( 'type' => 'string' ),
							),
						),
					),
				),
			),
			'callback' => function ( $input ) {
				// Always pass with_counts=1: the vendor's no-count branch is a
				// flat string[] with no stable per-item object shape.
				$response = fluent_abilities_player_invoke_controller(
					'\FluentPlayerPro\App\Http\Controllers\TagController',
					'getTags',
					array( 'with_counts' => 1 )
				);
				if ( is_wp_error( $response ) ) {
					return $response;
				}
				$data = is_array( $response ) ? $response : array();
				$tags = array();
				if ( isset( $data['tags'] ) && is_array( $data['tags'] ) ) {
					foreach ( $data['tags'] as $tag ) {
						if ( is_string( $tag ) ) {
							$tags[] = array( 'name' => $tag, 'count' => 0 );
							continue;
						}
						$tag    = (array) $tag;
						$tags[] = array(
							'name'  => (string) ( $tag['name'] ?? '' ),
							'count' => (int) ( $tag['count'] ?? 0 ),
						);
					}
				}

				$tag_options = array();
				if ( isset( $data['tagOptions'] ) && is_array( $data['tagOptions'] ) ) {
					foreach ( $data['tagOptions'] as $opt ) {
						if ( is_string( $opt ) ) {
							$tag_options[] = array( 'name' => $opt, 'slug' => sanitize_title( $opt ) );
							continue;
						}
						$opt           = (array) $opt;
						$tag_options[] = array(
							'name' => (string) ( $opt['name'] ?? '' ),
							'slug' => (string) ( $opt['slug'] ?? '' ),
						);
					}
				}

				return array(
					'tags'       => $tags,
					'tagOptions' => $tag_options,
				);
			},
		) );

		$reg->write( 'fluent-player/create-media-tag', array(
			'label'        => 'Create media tag',
			'description'  => 'Create a new media tag by name. Input: the tag name field is `tag_name` (a required string) — NOT `name`.',
			'category'     => 'fluent-player',
			'input_schema' => array(
				'type'       => 'object',
				'required'   => array( 'tag_name' ),
				'properties' => array(
					'tag_name' => array( 'type' => 'string', 'description' => 'Tag name to create.' ),
				),
			),
			// V3/V9: vendor TagController::createTag discards the wp_insert_term
			// id and returns only {message}. The pre-P8 callback echoed
			// success:true with no reference to the created tag. Vendor-grounded
			// fix: after a successful create, READ BACK via getTags/getTagOptions
			// (the only vendor surfaces that enumerate tags) and resolve the
			// created tag by exact name → return the persisted {name,slug}.
			// No numeric term id is exposed by any vendor tag endpoint; slug is
			// the stable identifier.
			'output_schema' => fluent_abilities_schema_success_output( array(
				'message' => array( 'type' => 'string' ),
				'tag'     => array(
					'type'       => array( 'object', 'null' ),
					'properties' => array(
						'name' => array( 'type' => 'string' ),
						'slug' => array( 'type' => 'string' ),
					),
				),
			) ),
			'annotations' => array( 'idempotent' => false ),
			'callback'    => function ( $input ) {
				$tag_name = sanitize_text_field( $input['tag_name'] ?? '' );
				if ( '' === $tag_name ) {
					return fluent_abilities_error( 'ability_invalid_input', 'tag_name is required.' );
				}
				$response = fluent_abilities_player_invoke_controller(
					'\FluentPlayerPro\App\Http\Controllers\TagController',
					'createTag',
					array( 'tag_name' => $tag_name )
				);
				if ( is_wp_error( $response ) ) {
					return $response;
				}
				$data    = is_array( $response ) ? $response : array();
				$message = $data['message'] ?? 'Tag created.';
				// V3 read-back: resolve the just-created tag by exact name.
				$tag      = null;
				$readback = fluent_abilities_player_invoke_controller(
					'\FluentPlayerPro\App\Http\Controllers\TagController',
					'getTags',
					array( 'with_counts' => 1 )
				);
				if ( ! is_wp_error( $readback ) && is_array( $readback )
					&& isset( $readback['tagOptions'] ) && is_array( $readback['tagOptions'] ) ) {
					foreach ( $readback['tagOptions'] as $opt ) {
						$opt = (array) $opt;
						if ( isset( $opt['name'] ) && (string) $opt['name'] === $tag_name ) {
							$tag = array(
								'name' => (string) $opt['name'],
								'slug' => (string) ( $opt['slug'] ?? '' ),
							);
							break;
						}
					}
				}
				if ( null === $tag ) {
					return fluent_abilities_error( 'ability_execution_failed', 'Tag create reported success but the tag was not found on read-back: ' . $tag_name );
				}
				return array(
					'success' => true,
					'message' => (string) $message,
					'tag'     => $tag,
				);
			},
		) );

		$reg->write( 'fluent-player/rename-media-tag', array(
			'label'        => 'Rename media tag',
			'description'  => 'Rename a media tag (operator-facing tag-name identifier).',
			'category'     => 'fluent-player',
			'input_schema' => array(
				'type'       => 'object',
				'required'   => array( 'old_name', 'new_name' ),
				'properties' => array(
					'old_name' => array( 'type' => 'string', 'description' => 'Existing tag name.' ),
					'new_name' => array( 'type' => 'string', 'description' => 'New tag name.' ),
				),
			),
			'output_schema' => fluent_abilities_schema_success_output( array(
				'message' => array( 'type' => 'string' ),
			) ),
			'callback' => function ( $input ) {
				$old_name = sanitize_text_field( $input['old_name'] ?? '' );
				$new_name = sanitize_text_field( $input['new_name'] ?? '' );
				if ( '' === $old_name || '' === $new_name ) {
					return fluent_abilities_error( 'ability_invalid_input', 'old_name and new_name are required.' );
				}
				$response = fluent_abilities_player_invoke_controller(
					'\FluentPlayerPro\App\Http\Controllers\TagController',
					'renameTag',
					array( 'old_name' => $old_name, 'new_name' => $new_name )
				);
				if ( is_wp_error( $response ) ) {
					return $response;
				}
				$data    = is_array( $response ) ? $response : array();
				$message = $data['message'] ?? 'Tag renamed.';
				return array(
					'success' => true,
					'message' => (string) $message,
				);
			},
		) );

		$reg->delete( 'fluent-player/delete-media-tag', array(
			'label'        => 'Delete media tag',
			'description'  => 'Delete a media tag by name.',
			'category'     => 'fluent-player',
			'input_schema' => array(
				'type'       => 'object',
				'required'   => array( 'tag_name' ),
				'properties' => array(
					'tag_name' => array( 'type' => 'string', 'description' => 'Tag name to delete.' ),
				),
			),
			'output_schema' => fluent_abilities_schema_success_output( array(
				'message' => array( 'type' => 'string' ),
			) ),
			'callback' => function ( $input ) {
				$tag_name = sanitize_text_field( $input['tag_name'] ?? '' );
				if ( '' === $tag_name ) {
					return fluent_abilities_error( 'ability_invalid_input', 'tag_name is required.' );
				}
				$response = fluent_abilities_player_invoke_controller(
					'\FluentPlayerPro\App\Http\Controllers\TagController',
					'deleteTag',
					array( 'tag_name' => $tag_name )
				);
				if ( is_wp_error( $response ) ) {
					return $response;
				}
				$data    = is_array( $response ) ? $response : array();
				$message = $data['message'] ?? 'Tag deleted.';
				return array(
					'success' => true,
					'message' => (string) $message,
				);
			},
		) );

	}

}
add_action( 'wp_abilities_api_init', 'fluent_abilities_player_register_media_abilities', 100 );
