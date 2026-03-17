<?php
/**
 * License Manager — FluentCart API Integration
 *
 * Validates Abilities for Fluent Plugins Pro licenses via the FluentCart license API.
 * Uses a 24-hour transient cache for the validation result and a 7-day grace period
 * for API unreachability.
 *
 * Product IDs are stored as WordPress options (not hardcoded constants).
 * On first activation, the product_id returned by FluentCart is saved
 * locally for future check_license and get_license_version calls.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

class Fluent_Abilities_License_Manager {

	/**
	 * FluentCart store URL where the license API lives.
	 *
	 * @var string
	 */
	const STORE_URL = 'https://community.wickedevolutions.com';

	/**
	 * FluentCart product ID for Abilities for Fluent Plugins.
	 *
	 * Required by every FluentCart license API call. Used as the default
	 * for initial activation; after activation the response product_id
	 * is stored locally for subsequent calls.
	 *
	 * @var int
	 */
	const PRODUCT_ID = 72;

	/**
	 * Cache lifetime for a successful validation result (24 hours).
	 *
	 * @var int
	 */
	const CACHE_TTL = DAY_IN_SECONDS;

	/**
	 * Grace period when the license API is unreachable (7 days).
	 *
	 * @var int
	 */
	const GRACE_PERIOD = 7 * DAY_IN_SECONDS;

	// WordPress option / transient keys.
	const OPT_LICENSE_KEY  = 'wkdevo_abilities_fluent_license_key';
	const OPT_ACTIV_HASH   = 'wkdevo_abilities_fluent_activation_hash';
	const OPT_LAST_VALID   = 'wkdevo_abilities_fluent_last_valid_ts';
	const OPT_PRODUCT_ID   = 'wkdevo_abilities_fluent_product_id';
	const TRANSIENT_STATUS = 'wkdevo_abilities_fluent_pro_status';

	// ----------------------------------------------------------------------------
	// Public API
	// ----------------------------------------------------------------------------

	/**
	 * Get the current license key (for update checker).
	 *
	 * @return string License key or empty string.
	 */
	public static function get_license_key() {
		return self::get_opt( self::OPT_LICENSE_KEY, '' );
	}

	/**
	 * Get the FluentCart product ID.
	 *
	 * Returns the stored product ID from a prior activation, or falls
	 * back to the hardcoded PRODUCT_ID constant for initial activation.
	 *
	 * @return int
	 */
	public static function get_product_id() {
		$stored = (int) self::get_opt( self::OPT_PRODUCT_ID, 0 );
		return $stored ?: self::PRODUCT_ID;
	}

	/**
	 * Check if a Pro license is currently active.
	 *
	 * @return bool
	 */
	public static function is_pro_active() {
		$cached = get_transient( self::TRANSIENT_STATUS );
		if ( false !== $cached ) {
			return 'active' === $cached;
		}

		$license_key = self::get_opt( self::OPT_LICENSE_KEY, '' );
		if ( empty( $license_key ) ) {
			return false;
		}

		$result = self::remote_check( $license_key );

		if ( is_wp_error( $result ) ) {
			return self::is_within_grace_period();
		}

		$is_active = isset( $result['status'] ) && 'valid' === $result['status'];

		if ( $is_active ) {
			self::update_opt( self::OPT_LAST_VALID, time() );
			set_transient( self::TRANSIENT_STATUS, 'active', self::CACHE_TTL );
		} else {
			set_transient( self::TRANSIENT_STATUS, 'inactive', self::CACHE_TTL );
		}

		return $is_active;
	}

	/**
	 * Activate a license key.
	 *
	 * @param string $license_key The license key to activate.
	 * @param int    $product_id  FluentCart product ID (from admin UI or prior activation).
	 * @return true|WP_Error
	 */
	public static function activate( $license_key, $product_id = 0 ) {
		$license_key = sanitize_text_field( $license_key );
		if ( empty( $license_key ) ) {
			return new WP_Error( 'invalid_key', __( 'License key cannot be empty.', 'fluent-abilities' ) );
		}

		$product_id = (int) $product_id;
		if ( ! $product_id ) {
			$product_id = self::get_product_id();
		}

		if ( ! $product_id ) {
			return new WP_Error( 'no_product_id', __( 'Product ID is required for license activation. Set it in the plugin settings.', 'fluent-abilities' ) );
		}

		// For network-activated plugins, always use network scope and main site URL.
		$is_network = self::is_network_license();
		$site_url   = $is_network ? network_site_url() : home_url();

		$response = self::remote_request( 'activate_license', array(
			'license_key' => $license_key,
			'item_id'     => $product_id,
			'site_url'    => $site_url,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! isset( $response['status'] ) || 'valid' !== $response['status'] ) {
			$message = $response['message'] ?? __( 'License activation failed.', 'fluent-abilities' );
			return new WP_Error( $response['error_type'] ?? 'activation_failed', $message );
		}

		// Store the product_id from the API response.
		$response_product = (int) ( $response['product_id'] ?? $product_id );
		self::update_opt( self::OPT_PRODUCT_ID, $response_product );

		// Persist network scope flag.
		if ( $is_network ) {
			update_site_option( 'wkdevo_abilities_fluent_license_scope', 'network' );
		}

		self::update_opt( self::OPT_LICENSE_KEY, $license_key );
		self::update_opt( self::OPT_ACTIV_HASH, $response['activation_hash'] ?? '' );
		self::update_opt( self::OPT_LAST_VALID, time() );

		delete_transient( self::TRANSIENT_STATUS );

		return true;
	}

	/**
	 * Deactivate the current license.
	 *
	 * @return bool
	 */
	public static function deactivate() {
		$license_key = self::get_opt( self::OPT_LICENSE_KEY, '' );
		$activ_hash  = self::get_opt( self::OPT_ACTIV_HASH, '' );
		$product_id  = self::get_product_id();

		if ( ! empty( $license_key ) && $product_id ) {
			self::remote_request( 'deactivate_license', array(
				'license_key'     => $license_key,
				'activation_hash' => $activ_hash,
				'item_id'         => $product_id,
				'site_url'        => home_url(),
			) );
		}

		self::delete_opt( self::OPT_LICENSE_KEY );
		self::delete_opt( self::OPT_ACTIV_HASH );
		self::delete_opt( self::OPT_LAST_VALID );
		self::delete_opt( self::OPT_PRODUCT_ID );
		delete_transient( self::TRANSIENT_STATUS );
		delete_site_option( 'wkdevo_abilities_fluent_license_scope' );

		return true;
	}

	/**
	 * Get the Pro-required error response.
	 *
	 * @param string $ability_name The ability slug.
	 * @return WP_Error
	 */
	public static function pro_required_error( $ability_name ) {
		return new WP_Error(
			'pro_required',
			sprintf(
				/* translators: %s: Ability name */
				__( 'The "%s" ability requires an active Pro license for Abilities for Fluent Plugins. Visit https://wickedevolutions.com/pro to upgrade.', 'fluent-abilities' ),
				$ability_name
			),
			array( 'status' => 403 )
		);
	}

	/**
	 * Get the current license status details for display in admin UI.
	 *
	 * @return array
	 */
	public static function get_status() {
		$license_key = self::get_opt( self::OPT_LICENSE_KEY, '' );
		$last_valid  = self::get_opt( self::OPT_LAST_VALID, 0 );

		if ( empty( $license_key ) ) {
			return array(
				'key'        => '',
				'status'     => 'unlicensed',
				'product_id' => self::get_product_id(),
				'activated'  => false,
				'last_valid' => '',
			);
		}

		$masked_key = substr( $license_key, 0, 6 ) . str_repeat( '*', max( 0, strlen( $license_key ) - 9 ) ) . substr( $license_key, -3 );

		$is_active = self::is_pro_active();

		return array(
			'key'        => $masked_key,
			'status'     => $is_active ? 'active' : 'inactive',
			'product_id' => self::get_product_id(),
			'activated'  => $is_active,
			'last_valid' => $last_valid ? gmdate( 'Y-m-d H:i:s', $last_valid ) : '',
		);
	}

	// ----------------------------------------------------------------------------
	// Internal Helpers
	// ----------------------------------------------------------------------------

	/**
	 * Whether the current license has network (multisite) scope.
	 *
	 * @return bool
	 */
	private static function is_network_license() {
		if ( ! is_multisite() ) {
			return false;
		}
		// If the plugin is network-activated, always use network scope.
		if ( is_plugin_active_for_network( 'abilities-for-fluent-plugins/abilities-for-fluent-plugins.php' ) ) {
			return true;
		}
		return 'network' === get_site_option( 'wkdevo_abilities_fluent_license_scope', '' );
	}

	/**
	 * Read a license option, respecting network scope.
	 *
	 * @param string $key     Option key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	private static function get_opt( $key, $default = '' ) {
		return self::is_network_license() ? get_site_option( $key, $default ) : get_option( $key, $default );
	}

	/**
	 * Write a license option, respecting network scope.
	 *
	 * @param string $key   Option key.
	 * @param mixed  $value Value.
	 */
	private static function update_opt( $key, $value ) {
		if ( self::is_network_license() ) {
			update_site_option( $key, $value );
		} else {
			update_option( $key, $value );
		}
	}

	/**
	 * Delete a license option, respecting network scope.
	 *
	 * @param string $key Option key.
	 */
	private static function delete_opt( $key ) {
		if ( self::is_network_license() ) {
			delete_site_option( $key );
		} else {
			delete_option( $key );
		}
	}

	/**
	 * POST to the FluentCart check_license endpoint.
	 *
	 * @param string $license_key License key.
	 * @return array|WP_Error
	 */
	private static function remote_check( $license_key ) {
		$activ_hash = self::get_opt( self::OPT_ACTIV_HASH, '' );
		$product_id = self::get_product_id();

		if ( ! $product_id ) {
			return new WP_Error( 'no_product_id', 'No product ID stored. Re-activate your license.' );
		}

		$payload = array(
			'item_id'  => $product_id,
			'site_url' => self::is_network_license() ? network_site_url() : home_url(),
		);

		if ( ! empty( $activ_hash ) ) {
			$payload['activation_hash'] = $activ_hash;
		} else {
			$payload['license_key'] = $license_key;
		}

		return self::remote_request( 'check_license', $payload );
	}

	/**
	 * POST to the FluentCart license API.
	 *
	 * @param string $action  One of: activate_license, check_license, deactivate_license.
	 * @param array  $payload POST body fields.
	 * @return array|WP_Error
	 */
	private static function remote_request( $action, array $payload ) {
		$url = add_query_arg( 'fluent-cart', $action, self::STORE_URL . '/' );

		$response = wp_remote_post( $url, array(
			'timeout'   => 15,
			'sslverify' => true,
			'body'      => $payload,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		$decoded = json_decode( $body, true );

		if ( ! is_array( $decoded ) ) {
			return new WP_Error(
				'invalid_response',
				sprintf( 'License API returned unexpected response (HTTP %d).', $code )
			);
		}

		return $decoded;
	}

	/**
	 * Check whether the last known-valid timestamp is within the grace period.
	 *
	 * @return bool
	 */
	private static function is_within_grace_period() {
		$last_valid = (int) self::get_opt( self::OPT_LAST_VALID, 0 );
		if ( $last_valid <= 0 ) {
			return false;
		}
		return ( time() - $last_valid ) < self::GRACE_PERIOD;
	}
}
