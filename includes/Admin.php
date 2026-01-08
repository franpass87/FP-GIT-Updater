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
        $menu_title = 'Git Updater';
        if ($pending_count > 0) {
            $menu_title .= ' <span class="update-plugins count-' . $pending_count . '"><span class="update-count">' . $pending_count . '</span></span>';
        }
        
        add_menu_page(
            'FP Git Updater',
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
     * Registra le impostazioni
     */
    public function register_settings() {
        register_setting('fp_git_updater_settings_group', 'fp_git_updater_settings', array(
            'sanitize_callback' => array($this, 'sanitize_settings')
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
        
        // Sanitizza username GitHub predefinito
        $default_github_username = isset($input['default_github_username']) ? sanitize_text_field($input['default_github_username']) : '';
        if (!empty($default_github_username)) {
            // Valida username (solo caratteri alfanumerici e trattini)
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $default_github_username)) {
                Logger::log('error', 'Username GitHub non valido: ' . $default_github_username);
                add_settings_error(
                    'fp_git_updater_settings',
                    'invalid_github_username',
                    sprintf(__('Username GitHub non valido: "%s". Usa solo lettere, numeri, _ e -', 'fp-git-updater'), $default_github_username)
                );
                $default_github_username = '';
            }
        }
        $output['default_github_username'] = $default_github_username;
        
        // Sanitizza la lista di plugin
        if (isset($input['plugins']) && is_array($input['plugins'])) {
            $output['plugins'] = array();
            $seen_repos = array(); // Per tracciare repository già visti ed evitare duplicati
            
            foreach ($input['plugins'] as $plugin) {
                $github_repo = isset($plugin['github_repo']) ? sanitize_text_field($plugin['github_repo']) : '';
                $branch = isset($plugin['branch']) ? sanitize_text_field($plugin['branch']) : 'main';

                // Auto-completa repository con username predefinito se manca lo slash
                if (!empty($github_repo) && !empty($default_github_username) && strpos($github_repo, '/') === false) {
                    $github_repo = $default_github_username . '/' . $github_repo;
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

                // Cripta il token GitHub se presente
                $github_token = isset($plugin['github_token']) ? sanitize_text_field($plugin['github_token']) : '';
                if (!empty($github_token)) {
                    if (strlen($github_token) > 500) {
                        Logger::log('error', 'Token GitHub troppo lungo (max 500 caratteri)');
                        add_settings_error(
                            'fp_git_updater_settings',
                            'token_too_long',
                            __('Token GitHub troppo lungo. Usa un token valido.', 'fp-git-updater')
                        );
                        $github_token = '';
                    } elseif (!$encryption->is_encrypted($github_token)) {
                        $encrypted_token = $encryption->encrypt($github_token);
                        $github_token = $encrypted_token !== false ? $encrypted_token : $github_token;
                    }
                }

                // Richiede almeno una sorgente (repo o zip)
                if (empty($github_repo) && empty($zip_url)) {
                    Logger::log('warning', 'Plugin ignorato: manca repository e URL ZIP');
                    continue;
                }

                // Se il repository è FP-GIT-Updater, usa l'ID speciale per l'auto-aggiornamento
                $plugin_id = isset($plugin['id']) ? sanitize_text_field($plugin['id']) : uniqid('plugin_');
                if (stripos($github_repo, 'FP-GIT-Updater') !== false || stripos($github_repo, 'FP-Git-Updater') !== false) {
                    $plugin_id = 'fp_git_updater_self';
                    Logger::log('info', 'Rilevato repository self-update, assegnato ID speciale: fp_git_updater_self');
                }
                
                $output['plugins'][] = array(
                    'id' => $plugin_id,
                    'name' => $plugin_name,
                    'github_repo' => $github_repo,
                    'plugin_slug' => $plugin_slug,
                    'branch' => $branch,
                    'github_token' => $github_token,
                    'zip_url' => $zip_url,
                    'enabled' => isset($plugin['enabled']) ? true : false,
                );
            }
        } else {
            $output['plugins'] = array();
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
            
            wp_localize_script('fp-git-updater-admin', 'fpGitUpdater', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('fp_git_updater_nonce')
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
        
        // Ottieni gli aggiornamenti pending
        $updater = Updater::get_instance();
        $pending_updates = $updater->get_pending_updates();
        $auto_update_enabled = isset($settings['auto_update']) ? $settings['auto_update'] : false;
        
        // Usa il template separato
        include FP_GIT_UPDATER_PLUGIN_DIR . 'includes/admin-templates/settings-page.php';
    }
    
    /**
     * Render pagina impostazioni (OLD - Deprecato, mantenuto per riferimento)
     * Ora usa i template in includes/admin-templates/
     */
    private function render_settings_page_old() {
        ?>
        <div class="wrap fp-git-updater-wrap">
            <h1>
                <span class="dashicons dashicons-update"></span>
                FP Git Updater - Impostazioni
                <?php if (!empty($pending_updates)): ?>
                    <span class="update-count"><?php echo count($pending_updates); ?></span>
                <?php endif; ?>
            </h1>
            
            <?php if (!empty($pending_updates)): ?>
                <div class="fp-notice fp-notice-info" style="border-left-color: #d63638; background: #fcf0f1;">
                    <h3 style="margin-top: 0;">⚠️ Aggiornamenti Disponibili (<?php echo count($pending_updates); ?>)</h3>
                    <p><strong>I seguenti plugin hanno aggiornamenti pronti per essere installati:</strong></p>
                    <ul style="margin-left: 20px;">
                        <?php foreach ($pending_updates as $pending): ?>
                            <li>
                                <strong><?php echo esc_html($pending['plugin_name']); ?></strong> - 
                                Commit: <code><?php echo esc_html($pending['commit_sha_short']); ?></code>
                                <?php if (!empty($pending['commit_message']) && $pending['commit_message'] !== 'Aggiornamento rilevato dal controllo schedulato'): ?>
                                    <br><em style="color: #666;"><?php echo esc_html($pending['commit_message']); ?></em>
                                    <?php if (!empty($pending['commit_author']) && $pending['commit_author'] !== 'Sistema'): ?>
                                        - da <?php echo esc_html($pending['commit_author']); ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <br><small>Ricevuto: <?php echo esc_html($pending['timestamp']); ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p style="margin-bottom: 0;">
                        <strong>Azione richiesta:</strong> Scorri in basso e clicca su "Installa Aggiornamento" per ogni plugin che vuoi aggiornare.
                    </p>
                </div>
            <?php endif; ?>
            
            <?php if (!$auto_update_enabled): ?>
                <div class="fp-notice fp-notice-info">
                    <p>
                        <span class="dashicons dashicons-shield-alt" style="color: #2271b1;"></span>
                        <strong>Modalità Aggiornamento Manuale Attiva</strong> - Gli aggiornamenti non verranno installati automaticamente. 
                        Riceverai una notifica quando sono disponibili e potrai installarli manualmente quando sei pronto.
                    </p>
                </div>
            <?php else: ?>
                <div class="fp-notice fp-notice-info" style="border-left-color: #dba617; background: #fcf9e8;">
                    <p>
                        <span class="dashicons dashicons-warning" style="color: #dba617;"></span>
                        <strong>Attenzione:</strong> Aggiornamento Automatico Attivo - I plugin verranno aggiornati automaticamente quando ricevi un push da GitHub.
                        <br>Se vuoi maggiore controllo e sicurezza per i siti dei tuoi clienti, disabilita questa opzione qui sotto.
                    </p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="options.php">
                <?php settings_fields('fp_git_updater_settings_group'); ?>
                
                <h2>Plugin Gestiti</h2>
                <p>Aggiungi e gestisci i plugin che vuoi aggiornare automaticamente da GitHub.</p>
                
                <div id="fp-plugins-list">
                    <?php if (!empty($plugins)): ?>
                        <?php foreach ($plugins as $index => $plugin): 
                            // Controlla se questo plugin ha un aggiornamento pending
                            $has_pending_update = false;
                            $pending_info = null;
                            foreach ($pending_updates as $pending) {
                                if ($pending['plugin']['id'] === $plugin['id']) {
                                    $has_pending_update = true;
                                    $pending_info = $pending;
                                    break;
                                }
                            }
                        ?>
                            <div class="fp-plugin-item <?php echo $has_pending_update ? 'has-update' : ''; ?>" data-index="<?php echo $index; ?>" <?php echo $has_pending_update ? 'style="border-left: 4px solid #d63638;"' : ''; ?>>
                                <div class="fp-plugin-header">
                                    <h3>
                                        <?php echo esc_html($plugin['name']); ?>
                                        <?php if ($has_pending_update): ?>
                                            <span class="log-badge" style="background: #d63638; margin-left: 10px;">AGGIORNAMENTO DISPONIBILE</span>
                                        <?php endif; ?>
                                    </h3>
                                    <div class="fp-plugin-actions">
                                        <button type="button" class="button fp-toggle-plugin" data-target="plugin-details-<?php echo $index; ?>">
                                            <span class="dashicons dashicons-edit"></span> Modifica
                                        </button>
                                        <button type="button" class="button fp-remove-plugin" data-index="<?php echo $index; ?>">
                                            <span class="dashicons dashicons-trash"></span> Rimuovi
                                        </button>
                                    </div>
                                </div>
                                <?php if ($has_pending_update && $pending_info): ?>
                                    <div class="fp-notice fp-notice-error" style="margin: 10px 0; padding: 10px; border-left-color: #d63638; background: #fcf0f1;">
                                        <p style="margin: 0;">
                                            <strong>🔄 Nuovo aggiornamento pronto!</strong><br>
                                            <small>
                                                Commit: <code><?php echo esc_html($pending_info['commit_sha_short']); ?></code>
                                                <?php if (!empty($pending_info['commit_message']) && $pending_info['commit_message'] !== 'Aggiornamento rilevato dal controllo schedulato'): ?>
                                                    - <?php echo esc_html($pending_info['commit_message']); ?>
                                                <?php endif; ?>
                                                <br>Ricevuto: <?php echo esc_html($pending_info['timestamp']); ?>
                                            </small>
                                        </p>
                                    </div>
                                <?php endif; ?>
                                <div class="fp-plugin-info">
                                    <span><strong>Repository:</strong> <?php echo esc_html($plugin['github_repo']); ?></span>
                                    <span><strong>Branch:</strong> <?php echo esc_html($plugin['branch']); ?></span>
                                    <span class="fp-plugin-status <?php echo $plugin['enabled'] ? 'enabled' : 'disabled'; ?>">
                                        <?php echo $plugin['enabled'] ? '● Abilitato' : '○ Disabilitato'; ?>
                                    </span>
                                </div>
                                <div class="fp-plugin-quick-actions">
                                    <button type="button" class="button button-small fp-check-updates" data-plugin-id="<?php echo esc_attr($plugin['id']); ?>">
                                        <span class="dashicons dashicons-cloud"></span> Controlla Aggiornamenti
                                    </button>
                                    <button type="button" class="button button-small <?php echo $has_pending_update ? 'button-primary' : ''; ?> fp-install-update" data-plugin-id="<?php echo esc_attr($plugin['id']); ?>" <?php echo $has_pending_update ? 'style="animation: pulse 2s infinite;"' : ''; ?>>
                                        <span class="dashicons dashicons-<?php echo $has_pending_update ? 'download' : 'update'; ?>"></span> 
                                        <?php echo $has_pending_update ? 'Installa Aggiornamento Ora' : 'Installa Aggiornamento'; ?>
                                    </button>
                                </div>
                                <div id="plugin-details-<?php echo $index; ?>" class="fp-plugin-details" style="display: none;">
                                    <input type="hidden" name="fp_git_updater_settings[plugins][<?php echo $index; ?>][id]" value="<?php echo esc_attr($plugin['id']); ?>">
                                    
                                    <table class="form-table">
                                        <tr>
                                            <th><label>Nome Plugin</label></th>
                                            <td>
                                                <input type="text" 
                                                       name="fp_git_updater_settings[plugins][<?php echo $index; ?>][name]" 
                                                       value="<?php echo esc_attr($plugin['name']); ?>" 
                                                       class="regular-text" required>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label>Repository GitHub</label></th>
                                            <td>
                                                <input type="text" 
                                                       name="fp_git_updater_settings[plugins][<?php echo $index; ?>][github_repo]" 
                                                       value="<?php echo esc_attr($plugin['github_repo']); ?>" 
                                                       class="regular-text" 
                                                       placeholder="username/repository" required>
                                                <p class="description">Es: tuousername/mio-plugin</p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label>Slug Plugin</label></th>
                                            <td>
                                                <input type="text" 
                                                       name="fp_git_updater_settings[plugins][<?php echo $index; ?>][plugin_slug]" 
                                                       value="<?php echo esc_attr($plugin['plugin_slug'] ?? ''); ?>" 
                                                       class="regular-text" 
                                                       placeholder="nome-cartella-plugin">
                                                <p class="description">Nome della cartella del plugin in wp-content/plugins/ (es: mio-plugin). Se vuoto, verrà dedotto dal nome del repository.</p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label>Branch</label></th>
                                            <td>
                                                <input type="text" 
                                                       name="fp_git_updater_settings[plugins][<?php echo $index; ?>][branch]" 
                                                       value="<?php echo esc_attr($plugin['branch']); ?>" 
                                                       class="regular-text">
                                                <p class="description">Branch da cui scaricare gli aggiornamenti</p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label>GitHub Token</label></th>
                                            <td>
                                                <input type="password" 
                                                       name="fp_git_updater_settings[plugins][<?php echo $index; ?>][github_token]" 
                                                       value="<?php echo esc_attr($plugin['github_token']); ?>" 
                                                       class="regular-text" 
                                                       placeholder="ghp_...">
                                                <p class="description">Opzionale, per repository privati</p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label>Abilitato</label></th>
                                            <td>
                                                <label>
                                                    <input type="checkbox" 
                                                           name="fp_git_updater_settings[plugins][<?php echo $index; ?>][enabled]" 
                                                           value="1" 
                                                           <?php checked($plugin['enabled'], true); ?>>
                                                    Abilita aggiornamenti per questo plugin
                                                </label>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="description">Nessun plugin configurato. Aggiungi il primo plugin qui sotto.</p>
                    <?php endif; ?>
                </div>
                
                <button type="button" id="fp-add-plugin" class="button button-secondary">
                    <span class="dashicons dashicons-plus-alt"></span> Aggiungi Plugin
                </button>
                
                <hr style="margin: 30px 0;">
                
                <h2>Impostazioni Generali</h2>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="webhook_secret">Webhook Secret</label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="webhook_secret" 
                                       name="fp_git_updater_settings[webhook_secret]" 
                                       value="<?php echo esc_attr($settings['webhook_secret'] ?? ''); ?>" 
                                       class="regular-text" readonly>
                                <p class="description">Copia questo secret nelle impostazioni del webhook su GitHub</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">URL Webhook</th>
                            <td>
                                <input type="text" 
                                       value="<?php echo esc_attr($webhook_url); ?>" 
                                       class="regular-text" readonly>
                                <button type="button" class="button" onclick="navigator.clipboard.writeText('<?php echo esc_js($webhook_url); ?>')">
                                    <span class="dashicons dashicons-clipboard"></span> Copia
                                </button>
                                <p class="description">Usa questo URL quando configuri il webhook su GitHub per tutti i repository</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Aggiornamento Automatico</th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           name="fp_git_updater_settings[auto_update]" 
                                           value="1" 
                                           <?php checked($settings['auto_update'] ?? false, true); ?>>
                                    Aggiorna automaticamente quando ricevi un push su GitHub
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="update_check_interval">Intervallo Controllo Aggiornamenti</label>
                            </th>
                            <td>
                                <select id="update_check_interval" name="fp_git_updater_settings[update_check_interval]">
                                    <option value="hourly" <?php selected($settings['update_check_interval'] ?? 'hourly', 'hourly'); ?>>Ogni ora</option>
                                    <option value="twicedaily" <?php selected($settings['update_check_interval'] ?? 'hourly', 'twicedaily'); ?>>Due volte al giorno</option>
                                    <option value="daily" <?php selected($settings['update_check_interval'] ?? 'hourly', 'daily'); ?>>Una volta al giorno</option>
                                </select>
                                <p class="description">Frequenza di controllo per nuovi aggiornamenti (oltre ai webhook)</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Notifiche Email</th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           name="fp_git_updater_settings[enable_notifications]" 
                                           value="1" 
                                           <?php checked($settings['enable_notifications'] ?? true, true); ?>>
                                    Invia notifiche email per gli aggiornamenti
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="notification_email">Email Notifiche</label>
                            </th>
                            <td>
                                <input type="email" 
                                       id="notification_email" 
                                       name="fp_git_updater_settings[notification_email]" 
                                       value="<?php echo esc_attr($settings['notification_email'] ?? get_option('admin_email')); ?>" 
                                       class="regular-text">
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <?php submit_button('Salva Impostazioni'); ?>
            </form>
            
            <div class="fp-git-updater-instructions">
                <h2>📋 Come Funziona</h2>
                
                <h3>🔒 Aggiornamento Manuale (Consigliato per Siti di Produzione)</h3>
                <p>Con l'aggiornamento manuale disabilitato (opzione sopra), il plugin funziona in questo modo:</p>
                <ol>
                    <li>Quando fai push/merge su GitHub, il webhook notifica questo sito</li>
                    <li>Il plugin registra l'aggiornamento come "disponibile" ma <strong>NON lo installa automaticamente</strong></li>
                    <li>Ricevi una notifica visibile nel menu admin e nella pagina delle impostazioni</li>
                    <li><strong>Tu decidi quando installare</strong> l'aggiornamento cliccando sul pulsante "Installa Aggiornamento"</li>
                    <li>Questo ti permette di <strong>testare prima gli aggiornamenti</strong> su un sito di staging</li>
                </ol>
                <p><strong style="color: #00a32a;">✓ Vantaggi:</strong> Protezione totale da aggiornamenti problematici, controllo completo, ideale per siti di clienti.</p>
                
                <h3 style="margin-top: 20px;">⚡ Aggiornamento Automatico</h3>
                <p>Se abiliti l'opzione "Aggiornamento Automatico" sopra:</p>
                <ul style="padding-left: 20px;">
                    <li>Gli aggiornamenti vengono installati immediatamente quando ricevi un push da GitHub</li>
                    <li>Utile per ambienti di sviluppo o plugin molto stabili</li>
                    <li><strong style="color: #d63638;">⚠ Attenzione:</strong> Un aggiornamento con bug andrà automaticamente in produzione</li>
                </ul>
                
                <h3 style="margin-top: 20px;">🔗 Configurazione Webhook su GitHub</h3>
                <p><strong>Importante:</strong> Devi configurare il webhook per ogni repository che hai aggiunto sopra.</p>
                <ol>
                    <li>Vai sul repository GitHub del plugin che vuoi aggiornare</li>
                    <li>Clicca su <strong>Settings</strong> → <strong>Webhooks</strong> → <strong>Add webhook</strong></li>
                    <li>Incolla l'URL webhook qui sopra nel campo <strong>Payload URL</strong></li>
                    <li>Seleziona <strong>Content type: application/json</strong></li>
                    <li>Incolla il Webhook Secret nel campo <strong>Secret</strong></li>
                    <li>In <strong>Which events would you like to trigger this webhook?</strong> seleziona <strong>Just the push event</strong></li>
                    <li>Clicca su <strong>Add webhook</strong></li>
                    <li>Ripeti per ogni repository che hai configurato</li>
                </ol>
            </div>
        </div>
        
        <!-- Template per nuovo plugin (nascosto, usato da JS) -->
        <script type="text/template" id="fp-plugin-template">
            <div class="fp-plugin-item new-plugin" data-index="{{INDEX}}">
                <div class="fp-plugin-header">
                    <h3>Nuovo Plugin</h3>
                    <div class="fp-plugin-actions">
                        <button type="button" class="button fp-toggle-plugin" data-target="plugin-details-{{INDEX}}">
                            <span class="dashicons dashicons-edit"></span> Modifica
                        </button>
                        <button type="button" class="button fp-remove-plugin" data-index="{{INDEX}}">
                            <span class="dashicons dashicons-trash"></span> Rimuovi
                        </button>
                    </div>
                </div>
                <div class="fp-plugin-info">
                    <span class="description">Configura i dettagli del plugin</span>
                </div>
                <div id="plugin-details-{{INDEX}}" class="fp-plugin-details" style="display: block;">
                    <input type="hidden" name="fp_git_updater_settings[plugins][{{INDEX}}][id]" value="{{ID}}">
                    
                    <table class="form-table">
                        <tr>
                            <th><label>Nome Plugin</label></th>
                            <td>
                                <input type="text" 
                                       name="fp_git_updater_settings[plugins][{{INDEX}}][name]" 
                                       value="" 
                                       class="regular-text" 
                                       placeholder="Es: Il mio plugin" required>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Repository GitHub</label></th>
                            <td>
                                <input type="text" 
                                       name="fp_git_updater_settings[plugins][{{INDEX}}][github_repo]" 
                                       value="" 
                                       class="regular-text" 
                                       placeholder="username/repository" required>
                                <p class="description">Es: tuousername/mio-plugin</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Slug Plugin</label></th>
                            <td>
                                <input type="text" 
                                       name="fp_git_updater_settings[plugins][{{INDEX}}][plugin_slug]" 
                                       value="" 
                                       class="regular-text" 
                                       placeholder="nome-cartella-plugin">
                                <p class="description">Nome della cartella del plugin in wp-content/plugins/ (es: mio-plugin). Se vuoto, verrà dedotto dal nome del repository.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Branch</label></th>
                            <td>
                                <input type="text" 
                                       name="fp_git_updater_settings[plugins][{{INDEX}}][branch]" 
                                       value="main" 
                                       class="regular-text">
                                <p class="description">Branch da cui scaricare gli aggiornamenti</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>GitHub Token</label></th>
                            <td>
                                <input type="password" 
                                       name="fp_git_updater_settings[plugins][{{INDEX}}][github_token]" 
                                       value="" 
                                       class="regular-text" 
                                       placeholder="ghp_...">
                                <p class="description">Opzionale, per repository privati</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Abilitato</label></th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           name="fp_git_updater_settings[plugins][{{INDEX}}][enabled]" 
                                           value="1" checked>
                                    Abilita aggiornamenti per questo plugin
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </script>
        <?php
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
                FP Git Updater - Log
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
            wp_die();
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'), 403);
            wp_die();
        }
        
        $plugin_id = isset($_POST['plugin_id']) ? sanitize_text_field($_POST['plugin_id']) : '';
        
        if (empty($plugin_id)) {
            wp_send_json_error(array('message' => 'ID plugin non fornito'), 400);
            wp_die();
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
        wp_die();
    }
    
    /**
     * AJAX: Installa aggiornamento per un plugin specifico
     */
    public function ajax_install_update() {
        // Verifica il nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fp_git_updater_nonce')) {
            wp_send_json_error(array('message' => 'Nonce non valido'), 400);
            wp_die();
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'), 403);
            wp_die();
        }
        
        $plugin_id = isset($_POST['plugin_id']) ? sanitize_text_field($_POST['plugin_id']) : '';
        
        if (empty($plugin_id)) {
            wp_send_json_error(array('message' => 'ID plugin non fornito'), 400);
            wp_die();
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
        wp_die();
    }
    
    /**
     * AJAX: Pulisci log
     */
    public function ajax_clear_logs() {
        // Verifica il nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fp_git_updater_nonce')) {
            wp_send_json_error(array('message' => 'Nonce non valido'), 400);
            wp_die();
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'), 403);
            wp_die();
        }
        
        try {
            Logger::clear_all_logs();
            wp_send_json_success(array('message' => 'Log puliti con successo!'));
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => 'Errore: ' . $e->getMessage()), 500);
        }
        wp_die();
    }
    
    /**
     * AJAX: Crea backup manuale
     */
    public function ajax_create_backup() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fp_git_updater_nonce')) {
            wp_send_json_error(array('message' => 'Nonce non valido'), 400);
            wp_die();
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'), 403);
            wp_die();
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
        wp_die();
    }
    
    /**
     * AJAX: Ripristina backup
     */
    public function ajax_restore_backup() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fp_git_updater_nonce')) {
            wp_send_json_error(array('message' => 'Nonce non valido'), 400);
            wp_die();
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'), 403);
            wp_die();
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
        wp_die();
    }
    
    /**
     * AJAX: Elimina backup
     */
    public function ajax_delete_backup() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fp_git_updater_nonce')) {
            wp_send_json_error(array('message' => 'Nonce non valido'), 400);
            wp_die();
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'), 403);
            wp_die();
        }
        
        $backup_index = isset($_POST['backup_index']) ? intval($_POST['backup_index']) : null;
        
        if ($backup_index === null) {
            wp_send_json_error(array('message' => 'Indice backup non fornito'), 400);
            wp_die();
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
        wp_die();
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
                FP Git Updater - Backup e Ripristino
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
                        <td><strong><?php echo count($current_settings['plugins'] ?? array()); ?></strong> plugin</td>
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
            wp_die();
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'), 403);
            wp_die();
        }
        
        try {
            $updater = Updater::get_instance();
            $result = $updater->check_plugin_update_by_id('fp_git_updater_self');
            
            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()), 500);
            } elseif ($result) {
                wp_send_json_success(array('message' => 'Aggiornamento disponibile per FP Git Updater!'));
            } else {
                wp_send_json_success(array('message' => 'FP Git Updater è già aggiornato.'));
            }
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => 'Errore: ' . $e->getMessage()), 500);
        }
        wp_die();
    }
    
    /**
     * AJAX: Installa aggiornamento per il plugin stesso
     */
    public function ajax_install_self_update() {
        // Verifica il nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fp_git_updater_nonce')) {
            wp_send_json_error(array('message' => 'Nonce non valido'), 400);
            wp_die();
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'), 403);
            wp_die();
        }
        
        try {
            $updater = Updater::get_instance();
            $result = $updater->run_update_by_id('fp_git_updater_self');
            
            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()), 500);
            } elseif ($result) {
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
        wp_die();
    }
    
    /**
     * AJAX: Carica lista repository da GitHub per l'username predefinito
     */
    public function ajax_load_github_repos() {
        // Verifica il nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'fp_git_updater_nonce')) {
            wp_send_json_error(array('message' => 'Nonce non valido'), 400);
            wp_die();
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'), 403);
            wp_die();
        }
        
        // Ottieni username predefinito
        $settings = get_option('fp_git_updater_settings', array());
        $default_username = isset($settings['default_github_username']) ? $settings['default_github_username'] : '';
        
        if (empty($default_username)) {
            wp_send_json_error(array('message' => 'Configura prima lo username GitHub predefinito nelle impostazioni'), 400);
            wp_die();
        }
        
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
                wp_die();
            }
            
            // Chiama API GitHub
            $api_url = 'https://api.github.com/users/' . urlencode($default_username) . '/repos';
            $args = array(
                'timeout' => 15,
                'headers' => array(
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'FP-Git-Updater-Plugin'
                )
            );
            
            // Usa token se disponibile per aumentare rate limit
            if (!empty($settings['github_token'])) {
                $encryption = Encryption::get_instance();
                $token = $encryption->decrypt($settings['github_token']);
                if (!empty($token)) {
                    $args['headers']['Authorization'] = 'token ' . $token;
                }
            }
            
            $response = wp_remote_get($api_url, $args);
            
            if (is_wp_error($response)) {
                wp_send_json_error(array('message' => 'Errore connessione GitHub: ' . $response->get_error_message()), 500);
                wp_die();
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
                wp_die();
            }
            
            $repos = json_decode($body, true);
            
            if (!is_array($repos)) {
                wp_send_json_error(array('message' => 'Risposta API GitHub non valida'), 500);
                wp_die();
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
        
        wp_die();
    }
}
