# D1DB: A wpdb-like Interface for Cloudflare D1

## 📣 Overview

**D1DB** is a single-file PHP class that provides a comprehensive, `wpdb`-like interface for interacting with a [Cloudflare D1](https://developers.cloudflare.com/d1/) database. It is designed to be a drop-in replacement or supplemental database object within a WordPress environment.

It uses WordPress's built-in HTTP functions (`wp_remote_post`) to communicate with the Cloudflare D1 Client API, allowing you to leverage D1's serverless SQLite database directly from your WordPress theme or plugin.

This class emulates the most common methods and properties of the global `$wpdb` object, making it familiar and easy to use for experienced WordPress developers.

## ✨ Features

* **Familiar `wpdb` Methods:** Implements popular methods like `prepare()`, `query()`, `get_row()`, `get_var()`, `get_results()`, `insert()`, `update()`, and `delete()`.
* **Secure by Default:** The `prepare()` method provides placeholder-based query parameterization to help prevent SQL injection vulnerabilities.
* **WordPress Integration:** Uses `wp_remote_post` for API calls and `wp_die` / admin notices for error handling when `WP_DEBUG` is enabled.
* **High Compatibility:** Includes numerous stub methods and public properties (e.g., `$prefix`, `$last_query`, `$insert_id`, `set_prefix()`) to maximize compatibility with existing code that expects a `$wpdb` object.
* **No External Dependencies:** Relies only on built-in WordPress functions.

## 🚀 Installation & Basic Usage

1.  **Include the class:**
    Place the `wp-cloudflare-d1.php` file in your theme or plugin.
    ```php
    require_once __DIR__ . '/wp-cloudflare-d1.php';
    ```

2.  **Define Constants:**
    It's best practice to store your credentials as constants in `wp-config.php`.
    ```php
    // In wp-config.php
    define( 'CLOUDFLARE_ACCOUNT_ID', 'your_account_id_here' );
    define( 'CLOUDFLARE_API_TOKEN', 'your_api_token_here' );
    define( 'CLOUDFLARE_D1_DB_ID', 'your_database_id_here' );
    ```

3.  **Instantiate the Class:**
    Create a new instance of `D1DB`, passing in your credentials. You can do this wherever you need database access, or assign it to a global variable.
    ```php
    global $d1db;
    $d1db = new D1DB(
        CLOUDFLARE_ACCOUNT_ID,
        CLOUDFLARE_API_TOKEN,
        CLOUDFLARE_D1_DB_ID,
        'wp_' // Optional table prefix
    );
    ```

## 📋 Examples

### Running a Prepared Query (`get_row`)

Just like `$wpdb`, use `prepare()` to safely insert variables into your query.

```php
global $d1db;

$user_id = 1;
$status = 'publish';

// prepare() returns an array [ 'sql' => ..., 'params' => [...] ]
$prepared_query = $d1db->prepare(
    "SELECT * FROM users WHERE ID = %d AND status = %s",
    $user_id,
    $status
);

// Pass the prepared query array to get_row()
$user_row = $d1db->get_row( $prepared_query, ARRAY_A ); // Get as associative array

if ( $user_row ) {
    echo 'Hello, ' . $user_row['username'];
}
