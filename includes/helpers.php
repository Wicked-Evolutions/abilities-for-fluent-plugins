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
