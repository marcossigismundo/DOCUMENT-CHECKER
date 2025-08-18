/**
 * Tainacan Document Checker Admin JavaScript (Completo)
 *
 * @package TainacanDocumentChecker
 * @since 1.0.0
 */

(function($) {
    'use strict';

    const TCD = {
        init: function() {
            // Verificar conexão AJAX primeiro
            if (!this.checkAjaxConnection()) {
                return;
            }
            
            this.bindEvents();
            this.initDocumentManager();
            
            // Testar conexão AJAX em modo debug
            if (this.isDebugMode()) {
                this.testAjaxConnection();
            }
        },

        checkAjaxConnection: function() {
            // Verificar se tcd_ajax está definido
            if (typeof tcd_ajax === 'undefined') {
                console.error('TCD: tcd_ajax object not found');
                this.showError('AJAX configuration not loaded properly. Please refresh the page.');
                return false;
            }
            
            // Verificar se a URL do AJAX está acessível
            if (!tcd_ajax.ajax_url) {
                console.error('TCD: AJAX URL not configured');
                this.showError('AJAX URL not configured properly.');
                return false;
            }
            
            // Verificar se o nonce está presente
            if (!tcd_ajax.nonce) {
                console.error('TCD: Security nonce missing');
                this.showError('Security configuration missing.');
                return false;
            }
            
            // Log para debug
            console.log('TCD: AJAX URL:', tcd_ajax.ajax_url);
            console.log('TCD: Nonce:', tcd_ajax.nonce ? 'Present' : 'Missing');
            console.log('TCD: Strings loaded:', typeof tcd_ajax.strings !== 'undefined');
            
            return true;
        },

        isDebugMode: function() {
            return typeof tcd_ajax.debug !== 'undefined' && tcd_ajax.debug === true;
        },

        testAjaxConnection: function() {
            console.log('TCD: Testing AJAX connection...');
            
            $.ajax({
                url: tcd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tcd_test_connection',
                    nonce: tcd_ajax.nonce
                },
                timeout: 10000, // 10 seconds timeout
                success: (response) => {
                    console.log('TCD: AJAX connection test successful', response);
                },
                error: (xhr, status, error) => {
                    console.error('TCD: AJAX connection test failed');
                    console.error('TCD: Status:', status);
                    console.error('TCD: Error:', error);
                    console.error('TCD: Response:', xhr.responseText);
                    console.error('TCD: Status Code:', xhr.status);
                    
                    if (xhr.status === 404) {
                        this.showError('AJAX endpoint not found (404). Please check WordPress configuration.');
                    } else if (xhr.status === 503) {
                        this.showError('Server unavailable (503). Please try again later.');
                    } else if (xhr.status === 0) {
                        this.showError('Network error. Please check your internet connection.');
                    } else {
                        this.showError('AJAX connection failed. Please check browser console for details.');
                    }
                }
            });
        },

        bindEvents: function() {
            // Single check form
            $('#tcd-single-check-form').on('submit', this.handleSingleCheck.bind(this));
            
            // Batch check form
            $('#tcd-batch-check-form').on('submit', this.handleBatchCheck.bind(this));
            
            // Clear cache button
            $('#tcd-clear-cache-btn').on('click', this.handleClearCache.bind(this));
            
            // Clear cache before batch check
            $('#tcd-clear-cache-before-batch').on('change', function() {
                if (typeof Storage !== 'undefined') {
                    localStorage.setItem('tcd_clear_cache_before_batch', $(this).is(':checked'));
                }
            });
            
            // Tab navigation
            $('.nav-tab').on('click', this.handleTabClick.bind(this));
            
            // Bulk email notifications
            $('#tcd-send-bulk-notifications').on('click', this.handleBulkNotifications.bind(this));
        },

        initDocumentManager: function() {
            // Add document button
            $('#tcd-add-document').on('click', this.addDocumentRow.bind(this));
            
            // Remove document buttons
            $(document).on('click', '.tcd-remove-document', this.removeDocumentRow.bind(this));
            
            // Restore clear cache preference
            if (typeof Storage !== 'undefined') {
                const clearCachePref = localStorage.getItem('tcd_clear_cache_before_batch');
                if (clearCachePref === 'true') {
                    $('#tcd-clear-cache-before-batch').prop('checked', true);
                }
            }
        },

        handleSingleCheck: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const $button = $form.find('button[type="submit"]');
            const $result = $('#tcd-single-result');
            const itemId = $('#tcd-item-id').val();
            const sendEmail = $('#tcd-send-email-single').is(':checked');
            
            if (!itemId || itemId <= 0) {
                this.showError('Please enter a valid item ID.');
                return;
            }
            
            // Disable button and show loading
            $button.prop('disabled', true).text(this.getString('checking'));
            $result.hide();
            
            $.ajax({
                url: tcd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tcd_check_single_item',
                    item_id: itemId,
                    send_email: sendEmail ? 1 : 0,
                    nonce: tcd_ajax.nonce
                },
                timeout: 30000, // 30 seconds timeout
                success: (response) => {
                    console.log('TCD: Single check response:', response);
                    
                    if (response.success) {
                        $result.html(response.data.html).fadeIn();
                        
                        // Log debug info if available
                        if (response.data.debug) {
                            console.log('TCD Debug:', response.data.debug);
                        }
                        
                        // Show email notification result if sent
                        if (response.data.email_sent !== undefined) {
                            this.showInfo(response.data.email_message);
                        }
                    } else {
                        this.showError(response.data || this.getString('error'));
                    }
                },
                error: (xhr, status, error) => {
                    console.error('TCD: Single check error:', {xhr, status, error});
                    this.handleAjaxError(xhr, status, error);
                },
                complete: () => {
                    $button.prop('disabled', false).text('Check Documents');
                }
            });
        },

        handleBatchCheck: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const collectionId = $('#tcd-collection-id').val();
            const perPage = $('#tcd-per-page').val();
            const clearCacheFirst = $('#tcd-clear-cache-before-batch').is(':checked');
            const sendEmails = $('#tcd-send-emails-batch').is(':checked');
            
            if (!collectionId || collectionId <= 0) {
                this.showError('Please enter a valid collection ID.');
                return;
            }
            
            if (clearCacheFirst) {
                // Clear cache first, then run batch check
                this.clearCache(() => {
                    this.showSuccess(this.getString('cache_cleared'));
                    setTimeout(() => {
                        this.runBatchCheck(collectionId, perPage, 1, sendEmails);
                    }, 500);
                });
            } else {
                this.runBatchCheck(collectionId, perPage, 1, sendEmails);
            }
        },

        runBatchCheck: function(collectionId, perPage, page, sendEmails) {
            const $button = $('#tcd-batch-check-form button[type="submit"]');
            const $progress = $('#tcd-batch-progress');
            const $progressBar = $progress.find('.tcd-progress-fill');
            const $progressText = $progress.find('.tcd-progress-text');
            const $result = $('#tcd-batch-result');
            
            // Show progress
            $progress.show();
            $button.prop('disabled', true);
            
            $.ajax({
                url: tcd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tcd_check_batch',
                    collection_id: collectionId,
                    per_page: perPage,
                    page: page,
                    send_emails: sendEmails ? 1 : 0,
                    nonce: tcd_ajax.nonce
                },
                timeout: 60000, // 60 seconds timeout for batch operations
                success: (response) => {
                    console.log('TCD: Batch check response:', response);
                    
                    if (response.success) {
                        const data = response.data;
                        
                        // Update progress
                        $progressBar.css('width', data.progress + '%');
                        $progressText.text(`Processing page ${data.page} of ${data.total_pages}`);
                        
                        // Show results
                        if (page === 1) {
                            $result.html(data.html).show();
                        } else {
                            $result.find('tbody').append($(data.html).find('tbody').html());
                            this.updateBatchSummary(data.summary);
                        }
                        
                        // Show email stats if available
                        if (data.email_stats) {
                            this.showInfo(data.email_message);
                        }
                        
                        // Continue to next page if available
                        if (data.has_more) {
                            setTimeout(() => {
                                this.runBatchCheck(collectionId, perPage, page + 1, sendEmails);
                            }, 500);
                        } else {
                            // Complete
                            $progressText.text(this.getString('check_complete'));
                            $button.prop('disabled', false);
                            setTimeout(() => {
                                $progress.fadeOut();
                            }, 2000);
                        }
                    } else {
                        this.showError(response.data || this.getString('error'));
                        $button.prop('disabled', false);
                        $progress.hide();
                    }
                },
                error: (xhr, status, error) => {
                    console.error('TCD: Batch check error:', {xhr, status, error});
                    this.handleAjaxError(xhr, status, error);
                    $button.prop('disabled', false);
                    $progress.hide();
                }
            });
        },

        updateBatchSummary: function(summary) {
            $('.tcd-stat-complete').text(`Complete: ${summary.complete}`);
            $('.tcd-stat-incomplete').text(`Incomplete: ${summary.incomplete}`);
            $('.tcd-stat-error').text(`Errors: ${summary.error}`);
        },

        handleClearCache: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const originalText = $button.text();
            
            $button.prop('disabled', true).text(this.getString('clearing_cache'));
            
            this.clearCache(() => {
                $button.prop('disabled', false).text(originalText);
                this.showSuccess(this.getString('cache_cleared'));
            }, () => {
                $button.prop('disabled', false).text(originalText);
            });
        },

        clearCache: function(successCallback, errorCallback) {
            $.ajax({
                url: tcd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tcd_clear_cache',
                    nonce: tcd_ajax.nonce
                },
                timeout: 15000, // 15 seconds timeout
                success: (response) => {
                    console.log('TCD: Clear cache response:', response);
                    
                    if (response.success && successCallback) {
                        successCallback();
                    } else if (errorCallback) {
                        errorCallback();
                    }
                },
                error: (xhr, status, error) => {
                    console.error('TCD: Clear cache error:', {xhr, status, error});
                    this.handleAjaxError(xhr, status, error);
                    if (errorCallback) {
                        errorCallback();
                    }
                }
            });
        },

        handleTabClick: function(e) {
            // Allow default browser behavior for tab navigation
            // WordPress handles the active state
        },

        handleBulkNotifications: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const collectionId = $('#tcd-bulk-collection-id').val();
            const originalText = $button.text();
            
            if (!collectionId) {
                this.showError('Please enter a collection ID');
                return;
            }
            
            $button.prop('disabled', true).text(this.getString('sending_emails'));
            
            $.ajax({
                url: tcd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tcd_send_notifications',
                    collection_id: collectionId,
                    nonce: tcd_ajax.nonce
                },
                timeout: 60000, // 60 seconds timeout for email operations
                success: (response) => {
                    console.log('TCD: Bulk notifications response:', response);
                    
                    if (response.success) {
                        this.showSuccess(response.data.message);
                    } else {
                        this.showError(response.data || this.getString('error'));
                    }
                },
                error: (xhr, status, error) => {
                    console.error('TCD: Bulk notifications error:', {xhr, status, error});
                    this.handleAjaxError(xhr, status, error);
                },
                complete: () => {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        sendSingleNotification: function(itemId) {
            console.log('TCD: Sending single notification for item:', itemId);
            
            const $button = $(`button[onclick*="${itemId}"]`);
            const $result = $(`#tcd-email-result-${itemId}`);
            
            if ($button.length === 0) {
                console.error('TCD: Button not found for item', itemId);
                this.showError('Email button not found for item ' + itemId);
                return;
            }
            
            const originalText = $button.text();
            $button.prop('disabled', true).text(this.getString('sending_emails'));
            $result.html('');
            
            $.ajax({
                url: tcd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tcd_send_notifications',
                    item_ids: [itemId],
                    nonce: tcd_ajax.nonce
                },
                timeout: 30000, // 30 seconds timeout
                success: (response) => {
                    console.log('TCD: Single notification response:', response);
                    
                    if (response.success) {
                        $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                    } else {
                        $result.html('<div class="notice notice-error"><p>' + (response.data || this.getString('error')) + '</p></div>');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('TCD: Single notification error:', {xhr, status, error});
                    $result.html('<div class="notice notice-error"><p>' + this.getAjaxErrorMessage(xhr, status, error) + '</p></div>');
                },
                complete: () => {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        addDocumentRow: function(e) {
            if (e) e.preventDefault();
            
            const $container = $('#tcd-documents-container');
            const $newRow = $('<div class="tcd-document-row">' +
                '<input type="text" name="tcd_required_documents[]" class="regular-text" required>' +
                '<button type="button" class="button tcd-remove-document">Remove</button>' +
                '</div>');
            
            $container.append($newRow);
            $newRow.find('input').focus();
        },

        removeDocumentRow: function(e) {
            if (e) e.preventDefault();
            
            const $row = $(e.target).closest('.tcd-document-row');
            
            // Ensure at least one document remains
            if ($('.tcd-document-row').length > 1) {
                $row.fadeOut(function() {
                    $(this).remove();
                });
            } else {
                alert('At least one document name is required.');
            }
        },

        handleAjaxError: function(xhr, status, error) {
            const message = this.getAjaxErrorMessage(xhr, status, error);
            this.showError(message);
        },

        getAjaxErrorMessage: function(xhr, status, error) {
            if (status === 'timeout') {
                return 'Request timed out. Please try again.';
            } else if (xhr.status === 404) {
                return 'AJAX endpoint not found (404). Please check WordPress configuration.';
            } else if (xhr.status === 503) {
                return 'Server temporarily unavailable (503). Please try again later.';
            } else if (xhr.status === 500) {
                return 'Internal server error (500). Please check server logs.';
            } else if (xhr.status === 0) {
                return 'Network error. Please check your internet connection.';
            } else if (xhr.responseText) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    return response.data || response.message || this.getString('error');
                } catch (e) {
                    return 'Server error: ' + xhr.responseText.substring(0, 100);
                }
            } else {
                return this.getString('error') + ' (' + status + ')';
            }
        },

        getString: function(key) {
            if (typeof tcd_ajax !== 'undefined' && 
                typeof tcd_ajax.strings !== 'undefined' && 
                typeof tcd_ajax.strings[key] !== 'undefined') {
                return tcd_ajax.strings[key];
            }
            
            // Fallback strings
            const fallbacks = {
                'checking': 'Checking...',
                'check_complete': 'Check complete',
                'error': 'An error occurred',
                'clearing_cache': 'Clearing cache...',
                'clear_cache': 'Clear Cache',
                'cache_cleared': 'Cache cleared successfully',
                'sending_emails': 'Sending emails...',
                'emails_sent': 'Email notifications sent'
            };
            
            return fallbacks[key] || 'Unknown';
        },

        showError: function(message) {
            const $error = $('<div class="notice notice-error is-dismissible">' +
                '<p>' + this.escapeHtml(message) + '</p>' +
                '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>' +
                '</div>');
            
            $('.wrap > h1').after($error);
            
            // Make dismiss button work
            $error.find('.notice-dismiss').on('click', function() {
                $error.fadeOut(function() {
                    $(this).remove();
                });
            });
            
            // Auto-dismiss after 8 seconds
            setTimeout(() => {
                if ($error.is(':visible')) {
                    $error.fadeOut(() => {
                        $error.remove();
                    });
                }
            }, 8000);
        },

        showSuccess: function(message) {
            const $success = $('<div class="notice notice-success is-dismissible">' +
                '<p>' + this.escapeHtml(message) + '</p>' +
                '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>' +
                '</div>');
            
            $('.wrap > h1').after($success);
            
            // Make dismiss button work
            $success.find('.notice-dismiss').on('click', function() {
                $success.fadeOut(function() {
                    $(this).remove();
                });
            });
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                if ($success.is(':visible')) {
                    $success.fadeOut(() => {
                        $success.remove();
                    });
                }
            }, 5000);
        },

        showInfo: function(message) {
            const $info = $('<div class="notice notice-info is-dismissible">' +
                '<p>' + this.escapeHtml(message) + '</p>' +
                '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>' +
                '</div>');
            
            $('.wrap > h1').after($info);
            
            // Make dismiss button work
            $info.find('.notice-dismiss').on('click', function() {
                $info.fadeOut(function() {
                    $(this).remove();
                });
            });
            
            // Auto-dismiss after 6 seconds
            setTimeout(() => {
                if ($info.is(':visible')) {
                    $info.fadeOut(() => {
                        $info.remove();
                    });
                }
            }, 6000);
        },

        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    };

    // Make TCD available globally for inline onclick handlers
    window.TCD = TCD;

    // Initialize when document is ready
    $(document).ready(() => {
        console.log('TCD: Document ready, initializing...');
        
        // Check if we're on the plugin page and if tcd_ajax is loaded
        if (typeof tcd_ajax !== 'undefined') {
            console.log('TCD: Configuration loaded, starting initialization...');
            TCD.init();
        } else {
            console.warn('TCD: tcd_ajax not loaded, skipping initialization');
        }
    });

    // Additional safety check after window load
    $(window).on('load', () => {
        if (typeof window.TCD === 'undefined') {
            console.error('TCD: Global TCD object not available after window load');
        } else {
            console.log('TCD: Global TCD object confirmed available');
        }
    });

})(jQuery);