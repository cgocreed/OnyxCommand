<?php
/**
 * Example Module for Onyx Command
 * 
 * This is a sample module that demonstrates how to create modules
 * for the Onyx Command plugin.
 * 
 * Module ID: example-hello-world
 * Module Name: Hello World Example
 * Description: A simple example module that adds a dashboard widget
 * Version: 1.0.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main module class
 */
class OC_Example_Hello_World {
    
    /**
     * Initialize the module
     */
    public function __construct() {
        // Only run in admin area
        if (is_admin()) {
            add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
        }
    }
    
    /**
     * Add dashboard widget
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'mm_hello_world_widget',
            'Hello World from Onyx Command',
            array($this, 'render_dashboard_widget')
        );
    }
    
    /**
     * Render dashboard widget content
     */
    public function render_dashboard_widget() {
        echo '<p>ðŸ‘‹ Hello! This widget was added by a Onyx Command module.</p>';
        echo '<p>Module ID: <code>example-hello-world</code></p>';
        echo '<p>This demonstrates how easy it is to extend WordPress with custom modules!</p>';
    }
}

// Initialize the module
new OC_Example_Hello_World();