<?php
/**
 * Security Layer — Capabilities & Module Toggles
 *
 * Layer 1: Module toggles (admin UI, stored in options)
 * Layer 2: Custom capabilities per module (read/write/delete/send)
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

/**
 * Custom capabilities for each Fluent module.
 *
 * Pattern: fluent_{module}_{level}
 * Levels: read, write, delete, plus module-specific (e.g., send for CRM).
 *
 * @return array Module => array of capabilities.
 */
function fluent_abilities_get_caps() {
	return array(
		'crm' => array(
			'fluent_crm_read',
			'fluent_crm_write',
			'fluent_crm_delete',
			'fluent_crm_send',    // Campaign/sequence sending — highest risk.
		),
		'community' => array(
			'fluent_community_read',
			'fluent_community_write',
			'fluent_community_delete',
			'fluent_community_admin', // Space/course management.
		),
		'forms' => array(
			'fluent_forms_read',
			'fluent_forms_write',
			'fluent_forms_delete',
		),
		'support' => array(
			'fluent_support_read',
			'fluent_support_write',
			'fluent_support_delete',
		),
		'boards' => array(
			'fluent_boards_read',
			'fluent_boards_write',
			'fluent_boards_delete',
		),
		'booking' => array(
			'fluent_booking_read',
			'fluent_booking_write',
			'fluent_booking_delete',
			'fluent_booking_admin', // License/global settings/integration credentials.
		),
		'smtp' => array(
			'fluent_smtp_read',
			'fluent_smtp_write',
		),
		'auth' => array(
			'fluent_auth_read',
		),
		'snippets' => array(
			'fluent_snippets_read',
			'fluent_snippets_write',
			'fluent_snippets_delete',
		),
		'messaging' => array(
			'fluent_messaging_read',
			'fluent_messaging_write',
		),
		'cart' => array(
			'fluent_cart_read',
			'fluent_cart_write',
			'fluent_cart_delete',
		),
		'affiliate' => array(
			'fluent_affiliate_read',
			'fluent_affiliate_write',
			'fluent_affiliate_delete',
		),
		'player' => array(
			'fluent_player_read',
			'fluent_player_write',
			'fluent_player_delete',
		),
	);
}

/**
 * Register capabilities on plugin activation.
 *
 * Grants all caps to administrators. Other roles must be configured manually.
 */
function fluent_abilities_register_caps() {
	$admin_role = get_role( 'administrator' );
	if ( ! $admin_role ) {
		return;
	}

	$all_caps = fluent_abilities_get_caps();
	foreach ( $all_caps as $module_caps ) {
		foreach ( $module_caps as $cap ) {
			$admin_role->add_cap( $cap );
		}
	}
}

/**
 * Self-healing capability check.
 *
 * register_activation_hook does NOT fire for `wp plugin install --activate-network`
 * or when a new site is created on an existing multisite network. This runs once
 * per version bump (or on first load) to ensure caps are always registered.
 *
 * Hooked to 'init' in the main plugin file.
 */
function fluent_abilities_maybe_register_caps() {
	$stored_version = get_option( 'fluent_abilities_caps_version', '' );
	if ( $stored_version === FLUENT_ABILITIES_VERSION ) {
		return; // Already registered for this version.
	}

	fluent_abilities_register_caps();
	update_option( 'fluent_abilities_caps_version', FLUENT_ABILITIES_VERSION, true );
}

/**
 * Check if the current user has the required capability for a module action.
 *
 * Authenticated request → standard `current_user_can( "fluent_{$module}_{$level}" )`.
 *
 * Anonymous WP-CLI / stdio bridge invocation (user ID 0) → strict deny by default
 * for every level. The previous behavior (module-toggle-only authorization) let
 * destructive abilities run as soon as the module toggle was on, regardless of
 * level — see issue #19.
 *
 * One-release backwards-compatibility shim: setting environment variable
 * `FLUENT_ABILITIES_CLI_ALLOW_ANONYMOUS_READ=1` re-enables anonymous read-only
 * access (`level === 'read'`) for enabled modules. Write/delete/send/admin still
 * deny. This shim is a migration aid and will be removed in v1.2.0.
 *
 * @param string $module Module name (e.g., 'crm', 'community').
 * @param string $level  Permission level: 'read', 'write', 'delete', 'send', 'admin'.
 * @return bool
 */
function fluent_abilities_user_can( $module, $level = 'read' ) {
	$cap = "fluent_{$module}_{$level}";

	// Standard path: user is authenticated, check capability.
	if ( get_current_user_id() > 0 ) {
		return current_user_can( $cap );
	}

	// Anonymous CLI / stdio bridge: deny by default. The one-release env-var shim
	// allows read-only fallback for enabled modules; everything else denies.
	if ( fluent_abilities_is_anonymous_cli() ) {
		if ( 'read' === $level && '1' === getenv( 'FLUENT_ABILITIES_CLI_ALLOW_ANONYMOUS_READ' ) ) {
			return fluent_abilities_module_enabled( $module );
		}
		return false;
	}

	return false;
}

/**
 * True when the current request is an anonymous WP-CLI / stdio bridge invocation
 * — i.e. running under WP-CLI with no resolved WordPress user.
 *
 * @return bool
 */
function fluent_abilities_is_anonymous_cli() {
	return 0 === get_current_user_id() && defined( 'WP_CLI' ) && WP_CLI;
}

/**
 * Check if a specific module is enabled via admin toggles (Security Layer 1).
 *
 * Unlike the file-load check in the main plugin (which prevents ability registration),
 * this function is called at runtime inside execute_callback to enforce toggles
 * for cross-module abilities that query multiple modules' data.
 *
 * @param string $module Module slug (e.g., 'crm', 'community').
 * @return bool True if the module is enabled.
 */
function fluent_abilities_module_enabled( $module ) {
	$enabled = fluent_abilities_get_enabled_modules();
	return in_array( $module, $enabled, true );
}

/**
 * Get the list of enabled modules, respecting multisite.
 *
 * Returns an empty array on fresh installs (explicit opt-in required).
 *
 * @return array Array of enabled module slugs.
 */
function fluent_abilities_get_enabled_modules() {
	if ( is_multisite() ) {
		$enabled = get_site_option( 'fluent_abilities_enabled_modules', array() );
	} else {
		$enabled = get_option( 'fluent_abilities_enabled_modules', array() );
	}

	return is_array( $enabled ) ? $enabled : array();
}

/**
 * Validate a URL for safe server-side fetching (SSRF protection).
 *
 * Blocks private/reserved IP ranges, localhost, link-local, and non-HTTP schemes.
 * Resolves hostname to IP before validation to prevent DNS rebinding.
 *
 * @param string $url The URL to validate.
 * @return string|WP_Error Sanitized URL on success, WP_Error on failure.
 */
function fluent_abilities_validate_url( $url ) {
	$url = esc_url_raw( $url );
	if ( empty( $url ) ) {
		return new WP_Error( 'ability_invalid_input', 'Invalid or empty URL' );
	}

	// Restrict to http/https schemes.
	$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
	if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
		return new WP_Error( 'ability_invalid_input', 'Only http and https URLs are allowed' );
	}

	// Resolve hostname to IP.
	$host = wp_parse_url( $url, PHP_URL_HOST );
	if ( empty( $host ) ) {
		return new WP_Error( 'ability_invalid_input', 'URL has no host' );
	}

	$ip = gethostbyname( $host );
	if ( $ip === $host ) {
		return new WP_Error( 'ability_invalid_input', 'Could not resolve hostname' );
	}

	// Block private and reserved IP ranges.
	$blocked = array(
		'10.',                          // 10.0.0.0/8
		'172.16.', '172.17.', '172.18.', '172.19.',
		'172.20.', '172.21.', '172.22.', '172.23.',
		'172.24.', '172.25.', '172.26.', '172.27.',
		'172.28.', '172.29.', '172.30.', '172.31.', // 172.16.0.0/12
		'192.168.',                     // 192.168.0.0/16
		'127.',                         // 127.0.0.0/8 (loopback)
		'169.254.',                     // link-local
		'0.',                           // 0.0.0.0/8
	);

	foreach ( $blocked as $prefix ) {
		if ( strpos( $ip, $prefix ) === 0 ) {
			return new WP_Error( 'ability_invalid_input', 'URLs pointing to private or reserved IP ranges are not allowed' );
		}
	}

	// Also check for IPv6 loopback (unlikely from gethostbyname but defensive).
	if ( $ip === '::1' ) {
		return new WP_Error( 'ability_invalid_input', 'URLs pointing to localhost are not allowed' );
	}

	return $url;
}

/**
 * Get all available modules with their detection status.
 *
 * @return array Module info with name, class, detected, enabled.
 */
function fluent_abilities_get_module_status() {
	$modules = array(
		'crm'        => array( 'label' => 'FluentCRM',        'constant' => 'FLUENTCRM_PLUGIN_VERSION' ),
		'community'  => array( 'label' => 'Fluent Community',  'constant' => 'FLUENT_COMMUNITY_PLUGIN_VERSION' ),
		'forms'      => array( 'label' => 'Fluent Forms',      'constant' => 'FLUENTFORM_VERSION' ),
		'support'    => array( 'label' => 'Fluent Support',    'constant' => 'FLUENT_SUPPORT_VERSION' ),
		'boards'     => array( 'label' => 'Fluent Boards',     'constant' => 'FLUENT_BOARDS_PLUGIN_VERSION' ),
		'booking'    => array( 'label' => 'FluentBooking',     'constant' => 'FLUENT_BOOKING_VERSION' ),
		'smtp'       => array( 'label' => 'FluentSMTP',        'constant' => 'FLUENTMAIL_PLUGIN_VERSION' ),
		'auth'       => array( 'label' => 'FluentAuth',        'constant' => 'FLUENT_AUTH_VERSION' ),
		'snippets'   => array( 'label' => 'Fluent Snippets',   'constant' => 'FLUENT_SNIPPETS_PLUGIN_VERSION' ),
		'messaging'  => array( 'label' => 'Fluent Messaging',  'constant' => 'FLUENT_MESSAGING_CHAT_VERSION' ),
		'cart'       => array( 'label' => 'FluentCart',        'constant' => 'FLUENTCART_VERSION' ),
		'affiliate'  => array( 'label' => 'FluentAffiliate',  'constant' => 'FLUENT_AFFILIATE_VERSION' ),
		'player'     => array( 'label' => 'FluentPlayer',     'constant' => 'FLUENT_PLAYER_VERSION' ),
		'fluent'     => array( 'label' => 'Fluent (Cross-Module)', 'constant' => 'FLUENT_ABILITIES_VERSION' ),
	);

	$enabled = fluent_abilities_get_enabled_modules();

	$status = array();
	foreach ( $modules as $key => $info ) {
		$status[ $key ] = array(
			'label'    => $info['label'],
			'detected' => defined( $info['constant'] ),
			'enabled'  => in_array( $key, $enabled, true ),
		);
	}

	return $status;
}
