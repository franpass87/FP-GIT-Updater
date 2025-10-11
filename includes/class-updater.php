<?php
/**
 * Gestione Aggiornamenti del Plugin
 * 
 * Scarica e installa gli aggiornamenti dal repository GitHub
 */

if (!defined('ABSPATH')) {
    exit;
}

class FP_Git_Updater_Updater {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Hook per l'aggiornamento schedulato
        add_action('fp_git_updater_run_update', array($this, 'run_update'), 10, 2);
        add_action('fp_git_updater_check_update', array($this, 'check_for_updates'));
        add_action('fp_git_updater_cleanup_backup', array($this, 'cleanup_backup'));
        add_action('fp_git_updater_cleanup_old_logs', array('FP_Git_Updater_Logger', 'clear_old_logs'));
        
        // Schedula controlli periodici se abilitati
        $this->schedule_update_checks();
    }
    
    /**
     * Schedula i controlli periodici per aggiornamenti
     */
    private function schedule_update_checks() {
        $settings = get_option('fp_git_updater_settings');
        $interval = isset($settings['update_check_interval']) ? $settings['update_check_interval'] : 'hourly';
        
        if (!wp_next_scheduled('fp_git_updater_check_update')) {
            wp_schedule_event(time(), $interval, 'fp_git_updater_check_update');
        }
    }
    
    /**
     * Controlla se ci sono aggiornamenti disponibili per tutti i plugin
     */
    public function check_for_updates() {
        $settings = get_option('fp_git_updater_settings');
        $plugins = isset($settings['plugins']) ? $settings['plugins'] : array();
        
        if (empty($plugins)) {
            return false;
        }
        
        FP_Git_Updater_Logger::log('info', 'Controllo aggiornamenti per tutti i plugin...');
        
        $updates_available = false;
        
        foreach ($plugins as $plugin) {
            if (!isset($plugin['enabled']) || !$plugin['enabled']) {
                continue;
            }
            
            $result = $this->check_plugin_for_updates($plugin);
            if ($result) {
                $updates_available = true;
            }
        }
        
        return $updates_available;
    }
    
    /**
     * Controlla aggiornamenti per un plugin specifico dato il suo ID
     */
    public function check_plugin_update_by_id($plugin_id) {
        $plugin = $this->get_plugin_by_id($plugin_id);
        
        if (!$plugin) {
            return new WP_Error('plugin_not_found', 'Plugin non trovato');
        }
        
        if (!isset($plugin['enabled']) || !$plugin['enabled']) {
            return new WP_Error('plugin_disabled', 'Plugin disabilitato');
        }
        
        return $this->check_plugin_for_updates($plugin);
    }
    
    /**
     * Esegue l'aggiornamento per un plugin specifico dato il suo ID
     */
    public function run_update_by_id($plugin_id) {
        $plugin = $this->get_plugin_by_id($plugin_id);
        
        if (!$plugin) {
            return new WP_Error('plugin_not_found', 'Plugin non trovato');
        }
        
        if (!isset($plugin['enabled']) || !$plugin['enabled']) {
            return new WP_Error('plugin_disabled', 'Plugin disabilitato');
        }
        
        // Ottieni il commit dal pending update se esiste
        $pending = get_option('fp_git_updater_pending_update_' . $plugin_id);
        $commit_sha = $pending && isset($pending['commit_sha']) ? $pending['commit_sha'] : null;
        
        $result = $this->run_plugin_update($commit_sha, $plugin);
        
        // Se l'aggiornamento ha successo, rimuovi il pending update
        if ($result && !is_wp_error($result)) {
            delete_option('fp_git_updater_pending_update_' . $plugin_id);
        }
        
        return $result;
    }
    
    /**
     * Ottiene tutti gli aggiornamenti pending
     */
    public function get_pending_updates() {
        $settings = get_option('fp_git_updater_settings');
        $plugins = isset($settings['plugins']) ? $settings['plugins'] : array();
        $pending_updates = array();
        
        foreach ($plugins as $plugin) {
            $pending = get_option('fp_git_updater_pending_update_' . $plugin['id']);
            if ($pending) {
                $pending['plugin'] = $plugin;
                $pending_updates[] = $pending;
            }
        }
        
        return $pending_updates;
    }
    
    /**
     * Rimuove un pending update
     */
    public function clear_pending_update($plugin_id) {
        return delete_option('fp_git_updater_pending_update_' . $plugin_id);
    }
    
    /**
     * Ottiene un plugin dalla configurazione dato il suo ID
     */
    private function get_plugin_by_id($plugin_id) {
        $settings = get_option('fp_git_updater_settings');
        $plugins = isset($settings['plugins']) ? $settings['plugins'] : array();
        
        // Sanitizza l'ID in input per evitare problemi di confronto
        $plugin_id = trim($plugin_id);
        
        foreach ($plugins as $plugin) {
            // Verifica che l'ID esista e confronta con trim per evitare problemi di spazi
            if (isset($plugin['id']) && trim($plugin['id']) === $plugin_id) {
                return $plugin;
            }
        }
        
        // Log per debug se il plugin non viene trovato
        FP_Git_Updater_Logger::log('error', 'Plugin non trovato con ID: ' . $plugin_id, array(
            'plugin_id' => $plugin_id,
            'available_plugins' => array_map(function($p) {
                return isset($p['id']) ? $p['id'] : 'NO_ID';
            }, $plugins)
        ));
        
        return null;
    }
    
    /**
     * Trova la directory root del plugin nella struttura estratta
     * Cerca il file principale del plugin (con header "Plugin Name:")
     */
    private function find_plugin_root_dir($extracted_dir, $plugin) {
        // Cerca prima direttamente nella directory estratta
        $main_file = $this->find_plugin_main_file($extracted_dir);
        if ($main_file) {
            return dirname($main_file);
        }
        
        // Se non trovato, cerca nelle sottocartelle (max 2 livelli)
        $subdirs = glob($extracted_dir . '/*', GLOB_ONLYDIR);
        foreach ($subdirs as $subdir) {
            $main_file = $this->find_plugin_main_file($subdir);
            if ($main_file) {
                FP_Git_Updater_Logger::log('info', 'Plugin trovato in sottocartella: ' . basename($subdir));
                return dirname($main_file);
            }
            
            // Cerca anche un livello più in profondità
            $subsubdirs = glob($subdir . '/*', GLOB_ONLYDIR);
            foreach ($subsubdirs as $subsubdir) {
                $main_file = $this->find_plugin_main_file($subsubdir);
                if ($main_file) {
                    FP_Git_Updater_Logger::log('info', 'Plugin trovato in sotto-sottocartella: ' . basename($subsubdir));
                    return dirname($main_file);
                }
            }
        }
        
        // Se non trova un file principale, usa la directory estratta come fallback
        FP_Git_Updater_Logger::log('warning', 'File principale del plugin non trovato, uso directory estratta come fallback');
        return $extracted_dir;
    }
    
    /**
     * Cerca il file principale del plugin in una directory
     * Restituisce il path completo del file o false
     */
    private function find_plugin_main_file($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        // Cerca tutti i file PHP nella directory
        $php_files = glob($dir . '/*.php');
        
        foreach ($php_files as $php_file) {
            // Leggi le prime 8KB del file (sufficienti per l'header del plugin)
            $file_data = file_get_contents($php_file, false, null, 0, 8192);
            
            // Controlla se contiene "Plugin Name:" nell'header
            if ($file_data && preg_match('/Plugin Name:\s*(.+)/i', $file_data, $matches)) {
                FP_Git_Updater_Logger::log('info', 'File principale del plugin trovato: ' . basename($php_file) . ' (Plugin: ' . trim($matches[1]) . ')');
                return $php_file;
            }
        }
        
        return false;
    }
    
    /**
     * Controlla se ci sono aggiornamenti per un plugin specifico
     */
    private function check_plugin_for_updates($plugin) {
        if (empty($plugin['github_repo'])) {
            return false;
        }
        
        FP_Git_Updater_Logger::log('info', 'Controllo aggiornamenti per: ' . $plugin['name']);
        
        $latest_commit = $this->get_latest_commit($plugin);
        
        if (is_wp_error($latest_commit)) {
            FP_Git_Updater_Logger::log('error', 'Errore nel controllo aggiornamenti per ' . $plugin['name'] . ': ' . $latest_commit->get_error_message());
            return false;
        }
        
        $current_commit = get_option('fp_git_updater_current_commit_' . $plugin['id'], '');
        
        if ($latest_commit !== $current_commit) {
            FP_Git_Updater_Logger::log('info', 'Nuovo aggiornamento disponibile per ' . $plugin['name'] . ': ' . $latest_commit);
            
            // Registra l'aggiornamento come pending
            $commit_short = substr($latest_commit, 0, 7);
            update_option('fp_git_updater_pending_update_' . $plugin['id'], array(
                'commit_sha' => $latest_commit,
                'commit_sha_short' => $commit_short,
                'commit_message' => 'Aggiornamento rilevato dal controllo schedulato',
                'commit_author' => 'Sistema',
                'branch' => isset($plugin['branch']) ? $plugin['branch'] : 'main',
                'timestamp' => current_time('mysql'),
                'plugin_name' => $plugin['name'],
            ));
            
            // Se l'aggiornamento automatico è abilitato, eseguilo
            $settings = get_option('fp_git_updater_settings');
            if (isset($settings['auto_update']) && $settings['auto_update']) {
                FP_Git_Updater_Logger::log('info', 'Aggiornamento automatico in corso per ' . $plugin['name']);
                $this->run_update($latest_commit, $plugin);
            } else {
                FP_Git_Updater_Logger::log('info', 'Aggiornamento disponibile per ' . $plugin['name'] . ' ma installazione manuale richiesta (auto_update disabilitato)');
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Ottieni l'ultimo commit dal repository GitHub per un plugin specifico
     */
    private function get_latest_commit($plugin) {
        $repo = $plugin['github_repo'];
        $branch = isset($plugin['branch']) ? $plugin['branch'] : 'main';
        $encrypted_token = isset($plugin['github_token']) ? $plugin['github_token'] : '';
        
        // URL API GitHub per ottenere l'ultimo commit
        $api_url = "https://api.github.com/repos/{$repo}/commits/{$branch}";
        
        $args = array(
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'FP-Git-Updater/' . FP_GIT_UPDATER_VERSION,
            ),
            'timeout' => 30,
        );
        
        // Decripta e aggiungi il token se presente
        if (!empty($encrypted_token)) {
            $encryption = FP_Git_Updater_Encryption::get_instance();
            $token = $encryption->decrypt($encrypted_token);
            
            if ($token !== false && !empty($token)) {
                $args['headers']['Authorization'] = 'token ' . $token;
            }
        }
        
        // Usa la cache per le chiamate API
        $api_cache = FP_Git_Updater_API_Cache::get_instance();
        $response = $api_cache->cached_api_call($api_url, $args, 300); // Cache per 5 minuti
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code !== 200) {
            $error_message = 'Errore API GitHub: ' . $code;
            if ($code === 404) {
                $error_message .= ' - Repository o branch non trovato. Verifica: ' . $repo . ' (branch: ' . $branch . ')';
            } elseif ($code === 401 || $code === 403) {
                $error_message .= ' - Accesso negato. Verifica il token GitHub.';
            }
            return new WP_Error('api_error', $error_message);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['sha'])) {
            return $data['sha'];
        }
        
        return new WP_Error('invalid_response', 'Risposta API non valida');
    }
    
    /**
     * Esegue l'aggiornamento del plugin
     */
    public function run_update($commit_sha = null, $plugin = null) {
        // Se non viene passato un plugin specifico, aggiorna tutti i plugin abilitati
        if ($plugin === null) {
            $settings = get_option('fp_git_updater_settings');
            $plugins = isset($settings['plugins']) ? $settings['plugins'] : array();
            
            $success = true;
            foreach ($plugins as $p) {
                if (!isset($p['enabled']) || !$p['enabled']) {
                    continue;
                }
                
                $result = $this->run_plugin_update(null, $p);
                if (!$result) {
                    $success = false;
                }
            }
            
            return $success;
        }
        
        return $this->run_plugin_update($commit_sha, $plugin);
    }
    
    /**
     * Esegue l'aggiornamento di un plugin specifico
     */
    private function run_plugin_update($commit_sha = null, $plugin) {
        try {
            FP_Git_Updater_Logger::log('info', 'Inizio aggiornamento per: ' . $plugin['name']);
            
            // Notifica inizio aggiornamento
            $this->send_notification('Inizio aggiornamento ' . $plugin['name'], 'L\'aggiornamento è iniziato...');
            
            $repo = $plugin['github_repo'];
            $branch = isset($plugin['branch']) ? $plugin['branch'] : 'main';
            $encrypted_token = isset($plugin['github_token']) ? $plugin['github_token'] : '';
            
            if (empty($repo)) {
                throw new Exception('Repository non configurato per ' . $plugin['name']);
            }
            
            // URL per scaricare il file zip del repository
            $download_url = "https://api.github.com/repos/{$repo}/zipball/{$branch}";
            
            $args = array(
                'timeout' => 300,
                'headers' => array(
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'FP-Git-Updater/' . FP_GIT_UPDATER_VERSION,
                ),
            );
            
            // Decripta e aggiungi il token se presente
            if (!empty($encrypted_token)) {
                $encryption = FP_Git_Updater_Encryption::get_instance();
                $token = $encryption->decrypt($encrypted_token);
                
                if ($token !== false && !empty($token)) {
                    $args['headers']['Authorization'] = 'token ' . $token;
                }
            }
        } catch (Exception $e) {
            FP_Git_Updater_Logger::log('error', 'Errore durante preparazione aggiornamento: ' . $e->getMessage());
            $this->send_notification('Errore aggiornamento', $e->getMessage());
            return false;
        }
        
        // Scarica il file zip
        FP_Git_Updater_Logger::log('info', 'Download dell\'aggiornamento...');
        
        // Usa wp_remote_get per supportare headers personalizzati (per repository privati)
        $response = wp_remote_get($download_url, $args);
        
        if (is_wp_error($response)) {
            FP_Git_Updater_Logger::log('error', 'Errore download: ' . $response->get_error_message());
            $this->send_notification('Errore aggiornamento', 'Errore durante il download: ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200) {
            FP_Git_Updater_Logger::log('error', 'Errore download: HTTP ' . $response_code);
            $this->send_notification('Errore aggiornamento', 'Errore HTTP durante il download: ' . $response_code);
            return false;
        }
        
        // Salva il contenuto in un file temporaneo
        $body = wp_remote_retrieve_body($response);
        
        if (empty($body)) {
            FP_Git_Updater_Logger::log('error', 'Errore download: file vuoto');
            $this->send_notification('Errore aggiornamento', 'Il file scaricato è vuoto');
            return false;
        }
        
        // Crea directory upgrade se non esiste
        $upgrade_dir = WP_CONTENT_DIR . '/upgrade';
        if (!file_exists($upgrade_dir)) {
            wp_mkdir_p($upgrade_dir);
        }
        
        // Salva in un file temporaneo nella directory upgrade
        $temp_file = $upgrade_dir . '/fp-git-updater-download-' . time() . '.zip';
        
        // Usa file_put_contents che è più affidabile per questa operazione
        if (!file_put_contents($temp_file, $body)) {
            FP_Git_Updater_Logger::log('error', 'Errore nel salvare il file temporaneo');
            $this->send_notification('Errore aggiornamento', 'Impossibile salvare il file temporaneo');
            return false;
        }
        
        // Unzip il file
        FP_Git_Updater_Logger::log('info', 'Estrazione dell\'aggiornamento...');
        
        // Inizializza WP_Filesystem per unzip
        global $wp_filesystem;
        if (!$wp_filesystem) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        
        $temp_extract_dir = $upgrade_dir . '/fp-git-updater-temp';
        
        // Rimuovi directory temporanea se esiste già
        if (file_exists($temp_extract_dir)) {
            $wp_filesystem->delete($temp_extract_dir, true);
        }
        
        $unzip_result = unzip_file($temp_file, $temp_extract_dir);
        
        // Rimuovi il file temporaneo
        @unlink($temp_file);
        
        if (is_wp_error($unzip_result)) {
            FP_Git_Updater_Logger::log('error', 'Errore estrazione: ' . $unzip_result->get_error_message());
            $this->send_notification('Errore aggiornamento', 'Errore durante l\'estrazione: ' . $unzip_result->get_error_message());
            return false;
        }
        
        // Trova la directory estratta (GitHub crea una directory con nome casuale)
        $extracted_dirs = glob($temp_extract_dir . '/*', GLOB_ONLYDIR);
        
        if (empty($extracted_dirs)) {
            FP_Git_Updater_Logger::log('error', 'Directory estratta non trovata');
            $wp_filesystem->delete($temp_extract_dir, true);
            $this->send_notification('Errore aggiornamento', 'Directory estratta non trovata');
            return false;
        }
        
        $github_extracted_dir = $extracted_dirs[0];
        
        // Cerca il file principale del plugin nella directory estratta
        // Potrebbe essere direttamente nella directory o in una sottocartella
        $source_dir = $this->find_plugin_root_dir($github_extracted_dir, $plugin);
        
        if (!$source_dir) {
            FP_Git_Updater_Logger::log('error', 'File principale del plugin non trovato nella directory estratta', array(
                'extracted_dir' => $github_extracted_dir,
                'plugin_name' => $plugin['name']
            ));
            $wp_filesystem->delete($temp_extract_dir, true);
            $this->send_notification('Errore aggiornamento', 'Struttura del plugin non valida nella directory estratta');
            return false;
        }
        
        FP_Git_Updater_Logger::log('info', 'Directory plugin trovata: ' . basename($source_dir));
        
        // Determina la directory del plugin da aggiornare
        // Se il plugin ha uno slug specificato, usa quello, altrimenti deduce dal repo
        $plugin_slug = isset($plugin['plugin_slug']) && !empty($plugin['plugin_slug']) 
            ? $plugin['plugin_slug'] 
            : basename($repo);
        
        $plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_slug;
        
        // Backup della versione attuale (solo se la directory esiste)
        $backup_dir = null;
        if (file_exists($plugin_dir) && is_dir($plugin_dir)) {
            FP_Git_Updater_Logger::log('info', 'Creazione backup...');
            $backup_dir = WP_CONTENT_DIR . '/upgrade/fp-git-updater-backup-' . time();
            
            // Usa rename() nativo che è più affidabile per operazioni atomiche
            if (!@rename($plugin_dir, $backup_dir)) {
                FP_Git_Updater_Logger::log('error', 'Impossibile creare backup: verifica i permessi della directory');
                $wp_filesystem->delete($temp_extract_dir, true);
                $this->send_notification('Errore aggiornamento', 'Impossibile creare backup della versione corrente. Verifica i permessi.');
                return false;
            }
            FP_Git_Updater_Logger::log('info', 'Backup creato con successo');
        } else {
            FP_Git_Updater_Logger::log('info', 'Prima installazione, backup non necessario');
        }
        
        // Copia i nuovi file
        FP_Git_Updater_Logger::log('info', 'Installazione nuovi file...');
        $copy_result = copy_dir($source_dir, $plugin_dir);
        
        if (is_wp_error($copy_result)) {
            // Ripristina il backup se esiste
            FP_Git_Updater_Logger::log('error', 'Errore installazione: ' . $copy_result->get_error_message());
            if ($backup_dir && file_exists($backup_dir)) {
                @rename($backup_dir, $plugin_dir);
                FP_Git_Updater_Logger::log('info', 'Backup ripristinato');
                $this->send_notification('Errore aggiornamento', 'Errore durante l\'installazione. Backup ripristinato.');
            } else {
                $this->send_notification('Errore aggiornamento', 'Errore durante l\'installazione.');
            }
            $wp_filesystem->delete($temp_extract_dir, true);
            return false;
        }
        
        // Pulisci
        $wp_filesystem->delete($temp_extract_dir, true);
        
        // Salva il commit corrente per questo plugin
        if ($commit_sha) {
            update_option('fp_git_updater_current_commit_' . $plugin['id'], $commit_sha);
        } else {
            $latest_commit = $this->get_latest_commit($plugin);
            if (!is_wp_error($latest_commit)) {
                update_option('fp_git_updater_current_commit_' . $plugin['id'], $latest_commit);
            }
        }
        
        update_option('fp_git_updater_last_update_' . $plugin['id'], current_time('mysql'));
        
        // Rimuovi il pending update se esiste
        delete_option('fp_git_updater_pending_update_' . $plugin['id']);
        
        FP_Git_Updater_Logger::log('success', 'Aggiornamento completato con successo per: ' . $plugin['name']);
        $this->send_notification('Aggiornamento completato', 'Il plugin ' . $plugin['name'] . ' è stato aggiornato con successo!');
        
        // Mantieni il backup per 7 giorni (se è stato creato)
        if ($backup_dir && file_exists($backup_dir)) {
            wp_schedule_single_event(time() + (7 * DAY_IN_SECONDS), 'fp_git_updater_cleanup_backup', array($backup_dir));
        }
        
        return true;
    }
    
    /**
     * Pulisci un backup specifico
     */
    public function cleanup_backup($backup_dir) {
        if (file_exists($backup_dir) && is_dir($backup_dir)) {
            global $wp_filesystem;
            if (!$wp_filesystem) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            
            $wp_filesystem->delete($backup_dir, true);
            FP_Git_Updater_Logger::log('info', 'Backup eliminato: ' . basename($backup_dir));
        }
    }
    
    /**
     * Invia notifica email
     */
    private function send_notification($subject, $message) {
        $settings = get_option('fp_git_updater_settings');
        
        if (!isset($settings['enable_notifications']) || !$settings['enable_notifications']) {
            return;
        }
        
        $email = isset($settings['notification_email']) ? $settings['notification_email'] : get_option('admin_email');
        
        wp_mail($email, $subject, $message);
    }
}
