<?php
/**
 * Gestione Aggiornamenti del Plugin
 * 
 * Scarica e installa gli aggiornamenti dal repository GitHub
 */


namespace FP\GitUpdater;

if (!defined('ABSPATH')) {
    exit;
}

class Updater {
    
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
        add_action('fp_git_updater_cleanup_old_logs', array('FP\GitUpdater\Logger', 'clear_old_logs'));
        
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
            // Aggiungi 1 minuto di offset per evitare race condition
            wp_schedule_event(time() + 60, $interval, 'fp_git_updater_check_update');
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
        
        Logger::log('info', 'Controllo aggiornamenti per tutti i plugin...');
        
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
        
        $result = $this->run_plugin_update($plugin, $commit_sha);
        
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
        Logger::log('error', 'Plugin non trovato con ID: ' . $plugin_id, array(
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
        if ($subdirs !== false) {
            foreach ($subdirs as $subdir) {
                $main_file = $this->find_plugin_main_file($subdir);
                if ($main_file) {
                    Logger::log('info', 'Plugin trovato in sottocartella: ' . basename($subdir));
                    return dirname($main_file);
                }
                
                // Cerca anche un livello più in profondità
                $subsubdirs = glob($subdir . '/*', GLOB_ONLYDIR);
                if ($subsubdirs !== false) {
                    foreach ($subsubdirs as $subsubdir) {
                        $main_file = $this->find_plugin_main_file($subsubdir);
                        if ($main_file) {
                            Logger::log('info', 'Plugin trovato in sotto-sottocartella: ' . basename($subsubdir));
                            return dirname($main_file);
                        }
                    }
                }
            }
        }
        
        // Se non trova un file principale, usa la directory estratta come fallback
        Logger::log('warning', 'File principale del plugin non trovato, uso directory estratta come fallback');
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
        
        if ($php_files !== false) {
            foreach ($php_files as $php_file) {
                // Leggi le prime 8KB del file (sufficienti per l'header del plugin)
                $file_data = @file_get_contents($php_file, false, null, 0, 8192);
                
                // Controlla se contiene "Plugin Name:" nell'header
                if ($file_data && preg_match('/Plugin Name:\s*(.+)/i', $file_data, $matches)) {
                    Logger::log('info', 'File principale del plugin trovato: ' . basename($php_file) . ' (Plugin: ' . trim($matches[1]) . ')');
                    return $php_file;
                }
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
        
        Logger::log('info', 'Controllo aggiornamenti per: ' . $plugin['name']);
        
        $latest_commit = $this->get_latest_commit($plugin);
        
        if (is_wp_error($latest_commit)) {
            Logger::log('error', 'Errore nel controllo aggiornamenti per ' . $plugin['name'] . ': ' . $latest_commit->get_error_message());
            return false;
        }
        
        $current_commit = get_option('fp_git_updater_current_commit_' . $plugin['id'], '');
        
        if ($latest_commit !== $current_commit) {
            Logger::log('info', 'Nuovo aggiornamento disponibile per ' . $plugin['name'] . ': ' . $latest_commit);
            
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
                Logger::log('info', 'Aggiornamento automatico in corso per ' . $plugin['name']);
                $this->run_update($latest_commit, $plugin);
            } else {
                Logger::log('info', 'Aggiornamento disponibile per ' . $plugin['name'] . ' ma installazione manuale richiesta (auto_update disabilitato)');
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
            $encryption = Encryption::get_instance();
            $token = $encryption->decrypt($encrypted_token);
            
            if ($token !== false && !empty($token)) {
                $args['headers']['Authorization'] = 'token ' . $token;
            }
        }
        
        // Usa la cache per le chiamate API
        $api_cache = ApiCache::get_instance();
        $response = $api_cache->cached_api_call($api_url, $args, 300); // Cache per 5 minuti
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code !== 200) {
            $error_message = 'Errore API GitHub: ' . $code;
            if ($code === 404) {
                $error_message .= ' - Repository o branch non trovato. Verifica: ' . $repo . ' (branch: ' . $branch . ')';
            } elseif ($code === 401) {
                $error_message .= ' - Autenticazione fallita. Token GitHub non valido o scaduto.';
            } elseif ($code === 403) {
                $error_message .= ' - Accesso negato. Verifica il token GitHub e i permessi del repository. Potrebbe essere un limite rate API.';
            } elseif ($code === 422) {
                $error_message .= ' - Richiesta non valida. Verifica il nome del branch.';
            } elseif ($code >= 500) {
                $error_message .= ' - Errore server GitHub. Riprova più tardi.';
            } elseif ($code === 301 || $code === 302) {
                $error_message .= ' - Repository spostato o rinominato.';
            }
            return new WP_Error('api_error', $error_message);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Verifica errori JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::log('error', 'Errore parsing JSON dalla risposta API: ' . json_last_error_msg());
            return new WP_Error('json_error', 'Risposta API non valida (JSON malformato)');
        }
        
        if (!is_array($data)) {
            Logger::log('error', 'Risposta API non è un array valido');
            return new WP_Error('invalid_response', 'Formato risposta API non valido');
        }
        
        if (isset($data['sha'])) {
            return $data['sha'];
        }
        
        // Log della risposta per debug
        Logger::log('error', 'SHA commit non trovato nella risposta API', array(
            'response_keys' => array_keys($data)
        ));
        
        return new WP_Error('invalid_response', 'SHA commit non trovato nella risposta API');
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
                
                $result = $this->run_plugin_update($p, null);
                if (!$result) {
                    $success = false;
                }
            }
            
            return $success;
        }
        
        return $this->run_plugin_update($plugin, $commit_sha);
    }
    
    /**
     * Esegue l'aggiornamento di un plugin specifico
     */
    private function run_plugin_update($plugin, $commit_sha = null) {
        // Verifica se c'è già un aggiornamento in corso per questo plugin (lock)
        $lock_key = 'fp_git_updater_lock_' . $plugin['id'];
        $lock_value = get_transient($lock_key);
        
        if ($lock_value !== false) {
            Logger::log('warning', 'Aggiornamento già in corso per: ' . $plugin['name'] . ' - saltato');
            return false;
        }
        
        // Imposta il lock (scade dopo 10 minuti come failsafe)
        set_transient($lock_key, time(), 600);
        
        try {
            Logger::log('info', 'Inizio aggiornamento per: ' . $plugin['name']);
            
            // Notifica inizio aggiornamento
            $this->send_notification('Inizio aggiornamento ' . $plugin['name'], 'L\'aggiornamento è iniziato...');
            
            $repo = $plugin['github_repo'];
            $branch = isset($plugin['branch']) ? $plugin['branch'] : 'main';
            $encrypted_token = isset($plugin['github_token']) ? $plugin['github_token'] : '';

            if (empty($repo) && empty($zip_url)) {
                throw new Exception('Sorgente aggiornamento non configurata per ' . $plugin['name'] . ' (repo o URL ZIP richiesto)');
            }

            // Supporto modalità semplice: URL ZIP pubblico opzionale
            $zip_url = isset($plugin['zip_url']) ? trim($plugin['zip_url']) : '';

            if (!empty($zip_url) && filter_var($zip_url, FILTER_VALIDATE_URL)) {
                $download_url = $zip_url;
                Logger::log('info', 'Modalità ZIP pubblico attiva per: ' . $plugin['name']);
                $args = array(
                    'timeout' => 300,
                    'redirection' => 5,
                    'headers' => array(
                        'User-Agent' => 'FP-Git-Updater/' . FP_GIT_UPDATER_VERSION,
                    ),
                );
                // Log se estensione non è .zip
                if (stripos(parse_url($zip_url, PHP_URL_PATH), '.zip') === false) {
                    Logger::log('warning', 'URL ZIP senza estensione .zip: ' . $zip_url);
                }
            } else {
                // GitHub repository download
                $args = array(
                    'timeout' => 300,
                    'redirection' => 5,
                    'headers' => array(
                        'User-Agent' => 'FP-Git-Updater/' . FP_GIT_UPDATER_VERSION,
                    ),
                );

                // Se c'è un token, usa l'API GitHub (per repository privati)
                if (!empty($encrypted_token)) {
                    $encryption = Encryption::get_instance();
                    $token = $encryption->decrypt($encrypted_token);
                    if ($token !== false && !empty($token)) {
                        // Usa API zipball con autenticazione per repository privati
                        $download_url = "https://api.github.com/repos/{$repo}/zipball/{$branch}";
                        $args['headers']['Accept'] = 'application/vnd.github.v3+json';
                        $args['headers']['Authorization'] = 'token ' . $token;
                        Logger::log('info', 'Usando API GitHub con token per repository privato');
                    } else {
                        // Token decryption failed, fallback a URL pubblico
                        $download_url = "https://github.com/{$repo}/archive/refs/heads/{$branch}.zip";
                        Logger::log('warning', 'Token decryption fallito, uso URL pubblico');
                    }
                } else {
                    // Repository pubblico: usa URL diretto senza API (no autenticazione richiesta)
                    $download_url = "https://github.com/{$repo}/archive/refs/heads/{$branch}.zip";
                    Logger::log('info', 'Repository pubblico, usando URL diretto GitHub (nessun token)');
                }
            }
        } catch (\Exception $e) {
            Logger::log('error', 'Errore durante preparazione aggiornamento: ' . $e->getMessage());
            $this->send_notification('Errore aggiornamento', $e->getMessage());
            // Rilascia il lock
            delete_transient($lock_key);
            return false;
        }
        
        // Scarica il file zip usando download_url() che fa streaming su file
        // Questo evita di caricare l'intero file in memoria
        Logger::log('info', 'Download dell\'aggiornamento...');
        
        // Crea directory upgrade se non esiste (serve per il temp file)
        $upgrade_dir = WP_CONTENT_DIR . '/upgrade';
        if (!file_exists($upgrade_dir)) {
            wp_mkdir_p($upgrade_dir);
        }
        
        // File temporaneo per il download
        $temp_file = $upgrade_dir . '/fp-git-updater-download-' . time() . '-' . uniqid() . '.zip';
        
        // Se abbiamo headers custom (token), usiamo wp_remote_get + streaming
        if (!empty($args['headers'])) {
            // Per repository privati con token, usa wp_remote_get con stream
            $args['stream'] = true;
            $args['filename'] = $temp_file;
            
            $response = $this->request_with_retry($download_url, $args, 2);
            
            if (is_wp_error($response)) {
                @unlink($temp_file);
                Logger::log('error', 'Errore download: ' . $response->get_error_message());
                $this->send_notification('Errore aggiornamento', 'Errore durante il download: ' . $response->get_error_message());
                delete_transient($lock_key);
                return false;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                @unlink($temp_file);
                Logger::log('error', 'Errore download: HTTP ' . $response_code);
                $this->send_notification('Errore aggiornamento', 'Errore HTTP durante il download: ' . $response_code);
                delete_transient($lock_key);
                return false;
            }
        } else {
            // Per repository pubblici, usa download_url() nativo WordPress (più efficiente)
            $downloaded = download_url($download_url, 300); // 5 minuti timeout
            
            if (is_wp_error($downloaded)) {
                Logger::log('error', 'Errore download: ' . $downloaded->get_error_message());
                $this->send_notification('Errore aggiornamento', 'Errore durante il download: ' . $downloaded->get_error_message());
                delete_transient($lock_key);
                return false;
            }
            
            // download_url() restituisce il path del file temporaneo, rinominiamolo
            if (!@rename($downloaded, $temp_file)) {
                // Se rename fallisce, copia e cancella
                if (!@copy($downloaded, $temp_file)) {
                    @unlink($downloaded);
                    Logger::log('error', 'Errore nel copiare il file scaricato');
                    $this->send_notification('Errore aggiornamento', 'Errore nel copiare il file scaricato');
                    delete_transient($lock_key);
                    return false;
                }
                @unlink($downloaded);
            }
        }
        
        // Verifica che il file esista e non sia vuoto
        if (!file_exists($temp_file) || filesize($temp_file) === 0) {
            @unlink($temp_file);
            Logger::log('error', 'Errore download: file vuoto o non trovato');
            $this->send_notification('Errore aggiornamento', 'Il file scaricato è vuoto');
            delete_transient($lock_key);
            return false;
        }
        
        $body_size = filesize($temp_file);
        $max_size = 100 * 1024 * 1024; // 100MB
        
        if ($body_size > $max_size) {
            @unlink($temp_file);
            Logger::log('error', 'File troppo grande: ' . round($body_size / 1024 / 1024, 2) . 'MB (max 100MB)');
            $this->send_notification('Errore aggiornamento', 'Repository troppo grande. Considera di ridurre la dimensione o escludere file non necessari.');
            delete_transient($lock_key);
            return false;
        }
        
        Logger::log('info', 'Download completato: ' . round($body_size / 1024 / 1024, 2) . 'MB');
        
        // Per file grandi, aumenta i limiti PHP prima dell'estrazione
        if ($body_size > 50 * 1024 * 1024) { // > 50MB
            Logger::log('info', 'File grande rilevato, aumento limiti PHP per estrazione...');
            @ini_set('max_execution_time', '0');
            @ini_set('memory_limit', '512M');
            @set_time_limit(0);
        }
        
        // Unzip il file
        Logger::log('info', 'Estrazione dell\'aggiornamento...');
        
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
            Logger::log('error', 'Errore estrazione: ' . $unzip_result->get_error_message());
            $this->send_notification('Errore aggiornamento', 'Errore durante l\'estrazione: ' . $unzip_result->get_error_message());
            delete_transient($lock_key);
            return false;
        }
        
        // Trova la directory estratta (GitHub crea una directory con nome casuale)
        $extracted_dirs = glob($temp_extract_dir . '/*', GLOB_ONLYDIR);
        
        if (empty($extracted_dirs)) {
            Logger::log('error', 'Directory estratta non trovata');
            $wp_filesystem->delete($temp_extract_dir, true);
            $this->send_notification('Errore aggiornamento', 'Directory estratta non trovata');
            delete_transient($lock_key);
            return false;
        }
        
        $github_extracted_dir = $extracted_dirs[0];
        
        // Cerca il file principale del plugin nella directory estratta
        // Potrebbe essere direttamente nella directory o in una sottocartella
        $source_dir = $this->find_plugin_root_dir($github_extracted_dir, $plugin);
        
        if (!$source_dir) {
            Logger::log('error', 'File principale del plugin non trovato nella directory estratta', array(
                'extracted_dir' => $github_extracted_dir,
                'plugin_name' => $plugin['name']
            ));
            $wp_filesystem->delete($temp_extract_dir, true);
            $this->send_notification('Errore aggiornamento', 'Struttura del plugin non valida nella directory estratta');
            delete_transient($lock_key);
            return false;
        }
        
        Logger::log('info', 'Directory plugin trovata: ' . basename($source_dir));
        
        // Pulisci file non necessari dalla directory estratta per ridurre dimensione
        $this->cleanup_unnecessary_files($source_dir);
        
        // Determina la directory del plugin da aggiornare
        // Preferisci slug configurato; se assente:
        // - con repo: deduci dal repo
        // - ZIP-only: deduci dal nome della directory sorgente
        if (isset($plugin['plugin_slug']) && !empty($plugin['plugin_slug'])) {
            $plugin_slug = $plugin['plugin_slug'];
        } else if (!empty($repo)) {
            $plugin_slug = basename($repo);
        } else {
            // ZIP-only fallback
            $plugin_slug = basename($source_dir);
            Logger::log('info', 'Slug dedotto da directory estratta (ZIP-only): ' . $plugin_slug);
        }
        
        // Sanitizza lo slug (rimuovi caratteri non validi per nomi directory)
        $plugin_slug = preg_replace('/[^a-zA-Z0-9_-]/', '-', $plugin_slug);
        $plugin_slug = trim($plugin_slug, '-');
        
        if (empty($plugin_slug)) {
            Logger::log('error', 'Plugin slug non valido dopo sanitizzazione');
            $wp_filesystem->delete($temp_extract_dir, true);
            $this->send_notification('Errore aggiornamento', 'Nome plugin non valido');
            delete_transient($lock_key);
            return false;
        }
        
        $plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_slug;
        
        // Verifica se stiamo cercando di aggiornare il plugin stesso
        if ($plugin_slug === 'fp-git-updater' || $plugin_slug === dirname(FP_GIT_UPDATER_PLUGIN_BASENAME)) {
            Logger::log('info', 'Auto-aggiornamento del plugin FP Git Updater in corso...');
            
            // Per l'auto-aggiornamento, usiamo un approccio più sicuro
            return $this->run_self_update($plugin, $commit_sha);
        }
        
        // Backup della versione attuale (solo se la directory esiste)
        $backup_dir = null;
        if (file_exists($plugin_dir) && is_dir($plugin_dir)) {
            Logger::log('info', 'Creazione backup...');
            $backup_dir = WP_CONTENT_DIR . '/upgrade/fp-git-updater-backup-' . time() . '-' . uniqid();
            
            // Usa rename() nativo che è più affidabile per operazioni atomiche
            if (!@rename($plugin_dir, $backup_dir)) {
                Logger::log('error', 'Impossibile creare backup: verifica i permessi della directory');
                $wp_filesystem->delete($temp_extract_dir, true);
                $this->send_notification('Errore aggiornamento', 'Impossibile creare backup della versione corrente. Verifica i permessi.');
                delete_transient($lock_key);
                return false;
            }
            Logger::log('info', 'Backup creato con successo');
        } else {
            Logger::log('info', 'Prima installazione, backup non necessario');
        }
        
        // Copia i nuovi file
        Logger::log('info', 'Installazione nuovi file...');
        
        // Verifica che la directory sorgente esista e sia leggibile
        if (!is_dir($source_dir) || !is_readable($source_dir)) {
            Logger::log('error', 'Directory sorgente non valida o non leggibile: ' . $source_dir);
            if ($backup_dir && file_exists($backup_dir)) {
                @rename($backup_dir, $plugin_dir);
                Logger::log('info', 'Backup ripristinato');
            }
            $wp_filesystem->delete($temp_extract_dir, true);
            $this->send_notification('Errore aggiornamento', 'Directory sorgente non valida.');
            delete_transient($lock_key);
            return false;
        }
        
        // Verifica che la directory parent di destinazione esista e sia scrivibile
        $parent_dir = dirname($plugin_dir);
        if (!is_dir($parent_dir)) {
            Logger::log('error', 'Directory parent non esiste: ' . $parent_dir);
            if ($backup_dir && file_exists($backup_dir)) {
                @rename($backup_dir, $plugin_dir);
                Logger::log('info', 'Backup ripristinato');
            }
            $wp_filesystem->delete($temp_extract_dir, true);
            $this->send_notification('Errore aggiornamento', 'Directory plugins non trovata.');
            delete_transient($lock_key);
            return false;
        }
        
        if (!is_writable($parent_dir)) {
            Logger::log('error', 'Directory parent non scrivibile: ' . $parent_dir . ' (permessi: ' . substr(sprintf('%o', fileperms($parent_dir)), -4) . ')');
            if ($backup_dir && file_exists($backup_dir)) {
                @rename($backup_dir, $plugin_dir);
                Logger::log('info', 'Backup ripristinato');
            }
            $wp_filesystem->delete($temp_extract_dir, true);
            $this->send_notification('Errore aggiornamento', 'Directory plugins non scrivibile. Verifica i permessi.');
            delete_transient($lock_key);
            return false;
        }
        
        // Assicurati che la directory di destinazione non esista (dovrebbe essere stata spostata nel backup)
        if (file_exists($plugin_dir)) {
            Logger::log('warning', 'La directory di destinazione esiste ancora, provo a rimuoverla: ' . $plugin_dir);
            if (!$wp_filesystem->delete($plugin_dir, true)) {
                Logger::log('error', 'Impossibile rimuovere directory esistente: ' . $plugin_dir);
                if ($backup_dir && file_exists($backup_dir)) {
                    @rename($backup_dir, $plugin_dir);
                    Logger::log('info', 'Backup ripristinato');
                }
                $wp_filesystem->delete($temp_extract_dir, true);
                $this->send_notification('Errore aggiornamento', 'Impossibile pulire la directory di destinazione.');
                delete_transient($lock_key);
                return false;
            }
        }
        
        // PRIMA prova rename() - è molto più veloce e affidabile (operazione atomica)
        // rename() sposta l'intera cartella con un singolo comando invece di copiare file per file
        Logger::log('info', 'Tentativo di spostamento diretto (rename)...');
        
        $move_success = @rename($source_dir, $plugin_dir);
        
        if ($move_success) {
            Logger::log('info', 'Spostamento diretto riuscito');
        } else {
            // Se rename fallisce (es. filesystem diversi), usa copy_dir come fallback
            Logger::log('info', 'Rename fallito, uso copy_dir come fallback...');
            
            // Per plugin con molti file, aumentiamo i limiti PHP prima della copia
            $file_count = $this->count_files_recursive($source_dir);
            Logger::log('info', 'File da copiare: ' . number_format($file_count, 0, ',', '.'));
            
            if ($file_count > 10000) {
                // Plugin grande: aumenta limiti PHP
                Logger::log('info', 'Plugin grande rilevato, aumento limiti PHP...');
                @ini_set('max_execution_time', '0'); // Nessun limite
                @ini_set('memory_limit', '512M'); // Aumenta memoria
                @set_time_limit(0); // Disabilita timeout
                Logger::log('info', 'Limiti PHP aumentati per gestire plugin grande');
            }
            
            // Usa la funzione di copia ottimizzata per plugin grandi
            $copy_result = $this->copy_directory_optimized($source_dir, $plugin_dir, $wp_filesystem, $file_count);
            
            if (is_wp_error($copy_result)) {
                // Ripristina il backup se esiste
                $error_msg = $copy_result->get_error_message();
                $error_data = $copy_result->get_error_data();
                
                Logger::log('error', 'Errore installazione: ' . $error_msg, array(
                    'source' => $source_dir,
                    'destination' => $plugin_dir,
                    'error_data' => $error_data,
                    'file_count' => $file_count
                ));
                
                if ($backup_dir && file_exists($backup_dir)) {
                    @rename($backup_dir, $plugin_dir);
                    Logger::log('info', 'Backup ripristinato');
                    $this->send_notification('Errore aggiornamento', 'Errore durante l\'installazione: ' . $error_msg . '. Backup ripristinato.');
                } else {
                    $this->send_notification('Errore aggiornamento', 'Errore durante l\'installazione: ' . $error_msg);
                }
                $wp_filesystem->delete($temp_extract_dir, true);
                delete_transient($lock_key);
                return false;
            }
        }
        
        // Verifica che i file siano stati copiati correttamente
        if (!is_dir($plugin_dir) || !is_readable($plugin_dir)) {
            Logger::log('error', 'La directory di destinazione non è valida dopo la copia');
            if ($backup_dir && file_exists($backup_dir)) {
                $wp_filesystem->delete($plugin_dir, true);
                @rename($backup_dir, $plugin_dir);
                Logger::log('info', 'Backup ripristinato');
                $this->send_notification('Errore aggiornamento', 'Verifica post-copia fallita. Backup ripristinato.');
            }
            $wp_filesystem->delete($temp_extract_dir, true);
            delete_transient($lock_key);
            return false;
        }
        
        Logger::log('info', 'File copiati con successo');
        
        // Pulisci
        $wp_filesystem->delete($temp_extract_dir, true);
        
        // Salva il commit corrente per questo plugin
        if ($commit_sha) {
            update_option('fp_git_updater_current_commit_' . $plugin['id'], $commit_sha);
        } else if (!empty($plugin['github_repo'])) {
            // Solo se abbiamo un repo configurato
            $latest_commit = $this->get_latest_commit($plugin);
            if (!is_wp_error($latest_commit)) {
                update_option('fp_git_updater_current_commit_' . $plugin['id'], $latest_commit);
            }
        } else {
            // Modalità ZIP-only: salva un identificatore sintetico basato su timestamp
            $synthetic = 'zip:' . time();
            update_option('fp_git_updater_current_commit_' . $plugin['id'], $synthetic);
        }
        
        update_option('fp_git_updater_last_update_' . $plugin['id'], current_time('mysql'));
        
        // Rimuovi il pending update se esiste
        delete_option('fp_git_updater_pending_update_' . $plugin['id']);
        
        Logger::log('success', 'Aggiornamento completato con successo per: ' . $plugin['name']);
        $this->send_notification('Aggiornamento completato', 'Il plugin ' . $plugin['name'] . ' è stato aggiornato con successo!');
        
        // Mantieni il backup per 7 giorni (se è stato creato)
        if ($backup_dir && file_exists($backup_dir)) {
            wp_schedule_single_event(time() + (7 * DAY_IN_SECONDS), 'fp_git_updater_cleanup_backup', array($backup_dir));
        }
        
        // Rilascia il lock
        delete_transient($lock_key);
        
        return true;
    }

    /**
     * Esegue una richiesta HTTP con semplici retry/backoff.
     */
    private function request_with_retry($url, $args, $retries = 2) {
        $attempt = 0;
        $delay_ms = 600;
        while (true) {
            $attempt++;
            $response = wp_remote_get($url, $args);
            if (!is_wp_error($response)) {
                $code = wp_remote_retrieve_response_code($response);
                // Ritenta su 429 o 5xx
                if ($code !== 429 && ($code < 500 || $code >= 600)) {
                    return $response;
                }
            }
            if ($attempt > $retries) {
                return $response;
            }
            Logger::log('warning', 'Retry download (' . $attempt . '/' . $retries . ') per URL: ' . $url);
            // Backoff semplice
            usleep($delay_ms * 1000);
            $delay_ms *= 2;
        }
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
            Logger::log('info', 'Backup eliminato: ' . basename($backup_dir));
        }
    }
    
    /**
     * Invia notifica email
     */
    private function send_notification($subject, $message) {
        $settings = get_option('fp_git_updater_settings');
        
        if (!isset($settings['enable_notifications']) || !$settings['enable_notifications']) {
            return false;
        }
        
        $email = isset($settings['notification_email']) ? $settings['notification_email'] : get_option('admin_email');
        
        // Valida l'email prima di inviare
        if (!is_email($email)) {
            Logger::log('error', 'Email notifica non valida: ' . $email);
            return false;
        }
        
        // Invia email e logga il risultato
        $result = wp_mail($email, $subject, $message);
        
        if (!$result) {
            Logger::log('warning', 'Impossibile inviare notifica email a: ' . $email, array(
                'subject' => $subject,
                'message' => substr($message, 0, 100)
            ));
        }
        
        return $result;
    }
    
    /**
     * Pulisce file non necessari dalla directory estratta per ridurre dimensione
     * Esclude: documentazione, test, tools, build files, node_modules, etc.
     */
    private function cleanup_unnecessary_files($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        global $wp_filesystem;
        if (!$wp_filesystem) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        
        $files_removed = 0;
        $size_saved = 0;
        
        // Pattern di file e directory da escludere
        $exclude_patterns = array(
            // Directory da rimuovere completamente
            'directories' => array(
                'tests',
                'test',
                'testsuite',
                'tools',
                'examples',
                'docs',
                'documentation',
                'node_modules',
                '.git',
                '.github',
                'build',
                'dist',
                // vendor/ NON viene escluso perché necessario per autoload Composer
            ),
            // Pattern di file da rimuovere
            'file_patterns' => array(
                '*.md', // Documentazione markdown
                '*.txt', // File di testo (eccetto readme.txt che WordPress richiede)
                '*.sh', // Script shell
                '*.bat', // Script batch
                '*.ps1', // PowerShell
                'phpunit.xml*', // Configurazione test
                'phpcs.xml*', // Configurazione code style
                'phpstan.neon*', // Configurazione static analysis
                '.gitignore',
                '.gitattributes',
                'composer.lock', // Lock file (composer.json va mantenuto)
                'package-lock.json', // Lock file npm
                'Dockerfile*',
                'docker-compose*.yml',
                '*.log',
                '*.cache',
            ),
            // File specifici da mantenere (eccezioni)
            'keep_files' => array(
                'readme.txt', // WordPress richiede questo file
                'README.md', // Manteniamo il README principale
                'composer.json', // Necessario per autoload
                'package.json', // Potrebbe essere necessario
            ),
        );
        
        // Funzione ricorsiva per rimuovere file
        $root_dir = $dir;
        $cleanup_recursive = function($current_dir) use (&$cleanup_recursive, &$files_removed, &$size_saved, $exclude_patterns, $wp_filesystem, $root_dir) {
            if (!is_dir($current_dir)) {
                return;
            }
            
            $items = @scandir($current_dir);
            if ($items === false) {
                return;
            }
            
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                
                $item_path = $current_dir . '/' . $item;
                $relative_path = str_replace($root_dir . '/', '', $item_path);
                
                // Controlla se è una directory da escludere
                if (is_dir($item_path)) {
                    $dir_name = strtolower(basename($item_path));
                    if (in_array($dir_name, $exclude_patterns['directories'], true)) {
                        $size = $this->get_directory_size($item_path);
                        if ($wp_filesystem->delete($item_path, true)) {
                            $files_removed++;
                            $size_saved += $size;
                            Logger::log('info', 'Rimossa directory: ' . $relative_path . ' (' . round($size / 1024, 2) . ' KB)');
                        }
                        continue;
                    }
                }
                
                // Controlla se è un file da escludere
                if (is_file($item_path)) {
                    $file_name = basename($item_path);
                    $should_remove = false;
                    
                    // Controlla se è nella lista dei file da mantenere
                    if (in_array($file_name, $exclude_patterns['keep_files'], true)) {
                        $should_remove = false;
                    } else {
                        // Controlla pattern (usa preg_match per compatibilità cross-platform)
                        foreach ($exclude_patterns['file_patterns'] as $pattern) {
                            // Converte pattern wildcard in regex
                            $regex = str_replace(
                                array('\\*', '\\?', '\\[', '\\]'),
                                array('.*', '.', '\\[', '\\]'),
                                preg_quote($pattern, '/')
                            );
                            if (preg_match('/^' . $regex . '$/i', $file_name)) {
                                $should_remove = true;
                                break;
                            }
                        }
                    }
                    
                    if ($should_remove) {
                        $size = filesize($item_path);
                        if ($wp_filesystem->delete($item_path, false)) {
                            $files_removed++;
                            $size_saved += $size;
                            Logger::log('info', 'Rimosso file: ' . $relative_path . ' (' . round($size / 1024, 2) . ' KB)');
                        }
                        continue;
                    }
                }
                
                // Ricorsione per sottodirectory
                if (is_dir($item_path)) {
                    $cleanup_recursive($item_path);
                }
            }
        };
        
        Logger::log('info', 'Pulizia file non necessari dalla directory estratta...');
        $cleanup_recursive($dir);
        
        if ($files_removed > 0) {
            Logger::log('info', 'Pulizia completata: rimossi ' . $files_removed . ' file/directory, risparmiati ' . round($size_saved / 1024 / 1024, 2) . ' MB');
        } else {
            Logger::log('info', 'Nessun file non necessario trovato da rimuovere');
        }
    }
    
    /**
     * Calcola la dimensione totale di una directory
     */
    private function get_directory_size($dir) {
        $size = 0;
        if (!is_dir($dir)) {
            return 0;
        }
        
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($files as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }
    
    /**
     * Esegue l'auto-aggiornamento del plugin stesso
     * Usa un approccio più sicuro per evitare problemi durante l'esecuzione
     */
    private function run_self_update($plugin, $commit_sha) {
        Logger::log('info', 'Inizio auto-aggiornamento del plugin FP Git Updater');
        
        try {
            // Crea un backup delle impostazioni prima dell'aggiornamento
            $backup_manager = SettingsBackup::get_instance();
            $backup_manager->create_backup(false);
            
            // Usa il metodo standard ma con alcune modifiche per la sicurezza
            $result = $this->run_plugin_update($plugin, $commit_sha);
            
            if ($result) {
                Logger::log('success', 'Auto-aggiornamento del plugin FP Git Updater completato con successo');
                
                // Invia notifica speciale per l'auto-aggiornamento
                $this->send_notification(
                    'FP Git Updater - Auto-aggiornamento completato',
                    'Il plugin FP Git Updater è stato aggiornato automaticamente con successo!'
                );
                
                // Aggiungi un flag per indicare che è stato fatto un auto-aggiornamento
                update_option('fp_git_updater_self_updated', array(
                    'timestamp' => current_time('mysql'),
                    'commit_sha' => $commit_sha,
                    'version' => FP_GIT_UPDATER_VERSION
                ));
            }
            
            return $result;
            
        } catch (\Exception $e) {
            Logger::log('error', 'Errore durante auto-aggiornamento: ' . $e->getMessage());
            $this->send_notification(
                'FP Git Updater - Errore auto-aggiornamento',
                'Si è verificato un errore durante l\'auto-aggiornamento: ' . $e->getMessage()
            );
            return false;
        }
    }
    
    /**
     * Conta ricorsivamente il numero di file in una directory
     * 
     * @param string $dir Directory da analizzare
     * @return int Numero di file
     */
    private function count_files_recursive($dir) {
        $count = 0;
        if (!is_dir($dir) || !is_readable($dir)) {
            return 0;
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Copia una directory in modo ottimizzato per plugin grandi
     * Gestisce meglio timeout e memoria per plugin con molti file
     * 
     * @param string $source_dir Directory sorgente
     * @param string $dest_dir Directory destinazione
     * @param \WP_Filesystem_Base $wp_filesystem Istanza WP_Filesystem
     * @param int $total_files Numero totale di file (per logging progressivo)
     * @return true|WP_Error
     */
    private function copy_directory_optimized($source_dir, $dest_dir, $wp_filesystem, $total_files = 0) {
        // Crea la directory di destinazione se non esiste
        if (!$wp_filesystem->exists($dest_dir)) {
            if (!$wp_filesystem->mkdir($dest_dir, FS_CHMOD_DIR)) {
                return new \WP_Error(
                    'mkdir_failed',
                    'Impossibile creare la directory di destinazione',
                    $dest_dir
                );
            }
        }
        
        $copied = 0;
        $last_log_time = time();
        $log_interval = 5; // Log ogni 5 secondi
        
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($source_dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $item) {
                $source_path = $item->getPathname();
                $relative_path = str_replace($source_dir . DIRECTORY_SEPARATOR, '', $source_path);
                $dest_path = $dest_dir . DIRECTORY_SEPARATOR . $relative_path;
                
                if ($item->isDir()) {
                    // Crea la directory se non esiste
                    if (!$wp_filesystem->exists($dest_path)) {
                        if (!$wp_filesystem->mkdir($dest_path, FS_CHMOD_DIR)) {
                            return new \WP_Error(
                                'mkdir_failed',
                                'Impossibile creare la directory: ' . $dest_path,
                                $dest_path
                            );
                        }
                    }
                } elseif ($item->isFile()) {
                    // Copia il file
                    if (!$wp_filesystem->copy($source_path, $dest_path, true, FS_CHMOD_FILE)) {
                        // Retry con chmod
                        $wp_filesystem->chmod($dest_path, FS_CHMOD_FILE);
                        if (!$wp_filesystem->copy($source_path, $dest_path, true, FS_CHMOD_FILE)) {
                            return new \WP_Error(
                                'copy_failed',
                                'Impossibile copiare il file: ' . $dest_path,
                                $dest_path
                            );
                        }
                    }
                    
                    $copied++;
                    
                    // Log progressivo ogni N secondi per plugin grandi
                    if ($total_files > 1000 && (time() - $last_log_time) >= $log_interval) {
                        $percent = round(($copied / $total_files) * 100, 1);
                        Logger::log('info', sprintf(
                            'Copia in corso: %s/%s file (%s%%)',
                            number_format($copied, 0, ',', '.'),
                            number_format($total_files, 0, ',', '.'),
                            $percent
                        ));
                        $last_log_time = time();
                    }
                    
                    // Invalida opcache per file PHP
                    if (pathinfo($dest_path, PATHINFO_EXTENSION) === 'php') {
                        wp_opcache_invalidate($dest_path);
                    }
                }
            }
            
            if ($total_files > 0) {
                Logger::log('info', sprintf(
                    'Copia completata: %s file copiati con successo',
                    number_format($copied, 0, ',', '.')
                ));
            }
            
            return true;
            
        } catch (\Exception $e) {
            return new \WP_Error(
                'copy_exception',
                'Eccezione durante la copia: ' . $e->getMessage(),
                $e->getCode()
            );
        }
    }
}
