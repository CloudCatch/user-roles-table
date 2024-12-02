# User Role Tables for WP_User_Query

A lightweight and efficient WordPress plugin that mirrors user roles into a custom database table to optimize performance for `WP_User_Query` and `get_users()` calls. By offloading role-based queries to a dedicated table, this plugin reduces database overhead, making large-scale user management faster and more reliable.

## Installation
Install and activate the plugin. This should automatically install the database table: `wp_user_roles`.

Run the following command to copy all user role data to the new `wp_user_roles` table:

```bash
wp user-roles-table migrate
```

This command will extract all users roles and capabilities from `wp_postmeta`, and insert this data is a more performant schema in the `wp_user_roles` database table.

## Running Unit Tests

To ensure the plugin works as expected, you can run the included unit tests. Follow the steps below:

### Prerequisites
- Make sure you have [Docker](https://www.docker.com/) installed and running on your machine (required for `wp-env`).
- Ensure you have [Composer](https://getcomposer.org/) installed to manage PHP dependencies.

### Steps to Run Tests
1. **Start the WordPress Environment:**
    Use `wp-env` to spin up a local WordPress environment:
    ```bash
    wp-env start
2. **Install Dependencies:** 
    If you haven't already, install the Composer dependencies:
    ```bash
    composer install
3. **Run the Tests:** 
    Execute the Composer test script to run the unit tests:
    ```bash
    composer test