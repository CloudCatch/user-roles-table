<?php
/**
 * Class Multisite_WP_User_Query_Shoehorn_Test
 *
 * @package UserRolesTable
 */

/**
 * Shoehorn test case.
 */
class Multisite_WP_User_Query_Shoehorn_Test extends WP_UnitTestCase {

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
	 * User 4 ID.
	 *
	 * @var int
	 */
	protected static $user_4_id;

	/**
	 * User 5 ID.
	 *
	 * @var int
	 */
	protected static $user_5_id;

	/**
	 * User 6 ID.
	 *
	 * @var int
	 */
	protected static $user_6_id;

	/**
	 * User 7 ID.
	 *
	 * @var int
	 */
	protected static $user_7_id;

	/**
	 * User 8 ID.
	 *
	 * @var int
	 */
	protected static $user_8_id;


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
		remove_role( 'role4' );

		// Remove all custom users
		wpmu_delete_user( self::$user_1_id );
		wpmu_delete_user( self::$user_2_id );
		wpmu_delete_user( self::$user_3_id );
		wpmu_delete_user( self::$user_4_id );
		wpmu_delete_user( self::$user_5_id );
		wpmu_delete_user( self::$user_6_id );
		wpmu_delete_user( self::$user_7_id );
		wpmu_delete_user( self::$user_8_id );
	}

	public function test_multisite_delete_user() {
		global $wpdb;

		$site_id = get_current_blog_id();
		self::$user_4_id = parent::factory()->user->create( array( 'role' => 'role3' ) );

		add_user_to_blog( $site_id, self::$user_4_id, 'role1' );

		$new_user = get_userdata( self::$user_4_id );
		$new_user->add_role( 'role2' );

		// Count how many rows in our table for this user.
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->base_prefix}user_roles WHERE user_id = %d", self::$user_4_id ) );

		$this->assertEquals( 2, $count );

		// Create new blog.
		$site_id = wpmu_create_blog( 'test2', '/test2/', 'Test2', 1 );

		// Switch to new blog.
		switch_to_blog( $site_id );

		add_user_to_blog( $site_id, self::$user_4_id, 'subscriber' );
		
		$count2 = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->base_prefix}user_roles WHERE user_id = %d", self::$user_4_id ) );

		$this->assertEquals( 3, $count2 );

		// Delete user.
		wp_delete_user( self::$user_4_id );

		$count3 = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->base_prefix}user_roles WHERE user_id = %d", self::$user_4_id ) );

		$this->assertEquals( 2, $count3 );

		// Delete user from network.
		wpmu_delete_user( self::$user_4_id );

		$count4 = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->base_prefix}user_roles WHERE user_id = %d", self::$user_4_id ) );

		$this->assertEquals( 0, $count4 );
	}

	public function test_multisite() {
		global $wpdb;

		// Add new role to main site.
		add_role( 'role4', 'Role 4', array( 'read' => true ) );

		// Add role to user on main site.
		$u = get_userdata( self::$user_1_id );
		$u->add_role( 'role4' );

		$u2 = get_userdata( self::$user_2_id );
		$u2->add_role( 'role4' );

		// Query users by role.
		$native = new \WP_User_Query( array( 'role' => 'role4', 'roles_table' => true ) );
		$user_roles = new \WP_User_Query( array( 'role' => 'role4' ) );

		$this->assertEquals( $native->get_results(), $user_roles->get_results() );
		$this->assertEquals( 2, $native->get_total() );

		// Create new site.
		$site_id = wpmu_create_blog( 'test', '/test/', 'Test', 1 );

		// Switch to new site.
		switch_to_blog( $site_id );

		$this->assertEquals( 2, count( get_sites() ) );

		$migration_completed = get_site_option( 'user_role_tables_migrated', '' );

		$this->assertNotEmpty( $migration_completed );

		// Verify only 1 table exists.
		$this->assertEquals( 1, $wpdb->get_var( "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '{$wpdb->dbname}' AND table_name = '{$wpdb->base_prefix}user_roles'" ) );

		// Verify we are on the new site.
		$this->assertEquals( get_current_blog_id(), $site_id );

		// Add new role to new site.
		add_role( 'role4', 'Role 4', array( 'read' => true ) );

		// Add user to new site.
		self::$user_5_id = parent::factory()->user->create( array( 'role' => 'role4' ) );

		$this->assertContains( 'role4', get_user_by( 'id', self::$user_5_id )->roles );

		// Query users by role.
		$native = new \WP_User_Query( array( 'role' => 'role4', 'roles_table' => true ) );
		$user_roles = new \WP_User_Query( array( 'role' => 'role4' ) );

		$this->assertEquals( $native->get_results(), $user_roles->get_results() );
		$this->assertEquals( 1, $native->get_total() );

		add_role( 'role5', 'Role 5', array( 'read' => true ) );

		self::$user_6_id = parent::factory()->user->create( array( 'role' => 'role5' ) );
		self::$user_7_id = parent::factory()->user->create( array( 'role' => 'role5' ) );
		self::$user_8_id = parent::factory()->user->create( array( 'role' => 'role5' ) );

		$u = get_userdata( self::$user_8_id );
		$u->add_role( 'role4' );

		$native = new \WP_User_Query( array( 'role' => 'role5', 'roles_table' => true ) );
		$user_roles = new \WP_User_Query( array( 'role' => 'role5' ) );

		$this->assertEquals( $native->get_results(), $user_roles->get_results() );

		$native = new \WP_User_Query( array( 'role' => array( 'role4' , 'role5' ), 'roles_table' => true ) );
		$user_roles = new \WP_User_Query( array( 'role' => array( 'role4' , 'role5' ) ) );

		$this->assertEquals( $native->get_results(), $user_roles->get_results() );

		$native = new \WP_User_Query( array( 'number' => 20, 'roles_table' => true ) );
		$user_roles = new \WP_User_Query( array( 'number' => 20 ) );

		$this->assertEquals( $native->get_results(), $user_roles->get_results() );

		// Switch back to main site.
		switch_to_blog( 1 );
	}
}
