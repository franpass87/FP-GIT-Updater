<?php
/**
 * Sistema di Logging
 * 
 * Gestisce il logging delle operazioni del plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class FP_Git_Updater_Logger {
    
    /**
     * Log un messaggio nel database
     */
    public static function log($type, $message, $details = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_git_updater_logs';
        
        try {
            $wpdb->insert(
                $table_name,
                array(
                    'log_type' => sanitize_text_field($type),
                    'message' => sanitize_text_field($message),
                    'details' => $details ? wp_json_encode($details) : null,
                    'log_date' => current_time('mysql'),
                ),
                array('%s', '%s', '%s', '%s')
            );
            
            // Usa un cron job per la pulizia invece di farlo ad ogni insert
            // Questo migliora le performance
            if (!wp_next_scheduled('fp_git_updater_cleanup_old_logs')) {
                // Schedula per domani alla stessa ora
                wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'fp_git_updater_cleanup_old_logs');
            }
        } catch (Exception $e) {
            // Fallback: logga su error_log se il database fallisce
            error_log('FP Git Updater - Errore logging: ' . $e->getMessage());
        }
    }
    
    /**
     * Ottieni i log dal database
     */
    public static function get_logs($limit = 100, $offset = 0, $type = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_git_updater_logs';
        
        if ($type) {
            $sql = $wpdb->prepare(
                "SELECT * FROM $table_name WHERE log_type = %s ORDER BY log_date DESC LIMIT %d OFFSET %d",
                $type,
                $limit,
                $offset
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY log_date DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            );
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Pulisci i log vecchi (chiamato via cron)
     */
    public static function clear_old_logs($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_git_updater_logs';
        
        try {
            // Elimina log più vecchi di X giorni
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM $table_name WHERE log_date < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            ));
            
            // Mantieni solo gli ultimi 1000 log comunque
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            
            if ($count > 1000) {
                $wpdb->query("DELETE FROM $table_name ORDER BY log_date ASC LIMIT " . ($count - 1000));
            }
            
            // Ottimizza la tabella dopo la pulizia
            $wpdb->query("OPTIMIZE TABLE $table_name");
            
            self::log('info', 'Pulizia log automatica completata', array(
                'logs_eliminati' => $deleted,
                'totale_rimanenti' => $count
            ));
        } catch (Exception $e) {
            error_log('FP Git Updater - Errore pulizia log: ' . $e->getMessage());
        }
    }
    
    /**
     * Pulisci tutti i log
     */
    public static function clear_all_logs() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_git_updater_logs';
        
        $wpdb->query("TRUNCATE TABLE $table_name");
    }
}
