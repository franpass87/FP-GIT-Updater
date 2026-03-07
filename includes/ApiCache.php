<?php
/**
 * Sistema di Caching per API GitHub
 * 
 * Riduce le chiamate API e migliora le performance
 * 
 * @package FP\GitUpdater
 */

namespace FP\GitUpdater;

if (!defined('ABSPATH')) {
    exit;
}

class ApiCache {
    
    private static $instance = null;
    
    /**
     * Durata della cache in secondi
     */
    private $cache_duration = 300; // 5 minuti di default
    
    /**
     * Prefix per le transient keys
     */
    private $cache_prefix = 'fp_git_api_cache_';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Carica durata cache personalizzata se presente
        $settings = get_option('fp_git_updater_settings');
        if (isset($settings['api_cache_duration'])) {
            $this->cache_duration = intval($settings['api_cache_duration']);
        }
    }
    
    /**
     * Ottieni un valore dalla cache
     * 
     * @param string $key Chiave della cache
     * @return mixed|false Valore in cache o false se non presente/scaduto
     */
    public function get($key) {
        $cache_key = $this->cache_prefix . md5($key);
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            // Log solo in debug mode per evitare spam nei log
            if (defined('WP_DEBUG') && WP_DEBUG) {
                Logger::log('info', 'Cache hit per: ' . $key);
            }
            return $cached_data;
        }
        
        return false;
    }
    
    /**
     * Salva un valore nella cache
     * 
     * @param string $key Chiave della cache
     * @param mixed $value Valore da salvare
     * @param int|null $expiration Durata personalizzata in secondi (null = usa default)
     * @return bool True se salvato con successo
     */
    public function set($key, $value, $expiration = null) {
        $cache_key = $this->cache_prefix . md5($key);
        $duration = $expiration !== null ? $expiration : $this->cache_duration;
        
        $result = set_transient($cache_key, $value, $duration);
        
        // Log solo in debug mode per evitare spam nei log
        if ($result && defined('WP_DEBUG') && WP_DEBUG) {
            Logger::log('info', 'Cache salvata per: ' . $key . ' (durata: ' . $duration . 's)');
        }
        
        return $result;
    }
    
    /**
     * Genera una chiave di cache per una chiamata API GitHub
     * 
     * @param string $endpoint Endpoint API
     * @param array $params Parametri della richiesta
     * @return string Chiave di cache univoca
     */
    public function generate_api_key($endpoint, $params = array()) {
        // Usa wp_json_encode invece di serialize per maggiore sicurezza
        return 'github_api_' . $endpoint . '_' . md5(wp_json_encode($params));
    }
    
    /**
     * Wrapper per chiamate API GitHub con caching automatico
     * 
     * @param string $url URL completo dell'API
     * @param array $args Argomenti per wp_remote_get
     * @param int|null $cache_duration Durata cache personalizzata
     * @return array|WP_Error Risposta API o errore
     */
    /**
     * Elimina una voce dalla cache
     */
    public function delete($key) {
        $cache_key = $this->cache_prefix . md5($key);
        delete_transient($cache_key);
    }

    /**
     * Elimina la cache per una chiamata API specifica (stesso URL+args usati in cached_api_call)
     */
    public function delete_api_call($url, $args = array()) {
        $cache_key = $this->generate_api_key($url, $args);
        $this->delete($cache_key);
    }

    public function cached_api_call($url, $args = array(), $cache_duration = null) {
        // Genera chiave di cache
        $cache_key = $this->generate_api_key($url, $args);
        
        // Controlla se c'è in cache
        $cached = $this->get($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        // Esegui la chiamata API
        $response = wp_remote_get($url, $args);
        
        // Se la risposta è valida, salvala in cache
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $this->set($cache_key, $response, $cache_duration);
        }
        
        return $response;
    }
}



