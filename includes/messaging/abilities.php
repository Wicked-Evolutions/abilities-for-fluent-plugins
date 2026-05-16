<?php
/**
 * Fluent Community Messaging (DM) Abilities
 *
 * Chat threads, messages, participants, and messaging stats.
 * Uses wpFluent query builder against fcom_chat_* tables.
 *
 * 5 abilities in the 'fluent-messaging' category.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'messaging' );

	// =========================================================================
	// LIST THREADS
	// =========================================================================

	$reg->read( 'fluent-messaging/list-threads', array(
		'label'       => 'List Chat Threads',
		'description' => 'List chat threads with pagination. Non-admin users only see threads they participate in. Includes participant names resolved from WordPress users.',
		'category'    => 'fluent-messaging',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge( array(
				'status' => array( 'type' => 'string', 'description' => 'Filter by status (e.g., active)' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'threads', array(
			'id'            => array( 'type' => 'integer' ),
			'title'         => array( 'type' => 'string' ),
			'space_id'      => array( 'type' => 'integer' ),
			'message_count' => array( 'type' => 'integer' ),
			'status'        => array( 'type' => 'string' ),
			'created_at'    => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );
			$current_user_id = get_current_user_id();

			$query = wpFluent()->table( 'fcom_chat_threads' )
				->orderBy( 'updated_at', 'DESC' );

			// Non-admin users: scope to threads they participate in.
			if ( ! current_user_can( 'manage_options' ) ) {
				$query->whereIn( 'id', function( $sub ) use ( $current_user_id ) {
					$sub->select( 'thread_id' )
						->from( 'fcom_chat_thread_users' )
						->where( 'user_id', $current_user_id );
				});
			}

			if ( ! empty( $input['status'] ) ) {
				$query->where( 'status', sanitize_text_field( $input['status'] ) );
			}

			$total = $query->count();
			$threads = $query->offset( $pagination['offset'] )
				->limit( $pagination['per_page'] )
				->get();

			$items = array();
			foreach ( $threads as $thread ) {
				// Resolve participants for this thread.
				$participants_raw = wpFluent()->table( 'fcom_chat_thread_users' )
					->where( 'thread_id', $thread->id )
					->get();

				$participants = array();
				foreach ( $participants_raw as $p ) {
					$user = get_userdata( (int) $p->user_id );
					$participants[] = array(
						'user_id'      => (int) $p->user_id,
						'display_name' => $user ? $user->display_name : '(unknown)',
					);
				}

				$items[] = array(
					'id'            => (int) $thread->id,
					'title'         => $thread->title ?? '',
					'space_id'      => (int) ( $thread->space_id ?? 0 ),
					'message_count' => (int) ( $thread->message_count ?? 0 ),
					'status'        => $thread->status ?? '',
					'provider'      => $thread->provider ?? '',
					'participants'  => $participants,
					'created_at'    => (string) $thread->created_at,
				);
			}

			return array( 'threads' => $items, 'total' => $total, 'page' => $pagination['page'] );
		},
	) );

	// =========================================================================
	// GET THREAD
	// =========================================================================

	$reg->read( 'fluent-messaging/get-thread', array(
		'label'       => 'Get Chat Thread',
		'description' => 'Get a single chat thread by ID with full participant details. Non-admin users must be a participant.',
		'category'    => 'fluent-messaging',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Thread ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'id'            => array( 'type' => 'integer' ),
			'title'         => array( 'type' => array( 'string', 'null' ) ),
			'space_id'      => array( 'type' => array( 'integer', 'null' ) ),
			'message_count' => array( 'type' => 'integer' ),
			'status'        => array( 'type' => 'string' ),
			'provider'      => array( 'type' => array( 'string', 'null' ) ),
			'participants'  => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'created_at'    => array( 'type' => 'string' ),
			'updated_at'    => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$thread = wpFluent()->table( 'fcom_chat_threads' )
				->where( 'id', (int) $input['id'] )
				->first();

			if ( ! $thread ) {
				return fluent_abilities_error( 'not_found', 'Thread not found' );
			}

			// Object-level auth: non-admin must be a participant.
			if ( ! current_user_can( 'manage_options' ) ) {
				$is_participant = wpFluent()->table( 'fcom_chat_thread_users' )
					->where( 'thread_id', $thread->id )
					->where( 'user_id', get_current_user_id() )
					->count();
				if ( ! $is_participant ) {
					return fluent_abilities_error( 'rest_forbidden', 'You are not a participant in this thread' );
				}
			}

			// Resolve participants with full details.
			$participants_raw = wpFluent()->table( 'fcom_chat_thread_users' )
				->where( 'thread_id', $thread->id )
				->get();

			$participants = array();
			foreach ( $participants_raw as $p ) {
				$user = get_userdata( (int) $p->user_id );
				$participants[] = array(
					'user_id'              => (int) $p->user_id,
					'display_name'         => $user ? $user->display_name : '(unknown)',
					'last_seen_message_id' => $p->last_seen_message_id ? (int) $p->last_seen_message_id : null,
					'status'               => $p->status,
				);
			}

			return array(
				'id'            => (int) $thread->id,
				'title'         => $thread->title,
				'space_id'      => $thread->space_id ? (int) $thread->space_id : null,
				'message_count' => (int) $thread->message_count,
				'status'        => $thread->status,
				'provider'      => $thread->provider,
				'participants'  => $participants,
				'created_at'    => (string) $thread->created_at,
				'updated_at'    => (string) $thread->updated_at,
			);
		},
	) );

	// =========================================================================
	// LIST MESSAGES
	// =========================================================================

	$reg->read( 'fluent-messaging/list-messages', array(
		'label'       => 'List Thread Messages',
		'description' => 'List messages for a chat thread with pagination. Non-admin users must be a participant. Ordered by created_at ASC (chronological). Meta is unserialized.',
		'category'    => 'fluent-messaging',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'thread_id' ),
			'properties' => array_merge( array(
				'thread_id' => array( 'type' => 'integer', 'description' => 'Thread ID' ),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'messages', array(
			'id'         => array( 'type' => 'integer' ),
			'thread_id'  => array( 'type' => 'integer' ),
			'user_id'    => array( 'type' => 'integer' ),
			'message'    => array( 'type' => 'string' ),
			'created_at' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			// Verify thread exists.
			$thread = wpFluent()->table( 'fcom_chat_threads' )
				->where( 'id', (int) $input['thread_id'] )
				->first();

			if ( ! $thread ) {
				return fluent_abilities_error( 'not_found', 'Thread not found' );
			}

			// Object-level auth: non-admin must be a participant.
			if ( ! current_user_can( 'manage_options' ) ) {
				$is_participant = wpFluent()->table( 'fcom_chat_thread_users' )
					->where( 'thread_id', $thread->id )
					->where( 'user_id', get_current_user_id() )
					->count();
				if ( ! $is_participant ) {
					return fluent_abilities_error( 'rest_forbidden', 'You are not a participant in this thread' );
				}
			}

			$pagination = fluent_abilities_pagination( $input );

			$query = wpFluent()->table( 'fcom_chat_messages' )
				->where( 'thread_id', (int) $input['thread_id'] )
				->orderBy( 'created_at', 'ASC' );

			$total = $query->count();
			$messages = $query->offset( $pagination['offset'] )
				->limit( $pagination['per_page'] )
				->get();

			$items = array();
			foreach ( $messages as $msg ) {
				$user = get_userdata( (int) $msg->user_id );
				$items[] = array(
					'id'           => (int) $msg->id,
					'user_id'      => (int) $msg->user_id,
					'display_name' => $user ? $user->display_name : '(unknown)',
					'text'         => $msg->text,
					'meta'         => $msg->meta ? maybe_unserialize( $msg->meta ) : null,
					'created_at'   => (string) $msg->created_at,
					'updated_at'   => (string) $msg->updated_at,
				);
			}

			return array( 'messages' => $items, 'total' => $total, 'page' => $pagination['page'] );
		},
	) );

	// =========================================================================
	// GET MESSAGING STATS
	// =========================================================================

	$reg->read( 'fluent-messaging/get-messaging-stats', array(
		'label'       => 'Get Messaging Stats',
		'description' => 'Dashboard stats: total threads, total messages, active threads, messages today, messages this week, and top 5 most active threads by message count.',
		'category'    => 'fluent-messaging',
		'input_schema' => array(
			'type'       => 'object',
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'total_threads'      => array( 'type' => 'integer' ),
			'total_messages'     => array( 'type' => 'integer' ),
			'active_threads'     => array( 'type' => 'integer' ),
			'messages_today'     => array( 'type' => 'integer' ),
			'messages_this_week' => array( 'type' => 'integer' ),
			'top_threads'        => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'capability' => 'manage_options',
		'callback' => function( $input ) {
			$total_threads  = (int) wpFluent()->table( 'fcom_chat_threads' )->count();
			$total_messages = (int) wpFluent()->table( 'fcom_chat_messages' )->count();
			$active_threads = (int) wpFluent()->table( 'fcom_chat_threads' )
				->where( 'status', 'active' )
				->count();

			$today = current_time( 'Y-m-d' ) . ' 00:00:00';
			$messages_today = (int) wpFluent()->table( 'fcom_chat_messages' )
				->where( 'created_at', '>=', $today )
				->count();

			$week_start = gmdate( 'Y-m-d', strtotime( 'monday this week', strtotime( current_time( 'Y-m-d' ) ) ) ) . ' 00:00:00';
			$messages_this_week = (int) wpFluent()->table( 'fcom_chat_messages' )
				->where( 'created_at', '>=', $week_start )
				->count();

			// Top 5 most active threads.
			$top_threads_raw = wpFluent()->table( 'fcom_chat_threads' )
				->orderBy( 'message_count', 'DESC' )
				->limit( 5 )
				->get();

			$top_threads = array();
			foreach ( $top_threads_raw as $t ) {
				$top_threads[] = array(
					'id'            => (int) $t->id,
					'title'         => $t->title,
					'message_count' => (int) $t->message_count,
					'status'        => $t->status,
				);
			}

			return array(
				'total_threads'      => $total_threads,
				'total_messages'     => $total_messages,
				'active_threads'     => $active_threads,
				'messages_today'     => $messages_today,
				'messages_this_week' => $messages_this_week,
				'top_threads'        => $top_threads,
			);
		},
	) );

	// =========================================================================
	// SEND MESSAGE
	// =========================================================================

	$reg->write( 'fluent-messaging/send-message', array(
		'label'       => 'Send Message',
		'description' => 'Send a message to a chat thread. Non-admin users must be a participant. Uses the current WordPress user as the sender. Updates thread message_count and updated_at timestamp. Returns the persisted message_id of the new entry so callers can reference it in subsequent reads.',
		'category'    => 'fluent-messaging',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'thread_id', 'text' ),
			'properties' => array(
				'thread_id' => array( 'type' => 'integer', 'description' => 'Thread ID to send the message to' ),
				'text'      => array( 'type' => 'string', 'description' => 'Message text content' ),
			),
		),
		'output_schema' => fluent_abilities_schema_success_output( array(
			'id'        => array( 'type' => 'integer' ),
			'thread_id' => array( 'type' => 'integer' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			// Verify thread exists.
			$thread = wpFluent()->table( 'fcom_chat_threads' )
				->where( 'id', (int) $input['thread_id'] )
				->first();

			if ( ! $thread ) {
				return fluent_abilities_error( 'not_found', 'Thread not found' );
			}

			$user_id = get_current_user_id();
			if ( ! $user_id ) {
				return fluent_abilities_error( 'rest_forbidden', 'No authenticated user' );
			}

			// Object-level auth: non-admin must be a participant.
			if ( ! current_user_can( 'manage_options' ) ) {
				$is_participant = wpFluent()->table( 'fcom_chat_thread_users' )
					->where( 'thread_id', $thread->id )
					->where( 'user_id', $user_id )
					->count();
				if ( ! $is_participant ) {
					return fluent_abilities_error( 'rest_forbidden', 'You are not a participant in this thread' );
				}
			}

			$now = current_time( 'mysql' );

			// insertGetId() returns the auto-increment id; insert() returns boolean
			$message_id = wpFluent()->table( 'fcom_chat_messages' )->insertGetId( array(
				'thread_id'  => (int) $input['thread_id'],
				'user_id'    => $user_id,
				'text'       => wp_kses_post( $input['text'] ),
				'created_at' => $now,
				'updated_at' => $now,
			));

			// Update thread message_count and updated_at.
			wpFluent()->table( 'fcom_chat_threads' )
				->where( 'id', (int) $input['thread_id'] )
				->update( array(
					'message_count' => (int) $thread->message_count + 1,
					'updated_at'    => $now,
				));

			return array(
				'success'    => true,
				'message_id' => (int) $message_id,
				'thread_id'  => (int) $input['thread_id'],
				'user_id'    => $user_id,
				'created_at' => $now,
			);
		},
	) );

	$count = 5;
	error_log( "Abilities for Fluent: Registered {$count} Messaging abilities" );

}, 100 );
