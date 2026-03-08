/**
 * FP Updater - Admin JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // ===== TAB NAVIGATION =====
        // Funzione per cambiare tab - nasconde tutti e mostra solo quello selezionato
        function switchTab(tabName) {
            if (!tabName) return;
            
            // Nascondi TUTTI i tab contents usando sia CSS che JavaScript
            $('.fp-tab-content').each(function() {
                $(this).removeClass('active');
                $(this).css('display', 'none');
            });
            
            // Rimuovi active da TUTTI i tab items nella navigation
            $('.fp-tab-item').removeClass('active');
            
            // Trova il tab target
            const $targetLink = $('.fp-tab-link[data-tab="' + tabName + '"]');
            const $targetItem = $targetLink.closest('.fp-tab-item');
            const $targetContent = $('#fp-tab-' + tabName);
            
            if ($targetItem.length && $targetContent.length) {
                $targetItem.addClass('active');
                $targetContent.addClass('active');
                $targetContent.css('display', 'block');

                // Aggiorna l'URL senza hash visibile e senza aggiungere voci alla cronologia
                if (history.replaceState) {
                    var cleanUrl = window.location.pathname + window.location.search;
                    history.replaceState(null, null, cleanUrl);
                }
            }
        }
        
        // Gestione click sui tab links
        $(document).on('click', '.fp-tab-link', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const tabName = $(this).data('tab');
            if (tabName) {
                switchTab(tabName);
            }
            return false;
        });
        
        // Inizializzazione al caricamento della pagina
        // Flag per evitare doppia esecuzione
        let tabsInitialized = false;
        
        // Funzione helper per inizializzare i tab
        function initTabs() {
            // Evita doppia esecuzione se già inizializzato correttamente
            if (tabsInitialized) {
                // Verifica solo se i tab sono ancora nello stato corretto
                const activeTab = $('.fp-tab-content.active');
                if (activeTab.length === 1 && activeTab.css('display') !== 'none') {
                    return; // Già inizializzato correttamente
                }
            }
            
            // Nascondi TUTTI i tab usando JavaScript (forza display: none)
            $('.fp-tab-content').each(function() {
                $(this).css('display', 'none');
            });
            
            // Rimuovi active da tutti i tab
            $('.fp-tab-item').removeClass('active');
            $('.fp-tab-content').removeClass('active');
            
            var hash = window.location.hash.replace(/^#tab-?/, '');
            const validTabs = ['plugins', 'settings', 'backup', 'instructions'];
            if (hash === 'master') { hash = 'plugins'; }
            if (hash && validTabs.includes(hash)) {
                switchTab(hash);
            } else {
                // Default: mostra SOLO il tab "plugins"
                $('.fp-tab-link[data-tab="plugins"]').closest('.fp-tab-item').addClass('active');
                $('#fp-tab-plugins').addClass('active').css('display', 'block');
            }

            // Pulisci l'hash dall'URL senza aggiungere voci alla cronologia
            if (window.location.hash && history.replaceState) {
                history.replaceState(null, null, window.location.pathname + window.location.search);
            }
            
            tabsInitialized = true;
        }
        
        // Esegui l'inizializzazione immediatamente
        initTabs();
        
        // Fallback: esegui anche dopo un breve delay per assicurarsi che tutto sia caricato
        setTimeout(function() {
            // Verifica che i tab non attivi siano nascosti, altrimenti reinizializza
            const hiddenTabs = $('.fp-tab-content:not(.active)');
            if (hiddenTabs.length > 0 && hiddenTabs.filter(function() { return $(this).css('display') !== 'none'; }).length > 0) {
                tabsInitialized = false; // Reset flag se necessario reinizializzare
                initTabs();
            }
        }, 100);
        
        // Gestisci back/forward del browser (hashchange) — solo per link esterni con #tab-X
        $(window).on('hashchange', function() {
            var hash = window.location.hash.replace(/^#tab-?/, '');
            const validTabs = ['plugins', 'settings', 'backup', 'instructions'];
            if (hash === 'master') { hash = 'plugins'; }
            if (hash && validTabs.includes(hash)) {
                switchTab(hash);
            }
        });
        
        // ===== PLUGIN MANAGEMENT =====
        let pluginIndex = $('.fp-plugin-item').length;
        
        // Aggiungi nuovo plugin
        $('#fp-add-plugin').on('click', function(e) {
            e.preventDefault();
            
            const template = $('#fp-plugin-template').html();
            const newId = 'plugin_' + Date.now();
            const newPlugin = template
                .replace(/\{\{INDEX\}\}/g, pluginIndex)
                .replace(/\{\{ID\}\}/g, newId);
            
            $('#fp-plugins-list').append(newPlugin);
            pluginIndex++;
            
            // Scroll al nuovo plugin
            $('html, body').animate({
                scrollTop: $('.fp-plugin-item:last').offset().top - 100
            }, 500);
        });
        
        // Toggle dettagli plugin
        $(document).on('click', '.fp-toggle-plugin', function(e) {
            e.preventDefault();
            const target = $(this).data('target');
            const $target = $('#' + target);
            $target.toggleClass('active');
            
            if ($target.hasClass('active')) {
                $target.css('display', 'block');
            } else {
                $target.css('display', 'none');
            }
        });
        
        // Rimuovi plugin
        $(document).on('click', '.fp-remove-plugin', function(e) {
            e.preventDefault();
            var $item = $(this).closest('.fp-plugin-item');
            var name = $item.data('plugin-name') || 'questo plugin';
            if (!confirm('Rimuovere «' + name + '» dalla configurazione?')) {
                return;
            }
            $item.fadeOut(function() {
                $(this).remove();
            });
        });
        
        // Installa aggiornamento per plugin specifico
        $(document).on('click', '.fp-install-update', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const pluginId = $button.data('plugin-id');
            
            if (!confirm('Sei sicuro di voler installare l\'aggiornamento per questo plugin?')) {
                return;
            }
            
            const originalText = $button.html();
            
            $button.prop('disabled', true);
            $button.html('<span class="dashicons dashicons-update spin"></span> Installazione...');
            
            $.ajax({
                url: fpGitUpdater.ajax_url,
                type: 'POST',
                data: {
                    action: 'fp_git_updater_install_update',
                    plugin_id: pluginId,
                    nonce: fpGitUpdater.nonce
                },
                timeout: 120000, // 2 minuti
                success: function(response) {
                    if (response.success) {
                        let message = response.data.message || response.data || 'Aggiornamento completato!';
                        showNotice('success', message + ' La pagina si ricaricherà tra 3 secondi...');
                        setTimeout(function() {
                            location.reload();
                        }, 3000);
                    } else {
                        let errorMessage = response.data.message || response.data || 'Errore durante l\'aggiornamento.';
                        showNotice('error', errorMessage);
                        $button.prop('disabled', false);
                        $button.html(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    let message = 'Errore durante l\'aggiornamento.';
                    if (status === 'timeout') {
                        message = 'Timeout: l\'aggiornamento potrebbe essere ancora in corso. Controlla i log.';
                    } else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        message = xhr.responseJSON.data.message;
                    } else if (xhr.status === 400) {
                        message = 'Richiesta non valida. Prova a ricaricare la pagina.';
                    } else if (xhr.status === 403) {
                        message = 'Permessi insufficienti.';
                    } else if (xhr.status === 500) {
                        message = 'Errore del server. Controlla i log per maggiori dettagli.';
                    }
                    showNotice('error', message);
                    $button.prop('disabled', false);
                    $button.html(originalText);
                }
            });
        });
        
        // Aggiorna versione GitHub
        $(document).on('click', '.fp-refresh-github-version', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $button = $(this);
            const pluginId = $button.data('plugin-id');
            const $versionCode = $button.siblings('code');
            const $versionContainer = $button.closest('.fp-version-github, .fp-version-item');
            
            // Determina se siamo nella sezione plugin o self-update
            const $versionsCompact = $button.closest('.fp-plugin-versions-compact');
            const $versionBox = $button.closest('.fp-version-box');
            const isSelfUpdate = $versionBox.length > 0;
            
            const originalHtml = $button.html();
            const originalVersion = $versionCode.text();
            
            $button.prop('disabled', true);
            $button.addClass('spin');
            $button.html('<span class="dashicons dashicons-update"></span>');
            $versionCode.text('...');
            
            $.ajax({
                url: fpGitUpdater.ajax_url,
                type: 'POST',
                data: {
                    action: 'fp_git_updater_refresh_github_version',
                    plugin_id: pluginId,
                    nonce: fpGitUpdater.nonce
                },
                timeout: 30000, // 30 secondi
                success: function(response) {
                    if (response.success) {
                        const githubVersion = response.data.github_version || '—';
                        const currentVersion = response.data.current_version || '';
                        
                        // Aggiorna il codice versione GitHub
                        $versionCode.text(githubVersion);
                        
                        // Aggiorna lo stato delle versioni
                        const versionsDiffer = currentVersion && githubVersion && currentVersion !== githubVersion;
                        
                        if (versionsDiffer) {
                            $versionCode.removeClass('fp-version-same').addClass('fp-version-diff');
                        } else if (currentVersion === githubVersion) {
                            $versionCode.removeClass('fp-version-diff').addClass('fp-version-same');
                        } else {
                            $versionCode.removeClass('fp-version-diff fp-version-same');
                        }
                        
                        // Aggiorna lo stato nella sezione versioni (plugin item o self-update)
                        let $status;
                        if (isSelfUpdate) {
                            $status = $versionBox.find('.fp-version-status');
                        } else {
                            $status = $versionsCompact.find('.fp-version-status');
                        }
                        
                        if (versionsDiffer) {
                            if ($status.length === 0) {
                                $status = $('<span class="fp-version-status fp-version-status-update"><span class="dashicons dashicons-warning"></span></span>');
                                if (isSelfUpdate) {
                                    $versionBox.append($status);
                                } else {
                                    $versionsCompact.append($status);
                                }
                            }
                            $status.removeClass('fp-version-status-ok').addClass('fp-version-status-update');
                            if (isSelfUpdate) {
                                $status.html('<span class="dashicons dashicons-warning"></span> ' + 
                                    'Aggiornamento disponibile: ' + currentVersion + ' → ' + githubVersion);
                            } else {
                                $status.html('<span class="dashicons dashicons-update"></span> ' + 
                                    currentVersion + ' → <strong>' + githubVersion + '</strong>');
                            }
                        } else if (currentVersion === githubVersion) {
                            if ($status.length === 0) {
                                $status = $('<span class="fp-version-status fp-version-status-ok"><span class="dashicons dashicons-yes-alt"></span></span>');
                                if (isSelfUpdate) {
                                    $versionBox.append($status);
                                } else {
                                    $versionsCompact.append($status);
                                }
                            }
                            $status.removeClass('fp-version-status-update').addClass('fp-version-status-ok');
                            if (isSelfUpdate) {
                                $status.html('<span class="dashicons dashicons-yes-alt"></span> ' + 
                                    'Plugin aggiornato all\'ultima versione');
                            } else {
                                $status.html('<span class="dashicons dashicons-yes-alt"></span> Aggiornato');
                            }
                        } else {
                            $status.remove();
                        }
                        
                        // Aggiorna anche la versione installata se disponibile
                        if (currentVersion) {
                            if (isSelfUpdate) {
                                const $installedCode = $versionBox.find('.fp-version-item:first code');
                                if ($installedCode.length) {
                                    $installedCode.text(currentVersion);
                                }
                            } else {
                                const $installedCode = $versionsCompact.find('.fp-version-installed code');
                                if ($installedCode.length && (!$installedCode.text() || $installedCode.text() === '—')) {
                                    $installedCode.text(currentVersion);
                                }
                            }
                        }

                        // Aggiorna o crea la riga commit
                        const commitShort = response.data.commit_short || '';
                        const commitMsg   = response.data.commit_message || '';
                        const commitDate  = response.data.commit_date || '';
                        if (commitShort) {
                            const $container = isSelfUpdate ? $versionBox : $versionsCompact;
                            let $commitRow = $container.find('.fp-commit-info[data-plugin-id="' + pluginId + '"]');
                            if ($commitRow.length === 0) {
                                $commitRow = $('<span class="fp-version-item fp-commit-info" data-plugin-id="' + pluginId + '"></span>');
                                // Inserisci prima del badge di stato
                                const $statusEl = $container.find('.fp-version-status');
                                if ($statusEl.length) {
                                    $statusEl.before($commitRow);
                                } else {
                                    $container.append($commitRow);
                                }
                            }
                            let html = '<strong>Commit:</strong> <code class="fp-commit-sha">' + commitShort + '</code>';
                            if (commitMsg) {
                                html += ' <span class="fp-commit-message">' + $('<span>').text(commitMsg).html() + '</span>';
                            }
                            if (commitDate) {
                                html += ' <span class="fp-commit-date">' + $('<span>').text(commitDate).html() + '</span>';
                            }
                            $commitRow.html(html);
                        }

                        var msg = response.data.message || 'Versione GitHub aggiornata con successo';
                        showNotice(response.data.update_available ? 'error' : 'success', msg);
                        if (response.data.update_available) {
                            setTimeout(function() { location.reload(); }, 2000);
                        }
                    } else {
                        const errorMessage = response.data.message || 'Errore durante l\'aggiornamento della versione GitHub';
                        showNotice('error', errorMessage);
                        $versionCode.text(originalVersion);
                    }
                },
                error: function(xhr, status, error) {
                    let errorMessage = 'Errore durante l\'aggiornamento della versione GitHub.';
                    if (status === 'timeout') {
                        errorMessage = 'Timeout: la richiesta ha impiegato troppo tempo. Riprova.';
                    } else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMessage = xhr.responseJSON.data.message;
                    } else if (xhr.status === 404) {
                        errorMessage = 'Plugin non trovato. Ricarica la pagina.';
                    } else if (xhr.status === 403) {
                        errorMessage = 'Permessi insufficienti.';
                    } else if (xhr.status === 500) {
                        errorMessage = 'Errore del server. Verifica il repository e il branch.';
                    }
                    showNotice('error', errorMessage);
                    $versionCode.text(originalVersion);
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $button.removeClass('spin');
                    $button.html(originalHtml);
                }
            });
        });
        
        // Pulisci log
        $('#fp-clear-logs').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm('Sei sicuro di voler eliminare tutti i log?')) {
                return;
            }
            
            const $button = $(this);
            const originalText = $button.html();
            
            $button.prop('disabled', true);
            $button.html('<span class="dashicons dashicons-update spin"></span> Pulizia in corso...');
            
            $.ajax({
                url: fpGitUpdater.ajax_url,
                type: 'POST',
                data: {
                    action: 'fp_git_updater_clear_logs',
                    nonce: fpGitUpdater.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotice('success', response.data.message || response.data);
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        showNotice('error', response.data.message || response.data || 'Errore durante la pulizia dei log.');
                    }
                },
                error: function(xhr, status, error) {
                    let errorMessage = 'Errore durante la pulizia dei log.';
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMessage = xhr.responseJSON.data.message;
                    } else if (xhr.status === 400) {
                        errorMessage = 'Richiesta non valida. Prova a ricaricare la pagina.';
                    } else if (xhr.status === 403) {
                        errorMessage = 'Permessi insufficienti.';
                    }
                    showNotice('error', errorMessage);
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $button.html(originalText);
                }
            });
        });
        
        // Carica repository da GitHub
        $(document).on('click', '.fp-load-repos-btn', function(e) {
            e.preventDefault();
            
            const btn = $(this);
            const index = btn.data('index');
            const input = $('.fp-repo-input[data-index="' + index + '"]');
            
            // Disabilita pulsante e mostra loading
            btn.prop('disabled', true);
            const originalHtml = btn.html();
            btn.html('<span class="dashicons dashicons-update spin"></span> Caricamento...');
            
            $.ajax({
                url: fpGitUpdater.ajax_url,
                type: 'POST',
                data: {
                    action: 'fp_git_updater_load_github_repos',
                    nonce: fpGitUpdater.nonce
                },
                success: function(response) {
                    if (response && response.success && response.data) {
                        showRepoModal(response.data.repositories, input, response.data.username, response.data.from_cache);
                    } else {
                        const errorMsg = (response && response.data && response.data.message) ? response.data.message : 'Errore durante il caricamento dei repository';
                        showNotice('error', errorMsg);
                    }
                },
                error: function(xhr) {
                    let errorMsg = 'Errore di connessione';
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMsg = xhr.responseJSON.data.message;
                    }
                    showNotice('error', errorMsg);
                },
                complete: function() {
                    // Ripristina pulsante
                    btn.prop('disabled', false);
                    btn.html(originalHtml);
                }
            });
        });
        
        // Mostra modal con lista repository
        function showRepoModal(repositories, targetInput, username, fromCache) {
            // Rimuovi modal esistente
            $('#fp-repo-modal').remove();
            
            if (!repositories || repositories.length === 0) {
                showNotice('error', 'Nessun repository trovato per l\'username: ' + username);
                return;
            }
            
            // Crea modal
            let modalHtml = `
                <div id="fp-repo-modal" style="display: none;">
                    <div class="fp-repo-modal-backdrop"></div>
                    <div class="fp-repo-modal-content">
                        <div class="fp-repo-modal-header">
                            <h2>
                                <span class="dashicons dashicons-admin-site-alt3"></span>
                                Seleziona Repository da GitHub
                            </h2>
                            <button type="button" class="fp-repo-modal-close">
                                <span class="dashicons dashicons-no-alt"></span>
                            </button>
                        </div>
                        <div class="fp-repo-modal-body">
                            <p style="margin-bottom: 15px;">
                                <strong>Username:</strong> ${username} &nbsp; | &nbsp;
                                <strong>Totale:</strong> ${repositories.length} repository
                                ${fromCache ? ' <span style="color: #2271b1;">(da cache)</span>' : ''}
                            </p>
                            <input type="text" 
                                   id="fp-repo-search" 
                                   class="regular-text" 
                                   placeholder="Cerca repository..." 
                                   style="width: 100%; margin-bottom: 15px;">
                            <div class="fp-repo-list">
            `;
            
            repositories.forEach(function(repo) {
                const repoName = repo.name;
                const fullName = repo.full_name;
                const description = repo.description || '<em>Nessuna descrizione</em>';
                const isPrivate = repo.private;
                const branch = repo.default_branch || 'main';
                
                modalHtml += `
                    <div class="fp-repo-item" data-repo="${repoName}" data-full-name="${fullName}" data-branch="${branch}">
                        <div class="fp-repo-item-header">
                            <strong>${repoName}</strong>
                            ${isPrivate ? '<span class="fp-repo-badge fp-repo-private">Privato</span>' : ''}
                        </div>
                        <div class="fp-repo-item-description">${description}</div>
                        <div class="fp-repo-item-meta">
                            <small>Branch predefinito: <code>${branch}</code></small>
                        </div>
                    </div>
                `;
            });
            
            modalHtml += `
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
            $('#fp-repo-modal').fadeIn(200);
            
            // Focus su ricerca
            setTimeout(function() {
                $('#fp-repo-search').focus();
            }, 250);
            
            // Ricerca repository
            $('#fp-repo-search').on('input', function() {
                const searchTerm = $(this).val().toLowerCase();
                $('.fp-repo-item').each(function() {
                    const repoName = $(this).data('repo').toLowerCase();
                    const fullName = $(this).data('full-name').toLowerCase();
                    const description = $(this).find('.fp-repo-item-description').text().toLowerCase();
                    
                    if (repoName.includes(searchTerm) || fullName.includes(searchTerm) || description.includes(searchTerm)) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });
            
            // Seleziona repository
            $('.fp-repo-item').on('click', function() {
                const repoName = $(this).data('repo');
                const branch = $(this).data('branch');
                
                // Imposta il valore nel campo input
                targetInput.val(repoName).trigger('change');
                
                // Imposta anche il branch se c'è un campo branch per questo plugin
                const branchInput = targetInput.closest('.fp-plugin-details').find('input[name*="[branch]"]');
                if (branchInput.length && !branchInput.val()) {
                    branchInput.val(branch);
                }
                
                // Chiudi modal
                $('#fp-repo-modal').fadeOut(200, function() {
                    $(this).remove();
                });
                
                showNotice('success', 'Repository "' + repoName + '" selezionato!');
            });
            
            // Chiudi modal
            $('.fp-repo-modal-close, .fp-repo-modal-backdrop').on('click', function() {
                $('#fp-repo-modal').fadeOut(200, function() {
                    $(this).remove();
                });
            });
            
            // Chiudi con ESC
            $(document).on('keydown.repoModal', function(e) {
                if (e.key === 'Escape') {
                    $('#fp-repo-modal').fadeOut(200, function() {
                        $(this).remove();
                    });
                    $(document).off('keydown.repoModal');
                }
            });
        }
        
        // Funzione helper per mostrare notifiche (senza scroll per non disturbare)
        function showNotice(type, message) {
            const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            const $p = $('<p>').text(message);
            const notice = $('<div class="notice ' + noticeClass + ' is-dismissible"></div>').append($p);
            
            $('.fp-git-updater-wrap h1').after(notice);
            
            setTimeout(function() {
                notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }

        // ===== MODALITÀ MASTER: Copia URL con feedback =====
        $(document).on('click', '.fp-btn-copy, .fp-btn-copy-master', function() {
            var $btn = $(this);
            var targetId = $btn.data('copy-target');
            var $input = targetId ? $('#' + targetId) : $btn.closest('.fp-input-group').find('.fp-url-input, input[readonly]');
            if (!$input.length) return;
            var url = $input.val();
            if (typeof navigator.clipboard !== 'undefined' && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function() {
                    var $text = $btn.find('.fp-btn-copy-text');
                    if ($text.length) {
                        $text.text($btn.data('copied-text') || 'Copiato!');
                    }
                    $btn.addClass('fp-btn-copy--done');
                    setTimeout(function() {
                        if ($text.length) $text.text($btn.data('copy-label') || 'Copia');
                        $btn.removeClass('fp-btn-copy--done');
                    }, 2000);
                });
            } else {
                $input.select();
                document.execCommand('copy');
                $btn.addClass('fp-btn-copy--done');
                setTimeout(function() { $btn.removeClass('fp-btn-copy--done'); }, 1500);
            }
        });

        // ===== MODALITÀ MASTER: Mostra/Nascondi secret =====
        $(document).on('click', '.fp-btn-toggle-password', function() {
            var $btn = $(this);
            var $input = $btn.closest('.fp-input-group').find('input');
            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $btn.find('.dashicons').removeClass('dashicons-visibility').addClass('dashicons-hidden');
            } else {
                $input.attr('type', 'password');
                $btn.find('.dashicons').removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
        });

        // ===== MODALITÀ MASTER: Aggiorna badge stato al toggle =====
        $(document).on('change', '#fp_git_updater_master_mode', function() {
            var $status = $('.fp-master-status');
            if (!$status.length) return;
            var active = $(this).is(':checked');
            var text = active ? ($status.data('label-active') || 'Attiva') : ($status.data('label-inactive') || 'Non attiva');
            $status.removeClass('fp-master-status--active fp-master-status--inactive')
                .addClass(active ? 'fp-master-status--active' : 'fp-master-status--inactive')
                .text(text);
        });

        // ===== DISTRIBUZIONE MASTER: Tab Installa / Aggiorna =====
        $(document).on('click', '.fp-master-deploy-subtab', function() {
            var tab = $(this).data('subtab');
            $('.fp-master-deploy-subtab').removeClass('active').attr('aria-selected', 'false');
            $(this).addClass('active').attr('aria-selected', 'true');
            $('.fp-master-deploy-subcontent').removeClass('active');
            $('#fp-deploy-tab-' + tab).addClass('active');
        });

        $('#fp-master-load-github-repos').on('click', function() {
            var $btn = $(this);
            var $loading = $('#fp-github-repos-loading');
            var $list = $('#fp-github-repos-list');
            var $tbody = $('#fp-github-repos-tbody');
            var configuredRepos = (fpGitUpdater.configured_repos || []).map(function(r) { return (r || '').toLowerCase(); });
            var existingRepos = [];
            $tbody.find('tr[data-repo]').each(function() {
                existingRepos.push(($(this).data('repo') || '').toLowerCase());
            });
            $btn.prop('disabled', true);
            $loading.removeClass('fp-loading-text--hidden');
            $.post(fpGitUpdater.ajax_url, {
                action: 'fp_git_updater_load_github_repos',
                nonce: fpGitUpdater.nonce
            }).done(function(response) {
                if (response.success && response.data && response.data.repositories) {
                    var repos = response.data.repositories;
                    var clients = (fpGitUpdater.connected_clients || []);
                    var added = 0;
                    repos.forEach(function(repo) {
                        var fullName = (repo.full_name || '').trim();
                        if (!fullName) return;
                        var fullLower = fullName.toLowerCase();
                        if (configuredRepos.indexOf(fullLower) >= 0 || existingRepos.indexOf(fullLower) >= 0) return;
                        existingRepos.push(fullLower);
                        added++;
                        var branch = repo.default_branch || 'main';
                        var name = repo.name || fullName.split('/').pop();
                        var allId = 'fp-sel-all-' + (fullName || name).replace(/\W/g, '_');
                        var clientChecks = clients.length ? '<label class="fp-deploy-client-check fp-select-all"><input type="checkbox" id="' + allId + '"> <strong>Tutti</strong></label>' : '';
                        clientChecks += clients.map(function(c) {
                            return '<label class="fp-deploy-client-check"><input type="checkbox" class="fp-client-cb" data-all="' + allId + '" value="' + c.replace(/"/g, '&quot;') + '"> ' + c + '</label>';
                        }).join('');
                        var clientCell = clients.length ? clientChecks : '<em>Nessun cliente</em>';
                        $tbody.append('<tr data-repo="' + fullName.replace(/"/g, '&quot;') + '" data-branch="' + branch + '" data-name="' + name.replace(/"/g, '&quot;') + '"><td><strong>' + name + '</strong></td><td><code>' + fullName + '</code></td><td class="fp-deploy-clients-cell">' + clientCell + '</td><td><button type="button" class="button button-small fp-deploy-install-btn">' +
                            '<span class="dashicons dashicons-download"></span> Installa</button></td></tr>');
                    });
                    $list.removeClass('fp-github-repos-list--hidden');
                    if (added > 0) {
                        showNotice('success', added + ' repository aggiunti dalla lista GitHub.');
                    } else if (repos.length > 0) {
                        showNotice('success', 'Tutti i repository del profilo GitHub sono già nella lista.');
                    } else {
                        showNotice('success', 'Nessun repository trovato nel profilo GitHub.');
                    }
                } else {
                    showNotice('error', response.data && response.data.message ? response.data.message : 'Errore caricamento repository');
                }
            }).fail(function() {
                showNotice('error', 'Errore di connessione.');
            }).always(function() {
                $btn.prop('disabled', false);
                $loading.addClass('fp-loading-text--hidden');
            });
        });

        $(document).on('change', '.fp-deploy-clients-cell .fp-select-all input', function() {
            var $row = $(this).closest('tr');
            var checked = $(this).is(':checked');
            $row.find('.fp-client-cb').prop('checked', checked);
        });
        $(document).on('change', '.fp-deploy-clients-cell .fp-client-cb', function() {
            var $row = $(this).closest('tr');
            var allId = $row.find('.fp-client-cb').first().data('all');
            var total = $row.find('.fp-client-cb').length;
            var checked = $row.find('.fp-client-cb:checked').length;
            $('#' + allId).prop('checked', total > 0 && checked === total);
        });

        $(document).on('change', '.fp-plugin-deploy-inline .fp-select-all input', function() {
            var $block = $(this).closest('.fp-plugin-deploy-inline');
            var checked = $(this).is(':checked');
            $block.find('.fp-client-cb').prop('checked', checked);
        });
        $(document).on('change', '.fp-plugin-deploy-inline .fp-client-cb', function() {
            var $block = $(this).closest('.fp-plugin-deploy-inline');
            var allId = $block.find('.fp-client-cb').first().data('all');
            var total = $block.find('.fp-client-cb').length;
            var checked = $block.find('.fp-client-cb:checked').length;
            $('#' + allId).prop('checked', total > 0 && checked === total);
        });

        function doDeployInstall(repo, branch, name, clientIds, $btn) {
            if (clientIds.length === 0) {
                showNotice('error', 'Seleziona almeno un cliente.');
                return;
            }
            var originalHtml = $btn.html();
            $btn.prop('disabled', true).attr('aria-busy', 'true')
                .html('<span class="dashicons dashicons-update spin"></span> ' + ($btn.data('loading-text') || 'Installazione...'));
            $.post(fpGitUpdater.ajax_url, {
                action: 'fp_git_updater_deploy_install',
                nonce: fpGitUpdater.nonce,
                github_repo: repo,
                branch: branch,
                name: name,
                client_ids_json: JSON.stringify(clientIds)
            }).done(function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    showNotice('error', response.data && response.data.message ? response.data.message : 'Errore');
                    $btn.prop('disabled', false).attr('aria-busy', 'false').html(originalHtml);
                }
            }).fail(function() {
                showNotice('error', 'Errore di connessione.');
                $btn.prop('disabled', false).attr('aria-busy', 'false').html(originalHtml);
            });
        }

        $(document).on('click', '.fp-deploy-install-inline', function() {
            var $block = $(this).closest('.fp-plugin-deploy-inline');
            var repo = $block.data('repo');
            var branch = $block.data('branch');
            var name = $block.data('name');
            var clientIds = [];
            $block.find('.fp-client-cb:checked').each(function() {
                var v = $(this).val();
                if (v) clientIds.push(v);
            });
            doDeployInstall(repo, branch, name, clientIds, $(this));
        });

        // Sincronizza versioni sui clienti selezionati
        $(document).on('click', '.fp-sync-versions-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $btn = $(this);
            var $block = $btn.closest('.fp-plugin-deploy-inline');
            var pluginSlug = $btn.data('plugin-slug') || '';
            var githubVersion = $btn.data('github-version') || '';

            // Raccoglie i client selezionati (checkbox spuntate)
            var clientIds = [];
            $block.find('.fp-client-cb:checked').each(function() {
                var v = $(this).val();
                if (v) clientIds.push(v);
            });

            if (clientIds.length === 0) {
                showNotice('error', 'Seleziona almeno un sito con le checkbox prima di sincronizzare.');
                return;
            }

            var origHtml = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Sincronizzazione...');

            var done = 0;
            var errors = 0;
            var errorMsgs = [];
            var total = clientIds.length;

            clientIds.forEach(function(clientId) {
                $.post(fpGitUpdater.ajax_url, {
                    action: 'fp_git_updater_sync_client_version',
                    nonce: fpGitUpdater.nonce,
                    client_id: clientId,
                    plugin_slug: pluginSlug
                }).done(function(response) {
                    if (response.success) {
                        var ver = response.data.plugin_version || '';
                        var allPlugins = response.data.all_plugins || {};

                        // Fallback: cerca lo slug con varie strategie di matching
                        if (!ver && pluginSlug) {
                            var slugLower = pluginSlug.toLowerCase();
                            var slugNoHyphens = slugLower.replace(/-/g, '');

                            // Alias noti per plugin con nomi cartella variabili
                            var knownAliases = {
                                'fp-remote-bridge': [
                                    'fp-remote-bridge',
                                    'fp-remote-bridge-update',
                                    'fp-remote-bridge-main',
                                    'fp-remotebridge',
                                    'fpremotebridge',
                                    'remote-bridge',
                                    'fp-bridge'
                                ]
                            };

                            // Controlla alias noti prima
                            if (knownAliases[slugLower]) {
                                $.each(knownAliases[slugLower], function(i, alias) {
                                    if (allPlugins[alias] !== undefined) {
                                        ver = allPlugins[alias];
                                        return false;
                                    }
                                });
                            }

                            // Fallback generico: match senza trattini, poi parziale
                            if (!ver) {
                                var bestMatch = '';
                                var bestVer = '';
                                $.each(allPlugins, function(k, v) {
                                    var kLower = k.toLowerCase();
                                    var kNoHyphens = kLower.replace(/-/g, '');
                                    if (kNoHyphens === slugNoHyphens) {
                                        ver = v;
                                        return false;
                                    }
                                    if (kLower.indexOf(slugLower) !== -1 || slugLower.indexOf(kLower) !== -1) {
                                        if (kLower.length > bestMatch.length) {
                                            bestMatch = kLower;
                                            bestVer = v;
                                        }
                                    }
                                });
                                if (!ver && bestVer) {
                                    ver = bestVer;
                                }
                            }
                        }

                        var $badge = $block.find('.fp-deploy-client-ver[data-client-id="' + clientId + '"]');
                        if ($badge.length) {
                            if (ver) {
                                var verClass = 'fp-deploy-client-ver';
                                verClass += (githubVersion && ver !== githubVersion)
                                    ? ' fp-deploy-client-ver--old'
                                    : ' fp-deploy-client-ver--ok';
                                $badge.attr('class', verClass).attr('data-client-id', clientId).text('v' + ver);
                            } else {
                                // Plugin non installato su questo sito
                                $badge.attr('class', 'fp-deploy-client-ver fp-deploy-client-ver--unknown').attr('title', 'Plugin non trovato su questo sito').text('n/a');
                            }
                        }
                    } else {
                        errors++;
                        var errMsg = (response.data && response.data.message) ? response.data.message : 'Errore';
                        errorMsgs.push(clientId + ': ' + errMsg);
                    }
                }).fail(function(xhr) {
                    errors++;
                    errorMsgs.push(clientId + ': connessione fallita (HTTP ' + xhr.status + ')');
                }).always(function() {
                    done++;
                    if (done >= total) {
                        $btn.prop('disabled', false).html(origHtml);
                        if (errors === 0) {
                            showNotice('success', total + ' ' + (total === 1 ? 'sito sincronizzato.' : 'siti sincronizzati.'));
                        } else if (errors < total) {
                            showNotice('error', (total - errors) + '/' + total + ' siti OK. Errori: ' + errorMsgs.join(' | '));
                        } else {
                            showNotice('error', 'Sincronizzazione fallita. ' + errorMsgs.join(' | '));
                        }
                    }
                });
            });
        });

        $(document).on('click', '.fp-deploy-install-btn', function() {
            var $row = $(this).closest('tr');
            var repo = $row.data('repo');
            var branch = $row.data('branch');
            var name = $row.data('name');
            var clientIds = [];
            $row.find('.fp-deploy-client-check input:checked').each(function() {
                var v = $(this).val();
                if (v) clientIds.push(v);
            });
            doDeployInstall(repo, branch, name, clientIds, $(this));
        });

        $(document).on('click', '.fp-deploy-update-btn', function() {
            var $btn = $(this);
            if ($btn.prop('disabled')) return;
            var pluginId = $btn.data('plugin-id');
            var $row = $btn.closest('tr');
            var pluginSlug = $row.data('plugin-slug') || pluginId;
            $btn.prop('disabled', true);
            $.post(fpGitUpdater.ajax_url, {
                action: 'fp_git_updater_deploy_update',
                nonce: fpGitUpdater.nonce,
                plugin_id: pluginId,
                plugin_slug: pluginSlug
            }).done(function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    showNotice('error', response.data && response.data.message ? response.data.message : 'Errore');
                    $btn.prop('disabled', false);
                }
            }).fail(function() {
                showNotice('error', 'Errore di connessione.');
                $btn.prop('disabled', false);
            });
        });

        $(document).on('click', '#fp-refresh-clients-btn', function() {
            var $btn = $(this);
            var $content = $('#fp-master-clients-content');
            var $badge = $('.fp-master-clients-badge');
            var origHtml = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + ($btn.data('loading') || 'Aggiornamento...'));
            $.post(fpGitUpdater.ajax_url, {
                action: 'fp_git_updater_refresh_clients',
                nonce: fpGitUpdater.nonce
            }).done(function(response) {
                if (response.success && response.data && response.data.html !== undefined) {
                    $content.html(response.data.html);
                    if (response.data.count > 0) {
                        if ($badge.length) {
                            $badge.text(response.data.count);
                        } else {
                            $('.fp-master-clients-title').append('<span class="fp-master-clients-badge">' + response.data.count + '</span>');
                        }
                    } else {
                        $badge.remove();
                    }
                    showNotice('success', response.data.count > 0 ? response.data.count + ' clienti collegati.' : 'Nessun cliente collegato.');
                } else {
                    showNotice('error', 'Errore durante l\'aggiornamento.');
                }
            }).fail(function() {
                showNotice('error', 'Errore di connessione.');
            }).always(function() {
                $btn.prop('disabled', false).html(origHtml);
            });
        });
        
        // Aggiorna versioni plugin da un cliente specifico
        $(document).on('click', '.fp-refresh-client-versions-btn', function() {
            var $btn = $(this);
            var clientId = $btn.data('client-id');
            if (!clientId) return;
            var origHtml = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span>');
            $.post(fpGitUpdater.ajax_url, {
                action: 'fp_git_updater_refresh_client_versions',
                nonce: fpGitUpdater.nonce,
                client_id: clientId
            }).done(function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    var plugins = response.data.plugins || {};
                    var slugs = Object.keys(plugins);
                    var str = slugs.length ? slugs.slice(0, 8).join(', ') + (slugs.length > 8 ? ' +' + (slugs.length - 8) + '…' : '') : '—';
                    var $row = $btn.closest('tr');
                    $row.find('.fp-client-plugins-list').text(str);
                    var $counter = $row.find('.fp-client-plugins-list').next('small');
                    if ($counter.length) {
                        $counter.text('(' + slugs.length + ')');
                    } else if (slugs.length > 0) {
                        $row.find('.fp-client-plugins-list').after('<small style="color:var(--fp-text-muted);">(' + slugs.length + ')</small>');
                    }
                    renderVersionsCell($row.find('.fp-client-versions-cell'), plugins);
                } else {
                    showNotice('error', response.data && response.data.message ? response.data.message : 'Errore durante l\'aggiornamento.');
                }
            }).fail(function() {
                showNotice('error', 'Errore di connessione.');
            }).always(function() {
                $btn.prop('disabled', false).html(origHtml);
            });
        });

        // ===== VERSIONI IN TEMPO REALE: aggiorna tutti i clienti uno per uno =====

        function renderVersionsCell($cell, plugins) {
            if (!plugins || Object.keys(plugins).length === 0) {
                $cell.html('<span class="fp-versions-placeholder">—</span>');
                return;
            }
            var slugs = Object.keys(plugins);
            var html = '<div class="fp-client-plugins-with-versions">';
            var shown = slugs.slice(0, 8);
            shown.forEach(function(slug) {
                html += '<span class="fp-client-plugin-entry">'
                    + '<span class="fp-client-plugin-slug">' + $('<span>').text(slug).html() + '</span>'
                    + ' <span class="fp-deploy-client-ver fp-deploy-client-ver--ok">v' + $('<span>').text(plugins[slug]).html() + '</span>'
                    + '</span>';
            });
            if (slugs.length > 8) {
                html += '<span class="fp-version-more">+' + (slugs.length - 8) + ' altri</span>';
            }
            html += '</div>';
            $cell.html(html);
        }

        $(document).on('click', '#fp-refresh-all-versions-btn', function() {
            var $btn = $(this);
            var origHtml = $btn.html();

            // Raccoglie tutti i client_id dalla tabella
            var clientIds = [];
            $('.fp-master-clients-table tbody tr[id^="fp-client-row-"]').each(function() {
                var cid = $(this).find('.fp-client-versions-cell').data('client-id');
                if (cid) clientIds.push(cid);
            });

            if (clientIds.length === 0) {
                showNotice('error', 'Nessun cliente trovato nella tabella.');
                return;
            }

            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Interrogazione in corso...');

            // Mostra stato "caricamento" su tutte le celle versioni
            clientIds.forEach(function(cid) {
                var $cell = $('.fp-client-versions-cell[data-client-id="' + CSS.escape(cid) + '"]');
                $cell.html('<span class="fp-versions-loading"><span class="dashicons dashicons-update spin"></span></span>');
            });

            var total = clientIds.length;
            var done = 0;
            var errors = 0;

            function onClientDone() {
                done++;
                $btn.html('<span class="dashicons dashicons-update spin"></span> ' + done + '/' + total + '...');
                if (done >= total) {
                    $btn.prop('disabled', false).html(origHtml);
                    var msg = errors > 0
                        ? (total - errors) + ' su ' + total + ' clienti aggiornati. ' + errors + ' errori.'
                        : 'Versioni aggiornate per tutti i ' + total + ' clienti.';
                    showNotice(errors > 0 ? 'error' : 'success', msg);
                }
            }

            // Interroga i clienti con concorrenza massima di 3
            var nextIndex = 0;

            function processNext() {
                if (nextIndex >= clientIds.length) return;
                var index = nextIndex++;
                var cid = clientIds[index];
                var $cell = $('.fp-client-versions-cell[data-client-id="' + CSS.escape(cid) + '"]');
                var $row = $cell.closest('tr');

                $.post(fpGitUpdater.ajax_url, {
                    action: 'fp_git_updater_refresh_single_client_versions',
                    nonce: fpGitUpdater.nonce,
                    client_id: cid
                }).done(function(response) {
                    if (response.success) {
                        var plugins = response.data.plugins || {};
                        var slugs = Object.keys(plugins);
                        renderVersionsCell($cell, plugins);
                        var str = slugs.length ? slugs.slice(0, 8).join(', ') + (slugs.length > 8 ? ' +' + (slugs.length - 8) + '…' : '') : '—';
                        $row.find('.fp-client-plugins-list').text(str);
                        var $counter = $row.find('.fp-client-plugins-list').next('small');
                        if ($counter.length) { $counter.text('(' + slugs.length + ')'); }
                        $cell.addClass('fp-versions-cell--ok');
                    } else {
                        errors++;
                        var errMsg = response.data && response.data.message ? response.data.message : 'Errore';
                        $cell.html('<span class="fp-versions-error" title="' + $('<span>').text(errMsg).html() + '"><span class="dashicons dashicons-warning"></span> Errore</span>');
                    }
                }).fail(function() {
                    errors++;
                    $cell.html('<span class="fp-versions-error"><span class="dashicons dashicons-warning"></span> Connessione fallita</span>');
                }).always(function() {
                    onClientDone();
                    processNext();
                });
            }

            // Avvia con max 3 richieste parallele
            var concurrency = Math.min(3, clientIds.length);
            for (var i = 0; i < concurrency; i++) {
                processNext();
            }
        });

        // Modifica cliente
        $(document).on('click', '.fp-edit-client-btn', function() {
            var $btn = $(this);
            var clientId  = $btn.data('client-id');
            var clientUrl = $btn.data('client-url') || '';

            // Rimuovi modal precedente
            $('#fp-edit-client-modal').remove();

            var html = '<div id="fp-edit-client-modal">'
                + '<div class="fp-modal-backdrop"></div>'
                + '<div class="fp-modal-box">'
                + '<div class="fp-modal-header">'
                + '<h3><span class="dashicons dashicons-edit"></span> Modifica cliente</h3>'
                + '<button type="button" class="fp-modal-close"><span class="dashicons dashicons-no-alt"></span></button>'
                + '</div>'
                + '<div class="fp-modal-body">'
                + '<table class="form-table" style="margin:0;">'
                + '<tr><th style="width:120px;padding:8px 0;"><label>Client ID</label></th>'
                + '<td style="padding:8px 0;"><input type="text" id="fp-edit-client-id" class="regular-text" value="' + $('<span>').text(clientId).html() + '"></td></tr>'
                + '<tr><th style="padding:8px 0;"><label>URL sito</label></th>'
                + '<td style="padding:8px 0;"><input type="url" id="fp-edit-client-url" class="regular-text" placeholder="https://esempio.com" value="' + $('<span>').text(clientUrl).html() + '"></td></tr>'
                + '</table>'
                + '<p style="margin:12px 0 0;font-size:12px;color:#646970;">Modifica il Client ID solo se il sito ha cambiato dominio. L\'URL serve per la sincronizzazione versioni.</p>'
                + '</div>'
                + '<div class="fp-modal-footer">'
                + '<button type="button" class="button fp-modal-close">Annulla</button>'
                + '<button type="button" class="button button-primary" id="fp-edit-client-save" data-old-id="' + $('<span>').text(clientId).html() + '">Salva</button>'
                + '</div>'
                + '</div>'
                + '</div>';

            $('body').append(html);
            $('#fp-edit-client-modal').fadeIn(150);
            $('#fp-edit-client-id').focus();
        });

        // Chiudi modal modifica
        $(document).on('click', '.fp-modal-close, .fp-modal-backdrop', function() {
            $('#fp-edit-client-modal').fadeOut(150, function() { $(this).remove(); });
        });
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $('#fp-edit-client-modal').fadeOut(150, function() { $(this).remove(); });
            }
        });

        // Salva modifiche cliente
        $(document).on('click', '#fp-edit-client-save', function() {
            var $btn = $(this);
            var oldId  = $btn.data('old-id');
            var newId  = $('#fp-edit-client-id').val().trim();
            var newUrl = $('#fp-edit-client-url').val().trim();

            if (!newId) {
                alert('Il Client ID non può essere vuoto.');
                return;
            }

            $btn.prop('disabled', true).text('Salvataggio...');

            $.post(fpGitUpdater.ajax_url, {
                action: 'fp_git_updater_edit_client',
                nonce: fpGitUpdater.nonce,
                client_id: oldId,
                new_client_id: newId,
                new_url: newUrl
            }).done(function(response) {
                if (response.success) {
                    $('#fp-edit-client-modal').fadeOut(150, function() { $(this).remove(); });
                    showNotice('success', response.data.message);
                    // Aggiorna la riga nella tabella
                    var $row = $('#fp-client-row-' + oldId.replace(/[^a-zA-Z0-9_-]/g, '-'));
                    if (!$row.length) { $row = $btn.closest('tr'); }
                    if (newId !== oldId) {
                        // Se il client_id è cambiato, ricarica la tabella
                        setTimeout(function() { location.reload(); }, 800);
                    } else {
                        // Aggiorna solo il pulsante con il nuovo URL
                        $row.find('.fp-edit-client-btn').data('client-url', newUrl);
                    }
                } else {
                    showNotice('error', response.data && response.data.message ? response.data.message : 'Errore.');
                    $btn.prop('disabled', false).text('Salva');
                }
            }).fail(function() {
                showNotice('error', 'Errore di connessione.');
                $btn.prop('disabled', false).text('Salva');
            });
        });

        // Rimuovi cliente
        $(document).on('click', '.fp-remove-client-btn', function() {
            var $btn = $(this);
            var clientId = $btn.data('client-id');
            if (!clientId) return;
            if (!confirm('Rimuovere il cliente "' + clientId + '" dalla lista?')) return;
            $btn.prop('disabled', true);
            $.post(fpGitUpdater.ajax_url, {
                action: 'fp_git_updater_remove_client',
                nonce: fpGitUpdater.nonce,
                client_id: clientId
            }).done(function(response) {
                if (response.success) {
                    var $row = $('#fp-client-row-' + clientId.replace(/[^a-zA-Z0-9_-]/g, '-'));
                    if (!$row.length) { $row = $btn.closest('tr'); }
                    $row.fadeOut(300, function() {
                        $row.remove();
                        var $badge = $('.fp-master-clients-badge');
                        var count = parseInt($badge.text(), 10) - 1;
                        if (count > 0) { $badge.text(count); } else { $badge.remove(); }
                    });
                    showNotice('success', response.data.message || 'Cliente rimosso.');
                } else {
                    showNotice('error', response.data && response.data.message ? response.data.message : 'Errore.');
                    $btn.prop('disabled', false);
                }
            }).fail(function() {
                showNotice('error', 'Errore di connessione.');
                $btn.prop('disabled', false);
            });
        });

        // Animazioni disabilitate - nessuna animazione spin
    });
    
    // Gestione statistiche backup
    function loadBackupStats() {
        const statsContainer = jQuery('#fp-backup-stats');
        if (!statsContainer.length) {
            return;
        }
        
        statsContainer.html('<p><strong>Caricamento...</strong></p>');
        
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'fp_git_updater_get_backup_stats',
                nonce: fpGitUpdater.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    const stats = response.data;
                    let html = '<table style="width: 100%;">';
                    html += '<tr><td><strong>Backup Totali:</strong></td><td>' + (stats.total_backups ?? 0) + '</td></tr>';
                    html += '<tr><td><strong>Spazio Totale:</strong></td><td>' + (stats.total_size_formatted ?? '0 B') + '</td></tr>';
                    html += '<tr><td><strong>Spazio Disponibile:</strong></td><td>' + (stats.available_space_formatted ?? 'N/A') + '</td></tr>';
                    if (stats.oldest_backup) {
                        html += '<tr><td><strong>Backup più Vecchio:</strong></td><td>' + stats.oldest_backup + '</td></tr>';
                    }
                    if (stats.newest_backup) {
                        html += '<tr><td><strong>Backup più Recente:</strong></td><td>' + stats.newest_backup + '</td></tr>';
                    }
                    html += '</table>';
                    
                    if ((stats.total_backups ?? 0) > 0) {
                        html += '<p style="margin-top: 10px; color: #d63638;"><strong>⚠ Attenzione:</strong> ' + (stats.total_backups ?? 0) + ' backup occupano ' + (stats.total_size_formatted ?? '0 B') + ' di spazio.</p>';
                    } else {
                        html += '<p style="margin-top: 10px; color: #00a32a;"><strong>✓ Nessun backup presente.</strong></p>';
                    }
                    
                    statsContainer.html(html);
                } else {
                    statsContainer.html('<p style="color: #d63638;">Errore nel caricamento delle statistiche.</p>');
                }
            },
            error: function() {
                statsContainer.html('<p style="color: #d63638;">Errore nel caricamento delle statistiche.</p>');
            }
        });
    }
    
    // Carica statistiche al caricamento della pagina
    jQuery(document).ready(function() {
        if (jQuery('#fp-backup-stats').length) {
            loadBackupStats();
        }
    });
    
    // Pulsante aggiorna statistiche
    jQuery(document).on('click', '#fp-refresh-backup-stats', function(e) {
        e.preventDefault();
        loadBackupStats();
    });
    
    // Pulsante pulizia backup
    jQuery(document).on('click', '#fp-cleanup-backups-now', function(e) {
        e.preventDefault();
        
        if (!confirm('Sei sicuro di voler eliminare i backup vecchi? Questa azione non può essere annullata.')) {
            return;
        }
        
        const button = jQuery(this);
        const originalText = button.html();
        button.prop('disabled', true).html('<span class="dashicons dashicons-update" style="animation: dashicons-spin 1s linear infinite;"></span> Pulizia in corso...');
        
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'fp_git_updater_cleanup_backups',
                nonce: fpGitUpdater.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    loadBackupStats();
                } else {
                    alert('Errore: ' + (response.data && response.data.message ? response.data.message : 'Errore sconosciuto'));
                }
                button.prop('disabled', false).html(originalText);
            },
            error: function() {
                alert('Errore durante la pulizia dei backup.');
                button.prop('disabled', false).html(originalText);
            }
        });
    });
    
})(jQuery);
