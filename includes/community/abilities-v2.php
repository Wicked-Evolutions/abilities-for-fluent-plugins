<?php
/**
 * Fluent Community Abilities — v2.0.0 (Phase B additions).
 *
 * Adds 53 community abilities across 14 clusters (§4.1–§4.10, §4.12–§4.15)
 * on top of the existing v1.1.3 community abilities in community/abilities.php
 * (which remain frozen per Stable Contracts).
 *
 * Cluster §4.11 (messaging surface expansion, 8 abilities) lives in
 * includes/messaging/abilities-v2.php — out of scope for this file.
 *
 * Source: ABILITY REGISTRAR RESEARCH — FluentCommunity 2026-05-12 v2.0.
 * Clusters: 4.1 Space membership & roles (6), 4.2 Community-level member CRUD (3),
 * 4.3 Space-group CRUD (3), 4.4 Reactions (3), 4.5 Notification mutations (4),
 * 4.6 Settings (12), 4.7 XProfile custom fields (2), 4.8 Course enrollment (4),
 * 4.9 Following predicate (1), 4.10 Cross-plugin event emission (1),
 * 4.12 Topics/Terms (7), 4.13 Surveys/polls (3), 4.14 Quiz Pro (3),
 * 4.15 Mention search (1). Total: 53.
 *
 * KD-3 (see docs/V1.1.3-KNOWN-DEFECTS.md): Course/Lesson code in this file uses
 * the canonical \FluentCommunity\Modules\Course\Model\{Course,CourseLesson}
 * namespace (NOT the legacy \FluentCommunity\App\Models\CourseLesson) and the
 * fcom_posts.type='course_lesson' (NOT 'lesson') value.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register all community v2 abilities. Public for unit tests.
 */
function fluent_abilities_register_community_v2() {
	$reg = new Fluent_Abilities_Registrar( 'community' );

	// =========================================================================
	// ===== Cluster 4.1: Space membership & roles (6) =========================
	// =========================================================================

	// ── 4.1.1 add-space-member ──────────────────────────────────────────────
	$reg->write( 'fluent-community/add-space-member', array(
		'label'       => 'Add Space Member',
		'description' => 'Attach a user to a space with a role (admin/moderator/member/student) and status (active/pending/blocked, default active). Course spaces accept role=student.',
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'space_id', 'user_id' ),
			'properties' => array(
				'space_id' => array( 'type' => 'integer', 'description' => 'Space ID' ),
				'user_id'  => array( 'type' => 'integer', 'description' => 'User ID' ),
				'role'     => array( 'type' => 'string', 'description' => 'admin|moderator|member|student' ),
				'status'   => array( 'type' => 'string', 'description' => 'active|pending|blocked (default active)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'space_id' => array( 'type' => 'integer' ),
			'user_id'  => array( 'type' => 'integer' ),
			'role'     => array( 'type' => 'string' ),
			'status'   => array( 'type' => 'string' ),
		) ),
		'annotations' => array( 'idempotent' => true ),
		'callback' => function( $input ) {
			$space_id = (int) ( $input['space_id'] ?? 0 );
			$user_id  = (int) ( $input['user_id'] ?? 0 );
			$role     = isset( $input['role'] ) ? sanitize_text_field( $input['role'] ) : 'member';
			$status   = isset( $input['status'] ) ? sanitize_text_field( $input['status'] ) : 'active';

			$valid_roles    = array( 'admin', 'moderator', 'member', 'student' );
			$valid_statuses = array( 'active', 'pending', 'blocked' );
			if ( ! in_array( $role, $valid_roles, true ) ) {
				return fluent_abilities_error( 'invalid_role', 'Invalid role; expected one of: ' . implode( ', ', $valid_roles ) );
			}
			if ( ! in_array( $status, $valid_statuses, true ) ) {
				return fluent_abilities_error( 'invalid_status', 'Invalid status; expected one of: ' . implode( ', ', $valid_statuses ) );
			}

			$space = \FluentCommunity\App\Models\Space::find( $space_id );
			if ( ! $space ) {
				return fluent_abilities_error( 'not_found', 'Space not found' );
			}

			$space->members()->attach( $user_id, array( 'role' => $role, 'status' => $status ) );

			return array(
				'success'  => true,
				'space_id' => $space_id,
				'user_id'  => $user_id,
				'role'     => $role,
				'status'   => $status,
			);
		},
	) );

	// ── 4.1.2 remove-space-member ───────────────────────────────────────────
	$reg->delete( 'fluent-community/remove-space-member', array(
		'label'       => 'Remove Space Member',
		'description' => 'Detach a user from a space.',
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'space_id', 'user_id' ),
			'properties' => array(
				'space_id' => array( 'type' => 'integer', 'description' => 'Space ID' ),
				'user_id'  => array( 'type' => 'integer', 'description' => 'User ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'space_id' => array( 'type' => 'integer' ),
			'user_id'  => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => true ),
		'callback' => function( $input ) {
			$space_id = (int) ( $input['space_id'] ?? 0 );
			$user_id  = (int) ( $input['user_id'] ?? 0 );

			$space = \FluentCommunity\App\Models\Space::find( $space_id );
			if ( ! $space ) {
				return fluent_abilities_error( 'not_found', 'Space not found' );
			}

			$space->members()->detach( $user_id );

			return array(
				'success'  => true,
				'space_id' => $space_id,
				'user_id'  => $user_id,
			);
		},
	) );

	// ── 4.1.3 update-space-member-role ──────────────────────────────────────
	$reg->write( 'fluent-community/update-space-member-role', array(
		'label'       => 'Update Space Member Role',
		'description' => 'Change the role of an existing space member (admin/moderator/member/student).',
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'space_id', 'user_id', 'role' ),
			'properties' => array(
				'space_id' => array( 'type' => 'integer', 'description' => 'Space ID' ),
				'user_id'  => array( 'type' => 'integer', 'description' => 'User ID' ),
				'role'     => array( 'type' => 'string', 'description' => 'admin|moderator|member|student' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'space_id' => array( 'type' => 'integer' ),
			'user_id'  => array( 'type' => 'integer' ),
			'role'     => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$space_id = (int) ( $input['space_id'] ?? 0 );
			$user_id  = (int) ( $input['user_id'] ?? 0 );
			$role     = isset( $input['role'] ) ? sanitize_text_field( $input['role'] ) : '';

			$valid_roles = array( 'admin', 'moderator', 'member', 'student' );
			if ( ! in_array( $role, $valid_roles, true ) ) {
				return fluent_abilities_error( 'invalid_role', 'Invalid role; expected one of: ' . implode( ', ', $valid_roles ) );
			}

			$space = \FluentCommunity\App\Models\Space::find( $space_id );
			if ( ! $space ) {
				return fluent_abilities_error( 'not_found', 'Space not found' );
			}

			$space->members()->updateExistingPivot( $user_id, array( 'role' => $role ) );

			return array(
				'success'  => true,
				'space_id' => $space_id,
				'user_id'  => $user_id,
				'role'     => $role,
			);
		},
	) );

	// ── 4.1.4 get-space-member ──────────────────────────────────────────────
	$reg->read( 'fluent-community/get-space-member', array(
		'label'       => 'Get Space Member',
		'description' => 'Return the pivot row (role, status, created_at, meta) for a single user in a space.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'space_id', 'user_id' ),
			'properties' => array(
				'space_id' => array( 'type' => 'integer', 'description' => 'Space ID' ),
				'user_id'  => array( 'type' => 'integer', 'description' => 'User ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'space_id'   => array( 'type' => 'integer' ),
			'user_id'    => array( 'type' => 'integer' ),
			'role'       => array( 'type' => array( 'string', 'null' ) ),
			'status'     => array( 'type' => array( 'string', 'null' ) ),
			'created_at' => array( 'type' => array( 'string', 'null' ) ),
			'meta'       => array( 'type' => array( 'object', 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$space_id = (int) ( $input['space_id'] ?? 0 );
			$user_id  = (int) ( $input['user_id'] ?? 0 );

			$pivot = \FluentCommunity\App\Models\SpaceUserPivot::where( 'space_id', $space_id )
				->where( 'user_id', $user_id )
				->first();
			if ( ! $pivot ) {
				return fluent_abilities_error( 'not_found', 'Pivot row not found for that space + user' );
			}

			return array(
				'space_id'   => $space_id,
				'user_id'    => $user_id,
				'role'       => isset( $pivot->role ) ? $pivot->role : null,
				'status'     => isset( $pivot->status ) ? $pivot->status : null,
				'created_at' => isset( $pivot->created_at ) ? (string) $pivot->created_at : null,
				'meta'       => isset( $pivot->meta ) ? fluent_abilities_safe_array( $pivot->meta ) : null,
			);
		},
	) );

	// ── 4.1.5 bulk-add-space-members ────────────────────────────────────────
	$reg->write( 'fluent-community/bulk-add-space-members', array(
		'label'       => 'Bulk Add Space Members',
		'description' => 'Attach multiple users to a space in a single call.',
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'space_id', 'user_ids' ),
			'properties' => array(
				'space_id' => array( 'type' => 'integer', 'description' => 'Space ID' ),
				'user_ids' => array(
					'type'        => 'array',
					'description' => 'List of user IDs to attach',
					'items'       => array( 'type' => 'integer' ),
				),
				'role'   => array( 'type' => 'string', 'description' => 'admin|moderator|member|student (default member)' ),
				'status' => array( 'type' => 'string', 'description' => 'active|pending|blocked (default active)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'space_id' => array( 'type' => 'integer' ),
			'attached' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => true ),
		'callback' => function( $input ) {
			$space_id = (int) ( $input['space_id'] ?? 0 );
			$user_ids = isset( $input['user_ids'] ) && is_array( $input['user_ids'] ) ? array_map( 'intval', $input['user_ids'] ) : array();
			$role     = isset( $input['role'] ) ? sanitize_text_field( $input['role'] ) : 'member';
			$status   = isset( $input['status'] ) ? sanitize_text_field( $input['status'] ) : 'active';

			$space = \FluentCommunity\App\Models\Space::find( $space_id );
			if ( ! $space ) {
				return fluent_abilities_error( 'not_found', 'Space not found' );
			}

			$attached = 0;
			foreach ( $user_ids as $uid ) {
				if ( $uid <= 0 ) {
					continue;
				}
				$space->members()->attach( $uid, array( 'role' => $role, 'status' => $status ) );
				$attached++;
			}

			return array(
				'success'  => true,
				'space_id' => $space_id,
				'attached' => $attached,
			);
		},
	) );

	// ── 4.1.6 bulk-remove-space-members ─────────────────────────────────────
	$reg->delete( 'fluent-community/bulk-remove-space-members', array(
		'label'       => 'Bulk Remove Space Members',
		'description' => 'Detach multiple users from a space in a single call.',
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'space_id', 'user_ids' ),
			'properties' => array(
				'space_id' => array( 'type' => 'integer', 'description' => 'Space ID' ),
				'user_ids' => array(
					'type'        => 'array',
					'description' => 'List of user IDs to detach',
					'items'       => array( 'type' => 'integer' ),
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'space_id' => array( 'type' => 'integer' ),
			'detached' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => true ),
		'callback' => function( $input ) {
			$space_id = (int) ( $input['space_id'] ?? 0 );
			$user_ids = isset( $input['user_ids'] ) && is_array( $input['user_ids'] ) ? array_map( 'intval', $input['user_ids'] ) : array();

			$space = \FluentCommunity\App\Models\Space::find( $space_id );
			if ( ! $space ) {
				return fluent_abilities_error( 'not_found', 'Space not found' );
			}

			$detached = 0;
			foreach ( $user_ids as $uid ) {
				if ( $uid <= 0 ) {
					continue;
				}
				$space->members()->detach( $uid );
				$detached++;
			}

			return array(
				'success'  => true,
				'space_id' => $space_id,
				'detached' => $detached,
			);
		},
	) );

	// =========================================================================
	// ===== Cluster 4.2: Community-level member CRUD (3) ======================
	// =========================================================================

	// ── 4.2.1 create-member ─────────────────────────────────────────────────
	$reg->write( 'fluent-community/create-member', array(
		'label'       => 'Create Community Member',
		'description' => 'Create an XProfile record for an existing WP user (community-level membership). Status enum: active|pending|blocked.',
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'user_id' ),
			'properties' => array(
				'user_id'           => array( 'type' => 'integer', 'description' => 'WordPress user ID' ),
				'display_name'      => array( 'type' => 'string', 'description' => 'Display name' ),
				'username'          => array( 'type' => 'string', 'description' => 'Unique username' ),
				'status'            => array( 'type' => 'string', 'description' => 'active|pending|blocked (default active)' ),
				'short_description' => array( 'type' => 'string', 'description' => 'Short bio' ),
				'avatar'            => array( 'type' => 'string', 'description' => 'Avatar URL' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'      => array( 'type' => 'integer' ),
			'user_id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			$user_id = (int) ( $input['user_id'] ?? 0 );
			if ( $user_id <= 0 ) {
				return fluent_abilities_error( 'invalid_user', 'user_id is required' );
			}

			$existing = \FluentCommunity\App\Models\XProfile::where( 'user_id', $user_id )->first();
			if ( $existing ) {
				return array(
					'success'  => true,
					'id'       => (int) $existing->id,
					'user_id'  => $user_id,
					'already_present' => true,
				);
			}

			$status = isset( $input['status'] ) ? sanitize_text_field( $input['status'] ) : 'active';
			$valid_statuses = array( 'active', 'pending', 'blocked' );
			if ( ! in_array( $status, $valid_statuses, true ) ) {
				return fluent_abilities_error( 'invalid_status', 'Invalid status' );
			}

			$attrs = array(
				'user_id' => $user_id,
				'status'  => $status,
			);
			if ( isset( $input['display_name'] ) ) {
				$attrs['display_name'] = sanitize_text_field( (string) $input['display_name'] );
			}
			if ( isset( $input['username'] ) ) {
				$attrs['username'] = sanitize_text_field( (string) $input['username'] );
			}
			if ( isset( $input['short_description'] ) ) {
				$attrs['short_description'] = wp_kses_post( (string) $input['short_description'] );
			}
			if ( isset( $input['avatar'] ) ) {
				$attrs['avatar'] = esc_url_raw( (string) $input['avatar'] );
			}

			$profile = \FluentCommunity\App\Models\XProfile::create( $attrs );

			return array(
				'success' => true,
				'id'      => (int) $profile->id,
				'user_id' => $user_id,
			);
		},
	) );

	// ── 4.2.2 update-member-status ──────────────────────────────────────────
	$reg->write( 'fluent-community/update-member-status', array(
		'label'       => 'Update Member Status',
		'description' => 'Set XProfile status for a user. Enum: active|pending|blocked.',
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'user_id', 'status' ),
			'properties' => array(
				'user_id' => array( 'type' => 'integer', 'description' => 'WordPress user ID' ),
				'status'  => array( 'type' => 'string', 'description' => 'active|pending|blocked' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'user_id' => array( 'type' => 'integer' ),
			'status'  => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$user_id = (int) ( $input['user_id'] ?? 0 );
			$status  = isset( $input['status'] ) ? sanitize_text_field( $input['status'] ) : '';

			$valid_statuses = array( 'active', 'pending', 'blocked' );
			if ( ! in_array( $status, $valid_statuses, true ) ) {
				return fluent_abilities_error( 'invalid_status', 'Invalid status; expected one of: ' . implode( ', ', $valid_statuses ) );
			}

			$profile = \FluentCommunity\App\Models\XProfile::where( 'user_id', $user_id )->first();
			if ( ! $profile ) {
				return fluent_abilities_error( 'not_found', 'XProfile not found for that user' );
			}

			$profile->status = $status;
			$profile->save();

			return array(
				'success' => true,
				'user_id' => $user_id,
				'status'  => $status,
			);
		},
	) );

	// ── 4.2.3 delete-member ─────────────────────────────────────────────────
	$reg->delete( 'fluent-community/delete-member', array(
		'label'       => 'Delete Community Member',
		'description' => 'Delete the XProfile record for a user (cascade: pivot rows + follow graph). Does NOT delete the underlying wp_users row.',
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'user_id' ),
			'properties' => array(
				'user_id' => array( 'type' => 'integer', 'description' => 'WordPress user ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'user_id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => true ),
		'callback' => function( $input ) {
			$user_id = (int) ( $input['user_id'] ?? 0 );

			$profile = \FluentCommunity\App\Models\XProfile::where( 'user_id', $user_id )->first();
			if ( ! $profile ) {
				return fluent_abilities_error( 'not_found', 'XProfile not found for that user' );
			}

			// Cascade pivot rows.
			\FluentCommunity\App\Models\SpaceUserPivot::where( 'user_id', $user_id )->delete();

			// Cascade follow graph (Pro only).
			if ( class_exists( '\\FluentCommunityPro\\App\\Models\\Follow' ) ) {
				\FluentCommunityPro\App\Models\Follow::where( 'follower_id', $user_id )
					->orWhere( 'following_id', $user_id )
					->delete();
			}

			$profile->delete();

			return array(
				'success' => true,
				'user_id' => $user_id,
			);
		},
	) );

	// =========================================================================
	// ===== Cluster 4.3: Space-group CRUD (3) =================================
	// =========================================================================
	// SpaceGroup is single-table inheritance on fcom_spaces.type='space_group'.
	// Spaces link to groups via fcom_spaces.parent_id (NOT group_id).

	// ── 4.3.1 create-space-group ────────────────────────────────────────────
	$reg->write( 'fluent-community/create-space-group', array(
		'label'       => 'Create Space Group',
		'description' => 'Create a new space group (parent container for spaces). type=space_group is auto-set.',
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'title' ),
			'properties' => array(
				'title'       => array( 'type' => 'string', 'description' => 'Group title' ),
				'slug'        => array( 'type' => 'string', 'description' => 'Optional slug (auto-generated if omitted)' ),
				'description' => array( 'type' => 'string', 'description' => 'Optional description' ),
				'serial'      => array( 'type' => 'integer', 'description' => 'Optional display serial/order' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'   => array( 'type' => 'integer' ),
			'slug' => array( 'type' => 'string' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			$attrs = array();
			if ( isset( $input['title'] ) ) {
				$attrs['title'] = sanitize_text_field( (string) $input['title'] );
			}
			if ( isset( $input['slug'] ) ) {
				$attrs['slug'] = sanitize_title( (string) $input['slug'] );
			}
			if ( isset( $input['description'] ) ) {
				$attrs['description'] = wp_kses_post( (string) $input['description'] );
			}
			if ( isset( $input['serial'] ) ) {
				$attrs['serial'] = (int) $input['serial'];
			}

			if ( empty( $attrs['title'] ) ) {
				return fluent_abilities_error( 'invalid_title', 'title is required' );
			}

			$group = \FluentCommunity\App\Models\SpaceGroup::create( $attrs );

			return array(
				'success' => true,
				'id'      => (int) $group->id,
				'slug'    => (string) $group->slug,
			);
		},
	) );

	// ── 4.3.2 update-space-group ────────────────────────────────────────────
	$reg->write( 'fluent-community/update-space-group', array(
		'label'       => 'Update Space Group',
		'description' => 'Update title/slug/description/serial/settings of an existing space group.',
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'          => array( 'type' => 'integer', 'description' => 'Space group ID' ),
				'title'       => array( 'type' => 'string' ),
				'slug'        => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
				'serial'      => array( 'type' => 'integer' ),
				'settings'    => array( 'type' => 'object', 'description' => 'Free-form settings bag' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$id    = (int) ( $input['id'] ?? 0 );
			$group = \FluentCommunity\App\Models\SpaceGroup::find( $id );
			if ( ! $group ) {
				return fluent_abilities_error( 'not_found', 'Space group not found' );
			}

			if ( array_key_exists( 'title', $input ) ) {
				$group->title = sanitize_text_field( (string) $input['title'] );
			}
			if ( array_key_exists( 'slug', $input ) ) {
				$group->slug = sanitize_title( (string) $input['slug'] );
			}
			if ( array_key_exists( 'description', $input ) ) {
				$group->description = wp_kses_post( (string) $input['description'] );
			}
			if ( array_key_exists( 'serial', $input ) ) {
				$group->serial = (int) $input['serial'];
			}
			if ( isset( $input['settings'] ) && is_array( $input['settings'] ) ) {
				$group->settings = $input['settings'];
			}
			$group->save();

			return array(
				'success' => true,
				'id'      => $id,
			);
		},
	) );

	// ── 4.3.3 delete-space-group ────────────────────────────────────────────
	$reg->delete( 'fluent-community/delete-space-group', array(
		'label'       => 'Delete Space Group',
		'description' => 'Delete a space group. Child spaces become orphans (parent_id set to NULL).',
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Space group ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'                => array( 'type' => 'integer' ),
			'orphaned_spaces'   => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => true ),
		'callback' => function( $input ) {
			$id    = (int) ( $input['id'] ?? 0 );
			$group = \FluentCommunity\App\Models\SpaceGroup::find( $id );
			if ( ! $group ) {
				return fluent_abilities_error( 'not_found', 'Space group not found' );
			}

			$orphaned = \FluentCommunity\App\Models\Space::where( 'parent_id', $id )
				->update( array( 'parent_id' => null ) );

			$group->delete();

			return array(
				'success'         => true,
				'id'              => $id,
				'orphaned_spaces' => (int) $orphaned,
			);
		},
	) );

	// =========================================================================
	// ===== Cluster 4.4: Reactions (3) ========================================
	// =========================================================================
	// object_type enum: feed|comment|lesson_completed. Survey vote casts use a
	// separate cluster (§4.13) — reject 'survey'-style object_types here.

	$reaction_valid_object_types = array( 'feed', 'comment', 'lesson_completed' );

	// ── 4.4.1 add-reaction ──────────────────────────────────────────────────
	$reg->write( 'fluent-community/add-reaction', array(
		'label'       => 'Add Reaction',
		'description' => 'Add a reaction (default type=like) to a feed post, comment, or lesson_completed marker.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'object_id', 'object_type' ),
			'properties' => array(
				'object_id'   => array( 'type' => 'integer', 'description' => 'Target object ID' ),
				'object_type' => array( 'type' => 'string', 'description' => 'feed|comment|lesson_completed' ),
				'type'        => array( 'type' => 'string', 'description' => 'Reaction type (default like)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'reaction_id' => array( 'type' => array( 'integer', 'null' ) ),
		) ),
		'annotations' => array( 'idempotent' => true ),
		'callback' => function( $input ) use ( $reaction_valid_object_types ) {
			$object_id   = (int) ( $input['object_id'] ?? 0 );
			$object_type = isset( $input['object_type'] ) ? sanitize_text_field( $input['object_type'] ) : '';
			$type        = isset( $input['type'] ) ? sanitize_text_field( $input['type'] ) : 'like';

			if ( ! in_array( $object_type, $reaction_valid_object_types, true ) ) {
				return fluent_abilities_error( 'invalid_object_type', 'object_type must be one of: ' . implode( ', ', $reaction_valid_object_types ) );
			}

			$user_id = get_current_user_id();
			if ( ! $user_id ) {
				return fluent_abilities_error( 'rest_forbidden', 'No authenticated user' );
			}

			$existing = \FluentCommunity\App\Models\Reaction::where( 'object_id', $object_id )
				->where( 'object_type', $object_type )
				->where( 'user_id', $user_id )
				->where( 'type', $type )
				->first();
			if ( $existing ) {
				return array(
					'success'         => true,
					'reaction_id'     => (int) $existing->id,
					'already_present' => true,
				);
			}

			$reaction = \FluentCommunity\App\Models\Reaction::create( array(
				'object_id'   => $object_id,
				'object_type' => $object_type,
				'user_id'     => $user_id,
				'type'        => $type,
			) );

			return array(
				'success'     => true,
				'reaction_id' => (int) $reaction->id,
			);
		},
	) );

	// ── 4.4.2 remove-reaction ───────────────────────────────────────────────
	$reg->delete( 'fluent-community/remove-reaction', array(
		'label'       => 'Remove Reaction',
		'description' => 'Remove the current user\'s reaction from a feed post, comment, or lesson_completed marker.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'object_id', 'object_type' ),
			'properties' => array(
				'object_id'   => array( 'type' => 'integer', 'description' => 'Target object ID' ),
				'object_type' => array( 'type' => 'string', 'description' => 'feed|comment|lesson_completed' ),
				'type'        => array( 'type' => 'string', 'description' => 'Reaction type (default like)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'removed' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => true ),
		'callback' => function( $input ) use ( $reaction_valid_object_types ) {
			$object_id   = (int) ( $input['object_id'] ?? 0 );
			$object_type = isset( $input['object_type'] ) ? sanitize_text_field( $input['object_type'] ) : '';
			$type        = isset( $input['type'] ) ? sanitize_text_field( $input['type'] ) : 'like';

			if ( ! in_array( $object_type, $reaction_valid_object_types, true ) ) {
				return fluent_abilities_error( 'invalid_object_type', 'object_type must be one of: ' . implode( ', ', $reaction_valid_object_types ) );
			}

			$user_id = get_current_user_id();
			if ( ! $user_id ) {
				return fluent_abilities_error( 'rest_forbidden', 'No authenticated user' );
			}

			$removed = \FluentCommunity\App\Models\Reaction::where( 'object_id', $object_id )
				->where( 'object_type', $object_type )
				->where( 'user_id', $user_id )
				->where( 'type', $type )
				->delete();

			return array(
				'success' => true,
				'removed' => (int) $removed,
			);
		},
	) );

	// ── 4.4.3 list-reactions ────────────────────────────────────────────────
	$reg->read( 'fluent-community/list-reactions', array(
		'label'       => 'List Reactions',
		'description' => 'List reactions on a target object (feed/comment/lesson_completed), optionally filtered by reaction type.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'object_id', 'object_type' ),
			'properties' => array(
				'object_id'   => array( 'type' => 'integer' ),
				'object_type' => array( 'type' => 'string', 'description' => 'feed|comment|lesson_completed' ),
				'type'        => array( 'type' => 'string', 'description' => 'Optional reaction type filter' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'reactions', array(
			'id'      => array( 'type' => 'integer' ),
			'user_id' => array( 'type' => 'integer' ),
			'type'    => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) use ( $reaction_valid_object_types ) {
			$object_id   = (int) ( $input['object_id'] ?? 0 );
			$object_type = isset( $input['object_type'] ) ? sanitize_text_field( $input['object_type'] ) : '';

			if ( ! in_array( $object_type, $reaction_valid_object_types, true ) ) {
				return fluent_abilities_error( 'invalid_object_type', 'object_type must be one of: ' . implode( ', ', $reaction_valid_object_types ) );
			}

			$query = \FluentCommunity\App\Models\Reaction::where( 'object_id', $object_id )
				->where( 'object_type', $object_type );

			if ( isset( $input['type'] ) ) {
				$query = $query->where( 'type', sanitize_text_field( $input['type'] ) );
			}

			$rows = $query->get();
			$items = array();
			foreach ( $rows as $r ) {
				$items[] = array(
					'id'      => (int) $r->id,
					'user_id' => (int) $r->user_id,
					'type'    => (string) $r->type,
				);
			}

			return array(
				'total'     => count( $items ),
				'reactions' => $items,
			);
		},
	) );

	// =========================================================================
	// ===== Cluster 4.5: Notification mutations (4) ===========================
	// =========================================================================
	// Per-user read-state lives in fcom_notification_users (model:
	// \FluentCommunity\App\Models\NotificationSubscriber) — column is is_read TINYINT.

	// ── 4.5.1 mark-notification-read ────────────────────────────────────────
	$reg->write( 'fluent-community/mark-notification-read', array(
		'label'       => 'Mark Notification Read',
		'description' => 'Mark a single notification as read for the current user (sets fcom_notification_users.is_read=1).',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'notification_id' ),
			'properties' => array(
				'notification_id' => array( 'type' => 'integer', 'description' => 'fcom_notifications.id' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'updated' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$notification_id = (int) ( $input['notification_id'] ?? 0 );
			$user_id         = get_current_user_id();
			if ( ! $user_id ) {
				return fluent_abilities_error( 'rest_forbidden', 'No authenticated user' );
			}

			$updated = \FluentCommunity\App\Models\NotificationSubscriber::where( 'user_id', $user_id )
				->where( 'object_id', $notification_id )
				->update( array( 'is_read' => 1 ) );

			return array(
				'success' => true,
				'updated' => (int) $updated,
			);
		},
	) );

	// ── 4.5.2 mark-all-notifications-read ───────────────────────────────────
	$reg->write( 'fluent-community/mark-all-notifications-read', array(
		'label'       => 'Mark All Notifications Read',
		'description' => 'Mark every unread notification as read for the current user.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'updated' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$user_id = get_current_user_id();
			if ( ! $user_id ) {
				return fluent_abilities_error( 'rest_forbidden', 'No authenticated user' );
			}

			$updated = \FluentCommunity\App\Models\NotificationSubscriber::where( 'user_id', $user_id )
				->where( 'is_read', 0 )
				->update( array( 'is_read' => 1 ) );

			return array(
				'success' => true,
				'updated' => (int) $updated,
			);
		},
	) );

	// ── 4.5.3 mark-feed-notifications-read ──────────────────────────────────
	$reg->write( 'fluent-community/mark-feed-notifications-read', array(
		'label'       => 'Mark Feed Notifications Read',
		'description' => 'Mark all notifications related to a specific feed as read for the current user.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'feed_id' ),
			'properties' => array(
				'feed_id' => array( 'type' => 'integer', 'description' => 'fcom_posts.id of the feed' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'updated' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$feed_id = (int) ( $input['feed_id'] ?? 0 );
			$user_id = get_current_user_id();
			if ( ! $user_id ) {
				return fluent_abilities_error( 'rest_forbidden', 'No authenticated user' );
			}

			// Find notifications referencing this feed via Notification::feed_id.
			$notification_ids = \FluentCommunity\App\Models\Notification::where( 'feed_id', $feed_id )
				->pluck( 'id' )
				->toArray();

			if ( empty( $notification_ids ) ) {
				return array(
					'success' => true,
					'updated' => 0,
				);
			}

			$updated = \FluentCommunity\App\Models\NotificationSubscriber::where( 'user_id', $user_id )
				->whereIn( 'object_id', $notification_ids )
				->update( array( 'is_read' => 1 ) );

			return array(
				'success' => true,
				'updated' => (int) $updated,
			);
		},
	) );

	// ── 4.5.4 list-unread-notifications ─────────────────────────────────────
	$reg->read( 'fluent-community/list-unread-notifications', array(
		'label'       => 'List Unread Notifications',
		'description' => "List the current user's unread notifications plus unread_count (max 50).",
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'unread_count'  => array( 'type' => 'integer' ),
				'notifications' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
			),
		),
		'callback' => function( $input ) {
			$user_id = get_current_user_id();
			if ( ! $user_id ) {
				return fluent_abilities_error( 'rest_forbidden', 'No authenticated user' );
			}

			$unread_pivot_rows = \FluentCommunity\App\Models\NotificationSubscriber::where( 'user_id', $user_id )
				->where( 'is_read', 0 )
				->limit( 50 )
				->get();

			$notification_ids = array();
			foreach ( $unread_pivot_rows as $pivot ) {
				$notification_ids[] = (int) $pivot->object_id;
			}

			$notifications = array();
			if ( ! empty( $notification_ids ) ) {
				$rows = \FluentCommunity\App\Models\Notification::whereIn( 'id', $notification_ids )
					->get();
				foreach ( $rows as $n ) {
					$notifications[] = fluent_abilities_safe_array( $n );
				}
			}

			$unread_count = \FluentCommunity\App\Models\NotificationSubscriber::where( 'user_id', $user_id )
				->where( 'is_read', 0 )
				->count();

			return array(
				'unread_count'  => (int) $unread_count,
				'notifications' => $notifications,
			);
		},
	) );

	// =========================================================================
	// ===== Cluster 4.6: Settings (12 — 6 get/update pairs) ===================
	// =========================================================================

	// ── 4.6.1 get-features-settings ─────────────────────────────────────────
	$reg->read( 'fluent-community/get-features-settings', array(
		'label'       => 'Get Features Settings',
		'description' => "Return option 'fluent_community_features'. Sub-schema keys: leader_board_module, course_module, giphy_module, giphy_api_key, cloud_storage, emoji_module, user_badge, has_crm_sync, followers_module, custom_profile_fields (each yes|no except giphy_api_key string).",
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(),
		),
		'output_schema' => array(
			'type' => 'object',
		),
		'callback' => function( $input ) {
			$value = get_option( 'fluent_community_features', array() );
			return is_array( $value ) ? $value : array();
		},
	) );

	// ── 4.6.2 update-features-settings ──────────────────────────────────────
	$reg->write( 'fluent-community/update-features-settings', array(
		'label'       => 'Update Features Settings',
		'description' => "Update option 'fluent_community_features'. Keys: leader_board_module, course_module, giphy_module, giphy_api_key, cloud_storage, emoji_module, user_badge, has_crm_sync, followers_module, custom_profile_fields. Yes|no enums sanitized; unknown keys passed through.",
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'settings' ),
			'properties' => array(
				'settings' => array( 'type' => 'object', 'description' => 'Features settings object (see description for keys).' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback' => function( $input ) {
			$incoming = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();
			$yes_no_keys = array( 'leader_board_module', 'course_module', 'giphy_module', 'cloud_storage', 'emoji_module', 'user_badge', 'has_crm_sync', 'followers_module', 'custom_profile_fields' );

			$sanitized = array();
			foreach ( $incoming as $k => $v ) {
				$k = sanitize_key( $k );
				if ( in_array( $k, $yes_no_keys, true ) ) {
					$sanitized[ $k ] = ( $v === 'yes' || $v === true || $v === 1 ) ? 'yes' : 'no';
				} elseif ( $k === 'giphy_api_key' ) {
					$sanitized[ $k ] = sanitize_text_field( (string) $v );
				} else {
					$sanitized[ $k ] = $v;
				}
			}

			update_option( 'fluent_community_features', $sanitized );

			return array( 'success' => true );
		},
	) );

	// ── 4.6.3 get-menu-settings ─────────────────────────────────────────────
	$reg->read( 'fluent-community/get-menu-settings', array(
		'label'       => 'Get Menu Settings',
		'description' => "Return option 'fluent_community_menu_groups'. Holds menu-group structures (key, title, items[]).",
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(),
		),
		'output_schema' => array(
			'type' => 'object',
		),
		'callback' => function( $input ) {
			$value = get_option( 'fluent_community_menu_groups', array() );
			return array( 'menu_groups' => is_array( $value ) ? $value : array() );
		},
	) );

	// ── 4.6.4 update-menu-settings ──────────────────────────────────────────
	$reg->write( 'fluent-community/update-menu-settings', array(
		'label'       => 'Update Menu Settings',
		'description' => "Update option 'fluent_community_menu_groups'. Pass full menu_groups payload; validation defers to controller.",
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'menu_groups' ),
			'properties' => array(
				'menu_groups' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback' => function( $input ) {
			$menu_groups = isset( $input['menu_groups'] ) && is_array( $input['menu_groups'] ) ? $input['menu_groups'] : array();
			update_option( 'fluent_community_menu_groups', $menu_groups );
			return array( 'success' => true );
		},
	) );

	// ── 4.6.5 get-customization-settings ────────────────────────────────────
	$reg->read( 'fluent-community/get-customization-settings', array(
		'label'       => 'Get Customization Settings',
		'description' => 'Return UI/theming customization settings via Utility::getCustomizationSettings(). Yes|no keys: dark_mode, fixed_page_header, show_powered_by, show_post_modal, feed_link_on_sidebar, fixed_sidebar, icon_on_header_menu, disable_feed_layout, collapse_sidebar_groups; affiliate_id integer.',
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(),
		),
		'output_schema' => array(
			'type' => 'object',
		),
		'callback' => function( $input ) {
			if ( ! class_exists( '\\FluentCommunity\\App\\Services\\Helper\\Utility' ) ) {
				return fluent_abilities_error( 'not_available', 'FluentCommunity Utility helper not available' );
			}
			return (array) \FluentCommunity\App\Services\Helper\Utility::getCustomizationSettings();
		},
	) );

	// ── 4.6.6 update-customization-settings ─────────────────────────────────
	$reg->write( 'fluent-community/update-customization-settings', array(
		'label'       => 'Update Customization Settings',
		'description' => 'Update UI/theming customization settings via Utility::updateCustomizationSettings(). See get-customization-settings for keys.',
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'settings' ),
			'properties' => array(
				'settings' => array( 'type' => 'object' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback' => function( $input ) {
			if ( ! class_exists( '\\FluentCommunity\\App\\Services\\Helper\\Utility' ) || ! method_exists( '\\FluentCommunity\\App\\Services\\Helper\\Utility', 'updateCustomizationSettings' ) ) {
				return fluent_abilities_error( 'not_available', 'FluentCommunity Utility::updateCustomizationSettings not available' );
			}

			$incoming    = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();
			$yes_no_keys = array( 'dark_mode', 'fixed_page_header', 'show_powered_by', 'show_post_modal', 'feed_link_on_sidebar', 'fixed_sidebar', 'icon_on_header_menu', 'disable_feed_layout', 'collapse_sidebar_groups' );

			$sanitized = array();
			foreach ( $incoming as $k => $v ) {
				$k = sanitize_key( $k );
				if ( in_array( $k, $yes_no_keys, true ) ) {
					$sanitized[ $k ] = ( $v === 'yes' || $v === true || $v === 1 ) ? 'yes' : 'no';
				} elseif ( $k === 'affiliate_id' ) {
					$sanitized[ $k ] = (int) $v;
				} else {
					$sanitized[ $k ] = $v;
				}
			}

			\FluentCommunity\App\Services\Helper\Utility::updateCustomizationSettings( $sanitized );

			return array( 'success' => true );
		},
	) );

	// ── 4.6.7 get-privacy-settings ──────────────────────────────────────────
	$reg->read( 'fluent-community/get-privacy-settings', array(
		'label'       => 'Get Privacy Settings',
		'description' => 'Return privacy settings via Utility::getPrivacySettings().',
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(),
		),
		'output_schema' => array(
			'type' => 'object',
		),
		'callback' => function( $input ) {
			if ( ! class_exists( '\\FluentCommunity\\App\\Services\\Helper\\Utility' ) || ! method_exists( '\\FluentCommunity\\App\\Services\\Helper\\Utility', 'getPrivacySettings' ) ) {
				return fluent_abilities_error( 'not_available', 'FluentCommunity Utility::getPrivacySettings not available' );
			}
			return (array) \FluentCommunity\App\Services\Helper\Utility::getPrivacySettings();
		},
	) );

	// ── 4.6.8 update-privacy-settings ───────────────────────────────────────
	$reg->write( 'fluent-community/update-privacy-settings', array(
		'label'       => 'Update Privacy Settings',
		'description' => 'Update privacy settings. V10 signature alignment (P-K): SettingController::updatePrivacySettings(Request $request) per installed vendor source app/Http/Controllers/SettingController.php expects a vendor Request object, not an array — the prior call passed an array and produced a PHP TypeError (F-COM-04). Registrar now routes through the vendor public helper Utility::updatePrivacySettings($settings) (the same call SettingController invokes internally), with a direct-option fallback when vendor symbols are absent.',
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'settings' ),
			'properties' => array(
				'settings' => array( 'type' => 'object' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback' => function( $input ) {
			$incoming = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();

			// V3 priority 2 (vendor public helper). The controller's request-shape
			// dependency is bypassed; we call the same Utility helper the
			// controller itself invokes internally to persist privacy settings.
			if ( class_exists( '\\FluentCommunity\\App\\Functions\\Utility' )
				&& method_exists( '\\FluentCommunity\\App\\Functions\\Utility', 'updatePrivacySettings' ) ) {
				try {
					\FluentCommunity\App\Functions\Utility::updatePrivacySettings( $incoming );
					return array( 'success' => true );
				} catch ( \Throwable $e ) {
					return new WP_Error( 'vendor_precondition_failed', 'FluentCommunity Utility::updatePrivacySettings failed: ' . $e->getMessage() );
				}
			}

			// V10 fallback: when the vendor helper is absent, persist directly to
			// the documented option. Last-resort path; the typed-error guard above
			// is the primary fix.
			update_option( 'fluent_community_privacy_settings', $incoming );
			return array( 'success' => true );
		},
	) );

	// ── 4.6.9 get-crm-tagging-config ────────────────────────────────────────
	$reg->read( 'fluent-community/get-crm-tagging-config', array(
		'label'       => 'Get CRM Tagging Config',
		'description' => "Return option '_fcom_crm_tagging'. Pro+CRM. Keys: is_enabled, tagging_maps, linked_maps, create_crm_contact, create_user, send_welcome_email, has_space_tagging, has_space_sync, has_course_tagging, has_course_sync.",
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(),
		),
		'output_schema' => array(
			'type' => 'object',
		),
		'callback' => function( $input ) {
			$value = get_option( '_fcom_crm_tagging', array() );
			return is_array( $value ) ? $value : array();
		},
	) );

	// ── 4.6.10 update-crm-tagging-config ────────────────────────────────────
	$reg->write( 'fluent-community/update-crm-tagging-config', array(
		'label'       => 'Update CRM Tagging Config',
		'description' => "Update option '_fcom_crm_tagging'. Pro+CRM. See get-crm-tagging-config for keys.",
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'settings' ),
			'properties' => array(
				'settings' => array( 'type' => 'object' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback' => function( $input ) {
			$incoming    = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();
			$yes_no_keys = array( 'is_enabled', 'create_crm_contact', 'create_user', 'send_welcome_email', 'has_space_tagging', 'has_space_sync', 'has_course_tagging', 'has_course_sync' );

			$sanitized = array();
			foreach ( $incoming as $k => $v ) {
				$k = sanitize_key( $k );
				if ( in_array( $k, $yes_no_keys, true ) ) {
					$sanitized[ $k ] = ( $v === 'yes' || $v === true || $v === 1 ) ? 'yes' : 'no';
				} else {
					$sanitized[ $k ] = $v;
				}
			}

			update_option( '_fcom_crm_tagging', $sanitized );

			return array( 'success' => true );
		},
	) );

	// ── 4.6.11 get-notification-prefs (per-user) ────────────────────────────
	// Default read level so self-access works; callback enforces admin-or-self.
	$reg->read( 'fluent-community/get-notification-prefs', array(
		'label'       => 'Get Notification Preferences',
		'description' => 'Return per-user notification email preferences. Self-access allowed; non-self requires admin. Keys: com_my_post_mail, reply_my_com_mail, mention_mail, digest_email_status, digest_mail, messaging_email_status, message_email_frequency, plus per-space np_by_<space_id>.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'user_id' => array( 'type' => 'integer', 'description' => 'Target user ID (defaults to current user)' ),
			),
		),
		'output_schema' => array(
			'type' => 'object',
		),
		'callback' => function( $input ) {
			$current_user_id = get_current_user_id();
			if ( ! $current_user_id ) {
				return fluent_abilities_error( 'rest_forbidden', 'No authenticated user' );
			}

			$target_user_id = isset( $input['user_id'] ) ? (int) $input['user_id'] : $current_user_id;

			if ( $target_user_id !== $current_user_id && ! current_user_can( 'fluent_community_admin' ) && ! current_user_can( 'manage_options' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'Only admins may read other users\' notification preferences' );
			}

			if ( ! class_exists( '\\FluentCommunity\\App\\Models\\NotificationPref' ) ) {
				return fluent_abilities_error( 'not_available', 'FluentCommunity NotificationPref model not available' );
			}

			$prefs = \FluentCommunity\App\Models\NotificationPref::getUserPrefs( $target_user_id );

			return array(
				'user_id' => $target_user_id,
				'prefs'   => fluent_abilities_safe_array( $prefs ),
			);
		},
	) );

	// ── 4.6.12 update-notification-prefs (per-user) ─────────────────────────
	$reg->write( 'fluent-community/update-notification-prefs', array(
		'label'       => 'Update Notification Preferences',
		'description' => 'Update per-user notification email preferences. Self-access allowed; non-self requires admin. See get-notification-prefs for keys.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'prefs' ),
			'properties' => array(
				'user_id' => array( 'type' => 'integer', 'description' => 'Target user ID (defaults to current user)' ),
				'prefs'   => array( 'type' => 'object', 'description' => 'Preference key→value object' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback' => function( $input ) {
			$current_user_id = get_current_user_id();
			if ( ! $current_user_id ) {
				return fluent_abilities_error( 'rest_forbidden', 'No authenticated user' );
			}

			$target_user_id = isset( $input['user_id'] ) ? (int) $input['user_id'] : $current_user_id;

			if ( $target_user_id !== $current_user_id && ! current_user_can( 'fluent_community_admin' ) && ! current_user_can( 'manage_options' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'Only admins may update other users\' notification preferences' );
			}

			if ( ! class_exists( '\\FluentCommunity\\App\\Models\\NotificationPref' ) ) {
				return fluent_abilities_error( 'not_available', 'FluentCommunity NotificationPref model not available' );
			}

			$prefs = isset( $input['prefs'] ) && is_array( $input['prefs'] ) ? $input['prefs'] : array();

			\FluentCommunity\App\Models\NotificationPref::updateUserPrefs( $target_user_id, $prefs );

			return array( 'success' => true );
		},
	) );

	// =========================================================================
	// ===== Cluster 4.7: XProfile custom-field user values (2) ================
	// =========================================================================
	// Callback enforces self-only unless admin.

	// ── 4.7.1 get-profile-custom-fields ─────────────────────────────────────
	$reg->read( 'fluent-community/get-profile-custom-fields', array(
		'label'       => 'Get Profile Custom Fields',
		'description' => 'Return XProfile.custom_fields JSON for a user. Self-access allowed; non-self requires admin.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'user_id' ),
			'properties' => array(
				'user_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => array(
			'type' => 'object',
		),
		'callback' => function( $input ) {
			$current_user_id = get_current_user_id();
			if ( ! $current_user_id ) {
				return fluent_abilities_error( 'rest_forbidden', 'No authenticated user' );
			}

			$target_user_id = (int) ( $input['user_id'] ?? 0 );
			if ( $target_user_id !== $current_user_id && ! current_user_can( 'fluent_community_admin' ) && ! current_user_can( 'manage_options' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'Only admins may read other users\' custom fields' );
			}

			$profile = \FluentCommunity\App\Models\XProfile::where( 'user_id', $target_user_id )->first();
			if ( ! $profile ) {
				return fluent_abilities_error( 'not_found', 'XProfile not found for that user' );
			}

			return array(
				'user_id'       => $target_user_id,
				'custom_fields' => fluent_abilities_safe_array( $profile->custom_fields ),
			);
		},
	) );

	// ── 4.7.2 update-profile-custom-fields ──────────────────────────────────
	$reg->write( 'fluent-community/update-profile-custom-fields', array(
		'label'       => 'Update Profile Custom Fields',
		'description' => 'Partial-merge update of XProfile.custom_fields JSON for a user. Self-access allowed; non-self requires admin.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'user_id', 'custom_fields' ),
			'properties' => array(
				'user_id'       => array( 'type' => 'integer' ),
				'custom_fields' => array( 'type' => 'object' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback' => function( $input ) {
			$current_user_id = get_current_user_id();
			if ( ! $current_user_id ) {
				return fluent_abilities_error( 'rest_forbidden', 'No authenticated user' );
			}

			$target_user_id = (int) ( $input['user_id'] ?? 0 );
			if ( $target_user_id !== $current_user_id && ! current_user_can( 'fluent_community_admin' ) && ! current_user_can( 'manage_options' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'Only admins may update other users\' custom fields' );
			}

			$profile = \FluentCommunity\App\Models\XProfile::where( 'user_id', $target_user_id )->first();
			if ( ! $profile ) {
				return fluent_abilities_error( 'not_found', 'XProfile not found for that user' );
			}

			$incoming = isset( $input['custom_fields'] ) && is_array( $input['custom_fields'] ) ? $input['custom_fields'] : array();
			$existing = is_array( $profile->custom_fields ) ? $profile->custom_fields : array();

			$merged = array_merge( $existing, $incoming );
			$profile->custom_fields = $merged;
			$profile->save();

			return array( 'success' => true );
		},
	) );

	// =========================================================================
	// ===== Cluster 4.8: Course enrollment (4) ================================
	// =========================================================================
	// KD-3: Uses \FluentCommunity\Modules\Course\Model\Course (NOT App\Models).

	// ── 4.8.1 list-course-students ──────────────────────────────────────────
	$reg->read( 'fluent-community/list-course-students', array(
		'label'       => 'List Course Students',
		'description' => 'List students enrolled in a course (role=student). Includes completion count where available.',
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'course_id' ),
			'properties' => array(
				'course_id' => array( 'type' => 'integer', 'description' => 'Course (space) ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'students', array(
			'user_id' => array( 'type' => 'integer' ),
			'status'  => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$course_id = (int) ( $input['course_id'] ?? 0 );
			$course = \FluentCommunity\Modules\Course\Model\Course::find( $course_id );
			if ( ! $course ) {
				return fluent_abilities_error( 'not_found', 'Course not found' );
			}

			$rows = \FluentCommunity\App\Models\SpaceUserPivot::where( 'space_id', $course_id )
				->where( 'role', 'student' )
				->get();

			$students = array();
			foreach ( $rows as $r ) {
				$students[] = array(
					'user_id' => (int) $r->user_id,
					'status'  => isset( $r->status ) ? (string) $r->status : null,
				);
			}

			return array(
				'total'    => count( $students ),
				'students' => $students,
			);
		},
	) );

	// ── 4.8.2 enroll-user-in-course ─────────────────────────────────────────
	$reg->write( 'fluent-community/enroll-user-in-course', array(
		'label'       => 'Enroll User In Course',
		'description' => 'Attach a user to a course space with role=student, status=active. KD-3: uses canonical Modules/Course namespace.',
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'course_id', 'user_id' ),
			'properties' => array(
				'course_id' => array( 'type' => 'integer' ),
				'user_id'   => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'course_id' => array( 'type' => 'integer' ),
			'user_id'   => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => true ),
		'callback' => function( $input ) {
			$course_id = (int) ( $input['course_id'] ?? 0 );
			$user_id   = (int) ( $input['user_id'] ?? 0 );

			$course = \FluentCommunity\Modules\Course\Model\Course::find( $course_id );
			if ( ! $course ) {
				return fluent_abilities_error( 'not_found', 'Course not found' );
			}

			$course->members()->attach( $user_id, array( 'role' => 'student', 'status' => 'active' ) );

			return array(
				'success'   => true,
				'course_id' => $course_id,
				'user_id'   => $user_id,
			);
		},
	) );

	// ── 4.8.3 unenroll-user-from-course ─────────────────────────────────────
	$reg->delete( 'fluent-community/unenroll-user-from-course', array(
		'label'       => 'Unenroll User From Course',
		'description' => 'Detach a user from a course space. KD-3: uses canonical Modules/Course namespace.',
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'course_id', 'user_id' ),
			'properties' => array(
				'course_id' => array( 'type' => 'integer' ),
				'user_id'   => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'course_id' => array( 'type' => 'integer' ),
			'user_id'   => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => true ),
		'callback' => function( $input ) {
			$course_id = (int) ( $input['course_id'] ?? 0 );
			$user_id   = (int) ( $input['user_id'] ?? 0 );

			$course = \FluentCommunity\Modules\Course\Model\Course::find( $course_id );
			if ( ! $course ) {
				return fluent_abilities_error( 'not_found', 'Course not found' );
			}

			$course->members()->detach( $user_id );

			return array(
				'success'   => true,
				'course_id' => $course_id,
				'user_id'   => $user_id,
			);
		},
	) );

	// ── 4.8.4 get-course-enrollment ─────────────────────────────────────────
	$reg->read( 'fluent-community/get-course-enrollment', array(
		'label'       => 'Get Course Enrollment',
		'description' => 'Return the pivot row (role, status, created_at) for a user\'s enrollment in a course.',
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'course_id', 'user_id' ),
			'properties' => array(
				'course_id' => array( 'type' => 'integer' ),
				'user_id'   => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'course_id'  => array( 'type' => 'integer' ),
			'user_id'    => array( 'type' => 'integer' ),
			'role'       => array( 'type' => array( 'string', 'null' ) ),
			'status'     => array( 'type' => array( 'string', 'null' ) ),
			'created_at' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$course_id = (int) ( $input['course_id'] ?? 0 );
			$user_id   = (int) ( $input['user_id'] ?? 0 );

			$course = \FluentCommunity\Modules\Course\Model\Course::find( $course_id );
			if ( ! $course ) {
				return fluent_abilities_error( 'not_found', 'Course not found' );
			}

			$pivot = \FluentCommunity\App\Models\SpaceUserPivot::where( 'space_id', $course_id )
				->where( 'user_id', $user_id )
				->first();
			if ( ! $pivot ) {
				return fluent_abilities_error( 'not_found', 'User is not enrolled in this course' );
			}

			return array(
				'course_id'  => $course_id,
				'user_id'    => $user_id,
				'role'       => isset( $pivot->role ) ? (string) $pivot->role : null,
				'status'     => isset( $pivot->status ) ? (string) $pivot->status : null,
				'created_at' => isset( $pivot->created_at ) ? (string) $pivot->created_at : null,
			);
		},
	) );

	// =========================================================================
	// ===== Cluster 4.9: Following predicate (Pro) (1) ========================
	// =========================================================================

	// ── 4.9.1 check-is-following ────────────────────────────────────────────
	$reg->read( 'fluent-community/check-is-following', array(
		'label'       => 'Check Is Following',
		'description' => 'Return whether follower_id is following followed_id. Pro module required.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'follower_id', 'followed_id' ),
			'properties' => array(
				'follower_id' => array( 'type' => 'integer' ),
				'followed_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'is_following' => array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\\FluentCommunityPro\\App\\Models\\Follow' ) ) {
				return fluent_abilities_error( 'not_available', 'FluentCommunity Pro (Follow model) is not available' );
			}

			$follower_id = (int) ( $input['follower_id'] ?? 0 );
			$followed_id = (int) ( $input['followed_id'] ?? 0 );

			$exists = \FluentCommunityPro\App\Models\Follow::where( 'follower_id', $follower_id )
				->where( 'following_id', $followed_id )
				->exists();

			return array( 'is_following' => (bool) $exists );
		},
	) );

	// =========================================================================
	// ===== Cluster 4.10: Cross-plugin event emission (1) =====================
	// =========================================================================

	$emit_event_allowlist = array(
		'fluent_community/space/joined',
		'fluent_community/space/updated',
		'fluent_community/course/enrolled',
		'fluent_community/feed/created',
		'fluent_community/feed/react_added',
		'fluent_community/feed/react_removed',
		'fluent_community/comment_added',
		'fluent_community/portal_render_for_user',
		'fluent_community/remove_medias_by_url',
		'fluent_community/install_messaging_plugin',
		'fluent_community/install_fluent_player_plugin',
	);

	// ── 4.10.1 emit-event ───────────────────────────────────────────────────
	$reg->write( 'fluent-community/emit-event', array(
		'label'       => 'Emit Cross-Plugin Event',
		'description' => 'Fire a do_action() hook with a payload. Event name must be in the FluentCommunity allow-list or use the custom/* prefix. Allow-listed events: fluent_community/space/joined, /space/updated, /course/enrolled, /feed/created, /feed/react_added, /feed/react_removed, /comment_added, /portal_render_for_user, /remove_medias_by_url, /install_messaging_plugin, /install_fluent_player_plugin.',
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'event' ),
			'properties' => array(
				'event'   => array( 'type' => 'string', 'description' => 'Hook name (must be allow-listed or custom/* prefixed)' ),
				'payload' => array( 'type' => 'object', 'description' => 'Payload passed to do_action() listeners' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'event' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) use ( $emit_event_allowlist ) {
			$event   = isset( $input['event'] ) ? sanitize_text_field( (string) $input['event'] ) : '';
			$payload = isset( $input['payload'] ) ? $input['payload'] : null;

			if ( $event === '' ) {
				return fluent_abilities_error( 'invalid_event', 'event is required' );
			}

			$allowed = in_array( $event, $emit_event_allowlist, true ) || strpos( $event, 'custom/' ) === 0;
			if ( ! $allowed ) {
				return fluent_abilities_error( 'invalid_event', 'Event not in allow-list. Allowed: ' . implode( ', ', $emit_event_allowlist ) . ', or any custom/* prefix.' );
			}

			do_action( $event, $payload );

			return array(
				'success' => true,
				'event'   => $event,
			);
		},
	) );

	// =========================================================================
	// ===== Cluster 4.12: Topics / Terms (7) ==================================
	// =========================================================================
	// Term::taxonomy_name='post_topic'. Term→Space relation via fcom_meta
	// (object_type='term_space_relation', object_id=term_id, meta_key=space_id).
	// Term→Feed relation via fcom_term_feed (no Eloquent model exposed — raw).

	// ── 4.12.1 list-topics ──────────────────────────────────────────────────
	$reg->read( 'fluent-community/list-topics', array(
		'label'       => 'List Topics',
		'description' => "List FluentCommunity topics (terms where taxonomy_name='post_topic'). Distinct from WP core taxonomies.",
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'topics', array(
			'id'   => array( 'type' => 'integer' ),
			'slug' => array( 'type' => array( 'string', 'null' ) ),
			'title' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$rows = \FluentCommunity\App\Models\Term::where( 'taxonomy_name', 'post_topic' )->get();
			$items = array();
			foreach ( $rows as $t ) {
				$items[] = array(
					'id'    => (int) $t->id,
					'slug'  => isset( $t->slug ) ? (string) $t->slug : null,
					'title' => isset( $t->title ) ? (string) $t->title : null,
				);
			}
			return array(
				'total'  => count( $items ),
				'topics' => $items,
			);
		},
	) );

	// ── 4.12.2 get-topic ────────────────────────────────────────────────────
	$reg->read( 'fluent-community/get-topic', array(
		'label'       => 'Get Topic',
		'description' => "Fetch a single FluentCommunity topic by ID (filtered by taxonomy_name='post_topic').",
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback' => function( $input ) {
			$id = (int) ( $input['id'] ?? 0 );
			$term = \FluentCommunity\App\Models\Term::where( 'taxonomy_name', 'post_topic' )
				->where( 'id', $id )
				->first();
			if ( ! $term ) {
				return fluent_abilities_error( 'not_found', 'Topic not found' );
			}
			return fluent_abilities_safe_array( $term );
		},
	) );

	// ── 4.12.3 create-topic ─────────────────────────────────────────────────
	$reg->write( 'fluent-community/create-topic', array(
		'label'       => 'Create Topic',
		'description' => "Create a new FluentCommunity topic with taxonomy_name='post_topic'.",
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'title' ),
			'properties' => array(
				'title'       => array( 'type' => 'string' ),
				'slug'        => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			$title = isset( $input['title'] ) ? sanitize_text_field( (string) $input['title'] ) : '';
			if ( $title === '' ) {
				return fluent_abilities_error( 'invalid_title', 'title is required' );
			}

			$attrs = array(
				'taxonomy_name' => 'post_topic',
				'title'         => $title,
			);
			if ( isset( $input['slug'] ) ) {
				$attrs['slug'] = sanitize_title( (string) $input['slug'] );
			}
			if ( isset( $input['description'] ) ) {
				$attrs['description'] = wp_kses_post( (string) $input['description'] );
			}

			$term = \FluentCommunity\App\Models\Term::create( $attrs );

			return array(
				'success' => true,
				'id'      => (int) $term->id,
			);
		},
	) );

	// ── 4.12.4 update-topic ─────────────────────────────────────────────────
	$reg->write( 'fluent-community/update-topic', array(
		'label'       => 'Update Topic',
		'description' => 'Update title/slug/description of a FluentCommunity topic.',
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'          => array( 'type' => 'integer' ),
				'title'       => array( 'type' => 'string' ),
				'slug'        => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$id   = (int) ( $input['id'] ?? 0 );
			$term = \FluentCommunity\App\Models\Term::where( 'taxonomy_name', 'post_topic' )
				->where( 'id', $id )
				->first();
			if ( ! $term ) {
				return fluent_abilities_error( 'not_found', 'Topic not found' );
			}

			if ( array_key_exists( 'title', $input ) ) {
				$term->title = sanitize_text_field( (string) $input['title'] );
			}
			if ( array_key_exists( 'slug', $input ) ) {
				$term->slug = sanitize_title( (string) $input['slug'] );
			}
			if ( array_key_exists( 'description', $input ) ) {
				$term->description = wp_kses_post( (string) $input['description'] );
			}
			$term->save();

			return array(
				'success' => true,
				'id'      => $id,
			);
		},
	) );

	// ── 4.12.5 delete-topic ─────────────────────────────────────────────────
	$reg->delete( 'fluent-community/delete-topic', array(
		'label'       => 'Delete Topic',
		'description' => 'Delete a FluentCommunity topic and cascade-clear its term_space_relation Meta rows and fcom_term_feed rows.',
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => true ),
		'callback' => function( $input ) {
			$id   = (int) ( $input['id'] ?? 0 );
			$term = \FluentCommunity\App\Models\Term::where( 'taxonomy_name', 'post_topic' )
				->where( 'id', $id )
				->first();
			if ( ! $term ) {
				return fluent_abilities_error( 'not_found', 'Topic not found' );
			}

			\FluentCommunity\App\Models\Meta::where( 'object_type', 'term_space_relation' )
				->where( 'object_id', $id )
				->delete();

			wpFluent()->table( 'fcom_term_feed' )
				->where( 'term_id', $id )
				->delete();

			$term->delete();

			return array(
				'success' => true,
				'id'      => $id,
			);
		},
	) );

	// ── 4.12.6 sync-space-topics ────────────────────────────────────────────
	$reg->write( 'fluent-community/sync-space-topics', array(
		'label'       => 'Sync Space Topics',
		'description' => 'Replace the set of topics assigned to a space (BaseSpace::syncTopics direct wrapper).',
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'space_id', 'topic_ids' ),
			'properties' => array(
				'space_id'  => array( 'type' => 'integer' ),
				'topic_ids' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output(),
		'callback' => function( $input ) {
			$space_id  = (int) ( $input['space_id'] ?? 0 );
			$topic_ids = isset( $input['topic_ids'] ) && is_array( $input['topic_ids'] )
				? array_values( array_filter( array_map( 'intval', $input['topic_ids'] ) ) )
				: array();

			$space = \FluentCommunity\App\Models\Space::find( $space_id );
			if ( ! $space ) {
				return fluent_abilities_error( 'not_found', 'Space not found' );
			}

			if ( method_exists( $space, 'syncTopics' ) ) {
				$space->syncTopics( $topic_ids );
				return array( 'success' => true );
			}

			return fluent_abilities_error( 'not_available', 'Space::syncTopics() not available on this Space instance' );
		},
	) );

	// ── 4.12.7 sync-feed-topics ─────────────────────────────────────────────
	$reg->write( 'fluent-community/sync-feed-topics', array(
		'label'       => 'Sync Feed Topics',
		'description' => 'Replace the set of topics assigned to a feed post (or course_lesson) via the fcom_term_feed pivot table. Validates that feed_id exists in fcom_posts.',
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'feed_id', 'topic_ids' ),
			'properties' => array(
				'feed_id'   => array( 'type' => 'integer', 'description' => 'fcom_posts.id (feed or course_lesson)' ),
				'topic_ids' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'feed_id' => array( 'type' => 'integer' ),
			'synced'  => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$feed_id   = (int) ( $input['feed_id'] ?? 0 );
			$topic_ids = isset( $input['topic_ids'] ) && is_array( $input['topic_ids'] )
				? array_values( array_unique( array_filter( array_map( 'intval', $input['topic_ids'] ) ) ) )
				: array();

			$exists = wpFluent()->table( 'fcom_posts' )->where( 'id', $feed_id )->first();
			if ( ! $exists ) {
				return fluent_abilities_error( 'not_found', 'Feed (fcom_posts.id) not found' );
			}

			wpFluent()->table( 'fcom_term_feed' )->where( 'post_id', $feed_id )->delete();

			$inserted = 0;
			foreach ( $topic_ids as $tid ) {
				if ( $tid <= 0 ) {
					continue;
				}
				wpFluent()->table( 'fcom_term_feed' )->insert( array(
					'post_id' => $feed_id,
					'term_id' => $tid,
				) );
				$inserted++;
			}

			return array(
				'success' => true,
				'feed_id' => $feed_id,
				'synced'  => $inserted,
			);
		},
	) );

	// =========================================================================
	// ===== Cluster 4.13: Surveys / polls (3) =================================
	// =========================================================================

	// ── 4.13.1 cast-survey-vote ─────────────────────────────────────────────
	$reg->write( 'fluent-community/cast-survey-vote', array(
		'label'       => 'Cast Survey Vote',
		'description' => 'Cast a vote on a Feed where content_type=survey. Vote indexes correspond to survey_config.options[].',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'feed_id', 'vote_indexes' ),
			'properties' => array(
				'feed_id'      => array( 'type' => 'integer' ),
				'vote_indexes' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'feed_id' => array( 'type' => 'integer' ),
			'cast'    => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$user_id = get_current_user_id();
			if ( ! $user_id ) {
				return fluent_abilities_error( 'rest_forbidden', 'No authenticated user' );
			}

			$feed_id = (int) ( $input['feed_id'] ?? 0 );
			$indexes = isset( $input['vote_indexes'] ) && is_array( $input['vote_indexes'] )
				? array_values( array_filter( array_map( 'intval', $input['vote_indexes'] ), function( $i ) { return $i >= 0; } ) )
				: array();

			$feed = \FluentCommunity\App\Models\Feed::find( $feed_id );
			if ( ! $feed ) {
				return fluent_abilities_error( 'not_found', 'Feed not found' );
			}

			$meta = is_array( $feed->meta ) ? $feed->meta : (array) $feed->meta;
			$options = isset( $meta['survey_config']['options'] ) && is_array( $meta['survey_config']['options'] )
				? $meta['survey_config']['options']
				: array();

			if ( empty( $options ) ) {
				return fluent_abilities_error( 'invalid_feed', 'Feed is not a survey or has no options' );
			}

			$cast = 0;
			foreach ( $indexes as $idx ) {
				if ( ! isset( $options[ $idx ] ) ) {
					continue;
				}
				$slug = isset( $options[ $idx ]['slug'] ) ? sanitize_text_field( $options[ $idx ]['slug'] ) : (string) $idx;

				$existing = \FluentCommunity\App\Models\Reaction::where( 'object_id', $feed_id )
					->where( 'object_type', $slug )
					->where( 'user_id', $user_id )
					->first();
				if ( $existing ) {
					continue;
				}

				\FluentCommunity\App\Models\Reaction::create( array(
					'object_id'   => $feed_id,
					'object_type' => $slug,
					'user_id'     => $user_id,
					'type'        => 'survey_vote',
				) );
				$cast++;
			}

			return array(
				'success' => true,
				'feed_id' => $feed_id,
				'cast'    => $cast,
			);
		},
	) );

	// ── 4.13.2 get-survey-results ───────────────────────────────────────────
	$reg->read( 'fluent-community/get-survey-results', array(
		'label'       => 'Get Survey Results',
		'description' => 'Return survey options with vote counts plus the current user\'s voted flag per option.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'feed_id' ),
			'properties' => array(
				'feed_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'options', array(
			'slug'       => array( 'type' => 'string' ),
			'label'      => array( 'type' => array( 'string', 'null' ) ),
			'vote_count' => array( 'type' => 'integer' ),
			'voted'      => array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $input ) {
			$feed_id = (int) ( $input['feed_id'] ?? 0 );
			$current_user_id = get_current_user_id();

			$feed = \FluentCommunity\App\Models\Feed::find( $feed_id );
			if ( ! $feed ) {
				return fluent_abilities_error( 'not_found', 'Feed not found' );
			}

			$meta = is_array( $feed->meta ) ? $feed->meta : (array) $feed->meta;
			$options = isset( $meta['survey_config']['options'] ) && is_array( $meta['survey_config']['options'] )
				? $meta['survey_config']['options']
				: array();

			$results = array();
			foreach ( $options as $opt ) {
				$slug  = isset( $opt['slug'] ) ? (string) $opt['slug'] : '';
				$label = isset( $opt['label'] ) ? (string) $opt['label'] : null;

				$count = \FluentCommunity\App\Models\Reaction::where( 'object_id', $feed_id )
					->where( 'object_type', $slug )
					->count();

				$voted = false;
				if ( $current_user_id ) {
					$voted = \FluentCommunity\App\Models\Reaction::where( 'object_id', $feed_id )
						->where( 'object_type', $slug )
						->where( 'user_id', $current_user_id )
						->exists();
				}

				$results[] = array(
					'slug'       => $slug,
					'label'      => $label,
					'vote_count' => (int) $count,
					'voted'      => (bool) $voted,
				);
			}

			return array(
				'total'   => count( $results ),
				'options' => $results,
			);
		},
	) );

	// ── 4.13.3 get-survey-voters ────────────────────────────────────────────
	$reg->read( 'fluent-community/get-survey-voters', array(
		'label'       => 'Get Survey Voters',
		'description' => 'Return the list of users (XProfile records) who voted for a specific survey option.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'feed_id', 'option_slug' ),
			'properties' => array(
				'feed_id'     => array( 'type' => 'integer' ),
				'option_slug' => array( 'type' => 'string' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'voters', array(
			'user_id'      => array( 'type' => 'integer' ),
			'display_name' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$feed_id     = (int) ( $input['feed_id'] ?? 0 );
			$option_slug = isset( $input['option_slug'] ) ? sanitize_text_field( $input['option_slug'] ) : '';

			$reactions = \FluentCommunity\App\Models\Reaction::where( 'object_id', $feed_id )
				->where( 'object_type', $option_slug )
				->get();

			$voters = array();
			$user_ids = array();
			foreach ( $reactions as $r ) {
				$user_ids[] = (int) $r->user_id;
			}
			$user_ids = array_values( array_unique( $user_ids ) );

			if ( ! empty( $user_ids ) ) {
				$profiles = \FluentCommunity\App\Models\XProfile::whereIn( 'user_id', $user_ids )->get();
				foreach ( $profiles as $p ) {
					$voters[] = array(
						'user_id'      => (int) $p->user_id,
						'display_name' => isset( $p->display_name ) ? (string) $p->display_name : null,
					);
				}
			}

			return array(
				'total'  => count( $voters ),
				'voters' => $voters,
			);
		},
	) );

	// =========================================================================
	// ===== Cluster 4.14: Quiz (Pro) (3) ======================================
	// =========================================================================
	// KD-3: Quiz lives on CourseLesson with content_type='quiz'. Uses canonical
	// \FluentCommunity\Modules\Course\Model\CourseLesson namespace.
	// Quiz attempt schema NOT source-verified in research §4.14 — minimal stub
	// queries wpFluent()->table('fcom_quiz_attempts') IF the table exists,
	// otherwise returns WP_Error 'not_available'.

	// ── 4.14.1 list-quiz-attempts ───────────────────────────────────────────
	$reg->read( 'fluent-community/list-quiz-attempts', array(
		'label'       => 'List Quiz Attempts',
		'description' => 'List quiz attempts for a lesson (Pro). Quiz attempt schema is not source-verified — this ability returns WP_Error not_available unless the fcom_quiz_attempts table exists. KD-3: uses canonical Modules/Course/CourseLesson namespace.',
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'lesson_id' ),
			'properties' => array(
				'lesson_id' => array( 'type' => 'integer', 'description' => 'fcom_posts.id where type=course_lesson' ),
				'user_id'   => array( 'type' => 'integer', 'description' => 'Optional user filter' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'attempts', array(
			'id'      => array( 'type' => 'integer' ),
			'user_id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\\FluentCommunity\\Modules\\Course\\Model\\CourseLesson' ) ) {
				return fluent_abilities_error( 'not_available', 'FluentCommunity Course module (CourseLesson) not available' );
			}

			global $wpdb;
			$table = $wpdb->prefix . 'fcom_quiz_attempts';
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( ! $exists ) {
				return fluent_abilities_error( 'not_available', 'Quiz attempts table (fcom_quiz_attempts) not found on this site' );
			}

			$lesson_id = (int) ( $input['lesson_id'] ?? 0 );
			$query = wpFluent()->table( 'fcom_quiz_attempts' )->where( 'lesson_id', $lesson_id );
			if ( isset( $input['user_id'] ) ) {
				$query = $query->where( 'user_id', (int) $input['user_id'] );
			}
			$rows = $query->get();

			$items = array();
			foreach ( $rows as $r ) {
				$items[] = fluent_abilities_safe_array( $r );
			}

			return array(
				'total'    => count( $items ),
				'attempts' => $items,
			);
		},
	) );

	// ── 4.14.2 submit-quiz-attempt ──────────────────────────────────────────
	$reg->write( 'fluent-community/submit-quiz-attempt', array(
		'label'       => 'Submit Quiz Attempt',
		'description' => 'Submit a quiz attempt for a lesson (Pro). Quiz attempt schema is not source-verified — this ability returns WP_Error not_available unless the fcom_quiz_attempts table exists. KD-3: uses canonical Modules/Course/CourseLesson namespace.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'lesson_id', 'answers' ),
			'properties' => array(
				'lesson_id' => array( 'type' => 'integer' ),
				'answers'   => array( 'type' => 'object', 'description' => 'Answer payload (schema lesson-specific)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'attempt_id' => array( 'type' => array( 'integer', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\\FluentCommunity\\Modules\\Course\\Model\\CourseLesson' ) ) {
				return fluent_abilities_error( 'not_available', 'FluentCommunity Course module (CourseLesson) not available' );
			}

			$user_id = get_current_user_id();
			if ( ! $user_id ) {
				return fluent_abilities_error( 'rest_forbidden', 'No authenticated user' );
			}

			global $wpdb;
			$table = $wpdb->prefix . 'fcom_quiz_attempts';
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( ! $exists ) {
				return fluent_abilities_error( 'not_available', 'Quiz attempts table (fcom_quiz_attempts) not found on this site' );
			}

			$lesson_id = (int) ( $input['lesson_id'] ?? 0 );
			$lesson    = \FluentCommunity\Modules\Course\Model\CourseLesson::find( $lesson_id );
			if ( ! $lesson ) {
				return fluent_abilities_error( 'not_found', 'Lesson not found' );
			}

			$answers = isset( $input['answers'] ) ? wp_json_encode( $input['answers'] ) : '{}';
			$now     = current_time( 'mysql' );

			$attempt_id = wpFluent()->table( 'fcom_quiz_attempts' )->insert( array(
				'lesson_id'  => $lesson_id,
				'user_id'    => $user_id,
				'answers'    => $answers,
				'created_at' => $now,
				'updated_at' => $now,
			) );

			return array(
				'success'    => true,
				'attempt_id' => $attempt_id ? (int) $attempt_id : null,
			);
		},
	) );

	// ── 4.14.3 get-quiz-results ─────────────────────────────────────────────
	$reg->read( 'fluent-community/get-quiz-results', array(
		'label'       => 'Get Quiz Results',
		'description' => 'Return the result row for a single quiz attempt (Pro). Quiz attempt schema is not source-verified — this ability returns WP_Error not_available unless the fcom_quiz_attempts table exists.',
		'category'    => 'fluent-community',
		'level'       => 'admin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'attempt_id' ),
			'properties' => array(
				'attempt_id' => array( 'type' => 'integer' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output(),
		'callback' => function( $input ) {
			global $wpdb;
			$table  = $wpdb->prefix . 'fcom_quiz_attempts';
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( ! $exists ) {
				return fluent_abilities_error( 'not_available', 'Quiz attempts table (fcom_quiz_attempts) not found on this site' );
			}

			$attempt_id = (int) ( $input['attempt_id'] ?? 0 );
			$row = wpFluent()->table( 'fcom_quiz_attempts' )->where( 'id', $attempt_id )->first();
			if ( ! $row ) {
				return fluent_abilities_error( 'not_found', 'Quiz attempt not found' );
			}

			return fluent_abilities_safe_array( $row );
		},
	) );

	// =========================================================================
	// ===== Cluster 4.15: Mention search (1) ==================================
	// =========================================================================

	// ── 4.15.1 search-members-mention ───────────────────────────────────────
	$reg->read( 'fluent-community/search-members-mention', array(
		'label'       => 'Search Members (Mention)',
		'description' => 'Autocomplete-style member search by display_name OR username. Excludes the current user, limited to 10 results, optionally scoped to a space. Requires authenticated user.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'mention' ),
			'properties' => array(
				'mention'  => array( 'type' => 'string', 'description' => 'Search string' ),
				'space_id' => array( 'type' => 'integer', 'description' => 'Optional space scope' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'members', array(
			'id'           => array( 'type' => 'integer' ),
			'user_id'      => array( 'type' => 'integer' ),
			'display_name' => array( 'type' => array( 'string', 'null' ) ),
			'username'     => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$current_user_id = get_current_user_id();
			if ( $current_user_id === 0 ) {
				return fluent_abilities_error( 'rest_forbidden', 'Mention search requires an authenticated user' );
			}

			$mention = isset( $input['mention'] ) ? sanitize_text_field( (string) $input['mention'] ) : '';
			if ( $mention === '' ) {
				return array(
					'total'   => 0,
					'members' => array(),
				);
			}

			$query = \FluentCommunity\App\Models\XProfile::where( function( $q ) use ( $mention ) {
					$q->where( 'display_name', 'LIKE', $mention . '%' )
					  ->orWhere( 'username', 'LIKE', $mention . '%' );
				} )
				->where( 'user_id', '!=', $current_user_id )
				->limit( 10 );

			if ( ! empty( $input['space_id'] ) ) {
				$space_id = (int) $input['space_id'];
				$member_user_ids = \FluentCommunity\App\Models\SpaceUserPivot::where( 'space_id', $space_id )
					->pluck( 'user_id' )
					->toArray();
				$query = $query->whereIn( 'user_id', $member_user_ids );
			}

			$rows = $query->get();

			$items = array();
			foreach ( $rows as $p ) {
				$items[] = array(
					'id'           => (int) $p->id,
					'user_id'      => (int) $p->user_id,
					'display_name' => isset( $p->display_name ) ? (string) $p->display_name : null,
					'username'     => isset( $p->username ) ? (string) $p->username : null,
				);
			}

			return array(
				'total'   => count( $items ),
				'members' => $items,
			);
		},
	) );

	error_log( 'Abilities for Fluent: Registered 53 Community v2.0 abilities (clusters 4.1-4.10, 4.12-4.15)' );
}

add_action( 'wp_abilities_api_init', 'fluent_abilities_register_community_v2', 100 );
