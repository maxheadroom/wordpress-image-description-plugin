<?php
/**
 * Settings management class
 * 
 * Handles plugin settings page and configuration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WP_Image_Descriptions_Settings {
    
    /**
     * Settings page slug
     */
    private $page_slug = 'wp-image-descriptions-settings';
    
    /**
     * Settings option name
     */
    private $option_name = 'wp_image_descriptions_settings';
    
    /**
     * Add settings page to WordPress admin menu
     */
    public function add_settings_page() {
        // Placeholder - will be implemented in Prompt 2
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        // Placeholder - will be implemented in Prompt 2
    }
    
    /**
     * Get setting value
     */
    public function get_setting($key, $default = null) {
        $settings = get_option($this->option_name, array());
        
        // Support nested keys like 'api.endpoint'
        $keys = explode('.', $key);
        $value = $settings;
        
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }
        
        return $value;
    }
}
