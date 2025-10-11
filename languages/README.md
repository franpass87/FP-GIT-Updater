# Directory Traduzioni - FP Git Updater

Questa directory contiene i file per le traduzioni del plugin.

## 📁 File Presenti

- **`fp-git-updater.pot`** - File template per traduzioni
- **`README.md`** - Questo file

## 🌍 Come Aggiungere una Traduzione

### 1. Usando Poedit (Consigliato)

1. Scarica e installa [Poedit](https://poedit.net/)
2. Apri Poedit e seleziona "File" → "Nuovo da file POT/PO"
3. Seleziona il file `fp-git-updater.pot`
4. Scegli la lingua (es: Italiano, Inglese, Francese, ecc.)
5. Traduci tutte le stringhe
6. Salva il file - Poedit genererà automaticamente:
   - `fp-git-updater-it_IT.po` (file sorgente)
   - `fp-git-updater-it_IT.mo` (file compilato)

### 2. Manualmente

```bash
# Copia il file POT
cp fp-git-updater.pot fp-git-updater-it_IT.po

# Modifica il file .po con un editor di testo
# Traduci tutte le stringhe msgstr ""

# Compila il file .mo
msgfmt fp-git-updater-it_IT.po -o fp-git-updater-it_IT.mo
```

### 3. Usando WP-CLI

```bash
# Genera il file .pot (se serve aggiornarlo)
wp i18n make-pot /path/to/plugin /path/to/plugin/languages/fp-git-updater.pot

# Aggiorna le traduzioni esistenti
wp i18n update-po /path/to/plugin/languages/fp-git-updater.pot /path/to/plugin/languages/
```

## 📝 Convenzioni Nomi File

Le traduzioni devono seguire questa convenzione:

```
fp-git-updater-{locale}.po
fp-git-updater-{locale}.mo
```

Dove `{locale}` è il codice lingua di WordPress:

| Lingua | Locale | File PO | File MO |
|--------|--------|---------|---------|
| Italiano | it_IT | fp-git-updater-it_IT.po | fp-git-updater-it_IT.mo |
| Inglese (UK) | en_GB | fp-git-updater-en_GB.po | fp-git-updater-en_GB.mo |
| Francese | fr_FR | fp-git-updater-fr_FR.po | fp-git-updater-fr_FR.mo |
| Spagnolo | es_ES | fp-git-updater-es_ES.po | fp-git-updater-es_ES.mo |
| Tedesco | de_DE | fp-git-updater-de_DE.po | fp-git-updater-de_DE.mo |

## 🔄 Rigenerare il File POT

Se aggiungi nuove stringhe traducibili al plugin:

```bash
# Metodo 1: Script incluso
./scripts/generate-pot.sh

# Metodo 2: WP-CLI manuale
wp i18n make-pot . languages/fp-git-updater.pot --domain=fp-git-updater
```

## ✅ Testare le Traduzioni

1. Carica i file `.mo` in questa directory
2. Vai su WordPress → Impostazioni → Generali
3. Cambia "Lingua del sito" nella lingua tradotta
4. Vai su FP Git Updater per vedere la traduzione

## 📊 Statistiche Attuali

- **Stringhe totali**: ~150
- **Lingue disponibili**: Italiano (default)
- **Lingue in sviluppo**: -

## 🤝 Contribuire con Traduzioni

Se vuoi contribuire con una traduzione:

1. Crea i file `.po` e `.mo` per la tua lingua
2. Testa la traduzione
3. Invia una pull request su GitHub con:
   - File `.po` (modificabile)
   - File `.mo` (compilato)
   - Screenshot della traduzione in azione

## 🔗 Risorse Utili

- [WordPress in Your Language](https://make.wordpress.org/polyglots/)
- [Poedit](https://poedit.net/)
- [WP-CLI i18n](https://developer.wordpress.org/cli/commands/i18n/)
- [Codex WordPress: i18n](https://codex.wordpress.org/I18n_for_WordPress_Developers)

---

**Nota**: WordPress carica automaticamente i file di traduzione da questa directory quando:
1. Il file ha il nome corretto (`fp-git-updater-{locale}.mo`)
2. Il locale corrisponde alla lingua di WordPress
3. Il plugin ha chiamato `load_plugin_textdomain()`
