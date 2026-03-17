<?php
/**
 * FluentCRM Advanced Query Abilities
 *
 * ORM-based read-only abilities for analytics and cross-entity queries
 * that go beyond the public API. Uses FluentCRM's Eloquent-style ORM
 * (wpfluent/framework) for complex aggregations and filtered reads.
 *
 * 6 abilities in the 'fluent-crm' category.
 *
 * @package Fluent_Abilities
 * @since 1.8.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Fluent_Abilities_Registrar( 'crm' );

	// =========================================================================
	// 1. COUNT CONTACTS BY STATUS
	// =========================================================================

	$reg->read( 'fluent-crm/count-contacts-by-status', array(
		'label'       => 'Count Contacts by Status',
		'description' => 'Count contacts grouped by status (subscribed, pending, unsubscribed, bounced, complained). Returns an array of { status, count } objects plus the total.',
		'category'    => 'fluent-crm',
		'output_schema' => fluent_abilities_schema_item_output( array(
			'breakdown' => array( 'type' => 'object' ),
			'total'     => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			global $wpdb;
			$table   = $wpdb->prefix . 'fc_subscribers';
			$results = $wpdb->get_results( "SELECT status, COUNT(*) as count FROM {$table} GROUP BY status" );

			$breakdown = array();
			$total     = 0;
			foreach ( $results as $row ) {
				$breakdown[] = array(
					'status' => $row->status,
					'count'  => (int) $row->count,
				);
				$total += (int) $row->count;
			}

			return array(
				'breakdown' => $breakdown,
				'total'     => $total,
			);
		},
	) );

	// =========================================================================
	// 2. ADVANCED CONTACT SEARCH
	// =========================================================================

	$reg->read( 'fluent-crm/search-contacts-advanced', array(
		'label'       => 'Advanced Contact Search',
		'description' => 'Search contacts across multiple fields with AND logic. Each filter specifies a field, operator (contains, equals, starts_with), and value. All filters must match (AND). Supports pagination.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'filters' ),
			'properties' => array_merge( array(
				'filters' => array(
					'type'        => 'array',
					'description' => 'Array of filter objects. Each has: field (email, first_name, last_name, phone, address_line_1, city, state, country), operator (contains, equals, starts_with), value.',
					'items'       => array(
						'type'       => 'object',
						'required'   => array( 'field', 'operator', 'value' ),
						'properties' => array(
							'field' => array(
								'type'        => 'string',
								'description' => 'Field to search',
								'enum'        => array( 'email', 'first_name', 'last_name', 'phone', 'address_line_1', 'city', 'state', 'country' ),
							),
							'operator' => array(
								'type'        => 'string',
								'description' => 'Search operator',
								'enum'        => array( 'contains', 'equals', 'starts_with' ),
							),
							'value' => array(
								'type'        => 'string',
								'description' => 'Value to search for',
							),
						),
					),
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'Optional status filter: subscribed, pending, unsubscribed, bounced, complained',
				),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'contacts' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'         => array( 'type' => 'integer' ),
							'email'      => array( 'type' => 'string' ),
							'first_name' => array( 'type' => array( 'string', 'null' ) ),
							'last_name'  => array( 'type' => array( 'string', 'null' ) ),
							'phone'      => array( 'type' => array( 'string', 'null' ) ),
							'city'       => array( 'type' => array( 'string', 'null' ) ),
							'state'      => array( 'type' => array( 'string', 'null' ) ),
							'country'    => array( 'type' => array( 'string', 'null' ) ),
							'status'     => array( 'type' => 'string' ),
							'created_at' => array( 'type' => array( 'string', 'null' ) ),
						),
					),
				),
				'total'           => array( 'type' => 'integer' ),
				'page'            => array( 'type' => 'integer' ),
				'per_page'        => array( 'type' => 'integer' ),
				'filters_applied' => array( 'type' => 'integer' ),
			),
		),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );

			if ( empty( $input['filters'] ) || ! is_array( $input['filters'] ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'At least one filter is required.' );
			}

			$allowed_fields = array( 'email', 'first_name', 'last_name', 'phone', 'address_line_1', 'city', 'state', 'country' );
			$allowed_ops    = array( 'contains', 'equals', 'starts_with' );

			$query = FluentCrm\App\Models\Subscriber::query();

			foreach ( $input['filters'] as $filter ) {
				if ( empty( $filter['field'] ) || empty( $filter['operator'] ) || ! isset( $filter['value'] ) ) {
					return fluent_abilities_error( 'ability_invalid_input', 'Each filter must have field, operator, and value.' );
				}

				$field    = sanitize_text_field( $filter['field'] );
				$operator = sanitize_text_field( $filter['operator'] );
				$value    = sanitize_text_field( $filter['value'] );

				if ( ! in_array( $field, $allowed_fields, true ) ) {
					return fluent_abilities_error( 'ability_invalid_input', "Field '{$field}' is not searchable. Allowed: " . implode( ', ', $allowed_fields ) );
				}

				if ( ! in_array( $operator, $allowed_ops, true ) ) {
					return fluent_abilities_error( 'ability_invalid_input', "Operator '{$operator}' is not valid. Allowed: contains, equals, starts_with" );
				}

				switch ( $operator ) {
					case 'contains':
						$query->where( $field, 'LIKE', '%' . $value . '%' );
						break;
					case 'equals':
						$query->where( $field, '=', $value );
						break;
					case 'starts_with':
						$query->where( $field, 'LIKE', $value . '%' );
						break;
				}
			}

			if ( ! empty( $input['status'] ) ) {
				$query->where( 'status', sanitize_text_field( $input['status'] ) );
			}

			$total    = $query->count();
			$contacts = $query->orderBy( 'id', 'DESC' )
				->offset( $pagination['offset'] )
				->limit( $pagination['per_page'] )
				->get();

			$items = array();
			foreach ( $contacts as $contact ) {
				$items[] = array(
					'id'         => $contact->id,
					'email'      => $contact->email,
					'first_name' => $contact->first_name,
					'last_name'  => $contact->last_name,
					'phone'      => $contact->phone,
					'city'       => $contact->city,
					'state'      => $contact->state,
					'country'    => $contact->country,
					'status'     => $contact->status,
					'created_at' => (string) $contact->created_at,
				);
			}

			return array(
				'contacts' => $items,
				'total'    => $total,
				'page'     => $pagination['page'],
				'per_page' => $pagination['per_page'],
				'filters_applied' => count( $input['filters'] ),
			);
		},
	) );

	// =========================================================================
	// 3. TAGS WITH SUBSCRIBER COUNTS
	// =========================================================================

	$reg->read( 'fluent-crm/list-tags-with-counts', array(
		'label'       => 'List Tags with Subscriber Counts',
		'description' => 'List all CRM tags with their subscriber counts. Each tag returns id, title, slug, subscriber_count, and created_at. Ordered by subscriber count descending.',
		'category'    => 'fluent-crm',
		'output_schema' => fluent_abilities_schema_collection_output( 'tags', array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
			'slug'  => array( 'type' => 'string' ),
			'count' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			global $wpdb;
			$tags_table = $wpdb->prefix . 'fc_tags';
			$pivot_table = $wpdb->prefix . 'fc_subscriber_pivot';

			$tags = $wpdb->get_results(
				"SELECT t.*, (SELECT COUNT(DISTINCT subscriber_id) FROM {$pivot_table} WHERE object_id = t.id AND object_type LIKE '%Tag%') as subscriber_count FROM {$tags_table} t ORDER BY subscriber_count DESC"
			);

			$items = array();
			foreach ( $tags as $tag ) {
				$items[] = array(
					'id'               => (int) $tag->id,
					'title'            => $tag->title,
					'slug'             => $tag->slug,
					'subscriber_count' => (int) $tag->subscriber_count,
					'created_at'       => (string) $tag->created_at,
				);
			}

			return array(
				'tags'  => $items,
				'total' => count( $items ),
			);
		},
	) );

	// =========================================================================
	// 4. LISTS WITH SUBSCRIBER COUNTS
	// =========================================================================

	$reg->read( 'fluent-crm/list-lists-with-counts', array(
		'label'       => 'List Lists with Subscriber Counts',
		'description' => 'List all CRM lists with their subscriber counts. Each list returns id, title, slug, subscriber_count, and created_at. Ordered by subscriber count descending.',
		'category'    => 'fluent-crm',
		'output_schema' => fluent_abilities_schema_collection_output( 'lists', array(
			'id'    => array( 'type' => 'integer' ),
			'title' => array( 'type' => 'string' ),
			'slug'  => array( 'type' => 'string' ),
			'count' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			global $wpdb;
			$lists_table = $wpdb->prefix . 'fc_lists';
			$pivot_table = $wpdb->prefix . 'fc_subscriber_pivot';

			$lists = $wpdb->get_results(
				"SELECT l.*, (SELECT COUNT(DISTINCT subscriber_id) FROM {$pivot_table} WHERE object_id = l.id AND object_type LIKE '%Lists%') as subscriber_count FROM {$lists_table} l ORDER BY subscriber_count DESC"
			);

			$items = array();
			foreach ( $lists as $list ) {
				$items[] = array(
					'id'               => (int) $list->id,
					'title'            => $list->title,
					'slug'             => $list->slug,
					'subscriber_count' => (int) $list->subscriber_count,
					'created_at'       => (string) $list->created_at,
				);
			}

			return array(
				'lists' => $items,
				'total' => count( $items ),
			);
		},
	) );

	// =========================================================================
	// 5. CONTACTS BY DATE RANGE
	// =========================================================================

	$reg->read( 'fluent-crm/list-contacts-by-date-range', array(
		'label'       => 'Get Contacts by Date Range',
		'description' => 'Get contacts created within a date range with optional status filter. Returns paginated results with a count of matching contacts.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'date_from', 'date_to' ),
			'properties' => array_merge( array(
				'date_from' => array(
					'type'        => 'string',
					'description' => 'Start date in YYYY-MM-DD format (inclusive)',
				),
				'date_to' => array(
					'type'        => 'string',
					'description' => 'End date in YYYY-MM-DD format (inclusive)',
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'Optional status filter: subscribed, pending, unsubscribed, bounced, complained',
				),
			), fluent_abilities_pagination_schema() ),
		),
		'output_schema' => fluent_abilities_schema_list_output( 'contacts', array(
			'id'         => array( 'type' => 'integer' ),
			'email'      => array( 'type' => 'string' ),
			'first_name' => array( 'type' => array( 'string', 'null' ) ),
			'last_name'  => array( 'type' => array( 'string', 'null' ) ),
			'status'     => array( 'type' => 'string' ),
			'created_at' => array( 'type' => array( 'string', 'null' ) ),
		) ),
		'callback' => function( $input ) {
			$pagination = fluent_abilities_pagination( $input );

			$date_from = sanitize_text_field( $input['date_from'] );
			$date_to   = sanitize_text_field( $input['date_to'] );

			// Validate date format.
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
				return fluent_abilities_error( 'ability_invalid_input', 'Dates must be in YYYY-MM-DD format.' );
			}

			// Extend date_to to end of day for inclusive range.
			$query = FluentCrm\App\Models\Subscriber::whereBetween(
				'created_at',
				array( $date_from . ' 00:00:00', $date_to . ' 23:59:59' )
			);

			if ( ! empty( $input['status'] ) ) {
				$query->where( 'status', sanitize_text_field( $input['status'] ) );
			}

			$total    = $query->count();
			$contacts = $query->orderBy( 'created_at', 'DESC' )
				->offset( $pagination['offset'] )
				->limit( $pagination['per_page'] )
				->get();

			$items = array();
			foreach ( $contacts as $contact ) {
				$items[] = array(
					'id'         => $contact->id,
					'email'      => $contact->email,
					'first_name' => $contact->first_name,
					'last_name'  => $contact->last_name,
					'status'     => $contact->status,
					'created_at' => (string) $contact->created_at,
				);
			}

			return array(
				'contacts'  => $items,
				'total'     => $total,
				'page'      => $pagination['page'],
				'per_page'  => $pagination['per_page'],
				'date_from' => $date_from,
				'date_to'   => $date_to,
			);
		},
	) );

	// =========================================================================
	// 6. RECENTLY ACTIVE CONTACTS
	// =========================================================================

	$reg->read( 'fluent-crm/list-contacts-recently-active', array(
		'label'       => 'Get Recently Active Contacts',
		'description' => 'Get contacts sorted by last activity. Filters by activity within N days (default 7). The last_activity column on the Subscriber model tracks the most recent interaction.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'days' => array(
					'type'        => 'integer',
					'description' => 'Number of days to look back (default: 7, max: 90)',
				),
				'status' => array(
					'type'        => 'string',
					'description' => 'Optional status filter: subscribed, pending, unsubscribed, bounced, complained',
				),
				'limit' => array(
					'type'        => 'integer',
					'description' => 'Maximum contacts to return (default: 50, max: 200)',
				),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'contacts'      => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'total_active'  => array( 'type' => 'integer' ),
			'returned'      => array( 'type' => 'integer' ),
			'days_lookback' => array( 'type' => 'integer' ),
			'cutoff_date'   => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$days  = isset( $input['days'] ) ? min( intval( $input['days'] ), 90 ) : 7;
			$limit = isset( $input['limit'] ) ? min( intval( $input['limit'] ), 200 ) : 50;

			$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

			$query = FluentCrm\App\Models\Subscriber::where( 'last_activity', '>=', $cutoff )
				->whereNotNull( 'last_activity' );

			if ( ! empty( $input['status'] ) ) {
				$query->where( 'status', sanitize_text_field( $input['status'] ) );
			}

			$total    = $query->count();
			$contacts = $query->orderBy( 'last_activity', 'DESC' )
				->limit( $limit )
				->get();

			$items = array();
			foreach ( $contacts as $contact ) {
				$items[] = array(
					'id'            => $contact->id,
					'email'         => $contact->email,
					'first_name'    => $contact->first_name,
					'last_name'     => $contact->last_name,
					'status'        => $contact->status,
					'last_activity' => $contact->last_activity,
					'created_at'    => (string) $contact->created_at,
				);
			}

			return array(
				'contacts'      => $items,
				'total_active'  => $total,
				'returned'      => count( $items ),
				'days_lookback' => $days,
				'cutoff_date'   => $cutoff,
			);
		},
	) );

	// =========================================================================
	// 7. CAMPAIGN STATS SUMMARY
	// Column names verified on production 2026-03-08:
	// fc_campaign_emails: is_open, click_counter, status, subscriber_id, campaign_id
	// =========================================================================

	$reg->read( 'fluent-crm/campaign-stats-summary', array(
		'label'       => 'Campaign Stats Summary',
		'description' => 'Aggregated email stats for all campaigns: sent count, open rate, click rate. Optionally filter by campaign status or date range. Returns one row per campaign with calculated rates.',
		'category'    => 'fluent-crm',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'status'    => array( 'type' => 'string', 'description' => 'Filter by campaign status: draft, scheduled, working, archived' ),
				'date_from' => array( 'type' => 'string', 'description' => 'Filter campaigns scheduled from this date (YYYY-MM-DD)' ),
				'date_to'   => array( 'type' => 'string', 'description' => 'Filter campaigns scheduled to this date (YYYY-MM-DD)' ),
				'limit'     => array( 'type' => 'integer', 'description' => 'Max campaigns to return (default: 50, max: 200)' ),
			),
		),
		'output_schema' => fluent_abilities_schema_item_output( array(
			'campaigns'    => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'total'        => array( 'type' => 'integer' ),
			'generated_at' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			global $wpdb;
			$prefix = $wpdb->prefix;

			$limit = isset( $input['limit'] ) ? min( intval( $input['limit'] ), 200 ) : 50;

			// Build campaign query with optional filters.
			$campaign_where = 'WHERE 1=1';
			$campaign_params = array();

			if ( ! empty( $input['status'] ) ) {
				$campaign_where .= ' AND c.status = %s';
				$campaign_params[] = sanitize_text_field( $input['status'] );
			}
			if ( ! empty( $input['date_from'] ) ) {
				$campaign_where .= ' AND c.scheduled_at >= %s';
				$campaign_params[] = sanitize_text_field( $input['date_from'] ) . ' 00:00:00';
			}
			if ( ! empty( $input['date_to'] ) ) {
				$campaign_where .= ' AND c.scheduled_at <= %s';
				$campaign_params[] = sanitize_text_field( $input['date_to'] ) . ' 23:59:59';
			}

			// Aggregate email stats joined to campaigns.
			$sql = "SELECT
				c.id,
				c.title,
				c.status,
				c.type,
				c.scheduled_at,
				COUNT(ce.id)                                             AS sent,
				SUM(ce.is_open)                                          AS opened,
				SUM(CASE WHEN ce.click_counter > 0 THEN 1 ELSE 0 END)   AS clicked,
				SUM(ce.click_counter)                                    AS total_clicks
			FROM {$prefix}fc_campaigns c
			LEFT JOIN {$prefix}fc_campaign_emails ce ON c.id = ce.campaign_id
			{$campaign_where}
			GROUP BY c.id
			ORDER BY c.id DESC
			LIMIT %d";

			$params = array_merge( $campaign_params, array( $limit ) );
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );

			$campaigns = array();
			foreach ( $rows as $row ) {
				$sent    = (int) $row->sent;
				$opened  = (int) $row->opened;
				$clicked = (int) $row->clicked;
				$campaigns[] = array(
					'id'           => (int) $row->id,
					'title'        => $row->title,
					'status'       => $row->status,
					'type'         => $row->type,
					'scheduled_at' => $row->scheduled_at,
					'sent'         => $sent,
					'opened'       => $opened,
					'clicked'      => $clicked,
					'total_clicks' => (int) $row->total_clicks,
					'open_rate'    => $sent > 0 ? round( $opened / $sent * 100, 1 ) . '%' : '0%',
					'click_rate'   => $sent > 0 ? round( $clicked / $sent * 100, 1 ) . '%' : '0%',
				);
			}

			return array(
				'campaigns'    => $campaigns,
				'total'        => count( $campaigns ),
				'generated_at' => current_time( 'mysql' ),
			);
		},
	) );

}, 100 ); // end wp_abilities_api_init
