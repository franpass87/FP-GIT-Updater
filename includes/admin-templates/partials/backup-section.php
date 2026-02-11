<?php
/**
 * Template Partial: Sezione Backup
 * 
 * @var array $settings Impostazioni del plugin
 */

if (!defined('ABSPATH')) {
    return;
}

$max_backups = isset($settings['max_backups']) ? intval($settings['max_backups']) : 5;
$max_backup_age_days = isset($settings['max_backup_age_days']) ? intval($settings['max_backup_age_days']) : 7;
?>

<div class="fp-section-header">
    <h2 class="fp-section-title">
        <span class="dashicons dashicons-backup"></span>
        <?php _e('Gestione Backup', 'fp-git-updater'); ?>
    </h2>
    <p class="fp-section-description">
        <?php _e('Configura i limiti per i backup automatici per evitare di saturare lo spazio disco.', 'fp-git-updater'); ?>
    </p>
</div>

<div class="fp-settings-card">
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
                        <span class="dashicons dashicons-info"></span>
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
                        <span class="dashicons dashicons-info"></span>
                        <?php _e('I backup più vecchi di questo numero di giorni verranno eliminati automaticamente. (Consigliato: 7)', 'fp-git-updater'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Statistiche Backup', 'fp-git-updater'); ?></th>
                <td>
                    <div id="fp-backup-stats" class="fp-backup-stats">
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
</div>
