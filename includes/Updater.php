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
        add_action('fp_git_updater_cleanup_old_backups', array($this, 'cleanup_old_backups'));
        
        // Schedula controlli periodici se abilitati
        $this->schedule_update_checks();
        $this->schedule_backup_cleanup();
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
     * Schedula la pulizia periodica dei backup vecchi
     */
    private function schedule_backup_cleanup() {
        if (!wp_next_scheduled('fp_git_updater_cleanup_old_backups')) {
            // Esegui pulizia backup ogni giorno alle 3:00 AM
            wp_schedule_event(strtotime('tomorrow 3:00'), 'daily', 'fp_git_updater_cleanup_old_backups');
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
        
        // Invalida la cache della versione GitHub quando viene fatto un controllo manuale
        // così viene recuperata la versione fresca
        delete_transient('fp_git_updater_github_version_' . $plugin_id);
        
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
     * Ottiene la versione del plugin installato
     * Restituisce la versione o stringa vuota se non trovata
     */
    /**
     * Ottiene la versione del plugin installato (pubblico per uso nei template)
     * Restituisce la versione o stringa vuota se non trovata
     * 
     * @param array|string $plugin Array del plugin o ID del plugin
     * @return string Versione del plugin o stringa vuota
     */
    public function get_installed_plugin_version($plugin) {
        // Se viene passato solo l'ID, cerca il plugin nelle impostazioni
        if (is_string($plugin)) {
            $settings = get_option('fp_git_updater_settings', array());
            $plugins = isset($settings['plugins']) ? $settings['plugins'] : array();
            foreach ($plugins as $p) {
                if (isset($p['id']) && $p['id'] === $plugin) {
                    $plugin = $p;
                    break;
                }
            }
            if (is_string($plugin)) {
                return '';
            }
        }
        
        // Prova prima usando get_plugins() di WordPress (più affidabile)
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $all_plugins = get_plugins();
        $plugin_name_lower = strtolower($plugin['name']);
        
        // Cerca il plugin per nome
        foreach ($all_plugins as $plugin_path => $plugin_data) {
            $plugin_file_name = strtolower($plugin_data['Name']);
            if ($plugin_file_name === $plugin_name_lower || 
                stripos($plugin_file_name, $plugin_name_lower) !== false ||
                stripos($plugin_name_lower, $plugin_file_name) !== false) {
                if (!empty($plugin_data['Version'])) {
                    return $plugin_data['Version'];
                }
            }
        }
        // Determina lo slug del plugin
        $plugin_slug = !empty($plugin['plugin_slug']) ? $plugin['plugin_slug'] : '';
        
        $repo_name = '';
        
        if (empty($plugin_slug)) {
            // Prova a dedurre lo slug dal repository
            $repo_parts = explode('/', $plugin['github_repo']);
            if (count($repo_parts) === 2) {
                $repo_name = $repo_parts[1];
                $plugin_slug = strtolower($repo_name);
                
                // Prima prova direttamente pattern comuni (-1, -2, ecc.)
                $plugins_dir = WP_PLUGIN_DIR;
                $common_patterns = array(
                    $repo_name,           // Nome esatto
                    $repo_name . '-1',    // Pattern comune
                    $repo_name . '-2',    // Pattern comune
                    strtolower($repo_name), // Lowercase
                    strtolower($repo_name) . '-1',
                );
                
                foreach ($common_patterns as $pattern) {
                    $test_dir = $plugins_dir . '/' . $pattern;
                    if (is_dir($test_dir)) {
                        $test_main_file = $this->find_plugin_main_file($test_dir);
                        if ($test_main_file) {
                            $file_data = @file_get_contents($test_main_file, false, null, 0, 8192);
                            if ($file_data && (stripos($file_data, $plugin['name']) !== false || 
                                preg_match('/Plugin Name:/i', $file_data))) {
                                $plugin_slug = $pattern;
                                break;
                            }
                        }
                    }
                }
                
                // Se non trovato, cerca tra tutte le cartelle dei plugin
                if ((empty($plugin_slug) || strtolower($plugin_slug) === strtolower($repo_name)) && is_dir($plugins_dir)) {
                    $dirs = @scandir($plugins_dir);
                    if ($dirs !== false) {
                        foreach ($dirs as $dir) {
                            if ($dir === '.' || $dir === '..' || !is_dir($plugins_dir . '/' . $dir)) {
                                continue;
                            }
                            
                            // Confronta con possibili variazioni
                            $dir_lower = strtolower($dir);
                            
                            // Verifica se la directory corrisponde al repository (varie forme)
                            // Prova anche pattern comuni come -1, -2, ecc. alla fine del nome
                            $matches_pattern = (
                                $dir_lower === strtolower($repo_name) || 
                                $dir_lower === strtolower($repo_name . '-1') ||
                                $dir_lower === strtolower($repo_name . '-2') ||
                                stripos($dir, $repo_name) !== false ||
                                stripos($repo_name, basename($dir)) !== false ||
                                $dir_lower === $plugin_slug ||
                                stripos($dir, str_replace('-', '', $repo_name)) !== false ||
                                // Pattern: repo_name-1, repo_name-2, ecc.
                                preg_match('/^' . preg_quote(strtolower($repo_name), '/') . '-\d+$/i', $dir_lower) ||
                                // Pattern: contiene il nome base senza trattini
                                stripos(str_replace('-', '', $dir), str_replace('-', '', $repo_name)) !== false
                            );
                            
                            if ($matches_pattern) {
                                // Verifica che sia davvero il plugin cercato usando find_plugin_main_file
                                // Questo è più affidabile perché cerca il file principale in qualsiasi forma
                                $test_main_file = $this->find_plugin_main_file($plugins_dir . '/' . $dir);
                                if ($test_main_file) {
                                    $file_data = @file_get_contents($test_main_file, false, null, 0, 8192);
                                    if ($file_data) {
                                        // Verifica che contenga il nome del plugin o "Plugin Name:"
                                        if (stripos($file_data, $plugin['name']) !== false || 
                                            preg_match('/Plugin Name:\s*' . preg_quote($plugin['name'], '/') . '/i', $file_data)) {
                                            $plugin_slug = $dir;
                                            break;
                                        }
                                    }
                                }
                                
                                // Fallback: verifica anche con file standard
                                $test_file = $plugins_dir . '/' . $dir . '/' . $dir . '.php';
                                if (file_exists($test_file)) {
                                    $file_data = @file_get_contents($test_file, false, null, 0, 8192);
                                    if ($file_data && (stripos($file_data, $plugin['name']) !== false || 
                                        preg_match('/Plugin Name:/i', $file_data))) {
                                        $plugin_slug = $dir;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        if (empty($plugin_slug)) {
            return '';
        }
        
        // Cerca il file principale del plugin (prova con vari possibili slug)
        $possible_dirs = array($plugin_slug);
        
        // Se abbiamo il repo_name, prova anche variazioni comuni
        if (!empty($repo_name)) {
            if (strtolower($plugin_slug) !== strtolower($repo_name)) {
                $possible_dirs[] = $repo_name; // Nome originale
            }
            // Pattern comune: Nome-Plugin-1, Nome-Plugin-2, ecc.
            $possible_dirs[] = $repo_name . '-1';
            $possible_dirs[] = $repo_name . '-2';
            // Se lo slug contiene già un numero (es: FP-Privacy-1), lo abbiamo già
            // Altrimenti aggiungi anche versioni alternative
            if (!preg_match('/-\d+$/', $plugin_slug) && !preg_match('/-\d+$/', $repo_name)) {
                $possible_dirs[] = $repo_name . '-1';
            }
        }
        
        $main_file = false;
        foreach ($possible_dirs as $dir) {
            $plugin_dir = WP_PLUGIN_DIR . '/' . $dir;
            if (!is_dir($plugin_dir)) {
                continue;
            }
            
            $main_file = $this->find_plugin_main_file($plugin_dir);
            
            if ($main_file) {
                break;
            }
            
            // Prova anche con nomi di file comuni
            $possible_main_files = array(
                $plugin_dir . '/' . basename($dir) . '.php',
                $plugin_dir . '/' . strtolower(basename($dir)) . '.php',
                $plugin_dir . '/' . str_replace('-', '-', basename($dir)) . '.php',
            );
            
            foreach ($possible_main_files as $possible_main_file) {
                if (file_exists($possible_main_file)) {
                    $file_data = @file_get_contents($possible_main_file, false, null, 0, 8192);
                    if ($file_data && preg_match('/Plugin Name:/i', $file_data)) {
                        $main_file = $possible_main_file;
                        break 2;
                    }
                }
            }
        }
        
        if (!$main_file) {
            return '';
        }
        
        // Usa get_plugin_data di WordPress se disponibile
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $plugin_data = get_plugin_data($main_file, false, false);
        
        if (!empty($plugin_data['Version'])) {
            return $plugin_data['Version'];
        }
        
        // Fallback: leggi direttamente dall'header
        $file_data = @file_get_contents($main_file, false, null, 0, 8192);
        if ($file_data && preg_match('/Version:\s*([^\n\r]+)/i', $file_data, $matches)) {
            return trim($matches[1]);
        }
        
        return '';
    }
    
    /**
     * Ottiene la versione del plugin dal repository GitHub (pubblico per uso nei template)
     * Restituisce la versione o stringa vuota se non trovata
     * 
     * @param array $plugin Array del plugin
     * @param string|null $commit_sha SHA del commit specifico (opzionale)
     * @return string Versione del plugin su GitHub o stringa vuota
     */
    public function get_github_plugin_version($plugin, $commit_sha = null) {
        $repo = $plugin['github_repo'];
        $branch = isset($plugin['branch']) ? $plugin['branch'] : 'main';
        
        // Se non abbiamo il commit SHA, otteniamo l'ultimo commit
        if (empty($commit_sha)) {
            $commit_result = $this->get_latest_commit($plugin);
            if (is_wp_error($commit_result)) {
                return '';
            }
            $commit_sha = $commit_result;
        }
        
        // Determina lo slug del plugin per trovare il file principale
        $plugin_slug = !empty($plugin['plugin_slug']) ? $plugin['plugin_slug'] : '';
        if (empty($plugin_slug)) {
            $repo_parts = explode('/', $repo);
            if (count($repo_parts) === 2) {
                $plugin_slug = strtolower($repo_parts[1]);
            }
        }
        
        if (empty($plugin_slug)) {
            return '';
        }
        
        // Prova prima con lo slug come nome file
        $possible_files = array(
            $plugin_slug . '.php',
            basename($repo) . '.php'
        );
        
        // Aggiungi anche il nome del repository originale
        $repo_parts = explode('/', $repo);
        if (count($repo_parts) === 2) {
            $possible_files[] = $repo_parts[1] . '.php';
        }
        
        // Prova a ottenere il contenuto del file principale
        foreach ($possible_files as $file_path) {
            $api_url = "https://api.github.com/repos/{$repo}/contents/{$file_path}";
            if (!empty($commit_sha)) {
                $api_url .= "?ref={$commit_sha}";
            } else {
                $api_url .= "?ref={$branch}";
            }
            
            $args = array(
                'headers' => array(
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'FP-Updater/' . FP_GIT_UPDATER_VERSION,
                ),
                'timeout' => 30,
            );
            
            // Usa token globale se disponibile
            $settings = get_option('fp_git_updater_settings', array());
            if (!empty($settings['global_github_token'])) {
                $encryption = Encryption::get_instance();
                $token = $encryption->decrypt($settings['global_github_token']);
                if ($token !== false && !empty($token)) {
                    $args['headers']['Authorization'] = 'token ' . $token;
                }
            }
            
            $response = wp_remote_get($api_url, $args);
            
            if (!is_wp_error($response)) {
                $code = wp_remote_retrieve_response_code($response);
                if ($code === 200) {
                    $body = wp_remote_retrieve_body($response);
                    $data = json_decode($body, true);
                    
                    if (isset($data['content']) && isset($data['encoding'])) {
                        // Decodifica il contenuto (base64)
                        $content = base64_decode($data['content']);
                        
                        // Leggi solo le prime 8KB per l'header
                        $header_content = substr($content, 0, 8192);
                        
                        // Cerca la versione nell'header
                        if (preg_match('/Version:\s*([^\n\r]+)/i', $header_content, $matches)) {
                            return trim($matches[1]);
                        }
                    }
                }
            }
        }
        
        return '';
    }
    
    /**
     * Controlla se ci sono aggiornamenti per un plugin specifico
     */
    private function check_plugin_for_updates($plugin) {
        // Verifica che ci sia almeno una sorgente configurata (repo o zip_url)
        $has_repo = !empty($plugin['github_repo']);
        $has_zip_url = !empty($plugin['zip_url']);
        
        if (!$has_repo && !$has_zip_url) {
            Logger::log('warning', 'Plugin ' . $plugin['name'] . ' non ha sorgente aggiornamento configurata (repo o zip_url richiesto)', array(
                'plugin_id' => isset($plugin['id']) ? $plugin['id'] : 'unknown'
            ));
            return false;
        }
        
        // Se ha solo zip_url senza repo, non possiamo controllare i commit
        if (!$has_repo) {
            Logger::log('info', 'Plugin ' . $plugin['name'] . ' usa solo ZIP URL, controllo commit non disponibile', array(
                'plugin_id' => isset($plugin['id']) ? $plugin['id'] : 'unknown',
                'zip_url' => $has_zip_url ? 'configured' : 'missing'
            ));
            return false;
        }
        
        // Validazione formato repository
        if (!preg_match('/^[a-zA-Z0-9_.-]+\/[a-zA-Z0-9_.-]+$/', $plugin['github_repo'])) {
            Logger::log('error', 'Formato repository non valido per ' . $plugin['name'] . ': ' . $plugin['github_repo'], array(
                'plugin_id' => isset($plugin['id']) ? $plugin['id'] : 'unknown',
                'expected_format' => 'username/repository'
            ));
            return false;
        }
        
        Logger::log('info', 'Controllo aggiornamenti per: ' . $plugin['name'], array(
            'plugin_id' => isset($plugin['id']) ? $plugin['id'] : 'unknown',
            'repository' => $plugin['github_repo'],
            'branch' => isset($plugin['branch']) ? $plugin['branch'] : 'main'
        ));
        
        // Ottieni versione installata corrente
        $current_version = $this->get_installed_plugin_version($plugin);
        
        $latest_commit = $this->get_latest_commit($plugin);
        
        if (is_wp_error($latest_commit)) {
            $error_code = $latest_commit->get_error_code();
            $error_message = $latest_commit->get_error_message();
            $error_data = $latest_commit->get_error_data();
            
            Logger::log('error', 'Errore nel controllo aggiornamenti per ' . $plugin['name'] . ': ' . $error_message, array(
                'plugin_id' => isset($plugin['id']) ? $plugin['id'] : 'unknown',
                'error_code' => $error_code,
                'error_data' => $error_data,
                'repository' => $plugin['github_repo']
            ));
            return false;
        }
        
        $current_commit = get_option('fp_git_updater_current_commit_' . $plugin['id'], '');
        
        if ($latest_commit !== $current_commit) {
            // Ottieni versione disponibile su GitHub
            $available_version = $this->get_github_plugin_version($plugin, $latest_commit);
            
            // Aggiorna la cache della versione GitHub (validità 5 minuti)
            if (!empty($available_version)) {
                set_transient('fp_git_updater_github_version_' . $plugin['id'], $available_version, 300);
            }
            
            Logger::log('info', 'Nuovo aggiornamento disponibile per ' . $plugin['name'], array(
                'plugin_id' => isset($plugin['id']) ? $plugin['id'] : 'unknown',
                'current_commit' => $current_commit ? substr($current_commit, 0, 7) : 'none',
                'latest_commit' => substr($latest_commit, 0, 7),
                'current_version' => $current_version,
                'available_version' => $available_version,
                'repository' => $plugin['github_repo']
            ));
            
            // Salva versione corrente se non già salvata
            if (!empty($current_version)) {
                update_option('fp_git_updater_current_version_' . $plugin['id'], $current_version);
            }
            
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
                'current_version' => $current_version,
                'available_version' => $available_version,
            ));
            
            // Se l'aggiornamento automatico è abilitato, eseguilo
            $settings = get_option('fp_git_updater_settings');
            if (isset($settings['auto_update']) && $settings['auto_update']) {
                Logger::log('info', 'Aggiornamento automatico in corso per ' . $plugin['name'], array(
                    'plugin_id' => isset($plugin['id']) ? $plugin['id'] : 'unknown',
                    'commit' => $commit_short
                ));
                $this->run_update($latest_commit, $plugin);
            } else {
                Logger::log('info', 'Aggiornamento disponibile per ' . $plugin['name'] . ' ma installazione manuale richiesta (auto_update disabilitato)', array(
                    'plugin_id' => isset($plugin['id']) ? $plugin['id'] : 'unknown',
                    'commit' => $commit_short
                ));
            }
            
            return true;
        }
        
        Logger::log('info', 'Nessun aggiornamento disponibile per ' . $plugin['name'], array(
            'plugin_id' => isset($plugin['id']) ? $plugin['id'] : 'unknown',
            'current_commit' => $current_commit ? substr($current_commit, 0, 7) : 'none'
        ));
        
        return false;
    }
    
    /**
     * Valida il formato di un repository GitHub
     * 
     * @param string $repo Repository in formato username/repository
     * @return bool|WP_Error True se valido, WP_Error altrimenti
     */
    private function validate_repository($repo) {
        if (empty($repo)) {
            return new WP_Error('empty_repo', 'Repository non può essere vuoto');
        }
        
        if (!preg_match('/^[a-zA-Z0-9_.-]+\/[a-zA-Z0-9_.-]+$/', $repo)) {
            return new WP_Error('invalid_format', 'Formato repository non valido. Usa: username/repository');
        }
        
        // Verifica lunghezza massima (GitHub limita a 100 caratteri per username + repo)
        if (strlen($repo) > 100) {
            return new WP_Error('too_long', 'Repository troppo lungo (max 100 caratteri)');
        }
        
        return true;
    }
    
    /**
     * Valida il nome di un branch Git
     * 
     * @param string $branch Nome del branch
     * @return bool|WP_Error True se valido, WP_Error altrimenti
     */
    private function validate_branch($branch) {
        if (empty($branch)) {
            return new WP_Error('empty_branch', 'Branch non può essere vuoto');
        }
        
        // Branch Git possono contenere lettere, numeri, _, -, /, .
        // Ma non possono iniziare/finire con / o .
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.\/-]*[a-zA-Z0-9]$|^[a-zA-Z0-9]$/', $branch)) {
            return new WP_Error('invalid_branch', 'Formato branch non valido');
        }
        
        // Verifica lunghezza massima (Git limita a 255 caratteri)
        if (strlen($branch) > 255) {
            return new WP_Error('too_long', 'Branch troppo lungo (max 255 caratteri)');
        }
        
        return true;
    }
    
    /**
     * Ottieni l'ultimo commit dal repository GitHub per un plugin specifico
     */
    private function get_latest_commit($plugin) {
        $repo = $plugin['github_repo'];
        $branch = isset($plugin['branch']) ? $plugin['branch'] : 'main';
        
        // Valida repository
        $repo_validation = $this->validate_repository($repo);
        if (is_wp_error($repo_validation)) {
            return $repo_validation;
        }
        
        // Valida branch
        $branch_validation = $this->validate_branch($branch);
        if (is_wp_error($branch_validation)) {
            return $branch_validation;
        }
        
        // URL API GitHub per ottenere l'ultimo commit
        $api_url = "https://api.github.com/repos/{$repo}/commits/{$branch}";
        
        $args = array(
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'FP-Updater/' . FP_GIT_UPDATER_VERSION,
            ),
            'timeout' => 30,
        );
        
        // Usa token globale se disponibile
        $settings = get_option('fp_git_updater_settings', array());
        if (!empty($settings['global_github_token'])) {
            $encryption = Encryption::get_instance();
            $token = $encryption->decrypt($settings['global_github_token']);
            
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

            // Supporto modalità semplice: URL ZIP pubblico opzionale
            $zip_url = isset($plugin['zip_url']) ? trim($plugin['zip_url']) : '';

            if (empty($repo) && empty($zip_url)) {
                throw new Exception('Sorgente aggiornamento non configurata per ' . $plugin['name'] . ' (repo o URL ZIP richiesto)');
            }

            if (!empty($zip_url) && filter_var($zip_url, FILTER_VALIDATE_URL)) {
                $download_url = $zip_url;
                Logger::log('info', 'Modalità ZIP pubblico attiva per: ' . $plugin['name']);
                $args = array(
                    'timeout' => 300,
                    'redirection' => 5,
                    'headers' => array(
                        'User-Agent' => 'FP-Updater/' . FP_GIT_UPDATER_VERSION,
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
                        'User-Agent' => 'FP-Updater/' . FP_GIT_UPDATER_VERSION,
                    ),
                );

                // Ottieni token globale
                $settings = get_option('fp_git_updater_settings', array());
                $has_token = false;
                
                if (!empty($settings['global_github_token'])) {
                    $encryption = Encryption::get_instance();
                    $token = $encryption->decrypt($settings['global_github_token']);
                    if ($token !== false && !empty($token)) {
                        // Usa API zipball con autenticazione per repository privati
                        $download_url = "https://api.github.com/repos/{$repo}/zipball/{$branch}";
                        $args['headers']['Accept'] = 'application/vnd.github.v3+json';
                        $args['headers']['Authorization'] = 'token ' . $token;
                        $has_token = true;
                        Logger::log('info', 'Usando API GitHub con token globale per repository', array(
                            'plugin_id' => isset($plugin['id']) ? $plugin['id'] : 'unknown',
                            'repository' => $repo,
                            'branch' => $branch,
                            'token_prefix' => substr($token, 0, 4) . '...' // Log solo prefisso per sicurezza
                        ));
                    }
                }
                
                if (!$has_token) {
                    // Repository pubblico: usa URL diretto senza API (no autenticazione richiesta)
                    $download_url = "https://github.com/{$repo}/archive/refs/heads/{$branch}.zip";
                    Logger::log('info', 'Repository pubblico, usando URL diretto GitHub (nessun token)', array(
                        'plugin_id' => isset($plugin['id']) ? $plugin['id'] : 'unknown',
                        'repository' => $repo
                    ));
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
                $error_code = $response->get_error_code();
                $error_message = $response->get_error_message();
                $error_data = $response->get_error_data();
                
                @unlink($temp_file);
                Logger::log('error', 'Errore download: ' . $error_message, array(
                    'plugin_id' => isset($plugin['id']) ? $plugin['id'] : 'unknown',
                    'plugin_name' => isset($plugin['name']) ? $plugin['name'] : 'unknown',
                    'error_code' => $error_code,
                    'error_data' => $error_data,
                    'download_url' => $download_url
                ));
                $this->send_notification('Errore aggiornamento', 'Errore durante il download: ' . $error_message);
                delete_transient($lock_key);
                return false;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                $response_message = wp_remote_retrieve_response_message($response);
                $response_body = wp_remote_retrieve_body($response);
                
                // Se errore 401 e abbiamo un token globale, prova fallback a URL pubblico
                // (potrebbe essere un repository pubblico con token non valido)
                $settings = get_option('fp_git_updater_settings', array());
                $has_global_token = !empty($settings['global_github_token']);
                
                if ($response_code === 401 && $has_global_token && !empty($repo)) {
                    Logger::log('warning', 'Token GitHub non valido o scaduto (401). Tentativo fallback a URL pubblico...', array(
                        'plugin_id' => isset($plugin['id']) ? $plugin['id'] : 'unknown',
                        'plugin_name' => isset($plugin['name']) ? $plugin['name'] : 'unknown',
                        'repository' => $repo
                    ));
                    
                    @unlink($temp_file);
                    
                    // Prova con URL pubblico GitHub (senza autenticazione)
                    $public_download_url = "https://github.com/{$repo}/archive/refs/heads/{$branch}.zip";
                    $public_args = array(
                        'timeout' => 300,
                        'redirection' => 5,
                        'headers' => array(
                            'User-Agent' => 'FP-Updater/' . FP_GIT_UPDATER_VERSION,
                        ),
                    );
                    
                    Logger::log('info', 'Tentativo download da URL pubblico: ' . $public_download_url);
                    
                    // Usa download_url() nativo WordPress per repository pubblici
                    $downloaded = download_url($public_download_url, 300);
                    
                    if (is_wp_error($downloaded)) {
                        Logger::log('error', 'Fallback a URL pubblico fallito: ' . $downloaded->get_error_message(), array(
                            'plugin_id' => isset($plugin['id']) ? $plugin['id'] : 'unknown',
                            'plugin_name' => isset($plugin['name']) ? $plugin['name'] : 'unknown'
                        ));
                        
                        // Se anche il fallback fallisce, potrebbe essere un repository privato
                        $user_message = 'Errore autenticazione (401). Il token GitHub potrebbe essere scaduto o non valido. ';
                        $user_message .= 'Se il repository è privato, verifica che il token abbia i permessi necessari.';
                        $this->send_notification('Errore aggiornamento', $user_message);
                        delete_transient($lock_key);
                        return false;
                    }
                    
                    // download_url() restituisce il path del file temporaneo, rinominiamolo
                    if (!@rename($downloaded, $temp_file)) {
                        if (!@copy($downloaded, $temp_file)) {
                            @unlink($downloaded);
                            Logger::log('error', 'Errore nel copiare il file scaricato dal fallback');
                            $this->send_notification('Errore aggiornamento', 'Errore nel copiare il file scaricato');
                            delete_transient($lock_key);
                            return false;
                        }
                        @unlink($downloaded);
                    }
                    
                    Logger::log('info', 'Download completato tramite fallback URL pubblico (token non valido)');
                    // Continua con l'estrazione del file
                } else {
                    // Altri errori HTTP o 401 senza possibilità di fallback
                    @unlink($temp_file);
                    
                    // Messaggio di errore più dettagliato
                    $error_details = 'HTTP ' . $response_code;
                    if ($response_message) {
                        $error_details .= ' - ' . $response_message;
                    }
                    
                    Logger::log('error', 'Errore download: ' . $error_details, array(
                        'plugin_id' => isset($plugin['id']) ? $plugin['id'] : 'unknown',
                        'plugin_name' => isset($plugin['name']) ? $plugin['name'] : 'unknown',
                        'response_code' => $response_code,
                        'response_message' => $response_message,
                        'response_body_preview' => substr($response_body, 0, 200),
                        'download_url' => $download_url
                    ));
                    
                    $user_message = 'Errore HTTP durante il download: ' . $error_details;
                    if ($response_code === 404) {
                        $user_message .= '. Repository o branch non trovato.';
                    } elseif ($response_code === 401) {
                        $user_message .= '. Autenticazione fallita. Verifica il token GitHub. Se il repository è pubblico, rimuovi il token dalla configurazione.';
                    } elseif ($response_code === 403) {
                        $user_message .= '. Accesso negato. Verifica i permessi del repository o il rate limit di GitHub.';
                    }
                    
                    $this->send_notification('Errore aggiornamento', $user_message);
                    delete_transient($lock_key);
                    return false;
                }
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
            Logger::log('info', 'Auto-aggiornamento del plugin FP Updater in corso...');
            
            // Per l'auto-aggiornamento, usiamo un approccio più sicuro
            return $this->run_self_update($plugin, $commit_sha);
        }
        
        // Backup della versione attuale (solo se la directory esiste)
        $backup_dir = null;
        if (file_exists($plugin_dir) && is_dir($plugin_dir)) {
            // Prima di creare un nuovo backup, pulisci i backup vecchi per evitare saturazione
            $this->cleanup_old_backups(true);
            
            // Verifica spazio disco disponibile prima di creare backup
            $plugin_size = $this->get_directory_size($plugin_dir);
            $available_space = $this->get_available_disk_space(WP_CONTENT_DIR);
            
            // Richiedi almeno 2x lo spazio del plugin per sicurezza (backup + nuovo)
            $required_space = $plugin_size * 2;
            
            if ($available_space < $required_space) {
                Logger::log('warning', 'Spazio disco insufficiente per backup. Spazio disponibile: ' . $this->format_bytes($available_space) . ', richiesto: ' . $this->format_bytes($required_space), array(
                    'plugin_id' => isset($plugin['id']) ? $plugin['id'] : 'unknown',
                    'plugin_name' => isset($plugin['name']) ? $plugin['name'] : 'unknown',
                    'plugin_size' => $this->format_bytes($plugin_size)
                ));
                
                // Prova a pulire più backup vecchi
                $this->cleanup_old_backups(false, 10); // Elimina fino a 10 backup vecchi
                
                // Ricontrolla spazio
                $available_space = $this->get_available_disk_space(WP_CONTENT_DIR);
                if ($available_space < $required_space) {
                    Logger::log('error', 'Spazio disco ancora insufficiente dopo pulizia. Backup saltato.', array(
                        'plugin_id' => isset($plugin['id']) ? $plugin['id'] : 'unknown',
                        'available_space' => $this->format_bytes($available_space),
                        'required_space' => $this->format_bytes($required_space)
                    ));
                    // Continua senza backup (rischioso ma meglio che bloccare tutto)
                    $this->send_notification('Avviso aggiornamento', 'Spazio disco insufficiente. Aggiornamento eseguito senza backup. Si consiglia di liberare spazio.');
                }
            }
            
            Logger::log('info', 'Creazione backup...', array(
                'plugin_id' => isset($plugin['id']) ? $plugin['id'] : 'unknown',
                'plugin_size' => $this->format_bytes($plugin_size),
                'available_space' => $this->format_bytes($available_space)
            ));
            
            $backup_dir = WP_CONTENT_DIR . '/upgrade/fp-git-updater-backup-' . time() . '-' . uniqid();
            
            // Usa rename() nativo che è più affidabile per operazioni atomiche
            if (!@rename($plugin_dir, $backup_dir)) {
                Logger::log('error', 'Impossibile creare backup: verifica i permessi della directory', array(
                    'plugin_id' => isset($plugin['id']) ? $plugin['id'] : 'unknown',
                    'plugin_dir' => $plugin_dir,
                    'backup_dir' => $backup_dir
                ));
                $wp_filesystem->delete($temp_extract_dir, true);
                $this->send_notification('Errore aggiornamento', 'Impossibile creare backup della versione corrente. Verifica i permessi.');
                delete_transient($lock_key);
                return false;
            }
            Logger::log('info', 'Backup creato con successo', array(
                'plugin_id' => isset($plugin['id']) ? $plugin['id'] : 'unknown',
                'backup_dir' => basename($backup_dir)
            ));
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
            
            // Salva anche la nuova versione installata
            $new_version = $this->get_installed_plugin_version($plugin);
            if (!empty($new_version)) {
                update_option('fp_git_updater_current_version_' . $plugin['id'], $new_version);
                // Aggiorna anche la cache della versione GitHub (ora dovrebbe corrispondere)
                set_transient('fp_git_updater_github_version_' . $plugin['id'], $new_version, 300);
            }
        } else if (!empty($plugin['github_repo'])) {
            // Solo se abbiamo un repo configurato
            $latest_commit = $this->get_latest_commit($plugin);
            if (!is_wp_error($latest_commit)) {
                update_option('fp_git_updater_current_commit_' . $plugin['id'], $latest_commit);
                
                // Salva anche la nuova versione installata
                $new_version = $this->get_installed_plugin_version($plugin);
                if (!empty($new_version)) {
                    update_option('fp_git_updater_current_version_' . $plugin['id'], $new_version);
                }
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
        
        // Il backup verrà gestito dal sistema di pulizia automatica
        // Non programmiamo più la pulizia singola, ma lasciamo che il sistema di pulizia periodica se ne occupi
        // Questo evita di avere troppi eventi schedulati
        if ($backup_dir && file_exists($backup_dir)) {
            Logger::log('info', 'Backup mantenuto per pulizia automatica', array(
                'plugin_id' => isset($plugin['id']) ? $plugin['id'] : 'unknown',
                'backup_dir' => basename($backup_dir)
            ));
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
            
            $backup_size = $this->get_directory_size($backup_dir);
            $wp_filesystem->delete($backup_dir, true);
            Logger::log('info', 'Backup eliminato: ' . basename($backup_dir), array(
                'backup_size' => $this->format_bytes($backup_size)
            ));
        }
    }
    
    /**
     * Pulisce i backup vecchi in base ai limiti configurati
     * 
     * @param bool $respect_max_limit Se true, rispetta il limite massimo di backup
     * @param int $force_delete_count Se > 0, forza l'eliminazione di questo numero di backup più vecchi
     * @return int Numero di backup eliminati
     */
    public function cleanup_old_backups($respect_max_limit = true, $force_delete_count = 0) {
        $upgrade_dir = WP_CONTENT_DIR . '/upgrade';
        
        if (!is_dir($upgrade_dir)) {
            return 0;
        }
        
        // Trova tutti i backup
        $backup_pattern = $upgrade_dir . '/fp-git-updater-backup-*';
        $backups = glob($backup_pattern, GLOB_ONLYDIR);
        
        if (empty($backups)) {
            return 0;
        }
        
        // Ordina per data di creazione (più vecchi prima)
        usort($backups, function($a, $b) {
            $time_a = filemtime($a);
            $time_b = filemtime($b);
            return $time_a - $time_b;
        });
        
        $deleted_count = 0;
        $settings = get_option('fp_git_updater_settings');
        
        // Limite massimo di backup (default: 5)
        $max_backups = isset($settings['max_backups']) ? intval($settings['max_backups']) : 5;
        if ($max_backups < 1) {
            $max_backups = 5; // Minimo 1 backup
        }
        if ($max_backups > 20) {
            $max_backups = 20; // Massimo 20 backup
        }
        
        // Età massima backup in giorni (default: 7)
        $max_backup_age_days = isset($settings['max_backup_age_days']) ? intval($settings['max_backup_age_days']) : 7;
        if ($max_backup_age_days < 1) {
            $max_backup_age_days = 7;
        }
        $max_backup_age_seconds = $max_backup_age_days * DAY_IN_SECONDS;
        $cutoff_time = time() - $max_backup_age_seconds;
        
        global $wp_filesystem;
        if (!$wp_filesystem) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        
        // Se force_delete_count > 0, elimina i più vecchi
        if ($force_delete_count > 0) {
            $to_delete = array_slice($backups, 0, min($force_delete_count, count($backups)));
            foreach ($to_delete as $backup) {
                $backup_size = $this->get_directory_size($backup);
                if ($wp_filesystem->delete($backup, true)) {
                    $deleted_count++;
                    Logger::log('info', 'Backup vecchio eliminato (pulizia forzata): ' . basename($backup), array(
                        'backup_size' => $this->format_bytes($backup_size),
                        'backup_age_days' => round((time() - filemtime($backup)) / DAY_IN_SECONDS, 1)
                    ));
                }
            }
            return $deleted_count;
        }
        
        // Elimina backup più vecchi del limite di età
        foreach ($backups as $backup) {
            $backup_mtime = filemtime($backup);
            if ($backup_mtime < $cutoff_time) {
                $backup_size = $this->get_directory_size($backup);
                if ($wp_filesystem->delete($backup, true)) {
                    $deleted_count++;
                    Logger::log('info', 'Backup vecchio eliminato (superato limite età): ' . basename($backup), array(
                        'backup_size' => $this->format_bytes($backup_size),
                        'backup_age_days' => round((time() - $backup_mtime) / DAY_IN_SECONDS, 1),
                        'max_age_days' => $max_backup_age_days
                    ));
                }
            }
        }
        
        // Se rispettiamo il limite massimo, elimina i backup più vecchi oltre il limite
        if ($respect_max_limit) {
            $remaining_backups = array_filter($backups, function($backup) use ($cutoff_time) {
                return filemtime($backup) >= $cutoff_time;
            });
            
            if (count($remaining_backups) > $max_backups) {
                // Ordina di nuovo i backup rimanenti
                usort($remaining_backups, function($a, $b) {
                    return filemtime($a) - filemtime($b);
                });
                
                // Elimina i più vecchi oltre il limite
                $excess_count = count($remaining_backups) - $max_backups;
                $to_delete = array_slice($remaining_backups, 0, $excess_count);
                
                foreach ($to_delete as $backup) {
                    $backup_size = $this->get_directory_size($backup);
                    if ($wp_filesystem->delete($backup, true)) {
                        $deleted_count++;
                        Logger::log('info', 'Backup eliminato (superato limite quantità): ' . basename($backup), array(
                            'backup_size' => $this->format_bytes($backup_size),
                            'current_count' => count($remaining_backups),
                            'max_backups' => $max_backups
                        ));
                    }
                }
            }
        }
        
        if ($deleted_count > 0) {
            Logger::log('info', 'Pulizia backup completata: ' . $deleted_count . ' backup eliminati', array(
                'max_backups' => $max_backups,
                'max_age_days' => $max_backup_age_days
            ));
        }
        
        return $deleted_count;
    }
    
    /**
     * Ottiene lo spazio disco disponibile
     * 
     * @param string $path Path della directory da controllare
     * @return int Spazio disponibile in bytes, -1 se non disponibile
     */
    private function get_available_disk_space($path) {
        if (!function_exists('disk_free_space')) {
            return -1;
        }
        
        $free_space = @disk_free_space($path);
        return $free_space !== false ? $free_space : -1;
    }
    
    /**
     * Formatta bytes in formato leggibile
     * 
     * @param int $bytes
     * @return string
     */
    private function format_bytes($bytes) {
        if ($bytes < 0) {
            return 'N/A';
        }
        
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Ottiene statistiche sui backup
     * 
     * @return array Array con statistiche backup
     */
    public function get_backup_stats() {
        $upgrade_dir = WP_CONTENT_DIR . '/upgrade';
        
        if (!is_dir($upgrade_dir)) {
            return array(
                'total_backups' => 0,
                'total_size' => 0,
                'total_size_formatted' => '0 B',
                'oldest_backup' => null,
                'newest_backup' => null
            );
        }
        
        $backup_pattern = $upgrade_dir . '/fp-git-updater-backup-*';
        $backups = glob($backup_pattern, GLOB_ONLYDIR);
        
        if (empty($backups)) {
            return array(
                'total_backups' => 0,
                'total_size' => 0,
                'total_size_formatted' => '0 B',
                'oldest_backup' => null,
                'newest_backup' => null
            );
        }
        
        $total_size = 0;
        $oldest_time = time();
        $newest_time = 0;
        
        foreach ($backups as $backup) {
            $size = $this->get_directory_size($backup);
            $total_size += $size;
            
            $mtime = filemtime($backup);
            if ($mtime < $oldest_time) {
                $oldest_time = $mtime;
            }
            if ($mtime > $newest_time) {
                $newest_time = $mtime;
            }
        }
        
        return array(
            'total_backups' => count($backups),
            'total_size' => $total_size,
            'total_size_formatted' => $this->format_bytes($total_size),
            'oldest_backup' => $oldest_time < time() ? date('Y-m-d H:i:s', $oldest_time) : null,
            'newest_backup' => $newest_time > 0 ? date('Y-m-d H:i:s', $newest_time) : null,
            'available_space' => $this->get_available_disk_space(WP_CONTENT_DIR),
            'available_space_formatted' => $this->format_bytes($this->get_available_disk_space(WP_CONTENT_DIR))
        );
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
        Logger::log('info', 'Inizio auto-aggiornamento del plugin FP Updater');
        
        try {
            // Crea un backup delle impostazioni prima dell'aggiornamento
            $backup_manager = SettingsBackup::get_instance();
            $backup_manager->create_backup(false);
            
            // Usa il metodo standard ma con alcune modifiche per la sicurezza
            $result = $this->run_plugin_update($plugin, $commit_sha);
            
            if ($result) {
                Logger::log('success', 'Auto-aggiornamento del plugin FP Updater completato con successo');
                
                // Invia notifica speciale per l'auto-aggiornamento
                $this->send_notification(
                    'FP Updater - Auto-aggiornamento completato',
                    'Il plugin FP Updater è stato aggiornato automaticamente con successo!'
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
                'FP Updater - Errore auto-aggiornamento',
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
