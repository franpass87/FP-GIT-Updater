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
    
    /**
     * Text domain del plugin
     */
    const TEXT_DOMAIN = 'fp-git-updater';
    
    /**
     * Genera link di aiuto contestuale
     * 
     * @param string $context Contesto dell'aiuto
     * @return string HTML del link
     */
    public static function help_link($context) {
        $help_texts = array(
            'default_github_username' => __('Username GitHub predefinito. Se inserisci solo il nome del repo (es. mio-plugin), verrà completato automaticamente.', self::TEXT_DOMAIN),
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
