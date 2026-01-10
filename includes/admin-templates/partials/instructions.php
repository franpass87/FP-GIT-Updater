<?php
/**
 * Template Partial: Istruzioni
 */

if (!defined('ABSPATH')) {
    return;
}
?>

<div class="fp-git-updater-instructions">
    <h2>
        <span class="dashicons dashicons-book-alt" style="color: #2271b1;"></span>
        <?php _e('Come Funziona', 'fp-git-updater'); ?>
    </h2>
    
    <h3 style="font-size: 18px; font-weight: 600; color: #1d2327; margin-top: 25px; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
        <span class="dashicons dashicons-lock" style="color: #2271b1; font-size: 20px;"></span>
        <?php _e('Aggiornamento Manuale (Consigliato per Siti di Produzione)', 'fp-git-updater'); ?>
    </h3>
    <p><?php _e('Con l\'aggiornamento manuale disabilitato (opzione sopra), il plugin funziona in questo modo:', 'fp-git-updater'); ?></p>
    <ol>
        <li><?php _e('Quando fai push/merge su GitHub, il webhook notifica questo sito', 'fp-git-updater'); ?></li>
        <li><?php _e('Il plugin registra l\'aggiornamento come "disponibile" ma NON lo installa automaticamente', 'fp-git-updater'); ?></li>
        <li><?php _e('Ricevi una notifica visibile nel menu admin e nella pagina delle impostazioni', 'fp-git-updater'); ?></li>
        <li><strong><?php _e('Tu decidi quando installare l\'aggiornamento cliccando sul pulsante "Installa Aggiornamento"', 'fp-git-updater'); ?></strong></li>
        <li><?php _e('Questo ti permette di testare prima gli aggiornamenti su un sito di staging', 'fp-git-updater'); ?></li>
    </ol>
    <p><strong style="color: #00a32a;">✓ <?php _e('Vantaggi:', 'fp-git-updater'); ?></strong> <?php _e('Protezione totale da aggiornamenti problematici, controllo completo, ideale per siti di clienti.', 'fp-git-updater'); ?></p>
    
    <h3 style="font-size: 18px; font-weight: 600; color: #1d2327; margin-top: 25px; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
        <span class="dashicons dashicons-update" style="color: #dba617; font-size: 20px;"></span>
        <?php _e('Aggiornamento Automatico', 'fp-git-updater'); ?>
    </h3>
    <p><?php _e('Se abiliti l\'opzione "Aggiornamento Automatico" sopra:', 'fp-git-updater'); ?></p>
    <ul style="padding-left: 20px;">
        <li><?php _e('Gli aggiornamenti vengono installati immediatamente quando ricevi un push da GitHub', 'fp-git-updater'); ?></li>
        <li><?php _e('Utile per ambienti di sviluppo o plugin molto stabili', 'fp-git-updater'); ?></li>
        <li><strong style="color: #d63638;">⚠ <?php _e('Attenzione:', 'fp-git-updater'); ?></strong> <?php _e('Un aggiornamento con bug andrà automaticamente in produzione', 'fp-git-updater'); ?></li>
    </ul>
    
    <h3 style="font-size: 18px; font-weight: 600; color: #1d2327; margin-top: 25px; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
        <span class="dashicons dashicons-admin-links" style="color: #8c5ed9; font-size: 20px;"></span>
        <?php _e('Configurazione Webhook su GitHub', 'fp-git-updater'); ?>
    </h3>
    <p><strong><?php _e('Importante:', 'fp-git-updater'); ?></strong> <?php _e('Devi configurare il webhook per ogni repository che hai aggiunto sopra.', 'fp-git-updater'); ?></p>
    <ol>
        <li><?php _e('Vai sul repository GitHub del plugin che vuoi aggiornare', 'fp-git-updater'); ?></li>
        <li><?php printf(__('Clicca su %s → %s → %s', 'fp-git-updater'), '<strong>Settings</strong>', '<strong>Webhooks</strong>', '<strong>Add webhook</strong>'); ?></li>
        <li><?php printf(__('Incolla l\'URL webhook qui sopra nel campo %s', 'fp-git-updater'), '<strong>Payload URL</strong>'); ?></li>
        <li><?php printf(__('Seleziona %s', 'fp-git-updater'), '<strong>Content type: application/json</strong>'); ?></li>
        <li><?php printf(__('Incolla il Webhook Secret nel campo %s', 'fp-git-updater'), '<strong>Secret</strong>'); ?></li>
        <li><?php printf(__('In %s seleziona %s', 'fp-git-updater'), '<strong>Which events would you like to trigger this webhook?</strong>', '<strong>Just the push event</strong>'); ?></li>
        <li><?php printf(__('Clicca su %s', 'fp-git-updater'), '<strong>Add webhook</strong>'); ?></li>
        <li><?php _e('Ripeti per ogni repository che hai configurato', 'fp-git-updater'); ?></li>
    </ol>
</div>
