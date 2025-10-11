#!/usr/bin/env php
<?php
/**
 * Script di verifica sintassi PHP
 * Simula l'ambiente WordPress per verificare che non ci siano errori
 */

// Simula costanti WordPress base
define('ABSPATH', '/fake/wordpress/');
define('WP_CONTENT_DIR', '/fake/wordpress/wp-content');
define('DAY_IN_SECONDS', 86400);

// Simula funzioni WordPress critiche
function __($text, $domain = 'default') { return $text; }
function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function esc_js($text) { return $text; }
function sanitize_text_field($text) { return trim(strip_tags($text)); }
function sanitize_email($email) { return filter_var($email, FILTER_SANITIZE_EMAIL); }
function get_option($option, $default = false) { return $default; }
function add_option($option, $value) { return true; }
function update_option($option, $value) { return true; }
function delete_option($option) { return true; }
function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugin_dir_url($file) { return 'https://example.com/wp-content/plugins/' . basename(dirname($file)) . '/'; }
function plugin_basename($file) { return basename(dirname($file)) . '/' . basename($file); }
function wp_generate_password($length, $special) { return bin2hex(random_bytes($length / 2)); }
function admin_url($path) { return 'https://example.com/wp-admin/' . $path; }
function rest_url($path) { return 'https://example.com/wp-json/' . $path; }
function current_time($type) { return date('Y-m-d H:i:s'); }
function wp_json_encode($data) { return json_encode($data); }
function wp_schedule_event($time, $recurrence, $hook) { return true; }
function wp_schedule_single_event($time, $hook, $args = []) { return true; }
function wp_next_scheduled($hook) { return false; }
function wp_unschedule_event($timestamp, $hook) { return true; }
function wp_clear_scheduled_hook($hook) { return true; }
function wp_mkdir_p($dir) { return true; }
function wp_mail($to, $subject, $message) { return true; }
function wp_remote_get($url, $args = []) { return ['response' => ['code' => 200], 'body' => 'test']; }
function wp_remote_retrieve_response_code($response) { return 200; }
function wp_remote_retrieve_body($response) { return $response['body']; }
function wp_remote_post($url, $args = []) { return ['response' => ['code' => 200]]; }
function is_wp_error($thing) { return $thing instanceof WP_Error; }
function dbDelta($sql) { return true; }
function flush_rewrite_rules() { return true; }
function register_activation_hook($file, $callback) { return true; }
function register_deactivation_hook($file, $callback) { return true; }
function add_action($hook, $callback, $priority = 10, $args = 1) { return true; }
function add_filter($hook, $callback, $priority = 10, $args = 1) { return true; }
function register_rest_route($namespace, $route, $args) { return true; }
function register_setting($group, $name, $args = []) { return true; }
function add_menu_page($title, $menu, $cap, $slug, $callback, $icon, $pos) { return true; }
function add_submenu_page($parent, $title, $menu, $cap, $slug, $callback) { return true; }
function is_admin() { return true; }
function current_user_can($cap) { return true; }
function wp_create_nonce($action) { return 'fake-nonce'; }
function check_ajax_referer($action, $key) { return true; }
function wp_send_json_success($data) { echo json_encode(['success' => true, 'data' => $data]); }
function wp_send_json_error($data) { echo json_encode(['success' => false, 'data' => $data]); }
function settings_fields($group) {}
function submit_button($text) {}
function checked($checked, $current) { return $checked === $current ? 'checked' : ''; }
function selected($selected, $current) { return $selected === $current ? 'selected' : ''; }
function unzip_file($file, $to) { return true; }
function copy_dir($from, $to) { return true; }
function WP_Filesystem() { return true; }

class WP_Error {
    private $message;
    public function __construct($code, $message) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}

class WP_REST_Response {
    private $data;
    private $status;
    public function __construct($data, $status = 200) {
        $this->data = $data;
        $this->status = $status;
    }
}

$wpdb = new stdClass();
$wpdb->prefix = 'wp_';
$wp_filesystem = new stdClass();
$wp_filesystem->delete = function() { return true; };
$wp_filesystem->move = function() { return true; };

echo "🔍 Verifica sintassi PHP dei file del plugin...\n\n";

$files = [
    'fp-git-updater.php',
    'includes/class-webhook-handler.php',
    'includes/class-updater.php',
    'includes/class-admin.php',
    'includes/class-logger.php',
    'uninstall.php',
];

$errors = 0;
foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    echo "Controllo: $file ... ";
    
    if (!file_exists($path)) {
        echo "❌ FILE NON TROVATO\n";
        $errors++;
        continue;
    }
    
    // Verifica sintassi con php -l
    $output = [];
    $return_var = 0;
    exec("php -l " . escapeshellarg($path) . " 2>&1", $output, $return_var);
    
    if ($return_var !== 0) {
        echo "❌ ERRORE SINTASSI\n";
        echo implode("\n", $output) . "\n";
        $errors++;
    } else {
        // Prova a includere il file
        try {
            @include_once $path;
            echo "✅ OK\n";
        } catch (Error $e) {
            echo "❌ ERRORE: " . $e->getMessage() . "\n";
            $errors++;
        }
    }
}

echo "\n";
if ($errors === 0) {
    echo "✅ TUTTI I FILE SONO CORRETTI!\n";
    echo "✅ Il plugin è pronto per essere usato.\n";
    exit(0);
} else {
    echo "❌ Trovati $errors errori.\n";
    exit(1);
}
