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
        
        // Mantieni solo gli ultimi 1000 log
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        if ($count > 1000) {
            $wpdb->query("DELETE FROM $table_name ORDER BY log_date ASC LIMIT " . ($count - 1000));
        }
    }
    
    /**
     * Ottieni i log dal database
     */
    public static function get_logs($limit = 100, $offset = 0, $type = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_git_updater_logs';
        
        $sql = "SELECT * FROM $table_name";
        
        if ($type) {
            $sql .= $wpdb->prepare(" WHERE log_type = %s", $type);
        }
        
        $sql .= " ORDER BY log_date DESC LIMIT %d OFFSET %d";
        
        return $wpdb->get_results($wpdb->prepare($sql, $limit, $offset));
    }
    
    /**
     * Pulisci i log vecchi
     */
    public static function clear_old_logs($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_git_updater_logs';
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE log_date < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
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
