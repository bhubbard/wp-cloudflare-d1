<?php

/**
 * Cloudflare D1 Database Access Abstraction Class for WordPress.
 *
 * This class provides a comprehensive wpdb-like interface for interacting with a
 * Cloudflare D1 database, using WordPress's built-in HTTP functions.
 * It includes functional equivalents for core data methods and compatibility
 * stubs for MySQL-specific features.
 *
 * @version 2.0.0
 * @link https://developers.cloudflare.com/d1/platform/client-api/
 */

// Define constants if they don't already exist
if (!defined('OBJECT'))   define('OBJECT', 'OBJECT');
if (!defined('OBJECT_K')) define('OBJECT_K', 'OBJECT_K');
if (!defined('ARRAY_A'))  define('ARRAY_A', 'ARRAY_A');
if (!defined('ARRAY_N'))  define('ARRAY_N', 'ARRAY_N');

#[AllowDynamicProperties]
class D1DB
{
    // --- Connection & API Credentials ---
    private string $api_token;
    private string $api_url;
    private bool $ready = false;

    // --- Public Properties (wpdb compatibility) ---
    public bool $show_errors = false;
    public bool $suppress_errors = false;
    public string $last_error = '';
    public int $num_queries = 0;
    public int $num_rows = 0;
    public int $rows_affected = 0;
    public int $insert_id = 0;
    public ?string $last_query = null;
    public ?array $last_result = null;
    public ?array $col_info = null;
    public array $queries = [];
    public string $prefix = '';
    public string $base_prefix = '';
    public int $blogid = 0;
    public int $siteid = 0;
    public string $charset = 'utf8';
    public string $collate = 'utf8_general_ci';

    // --- Internal Properties ---
    private ?float $time_start = null;
    private ?string $placeholder_escape_string = null;

    // #################################################################
    // ## CONSTRUCTOR & MAGIC METHODS
    // #################################################################

    /**
     * Sets up the D1DB object with connection details.
     *
     * @param string $account_id  Your Cloudflare Account ID.
     * @param string $api_token   Your Cloudflare API Token with D1 access.
     * @param string $database_id Your Cloudflare D1 Database ID.
     * @param string $prefix      Optional. The database table prefix.
     */
    public function __construct(string $account_id, string $api_token, string $database_id, string $prefix = '')
    {
        $this->api_url = "https://api.cloudflare.com/client/v4/accounts/{$account_id}/d1/database/{$database_id}/query";
        $this->api_token = $api_token;

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->show_errors();
        }

        $this->base_prefix = $prefix;
        $this->prefix = $prefix;
        $this->ready = true; // API is always "ready"
    }

    /**
     * Dynamically get a prefixed table name or other public property.
     *
     * @param string $name The base name of the table or property.
     * @return mixed The value of the property or a prefixed table name.
     */
    public function __get(string $name)
    {
        // A simplified list of tables for prefixing.
        $wp_tables = ['posts', 'comments', 'links', 'options', 'postmeta', 'terms', 'term_taxonomy', 'term_relationships', 'termmeta', 'commentmeta', 'users', 'usermeta'];
        if (in_array($name, $wp_tables)) {
            return $this->prefix . $name;
        }
        return $this->$name ?? null;
    }

    /**
     * Makes properties settable for backward compatibility.
     */
    public function __set(string $name, $value): void
    {
        $this->$name = $value;
    }

    /**
     * Makes properties check-able for backward compatibility.
     */
    public function __isset(string $name): bool
    {
        return isset($this->$name);
    }

    /**
     * Makes properties un-settable for backward compatibility.
     */
    public function __unset(string $name): void
    {
        unset($this->$name);
    }

    // #################################################################
    // ## MYSQL-SPECIFIC COMPATIBILITY STUBS
    // ## (These methods have no direct equivalent in the D1 API)
    // #################################################################

    public function init_charset(): void { /* D1 is UTF-8 by default. Stub for compatibility. */ }
    public function determine_charset($charset, $collate): array { return ['charset' => $this->charset, 'collate' => $this->collate]; }
    public function set_charset($dbh, $charset = null, $collate = null): void { /* Stub for compatibility. */ }
    public function set_sql_mode(array $modes = []): void { /* D1 is SQLite-based. Stub for compatibility. */ }
    public function select($db, $dbh = null): bool { return true; /* D1 has no concept of selecting a DB. Stub. */ }
    public function db_connect($allow_bail = true): bool { return true; /* D1 is stateless. Stub for compatibility. */ }
    public function parse_db_host($host) { return false; /* No host to parse. Stub. */ }
    public function check_connection($allow_bail = true): bool { return true; /* D1 is stateless. Stub. */ }
    public function get_table_charset($table): string { return $this->charset; /* Stub. */ }
    public function get_col_charset($table, $column): string { return $this->charset; /* Stub. */ }
    public function get_col_length($table, $column) { return false; /* Stub. */ }
    public function check_ascii($input_string): bool { return !preg_match('/[^\x00-\x7F]/', $input_string); }
    public function check_safe_collation($query): bool { return true; /* Stub. */ }
    public function strip_invalid_text($data) { return $data; /* Stub. */ }
    public function strip_invalid_text_from_query($query) { return $query; /* Stub. */ }
    public function strip_invalid_text_for_column($table, $column, $value) { return $value; /* Stub. */ }
    public function get_table_from_query($query): string|false { preg_match('/(?:from|update|into|join)\s+`?(' . $this->prefix . '\w+)`?/i', $query, $matches); return $matches[1] ?? false; }
    public function close(): bool { return true; /* D1 is stateless. Stub. */ }
    public function check_database_version() { return null; /* Stub. */ }
    public function supports_collation(): bool { return true; /* SQLite supports collation. */ }
    public function get_charset_collate(): string { return "DEFAULT CHARACTER SET {$this->charset} COLLATE {$this->collate}"; }
    public function has_cap($db_cap): bool { $db_cap = strtolower($db_cap); return in_array($db_cap, ['subqueries', 'group_concat']); }
    public function db_version(): string { return 'SQLite (via Cloudflare D1)'; }
    public function db_server_info(): string { return 'Cloudflare D1'; }
    public function get_caller(): string { return function_exists('wp_debug_backtrace_summary') ? wp_debug_backtrace_summary(__CLASS__) : ''; }
    public function process_fields($table, $data, $format) { return $this->process_field_formats($data, $format); }
    public function process_field_charsets($data, $table) { return $data; }
    public function process_field_lengths($data, $table) { return $data; }

    // #################################################################
    // ## PREFIX & TABLE MANAGEMENT
    // #################################################################

    public function set_prefix($prefix, $set_table_names = true): void
    {
        $this->base_prefix = $prefix;
        $this->prefix = $prefix;
    }

    public function set_blog_id($blog_id, $network_id = 0): void
    {
        $this->blogid = (int) $blog_id;
        $this->prefix = $this->get_blog_prefix($blog_id);
    }

    public function get_blog_prefix($blog_id = null): string
    {
        if (null === $blog_id) {
            $blog_id = $this->blogid;
        }
        if ($blog_id > 1) {
            return $this->base_prefix . $blog_id . '_';
        }
        return $this->base_prefix;
    }

    public function tables($scope = 'all', $prefix = true, $blog_id = 0): array
    {
        // This is a simplified stub for compatibility.
        $standard_tables = ['posts', 'comments', 'options', 'postmeta', 'terms', 'term_taxonomy', 'term_relationships', 'users', 'usermeta'];
        $prefixed_tables = [];
        $the_prefix = $prefix ? $this->get_blog_prefix($blog_id) : '';
        foreach ($standard_tables as $table) {
            $prefixed_tables[$table] = $the_prefix . $table;
        }
        return $prefixed_tables;
    }

    // #################################################################
    // ## ESCAPING & PREPARATION
    // #################################################################

    public function _weak_escape($data): string { _deprecated_function(__METHOD__, '3.6.0', 'wpdb::prepare() or esc_sql()'); return addslashes($data); }
    public function _real_escape($data): string { return addslashes($data); /* Best effort for non-parameterized parts. */ }
    public function _escape($data) { if(is_array($data)) { foreach($data as $k => $v) { $data[$k] = $this->_escape($v); } } else { $data = $this->_real_escape($data); } return $data; }
    public function escape($data): string { _deprecated_function(__METHOD__, '3.6.0', 'wpdb::prepare() or esc_sql()'); return $this->_weak_escape($data); }
    public function escape_by_ref(&$data): void { $data = $this->_real_escape($data); }
    public function quote_identifier($identifier): string { return '`' . $this->_escape_identifier_value($identifier) . '`'; }
    private function _escape_identifier_value($identifier): string { return str_replace('`', '``', $identifier); }
    public function esc_like($text): string { return addcslashes($text, '_%\\'); }

    public function prepare(?string $query, ...$args): ?array
    {
        if (is_null($query)) return null;
        if (isset($args[0]) && is_array($args[0])) $args = $args[0];

        $bindings = [];
        $prepared_query = preg_replace_callback('/%[sdf]/', function ($matches) use (&$args, &$bindings) {
            $value = array_shift($args);
            $bindings[] = match ($matches[0]) {
                '%d' => intval($value),
                '%f' => floatval($value),
                default => strval($value),
            };
            return '?';
        }, $query);

        $prepared_query = str_replace('%%', '%', $prepared_query);
        return ['sql' => $prepared_query, 'params' => $bindings];
    }
    
    // #################################################################
    // ## QUERY EXECUTION & LOGGING
    // #################################################################

    /**
     * A helper function to replace '?' placeholders with their values for logging.
     * This is for debugging only and does not perform real database escaping.
     *
     * @param string $sql    The SQL query with '?' placeholders.
     * @param array  $params The array of parameters.
     * @return string The interpolated query string.
     */
    private function interpolate_query(string $sql, array $params): string
    {
        $query = $sql;
        $params_copy = $params;

        $query = preg_replace_callback(
            '/\?/',
            function ($matches) use (&$params_copy) {
                if (empty($params_copy)) {
                    return '?'; // Should not happen if params match placeholders
                }
                $value = array_shift($params_copy);
                if (is_null($value)) {
                    return 'NULL';
                }
                if (is_string($value)) {
                    // Simple escape for logging. Not for execution.
                    return "'" . addslashes($value) . "'";
                }
                return $value; // int, float
            },
            $query
        );

        return $query;
    }

    public function query($query)
    {
        if (!$this->ready) return false;
        $this->flush();

        if (is_array($query) && isset($query['sql'])) {
            $payload = $query;
            $this->last_query = $this->interpolate_query($query['sql'], $query['params']);
        } else {
            $payload = ['sql' => (string) $query, 'params' => []];
            $this->last_query = (string) $query;
        }

        return $this->_do_query($payload);
    }

    private function _do_query(array $payload)
    {
        $this->timer_start();
        $this->num_queries++;
        
        $args = [
            'method'  => 'POST',
            'timeout' => 15,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_token,
            ],
            'body'    => wp_json_encode($payload),
        ];

        $response = wp_remote_post($this->api_url, $args);
        $query_time = $this->timer_stop();

        $this->log_query($this->last_query, $query_time, $this->get_caller(), $this->time_start, []);

        if (is_wp_error($response)) {
            $this->last_error = 'WP_Error: ' . $response->get_error_message();
            $this->print_error();
            return false;
        }

        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        if (!($response_data['success'] ?? false)) {
            $error_msg = $response_data['errors'][0]['message'] ?? ($response_data['result'][0]['error'] ?? 'Unknown D1 API error.');
            $this->last_error = 'D1 API Error: ' . $error_msg;
            $this->print_error();
            return false;
        }

        $result_data = $response_data['result'][0] ?? [];
        $this->last_result = $result_data['results'] ?? [];
        $meta = $result_data['meta'] ?? [];

        $this->rows_affected = $meta['changes'] ?? 0;
        $this->num_rows = $meta['rows_read'] ?? count($this->last_result);
        $this->insert_id = $meta['last_row_id'] ?? 0;
        
        $this->load_col_info();

        $query_type = strtoupper(substr(trim($this->last_query), 0, 6));

        if (in_array($query_type, ['INSERT', 'DELETE', 'UPDATE', 'REPLAC'])) return $this->rows_affected;
        if (in_array($query_type, ['CREATE', 'ALTER', 'DROP'])) return true;
        return $this->num_rows;
    }

    public function log_query($query, $query_time, $query_callstack, $query_start, $query_data): void
    {
        $this->queries[] = [$query, $query_time, $query_callstack, $query_start, $query_data];
    }

    // #################################################################
    // ## ERROR & DEBUG HANDLING
    // #################################################################

    public function print_error($str = ''): void
    {
        if (!$this->show_errors || $this->suppress_errors) return;
        $error_str = esc_html($str ?: $this->last_error);
        $query_str = esc_html($this->last_query);
        printf('<div class="notice notice-error is-dismissible" style="padding:1em;margin:1em 0;"><p><strong>D1 Database Error:</strong> %s</p><code>%s</code></div>', $error_str, $query_str);
    }
    public function show_errors($show = true): bool { $old = $this->show_errors; $this->show_errors = $show; return $old; }
    public function hide_errors(): bool { return $this->show_errors(false); }
    public function suppress_errors($suppress = true): bool { $old = $this->suppress_errors; $this->suppress_errors = (bool) $suppress; return $old; }
    public function flush(): void { $this->last_result = []; $this->last_error = ''; $this->num_rows = 0; $this->rows_affected = 0; $this->insert_id = 0; $this->col_info = null; }
    public function timer_start(): void { $this->time_start = microtime(true); }
    public function timer_stop(): float { return microtime(true) - $this->time_start; }
    public function bail($message, $error_code = '500'): void { if ($this->show_errors) wp_die($message, 'Database Error', ['response' => (int) $error_code]); }

    // #################################################################
    // ## PLACEHOLDER HELPERS (Compatibility for advanced prepare)
    // #################################################################
    
    public function placeholder_escape(): string { if (is_null($this->placeholder_escape_string)) { $this->placeholder_escape_string = '{' . wp_generate_password(64, false) . '}'; } return $this->placeholder_escape_string; }
    public function add_placeholder_escape($query): string { return str_replace('%', $this->placeholder_escape(), $query); }
    public function remove_placeholder_escape($query): string { return str_replace($this->placeholder_escape(), '%', $query); }

    // #################################################################
    // ## CRUD METHODS
    // #################################################################

    public function insert(string $table, array $data, $format = null) { return $this->_insert_replace_helper($table, $data, $format, 'INSERT'); }
    public function replace(string $table, array $data, $format = null) { return $this->_insert_replace_helper($table, $data, $format, 'REPLACE'); }

    public function _insert_replace_helper(string $table, array $data, $format = null, string $type = 'INSERT'): int|false
    {
        $this->insert_id = 0;
        if (empty($data)) return false;

        $fields = '`' . implode('`, `', array_keys($data)) . '`';
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = strtoupper($type) . " INTO `{$table}` ({$fields}) VALUES ({$placeholders})";
        
        return $this->query(['sql' => $sql, 'params' => array_values($data)]);
    }

    public function update(string $table, array $data, array $where, $format = null, $where_format = null): int|false
    {
        if (empty($data) || empty($where)) return false;
        $bindings = [];
        
        $set_clauses = [];
        foreach ($data as $field => $value) { $set_clauses[] = "`{$field}` = ?"; $bindings[] = $value; }
        
        $where_clauses = [];
        foreach ($where as $field => $value) { $where_clauses[] = "`{$field}` = ?"; $bindings[] = $value; }

        $sql = "UPDATE `{$table}` SET " . implode(', ', $set_clauses) . " WHERE " . implode(' AND ', $where_clauses);
        return $this->query(['sql' => $sql, 'params' => $bindings]);
    }

    public function delete(string $table, array $where, $where_format = null): int|false
    {
        if (empty($where)) return false;
        $bindings = [];
        $where_clauses = [];
        foreach ($where as $field => $value) { $where_clauses[] = "`{$field}` = ?"; $bindings[] = $value; }
        $sql = "DELETE FROM `{$table}` WHERE " . implode(' AND ', $where_clauses);
        return $this->query(['sql' => $sql, 'params' => $bindings]);
    }

    public function process_field_formats($data, $format): array
    {
        // Simplified stub for compatibility.
        $new_data = [];
        foreach ($data as $field => $value) {
            $new_data[$field] = [
                'value' => $value,
                'format' => is_array($format) ? (array_shift($format) ?: '%s') : ($format ?: '%s')
            ];
        }
        return $new_data;
    }

    // #################################################################
    // ## RESULT RETRIEVAL METHODS
    // #################################################################

    public function get_var($query = null, int $x = 0, int $y = 0)
    {
        if (!is_null($query)) {
            if (is_array($query) && isset($query['sql'])) {
                $this->query($query); // It's already prepared.
            } else {
                $this->query($this->prepare((string) $query)); // It's a raw string.
            }
        }

        if ($this->last_error || !isset($this->last_result[$y])) return null;
        $row = array_values($this->last_result[$y]);
        return $row[$x] ?? null;
    }

    public function get_row($query = null, string $output = OBJECT, int $y = 0)
    {
        if (!is_null($query)) {
            if (is_array($query) && isset($query['sql'])) {
                $this->query($query); // It's already prepared
            } else {
                $this->query($this->prepare((string) $query)); // It's a raw string
            }
        }

        if ($this->last_error || !isset($this->last_result[$y])) return null;
        $row = $this->last_result[$y];
        if ($output === ARRAY_A) return $row;
        if ($output === ARRAY_N) return array_values($row);
        return (object) $row;
    }

    public function get_col($query = null, int $x = 0): array
    {
        if (!is_null($query)) {
            if (is_array($query) && isset($query['sql'])) {
                $this->query($query); // It's already prepared
            } else {
                $this->query($this->prepare((string) $query)); // It's a raw string
            }
        }

        if ($this->last_error || empty($this->last_result)) return [];
        $new_array = [];
        foreach ($this->last_result as $row) {
            $values = array_values($row);
            if (isset($values[$x])) $new_array[] = $values[$x];
        }
        return $new_array;
    }

    public function get_results($query = null, string $output = OBJECT)
    {
        if (!is_null($query)) {
            if (is_array($query) && isset($query['sql'])) {
                $this->query($query); // It's already prepared
            } else {
                $this->query($this->prepare((string) $query)); // It's a raw string
            }
        }

        if ($this->last_error || is_null($this->last_result)) return null;
        
        $results = $this->last_result;
        if ($output === ARRAY_A) return $results;

        $new_array = [];
        if ($output === ARRAY_N) {
            foreach ($results as $row) $new_array[] = array_values($row);
            return $new_array;
        }

        foreach ($results as $row) {
            $obj = (object) $row;
            if ($output === OBJECT_K) {
                $key = current($row);
                $new_array[$key] = $obj;
            } else {
                $new_array[] = $obj;
            }
        }
        return $new_array;
    }

    // #################################################################
    // ## METADATA METHODS
    // #################################################################

    protected function load_col_info(): void
    {
        if ($this->col_info || empty($this->last_result)) return;
        
        $this->col_info = [];
        $first_row = $this->last_result[0];
        $i = 0;
        foreach ($first_row as $col_name => $value) {
            $this->col_info[$i] = (object) [
                'name' => $col_name,
                'table' => '', // D1 API doesn't provide this
                'def' => '',
                'max_length' => -1,
                'not_null' => 0,
                'primary_key' => 0,
                'multiple_key' => 0,
                'unique_key' => 0,
                'numeric' => is_numeric($value) ? 1 : 0,
                'blob' => 0,
                'type' => gettype($value),
                'unsigned' => 0,
                'zerofill' => 0,
            ];
            $i++;
        }
    }

    public function get_col_info($info_type = 'name', $col_offset = -1)
    {
        $this->load_col_info();
        if (is_null($this->col_info)) return null;

        if ($col_offset === -1) {
            $results = [];
            foreach ($this->col_info as $col) {
                $results[] = $col->{$info_type} ?? null;
            }
            return $results;
        }
        return $this->col_info[$col_offset]->{$info_type} ?? null;
    }
}
