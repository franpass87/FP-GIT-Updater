<?php
/**
 * Template Partial: Notifica Aggiornamenti Pending
 * 
 * @var array $pending_updates Lista aggiornamenti in attesa
 */

if (!defined('ABSPATH') || empty($pending_updates)) {
    return;
}
?>

<div class="fp-notice fp-notice-info" style="border-left-color: #d63638; background: #fcf0f1;">
    <h3 style="margin-top: 0;">
        ⚠️ <?php 
        printf(
            _n(
                'Aggiornamento Disponibile (%d)',
                'Aggiornamenti Disponibili (%d)',
                count($pending_updates),
                'fp-git-updater'
            ),
            count($pending_updates)
        ); 
        ?>
    </h3>
    <p><strong><?php _e('I seguenti plugin hanno aggiornamenti pronti per essere installati:', 'fp-git-updater'); ?></strong></p>
    <ul style="margin-left: 20px;">
        <?php foreach ($pending_updates as $pending): ?>
            <li>
                <strong><?php echo esc_html($pending['plugin_name']); ?></strong> - 
                <?php _e('Commit:', 'fp-git-updater'); ?> <code><?php echo esc_html($pending['commit_sha_short']); ?></code>
                <?php if (!empty($pending['commit_message']) && $pending['commit_message'] !== 'Aggiornamento rilevato dal controllo schedulato'): ?>
                    <br><em style="color: #666;"><?php echo esc_html($pending['commit_message']); ?></em>
                    <?php if (!empty($pending['commit_author']) && $pending['commit_author'] !== 'Sistema'): ?>
                        - <?php printf(__('da %s', 'fp-git-updater'), esc_html($pending['commit_author'])); ?>
                    <?php endif; ?>
                <?php endif; ?>
                <br><small><?php printf(__('Ricevuto: %s', 'fp-git-updater'), esc_html($pending['timestamp'])); ?></small>
            </li>
        <?php endforeach; ?>
    </ul>
    <p style="margin-bottom: 0;">
        <strong><?php _e('Azione richiesta:', 'fp-git-updater'); ?></strong> 
        <?php _e('Scorri in basso e clicca su "Installa Aggiornamento" per ogni plugin che vuoi aggiornare.', 'fp-git-updater'); ?>
    </p>
</div>
