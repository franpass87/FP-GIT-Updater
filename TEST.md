# 🧪 Guida Test - FP Git Updater

## Test Completo del Plugin

### ✅ Prerequisiti

Prima di testare, assicurati di avere:
- [ ] WordPress installato e funzionante
- [ ] Repository GitHub (pubblico o privato)
- [ ] Accesso alle impostazioni del repository
- [ ] Personal Access Token GitHub (per repository privati)

---

## 🔍 Test di Base

### Test 1: Installazione
**Obiettivo**: Verificare che il plugin si installi correttamente

**Passi**:
1. Carica il plugin su WordPress
2. Attiva il plugin
3. Verifica che appaia nel menu "Git Updater"

**Risultato atteso**:
- ✅ Plugin attivato senza errori
- ✅ Menu "Git Updater" visibile
- ✅ Tabella log creata nel database
- ✅ Webhook secret generato automaticamente

**Come verificare**:
```sql
-- Controlla tabella log
SELECT * FROM wp_fp_git_updater_logs;

-- Controlla opzioni
SELECT * FROM wp_options WHERE option_name = 'fp_git_updater_settings';
```

---

### Test 2: Configurazione Base
**Obiettivo**: Configurare il plugin con un repository

**Passi**:
1. Vai su **Git Updater** → **Impostazioni**
2. Inserisci:
   - Repository: `username/repo`
   - Branch: `main`
   - Token: (se privato)
3. Clicca **Salva Impostazioni**

**Risultato atteso**:
- ✅ Impostazioni salvate
- ✅ Messaggio di conferma mostrato
- ✅ URL webhook e secret visibili

---

### Test 3: Test Connessione GitHub
**Obiettivo**: Verificare connessione al repository

**Passi**:
1. Nella pagina impostazioni
2. Clicca **Test Connessione**

**Risultati possibili**:

✅ **Successo**: "Connessione riuscita!"
- Repository accessibile
- Token valido (se privato)
- Branch esiste

❌ **Errore 404**: Repository non trovato
- Verifica nome repository
- Verifica che esista

❌ **Errore 401**: Non autorizzato
- Token non valido o scaduto
- Token senza scope `repo`
- Repository privato senza token

---

## 🔗 Test Webhook

### Test 4: Configurazione Webhook GitHub
**Obiettivo**: Configurare webhook su GitHub

**Passi**:
1. GitHub → Repository → **Settings** → **Webhooks**
2. **Add webhook**
3. Compila:
   - Payload URL: [copia da plugin]
   - Content type: `application/json`
   - Secret: [copia da plugin]
   - Events: `Just the push event`
4. **Add webhook**

**Risultato atteso**:
- ✅ Webhook creato
- ✅ Segno ✅ verde accanto al webhook
- ✅ Status "Last delivery was successful"

**Se vedi ❌ rosso**:
1. Clicca sul webhook
2. Vai a **Recent Deliveries**
3. Clicca sulla delivery fallita
4. Controlla "Response" per vedere l'errore

---

### Test 5: Test Webhook con Ping
**Obiettivo**: Verificare che il webhook raggiunga WordPress

**Passi**:
1. GitHub → Repository → Settings → Webhooks
2. Clicca sul tuo webhook
3. Scorri in basso e clicca **Redeliver** su una delivery
4. Oppure clicca **Send test payload**

**Risultato atteso**:
- ✅ Status HTTP 200
- ✅ Response body con `success: true`

**Verifica nei log**:
```
WordPress → Git Updater → Log
Dovresti vedere: "Webhook ricevuto da GitHub"
```

---

### Test 6: Test Push Reale
**Obiettivo**: Testare aggiornamento completo

**Passi**:
```bash
# Sul tuo computer
cd /path/to/repository

# Fai una modifica
echo "# Test update" >> README.md
git add README.md
git commit -m "Test: aggiornamento plugin"
git push
```

**Risultato atteso (nei log)**:
1. ✅ "Webhook ricevuto da GitHub"
2. ✅ "Push ricevuto sul branch main"
3. ✅ "Aggiornamento schedulato per il commit abc123"
4. ✅ "Inizio aggiornamento..."
5. ✅ "Download dell'aggiornamento..."
6. ✅ "Estrazione dell'aggiornamento..."
7. ✅ "Creazione backup..."
8. ✅ "Installazione nuovi file..."
9. ✅ "Aggiornamento completato con successo!"

**Tempo stimato**: 15-45 secondi

**Verifica**:
- Email di notifica ricevuta (se abilitata)
- Commit SHA aggiornato nelle impostazioni
- File modificati presenti nel plugin

---

## 🛡️ Test Sicurezza

### Test 7: Webhook con Firma Invalida
**Obiettivo**: Verificare che webhook senza firma corretta vengano rifiutati

**Passi**:
```bash
# Test con curl (sostituisci URL)
curl -X POST https://tuosito.com/wp-json/fp-git-updater/v1/webhook \
  -H "Content-Type: application/json" \
  -H "X-GitHub-Event: push" \
  -H "X-Hub-Signature-256: sha256=firmafalsa" \
  -d '{"ref":"refs/heads/main"}'
```

**Risultato atteso**:
- ❌ HTTP 401 Unauthorized
- ❌ Log: "Webhook: firma non valida"

---

### Test 8: Token Scaduto/Invalido
**Obiettivo**: Gestione errori token

**Passi**:
1. Impostazioni → Inserisci token invalido
2. Clicca **Test Connessione**

**Risultato atteso**:
- ❌ Errore 401
- ❌ Log: "Errore API GitHub"

---

## 🔄 Test Aggiornamento

### Test 9: Aggiornamento Manuale
**Obiettivo**: Testare aggiornamento con pulsante

**Passi**:
1. Impostazioni → **Aggiorna Ora**
2. Conferma

**Risultato atteso**:
- ⏳ Messaggio "Aggiornamento in corso..."
- ✅ Dopo 15-45 secondi: "Aggiornamento completato!"
- ✅ Pagina si ricarica automaticamente
- ✅ Email notifica ricevuta

---

### Test 10: Rollback su Errore
**Obiettivo**: Verificare ripristino backup in caso di errore

**Passi simulazione**:
1. Forza un errore (es: repository inesistente temporaneamente)
2. Triggera aggiornamento

**Risultato atteso**:
- ❌ Aggiornamento fallisce
- ✅ Backup ripristinato automaticamente
- ✅ Plugin ancora funzionante
- ✅ Log descrive l'errore
- ✅ Email notifica errore

---

## 📊 Test Log

### Test 11: Visualizzazione Log
**Obiettivo**: Verificare sistema logging

**Passi**:
1. Vai su **Git Updater** → **Log**

**Risultato atteso**:
- ✅ Tabella con log ordinati (più recenti primi)
- ✅ Colonne: Data, Tipo, Messaggio
- ✅ Badge colorati per tipo:
  - 🔵 info
  - 🟢 success
  - 🟡 warning
  - 🔴 error
  - 🟣 webhook

---

### Test 12: Pulizia Log
**Obiettivo**: Testare funzione pulizia

**Passi**:
1. Git Updater → Log
2. Clicca **Pulisci Log**
3. Conferma

**Risultato atteso**:
- ✅ Tutti i log eliminati
- ✅ Tabella vuota
- ✅ Messaggio conferma

---

## 🔧 Test Avanzati

### Test 13: Repository Privato
**Obiettivo**: Verificare accesso repository privati

**Passi**:
1. Usa repository privato
2. Configura token con scope `repo`
3. Testa connessione e aggiornamento

**Risultato atteso**:
- ✅ Test connessione: successo
- ✅ Aggiornamento: completo

---

### Test 14: Branch Diversi
**Obiettivo**: Testare aggiornamenti da branch specifici

**Passi**:
1. Crea branch `staging` su GitHub
2. Impostazioni → Branch: `staging`
3. Salva
4. Push su branch `staging`

**Risultato atteso**:
- ✅ Aggiornamento triggerato solo da push su `staging`
- ❌ Push su `main` ignorato

---

### Test 15: Controlli Periodici
**Obiettivo**: Verificare cron job

**Passi**:
```bash
# Via SSH o WP-CLI
wp cron event list | grep fp_git_updater
```

**Risultato atteso**:
- ✅ `fp_git_updater_check_update` presente
- ✅ Next run schedulato

**Forza esecuzione**:
```bash
wp cron event run fp_git_updater_check_update
```

---

### Test 16: Multi-Sito
**Obiettivo**: Testare su più siti contemporaneamente

**Passi**:
1. Installa plugin su 2+ siti
2. Configura stesso repository su tutti
3. Push su GitHub

**Risultato atteso**:
- ✅ Tutti i siti ricevono webhook
- ✅ Tutti i siti si aggiornano
- ✅ Email da tutti i siti (se abilitata)

---

## 📱 Test UI/UX

### Test 17: Interfaccia Admin
**Obiettivo**: Verificare UI e UX

**Checklist**:
- [ ] Layout responsive (mobile/tablet/desktop)
- [ ] Pulsanti cliccabili e funzionanti
- [ ] Loading indicators durante operazioni
- [ ] Messaggi di conferma/errore chiari
- [ ] Tooltip e istruzioni presenti
- [ ] Nessun errore JavaScript in console
- [ ] Stili CSS corretti

---

### Test 18: Notifiche
**Obiettivo**: Testare sistema notifiche

**Passi**:
1. Abilita notifiche email
2. Configura email personalizzata
3. Triggera aggiornamento

**Risultato atteso**:
- ✅ Email "Inizio aggiornamento"
- ✅ Email "Aggiornamento completato"
- ✅ Email corretta destinazione
- ✅ Contenuto email chiaro

---

## 🐛 Test Edge Cases

### Test 19: Disk Space Insufficiente
**Simulazione**: Riempi disco quasi completamente

**Risultato atteso**:
- ❌ Aggiornamento fallisce gracefully
- ✅ Messaggio errore chiaro
- ✅ Backup non compromesso

---

### Test 20: Timeout Network
**Simulazione**: Download molto lento

**Risultato atteso**:
- ⏳ Timeout dopo 300 secondi (5 minuti)
- ❌ Errore gestito correttamente
- ✅ Log descrive timeout

---

### Test 21: Repository Eliminato
**Simulazione**: Repository non esiste più

**Risultato atteso**:
- ❌ HTTP 404
- ✅ Messaggio errore chiaro
- ✅ Plugin continua a funzionare

---

## 📋 Checklist Finale

Prima di considerare il plugin production-ready:

### Funzionalità
- [ ] Installazione e attivazione ok
- [ ] Configurazione salvata correttamente
- [ ] Test connessione funziona
- [ ] Webhook ricevuto e verificato
- [ ] Aggiornamento completo funziona
- [ ] Backup creato e gestito
- [ ] Rollback funziona in caso errore
- [ ] Log registrati correttamente
- [ ] Email notifiche inviate
- [ ] Pulizia automatica backup

### Sicurezza
- [ ] Firma webhook verificata
- [ ] Token GitHub sicuro
- [ ] Input sanitizzati
- [ ] Nonce verificati
- [ ] Capability checks presenti
- [ ] SQL injection prevenuta

### Performance
- [ ] Aggiornamento < 60 secondi
- [ ] Nessun blocking del sito
- [ ] Cron job schedulati correttamente
- [ ] Log puliti periodicamente

### Compatibilità
- [ ] WordPress 5.0+ testato
- [ ] PHP 7.4+ testato
- [ ] MySQL/MariaDB testato
- [ ] Vari temi testati
- [ ] Multi-sito testato (se necessario)

---

## 🆘 Troubleshooting Comune

### Problema: "Webhook non ricevuto"
**Diagnosi**:
```bash
# Test manuale
curl -X POST https://tuosito.com/wp-json/fp-git-updater/v1/webhook \
  -H "Content-Type: application/json" \
  -H "X-GitHub-Event: ping"
```

**Soluzioni**:
1. Verifica firewall
2. Verifica plugin sicurezza
3. Verifica URL corretto
4. Controlla Recent Deliveries su GitHub

---

### Problema: "Aggiornamento fallisce"
**Diagnosi**:
1. Controlla log per errore specifico
2. Verifica permessi directory plugin
3. Verifica spazio disco
4. Testa token manualmente

**Soluzioni**:
```bash
# Verifica permessi
ls -la /path/to/wp-content/plugins/fp-git-updater

# Fix permessi
chmod 755 /path/to/wp-content/plugins/fp-git-updater
chown www-data:www-data -R /path/to/wp-content/plugins/fp-git-updater
```

---

## ✅ Risultati Attesi

Dopo tutti i test, il plugin dovrebbe:
- ✅ Installarsi senza errori
- ✅ Ricevere webhook da GitHub
- ✅ Scaricare e installare aggiornamenti
- ✅ Creare backup automatici
- ✅ Rollback in caso errore
- ✅ Registrare tutto nei log
- ✅ Notificare via email
- ✅ Funzionare su repository privati
- ✅ Gestire errori gracefully
- ✅ Essere sicuro e performante

---

**Buon testing!** 🧪✨
