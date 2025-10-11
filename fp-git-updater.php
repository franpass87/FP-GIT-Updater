<?php
/**
 * Plugin Name: FP Git Updater
 * Plugin URI: https://github.com/tuousername/fp-git-updater
 * Description: Plugin personalizzato per aggiornamento automatico da GitHub tramite webhook. Si aggiorna automaticamente quando fai merge/push sul repository.
 * Version: 1.0.0
 * Author: Il Tuo Nome
 * Author URI: https://tuosito.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fp-git-updater
 * Domain Path: /languages
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Definisci costanti del plugin
define('FP_GIT_UPDATER_VERSION', '1.0.0');
define('FP_GIT_UPDATER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FP_GIT_UPDATER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FP_GIT_UPDATER_PLUGIN_FILE', __FILE__);
define('FP_GIT_UPDATER_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Classe principale del plugin
 */
class FP_Git_Updater {
    
    private static $instance = null;
    
    /**
     * Singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Carica le dipendenze
     */
    private function load_dependencies() {
        require_once FP_GIT_UPDATER_PLUGIN_DIR . 'includes/class-webhook-handler.php';
        require_once FP_GIT_UPDATER_PLUGIN_DIR . 'includes/class-updater.php';
        require_once FP_GIT_UPDATER_PLUGIN_DIR . 'includes/class-admin.php';
        require_once FP_GIT_UPDATER_PLUGIN_DIR . 'includes/class-logger.php';
    }
    
    /**
     * Inizializza gli hooks
     */
    private function init_hooks() {
        // Attivazione e disattivazione plugin
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Inizializza i componenti
        add_action('plugins_loaded', array($this, 'init_components'));
        
        // Aggiungi link alle impostazioni nella pagina dei plugin
        add_filter('plugin_action_links_' . FP_GIT_UPDATER_PLUGIN_BASENAME, array($this, 'add_action_links'));
    }
    
    /**
     * Inizializza i componenti del plugin
     */
    public function init_components() {
        FP_Git_Updater_Webhook_Handler::get_instance();
        FP_Git_Updater_Updater::get_instance();
        
        if (is_admin()) {
            FP_Git_Updater_Admin::get_instance();
        }
    }
    
    /**
     * Attivazione plugin
     */
    public function activate() {
        // Crea le opzioni di default
        $default_options = array(
            'github_repo' => '',
            'github_token' => '',
            'webhook_secret' => wp_generate_password(32, false),
            'auto_update' => true,
            'branch' => 'main',
            'update_check_interval' => 'hourly',
            'enable_notifications' => true,
            'notification_email' => get_option('admin_email'),
        );
        
        add_option('fp_git_updater_settings', $default_options);
        
        // Crea la tabella per i log
        $this->create_log_table();
        
        // Pulisci i rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Disattivazione plugin
     */
    public function deactivate() {
        // Pulisci i cron job
        $timestamp = wp_next_scheduled('fp_git_updater_check_update');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'fp_git_updater_check_update');
        }
        
        flush_rewrite_rules();
    }
    
    /**
     * Crea la tabella per i log
     */
    private function create_log_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_git_updater_logs';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            log_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            log_type varchar(50) NOT NULL,
            message text NOT NULL,
            details longtext,
            PRIMARY KEY  (id),
            KEY log_date (log_date),
            KEY log_type (log_type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Aggiungi link alle impostazioni
     */
    public function add_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=fp-git-updater') . '">' . __('Impostazioni', 'fp-git-updater') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

/**
 * Inizializza il plugin
 */
function fp_git_updater_init() {
    return FP_Git_Updater::get_instance();
}

// Avvia il plugin
fp_git_updater_init();
