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

$webhook_secret = isset($settings['webhook_secret']) ? $settings['webhook_secret'] : '';
$auto_update = isset($settings['auto_update']) ? $settings['auto_update'] : false;
$update_check_interval = isset($settings['update_check_interval']) ? $settings['update_check_interval'] : 'hourly';
$enable_notifications = isset($settings['enable_notifications']) ? $settings['enable_notifications'] : true;
$notification_email = isset($settings['notification_email']) ? $settings['notification_email'] : get_option('admin_email');
?>

<h2><?php _e('Impostazioni Generali', 'fp-git-updater'); ?></h2>

<table class="form-table">
    <tbody>
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
                    <?php echo FP_Git_Updater_I18n_Helper::help_link('webhook_secret'); ?>
                </p>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><?php _e('URL Webhook', 'fp-git-updater'); ?></th>
            <td>
                <input type="text" 
                       value="<?php echo esc_attr($webhook_url); ?>" 
                       class="regular-text" 
                       readonly>
                <button type="button" class="button" onclick="navigator.clipboard.writeText('<?php echo esc_js($webhook_url); ?>')">
                    <span class="dashicons dashicons-clipboard"></span> <?php _e('Copia', 'fp-git-updater'); ?>
                </button>
                <p class="description">
                    <?php _e('Usa questo URL quando configuri il webhook su GitHub per tutti i repository', 'fp-git-updater'); ?>
                    <?php echo FP_Git_Updater_I18n_Helper::help_link('webhook_url'); ?>
                </p>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><?php _e('Aggiornamento Automatico', 'fp-git-updater'); ?></th>
            <td>
                <label>
                    <input type="checkbox" 
                           name="fp_git_updater_settings[auto_update]" 
                           value="1" 
                           <?php checked($auto_update, true); ?>>
                    <?php _e('Aggiorna automaticamente quando ricevi un push su GitHub', 'fp-git-updater'); ?>
                    <?php echo FP_Git_Updater_I18n_Helper::help_link('auto_update'); ?>
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
                        <?php echo FP_Git_Updater_I18n_Helper::get_interval_label('hourly'); ?>
                    </option>
                    <option value="twicedaily" <?php selected($update_check_interval, 'twicedaily'); ?>>
                        <?php echo FP_Git_Updater_I18n_Helper::get_interval_label('twicedaily'); ?>
                    </option>
                    <option value="daily" <?php selected($update_check_interval, 'daily'); ?>>
                        <?php echo FP_Git_Updater_I18n_Helper::get_interval_label('daily'); ?>
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
                <label>
                    <input type="checkbox" 
                           name="fp_git_updater_settings[enable_notifications]" 
                           value="1" 
                           <?php checked($enable_notifications, true); ?>>
                    <?php _e('Invia notifiche email per gli aggiornamenti', 'fp-git-updater'); ?>
                </label>
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
