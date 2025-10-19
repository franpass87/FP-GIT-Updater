# Changelog v1.2.0 - Auto-aggiornamento

## 🚀 Nuove Funzionalità

### Auto-aggiornamento del Plugin
- **Configurazione automatica**: Il plugin si aggiunge automaticamente alla lista dei plugin gestiti all'attivazione
- **Interfaccia dedicata**: Nuova sezione nell'admin per gestire l'auto-aggiornamento con design moderno
- **Sicurezza avanzata**: Backup automatico delle impostazioni prima di ogni auto-aggiornamento
- **Notifiche speciali**: Email dedicate per gli auto-aggiornamenti con messaggi personalizzati
- **Controlli manuali**: Pulsanti per controllare e installare aggiornamenti manualmente

### Miglioramenti Interfaccia
- **Sezione auto-aggiornamento**: Design moderno con gradiente e informazioni dettagliate
- **Stato aggiornamento**: Visualizzazione chiara della versione attuale e ultimo aggiornamento
- **Controlli intuitivi**: Pulsanti per controllare aggiornamenti e installare manualmente
- **Feedback visivo**: Animazioni e notifiche per migliorare l'esperienza utente

## 🔧 Modifiche Tecniche

### File Modificati
- `fp-git-updater.php`: Aggiunto metodo `init_self_update()` e configurazione automatica
- `includes/class-updater.php`: Aggiunto metodo `run_self_update()` per gestione sicura
- `includes/class-admin.php`: Aggiunti handler AJAX per auto-aggiornamento
- `includes/admin-templates/settings-page.php`: Integrata sezione auto-aggiornamento
- `README.md`: Documentazione completa della nuova funzionalità

### File Aggiunti
- `includes/admin-templates/partials/self-update-section.php`: Template per sezione auto-aggiornamento
- `test-self-update.php`: File di test per verificare la funzionalità
- `CHANGELOG_v1.2.0.md`: Questo changelog

### Configurazione Automatica
- Repository predefinito: `franpass87/FP-GIT-Updater`
- ID plugin: `fp_git_updater_self`
- Nome: `FP Git Updater (Auto-aggiornamento)`
- Branch: `main`
- Abilitato: `true` (di default)

## 🛡️ Sicurezza

### Backup Automatico
- Backup delle impostazioni prima di ogni auto-aggiornamento
- Ripristino automatico in caso di errori
- Logging dettagliato di tutte le operazioni

### Gestione Errori
- Try-catch esteso per prevenire crash
- Rollback automatico in caso di problemi
- Notifiche email per errori critici

## 📱 Interfaccia Utente

### Sezione Auto-aggiornamento
- **Design moderno**: Gradiente blu-viola con icone
- **Informazioni dettagliate**: Versione attuale, ultimo aggiornamento
- **Stato aggiornamento**: Indicatori visivi per aggiornamenti disponibili
- **Controlli intuitivi**: Pulsanti per controllare e installare aggiornamenti

### Funzionalità JavaScript
- Controllo aggiornamenti via AJAX
- Installazione aggiornamenti con feedback
- Ricaricamento automatico dopo aggiornamento
- Gestione errori con notifiche

## 🔄 Compatibilità

### WordPress
- Compatibile con WordPress 5.0+
- Testato con le ultime versioni

### PHP
- Richiede PHP 7.4+
- Compatibile con PHP 8.x

### Browser
- Supporta tutti i browser moderni
- JavaScript ES5+ per compatibilità

## 📋 Istruzioni per l'Uso

### Attivazione
1. Il plugin si configura automaticamente all'attivazione
2. Vai su **Git Updater → Impostazioni**
3. Trova la sezione "Auto-aggiornamento FP Git Updater"
4. Configura il repository se necessario

### Configurazione Repository
1. Modifica il repository nelle impostazioni se diverso da quello predefinito
2. Aggiungi un token GitHub se usi un repository privato
3. Configura il webhook sul repository GitHub

### Aggiornamento Manuale
1. Clicca su "Controlla Aggiornamenti" per verificare disponibilità
2. Se disponibile, clicca su "Installa Aggiornamento Ora"
3. Attendi il completamento e ricaricamento automatico

## 🐛 Risoluzione Problemi

### Plugin non si auto-configura
- Verifica che il plugin sia attivato correttamente
- Controlla i log per errori di configurazione
- Disattiva e riattiva il plugin

### Aggiornamento fallisce
- Verifica i permessi della directory del plugin
- Controlla che il repository sia accessibile
- Verifica il token GitHub se necessario

### Interfaccia non si aggiorna
- Ricarica la pagina delle impostazioni
- Controlla la console del browser per errori JavaScript
- Verifica che i file CSS/JS siano caricati correttamente

## 🔮 Prossimi Sviluppi

### Funzionalità Pianificate
- Dashboard statistiche per auto-aggiornamenti
- Cronologia dettagliata degli aggiornamenti
- Notifiche push per aggiornamenti disponibili
- Supporto per repository multipli per auto-aggiornamento

### Miglioramenti Tecnici
- Ottimizzazione performance per repository grandi
- Cache intelligente per controlli aggiornamenti
- Integrazione con sistema di notifiche WordPress
- API REST per controllo esterno

---

**Data Rilascio**: $(date)  
**Versione**: 1.2.0  
**Autore**: Francesco Passeri  
**Compatibilità**: WordPress 5.0+, PHP 7.4+
