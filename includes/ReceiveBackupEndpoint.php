<?php
/**
 * Endpoint Master per ricezione backup dai client FP-Remote-Bridge
 *
 * I client inviano i backup via POST multipart. Il file viene salvato
 * in wp-content/fp-backups/{client_id}/.
 *
 * @package FP\GitUpdater
 */

namespace FP\GitUpdater;

use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

class ReceiveBackupEndpoint
{
    public const BACKUP_DIR = 'fp-backups';

    /**
     * Registra l'endpoint REST
     */
    public static function register(): void
    {
        register_rest_route('fp-git-updater/v1', '/receive-backup', [
            'methods' => 'POST',
            'callback' => [self::class, 'handle_request'],
            'permission_callback' => [self::class, 'permission_check'],
        ]);
    }

    /**
     * Stesso schema di MasterEndpoint: modalità Master + X-FP-Client-Secret
     */
    public static function permission_check(WP_REST_Request $request): bool
    {
        if (!get_option(MasterEndpoint::OPTION_MASTER_MODE, false)) {
            return false;
        }

        $secret = get_option(MasterEndpoint::OPTION_MASTER_CLIENT_SECRET, '');
        if (empty($secret)) {
            return false;
        }

        $provided = $request->get_header('X-FP-Client-Secret');
        if (empty($provided)) {
            $provided = $request->get_param('secret');
        }
        if (empty($provided) || !is_string($provided)) {
            return false;
        }

        return hash_equals($secret, $provided);
    }

    /**
     * Gestisce il caricamento del file backup
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function handle_request(WP_REST_Request $request): WP_REST_Response
    {
        $files = $request->get_file_params();
        $file = $files['file'] ?? null;

        if (!$file || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Nessun file ricevuto.', 'fp-git-updater'),
            ], 400);
        }

        if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Errore upload: ', 'fp-git-updater') . $file['error'],
            ], 400);
        }

        $client_id = $request->get_param('client_id');
        if (empty($client_id) || !is_string($client_id)) {
            $client_id = 'client-' . gmdate('Y-m-d');
        }
        $client_id = sanitize_file_name($client_id);
        if (empty($client_id)) {
            $client_id = 'client';
        }

        $base_dir = WP_CONTENT_DIR . '/' . self::BACKUP_DIR . '/' . $client_id;
        if (!is_dir($base_dir)) {
            if (!wp_mkdir_p($base_dir)) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => __('Impossibile creare la cartella backup.', 'fp-git-updater'),
                ], 500);
            }
        }
        // Garantisce protezione .htaccess sia sulla directory padre che su quella del client.
        self::add_htaccess_protection(dirname($base_dir));
        self::add_htaccess_protection($base_dir);

        $filename = gmdate('Y-m-d-His') . '.zip';
        $dest_path = $base_dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Impossibile salvare il file.', 'fp-git-updater'),
            ], 500);
        }

        // Verifica MIME type reale del file appena spostato (non fidarsi del
        // Content-Type del client). Rifiuta tutto ciò che non è uno zip valido.
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = (string) finfo_file($finfo, $dest_path);
                finfo_close($finfo);
                $allowed_mimes = [
                    'application/zip',
                    'application/x-zip',
                    'application/x-zip-compressed',
                    'application/octet-stream', // alcuni client/server non identificano correttamente
                ];
                if ($mime !== '' && !in_array($mime, $allowed_mimes, true)) {
                    @unlink($dest_path);
                    return new WP_REST_Response([
                        'success' => false,
                        'message' => sprintf(__('File rifiutato: tipo non valido (%s). Atteso application/zip.', 'fp-git-updater'), $mime),
                    ], 415);
                }
                // Ulteriore controllo: i file ZIP iniziano con "PK\x03\x04" o "PK\x05\x06"/"PK\x07\x08"
                $fh = @fopen($dest_path, 'rb');
                if ($fh) {
                    $sig = (string) fread($fh, 4);
                    fclose($fh);
                    if (substr($sig, 0, 2) !== 'PK') {
                        @unlink($dest_path);
                        return new WP_REST_Response([
                            'success' => false,
                            'message' => __('File rifiutato: signature ZIP non valida.', 'fp-git-updater'),
                        ], 415);
                    }
                }
            }
        }

        $size = (int) filesize($dest_path);

        return new WP_REST_Response([
            'success' => true,
            'path' => str_replace(ABSPATH, '', $dest_path),
            'size' => $size,
        ], 200);
    }

    /**
     * Aggiunge .htaccess per negare accesso diretto
     */
    private static function add_htaccess_protection(string $dir): void
    {
        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            $content = "# FP Backups - deny direct access\nDeny from all\n";
            if (!file_put_contents($htaccess, $content) && defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Updater] Impossibile creare .htaccess in: ' . $dir);
            }
        }
    }
}
