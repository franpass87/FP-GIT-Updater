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
        
        // Test connessione GitHub
        $('#fp-test-connection').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const originalText = $button.html();
            
            $button.prop('disabled', true);
            $button.html('<span class="dashicons dashicons-update spin"></span> Test in corso...');
            
            $.ajax({
                url: fpGitUpdater.ajax_url,
                type: 'POST',
                data: {
                    action: 'fp_git_updater_test_connection',
                    nonce: fpGitUpdater.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotice('success', response.data);
                    } else {
                        showNotice('error', response.data);
                    }
                },
                error: function() {
                    showNotice('error', 'Errore durante il test di connessione.');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $button.html(originalText);
                }
            });
        });
        
        // Aggiornamento manuale
        $('#fp-manual-update').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm('Sei sicuro di voler aggiornare il plugin adesso?')) {
                return;
            }
            
            const $button = $(this);
            const originalText = $button.html();
            
            $button.prop('disabled', true);
            $button.html('<span class="dashicons dashicons-update spin"></span> Aggiornamento in corso...');
            
            $.ajax({
                url: fpGitUpdater.ajax_url,
                type: 'POST',
                data: {
                    action: 'fp_git_updater_manual_update',
                    nonce: fpGitUpdater.nonce
                },
                timeout: 120000, // 2 minuti
                success: function(response) {
                    if (response.success) {
                        showNotice('success', response.data + ' La pagina si ricaricherà tra 3 secondi...');
                        setTimeout(function() {
                            location.reload();
                        }, 3000);
                    } else {
                        showNotice('error', response.data);
                        $button.prop('disabled', false);
                        $button.html(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    let message = 'Errore durante l\'aggiornamento.';
                    if (status === 'timeout') {
                        message = 'Timeout: l\'aggiornamento potrebbe essere ancora in corso. Controlla i log.';
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
                        showNotice('success', response.data);
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        showNotice('error', response.data);
                    }
                },
                error: function() {
                    showNotice('error', 'Errore durante la pulizia dei log.');
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
