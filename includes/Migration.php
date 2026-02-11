<?php
/**
 * Gestione Migrazioni
 * 
 * Gestisce la migrazione dei dati tra versioni del plugin
 */


namespace FP\GitUpdater;

if (!defined('ABSPATH')) {
    exit;
}

class Migration {
    
    private static $instance = null;
    
    /**
     * Versione corrente del database
     */
    private $db_version_key = 'fp_git_updater_db_version';
    
    /**
     * Versione corrente dello schema
     */
    private $current_version = '1.2.0';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_init', array($this, 'check_and_migrate'));
    }
    
    /**
     * Controlla se sono necessarie migrazioni
     */
    public function check_and_migrate() {
        $installed_version = get_option($this->db_version_key, '0.0.0');
        
        if (version_compare($installed_version, $this->current_version, '<')) {
            Logger::log('info', 'Inizio migrazione da versione ' . $installed_version . ' a ' . $this->current_version);
            
            $this->run_migrations($installed_version);
            
            // Aggiorna la versione installata
            update_option($this->db_version_key, $this->current_version);
            
            Logger::log('success', 'Migrazione completata a versione ' . $this->current_version);
        }
    }
    
    /**
     * Esegue le migrazioni necessarie
     */
    private function run_migrations($from_version) {
        // Migrazione per criptazione token (v1.2.0)
        if (version_compare($from_version, '1.2.0', '<')) {
            $this->migrate_to_1_2_0();
        }
        
        // Aggiungi qui altre migrazioni future
    }
    
    /**
     * Migrazione alla versione 1.2.0 - Criptazione token
     */
    private function migrate_to_1_2_0() {
        Logger::log('info', 'Migrazione 1.2.0: Criptazione token esistenti');
        
        try {
            $encryption = Encryption::get_instance();
            
            // Cripta token GitHub
            $tokens_migrated = $encryption->migrate_existing_tokens();
            
            // Cripta webhook secret
            $secret_migrated = $encryption->migrate_webhook_secret();
            
            if ($tokens_migrated || $secret_migrated) {
                Logger::log('success', 'Token e secret criptati con successo');
                
                // Mostra notifica admin
                add_action('admin_notices', function() {
                    ?>
                    <div class="notice notice-success is-dismissible">
                        <p>
                            <strong><?php _e('FP Updater:', 'fp-git-updater'); ?></strong> 
                            <?php _e('I tuoi token GitHub e webhook secret sono stati criptati per maggiore sicurezza.', 'fp-git-updater'); ?>
                        </p>
                    </div>
                    <?php
                });
            }
        } catch (\Exception $e) {
            Logger::log('error', 'Errore durante migrazione 1.2.0: ' . $e->getMessage());
        }
    }
    
    /**
     * Forza una migrazione specifica (per uso admin)
     */
    public function force_migration($version) {
        switch ($version) {
            case '1.2.0':
                $this->migrate_to_1_2_0();
                break;
            default:
                return false;
        }
        
        return true;
    }
    
    /**
     * Ottieni la versione corrente del database
     */
    public function get_db_version() {
        return get_option($this->db_version_key, '0.0.0');
    }
    
    /**
     * Ottieni la versione target
     */
    public function get_target_version() {
        return $this->current_version;
    }
}
