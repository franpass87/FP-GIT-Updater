# Implementazione Sistema di Aggiornamento Manuale

## 📋 Riepilogo delle Modifiche

Implementato un sistema robusto di aggiornamento manuale per proteggere i siti dei clienti da aggiornamenti problematici.

## 🔧 Modifiche Tecniche

### 1. **class-webhook-handler.php**
- ✅ Ora registra SEMPRE gli aggiornamenti disponibili come "pending"
- ✅ Se `auto_update` è **disabilitato**: notifica solo la disponibilità, non installa
- ✅ Se `auto_update` è **abilitato**: installa automaticamente come prima
- ✅ Log migliorati per distinguere tra modalità automatica e manuale

### 2. **class-updater.php**
- ✅ Aggiunto sistema di tracking "pending updates"
- ✅ Nuovi metodi:
  - `get_pending_updates()`: ottiene tutti gli aggiornamenti in attesa
  - `clear_pending_update($plugin_id)`: rimuove un pending update
- ✅ Modificato `check_plugin_for_updates()`: registra gli aggiornamenti come pending prima di installarli
- ✅ Modificato `run_update_by_id()`: usa il commit SHA dal pending update se disponibile
- ✅ Pulizia automatica dei pending updates dopo installazione riuscita

### 3. **class-admin.php**
- ✅ **Menu WordPress**: mostra badge con numero di aggiornamenti pending
- ✅ **Banner in alto**: avviso prominente con lista di aggiornamenti disponibili
- ✅ **Indicatori visivi**: 
  - Badge "AGGIORNAMENTO DISPONIBILE" rosso su ogni plugin
  - Bordo rosso sui plugin con aggiornamenti pending
  - Animazione pulse sul pulsante di installazione
- ✅ **Notifiche contestuali**:
  - Modalità manuale attiva: badge verde con icona shield
  - Modalità automatica attiva: avviso giallo di attenzione
- ✅ **Informazioni aggiornamento**: commit SHA, messaggio, autore, timestamp
- ✅ **Istruzioni migliorate**: documentazione chiara su entrambe le modalità

### 4. **assets/admin.css**
- ✅ Aggiunta animazione `@keyframes pulse` per pulsanti di aggiornamento
- ✅ Stile `.update-count` per badge nel titolo pagina
- ✅ Stile `.fp-plugin-item.has-update` per evidenziare plugin con aggiornamenti

### 5. **fp-git-updater.php**
- ✅ Versione aggiornata a **1.1.0**
- ✅ Descrizione plugin aggiornata per riflettere la nuova funzionalità
- ✅ **Default `auto_update` cambiato a `false`** per sicurezza (nuove installazioni)
- ✅ Installazioni esistenti mantengono il loro valore di `auto_update`

## 🎯 Come Funziona Ora

### Modalità Manuale (Consigliata per Produzione) ✅
1. **GitHub Push** → Webhook notifica il sito
2. **Plugin** → Registra l'aggiornamento come "disponibile"
3. **Amministratore** → Vede notifica nel menu e nella pagina
4. **Amministratore** → Decide QUANDO installare cliccando "Installa Aggiornamento"
5. **Vantaggio** → Puoi testare prima su staging, poi installare in produzione

### Modalità Automatica (Per Sviluppo)
1. **GitHub Push** → Webhook notifica il sito
2. **Plugin** → Installa immediatamente l'aggiornamento
3. **Rischio** → Un bug va direttamente in produzione

## 🛡️ Protezioni Implementate

1. **Default Sicuro**: Nuove installazioni hanno `auto_update = false`
2. **Notifiche Visibili**: Badge rosso nel menu + banner prominente
3. **Informazioni Complete**: Vedi commit, messaggio, autore prima di installare
4. **Backup Automatico**: Il sistema di backup esistente protegge da problemi
5. **Log Dettagliati**: Tutto viene tracciato per debug

## 📝 Flusso Consigliato per Siti di Clienti

```
1. SVILUPPO (auto_update = true)
   └─> Testa le modifiche in locale

2. STAGING (auto_update = false)
   ├─> Ricevi notifica aggiornamento
   ├─> Installa manualmente
   └─> Testa che tutto funzioni

3. PRODUZIONE (auto_update = false)
   ├─> Ricevi notifica aggiornamento
   ├─> Installa manualmente dopo test su staging
   └─> Sito cliente protetto! ✅
```

## 🎨 Interfaccia Utente

### Elementi Visivi Aggiunti
- **Badge Menu**: Numero rosso accanto a "Git Updater" nel menu WordPress
- **Badge Titolo**: Contatore aggiornamenti nel titolo della pagina
- **Banner Rosso**: Lista completa aggiornamenti disponibili in alto
- **Badge Plugin**: "AGGIORNAMENTO DISPONIBILE" su ogni plugin interessato
- **Bordo Rosso**: Evidenziazione visiva dei plugin con aggiornamenti
- **Box Info**: Dettagli commit con SHA, messaggio, autore, data
- **Pulsante Animato**: Effetto pulse sul bottone "Installa Aggiornamento Ora"
- **Icona Shield**: Indica quando la modalità manuale è attiva

## 🔄 Compatibilità

- ✅ **Retrocompatibile**: Installazioni esistenti continuano a funzionare
- ✅ **Preserva Impostazioni**: L'upgrade mantiene la configurazione `auto_update`
- ✅ **Migrazione Automatica**: Plugin esistenti vengono migrati correttamente
- ✅ **Backup Settings**: Sistema di backup protegge le configurazioni

## 📊 Dati Salvati

Per ogni aggiornamento pending viene salvato:
```php
'fp_git_updater_pending_update_{plugin_id}' => [
    'commit_sha' => 'abc123...',
    'commit_sha_short' => 'abc123',
    'commit_message' => 'Fix bug xyz',
    'commit_author' => 'Nome Sviluppatore',
    'branch' => 'main',
    'timestamp' => '2025-10-11 10:30:00',
    'plugin_name' => 'Nome Plugin'
]
```

## ✅ Testing Suggerito

1. **Disabilita `auto_update`** nelle impostazioni
2. **Fai un push** su un repository configurato
3. **Verifica** che appaia il badge nel menu
4. **Controlla** che il banner rosso mostri l'aggiornamento
5. **Clicca** su "Installa Aggiornamento Ora"
6. **Verifica** che l'installazione vada a buon fine
7. **Controlla** che il pending update sia stato rimosso

## 🚀 Deployment

Questo aggiornamento è **sicuro da deployare** su siti in produzione:
- Non rompe installazioni esistenti
- Non forza cambiamenti di comportamento
- Aggiunge solo funzionalità di sicurezza
- Default sicuro per nuove installazioni

---

**Versione**: 1.1.0  
**Data**: 2025-10-11  
**Autore**: Francesco Passeri
