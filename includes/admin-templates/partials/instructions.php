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
        <span class="dashicons dashicons-book-alt"></span>
        <?php _e('Come Funziona', 'fp-git-updater'); ?>
    </h2>

    <h3 class="fp-instructions-subtitle">
        <span class="dashicons dashicons-networking"></span>
        <?php _e('Modalità Master e distribuzione ai clienti', 'fp-git-updater'); ?>
    </h3>
    <p><?php _e('Se usi FP Remote Bridge sui siti dei tuoi clienti, questo plugin funziona da Master:', 'fp-git-updater'); ?></p>
    <ol>
        <li><?php _e('Configura il Master (tab principale): abilita, inserisci chiave segreta e URL', 'fp-git-updater'); ?></li>
        <li><?php _e('Aggiungi i plugin da GitHub nella sezione «Plugin gestiti»', 'fp-git-updater'); ?></li>
        <li><?php _e('Distribuisci: «Installa su clienti» per nuovi siti, «Aggiorna chi ce l\'ha» per aggiornamenti', 'fp-git-updater'); ?></li>
        <li><?php _e('Su ogni sito cliente: installa FP Remote Bridge e incolla URL + chiave segreta', 'fp-git-updater'); ?></li>
    </ol>

    <h3 class="fp-instructions-subtitle">
        <span class="dashicons dashicons-lock"></span>
        <?php _e('Aggiornamento su questo sito (manual vs automatic)', 'fp-git-updater'); ?>
    </h3>
    <p><?php _e('Nel tab Impostazioni Generali puoi scegliere:', 'fp-git-updater'); ?></p>
    <ul class="fp-instructions-list">
        <li><strong><?php _e('Manuale (consigliato):', 'fp-git-updater'); ?></strong> <?php _e('il webhook notifica, ma tu decidi quando cliccare «Installa Aggiornamento». Ideale per produzione.', 'fp-git-updater'); ?></li>
        <li><strong><?php _e('Automatico:', 'fp-git-updater'); ?></strong> <?php _e('gli aggiornamenti si installano subito al push. Utile per sviluppo.', 'fp-git-updater'); ?></li>
    </ul>

    <h3 class="fp-instructions-subtitle">
        <span class="dashicons dashicons-admin-links"></span>
        <?php _e('Configurazione Webhook su GitHub', 'fp-git-updater'); ?>
    </h3>
    <p class="fp-instructions-note"><strong><?php _e('Importante:', 'fp-git-updater'); ?></strong> <?php _e('Configura il webhook per ogni repository che aggiungi in «Plugin gestiti».', 'fp-git-updater'); ?></p>
    <ol>
        <li><?php _e('Vai sul repository GitHub del plugin', 'fp-git-updater'); ?></li>
        <li><?php printf(__('Clicca su %s → %s → %s', 'fp-git-updater'), '<strong>Settings</strong>', '<strong>Webhooks</strong>', '<strong>Add webhook</strong>'); ?></li>
        <li><?php printf(__('Incolla l\'URL webhook (dal tab Impostazioni Generali) nel campo %s', 'fp-git-updater'), '<strong>Payload URL</strong>'); ?></li>
        <li><?php printf(__('Seleziona %s e incolla il Webhook Secret', 'fp-git-updater'), '<strong>Content type: application/json</strong>'); ?></li>
        <li><?php printf(__('In %s scegli %s, poi clicca %s', 'fp-git-updater'), '<strong>Which events...</strong>', '<strong>Just the push event</strong>', '<strong>Add webhook</strong>'); ?></li>
        <li><?php _e('Ripeti per ogni repository configurato', 'fp-git-updater'); ?></li>
    </ol>
</div>
