<?php
/**
 * Sistema di Criptazione
 * 
 * Gestisce la criptazione sicura dei token e dati sensibili
 * 
 * @package FP\GitUpdater
 */

namespace FP\GitUpdater;

if (!defined('ABSPATH')) {
    exit;
}

class Encryption {
    
    private static $instance = null;
    
    /**
     * Chiave di criptazione (generata dal salt di WordPress)
     */
    private $key;
    
    /**
     * Metodo di criptazione
     */
    private $cipher_method = 'AES-256-CBC';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->key = $this->get_encryption_key();
    }
    
    /**
     * Ottieni la chiave di criptazione basata sui salt di WordPress
     */
    private function get_encryption_key() {
        // Usa i salt di WordPress per generare una chiave univoca e sicura
        $key_source = AUTH_KEY . SECURE_AUTH_KEY . LOGGED_IN_KEY . NONCE_KEY;
        
        // Genera una chiave a 256 bit usando hash
        return hash('sha256', $key_source, true);
    }
    
    /**
     * Cripta un valore
     * 
     * @param string $value Valore da criptare
     * @return string|false Valore criptato in formato base64, o false in caso di errore
     */
    public function encrypt($value) {
        if (empty($value)) {
            return $value;
        }
        
        try {
            // Genera un IV (Initialization Vector) casuale
            $iv_length = openssl_cipher_iv_length($this->cipher_method);
            $iv = openssl_random_pseudo_bytes($iv_length);
            
            // Cripta il valore
            $encrypted = openssl_encrypt(
                $value,
                $this->cipher_method,
                $this->key,
                OPENSSL_RAW_DATA,
                $iv
            );
            
            if ($encrypted === false) {
                Logger::log('error', 'Errore durante la criptazione');
                return false;
            }
            
            // Combina IV e valore criptato, poi converti in base64
            return base64_encode($iv . $encrypted);
            
        } catch (\Exception $e) {
            Logger::log('error', 'Eccezione durante la criptazione: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Decripta un valore
     * 
     * @param string $encrypted_value Valore criptato in formato base64
     * @return string|false Valore decriptato, o false in caso di errore
     */
    public function decrypt($encrypted_value) {
        if (empty($encrypted_value)) {
            return $encrypted_value;
        }
        
        try {
            // Decodifica da base64
            $decoded = base64_decode($encrypted_value, true);
            
            if ($decoded === false) {
                // Potrebbe essere un token non criptato (retrocompatibilità)
                return $encrypted_value;
            }
            
            // Estrai IV e valore criptato
            $iv_length = openssl_cipher_iv_length($this->cipher_method);
            
            if (strlen($decoded) < $iv_length) {
                // Troppo corto per essere criptato correttamente
                return $encrypted_value;
            }
            
            $iv = substr($decoded, 0, $iv_length);
            $encrypted = substr($decoded, $iv_length);
            
            // Decripta
            $decrypted = openssl_decrypt(
                $encrypted,
                $this->cipher_method,
                $this->key,
                OPENSSL_RAW_DATA,
                $iv
            );
            
            if ($decrypted === false) {
                // Se la decriptazione fallisce, potrebbe essere un token plain text (retrocompatibilità)
                Logger::log('warning', 'Decriptazione fallita, potrebbe essere un token non criptato');
                return $encrypted_value;
            }
            
            return $decrypted;
            
        } catch (\Exception $e) {
            Logger::log('error', 'Eccezione durante la decriptazione: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verifica se un valore è criptato
     * 
     * @param string $value Valore da verificare
     * @return bool True se il valore sembra essere criptato
     */
    public function is_encrypted($value) {
        if (empty($value)) {
            return false;
        }
        
        // Verifica se è un base64 valido
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }
        
        // Verifica se la lunghezza è sufficiente per IV + dati
        $iv_length = openssl_cipher_iv_length($this->cipher_method);
        return strlen($decoded) >= $iv_length;
    }
    
    /**
     * Cripta tutti i token esistenti nel database (per migrazione)
     */
    public function migrate_existing_tokens() {
        $settings = get_option('fp_git_updater_settings');
        
        if (empty($settings) || empty($settings['plugins'])) {
            return false;
        }
        
        $updated = false;
        
        foreach ($settings['plugins'] as &$plugin) {
            if (!empty($plugin['github_token']) && !$this->is_encrypted($plugin['github_token'])) {
                $encrypted = $this->encrypt($plugin['github_token']);
                if ($encrypted !== false) {
                    $plugin['github_token'] = $encrypted;
                    $updated = true;
                    Logger::log('info', 'Token GitHub criptato per: ' . $plugin['name']);
                }
            }
        }
        
        if ($updated) {
            update_option('fp_git_updater_settings', $settings);
            Logger::log('success', 'Migrazione token completata');
            return true;
        }
        
        return false;
    }
    
    /**
     * Cripta il webhook secret se non lo è già
     */
    public function migrate_webhook_secret() {
        $settings = get_option('fp_git_updater_settings');
        
        if (empty($settings) || empty($settings['webhook_secret'])) {
            return false;
        }
        
        if (!$this->is_encrypted($settings['webhook_secret'])) {
            $encrypted = $this->encrypt($settings['webhook_secret']);
            if ($encrypted !== false) {
                $settings['webhook_secret'] = $encrypted;
                update_option('fp_git_updater_settings', $settings);
                Logger::log('info', 'Webhook secret criptato con successo');
                return true;
            }
        }
        
        return false;
    }
}



