(function($) {
    'use strict';
    
    const MM = {
        init: function() {
            this.bindEvents();
            this.initToggles();
            this.initModals();
            this.initUpload();
        },
        
        bindEvents: function() {
            // Module actions
            $(document).on('change', '.oc-module-toggle', this.toggleModule);
            $(document).on('click', '.oc-scan-module', this.scanModule);
            $(document).on('click', '.oc-delete-module', this.deleteModule);
            $(document).on('click', '.oc-view-stats', this.viewStats);
            $(document).on('click', '.oc-config-module', this.configModule);
            
            // Auto-fix
            $(document).on('click', '.oc-auto-fix-btn', this.autoFix);
            
            // Cache and optimization
            $(document).on('click', '.oc-clear-cache, .oc-clear-cache-btn', this.clearCache);
            $(document).on('click', '.oc-clean-db-btn', this.cleanDatabase);
            $(document).on('click', '.oc-clean-logs-btn', this.cleanLogs);
            $(document).on('click', '.oc-apply-suggestion', this.applySuggestion);
            
            // Log actions
            $(document).on('click', '.oc-resolve-log', this.resolveLog);
            $(document).on('click', '.oc-export-logs', this.exportLogs);
            $(document).on('click', '.oc-clear-all-logs', this.clearAllLogs);
            $(document).on('click', '.oc-toggle-details', this.toggleDetails);
            
            // Filter
            $(document).on('change', '#oc-log-filter', this.filterLogs);
            
            // Statistics
            $(document).on('click', '.oc-export-stats', this.exportStats);
            
            // Bulk actions
            $(document).on('click', '.oc-apply-bulk-action', this.applyBulkAction);
            $(document).on('change', '.oc-select-all', this.selectAll);
            
            // Scan modules directory
            $(document).on('click', '#scan-modules-btn', this.scanModulesDirectory);
        },
        
        initToggles: function() {
            // Initialize any toggle switches
        },
        
        initModals: function() {
            // Close modal when clicking X or outside
            $(document).on('click', '.oc-modal-close', function() {
                $(this).closest('.oc-modal').fadeOut();
            });
            
            $(document).on('click', '.oc-modal', function(e) {
                if (e.target === this) {
                    $(this).fadeOut();
                }
            });
        },
        
        initUpload: function() {
            const $fileInput = $('#module_file');
            const $uploadForm = $('#oc-upload-form');
            const $uploadLabel = $('.oc-upload-label');
            const $fileInfo = $('#oc-file-info');
            
            // File selection
            $fileInput.on('change', function() {
                const file = this.files[0];
                if (file) {
                    $('#oc-file-name').text(file.name);
                    $('#oc-file-size').text(MM.formatFileSize(file.size));
                    $fileInfo.fadeIn();
                }
            });
            
            // Drag and drop
            $uploadLabel.on('dragover', function(e) {
                e.preventDefault();
                $(this).addClass('oc-drag-over');
            });
            
            $uploadLabel.on('dragleave', function() {
                $(this).removeClass('oc-drag-over');
            });
            
            $uploadLabel.on('drop', function(e) {
                e.preventDefault();
                $(this).removeClass('oc-drag-over');
                
                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    $fileInput[0].files = files;
                    $fileInput.trigger('change');
                }
            });
            
            // Form submission
            $uploadForm.on('submit', function(e) {
                e.preventDefault();
                MM.uploadModule(this);
            });
            
            // Cancel button
            $('#oc-cancel-btn').on('click', function() {
                $uploadForm[0].reset();
                $fileInfo.hide();
            });
        },
        
        toggleModule: function() {
            const $toggle = $(this);
            const moduleId = $toggle.data('module-id');
            const isActive = $toggle.is(':checked');
            const action = isActive ? 'mm_activate_module' : 'mm_deactivate_module';
            
            $.ajax({
                url: ocAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: action,
                    nonce: ocAdmin.nonce,
                    module_id: moduleId
                },
                beforeSend: function() {
                    $toggle.prop('disabled', true);
                },
                success: function(response) {
                    if (!response.success) {
                        // Only show error messages, not success messages
                        MM.showNotice(response.data.message, 'error');
                        $toggle.prop('checked', !isActive);
                    }
                    // Success - no notice shown
                },
                error: function() {
                    MM.showNotice('An error occurred. Please try again.', 'error');
                    $toggle.prop('checked', !isActive);
                },
                complete: function() {
                    $toggle.prop('disabled', false);
                }
            });
        },
        
        scanModule: function() {
            const moduleId = $(this).data('module-id');
            const $modal = $('#oc-scan-modal');
            const $results = $('#oc-scan-results');
            
            $results.html('<div class="oc-loading"></div><p>Scanning module...</p>');
            $modal.fadeIn();
            
            $.ajax({
                url: ocAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'mm_scan_module',
                    nonce: ocAdmin.nonce,
                    module_id: moduleId
                },
                success: function(response) {
                    if (response.success) {
                        $results.html(MM.formatScanResults(response.data));
                    } else {
                        $results.html('<p class="oc-text-error">' + response.data.message + '</p>');
                    }
                },
                error: function() {
                    $results.html('<p class="oc-text-error">Failed to scan module.</p>');
                }
            });
        },
        
        formatScanResults: function(data) {
            let html = '';
            
            // Syntax errors
            if (data.syntax && data.syntax.errors.length > 0) {
                html += '<div class="oc-section"><h3 class="oc-text-error">Syntax Errors</h3>';
                data.syntax.errors.forEach(function(error) {
                    html += MM.formatIssue(error, 'error', data.module_id);
                });
                html += '</div>';
            }
            
            // Conflicts
            if (data.conflicts && data.conflicts.errors.length > 0) {
                html += '<div class="oc-section"><h3 class="oc-text-error">Conflicts</h3>';
                data.conflicts.errors.forEach(function(error) {
                    html += MM.formatIssue(error, 'error', data.module_id);
                });
                html += '</div>';
            }
            
            // Warnings
            const warnings = [
                ...(data.syntax ? data.syntax.warnings : []),
                ...(data.conflicts ? data.conflicts.warnings : [])
            ];
            
            if (warnings.length > 0) {
                html += '<div class="oc-section"><h3 class="oc-text-warning">Warnings</h3>';
                warnings.forEach(function(warning) {
                    html += MM.formatIssue(warning, 'warning', data.module_id);
                });
                html += '</div>';
            }
            
            // Security warnings
            if (data.security && data.security.length > 0) {
                html += '<div class="oc-section"><h3 class="oc-text-warning">Security Warnings</h3>';
                data.security.forEach(function(warning) {
                    html += '<div class="oc-suggestion oc-suggestion-medium">';
                    html += '<div class="oc-suggestion-icon">⚠️</div>';
                    html += '<div class="oc-suggestion-content">';
                    html += '<p>' + warning + '</p>';
                    html += '</div></div>';
                });
                html += '</div>';
            }
            
            if (html === '') {
                html = '<div class="oc-section oc-text-success">';
                html += '<p>✅ No issues found! Module is ready to use.</p>';
                html += '</div>';
            }
            
            return html;
        },
        
        formatIssue: function(issue, type, moduleId) {
            let html = '<div class="oc-suggestion oc-suggestion-' + (type === 'error' ? 'high' : 'medium') + '">';
            html += '<div class="oc-suggestion-icon">' + (type === 'error' ? '❌' : '⚠️') + '</div>';
            html += '<div class="oc-suggestion-content">';
            html += '<p><strong>' + issue.message + '</strong></p>';
            
            if (issue.suggestion) {
                html += '<p>' + issue.suggestion + '</p>';
            }
            
            if (issue.auto_fix_available) {
                html += '<button class="button button-primary oc-auto-fix-btn" ';
                html += 'data-module-id="' + moduleId + '" ';
                html += 'data-action="' + issue.auto_fix_action + '" ';
                html += 'data-fix-data=\'' + JSON.stringify(issue.auto_fix_data || {}) + '\'>';
                html += '🔧 Auto-Fix</button>';
            }
            
            html += '</div></div>';
            return html;
        },
        
        autoFix: function() {
            const $btn = $(this);
            const moduleId = $btn.data('module-id');
            const action = $btn.data('action');
            const fixData = $btn.data('fix-data');
            
            if (!confirm('Apply this fix? A backup will be created automatically.')) {
                return;
            }
            
            $.ajax({
                url: ocAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'mm_auto_fix',
                    nonce: ocAdmin.nonce,
                    module_id: moduleId,
                    fix_action: action,
                    fix_data: JSON.stringify(fixData)
                },
                beforeSend: function() {
                    $btn.prop('disabled', true).text('Applying fix...');
                },
                success: function(response) {
                    if (response.success) {
                        MM.showNotice(response.data.message, 'success');
                        $btn.closest('.oc-suggestion').fadeOut();
                    } else {
                        MM.showNotice(response.data.message, 'error');
                        $btn.prop('disabled', false).text('🔧 Auto-Fix');
                    }
                },
                error: function() {
                    MM.showNotice('Failed to apply fix.', 'error');
                    $btn.prop('disabled', false).text('🔧 Auto-Fix');
                }
            });
        },
        
        deleteModule: function() {
            if (!confirm(ocAdmin.strings.confirm_delete)) {
                return;
            }
            
            const moduleId = $(this).data('module-id');
            const $row = $(this).closest('tr');
            
            $.ajax({
                url: ocAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'mm_delete_module',
                    nonce: ocAdmin.nonce,
                    module_id: moduleId
                },
                beforeSend: function() {
                    $row.css('opacity', '0.5');
                },
                success: function(response) {
                    if (response.success) {
                        MM.showNotice(response.data.message, 'success');
                        $row.fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        MM.showNotice(response.data.message, 'error');
                        $row.css('opacity', '1');
                    }
                },
                error: function() {
                    MM.showNotice('Failed to delete module.', 'error');
                    $row.css('opacity', '1');
                }
            });
        },
        
        scanModulesDirectory: function() {
            const $btn = $('#scan-modules-btn');
            const $result = $('#scan-result');
            
            $.ajax({
                url: ocAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'mm_scan_modules_directory',
                    nonce: ocAdmin.nonce
                },
                beforeSend: function() {
                    $btn.prop('disabled', true).text('⏳ Scanning...');
                    $result.html('<div class="notice notice-info"><p>Scanning modules directory...</p></div>').fadeIn();
                },
                success: function(response) {
                    console.log('Scan response:', response);
                    if (response.success) {
                        const count = response.data.registered_count;
                        if (count > 0) {
                            $result.html('<div class="notice notice-success"><p>✅ ' + response.data.message + '</p></div>');
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            $result.html('<div class="notice notice-info"><p>ℹ️ No new modules found.</p></div>');
                        }
                    } else {
                        console.error('Scan error:', response.data);
                        $result.html('<div class="notice notice-error"><p>❌ ' + (response.data.message || 'Unknown error') + '</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', xhr, status, error);
                    console.error('Response text:', xhr.responseText);
                    let errorMsg = 'Failed to scan modules directory.';
                    if (xhr.responseText) {
                        try {
                            const errorData = JSON.parse(xhr.responseText);
                            if (errorData.data && errorData.data.message) {
                                errorMsg = errorData.data.message;
                            }
                        } catch(e) {
                            errorMsg += ' Check console for details.';
                        }
                    }
                    $result.html('<div class="notice notice-error"><p>❌ ' + errorMsg + '</p></div>');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('🔍 Scan for Modules');
                    setTimeout(function() {
                        $result.fadeOut();
                    }, 5000);
                }
            });
        },
        
        viewStats: function() {
            const moduleId = $(this).data('module-id');
            const $modal = $('#oc-stats-modal');
            const $content = $('#oc-stats-content');
            
            $content.html('<div class="oc-loading"></div><p>Loading statistics...</p>');
            $modal.fadeIn();
            
            $.ajax({
                url: ocAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'mm_get_module_stats',
                    nonce: ocAdmin.nonce,
                    module_id: moduleId
                },
                success: function(response) {
                    if (response.success) {
                        $content.html(MM.formatStats(response.data));
                    } else {
                        $content.html('<p class="oc-text-error">' + response.data.message + '</p>');
                    }
                },
                error: function() {
                    $content.html('<p class="oc-text-error">Failed to load statistics.</p>');
                }
            });
        },
        
        formatStats: function(data) {
            let html = '<div class="oc-stats-display">';
            
            html += '<div class="oc-stats-grid">';
            html += '<div class="oc-stat-card">';
            html += '<h3>' + data.module_info.execution_count + '</h3>';
            html += '<p>Total Executions</p>';
            html += '</div>';
            
            html += '<div class="oc-stat-card">';
            html += '<h3>' + data.error_count + '</h3>';
            html += '<p>Errors</p>';
            html += '</div>';
            
            html += '<div class="oc-stat-card">';
            html += '<h3>' + data.uptime + '%</h3>';
            html += '<p>Success Rate</p>';
            html += '</div>';
            html += '</div>';
            
            html += '</div>';
            return html;
        },
        
        configModule: function() {
            const moduleId = $(this).data('module-id');
            // Placeholder for configuration modal
            alert('Configuration modal for ' + moduleId);
        },
        
        uploadModule: function(form) {
            const formData = new FormData(form);
            formData.append('action', 'mm_upload_module');
            
            const $progress = $('#oc-upload-progress');
            const $result = $('#oc-upload-result');
            const $btn = $('#oc-upload-btn');
            
            $progress.fadeIn();
            $result.hide();
            
            $.ajax({
                url: ocAdmin.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    $btn.prop('disabled', true);
                },
                success: function(response) {
                    $progress.hide();
                    
                    if (response.success) {
                        $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>').fadeIn();
                        // Redirect to modules page instead of staying on upload page
                        setTimeout(function() {
                            window.location.href = 'admin.php?page=onyx-command-modules';
                        }, 1500);
                    } else {
                        let errorHtml = '<div class="notice notice-error"><p>' + response.data.message + '</p>';
                        
                        if (response.data.data) {
                            errorHtml += '<div class="oc-error-details">';
                            if (response.data.data.errors) {
                                errorHtml += '<h4>Errors:</h4><ul>';
                                response.data.data.errors.forEach(function(error) {
                                    errorHtml += '<li>' + error.message + '</li>';
                                });
                                errorHtml += '</ul>';
                            }
                            errorHtml += '</div>';
                        }
                        
                        errorHtml += '</div>';
                        $result.html(errorHtml).fadeIn();
                    }
                },
                error: function() {
                    $progress.hide();
                    $result.html('<div class="notice notice-error"><p>Upload failed. Please try again.</p></div>').fadeIn();
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        },
        
        clearCache: function() {
            const $btn = $(this);
            
            $.ajax({
                url: ocAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'mm_clear_caches',
                    nonce: ocAdmin.nonce
                },
                beforeSend: function() {
                    $btn.prop('disabled', true).text('Clearing...');
                },
                success: function(response) {
                    if (response.success) {
                        MM.showNotice(response.data.message, 'success');
                    } else {
                        MM.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    MM.showNotice('Failed to clear caches.', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text($btn.hasClass('oc-clear-cache-btn') ? 'Clear All Caches' : 'Clear Cache');
                }
            });
        },
        
        cleanDatabase: function() {
            if (!confirm(ocAdmin.strings.confirm_clean_db)) {
                return;
            }
            
            const $btn = $(this);
            
            $.ajax({
                url: ocAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'mm_clean_database',
                    nonce: ocAdmin.nonce
                },
                beforeSend: function() {
                    $btn.prop('disabled', true).text('Cleaning...');
                },
                success: function(response) {
                    if (response.success) {
                        MM.showNotice(response.data.message + ' Details: ' + JSON.stringify(response.data.details), 'success');
                    } else {
                        MM.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    MM.showNotice('Failed to clean database.', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Clean Database');
                }
            });
        },
        
        cleanLogs: function() {
            const days = $('#oc-log-days').val();
            const $btn = $(this);
            
            $.ajax({
                url: ocAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'mm_clean_logs',
                    nonce: ocAdmin.nonce,
                    days: days
                },
                beforeSend: function() {
                    $btn.prop('disabled', true).text('Cleaning...');
                },
                success: function(response) {
                    if (response.success) {
                        MM.showNotice(response.data.message, 'success');
                    } else {
                        MM.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    MM.showNotice('Failed to clean logs.', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Clean Logs');
                }
            });
        },
        
        applySuggestion: function() {
            const action = $(this).data('action');
            
            if (action === 'clear_all_caches') {
                MM.clearCache.call(this);
            } else if (action === 'clean_database') {
                MM.cleanDatabase.call(this);
            }
        },
        
        resolveLog: function() {
            const logId = $(this).data('log-id');
            const $row = $(this).closest('tr');
            
            $.ajax({
                url: ocAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'mm_resolve_log',
                    nonce: ocAdmin.nonce,
                    log_id: logId
                },
                success: function(response) {
                    if (response.success) {
                        $row.addClass('oc-log-resolved');
                        $row.find('.oc-badge-unresolved').replaceWith('<span class="oc-badge-resolved">✓ Resolved</span>');
                        $row.find('.oc-resolve-log').remove();
                        MM.showNotice(response.data.message, 'success');
                    } else {
                        MM.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    MM.showNotice('Failed to resolve log.', 'error');
                }
            });
        },
        
        exportLogs: function() {
            window.location.href = ocAdmin.ajax_url + '?action=mm_export_logs&nonce=' + ocAdmin.nonce;
        },
        
        clearAllLogs: function() {
            if (!confirm(ocAdmin.strings.confirm_clear_logs)) {
                return;
            }
            
            $.ajax({
                url: ocAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'mm_clear_all_logs',
                    nonce: ocAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        MM.showNotice(response.data.message, 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        MM.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    MM.showNotice('Failed to clear logs.', 'error');
                }
            });
        },
        
        toggleDetails: function() {
            const $details = $(this).siblings('.oc-log-details');
            $details.slideToggle();
            $(this).text($details.is(':visible') ? 'Hide Details' : 'Show Details');
        },
        
        filterLogs: function() {
            const filter = $(this).val();
            window.location.href = window.location.pathname + '?page=onyx-command-logs&filter=' + filter;
        },
        
        exportStats: function() {
            window.location.href = ocAdmin.ajax_url + '?action=mm_export_stats&nonce=' + ocAdmin.nonce;
        },
        
        applyBulkAction: function() {
            const action = $('.oc-bulk-action-select').val();
            const moduleIds = [];
            
            $('.oc-module-checkbox:checked').each(function() {
                moduleIds.push($(this).val());
            });
            
            if (moduleIds.length === 0) {
                alert('Please select at least one module.');
                return;
            }
            
            if (!confirm('Apply this action to ' + moduleIds.length + ' module(s)?')) {
                return;
            }
            
            // Process each module
            moduleIds.forEach(function(moduleId) {
                let ajaxAction = '';
                
                switch(action) {
                    case 'activate':
                        ajaxAction = 'mm_activate_module';
                        break;
                    case 'deactivate':
                        ajaxAction = 'mm_deactivate_module';
                        break;
                    case 'delete':
                        ajaxAction = 'mm_delete_module';
                        break;
                }
                
                if (ajaxAction) {
                    $.ajax({
                        url: ocAdmin.ajax_url,
                        type: 'POST',
                        data: {
                            action: ajaxAction,
                            nonce: ocAdmin.nonce,
                            module_id: moduleId
                        }
                    });
                }
            });
            
            setTimeout(function() {
                location.reload();
            }, 1000);
        },
        
        selectAll: function() {
            const isChecked = $(this).is(':checked');
            $('.oc-module-checkbox').prop('checked', isChecked);
        },
        
        showNotice: function(message, type) {
            const noticeClass = 'notice notice-' + type;
            const $notice = $('<div class="' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.wrap').prepend($notice);
            
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },
        
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        MM.init();
    });
    
})(jQuery);
