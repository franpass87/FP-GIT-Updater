<?php
/**
 * Template Partial: Modalità Master (API per siti client FP-Remote-Bridge)
 *
 * @package FP\GitUpdater
 */

if (!defined('ABSPATH')) {
    return;
}

use FP\GitUpdater\MasterEndpoint;

$master_mode = get_option(MasterEndpoint::OPTION_MASTER_MODE, false);
$master_secret = get_option(MasterEndpoint::OPTION_MASTER_CLIENT_SECRET, '');
$master_url = MasterEndpoint::get_endpoint_url();

$backup_dir = WP_CONTENT_DIR . '/' . \FP\GitUpdater\ReceiveBackupEndpoint::BACKUP_DIR . '/';
$backup_dir_exists = is_dir($backup_dir);
$backup_dir_writable = $backup_dir_exists && is_writable($backup_dir);
?>

<div class="fp-section-header fp-master-header">
    <div class="fp-master-header-inner">
        <h2 class="fp-section-title">
            <span class="dashicons dashicons-networking"></span>
            <?php _e('Modalità Master', 'fp-git-updater'); ?>
        </h2>
        <span class="fp-master-status fp-master-status--<?php echo $master_mode ? 'active' : 'inactive'; ?>"
              data-label-active="<?php echo esc_attr__('Attiva', 'fp-git-updater'); ?>"
              data-label-inactive="<?php echo esc_attr__('Non attiva', 'fp-git-updater'); ?>">
            <?php echo $master_mode ? esc_html__('Attiva', 'fp-git-updater') : esc_html__('Non attiva', 'fp-git-updater'); ?>
        </span>
    </div>
    <p class="fp-section-description">
        <?php _e('I siti client con FP-Remote-Bridge contattano questo endpoint per verificare gli aggiornamenti e installarli in automatico. Configura qui URL e secret da usare sui client.', 'fp-git-updater'); ?>
    </p>
</div>

<div class="fp-settings-card fp-master-card">
    <form method="post" action="options.php" id="fp-master-form">
        <?php settings_fields('fp_git_updater_master_group'); ?>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><?php _e('Stato', 'fp-git-updater'); ?></th>
                    <td>
                        <label class="fp-checkbox-label fp-toggle-label">
                            <input type="checkbox" id="fp_git_updater_master_mode"
                                   name="fp_git_updater_master_mode" value="1"
                                   <?php checked($master_mode, true); ?>>
                            <?php _e('Abilita Modalità Master', 'fp-git-updater'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Quando attivo, l\'endpoint è raggiungibile dai client con il secret corretto.', 'fp-git-updater'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fp_git_updater_master_client_secret"><?php _e('Secret Client', 'fp-git-updater'); ?></label>
                    </th>
                    <td>
                        <div class="fp-input-group fp-input-group--secret">
                            <input type="password" id="fp_git_updater_master_client_secret"
                                   name="fp_git_updater_master_client_secret"
                                   value="<?php echo esc_attr($master_secret); ?>"
                                   class="regular-text" autocomplete="off"
                                   placeholder="<?php echo esc_attr__('Inserisci una chiave segreta', 'fp-git-updater'); ?>">
                            <button type="button" class="button button-secondary fp-btn-toggle-password" aria-label="<?php esc_attr_e('Mostra/Nascondi password', 'fp-git-updater'); ?>">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                        </div>
                        <p class="description">
                            <code>X-FP-Client-Secret</code>
                            <?php _e('— Lo stesso valore va configurato in Impostazioni → FP Remote Bridge su ogni sito client.', 'fp-git-updater'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fp-master-endpoint-url"><?php _e('URL da dare ai client', 'fp-git-updater'); ?></label>
                    </th>
                    <td>
                        <div class="fp-input-group fp-input-group--url">
                            <input type="text" value="<?php echo esc_attr($master_url); ?>"
                                   class="regular-text fp-url-input" readonly id="fp-master-endpoint-url"
                                   aria-describedby="fp-master-url-desc">
                            <button type="button" class="button fp-btn-copy fp-btn-copy-master" data-copy-target="fp-master-endpoint-url">
                                <span class="fp-btn-copy-icon dashicons dashicons-clipboard"></span>
                                <span class="fp-btn-copy-text"><?php _e('Copia', 'fp-git-updater'); ?></span>
                            </button>
                        </div>
                        <p id="fp-master-url-desc" class="description">
                            <?php _e('Incolla questo URL nel campo «URL Master» nelle impostazioni di FP Remote Bridge su ogni sito client.', 'fp-git-updater'); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php submit_button(__('Salva Impostazioni Master', 'fp-git-updater')); ?>
    </form>
</div>

<div class="fp-settings-card fp-master-quick-setup">
    <h3 class="fp-master-quick-setup-title">
        <span class="dashicons dashicons-admin-links"></span>
        <?php _e('Setup rapido sul sito client', 'fp-git-updater'); ?>
    </h3>
    <ol class="fp-master-quick-setup-list">
        <li><?php _e('Installa e attiva il plugin FP Remote Bridge.', 'fp-git-updater'); ?></li>
        <li><?php _e('Vai in Impostazioni → FP Remote Bridge.', 'fp-git-updater'); ?></li>
        <li><?php _e('Incolla l\'URL Endpoint Master (pulsante Copia sopra).', 'fp-git-updater'); ?></li>
        <li><?php _e('Inserisci lo stesso Secret Client configurato qui.', 'fp-git-updater'); ?></li>
        <li><?php _e('Salva: i client controlleranno gli aggiornamenti in base alla frequenza scelta.', 'fp-git-updater'); ?></li>
    </ol>
</div>

<div class="fp-settings-card fp-master-backup-card">
    <h3 class="fp-master-backup-title">
        <span class="dashicons dashicons-database-export"></span>
        <?php _e('Backup dai client', 'fp-git-updater'); ?>
    </h3>
    <p class="fp-master-backup-desc">
        <?php _e('I siti client possono inviare backup completi (database + file) a questo Master. I file vengono salvati nella cartella indicata sotto, organizzati per client.', 'fp-git-updater'); ?>
    </p>
    <div class="fp-master-backup-path">
        <code class="fp-master-backup-path-code" title="<?php echo esc_attr($backup_dir); ?>"><?php echo esc_html($backup_dir); ?></code>
    </div>
    <?php if ($backup_dir_exists) : ?>
        <p class="fp-master-backup-status fp-master-backup-status--<?php echo $backup_dir_writable ? 'ok' : 'warn'; ?>">
            <span class="dashicons dashicons-<?php echo $backup_dir_writable ? 'yes-alt' : 'warning'; ?>"></span>
            <?php echo $backup_dir_writable
                ? esc_html__('Cartella presente e scrivibile.', 'fp-git-updater')
                : esc_html__('Cartella presente ma non scrivibile. Verifica i permessi.', 'fp-git-updater'); ?>
        </p>
    <?php else : ?>
        <p class="fp-master-backup-status fp-master-backup-status--info">
            <span class="dashicons dashicons-info"></span>
            <?php _e('La cartella verrà creata al primo backup ricevuto da un client.', 'fp-git-updater'); ?>
        </p>
    <?php endif; ?>
    <p class="description">
        <?php _e('Per scaricare i backup usa FTP/SFTP o il file manager del tuo hosting.', 'fp-git-updater'); ?>
    </p>
</div>
