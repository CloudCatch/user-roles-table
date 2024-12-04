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
	if ( ! get_site_option( 'user_role_tables_migrated' ) ) {
		return;
	}

	if ( true !== $query->get( 'roles_table' ) && ! apply_filters( 'enable_roles_table_integration', false, $query ) ) {
		return;
	}

	// Bail early if we are not querying users by roles or capabilities.
	if ( ( empty( $query->get( 'role' ) ) && empty( $query->get( 'role__in' ) ) && empty( $query->get( 'role__not_in' ) ) && empty( $query->get( 'capability' ) ) && ! is_multisite() ) ) {
		return;
	}

	$query->set( 'roles_table', true );

	new User_Roles_Table_Shoehorn( $query );
}
add_action( 'pre_get_users', __NAMESPACE__ . '\maybe_filter_users', 1000 );

/**
 * Update user roles in the custom table when user meta is updated.
 *
 * @param int    $meta_id   ID of the metadata entry to update.
 * @param int    $object_id ID of the object metadata is for.
 * @param string $meta_key Metadata key.
 * @param mixed  $meta_value Metadata value. Must be serializable if non-scalar.
 * @return void
 */
function update_user_roles( $meta_id, $object_id, $meta_key, $meta_value ) {
	global $wpdb;

	if ( $wpdb->prefix . 'capabilities' !== $meta_key ) {
		return;
	}

	$blog_id = 1;

	if ( is_multisite() ) {
		$blog_id = get_current_blog_id();
	}

	$wpdb->delete(
		$wpdb->base_prefix . 'user_roles',
		array(
			'user_id' => $object_id,
			'site_id' => $blog_id,
		),
		array(
			'%d',
			'%d',
		)
	);

	$roles = (array) array_keys( maybe_unserialize( $meta_value ) );

	foreach ( $roles as $role ) {
		$wpdb->insert(
			$wpdb->base_prefix . 'user_roles',
			array(
				'user_id' => $object_id,
				'site_id' => $blog_id,
				'role'    => $role,
			),
			array(
				'%d',
				'%d',
				'%s',
			)
		);
	}
}
add_action( 'updated_user_meta', __NAMESPACE__ . '\update_user_roles', PHP_INT_MAX, 4 );
add_action( 'added_user_meta', __NAMESPACE__ . '\update_user_roles', PHP_INT_MAX, 4 );

/**
 * Delete user roles from the custom table when user meta is deleted.
 *
 * @param string[] $meta_ids    An array of metadata entry IDs to delete.
 * @param int      $object_id   ID of the object metadata is for.
 * @param string   $meta_key    Metadata key.
 * @return void
 */
function delete_user_roles( $meta_ids, $object_id, $meta_key ) {
	global $wpdb;

	if ( $wpdb->prefix . 'capabilities' !== $meta_key ) {
		return;
	}

	$blog_id = 1;

	if ( is_multisite() ) {
		$blog_id = get_current_blog_id();
	}

	$wpdb->delete(
		$wpdb->base_prefix . 'user_roles',
		array(
			'user_id' => $object_id,
			'site_id' => $blog_id,
		),
		array(
			'%d',
			'%d',
		)
	);
}
add_action( 'delete_user_meta', __NAMESPACE__ . '\delete_user_roles', PHP_INT_MAX, 3 );
