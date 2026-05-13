<?php
/**
 * Fluent Boards — CSV / Export Import (Research §4.27)
 *
 * 3 abilities. Tier: pro.
 *
 * CSV import is a two-step flow:
 *   1) upload-csv:    upload a CSV and stage it; returns csv_id + preview rows
 *   2) import-csv-to-board:  apply column_mapping and import rows as tasks
 *
 * import-fluent-boards-export takes a full export package (json/zip) and
 * restores boards + stages + tasks together.
 *
 * Staged CSVs are stored in fbs_metas with object_type='csv_upload'.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

// =========================================================================
// §4.27.1 — upload-csv
// =========================================================================
$reg->write( 'fluent-boards/upload-csv', array(
	'label'       => 'Upload CSV (Pro)',
	'description' => 'Stage a CSV upload for later mapping/import. Accepts either an existing WordPress attachment_id pointing to a CSV file, or a remote csv_url (sideloaded with SSRF validation). Returns csv_id, detected columns, and a preview of the first rows.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'attachment_id' => array( 'type' => 'integer' ),
			'csv_url'       => array( 'type' => 'string' ),
			'preview_rows'  => array( 'type' => 'integer', 'default' => 5, 'minimum' => 1, 'maximum' => 50 ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'csv_id'  => array( 'type' => 'integer' ),
		'columns' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
		'preview' => array( 'type' => 'array' ),
		'rows'    => array( 'type' => 'integer' ),
	) ),
	'annotations' => array( 'idempotent' => false ),
	'callback'    => function( $input ) {
		$attachment_id = (int) ( $input['attachment_id'] ?? 0 );
		$path          = '';
		if ( $attachment_id ) {
			$path = (string) get_attached_file( $attachment_id );
		} elseif ( ! empty( $input['csv_url'] ) ) {
			$validated = fluent_abilities_validate_url( $input['csv_url'] );
			if ( is_wp_error( $validated ) ) {
				return $validated;
			}
			if ( ! function_exists( 'download_url' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			$tmp = download_url( $validated );
			if ( is_wp_error( $tmp ) ) {
				return $tmp;
			}
			$path = (string) $tmp;
		}
		if ( ! $path || ! file_exists( $path ) ) {
			return fluent_abilities_error( 'ability_invalid_input', 'Provide attachment_id or csv_url.' );
		}
		$preview_rows = max( 1, min( 50, (int) ( $input['preview_rows'] ?? 5 ) ) );
		$handle       = fopen( $path, 'r' );
		if ( ! $handle ) {
			return fluent_abilities_error( 'io_error', 'Could not open CSV.' );
		}
		$columns = fgetcsv( $handle );
		$columns = is_array( $columns ) ? array_map( 'strval', $columns ) : array();
		$preview = array();
		$total   = 0;
		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			$total++;
			if ( count( $preview ) < $preview_rows ) {
				$preview[] = array_combine( $columns, array_slice( $row, 0, count( $columns ) ) ?: array_fill( 0, count( $columns ), null ) );
			}
		}
		fclose( $handle );
		$now    = current_time( 'mysql' );
		$csv_id = wpFluent()->table( 'fbs_metas' )->insert( array(
			'object_id'   => $attachment_id,
			'object_type' => 'csv_upload',
			'key'         => 'staged_csv',
			'value'       => maybe_serialize( array( 'path' => $path, 'columns' => $columns, 'rows' => $total ) ),
			'created_at'  => $now,
			'updated_at'  => $now,
		) );
		return array( 'success' => true, 'csv_id' => (int) $csv_id, 'columns' => $columns, 'preview' => $preview, 'rows' => $total );
	},
) );

// =========================================================================
// §4.27.2 — import-csv-to-board (idempotent:false)
// =========================================================================
$reg->write( 'fluent-boards/import-csv-to-board', array(
	'label'       => 'Import CSV To Board (Pro)',
	'description' => 'Import a staged CSV into a board as tasks. column_mapping maps CSV column → fbs_tasks column (e.g. {Subject: "title", Notes: "description"}).',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'csv_id', 'column_mapping' ),
		'properties' => array(
			'board_id'       => array( 'type' => 'integer' ),
			'csv_id'         => array( 'type' => 'integer' ),
			'column_mapping' => array( 'type' => 'object' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'board_id' => array( 'type' => 'integer' ),
		'imported' => array( 'type' => 'integer' ),
	) ),
	'annotations' => array( 'idempotent' => false ),
	'callback'    => function( $input ) {
		$board_id = (int) $input['board_id'];
		$csv_id   = (int) $input['csv_id'];
		$mapping  = (array) ( $input['column_mapping'] ?? array() );
		$row      = wpFluent()->table( 'fbs_metas' )->where( 'id', $csv_id )->where( 'object_type', 'csv_upload' )->first();
		if ( ! $row ) {
			return fluent_abilities_error( 'not_found', 'Staged CSV not found.' );
		}
		if ( ! wpFluent()->table( 'fbs_boards' )->where( 'id', $board_id )->first() ) {
			return fluent_abilities_error( 'not_found', 'Board not found.' );
		}
		$meta = maybe_unserialize( $row->value ?? '' );
		$meta = is_array( $meta ) ? $meta : array();
		$path = $meta['path'] ?? '';
		if ( ! $path || ! file_exists( $path ) ) {
			return fluent_abilities_error( 'not_found', 'CSV file is no longer available.' );
		}
		$handle  = fopen( $path, 'r' );
		$columns = fgetcsv( $handle );
		$columns = is_array( $columns ) ? array_map( 'strval', $columns ) : array();
		$now     = current_time( 'mysql' );
		$count   = 0;
		while ( ( $cells = fgetcsv( $handle ) ) !== false ) {
			$assoc = array_combine( $columns, array_slice( $cells, 0, count( $columns ) ) ?: array_fill( 0, count( $columns ), null ) );
			$task  = array(
				'board_id'   => $board_id,
				'type'       => 'task',
				'created_by' => (int) get_current_user_id(),
				'created_at' => $now,
				'updated_at' => $now,
			);
			foreach ( $mapping as $csv_col => $task_col ) {
				if ( ! is_string( $task_col ) || '' === $task_col ) { continue; }
				$task[ sanitize_key( $task_col ) ] = isset( $assoc[ $csv_col ] ) ? sanitize_text_field( (string) $assoc[ $csv_col ] ) : null;
			}
			wpFluent()->table( 'fbs_tasks' )->insert( $task );
			$count++;
		}
		fclose( $handle );
		return array( 'success' => true, 'board_id' => $board_id, 'imported' => $count );
	},
) );

// =========================================================================
// §4.27.3 — import-fluent-boards-export (idempotent:false)
// =========================================================================
$reg->write( 'fluent-boards/import-fluent-boards-export', array(
	'label'       => 'Import Fluent Boards Export (Pro)',
	'description' => 'Import a Fluent Boards export package — JSON describing boards, stages, labels, custom fields, and tasks. Accepts attachment_id of an uploaded JSON file or an inline export_payload object.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'attachment_id'  => array( 'type' => 'integer' ),
			'export_payload' => array(
				'type'       => 'object',
				'description' => '{boards: [{...}], stages: [{...}], labels: [{...}], custom_fields: [{...}], tasks: [{...}]}',
			),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'boards_imported' => array( 'type' => 'integer' ),
		'tasks_imported'  => array( 'type' => 'integer' ),
	) ),
	'annotations' => array( 'idempotent' => false ),
	'callback'    => function( $input ) {
		$payload = (array) ( $input['export_payload'] ?? array() );
		if ( empty( $payload ) && ! empty( $input['attachment_id'] ) ) {
			$path = (string) get_attached_file( (int) $input['attachment_id'] );
			if ( $path && file_exists( $path ) ) {
				$raw = file_get_contents( $path );
				$decoded = json_decode( $raw, true );
				if ( is_array( $decoded ) ) {
					$payload = $decoded;
				}
			}
		}
		if ( empty( $payload ) ) {
			return fluent_abilities_error( 'ability_invalid_input', 'Provide attachment_id or export_payload.' );
		}
		$now             = current_time( 'mysql' );
		$boards_imported = 0;
		$tasks_imported  = 0;
		$board_id_map    = array();
		$stage_id_map    = array();
		foreach ( (array) ( $payload['boards'] ?? array() ) as $b ) {
			$new_bid = wpFluent()->table( 'fbs_boards' )->insert( array(
				'title'      => sanitize_text_field( $b['title'] ?? 'Imported Board' ),
				'description'=> sanitize_textarea_field( $b['description'] ?? '' ),
				'type'       => sanitize_key( $b['type'] ?? 'to-do' ),
				'status'     => 'active',
				'created_by' => (int) get_current_user_id(),
				'created_at' => $now,
				'updated_at' => $now,
			) );
			$board_id_map[ (int) ( $b['id'] ?? 0 ) ] = (int) $new_bid;
			$boards_imported++;
		}
		foreach ( (array) ( $payload['stages'] ?? array() ) as $s ) {
			$src_bid  = (int) ( $s['board_id'] ?? 0 );
			$dst_bid  = $board_id_map[ $src_bid ] ?? 0;
			if ( ! $dst_bid ) { continue; }
			$new_sid  = wpFluent()->table( 'fbs_board_terms' )->insert( array(
				'board_id'   => $dst_bid,
				'type'       => 'stage',
				'title'      => sanitize_text_field( $s['title'] ?? '' ),
				'position'   => $s['position'] ?? 0,
				'created_at' => $now,
				'updated_at' => $now,
			) );
			$stage_id_map[ (int) ( $s['id'] ?? 0 ) ] = (int) $new_sid;
		}
		foreach ( (array) ( $payload['tasks'] ?? array() ) as $t ) {
			$src_bid = (int) ( $t['board_id'] ?? 0 );
			$dst_bid = $board_id_map[ $src_bid ] ?? 0;
			if ( ! $dst_bid ) { continue; }
			wpFluent()->table( 'fbs_tasks' )->insert( array(
				'board_id'   => $dst_bid,
				'stage_id'   => $stage_id_map[ (int) ( $t['stage_id'] ?? 0 ) ] ?? 0,
				'type'       => 'task',
				'title'      => sanitize_text_field( $t['title'] ?? '' ),
				'description'=> $t['description'] ?? '',
				'priority'   => $t['priority'] ?? null,
				'position'   => $t['position'] ?? 0,
				'created_by' => (int) get_current_user_id(),
				'created_at' => $now,
				'updated_at' => $now,
			) );
			$tasks_imported++;
		}
		return array( 'success' => true, 'boards_imported' => $boards_imported, 'tasks_imported' => $tasks_imported );
	},
) );
