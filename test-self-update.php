<?php
/**
 * Test per la funzionalità di auto-aggiornamento
 * 
 * Questo file può essere eseguito per testare la funzionalità di auto-aggiornamento
 * del plugin FP Git Updater.
 */

// Simula l'ambiente WordPress
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Carica WordPress (se disponibile)
if (file_exists(ABSPATH . 'wp-config.php')) {
    require_once(ABSPATH . 'wp-config.php');
    
    // Test della funzionalità di auto-aggiornamento
    echo "=== Test Auto-aggiornamento FP Git Updater ===\n\n";
    
    // 1. Verifica che il plugin sia attivo
    if (class_exists('FP_Git_Updater')) {
        echo "✅ Plugin FP Git Updater attivo\n";
        
        // 2. Verifica che la configurazione di auto-aggiornamento sia presente
        $settings = get_option('fp_git_updater_settings');
        if ($settings && isset($settings['plugins'])) {
            $self_plugin_found = false;
            foreach ($settings['plugins'] as $plugin) {
                if (isset($plugin['plugin_slug']) && $plugin['plugin_slug'] === 'fp-git-updater') {
                    $self_plugin_found = true;
                    echo "✅ Plugin configurato per auto-aggiornamento\n";
                    echo "   - Nome: " . $plugin['name'] . "\n";
                    echo "   - Repository: " . $plugin['github_repo'] . "\n";
                    echo "   - Branch: " . $plugin['branch'] . "\n";
                    echo "   - Abilitato: " . ($plugin['enabled'] ? 'Sì' : 'No') . "\n";
                    break;
                }
            }
            
            if (!$self_plugin_found) {
                echo "❌ Plugin non configurato per auto-aggiornamento\n";
            }
        } else {
            echo "❌ Impostazioni del plugin non trovate\n";
        }
        
        // 3. Verifica informazioni sull'ultimo aggiornamento
        $self_update_info = get_option('fp_git_updater_self_updated');
        if ($self_update_info) {
            echo "✅ Informazioni ultimo auto-aggiornamento trovate\n";
            echo "   - Data: " . $self_update_info['timestamp'] . "\n";
            echo "   - Commit: " . $self_update_info['commit_sha'] . "\n";
            echo "   - Versione: " . $self_update_info['version'] . "\n";
        } else {
            echo "ℹ️  Nessun auto-aggiornamento precedente registrato\n";
        }
        
        // 4. Verifica aggiornamenti pending
        $pending_update = get_option('fp_git_updater_pending_update_fp_git_updater_self');
        if ($pending_update) {
            echo "🔄 Aggiornamento pending disponibile\n";
            echo "   - Commit: " . $pending_update['commit_sha_short'] . "\n";
            echo "   - Messaggio: " . $pending_update['commit_message'] . "\n";
            echo "   - Data: " . $pending_update['timestamp'] . "\n";
        } else {
            echo "✅ Nessun aggiornamento pending\n";
        }
        
        // 5. Test delle classi necessarie
        if (class_exists('FP_Git_Updater_Updater')) {
            echo "✅ Classe FP_Git_Updater_Updater disponibile\n";
        } else {
            echo "❌ Classe FP_Git_Updater_Updater non trovata\n";
        }
        
        if (class_exists('FP_Git_Updater_Admin')) {
            echo "✅ Classe FP_Git_Updater_Admin disponibile\n";
        } else {
            echo "❌ Classe FP_Git_Updater_Admin non trovata\n";
        }
        
        // 6. Verifica file template
        $template_file = FP_GIT_UPDATER_PLUGIN_DIR . 'includes/admin-templates/partials/self-update-section.php';
        if (file_exists($template_file)) {
            echo "✅ Template auto-aggiornamento presente\n";
        } else {
            echo "❌ Template auto-aggiornamento non trovato\n";
        }
        
    } else {
        echo "❌ Plugin FP Git Updater non attivo\n";
    }
    
    echo "\n=== Test Completato ===\n";
    
} else {
    echo "❌ WordPress non trovato. Esegui questo test in un ambiente WordPress.\n";
}
?>
