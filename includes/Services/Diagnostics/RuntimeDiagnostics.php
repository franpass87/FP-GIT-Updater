<?php
/**
 * Runtime diagnostics FP Git Updater (read-only, Bridge-safe).
 *
 * @package FP\GitUpdater\Services\Diagnostics
 */

declare(strict_types=1);

namespace FP\GitUpdater\Services\Diagnostics;

use FP\GitUpdater\Logger;
use FP\GitUpdater\MasterEndpoint;
use FP\GitUpdater\ReceiveBackupEndpoint;
use FP\GitUpdater\WebhookHandler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Snapshot operativo per FP Remote Bridge (`gitupdater_runtime`).
 */
final class RuntimeDiagnostics
{
    public const SECTION_UPDATER_CONTEXT = 'updater_context';
    public const SECTION_CONFIGURED_PLUGINS = 'configured_plugins';
    public const SECTION_PENDING_UPDATES = 'pending_updates';
    public const SECTION_DEPLOY_PIPELINE = 'deploy_pipeline';
    public const SECTION_CONNECTED_CLIENTS = 'connected_clients';
    public const SECTION_LOGS_RECENT = 'logs_recent';
    public const SECTION_CRON_INTEGRATIONS = 'cron_integrations';
    public const SECTION_PROBLEMS = 'problems';

    public const ALL_SECTIONS = [
        self::SECTION_UPDATER_CONTEXT,
        self::SECTION_CONFIGURED_PLUGINS,
        self::SECTION_PENDING_UPDATES,
        self::SECTION_DEPLOY_PIPELINE,
        self::SECTION_CONNECTED_CLIENTS,
        self::SECTION_LOGS_RECENT,
        self::SECTION_CRON_INTEGRATIONS,
        self::SECTION_PROBLEMS,
    ];

    private const MAX_CLIENTS_SAMPLE = 50;
    private const LOG_TAIL_DEFAULT = 40;

    /**
     * @param array<int, string>   $sections
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function build(array $sections = [], array $options = []): array
    {
        $requested = $sections === [] ? self::ALL_SECTIONS : array_values(array_intersect($sections, self::ALL_SECTIONS));
        if ($requested === []) {
            $requested = self::ALL_SECTIONS;
        }

        $logLimit = isset($options['log_tail_limit']) ? max(5, min(100, (int) $options['log_tail_limit'])) : self::LOG_TAIL_DEFAULT;
        $clientLimit = isset($options['clients_sample_limit']) ? max(1, min(self::MAX_CLIENTS_SAMPLE, (int) $options['clients_sample_limit'])) : self::MAX_CLIENTS_SAMPLE;

        $isMaster = self::is_master_mode();

        $payload = [
            'plugin_active' => true,
            'plugin_version' => defined('FP_GIT_UPDATER_VERSION') ? (string) FP_GIT_UPDATER_VERSION : '',
            'available_sections' => self::ALL_SECTIONS,
            'requested_sections' => $requested,
            'generated_at_gmt' => gmdate('Y-m-d H:i:s'),
            'master_mode' => $isMaster,
            'logs_table_exists' => self::logs_table_exists(),
        ];

        foreach ($requested as $section) {
            switch ($section) {
                case self::SECTION_UPDATER_CONTEXT:
                    $payload['updater_context'] = self::build_updater_context($isMaster);
                    break;
                case self::SECTION_CONFIGURED_PLUGINS:
                    $payload['configured_plugins'] = self::build_configured_plugins();
                    break;
                case self::SECTION_PENDING_UPDATES:
                    $payload['pending_updates'] = self::build_pending_updates();
                    break;
                case self::SECTION_DEPLOY_PIPELINE:
                    $payload['deploy_pipeline'] = $isMaster
                        ? self::build_deploy_pipeline()
                        : ['skipped' => true, 'reason' => 'not_master_mode'];
                    break;
                case self::SECTION_CONNECTED_CLIENTS:
                    $payload['connected_clients'] = $isMaster
                        ? self::build_connected_clients($clientLimit)
                        : ['skipped' => true, 'reason' => 'not_master_mode'];
                    break;
                case self::SECTION_LOGS_RECENT:
                    $payload['logs_recent'] = self::build_logs_recent($logLimit);
                    break;
                case self::SECTION_CRON_INTEGRATIONS:
                    $payload['cron_integrations'] = self::build_cron_integrations($isMaster);
                    break;
                case self::SECTION_PROBLEMS:
                    $payload['problems'] = self::collect_problems($payload, $isMaster);
                    break;
            }
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private static function build_updater_context(bool $isMaster): array
    {
        $settings = get_option('fp_git_updater_settings', []);
        $settings = is_array($settings) ? $settings : [];

        $token = $settings['global_github_token'] ?? '';
        $webhookSecret = $settings['webhook_secret'] ?? '';

        $bridgeConfigured = defined('FP_REMOTE_BRIDGE_VERSION')
            || (get_option('fp_remote_bridge_master_url', '') !== ''
                && get_option('fp_remote_bridge_master_secret', '') !== '');

        return [
            'master_mode' => $isMaster,
            'master_client_secret_is_set' => trim((string) get_option(MasterEndpoint::OPTION_MASTER_CLIENT_SECRET, '')) !== '',
            'role' => $isMaster ? 'master' : ($bridgeConfigured ? 'client_with_bridge' : 'standalone'),
            'global_github_token_is_set' => is_string($token) && trim($token) !== '',
            'webhook_secret_is_set' => is_string($webhookSecret) && trim($webhookSecret) !== '',
            'webhook_rest_path' => '/wp-json/fp-git-updater/v1/webhook',
            'master_updates_status_path' => '/wp-json/fp-git-updater/v1/master-updates-status',
            'cursor_mcp_sites_path' => '/wp-json/fp-git-updater/v1/cursor-mcp-sites',
            'bridge_plugin_detected' => defined('FP_REMOTE_BRIDGE_VERSION'),
            'fp_remote_bridge_version' => defined('FP_REMOTE_BRIDGE_VERSION') ? (string) FP_REMOTE_BRIDGE_VERSION : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function build_configured_plugins(): array
    {
        $settings = get_option('fp_git_updater_settings', []);
        $plugins = is_array($settings['plugins'] ?? null) ? $settings['plugins'] : [];

        $enabled = 0;
        $disabled = 0;
        $gaps = 0;
        $sample = [];

        foreach ($plugins as $plugin) {
            if (!is_array($plugin)) {
                continue;
            }
            if (!empty($plugin['enabled'])) {
                ++$enabled;
            } else {
                ++$disabled;
            }

            $repo = trim((string) ($plugin['github_repo'] ?? ''));
            $slug = trim((string) ($plugin['plugin_slug'] ?? ''));
            if ($repo === '' || $slug === '') {
                ++$gaps;
            }

            if (count($sample) < 30) {
                $sample[] = [
                    'id' => sanitize_key((string) ($plugin['id'] ?? '')),
                    'name' => sanitize_text_field((string) ($plugin['name'] ?? '')),
                    'plugin_slug' => sanitize_key($slug),
                    'branch' => sanitize_text_field((string) ($plugin['branch'] ?? 'main')),
                    'enabled' => !empty($plugin['enabled']),
                    'has_github_repo' => $repo !== '',
                ];
            }
        }

        return [
            'total' => count($plugins),
            'enabled' => $enabled,
            'disabled' => $disabled,
            'config_gaps' => $gaps,
            'plugins_sample' => $sample,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function build_pending_updates(): array
    {
        // Read-only: non usare Updater::get_pending_updates() (può cancellare pending obsoleti).
        $settings = get_option('fp_git_updater_settings', []);
        $plugins = is_array($settings['plugins'] ?? null) ? $settings['plugins'] : [];
        $items = [];

        foreach ($plugins as $plugin) {
            if (!is_array($plugin)) {
                continue;
            }
            $pluginId = sanitize_key((string) ($plugin['id'] ?? ''));
            if ($pluginId === '') {
                continue;
            }
            $pending = get_option('fp_git_updater_pending_update_' . $pluginId, null);
            if (!is_array($pending) || $pending === []) {
                continue;
            }

            $lockKey = 'fp_git_updater_lock_' . $pluginId;
            $items[] = [
                'plugin_id' => $pluginId,
                'plugin_slug' => sanitize_key((string) ($plugin['plugin_slug'] ?? '')),
                'available_version' => sanitize_text_field((string) ($pending['available_version'] ?? '')),
                'commit_sha_short' => self::short_sha((string) ($pending['commit_sha'] ?? '')),
                'branch' => sanitize_text_field((string) ($pending['branch'] ?? ($plugin['branch'] ?? ''))),
                'has_update_lock' => (bool) get_transient($lockKey),
                'detected_at' => isset($pending['detected_at']) ? (string) $pending['detected_at'] : null,
            ];
        }

        return [
            'count' => count($items),
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function build_deploy_pipeline(): array
    {
        $until = (int) get_option(MasterEndpoint::OPTION_DEPLOY_AUTHORIZED_UNTIL, 0);
        $install = get_option(MasterEndpoint::OPTION_DEPLOY_INSTALL, []);
        $update = get_option(MasterEndpoint::OPTION_DEPLOY_UPDATE, []);
        $install = is_array($install) ? $install : [];
        $update = is_array($update) ? $update : [];

        return [
            'deploy_window_active' => $until > time(),
            'deploy_authorized_until_gmt' => $until > 0 ? gmdate('Y-m-d H:i:s', $until) : null,
            'deploy_install_queue_count' => count($install),
            'deploy_update_queue_count' => count($update),
            'deploy_install_plugin_ids_sample' => array_slice(array_values(array_map('strval', $install)), 0, 20),
            'deploy_update_plugin_ids_sample' => array_slice(array_values(array_map('strval', $update)), 0, 20),
            'removed_clients_count' => count((array) get_option(MasterEndpoint::OPTION_REMOVED_CLIENTS, [])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function build_connected_clients(int $limit): array
    {
        $clients = MasterEndpoint::get_connected_clients();
        $allRaw = get_option(MasterEndpoint::OPTION_CONNECTED_CLIENTS, []);
        $allRaw = is_array($allRaw) ? $allRaw : [];
        $totalRegistered = count($allRaw);

        $sample = [];
        $withBridge = 0;
        $staleNotInFiltered = max(0, $totalRegistered - count($clients));

        foreach ($clients as $clientId => $data) {
            if (!is_array($data)) {
                continue;
            }
            if (count($sample) >= $limit) {
                break;
            }

            $installed = is_array($data['installed_plugins'] ?? null) ? $data['installed_plugins'] : [];
            $hasBridge = in_array('fp-remote-bridge', $installed, true)
                || in_array('FP-Remote-Bridge', $installed, true);
            if ($hasBridge) {
                ++$withBridge;
            }

            $lastSeen = (int) ($data['last_seen'] ?? 0);
            $ageHours = $lastSeen > 0 ? (int) round((time() - $lastSeen) / 3600) : null;

            $sample[] = [
                'client_id' => sanitize_key((string) $clientId),
                'site_name' => sanitize_text_field((string) ($data['site_name'] ?? '')),
                'site_url_host' => self::url_host((string) ($data['url'] ?? '')),
                'last_seen_gmt' => $lastSeen > 0 ? gmdate('Y-m-d H:i:s', $lastSeen) : null,
                'last_seen_age_hours' => $ageHours,
                'installed_plugins_count' => count($installed),
                'has_fp_remote_bridge' => $hasBridge,
                'plugin_versions_count' => is_array($data['plugin_versions'] ?? null) ? count($data['plugin_versions']) : 0,
            ];
        }

        return [
            'active_clients_count' => count($clients),
            'registered_total_count' => $totalRegistered,
            'filtered_out_stale_count' => $staleNotInFiltered,
            'clients_with_bridge_count' => $withBridge,
            'stale_threshold_days' => (int) (MasterEndpoint::CLIENT_STALE_SECONDS / DAY_IN_SECONDS),
            'sample' => $sample,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function build_logs_recent(int $limit): array
    {
        if (!self::logs_table_exists()) {
            return ['table_exists' => false, 'entries' => []];
        }

        $rows = Logger::get_logs($limit, 0, null);
        $entries = [];

        foreach ($rows as $row) {
            if (!is_object($row)) {
                continue;
            }
            $details = null;
            if (!empty($row->details)) {
                $decoded = json_decode((string) $row->details, true);
                $details = is_array($decoded) ? self::scrub_details($decoded) : null;
            }

            $entries[] = [
                'log_type' => sanitize_key((string) ($row->log_type ?? '')),
                'message' => sanitize_text_field((string) ($row->message ?? '')),
                'log_date' => (string) ($row->log_date ?? ''),
                'details' => $details,
            ];
        }

        $webhookErrors24h = 0;
        global $wpdb;
        $table = $wpdb->prefix . 'fp_git_updater_logs';
        $webhookErrors24h = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE log_type IN ('error','warning') AND log_date >= %s",
                gmdate('Y-m-d H:i:s', strtotime('-24 hours'))
            )
        );

        return [
            'table_exists' => true,
            'entries' => $entries,
            'errors_warnings_last_24h' => $webhookErrors24h,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function build_cron_integrations(bool $isMaster): array
    {
        $webhookUrl = WebhookHandler::get_webhook_url();

        $backupDir = WP_CONTENT_DIR . '/' . ReceiveBackupEndpoint::BACKUP_DIR;

        return [
            'cron_cleanup_logs_scheduled' => wp_next_scheduled('fp_git_updater_cleanup_old_logs') !== false,
            'cron_cleanup_backups_scheduled' => wp_next_scheduled('fp_git_updater_cleanup_old_backups') !== false,
            'webhook_url_path' => is_string($webhookUrl) ? (string) wp_parse_url($webhookUrl, PHP_URL_PATH) : null,
            'master_backup_dir_writable' => $isMaster && is_dir($backupDir) ? is_writable($backupDir) : null,
            'db_version' => (string) get_option('fp_git_updater_db_version', ''),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, string>>
     */
    private static function collect_problems(array $payload, bool $isMaster): array
    {
        $problems = [];

        $ctx = is_array($payload['updater_context'] ?? null) ? $payload['updater_context'] : [];
        if ($isMaster && empty($ctx['master_client_secret_is_set'])) {
            $problems[] = [
                'code' => 'master_secret_missing',
                'severity' => 'high',
                'message' => 'Modalità Master attiva ma fp_git_updater_master_client_secret non configurato.',
            ];
        }

        if (!$isMaster && empty($ctx['global_github_token_is_set'])) {
            $problems[] = [
                'code' => 'github_token_missing',
                'severity' => 'medium',
                'message' => 'Token GitHub globale non configurato (check/update potrebbero fallire).',
            ];
        }

        $plugins = is_array($payload['configured_plugins'] ?? null) ? $payload['configured_plugins'] : [];
        if ((int) ($plugins['config_gaps'] ?? 0) > 0) {
            $problems[] = [
                'code' => 'plugin_config_gaps',
                'severity' => 'medium',
                'message' => 'Plugin configurati con repo o slug mancante.',
            ];
        }

        $pending = is_array($payload['pending_updates'] ?? null) ? $payload['pending_updates'] : [];
        if ((int) ($pending['count'] ?? 0) > 5) {
            $problems[] = [
                'code' => 'many_pending_updates',
                'severity' => 'low',
                'message' => 'Più di 5 aggiornamenti in attesa di approvazione.',
            ];
        }

        if ($isMaster) {
            $deploy = is_array($payload['deploy_pipeline'] ?? null) ? $payload['deploy_pipeline'] : [];
            if (
                empty($deploy['deploy_window_active'])
                && ((int) ($deploy['deploy_install_queue_count'] ?? 0) > 0
                    || (int) ($deploy['deploy_update_queue_count'] ?? 0) > 0)
            ) {
                $problems[] = [
                    'code' => 'deploy_queue_without_window',
                    'severity' => 'medium',
                    'message' => 'Code deploy presenti ma finestra autorizzazione scaduta.',
                ];
            }

            $clients = is_array($payload['connected_clients'] ?? null) ? $payload['connected_clients'] : [];
            if ((int) ($clients['active_clients_count'] ?? 0) === 0) {
                $problems[] = [
                    'code' => 'no_active_clients',
                    'severity' => 'high',
                    'message' => 'Nessun client attivo negli ultimi 30 giorni.',
                ];
            }
        }

        $logs = is_array($payload['logs_recent'] ?? null) ? $payload['logs_recent'] : [];
        if ((int) ($logs['errors_warnings_last_24h'] ?? 0) > 20) {
            $problems[] = [
                'code' => 'high_log_noise_24h',
                'severity' => 'low',
                'message' => 'Oltre 20 log error/warning nelle ultime 24 ore.',
            ];
        }

        return $problems;
    }

    private static function is_master_mode(): bool
    {
        return (bool) get_option(MasterEndpoint::OPTION_MASTER_MODE, false);
    }

    private static function logs_table_exists(): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'fp_git_updater_logs';

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    /**
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    private static function scrub_details(array $details): array
    {
        $scrubKeys = ['secret', 'token', 'password', 'authorization', 'signature', 'api_key'];
        $out = [];
        foreach ($details as $key => $value) {
            $keyStr = strtolower((string) $key);
            $redact = false;
            foreach ($scrubKeys as $needle) {
                if ($needle !== '' && strpos($keyStr, $needle) !== false) {
                    $redact = true;
                    break;
                }
            }
            if ($redact) {
                $out[$key] = '[REDACTED]';
                continue;
            }
            if (is_scalar($value)) {
                $out[$key] = $value;
            } elseif (is_array($value)) {
                $out[$key] = self::scrub_details($value);
            }
        }

        return $out;
    }

    private static function short_sha(string $sha): string
    {
        $sha = trim($sha);
        if ($sha === '') {
            return '';
        }

        return strlen($sha) > 8 ? substr($sha, 0, 8) : $sha;
    }

    private static function url_host(string $url): ?string
    {
        $host = wp_parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }
}
