<?php
/**
 * Plugin Name: FP Updater
 * Plugin URI: https://www.francescopasseri.com
 * Description: Gestione sicura degli aggiornamenti dei plugin da GitHub. Supporta sia aggiornamenti automatici che manuali tramite webhook, proteggendo i tuoi siti da aggiornamenti problematici.
 * Version: 1.4.1
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

// Guard: blocca caricamento se il plugin è in path annidato (upload errato che crea duplicati)
$self_basename = plugin_basename(__FILE__);
if (substr_count($self_basename, '/') > 1) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>FP Updater:</strong> Installazione errata rilevata (cartelle annidate). Elimina questo plugin dalla lista e reinstalla correttamente. Vedi istruzioni in FP Updater.</p></div>';
    });
    return;
}

// Definisci costanti del plugin
define('FP_GIT_UPDATER_VERSION', '1.4.1');
define('FP_GIT_UPDATER_PLUGIN_DIR', dirname(__FILE__) . '/');
define('FP_GIT_UPDATER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FP_GIT_UPDATER_PLUGIN_FILE', __FILE__);
define('FP_GIT_UPDATER_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Carica Composer autoload (Best Practice PSR-4)
if (file_exists(FP_GIT_UPDATER_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once FP_GIT_UPDATER_PLUGIN_DIR . 'vendor/autoload.php';
}

// Usa i namespace delle classi
use FP\GitUpdater\Admin;
use FP\GitUpdater\Logger;

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
        // Hooks essenziali
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Inizializza l'auto-aggiornamento del plugin stesso (sempre, non solo in admin)
        add_action('plugins_loaded', array($this, 'init_self_update'), 5);

        // REST API: webhook e endpoint Master (sempre caricati per richieste esterne)
        add_action('rest_api_init', array($this, 'register_rest_endpoints'));

        // Updater sempre caricato: necessario per registrare l'handler del cron
        // (wp-cron gira su richieste frontend/cron, non solo admin)
        add_action('plugins_loaded', array($this, 'load_updater'), 10);
        
        // Carica l'admin subito se siamo nell'admin (prima di admin_menu!)
        if (is_admin()) {
            $this->load_admin();
            if (class_exists('\FP\GitUpdater\Migration')) {
                \FP\GitUpdater\Migration::get_instance();
            }
        }
    }
    
    /**
     * Registra gli endpoint REST (webhook GitHub + API Master per client Bridge)
     */
    public function register_rest_endpoints() {
        \FP\GitUpdater\WebhookHandler::get_instance();
        if (class_exists('\FP\GitUpdater\MasterEndpoint')) {
            \FP\GitUpdater\MasterEndpoint::register();
        }
        if (class_exists('\FP\GitUpdater\ReceiveBackupEndpoint')) {
            \FP\GitUpdater\ReceiveBackupEndpoint::register();
        }
    }

    /**
     * Carica l'Updater su ogni richiesta (necessario per handler cron su frontend/cron)
     */
    public function load_updater() {
        if (class_exists('\FP\GitUpdater\Updater')) {
            \FP\GitUpdater\Updater::get_instance();
        }
    }

    /**
     * Carica l'admin (PSR-4 autoload via Composer)
     */
    private function load_admin() {
        // Le classi vengono caricate automaticamente tramite Composer autoload (PSR-4)
        // Inizializza l'admin se la classe esiste
        if (class_exists('\FP\GitUpdater\Admin')) {
            Admin::get_instance();
        }
    }
    
    /**
     * Attivazione plugin
     */
    public function activate() {
        // Attivazione ultra-sicura senza dipendenze
        
        // Crea la tabella per i log
        $this->create_log_table();
        
        // Crea solo le opzioni essenziali
        $default_settings = array(
            'github_token' => '',
            'notification_email' => get_option('admin_email'),
            'auto_update' => false,
            'webhook_secret' => wp_generate_password(32, false),
            'plugins' => array(),
            'max_backups' => 5, // Numero massimo di backup da mantenere
            'max_backup_age_days' => 7 // Età massima backup in giorni
        );
        
        $existing_settings = get_option('fp_git_updater_settings', array());
        $settings = wp_parse_args($existing_settings, $default_settings);
        update_option('fp_git_updater_settings', $settings);

        // Fallback: schedula il cron se non già presente (Updater potrebbe non essere caricato)
        $interval = isset($settings['update_check_interval']) ? $settings['update_check_interval'] : 'hourly';
        if (!wp_next_scheduled('fp_git_updater_check_update')) {
            wp_schedule_event(time() + 60, $interval, 'fp_git_updater_check_update');
        }
    }
    
    /**
     * Disattivazione plugin
     */
    public function deactivate() {
        // Pulisci tutti i cron job del plugin
        wp_clear_scheduled_hook('fp_git_updater_check_update');
        wp_clear_scheduled_hook('fp_git_updater_cleanup_old_logs');
        wp_clear_scheduled_hook('fp_git_updater_cleanup_old_backups');
        
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
     * Inizializza l'auto-aggiornamento del plugin stesso
     * Rimuove il plugin stesso dalla lista dei plugin gestiti (gestito separatamente in alto)
     * Pubblico per essere chiamato dall'hook plugins_loaded
     */
    public function init_self_update() {
        // Rimuovi il plugin stesso dalla lista dei plugin gestiti (gestito nella sezione dedicata in alto)
        $settings = get_option('fp_git_updater_settings', array());
        $plugins = isset($settings['plugins']) ? $settings['plugins'] : array();
        $original_count = count($plugins);
        
        // Filtra rimuovendo il plugin self-update dalla lista
        $plugins = array_filter($plugins, function($plugin) {
            // Rimuovi se ha l'ID del self-update
            if (isset($plugin['id']) && $plugin['id'] === 'fp_git_updater_self') {
                return false;
            }
            // Rimuovi anche se ha lo slug del self-update
            if (isset($plugin['plugin_slug']) && $plugin['plugin_slug'] === 'fp-git-updater') {
                return false;
            }
            return true;
        });
        
        // Re-indicizza l'array dopo il filtro
        $plugins = array_values($plugins);
        
        // Se sono stati rimossi elementi, salva le impostazioni
        if (count($plugins) !== $original_count) {
            $settings['plugins'] = $plugins;
            update_option('fp_git_updater_settings', $settings);
            
            if (class_exists('\FP\GitUpdater\Logger')) {
                Logger::log('info', 'Plugin FP Updater rimosso dalla lista gestiti (gestito nella sezione dedicata)');
            }
        }
    }
    
}

/**
 * Inizializza il plugin (versione sicura)
 */
function fp_git_updater_init() {
    // Verifica solo ABSPATH (wp_get_current_user non è disponibile a questo punto)
    if (!defined('ABSPATH')) {
        return false;
    }
    
    try {
        return FP_Git_Updater::get_instance();
    } catch (Exception $e) {
        error_log("[FP-GIT-UPDATER] Errore fatale durante l'inizializzazione: " . $e->getMessage());
        return false;
    }
}

// Avvia il plugin SUBITO (senza hook) per supportare AJAX handlers
// Gli AJAX handlers devono essere registrati PRIMA di admin-ajax.php
fp_git_updater_init();
