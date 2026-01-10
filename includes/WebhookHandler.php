<?php
/**
 * Gestione Webhook GitHub
 * 
 * Riceve e processa i webhook da GitHub quando viene fatto push/merge
 */


namespace FP\GitUpdater;

if (!defined('ABSPATH')) {
    exit;
}

class WebhookHandler {
    
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
            'permission_callback' => array($this, 'verify_webhook_permission'),
        ));
    }
    
    /**
     * Verifica i permessi per il webhook (rate limiting + signature)
     */
    public function verify_webhook_permission($request) {
        // Controlla rate limiting
        $rate_limiter = RateLimiter::get_instance();
        $identifier = $rate_limiter->get_request_identifier();
        
        if (!$rate_limiter->is_allowed($identifier)) {
            Logger::log('warning', 'Richiesta webhook bloccata per rate limiting', array(
                'ip' => $identifier
            ));
            return false;
        }
        
        // La verifica della firma sarà fatta nel callback handle_webhook
        // per avere accesso al body della richiesta
        return true;
    }
    
    /**
     * Gestisce la richiesta webhook da GitHub
     */
    public function handle_webhook($request) {
        try {
            // Log della richiesta
            Logger::log('webhook', __('Webhook ricevuto da GitHub', 'fp-git-updater'));
            
            // Verifica la firma del webhook
            if (!$this->verify_signature($request)) {
                Logger::log('error', __('Webhook: firma non valida', 'fp-git-updater'));
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => __('Firma webhook non valida', 'fp-git-updater')
                ), 401);
            }
            
            // Ottieni i dati del payload
            $payload = $request->get_json_params();
            
            if (empty($payload)) {
                Logger::log('error', __('Webhook: payload vuoto', 'fp-git-updater'));
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => __('Payload vuoto', 'fp-git-updater')
                ), 400);
            }
        
        // Verifica che sia un evento push
        $event = $request->get_header('X-GitHub-Event');
        
        if ($event !== 'push') {
            Logger::log('info', 'Webhook: evento ignorato - ' . $event);
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Evento ignorato: ' . $event
            ), 200);
        }
        
        // Identifica il plugin basandosi sul repository
        $settings = get_option('fp_git_updater_settings');
        $plugins = isset($settings['plugins']) ? $settings['plugins'] : array();
        
        $repository = isset($payload['repository']['full_name']) ? $payload['repository']['full_name'] : '';
        
        if (empty($repository)) {
            Logger::log('error', 'Webhook: repository non identificato nel payload');
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Repository non identificato'
            ), 400);
        }
        
        // Trova il plugin corrispondente al repository
        $matched_plugin = null;
        foreach ($plugins as $plugin) {
            if ($plugin['github_repo'] === $repository && isset($plugin['enabled']) && $plugin['enabled']) {
                $matched_plugin = $plugin;
                break;
            }
        }
        
        if (!$matched_plugin) {
            Logger::log('info', 'Webhook: nessun plugin configurato per il repository ' . $repository);
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Repository non configurato o disabilitato: ' . $repository
            ), 200);
        }
        
        // Verifica il branch
        $target_branch = isset($matched_plugin['branch']) ? $matched_plugin['branch'] : 'main';
        
        $ref = isset($payload['ref']) ? $payload['ref'] : '';
        $branch = str_replace('refs/heads/', '', $ref);
        
        if ($branch !== $target_branch) {
            Logger::log('info', 'Webhook: branch ignorato - ' . $branch . ' per ' . $matched_plugin['name']);
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Branch ignorato: ' . $branch
            ), 200);
        }
        
        // Verifica che head_commit esista (potrebbe non esserci in caso di branch deletion)
        if (!isset($payload['head_commit']) || empty($payload['head_commit'])) {
            Logger::log('info', 'Webhook ignorato: nessun commit (probabilmente branch deletion)');
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Evento ignorato: nessun commit disponibile'
            ), 200);
        }
        
        // Log dei dettagli del push
        $commit_message = isset($payload['head_commit']['message']) ? $payload['head_commit']['message'] : 'N/A';
        $commit_author = isset($payload['head_commit']['author']['name']) ? $payload['head_commit']['author']['name'] : 'N/A';
        $commit_sha = isset($payload['head_commit']['id']) ? $payload['head_commit']['id'] : '';
        
        // Verifica che il commit SHA sia valido
        if (empty($commit_sha) || strlen($commit_sha) < 7) {
            Logger::log('error', 'Webhook: commit SHA mancante o non valido');
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Commit SHA non valido'
            ), 400);
        }
        
        $commit_sha_short = substr($commit_sha, 0, 7);
        
        Logger::log('info', 'Push ricevuto per ' . $matched_plugin['name'] . ' sul branch ' . $branch, array(
            'commit' => $commit_sha_short,
            'author' => $commit_author,
            'message' => $commit_message,
        ));
        
        // Ottieni versioni corrente e disponibile
        $updater = Updater::get_instance();
        
        // Ora i metodi sono pubblici, possiamo chiamarli direttamente
        $current_version = $updater->get_installed_plugin_version($matched_plugin);
        $available_version = $updater->get_github_plugin_version($matched_plugin, $commit_sha);
        
        // Registra l'aggiornamento disponibile
        update_option('fp_git_updater_pending_update_' . $matched_plugin['id'], array(
            'commit_sha' => $commit_sha,
            'commit_sha_short' => $commit_sha_short,
            'commit_message' => $commit_message,
            'commit_author' => $commit_author,
            'branch' => $branch,
            'timestamp' => current_time('mysql'),
            'plugin_name' => $matched_plugin['name'],
            'current_version' => $current_version,
            'available_version' => $available_version,
        ));
            
            // Se l'aggiornamento automatico è abilitato, avvia l'aggiornamento
            if (isset($settings['auto_update']) && $settings['auto_update']) {
                // Schedula l'aggiornamento (eseguilo in background) passando il plugin come parametro
                // Aggiungi 5 secondi di offset per evitare race condition con il cron
                wp_schedule_single_event(time() + 5, 'fp_git_updater_run_update', array($commit_sha, $matched_plugin));
                
                Logger::log('info', 'Aggiornamento automatico schedulato per ' . $matched_plugin['name'] . ' al commit ' . $commit_sha_short);
                
                return new WP_REST_Response(array(
                    'success' => true,
                    'message' => 'Aggiornamento automatico schedulato per ' . $matched_plugin['name'],
                    'commit' => $commit_sha_short,
                    'plugin' => $matched_plugin['name']
                ), 200);
            }
            
            // Aggiornamento manuale: notifica disponibilità
            Logger::log('info', 'Nuovo aggiornamento disponibile per ' . $matched_plugin['name'] . ' (commit: ' . $commit_sha_short . '). Installazione manuale richiesta.');
            
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Aggiornamento disponibile per ' . $matched_plugin['name'] . '. Accedi al pannello admin per installarlo manualmente.',
                'commit' => $commit_sha_short,
                'plugin' => $matched_plugin['name'],
                'mode' => 'manual'
            ), 200);
        } catch (\Exception $e) {
            Logger::log('error', 'Errore nel webhook handler: ' . $e->getMessage());
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
        $encrypted_secret = isset($settings['webhook_secret']) ? $settings['webhook_secret'] : '';
        
        if (empty($encrypted_secret)) {
            // Se non c'è un secret configurato, rifiuta la richiesta per sicurezza
            Logger::log('error', 'Webhook: nessun secret configurato - richiesta rifiutata');
            return false;
        }
        
        // Decripta il secret
        $encryption = Encryption::get_instance();
        $secret = $encryption->decrypt($encrypted_secret);
        
        if ($secret === false) {
            Logger::log('error', 'Webhook: impossibile decriptare il secret');
            return false;
        }
        
        // Supporta varianti header e legacy 'X-Hub-Signature'
        $signature = $request->get_header('X-Hub-Signature-256');
        if (empty($signature)) {
            $signature = $request->get_header('x-hub-signature-256');
        }
        if (empty($signature)) {
            $signature = $request->get_header('X-Hub-Signature');
        }
        if (empty($signature)) {
            $signature = $request->get_header('x-hub-signature');
        }
        
        if (empty($signature)) {
            Logger::log('warning', 'Webhook: firma mancante nell\'header');
            return false;
        }
        
        $body = $request->get_body();
        $expected_signature = 'sha256=' . hash_hmac('sha256', $body, $secret);
        
        $is_valid = hash_equals($expected_signature, $signature);
        
        if (!$is_valid) {
            Logger::log('error', 'Webhook: firma non valida - possibile tentativo di accesso non autorizzato');
        }
        
        return $is_valid;
    }
    
    /**
     * Ottieni l'URL del webhook per questo sito
     */
    public static function get_webhook_url() {
        return rest_url('fp-git-updater/v1/webhook');
    }
}
