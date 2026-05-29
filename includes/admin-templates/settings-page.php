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

?>

<div class="wrap fp-git-updater-wrap fpgitupdater-admin-page">
    <h1 class="screen-reader-text"><?php esc_html_e('FP Updater', 'fp-git-updater'); ?></h1>

    <div class="fpgitupdater-page-header">
        <div class="fpgitupdater-page-header-content">
            <h2 class="fpgitupdater-page-header-title" aria-hidden="true">
                <span class="dashicons dashicons-update" aria-hidden="true"></span>
                <?php esc_html_e('FP Updater', 'fp-git-updater'); ?>
            </h2>
            <p class="fpgitupdater-page-header-desc">
                <?php esc_html_e('Gestione aggiornamenti da GitHub, backup, Master e distribuzione ai siti client.', 'fp-git-updater'); ?>
            </p>
        </div>
        <span class="fpgitupdater-page-header-badge"><?php echo esc_html('v' . FP_GIT_UPDATER_VERSION); ?></span>
    </div>
    
    <?php 
    // Includi notifica per modalità aggiornamento
    include FP_GIT_UPDATER_PLUGIN_DIR . 'includes/admin-templates/partials/update-mode-notice.php';
    
    // Includi sezione auto-aggiornamento
    include FP_GIT_UPDATER_PLUGIN_DIR . 'includes/admin-templates/partials/self-update-section.php';
    ?>
    
    <h2 class="fp-step-heading">
        <span class="fp-step-heading-num" aria-hidden="true">1</span>
        <?php esc_html_e('Configura il Master', 'fp-git-updater'); ?>
    </h2>
    <?php
    // Form Master separato (prima del form principale per evitare form annidati)
    include FP_GIT_UPDATER_PLUGIN_DIR . 'includes/admin-templates/partials/master-config-card.php';
    ?>
    
    <!-- Tab Navigation -->
    <nav class="fp-tab-nav">
        <ul class="fp-tab-list">
            <li class="fp-tab-item active">
                <a href="javascript:void(0)" class="fp-tab-link" data-tab="plugins">
                    <span class="dashicons dashicons-admin-plugins"></span>
                    <?php _e('Plugin e Distribuzione', 'fp-git-updater'); ?>
                    <?php if (!empty($plugins)): ?>
                        <span class="fp-tab-badge"><?php echo count($plugins); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($pending_updates)): ?>
                        <span class="fp-tab-update-badge"><?php echo count($pending_updates); ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="fp-tab-item">
                <a href="javascript:void(0)" class="fp-tab-link" data-tab="settings">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php _e('Impostazioni Generali', 'fp-git-updater'); ?>
                </a>
            </li>
            <li class="fp-tab-item">
                <a href="javascript:void(0)" class="fp-tab-link" data-tab="backup">
                    <span class="dashicons dashicons-backup"></span>
                    <?php _e('Gestione Backup', 'fp-git-updater'); ?>
                </a>
            </li>
            <li class="fp-tab-item">
                <a href="javascript:void(0)" class="fp-tab-link" data-tab="instructions">
                    <span class="dashicons dashicons-book-alt"></span>
                    <?php _e('Istruzioni', 'fp-git-updater'); ?>
                </a>
            </li>
        </ul>
    </nav>
    
    <form method="post" action="options.php" id="fp-git-updater-form">
        <?php settings_fields('fp_git_updater_settings_group'); ?>
        
        <!-- Tab: Plugin e Distribuzione (unificato con Master) -->
        <div id="fp-tab-plugins" class="fp-tab-content active">
            <h2 class="fp-step-heading">
                <span class="fp-step-heading-num" aria-hidden="true">2</span>
                <?php esc_html_e('Gestisci plugin e distribuisci', 'fp-git-updater'); ?>
            </h2>
            <div class="fp-section-header fp-plugins-section-header">
                <h3 class="fp-section-title"><?php _e('Plugin gestiti e distribuzione', 'fp-git-updater'); ?></h3>
                <p class="fp-section-description">
                    <?php _e('Aggiungi i plugin da GitHub. Per ogni plugin puoi controllare aggiornamenti, installare su questo sito e distribuire ai clienti (seleziona i siti e clicca «Distribuisci»).', 'fp-git-updater'); ?>
                </p>
            </div>

            <?php if (!empty($plugins)): ?>
            <div class="fp-plugins-list-toolbar" role="toolbar" aria-label="<?php esc_attr_e('Controlli lista plugin', 'fp-git-updater'); ?>">
                <button type="button" class="button button-small fp-plugins-expand-all">
                    <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                    <?php esc_html_e('Espandi tutti', 'fp-git-updater'); ?>
                </button>
                <button type="button" class="button button-small fp-plugins-collapse-all">
                    <span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>
                    <?php esc_html_e('Comprimi tutti', 'fp-git-updater'); ?>
                </button>
                <span class="fp-plugins-list-toolbar-hint">
                    <?php esc_html_e('Clicca sull\'header di una card per espanderla/comprimerla.', 'fp-git-updater'); ?>
                </span>
            </div>
            <?php endif; ?>

            <div id="fp-plugins-list">
                <?php if (!empty($plugins)): ?>
                    <?php foreach ($plugins as $index => $plugin): 
                        // Salta il plugin self-update (gestito nella sezione dedicata in alto)
                        if (isset($plugin['id']) && $plugin['id'] === 'fp_git_updater_self') {
                            continue;
                        }
                        if (isset($plugin['plugin_slug']) && $plugin['plugin_slug'] === 'fp-git-updater') {
                            continue;
                        }
                        
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
                    <div class="fp-empty-state fp-empty-state--plugins">
                        <span class="dashicons dashicons-admin-plugins"></span>
                        <p class="fp-empty-state-title"><?php _e('Nessun plugin configurato', 'fp-git-updater'); ?></p>
                        <p class="fp-empty-state-desc"><?php _e('Aggiungi il primo plugin con il pulsante qui sotto. Inserisci repository GitHub (es. owner/repo) e branch.', 'fp-git-updater'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <button type="button" id="fp-add-plugin" class="button button-primary fp-add-plugin-btn fpgitupdater-btn fpgitupdater-btn-primary">
                <span class="dashicons dashicons-plus-alt"></span>
                <?php _e('Aggiungi Nuovo Plugin', 'fp-git-updater'); ?>
            </button>
            
            <?php include FP_GIT_UPDATER_PLUGIN_DIR . 'includes/admin-templates/partials/master-deploy-clients.php'; ?>
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
        
        <!-- Bottone Salva Impostazioni -->
        <div class="fp-form-actions fpgitupdater-form-actions" id="fp-main-form-actions">
            <?php submit_button(__('Salva impostazioni', 'fp-git-updater'), 'primary large fpgitupdater-btn fpgitupdater-btn-primary', 'submit', false, array('id' => 'submit')); ?>
        </div>
    </form>
</div>

<!-- Template per nuovo plugin (nascosto, usato da JS) -->
<?php include FP_GIT_UPDATER_PLUGIN_DIR . 'includes/admin-templates/partials/plugin-template.php'; ?>
