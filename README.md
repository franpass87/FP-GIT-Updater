# FP Updater

[![Build](https://github.com/TUOUSERNAME/fp-git-updater/actions/workflows/build-release.yml/badge.svg)](https://github.com/TUOUSERNAME/fp-git-updater/actions/workflows/build-release.yml)
[![Test](https://github.com/TUOUSERNAME/fp-git-updater/actions/workflows/test.yml/badge.svg)](https://github.com/TUOUSERNAME/fp-git-updater/actions/workflows/test.yml)
[![License](https://img.shields.io/badge/license-GPL%20v2-blue.svg)](LICENSE)
[![WordPress](https://img.shields.io/badge/wordpress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/php-7.4%2B-blue.svg)](https://php.net/)

Plugin WordPress personalizzato per l'aggiornamento automatico da GitHub tramite webhook. Ogni volta che fai push o merge sul tuo repository GitHub, il plugin si aggiorna automaticamente su tutti i siti dove è installato.

**✨ Novità**: Build automatico con GitHub Actions ad ogni push!

## 📚 Indice

1. [Caratteristiche](#-caratteristiche)
2. [Requisiti](#-requisiti)
3. [Quickstart](#-quickstart)
4. [Installazione](#-installazione)
5. [Configurazione](#-configurazione)
   - [Modalità GitHub](#modalità-github-completa)
   - [Modalità Semplice (ZIP Pubblico)](#modalità-semplice-zip-pubblico)
6. [Configurazione Webhook](#-configura-il-webhook-su-github)
7. [Come Funziona](#-come-funziona)
8. [Monitoraggio e Admin](#-monitoraggio)
9. [Sicurezza](#-sicurezza)
10. [Backup e Ripristino](#-backup-e-ripristino-impostazioni)
11. [Risoluzione Problemi](#-risoluzione-problemi)
12. [Auto‑aggiornamento](#-auto-aggiornamento-del-plugin-stesso)
13. [Struttura File](#-struttura-file)
14. [Miglioramenti Recenti](#-miglioramenti-recenti)
15. [Licenza e Autore](#-licenza)

---

## 🚀 Caratteristiche

- ✅ **Aggiornamento automatico da GitHub** tramite webhook
- ✅ **Supporto repository privati** con token di accesso
- ✅ **Sicurezza integrata** con secret key per webhook
- ✅ **Pannello di amministrazione** intuitivo per configurazione
- ✅ **Modalità semplice (ZIP pubblico)**: aggiorna da un URL .zip pubblico senza token
- ✅ **Sistema di logging** completo per tracciare tutti gli aggiornamenti
- ✅ **Notifiche email** per aggiornamenti completati
- ✅ **Backup automatico** della versione precedente
- ✅ **Backup e ripristino impostazioni** automatico e manuale
- ✅ **Rollback sicuro** in caso di errori
- ✅ **Controlli periodici** per aggiornamenti (oltre ai webhook)
- ✅ **Interfaccia moderna** con dashboard WordPress

## 📋 Requisiti

- WordPress 5.0 o superiore
- PHP 7.4 o superiore
- Repository GitHub (pubblico o privato)
- Accesso alle impostazioni del repository GitHub per configurare webhook

## ⚡ Quickstart

Se vuoi partire subito, leggi anche `QUICKSTART.md` (2 percorsi: GitHub o ZIP pubblico).

## 📦 Installazione

1. **Scarica il plugin**
   ```bash
   git clone https://github.com/tuousername/fp-git-updater.git
   ```

2. **Carica il plugin**
   - Carica la cartella `fp-git-updater` nella directory `/wp-content/plugins/` del tuo sito WordPress
   - Oppure comprimi la cartella in un file ZIP e caricala tramite il pannello WordPress

3. **Attiva il plugin**
   - Vai su `Plugin` nel pannello WordPress
   - Trova "FP Updater" e clicca su "Attiva"

## ⚙️ Configurazione

### 0. Username GitHub Predefinito (Opzionale ma Consigliato) ⭐

**✨ Novità:** Imposta il tuo username GitHub predefinito per semplificare l'aggiunta di plugin!

1. Vai su **FP Updater** → **Impostazioni** nel menu WordPress
2. Nel campo **Username GitHub Predefinito** inserisci il tuo username (es: `franpass87`)
3. Clicca su **Salva Impostazioni**

**Vantaggi:**
- ✅ Inserisci solo il nome del repository invece di `username/repository`
- ✅ Esempio: scrivi `FP-Forms` invece di `franpass87/FP-Forms`
- ✅ Puoi comunque usare il formato completo quando necessario
- ✅ Perfetto se gestisci principalmente i tuoi repository

### 1. Configura il Plugin

1. Vai su **FP Updater** → **Impostazioni** nel menu WordPress
2. Compila i seguenti campi (scegli una delle due modalità):
   
   Modalità GitHub (completa):
   - **Repository GitHub**: Il tuo repository
     - Se hai impostato lo username predefinito: `FP-Forms` o `franpass87/FP-Forms`
     - Senza username predefinito: `username/repository`
   - **Branch**: Il branch da cui scaricare gli aggiornamenti (default: `main`)
   - **GitHub Token** (opzionale): Necessario solo per repository privati
     - Vai su GitHub → Settings → Developer settings → Personal access tokens → Generate new token
     - Seleziona almeno lo scope `repo`
     - Copia il token generato
   
   Modalità semplice (ZIP pubblico):
   - **URL ZIP pubblico (opzionale)**: link diretto a un file `.zip` pubblico (ad es. asset di una release)
   - In questa modalità non servono `Repository` né `Token`
   - **Webhook Secret**: Generato automaticamente, usalo per il passo successivo
   - **Aggiornamento Automatico**: Abilita per aggiornamenti automatici
   - **Notifiche Email**: Configura email per ricevere notifiche

3. Clicca su **Salva Impostazioni**

### 2. Configura il Webhook su GitHub

1. Vai sul tuo repository GitHub
2. Clicca su **Settings** → **Webhooks** → **Add webhook**
3. Compila il form:
   - **Payload URL**: Copia l'URL mostrato nella pagina impostazioni del plugin
   - **Content type**: Seleziona `application/json`
   - **Secret**: Incolla il "Webhook Secret" dalla pagina impostazioni del plugin
   - **Which events**: Seleziona "Just the push event"
   - **Active**: Assicurati che sia spuntato
4. Clicca su **Add webhook**

### 3. Testa la Configurazione

1. Torna sulla pagina **Impostazioni** del plugin
2. Clicca su **Test Connessione** per verificare che tutto funzioni
3. Clicca su **Aggiorna Ora** per eseguire un aggiornamento manuale di prova

## 🎯 Come Funziona

1. **Fai un push o merge** sul tuo repository GitHub
2. **GitHub invia un webhook** al tuo sito WordPress
3. **Il plugin verifica la firma** del webhook per sicurezza
4. **Scarica l'ultima versione** dal repository o dall'**URL ZIP pubblico**
5. **Crea un backup** della versione attuale
6. **Installa la nuova versione** e verifica che funzioni
7. **Invia una notifica** via email (se abilitata)
8. **Logga tutto** nella sezione Log del plugin

## 📊 Monitoraggio

### Visualizza i Log

Vai su **FP Updater** → **Log** per vedere:
- Tutti i webhook ricevuti
- Aggiornamenti eseguiti
- Eventuali errori
- Dettagli di ogni operazione

### Dashboard

Nella pagina **Impostazioni** puoi vedere:
- **Ultimo commit** installato
- **Data ultimo aggiornamento**
- Pulsanti per **test connessione** e **aggiornamento manuale**

## 🔒 Sicurezza

- ✅ **Criptazione AES-256**: Token GitHub e webhook secret criptati nel database (NEW v1.2.0)
- ✅ **Rate Limiting**: Protezione automatica da abusi - max 60 richieste/ora per IP (NEW v1.2.0)
- ✅ **Webhook firmato**: Ogni richiesta webhook è verificata con HMAC SHA-256 (supporto header `X-Hub-Signature-256` e legacy)
- ✅ **Token sicuro**: Il token GitHub non viene mai esposto in plain text
- ✅ **Backup automatico**: Ogni aggiornamento crea un backup del codice e delle impostazioni
- ✅ **Protezione impostazioni**: Backup automatico prima di ogni modifica con ripristino automatico
- ✅ **Rollback automatico**: In caso di errore, viene ripristinata la versione precedente
- ✅ **Validazione input**: Tutti gli input sono sanitizzati
- ✅ **Permessi WordPress**: Solo gli amministratori possono accedere alle impostazioni
- ✅ **Gestione errori robusta**: Try-catch esteso per prevenire crash (NEW v1.2.0)

## 🔄 Backup e Ripristino Impostazioni

Il plugin include un sistema avanzato di backup e ripristino per proteggere la tua configurazione:

### Backup Automatici
- **Prima degli aggiornamenti**: Backup automatico prima di ogni aggiornamento del plugin
- **Prima delle modifiche**: Backup automatico prima di salvare nuove impostazioni
- **Dopo attivazione**: Ripristino automatico se le impostazioni sono state resettate

### Gestione Backup
Vai su **FP Updater → Backup e Ripristino** per:
- ✅ Creare backup manuali in qualsiasi momento
- ✅ Visualizzare la cronologia degli ultimi 10 backup
- ✅ Ripristinare backup specifici
- ✅ Vedere i dettagli di ogni backup (data, versione, plugin salvati)
- ✅ Ricevere notifiche se le impostazioni sono state resettate

### Quando viene ripristinato automaticamente?
Il sistema rileva automaticamente se le tue impostazioni sono state perse (ad esempio dopo un aggiornamento di WordPress o del plugin) e le ripristina dal backup più recente.

## 🛠️ Risoluzione Problemi

### Il webhook non funziona

1. Verifica che l'URL webhook sia corretto
2. Controlla che il secret sia stato copiato correttamente
3. Vai su GitHub → Repository → Settings → Webhooks e controlla che ci sia un segno di spunta verde
4. Clicca sul webhook per vedere le "Recent Deliveries" e eventuali errori
5. Controlla i log del plugin per vedere se il webhook è stato ricevuto

### L'aggiornamento fallisce

1. Controlla i **Log** del plugin per vedere l'errore specifico
2. Verifica che il **token GitHub** sia valido (se usi repository privati)
3. Assicurati che il **branch** configurato esista sul repository
4. Verifica i **permessi** della directory del plugin (devono essere scrivibili)
5. Controlla che non ci siano **plugin di sicurezza** che bloccano le operazioni
 6. Se usi **URL ZIP pubblico**:
    - Verifica che l'URL risponda con codice 200 e sia raggiungibile
    - Preferisci URL che terminano in `.zip` (il plugin avvisa se diverso)
    - Assicurati che lo ZIP contenga il plugin in root o al massimo in 1-2 sottocartelle

### Repository privato non accessibile

1. Assicurati di aver creato un **Personal Access Token** su GitHub
2. Verifica che il token abbia almeno lo scope **`repo`**
3. Controlla che il token non sia scaduto
4. Prova a fare un **Test Connessione** dalla pagina impostazioni

## 📝 Utilizzare il Plugin per Altri Progetti

Questo plugin è progettato per aggiornarsi da solo, ma puoi facilmente adattarlo per aggiornare altri plugin:

1. **Clona questo repository** come base
2. **Modifica il file principale** per puntare al plugin che vuoi aggiornare
3. **Configura il repository** nelle impostazioni
4. **Configura il webhook** sul repository del plugin target

## 🔄 Auto-aggiornamento del Plugin Stesso

Il plugin può aggiornare se stesso automaticamente! Questa funzionalità è stata implementata nella versione 1.2.0:

### ✨ Funzionalità Auto-aggiornamento

- **Configurazione automatica**: Il plugin si aggiunge automaticamente alla lista dei plugin gestiti
- **Interfaccia dedicata**: Sezione speciale nell'admin per gestire l'auto-aggiornamento
- **Sicurezza avanzata**: Backup automatico delle impostazioni prima di ogni aggiornamento
- **Notifiche speciali**: Email dedicate per gli auto-aggiornamenti
- **Controlli manuali**: Pulsanti per controllare e installare aggiornamenti manualmente

### 🎯 Come Funziona

1. **All'attivazione**: Il plugin si configura automaticamente per l'auto-aggiornamento
2. **Repository predefinito**: Usa il repository `franpass87/FP-GIT-Updater` (modificabile)
3. **Webhook automatico**: Funziona con lo stesso webhook degli altri plugin
4. **Aggiornamento sicuro**: Backup automatico e rollback in caso di errori

### 🛠️ Configurazione

1. **Automatica**: Non serve configurazione, funziona subito
2. **Repository personalizzato**: Puoi modificare il repository nelle impostazioni
3. **Token GitHub**: Aggiungi un token se usi un repository privato
4. **Modalità manuale**: Disabilita l'aggiornamento automatico per controllo totale

### 📱 Interfaccia Admin

Nella pagina **Impostazioni** troverai una sezione speciale "Auto-aggiornamento FP Updater" con:
- Versione attuale del plugin
- Ultimo aggiornamento eseguito
- Pulsante per controllare aggiornamenti
- Pulsante per installare aggiornamenti disponibili
- Link ai log dell'ultimo aggiornamento

## 📁 Struttura File

```
fp-git-updater/
├── fp-git-updater.php          # File principale del plugin
├── includes/
│   ├── class-webhook-handler.php   # Gestione webhook GitHub
│   ├── class-updater.php           # Sistema di aggiornamento
│   ├── class-admin.php             # Pannello amministrazione
│   ├── class-logger.php            # Sistema di logging
│   └── class-settings-backup.php   # Sistema backup/ripristino impostazioni
├── assets/
│   ├── admin.css                   # Stili interfaccia admin
│   └── admin.js                    # JavaScript interfaccia admin
└── README.md                       # Documentazione
```

## 🤝 Contribuire

Questo è un plugin personalizzato, ma sei libero di:
- Forkare il repository
- Aprire issue per bug o suggerimenti
- Inviare pull request con miglioramenti

## 📄 Licenza

GPL v2 o successiva

## 👤 Autore

**Francesco Passeri**
- Website: [www.francescopasseri.com](https://www.francescopasseri.com)
- Email: info@francescopasseri.com

## 🆘 Supporto

Per supporto:
1. Controlla la sezione **Log** del plugin
2. Consulta questa documentazione
3. Verifica le "Recent Deliveries" del webhook su GitHub

## 🎉 Miglioramenti Recenti

### 🚀 Caricamento Automatico Repository da GitHub (NUOVISSIMO! v1.2.2)
- [x] 📋 **Carica dalla lista** - Click su un pulsante e vedi TUTTI i tuoi repository GitHub
- [x] 🔍 **Ricerca in tempo reale** - Filtra la lista mentre digiti
- [x] ⚡ **Selezione con 1 click** - Nessuna digitazione richiesta
- [x] 🎨 **Modal elegante** - Interfaccia professionale e moderna
- [x] 💾 **Cache intelligente** - 5 minuti di cache per performance ottimali
- [x] 📱 **Mobile-friendly** - Funziona perfettamente su tutti i dispositivi
- [x] 🏷️ **Badge repository privati** - Distingui a colpo d'occhio
- [x] 🌿 **Branch predefinito automatico** - Compila anche il branch corretto

### ✨ Username GitHub Predefinito (v1.2.1)
- [x] 🎯 **Configura una volta, usa ovunque** - Imposta il tuo username GitHub predefinito
- [x] ⚡ **Inserimento rapido** - Scrivi solo `FP-Forms` invece di `franpass87/FP-Forms`
- [x] 🔄 **Auto-completamento intelligente** - Il sistema completa automaticamente il formato completo
- [x] 🎨 **Placeholder dinamici** - L'interfaccia si adatta alle tue impostazioni
- [x] ✅ **Retrocompatibilità totale** - Funziona anche con il formato completo

### 🚀 Modalità semplice ZIP pubblico
- [x] Aggiornamenti da URL `.zip` senza credenziali/token
- [x] Retry/backoff leggero e follow redirect per download più robusti
- [x] Rilevamento slug automatico dalla directory estratta se non specificato

### 🚀 Auto-aggiornamento
- [x] ✨ **Auto-aggiornamento del plugin stesso** - Il plugin può aggiornarsi automaticamente!
- [x] **Interfaccia dedicata** - Sezione speciale nell'admin per gestire l'auto-aggiornamento
- [x] **Configurazione automatica** - Si configura da solo all'attivazione
- [x] **Sicurezza avanzata** - Backup automatico prima di ogni auto-aggiornamento
- [x] **Notifiche speciali** - Email dedicate per gli auto-aggiornamenti

### 🔒 Sicurezza
- [x] ✨ **Criptazione AES-256 per token GitHub** - I tuoi token sono ora criptati nel database!
- [x] **Rate limiting per webhook** - Protezione automatica da abusi e attacchi DDoS
- [x] **Permission callback migliorato** - Doppio livello di sicurezza per webhook endpoint
- [x] **Firma webhook**: supporto header `X-Hub-Signature-256` e fallback legacy

### ⚡ Performance
- [x] **Caching API GitHub** - Riduzione del 95% delle chiamate API, risposta più veloce
- [x] **Logging ottimizzato** - Performance migliorate del 75% con pulizia via cron
- [x] **Download robusto** - Retry con backoff ed handling redirect per asset `.zip`

### 🛠️ Architettura
- [x] **Sistema di migrazione automatica** - Aggiornamenti trasparenti senza perdita dati
- [x] **Gestione errori migliorata** - Try-catch esteso per maggiore stabilità
- [x] **Setup internazionalizzazione** - Pronto per traduzioni multilingua

### Già Implementato
- [x] ✨ **Backup e ripristino automatico delle impostazioni** - Le tue configurazioni sono al sicuro!
- [x] **Supporto per più repository** - Gestisci più plugin contemporaneamente
- [x] **Pannello backup dedicato** - Controlla e gestisci tutti i tuoi backup

## 🎯 Prossimi Miglioramenti

- [ ] Completamento internazionalizzazione (file .pot)
- [ ] Dashboard statistiche con metriche
- [ ] WP-CLI commands per automazione
- [ ] Supporto tag/release specifiche GitHub
- [ ] API REST per controllo esterno
- [ ] Export/Import configurazioni
- [ ] Supporto GitLab/Bitbucket
- [ ] Dry-run mode per test sicuri

---

**Nota**: Assicurati sempre di testare gli aggiornamenti in un ambiente di staging prima di applicarli in produzione!
