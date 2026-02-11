<?php
/**
 * Gestione Backup e Ripristino Impostazioni
 * 
 * Gestisce il backup automatico e il ripristino delle impostazioni del plugin
 * 
 * @package FP\GitUpdater
 */

namespace FP\GitUpdater;

if (!defined('ABSPATH')) {
    exit;
}

class SettingsBackup {
    
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
        
        // Hook per pulizia automatica dei duplicati all'avvio
        add_action('plugins_loaded', array($this, 'cleanup_duplicate_plugins'), 1);
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
        
        Logger::log('info', 'Backup impostazioni creato con successo', array(
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
                Logger::log('error', 'Backup non trovato nell\'indice specificato');
                return false;
            }
            $backup_data = $history[$backup_index];
        } else {
            // Ripristina dall'ultimo backup
            $backup_data = get_option($this->backup_option_key);
        }
        
        if (empty($backup_data) || !isset($backup_data['settings'])) {
            Logger::log('error', 'Nessun backup disponibile da ripristinare');
            return false;
        }
        
        // Ripristina le impostazioni
        update_option('fp_git_updater_settings', $backup_data['settings']);
        
        Logger::log('success', 'Impostazioni ripristinate con successo dal backup', array(
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
        
        // NON ripristinare automaticamente se stiamo ricaricando manualmente da zip
        // Questo previene duplicazioni quando il plugin viene ricaricato manualmente
        $settings = get_option('fp_git_updater_settings');
        if (!empty($settings) && !empty($settings['plugins'])) {
            // Se abbiamo già delle impostazioni valide, non ripristinare
            Logger::log('info', 'Attivazione rilevata ma impostazioni già presenti, skip ripristino automatico per evitare duplicazioni');
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
            Logger::log('warning', 'Rilevato reset delle impostazioni, ripristino automatico dal backup...');
            
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
            <p><strong>FP Updater:</strong> Le tue impostazioni sono state ripristinate automaticamente dal backup dopo l'aggiornamento.</p>
        </div>
        <?php
    }
    
    /**
     * Mostra notifica di ripristino fallito
     */
    public function show_restore_failed_notice() {
        ?>
        <div class="notice notice-error">
            <p><strong>FP Updater:</strong> Non è stato possibile ripristinare le impostazioni automaticamente. Vai su <a href="<?php echo admin_url('admin.php?page=fp-git-updater-backup'); ?>">Backup e Ripristino</a> per un ripristino manuale.</p>
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
            
            Logger::log('info', 'Backup eliminato dalla cronologia');
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
        Logger::log('info', 'Tutti i backup sono stati eliminati');
        return true;
    }
    
    /**
     * Rimuove automaticamente i plugin duplicati dalle impostazioni
     * Eseguito all'avvio del plugin per prevenire problemi
     */
    public function cleanup_duplicate_plugins() {
        $settings = get_option('fp_git_updater_settings');
        
        if (empty($settings) || empty($settings['plugins'])) {
            return;
        }
        
        $plugins = $settings['plugins'];
        $cleaned_plugins = array();
        $seen_repos = array();
        $duplicates_found = false;
        
        foreach ($plugins as $plugin) {
            if (empty($plugin['github_repo'])) {
                continue;
            }
            
            $github_repo = $plugin['github_repo'];
            $branch = isset($plugin['branch']) ? $plugin['branch'] : 'main';
            
            // Crea una chiave unica basata su repo e branch
            $repo_key = strtolower($github_repo . ':' . $branch);
            
            // Se questo repository+branch è già stato visto, salta (duplicato)
            if (isset($seen_repos[$repo_key])) {
                $duplicates_found = true;
                Logger::log('warning', 'Plugin duplicato rimosso automaticamente: ' . $github_repo . ' (branch: ' . $branch . ')');
                continue;
            }
            
            // Segna questo repository come visto
            $seen_repos[$repo_key] = true;
            $cleaned_plugins[] = $plugin;
        }
        
        // Se sono stati trovati duplicati, aggiorna le impostazioni
        if ($duplicates_found) {
            $settings['plugins'] = $cleaned_plugins;
            update_option('fp_git_updater_settings', $settings);
            Logger::log('success', 'Pulizia automatica completata: ' . (count($plugins) - count($cleaned_plugins)) . ' plugin duplicati rimossi');
        }
    }
}



