<?php
/**
 * Rate Limiter per Webhook
 * 
 * Previene abusi limitando il numero di richieste per IP
 * 
 * @package FP\GitUpdater
 */

namespace FP\GitUpdater;

if (!defined('ABSPATH')) {
    exit;
}

class RateLimiter {
    
    private static $instance = null;
    
    /**
     * Numero massimo di richieste consentite
     */
    private $max_requests = 60;
    
    /**
     * Periodo di tempo in secondi
     */
    private $time_window = 3600; // 1 ora
    
    /**
     * Prefix per le transient keys
     */
    private $transient_prefix = 'fp_git_rate_limit_';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Carica impostazioni personalizzate se presenti
        $settings = get_option('fp_git_updater_settings');
        if (isset($settings['rate_limit_max'])) {
            $this->max_requests = intval($settings['rate_limit_max']);
        }
        if (isset($settings['rate_limit_window'])) {
            $this->time_window = intval($settings['rate_limit_window']);
        }
    }
    
    /**
     * Verifica se una richiesta è consentita
     * 
     * @param string $identifier Identificatore unico (es: IP address)
     * @return bool True se la richiesta è consentita, false altrimenti
     */
    public function is_allowed($identifier) {
        $key = $this->transient_prefix . md5($identifier);
        $timestamp_key = $key . '_timestamp';
        
        // Ottieni il contatore corrente
        $counter = get_transient($key);
        $timestamp = get_transient($timestamp_key);
        
        if ($counter === false) {
            // Prima richiesta in questo time window
            set_transient($key, 1, $this->time_window);
            set_transient($timestamp_key, time(), $this->time_window);
            return true;
        }
        
        // Converte a intero
        $counter = intval($counter);
        
        if ($counter >= $this->max_requests) {
            // Limite raggiunto
            Logger::log('warning', 'Rate limit raggiunto per: ' . $identifier, array(
                'requests' => $counter,
                'limit' => $this->max_requests,
                'window' => $this->time_window
            ));
            return false;
        }
        
        // Calcola il tempo rimanente per preservare il timeout originale
        if ($timestamp !== false) {
            $elapsed = time() - intval($timestamp);
            $remaining = max(1, $this->time_window - $elapsed);
        } else {
            $remaining = $this->time_window;
        }
        
        // Incrementa il contatore preservando il timeout originale
        set_transient($key, $counter + 1, $remaining);
        if ($timestamp === false) {
            set_transient($timestamp_key, time(), $remaining);
        }
        return true;
    }
    
    /**
     * Ottieni il numero di richieste rimanenti
     * 
     * @param string $identifier Identificatore unico
     * @return int Numero di richieste rimanenti
     */
    public function get_remaining_requests($identifier) {
        $key = $this->transient_prefix . md5($identifier);
        $counter = get_transient($key);
        
        if ($counter === false) {
            return $this->max_requests;
        }
        
        return max(0, $this->max_requests - intval($counter));
    }
    
    /**
     * Ottieni il tempo rimanente prima del reset
     * 
     * @param string $identifier Identificatore unico
     * @return int Secondi rimanenti, 0 se non c'è limite attivo
     */
    public function get_time_until_reset($identifier) {
        $key = $this->transient_prefix . md5($identifier);
        
        // WordPress non espone direttamente il TTL delle transient
        // Usiamo un workaround con timestamp
        $timestamp_key = $key . '_timestamp';
        $timestamp = get_transient($timestamp_key);
        
        if ($timestamp === false) {
            // Imposta timestamp alla prima richiesta
            set_transient($timestamp_key, time(), $this->time_window);
            return $this->time_window;
        }
        
        $elapsed = time() - intval($timestamp);
        return max(0, $this->time_window - $elapsed);
    }
    
    /**
     * Reset manuale del contatore per un identificatore
     * 
     * @param string $identifier Identificatore unico
     * @return bool True se il reset ha successo
     */
    public function reset($identifier) {
        $key = $this->transient_prefix . md5($identifier);
        $timestamp_key = $key . '_timestamp';
        
        delete_transient($key);
        delete_transient($timestamp_key);
        
        Logger::log('info', 'Rate limit resettato per: ' . $identifier);
        return true;
    }
    
    /**
     * Ottieni l'identificatore dalla richiesta corrente
     * 
     * @return string IP address o identificatore alternativo
     */
    public function get_request_identifier() {
        // Prova a ottenere l'IP reale anche dietro proxy/CDN
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            // Cloudflare
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Potrebbe essere una lista, prendi il primo
            $ip_list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ip_list[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        // Sanitizza l'IP
        $ip = filter_var($ip, FILTER_VALIDATE_IP);
        
        if (!$ip) {
            // Fallback: usa un hash della user agent + altri header
            $ip = md5(
                $_SERVER['HTTP_USER_AGENT'] ?? '' .
                $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''
            );
        }
        
        return $ip;
    }
    
    /**
     * Blocca una richiesta e ritorna una risposta di errore
     * 
     * @param string $identifier Identificatore che ha violato il limite
     * @return WP_REST_Response Risposta di errore 429
     */
    public function block_request($identifier) {
        $remaining_time = $this->get_time_until_reset($identifier);
        
        return new \WP_REST_Response(array(
            'success' => false,
            'message' => 'Rate limit exceeded. Too many requests.',
            'retry_after' => $remaining_time
        ), 429);
    }
}



