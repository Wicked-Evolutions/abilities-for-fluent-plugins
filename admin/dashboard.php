<?php
/**
 * Abilities for Fluent Plugins — Unified Dashboard
 *
 * Single admin page with two tabs:
 *   1. Explorer — module toggles (Security Layer 1), filterable ability table
 *                 with 3-level hierarchy: Module → Subcategory → Ability.
 *   2. License — activate/deactivate the Pro license key.
 *
 * Works independently of Abilities for WordPress.
 *
 * @package Fluent_Abilities
 */

defined( 'ABSPATH' ) || exit;

class Fluent_Abilities_Dashboard {

	/**
	 * Module slug → category slug mapping.
	 */
	private static $module_to_category = array(
		'crm'       => 'fluent-crm',
		'community' => 'fluent-community',
		'forms'     => 'fluent-forms',
		'support'   => 'fluent-support',
		'boards'    => 'fluent-boards',
		'booking'   => 'fluent-booking',
		'smtp'      => 'fluent-smtp',
		'auth'      => 'fluent-auth',
		'snippets'  => 'fluent-snippets',
		'messaging' => 'fluent-messaging',
		'cart'      => 'fluent-cart',
		'affiliate' => 'fluent-affiliate',
		'player'    => 'fluent-player',
		'cross'     => 'fluent',
	);

	/**
	 * Subcategory derivation rules.
	 *
	 * Maps noun fragments in ability names to human-readable subcategory labels.
	 * Order matters — first match wins.
	 */
	private static $subcategory_rules = array(
		'fluent-crm' => array(
			'contact'    => 'Contacts',
			'tag'        => 'Tags',
			'list'       => 'Lists',
			'campaign'   => 'Campaigns',
			'automation' => 'Automations',
			'sequence'   => 'Sequences',
			'smart-link' => 'Smart Links',
			'note'       => 'Notes',
			'event'      => 'Events',
			'template'   => 'Templates',
			'form'       => 'Forms',
			'company'    => 'Companies',
			'cohort'     => 'Analytics',
			'funnel'     => 'Analytics',
			'dashboard'  => 'Analytics',
			'stats'      => 'Analytics',
			'crm-report' => 'Analytics',
			'engagement' => 'Analytics',
			'email'      => 'Analytics',
		),
		'fluent-community' => array(
			'space'       => 'Spaces',
			'course'      => 'Courses & Lessons',
			'lesson'      => 'Courses & Lessons',
			'feed'        => 'Feeds',
			'scheduled'   => 'Feeds',
			'member'      => 'Members',
			'profile'     => 'Members',
			'follow'      => 'Members',
			'unfollow'    => 'Members',
			'media'       => 'Media',
			'leaderboard' => 'Analytics',
			'top-'        => 'Analytics',
			'activit'     => 'Analytics',
			'notification' => 'Analytics',
		),
		'fluent-support' => array(
			'ticket' => 'Tickets',
		),
	);

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ), 20 );
		add_action( 'network_admin_menu', array( $this, 'add_menu_pages' ), 20 );
		add_action( 'admin_init', array( $this, 'handle_settings_save' ) );
		add_action( 'admin_init', array( $this, 'handle_license_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_menu_pages() {
		// Always register as own top-level menu.
		add_menu_page(
			'Abilities for Fluent Plugins',
			'Fluent Abilities',
			'manage_options',
			'abilities-fluent-plugins',
			array( $this, 'render_page' ),
			'dashicons-admin-tools',
			31
		);
	}

	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'abilities-fluent-plugins' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'fluent-abilities-admin',
			FLUENT_ABILITIES_URL . 'admin/css/dashboard.css',
			array(),
			FLUENT_ABILITIES_VERSION
		);
	}

	// ─── Form Handlers ───────────────────────────────────────────

	/**
	 * Handle module toggle form submission.
	 */
	public function handle_settings_save() {
		if ( ! isset( $_POST['fluent_abilities_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $_POST['fluent_abilities_nonce'], 'fluent_abilities_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$enabled = isset( $_POST['fluent_modules'] ) && is_array( $_POST['fluent_modules'] )
			? array_map( 'sanitize_key', $_POST['fluent_modules'] )
			: array();

		if ( is_multisite() ) {
			if ( ! is_super_admin() ) {
				return;
			}
			update_site_option( 'fluent_abilities_enabled_modules', $enabled );
		} else {
			update_option( 'fluent_abilities_enabled_modules', $enabled );
		}

		add_settings_error(
			'fluent_abilities',
			'settings_updated',
			__( 'Module settings saved. MCP adapter restart required for changes to take effect.', 'fluent-abilities' ),
			'updated'
		);
	}

	/**
	 * Handle license activate/deactivate form submissions.
	 */
	public function handle_license_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Activate.
		if ( isset( $_POST['fluent_abilities_license_activate'] ) ) {
			check_admin_referer( 'fluent_abilities_license_nonce' );
			$key    = sanitize_text_field( wp_unslash( $_POST['fluent_abilities_license_key'] ?? '' ) );
			$result = Fluent_Abilities_License_Manager::activate( $key );

			if ( is_wp_error( $result ) ) {
				add_settings_error( 'fluent_abilities', 'activation_failed', $result->get_error_message(), 'error' );
			} else {
				add_settings_error( 'fluent_abilities', 'activated', __( 'License activated successfully.', 'fluent-abilities' ), 'success' );
			}
			set_transient( 'settings_errors', get_settings_errors(), 30 );
			wp_safe_redirect( add_query_arg( array( 'page' => 'abilities-fluent-plugins', 'tab' => 'license', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		// Deactivate.
		if ( isset( $_POST['fluent_abilities_license_deactivate'] ) ) {
			check_admin_referer( 'fluent_abilities_license_nonce' );
			Fluent_Abilities_License_Manager::deactivate();
			add_settings_error( 'fluent_abilities', 'deactivated', __( 'License deactivated.', 'fluent-abilities' ), 'info' );
			set_transient( 'settings_errors', get_settings_errors(), 30 );
			wp_safe_redirect( add_query_arg( array( 'page' => 'abilities-fluent-plugins', 'tab' => 'license', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
			exit;
		}
	}

	// ─── Data Loading ────────────────────────────────────────────

	/**
	 * Get all Fluent abilities as normalised arrays.
	 */
	private function get_fluent_abilities() {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return array();
		}

		$abilities = wp_get_abilities();
		$result    = array();
		$fluent_categories = array_values( self::$module_to_category );

		foreach ( $abilities as $name => $ability ) {
			if ( ! is_object( $ability ) || ! method_exists( $ability, 'get_label' ) ) {
				continue;
			}

			$category = $ability->get_category();

			// Only include Fluent abilities.
			if ( ! in_array( $category, $fluent_categories, true ) ) {
				continue;
			}

			$meta     = $ability->get_meta();
			$readonly = ! empty( $meta['annotations']['readonly'] );

			$result[ $name ] = array(
				'label'         => $ability->get_label(),
				'description'   => $ability->get_description(),
				'category'      => $category,
				'module'        => $this->category_to_module( $category ),
				'subcategory'   => $this->derive_subcategory( $name, $category ),
				'readonly'      => $readonly,
				'destructive'   => ! empty( $meta['annotations']['destructive'] ),
				'idempotent'    => ! empty( $meta['annotations']['idempotent'] ),
				'tier'          => ! empty( $meta['tier'] ) ? $meta['tier'] : 'pro',
				'input_schema'  => $ability->get_input_schema(),
				'output_schema' => $ability->get_output_schema(),
				'meta'          => $meta,
			);
		}

		// Sort by module → subcategory → name.
		uasort( $result, function( $a, $b ) {
			$cmp = strcmp( $a['module'], $b['module'] );
			if ( $cmp !== 0 ) return $cmp;
			$cmp = strcmp( $a['subcategory'], $b['subcategory'] );
			if ( $cmp !== 0 ) return $cmp;
			return 0;
		});

		return $result;
	}

	/**
	 * Map category slug to module slug.
	 */
	private function category_to_module( $category ) {
		$map = array_flip( self::$module_to_category );
		return $map[ $category ] ?? 'cross';
	}

	/**
	 * Derive a subcategory label from an ability name.
	 *
	 * Parses the noun portion of ability names like `fluent-crm/list-contacts`
	 * and matches against subcategory rules.
	 *
	 * @param string $name     Ability name (e.g. 'fluent-crm/list-contacts').
	 * @param string $category Category slug (e.g. 'fluent-crm').
	 * @return string Subcategory label.
	 */
	private function derive_subcategory( $name, $category ) {
		$rules = self::$subcategory_rules[ $category ] ?? array();

		// Extract the part after the slash.
		$parts = explode( '/', $name, 2 );
		$slug  = $parts[1] ?? $parts[0];

		foreach ( $rules as $fragment => $label ) {
			if ( false !== strpos( $slug, $fragment ) ) {
				return $label;
			}
		}

		// Fallback: use the module label.
		$module_status = fluent_abilities_get_module_status();
		$module        = $this->category_to_module( $category );
		return $module_status[ $module ]['label'] ?? ucfirst( $module );
	}

	/**
	 * Get the operation type for an ability.
	 */
	private function get_ability_op( $ability ) {
		if ( $ability['readonly'] ) return 'read';
		if ( $ability['destructive'] ) return 'delete';
		return 'write';
	}

	// ─── Main Render ─────────────────────────────────────────────

	public function render_page() {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'explorer';
		$saved      = isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'];
		?>
		<div class="wrap fluent-abilities-wrap">
			<h1>Abilities for Fluent Plugins</h1>
			<p class="fluent-abilities-subtitle">v<?php echo esc_html( FLUENT_ABILITIES_VERSION ); ?> — AI abilities for the Fluent product ecosystem</p>

			<div class="fluent-independence-notice">
				This plugin works independently. Abilities for WordPress is <strong>not required</strong>.
			</div>

			<?php if ( $saved ) : ?>
				<?php settings_errors( 'fluent_abilities' ); ?>
			<?php endif; ?>

			<nav class="nav-tab-wrapper fluent-abilities-tabs">
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'abilities-fluent-plugins', 'tab' => 'explorer' ), admin_url( 'admin.php' ) ) ); ?>"
				   class="nav-tab <?php echo 'explorer' === $active_tab ? 'nav-tab-active' : ''; ?>">
					Explorer
				</a>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'abilities-fluent-plugins', 'tab' => 'license' ), admin_url( 'admin.php' ) ) ); ?>"
				   class="nav-tab <?php echo 'license' === $active_tab ? 'nav-tab-active' : ''; ?>">
					License
				</a>
			</nav>

			<?php
			if ( 'license' === $active_tab ) {
				$this->render_license_tab();
			} else {
				$this->render_explorer_tab();
			}
			?>
		</div>
		<?php
	}

	// ─── Explorer Tab ────────────────────────────────────────────

	private function render_explorer_tab() {
		$abilities     = $this->get_fluent_abilities();
		$module_status = fluent_abilities_get_module_status();
		$loaded        = defined( 'FLUENT_ABILITIES_LOADED_MODULES' )
			? array_filter( explode( ',', FLUENT_ABILITIES_LOADED_MODULES ) )
			: array();

		// Collect filters data.
		$all_modules      = array();
		$all_subcats      = array();   // Grouped by module.
		$all_subcats_flat = array();

		foreach ( $abilities as $a ) {
			$mod = $a['module'];
			$all_modules[ $mod ] = ( $all_modules[ $mod ] ?? 0 ) + 1;

			$sub = $a['subcategory'];
			if ( ! isset( $all_subcats[ $mod ] ) ) {
				$all_subcats[ $mod ] = array();
			}
			$all_subcats[ $mod ][ $sub ] = ( $all_subcats[ $mod ][ $sub ] ?? 0 ) + 1;
			$all_subcats_flat[ $sub ] = ( $all_subcats_flat[ $sub ] ?? 0 ) + 1;
		}

		// Read filters.
		$module_filter  = isset( $_GET['module'] ) ? sanitize_text_field( wp_unslash( $_GET['module'] ) ) : '';
		$subcat_filter  = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '';
		$type_filter    = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : '';
		$search_filter  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		// Apply filters.
		$filtered = $abilities;
		if ( $module_filter ) {
			$filtered = array_filter( $filtered, function( $a ) use ( $module_filter ) {
				return $a['module'] === $module_filter;
			});
		}
		if ( $subcat_filter ) {
			$filtered = array_filter( $filtered, function( $a ) use ( $subcat_filter ) {
				return $a['subcategory'] === $subcat_filter;
			});
		}
		if ( 'read' === $type_filter ) {
			$filtered = array_filter( $filtered, function( $a ) { return $a['readonly']; });
		} elseif ( 'write' === $type_filter ) {
			$filtered = array_filter( $filtered, function( $a ) { return ! $a['readonly'] && ! $a['destructive']; });
		} elseif ( 'destructive' === $type_filter ) {
			$filtered = array_filter( $filtered, function( $a ) { return $a['destructive']; });
		}
		if ( $search_filter ) {
			$s = strtolower( $search_filter );
			$filtered = array_filter( $filtered, function( $a ) use ( $s ) {
				return false !== strpos( strtolower( $a['label'] ), $s )
					|| false !== strpos( strtolower( $a['description'] ), $s )
					|| false !== strpos( strtolower( $a['subcategory'] ), $s );
			});
		}

		$this->render_stats( $abilities, $module_status, $loaded );
		$this->render_filter_bar( $all_modules, $all_subcats, $module_status, $module_filter, $subcat_filter, $type_filter, $search_filter );
		$this->render_explorer_table( $filtered, $module_status, $loaded );
		$this->render_debug_info( $abilities, $module_status, $loaded );
	}

	/**
	 * Stats cards.
	 */
	private function render_stats( $abilities, $module_status, $loaded ) {
		$detected = 0;
		foreach ( $module_status as $info ) {
			if ( $info['detected'] ) $detected++;
		}
		?>
		<div class="fluent-stats-row">
			<div class="fluent-stat-card"><div class="val"><?php echo count( $abilities ); ?></div><div class="lbl">Total Abilities</div></div>
			<div class="fluent-stat-card green"><div class="val"><?php echo count( $abilities ); ?></div><div class="lbl">Enabled</div></div>
			<div class="fluent-stat-card green"><div class="val"><?php echo count( $loaded ); ?></div><div class="lbl">Modules Loaded</div></div>
			<div class="fluent-stat-card blue"><div class="val"><?php echo $detected; ?></div><div class="lbl">Plugins Detected</div></div>
			<div class="fluent-stat-card"><div class="val"><?php echo esc_html( FLUENT_ABILITIES_VERSION ); ?></div><div class="lbl">Plugin Version</div></div>
		</div>
		<?php
	}

	/**
	 * Filter bar with Module, Category (subcategories grouped by module), Type + search.
	 */
	private function render_filter_bar( $modules, $subcats, $module_status, $module_filter, $subcat_filter, $type_filter, $search_filter ) {
		$has_filters = $module_filter || $subcat_filter || $type_filter || $search_filter;
		?>
		<div class="fluent-filter-bar">
			<form method="get">
				<input type="hidden" name="page" value="abilities-fluent-plugins">
				<input type="hidden" name="tab" value="explorer">

				<label for="filter-module">Module:</label>
				<select name="module" id="filter-module">
					<option value="">All Modules (<?php echo array_sum( $modules ); ?>)</option>
					<?php foreach ( $modules as $mod => $count ) :
						$label = $module_status[ $mod ]['label'] ?? ucfirst( $mod );
					?>
						<option value="<?php echo esc_attr( $mod ); ?>" <?php selected( $module_filter, $mod ); ?>>
							<?php echo esc_html( $label ); ?> (<?php echo $count; ?>)
						</option>
					<?php endforeach; ?>
				</select>

				<label for="filter-category">Category:</label>
				<select name="category" id="filter-category">
					<option value="">All Categories</option>
					<?php foreach ( $subcats as $mod => $subs ) :
						$mod_label = $module_status[ $mod ]['label'] ?? ucfirst( $mod );
					?>
						<optgroup label="<?php echo esc_attr( $mod_label ); ?>">
							<?php foreach ( $subs as $sub_name => $sub_count ) : ?>
								<option value="<?php echo esc_attr( $sub_name ); ?>" <?php selected( $subcat_filter, $sub_name ); ?>>
									<?php echo esc_html( $sub_name ); ?> (<?php echo $sub_count; ?>)
								</option>
							<?php endforeach; ?>
						</optgroup>
					<?php endforeach; ?>
				</select>

				<label for="filter-type">Type:</label>
				<select name="type" id="filter-type">
					<option value="">All</option>
					<option value="read" <?php selected( $type_filter, 'read' ); ?>>Read-only</option>
					<option value="write" <?php selected( $type_filter, 'write' ); ?>>Write</option>
					<option value="destructive" <?php selected( $type_filter, 'destructive' ); ?>>Destructive</option>
				</select>

				<input type="text" name="s" placeholder="Search abilities…" value="<?php echo esc_attr( $search_filter ); ?>">
				<button type="submit" class="button button-primary">Filter</button>
				<?php if ( $has_filters ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=abilities-fluent-plugins&tab=explorer' ) ); ?>" class="button">Clear</a>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Explorer table with 3-level hierarchy: Module → Subcategory → Ability.
	 */
	private function render_explorer_table( $abilities, $module_status, $loaded ) {
		?>
		<form method="post" id="fluent-abilities-modules-form">
			<?php wp_nonce_field( 'fluent_abilities_save', 'fluent_abilities_nonce' ); ?>

			<div class="fluent-abilities-list">
				<p class="fluent-abilities-count">
					Showing <strong><?php echo count( $abilities ); ?></strong> abilities
				</p>

				<?php if ( empty( $abilities ) ) : ?>
					<div class="notice notice-warning inline"><p>No abilities match the current filters.</p></div>
				<?php else : ?>
					<table class="wp-list-table widefat fixed">
						<thead>
							<tr>
								<th style="width: 24%;">Ability</th>
								<th style="width: 34%;">Description</th>
								<th style="width: 12%;">Annotations</th>
								<th style="width: 6%;">Tier</th>
								<th class="fluent-perm-col" title="Read">R</th>
								<th class="fluent-perm-col" title="Write">W</th>
								<th class="fluent-perm-col" title="Delete">D</th>
							</tr>
						</thead>
						<tbody>
							<?php
							$current_module   = '';
							$current_subcat   = '';
							$all_seen_modules = array(); // Tracks modules rendered in the loop below so the
							                             // fallback can skip duplicates and catch installed-but-disabled
							                             // and not-installed modules that have no abilities to iterate.

							foreach ( $abilities as $name => $ability ) :
								$module = $ability['module'];
								$subcat = $ability['subcategory'];

								// ── Module header (Level 1) ──
								if ( $module !== $current_module ) :
									$current_module              = $module;
									$current_subcat              = ''; // Reset subcategory.
									$all_seen_modules[ $module ] = true;
									$info      = $module_status[ $module ] ?? array( 'label' => ucfirst( $module ), 'detected' => false, 'enabled' => false );
									$is_loaded = in_array( $module, $loaded, true );
									$mod_count = 0;
									foreach ( $abilities as $a ) {
										if ( $a['module'] === $module ) $mod_count++;
									}
								?>
									<tr class="fluent-module-header<?php echo ! $info['detected'] ? ' fluent-module-disabled' : ''; ?>">
										<td colspan="4">
											<span class="fluent-module-toggle">
												<label class="fluent-toggle">
													<input type="checkbox"
														name="fluent_modules[]"
														value="<?php echo esc_attr( $module ); ?>"
														<?php checked( $info['enabled'] ); ?>
														<?php disabled( ! $info['detected'] ); ?>>
													<span class="fluent-slider"></span>
												</label>
											</span>
											<span class="fluent-module-name"><?php echo esc_html( $info['label'] ); ?></span>
											<?php if ( $is_loaded ) : ?>
												<span class="fluent-module-status loaded">Loaded</span>
											<?php elseif ( ! $info['detected'] ) : ?>
												<span class="fluent-module-status not-installed">Not installed</span>
											<?php elseif ( ! $info['enabled'] ) : ?>
												<span class="fluent-module-status disabled">Disabled</span>
											<?php endif; ?>
											<span class="fluent-module-meta"><?php echo $mod_count; ?> abilities</span>
										</td>
										<td class="fluent-perm-col"></td>
										<td class="fluent-perm-col"></td>
										<td class="fluent-perm-col"></td>
									</tr>
								<?php
								endif;

								// ── Subcategory header (Level 2) ──
								if ( $subcat !== $current_subcat ) :
									$current_subcat = $subcat;
									$sub_count = 0;
									foreach ( $abilities as $a ) {
										if ( $a['module'] === $module && $a['subcategory'] === $subcat ) $sub_count++;
									}
								?>
									<tr class="fluent-subcat-header">
										<td colspan="4">
											<?php echo esc_html( $subcat ); ?>
											<span class="fluent-subcat-count">(<?php echo $sub_count; ?>)</span>
										</td>
										<td class="fluent-perm-col"></td>
										<td class="fluent-perm-col"></td>
										<td class="fluent-perm-col"></td>
									</tr>
								<?php
								endif;

								// ── Individual ability (Level 3) ──
								$detail_id = 'fd-' . sanitize_html_class( str_replace( '/', '-', $name ) );
								$op        = $this->get_ability_op( $ability );
								?>
								<tr>
									<td>
										<strong><?php echo esc_html( $name ); ?></strong>
										<div class="fluent-row-actions">
											<a href="#" onclick="toggleFluentDetail('<?php echo esc_js( $detail_id ); ?>'); return false;">Inspect</a>
										</div>
									</td>
									<td class="fluent-desc-cell"><?php echo esc_html( $ability['description'] ?: 'No description' ); ?></td>
									<td>
										<?php if ( $ability['readonly'] ) : ?>
											<span class="badge badge-read">Read</span>
										<?php else : ?>
											<span class="badge badge-write">Write</span>
										<?php endif; ?>
										<?php if ( $ability['idempotent'] ) : ?>
											<span class="badge badge-idem">Idem</span>
										<?php endif; ?>
										<?php if ( $ability['destructive'] ) : ?>
											<span class="badge badge-destruct">Destruct</span>
										<?php endif; ?>
									</td>
									<td><span class="badge badge-pro">Pro</span></td>
									<td class="fluent-perm-col"><?php if ( 'read' === $op ) : ?><input type="checkbox" checked disabled><?php endif; ?></td>
									<td class="fluent-perm-col"><?php if ( 'write' === $op ) : ?><input type="checkbox" checked disabled><?php endif; ?></td>
									<td class="fluent-perm-col"><?php if ( 'delete' === $op ) : ?><input type="checkbox" checked disabled class="fluent-destructive-check"><?php endif; ?></td>
								</tr>

								<!-- Detail panel -->
								<tr id="<?php echo esc_attr( $detail_id ); ?>" class="fluent-detail-row" style="display:none;">
									<td colspan="7">
										<div class="fluent-detail-panel">
											<h3><?php echo esc_html( $name ); ?></h3>
											<p>
												<?php if ( $ability['readonly'] ) : ?>
													<span class="badge badge-read">Read-only</span>
												<?php else : ?>
													<span class="badge badge-write">Write</span>
												<?php endif; ?>
												<?php if ( $ability['idempotent'] ) : ?>
													<span class="badge badge-idem">Idempotent</span>
												<?php endif; ?>
												<?php if ( $ability['destructive'] ) : ?>
													<span class="badge badge-destruct">Destructive</span>
												<?php endif; ?>
												<span class="badge badge-pro">Pro</span>
											</p>
											<p><?php echo esc_html( $ability['description'] ); ?></p>

											<div class="fluent-schema-grid">
												<div>
													<h4>Input Schema</h4>
													<pre><?php echo esc_html( wp_json_encode( $ability['input_schema'] ?? array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
												</div>
												<div>
													<h4>Output Schema</h4>
													<pre><?php echo esc_html( wp_json_encode( $ability['output_schema'] ?? array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
												</div>
											</div>

											<p class="fluent-mcp-hint">
												<strong>MCP Tool:</strong> <code><?php echo esc_html( 'mcp__wordpress__' . str_replace( '/', '-', $name ) ); ?></code>
											</p>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>

							<?php
							// Render module headers for modules the main loop didn't reach: either
							// not installed, or installed-but-disabled (registers no abilities so the
							// main loop never iterates a first ability for them). Without this, a
							// disabled module vanishes from the UI and can't be re-enabled without
							// editing the wp_options fluent_abilities_enabled_modules entry by hand.
							foreach ( $module_status as $mod_key => $mod_info ) :
								if ( isset( $all_seen_modules[ $mod_key ] ) ) {
									continue;
								}
								$is_installed_disabled = $mod_info['detected'] && ! $mod_info['enabled'];
							?>
								<tr class="fluent-module-header<?php echo $is_installed_disabled ? '' : ' fluent-module-disabled'; ?>">
									<td colspan="4">
										<span class="fluent-module-toggle">
											<label class="fluent-toggle">
												<?php if ( $is_installed_disabled ) : ?>
													<input type="checkbox" name="fluent_modules[]" value="<?php echo esc_attr( $mod_key ); ?>">
												<?php else : ?>
													<input type="checkbox" disabled>
												<?php endif; ?>
												<span class="fluent-slider"></span>
											</label>
										</span>
										<span class="fluent-module-name"<?php echo $is_installed_disabled ? '' : ' style="color:#646970;"'; ?>><?php echo esc_html( $mod_info['label'] ); ?></span>
										<?php if ( $is_installed_disabled ) : ?>
											<span class="fluent-module-status disabled">Disabled</span>
											<span class="fluent-module-meta"><em>Enable to register abilities</em></span>
										<?php else : ?>
											<span class="fluent-module-status not-installed">Not installed</span>
											<span class="fluent-module-meta"><em>Install <?php echo esc_html( $mod_info['label'] ); ?> to enable abilities</em></span>
										<?php endif; ?>
									</td>
									<td class="fluent-perm-col"></td>
									<td class="fluent-perm-col"></td>
									<td class="fluent-perm-col"></td>
								</tr>
							<?php
							endforeach;
							?>
						</tbody>
					</table>
				<?php endif; ?>

				<!-- Save bar -->
				<div class="fluent-save-bar">
					<?php submit_button( 'Save Module Settings', 'primary', 'submit', false ); ?>
					<span class="fluent-save-summary">
						<strong><?php echo count( $abilities ); ?></strong> abilities
						· <strong><?php echo count( $loaded ); ?></strong> modules active
						· Restart MCP adapter after saving
					</span>
				</div>
			</div>
		</form>
		<?php
	}

	// ─── License Tab ─────────────────────────────────────────────

	private function render_license_tab() {
		$status    = Fluent_Abilities_License_Manager::get_status();
		$is_active = $status['activated'];
		$has_key   = ! empty( $status['key'] );
		?>

		<div class="fluent-license-card">
			<h3>
				Abilities for Fluent Plugins
				<?php if ( $is_active ) : ?>
					<span class="badge badge-pro" style="vertical-align:middle;">Pro</span>
				<?php endif; ?>
			</h3>

			<?php if ( $is_active ) : ?>
				<div class="fluent-license-status">
					<span class="fluent-dot fluent-dot-active"></span>
					<strong style="color:#00a32a;"><?php esc_html_e( 'Active', 'fluent-abilities' ); ?></strong>
				</div>
				<form method="post">
					<?php wp_nonce_field( 'fluent_abilities_license_nonce' ); ?>
					<div class="fluent-key-field">
						<input type="text" value="<?php echo esc_attr( $status['key'] ); ?>" disabled>
						<button type="submit" name="fluent_abilities_license_deactivate" class="button fluent-btn-danger fluent-btn-sm">Deactivate</button>
					</div>
				</form>
				<p class="fluent-license-meta">
					<span>Product ID: <?php echo Fluent_Abilities_License_Manager::PRODUCT_ID; ?></span>
					<?php if ( ! empty( $status['last_valid'] ) ) : ?>
						<span>Last validated: <?php echo esc_html( $status['last_valid'] ); ?> UTC</span>
					<?php endif; ?>
				</p>

			<?php elseif ( $has_key ) : ?>
				<div class="fluent-license-status">
					<span class="fluent-dot fluent-dot-inactive"></span>
					<strong style="color:#d63638;"><?php esc_html_e( 'Inactive', 'fluent-abilities' ); ?></strong>
				</div>
				<form method="post">
					<?php wp_nonce_field( 'fluent_abilities_license_nonce' ); ?>
					<div class="fluent-key-field">
						<input type="text" value="<?php echo esc_attr( $status['key'] ); ?>" disabled>
						<button type="submit" name="fluent_abilities_license_deactivate" class="button fluent-btn-danger fluent-btn-sm">Remove</button>
					</div>
				</form>

			<?php else : ?>
				<div class="fluent-license-status">
					<span class="fluent-dot fluent-dot-unlicensed"></span>
					<strong style="color:#dba617;"><?php esc_html_e( 'No License', 'fluent-abilities' ); ?></strong>
				</div>
				<form method="post">
					<?php wp_nonce_field( 'fluent_abilities_license_nonce' ); ?>
					<div class="fluent-key-field">
						<input type="text" name="fluent_abilities_license_key" placeholder="Enter your license key…">
						<button type="submit" name="fluent_abilities_license_activate" class="button button-primary fluent-btn-sm">Activate</button>
					</div>
				</form>
				<p class="fluent-license-meta">
					<span>All abilities require an active Pro license.</span>
				</p>
			<?php endif; ?>
		</div>

		<?php $this->render_pro_breakdown(); ?>
		<?php
	}

	/**
	 * "What Pro Unlocks" table on License tab.
	 */
	private function render_pro_breakdown() {
		$module_status = fluent_abilities_get_module_status();
		$abilities     = $this->get_fluent_abilities();

		$module_counts = array();
		foreach ( $abilities as $a ) {
			$mod = $a['module'];
			$module_counts[ $mod ] = ( $module_counts[ $mod ] ?? 0 ) + 1;
		}

		$total = 0;
		?>
		<div class="fluent-card" style="margin-top:20px;">
			<h2>What Pro Unlocks</h2>
			<p class="fluent-card-desc">All Abilities for Fluent Plugins are Pro-gated.</p>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr><th>Module</th><th>Abilities</th><th>Key Capabilities</th></tr></thead>
				<tbody>
					<?php foreach ( $module_counts as $mod => $count ) :
						$label = $module_status[ $mod ]['label'] ?? ucfirst( $mod );
						$total += $count;
					?>
						<tr>
							<td><strong><?php echo esc_html( $label ); ?></strong></td>
							<td><?php echo $count; ?></td>
							<td style="color:#50575e;"><?php echo esc_html( $this->get_module_capabilities_summary( $mod ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					<tr style="background:#f6f7f7;font-weight:600;">
						<td>Total</td>
						<td><?php echo $total; ?></td>
						<td></td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Short summary of capabilities per module.
	 */
	private function get_module_capabilities_summary( $module ) {
		$summaries = array(
			'crm'       => 'Contacts, campaigns, automations, sequences, analytics, cohort analysis',
			'community' => 'Spaces, feeds, courses, lessons, members, media, leaderboards',
			'support'   => 'Tickets, conversations, agent/customer analytics',
			'boards'    => 'Boards, stages, task CRUD, comments',
			'booking'   => 'Calendars, bookings, availability',
			'forms'     => 'Form entries, submissions',
			'smtp'      => 'Email logs, sending stats',
			'auth'      => 'Login security, social auth',
			'snippets'  => 'Code snippets, activation toggles',
			'messaging' => 'Conversations, message sending',
			'cart'      => 'Products, orders, customers',
			'cross'     => 'User 360, engagement scoring, onboarding, dashboard',
		);
		return $summaries[ $module ] ?? '';
	}

	// ─── Debug ───────────────────────────────────────────────────

	private function render_debug_info( $abilities, $module_status, $loaded ) {
		$license = Fluent_Abilities_License_Manager::get_status();
		?>
		<div class="fluent-card" style="margin-top:20px;">
			<h2>Debug Information</h2>
			<p class="fluent-card-desc">Copy this when reporting issues.</p>
			<textarea class="fluent-debug-area" readonly>Plugin: Abilities for Fluent Plugins v<?php echo esc_html( FLUENT_ABILITIES_VERSION ); ?>

WordPress: <?php echo get_bloginfo( 'version' ); ?> | PHP: <?php echo PHP_VERSION; ?> | Multisite: <?php echo is_multisite() ? 'Yes' : 'No'; ?>

Total: <?php echo count( $abilities ); ?> | Modules: <?php echo count( $loaded ); ?>/<?php echo count( $module_status ); ?>

Loaded: <?php echo implode( ', ', $loaded ); ?>

License: <?php echo esc_html( $license['status'] ); ?><?php if ( ! empty( $license['last_valid'] ) ) : ?> | Last valid: <?php echo esc_html( $license['last_valid'] ); ?><?php endif; ?></textarea>
		</div>

		<script>
		function toggleFluentDetail(id) {
			var row = document.getElementById(id);
			if (row) row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
		}
		</script>
		<?php
	}
}

new Fluent_Abilities_Dashboard();
