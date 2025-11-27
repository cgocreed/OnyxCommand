(function($){
    var MM = {
        // ... other methods omitted for brevity ...

        formatStats: function(data) {
            var html = '<div class="oc-stats-display">';
            
            html += '<div class="oc-stats-grid">';
            
            // Errors
            html += '<div class="oc-stat-card">';
            html += '<h3>' + data.error_count + '</h3>';
            html += '<p>Errors</p>';
            html += '</div>';
            
            // Uptime / Success Rate
            html += '<div class="oc-stat-card">';
            html += '<h3>' + data.uptime + '%</h3>';
            html += '<p>Success Rate</p>';
            html += '</div>';
            html += '</div>';
            
            html += '</div>';
            return html;
        },

        // ... rest of file unchanged ...
    };

    window.MM = MM;
})(jQuery);