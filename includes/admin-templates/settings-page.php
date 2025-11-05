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
        <?php _e('FP Git Updater - Impostazioni', 'fp-git-updater'); ?>
        <?php if (!empty($pending_updates)): ?>
            <span class="update-count"><?php echo count($pending_updates); ?></span>
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
    
    <form method="post" action="options.php">
        <?php settings_fields('fp_git_updater_settings_group'); ?>
        
        <h2><?php _e('Plugin Gestiti', 'fp-git-updater'); ?></h2>
        <p><?php _e('Aggiungi e gestisci i plugin che vuoi aggiornare automaticamente da GitHub.', 'fp-git-updater'); ?></p>
        
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
                <p class="description">
                    <?php _e('Nessun plugin configurato. Aggiungi il primo plugin qui sotto.', 'fp-git-updater'); ?>
                </p>
            <?php endif; ?>
        </div>
        
        <button type="button" id="fp-add-plugin" class="button button-secondary">
            <span class="dashicons dashicons-plus-alt"></span>
            <?php _e('Aggiungi Plugin', 'fp-git-updater'); ?>
        </button>
        
        <hr style="margin: 30px 0;">
        
        <?php 
        // Includi sezione impostazioni generali
        include FP_GIT_UPDATER_PLUGIN_DIR . 'includes/admin-templates/partials/general-settings.php';
        ?>
        
        <?php submit_button(__('Salva Impostazioni', 'fp-git-updater')); ?>
    </form>
    
    <?php 
    // Includi istruzioni
    include FP_GIT_UPDATER_PLUGIN_DIR . 'includes/admin-templates/partials/instructions.php';
    ?>
</div>

<!-- Template per nuovo plugin (nascosto, usato da JS) -->
<?php include FP_GIT_UPDATER_PLUGIN_DIR . 'includes/admin-templates/partials/plugin-template.php'; ?>
