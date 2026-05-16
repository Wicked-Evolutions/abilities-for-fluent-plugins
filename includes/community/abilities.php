<?php
/**
 * Fluent Community Abilities
 *
 * Spaces, feeds, comments, members, courses, lessons, reactions,
 * notifications, scheduled posts, media, and settings.
 *
 * 45 abilities in the 'fluent-community' category.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

/**
 * Check whether the current user may access a space or course.
 *
 * Admins (fluent_community_admin) bypass all privacy checks.
 * Non-admins are denied secret spaces entirely, and denied private
 * spaces where they are not an active member.
 *
 * @param object $space  A FluentCommunity Space model instance.
 * @return bool
 */
function fluent_abilities_space_accessible( $space ) {
	if ( fluent_abilities_user_can( 'community', 'admin' ) ) {
		return true;
	}

	if ( 'secret' === $space->privacy ) {
		return false;
	}

	if ( 'private' === $space->privacy ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}
		$is_member = $space->members()
			->where( 'user_id', $user_id )
			->where( 'status', 'active' )
			->exists();
		return (bool) $is_member;
	}

	return true;
}

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'community' );

	// =========================================================================
	// SPACES
	// =========================================================================

	$reg->read( 'fluent-community/list-spaces', array(
		'label'       => 'List Community Spaces',
		'description' => 'List all community spaces (groups, channels, course spaces).',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'type' => array( 'type' => 'string', 'description' => 'Filter by type: community, course' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'spaces', array(
			'id'           => array( 'type' => 'integer' ),
			'title'        => array( 'type' => 'string' ),
			'slug'         => array( 'type' => 'string' ),
			'type'         => array( 'type' => 'string' ),
			'privacy'      => array( 'type' => 'string' ),
			'status'       => array( 'type' => 'string' ),
			'members_count'=> array( 'type' => 'integer' ),
			'created_at'   => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$query = \FluentCommunity\App\Models\Space::orderBy( 'title', 'ASC' );

			if ( ! empty( $input['type'] ) ) {
				$query->where( 'type', sanitize_text_field( $input['type'] ) );
			}

			$spaces = $query->get();
			$items = array();
			foreach ( $spaces as $space ) {
				if ( ! fluent_abilities_space_accessible( $space ) ) {
					continue;
				}
				$items[] = array(
					'id'          => $space->id,
					'title'       => $space->title,
					'slug'        => $space->slug,
					'type'        => $space->type,
					'privacy'     => $space->privacy,
					'status'      => $space->status,
					'description' => $space->description,
					'members_count' => $space->members_count ?? 0,
				);
			}

			return array( 'spaces' => $items, 'total' => count( $items ) );
		},
	) );

	$reg->read( 'fluent-community/get-space', array(
		'label'       => 'Get Community Space',
		'description' => 'Get space details by ID or slug.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id'   => array( 'type' => 'integer', 'description' => 'Space ID' ),
				'slug' => array( 'type' => 'string', 'description' => 'Space slug (alternative to ID)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'           => array( 'type' => 'integer' ),
			'title'        => array( 'type' => 'string' ),
			'slug'         => array( 'type' => 'string' ),
			'type'         => array( 'type' => 'string' ),
			'privacy'      => array( 'type' => 'string' ),
			'status'       => array( 'type' => 'string' ),
			'description'  => array( 'type' => 'string' ),
			'settings'     => array( 'type' => 'object' ),
			'members_count'=> array( 'type' => 'integer' ),
			'created_at'   => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! empty( $input['id'] ) ) {
				$space = \FluentCommunity\App\Models\Space::find( (int) $input['id'] );
			} elseif ( ! empty( $input['slug'] ) ) {
				$space = \FluentCommunity\App\Models\Space::where( 'slug', sanitize_text_field( $input['slug'] ) )->first();
			} else {
				return fluent_abilities_error( 'ability_invalid_input', 'Provide either id or slug' );
			}

			if ( ! $space ) {
				return fluent_abilities_error( 'not_found', 'Space not found' );
			}

			if ( ! fluent_abilities_space_accessible( $space ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have access to this space' );
			}

			return array(
				'id'            => $space->id,
				'title'         => $space->title,
				'slug'          => $space->slug,
				'type'          => $space->type,
				'privacy'       => $space->privacy,
				'status'        => $space->status,
				'description'   => $space->description,
				'settings'      => fluent_abilities_safe_array( $space->settings ),
				'members_count' => $space->members_count ?? 0,
				'created_at'    => (string) $space->created_at,
			);
		},
	) );

	$reg->write( 'fluent-community/create-space', array(
		'label'       => 'Create Community Space',
		'description' => 'Create a new community space.',
		'category'    => 'fluent-community',
		'annotations' => array( 'idempotent' => false ),
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'title' ),
			'properties' => array(
				'title'       => array( 'type' => 'string', 'description' => 'Space title' ),
				'slug'        => array( 'type' => 'string', 'description' => 'Space slug (auto-generated from title if omitted)' ),
				'description' => array( 'type' => 'string', 'description' => 'Space description' ),
				'privacy'     => array( 'type' => 'string', 'description' => 'Privacy: public, private, secret (default: public)' ),
				'status'      => array( 'type' => 'string', 'description' => 'Status: active, draft (default: active)' ),
				'type'        => array( 'type' => 'string', 'description' => 'Space type: community, course (default: community)' ),
				'group_id'    => array( 'type' => 'integer', 'description' => 'Space group ID (optional)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'     => array( 'type' => 'integer' ),
			'slug'   => array( 'type' => 'string' ),
			'status' => array( 'type' => 'string' ),
		) ),
		'level'    => 'admin',
		'callback' => function( $input ) {
			$title = sanitize_text_field( $input['title'] );
			$slug  = ! empty( $input['slug'] ) ? sanitize_title( $input['slug'] ) : sanitize_title( $title );

			// Check slug uniqueness.
			if ( \FluentCommunity\App\Models\Space::where( 'slug', $slug )->exists() ) {
				return fluent_abilities_error( 'ability_invalid_input', 'A space with this slug already exists' );
			}

			$data = array(
				'title'       => $title,
				'slug'        => $slug,
				'description' => sanitize_text_field( $input['description'] ?? '' ),
				'privacy'     => sanitize_text_field( $input['privacy'] ?? 'public' ),
				'status'      => sanitize_text_field( $input['status'] ?? 'active' ),
				'type'        => sanitize_text_field( $input['type'] ?? 'community' ),
			);

			if ( ! empty( $input['group_id'] ) ) {
				$data['parent_id'] = (int) $input['group_id'];
			}

			$space = \FluentCommunity\App\Models\Space::create( $data );

			// Auto-add the creator as admin member.
			$user_id = get_current_user_id();
			if ( $user_id && method_exists( $space, 'members' ) ) {
				$space->members()->attach( $user_id, array( 'role' => 'admin', 'status' => 'active' ) );
			}

			return array( 'success' => true, 'id' => $space->id, 'slug' => $space->slug, 'status' => $space->status );
		},
	) );

	$reg->write( 'fluent-community/update-space', array(
		'label'       => 'Update Community Space',
		'description' => 'Update an existing community space.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'space_id' ),
			'properties' => array(
				'space_id'    => array( 'type' => 'integer', 'description' => 'Space ID' ),
				'title'       => array( 'type' => 'string', 'description' => 'New title' ),
				'description' => array( 'type' => 'string', 'description' => 'New description' ),
				'privacy'     => array( 'type' => 'string', 'description' => 'Privacy: public, private, secret' ),
				'status'      => array( 'type' => 'string', 'description' => 'Status: active, draft' ),
				'settings'    => array( 'type' => 'object', 'description' => 'Settings object (partial merge)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'     => array( 'type' => 'integer' ),
			'title'  => array( 'type' => 'string' ),
			'status' => array( 'type' => 'string' ),
		) ),
		'level'    => 'admin',
		'callback' => function( $input ) {
			$space = \FluentCommunity\App\Models\Space::find( (int) $input['space_id'] );
			if ( ! $space ) {
				return fluent_abilities_error( 'not_found', 'Space not found' );
			}

			$fillable = array( 'title', 'description', 'privacy', 'status' );
			foreach ( $fillable as $field ) {
				if ( isset( $input[ $field ] ) ) {
					$space->$field = sanitize_text_field( $input[ $field ] );
				}
			}

			if ( isset( $input['settings'] ) && is_array( $input['settings'] ) ) {
				$existing = $space->settings ?? array();
				if ( is_string( $existing ) ) {
					$existing = json_decode( $existing, true ) ?: array();
				}
				$space->settings = array_merge( $existing, $input['settings'] );
			}

			$space->save();

			return array( 'success' => true, 'id' => $space->id, 'title' => $space->title, 'status' => $space->status );
		},
	) );

	// =========================================================================
	// FEEDS (Posts)
	// =========================================================================

	$reg->read( 'fluent-community/list-feeds', array(
		'label'       => 'List Community Feeds',
		'description' => 'List posts/feeds with optional space and status filters.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'space_id' => array( 'type' => 'integer', 'description' => 'Filter by space ID' ),
				'status'   => array( 'type' => 'string', 'description' => 'Filter: published, draft, archived' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'feeds', array(
			'id'              => array( 'type' => 'integer' ),
			'title'           => array( 'type' => 'string' ),
			'space_id'        => array( 'type' => 'integer' ),
			'user_id'         => array( 'type' => 'integer' ),
			'status'          => array( 'type' => 'string' ),
			'type'            => array( 'type' => 'string' ),
			'privacy'         => array( 'type' => 'string' ),
			'comments_count'  => array( 'type' => 'integer' ),
			'reactions_count' => array( 'type' => 'integer' ),
			'created_at'      => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$query = \FluentCommunity\App\Models\Feed::orderBy( 'id', 'DESC' );

			if ( ! empty( $input['space_id'] ) ) {
				// Validate space accessibility before filtering by it.
				$space = \FluentCommunity\App\Models\Space::find( (int) $input['space_id'] );
				if ( ! $space || ! fluent_abilities_space_accessible( $space ) ) {
					return fluent_abilities_error( 'rest_forbidden', 'You do not have access to this space' );
				}
				$query->where( 'space_id', (int) $input['space_id'] );
			} else {
				// No space filter — exclude feeds from secret/inaccessible spaces.
				if ( ! fluent_abilities_user_can( 'community', 'admin' ) ) {
					$accessible_ids = \FluentCommunity\App\Models\Space::where( 'privacy', 'public' )
						->pluck( 'id' )
						->toArray();
					if ( empty( $accessible_ids ) ) {
						return array( 'feeds' => array(), 'total' => 0, 'page' => 1 );
					}
					$query->whereIn( 'space_id', $accessible_ids );
				}
			}

			if ( ! empty( $input['status'] ) ) {
				$query->where( 'status', sanitize_text_field( $input['status'] ) );
			}

			$total = $query->count();
			$feeds = $query->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();

			$items = array();
			foreach ( $feeds as $feed ) {
				$items[] = array(
					'id'         => $feed->id,
					'title'      => $feed->title,
					'space_id'   => $feed->space_id,
					'user_id'    => $feed->user_id,
					'type'       => $feed->type,
					'status'     => $feed->status,
					'privacy'    => $feed->privacy,
					'created_at' => (string) $feed->created_at,
				);
			}

			return array( 'feeds' => $items, 'total' => $total, 'page' => $pagination['page'] );
		},
	) );

	$reg->read( 'fluent-community/get-feed', array(
		'label'       => 'Get Community Feed',
		'description' => 'Get a single feed/post by ID with full content.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Feed ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'               => array( 'type' => 'integer' ),
			'title'            => array( 'type' => 'string' ),
			'message'          => array( 'type' => 'string' ),
			'message_rendered' => array( 'type' => array( 'string', 'null' ) ),
			'space_id'         => array( 'type' => 'integer' ),
			'user_id'          => array( 'type' => 'integer' ),
			'type'             => array( 'type' => 'string' ),
			'status'           => array( 'type' => 'string' ),
			'privacy'          => array( 'type' => 'string' ),
			'comments_count'   => array( 'type' => 'integer' ),
			'reactions_count'  => array( 'type' => 'integer' ),
			'meta'             => array( 'type' => 'object' ),
			'created_at'       => array( 'type' => 'string' ),
			'updated_at'       => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$feed = \FluentCommunity\App\Models\Feed::find( (int) $input['id'] );
			if ( ! $feed ) {
				return fluent_abilities_error( 'not_found', 'Feed not found' );
			}

			// Verify space is accessible to the current user.
			$space = \FluentCommunity\App\Models\Space::find( $feed->space_id );
			if ( ! $space || ! fluent_abilities_space_accessible( $space ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have access to this feed' );
			}

			return array(
				'id'             => $feed->id,
				'title'          => $feed->title,
				'message'        => $feed->message,
				'message_rendered' => $feed->message_rendered ?? null,
				'space_id'       => $feed->space_id,
				'user_id'        => $feed->user_id,
				'type'           => $feed->type,
				'status'         => $feed->status,
				'privacy'        => $feed->privacy,
				'comments_count' => $feed->comments_count ?? 0,
				'reactions_count'=> $feed->reactions_count ?? 0,
				'meta'           => fluent_abilities_safe_array( $feed->meta ),
				'created_at'     => (string) $feed->created_at,
				'updated_at'     => (string) $feed->updated_at,
			);
		},
	) );

	$reg->write( 'fluent-community/create-feed', array(
		'label'       => 'Create Community Post',
		'description' => 'Create a new post in a community space.',
		'category'    => 'fluent-community',
		'annotations' => array( 'idempotent' => false ),
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'space_id', 'message' ),
			'properties' => array(
				'space_id' => array( 'type' => 'integer', 'description' => 'Space ID to post in' ),
				'title'    => array( 'type' => 'string', 'description' => 'Post title (optional)' ),
				'message'  => array( 'type' => 'string', 'description' => 'Post content/message' ),
				'type'     => array( 'type' => 'string', 'description' => 'Post type: text, image, video (default: text)' ),
				'status'   => array( 'type' => 'string', 'description' => 'Status: published, draft (default: published)' ),
				'privacy'  => array( 'type' => 'string', 'description' => 'Privacy: public, private, space_only (default: space_only)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'     => array( 'type' => 'integer' ),
			'status' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$space = \FluentCommunity\App\Models\Space::find( (int) $input['space_id'] );
			if ( ! $space ) {
				return fluent_abilities_error( 'not_found', 'Space not found' );
			}

			if ( ! fluent_abilities_space_accessible( $space ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have access to this space.' );
			}

			$feed = \FluentCommunity\App\Models\Feed::create( array(
				'space_id' => $space->id,
				'user_id'  => get_current_user_id(),
				'title'    => sanitize_text_field( $input['title'] ?? '' ),
				'message'  => wp_kses_post( $input['message'] ),
				'type'     => sanitize_text_field( $input['type'] ?? 'text' ),
				'status'   => sanitize_text_field( $input['status'] ?? 'published' ),
				'privacy'  => sanitize_text_field( $input['privacy'] ?? 'space_only' ),
			));

			return array( 'success' => true, 'id' => $feed->id, 'status' => $feed->status );
		},
	) );

	// =========================================================================
	// COMMENTS
	// =========================================================================

	$reg->read( 'fluent-community/list-comments', array(
		'label'       => 'List Feed Comments',
		'description' => 'List comments on a specific feed/post.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'feed_id' ),
			'properties' => array_merge( array(
				'feed_id' => array( 'type' => 'integer', 'description' => 'Feed ID' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'comments', array(
			'id'         => array( 'type' => 'integer' ),
			'feed_id'    => array( 'type' => 'integer' ),
			'user_id'    => array( 'type' => 'integer' ),
			'message'    => array( 'type' => 'string' ),
			'created_at' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			// Verify the feed exists and its space is accessible.
			$feed = \FluentCommunity\App\Models\Feed::find( (int) $input['feed_id'] );
			if ( ! $feed ) {
				return fluent_abilities_error( 'not_found', 'Feed not found' );
			}
			$space = \FluentCommunity\App\Models\Space::find( $feed->space_id );
			if ( ! $space || ! fluent_abilities_space_accessible( $space ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have access to this feed' );
			}

			$pagination = fluent_abilities_pagination( $input );
			$query = \FluentCommunity\App\Models\Comment::where( 'post_id', (int) $input['feed_id'] )
				->orderBy( 'id', 'ASC' );

			$total = $query->count();
			$comments = $query->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();

			$items = array();
			foreach ( $comments as $comment ) {
				$items[] = array(
					'id'         => $comment->id,
					'user_id'    => $comment->user_id,
					'message'    => $comment->message,
					'parent_id'  => $comment->parent_id,
					'created_at' => (string) $comment->created_at,
				);
			}

			return array( 'comments' => $items, 'total' => $total, 'page' => $pagination['page'] );
		},
	) );

	$reg->write( 'fluent-community/create-comment', array(
		'label'       => 'Create Feed Comment',
		'description' => 'Add a comment to a feed/post.',
		'category'    => 'fluent-community',
		'annotations' => array( 'idempotent' => false ),
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'feed_id', 'message' ),
			'properties' => array(
				'feed_id'   => array( 'type' => 'integer', 'description' => 'Feed ID' ),
				'message'   => array( 'type' => 'string', 'description' => 'Comment content' ),
				'parent_id' => array( 'type' => 'integer', 'description' => 'Parent comment ID for replies' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$feed = \FluentCommunity\App\Models\Feed::find( (int) $input['feed_id'] );
			if ( ! $feed ) {
				return fluent_abilities_error( 'not_found', 'Feed not found' );
			}

			$comment = \FluentCommunity\App\Models\Comment::create( array(
				'post_id'   => $feed->id,
				'user_id'   => get_current_user_id(),
				'message'   => wp_kses_post( $input['message'] ),
				'parent_id' => ! empty( $input['parent_id'] ) ? (int) $input['parent_id'] : null,
			));

			return array( 'success' => true, 'id' => $comment->id );
		},
	) );

	// =========================================================================
	// MEMBERS
	// =========================================================================

	$reg->read( 'fluent-community/list-members', array(
		'label'       => 'List Community Members',
		'description' => 'List community members with optional search.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'search' => array( 'type' => 'string', 'description' => 'Search by name or email' ),
				'status' => array( 'type' => 'string', 'description' => 'Filter by status' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'members', array(
			'id'           => array( 'type' => 'integer' ),
			'user_id'      => array( 'type' => 'integer' ),
			'display_name' => array( 'type' => 'string' ),
			'status'       => array( 'type' => 'string' ),
			'total_points' => array( 'type' => 'integer' ),
			'created_at'   => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$query = \FluentCommunity\App\Models\XProfile::orderBy( 'id', 'DESC' );

			if ( ! empty( $input['search'] ) ) {
				$search = sanitize_text_field( $input['search'] );
				$query->where( 'display_name', 'LIKE', "%{$search}%" );
			}
			if ( ! empty( $input['status'] ) ) {
				$query->where( 'status', sanitize_text_field( $input['status'] ) );
			}

			$total = $query->count();
			$members = $query->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();

			$items = array();
			foreach ( $members as $member ) {
				$items[] = array(
					'id'           => $member->id,
					'user_id'      => $member->user_id,
					'display_name' => $member->display_name,
					'status'       => $member->status,
					'created_at'   => (string) $member->created_at,
				);
			}

			return array( 'members' => $items, 'total' => $total, 'page' => $pagination['page'] );
		},
	) );

	$reg->read( 'fluent-community/list-space-members', array(
		'label'       => 'List Space Members',
		'description' => 'List members of a specific space.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'space_id' ),
			'properties' => array_merge( array(
				'space_id' => array( 'type' => 'integer', 'description' => 'Space ID' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'members', array(
			'id'           => array( 'type' => 'integer' ),
			'user_id'      => array( 'type' => 'integer' ),
			'display_name' => array( 'type' => 'string' ),
			'role'         => array( 'type' => 'string' ),
			'status'       => array( 'type' => 'string' ),
			'created_at'   => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$space = \FluentCommunity\App\Models\Space::find( (int) $input['space_id'] );
			if ( ! $space ) {
				return fluent_abilities_error( 'not_found', 'Space not found' );
			}

			$query = $space->members()->orderBy( 'ID', 'DESC' );
			$total = $query->count();
			$members = $query->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();

			$items = array();
			foreach ( $members as $member ) {
				$items[] = array(
					'id'           => (int) $member->ID,
					'user_id'      => (int) $member->ID,
					'display_name' => $member->display_name ?? null,
					'role'         => $member->pivot->role ?? null,
					'status'       => $member->pivot->status ?? null,
				);
			}

			return array( 'members' => $items, 'total' => $total, 'page' => $pagination['page'] );
		},
	) );

	// =========================================================================
	// COURSES & LESSONS
	// =========================================================================

	$reg->read( 'fluent-community/list-courses', array(
		'label'       => 'List Courses',
		'description' => 'List all courses (spaces with type=course).',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'courses', array(
			'id'           => array( 'type' => 'integer' ),
			'title'        => array( 'type' => 'string' ),
			'slug'         => array( 'type' => 'string' ),
			'type'         => array( 'type' => 'string' ),
			'privacy'      => array( 'type' => 'string' ),
			'status'       => array( 'type' => 'string' ),
			'members_count'=> array( 'type' => 'integer' ),
			'created_at'   => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$courses = \FluentCommunity\App\Models\Space::where( 'type', 'course' )
				->orderBy( 'title', 'ASC' )
				->get();

			$items = array();
			foreach ( $courses as $course ) {
				if ( ! fluent_abilities_space_accessible( $course ) ) {
					continue;
				}
				$items[] = array(
					'id'            => $course->id,
					'title'         => $course->title,
					'slug'          => $course->slug,
					'status'        => $course->status,
					'description'   => $course->description,
					'members_count' => $course->members_count ?? 0,
				);
			}

			return array( 'courses' => $items, 'total' => count( $items ) );
		},
	) );

	$reg->read( 'fluent-community/get-course', array(
		'label'       => 'Get Course',
		'description' => 'Get course details by ID.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Course (space) ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'           => array( 'type' => 'integer' ),
			'title'        => array( 'type' => 'string' ),
			'slug'         => array( 'type' => 'string' ),
			'type'         => array( 'type' => 'string' ),
			'privacy'      => array( 'type' => 'string' ),
			'status'       => array( 'type' => 'string' ),
			'description'  => array( 'type' => 'string' ),
			'settings'     => array( 'type' => 'object' ),
			'members_count'=> array( 'type' => 'integer' ),
			'created_at'   => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$course = \FluentCommunity\App\Models\Space::where( 'type', 'course' )->find( (int) $input['id'] );
			if ( ! $course ) {
				return fluent_abilities_error( 'not_found', 'Course not found' );
			}

			if ( ! fluent_abilities_space_accessible( $course ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have access to this course' );
			}

			return array(
				'id'            => $course->id,
				'title'         => $course->title,
				'slug'          => $course->slug,
				'status'        => $course->status,
				'privacy'       => $course->privacy,
				'description'   => $course->description,
				'settings'      => fluent_abilities_safe_array( $course->settings ),
				'members_count' => $course->members_count ?? 0,
				'created_at'    => (string) $course->created_at,
			);
		},
	) );

	$reg->read( 'fluent-community/get-course-by-slug', array(
		'label'       => 'Get Course by Slug',
		'description' => 'Get course details by slug.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'slug' ),
			'properties' => array(
				'slug' => array( 'type' => 'string', 'description' => 'Course slug' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'           => array( 'type' => 'integer' ),
			'title'        => array( 'type' => 'string' ),
			'slug'         => array( 'type' => 'string' ),
			'type'         => array( 'type' => 'string' ),
			'privacy'      => array( 'type' => 'string' ),
			'status'       => array( 'type' => 'string' ),
			'description'  => array( 'type' => 'string' ),
			'settings'     => array( 'type' => 'object' ),
			'members_count'=> array( 'type' => 'integer' ),
			'created_at'   => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$course = \FluentCommunity\App\Models\Space::where( 'type', 'course' )
				->where( 'slug', sanitize_text_field( $input['slug'] ) )
				->first();
			if ( ! $course ) {
				return fluent_abilities_error( 'not_found', 'Course not found' );
			}

			if ( ! fluent_abilities_space_accessible( $course ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have access to this course' );
			}

			return array(
				'id'            => $course->id,
				'title'         => $course->title,
				'slug'          => $course->slug,
				'status'        => $course->status,
				'privacy'       => $course->privacy,
				'description'   => $course->description,
				'settings'      => fluent_abilities_safe_array( $course->settings ),
				'members_count' => $course->members_count ?? 0,
				'created_at'    => (string) $course->created_at,
			);
		},
	) );

	$reg->read( 'fluent-community/get-admin-courses', array(
		'label'       => 'Get Admin Courses',
		'description' => 'List courses with admin-level details (enrollment counts, draft courses).',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'status' => array( 'type' => 'string', 'description' => 'Filter by status: active, draft' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'courses', array(
			'id'               => array( 'type' => 'integer' ),
			'title'            => array( 'type' => 'string' ),
			'status'           => array( 'type' => 'string' ),
			'enrollment_count' => array( 'type' => 'integer' ),
		) ),
		'capability' => 'manage_options',
		'callback'   => function( $input ) {
			$query = \FluentCommunity\App\Models\Space::where( 'type', 'course' )
				->orderBy( 'title', 'ASC' );

			if ( ! empty( $input['status'] ) ) {
				$query->where( 'status', sanitize_text_field( $input['status'] ) );
			}

			$courses = $query->get();
			$items = array();
			foreach ( $courses as $course ) {
				$items[] = array(
					'id'            => $course->id,
					'title'         => $course->title,
					'slug'          => $course->slug,
					'status'        => $course->status,
					'privacy'       => $course->privacy,
					'description'   => $course->description,
					'settings'      => fluent_abilities_safe_array( $course->settings ),
					'members_count' => $course->members_count ?? 0,
					'created_at'    => (string) $course->created_at,
					'updated_at'    => (string) $course->updated_at,
				);
			}

			return array( 'courses' => $items, 'total' => count( $items ) );
		},
	) );

	$reg->read( 'fluent-community/list-lessons', array(
		'label'       => 'List Course Lessons',
		'description' => 'List lessons within a course, grouped by section.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'course_id' ),
			'properties' => array(
				'course_id' => array( 'type' => 'integer', 'description' => 'Course (space) ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'lessons', array(
			'id'         => array( 'type' => 'integer' ),
			'title'      => array( 'type' => 'string' ),
			'status'     => array( 'type' => 'string' ),
			'course_id'  => array( 'type' => 'integer' ),
			'created_at' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			// Privacy check: verify course is accessible.
			$course = \FluentCommunity\App\Models\Space::where( 'type', 'course' )->find( (int) $input['course_id'] );
			if ( ! $course ) {
				return fluent_abilities_error( 'not_found', 'Course not found' );
			}
			if ( ! fluent_abilities_space_accessible( $course ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You do not have access to this course' );
			}

			if ( ! class_exists( '\FluentCommunity\App\Models\CourseLesson' ) ) {
				// Try the Feed-based lessons approach.
				$lessons = \FluentCommunity\App\Models\Feed::where( 'space_id', (int) $input['course_id'] )
					->where( 'type', 'lesson' )
					->orderBy( 'priority', 'ASC' )
					->get();
			} else {
				$lessons = \FluentCommunity\App\Models\CourseLesson::where( 'course_id', (int) $input['course_id'] )
					->orderBy( 'serial', 'ASC' )
					->get();
			}

			$items = array();
			foreach ( $lessons as $lesson ) {
				$items[] = array(
					'id'         => $lesson->id,
					'title'      => $lesson->title,
					'status'     => $lesson->status,
					'order'      => $lesson->serial ?? $lesson->priority ?? null,
					'section'    => $lesson->section ?? $lesson->parent_id ?? null,
					'created_at' => (string) $lesson->created_at,
				);
			}

			return array( 'lessons' => $items, 'total' => count( $items ) );
		},
	) );

	$reg->read( 'fluent-community/get-lesson', array(
		'label'       => 'Get Lesson Content',
		'description' => 'Get full lesson content by ID.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Lesson ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'         => array( 'type' => 'integer' ),
			'title'      => array( 'type' => 'string' ),
			'content'    => array( 'type' => 'string' ),
			'status'     => array( 'type' => 'string' ),
			'course_id'  => array( 'type' => 'integer' ),
			'created_at' => array( 'type' => 'string' ),
			'updated_at' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( class_exists( '\FluentCommunity\App\Models\CourseLesson' ) ) {
				$lesson = \FluentCommunity\App\Models\CourseLesson::find( (int) $input['id'] );
			} else {
				$lesson = \FluentCommunity\App\Models\Feed::where( 'type', 'lesson' )->find( (int) $input['id'] );
			}

			if ( ! $lesson ) {
				return fluent_abilities_error( 'not_found', 'Lesson not found' );
			}

			// Privacy check: verify the lesson's course is accessible.
			$course_id = $lesson->space_id ?? $lesson->course_id ?? null;
			if ( $course_id ) {
				$course = \FluentCommunity\App\Models\Space::find( $course_id );
				if ( ! $course || ! fluent_abilities_space_accessible( $course ) ) {
					return fluent_abilities_error( 'rest_forbidden', 'You do not have access to this lesson' );
				}
			}

			return array(
				'id'         => $lesson->id,
				'title'      => $lesson->title,
				'content'    => $lesson->message ?? $lesson->content ?? '',
				'status'     => $lesson->status,
				'order'      => $lesson->serial ?? $lesson->priority ?? null,
				'course_id'  => $lesson->space_id ?? $lesson->course_id ?? null,
				'created_at' => (string) $lesson->created_at,
				'updated_at' => (string) $lesson->updated_at,
			);
		},
	) );

	$reg->write( 'fluent-community/create-lesson', array(
		'label'       => 'Create Course Lesson',
		'description' => 'Create a new lesson in a course.',
		'category'    => 'fluent-community',
		'annotations' => array( 'idempotent' => false ),
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'course_id', 'title' ),
			'properties' => array(
				'course_id' => array( 'type' => 'integer', 'description' => 'Course (space) ID' ),
				'title'     => array( 'type' => 'string', 'description' => 'Lesson title' ),
				'content'   => array( 'type' => 'string', 'description' => 'Lesson content (HTML)' ),
				'status'    => array( 'type' => 'string', 'description' => 'Status: published, draft (default: draft)' ),
				'order'     => array( 'type' => 'integer', 'description' => 'Sort order' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'     => array( 'type' => 'integer' ),
			'status' => array( 'type' => 'string' ),
		) ),
		'level'    => 'admin',
		'callback' => function( $input ) {
			$course = \FluentCommunity\App\Models\Space::where( 'type', 'course' )->find( (int) $input['course_id'] );
			if ( ! $course ) {
				return fluent_abilities_error( 'not_found', 'Course not found' );
			}

			$data = array(
				'space_id' => $course->id,
				'user_id'  => get_current_user_id(),
				'title'    => sanitize_text_field( $input['title'] ),
				'message'  => wp_kses_post( $input['content'] ?? '' ),
				'type'     => 'lesson',
				'status'   => sanitize_text_field( $input['status'] ?? 'draft' ),
				'priority' => (int) ( $input['order'] ?? 0 ),
			);

			$lesson = \FluentCommunity\App\Models\Feed::create( $data );

			return array( 'success' => true, 'id' => $lesson->id, 'status' => $lesson->status );
		},
	) );

	$reg->write( 'fluent-community/update-lesson', array(
		'label'       => 'Update Course Lesson',
		'description' => 'Update an existing lesson.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'      => array( 'type' => 'integer', 'description' => 'Lesson ID' ),
				'title'   => array( 'type' => 'string', 'description' => 'New title' ),
				'content' => array( 'type' => 'string', 'description' => 'New content (HTML)' ),
				'status'  => array( 'type' => 'string', 'description' => 'Status: published, draft' ),
				'order'   => array( 'type' => 'integer', 'description' => 'Sort order' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'     => array( 'type' => 'integer' ),
			'title'  => array( 'type' => 'string' ),
			'status' => array( 'type' => 'string' ),
		) ),
		'level'    => 'admin',
		'callback' => function( $input ) {
			if ( class_exists( '\FluentCommunity\Modules\Course\Model\CourseLesson' ) ) {
				$lesson = \FluentCommunity\Modules\Course\Model\CourseLesson::find( (int) $input['id'] );
			} else {
				$lesson = \FluentCommunity\App\Models\Feed::where( 'type', 'lesson' )->find( (int) $input['id'] );
			}

			if ( ! $lesson ) {
				return fluent_abilities_error( 'not_found', 'Lesson not found' );
			}

			if ( isset( $input['title'] ) ) {
				$lesson->title = sanitize_text_field( $input['title'] );
			}
			if ( isset( $input['content'] ) ) {
				$content_field = property_exists( $lesson, 'message' ) || isset( $lesson->message ) ? 'message' : 'content';
				$lesson->$content_field = wp_kses_post( $input['content'] );
			}
			if ( isset( $input['status'] ) ) {
				$lesson->status = sanitize_text_field( $input['status'] );
			}
			if ( isset( $input['order'] ) ) {
				$order_field = property_exists( $lesson, 'serial' ) || isset( $lesson->serial ) ? 'serial' : 'priority';
				$lesson->$order_field = (int) $input['order'];
			}

			$lesson->save();

			return array( 'success' => true, 'id' => $lesson->id, 'title' => $lesson->title, 'status' => $lesson->status );
		},
	) );

	// =========================================================================
	// COURSE PROGRESS
	// =========================================================================

	$reg->read( 'fluent-community/get-course-progress', array(
		'label'       => 'Get Course Progress',
		'description' => 'Get a user\'s progress in a course (completed lessons).',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'course_id' ),
			'properties' => array(
				'course_id' => array( 'type' => 'integer', 'description' => 'Course (space) ID' ),
				'user_id'   => array( 'type' => 'integer', 'description' => 'User ID (defaults to current user)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'completed_lessons'   => array( 'type' => 'integer' ),
			'total_lessons'       => array( 'type' => 'integer' ),
			'progress_percentage' => array( 'type' => 'number' ),
		) ),
		'callback' => function( $input ) {
			$course = \FluentCommunity\App\Models\Space::where( 'type', 'course' )->find( (int) $input['course_id'] );
			if ( ! $course ) {
				return fluent_abilities_error( 'not_found', 'Course not found' );
			}

			$current_user_id = get_current_user_id();
			$user_id = ! empty( $input['user_id'] ) ? (int) $input['user_id'] : $current_user_id;

			// Non-admins may only query their own progress.
			if ( $user_id !== $current_user_id && ! fluent_abilities_user_can( 'community', 'admin' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You may only view your own course progress' );
			}

			// Get all lessons for this course.
			$total_lessons = 0;
			if ( class_exists( '\FluentCommunity\Modules\Course\Model\CourseLesson' ) ) {
				$total_lessons = \FluentCommunity\Modules\Course\Model\CourseLesson::where( 'course_id', $course->id )
					->where( 'status', 'published' )
					->count();
			} else {
				$total_lessons = \FluentCommunity\App\Models\Feed::where( 'space_id', $course->id )
					->where( 'type', 'lesson' )
					->where( 'status', 'published' )
					->count();
			}

			// Get completed lessons via Reaction model.
			$completed = \FluentCommunity\App\Models\Reaction::where( 'user_id', $user_id )
				->where( 'object_type', 'lesson_completed' )
				->where( 'type', 'completed' )
				->whereHas( 'post', function( $q ) use ( $course ) {
					$q->where( 'space_id', $course->id );
				})
				->count();

			$percentage = $total_lessons > 0 ? round( ( $completed / $total_lessons ) * 100 ) : 0;

			return array(
				'course_id'       => $course->id,
				'user_id'         => $user_id,
				'total_lessons'   => $total_lessons,
				'completed'       => $completed,
				'percentage'      => $percentage,
				'is_complete'     => $completed >= $total_lessons && $total_lessons > 0,
			);
		},
	) );

	$reg->write( 'fluent-community/mark-lesson-complete', array(
		'label'       => 'Mark Lesson Complete',
		'description' => 'Mark a lesson as completed for a user.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'lesson_id' ),
			'properties' => array(
				'lesson_id' => array( 'type' => 'integer', 'description' => 'Lesson ID' ),
				'user_id'   => array( 'type' => 'integer', 'description' => 'User ID (defaults to current user)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'lesson_id'        => array( 'type' => 'integer' ),
			'user_id'          => array( 'type' => 'integer' ),
			'already_completed'=> array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $input ) {
			$current_user_id = get_current_user_id();
			$user_id = ! empty( $input['user_id'] ) ? (int) $input['user_id'] : $current_user_id;

			// Non-admins may only mark their own lessons complete.
			if ( $user_id !== $current_user_id && ! fluent_abilities_user_can( 'community', 'admin' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You may only mark your own lessons as complete' );
			}

			$lesson_id = (int) $input['lesson_id'];

			// Check lesson exists.
			if ( class_exists( '\FluentCommunity\Modules\Course\Model\CourseLesson' ) ) {
				$lesson = \FluentCommunity\Modules\Course\Model\CourseLesson::find( $lesson_id );
			} else {
				$lesson = \FluentCommunity\App\Models\Feed::where( 'type', 'lesson' )->find( $lesson_id );
			}
			if ( ! $lesson ) {
				return fluent_abilities_error( 'not_found', 'Lesson not found' );
			}

			// Check if already completed.
			$existing = \FluentCommunity\App\Models\Reaction::where( 'user_id', $user_id )
				->where( 'object_id', $lesson_id )
				->where( 'object_type', 'lesson_completed' )
				->first();

			if ( $existing ) {
				return array( 'success' => true, 'already_completed' => true );
			}

			\FluentCommunity\App\Models\Reaction::create( array(
				'user_id'     => $user_id,
				'object_id'   => $lesson_id,
				'object_type' => 'lesson_completed',
				'type'        => 'completed',
			));

			return array( 'success' => true, 'lesson_id' => $lesson_id, 'user_id' => $user_id );
		},
	) );

	$reg->write( 'fluent-community/mark-lesson-incomplete', array(
		'label'       => 'Mark Lesson Incomplete',
		'description' => 'Remove completion status from a lesson for a user.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'lesson_id' ),
			'properties' => array(
				'lesson_id' => array( 'type' => 'integer', 'description' => 'Lesson ID' ),
				'user_id'   => array( 'type' => 'integer', 'description' => 'User ID (defaults to current user)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'removed' => array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $input ) {
			$current_user_id = get_current_user_id();
			$user_id = ! empty( $input['user_id'] ) ? (int) $input['user_id'] : $current_user_id;

			// Non-admins may only unmark their own lesson completion.
			if ( $user_id !== $current_user_id && ! fluent_abilities_user_can( 'community', 'admin' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You may only modify your own lesson completion' );
			}

			$lesson_id = (int) $input['lesson_id'];

			$deleted = \FluentCommunity\App\Models\Reaction::where( 'user_id', $user_id )
				->where( 'object_id', $lesson_id )
				->where( 'object_type', 'lesson_completed' )
				->delete();

			return array( 'success' => true, 'removed' => $deleted > 0 );
		},
	) );

	// =========================================================================
	// NOTIFICATIONS
	// =========================================================================

	$reg->read( 'fluent-community/list-notifications', array(
		'label'       => 'List Notifications',
		'description' => 'List notifications for the current user.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => fluent_abilities_pagination_schema(),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'notifications', array(
			'id'              => array( 'type' => 'integer' ),
			'src_user_id'     => array( 'type' => array( 'integer', 'null' ) ),
			'action'          => array( 'type' => array( 'string', 'null' ) ),
			'content'         => array( 'type' => array( 'string', 'null' ) ),
			'src_object_type' => array( 'type' => array( 'string', 'null' ) ),
			'created_at'      => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$user_id = get_current_user_id();

			// Notifications don't have a direct user_id column.
			// They link to feeds via feed_id. Get feed IDs for the current user's posts.
			$user_feed_ids = wpFluent()->table( 'fcom_posts' )
				->where( 'user_id', $user_id )
				->select( 'id' )
				->get();

			$feed_ids = array();
			foreach ( $user_feed_ids as $f ) {
				$feed_ids[] = (int) $f->id;
			}

			if ( empty( $feed_ids ) ) {
				return array( 'notifications' => array(), 'total' => 0, 'page' => $pagination['page'] );
			}

			$query = wpFluent()->table( 'fcom_notifications' )
				->whereIn( 'feed_id', $feed_ids )
				->orderBy( 'id', 'DESC' );

			$total = $query->count();
			$notifications = $query->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();

			$items = array();
			foreach ( $notifications as $notif ) {
				$items[] = array(
					'id'              => (int) $notif->id,
					'src_user_id'     => $notif->src_user_id ? (int) $notif->src_user_id : null,
					'action'          => $notif->action,
					'content'         => $notif->content,
					'src_object_type' => $notif->src_object_type,
					'created_at'      => (string) $notif->created_at,
				);
			}

			return array( 'notifications' => $items, 'total' => $total, 'page' => $pagination['page'] );
		},
	) );

	// =========================================================================
	// LEADERBOARD
	// =========================================================================

	$reg->read( 'fluent-community/get-leaderboard', array(
		'label'       => 'Get Leaderboard',
		'description' => 'Get community leaderboard — top members by activity.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'limit' => array( 'type' => 'integer', 'description' => 'Number of members (default: 10, max: 50)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'leaderboard' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $input ) {
			$limit = min( 50, max( 1, (int) ( $input['limit'] ?? 10 ) ) );

			$members = \FluentCommunity\App\Models\XProfile::orderBy( 'total_points', 'DESC' )
				->limit( $limit )
				->get();

			$items = array();
			$rank = 1;
			foreach ( $members as $member ) {
				$items[] = array(
					'rank'         => $rank++,
					'user_id'      => $member->user_id,
					'display_name' => $member->display_name,
					'total_points' => $member->total_points ?? 0,
				);
			}

			return array( 'leaderboard' => $items );
		},
	) );

	// =========================================================================
	// SPACE GROUPS
	// =========================================================================

	$reg->read( 'fluent-community/get-space-groups', array(
		'label'       => 'Get Space Groups',
		'description' => 'Get space groups (organizational categories for spaces).',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'groups', array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
			'slug'  => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentCommunity\App\Models\SpaceGroup' ) ) {
				return array( 'groups' => array(), 'total' => 0 );
			}

			$groups = \FluentCommunity\App\Models\SpaceGroup::orderBy( 'serial', 'ASC' )->get();
			$items = array();
			foreach ( $groups as $group ) {
				$items[] = array(
					'id'    => $group->id,
					'title' => $group->title,
					'slug'  => $group->slug,
					'order' => $group->serial,
				);
			}

			return array( 'groups' => $items, 'total' => count( $items ) );
		},
	) );

	// =========================================================================
	// STATS — Top Members, Commenters, Post Starters
	// =========================================================================

	$reg->read( 'fluent-community/get-top-members', array(
		'label'       => 'Get Top Members',
		'description' => 'Get top community members ranked by total points.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'limit'  => array( 'type' => 'integer', 'description' => 'Number of members (default: 10, max: 50)' ),
				'period' => array( 'type' => 'string', 'description' => 'Time period: all_time, this_week, this_month (default: all_time)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'members', array(
			'user_id'      => array( 'type' => 'integer' ),
			'display_name' => array( 'type' => 'string' ),
			'total_points' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$limit = min( 50, max( 1, (int) ( $input['limit'] ?? 10 ) ) );

			$members = \FluentCommunity\App\Models\XProfile::orderBy( 'total_points', 'DESC' )
				->where( 'status', 'active' )
				->limit( $limit )
				->get();

			$items = array();
			$rank = 1;
			foreach ( $members as $m ) {
				$items[] = array(
					'rank'         => $rank++,
					'user_id'      => $m->user_id,
					'display_name' => $m->display_name,
					'total_points' => $m->total_points ?? 0,
					'username'     => $m->username ?? null,
				);
			}

			return array( 'members' => $items, 'total' => count( $items ) );
		},
	) );

	$reg->read( 'fluent-community/get-top-commenters', array(
		'label'       => 'Get Top Commenters',
		'description' => 'Get top community members by comment count.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'limit' => array( 'type' => 'integer', 'description' => 'Number of members (default: 10, max: 50)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'commenters', array(
			'user_id'       => array( 'type' => 'integer' ),
			'display_name'  => array( 'type' => 'string' ),
			'comment_count' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$limit = min( 50, max( 1, (int) ( $input['limit'] ?? 10 ) ) );

			global $wpdb;
			$table = $wpdb->prefix . 'fcom_post_comments';
			$profiles_table = $wpdb->prefix . 'fcom_xprofile';

			$results = $wpdb->get_results( $wpdb->prepare(
				"SELECT c.user_id, COUNT(*) as comment_count, p.display_name
				FROM {$table} c
				JOIN {$profiles_table} p ON p.user_id = c.user_id
				GROUP BY c.user_id
				ORDER BY comment_count DESC
				LIMIT %d",
				$limit
			) );

			$items = array();
			$rank = 1;
			foreach ( $results as $row ) {
				$items[] = array(
					'rank'          => $rank++,
					'user_id'       => (int) $row->user_id,
					'display_name'  => $row->display_name,
					'comment_count' => (int) $row->comment_count,
				);
			}

			return array( 'commenters' => $items, 'total' => count( $items ) );
		},
	) );

	$reg->read( 'fluent-community/get-top-post-starters', array(
		'label'       => 'Get Top Post Starters',
		'description' => 'Get top community members by post/feed count.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'limit' => array( 'type' => 'integer', 'description' => 'Number of members (default: 10, max: 50)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_collection_output( 'posters', array(
			'user_id'      => array( 'type' => 'integer' ),
			'display_name' => array( 'type' => 'string' ),
			'post_count'   => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$limit = min( 50, max( 1, (int) ( $input['limit'] ?? 10 ) ) );

			global $wpdb;
			$table = $wpdb->prefix . 'fcom_posts';
			$profiles_table = $wpdb->prefix . 'fcom_xprofile';

			$results = $wpdb->get_results( $wpdb->prepare(
				"SELECT f.user_id, COUNT(*) as post_count, p.display_name
				FROM {$table} f
				JOIN {$profiles_table} p ON p.user_id = f.user_id
				WHERE f.status = 'published'
				GROUP BY f.user_id
				ORDER BY post_count DESC
				LIMIT %d",
				$limit
			) );

			$items = array();
			$rank = 1;
			foreach ( $results as $row ) {
				$items[] = array(
					'rank'         => $rank++,
					'user_id'      => (int) $row->user_id,
					'display_name' => $row->display_name,
					'post_count'   => (int) $row->post_count,
				);
			}

			return array( 'posters' => $items, 'total' => count( $items ) );
		},
	) );

	$reg->read( 'fluent-community/list-activities', array(
		'label'       => 'List Activities',
		'description' => 'List community activity feed (recent actions by members).',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'user_id' => array( 'type' => 'integer', 'description' => 'Filter by user ID' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'activities', array(
			'id'         => array( 'type' => 'integer' ),
			'user_id'    => array( 'type' => 'integer' ),
			'action'     => array( 'type' => array( 'string', 'null' ) ),
			'object_id'  => array( 'type' => array( 'integer', 'null' ) ),
			'created_at' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );

			if ( ! class_exists( '\FluentCommunity\App\Models\Activity' ) ) {
				return array( 'activities' => array(), 'total' => 0, 'page' => $pagination['page'] );
			}

			$query = \FluentCommunity\App\Models\Activity::orderBy( 'id', 'DESC' );

			if ( ! empty( $input['user_id'] ) ) {
				$query->where( 'user_id', (int) $input['user_id'] );
			}

			$total = $query->count();
			$activities = $query->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();

			$items = array();
			foreach ( $activities as $act ) {
				$items[] = array(
					'id'         => $act->id,
					'user_id'    => $act->user_id,
					'action'     => $act->action ?? $act->type ?? null,
					'content'    => $act->content ?? $act->description ?? null,
					'object_id'  => $act->object_id ?? null,
					'created_at' => (string) $act->created_at,
				);
			}

			return array( 'activities' => $items, 'total' => $total, 'page' => $pagination['page'] );
		},
	) );

	// =========================================================================
	// PROFILES
	// =========================================================================

	$reg->read( 'fluent-community/get-profile', array(
		'label'       => 'Get Member Profile',
		'description' => 'Get a community member profile by user ID.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'user_id' ),
			'properties' => array(
				'user_id' => array( 'type' => 'integer', 'description' => 'WordPress user ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'user_id'           => array( 'type' => 'integer' ),
			'display_name'      => array( 'type' => 'string' ),
			'short_description' => array( 'type' => 'string' ),
			'meta'              => array( 'type' => 'object' ),
			'created_at'        => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$profile = \FluentCommunity\App\Models\XProfile::where( 'user_id', (int) $input['user_id'] )->first();
			if ( ! $profile ) {
				return fluent_abilities_error( 'not_found', 'Profile not found' );
			}

			return array(
				'id'           => $profile->id,
				'user_id'      => $profile->user_id,
				'display_name' => $profile->display_name,
				'username'     => $profile->username ?? null,
				'email'        => $profile->email ?? null,
				'status'       => $profile->status,
				'total_points' => $profile->total_points ?? 0,
				'avatar'       => $profile->avatar ?? null,
				'short_description' => $profile->short_description ?? null,
				'meta'         => fluent_abilities_safe_array( $profile->meta ),
				'created_at'   => (string) $profile->created_at,
			);
		},
	) );

	$reg->read( 'fluent-community/get-my-profile', array(
		'label'       => 'Get My Profile',
		'description' => 'Get the current user\'s community profile.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'user_id'           => array( 'type' => 'integer' ),
			'display_name'      => array( 'type' => 'string' ),
			'short_description' => array( 'type' => 'string' ),
			'meta'              => array( 'type' => 'object' ),
			'created_at'        => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$user_id = get_current_user_id();
			$profile = \FluentCommunity\App\Models\XProfile::where( 'user_id', $user_id )->first();
			if ( ! $profile ) {
				return fluent_abilities_error( 'not_found', 'Profile not found for current user' );
			}

			return array(
				'id'           => $profile->id,
				'user_id'      => $profile->user_id,
				'display_name' => $profile->display_name,
				'username'     => $profile->username ?? null,
				'email'        => $profile->email ?? null,
				'status'       => $profile->status,
				'total_points' => $profile->total_points ?? 0,
				'avatar'       => $profile->avatar ?? null,
				'short_description' => $profile->short_description ?? null,
				'meta'         => fluent_abilities_safe_array( $profile->meta ),
				'created_at'   => (string) $profile->created_at,
			);
		},
	) );

	$reg->write( 'fluent-community/update-profile', array(
		'label'       => 'Update Member Profile',
		'description' => 'Update a community member profile.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'user_id' ),
			'properties' => array(
				'user_id'           => array( 'type' => 'integer', 'description' => 'WordPress user ID' ),
				'display_name'      => array( 'type' => 'string', 'description' => 'Display name' ),
				'short_description' => array( 'type' => 'string', 'description' => 'Short bio/description' ),
				'status'            => array( 'type' => 'string', 'description' => 'Status: active, blocked' ),
				'meta'              => array( 'type' => 'object', 'description' => 'Meta fields (partial merge)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'user_id'      => array( 'type' => 'integer' ),
			'display_name' => array( 'type' => 'string' ),
		) ),
		'level'    => 'admin',
		'callback' => function( $input ) {
			$profile = \FluentCommunity\App\Models\XProfile::where( 'user_id', (int) $input['user_id'] )->first();
			if ( ! $profile ) {
				return fluent_abilities_error( 'not_found', 'Profile not found' );
			}

			$fillable = array( 'display_name', 'short_description', 'status' );
			foreach ( $fillable as $field ) {
				if ( isset( $input[ $field ] ) ) {
					$profile->$field = sanitize_text_field( $input[ $field ] );
				}
			}

			if ( isset( $input['meta'] ) && is_array( $input['meta'] ) ) {
				$existing = $profile->meta ?? array();
				if ( is_string( $existing ) ) {
					$existing = json_decode( $existing, true ) ?: array();
				}
				$profile->meta = array_merge( $existing, $input['meta'] );
			}

			$profile->save();

			return array( 'success' => true, 'user_id' => $profile->user_id, 'display_name' => $profile->display_name );
		},
	) );

	$reg->write( 'fluent-community/follow-user', array(
		'label'       => 'Follow User',
		'description' => 'Follow a community member (requires FluentCommunity Pro).',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'target_user_id' ),
			'properties' => array(
				'target_user_id' => array( 'type' => 'integer', 'description' => 'User ID to follow' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'follower_id'       => array( 'type' => 'integer' ),
			'following_id'      => array( 'type' => 'integer' ),
			'already_following' => array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentCommunityPro\App\Models\Follow' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'Follow feature requires FluentCommunity Pro' );
			}

			// Always act as the current user — no impersonation via user_id.
			$follower_id = get_current_user_id();
			$target_id   = (int) $input['target_user_id'];

			if ( $follower_id === $target_id ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Cannot follow yourself' );
			}

			// Check if already following.
			$existing = wpFluent()->table( 'fcom_followers' )
				->where( 'follower_id', $follower_id )
				->where( 'followed_id', $target_id )
				->first();

			if ( $existing ) {
				return array( 'success' => true, 'already_following' => true );
			}

			wpFluent()->table( 'fcom_followers' )->insert( array(
				'follower_id' => $follower_id,
				'followed_id' => $target_id,
				'created_at'  => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			));

			return array( 'success' => true, 'follower_id' => $follower_id, 'following_id' => $target_id );
		},
	) );

	$reg->write( 'fluent-community/unfollow-user', array(
		'label'       => 'Unfollow User',
		'description' => 'Unfollow a community member (requires FluentCommunity Pro).',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'target_user_id' ),
			'properties' => array(
				'target_user_id' => array( 'type' => 'integer', 'description' => 'User ID to unfollow' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'removed' => array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentCommunityPro\App\Models\Follow' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'Follow feature requires FluentCommunity Pro' );
			}

			// Always act as the current user — no impersonation via user_id.
			$follower_id = get_current_user_id();
			$target_id   = (int) $input['target_user_id'];

			$deleted = wpFluent()->table( 'fcom_followers' )
				->where( 'follower_id', $follower_id )
				->where( 'followed_id', $target_id )
				->delete();

			return array( 'success' => true, 'removed' => $deleted > 0 );
		},
	) );

	$reg->read( 'fluent-community/list-followers', array(
		'label'       => 'List Followers',
		'description' => 'List followers of a community member (requires FluentCommunity Pro).',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'user_id' ),
			'properties' => array_merge( array(
				'user_id' => array( 'type' => 'integer', 'description' => 'User ID to get followers for' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'followers', array(
			'user_id'      => array( 'type' => 'integer' ),
			'display_name' => array( 'type' => 'string' ),
			'created_at'   => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentCommunityPro\App\Models\Follow' ) ) {
				return array( 'followers' => array(), 'total' => 0, 'note' => 'Requires FluentCommunity Pro' );
			}

			$pagination = fluent_abilities_pagination( $input );
			$user_id = (int) $input['user_id'];

			$query = wpFluent()->table( 'fcom_followers' )
				->where( 'followed_id', $user_id )
				->orderBy( 'id', 'DESC' );

			$total = $query->count();
			$follows = $query->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();

			$items = array();
			foreach ( $follows as $f ) {
				$profile = \FluentCommunity\App\Models\XProfile::where( 'user_id', $f->follower_id )->first();
				$items[] = array(
					'user_id'      => (int) $f->follower_id,
					'display_name' => $profile ? $profile->display_name : null,
					'created_at'   => (string) $f->created_at,
				);
			}

			return array( 'followers' => $items, 'total' => $total, 'page' => $pagination['page'] );
		},
	) );

	$reg->read( 'fluent-community/list-following', array(
		'label'       => 'List Following',
		'description' => 'List members a user is following (requires FluentCommunity Pro).',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'user_id' ),
			'properties' => array_merge( array(
				'user_id' => array( 'type' => 'integer', 'description' => 'User ID' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'following', array(
			'user_id'      => array( 'type' => 'integer' ),
			'display_name' => array( 'type' => 'string' ),
			'created_at'   => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentCommunityPro\App\Models\Follow' ) ) {
				return array( 'following' => array(), 'total' => 0, 'note' => 'Requires FluentCommunity Pro' );
			}

			$pagination = fluent_abilities_pagination( $input );
			$user_id = (int) $input['user_id'];

			$query = wpFluent()->table( 'fcom_followers' )
				->where( 'follower_id', $user_id )
				->orderBy( 'id', 'DESC' );

			$total = $query->count();
			$follows = $query->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();

			$items = array();
			foreach ( $follows as $f ) {
				$profile = \FluentCommunity\App\Models\XProfile::where( 'user_id', $f->followed_id )->first();
				$items[] = array(
					'user_id'      => (int) $f->followed_id,
					'display_name' => $profile ? $profile->display_name : null,
					'created_at'   => (string) $f->created_at,
				);
			}

			return array( 'following' => $items, 'total' => $total, 'page' => $pagination['page'] );
		},
	) );

	// =========================================================================
	// SCHEDULED POSTS
	// =========================================================================

	$reg->read( 'fluent-community/list-scheduled-posts', array(
		'label'       => 'List Scheduled Posts',
		'description' => 'List scheduled (future) community posts.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'space_id' => array( 'type' => 'integer', 'description' => 'Filter by space ID' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'scheduled_posts', array(
			'id'           => array( 'type' => 'integer' ),
			'message'      => array( 'type' => 'string' ),
			'scheduled_at' => array( 'type' => 'string' ),
			'status'       => array( 'type' => 'string' ),
			'space_id'     => array( 'type' => 'integer' ),
			'created_at'   => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$query = \FluentCommunity\App\Models\Feed::where( 'status', 'scheduled' )
				->orderBy( 'scheduled_at', 'ASC' );

			if ( ! empty( $input['space_id'] ) ) {
				$query->where( 'space_id', (int) $input['space_id'] );
			}

			$total = $query->count();
			$feeds = $query->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();

			$items = array();
			foreach ( $feeds as $feed ) {
				$items[] = array(
					'id'           => $feed->id,
					'title'        => $feed->title,
					'space_id'     => $feed->space_id,
					'user_id'      => $feed->user_id,
					'type'         => $feed->type,
					'scheduled_at' => $feed->scheduled_at,
					'created_at'   => (string) $feed->created_at,
				);
			}

			return array( 'scheduled_posts' => $items, 'total' => $total, 'page' => $pagination['page'] );
		},
	) );

	$reg->write( 'fluent-community/create-scheduled-post', array(
		'label'       => 'Create Scheduled Post',
		'description' => 'Create a community post scheduled for future publication.',
		'category'    => 'fluent-community',
		'annotations' => array( 'idempotent' => false ),
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'space_id', 'message', 'scheduled_at' ),
			'properties' => array(
				'space_id'     => array( 'type' => 'integer', 'description' => 'Space ID to post in' ),
				'title'        => array( 'type' => 'string', 'description' => 'Post title (optional)' ),
				'message'      => array( 'type' => 'string', 'description' => 'Post content/message' ),
				'type'         => array( 'type' => 'string', 'description' => 'Post type: text, image, video (default: text)' ),
				'scheduled_at' => array( 'type' => 'string', 'description' => 'Scheduled datetime (Y-m-d H:i:s in UTC)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'           => array( 'type' => 'integer' ),
			'scheduled_at' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$space = \FluentCommunity\App\Models\Space::find( (int) $input['space_id'] );
			if ( ! $space ) {
				return fluent_abilities_error( 'not_found', 'Space not found' );
			}

			$scheduled_at = sanitize_text_field( $input['scheduled_at'] );
			if ( strtotime( $scheduled_at ) <= time() ) {
				return fluent_abilities_error( 'ability_invalid_input', 'scheduled_at must be in the future' );
			}

			$feed = \FluentCommunity\App\Models\Feed::create( array(
				'space_id'     => $space->id,
				'user_id'      => get_current_user_id(),
				'title'        => sanitize_text_field( $input['title'] ?? '' ),
				'message'      => wp_kses_post( $input['message'] ),
				'type'         => sanitize_text_field( $input['type'] ?? 'text' ),
				'status'       => 'scheduled',
				'scheduled_at' => $scheduled_at,
			));

			return array( 'success' => true, 'id' => $feed->id, 'scheduled_at' => $feed->scheduled_at );
		},
	) );

	$reg->read( 'fluent-community/get-scheduled-post', array(
		'label'       => 'Get Scheduled Post',
		'description' => 'Get a scheduled post by ID.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Feed ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'           => array( 'type' => 'integer' ),
			'message'      => array( 'type' => 'string' ),
			'scheduled_at' => array( 'type' => 'string' ),
			'status'       => array( 'type' => 'string' ),
			'title'        => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$feed = \FluentCommunity\App\Models\Feed::where( 'status', 'scheduled' )->find( (int) $input['id'] );
			if ( ! $feed ) {
				return fluent_abilities_error( 'not_found', 'Scheduled post not found' );
			}

			return array(
				'id'           => $feed->id,
				'title'        => $feed->title,
				'message'      => $feed->message,
				'space_id'     => $feed->space_id,
				'user_id'      => $feed->user_id,
				'type'         => $feed->type,
				'scheduled_at' => $feed->scheduled_at,
				'created_at'   => (string) $feed->created_at,
			);
		},
	) );

	$reg->write( 'fluent-community/update-scheduled-post', array(
		'label'       => 'Update Scheduled Post',
		'description' => 'Update a scheduled post (title, message, scheduled time).',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'           => array( 'type' => 'integer', 'description' => 'Feed ID' ),
				'title'        => array( 'type' => 'string', 'description' => 'New title' ),
				'message'      => array( 'type' => 'string', 'description' => 'New content' ),
				'scheduled_at' => array( 'type' => 'string', 'description' => 'New scheduled datetime (Y-m-d H:i:s in UTC)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'           => array( 'type' => 'integer' ),
			'scheduled_at' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$feed = \FluentCommunity\App\Models\Feed::where( 'status', 'scheduled' )->find( (int) $input['id'] );
			if ( ! $feed ) {
				return fluent_abilities_error( 'not_found', 'Scheduled post not found' );
			}

			// Ownership check: only the post author or an admin may edit.
			if ( (int) $feed->user_id !== get_current_user_id() && ! fluent_abilities_user_can( 'community', 'admin' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You may only edit your own scheduled posts' );
			}

			if ( isset( $input['title'] ) ) {
				$feed->title = sanitize_text_field( $input['title'] );
			}
			if ( isset( $input['message'] ) ) {
				$feed->message = wp_kses_post( $input['message'] );
			}
			if ( isset( $input['scheduled_at'] ) ) {
				$scheduled_at = sanitize_text_field( $input['scheduled_at'] );
				if ( strtotime( $scheduled_at ) <= time() ) {
					return fluent_abilities_error( 'ability_invalid_input', 'scheduled_at must be in the future' );
				}
				$feed->scheduled_at = $scheduled_at;
			}

			$feed->save();

			return array( 'success' => true, 'id' => $feed->id, 'scheduled_at' => $feed->scheduled_at );
		},
	) );

	$reg->write( 'fluent-community/publish-scheduled-post', array(
		'label'       => 'Publish Scheduled Post',
		'description' => 'Immediately publish a scheduled post.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Feed ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'     => array( 'type' => 'integer' ),
			'status' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$feed = \FluentCommunity\App\Models\Feed::where( 'status', 'scheduled' )->find( (int) $input['id'] );
			if ( ! $feed ) {
				return fluent_abilities_error( 'not_found', 'Scheduled post not found' );
			}

			// Ownership check: only the post author or an admin may publish.
			if ( (int) $feed->user_id !== get_current_user_id() && ! fluent_abilities_user_can( 'community', 'admin' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'You may only publish your own scheduled posts' );
			}

			$feed->status = 'published';
			$feed->scheduled_at = null;
			$feed->created_at = current_time( 'mysql', true );
			$feed->save();

			return array( 'success' => true, 'id' => $feed->id, 'status' => 'published' );
		},
	) );

	// =========================================================================
	// MEDIA
	// =========================================================================

	$reg->read( 'fluent-community/list-media', array(
		'label'       => 'List Community Media',
		'description' => 'List media files in the community media archive.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'user_id'    => array( 'type' => 'integer', 'description' => 'Filter by uploader user ID' ),
				'media_type' => array( 'type' => 'string', 'description' => 'Filter by type: image, video, audio, document' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'media', array(
			'id'          => array( 'type' => 'integer' ),
			'title'       => array( 'type' => 'string' ),
			'media_type'  => array( 'type' => 'string' ),
			'url'         => array( 'type' => 'string' ),
			'uploaded_at' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentCommunity\App\Models\Media' ) ) {
				return array( 'media' => array(), 'total' => 0, 'note' => 'Media model not available' );
			}

			$pagination = fluent_abilities_pagination( $input );
			$query = \FluentCommunity\App\Models\Media::orderBy( 'id', 'DESC' );

			if ( ! empty( $input['user_id'] ) ) {
				$query->where( 'user_id', (int) $input['user_id'] );
			}
			if ( ! empty( $input['media_type'] ) ) {
				$query->where( 'media_type', sanitize_text_field( $input['media_type'] ) );
			}

			$total = $query->count();
			$media = $query->offset( $pagination['offset'] )->limit( $pagination['per_page'] )->get();

			$items = array();
			foreach ( $media as $m ) {
				$items[] = array(
					'id'         => $m->id,
					'user_id'    => $m->user_id,
					'media_type' => $m->media_type ?? $m->type ?? null,
					'url'        => $m->public_url ?? $m->media_url ?? $m->url ?? null,
					'file_name'  => $m->media_key ?? null,
					'file_size'  => $m->file_size ?? null,
					'created_at' => (string) $m->created_at,
				);
			}

			return array( 'media' => $items, 'total' => $total, 'page' => $pagination['page'] );
		},
	) );

	$reg->read( 'fluent-community/get-media', array(
		'label'       => 'Get Community Media',
		'description' => 'Get a media item by ID.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Media ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'          => array( 'type' => 'integer' ),
			'title'       => array( 'type' => 'string' ),
			'media_type'  => array( 'type' => 'string' ),
			'url'         => array( 'type' => 'string' ),
			'uploaded_at' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentCommunity\App\Models\Media' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'Media model not available' );
			}

			$media = \FluentCommunity\App\Models\Media::find( (int) $input['id'] );
			if ( ! $media ) {
				return fluent_abilities_error( 'not_found', 'Media not found' );
			}

			return array(
				'id'         => $media->id,
				'user_id'    => $media->user_id,
				'media_type' => $media->media_type ?? $media->type ?? null,
				'url'        => $media->public_url ?? $media->media_url ?? $media->url ?? null,
				'file_name'  => $media->media_key ?? null,
				'file_size'  => $media->file_size ?? null,
				'meta'       => fluent_abilities_safe_array( $media->meta ),
				'created_at' => (string) $media->created_at,
			);
		},
	) );

	$reg->write( 'fluent-community/upload-media-from-url', array(
		'label'       => 'Upload Media from URL',
		'description' => 'Download and add an external image/file to the community media archive.',
		'category'    => 'fluent-community',
		'annotations' => array( 'idempotent' => false ),
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'url' ),
			'properties' => array(
				'url'        => array( 'type' => 'string', 'description' => 'Source URL of the file to import' ),
				'title'      => array( 'type' => 'string', 'description' => 'Media title/filename override' ),
				'media_type' => array( 'type' => 'string', 'description' => 'Type: image, video, audio, document (auto-detected if omitted)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentCommunity\App\Models\Media' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'Media model not available' );
			}

			// SSRF protection: validate URL scheme, host, and resolved IP.
			$url = fluent_abilities_validate_url( $input['url'] );
			if ( is_wp_error( $url ) ) {
				return $url;
			}

			// Download the file using WordPress HTTP API.
			require_once ABSPATH . 'wp-admin/includes/file.php';
			$tmp = download_url( $url, 60 );
			if ( is_wp_error( $tmp ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Failed to download: ' . $tmp->get_error_message() );
			}

			// Enforce 5 MB size limit.
			if ( filesize( $tmp ) > 5 * 1024 * 1024 ) {
				@unlink( $tmp );
				return fluent_abilities_error( 'ability_invalid_input', 'File exceeds the 5 MB size limit.' );
			}

			$file_name = ! empty( $input['title'] )
				? sanitize_file_name( $input['title'] )
				: sanitize_file_name( basename( wp_parse_url( $url, PHP_URL_PATH ) ) );

			// Auto-detect media type from extension.
			$ext = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
			$type_map = array(
				'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'gif' => 'image', 'webp' => 'image',
				'mp4' => 'video', 'webm' => 'video', 'mov' => 'video',
				'mp3' => 'audio', 'wav' => 'audio', 'ogg' => 'audio',
			);
			$media_type = ! empty( $input['media_type'] )
				? sanitize_text_field( $input['media_type'] )
				: ( $type_map[ $ext ] ?? 'document' );

			// Move to FluentCommunity uploads directory.
			$upload_dir = wp_upload_dir();
			$fcom_dir   = $upload_dir['basedir'] . '/fluent-community/media/' . date( 'Y/m' );
			wp_mkdir_p( $fcom_dir );

			$dest = $fcom_dir . '/' . wp_unique_filename( $fcom_dir, $file_name );
			if ( ! @rename( $tmp, $dest ) ) {
				@copy( $tmp, $dest );
				@unlink( $tmp );
			}

			$relative_url = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $dest );

			$media = \FluentCommunity\App\Models\Media::create( array(
				'user_id'    => get_current_user_id(),
				'media_type' => $media_type,
				'media_url'  => $relative_url,
				'file_name'  => basename( $dest ),
				'file_size'  => filesize( $dest ),
			));

			return array( 'success' => true, 'id' => $media->id, 'url' => $relative_url, 'file_name' => basename( $dest ) );
		},
	) );

	$reg->write( 'fluent-community/update-media', array(
		'label'       => 'Update Media',
		'description' => 'Update media metadata (title, type).',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'         => array( 'type' => 'integer', 'description' => 'Media ID' ),
				'title'      => array( 'type' => 'string', 'description' => 'New title/filename' ),
				'media_type' => array( 'type' => 'string', 'description' => 'Media type: image, video, audio, document' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentCommunity\App\Models\Media' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'Media model not available' );
			}

			$media = \FluentCommunity\App\Models\Media::find( (int) $input['id'] );
			if ( ! $media ) {
				return fluent_abilities_error( 'not_found', 'Media not found' );
			}

			if ( isset( $input['title'] ) ) {
				$media->media_key = sanitize_text_field( $input['title'] );
			}
			if ( isset( $input['media_type'] ) ) {
				$media->media_type = sanitize_text_field( $input['media_type'] );
			}

			$media->save();

			return array( 'success' => true, 'id' => $media->id );
		},
	) );

	// ===== COMMUNITY — DELETE =====

	$reg->delete( 'fluent-community/delete-space', array(
		'label'       => 'Delete Space',
		'description' => 'Delete a FluentCommunity space and all its content.',
		'category'    => 'fluent-community',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'space_id' ),
			'properties' => array(
				'space_id' => array( 'type' => 'integer', 'description' => 'Space ID to delete' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'message'  => array( 'type' => 'string' ),
			'space_id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$space = \FluentCommunity\App\Models\Space::find( (int) $input['space_id'] );
			if ( ! $space ) {
				return fluent_abilities_error( 'not_found', 'Space not found' );
			}

			// Don't delete courses via this ability — use delete-course instead.
			if ( $space->type === 'course' ) {
				return fluent_abilities_error( 'ability_invalid_input', 'This is a course space. Use fluent-community/delete-course instead.' );
			}

			$space_id = (int) $space->id;
			$space->delete();

			return array(
				'success'  => true,
				'message'  => 'Space deleted',
				'space_id' => $space_id,
			);
		},
	) );

	$reg->delete( 'fluent-community/delete-course', array(
		'label'       => 'Delete Course',
		'description' => 'Delete a FluentCommunity course (a space of type "course") and all its lessons.',
		'category'    => 'fluent-community',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'course_id' ),
			'properties' => array(
				'course_id' => array( 'type' => 'integer', 'description' => 'Course (space) ID to delete' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'message'   => array( 'type' => 'string' ),
			'course_id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$course = \FluentCommunity\App\Models\Space::where( 'type', 'course' )->find( (int) $input['course_id'] );
			if ( ! $course ) {
				return fluent_abilities_error( 'not_found', 'Course not found' );
			}

			$course_id = (int) $course->id;
			$course->delete();

			return array(
				'success'   => true,
				'message'   => 'Course deleted',
				'course_id' => $course_id,
			);
		},
	) );

	// ===== COMMUNITY — CRUD COMPLETENESS =====

	$reg->write( 'fluent-community/update-feed', array(
		'label'       => 'Update Community Post',
		'description' => 'Update a community feed/post title, message, type, status, or privacy.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'      => array( 'type' => 'integer', 'description' => 'Feed ID' ),
				'title'   => array( 'type' => 'string', 'description' => 'New post title' ),
				'message' => array( 'type' => 'string', 'description' => 'New post content' ),
				'type'    => array( 'type' => 'string', 'description' => 'Post type: text, image, video' ),
				'status'  => array( 'type' => 'string', 'description' => 'Status: published, draft' ),
				'privacy' => array( 'type' => 'string', 'description' => 'Privacy: public, private, space_only' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$feed = \FluentCommunity\App\Models\Feed::find( (int) $input['id'] );
			if ( ! $feed ) {
				return fluent_abilities_error( 'not_found', 'Feed not found' );
			}
			if ( isset( $input['title'] ) )   $feed->title   = sanitize_text_field( $input['title'] );
			if ( isset( $input['message'] ) ) $feed->message = wp_kses_post( $input['message'] );
			if ( isset( $input['type'] ) )    $feed->type    = sanitize_text_field( $input['type'] );
			if ( isset( $input['status'] ) )  $feed->status  = sanitize_text_field( $input['status'] );
			if ( isset( $input['privacy'] ) ) $feed->privacy = sanitize_text_field( $input['privacy'] );
			$feed->save();
			return array( 'success' => true, 'id' => $feed->id );
		},
	) );

	$reg->delete( 'fluent-community/delete-feed', array(
		'label'       => 'Delete Community Post',
		'description' => 'Permanently delete a community feed/post and its comments.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Feed ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			$feed = \FluentCommunity\App\Models\Feed::find( (int) $input['id'] );
			if ( ! $feed ) {
				return fluent_abilities_error( 'not_found', 'Feed not found' );
			}
			$id = $feed->id;
			// Delete comments first.
			\FluentCommunity\App\Models\Comment::where( 'post_id', $id )->delete();
			$feed->delete();
			return array( 'success' => true, 'id' => $id );
		},
	) );

	$reg->write( 'fluent-community/create-course', array(
		'label'       => 'Create Course',
		'description' => 'Create a new course in FluentCommunity. Source: FluentCommunity\\Modules\\Course\\Model\\Course::create(). The Course model\'s static $type=\'course\' ensures the correct space type is persisted; do not pass type manually.',
		'category'    => 'fluent-community',
		'capability'  => 'manage_options',
		'annotations' => array( 'idempotent' => false ),
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'title' ),
			'properties' => array(
				'title'       => array( 'type' => 'string', 'description' => 'Course title' ),
				'slug'        => array( 'type' => 'string', 'description' => 'Course slug (auto-generated if omitted)' ),
				'description' => array( 'type' => 'string', 'description' => 'Course description' ),
				'privacy'     => array( 'type' => 'string', 'description' => 'Privacy: public, private, secret (default: public)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
			'slug'  => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
				if ( ! class_exists( '\\FluentCommunity\\Modules\\Course\\Model\\Course' ) ) {
				return new WP_Error(
					'vendor_helper_unavailable',
					'FluentCommunity\\Modules\\Course\\Model\\Course is not available. The course module must be active for this ability.'
				);
			}

			$slug = ! empty( $input['slug'] ) ? sanitize_title( $input['slug'] ) : sanitize_title( $input['title'] );

			// Slug uniqueness check uses Space (the shared fcom_spaces table) to
			// catch conflicts with community/course/sidebar_link rows, since slug
			// is unique across all space types.
			if ( \FluentCommunity\App\Models\Space::withoutGlobalScopes()->where( 'slug', $slug )->exists() ) {
				return fluent_abilities_error( 'ability_invalid_input', "A space with slug '{$slug}' already exists." );
			}

			$course = \FluentCommunity\Modules\Course\Model\Course::create( array(
				'title'       => sanitize_text_field( $input['title'] ),
				'slug'        => $slug,
				'description' => wp_kses_post( $input['description'] ?? '' ),
				'privacy'     => sanitize_text_field( $input['privacy'] ?? 'public' ),
				'created_by'  => get_current_user_id(),
			) );

			return array( 'success' => true, 'id' => $course->id, 'title' => $course->title, 'slug' => $course->slug );
		},
	) );

	$reg->write( 'fluent-community/update-course', array(
		'label'       => 'Update Course',
		'description' => 'Update a course title, description, or privacy. Source: FluentCommunity\\Modules\\Course\\Model\\Course::find().',
		'category'    => 'fluent-community',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'course_id' ),
			'properties' => array(
				'course_id'   => array( 'type' => 'integer', 'description' => 'Course (space) ID' ),
				'title'       => array( 'type' => 'string', 'description' => 'New course title' ),
				'description' => array( 'type' => 'string', 'description' => 'New course description' ),
				'privacy'     => array( 'type' => 'string', 'description' => 'Privacy: public, private, secret' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\\FluentCommunity\\Modules\\Course\\Model\\Course' ) ) {
				return new WP_Error(
					'vendor_helper_unavailable',
					'FluentCommunity\\Modules\\Course\\Model\\Course is not available. The course module must be active for this ability.'
				);
			}
			$course = \FluentCommunity\Modules\Course\Model\Course::find( (int) $input['course_id'] );
			if ( ! $course ) {
				return fluent_abilities_error( 'not_found', 'Course not found' );
			}
			if ( isset( $input['title'] ) )       $course->title       = sanitize_text_field( $input['title'] );
			if ( isset( $input['description'] ) ) $course->description = wp_kses_post( $input['description'] );
			if ( isset( $input['privacy'] ) )     $course->privacy     = sanitize_text_field( $input['privacy'] );
			$course->save();
			return array( 'success' => true, 'id' => $course->id );
		},
	) );

	$reg->delete( 'fluent-community/delete-lesson', array(
		'label'       => 'Delete Lesson',
		'description' => 'Permanently delete a course lesson by ID.',
		'category'    => 'fluent-community',
		'capability'  => 'manage_options',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Lesson ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentCommunity\App\Models\CourseLesson' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'CourseLesson model not available' );
			}
			$lesson = \FluentCommunity\App\Models\CourseLesson::find( (int) $input['id'] );
			if ( ! $lesson ) {
				return fluent_abilities_error( 'not_found', 'Lesson not found' );
			}
			$id = $lesson->id;
			$lesson->delete();
			return array( 'success' => true, 'id' => $id );
		},
	) );

	$reg->delete( 'fluent-community/delete-scheduled-post', array(
		'label'       => 'Delete Scheduled Post',
		'description' => 'Permanently delete a scheduled community post.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Scheduled post (feed) ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			$feed = \FluentCommunity\App\Models\Feed::find( (int) $input['id'] );
			if ( ! $feed ) {
				return fluent_abilities_error( 'not_found', 'Post not found' );
			}
			if ( $feed->status !== 'scheduled' ) {
				return fluent_abilities_error( 'ability_invalid_input', 'This post is not scheduled. Use fluent-community/delete-feed for published posts.' );
			}
			$id = $feed->id;
			$feed->delete();
			return array( 'success' => true, 'id' => $id );
		},
	) );

	$reg->delete( 'fluent-community/delete-media', array(
		'label'       => 'Delete Community Media',
		'description' => 'Permanently delete a community media item by ID.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Media ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			if ( ! class_exists( '\FluentCommunity\App\Models\Media' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'Media model not available' );
			}
			$media = \FluentCommunity\App\Models\Media::find( (int) $input['id'] );
			if ( ! $media ) {
				return fluent_abilities_error( 'not_found', 'Media not found' );
			}
			$id = $media->id;
			$media->delete();
			return array( 'success' => true, 'id' => $id );
		},
	) );

	$reg->write( 'fluent-community/update-comment', array(
		'label'       => 'Update Feed Comment',
		'description' => 'Update a community comment message.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id', 'message' ),
			'properties' => array(
				'id'      => array( 'type' => 'integer', 'description' => 'Comment ID' ),
				'message' => array( 'type' => 'string', 'description' => 'New comment content' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$comment = \FluentCommunity\App\Models\Comment::find( (int) $input['id'] );
			if ( ! $comment ) {
				return fluent_abilities_error( 'not_found', 'Comment not found' );
			}
			$comment->message = wp_kses_post( $input['message'] );
			$comment->save();
			return array( 'success' => true, 'id' => $comment->id );
		},
	) );

	$reg->delete( 'fluent-community/delete-comment', array(
		'label'       => 'Delete Feed Comment',
		'description' => 'Permanently delete a community comment by ID.',
		'category'    => 'fluent-community',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Comment ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			$comment = \FluentCommunity\App\Models\Comment::find( (int) $input['id'] );
			if ( ! $comment ) {
				return fluent_abilities_error( 'not_found', 'Comment not found' );
			}
			$id = $comment->id;
			$comment->delete();
			return array( 'success' => true, 'id' => $id );
		},
	) );

	$count = 56;
	error_log( "Abilities for Fluent: Registered {$count} Community abilities" );

}, 100 );
