<?php
/**
 * Pannello di Amministrazione
 * 
 * Gestisce l'interfaccia admin del plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class FP_Git_Updater_Admin {
    
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
        add_action('wp_ajax_fp_git_updater_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_fp_git_updater_manual_update', array($this, 'ajax_manual_update'));
        add_action('wp_ajax_fp_git_updater_clear_logs', array($this, 'ajax_clear_logs'));
    }
    
    /**
     * Aggiungi menu admin
     */
    public function add_admin_menu() {
        add_menu_page(
            'FP Git Updater',
            'Git Updater',
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
        $output = array();
        
        if (isset($input['github_repo'])) {
            $output['github_repo'] = sanitize_text_field($input['github_repo']);
        }
        
        if (isset($input['github_token'])) {
            $output['github_token'] = sanitize_text_field($input['github_token']);
        }
        
        if (isset($input['webhook_secret'])) {
            $output['webhook_secret'] = sanitize_text_field($input['webhook_secret']);
        }
        
        if (isset($input['branch'])) {
            $output['branch'] = sanitize_text_field($input['branch']);
        }
        
        $output['auto_update'] = isset($input['auto_update']) ? true : false;
        $output['enable_notifications'] = isset($input['enable_notifications']) ? true : false;
        
        if (isset($input['notification_email'])) {
            $output['notification_email'] = sanitize_email($input['notification_email']);
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
        
        if (file_exists($css_file)) {
            wp_enqueue_style('fp-git-updater-admin', FP_GIT_UPDATER_PLUGIN_URL . 'assets/admin.css', array(), FP_GIT_UPDATER_VERSION);
        } else {
            // Log se il file CSS non esiste
            FP_Git_Updater_Logger::log('error', 'File CSS non trovato: ' . $css_file);
        }
        
        if (file_exists($js_file)) {
            wp_enqueue_script('fp-git-updater-admin', FP_GIT_UPDATER_PLUGIN_URL . 'assets/admin.js', array('jquery'), FP_GIT_UPDATER_VERSION, true);
            
            wp_localize_script('fp-git-updater-admin', 'fpGitUpdater', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('fp_git_updater_nonce'),
            ));
        } else {
            // Log se il file JS non esiste
            FP_Git_Updater_Logger::log('error', 'File JS non trovato: ' . $js_file);
        }
    }
    
    /**
     * Render pagina impostazioni
     */
    public function render_settings_page() {
        $settings = get_option('fp_git_updater_settings');
        $webhook_url = FP_Git_Updater_Webhook_Handler::get_webhook_url();
        $current_commit = get_option('fp_git_updater_current_commit', 'N/A');
        $last_update = get_option('fp_git_updater_last_update', 'Mai');
        
        ?>
        <div class="wrap fp-git-updater-wrap">
            <h1>
                <span class="dashicons dashicons-update"></span>
                FP Git Updater - Impostazioni
            </h1>
            
            <div class="fp-git-updater-header">
                <div class="fp-status-box">
                    <h3>Stato Aggiornamento</h3>
                    <p><strong>Ultimo commit:</strong> <?php echo esc_html(substr($current_commit, 0, 7)); ?></p>
                    <p><strong>Ultimo aggiornamento:</strong> <?php echo esc_html($last_update); ?></p>
                    <button type="button" id="fp-manual-update" class="button button-primary">
                        <span class="dashicons dashicons-update"></span> Aggiorna Ora
                    </button>
                    <button type="button" id="fp-test-connection" class="button">
                        <span class="dashicons dashicons-yes"></span> Test Connessione
                    </button>
                </div>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields('fp_git_updater_settings_group'); ?>
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="github_repo">Repository GitHub</label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="github_repo" 
                                       name="fp_git_updater_settings[github_repo]" 
                                       value="<?php echo esc_attr($settings['github_repo'] ?? ''); ?>" 
                                       class="regular-text"
                                       placeholder="username/repository">
                                <p class="description">Es: tuousername/mio-plugin</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="branch">Branch</label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="branch" 
                                       name="fp_git_updater_settings[branch]" 
                                       value="<?php echo esc_attr($settings['branch'] ?? 'main'); ?>" 
                                       class="regular-text">
                                <p class="description">Branch da cui scaricare gli aggiornamenti (default: main)</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="github_token">GitHub Token (opzionale)</label>
                            </th>
                            <td>
                                <input type="password" 
                                       id="github_token" 
                                       name="fp_git_updater_settings[github_token]" 
                                       value="<?php echo esc_attr($settings['github_token'] ?? ''); ?>" 
                                       class="regular-text"
                                       placeholder="ghp_...">
                                <p class="description">Token per repository privati. Creane uno su GitHub → Settings → Developer settings → Personal access tokens</p>
                            </td>
                        </tr>
                        
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
                                <p class="description">Usa questo URL quando configuri il webhook su GitHub</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Aggiornamento Automatico</th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           name="fp_git_updater_settings[auto_update]" 
                                           value="1" 
                                           <?php checked($settings['auto_update'] ?? true, true); ?>>
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
                <h2>Come configurare il webhook su GitHub</h2>
                <ol>
                    <li>Vai sul tuo repository GitHub</li>
                    <li>Clicca su <strong>Settings</strong> → <strong>Webhooks</strong> → <strong>Add webhook</strong></li>
                    <li>Incolla l'URL webhook qui sopra nel campo <strong>Payload URL</strong></li>
                    <li>Seleziona <strong>Content type: application/json</strong></li>
                    <li>Incolla il Webhook Secret nel campo <strong>Secret</strong></li>
                    <li>In <strong>Which events would you like to trigger this webhook?</strong> seleziona <strong>Just the push event</strong></li>
                    <li>Clicca su <strong>Add webhook</strong></li>
                </ol>
                <p>Ora ogni volta che fai push o merge sul branch configurato, il plugin si aggiornerà automaticamente!</p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render pagina log
     */
    public function render_logs_page() {
        $logs = FP_Git_Updater_Logger::get_logs(100);
        
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
     * AJAX: Test connessione GitHub
     */
    public function ajax_test_connection() {
        // Verifica il nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fp_git_updater_nonce')) {
            wp_send_json_error(array('message' => 'Nonce non valido'), 400);
            wp_die();
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'), 403);
            wp_die();
        }
        
        try {
            $updater = FP_Git_Updater_Updater::get_instance();
            $result = $updater->check_for_updates();
            
            if ($result) {
                wp_send_json_success(array('message' => 'Connessione riuscita! Aggiornamenti disponibili.'));
            } else {
                wp_send_json_success(array('message' => 'Connessione riuscita! Nessun aggiornamento disponibile.'));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Errore: ' . $e->getMessage()), 500);
        }
        wp_die();
    }
    
    /**
     * AJAX: Aggiornamento manuale
     */
    public function ajax_manual_update() {
        // Verifica il nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fp_git_updater_nonce')) {
            wp_send_json_error(array('message' => 'Nonce non valido'), 400);
            wp_die();
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'), 403);
            wp_die();
        }
        
        try {
            $updater = FP_Git_Updater_Updater::get_instance();
            $result = $updater->run_update();
            
            if ($result) {
                wp_send_json_success(array('message' => 'Aggiornamento completato con successo!'));
            } else {
                wp_send_json_error(array('message' => 'Errore durante l\'aggiornamento. Controlla i log.'), 500);
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Errore: ' . $e->getMessage()), 500);
        }
        wp_die();
    }
    
    /**
     * AJAX: Pulisci log
     */
    public function ajax_clear_logs() {
        // Verifica il nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fp_git_updater_nonce')) {
            wp_send_json_error(array('message' => 'Nonce non valido'), 400);
            wp_die();
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permessi insufficienti'), 403);
            wp_die();
        }
        
        try {
            FP_Git_Updater_Logger::clear_all_logs();
            wp_send_json_success(array('message' => 'Log puliti con successo!'));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Errore: ' . $e->getMessage()), 500);
        }
        wp_die();
    }
}
