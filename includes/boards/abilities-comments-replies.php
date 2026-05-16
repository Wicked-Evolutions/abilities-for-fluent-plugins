<?php
/**
 * Fluent Boards — Comment Replies + Privacy (Research §4.5)
 *
 * 6 abilities (free).
 *
 * Comment privacy: 'public' or 'private'. fbs_comments.type can be 'comment',
 * 'reply' (parent_id set), or 'note' (not exposed in v2.0.0 — see §7.Q4).
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

// =========================================================================
// §4.5.1 — create-task-comment-reply (idempotent:false)
// =========================================================================
$reg->write( 'fluent-boards/create-task-comment-reply', array(
	'label'       => 'Create Task Comment Reply',
	'description' => 'Reply to an existing task comment. Creates a new fbs_comments row with parent_id and type=reply.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'task_id', 'parent_comment_id', 'description' ),
		'properties' => array(
			'task_id'           => array( 'type' => 'integer' ),
			'parent_comment_id' => array( 'type' => 'integer' ),
			'description'       => array( 'type' => 'string' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'reply_id' => array( 'type' => 'integer' ),
		'task_id'  => array( 'type' => 'integer' ),
	) ),
	'annotations' => array( 'idempotent' => false ),
	'callback'    => function( $input ) {
		$task_id   = (int) $input['task_id'];
		$parent_id = (int) $input['parent_comment_id'];
		$desc      = wp_kses_post( $input['description'] ?? '' );
		if ( '' === trim( $desc ) ) {
			return fluent_abilities_error( 'ability_invalid_input', 'description is required.' );
		}
		$parent = wpFluent()->table( 'fbs_comments' )->where( 'id', $parent_id )->where( 'task_id', $task_id )->first();
		if ( ! $parent ) {
			return fluent_abilities_error( 'not_found', 'Parent comment not found on this task.' );
		}
		$now    = current_time( 'mysql' );
		$new_id = wpFluent()->table( 'fbs_comments' )->insertGetId( array(
			'task_id'     => $task_id,
			'parent_id'   => $parent_id,
			'description' => $desc,
			'type'        => 'reply',
			'privacy'     => $parent->privacy ?? 'public',
			'created_by'  => (int) get_current_user_id(),
			'created_at'  => $now,
			'updated_at'  => $now,
		) );
		return array( 'success' => true, 'reply_id' => (int) $new_id, 'task_id' => $task_id );
	},
) );

// =========================================================================
// §4.5.2 — update-task-comment-reply
// =========================================================================
$reg->write( 'fluent-boards/update-task-comment-reply', array(
	'label'       => 'Update Task Comment Reply',
	'description' => 'Edit a reply\'s description text.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'reply_id', 'description' ),
		'properties' => array(
			'reply_id'    => array( 'type' => 'integer' ),
			'description' => array( 'type' => 'string' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'reply_id' => array( 'type' => 'integer' ) ) ),
	'callback'     => function( $input ) {
		$reply_id = (int) $input['reply_id'];
		$reply    = wpFluent()->table( 'fbs_comments' )->where( 'id', $reply_id )->where( 'type', 'reply' )->first();
		if ( ! $reply ) {
			return fluent_abilities_error( 'not_found', 'Reply not found.' );
		}
		wpFluent()->table( 'fbs_comments' )->where( 'id', $reply_id )->update( array(
			'description' => wp_kses_post( $input['description'] ?? '' ),
			'updated_at'  => current_time( 'mysql' ),
		) );
		return array( 'success' => true, 'reply_id' => $reply_id );
	},
) );

// =========================================================================
// §4.5.3 — delete-task-comment-reply (idempotent:false)
// =========================================================================
$reg->delete( 'fluent-boards/delete-task-comment-reply', array(
	'label'       => 'Delete Task Comment Reply',
	'description' => 'Permanently delete a reply. Not idempotent.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'reply_id' ),
		'properties' => array(
			'reply_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array( 'reply_id' => array( 'type' => 'integer' ) ) ),
	'annotations'  => array( 'idempotent' => false ),
	'callback'     => function( $input ) {
		$reply_id = (int) $input['reply_id'];
		$reply    = wpFluent()->table( 'fbs_comments' )->where( 'id', $reply_id )->where( 'type', 'reply' )->first();
		if ( ! $reply ) {
			return fluent_abilities_error( 'not_found', 'Reply not found.' );
		}
		wpFluent()->table( 'fbs_comments' )->where( 'id', $reply_id )->delete();
		return array( 'success' => true, 'reply_id' => $reply_id );
	},
) );

// =========================================================================
// §4.5.4 — update-comment-privacy
// =========================================================================
$reg->write( 'fluent-boards/update-comment-privacy', array(
	'label'       => 'Update Comment Privacy',
	'description' => 'Set a comment\'s privacy flag to public or private. Private comments are hidden from non-managers.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'comment_id', 'privacy' ),
		'properties' => array(
			'comment_id' => array( 'type' => 'integer' ),
			'privacy'    => array( 'type' => 'string', 'enum' => array( 'public', 'private' ) ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'comment_id' => array( 'type' => 'integer' ),
		'privacy'    => array( 'type' => 'string' ),
	) ),
	'callback' => function( $input ) {
		$comment_id = (int) $input['comment_id'];
		$privacy    = sanitize_text_field( $input['privacy'] ?? '' );
		if ( ! in_array( $privacy, array( 'public', 'private' ), true ) ) {
			return fluent_abilities_error( 'ability_invalid_input', 'privacy must be public or private.' );
		}
		if ( ! wpFluent()->table( 'fbs_comments' )->where( 'id', $comment_id )->first() ) {
			return fluent_abilities_error( 'not_found', 'Comment not found.' );
		}
		wpFluent()->table( 'fbs_comments' )->where( 'id', $comment_id )->update( array(
			'privacy'    => $privacy,
			'updated_at' => current_time( 'mysql' ),
		) );
		return array( 'success' => true, 'comment_id' => $comment_id, 'privacy' => $privacy );
	},
) );

// =========================================================================
// §4.5.5 — list-comments-and-activities
// =========================================================================
$reg->read( 'fluent-boards/list-comments-and-activities', array(
	'label'       => 'List Comments And Activities',
	'description' => 'Return an interleaved timeline of comments (with replies) and activity-log entries for a task, ordered chronologically.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'task_id' ),
		'properties' => array_merge( array(
			'task_id' => array( 'type' => 'integer' ),
		), fluent_abilities_pagination_schema() ),
	),
	'output_schema' => fluent_abilities_schema_list_output( 'items', array(
		'kind'        => array( 'type' => 'string', 'enum' => array( 'comment', 'reply', 'activity' ) ),
		'id'          => array( 'type' => 'integer' ),
		'parent_id'   => array( 'type' => array( 'integer', 'null' ) ),
		'description' => array( 'type' => array( 'string', 'null' ) ),
		'privacy'     => array( 'type' => array( 'string', 'null' ) ),
		'action'      => array( 'type' => array( 'string', 'null' ) ),
		'created_by'  => array( 'type' => array( 'integer', 'null' ) ),
		'created_at'  => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$task_id    = (int) $input['task_id'];
		$pagination = fluent_abilities_pagination( $input, 50 );
		if ( ! wpFluent()->table( 'fbs_tasks' )->where( 'id', $task_id )->first() ) {
			return fluent_abilities_error( 'not_found', 'Task not found.' );
		}
		$comments = wpFluent()->table( 'fbs_comments' )->where( 'task_id', $task_id )->get();
		$acts     = wpFluent()->table( 'fbs_activities' )->where( 'object_id', $task_id )->where( 'object_type', 'task_activity' )->get();
		$timeline = array();
		foreach ( $comments as $c ) {
			$timeline[] = array(
				'kind'        => ( ( $c->type ?? 'comment' ) === 'reply' ) ? 'reply' : 'comment',
				'id'          => (int) $c->id,
				'parent_id'   => $c->parent_id ? (int) $c->parent_id : null,
				'description' => $c->description ?? null,
				'privacy'     => $c->privacy ?? null,
				'action'      => null,
				'created_by'  => $c->created_by ? (int) $c->created_by : null,
				'created_at'  => $c->created_at ?? null,
			);
		}
		foreach ( $acts as $a ) {
			$timeline[] = array(
				'kind'        => 'activity',
				'id'          => (int) $a->id,
				'parent_id'   => null,
				'description' => $a->description ?? null,
				'privacy'     => null,
				'action'      => $a->action ?? null,
				'created_by'  => $a->created_by ? (int) $a->created_by : null,
				'created_at'  => $a->created_at ?? null,
			);
		}
		usort( $timeline, function( $a, $b ) {
			return strcmp( (string) ( $a['created_at'] ?? '' ), (string) ( $b['created_at'] ?? '' ) );
		} );
		$total = count( $timeline );
		$items = array_slice( $timeline, $pagination['offset'], $pagination['per_page'] );
		return array( 'items' => array_values( $items ), 'total' => $total, 'page' => $pagination['page'], 'per_page' => $pagination['per_page'] );
	},
) );

// =========================================================================
// §4.5.6 — upload-comment-image
// =========================================================================
$reg->write( 'fluent-boards/upload-comment-image', array(
	'label'       => 'Upload Comment Image',
	'description' => 'Upload an image and return its URL for embedding inside a comment\'s markdown description. Provide at least one of `attachment_id` or `image_url` (both may be supplied — `attachment_id` takes precedence; the handler rejects only when NEITHER resolves). Schema declares this via `anyOf` (P5 factually-corrective per installed-handler precedence chain, not exactly-one).',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'task_id' ),
		'properties' => array(
			'task_id'       => array( 'type' => 'integer' ),
			'attachment_id' => array( 'type' => 'integer' ),
			'image_url'     => array( 'type' => 'string' ),
		),
		// P5 factually-corrective (NOT oneOf): handler precedence
		// if($attachment_id) elseif($image_url) — both accepted, only
		// neither rejected. anyOf = "at least one".
		'anyOf'      => array(
			array( 'required' => array( 'attachment_id' ) ),
			array( 'required' => array( 'image_url' ) ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'task_id'       => array( 'type' => 'integer' ),
		'image_url'     => array( 'type' => 'string' ),
		'attachment_id' => array( 'type' => array( 'integer', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$task_id = (int) $input['task_id'];
		if ( ! wpFluent()->table( 'fbs_tasks' )->where( 'id', $task_id )->first() ) {
			return fluent_abilities_error( 'not_found', 'Task not found.' );
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
		return array( 'success' => true, 'task_id' => $task_id, 'image_url' => $image_url, 'attachment_id' => $attachment_id ?: null );
	},
) );
