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

// Trova il plugin FP Updater nella lista dei plugin gestiti
$self_plugin = null;
foreach ($plugins as $plugin) {
    if (isset($plugin['id']) && $plugin['id'] === 'fp_git_updater_self') {
        $self_plugin = $plugin;
        break;
    }
    // Fallback: controlla se il repository è quello del plugin stesso
    if (isset($plugin['github_repo'])) {
        $repo_lower = strtolower($plugin['github_repo']);
        if ($repo_lower === 'franpass87/fp-git-updater' || $repo_lower === 'franpass87/fp-updater') {
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

<div class="fp-git-updater-header">
    <h2 class="fp-section-title">
        <span class="dashicons dashicons-update"></span>
        <?php _e('Auto-aggiornamento FP Updater', 'fp-git-updater'); ?>
    </h2>
    
    <div class="fp-self-update-versions">
        <div class="fp-version-box">
            <span class="fp-version-label"><?php _e('Versione:', 'fp-git-updater'); ?></span>
            <span class="fp-version-item">
                <strong><?php _e('Installata:', 'fp-git-updater'); ?></strong>
                <code><?php echo esc_html($current_version); ?></code>
            </span>
            <span class="fp-version-item">
                <strong><?php _e('GitHub:', 'fp-git-updater'); ?></strong>
                <code class="<?php echo $versions_differ ? 'fp-version-diff' : 'fp-version-same'; ?>" data-plugin-id="fp_git_updater_self">
                    <?php echo !empty($github_version) ? esc_html($github_version) : '—'; ?>
                </code>
                <button type="button" 
                        class="button button-small fp-refresh-github-version" 
                        data-plugin-id="fp_git_updater_self"
                        title="<?php esc_attr_e('Aggiorna versione GitHub', 'fp-git-updater'); ?>">
                    <span class="dashicons dashicons-update"></span>
                </button>
            </span>
            <?php if ($versions_differ): ?>
                <span class="fp-version-status fp-version-status-update">
                    <span class="dashicons dashicons-warning"></span>
                    <?php printf(__('Aggiornamento disponibile: %s → %s', 'fp-git-updater'), esc_html($current_version), esc_html($github_version)); ?>
                </span>
            <?php elseif (!empty($current_version) && !empty($github_version) && $current_version === $github_version): ?>
                <span class="fp-version-status fp-version-status-ok">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php _e('Plugin aggiornato all\'ultima versione', 'fp-git-updater'); ?>
                </span>
            <?php endif; ?>
        </div>
        
        <?php if ($self_update_info): ?>
            <div class="fp-last-update-info">
                <strong><?php _e('Ultimo Aggiornamento:', 'fp-git-updater'); ?></strong> 
                <span><?php echo esc_html($self_update_info['timestamp']); ?></span>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($self_pending): ?>
        <div class="fp-notice fp-notice-warning">
            <h3>
                <span class="dashicons dashicons-warning"></span>
                <?php _e('Aggiornamento Disponibile!', 'fp-git-updater'); ?>
            </h3>
            <p>
                <strong><?php _e('Commit:', 'fp-git-updater'); ?></strong> 
                <code><?php echo esc_html($self_pending['commit_sha_short']); ?></code>
                <?php if (!empty($self_pending['commit_message']) && $self_pending['commit_message'] !== 'Aggiornamento rilevato dal controllo schedulato'): ?>
                    <br><em><?php echo esc_html($self_pending['commit_message']); ?></em>
                <?php endif; ?>
            </p>
            <button type="button" id="fp-install-self-update" class="button button-primary">
                <span class="dashicons dashicons-download"></span>
                <?php _e('Installa Aggiornamento Ora', 'fp-git-updater'); ?>
            </button>
        </div>
    <?php else: ?>
        <div class="fp-notice fp-notice-success">
            <p>
                <span class="dashicons dashicons-yes-alt"></span>
                <strong><?php _e('FP Updater è aggiornato!', 'fp-git-updater'); ?></strong>
            </p>
        </div>
    <?php endif; ?>
    
    <div class="fp-self-update-actions">
        <button type="button" id="fp-check-self-update" class="button">
            <span class="dashicons dashicons-cloud"></span>
            <?php _e('Controlla Aggiornamenti', 'fp-git-updater'); ?>
        </button>
        
        <?php if ($self_update_info): ?>
            <button type="button" id="fp-view-self-update-log" class="button">
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
        
        $.post(fpGitUpdater.ajax_url || (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'), {
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
        if (!confirm('<?php _e('Sei sicuro di voler aggiornare FP Updater? Questa operazione potrebbe richiedere alcuni secondi.', 'fp-git-updater'); ?>')) {
            return;
        }
        
        var $btn = $(this);
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> <?php _e('Aggiornamento in corso...', 'fp-git-updater'); ?>');
        
        $.post(fpGitUpdater.ajax_url || (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'), {
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
