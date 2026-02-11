<?php
/**
 * Endpoint Master per i siti client con FP-Remote-Bridge
 *
 * Il sito con FP Updater in "modalità Master" espone questo endpoint.
 * I client (Bridge) lo interrogano periodicamente per sapere se ci sono aggiornamenti
 * nei plugin FP; se sì, il client esegue localmente check + update.
 *
 * @package FP\GitUpdater
 */

namespace FP\GitUpdater;

use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

class MasterEndpoint
{
    public const OPTION_MASTER_MODE = 'fp_git_updater_master_mode';
    public const OPTION_MASTER_CLIENT_SECRET = 'fp_git_updater_master_client_secret';
    public const HEADER_SECRET = 'X-FP-Client-Secret';

    /**
     * Registra l'endpoint REST (chiamare da rest_api_init)
     */
    public static function register(): void
    {
        register_rest_route('fp-git-updater/v1', '/master-updates-status', [
            'methods' => 'GET',
            'callback' => [self::class, 'handle_request'],
            'permission_callback' => [self::class, 'permission_check'],
        ]);
    }

    public static function permission_check(WP_REST_Request $request): bool
    {
        if (!get_option(self::OPTION_MASTER_MODE, false)) {
            return false;
        }

        $secret = get_option(self::OPTION_MASTER_CLIENT_SECRET, '');
        if (empty($secret)) {
            return false;
        }

        $provided = $request->get_header(self::HEADER_SECRET);
        if (empty($provided)) {
            $provided = $request->get_param('secret');
        }
        if (empty($provided) || !is_string($provided)) {
            return false;
        }

        return hash_equals($secret, $provided);
    }

    public static function handle_request(WP_REST_Request $request): WP_REST_Response
    {
        $updater = Updater::get_instance();
        $updater->check_for_updates();
        $pending = $updater->get_pending_updates();

        $plugins = [];
        foreach ($pending as $p) {
            $plugin = $p['plugin'] ?? [];
            $slug = $plugin['plugin_slug'] ?? '';
            if (empty($slug) && !empty($plugin['github_repo'])) {
                $parts = explode('/', $plugin['github_repo']);
                $slug = strtolower(end($parts));
            }
            $item = [
                'id' => $plugin['id'] ?? '',
                'name' => $plugin['name'] ?? '',
                'slug' => $slug,
                'current_version' => $p['current_version'] ?? '',
                'available_version' => $p['available_version'] ?? '',
            ];
            // Per client senza FP Updater: Bridge installa direttamente da questi dati
            if (!empty($plugin['zip_url'])) {
                $item['zip_url'] = $plugin['zip_url'];
            }
            if (!empty($plugin['github_repo'])) {
                $item['github_repo'] = $plugin['github_repo'];
                $item['branch'] = $plugin['branch'] ?? 'main';
            }
            $plugins[] = $item;
        }

        return new WP_REST_Response([
            'updates_available' => count($pending) > 0,
            'pending_count' => count($pending),
            'plugins' => $plugins,
        ], 200);
    }

    public static function get_endpoint_url(): string
    {
        return rest_url('fp-git-updater/v1/master-updates-status');
    }
}
