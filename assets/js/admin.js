/**
 * Tainacan Document Checker Admin JavaScript
 *
 * @package TainacanDocumentChecker
 * @since 1.0.0
 */

(function($) {
    'use strict';

    window.TCD = {
        init: function() {
            console.log('TCD: Initializing...');
            
            // Verificar se tcd_ajax está definido
            if (typeof tcd_ajax === 'undefined') {
                console.error('TCD: tcd_ajax object not found');
                return;
            }
            
            console.log('TCD: AJAX URL:', tcd_ajax.ajax_url);
            console.log('TCD: Nonce:', tcd_ajax.nonce ? 'Present' : 'Missing');
            
            this.bindEvents();
            this.initDocumentManager();
        },

        bindEvents: function() {
            // Single check form
            $('#tcd-single-check-form').on('submit', this.handleSingleCheck.bind(this));
            
            // Batch check form
            $('#tcd-batch-check-form').on('submit', this.handleBatchCheck.bind(this));
            
            // Clear cache button
            $('#tcd-clear-cache-btn').on('click', this.handleClearCache.bind(this));
            
            // Document management
            $('#tcd-add-document-btn').on('click', this.addDocumentField.bind(this));
            $(document).on('click', '.tcd-remove-document', this.removeDocumentField.bind(this));
            
            // SMTP test
            $('#tcd-smtp-test-btn').on('click', this.handleSmtpTest.bind(this));
            
            console.log('TCD: Events bound');
        },

        initDocumentManager: function() {
            // Initialize sortable for document list
            if ($('#tcd-documents-list').length && typeof $.fn.sortable !== 'undefined') {
                $('#tcd-documents-list').sortable({
                    handle: '.dashicons-move',
                    placeholder: 'tcd-document-placeholder',
                    update: function() {
                        console.log('TCD: Document order updated');
                    }
                });
            }
        },

        handleSingleCheck: function(e) {
            e.preventDefault();
            console.log('TCD: Starting single check...');
            
            const $form = $(e.target);
            const $button = $form.find('button[type="submit"]');
            const $result = $('#tcd-single-result');
            
            const itemId = $('#tcd-item-id').val();
            const sendEmail = $('#tcd-send-email-single').is(':checked');
            
            if (!itemId) {
                alert(tcd_ajax.strings?.item_required || 'Please enter an item ID');
                return;
            }
            
            // UI feedback
            $button.prop('disabled', true).text(tcd_ajax.strings?.checking || 'Checking...');
            $result.html('<div class="notice notice-info"><p>Processing...</p></div>').show();
            
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
                    console.log('TCD: Single check response:', response);
                    
                    if (response.success) {
                        $result.html(response.data.html);
                        
                        if (response.data.email_sent) {
                            $result.prepend('<div class="notice notice-success"><p>' + 
                                (tcd_ajax.strings?.email_sent || 'Email notification sent successfully') + 
                                '</p></div>');
                        } else if (response.data.email_message) {
                            $result.prepend('<div class="notice notice-info"><p>' + 
                                response.data.email_message + '</p></div>');
                        }
                    } else {
                        $result.html('<div class="notice notice-error"><p>' + 
                            response.data + '</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('TCD: AJAX error:', status, error);
                    $result.html('<div class="notice notice-error"><p>' + 
                        (tcd_ajax.strings?.error || 'An error occurred') + ': ' + error + 
                        '</p></div>');
                },
                complete: function() {
                    $button.prop('disabled', false).text(tcd_ajax.strings?.check_documents || 'Check Documents');
                }
            });
        },

        handleBatchCheck: function(e) {
            e.preventDefault();
            console.log('TCD: Starting batch check...');
            
            const $form = $(e.target);
            const $button = $form.find('button[type="submit"]');
            const $result = $('#tcd-batch-result');
            const $progress = $('#tcd-batch-progress');
            
            const collectionId = $('#tcd-collection-id').val();
            const perPage = $('#tcd-per-page').val() || 20;
            const sendEmail = $('#tcd-send-email-batch').is(':checked');
            
            if (!collectionId) {
                alert(tcd_ajax.strings?.collection_required || 'Please enter a collection ID');
                return;
            }
            
            console.log('TCD: Batch check parameters:', {
                collection: collectionId,
                perPage: perPage,
                sendEmail: sendEmail
            });
            
            // Reset and show progress
            $button.prop('disabled', true);
            $progress.show().find('.tcd-progress-bar').css('width', '0%');
            $result.html('').show();
            
            let currentPage = 1;
            let totalEmailsSent = 0;
            let totalEmailsFailed = 0;
            
            const processBatch = () => {
                $.ajax({
                    url: tcd_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'tcd_check_batch',
                        collection_id: collectionId,
                        page: currentPage,
                        per_page: perPage,
                        send_email: sendEmail ? 1 : 0,
                        nonce: tcd_ajax.nonce
                    },
                    success: function(response) {
                        console.log('TCD: Batch response for page', currentPage, response);
                        
                        if (response.success) {
                            // Update progress
                            const progress = response.data.progress || 0;
                            $progress.find('.tcd-progress-bar').css('width', progress + '%');
                            $progress.find('.tcd-progress-text').text(progress + '%');
                            
                            // Append results
                            $result.append(response.data.html);
                            
                            // Track email statistics
                            if (response.data.emails_sent !== undefined) {
                                totalEmailsSent += response.data.emails_sent;
                                totalEmailsFailed += response.data.emails_failed || 0;
                            }
                            
                            // Check if there are more pages
                            if (response.data.has_more) {
                                currentPage++;
                                setTimeout(processBatch, 500); // Small delay between batches
                            } else {
                                // Complete
                                $progress.find('.tcd-progress-text').text('Complete!');
                                $button.prop('disabled', false);
                                
                                // Show email summary if emails were sent
                                if (sendEmail && (totalEmailsSent > 0 || totalEmailsFailed > 0)) {
                                    const emailMessage = tcd_ajax.strings?.emails_sent || 
                                        'Email notifications: ' + totalEmailsSent + ' sent, ' + totalEmailsFailed + ' failed';
                                    $result.prepend('<div class="notice notice-success"><p>' + emailMessage + '</p></div>');
                                }
                            }
                        } else {
                            $result.html('<div class="notice notice-error"><p>' + 
                                response.data + '</p></div>');
                            $button.prop('disabled', false);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('TCD: Batch error:', status, error);
                        $result.html('<div class="notice notice-error"><p>Error: ' + error + '</p></div>');
                        $button.prop('disabled', false);
                    }
                });
            };
            
            processBatch();
        },

        handleClearCache: function(e) {
            e.preventDefault();
            console.log('TCD: Clearing cache...');
            
            const $button = $(e.target);
            $button.prop('disabled', true);
            
            $.ajax({
                url: tcd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tcd_clear_cache',
                    nonce: tcd_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(tcd_ajax.strings?.cache_cleared || 'Cache cleared successfully');
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert(tcd_ajax.strings?.error || 'An error occurred');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        addDocumentField: function(e) {
            e.preventDefault();
            
            const $list = $('#tcd-documents-list');
            const index = $list.find('.tcd-document-item').length;
            
            const html = `
                <div class="tcd-document-item">
                    <span class="dashicons dashicons-move"></span>
                    <input type="text" name="tcd_required_documents[]" value="" placeholder="${tcd_ajax.strings?.document_name || 'Document name'}" required>
                    <button type="button" class="button tcd-remove-document">
                        <span class="dashicons dashicons-no"></span>
                    </button>
                </div>
            `;
            
            $list.append(html);
        },

        removeDocumentField: function(e) {
            e.preventDefault();
            $(e.target).closest('.tcd-document-item').remove();
        },

        handleSmtpTest: function(e) {
            e.preventDefault();
            console.log('TCD: Testing SMTP...');
            
            const $button = $(e.target);
            const $result = $('#tcd-smtp-test-result');
            const testEmail = $('#tcd_test_email').val();
            
            if (!testEmail) {
                alert('Please enter a test email address');
                return;
            }
            
            $button.prop('disabled', true).text('Sending...');
            $result.html('');
            
            $.ajax({
                url: tcd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tcd_test_smtp',
                    test_email: testEmail,
                    nonce: tcd_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                    } else {
                        $result.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                    }
                },
                error: function() {
                    $result.html('<div class="notice notice-error"><p>An error occurred while testing SMTP</p></div>');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Send Test Email');
                }
            });
        },

        // Função para enviar notificação de email individual
        sendSingleNotification: function(itemId) {
            console.log('TCD: Sending notification for item', itemId);
            
            const $resultDiv = $('#tcd-email-result-' + itemId);
            $resultDiv.html('<span class="spinner is-active"></span> Sending...');
            
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
                        $resultDiv.html('<span style="color: green;">✔ Email sent successfully</span>');
                    } else {
                        $resultDiv.html('<span style="color: red;">✗ ' + response.data + '</span>');
                    }
                },
                error: function() {
                    $resultDiv.html('<span style="color: red;">✗ Failed to send email</span>');
                }
            });
        },

        // Função para enviar notificações em lote
        sendBatchEmails: function(itemIds) {
            console.log('TCD: Sending batch emails for items:', itemIds);
            
            const $resultDiv = $('#tcd-batch-email-result');
            $resultDiv.html('<span class="spinner is-active"></span> Sending emails...');
            
            $.ajax({
                url: tcd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tcd_send_notifications',
                    item_ids: itemIds,
                    nonce: tcd_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $resultDiv.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                    } else {
                        $resultDiv.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('TCD: Batch email error:', status, error);
                    $resultDiv.html('<div class="notice notice-error"><p>Failed to send emails. Error: ' + error + '</p></div>');
                }
            });
        },

        // Função para enviar notificações após batch check
        sendBatchNotifications: function(itemsChecked) {
            console.log('TCD: Sending notifications for batch check results');
            
            const $resultDiv = $('#tcd-batch-notification-result');
            
            // Filtrar apenas itens incompletos
            const incompleteItems = itemsChecked.filter(function(item) {
                return item.status === 'incomplete';
            });
            
            if (incompleteItems.length === 0) {
                $resultDiv.html('<div class="notice notice-warning"><p>No items need notifications.</p></div>');
                return;
            }
            
            // Extrair IDs dos itens incompletos
            const itemIds = incompleteItems.map(function(item) {
                return item.id;
            });
            
            console.log('TCD: Sending notifications for items:', itemIds);
            
            $resultDiv.html('<div class="notice notice-info"><p><span class="spinner is-active"></span> Sending email notifications...</p></div>');
            
            $.ajax({
                url: tcd_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tcd_send_notifications',
                    item_ids: itemIds,
                    nonce: tcd_ajax.nonce
                },
                success: function(response) {
                    console.log('TCD: Notification response:', response);
                    
                    if (response.success) {
                        const stats = response.data.stats || {};
                        let message = '<strong>Email notifications sent!</strong><br>';
                        message += 'Sent: ' + (stats.emails_sent || 0) + '<br>';
                        message += 'Failed: ' + (stats.emails_failed || 0) + '<br>';
                        if (stats.users_notified && stats.users_notified.length > 0) {
                            message += 'Users notified: ' + stats.users_notified.length;
                        }
                        $resultDiv.html('<div class="notice notice-success"><p>' + message + '</p></div>');
                    } else {
                        $resultDiv.html('<div class="notice notice-error"><p><strong>Error:</strong> ' + response.data + '</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('TCD: Notification error:', status, error);
                    $resultDiv.html('<div class="notice notice-error"><p><strong>Failed to send notifications.</strong> Error: ' + error + '</p></div>');
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        console.log('TCD: Document ready, initializing...');
        
        // Verificar se estamos na página correta
        if ($('.wrap').find('[class*="tcd-"]').length > 0) {
            console.log('TCD: Plugin page detected, starting initialization');
            
            // Aguardar um momento para garantir que tcd_ajax esteja disponível
            setTimeout(function() {
                if (typeof tcd_ajax !== 'undefined') {
                    console.log('TCD: Configuration loaded, starting initialization...');
                    window.TCD.init();
                    
                    // Tornar TCD globalmente acessível
                    if (!window.TCD) {
                        window.TCD = TCD;
                    }
                    console.log('TCD: Global TCD object confirmed available');
                } else {
                    console.error('TCD: tcd_ajax configuration not found after waiting');
                }
            }, 100);
        }
    });

})(jQuery);
