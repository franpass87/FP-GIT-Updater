<?php
/**
 * Template Partial: Notifica Modalità Aggiornamento
 * 
 * @var bool $auto_update_enabled Stato aggiornamento automatico
 */

if (!defined('ABSPATH')) {
    return;
}
?>

<?php if (!$auto_update_enabled): ?>
    <div class="fp-notice fp-notice-info fpgitupdater-notice-mode">
        <p>
            <span class="dashicons dashicons-shield-alt" aria-hidden="true"></span>
            <strong><?php esc_html_e('Modalità aggiornamento manuale attiva', 'fp-git-updater'); ?></strong>
            <?php esc_html_e('— Gli aggiornamenti non verranno installati automaticamente. Riceverai una notifica quando sono disponibili e potrai installarli manualmente.', 'fp-git-updater'); ?>
        </p>
    </div>
<?php else: ?>
    <div class="fp-notice fp-notice-info fp-notice-warning fpgitupdater-notice-mode fpgitupdater-notice-mode--manual">
        <p>
            <span class="dashicons dashicons-warning" aria-hidden="true"></span>
            <strong><?php esc_html_e('Attenzione:', 'fp-git-updater'); ?></strong>
            <?php esc_html_e('Aggiornamento automatico attivo: i plugin verranno aggiornati quando ricevi un push da GitHub.', 'fp-git-updater'); ?>
            <br><?php esc_html_e('Per maggiore controllo disabilita «Aggiornamento automatico» nel tab Impostazioni generali.', 'fp-git-updater'); ?>
        </p>
    </div>
<?php endif; ?>
