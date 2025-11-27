(function($){
    $(document).ready(function(){
        var $catSelect = $('#oc_checklist_category_select');
        var $accordion = $('#oc_checklists_accordion');
        var $stateInput = $('#oc_checklist_state');

        function renderAccordion(data) {
            $accordion.empty();
            var state = {};
            try {
                state = JSON.parse($stateInput.val() || '{}');
            } catch(e) { state = {}; }

            if (!data.length) {
                $accordion.html('<p style="color:#666;">No checklists found for this category.</p>');
                return;
            }

            data.forEach(function(cl) {
                var id = cl.id;
                var items = cl.items || [];
                var required = cl.required;
                var $panel = $('<div class="oc-checklist-panel"></div>');
                var $header = $('<div class="oc-checklist-header" style="cursor:pointer; padding:8px 10px; background:#f7f7f7; border:1px solid #e1e1e1;">' + cl.title + (required ? ' <strong style="color:#b00;">(Required)</strong>' : '') + '</div>');
                var $body = $('<div class="oc-checklist-body" style="display:none; padding:8px 10px; border-left:1px solid #e1e1e1; border-right:1px solid #e1e1e1; border-bottom:1px solid #e1e1e1;"></div>');
                var $ul = $('<ul style="margin:0; padding-left:18px;"></ul>');
                items.forEach(function(it, idx){
                    var checked = (state[id] && state[id].items && state[id].items[idx]) ? 'checked' : '';
                    var $li = $('<li style="margin:6px 0;"><label><input type="checkbox" data-cl-id="'+id+'" data-item-idx="'+idx+'" '+checked+'> ' + it + '</label></li>');
                    $ul.append($li);
                });
                $body.append($ul);
                $panel.append($header).append($body);
                $accordion.append($panel);

                if (!state[id]) {
                    state[id] = {items: [], complete: false};
                }
            });

            $stateInput.val(JSON.stringify(state));
        }

        $catSelect.on('change', function(){
            var term_id = $(this).val();
            $accordion.html('<p class="oc-loading">Loading checklists...</p>');
            if (!term_id) {
                $accordion.empty();
                return;
            }
            $.post(ocChecklists.ajax_url, {
                action: 'oc_get_checklists_for_category',
                nonce: ocChecklists.nonce,
                term_id: term_id
            }, function(resp){
                if (resp.success) {
                    renderAccordion(resp.data);
                } else {
                    $accordion.html('<p style="color:#900;">' + (resp.data && resp.data.message ? resp.data.message : 'Error loading') + '</p>');
                }
            });
        });

        // Toggle open/close
        $(document).on('click', '.oc-checklist-header', function(){
            var $body = $(this).next('.oc-checklist-body');
            $body.slideToggle(180);
        });

        // Track checkbox changes
        $(document).on('change', '#oc_checklists_accordion input[type=checkbox]', function(){
            var clId = $(this).data('cl-id');
            var idx = $(this).data('item-idx');
            var state = {};
            try { state = JSON.parse($stateInput.val() || '{}'); } catch(e) { state = {}; }
            state[clId] = state[clId] || {items: [], complete: false};
            state[clId].items[idx] = $(this).is(':checked') ? 1 : 0;
            // determine completion
            var allChecked = true;
            $('#oc_checklists_accordion input[data-cl-id="'+clId+'"]').each(function(){
                if (!$(this).is(':checked')) { allChecked = false; }
            });
            state[clId].complete = allChecked;
            $stateInput.val(JSON.stringify(state));
        });

        // When page loads, if query param oc_checklist_publish_failed is present show admin notice
        if (typeof window.location !== 'undefined' && window.location.search.indexOf('oc_checklist_publish_failed=1') !== -1) {
            var $notice = $('<div class="notice notice-error"><p>Publishing blocked: one or more required checklists are incomplete. Complete required checklists before publishing.</p></div>');
            $('.wrap').first().prepend($notice);
        }
    });
})(jQuery);
