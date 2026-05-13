<?php
/**
 * Plugin Name: Abilities for Fluent Plugins
 * Plugin URI:  https://github.com/Wicked-Evolutions/abilities-for-fluent-plugins
 * Description: WordPress Abilities API integration for the Fluent plugin ecosystem — CRM, Community, Forms, Support, Boards, Booking, SMTP, Auth, Snippets, Messaging, Cart, and Affiliate. Conditional module loading: only registers abilities for active Fluent products. This is an independent plugin and is not affiliated with or endorsed by WPManageNinja.
 * Version: 1.1.3
 * Author: Wicked Evolutions
 * Author URI: https://wickedevolutions.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires PHP: 7.4
 * Requires at least: 6.9
 * Network: true
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants (guarded — WordPress updater can re-include this file).
if ( ! defined( 'FLUENT_ABILITIES_VERSION' ) ) {
	define( 'FLUENT_ABILITIES_VERSION', '1.1.3' );
}
if ( ! defined( 'FLUENT_ABILITIES_PATH' ) ) {
	define( 'FLUENT_ABILITIES_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'FLUENT_ABILITIES_URL' ) ) {
	define( 'FLUENT_ABILITIES_URL', plugin_dir_url( __FILE__ ) );
}

// Require Abilities API (WP 6.9+).
if ( ! function_exists( 'wp_register_ability' ) ) {
	add_action( 'admin_notices', function() {
		echo '<div class="notice notice-error"><p><strong>Abilities for Fluent Plugins</strong> requires the WordPress Abilities API (WP 6.9+).</p></div>';
	});
	return;
}

// PSR-4 autoloader (composer-generated, with graceful fallback).
$autoloader = FLUENT_ABILITIES_PATH . 'vendor/autoload.php';
if ( file_exists( $autoloader ) ) {
	require_once $autoloader;
} else {
	// Fallback: manual class map for environments without composer install.
	// CI release ZIPs always include vendor/; this fallback is for dev clones only.
	spl_autoload_register( function( $class ) {
		$prefix = 'WickedEvolutions\\AbilitiesForFluent\\';
		if ( strpos( $class, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$file     = FLUENT_ABILITIES_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	} );
}

// Load shared infrastructure (categories, helpers, schemas, security, licensing).
// compat.php provides `Fluent_Abilities_Registrar` alias for the namespaced class —
// must load after autoloader so the namespaced class exists before class_alias() runs.
require_once FLUENT_ABILITIES_PATH . 'includes/compat.php';
require_once FLUENT_ABILITIES_PATH . 'includes/ability-categories.php';
require_once FLUENT_ABILITIES_PATH . 'includes/helpers.php';
require_once FLUENT_ABILITIES_PATH . 'includes/schemas.php';
require_once FLUENT_ABILITIES_PATH . 'includes/security.php';
require_once FLUENT_ABILITIES_PATH . 'includes/license-manager.php';
require_once FLUENT_ABILITIES_PATH . 'includes/tier-gate.php';

// Load plugin updater — checks FluentCart for new versions.
require_once FLUENT_ABILITIES_PATH . 'includes/updater/class-plugin-updater.php';

new Fluent_Abilities_Plugin_Updater( array(
	'slug'                 => 'abilities-for-fluent-plugins',
	'basename'             => plugin_basename( __FILE__ ),
	'version'              => FLUENT_ABILITIES_VERSION,
	'item_id'              => Fluent_Abilities_License_Manager::PRODUCT_ID,
	'api_url'              => Fluent_Abilities_License_Manager::STORE_URL,
	'license_key_callback' => array( 'Fluent_Abilities_License_Manager', 'get_license_key' ),
	'show_check_update'    => true,
) );

// Load admin dashboard.
if ( is_admin() ) {
	require_once FLUENT_ABILITIES_PATH . 'admin/dashboard.php';
}

// Self-healing: ensure caps are registered even if activation hook didn't fire
// (e.g., wp plugin install --activate-network, or new multisite site created).
add_action( 'init', 'fluent_abilities_maybe_register_caps' );

// Register caps when a new site is created on a multisite network.
add_action( 'wp_initialize_site', function( $new_site ) {
	switch_to_blog( $new_site->blog_id );
	fluent_abilities_register_caps();
	restore_current_blog();
}, 200 );

/**
 * Conditional module loader.
 *
 * Each module only loads if its corresponding Fluent plugin is active.
 * Runs on plugins_loaded so all Fluent plugins have initialized.
 */
add_action( 'plugins_loaded', function() {

	// All detections use defined() constants — class_exists(,false) is unreliable
	// because plugin load order is not guaranteed and autoloaders vary.
	// Constants verified against actual plugin source files on 2026-02-16.
	$modules = array(
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
		'cart'       => 'FLUENTCART_VERSION',
		'affiliate'  => 'FLUENT_AFFILIATE_VERSION',
		'player'     => 'FLUENT_PLAYER_VERSION',
	);

	// Get user-configured module toggles (Security Layer 1).
	// Returns empty array on fresh installs — explicit opt-in required.
	$enabled_modules = fluent_abilities_get_enabled_modules();

	$loaded_modules = array();

	foreach ( $modules as $module => $constant ) {
		// Security Layer 1: Module toggle check.
		if ( ! in_array( $module, $enabled_modules, true ) ) {
			continue;
		}

		// Dependency check: only load if the Fluent plugin is active.
		if ( ! defined( $constant ) ) {
			continue;
		}

		$module_file = FLUENT_ABILITIES_PATH . "includes/{$module}/abilities.php";
		if ( file_exists( $module_file ) ) {
			require_once $module_file;
			$loaded_modules[] = $module;

			// Load write abilities when cart module is active.
			if ( 'cart' === $module ) {
				$cart_write_file = FLUENT_ABILITIES_PATH . 'includes/cart/write-abilities.php';
				if ( file_exists( $cart_write_file ) ) {
					require_once $cart_write_file;
				}
			}

			// Load affiliate sub-modules when affiliate module is active.
			if ( 'affiliate' === $module ) {
				foreach ( array( 'payout-abilities', 'report-abilities', 'settings-abilities', 'portal-abilities' ) as $sub ) {
					$sub_file = FLUENT_ABILITIES_PATH . "includes/affiliate/{$sub}.php";
					if ( file_exists( $sub_file ) ) {
						require_once $sub_file;
					}
				}
			}

			// Load booking sub-modules when booking module is active.
			if ( 'booking' === $module ) {
				foreach ( array( 'abilities-bookings', 'abilities-availability' ) as $sub ) {
					$sub_file = FLUENT_ABILITIES_PATH . "includes/booking/{$sub}.php";
					if ( file_exists( $sub_file ) ) {
						require_once $sub_file;
					}
				}

				// Load extended Bookings ability sub-files (Phase B Bookings Registrar — v2.0.0).
				foreach ( array(
					'abilities-booking-meta',
					'abilities-calendar-integrations',
					'abilities-calendar-meta',
					'abilities-coupons',
					'abilities-event-config',
					'abilities-event-location',
					'abilities-global-settings',
					'abilities-import',
					'abilities-license',
					'abilities-multi-host',
					'abilities-orders',
					'abilities-permissions',
					'abilities-reports',
					'abilities-reschedule',
					'abilities-slots',
					'abilities-team',
					'abilities-webhooks',
					'abilities-zoom-twilio',
				) as $sub ) {
					$sub_file = FLUENT_ABILITIES_PATH . "includes/booking/{$sub}.php";
					if ( file_exists( $sub_file ) ) {
						require_once $sub_file;
					}
				}
			}

			// Load cohort analysis abilities when CRM module is active.
			if ( 'crm' === $module ) {
				$cohort_file = FLUENT_ABILITIES_PATH . 'includes/crm/cohort-abilities.php';
				if ( file_exists( $cohort_file ) ) {
					require_once $cohort_file;
				}
				$automation_file = FLUENT_ABILITIES_PATH . 'includes/crm/automation-abilities.php';
				if ( file_exists( $automation_file ) ) {
					require_once $automation_file;
				}
				$advanced_query_file = FLUENT_ABILITIES_PATH . 'includes/crm/advanced-query-abilities.php';
				if ( file_exists( $advanced_query_file ) ) {
					require_once $advanced_query_file;
				}

				// Load extended CRM ability sub-files (Phase B CRM Registrar — v2.0.0).
				foreach ( array(
					'extended-campaigns',
					'extended-funnels',
					'extended-misc-medium',
					'extended-misc-small',
					'extended-pro-companies',
					'extended-pro-marketing',
					'extended-pro-settings-and-commerce',
					'extended-reports',
					'extended-settings',
					'extended-subscribers',
					'extended-templates-and-patterns',
				) as $sub ) {
					$sub_file = FLUENT_ABILITIES_PATH . "includes/crm/{$sub}.php";
					if ( file_exists( $sub_file ) ) {
						require_once $sub_file;
					}
				}
			}

			// Load v2 ability sub-file when Community module is active (Phase B Community Registrar — v2.0.0).
			if ( 'community' === $module ) {
				$community_v2_file = FLUENT_ABILITIES_PATH . 'includes/community/abilities-v2.php';
				if ( file_exists( $community_v2_file ) ) {
					require_once $community_v2_file;
				}
			}

			// Load v2 ability sub-file when Messaging module is active (Phase B Community Registrar — v2.0.0).
			if ( 'messaging' === $module ) {
				$messaging_v2_file = FLUENT_ABILITIES_PATH . 'includes/messaging/abilities-v2.php';
				if ( file_exists( $messaging_v2_file ) ) {
					require_once $messaging_v2_file;
				}
			}
		}
	}

	// Cross-module abilities load if ANY Fluent product is active.
	if ( ! empty( $loaded_modules ) ) {
		$cross_module_file = FLUENT_ABILITIES_PATH . 'includes/cross-module/abilities.php';
		if ( file_exists( $cross_module_file ) ) {
			require_once $cross_module_file;
			$loaded_modules[] = 'cross-module';
		}
	}

	// Store loaded modules for admin dashboard display.
	if ( ! defined( 'FLUENT_ABILITIES_LOADED_MODULES' ) ) {
		define( 'FLUENT_ABILITIES_LOADED_MODULES', implode( ',', $loaded_modules ) );
	}

}, 20 ); // Priority 20: after Fluent plugins have loaded.

// Activation hook.
register_activation_hook( __FILE__, function( $network_wide = false ) {
	if ( is_multisite() && $network_wide ) {
		// Register caps on every site in the network.
		$site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );
			fluent_abilities_register_caps();
			restore_current_blog();
		}
		update_site_option( 'fluent_abilities_version', FLUENT_ABILITIES_VERSION );
	} else {
		fluent_abilities_register_caps();
		update_option( 'fluent_abilities_version', FLUENT_ABILITIES_VERSION );
	}

	// Auto-enable detected modules on fresh install.
	// On fresh installs the option doesn't exist — auto-detect which Fluent plugins
	// are active and enable those modules. This prevents the "zero abilities on first
	// install" problem for CLI-first workflows where no one visits the admin dashboard.
	$option_key = is_multisite() ? 'fluent_abilities_enabled_modules' : 'fluent_abilities_enabled_modules';
	$existing   = is_multisite() ? get_site_option( $option_key, null ) : get_option( $option_key, null );

	if ( $existing === null ) {
		$detection_constants = array(
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
			'cart'       => 'FLUENTCART_VERSION',
			'affiliate'  => 'FLUENT_AFFILIATE_VERSION',
		);

		$auto_enabled = array();
		foreach ( $detection_constants as $module => $constant ) {
			if ( defined( $constant ) ) {
				$auto_enabled[] = $module;
			}
		}

		if ( ! empty( $auto_enabled ) ) {
			if ( is_multisite() ) {
				update_site_option( $option_key, $auto_enabled );
			} else {
				update_option( $option_key, $auto_enabled );
			}
			error_log( 'Abilities for Fluent Plugins: Auto-enabled modules: ' . implode( ', ', $auto_enabled ) );
		}
	}

	wp_cache_flush();
	error_log( 'Abilities for Fluent Plugins: Activated v' . FLUENT_ABILITIES_VERSION );
});

// Deactivation hook.
register_deactivation_hook( __FILE__, function() {
	wp_cache_flush();
	error_log( 'Abilities for Fluent Plugins: Deactivated' );
});
