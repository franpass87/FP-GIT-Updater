<?php
/**
 * Gestione Webhook GitHub
 * 
 * Riceve e processa i webhook da GitHub quando viene fatto push/merge
 */

if (!defined('ABSPATH')) {
    exit;
}

class FP_Git_Updater_Webhook_Handler {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('rest_api_init', array($this, 'register_webhook_endpoint'));
    }
    
    /**
     * Registra l'endpoint REST API per il webhook
     */
    public function register_webhook_endpoint() {
        register_rest_route('fp-git-updater/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => '__return_true', // La sicurezza è gestita dal secret
        ));
    }
    
    /**
     * Gestisce la richiesta webhook da GitHub
     */
    public function handle_webhook($request) {
        try {
            // Log della richiesta
            FP_Git_Updater_Logger::log('webhook', 'Webhook ricevuto da GitHub');
            
            // Verifica la firma del webhook
            if (!$this->verify_signature($request)) {
                FP_Git_Updater_Logger::log('error', 'Webhook: firma non valida');
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => 'Firma webhook non valida'
                ), 401);
            }
            
            // Ottieni i dati del payload
            $payload = $request->get_json_params();
            
            if (empty($payload)) {
                FP_Git_Updater_Logger::log('error', 'Webhook: payload vuoto');
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => 'Payload vuoto'
                ), 400);
            }
        
        // Verifica che sia un evento push
        $event = $request->get_header('X-GitHub-Event');
        
        if ($event !== 'push') {
            FP_Git_Updater_Logger::log('info', 'Webhook: evento ignorato - ' . $event);
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Evento ignorato: ' . $event
            ), 200);
        }
        
        // Verifica il branch
        $settings = get_option('fp_git_updater_settings');
        $target_branch = isset($settings['branch']) ? $settings['branch'] : 'main';
        
        $ref = isset($payload['ref']) ? $payload['ref'] : '';
        $branch = str_replace('refs/heads/', '', $ref);
        
        if ($branch !== $target_branch) {
            FP_Git_Updater_Logger::log('info', 'Webhook: branch ignorato - ' . $branch);
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Branch ignorato: ' . $branch
            ), 200);
        }
        
        // Log dei dettagli del push
        $commit_message = isset($payload['head_commit']['message']) ? $payload['head_commit']['message'] : 'N/A';
        $commit_author = isset($payload['head_commit']['author']['name']) ? $payload['head_commit']['author']['name'] : 'N/A';
        $commit_sha = isset($payload['head_commit']['id']) ? substr($payload['head_commit']['id'], 0, 7) : 'N/A';
        
        FP_Git_Updater_Logger::log('info', 'Push ricevuto sul branch ' . $branch, array(
            'commit' => $commit_sha,
            'author' => $commit_author,
            'message' => $commit_message,
        ));
        
            // Se l'aggiornamento automatico è abilitato, avvia l'aggiornamento
            if (isset($settings['auto_update']) && $settings['auto_update']) {
                // Schedula l'aggiornamento (eseguilo in background)
                wp_schedule_single_event(time(), 'fp_git_updater_run_update', array($commit_sha));
                
                FP_Git_Updater_Logger::log('info', 'Aggiornamento schedulato per il commit ' . $commit_sha);
                
                return new WP_REST_Response(array(
                    'success' => true,
                    'message' => 'Aggiornamento schedulato',
                    'commit' => $commit_sha
                ), 200);
            }
            
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Webhook ricevuto ma aggiornamento automatico disabilitato'
            ), 200);
        } catch (Exception $e) {
            FP_Git_Updater_Logger::log('error', 'Errore nel webhook handler: ' . $e->getMessage());
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Errore interno del server: ' . $e->getMessage()
            ), 500);
        }
    }
    
    /**
     * Verifica la firma del webhook usando il secret
     */
    private function verify_signature($request) {
        $settings = get_option('fp_git_updater_settings');
        $secret = isset($settings['webhook_secret']) ? $settings['webhook_secret'] : '';
        
        if (empty($secret)) {
            // Se non c'è un secret configurato, accetta tutte le richieste (non sicuro!)
            FP_Git_Updater_Logger::log('warning', 'Webhook: nessun secret configurato');
            return true;
        }
        
        $signature = $request->get_header('X-Hub-Signature-256');
        
        if (empty($signature)) {
            return false;
        }
        
        $body = $request->get_body();
        $expected_signature = 'sha256=' . hash_hmac('sha256', $body, $secret);
        
        return hash_equals($expected_signature, $signature);
    }
    
    /**
     * Ottieni l'URL del webhook per questo sito
     */
    public static function get_webhook_url() {
        return rest_url('fp-git-updater/v1/webhook');
    }
}
