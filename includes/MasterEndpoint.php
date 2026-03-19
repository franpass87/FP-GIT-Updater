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
    /** Client registrati dal Master. */
    public const OPTION_CONNECTED_CLIENTS = 'fp_git_updater_connected_clients';
    /** Mapping vecchio client_id -> client_id corrente (dopo rinomina), così il sito che si riconnette con il vecchio ID aggiorna l'entry con il nome nuovo. */
    public const OPTION_CLIENT_ID_ALIASES = 'fp_git_updater_client_id_aliases';
    /** Client rimossi manualmente dall'admin (non devono riapparire al ping successivo). */
    public const OPTION_REMOVED_CLIENTS = 'fp_git_updater_removed_clients';
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
        $args = [
            'secret'            => ['type' => 'string', 'required' => false],
            'client_id'         => ['type' => 'string', 'required' => false],
            'installed_plugins' => ['type' => 'string', 'required' => false],
        ];
        register_rest_route('fp-git-updater/v1', '/master-updates-status', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'handle_request'],
            'permission_callback' => [self::class, 'permission_check'],
            'args'                => $args,
        ]);
        // POST: accetta installed_plugins nel body (nessun limite di lunghezza URL)
        register_rest_route('fp-git-updater/v1', '/master-updates-status', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'handle_request'],
            'permission_callback' => [self::class, 'permission_check'],
            'args'                => $args,
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
        $client_id = self::get_canonical_client_id_from_request($request);
        if ($client_id !== '') {
            // Usa un ID canonico per matching deploy e lookup dati cliente.
            // Evita mismatch tra formati diversi (es. www.example.com vs example.com).
            $client_id = self::resolve_client_id_alias(self::normalize_client_id($client_id));
        }

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
            // Deduplicazione basata su github_repo (univoco) o zip_url
            // Evita che lo stesso plugin venga inviato due volte per items accumulati
            $seen_sources = [];
            foreach ($deploy_install as $item) {
                $client_ids = $item['client_ids'] ?? [];
                if (!in_array($client_id, $client_ids, true)) {
                    continue;
                }
                $plugin_data = $item['plugin'] ?? [];
                $github_repo = strtolower(trim($plugin_data['github_repo'] ?? ''));
                $zip_url     = trim($plugin_data['zip_url'] ?? '');
                // Almeno una sorgente è richiesta
                if (empty($github_repo) && empty($zip_url)) {
                    continue;
                }
                // Chiave di deduplicazione: repo normalizzato (solo nome, senza owner)
                // es. "franpass87/fp-remote-bridge" e "francescopasseri/fp-remote-bridge"
                // sono lo stesso plugin → usa solo la parte dopo "/"
                $repo_name = !empty($github_repo)
                    ? strtolower(trim(strstr($github_repo, '/') ?: $github_repo, '/'))
                    : $zip_url;
                if (!empty($repo_name) && in_array($repo_name, $seen_sources, true)) {
                    continue; // stesso plugin, salta duplicato
                }
                if (!empty($repo_name)) {
                    $seen_sources[] = $repo_name;
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
        // Priorità: slug > plugin_slug > derivato da github_repo
        $slug = $plugin['slug'] ?? $plugin['plugin_slug'] ?? '';
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

        // Chiama subito trigger-sync sui client target: non aspetta il cron WordPress del client
        self::push_sync_to_clients($new_client_ids);
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

        // Chiama subito trigger-sync sui client che hanno i plugin da aggiornare
        $clients = self::get_connected_clients();
        $target_ids = [];
        foreach ($plugin_ids as $slug) {
            foreach (self::get_clients_with_plugin($slug) as $cid) {
                $target_ids[] = $cid;
            }
        }
        if (!empty($target_ids)) {
            self::push_sync_to_clients(array_values(array_unique($target_ids)));
        }
    }

    /**
     * Chiama POST /wp-json/fp-remote-bridge/v1/trigger-sync su ogni client target.
     * Eseguito in modo non-bloccante (timeout breve): il client esegue il sync
     * in background, il Master non aspetta la risposta.
     *
     * @param array<string> $client_ids Lista di client ID da triggerare
     */
    public static function push_sync_to_clients(array $client_ids): void
    {
        if (empty($client_ids)) {
            return;
        }

        $secret = get_option(self::OPTION_MASTER_CLIENT_SECRET, '');
        if (empty($secret)) {
            return;
        }

        $clients = self::get_connected_clients();

        foreach ($client_ids as $client_id) {
            if (!isset($clients[$client_id])) {
                continue;
            }

            // Ricava l'URL del sito dal client_id (che è il dominio o l'URL)
            $site_url = $clients[$client_id]['url'] ?? '';
            if (empty($site_url)) {
                // Prova a costruire l'URL dal client_id (che di solito è il dominio)
                $host = $client_id;
                if (!preg_match('#^https?://#', $host)) {
                    $host = 'https://' . $host;
                }
                $site_url = $host;
            }

            $endpoint = rtrim($site_url, '/') . '/wp-json/fp-remote-bridge/v1/trigger-sync';

            // Chiamata non-bloccante: timeout 3s, non aspettiamo la risposta
            wp_remote_post($endpoint, [
                'timeout'   => 3,
                'blocking'  => false,
                'headers'   => [
                    'X-FP-Client-Secret' => $secret,
                    'Content-Type'       => 'application/json',
                ],
                'body'      => wp_json_encode(['client_id' => $client_id]),
            ]);
        }
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
        $client_id = self::get_canonical_client_id_from_request($request);
        if (empty($client_id) || !is_string($client_id)) {
            return;
        }

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

        // Non riaggiungere clienti rimossi manualmente dall'admin.
        if (self::is_client_removed($client_id)) {
            Logger::log('info', 'Master: ping ignorato per cliente rimosso manualmente', ['client_id' => $client_id]);
            return;
        }

        // Non riaggiungere clienti che l’admin ha rimosso dalla lista
        // Se il cliente è stato rinominato, aggiorna l'entry con il nome nuovo (così non riappare il vecchio ID)
        $normalized_input = self::normalize_client_id($client_id);
        $resolved_id = self::resolve_client_id_alias($normalized_input);
        if ($resolved_id === $normalized_input) {
            $resolved_id = self::resolve_client_id_alias($client_id);
        }
        if ($resolved_id === $client_id && $normalized_input !== $client_id) {
            $resolved_id = $normalized_input;
        }
        $now = time();
        $earliest_first_seen = null;
        $clients = get_option(self::OPTION_CONNECTED_CLIENTS, []);
        if (!is_array($clients)) {
            $clients = [];
        }
        if ($resolved_id === $normalized_input) {
            $to_merge = [$resolved_id, $client_id];
            foreach (array_keys($clients) as $existing_key) {
                if ($existing_key !== $resolved_id && self::normalize_client_id($existing_key) === $normalized_input) {
                    $to_merge[] = $existing_key;
                }
            }
            foreach ($to_merge as $key) {
                $fs = $clients[$key]['first_seen'] ?? null;
                if ($fs && (!$earliest_first_seen || $fs < $earliest_first_seen)) {
                    $earliest_first_seen = $fs;
                }
            }
            foreach (array_keys($clients) as $existing_key) {
                if ($existing_key !== $resolved_id && self::normalize_client_id($existing_key) === $normalized_input) {
                    unset($clients[$existing_key]);
                }
            }
        }
        $first_seen_fallback = $earliest_first_seen;

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

        // Salva l'URL del sito client per poter chiamare trigger-sync in push
        // Priorità: header X-FP-Site-URL > client_id se sembra un dominio
        $site_url = $request->get_header('X-FP-Site-URL') ?? '';
        if (empty($site_url)) {
            // Se il client_id sembra un dominio, costruisci l'URL
            if (preg_match('#^[a-z0-9]([a-z0-9\-\.]+)?[a-z0-9]\.[a-z]{2,}$#i', $client_id)) {
                $site_url = 'https://' . $client_id;
            }
        }
        $site_url = esc_url_raw($site_url);
        $site_name = sanitize_text_field($request->get_header('X-FP-Site-Name') ?? '');

        $clients[$resolved_id] = [
            'last_seen'         => $now,
            'first_seen'        => $first_seen_fallback ?? $clients[$resolved_id]['first_seen'] ?? $clients[$client_id]['first_seen'] ?? $now,
            'installed_plugins' => array_values(array_unique($slugs)),
            'plugin_versions'   => $plugin_versions,
            'url'               => $site_url ?: ($clients[$resolved_id]['url'] ?? $clients[$client_id]['url'] ?? ''),
            'site_name'         => $site_name ?: ($clients[$resolved_id]['site_name'] ?? $clients[$client_id]['site_name'] ?? ''),
        ];

        update_option(self::OPTION_CONNECTED_CLIENTS, $clients);
        Logger::log('info', 'Master: client registrato', ['client_id' => $resolved_id, 'plugins_count' => count($installed_plugins)]);
    }

    /**
     * Verifica se un client è stato rimosso manualmente dall'admin.
     *
     * @param string $client_id ID inviato dal Bridge
     * @return bool True se il client è in blocco rimozione
     */
    private static function is_client_removed(string $client_id): bool
    {
        $removed = get_option(self::OPTION_REMOVED_CLIENTS, []);
        if (!is_array($removed)) {
            return false;
        }

        $raw = trim($client_id);
        $normalized = self::normalize_client_id($client_id);
        $resolved = self::resolve_client_id_alias($normalized);

        return isset($removed[$raw]) || isset($removed[$normalized]) || isset($removed[$resolved]);
    }

    /**
     * Estrae un client_id canonico dalla request usando header/param e, quando disponibile,
     * l'host dell'URL sito inviato dal Bridge.
     *
     * Se X-FP-Site-URL contiene un host valido, viene preferito perché più stabile
     * rispetto a client_id manuali/non uniformi (es. "oplatium" vs "oplatium.it").
     *
     * @param WP_REST_Request $request Request in ingresso dal Bridge.
     * @return string Client ID canonico o stringa vuota.
     */
    private static function get_canonical_client_id_from_request(WP_REST_Request $request): string
    {
        $client_id = $request->get_header(self::HEADER_CLIENT_ID) ?: $request->get_param('client_id');
        $client_id = is_string($client_id) ? sanitize_text_field($client_id) : '';
        $client_id = trim($client_id);

        $site_url = $request->get_header('X-FP-Site-URL');
        $site_url = is_string($site_url) ? trim($site_url) : '';
        if ($site_url !== '') {
            $site_host = wp_parse_url($site_url, PHP_URL_HOST);
            if (is_string($site_host) && $site_host !== '') {
                $normalized_host = self::normalize_client_id($site_host);
                if ($normalized_host !== '') {
                    return $normalized_host;
                }
            }
        }

        if ($client_id === '') {
            return '';
        }

        return self::normalize_client_id($client_id);
    }

    /**
     * Normalizza un client_id per confronto e lookup alias.
     * Il Bridge può inviare "https://www.example.com", "example.com" ecc.: stessa entità.
     *
     * @param string $client_id ID inviato dal Bridge
     * @return string Forma canonica (host lowercase, senza protocollo, senza www)
     */
    public static function normalize_client_id(string $client_id): string
    {
        $s = trim($client_id);
        $s = preg_replace('#^https?://#i', '', $s);
        $s = preg_replace('#/.*$#', '', $s);
        $s = strtolower(trim($s, '/'));
        $s = preg_replace('#^www\.#i', '', $s);
        return $s === '' ? $client_id : $s;
    }

    /**
     * Risolve un client_id eventualmente rinominato: restituisce l'ID corrente (chiave in connected_clients).
     * Controlla sia l'ID esatto che la forma normalizzata (evita duplicati quando il Bridge invia
     * "https://example.com" vs "example.com").
     *
     * @param string $client_id ID inviato dal Bridge (può essere un vecchio nome dopo rinomina)
     * @return string ID da usare come chiave (nuovo nome se esiste alias, altrimenti $client_id)
     */
    private static function resolve_client_id_alias(string $client_id): string
    {
        $aliases = get_option(self::OPTION_CLIENT_ID_ALIASES, []);
        if (!is_array($aliases)) {
            return $client_id;
        }
        $id = $client_id;
        if (!isset($aliases[$id])) {
            $normalized = self::normalize_client_id($client_id);
            if ($normalized !== $id && isset($aliases[$normalized])) {
                $id = $aliases[$normalized];
            }
        }
        $seen = [];
        while (isset($aliases[$id]) && !isset($seen[$id])) {
            $seen[$id] = true;
            $id = $aliases[$id];
        }
        return is_string($id) ? $id : $client_id;
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
