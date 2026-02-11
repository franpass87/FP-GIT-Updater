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
?>

<div class="fp-section-header">
    <h2 class="fp-section-title">
        <span class="dashicons dashicons-networking"></span>
        <?php _e('Modalità Master (siti client FP-Remote-Bridge)', 'fp-git-updater'); ?>
    </h2>
    <p class="fp-section-description">
        <?php _e('Quando attivo, i siti client con FP-Remote-Bridge possono interrogare questo endpoint per sapere se ci sono aggiornamenti. Se sì, eseguiranno gli aggiornamenti localmente.', 'fp-git-updater'); ?>
    </p>
</div>

<div class="fp-settings-card">
    <form method="post" action="options.php">
        <?php settings_fields('fp_git_updater_master_group'); ?>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="fp_git_updater_master_mode"><?php _e('Abilita Modalità Master', 'fp-git-updater'); ?></label>
                    </th>
                    <td>
                        <label class="fp-checkbox-label">
                            <input type="checkbox" id="fp_git_updater_master_mode"
                                   name="fp_git_updater_master_mode" value="1"
                                   <?php checked($master_mode, true); ?>>
                            <?php _e('Esponi l\'endpoint per i siti client che usano FP-Remote-Bridge', 'fp-git-updater'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fp_git_updater_master_client_secret"><?php _e('Secret Client', 'fp-git-updater'); ?></label>
                    </th>
                    <td>
                        <input type="password" id="fp_git_updater_master_client_secret"
                               name="fp_git_updater_master_client_secret"
                               value="<?php echo esc_attr($master_secret); ?>"
                               class="regular-text" autocomplete="off">
                        <p class="description">
                            <?php _e('Configura lo stesso secret in Impostazioni → FP Remote Bridge su ogni sito client. I client invieranno questo valore nell\'header', 'fp-git-updater'); ?>
                            <code>X-FP-Client-Secret</code>
                            <?php _e('per autenticarsi.', 'fp-git-updater'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('URL Endpoint Master', 'fp-git-updater'); ?></th>
                    <td>
                        <div class="fp-input-group">
                            <input type="text" value="<?php echo esc_attr($master_url); ?>"
                                   class="regular-text" readonly id="fp-master-endpoint-url">
                            <button type="button" class="button fp-btn-copy"
                                    onclick="navigator.clipboard.writeText(document.getElementById('fp-master-endpoint-url').value); this.innerHTML='<span class=\'dashicons dashicons-yes\'></span> <?php echo esc_js(__('Copiato!', 'fp-git-updater')); ?>';">
                                <span class="dashicons dashicons-clipboard"></span> <?php _e('Copia', 'fp-git-updater'); ?>
                            </button>
                        </div>
                        <p class="description">
                            <?php _e('Incolla questo URL in Impostazioni → FP Remote Bridge su ogni sito client.', 'fp-git-updater'); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php submit_button(__('Salva Impostazioni Master', 'fp-git-updater')); ?>
    </form>
</div>
