<?php
/**
 * Fluent Boards — Folders + Board Invitations (Research §4.17 + §4.18)
 *
 * §4.17 Folders                  — 6 abilities (pro)
 * §4.18 Board invitations        — 3 abilities (pro)
 * Total: 9 abilities.
 *
 * Folders are stored as fbs_boards rows with type='folder'. Folder→board
 * membership is fbs_relations with object_type='OBJECT_TYPE_FOLDER_BOARD'.
 * Invitations stored in fbs_metas with object_type='board_invitation'.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

// =========================================================================
// §4.17.1 — list-folders
// =========================================================================
$reg->read( 'fluent-boards/list-folders', array(
	'label'         => 'List Folders (Pro)',
	'description'   => 'List all folder records (fbs_boards WHERE type=folder).',
	'category'      => 'fluent-boards',
	'output_schema' => fluent_abilities_schema_collection_output( 'folders', array(
		'id'          => array( 'type' => 'integer' ),
		'title'       => array( 'type' => array( 'string', 'null' ) ),
		'description' => array( 'type' => array( 'string', 'null' ) ),
		'created_at'  => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function() {
		$rows  = wpFluent()->table( 'fbs_boards' )->where( 'type', 'folder' )->orderBy( 'id', 'DESC' )->get();
		$items = array();
		foreach ( $rows as $r ) {
			$items[] = array(
				'id'          => (int) $r->id,
				'title'       => $r->title ?? '',
				'description' => $r->description ?? '',
				'created_at'  => $r->created_at ?? null,
			);
		}
		return array( 'folders' => $items, 'total' => count( $items ) );
	},
) );

// =========================================================================
// §4.17.2 — create-folder (idempotent:false)
// =========================================================================
$reg->write( 'fluent-boards/create-folder', array(
	'label'       => 'Create Folder (Pro)',
	'description' => 'Create a new folder for organizing boards.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'title' ),
		'properties' => array(
			'title'       => array( 'type' => 'string' ),
			'description' => array( 'type' => 'string' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'folder_id' => array( 'type' => 'integer' ) ) ),
	'annotations'  => array( 'idempotent' => false ),
	'callback'     => function( $input ) {
		$title = sanitize_text_field( $input['title'] ?? '' );
		if ( ! $title ) {
			return fluent_abilities_error( 'ability_invalid_input', 'title is required.' );
		}
		$now    = current_time( 'mysql' );
		$new_id = wpFluent()->table( 'fbs_boards' )->insert( array(
			'title'       => $title,
			'description' => sanitize_textarea_field( $input['description'] ?? '' ),
			'type'        => 'folder',
			'status'      => 'active',
			'created_by'  => (int) get_current_user_id(),
			'created_at'  => $now,
			'updated_at'  => $now,
		) );
		return array( 'success' => true, 'folder_id' => (int) $new_id );
	},
) );

// =========================================================================
// §4.17.3 — update-folder
// =========================================================================
$reg->write( 'fluent-boards/update-folder', array(
	'label'       => 'Update Folder (Pro)',
	'description' => 'Update folder title and/or description.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'folder_id' ),
		'properties' => array(
			'folder_id'   => array( 'type' => 'integer' ),
			'title'       => array( 'type' => 'string' ),
			'description' => array( 'type' => 'string' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'folder_id' => array( 'type' => 'integer' ) ) ),
	'callback'     => function( $input ) {
		$folder_id = (int) $input['folder_id'];
		$folder    = wpFluent()->table( 'fbs_boards' )->where( 'id', $folder_id )->where( 'type', 'folder' )->first();
		if ( ! $folder ) {
			return fluent_abilities_error( 'not_found', 'Folder not found.' );
		}
		$update = array( 'updated_at' => current_time( 'mysql' ) );
		if ( isset( $input['title'] ) ) {
			$update['title'] = sanitize_text_field( $input['title'] );
		}
		if ( isset( $input['description'] ) ) {
			$update['description'] = sanitize_textarea_field( $input['description'] );
		}
		wpFluent()->table( 'fbs_boards' )->where( 'id', $folder_id )->update( $update );
		return array( 'success' => true, 'folder_id' => $folder_id );
	},
) );

// =========================================================================
// §4.17.4 — delete-folder
// =========================================================================
$reg->delete( 'fluent-boards/delete-folder', array(
	'label'       => 'Delete Folder (Pro)',
	'description' => 'Delete a folder. Boards previously inside the folder remain; the folder→board memberships are cleared.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'folder_id' ),
		'properties' => array(
			'folder_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'folder_id' => array( 'type' => 'integer' ) ) ),
	'callback'     => function( $input ) {
		$folder_id = (int) $input['folder_id'];
		$folder    = wpFluent()->table( 'fbs_boards' )->where( 'id', $folder_id )->where( 'type', 'folder' )->first();
		if ( ! $folder ) {
			return fluent_abilities_error( 'not_found', 'Folder not found.' );
		}
		wpFluent()->table( 'fbs_relations' )->where( 'object_type', 'OBJECT_TYPE_FOLDER_BOARD' )->where( 'object_id', $folder_id )->delete();
		wpFluent()->table( 'fbs_boards' )->where( 'id', $folder_id )->delete();
		return array( 'success' => true, 'folder_id' => $folder_id );
	},
) );

// =========================================================================
// §4.17.5 — add-board-to-folder (idempotent:true)
// =========================================================================
$reg->write( 'fluent-boards/add-board-to-folder', array(
	'label'       => 'Add Board To Folder (Pro)',
	'description' => 'Add a board to a folder. Idempotent — repeating is a no-op.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'folder_id', 'board_id' ),
		'properties' => array(
			'folder_id' => array( 'type' => 'integer' ),
			'board_id'  => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'folder_id' => array( 'type' => 'integer' ),
		'board_id'  => array( 'type' => 'integer' ),
	) ),
	'annotations' => array( 'idempotent' => true ),
	'callback'    => function( $input ) {
		$folder_id = (int) $input['folder_id'];
		$board_id  = (int) $input['board_id'];
		$folder    = wpFluent()->table( 'fbs_boards' )->where( 'id', $folder_id )->where( 'type', 'folder' )->first();
		$board     = wpFluent()->table( 'fbs_boards' )->where( 'id', $board_id )->where( 'type', '!=', 'folder' )->first();
		if ( ! $folder || ! $board ) {
			return fluent_abilities_error( 'not_found', 'Folder or board not found.' );
		}
		$exists = wpFluent()->table( 'fbs_relations' )->where( 'object_type', 'OBJECT_TYPE_FOLDER_BOARD' )->where( 'object_id', $folder_id )->where( 'foreign_id', $board_id )->first();
		if ( ! $exists ) {
			$now = current_time( 'mysql' );
			wpFluent()->table( 'fbs_relations' )->insert( array(
				'object_id'   => $folder_id,
				'object_type' => 'OBJECT_TYPE_FOLDER_BOARD',
				'foreign_id'  => $board_id,
				'created_at'  => $now,
				'updated_at'  => $now,
			) );
		}
		return array( 'success' => true, 'folder_id' => $folder_id, 'board_id' => $board_id );
	},
) );

// =========================================================================
// §4.17.6 — remove-board-from-folder
// =========================================================================
$reg->write( 'fluent-boards/remove-board-from-folder', array(
	'label'       => 'Remove Board From Folder (Pro)',
	'description' => 'Remove a board from a folder. Idempotent.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'folder_id', 'board_id' ),
		'properties' => array(
			'folder_id' => array( 'type' => 'integer' ),
			'board_id'  => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'folder_id' => array( 'type' => 'integer' ),
		'board_id'  => array( 'type' => 'integer' ),
	) ),
	'annotations' => array( 'idempotent' => true ),
	'callback'    => function( $input ) {
		$folder_id = (int) $input['folder_id'];
		$board_id  = (int) $input['board_id'];
		wpFluent()->table( 'fbs_relations' )->where( 'object_type', 'OBJECT_TYPE_FOLDER_BOARD' )->where( 'object_id', $folder_id )->where( 'foreign_id', $board_id )->delete();
		return array( 'success' => true, 'folder_id' => $folder_id, 'board_id' => $board_id );
	},
) );

// =========================================================================
// §4.18.1 — create-board-invitation (idempotent:false)
// =========================================================================
$reg->write( 'fluent-boards/create-board-invitation', array(
	'label'       => 'Create Board Invitation (Pro)',
	'description' => 'Create an invitation to join a board. The invitee_email is recorded; vendor sends the email separately when its delivery hooks fire.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'invitee_email', 'role' ),
		'properties' => array(
			'board_id'      => array( 'type' => 'integer' ),
			'invitee_email' => array( 'type' => 'string', 'format' => 'email' ),
			'role'          => array( 'type' => 'string', 'enum' => array( 'admin', 'manager', 'member', 'viewer' ) ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'invitation_id' => array( 'type' => 'integer' ),
		'board_id'      => array( 'type' => 'integer' ),
		'invitee_email' => array( 'type' => 'string' ),
	) ),
	'annotations' => array( 'idempotent' => false ),
	'callback'    => function( $input ) {
		$board_id = (int) $input['board_id'];
		$email    = sanitize_email( $input['invitee_email'] ?? '' );
		$role     = sanitize_key( $input['role'] ?? '' );
		if ( ! $email || ! is_email( $email ) ) {
			return fluent_abilities_error( 'ability_invalid_input', 'invitee_email must be a valid email address.' );
		}
		if ( ! wpFluent()->table( 'fbs_boards' )->where( 'id', $board_id )->first() ) {
			return fluent_abilities_error( 'not_found', 'Board not found.' );
		}
		$now    = current_time( 'mysql' );
		$new_id = wpFluent()->table( 'fbs_metas' )->insert( array(
			'object_id'   => $board_id,
			'object_type' => 'board_invitation',
			'key'         => 'invitation',
			'value'       => maybe_serialize( array(
				'invitee_email' => $email,
				'role'          => $role,
				'invited_by'    => (int) get_current_user_id(),
				'token'         => wp_generate_uuid4(),
			) ),
			'created_at'  => $now,
			'updated_at'  => $now,
		) );
		return array( 'success' => true, 'invitation_id' => (int) $new_id, 'board_id' => $board_id, 'invitee_email' => $email );
	},
) );

// =========================================================================
// §4.18.2 — list-board-invitations
// =========================================================================
$reg->read( 'fluent-boards/list-board-invitations', array(
	'label'         => 'List Board Invitations (Pro)',
	'description'   => 'List pending invitations for a board.',
	'category'      => 'fluent-boards',
	'input_schema'  => array(
		'type'       => 'object',
		'required'   => array( 'board_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_collection_output( 'invitations', array(
		'invitation_id' => array( 'type' => 'integer' ),
		'invitee_email' => array( 'type' => array( 'string', 'null' ) ),
		'role'          => array( 'type' => array( 'string', 'null' ) ),
		'invited_by'    => array( 'type' => array( 'integer', 'null' ) ),
		'created_at'    => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$board_id = (int) $input['board_id'];
		$rows     = wpFluent()->table( 'fbs_metas' )->where( 'object_type', 'board_invitation' )->where( 'object_id', $board_id )->orderBy( 'id', 'DESC' )->get();
		$items    = array();
		foreach ( $rows as $r ) {
			$meta    = maybe_unserialize( $r->value ?? '' );
			$meta    = is_array( $meta ) ? $meta : array();
			$items[] = array(
				'invitation_id' => (int) $r->id,
				'invitee_email' => $meta['invitee_email'] ?? null,
				'role'          => $meta['role'] ?? null,
				'invited_by'    => isset( $meta['invited_by'] ) ? (int) $meta['invited_by'] : null,
				'created_at'    => $r->created_at ?? null,
			);
		}
		return array( 'invitations' => $items, 'total' => count( $items ) );
	},
) );

// =========================================================================
// §4.18.3 — delete-board-invitation
// =========================================================================
$reg->delete( 'fluent-boards/delete-board-invitation', array(
	'label'       => 'Delete Board Invitation (Pro)',
	'description' => 'Revoke a board invitation by its invitation_id (fbs_metas row id).',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'invitation_id' ),
		'properties' => array(
			'invitation_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'invitation_id' => array( 'type' => 'integer' ) ) ),
	'callback'     => function( $input ) {
		$inv_id = (int) $input['invitation_id'];
		wpFluent()->table( 'fbs_metas' )->where( 'id', $inv_id )->where( 'object_type', 'board_invitation' )->delete();
		return array( 'success' => true, 'invitation_id' => $inv_id );
	},
) );
