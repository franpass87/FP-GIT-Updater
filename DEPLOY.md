# 🚀 Guida Deploy Completo

## Workflow Deploy Automatico

Questa guida spiega come fare il deploy completo del plugin con automazione GitHub.

---

## 📋 Prerequisiti

- [x] Repository GitHub creato
- [x] Plugin testato localmente
- [x] WordPress di test disponibile
- [x] Accesso ai siti WordPress di destinazione

---

## 🎯 Deploy in 4 Passi

### Passo 1: Push su GitHub (2 min)

```bash
# Dalla directory del plugin
cd /workspace

# Inizializza git (se non fatto)
git init
git add .
git commit -m "Initial commit: FP Git Updater v1.0.0"

# Collega a GitHub
git remote add origin https://github.com/TUO_USERNAME/fp-git-updater.git
git branch -M main
git push -u origin main
```

**✅ Risultato**: 
- Plugin su GitHub
- GitHub Actions si attivano automaticamente
- ZIP creato e disponibile

---

### Passo 2: Scarica ZIP Automatico (1 min)

**Opzione A - Da GitHub Actions** (Raccomandato):
```
1. Vai su: https://github.com/TUO_USERNAME/fp-git-updater/actions
2. Click su "Build e Release Plugin"
3. Click sul workflow più recente (verde ✅)
4. Scroll down → "Artifacts"
5. Download "fp-git-updater"
```

**Opzione B - Build locale**:
```bash
cd /workspace
./scripts/build.sh
# Crea: fp-git-updater.zip
```

---

### Passo 3: Installa su WordPress (3 min)

#### Su Primo Sito:

```bash
# Via SSH
scp fp-git-updater.zip user@tuo-sito.com:/tmp/

ssh user@tuo-sito.com
cd /var/www/html/wp-content/plugins/
unzip /tmp/fp-git-updater.zip
rm /tmp/fp-git-updater.zip
chown -R www-data:www-data fp-git-updater
```

**O via WordPress Admin**:
```
1. WordPress → Plugin → Aggiungi nuovo
2. Carica plugin → Scegli file
3. Seleziona fp-git-updater.zip
4. Installa ora → Attiva
```

---

### Passo 4: Configura Plugin (5 min)

#### 1. Crea Token GitHub:
```
GitHub → Settings → Developer settings → 
Personal access tokens → Generate new token (classic)

Nome: WordPress Updater
Scope: ☑️ repo (tutte le sotto-voci)
→ Generate token
→ COPIA IL TOKEN!
```

#### 2. Configura Plugin:
```
WordPress → Git Updater → Impostazioni

Repository GitHub: TUO_USERNAME/fp-git-updater
Branch: main
GitHub Token: ghp_xxxxxxxxxxxxx (incolla token)
→ Salva Impostazioni

COPIA:
- URL Webhook
- Webhook Secret
```

#### 3. Configura Webhook GitHub:
```
GitHub → Repository → Settings → Webhooks → Add webhook

Payload URL: [incolla URL Webhook]
Content type: application/json
Secret: [incolla Webhook Secret]
Events: ☑️ Just the push event
Active: ☑️
→ Add webhook

VERIFICA: Dovrebbe avere ✅ verde
```

#### 4. Test:
```
WordPress → Git Updater → Impostazioni
→ Click "Test Connessione"

Dovrebbe mostrare: "✅ Connessione riuscita!"
```

---

## 🔄 Aggiornamento Automatico Funzionante!

Ora quando fai:

```bash
git add .
git commit -m "Update: nuova funzionalità"
git push origin main
```

**Succede automaticamente**:
1. ✅ GitHub Actions crea nuovo ZIP (2 min)
2. ✅ GitHub invia webhook a WordPress (istantaneo)
3. ✅ WordPress scarica e installa update (15-30 sec)
4. ✅ Email notifica inviata
5. ✅ Log salvati

---

## 🌐 Deploy Multi-Sito

Hai 10 siti da aggiornare? Nessun problema!

### Metodo 1: Manuale (Primo Setup)

```bash
# Usa lo script deploy incluso
vim scripts/deploy.sh

# Aggiungi i tuoi siti:
SITES=(
    "user@sito1.com:/var/www/html/wp-content/plugins/"
    "user@sito2.com:/var/www/html/wp-content/plugins/"
    "user@sito3.com:/var/www/html/wp-content/plugins/"
)

# Esegui deploy
./scripts/deploy.sh
```

**Risultato**: Plugin installato su tutti i siti

### Metodo 2: Automatico (Dopo Setup)

**Su ogni sito**:
1. Installa plugin (una volta sola)
2. Configura con STESSO repository
3. Done!

**Quando fai push**:
- ✅ Tutti i siti ricevono webhook
- ✅ Tutti si aggiornano simultaneamente
- ✅ Zero azioni manuali

---

## 📦 Release Ufficiali

### Creare una Release Pubblica:

```bash
# 1. Aggiorna versione
vim fp-git-updater.php
# Version: 1.0.0 → Version: 1.1.0

# 2. Aggiorna CHANGELOG
vim CHANGELOG.md
# Aggiungi sezione [1.1.0]

# 3. Commit
git add .
git commit -m "Release: v1.1.0"
git push origin main

# 4. Crea tag
git tag -a v1.1.0 -m "Release 1.1.0 - Descrizione funzionalità"
git push origin v1.1.0
```

**GitHub automaticamente**:
1. ✅ Crea GitHub Release
2. ✅ Allega ZIP
3. ✅ Genera note di release
4. ✅ Invia webhook a tutti i siti
5. ✅ Tutti si aggiornano a v1.1.0

**Utenti vedono**:
```
GitHub → Repository → Releases
→ v1.1.0 con ZIP scaricabile
```

---

## 🔒 Deploy Sicuro

### Best Practices:

#### 1. Test su Staging Prima
```bash
# Branch staging
git checkout -b staging
git push origin staging

# Configura siti staging con branch 'staging'
# Testa tutto
# Se ok, merge a main
git checkout main
git merge staging
git push origin main
```

#### 2. Backup Automatico
Il plugin crea backup automaticamente, ma:
```bash
# Backup database manuale prima di test
wp db export backup-$(date +%Y%m%d).sql

# O con plugin
# UpdraftPlus, BackWPup, etc.
```

#### 3. Rollback Rapido
Se qualcosa va storto:
```bash
# 1. Plugin rollback automatico (già incluso)

# 2. O manualmente:
ssh user@sito.com
cd /var/www/html/wp-content/plugins/
rm -rf fp-git-updater
# Ripristina backup precedente
mv fp-git-updater-backup-XXXXX fp-git-updater
```

---

## 🎛️ Ambienti Multipli

### Setup per Dev/Staging/Production:

```bash
# Branch Strategy
main        → Production
staging     → Staging  
develop     → Development
```

**Configurazione**:

**Sito Production**:
```
WordPress → Git Updater → Impostazioni
Branch: main
```

**Sito Staging**:
```
WordPress → Git Updater → Impostazioni
Branch: staging
```

**Sito Dev**:
```
WordPress → Git Updater → Impostazioni
Branch: develop
```

**Workflow**:
```bash
# Sviluppo
git checkout develop
git add .
git commit -m "Add: feature X"
git push origin develop
→ Solo sito DEV si aggiorna

# Test su staging
git checkout staging
git merge develop
git push origin staging
→ Solo sito STAGING si aggiorna

# Deploy production
git checkout main
git merge staging
git push origin main
→ Solo siti PRODUCTION si aggiornano
```

---

## 📊 Monitoraggio Deploy

### Verifica Stato Aggiornamenti:

**Su GitHub**:
```
Actions → Workflow runs → Vedi storia completa
```

**Su WordPress**:
```
Git Updater → Log → Vedi aggiornamenti
```

### Dashboard Centralizzato (Opzionale):

Aggiungi a `.github/workflows/auto-update-webhook.yml`:

```yaml
- name: Report a Dashboard
  run: |
    curl -X POST https://tua-dashboard.com/api/deploy \
      -H "Content-Type: application/json" \
      -d '{
        "plugin": "fp-git-updater",
        "version": "${{ github.sha }}",
        "timestamp": "'$(date -u +%Y-%m-%dT%H:%M:%SZ)'"
      }'
```

---

## 🚨 Troubleshooting Deploy

### Problema: Webhook non arriva

**Diagnosi**:
```bash
# Test endpoint manualmente
curl -X POST https://tuo-sito.com/wp-json/fp-git-updater/v1/webhook \
  -H "Content-Type: application/json" \
  -H "X-GitHub-Event: ping" \
  -d '{"zen":"test"}'
```

**Fix**:
1. Verifica firewall
2. Verifica plugin sicurezza
3. Controlla "Recent Deliveries" su GitHub

### Problema: Aggiornamento fallisce

**Diagnosi**:
```
WordPress → Git Updater → Log
→ Guarda ultimo errore
```

**Fix comuni**:
```bash
# Permessi
sudo chown -R www-data:www-data /var/www/html/wp-content/plugins/fp-git-updater
sudo chmod -R 755 /var/www/html/wp-content/plugins/fp-git-updater

# Spazio disco
df -h

# PHP timeout
# Aggiungi in wp-config.php:
set_time_limit(300);
```

### Problema: Build GitHub fallisce

**Diagnosi**:
```
GitHub → Actions → Click sul workflow rosso → Vedi logs
```

**Fix**:
```bash
# Test build locale
./scripts/build.sh

# Verifica sintassi
php -l fp-git-updater.php
php -l includes/*.php
```

---

## 📈 Scaling

### Per 100+ Siti:

**Opzione 1 - Webhook Stagger**:
GitHub invia webhook in batch automaticamente.

**Opzione 2 - CDN**:
```yaml
# .github/workflows/build-release.yml
- name: Upload a CDN
  run: |
    aws s3 cp fp-git-updater.zip s3://tuo-bucket/plugins/
    # Siti scaricano da CDN invece che da GitHub
```

**Opzione 3 - Custom Endpoint**:
Crea endpoint che gestisce code:
```php
// Ricevi webhook
// Aggiungi a queue
// Processa in background (WP Cron)
```

---

## ✅ Checklist Deploy Completo

### Pre-Deploy
- [ ] Plugin testato localmente
- [ ] Documentazione aggiornata
- [ ] CHANGELOG aggiornato
- [ ] Versione incrementata
- [ ] Backup database fatto

### Deploy
- [ ] Push su GitHub
- [ ] GitHub Actions verde ✅
- [ ] ZIP scaricato
- [ ] Plugin installato su WordPress
- [ ] Token GitHub configurato
- [ ] Webhook GitHub configurato
- [ ] Test connessione ok
- [ ] Test push funziona

### Post-Deploy
- [ ] Siti si aggiornano automaticamente
- [ ] Log puliti (no errori)
- [ ] Email notifiche arrivano
- [ ] Monitoraggio attivo
- [ ] Documentazione deploy salvata

---

## 🎉 Deploy Completato!

Il tuo sistema è ora:
- ✅ Completamente automatizzato
- ✅ Self-updating
- ✅ Monitorato
- ✅ Scalabile
- ✅ Production-ready

**Push e dimentica! 🚀**

---

## 📞 Supporto

Problemi durante il deploy?

1. Consulta [GITHUB_ACTIONS.md](.github/GITHUB_ACTIONS.md)
2. Verifica [TEST.md](TEST.md)
3. Controlla logs GitHub Actions
4. Controlla logs WordPress

**Tutto dovrebbe funzionare al primo colpo!** 🎊
