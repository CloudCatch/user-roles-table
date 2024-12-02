<?php
/**
 * Plugin Name:       User Role Tables
 * Description:       Move WordPress user roles to a custom table.
 * Requires at least: 6.0.2
 * Requires PHP:      7.0
 * Version:           1.0.0
 * Author:            CloudCatch LLC
 * Author URI:        https://cloudcatch.io
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       user-roles-table
 * 
 * @package UserRolesTable
 */

namespace CloudCatch\UserRolesTable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define the version of the custom table schema.
 */
define( 'USER_ROLE_TABLES_VERSION', '1.0.0' );

/**
 * Include necessary files.
 */
require_once __DIR__ . '/class-user-roles-table-shoehorn.php';
require_once __DIR__ . '/class-user-roles-table-cli.php';

/**
 * Install the custom table.
 */
register_activation_hook( __FILE__, array( __NAMESPACE__ . '\User_Roles_Table_CLI', 'do_install' ) );

/**
 * Filter user queries to use the custom user roles table.
 *
 * @param WP_User_Query $query User query object.
 * @return void
 */
function maybe_filter_users( $query ) {
	if ( ! get_option( 'user_role_tables_migrated' ) ) {
		return;
	}

	if ( true !== $query->get( 'roles_table' ) && ! apply_filters( 'enable_roles_table_integration', true, $query ) ) {
		return;
	}

	// Bail early if we are not querying users by roles or capabilities.
	if ( empty( $query->get( 'role' ) ) && empty( $query->get( 'role__in' ) ) && empty( $query->get( 'role__not_in' ) ) && empty( $query->get( 'capability' ) ) ) {
		return;
	}

	$query->set( 'roles_table', true );

	new User_Roles_Table_Shoehorn( $query );
}
add_action( 'pre_get_users', __NAMESPACE__ . '\maybe_filter_users', 1000 );

/**
 * Update user roles in the custom table when user meta is updated.
 *
 * @param null|bool $check Whether to allow updating metadata for the given type.
 * @param int       $object_id ID of the object metadata is for.
 * @param string    $meta_key Metadata key.
 * @param mixed     $meta_value Metadata value. Must be serializable if non-scalar.
 * @return null|bool
 */
function update_user_roles( $check, $object_id, $meta_key, $meta_value ) {
	global $wpdb;

	if ( $wpdb->base_prefix . 'capabilities' !== $meta_key ) {
		return $check;
	}

	$wpdb->delete(
		$wpdb->prefix . 'user_roles',
		array(
			'user_id' => $object_id,
		),
		array(
			'%d',
		)
	);

	$roles = (array) array_keys( maybe_unserialize( $meta_value ) );

	foreach ( $roles as $role ) {
		$wpdb->insert(
			$wpdb->prefix . 'user_roles',
			array(
				'user_id' => $object_id,
				'role'    => $role,
			),
			array(
				'%d',
				'%s',
			)
		);
	}

	return $check;
}
add_filter( 'update_user_metadata', __NAMESPACE__ . '\update_user_roles', PHP_INT_MAX, 5 );
