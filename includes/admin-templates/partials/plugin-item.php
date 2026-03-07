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
     data-plugin-name="<?php echo esc_attr($plugin['name'] ?? $plugin['id'] ?? ''); ?>">
    
    <div class="fp-plugin-header">
        <h3>
            <?php echo esc_html($plugin['name']); ?>
            <?php if ($has_pending_update): ?>
                <span class="log-badge log-badge-error">
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
        <div class="fp-notice fp-notice-error fp-plugin-update-notice">
            <p>
                <span class="dashicons dashicons-update"></span>
                <strong><?php _e('Aggiornamento disponibile', 'fp-git-updater'); ?></strong>
                <span class="fp-notice-separator">•</span>
                <span><?php _e('Commit:', 'fp-git-updater'); ?></span>
                <code><?php echo esc_html($pending_info['commit_sha_short']); ?></code>
            </p>
        </div>
    <?php endif; ?>
    
    <div class="fp-plugin-info">
        <?php 
        $current_version = '';
        $github_version = '';
        $versions_differ = false;
        
        $github_commit_short = '';
        $github_commit_msg   = '';
        $github_commit_date_fmt = '';

        // Versioni sui clienti (per plugin non installati localmente)
        $client_versions = [];
        $plugin_slug_for_clients = !empty($plugin['plugin_slug']) ? $plugin['plugin_slug'] : '';
        if (empty($plugin_slug_for_clients) && !empty($plugin['github_repo'])) {
            $repo_parts = explode('/', $plugin['github_repo']);
            $plugin_slug_for_clients = strtolower(end($repo_parts));
        }
        if (!empty($plugin_slug_for_clients)) {
            $client_versions = \FP\GitUpdater\MasterEndpoint::get_clients_plugin_versions($plugin_slug_for_clients);
        }

        try {
            $updater = \FP\GitUpdater\Updater::get_instance();
            
            // Ottieni versione installata corrente
            $current_version = get_option('fp_git_updater_current_version_' . $plugin['id'], '');
            
            if (empty($current_version)) {
                $current_version = $updater->get_installed_plugin_version($plugin);
                if (!empty($current_version)) {
                    update_option('fp_git_updater_current_version_' . $plugin['id'], $current_version);
                }
            }
            
            $commit_date_raw = '';

            if ($has_pending_update && !empty($pending_info['available_version'])) {
                $github_version      = $pending_info['available_version'];
                $github_commit_short = $pending_info['commit_sha_short'] ?? '';
                $github_commit_msg   = $pending_info['commit_message'] ?? '';
                $commit_date_raw     = $pending_info['commit_date'] ?? '';
            } else {
                $cached_github_version = get_transient('fp_git_updater_github_version_' . $plugin['id']);
                $cached_commit_info    = get_transient('fp_git_updater_commit_info_' . $plugin['id']);

                if ($cached_github_version !== false) {
                    $github_version = $cached_github_version;
                    // Usa il commit dalla cache se disponibile
                    if (is_array($cached_commit_info)) {
                        $github_commit_short = $cached_commit_info['short'] ?? '';
                        $github_commit_msg   = $cached_commit_info['message'] ?? '';
                        $commit_date_raw     = $cached_commit_info['date'] ?? '';
                    }
                } elseif (!empty($plugin['github_repo'])) {
                    $commit_info = $updater->get_latest_commit_info($plugin);
                    if (!is_wp_error($commit_info)) {
                        $github_commit_short = $commit_info['short'];
                        $github_commit_msg   = $commit_info['message'];
                        $commit_date_raw     = $commit_info['date'];
                        $github_version = $updater->get_github_plugin_version($plugin, $commit_info['sha']);
                        // Salva cache commit separata (5 minuti)
                        set_transient('fp_git_updater_commit_info_' . $plugin['id'], [
                            'short'   => $commit_info['short'],
                            'message' => $commit_info['message'],
                            'date'    => $commit_info['date'],
                        ], 300);
                    } else {
                        $github_version = $updater->get_github_plugin_version($plugin);
                    }
                    if (!empty($github_version)) {
                        set_transient('fp_git_updater_github_version_' . $plugin['id'], $github_version, 300);
                    }
                }
            }

            if (!empty($commit_date_raw)) {
                $ts = strtotime($commit_date_raw);
                if ($ts) {
                    $github_commit_date_fmt = date_i18n('j M Y H:i', $ts);
                }
            }
            
            $versions_differ = !empty($current_version) && !empty($github_version) && $current_version !== $github_version;
        } catch (\Throwable $e) {
            if (class_exists('\FP\GitUpdater\Logger')) {
                \FP\GitUpdater\Logger::log('error', 'Errore caricamento info plugin ' . ($plugin['name'] ?? $plugin['id']) . ': ' . $e->getMessage());
            }
        }
        ?>
        
        <div class="fp-plugin-meta-row">
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
        <div class="fp-plugin-versions-compact">
            <span class="fp-version-label"><?php _e('Versioni:', 'fp-git-updater'); ?></span>
            
            <span class="fp-version-installed">
                <strong><?php _e('Installata:', 'fp-git-updater'); ?></strong>
                <?php if (!empty($current_version)): ?>
                    <code><?php echo esc_html($current_version); ?></code>
                <?php else: ?>
                    <code>—</code>
                <?php endif; ?>
            </span>

            <?php if (!empty($client_versions)): ?>
            <span class="fp-version-clients-badge">
                <span class="dashicons dashicons-networking"></span>
                <?php
                $total_clients = count($client_versions);
                printf(
                    _n('Solo su %d cliente', 'Solo su %d clienti', $total_clients, 'fp-git-updater'),
                    $total_clients
                );
                ?>
            </span>
            <?php endif; ?>
            
            <span class="fp-version-github">
                <strong><?php _e('GitHub:', 'fp-git-updater'); ?></strong>
                <code class="<?php echo $versions_differ ? 'fp-version-diff' : 'fp-version-same'; ?>" data-plugin-id="<?php echo esc_attr($plugin['id']); ?>">
                    <?php echo !empty($github_version) ? esc_html($github_version) : '—'; ?>
                </code>
                <button type="button" 
                        class="button button-small fp-refresh-github-version" 
                        data-plugin-id="<?php echo esc_attr($plugin['id']); ?>"
                        title="<?php esc_attr_e('Controlla aggiornamenti da GitHub', 'fp-git-updater'); ?>">
                    <span class="dashicons dashicons-update"></span>
                </button>
            </span>

            <?php if (!empty($github_commit_short)): ?>
            <span class="fp-version-item fp-commit-info" data-plugin-id="<?php echo esc_attr($plugin['id']); ?>">
                <strong><?php _e('Commit:', 'fp-git-updater'); ?></strong>
                <code class="fp-commit-sha"><?php echo esc_html($github_commit_short); ?></code>
                <?php if (!empty($github_commit_msg)): ?>
                    <span class="fp-commit-message"><?php echo esc_html($github_commit_msg); ?></span>
                <?php endif; ?>
                <?php if (!empty($github_commit_date_fmt)): ?>
                    <span class="fp-commit-date"><?php echo esc_html($github_commit_date_fmt); ?></span>
                <?php endif; ?>
            </span>
            <?php endif; ?>
            
            <?php if ($versions_differ): ?>
                <span class="fp-version-status fp-version-status-update">
                    <span class="dashicons dashicons-update"></span>
                    <?php printf(__('%s → %s', 'fp-git-updater'), esc_html($current_version), '<strong>' . esc_html($github_version) . '</strong>'); ?>
                </span>
            <?php elseif (!empty($current_version) && !empty($github_version) && $current_version === $github_version): ?>
                <span class="fp-version-status fp-version-status-ok">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php _e('Aggiornato', 'fp-git-updater'); ?>
                </span>
            <?php elseif (empty($current_version) && !empty($client_versions)): ?>
                <span class="fp-version-status fp-version-status-clients">
                    <span class="dashicons dashicons-networking"></span>
                    <?php printf(_n('Solo su %d cliente', 'Solo su %d clienti', count($client_versions), 'fp-git-updater'), count($client_versions)); ?>
                </span>
            <?php elseif (empty($current_version)): ?>
                <span class="fp-version-status fp-version-status-not-installed">
                    <span class="dashicons dashicons-minus"></span>
                    <?php _e('Non installato', 'fp-git-updater'); ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="fp-plugin-quick-actions">
        <button type="button" 
                class="button button-small <?php echo $has_pending_update ? 'button-primary' : ''; ?> fp-install-update" 
                data-plugin-id="<?php echo esc_attr($plugin['id']); ?>">
            <span class="dashicons dashicons-<?php echo $has_pending_update ? 'download' : 'update'; ?>"></span> 
            <?php echo $has_pending_update 
                ? __('Installa Aggiornamento Ora', 'fp-git-updater')
                : __('Installa Aggiornamento', 'fp-git-updater'); 
            ?>
        </button>
    </div>
    <?php
    $connected = isset($connected_clients) ? $connected_clients : [];
    $client_ids = array_keys($connected);
    $repo = $plugin['github_repo'] ?? '';
    if (!empty($repo)): ?>
    <div class="fp-plugin-deploy-row">
        <?php if (!empty($client_ids)):
            $all_id = 'fp-sel-' . preg_replace('/\W/', '_', $plugin['id']);
            $branch = $plugin['branch'] ?? 'main';
            $name = $plugin['name'] ?? basename(str_replace('/', '-', $repo));
        ?>
        <div class="fp-plugin-deploy-inline" data-repo="<?php echo esc_attr($repo); ?>" data-branch="<?php echo esc_attr($branch); ?>" data-name="<?php echo esc_attr($name); ?>">
            <div class="fp-deploy-header">
                <span class="fp-deploy-label">
                    <span class="dashicons dashicons-upload"></span>
                    <?php _e('Installa su clienti', 'fp-git-updater'); ?>
                </span>
                <label class="fp-deploy-client-check fp-select-all">
                    <input type="checkbox" id="<?php echo esc_attr($all_id); ?>">
                    <strong><?php _e('Seleziona tutti', 'fp-git-updater'); ?></strong>
                </label>
            </div>
            <div class="fp-deploy-clients-grid">
                <?php foreach ($client_ids as $c):
                    $c_ver = $client_versions[$c] ?? '';
                    // Confronta versione cliente con GitHub per colorare il badge
                    $ver_class = '';
                    if (!empty($c_ver) && !empty($github_version)) {
                        if (version_compare($c_ver, $github_version, '<')) {
                            $ver_class = 'fp-deploy-client-ver--old';
                        } elseif (version_compare($c_ver, $github_version, '>=')) {
                            $ver_class = 'fp-deploy-client-ver--ok';
                        }
                    }
                ?>
                <label class="fp-deploy-client-check">
                    <input type="checkbox" class="fp-client-cb fp-deploy-cb" data-all="<?php echo esc_attr($all_id); ?>" value="<?php echo esc_attr($c); ?>">
                    <span class="fp-deploy-client-dot"></span>
                    <span class="fp-deploy-client-name"><?php echo esc_html($c); ?></span>
                    <?php if (!empty($c_ver)): ?>
                        <span class="fp-deploy-client-ver <?php echo esc_attr($ver_class); ?>">v<?php echo esc_html($c_ver); ?></span>
                    <?php endif; ?>
                </label>
                <?php endforeach; ?>
            </div>
            <div class="fp-deploy-footer">
                <button type="button" class="button button-primary button-small fp-deploy-install-inline">
                    <span class="dashicons dashicons-download"></span> <?php _e('Installa sui selezionati', 'fp-git-updater'); ?>
                </button>
            </div>
        </div>
        <?php else: ?>
        <div class="fp-plugin-deploy-hint fp-plugin-deploy-no-clients">
            <span class="dashicons dashicons-info"></span>
            <?php _e('Nessun cliente collegato. Collega i siti con FP Remote Bridge; appariranno qui dopo la prima connessione.', 'fp-git-updater'); ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <div id="plugin-details-<?php echo $index; ?>" class="fp-plugin-details">
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
                    <div class="fp-input-group">
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
                            <span class="dashicons dashicons-download"></span>
                            <?php _e('Carica da GitHub', 'fp-git-updater'); ?>
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
