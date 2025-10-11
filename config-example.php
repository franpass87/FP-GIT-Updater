<?php
/**
 * Esempio di configurazione per FP Git Updater
 * 
 * IMPORTANTE: Questo file è solo un esempio!
 * La configurazione vera si fa tramite il pannello WordPress.
 * 
 * Tuttavia, se vuoi pre-configurare il plugin prima dell'installazione
 * (ad esempio per distribuirlo già configurato), puoi definire queste
 * costanti nel tuo wp-config.php
 */

// Non usare questo file direttamente!
// Copia queste costanti in wp-config.php se necessario

/*
// Repository GitHub (formato: username/repository)
define('FP_GIT_UPDATER_REPO', 'tuousername/tuo-repository');

// Branch da cui scaricare gli aggiornamenti
define('FP_GIT_UPDATER_BRANCH', 'main');

// Token GitHub per repository privati
define('FP_GIT_UPDATER_TOKEN', 'ghp_tuotokensegreto');

// Webhook secret (per sicurezza)
define('FP_GIT_UPDATER_SECRET', 'tusecretcasuale32caratteri');

// Abilita/disabilita aggiornamenti automatici
define('FP_GIT_UPDATER_AUTO_UPDATE', true);

// Abilita/disabilita notifiche email
define('FP_GIT_UPDATER_NOTIFICATIONS', true);

// Email per le notifiche
define('FP_GIT_UPDATER_EMAIL', 'tua@email.com');

// Intervallo di controllo aggiornamenti (hourly, twicedaily, daily)
define('FP_GIT_UPDATER_CHECK_INTERVAL', 'hourly');

// Modalità debug (scrive log più dettagliati)
define('FP_GIT_UPDATER_DEBUG', false);
*/

/**
 * Esempio di utilizzo in wp-config.php:
 * 
 * 1. Apri il file wp-config.php del tuo WordPress
 * 
 * 2. Aggiungi queste righe prima di "That's all, stop editing!":
 * 
 * // Configurazione FP Git Updater
 * define('FP_GIT_UPDATER_REPO', 'mio-username/mio-plugin');
 * define('FP_GIT_UPDATER_BRANCH', 'main');
 * define('FP_GIT_UPDATER_TOKEN', 'ghp_abc123def456...');
 * define('FP_GIT_UPDATER_AUTO_UPDATE', true);
 * 
 * 3. Salva il file
 * 
 * 4. Installa e attiva il plugin
 * 
 * 5. Le impostazioni saranno pre-configurate!
 * 
 * NOTA: Le impostazioni definite in wp-config.php hanno la precedenza
 * su quelle configurate nel pannello WordPress.
 */

/**
 * Esempio di utilizzo avanzato con ambienti diversi:
 */

/*
// Rileva l'ambiente corrente
$is_production = (defined('WP_ENV') && WP_ENV === 'production');
$is_staging = (defined('WP_ENV') && WP_ENV === 'staging');

if ($is_production) {
    // Configurazione per produzione
    define('FP_GIT_UPDATER_BRANCH', 'main');
    define('FP_GIT_UPDATER_AUTO_UPDATE', true);
    define('FP_GIT_UPDATER_NOTIFICATIONS', true);
} elseif ($is_staging) {
    // Configurazione per staging
    define('FP_GIT_UPDATER_BRANCH', 'staging');
    define('FP_GIT_UPDATER_AUTO_UPDATE', true);
    define('FP_GIT_UPDATER_NOTIFICATIONS', false);
} else {
    // Configurazione per development
    define('FP_GIT_UPDATER_BRANCH', 'develop');
    define('FP_GIT_UPDATER_AUTO_UPDATE', false);
    define('FP_GIT_UPDATER_DEBUG', true);
}
*/

/**
 * Sicurezza delle costanti:
 * 
 * Se definisci le costanti in wp-config.php:
 * - Assicurati che wp-config.php NON sia accessibile via web
 * - NON committare wp-config.php nel repository
 * - Usa un file .env per valori sensibili
 * - Considera l'uso di vault per i segreti (es. AWS Secrets Manager)
 */
