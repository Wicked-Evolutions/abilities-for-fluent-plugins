<?php
/**
 * Backward-Compatible Aliases
 *
 * Resolves J3-F: the uncommitted function-renaming decision that was reverted.
 * This file is the canonical migration point — any future renaming goes here.
 *
 * Provides:
 * 1. `Fluent_Abilities_Registrar` as an alias for the namespaced Registrar class.
 *    Existing module files that use `new Fluent_Abilities_Registrar()` continue
 *    to work without modification after PSR-4 adoption.
 *
 * 2. Procedural function aliases — for any future function renames, add here:
 *    if ( ! function_exists( 'new_name' ) ) {
 *        function new_name( ...$args ) { return old_name( ...$args ); }
 *    }
 *
 * Load order: After autoloader registration. Before include files.
 *
 * @package WickedEvolutions\AbilitiesForFluent
 */

defined( 'ABSPATH' ) || exit;

// Alias the namespaced Registrar to the legacy global class name.
// All existing ability module files use `new Fluent_Abilities_Registrar( 'module' )`
// and continue to work without modification.
if ( ! class_exists( 'Fluent_Abilities_Registrar' ) ) {
	class_alias(
		\WickedEvolutions\AbilitiesForFluent\Core\Registrar::class,
		'Fluent_Abilities_Registrar'
	);
}
