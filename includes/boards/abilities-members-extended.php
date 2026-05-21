<?php
/**
 * Fluent Boards — Members & Roles Extended (Research §4.6 + §4.7 + §4.8)
 *
 * §4.6 Activities: member view            — 1 ability  (free)
 * §4.7 Members & Roles                    — 12 abilities (4 pro / 8 free)
 * §4.8 Org-Wide Managers                  — 6 abilities (pro)
 * Total: 19 abilities.
 *
 * Roles are stored as flags in fbs_relations.settings (board_user): is_admin,
 * is_viewer_only. Pro adds is_manager via Pro/BoardUserController.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

// Helper closure: update a board_user relation's settings flags. Reused below.
$boards_member_set_flags = function( $board_id, $user_id, array $flags ) {
	$rel = wpFluent()->table( 'fbs_relations' )
		->where( 'object_type', 'board_user' )
		->where( 'object_id', $board_id )
		->where( 'foreign_id', $user_id )
		->first();
	if ( ! $rel ) {
		return fluent_abilities_error( 'not_found', 'User is not a member of this board.' );
	}
	$settings = maybe_unserialize( $rel->settings ?? '' );
	$settings = is_array( $settings ) ? $settings : array();
	foreach ( $flags as $k => $v ) {
		if ( null === $v ) {
			unset( $settings[ $k ] );
		} else {
			$settings[ $k ] = $v;
		}
	}
	wpFluent()->table( 'fbs_relations' )->where( 'id', $rel->id )->update( array(
		'settings'   => maybe_serialize( $settings ),
		'updated_at' => current_time( 'mysql' ),
	) );
	return $settings;
};

// =========================================================================
// §4.6.1 — list-member-activities
// =========================================================================
$reg->read( 'fluent-boards/list-member-activities', array(
	'label'       => 'List Member Activities',
	'description' => 'List activities authored by a user across all tasks/boards they have access to.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'user_id' ),
		'properties' => array_merge( array(
			'user_id' => array( 'type' => 'integer' ),
		), fluent_abilities_pagination_schema() ),
	),
	'output_schema' => fluent_abilities_schema_list_output( 'activities', array(
		'id'         => array( 'type' => 'integer' ),
		'action'     => array( 'type' => array( 'string', 'null' ) ),
		'object_id'  => array( 'type' => array( 'integer', 'null' ) ),
		'object_type'=> array( 'type' => array( 'string', 'null' ) ),
		'created_at' => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$user_id    = (int) $input['user_id'];
		$pagination = fluent_abilities_pagination( $input, 40 );
		$query      = wpFluent()->table( 'fbs_activities' )->where( 'created_by', $user_id )->orderBy( 'id', 'DESC' );
		$total      = (int) $query->count();
		$rows       = $query->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();
		$items      = array();
		foreach ( $rows as $a ) {
			$items[] = array(
				'id'         => (int) $a->id,
				'action'     => $a->action ?? null,
				'object_id'  => $a->object_id ? (int) $a->object_id : null,
				'object_type'=> $a->object_type ?? null,
				'created_at' => $a->created_at ?? null,
			);
		}
		return array( 'activities' => $items, 'total' => $total, 'page' => $pagination['page'], 'per_page' => $pagination['per_page'] );
	},
) );

// =========================================================================
// §4.7.1 — make-board-manager (pro)
// =========================================================================
$reg->write( 'fluent-boards/make-board-manager', array(
	'label'       => 'Make Board Manager (Pro)',
	'description' => 'Promote a board member to manager role (sets is_manager flag in fbs_relations.settings). Pro role.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'user_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
			'user_id'  => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'board_id' => array( 'type' => 'integer' ),
		'user_id'  => array( 'type' => 'integer' ),
	) ),
	'callback' => function( $input ) use ( $boards_member_set_flags ) {
		$result = $boards_member_set_flags( (int) $input['board_id'], (int) $input['user_id'], array( 'is_manager' => true, 'is_admin' => false, 'is_viewer_only' => false ) );
		if ( is_wp_error( $result ) ) { return $result; }
		return array( 'success' => true, 'board_id' => (int) $input['board_id'], 'user_id' => (int) $input['user_id'] );
	},
) );

// =========================================================================
// §4.7.2 — remove-board-manager (pro)
// =========================================================================
$reg->write( 'fluent-boards/remove-board-manager', array(
	'label'       => 'Remove Board Manager (Pro)',
	'description' => 'Clear the is_manager flag on a board member, demoting them to plain member.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'user_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
			'user_id'  => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'board_id' => array( 'type' => 'integer' ),
		'user_id'  => array( 'type' => 'integer' ),
	) ),
	'callback' => function( $input ) use ( $boards_member_set_flags ) {
		$result = $boards_member_set_flags( (int) $input['board_id'], (int) $input['user_id'], array( 'is_manager' => null ) );
		if ( is_wp_error( $result ) ) { return $result; }
		return array( 'success' => true, 'board_id' => (int) $input['board_id'], 'user_id' => (int) $input['user_id'] );
	},
) );

// =========================================================================
// §4.7.3 — make-board-viewer (pro)
// =========================================================================
$reg->write( 'fluent-boards/make-board-viewer', array(
	'label'       => 'Make Board Viewer (Pro)',
	'description' => 'Mark a member as viewer-only (read-access; no edits). Sets is_viewer_only=true.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'user_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
			'user_id'  => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'board_id' => array( 'type' => 'integer' ),
		'user_id'  => array( 'type' => 'integer' ),
	) ),
	'callback' => function( $input ) use ( $boards_member_set_flags ) {
		$result = $boards_member_set_flags( (int) $input['board_id'], (int) $input['user_id'], array( 'is_viewer_only' => true, 'is_admin' => false, 'is_manager' => null ) );
		if ( is_wp_error( $result ) ) { return $result; }
		return array( 'success' => true, 'board_id' => (int) $input['board_id'], 'user_id' => (int) $input['user_id'] );
	},
) );

// =========================================================================
// §4.7.4 — make-board-member (pro)
// =========================================================================
$reg->write( 'fluent-boards/make-board-member', array(
	'label'       => 'Make Board Member (Pro)',
	'description' => 'Demote a manager or viewer back to plain member (clears is_admin, is_manager, is_viewer_only).',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'user_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
			'user_id'  => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'board_id' => array( 'type' => 'integer' ),
		'user_id'  => array( 'type' => 'integer' ),
	) ),
	'callback' => function( $input ) use ( $boards_member_set_flags ) {
		$result = $boards_member_set_flags( (int) $input['board_id'], (int) $input['user_id'], array( 'is_admin' => false, 'is_manager' => null, 'is_viewer_only' => false ) );
		if ( is_wp_error( $result ) ) { return $result; }
		return array( 'success' => true, 'board_id' => (int) $input['board_id'], 'user_id' => (int) $input['user_id'] );
	},
) );

// =========================================================================
// §4.7.5 — bulk-add-board-members (free)
// =========================================================================
$reg->write( 'fluent-boards/bulk-add-board-members', array(
	'label'       => 'Bulk Add Board Members',
	'description' => 'Add many users to a board in one call with a uniform role (admin/manager/member/viewer).',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id', 'user_ids', 'role' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
			'user_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			'role'     => array( 'type' => 'string', 'enum' => array( 'admin', 'manager', 'member', 'viewer' ) ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'board_id' => array( 'type' => 'integer' ),
		'added'    => array( 'type' => 'integer' ),
	) ),
	'callback' => function( $input ) {
		$board_id = (int) $input['board_id'];
		$ids      = array_map( 'intval', (array) ( $input['user_ids'] ?? array() ) );
		$role     = sanitize_text_field( $input['role'] ?? '' );
		if ( empty( $ids ) ) {
			return fluent_abilities_error( 'ability_invalid_input', 'user_ids must be a non-empty array.' );
		}
		if ( ! wpFluent()->table( 'fbs_boards' )->where( 'id', $board_id )->first() ) {
			return fluent_abilities_error( 'not_found', 'Board not found.' );
		}
		$flags = array(
			'admin'   => array( 'is_admin' => true ),
			'manager' => array( 'is_manager' => true ),
			'member'  => array(),
			'viewer'  => array( 'is_viewer_only' => true ),
		);
		$now   = current_time( 'mysql' );
		$added = 0;
		foreach ( $ids as $uid ) {
			$exists = wpFluent()->table( 'fbs_relations' )->where( 'object_type', 'board_user' )->where( 'object_id', $board_id )->where( 'foreign_id', $uid )->first();
			if ( $exists ) { continue; }
			wpFluent()->table( 'fbs_relations' )->insert( array(
				'object_id'   => $board_id,
				'object_type' => 'board_user',
				'foreign_id'  => $uid,
				'settings'    => maybe_serialize( $flags[ $role ] ?? array() ),
				'preferences' => maybe_serialize( array( 'board_email_task_assign', 'board_email_comment', 'board_email_task_completed', 'board_email_due_date' ) ),
				'created_at'  => $now,
				'updated_at'  => $now,
			) );
			$added++;
		}
		return array( 'success' => true, 'board_id' => $board_id, 'added' => $added );
	},
) );

// =========================================================================
// §4.7.6 — list-board-assignees (free)
// =========================================================================
$reg->read( 'fluent-boards/list-board-assignees', array(
	'label'       => 'List Board Assignees',
	'description' => 'List users assigned to at least one task on the board.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_collection_output( 'assignees', array(
		'user_id'      => array( 'type' => 'integer' ),
		'display_name' => array( 'type' => array( 'string', 'null' ) ),
		'task_count'   => array( 'type' => 'integer' ),
	) ),
	'callback' => function( $input ) {
		$board_id = (int) $input['board_id'];
		$task_ids = wpFluent()->table( 'fbs_tasks' )->where( 'board_id', $board_id )->select( 'id' )->get();
		// V5: coerce vendor Collection to array before array_map (P-A pattern).
		$tids     = array_map( function( $t ) { return (int) $t->id; }, fluent_abilities_to_array( $task_ids ) );
		if ( empty( $tids ) ) {
			return array( 'assignees' => array(), 'total' => 0 );
		}
		$rels  = wpFluent()->table( 'fbs_relations' )->whereIn( 'object_id', $tids )->where( 'object_type', 'task_assignee' )->get();
		$count = array();
		foreach ( $rels as $r ) {
			$uid             = (int) $r->foreign_id;
			$count[ $uid ]   = ( $count[ $uid ] ?? 0 ) + 1;
		}
		$items = array();
		foreach ( $count as $uid => $c ) {
			$u = get_userdata( $uid );
			$items[] = array(
				'user_id'      => $uid,
				'display_name' => $u ? $u->display_name : null,
				'task_count'   => (int) $c,
			);
		}
		return array( 'assignees' => $items, 'total' => count( $items ) );
	},
) );

// =========================================================================
// §4.7.7 — list-board-users (free)
// =========================================================================
$reg->read( 'fluent-boards/list-board-users', array(
	'label'       => 'List Board Users',
	'description' => 'List all members of a board with roles + email notification preferences (richer than list-board-members which only returns role flags).',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'board_id' ),
		'properties' => array(
			'board_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_collection_output( 'users', array(
		'user_id'           => array( 'type' => 'integer' ),
		'display_name'      => array( 'type' => array( 'string', 'null' ) ),
		'email'             => array( 'type' => array( 'string', 'null' ) ),
		'is_admin'          => array( 'type' => 'boolean' ),
		'is_manager'        => array( 'type' => 'boolean' ),
		'is_viewer_only'    => array( 'type' => 'boolean' ),
		'email_preferences' => array( 'type' => array( 'array', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$board_id = (int) $input['board_id'];
		$rels     = wpFluent()->table( 'fbs_relations' )->where( 'object_id', $board_id )->where( 'object_type', 'board_user' )->get();
		$users    = array();
		foreach ( $rels as $r ) {
			$uid      = (int) $r->foreign_id;
			$u        = get_userdata( $uid );
			$settings = maybe_unserialize( $r->settings ?? '' );
			$prefs    = maybe_unserialize( $r->preferences ?? '' );
			$users[]  = array(
				'user_id'           => $uid,
				'display_name'      => $u ? $u->display_name : null,
				'email'             => $u ? $u->user_email : null,
				'is_admin'          => is_array( $settings ) && ! empty( $settings['is_admin'] ),
				'is_manager'        => is_array( $settings ) && ! empty( $settings['is_manager'] ),
				'is_viewer_only'    => is_array( $settings ) && ! empty( $settings['is_viewer_only'] ),
				'email_preferences' => is_array( $prefs ) ? $prefs : null,
			);
		}
		return array( 'users' => $users, 'total' => count( $users ) );
	},
) );

// =========================================================================
// §4.7.8 — get-member-info (free)
// =========================================================================
$reg->read( 'fluent-boards/get-member-info', array(
	'label'         => 'Get Member Info',
	'description'   => 'Get board-user profile for a WordPress user (display name, avatar, joined date).',
	'category'      => 'fluent-boards',
	'input_schema'  => array(
		'type'       => 'object',
		'required'   => array( 'user_id' ),
		'properties' => array(
			'user_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_item_output( array(
		'user_id'      => array( 'type' => 'integer' ),
		'display_name' => array( 'type' => array( 'string', 'null' ) ),
		'email'        => array( 'type' => array( 'string', 'null' ) ),
		'avatar_url'   => array( 'type' => array( 'string', 'null' ) ),
		'board_count'  => array( 'type' => 'integer' ),
	) ),
	'callback' => function( $input ) {
		$user_id = (int) $input['user_id'];
		$u       = get_userdata( $user_id );
		if ( ! $u ) {
			return fluent_abilities_error( 'not_found', 'User not found.' );
		}
		$board_count = (int) wpFluent()->table( 'fbs_relations' )->where( 'object_type', 'board_user' )->where( 'foreign_id', $user_id )->count();
		return array(
			'user_id'      => $user_id,
			'display_name' => $u->display_name,
			'email'        => $u->user_email,
			'avatar_url'   => get_avatar_url( $user_id ),
			'board_count'  => $board_count,
		);
	},
) );

// =========================================================================
// §4.7.9 — list-member-boards (free)
// =========================================================================
$reg->read( 'fluent-boards/list-member-boards', array(
	'label'         => 'List Member Boards',
	'description'   => 'List boards a given user is a member of.',
	'category'      => 'fluent-boards',
	'input_schema'  => array(
		'type'       => 'object',
		'required'   => array( 'user_id' ),
		'properties' => array(
			'user_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_collection_output( 'boards', array(
		'id'    => array( 'type' => 'integer' ),
		'title' => array( 'type' => array( 'string', 'null' ) ),
		'type'  => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$user_id = (int) $input['user_id'];
		$rels    = wpFluent()->table( 'fbs_relations' )->where( 'object_type', 'board_user' )->where( 'foreign_id', $user_id )->get();
		// V5: coerce vendor Collection to array before array_map (P-A pattern).
		$ids     = array_map( function( $r ) { return (int) $r->object_id; }, fluent_abilities_to_array( $rels ) );
		if ( empty( $ids ) ) {
			return array( 'boards' => array(), 'total' => 0 );
		}
		$rows  = wpFluent()->table( 'fbs_boards' )->whereIn( 'id', $ids )->get();
		$items = array();
		foreach ( $rows as $b ) {
			$items[] = array( 'id' => (int) $b->id, 'title' => $b->title ?? '', 'type' => $b->type ?? null );
		}
		return array( 'boards' => $items, 'total' => count( $items ) );
	},
) );

// =========================================================================
// §4.7.10 — list-member-tasks (free)
// =========================================================================
$reg->read( 'fluent-boards/list-member-tasks', array(
	'label'       => 'List Member Tasks',
	'description' => 'List tasks assigned to a user, with optional status filter.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'user_id' ),
		'properties' => array_merge( array(
			'user_id' => array( 'type' => 'integer' ),
			'status'  => array( 'type' => 'string', 'enum' => array( 'open', 'closed', 'won', 'lost' ) ),
		), fluent_abilities_pagination_schema() ),
	),
	'output_schema' => fluent_abilities_schema_list_output( 'tasks', array(
		'id'       => array( 'type' => 'integer' ),
		'board_id' => array( 'type' => 'integer' ),
		'title'    => array( 'type' => array( 'string', 'null' ) ),
		'status'   => array( 'type' => array( 'string', 'null' ) ),
		'due_at'   => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$user_id    = (int) $input['user_id'];
		$pagination = fluent_abilities_pagination( $input, 25 );
		$rels       = wpFluent()->table( 'fbs_relations' )->where( 'object_type', 'task_assignee' )->where( 'foreign_id', $user_id )->select( 'object_id' )->get();
		// V5: coerce vendor Collection to array before array_map (P-A pattern).
		$task_ids   = array_map( function( $r ) { return (int) $r->object_id; }, fluent_abilities_to_array( $rels ) );
		if ( empty( $task_ids ) ) {
			return array( 'tasks' => array(), 'total' => 0, 'page' => $pagination['page'], 'per_page' => $pagination['per_page'] );
		}
		$query = wpFluent()->table( 'fbs_tasks' )->whereIn( 'id', $task_ids )->whereNull( 'archived_at' )->orderBy( 'updated_at', 'DESC' );
		if ( ! empty( $input['status'] ) ) {
			$query->where( 'status', sanitize_text_field( $input['status'] ) );
		}
		$total = (int) $query->count();
		$rows  = $query->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();
		$items = array();
		foreach ( $rows as $t ) {
			$items[] = array(
				'id'       => (int) $t->id,
				'board_id' => (int) $t->board_id,
				'title'    => $t->title ?? '',
				'status'   => $t->status ?? null,
				'due_at'   => $t->due_at ?? null,
			);
		}
		return array( 'tasks' => $items, 'total' => $total, 'page' => $pagination['page'], 'per_page' => $pagination['per_page'] );
	},
) );

// =========================================================================
// §4.7.11 — list-member-associated-users (free)
// =========================================================================
$reg->read( 'fluent-boards/list-member-associated-users', array(
	'label'       => 'List Member Associated Users',
	'description' => 'List users who collaborate with the given user on shared boards (board co-members).',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'user_id' ),
		'properties' => array(
			'user_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_collection_output( 'users', array(
		'user_id'        => array( 'type' => 'integer' ),
		'display_name'   => array( 'type' => array( 'string', 'null' ) ),
		'shared_boards'  => array( 'type' => 'integer' ),
	) ),
	'callback' => function( $input ) {
		$user_id = (int) $input['user_id'];
		$rels    = wpFluent()->table( 'fbs_relations' )->where( 'object_type', 'board_user' )->where( 'foreign_id', $user_id )->select( 'object_id' )->get();
		// V5: coerce vendor Collection to array before array_map (P-A pattern).
		$boards  = array_map( function( $r ) { return (int) $r->object_id; }, fluent_abilities_to_array( $rels ) );
		if ( empty( $boards ) ) {
			return array( 'users' => array(), 'total' => 0 );
		}
		$co       = wpFluent()->table( 'fbs_relations' )->whereIn( 'object_id', $boards )->where( 'object_type', 'board_user' )->where( 'foreign_id', '!=', $user_id )->get();
		$counts   = array();
		foreach ( $co as $r ) {
			$counts[ (int) $r->foreign_id ] = ( $counts[ (int) $r->foreign_id ] ?? 0 ) + 1;
		}
		$items = array();
		foreach ( $counts as $uid => $c ) {
			$u       = get_userdata( $uid );
			$items[] = array( 'user_id' => $uid, 'display_name' => $u ? $u->display_name : null, 'shared_boards' => (int) $c );
		}
		return array( 'users' => $items, 'total' => count( $items ) );
	},
) );

// =========================================================================
// §4.7.12 — list-top-tasks-for-boards (free)
// =========================================================================

// =========================================================================
// §4.8 — Org-Wide Managers (6 abilities; pro). Stored as fbs_metas with
//        object_type='org_manager', value=user_id.
// =========================================================================

$reg->read( 'fluent-boards/get-org-managers', array(
	'label'         => 'Get Org-Wide Managers (Pro)',
	'description'   => 'List organization-wide managers (users with elevated cross-board management privileges).',
	'category'      => 'fluent-boards',
	'output_schema' => fluent_abilities_schema_collection_output( 'managers', array(
		'user_id'      => array( 'type' => 'integer' ),
		'display_name' => array( 'type' => array( 'string', 'null' ) ),
		'email'        => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function() {
		$rows  = wpFluent()->table( 'fbs_metas' )->where( 'object_type', 'org_manager' )->get();
		$items = array();
		foreach ( $rows as $r ) {
			$uid = (int) ( $r->value ?? 0 );
			if ( ! $uid ) { continue; }
			$u   = get_userdata( $uid );
			$items[] = array(
				'user_id'      => $uid,
				'display_name' => $u ? $u->display_name : null,
				'email'        => $u ? $u->user_email : null,
			);
		}
		return array( 'managers' => $items, 'total' => count( $items ) );
	},
) );

$reg->write( 'fluent-boards/add-org-manager', array(
	'label'       => 'Add Org-Wide Manager (Pro)',
	'description' => 'Promote a user to organization-wide manager. Idempotent.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'user_id' ),
		'properties' => array(
			'user_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'user_id' => array( 'type' => 'integer' ) ) ),
	'annotations'  => array( 'idempotent' => true ),
	'callback'     => function( $input ) {
		$user_id = (int) $input['user_id'];
		if ( ! get_userdata( $user_id ) ) {
			return fluent_abilities_error( 'not_found', 'User not found.' );
		}
		$exists = wpFluent()->table( 'fbs_metas' )->where( 'object_type', 'org_manager' )->where( 'value', (string) $user_id )->first();
		if ( ! $exists ) {
			$now = current_time( 'mysql' );
			wpFluent()->table( 'fbs_metas' )->insert( array(
				'object_id'   => 0,
				'object_type' => 'org_manager',
				'key'         => 'user_id',
				'value'       => (string) $user_id,
				'created_at'  => $now,
				'updated_at'  => $now,
			) );
		}
		return array( 'success' => true, 'user_id' => $user_id );
	},
) );

$reg->write( 'fluent-boards/remove-org-manager', array(
	'label'       => 'Remove Org-Wide Manager (Pro)',
	'description' => 'Demote a user from organization-wide manager.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'user_id' ),
		'properties' => array(
			'user_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'user_id' => array( 'type' => 'integer' ) ) ),
	'callback'     => function( $input ) {
		$user_id = (int) $input['user_id'];
		wpFluent()->table( 'fbs_metas' )->where( 'object_type', 'org_manager' )->where( 'value', (string) $user_id )->delete();
		return array( 'success' => true, 'user_id' => $user_id );
	},
) );

$reg->read( 'fluent-boards/list-manager-boards', array(
	'label'         => 'List Manager Boards (Pro)',
	'description'   => 'List boards an org-wide manager has access to (effectively all boards if the user is in fbs_metas.org_manager).',
	'category'      => 'fluent-boards',
	'input_schema'  => array(
		'type'       => 'object',
		'required'   => array( 'user_id' ),
		'properties' => array(
			'user_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_collection_output( 'boards', array(
		'id'    => array( 'type' => 'integer' ),
		'title' => array( 'type' => array( 'string', 'null' ) ),
		'type'  => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$user_id  = (int) $input['user_id'];
		$is_mgr   = (bool) wpFluent()->table( 'fbs_metas' )->where( 'object_type', 'org_manager' )->where( 'value', (string) $user_id )->first();
		if ( ! $is_mgr ) {
			return fluent_abilities_error( 'forbidden', 'User is not an org-wide manager.' );
		}
		$rows  = wpFluent()->table( 'fbs_boards' )->whereNull( 'archived_at' )->get();
		$items = array();
		foreach ( $rows as $b ) {
			$items[] = array( 'id' => (int) $b->id, 'title' => $b->title ?? '', 'type' => $b->type ?? null );
		}
		return array( 'boards' => $items, 'total' => count( $items ) );
	},
) );

$reg->read( 'fluent-boards/list-manager-tasks', array(
	'label'         => 'List Manager Tasks (Pro)',
	'description'   => 'List tasks visible to an org-wide manager (all open tasks if user is in fbs_metas.org_manager).',
	'category'      => 'fluent-boards',
	'input_schema'  => array(
		'type'       => 'object',
		'required'   => array( 'user_id' ),
		'properties' => array_merge( array(
			'user_id' => array( 'type' => 'integer' ),
		), fluent_abilities_pagination_schema() ),
	),
	'output_schema' => fluent_abilities_schema_list_output( 'tasks', array(
		'id'       => array( 'type' => 'integer' ),
		'board_id' => array( 'type' => 'integer' ),
		'title'    => array( 'type' => array( 'string', 'null' ) ),
		'status'   => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$user_id    = (int) $input['user_id'];
		$pagination = fluent_abilities_pagination( $input, 25 );
		$is_mgr     = (bool) wpFluent()->table( 'fbs_metas' )->where( 'object_type', 'org_manager' )->where( 'value', (string) $user_id )->first();
		if ( ! $is_mgr ) {
			return fluent_abilities_error( 'forbidden', 'User is not an org-wide manager.' );
		}
		$query = wpFluent()->table( 'fbs_tasks' )->whereNull( 'archived_at' )->whereNull( 'parent_id' )->orderBy( 'updated_at', 'DESC' );
		$total = (int) $query->count();
		$rows  = $query->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();
		$items = array();
		foreach ( $rows as $t ) {
			$items[] = array(
				'id'       => (int) $t->id,
				'board_id' => (int) $t->board_id,
				'title'    => $t->title ?? '',
				'status'   => $t->status ?? null,
			);
		}
		return array( 'tasks' => $items, 'total' => $total, 'page' => $pagination['page'], 'per_page' => $pagination['per_page'] );
	},
) );

$reg->read( 'fluent-boards/list-manager-team-users', array(
	'label'         => 'List Manager Team Users (Pro)',
	'description'   => 'List users in the org-wide manager\'s team (currently: all users who share at least one board with the manager).',
	'category'      => 'fluent-boards',
	'input_schema'  => array(
		'type'       => 'object',
		'required'   => array( 'user_id' ),
		'properties' => array(
			'user_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_collection_output( 'users', array(
		'user_id'      => array( 'type' => 'integer' ),
		'display_name' => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$user_id = (int) $input['user_id'];
		$is_mgr  = (bool) wpFluent()->table( 'fbs_metas' )->where( 'object_type', 'org_manager' )->where( 'value', (string) $user_id )->first();
		if ( ! $is_mgr ) {
			return fluent_abilities_error( 'forbidden', 'User is not an org-wide manager.' );
		}
		$mgr_boards = wpFluent()->table( 'fbs_relations' )->where( 'object_type', 'board_user' )->where( 'foreign_id', $user_id )->select( 'object_id' )->get();
		// V5: coerce vendor Collection to array before array_map (P-A pattern).
		$board_ids  = array_map( function( $r ) { return (int) $r->object_id; }, fluent_abilities_to_array( $mgr_boards ) );
		if ( empty( $board_ids ) ) {
			$rows  = wpFluent()->table( 'fbs_relations' )->where( 'object_type', 'board_user' )->where( 'foreign_id', '!=', $user_id )->select( 'foreign_id' )->get();
		} else {
			$rows  = wpFluent()->table( 'fbs_relations' )->whereIn( 'object_id', $board_ids )->where( 'object_type', 'board_user' )->where( 'foreign_id', '!=', $user_id )->get();
		}
		$seen  = array();
		$items = array();
		foreach ( $rows as $r ) {
			$uid = (int) $r->foreign_id;
			if ( isset( $seen[ $uid ] ) ) { continue; }
			$seen[ $uid ] = true;
			$u            = get_userdata( $uid );
			$items[]      = array( 'user_id' => $uid, 'display_name' => $u ? $u->display_name : null );
		}
		return array( 'users' => $items, 'total' => count( $items ) );
	},
) );
