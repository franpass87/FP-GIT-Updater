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

// Recupera le impostazioni per ottenere il repository del plugin stesso
$settings = get_option('fp_git_updater_settings', array());
$plugins = isset($settings['plugins']) ? $settings['plugins'] : array();

// Trova il plugin FP Git Updater nella lista dei plugin gestiti
$self_plugin = null;
foreach ($plugins as $plugin) {
    if (isset($plugin['id']) && $plugin['id'] === 'fp_git_updater_self') {
        $self_plugin = $plugin;
        break;
    }
    // Fallback: controlla se il repository è quello del plugin stesso
    if (isset($plugin['github_repo'])) {
        $repo_lower = strtolower($plugin['github_repo']);
        if ($repo_lower === 'franpass87/fp-git-updater' || $repo_lower === 'franpass87/fp-git-updater') {
            $self_plugin = $plugin;
            break;
        }
    }
}

// Se non trovato, crea un array di default per il plugin stesso
if (!$self_plugin) {
    $self_plugin = array(
        'id' => 'fp_git_updater_self',
        'github_repo' => 'FranPass87/FP-GIT-Updater',
        'branch' => 'main',
        'enabled' => true
    );
}

// Ottieni versione GitHub
$github_version = '';
if ($self_pending && !empty($self_pending['available_version'])) {
    // Usa la versione già recuperata nell'aggiornamento pending
    $github_version = $self_pending['available_version'];
} else {
    // Controlla se abbiamo una versione GitHub salvata in cache (validità 5 minuti)
    $cached_github_version = get_transient('fp_git_updater_github_version_' . $self_plugin['id']);
    if ($cached_github_version !== false) {
        $github_version = $cached_github_version;
    } elseif (!empty($self_plugin['github_repo'])) {
        // Recupera la versione GitHub solo se non in cache
        $github_version = $updater->get_github_plugin_version($self_plugin);
        // Salva in cache per 5 minuti (300 secondi)
        if (!empty($github_version)) {
            set_transient('fp_git_updater_github_version_' . $self_plugin['id'], $github_version, 300);
        }
    }
}

// Determina se le versioni sono diverse
$versions_differ = !empty($current_version) && !empty($github_version) && version_compare($current_version, $github_version, '<');
?>

<div class="fp-git-updater-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;">
    <h2 style="color: white; margin-top: 0;">
        <span class="dashicons dashicons-update" style="color: white;"></span>
        <?php _e('Auto-aggiornamento FP Git Updater', 'fp-git-updater'); ?>
    </h2>
    
    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 20px; margin-top: 15px;">
        <!-- Versioni -->
        <div style="background: rgba(255,255,255,0.15); padding: 10px 15px; border-radius: 5px; display: flex; flex-wrap: wrap; gap: 15px; align-items: center; font-size: 13px;">
            <span style="font-weight: 600; white-space: nowrap;">
                <?php _e('Versione:', 'fp-git-updater'); ?>
            </span>
            <span style="white-space: nowrap;">
                <strong><?php _e('Installata:', 'fp-git-updater'); ?></strong>
                <code style="background: rgba(0,0,0,0.2); padding: 3px 8px; border-radius: 3px; font-family: monospace; margin-left: 5px;">
                    <?php echo esc_html($current_version); ?>
                </code>
            </span>
            <span style="white-space: nowrap;">
                <strong><?php _e('GitHub:', 'fp-git-updater'); ?></strong>
                <code style="background: rgba(0,0,0,0.2); padding: 3px 8px; border-radius: 3px; font-family: monospace; margin-left: 5px; <?php echo $versions_differ ? 'color: #ffd700; border: 1px solid #ffd700;' : 'color: #90EE90;'; ?>">
                    <?php echo !empty($github_version) ? esc_html($github_version) : '—'; ?>
                </code>
            </span>
            <?php if ($versions_differ): ?>
                <span style="white-space: nowrap; color: #ffd700;">
                    <span class="dashicons dashicons-warning" style="font-size: 16px; vertical-align: middle;"></span>
                    <?php printf(__('Aggiornamento disponibile: %s → %s', 'fp-git-updater'), esc_html($current_version), esc_html($github_version)); ?>
                </span>
            <?php elseif (!empty($current_version) && !empty($github_version) && $current_version === $github_version): ?>
                <span style="white-space: nowrap; color: #90EE90;">
                    <span class="dashicons dashicons-yes-alt" style="font-size: 16px; vertical-align: middle;"></span>
                    <?php _e('Plugin aggiornato all\'ultima versione', 'fp-git-updater'); ?>
                </span>
            <?php endif; ?>
        </div>
        
        <?php if ($self_update_info): ?>
            <div style="white-space: nowrap;">
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
