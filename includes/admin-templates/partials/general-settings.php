<?php
/**
 * Template Partial: Impostazioni Generali
 * 
 * @var array $settings Impostazioni del plugin
 * @var string $webhook_url URL del webhook
 */

if (!defined('ABSPATH')) {
    return;
}

// Username hardcodato a FranPass87
$default_github_username = 'FranPass87';
$global_github_token = isset($settings['global_github_token']) ? $settings['global_github_token'] : '';
$webhook_secret = isset($settings['webhook_secret']) ? $settings['webhook_secret'] : '';
$auto_update = isset($settings['auto_update']) ? $settings['auto_update'] : false;
$update_check_interval = isset($settings['update_check_interval']) ? $settings['update_check_interval'] : 'hourly';
$enable_notifications = isset($settings['enable_notifications']) ? $settings['enable_notifications'] : false;
$notification_email = isset($settings['notification_email']) ? $settings['notification_email'] : get_option('admin_email');
?>

<div class="fp-section-header">
    <h2 class="fp-section-title">
        <span class="dashicons dashicons-admin-settings"></span>
        <?php _e('Impostazioni Generali', 'fp-git-updater'); ?>
    </h2>
    <p class="fp-section-description">
        <?php _e('Configura le impostazioni principali del plugin per gestire gli aggiornamenti.', 'fp-git-updater'); ?>
    </p>
</div>

<div class="fp-settings-card">
    <table class="form-table">
        <tbody>
            <tr>
                <th scope="row">
                    <label><?php _e('Username GitHub', 'fp-git-updater'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           value="<?php echo esc_attr($default_github_username); ?>" 
                           class="regular-text" 
                           readonly>
                    <p class="description">
                        <span class="dashicons dashicons-info"></span>
                        <?php _e('Username GitHub predefinito. Tutti i plugin utilizzano questo username.', 'fp-git-updater'); ?>
                        <?php echo \FP\GitUpdater\I18nHelper::help_link('default_github_username'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="global_github_token"><?php _e('Token GitHub Globale', 'fp-git-updater'); ?></label>
                </th>
                <td>
                    <input type="password" 
                           id="global_github_token" 
                           name="fp_git_updater_settings[global_github_token]" 
                           value="<?php echo esc_attr($global_github_token); ?>" 
                           class="regular-text" 
                           placeholder="ghp_...">
                    <p class="description">
                        <span class="dashicons dashicons-info"></span>
                        <?php _e('Token GitHub globale valido per tutti i plugin. Necessario solo per repository privati.', 'fp-git-updater'); ?>
                        <?php echo \FP\GitUpdater\I18nHelper::help_link('github_token'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="webhook_secret"><?php _e('Webhook Secret', 'fp-git-updater'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="webhook_secret" 
                           name="fp_git_updater_settings[webhook_secret]" 
                           value="<?php echo esc_attr($webhook_secret); ?>" 
                           class="regular-text" 
                           readonly>
                    <p class="description">
                        <?php _e('Copia questo secret nelle impostazioni del webhook su GitHub', 'fp-git-updater'); ?>
                        <?php echo \FP\GitUpdater\I18nHelper::help_link('webhook_secret'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('URL Webhook', 'fp-git-updater'); ?></th>
                <td>
                    <div class="fp-input-group">
                        <input type="text" 
                               value="<?php echo esc_attr($webhook_url); ?>" 
                               class="regular-text" 
                               readonly>
                        <button type="button" class="button fp-btn-copy" onclick="navigator.clipboard.writeText('<?php echo esc_js($webhook_url); ?>')">
                            <span class="dashicons dashicons-clipboard"></span> <?php _e('Copia', 'fp-git-updater'); ?>
                        </button>
                    </div>
                    <p class="description">
                        <?php _e('Usa questo URL quando configuri il webhook su GitHub per tutti i repository', 'fp-git-updater'); ?>
                        <?php echo \FP\GitUpdater\I18nHelper::help_link('webhook_url'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Aggiornamento Automatico', 'fp-git-updater'); ?></th>
                <td>
                    <label class="fp-checkbox-label">
                        <input type="checkbox" 
                               name="fp_git_updater_settings[auto_update]" 
                               value="1" 
                               <?php checked($auto_update, true); ?>>
                        <?php _e('Aggiorna automaticamente quando ricevi un push su GitHub', 'fp-git-updater'); ?>
                        <?php echo \FP\GitUpdater\I18nHelper::help_link('auto_update'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="update_check_interval"><?php _e('Intervallo Controllo Aggiornamenti', 'fp-git-updater'); ?></label>
                </th>
                <td>
                    <select id="update_check_interval" name="fp_git_updater_settings[update_check_interval]">
                        <option value="hourly" <?php selected($update_check_interval, 'hourly'); ?>>
                            <?php echo \FP\GitUpdater\I18nHelper::get_interval_label('hourly'); ?>
                        </option>
                        <option value="twicedaily" <?php selected($update_check_interval, 'twicedaily'); ?>>
                            <?php echo \FP\GitUpdater\I18nHelper::get_interval_label('twicedaily'); ?>
                        </option>
                        <option value="daily" <?php selected($update_check_interval, 'daily'); ?>>
                            <?php echo \FP\GitUpdater\I18nHelper::get_interval_label('daily'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php _e('Frequenza di controllo per nuovi aggiornamenti (oltre ai webhook)', 'fp-git-updater'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Notifiche Email', 'fp-git-updater'); ?></th>
                <td>
                    <label class="fp-checkbox-label">
                        <input type="checkbox" 
                               name="fp_git_updater_settings[enable_notifications]" 
                               value="1" 
                               <?php checked($enable_notifications, true); ?>>
                        <?php _e('Abilita notifiche email per gli aggiornamenti', 'fp-git-updater'); ?>
                    </label>
                    <p class="description">
                        <span class="dashicons dashicons-info"></span>
                        <?php _e('Le notifiche email sono <strong>disabilitate di default</strong>. Attiva questa opzione solo se desideri ricevere email quando sono disponibili nuovi aggiornamenti.', 'fp-git-updater'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="notification_email"><?php _e('Email Notifiche', 'fp-git-updater'); ?></label>
                </th>
                <td>
                    <input type="email" 
                           id="notification_email" 
                           name="fp_git_updater_settings[notification_email]" 
                           value="<?php echo esc_attr($notification_email); ?>" 
                           class="regular-text">
                </td>
            </tr>
        </tbody>
    </table>
</div>
