# FP Git Updater

Plugin WordPress personalizzato per l'aggiornamento automatico da GitHub tramite webhook. Ogni volta che fai push o merge sul tuo repository GitHub, il plugin si aggiorna automaticamente su tutti i siti dove è installato.

## 🚀 Caratteristiche

- ✅ **Aggiornamento automatico da GitHub** tramite webhook
- ✅ **Supporto repository privati** con token di accesso
- ✅ **Sicurezza integrata** con secret key per webhook
- ✅ **Pannello di amministrazione** intuitivo per configurazione
- ✅ **Sistema di logging** completo per tracciare tutti gli aggiornamenti
- ✅ **Notifiche email** per aggiornamenti completati
- ✅ **Backup automatico** della versione precedente
- ✅ **Rollback sicuro** in caso di errori
- ✅ **Controlli periodici** per aggiornamenti (oltre ai webhook)
- ✅ **Interfaccia moderna** con dashboard WordPress

## 📋 Requisiti

- WordPress 5.0 o superiore
- PHP 7.4 o superiore
- Repository GitHub (pubblico o privato)
- Accesso alle impostazioni del repository GitHub per configurare webhook

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
   - Trova "FP Git Updater" e clicca su "Attiva"

## ⚙️ Configurazione

### 1. Configura il Plugin

1. Vai su **Git Updater** → **Impostazioni** nel menu WordPress
2. Compila i seguenti campi:
   - **Repository GitHub**: Il tuo repository nel formato `username/repository`
   - **Branch**: Il branch da cui scaricare gli aggiornamenti (default: `main`)
   - **GitHub Token** (opzionale): Necessario solo per repository privati
     - Vai su GitHub → Settings → Developer settings → Personal access tokens → Generate new token
     - Seleziona almeno lo scope `repo`
     - Copia il token generato
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
4. **Scarica l'ultima versione** dal repository
5. **Crea un backup** della versione attuale
6. **Installa la nuova versione** e verifica che funzioni
7. **Invia una notifica** via email (se abilitata)
8. **Logga tutto** nella sezione Log del plugin

## 📊 Monitoraggio

### Visualizza i Log

Vai su **Git Updater** → **Log** per vedere:
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

- ✅ **Webhook firmato**: Ogni richiesta webhook è verificata con HMAC SHA-256
- ✅ **Token sicuro**: Il token GitHub non viene mai esposto
- ✅ **Backup automatico**: Ogni aggiornamento crea un backup
- ✅ **Rollback automatico**: In caso di errore, viene ripristinata la versione precedente
- ✅ **Validazione input**: Tutti gli input sono sanitizzati
- ✅ **Permessi WordPress**: Solo gli amministratori possono accedere alle impostazioni

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

## 🔄 Aggiornamento del Plugin Stesso

Il plugin può aggiornare se stesso! Basta:
1. Configurare il repository di questo plugin
2. Ogni volta che fai push, tutti i siti si aggiorneranno automaticamente

## 📁 Struttura File

```
fp-git-updater/
├── fp-git-updater.php          # File principale del plugin
├── includes/
│   ├── class-webhook-handler.php   # Gestione webhook GitHub
│   ├── class-updater.php           # Sistema di aggiornamento
│   ├── class-admin.php             # Pannello amministrazione
│   └── class-logger.php            # Sistema di logging
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

Creato per uso personale - personalizza a piacere!

## 🆘 Supporto

Per supporto:
1. Controlla la sezione **Log** del plugin
2. Consulta questa documentazione
3. Verifica le "Recent Deliveries" del webhook su GitHub

## 🎉 Prossimi Miglioramenti

- [ ] Supporto per più repository
- [ ] Aggiornamento selettivo per sito
- [ ] Integrazione con CI/CD
- [ ] Dashboard statistiche
- [ ] Supporto tag/release specifiche
- [ ] API REST per controllo esterno

---

**Nota**: Assicurati sempre di testare gli aggiornamenti in un ambiente di staging prima di applicarli in produzione!
