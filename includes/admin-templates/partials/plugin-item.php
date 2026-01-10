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
        <div class="fp-notice fp-notice-error" style="margin: 0; border-left-color: #d63638; background: linear-gradient(to right, #fcf0f1 0%, #fff 10%);">
            <p style="margin: 0;">
                <strong>
                    <span class="dashicons dashicons-update" style="font-size: 18px; vertical-align: middle;"></span>
                    <?php _e('Nuovo aggiornamento pronto!', 'fp-git-updater'); ?>
                </strong>
                <div style="margin-top: 10px; padding-left: 26px; font-size: 13px; line-height: 1.8;">
                    <?php 
                    $current_version = isset($pending_info['current_version']) ? $pending_info['current_version'] : get_option('fp_git_updater_current_version_' . $plugin['id'], '');
                    $available_version = isset($pending_info['available_version']) ? $pending_info['available_version'] : '';
                    ?>
                    <?php if (!empty($current_version) && !empty($available_version)): ?>
                        <div>
                            <strong><?php _e('Versione:', 'fp-git-updater'); ?></strong> 
                            <code style="background: #fff; padding: 3px 8px; border-radius: 4px; border: 1px solid #dcdcde;"><?php echo esc_html($current_version); ?></code> 
                            <span style="margin: 0 8px; color: #d63638;">→</span>
                            <code style="background: #fff; padding: 3px 8px; border-radius: 4px; border: 1px solid #d63638; color: #d63638; font-weight: 600;"><?php echo esc_html($available_version); ?></code>
                        </div>
                    <?php elseif (!empty($available_version)): ?>
                        <div>
                            <strong><?php _e('Nuova versione:', 'fp-git-updater'); ?></strong> 
                            <code style="background: #fff; padding: 3px 8px; border-radius: 4px; border: 1px solid #d63638; color: #d63638; font-weight: 600;"><?php echo esc_html($available_version); ?></code>
                        </div>
                    <?php endif; ?>
                    <div style="margin-top: 8px;">
                        <strong><?php _e('Commit:', 'fp-git-updater'); ?></strong> 
                        <code style="background: #f6f7f7; padding: 3px 8px; border-radius: 4px; font-family: 'Monaco', 'Menlo', monospace;"><?php echo esc_html($pending_info['commit_sha_short']); ?></code>
                        <?php if (!empty($pending_info['commit_message']) && $pending_info['commit_message'] !== 'Aggiornamento rilevato dal controllo schedulato'): ?>
                            <span style="color: #50575e; margin-left: 8px;">- <?php echo esc_html($pending_info['commit_message']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div style="margin-top: 8px; color: #50575e; font-size: 12px;">
                        <span class="dashicons dashicons-clock" style="font-size: 14px; vertical-align: middle;"></span>
                        <?php printf(__('Ricevuto: %s', 'fp-git-updater'), esc_html($pending_info['timestamp'])); ?>
                    </div>
                </div>
            </p>
        </div>
    <?php endif; ?>
    
    <div class="fp-plugin-info">
        <?php 
        $updater = \FP\GitUpdater\Updater::get_instance();
        
        // Ottieni versione installata corrente
        // Prima prova a leggerla dall'opzione salvata
        $current_version = get_option('fp_git_updater_current_version_' . $plugin['id'], '');
        
        // Se non c'è, prova a leggerla direttamente dal file del plugin
        if (empty($current_version)) {
            $current_version = $updater->get_installed_plugin_version($plugin);
            
            // Salva la versione trovata per la prossima volta
            if (!empty($current_version)) {
                update_option('fp_git_updater_current_version_' . $plugin['id'], $current_version);
            }
        }
        
        // Ottieni versione disponibile su GitHub (anche se non c'è aggiornamento pending)
        $github_version = '';
        if ($has_pending_update && !empty($pending_info['available_version'])) {
            // Usa la versione già recuperata nell'aggiornamento pending
            $github_version = $pending_info['available_version'];
        } else {
            // Controlla se abbiamo una versione GitHub salvata in cache (validità 5 minuti)
            $cached_github_version = get_transient('fp_git_updater_github_version_' . $plugin['id']);
            if ($cached_github_version !== false) {
                $github_version = $cached_github_version;
            } elseif (!empty($plugin['github_repo'])) {
                // Recupera la versione GitHub solo se non in cache
                $github_version = $updater->get_github_plugin_version($plugin);
                // Salva in cache per 5 minuti (300 secondi)
                if (!empty($github_version)) {
                    set_transient('fp_git_updater_github_version_' . $plugin['id'], $github_version, 300);
                }
            }
        }
        
        // Determina se le versioni sono diverse
        $versions_differ = !empty($current_version) && !empty($github_version) && $current_version !== $github_version;
        ?>
        
        <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-bottom: 8px; font-size: 13px;">
            <span><strong><?php _e('Repository:', 'fp-git-updater'); ?></strong> <code><?php echo esc_html($plugin['github_repo']); ?></code></span>
            <span><strong><?php _e('Branch:', 'fp-git-updater'); ?></strong> <code><?php echo esc_html($plugin['branch']); ?></code></span>
            <span class="fp-plugin-status <?php echo $plugin['enabled'] ? 'enabled' : 'disabled'; ?>">
                <?php 
                echo $plugin['enabled'] 
                    ? '● ' . __('Abilitato', 'fp-git-updater')
                    : '○ ' . __('Disabilitato', 'fp-git-updater');
                ?>
            </span>
        </div>
        
        <!-- Sezione Versioni - Compatta -->
        <div style="background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 3px; padding: 8px 10px; margin: 8px 0; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; font-size: 13px;">
            <span style="font-weight: 600; color: #1d2327; white-space: nowrap;">
                <?php _e('Versioni:', 'fp-git-updater'); ?>
            </span>
            
            <!-- Versione Installata -->
            <span style="display: inline-flex; align-items: center; gap: 4px;">
                <span style="color: #50575e; font-size: 12px;"><?php _e('Installata', 'fp-git-updater'); ?>:</span>
                <code style="background: #fff; padding: 3px 6px; border-radius: 2px; border-left: 3px solid #2271b1; font-size: 13px; font-weight: 600; color: #2271b1;">
                    <?php echo !empty($current_version) ? esc_html($current_version) : '—'; ?>
                </code>
            </span>
            
            <!-- Versione GitHub -->
            <span style="display: inline-flex; align-items: center; gap: 4px;">
                <span style="color: #50575e; font-size: 12px;"><?php _e('GitHub', 'fp-git-updater'); ?>:</span>
                <code style="background: #fff; padding: 3px 6px; border-radius: 2px; border-left: 3px solid <?php echo $versions_differ ? '#d63638' : '#00a32a'; ?>; font-size: 13px; font-weight: 600; color: <?php echo $versions_differ ? '#d63638' : '#00a32a'; ?>;">
                    <?php echo !empty($github_version) ? esc_html($github_version) : '—'; ?>
                </code>
            </span>
            
            <!-- Indicatore differenza versioni inline -->
            <?php if ($versions_differ): ?>
                <span style="margin-left: auto; color: #d63638; font-weight: 600; font-size: 12px; display: inline-flex; align-items: center; gap: 4px;">
                    <span class="dashicons dashicons-update" style="font-size: 14px;"></span>
                    <?php printf(
                        __('%s → %s', 'fp-git-updater'),
                        esc_html($current_version),
                        '<strong>' . esc_html($github_version) . '</strong>'
                    ); ?>
                </span>
            <?php elseif (!empty($current_version) && !empty($github_version) && $current_version === $github_version): ?>
                <span style="margin-left: auto; color: #00a32a; font-weight: 600; font-size: 12px; display: inline-flex; align-items: center; gap: 4px;">
                    <span class="dashicons dashicons-yes-alt" style="font-size: 14px;"></span>
                    <?php _e('Aggiornato', 'fp-git-updater'); ?>
                </span>
            <?php endif; ?>
        </div>
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
                    // Username hardcodato a FranPass87
                    $default_username = 'FranPass87';
                    $repo_placeholder = sprintf(__('FP-Forms oppure %s/FP-Forms', 'fp-git-updater'), $default_username);
                    $repo_description = sprintf(__('Inserisci solo il nome (es: FP-Forms) o il formato completo. Username predefinito: <strong>%s</strong>', 'fp-git-updater'), $default_username);
                    ?>
                    <div style="display: flex; gap: 10px; align-items: flex-start;">
                        <input type="text" 
                               name="fp_git_updater_settings[plugins][<?php echo $index; ?>][github_repo]" 
                               value="<?php echo esc_attr($plugin['github_repo']); ?>" 
                               class="regular-text fp-repo-input" 
                               data-index="<?php echo $index; ?>"
                               placeholder="<?php echo esc_attr($repo_placeholder); ?>" required>
                        <button type="button" 
                                class="button fp-load-repos-btn" 
                                data-index="<?php echo $index; ?>"
                                title="<?php esc_attr_e('Carica repository da GitHub', 'fp-git-updater'); ?>">
                            <span class="dashicons dashicons-download" style="margin-top: 3px;"></span>
                            <?php _e('Carica dalla lista', 'fp-git-updater'); ?>
                        </button>
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
