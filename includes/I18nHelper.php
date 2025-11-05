<?php
/**
 * Helper per Internazionalizzazione
 * 
 * Fornisce funzioni helper per traduzioni e formattazione
 * 
 * @package FP\GitUpdater
 */

namespace FP\GitUpdater;

if (!defined('ABSPATH')) {
    exit;
}

class I18nHelper {
    
    private static $instance = null;
    
    /**
     * Text domain del plugin
     */
    const TEXT_DOMAIN = 'fp-git-updater';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Traduci e formatta una stringa con placeholder
     * 
     * @param string $text Testo da tradurre
     * @param array $args Argomenti per sprintf
     * @return string Testo tradotto e formattato
     */
    public static function translate($text, $args = array()) {
        $translated = __($text, self::TEXT_DOMAIN);
        
        if (!empty($args)) {
            return vsprintf($translated, $args);
        }
        
        return $translated;
    }
    
    /**
     * Traduci e echo una stringa
     * 
     * @param string $text Testo da tradurre
     * @param array $args Argomenti per sprintf
     */
    public static function echo_translate($text, $args = array()) {
        echo self::translate($text, $args);
    }
    
    /**
     * Traduci una stringa al plurale
     * 
     * @param string $single Forma singolare
     * @param string $plural Forma plurale
     * @param int $number Numero per determinare singolare/plurale
     * @return string Testo tradotto
     */
    public static function translate_plural($single, $plural, $number) {
        return _n($single, $plural, $number, self::TEXT_DOMAIN);
    }
    
    /**
     * Formatta una data per la visualizzazione
     * 
     * @param string $date Data in formato MySQL
     * @param bool $time_ago Se true, mostra "X tempo fa"
     * @return string Data formattata
     */
    public static function format_date($date, $time_ago = false) {
        if (empty($date)) {
            return __('Mai', self::TEXT_DOMAIN);
        }
        
        if ($time_ago) {
            return sprintf(
                /* translators: %s: human readable time difference */
                __('%s fa', self::TEXT_DOMAIN),
                human_time_diff(strtotime($date), current_time('timestamp'))
            );
        }
        
        return date_i18n(
            get_option('date_format') . ' ' . get_option('time_format'),
            strtotime($date)
        );
    }
    
    /**
     * Ottieni messaggi standard tradotti
     * 
     * @param string $key Chiave del messaggio
     * @return string Messaggio tradotto
     */
    public static function get_message($key) {
        $messages = array(
            // Successo
            'update_success' => __('Aggiornamento completato con successo!', self::TEXT_DOMAIN),
            'backup_created' => __('Backup creato con successo', self::TEXT_DOMAIN),
            'settings_saved' => __('Impostazioni salvate con successo', self::TEXT_DOMAIN),
            
            // Errori
            'update_failed' => __('Errore durante l\'aggiornamento', self::TEXT_DOMAIN),
            'backup_failed' => __('Impossibile creare il backup', self::TEXT_DOMAIN),
            'connection_failed' => __('Impossibile connettersi a GitHub', self::TEXT_DOMAIN),
            'permission_denied' => __('Permessi insufficienti', self::TEXT_DOMAIN),
            
            // Avvisi
            'no_updates' => __('Nessun aggiornamento disponibile', self::TEXT_DOMAIN),
            'auto_update_enabled' => __('Aggiornamento automatico attivo', self::TEXT_DOMAIN),
            'manual_update_required' => __('Aggiornamento manuale richiesto', self::TEXT_DOMAIN),
            
            // Info
            'checking_updates' => __('Controllo aggiornamenti in corso...', self::TEXT_DOMAIN),
            'installing_update' => __('Installazione aggiornamento in corso...', self::TEXT_DOMAIN),
            'creating_backup' => __('Creazione backup in corso...', self::TEXT_DOMAIN),
        );
        
        return isset($messages[$key]) ? $messages[$key] : $key;
    }
    
    /**
     * Ottieni etichette per i tipi di log
     * 
     * @param string $type Tipo di log
     * @return string Etichetta tradotta
     */
    public static function get_log_type_label($type) {
        $labels = array(
            'info' => __('Info', self::TEXT_DOMAIN),
            'success' => __('Successo', self::TEXT_DOMAIN),
            'warning' => __('Avviso', self::TEXT_DOMAIN),
            'error' => __('Errore', self::TEXT_DOMAIN),
            'webhook' => __('Webhook', self::TEXT_DOMAIN),
        );
        
        return isset($labels[$type]) ? $labels[$type] : ucfirst($type);
    }
    
    /**
     * Formatta una dimensione in byte in formato leggibile
     * 
     * @param int $bytes Dimensione in byte
     * @return string Dimensione formattata
     */
    public static function format_bytes($bytes) {
        $units = array(
            __('B', self::TEXT_DOMAIN),
            __('KB', self::TEXT_DOMAIN),
            __('MB', self::TEXT_DOMAIN),
            __('GB', self::TEXT_DOMAIN),
        );
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Ottieni testo per stati plugin
     * 
     * @param string $status Stato del plugin
     * @return string Testo stato tradotto
     */
    public static function get_status_text($status) {
        $statuses = array(
            'enabled' => __('Abilitato', self::TEXT_DOMAIN),
            'disabled' => __('Disabilitato', self::TEXT_DOMAIN),
            'updating' => __('In aggiornamento', self::TEXT_DOMAIN),
            'error' => __('Errore', self::TEXT_DOMAIN),
        );
        
        return isset($statuses[$status]) ? $statuses[$status] : $status;
    }
    
    /**
     * Genera link di aiuto contestuale
     * 
     * @param string $context Contesto dell'aiuto
     * @return string HTML del link
     */
    public static function help_link($context) {
        $help_texts = array(
            'webhook_url' => __('Usa questo URL quando configuri il webhook su GitHub', self::TEXT_DOMAIN),
            'webhook_secret' => __('Copia questo secret nelle impostazioni del webhook su GitHub', self::TEXT_DOMAIN),
            'github_token' => __('Token necessario solo per repository privati', self::TEXT_DOMAIN),
            'auto_update' => __('Se abilitato, gli aggiornamenti vengono installati automaticamente', self::TEXT_DOMAIN),
        );
        
        if (!isset($help_texts[$context])) {
            return '';
        }
        
        return sprintf(
            '<span class="dashicons dashicons-editor-help" title="%s" style="cursor: help; color: #999;"></span>',
            esc_attr($help_texts[$context])
        );
    }
    
    /**
     * Ottieni testo per intervalli di tempo
     * 
     * @param string $interval Intervallo (hourly, twicedaily, daily)
     * @return string Testo tradotto
     */
    public static function get_interval_label($interval) {
        $intervals = array(
            'hourly' => __('Ogni ora', self::TEXT_DOMAIN),
            'twicedaily' => __('Due volte al giorno', self::TEXT_DOMAIN),
            'daily' => __('Una volta al giorno', self::TEXT_DOMAIN),
            'weekly' => __('Una volta a settimana', self::TEXT_DOMAIN),
        );
        
        return isset($intervals[$interval]) ? $intervals[$interval] : $interval;
    }
}



