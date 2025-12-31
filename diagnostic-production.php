<?php
/**
 * DIAGNOSTICA PRODUZIONE - FP Git Updater
 * 
 * Carica questo file su agriavengers.it e aprilo nel browser
 * URL: https://agriavengers.it/wp-content/plugins/fp-git-updater/diagnostic-production.php
 */

// Forza visualizzazione errori
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre style='background:#f0f0f0; padding:20px; font-family:monospace;'>";
echo "=== DIAGNOSTICA FP GIT UPDATER - PRODUZIONE ===\n\n";

// 1. Verifica path
echo "1. PATH CORRENTE:\n";
echo "   " . __DIR__ . "\n\n";

// 2. Verifica file principale
echo "2. FILE PRINCIPALE:\n";
$main_file = __DIR__ . '/fp-git-updater.php';
echo "   Esiste: " . (file_exists($main_file) ? "✅ SI" : "❌ NO") . "\n";
if (file_exists($main_file)) {
    echo "   Readable: " . (is_readable($main_file) ? "✅ SI" : "❌ NO") . "\n";
    echo "   Size: " . filesize($main_file) . " bytes\n";
}
echo "\n";

// 3. Verifica vendor/autoload.php
echo "3. COMPOSER AUTOLOAD:\n";
$autoload = __DIR__ . '/vendor/autoload.php';
echo "   vendor/autoload.php esiste: " . (file_exists($autoload) ? "✅ SI" : "❌ NO") . "\n";
if (file_exists($autoload)) {
    echo "   Readable: " . (is_readable($autoload) ? "✅ SI" : "❌ NO") . "\n";
    echo "   Size: " . filesize($autoload) . " bytes\n";
}
echo "\n";

// 4. Verifica classi includes
echo "4. CLASSI INCLUDES:\n";
$classes = ['Admin.php', 'Updater.php', 'Logger.php', 'Encryption.php'];
foreach ($classes as $class) {
    $path = __DIR__ . '/includes/' . $class;
    $exists = file_exists($path);
    echo "   includes/$class: " . ($exists ? "✅" : "❌") . "\n";
}
echo "\n";

// 5. Verifica WordPress
echo "5. WORDPRESS:\n";
$wp_load = __DIR__ . '/../../../../wp-load.php';
if (file_exists($wp_load)) {
    echo "   Caricamento WordPress...\n";
    require_once $wp_load;
    echo "   ✅ WordPress caricato\n";
    echo "   WP Version: " . get_bloginfo('version') . "\n";
    echo "   PHP Version: " . PHP_VERSION . "\n";
    echo "   is_admin(): " . (is_admin() ? "YES" : "NO") . "\n";
    echo "\n";
    
    // 6. Verifica Composer Autoload
    echo "6. TEST COMPOSER AUTOLOAD:\n";
    if (file_exists($autoload)) {
        require_once $autoload;
        echo "   ✅ Autoload caricato\n";
        
        // Test caricamento classe Admin
        echo "\n7. TEST CLASSE ADMIN:\n";
        if (class_exists('\FP\GitUpdater\Admin')) {
            echo "   ✅ Classe Admin trovata!\n";
            
            // Testa istanziazione
            try {
                $admin = \FP\GitUpdater\Admin::get_instance();
                echo "   ✅ Istanza Admin creata con successo\n";
                echo "   ✅ TUTTO FUNZIONA CORRETTAMENTE!\n\n";
                echo "   🔍 PROBLEMA: Il menu dovrebbe essere visibile.\n";
                echo "      Possibili cause:\n";
                echo "      - Cache del browser (premi CTRL+F5)\n";
                echo "      - Cache del server (svuota cache plugin)\n";
                echo "      - Conflitto con altro plugin\n";
            } catch (Exception $e) {
                echo "   ❌ ERRORE istanziazione: " . $e->getMessage() . "\n";
            }
        } else {
            echo "   ❌ Classe Admin NON trovata\n";
            echo "   🔍 Autoload PSR-4 non funziona\n";
        }
    } else {
        echo "   ❌ vendor/autoload.php NON trovato\n";
    }
} else {
    echo "   ❌ WordPress non trovato al path: $wp_load\n";
}

echo "\n=== FINE DIAGNOSTICA ===\n";
echo "</pre>";












