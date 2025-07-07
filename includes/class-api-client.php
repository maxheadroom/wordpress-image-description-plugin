<?php
/**
 * API Client class
 * 
 * Handles communication with OpenAI-compatible APIs
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WP_Image_Descriptions_API_Client {
    
    /**
     * Generate description for image
     */
    public function generate_description($image_url, $prompt_template) {
        // Placeholder - will be implemented in Prompt 3
        return array(
            'success' => false,
            'description' => '',
            'error' => 'Not implemented yet'
        );
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        // Placeholder - will be implemented in Prompt 3
        return array(
            'success' => false,
            'error' => 'Not implemented yet'
        );
    }
}
