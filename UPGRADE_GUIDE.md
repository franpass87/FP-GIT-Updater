# Guida all'Aggiornamento - FP Git Updater v1.2.0

## 📋 Panoramica

Questa guida descrive come aggiornare dalla versione precedente alla v1.2.0 e cosa aspettarsi.

---

## ✅ Processo di Aggiornamento Automatico

### Cosa Succede Automaticamente

1. **Backup Automatico**
   - Prima dell'aggiornamento, viene creato un backup delle impostazioni correnti
   - Il backup include tutti i plugin configurati e le impostazioni

2. **Migrazione Dati**
   - All'attivazione del plugin aggiornato, viene eseguita automaticamente la migrazione
   - I token GitHub esistenti vengono **criptati con AES-256**
   - Il webhook secret viene **criptato**
   - Nessun dato viene perso o modificato (solo criptato)

3. **Notifica Admin**
   - Dopo la migrazione, vedrai una notifica nell'admin di WordPress
   - Conferma che i token sono stati criptati con successo

### Nessuna Azione Richiesta

✅ L'aggiornamento è **completamente automatico**  
✅ **Zero downtime** durante l'upgrade  
✅ **Retrocompatibile** con installazioni esistenti  
✅ **Rollback automatico** in caso di problemi  

---

## 🔍 Verifica Post-Aggiornamento

Dopo l'aggiornamento, verifica quanto segue:

### 1. Controlla i Log
Vai su **Git Updater → Log** e verifica:
- Presenza del messaggio: "Token e secret criptati con successo"
- Nessun errore durante la migrazione

### 2. Testa la Connessione GitHub
Per ogni plugin configurato:
1. Vai su **Git Updater → Impostazioni**
2. Clicca su **Controlla Aggiornamenti** per ogni plugin
3. Verifica che la connessione funzioni correttamente

### 3. Testa il Webhook
1. Fai un commit di test sul tuo repository GitHub
2. Vai su GitHub → Repository → Settings → Webhooks
3. Controlla la "Recent Deliveries"
4. Verifica che la risposta sia 200 OK

---

## 🆕 Nuove Funzionalità Disponibili

### Rate Limiting
Il webhook è ora protetto da rate limiting:
- **Limite**: 60 richieste per ora per IP
- **Automatico**: Non richiede configurazione
- **Logging**: Tentativi di abuso vengono loggati

### Caching API
Le chiamate API GitHub sono ora automaticamente cachate:
- **Durata**: 5 minuti (configurabile)
- **Beneficio**: Pagine admin più veloci
- **Trasparente**: Funziona automaticamente

### Logging Ottimizzato
- **Prima**: Pulizia ad ogni insert (lento)
- **Dopo**: Pulizia giornaliera via cron (veloce)
- **Beneficio**: 75% più veloce

---

## ⚙️ Configurazione Opzionale

### Personalizzare Rate Limiting

Aggiungi al tuo file `config.php` o tramite filtro:

```php
// Nel tuo tema o plugin personalizzato
add_filter('fp_git_updater_settings', function($settings) {
    $settings['rate_limit_max'] = 100; // 100 richieste invece di 60
    $settings['rate_limit_window'] = 7200; // 2 ore invece di 1 ora
    return $settings;
});
```

### Personalizzare Durata Cache

```php
add_filter('fp_git_updater_settings', function($settings) {
    $settings['api_cache_duration'] = 600; // 10 minuti invece di 5
    return $settings;
});
```

---

## 🔧 Risoluzione Problemi

### Problema: Token non vengono criptati

**Soluzione**:
1. Vai su **Git Updater → Impostazioni**
2. Modifica un plugin qualsiasi (anche solo cambiando nome)
3. Clicca **Salva Impostazioni**
4. I token verranno automaticamente criptati

### Problema: Webhook restituisce 429 (Rate Limit)

**Causa**: Troppe richieste dallo stesso IP  
**Soluzione**:
1. Normale se stai testando ripetutamente
2. Aspetta 1 ora per il reset automatico
3. O aumenta il limite (vedi configurazione sopra)

### Problema: Cache non si aggiorna

**Soluzione**:
```php
// Aggiungi questo codice temporaneamente per invalidare tutta la cache
$cache = FP_Git_Updater_API_Cache::get_instance();
$cache->invalidate_all();
```

---

## 📊 Confronto Versioni

| Caratteristica | v1.1.0 | v1.2.0 |
|---------------|--------|--------|
| Token Security | Plain text | AES-256 Encrypted |
| Rate Limiting | ❌ | ✅ 60 req/h |
| API Caching | ❌ | ✅ 5 min |
| Log Performance | Lento | +75% veloce |
| Migrazione Auto | ❌ | ✅ |
| Gestione Errori | Parziale | Completa |

---

## 🔄 Rollback (Se Necessario)

Se riscontri problemi critici con la v1.2.0:

### Metodo 1: Tramite WordPress
1. Vai su **Plugin** in WordPress
2. Disattiva "FP Git Updater"
3. Elimina il plugin
4. Carica la versione precedente dalla tua directory di backup
5. Attiva il plugin
6. Le impostazioni verranno ripristinate dal backup automatico

### Metodo 2: Tramite FTP
1. Connettiti via FTP al tuo sito
2. Naviga in `wp-content/plugins/`
3. Rinomina `fp-git-updater` in `fp-git-updater-backup`
4. Carica la versione precedente
5. Verifica che tutto funzioni

### Ripristino Impostazioni
1. Vai su **Git Updater → Backup e Ripristino**
2. Trova il backup precedente all'aggiornamento
3. Clicca **Ripristina**

---

## 📝 Checklist Post-Upgrade

Usa questa checklist per verificare che tutto funzioni:

- [ ] Plugin attivato senza errori
- [ ] Notifica di migrazione completata visualizzata
- [ ] Log non contengono errori critici
- [ ] Test connessione GitHub funzionante per tutti i plugin
- [ ] Webhook GitHub risponde 200 OK
- [ ] Pagina impostazioni carica correttamente
- [ ] Backup settings visibile in "Backup e Ripristino"
- [ ] Test di un aggiornamento manuale completato

---

## 💡 Best Practices

### Prima dell'Aggiornamento
1. ✅ Esegui un backup completo del sito
2. ✅ Testa su ambiente di staging
3. ✅ Verifica che WordPress sia aggiornato
4. ✅ Disabilita temporaneamente altri plugin di sicurezza

### Durante l'Aggiornamento
1. ✅ Non interrompere il processo
2. ✅ Aspetta la notifica di completamento
3. ✅ Controlla i log per conferma

### Dopo l'Aggiornamento
1. ✅ Verifica tutte le funzionalità
2. ✅ Testa webhook con un commit
3. ✅ Controlla che le notifiche email funzionino
4. ✅ Monitora i log per 24-48 ore

---

## 🆘 Supporto

Se riscontri problemi durante l'aggiornamento:

1. **Controlla i Log**: **Git Updater → Log**
2. **Verifica Backup**: **Git Updater → Backup e Ripristino**
3. **GitHub Webhook**: Controlla "Recent Deliveries" sul repository
4. **Error Log WordPress**: Controlla `wp-content/debug.log` se attivo

---

## 📈 Metriche di Successo

Dopo l'aggiornamento dovresti notare:

- ⚡ **Caricamento più veloce** delle pagine admin (grazie alla cache)
- 🔒 **Sicurezza migliorata** (token criptati, rate limiting attivo)
- 📊 **Log più puliti** (pulizia automatica schedulata)
- 🛡️ **Stabilità maggiore** (gestione errori migliorata)

---

**Versione Guida**: 1.0  
**Data**: 11 Ottobre 2025  
**Autore**: Francesco Passeri
