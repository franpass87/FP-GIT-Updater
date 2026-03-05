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
    public const OPTION_DEPLOY_AUTHORIZED_UNTIL = 'fp_git_updater_deploy_authorized_until';
    public const OPTION_DEPLOY_INSTALL = 'fp_git_updater_deploy_install';
    public const OPTION_DEPLOY_UPDATE = 'fp_git_updater_deploy_update';
    public const OPTION_CONNECTED_CLIENTS = 'fp_git_updater_connected_clients';
    public const HEADER_SECRET = 'X-FP-Client-Secret';
    public const HEADER_CLIENT_ID = 'X-FP-Client-ID';

    /** Durata finestra autorizzazione (secondi). I client aggiornano solo se il Master ha autorizzato. */
    public const DEPLOY_WINDOW_SECONDS = 7200; // 2 ore

    /** Client non visti da più di N secondi vengono considerati disconnessi (30 giorni). */
    public const CLIENT_STALE_SECONDS = 2592000;

    /**
     * Registra l'endpoint REST (chiamare da rest_api_init)
     */
    public static function register(): void
    {
        register_rest_route('fp-git-updater/v1', '/master-updates-status', [
            'methods' => 'GET',
            'callback' => [self::class, 'handle_request'],
            'permission_callback' => [self::class, 'permission_check'],
            'args' => [
                'secret' => ['type' => 'string', 'required' => false],
                'client_id' => ['type' => 'string', 'required' => false],
                'installed_plugins' => ['type' => 'string', 'required' => false],
            ],
        ]);
    }

    public static function permission_check(WP_REST_Request $request): bool
    {
        $client_id = $request->get_header(self::HEADER_CLIENT_ID) ?: $request->get_param('client_id');

        if (!get_option(self::OPTION_MASTER_MODE, false)) {
            Logger::log('warning', 'Master: richiesta rifiutata (Modalità Master non attiva)', ['client_id' => $client_id]);
            return false;
        }

        $secret = get_option(self::OPTION_MASTER_CLIENT_SECRET, '');
        if (empty($secret)) {
            Logger::log('warning', 'Master: richiesta rifiutata (chiave segreta non configurata)', ['client_id' => $client_id]);
            return false;
        }

        $provided = $request->get_header(self::HEADER_SECRET);
        if (empty($provided)) {
            $provided = $request->get_param('secret');
        }
        if (empty($provided) || !is_string($provided)) {
            Logger::log('warning', 'Master: richiesta rifiutata (secret non fornito)', ['client_id' => $client_id]);
            return false;
        }

        if (!hash_equals($secret, $provided)) {
            Logger::log('warning', 'Master: richiesta rifiutata (secret non valido)', ['client_id' => $client_id]);
            return false;
        }

        return true;
    }

    public static function handle_request(WP_REST_Request $request): WP_REST_Response
    {
        $client_id = $request->get_header(self::HEADER_CLIENT_ID) ?: $request->get_param('client_id');
        $client_id = is_string($client_id) ? sanitize_text_field($client_id) : '';

        $installed_raw = $request->get_param('installed_plugins');
        $installed_slugs = [];
        if (is_string($installed_raw) && $installed_raw !== '') {
            $installed_slugs = array_filter(array_map('trim', explode(',', $installed_raw)));
        }

        self::register_client_connection($request, $installed_slugs);

        $updater = Updater::get_instance();
        $updater->check_for_updates();
        $pending = $updater->get_pending_updates();
        $settings = get_option('fp_git_updater_settings', []);
        $configured_plugins = isset($settings['plugins']) ? $settings['plugins'] : [];

        $deploy_until = (int) get_option(self::OPTION_DEPLOY_AUTHORIZED_UNTIL, 0);
        $window_active = $deploy_until > time();
        $plugins = [];

        // Se la finestra è scaduta, pulisci le liste deploy per evitare accumulo infinito
        if (!$window_active) {
            $has_install = get_option(self::OPTION_DEPLOY_INSTALL, []);
            $has_update  = get_option(self::OPTION_DEPLOY_UPDATE, []);
            if (!empty($has_install)) {
                update_option(self::OPTION_DEPLOY_INSTALL, []);
            }
            if (!empty($has_update)) {
                update_option(self::OPTION_DEPLOY_UPDATE, []);
            }
        }

        // 1. Deploy INSTALLA/AGGIORNA: plugin inviati a client specifici tramite "Installa sui selezionati"
        // Funziona sia per nuove installazioni che per aggiornamenti di plugin già presenti.
        $deploy_install = get_option(self::OPTION_DEPLOY_INSTALL, []);
        if (is_array($deploy_install) && $window_active && !empty($client_id)) {
            $seen_slugs = [];
            foreach ($deploy_install as $item) {
                $client_ids = $item['client_ids'] ?? [];
                if (!in_array($client_id, $client_ids, true)) {
                    continue;
                }
                $plugin_data = $item['plugin'] ?? [];
                if (empty($plugin_data['github_repo']) && empty($plugin_data['zip_url'] ?? '')) {
                    continue;
                }
                $slug = $plugin_data['slug'] ?? $plugin_data['id'] ?? '';
                // Deduplicazione: stesso plugin non viene inviato due volte
                if (!empty($slug) && in_array($slug, $seen_slugs, true)) {
                    continue;
                }
                if (!empty($slug)) {
                    $seen_slugs[] = $slug;
                }
                $plugins[] = self::normalize_plugin_for_response($plugin_data, null);
            }
        }

        // 2. Deploy AGGIORNA: plugin da aggiornare per client che ce l'hanno già
        $deploy_update = get_option(self::OPTION_DEPLOY_UPDATE, []);
        if (is_array($deploy_update) && $window_active && !empty($client_id)) {
            $client_data = self::get_client_data($client_id);
            $client_installed = $client_data['installed_plugins'] ?? [];
            foreach ($deploy_update as $plugin_id_or_slug) {
                $plugin = self::get_plugin_by_id_or_slug($plugin_id_or_slug, $configured_plugins, $pending);
                if (!$plugin) {
                    continue;
                }
                $slug = $plugin['plugin_slug'] ?? '';
                if (empty($slug) && !empty($plugin['github_repo'])) {
                    $parts = explode('/', $plugin['github_repo']);
                    $slug = strtolower(end($parts));
                }
                if (empty($slug) || !in_array($slug, $client_installed, true)) {
                    continue;
                }
                $p = self::find_pending_for_plugin($plugin_id_or_slug, $pending);
                $plugins[] = self::normalize_plugin_for_response($plugin, $p);
            }
        }

        $updates_for_clients = count($plugins) > 0;

        $response = new WP_REST_Response([
            'updates_available' => $updates_for_clients,
            'pending_count' => count($plugins),
            'plugins' => $plugins,
            'deploy_authorized' => $window_active && $updates_for_clients,
        ], 200);
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->header('Pragma', 'no-cache');
        return $response;
    }

    private static function normalize_plugin_for_response(array $plugin, ?array $pending): array
    {
        $slug = $plugin['plugin_slug'] ?? '';
        if (empty($slug) && !empty($plugin['github_repo'])) {
            $parts = explode('/', $plugin['github_repo']);
            $slug = strtolower(end($parts));
        }
        $item = [
            'id' => $plugin['id'] ?? $slug,
            'name' => $plugin['name'] ?? $slug,
            'slug' => $slug,
            'current_version' => $pending['current_version'] ?? '',
            'available_version' => $pending['available_version'] ?? '',
        ];
        if (!empty($plugin['zip_url'])) {
            $item['zip_url'] = $plugin['zip_url'];
        }
        if (!empty($plugin['github_repo'])) {
            $item['github_repo'] = $plugin['github_repo'];
            $item['branch'] = $plugin['branch'] ?? 'main';
        }
        return $item;
    }

    private static function get_plugin_by_id_or_slug(string $id_or_slug, array $configured, array $pending): ?array
    {
        foreach ($configured as $p) {
            if (($p['id'] ?? '') === $id_or_slug || ($p['plugin_slug'] ?? '') === $id_or_slug) {
                return $p;
            }
        }
        foreach ($pending as $p) {
            $plugin = $p['plugin'] ?? [];
            if (($plugin['id'] ?? '') === $id_or_slug || ($plugin['plugin_slug'] ?? '') === $id_or_slug) {
                return $plugin;
            }
        }
        return null;
    }

    private static function find_pending_for_plugin(string $id_or_slug, array $pending): ?array
    {
        foreach ($pending as $p) {
            $plugin = $p['plugin'] ?? [];
            if (($plugin['id'] ?? '') === $id_or_slug || ($plugin['plugin_slug'] ?? '') === $id_or_slug) {
                return $p;
            }
        }
        return null;
    }

    private static function get_client_data(string $client_id): array
    {
        $clients = get_option(self::OPTION_CONNECTED_CLIENTS, []);
        if (!is_array($clients) || !isset($clients[$client_id])) {
            return [];
        }
        return is_array($clients[$client_id]) ? $clients[$client_id] : [];
    }

    /**
     * Autorizza INSTALLA: invia plugin a client selezionati (che non ce l'hanno).
     *
     * @param array  $plugin    Dati plugin: id, name, slug, github_repo, branch, zip_url?
     * @param array  $client_ids Client ID destinatari
     */
    public static function authorize_deploy_install(array $plugin, array $client_ids): void
    {
        update_option(self::OPTION_DEPLOY_AUTHORIZED_UNTIL, time() + self::DEPLOY_WINDOW_SECONDS);
        $items = get_option(self::OPTION_DEPLOY_INSTALL, []);
        if (!is_array($items)) {
            $items = [];
        }
        $slug = $plugin['slug'] ?? $plugin['id'] ?? '';
        $new_client_ids = array_values(array_map('strval', $client_ids));

        // Se esiste già un item per lo stesso plugin (stesso slug), aggiorna i client_ids
        // invece di aggiungere un duplicato. Questo evita accumulo infinito.
        $found = false;
        foreach ($items as &$item) {
            $item_slug = $item['plugin']['slug'] ?? $item['plugin']['id'] ?? '';
            if (!empty($slug) && $item_slug === $slug) {
                // Unisce i client_ids (nuovi + esistenti) per non perdere client già autorizzati
                $merged = array_values(array_unique(array_merge($item['client_ids'] ?? [], $new_client_ids)));
                $item = ['plugin' => $plugin, 'client_ids' => $merged];
                $found = true;
                break;
            }
        }
        unset($item);

        if (!$found) {
            $items[] = ['plugin' => $plugin, 'client_ids' => $new_client_ids];
        }

        update_option(self::OPTION_DEPLOY_INSTALL, $items);
    }

    /**
     * Autorizza AGGIORNA: aggiorna plugin per tutti i client che ce l'hanno già.
     *
     * @param array $plugin_ids ID o slug dei plugin da aggiornare
     */
    public static function authorize_deploy_update(array $plugin_ids): void
    {
        update_option(self::OPTION_DEPLOY_AUTHORIZED_UNTIL, time() + self::DEPLOY_WINDOW_SECONDS);
        update_option(self::OPTION_DEPLOY_UPDATE, array_values(array_map('strval', $plugin_ids)));
    }

    /**
     * Restituisce i client che hanno installato un determinato plugin (slug).
     *
     * @param string $plugin_slug Slug del plugin (es. fp-forms)
     * @return array<string>
     */
    public static function get_clients_with_plugin(string $plugin_slug): array
    {
        $plugin_slug = strtolower($plugin_slug);
        $clients = self::get_connected_clients();
        $result = [];
        foreach ($clients as $id => $data) {
            $installed = $data['installed_plugins'] ?? [];
            if (in_array($plugin_slug, $installed, true)) {
                $result[] = $id;
            }
        }
        return $result;
    }

    /**
     * Restituisce la versione installata di un plugin su un cliente specifico.
     *
     * @param string $client_id   ID del cliente
     * @param string $plugin_slug Slug del plugin
     * @return string Versione o stringa vuota se non disponibile
     */
    public static function get_client_plugin_version(string $client_id, string $plugin_slug): string
    {
        $clients = self::get_connected_clients();
        if (!isset($clients[$client_id])) {
            return '';
        }
        $versions = $clients[$client_id]['plugin_versions'] ?? [];
        return $versions[$plugin_slug] ?? '';
    }

    /**
     * Restituisce un array client_id => version per i clienti che hanno un plugin installato.
     * Solo i clienti che hanno inviato la versione (formato slug:version).
     *
     * @param string $plugin_slug Slug del plugin
     * @return array<string, string> client_id => version
     */
    public static function get_clients_plugin_versions(string $plugin_slug): array
    {
        $plugin_slug = strtolower($plugin_slug);
        $clients = self::get_connected_clients();
        $result = [];
        foreach ($clients as $id => $data) {
            $installed = $data['installed_plugins'] ?? [];
            if (in_array($plugin_slug, $installed, true)) {
                $version = $data['plugin_versions'][$plugin_slug] ?? '';
                $result[$id] = $version;
            }
        }
        return $result;
    }

    public static function get_endpoint_url(): string
    {
        return rest_url('fp-git-updater/v1/master-updates-status');
    }

    /**
     * Registra la connessione del client per la lista "Client collegati".
     *
     * @param WP_REST_Request $request
     * @param array<string>   $installed_plugins Slug dei plugin installati sul client
     */
    private static function register_client_connection(WP_REST_Request $request, array $installed_plugins = []): void
    {
        $client_id = $request->get_header(self::HEADER_CLIENT_ID);
        if (empty($client_id)) {
            $client_id = $request->get_param('client_id');
        }
        if (empty($client_id) || !is_string($client_id)) {
            return;
        }

        $client_id = sanitize_text_field($client_id);
        if (strlen($client_id) > 255) {
            $client_id = substr($client_id, 0, 255);
        }
        if (empty($client_id)) {
            Logger::log('warning', 'Master: register_client_connection saltato (client_id vuoto)', [
                'has_header' => !empty($request->get_header(self::HEADER_CLIENT_ID)),
                'has_param' => $request->get_param('client_id') !== null,
            ]);
            return;
        }

        $now = time();
        $clients = get_option(self::OPTION_CONNECTED_CLIENTS, []);
        if (!is_array($clients)) {
            $clients = [];
        }

        // Parsare il formato "slug:version" — compatibile con il vecchio formato "slug"
        // Gli slug vengono normalizzati in lowercase per confronti case-insensitive
        $slugs = [];
        $plugin_versions = [];
        foreach ($installed_plugins as $entry) {
            if (strpos($entry, ':') !== false) {
                list($slug, $version) = explode(':', $entry, 2);
                $slug = strtolower(sanitize_text_field(trim($slug)));
                $version = sanitize_text_field(trim($version));
                if (!empty($slug)) {
                    $slugs[] = $slug;
                    $plugin_versions[$slug] = $version;
                }
            } else {
                $slug = strtolower(sanitize_text_field(trim($entry)));
                if (!empty($slug)) {
                    $slugs[] = $slug;
                }
            }
        }

        $clients[$client_id] = [
            'last_seen'         => $now,
            'first_seen'        => $clients[$client_id]['first_seen'] ?? $now,
            'installed_plugins' => array_values(array_unique($slugs)),
            'plugin_versions'   => $plugin_versions,
        ];

        update_option(self::OPTION_CONNECTED_CLIENTS, $clients);
        Logger::log('info', 'Master: client registrato', ['client_id' => $client_id, 'plugins_count' => count($installed_plugins)]);
    }

    /**
     * Restituisce la lista dei client collegati (visti negli ultimi N giorni).
     *
     * @return array<string, array{last_seen: int, first_seen: int}>
     */
    public static function get_connected_clients(): array
    {
        $clients = get_option(self::OPTION_CONNECTED_CLIENTS, []);
        if (!is_array($clients)) {
            return [];
        }

        $cutoff = time() - self::CLIENT_STALE_SECONDS;
        $filtered = [];
        foreach ($clients as $id => $data) {
            if (!is_array($data) || empty($data['last_seen'])) {
                continue;
            }
            if ($data['last_seen'] >= $cutoff) {
                $filtered[$id] = $data;
            }
        }

        uasort($filtered, function ($a, $b) {
            return ($b['last_seen'] ?? 0) <=> ($a['last_seen'] ?? 0);
        });

        return $filtered;
    }
}
