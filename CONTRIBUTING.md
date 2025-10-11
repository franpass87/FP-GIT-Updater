# Contribuire a FP Git Updater

Grazie per il tuo interesse nel contribuire a FP Git Updater! 🎉

Questo è un plugin personalizzato, ma accettiamo volentieri contributi dalla community.

## Come Contribuire

### Segnalare Bug

Se trovi un bug:

1. **Controlla** se il bug è già stato segnalato nelle [Issues](../../issues)
2. Se non esiste, **apri una nuova issue** includendo:
   - Descrizione chiara del problema
   - Passi per riprodurre il bug
   - Comportamento atteso vs comportamento effettivo
   - Versione WordPress, PHP, e del plugin
   - Screenshot o log (se rilevanti)

### Suggerire Nuove Funzionalità

Hai un'idea per migliorare il plugin?

1. **Apri una issue** con l'etichetta "enhancement"
2. Descrivi:
   - Il problema che vuoi risolvere
   - La soluzione proposta
   - Alternative considerate
   - Perché sarebbe utile agli altri utenti

### Inviare Pull Request

Vuoi contribuire con codice?

1. **Fai fork** del repository
2. **Crea un branch** per la tua feature:
   ```bash
   git checkout -b feature/nome-feature
   ```
3. **Fai le tue modifiche** seguendo le linee guida sotto
4. **Testa** le tue modifiche
5. **Commit** con messaggi descrittivi:
   ```bash
   git commit -m "Add: descrizione feature"
   ```
6. **Push** al tuo fork:
   ```bash
   git push origin feature/nome-feature
   ```
7. **Apri una Pull Request** verso il branch `main`

## Linee Guida per il Codice

### Standard di Codice

- Segui gli [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- Usa indentazione con **4 spazi** (no tab)
- Commenta il codice complesso
- Usa nomi di variabili e funzioni descrittivi

### PHP

```php
// ✅ Bene
public function get_latest_commit() {
    $settings = get_option('fp_git_updater_settings');
    // ...
}

// ❌ Male
public function glc() {
    $s = get_option('fps');
    // ...
}
```

### JavaScript

```javascript
// ✅ Bene
function testConnection() {
    const button = $('#fp-test-connection');
    // ...
}

// ❌ Male
function tc() {
    const b = $('#ftc');
    // ...
}
```

### CSS

```css
/* ✅ Bene */
.fp-git-updater-wrap {
    max-width: 1200px;
    margin: 0 auto;
}

/* ❌ Male */
.fgu {
    max-width: 1200px;
}
```

## Struttura dei Commit

Usa prefissi chiari nei messaggi di commit:

- `Add:` - Nuove funzionalità
- `Update:` - Modifiche a funzionalità esistenti
- `Fix:` - Correzione bug
- `Refactor:` - Refactoring senza cambiare funzionalità
- `Docs:` - Solo documentazione
- `Style:` - Formattazione, spazi, etc.
- `Test:` - Aggiunta o modifica test

Esempi:
```
Add: support for multiple repositories
Fix: webhook signature verification fails
Update: improve error handling in updater
Docs: add troubleshooting section
```

## Testing

Prima di inviare una PR, assicurati che:

- [ ] Il codice non genera errori PHP
- [ ] Il codice funziona su WordPress 5.0+
- [ ] Il codice funziona su PHP 7.4+
- [ ] Hai testato sia con repository pubblici che privati
- [ ] Hai testato il webhook end-to-end
- [ ] L'interfaccia admin funziona correttamente
- [ ] I log vengono registrati correttamente

## Domande?

Se hai domande:

1. Controlla la [documentazione](README.md)
2. Cerca nelle [Issues esistenti](../../issues)
3. Apri una nuova issue con l'etichetta "question"

## Codice di Condotta

Sii rispettoso e costruttivo. Questo progetto vuole essere un ambiente accogliente per tutti.

## Licenza

Contribuendo, accetti che i tuoi contributi saranno licenziati sotto GPL v2 o successiva.

---

Grazie per aver contribuito! 🙏
