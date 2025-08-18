/**
 * Tainacan Document Checker Admin JavaScript
 *
 * @package TainacanDocumentChecker
 * @since 1.0.0
 */

(function($) {
    'use strict';

    const TCD = {
        init: function() {
            this.bindEvents();
            this.initDocumentManager();
        },

        bindEvents: function() {
            // Single check form
            $('#tcd-single-check-form').on('submit', this.handleSingleCheck);
            
            // Batch check form
            $('#tcd-batch-check-form').on('submit', this.handleBatchCheck);
            
            // Clear cache button
            $('#tcd-clear-cache-btn').on('click', this.handleClearCache);
            
            // Clear cache before batch check
            $('#tcd-clear-cache-before-batch').on('change', function() {
                localStorage.setItem('tcd_clear_cache_before_batch', $(this).is(':checked'));
            });
            
            // Tab navigation
            $('.nav-tab').on('click', this.handleTabClick);
            
            // Bulk email notifications
            $('#tcd-send-bulk-notifications').on('click', this.handleBulkNotifications);
        },

        initDocumentManager: function() {
            // Add document button
            $('#tcd-add-document').on('click', this.addDocumentRow);
            
            // Remove document buttons
            $(document).on('click', '.tcd-remove-document', this.removeDocumentRow);
            
            // Restore clear cache preference
            const clearCachePref = localStorage.getItem('tcd_clear_cache_before_batch');
            if (clearCachePref === 'true') {
                $('#tcd-clear-cache-before-batch').prop('checked', true);
            }
        },

        handleSingleCheck: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $button = $form.find('button[type="submit"]');
            const $result = $('#tcd-single-result');
            const itemId = $('#tcd-item-id').val();
            const sendEmail = $('#tcd-send-email-single').is(':checked');
            
            // Disable button and show loading
            $button.prop('disabled', true).text(tcd_ajax.strings.checking);
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
                success: function(response) {
                    if (response.success) {
                        $result.html(response.data.html).fadeIn();
                        
                        // Log debug info if available
                        if (response.data.debug) {
                            console.log('TCD Debug:', response.data.debug);
                        }
                        
                        // Show email notification result if sent
                        if (response.data.email_sent !== undefined) {
                            TCD.showInfo(response.data.email_message);
                        }
                    } else {
                        TCD.showError(response.data || tcd_ajax.strings.error);
                    }
                },
                error: function() {
                    TCD.showError(tcd_ajax.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text(tcd_ajax.strings.check_complete);
                }
            });
        },

        handleBatchCheck: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const collectionId = $('#tcd-collection-id').val();
            const perPage = $('#tcd-per-page').val();
            const clearCacheFirst = $('#tcd-clear-cache-before-batch').is(':checked');
            const sendEmails = $('#tcd-send-emails-batch').is(':checked');
            
            if (clearCacheFirst) {
                // Clear cache first, then run batch check
                TCD.clearCache(function() {
                    TCD.showSuccess(tcd_ajax.strings.cache_cleared);
                    setTimeout(function() {
                        TCD.runBatchCheck(collectionId, perPage, 1, sendEmails);
                    }, 500);
                });
            } else {
                TCD.runBatchCheck(collectionId, perPage, 1, sendEmails);
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
                success: function(response) {
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
                            TCD.updateBatchSummary(data.summary);
                        }
                        
                        // Show email stats if available
                        if (data.email_stats) {
                            TCD.showInfo(data.email_message);
                        }
                        
                        // Continue to next page if available
                        if (data.has_more) {
                            setTimeout(function() {
                                TCD.runBatchCheck(collectionId, perPage, page + 1, sendEmails);
                            }, 500);
                        } else {
                            // Complete
                            $progressText.text(tcd_ajax.strings.check_complete);
                            $button.prop('disabled', false);
                            setTimeout(function() {
                                $progress.fadeOut();
                            }, 2000);
                        }
                    } else {
                        TCD.showError(response.data || tcd_ajax.strings.error);
                        $button.prop('disabled', false);
                        $progress.hide();
                    }
                },
                error: function() {
                    TCD.showError(tcd_ajax.strings.error);
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
            
            const $button = $(this);
            $button.prop('disabled', true).text(tcd_ajax.strings.clearing_cache);
            
            TCD.clearCache(function() {
                $button.prop('disabled', false).text(tcd_ajax.strings.clear_cache);
                TCD.showSuccess(tcd_ajax.strings.cache_cleared);
            });
        },

        clearCache: function(callback) {
            $.ajax({
                url: tcd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tcd_clear_cache',
                    nonce: tcd_ajax.nonce
                },
                success: function(response) {
                    if (response.success && callback) {
                        callback();
                    }
                },
                error: function() {
                    TCD.showError(tcd_ajax.strings.error);
                }
            });
        },

        handleTabClick: function(e) {
            // Allow default browser behavior for tab navigation
            // WordPress handles the active state
        },

        handleBulkNotifications: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const collectionId = $('#tcd-bulk-collection-id').val();
            
            if (!collectionId) {
                TCD.showError('Please enter a collection ID');
                return;
            }
            
            $button.prop('disabled', true).text(tcd_ajax.strings.sending_emails);
            
            $.ajax({
                url: tcd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tcd_send_notifications',
                    collection_id: collectionId,
                    nonce: tcd_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        TCD.showSuccess(response.data.message);
                    } else {
                        TCD.showError(response.data || tcd_ajax.strings.error);
                    }
                },
                error: function() {
                    TCD.showError(tcd_ajax.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text('Send Email Notifications');
                }
            });
        },

        sendSingleNotification: function(itemId) {
            const $button = $(`button[onclick*="${itemId}"]`);
            const $result = $(`#tcd-email-result-${itemId}`);
            
            $button.prop('disabled', true).text(tcd_ajax.strings.sending_emails);
            $result.html('');
            
            $.ajax({
                url: tcd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tcd_send_notifications',
                    item_ids: [itemId],
                    nonce: tcd_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                    } else {
                        $result.html('<div class="notice notice-error"><p>' + (response.data || tcd_ajax.strings.error) + '</p></div>');
                    }
                },
                error: function() {
                    $result.html('<div class="notice notice-error"><p>' + tcd_ajax.strings.error + '</p></div>');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Send Email to User');
                }
            });
        },

        addDocumentRow: function() {
            const $container = $('#tcd-documents-container');
            const $newRow = $('<div class="tcd-document-row">' +
                '<input type="text" name="tcd_required_documents[]" class="regular-text" required>' +
                '<button type="button" class="button tcd-remove-document">Remove</button>' +
                '</div>');
            
            $container.append($newRow);
            $newRow.find('input').focus();
        },

        removeDocumentRow: function() {
            const $row = $(this).closest('.tcd-document-row');
            
            // Ensure at least one document remains
            if ($('.tcd-document-row').length > 1) {
                $row.fadeOut(function() {
                    $(this).remove();
                });
            } else {
                alert('At least one document name is required.');
            }
        },

        showError: function(message) {
            const $error = $('<div class="notice notice-error is-dismissible">' +
                '<p>' + message + '</p>' +
                '</div>');
            
            $('.wrap > h1').after($error);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $error.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        showSuccess: function(message) {
            const $success = $('<div class="notice notice-success is-dismissible">' +
                '<p>' + message + '</p>' +
                '</div>');
            
            $('.wrap > h1').after($success);
            
            // Auto-dismiss after 3 seconds
            setTimeout(function() {
                $success.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        },

        showInfo: function(message) {
            const $info = $('<div class="notice notice-info is-dismissible">' +
                '<p>' + message + '</p>' +
                '</div>');
            
            $('.wrap > h1').after($info);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $info.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    // Make TCD available globally for inline onclick handlers
    window.TCD = TCD;

    // Initialize when document is ready
    $(document).ready(function() {
        TCD.init();
    });

})(jQuery);