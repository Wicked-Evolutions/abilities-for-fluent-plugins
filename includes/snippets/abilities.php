<?php
/**
 * Fluent Snippets Abilities
 *
 * Read-only access to Fluent Snippets' file-based snippet storage.
 * Snippets are stored as PHP files in wp-content/fluent-snippet-storage/
 * with an index.php file that returns metadata for all snippets.
 *
 * 7 abilities in the 'fluent-snippets' category.
 * Registered via Fluent_Abilities_Registrar.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	/**
	 * Load and flatten the snippet index file.
	 *
	 * @return array Keyed by file_name, each containing snippet metadata.
	 */
	$load_snippet_index = function() {
		$index_file = WP_CONTENT_DIR . '/fluent-snippet-storage/index.php';
		if ( ! file_exists( $index_file ) ) {
			return array();
		}
		$data = @include $index_file;
		if ( ! is_array( $data ) ) {
			return array();
		}
		// Flatten published + draft into one array.
		$all = array();
		foreach ( array( 'published', 'draft' ) as $status_key ) {
			if ( ! empty( $data[ $status_key ] ) && is_array( $data[ $status_key ] ) ) {
				foreach ( $data[ $status_key ] as $file_name => $snippet ) {
					$all[ $file_name ] = $snippet;
				}
			}
		}
		return $all;
	};

	/**
	 * Load the raw (non-flattened) snippet index.
	 *
	 * @return array { published: array, draft: array }
	 */
	$load_raw_index = function() {
		$index_file = WP_CONTENT_DIR . '/fluent-snippet-storage/index.php';
		if ( ! file_exists( $index_file ) ) {
			return array( 'published' => array(), 'draft' => array() );
		}
		$data = @include $index_file;
		if ( ! is_array( $data ) ) {
			return array( 'published' => array(), 'draft' => array() );
		}
		if ( ! isset( $data['published'] ) ) $data['published'] = array();
		if ( ! isset( $data['draft'] ) )     $data['draft']     = array();
		return $data;
	};

	/**
	 * Write the snippet index back to disk.
	 *
	 * @param array $data { published: array, draft: array }
	 * @return bool
	 */
	$write_snippet_index = function( $data ) {
		$index_file = WP_CONTENT_DIR . '/fluent-snippet-storage/index.php';
		$export = "<?php return " . var_export( $data, true ) . ";\n";
		return file_put_contents( $index_file, $export ) !== false;
	};

	$reg = new Fluent_Abilities_Registrar( 'snippets' );

	// =========================================================================
	// LIST SNIPPETS
	// =========================================================================

	$reg->read( 'fluent-snippets/list-snippets', array(
		'label'       => 'List Snippets',
		'description' => 'List all code snippets from Fluent Snippets. Returns metadata only (no code content). Filter by status or type.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'status' => array(
					'type'        => 'string',
					'description' => 'Filter by status: published or draft',
				),
				'type' => array(
					'type'        => 'string',
					'description' => 'Filter by type: PHP, php_content, CSS, or JS',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'snippets', array(
			'file_name'  => array( 'type' => 'string' ),
			'name'       => array( 'type' => 'string' ),
			'type'       => array( 'type' => 'string' ),
			'status'     => array( 'type' => 'string' ),
			'tags'       => array( 'type' => 'string' ),
			'run_at'     => array( 'type' => 'string' ),
			'priority'   => array( 'type' => 'integer' ),
			'updated_at' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) use ( $load_snippet_index ) {
			$all = $load_snippet_index();

			if ( empty( $all ) ) {
				return array( 'snippets' => array(), 'total' => 0 );
			}

			$items = array();
			foreach ( $all as $file_name => $snippet ) {
				// Filter by status.
				if ( ! empty( $input['status'] ) ) {
					$filter_status = sanitize_text_field( $input['status'] );
					if ( ( $snippet['status'] ?? '' ) !== $filter_status ) {
						continue;
					}
				}

				// Filter by type.
				if ( ! empty( $input['type'] ) ) {
					$filter_type = sanitize_text_field( $input['type'] );
					if ( ( $snippet['type'] ?? '' ) !== $filter_type ) {
						continue;
					}
				}

				$items[] = array(
					'file_name'  => $file_name,
					'name'       => $snippet['name'] ?? '',
					'type'       => $snippet['type'] ?? '',
					'status'     => $snippet['status'] ?? '',
					'tags'       => $snippet['tags'] ?? '',
					'run_at'     => $snippet['run_at'] ?? '',
					'priority'   => $snippet['priority'] ?? 10,
					'updated_at' => $snippet['updated_at'] ?? '',
				);
			}

			return array(
				'snippets' => $items,
				'total'    => count( $items ),
			);
		},
	) );

	// =========================================================================
	// GET SNIPPET
	// =========================================================================

	$reg->read( 'fluent-snippets/get-snippet', array(
		'label'       => 'Get Snippet',
		'description' => 'Get a single snippet by file_name. Returns full index metadata plus the file contents (code).',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'file_name' ),
			'properties' => array(
				'file_name' => array(
					'type'        => 'string',
					'description' => 'Snippet file name (e.g., "1-re-driect-password-reset.php")',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'file_name'    => array( 'type' => 'string' ),
			'name'         => array( 'type' => 'string' ),
			'description'  => array( 'type' => 'string' ),
			'type'         => array( 'type' => 'string' ),
			'status'       => array( 'type' => 'string' ),
			'tags'         => array( 'type' => 'string' ),
			'run_at'       => array( 'type' => 'string' ),
			'priority'     => array( 'type' => 'integer' ),
			'group'        => array( 'type' => 'string' ),
			'condition'    => array( 'type' => 'object' ),
			'load_as_file' => array( 'type' => 'string' ),
			'created_at'   => array( 'type' => 'string' ),
			'updated_at'   => array( 'type' => 'string' ),
			'code'         => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) use ( $load_snippet_index ) {
			$file_name = sanitize_file_name( $input['file_name'] );
			$all = $load_snippet_index();

			if ( ! isset( $all[ $file_name ] ) ) {
				return fluent_abilities_error( 'not_found', 'Snippet not found in index: ' . $file_name );
			}

			$snippet = $all[ $file_name ];

			// Read the actual snippet file content.
			$file_path = WP_CONTENT_DIR . '/fluent-snippet-storage/' . $file_name;
			$code = '';
			if ( file_exists( $file_path ) ) {
				$code = file_get_contents( $file_path );
			}

			return array(
				'file_name'   => $file_name,
				'name'        => $snippet['name'] ?? '',
				'description' => $snippet['description'] ?? '',
				'type'        => $snippet['type'] ?? '',
				'status'      => $snippet['status'] ?? '',
				'tags'        => $snippet['tags'] ?? '',
				'run_at'      => $snippet['run_at'] ?? '',
				'priority'    => $snippet['priority'] ?? 10,
				'group'       => $snippet['group'] ?? '',
				'condition'   => $snippet['condition'] ?? array(),
				'load_as_file'=> $snippet['load_as_file'] ?? '',
				'created_at'  => $snippet['created_at'] ?? '',
				'updated_at'  => $snippet['updated_at'] ?? '',
				'code'        => $code,
			);
		},
	) );

	// =========================================================================
	// GET SNIPPET STATS
	// =========================================================================

	$reg->read( 'fluent-snippets/get-snippet-stats', array(
		'label'       => 'Get Snippet Stats',
		'description' => 'Get statistics: total snippets, counts by status and type, total file size on disk.',
		'input_schema' => array(
			'type'       => 'object',
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'total'            => array( 'type' => 'integer' ),
			'by_status'        => array( 'type' => 'object' ),
			'by_type'          => array( 'type' => 'object' ),
			'total_size'       => array( 'type' => 'integer' ),
			'total_size_human' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) use ( $load_snippet_index ) {
			$all = $load_snippet_index();

			$by_status = array( 'published' => 0, 'draft' => 0 );
			$by_type   = array( 'PHP' => 0, 'php_content' => 0, 'CSS' => 0, 'JS' => 0 );
			$total_size = 0;
			$storage_dir = WP_CONTENT_DIR . '/fluent-snippet-storage/';

			foreach ( $all as $file_name => $snippet ) {
				// Count by status.
				$status = $snippet['status'] ?? 'draft';
				if ( isset( $by_status[ $status ] ) ) {
					$by_status[ $status ]++;
				} else {
					$by_status[ $status ] = 1;
				}

				// Count by type.
				$type = $snippet['type'] ?? '';
				if ( isset( $by_type[ $type ] ) ) {
					$by_type[ $type ]++;
				} elseif ( $type !== '' ) {
					$by_type[ $type ] = 1;
				}

				// File size.
				$file_path = $storage_dir . $file_name;
				if ( file_exists( $file_path ) ) {
					$total_size += filesize( $file_path );
				}
			}

			return array(
				'total'      => count( $all ),
				'by_status'  => $by_status,
				'by_type'    => $by_type,
				'total_size' => $total_size,
				'total_size_human' => size_format( $total_size ),
			);
		},
	) );

	// =========================================================================
	// LIST SNIPPET TAGS
	// =========================================================================

	$reg->read( 'fluent-snippets/list-snippet-tags', array(
		'label'       => 'List Snippet Tags',
		'description' => 'List all unique tags used across snippets with usage counts.',
		'input_schema' => array(
			'type'       => 'object',
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'tags', array(
			'tag'   => array( 'type' => 'string' ),
			'count' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) use ( $load_snippet_index ) {
			$all = $load_snippet_index();

			$tag_counts = array();
			foreach ( $all as $file_name => $snippet ) {
				$tags_raw = $snippet['tags'] ?? '';
				if ( empty( $tags_raw ) ) {
					continue;
				}

				// Tags may be comma-separated string.
				$tags = array_map( 'trim', explode( ',', $tags_raw ) );
				foreach ( $tags as $tag ) {
					if ( $tag === '' ) {
						continue;
					}
					if ( ! isset( $tag_counts[ $tag ] ) ) {
						$tag_counts[ $tag ] = 0;
					}
					$tag_counts[ $tag ]++;
				}
			}

			// Sort alphabetically by tag name.
			ksort( $tag_counts );

			$items = array();
			foreach ( $tag_counts as $tag => $count ) {
				$items[] = array(
					'tag'   => $tag,
					'count' => $count,
				);
			}

			return array(
				'tags'  => $items,
				'total' => count( $items ),
			);
		},
	) );

	// =========================================================================
	// CREATE SNIPPET
	// =========================================================================

	$reg->write( 'fluent-snippets/create-snippet', array(
		'label'       => 'Create Snippet',
		'description' => 'Create a new code snippet in Fluent Snippets storage. Generates a file name from the snippet name. Returns an error if the file already exists.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'name', 'code' ),
			'properties' => array(
				'name' => array(
					'type'        => 'string',
					'description' => 'Human-readable snippet name.',
				),
				'code' => array(
					'type'        => 'string',
					'description' => 'Snippet code content.',
				),
				'type' => array(
					'type'        => 'string',
					'description' => 'Snippet type: PHP, php_content, CSS, or JS. Defaults to PHP.',
					'enum'        => array( 'PHP', 'php_content', 'CSS', 'JS' ),
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'Snippet status: published or draft. Defaults to draft.',
					'enum'        => array( 'published', 'draft' ),
				),
				'description' => array(
					'type'        => 'string',
					'description' => 'Optional snippet description.',
				),
				'tags' => array(
					'type'        => 'string',
					'description' => 'Comma-separated tags.',
				),
				'run_at' => array(
					'type'        => 'string',
					'description' => 'Hook to run snippet at. Defaults to wp_head.',
				),
			),
		),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function( $input ) use ( $load_raw_index, $write_snippet_index ) {
			$name        = sanitize_text_field( $input['name'] ?? '' );
			$code        = $input['code'] ?? '';
			$type        = sanitize_text_field( $input['type'] ?? 'PHP' );
			$status      = sanitize_text_field( $input['status'] ?? 'draft' );
			$description = sanitize_text_field( $input['description'] ?? '' );
			$tags        = sanitize_text_field( $input['tags'] ?? '' );
			$run_at      = sanitize_text_field( $input['run_at'] ?? 'wp_head' );

			if ( $name === '' ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Snippet name is required.' );
			}

			// Determine file extension.
			if ( $type === 'CSS' ) {
				$ext = '.css';
			} elseif ( $type === 'JS' ) {
				$ext = '.js';
			} else {
				$ext = '.php';
			}

			$base_name = sanitize_file_name( strtolower( str_replace( ' ', '-', $name ) ) );
			$file_name = $base_name . $ext;

			$storage_dir = realpath( WP_CONTENT_DIR . '/fluent-snippet-storage' );
			if ( $storage_dir === false ) {
				return fluent_abilities_error( 'not_found', 'Snippet storage directory does not exist.' );
			}

			$file_path = $storage_dir . '/' . $file_name;

			// Realpath containment check (directory must exist for realpath to work on new file).
			$real_dir = realpath( dirname( $file_path ) );
			if ( $real_dir === false || strpos( $real_dir, $storage_dir ) !== 0 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'File path is outside the snippet storage directory.' );
			}

			if ( file_exists( $file_path ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'A snippet file with that name already exists: ' . $file_name );
			}

			if ( file_put_contents( $file_path, $code ) === false ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Failed to write snippet file: ' . $file_name );
			}

			$now  = current_time( 'mysql' );
			$data = $load_raw_index();

			$data[ $status ][ $file_name ] = array(
				'name'         => $name,
				'description'  => $description,
				'type'         => $type,
				'status'       => $status,
				'tags'         => $tags,
				'run_at'       => $run_at,
				'priority'     => 10,
				'group'        => '',
				'condition'    => array(),
				'load_as_file' => '',
				'created_at'   => $now,
				'updated_at'   => $now,
			);

			if ( ! $write_snippet_index( $data ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Snippet file written but index could not be updated.' );
			}

			return array(
				'success'   => true,
				'file_name' => $file_name,
				'message'   => 'Snippet created successfully.',
			);
		},
	) );

	// =========================================================================
	// UPDATE SNIPPET
	// =========================================================================

	$reg->write( 'fluent-snippets/update-snippet', array(
		'label'       => 'Update Snippet',
		'description' => 'Update an existing snippet\'s code and/or metadata by file_name.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'file_name' ),
			'properties' => array(
				'file_name' => array(
					'type'        => 'string',
					'description' => 'Snippet file name (e.g., "my-snippet.php").',
				),
				'code' => array(
					'type'        => 'string',
					'description' => 'New code content. Omit to leave code unchanged.',
				),
				'name' => array(
					'type'        => 'string',
					'description' => 'Updated snippet name.',
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'Updated status: published or draft.',
					'enum'        => array( 'published', 'draft' ),
				),
				'description' => array(
					'type'        => 'string',
					'description' => 'Updated description.',
				),
				'tags' => array(
					'type'        => 'string',
					'description' => 'Updated comma-separated tags.',
				),
				'run_at' => array(
					'type'        => 'string',
					'description' => 'Updated hook to run snippet at.',
				),
			),
		),
		'annotations' => array( 'idempotent' => true ),
		'callback' => function( $input ) use ( $load_raw_index, $write_snippet_index ) {
			$file_name = sanitize_file_name( $input['file_name'] ?? '' );

			if ( $file_name === '' ) {
				return fluent_abilities_error( 'ability_invalid_input', 'file_name is required.' );
			}

			$storage_dir = realpath( WP_CONTENT_DIR . '/fluent-snippet-storage' );
			if ( $storage_dir === false ) {
				return fluent_abilities_error( 'not_found', 'Snippet storage directory does not exist.' );
			}

			$file_path = $storage_dir . '/' . $file_name;
			$real_dir  = realpath( dirname( $file_path ) );
			if ( $real_dir === false || strpos( $real_dir, $storage_dir ) !== 0 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'File path is outside the snippet storage directory.' );
			}

			$data = $load_raw_index();

			// Find which status bucket contains this snippet.
			$found_status = null;
			foreach ( array( 'published', 'draft' ) as $s ) {
				if ( isset( $data[ $s ][ $file_name ] ) ) {
					$found_status = $s;
					break;
				}
			}

			if ( $found_status === null ) {
				return fluent_abilities_error( 'not_found', 'Snippet not found in index: ' . $file_name );
			}

			// Update code on disk if provided.
			if ( isset( $input['code'] ) ) {
				if ( file_put_contents( $file_path, $input['code'] ) === false ) {
					return fluent_abilities_error( 'ability_invalid_input', 'Failed to write snippet file: ' . $file_name );
				}
			}

			// Update index metadata.
			$entry = $data[ $found_status ][ $file_name ];

			if ( isset( $input['name'] ) )        $entry['name']        = sanitize_text_field( $input['name'] );
			if ( isset( $input['description'] ) ) $entry['description'] = sanitize_text_field( $input['description'] );
			if ( isset( $input['tags'] ) )        $entry['tags']        = sanitize_text_field( $input['tags'] );
			if ( isset( $input['run_at'] ) )      $entry['run_at']      = sanitize_text_field( $input['run_at'] );
			$entry['updated_at'] = current_time( 'mysql' );

			// Handle status change: move between buckets.
			$new_status = isset( $input['status'] ) ? sanitize_text_field( $input['status'] ) : $found_status;
			$entry['status'] = $new_status;

			if ( $new_status !== $found_status ) {
				unset( $data[ $found_status ][ $file_name ] );
			}
			$data[ $new_status ][ $file_name ] = $entry;

			if ( ! $write_snippet_index( $data ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Failed to update snippet index.' );
			}

			return array(
				'success'   => true,
				'file_name' => $file_name,
				'message'   => 'Snippet updated successfully.',
			);
		},
	) );

	// =========================================================================
	// DELETE SNIPPET
	// =========================================================================

	$reg->delete( 'fluent-snippets/delete-snippet', array(
		'label'       => 'Delete Snippet',
		'description' => 'Permanently delete a snippet file and remove it from the index.',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'file_name' ),
			'properties' => array(
				'file_name' => array(
					'type'        => 'string',
					'description' => 'Snippet file name to delete (e.g., "my-snippet.php").',
				),
			),
		),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function( $input ) use ( $load_raw_index, $write_snippet_index ) {
			$file_name = sanitize_file_name( $input['file_name'] ?? '' );

			if ( $file_name === '' ) {
				return fluent_abilities_error( 'ability_invalid_input', 'file_name is required.' );
			}

			$storage_dir = realpath( WP_CONTENT_DIR . '/fluent-snippet-storage' );
			if ( $storage_dir === false ) {
				return fluent_abilities_error( 'not_found', 'Snippet storage directory does not exist.' );
			}

			$file_path = $storage_dir . '/' . $file_name;
			$real_dir  = realpath( dirname( $file_path ) );
			if ( $real_dir === false || strpos( $real_dir, $storage_dir ) !== 0 ) {
				return fluent_abilities_error( 'ability_invalid_input', 'File path is outside the snippet storage directory.' );
			}

			$data = $load_raw_index();

			// Find which status bucket contains this snippet.
			$found_status = null;
			foreach ( array( 'published', 'draft' ) as $s ) {
				if ( isset( $data[ $s ][ $file_name ] ) ) {
					$found_status = $s;
					break;
				}
			}

			if ( $found_status === null ) {
				return fluent_abilities_error( 'not_found', 'Snippet not found in index: ' . $file_name );
			}

			// Delete the file.
			if ( file_exists( $file_path ) ) {
				if ( ! unlink( $file_path ) ) {
					return fluent_abilities_error( 'ability_invalid_input', 'Failed to delete snippet file: ' . $file_name );
				}
			}

			// Remove from index.
			unset( $data[ $found_status ][ $file_name ] );

			if ( ! $write_snippet_index( $data ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Snippet deleted but index could not be updated.' );
			}

			return array(
				'success'   => true,
				'file_name' => $file_name,
				'message'   => 'Snippet deleted successfully.',
			);
		},
	) );

}, 100 );
