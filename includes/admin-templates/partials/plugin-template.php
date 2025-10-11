<?php
/**
 * Template JavaScript per Nuovo Plugin
 */

if (!defined('ABSPATH')) {
    return;
}
?>

<script type="text/template" id="fp-plugin-template">
    <div class="fp-plugin-item new-plugin" data-index="{{INDEX}}">
        <div class="fp-plugin-header">
            <h3><?php _e('Nuovo Plugin', 'fp-git-updater'); ?></h3>
            <div class="fp-plugin-actions">
                <button type="button" class="button fp-toggle-plugin" data-target="plugin-details-{{INDEX}}">
                    <span class="dashicons dashicons-edit"></span> <?php _e('Modifica', 'fp-git-updater'); ?>
                </button>
                <button type="button" class="button fp-remove-plugin" data-index="{{INDEX}}">
                    <span class="dashicons dashicons-trash"></span> <?php _e('Rimuovi', 'fp-git-updater'); ?>
                </button>
            </div>
        </div>
        <div class="fp-plugin-info">
            <span class="description"><?php _e('Configura i dettagli del plugin', 'fp-git-updater'); ?></span>
        </div>
        <div id="plugin-details-{{INDEX}}" class="fp-plugin-details" style="display: block;">
            <input type="hidden" name="fp_git_updater_settings[plugins][{{INDEX}}][id]" value="{{ID}}">
            
            <table class="form-table">
                <tr>
                    <th><label><?php _e('Nome Plugin', 'fp-git-updater'); ?></label></th>
                    <td>
                        <input type="text" 
                               name="fp_git_updater_settings[plugins][{{INDEX}}][name]" 
                               value="" 
                               class="regular-text" 
                               placeholder="<?php esc_attr_e('Es: Il mio plugin', 'fp-git-updater'); ?>" required>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Repository GitHub', 'fp-git-updater'); ?></label></th>
                    <td>
                        <input type="text" 
                               name="fp_git_updater_settings[plugins][{{INDEX}}][github_repo]" 
                               value="" 
                               class="regular-text" 
                               placeholder="username/repository" required>
                        <p class="description"><?php _e('Es: tuousername/mio-plugin', 'fp-git-updater'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Slug Plugin', 'fp-git-updater'); ?></label></th>
                    <td>
                        <input type="text" 
                               name="fp_git_updater_settings[plugins][{{INDEX}}][plugin_slug]" 
                               value="" 
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
                               name="fp_git_updater_settings[plugins][{{INDEX}}][branch]" 
                               value="main" 
                               class="regular-text">
                        <p class="description"><?php _e('Branch da cui scaricare gli aggiornamenti', 'fp-git-updater'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('GitHub Token', 'fp-git-updater'); ?></label></th>
                    <td>
                        <input type="password" 
                               name="fp_git_updater_settings[plugins][{{INDEX}}][github_token]" 
                               value="" 
                               class="regular-text" 
                               placeholder="ghp_...">
                        <p class="description"><?php _e('Opzionale, per repository privati', 'fp-git-updater'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Abilitato', 'fp-git-updater'); ?></label></th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="fp_git_updater_settings[plugins][{{INDEX}}][enabled]" 
                                   value="1" checked>
                            <?php _e('Abilita aggiornamenti per questo plugin', 'fp-git-updater'); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</script>
