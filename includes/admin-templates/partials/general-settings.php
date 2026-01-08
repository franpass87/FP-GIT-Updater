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

$default_github_username = isset($settings['default_github_username']) ? $settings['default_github_username'] : '';
$webhook_secret = isset($settings['webhook_secret']) ? $settings['webhook_secret'] : '';
$auto_update = isset($settings['auto_update']) ? $settings['auto_update'] : false;
$update_check_interval = isset($settings['update_check_interval']) ? $settings['update_check_interval'] : 'hourly';
$enable_notifications = isset($settings['enable_notifications']) ? $settings['enable_notifications'] : true;
$notification_email = isset($settings['notification_email']) ? $settings['notification_email'] : get_option('admin_email');
$max_backups = isset($settings['max_backups']) ? intval($settings['max_backups']) : 5;
$max_backup_age_days = isset($settings['max_backup_age_days']) ? intval($settings['max_backup_age_days']) : 7;
?>

<h2><?php _e('Impostazioni Generali', 'fp-git-updater'); ?></h2>

<table class="form-table">
    <tbody>
        <tr>
            <th scope="row">
                <label for="default_github_username"><?php _e('Username GitHub Predefinito', 'fp-git-updater'); ?></label>
            </th>
            <td>
                <input type="text" 
                       id="default_github_username" 
                       name="fp_git_updater_settings[default_github_username]" 
                       value="<?php echo esc_attr($default_github_username); ?>" 
                       class="regular-text" 
                       placeholder="franpass87">
                <p class="description">
                    <span class="dashicons dashicons-info" style="color: #2271b1;"></span>
                    <?php _e('Il tuo username GitHub. Se impostato, potrai inserire solo il nome del repository (es: "FP-Forms") invece di "username/repository".', 'fp-git-updater'); ?>
                    <?php echo \FP\GitUpdater\I18nHelper::help_link('default_github_username'); ?>
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
                <input type="text" 
                       value="<?php echo esc_attr($webhook_url); ?>" 
                       class="regular-text" 
                       readonly>
                <button type="button" class="button" onclick="navigator.clipboard.writeText('<?php echo esc_js($webhook_url); ?>')">
                    <span class="dashicons dashicons-clipboard"></span> <?php _e('Copia', 'fp-git-updater'); ?>
                </button>
                <p class="description">
                    <?php _e('Usa questo URL quando configuri il webhook su GitHub per tutti i repository', 'fp-git-updater'); ?>
                    <?php echo \FP\GitUpdater\I18nHelper::help_link('webhook_url'); ?>
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

<h2><?php _e('Gestione Backup', 'fp-git-updater'); ?></h2>
<p class="description">
    <?php _e('Configura i limiti per i backup automatici per evitare di saturare lo spazio disco.', 'fp-git-updater'); ?>
</p>

<table class="form-table">
    <tbody>
        <tr>
            <th scope="row">
                <label for="max_backups"><?php _e('Numero Massimo Backup', 'fp-git-updater'); ?></label>
            </th>
            <td>
                <input type="number" 
                       id="max_backups" 
                       name="fp_git_updater_settings[max_backups]" 
                       value="<?php echo esc_attr($max_backups); ?>" 
                       class="small-text" 
                       min="1" 
                       max="20" 
                       step="1">
                <p class="description">
                    <span class="dashicons dashicons-info" style="color: #2271b1;"></span>
                    <?php _e('Numero massimo di backup da mantenere. I backup più vecchi verranno eliminati automaticamente. (Consigliato: 5)', 'fp-git-updater'); ?>
                </p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="max_backup_age_days"><?php _e('Età Massima Backup (giorni)', 'fp-git-updater'); ?></label>
            </th>
            <td>
                <input type="number" 
                       id="max_backup_age_days" 
                       name="fp_git_updater_settings[max_backup_age_days]" 
                       value="<?php echo esc_attr($max_backup_age_days); ?>" 
                       class="small-text" 
                       min="1" 
                       max="30" 
                       step="1">
                <p class="description">
                    <span class="dashicons dashicons-info" style="color: #2271b1;"></span>
                    <?php _e('I backup più vecchi di questo numero di giorni verranno eliminati automaticamente. (Consigliato: 7)', 'fp-git-updater'); ?>
                </p>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><?php _e('Statistiche Backup', 'fp-git-updater'); ?></th>
            <td>
                <div id="fp-backup-stats" style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
                    <p><strong><?php _e('Caricamento statistiche...', 'fp-git-updater'); ?></strong></p>
                </div>
                <p>
                    <button type="button" class="button" id="fp-refresh-backup-stats">
                        <span class="dashicons dashicons-update"></span> <?php _e('Aggiorna Statistiche', 'fp-git-updater'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="fp-cleanup-backups-now">
                        <span class="dashicons dashicons-trash"></span> <?php _e('Pulisci Backup Vecchi Ora', 'fp-git-updater'); ?>
                    </button>
                </p>
            </td>
        </tr>
    </tbody>
</table>
