/**
 * Admin JavaScript for Tainacan Document Checker
 */
jQuery(document).ready(function($) {
    'use strict';

    // Tab navigation
    $('.tcd-tab').on('click', function(e) {
        e.preventDefault();
        
        const tab = $(this).data('tab');
        
        // Update active tab
        $('.tcd-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Show corresponding content
        $('.tcd-tab-content').removeClass('active');
        $('.tcd-tab-content.tcd-' + tab).addClass('active');
        
        // Update URL without reload
        window.history.pushState({}, '', $(this).attr('href'));
    });

    // Single item check
    $('#tcd-check-single').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        const $result = $('#tcd-single-result');
        const $sendEmail = $form.find('#tcd-send-email-single');
        
        // Disable button and show loading
        $button.prop('disabled', true).text(tcd_ajax.strings.checking);
        $result.html('<div class="notice notice-info"><p>' + tcd_ajax.strings.checking + '</p></div>');
        
        $.post(tcd_ajax.ajax_url, {
            action: 'tcd_check_single_item',
            item_id: $('#tcd-item-id').val(),
            send_email: $sendEmail.is(':checked') ? 'true' : 'false',
            nonce: tcd_ajax.nonce
        })
        .done(function(response) {
            if (response.success) {
                $result.html(response.data.html);
                if (response.data.email_sent) {
                    $result.prepend('<div class="notice notice-success"><p>' + tcd_ajax.strings.emails_sent + '</p></div>');
                }
            } else {
                $result.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
            }
        })
        .fail(function() {
            $result.html('<div class="notice notice-error"><p>' + tcd_ajax.strings.error + '</p></div>');
        })
        .always(function() {
            $button.prop('disabled', false).text(tcd_ajax.strings.check_complete);
        });
    });

    // Batch check
    $('#tcd-check-batch').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        const $result = $('#tcd-batch-result');
        const $progress = $('#tcd-batch-progress');
        const $sendEmail = $form.find('#tcd-send-email-batch');
        
        // Disable button and show loading
        $button.prop('disabled', true).text(tcd_ajax.strings.checking);
        $progress.show();
        $result.html('');
        
        const collectionId = $('#tcd-collection-id').val();
        const perPage = $('#tcd-per-page').val() || 10;
        let currentPage = 1;
        
        function checkBatch(page) {
            $.post(tcd_ajax.ajax_url, {
                action: 'tcd_check_batch',
                collection_id: collectionId,
                page: page,
                per_page: perPage,
                send_email: $sendEmail.is(':checked') ? 'true' : 'false',
                nonce: tcd_ajax.nonce
            })
            .done(function(response) {
                if (response.success) {
                    $result.html(response.data.html);
                    
                    // Update progress
                    const progress = (response.data.result.page / response.data.result.total_pages) * 100;
                    $progress.find('.progress-bar').css('width', progress + '%');
                    
                    // Check if there are more pages
                    if (response.data.result.page < response.data.result.total_pages) {
                        // Continue with next page
                        setTimeout(function() {
                            checkBatch(response.data.result.page + 1);
                        }, 500);
                    } else {
                        // Batch complete
                        $button.prop('disabled', false).text(tcd_ajax.strings.check_complete);
                        $progress.hide();
                        
                        if (response.data.email_results && response.data.email_results.sent > 0) {
                            $result.prepend('<div class="notice notice-success"><p>' + 
                                'Emails sent: ' + response.data.email_results.sent + 
                                (response.data.email_results.failed > 0 ? ', Failed: ' + response.data.email_results.failed : '') +
                                '</p></div>');
                        }
                    }
                } else {
                    $result.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                    $button.prop('disabled', false).text('Check Collection');
                    $progress.hide();
                }
            })
            .fail(function() {
                $result.html('<div class="notice notice-error"><p>' + tcd_ajax.strings.error + '</p></div>');
                $button.prop('disabled', false).text('Check Collection');
                $progress.hide();
            });
        }
        
        // Start batch check
        checkBatch(currentPage);
    });

    // Get item history
    $('#tcd-get-history').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        const $result = $('#tcd-history-result');
        
        $button.prop('disabled', true).text('Loading...');
        
        $.post(tcd_ajax.ajax_url, {
            action: 'tcd_get_item_history',
            item_id: $('#tcd-history-item-id').val(),
            nonce: tcd_ajax.nonce
        })
        .done(function(response) {
            if (response.success) {
                $result.html(response.data.html);
            } else {
                $result.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
            }
        })
        .fail(function() {
            $result.html('<div class="notice notice-error"><p>' + tcd_ajax.strings.error + '</p></div>');
        })
        .always(function() {
            $button.prop('disabled', false).text('Get History');
        });
    });

    // Clear cache
    $('#tcd-clear-cache').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        
        if (!confirm('Are you sure you want to clear all cached results?')) {
            return;
        }
        
        $button.prop('disabled', true).text(tcd_ajax.strings.clearing_cache);
        
        $.post(tcd_ajax.ajax_url, {
            action: 'tcd_clear_cache',
            nonce: tcd_ajax.nonce
        })
        .done(function(response) {
            if (response.success) {
                alert(response.data.message);
            } else {
                alert('Error: ' + response.data);
            }
        })
        .fail(function() {
            alert(tcd_ajax.strings.error);
        })
        .always(function() {
            $button.prop('disabled', false).text(tcd_ajax.strings.clear_cache);
        });
    });

    // Document manager
    $('#tcd-add-document').on('click', function() {
        const newRow = $('<div class="tcd-document-row">' +
            '<input type="text" name="tcd_required_documents[]" value="" placeholder="Enter document name" />' +
            '<button type="button" class="button tcd-remove-document">Remove</button>' +
            '</div>');
        $('#tcd-documents-container').append(newRow);
    });

    $(document).on('click', '.tcd-remove-document', function() {
        $(this).closest('.tcd-document-row').remove();
    });

    // Test SMTP Configuration
    $('#tcd-test-smtp').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $email = $('#tcd-test-email');
        const testEmail = $email.val();
        
        if (!testEmail || !testEmail.includes('@')) {
            alert('Please enter a valid email address');
            return;
        }
        
        // Update button state
        $button.prop('disabled', true).text('Sending...');
        
        // Send test email
        $.post(tcd_ajax.ajax_url, {
            action: 'tcd_send_test_email',
            email: testEmail,
            nonce: tcd_ajax.nonce
        })
        .done(function(response) {
            if (response.success) {
                alert(response.data.message);
                $email.val(''); // Clear the field on success
            } else {
                alert('Error: ' + response.data);
            }
        })
        .fail(function(jqXHR, textStatus, errorThrown) {
            console.error('AJAX Error:', textStatus, errorThrown);
            alert('Failed to send test email. Please check your server configuration.');
        })
        .always(function() {
            $button.prop('disabled', false).text('Send Test Email');
        });
    });

    // Email logs
    $('#tcd-view-email-logs').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $logsContainer = $('#tcd-email-logs');
        
        $button.prop('disabled', true).text('Loading...');
        
        $.post(tcd_ajax.ajax_url, {
            action: 'tcd_get_email_logs',
            nonce: tcd_ajax.nonce
        })
        .done(function(response) {
            if (response.success) {
                $logsContainer.html(response.data.html).show();
            } else {
                $logsContainer.html('<div class="notice notice-error"><p>' + response.data + '</p></div>').show();
            }
        })
        .fail(function() {
            $logsContainer.html('<div class="notice notice-error"><p>Failed to load email logs</p></div>').show();
        })
        .always(function() {
            $button.prop('disabled', false).text('View Email Logs');
        });
    });

    // Toggle SMTP fields based on checkbox
    $('#tcd_smtp_enabled').on('change', function() {
        const $smtpFields = $('.tcd-smtp-fields');
        if ($(this).is(':checked')) {
            $smtpFields.show();
        } else {
            $smtpFields.hide();
        }
    }).trigger('change');

    // Toggle email enabled fields
    $('#tcd_email_enabled').on('change', function() {
        const $emailFields = $('.tcd-email-settings');
        if ($(this).is(':checked')) {
            $emailFields.show();
        } else {
            $emailFields.hide();
        }
    }).trigger('change');

    // Initialize tooltips if available
    if ($.fn.tooltip) {
        $('.tcd-tooltip').tooltip();
    }

    // Auto-save settings on change (optional)
    let saveTimeout;
    $('.tcd-auto-save').on('change', function() {
        clearTimeout(saveTimeout);
        const $indicator = $('#tcd-save-indicator');
        
        $indicator.text('Saving...').show();
        
        saveTimeout = setTimeout(function() {
            // You could implement auto-save here
            $indicator.text('Changes saved').delay(2000).fadeOut();
        }, 1000);
    });

    // Handle settings form submission via AJAX (optional enhancement)
    $('#tcd-settings-form').on('submit', function(e) {
        // Allow normal form submission for now
        // You could enhance this with AJAX later
    });

    // Debug mode toggle - show/hide debug information
    $('#tcd_debug_mode').on('change', function() {
        if ($(this).is(':checked')) {
            $('.tcd-debug').show();
        } else {
            $('.tcd-debug').hide();
        }
    });

    // Collection ID autocomplete (if you have many collections)
    // This would require an additional AJAX endpoint to fetch collections
    /*
    $('#tcd-collection-id').on('input', function() {
        // Implement autocomplete if needed
    });
    */

    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Ctrl+S to save settings
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            $('#tcd-settings-form').submit();
        }
    });

    // Responsive table handling
    function makeTablesResponsive() {
        $('.wp-list-table').each(function() {
            if (!$(this).parent().hasClass('table-responsive')) {
                $(this).wrap('<div class="table-responsive"></div>');
            }
        });
    }
    
    makeTablesResponsive();
    
    // Re-run after AJAX content loads
    $(document).ajaxComplete(function() {
        makeTablesResponsive();
    });
});
