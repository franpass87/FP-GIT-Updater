<?php
/**
 * Gestione Backup e Ripristino Impostazioni
 * 
 * Gestisce il backup automatico e il ripristino delle impostazioni del plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class FP_Git_Updater_Settings_Backup {
    
    private static $instance = null;
    private $backup_option_key = 'fp_git_updater_settings_backup';
    private $backup_history_key = 'fp_git_updater_settings_backup_history';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Hook per salvare backup prima di aggiornamenti
        add_action('upgrader_process_complete', array($this, 'auto_backup_on_update'), 10, 2);
        
        // Hook per ripristino automatico dopo attivazione
        add_action('activated_plugin', array($this, 'auto_restore_on_activation'), 10, 2);
    }
    
    /**
     * Crea un backup delle impostazioni correnti
     */
    public function create_backup($manual = false) {
        $settings = get_option('fp_git_updater_settings');
        
        if (empty($settings)) {
            return false;
        }
        
        $backup_data = array(
            'timestamp' => current_time('mysql'),
            'settings' => $settings,
            'version' => FP_GIT_UPDATER_VERSION,
            'manual' => $manual
        );
        
        // Salva il backup corrente
        update_option($this->backup_option_key, $backup_data, false);
        
        // Aggiungi alla cronologia (mantieni ultimi 10 backup)
        $history = get_option($this->backup_history_key, array());
        array_unshift($history, $backup_data);
        $history = array_slice($history, 0, 10); // Mantieni solo i 10 più recenti
        update_option($this->backup_history_key, $history, false);
        
        FP_Git_Updater_Logger::log('info', 'Backup impostazioni creato con successo', array(
            'plugins_count' => count($settings['plugins'] ?? array()),
            'manual' => $manual
        ));
        
        return true;
    }
    
    /**
     * Ripristina le impostazioni dall'ultimo backup
     */
    public function restore_backup($backup_index = null) {
        if ($backup_index !== null) {
            // Ripristina da un backup specifico nella cronologia
            $history = get_option($this->backup_history_key, array());
            if (!isset($history[$backup_index])) {
                FP_Git_Updater_Logger::log('error', 'Backup non trovato nell\'indice specificato');
                return false;
            }
            $backup_data = $history[$backup_index];
        } else {
            // Ripristina dall'ultimo backup
            $backup_data = get_option($this->backup_option_key);
        }
        
        if (empty($backup_data) || !isset($backup_data['settings'])) {
            FP_Git_Updater_Logger::log('error', 'Nessun backup disponibile da ripristinare');
            return false;
        }
        
        // Ripristina le impostazioni
        update_option('fp_git_updater_settings', $backup_data['settings']);
        
        FP_Git_Updater_Logger::log('success', 'Impostazioni ripristinate con successo dal backup', array(
            'backup_date' => $backup_data['timestamp'],
            'backup_version' => $backup_data['version'] ?? 'unknown',
            'plugins_count' => count($backup_data['settings']['plugins'] ?? array())
        ));
        
        return true;
    }
    
    /**
     * Ottieni l'ultimo backup disponibile
     */
    public function get_latest_backup() {
        return get_option($this->backup_option_key);
    }
    
    /**
     * Ottieni la cronologia dei backup
     */
    public function get_backup_history() {
        return get_option($this->backup_history_key, array());
    }
    
    /**
     * Verifica se le impostazioni sono state resettate
     */
    public function check_if_settings_reset() {
        $settings = get_option('fp_git_updater_settings');
        
        // Se le impostazioni sono vuote o non hanno plugin configurati
        if (empty($settings) || empty($settings['plugins'])) {
            $backup = $this->get_latest_backup();
            
            // Ma c'è un backup con plugin configurati
            if (!empty($backup) && !empty($backup['settings']['plugins'])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Backup automatico prima di aggiornamenti del plugin
     */
    public function auto_backup_on_update($upgrader_object, $options) {
        // Verifica se è un aggiornamento di plugin
        if ($options['action'] !== 'update' || $options['type'] !== 'plugin') {
            return;
        }
        
        // Verifica se è il nostro plugin
        $plugins = isset($options['plugins']) ? $options['plugins'] : array();
        if (in_array(FP_GIT_UPDATER_PLUGIN_BASENAME, $plugins)) {
            $this->create_backup(false);
        }
    }
    
    /**
     * Ripristino automatico dopo attivazione se le impostazioni sono state resettate
     */
    public function auto_restore_on_activation($plugin, $network_wide) {
        // Verifica se è il nostro plugin
        if ($plugin !== FP_GIT_UPDATER_PLUGIN_BASENAME) {
            return;
        }
        
        // Aspetta un momento per permettere al plugin di inizializzarsi
        add_action('init', array($this, 'delayed_auto_restore'));
    }
    
    /**
     * Ripristino ritardato (dopo che il plugin è stato completamente inizializzato)
     */
    public function delayed_auto_restore() {
        if ($this->check_if_settings_reset()) {
            FP_Git_Updater_Logger::log('warning', 'Rilevato reset delle impostazioni, ripristino automatico dal backup...');
            
            if ($this->restore_backup()) {
                // Aggiungi una notifica admin
                add_action('admin_notices', array($this, 'show_restore_notice'));
            } else {
                add_action('admin_notices', array($this, 'show_restore_failed_notice'));
            }
        }
    }
    
    /**
     * Mostra notifica di ripristino avvenuto
     */
    public function show_restore_notice() {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><strong>FP Git Updater:</strong> Le tue impostazioni sono state ripristinate automaticamente dal backup dopo l'aggiornamento.</p>
        </div>
        <?php
    }
    
    /**
     * Mostra notifica di ripristino fallito
     */
    public function show_restore_failed_notice() {
        ?>
        <div class="notice notice-error">
            <p><strong>FP Git Updater:</strong> Non è stato possibile ripristinare le impostazioni automaticamente. Vai su <a href="<?php echo admin_url('admin.php?page=fp-git-updater-backup'); ?>">Backup e Ripristino</a> per un ripristino manuale.</p>
        </div>
        <?php
    }
    
    /**
     * Elimina un backup specifico dalla cronologia
     */
    public function delete_backup($index) {
        $history = get_option($this->backup_history_key, array());
        
        if (isset($history[$index])) {
            unset($history[$index]);
            $history = array_values($history); // Reindicizza l'array
            update_option($this->backup_history_key, $history, false);
            
            FP_Git_Updater_Logger::log('info', 'Backup eliminato dalla cronologia');
            return true;
        }
        
        return false;
    }
    
    /**
     * Pulisci tutti i backup
     */
    public function clear_all_backups() {
        delete_option($this->backup_option_key);
        delete_option($this->backup_history_key);
        FP_Git_Updater_Logger::log('info', 'Tutti i backup sono stati eliminati');
        return true;
    }
}
