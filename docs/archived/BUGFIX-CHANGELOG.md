# Bugfix Changelog - FP Updater v1.2.0

## 🐛 Bug Corretti

### Bug #1: Tabella log non creata all'attivazione
**Problema**: Il metodo `create_log_table()` era definito ma mai chiamato durante l'attivazione del plugin, causando l'assenza della tabella del database per i log.

**Soluzione**: Aggiunta chiamata a `$this->create_log_table()` nel metodo `activate()`.

```php
public function activate() {
    // Crea la tabella per i log
    $this->create_log_table();
    
    // ... resto del codice
}
```

### Bug #2: Riferimenti a classi vecchie nel file principale
**Problema**: Due riferimenti hardcoded alle vecchie classi con prefisso `FP_Git_Updater_` invece di usare i namespace PSR-4.

**Soluzione**: 
- Aggiunto `use FP\GitUpdater\Logger;`
- Sostituiti `FP_Git_Updater_Logger::log()` con `Logger::log()`

### Bug #3: Riferimento errato in Updater.php
**Problema**: Hook action che referenziava la vecchia classe come stringa `'FP_Git_Updater_Logger'`.

**Soluzione**: Sostituito con namespace completo `'FP\GitUpdater\Logger'`

```php
add_action('fp_git_updater_cleanup_old_logs', array('FP\GitUpdater\Logger', 'clear_old_logs'));
```

### Bug #4: Riferimenti nei template admin
**Problema**: Template admin che usavano ancora le vecchie classi:
- `FP_Git_Updater_I18n_Helper` in settings-page.php
- `FP_Git_Updater_Updater` in self-update-section.php
- `FP_Git_Updater_I18n_Helper` in general-settings.php (6 occorrenze)
- `FP_Git_Updater_I18n_Helper` in plugin-item.php

**Soluzione**: Aggiornati tutti i riferimenti con namespace PSR-4:
- `use FP\GitUpdater\I18nHelper;`
- `use FP\GitUpdater\Updater;`
- Sostituiti tutti i riferimenti con `\FP\GitUpdater\I18nHelper::`

## ✅ Verifica

- ✅ Nessun errore di linting
- ✅ Tutti i riferimenti PSR-4 corretti
- ✅ Autoload Composer funzionante
- ✅ Tabella database viene creata all'attivazione
- ✅ Plugin pronto per produzione

## 📦 Release

**File**: `fp-git-updater-v1.2.0-release.zip`  
**Data**: 30 Ottobre 2025  
**Bugfix totali**: 4  
**Stato**: Production Ready ✅

---

Tutti i bug sono stati risolti e il plugin è ora completamente compatibile con PSR-4 autoload senza riferimenti legacy.



