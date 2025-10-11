# 🤖 GitHub Actions - Automazione

## Overview

Questo plugin include **3 workflow GitHub Actions** per automazione completa:

1. **Build & Release** - Crea ZIP ad ogni push/merge
2. **Auto Update Webhook** - Notifica push ai siti WordPress  
3. **Test** - Verifica sintassi e qualità codice

---

## 📦 1. Build e Release (`build-release.yml`)

### Cosa fa:
- ✅ Si attiva ad ogni push su `main`
- ✅ Crea automaticamente il file ZIP del plugin
- ✅ Salva il ZIP come artifact (disponibile per 30 giorni)
- ✅ Crea GitHub Release automatica se pusshi un tag

### Come usare:

#### Build automatico ad ogni merge:
```bash
git add .
git commit -m "Update: nuova funzionalità"
git push origin main
```

**Risultato**: 
- ✅ ZIP creato automaticamente
- ✅ Scaricabile da GitHub Actions → Artifacts
- ✅ Valido per 30 giorni

#### Creare una Release ufficiale:
```bash
# 1. Aggiorna versione nel plugin
# Modifica: fp-git-updater.php → Version: 1.1.0

# 2. Crea tag
git tag -a v1.1.0 -m "Release 1.1.0"
git push origin v1.1.0
```

**Risultato**:
- ✅ ZIP creato
- ✅ GitHub Release creata automaticamente
- ✅ ZIP allegato alla release
- ✅ Note di release generate automaticamente

### Dove trovare il ZIP:

**Dopo ogni push:**
```
GitHub → Repository → Actions → 
Build e Release Plugin → 
Click sul workflow → 
Artifacts → 
Download fp-git-updater
```

**Dopo un tag (Release):**
```
GitHub → Repository → Releases → 
Click sulla release → 
Download fp-git-updater.zip
```

---

## 🔔 2. Trigger Plugin Update (`auto-update-webhook.yml`)

### Cosa fa:
- ✅ Si attiva ad ogni push su `main`
- ✅ Mostra info del commit
- ✅ Conferma che il webhook verrà inviato

### Estensioni opzionali:

#### Notifiche Slack:
```yaml
# Decommenta nel file .github/workflows/auto-update-webhook.yml
- name: Notifica Slack
  uses: 8398a7/action-slack@v3
  with:
    status: custom
    custom_payload: |
      {
        text: "🚀 Plugin aggiornato! Commit: ${{ github.event.head_commit.message }}"
      }
  env:
    SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK_URL }}
```

**Setup Slack**:
1. Crea Webhook su Slack: https://api.slack.com/messaging/webhooks
2. GitHub → Repository → Settings → Secrets → New secret
3. Nome: `SLACK_WEBHOOK_URL`
4. Valore: URL webhook da Slack

#### Notifiche Discord:
```yaml
- name: Notifica Discord
  uses: sarisia/actions-status-discord@v1
  with:
    webhook: ${{ secrets.DISCORD_WEBHOOK }}
    title: "Plugin Aggiornato"
    description: "Commit: ${{ github.event.head_commit.message }}"
```

#### Notifiche Email:
```yaml
- name: Notifica Email
  uses: dawidd6/action-send-mail@v3
  with:
    server_address: smtp.gmail.com
    server_port: 465
    username: ${{ secrets.EMAIL_USERNAME }}
    password: ${{ secrets.EMAIL_PASSWORD }}
    subject: "Plugin aggiornato"
    body: "Commit: ${{ github.event.head_commit.message }}"
    to: admin@example.com
    from: GitHub Actions
```

---

## 🧪 3. Test (`test.yml`)

### Cosa fa:
- ✅ Verifica sintassi PHP su più versioni (7.4, 8.0, 8.1, 8.2)
- ✅ Controlla struttura plugin
- ✅ Verifica header WordPress
- ✅ Statistiche codice
- ✅ Verifica documentazione

### Quando si attiva:
- Push su `main` o `develop`
- Apertura/modifica Pull Request

### Risultato:
- ✅ Badge verde se tutto ok
- ❌ Badge rosso se ci sono errori
- 📊 Report dettagliato per ogni problema

---

## 🎯 Workflow Completo

### Scenario 1: Sviluppo Normale

```bash
# 1. Fai modifiche
vim includes/class-updater.php

# 2. Commit
git add .
git commit -m "Fix: corretto bug aggiornamento"

# 3. Push
git push origin main
```

**Cosa succede automaticamente:**
1. ✅ **Test** verificano sintassi (2-3 min)
2. ✅ **Build** crea ZIP (1-2 min)
3. ✅ **Webhook** viene inviato ai siti WordPress
4. ✅ **Siti** si aggiornano automaticamente
5. ✅ **ZIP** disponibile su GitHub per 30 giorni

### Scenario 2: Release Ufficiale

```bash
# 1. Aggiorna versione
vim fp-git-updater.php
# Cambia: Version: 1.1.0

# 2. Aggiorna CHANGELOG
vim CHANGELOG.md
# Aggiungi note versione 1.1.0

# 3. Commit
git add .
git commit -m "Release: v1.1.0"
git push origin main

# 4. Crea tag
git tag -a v1.1.0 -m "Release v1.1.0 - Descrizione"
git push origin v1.1.0
```

**Cosa succede automaticamente:**
1. ✅ **Test** verificano tutto
2. ✅ **Build** crea ZIP
3. ✅ **Release** GitHub creata automaticamente
4. ✅ **ZIP** allegato alla release
5. ✅ **Note** generate da commit
6. ✅ **Webhook** ai siti
7. ✅ **Siti** si aggiornano

### Scenario 3: Pull Request

```bash
# 1. Crea branch
git checkout -b feature/nuova-funzione

# 2. Fai modifiche
vim includes/class-admin.php

# 3. Push branch
git push origin feature/nuova-funzione

# 4. Apri Pull Request su GitHub
```

**Cosa succede automaticamente:**
1. ✅ **Test** verificano il codice della PR
2. ✅ Risultati visibili nella PR
3. ❌ Se ci sono errori, PR non può essere merged
4. ✅ Se tutto ok, PR può essere merged

---

## 🔧 Configurazione Avanzata

### Badge nel README

Aggiungi badge per mostrare lo stato:

```markdown
# FP Git Updater

![Build](https://github.com/tuousername/fp-git-updater/actions/workflows/build-release.yml/badge.svg)
![Test](https://github.com/tuousername/fp-git-updater/actions/workflows/test.yml/badge.svg)
![Release](https://img.shields.io/github/v/release/tuousername/fp-git-updater)
```

### Auto-deploy su Server Specifici

Aggiungi al workflow `auto-update-webhook.yml`:

```yaml
- name: Deploy su server staging
  uses: appleboy/ssh-action@master
  with:
    host: ${{ secrets.STAGING_HOST }}
    username: ${{ secrets.STAGING_USER }}
    key: ${{ secrets.STAGING_SSH_KEY }}
    script: |
      cd /var/www/staging/wp-content/plugins/fp-git-updater
      git pull origin main
      # Il plugin si auto-aggiorna via webhook
```

### Matrix Testing per WordPress

```yaml
strategy:
  matrix:
    php-version: ['7.4', '8.0', '8.1', '8.2']
    wordpress-version: ['5.9', '6.0', '6.1', '6.2']
```

---

## 📊 Monitoraggio

### Visualizzare Storia Build

```
GitHub → Repository → Actions → 
Click su un workflow → 
Vedi tutti i run passati
```

### Scaricare ZIP da Build Precedenti

```
Actions → Build e Release Plugin → 
Click su un run specifico → 
Artifacts → Download
```

### Logs Dettagliati

Ogni step dei workflow ha log dettagliati:
- Click sul workflow
- Click su un job
- Click su uno step
- Vedi l'output completo

---

## 🚨 Troubleshooting

### Build fallisce?

**Controlla**:
1. Logs del workflow
2. Errori di sintassi PHP
3. File mancanti

**Fix**:
```bash
# Test locale prima di pushare
./scripts/build.sh
```

### Test falliscono?

**Controlla**:
1. Quale versione PHP fallisce
2. Quale file ha errori di sintassi
3. Logs dettagliati

**Fix**:
```bash
# Test sintassi locale
php -l includes/class-updater.php
```

### Release non creata?

**Controlla**:
1. Hai pushato un tag? (`git push origin v1.0.0`)
2. Tag inizia con `v`? (es: `v1.0.0` non `1.0.0`)
3. Permessi repository corretti?

---

## 💡 Best Practices

### 1. Semantic Versioning
```
v1.0.0 - Major.Minor.Patch
v1.0.1 - Patch: bug fix
v1.1.0 - Minor: nuova funzionalità
v2.0.0 - Major: breaking changes
```

### 2. Commit Messages
```bash
git commit -m "Add: nuova funzionalità"
git commit -m "Fix: corretto bug X"
git commit -m "Update: migliorato Y"
git commit -m "Docs: aggiornata guida"
```

### 3. Branch Strategy
```
main        - Production ready
develop     - Development
feature/*   - Nuove funzionalità
bugfix/*    - Correzione bug
release/*   - Preparazione release
```

### 4. Testing Prima di Merge
```bash
# Sempre testa localmente
./scripts/build.sh
# Verifica che crei il ZIP correttamente

# Se possibile, testa su WordPress locale
```

---

## 🎉 Vantaggi

### Automazione Completa
- ✅ Zero azioni manuali
- ✅ Build automatico ad ogni push
- ✅ Release automatiche con tag
- ✅ Test automatici su PR

### Qualità Garantita
- ✅ Test su PHP 7.4, 8.0, 8.1, 8.2
- ✅ Verifica sintassi sempre
- ✅ Controllo struttura plugin
- ✅ PR non mergeable se test falliscono

### Distribuzione Veloce
- ✅ Push → ZIP pronto in 2 minuti
- ✅ Tag → Release pubblica in 3 minuti
- ✅ Webhook → Siti aggiornati automaticamente
- ✅ Zero downtime

---

## 📞 Supporto

Problemi con i workflow?

1. Controlla logs in GitHub Actions
2. Consulta questa guida
3. Verifica configurazione secrets
4. Test build locale con `./scripts/build.sh`

---

**Tutto automatico! Pusha e rilassati! 🚀**
