<?php
/**
 * FluentPlayer Abilities — Bunny CDN Stream + Storage (all Pro)
 *
 * 13 abilities in the `fluent-player` category:
 *  - Cluster 14 Bunny Stream (9, video + collection CRUD on Bunny's Stream service)
 *  - Cluster 15 Bunny Storage (4, file-tree + delete on Bunny's Storage service)
 *
 * Public no-auth `bunny/storage/stream` route is intentionally NOT registered
 * (frontend playback endpoint, not an operator action). Bunny Storage upload +
 * Bunny Stream upload are excluded per research §5.15 (file-upload surfaces).
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

function fluent_abilities_player_register_bunny_abilities() {

	if ( ! defined( 'FLUENT_PLAYER_PRO_VERSION' ) ) {
		return;
	}

	$reg = new Fluent_Abilities_Registrar( 'player' );

	$call = function ( $controller_class, $method, $input, $id_arg = null, $merge_keys = array() ) {
		$extra = ( null === $id_arg ) ? array() : array( 'id' => $id_arg );
		return fluent_abilities_player_invoke_controller(
			$controller_class,
			$method,
			is_array( $input ) ? $input : array(),
			$extra
		);
	};

	// ─── Cluster 14: Bunny Stream ──────────────────────────────────────────

	$reg->read( 'fluent-player/bunny-stream-list-libraries', array(
		'label'         => 'Bunny Stream — list libraries',
		'description'   => 'List Bunny Stream libraries available on the connected account.',
		'category'      => 'fluent-player',
		'output_schema' => fluent_abilities_schema_collection_output( 'libraries', array(
			'id'   => array( 'type' => 'integer' ),
			'name' => array( 'type' => 'string' ),
		) ),
		'callback'      => function ( $input ) use ( $call ) {
			$result    = $call( '\FluentPlayerPro\App\Http\Controllers\BunnyCDNController', 'getLibraries', $input );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$items     = $result['libraries'] ?? ( is_array( $result ) ? array_values( $result ) : array() );
			return array( 'libraries' => $items, 'total' => is_array( $items ) ? count( $items ) : 0 );
		},
	) );

	$reg->read( 'fluent-player/bunny-stream-list-videos', array(
		'label'         => 'Bunny Stream — list videos',
		'description'   => 'List Bunny Stream videos for a library (paginated).',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'library_id' ),
			'properties' => array(
				'library_id'    => array( 'type' => 'integer' ),
				'per_page'      => array( 'type' => 'integer', 'default' => 20 ),
				'page'          => array( 'type' => 'integer', 'default' => 1 ),
				'collection_id' => array( 'type' => 'string' ),
				'search'        => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'videos' ),
		'callback'      => function ( $input ) use ( $call ) {
			if ( ! absint( $input['library_id'] ?? 0 ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'library_id is required.' );
			}
			return $call(
				'\FluentPlayerPro\App\Http\Controllers\BunnyCDNController',
				'getVideos',
				$input,
				null,
				array( 'library_id', 'per_page', 'page', 'collection_id', 'search' )
			);
		},
	) );

	$reg->write( 'fluent-player/bunny-stream-create-video', array(
		'label'         => 'Bunny Stream — create video',
		'description'   => 'Create a Bunny Stream video shell (no file upload — file is streamed in by separate signed-upload flow).',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'library_id', 'title' ),
			'properties' => array(
				'library_id'    => array( 'type' => 'integer' ),
				'title'         => array( 'type' => 'string' ),
				'collection_id' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'video' => array( 'type' => array( 'object', 'null' ) ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function ( $input ) use ( $call ) {
			if ( ! absint( $input['library_id'] ?? 0 ) || empty( $input['title'] ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'library_id and title are required.' );
			}
			$result = $call(
				'\FluentPlayerPro\App\Http\Controllers\BunnyCDNController',
				'createVideo',
				$input,
				null,
				array( 'library_id', 'title', 'collection_id' )
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array(
				'success' => true,
				'video'   => $result['video'] ?? $result,
			);
		},
	) );

	$reg->write( 'fluent-player/bunny-stream-update-video', array(
		'label'         => 'Bunny Stream — update video',
		'description'   => 'Update a Bunny Stream video (title and/or collection).',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'library_id', 'video_id' ),
			'properties' => array(
				'library_id'    => array( 'type' => 'integer' ),
				'video_id'      => array( 'type' => 'string' ),
				'title'         => array( 'type' => 'string' ),
				'collection_id' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'video' => array( 'type' => array( 'object', 'null' ) ),
		) ),
		'callback'      => function ( $input ) use ( $call ) {
			$video_id = isset( $input['video_id'] ) ? sanitize_text_field( $input['video_id'] ) : '';
			if ( ! absint( $input['library_id'] ?? 0 ) || '' === $video_id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'library_id and video_id are required.' );
			}
			$result = $call(
				'\FluentPlayerPro\App\Http\Controllers\BunnyCDNController',
				'updateVideo',
				$input,
				$video_id,
				array( 'library_id', 'title', 'collection_id' )
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array(
				'success' => true,
				'video'   => $result['video'] ?? $result,
			);
		},
	) );

	$reg->delete( 'fluent-player/bunny-stream-delete-video', array(
		'label'         => 'Bunny Stream — delete video',
		'description'   => 'Permanently delete a Bunny Stream video.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'library_id', 'video_id' ),
			'properties' => array(
				'library_id' => array( 'type' => 'integer' ),
				'video_id'   => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'message' => array( 'type' => 'string' ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function ( $input ) use ( $call ) {
			$video_id = isset( $input['video_id'] ) ? sanitize_text_field( $input['video_id'] ) : '';
			if ( ! absint( $input['library_id'] ?? 0 ) || '' === $video_id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'library_id and video_id are required.' );
			}
			$result = $call(
				'\FluentPlayerPro\App\Http\Controllers\BunnyCDNController',
				'deleteVideo',
				$input,
				$video_id,
				array( 'library_id' )
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array(
				'success' => true,
				'message' => $result['message'] ?? 'Video deleted.',
			);
		},
	) );

	$reg->read( 'fluent-player/bunny-stream-list-collections', array(
		'label'         => 'Bunny Stream — list collections',
		'description'   => 'List Bunny Stream collections in a library.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'library_id' ),
			'properties' => array(
				'library_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'collections', array(
			'id'          => array( 'type' => 'string' ),
			'name'        => array( 'type' => 'string' ),
			'video_count' => array( 'type' => 'integer' ),
		) ),
		'callback'      => function ( $input ) use ( $call ) {
			if ( ! absint( $input['library_id'] ?? 0 ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'library_id is required.' );
			}
			$result = $call(
				'\FluentPlayerPro\App\Http\Controllers\BunnyCDNController',
				'getCollections',
				$input,
				null,
				array( 'library_id' )
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$items = $result['collections'] ?? ( is_array( $result ) ? array_values( $result ) : array() );
			return array( 'collections' => $items, 'total' => is_array( $items ) ? count( $items ) : 0 );
		},
	) );

	$reg->write( 'fluent-player/bunny-stream-create-collection', array(
		'label'         => 'Bunny Stream — create collection',
		'description'   => 'Create a Bunny Stream collection in a library.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'library_id', 'name' ),
			'properties' => array(
				'library_id' => array( 'type' => 'integer' ),
				'name'       => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'collection' => array( 'type' => array( 'object', 'null' ) ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function ( $input ) use ( $call ) {
			if ( ! absint( $input['library_id'] ?? 0 ) || empty( $input['name'] ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'library_id and name are required.' );
			}
			$result = $call(
				'\FluentPlayerPro\App\Http\Controllers\BunnyCDNController',
				'createCollection',
				$input,
				null,
				array( 'library_id', 'name' )
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array(
				'success'    => true,
				'collection' => $result['collection'] ?? $result,
			);
		},
	) );

	$reg->write( 'fluent-player/bunny-stream-update-collection', array(
		'label'         => 'Bunny Stream — update collection',
		'description'   => 'Rename a Bunny Stream collection.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'library_id', 'collection_id', 'name' ),
			'properties' => array(
				'library_id'    => array( 'type' => 'integer' ),
				'collection_id' => array( 'type' => 'string' ),
				'name'          => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'collection' => array( 'type' => array( 'object', 'null' ) ),
		) ),
		'callback'      => function ( $input ) use ( $call ) {
			$collection_id = isset( $input['collection_id'] ) ? sanitize_text_field( $input['collection_id'] ) : '';
			if ( ! absint( $input['library_id'] ?? 0 ) || '' === $collection_id || empty( $input['name'] ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'library_id, collection_id, and name are required.' );
			}
			$result = $call(
				'\FluentPlayerPro\App\Http\Controllers\BunnyCDNController',
				'updateCollection',
				$input,
				$collection_id,
				array( 'library_id', 'name' )
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array(
				'success'    => true,
				'collection' => $result['collection'] ?? $result,
			);
		},
	) );

	$reg->delete( 'fluent-player/bunny-stream-delete-collection', array(
		'label'         => 'Bunny Stream — delete collection',
		'description'   => 'Delete a Bunny Stream collection.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'library_id', 'collection_id' ),
			'properties' => array(
				'library_id'    => array( 'type' => 'integer' ),
				'collection_id' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'message' => array( 'type' => 'string' ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function ( $input ) use ( $call ) {
			$collection_id = isset( $input['collection_id'] ) ? sanitize_text_field( $input['collection_id'] ) : '';
			if ( ! absint( $input['library_id'] ?? 0 ) || '' === $collection_id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'library_id and collection_id are required.' );
			}
			$result = $call(
				'\FluentPlayerPro\App\Http\Controllers\BunnyCDNController',
				'deleteCollection',
				$input,
				$collection_id,
				array( 'library_id' )
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array(
				'success' => true,
				'message' => $result['message'] ?? 'Collection deleted.',
			);
		},
	) );

	// ─── Cluster 15: Bunny Storage ─────────────────────────────────────────

	$reg->read( 'fluent-player/bunny-storage-list-videos', array(
		'label'         => 'Bunny Storage — list videos',
		'description'   => 'List files at a Bunny Storage path.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => array(
				'path' => array( 'type' => 'string', 'default' => '/' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'items' ),
		'callback'      => function ( $input ) use ( $call ) {
			$result = $call(
				'\FluentPlayerPro\App\Http\Controllers\BunnyCDNStorageController',
				'listVideos',
				$input,
				null,
				array( 'path' )
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$items = $result['items'] ?? ( is_array( $result ) ? array_values( $result ) : array() );
			return array( 'items' => $items, 'total' => is_array( $items ) ? count( $items ) : 0 );
		},
	) );

	$reg->read( 'fluent-player/bunny-storage-get-video', array(
		'label'         => 'Bunny Storage — get video',
		'description'   => 'Get a single file at a Bunny Storage path.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'path' ),
			'properties' => array(
				'path' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'item' => array( 'type' => array( 'object', 'null' ) ),
		) ),
		'callback'      => function ( $input ) use ( $call ) {
			if ( empty( $input['path'] ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'path is required.' );
			}
			$result = $call(
				'\FluentPlayerPro\App\Http\Controllers\BunnyCDNStorageController',
				'getVideo',
				$input,
				null,
				array( 'path' )
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array( 'item' => $result['item'] ?? $result );
		},
	) );

	$reg->delete( 'fluent-player/bunny-storage-delete-video', array(
		'label'         => 'Bunny Storage — delete video',
		'description'   => 'Delete a file at a Bunny Storage path.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'path' ),
			'properties' => array(
				'path' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'message' => array( 'type' => 'string' ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function ( $input ) use ( $call ) {
			if ( empty( $input['path'] ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'path is required.' );
			}
			$result = $call(
				'\FluentPlayerPro\App\Http\Controllers\BunnyCDNStorageController',
				'deleteVideo',
				$input,
				null,
				array( 'path' )
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array(
				'success' => true,
				'message' => $result['message'] ?? 'File deleted.',
			);
		},
	) );

	$reg->write( 'fluent-player/bunny-storage-create-directory', array(
		'label'         => 'Bunny Storage — create directory',
		'description'   => 'Create a directory in Bunny Storage.',
		'category'      => 'fluent-player',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'name' ),
			'properties' => array(
				'parent_path' => array( 'type' => 'string', 'default' => '/' ),
				'name'        => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'message' => array( 'type' => 'string' ),
		) ),
		'annotations'   => array( 'idempotent' => false ),
		'callback'      => function ( $input ) use ( $call ) {
			if ( empty( $input['name'] ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'name is required.' );
			}
			$result = $call(
				'\FluentPlayerPro\App\Http\Controllers\BunnyCDNStorageController',
				'createDirectory',
				$input,
				null,
				array( 'parent_path', 'name' )
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array(
				'success' => true,
				'message' => $result['message'] ?? 'Directory created.',
			);
		},
	) );
}
add_action( 'wp_abilities_api_init', 'fluent_abilities_player_register_bunny_abilities', 100 );
