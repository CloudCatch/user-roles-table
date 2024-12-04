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
		$this->hash           = spl_object_id( $query );
		$this->original_query = clone $query;

		$this->prepare_query_vars( $query );
		$this->unload_query_vars( $query );

		$query->set( 'user_role_tables_hash', $this->hash );

		add_filter( 'get_meta_sql', array( $this, 'filter_user_meta_query' ), 1000, 6 );

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

		remove_action( 'pre_user_query', array( $this, 'filter_user_query' ), 1000 );

		$query->set( 'did_roles_table', true );

		$qv = $this->original_query->query_vars;

		$blog_id          = 0;
		$blog_id_to_query = 1;

		if ( isset( $qv['blog_id'] ) ) {
			$blog_id          = absint( $qv['blog_id'] );
			$blog_id_to_query = $blog_id;
		}

		$roles           = $this->roles;
		$role__in        = $this->role__in;
		$role__not_in    = $this->role__not_in;
		$capabilities    = $this->capabilities;
		$caps_with_roles = $this->caps_with_roles;
	
		$joins = array();
		$where = array();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
	
		if ( $roles ) {
			foreach ( $roles as $index => $role ) {
				$index   = (int) $index;
				$joins[] = $wpdb->prepare(
					" INNER JOIN {$wpdb->base_prefix}user_roles ur{$index} ON {$wpdb->users}.ID = ur{$index}.user_id AND ur{$index}.role = %s AND ur{$index}.site_id = %d",
					$role,
					$blog_id_to_query
				);
			}
		}
	
		if ( $role__in ) {
			$role_in_placeholders = implode( ',', array_fill( 0, count( $role__in ), '%s' ) );
			$joins[]              = $wpdb->prepare(
				" INNER JOIN {$wpdb->base_prefix}user_roles ur_in ON {$wpdb->users}.ID = ur_in.user_id AND ur_in.site_id = %d AND ur_in.role IN ($role_in_placeholders)",
				$blog_id_to_query,
				...$role__in,
			);
		}
	
		if ( $role__not_in ) {
			$role_not_in_placeholders = implode( ',', array_fill( 0, count( $role__not_in ), '%s' ) );
			$joins[]                  = $wpdb->prepare(
				" LEFT JOIN {$wpdb->base_prefix}user_roles ur_not_in ON {$wpdb->users}.ID = ur_not_in.user_id AND ur_not_in.site_id = %d AND ur_not_in.role IN ($role_not_in_placeholders)",
				$blog_id_to_query,
				...$role__not_in
			);
			$where[]                  = 'ur_not_in.role IS NULL';
		}
	
		foreach ( $capabilities as $index => $cap ) {
			$index = (int) $index;
			if ( ! empty( $caps_with_roles[ $cap ] ) ) {
				$cap_role_placeholders = implode( ',', array_fill( 0, count( $caps_with_roles[ $cap ] ), '%s' ) );
				$joins[]               = $wpdb->prepare(
					" INNER JOIN {$wpdb->base_prefix}user_roles ur_caps{$index} ON {$wpdb->users}.ID = ur_caps{$index}.user_id AND ur_caps{$index}.site_id = %d",
					$blog_id_to_query
				);
				$where[]               = $wpdb->prepare(
					"ur_caps{$index}.role IN ($cap_role_placeholders)",
					...$caps_with_roles[ $cap ]
				);
			}
		}
	
		if ( $blog_id && ! empty( $capabilities ) ) {
			foreach ( $capabilities as $index => $cap ) {
				$index  = (int) $index;
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
		
				$joins[] = $wpdb->prepare( " INNER JOIN {$wpdb->base_prefix}user_roles ur_capsa{$index} ON {$wpdb->users}.ID = ur_capsa{$index}.user_id AND ur_capsa{$index}.site_id = %d", $blog_id_to_query );
		
				$where[] = '(' . implode( ' OR ', $clause ) . ')';
			}
		}

		if ( $blog_id && ( empty( $roles ) && empty( $role__in ) && empty( $role__not_in ) && empty( $capabilities ) && is_multisite() ) ) {
			$joins[] = $wpdb->prepare(
				" INNER JOIN {$wpdb->base_prefix}user_roles ur_blog ON {$wpdb->users}.ID = ur_blog.user_id AND ur_blog.site_id = %d",
				$blog_id
			);
		}
	
		$query->query_from .= implode( ' ', $joins );
		if ( ! empty( $where ) ) {
			$query->query_where .= ' AND (' . implode( ' AND ', $where ) . ')';
		}
	
		if ( strpos( $query->query_orderby, 'GROUP BY' ) === false ) {
			$query->query_orderby = esc_sql( " GROUP BY {$wpdb->users}.ID " . $query->query_orderby );
		}

		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
	}

	/**
	 * Remove meta queries related to user roles and capabilities.
	 *
	 * @param string[] $sql               Array containing the query's JOIN and WHERE clauses.
	 * @param array    $queries           Array of meta queries.
	 * @param string   $type              Type of meta. Possible values include but are not limited
	 *                                    to 'post', 'comment', 'blog', 'term', and 'user'.
	 * @param string   $primary_table     Primary table.
	 * @param string   $primary_id_column Primary column ID.
	 * @param object   $context           The main query object that corresponds to the type, for
	 *                                    example a `WP_Query`, `WP_User_Query`, or `WP_Site_Query`.
	 * @return string[]
	 */
	public function filter_user_meta_query( $sql, $queries, $type, $primary_table, $primary_id_column, $context ) {
		global $wpdb;

		if ( ! $context instanceof WP_User_Query || $context->get( 'user_role_tables_hash' ) !== $this->hash ) {
			return $sql;
		}

		remove_filter( 'get_meta_sql', array( $this, 'filter_user_meta_query' ), 1000 );

		$qv = $this->original_query->query_vars;

		$blog_id = 0;

		if ( isset( $qv['blog_id'] ) ) {
			$blog_id = absint( $qv['blog_id'] );
		}

		$regenerate = false;

		foreach ( $queries as $index => $query ) {
			if ( ! is_array( $query ) || ! isset( $query['key'] ) ) {
				continue;
			}

			if ( $wpdb->get_blog_prefix( $blog_id ) . 'capabilities' === $query['key'] ) {
				unset( $queries[ $index ] );

				$regenerate = true;
			}
		}

		if ( $regenerate ) {
			$query                      = clone $context;
			$query->meta_query->queries = $queries;

			$sql = $query->meta_query->get_sql( $type, $primary_table, $primary_id_column, $context );
		}

		return $sql;
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
