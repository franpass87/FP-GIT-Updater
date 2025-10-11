# Guida Installazione FP Git Updater

## Installazione Rapida

### Metodo 1: Upload Diretto

1. Scarica il plugin come ZIP o clonalo:
   ```bash
   git clone https://github.com/tuousername/fp-git-updater.git
   cd fp-git-updater
   zip -r fp-git-updater.zip . -x "*.git*" -x "*.DS_Store"
   ```

2. Vai nel pannello WordPress → **Plugin** → **Aggiungi nuovo** → **Carica plugin**

3. Seleziona il file ZIP e clicca **Installa ora**

4. Clicca **Attiva plugin**

### Metodo 2: FTP/SFTP

1. Carica la cartella `fp-git-updater` in `/wp-content/plugins/`

2. Vai su **Plugin** nel pannello WordPress

3. Trova "FP Git Updater" e clicca **Attiva**

### Metodo 3: SSH (per server con accesso SSH)

```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/tuousername/fp-git-updater.git
```

Poi attiva dal pannello WordPress.

## Primo Setup

### 1. Crea un Personal Access Token su GitHub

1. Vai su GitHub.com e accedi al tuo account
2. Clicca sulla tua foto profilo in alto a destra → **Settings**
3. Nel menu laterale in basso, clicca **Developer settings**
4. Clicca **Personal access tokens** → **Tokens (classic)**
5. Clicca **Generate new token** → **Generate new token (classic)**
6. Inserisci un nome descrittivo, es: "WordPress Updater"
7. Seleziona lo scope **`repo`** (tutte le sottovoci)
8. Clicca **Generate token**
9. **IMPORTANTE**: Copia subito il token, non potrai più vederlo!

### 2. Configura il Plugin

1. Vai su **Git Updater** → **Impostazioni**

2. Compila il form:
   - **Repository GitHub**: `tuousername/nome-repository`
   - **Branch**: `main` (o il tuo branch principale)
   - **GitHub Token**: Incolla il token creato al passo 1
   - **Webhook Secret**: Lascia quello generato automaticamente
   - **Aggiornamento Automatico**: ✅ Spuntato
   - **Notifiche Email**: ✅ Spuntato
   - **Email Notifiche**: La tua email

3. Copia:
   - L'**URL Webhook** (lo userai al passo successivo)
   - Il **Webhook Secret** (lo userai al passo successivo)

4. Clicca **Salva Impostazioni**

### 3. Configura il Webhook su GitHub

1. Vai sul tuo repository GitHub

2. Clicca su **Settings** (tab in alto)

3. Nel menu laterale, clicca **Webhooks**

4. Clicca **Add webhook**

5. Compila il form:
   - **Payload URL**: Incolla l'URL webhook copiato dal plugin
   - **Content type**: Seleziona `application/json`
   - **Secret**: Incolla il Webhook Secret copiato dal plugin
   - **Which events would you like to trigger this webhook?**
     - Seleziona: ⚪ **Just the push event**
   - **Active**: ✅ Assicurati che sia spuntato

6. Clicca **Add webhook**

7. GitHub mostrerà una lista di webhook. Il tuo dovrebbe avere:
   - Un segno ✅ verde (significa che la configurazione è corretta)
   - Se vedi ❌ rosso, clicca sul webhook e controlla "Recent Deliveries" per vedere l'errore

### 4. Testa la Configurazione

#### Test Connessione

1. Torna su **Git Updater** → **Impostazioni** su WordPress
2. Clicca il pulsante **Test Connessione**
3. Dovresti vedere: "Connessione riuscita!"

Se vedi un errore:
- Verifica che il repository sia scritto correttamente (`username/repo`)
- Verifica che il token GitHub sia valido e abbia lo scope `repo`
- Verifica che il branch esista

#### Test Webhook

1. Sul tuo computer, fai una modifica al repository e fai push:
   ```bash
   git commit --allow-empty -m "Test webhook"
   git push
   ```

2. Vai su **Git Updater** → **Log** su WordPress

3. Dovresti vedere:
   - "Webhook ricevuto da GitHub"
   - "Push ricevuto sul branch main"
   - "Aggiornamento schedulato"

4. Su GitHub, vai su **Settings** → **Webhooks** → clicca sul tuo webhook
   - Scorri fino a **Recent Deliveries**
   - Dovresti vedere la richiesta con il segno ✅ verde e "Response: 200"

#### Test Aggiornamento Completo

1. Fai una modifica reale al repository:
   ```bash
   echo "// Test update" >> README.md
   git add README.md
   git commit -m "Test update"
   git push
   ```

2. Entro pochi secondi dovresti ricevere:
   - Una notifica email (se abilitata)
   - I log aggiornati in **Git Updater** → **Log**

3. Controlla i log per vedere:
   - "Inizio aggiornamento..."
   - "Download dell'aggiornamento..."
   - "Estrazione dell'aggiornamento..."
   - "Creazione backup..."
   - "Installazione nuovi file..."
   - "Aggiornamento completato con successo!"

## Installazione Multi-Sito

Se hai più siti WordPress e vuoi che tutti si aggiornino automaticamente:

1. **Installa il plugin su ogni sito** seguendo la procedura sopra

2. **Usa lo stesso repository GitHub** per tutti

3. **Configura le impostazioni** identiche su ogni sito

4. **Configura UN SOLO webhook** su GitHub (funzionerà per tutti i siti)

Ora quando fai push, tutti i siti si aggiorneranno automaticamente!

## Verifica Installazione

### Checklist

- [ ] Plugin attivato su WordPress
- [ ] Token GitHub creato con scope `repo`
- [ ] Impostazioni configurate e salvate
- [ ] Webhook configurato su GitHub
- [ ] Test connessione superato ✅
- [ ] Test webhook ricevuto ✅
- [ ] Log mostrano attività
- [ ] Notifica email ricevuta (se abilitata)

### Problemi Comuni

#### "Webhook non ricevuto"

**Causa**: Firewall o plugin di sicurezza blocca le richieste esterne

**Soluzione**:
1. Verifica che il tuo sito sia raggiungibile da internet
2. Se usi Cloudflare o altri CDN, verifica che non blocchino le richieste POST
3. Se usi plugin di sicurezza (es: Wordfence), aggiungi l'URL webhook alle whitelist
4. Verifica che il file `.htaccess` non blocchi l'URL webhook

#### "Errore 401 Unauthorized"

**Causa**: Token GitHub non valido o scaduto

**Soluzione**:
1. Rigenera il token su GitHub
2. Assicurati di selezionare lo scope `repo`
3. Copia e incolla il nuovo token nelle impostazioni

#### "Errore durante download"

**Causa**: Problemi di connessione o timeout

**Soluzione**:
1. Verifica la connessione internet del server
2. Aumenta il `max_execution_time` in PHP (minimo 300 secondi)
3. Verifica che il repository sia accessibile

#### "Errore permessi file"

**Causa**: La directory del plugin non è scrivibile

**Soluzione**:
```bash
# Via SSH/FTP, imposta i permessi corretti
chmod 755 /path/to/wp-content/plugins/fp-git-updater
chown www-data:www-data /path/to/wp-content/plugins/fp-git-updater -R
```

## Configurazione Avanzata

### Repository Privato

Il plugin supporta nativamente repository privati. Basta inserire il token GitHub nelle impostazioni.

### Branch Multipli

Puoi configurare branch diversi per ambienti diversi:
- **Produzione**: branch `main`
- **Staging**: branch `staging`
- **Development**: branch `develop`

### Disabilitare Aggiornamento Automatico

Se vuoi ricevere i webhook ma decidere manualmente quando aggiornare:
1. Vai su **Git Updater** → **Impostazioni**
2. Deseleziona **Aggiornamento Automatico**
3. Usa il pulsante **Aggiorna Ora** quando vuoi aggiornare manualmente

## Supporto

Se hai problemi:
1. Controlla **Git Updater** → **Log** per errori specifici
2. Consulta il README.md per risoluzione problemi
3. Verifica "Recent Deliveries" del webhook su GitHub

---

✅ **Installazione completata!** Ora ogni volta che fai push su GitHub, il tuo sito si aggiornerà automaticamente.
