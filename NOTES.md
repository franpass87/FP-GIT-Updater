# Note Sviluppatore - FP Git Updater

## Architettura del Plugin

### Struttura delle Classi

```
FP_Git_Updater (main)
├── FP_Git_Updater_Webhook_Handler
│   ├── Registra endpoint REST API
│   ├── Verifica firma webhook
│   └── Triggera aggiornamenti
├── FP_Git_Updater_Updater
│   ├── Scarica da GitHub
│   ├── Gestisce backup
│   └── Installa nuova versione
├── FP_Git_Updater_Admin
│   ├── Interfaccia amministrazione
│   ├── Gestisce impostazioni
│   └── AJAX handlers
└── FP_Git_Updater_Logger
    ├── Salva log in database
    └── Query e gestione log
```

### Flusso di Aggiornamento

1. **GitHub Push** → Invia webhook
2. **WordPress REST API** → Riceve richiesta
3. **Webhook Handler** → Verifica firma HMAC
4. **Schedula Cron** → Esegue in background
5. **Updater** → Download + Backup + Installa
6. **Logger** → Registra tutto
7. **Email** → Notifica completamento

## Sicurezza

### Protezioni Implementate

1. **Verifica Webhook**
   - HMAC SHA-256 con secret
   - Prevenzione replay attacks

2. **WordPress Security**
   - Nonce per AJAX
   - Capability checks (manage_options)
   - Sanitizzazione input
   - Prepared statements database

3. **File Operations**
   - WP_Filesystem API
   - Backup automatico
   - Rollback su errore

### Best Practices

- ✅ Tutti gli input sanitizzati
- ✅ Output escaped
- ✅ Prepared statements SQL
- ✅ Nonce verification
- ✅ Capability checks
- ✅ No eval() o system()
- ✅ Validazione file types

## Performance

### Ottimizzazioni

1. **Cron Jobs**: Aggiornamenti in background
2. **Database**: Indici su colonne ricercate
3. **Pulizia**: Log e backup auto-eliminati
4. **Timeout**: 5 minuti per operazioni lunghe

### Carico Server

- Webhook: ~50ms (solo verifica)
- Download: ~10-30s (dipende da dimensione)
- Estrazione: ~5-10s
- Totale: ~20-45s per aggiornamento

## Database

### Tabella Log

```sql
CREATE TABLE wp_fp_git_updater_logs (
    id bigint(20) AUTO_INCREMENT PRIMARY KEY,
    log_date datetime DEFAULT CURRENT_TIMESTAMP,
    log_type varchar(50),
    message text,
    details longtext,
    KEY log_date (log_date),
    KEY log_type (log_type)
);
```

### Opzioni WordPress

- `fp_git_updater_settings` - Configurazione
- `fp_git_updater_current_commit` - SHA corrente
- `fp_git_updater_last_update` - Timestamp

## REST API

### Endpoint Webhook

```
POST /wp-json/fp-git-updater/v1/webhook
```

**Headers richiesti:**
- `X-GitHub-Event: push`
- `X-Hub-Signature-256: sha256=...`

**Payload:**
```json
{
  "ref": "refs/heads/main",
  "head_commit": {
    "id": "abc123...",
    "message": "Update feature",
    "author": {
      "name": "Username"
    }
  }
}
```

**Risposta successo:**
```json
{
  "success": true,
  "message": "Aggiornamento schedulato",
  "commit": "abc123"
}
```

## Cron Jobs

### Schedulati

1. `fp_git_updater_check_update`
   - Intervallo: configurabile (hourly/twicedaily/daily)
   - Azione: Controlla nuovi commit

2. `fp_git_updater_run_update`
   - Tipo: Single event
   - Azione: Esegue aggiornamento

3. `fp_git_updater_cleanup_backup`
   - Tipo: Single event (7 giorni dopo)
   - Azione: Elimina backup vecchi

## Testing

### Test Manuali Necessari

1. **Webhook**
   - ✅ Push su branch corretto
   - ✅ Push su branch diverso (ignorato)
   - ✅ Firma valida
   - ✅ Firma invalida (rifiutato)

2. **Aggiornamento**
   - ✅ Download completo
   - ✅ Backup creato
   - ✅ Installazione riuscita
   - ✅ Rollback su errore

3. **Interfaccia**
   - ✅ Salvataggio impostazioni
   - ✅ Test connessione
   - ✅ Aggiornamento manuale
   - ✅ Visualizzazione log
   - ✅ Pulizia log

### Casi Edge

- [ ] Repository inesistente
- [ ] Token scaduto
- [ ] Branch inesistente
- [ ] Connessione interrotta
- [ ] Disk space insufficiente
- [ ] Permessi file negati
- [ ] Plugin già aggiornato

## Estensibilità

### Hooks Personalizzabili

Puoi aggiungere hook per estendere il plugin:

```php
// Prima dell'aggiornamento
do_action('fp_git_updater_before_update', $commit_sha);

// Dopo aggiornamento riuscito
do_action('fp_git_updater_after_update', $commit_sha);

// Su errore
do_action('fp_git_updater_update_error', $error);

// Log personalizzati
apply_filters('fp_git_updater_log_message', $message, $type);
```

### Esempio Estensione

```php
// Nel tuo tema o plugin
add_action('fp_git_updater_after_update', function($commit) {
    // Pulisci cache
    wp_cache_flush();
    
    // Notifica Slack
    wp_remote_post('https://hooks.slack.com/...', [
        'body' => json_encode([
            'text' => "Plugin aggiornato al commit {$commit}"
        ])
    ]);
});
```

## Debug

### Modalità Debug

Abilita nel wp-config.php:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('FP_GIT_UPDATER_DEBUG', true);
```

### Log Location

- Plugin logs: Database `wp_fp_git_updater_logs`
- PHP errors: `wp-content/debug.log`
- Webhook deliveries: GitHub repository settings

### Troubleshooting Common

1. **"Webhook non ricevuto"**
   ```bash
   # Test endpoint manualmente
   curl -X POST https://tuosito.com/wp-json/fp-git-updater/v1/webhook \
     -H "Content-Type: application/json" \
     -H "X-GitHub-Event: ping" \
     -d '{"zen":"test"}'
   ```

2. **"Errore permessi"**
   ```bash
   # Verifica permessi
   ls -la /path/to/wp-content/plugins/fp-git-updater
   # Dovrebbe essere owned by www-data o l'utente del web server
   ```

3. **"Timeout durante download"**
   ```php
   // Aumenta in wp-config.php
   set_time_limit(300);
   ini_set('max_execution_time', 300);
   ```

## Roadmap Futuro

### v1.1 (Pianificato)
- [ ] Supporto GitHub releases
- [ ] Rollback UI a versione specifica
- [ ] Export/import configurazione

### v1.2 (Idea)
- [ ] Multi-repository support
- [ ] Conditional updates (solo certi file)
- [ ] API REST per controllo esterno

### v2.0 (Vision)
- [ ] GitLab/Bitbucket support
- [ ] Deploy pipeline integrato
- [ ] A/B testing versioni
- [ ] Network-wide updates (multisite)

## Compatibilità

### Testato Con

- ✅ WordPress 5.0 - 6.x
- ✅ PHP 7.4 - 8.2
- ✅ MySQL 5.6+
- ✅ MariaDB 10.1+

### Incompatibilità Note

- ❌ PHP < 7.4 (usa sintassi moderna)
- ❌ WordPress < 5.0 (REST API requirements)
- ⚠️ Shared hosting con limitazioni exec

## Contribuire

Vedi [CONTRIBUTING.md](CONTRIBUTING.md) per linee guida.

### Areas Needing Help

1. **Testing**: Più scenari edge case
2. **i18n**: Traduzioni in altre lingue
3. **Docs**: Video tutorial, screenshots
4. **Features**: Vedi roadmap sopra

## Licenza & Copyright

GPL v2 or later
Copyright (C) 2025

---

**Domande?** Apri una issue su GitHub!
