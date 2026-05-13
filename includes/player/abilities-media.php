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
		'description'  => 'Get a single media item by ID with enriched view_url, tags, and post_content.',
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
		'description'  => 'Search media by query / ID list / status with offset+limit paging.',
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
			if ( ! class_exists( '\FluentPlayer\App\Http\Controllers\MediaController' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentPlayer MediaController not found.' );
			}
			if ( ! class_exists( '\FluentPlayer\Framework\Http\Request\Request' ) && ! class_exists( '\FluentPlayer\Framework\Http\Request' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentPlayer Request class not found.' );
			}

			$settings = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : null;
			if ( ! $settings ) {
				return fluent_abilities_error( 'ability_invalid_input', 'settings is required.' );
			}

			try {
				$request_class = class_exists( '\FluentPlayer\Framework\Http\Request\Request' )
					? '\FluentPlayer\Framework\Http\Request\Request'
					: '\FluentPlayer\Framework\Http\Request';

				$payload = array( 'settings' => $settings );
				$request = new $request_class( $payload );

				$controller = new \FluentPlayer\App\Http\Controllers\MediaController();
				$response   = $controller->store( $request );
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'execution_failed', $e->getMessage() );
			}

			$data = fluent_abilities_safe_array( $response );
			if ( ! is_array( $data ) ) {
				$data = array();
			}

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
			if ( ! class_exists( '\FluentPlayer\App\Http\Controllers\MediaController' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentPlayer MediaController not found.' );
			}
			if ( ! class_exists( '\FluentPlayer\Framework\Http\Request\Request' ) && ! class_exists( '\FluentPlayer\Framework\Http\Request' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentPlayer Request class not found.' );
			}

			$settings = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();

			try {
				$request_class = class_exists( '\FluentPlayer\Framework\Http\Request\Request' )
					? '\FluentPlayer\Framework\Http\Request\Request'
					: '\FluentPlayer\Framework\Http\Request';

				$payload = array( 'settings' => $settings, 'id' => $id );
				$request = new $request_class( $payload );

				$controller = new \FluentPlayer\App\Http\Controllers\MediaController();
				$response   = $controller->update( $request, $id );
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'execution_failed', $e->getMessage() );
			}

			$data = fluent_abilities_safe_array( $response );
			if ( ! is_array( $data ) ) {
				$data = array();
			}

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
			if ( ! class_exists( '\FluentPlayer\App\Http\Controllers\MediaController' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentPlayer MediaController not found.' );
			}

			try {
				$controller = new \FluentPlayer\App\Http\Controllers\MediaController();
				$response   = $controller->delete( $id );
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'execution_failed', $e->getMessage() );
			}

			$data = fluent_abilities_safe_array( $response );
			$message = is_array( $data ) ? ( $data['message'] ?? 'Media deleted.' ) : 'Media deleted.';

			return array(
				'success' => true,
				'message' => (string) $message,
			);
		},
	) );

	$reg->read( 'fluent-player/get-media-metadata', array(
		'label'        => 'Get media metadata (oEmbed/YouTube)',
		'description'  => 'Fetch oEmbed-style metadata for a URL (used by editor previews).',
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
			if ( ! class_exists( '\FluentPlayer\App\Http\Controllers\MediaController' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentPlayer MediaController not found.' );
			}
			if ( ! class_exists( '\FluentPlayer\Framework\Http\Request\Request' ) && ! class_exists( '\FluentPlayer\Framework\Http\Request' ) ) {
				return fluent_abilities_error( 'missing_class', 'FluentPlayer Request class not found.' );
			}

			try {
				$request_class = class_exists( '\FluentPlayer\Framework\Http\Request\Request' )
					? '\FluentPlayer\Framework\Http\Request\Request'
					: '\FluentPlayer\Framework\Http\Request';

				$request = new $request_class( array( 'url' => $url ) );

				$controller = new \FluentPlayer\App\Http\Controllers\MediaController();
				$response   = $controller->getMetadata( $request );
			} catch ( \Throwable $e ) {
				return fluent_abilities_error( 'execution_failed', $e->getMessage() );
			}

			$data = fluent_abilities_safe_array( $response );
			if ( ! is_array( $data ) ) {
				$data = array();
			}

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
					'tagOptions' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				),
			),
			'callback' => function ( $input ) {
				if ( ! class_exists( '\FluentPlayerPro\App\Http\Controllers\TagController' ) ) {
					return fluent_abilities_error( 'missing_class', 'FluentPlayer Pro TagController not found.' );
				}
				if ( ! class_exists( '\FluentPlayer\Framework\Http\Request\Request' ) && ! class_exists( '\FluentPlayer\Framework\Http\Request' ) ) {
					return fluent_abilities_error( 'missing_class', 'FluentPlayer Request class not found.' );
				}

				$with_counts = ! empty( $input['with_counts'] );

				try {
					$request_class = class_exists( '\FluentPlayer\Framework\Http\Request\Request' )
						? '\FluentPlayer\Framework\Http\Request\Request'
						: '\FluentPlayer\Framework\Http\Request';

					$request = new $request_class( array( 'with_counts' => $with_counts ? 1 : 0 ) );

					$controller = new \FluentPlayerPro\App\Http\Controllers\TagController();
					$response   = $controller->getTags( $request );
				} catch ( \Throwable $e ) {
					return fluent_abilities_error( 'execution_failed', $e->getMessage() );
				}

				$data = fluent_abilities_safe_array( $response );
				if ( ! is_array( $data ) ) {
					$data = array();
				}

				$tags = array();
				if ( isset( $data['tags'] ) && is_array( $data['tags'] ) ) {
					foreach ( $data['tags'] as $tag ) {
						$tag    = (array) $tag;
						$tags[] = array(
							'name'  => (string) ( $tag['name'] ?? '' ),
							'count' => (int) ( $tag['count'] ?? 0 ),
						);
					}
				}

				$tag_options = array();
				if ( isset( $data['tagOptions'] ) && is_array( $data['tagOptions'] ) ) {
					$tag_options = array_values( array_map( 'strval', $data['tagOptions'] ) );
				}

				return array(
					'tags'       => $tags,
					'tagOptions' => $tag_options,
				);
			},
		) );

		$reg->write( 'fluent-player/create-media-tag', array(
			'label'        => 'Create media tag',
			'description'  => 'Create a new media tag by name.',
			'category'     => 'fluent-player',
			'input_schema' => array(
				'type'       => 'object',
				'required'   => array( 'tag_name' ),
				'properties' => array(
					'tag_name' => array( 'type' => 'string', 'description' => 'Tag name to create.' ),
				),
			),
			'output_schema' => fluent_abilities_schema_success_output( array(
				'message' => array( 'type' => 'string' ),
			) ),
			'annotations' => array( 'idempotent' => false ),
			'callback'    => function ( $input ) {
				$tag_name = sanitize_text_field( $input['tag_name'] ?? '' );
				if ( '' === $tag_name ) {
					return fluent_abilities_error( 'ability_invalid_input', 'tag_name is required.' );
				}
				if ( ! class_exists( '\FluentPlayerPro\App\Http\Controllers\TagController' ) ) {
					return fluent_abilities_error( 'missing_class', 'FluentPlayer Pro TagController not found.' );
				}
				if ( ! class_exists( '\FluentPlayer\Framework\Http\Request\Request' ) && ! class_exists( '\FluentPlayer\Framework\Http\Request' ) ) {
					return fluent_abilities_error( 'missing_class', 'FluentPlayer Request class not found.' );
				}

				try {
					$request_class = class_exists( '\FluentPlayer\Framework\Http\Request\Request' )
						? '\FluentPlayer\Framework\Http\Request\Request'
						: '\FluentPlayer\Framework\Http\Request';

					$request = new $request_class( array( 'tag_name' => $tag_name ) );

					$controller = new \FluentPlayerPro\App\Http\Controllers\TagController();
					$response   = $controller->createTag( $request );
				} catch ( \Throwable $e ) {
					return fluent_abilities_error( 'execution_failed', $e->getMessage() );
				}

				$data    = fluent_abilities_safe_array( $response );
				$message = is_array( $data ) ? ( $data['message'] ?? 'Tag created.' ) : 'Tag created.';

				return array(
					'success' => true,
					'message' => (string) $message,
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
				if ( ! class_exists( '\FluentPlayerPro\App\Http\Controllers\TagController' ) ) {
					return fluent_abilities_error( 'missing_class', 'FluentPlayer Pro TagController not found.' );
				}
				if ( ! class_exists( '\FluentPlayer\Framework\Http\Request\Request' ) && ! class_exists( '\FluentPlayer\Framework\Http\Request' ) ) {
					return fluent_abilities_error( 'missing_class', 'FluentPlayer Request class not found.' );
				}

				try {
					$request_class = class_exists( '\FluentPlayer\Framework\Http\Request\Request' )
						? '\FluentPlayer\Framework\Http\Request\Request'
						: '\FluentPlayer\Framework\Http\Request';

					$request = new $request_class( array(
						'old_name' => $old_name,
						'new_name' => $new_name,
					) );

					$controller = new \FluentPlayerPro\App\Http\Controllers\TagController();
					$response   = $controller->renameTag( $request );
				} catch ( \Throwable $e ) {
					return fluent_abilities_error( 'execution_failed', $e->getMessage() );
				}

				$data    = fluent_abilities_safe_array( $response );
				$message = is_array( $data ) ? ( $data['message'] ?? 'Tag renamed.' ) : 'Tag renamed.';

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
				if ( ! class_exists( '\FluentPlayerPro\App\Http\Controllers\TagController' ) ) {
					return fluent_abilities_error( 'missing_class', 'FluentPlayer Pro TagController not found.' );
				}
				if ( ! class_exists( '\FluentPlayer\Framework\Http\Request\Request' ) && ! class_exists( '\FluentPlayer\Framework\Http\Request' ) ) {
					return fluent_abilities_error( 'missing_class', 'FluentPlayer Request class not found.' );
				}

				try {
					$request_class = class_exists( '\FluentPlayer\Framework\Http\Request\Request' )
						? '\FluentPlayer\Framework\Http\Request\Request'
						: '\FluentPlayer\Framework\Http\Request';

					$request = new $request_class( array( 'tag_name' => $tag_name ) );

					$controller = new \FluentPlayerPro\App\Http\Controllers\TagController();
					$response   = $controller->deleteTag( $request );
				} catch ( \Throwable $e ) {
					return fluent_abilities_error( 'execution_failed', $e->getMessage() );
				}

				$data    = fluent_abilities_safe_array( $response );
				$message = is_array( $data ) ? ( $data['message'] ?? 'Tag deleted.' ) : 'Tag deleted.';

				return array(
					'success' => true,
					'message' => (string) $message,
				);
			},
		) );

	}

}
add_action( 'wp_abilities_api_init', 'fluent_abilities_player_register_media_abilities', 100 );
