(function($) {
    'use strict';
    
    console.log('AI Alt Tag Manager: Scripts loaded successfully');
    
    /**
     * Save alt tag to database via AJAX
     */
    function saveAltTagToDatabase(attachmentId, altTag, callback) {
        console.log('Saving alt tag to database for attachment:', attachmentId);
        
        $.ajax({
            url: aiAltTagManager.ajax_url,
            type: 'POST',
            data: {
                action: 'save_alt_tag',
                nonce: aiAltTagManager.nonce,
                attachment_id: attachmentId,
                alt_tag: altTag
            },
            success: function(response) {
                console.log('Save response:', response);
                if (response.success) {
                    console.log('Alt tag saved successfully');
                    if (callback) callback(true, response.data.message);
                } else {
                    console.log('Save failed:', response.data);
                    if (callback) callback(false, response.data);
                }
            },
            error: function(xhr, status, error) {
                console.log('Save error:', error);
                if (callback) callback(false, 'Connection error');
            }
        });
    }
    
    /**
     * Update all alt text fields with new value AND save to database
     */
    function updateAllAltFields(attachmentId, altTag) {
        console.log('Updating all alt fields for attachment:', attachmentId, 'with:', altTag);
        
        // SPECIFIC FIELD IDs - THE THREE PAGES YOU MENTIONED
        const specificSelectors = [
            '#attachment_alt',                           // post.php?post=X&action=edit
            '#attachment-details-two-column-alt-text',   // upload.php?item=X modal
            'textarea#attachment_alt',
            'input#attachment_alt',
            'textarea#attachment-details-two-column-alt-text',
            'input#attachment-details-two-column-alt-text'
        ];
        
        // Update specific fields
        let fieldsFound = 0;
        specificSelectors.forEach(function(selector) {
            const $field = $(selector);
            if ($field.length) {
                console.log('✓ Found specific field:', selector, '- Updating with:', altTag);
                $field.val(altTag).trigger('change').trigger('input').trigger('blur');
                fieldsFound++;
            }
        });
        
        console.log('Specific fields updated:', fieldsFound);
        
        // Generic WordPress patterns
        const genericSelectors = [
            '#attachments-' + attachmentId + '-_wp_attachment_image_alt',
            'input[name="attachments[' + attachmentId + '][_wp_attachment_image_alt]"]',
            'textarea[name="attachments[' + attachmentId + '][_wp_attachment_image_alt]"]',
            '#attachment_' + attachmentId + '_alt',
            
            // Modal and detail view fields
            '.attachment-details .setting[data-setting="alt"] input',
            '.attachment-details .setting[data-setting="alt"] textarea',
            '.compat-field-_wp_attachment_image_alt input',
            '.compat-field-_wp_attachment_image_alt textarea'
        ];
        
        genericSelectors.forEach(function(selector) {
            const $field = $(selector);
            if ($field.length) {
                console.log('✓ Found generic field:', selector);
                $field.val(altTag).trigger('change').trigger('input').trigger('blur');
            }
        });
        
        // Find by data-setting attribute in media modal
        $('[data-setting="alt"]').each(function() {
            const $input = $(this).find('input, textarea');
            if ($input.length) {
                console.log('✓ Found alt field in data-setting element');
                $input.val(altTag).trigger('change').trigger('input').trigger('blur');
            }
        });
        
        // Update WordPress media model (for upload.php?item=X)
        if (typeof wp !== 'undefined' && wp.media) {
            try {
                if (wp.media.frame) {
                    const state = wp.media.frame.state();
                    if (state && state.get) {
                        const selection = state.get('selection');
                        if (selection) {
                            const attachment = selection.findWhere({id: parseInt(attachmentId)});
                            if (attachment) {
                                console.log('✓ Updating WordPress media model via selection');
                                attachment.set('alt', altTag);
                                attachment.save();
                            }
                        }
                    }
                }
                
                // Update the attachment model directly
                if (wp.media.attachment) {
                    const attachment = wp.media.attachment(attachmentId);
                    if (attachment) {
                        console.log('✓ Updating attachment model directly');
                        attachment.set('alt', altTag);
                        attachment.save();
                    }
                }
            } catch(e) {
                console.log('Error updating WordPress media model:', e);
            }
        }
        
        // CRITICAL: Save to database via our AJAX handler
        saveAltTagToDatabase(attachmentId, altTag);
        
        console.log('All update methods executed for alt tag:', altTag);
    }
    
    // ========================================
    // HANDLER 1: Edit page (post.php?post=X&action=edit)
    // ========================================
    $(document).on('click', '.ai-generate-alt-button', function() {
        const $button = $(this);
        const attachmentId = $button.data('attachment-id');
        const $result = $button.siblings('.ai-alt-suggestion-result');
        const $loading = $button.siblings('.ai-alt-loading');
        
        console.log('Generate clicked for attachment:', attachmentId);
        
        $button.prop('disabled', true);
        $loading.show();
        $result.removeClass('show').hide();
        
        $.ajax({
            url: aiAltTagManager.ajax_url,
            type: 'POST',
            data: {
                action: 'generate_ai_alt_tag',
                nonce: aiAltTagManager.nonce,
                attachment_id: attachmentId
            },
            success: function(response) {
                $button.prop('disabled', false);
                $loading.hide();
                
                if (response.success) {
                    const altTag = response.data.alt_tag;
                    console.log('Generated alt tag:', altTag);
                    
                    // Update ALL fields everywhere AND save
                    updateAllAltFields(attachmentId, altTag);
                    
                    // Show success
                    $result.html('<strong>✓ Success!</strong> Alt tag generated and saved!<br><strong>Alt Tag:</strong> ' + altTag).addClass('show').css({
                        'border-left-color': '#00a32a',
                        'background': '#f0f6fc'
                    }).slideDown();
                    
                    $button.text('Regenerate AI Alt Tag');
                } else {
                    $result.html('<strong>✗ Error:</strong> ' + response.data).addClass('show').css({
                        'border-left-color': '#d63638',
                        'background': '#fcf0f1'
                    }).slideDown();
                }
            },
            error: function() {
                $button.prop('disabled', false);
                $loading.hide();
                $result.html('<strong>✗ Error:</strong> Connection failed').slideDown();
            }
        });
    });
    
    // ========================================
    // HANDLER 2: Media library column
    // ========================================
    $(document).on('click', '.ai-generate-alt-inline', function() {
        const $button = $(this);
        const attachmentId = $button.data('attachment-id');
        const $container = $button.closest('.ai-alt-column-content');
        const $status = $container.find('.ai-alt-status');
        const $display = $container.find('.ai-alt-display');
        
        $button.prop('disabled', true).text('...');
        $status.html('<span style="color: #2271b1;">⏳ Generating...</span>');
        
        $.ajax({
            url: aiAltTagManager.ajax_url,
            type: 'POST',
            data: {
                action: 'generate_ai_alt_tag',
                nonce: aiAltTagManager.nonce,
                attachment_id: attachmentId
            },
            success: function(response) {
                if (response.success) {
                    const altTag = response.data.alt_tag;
                    
                    // Update display
                    $display.html('<strong style="color: #00a32a;">✓</strong> <span class="ai-alt-text">' + altTag + '</span>');
                    $button.text('Regenerate').prop('disabled', false);
                    $status.html('<span style="color: #00a32a;">✓ Saved!</span>');
                    
                    // Add clear button if it doesn't exist
                    if (!$container.find('.ai-clear-alt-inline').length) {
                        $button.after('<button type="button" class="button button-small ai-clear-alt-inline" data-attachment-id="' + attachmentId + '" style="color: #d63638; margin-left: 4px;">Clear</button>');
                    }
                    
                    // Update all fields AND save
                    updateAllAltFields(attachmentId, altTag);
                    
                    setTimeout(() => $status.fadeOut(), 3000);
                } else {
                    $button.prop('disabled', false).text('Retry');
                    $status.html('<span style="color: #d63638;">✗ ' + response.data + '</span>');
                }
            },
            error: function() {
                $button.prop('disabled', false).text('Retry');
                $status.html('<span style="color: #d63638;">✗ Connection failed</span>');
            }
        });
    });
    
    // ========================================
    // HANDLER 3: Missing Alt Tags page - Generate button
    // ========================================
    $(document).on('click', '.ai-generate-single', function() {
        const $button = $(this);
        const $card = $button.closest('.ai-image-card');
        const attachmentId = $button.data('attachment-id');
        const $input = $card.find('.ai-alt-input');
        const $message = $card.find('.ai-result-message');
        
        console.log('Missing alt tags - Generate clicked for:', attachmentId);
        
        $button.prop('disabled', true).text('Generating...');
        $message.html('').hide();
        
        $.ajax({
            url: aiAltTagManager.ajax_url,
            type: 'POST',
            data: {
                action: 'generate_ai_alt_tag',
                nonce: aiAltTagManager.nonce,
                attachment_id: attachmentId
            },
            success: function(response) {
                $button.prop('disabled', false).text('Generate AI Alt Tag');
                
                if (response.success) {
                    const altTag = response.data.alt_tag;
                    console.log('Generated alt tag:', altTag);
                    
                    // Update input field
                    $input.val(altTag).trigger('change');
                    
                    // Update all fields AND save
                    updateAllAltFields(attachmentId, altTag);
                    
                    // Show success message
                    $message.html('<span style="color: #00a32a;">✓ Generated and saved!</span>').slideDown();
                    
                    // Change button text
                    $button.text('Regenerate AI Alt Tag');
                    
                    setTimeout(() => $message.fadeOut(), 3000);
                } else {
                    $message.html('<span style="color: #d63638;">✗ Error: ' + response.data + '</span>').slideDown();
                }
            },
            error: function() {
                $button.prop('disabled', false).text('Generate AI Alt Tag');
                $message.html('<span style="color: #d63638;">✗ Connection failed</span>').slideDown();
            }
        });
    });
    
    // ========================================
    // HANDLER 4: Missing Alt Tags page - Save button
    // ========================================
    $(document).on('click', '.ai-save-alt', function() {
        const $button = $(this);
        const $card = $button.closest('.ai-image-card');
        const attachmentId = $button.data('attachment-id');
        const $input = $card.find('.ai-alt-input');
        const $message = $card.find('.ai-result-message');
        const altTag = $input.val().trim();
        
        console.log('Missing alt tags - Save clicked for:', attachmentId, 'Alt:', altTag);
        
        if (!altTag) {
            $message.html('<span style="color: #d63638;">✗ Please enter alt text first</span>').slideDown();
            return;
        }
        
        $button.prop('disabled', true).text('Saving...');
        $message.html('').hide();
        
        saveAltTagToDatabase(attachmentId, altTag, function(success, message) {
            $button.prop('disabled', false).text('Save Alt Tag');
            
            if (success) {
                // Update all fields
                updateAllAltFields(attachmentId, altTag);
                
                // Show success and fade out card
                $message.html('<span style="color: #00a32a;">✓ Saved successfully!</span>').slideDown();
                
                setTimeout(function() {
                    $card.fadeOut(500, function() {
                        $(this).remove();
                        
                        // Check if no more cards
                        if ($('.ai-image-card').length === 0) {
                            $('.ai-images-grid').html('<div class="notice notice-success inline"><p>🎉 All images have alt tags!</p></div>');
                        }
                    });
                }, 1500);
            } else {
                $message.html('<span style="color: #d63638;">✗ Save failed: ' + message + '</span>').slideDown();
            }
        });
    });
    
    // ========================================
    // HANDLER 5: Missing Alt Tags page - Bulk Generate
    // ========================================
    $(document).on('click', '.ai-bulk-generate', function() {
        const $button = $(this);
        const $cards = $('.ai-image-card');
        const total = $cards.length;
        
        if (total === 0) return;
        
        if (!confirm('Generate AI alt tags for all ' + total + ' images? This may take a while.')) {
            return;
        }
        
        $button.prop('disabled', true).text('Processing...');
        
        let processed = 0;
        let success = 0;
        
        $cards.each(function(index) {
            const $card = $(this);
            const attachmentId = $card.find('.ai-generate-single').data('attachment-id');
            const $input = $card.find('.ai-alt-input');
            const $message = $card.find('.ai-result-message');
            
            // Delay each request by 2 seconds
            setTimeout(function() {
                $message.html('<span style="color: #2271b1;">⏳ Generating...</span>').show();
                
                $.ajax({
                    url: aiAltTagManager.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'generate_ai_alt_tag',
                        nonce: aiAltTagManager.nonce,
                        attachment_id: attachmentId
                    },
                    success: function(response) {
                        processed++;
                        
                        if (response.success) {
                            success++;
                            const altTag = response.data.alt_tag;
                            
                            // Update input
                            $input.val(altTag);
                            
                            // Update all fields AND save
                            updateAllAltFields(attachmentId, altTag);
                            
                            $message.html('<span style="color: #00a32a;">✓ Done!</span>');
                        } else {
                            $message.html('<span style="color: #d63638;">✗ Failed</span>');
                        }
                        
                        // Update button status
                        $button.text('Processing ' + processed + '/' + total);
                        
                        // If all done
                        if (processed === total) {
                            $button.prop('disabled', false).text('Bulk Generate AI Alt Tags');
                            alert('Completed! Successfully generated ' + success + ' out of ' + total + ' alt tags.');
                        }
                    },
                    error: function() {
                        processed++;
                        $message.html('<span style="color: #d63638;">✗ Error</span>');
                        $button.text('Processing ' + processed + '/' + total);
                        
                        if (processed === total) {
                            $button.prop('disabled', false).text('Bulk Generate AI Alt Tags');
                            alert('Completed! Successfully generated ' + success + ' out of ' + total + ' alt tags.');
                        }
                    }
                });
            }, index * 2000);  // 2 second delay between each
        });
    });
    
    // ========================================
    // HANDLER 6: Clear button in media library
    // ========================================
    $(document).on('click', '.ai-clear-alt-inline', function() {
        const $button = $(this);
        const attachmentId = $button.data('attachment-id');
        const $container = $button.closest('.ai-alt-column-content');
        const $status = $container.find('.ai-alt-status');
        const $display = $container.find('.ai-alt-display');
        const $generateBtn = $container.find('.ai-generate-alt-inline');
        
        if (!confirm('Remove alt tag from this image?')) return;
        
        $.ajax({
            url: aiAltTagManager.ajax_url,
            type: 'POST',
            data: {
                action: 'clear_alt_tag',
                nonce: aiAltTagManager.nonce,
                attachment_id: attachmentId
            },
            success: function(response) {
                if (response.success) {
                    $display.html('<strong style="color: #d63638;">✗</strong> <span class="ai-alt-text" style="color: #999;">No alt tag</span>');
                    $generateBtn.text('Generate');
                    $button.remove();
                    $status.html('<span style="color: #00a32a;">✓ Cleared!</span>');
                    
                    // Update all fields
                    updateAllAltFields(attachmentId, '');
                    
                    setTimeout(() => $status.fadeOut(), 3000);
                }
            }
        });
    });
    
})(jQuery);
