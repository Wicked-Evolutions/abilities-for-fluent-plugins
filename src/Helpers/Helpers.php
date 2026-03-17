<?php
/**
 * Shared Helpers — Namespaced Static Interface
 *
 * Provides a namespaced static interface for helper functions.
 * Procedural functions are defined in includes/helpers.php for backward compat.
 * New code can use this class; existing module files continue using functions directly.
 *
 * @package WickedEvolutions\AbilitiesForFluent
 */

namespace WickedEvolutions\AbilitiesForFluent\Helpers;

defined( 'ABSPATH' ) || exit;

class Helpers {

	/**
	 * Resolve a user across Fluent products.
	 *
	 * @param string|int $identifier Email address or WordPress user ID.
	 * @return array Keyed by module name.
	 */
	public static function resolve_user( $identifier ) {
		return fluent_abilities_resolve_user( $identifier );
	}

	/**
	 * Format a WP_Error or exception as a standard ability error response.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @return \WP_Error
	 */
	public static function error( $code, $message ) {
		return fluent_abilities_error( $code, $message );
	}

	/**
	 * Paginate results with standard parameters.
	 *
	 * @param array $input            Input with optional 'page' and 'per_page'.
	 * @param int   $default_per_page Default items per page.
	 * @return array [ 'page', 'per_page', 'offset' ]
	 */
	public static function pagination( $input, $default_per_page = 20 ) {
		return fluent_abilities_pagination( $input, $default_per_page );
	}

	/**
	 * Standard pagination input schema properties.
	 *
	 * @return array Schema properties for page and per_page.
	 */
	public static function pagination_schema() {
		return fluent_abilities_pagination_schema();
	}

	/**
	 * Resolve a cohort of contact IDs from a selector.
	 *
	 * @param string     $selector_type  One of: tag, event_key, list, contact_ids.
	 * @param string|int $selector_value Tag ID, event_key string, list ID, or CSV contact IDs.
	 * @param int        $max_contacts   Maximum contacts to return.
	 * @return array|\WP_Error Array of integer contact IDs, or WP_Error on failure.
	 */
	public static function resolve_cohort( $selector_type, $selector_value, $max_contacts = 100 ) {
		return fluent_abilities_resolve_cohort( $selector_type, $selector_value, $max_contacts );
	}

	/**
	 * Check which Fluent modules are currently active.
	 *
	 * @return array Keyed by module slug, values are version strings.
	 */
	public static function active_modules() {
		return fluent_abilities_active_modules();
	}
}
