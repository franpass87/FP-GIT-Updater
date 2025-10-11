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
    <div class="fp-notice fp-notice-info">
        <p>
            <span class="dashicons dashicons-shield-alt" style="color: #2271b1;"></span>
            <strong><?php _e('Modalità Aggiornamento Manuale Attiva', 'fp-git-updater'); ?></strong> - 
            <?php _e('Gli aggiornamenti non verranno installati automaticamente. Riceverai una notifica quando sono disponibili e potrai installarli manualmente quando sei pronto.', 'fp-git-updater'); ?>
        </p>
    </div>
<?php else: ?>
    <div class="fp-notice fp-notice-info" style="border-left-color: #dba617; background: #fcf9e8;">
        <p>
            <span class="dashicons dashicons-warning" style="color: #dba617;"></span>
            <strong><?php _e('Attenzione:', 'fp-git-updater'); ?></strong> 
            <?php _e('Aggiornamento Automatico Attivo - I plugin verranno aggiornati automaticamente quando ricevi un push da GitHub.', 'fp-git-updater'); ?>
            <br><?php _e('Se vuoi maggiore controllo e sicurezza per i siti dei tuoi clienti, disabilita questa opzione qui sotto.', 'fp-git-updater'); ?>
        </p>
    </div>
<?php endif; ?>
