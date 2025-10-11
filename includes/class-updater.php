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
        add_action('fp_git_updater_run_update', array($this, 'run_update'));
        add_action('fp_git_updater_check_update', array($this, 'check_for_updates'));
        
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
     * Controlla se ci sono aggiornamenti disponibili
     */
    public function check_for_updates() {
        $settings = get_option('fp_git_updater_settings');
        
        if (empty($settings['github_repo'])) {
            return false;
        }
        
        FP_Git_Updater_Logger::log('info', 'Controllo aggiornamenti...');
        
        $latest_commit = $this->get_latest_commit();
        
        if (is_wp_error($latest_commit)) {
            FP_Git_Updater_Logger::log('error', 'Errore nel controllo aggiornamenti: ' . $latest_commit->get_error_message());
            return false;
        }
        
        $current_commit = get_option('fp_git_updater_current_commit', '');
        
        if ($latest_commit !== $current_commit) {
            FP_Git_Updater_Logger::log('info', 'Nuovo aggiornamento disponibile: ' . $latest_commit);
            
            // Se l'aggiornamento automatico è abilitato, eseguilo
            if (isset($settings['auto_update']) && $settings['auto_update']) {
                $this->run_update($latest_commit);
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Ottieni l'ultimo commit dal repository GitHub
     */
    private function get_latest_commit() {
        $settings = get_option('fp_git_updater_settings');
        $repo = $settings['github_repo'];
        $branch = isset($settings['branch']) ? $settings['branch'] : 'main';
        $token = isset($settings['github_token']) ? $settings['github_token'] : '';
        
        // URL API GitHub per ottenere l'ultimo commit
        $api_url = "https://api.github.com/repos/{$repo}/commits/{$branch}";
        
        $args = array(
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'FP-Git-Updater/' . FP_GIT_UPDATER_VERSION,
            ),
            'timeout' => 30,
        );
        
        // Aggiungi il token se presente
        if (!empty($token)) {
            $args['headers']['Authorization'] = 'token ' . $token;
        }
        
        $response = wp_remote_get($api_url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code !== 200) {
            return new WP_Error('api_error', 'Errore API GitHub: ' . $code);
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
    public function run_update($commit_sha = null) {
        FP_Git_Updater_Logger::log('info', 'Inizio aggiornamento...');
        
        // Notifica inizio aggiornamento
        $this->send_notification('Inizio aggiornamento plugin FP Git Updater', 'L\'aggiornamento è iniziato...');
        
        $settings = get_option('fp_git_updater_settings');
        $repo = $settings['github_repo'];
        $branch = isset($settings['branch']) ? $settings['branch'] : 'main';
        $token = isset($settings['github_token']) ? $settings['github_token'] : '';
        
        if (empty($repo)) {
            FP_Git_Updater_Logger::log('error', 'Repository non configurato');
            $this->send_notification('Errore aggiornamento', 'Repository non configurato');
            return false;
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
        
        if (!empty($token)) {
            $args['headers']['Authorization'] = 'token ' . $token;
        }
        
        // Scarica il file zip
        FP_Git_Updater_Logger::log('info', 'Download dell\'aggiornamento...');
        
        $temp_file = download_url($download_url, 300);
        
        if (is_wp_error($temp_file)) {
            FP_Git_Updater_Logger::log('error', 'Errore download: ' . $temp_file->get_error_message());
            $this->send_notification('Errore aggiornamento', 'Errore durante il download: ' . $temp_file->get_error_message());
            return false;
        }
        
        // Unzip il file
        FP_Git_Updater_Logger::log('info', 'Estrazione dell\'aggiornamento...');
        
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
        
        $unzip_result = unzip_file($temp_file, WP_CONTENT_DIR . '/upgrade/fp-git-updater-temp');
        
        // Rimuovi il file temporaneo
        @unlink($temp_file);
        
        if (is_wp_error($unzip_result)) {
            FP_Git_Updater_Logger::log('error', 'Errore estrazione: ' . $unzip_result->get_error_message());
            $this->send_notification('Errore aggiornamento', 'Errore durante l\'estrazione: ' . $unzip_result->get_error_message());
            return false;
        }
        
        // Trova la directory estratta (GitHub crea una directory con nome casuale)
        $temp_dir = WP_CONTENT_DIR . '/upgrade/fp-git-updater-temp';
        $extracted_dirs = glob($temp_dir . '/*', GLOB_ONLYDIR);
        
        if (empty($extracted_dirs)) {
            FP_Git_Updater_Logger::log('error', 'Directory estratta non trovata');
            $this->send_notification('Errore aggiornamento', 'Directory estratta non trovata');
            return false;
        }
        
        $source_dir = $extracted_dirs[0];
        $plugin_dir = FP_GIT_UPDATER_PLUGIN_DIR;
        
        // Backup della versione attuale
        FP_Git_Updater_Logger::log('info', 'Creazione backup...');
        $backup_dir = WP_CONTENT_DIR . '/upgrade/fp-git-updater-backup-' . time();
        
        global $wp_filesystem;
        $wp_filesystem->move($plugin_dir, $backup_dir);
        
        // Copia i nuovi file
        FP_Git_Updater_Logger::log('info', 'Installazione nuovi file...');
        $copy_result = copy_dir($source_dir, $plugin_dir);
        
        if (is_wp_error($copy_result)) {
            // Ripristina il backup
            FP_Git_Updater_Logger::log('error', 'Errore installazione: ' . $copy_result->get_error_message());
            $wp_filesystem->move($backup_dir, $plugin_dir);
            $this->send_notification('Errore aggiornamento', 'Errore durante l\'installazione. Backup ripristinato.');
            return false;
        }
        
        // Pulisci
        $wp_filesystem->delete($temp_dir, true);
        
        // Salva il commit corrente
        if ($commit_sha) {
            update_option('fp_git_updater_current_commit', $commit_sha);
        } else {
            $latest_commit = $this->get_latest_commit();
            if (!is_wp_error($latest_commit)) {
                update_option('fp_git_updater_current_commit', $latest_commit);
            }
        }
        
        update_option('fp_git_updater_last_update', current_time('mysql'));
        
        FP_Git_Updater_Logger::log('success', 'Aggiornamento completato con successo!');
        $this->send_notification('Aggiornamento completato', 'Il plugin è stato aggiornato con successo!');
        
        // Mantieni il backup per 7 giorni
        wp_schedule_single_event(time() + (7 * DAY_IN_SECONDS), 'fp_git_updater_cleanup_backup', array($backup_dir));
        
        return true;
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
