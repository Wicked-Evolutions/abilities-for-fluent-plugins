<?php
/**
 * Security Layer — Capabilities & Module Toggles
 *
 * Namespaced wrapper for the security layer.
 * Procedural functions are defined in includes/security.php for backward compat.
 * This class provides a static interface for new code.
 *
 * @package WickedEvolutions\AbilitiesForFluent
 */

namespace WickedEvolutions\AbilitiesForFluent\Core;

defined( 'ABSPATH' ) || exit;

class Security {

	/**
	 * Check if the current user has the required capability for a module action.
	 *
	 * @param string $module Module name (e.g., 'crm', 'community').
	 * @param string $level  Permission level: 'read', 'write', 'delete', 'send', 'admin'.
	 * @return bool
	 */
	public static function user_can( $module, $level = 'read' ) {
		return fluent_abilities_user_can( $module, $level );
	}

	/**
	 * Check if a specific module is enabled via admin toggles.
	 *
	 * @param string $module Module slug.
	 * @return bool
	 */
	public static function module_enabled( $module ) {
		return fluent_abilities_module_enabled( $module );
	}

	/**
	 * Get the list of enabled modules.
	 *
	 * @return array Array of enabled module slugs.
	 */
	public static function get_enabled_modules() {
		return fluent_abilities_get_enabled_modules();
	}

	/**
	 * Register capabilities on plugin activation.
	 */
	public static function register_caps() {
		fluent_abilities_register_caps();
	}

	/**
	 * Validate a URL for safe server-side fetching (SSRF protection).
	 *
	 * @param string $url The URL to validate.
	 * @return string|\WP_Error Sanitized URL on success, WP_Error on failure.
	 */
	public static function validate_url( $url ) {
		return fluent_abilities_validate_url( $url );
	}
}
