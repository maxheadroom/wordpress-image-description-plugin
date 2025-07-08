<?php
/**
 * Core plugin class
 * 
 * Handles plugin initialization, component coordination, and hook registration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WP_Image_Descriptions_Core {
    
    /**
     * Plugin components
     */
    private $settings;
    private $media_library;
    private $api_client;
    private $batch_manager;
    private $queue_processor;
    private $preview_page;
    
    /**
     * Initialize the plugin core
     */
    public function init() {
        // Initialize components
        $this->init_components();
        
        // Register hooks
        $this->register_hooks();
        
        // Load text domain for translations
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Initialize settings (admin only)
        if (is_admin()) {
            $this->settings = new WP_Image_Descriptions_Settings();
            $this->media_library = new WP_Image_Descriptions_Media_Library();
            $this->preview_page = new WP_Image_Descriptions_Preview_Page();
        }
        
        // Initialize core components
        $this->api_client = new WP_Image_Descriptions_API_Client();
        $this->batch_manager = new WP_Image_Descriptions_Batch_Manager();
        $this->queue_processor = new WP_Image_Descriptions_Queue_Processor();
    }
    
    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        // Admin hooks
        if (is_admin()) {
            // Settings hooks
            if ($this->settings) {
                add_action('admin_menu', array($this->settings, 'add_settings_page'));
                add_action('admin_init', array($this->settings, 'register_settings'));
            }
            
            // Media library hooks - now handled by the Media Library class itself
            if ($this->media_library) {
                // Only register admin notices here, other hooks are handled by the class
                add_action('admin_notices', array($this->media_library, 'display_bulk_action_notices'));
            }
            
            // Preview page hooks
            if ($this->preview_page) {
                add_action('admin_menu', array($this->preview_page, 'add_preview_page'));
            }
            
            // Admin notices
            add_action('admin_notices', array($this, 'display_admin_notices'));
            
            // AJAX hooks
            add_action('wp_ajax_wp_image_descriptions_process_batch', array($this, 'ajax_process_batch'));
            add_action('wp_ajax_wp_image_descriptions_apply_batch', array($this, 'ajax_apply_batch'));
        }
        
        // General hooks
        add_action('wp_loaded', array($this, 'check_requirements'));
        
        // Cron hooks for batch processing
        add_action('wp_image_descriptions_process_batch', array($this, 'cron_process_batch'));
    }
    
    /**
     * Load plugin text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'wp-image-descriptions',
            false,
            dirname(plugin_basename(WP_IMAGE_DESCRIPTIONS_PLUGIN_FILE)) . '/languages'
        );
    }
    
    /**
     * Check plugin requirements
     */
    public function check_requirements() {
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            add_action('admin_notices', array($this, 'wordpress_version_notice'));
            return false;
        }
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            add_action('admin_notices', array($this, 'php_version_notice'));
            return false;
        }
        
        // Check required functions
        if (!function_exists('wp_remote_post')) {
            add_action('admin_notices', array($this, 'http_api_notice'));
            return false;
        }
        
        return true;
    }
    
    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        // Debug notice (remove after testing)
        if (isset($_GET['page']) && $_GET['page'] === 'wp-image-descriptions-debug') {
            echo '<div class="notice notice-info">';
            echo '<p><strong>WP Image Descriptions Debug Info:</strong></p>';
            echo '<p>Plugin loaded: Yes</p>';
            echo '<p>WordPress version: ' . get_bloginfo('version') . '</p>';
            echo '<p>Current user can edit_posts: ' . (current_user_can('edit_posts') ? 'Yes' : 'No') . '</p>';
            echo '<p>Media Library class loaded: ' . (class_exists('WP_Image_Descriptions_Media_Library') ? 'Yes' : 'No') . '</p>';
            echo '</div>';
        }
        
        // Check for success/error messages in URL parameters
        if (isset($_GET['wp_image_descriptions_message'])) {
            $message_type = sanitize_text_field($_GET['wp_image_descriptions_message']);
            
            switch ($message_type) {
                case 'batch_created':
                    echo '<div class="notice notice-success is-dismissible">';
                    echo '<p>' . esc_html__('Batch processing started successfully.', 'wp-image-descriptions') . '</p>';
                    echo '</div>';
                    break;
                    
                case 'batch_completed':
                    echo '<div class="notice notice-success is-dismissible">';
                    echo '<p>' . esc_html__('Image descriptions applied successfully.', 'wp-image-descriptions') . '</p>';
                    echo '</div>';
                    break;
                    
                case 'batch_error':
                    echo '<div class="notice notice-error is-dismissible">';
                    echo '<p>' . esc_html__('An error occurred while processing images.', 'wp-image-descriptions') . '</p>';
                    echo '</div>';
                    break;
            }
        }
    }
    
    /**
     * AJAX handler for batch processing
     */
    public function ajax_process_batch() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wp_image_descriptions_process')) {
            wp_die('Security check failed');
        }
        
        // Check capabilities
        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }
        
        // Get batch ID
        $batch_id = sanitize_text_field($_POST['batch_id']);
        
        // Process batch
        if ($this->queue_processor) {
            $result = $this->queue_processor->process_batch($batch_id);
            wp_send_json_success($result);
        } else {
            wp_send_json_error('Queue processor not available');
        }
    }
    
    /**
     * AJAX handler for applying batch results
     */
    public function ajax_apply_batch() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wp_image_descriptions_apply')) {
            wp_die('Security check failed');
        }
        
        // Check capabilities
        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }
        
        // Get batch ID
        $batch_id = sanitize_text_field($_POST['batch_id']);
        
        // Apply batch results
        if ($this->batch_manager) {
            $result = $this->batch_manager->apply_batch_results($batch_id);
            wp_send_json_success($result);
        } else {
            wp_send_json_error('Batch manager not available');
        }
    }
    
    /**
     * WordPress version notice
     */
    public function wordpress_version_notice() {
        echo '<div class="notice notice-error">';
        echo '<p>' . sprintf(
            esc_html__('WP Image Descriptions requires WordPress 5.0 or higher. You are running version %s.', 'wp-image-descriptions'),
            get_bloginfo('version')
        ) . '</p>';
        echo '</div>';
    }
    
    /**
     * PHP version notice
     */
    public function php_version_notice() {
        echo '<div class="notice notice-error">';
        echo '<p>' . sprintf(
            esc_html__('WP Image Descriptions requires PHP 7.4 or higher. You are running version %s.', 'wp-image-descriptions'),
            PHP_VERSION
        ) . '</p>';
        echo '</div>';
    }
    
    /**
     * HTTP API notice
     */
    public function http_api_notice() {
        echo '<div class="notice notice-error">';
        echo '<p>' . esc_html__('WP Image Descriptions requires WordPress HTTP API functions to be available.', 'wp-image-descriptions') . '</p>';
        echo '</div>';
    }
    
    /**
     * Cron handler for batch processing
     */
    public function cron_process_batch($batch_id) {
        error_log('WP Image Descriptions: Cron processing batch ' . $batch_id);
        
        if ($this->queue_processor) {
            $result = $this->queue_processor->process_batch($batch_id);
            error_log('WP Image Descriptions: Cron batch processing result: ' . print_r($result, true));
        } else {
            error_log('WP Image Descriptions: Queue processor not available for cron processing');
        }
    }
    
    /**
     * Get component instance
     */
    public function get_component($component_name) {
        if (property_exists($this, $component_name)) {
            return $this->$component_name;
        }
        return null;
    }
}
