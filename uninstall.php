<?php
/**
 * Uninstall FP Git Updater
 * 
 * Pulisce tutte le opzioni e tabelle del database quando il plugin viene disinstallato
 */

// Se l'uninstall non è chiamato da WordPress, esci
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Rimuovi le opzioni principali
$settings = get_option('fp_git_updater_settings');
if ($settings && isset($settings['plugins'])) {
    // Rimuovi le opzioni per ogni plugin
    foreach ($settings['plugins'] as $plugin) {
        if (isset($plugin['id'])) {
            delete_option('fp_git_updater_current_commit_' . $plugin['id']);
            delete_option('fp_git_updater_last_update_' . $plugin['id']);
            delete_option('fp_git_updater_pending_update_' . $plugin['id']);
        }
    }
}

// Rimuovi anche le vecchie opzioni (per retrocompatibilità)
delete_option('fp_git_updater_current_commit');
delete_option('fp_git_updater_last_update');

// Rimuovi le impostazioni e i backup
delete_option('fp_git_updater_settings');
delete_option('fp_git_updater_settings_backup');
delete_option('fp_git_updater_settings_backup_history');
delete_option('fp_git_updater_db_version');

// Rimuovi i cron job schedulati
$timestamp = wp_next_scheduled('fp_git_updater_check_update');
if ($timestamp) {
    wp_unschedule_event($timestamp, 'fp_git_updater_check_update');
}

wp_clear_scheduled_hook('fp_git_updater_run_update');
wp_clear_scheduled_hook('fp_git_updater_cleanup_backup');
wp_clear_scheduled_hook('fp_git_updater_cleanup_old_logs');
wp_clear_scheduled_hook('fp_git_updater_cleanup_temp_files');
wp_clear_scheduled_hook('fp_git_updater_cleanup_old_backups');

// Rimuovi la tabella dei log
global $wpdb;
$table_name = $wpdb->prefix . 'fp_git_updater_logs';
$wpdb->query("DROP TABLE IF EXISTS $table_name");

// Pulisci i backup vecchi (opzionale - commentato di default)
// Togli il commento se vuoi eliminare i backup quando disinstalli il plugin
/*
$backup_dir = WP_CONTENT_DIR . '/upgrade/';
$backups = glob($backup_dir . 'fp-git-updater-backup-*');
foreach ($backups as $backup) {
    if (is_dir($backup)) {
        // Elimina ricorsivamente
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($backup, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($backup);
    }
}
*/
