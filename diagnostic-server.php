<?php
/**
 * Script Diagnostica FP Git Updater
 * Carica questo file nella root del plugin sul server e chiamalo via browser
 * URL: https://agriavengers.it/wp-content/plugins/fp-git-updater/diagnostic-server.php
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>FP Git Updater - Diagnostica Server</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2271b1; border-bottom: 3px solid #2271b1; padding-bottom: 10px; }
        h2 { color: #333; margin-top: 30px; }
        .test { background: #f0f0f0; padding: 15px; margin: 10px 0; border-left: 4px solid #ccc; border-radius: 4px; }
        .success { border-left-color: #00a32a; background: #f0f8f2; }
        .error { border-left-color: #d63638; background: #fcf0f1; }
        .warning { border-left-color: #dba617; background: #fef8ee; }
        .info { border-left-color: #2271b1; background: #f0f6fc; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        pre { background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .icon { font-size: 20px; margin-right: 8px; }
    </style>
</head>
<body>
<div class="container">
    <h1><span class="icon">🔍</span>Diagnostica FP Git Updater</h1>
    
    <?php
    $plugin_dir = __DIR__ . '/';
    $errors_found = 0;
    
    // Test 1: Directory plugin
    echo '<h2>1. Verifica Directory Plugin</h2>';
    echo '<div class="test ' . (is_dir($plugin_dir) ? 'success' : 'error') . '">';
    echo '<strong>Directory plugin:</strong> ' . ($plugin_dir) . '<br>';
    echo '<strong>Esiste:</strong> ' . (is_dir($plugin_dir) ? '✅ SÌ' : '❌ NO');
    if (!is_dir($plugin_dir)) $errors_found++;
    echo '</div>';
    
    // Test 2: File principale
    echo '<h2>2. File Principale Plugin</h2>';
    $main_file = $plugin_dir . 'fp-git-updater.php';
    $exists = file_exists($main_file);
    echo '<div class="test ' . ($exists ? 'success' : 'error') . '">';
    echo '<strong>File:</strong> fp-git-updater.php<br>';
    echo '<strong>Path completo:</strong> ' . $main_file . '<br>';
    echo '<strong>Esiste:</strong> ' . ($exists ? '✅ SÌ' : '❌ NO') . '<br>';
    if ($exists) {
        $size = filesize($main_file);
        echo '<strong>Dimensione:</strong> ' . number_format($size) . ' bytes<br>';
        // Leggi version
        $content = file_get_contents($main_file, false, null, 0, 500);
        if (preg_match('/Version:\s*([0-9.]+)/', $content, $matches)) {
            echo '<strong>Versione rilevata:</strong> ' . $matches[1];
            if ($matches[1] !== '1.2.3') {
                echo ' <span style="color: #d63638;">⚠️ ATTENZIONE: Versione non corretta! Dovrebbe essere 1.2.3</span>';
                $errors_found++;
            }
        }
    } else {
        $errors_found++;
    }
    echo '</div>';
    
    // Test 3: vendor/autoload.php
    echo '<h2>3. ⭐ Composer Autoload (CRITICO)</h2>';
    $vendor_autoload = $plugin_dir . 'vendor/autoload.php';
    $vendor_exists = file_exists($vendor_autoload);
    echo '<div class="test ' . ($vendor_exists ? 'success' : 'error') . '">';
    echo '<strong>File:</strong> vendor/autoload.php<br>';
    echo '<strong>Path:</strong> ' . $vendor_autoload . '<br>';
    echo '<strong>Esiste:</strong> ' . ($vendor_exists ? '✅ SÌ' : '❌ NO - QUESTO È IL PROBLEMA!') . '<br>';
    if (!$vendor_exists) {
        echo '<br><strong style="color: #d63638;">🚨 PROBLEMA TROVATO!</strong><br>';
        echo 'La cartella vendor/ non è presente sul server.<br>';
        echo '<strong>Soluzione:</strong><br>';
        echo '1. Carica manualmente la cartella vendor/ via FTP<br>';
        echo '2. Oppure ricarica il plugin usando l\'ultimo ZIP creato con tar<br>';
        $errors_found++;
    }
    echo '</div>';
    
    // Test 4: Cartelle includes
    echo '<h2>4. Cartella includes/</h2>';
    $includes_dir = $plugin_dir . 'includes/';
    $includes_exists = is_dir($includes_dir);
    echo '<div class="test ' . ($includes_exists ? 'success' : 'error') . '">';
    echo '<strong>Cartella:</strong> includes/<br>';
    echo '<strong>Esiste:</strong> ' . ($includes_exists ? '✅ SÌ' : '❌ NO') . '<br>';
    if ($includes_exists) {
        $php_files = glob($includes_dir . '*.php');
        echo '<strong>File PHP trovati:</strong> ' . count($php_files) . '<br>';
        if (count($php_files) > 0) {
            echo '<details><summary>Lista file (click per espandere)</summary><ul>';
            foreach ($php_files as $file) {
                echo '<li>' . basename($file) . '</li>';
            }
            echo '</ul></details>';
        }
    } else {
        $errors_found++;
    }
    echo '</div>';
    
    // Test 5: File Admin.php
    echo '<h2>5. File Admin.php</h2>';
    $admin_file = $plugin_dir . 'includes/Admin.php';
    $admin_exists = file_exists($admin_file);
    echo '<div class="test ' . ($admin_exists ? 'success' : 'error') . '">';
    echo '<strong>File:</strong> includes/Admin.php<br>';
    echo '<strong>Esiste:</strong> ' . ($admin_exists ? '✅ SÌ' : '❌ NO') . '<br>';
    if ($admin_exists) {
        $size = filesize($admin_file);
        echo '<strong>Dimensione:</strong> ' . number_format($size) . ' bytes<br>';
        $content = file_get_contents($admin_file, false, null, 0, 300);
        if (strpos($content, 'namespace FP\GitUpdater') !== false) {
            echo '<strong>Namespace:</strong> ✅ FP\GitUpdater trovato';
        }
    } else {
        $errors_found++;
    }
    echo '</div>';
    
    // Test 6: Test Composer Autoload (se vendor esiste)
    if ($vendor_exists) {
        echo '<h2>6. Test Caricamento Classi</h2>';
        echo '<div class="test">';
        try {
            require_once $vendor_autoload;
            echo '<strong>Autoload caricato:</strong> ✅ OK<br><br>';
            
            // Test class_exists
            $class_exists = class_exists('\FP\GitUpdater\Admin');
            echo '<div class="' . ($class_exists ? 'success' : 'error') . '" style="padding: 10px; margin: 10px 0;">';
            echo '<strong>class_exists(\FP\GitUpdater\Admin):</strong> ' . ($class_exists ? '✅ SÌ' : '❌ NO') . '<br>';
            
            if ($class_exists) {
                echo '<strong>Namespace mapping:</strong> ✅ FUNZIONANTE<br>';
                echo '<strong>Classe Admin:</strong> ✅ DISPONIBILE<br>';
                echo '<br><span style="color: #00a32a; font-size: 18px;">🎉 TUTTO OK! Il plugin dovrebbe funzionare!</span>';
            } else {
                echo '<br><strong style="color: #d63638;">❌ Classe Admin non trovata anche con vendor presente!</strong><br>';
                echo 'Verifica il file includes/Admin.php';
                $errors_found++;
            }
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="error" style="padding: 10px;">';
            echo '<strong>❌ Errore caricamento autoload:</strong><br>';
            echo htmlspecialchars($e->getMessage());
            echo '</div>';
            $errors_found++;
        }
        echo '</div>';
    }
    
    // Test 7: Verifica PHP Version
    echo '<h2>7. Ambiente Server</h2>';
    $php_version = phpversion();
    $php_ok = version_compare($php_version, '7.4.0', '>=');
    echo '<div class="test ' . ($php_ok ? 'success' : 'error') . '">';
    echo '<strong>Versione PHP:</strong> ' . $php_version;
    if (!$php_ok) {
        echo ' <span style="color: #d63638;">❌ PHP 7.4+ richiesto!</span>';
        $errors_found++;
    } else {
        echo ' ✅';
    }
    echo '</div>';
    
    // Test 8: Permessi
    echo '<h2>8. Permessi File</h2>';
    $readable = is_readable($plugin_dir);
    echo '<div class="test ' . ($readable ? 'success' : 'error') . '">';
    echo '<strong>Directory leggibile:</strong> ' . ($readable ? '✅ SÌ' : '❌ NO') . '<br>';
    if ($vendor_exists) {
        $vendor_readable = is_readable($vendor_autoload);
        echo '<strong>vendor/autoload.php leggibile:</strong> ' . ($vendor_readable ? '✅ SÌ' : '❌ NO');
        if (!$vendor_readable) $errors_found++;
    }
    echo '</div>';
    
    // Riepilogo Finale
    echo '<h2>📊 Riepilogo</h2>';
    if ($errors_found === 0) {
        echo '<div class="test success" style="font-size: 16px;">';
        echo '<strong>🎉 TUTTI I TEST SUPERATI!</strong><br><br>';
        echo 'Il plugin DOVREBBE funzionare correttamente.<br><br>';
        echo 'Se il menu non appare ancora:<br>';
        echo '1. Disattiva e riattiva il plugin<br>';
        echo '2. Pulisci la cache (se presente)<br>';
        echo '3. Fai logout e login<br>';
        echo '4. Prova in navigazione in incognito';
        echo '</div>';
    } else {
        echo '<div class="test error" style="font-size: 16px;">';
        echo '<strong>❌ PROBLEMI TROVATI: ' . $errors_found . '</strong><br><br>';
        echo 'Risolvi i problemi evidenziati sopra.';
        echo '</div>';
    }
    
    // Istruzioni vendor
    if (!$vendor_exists) {
        echo '<h2>🛠️ Come Risolvere il Problema vendor/</h2>';
        echo '<div class="test warning">';
        echo '<strong>METODO 1: Ricarica il plugin (Consigliato)</strong><br>';
        echo '1. Disattiva ed elimina il plugin corrente<br>';
        echo '2. Carica il nuovo fp-git-updater.zip (creato con tar)<br>';
        echo '3. Installa e attiva<br><br>';
        
        echo '<strong>METODO 2: Carica vendor/ via FTP</strong><br>';
        echo '1. Connettiti via FTP al server<br>';
        echo '2. Vai in /wp-content/plugins/fp-git-updater/<br>';
        echo '3. Carica l\'intera cartella vendor/ dal tuo computer locale<br>';
        echo '4. Path locale: C:\\Users\\franc\\Local Sites\\fp-development\\app\\public\\wp-content\\plugins\\fp-git-updater\\vendor<br>';
        echo '5. Ricarica questa pagina per verificare';
        echo '</div>';
    }
    ?>
    
    <h2>ℹ️ Info Tecniche</h2>
    <div class="test info">
        <strong>Script eseguito il:</strong> <?php echo date('d/m/Y H:i:s'); ?><br>
        <strong>Server:</strong> <?php echo $_SERVER['SERVER_NAME'] ?? 'N/A'; ?><br>
        <strong>PHP Version:</strong> <?php echo phpversion(); ?><br>
        <strong>Script path:</strong> <?php echo __FILE__; ?>
    </div>
    
    <p style="text-align: center; color: #666; margin-top: 40px;">
        <small>FP Git Updater v1.2.3 - Diagnostica Server<br>
        Dopo aver risolto i problemi, puoi eliminare questo file.</small>
    </p>
</div>
</body>
</html>












