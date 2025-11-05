<?php
/**
 * Template Partial: Item Plugin Singolo
 * 
 * @var int $index Indice del plugin
 * @var array $plugin Dati del plugin
 * @var bool $has_pending_update Se ha aggiornamenti pending
 * @var array|null $pending_info Info aggiornamento pending
 */

if (!defined('ABSPATH')) {
    return;
}
?>

<div class="fp-plugin-item <?php echo $has_pending_update ? 'has-update' : ''; ?>" 
     data-index="<?php echo $index; ?>" 
     <?php echo $has_pending_update ? 'style="border-left: 4px solid #d63638;"' : ''; ?>>
    
    <div class="fp-plugin-header">
        <h3>
            <?php echo esc_html($plugin['name']); ?>
            <?php if ($has_pending_update): ?>
                <span class="log-badge" style="background: #d63638; margin-left: 10px;">
                    <?php _e('AGGIORNAMENTO DISPONIBILE', 'fp-git-updater'); ?>
                </span>
            <?php endif; ?>
        </h3>
        <div class="fp-plugin-actions">
            <button type="button" class="button fp-toggle-plugin" data-target="plugin-details-<?php echo $index; ?>">
                <span class="dashicons dashicons-edit"></span> <?php _e('Modifica', 'fp-git-updater'); ?>
            </button>
            <button type="button" class="button fp-remove-plugin" data-index="<?php echo $index; ?>">
                <span class="dashicons dashicons-trash"></span> <?php _e('Rimuovi', 'fp-git-updater'); ?>
            </button>
        </div>
    </div>
    
    <?php if ($has_pending_update && $pending_info): ?>
        <div class="fp-notice fp-notice-error" style="margin: 10px 0; padding: 10px; border-left-color: #d63638; background: #fcf0f1;">
            <p style="margin: 0;">
                <strong>🔄 <?php _e('Nuovo aggiornamento pronto!', 'fp-git-updater'); ?></strong><br>
                <small>
                    <?php _e('Commit:', 'fp-git-updater'); ?> <code><?php echo esc_html($pending_info['commit_sha_short']); ?></code>
                    <?php if (!empty($pending_info['commit_message']) && $pending_info['commit_message'] !== 'Aggiornamento rilevato dal controllo schedulato'): ?>
                        - <?php echo esc_html($pending_info['commit_message']); ?>
                    <?php endif; ?>
                    <br><?php printf(__('Ricevuto: %s', 'fp-git-updater'), esc_html($pending_info['timestamp'])); ?>
                </small>
            </p>
        </div>
    <?php endif; ?>
    
    <div class="fp-plugin-info">
        <span><strong><?php _e('Repository:', 'fp-git-updater'); ?></strong> <?php echo esc_html($plugin['github_repo']); ?></span>
        <span><strong><?php _e('Branch:', 'fp-git-updater'); ?></strong> <?php echo esc_html($plugin['branch']); ?></span>
        <span class="fp-plugin-status <?php echo $plugin['enabled'] ? 'enabled' : 'disabled'; ?>">
            <?php 
            echo $plugin['enabled'] 
                ? '● ' . __('Abilitato', 'fp-git-updater')
                : '○ ' . __('Disabilitato', 'fp-git-updater');
            ?>
        </span>
    </div>
    
    <div class="fp-plugin-quick-actions">
        <button type="button" class="button button-small fp-check-updates" data-plugin-id="<?php echo esc_attr($plugin['id']); ?>">
            <span class="dashicons dashicons-cloud"></span> <?php _e('Controlla Aggiornamenti', 'fp-git-updater'); ?>
        </button>
        <button type="button" 
                class="button button-small <?php echo $has_pending_update ? 'button-primary' : ''; ?> fp-install-update" 
                data-plugin-id="<?php echo esc_attr($plugin['id']); ?>" 
                <?php echo $has_pending_update ? 'style="animation: pulse 2s infinite;"' : ''; ?>>
            <span class="dashicons dashicons-<?php echo $has_pending_update ? 'download' : 'update'; ?>"></span> 
            <?php echo $has_pending_update 
                ? __('Installa Aggiornamento Ora', 'fp-git-updater')
                : __('Installa Aggiornamento', 'fp-git-updater'); 
            ?>
        </button>
    </div>
    
    <div id="plugin-details-<?php echo $index; ?>" class="fp-plugin-details" style="display: none;">
        <input type="hidden" name="fp_git_updater_settings[plugins][<?php echo $index; ?>][id]" value="<?php echo esc_attr($plugin['id']); ?>">
        
        <table class="form-table">
            <tr>
                <th><label><?php _e('Nome Plugin', 'fp-git-updater'); ?></label></th>
                <td>
                    <input type="text" 
                           name="fp_git_updater_settings[plugins][<?php echo $index; ?>][name]" 
                           value="<?php echo esc_attr($plugin['name']); ?>" 
                           class="regular-text" required>
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Repository GitHub', 'fp-git-updater'); ?></label></th>
                <td>
                    <?php 
                    $current_settings = get_option('fp_git_updater_settings', array());
                    $has_default_username = !empty($current_settings['default_github_username']);
                    $repo_placeholder = $has_default_username 
                        ? sprintf(__('FP-Forms oppure %s/FP-Forms', 'fp-git-updater'), $current_settings['default_github_username'])
                        : 'username/repository';
                    $repo_description = $has_default_username
                        ? sprintf(__('Inserisci solo il nome (es: FP-Forms) o il formato completo. Username predefinito: <strong>%s</strong>', 'fp-git-updater'), $current_settings['default_github_username'])
                        : __('Es: tuousername/mio-plugin', 'fp-git-updater');
                    ?>
                    <div style="display: flex; gap: 10px; align-items: flex-start;">
                        <input type="text" 
                               name="fp_git_updater_settings[plugins][<?php echo $index; ?>][github_repo]" 
                               value="<?php echo esc_attr($plugin['github_repo']); ?>" 
                               class="regular-text fp-repo-input" 
                               data-index="<?php echo $index; ?>"
                               placeholder="<?php echo esc_attr($repo_placeholder); ?>" required>
                        <?php if ($has_default_username): ?>
                            <button type="button" 
                                    class="button fp-load-repos-btn" 
                                    data-index="<?php echo $index; ?>"
                                    title="<?php esc_attr_e('Carica repository da GitHub', 'fp-git-updater'); ?>">
                                <span class="dashicons dashicons-download" style="margin-top: 3px;"></span>
                                <?php _e('Carica dalla lista', 'fp-git-updater'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                    <p class="description"><?php echo $repo_description; ?></p>
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Slug Plugin', 'fp-git-updater'); ?></label></th>
                <td>
                    <input type="text" 
                           name="fp_git_updater_settings[plugins][<?php echo $index; ?>][plugin_slug]" 
                           value="<?php echo esc_attr($plugin['plugin_slug'] ?? ''); ?>" 
                           class="regular-text" 
                           placeholder="nome-cartella-plugin">
                    <p class="description">
                        <?php _e('Nome della cartella del plugin in wp-content/plugins/ (es: mio-plugin). Se vuoto, verrà dedotto dal nome del repository.', 'fp-git-updater'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Branch', 'fp-git-updater'); ?></label></th>
                <td>
                    <input type="text" 
                           name="fp_git_updater_settings[plugins][<?php echo $index; ?>][branch]" 
                           value="<?php echo esc_attr($plugin['branch']); ?>" 
                           class="regular-text">
                    <p class="description"><?php _e('Branch da cui scaricare gli aggiornamenti', 'fp-git-updater'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label><?php _e('URL ZIP pubblico (opzionale)', 'fp-git-updater'); ?></label></th>
                <td>
                    <input type="text" 
                           name="fp_git_updater_settings[plugins][<?php echo $index; ?>][zip_url]" 
                           value="<?php echo esc_attr($plugin['zip_url'] ?? ''); ?>" 
                           class="regular-text" placeholder="https://.../package.zip">
                    <p class="description"><?php _e('Se impostato, l\'aggiornamento userà direttamente questo ZIP senza token.', 'fp-git-updater'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label><?php _e('GitHub Token', 'fp-git-updater'); ?></label></th>
                <td>
                    <input type="password" 
                           name="fp_git_updater_settings[plugins][<?php echo $index; ?>][github_token]" 
                           value="<?php echo esc_attr($plugin['github_token']); ?>" 
                           class="regular-text" 
                           placeholder="ghp_...">
                    <p class="description">
                        <?php _e('Opzionale, per repository privati', 'fp-git-updater'); ?>
                        <?php echo \FP\GitUpdater\I18nHelper::help_link('github_token'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Abilitato', 'fp-git-updater'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="fp_git_updater_settings[plugins][<?php echo $index; ?>][enabled]" 
                               value="1" 
                               <?php checked($plugin['enabled'], true); ?>>
                        <?php _e('Abilita aggiornamenti per questo plugin', 'fp-git-updater'); ?>
                    </label>
                </td>
            </tr>
        </table>
    </div>
</div>
