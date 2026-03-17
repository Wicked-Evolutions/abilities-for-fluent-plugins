<?php
/**
 * Cross-Module Abilities
 *
 * These abilities span multiple Fluent products and are only possible
 * because abilities-for-fluent-plugins is a unified plugin with access to all APIs.
 *
 * 5 abilities in the 'fluent' category.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'fluent' );

	// =========================================================================
	// GET ACTIVE MODULES
	// =========================================================================

	$reg->read( 'fluent/get-active-modules', array(
		'capability'    => 'manage_options',
		'label'         => 'Get Active Fluent Modules',
		'description'   => 'List which Fluent products are installed and active, with versions. Shows both detection status and abilities-enabled status.',
		'output_schema' => fluent_abilities_schema_item_output( array(
			'modules'        => array( 'type' => 'object', 'description' => 'Keyed by module slug, each with label, plugin_detected, abilities_enabled, version' ),
			'loaded_count'   => array( 'type' => 'integer' ),
			'loaded_modules' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'plugin_version' => array( 'type' => 'string' ),
		) ),
		'callback'      => function( $input ) {
			$active = fluent_abilities_active_modules();
			$module_status = fluent_abilities_get_module_status();

			$modules = array();
			foreach ( $module_status as $key => $info ) {
				$modules[ $key ] = array(
					'label'             => $info['label'],
					'plugin_detected'   => $info['detected'],
					'abilities_enabled' => $info['enabled'],
					'version'           => isset( $active[ $key ] ) && is_string( $active[ $key ] ) ? $active[ $key ] : null,
				);
			}

			$loaded = defined( 'FLUENT_ABILITIES_LOADED_MODULES' )
				? array_filter( explode( ',', FLUENT_ABILITIES_LOADED_MODULES ) )
				: array();

			return array(
				'modules'        => $modules,
				'loaded_count'   => count( $loaded ),
				'loaded_modules' => $loaded,
				'plugin_version' => FLUENT_ABILITIES_VERSION,
			);
		},
	) );

	// =========================================================================
	// GET USER 360 VIEW
	// =========================================================================

	$reg->read( 'fluent/get-user-360', array(
		'capability'    => 'manage_options',
		'label'         => 'Get User 360 View',
		'description'   => 'Unified view of a user across all active Fluent products: CRM contact, Community profile, Form submissions, Support tickets, Bookings, Board tasks. Provide email or WordPress user ID.',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => array(
				'email'   => array( 'type' => 'string', 'description' => 'User email' ),
				'user_id' => array( 'type' => 'integer', 'description' => 'WordPress user ID (alternative to email)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'email'      => array( 'type' => 'string' ),
			'wp_user_id' => array( 'type' => array( 'integer', 'null' ) ),
			'wp_user'    => array( 'type' => array( 'string', 'null' ) ),
			'crm'        => array( 'type' => 'object' ),
			'community'  => array( 'type' => 'object' ),
			'forms'      => array( 'type' => 'object' ),
			'support'    => array( 'type' => 'object' ),
			'booking'    => array( 'type' => 'object' ),
		) ),
		'callback'      => function( $input ) {
			$identifier = ! empty( $input['email'] ) ? $input['email'] : ( $input['user_id'] ?? null );
			if ( ! $identifier ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Provide either email or user_id' );
			}

			$result = fluent_abilities_resolve_user( $identifier );

			global $wpdb;

			// FluentCRM — contact with tags, lists, last activity.
			if ( fluent_abilities_module_enabled( 'crm' ) && defined( 'FLUENTCRM_PLUGIN_VERSION' ) && function_exists( 'FluentCrmApi' ) && ! empty( $result['email'] ) ) {
				$contact = FluentCrmApi( 'contacts' )->getContactByUserRef( $result['email'] );
				if ( $contact ) {
					$tags = $contact->tags ? $contact->tags->pluck( 'title' )->toArray() : array();
					$lists = $contact->lists ? $contact->lists->pluck( 'title' )->toArray() : array();
					$crm_data = array(
						'id'         => $contact->id,
						'status'     => $contact->status,
						'name'       => trim( $contact->first_name . ' ' . $contact->last_name ),
						'email'      => $contact->email,
						'phone'      => $contact->phone ?: null,
						'country'    => $contact->country ?: null,
						'tags'       => $tags,
						'lists'      => $lists,
						'created_at' => (string) $contact->created_at,
						'last_activity' => $contact->last_activity,
					);

					// Recent email stats.
					$stats_table = $wpdb->prefix . 'fc_campaign_emails';
					if ( $wpdb->get_var( "SHOW TABLES LIKE '{$stats_table}'" ) === $stats_table ) {
						$email_stats = $wpdb->get_row( $wpdb->prepare(
							"SELECT COUNT(*) as sent, SUM(CASE WHEN is_open = 1 THEN 1 ELSE 0 END) as opened, SUM(click_counter) as clicks FROM {$stats_table} WHERE subscriber_id = %d",
							$contact->id
						) );
						if ( $email_stats ) {
							$crm_data['emails_sent']   = (int) $email_stats->sent;
							$crm_data['emails_opened'] = (int) $email_stats->opened;
							$crm_data['link_clicks']   = (int) $email_stats->clicks;
						}
					}

					$result['crm'] = $crm_data;
				}
			}

			// Fluent Community — profile, space memberships, post count.
			if ( fluent_abilities_module_enabled( 'community' ) && defined( 'FLUENT_COMMUNITY_PLUGIN_VERSION' ) && ! empty( $result['wp_user_id'] ) ) {
				$xprofile_table = $wpdb->prefix . 'fcom_xprofile';
				$space_user_table = $wpdb->prefix . 'fcom_space_user';
				$spaces_table = $wpdb->prefix . 'fcom_spaces';
				$posts_table = $wpdb->prefix . 'fcom_posts';
				$comments_table = $wpdb->prefix . 'fcom_post_comments';
				$uid = $result['wp_user_id'];

				if ( $wpdb->get_var( "SHOW TABLES LIKE '{$xprofile_table}'" ) === $xprofile_table ) {
					$profile = $wpdb->get_row( $wpdb->prepare(
						"SELECT id, display_name, status, total_points, is_verified, created_at FROM {$xprofile_table} WHERE user_id = %d LIMIT 1",
						$uid
					) );
					if ( $profile ) {
						// Space memberships with names.
						$spaces = $wpdb->get_results( $wpdb->prepare(
							"SELECT s.title, s.type, su.role, su.status, su.created_at FROM {$space_user_table} su JOIN {$spaces_table} s ON su.space_id = s.id WHERE su.user_id = %d ORDER BY su.created_at DESC",
							$uid
						) );

						$post_count = (int) $wpdb->get_var( $wpdb->prepare(
							"SELECT COUNT(*) FROM {$posts_table} WHERE user_id = %d AND status = 'published'",
							$uid
						) );

						$comment_count = (int) $wpdb->get_var( $wpdb->prepare(
							"SELECT COUNT(*) FROM {$comments_table} WHERE user_id = %d",
							$uid
						) );

						$result['community'] = array(
							'id'            => (int) $profile->id,
							'display_name'  => $profile->display_name,
							'status'        => $profile->status,
							'total_points'  => (int) ( $profile->total_points ?? 0 ),
							'is_verified'   => (bool) ( $profile->is_verified ?? false ),
							'post_count'    => $post_count,
							'comment_count' => $comment_count,
							'spaces'        => array_map( function( $s ) {
								return array(
									'title'     => $s->title,
									'type'      => $s->type,
									'role'      => $s->role,
									'status'    => $s->status,
									'joined_at' => (string) $s->created_at,
								);
							}, $spaces ),
							'created_at' => (string) $profile->created_at,
						);
					}
				}
			}

			// Fluent Forms — submission count with recent form names.
			if ( fluent_abilities_module_enabled( 'forms' ) && defined( 'FLUENTFORM_VERSION' ) && ! empty( $result['email'] ) ) {
				$subs_table = $wpdb->prefix . 'fluentform_submissions';
				$forms_table = $wpdb->prefix . 'fluentform_forms';
				if ( $wpdb->get_var( "SHOW TABLES LIKE '{$subs_table}'" ) === $subs_table ) {
					$count = (int) $wpdb->get_var( $wpdb->prepare(
						"SELECT COUNT(*) FROM {$subs_table} WHERE response LIKE %s",
						'%' . $wpdb->esc_like( $result['email'] ) . '%'
					) );

					// Get recent submissions with form names.
					$recent = $wpdb->get_results( $wpdb->prepare(
						"SELECT s.id, s.form_id, f.title as form_title, s.status, s.created_at FROM {$subs_table} s LEFT JOIN {$forms_table} f ON s.form_id = f.id WHERE s.response LIKE %s ORDER BY s.created_at DESC LIMIT 10",
						'%' . $wpdb->esc_like( $result['email'] ) . '%'
					) );

					$result['forms'] = array(
						'submission_count' => $count,
						'recent' => array_map( function( $s ) {
							return array(
								'id'         => (int) $s->id,
								'form_id'    => (int) $s->form_id,
								'form_title' => $s->form_title,
								'status'     => $s->status,
								'created_at' => (string) $s->created_at,
							);
						}, $recent ),
					);
				}
			}

			// Fluent Support — tickets via persons table.
			if ( fluent_abilities_module_enabled( 'support' ) && defined( 'FLUENT_SUPPORT_VERSION' ) && ! empty( $result['email'] ) ) {
				$persons_table = $wpdb->prefix . 'fs_persons';
				$tickets_table = $wpdb->prefix . 'fs_tickets';
				if ( $wpdb->get_var( "SHOW TABLES LIKE '{$persons_table}'" ) === $persons_table ) {
					$person = $wpdb->get_row( $wpdb->prepare(
						"SELECT id, first_name, last_name, status FROM {$persons_table} WHERE email = %s AND person_type = 'customer' LIMIT 1",
						$result['email']
					) );
					if ( $person ) {
						$tickets = $wpdb->get_results( $wpdb->prepare(
							"SELECT id, title, status, priority, created_at, resolved_at FROM {$tickets_table} WHERE customer_id = %d ORDER BY created_at DESC LIMIT 10",
							$person->id
						) );
						$result['support'] = array(
							'customer_id'  => (int) $person->id,
							'ticket_count' => count( $tickets ),
							'tickets'      => array_map( function( $t ) {
								return array(
									'id'          => (int) $t->id,
									'title'       => $t->title,
									'status'      => $t->status,
									'priority'    => $t->priority,
									'created_at'  => (string) $t->created_at,
									'resolved_at' => $t->resolved_at,
								);
							}, $tickets ),
						);
					}
				}
			}

			// FluentBooking — booking history.
			if ( fluent_abilities_module_enabled( 'booking' ) && defined( 'FLUENT_BOOKING_VERSION' ) && ! empty( $result['email'] ) ) {
				$bookings_table = $wpdb->prefix . 'fcal_bookings';
				$events_table = $wpdb->prefix . 'fcal_calendar_events';
				if ( $wpdb->get_var( "SHOW TABLES LIKE '{$bookings_table}'" ) === $bookings_table ) {
					$bookings = $wpdb->get_results( $wpdb->prepare(
						"SELECT b.id, b.status, b.start_time, b.end_time, b.source, e.title as event_title FROM {$bookings_table} b LEFT JOIN {$events_table} e ON b.event_id = e.id WHERE b.email = %s ORDER BY b.start_time DESC LIMIT 10",
						$result['email']
					) );
					if ( $bookings ) {
						$result['booking'] = array(
							'booking_count' => count( $bookings ),
							'bookings'      => array_map( function( $b ) {
								return array(
									'id'          => (int) $b->id,
									'event_title' => $b->event_title,
									'status'      => $b->status,
									'start_time'  => $b->start_time,
									'end_time'    => $b->end_time,
									'source'      => $b->source,
								);
							}, $bookings ),
						);
					}
				}
			}

			return $result;
		},
	) );

	// =========================================================================
	// DASHBOARD
	// =========================================================================

	$reg->read( 'fluent/get-suite-dashboard', array(
		'capability'    => 'manage_options',
		'label'         => 'Get Fluent Dashboard',
		'description'   => 'Aggregated statistics across all active Fluent products.',
		'output_schema' => fluent_abilities_schema_item_output( array(
			'stats'          => array( 'type' => 'object', 'description' => 'Keyed by module: crm, community, forms, support' ),
			'active_modules' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'generated_at'   => array( 'type' => 'string' ),
		) ),
		'callback'      => function( $input ) {
			$stats = array();

			// CRM stats.
			if ( fluent_abilities_module_enabled( 'crm' ) && function_exists( 'FluentCrmApi' ) ) {
				$stats['crm'] = array(
					'contacts_total'  => \FluentCrm\App\Models\Subscriber::count(),
					'contacts_active' => \FluentCrm\App\Models\Subscriber::where( 'status', 'subscribed' )->count(),
					'tags'            => \FluentCrm\App\Models\Tag::count(),
					'lists'           => \FluentCrm\App\Models\Lists::count(),
					'campaigns'       => \FluentCrm\App\Models\Campaign::count(),
				);
			}

			// Community stats.
			if ( fluent_abilities_module_enabled( 'community' ) && class_exists( 'FluentCommunity\\App\\App' ) ) {
				$stats['community'] = array(
					'members' => \FluentCommunity\App\Models\XProfile::count(),
					'spaces'  => \FluentCommunity\App\Models\Space::where( 'type', 'community' )->count(),
					'courses' => \FluentCommunity\App\Models\Space::where( 'type', 'course' )->count(),
					'posts'   => \FluentCommunity\App\Models\Feed::where( 'status', 'published' )->count(),
				);
			}

			// Forms stats.
			if ( fluent_abilities_module_enabled( 'forms' ) && class_exists( 'FluentForm\\App\\App' ) ) {
				global $wpdb;
				$forms_table = $wpdb->prefix . 'fluentform_forms';
				$subs_table = $wpdb->prefix . 'fluentform_submissions';
				if ( $wpdb->get_var( "SHOW TABLES LIKE '{$forms_table}'" ) === $forms_table ) {
					$stats['forms'] = array(
						'forms'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$forms_table}" ),
						'submissions' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$subs_table}" ),
					);
				}
			}

			// Support stats — direct SQL (no Eloquent model dependency).
			if ( fluent_abilities_module_enabled( 'support' ) && defined( 'FLUENT_SUPPORT_VERSION' ) ) {
				global $wpdb;
				$tickets_table = $wpdb->prefix . 'fs_tickets';
				if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tickets_table}'" ) === $tickets_table ) {
					$stats['support'] = array(
						'tickets_total' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tickets_table}" ),
						'tickets_open'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tickets_table} WHERE status = 'new' OR status = 'active'" ),
					);
				}
			}

			return array(
				'stats'          => $stats,
				'active_modules' => array_keys( fluent_abilities_active_modules() ),
				'generated_at'   => current_time( 'mysql' ),
			);
		},
	) );

	// =========================================================================
	// ENGAGEMENT SCORE
	// =========================================================================

	$reg->read( 'fluent/get-engagement-score', array(
		'capability'    => 'manage_options',
		'label'         => 'Get User Engagement Score',
		'description'   => 'Calculate an engagement score for a user across CRM, Community, Forms, and Support. Gracefully handles missing modules.',
		'input_schema'  => array(
			'type'       => 'object',
			'properties' => array(
				'email'   => array( 'type' => 'string', 'description' => 'User email' ),
				'user_id' => array( 'type' => 'integer', 'description' => 'WordPress user ID' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'email'            => array( 'type' => 'string' ),
			'wp_user_id'       => array( 'type' => array( 'integer', 'null' ) ),
			'engagement_score' => array( 'type' => 'integer' ),
			'breakdown'        => array( 'type' => 'object', 'description' => 'Score per module' ),
			'modules_checked'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
		) ),
		'callback'      => function( $input ) {
			$identifier = ! empty( $input['email'] ) ? $input['email'] : ( $input['user_id'] ?? null );
			if ( ! $identifier ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Provide either email or user_id' );
			}

			$resolved = fluent_abilities_resolve_user( $identifier );
			$score = 0;
			$breakdown = array();

			// CRM engagement (tags, list memberships).
			if ( fluent_abilities_module_enabled( 'crm' ) && function_exists( 'FluentCrmApi' ) && ! empty( $resolved['email'] ) ) {
				$contact = FluentCrmApi( 'contacts' )->getContactByUserRef( $resolved['email'] );
				if ( $contact ) {
					$tag_count  = $contact->tags ? $contact->tags->count() : 0;
					$list_count = $contact->lists ? $contact->lists->count() : 0;
					$crm_score  = ( $tag_count * 5 ) + ( $list_count * 3 );
					if ( $contact->status === 'subscribed' ) $crm_score += 10;
					$score += $crm_score;
					$breakdown['crm'] = $crm_score;
				}
			}

			// Community engagement (points, posts).
			if ( fluent_abilities_module_enabled( 'community' ) && class_exists( 'FluentCommunity\\App\\App' ) && ! empty( $resolved['wp_user_id'] ) ) {
				$profile = \FluentCommunity\App\Models\XProfile::where( 'user_id', $resolved['wp_user_id'] )->first();
				if ( $profile ) {
					$community_score = (int) ( $profile->total_points ?? 0 );
					$post_count = \FluentCommunity\App\Models\Feed::where( 'user_id', $resolved['wp_user_id'] )->count();
					$community_score += ( $post_count * 3 );
					$score += $community_score;
					$breakdown['community'] = $community_score;
				}
			}

			// Form submissions.
			if ( fluent_abilities_module_enabled( 'forms' ) && class_exists( 'FluentForm\\App\\App' ) && ! empty( $resolved['email'] ) ) {
				global $wpdb;
				$table = $wpdb->prefix . 'fluentform_submissions';
				if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table ) {
					$sub_count = (int) $wpdb->get_var( $wpdb->prepare(
						"SELECT COUNT(*) FROM {$table} WHERE response LIKE %s",
						'%' . $wpdb->esc_like( $resolved['email'] ) . '%'
					));
					$forms_score = $sub_count * 5;
					$score += $forms_score;
					$breakdown['forms'] = $forms_score;
				}
			}

			return array(
				'email'            => $resolved['email'] ?? null,
				'wp_user_id'       => $resolved['wp_user_id'] ?? null,
				'engagement_score' => $score,
				'breakdown'        => $breakdown,
				'modules_checked'  => array_keys( $breakdown ),
			);
		},
	) );

	// =========================================================================
	// ONBOARD USER
	// =========================================================================

	$reg->write( 'fluent/onboard-user', array(
		'capability'    => 'fluent_crm_write',
		'label'         => 'Onboard User Across Fluent Products',
		'description'   => 'Create records for a user across multiple Fluent products in one call: CRM contact, Community member. Provide email and optionally tags/lists for CRM.',
		'input_schema'  => array(
			'type'       => 'object',
			'required'   => array( 'email' ),
			'properties' => array(
				'email'      => array( 'type' => 'string', 'description' => 'User email (required)' ),
				'first_name' => array( 'type' => 'string', 'description' => 'First name' ),
				'last_name'  => array( 'type' => 'string', 'description' => 'Last name' ),
				'crm_tags'   => array( 'type' => 'string', 'description' => 'Comma-separated CRM tag IDs' ),
				'crm_lists'  => array( 'type' => 'string', 'description' => 'Comma-separated CRM list IDs' ),
				'crm_status' => array( 'type' => 'string', 'description' => 'CRM status (default: subscribed)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'email' => array( 'type' => 'string' ),
			'crm'   => array( 'type' => 'object', 'description' => 'CRM onboarding result with success and contact_id' ),
		) ),
		'callback'      => function( $input ) {
			$email = sanitize_email( $input['email'] );
			$results = array( 'email' => $email );

			// CRM: create or update contact.
			if ( fluent_abilities_module_enabled( 'crm' ) && function_exists( 'FluentCrmApi' ) ) {
				$data = array(
					'email'      => $email,
					'first_name' => sanitize_text_field( $input['first_name'] ?? '' ),
					'last_name'  => sanitize_text_field( $input['last_name'] ?? '' ),
					'status'     => sanitize_text_field( $input['crm_status'] ?? 'subscribed' ),
				);

				$contact = FluentCrmApi( 'contacts' )->createOrUpdate( $data );

				if ( $contact && $contact->id ) {
					if ( ! empty( $input['crm_tags'] ) ) {
						$contact->attachTags( array_map( 'intval', explode( ',', $input['crm_tags'] ) ) );
					}
					if ( ! empty( $input['crm_lists'] ) ) {
						$contact->attachLists( array_map( 'intval', explode( ',', $input['crm_lists'] ) ) );
					}
					$results['crm'] = array( 'success' => true, 'contact_id' => $contact->id );
				} else {
					$results['crm'] = array( 'success' => false, 'error' => 'Failed to create contact' );
				}
			}

			return $results;
		},
	) );

}, 100 );
