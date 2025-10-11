/**
 * FP Git Updater - Admin JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
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
        
        // Animazione spin per i dashicons
        const style = document.createElement('style');
        style.innerHTML = `
            .dashicons.spin {
                animation: dashicons-spin 1s linear infinite;
            }
            @keyframes dashicons-spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    });
    
})(jQuery);
