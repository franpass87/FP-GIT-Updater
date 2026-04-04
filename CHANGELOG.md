# Changelog

All notable changes to FP Updater will be documented in this file.

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
