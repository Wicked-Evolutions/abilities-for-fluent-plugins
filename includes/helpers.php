<?php
/**
 * Shared Helper Functions
 *
 * Helpers used across multiple Fluent ability modules.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolve a user across Fluent products.
 *
 * Given an email or user ID, find the corresponding records in each active module.
 *
 * @param string|int $identifier Email address or WordPress user ID.
 * @return array Keyed by module name, each containing the user record or null.
 */
function fluent_abilities_resolve_user( $identifier ) {
	$result = array();

	// Resolve to email.
	if ( is_numeric( $identifier ) ) {
		$user = get_userdata( (int) $identifier );
		$email = $user ? $user->user_email : null;
		$result['wp_user_id'] = (int) $identifier;
		$result['wp_user'] = $user ? $user->display_name : null;
	} else {
		$email = sanitize_email( $identifier );
		$user = get_user_by( 'email', $email );
		$result['wp_user_id'] = $user ? $user->ID : null;
		$result['wp_user'] = $user ? $user->display_name : null;
	}

	if ( ! $email ) {
		return $result;
	}

	$result['email'] = $email;

	// FluentCRM contact.
	if ( fluent_abilities_module_enabled( 'crm' ) && class_exists( 'FluentCrm' ) && function_exists( 'FluentCrmApi' ) ) {
		$contact = FluentCrmApi( 'contacts' )->getContactByUserRef( $email );
		$result['crm_contact'] = $contact ? array(
			'id'     => $contact->id,
			'status' => $contact->status,
			'name'   => trim( $contact->first_name . ' ' . $contact->last_name ),
		) : null;
	}

	// FluentCommunity member.
	if ( fluent_abilities_module_enabled( 'community' ) && class_exists( 'FluentCommunity\\App\\App' ) && $result['wp_user_id'] ) {
		$member = \FluentCommunity\App\Models\XProfile::where( 'user_id', $result['wp_user_id'] )->first();
		$result['community_member'] = $member ? array(
			'id'           => $member->id,
			'display_name' => $member->display_name,
			'status'       => $member->status,
		) : null;
	}

	return $result;
}

/**
 * Format a WP_Error or exception as a standard ability error response.
 *
 * @param string $code    Error code.
 * @param string $message Error message.
 * @return WP_Error
 */
function fluent_abilities_error( $code, $message ) {
	return new WP_Error( $code, $message );
}

/**
 * Coerce a vendor Collection (or any framework-internal container) to a plain
 * PHP array, so the result is safe to pass to array_map(), array_filter(), etc.
 *
 * Vendor query builders (wpFluent, FluentCRM, FluentBoards) return Collection
 * objects — implementing Countable + IteratorAggregate but NOT array. Passing
 * such a Collection to PHP's array_map() raises a PHP TypeError. This is the
 * V5 framework-boundary coercion the v1.4.0 cold-start re-test found in
 * 11 FluentBoards sites (Pattern P-A).
 *
 * Idempotent on arrays. Null is normalized to empty array.
 *
 * @param mixed $value Collection, array, Traversable, or null.
 * @return array Plain array; empty array for null. Keys preserved.
 */
function fluent_abilities_to_array( $value ) {
	if ( is_array( $value ) ) {
		return $value;
	}
	if ( null === $value ) {
		return array();
	}
	if ( is_object( $value ) && method_exists( $value, 'toArray' ) ) {
		return $value->toArray();
	}
	if ( is_object( $value ) && method_exists( $value, 'all' ) ) {
		return $value->all();
	}
	if ( $value instanceof Traversable ) {
		return iterator_to_array( $value );
	}
	return (array) $value;
}

/**
 * Safely convert an Eloquent model attribute to a plain array/scalar.
 *
 * Eloquent cast attributes (settings, meta) may contain nested Collections,
 * Carbon dates, or objects with circular references that crash wp_json_encode().
 * This forces a clean round-trip through JSON to produce a plain array.
 *
 * @param mixed $value The model attribute value.
 * @return array|null  Plain array or null.
 */
function fluent_abilities_safe_array( $value ) {
	if ( is_null( $value ) ) {
		return null;
	}
	if ( is_scalar( $value ) ) {
		return $value;
	}
	// If it's an Eloquent model or Collection, use its own toArray().
	if ( is_object( $value ) && method_exists( $value, 'toArray' ) ) {
		$value = $value->toArray();
	}
	// Convert remaining objects to arrays recursively.
	if ( is_object( $value ) ) {
		$value = (array) $value;
	}
	if ( ! is_array( $value ) ) {
		return null;
	}
	// Recursively sanitize nested values.
	return array_map( 'fluent_abilities_safe_array', $value );
}

/**
 * Project a proxied vendor response through the framework-object boundary.
 *
 * FluentCRM REST controllers proxied via rest_do_request() may return an
 * Eloquent model / Collection. WP serializes its public surface, leaking
 * model internals (`incrementing`, `exists`, `wasRecentlyCreated`,
 * `timestamps`, `preventsLazyLoading`, `usesUniqueIds`, ...) into the
 * ability response (V5 framework-object boundary; v1.4.0 cold-start
 * Pattern P-G, ~17 FluentCRM slugs). This reuses
 * fluent_abilities_safe_array() (deep ->toArray() projection — attributes
 * only) so no per-slug projection logic is duplicated. WP_Error passes
 * through untouched so V10 typed errors are preserved.
 *
 * @param mixed $data Proxy result (model, Collection, array, scalar, WP_Error).
 * @return mixed Plain array/scalar projection, or the WP_Error untouched.
 */
function fluent_abilities_project_response( $data ) {
	if ( is_wp_error( $data ) ) {
		return $data;
	}
	return fluent_abilities_safe_array( $data );
}

/**
 * Unwrap a Laravel/wpFluent paginator response to the canonical
 * { <items_key>: [...], total, page, per_page } shape.
 *
 * Vendor REST controllers proxied via rest_do_request() return the raw
 * paginator structure — LengthAwarePaginator serialized with its internal
 * keys (`current_page`, `last_page`, `per_page`, `onEachSide`, `path`,
 * `links`, `from`, `to`, `first_page_url`, ...). Only the rows + canonical
 * pagination scalars may cross the ability boundary (V5; v1.4.0 cold-start
 * Pattern P-J, 5 FluentCRM slugs). Single shared unwrap — not duplicated
 * per slug. WP_Error passes through untouched.
 *
 * Accepts a paginator array (`data` + `current_page`/`per_page`/`total`),
 * an already-clean `{ items_key: [...] }`, a bare list, or null.
 *
 * @param mixed  $data      Proxy result.
 * @param string $items_key Key to expose the row list under (e.g. 'campaigns').
 * @return array|WP_Error   { items_key: array, total, page, per_page }.
 */
function fluent_abilities_unwrap_paginator( $data, $items_key = 'items' ) {
	if ( is_wp_error( $data ) ) {
		return $data;
	}
	$data = fluent_abilities_to_array( $data );

	// A Laravel/wpFluent paginator serializes (via ->toArray()) to an assoc
	// array carrying `data` plus meta (`current_page`,`per_page`,`last_page`,
	// `total`,`links`,`path`,`from`,`to`,...). The vendor returns it bare at
	// the top level OR — critically — as a paginator OBJECT nested under the
	// items key (`return ['campaigns' => $query->paginate()]`, verified in
	// FluentCampaign RecurringCampaignController::getCampaigns). The shape
	// test MUST run on the array form, so coerce objects first.
	$is_paginator = static function ( $v ) {
		return is_array( $v )
			&& array_key_exists( 'data', $v )
			&& ( isset( $v['current_page'] ) || isset( $v['per_page'] ) || isset( $v['last_page'] ) || isset( $v['links'] ) || isset( $v['path'] ) || isset( $v['total'] ) );
	};

	// Resolve the row list + pagination meta by descending to the paginator
	// wherever it sits: bare at the top level, under the items key as a
	// paginator OBJECT or its array form, or wrapped one (or more) levels
	// deep inside a single-element list — verified live: the FluentCRM
	// campaign-unsubscribers / sequences-for-subscriber endpoints return
	// `[ '<key>' => [ <paginator> ] ]` (a one-element list wrapping the
	// paginator), which the prior single-pass logic mis-rendered as
	// `<key>:[<whole paginator>]`. Never flatten paginator meta into rows.
	$meta      = $data;
	$candidate = $data;
	if ( is_array( $data ) && array_key_exists( $items_key, $data ) ) {
		$candidate = $data[ $items_key ];
	} elseif ( is_array( $data ) && ! $is_paginator( $data )
		&& array_key_exists( 'data', $data ) && ! fluent_abilities_is_list( $data ) ) {
		$candidate = $data['data'];
	} elseif ( is_array( $data ) && ! $is_paginator( $data )
		&& ! fluent_abilities_is_list( $data ) && 1 === count( $data ) ) {
		// Vendor wraps the result under ITS OWN single key, which need not
		// equal our schema items_key — verified live: CampaignAnalytics
		// Controller::getUnsubscribers returns `['unsubscribes' => $paginator]`
		// while the ability's items_key is `unsubscribers`. Descend into the
		// sole wrapper value; the loop below resolves the paginator from it.
		$candidate = reset( $data );
	}

	for ( $guard = 0; $guard < 6; $guard++ ) {
		$candidate = fluent_abilities_to_array( $candidate );
		if ( $is_paginator( $candidate ) ) {
			$meta      = $candidate;
			$candidate = $candidate['data'];
			continue;
		}
		// Single-element list wrapping a paginator → descend into it.
		if ( is_array( $candidate ) && 1 === count( $candidate )
			&& array_key_exists( 0, $candidate )
			&& $is_paginator( fluent_abilities_to_array( $candidate[0] ) ) ) {
			$candidate = $candidate[0];
			continue;
		}
		// Single-key assoc wrapper whose sole value resolves to a paginator —
		// the vendor-key-vs-items_key mismatch case nested one level deeper
		// (e.g. `['<vendorkey>' => [ <paginator> ]]`). Bounded to the one
		// value and only when it actually resolves to a paginator, so a
		// legitimate single-row assoc result is never mis-descended.
		if ( is_array( $candidate ) && ! fluent_abilities_is_list( $candidate )
			&& ! $is_paginator( $candidate ) && 1 === count( $candidate ) ) {
			$sole = fluent_abilities_to_array( reset( $candidate ) );
			if ( $is_paginator( $sole )
				|| ( is_array( $sole ) && 1 === count( $sole ) && array_key_exists( 0, $sole )
					&& $is_paginator( fluent_abilities_to_array( $sole[0] ) ) ) ) {
				$candidate = $sole;
				continue;
			}
		}
		break;
	}

	$rows = fluent_abilities_safe_array( fluent_abilities_to_array( $candidate ) );
	$rows = is_array( $rows ) ? array_values( $rows ) : array();

	$total = isset( $meta['total'] ) ? (int) $meta['total'] : count( $rows );
	$page  = isset( $meta['current_page'] ) ? (int) $meta['current_page']
		: ( isset( $meta['page'] ) ? (int) $meta['page'] : 1 );
	$per   = isset( $meta['per_page'] ) ? (int) $meta['per_page'] : count( $rows );

	return array(
		$items_key => $rows,
		'total'    => $total,
		'page'     => $page,
		'per_page' => $per,
	);
}

/**
 * True when $a is a sequential 0-indexed list (PHP 7.4-safe array_is_list).
 *
 * @param mixed $a Value to test.
 * @return bool
 */
function fluent_abilities_is_list( $a ) {
	if ( ! is_array( $a ) ) {
		return false;
	}
	if ( function_exists( 'array_is_list' ) ) {
		return array_is_list( $a );
	}
	$i = 0;
	foreach ( $a as $k => $_ ) {
		if ( $k !== $i++ ) {
			return false;
		}
	}
	return true;
}

/**
 * Normalize a proxied vendor list response so the schema-declared
 * collection key is ALWAYS a sequential array.
 *
 * P-H empty-state branch (v1.4.0 cold-start): the registrar declares
 * `output_schema` with `<key>: array`, but the vendor REST controller
 * returns the collection as `null`, `{}`, or an id-keyed object when the
 * set is empty or sparse — the ability output validator then rejects a
 * valid vendor response. The declared array contract is correct; only the
 * empty/sparse representation drifts, so we normalize at the boundary
 * (NOT weaken the schema). Object/keyed maps are flattened to their
 * values (array_values). WP_Error passes through untouched.
 *
 * Use ONLY where the per-slug decision is "normalize empty-state"; slugs
 * where the vendor genuinely returns an alternative shape get a
 * union-declared output_schema instead (documented per slug in vendor-map).
 *
 * @param mixed  $data Proxy result.
 * @param string $key  Schema-declared collection key (e.g. 'logs').
 * @return mixed Array with $key normalized to a list, or WP_Error.
 */
function fluent_abilities_normalize_collection( $data, $key ) {
	if ( is_wp_error( $data ) ) {
		return $data;
	}
	$data = fluent_abilities_to_array( $data );

	$is_paginator = static function ( $v ) {
		return is_array( $v )
			&& array_key_exists( 'data', $v )
			&& ( isset( $v['current_page'] ) || isset( $v['per_page'] ) || isset( $v['last_page'] ) || isset( $v['links'] ) || isset( $v['path'] ) || isset( $v['total'] ) );
	};

	if ( $is_paginator( $data ) ) {
		$list = $data['data'];
	} elseif ( array_key_exists( $key, $data ) ) {
		// Coerce objects (vendor may nest a paginator OBJECT under the key)
		// to array BEFORE the paginator-shape test.
		$candidate = fluent_abilities_to_array( $data[ $key ] );
		$list = $is_paginator( $candidate ) ? $candidate['data'] : $candidate;
	} elseif ( array_key_exists( 'data', $data ) ) {
		$list = $data['data'];
	} else {
		// Whole payload is the collection (bare list, keyed map, or null/{}).
		$list = $data;
	}

	$list = fluent_abilities_safe_array( fluent_abilities_to_array( $list ) );
	$data[ $key ] = is_array( $list ) ? array_values( $list ) : array();

	return $data;
}

/**
 * Paginate results with standard parameters.
 *
 * @param array $input  Input with optional 'page' and 'per_page'.
 * @param int   $default_per_page Default items per page.
 * @return array [ 'page' => int, 'per_page' => int, 'offset' => int ]
 */
function fluent_abilities_pagination( $input, $default_per_page = 20 ) {
	$input    = (array) $input;
	$page     = max( 1, (int) ( $input['page'] ?? 1 ) );
	$per_page = min( 100, max( 1, (int) ( $input['per_page'] ?? $default_per_page ) ) );
	$offset   = ( $page - 1 ) * $per_page;

	return array(
		'page'     => $page,
		'per_page' => $per_page,
		'offset'   => $offset,
	);
}

/**
 * Standard pagination input schema properties.
 *
 * @return array Schema properties for page and per_page.
 */
function fluent_abilities_pagination_schema() {
	return array(
		'page' => array(
			'type'        => 'integer',
			'description' => 'Page number (default: 1)',
			'default'     => 1,
		),
		'per_page' => array(
			'type'        => 'integer',
			'description' => 'Items per page, max 100 (default: 20)',
			'default'     => 20,
		),
	);
}

/**
 * Check which Fluent modules are currently active.
 *
 * @return array Keyed by module slug, values are version strings or true.
 */
function fluent_abilities_active_modules() {
	$checks = array(
		'crm'        => 'FLUENTCRM_PLUGIN_VERSION',
		'community'  => 'FLUENT_COMMUNITY_PLUGIN_VERSION',
		'forms'      => 'FLUENTFORM_VERSION',
		'support'    => 'FLUENT_SUPPORT_VERSION',
		'boards'     => 'FLUENT_BOARDS_PLUGIN_VERSION',
		'booking'    => 'FLUENT_BOOKING_VERSION',
		'smtp'       => 'FLUENTMAIL_PLUGIN_VERSION',
		'auth'       => 'FLUENT_AUTH_VERSION',
		'snippets'   => 'FLUENT_SNIPPETS_PLUGIN_VERSION',
		'messaging'  => 'FLUENT_MESSAGING_CHAT_VERSION',
		'affiliate'  => 'FLUENT_AFFILIATE_VERSION',
	);

	$active = array();
	foreach ( $checks as $slug => $constant ) {
		if ( defined( $constant ) ) {
			$active[ $slug ] = constant( $constant );
		}
	}

	return $active;
}

/**
 * Resolve a cohort of contact IDs from a selector.
 *
 * @param string     $selector_type  One of: tag, event_key, list, contact_ids.
 * @param string|int $selector_value Tag ID, event_key string, list ID, or comma-separated contact IDs.
 * @param int        $max_contacts   Maximum contacts to return (safety limit).
 * @return array|WP_Error Array of integer contact IDs, or WP_Error on failure.
 */
function fluent_abilities_resolve_cohort( $selector_type, $selector_value, $max_contacts = 100 ) {
	global $wpdb;
	$prefix = $wpdb->prefix;

	switch ( $selector_type ) {
		case 'tag':
			$tag_id = intval( $selector_value );
			$ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT DISTINCT subscriber_id FROM {$prefix}fc_subscriber_pivot
				WHERE object_type LIKE '%%Tag' AND object_id = %d
				LIMIT %d",
				$tag_id,
				$max_contacts
			) );
			break;

		case 'list':
			$list_id = intval( $selector_value );
			$ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT DISTINCT subscriber_id FROM {$prefix}fc_subscriber_pivot
				WHERE object_type LIKE '%%Lists' AND object_id = %d
				LIMIT %d",
				$list_id,
				$max_contacts
			) );
			break;

		case 'event_key':
			$event_table = $prefix . 'fc_event_tracking';
			if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$event_table}'" ) ) {
				return fluent_abilities_error( 'not_found', 'Event tracking table not found.' );
			}
			$ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT DISTINCT subscriber_id FROM {$event_table}
				WHERE event_key = %s
				LIMIT %d",
				sanitize_text_field( $selector_value ),
				$max_contacts
			) );
			break;

		case 'contact_ids':
			$raw_ids = array_map( 'intval', explode( ',', $selector_value ) );
			$ids = array_filter( $raw_ids, function( $id ) { return $id > 0; } );
			$ids = array_slice( array_values( $ids ), 0, $max_contacts );
			break;

		default:
			return fluent_abilities_error( 'ability_invalid_input', "Invalid selector_type: {$selector_type}. Use tag, list, event_key, or contact_ids." );
	}

	if ( empty( $ids ) ) {
		return fluent_abilities_error( 'not_found', 'No contacts found for the given selector.' );
	}

	return array_map( 'intval', $ids );
}
