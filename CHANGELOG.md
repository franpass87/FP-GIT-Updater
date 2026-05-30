## [1.9.1] - 2026-05-30

### Fixed

- **Cron orfano `fp_git_updater_cleanup_backup` (hook legacy)**: aggiunta la pulizia dell'hook single-event legacy (versioni < 1.9) in `deactivate()` (`fp-git-updater.php`) e `uninstall.php`. In precedenza veniva ripulito solo `fp_git_updater_cleanup_old_backups` (plurale): il vecchio evento per-backup restava schedulato e, a plugin disattivo, WordPress lo ritentava a ogni page-load generando in loop `Errore nell'evento Cron non programmato … codice di errore: could_not_set` nel log (riscontrato su Villa Piaggia). Il fix evita che il residuo si ripresenti sui siti dove il plugin viene disattivato.

## [1.9.0] - 2026-05-29

### Added

- **`AjaxSecurityHelper`** (`includes/AjaxSecurityHelper.php`): nuova classe utility che centralizza il boilerplate AJAX duplicato in oltre 15 handler di `Admin.php`:
  - `AjaxSecurityHelper::verify(array $opts)` — verifica nonce + capability + metodo HTTP in una sola chiamata, termina la richiesta con `wp_send_json_error` (403/400/405) in caso di fail.
  - `AjaxSecurityHelper::check(array $opts)` — variante "soft" che ritorna `true|WP_Error` invece di terminare.
  - `AjaxSecurityHelper::parse_string_list($raw)` — sanitizza un input che può essere array nativo, JSON stringificato o CSV (pattern duplicato in REST endpoint).
  Adozione opt-in: i metodi esistenti continuano a funzionare invariati; nuovi handler (e refactor futuri) possono ridurre il codice del 60% chiamando l'helper.

### Changed

- **`declare(strict_types=1)`** aggiunto ai file core a basso rischio:
  - `Logger.php`, `Encryption.php`, `RateLimiter.php`, `ApiCache.php`, `Migration.php`, `I18nHelper.php`, `SettingsBackup.php`.
  - Volutamente NON aggiunto ad `Admin.php`, `Updater.php`, `WebhookHandler.php`, `MasterEndpoint.php`, `ReceiveBackupEndpoint.php` per evitare esposizione di type juggling latente in flussi critici (REST/upload/update). Verrà fatto incrementalmente.
- **Classmap Composer rigenerato** (`vendor/composer/autoload_*.php`) con `--optimize --classmap-authoritative` per registrare `AjaxSecurityHelper` (15 classi totali). Necessario perché il classmap è authoritative (vedi memory `fp_woo_composer_authoritative_pitfall`).

### Deferred

- **Split di `Admin.php` (2070 righe, god class)** in `AjaxHandler` + `PageRenderer` + `AssetEnqueuer` come suggerito dall'audit architetturale è stato **deliberatamente rimandato** a una release futura. Motivazione: senza la possibilità di testare manualmente nel browser ogni AJAX handler (computer-use MCP disconnesso in questa sessione), spezzare i `add_action('wp_ajax_*', [$this, 'method'])` rischia regressioni silenziose in produzione. La preparazione (AjaxSecurityHelper) è già in mano: lo split potrà avvenire incrementalmente nelle prossime versioni con test manuali per ogni gruppo di handler estratto.

## [1.8.2] - 2026-05-29

### Security

- **SSRF protection sui webhook trigger-sync verso i client** (`MasterEndpoint.php`): nuovo helper `is_safe_outbound_url()` che rifiuta URL non-http/https, hostname senza dominio (es. `intranet`), loopback (`localhost`, `127.x`) e IP in range privati/link-local (`10.x`, `192.168.x`, `169.254.x`). Applicato a `get_trigger_sync_endpoint_for_client()` → coperti automaticamente `trigger_sync_client_blocking()` e `push_sync_to_clients()`. Blocca un client_id maliziono che punti a Redis interno o AWS IMDS.
- **Master client secret cifrato in DB** (`MasterEndpoint.php` + `fp-git-updater.php`): nuovi filtri trasparenti `pre_update_option_*` (cifra al salvataggio) e `option_*` (decifra in lettura) sul `fp_git_updater_master_client_secret`. Usa l'esistente `Encryption` AES-256-CBC. Migration automatica: secret legacy in chiaro continuano a funzionare e vengono cifrati al primo save successivo. Codice chiamante invariato.
- **Path traversal defense in profondità su plugin_slug** (`Updater.php:1387+`): dopo il `preg_replace`, ulteriore check su `..`, `/`, `\\`, `\0`; `basename()` esplicito; doppia verifica via `realpath()` che il path risolto sia dentro `WP_PLUGIN_DIR`. Throw + log + cleanup tmp dir se sospetto.
- **Webhook signature SHA-256 only** (`WebhookHandler.php:256+`): rimosso il fallback alla legacy `X-Hub-Signature` (HMAC-SHA-1) deprecato da GitHub. Verifica esplicita del prefisso `sha256=`. Loggata e rifiutata qualunque firma SHA-1 ricevuta (downgrade attack protection).
- **MIME type + magic bytes check sugli upload ZIP** (`ReceiveBackupEndpoint.php:111+`): dopo `move_uploaded_file()` verifica `finfo_file()` con whitelist mime (`application/zip` + varianti) e signature ZIP (`PK\x03\x04` o varianti). File rifiutati → unlink + 415 Unsupported Media Type.

### Fixed

- **Lock transient: rilascio garantito anche su eccezioni non gestite** (`Updater.php:run_plugin_update`): wrap try/finally globale attorno al body del metodo. Idempotente con i `delete_transient` esistenti nei branch normali. Risolve il caso in cui un'eccezione fatal lasciava il lock attivo per 10 minuti bloccando ogni successivo update dello stesso plugin.
- **Race condition cron pulizia backup** (`Updater.php:cleanup_old_backups`): nuovo transient lock `fp_git_updater_cleanup_backups_lock` (10 min) per prevenire due esecuzioni concorrenti del cron che tentavano di eliminare gli stessi file. Rilascio in `finally`.
- **Cache "versione GitHub" non invalidata sui webhook push** (`WebhookHandler.php:handle_webhook`): dopo match plugin → `delete_transient('fp_git_updater_github_version_*')` + `fp_git_updater_commit_info_*` per il plugin coinvolto. L'admin vede subito la nuova versione disponibile senza attendere il TTL di 5 minuti.
- **Logger: hard cap 10k righe + lock anti-overlap** (`Logger.php:clear_old_logs`): se la tabella supera le 10.000 righe (cron saltato per mesi), `TRUNCATE` invece di `DELETE` incrementale. Nuovo lock transient `fp_git_updater_logs_cleanup_lock` previene `OPTIMIZE TABLE` paralleli che lockerebbero la tabella.

### Performance

- **In-memory cache della lista client connessi** (`MasterEndpoint.php`): nuova proprietà statica `$connected_clients_cache` evita 5+ letture autoload-option per ogni page-load admin (Admin enqueue + render tabelle deploy + ecc.). Auto-invalidata via hook `updated_option` / `added_option` / `deleted_option` quando un qualsiasi chiamante modifica `OPTION_CONNECTED_CLIENTS` (Master endpoint o handler Admin).

### Changed

- **GitHub username predefinito configurabile** (`Admin::get_default_github_username()`): rimosso il valore hardcoded `FranPass87` da 6 punti diversi (Admin.php, Updater.php, 3 partial template). Nuovo helper centrale con fallback a 3 livelli: 1) costante `FP_GIT_UPDATER_DEFAULT_GITHUB_USERNAME` in `wp-config.php`, 2) filtro `fp_git_updater_default_github_username`, 3) default storico `FranPass87`. Comportamento invariato per chi non configura nulla.
- **Archiviati 9 file di documentazione storica** (BUGFIX-CHANGELOG, BUGFIX-DEEP-AUDIT-2025-11-03, BUGFIX-v1.2.4, CHANGELOG-v1.2.1, CHANGELOG-v1.2.2, FEATURE-*, RELEASE-v1.2.3-FINALE, REFACTORING_PSR4) spostati in `docs/archived/` per pulire la root del repo. Mantenuti in root solo README.md e CHANGELOG.md.

## [1.8.1] - 2026-05-29

### Fixed

- **Bottoni azione tabella «Clienti collegati» che traboccavano dal bordo destro della card**: la colonna «Azione» della tabella `.fp-master-clients-table` aveva `width: 80px` ma contiene 3 bottoni (aggiorna versioni / modifica / rimuovi cliente) che richiedono ~140px. Allargata la colonna a 140px con classe dedicata `.fp-master-clients-actions-col`, allineamento a destra e `white-space: nowrap`. Ribilanciate le altre colonne (28% / 35% / auto / 140px). Aggiunto safety net responsive: sotto i 900px la card scrolla orizzontalmente. Rimosso lo header vuoto della colonna, sostituito con `screen-reader-text` per accessibilità.

## [1.8.0] - 2026-05-29

### Added

- **Card plugin collassabili (accordion)**: nel tab «Plugin e Distribuzione» ogni card plugin è ora collassabile cliccando l'header o il chevron a sinistra. Stato persistito in `localStorage` per id plugin. Default intelligente: se ci sono più di 3 card, parte tutto collassato tranne le card con aggiornamento pending (così le azioni urgenti restano in vista). Aggiunti pulsanti «Espandi tutti» / «Comprimi tutti» nella toolbar sopra la lista. Riduce drasticamente lo scroll per chi gestisce molti plugin.
- **Dialog di conferma personalizzato** (`fpConfirmDialog`): sostituisce i popup `confirm()`/`alert()` nativi del browser. Modal con titolo, messaggio contestuale (es. nome plugin/cliente), pulsanti «Annulla» / azione, varianti `warning` / `danger`, focus trap minimale (Tab/Shift+Tab tra i bottoni), chiusura con Esc o click su backdrop, restore del focus al pulsante originario. Esposto come `window.fpConfirmDialog` per estensioni future.
- **Heading espliciti «Step 1: Configura il Master»** e **«Step 2: Gestisci plugin e distribuisci»** sopra le rispettive sezioni, per chiarire la sequenza di setup ai nuovi utenti.
- **Spinner CSS via `aria-busy="true"`**: aggiunto stile globale `.button[aria-busy="true"]::after` che mostra uno spinner rotante accanto al testo del bottone durante operazioni AJAX. Migliora il feedback visivo durante deploy massivi e sincronizzazioni.

### Changed

- **Microcopy uniformata** sui bottoni di installazione/distribuzione:
  - «Installa su questo sito» (plugin non ancora installato) / «Aggiorna su questo sito» (versione presente) / «Aggiorna ora» (update GitHub pendente, CTA enfatica).
  - «Distribuisci ai selezionati» nella sezione clienti (prima «Installa sui selezionati») per distinguere chiaramente l'installazione locale dalla distribuzione remota.
- **Token CSS riorganizzati e documentati** in `:root`: le due famiglie `--fp-*` (WordPress native palette) e `--fpdms-*` (brand FP Digital Marketing Suite) sono ora separate da commenti chiari che spiegano quando usare ciascun set. Nessun valore cambiato per evitare regressioni visive.
- **Header del Master** semplificato: rimosso il badge numerato interno «1» (ridondante con il nuovo heading Step 1 sopra).

### Accessibility

- **aria-label espliciti** sui pulsanti icona «Rimuovi», «Modifica», «Aggiorna versioni» di plugin e clienti — i nomi includono il contesto (es. «Rimuovi cliente villa-dianella dalla lista») per gli screen reader.
- **Focus visibile** sul chevron accordion e sui pulsanti del dialog di conferma (outline 2px in `--fpdms-primary`).
- **`prefers-reduced-motion`** rispettato per animazioni di accordion, spinner e dialog.
- **Toggle accordion accessibile da tastiera** con `aria-expanded` e `aria-controls` corretti.

## [1.7.8] - 2026-05-29

### Fixed

- **Versione FP-Remote-Bridge non visualizzata sui client** (regressione UI): la card del plugin Bridge e i badge versione mostravano `—` su molti siti anche se il Bridge era installato e aggiornato. Causa: `MasterEndpoint::get_clients_plugin_versions()` cercava solo lo slug richiesto, ma il Bridge dopo self-update vive nella cartella `fp-remote-bridge-update/` invece di `fp-remote-bridge/`, quindi i client inviano la versione sotto la chiave alternativa. Aggiunto helper `get_plugin_slug_aliases()` che riconosce entrambi gli slug Bridge come equivalenti; applicato anche a `get_clients_with_plugin()` e `get_client_plugin_version()` per coerenza. Nessuna chiamata REST aggiuntiva: legge i dati già presenti nel registry `fp_git_updater_connected_clients`.

## [1.7.7] - 2026-05-27

### Fixed

- **Selettore repository GitHub limitato a 30**: la modal «Seleziona Repository da GitHub» chiamava `GET /users/{username}/repos` senza paginazione, quindi GitHub restituiva solo i primi 30 repository (default API). I repo oltre il 30° (es. `FP-WooCommerce` su account con 35+ repo pubblici) non comparivano nemmeno cercandoli. Ora `ajax_load_github_repos()` scorre tutte le pagine con `per_page=100` fino a un cap di 10 pagine (1000 repo). Cache transient versionata (`_v2_`) per invalidare automaticamente la cache pre-fix.

## [1.7.6] - 2026-05-27

### Fixed

- **Deploy client MCP/slug**: `get_plugin_by_id_or_slug()` risolve anche lo slug derivato da `github_repo` (es. `fp-experiences` → `plugin_*` senza `plugin_slug` in config). Sblocca `master-updates-status` + trigger-sync quando la coda deploy usa lo slug.

## [1.7.5] - 2026-05-24

### Added

- **REST automazione deploy (secret Master)**: `POST /wp-json/fp-git-updater/v1/deploy-update` autorizza aggiornamento plugin sui client collegati e invia `trigger-sync` (async o bloccante con `blocking: true`). `POST /deploy-install-push` per installazioni su client selezionati.
- **`MasterEndpoint::orchestrate_deploy_update()` / `orchestrate_deploy_install()`**: pipeline usata da MCP Cursor (`fp_master_deploy_plugins`) per deploy automatico post-ottimizzazione plugin.

## [1.7.4] - 2026-05-21

### Added

- **Admin Master**: sezione «Clienti rimossi» con pulsante **Ripristina** per siti in blacklist (`fp_git_updater_removed_clients`) che sincronizzano con successo ma non compaiono in «Clienti collegati».

### Changed

- Messaggio guida quando nessun cliente collegato: rimanda alla sezione rimossi se la sync sul cliente è verde.

## [1.7.3] - 2026-05-21

### Fixed

- **cursor-mcp-sites**: include clienti con Bridge in cartella `fp-remote-bridge-update` (oltre a `fp-remote-bridge`) e versione da `plugin_versions` coerente.
# Changelog

All notable changes to FP Updater will be documented in this file.

## [1.7.2] - 2026-05-21

### Added

- **Runtime diagnostics API** (`includes/Services/Diagnostics/RuntimeDiagnostics.php` + `api.php`): `fp_gitupdater_get_runtime_diagnostics()` per FP Remote Bridge sezione `gitupdater_runtime` (Master: client collegati, deploy queue; tutti: pending, log scrubbed). Pending letti da opzioni senza invocare `get_pending_updates()` (evita side-effect di pulizia).

## [1.7.1] - 2026-05-14
### Added
- Endpoint read-only `GET /wp-json/fp-git-updater/v1/cursor-mcp-sites` (secret Master) per elencare i client con `fp-remote-bridge` e sincronizzare MCP Cursor.

## [1.7.0] - 2026-04-05
### Added
- Admin: classe body `fpgitupdater-admin-shell`, wrapper `fpgitupdater-admin-page`, banner titolo pagina (gradiente FP, badge versione, `h1` solo screen-reader) su Impostazioni, Log e Backup.
- CSS: componenti `fpgitupdater-page-header`, `fpgitupdater-card-block`, `fpgitupdater-btn*`, tabelle `fpgitupdater-wp-table` (thead gradiente), form-table e focus ring token `--fpdms-*`.

### Changed
- Interfaccia admin allineata al **FP Admin UI Design System** (token DMS, card, tab attive, plugin card, badge log, azioni form).
- Pagina Backup: rimossi stili inline principali; testi user-facing passati a funzioni di traduzione dove toccati.
- Enqueue asset admin: riconoscimento anche tramite `$_GET['page']` per hook sotto-menu (coerenza con linee guida FP).

## [1.6.20] - 2026-04-05
### Changed
- Modal distribuzione Master: grafica allineata al **FP Admin UI Design System** (token `--fpdms-*`, header con gradiente viola come gli altri plugin FP, card pannello, barra avanzamento e log con colori success/danger canonici, classi `fpgitupdater-deploy-modal__*`).

## [1.6.19] - 2026-04-05
### Added
- Modalità Master: durante «Installa su clienti» e «Aggiorna tutti» viene mostrato un pannello con barra di avanzamento, fase corrente e log per ogni sito contattato (trigger-sync sequenziale verso FP Remote Bridge).

### Changed
- `authorize_deploy_install` / `authorize_deploy_update` accettano `$defer_remote_trigger` (default false): REST e flussi esistenti restano invariati; l’admin AJAX differisce il push e usa `fp_git_updater_deploy_trigger_client` per cliente.

## [1.6.18] - 2026-03-22
### Fixed
- `error_log` in Logger::clear_old_logs condizionato a WP_DEBUG (completamento no-debug-in-production).

## [1.6.17] - 2026-03-22
### Fixed
- error_log condizionati a WP_DEBUG: bootstrap fatale, Logger fallback, ReceiveBackupEndpoint .htaccess (no-debug-in-production).

## [1.6.16] - 2026-03-19
### Fixed
- Clienti doppioni: canonizzazione client_id tramite host estratto da `X-FP-Site-URL` del Bridge (previene mismatch tra varianti dello stesso dominio)
- Blacklist rimozione clienti ora include anche l'host estratto dall'URL salvato, impedendo ricomparsa con alias diversi
- Corretto bug in `ajax_remove_client` che leggeva URL del client dopo averlo rimosso dall'array

## [1.6.15] - 2026-03-19
### Fixed
- Duplicati clienti: normalizzazione ora rimuove "www." (es. www.example.com = example.com) e rimuove tutte le chiavi che normalizzano allo stesso valore

## [1.6.14] - 2026-03-19
### Added
- Nome sito nella lista clienti: il Master mostra il nome del sito (Impostazioni > Generale) inviato dal Bridge, con fallback al client_id/dominio

## [1.6.13] - 2026-03-19
### Fixed
- Rinomina cliente: il vecchio nome non riappare più nella lista. Normalizzazione client_id (es. `https://example.com` ↔ `example.com`) e alias per entrambe le forme, così le riconnessioni del Bridge aggiornano l'entry rinominata invece di creare duplicati

## [1.6.12] - 2026-03-19
### Fixed
- Modal modifica cliente: corretto salvataggio e chiusura. Ora usa `$.ajax` con `dataType: 'json'`, gestione errori migliorata (403/sessione scaduta, parsererror), `preventDefault`/`stopPropagation` sul click Salva
- Pulsante Modifica cliente incluso nell'HTML restituito da "Aggiorna elenco" (ajax_refresh_clients)

## [1.6.11] - 2026-03-18
### Fixed
- Risolto errore critico in admin dovuto a testo corrotto nella dichiarazione della costante `OPTION_CONNECTED_CLIENTS` in `MasterEndpoint`
- Ripulita coda corrotta del file `CHANGELOG.md` con righe duplicate/troncate

## [1.6.10] - 2026-03-15
### Added
- Alias client_id dopo rinomina: se rinomini un cliente, il sito che si riconnette con il vecchio ID aggiorna l'entry con il nome nuovo (il vecchio nome non riappare)

## [1.6.9] - 2026-03-10
### Changed
- Controlli aggiornamenti solo manuali: rimosso cron automatico per evitare rate limit API GitHub

## [1.6.8] - 2026-03-08
### Added
- Pulsante modifica cliente con modal per correggere ID e URL

## [1.6.7] - 2026-03-08
### Fixed
- Alias noti `fp-remote-bridge` + fallback generico slug matching

## [1.6.6] - 2026-03-08
### Fixed
- Slug matching parziale per cartelle plugin con nome diverso

## [1.6.4] - 2026-03-08
### Fixed
- Badge versione: fallback slug fuzzy + `n/a` se plugin non installato

## [1.6.3] - 2026-03-08
### Fixed
- Fallback URL cliente migliorato con messaggio errore dettagliato

## [1.6.2] - 2026-03-08
### Fixed
- Sync versioni: errori visibili, `e.preventDefault`, messaggi dettagliati

## [1.6.0] - 2026-03-07
### Added
- Pulsante sincronizza versioni sui clienti selezionati

### Fixed
- Logger prepare, SettingsBackup validazione+esc_url, uninstall opzioni master, XSS plugin-template
- Namespace `WP_REST_Response`, rate limiter IP spoofing, Encryption IV+decrypt, Migration lock, htaccess backup
- `commit_short->latest_commit_short`, WP_Filesystem check, fallback `??` in Updater

## [1.5.4] - 2026-03-07
### Added
- Bottone refresh versioni per ogni cliente
- Endpoint `plugin-versions` su Bridge

## [1.5.2] - 2026-03-07
### Fixed
- UI duplicati rimossi
- Supporto POST a `master-updates-status` per `installed_plugins` illimitati

## [1.5.1] - 2026-03-06
### Added
- Endpoint `/deploy-and-reload` per aggiornare Bridge con reload forzato

## [1.5.0] - 2026-03-05
### Added
- Pulsante elimina cliente nella lista clienti collegati

## [1.4.8] - 2026-03-05
### Added
- Push `trigger-sync` ai client dopo deploy

### Fixed
- Slug maiuscole normalizzate, pulizia lista deploy

## [1.4.7] - 2026-03-05
### Fixed
- `deploy-install` non accumula duplicati e pulisce lista scaduta

## [1.3.8] - 2026-03-04
### Fixed
- Rimosso hash `#tab-X` dall'URL per evitare scroll anchor

## [1.3.7] - 2026-03-04
### Added
- Griglia clienti nella sezione deploy plugin

## [1.3.6] - 2026-03-04
### Added
- Mostra commit SHA e messaggio nelle card plugin e self-update

## [1.3.5] - 2026-03-04
### Added
- Favicon SVG personalizzata nelle pagine admin (icona sync blu-viola)

## [1.3.4] - 2026-03-03
### Fixed
- Auto-updater, self-update ricorsione, Master clienti e debug
- Namespace `WP_Error` + self-update fallback
- Lock orfano: auto-sblocco dopo 15 min + pulsante "Sblocca e riprova"

## [1.3.3] - 2026-03-02
### Fixed
- Gestione errori critici: `get_backup_stats`, plugin-item try-catch, admin.js nullish coalescing
- Usa repo `franpass87/FP-GIT-Updater` per auto-aggiornamento

## [1.3.2] - 2026-03-02
### Fixed
- Backup system, AJAX handlers, cron, UI/UX, Migration init

## [1.2.9] - 2026-01-10
### Added
- Visualizzazione versione installata e GitHub nella sezione auto-aggiornamento

### Changed
- Rimossi tutti i keyframes e animazioni decorative non utilizzate

## [1.2.8] - 2026-01-08
### Added
- Sistema gestione backup con limiti e pulizia automatica
- Fallback automatico per errore HTTP 401 durante download aggiornamenti
- Pulizia automatica file non necessari durante installazione

## [1.2.7] - 2025-12-31
### Changed
- Usa `rename` invece di `copy_dir`: molto più veloce e affidabile su hosting condivisi

## [1.2.5] - 2025-11-05
### Fixed
- Auto-aggiornamento completo: assegnato ID speciale `fp_git_updater_self`

## [1.0.0] - 2025-10-xx
### Added
- Release iniziale: aggiornamenti automatici plugin WordPress da GitHub
- Supporto repo privati con token GitHub
- Dashboard admin con stato aggiornamenti
