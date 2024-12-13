<?php
/**
 * Class WP_User_Query_Shoehorn_Test
 *
 * @package UserRolesTable
 */

/**
 * Shoehorn test case.
 */
class WP_User_Query_Shoehorn_Test extends WP_UnitTestCase {

	/**
	 * User 1 ID.
	 *
	 * @var int
	 */
	protected static $user_1_id;

	/**
	 * User 2 ID.
	 *
	 * @var int
	 */
	protected static $user_2_id;

	/**
	 * User 3 ID.
	 *
	 * @var int
	 */
	protected static $user_3_id;


	/**
	 * Create some users and roles and capabilities
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		// Install the custom table
		$user_roles_table_cli = new \CloudCatch\UserRolesTable\User_Roles_Table_CLI();
		$user_roles_table_cli::do_install();

		// Create some custom capabilities
		$custom_capabilities = array(
			'read',
			'test_cap1',
			'test_cap2',
			'test_cap3',
		);

		// Create some custom roles with random capabilities
		add_role( 'role1', 'Role 1', array_combine( $custom_capabilities, array_fill( 0, count( $custom_capabilities ), true ) ) );
		add_role( 'role2', 'Role 2', array_combine( $custom_capabilities, array_fill( 0, count( $custom_capabilities ), true ) ) );
		add_role( 'role3', 'Role 3', array_combine( $custom_capabilities, array_fill( 0, count( $custom_capabilities ), true ) ) );

		// Create some users
		self::$user_1_id = parent::factory()->user->create( array( 'role' => 'role1' ) );
		self::$user_2_id = parent::factory()->user->create( array( 'role' => 'role2' ) );
		self::$user_3_id = parent::factory()->user->create( array( 'role' => 'role3' ) );

		$u = new WP_User( self::$user_3_id );
		$u->add_role( 'role1' );
		$u->add_role( 'role2' );

		// Add random user meta to each user.
		update_user_meta( self::$user_1_id, 'test_meta1', 'test1' );
		update_user_meta( self::$user_2_id, 'test_meta1', 'test1' );
		update_user_meta( self::$user_2_id, 'test_meta2', 'test2' );
		update_user_meta( self::$user_3_id, 'test_meta3', 'test3' );

		$generator = $user_roles_table_cli::do_migration();

		foreach ( $generator as $result ) {
			// Do nothing
		}
	}

	public static function tear_down_after_class() {
		parent::tear_down_after_class();

		// Remove all custom roles
		remove_role( 'role1' );
		remove_role( 'role2' );
		remove_role( 'role3' );

		// Remove all custom users
		wp_delete_user( self::$user_1_id );
		wp_delete_user( self::$user_1_id );
		wp_delete_user( self::$user_1_id );
	}

	/**
	 * Test that the table was installed.
	 */
	public function test_install() {
		global $wpdb;

		$this->assertEquals( 1, $wpdb->get_var( "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '{$wpdb->dbname}' AND table_name = '{$wpdb->base_prefix}user_roles'" ) );
	}

	/**
	 * Test that the migration completed.
	 */
	public function test_migrated() {
		global $wpdb;

		$migration_completed = get_site_option( 'user_role_tables_migrated', '' );

		$this->assertNotEmpty( $migration_completed );

		$this->assertGreaterThan( 0, $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}user_roles" ) );

		// Test how many rows user ID 3 has.
		$this->assertEquals( 3, $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->base_prefix}user_roles WHERE user_id = %d", self::$user_3_id ) ) );
	}

	/**
	 * Test that the shoehorn is only applied under certain conditions.
	 */
	public function test_wp_user_query_hash_exists() {
		add_filter( 'enable_roles_table_integration', '__return_true' );

		$user_roles = new \WP_User_Query( array( 'role' => 'role1' ) );

		remove_all_filters( 'enable_roles_table_integration' );

		$this->assertTrue( $user_roles->get( 'roles_table' ) );
	}

	/**
	 * Test that the request is different.
	 */
	public function test_requests_not_equal() {
		$native = new \WP_User_Query( array( 'role' => 'role1', 'roles_table' => true ) );
		$user_roles = new \WP_User_Query( array( 'role' => 'role1' ) );

		$this->assertNotEquals( $native->request, $user_roles->request );
	}

	/**
	 * Test getting a single role.
	 */
	public function test_query_users_by_role() {
		$native = new \WP_User_Query( array( 'role' => 'role1', 'roles_table' => true ) );
		$user_roles = new \WP_User_Query( array( 'role' => 'role1' ) );

		$this->assertEquals( $native->get_results(), $user_roles->get_results() );
	}

	/**
	 * Test getting users with multiple roles.
	 */
	public function test_query_users_by_role_multiple() {
		$native = new \WP_User_Query( array( 'role' => 'role1,role2', 'roles_table' => true ) );
		$user_roles = new \WP_User_Query( array( 'role' => 'role1,role2' ) );

		$this->assertEquals( $native->get_results(), $user_roles->get_results() );
	}

	/**
	 * Test getting users with role__in.
	 */
	public function test_query_users_by_role__in() {
		$native = new \WP_User_Query( array( 'role__in' => array( 'role1' , 'role2' ), 'roles_table' => true ) );
		$user_roles = new \WP_User_Query( array( 'role__in' => array( 'role1' , 'role2' ) ) );

		$this->assertEquals( $native->get_results(), $user_roles->get_results() );
	}

	/**
	 * Test getting users with role__not_in.
	 */
	public function test_query_users_by_role__not_in() {
		$native = new \WP_User_Query( array( 'role__not_in' => array( 'role1' , 'role2' ), 'roles_table' => true ) );
		$user_roles = new \WP_User_Query( array( 'role__not_in' => array( 'role1' , 'role2' ) ) );

		$this->assertEquals( $native->get_results(), $user_roles->get_results() );
	}

	/**
	 * Test getting users with capability.
	 */
	public function test_query_users_by_capability() {
		$native = new \WP_User_Query( array( 'capability' => 'test_cap1', 'roles_table' => true ) );
		$user_roles = new \WP_User_Query( array( 'capability' => 'test_cap1' ) );

		$this->assertEquals( $native->get_results(), $user_roles->get_results() );
	}

	/**
	 * Test getting users with capability__in.
	 */
	public function test_query_users_by_capability__in() {
		$native = new \WP_User_Query( array( 'capability__in' => array( 'test_cap1' , 'test_cap2' ), 'roles_table' => true ) );
		$user_roles = new \WP_User_Query( array( 'capability__in' => array( 'test_cap1' , 'test_cap2' ) ) );

		$this->assertEquals( $native->get_results(), $user_roles->get_results() );
	}

	/**
	 * Test getting users with capability__not_in.
	 */
	public function test_query_users_by_capability__not_in() {
		$native = new \WP_User_Query( array( 'capability__not_in' => array( 'test_cap1' , 'test_cap2' ), 'roles_table' => true ) );
		$user_roles = new \WP_User_Query( array( 'capability__not_in' => array( 'test_cap1' , 'test_cap2' ) ) );

		$this->assertEquals( $native->get_results(), $user_roles->get_results() );
	}

	/**
	 * Test getting users with role and capability.
	 */
	public function test_query_users_by_role_and_capability() {
		$native = new \WP_User_Query( array( 'role' => 'role1', 'capability' => 'test_cap1', 'roles_table' => true ) );
		$user_roles = new \WP_User_Query( array( 'role' => 'role1', 'capability' => 'test_cap1' ) );

		$this->assertEquals( $native->get_results(), $user_roles->get_results() );
	}

	/**
	 * Test getting users with role and capability__in.
	 */
	public function test_query_users_by_role_and_capability__in() {
		$native = new \WP_User_Query( array( 'role' => 'role1', 'capability__in' => array( 'test_cap1' , 'test_cap2' ), 'roles_table' => true ) );
		$user_roles = new \WP_User_Query( array( 'role' => 'role1', 'capability__in' => array( 'test_cap1' , 'test_cap2' ) ) );

		$this->assertEquals( $native->get_results(), $user_roles->get_results() );
	}

	/**
	 * Test getting users with role and capability__not_in.
	 */
	public function test_query_users_by_role_and_capability__not_in() {
		$native = new \WP_User_Query( array( 'role' => 'role1', 'capability__not_in' => array( 'test_cap1' , 'test_cap2' ), 'roles_table' => true ) );
		$user_roles = new \WP_User_Query( array( 'role' => 'role1', 'capability__not_in' => array( 'test_cap1' , 'test_cap2' ) ) );

		$this->assertEquals( $native->get_results(), $user_roles->get_results() );
	}

	/**
	 * Test getting users with role__in and capability.
	 */
	public function test_query_users_by_role__in_and_capability() {
		$native = new \WP_User_Query( array( 'role__in' => array( 'role1' , 'role2' ), 'capability' => 'test_cap1', 'roles_table' => true ) );
		$user_roles = new \WP_User_Query( array( 'role__in' => array( 'role1' , 'role2' ), 'capability' => 'test_cap1' ) );

		$this->assertEquals( $native->get_results(), $user_roles->get_results() );
	}

	/**
	 * Test getting users with role__in and capability__in.
	 */
	public function test_query_users_by_role__in_and_capability__in() {
		$native = new \WP_User_Query( array( 'role__in' => array( 'role1' , 'role2' ), 'capability__in' => array( 'test_cap1' , 'test_cap2' ), 'roles_table' => true ) );
		$user_roles = new \WP_User_Query( array( 'role__in' => array( 'role1' , 'role2' ), 'capability__in' => array( 'test_cap1' , 'test_cap2' ) ) );

		$this->assertEquals( $native->get_results(), $user_roles->get_results() );
	}

	/**
	 * Test getting users with role__in and capability__not_in.
	 */
	public function test_query_users_by_role__in_and_capability__not_in() {
		$native = new \WP_User_Query( array( 'role__in' => array( 'role1' , 'role2' ), 'capability__not_in' => array( 'test_cap1' , 'test_cap2' ), 'roles_table' => true ) );
		$user_roles = new \WP_User_Query( array( 'role__in' => array( 'role1' , 'role2' ), 'capability__not_in' => array( 'test_cap1' , 'test_cap2' ) ) );

		$this->assertEquals( $native->get_results(), $user_roles->get_results() );
	}

	/**
	 * Test getting users with role__not_in and capability.
	 */
	public function test_query_users_by_role__not_in_and_capability() {
		$native = new \WP_User_Query( array( 'role__not_in' => array( 'role1' , 'role2' ), 'capability' => 'test_cap1', 'roles_table' => true ) );
		$user_roles = new \WP_User_Query( array( 'role__not_in' => array( 'role1' , 'role2' ), 'capability' => 'test_cap1' ) );

		$this->assertEquals( $native->get_results(), $user_roles->get_results() );
	}

	/**
	 * Test getting users with role__not_in and capability__in.
	 */
	public function test_query_users_by_role__not_in_and_capability__in() {
		$native = new \WP_User_Query( array( 'role__not_in' => array( 'role1' , 'role2' ), 'capability__in' => array( 'test_cap1' , 'test_cap2' ), 'roles_table' => true ) );
		$user_roles = new \WP_User_Query( array( 'role__not_in' => array( 'role1' , 'role2' ), 'capability__in' => array( 'test_cap1' , 'test_cap2' ) ) );

		$this->assertEquals( $native->get_results(), $user_roles->get_results() );
	}

	/**
	 * Test getting users with role__not_in and capability__not_in.
	 */
	public function test_query_users_by_role__not_in_and_capability__not_in() {
		$native = new \WP_User_Query( array( 'role__not_in' => array( 'role1' , 'role2' ), 'capability__not_in' => array( 'test_cap1' , 'test_cap2' ), 'roles_table' => true ) );
		$user_roles = new \WP_User_Query( array( 'role__not_in' => array( 'role1' , 'role2' ), 'capability__not_in' => array( 'test_cap1' , 'test_cap2' ) ) );

		$this->assertEquals( $native->get_results(), $user_roles->get_results() );
	}

	/**
	 * Test getting users with multiple roles and capabilities.
	 */
	public function test_query_users_by_role_and_capability_multiple() {
		$native = new \WP_User_Query( array( 'role' => 'role1,role2', 'capability' => 'test_cap1,test_cap2', 'roles_table' => true ) );
		$user_roles = new \WP_User_Query( array( 'role' => 'role1,role2', 'capability' => 'test_cap1,test_cap2' ) );

		$this->assertEquals( $native->get_results(), $user_roles->get_results() );
	}

	/**
	 * Test getting users with multiple roles and capabilities.
	 */
	public function test_query_users_by_role__in_and_capability__in_multiple() {
		$native = new \WP_User_Query( array( 'role__in' => array( 'role1' , 'role2' ), 'capability__in' => array( 'test_cap1' , 'test_cap2' ), 'roles_table' => true ) );
		$user_roles = new \WP_User_Query( array( 'role__in' => array( 'role1' , 'role2' ), 'capability__in' => array( 'test_cap1' , 'test_cap2' ) ) );

		$this->assertEquals( $native->get_results(), $user_roles->get_results() );
	}

	/**
	 * Test getting users with multiple roles and capabilities.
	 */
	public function test_query_users_by_role__not_in_and_capability__not_in_multiple() {
		$native = new \WP_User_Query( array( 'role__not_in' => array( 'role1' , 'role2' ), 'capability__not_in' => array( 'test_cap1' , 'test_cap2' ), 'roles_table' => true ) );
		$user_roles = new \WP_User_Query( array( 'role__not_in' => array( 'role1' , 'role2' ), 'capability__not_in' => array( 'test_cap1' , 'test_cap2' ) ) );

		$this->assertEquals( $native->get_results(), $user_roles->get_results() );
	}

	/**
	 * Test getting users with fields parameter.
	 */
	public function test_query_users_by_fields() {
		$native = new \WP_User_Query( array( 'fields' => 'all', 'role' => 'role1', 'roles_table' => true ) );
		$user_roles = new \WP_User_Query( array( 'fields' => 'all', 'role' => 'role1' ) );

		$this->assertEquals( $native->get_results(), $user_roles->get_results() );

		$native2 = new \WP_User_Query( array( 'fields' => 'display_name', 'role' => 'role1', 'roles_table' => true ) );
		$user_roles2 = new \WP_User_Query( array( 'fields' => 'display_name', 'role' => 'role1' ) );

		$this->assertEquals( $native2->get_results(), $user_roles2->get_results() );
	}

	/**
	 * Test SQL_CALC_FOUND_ROWS after the query.
	 */
	public function test_query_users_sql_calc_found_rows() {
		$native = new \WP_User_Query( array( 'role' => 'role1', 'roles_table' => true ) );
		$user_roles = new \WP_User_Query( array( 'role' => 'role1' ) );

		$this->assertEquals( $native->get_total(), $user_roles->get_total() );
	}

	public function test_query_users_by_meta() {
		$native = new \WP_User_Query( array( 'meta_key' => 'test_meta1', 'meta_value' => 'test1', 'roles_table' => true ) );
		$user_roles = new \WP_User_Query( array( 'meta_key' => 'test_meta1', 'meta_value' => 'test1' ) );

		$this->assertEquals( $native->get_results(), $user_roles->get_results() );
		$this->assertEquals( $native->get_total(), 2 );

		$native = new \WP_User_Query( array( 'meta_query' => array( array( 'key' => 'test_meta1', 'value' => 'test1' ) ), 'roles_table' => true ) );
		$user_roles = new \WP_User_Query( array( 'meta_query' => array( array( 'key' => 'test_meta1', 'value' => 'test1' ) ) ) );

		$this->assertEquals( $native->get_results(), $user_roles->get_results() );
		$this->assertEquals( $native->get_total(), 2 );

		$native = new \WP_User_Query( array( 'meta_query' => array( array( 'key' => 'test_meta1', 'compare' => 'NOT EXISTS' ) ), 'roles_table' => true ) );
		$user_roles = new \WP_User_Query( array( 'meta_query' => array( array( 'key' => 'test_meta1', 'compare' => 'NOT EXISTS' ) ) ) );

		$this->assertEquals( $native->get_results(), $user_roles->get_results() );
	}

	public function test_query_users_search() {
		global $wpdb;
		
		$native = new \WP_User_Query( array( 'search' => 'test1', 'roles_table' => true ) );
		$user_roles = new \WP_User_Query( array( 'search' => 'test1' ) );

		$this->assertEquals( $native->get_results(), $user_roles->get_results() );

		// Update name of user 1
		$updated = wp_update_user( array( 
			'ID' => self::$user_1_id, 
			'first_name' => 'John', 
			'last_name' => 'Doe', 
			'display_name' => 'John Doe',
			'user_nicename' => 'john-doe',
			'user_login' => 'john-doe',
			'user_email' => 'johndoe@example.com',
		) );

		$this->assertEquals( $updated, self::$user_1_id );

		$native = new \WP_User_Query( array( 
			'role__in'       => array(
				'role1',
			),
			'fields'         => array( 'ID', 'display_name' ),
			'search'         => '*John*',
			'search_columns' => array(
				'ID',
				'user_login',
				'user_nicename',
				'user_email',
				'display_name',
			),
			'roles_table' => true 
		) );

		$user_roles = new \WP_User_Query( array( 
			'role__in'       => array(
				'role1',
			),
			'fields'         => array( 'ID', 'display_name' ),
			'search'         => '*John*',
			'search_columns' => array(
				'ID',
				'user_login',
				'user_nicename',
				'user_email',
				'display_name',
			),
		) );

		$this->assertCount( 1, $native->get_results() );
		$this->assertEquals( $native->get_results(), $user_roles->get_results() );
		$this->assertStringNotContainsString( $wpdb->users . '.user_login', $user_roles->request );
		$this->assertStringContainsString( $wpdb->users . '.user_login', $native->request );

		$native = new \WP_User_Query( array( 
			'role__in'       => array(
				'role1',
			),
			'search'         => '*John*',
			'roles_table' => true 
		) );

		$user_roles = new \WP_User_Query( array( 
			'role__in'       => array(
				'role1',
			),
			'search'         => '*John*',
		) );

		$this->assertCount( 1, $native->get_results() );
		$this->assertEquals( $native->get_results(), $user_roles->get_results() );

		$native = new \WP_User_Query( array( 
			'role__in'       => array(
				'role1',
			),
			'search'         => 'johndoe@example.com',
			'roles_table' => true 
		) );

		$user_roles = new \WP_User_Query( array( 
			'role__in'       => array(
				'role1',
			),
			'search'         => 'johndoe@example.com',
		) );

		$this->assertCount( 1, $native->get_results() );
		$this->assertEquals( $native->get_results(), $user_roles->get_results() );
		$this->assertStringNotContainsString( $wpdb->users . '.user_email', $user_roles->request );
		$this->assertStringContainsString( $wpdb->users . '.user_email', $native->request );
	}
}
