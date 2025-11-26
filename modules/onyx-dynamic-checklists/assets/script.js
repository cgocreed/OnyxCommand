jQuery(document).ready(function($) {
    // Initialize accordion in meta box
    if ($(''.onyx-checklists-accordion'').length) {
        $(''.onyx-checklists-accordion'').accordion({
            collapsible: true,
            active: false,
            heightStyle: ''content''
        });
    }
    
    // Handle checkbox changes in meta box
    $(document).on(''change'', ''.checklist-item-checkbox'', function() {
        var $checkbox = $(this);
        var $list = $checkbox.closest(''.checklist-items'');
        var checklistId = $list.data(''checklist-id'');
        var postId = $(''#onyx-post-id'').val();
        
        // Get all checked items
        var checkedItems = [];
        $list.find(''.checklist-item-checkbox:checked'').each(function() {
            checkedItems.push(parseInt($(this).data(''item-index'')));
        });
        
        // Update visual state
        $checkbox.closest(''li'').toggleClass(''checked'', $checkbox.is('':checked''));
        
        // Save progress via AJAX
        $.ajax({
            url: onyxChecklists.ajax_url,
            type: ''POST'',
            data: {
                action: ''odc_update_progress'',
                nonce: onyxChecklists.nonce,
                post_id: postId,
                checklist_id: checklistId,
                checked_items: checkedItems
            }
        });
    });
    
    // Management page functionality
    if ($(''.onyx-checklists-page'').length) {
        // Save checklist
        $(''#checklist-form'').on(''submit'', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $message = $(''#checklist-message'');
            
            $.ajax({
                url: onyxChecklists.ajax_url,
                type: ''POST'',
                data: {
                    action: ''odc_save_checklist'',
                    nonce: onyxChecklists.nonce,
                    id: $(''#checklist-id'').val(),
                    name: $(''#checklist-name'').val(),
                    description: $(''#checklist-description'').val(),
                    items: $(''#checklist-items'').val()
                },
                success: function(response) {
                    if (response.success) {
                        $message.removeClass(''notice-error'').addClass(''notice-success'').html(response.data.message).show();
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        $message.removeClass(''notice-success'').addClass(''notice-error'').html(response.data).show();
                    }
                }
            });
        });
        
        // Edit checklist
        $(document).on(''click'', ''.edit-checklist'', function() {
            var checklistId = $(this).data(''id'');
            
            // Get checklist data via AJAX or from page data
            // For now, we''ll reload the page with edit mode
            // In production, you''d fetch via AJAX
            alert(''Edit functionality - fetch checklist data via AJAX and populate form'');
        });
        
        // Delete checklist
        $(document).on(''click'', ''.delete-checklist'', function() {
            if (!confirm(''Are you sure you want to delete this checklist? This will also remove all progress data.'')) {
                return;
            }
            
            var checklistId = $(this).data(''id'');
            var $row = $(this).closest(''tr'');
            
            $.ajax({
                url: onyxChecklists.ajax_url,
                type: ''POST'',
                data: {
                    action: ''odc_delete_checklist'',
                    nonce: onyxChecklists.nonce,
                    id: checklistId
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        alert(''Error: '' + response.data);
                    }
                }
            });
        });
        
        // Simplify with AI
        $(''#simplify-with-ai'').on(''click'', function() {
            var $button = $(this);
            var checklistText = $(''#checklist-items'').val();
            
            if (!checklistText.trim()) {
                alert(''Please enter some checklist items first.'');
                return;
            }
            
            $button.prop(''disabled'', true).text(''Simplifying...'');
            
            $.ajax({
                url: onyxChecklists.ajax_url,
                type: ''POST'',
                data: {
                    action: ''odc_simplify_checklist'',
                    nonce: onyxChecklists.nonce,
                    checklist_text: checklistText
                },
                success: function(response) {
                    $button.prop(''disabled'', false).text(''✨ Simplify with AI'');
                    
                    if (response.success) {
                        $(''#checklist-items'').val(response.data.simplified_text);
                        $(''#checklist-message'').removeClass(''notice-error'').addClass(''notice-success'').html(''Checklist simplified successfully!'').show();
                        setTimeout(function() {
                            $(''#checklist-message'').fadeOut();
                        }, 3000);
                    } else {
                        $(''#checklist-message'').removeClass(''notice-success'').addClass(''notice-error'').html(''Error: '' + response.data).show();
                    }
                },
                error: function() {
                    $button.prop(''disabled'', false).text(''✨ Simplify with AI'');
                    $(''#checklist-message'').removeClass(''notice-success'').addClass(''notice-error'').html(''Error communicating with server'').show();
                }
            });
        });
        
        // Cancel edit
        $(''#cancel-edit'').on(''click'', function() {
            $(''#checklist-form'')[0].reset();
            $(''#checklist-id'').val('''');
            $(''#editor-title'').text(''Create New Checklist'');
            $(this).hide();
        });
    }
});
