<?php
/**
 * Fluent Boards — Discovery + Board Properties (Research §4.1 + §4.2)
 *
 * 11 discovery abilities + 5 board-properties/duplication abilities = 16 total.
 *
 * KD-7 ([#51]) — Board::boot global scope excludes 'sales-pipeline'.
 *   Discovery abilities below use raw wpFluent()->table('fbs_boards') so
 *   sales-pipeline boards surface in listings (matches existing v1.1.3 pattern
 *   for list/get/move/duplicate; documented per ability description).
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

// Loaded inside the wp_abilities_api_init callback in abilities.php.
// $reg (Fluent_Abilities_Registrar) is already available in scope.

// =========================================================================
// §4.1.1 — list-recent-boards
// =========================================================================
$reg->read( 'fluent-boards/list-recent-boards', array(
	'label'        => 'List Recent Boards',
	'description'  => 'List recently accessed boards for the current user. Uses raw fbs_boards query (KD-7) so sales-pipeline boards are included.',
	'category'     => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'properties' => fluent_abilities_pagination_schema(),
	),
	'output_schema' => fluent_abilities_schema_list_output( 'boards', array(
		'id'         => array( 'type' => 'integer' ),
		'title'      => array( 'type' => array( 'string', 'null' ) ),
		'type'       => array( 'type' => array( 'string', 'null' ) ),
		'updated_at' => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$pagination = fluent_abilities_pagination( $input, 10 );
		$query      = wpFluent()->table( 'fbs_boards' )
			->whereNull( 'archived_at' )
			->orderBy( 'updated_at', 'DESC' );
		$total = (int) $query->count();
		$rows  = $query->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();
		$items = array();
		foreach ( $rows as $b ) {
			$items[] = array(
				'id'         => (int) $b->id,
				'title'      => $b->title ?? '',
				'type'       => $b->type ?? null,
				'updated_at' => (string) ( $b->updated_at ?? '' ),
			);
		}
		return array( 'boards' => $items, 'total' => $total, 'page' => $pagination['page'], 'per_page' => $pagination['per_page'] );
	},
) );

// =========================================================================
// §4.1.2 — list-pinned-boards
// =========================================================================
$reg->read( 'fluent-boards/list-pinned-boards', array(
	'label'        => 'List Pinned Boards',
	'description'  => 'List boards pinned by the current user (or by user_id if provided). Raw fbs_relations + fbs_boards (KD-7 inclusive).',
	'category'     => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'user_id' => array( 'type' => 'integer', 'description' => 'Defaults to current WordPress user.' ),
		),
	),
	'output_schema' => fluent_abilities_schema_collection_output( 'boards', array(
		'id'    => array( 'type' => 'integer' ),
		'title' => array( 'type' => array( 'string', 'null' ) ),
		'type'  => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$user_id = ! empty( $input['user_id'] ) ? (int) $input['user_id'] : (int) get_current_user_id();
		if ( ! $user_id ) {
			return fluent_abilities_error( 'ability_invalid_input', 'user_id required when no current user is set.' );
		}
		$rels = wpFluent()->table( 'fbs_relations' )
			->where( 'object_type', 'board_user' )
			->where( 'foreign_id', $user_id )
			->get();
		$pinned_ids = array();
		foreach ( $rels as $r ) {
			$settings = maybe_unserialize( $r->settings ?? '' );
			if ( is_array( $settings ) && ! empty( $settings['is_pinned'] ) ) {
				$pinned_ids[] = (int) $r->object_id;
			}
		}
		if ( empty( $pinned_ids ) ) {
			return array( 'boards' => array(), 'total' => 0 );
		}
		$rows  = wpFluent()->table( 'fbs_boards' )->whereIn( 'id', $pinned_ids )->get();
		$items = array();
		foreach ( $rows as $b ) {
			$items[] = array( 'id' => (int) $b->id, 'title' => $b->title ?? '', 'type' => $b->type ?? null );
		}
		return array( 'boards' => $items, 'total' => count( $items ) );
	},
) );

// =========================================================================
// §4.1.3 — list-user-accessible-boards
// =========================================================================
$reg->read( 'fluent-boards/list-user-accessible-boards', array(
	'label'        => 'List User-Accessible Boards',
	'description'  => 'List boards the given user has access to via fbs_relations.board_user. Defaults to current user. KD-7: uses raw query (sales-pipeline included).',
	'category'     => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'user_id' => array( 'type' => 'integer', 'description' => 'Defaults to current WordPress user.' ),
		),
	),
	'output_schema' => fluent_abilities_schema_collection_output( 'boards', array(
		'id'         => array( 'type' => 'integer' ),
		'title'      => array( 'type' => array( 'string', 'null' ) ),
		'type'       => array( 'type' => array( 'string', 'null' ) ),
		'is_admin'   => array( 'type' => 'boolean' ),
		'is_viewer'  => array( 'type' => 'boolean' ),
	) ),
	'callback' => function( $input ) {
		$user_id = ! empty( $input['user_id'] ) ? (int) $input['user_id'] : (int) get_current_user_id();
		if ( ! $user_id ) {
			return fluent_abilities_error( 'ability_invalid_input', 'user_id required when no current user is set.' );
		}
		$rels = wpFluent()->table( 'fbs_relations' )
			->where( 'object_type', 'board_user' )
			->where( 'foreign_id', $user_id )
			->get();
		$by_board = array();
		foreach ( $rels as $r ) {
			$by_board[ (int) $r->object_id ] = maybe_unserialize( $r->settings ?? '' ) ?: array();
		}
		if ( empty( $by_board ) ) {
			return array( 'boards' => array(), 'total' => 0 );
		}
		$rows  = wpFluent()->table( 'fbs_boards' )->whereIn( 'id', array_keys( $by_board ) )->get();
		$items = array();
		foreach ( $rows as $b ) {
			$s = $by_board[ (int) $b->id ];
			$items[] = array(
				'id'        => (int) $b->id,
				'title'     => $b->title ?? '',
				'type'      => $b->type ?? null,
				'is_admin'  => ! empty( $s['is_admin'] ),
				'is_viewer' => ! empty( $s['is_viewer_only'] ),
			);
		}
		return array( 'boards' => $items, 'total' => count( $items ) );
	},
) );

// =========================================================================
// §4.1.4 — list-user-admin-boards
// =========================================================================
$reg->read( 'fluent-boards/list-user-admin-boards', array(
	'label'        => 'List User Admin Boards',
	'description'  => 'List boards where the given user is admin (is_admin flag in fbs_relations.settings).',
	'category'     => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'user_id' => array( 'type' => 'integer', 'description' => 'Defaults to current WordPress user.' ),
		),
	),
	'output_schema' => fluent_abilities_schema_collection_output( 'boards', array(
		'id'    => array( 'type' => 'integer' ),
		'title' => array( 'type' => array( 'string', 'null' ) ),
		'type'  => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$user_id = ! empty( $input['user_id'] ) ? (int) $input['user_id'] : (int) get_current_user_id();
		if ( ! $user_id ) {
			return fluent_abilities_error( 'ability_invalid_input', 'user_id required when no current user is set.' );
		}
		$rels = wpFluent()->table( 'fbs_relations' )
			->where( 'object_type', 'board_user' )
			->where( 'foreign_id', $user_id )
			->get();
		$admin_ids = array();
		foreach ( $rels as $r ) {
			$s = maybe_unserialize( $r->settings ?? '' );
			if ( is_array( $s ) && ! empty( $s['is_admin'] ) ) {
				$admin_ids[] = (int) $r->object_id;
			}
		}
		if ( empty( $admin_ids ) ) {
			return array( 'boards' => array(), 'total' => 0 );
		}
		$rows  = wpFluent()->table( 'fbs_boards' )->whereIn( 'id', $admin_ids )->get();
		$items = array();
		foreach ( $rows as $b ) {
			$items[] = array( 'id' => (int) $b->id, 'title' => $b->title ?? '', 'type' => $b->type ?? null );
		}
		return array( 'boards' => $items, 'total' => count( $items ) );
	},
) );

// =========================================================================
// §4.1.5 — list-boards-by-type (KD-7: sales-pipeline included via enum)
// =========================================================================
$reg->read( 'fluent-boards/list-boards-by-type', array(
	'label'        => 'List Boards By Type',
	'description'  => 'List boards filtered by single-table-inheritance type discriminator: to-do, roadmap, or sales-pipeline. KD-7: raw query bypasses the Board model global scope so sales-pipeline is accessible here.',
	'category'     => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'type' ),
		'properties' => array(
			'type' => array(
				'type'        => 'string',
				'enum'        => array( 'to-do', 'roadmap', 'sales-pipeline' ),
				'description' => 'Board type discriminator (required).',
			),
		),
	),
	'output_schema' => fluent_abilities_schema_collection_output( 'boards', array(
		'id'         => array( 'type' => 'integer' ),
		'title'      => array( 'type' => array( 'string', 'null' ) ),
		'type'       => array( 'type' => array( 'string', 'null' ) ),
		'status'     => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$type = sanitize_text_field( $input['type'] ?? '' );
		if ( ! in_array( $type, array( 'to-do', 'roadmap', 'sales-pipeline' ), true ) ) {
			return fluent_abilities_error( 'ability_invalid_input', 'type must be one of to-do, roadmap, sales-pipeline.' );
		}
		$rows  = wpFluent()->table( 'fbs_boards' )->where( 'type', $type )->orderBy( 'id', 'DESC' )->get();
		$items = array();
		foreach ( $rows as $b ) {
			$items[] = array(
				'id'     => (int) $b->id,
				'title'  => $b->title ?? '',
				'type'   => $b->type ?? null,
				'status' => $b->status ?? null,
			);
		}
		return array( 'boards' => $items, 'total' => count( $items ) );
	},
) );

// =========================================================================
// §4.1.6 — list-boards-summary
// =========================================================================
$reg->read( 'fluent-boards/list-boards-summary', array(
	'label'        => 'List Boards Summary',
	'description'  => 'Lightweight board list returning only {id, title, type} for autocomplete / picker UI. KD-7 inclusive.',
	'category'     => 'fluent-boards',
	'output_schema' => fluent_abilities_schema_collection_output( 'boards', array(
		'id'    => array( 'type' => 'integer' ),
		'title' => array( 'type' => array( 'string', 'null' ) ),
		'type'  => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function() {
		$rows  = wpFluent()->table( 'fbs_boards' )->whereNull( 'archived_at' )->select( array( 'id', 'title', 'type' ) )->orderBy( 'title', 'ASC' )->get();
		$items = array();
		foreach ( $rows as $b ) {
			$items[] = array( 'id' => (int) $b->id, 'title' => $b->title ?? '', 'type' => $b->type ?? null );
		}
		return array( 'boards' => $items, 'total' => count( $items ) );
	},
) );

// =========================================================================
// §4.1.7 — get-board-currencies
// =========================================================================
$reg->read( 'fluent-boards/get-board-currencies', array(
	'label'         => 'Get Board Currencies',
	'description'   => 'Return supported currency codes for sales-pipeline boards.',
	'category'      => 'fluent-boards',
	'output_schema' => fluent_abilities_schema_collection_output( 'currencies', array(
		'code'   => array( 'type' => 'string' ),
		'symbol' => array( 'type' => array( 'string', 'null' ) ),
		'label'  => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function() {
		if ( ! class_exists( '\\FluentBoards\\App\\Services\\Helper' ) ) {
			return array( 'currencies' => array(), 'total' => 0 );
		}
		$currencies = array();
		if ( method_exists( '\\FluentBoards\\App\\Services\\Helper', 'getCurrencies' ) ) {
			$raw = \FluentBoards\App\Services\Helper::getCurrencies();
			foreach ( (array) $raw as $code => $meta ) {
				$currencies[] = array(
					'code'   => is_string( $code ) ? $code : ( $meta['code'] ?? '' ),
					'symbol' => is_array( $meta ) ? ( $meta['symbol'] ?? null ) : null,
					'label'  => is_array( $meta ) ? ( $meta['label'] ?? null ) : null,
				);
			}
		}
		return array( 'currencies' => $currencies, 'total' => count( $currencies ) );
	},
) );

// =========================================================================
// §4.1.8 — get-default-board-colors
// =========================================================================
$reg->read( 'fluent-boards/get-default-board-colors', array(
	'label'         => 'Get Default Board Colors',
	'description'   => 'Return the vendor-preset background color options used when a board is created without an explicit background.',
	'category'      => 'fluent-boards',
	'output_schema' => fluent_abilities_schema_collection_output( 'colors', array(
		'id'    => array( 'type' => array( 'string', 'integer' ) ),
		'color' => array( 'type' => 'string' ),
	) ),
	'callback' => function() {
		$colors = array();
		if ( class_exists( '\\FluentBoardsPro\\App\\Models\\Board' ) && method_exists( '\\FluentBoardsPro\\App\\Models\\Board', 'getDefaultBackgrounds' ) ) {
			$colors = (array) \FluentBoardsPro\App\Models\Board::getDefaultBackgrounds();
		} elseif ( class_exists( '\\FluentBoards\\App\\Models\\Board' ) && method_exists( '\\FluentBoards\\App\\Models\\Board', 'getDefaultBackgrounds' ) ) {
			$colors = (array) \FluentBoards\App\Models\Board::getDefaultBackgrounds();
		}
		$items = array();
		foreach ( $colors as $k => $c ) {
			if ( is_array( $c ) ) {
				$items[] = array( 'id' => $c['id'] ?? $k, 'color' => $c['color'] ?? ( $c['value'] ?? '' ) );
			} else {
				$items[] = array( 'id' => $k, 'color' => (string) $c );
			}
		}
		return array( 'colors' => $items, 'total' => count( $items ) );
	},
) );

// =========================================================================
// §4.1.9 — pin-board
// =========================================================================
$reg->write( 'fluent-boards/pin-board', array(
	'label'       => 'Pin Board',
	'description' => 'Pin a board for the current user. Idempotent.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'board_id' => array( 'type' => 'integer' ) ) ),
	'annotations'  => array( 'idempotent' => true ),
	'callback'     => function( $input ) {
		$board_id = (int) ( $input['board_id'] ?? 0 );
		$user_id  = (int) get_current_user_id();
		if ( ! $board_id || ! $user_id ) {
			return fluent_abilities_error( 'ability_invalid_input', 'board_id and an authenticated user are required.' );
		}
		$rel = wpFluent()->table( 'fbs_relations' )
			->where( 'object_type', 'board_user' )
			->where( 'object_id', $board_id )
			->where( 'foreign_id', $user_id )
			->first();
		if ( ! $rel ) {
			return fluent_abilities_error( 'not_found', 'User is not a member of this board.' );
		}
		$settings              = maybe_unserialize( $rel->settings ?? '' );
		$settings              = is_array( $settings ) ? $settings : array();
		$settings['is_pinned'] = true;
		wpFluent()->table( 'fbs_relations' )->where( 'id', $rel->id )->update( array(
			'settings'   => maybe_serialize( $settings ),
			'updated_at' => current_time( 'mysql' ),
		) );
		return array( 'success' => true, 'board_id' => $board_id );
	},
) );

// =========================================================================
// §4.1.10 — unpin-board
// =========================================================================
$reg->write( 'fluent-boards/unpin-board', array(
	'label'       => 'Unpin Board',
	'description' => 'Unpin a board for the current user. Idempotent.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'board_id' => array( 'type' => 'integer' ) ) ),
	'annotations'  => array( 'idempotent' => true ),
	'callback'     => function( $input ) {
		$board_id = (int) ( $input['board_id'] ?? 0 );
		$user_id  = (int) get_current_user_id();
		if ( ! $board_id || ! $user_id ) {
			return fluent_abilities_error( 'ability_invalid_input', 'board_id and an authenticated user are required.' );
		}
		$rel = wpFluent()->table( 'fbs_relations' )
			->where( 'object_type', 'board_user' )
			->where( 'object_id', $board_id )
			->where( 'foreign_id', $user_id )
			->first();
		if ( ! $rel ) {
			return array( 'success' => true, 'board_id' => $board_id );
		}
		$settings = maybe_unserialize( $rel->settings ?? '' );
		$settings = is_array( $settings ) ? $settings : array();
		unset( $settings['is_pinned'] );
		wpFluent()->table( 'fbs_relations' )->where( 'id', $rel->id )->update( array(
			'settings'   => maybe_serialize( $settings ),
			'updated_at' => current_time( 'mysql' ),
		) );
		return array( 'success' => true, 'board_id' => $board_id );
	},
) );

// =========================================================================
// §4.1.11 — has-data-changed
// =========================================================================
$reg->read( 'fluent-boards/has-data-changed', array(
	'label'       => 'Has Board Data Changed',
	'description' => 'Returns {changed: bool} based on whether tasks or stages on the board have updated_at newer than the given timestamp. For client polling.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'last_check_at' ),
		'properties' => array(
			'board_id'      => array( 'type' => 'integer' ),
			'last_check_at' => array( 'type' => 'string', 'description' => 'MySQL datetime (Y-m-d H:i:s).' ),
		),
	),
	'output_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'changed'  => array( 'type' => 'boolean' ),
			'board_id' => array( 'type' => 'integer' ),
		),
	),
	'callback' => function( $input ) {
		$board_id = (int) ( $input['board_id'] ?? 0 );
		$since    = sanitize_text_field( $input['last_check_at'] ?? '' );
		if ( ! $board_id || ! $since ) {
			return fluent_abilities_error( 'ability_invalid_input', 'board_id and last_check_at are required.' );
		}
		$tasks   = (int) wpFluent()->table( 'fbs_tasks' )->where( 'board_id', $board_id )->where( 'updated_at', '>', $since )->count();
		$stages  = (int) wpFluent()->table( 'fbs_board_terms' )->where( 'board_id', $board_id )->where( 'updated_at', '>', $since )->count();
		$boardup = (int) wpFluent()->table( 'fbs_boards' )->where( 'id', $board_id )->where( 'updated_at', '>', $since )->count();
		return array( 'changed' => ( $tasks + $stages + $boardup ) > 0, 'board_id' => $board_id );
	},
) );

// =========================================================================
// §4.2.1 — update-board-properties
// =========================================================================
$reg->write( 'fluent-boards/update-board-properties', array(
	'label'       => 'Update Board Properties',
	'description' => 'Merge-update board settings and background JSON sub-fields without replacing the whole object.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id' ),
		'properties' => array(
			'board_id'   => array( 'type' => 'integer' ),
			'settings'   => array( 'type' => 'object', 'description' => 'Partial settings object (deep-merge).' ),
			'background' => array( 'type' => 'object', 'description' => 'Partial background object (deep-merge).' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'board_id' => array( 'type' => 'integer' ),
		'settings' => array( 'type' => array( 'object', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$board_id = (int) ( $input['board_id'] ?? 0 );
		$board    = wpFluent()->table( 'fbs_boards' )->where( 'id', $board_id )->first();
		if ( ! $board ) {
			return fluent_abilities_error( 'not_found', 'Board not found.' );
		}
		$existing = maybe_unserialize( $board->settings ?? '' );
		$existing = is_array( $existing ) ? $existing : array();
		if ( isset( $input['settings'] ) && is_array( $input['settings'] ) ) {
			$existing = array_replace_recursive( $existing, $input['settings'] );
		}
		if ( isset( $input['background'] ) && is_array( $input['background'] ) ) {
			$existing['background'] = array_replace_recursive( $existing['background'] ?? array(), $input['background'] );
		}
		wpFluent()->table( 'fbs_boards' )->where( 'id', $board_id )->update( array(
			'settings'   => maybe_serialize( $existing ),
			'updated_at' => current_time( 'mysql' ),
		) );
		return array( 'success' => true, 'board_id' => $board_id, 'settings' => $existing );
	},
) );

// =========================================================================
// §4.2.2 — set-board-background
// =========================================================================
$reg->write( 'fluent-boards/set-board-background', array(
	'label'       => 'Set Board Background',
	'description' => 'Replace a board background block (color or image reference) in board settings.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'background' ),
		'properties' => array(
			'board_id'   => array( 'type' => 'integer' ),
			'background' => array(
				'type'       => 'object',
				'properties' => array(
					'id'        => array( 'type' => array( 'string', 'integer' ) ),
					'is_image'  => array( 'type' => 'boolean' ),
					'image_url' => array( 'type' => array( 'string', 'null' ) ),
					'color'     => array( 'type' => array( 'string', 'null' ) ),
				),
			),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'board_id' => array( 'type' => 'integer' ) ) ),
	'callback'     => function( $input ) {
		$board_id   = (int) ( $input['board_id'] ?? 0 );
		$background = is_array( $input['background'] ?? null ) ? $input['background'] : array();
		$board      = wpFluent()->table( 'fbs_boards' )->where( 'id', $board_id )->first();
		if ( ! $board ) {
			return fluent_abilities_error( 'not_found', 'Board not found.' );
		}
		$settings               = maybe_unserialize( $board->settings ?? '' );
		$settings               = is_array( $settings ) ? $settings : array();
		$settings['background'] = $background;
		wpFluent()->table( 'fbs_boards' )->where( 'id', $board_id )->update( array(
			'settings'   => maybe_serialize( $settings ),
			'updated_at' => current_time( 'mysql' ),
		) );
		return array( 'success' => true, 'board_id' => $board_id );
	},
) );

// =========================================================================
// §4.2.3 — upload-board-background-image
// =========================================================================
$reg->write( 'fluent-boards/upload-board-background-image', array(
	'label'       => 'Upload Board Background Image',
	'description' => 'Upload an image file and use it as a board background. Provide at least one of `attachment_id` or `image_url` (both may be supplied — `attachment_id` takes precedence and `image_url` is then ignored; the handler rejects only when NEITHER resolves). Schema declares this via `anyOf` (P5 factually-corrective per installed-handler source: precedence chain `if($attachment_id) elseif($image_url)`, not exactly-one).',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id' ),
		'properties' => array(
			'board_id'      => array( 'type' => 'integer' ),
			'attachment_id' => array( 'type' => 'integer', 'description' => 'Existing WordPress attachment to use.' ),
			'image_url'     => array( 'type' => 'string', 'description' => 'Remote image URL (sideloaded into Media Library).' ),
		),
		// P5 factually-corrective (NOT oneOf): installed handler uses an
		// if($attachment_id) elseif($image_url) precedence chain — BOTH
		// supplied is accepted (attachment_id wins), only NEITHER is
		// rejected. anyOf declares "at least one", matching the handler;
		// oneOf would false-reject the handler-valid both-case (Principle 4).
		'anyOf'      => array(
			array( 'required' => array( 'attachment_id' ) ),
			array( 'required' => array( 'image_url' ) ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'board_id'      => array( 'type' => 'integer' ),
		'attachment_id' => array( 'type' => array( 'integer', 'null' ) ),
		'image_url'     => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$board_id = (int) ( $input['board_id'] ?? 0 );
		$board    = wpFluent()->table( 'fbs_boards' )->where( 'id', $board_id )->first();
		if ( ! $board ) {
			return fluent_abilities_error( 'not_found', 'Board not found.' );
		}
		$attachment_id = (int) ( $input['attachment_id'] ?? 0 );
		$image_url     = '';
		if ( $attachment_id ) {
			$image_url = (string) wp_get_attachment_url( $attachment_id );
		} elseif ( ! empty( $input['image_url'] ) ) {
			$validated = fluent_abilities_validate_url( $input['image_url'] );
			if ( is_wp_error( $validated ) ) {
				return $validated;
			}
			if ( ! function_exists( 'media_sideload_image' ) ) {
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}
			$sideload = media_sideload_image( $validated, 0, null, 'src' );
			if ( is_wp_error( $sideload ) ) {
				return $sideload;
			}
			$image_url = (string) $sideload;
		}
		if ( ! $image_url ) {
			return fluent_abilities_error( 'ability_invalid_input', 'Provide attachment_id or image_url.' );
		}
		$settings               = maybe_unserialize( $board->settings ?? '' );
		$settings               = is_array( $settings ) ? $settings : array();
		$settings['background'] = array( 'is_image' => true, 'image_url' => $image_url );
		wpFluent()->table( 'fbs_boards' )->where( 'id', $board_id )->update( array(
			'settings'   => maybe_serialize( $settings ),
			'updated_at' => current_time( 'mysql' ),
		) );
		return array( 'success' => true, 'board_id' => $board_id, 'attachment_id' => $attachment_id ?: null, 'image_url' => $image_url );
	},
) );

// =========================================================================
// §4.2.4 — duplicate-board (idempotent:false; clones tasks + members optionally)
// =========================================================================
$reg->write( 'fluent-boards/duplicate-board', array(
	'label'       => 'Duplicate Board',
	'description' => 'Create a new board cloned from source. Optionally copies tasks (with stages) and members. Each call creates a new board id; not idempotent.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'new_title' ),
		'properties' => array(
			'board_id'      => array( 'type' => 'integer' ),
			'new_title'     => array( 'type' => 'string' ),
			'clone_tasks'   => array( 'type' => 'boolean', 'default' => false ),
			'clone_members' => array( 'type' => 'boolean', 'default' => false ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'board_id'     => array( 'type' => 'integer' ),
		'new_board_id' => array( 'type' => 'integer' ),
	) ),
	'annotations' => array( 'idempotent' => false ),
	'callback'    => function( $input ) {
		$src_id    = (int) ( $input['board_id'] ?? 0 );
		$new_title = sanitize_text_field( $input['new_title'] ?? '' );
		if ( ! $src_id || ! $new_title ) {
			return fluent_abilities_error( 'ability_invalid_input', 'board_id and new_title are required.' );
		}
		$src = wpFluent()->table( 'fbs_boards' )->where( 'id', $src_id )->first();
		if ( ! $src ) {
			return fluent_abilities_error( 'not_found', 'Source board not found.' );
		}
		$now      = current_time( 'mysql' );
		$new_id   = wpFluent()->table( 'fbs_boards' )->insertGetId( array(
			'title'       => $new_title,
			'description' => $src->description ?? '',
			'type'        => $src->type ?? 'to-do',
			'settings'    => $src->settings ?? '',
			'currency'    => $src->currency ?? null,
			'created_by'  => (int) get_current_user_id(),
			'created_at'  => $now,
			'updated_at'  => $now,
		) );

		// Clone stages always (stages without tasks is the empty-board case).
		$stage_map = array();
		$stages    = wpFluent()->table( 'fbs_board_terms' )->where( 'board_id', $src_id )->where( 'type', 'stage' )->orderBy( 'position', 'ASC' )->get();
		foreach ( $stages as $st ) {
			$new_sid = wpFluent()->table( 'fbs_board_terms' )->insertGetId( array(
				'board_id'   => $new_id,
				'type'       => 'stage',
				'title'      => $st->title ?? '',
				'position'   => $st->position ?? 0,
				'settings'   => $st->settings ?? '',
				'created_at' => $now,
				'updated_at' => $now,
			) );
			$stage_map[ (int) $st->id ] = (int) $new_sid;
		}

		if ( ! empty( $input['clone_tasks'] ) ) {
			$tasks = wpFluent()->table( 'fbs_tasks' )->where( 'board_id', $src_id )->whereNull( 'parent_id' )->get();
			foreach ( $tasks as $t ) {
				wpFluent()->table( 'fbs_tasks' )->insert( array(
					'board_id'   => $new_id,
					'stage_id'   => $stage_map[ (int) ( $t->stage_id ?? 0 ) ] ?? 0,
					'type'       => $t->type ?? 'task',
					'title'      => $t->title ?? '',
					'description'=> $t->description ?? '',
					'priority'   => $t->priority ?? null,
					'position'   => $t->position ?? 0,
					'created_by' => (int) get_current_user_id(),
					'created_at' => $now,
					'updated_at' => $now,
				) );
			}
		}

		if ( ! empty( $input['clone_members'] ) ) {
			$rels = wpFluent()->table( 'fbs_relations' )->where( 'object_type', 'board_user' )->where( 'object_id', $src_id )->get();
			foreach ( $rels as $r ) {
				wpFluent()->table( 'fbs_relations' )->insert( array(
					'object_id'   => $new_id,
					'object_type' => 'board_user',
					'foreign_id'  => $r->foreign_id,
					'settings'    => $r->settings ?? '',
					'preferences' => $r->preferences ?? '',
					'created_at'  => $now,
					'updated_at'  => $now,
				) );
			}
		}

		return array( 'success' => true, 'board_id' => $src_id, 'new_board_id' => (int) $new_id );
	},
) );

// =========================================================================
// §4.2.5 — import-from-board (idempotent:false)
// =========================================================================
$reg->write( 'fluent-boards/import-from-board', array(
	'label'       => 'Import Stages/Tasks From Another Board',
	'description' => 'Copy stages and/or open tasks from source_board_id into target_board_id, preserving stage ordering and task→stage relations. Not idempotent.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'target_board_id', 'source_board_id' ),
		'properties' => array(
			'target_board_id' => array( 'type' => 'integer' ),
			'source_board_id' => array( 'type' => 'integer' ),
			'import_stages'   => array( 'type' => 'boolean', 'default' => true ),
			'import_tasks'    => array( 'type' => 'boolean', 'default' => false ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'target_board_id' => array( 'type' => 'integer' ),
		'imported_stages' => array( 'type' => 'integer' ),
		'imported_tasks'  => array( 'type' => 'integer' ),
	) ),
	'annotations' => array( 'idempotent' => false ),
	'callback'    => function( $input ) {
		$tgt = (int) ( $input['target_board_id'] ?? 0 );
		$src = (int) ( $input['source_board_id'] ?? 0 );
		if ( ! $tgt || ! $src ) {
			return fluent_abilities_error( 'ability_invalid_input', 'target_board_id and source_board_id are required.' );
		}
		$src_board = wpFluent()->table( 'fbs_boards' )->where( 'id', $src )->first();
		$tgt_board = wpFluent()->table( 'fbs_boards' )->where( 'id', $tgt )->first();
		if ( ! $src_board || ! $tgt_board ) {
			return fluent_abilities_error( 'not_found', 'Source or target board not found.' );
		}
		$now         = current_time( 'mysql' );
		$stage_map   = array();
		$stage_count = 0;
		$task_count  = 0;

		if ( ! isset( $input['import_stages'] ) || ! empty( $input['import_stages'] ) ) {
			$stages = wpFluent()->table( 'fbs_board_terms' )->where( 'board_id', $src )->where( 'type', 'stage' )->orderBy( 'position', 'ASC' )->get();
			foreach ( $stages as $st ) {
				$new_sid = wpFluent()->table( 'fbs_board_terms' )->insertGetId( array(
					'board_id'   => $tgt,
					'type'       => 'stage',
					'title'      => $st->title ?? '',
					'position'   => $st->position ?? 0,
					'settings'   => $st->settings ?? '',
					'created_at' => $now,
					'updated_at' => $now,
				) );
				$stage_map[ (int) $st->id ] = (int) $new_sid;
				$stage_count++;
			}
		}

		if ( ! empty( $input['import_tasks'] ) ) {
			$tasks = wpFluent()->table( 'fbs_tasks' )->where( 'board_id', $src )->whereNull( 'parent_id' )->whereNull( 'archived_at' )->get();
			foreach ( $tasks as $t ) {
				wpFluent()->table( 'fbs_tasks' )->insert( array(
					'board_id'   => $tgt,
					'stage_id'   => $stage_map[ (int) ( $t->stage_id ?? 0 ) ] ?? 0,
					'type'       => $t->type ?? 'task',
					'title'      => $t->title ?? '',
					'description'=> $t->description ?? '',
					'priority'   => $t->priority ?? null,
					'position'   => $t->position ?? 0,
					'created_by' => (int) get_current_user_id(),
					'created_at' => $now,
					'updated_at' => $now,
				) );
				$task_count++;
			}
		}

		return array( 'success' => true, 'target_board_id' => $tgt, 'imported_stages' => $stage_count, 'imported_tasks' => $task_count );
	},
) );
