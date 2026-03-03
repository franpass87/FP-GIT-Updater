<?php
/**
 * Template Partial: Distribuzione ai client – Tab Installa / Aggiorna
 *
 * @package FP\GitUpdater
 */

if (!defined('ABSPATH')) {
    return;
}

use FP\GitUpdater\MasterEndpoint;

$deploy_until = (int) get_option(MasterEndpoint::OPTION_DEPLOY_AUTHORIZED_UNTIL, 0);
$deploy_active = $deploy_until > time();
$updater = \FP\GitUpdater\Updater::get_instance();
$pending_updates = isset($pending_updates) ? $pending_updates : $updater->get_pending_updates();
$connected_clients = MasterEndpoint::get_connected_clients();
$settings = get_option('fp_git_updater_settings', []);
$configured_plugins = isset($settings['plugins']) ? $settings['plugins'] : [];
// Escludi self-update come in settings-page
$configured_plugins = array_values(array_filter($configured_plugins, function ($p) {
    if (isset($p['id']) && $p['id'] === 'fp_git_updater_self') return false;
    if (isset($p['plugin_slug']) && $p['plugin_slug'] === 'fp-git-updater') return false;
    return true;
}));
$backup_dir = WP_CONTENT_DIR . '/' . \FP\GitUpdater\ReceiveBackupEndpoint::BACKUP_DIR . '/';
$backup_dir_exists = is_dir($backup_dir);
$backup_dir_writable = $backup_dir_exists && is_writable($backup_dir);
?>

<!-- Sezione Distribuzione Master: Tab Installa / Aggiorna -->
<div class="fp-settings-card fp-master-deploy-card">
    <h3 class="fp-master-deploy-title">
        <span class="dashicons dashicons-cloud-upload"></span>
        <?php _e('Altro: installa repo extra e aggiorna clienti', 'fp-git-updater'); ?>
    </h3>
    <p class="fp-master-deploy-desc">
        <?php _e('Installazione sui clienti avviene dalle card sopra. Qui: <strong>Carica da GitHub</strong> per repo non in elenco; <strong>Aggiorna chi ce l\'ha</strong> per inviare aggiornamenti.', 'fp-git-updater'); ?>
    </p>

    <?php if ($deploy_active): ?>
        <p class="fp-master-deploy-status-active">
            <span class="dashicons dashicons-yes-alt"></span>
            <?php printf(
                __('Autorizzazione attiva fino alle %s (UTC). I siti installeranno/aggiorneranno nelle prossime 2 ore.', 'fp-git-updater'),
                esc_html(gmdate('H:i', $deploy_until) . ' del ' . gmdate('d/m/Y', $deploy_until))
            ); ?>
        </p>
    <?php endif; ?>

    <!-- Sub-tab Installa / Aggiorna -->
    <nav class="fp-master-deploy-subnav" role="tablist" aria-label="<?php esc_attr_e('Tipo di distribuzione', 'fp-git-updater'); ?>">
        <button type="button" class="fp-master-deploy-subtab active" role="tab" aria-selected="true" aria-controls="fp-deploy-tab-install" id="fp-deploy-tab-install-btn" data-subtab="install">
            <span class="dashicons dashicons-download"></span>
            <?php _e('Installa su clienti', 'fp-git-updater'); ?>
        </button>
        <button type="button" class="fp-master-deploy-subtab" role="tab" aria-selected="false" aria-controls="fp-deploy-tab-update" id="fp-deploy-tab-update-btn" data-subtab="update">
            <span class="dashicons dashicons-update"></span>
            <?php _e('Aggiorna chi ce l\'ha', 'fp-git-updater'); ?>
        </button>
    </nav>

    <!-- Tab Installa: solo repo non ancora in elenco (da GitHub) -->
    <div id="fp-deploy-tab-install" class="fp-master-deploy-subcontent active" role="tabpanel" aria-labelledby="fp-deploy-tab-install-btn">
        <p class="fp-deploy-tab-desc">
            <?php _e('Per i plugin in elenco sopra, usa «Installa su clienti» nella card di ogni plugin. Qui sotto carichi repo <strong>non ancora aggiunti</strong> per installarli in un colpo solo.', 'fp-git-updater'); ?>
        </p>
        <?php if (empty($connected_clients)): ?>
            <p class="fp-deploy-hint fp-deploy-no-clients-hint">
                <span class="dashicons dashicons-info"></span>
                <?php _e('Nessun cliente collegato. Collega i siti dei tuoi clienti con FP Remote Bridge; appariranno qui dopo la prima connessione.', 'fp-git-updater'); ?>
            </p>
        <?php endif; ?>
        <div class="fp-deploy-load-repos-row">
            <button type="button" id="fp-master-load-github-repos" class="button button-secondary">
                <span class="dashicons dashicons-download"></span>
                <?php _e('Carica da GitHub', 'fp-git-updater'); ?>
            </button>
            <span id="fp-github-repos-loading" class="fp-loading-text fp-loading-text--hidden" aria-live="polite"><?php _e('Caricamento...', 'fp-git-updater'); ?></span>
        </div>
        <div id="fp-github-repos-list" class="fp-github-repos-list fp-github-repos-list--hidden">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:30%"><?php _e('Plugin', 'fp-git-updater'); ?></th>
                        <th><?php _e('Repository', 'fp-git-updater'); ?></th>
                        <th style="width:35%"><?php _e('Clienti destinatari', 'fp-git-updater'); ?></th>
                        <th style="width:120px"><?php _e('Azione', 'fp-git-updater'); ?></th>
                    </tr>
                </thead>
                <tbody id="fp-github-repos-tbody">
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tab Aggiorna: plugin con update + client che ce l'hanno -->
    <div id="fp-deploy-tab-update" class="fp-master-deploy-subcontent" role="tabpanel" aria-labelledby="fp-deploy-tab-update-btn">
        <p class="fp-deploy-tab-desc">
            <?php _e('Plugin con aggiornamento disponibile. Clicca «Aggiorna tutti» per inviare l\'aggiornamento solo ai clienti che hanno quel plugin installato.', 'fp-git-updater'); ?>
        </p>
        <?php if (empty($pending_updates)): ?>
            <p class="fp-deploy-hint">
                <?php _e('Nessun aggiornamento disponibile. Usa «Controlla aggiornamenti» sui plugin nella lista sopra per rilevare nuove versioni.', 'fp-git-updater'); ?>
            </p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped fp-deploy-update-table">
                <thead>
                    <tr>
                        <th><?php _e('Plugin', 'fp-git-updater'); ?></th>
                        <th><?php _e('Versione', 'fp-git-updater'); ?></th>
                        <th><?php _e('Clienti con plugin', 'fp-git-updater'); ?></th>
                        <th style="width:140px"><?php _e('Azione', 'fp-git-updater'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_updates as $p):
                        $plugin = $p['plugin'] ?? [];
                        $plugin_id = $plugin['id'] ?? '';
                        $slug = $plugin['plugin_slug'] ?? '';
                        if (empty($slug) && !empty($plugin['github_repo'])) {
                            $parts = explode('/', $plugin['github_repo']);
                            $slug = strtolower(end($parts));
                        }
                        $clients_with = MasterEndpoint::get_clients_with_plugin($slug);
                        $client_count = count($clients_with);
                        ?>
                        <tr data-plugin-id="<?php echo esc_attr($plugin_id); ?>" data-plugin-slug="<?php echo esc_attr($slug); ?>">
                            <td><strong><?php echo esc_html($plugin['name'] ?? $plugin_id); ?></strong></td>
                            <td>
                                <?php echo esc_html($p['current_version'] ?? '—'); ?>
                                → <strong><?php echo esc_html($p['available_version'] ?? ''); ?></strong>
                            </td>
                            <td>
                                <?php if ($client_count > 0): ?>
                                    <span class="fp-client-count"><?php echo (int) $client_count; ?> <?php echo esc_html(_n('cliente', 'clienti', (int) $client_count, 'fp-git-updater')); ?></span>
                                    <span class="fp-client-ids" title="<?php echo esc_attr(implode(', ', $clients_with)); ?>"><?php echo esc_html(implode(', ', array_slice($clients_with, 0, 3))); ?><?php echo $client_count > 3 ? '…' : ''; ?></span>
                                <?php else: ?>
                                    <span class="fp-no-clients"><?php _e('Nessun cliente', 'fp-git-updater'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="button button-small fp-deploy-update-btn" <?php echo $client_count === 0 ? 'disabled' : ''; ?>
                                        data-plugin-id="<?php echo esc_attr($plugin_id); ?>">
                                    <span class="dashicons dashicons-cloud-upload"></span>
                                    <?php _e('Aggiorna tutti', 'fp-git-updater'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Client collegati -->
<div class="fp-settings-card fp-master-clients-card">
    <div class="fp-master-clients-header">
        <h3 class="fp-master-clients-title">
            <span class="dashicons dashicons-groups"></span>
            <?php _e('Clienti collegati', 'fp-git-updater'); ?>
            <?php if (!empty($connected_clients)): ?>
                <span class="fp-master-clients-badge"><?php echo count($connected_clients); ?></span>
            <?php endif; ?>
        </h3>
        <button type="button" id="fp-refresh-clients-btn" class="button button-secondary" title="<?php esc_attr_e('Ricarica elenco clienti', 'fp-git-updater'); ?>">
            <span class="dashicons dashicons-update"></span>
            <?php _e('Aggiorna elenco', 'fp-git-updater'); ?>
        </button>
    </div>
    <p class="fp-master-clients-desc">
        <?php _e('Siti dei tuoi clienti con FP Remote Bridge che hanno contattato il Master negli ultimi 30 giorni. I plugin installati vengono rilevati automaticamente.', 'fp-git-updater'); ?>
    </p>
    <div id="fp-master-clients-content">
    <?php if (empty($connected_clients)): ?>
        <div class="fp-master-clients-empty" style="padding: 16px; background: #f0f6fc; border-left: 4px solid #2271b1; margin: 12px 0;">
            <p style="margin: 0 0 12px;"><strong><?php _e('Nessun cliente collegato.', 'fp-git-updater'); ?></strong></p>
            <p style="margin: 0 0 8px;"><?php _e('Per far apparire un sito qui:', 'fp-git-updater'); ?></p>
            <ol style="margin: 0 0 0 20px; padding: 0;">
                <li><?php _e('Sul sito del cliente: Impostazioni → FP Remote Bridge', 'fp-git-updater'); ?></li>
                <li><?php _e('Inserisci URL Master (es. https://manager.francescopasseri.com) e la Chiave segreta (identica a quella qui sopra)', 'fp-git-updater'); ?></li>
                <li><?php _e('Salva e clicca «Sincronizza ora» — il cliente apparirà subito in questa lista', 'fp-git-updater'); ?></li>
            </ol>
            <p style="margin: 12px 0 0; font-size: 12px; color: #646970;">
                <?php _e('Se il cliente non appare: verifica che Modalità Master sia attiva, che la chiave segreta coincida esattamente e che il sito cliente riesca a raggiungere l\'URL del Master.', 'fp-git-updater'); ?>
            </p>
        </div>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped fp-master-clients-table">
            <thead>
                <tr>
                    <th scope="col" style="width: 35%;"><?php _e('Sito cliente', 'fp-git-updater'); ?></th>
                    <th scope="col" style="width: 35%;"><?php _e('Plugin installati', 'fp-git-updater'); ?></th>
                    <th scope="col"><?php _e('Ultima connessione', 'fp-git-updater'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($connected_clients as $client_id => $data):
                    $installed = $data['installed_plugins'] ?? [];
                    $installed_str = !empty($installed) ? implode(', ', array_slice($installed, 0, 8)) . (count($installed) > 8 ? '…' : '') : '—';
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($client_id); ?></strong></td>
                        <td><small><?php echo esc_html($installed_str); ?></small></td>
                        <td><?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $data['last_seen'] ?? 0)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    </div>
</div>

<!-- Setup rapido -->
<div class="fp-settings-card fp-master-quick-setup">
    <h3 class="fp-master-quick-setup-title">
        <span class="dashicons dashicons-admin-links"></span>
        <?php _e('Setup rapido sul sito del cliente', 'fp-git-updater'); ?>
    </h3>
    <ol class="fp-master-quick-setup-list">
        <li><?php _e('Installa e attiva FP Remote Bridge.', 'fp-git-updater'); ?></li>
        <li><?php _e('Incolla l\'URL Endpoint Master e la chiave segreta nelle impostazioni del Bridge.', 'fp-git-updater'); ?></li>
        <li><?php _e('Salva. I siti dei clienti installeranno/aggiorneranno SOLO quando autorizzi da qui.', 'fp-git-updater'); ?></li>
    </ol>
</div>

<!-- Backup dai client -->
<div class="fp-settings-card fp-master-backup-card">
    <h3 class="fp-master-backup-title">
        <span class="dashicons dashicons-database-export"></span>
        <?php _e('Backup dai clienti', 'fp-git-updater'); ?>
    </h3>
    <p class="fp-master-backup-desc">
        <?php _e('I siti dei clienti possono inviare backup a questo Master. Cartella:', 'fp-git-updater'); ?>
        <code><?php echo esc_html($backup_dir); ?></code>
    </p>
    <?php if ($backup_dir_exists): ?>
        <p class="fp-master-backup-status fp-master-backup-status--<?php echo $backup_dir_writable ? 'ok' : 'warn'; ?>">
            <span class="dashicons dashicons-<?php echo $backup_dir_writable ? 'yes-alt' : 'warning'; ?>"></span>
            <?php echo $backup_dir_writable ? esc_html__('Cartella scrivibile.', 'fp-git-updater') : esc_html__('Cartella non scrivibile.', 'fp-git-updater'); ?>
        </p>
    <?php endif; ?>
</div>
