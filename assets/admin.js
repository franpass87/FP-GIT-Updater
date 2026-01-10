/**
 * FP Git Updater - Admin JavaScript
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
                // Attiva il tab item nella navigation
                $targetItem.addClass('active');
                
                // Mostra SOLO il tab selezionato usando sia CSS che JavaScript
                $targetContent.addClass('active');
                $targetContent.css('display', 'block');
                
                // Aggiorna URL hash senza ricaricare
                if (history.pushState) {
                    history.pushState(null, null, '#' + tabName);
                }
                
                // Scroll smooth al top della navigation
                $('html, body').animate({
                    scrollTop: $('.fp-tab-nav').offset().top - 100
                }, 300);
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
        // Funzione helper per inizializzare i tab
        function initTabs() {
            // Nascondi TUTTI i tab usando JavaScript (forza display: none)
            $('.fp-tab-content').each(function() {
                $(this).css('display', 'none');
            });
            
            // Rimuovi active da tutti i tab
            $('.fp-tab-item').removeClass('active');
            $('.fp-tab-content').removeClass('active');
            
            const hash = window.location.hash.replace('#', '');
            const validTabs = ['plugins', 'settings', 'backup', 'instructions'];
            
            if (hash && validTabs.includes(hash)) {
                // Se c'è un hash valido nell'URL, carica quel tab
                switchTab(hash);
            } else {
                // Default: mostra SOLO il tab "plugins"
                $('.fp-tab-link[data-tab="plugins"]').closest('.fp-tab-item').addClass('active');
                $('#fp-tab-plugins').addClass('active').css('display', 'block');
                
                // Aggiorna URL con hash plugins se non c'è hash
                if (history.pushState) {
                    history.pushState(null, null, '#plugins');
                }
            }
        }
        
        // Esegui l'inizializzazione immediatamente
        initTabs();
        
        // Fallback: esegui anche dopo un breve delay per assicurarsi che tutto sia caricato
        setTimeout(function() {
            // Verifica che i tab non attivi siano nascosti, altrimenti reinizializza
            const hiddenTabs = $('.fp-tab-content:not(.active)');
            if (hiddenTabs.length > 0 && hiddenTabs.filter(function() { return $(this).css('display') !== 'none'; }).length > 0) {
                initTabs();
            }
        }, 100);
        
        // Gestisci back/forward del browser (hashchange)
        $(window).on('hashchange', function() {
            const hash = window.location.hash.replace('#', '');
            const validTabs = ['plugins', 'settings', 'backup', 'instructions'];
            if (hash && validTabs.includes(hash)) {
                switchTab(hash);
            } else {
                switchTab('plugins');
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
            $('#' + target).slideToggle();
        });
        
        // Rimuovi plugin
        $(document).on('click', '.fp-remove-plugin', function(e) {
            e.preventDefault();
            
            if (!confirm('Sei sicuro di voler rimuovere questo plugin?')) {
                return;
            }
            
            $(this).closest('.fp-plugin-item').fadeOut(function() {
                $(this).remove();
            });
        });
        
        // Controlla aggiornamenti per plugin specifico
        $(document).on('click', '.fp-check-updates', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const pluginId = $button.data('plugin-id');
            const originalText = $button.html();
            
            $button.prop('disabled', true);
            $button.html('<span class="dashicons dashicons-update spin"></span> Controllo...');
            
            $.ajax({
                url: fpGitUpdater.ajax_url,
                type: 'POST',
                data: {
                    action: 'fp_git_updater_check_updates',
                    plugin_id: pluginId,
                    nonce: fpGitUpdater.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotice('success', response.data.message || response.data);
                    } else {
                        showNotice('error', response.data.message || response.data || 'Errore durante il controllo aggiornamenti.');
                    }
                },
                error: function(xhr, status, error) {
                    let errorMessage = 'Errore durante il controllo aggiornamenti.';
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
        
        // Funzione helper per mostrare notifiche
        function showNotice(type, message) {
            const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            const notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.fp-git-updater-wrap h1').after(notice);
            
            // Auto-dismiss dopo 5 secondi
            setTimeout(function() {
                notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Scroll to top
            $('html, body').animate({
                scrollTop: $('.fp-git-updater-wrap').offset().top - 50
            }, 500);
        }
        
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
                    html += '<tr><td><strong>Backup Totali:</strong></td><td>' + stats.total_backups + '</td></tr>';
                    html += '<tr><td><strong>Spazio Totale:</strong></td><td>' + stats.total_size_formatted + '</td></tr>';
                    html += '<tr><td><strong>Spazio Disponibile:</strong></td><td>' + stats.available_space_formatted + '</td></tr>';
                    if (stats.oldest_backup) {
                        html += '<tr><td><strong>Backup più Vecchio:</strong></td><td>' + stats.oldest_backup + '</td></tr>';
                    }
                    if (stats.newest_backup) {
                        html += '<tr><td><strong>Backup più Recente:</strong></td><td>' + stats.newest_backup + '</td></tr>';
                    }
                    html += '</table>';
                    
                    if (stats.total_backups > 0) {
                        html += '<p style="margin-top: 10px; color: #d63638;"><strong>⚠ Attenzione:</strong> ' + stats.total_backups + ' backup occupano ' + stats.total_size_formatted + ' di spazio.</p>';
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
                nonce: fpGitUpdaterAdmin.nonce
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
