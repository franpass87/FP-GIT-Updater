<?php
/**
 * Template: Sezione Auto-aggiornamento
 * 
 * Mostra informazioni e controlli per l'auto-aggiornamento del plugin stesso
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ottieni informazioni sull'ultimo auto-aggiornamento
$self_update_info = get_option('fp_git_updater_self_updated');
$current_version = FP_GIT_UPDATER_VERSION;

// Controlla se c'è un aggiornamento pending per il plugin stesso
use FP\GitUpdater\Updater;

$updater = Updater::get_instance();
$self_pending = get_option('fp_git_updater_pending_update_fp_git_updater_self');
?>

<div class="fp-git-updater-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;">
    <h2 style="color: white; margin-top: 0;">
        <span class="dashicons dashicons-update" style="color: white;"></span>
        <?php _e('Auto-aggiornamento FP Git Updater', 'fp-git-updater'); ?>
    </h2>
    
    <div style="display: flex; align-items: center; gap: 20px; margin-top: 15px;">
        <div>
            <strong><?php _e('Versione Attuale:', 'fp-git-updater'); ?></strong> 
            <span style="background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 3px; font-family: monospace;">
                <?php echo esc_html($current_version); ?>
            </span>
        </div>
        
        <?php if ($self_update_info): ?>
            <div>
                <strong><?php _e('Ultimo Aggiornamento:', 'fp-git-updater'); ?></strong> 
                <span style="background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 3px;">
                    <?php echo esc_html($self_update_info['timestamp']); ?>
                </span>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($self_pending): ?>
        <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 5px; margin-top: 15px; border-left: 4px solid #ffd700;">
            <h3 style="color: #ffd700; margin-top: 0;">
                <span class="dashicons dashicons-warning"></span>
                <?php _e('Aggiornamento Disponibile!', 'fp-git-updater'); ?>
            </h3>
            <p style="margin-bottom: 10px;">
                <strong><?php _e('Commit:', 'fp-git-updater'); ?></strong> 
                <code style="background: rgba(0,0,0,0.2); color: #ffd700;"><?php echo esc_html($self_pending['commit_sha_short']); ?></code>
                <?php if (!empty($self_pending['commit_message']) && $self_pending['commit_message'] !== 'Aggiornamento rilevato dal controllo schedulato'): ?>
                    <br><em><?php echo esc_html($self_pending['commit_message']); ?></em>
                <?php endif; ?>
            </p>
            <button type="button" id="fp-install-self-update" class="button button-primary" style="background: #ffd700; border-color: #ffd700; color: #000;">
                <span class="dashicons dashicons-download"></span>
                <?php _e('Installa Aggiornamento Ora', 'fp-git-updater'); ?>
            </button>
        </div>
    <?php else: ?>
        <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 5px; margin-top: 15px;">
            <p style="margin: 0;">
                <span class="dashicons dashicons-yes-alt" style="color: #90EE90;"></span>
                <strong><?php _e('FP Git Updater è aggiornato!', 'fp-git-updater'); ?></strong>
            </p>
        </div>
    <?php endif; ?>
    
    <div style="margin-top: 15px;">
        <button type="button" id="fp-check-self-update" class="button" style="background: rgba(255,255,255,0.2); border-color: rgba(255,255,255,0.3); color: white;">
            <span class="dashicons dashicons-cloud"></span>
            <?php _e('Controlla Aggiornamenti', 'fp-git-updater'); ?>
        </button>
        
        <?php if ($self_update_info): ?>
            <button type="button" id="fp-view-self-update-log" class="button" style="background: rgba(255,255,255,0.2); border-color: rgba(255,255,255,0.3); color: white;">
                <span class="dashicons dashicons-list-view"></span>
                <?php _e('Vedi Log Aggiornamento', 'fp-git-updater'); ?>
            </button>
        <?php endif; ?>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Controlla aggiornamenti per il plugin stesso
    $('#fp-check-self-update').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Controllo in corso...');
        
        $.post(ajaxurl, {
            action: 'fp_git_updater_check_self_update',
            nonce: fpGitUpdater.nonce
        }, function(response) {
            if (response.success) {
                alert(response.data.message);
                if (response.data.message.includes('disponibile')) {
                    location.reload(); // Ricarica per mostrare il pulsante di installazione
                }
            } else {
                alert('Errore: ' + (response.data ? response.data.message : 'Errore sconosciuto'));
            }
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-cloud"></span> Controlla Aggiornamenti');
        });
    });
    
    // Installa aggiornamento per il plugin stesso
    $('#fp-install-self-update').on('click', function() {
        if (!confirm('<?php _e('Sei sicuro di voler aggiornare FP Git Updater? Questa operazione potrebbe richiedere alcuni secondi.', 'fp-git-updater'); ?>')) {
            return;
        }
        
        var $btn = $(this);
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> <?php _e('Aggiornamento in corso...', 'fp-git-updater'); ?>');
        
        $.post(ajaxurl, {
            action: 'fp_git_updater_install_self_update',
            nonce: fpGitUpdater.nonce
        }, function(response) {
            if (response.success) {
                alert(response.data.message);
                if (response.data.reload) {
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                }
            } else {
                alert('Errore: ' + (response.data ? response.data.message : 'Errore sconosciuto'));
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> <?php _e('Installa Aggiornamento Ora', 'fp-git-updater'); ?>');
            }
        });
    });
    
    // Vedi log dell'ultimo aggiornamento
    $('#fp-view-self-update-log').on('click', function() {
        window.open('<?php echo admin_url('admin.php?page=fp-git-updater-logs'); ?>', '_blank');
    });
});
</script>
