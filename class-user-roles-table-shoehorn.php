<?php
/**
 * User Role Tables Shoehorn
 * 
 * @package UserRolesTable
 */

namespace CloudCatch\UserRolesTable;

use WP_User_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User Role Tables Shoehorn
 */
class User_Roles_Table_Shoehorn {

	/**
	 * Hash
	 *
	 * @var string
	 */
	private $hash;

	/**
	 * Original Query
	 *
	 * @var WP_User_Query
	 */
	private $original_query;

	/**
	 * Roles
	 *
	 * @var string|string[]
	 */
	private $roles;

	/**
	 * Role__in
	 *
	 * @var string[]
	 */
	private $role__in;

	/**
	 * Role__not_in
	 *
	 * @var string[]
	 */
	private $role__not_in;

	/**
	 * Capabilities
	 *
	 * @var string|string[]
	 */
	private $capabilities;

	/**
	 * Capability__in
	 *
	 * @var string[]
	 */
	private $capability__in;

	/**
	 * Capability__not_in
	 *
	 * @var string[]
	 */
	private $capability__not_in;

	/**
	 * Caps with Roles
	 *
	 * @var array
	 */
	private $caps_with_roles = array();

	/**
	 * Constructor
	 *
	 * @param WP_User_Query $query Query object.
	 */
	public function __construct( &$query ) {
		$this->hash           = spl_object_hash( $query );
		$this->original_query = clone $query;

		$query->set( 'user_role_tables_hash', $this->hash );

		$this->prepare_query_vars( $query );
		$this->unload_query_vars( $query );

		add_action( 'pre_user_query', array( $this, 'filter_user_query' ), 1000 );
	}

	/**
	 * Filter User Query
	 *
	 * @param WP_User_Query $query Query object.
	 * @global wpdb $wpdb WordPress database abstraction object.
	 * @return void
	 */
	public function filter_user_query( $query ) {
		global $wpdb;

		// Bail if this is not the original query.
		if ( $query->get( 'user_role_tables_hash' ) !== $this->hash ) {
			return;
		}

		$qv = $this->original_query->query_vars;

		$blog_id = 0;

		if ( isset( $qv['blog_id'] ) ) {
			$blog_id = absint( $qv['blog_id'] );
		}

		$roles                = $this->roles;
		$role__in             = $this->role__in;
		$role__not_in         = $this->role__not_in;
		$capabilities         = $this->capabilities;
		$capabilities__in     = $this->capability__in;
		$capabilities__not_in = $this->capability__not_in;
		$caps_with_roles      = $this->caps_with_roles;
	
		$joins = array();
		$where = array();
	
		if ( $roles ) {
			foreach ( $roles as $index => $role ) {
				$joins[] = $wpdb->prepare(
					" INNER JOIN {$wpdb->prefix}user_roles ur{$index} ON {$wpdb->users}.ID = ur{$index}.user_id AND ur{$index}.role = %s",
					$role
				);
			}
		}
	
		if ( $role__in ) {
			$role_in_placeholders = implode( ',', array_fill( 0, count( $role__in ), '%s' ) );
			$joins[]              = $wpdb->prepare(
				" INNER JOIN {$wpdb->prefix}user_roles ur_in ON {$wpdb->users}.ID = ur_in.user_id AND ur_in.role IN ($role_in_placeholders)",
				...$role__in
			);
		}
	
		if ( $role__not_in ) {
			$role_not_in_placeholders = implode( ',', array_fill( 0, count( $role__not_in ), '%s' ) );
			$joins[]                  = $wpdb->prepare(
				" LEFT JOIN {$wpdb->prefix}user_roles ur_not_in ON {$wpdb->users}.ID = ur_not_in.user_id AND ur_not_in.role IN ($role_not_in_placeholders)",
				...$role__not_in
			);
			$where[]                  = 'ur_not_in.role IS NULL';
		}
	
		foreach ( $capabilities as $index => $cap ) {
			if ( ! empty( $caps_with_roles[ $cap ] ) ) {
				$cap_role_placeholders = implode( ',', array_fill( 0, count( $caps_with_roles[ $cap ] ), '%s' ) );
				$joins[]               = " INNER JOIN {$wpdb->prefix}user_roles ur_caps{$index} ON {$wpdb->users}.ID = ur_caps{$index}.user_id";
				$where[]               = $wpdb->prepare(
					"ur_caps{$index}.role IN ($cap_role_placeholders)",
					...$caps_with_roles[ $cap ]
				);
			}
		}
	
		if ( $blog_id && ! empty( $capabilities ) ) {
			foreach ( $capabilities as $index => $cap ) {
				$clause = array();
		
				$clause[] = $wpdb->prepare(
					"(ur_capsa{$index}.role = %s)",
					$cap
				);
		
				if ( ! empty( $caps_with_roles[ $cap ] ) ) {
					$role_placeholders = implode( ',', array_fill( 0, count( $caps_with_roles[ $cap ] ), '%s' ) );
					$clause[]          = $wpdb->prepare(
						"(ur_capsa{$index}.role IN ($role_placeholders))",
						...$caps_with_roles[ $cap ]
					);
				}
		
				$joins[] = " INNER JOIN {$wpdb->prefix}user_roles ur_capsa{$index} ON {$wpdb->users}.ID = ur_capsa{$index}.user_id";
		
				$where[] = '(' . implode( ' OR ', $clause ) . ')';
			}
		}
	
		$query->query_from .= implode( ' ', $joins );
		if ( ! empty( $where ) ) {
			$query->query_where .= ' AND (' . implode( ' AND ', $where ) . ')';
		}
	
		if ( strpos( $query->query_orderby, 'GROUP BY' ) === false ) {
			$query->query_orderby = " GROUP BY {$wpdb->users}.ID " . $query->query_orderby;
		}
	}

	/**
	 * Prepare query vars.
	 *
	 * @param WP_User_Query $query Query object.
	 * @return void
	 */
	private function prepare_query_vars( &$query ) {
		global $wp_roles;
		
		$qv = $query->query_vars;

		$blog_id = 0;

		if ( isset( $qv['blog_id'] ) ) {
			$blog_id = absint( $qv['blog_id'] );
		}

		// Roles.
		$roles = array();
		if ( isset( $qv['role'] ) ) {
			if ( is_array( $qv['role'] ) ) {
				$roles = $qv['role'];
			} elseif ( is_string( $qv['role'] ) && ! empty( $qv['role'] ) ) {
				$roles = array_map( 'trim', explode( ',', $qv['role'] ) );
			}
		}

		$role__in = array();
		if ( isset( $qv['role__in'] ) ) {
			$role__in = (array) $qv['role__in'];
		}

		$role__not_in = array();
		if ( isset( $qv['role__not_in'] ) ) {
			$role__not_in = (array) $qv['role__not_in'];
		}

		// Capabilities.
		$available_roles = array();

		if ( ! empty( $qv['capability'] ) || ! empty( $qv['capability__in'] ) || ! empty( $qv['capability__not_in'] ) ) {
			$wp_roles->for_site( $blog_id );
			$available_roles = $wp_roles->roles;
		}

		$capabilities = array();
		if ( ! empty( $qv['capability'] ) ) {
			if ( is_array( $qv['capability'] ) ) {
				$capabilities = $qv['capability'];
			} elseif ( is_string( $qv['capability'] ) ) {
				$capabilities = array_map( 'trim', explode( ',', $qv['capability'] ) );
			}
		}

		$capability__in = array();
		if ( ! empty( $qv['capability__in'] ) ) {
			$capability__in = (array) $qv['capability__in'];
		}

		$capability__not_in = array();
		if ( ! empty( $qv['capability__not_in'] ) ) {
			$capability__not_in = (array) $qv['capability__not_in'];
		}

		foreach ( $available_roles as $role => $role_data ) {
			$role_caps = array_keys( array_filter( $role_data['capabilities'] ) );

			foreach ( $capabilities as $cap ) {
				if ( in_array( $cap, $role_caps, true ) ) {
					$this->caps_with_roles[ $cap ][] = $role;
					break;
				}
			}

			foreach ( $capability__in as $cap ) {
				if ( in_array( $cap, $role_caps, true ) ) {
					$role__in[] = $role;
					break;
				}
			}

			foreach ( $capability__not_in as $cap ) {
				if ( in_array( $cap, $role_caps, true ) ) {
					$role__not_in[] = $role;
					break;
				}
			}
		}

		$this->roles              = array_unique( $roles );
		$this->role__in           = array_unique( array_merge( $role__in, $capability__in ) );
		$this->role__not_in       = array_unique( array_merge( $role__not_in, $capability__not_in ) );
		$this->capabilities       = $capabilities;
		$this->capability__in     = $capability__in;
		$this->capability__not_in = $capability__not_in;
	}

	/**
	 * Remove relevant query vars to prevent meta queries from running.
	 *
	 * @param WP_User_Query $query Query object.
	 * @return void
	 */
	private function unload_query_vars( &$query ) {
		$query->set( 'role', null );
		$query->set( 'role__in', null );
		$query->set( 'role__not_in', null );
		$query->set( 'capability', null );
		$query->set( 'capability__in', null );
		$query->set( 'capability__not_in', null );
		$query->set( 'caps_with_roles', null );
	}
}
