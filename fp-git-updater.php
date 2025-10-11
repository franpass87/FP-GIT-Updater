<?php
/**
 * Plugin Name: FP Git Updater
 * Plugin URI: https://www.francescopasseri.com
 * Description: Gestione sicura degli aggiornamenti dei plugin da GitHub. Supporta sia aggiornamenti automatici che manuali tramite webhook, proteggendo i tuoi siti da aggiornamenti problematici.
 * Version: 1.1.0
 * Author: Francesco Passeri
 * Author URI: https://www.francescopasseri.com
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
define('FP_GIT_UPDATER_VERSION', '1.1.0');
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
        require_once FP_GIT_UPDATER_PLUGIN_DIR . 'includes/class-settings-backup.php';
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
        FP_Git_Updater_Settings_Backup::get_instance();
        
        if (is_admin()) {
            FP_Git_Updater_Admin::get_instance();
        }
    }
    
    /**
     * Attivazione plugin
     */
    public function activate() {
        // Verifica se c'è un backup da ripristinare (dopo un aggiornamento)
        require_once FP_GIT_UPDATER_PLUGIN_DIR . 'includes/class-settings-backup.php';
        $backup_manager = FP_Git_Updater_Settings_Backup::get_instance();
        
        $existing_settings = get_option('fp_git_updater_settings');
        $has_backup = $backup_manager->get_latest_backup();
        
        // Se le impostazioni sono vuote ma c'è un backup, ripristinalo
        if ((empty($existing_settings) || empty($existing_settings['plugins'])) && !empty($has_backup)) {
            require_once FP_GIT_UPDATER_PLUGIN_DIR . 'includes/class-logger.php';
            FP_Git_Updater_Logger::log('info', 'Ripristino impostazioni dal backup durante attivazione...');
            $backup_manager->restore_backup();
            $existing_settings = get_option('fp_git_updater_settings');
        }
        
        // Crea le opzioni di default
        $default_options = array(
            'plugins' => array(), // Lista di plugin da gestire
            'webhook_secret' => wp_generate_password(32, false),
            'auto_update' => false, // Default a false per sicurezza
            'update_check_interval' => 'hourly',
            'enable_notifications' => true,
            'notification_email' => get_option('admin_email'),
        );
        
        // Se esiste già una configurazione, migra i dati vecchi
        if ($existing_settings && isset($existing_settings['github_repo']) && !empty($existing_settings['github_repo'])) {
            // Migra da configurazione singola a lista
            $default_options['plugins'][] = array(
                'id' => uniqid('plugin_'),
                'name' => 'FP Git Updater',
                'github_repo' => $existing_settings['github_repo'],
                'plugin_slug' => 'fp-git-updater',
                'branch' => isset($existing_settings['branch']) ? $existing_settings['branch'] : 'main',
                'github_token' => isset($existing_settings['github_token']) ? $existing_settings['github_token'] : '',
                'enabled' => true,
            );
            // Mantieni le altre impostazioni (preserva auto_update se esiste)
            $default_options['webhook_secret'] = isset($existing_settings['webhook_secret']) ? $existing_settings['webhook_secret'] : $default_options['webhook_secret'];
            $default_options['auto_update'] = isset($existing_settings['auto_update']) ? $existing_settings['auto_update'] : false;
            $default_options['update_check_interval'] = isset($existing_settings['update_check_interval']) ? $existing_settings['update_check_interval'] : 'hourly';
            $default_options['enable_notifications'] = isset($existing_settings['enable_notifications']) ? $existing_settings['enable_notifications'] : true;
            $default_options['notification_email'] = isset($existing_settings['notification_email']) ? $existing_settings['notification_email'] : get_option('admin_email');
            
            update_option('fp_git_updater_settings', $default_options);
        } elseif (!$existing_settings || empty($existing_settings['plugins'])) {
            // Solo se non ci sono impostazioni esistenti E non sono state ripristinate dal backup
            add_option('fp_git_updater_settings', $default_options);
        }
        
        // Crea la tabella per i log
        $this->create_log_table();
        
        // Crea un backup delle impostazioni correnti
        if (!empty($existing_settings) && !empty($existing_settings['plugins'])) {
            $backup_manager->create_backup(false);
        }
        
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
