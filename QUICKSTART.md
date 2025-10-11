# 🚀 Guida Rapida - FP Git Updater

Configurazione completa in **5 minuti**!

## Passo 1: Installa il Plugin (2 min)

### Opzione A: Upload ZIP
1. Scarica o crea lo ZIP del plugin
2. WordPress → **Plugin** → **Aggiungi nuovo** → **Carica plugin**
3. Seleziona il file ZIP
4. Clicca **Installa ora** → **Attiva**

### Opzione B: FTP/SSH
```bash
cd /percorso/wordpress/wp-content/plugins/
git clone https://github.com/tuousername/fp-git-updater.git
# Poi attiva dal pannello WordPress
```

## Passo 2: Crea Token GitHub (1 min)

1. Vai su **github.com** → Foto profilo → **Settings**
2. **Developer settings** → **Personal access tokens** → **Tokens (classic)**
3. **Generate new token (classic)**
4. Nome: `WordPress Updater`
5. Spunta: ☑️ **repo** (tutte le sotto-voci)
6. **Generate token**
7. **Copia il token** (non lo vedrai più!)

## Passo 3: Configura Plugin (1 min)

1. WordPress → **Git Updater** → **Impostazioni**

2. Compila:
   ```
   Repository:  tuousername/tuo-repository
   Branch:      main
   Token:       ghp_xxxxxxxxxxxxx (il token del passo 2)
   ```

3. **Copia**:
   - URL Webhook
   - Webhook Secret

4. **Salva Impostazioni**

## Passo 4: Configura Webhook GitHub (1 min)

1. GitHub → Tuo repository → **Settings** → **Webhooks** → **Add webhook**

2. Compila:
   ```
   Payload URL:    [incolla URL Webhook dal passo 3]
   Content type:   application/json
   Secret:         [incolla Webhook Secret dal passo 3]
   Events:         Just the push event
   Active:         ✅ Spuntato
   ```

3. **Add webhook**

## Passo 5: Testa! (30 sec)

### Test 1: Connessione
WordPress → **Git Updater** → **Impostazioni** → Click **Test Connessione**

✅ Dovresti vedere: "Connessione riuscita!"

### Test 2: Webhook
```bash
# Sul tuo computer
git commit --allow-empty -m "Test webhook"
git push
```

Controlla:
1. WordPress → **Git Updater** → **Log**
   - Dovresti vedere: "Webhook ricevuto da GitHub"
2. GitHub → Repository → **Settings** → **Webhooks** → Click sul webhook
   - Dovresti vedere: ✅ verde in "Recent Deliveries"

## 🎉 Fatto!

Ora ogni volta che fai push su GitHub, il plugin si aggiornerà automaticamente!

---

## Comandi Veloci

### Forza aggiornamento manuale
WordPress → **Git Updater** → **Impostazioni** → **Aggiorna Ora**

### Vedi log attività
WordPress → **Git Updater** → **Log**

### Test push
```bash
git commit --allow-empty -m "Test update"
git push
```

---

## ❓ Problemi?

### Webhook non ricevuto?
- Verifica che il sito sia raggiungibile da internet
- Controlla firewall/plugin sicurezza
- Verifica su GitHub "Recent Deliveries"

### Errore 401?
- Token scaduto o invalido
- Rigenera il token con scope `repo`

### Aggiornamento fallisce?
- Controlla i **Log** per l'errore specifico
- Verifica permessi directory plugin
- Aumenta `max_execution_time` PHP

---

## 📚 Documentazione Completa

- [README.md](README.md) - Guida completa
- [INSTALL.md](INSTALL.md) - Installazione dettagliata
- [CHANGELOG.md](CHANGELOG.md) - Versioni e modifiche

---

## 💡 Tips

### Repository Privato
Il plugin supporta repository privati - basta inserire il token!

### Multi-Sito
Installa su più siti, configura lo stesso repository, e tutti si aggiorneranno insieme!

### Disabilita Auto-Update
Impostazioni → Deseleziona "Aggiornamento Automatico"
Usa il pulsante "Aggiorna Ora" quando vuoi

### Branch Diversi
Produzione → `main`
Staging → `staging`
Development → `develop`

---

**Buon divertimento!** 🎊
