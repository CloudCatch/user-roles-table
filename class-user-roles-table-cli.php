<?php
/**
 * CLI commands for the user role tables plugin.
 * 
 * @package UserRolesTable
 */

namespace CloudCatch\UserRolesTable;

use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CLI commands for the user role tables plugin.
 */
class User_Roles_Table_CLI {

	/**
	 * Install the custom user roles table.
	 * 
	 * ## EXAMPLES
	 *
	 * wp user-roles-table install
	 *
	 * @when after_wp_load
	 * 
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function install( $args = array(), $assoc_args = array() ) {
		if ( ( empty( $assoc_args['force'] ) || ! $assoc_args['force'] ) && version_compare( USER_ROLE_TABLES_VERSION, get_option( 'user_role_tables_version' ), '<=' ) ) {
			WP_CLI::success( __( 'The custom user roles table is already installed and up to date.', 'user-roles-table' ) );

			return;
		}

		WP_CLI::log( __( 'Installing the custom user roles table...', 'user-roles-table' ) );

		self::do_install();

		WP_CLI::success( __( 'The custom user roles table has been installed.', 'user-roles-table' ) );
	}

	/**
	 * Create the custom user roles table.
	 * 
	 * @global wpdb $wpdb WordPress database abstraction object.
	 * @return void
	 */
	public static function do_install() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$wpdb->base_prefix}user_roles (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			site_id bigint(20) NOT NULL DEFAULT '1',
			role varchar(255) NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY role (role)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql );

		update_option( 'user_role_tables_version', USER_ROLE_TABLES_VERSION );
	}

	/**
	 * Migrate user roles to a custom table.
	 * 
	 * ## OPTIONS
	 * 
	 * [--preserve]
	 * : Whether or not to truncate the custom user roles table before migrating user roles.
	 * ---
	 * default: false
	 *
	 * ## EXAMPLES
	 *
	 * wp user-roles-table migrate
	 *
	 * @when after_wp_load
	 * 
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function migrate( $args = array(), $assoc_args = array() ) {
		$generator = self::do_migration( $args, $assoc_args );

		$progress = \WP_CLI\Utils\make_progress_bar( __( 'Migrating user roles', 'user-roles-table' ), 0 );

		foreach ( $generator as $item ) {
			if ( 'start' === $item['type'] ) {
				$progress = \WP_CLI\Utils\make_progress_bar( __( 'Migrating user roles', 'user-roles-table' ), $item['count'] );
			} elseif ( 'progress' === $item['type'] ) {
				$progress->tick();
			} elseif ( 'finish' === $item['type'] ) {
				$progress->finish();
				WP_CLI::success( $item['message'] );
			} elseif ( 'message' === $item['type'] ) {
				WP_CLI::log( $item['message'] );
			}
		}
	}

	/**
	 * Generator function to migrate user roles to a custom table.
	 * 
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 * @return \Generator Yields migration progress data.
	 */
	public static function do_migration( $args = array(), $assoc_args = array() ) {
		global $wpdb;

		// Check if multisite is enabled and setup properly.
		$multisite = is_multisite();

		$site_ids = $multisite ? wp_list_pluck( get_sites(), 'blog_id' ) : array( 1 );

		// Truncate the custom user roles table.
		if ( empty( $assoc_args['preserve'] ) || ! $assoc_args['preserve'] ) {
			yield array(
				'type'    => 'message',
				'message' => __( 'Truncating the custom user roles table...', 'user-roles-table' ),
			);

			$wpdb->query( "TRUNCATE TABLE {$wpdb->base_prefix}user_roles" );
		}

		// Count for all sites.
		$total_users_count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->users );

		yield array(
			'type'  => 'start',
			'count' => $total_users_count,
		);

		yield array(
			'type'    => 'message',
			'message' => __( 'Migrating user roles...', 'user-roles-table' ),
		);

		foreach ( $site_ids as $site_id ) {
			if ( $multisite ) {
				switch_to_blog( $site_id );

				yield array(
					'type'    => 'message',
					'message' => sprintf(
						/* translators: %d: Site ID */
						__( 'Migrating user roles for site %d...', 'user-roles-table' ),
						$site_id
					),
				);
			}

			$users = $wpdb->get_results(
				"
			SELECT 		ID, meta_value
			FROM 		$wpdb->users
			LEFT JOIN 	$wpdb->usermeta
				ON 		$wpdb->users.ID = $wpdb->usermeta.user_id
			WHERE 		meta_key = '{$wpdb->prefix}capabilities'
			ORDER BY 	ID ASC
			"
			);

			foreach ( $users as $user ) {
				$roles = (array) array_keys( maybe_unserialize( $user->meta_value ) );
	
				foreach ( $roles as $role ) {
					$wpdb->insert(
						"{$wpdb->base_prefix}user_roles",
						array(
							'user_id' => $user->ID,
							'site_id' => $site_id,
							'role'    => $role,
						),
						array(
							'%d',
							'%d',
							'%s',
						)
					);
				}

				yield array(
					'type'    => 'progress',
					'user_id' => $user->ID,
					'roles'   => $roles,
				);
			}
		}

		yield array(
			'type'    => 'finish',
			'message' => sprintf(
				/* translators: %1$d: Number of users, %2$s: User or users */
				__( 'Migrated %1$d %2$s to the custom user roles table.', 'user-roles-table' ),
				$total_users_count,
				_n( 'user', 'users', $total_users_count, 'user-roles-table' ) 
			),
		);
   
		update_site_option( 'user_role_tables_migrated', time() );
	}
}

/**
 * Install the custom user roles table.
 */
if ( class_exists( '\WP_CLI' ) ) {
	$user_roles_table_cli = new User_Roles_Table_CLI();

	WP_CLI::add_command( 'user-roles-table', $user_roles_table_cli );
}
