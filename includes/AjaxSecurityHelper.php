<?php
/**
 * AJAX Security Helper.
 *
 * Centralizza nonce + capability check per gli handler AJAX di FP Updater.
 * Riduce il boilerplate duplicato ~15 volte in Admin.php.
 *
 * @package FP\GitUpdater
 */

declare(strict_types=1);

namespace FP\GitUpdater;

if (!defined('ABSPATH')) {
    exit;
}

class AjaxSecurityHelper
{
    /**
     * Verifica nonce + capability su una richiesta AJAX e termina la richiesta
     * con wp_send_json_error se i requisiti non sono soddisfatti. Non ritorna
     * mai in caso di errore (chiama wp_die internamente).
     *
     * Esempio:
     *   public function ajax_install_update() {
     *       AjaxSecurityHelper::verify();
     *       // qui è garantito: nonce valido + utente ha 'manage_options'.
     *   }
     *
     * @param array{
     *     nonce_action?: string,
     *     nonce_param?: string,
     *     capability?: string,
     *     method?: 'GET'|'POST'|'ANY',
     * } $opts
     * @return void
     */
    public static function verify(array $opts = []): void
    {
        $nonce_action = $opts['nonce_action'] ?? 'fp_git_updater_nonce';
        $nonce_param  = $opts['nonce_param']  ?? 'nonce';
        $capability   = $opts['capability']   ?? 'manage_options';
        $method       = strtoupper($opts['method'] ?? 'ANY');

        // 1. Capability check
        if (!current_user_can($capability)) {
            wp_send_json_error(
                [
                    'message' => __('Permessi insufficienti.', 'fp-git-updater'),
                    'code'    => 'forbidden',
                ],
                403
            );
        }

        // 2. Method check (opzionale)
        if ($method !== 'ANY') {
            $request_method = isset($_SERVER['REQUEST_METHOD'])
                ? strtoupper(sanitize_text_field((string) $_SERVER['REQUEST_METHOD']))
                : '';
            if ($request_method !== $method) {
                wp_send_json_error(
                    [
                        'message' => __('Metodo HTTP non supportato.', 'fp-git-updater'),
                        'code'    => 'method_not_allowed',
                    ],
                    405
                );
            }
        }

        // 3. Nonce check (cerca in POST, GET, header)
        $nonce = '';
        if (isset($_REQUEST[$nonce_param])) {
            $nonce = sanitize_text_field(wp_unslash((string) $_REQUEST[$nonce_param]));
        } elseif (isset($_SERVER['HTTP_X_WP_NONCE'])) {
            $nonce = sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_X_WP_NONCE']));
        }

        if ($nonce === '' || !wp_verify_nonce($nonce, $nonce_action)) {
            wp_send_json_error(
                [
                    'message' => __('Token di sicurezza non valido. Ricarica la pagina e riprova.', 'fp-git-updater'),
                    'code'    => 'invalid_nonce',
                ],
                400
            );
        }
    }

    /**
     * Versione "soft" che ritorna true/false invece di terminare. Utile dentro
     * handler che vogliono restituire WP_Error o gestire diversamente.
     *
     * @param array{nonce_action?: string, nonce_param?: string, capability?: string} $opts
     * @return true|\WP_Error true se OK, WP_Error con codice 'forbidden' o 'invalid_nonce'
     */
    public static function check(array $opts = [])
    {
        $nonce_action = $opts['nonce_action'] ?? 'fp_git_updater_nonce';
        $nonce_param  = $opts['nonce_param']  ?? 'nonce';
        $capability   = $opts['capability']   ?? 'manage_options';

        if (!current_user_can($capability)) {
            return new \WP_Error('forbidden', __('Permessi insufficienti.', 'fp-git-updater'), ['status' => 403]);
        }

        $nonce = isset($_REQUEST[$nonce_param])
            ? sanitize_text_field(wp_unslash((string) $_REQUEST[$nonce_param]))
            : '';

        if ($nonce === '' || !wp_verify_nonce($nonce, $nonce_action)) {
            return new \WP_Error('invalid_nonce', __('Token di sicurezza non valido.', 'fp-git-updater'), ['status' => 400]);
        }

        return true;
    }

    /**
     * Sanitizza un parametro che può essere array nativo, JSON stringificato
     * o stringa CSV. Restituisce sempre array<string> con sanitize_text_field
     * applicato. Centralizza il pattern duplicato in più endpoint REST/AJAX.
     *
     * @param mixed $raw
     * @return array<int, string>
     */
    public static function parse_string_list($raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            } else {
                $raw = array_map('trim', explode(',', $raw));
            }
        }

        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($item): string => sanitize_text_field((string) $item),
            $raw
        )));
    }
}
