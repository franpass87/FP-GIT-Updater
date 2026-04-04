<?php
/**
 * Template Partial: Configurazione Modalità Master (form)
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
?>

<div class="fp-settings-card fp-master-config-card">
    <div class="fp-master-config-header">
        <span class="fp-step-badge" aria-hidden="true">1</span>
        <h3 class="fp-master-config-title">
            <span class="dashicons dashicons-networking"></span>
            <?php _e('Configurazione Master', 'fp-git-updater'); ?>
        </h3>
        <span class="fp-master-status fp-master-status--<?php echo $master_mode ? 'active' : 'inactive'; ?>"
              data-label-active="<?php echo esc_attr__('Attiva', 'fp-git-updater'); ?>"
              data-label-inactive="<?php echo esc_attr__('Non attiva', 'fp-git-updater'); ?>">
            <?php echo $master_mode ? esc_html__('Attiva', 'fp-git-updater') : esc_html__('Non attiva', 'fp-git-updater'); ?>
        </span>
    </div>
    <p class="fp-master-config-desc">
        <?php _e('Abilita il Master, inserisci la chiave segreta e l\'URL. I siti dei tuoi clienti con FP Remote Bridge si collegheranno qui per ricevere plugin e aggiornamenti.', 'fp-git-updater'); ?>
    </p>
    <ul class="fp-master-config-steps">
        <li><?php _e('Spunta «Abilita Modalità Master»', 'fp-git-updater'); ?></li>
        <li><?php _e('Inserisci la chiave segreta (la stessa va usata su ogni sito cliente nel Bridge)', 'fp-git-updater'); ?></li>
        <li><?php _e('Copia l\'URL Endpoint e incollalo nel Bridge su ogni sito dei clienti', 'fp-git-updater'); ?></li>
    </ul>
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
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fp_git_updater_master_client_secret"><?php _e('Chiave segreta', 'fp-git-updater'); ?></label>
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
                            <?php _e('Lo stesso valore va configurato in FP Remote Bridge su ogni sito cliente.', 'fp-git-updater'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fp-master-endpoint-url"><?php _e('URL Endpoint (da copiare nei siti clienti)', 'fp-git-updater'); ?></label>
                    </th>
                    <td>
                        <div class="fp-input-group fp-input-group--url">
                            <input type="text" value="<?php echo esc_attr($master_url); ?>"
                                   class="regular-text fp-url-input" readonly id="fp-master-endpoint-url">
                            <button type="button" class="button fp-btn-copy fp-btn-copy-master" data-copy-target="fp-master-endpoint-url">
                                <span class="fp-btn-copy-icon dashicons dashicons-clipboard"></span>
                                <span class="fp-btn-copy-text"><?php _e('Copia', 'fp-git-updater'); ?></span>
                            </button>
                        </div>
                        <p class="description">
                            <?php _e('Incollalo nel campo «URL Endpoint» di FP Remote Bridge su ogni sito cliente.', 'fp-git-updater'); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php submit_button(__('Salva impostazioni Master', 'fp-git-updater'), 'secondary fpgitupdater-btn fpgitupdater-btn-secondary', 'submit', false); ?>
    </form>
</div>
