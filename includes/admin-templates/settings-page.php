<?php
/**
 * Template: Pagina Impostazioni
 * 
 * @var array $settings Impostazioni del plugin
 * @var string $webhook_url URL del webhook
 * @var array $plugins Lista plugin configurati
 * @var array $pending_updates Aggiornamenti in attesa
 * @var bool $auto_update_enabled Stato aggiornamento automatico
 */

if (!defined('ABSPATH')) {
    exit;
}

use FP\GitUpdater\I18nHelper;

$i18n = I18nHelper::get_instance();
?>

<div class="wrap fp-git-updater-wrap">
    <h1>
        <span class="dashicons dashicons-update"></span>
        <?php _e('FP Git Updater', 'fp-git-updater'); ?>
        <span style="font-size: 18px; font-weight: 400; color: #50575e; margin-left: 10px;">
            <?php _e('Gestione aggiornamenti da GitHub', 'fp-git-updater'); ?>
        </span>
        <?php if (!empty($pending_updates)): ?>
            <span class="update-count">
                <span class="dashicons dashicons-warning" style="font-size: 14px; margin-right: 4px;"></span>
                <?php echo count($pending_updates); ?>
            </span>
        <?php endif; ?>
    </h1>
    
    <?php 
    // Includi notifiche per aggiornamenti pending
    include FP_GIT_UPDATER_PLUGIN_DIR . 'includes/admin-templates/partials/pending-updates-notice.php';
    
    // Includi notifica per modalità aggiornamento
    include FP_GIT_UPDATER_PLUGIN_DIR . 'includes/admin-templates/partials/update-mode-notice.php';
    
    // Includi sezione auto-aggiornamento
    include FP_GIT_UPDATER_PLUGIN_DIR . 'includes/admin-templates/partials/self-update-section.php';
    ?>
    
    <!-- Tab Navigation -->
    <nav class="fp-tab-nav">
        <ul class="fp-tab-list">
            <li class="fp-tab-item active">
                <a href="#fp-tab-plugins" class="fp-tab-link" data-tab="plugins">
                    <span class="dashicons dashicons-admin-plugins"></span>
                    <?php _e('Plugin Gestiti', 'fp-git-updater'); ?>
                    <?php if (!empty($plugins)): ?>
                        <span class="fp-tab-badge"><?php echo count($plugins); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($pending_updates)): ?>
                        <span class="fp-tab-update-badge"><?php echo count($pending_updates); ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="fp-tab-item">
                <a href="#fp-tab-settings" class="fp-tab-link" data-tab="settings">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php _e('Impostazioni Generali', 'fp-git-updater'); ?>
                </a>
            </li>
            <li class="fp-tab-item">
                <a href="#fp-tab-backup" class="fp-tab-link" data-tab="backup">
                    <span class="dashicons dashicons-backup"></span>
                    <?php _e('Gestione Backup', 'fp-git-updater'); ?>
                </a>
            </li>
            <li class="fp-tab-item">
                <a href="#fp-tab-instructions" class="fp-tab-link" data-tab="instructions">
                    <span class="dashicons dashicons-book-alt"></span>
                    <?php _e('Istruzioni', 'fp-git-updater'); ?>
                </a>
            </li>
        </ul>
    </nav>
    
    <form method="post" action="options.php" id="fp-git-updater-form">
        <?php settings_fields('fp_git_updater_settings_group'); ?>
        
        <!-- Tab: Plugin Gestiti (Default attivo) -->
        <div id="fp-tab-plugins" class="fp-tab-content active">
            <div style="margin: 0 0 25px 0;">
                <p style="font-size: 14px; color: #50575e; margin: 0;">
                    <?php _e('Aggiungi e gestisci i plugin che vuoi aggiornare automaticamente da GitHub.', 'fp-git-updater'); ?>
                </p>
            </div>
            
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
                        
                        // Includi template per singolo plugin
                        include FP_GIT_UPDATER_PLUGIN_DIR . 'includes/admin-templates/partials/plugin-item.php';
                    endforeach; ?>
                <?php else: ?>
                    <div style="background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 30px; text-align: center; margin: 20px 0;">
                        <span class="dashicons dashicons-admin-plugins" style="font-size: 48px; color: #dcdcde; margin-bottom: 15px; display: block;"></span>
                        <p class="description" style="font-size: 15px; color: #50575e; margin: 0;">
                            <?php _e('Nessun plugin configurato. Aggiungi il primo plugin qui sotto.', 'fp-git-updater'); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            
            <button type="button" id="fp-add-plugin" class="button button-primary" style="margin-top: 20px;">
                <span class="dashicons dashicons-plus-alt"></span>
                <?php _e('Aggiungi Nuovo Plugin', 'fp-git-updater'); ?>
            </button>
        </div>
        
        <!-- Tab: Impostazioni Generali -->
        <div id="fp-tab-settings" class="fp-tab-content">
            <?php 
            // Includi sezione impostazioni generali
            include FP_GIT_UPDATER_PLUGIN_DIR . 'includes/admin-templates/partials/general-settings.php';
            ?>
        </div>
        
        <!-- Tab: Backup -->
        <div id="fp-tab-backup" class="fp-tab-content">
            <?php 
            // Includi sezione backup
            include FP_GIT_UPDATER_PLUGIN_DIR . 'includes/admin-templates/partials/backup-section.php';
            ?>
        </div>
        
        <!-- Tab: Istruzioni -->
        <div id="fp-tab-instructions" class="fp-tab-content">
            <?php 
            // Includi istruzioni
            include FP_GIT_UPDATER_PLUGIN_DIR . 'includes/admin-templates/partials/instructions.php';
            ?>
        </div>
        
        <!-- Bottone Salva (sempre visibile) -->
        <div class="fp-form-actions" style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #f0f0f1;">
            <?php submit_button(__('Salva Impostazioni', 'fp-git-updater'), 'primary large', 'submit', false); ?>
        </div>
    </form>
</div>

<!-- Template per nuovo plugin (nascosto, usato da JS) -->
<?php include FP_GIT_UPDATER_PLUGIN_DIR . 'includes/admin-templates/partials/plugin-template.php'; ?>
