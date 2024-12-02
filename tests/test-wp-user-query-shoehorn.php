<?php
/**
 * Class WP_User_Query_Shoehorn_Test
 *
 * @package UserRolesTable
 */

/**
 * Sample test case.
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

		$table_name = $wpdb->prefix . 'user_roles';

		$this->assertTrue( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}user_roles'" ) === $table_name );
	}

	/**
	 * Test that the migration completed.
	 */
	public function test_migrated() {
		global $wpdb;

		$migration_completed = get_option( 'user_role_tables_migrated', '' );

		$this->assertNotEmpty( $migration_completed );

		$this->assertGreaterThan( 0, $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}user_roles" ) );
	}

	/**
	 * Test that the shoehorn is only applied under certain conditions.
	 */
	public function test_wp_user_query_hash_exists() {
		add_filter( 'enable_roles_table_integration', '__return_true' );

		$user_roles = new \WP_User_Query( array( 'role' => 'role1' ) );

		$user_query_without_roles = new \WP_User_Query( array( 'number' => 10 ) );

		remove_all_filters( 'enable_roles_table_integration' );

		$this->assertTrue( $user_roles->get( 'roles_table' ) );
		$this->assertEmpty( $user_query_without_roles->get( 'roles_table' ) );
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
}
