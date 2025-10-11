# Changelog

Tutte le modifiche importanti a questo progetto saranno documentate in questo file.

Il formato è basato su [Keep a Changelog](https://keepachangelog.com/it/1.0.0/),
e questo progetto aderisce a [Semantic Versioning](https://semver.org/lang/it/).

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
