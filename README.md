# FP Updater

Sistema di aggiornamento automatico plugin WordPress da GitHub. Gestisce aggiornamenti, backup, deploy remoto su clienti e auto-aggiornamento.

[![Version](https://img.shields.io/badge/version-1.6.8-blue.svg)](https://github.com/franpass87/FP-GIT-Updater)
[![License](https://img.shields.io/badge/license-GPL%20v2-blue.svg)](LICENSE)

---

## Per l'utente

### Cosa fa
FP Updater è il sistema centrale di aggiornamento dell'ecosistema FP. Permette di:
- Aggiornare automaticamente i plugin FP da GitHub
- Gestire i **siti client** collegati tramite FP Remote Bridge
- Fare **deploy remoto** di plugin sui siti client con un click
- Monitorare le versioni installate su tutti i clienti
- Gestire backup automatici prima di ogni aggiornamento

### Configurazione
1. Vai su **FP Updater** nel menu admin
2. Inserisci il tuo **GitHub Token** (Personal Access Token con scope `repo`)
3. I plugin FP vengono rilevati automaticamente
4. Per collegare un sito client: vai su **Clienti** e aggiungi URL + credenziali

### Deploy remoto
1. Vai su **Deploy Plugin**
2. Seleziona il plugin da deployare
3. Seleziona i clienti destinatari
4. Clicca **Sincronizza versioni** per aggiornare

### Requisiti
- WordPress 6.0+
- PHP 8.0+
- Token GitHub con accesso ai repo privati
- FP Remote Bridge sui siti client

---

## Per lo sviluppatore

### Struttura
```
fp-git-updater/
├── fp-git-updater.php          # File principale
├── src/
│   ├── Core/Plugin.php         # Bootstrap
│   ├── Updater/
│   │   ├── GitHubUpdater.php   # Logica aggiornamento da GitHub
│   │   └── SelfUpdater.php     # Auto-aggiornamento FP Updater stesso
│   ├── Admin/
│   │   ├── Dashboard.php       # Dashboard principale
│   │   ├── ClientsPage.php     # Gestione clienti
│   │   └── DeployPage.php      # Deploy remoto
│   ├── Master/
│   │   ├── MasterSync.php      # Sincronizzazione con client
│   │   └── ClientManager.php   # Gestione lista clienti
│   ├── Backup/
│   │   └── BackupManager.php   # Backup prima degli update
│   ├── Security/
│   │   ├── Encryption.php      # Cifratura credenziali
│   │   └── RateLimiter.php     # Rate limiting API
│   └── Migration/
│       └── MigrationManager.php # Migrazioni DB
├── assets/
│   ├── admin.css
│   └── admin.js
└── vendor/
```

### Flusso aggiornamento
1. FP Updater controlla GitHub per nuove release (cron ogni ora)
2. Se disponibile, scarica il `.zip` dalla release GitHub
3. Crea backup del plugin corrente
4. Installa la nuova versione tramite `WP_Upgrader`
5. Notifica i client collegati tramite `trigger-sync`

### Flusso deploy remoto
1. Admin seleziona plugin + clienti
2. FP Updater chiama `/wp-json/fp-bridge/v1/install` su ogni client
3. FP Remote Bridge scarica e installa il plugin
4. Bridge chiama `/plugin-versions` per aggiornare le versioni in UI

### Sicurezza
- Credenziali client cifrate in DB con `Encryption.php`
- Rate limiting su tutte le chiamate API
- Lock anti-ricorsione per self-update
- Auto-sblocco lock orfani dopo 15 minuti

### Hooks disponibili
| Hook | Tipo | Descrizione |
|------|------|-------------|
| `fp_updater_before_update` | action | Prima di ogni aggiornamento |
| `fp_updater_after_update` | action | Dopo aggiornamento completato |
| `fp_updater_plugins` | filter | Modifica lista plugin monitorati |

---

## Changelog
Vedi [CHANGELOG.md](CHANGELOG.md)
