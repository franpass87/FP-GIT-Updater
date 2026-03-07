<?php
/**
 * Pannello di Amministrazione
 * 
 * Gestisce l'interfaccia admin del plugin
 */


namespace FP\GitUpdater;

if (!defined('ABSPATH')) {
    exit;
}

class Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_head', array($this, 'add_custom_favicon'));
        
        // AJAX handlers
        add_action('wp_ajax_fp_git_updater_check_updates', array($this, 'ajax_check_updates'));
        add_action('wp_ajax_fp_git_updater_install_update', array($this, 'ajax_install_update'));
        add_action('wp_ajax_fp_git_updater_clear_logs', array($this, 'ajax_clear_logs'));
        add_action('wp_ajax_fp_git_updater_create_backup', array($this, 'ajax_create_backup'));
        add_action('wp_ajax_fp_git_updater_restore_backup', array($this, 'ajax_restore_backup'));
        add_action('wp_ajax_fp_git_updater_delete_backup', array($this, 'ajax_delete_backup'));
        add_action('wp_ajax_fp_git_updater_check_self_update', array($this, 'ajax_check_self_update'));
        add_action('wp_ajax_fp_git_updater_install_self_update', array($this, 'ajax_install_self_update'));
        add_action('wp_ajax_fp_git_updater_load_github_repos', array($this, 'ajax_load_github_repos'));
        add_action('wp_ajax_fp_git_updater_cleanup_backups', array($this, 'ajax_cleanup_backups'));
        add_action('wp_ajax_fp_git_updater_get_backup_stats', array($this, 'ajax_get_backup_stats'));
        add_action('wp_ajax_fp_git_updater_refresh_github_version', array($this, 'ajax_refresh_github_version'));
        add_action('wp_ajax_fp_git_updater_deploy_install', array($this, 'ajax_deploy_install'));
        add_action('wp_ajax_fp_git_updater_deploy_update', array($this, 'ajax_deploy_update'));
        add_action('wp_ajax_fp_git_updater_refresh_clients', array($this, 'ajax_refresh_clients'));
        add_action('wp_ajax_fp_git_updater_clear_update_lock', array($this, 'ajax_clear_update_lock'));
        add_action('wp_ajax_fp_git_updater_remove_client', array($this, 'ajax_remove_client'));
        add_action('wp_ajax_fp_git_updater_refresh_client_versions', array($this, 'ajax_refresh_client_versions'));
    }
    
    /**
     * Aggiungi menu admin
     */
    public function add_admin_menu() {
        // Conta gli aggiornamenti pending
        $updater = Updater::get_instance();
        $pending_updates = $updater->get_pending_updates();
        $pending_count = count($pending_updates);
        
        // Crea il titolo del menu con badge se ci sono aggiornamenti
        $menu_title = 'FP Updater';
        if ($pending_count > 0) {
            $menu_title .= ' <span class="update-plugins count-' . $pending_count . '"><span class="update-count">' . $pending_count . '</span></span>';
        }
        
        add_menu_page(
            'FP Updater',
            $menu_title,
            'manage_options',
            'fp-git-updater',
            array($this, 'render_settings_page'),
            'dashicons-update',
            80
        );
        
        add_submenu_page(
            'fp-git-updater',
            'Impostazioni',
            'Impostazioni',
            'manage_options',
            'fp-git-updater',
            array($this, 'render_settings_page')
        );
        
        add_submenu_page(
            'fp-git-updater',
            'Backup e Ripristino',
            'Backup e Ripristino',
            'manage_options',
            'fp-git-updater-backup',
            array($this, 'render_backup_page')
        );
        
        add_submenu_page(
            'fp-git-updater',
            'Log',
            'Log',
            'manage_options',
            'fp-git-updater-logs',
            array($this, 'render_logs_page')
        );
    }
    
    /**
     * Aggiungi favicon personalizzata nelle pagine del plugin
     */
    public function add_custom_favicon(): void {
        $screen = get_current_screen();
        if ( $screen === null ) {
            return;
        }

        $screen_id = (string) $screen->id;
        if ( strpos( $screen_id, 'fp-git-updater' ) === false && $screen_id !== 'toplevel_page_fp-git-updater' ) {
            return;
        }

        // SVG favicon inline - Frecce di sync/update con branch node, gradiente blu-viola
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">'
            . '<defs><linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%">'
            . '<stop offset="0%" stop-color="#3b82f6"/><stop offset="100%" stop-color="#7c3aed"/>'
            . '</linearGradient></defs>'
            . '<rect width="32" height="32" rx="6" fill="url(#g)"/>'
            // Freccia superiore (senso orario, arco in alto)
            . '<path d="M16 7 A9 9 0 0 1 25 16" stroke="#fff" stroke-width="2.5" fill="none" stroke-linecap="round"/>'
            . '<polygon points="25,12 25,19 20,15" fill="#fff"/>'
            // Freccia inferiore (senso orario, arco in basso)
            . '<path d="M16 25 A9 9 0 0 1 7 16" stroke="#fff" stroke-width="2.5" fill="none" stroke-linecap="round"/>'
            . '<polygon points="7,20 7,13 12,17" fill="#fff"/>'
            // Nodo centrale (dot branch)
            . '<circle cx="16" cy="16" r="2.5" fill="#fff"/>'
            . '</svg>';

        $favicon_data = 'data:image/svg+xml;base64,' . base64_encode( $svg );
        echo '<link rel="icon" type="image/svg+xml" href="' . esc_attr( $favicon_data ) . '" />' . "\n";
    }

    /**
     * Registra le impostazioni
     */
    public function register_settings() {
        register_setting('fp_git_updater_settings_group', 'fp_git_updater_settings', array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));

        // Opzioni separate per Modalità Master
        register_setting('fp_git_updater_master_group', 'fp_git_updater_master_mode', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => function ($v) { return !empty($v); }
        ));
        register_setting('fp_git_updater_master_group', 'fp_git_updater_master_client_secret', array(
            'type' => 'string',
            'sanitize_callback' => function ($v) { return is_string($v) ? trim($v) : ''; }
        ));
    }
    
    /**
     * Sanitizza le impostazioni
     */
    public function sanitize_settings($input) {
        // Crea un backup prima di salvare nuove impostazioni
        $backup_manager = SettingsBackup::get_instance();
        $current_settings = get_option('fp_git_updater_settings');
        
        if (!empty($current_settings) && !empty($current_settings['plugins'])) {
            $backup_manager->create_backup(false);
        }
        
        $output = array();
        $encryption = Encryption::get_instance();
        
        // Username hardcodato a FranPass87
        $output['default_github_username'] = 'FranPass87';
        
        // Sanitizza token GitHub globale
        $global_github_token = isset($input['global_github_token']) ? sanitize_text_field($input['global_github_token']) : '';
        if (!empty($global_github_token)) {
            if (strlen($global_github_token) > 500) {
                Logger::log('error', 'Token GitHub globale troppo lungo (max 500 caratteri)');
                add_settings_error(
                    'fp_git_updater_settings',
                    'token_too_long',
                    __('Token GitHub globale troppo lungo. Usa un token valido.', 'fp-git-updater')
                );
                $global_github_token = '';
            } elseif (!$encryption->is_encrypted($global_github_token)) {
                $encrypted_token = $encryption->encrypt($global_github_token);
                $global_github_token = $encrypted_token !== false ? $encrypted_token : $global_github_token;
            }
        }
        $output['global_github_token'] = $global_github_token;
        
        // Sanitizza la lista di plugin
        if (isset($input['plugins']) && is_array($input['plugins'])) {
            $output['plugins'] = array();
            $seen_repos = array(); // Per tracciare repository già visti ed evitare duplicati
            
            foreach ($input['plugins'] as $plugin) {
                $github_repo = isset($plugin['github_repo']) ? sanitize_text_field($plugin['github_repo']) : '';
                $branch = isset($plugin['branch']) ? sanitize_text_field($plugin['branch']) : 'main';

                // Auto-completa repository con username predefinito (FranPass87) se manca lo slash
                if (!empty($github_repo) && strpos($github_repo, '/') === false) {
                    $github_repo = 'FranPass87/' . $github_repo;
                    Logger::log('info', 'Repository auto-completato: ' . $github_repo);
                }

                // URL ZIP pubblico opzionale (per aggiornamenti senza credenziali)
                $zip_url = isset($plugin['zip_url']) ? trim(sanitize_text_field($plugin['zip_url'])) : '';
                if (!empty($zip_url) && !filter_var($zip_url, FILTER_VALIDATE_URL)) {
                    Logger::log('error', 'URL ZIP non valido: ' . $zip_url);
                    add_settings_error(
                        'fp_git_updater_settings',
                        'invalid_zip_url',
                        sprintf(__('URL ZIP non valido: "%s". Inserisci un URL http/https a un file .zip', 'fp-git-updater'), $zip_url)
                    );
                    $zip_url = '';
                }

                // Se presente, valida il formato del repository (username/repository)
                if (!empty($github_repo) && !preg_match('/^[a-zA-Z0-9_-]+\/[a-zA-Z0-9_.-]+$/', $github_repo)) {
                    Logger::log('error', 'Formato repository non valido: ' . $github_repo);
                    add_settings_error(
                        'fp_git_updater_settings',
                        'invalid_repo_format',
                        sprintf(__('Formato repository non valido: "%s". Usa il formato username/repository o solo il nome repository se hai impostato uno username predefinito', 'fp-git-updater'), $github_repo)
                    );
                    $github_repo = '';
                }

                // Valida il nome del branch (solo caratteri alfanumerici, -, _, /)
                if (!preg_match('/^[a-zA-Z0-9_.\/-]+$/', $branch)) {
                    Logger::log('error', 'Nome branch non valido: ' . $branch);
                    add_settings_error(
                        'fp_git_updater_settings',
                        'invalid_branch_name',
                        sprintf(__('Nome branch non valido: "%s". Usa solo lettere, numeri, -, _ e /', 'fp-git-updater'), $branch)
                    );
                    continue;
                }

                // Evita duplicati su base repo:branch (se repo presente)
                if (!empty($github_repo)) {
                    $repo_key = strtolower($github_repo . ':' . $branch);
                    if (isset($seen_repos[$repo_key])) {
                        Logger::log('warning', 'Plugin duplicato rimosso: ' . $github_repo . ' (' . $branch . ')');
                        continue;
                    }
                    $seen_repos[$repo_key] = true;
                }

                // Valida nome e slug
                $plugin_name = isset($plugin['name']) ? sanitize_text_field($plugin['name']) : __('Plugin senza nome', 'fp-git-updater');
                if (strlen($plugin_name) > 200) {
                    $plugin_name = substr($plugin_name, 0, 200);
                    Logger::log('warning', 'Nome plugin troppo lungo, troncato a 200 caratteri');
                }

                $plugin_slug = isset($plugin['plugin_slug']) ? sanitize_text_field($plugin['plugin_slug']) : '';
                if (!empty($plugin_slug)) {
                    $plugin_slug = preg_replace('/[^a-zA-Z0-9_-]/', '-', $plugin_slug);
                    $plugin_slug = trim($plugin_slug, '-');
                    if (strlen($plugin_slug) > 100) {
                        $plugin_slug = substr($plugin_slug, 0, 100);
                        Logger::log('warning', 'Slug plugin troppo lungo, troncato a 100 caratteri');
                    }
                }

                // Richiede almeno una sorgente (repo o zip)
                if (empty($github_repo) && empty($zip_url)) {
                    Logger::log('warning', 'Plugin ignorato: manca repository e URL ZIP');
                    continue;
                }

                // Se il repository è FP-GIT-Updater, salta questo plugin (gestito separatamente nella sezione dedicata)
                if (stripos($github_repo, 'FP-Updater') !== false || stripos($github_repo, 'FP-GIT-Updater') !== false || 
                    stripos($github_repo, 'FP-Git-Updater') !== false || stripos($github_repo, 'FP-Updater') !== false ||
                    isset($plugin['id']) && $plugin['id'] === 'fp_git_updater_self' ||
                    $plugin_slug === 'fp-git-updater') {
                    Logger::log('info', 'Plugin self-update escluso dalla lista gestiti (gestito nella sezione dedicata)');
                    continue; // Salta questo plugin, non aggiungerlo alla lista
                }
                
                $plugin_id = isset($plugin['id']) ? sanitize_text_field($plugin['id']) : uniqid('plugin_');
                
                $output['plugins'][] = array(
                    'id' => $plugin_id,
                    'name' => $plugin_name,
                    'github_repo' => $github_repo,
                    'plugin_slug' => $plugin_slug,
                    'branch' => $branch,
                    'zip_url' => $zip_url,
                    'enabled' => isset($plugin['enabled']) ? true : false,
                );
            }
        } else {
            $output['plugins'] = array();
        }
        
        // Rimuovi eventuali plugin self-update rimasti nella lista (pulizia)
        if (!empty($output['plugins'])) {
            $output['plugins'] = array_filter($output['plugins'], function($plugin) {
                if (isset($plugin['id']) && $plugin['id'] === 'fp_git_updater_self') {
                    return false;
                }
                if (isset($plugin['plugin_slug']) && $plugin['plugin_slug'] === 'fp-git-updater') {
                    return false;
                }
                return true;
            });
            $output['plugins'] = array_values($output['plugins']);
        }
        
        // Cripta il webhook secret se non è già criptato
        if (isset($input['webhook_secret'])) {
            $webhook_secret = sanitize_text_field($input['webhook_secret']);
            if (!empty($webhook_secret) && !$encryption->is_encrypted($webhook_secret)) {
                $encrypted_secret = $encryption->encrypt($webhook_secret);
                $output['webhook_secret'] = $encrypted_secret !== false ? $encrypted_secret : $webhook_secret;
            } else {
                $output['webhook_secret'] = $webhook_secret;
            }
        }
        
        $output['auto_update'] = isset($input['auto_update']) ? true : false;
        $output['enable_notifications'] = isset($input['enable_notifications']) ? true : false;
        
        if (isset($input['notification_email'])) {
            $sanitized_email = sanitize_email($input['notification_email']);
            // Valida che sia un'email valida
            if (!empty($sanitized_email) && is_email($sanitized_email)) {
                $output['notification_email'] = $sanitized_email;
            } else {
                // Usa email admin come fallback
                $output['notification_email'] = get_option('admin_email');
                if (!empty($input['notification_email'])) {
                    add_settings_error(
                        'fp_git_updater_settings',
                        'invalid_email',
                        sprintf(__('Email non valida: "%s". Verrà usata l\'email admin.', 'fp-git-updater'), $input['notification_email'])
                    );
                }
            }
        }
        
        if (isset($input['update_check_interval'])) {
            $output['update_check_interval'] = sanitize_text_field($input['update_check_interval']);
        }
        
        return $output;
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'fp-git-updater') === false) {
            return;
        }
        
        // Verifica che i file esistano prima di caricarli
        $css_file = FP_GIT_UPDATER_PLUGIN_DIR . 'assets/admin.css';
        $js_file = FP_GIT_UPDATER_PLUGIN_DIR . 'assets/admin.js';
        
        // Carica CSS con fallback inline
        if (file_exists($css_file)) {
            wp_enqueue_style('fp-git-updater-admin', FP_GIT_UPDATER_PLUGIN_URL . 'assets/admin.css', array(), FP_GIT_UPDATER_VERSION);
        } else {
            // Fallback: carica CSS inline
            add_action('admin_head', array($this, 'enqueue_inline_css'));
            Logger::log('warning', 'File admin.css non trovato, uso CSS inline come fallback', array('path' => $css_file));
        }
        
        // Carica JS con controllo esistenza e logging migliorato
        if (file_exists($js_file)) {
            wp_enqueue_script('fp-git-updater-admin', FP_GIT_UPDATER_PLUGIN_URL . 'assets/admin.js', array('jquery'), FP_GIT_UPDATER_VERSION, true);
            
            $connected = MasterEndpoint::get_connected_clients();
            $client_ids = array_keys($connected);
            $settings = get_option('fp_git_updater_settings', array());
            $plugins = isset($settings['plugins']) ? $settings['plugins'] : array();
            $configured_repos = array();
            foreach ($plugins as $p) {
                if (!empty($p['github_repo']) && (empty($p['id']) || $p['id'] !== 'fp_git_updater_self') && (empty($p['plugin_slug']) || $p['plugin_slug'] !== 'fp-git-updater')) {
                    $configured_repos[] = $p['github_repo'];
                }
            }
            wp_localize_script('fp-git-updater-admin', 'fpGitUpdater', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('fp_git_updater_nonce'),
                'connected_clients' => $client_ids,
                'configured_repos' => $configured_repos,
            ));
        } else {
            // Log se il file JS non esiste
            Logger::log('error', 'File JS non trovato: ' . $js_file);
        }
    }
    
    /**
     * Enqueue CSS inline come fallback
     */
    public function enqueue_inline_css() {
        $css_file_path = FP_GIT_UPDATER_PLUGIN_DIR . 'assets/admin.css';
        
        if (file_exists($css_file_path) && is_readable($css_file_path)) {
            $css_content = @file_get_contents($css_file_path);
            if ($css_content !== false) {
                echo '<style id="fp-git-updater-admin-css">' . $css_content . '</style>';
                return;
            }
        }
        
        // Se non è possibile leggere il file CSS, usa il fallback
        // CSS minimo di emergenza
        echo '<style id="fp-git-updater-admin-fallback-css">
.fp-git-updater-wrap { max-width: 1200px; }
.fp-git-updater-wrap h1 { display: flex; align-items: center; gap: 10px; }
.fp-git-updater-header { background: #fff; border: 1px solid #ccd0d4; margin: 20px 0; padding: 20px; border-radius: 4px; }
.fp-status-box button { margin-right: 10px; margin-top: 15px; }
.fp-git-updater-instructions { background: #fff; border: 1px solid #ccd0d4; margin: 20px 0; padding: 20px; border-radius: 4px; }
.log-badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; text-transform: uppercase; color: #fff; }
.log-badge-info { background: #2271b1; }
.log-badge-success { background: #00a32a; }
.log-badge-warning { background: #dba617; }
.log-badge-error { background: #d63638; }
.log-badge-webhook { background: #8c5ed9; }
.fp-notice { padding: 12px; margin: 15px 0; border-left: 4px solid; border-radius: 0 4px 4px 0; background: #fff; }
.fp-notice-success { border-left-color: #00a32a; background: #f0f8f2; }
.fp-notice-error { border-left-color: #d63638; background: #fcf0f1; }
.fp-notice-info { border-left-color: #2271b1; background: #f0f6fc; }
</style>';
    }
    
    /**
     * Render pagina impostazioni
     */
    public function render_settings_page() {
        $settings = get_option('fp_git_updater_settings');
        $webhook_url = WebhookHandler::get_webhook_url();
        $plugins = isset($settings['plugins']) ? $settings['plugins'] : array();
        
        // Filtra il plugin self-update dalla lista (gestito separatamente nella sezione dedicata)
        $plugins = array_filter($plugins, function($plugin) {
            if (isset($plugin['id']) && $plugin['id'] === 'fp_git_updater_self') {
                return false;
            }
            if (isset($plugin['plugin_slug']) && $plugin['plugin_slug'] === 'fp-git-updater') {
                return false;
            }
            return true;
        });
        
        // Re-indicizza l'array
        $plugins = array_values($plugins);
        
        // Ottieni gli aggiornamenti pending (escludendo il self-update dalla lista)
        $updater = Updater::get_instance();
        $all_pending_updates = $updater->get_pending_updates();
        
        // Filtra anche i pending updates per escludere il self-update dalla lista visualizzata
        $pending_updates = array_filter($all_pending_updates, function($update) {
            if (isset($update['plugin']['id']) && $update['plugin']['id'] === 'fp_git_updater_self') {
                return false;
            }
            return true;
        });
        $pending_updates = array_values($pending_updates);
        
        $auto_update_enabled = isset($settings['auto_update']) ? $settings['auto_update'] : false;
        $connected_clients = MasterEndpoint::get_connected_clients();
        
        // Usa il template separato
        include FP_GIT_UPDATER_PLUGIN_DIR . 'includes/admin-templates/settings-page.php';
    }
    
    /**
     * Render pagina log
     */
    public function render_logs_page() {
        $logs = Logger::get_logs(100);
        
        ?>
        <div class="wrap fp-git-updater-wrap">
            <h1>
                <span class="dashicons dashicons-list-view"></span>
                FP Updater - Log
            </h1>
            
            <div class="fp-logs-actions">
                <button type="button" id="fp-clear-logs" class="button">
                    <span class="dashicons dashicons-trash"></span> Pulisci Log
                </button>
                <button type="button" class="button" onclick="location.reload()">
                    <span class="dashicons dashicons-update"></span> Ricarica
                </button>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 150px">Data/Ora</th>
                        <th style="width: 100px">Tipo</th>
                        <th>Messaggio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="3" style="text-align: center; padding: 20px;">
                                Nessun log disponibile
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr class="log-<?php echo esc_attr($log->log_type); ?>">
                                <td><?php echo esc_html($log->log_date); ?></td>
                                <td>
                                    <span class="log-badge log-badge-<?php echo esc_attr($log->log_type); ?>">
                                        <?php echo esc_html($log->log_type); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo esc_html($log->message); ?>
                                    <?php if ($log->details): ?>
                                        <details style="margin-top: 5px;">
                                            <summary style="cursor: pointer; color: #666;">Dettagli</summary>
                                            <pre style="margin-top: 10px; background: #f5f5f5; padding: 10px; overflow-x: auto;"><?php echo esc_html(print_r(json_decode($log->details, true), true)); ?></pre>
                                        </details>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * AJAX: Controlla aggiornamenti per un plugin specifico
     */
    public function ajax_check_updates() {
        // Verifica il nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fp_git_updater_nonce')) {
            wp_send_json_error(array('message' => 'Nonce non valido'), 400);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'), 403);
        }
        
        $plugin_id = isset($_POST['plugin_id']) ? sanitize_text_field($_POST['plugin_id']) : '';
        
        if (empty($plugin_id)) {
            wp_send_json_error(array('message' => 'ID plugin non fornito'), 400);
        }
        
        try {
            $updater = Updater::get_instance();
            $result = $updater->check_plugin_update_by_id($plugin_id);
            
            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()), 500);
            } elseif ($result) {
                wp_send_json_success(array('message' => 'Aggiornamento disponibile per questo plugin!'));
            } else {
                wp_send_json_success(array('message' => 'Il plugin è già aggiornato.'));
            }
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => 'Errore: ' . $e->getMessage()), 500);
        }
    }
    
    /**
     * AJAX: Installa aggiornamento per un plugin specifico
     */
    public function ajax_install_update() {
        // Verifica il nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fp_git_updater_nonce')) {
            wp_send_json_error(array('message' => 'Nonce non valido'), 400);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'), 403);
        }
        
        $plugin_id = isset($_POST['plugin_id']) ? sanitize_text_field($_POST['plugin_id']) : '';
        
        if (empty($plugin_id)) {
            wp_send_json_error(array('message' => 'ID plugin non fornito'), 400);
        }
        
        try {
            $updater = Updater::get_instance();
            $result = $updater->run_update_by_id($plugin_id);
            
            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()), 500);
            } elseif ($result) {
                wp_send_json_success(array('message' => 'Aggiornamento completato con successo!'));
            } else {
                wp_send_json_error(array('message' => 'Errore durante l\'aggiornamento. Controlla i log.'), 500);
            }
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => 'Errore: ' . $e->getMessage()), 500);
        }
    }
    
    /**
     * AJAX: Pulisci log
     */
    public function ajax_clear_logs() {
        // Verifica il nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fp_git_updater_nonce')) {
            wp_send_json_error(array('message' => 'Nonce non valido'), 400);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'), 403);
        }
        
        try {
            Logger::clear_all_logs();
            wp_send_json_success(array('message' => 'Log puliti con successo!'));
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => 'Errore: ' . $e->getMessage()), 500);
        }
    }
    
    /**
     * AJAX: Crea backup manuale
     */
    public function ajax_create_backup() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fp_git_updater_nonce')) {
            wp_send_json_error(array('message' => 'Nonce non valido'), 400);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'), 403);
        }
        
        try {
            $backup_manager = SettingsBackup::get_instance();
            $result = $backup_manager->create_backup(true);
            
            if ($result) {
                wp_send_json_success(array('message' => 'Backup creato con successo!'));
            } else {
                wp_send_json_error(array('message' => 'Impossibile creare il backup. Assicurati di avere impostazioni da salvare.'), 500);
            }
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => 'Errore: ' . $e->getMessage()), 500);
        }
    }
    
    /**
     * AJAX: Ripristina backup
     */
    public function ajax_restore_backup() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fp_git_updater_nonce')) {
            wp_send_json_error(array('message' => 'Nonce non valido'), 400);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'), 403);
        }
        
        $backup_index = isset($_POST['backup_index']) ? intval($_POST['backup_index']) : null;
        
        try {
            $backup_manager = SettingsBackup::get_instance();
            $result = $backup_manager->restore_backup($backup_index);
            
            if ($result) {
                wp_send_json_success(array('message' => 'Impostazioni ripristinate con successo!'));
            } else {
                wp_send_json_error(array('message' => 'Nessun backup disponibile da ripristinare.'), 404);
            }
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => 'Errore: ' . $e->getMessage()), 500);
        }
    }
    
    /**
     * AJAX: Elimina backup
     */
    public function ajax_delete_backup() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fp_git_updater_nonce')) {
            wp_send_json_error(array('message' => 'Nonce non valido'), 400);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'), 403);
        }
        
        $backup_index = isset($_POST['backup_index']) ? intval($_POST['backup_index']) : null;
        
        if ($backup_index === null) {
            wp_send_json_error(array('message' => 'Indice backup non fornito'), 400);
        }
        
        try {
            $backup_manager = SettingsBackup::get_instance();
            $result = $backup_manager->delete_backup($backup_index);
            
            if ($result) {
                wp_send_json_success(array('message' => 'Backup eliminato con successo!'));
            } else {
                wp_send_json_error(array('message' => 'Backup non trovato.'), 404);
            }
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => 'Errore: ' . $e->getMessage()), 500);
        }
    }
    
    /**
     * Render pagina backup
     */
    public function render_backup_page() {
        $backup_manager = SettingsBackup::get_instance();
        $latest_backup = $backup_manager->get_latest_backup();
        $backup_history = $backup_manager->get_backup_history();
        $current_settings = get_option('fp_git_updater_settings');
        $has_settings_reset = $backup_manager->check_if_settings_reset();
        
        ?>
        <div class="wrap fp-git-updater-wrap">
            <h1>
                <span class="dashicons dashicons-database"></span>
                FP Updater - Backup e Ripristino
            </h1>
            
            <?php if ($has_settings_reset): ?>
                <div class="notice notice-warning">
                    <p><strong>Attenzione!</strong> Le tue impostazioni sembrano essere state resettate. È disponibile un backup che puoi ripristinare.</p>
                    <p>
                        <button type="button" id="fp-quick-restore" class="button button-primary">
                            <span class="dashicons dashicons-backup"></span> Ripristina Ora
                        </button>
                    </p>
                </div>
            <?php endif; ?>
            
            <div class="fp-git-updater-header">
                <h2>Stato Attuale</h2>
                <table class="form-table">
                    <tr>
                        <th style="width: 200px;">Plugin Configurati:</th>
                        <td><strong><?php echo count(is_array($current_settings) ? ($current_settings['plugins'] ?? array()) : array()); ?></strong> plugin</td>
                    </tr>
                    <tr>
                        <th>Ultimo Backup:</th>
                        <td>
                            <?php if ($latest_backup): ?>
                                <?php echo esc_html($latest_backup['timestamp']); ?>
                                (<?php echo $latest_backup['manual'] ? 'Manuale' : 'Automatico'; ?>)
                            <?php else: ?>
                                <em>Nessun backup disponibile</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                
                <div style="margin-top: 20px;">
                    <button type="button" id="fp-create-backup" class="button button-primary">
                        <span class="dashicons dashicons-database-add"></span> Crea Backup Manuale
                    </button>
                    <?php if ($latest_backup): ?>
                        <button type="button" id="fp-restore-latest" class="button">
                            <span class="dashicons dashicons-backup"></span> Ripristina Ultimo Backup
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="fp-git-updater-instructions" style="margin-top: 20px;">
                <h2>Cronologia Backup</h2>
                
                <?php if (empty($backup_history)): ?>
                    <p class="description">Nessun backup disponibile nella cronologia.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 150px">Data/Ora</th>
                                <th style="width: 100px">Tipo</th>
                                <th style="width: 100px">Versione</th>
                                <th>Plugin Salvati</th>
                                <th style="width: 200px">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backup_history as $index => $backup): ?>
                                <tr>
                                    <td><?php echo esc_html($backup['timestamp']); ?></td>
                                    <td>
                                        <span class="log-badge log-badge-<?php echo $backup['manual'] ? 'info' : 'success'; ?>">
                                            <?php echo $backup['manual'] ? 'Manuale' : 'Automatico'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($backup['version'] ?? 'N/A'); ?></td>
                                    <td>
                                        <strong><?php echo count($backup['settings']['plugins'] ?? array()); ?></strong> plugin
                                        <?php if (!empty($backup['settings']['plugins'])): ?>
                                            <details style="margin-top: 5px;">
                                                <summary style="cursor: pointer; color: #666;">Vedi dettagli</summary>
                                                <ul style="margin-top: 10px; padding-left: 20px;">
                                                    <?php foreach ($backup['settings']['plugins'] as $plugin): ?>
                                                        <li>
                                                            <strong><?php echo esc_html($plugin['name']); ?></strong><br>
                                                            <small>Repository: <?php echo esc_html($plugin['github_repo']); ?></small>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </details>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="button button-small fp-restore-backup-btn" data-backup-index="<?php echo $index; ?>">
                                            <span class="dashicons dashicons-backup"></span> Ripristina
                                        </button>
                                        <button type="button" class="button button-small fp-delete-backup-btn" data-backup-index="<?php echo $index; ?>">
                                            <span class="dashicons dashicons-trash"></span> Elimina
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <div class="fp-git-updater-instructions" style="margin-top: 20px;">
                <h2>Come Funziona il Backup Automatico</h2>
                <p>Il sistema di backup protegge automaticamente le tue impostazioni:</p>
                <ul style="padding-left: 20px;">
                    <li><strong>Prima degli aggiornamenti:</strong> Viene creato automaticamente un backup prima di ogni aggiornamento del plugin</li>
                    <li><strong>Dopo l'attivazione:</strong> Se le impostazioni sono state resettate, vengono ripristinate automaticamente dal backup</li>
                    <li><strong>Backup manuali:</strong> Puoi creare backup manuali in qualsiasi momento usando il pulsante sopra</li>
                    <li><strong>Cronologia:</strong> Vengono conservati gli ultimi 10 backup per sicurezza</li>
                </ul>
                
                <h3 style="margin-top: 15px;">Quando usare il ripristino manuale</h3>
                <ul style="padding-left: 20px;">
                    <li>Se il ripristino automatico non è andato a buon fine</li>
                    <li>Se vuoi tornare a una configurazione precedente specifica</li>
                    <li>Se hai accidentalmente cancellato delle impostazioni</li>
                </ul>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Crea backup manuale
            $('#fp-create-backup').on('click', function() {
                if (!confirm('Creare un backup delle impostazioni correnti?')) return;
                
                var $btn = $(this);
                $btn.prop('disabled', true).text('Creazione in corso...');
                
                $.post(ajaxurl, {
                    action: 'fp_git_updater_create_backup',
                    nonce: fpGitUpdater.nonce
                }, function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert('Errore: ' + (response.data ? response.data.message : 'Errore sconosciuto'));
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-database-add"></span> Crea Backup Manuale');
                    }
                });
            });
            
            // Ripristina ultimo backup
            $('#fp-restore-latest, #fp-quick-restore').on('click', function() {
                if (!confirm('Ripristinare le impostazioni dall\'ultimo backup? Le impostazioni correnti saranno sovrascritte.')) return;
                
                var $btn = $(this);
                $btn.prop('disabled', true).text('Ripristino in corso...');
                
                $.post(ajaxurl, {
                    action: 'fp_git_updater_restore_backup',
                    nonce: fpGitUpdater.nonce
                }, function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert('Errore: ' + (response.data ? response.data.message : 'Errore sconosciuto'));
                        $btn.prop('disabled', false);
                    }
                });
            });
            
            // Ripristina backup specifico
            $('.fp-restore-backup-btn').on('click', function() {
                if (!confirm('Ripristinare questo backup? Le impostazioni correnti saranno sovrascritte.')) return;
                
                var $btn = $(this);
                var backupIndex = $btn.data('backup-index');
                $btn.prop('disabled', true).text('Ripristino...');
                
                $.post(ajaxurl, {
                    action: 'fp_git_updater_restore_backup',
                    backup_index: backupIndex,
                    nonce: fpGitUpdater.nonce
                }, function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert('Errore: ' + (response.data ? response.data.message : 'Errore sconosciuto'));
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-backup"></span> Ripristina');
                    }
                });
            });
            
            // Elimina backup
            $('.fp-delete-backup-btn').on('click', function() {
                if (!confirm('Eliminare questo backup? Questa azione non può essere annullata.')) return;
                
                var $btn = $(this);
                var backupIndex = $btn.data('backup-index');
                $btn.prop('disabled', true).text('Eliminazione...');
                
                $.post(ajaxurl, {
                    action: 'fp_git_updater_delete_backup',
                    backup_index: backupIndex,
                    nonce: fpGitUpdater.nonce
                }, function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert('Errore: ' + (response.data ? response.data.message : 'Errore sconosciuto'));
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Elimina');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX: Controlla aggiornamenti per il plugin stesso
     */
    public function ajax_check_self_update() {
        // Verifica il nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fp_git_updater_nonce')) {
            wp_send_json_error(array('message' => 'Nonce non valido'), 400);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'), 403);
        }
        
        try {
            $updater = Updater::get_instance();
            // Il metodo check_plugin_update_by_id invalida già la cache della versione GitHub
            $result = $updater->check_plugin_update_by_id('fp_git_updater_self');
            
            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()), 500);
            } elseif ($result) {
                wp_send_json_success(array('message' => 'Aggiornamento disponibile per FP Updater!'));
            } else {
                wp_send_json_success(array('message' => 'FP Updater è già aggiornato.'));
            }
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => 'Errore: ' . $e->getMessage()), 500);
        }
    }
    
    /**
     * AJAX: Installa aggiornamento per il plugin stesso
     */
    public function ajax_install_self_update() {
        // Verifica il nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fp_git_updater_nonce')) {
            wp_send_json_error(array('message' => 'Nonce non valido'), 400);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'), 403);
        }
        
        try {
            $updater = Updater::get_instance();
            $result = $updater->run_update_by_id('fp_git_updater_self');
            
            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()), 500);
            } elseif ($result) {
                // Invalida la cache della versione GitHub dopo l'aggiornamento
                delete_transient('fp_git_updater_github_version_fp_git_updater_self');
                
                wp_send_json_success(array(
                    'message' => 'Auto-aggiornamento completato con successo! La pagina verrà ricaricata.',
                    'reload' => true
                ));
            } else {
                wp_send_json_error(array('message' => 'Errore durante l\'auto-aggiornamento. Controlla i log.'), 500);
            }
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => 'Errore: ' . $e->getMessage()), 500);
        }
    }
    
    /**
     * AJAX: Carica lista repository da GitHub per l'username predefinito
     */
    public function ajax_load_github_repos() {
        // Verifica il nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fp_git_updater_nonce')) {
            wp_send_json_error(array('message' => 'Nonce non valido'), 400);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'), 403);
        }
        
        // Username hardcodato a FranPass87
        $settings = get_option('fp_git_updater_settings', array());
        $default_username = 'FranPass87';
        
        try {
            // Controlla cache (valida per 5 minuti)
            $cache_key = 'fp_git_updater_repos_' . md5($default_username);
            $cached_repos = get_transient($cache_key);
            
            if ($cached_repos !== false) {
                wp_send_json_success(array(
                    'repositories' => $cached_repos,
                    'username' => $default_username,
                    'from_cache' => true
                ));
                return;
            }
            
            // Chiama API GitHub
            $api_url = 'https://api.github.com/users/' . urlencode($default_username) . '/repos';
            $args = array(
                'timeout' => 15,
                'headers' => array(
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'FP-Updater-Plugin'
                )
            );
            
            // Usa token globale se disponibile per aumentare rate limit
            if (!empty($settings['global_github_token'])) {
                $encryption = Encryption::get_instance();
                $token = $encryption->decrypt($settings['global_github_token']);
                if (!empty($token)) {
                    $args['headers']['Authorization'] = 'token ' . $token;
                }
            }
            
            $response = wp_remote_get($api_url, $args);
            
            if (is_wp_error($response)) {
                wp_send_json_error(array('message' => 'Errore connessione GitHub: ' . $response->get_error_message()), 500);
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($status_code !== 200) {
                $error_message = 'Errore API GitHub (HTTP ' . $status_code . ')';
                if ($status_code === 404) {
                    $error_message = 'Username GitHub "' . $default_username . '" non trovato';
                } elseif ($status_code === 403) {
                    $error_message = 'Rate limit GitHub raggiunto. Riprova tra qualche minuto.';
                }
                wp_send_json_error(array('message' => $error_message), $status_code);
            }
            
            $repos = json_decode($body, true);
            
            if (!is_array($repos)) {
                wp_send_json_error(array('message' => 'Risposta API GitHub non valida'), 500);
            }
            
            // Prepara lista repository (solo nome e descrizione)
            $repo_list = array();
            foreach ($repos as $repo) {
                $repo_list[] = array(
                    'name' => $repo['name'],
                    'full_name' => $repo['full_name'],
                    'description' => !empty($repo['description']) ? $repo['description'] : '',
                    'private' => $repo['private'],
                    'default_branch' => $repo['default_branch'],
                    'updated_at' => $repo['updated_at']
                );
            }
            
            // Ordina per data di aggiornamento (più recenti prima)
            usort($repo_list, function($a, $b) {
                return strtotime($b['updated_at']) - strtotime($a['updated_at']);
            });
            
            // Salva in cache per 5 minuti
            set_transient($cache_key, $repo_list, 5 * MINUTE_IN_SECONDS);
            
            Logger::log('info', 'Caricati ' . count($repo_list) . ' repository per username: ' . $default_username);
            
            wp_send_json_success(array(
                'repositories' => $repo_list,
                'username' => $default_username,
                'from_cache' => false,
                'count' => count($repo_list)
            ));
            
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => 'Errore: ' . $e->getMessage()), 500);
        }
    }
    
    /**
     * AJAX: Aggiorna versione GitHub per un plugin specifico
     */
    public function ajax_refresh_github_version() {
        // Verifica il nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fp_git_updater_nonce')) {
            wp_send_json_error(array('message' => 'Nonce non valido'), 400);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'), 403);
        }
        
        $plugin_id = isset($_POST['plugin_id']) ? sanitize_text_field($_POST['plugin_id']) : '';
        
        if (empty($plugin_id)) {
            wp_send_json_error(array('message' => 'ID plugin non fornito'), 400);
        }
        
        try {
            $settings = get_option('fp_git_updater_settings', array());
            $plugins = isset($settings['plugins']) ? $settings['plugins'] : array();
            
            // Trova il plugin nella lista
            $plugin = null;
            foreach ($plugins as $p) {
                if (isset($p['id']) && $p['id'] === $plugin_id) {
                    $plugin = $p;
                    break;
                }
            }
            
            // Se non trovato e si tratta del self-update, usa i default
            if (!$plugin && $plugin_id === 'fp_git_updater_self') {
                $plugin = array(
                    'id' => 'fp_git_updater_self',
                    'github_repo' => 'franpass87/FP-GIT-Updater',
                    'branch' => 'main',
                    'enabled' => true
                );
            }
            
            if (!$plugin) {
                wp_send_json_error(array('message' => 'Plugin non trovato'), 404);
            }
            
            // Invalida la cache della versione GitHub, del commit e della chiamata API GitHub
            delete_transient('fp_git_updater_github_version_' . $plugin_id);
            delete_transient('fp_git_updater_commit_info_' . $plugin_id);
            $repo   = $plugin['github_repo'] ?? '';
            $branch = $plugin['branch'] ?? 'main';
            if (!empty($repo)) {
                $api_url    = "https://api.github.com/repos/{$repo}/commits/{$branch}";
                $cache_args = [
                    'headers' => [
                        'Accept'     => 'application/vnd.github.v3+json',
                        'User-Agent' => 'FP-Updater/' . FP_GIT_UPDATER_VERSION,
                    ],
                    'timeout' => 30,
                ];
                $settings = get_option('fp_git_updater_settings', []);
                if (!empty($settings['global_github_token'])) {
                    $encryption = Encryption::get_instance();
                    $token      = $encryption->decrypt($settings['global_github_token']);
                    if ($token !== false && !empty($token)) {
                        $cache_args['headers']['Authorization'] = 'token ' . $token;
                    }
                }
                ApiCache::get_instance()->delete_api_call($api_url, $cache_args);
            }

            $updater = Updater::get_instance();

            // Recupera info commit più recente
            $commit_info = $updater->get_latest_commit_info($plugin);
            $commit_sha   = '';
            $commit_short = '';
            $commit_msg   = '';
            $commit_date  = '';
            if (!is_wp_error($commit_info)) {
                $commit_sha   = $commit_info['sha'];
                $commit_short = $commit_info['short'];
                $commit_msg   = $commit_info['message'];
                $commit_date  = $commit_info['date'];
            }

            // Recupera la nuova versione GitHub
            $github_version = $updater->get_github_plugin_version($plugin, $commit_sha ?: null);
            
            if (!empty($github_version)) {
                // Salva in cache per 5 minuti
                set_transient('fp_git_updater_github_version_' . $plugin_id, $github_version, 300);
                if (!empty($commit_short)) {
                    set_transient('fp_git_updater_commit_info_' . $plugin_id, [
                        'short'   => $commit_short,
                        'message' => $commit_msg,
                        'date'    => $commit_date,
                    ], 300);
                }
                
                // Recupera anche la versione installata per confronto
                if ($plugin_id === 'fp_git_updater_self') {
                    $current_version = defined('FP_GIT_UPDATER_VERSION') ? FP_GIT_UPDATER_VERSION : '';
                } else {
                    $current_version = $updater->get_installed_plugin_version($plugin);
                }
                
                if (!empty($current_version)) {
                    update_option('fp_git_updater_current_version_' . $plugin_id, $current_version);
                }

                // Se le versioni differiscono, salva il pending update (come fa check_updates)
                $update_available = !empty($current_version) && !empty($github_version) && $current_version !== $github_version;
                if ($update_available) {
                    $pending_key = 'fp_git_updater_pending_update_' . $plugin_id;
                    update_option($pending_key, array(
                        'plugin'            => $plugin,
                        'available_version' => $github_version,
                        'commit_sha'        => $commit_sha,
                        'commit_sha_short'  => $commit_short,
                        'commit_message'    => $commit_msg,
                        'commit_date'       => $commit_date,
                        'checked_at'        => current_time('mysql'),
                    ));
                }
                
                wp_send_json_success(array(
                    'github_version'   => $github_version,
                    'current_version'  => $current_version,
                    'commit_sha'       => $commit_sha,
                    'commit_short'     => $commit_short,
                    'commit_message'   => $commit_msg,
                    'commit_date'      => $commit_date,
                    'update_available' => $update_available,
                    'message'          => $update_available
                        ? 'Aggiornamento disponibile: ' . $current_version . ' → ' . $github_version
                        : 'Versione GitHub aggiornata con successo',
                ));
            } else {
                wp_send_json_error(array('message' => 'Impossibile recuperare la versione GitHub. Verifica il repository e il branch.'), 500);
            }
            
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => 'Errore: ' . $e->getMessage()), 500);
        }
    }

    /**
     * AJAX: Statistiche backup client ricevuti dal Master
     */
    public function ajax_get_backup_stats() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fp_git_updater_nonce')) {
            wp_send_json_error(array('message' => 'Nonce non valido'), 400);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'), 403);
        }

        try {
            $updater = Updater::get_instance();
            $stats = $updater->get_backup_stats();
            wp_send_json_success($stats);
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => 'Errore: ' . $e->getMessage()), 500);
        }
    }

    /**
     * AJAX: Pulizia backup vecchi
     */
    public function ajax_cleanup_backups() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fp_git_updater_nonce')) {
            wp_send_json_error(array('message' => 'Nonce non valido'), 400);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'), 403);
        }

        try {
            $updater = Updater::get_instance();
            $deleted = $updater->cleanup_old_backups(true);
            wp_send_json_success(array(
                'message' => sprintf('%d backup eliminati con successo.', $deleted),
                'deleted' => $deleted,
            ));
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => 'Errore: ' . $e->getMessage()), 500);
        }
    }

    /**
     * AJAX: Autorizza INSTALLA – invia plugin da repo GitHub a client selezionati.
     */
    public function ajax_deploy_install() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fp_git_updater_nonce')) {
            wp_send_json_error(array('message' => 'Nonce non valido'), 400);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'), 403);
        }

        $github_repo = isset($_POST['github_repo']) ? sanitize_text_field(wp_unslash($_POST['github_repo'])) : '';
        $branch = isset($_POST['branch']) ? sanitize_text_field(wp_unslash($_POST['branch'])) : 'main';
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $client_ids = array();
        if (!empty($_POST['client_ids_json'])) {
            $decoded = json_decode(wp_unslash($_POST['client_ids_json']), true);
            if (is_array($decoded)) {
                $client_ids = array_values(array_filter(array_map('sanitize_text_field', $decoded)));
            }
        }

        if (empty($github_repo) || empty($client_ids)) {
            wp_send_json_error(array('message' => __('Repository e clienti obbligatori.', 'fp-git-updater')), 400);
        }

        $parts = explode('/', $github_repo);
        $slug = preg_replace('/[^a-z0-9_-]/', '-', strtolower(trim(end($parts), '-')));
        $plugin = array(
            'id' => 'repo_' . $slug,
            'name' => $name ?: $slug,
            'slug' => $slug,
            'github_repo' => $github_repo,
            'branch' => $branch,
        );

        MasterEndpoint::authorize_deploy_install($plugin, $client_ids);
        $until = (int) get_option(MasterEndpoint::OPTION_DEPLOY_AUTHORIZED_UNTIL, 0);
        wp_send_json_success(array(
            'message' => sprintf(
                __('Installazione autorizzata: %s su %d clienti. I siti installeranno nelle prossime 2 ore.', 'fp-git-updater'),
                esc_html($plugin['name']),
                count($client_ids)
            ),
            'valid_until' => $until,
        ));
    }

    /**
     * AJAX: Autorizza AGGIORNA – invia aggiornamento a tutti i client che hanno il plugin.
     */
    public function ajax_deploy_update() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fp_git_updater_nonce')) {
            wp_send_json_error(array('message' => 'Nonce non valido'), 400);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'), 403);
        }

        $plugin_id = isset($_POST['plugin_id']) ? sanitize_text_field(wp_unslash($_POST['plugin_id'])) : '';
        $plugin_slug = isset($_POST['plugin_slug']) ? sanitize_text_field(wp_unslash($_POST['plugin_slug'])) : $plugin_id;
        if (empty($plugin_id)) {
            wp_send_json_error(array('message' => __('ID plugin obbligatorio.', 'fp-git-updater')), 400);
        }

        MasterEndpoint::authorize_deploy_update(array($plugin_id));
        $clients = MasterEndpoint::get_clients_with_plugin($plugin_slug);
        $until = (int) get_option(MasterEndpoint::OPTION_DEPLOY_AUTHORIZED_UNTIL, 0);
        wp_send_json_success(array(
            'message' => sprintf(
                __('Aggiornamento autorizzato. %d clienti aggiorneranno nelle prossime 2 ore.', 'fp-git-updater'),
                count($clients)
            ),
            'valid_until' => $until,
        ));
    }

    /**
     * AJAX: Aggiorna elenco clienti collegati (ricarica da DB)
     */
    public function ajax_refresh_clients() {
        check_ajax_referer('fp_git_updater_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Accesso negato.', 'fp-git-updater')), 403);
        }

        $clients = MasterEndpoint::get_connected_clients();
        $count = count($clients);
        ob_start();
        if (empty($clients)) {
            echo '<p class="fp-master-clients-empty"><span class="dashicons dashicons-info"></span> ';
            esc_html_e('Nessun cliente collegato. I siti dei tuoi clienti appariranno qui dopo la prima connessione.', 'fp-git-updater');
            echo '</p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped fp-master-clients-table"><thead><tr>';
            echo '<th scope="col" style="width:28%;">' . esc_html__('Sito cliente', 'fp-git-updater') . '</th>';
            echo '<th scope="col" style="width:35%;">' . esc_html__('Plugin installati', 'fp-git-updater') . '</th>';
            echo '<th scope="col">' . esc_html__('Ultima connessione', 'fp-git-updater') . '</th>';
            echo '<th scope="col" style="width:100px;"></th></tr></thead><tbody>';
            foreach ($clients as $client_id => $data) {
                $installed = $data['installed_plugins'] ?? [];
                $count_plugins = count($installed);
                $installed_str = !empty($installed) ? implode(', ', array_slice($installed, 0, 8)) . ($count_plugins > 8 ? ' +' . ($count_plugins - 8) . '…' : '') : '—';
                $row_id = 'fp-client-row-' . sanitize_html_class($client_id);
                echo '<tr id="' . esc_attr($row_id) . '">';
                echo '<td><strong>' . esc_html($client_id) . '</strong></td>';
                echo '<td><small class="fp-client-plugins-list" data-client-id="' . esc_attr($client_id) . '">' . esc_html($installed_str) . '</small>';
                if ($count_plugins > 0) {
                    echo ' <small style="color:var(--fp-text-muted);">(' . $count_plugins . ')</small>';
                }
                echo '</td>';
                echo '<td>' . esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $data['last_seen'] ?? 0)) . '</td>';
                echo '<td style="white-space:nowrap;">';
                echo '<button type="button" class="button button-small fp-refresh-client-versions-btn" data-client-id="' . esc_attr($client_id) . '" title="' . esc_attr__('Aggiorna versioni plugin da questo sito', 'fp-git-updater') . '" style="margin-right:4px;"><span class="dashicons dashicons-update" style="margin-top:3px;"></span></button>';
                echo '<button type="button" class="button button-small fp-remove-client-btn" data-client-id="' . esc_attr($client_id) . '" title="' . esc_attr__('Rimuovi cliente', 'fp-git-updater') . '" style="color:#b32d2e;border-color:#b32d2e;"><span class="dashicons dashicons-trash" style="margin-top:3px;"></span></button>';
                echo '</td></tr>';
            }
            echo '</tbody></table>';
        }
        $html = ob_get_clean();
        wp_send_json_success(array('html' => $html, 'count' => $count));
    }

    /**
     * AJAX: Rimuove un cliente dalla lista clienti collegati
     */
    public function ajax_remove_client() {
        check_ajax_referer('fp_git_updater_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Accesso negato.', 'fp-git-updater')), 403);
        }
        $client_id = sanitize_text_field(wp_unslash($_POST['client_id'] ?? ''));
        if (empty($client_id)) {
            wp_send_json_error(array('message' => __('ID cliente mancante.', 'fp-git-updater')));
        }
        $clients = MasterEndpoint::get_connected_clients();
        if (!isset($clients[$client_id])) {
            wp_send_json_error(array('message' => __('Cliente non trovato.', 'fp-git-updater')));
        }
        unset($clients[$client_id]);
        update_option(MasterEndpoint::OPTION_CONNECTED_CLIENTS, $clients);
        wp_send_json_success(array('message' => sprintf(__('Cliente "%s" rimosso.', 'fp-git-updater'), $client_id)));
    }

    /**
     * AJAX: Interroga il Bridge di un cliente e aggiorna le versioni plugin nel DB del Master
     */
    public function ajax_refresh_client_versions() {
        check_ajax_referer('fp_git_updater_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Accesso negato.', 'fp-git-updater')), 403);
        }

        $client_id = sanitize_text_field(wp_unslash($_POST['client_id'] ?? ''));
        if (empty($client_id)) {
            wp_send_json_error(array('message' => __('ID cliente mancante.', 'fp-git-updater')));
        }

        $all_clients = get_option(MasterEndpoint::OPTION_CONNECTED_CLIENTS, []);
        if (!isset($all_clients[$client_id])) {
            wp_send_json_error(array('message' => __('Cliente non trovato.', 'fp-git-updater')));
        }

        // Ricava l'URL del sito cliente
        $client_data = $all_clients[$client_id];
        $site_url = $client_data['url'] ?? '';
        if (empty($site_url)) {
            // Prova a costruire l'URL dal client_id se sembra un dominio
            if (preg_match('#^[a-z0-9]([a-z0-9\-\.]+)?[a-z0-9]\.[a-z]{2,}$#i', $client_id)) {
                $site_url = 'https://' . $client_id;
            }
        }
        if (empty($site_url)) {
            wp_send_json_error(array('message' => __('URL sito cliente non disponibile. Aspetta la prossima sincronizzazione automatica.', 'fp-git-updater')));
        }

        // Recupera il secret configurato sul Master
        $secret = get_option(MasterEndpoint::OPTION_MASTER_CLIENT_SECRET, '');
        if (empty($secret)) {
            wp_send_json_error(array('message' => __('Chiave segreta Master non configurata.', 'fp-git-updater')));
        }

        // Chiama l'endpoint /plugin-versions sul Bridge del cliente
        $endpoint = rtrim($site_url, '/') . '/wp-json/fp-remote-bridge/v1/plugin-versions';
        $response = wp_remote_get($endpoint, [
            'timeout' => 15,
            'headers' => [
                'X-FP-Client-Secret' => $secret,
            ],
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => __('Impossibile contattare il sito cliente: ', 'fp-git-updater') . $response->get_error_message()));
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            wp_send_json_error(array('message' => sprintf(__('Il sito cliente ha risposto con errore HTTP %d.', 'fp-git-updater'), $code)));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['success']) || !isset($body['plugins'])) {
            wp_send_json_error(array('message' => __('Risposta non valida dal sito cliente.', 'fp-git-updater')));
        }

        // Aggiorna i dati del cliente nel DB del Master
        $plugins_map = $body['plugins']; // ['slug' => 'version', ...]
        $slugs = array_keys($plugins_map);
        $all_clients[$client_id]['installed_plugins'] = $slugs;
        $all_clients[$client_id]['plugin_versions']   = $plugins_map;
        $all_clients[$client_id]['last_seen']         = time();
        update_option(MasterEndpoint::OPTION_CONNECTED_CLIENTS, $all_clients);

        wp_send_json_success(array(
            'message'  => sprintf(__('Versioni aggiornate: %d plugin trovati su %s.', 'fp-git-updater'), count($slugs), $client_id),
            'plugins'  => $plugins_map,
            'count'    => count($slugs),
        ));
    }

    /**
     * AJAX: Sblocca aggiornamento bloccato (rimuove lock orfano)
     */
    public function ajax_clear_update_lock() {
        check_ajax_referer('fp_git_updater_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Accesso negato.', 'fp-git-updater')), 403);
        }

        $plugin_id = isset($_POST['plugin_id']) ? sanitize_text_field(wp_unslash($_POST['plugin_id'])) : 'fp_git_updater_self';
        $lock_key = 'fp_git_updater_lock_' . $plugin_id;
        delete_transient($lock_key);

        Logger::log('info', 'Lock aggiornamento rimosso manualmente per: ' . $plugin_id);

        wp_send_json_success(array('message' => __('Sblocco completato. Puoi riprovare l\'aggiornamento.', 'fp-git-updater')));
    }
}
