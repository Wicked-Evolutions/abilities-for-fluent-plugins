<?php
/**
 * FluentCRM Cohort Analysis Abilities
 *
 * Server-side aggregation abilities for analyzing groups of contacts.
 * Reduces API calls and token usage by 10-15x compared to per-contact queries.
 *
 * @package Fluent_Abilities
 * @since 1.4.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Helper: Build a safe IN clause for wpdb->prepare.
 *
 * @param array $ids Array of integer IDs.
 * @return string Comma-separated placeholders like "%d,%d,%d".
 */
function fluent_abilities_in_clause( $ids ) {
	return implode( ',', array_fill( 0, count( $ids ), '%d' ) );
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// COHORT ANALYSIS
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'crm' );

	// ── Ability 1: Get Cohort Journeys ──────────────────────────────────────────

	$reg->read( 'fluent-crm/get-cohort-journeys', array(
		'label'       => 'Get Cohort Journeys',
		'description' => 'Bulk-fetch compact journey summaries for a cohort of contacts. Returns one summary row per contact with tags, purchases, email stats, automations, event counts, and optional notes. 10-15x fewer tokens than individual get-contact-journey calls.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'selector_type', 'selector_value' ),
			'properties' => array(
				'selector_type' => array(
					'type'        => 'string',
					'description' => 'How to select contacts: tag (by tag ID), list (by list ID), event_key (by event key string), or contact_ids (comma-separated IDs)',
					'enum'        => array( 'tag', 'list', 'event_key', 'contact_ids' ),
				),
				'selector_value' => array(
					'type'        => 'string',
					'description' => 'The selector value: tag ID, list ID, event_key string, or comma-separated contact IDs',
				),
				'include_notes' => array(
					'type'        => 'string',
					'description' => 'Notes detail level: none (skip), summary (count + latest title, default), full (all note titles + first 200 chars)',
					'enum'        => array( 'none', 'summary', 'full' ),
				),
				'max_contacts' => array(
					'type'        => 'integer',
					'description' => 'Maximum contacts to include (default: 100, max: 200)',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'cohort_size' => array( 'type' => 'integer' ),
			'selector'    => array( 'type' => 'object' ),
			'contacts'    => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $input ) {
			global $wpdb;
			$prefix = $wpdb->prefix;

			$max_contacts = isset( $input['max_contacts'] ) ? min( intval( $input['max_contacts'] ), 200 ) : 100;
			$include_notes = $input['include_notes'] ?? 'summary';

			// Resolve cohort.
			$contact_ids = fluent_abilities_resolve_cohort(
				$input['selector_type'],
				$input['selector_value'],
				$max_contacts
			);

			if ( is_wp_error( $contact_ids ) ) {
				return $contact_ids;
			}

			$id_count = count( $contact_ids );
			$in_clause = fluent_abilities_in_clause( $contact_ids );

			// ── Query 1: Contact profiles ──
			$subscribers = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, first_name, last_name, email, status, created_at
				FROM {$prefix}fc_subscribers
				WHERE id IN ({$in_clause})",
				...$contact_ids
			) );

			$contacts = array();
			foreach ( $subscribers as $s ) {
				$contacts[ $s->id ] = array(
					'id'         => (int) $s->id,
					'name'       => trim( $s->first_name . ' ' . $s->last_name ),
					'email'      => $s->email,
					'status'     => $s->status,
					'created_at' => (string) $s->created_at,
					'tags'       => array(),
					'tag_dates'  => array(),
					'lists'      => array(),
					'purchases'  => array(),
					'emails'     => array( 'sent' => 0, 'opened' => 0, 'clicked' => 0 ),
					'automations' => array(),
					'event_keys'  => array(),
					'event_counts' => array(),
				);
			}

			if ( empty( $contacts ) ) {
				return fluent_abilities_error( 'not_found', 'No subscriber records found for resolved contact IDs.' );
			}

			// ── Query 2: Tags and lists from pivot table ──
			$pivots = $wpdb->get_results( $wpdb->prepare(
				"SELECT p.subscriber_id, p.object_type, p.object_id, p.created_at,
					CASE
						WHEN p.object_type LIKE '%%Tag' THEN t.title
						WHEN p.object_type LIKE '%%Lists' THEN l.title
						ELSE NULL
					END as object_title
				FROM {$prefix}fc_subscriber_pivot p
				LEFT JOIN {$prefix}fc_tags t ON p.object_type LIKE '%%Tag' AND p.object_id = t.id
				LEFT JOIN {$prefix}fc_lists l ON p.object_type LIKE '%%Lists' AND p.object_id = l.id
				WHERE p.subscriber_id IN ({$in_clause})",
				...$contact_ids
			) );

			foreach ( $pivots as $p ) {
				$sid = (int) $p->subscriber_id;
				if ( ! isset( $contacts[ $sid ] ) ) continue;

				if ( strpos( $p->object_type, 'Tag' ) !== false ) {
					$contacts[ $sid ]['tags'][] = $p->object_title;
					$contacts[ $sid ]['tag_dates'][ $p->object_title ] = substr( $p->created_at, 0, 10 );
				} else {
					$contacts[ $sid ]['lists'][] = $p->object_title;
				}
			}

			// Deduplicate tags/lists.
			foreach ( $contacts as &$c ) {
				$c['tags'] = array_values( array_unique( $c['tags'] ) );
				$c['lists'] = array_values( array_unique( $c['lists'] ) );
			}
			unset( $c );

			// ── Query 3: Email stats (aggregate per contact) ──
			$email_stats = $wpdb->get_results( $wpdb->prepare(
				"SELECT subscriber_id,
					COUNT(*) as sent,
					SUM(is_open) as opened,
					SUM(CASE WHEN click_counter > 0 THEN 1 ELSE 0 END) as clicked
				FROM {$prefix}fc_campaign_emails
				WHERE subscriber_id IN ({$in_clause})
				GROUP BY subscriber_id",
				...$contact_ids
			) );

			foreach ( $email_stats as $es ) {
				$sid = (int) $es->subscriber_id;
				if ( ! isset( $contacts[ $sid ] ) ) continue;
				$contacts[ $sid ]['emails'] = array(
					'sent'    => (int) $es->sent,
					'opened'  => (int) $es->opened,
					'clicked' => (int) $es->clicked,
				);
			}

			// ── Query 4: Automation funnels ──
			$funnel_table = $prefix . 'fc_funnel_metrics';
			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$funnel_table}'" ) ) {
				$funnels = $wpdb->get_results( $wpdb->prepare(
					"SELECT fm.subscriber_id, f.title as funnel_title
					FROM {$funnel_table} fm
					LEFT JOIN {$prefix}fc_funnels f ON fm.funnel_id = f.id
					WHERE fm.subscriber_id IN ({$in_clause})
					GROUP BY fm.subscriber_id, fm.funnel_id",
					...$contact_ids
				) );

				foreach ( $funnels as $f ) {
					$sid = (int) $f->subscriber_id;
					if ( ! isset( $contacts[ $sid ] ) ) continue;
					if ( ! in_array( $f->funnel_title, $contacts[ $sid ]['automations'], true ) ) {
						$contacts[ $sid ]['automations'][] = $f->funnel_title;
					}
				}
			}

			// ── Query 5: Custom events (aggregate per contact per event_key) ──
			$event_table = $prefix . 'fc_event_tracking';
			$has_events = $wpdb->get_var( "SHOW TABLES LIKE '{$event_table}'" );
			if ( $has_events ) {
				$events = $wpdb->get_results( $wpdb->prepare(
					"SELECT subscriber_id, event_key, COUNT(*) as cnt
					FROM {$event_table}
					WHERE subscriber_id IN ({$in_clause})
					GROUP BY subscriber_id, event_key",
					...$contact_ids
				) );

				foreach ( $events as $ev ) {
					$sid = (int) $ev->subscriber_id;
					if ( ! isset( $contacts[ $sid ] ) ) continue;
					$contacts[ $sid ]['event_keys'][] = $ev->event_key;
					$contacts[ $sid ]['event_counts'][ $ev->event_key ] = (int) $ev->cnt;
				}

				// Deduplicate event_keys.
				foreach ( $contacts as &$c ) {
					$c['event_keys'] = array_values( array_unique( $c['event_keys'] ) );
				}
				unset( $c );

				// ── Query 5b: Purchases from event tracking ──
				$purchases = $wpdb->get_results( $wpdb->prepare(
					"SELECT subscriber_id, title as product, value, created_at
					FROM {$event_table}
					WHERE subscriber_id IN ({$in_clause}) AND event_key = 'cart_order_paid'
					ORDER BY subscriber_id, created_at",
					...$contact_ids
				) );

				foreach ( $purchases as $p ) {
					$sid = (int) $p->subscriber_id;
					if ( ! isset( $contacts[ $sid ] ) ) continue;
					$contacts[ $sid ]['purchases'][] = array(
						'product' => $p->product,
						'date'    => substr( $p->created_at, 0, 10 ),
						'value'   => (float) $p->value,
					);
				}

				// ── Query 5c: First/last event dates ──
				$event_dates = $wpdb->get_results( $wpdb->prepare(
					"SELECT subscriber_id, MIN(created_at) as first_event, MAX(created_at) as last_event
					FROM {$event_table}
					WHERE subscriber_id IN ({$in_clause})
					GROUP BY subscriber_id",
					...$contact_ids
				) );

				foreach ( $event_dates as $ed ) {
					$sid = (int) $ed->subscriber_id;
					if ( ! isset( $contacts[ $sid ] ) ) continue;
					$contacts[ $sid ]['first_event'] = substr( $ed->first_event, 0, 10 );
					$contacts[ $sid ]['last_event'] = substr( $ed->last_event, 0, 10 );
				}
			}

			// ── Query 6: Notes (conditional) ──
			if ( 'none' !== $include_notes ) {
				$notes_table = $prefix . 'fc_subscriber_notes';

				if ( 'summary' === $include_notes ) {
					// Count + latest note per contact.
					$note_counts = $wpdb->get_results( $wpdb->prepare(
						"SELECT subscriber_id, COUNT(*) as note_count,
							MAX(created_at) as latest_date
						FROM {$notes_table}
						WHERE subscriber_id IN ({$in_clause})
						GROUP BY subscriber_id",
						...$contact_ids
					) );

					foreach ( $note_counts as $nc ) {
						$sid = (int) $nc->subscriber_id;
						if ( ! isset( $contacts[ $sid ] ) ) continue;

						// Get latest note title.
						$latest = $wpdb->get_row( $wpdb->prepare(
							"SELECT title FROM {$notes_table}
							WHERE subscriber_id = %d
							ORDER BY created_at DESC LIMIT 1",
							$sid
						) );

						$contacts[ $sid ]['notes'] = array(
							'count'  => (int) $nc->note_count,
							'latest' => array(
								'title' => $latest ? $latest->title : null,
								'date'  => substr( $nc->latest_date, 0, 10 ),
							),
						);
					}
				} elseif ( 'full' === $include_notes ) {
					// All notes with truncated descriptions.
					$all_notes = $wpdb->get_results( $wpdb->prepare(
						"SELECT subscriber_id, title, LEFT(description, 200) as description, created_at
						FROM {$notes_table}
						WHERE subscriber_id IN ({$in_clause})
						ORDER BY subscriber_id, created_at DESC",
						...$contact_ids
					) );

					$grouped = array();
					foreach ( $all_notes as $n ) {
						$sid = (int) $n->subscriber_id;
						$grouped[ $sid ][] = array(
							'title'       => $n->title,
							'description' => $n->description,
							'date'        => substr( $n->created_at, 0, 10 ),
						);
					}

					foreach ( $grouped as $sid => $notes ) {
						if ( isset( $contacts[ $sid ] ) ) {
							$contacts[ $sid ]['notes'] = array(
								'count' => count( $notes ),
								'items' => $notes,
							);
						}
					}
				}
			}

			return array(
				'cohort_size' => $id_count,
				'selector'    => array(
					'type'  => $input['selector_type'],
					'value' => $input['selector_value'],
				),
				'contacts'    => array_values( $contacts ),
			);
		},
	) );

	// ── Ability 2: Get Cohort Patterns ──────────────────────────────────────────

	$reg->read( 'fluent-crm/get-cohort-patterns', array(
		'label'       => 'Get Cohort Patterns',
		'description' => 'Aggregate statistics across a cohort: purchase frequency, time-to-first-purchase, tag progression paths, engagement signals before purchase, and conversion funnel. Returns compact stats (~2K tokens) for pattern recognition.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'selector_type', 'selector_value' ),
			'properties' => array(
				'selector_type' => array(
					'type'        => 'string',
					'description' => 'How to select contacts: tag, list, event_key, or contact_ids',
					'enum'        => array( 'tag', 'list', 'event_key', 'contact_ids' ),
				),
				'selector_value' => array(
					'type'        => 'string',
					'description' => 'The selector value',
				),
				'purchase_event_key' => array(
					'type'        => 'string',
					'description' => 'Event key for purchases (default: cart_order_paid)',
				),
				'max_contacts' => array(
					'type'        => 'integer',
					'description' => 'Maximum contacts to include (default: 100, max: 200)',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'cohort_size'   => array( 'type' => 'integer' ),
			'selector'      => array( 'type' => 'object' ),
			'date_range'    => array( 'type' => 'object' ),
			'purchase_stats'=> array( 'type' => 'object' ),
		) ),
		'callback' => function( $input ) {
			global $wpdb;
			$prefix = $wpdb->prefix;

			$max_contacts = isset( $input['max_contacts'] ) ? min( intval( $input['max_contacts'] ), 200 ) : 100;
			$purchase_key = $input['purchase_event_key'] ?? 'cart_order_paid';

			// Resolve cohort.
			$contact_ids = fluent_abilities_resolve_cohort(
				$input['selector_type'],
				$input['selector_value'],
				$max_contacts
			);

			if ( is_wp_error( $contact_ids ) ) {
				return $contact_ids;
			}

			$id_count = count( $contact_ids );
			$in_clause = fluent_abilities_in_clause( $contact_ids );

			$result = array(
				'cohort_size' => $id_count,
				'selector'    => array( 'type' => $input['selector_type'], 'value' => $input['selector_value'] ),
			);

			// ── Date range ──
			$date_range = $wpdb->get_row( $wpdb->prepare(
				"SELECT MIN(created_at) as earliest, MAX(created_at) as latest
				FROM {$prefix}fc_subscribers
				WHERE id IN ({$in_clause})",
				...$contact_ids
			) );
			$result['date_range'] = array(
				'earliest' => $date_range ? substr( $date_range->earliest, 0, 10 ) : null,
				'latest'   => $date_range ? substr( $date_range->latest, 0, 10 ) : null,
			);

			// ── Purchase stats ──
			$event_table = $prefix . 'fc_event_tracking';
			$has_events = $wpdb->get_var( "SHOW TABLES LIKE '{$event_table}'" );

			if ( $has_events ) {
				$purchase_stats = $wpdb->get_row( $wpdb->prepare(
					"SELECT COUNT(*) as total_purchases,
						COUNT(DISTINCT subscriber_id) as unique_buyers,
						SUM(value) as total_revenue
					FROM {$event_table}
					WHERE subscriber_id IN ({$in_clause}) AND event_key = %s",
					...array_merge( $contact_ids, array( $purchase_key ) )
				) );

				$unique_buyers = (int) $purchase_stats->unique_buyers;
				$result['purchase_stats'] = array(
					'total_purchases'      => (int) $purchase_stats->total_purchases,
					'unique_buyers'        => $unique_buyers,
					'avg_purchases_per_buyer' => $unique_buyers > 0 ? round( (int) $purchase_stats->total_purchases / $unique_buyers, 1 ) : 0,
					'total_revenue'        => (float) $purchase_stats->total_revenue,
				);

				// Product breakdown.
				$products = $wpdb->get_results( $wpdb->prepare(
					"SELECT title as product, COUNT(DISTINCT subscriber_id) as buyers,
						COUNT(*) as purchases, SUM(value) as revenue
					FROM {$event_table}
					WHERE subscriber_id IN ({$in_clause}) AND event_key = %s
					GROUP BY title
					ORDER BY buyers DESC",
					...array_merge( $contact_ids, array( $purchase_key ) )
				) );

				$result['purchase_stats']['products'] = array();
				foreach ( $products as $p ) {
					$result['purchase_stats']['products'][] = array(
						'product'  => $p->product,
						'buyers'   => (int) $p->buyers,
						'revenue'  => (float) $p->revenue,
					);
				}

				// ── Time to first purchase ──
				$time_to_purchase = $wpdb->get_results( $wpdb->prepare(
					"SELECT e.subscriber_id,
						DATEDIFF(MIN(e.created_at), s.created_at) as days_to_purchase
					FROM {$event_table} e
					JOIN {$prefix}fc_subscribers s ON e.subscriber_id = s.id
					WHERE e.subscriber_id IN ({$in_clause}) AND e.event_key = %s
					GROUP BY e.subscriber_id",
					...array_merge( $contact_ids, array( $purchase_key ) )
				) );

				if ( ! empty( $time_to_purchase ) ) {
					$days = array_map( function( $r ) { return (int) $r->days_to_purchase; }, $time_to_purchase );
					sort( $days );
					$count = count( $days );
					$mid = (int) floor( $count / 2 );
					$median = $count % 2 === 0 ? ( $days[ $mid - 1 ] + $days[ $mid ] ) / 2 : $days[ $mid ];

					$result['time_to_first_purchase'] = array(
						'median_days' => (int) round( $median ),
						'avg_days'    => (int) round( array_sum( $days ) / $count ),
						'min_days'    => $days[0],
						'max_days'    => $days[ $count - 1 ],
						'buyers'      => $count,
					);
				}

				// ── Engagement signals before first purchase ──
				$pre_purchase = $wpdb->get_results( $wpdb->prepare(
					"SELECT pre_events.event_key, COUNT(*) as occurrences
					FROM {$event_table} pre_events
					JOIN (
						SELECT subscriber_id, MIN(created_at) as first_purchase
						FROM {$event_table}
						WHERE subscriber_id IN ({$in_clause}) AND event_key = %s
						GROUP BY subscriber_id
					) fp ON pre_events.subscriber_id = fp.subscriber_id
					WHERE pre_events.created_at < fp.first_purchase
						AND pre_events.subscriber_id IN ({$in_clause})
						AND pre_events.event_key != %s
					GROUP BY pre_events.event_key
					ORDER BY occurrences DESC
					LIMIT 10",
					...array_merge(
						$contact_ids,
						array( $purchase_key ),
						$contact_ids,
						array( $purchase_key )
					)
				) );

				$result['engagement_before_purchase'] = array();
				foreach ( $pre_purchase as $pp ) {
					$result['engagement_before_purchase'][] = array(
						'signal'      => $pp->event_key,
						'occurrences' => (int) $pp->occurrences,
					);
				}
			}

			// ── Tag progression paths ──
			$tag_sequences = $wpdb->get_results( $wpdb->prepare(
				"SELECT p.subscriber_id, t.title, p.created_at
				FROM {$prefix}fc_subscriber_pivot p
				JOIN {$prefix}fc_tags t ON p.object_id = t.id
				WHERE p.subscriber_id IN ({$in_clause}) AND p.object_type LIKE '%%Tag'
				ORDER BY p.subscriber_id, p.created_at",
				...$contact_ids
			) );

			// Group tags per contact in chronological order.
			$paths_by_contact = array();
			foreach ( $tag_sequences as $ts ) {
				$sid = (int) $ts->subscriber_id;
				$paths_by_contact[ $sid ][] = $ts->title;
			}

			// Count unique paths.
			$path_counts = array();
			foreach ( $paths_by_contact as $path ) {
				$key = implode( ' > ', $path );
				$path_counts[ $key ] = ( $path_counts[ $key ] ?? 0 ) + 1;
			}
			arsort( $path_counts );

			$result['tag_progression'] = array();
			$i = 0;
			foreach ( $path_counts as $path_str => $count ) {
				if ( ++$i > 10 ) break;
				$result['tag_progression'][] = array(
					'path'  => explode( ' > ', $path_str ),
					'count' => $count,
				);
			}

			// ── Email engagement stats ──
			$email_agg = $wpdb->get_row( $wpdb->prepare(
				"SELECT COUNT(*) as total_sent,
					SUM(is_open) as total_opened,
					SUM(CASE WHEN click_counter > 0 THEN 1 ELSE 0 END) as total_clicked,
					AVG(is_open) as avg_open_rate
				FROM {$prefix}fc_campaign_emails
				WHERE subscriber_id IN ({$in_clause})",
				...$contact_ids
			) );

			if ( $email_agg ) {
				$total_sent = (int) $email_agg->total_sent;
				$result['email_engagement'] = array(
					'total_sent'    => $total_sent,
					'total_opened'  => (int) $email_agg->total_opened,
					'total_clicked' => (int) $email_agg->total_clicked,
					'open_rate'     => $total_sent > 0 ? round( (int) $email_agg->total_opened / $total_sent * 100, 1 ) . '%' : '0%',
					'click_rate'    => $total_sent > 0 ? round( (int) $email_agg->total_clicked / $total_sent * 100, 1 ) . '%' : '0%',
				);
			}

			// ── Conversion funnel summary ──
			$result['conversion_funnel'] = array(
				'total_contacts'   => $id_count,
				'with_email_open'  => 0,
				'with_event'       => 0,
				'with_purchase'    => 0,
				'repeat_purchasers' => 0,
			);

			$openers = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(DISTINCT subscriber_id) FROM {$prefix}fc_campaign_emails
				WHERE subscriber_id IN ({$in_clause}) AND is_open = 1",
				...$contact_ids
			) );
			$result['conversion_funnel']['with_email_open'] = (int) $openers;

			if ( $has_events ) {
				$with_event = $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(DISTINCT subscriber_id) FROM {$event_table}
					WHERE subscriber_id IN ({$in_clause})",
					...$contact_ids
				) );
				$result['conversion_funnel']['with_event'] = (int) $with_event;

				$buyers = $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(DISTINCT subscriber_id) FROM {$event_table}
					WHERE subscriber_id IN ({$in_clause}) AND event_key = %s",
					...array_merge( $contact_ids, array( $purchase_key ) )
				) );
				$result['conversion_funnel']['with_purchase'] = (int) $buyers;

				$repeat = $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM (
						SELECT subscriber_id FROM {$event_table}
						WHERE subscriber_id IN ({$in_clause}) AND event_key = %s
						GROUP BY subscriber_id HAVING COUNT(*) > 1
					) repeat_buyers",
					...array_merge( $contact_ids, array( $purchase_key ) )
				) );
				$result['conversion_funnel']['repeat_purchasers'] = (int) $repeat;
			}

			return $result;
		},
	) );

	// ── Ability 3: Get Funnel Conversion ────────────────────────────────────────

} ); // End wp_abilities_api_init
