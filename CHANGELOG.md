# Changelog

Tutte le modifiche importanti a questo progetto saranno documentate in questo file.

Il formato è basato su [Keep a Changelog](https://keepachangelog.com/it/1.0.0/),
e questo progetto aderisce a [Semantic Versioning](https://semver.org/lang/it/).

## [1.1.0] - 2025-10-11

### 🎯 Aggiunto
- **Sistema di Aggiornamento Manuale Sicuro**: Implementato sistema completo di "pending updates" per proteggere i siti di produzione
  - Gli aggiornamenti disponibili vengono registrati ma NON installati automaticamente quando `auto_update` è disabilitato
  - Badge di notifica nel menu WordPress mostra il numero di aggiornamenti disponibili
  - Banner prominente nella pagina delle impostazioni elenca tutti gli aggiornamenti pending
  - Indicatori visivi (badge rossi, bordi colorati, animazioni) sui plugin con aggiornamenti disponibili
  - Informazioni dettagliate per ogni aggiornamento: commit SHA, messaggio, autore, timestamp

### 🔒 Migliorato
- **Sicurezza**: Default `auto_update` cambiato a `false` per nuove installazioni (maggiore controllo)
- **Interfaccia Utente**: Completamente rinnovata con notifiche chiare e indicatori visivi
  - Modalità manuale: badge verde con icona shield
  - Modalità automatica: avviso giallo di attenzione
  - Pulsante "Installa Aggiornamento" con animazione pulse quando ci sono aggiornamenti
- **Documentazione**: Istruzioni chiare su entrambe le modalità (manuale vs automatica)
- **Logging**: Log più dettagliati che distinguono tra aggiornamenti automatici e notifiche

### 🛠️ Modificato
- **class-webhook-handler.php**: Ora registra sempre gli aggiornamenti come pending prima di decidere se installarli
- **class-updater.php**: Aggiunto sistema di tracking degli aggiornamenti pending con metodi dedicati
- **class-admin.php**: Interfaccia completamente aggiornata con visualizzazione pending updates
- **Versione plugin**: 1.0.0 → 1.1.0

### 🎨 CSS
- Aggiunta animazione `@keyframes pulse` per effetti visivi
- Nuovi stili per badge update-count
- Evidenziazione plugin con aggiornamenti pending

### ✅ Compatibilità
- Retrocompatibile con installazioni esistenti
- Le impostazioni `auto_update` esistenti vengono preservate durante l'upgrade
- Nessuna breaking change

### 📝 Note per gli Sviluppatori
- Nuovi metodi API disponibili: `get_pending_updates()`, `clear_pending_update($plugin_id)`
- I pending updates sono salvati come option WordPress: `fp_git_updater_pending_update_{plugin_id}`
- Viene automaticamente rimosso il pending update dopo installazione riuscita

## [1.0.0] - 2025-10-11

### Aggiunto
- Plugin base con sistema di aggiornamento automatico da GitHub
- Gestione webhook GitHub per push events
- Sistema di verifica firma webhook con HMAC SHA-256
- Pannello amministrazione completo con:
  - Configurazione repository e credenziali
  - Test connessione GitHub
  - Aggiornamento manuale con un click
  - Visualizzazione stato attuale e ultimo commit
- Sistema di logging completo:
  - Salvataggio log nel database
  - Visualizzazione log nel pannello admin
  - Pulizia automatica log vecchi
  - Filtri per tipo di log
- Sistema di notifiche email per:
  - Inizio aggiornamento
  - Completamento aggiornamento
  - Errori durante l'aggiornamento
- Backup automatico prima di ogni aggiornamento
- Rollback automatico in caso di errore
- Supporto per repository privati tramite Personal Access Token
- Controlli periodici per aggiornamenti (oltre ai webhook)
- Interfaccia utente moderna con:
  - Stili personalizzati
  - JavaScript interattivo
  - Notifiche in-page
  - Animazioni e feedback visivi
- Sicurezza:
  - Sanitizzazione di tutti gli input
  - Verifiche permessi utente
  - Nonce CSRF per richieste AJAX
  - Protezione contro accesso diretto ai file
- Documentazione completa:
  - README.md con guida completa
  - INSTALL.md con guida installazione passo-passo
  - Commenti nel codice
  - Istruzioni in-app nel pannello admin

### Funzionalità Tecniche
- Utilizzo WordPress REST API per endpoint webhook
- Sistema di cron job WordPress per aggiornamenti schedulati
- Utilizzo WP_Filesystem per operazioni su file sicure
- Supporto unzip per estrarre archivi GitHub
- Gestione errori completa con WP_Error
- Logging strutturato in database
- AJAX per operazioni asincrone nell'admin
- Pulizia automatica di backup vecchi

### Note di Sicurezza
- Tutti gli input sono sanitizzati
- Webhook verificato con firma HMAC SHA-256
- Token GitHub memorizzato in modo sicuro
- Solo amministratori possono accedere alle impostazioni
- Protezione CSRF su tutte le operazioni AJAX
- Validazione completa dei dati del webhook

## [Unreleased]

### Aggiunto
- ✨ **Sistema di backup e ripristino automatico delle impostazioni**
  - Backup automatico prima di ogni aggiornamento del plugin
  - Backup automatico prima di salvare nuove impostazioni
  - Ripristino automatico se le impostazioni vengono resettate dopo un aggiornamento
  - Nuova pagina "Backup e Ripristino" nel pannello admin
  - Cronologia degli ultimi 10 backup con dettagli completi
  - Possibilità di creare backup manuali
  - Ripristino da backup specifici
  - Notifiche automatiche se le impostazioni sono state resettate
- Nuova classe `FP_Git_Updater_Settings_Backup` per gestire backup e ripristini
- Hook `upgrader_process_complete` per backup pre-aggiornamento
- Hook `activated_plugin` per ripristino automatico post-attivazione
- AJAX handlers per operazioni di backup (crea, ripristina, elimina)

### Modificato
- Metodo `activate()` ora controlla e ripristina automaticamente le impostazioni dal backup
- Metodo `sanitize_settings()` ora crea un backup prima di salvare modifiche
- Documentazione aggiornata con sezione dedicata al sistema di backup

### Corretto
- **Problema principale risolto**: Le impostazioni non vengono più perse dopo gli aggiornamenti
- Protezione completa della configurazione dei plugin

## [1.0.0] - 2025-10-11

### Pianificato per versioni future
- [ ] Supporto per più repository contemporaneamente
- [ ] Aggiornamento selettivo per sito specifico
- [ ] Integrazione con sistemi CI/CD
- [ ] Dashboard con statistiche e grafici
- [ ] Supporto per tag e release specifiche di GitHub
- [ ] API REST per controllo esterno degli aggiornamenti
- [ ] Notifiche Slack/Discord/Telegram
- [ ] Modalità dry-run per testare aggiornamenti
- [ ] Snapshot database prima dell'aggiornamento
- [ ] Ripristino completo (codice + database)
- [ ] Gestione dipendenze e compatibilità
- [ ] Test automatici post-aggiornamento
- [ ] Staging environment integrato
- [ ] Rollback a versioni specifiche
- [ ] Aggiornamenti schedulati a orari prestabiliti

---

## Formato Changelog

### Tipi di modifiche
- **Aggiunto** per nuove funzionalità
- **Modificato** per modifiche a funzionalità esistenti
- **Deprecato** per funzionalità che saranno rimosse
- **Rimosso** per funzionalità rimosse
- **Corretto** per bug fix
- **Sicurezza** per vulnerabilità corrette

### Versionamento
- **MAJOR** (X.0.0): Modifiche incompatibili con versioni precedenti
- **MINOR** (0.X.0): Nuove funzionalità compatibili con versioni precedenti  
- **PATCH** (0.0.X): Bug fix compatibili con versioni precedenti
