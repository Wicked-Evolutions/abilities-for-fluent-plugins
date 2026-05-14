<?php
/**
 * Fluent Messaging Abilities — v2.0.0 (Phase B additions).
 *
 * Adds 8 messaging abilities (cluster 4.11) to the existing 5 v1.1.3 messaging
 * abilities in messaging/abilities.php (which remain frozen per Stable Contracts).
 *
 * Total messaging surface after this file loads: 13.
 *
 * Source: ABILITY REGISTRAR RESEARCH — FluentCommunity 2026-05-12 v2.0, §4.11.
 *
 * Storage layer note: this file follows the existing module's wpFluent()->table()
 * raw-query pattern (matching messaging/abilities.php) rather than Eloquent
 * models — vendor `fluent-messaging/app/Models/` exists (Thread.php, Message.php,
 * ThreadUser.php) but column-name parity with the existing module is the
 * primary correctness gate.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register all messaging v2 abilities. Public for unit tests.
 */
function fluent_abilities_register_messaging_v2() {
	$reg = new Fluent_Abilities_Registrar( 'messaging' );

	// =========================================================================
	// 4.11.1 — CREATE THREAD
	// =========================================================================

	$reg->write( 'fluent-messaging/create-thread', array(
		'label'       => 'Create Chat Thread',
		'description' => 'Create a new chat thread, optionally bound to a community space, optionally seeded with participant user IDs. Creator is auto-added as a participant.',
		'category'    => 'fluent-messaging',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'title'           => array( 'type' => array( 'string', 'null' ), 'description' => 'Optional thread title' ),
				'space_id'        => array( 'type' => array( 'integer', 'null' ), 'description' => 'Optional space ID to bind the thread to' ),
				'participant_ids' => array(
					'type'        => 'array',
					'description' => 'Optional list of user IDs to add as initial participants (creator auto-added)',
					'items'       => array( 'type' => 'integer' ),
				),
				'status'   => array( 'type' => 'string', 'description' => 'Thread status (default active)' ),
				'provider' => array( 'type' => array( 'string', 'null' ), 'description' => 'Optional provider tag' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'thread_id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			$user_id = get_current_user_id();
			if ( ! $user_id ) {
				return fluent_abilities_error( 'rest_forbidden', 'No authenticated user' );
			}

			if ( ! class_exists( '\\FluentMessaging\\App\\Models\\Thread' ) || ! class_exists( '\\FluentMessaging\\App\\Models\\ThreadUser' ) ) {
				return fluent_abilities_error( 'not_available', 'FluentMessaging Thread/ThreadUser model not available' );
			}

			// Use Eloquent create() (returns model with populated id) instead of
			// wpFluent()->insert() (which returns boolean-as-int 1 regardless of
			// inserted ID — phantom-id bug surfaced during Phase B live verification).
			$thread = \FluentMessaging\App\Models\Thread::create( array(
				'title'    => isset( $input['title'] ) ? sanitize_text_field( (string) $input['title'] ) : null,
				'space_id' => isset( $input['space_id'] ) ? (int) $input['space_id'] : null,
				'status'   => isset( $input['status'] ) ? sanitize_text_field( $input['status'] ) : 'active',
				'provider' => isset( $input['provider'] ) ? sanitize_text_field( $input['provider'] ) : null,
			) );

			if ( ! $thread || empty( $thread->id ) ) {
				return fluent_abilities_error( 'create_failed', 'Failed to create thread' );
			}

			$thread_id = (int) $thread->id;

			$participants = array( $user_id );
			if ( ! empty( $input['participant_ids'] ) && is_array( $input['participant_ids'] ) ) {
				foreach ( $input['participant_ids'] as $pid ) {
					$pid = (int) $pid;
					if ( $pid > 0 && ! in_array( $pid, $participants, true ) ) {
						$participants[] = $pid;
					}
				}
			}

			foreach ( $participants as $pid ) {
				\FluentMessaging\App\Models\ThreadUser::create( array(
					'thread_id' => $thread_id,
					'user_id'   => $pid,
					'status'    => 'active',
				) );
			}

			return array(
				'success'   => true,
				'thread_id' => $thread_id,
			);
		},
	) );

	// =========================================================================
	// 4.11.2 — UPDATE THREAD (title/status)
	// =========================================================================

	$reg->write( 'fluent-messaging/update-thread', array(
		'label'       => 'Update Chat Thread',
		'description' => 'Update a chat thread title and/or status. Non-admin users must be a participant of the thread.',
		'category'    => 'fluent-messaging',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'     => array( 'type' => 'integer', 'description' => 'Thread ID' ),
				'title'  => array( 'type' => array( 'string', 'null' ), 'description' => 'New title' ),
				'status' => array( 'type' => 'string', 'description' => 'New status' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'thread_id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$thread_id = (int) ( $input['id'] ?? 0 );
			$thread    = wpFluent()->table( 'fcom_chat_threads' )->where( 'id', $thread_id )->first();
			if ( ! $thread ) {
				return fluent_abilities_error( 'not_found', 'Thread not found' );
			}

			$user_id = get_current_user_id();
			if ( ! current_user_can( 'manage_options' ) ) {
				$is_participant = wpFluent()->table( 'fcom_chat_thread_users' )
					->where( 'thread_id', $thread_id )
					->where( 'user_id', $user_id )
					->count();
				if ( ! $is_participant ) {
					return fluent_abilities_error( 'rest_forbidden', 'You are not a participant in this thread' );
				}
			}

			$update = array( 'updated_at' => current_time( 'mysql' ) );
			if ( array_key_exists( 'title', $input ) ) {
				$update['title'] = $input['title'] === null ? null : sanitize_text_field( (string) $input['title'] );
			}
			if ( ! empty( $input['status'] ) ) {
				$update['status'] = sanitize_text_field( $input['status'] );
			}

			wpFluent()->table( 'fcom_chat_threads' )->where( 'id', $thread_id )->update( $update );

			return array(
				'success'   => true,
				'thread_id' => $thread_id,
			);
		},
	) );

	// =========================================================================
	// 4.11.3 — DELETE THREAD
	// =========================================================================

	$reg->delete( 'fluent-messaging/delete-thread', array(
		'label'       => 'Delete Chat Thread',
		'description' => 'Delete a chat thread and cascade-delete its messages and thread-user pivot rows. Admin only.',
		'category'    => 'fluent-messaging',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Thread ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'thread_id'       => array( 'type' => 'integer' ),
			'messages_deleted' => array( 'type' => 'integer' ),
			'participants_deleted' => array( 'type' => 'integer' ),
		) ),
		'capability'  => 'manage_options',
		'annotations' => array( 'idempotent' => true ),
		'callback' => function( $input ) {
			$thread_id = (int) ( $input['id'] ?? 0 );
			$thread    = wpFluent()->table( 'fcom_chat_threads' )->where( 'id', $thread_id )->first();
			if ( ! $thread ) {
				return fluent_abilities_error( 'not_found', 'Thread not found' );
			}

			$messages_deleted = wpFluent()->table( 'fcom_chat_messages' )
				->where( 'thread_id', $thread_id )
				->delete();

			$participants_deleted = wpFluent()->table( 'fcom_chat_thread_users' )
				->where( 'thread_id', $thread_id )
				->delete();

			wpFluent()->table( 'fcom_chat_threads' )->where( 'id', $thread_id )->delete();

			return array(
				'success'              => true,
				'thread_id'            => $thread_id,
				'messages_deleted'     => (int) $messages_deleted,
				'participants_deleted' => (int) $participants_deleted,
			);
		},
	) );

	// =========================================================================
	// 4.11.4 — ADD PARTICIPANT
	// =========================================================================

	$reg->write( 'fluent-messaging/add-participant', array(
		'label'       => 'Add Thread Participant',
		'description' => 'Add a user as a participant in a chat thread. Non-admin callers must already be a participant of the thread.',
		'category'    => 'fluent-messaging',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'thread_id', 'user_id' ),
			'properties' => array(
				'thread_id' => array( 'type' => 'integer', 'description' => 'Thread ID' ),
				'user_id'   => array( 'type' => 'integer', 'description' => 'User ID to add' ),
				'status'    => array( 'type' => 'string', 'description' => 'Participant status (default active)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'thread_id' => array( 'type' => 'integer' ),
			'user_id'   => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => true ),
		'callback' => function( $input ) {
			$thread_id = (int) ( $input['thread_id'] ?? 0 );
			$user_id   = (int) ( $input['user_id'] ?? 0 );
			$caller_id = get_current_user_id();

			$thread = wpFluent()->table( 'fcom_chat_threads' )->where( 'id', $thread_id )->first();
			if ( ! $thread ) {
				return fluent_abilities_error( 'not_found', 'Thread not found' );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				$is_participant = wpFluent()->table( 'fcom_chat_thread_users' )
					->where( 'thread_id', $thread_id )
					->where( 'user_id', $caller_id )
					->count();
				if ( ! $is_participant ) {
					return fluent_abilities_error( 'rest_forbidden', 'You are not a participant in this thread' );
				}
			}

			$existing = wpFluent()->table( 'fcom_chat_thread_users' )
				->where( 'thread_id', $thread_id )
				->where( 'user_id', $user_id )
				->first();

			if ( $existing ) {
				return array(
					'success'   => true,
					'thread_id' => $thread_id,
					'user_id'   => $user_id,
					'already_present' => true,
				);
			}

			if ( ! class_exists( '\\FluentMessaging\\App\\Models\\ThreadUser' ) ) {
				return fluent_abilities_error( 'not_available', 'FluentMessaging ThreadUser model not available' );
			}

			\FluentMessaging\App\Models\ThreadUser::create( array(
				'thread_id' => $thread_id,
				'user_id'   => $user_id,
				'status'    => isset( $input['status'] ) ? sanitize_text_field( $input['status'] ) : 'active',
			) );

			return array(
				'success'   => true,
				'thread_id' => $thread_id,
				'user_id'   => $user_id,
			);
		},
	) );

	// =========================================================================
	// 4.11.5 — REMOVE PARTICIPANT
	// =========================================================================
	// Per research §7.Q2 — self-removal vs admin-removal semantics surfaced:
	// implementation chooses simplest interpretation: any participant may
	// remove themselves; manage_options users may remove anyone.

	$reg->delete( 'fluent-messaging/remove-participant', array(
		'label'       => 'Remove Thread Participant',
		'description' => 'Remove a user from a chat thread. Users may always remove themselves; only admins (manage_options) may remove other participants.',
		'category'    => 'fluent-messaging',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'thread_id', 'user_id' ),
			'properties' => array(
				'thread_id' => array( 'type' => 'integer', 'description' => 'Thread ID' ),
				'user_id'   => array( 'type' => 'integer', 'description' => 'User ID to remove' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'thread_id' => array( 'type' => 'integer' ),
			'user_id'   => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => true ),
		'callback' => function( $input ) {
			$thread_id = (int) ( $input['thread_id'] ?? 0 );
			$user_id   = (int) ( $input['user_id'] ?? 0 );
			$caller_id = get_current_user_id();

			$thread = wpFluent()->table( 'fcom_chat_threads' )->where( 'id', $thread_id )->first();
			if ( ! $thread ) {
				return fluent_abilities_error( 'not_found', 'Thread not found' );
			}

			$is_self = ( $caller_id === $user_id );
			$is_admin = current_user_can( 'manage_options' );

			if ( ! $is_admin && ! $is_self ) {
				return fluent_abilities_error( 'rest_forbidden', 'Only admins may remove other participants' );
			}

			$deleted = wpFluent()->table( 'fcom_chat_thread_users' )
				->where( 'thread_id', $thread_id )
				->where( 'user_id', $user_id )
				->delete();

			return array(
				'success'   => true,
				'thread_id' => $thread_id,
				'user_id'   => $user_id,
				'removed'   => (bool) $deleted,
			);
		},
	) );

	// =========================================================================
	// 4.11.6 — UPDATE MESSAGE
	// =========================================================================

	$reg->write( 'fluent-messaging/update-message', array(
		'label'       => 'Update Chat Message',
		'description' => 'Edit a chat message text. Only the original author or an admin (manage_options) may update.',
		'category'    => 'fluent-messaging',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id', 'text' ),
			'properties' => array(
				'id'   => array( 'type' => 'integer', 'description' => 'Message ID' ),
				'text' => array( 'type' => 'string', 'description' => 'New message text' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'message_id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$message_id = (int) ( $input['id'] ?? 0 );
			$caller_id  = get_current_user_id();

			$message = wpFluent()->table( 'fcom_chat_messages' )->where( 'id', $message_id )->first();
			if ( ! $message ) {
				return fluent_abilities_error( 'not_found', 'Message not found' );
			}

			if ( (int) $message->user_id !== $caller_id && ! current_user_can( 'manage_options' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'Only the message author or an admin may update this message' );
			}

			wpFluent()->table( 'fcom_chat_messages' )
				->where( 'id', $message_id )
				->update( array(
					'text'       => wp_kses_post( (string) $input['text'] ),
					'updated_at' => current_time( 'mysql' ),
				) );

			return array(
				'success'    => true,
				'message_id' => $message_id,
			);
		},
	) );

	// =========================================================================
	// 4.11.7 — DELETE MESSAGE
	// =========================================================================

	$reg->delete( 'fluent-messaging/delete-message', array(
		'label'       => 'Delete Chat Message',
		'description' => 'Delete a chat message. Only the original author or an admin (manage_options) may delete. Decrements thread message_count.',
		'category'    => 'fluent-messaging',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Message ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'message_id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => true ),
		'callback' => function( $input ) {
			$message_id = (int) ( $input['id'] ?? 0 );
			$caller_id  = get_current_user_id();

			$message = wpFluent()->table( 'fcom_chat_messages' )->where( 'id', $message_id )->first();
			if ( ! $message ) {
				return fluent_abilities_error( 'not_found', 'Message not found' );
			}

			if ( (int) $message->user_id !== $caller_id && ! current_user_can( 'manage_options' ) ) {
				return fluent_abilities_error( 'rest_forbidden', 'Only the message author or an admin may delete this message' );
			}

			$thread_id = (int) $message->thread_id;

			wpFluent()->table( 'fcom_chat_messages' )->where( 'id', $message_id )->delete();

			$thread = wpFluent()->table( 'fcom_chat_threads' )->where( 'id', $thread_id )->first();
			if ( $thread ) {
				$new_count = max( 0, (int) $thread->message_count - 1 );
				wpFluent()->table( 'fcom_chat_threads' )
					->where( 'id', $thread_id )
					->update( array(
						'message_count' => $new_count,
						'updated_at'    => current_time( 'mysql' ),
					) );
			}

			return array(
				'success'    => true,
				'message_id' => $message_id,
				'thread_id'  => $thread_id,
			);
		},
	) );

	// =========================================================================
	// 4.11.8 — MARK THREAD READ
	// =========================================================================

	$reg->write( 'fluent-messaging/mark-thread-read', array(
		'label'       => 'Mark Thread Read',
		'description' => "Mark a thread as read for the current user by setting last_seen_message_id to the thread's most recent message ID. Caller must be a participant.",
		'category'    => 'fluent-messaging',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'thread_id' ),
			'properties' => array(
				'thread_id' => array( 'type' => 'integer', 'description' => 'Thread ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'thread_id'            => array( 'type' => 'integer' ),
			'last_seen_message_id' => array( 'type' => array( 'integer', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$thread_id = (int) ( $input['thread_id'] ?? 0 );
			$user_id   = get_current_user_id();
			if ( ! $user_id ) {
				return fluent_abilities_error( 'rest_forbidden', 'No authenticated user' );
			}

			$pivot = wpFluent()->table( 'fcom_chat_thread_users' )
				->where( 'thread_id', $thread_id )
				->where( 'user_id', $user_id )
				->first();
			if ( ! $pivot ) {
				return fluent_abilities_error( 'rest_forbidden', 'You are not a participant in this thread' );
			}

			$last_message = wpFluent()->table( 'fcom_chat_messages' )
				->where( 'thread_id', $thread_id )
				->orderBy( 'id', 'DESC' )
				->first();

			$last_seen = $last_message ? (int) $last_message->id : null;

			wpFluent()->table( 'fcom_chat_thread_users' )
				->where( 'thread_id', $thread_id )
				->where( 'user_id', $user_id )
				->update( array(
					'last_seen_message_id' => $last_seen,
					'updated_at'           => current_time( 'mysql' ),
				) );

			return array(
				'success'              => true,
				'thread_id'            => $thread_id,
				'last_seen_message_id' => $last_seen,
			);
		},
	) );

	error_log( 'Abilities for Fluent: Registered 8 Messaging v2.0 abilities (cluster 4.11)' );
}

add_action( 'wp_abilities_api_init', 'fluent_abilities_register_messaging_v2', 100 );
