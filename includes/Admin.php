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
        add_filter('admin_body_class', array($this, 'filter_admin_body_class'));
        
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
        add_action('wp_ajax_fp_git_updater_deploy_trigger_client', array($this, 'ajax_deploy_trigger_client'));
        add_action('wp_ajax_fp_git_updater_refresh_clients', array($this, 'ajax_refresh_clients'));
        add_action('wp_ajax_fp_git_updater_clear_update_lock', array($this, 'ajax_clear_update_lock'));
        add_action('wp_ajax_fp_git_updater_remove_client', array($this, 'ajax_remove_client'));
        add_action('wp_ajax_fp_git_updater_refresh_client_versions', array($this, 'ajax_refresh_client_versions'));
        add_action('wp_ajax_fp_git_updater_refresh_single_client_versions', array($this, 'ajax_refresh_single_client_versions'));
        add_action('wp_ajax_fp_git_updater_sync_client_version', array($this, 'ajax_sync_client_version'));
        add_action('wp_ajax_fp_git_updater_edit_client', array($this, 'ajax_edit_client'));
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
     * Aggiunge classe body per skin admin allineata al design system FP.
     *
     * @param string $classes Classi CSS del body (admin).
     * @return string
     */
    public function filter_admin_body_class($classes) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && strpos((string) $screen->id, 'fp-git-updater') !== false) {
            $classes .= ' fpgitupdater-admin-shell';
        }

        return $classes;
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        $page     = isset($_GET['page']) ? sanitize_text_field(wp_unslash((string) $_GET['page'])) : '';
        $our_pages = array('fp-git-updater', 'fp-git-updater-backup', 'fp-git-updater-logs');
        $is_our_screen = (strpos((string) $hook, 'fp-git-updater') !== false)
            || in_array($page, $our_pages, true);

        if (!$is_our_screen) {
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
        <div class="wrap fp-git-updater-wrap fpgitupdater-admin-page">
            <h1 class="screen-reader-text"><?php esc_html_e('FP Updater — Log', 'fp-git-updater'); ?></h1>

            <div class="fpgitupdater-page-header">
                <div class="fpgitupdater-page-header-content">
                    <h2 class="fpgitupdater-page-header-title" aria-hidden="true">
                        <span class="dashicons dashicons-list-view" aria-hidden="true"></span>
                        <?php esc_html_e('Log attività', 'fp-git-updater'); ?>
                    </h2>
                    <p class="fpgitupdater-page-header-desc">
                        <?php esc_html_e('Registro eventi, errori e operazioni del plugin.', 'fp-git-updater'); ?>
                    </p>
                </div>
                <span class="fpgitupdater-page-header-badge"><?php echo esc_html('v' . FP_GIT_UPDATER_VERSION); ?></span>
            </div>

            <div class="fpgitupdater-toolbar fp-logs-actions">
                <button type="button" id="fp-clear-logs" class="button fpgitupdater-btn fpgitupdater-btn-secondary">
                    <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                    <?php esc_html_e('Pulisci log', 'fp-git-updater'); ?>
                </button>
                <button type="button" class="button fpgitupdater-btn fpgitupdater-btn-secondary" onclick="location.reload()">
                    <span class="dashicons dashicons-update" aria-hidden="true"></span>
                    <?php esc_html_e('Ricarica', 'fp-git-updater'); ?>
                </button>
            </div>

            <div class="fpgitupdater-table-scroll">
                <table class="wp-list-table widefat fixed striped fpgitupdater-wp-table">
                    <thead>
                        <tr>
                            <th class="fpgitupdater-col-date"><?php esc_html_e('Data/ora', 'fp-git-updater'); ?></th>
                            <th class="fpgitupdater-col-type"><?php esc_html_e('Tipo', 'fp-git-updater'); ?></th>
                            <th><?php esc_html_e('Messaggio', 'fp-git-updater'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)) : ?>
                            <tr>
                                <td colspan="3" class="fpgitupdater-table-empty">
                                    <?php esc_html_e('Nessun log disponibile.', 'fp-git-updater'); ?>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($logs as $log) : ?>
                                <tr class="log-<?php echo esc_attr($log->log_type); ?>">
                                    <td><?php echo esc_html($log->log_date); ?></td>
                                    <td>
                                        <span class="log-badge log-badge-<?php echo esc_attr($log->log_type); ?>">
                                            <?php echo esc_html($log->log_type); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo esc_html($log->message); ?>
                                        <?php if ($log->details) : ?>
                                            <details class="fpgitupdater-log-details">
                                                <summary class="fpgitupdater-log-details-summary"><?php esc_html_e('Dettagli', 'fp-git-updater'); ?></summary>
                                                <pre class="fpgitupdater-log-pre is-monospace"><?php echo esc_html(print_r(json_decode($log->details, true), true)); ?></pre>
                                            </details>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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
        <div class="wrap fp-git-updater-wrap fpgitupdater-admin-page">
            <h1 class="screen-reader-text"><?php esc_html_e('FP Updater — Backup e ripristino', 'fp-git-updater'); ?></h1>

            <div class="fpgitupdater-page-header">
                <div class="fpgitupdater-page-header-content">
                    <h2 class="fpgitupdater-page-header-title" aria-hidden="true">
                        <span class="dashicons dashicons-database" aria-hidden="true"></span>
                        <?php esc_html_e('Backup e ripristino', 'fp-git-updater'); ?>
                    </h2>
                    <p class="fpgitupdater-page-header-desc">
                        <?php esc_html_e('Stato backup, cronologia e ripristino delle impostazioni del plugin.', 'fp-git-updater'); ?>
                    </p>
                </div>
                <span class="fpgitupdater-page-header-badge"><?php echo esc_html('v' . FP_GIT_UPDATER_VERSION); ?></span>
            </div>

            <?php if ($has_settings_reset) : ?>
                <div class="notice notice-warning fpgitupdater-notice-compat">
                    <p>
                        <strong><?php esc_html_e('Attenzione!', 'fp-git-updater'); ?></strong>
                        <?php esc_html_e('Le tue impostazioni sembrano essere state resettate. È disponibile un backup che puoi ripristinare.', 'fp-git-updater'); ?>
                    </p>
                    <p>
                        <button type="button" id="fp-quick-restore" class="button button-primary fpgitupdater-btn fpgitupdater-btn-primary">
                            <span class="dashicons dashicons-backup" aria-hidden="true"></span>
                            <?php esc_html_e('Ripristina ora', 'fp-git-updater'); ?>
                        </button>
                    </p>
                </div>
            <?php endif; ?>

            <div class="fp-git-updater-header fpgitupdater-card-block">
                <div class="fpgitupdater-card-block__head">
                    <h2 class="fpgitupdater-card-block__title"><?php esc_html_e('Stato attuale', 'fp-git-updater'); ?></h2>
                </div>
                <div class="fpgitupdater-card-block__body">
                    <table class="form-table fpgitupdater-form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Plugin configurati', 'fp-git-updater'); ?></th>
                            <td>
                                <strong><?php echo (int) count(is_array($current_settings) ? ($current_settings['plugins'] ?? array()) : array()); ?></strong>
                                <?php esc_html_e('plugin', 'fp-git-updater'); ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Ultimo backup', 'fp-git-updater'); ?></th>
                            <td>
                                <?php if ($latest_backup) : ?>
                                    <?php echo esc_html($latest_backup['timestamp']); ?>
                                    (<?php echo $latest_backup['manual'] ? esc_html__('Manuale', 'fp-git-updater') : esc_html__('Automatico', 'fp-git-updater'); ?>)
                                <?php else : ?>
                                    <em><?php esc_html_e('Nessun backup disponibile', 'fp-git-updater'); ?></em>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>

                    <div class="fpgitupdater-toolbar fpgitupdater-toolbar--spaced">
                        <button type="button" id="fp-create-backup" class="button button-primary fpgitupdater-btn fpgitupdater-btn-primary">
                            <span class="dashicons dashicons-database-add" aria-hidden="true"></span>
                            <?php esc_html_e('Crea backup manuale', 'fp-git-updater'); ?>
                        </button>
                        <?php if ($latest_backup) : ?>
                            <button type="button" id="fp-restore-latest" class="button fpgitupdater-btn fpgitupdater-btn-secondary">
                                <span class="dashicons dashicons-backup" aria-hidden="true"></span>
                                <?php esc_html_e('Ripristina ultimo backup', 'fp-git-updater'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="fp-git-updater-instructions fpgitupdater-card-block fpgitupdater-card-block--muted">
                <div class="fpgitupdater-card-block__head">
                    <h2 class="fpgitupdater-card-block__title"><?php esc_html_e('Cronologia backup', 'fp-git-updater'); ?></h2>
                </div>
                <div class="fpgitupdater-card-block__body">
                    <?php if (empty($backup_history)) : ?>
                        <p class="description"><?php esc_html_e('Nessun backup disponibile nella cronologia.', 'fp-git-updater'); ?></p>
                    <?php else : ?>
                        <div class="fpgitupdater-table-scroll">
                            <table class="wp-list-table widefat fixed striped fpgitupdater-wp-table">
                                <thead>
                                    <tr>
                                        <th class="fpgitupdater-col-date"><?php esc_html_e('Data/ora', 'fp-git-updater'); ?></th>
                                        <th class="fpgitupdater-col-type"><?php esc_html_e('Tipo', 'fp-git-updater'); ?></th>
                                        <th class="fpgitupdater-col-version"><?php esc_html_e('Versione', 'fp-git-updater'); ?></th>
                                        <th><?php esc_html_e('Plugin salvati', 'fp-git-updater'); ?></th>
                                        <th class="fpgitupdater-col-actions"><?php esc_html_e('Azioni', 'fp-git-updater'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backup_history as $index => $backup) : ?>
                                        <tr>
                                            <td><?php echo esc_html($backup['timestamp']); ?></td>
                                            <td>
                                                <span class="log-badge log-badge-<?php echo $backup['manual'] ? 'info' : 'success'; ?>">
                                                    <?php echo $backup['manual'] ? esc_html__('Manuale', 'fp-git-updater') : esc_html__('Automatico', 'fp-git-updater'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo esc_html($backup['version'] ?? 'N/A'); ?></td>
                                            <td>
                                                <strong><?php echo count($backup['settings']['plugins'] ?? array()); ?></strong>
                                                <?php esc_html_e('plugin', 'fp-git-updater'); ?>
                                                <?php if (!empty($backup['settings']['plugins'])) : ?>
                                                    <details class="fpgitupdater-log-details">
                                                        <summary class="fpgitupdater-log-details-summary"><?php esc_html_e('Vedi dettagli', 'fp-git-updater'); ?></summary>
                                                        <ul class="fpgitupdater-prose-list">
                                                            <?php foreach ($backup['settings']['plugins'] as $plugin) : ?>
                                                                <li>
                                                                    <strong><?php echo esc_html($plugin['name']); ?></strong><br>
                                                                    <small><?php esc_html_e('Repository:', 'fp-git-updater'); ?> <?php echo esc_html($plugin['github_repo']); ?></small>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </details>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="button button-small fp-restore-backup-btn fpgitupdater-btn fpgitupdater-btn-secondary fpgitupdater-btn--sm" data-backup-index="<?php echo esc_attr((string) $index); ?>">
                                                    <span class="dashicons dashicons-backup" aria-hidden="true"></span>
                                                    <?php esc_html_e('Ripristina', 'fp-git-updater'); ?>
                                                </button>
                                                <button type="button" class="button button-small fp-delete-backup-btn fpgitupdater-btn fpgitupdater-btn-ghost fpgitupdater-btn--sm" data-backup-index="<?php echo esc_attr((string) $index); ?>">
                                                    <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                                                    <?php esc_html_e('Elimina', 'fp-git-updater'); ?>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="fp-git-updater-instructions fpgitupdater-card-block fpgitupdater-card-block--help">
                <div class="fpgitupdater-card-block__head">
                    <h2 class="fpgitupdater-card-block__title"><?php esc_html_e('Come funziona il backup automatico', 'fp-git-updater'); ?></h2>
                </div>
                <div class="fpgitupdater-card-block__body">
                    <p><?php esc_html_e('Il sistema di backup protegge automaticamente le tue impostazioni:', 'fp-git-updater'); ?></p>
                    <ul class="fpgitupdater-prose-list">
                        <li><strong><?php esc_html_e('Prima degli aggiornamenti:', 'fp-git-updater'); ?></strong> <?php esc_html_e('viene creato automaticamente un backup prima di ogni aggiornamento del plugin.', 'fp-git-updater'); ?></li>
                        <li><strong><?php esc_html_e('Dopo l’attivazione:', 'fp-git-updater'); ?></strong> <?php esc_html_e('se le impostazioni sono state resettate, vengono ripristinate automaticamente dal backup.', 'fp-git-updater'); ?></li>
                        <li><strong><?php esc_html_e('Backup manuali:', 'fp-git-updater'); ?></strong> <?php esc_html_e('puoi crearne in qualsiasi momento con il pulsante sopra.', 'fp-git-updater'); ?></li>
                        <li><strong><?php esc_html_e('Cronologia:', 'fp-git-updater'); ?></strong> <?php esc_html_e('vengono conservati gli ultimi 10 backup per sicurezza.', 'fp-git-updater'); ?></li>
                    </ul>
                    <h3 class="fpgitupdater-card-block__subtitle"><?php esc_html_e('Quando usare il ripristino manuale', 'fp-git-updater'); ?></h3>
                    <ul class="fpgitupdater-prose-list">
                        <li><?php esc_html_e('Se il ripristino automatico non è andato a buon fine.', 'fp-git-updater'); ?></li>
                        <li><?php esc_html_e('Se vuoi tornare a una configurazione precedente specifica.', 'fp-git-updater'); ?></li>
                        <li><?php esc_html_e('Se hai accidentalmente cancellato delle impostazioni.', 'fp-git-updater'); ?></li>
                    </ul>
                </div>
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
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-database-add"></span> <?php echo esc_js(__('Crea backup manuale', 'fp-git-updater')); ?>');
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
                return;
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
                return;
            }
            
            $repos = json_decode($body, true);
            
            if (!is_array($repos)) {
                wp_send_json_error(array('message' => 'Risposta API GitHub non valida'), 500);
                return;
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
            if (is_wp_error($commit_info)) {
                wp_send_json_error(array('message' => 'Impossibile contattare GitHub: ' . $commit_info->get_error_message()), 500);
                return;
            }
            $commit_sha   = $commit_info['sha'];
            $commit_short = $commit_info['short'];
            $commit_msg   = $commit_info['message'];
            $commit_date  = $commit_info['date'];

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

        MasterEndpoint::authorize_deploy_install($plugin, $client_ids, true);
        $until = (int) get_option(MasterEndpoint::OPTION_DEPLOY_AUTHORIZED_UNTIL, 0);
        wp_send_json_success(array(
            'message' => sprintf(
                __('Installazione autorizzata: %s su %d clienti. I siti installeranno nelle prossime 2 ore.', 'fp-git-updater'),
                esc_html($plugin['name']),
                count($client_ids)
            ),
            'valid_until'     => $until,
            'trigger_targets' => $client_ids,
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

        // Usa lo slug per la fase di push immediato (trigger-sync),
        // mantenendo compatibilità con il lookup plugin lato Master.
        $deploy_key = !empty($plugin_slug) ? $plugin_slug : $plugin_id;
        $clients    = MasterEndpoint::get_clients_with_plugin($plugin_slug);
        MasterEndpoint::authorize_deploy_update(array($deploy_key), true);
        $until = (int) get_option(MasterEndpoint::OPTION_DEPLOY_AUTHORIZED_UNTIL, 0);
        wp_send_json_success(array(
            'message' => sprintf(
                __('Aggiornamento autorizzato. %d clienti aggiorneranno nelle prossime 2 ore.', 'fp-git-updater'),
                count($clients)
            ),
            'valid_until'     => $until,
            'trigger_targets' => $clients,
        ));
    }

    /**
     * AJAX: trigger-sync bloccante verso un singolo client (sequenza con barra avanzamento in admin).
     */
    public function ajax_deploy_trigger_client() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fp_git_updater_nonce')) {
            wp_send_json_error(array('message' => __('Nonce non valido.', 'fp-git-updater')), 400);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permessi insufficienti.', 'fp-git-updater')), 403);
        }

        $client_id = isset($_POST['client_id']) ? sanitize_text_field(wp_unslash($_POST['client_id'])) : '';
        if ($client_id === '') {
            wp_send_json_error(array('message' => __('Client ID mancante.', 'fp-git-updater')), 400);
        }

        $until = (int) get_option(MasterEndpoint::OPTION_DEPLOY_AUTHORIZED_UNTIL, 0);
        if ($until <= time()) {
            wp_send_json_error(array(
                'message' => __('La finestra di distribuzione è scaduta. Ripeti l\'operazione da «Installa» o «Aggiorna tutti».', 'fp-git-updater'),
            ), 400);
        }

        $r = MasterEndpoint::trigger_sync_client_blocking($client_id);

        $message = '';
        if ($r['ok']) {
            $message = sprintf(
                /* translators: %s: client id (domain or URL) */
                __('Sincronizzazione avviata su %s.', 'fp-git-updater'),
                $client_id
            );
        } elseif ($r['error'] === 'no_secret') {
            $message = __('Chiave segreta Master non configurata: impossibile contattare il sito cliente.', 'fp-git-updater');
        } elseif ($r['error'] === 'unknown_client') {
            $message = sprintf(
                /* translators: %s: client id */
                __('Cliente «%s» non trovato nell’elenco collegati.', 'fp-git-updater'),
                $client_id
            );
        } elseif ($r['error'] === 'request_failed' && !empty($r['detail'])) {
            $message = sprintf(
                /* translators: 1: client id, 2: WordPress/HTTP error message */
                __('Connessione a %1$s non riuscita: %2$s', 'fp-git-updater'),
                $client_id,
                $r['detail']
            );
        } elseif ($r['error'] === 'bad_status') {
            $message = sprintf(
                /* translators: 1: client id, 2: HTTP status code */
                __('Risposta HTTP %2$d dal sito %1$s (trigger-sync).', 'fp-git-updater'),
                $client_id,
                $r['http_code']
            );
        } else {
            $message = sprintf(
                /* translators: %s: client id */
                __('Impossibile avviare la sincronizzazione su %s.', 'fp-git-updater'),
                $client_id
            );
        }

        wp_send_json_success(array(
            'ok'         => $r['ok'],
            'http_code'  => $r['http_code'],
            'client_id'  => $client_id,
            'message'    => $message,
            'error_code' => $r['error'],
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
            echo '<table class="wp-list-table widefat fixed striped fp-master-clients-table fpgitupdater-wp-table"><thead><tr>';
            echo '<th scope="col" style="width:32%;">' . esc_html__('Sito cliente', 'fp-git-updater') . '</th>';
            echo '<th scope="col" style="width:33%;">' . esc_html__('Plugin installati', 'fp-git-updater') . '</th>';
            echo '<th scope="col">' . esc_html__('Ultima connessione', 'fp-git-updater') . '</th>';
            echo '<th scope="col" style="width:80px;"></th></tr></thead><tbody>';
            foreach ($clients as $client_id => $data) {
                $installed = $data['installed_plugins'] ?? [];
                $plugin_versions = $data['plugin_versions'] ?? [];
                $count_plugins = count($installed);
                $installed_str = !empty($installed) ? implode(', ', array_slice($installed, 0, 8)) . ($count_plugins > 8 ? ' +' . ($count_plugins - 8) . '…' : '') : '—';
                $has_versions = !empty($plugin_versions);
                $display_name = !empty($data['site_name']) ? $data['site_name'] : $client_id;
                $row_id = 'fp-client-row-' . sanitize_html_class($client_id);
                echo '<tr id="' . esc_attr($row_id) . '">';
                echo '<td><strong>' . esc_html($display_name) . '</strong>';
                if (!empty($data['site_name'])) {
                    echo '<br><small style="color:var(--fp-text-muted);">' . esc_html($client_id) . '</small>';
                }
                echo '</td>';
                // Colonna plugin: con versioni inline se disponibili, altrimenti solo slug
                echo '<td class="fp-client-versions-cell" data-client-id="' . esc_attr($client_id) . '">';
                if ($has_versions) {
                    echo '<div class="fp-client-plugins-with-versions">';
                    $i = 0;
                    foreach ($plugin_versions as $slug => $ver) {
                        if ($i >= 8) break;
                        echo '<span class="fp-client-plugin-entry">'
                            . '<span class="fp-client-plugin-slug">' . esc_html($slug) . '</span>'
                            . ' <span class="fp-deploy-client-ver fp-deploy-client-ver--ok">v' . esc_html($ver) . '</span>'
                            . '</span>';
                        $i++;
                    }
                    if ($count_plugins > 8) {
                        echo '<span class="fp-version-more">+' . ($count_plugins - 8) . ' altri</span>';
                    }
                    echo '</div>';
                } else {
                    echo '<small class="fp-client-plugins-list" data-client-id="' . esc_attr($client_id) . '">' . esc_html($installed_str) . '</small>';
                    if ($count_plugins > 0) {
                        echo ' <small style="color:var(--fp-text-muted);">(' . $count_plugins . ')</small>';
                    }
                }
                echo '</td>';
                echo '<td>' . esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $data['last_seen'] ?? 0)) . '</td>';
                echo '<td style="white-space:nowrap;">';
                echo '<button type="button" class="button button-small fp-refresh-client-versions-btn" data-client-id="' . esc_attr($client_id) . '" title="' . esc_attr__('Aggiorna versioni plugin da questo sito', 'fp-git-updater') . '" style="margin-right:4px;"><span class="dashicons dashicons-update" style="margin-top:3px;"></span></button>';
                echo '<button type="button" class="button button-small fp-edit-client-btn" data-client-id="' . esc_attr($client_id) . '" data-client-url="' . esc_attr($data['url'] ?? '') . '" title="' . esc_attr__('Modifica cliente', 'fp-git-updater') . '" style="margin-right:4px;"><span class="dashicons dashicons-edit" style="margin-top:3px;"></span></button>';
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
            return;
        }
        $clients = MasterEndpoint::get_connected_clients();
        if (!isset($clients[$client_id])) {
            wp_send_json_error(array('message' => __('Cliente non trovato.', 'fp-git-updater')));
            return;
        }
        $client_data = $clients[$client_id];
        unset($clients[$client_id]);
        update_option(MasterEndpoint::OPTION_CONNECTED_CLIENTS, $clients);

        // Marca il client come rimosso manualmente per evitare che si ricrei al ping successivo.
        $removed = get_option(MasterEndpoint::OPTION_REMOVED_CLIENTS, []);
        if (!is_array($removed)) {
            $removed = [];
        }
        $removed[$client_id] = time();
        $normalized_id = MasterEndpoint::normalize_client_id($client_id);
        $removed[$normalized_id] = time();
        $client_url = $client_data['url'] ?? '';
        if (is_string($client_url) && $client_url !== '') {
            $client_host = wp_parse_url($client_url, PHP_URL_HOST);
            if (is_string($client_host) && $client_host !== '') {
                $removed[MasterEndpoint::normalize_client_id($client_host)] = time();
            }
        }
        update_option(MasterEndpoint::OPTION_REMOVED_CLIENTS, $removed);

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
            return;
        }

        $all_clients = get_option(MasterEndpoint::OPTION_CONNECTED_CLIENTS, []);
        if (!isset($all_clients[$client_id])) {
            wp_send_json_error(array('message' => __('Cliente non trovato.', 'fp-git-updater')));
            return;
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
            return;
        }

        // Recupera il secret configurato sul Master
        $secret = get_option(MasterEndpoint::OPTION_MASTER_CLIENT_SECRET, '');
        if (empty($secret)) {
            wp_send_json_error(array('message' => __('Chiave segreta Master non configurata. Vai nelle impostazioni e salva la chiave segreta.', 'fp-git-updater')));
            return;
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
            return;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            if ($code === 401 || $code === 403) {
                $msg = sprintf(
                    __('Accesso negato dal sito cliente (HTTP %d). Verifica che la chiave segreta Master sia identica su entrambi i siti.', 'fp-git-updater'),
                    $code
                );
            } else {
                $msg = sprintf(__('Il sito cliente ha risposto con errore HTTP %d.', 'fp-git-updater'), $code);
            }
            wp_send_json_error(array('message' => $msg));
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['success']) || !isset($body['plugins'])) {
            wp_send_json_error(array('message' => __('Risposta non valida dal sito cliente.', 'fp-git-updater')));
            return;
        }

        // Aggiorna i dati del cliente nel DB del Master
        $plugins_map = $body['plugins']; // ['slug' => 'version', ...]
        $slugs = array_keys($plugins_map);
        $all_clients[$client_id]['installed_plugins'] = $slugs;
        $all_clients[$client_id]['plugin_versions']   = $plugins_map;
        $all_clients[$client_id]['last_seen']         = time();
        if (!empty($body['site_name'])) {
            $all_clients[$client_id]['site_name'] = sanitize_text_field($body['site_name']);
        }
        update_option(MasterEndpoint::OPTION_CONNECTED_CLIENTS, $all_clients);

        wp_send_json_success(array(
            'message'  => sprintf(__('Versioni aggiornate: %d plugin trovati su %s.', 'fp-git-updater'), count($slugs), $all_clients[$client_id]['site_name'] ?? $client_id),
            'plugins'  => $plugins_map,
            'count'    => count($slugs),
        ));
    }

    /**
     * AJAX: Interroga un singolo cliente e restituisce le versioni plugin (usato da "Versioni in tempo reale")
     * Versione leggera di ajax_refresh_client_versions: restituisce solo i dati, non aggiorna il DB.
     */
    public function ajax_refresh_single_client_versions() {
        check_ajax_referer('fp_git_updater_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Accesso negato.', 'fp-git-updater')), 403);
        }

        $client_id = sanitize_text_field(wp_unslash($_POST['client_id'] ?? ''));
        if (empty($client_id)) {
            wp_send_json_error(array('message' => __('ID cliente mancante.', 'fp-git-updater')));
            return;
        }

        $all_clients = get_option(MasterEndpoint::OPTION_CONNECTED_CLIENTS, []);
        if (!isset($all_clients[$client_id])) {
            wp_send_json_error(array('message' => __('Cliente non trovato.', 'fp-git-updater')));
            return;
        }

        $client_data = $all_clients[$client_id];
        $site_url = $client_data['url'] ?? '';
        if (empty($site_url)) {
            if (preg_match('#^[a-z0-9]([a-z0-9\-\.]+)?[a-z0-9]\.[a-z]{2,}$#i', $client_id)) {
                $site_url = 'https://' . $client_id;
            }
        }
        if (empty($site_url)) {
            wp_send_json_error(array('message' => __('URL sito cliente non disponibile.', 'fp-git-updater')));
            return;
        }

        $secret = get_option(MasterEndpoint::OPTION_MASTER_CLIENT_SECRET, '');
        if (empty($secret)) {
            wp_send_json_error(array('message' => __('Chiave segreta Master non configurata.', 'fp-git-updater')));
            return;
        }

        $endpoint = rtrim($site_url, '/') . '/wp-json/fp-remote-bridge/v1/plugin-versions';
        $response = wp_remote_get($endpoint, [
            'timeout' => 15,
            'headers' => [
                'X-FP-Client-Secret' => $secret,
            ],
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => __('Impossibile contattare il sito: ', 'fp-git-updater') . $response->get_error_message(),
                'client_id' => $client_id,
            ));
            return;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $msg = $code === 401 || $code === 403
                ? sprintf(__('Accesso negato (HTTP %d). Verifica la chiave segreta.', 'fp-git-updater'), $code)
                : sprintf(__('Errore HTTP %d dal sito cliente.', 'fp-git-updater'), $code);
            wp_send_json_error(array('message' => $msg, 'client_id' => $client_id));
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['success']) || !isset($body['plugins'])) {
            wp_send_json_error(array('message' => __('Risposta non valida dal sito cliente.', 'fp-git-updater'), 'client_id' => $client_id));
            return;
        }

        $plugins_map = $body['plugins'];
        $slugs = array_keys($plugins_map);

        // Aggiorna anche il DB del Master
        $all_clients[$client_id]['installed_plugins'] = $slugs;
        $all_clients[$client_id]['plugin_versions']   = $plugins_map;
        $all_clients[$client_id]['last_seen']         = time();
        if (!empty($body['site_name'])) {
            $all_clients[$client_id]['site_name'] = sanitize_text_field($body['site_name']);
        }
        update_option(MasterEndpoint::OPTION_CONNECTED_CLIENTS, $all_clients);

        wp_send_json_success(array(
            'client_id' => $client_id,
            'plugins'   => $plugins_map,
            'count'     => count($slugs),
        ));
    }

    /**
     * AJAX: Forza connessione con un sito cliente e restituisce la versione di un plugin specifico.
     * Usato dal pulsante refresh inline nella griglia "Installa su clienti" di ogni plugin.
     */
    public function ajax_sync_client_version() {
        check_ajax_referer('fp_git_updater_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Accesso negato.', 'fp-git-updater')), 403);
        }

        $client_id   = sanitize_text_field(wp_unslash($_POST['client_id'] ?? ''));
        $plugin_slug = sanitize_text_field(wp_unslash($_POST['plugin_slug'] ?? ''));

        if (empty($client_id)) {
            wp_send_json_error(array('message' => __('ID cliente mancante.', 'fp-git-updater')));
            return;
        }

        $all_clients = get_option(MasterEndpoint::OPTION_CONNECTED_CLIENTS, []);
        if (!isset($all_clients[$client_id])) {
            wp_send_json_error(array('message' => __('Cliente non trovato.', 'fp-git-updater')));
            return;
        }

        $client_data = $all_clients[$client_id];
        $site_url    = $client_data['url'] ?? '';

        // Fallback: costruisci URL dal client_id se sembra un dominio (con o senza www)
        if (empty($site_url)) {
            $cid_clean = preg_replace('#^https?://#', '', $client_id);
            if (preg_match('#^[a-z0-9]([a-z0-9\-\.]+)?[a-z0-9]\.[a-z]{2,}(/.*)?$#i', $cid_clean)) {
                $site_url = 'https://' . ltrim($cid_clean, '/');
            }
        }

        if (empty($site_url)) {
            wp_send_json_error(array('message' => sprintf(
                __('URL sito non disponibile per "%s". Vai sul sito cliente → Impostazioni → FP Remote Bridge → Sincronizza ora, poi riprova.', 'fp-git-updater'),
                $client_id
            )));
            return;
        }

        $secret = get_option(MasterEndpoint::OPTION_MASTER_CLIENT_SECRET, '');
        if (empty($secret)) {
            wp_send_json_error(array('message' => __('Chiave segreta Master non configurata.', 'fp-git-updater')));
            return;
        }

        $endpoint = rtrim($site_url, '/') . '/wp-json/fp-remote-bridge/v1/plugin-versions';
        $response = wp_remote_get($endpoint, [
            'timeout' => 15,
            'headers' => [ 'X-FP-Client-Secret' => $secret ],
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
            return;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $msg = ($code === 401 || $code === 403)
                ? sprintf(__('Accesso negato (HTTP %d). Verifica la chiave segreta.', 'fp-git-updater'), $code)
                : sprintf(__('Errore HTTP %d.', 'fp-git-updater'), $code);
            wp_send_json_error(array('message' => $msg));
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['success']) || !isset($body['plugins'])) {
            wp_send_json_error(array('message' => __('Risposta non valida dal sito cliente.', 'fp-git-updater')));
            return;
        }

        $plugins_map = $body['plugins'];
        $slugs       = array_keys($plugins_map);

        // Salva nel DB del Master
        $all_clients[$client_id]['installed_plugins'] = $slugs;
        $all_clients[$client_id]['plugin_versions']   = $plugins_map;
        $all_clients[$client_id]['last_seen']         = time();
        if (!empty($body['site_name'])) {
            $all_clients[$client_id]['site_name'] = sanitize_text_field($body['site_name']);
        }
        update_option(MasterEndpoint::OPTION_CONNECTED_CLIENTS, $all_clients);

        // Versione del plugin specifico richiesto
        $plugin_version = !empty($plugin_slug) ? ($plugins_map[$plugin_slug] ?? '') : '';

        wp_send_json_success(array(
            'client_id'      => $client_id,
            'plugin_slug'    => $plugin_slug,
            'plugin_version' => $plugin_version,
            'all_plugins'    => $plugins_map,
        ));
    }

    /**
     * AJAX: Modifica dati di un cliente (client_id e/o url)
     */
    public function ajax_edit_client() {
        check_ajax_referer('fp_git_updater_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Accesso negato.', 'fp-git-updater')), 403);
        }

        $old_client_id = sanitize_text_field(wp_unslash($_POST['client_id'] ?? ''));
        $new_client_id = sanitize_text_field(wp_unslash($_POST['new_client_id'] ?? ''));
        $new_url       = esc_url_raw(wp_unslash($_POST['new_url'] ?? ''));

        if (empty($old_client_id)) {
            wp_send_json_error(array('message' => __('ID cliente mancante.', 'fp-git-updater')));
            return;
        }

        $clients = get_option(MasterEndpoint::OPTION_CONNECTED_CLIENTS, []);
        if (!isset($clients[$old_client_id])) {
            wp_send_json_error(array('message' => __('Cliente non trovato.', 'fp-git-updater')));
            return;
        }

        $new_client_id = !empty($new_client_id) ? $new_client_id : $old_client_id;

        // Se il client_id cambia, rinomina la chiave e registra l'alias (così le riconnessioni con il vecchio ID aggiornano l'entry con il nome nuovo)
        if ($new_client_id !== $old_client_id) {
            if (isset($clients[$new_client_id])) {
                wp_send_json_error(array('message' => sprintf(
                    __('Esiste già un cliente con ID "%s".', 'fp-git-updater'),
                    $new_client_id
                )));
                return;
            }
            $clients[$new_client_id] = $clients[$old_client_id];
            unset($clients[$old_client_id]);

            $aliases = get_option(MasterEndpoint::OPTION_CLIENT_ID_ALIASES, []);
            if (!is_array($aliases)) {
                $aliases = [];
            }
            $aliases[$old_client_id] = $new_client_id;
            $normalized_old = MasterEndpoint::normalize_client_id($old_client_id);
            if ($normalized_old !== $old_client_id) {
                $aliases[$normalized_old] = $new_client_id;
            }
            update_option(MasterEndpoint::OPTION_CLIENT_ID_ALIASES, $aliases);
        }

        // Aggiorna URL
        $clients[$new_client_id]['url'] = $new_url;

        update_option(MasterEndpoint::OPTION_CONNECTED_CLIENTS, $clients);

        wp_send_json_success(array(
            'message'        => __('Cliente aggiornato.', 'fp-git-updater'),
            'old_client_id'  => $old_client_id,
            'new_client_id'  => $new_client_id,
            'new_url'        => $new_url,
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
