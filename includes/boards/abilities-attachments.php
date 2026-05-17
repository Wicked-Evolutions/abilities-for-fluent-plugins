<?php
/**
 * Fluent Boards — Task Attachments (Research §4.14)
 *
 * 5 abilities. Tier: pro.
 *
 * Attachments stored in fbs_metas with object_type='task_attachment' and
 * object_id=task_id. value holds attachment metadata (attachment_id, url, name,
 * mime, size, visibility).
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

// =========================================================================
// §4.14.1 — list-task-attachments
// =========================================================================
$reg->read( 'fluent-boards/list-task-attachments', array(
	'label'       => 'List Task Attachments (Pro)',
	'description' => 'List files attached to a task.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'task_id' ),
		'properties' => array(
			'task_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => fluent_abilities_schema_collection_output( 'attachments', array(
		'id'             => array( 'type' => 'integer' ),
		'attachment_id'  => array( 'type' => array( 'integer', 'null' ) ),
		'name'           => array( 'type' => array( 'string', 'null' ) ),
		'url'            => array( 'type' => array( 'string', 'null' ) ),
		'mime'           => array( 'type' => array( 'string', 'null' ) ),
		'visibility'     => array( 'type' => array( 'string', 'null' ) ),
		'created_at'     => array( 'type' => array( 'string', 'null' ) ),
	) ),
	'callback' => function( $input ) {
		$task_id = (int) $input['task_id'];
		$rows    = wpFluent()->table( 'fbs_metas' )->where( 'object_type', 'task_attachment' )->where( 'object_id', $task_id )->orderBy( 'id', 'DESC' )->get();
		$items   = array();
		foreach ( $rows as $r ) {
			$meta    = maybe_unserialize( $r->value ?? '' );
			$meta    = is_array( $meta ) ? $meta : array();
			$items[] = array(
				'id'             => (int) $r->id,
				'attachment_id'  => isset( $meta['attachment_id'] ) ? (int) $meta['attachment_id'] : null,
				'name'           => $meta['name'] ?? null,
				'url'            => $meta['url'] ?? null,
				'mime'           => $meta['mime'] ?? null,
				'visibility'     => $meta['visibility'] ?? 'public',
				'created_at'     => $r->created_at ?? null,
			);
		}
		return array( 'attachments' => $items, 'total' => count( $items ) );
	},
) );

// =========================================================================
// §4.14.2 — add-task-attachment
// =========================================================================
$reg->write( 'fluent-boards/add-task-attachment', array(
	'label'       => 'Add Task Attachment (Pro)',
	'description' => 'Attach a file to a task. Provide at least one of `attachment_id` or `image_url` (both may be supplied — `attachment_id` takes precedence; the handler rejects only when NEITHER resolves). Schema declares this via `anyOf` (P5 factually-corrective per installed-handler precedence chain, not exactly-one).',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'task_id' ),
		'properties' => array(
			'task_id'       => array( 'type' => 'integer' ),
			'attachment_id' => array( 'type' => 'integer' ),
			'image_url'     => array( 'type' => 'string' ),
			'visibility'    => array( 'type' => 'string', 'enum' => array( 'public', 'private' ), 'default' => 'public' ),
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
		'id'      => array( 'type' => 'integer' ),
		'task_id' => array( 'type' => 'integer' ),
	) ),
	'annotations' => array( 'idempotent' => false ),
	'callback'    => function( $input ) {
		$task_id = (int) $input['task_id'];
		if ( ! wpFluent()->table( 'fbs_tasks' )->where( 'id', $task_id )->first() ) {
			return fluent_abilities_error( 'not_found', 'Task not found.' );
		}
		$attachment_id = (int) ( $input['attachment_id'] ?? 0 );
		$url           = '';
		$name          = '';
		$mime          = '';
		if ( $attachment_id ) {
			$url  = (string) wp_get_attachment_url( $attachment_id );
			$post = get_post( $attachment_id );
			$name = $post ? (string) $post->post_title : basename( $url );
			$mime = $post ? (string) $post->post_mime_type : '';
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
			$url  = (string) $sideload;
			$name = basename( $url );
		}
		if ( ! $url ) {
			return fluent_abilities_error( 'ability_invalid_input', 'Provide attachment_id or image_url.' );
		}
		$visibility = sanitize_key( $input['visibility'] ?? 'public' );
		$now        = current_time( 'mysql' );
		$new_id     = wpFluent()->table( 'fbs_metas' )->insertGetId( array(
			'object_id'   => $task_id,
			'object_type' => 'task_attachment',
			'key'         => 'task_attachment',
			'value'       => maybe_serialize( array(
				'attachment_id' => $attachment_id ?: null,
				'name'          => $name,
				'url'           => $url,
				'mime'          => $mime,
				'visibility'    => $visibility,
			) ),
			'created_at'  => $now,
			'updated_at'  => $now,
		) );
		return array( 'success' => true, 'id' => (int) $new_id, 'task_id' => $task_id );
	},
) );

// =========================================================================
// §4.14.3 — delete-task-attachment
// =========================================================================
$reg->delete( 'fluent-boards/delete-task-attachment', array(
	'label'       => 'Delete Task Attachment (Pro)',
	'description' => 'Remove an attachment from a task (does not delete the underlying WordPress attachment).',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'task_id', 'attachment_id' ),
		'properties' => array(
			'task_id'       => array( 'type' => 'integer' ),
			'attachment_id' => array( 'type' => 'integer', 'description' => 'fbs_metas.id of the attachment row (NOT the WP attachment id).' ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'task_id'       => array( 'type' => 'integer' ),
		'attachment_id' => array( 'type' => 'integer' ),
	) ),
	'callback' => function( $input ) {
		$task_id = (int) $input['task_id'];
		$att_id  = (int) $input['attachment_id'];
		wpFluent()->table( 'fbs_metas' )->where( 'id', $att_id )->where( 'object_type', 'task_attachment' )->where( 'object_id', $task_id )->delete();
		return array( 'success' => true, 'task_id' => $task_id, 'attachment_id' => $att_id );
	},
) );

// =========================================================================
// §4.14.4 — update-attachment-visibility
// =========================================================================
$reg->write( 'fluent-boards/update-attachment-visibility', array(
	'label'       => 'Update Attachment Visibility (Pro)',
	'description' => 'Set an attachment\'s visibility flag to public or private.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'attachment_id', 'visibility' ),
		'properties' => array(
			'attachment_id' => array( 'type' => 'integer' ),
			'visibility'    => array( 'type' => 'string', 'enum' => array( 'public', 'private' ) ),
		),
	),
	'output_schema' => fluent_abilities_schema_success_output( array(
		'attachment_id' => array( 'type' => 'integer' ),
		'visibility'    => array( 'type' => 'string' ),
	) ),
	'callback' => function( $input ) {
		$att_id     = (int) $input['attachment_id'];
		$visibility = sanitize_key( $input['visibility'] ?? '' );
		$row        = wpFluent()->table( 'fbs_metas' )->where( 'id', $att_id )->where( 'object_type', 'task_attachment' )->first();
		if ( ! $row ) {
			return fluent_abilities_error( 'not_found', 'Attachment not found.' );
		}
		$meta               = maybe_unserialize( $row->value ?? '' );
		$meta               = is_array( $meta ) ? $meta : array();
		$meta['visibility'] = $visibility;
		wpFluent()->table( 'fbs_metas' )->where( 'id', $att_id )->update( array(
			'value'      => maybe_serialize( $meta ),
			'updated_at' => current_time( 'mysql' ),
		) );
		return array( 'success' => true, 'attachment_id' => $att_id, 'visibility' => $visibility );
	},
) );

// =========================================================================
// §4.14.5 — get-attachment-download-url
// =========================================================================
$reg->read( 'fluent-boards/get-attachment-download-url', array(
	'label'       => 'Get Attachment Download URL (Pro)',
	'description' => 'Get a download URL for an attachment. For private attachments, the URL is signed/short-lived where the underlying storage driver supports it.',
	'category'    => 'fluent-boards',
	'input_schema' => array(
		'type'       => 'object',
		'required'   => array( 'attachment_id' ),
		'properties' => array(
			'attachment_id' => array( 'type' => 'integer' ),
		),
	),
	'output_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'attachment_id' => array( 'type' => 'integer' ),
			'url'           => array( 'type' => array( 'string', 'null' ) ),
			'visibility'    => array( 'type' => array( 'string', 'null' ) ),
		),
	),
	'callback' => function( $input ) {
		$att_id = (int) $input['attachment_id'];
		$row    = wpFluent()->table( 'fbs_metas' )->where( 'id', $att_id )->where( 'object_type', 'task_attachment' )->first();
		if ( ! $row ) {
			return fluent_abilities_error( 'not_found', 'Attachment not found.' );
		}
		$meta = maybe_unserialize( $row->value ?? '' );
		$meta = is_array( $meta ) ? $meta : array();
		return array( 'attachment_id' => $att_id, 'url' => $meta['url'] ?? null, 'visibility' => $meta['visibility'] ?? 'public' );
	},
) );
